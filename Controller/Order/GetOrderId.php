<?php

namespace Madfu\MadfuPayment\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class GetOrderId extends Action
{
    protected $jsonFactory;
    protected $quoteIdMaskFactory;
    protected $orderRepository;
    protected $searchCriteriaBuilder;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log', Logger::DEBUG));
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $inputQuoteId = $this->getRequest()->getParam('quote_id', $this->getRequest()->getParam('masked_id'));

        try {
            $quoteId = $this->resolveQuoteId($inputQuoteId);

            if (!$quoteId) {
                throw new LocalizedException(__('A valid Quote ID is required.'));
            }

            $order = $this->getOrderByQuoteId($quoteId);
            if ($order) {
                return $result->setData([
                    'order_id' => $order->getEntityId()
                ]);
            } else {
                return $result->setData(['message' => 'No order found for this quote.']);
            }
        } catch (\Exception $e) {
            return $result->setData(['error' => $e->getMessage()]);
        }
    }

    protected function resolveQuoteId($inputQuoteId)
    {
        if (is_numeric($inputQuoteId)) {
            return $inputQuoteId;
        }

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($inputQuoteId, 'masked_id');
        return $quoteIdMask->getQuoteId() ?? null;
    }

    protected function getOrderByQuoteId($quoteId)
    {
        $this->logger->info("Searching for order with quote ID: " . $quoteId);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('quote_id', $quoteId)
            ->setPageSize(1)
            ->create();

        $orderList = $this->orderRepository->getList($searchCriteria);
        $this->logger->info("Found " . $orderList->getTotalCount() . " orders for quote ID: " . $quoteId);

        if ($orderList->getTotalCount() > 0) {
            return current($orderList->getItems());
        }

        return null;
    }

}

<?php
namespace Madfu\MadfuPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PaymentResponseObserver implements ObserverInterface
{
    protected $messageManager;
    protected $checkoutSession;
    protected $logger;
    protected $orderRepository;
    protected $searchCriteriaBuilder;
    protected $quoteIdMaskFactory;

    public function __construct(
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->messageManager = $messageManager;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log', Logger::DEBUG));
    }

    public function execute(Observer $observer)
    {
        $paymentStatus = $this->checkoutSession->getData('paymentStatus');
        $quoteId = $this->checkoutSession->getData('quoteId'); // Retrieve the quoteId from session

        if ($paymentStatus === 'success' && $quoteId) {
            $this->checkoutSession->unsetData('paymentStatus'); // Clear the session after use
            $this->checkoutSession->unsetData('quoteId');

            $realQuoteId = $this->resolveQuoteId($quoteId);

            if ($realQuoteId) {
                $criteria = $this->searchCriteriaBuilder->addFilter('quote_id', $realQuoteId, 'eq')->create();
                $orders = $this->orderRepository->getList($criteria)->getItems();

                if (count($orders) > 0) {
                    $order = array_values($orders)[0];
                    $order->setStatus('paid');
                    $this->orderRepository->save($order);
                    $this->messageManager->addSuccessMessage(__('Payment successful and order placed.'));
                    $this->logger->info('Order status updated to Paid.');
                } else {
                    $this->logger->error('No order found with quote ID: ' . $realQuoteId);
                }
            } else {
                $this->logger->error('No valid quote ID found for ID: ' . $quoteId);
            }
        } else {
            $this->logger->info('No successful payment status found in session or missing quote ID.');
        }
    }

    private function resolveQuoteId($quoteId)
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'masked_id');
            return $quoteIdMask->getQuoteId() ?? $quoteId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to resolve quote ID: ' . $e->getMessage());
            return $quoteId; // Fallback to using the given ID if the masked lookup fails
        }
    }
}

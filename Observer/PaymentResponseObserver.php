<?php
namespace Madfu\MadfuPayment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
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
        QuoteIdMaskFactory $quoteIdMaskFactory,
        LoggerInterface $logger
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
        $maskedQuoteId = $this->checkoutSession->getData('quoteId'); // Retrieve the masked quoteId from session

        if ($paymentStatus === 'success' && $maskedQuoteId) {
            $this->checkoutSession->unsetData('paymentStatus'); // Clear the session after use
            $this->checkoutSession->unsetData('quoteId');

            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedQuoteId, 'masked_id');
            $quoteId = $quoteIdMask->getQuoteId(); // Actual quote ID

            if ($quoteId) {
                $criteria = $this->searchCriteriaBuilder->addFilter('quote_id', $quoteId, 'eq')->create();
                $orders = $this->orderRepository->getList($criteria)->getItems();

                if (count($orders) > 0) {
                    $order = array_values($orders)[0]; // Assuming there's only one order with this quoteId
                    $order->setStatus('paid');
                    $this->orderRepository->save($order);
                    $this->messageManager->addSuccessMessage(__('Payment successful and order placed.'));
                    $this->logger->info('Order status updated to Paid.');
                } else {
                    $this->logger->error('No order found with quote ID: ' . $quoteId);
                }
            } else {
                $this->logger->error('No valid quote ID found for masked ID: ' . $maskedQuoteId);
            }
        } else {
            $this->logger->info('No successful payment status found in session or missing quote ID.');
        }
    }
}

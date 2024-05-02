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
        $quoteId = $this->checkoutSession->getData('quoteId');

        $this->checkoutSession->unsetData('paymentStatus');
        $this->checkoutSession->unsetData('quoteId');

        if (!$quoteId) {
            $this->logger->error('No quote ID provided.');
            $this->messageManager->addErrorMessage(__('Error: No order information found.'));
            return;
        }

        $realQuoteId = $this->resolveQuoteId($quoteId);
        if (!$realQuoteId) {
            $this->logger->error('Failed to resolve quote ID for ID: ' . $quoteId);
            $this->messageManager->addErrorMessage(__('Error: Failed to process payment information.'));
            return;
        }

        switch ($paymentStatus) {
            case 'success':
                $this->updateOrderStatus($realQuoteId, 'paid');
                $this->messageManager->addSuccessMessage(__('Payment successful and order placed.'));
                break;
            case 'failed':
                $this->updateOrderStatus($realQuoteId, 'payment_failed');
                $this->messageManager->addErrorMessage(__('Payment failed. Please try again or use another payment method.'));
                break;
            case 'canceled':
                $this->updateOrderStatus($realQuoteId, 'payment_canceled');
                $this->messageManager->addNoticeMessage(__('Payment was canceled.'));
                break;
            default:
                $this->messageManager->addErrorMessage(__('Error: Unrecognized payment status.'));
                break;
        }
    }

    private function updateOrderStatus($quoteId, $status)
    {
        $criteria = $this->searchCriteriaBuilder->addFilter('quote_id', $quoteId, 'eq')->create();
        $orders = $this->orderRepository->getList($criteria)->getItems();
        if (count($orders) > 0) {
            $order = array_values($orders)[0];
            $order->setStatus($status);
            $this->orderRepository->save($order);
        } else {
            $this->logger->error('No order found with quote ID: ' . $quoteId);
            $this->messageManager->addErrorMessage(__('Error: Order placement failed.'));
        }
    }

    private function resolveQuoteId($quoteId)
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'masked_id');
            return $quoteIdMask->getQuoteId() ?? $quoteId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to resolve quote ID: ' . $e->getMessage());
            return null;
        }
    }
}

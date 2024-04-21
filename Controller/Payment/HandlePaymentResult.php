<?php
namespace Madfu\MadfuPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class HandlePaymentResult extends Action
{
    protected $jsonFactory;
    protected $orderRepository;
    protected $checkoutSession;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $postData = $this->getRequest()->getContent();
        $data = json_decode($postData, true);

        if (!$data) {
            return $result->setData(['success' => false, 'message' => 'Invalid JSON data']);
        }

        if (!isset($data['status'], $data['paymentData']['quoteId'])) {
            return $result->setData(['success' => false, 'message' => 'Missing required data fields']);
        }

        $quoteId = $data['paymentData']['quoteId'];
        $status = $data['status'];

        // Save quoteId and payment status in the session
        $this->checkoutSession->setData('quoteId', $quoteId);
        $this->checkoutSession->setData('paymentStatus', $status);

        return $result->setData(['success' => true, 'message' => "Payment status '$status' for Quote ID '$quoteId' saved in session"]);
    }
}

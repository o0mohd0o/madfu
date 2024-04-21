<?php
namespace Madfu\MadfuPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class HandlePaymentResult extends Action
{
    protected $jsonFactory;
    protected $checkoutSession;
    protected $cartRepository;
    protected $quoteIdMaskFactory;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
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

        $maskedQuoteId = $data['paymentData']['quoteId'];
        $status = $data['status'];

        try {
            if ($this->getSessionCustomerId()) {
                // For logged-in users, use the real quote ID directly
                $quoteId = $maskedQuoteId;
            } else {
                // For guest users, convert masked ID to real quote ID
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedQuoteId, 'masked_id');
                $quoteId = $quoteIdMask->getQuoteId();
            }
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => 'Failed to retrieve quote ID']);
        }

        // Save quoteId and payment status in the session
        $this->checkoutSession->setData('quoteId', $quoteId);
        $this->checkoutSession->setData('paymentStatus', $status);

        return $result->setData(['success' => true, 'message' => "Payment status '$status' for Quote ID '$quoteId' saved in session"]);
    }

    private function getSessionCustomerId() {
        return $this->checkoutSession->getQuote()->getCustomerId();
    }
}

<?php
namespace Madfu\MadfuPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class HandlePaymentResult extends Action
{
    protected $jsonFactory;
    protected $checkoutSession;
    protected $cartRepository;
    protected $quoteIdMaskFactory;
    protected $logger;

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
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log', Logger::DEBUG));
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

        $status = $data['status'];
        $maskedQuoteId = $data['paymentData']['quoteId'];

        try {
            if ($this->getSessionCustomerId()) {
                $quoteId = $maskedQuoteId;
            } else {
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedQuoteId, 'masked_id');
                $quoteId = $quoteIdMask->getQuoteId();
            }
            $this->checkoutSession->setData('quoteId', $quoteId);
            $this->checkoutSession->setData('paymentStatus', $status);

            return $result->setData(['success' => true, 'message' => "Payment status '$status' for Quote ID '$quoteId' saved in session"]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => 'Failed to process payment information. Error: ' . $e->getMessage()]);
        }
    }

    private function getSessionCustomerId() {
        return $this->checkoutSession->getQuote()->getCustomerId();
    }
}

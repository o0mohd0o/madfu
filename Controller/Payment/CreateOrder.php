<?php

namespace Madfu\MadfuPayment\Controller\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Context;
use Madfu\MadfuPayment\Gateway\Service\SignInService;
use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface as MessageManager;

class CreateOrder extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $signInService;
    protected $client;
    protected $scopeConfig;
    protected $encryptor;
    protected $checkoutSession;
    private $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        SignInService $signInService,
        CheckoutSession $checkoutSession,
        MessageManager $messageManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->signInService = $signInService;
        $this->client = new Client();
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        // Create a Monolog Logger instance
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log'));
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $requestBody = file_get_contents('php://input');
            $orderData = json_decode($requestBody, true);

            $signInResponse = $this->signInService->signIn();
            if (!$signInResponse['success']) {
                throw new \Exception($signInResponse['message']);
            }
            $token = $signInResponse['data']['token'];

            // Retrieve the necessary information from the scopeConfig
            $apiKey = $this->scopeConfig->getValue('payment/madfu_gateway/api_key', ScopeInterface::SCOPE_STORE);
            $appCode = $this->scopeConfig->getValue('payment/madfu_gateway/app_code', ScopeInterface::SCOPE_STORE);
            $authorization = $this->scopeConfig->getValue('payment/madfu_gateway/authorization', ScopeInterface::SCOPE_STORE);
            $platformTypeId = $this->scopeConfig->getValue('payment/madfu_gateway/platform_type_id', ScopeInterface::SCOPE_STORE);

            // Decrypt the values
            $apiKey = $this->encryptor->decrypt($apiKey);
            $appCode = $this->encryptor->decrypt($appCode);
            $authorization = $this->encryptor->decrypt($authorization);

            $headers = [
                'APIKey' => $apiKey,
                'Appcode' => $appCode,
                'Authorization' => 'Basic ' . $authorization,
                'PlatformTypeId' => $platformTypeId,
                'Token' => $token,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ];

            $response = $this->client->request('POST', 'https://api.staging.madfu.com.sa/Merchants/Checkout/CreateOrder', [
                'json' => $orderData,
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            $result->setData(['success' => true, 'data' => $responseData]);
        } catch (\Exception $e) {
            $this->logger->error('Error occurred: ' . $e->getMessage());
            // Add an error message to be displayed to the user
            $this->messageManager->addErrorMessage(__('Error occurred while creating the order: %1', $e->getMessage()));
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
        return $result;
    }
}

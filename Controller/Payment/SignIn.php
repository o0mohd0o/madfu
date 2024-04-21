<?php

namespace Madfu\MadfuPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Madfu\MadfuPayment\Gateway\Service\TokenService;
use Madfu\MadfuPayment\Helper\ConfigHelper;  // Ensure you import your configuration helper
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class SignIn extends Action
{
    protected $jsonFactory;
    protected $tokenService;
    protected $client;
    protected $scopeConfig;
    protected $encryptor;
    protected $configHelper; // Helper to get environment URL
    private $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        TokenService $tokenService,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        ConfigHelper $configHelper  // Inject the helper
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->tokenService = $tokenService;
        $this->client = new Client();
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->configHelper = $configHelper;  // Initialize the helper
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log'));
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $environmentUrl = $this->configHelper->getUrlForEnvironment(  // Get the dynamic URL
            $this->scopeConfig->getValue(
                'payment/madfu_gateway/environment',
                ScopeInterface::SCOPE_STORE
            )
        );

        try {
            $signInResponse = $this->signInService->signIn();
            if (!$signInResponse['success']) {
                throw new \Exception($signInResponse['message']);
            }
            $token = $signInResponse['data']['token'];

            $body = [
                // Body contents as previously defined
            ];

            $headers = [
                'Token' => $token,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ];

            $this->logger->info('Sending request to ' . $environmentUrl . '/Merchants/Checkout/CreateOrder');
            $this->logger->info('Request Body: ' . json_encode($body));
            $this->logger->info('Headers: ' . json_encode($headers));

            $response = $this->client->request('POST', $environmentUrl . '/Merchants/Checkout/CreateOrder', [
                'json' => $body,
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $this->logger->info('Response Data: ' . json_encode($responseData));

            $result->setData(['success' => true, 'data' => $responseData]);
        } catch (\Exception $e) {
            $this->logger->error('Error occurred: ' . $e->getMessage());
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
        return $result;
    }
}

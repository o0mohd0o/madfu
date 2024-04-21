<?php

namespace Madfu\MadfuPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Madfu\MadfuPayment\Gateway\Service\TokenService;
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
    private $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        TokenService $tokenService,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->tokenService = $tokenService;
        $this->client = new Client();
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        // Create a Monolog Logger instance
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log'));
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $signInResponse = $this->signInService->signIn();
            if (!$signInResponse['success']) {
                throw new \Exception($signInResponse['message']);
            }
            $token = $signInResponse['data']['token'];

            $body = [
                "GuestOrderData" => [
                    "CustomerMobile" => "599904447",
                    "CustomerName" => "Mohamed Tawfik",
                    "Lang" => "ar"
                ],
                "Order" => [
                    "Taxes" => 1.5,
                    "ActualValue" => 13,
                    "Amount" => 10,
                    "MerchantReference" => "15650-AAA"
                ],
                "OrderDetails" => [
                    [
                        "productName" => "Product Name",
                        "SKU" => "Stock keeping unit",
                        "productImage" => "product image url",
                        "count" => 5,
                        "totalAmount" => 100
                    ]
                ]
            ];

            $headers = [
                // Your headers here
                'Token' => $token,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ];

            $this->logger->info('Sending request to https://api.staging.madfu.com.sa/Merchants/Checkout/CreateOrder');
            $this->logger->info('Request Body: ' . json_encode($body));
            $this->logger->info('Headers: ' . json_encode($headers));

            $response = $this->client->request('POST', 'https://api.staging.madfu.com.sa/Merchants/Checkout/CreateOrder', [
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

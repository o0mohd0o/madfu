<?php

namespace Madfu\MadfuPayment\Gateway\Http\Client;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use GuzzleHttp\Client;
use Magento\Store\Model\ScopeInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CreateOrder
{
    private $client;
    private $scopeConfig;
    private $encryptor;
    private $logger;

    public function __construct(
        Client $client,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->client = $client;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;

        // Create a Monolog Logger instance
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log'));
    }

    public function createOrder($orderDetails, $guestOrderData, $order)
    {
        $apiKey = $this->decryptConfigValue('payment/madfu_gateway/api_key');
        $appCode = $this->decryptConfigValue('payment/madfu_gateway/app_code');
        $authorization = 'Basic ' . $this->decryptConfigValue('payment/madfu_gateway/authorization');

        $body = [
            'OrderDetails' => $orderDetails,
            'GuestOrderData' => $guestOrderData,
            'Order' => $order
        ];
        $headers = [
            'APIKey' => $apiKey,
            'Appcode' => $appCode,
            'Authorization' => $authorization,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Log the request
        $this->logger->info('Request to https://api.staging.madfu.com.sa/Merchants/Checkout/CreateOrder');
        $this->logger->info('Body: ' . json_encode($body));
        $this->logger->info('Headers: ' . json_encode($headers));

        $response = $this->client->request('POST', 'https://api.staging.madfu.com.sa/Merchants/Checkout/CreateOrder', [
            'body' => json_encode($body),
            'headers' => $headers,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getConfigValue($configPath)
    {
        return $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);
    }

    private function decryptConfigValue($configPath)
    {
        $encryptedValue = $this->getConfigValue($configPath);
        return $this->encryptor->decrypt($encryptedValue);
    }
}

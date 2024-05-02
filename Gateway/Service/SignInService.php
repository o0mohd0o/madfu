<?php

namespace Madfu\MadfuPayment\Gateway\Service;

use GuzzleHttp\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class SignInService
{
    protected $tokenService;
    protected $client;
    protected $scopeConfig;
    protected $encryptor;
    private $logger;

    public function __construct(
        TokenService $tokenService,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->tokenService = $tokenService;
        $this->client = new Client();
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        // Create a Monolog Logger instance
        $this->logger = new Logger('customLogger');
        $this->logger->pushHandler(new StreamHandler(BP . '/var/log/madfu-payment.log'));
    }

    public function signIn()
    {
        try {
            $apiKey = $this->scopeConfig->getValue('payment/madfu_gateway/api_key', ScopeInterface::SCOPE_STORE);
            $appCode = $this->scopeConfig->getValue('payment/madfu_gateway/app_code', ScopeInterface::SCOPE_STORE);
            $platformTypeId = $this->scopeConfig->getValue('payment/madfu_gateway/platform_type_id', ScopeInterface::SCOPE_STORE);
            $authorization = $this->scopeConfig->getValue('payment/madfu_gateway/authorization', ScopeInterface::SCOPE_STORE);
            $username = $this->scopeConfig->getValue('payment/madfu_gateway/username', ScopeInterface::SCOPE_STORE);
            $password = $this->scopeConfig->getValue('payment/madfu_gateway/password', ScopeInterface::SCOPE_STORE);

            // Decrypt the values
            $apiKey = $this->encryptor->decrypt($apiKey);
            $appCode = $this->encryptor->decrypt($appCode);
            $authorization = $this->encryptor->decrypt($authorization);
            $password = $this->encryptor->decrypt($password);

            // Get the token from TokenService
            $token = $this->tokenService->initializeToken();

            $body = [
                'userName' => $username,
                'password' => $password,
            ];

            $headers = [
                'APIKey' => $apiKey,
                'Appcode' => $appCode,
                'Authorization' => 'Basic ' . $authorization,
                'PlatformTypeId' => $platformTypeId,
                'Token' => $token,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ];

            $response = $this->client->request('POST', 'https://api.staging.madfu.com.sa/Merchants/sign-in', [
                'json' => $body,
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            return ['success' => true, 'data' => $responseData];
        } catch (\Exception $e) {
            $this->logger->error('Error occurred: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

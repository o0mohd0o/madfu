<?php

namespace Madfu\MadfuPayment\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Madfu\MadfuPayment\Gateway\Service\TokenService;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var TokenService
     */
    private $tokenService;

    /**
     * @param ConfigInterface $config
     * @param TokenService $tokenService
     */
    public function __construct(
        ConfigInterface $config,
        TokenService $tokenService
    ) {
        $this->config = $config;
        $this->tokenService = $tokenService;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment']) || !$buildSubject['payment'] instanceof PaymentDataObjectInterface) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        // Authenticate and obtain a token
        $token = $this->tokenService->initializeToken();

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $address = $order->getShippingAddress();

        $userName = $this->config->getValue('payment/madfu_gateway/username', $order->getStoreId());
        $password = $this->config->getValue('payment/madfu_gateway/password', $order->getStoreId());

        // Use the token obtained from TokenService for authentication in the request headers
        return [
            'headers' => [
                'Token' => $token,
                'APIKey' => $this->config->getValue('api_key', $order->getStoreId()),
                'Appcode' => $this->config->getValue('app_code', $order->getStoreId()),
                'PlatformTypeId' => $this->config->getValue('platform_type_id', $order->getStoreId()),
                'Authorization' => $this->config->getValue('authorization', $order->getStoreId()),
            ],
            'body' => [
                'userName' => $userName,
                'password' => $password,
            ]
        ];
    }
}

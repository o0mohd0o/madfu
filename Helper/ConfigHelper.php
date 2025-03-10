<?php
namespace Madfu\MadfuPayment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class ConfigHelper extends AbstractHelper
{
    const URLS = [
        'staging' => 'https://checkout-staging.madfu.com.sa/MadfuCheckout.js?v=0.1',
        'production' => 'https://checkout.madfu.com.sa/MadfuCheckout.js?v=0.1',
    ];

    // Define the XML path for the payment method title
    const XML_PATH_PAYMENT_TITLE = 'payment/madfu_gateway/title';

    /**
     * Get the Checkout JS URL based on the environment setting from the scope config.
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        $environment = $this->scopeConfig->getValue(
            'payment/madfu_gateway/environment',
            ScopeInterface::SCOPE_STORE
        );

        return self::URLS[$environment] ?? self::URLS['staging']; // Default to staging if no environment is set
    }

    /**
     * Get the payment method title from system configuration.
     *
     * @param string $paymentCode The payment code to get the title for
     * @return string
     */
    public function getPaymentMethodTitle($paymentCode)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PAYMENT_TITLE,
            ScopeInterface::SCOPE_STORE
        );
    }
}

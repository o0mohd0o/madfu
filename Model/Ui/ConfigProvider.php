<?php
namespace Madfu\MadfuPayment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Madfu\MadfuPayment\Gateway\Http\Client\ClientMock;
use Magento\Framework\Locale\ResolverInterface;
use Madfu\MadfuPayment\Helper\ConfigHelper;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'madfu_gateway';
    private $localeResolver;
    private $configHelper;

    public function __construct(
        ResolverInterface $localeResolver,
        ConfigHelper $configHelper
    ) {
        $this->localeResolver = $localeResolver;
        $this->configHelper = $configHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $locale = $this->localeResolver->getLocale();
        $checkoutUrl = $this->configHelper->getCheckoutUrl();
        $paymentMethodTitle = $this->configHelper->getPaymentMethodTitle(self::CODE);

        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ],
                    'locale' => $locale,
                    'checkoutUrl' => $checkoutUrl,
                    'title' => $paymentMethodTitle
                ]
            ]
        ];
    }
}

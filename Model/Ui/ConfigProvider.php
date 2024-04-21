<?php
namespace Madfu\MadfuPayment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Madfu\MadfuPayment\Gateway\Http\Client\ClientMock;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\ResolverInterface;
use Madfu\MadfuPayment\Helper\ConfigHelper;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'madfu_gateway';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResolverInterface
     */
    private $localeResolver;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ResolverInterface $localeResolver
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResolverInterface $localeResolver,
        ConfigHelper $configHelper
    ) {
        $this->storeManager = $storeManager;
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
        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ],
                    'locale' => $locale,
                    'checkoutUrl' => $checkoutUrl
                ]
            ]
        ];
    }
}

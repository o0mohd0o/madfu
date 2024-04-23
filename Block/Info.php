<?php

namespace Madfu\MadfuPayment\Block;

class Info extends \Magento\Payment\Block\Info
{
    protected $_configHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Madfu\MadfuPayment\Helper\ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_configHelper = $configHelper;
    }

    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $paymentCode = $this->getMethod()->getCode();
        $paymentTitle = $this->_configHelper->getPaymentMethodTitle($paymentCode);

        // Using the translation and converting to string
        return $transport->addData([
            'Payment Method Title' => $paymentTitle
        ]);
    }
}

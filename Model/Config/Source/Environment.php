<?php
namespace Madfu\MadfuPayment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Environment implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'staging', 'label' => __('Staging')],
            ['value' => 'production', 'label' => __('Production')]
        ];
    }
}

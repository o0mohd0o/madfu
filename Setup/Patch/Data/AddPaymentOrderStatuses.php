<?php
namespace Madfu\MadfuPayment\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;

class AddPaymentOrderStatuses implements DataPatchInterface
{
    private $moduleDataSetup;
    private $statusFactory;
    private $statusResource;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        StatusFactory $statusFactory,
        StatusResource $statusResource
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->statusFactory = $statusFactory;
        $this->statusResource = $statusResource;
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        // Statuses and state to assign
        $statuses = [
            'paid' => 'Paid',
            'payment_failed' => 'Payment Failed',
            'payment_canceled' => 'Payment Canceled'
        ];

        foreach ($statuses as $code => $label) {
            /** @var Status $status */
            $status = $this->statusFactory->create();
            $status->setData([
                'status' => $code,
                'label' => $label
            ]);

            $this->statusResource->save($status);
            $status->assignState('processing', false, true); // Change as needed
        }

        $this->moduleDataSetup->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}

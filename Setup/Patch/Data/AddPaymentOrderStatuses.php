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

        $statuses = [
            'paid' => 'Paid',
            'payment_failed' => 'Payment Failed',
            'payment_canceled' => 'Payment Canceled'
        ];

        foreach ($statuses as $code => $label) {
            $status = $this->statusFactory->create();
            $status->setData([
                'status' => $code,
                'label' => $label
            ]);
            $this->statusResource->save($status);

            // Assign new statuses to appropriate states, consider using 'closed' for cancellation or failure
            if ($code === 'payment_failed' || $code === 'payment_canceled') {
                $status->assignState('closed', false, true);
            } else {
                $status->assignState('processing', false, true); // Original assignment
            }
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

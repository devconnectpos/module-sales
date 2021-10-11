<?php

namespace SM\Sales\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Setup\SalesSetupFactory;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var SalesSetupFactory
     */
    private $salesSetupFactory;

    public function __construct(SalesSetupFactory $salesSetupFactory)
    {
        $this->salesSetupFactory = $salesSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '0.3.6', '<')) {
            $this->addExchangeOrderIdAttributes($setup);
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param OutputInterface          $output
     */
    public function execute(ModuleDataSetupInterface $setup, OutputInterface $output)
    {
        $output->writeln('  |__ Add attributes for order exchange');
        $this->addExchangeOrderIdAttributes($setup);
    }

    /**
     * @param ModuleDataSetupInterface $setup
     */
    protected function addExchangeOrderIdAttributes(ModuleDataSetupInterface $setup)
    {
        $setup->startSetup();

        $orderAttributes = [
            'origin_order_id' => [
                'type'     => Table::TYPE_INTEGER,
                'length'   => 12,
                'visible'  => false,
                'nullable' => true,
            ],
            'rwr_transaction_id' => [
                'type'     => Table::TYPE_INTEGER,
                'length'   => 12,
                'visible'  => false,
                'nullable' => true,
            ],
            'exchange_order_ids' => [
                'type'     => Table::TYPE_TEXT,
                'length'   => 255,
                'visible'  => false,
                'nullable' => true,
            ],
            'cpos_is_new' => [
                'type'     => Table::TYPE_INTEGER,
                'length'   => 5,
                'visible'  => false,
                'nullable' => true,
            ]
        ];

        foreach($orderAttributes as $attributeName => $attributeData) {
            $this->addOrderAttribute($setup, $attributeName, $attributeData);
        }

        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param                          $attributeName
     * @param                          $attributeData
     */
    private function addOrderAttribute(ModuleDataSetupInterface $setup, $attributeName, $attributeData)
    {
        $salesSetup = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        // Skip if the attribute exists
        $attribute = $salesSetup->getAttribute(Order::ENTITY, $attributeName);

        if ($attribute) {
            return;
        }

        $salesSetup->addAttribute(Order::ENTITY, $attributeName, $attributeData);
    }
}

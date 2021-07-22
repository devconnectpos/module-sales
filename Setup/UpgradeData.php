<?php


namespace SM\Sales\Setup;

class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
{
    /**
     * @var \Magento\Sales\Setup\SalesSetupFactory
     */
    private $salesSetupFactory;

    private $orderCollectionFactory;

    public function __construct(\Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory)
    {
        $this->salesSetupFactory = $salesSetupFactory;
    }

    public function upgrade(\Magento\Framework\Setup\ModuleDataSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (version_compare($context->getVersion(), '0.3.6', '<')) {
            $this->addExchangeOrderIdAttributes($installer);
        }
        if (version_compare($context->getVersion(), '0.4.1', '<=')) {
            // Remove this one because of setup performance
            //$this->addOutletPaymentMethods($installer);
        }

        $installer->endSetup();
    }

    protected function addOutletPaymentMethods(\Magento\Framework\Setup\ModuleDataSetupInterface $installer)
    {
        $salesSetup = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $installer]);
        $connection = $salesSetup->getConnection();
        $salesOrderTable = $connection->getTableName('sales_order');
        $salesOrderGridTable = $connection->getTableName('sales_order_grid');
        $transactionTable = $connection->getTableName('sm_retail_transaction');
        $query1 = "
            UPDATE ${salesOrderTable} so
            SET
                so.outlet_payment_method = (SELECT GROUP_CONCAT(UPPER(st.payment_title) SEPARATOR '-')
                    FROM ${transactionTable} st
                    WHERE st.order_id = so.entity_id
                    GROUP BY st.order_id)
            WHERE
                so.retail_id IS NOT NULL
        ";
        $query2 = "
            UPDATE ${salesOrderGridTable} so
            SET
                so.outlet_payment_method = (SELECT GROUP_CONCAT(UPPER(st.payment_title) SEPARATOR '-')
                    FROM ${transactionTable} st
                    WHERE st.order_id = so.entity_id
                    GROUP BY st.order_id)
            WHERE
                so.retail_id IS NOT NULL
        ";
        try {
            $connection->query($query1)->execute();
            $connection->query($query2)->execute();
        } catch (\Exception $e) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/connectpos.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info("====> Failed to add outlet payment method data");
            $logger->info($e->getMessage() . "\n" . $e->getTranceAsString());
        }
    }

    protected function addExchangeOrderIdAttributes(\Magento\Framework\Setup\ModuleDataSetupInterface $installer)
    {
        $salesSetup = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $installer]);

        $salesSetup->addAttribute(\Magento\Sales\Model\Order::ENTITY, 'origin_order_id', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            'length' => 12,
            'visible' => false,
            'nullable' => true,
        ]);

        $salesSetup->addAttribute(\Magento\Sales\Model\Order::ENTITY, 'rwr_transaction_id', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            'length' => 12,
            'visible' => false,
            'nullable' => true,
        ]);

        $salesSetup->addAttribute(\Magento\Sales\Model\Order::ENTITY, 'exchange_order_ids', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            'length' => 255,
            'visible' => false,
            'nullable' => true,
        ]);

        $salesSetup->addAttribute(\Magento\Sales\Model\Order::ENTITY, 'cpos_is_new', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            'length' => 5,
            'visible' => false,
            'nullable' => true,
        ]);
    }
}

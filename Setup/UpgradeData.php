<?php


namespace SM\Sales\Setup;


class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
{
	/**
	 * @var \Magento\Sales\Setup\SalesSetupFactory
	 */
	private $salesSetupFactory;
	
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
		
		$installer->endSetup();
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

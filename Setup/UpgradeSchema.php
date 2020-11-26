<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace SM\Sales\Setup;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Sales\Model\OrderFactory;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    public function __construct(
        OrderFactory $orderFactory,
        State $state
    ) {
        $this->orderFactory = $orderFactory;
        try {
            $state->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException $e) {
        }
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (version_compare($context->getVersion(), '0.1.1', '<')) {
            $this->addRetailDataToOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.1.6', '<')) {
            $this->addOrderSyncErrorTable($setup);
        }
        if (version_compare($context->getVersion(), '0.1.7', '<')) {
            $this->updateRetailToOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.2.3', '<')) {
            $this->addXRefNumOrderCardKnox($setup);
        }
        if (version_compare($context->getVersion(), '0.2.4', '<')) {
            $this->addCashierUserInOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.2.5', '<')) {
            $this->addStorePickupOutletIdToQuote($setup);
        }
        if (version_compare($context->getVersion(), '0.2.7', '<')) {
            $this->addRateOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.2.7', '<')) {
            $this->addFeedback($setup);
        }
        if (version_compare($context->getVersion(), '0.2.6', '<')) {
            $this->addRewardPointsAndStoreCreditInfoToOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.2.8', '<')) {
            $this->addTransactionIdNumOrderAuthorize($setup);
        }
        if (version_compare($context->getVersion(), '0.2.9', '<')) {
            $this->addRewardPointsEarnAmountToOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.3.0', '<')) {
            $this->addPrintTimeCounter($setup);
        }
        if (version_compare($context->getVersion(), '0.3.1', '<')) {
            $this->modifyOutletRewardPointAndStoreCreditInfoToOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.3.2', '<')) {
            $this->upgradeUserNameToOrderAndQuote($setup);
        }
        if (version_compare($context->getVersion(), '0.3.3', '<')) {
            $this->addIndexForRetailId($setup);
        }
        if (version_compare($context->getVersion(), '0.3.4', '<')) {
            $this->addEstimatedAvailabilityToOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.3.5', '<')) {
            $this->addSellerUsernameInOrder($setup);
        }
        if (version_compare($context->getVersion(), '0.3.7', '<')) {
            $this->addCreditmemoFromStore($setup);
        }
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addRetailDataToOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        if ($installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'user_id')
            && $installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'retail_has_shipment')) {
            return;
        }

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'outlet_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'outlet_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'outlet_id');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'retail_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'retail_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'retail_id');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'retail_status');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'retail_status');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'retail_status');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'retail_note');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'retail_note');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'retail_note');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'retail_has_shipment');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'retail_has_shipment');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'retail_has_shipment');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'is_exchange');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'is_exchange');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'is_exchange');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'user_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'user_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'user_id');

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'outlet_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Outlet id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'outlet_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Outlet id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'outlet_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Outlet id',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'retail_id',
            [
                'type'    => Table::TYPE_TEXT,
                'length'  => '32',
                'comment' => 'Client id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'retail_id',
            [
                'type'    => Table::TYPE_TEXT,
                'length'  => '32',
                'comment' => 'Client id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'retail_id',
            [
                'type'    => Table::TYPE_TEXT,
                'length'  => '32',
                'comment' => 'Client id',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'retail_status',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Client Status',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'retail_status',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Client Status',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'retail_status',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Client Status',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'retail_note',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Retail Note',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'retail_note',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Retail Note',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'retail_note',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Retail Note',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'retail_has_shipment',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Retail Shipment',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'retail_has_shipment',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Retail Shipment',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'retail_has_shipment',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Retail Shipment',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'is_exchange',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Retail Shipment',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'is_exchange',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Retail Shipment',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'is_exchange',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Retail Shipment',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'user_id',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Cashier Id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'user_id',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Cashier Id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'user_id',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Cashier Id',
            ]
        );
        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     *
     * @throws \Zend_Db_Exception
     */
    protected function addOrderSyncErrorTable(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $installer->startSetup();
        $setup->getConnection()->dropTable($setup->getTable('sm_order_sync_error'));
        $table = $installer->getConnection()->newTable(
            $installer->getTable('sm_order_sync_error')
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Entity ID'
        )->addColumn(
            'retail_id',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'retail_id'
        )->addColumn(
            'outlet_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'retail_id'
        )->addColumn(
            'store_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false],
            'retail_id'
        )->addColumn(
            'message',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'error'
        )->addColumn(
            'order_offline',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false],
            'Order offline data'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Creation Time'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Modification Time'
        );
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }

    protected function updateRetailToOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'register_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'register_id');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'register_id');

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'register_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Register id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'register_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Register id',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'register_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Register id',
            ]
        );
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addXRefNumOrderCardKnox(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        if ($installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'xRefNum')) {
            $installer->getConnection()->dropColumn($installer->getTable('quote'), 'xRefNum');
        }
        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order'), 'xRefNum')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'xRefNum');
        }
        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order_grid'), 'xRefNum')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'xRefNum');
        }

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'xRefNum',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'xRefNum',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'xRefNum',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'xRefNum',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'xRefNum',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'xRefNum',
            ]
        );
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addStorePickupOutletIdToQuote(SchemaSetupInterface $setup)
    {
        $upgrader = $setup;
        $upgrader->getConnection()->dropColumn($upgrader->getTable('quote'), 'pickup_outlet_id');
        $upgrader->getConnection()->dropColumn($upgrader->getTable('sales_order'), 'pickup_outlet_id');
        $upgrader->getConnection()->dropColumn($upgrader->getTable('sales_order_grid'), 'pickup_outlet_id');

        $upgrader->getConnection()->addColumn(
            $upgrader->getTable('quote'),
            'pickup_outlet_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Store Pickup Outlet id',
            ]
        );
        $upgrader->getConnection()->addColumn(
            $upgrader->getTable('sales_order'),
            'pickup_outlet_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Store Pickup Outlet id',
            ]
        );
        $upgrader->getConnection()->addColumn(
            $upgrader->getTable('sales_order_grid'),
            'pickup_outlet_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Store Pickup Outlet id',
            ]
        );
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addCashierUserInOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        if ($installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'sm_seller_ids')) {
            $installer->getConnection()->dropColumn($installer->getTable('quote'), 'sm_seller_ids');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'sm_seller_ids',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Seller Ids',
            ]
        );

        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order'), 'sm_seller_ids')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'sm_seller_ids');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'sm_seller_ids',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Seller Ids',
            ]
        );

        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order_grid'), 'sm_seller_ids')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'sm_seller_ids');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'sm_seller_ids',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Seller Ids',
            ]
        );
    }

    protected function addRateOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order'), 'order_rate')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'order_rate');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'order_rate',
            [
                'type'    => Table::TYPE_SMALLINT,
                'comment' => 'Order Rate',
            ]
        );

        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order'), 'order_feedback')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'order_feedback');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'order_feedback',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Order Feedback',
            ]
        );
    }

    protected function addFeedback(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $installer->startSetup();
        $setup->getConnection()->dropTable($setup->getTable('sm_feedback'));
        $table = $installer->getConnection()->newTable(
            $installer->getTable('sm_feedback')
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Entity ID'
        )->addColumn(
            'retail_id',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false],
            'Retail Id'
        )->addColumn(
            'retail_feedback',
            Table::TYPE_TEXT,
            null,
            ['nullable' => true],
            'Retail Feedback'
        )->addColumn(
            'retail_rate',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => true],
            'Retail Rate'
        );
        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }

    protected function addRewardPointsAndStoreCreditInfoToOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        if ($installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'store_credit_balance')) {
            return;
        }

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'store_credit_balance');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'store_credit_balance');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'store_credit_balance');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'previous_reward_points_balance');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'previous_reward_points_balance');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'previous_reward_points_balance');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'reward_points_redeemed');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'reward_points_redeemed');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'reward_points_redeemed');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'reward_points_earned');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'reward_points_earned');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'reward_points_earned');

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'reward_points_refunded');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'reward_points_refunded');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'reward_points_refunded');

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'store_credit_balance',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Store Credit Balance',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'store_credit_balance',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Store Credit Balance',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'store_credit_balance',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Store Credit Balance',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'previous_reward_points_balance',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Previous Reward Points Balance',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'previous_reward_points_balance',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Previous Reward Points Balance',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'previous_reward_points_balance',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Previous Reward Points Balance',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'reward_points_redeemed',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Redeemed',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'reward_points_redeemed',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Redeemed',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'reward_points_redeemed',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Redeemed',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'reward_points_earned',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Earned',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'reward_points_earned',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Earned',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'reward_points_earned',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Earned',
            ]
        );

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'reward_points_refunded',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Refunded',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'reward_points_refunded',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Refunded',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'reward_points_refunded',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Reward Points Refunded',
            ]
        );
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addTransactionIdNumOrderAuthorize(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        if ($installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'transId')) {
            $installer->getConnection()->dropColumn($installer->getTable('quote'), 'transId');
        }
        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order'), 'transId')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'transId');
        }
        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order_grid'), 'transId')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'transId');
        }

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'transId',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'transId',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'transId',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'transId',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'transId',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'transId',
            ]
        );
    }

    protected function addRewardPointsEarnAmountToOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'reward_points_earned_amount');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'reward_points_earned_amount');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'reward_points_earned_amount');

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'reward_points_earned_amount',
            [
                'type'     => Table::TYPE_DECIMAL,
                'length'   => '12,4',
                'nullable' => true,
                'comment'  => 'Reward Points Earned Amount',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'reward_points_earned_amount',
            [
                'type'     => Table::TYPE_DECIMAL,
                'length'   => '12,4',
                'nullable' => true,
                'comment'  => 'Reward Points Earned Amount',
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'reward_points_earned_amount',
            [
                'type'     => Table::TYPE_DECIMAL,
                'length'   => '12,4',
                'nullable' => true,
                'comment'  => 'Reward Points Earned Amount',
            ]
        );
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function addPrintTimeCounter(SchemaSetupInterface $setup)
    {
        $setup->startSetup();
        if (!$setup->getConnection()->tableColumnExists($setup->getTable('sales_order'), 'print_time_counter')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'print_time_counter',
                [
                    'type'    => Table::TYPE_INTEGER,
                    'comment' => 'Print time counter',
                    'default' => 0
                ]
            );
        }
        $setup->endSetup();
    }

    protected function modifyOutletRewardPointAndStoreCreditInfoToOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $installer->startSetup();

        $installer
            ->getConnection()
            ->modifyColumn(
                $installer->getTable('quote'),
                'store_credit_balance',
                [
                    'type'    => Table::TYPE_DECIMAL,
                    'length'  => '12,2',
                    'comment' => 'Store Credit Balance',
                ]
            );
        $installer
            ->getConnection()
            ->modifyColumn(
                $installer->getTable('sales_order'),
                'store_credit_balance',
                [
                    'type'    => Table::TYPE_DECIMAL,
                    'length'  => '12,2',
                    'comment' => 'Store Credit Balance',
                ]
            );
        $installer
            ->getConnection()
            ->modifyColumn(
                $installer->getTable('sales_order_grid'),
                'store_credit_balance',
                [
                    'type'    => Table::TYPE_DECIMAL,
                    'length'  => '12,2',
                    'comment' => 'Store Credit Balance',
                ]
            );
        $installer->endSetup();
    }

    protected function upgradeUserNameToOrderAndQuote(SchemaSetupInterface $setup)
    {
        $setup->startSetup();
        if (!$setup->getConnection()->tableColumnExists($setup->getTable('quote'), 'user_name')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('quote'),
                'user_name',
                [
                    'type'    => Table::TYPE_TEXT,
                    'size'    => 255,
                    'comment' => 'User name',
                ]
            );
        }
        if (!$setup->getConnection()->tableColumnExists($setup->getTable('sales_order'), 'user_name')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'user_name',
                [
                    'type'    => Table::TYPE_TEXT,
                    'size'    => 255,
                    'comment' => 'User name',
                ]
            );
        }
        $setup->endSetup();
    }

    protected function addIndexForRetailId(SchemaSetupInterface $setup)
    {
        $table = $setup->getTable('sales_order_grid');

        $setup->getConnection()
              ->addIndex(
                  $table,
                  $setup->getIdxName(
                      $table,
                      [
                          'increment_id',
                          'billing_name',
                          'shipping_name',
                          'shipping_address',
                          'billing_address',
                          'customer_email',
                          'customer_name',
                          'retail_id'
                      ],
                      \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
                  ),
                  [
                      'increment_id',
                      'billing_name',
                      'shipping_name',
                      'shipping_address',
                      'billing_address',
                      'customer_email',
                      'customer_name',
                      'retail_id'
                  ],
                  \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
              );
    }

    protected function addEstimatedAvailabilityToOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        $installer->getConnection()->dropColumn($installer->getTable('quote'), 'estimated_availability');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'estimated_availability');
        $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'estimated_availability');

        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'estimated_availability',
            [
                'type'     => Table::TYPE_DECIMAL,
                'length'   => '12,4',
                'nullable' => true,
                'default'  => 0,
                'comment'  => 'Estimated Availability'
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'estimated_availability',
            [
                'type'     => Table::TYPE_DECIMAL,
                'length'   => '12,4',
                'nullable' => true,
                'default'  => 0,
                'comment'  => 'Estimated Availability'
            ]
        );
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'estimated_availability',
            [
                'type'     => Table::TYPE_DECIMAL,
                'length'   => '12,4',
                'nullable' => true,
                'default'  => 0,
                'comment'  => 'Estimated Availability'
            ]
        );
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addSellerUsernameInOrder(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        if ($installer->getConnection()->tableColumnExists($installer->getTable('quote'), 'sm_seller_username')) {
            $installer->getConnection()->dropColumn($installer->getTable('quote'), 'sm_seller_username');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('quote'),
            'sm_seller_username',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Seller Username',
            ]
        );

        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order'), 'sm_seller_username')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order'), 'sm_seller_username');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'sm_seller_username',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Seller Username',
            ]
        );

        if ($installer->getConnection()->tableColumnExists($installer->getTable('sales_order_grid'), 'sm_seller_username')) {
            $installer->getConnection()->dropColumn($installer->getTable('sales_order_grid'), 'sm_seller_username');
        }
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order_grid'),
            'sm_seller_username',
            [
                'type'    => Table::TYPE_TEXT,
                'comment' => 'Seller Username',
            ]
        );
    }
    
    protected function addCreditmemoFromStore(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        
        if (!$installer->getConnection()->tableColumnExists($installer->getTable('sales_creditmemo'), 'cpos_creditmemo_from_store_id')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('sales_creditmemo'),
                'cpos_creditmemo_from_store_id',
                [
                    'type'    => Table::TYPE_INTEGER,
                    'length'  => 5,
                    'comment' => 'Creditmemo From Store',
                ]
            );
        }
    }
}

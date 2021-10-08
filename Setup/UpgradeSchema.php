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

    protected $state;

    public function __construct(
        OrderFactory $orderFactory,
        State $state
    ) {
        $this->orderFactory = $orderFactory;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->state->emulateAreaCode(Area::AREA_FRONTEND, function (SchemaSetupInterface $setup, ModuleContextInterface $context) {
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
                if (version_compare($context->getVersion(), '0.3.8', '<=')) {
                    $this->addSerialNumberToSalesItem($setup);
                }
                if (version_compare($context->getVersion(), '0.3.9', '<=')) {
                    $this->addOutletNameToOrder($setup);
                }
                if (version_compare($context->getVersion(), '0.4.0', '<=')) {
                    $this->addOutletPaymentMethodToOrder($setup);
                }
            }, [$setup, $context]);
        } catch (\Throwable $e) {
            $writer = new \Zend\Log\Writer\Stream(BP.'/var/log/connectpos.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info('====> Failed to upgrade Sales schema');
            $logger->info($e->getMessage()."\n".$e->getTraceAsString());
        }
    }

    protected function addOutletPaymentMethodToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'outlet_payment_method')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'outlet_payment_method',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'length'  => 255,
                        'comment' => 'Outlet Payment Method',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function addSerialNumberToSalesItem(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote_item', 'sales_order_item'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'serial_number')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'serial_number',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'length'  => 250,
                        'comment' => 'Serial number',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addRetailDataToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'outlet_id')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'outlet_id',
                    [
                        'type' => Table::TYPE_INTEGER,
                        'comment' => 'Outlet id',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'retail_id')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'retail_id',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'length'  => '32',
                        'comment' => 'Retail Id',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'retail_status')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'retail_status',
                    [
                        'type'    => Table::TYPE_SMALLINT,
                        'comment' => 'Retail Status',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'retail_note')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'retail_note',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'comment' => 'Retail Note',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'retail_has_shipment')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'retail_has_shipment',
                    [
                        'type'    => Table::TYPE_SMALLINT,
                        'comment' => 'Retail Shipment',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'is_exchange')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'is_exchange',
                    [
                        'type'    => Table::TYPE_SMALLINT,
                        'comment' => 'Is Exchange',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'user_id')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'user_id',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'comment' => 'Cashier Id',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     *
     * @throws \Zend_Db_Exception
     */
    protected function addOrderSyncErrorTable(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        if ($setup->getConnection()->isTableExists($setup->getTable('sm_order_sync_error'))) {
            $setup->endSetup();

            return;
        }

        $table = $setup->getConnection()->newTable(
            $setup->getTable('sm_order_sync_error')
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
        $setup->getConnection()->createTable($table);

        $setup->endSetup();
    }

    protected function updateRetailToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'register_id')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'register_id',
                    [
                        'type'    => Table::TYPE_INTEGER,
                        'comment' => 'Register id',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addXRefNumOrderCardKnox(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'xRefNum')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'xRefNum',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'comment' => 'xRefNum',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addStorePickupOutletIdToQuote(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'pickup_outlet_id')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'pickup_outlet_id',
                    [
                        'type'    => Table::TYPE_INTEGER,
                        'comment' => 'Store Pickup Outlet id',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addCashierUserInOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'sm_seller_ids')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'sm_seller_ids',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'comment' => 'Seller Ids',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function addRateOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        if (!$setup->getConnection()->tableColumnExists($setup->getTable('sales_order'), 'order_rate')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'order_rate',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'comment' => 'Order Rate',
                ]
            );
        }

        if (!$setup->getConnection()->tableColumnExists($setup->getTable('sales_order'), 'order_feedback')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'order_feedback',
                [
                    'type' => Table::TYPE_TEXT,
                    'comment' => 'Order Feedback',
                ]
            );
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     *
     * @throws \Zend_Db_Exception
     */
    protected function addFeedback(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        if ($setup->getConnection()->isTableExists($setup->getTable('sm_feedback'))) {
            $setup->endSetup();

            return;
        }

        $table = $setup->getConnection()->newTable(
            $setup->getTable('sm_feedback')
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
        $setup->getConnection()->createTable($table);

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $setup
     */
    protected function addRewardPointsAndStoreCreditInfoToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'store_credit_balance')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'store_credit_balance',
                    [
                        'type'    => Table::TYPE_DECIMAL,
                        'length'  => '12,2',
                        'comment' => 'Store Credit Balance',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'previous_reward_points_balance')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'previous_reward_points_balance',
                    [
                        'type'    => Table::TYPE_INTEGER,
                        'comment' => 'Previous Reward Points Balance',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'reward_points_redeemed')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'reward_points_redeemed',
                    [
                        'type'    => Table::TYPE_INTEGER,
                        'comment' => 'Reward Points Redeemed',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'reward_points_earned')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'reward_points_earned',
                    [
                        'type'    => Table::TYPE_INTEGER,
                        'comment' => 'Reward Points Earned',
                    ]
                );
            }
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'reward_points_refunded')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'reward_points_refunded',
                    [
                        'type'    => Table::TYPE_INTEGER,
                        'comment' => 'Reward Points Refunded',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addTransactionIdNumOrderAuthorize(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'transId')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'transId',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'comment' => 'transId',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    protected function addRewardPointsEarnAmountToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'reward_points_earned_amount')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'reward_points_earned_amount',
                    [
                        'type'     => Table::TYPE_DECIMAL,
                        'length'   => '12,4',
                        'nullable' => true,
                        'comment'  => 'Reward Points Earned Amount',
                    ]
                );
            }
        }

        $setup->endSetup();
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
                    'default' => 0,
                ]
            );
        }

        $setup->endSetup();
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
        $setup->startSetup();

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
                        'retail_id',
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
                    'retail_id',
                ],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_FULLTEXT
            );

        $setup->endSetup();
    }

    protected function addEstimatedAvailabilityToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'estimated_availability')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'estimated_availability',
                    [
                        'type'     => Table::TYPE_DECIMAL,
                        'length'   => '12,4',
                        'nullable' => true,
                        'default'  => 0,
                        'comment'  => 'Estimated Availability',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     */
    protected function addSellerUsernameInOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'sm_seller_username')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'sm_seller_username',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'comment' => 'Seller Username',
                    ]
                );
            }
        }

        $setup->endSetup();
    }

    protected function addCreditmemoFromStore(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        if (!$setup->getConnection()->tableColumnExists($setup->getTable('sales_creditmemo'), 'cpos_creditmemo_from_store_id')) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_creditmemo'),
                'cpos_creditmemo_from_store_id',
                [
                    'type'    => Table::TYPE_INTEGER,
                    'length'  => 5,
                    'comment' => 'Creditmemo From Store',
                ]
            );
        }

        $setup->endSetup();
    }

    protected function addOutletNameToOrder(SchemaSetupInterface $setup)
    {
        $setup->startSetup();

        $tableNames = ['quote', 'sales_order', 'sales_order_grid'];

        foreach ($tableNames as $tableName) {
            if (!$setup->getConnection()->tableColumnExists($setup->getTable($tableName), 'outlet_name')) {
                $setup->getConnection()->addColumn(
                    $setup->getTable($tableName),
                    'outlet_name',
                    [
                        'type'    => Table::TYPE_TEXT,
                        'length'  => 50,
                        'comment' => 'Outlet Name',
                    ]
                );
            }
        }

        $setup->endSetup();
    }
}

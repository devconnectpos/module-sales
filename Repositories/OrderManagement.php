<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 03/12/2016
 * Time: 22:39
 */

namespace SM\Sales\Repositories;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Helper\Product;
use Magento\Config\Model\Config\Loader;
use Magento\Customer\Model\Session;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Filesystem;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Shipping\Model\Shipping;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Integrate\Model\GCIntegrateManagement;
use SM\Integrate\Model\RPIntegrateManagement;
use SM\Integrate\Model\StoreCreditIntegrateManagement;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\Payment\Helper\PaymentHelper;
use SM\Payment\Model\RetailMultiple;
use SM\Payment\Model\RetailPayment;
use SM\Payment\Model\RetailPaymentFactory;
use SM\Performance\Helper\RealtimeManager;
use SM\Product\Helper\ProductHelper;
use SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransactionFactory;
use SM\RefundWithoutReceipt\Model\ResourceModel\RefundWithoutReceiptTransaction\CollectionFactory as RefundWithoutReceiptTransactionCollectionFactory;
use SM\Sales\Model\FeedbackFactory;
use SM\Sales\Model\OrderSyncErrorFactory;
use SM\Sales\Model\ResourceModel\Feedback\CollectionFactory as feedbackCollectionFactory;
use SM\Shift\Helper\Data as ShiftHelper;
use SM\Shift\Model\RetailTransactionFactory;
use SM\Shipping\Model\Carrier\RetailShipping;
use SM\XRetail\Helper\Data;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Model\UserOrderCounterFactory;

use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
/**
 * Class OrderManagement
 *
 * @package SM\Sales\Repositories
 */
class OrderManagement extends ServiceAbstract
{
    public static $IS_COLLECT_RULE       = true;
    public static $ALLOW_BACK_ORDER      = true;
    public static $FROM_API              = false;
    public static $ORDER_HAS_CUSTOM_SALE = false;
    public static $SAVE_ORDER            = false;

    const USING_REFUND_TO_GIFT_CARD = 'using_refund_to_GC';

    public static $MESSAGE_ERROR = [];

    const DISCOUNT_WHOLE_ORDER_KEY = 'discount_whole_order';

    const RETAIL_ORDER_PARTIALLY_PAID_AWAIT_COLLECTION    = 16;
    const RETAIL_ORDER_PARTIALLY_PAID_PICKING_IN_PROGRESS = 15;
    const RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING       = 14;
    const RETAIL_ORDER_PARTIALLY_PAID_SHIPPED             = 13;
    const RETAIL_ORDER_PARTIALLY_PAID_NOT_SHIPPED         = 12;
    const RETAIL_ORDER_PARTIALLY_PAID                     = 11;

    const RETAIL_ORDER_COMPLETE_AWAIT_COLLECTION    = 26;
    const RETAIL_ORDER_COMPLETE_PICKING_IN_PROGRESS = 25;
    const RETAIL_ORDER_COMPLETE_AWAIT_PICKING       = 24;
    const RETAIL_ORDER_COMPLETE_SHIPPED             = 23;
    const RETAIL_ORDER_COMPLETE_NOT_SHIPPED         = 22;
    const RETAIL_ORDER_COMPLETE                     = 21;

    const RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION    = 36;
    const RETAIL_ORDER_PARTIALLY_REFUND_PICKING_IN_PROGRESS = 35;
    const RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING       = 34;
    const RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED             = 33;
    const RETAIL_ORDER_PARTIALLY_REFUND_NOT_SHIPPED         = 32;
    const RETAIL_ORDER_PARTIALLY_REFUND                     = 31;

    const RETAIL_ORDER_FULLY_REFUND = 40;

    const RETAIL_ORDER_EXCHANGE_AWAIT_COLLECTION    = 56;
    const RETAIL_ORDER_EXCHANGE_PICKING_IN_PROGRESS = 55;
    const RETAIL_ORDER_EXCHANGE_AWAIT_PICKING       = 54;
    const RETAIL_ORDER_EXCHANGE_SHIPPED             = 53;
    const RETAIL_ORDER_EXCHANGE_NOT_SHIPPED         = 52;
    const RETAIL_ORDER_EXCHANGE                     = 51;

    const RETAIL_ORDER_CANCELED                     = 61;

    /**
     * @var \Magento\Backend\App\Action\Context
     */
    protected $context;
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;
    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;
    /**
     * @var \SM\XRetail\Model\UserOrderCounterFactory
     */
    protected $userOrderCounterFactory;
    /**
     * @var \SM\Sales\Repositories\ShipmentManagement
     */
    protected $shipmentDataManagement;
    /**
     * @var \SM\Sales\Repositories\InvoiceManagement
     */
    protected $invoiceManagement;
    /**
     * @var \Magento\Catalog\Helper\Product
     */
    protected $catalogProduct;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    /**
     * @var \SM\Payment\Model\RetailPaymentFactory
     */
    protected $retailPaymentFactory;
    /**
     * @var \SM\Shift\Model\RetailTransactionFactory
     */
    protected $retailTransactionFactory;
    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;
    /**
     * @var \SM\Integrate\Model\StoreCreditIntegrateManagement
     */
    protected $storeCreditIntegrateManagement;
    /**
     * @var \SM\Integrate\Model\RPIntegrateManagement
     */
    protected $rpIntegrateManagement;
    /**
     * @var \SM\RefundWithoutReceipt\Model\ResourceModel\RefundWithoutReceiptTransaction\CollectionFactory
     */
    protected $refundWithoutReceiptCollectionFactory;
    /**
     * @var \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransactionFactory
     */
    protected $refundWithoutReceiptTransactionFactory;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;
    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    protected $response;
    /**
     * @var \Magento\Sales\Model\Order\InvoiceRepository
     */
    protected $invoiceRepository;
    /**
     * @var \SM\Shift\Helper\Data
     */
    private $shiftHelper;
    /**
     * @var \SM\Sales\Model\OrderSyncErrorFactory
     */
    private $orderSyncErrorFactory;
    /**
     * @var \SM\Sales\Model\FeedbackFactory
     */
    private $feedbackFactory;
    /**
     * @var \SM\Sales\Repositories\OrderHistoryManagement
     */
    private $orderHistoryManagement;
    /**
     * @var \SM\XRetail\Helper\Data
     */
    private $retailHelper;

    private $currentRate;
    /**
     * @var \Magento\Tax\Helper\Data
     */
    private $taxHelper;
    /**
     * @var \SM\Integrate\Model\GCIntegrateManagement
     */
    private $gcIntegrateManagement;

    private $requestOrderData;

    private $isRefundToGC;
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var \SM\Sales\Model\ResourceModel\Feedback\CollectionFactory
     */
    protected $feedbackCollectionFactory;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;
    /**
     * @var \SM\Payment\Helper\PaymentHelper
     */
    private $paymentHelper;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    protected $metadataPool;
    /**
     * @var \Magento\Shipping\Model\Shipping
     */
    private $shippingModel;
    /**
     * @var \SM\Performance\Helper\RealtimeManager
     */
    private $realtimeManager;
    /**
     * @var \SM\Integrate\Model\WarehouseIntegrateManagement
     */
    private $warehouseIntegrateManagement;
    /**
     * @var \Magento\Config\Model\Config\Loader
     */
    protected $configLoader;
    /**
     * @var Currency
     */
    private $currencyModel;

    /**
     * @var \SM\Product\Helper\ProductHelper
     */
    private $productHelper;

    /**
     * OrderManagement constructor.
     *
     * @param \SM\XRetail\Helper\DataConfig                                                                  $dataConfig
     * @param \SM\XRetail\Helper\Data                                                                        $retailHelper
     * @param \Magento\Store\Model\StoreManagerInterface                                                     $storeManager
     * @param \Magento\Backend\App\Action\Context                                                            $context
     * @param \Magento\Framework\Registry                                                                    $registry
     * @param \SM\XRetail\Model\UserOrderCounterFactory                                                      $userOrderCounterFactory
     * @param \SM\Sales\Repositories\ShipmentManagement                                                      $shipmentManagement
     * @param \SM\Sales\Repositories\InvoiceManagement                                                       $invoiceManagement
     * @param \Magento\Catalog\Helper\Product                                                                $catalogProduct
     * @param \Magento\Customer\Model\Session                                                                $customerSession
     * @param \SM\Payment\Model\RetailPaymentFactory                                                         $retailPaymentFactory
     * @param \SM\Payment\Helper\PaymentHelper                                                               $paymentHelper
     * @param \SM\Shift\Model\RetailTransactionFactory                                                       $retailTransactionFactory
     * @param \SM\Shift\Helper\Data                                                                          $shiftHelper
     * @param \SM\Integrate\Helper\Data                                                                      $integrateHelperData
     * @param \SM\Integrate\Model\RPIntegrateManagement                                                      $RPIntegrateManagement
     * @param \SM\Integrate\Model\StoreCreditIntegrateManagement                                             $storeCreditIntegrateManagement ,
     * @param \SM\Integrate\Model\GCIntegrateManagement                                                      $GCIntegrateManagement
     * @param \SM\Sales\Model\OrderSyncErrorFactory                                                          $orderSyncErrorFactory
     * @param \SM\Sales\Model\FeedbackFactory                                                                $feedbackFactory
     * @param \SM\Sales\Model\ResourceModel\Feedback\CollectionFactory                                       $feedbackCollectionFactory
     * @param \SM\Sales\Repositories\OrderHistoryManagement                                                  $orderHistoryManagement
     * @param \Magento\Tax\Helper\Data                                                                       $taxHelper
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory                                     $collectionFactory
     * @param \Magento\Framework\App\ResourceConnection                                                      $resourceConnection
     * @param \Magento\Sales\Model\OrderFactory                                                              $orderFactory
     * @param \Magento\Framework\EntityManager\MetadataPool                                                  $metadataPool
     * @param \Magento\Shipping\Model\Shipping                                                               $shippingModel
     * @param \SM\Performance\Helper\RealtimeManager                                                         $realtimeManager
     * @param \SM\Integrate\Model\WarehouseIntegrateManagement                                               $warehouseIntegrateManagement
     * @param \SM\Product\Helper\ProductHelper                                                               $productHelper
     * @param \SM\RefundWithoutReceipt\Model\ResourceModel\RefundWithoutReceiptTransaction\CollectionFactory $refundWithoutReceiptCollectionFactory
     * @param \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransactionFactory                          $refundWithoutReceiptTransactionFactory
     * @param \Magento\Config\Model\Config\Loader                                                            $loader
     * @param \Magento\Framework\App\Response\Http\FileFactory                                               $fileFactory
     * @param Currency                                                                                       $currencyModel
     * @param \Magento\Framework\Filesystem                                                                  $filesystem
     * @param \Magento\Sales\Model\Order\InvoiceRepository                                                   $invoiceRepository
     */
    public function __construct(
        DataConfig $dataConfig,
        Data $retailHelper,
        StoreManagerInterface $storeManager,
        Context $context,
        Registry $registry,
        UserOrderCounterFactory $userOrderCounterFactory,
        ShipmentManagement $shipmentManagement,
        InvoiceManagement $invoiceManagement,
        Product $catalogProduct,
        Session $customerSession,
        RetailPaymentFactory $retailPaymentFactory,
        PaymentHelper $paymentHelper,
        RetailTransactionFactory $retailTransactionFactory,
        ShiftHelper $shiftHelper,
        IntegrateHelper $integrateHelperData,
        RPIntegrateManagement $RPIntegrateManagement,
        StoreCreditIntegrateManagement $storeCreditIntegrateManagement,
        GCIntegrateManagement $GCIntegrateManagement,
        OrderSyncErrorFactory $orderSyncErrorFactory,
        FeedbackFactory $feedbackFactory,
        feedbackCollectionFactory $feedbackCollectionFactory,
        OrderHistoryManagement $orderHistoryManagement,
        TaxHelper $taxHelper,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection,
        OrderFactory $orderFactory,
        MetadataPool $metadataPool,
        Shipping $shippingModel,
        RealtimeManager $realtimeManager,
        WarehouseIntegrateManagement $warehouseIntegrateManagement,
        ProductHelper $productHelper,
        RefundWithoutReceiptTransactionCollectionFactory $refundWithoutReceiptCollectionFactory,
        RefundWithoutReceiptTransactionFactory $refundWithoutReceiptTransactionFactory,
        Loader $loader,
        FileFactory $fileFactory,
        Currency $currencyModel,
        Filesystem $filesystem,
        InvoiceRepository $invoiceRepository
    ) {
        $this->retailTransactionFactory               = $retailTransactionFactory;
        $this->retailPaymentFactory                   = $retailPaymentFactory;
        $this->customerSession                        = $customerSession;
        $this->catalogProduct                         = $catalogProduct;
        $this->shipmentDataManagement                 = $shipmentManagement;
        $this->invoiceManagement                      = $invoiceManagement;
        $this->context                                = $context;
        $this->registry                               = $registry;
        $this->userOrderCounterFactory                = $userOrderCounterFactory;
        $this->shiftHelper                            = $shiftHelper;
        $this->integrateHelperData                    = $integrateHelperData;
        $this->rpIntegrateManagement                  = $RPIntegrateManagement;
        $this->storeCreditIntegrateManagement         = $storeCreditIntegrateManagement;
        $this->gcIntegrateManagement                  = $GCIntegrateManagement;
        $this->orderSyncErrorFactory                  = $orderSyncErrorFactory;
        $this->orderHistoryManagement                 = $orderHistoryManagement;
        $this->retailHelper                           = $retailHelper;
        $this->taxHelper                              = $taxHelper;
        $this->orderCollectionFactory                 = $collectionFactory;
        $this->orderFactory                           = $orderFactory;
        $this->feedbackFactory                        = $feedbackFactory;
        $this->productHelper                          = $productHelper;
        $this->feedbackCollectionFactory              = $feedbackCollectionFactory;
        $this->resourceConnection                     = $resourceConnection;
        $this->metadataPool                           = $metadataPool;
        $this->paymentHelper                          = $paymentHelper;
        $this->realtimeManager                        = $realtimeManager;
        $this->metadataPool                           = $metadataPool;
        $this->paymentHelper                          = $paymentHelper;
        $this->shippingModel                          = $shippingModel;
        $this->configLoader                           = $loader;
        $this->warehouseIntegrateManagement           = $warehouseIntegrateManagement;
        $this->refundWithoutReceiptCollectionFactory  = $refundWithoutReceiptCollectionFactory;
        $this->refundWithoutReceiptTransactionFactory = $refundWithoutReceiptTransactionFactory;
        $this->objectManager                          = $context->getObjectManager();
        $this->fileFactory                            = $fileFactory;
        $this->currencyModel                          = $currencyModel;
        $this->filesystem                             = $filesystem;
        $this->response                               = $context->getResponse();
        $this->invoiceRepository                      = $invoiceRepository;
        parent::__construct($context->getRequest(), $dataConfig, $storeManager);
    }

    /**
     * @param bool $isSaveOrder
     *
     * @return array|null
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function loadOrderData($isSaveOrder = false)
    {
        // see XRT-388: not collect all selection of bundle product because it not salable
        $this->catalogProduct->setSkipSaleableCheck(true);

        $data = $this->getRequest()->getParams();
        if (isset($data['is_pwa']) && $data['is_pwa'] === true) {
            $this->transformData()
                 ->checkIsPWAOrder()
                 ->checkCustomerGroup()
                 ->checkOutlet();
//                ->checkIntegrateWh();
        } else {
            $this->transformData()
                 ->checkShift()
                 ->checkCustomerGroup()
                 ->checkOutlet()
                 ->checkRegister()
                 ->checkRetailAdditionData()
                 ->checkOfflineMode()
                 ->checkIntegrateMagentoInventory()
                 ->checkIntegrateWh()
                 ->checkFeedback();
        }

        if ($isSaveOrder === true) {
            $this->checkOrderCount()
                 ->checkXRefNumCardKnox()
                 ->checkUserName()
                 ->checkTransactionIDAuthorize();
        }

        try {
            $this->initSession()
                // We must get quote after session has been created
                 ->checkShippingMethod()
                 ->checkDiscountWholeOrder()
                 ->processActionData($isSaveOrder ? "check" : null);
        } catch (Exception $e) {
            $this->clear();
            throw new Exception($e->getMessage());
        }

        $data = null;
        if (!$isSaveOrder) {
            $this->getQuote()->setIsActive(true)->save();
            $data = $this->getOutputLoadData();
            $this->clear();
        } else {
            $this->getQuote()->setIsActive(false)->save();
        }

        return $data;
    }

    /**
     * @throws \Exception
     */
    public function updateOrderNote()
    {
        $data = $this->getRequest()->getParams()['noteData'];

        /** @var  \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create();

        $collection->addFieldToFilter('entity_id', $data['order_id']);
        $dataOrder = $collection->getFirstItem();

        if ($dataOrder->getId()) {
            $dataOrder->setData('retail_note', $data['retail_note']);
            $this->saveNoteToOrderAlso($dataOrder, $data['retail_note']);
            $dataOrder->save();

            $criteria = new DataObject(
                ['entity_id' => $dataOrder->getEntityId(), 'storeId' => $dataOrder->getStoreId()]
            );

            return $this->orderHistoryManagement->loadOrders($criteria);
        }
    }

    /**
     * @throws \Exception
     */
    public function updatePrintTime()
    {
        $printTimeCounter = $this->getRequest()->getParam('printTimeCounter');
        $order_id         = $this->getRequest()->getParam('order_id');

        $dataOrder = $this->getPrintTimeCollection($order_id);

        if ($dataOrder->getId()) {
            $dataOrder->setData('print_time_counter', $printTimeCounter);
            $dataOrder->save();
        }
        $criteria = new DataObject(
            ['entity_id' => $dataOrder->getEntityId(), 'storeId' => $dataOrder->getStoreId()]
        );

        return $this->orderHistoryManagement->loadOrders($criteria);
    }

    /**
     * @param $order_id
     *
     * @return DataObject
     */
    public function getPrintTimeCollection($order_id)
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addAttributeToFilter('entity_id', $order_id);

        return $collection->getFirstItem();
    }

    public function saveOrder()
    {
        $retailId         = $this->getRequest()->getParam('retail_id');
        $outletId         = $this->getRequest()->getParam('outlet_id');
        $userId           = $this->getRequest()->getParam('user_id');
        $registerId       = $this->getRequest()->getParam('register_id');
        $customerId       = $this->getRequest()->getParam('customer_id');
        $printTimeCounter = $this->getRequest()->getParam('print_time_counter');
        $isPendingOrder   = $this->getRequest()->getParam('isPendingOrder');
        if ($this->getRequest()->getParam('orderOffline')) {
            $grandTotal = $this->getRequest()->getParam('orderOffline')['totals']['grand_total'];
            if ($retailId && $this->checkExistedOrder($retailId, $outletId, $registerId, $userId, $customerId, $grandTotal)) {
                throw new Exception(__('Duplicated order, cannot save!'));
            }
        }
        self::$SAVE_ORDER = true;
        $this->loadOrderData(true);
        try {
            $order = $this->getOrderCreateModel()
                          ->setIsValidate(true)
                          ->createOrder();
            if ($this->getRequest()->getParam('is_pwa') !== true) {
                $this->savePaymentTransaction($order);
                $this->saveNoteToOrderAlso($order);
                $this->savePrintTimeCounter($order, $printTimeCounter);
            }

            if ($this->getRequest()->getParam('refund_transaction_id')) {
                $this->updateRefundWithoutReceiptTransaction($order, $this->getRequest()->getParam('refund_transaction_id'));
            }
        } catch (Exception $e) {
            if (isset($order) && !!$order->getId()) {
                $order->setData('retail_note', $order->getData('retail_note') . ' - ' . $e->getMessage());
                $order->save();
            } elseif ($this->getRequest()->getParam('orderOffline')) {
                $this->saveOrderError($this->getRequest()->getParam('orderOffline'), $e);
            }

            throw new Exception($e->getMessage());
        } finally {
            $this->clear();
            if (isset($order) && !!$order->getId()) {
                // Save loyalty info before create ship
                try {
                    $this->addStoreCreditData($order);
                    $this->addRewardPointData($order);
                } catch (Exception $e) {
                }
                if (!$this->getRequest()->getParam('retail_has_shipment') && !$this->getQuote()->isVirtual() && !$isPendingOrder) {
                    try {
                        if (!$this->getRequest()->getParam('is_pwa') === true) {
                            $this->shipmentDataManagement->ship($order->getId());
                        }
                    } catch (\Exception $e) {
                        // ship error
                        if ($e->getMessage() === 'Negative quantity is not allowed, stock movement can not be created'
                            || $e->getMessage()
                               === 'Negative quantity is not allowed'
                            || $e->getMessage() === "Not all of your products are available in the requested quantity.") {
                            self::$MESSAGE_ERROR[] = 'can_not_create_shipment_with_negative_qty';
                        }
                    }
                }

                try {
                    if (($this->getRequest()->getParam('is_pwa') === true || $this->getRequest()->getParam('is_pwa') === 1)
                        && !$this->getRequest()
                                 ->getParam(
                                     'is_use_paypal'
                                 )) {
                    } else {
                        $this->invoiceManagement->checkPayment($order, $isPendingOrder);
                    }
                } catch (\Exception $e) {
                    // invoice error
                }
                if ($this->getRequest()->getParam('is_pwa') !== true) {
                    $this->saveOrderTaxInTableShift($order);
                }
            }
        }

        $configData = $this->getConfigLoaderData();

        if ($this->isRefundToGC && !!$this->getRequest()->getParam('order_refund_id')) {
            /** @var \Magento\Sales\Model\Order $order */
            $refundOrder = $this->orderFactory->create();
            $refundOrder->load($this->getRequest()->getParam('order_refund_id'));
            if ($refundOrder->getId()) {
                $splitData = json_decode($refundOrder->getPayment()->getAdditionalInformation('split_data'), true);
                if ($splitData) {
                    foreach ($splitData as &$paymentData) {
                        if (is_array($paymentData)
                            && $paymentData['type'] == 'refund_gift_card'
                            && $paymentData['is_purchase'] == 0) {
                            $gcProduct = $order->getItemsCollection()->getFirstItem();
                            if ($this->integrateHelperData->isAHWGiftCardExist()
                                && isset($configData['xretail/pos/integrate_gc'])
                                && $configData['xretail/pos/integrate_gc']['value'] === 'aheadWorks'
                                && $this->integrateHelperData->isIntegrateGC()) {
                                $paymentData['gc_created_codes'] = $gcProduct->getData('product_options')['aw_gc_created_codes'][0];
                                $paymentData['gc_amount']        = $gcProduct->getData('product_options')['aw_gc_amount'];
                            } elseif ($this->integrateHelperData->isGiftCardMagento2EE()
                                      && isset($configData['xretail/pos/integrate_gc'])
                                      && $configData['xretail/pos/integrate_gc']['value'] === 'mage2_ee'
                                      && $this->integrateHelperData->isIntegrateGC()) {
                                $paymentData['gc_created_codes'] = $gcProduct->getData('product_options')['giftcard_created_codes'][0];
                                $paymentData['gc_amount']        = $paymentData['amount'];
                            }
                        }
                    }
                    $refundOrder->getPayment()->setAdditionalInformation('split_data', json_encode($splitData))->save();
                }
            }
            $criteria = new DataObject(
                [
                    'entity_id' => $order->getEntityId() . "," . $refundOrder->getEntityId(),
                    'storeId'   => $this->requestOrderData['store_id'],
                    'outletId'  => $this->requestOrderData['outlet_id']
                ]
            );

            return $this->orderHistoryManagement->loadOrders($criteria);
        }

        if ($this->getRequest()->getParam('is_pwa') === true) {
            $criteria = new DataObject(
                [
                    'entity_id' => $order->getEntityId(),
                    'storeId'   => $this->requestOrderData['store_id']]
            );
        } else {
            $criteria = new DataObject(
                [
                    'entity_id' => $order->getEntityId(),
                    'storeId'   => $this->requestOrderData['store_id'],
                    'outletId'  => $this->requestOrderData['outlet_id']]
            );
        }

        return $this->orderHistoryManagement->loadOrders($criteria);
    }

    /**
     * @param $orderOffline
     * @param $e
     *
     * @return $this
     * @throws \Exception
     */
    protected function saveOrderError($orderOffline, $e)
    {
        /** @var \SM\Sales\Model\OrderSyncError $orderError */
        $orderError                  = $this->orderSyncErrorFactory->create();
        $orderOffline['pushed']      = 3; // mark as error
        $orderOffline['retail_note'] = $e->getMessage();
        $orderError->setData('order_offline', json_encode($orderOffline))
                   ->setData(
                       'retail_id',
                       $this->getRequest()->getParam('retail_id')
                   )
                   ->setData('store_id', $this->getRequest()->getParam('store_id'))
                   ->setData('outlet_id', $this->getRequest()->getParam('outlet_id'))
                   ->setData('message', $e->getMessage());
        $orderError->save();

        return $this;
    }

    /**
     * To fix amount of exchange order
     *
     * @return $this
     * @throws \Exception
     */
    protected function checkExchange($isSave)
    {
        if (!$isSave) {
            return $this;
        }
        $data  = $this->getRequest()->getParams();
        $order = $data['order'];
        if (isset($order['is_exchange']) && $order['is_exchange'] == true) {
            $this->registry->unregister('is_exchange');
            $this->registry->register('is_exchange', true);
            if ($order['payment_method'] !== RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE
                || !is_array($order['payment_data'])
                || count($order['payment_data']) > 2
            ) {
                throw new Exception("Order payment data for exchange not valid");
            }
            if ($order['payment_data'] == null && $this->isIntegrateGC()) {
                $created_at               = $this->retailHelper->getCurrentTime();
                $giftCardPaymentId        = $this->paymentHelper->getPaymentIdByType(
                    RetailPayment::GIFT_CARD_PAYMENT_TYPE
                );
                $order['payment_data'][0] = [
                    "id"                    => $giftCardPaymentId,
                    "type"                  => RetailPayment::GIFT_CARD_PAYMENT_TYPE,
                    "title"                 => "Gift Card",
                    "refund_amount"         => $this->getQuote()->getGrandTotal(),
                    "data"                  => [],
                    "isChanging"            => true,
                    "allow_amount_tendered" => true,
                    "is_purchase"           => 1,
                    "created_at"            => $created_at,
                    "payment_data"          => []
                ];
                if (count($order['payment_data']) > 0) {
                    $order['payment_data'][0]['amount']      = $this->getQuote()->getGrandTotal();
                    $order['payment_data'][0]['is_purchase'] = 1;
                    $order['payment_data']['store_id']       = $this->getRequest()->getParam('store_id');
                }
            }
            $data['order'] = $order;
        }
        if (isset($order['payment_data'])) {
            $this->getOrderCreateModel()->getQuote()->getPayment()->addData($order['payment_data']);
            $this->getOrderCreateModel()->setPaymentData($order['payment_data']);
        }
        $this->getRequest()->setParams($data);

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkShift()
    {
        $data         = $this->getRequest()->getParams();
        $openingShift = $this->shiftHelper->getShiftOpening($data['outlet_id'], $data['register_id']);
        if (!$openingShift->getData('id')) {
            throw new Exception("No Shift are opening");
        }
        $this->registry->register('opening_shift', $openingShift);

        return $this;
    }

    /**
     * @param $orderData
     *
     * @throws \Exception
     */
    protected function savePaymentTransaction($orderData)
    {
        $baseCurrencyCode    = $this->storeManager->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = $this->storeManager->getStore($orderData->getData('store_id'))->getCurrentCurrencyCode();
        $allowedCurrencies   = $this->currencyModel->getConfigAllowCurrencies();
        $rates               = $this->currencyModel->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
        $data                = $this->getRequest()->getParams();
        $order               = $data['order'];
        if (isset($order['payment_method'])
            && $order['payment_method'] == RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE) {
            $openingShift = $this->registry->registry('opening_shift');
            if (isset($order['payment_data'])
                && is_array($order['payment_data'])
                && count($order['payment_data']) > 0) {
                foreach ($order['payment_data'] as $payment_datum) {
                    if (!is_array($payment_datum)) {
                        continue;
                    }
                    if (!isset($payment_datum['id']) || !$payment_datum['id']) {
                        throw new Exception("Payment data not valid");
                    }
                    $created_at = $this->retailHelper->getCurrentTime();
                    $_p         = $this->retailTransactionFactory->create();
                    $_p->addData(
                        [
                            'outlet_id'     => $data['outlet_id'],
                            'register_id'   => $data['register_id'],
                            'shift_id'      => $openingShift->getData('id'),
                            'payment_id'    => $payment_datum['id'],
                            'payment_title' => $payment_datum['title'],
                            'payment_type'  => $payment_datum['type'],
                            'amount'        => $payment_datum['amount'],
                            'is_purchase'   => 1,
                            "created_at"    => $created_at,
                            'order_id'      => $orderData->getData('entity_id'),
                            "user_name"     => isset($data['user_name']) ? $data['user_name'] : '',
                            'base_amount'   => isset($rates[$currentCurrencyCode]) && $rates[$currentCurrencyCode] != 0 ? $payment_datum['amount']
                                                                                                                          / $rates[$currentCurrencyCode] : null,
                        ]
                    )->save();
                }
            }
        }
    }

    /**
     * save total tax into shift table when create order
     *
     * @throws \Exception
     */
    protected function saveOrderTaxInTableShift($orderData)
    {
        $orderID        = $orderData->getId();
        $state          = $this->orderFactory->create()
                                             ->load($orderID)
                                             ->getData()['state'];
        $taxClassAmount = [];
        if ($orderData instanceof Order) {
            $taxClassAmount = $this->taxHelper->getCalculatedTaxes($orderData);
        }
        $data            = $this->getRequest()->getParams();
        $tax_amount      = $orderData->getData('tax_amount');
        $base_tax_amount = $orderData->getData('base_tax_amount');
        //if (isset($tax_amount) && $tax_amount > 0) {
        $openingShift = $this->shiftHelper->getShiftOpening($data['outlet_id'], $data['register_id']);
        if (!$openingShift) {
            throw new Exception("No shift are opening");
        }

        $currentTax     = floatval($openingShift->getData('total_order_tax')) + floatval($tax_amount);
        $currentBaseTax = floatval($openingShift->getData('base_total_order_tax')) + floatval($base_tax_amount);
        $currentTaxData = json_decode($openingShift->getData('detail_tax'), true);

        $currentPoint_spent  = floatval($openingShift->getData('point_spent'));
        $currentPoint_earned = floatval($openingShift->getData('point_earned'));
        if ($this->integrateHelperData->isAHWRewardPoints()
            && $this->integrateHelperData->isIntegrateRP()
            && $state === 'complete') {
            $connection            = $this->resourceConnection->getConnectionByName(
                $this->metadataPool->getMetadata('Aheadworks\RewardPoints\Api\Data\TransactionInterface')
                                   ->getEntityConnectionName()
            );
            $select_transaction_id = $connection->select()
                                                ->from(
                                                    $this->resourceConnection->getTableName('aw_rp_transaction_entity')
                                                )
                                                ->where('entity_id =' . $orderData->getId());

            $transaction_id = $connection->fetchOne($select_transaction_id, ['transaction_id']);

            $balance = $this->rpIntegrateManagement->getTransactionByOrder($transaction_id);
            if ($balance > 0) {
                $currentPoint_earned += floatval($balance ? $balance : 0);
            } else {
                $currentPoint_spent += floatval($balance ? $balance : 0);
            }
        }

        if (count($taxClassAmount) > 0) {
            foreach ($taxClassAmount as $taxDetail) {
                $title = $taxDetail['title'] . '(' . $taxDetail['percent'] . ' %)';
                if (isset($currentTaxData[$title])) {
                    $currentTaxData[$title] = $currentTaxData[$title] + $taxDetail['tax_amount'];
                } else {
                    $currentTaxData[$title] = $taxDetail['tax_amount'];
                }
            }
        }
        $openingShift->setData('total_order_tax', "$currentTax")
                     ->setData('base_total_order_tax', "$currentBaseTax")
                     ->setData('detail_tax', json_encode($currentTaxData))
                     ->setData('point_earned', $currentPoint_earned)
                     ->setData('point_spent', $currentPoint_spent)
                     ->save();
    }

    /**
     *
     */
    public function clear()
    {
        $this->getSession()->clearStorage()->destroy();
    }

    /**
     * Process request data with additional logic for saving quote and creating order
     *
     * @param string $action
     *
     * @param array  $carriers
     *
     * @return $this
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function processActionData($action = null, $carriers = [])
    {
        if ($this->getRetailConfig()->isIntegrate()) {
            $eventData = [
                'order_create_model' => $this->getOrderCreateModel(),
                'request_model'      => $this->getRequest(),
                'session'            => $this->getSession(),
            ];
            $this->getContext()
                 ->getEventManager()
                 ->dispatch('adminhtml_sales_order_create_process_data_before', $eventData);
        }

        /*
         * Must remove all address because when magento get quote will collect total,
         * at that time quote hasn't address => magento will use default address
         * After, when we use function setBillingAddress
         * magento still check $customerAddressId -> it already existed -> can't save new billing address
         */
        $this->getOrderCreateModel()->getQuote()->removeAllAddresses();

        $data = $this->getRequest()->getParam('order');
        /**
         * Saving order data
         */
        if ($data) {
            $this->getOrderCreateModel()->importPostData($data);
        }

        /**
         * Initialize catalog rule data
         */
        if (self::$IS_COLLECT_RULE) {
            $this->getOrderCreateModel()->initRuleData();
        }

        /**
         * init first billing address, need for virtual products
         */
        $this->getOrderCreateModel()->getBillingAddress();

        /**
         * Flag for using billing address for shipping
         */
        if (!$this->getOrderCreateModel()->getQuote()->isVirtual()) {
            $syncFlag       = $this->getRequest()->getPost('shipping_as_billing');
            $shippingMethod = $this->getOrderCreateModel()->getShippingAddress()->getShippingMethod();
            if ($syncFlag === null
                && $this->getOrderCreateModel()->getShippingAddress()->getSameAsBilling()
                && empty($shippingMethod)
            ) {
                $this->getOrderCreateModel()->setShippingAsBilling(1);
            } else {
                $this->getOrderCreateModel()->setShippingAsBilling((int)$syncFlag);
            }
        }

        /**
         * Change shipping address flag
         */
        if (!$this->getOrderCreateModel()->getQuote()->isVirtual() && $this->getRequest()->getPost('reset_shipping')
        ) {
            $this->getOrderCreateModel()->resetShippingMethod();
        }

        /**
         * Adding products to quote from special grid
         */
        if ($this->getRequest()->has('items') && !$this->getRequest()->getPost('update_items') && !($action == 'save')
        ) {
            $items = $this->getRequest()->getParam('items');
            $items = $this->processFiles($items);
            $this->getOrderCreateModel()->addProducts($items);
        }

        if ($this->getRetailConfig()->isIntegrate()) {
            $eventData = [
                'order_create_model' => $this->getOrderCreateModel(),
                'request'            => $this->getRequest()->getPostValue(),
            ];

            $this->getContext()->getEventManager()->dispatch('adminhtml_sales_order_create_process_data', $eventData);
        }

        $this->checkIntegrateRP()
             ->checkIntegrateGC()
             ->checkIntegrateStoreCredit();

        // Collect shipping rate
        if (empty($carriers)) {
            $carriers = ['retailshipping'];
            if ($this->getRequest()->getParam('order')['shipping_method'] !== 'retailshipping_retailshipping') {
                $carrier     = $this->getRequest()->getParam('order')['shipping_method'];
                $carrier     = explode('_', $carrier);
                $carriers[0] = $carrier[0];
            }
        }

        /*
        *  Need unset data: cached_items_all. Because it's cache when collect total at the first time when haven't any item in quote.
        *  After, we collect it will show error not shipping has set because this can't collect shipping rates(no items)
        */
        $this->getQuote()->getBillingAddress()->unsetData("cached_items_all");
        $this->getQuote()->getShippingAddress()->unsetData("cached_items_all");

        if (isset($data['payment_data'])
            && $data['payment_method'] == RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE) {
            $this->getOrderCreateModel()
                 ->getQuote()
                 ->setTotalsCollectedFlag(false);
            $data['payment_data']['store_id'] = $this->getRequest()->getParam('store_id');
            /**
             * There may be an error in here  Magento\Quote\Model\Quote\Payment
             * $method = parent::getMethodInstance();
             * $method->setStore($this->getQuote()->getStoreId());
             * Can't get StoreId because quote is null.
             * Magento can't set quote to payment in Magento\Quote\Model\Quote:getPayment() - will set current quote to payment here.
             * But we can't get quote from session quote because it check quoteId()(if magento check id !== null instead will not occur error)
             * We don't have to fix this. Only need restrict user assign admin store to outlet.
             **/
            $this->getOrderCreateModel()->setPaymentData($data['payment_data']);
        }

        $this->checkExchange($action == 'check');

        $this->getOrderCreateModel()->saveQuote();

        if (!$this->getOrderCreateModel()->getQuote()->isVirtual()) {
            $this->getOrderCreateModel()
                ->getShippingAddress()
                ->setLimitCarrier($carriers)
                ->setCollectShippingRates(true)->collectShippingRates();

            $this->getOrderCreateModel()
                ->getQuote()
                ->setTotalsCollectedFlag(false)
                ->collectTotals();
        }

        $data       = $this->getRequest()->getParam('order');
        $couponCode = '';
        if (isset($data) && isset($data['coupon']['code'])) {
            $couponCode = trim($data['coupon']['code']);
        }

        if (!empty($couponCode)) {
            $isApplyDiscount = false;
            foreach ($this->getQuote()->getAllItems() as $item) {
                if (!$item->getNoDiscount()) {
                    $isApplyDiscount = true;
                    break;
                }
            }
            if (!$isApplyDiscount) {
                throw new Exception(
                    __(
                        '"%1" coupon code was not applied. Do not apply discount is selected for item(s)',
                        $this->escaper->escapeHtml($couponCode)
                    )
                );
            } else {
                if ($this->getQuote()->getCouponCode() !== $couponCode) {
                    throw new Exception(
                        __(
                            '"%1" coupon code is not valid.',
                            ($couponCode)
                        )
                    );
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkCustomerGroup()
    {
        $account = $this->getRequest()->getParam('account');
        if (!isset($account['group_id'])) {
            throw new Exception("Must have param customer_group_id");
        }
        $this->customerSession->setCustomerGroupId($account['group_id']);

        return $this;
    }

    /**
     * Retrieve session object
     *
     * @return \Magento\Backend\Model\Session\Quote
     */
    protected function getSession()
    {
        return $this->getContext()->getObjectManager()->get('Magento\Backend\Model\Session\Quote');
    }

    /**
     * Retrieve quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        return $this->getSession()->getQuote();
    }

    /**
     * Initialize order creation session data
     *
     * @return $this
     */
    protected function initSession()
    {
        /**
         * Identify customer
         */
        if ($customerId = $this->getRequest()->getParam('customer_id')) {
            $this->getSession()->setCustomerId((int)$customerId);
        }

        /**
         * Identify store
         */
        if ($storeId = $this->getRequest()->getParam('store_id')) {
            $this->getSession()->setStoreId((int)$storeId);
        }

        /**
         * Identify currency
         */
        if ($currencyId = $this->getRequest()->getParam('currency_id')) {
            $this->getSession()->setCurrencyId((string)$currencyId);
            $this->getOrderCreateModel()->setRecollect(true);
        }

        return $this;
    }

    /**
     * Retrieve order create model
     *
     * @return \SM\Sales\Model\AdminOrder\Create
     */
    protected function getOrderCreateModel()
    {
        //FIX for magento 2.2.3 : update magento core code in QuoteRepository :
        // $quote->$loadMethod($identifier)->setStoreId($this->storeManager->getStore()->getId());
        $storeID = $this->getRequest()->getParam('store_id');
        if (!!$storeID) {
            $this->storeManager->setCurrentStore($storeID);
        }

        return $this->getContext()->getObjectManager()->get('SM\Sales\Model\AdminOrder\Create');
    }

    /**
     * @return \Magento\Backend\App\Action\Context
     */
    protected function getContext()
    {
        return $this->context;
    }

    /**
     * @return \SM\XRetail\Helper\DataConfig
     */
    protected function getRetailConfig()
    {
        return $this->dataConfig;
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    private function getOutputLoadData()
    {
        $data = [];
        if ($this->getQuote()->isVirtual()) {
            $address = $this->getQuote()->getBillingAddress();
            $totals  = $this->getQuote()->getBillingAddress()->getTotals();
        } else {
            $address = $this->getQuote()->getShippingAddress();
            $totals  = $this->getQuote()->getShippingAddress()->getTotals();
        }
        $data['totals'] = [
            'subtotal'                     => $address->getData('subtotal'),
            'subtotal_incl_tax'            => $address->getData('subtotal_incl_tax'),
            'real_tax_for_display_in_xpos' => $address->getTaxAmount() + $address->getDiscountTaxCompensationAmount(),
            'tax_only'                     => $totals['tax']->getData('value'),
            'shipping'                     => $address->getData('shipping_amount'),
            'shipping_incl_tax'            => $address->getData('shipping_incl_tax'),
            'shipping_method'              => $address->getShippingMethod(),
            'shipping_discount'            => $address->getShippingDiscountAmount(),
            'shipping_tax_amount'          => $address->getShippingTaxAmount(),
            'discount'                     => isset($totals['discount']) ? $totals['discount']->getValue() : 0,
            'grand_total'                  => $totals['grand_total']->getData('value'),
            'applied_taxes'                => $address->getData('applied_taxes') ? $this->retailHelper->unserialize($address->getData('applied_taxes')) : null,
            'cart_fixed_rules'             => $address->getData('cart_fixed_rules'),
            'applied_rule_ids'             => $address->getData('applied_rule_ids'),
            'retail_discount_per_item'     => $this->getQuote()->getData('retail_discount_per_item'),
            'coupon_code'                  => $this->getQuote()->getCouponCode()
        ];

        $data['items'] = $this->orderHistoryManagement->getOrderItemData($address->getAllItems());
        if ($this->integrateHelperData->isIntegrateWH() || $this->integrateHelperData->isMagentoInventory()) {
            foreach ($data['items'] as $item) {
                $isSalable = $this->warehouseIntegrateManagement->isSalableQty($item);
                if (!$isSalable) {
                    throw new Exception("The requested qty is not available");
                }
            }
        }
        $data['totals'] = array_map(
            function ($number) {
                if (is_numeric($number)) {
                    return round($number, 2);
                } else {
                    return $number;
                }
            },
            $data['totals']
        );

        if ($this->integrateHelperData->isIntegrateStoreCredit()
            && $this->integrateHelperData->isExistStoreCreditMagento2EE()) {
            $data['store_credit'] = $this->storeCreditIntegrateManagement->getQuoteStoreCreditData();
        }

        if ($this->integrateHelperData->isIntegrateRP()) {
            $data['reward_point'] = $this->rpIntegrateManagement->getQuoteRPData();
        }

        if ($this->integrateHelperData->isIntegrateGC()
            || ($this->integrateHelperData->isIntegrateGCInPWA()
                && $this->getRequest()->getParam(
                    'is_pwa'
                ) === true)) {
            $giftCardRequest = $this->getRequest()->getParam('gift_card');
            if ($giftCardRequest) {
                $data['gift_card'] = $this->gcIntegrateManagement->getQuoteGCData();
            } else {
                $data['gift_card'] = [];
            }
        }

        return $data;
    }

    /**
     * Process buyRequest file options of items
     *
     * @param array $items
     *
     * @return array
     */
    protected function processFiles($items)
    {
        /* @var $productHelper \Magento\Catalog\Helper\Product */
        $productHelper = $this->getContext()->getObjectManager()->get('Magento\Catalog\Helper\Product');
        foreach ($items as $id => $item) {
            $buyRequest = new \Magento\Framework\DataObject($item);
            $params     = ['files_prefix' => 'item_' . $id . '_'];
            $buyRequest = $productHelper->addParamsToBuyRequest($buyRequest, $params);
            if ($buyRequest->hasData()) {
                $items[$id] = $buyRequest->toArray();
            }
        }

        return $items;
    }

    /**
     * @param null $configData
     *
     * @return $this
     */
    private function transformData($configData = null)
    {
        $configData             = $this->getConfigLoaderData();
        $this->requestOrderData = $data = $this->getRequest()->getParams();
        $order                  = $this->getRequest()->getParam('order');
        $items                  = $this->getRequest()->getParam('items');

        if (is_array($items)) {
            foreach ($items as $key => $value) {
                if (isset($value['gift_card'])) {
                    if (isset($items[$key]['gift_card']['aw_gc_amount'])) {
                        $items[$key]['gift_card']['giftcard_amount'] = $items[$key]['gift_card']['aw_gc_amount'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_custom_amount'])) {
                        $items[$key]['gift_card']['custom_giftcard_amount'] = $items[$key]['gift_card']['aw_gc_custom_amount'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_sender_name'])) {
                        $items[$key]['gift_card']['giftcard_sender_name'] = $items[$key]['gift_card']['aw_gc_sender_name'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_sender_email'])) {
                        $items[$key]['gift_card']['giftcard_sender_email'] = $items[$key]['gift_card']['aw_gc_sender_email'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_recipient_name'])) {
                        $items[$key]['gift_card']['giftcard_recipient_name'] = $items[$key]['gift_card']['aw_gc_recipient_name'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_recipient_email'])) {
                        $items[$key]['gift_card']['giftcard_recipient_email'] = $items[$key]['gift_card']['aw_gc_recipient_email'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_headline'])) {
                        $items[$key]['gift_card']['giftcard_headline'] = $items[$key]['gift_card']['aw_gc_headline'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_message'])) {
                        $items[$key]['gift_card']['giftcard_message'] = $items[$key]['gift_card']['aw_gc_message'];
                    }
                    if (isset($items[$key]['gift_card']['aw_gc_code'])) {
                        $queue = $this->registry->registry('aw_gc_code');
                        if (!$queue || $queue->isEmpty()) {
                            $queue = new \SplQueue();
                        }
                        $queue->enqueue($items[$key]['gift_card']['aw_gc_code']);
                        $this->registry->unregister('aw_gc_code');
                        $this->registry->register('aw_gc_code', $queue);
                    }
                }
                if (isset($value['options']) && isset($value['product_options_custom_option'])) {
                    foreach ($value['product_options_custom_option'] as $opt) {
                        if (isset($opt['option_type']) && $this->_isMultipleSelection($opt['option_type']) && is_string($value['options'][$opt['option_id']])) {
                            $items[$key]['options'][$opt['option_id']] = explode(",", $value['options'][$opt['option_id']]);
                        }
                    }
                }
            }
            $data['items'] = $items;
        }

        if (isset($order['billing_address']['first_name'])) {
            $order['billing_address']['firstname'] = $order['billing_address']['first_name'];
        }
        if (isset($order['billing_address']['middle_name'])) {
            $order['billing_address']['middlename'] = $order['billing_address']['middle_name'];
        }
        if (isset($order['billing_address']['last_name'])) {
            $order['billing_address']['lastname'] = $order['billing_address']['last_name'];
        }
        if (!is_array($order['billing_address']['street'])) {
            $order['billing_address']['street'] = [$order['billing_address']['street']];
        }
        if ($order['billing_address']['region_id'] == "*") {
            $order['billing_address']['region_id'] = null;
        }

        if (isset($order['shipping_address']['first_name'])) {
            $order['shipping_address']['firstname'] = $order['shipping_address']['first_name'];
        }
        if (isset($order['shipping_address']['middle_name'])) {
            $order['shipping_address']['middlename'] = $order['shipping_address']['middle_name'];
        }
        if (isset($order['shipping_address']['last_name'])) {
            $order['shipping_address']['lastname'] = $order['shipping_address']['last_name'];
        }
        if (!is_array($order['shipping_address']['street'])) {
            $order['shipping_address']['street'] = [$order['shipping_address']['street']];
        }
        // fix region id for magento 2.2.3 or above
        if ($order['shipping_address']['region_id'] == "*") {
            $order['shipping_address']['region_id'] = null;
        }

        if ($this->checkIsRefundToGiftCard()) {
            $refundToGCProductId = $this->gcIntegrateManagement->getRefundToGCProductId();
            $giftCardItems       = [
                'qty'        => 1,
                'product_id' => $refundToGCProductId,
                'product'    => null,
            ];
            if ($this->integrateHelperData->isAHWGiftCardExist()
                && isset($configData['xretail/pos/integrate_gc'])
                && $configData['xretail/pos/integrate_gc']['value'] === 'aheadWorks'
                && $this->integrateHelperData->isIntegrateGC()) {
                $giftCardItems['gift_card'] = [
                    'aw_gc_amount'        => "custom",
                    'aw_gc_custom_amount' => $order['payment_data'][0]['refund_amount'] - $data['order']['payment_data'][0]['amount'],
                    'aw_gc_template'      => 'aw_giftcard_email_template',
                    'aw_gc_sender_email'  => $order['payment_data'][0]['sender_email'],
                    'aw_gc_sender_name'   => $order['payment_data'][0]['sender_name'],

                    'aw_gc_recipient_email' => $order['payment_data'][0]['recipient_email'],
                    'aw_gc_recipient_name'  => $order['payment_data'][0]['recipient_name']
                ];
            } elseif ($this->integrateHelperData->isGiftCardMagento2EE()
                      && isset($configData['xretail/pos/integrate_gc'])
                      && $configData['xretail/pos/integrate_gc']['value'] === 'mage2_ee'
                      && $this->integrateHelperData->isIntegrateGC()) {
                $giftCardItems['gift_card'] = [
                    'giftcard_amount'        => "custom",
                    'custom_giftcard_amount' => $order['payment_data'][0]['refund_amount'] - $data['order']['payment_data'][0]['amount'],
                    'giftcard_sender_email'  => $order['payment_data'][0]['sender_email'],
                    'giftcard_sender_name'   => $order['payment_data'][0]['sender_name'],

                    'giftcard_recipient_email' => $order['payment_data'][0]['recipient_email'],
                    'giftcard_recipient_name'  => $order['payment_data'][0]['recipient_name']
                ];
            }
            array_push($data['items'], $giftCardItems);

            $data['order']['payment_data'][0]['isChanging'] = true;
            if ($data['order']['payment_data'][0]['amount'] == 0) {
                $data['order']['payment_data'][0]['amount'] = $data['order']['payment_data'][0]['refund_amount'];
            }
            $this->registry->register(self::USING_REFUND_TO_GIFT_CARD, true);
        } else {
            $this->registry->register(self::USING_REFUND_TO_GIFT_CARD, false);
        }

        $data['order'] = $order;

        // gift card data
        $data['items'] = array_map(
            function ($buyRequest) {
                if (isset($buyRequest['gift_card'])) {
                    foreach ($buyRequest['gift_card'] as $key => $value) {
                        if ($key === 'aw_gc_delivery_date' && isset($value['data_date'])) {
                            $buyRequest[$key] = $value['data_date'];
                        } else {
                            $buyRequest[$key] = $value;
                        }
                    }
                }

                return $buyRequest;
            },
            $data['items']
        );

        $this->getRequest()->setParams($data);

        return $this;
    }

    /**
     *For test
     *
     * @param bool $isExchange
     */
    private function dummyData($isExchange = false)
    {
        $data = [
            'items'       => [
                [
                    'qty'               => '2',
                    'discount_per_item' => 10,
                    'product_id'        => 1
                ],
            ],
            'account'     => [
                'group_id' => 2,
                'email'    => 'roni_cost@example.com'
            ],
            'customer_id' => '1',
            'store_id'    => '1',
            'order'       => [
                'billing_address'          => [
                    'firstname'  => 'Veronica2324',
                    'middlename' => 'Bla',
                    'lastname'   => 'Costello',
                    'company'    => 'Taxa',
                    'street'     => [
                            0 => '6146 Honey Bluff Parkway',
                        ],
                    'city'       => 'Calder',
                    'country_id' => 'US',
                    'region_id'  => '43',
                    'region'     => 'NewJersey',
                    'postcode'   => '49628-7978',
                    'telephone'  => '(555) 229-3326',
                ],
                'shipping_address'         => [
                    'firstname'  => 'Veronica2324',
                    'middlename' => 'Bla',
                    'lastname'   => 'Costello',
                    'company'    => 'Taxa',
                    'street'     => [
                            0 => '6146 Honey Bluff Parkway',
                        ],
                    'city'       => 'Calder',
                    'country_id' => 'US',
                    'region_id'  => '43',
                    'region'     => 'NewJersey',
                    'postcode'   => '49628-7978',
                    'telephone'  => '(555) 229-3326',
                ],
                'payment_method'           => 'retailmultiple',
                'shipping_method'          => 'retailshipping_retailshipping',
                'shipping_amount'          => 0,
                'shipping_same_as_billing' => 'on',
                'payment_data'             => [
                    'checkmo'        => 123,
                    'cashondelivery' => 345
                ],
                'coupon'                   => [
                    'code' => 75
                ],
            ]
        ];
        if ($isExchange) {
            $data['creditmemo'] = [
                'items'               => [
                        1128 => [
                                'qty' => '1',
                            ],
                    ],
                'order_id'            => 281,
                'do_offline'          => '1',
                'comment_text'        => '',
                'shipping_amount'     => '0',
                'adjustment_positive' => '0',
                'adjustment_negative' => '0',
            ];
        }
        $this->getRequest()->setParams($data);
    }

    /**
     * @return $this
     */
    private function checkDiscountWholeOrder()
    {
        $order = $this->getRequest()->getParam('order');
        if (isset($order['whole_order_discount'])
            && isset($order['whole_order_discount']['value'])
            && $order['whole_order_discount']['value'] > 0
        ) {
            self::$IS_COLLECT_RULE = true;
            if (isset($order['whole_order_discount']['isPercentMode'])
                && $order['whole_order_discount']['isPercentMode'] !== true) {
                $order['whole_order_discount']['value'] = $order['whole_order_discount']['value'] / $this->getCurrentRate();
            }
            $this->registry->register(self::DISCOUNT_WHOLE_ORDER_KEY, $order['whole_order_discount']);
        } else {
            $this->registry->register(self::DISCOUNT_WHOLE_ORDER_KEY, false);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function checkShippingMethod()
    {
        $order          = $this->getRequest()->getParam('order');
        $shippingAmount = 0;
        if (isset($order['shipping_amount']) && !is_nan($order['shipping_amount'])) {
            $shippingAmount = $order['shipping_amount'];
        }

        $this->registry->register(
            RetailShipping::RETAIL_SHIPPING_AMOUNT_KEY,
            $shippingAmount / $this->getCurrentRate()
        );
        $this->registry->register('retail_has_shipment', $this->getRequest()->getParam('retail_has_shipment'));
        self::$FROM_API = true;

        return $this;
    }

    private function getCurrentRate()
    {
        if ($this->currentRate === null) {
            $quote             = $this->getOrderCreateModel()->getQuote();
            $this->currentRate = $quote->getStore()
                                       ->getBaseCurrency()
                                       ->convert(1, $quote->getQuoteCurrencyCode());
        }

        return $this->currentRate;
    }

    /**
     * @return $this
     */
    private function checkOfflineMode()
    {
        if ($this->getRequest()->getParam('is_offline')) {
            self::$IS_COLLECT_RULE = false;
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkOutlet()
    {
        $outletId = $this->getRequest()->getParam('outlet_id');
        if (!!$outletId) {
            $this->registry->unregister('outlet_id');
            $this->registry->register('outlet_id', $outletId);
        } else {
            throw new Exception("Please define outlet when save order");
        }

        $this->registry->unregister('retail_note');
        $this->registry->register('retail_note', $this->getRequest()->getParam('retail_note'));

        $pickup_outlet_id = $this->getRequest()->getParam('pickup_outlet_id');
        if (!!$pickup_outlet_id) {
            $this->registry->unregister('pickup_outlet_id');
            $this->registry->register('pickup_outlet_id', $pickup_outlet_id);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkFeedback()
    {
        $retailId   = $this->getRequest()->getParam('retail_id');
        $collection = $this->feedbackCollectionFactory->create();

        $collection->addFieldToFilter('retail_id', $retailId);
        $dataFeedback = $collection->getFirstItem();

        if (!!$dataFeedback->getId()) {
            $this->registry->unregister('order_feedback');
            $this->registry->register('order_feedback', $dataFeedback->getData('retail_feedback'));
            $this->registry->unregister('order_rate');
            $this->registry->register('order_rate', $dataFeedback->getData('retail_rate'));
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkRegister()
    {
        // need register id for report
        $registerId = $this->getRequest()->getParam('register_id');
        if (!!$registerId) {
            $this->registry->unregister('register_id');
            $this->registry->register('register_id', $registerId);
        } else {
            throw new Exception("Please define register when save order");
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkUserName()
    {
        $username = $this->getRequest()->getParam('user_name');
        if (!!$username) {
            $this->registry->unregister('user_name');
            $this->registry->register('user_name', $username);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkXRefNumCardKnox()
    {
        // need reference number CardKnox for report
        $xRefNum = $this->getRequest()->getParam('xRefNum');
        if (!!$xRefNum) {
            $this->registry->unregister('xRefNum');
            $this->registry->register('xRefNum', $xRefNum);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkTransactionIDAuthorize()
    {
        // need reference number Authorize for report
        $transId = $this->getRequest()->getParam('transId');
        if (!!$transId) {
            $this->registry->unregister('transId');
            $this->registry->register('transId', $transId);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function checkOrderCount()
    {
        $orderCount = $this->getRequest()->getParam('retail_id');
        if (!!$orderCount) {
            $this->registry->unregister('retail_id');
            $this->registry->register('retail_id', $orderCount);
            $count      = intval(substr($orderCount, -8));
            $outletId   = $this->getRequest()->getParam('outlet_id');
            $userId     = $this->getRequest()->getParam('user_id');
            $registerId = $this->getRequest()->getParam('register_id');
            $sellerIds  = $this->getRequest()->getParam('sellers');
            $sellerUsername  = $this->getRequest()->getParam('sellersUsername');
            // save cashier to order
            if (!!$userId) {
                $this->registry->unregister('user_id');
                $this->registry->register('user_id', $userId);
            }
            if (!!$sellerIds) {
                $this->registry->unregister('sm_seller_ids');
                $this->registry->register('sm_seller_ids', implode(",", $sellerIds));
            }

            if (!!$sellerUsername) {
                $this->registry->unregister('sm_seller_username');
                $this->registry->register('sm_seller_username', $sellerUsername);
            }

            /** @var \SM\XRetail\Model\UserOrderCounter $userOrderCounterModel */
            $userOrderCounterModel = $this->userOrderCounterFactory->create();
            $orderCount            = $userOrderCounterModel->loadOrderCount($outletId, $registerId, $userId);
            $orderCount->setData('order_count', $count)
                       ->setData('user_id', $userId)
                       ->setData('outlet_id', $outletId)
                       ->setData('register_id', $registerId)
                       ->save();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function checkRetailAdditionData()
    {
        $retailAdditionData = $this->getRequest()->getParam('retail_addition_data');
        // check has custom sale
        if (isset($retailAdditionData['has_custom_sale']) && $retailAdditionData['has_custom_sale'] == true) {
            $this::$ORDER_HAS_CUSTOM_SALE = true;
        }

        $estimatedAvailability = $this->getRequest()->getParam('estimated_availability');

        if (!!$estimatedAvailability) {
            $this->registry->unregister('estimated_availability');
            $this->registry->register('estimated_availability', $estimatedAvailability);
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function checkIntegrateRP()
    {
        if ($this->integrateHelperData->isIntegrateRP()) {
            $this->rpIntegrateManagement->saveRPDataBeforeQuoteCollect($this->getRequest()->getParam('reward_point'));
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function checkIntegrateGC()
    {
        if (($this->integrateHelperData->isIntegrateGC()
             || ($this->integrateHelperData->isIntegrateGCInPWA()
                 && $this->getRequest()->getParam(
                        'is_pwa'
                 ) === true))
            && $this->getRequest()->getParam('gift_card')) {
            $this->gcIntegrateManagement->saveGCDataBeforeQuoteCollect($this->getRequest()->getParam('gift_card'));
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function checkIntegrateStoreCredit()
    {
        if ($this->integrateHelperData->isIntegrateStoreCredit()
            && $this->integrateHelperData->isExistStoreCreditMagento2EE()) {
            $this->storeCreditIntegrateManagement->saveStoreCreditDataBeforeQuoteCollect(
                $this->getRequest()->getParam('store_credit')
            );
        }

        return $this;
    }

    protected function isIntegrateGC()
    {
        $configData = $this->getConfigLoaderData();
        if ($this->integrateHelperData->isAHWGiftCardExist()
            && isset($configData['xretail/pos/integrate_gc'])
            && $configData['xretail/pos/integrate_gc']['value'] === 'aheadWorks'
            && ($this->integrateHelperData->isIntegrateGC()
                || ($this->integrateHelperData->isIntegrateGCInPWA()
                    && $this->getRequest()->getParam(
                        'is_pwa'
                    ) === true))
            && $this->getRequest()->getParam('gift_card')) {
            return true;
        }

        return false;
    }

    protected function checkIntegrateWh()
    {
        if ($this->integrateHelperData->isIntegrateWH()) {
            WarehouseIntegrateManagement::setWarehouseId($this->getRequest()->getParam('warehouse_id'));
        }

        return $this;
    }

    protected function checkIntegrateMagentoInventory()
    {
        if ($this->integrateHelperData->isMagentoInventory()) {
            WarehouseIntegrateManagement::setWarehouseId($this->getRequest()->getParam('warehouse_id'));
        }

        return $this;
    }

    /**
     * @return bool
     */
    protected function checkIsRefundToGiftCard()
    {
        $data         = $this->getRequest()->getParams();
        $order        = $data['order'];
        $isRefundByGC = false;

        if (isset($order['payment_data']) && is_array($order['payment_data']) && count($order['payment_data']) == 1) {
            if (isset($order['payment_data'][0]['type']) && $order['payment_data'][0]['type'] == 'refund_gift_card') {
                $isRefundByGC = true;
            }
        }

        if ($this->integrateHelperData->isIntegrateGC()
            && isset($order['is_exchange'])
            && $order['is_exchange'] == true
            && $isRefundByGC
            && is_array($data['items'])
            && count($data['items']) == 0) {
            $this->isRefundToGC = true;
        } else {
            $this->isRefundToGC = false;
        }

        return $this->isRefundToGC;
    }

    protected function checkIsPWAOrder()
    {
        $isPWA = $this->getRequest()->getParam('is_pwa');
        if (!!$isPWA) {
            $this->registry->unregister('is_pwa');
            $this->registry->register('is_pwa', 1);
        }
        $storeId = $this->getRequest()->getParam('store_id');
        if ($this->productHelper->getPWAOutOfStockStatus($storeId) === 'no') {
        } else {
            $this->registry->unregister('is_connectpos');
            $this->registry->register('is_connectpos', true);
        }

        return $this;
    }

    public function rateOrder()
    {
        $data = $this->getRequest()->getParams();
        /** @var  \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create();

        $collection->addFieldToFilter('retail_id', $data['retailId']);
        $dataOrder = $collection->getFirstItem();

        if ($dataOrder->getId()) {
            $dataOrder->setData('order_feedback', $data['noteData']);
            $dataOrder->setData('order_rate', $data['rateStar']);
            $dataOrder->save();

            $criteria = new DataObject(['entity_id' => $dataOrder->getEntityId(), 'storeId' => $dataOrder->getStoreId()]);
            $this->realtimeManager->trigger(RealtimeManager::ORDER_ENTITY, $dataOrder->getId(), RealtimeManager::TYPE_CHANGE_NEW);

            return $this->orderHistoryManagement->loadOrders($criteria);
        } else {
            $feedback = $this->feedbackFactory->create();
            $feedback->setData('retail_id', $data['retailId']);
            $feedback->setData('retail_feedback', $data['noteData']);
            $feedback->setData('retail_rate', $data['rateStar']);
            $feedback->save();
        }
    }

    /**
     * @param      $order
     * @param null $comment
     */

    protected function saveNoteToOrderAlso($order, $comment = null)
    {
        if ($comment != null || $comment = $this->getRequest()->getParam("retail_note")) {
            /** @var \SM\Sales\Model\AdminOrder\Create $order */
            $order->addStatusHistoryComment($comment)
                  ->setIsCustomerNotified(false)
                  ->setEntityName('order')
                  ->save();
        }
    }

    /**
     * @param $order
     * @param $printTimeCounter
     */
    protected function savePrintTimeCounter($order, $printTimeCounter)
    {
        /** @var \SM\Sales\Model\AdminOrder\Create $order */
        $order->setData('print_time_counter', $printTimeCounter)
              ->save();
    }

    /**
     * @throws \Exception
     */
    public function calculateShippingRates()
    {
        $configData = $this->getConfigLoaderData();
        $this->catalogProduct->setSkipSaleableCheck(true);
        $allowShippingCarriers = $this->getAllowedShippingMethods();

        $this->transformData($configData)
             ->checkShift()
             ->checkCustomerGroup()
             ->checkOutlet()
             ->checkRegister()
             ->checkRetailAdditionData()
             ->checkOfflineMode()
             ->checkIntegrateWh();

        try {
            $this->initSession()
                // We must get quote after session has been created
                 ->checkShippingMethod()
                 ->checkDiscountWholeOrder()
                 ->processActionData(null, $allowShippingCarriers);
        } catch (Exception $e) {
            $this->clear();
            throw new Exception($e->getMessage());
        }
        $quote           = $this->getOrderCreateModel()->getQuote();


        $shippingAddress = $quote->getShippingAddress();
        $rates           = $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->getGroupedAllShippingRates();

        $arr = [];
        foreach ($rates as $rate) {
            foreach ($rate as $item) {
                $rateData = $item->getData();
                if (in_array($rateData['carrier'], $allowShippingCarriers) || strpos($rateData['carrier'], 'shq') !== false) {
                    $arr[] = $rateData;
                }
            }
        }

        return $this->getSearchResult()
                    ->setItems($arr)
                    ->setTotalCount(1)
                    ->setLastPageNumber(1)
                    ->getOutput();
    }

    /**
     * @param Order $order
     *
     * @return $this
     * @throws \Exception
     */
    protected function addStoreCreditData($order)
    {
        if ($this->getRequest()->getParam('store_credit')) {
            $storeCredit     = $this->getRequest()->getParam('store_credit');
            $storeCreditData = $storeCredit['customer_balance_base_currency'] - ($storeCredit['store_credit_discount_amount']
                                                                                 / $this->getCurrentRate());
            $order->setData('store_credit_balance', $storeCreditData);
            $order->save();
        }
    }

    /**
     * @param Order $order
     *
     * @return $this
     * @throws \Exception
     */
    protected function addRewardPointData($order)
    {
        if ($this->getRequest()->getParam('reward_point')) {
            $reward_point_data = $this->getRequest()->getParam('reward_point');
            $order->setData('reward_points_earned', $reward_point_data['reward_point_earn']);
            $order->setData('reward_points_earned_amount', $reward_point_data['reward_point_earn_amount']);
            $order->setData('reward_points_redeemed', $reward_point_data['reward_point_spent']);
            $order->setData('previous_reward_points_balance', $reward_point_data['customer_balance']);
            $order->save();
        }
    }

    /**
     * function get shipping method allowed
     *
     * @return array
     */
    public static function getAllowedShippingMethods()
    {
        return ['smstorepickup', 'dhl', 'ups', 'usps', 'fedex', 'flatrate', 'tablerate', 'matrixrate', 'shipper'];
    }

    protected function checkExistedOrder($retailId, $outletId, $registerId, $userId, $customerId, $grandTotal)
    {
        $orderModel = $this->orderCollectionFactory->create();
        $orderModel->addFieldToFilter('retail_id', ['eq' => $retailId])
                   ->addFieldToFilter('outlet_id', ['eq' => $outletId])
                   ->addFieldToFilter('register_id', ['eq' => $registerId])
                   ->addFieldToFilter('user_id', ['eq' => $userId])
                   ->addFieldToFilter('customer_id', ['eq' => $customerId])
                   ->addFieldToFilter('grand_total', ['eq' => $grandTotal]);

        return $orderModel->getSize() > 0;
    }

    public static function checkClickAndCollectOrderByCode($code)
    {
        $code = intval($code);
        switch ($code) {
            case OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_PICKING:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING:
            case OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING:
                return "await_picking";
            case OrderManagement::RETAIL_ORDER_EXCHANGE_PICKING_IN_PROGRESS:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_PICKING_IN_PROGRESS:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_PICKING_IN_PROGRESS:
            case OrderManagement::RETAIL_ORDER_COMPLETE_PICKING_IN_PROGRESS:
                return "picking_in_progress";
            case OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_COLLECTION:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_COLLECTION:
            case OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_COLLECTION:
                return "await_collection";
            case OrderManagement::RETAIL_ORDER_EXCHANGE_SHIPPED:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED:
            case OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED:
            case OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_SHIPPED:
            case OrderManagement::RETAIL_ORDER_COMPLETE:
                return "done_collection";
            default:
                return "other_status";
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $transactionId
     *
     * @throws \Exception
     */
    protected function updateRefundWithoutReceiptTransaction($order, $transactionId)
    {
        /** @var \SM\RefundWithoutReceipt\Model\RefundWithoutReceiptTransaction $transaction */
        $transaction = $this->refundWithoutReceiptTransactionFactory->create()->load($transactionId);
        if ($transaction->getId()) {
            try {
                $transaction->setExchangeOrderId($order->getEntityId());
                $transaction->setExchangeOrderIncrementId($order->getIncrementId());
                $transaction->save();
            } catch (Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }
    }

    /**
     * @return mixed
     */
    private function getConfigLoaderData()
    {
        return $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
    }

    /**
     * @return array|\Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function printMagentoInvoice()
    {
        $invoiceId = $this->getSearchCriteria()->getData('invoiceId');
        $invoice   = $this->invoiceRepository->get($invoiceId);

        if ($invoice) {
            return $this->generatePdfInvoice($invoice);
        }

        return null;
    }

    /**
     * @param $invoice
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function generatePdfInvoice($invoice)
    {
        $this->registry->register('print_magento_invoice_from_cpos', true);
        $pdf           = $this->objectManager->create(\Magento\Sales\Model\Order\Pdf\Invoice::class)->getPdf([$invoice]);
        $date          = $this->objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->date('Y-m-d_H-i-s');
        $baseDir       = DirectoryList::MEDIA;
        $contentType   = 'application/pdf';
        $contentLength = null;
        $dir           = $this->filesystem->getDirectoryWrite($baseDir);
        $isFile        = false;
        $file          = null;

        if ($this->integrateHelperData->isExistSnmportalPdfprint()) {
            $engine = $this->objectManager->create(\Snmportal\Pdfprint\Model\Pdf\InvoiceFactory::class);
            $documents = [$invoice];
            $fileContent = $engine->getPdf($documents)->render();
            $fileName = $engine->getEmailFilename();
        } else {
            $fileName      = 'invoice' . $date . '.pdf';
            $fileContent   = $pdf->render();
        }

        $this->response->setHttpResponseCode(200)
                       ->setHeader('Pragma', 'public', true)
                       ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
                       ->setHeader('Content-type', $contentType, true)
                       ->setHeader('Content-Length', $contentLength === null ? strlen($fileContent) : $contentLength, true)
                       ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"', true)
                       ->setHeader('Last-Modified', date('r'), true);

        if ($fileContent !== null) {
            $this->response->sendHeaders();
            $dir->writeFile($fileName, $fileContent);
            $file   = $fileName;
            $stream = $dir->openFile($fileName, 'r');
            while (!$stream->eof()) {
                print $stream->read(1024);
            }
            $stream->close();
            flush();
            $dir->delete($file);
        }

        return $this->response;
    }

    protected function _isMultipleSelection($type)
    {
        $single = [
            'checkbox',
            'multiple',
        ];
        return in_array($type, $single);
    }
}

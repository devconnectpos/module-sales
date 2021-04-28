<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 07/01/2017
 * Time: 10:56
 */

namespace SM\Sales\Repositories;

use Exception;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use SM\Core\Api\Data\CustomerAddress;
use SM\Core\Api\Data\XOrder;
use SM\Customer\Helper\Data as CustomerHelper;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Payment\Model\RetailMultiple;
use SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory as OrderSyncErrorCollectionFactory;
use SM\XRetail\Helper\Data;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

/**
 * Class OrderHistoryManagement
 *
 * @package SM\Sales\Repositories
 */
class OrderHistoryManagement extends ServiceAbstract
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var \SM\Customer\Helper\Data
     */
    protected $customerHelper;

    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;
    /**
     * @var \SM\XRetail\Model\OutletRepository
     */
    protected $outletRepository;
    /**
     * @var \SM\Core\Api\Data\XOrderFactory
     */
    protected $xOrderFactory;
    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    private $productMediaConfig;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory
     */
    private $orderErrorCollectionFactory;

    /**
     * @var \SM\XRetail\Helper\Data
     */
    private $retailHelper;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \SM\Product\Repositories\ProductManagement
     */
    protected $productManagement;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var XOrder\XOrderItemFactory
     */
    protected $xOrderItemFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Tax\CollectionFactory
     */
    private $taxCollectionFactory;

    /**
     * OrderHistoryManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface $requestInterface
     * @param \SM\XRetail\Helper\DataConfig $dataConfig
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \SM\XRetail\Helper\Data $retailHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory
     * @param \SM\Customer\Helper\Data $customerHelper
     * @param \SM\Integrate\Helper\Data $integrateHelperData
     * @param \Magento\Catalog\Model\Product\Media\Config $productMediaConfig
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     * @param \SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory $orderErrorCollectionFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \SM\Product\Repositories\ProductManagement $productManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param XOrder\XOrderItemFactory $xOrderItemFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Tax\CollectionFactory $taxCollectionFactory
     * @param \SM\XRetail\Model\OutletRepository $outletRepository
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        Data $retailHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $collectionFactory,
        CustomerHelper $customerHelper,
        IntegrateHelper $integrateHelperData,
        Config $productMediaConfig,
        CustomerFactory $customerFactory,
        OrderSyncErrorCollectionFactory $orderErrorCollectionFactory,
        CartRepositoryInterface $quoteRepository,
        OrderFactory $orderFactory,
        \SM\Product\Repositories\ProductManagement $productManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \SM\Core\Api\Data\XOrder\XOrderItemFactory $xOrderItemFactory,
        \SM\Core\Api\Data\XOrderFactory $xOrderFactory,
        \Magento\Sales\Model\ResourceModel\Order\Tax\CollectionFactory $taxCollectionFactory,
        \SM\XRetail\Model\OutletRepository $outletRepository
    ) {
        $this->productMediaConfig = $productMediaConfig;
        $this->productRepository = $productRepository;
        $this->customerHelper = $customerHelper;
        $this->orderCollectionFactory = $collectionFactory;
        $this->integrateHelperData = $integrateHelperData;
        $this->customerFactory = $customerFactory;
        $this->retailHelper = $retailHelper;
        $this->orderErrorCollectionFactory = $orderErrorCollectionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->orderFactory = $orderFactory;
        $this->productManagement = $productManagement;
        $this->orderRepository = $orderRepository;
        $this->xOrderItemFactory = $xOrderItemFactory;
        $this->taxCollectionFactory = $taxCollectionFactory;
        $this->outletRepository = $outletRepository;
        $this->xOrderFactory = $xOrderFactory;

        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    public function getOrders()
    {
        $searchCriteria = $this->getSearchCriteria();

        if ($searchCriteria->getData('getErrorOrder')
            && (int)$searchCriteria->getData('getErrorOrder') === 1
        ) {
            return $this->loadOrderError($searchCriteria);
        } else {
            return $this->loadOrders($searchCriteria);
        }
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \ReflectionException
     */
    public function loadOrders(DataObject $searchCriteria)
    {
        $collection = $this->getOrderCollection($searchCriteria);

        $orders = [];
        if ($collection->getLastPageNumber() < $searchCriteria->getData('currentPage')) {
        } else {
            $storeId = $searchCriteria->getData('storeId');

            /** @var \Magento\Sales\Model\Order $order */
            foreach ($collection as $order) {
                $order = $this->orderRepository->get($order->getId());
                if (!$searchCriteria->getData('isSearchOnline')
                    && $order->getShippingMethod() === 'smstorepickup_smstorepickup'
                    && !isset($this->getRequest()->getParams()['save-order'])
                ) {
                    if (!!$order->getData('pickup_outlet_id')
                        && $order->getData('pickup_outlet_id') != $order->getData('outlet_id')
                    ) {
                        if ($order->getData('pickup_outlet_id') != $searchCriteria->getData('outletId')) {
                            continue;
                        }
                    } elseif (!!$order->getData('outlet_id')
                        && $order->getData('outlet_id') != $searchCriteria->getData('outletId')) {
                        continue;
                    }
                }
                $customerPhone = "";
                $xOrder = $this->xOrderFactory->create();
                $xOrder = $xOrder->addData(($order->getData()));
                $xOrder->setData(
                    'created_at',
                    $this->retailHelper->convertTimeDBUsingTimeZone($order->getCreatedAt(), $storeId)
                );
                $xOrder->setData('status', $order->getStatusLabel());
                if ($order->getCustomerId()) {
                    $customer = $this->customerFactory->create()->load($order->getCustomerId());
                    if ($customer->getData('retail_telephone')) {
                        $customerPhone = $customer->getData('retail_telephone');
                    } else {
                        $customerPhone = "";
                    }
                }
                $xOrder->setData(
                    'customer',
                    [
                        'id'    => $order->getCustomerId(),
                        'name'  => $order->getCustomerName(),
                        'email' => $order->getCustomerEmail(),
                        'phone' => $customerPhone,
                    ]
                );
                $itemTaxes = [];
                $itemAppliedTaxes = $order->getExtensionAttributes()->getItemAppliedTaxes();
                if (!empty($itemAppliedTaxes)) {
                    foreach ($itemAppliedTaxes as $itemAppliedTax) {
                        $appliedTaxes = [];
                        foreach ($itemAppliedTax->getAppliedTaxes() as $appliedTax) {
                            $appliedTaxes[] = $appliedTax->getData();
                        }
                        $itemTax = $itemAppliedTax->getData();
                        $itemTax['applied_taxes'] = $appliedTaxes;
                        $itemTaxes[] = $itemTax;
                    }
                }

                $xOrder->setData('item_applied_taxes', $itemTaxes);
                $xOrder->setData('items', $this->getOrderItemData($order->getItemsCollection()->getItems()));

                if ($billingAdd = $order->getBillingAddress()) {
                    $customerBillingAdd = new CustomerAddress($billingAdd->getData());
                    $xOrder->setData('billing_address', $customerBillingAdd);
                }
                if ($shippingAdd = $order->getShippingAddress()) {
                    $customerShippingAdd = new CustomerAddress($shippingAdd->getData());
                    $xOrder->setData('shipping_address', $customerShippingAdd);
                }
                if ($order->getShippingMethod() === 'smstorepickup_smstorepickup'
                    && $order->getData('retail_status') === null
                ) {
                    if (!$order->hasCreditmemos()) {
                        if ($order->canInvoice()) {
                            $xOrder->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_PICKING);
                        } elseif ($order->canShip()) {
                            $xOrder->setData('retail_status', OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_PICKING);
                        }
                    } else {
                        if ($order->getState() == Order::STATE_CLOSED) {
                            $xOrder->setData('retail_status', OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                        } else {
                            if ($order->canShip()) {
                                $xOrder->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING);
                            }
                        }
                    }
                }
                if ($order->getPayment()->getMethod() == RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE
                    || $order->getShippingMethod() === 'smstorepickup_smstorepickup'
                ) {
                    $paymentData = json_decode($order->getPayment()->getAdditionalInformation('split_data'), true);
                    $paymentData = is_array($paymentData) ? $paymentData : [];
                    if (is_array($paymentData)) {
                        $paymentData = array_filter(
                            $paymentData,
                            function ($val) {
                                return is_array($val);
                            }
                        );
                        if ($order->getShippingMethod() === 'smstorepickup_smstorepickup'
                            && !$order->canInvoice()
                            && $order->getData('is_exchange') != 1
                            && empty($paymentData)
                        ) {
                            array_push(
                                $paymentData,
                                [
                                    'title'      => $order->getPayment()->getMethodInstance()->getTitle(),
                                    'amount'     => $order->getTotalPaid(),
                                    'created_at' => $order->getCreatedAt(),
                                    'type'       => $order->getPayment()->getMethodInstance()->getCode(),
                                ]
                            );
                        }
                        $xOrder->setData('payment', $paymentData);
                    }
                } else {
                    $xOrder->setData(
                        'payment',
                        [
                            [
                                'title'                  => $order->getPayment()->getMethodInstance()->getTitle(),
                                'amount'                 => $order->getTotalPaid(),
                                'created_at'             => $order->getCreatedAt(),
                                'type'                   => $order->getPayment()->getMethodInstance()->getCode(),
                                'additional_information' => $order->getPayment()->getAdditionalInformation(),
                            ],
                        ]
                    );
                }
                $xOrder->setData('outlet_id', $order->getData('outlet_id'));
                $xOrder->setData('can_creditmemo', $order->canCreditmemo());
                $xOrder->setData('can_ship', $order->canShip());
                $xOrder->setData('can_invoice', $order->canInvoice());
                $xOrder->setData('is_order_virtual', $order->getIsVirtual());
                $xOrder->setData('is_pwa', $order->getData('is_pwa'));

                $taxes = $this->taxCollectionFactory->create()->loadByOrder($order);
                $applied_taxes = [];
                foreach ($taxes->getItems() as $tax) {
                    $applied_taxes[] = $tax->getData();
                }
                $totals = [
                    'shipping_incl_tax'              => floatval($order->getShippingInclTax()),
                    'shipping'                       => floatval($order->getShippingAmount()),
                    'shipping_method'                => floatval($order->getShippingMethod()),
                    'shipping_method_name'           => $order->getShippingDescription(),
                    'shipping_discount'              => floatval($order->getShippingDiscountAmount()),
                    'shipping_tax_amount'            => floatval($order->getShippingTaxAmount()),
                    'subtotal'                       => floatval($order->getSubtotal()),
                    'subtotal_incl_tax'              => floatval($order->getSubtotalInclTax()),
                    'tax'                            => floatval($order->getTaxAmount()),
                    'applied_taxes'                  => $applied_taxes,
                    'discount'                       => floatval($order->getDiscountAmount()),
                    'retail_discount_per_item'      => floatval($order->getData('discount_per_item')),
                    'grand_total'                    => floatval($order->getGrandTotal()),
                    'total_paid'                     => floatval($order->getTotalPaid()),
                    'total_refunded'                 => floatval($order->getTotalRefunded()),
                    'reward_point_discount_amount'   => null,
                    'store_credit_discount_amount'   => null,
                    'gift_card_discount_amount'      => null,
                    'store_credit_balance'           => floatval($order->getData('store_credit_balance')),
                    'previous_reward_points_balance' => floatval($order->getData('previous_reward_points_balance')),
                    'reward_points_redeemed'         => floatval($order->getData('reward_points_redeemed')),
                    'reward_points_earned'           => floatval($order->getData('reward_points_earned')),
                    'reward_points_earned_amount'    => floatval($order->getData('reward_points_earned_amount')),
                    'reward_points_refunded'         => floatval($order->getData('reward_points_refunded')),
                ];

                if ($this->integrateHelperData->isIntegrateRP()
                    && $this->integrateHelperData->isAHWRewardPoints()
                ) {
                    $totals['reward_point_discount_amount'] = $order->getData('aw_reward_points_amount');
                }

                if ($this->integrateHelperData->isIntegrateRP()
                    && $this->integrateHelperData->isAmastyRewardPoints()) {
                    $totals['reward_point_discount_amount'] = $order->getData('reward_currency_amount');
                }

                if ($this->integrateHelperData->isIntegrateRP()
                    && $this->integrateHelperData->isRewardPointMagento2EE()) {
                    $totals['reward_point_discount_amount'] = -$order->getData('reward_currency_amount');
                }

                if ($this->integrateHelperData->isIntegrateStoreCredit()
                    && $this->integrateHelperData->isExistStoreCreditMagento2EE()
                ) {
                    $totals['store_credit_discount_amount'] = -$order->getData('customer_balance_amount');
                }

                if (($this->integrateHelperData->isIntegrateGC() || ($this->integrateHelperData->isIntegrateGCInPWA() && $order->getData('is_pwa') === '1'))
                    && $this->integrateHelperData->isAHWGiftCardExist()
                ) {
                    $orderGiftCards = [];
                    if ($order->getExtensionAttributes()) {
                        $orderGiftCards = $order->getExtensionAttributes()
                            ->getAwGiftcardCodes();
                    }
                    if (is_array($orderGiftCards) && count($orderGiftCards) > 0) {
                        $totals['gift_card'] = [];
                        foreach ($orderGiftCards as $giftcard) {
                            array_push(
                                $totals['gift_card'],
                                [
                                    'gift_code'            => $giftcard->getGiftcardCode(),
                                    'giftcard_amount'      => -floatval(abs($giftcard->getGiftcardAmount())),
                                    'base_giftcard_amount' => -floatval(abs($giftcard->getBaseGiftcardAmount())),
                                ]
                            );
                        }
                    }
                }
                if ($this->integrateHelperData->isIntegrateGC()
                    && $this->integrateHelperData->isGiftCardMagento2EE()
                ) {
                    $orderGiftCards = [];
                    if ($order->getData('gift_cards')) {
                        $orderGiftCards = $this->retailHelper->unserialize($order->getData('gift_cards'));
                    }
                    if (is_array($orderGiftCards) && count($orderGiftCards) > 0) {
                        $totals['gift_card'] = [];
                        foreach ($orderGiftCards as $giftCard) {
                            array_push(
                                $totals['gift_card'],
                                [
                                    'gift_code'            => $giftCard['c'],
                                    'giftcard_amount'      => -floatval(abs($giftCard['a'])),
                                    'base_giftcard_amount' => -floatval(abs($giftCard['ba'])),
                                ]
                            );
                        }
                    }
                }

                $invoiceCollection = $this->getInvoices($order);
                $xOrder->setData('invoice_collection', $invoiceCollection);
                $creditmemoHistory = $this->getCreditmemoHistory($order);
                $xOrder->setData('comment_history', $creditmemoHistory);

                $xOrder->setData('totals', $totals);

                if ($order->getData('origin_order_id')) {
                    $xOrder->setData('origin_order_retail_id', $this->getOrderRetailId($order->getData('origin_order_id')));
                }

                $orders[] = $xOrder;
            }
        }

        return $this->getSearchResult()
            ->setSearchCriteria($searchCriteria)
            ->setItems($orders)
            ->setTotalCount($collection->getTotalCount())
            ->setMessageError(OrderManagement::$MESSAGE_ERROR)
            ->setMessageText(OrderManagement::$MESSAGE_TEXT)
            ->setLastPageNumber($collection->getLastPageNumber())
            ->getOutput();
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     * @throws \Exception
     */
    protected function getOrderCollection(DataObject $searchCriteria)
    {
        /** @var  \Magento\Sales\Model\ResourceModel\Order\Collection $collection */
        $collection = $this->orderCollectionFactory->create();
        $storeId = $searchCriteria->getData('storeId');
        $locationId = $searchCriteria->getData('location_id');
        if (!$searchCriteria->getData('isSearchOnline')) {
            $outletId = $searchCriteria->getData('outletId');
            if (!!$outletId && !$searchCriteria->getData('searchString')) {
                if ($this->integrateHelperData->isIntegrateStorePickUpExtension()) {
                    $collection->addFieldToFilter(
                        ['outlet_id', 'shipping_method', 'pickup_outlet_id', 'is_pwa', 'mageworx_pickup_location_id'],
                        [
                            ['eq' => $outletId],
                            ['eq' => 'smstorepickup_smstorepickup'],
                            ['eq' => $outletId],
                            ['eq' => 1],
                            ['eq' => $locationId],
                        ]
                    );
                } else {
                    $collection->addFieldToFilter(
                        ['outlet_id', 'shipping_method', 'pickup_outlet_id', 'is_pwa'],
                        [
                            ['eq' => $outletId],
                            ['eq' => 'smstorepickup_smstorepickup'],
                            ['eq' => $outletId],
                            ['eq' => 1],
                        ]
                    );
                }
            }
            if (is_null($storeId)) {
                throw new Exception("Please define storeId when pull order");
            } else {
                if (!!$outletId) {
                    if ($this->integrateHelperData->isIntegrateStorePickUpExtension()) {
                        $collection->getSelect()->where(
                            sprintf('(store_id = %s AND is_pwa = 1) OR (outlet_id = %s AND is_pwa != 1) OR shipping_method = "smstorepickup_smstorepickup" OR mageworx_pickup_location_id = "%s"', $storeId, $outletId, $locationId)
                        );
                    } else {
                        $collection->getSelect()->where(sprintf('(store_id = %s AND is_pwa = 1) OR (outlet_id = %s AND is_pwa != 1) OR shipping_method = "smstorepickup_smstorepickup"', $storeId, $outletId));
                    }
                } else {
                    $collection->getSelect()->where(sprintf('(store_id = %s AND is_pwa = 1) OR shipping_method = "smstorepickup_smstorepickup"', $storeId));
                }
            }
        }
        if ($entityId = $searchCriteria->getData('entity_id')) {
            $arrEntityId = explode(",", $entityId);
            $collection->addFieldToFilter('entity_id', ["in" => $arrEntityId]);
        }

        $collection
            ->setOrder('created_at')
            ->setCurPage(is_nan($searchCriteria->getData('currentPage')) ? 1 : $searchCriteria->getData('currentPage'))
            ->setPageSize(
                is_nan($searchCriteria->getData('pageSize'))
                    ?
                    DataConfig::PAGE_SIZE_LOAD_DATA
                    :
                    $searchCriteria->getData('pageSize')
            );
        if ($dateFrom = $searchCriteria->getData('dateFrom')) {
            $collection->getSelect()
                ->where('created_at >= ?', $dateFrom);
        }
        if ($dateTo = $searchCriteria->getData('dateTo')) {
            $collection->getSelect()
                ->where('created_at <= ?', $dateTo . ' 23:59:59');
        }
        if ($searchString = $searchCriteria->getData('searchString')) {
            $fieldSearch = ['retail_id', 'customer_email', 'increment_id'];
            $fieldSearchValue = [
                ['like' => '%' . $searchString . '%'],
                ['like' => '%' . $searchString . '%'],
                ['like' => '%' . $searchString . '%'],
            ];
            $arrString = explode(' ', $searchString);
            foreach ($arrString as $customerNameSearchValue) {
                $fieldSearch[] = 'customer_firstname';
                $fieldSearchValue[] = ['like' => '%' . $customerNameSearchValue . '%'];
                $fieldSearch[] = 'customer_lastname';
                $fieldSearchValue[] = ['like' => '%' . $customerNameSearchValue . '%'];
            }
            $collection->addFieldToFilter($fieldSearch, $fieldSearchValue);
        }

        return $collection;
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return array
     * @throws \ReflectionException
     */
    public function loadOrderError(DataObject $searchCriteria)
    {
        $collection = $this->getOrderErrorCollection($searchCriteria);
        $orders = [];
        if (1 < $searchCriteria->getData('currentPage')) {
        } else {
            foreach ($collection as $order) {
                $orderData = json_decode($order['order_offline'], true);
                if (is_array($orderData)) {
                    if (isset($orderData['id'])) {
                        unset($orderData['id']);
                    }
                    $orders[] = $orderData;
                }
            }
        }

        return $this->getSearchResult()
            ->setSearchCriteria($searchCriteria)
            ->setItems($orders)
            ->getOutput();
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getOrderErrorCollection(DataObject $searchCriteria)
    {
        $collection = $this->orderErrorCollectionFactory->create();
        $storeId = $searchCriteria->getData('storeId');
        if (is_null($storeId)) {
            throw new Exception("Please define storeId when pull order");
        }

        $collection->addFieldToFilter('store_id', $storeId);

        if ($dateFrom = $searchCriteria->getData('dateFrom')) {
            $collection->getSelect()
                ->where('created_at >= ?', $dateFrom);
        }
        if ($dateTo = $searchCriteria->getData('dateTo')) {
            $collection->getSelect()
                ->where('created_at <= ?', $dateTo . ' 23:59:59');
        }

        return $collection;
    }

    /**
     * @param      $items
     *
     * @param null $storeId
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getOrderItemData($items, $storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->getRequest()->getParam('store_id');
        }

        if (!$storeId && $this->getRequest()->getParam('searchCriteria')) {
            $storeId = $this->getSearchCriteria()->getData('storeId');
        }

        $itemData = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $itemData[] = $this->getIndividualOrderItemData($item, $storeId);
        }

        return $itemData;
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $item
     * @param $storeId
     *
     * @throws \ReflectionException
     * @return array
     */
    public function getIndividualOrderItemData($item, $storeId)
    {
        $xOrderItem = $this->xOrderItemFactory->create();
        $xOrderItem->addData($item->getData());
        $xOrderItem->setData('isChildrenCalculated', $item->isChildrenCalculated());

        if ($item instanceof Item) {
            $stockItemToCheck = [];
            $childItems = $item->getChildren();

            if (count($childItems)) {
                foreach ($childItems as $childItem) {
                    $stockItemToCheck[] = $childItem->getProduct()->getId();
                }
            } else {
                $stockItemToCheck[] = $item->getProduct()->getId();
            }

            $xOrderItem->setData('stockItemToCheck', $stockItemToCheck);
        }

        if (!$item->getProduct()
            || is_null($item->getProduct()->getImage())
            || $item->getProduct()->getImage() == 'no_selection'
            || !$item->getProduct()->getImage()
        ) {
            $xOrderItem->setData('origin_image', null);
        } else {
            $xOrderItem->setData(
                'origin_image',
                $this->productMediaConfig->getMediaUrl($item->getProduct()->getImage())
            );
            $xOrderItem->setData(
                'origin_image',
                $this->productMediaConfig->getMediaUrl($item->getProduct()->getImage())
            );
        }

        $children = [];

        if ($item->getChildrenItems() && $item->getProductType() == 'bundle') {
            /** @var \Magento\Sales\Model\Order\Item $childrenItem */
            foreach ($item->getChildrenItems() as $childrenItem) {
                $child = $this->xOrderItemFactory->create();
                $child->addData($childrenItem->getData());

                if (!$childrenItem->getProduct()
                    || is_null($childrenItem->getProduct()->getImage())
                    || $childrenItem->getProduct()->getImage() == 'no_selection'
                    || !$childrenItem->getProduct()->getImage()
                ) {
                    $child->setData('origin_image', null);
                } else {
                    $child->setData(
                        'origin_image',
                        $this->productMediaConfig->getMediaUrl($childrenItem->getProduct()->getImage())
                    );
                }

                $children[] = $child->getOutput();
            }
        }

        $xOrderItem->setData('children', $children);

        if ($storeId !== null) {
            $searchCriteriaReq = $this->getRequest()->getParam('searchCriteria');
            $warehouseId = null;
            if ($searchCriteriaReq && isset($searchCriteriaReq['warehouse_id'])) {
                $warehouseId = $searchCriteriaReq['warehouse_id'];
            }
            if ($warehouseId == null && $this->getRequest()->getParam('outlet_id')) {
                $outlet = $this->outletRepository->getById($this->getRequest()->getParam('outlet_id'));
                $warehouseId = $outlet->getData('warehouse_id');
            }

            if ($warehouseId) {
                $searchCriteria = new \Magento\Framework\DataObject(
                    [
                        'storeId'   => $storeId,
                        'entity_id' => $item->getProductId(),
                        'warehouse_id' => $warehouseId
                    ]
                );

                try {
                    $p = $this->productRepository->getById($item->getProductId());
                    $p = $this->productManagement->processXProduct($p, $storeId, $warehouseId);
                    $xOrderItem->setData('product', $p);
                } catch (\Exception $e) {
                }
            }
        }

        $buyRequest = is_array($item->getBuyRequest()) ? $item->getBuyRequest() : $item->getBuyRequest()->getData();

        if (!isset($buyRequest['item_note']) && isset($item->getProductOptions()['additional_options'])) {
            $additionalOptions = $item->getProductOptions()['additional_options'];
            foreach ($additionalOptions as $additionalOption) {
                if ($additionalOption['label'] === 'Comment') {
                    $buyRequest['item_note'] = $additionalOption['value'];
                }
            }
        }

        if ($item->getData('cpos_discount_per_item_percent')) {
            $buyRequest['retail_discount_per_items_percent'] = $item->getData('cpos_discount_per_item_percent');
        }

        if ($item->getData('cpos_discount_per_item')) {
            $buyRequest['discount_per_item'] = $item->getData('cpos_discount_per_item');
        }

        $xOrderItem->setData('buy_request', $buyRequest);
        $xOrderItem->setData('serial_number', $item->getData('serial_number'));

        return $xOrderItem->getOutput();
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array
     */
    public function getInvoices($order)
    {
        $invoices = [];
        $invoiceCollection = $order->getInvoiceCollection();
        foreach ($invoiceCollection as $invoice) {
            $invoices[] = ['id' => $invoice->getId(), 'increment_id' => $invoice->getIncrementId()];
        }

        return $invoices;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array
     */
    public function getCreditmemoHistory($order)
    {
        $creditMemoHistory = [];
        $creditmemosCollection = $order->getCreditmemosCollection();
        if (is_array($creditmemosCollection->getData()) && count($creditmemosCollection) > 0) {
            foreach ($creditmemosCollection as $creditmemo) {
                $creditMemoHistory[] = [
                    'order_increment_id'      => $order->getIncrementId(),
                    'creditmemo_increment_id' => $creditmemo->getIncrementId(),
                    'creditmemo_id'           => $creditmemo->getId(),
                    'comment_history'         => $this->getCommentHistory($creditmemo)];
            }
        }

        return $creditMemoHistory;
    }

    /**
     * @param null $creditmemo
     *
     * @return array
     */
    public function getCommentHistory($creditmemo = null)
    {
        $commentHistory = [];
        if ($creditmemo === null) {
            return $commentHistory;
        }
        foreach ($creditmemo->getCommentsCollection() as $comment) {
            $commentHistory[] = ['comment' => $comment->getComment(), 'created_at' => $comment->getCreatedAt()];
        }

        return $commentHistory;
    }

    protected function getOrderRetailId($orderId)
    {
        $order = $this->orderRepository->get($orderId);

        return $order->getData('retail_id');
    }
}

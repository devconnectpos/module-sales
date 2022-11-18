<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 01/01/2017
 * Time: 18:06
 */

namespace SM\Sales\Repositories;

use Exception;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Block\Adminhtml\Items\AbstractItems;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Sales\Model\Order\InvoiceFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Config;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Payment\Helper\PaymentHelper;
use SM\Payment\Model\RetailPayment;
use SM\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use SM\XRetail\Helper\Data;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

/**
 * Class CreditmemoManagement
 *
 * @package SM\Sales\Repositories
 */
class CreditmemoManagement extends ServiceAbstract
{
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManagement;
    /**
     * @var \Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader
     */
    protected $creditmemoLoader;
    /**
     * @var \Magento\Sales\Block\Adminhtml\Items\AbstractItems
     */
    protected $blockItem;
    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $session;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender
     */
    protected $creditmemoSender;
    /**
     * @var \SM\Sales\Repositories\InvoiceManagement
     */
    protected $invoiceManagement;
    /**
     * @var \Magento\Tax\Model\Config
     */
    protected $taxConfig;

    /**
     * @var
     */
    private $currentRate;
    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \SM\XRetail\Helper\Data
     */
    private $retailHelper;

    /**
     * @var \SM\Payment\Helper\PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var \SM\Integrate\Helper\Data
     */
    protected $integrateHelperData;
    /**
     * @var \Magento\Sales\Model\Order\InvoiceFactory
     */
    private $invoiceFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Sales\Api\Data\CreditmemoExtensionInterfaceFactory
     */
    private $creditmemoExtensionInterfaceFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */
    protected $orderResource;

    /**
     * @var State
     */
    protected $state;

    /**
     * CreditmemoManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                  $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                            $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface               $storeManager
     * @param \SM\Sales\Controller\Adminhtml\Order\CreditmemoLoader    $creditmemoLoader
     * @param \Magento\Sales\Block\Adminhtml\Items\AbstractItems       $blockItem
     * @param \Magento\Backend\Model\Session                           $session
     * @param \Magento\Framework\ObjectManagerInterface                $objectManager
     * @param \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender $creditmemoSender
     * @param \SM\Sales\Repositories\InvoiceManagement                 $invoiceManagement
     * @param \Magento\Tax\Model\Config                                $taxConfig
     * @param \SM\Payment\Helper\PaymentHelper                         $paymentHelper
     * @param \SM\XRetail\Helper\Data                                  $retailHelper
     * @param \SM\Integrate\Helper\Data                                $integrateHelperData
     * @param \SM\Sales\Repositories\OrderHistoryManagement            $orderHistoryManagement
     * @param \Magento\Framework\Event\ManagerInterface                $eventManagement
     * @param \Magento\Sales\Model\Order\InvoiceFactory                $invoiceFactory
     * @param \Magento\Customer\Model\CustomerFactory                  $customerFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface              $orderRepository
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        CreditmemoLoader $creditmemoLoader,
        AbstractItems $blockItem,
        Session $session,
        ObjectManagerInterface $objectManager,
        CreditmemoSender $creditmemoSender,
        InvoiceManagement $invoiceManagement,
        Config $taxConfig,
        PaymentHelper $paymentHelper,
        Data $retailHelper,
        IntegrateHelper $integrateHelperData,
        ManagerInterface $eventManagement,
        InvoiceFactory $invoiceFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\Data\CreditmemoExtensionInterfaceFactory $creditmemoExtensionInterfaceFactory,
        ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\ResourceModel\Order $orderResource,
        State $state
    ) {
        $this->taxConfig = $taxConfig;
        $this->invoiceManagement = $invoiceManagement;
        $this->session = $session;
        $this->creditmemoLoader = $creditmemoLoader;
        $this->blockItem = $blockItem;
        $this->objectManager = $objectManager;
        $this->creditmemoSender = $creditmemoSender;
        $this->paymentHelper = $paymentHelper;
        $this->retailHelper = $retailHelper;
        $this->integrateHelperData = $integrateHelperData;
        $this->eventManagement = $eventManagement;
        $this->invoiceFactory = $invoiceFactory;
        $this->customerFactory = $customerFactory;
        $this->orderRepository = $orderRepository;
        $this->creditmemoExtensionInterfaceFactory = $creditmemoExtensionInterfaceFactory;
        $this->scopeConfig = $scopeConfig;
        $this->orderResource = $orderResource;
        $this->state = $state;

        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function creditmemo()
    {
        if ($this->getRequest()->getParam('is_save') == false) {
            return $this->load();
        } elseif ($this->getRequest()->getParam('is_save') == true) {
            return $this->save();
        }
        throw new Exception("Please define action");
    }

    /**
     * @return array
     */
    protected function load()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $data = $this->getCreditmemoData();
        $this->creditmemoLoader->setCreditmemo($data);
        $this->creditmemoLoader->setOrderId($orderId);
        $creditmemo = $this->creditmemoLoader->load();

        return $this->getOutputCreditmemo($creditmemo);
    }

    /**
     * @return mixed
     */
    protected function getCreditmemoData()
    {
        $data = $this->getRequest()->getParam('creditmemo');

        if (isset($data['shipping_amount']) && !is_nan((float)$data['shipping_amount'])) {
            $data['shipping_amount'] = $data['shipping_amount'] / $this->getCurrentRate();
        }

        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function save()
    {
        try {
            $orderId = $this->getRequest()->getParam('order_id');
            $storeId = $this->getRequest()->getParam('store_id');
            $data = $this->getCreditmemoData();
            $this->creditmemoLoader->setOrderId($orderId);
            $this->creditmemoLoader->setCreditmemo($data);
            $creditmemo = $this->creditmemoLoader->load();

            $creditmemo->setData('cpos_creditmemo_from_store_id', $storeId);

            // Support create refund for online order
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderRepository->get($creditmemo->getOrderId());

            if ($order->getInvoiceCollection()->getSize() == 1) {
                $creditmemo->setInvoice(
                    $this->invoiceFactory->create()->load(
                        $order->getInvoiceCollection()
                            ->getFirstItem()
                            ->getData('entity_id')
                    )
                );
            }

            if ($creditmemo) {
                if (!$creditmemo->isValidGrandTotal()) {
                    throw new LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }
                if (!empty($data['comment_text'])) {
                    $creditmemo->addComment(
                        $data['comment_text'],
                        isset($data['comment_customer_notify']),
                        isset($data['is_visible_on_front'])
                    );

                    $creditmemo->setCustomerNote($data['comment_text']);
                    $creditmemo->setCustomerNoteNotify(isset($data['comment_customer_notify']));
                }

                if (isset($data['do_offline'])) {
                    //do not allow online refund for Refund to Store Credit
                    if (!$data['do_offline'] && !empty($data['refund_customerbalance_return_enable'])) {
                        throw new LocalizedException(
                            __('Cannot create online refund for Refund to Store Credit.')
                        );
                    }
                }

                // check payment data
                if (isset($data['payment_data']) && is_array($data['payment_data']) && count($data['payment_data']) > 1) {
                    $hasStoreCreditRefund = false;
                    foreach ($data['payment_data'] as $paymentDatum) {
                        if (isset($paymentDatum['type']) && $paymentDatum['type'] == \SM\Payment\Model\RetailPayment::REFUND_TO_STORE_CREDIT_PAYMENT_TYPE) {
                            $hasStoreCreditRefund = true;
                            break;
                        }
                    }

                    // Only allow more than 1 payment methods when there exists store credit refund
                    if (!$hasStoreCreditRefund) {
                        // when exchange can have two payment method.
                        throw new Exception("Refund only accept one payment method");
                    }
                }

                $creditmemoManagement = $this->objectManager->create(
                    'Magento\Sales\Api\CreditmemoManagementInterface'
                );
                $creditmemo->setAutomaticallyCreated(true);

                $creditmemoManagement->refund($creditmemo, (bool)$data['do_offline']);

                if (!empty($data['send_email'])) {
                    $this->creditmemoSender->send($creditmemo);
                }

                $this->eventManagement->dispatch(
                    'disable_giftcard_refund',
                    [
                        'order' => $creditmemo,
                    ]
                );

                // for case refund using only giftcard
                if (isset($data['payment_data'])
                    && $data['payment_data'] == null
                    && isset($data['refund_to_gift_card'])
                    && $data['refund_to_gift_card'] == true
                    && $this->integrateHelperData->isAHWGiftCardExist()
                    && ($this->integrateHelperData->isIntegrateGC()
                        || ($order->getData('is_pwa') == 1
                            && $this->integrateHelperData->isIntegrateGCInPWA()))
                ) {
                    $createdAt = $this->retailHelper->getCurrentTime();
                    $giftCardPaymentId = $this->paymentHelper->getPaymentIdByType(
                        RetailPayment::GIFT_CARD_PAYMENT_TYPE,
                        $order->getData('register_id')
                    );
                    $data['payment_data'][0] = [
                        "id"                    => $giftCardPaymentId,
                        "type"                  => RetailPayment::GIFT_CARD_PAYMENT_TYPE,
                        "title"                 => "Gift Card",
                        "refund_amount"         => $creditmemo->getGrandTotal(),
                        "data"                  => [],
                        "isChanging"            => false,
                        "allow_amount_tendered" => true,
                        "is_purchase"           => 0,
                        "created_at"            => $createdAt,
                        "payment_data"          => [],
                    ];
                }

                if ($this->integrateHelperData->isIntegrateStoreCredit()
                    && ($this->integrateHelperData->isExistStoreCreditMagento2EE() || $this->integrateHelperData->isExistStoreCreditAheadworks())
                    && (isset($data['refund_to_store_credit']) && $data['refund_to_store_credit'] == 1)
                ) {
                    $storeCreditData = $this->integrateHelperData
                        ->getStoreCreditIntegrateManagement()
                        ->getCurrentIntegrateModel()
                        ->getStoreCreditCollection(
                            $this->getCustomerModel()->load($creditmemo->getOrder()->getCustomerId()),
                            $this->storeManager->getStore($storeId)->getWebsiteId()
                        );
                    $order->setData('store_credit_balance', $storeCreditData);
                    $this->orderResource->saveAttribute($order, 'store_credit_balance');
                }

                return $this->invoiceManagement->addPayment(
                    [
                        'payment_data' => $data['payment_data'],
                        'order_id'     => $this->getRequest()->getParam('order_id'),
                        "outlet_id"    => $this->getRequest()->getParam('outlet_id'),
                        "register_id"  => $this->getRequest()->getParam('register_id'),
                        "user_name"    => $this->getRequest()->getParam('user_name'),
                        'store_id'     => $storeId,
                    ],
                    true
                );
            } else {
                throw new Exception("Can't find creditmemo data");
            }
        } catch (\Throwable $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('Psr\Log\LoggerInterface');
            $logger->critical('===> Unable to save credit memo');
            $logger->critical($e->getMessage()."\n".$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     *
     * @return array
     */
    public function getOutputCreditmemo(Creditmemo $creditmemo)
    {
        $data = [
            'order_id'  => $creditmemo->getOrderId(),
            'retail_id' => $creditmemo->getOrder()->getData('retail_id'),
        ];
        $data['customer'] = [
            'customer_id' => $creditmemo->getOrder()->getCustomerId(),
        ];

        $data['items'] = [];
        $this->blockItem->setOrder($creditmemo->getOrder());

        foreach ($creditmemo->getAllItems() as $item) {
            /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
            $_item = [];
            $_item['item_id'] = $item->getOrderItemId();
            $_item['product_id'] = $item->getProductId();
            $_item['name'] = $item->getName();
            $_item['sku'] = $item->getSku();
            $_item['parent_id'] = $item->getOrderItem()->getParentItemId();
            $_item['type_id'] = $item->getOrderItem()->getProductType();
            if ($item->getOrderItem()->isChildrenCalculated()) {
                $_item['children_calculated'] = $item->getOrderItem()->isChildrenCalculated();
            }
            $_item['price'] = $item->getPrice();
            $_item['price_incl_tax'] = $item->getPriceInclTax();
            $_item['qty_ordered'] = $item->getOrderItem()->getQtyOrdered() * 1;
            $_item['qty_invoiced'] = $item->getOrderItem()->getQtyInvoiced() * 1;
            $_item['qty_shipped'] = $item->getOrderItem()->getQtyShipped() * 1;
            $_item['qty_refunded'] = $item->getOrderItem()->getQtyRefunded() * 1;
            $_item['qty_canceled'] = $item->getOrderItem()->getQtyCanceled() * 1;
            $_item['can_back_to_stock'] = $this->blockItem->canParentReturnToStock($item)
                && $this->blockItem->canReturnItemToStock($item);
            $backToStock = $this->retailHelper->getStoreConfig('xretail/pos/auto_return_to_stock') && $_item['can_back_to_stock'];
            $_item['back_to_stock'] = $backToStock;
            $_item['can_edit_qty'] = $this->blockItem->canEditQty();
            $_item['qty_to_refund'] = $item->getOrderItem()->getQtyToRefund();
            $_item['qty'] = $item->getQty();
            $_item['row_total'] = $item->getRowTotal();
            $_item['row_total_incl_tax'] = $item->getRowTotalInclTax();
            $_item['tax_amount'] = $item->getTaxAmount();
            $_item['discount_amount'] = $item->getDiscountAmount();
            $_item['is_qty_decimal'] = $item->getOrderItem()->getIsQtyDecimal();
            $productOption = $item->getOrderItem()->getProductOptions();
            if (isset($productOption['info_buyRequest']['custom_sale'])) {
                $_item['custom_sale_name'] = $productOption['info_buyRequest']['custom_sale']['name'];
            }
            $data['items'][] = $_item;
        }
        $rewardPointsRefunded = floatval($creditmemo->getOrder()->getData('reward_points_refunded'));
        $totals = [
            'reward_point_discount_amount'   => null,
            'store_credit_discount_amount'   => null,
            'gift_card_discount_amount'      => null,
            'store_credit_balance'           => floatval($creditmemo->getOrder()->getData('store_credit_balance')),
            'store_credit_refunded'          => floatval($creditmemo->getOrder()->getData('customer_balance_refunded')),
            'previous_reward_points_balance' => floatval($creditmemo->getOrder()->getData('previous_reward_points_balance')),
            'reward_points_redeemed'         => floatval($creditmemo->getOrder()->getData('reward_points_redeemed')),
            'reward_points_earned'           => floatval($creditmemo->getOrder()->getData('reward_points_earned')),
            'reward_points_earned_amount'    => floatval($creditmemo->getOrder()->getData('reward_points_earned_amount')),
            'reward_points_refunded'         => $rewardPointsRefunded,
        ];
        $totals['subtotal'] = $creditmemo->getSubtotal();
        $totals['subtotal_incl_tax'] = $creditmemo->getSubtotalInclTax();
        $totals['shipping'] = $creditmemo->getShippingAmount();
        $totals['shipping_incl_tax'] = $creditmemo->getShippingInclTax();
        $totals['discount_amount'] = $creditmemo->getDiscountAmount();
        $totals['tax_amount'] = $creditmemo->getTaxAmount();
        $totals['grand_total'] = $creditmemo->getGrandTotal();
        $totals['has_shipment'] = $creditmemo->getOrder()->getShippingAmount() ? true : false;

        if ($this->integrateHelperData->isIntegrateRP()
            && $this->integrateHelperData->isAHWRewardPoints()
        ) {
            $totals['reward_point_discount_amount'] = $creditmemo->getOrder()->getData('aw_reward_points_amount');
            $rewardPointsRefundedAmount = floatval($creditmemo->getOrder()->getData('aw_reward_points_refunded'));
            $rewardPointsRefunded = intval($creditmemo->getOrder()->getData('aw_reward_points_blnce_refunded'));
            $totals['reward_points_refunded'] = abs($rewardPointsRefunded);
            $totals['reward_points_refunded_amount'] = abs($rewardPointsRefundedAmount);
            $totals['estimated_reward_points_refund'] = $creditmemo->getAwRewardPoints();
            $totals['estimated_reward_points_refund_amount'] = $creditmemo->getAwRewardPointsAmount();
        }

        if ($this->integrateHelperData->isIntegrateRP()
            && $this->integrateHelperData->isAmastyRewardPoints()
        ) {
            $totals['reward_point_discount_amount'] = $creditmemo->getOrder()->getData('reward_currency_amount');
        }

        if ($this->integrateHelperData->isIntegrateRP()
            && $this->integrateHelperData->isRewardPointMagento2EE()
        ) {
            $totals['reward_point_discount_amount'] = -$creditmemo->getOrder()->getData('reward_currency_amount');
            $rewardPointsRefunded = floatval($creditmemo->getOrder()->getData('reward_points_refunded'));
            $rewardPointsRefundedAmount = floatval($creditmemo->getOrder()->getData('reward_points_refunded_amount'));
            $totals['reward_points_refunded'] = abs($rewardPointsRefunded);
            $totals['reward_points_refunded_amount'] = abs($rewardPointsRefundedAmount);
            $totals['estimated_reward_points_refund'] = $creditmemo->getRewardPointsBalance();
            $totals['estimated_reward_points_refund_amount'] = $creditmemo->getRewardCurrencyAmount();
        }

        if ($this->integrateHelperData->isIntegrateRP()
            && $this->integrateHelperData->isMirasvitRewardPoints()
        ) {
            // TODO: add logic here
        }

        if ($this->integrateHelperData->isIntegrateStoreCredit()
            && ($this->integrateHelperData->isExistStoreCreditMagento2EE() || $this->integrateHelperData->isExistStoreCreditAheadworks())
        ) {
            $totals['store_credit_discount_amount'] = -$creditmemo->getOrder()->getData('customer_balance_amount');
        }

        if (($this->integrateHelperData->isIntegrateGC() || ($this->integrateHelperData->isIntegrateGCInPWA() && $creditmemo->getOrder()->getData('is_pwa') === '1'))
            && $this->integrateHelperData->isAHWGiftCardExist()
        ) {
            $orderGiftCards = [];
            if ($creditmemo->getOrder()->getExtensionAttributes()) {
                $orderGiftCards = $creditmemo->getOrder()->getExtensionAttributes()
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
            if ($creditmemo->getOrder()->getData('gift_cards')) {
                $orderGiftCards = $this->retailHelper->unserialize($creditmemo->getOrder()->getData('gift_cards'));
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

        $data['base_customer_balance_amount'] = $creditmemo->getData('base_customer_balance_amount');
        $data['customer_balance_amount'] = $creditmemo->getData('customer_balance_amount');
        $data['totals'] = $totals;
        $data['adjustment'] = $creditmemo->getAdjustmentPositive() - $creditmemo->getAdjustmentNegative();
        $data['is_display_shipping_incl_tax'] = $this->taxConfig->displaySalesShippingInclTax(
            $creditmemo->getOrder()->getStoreId()
        );

        if ($data['is_display_shipping_incl_tax']) {
            $shipping = $creditmemo->getShippingInclTax();
        } else {
            $shipping = $creditmemo->getShippingAmount();
        }
        $data['shipping_method'] = $creditmemo->getOrder()->getData('shipping_method');
        $data['shipping_amount'] = $shipping;
        $data['retail_has_shipment'] = $creditmemo->getOrder()->getData('retail_has_shipment');
        $data['total_paid'] = $creditmemo->getOrder()->getData('total_paid');
        $data['total_refunded'] = $creditmemo->getOrder()->getData('total_refunded');
        $data['xRefNum'] = $creditmemo->getOrder()->getData('xRefNum');
        $data['transId'] = $creditmemo->getOrder()->getData('transId');
        $data['is_pwa'] = $creditmemo->getOrder()->getData('is_pwa');

        $order = $this->orderRepository->get($creditmemo->getOrderId());
        $itemAppliedTaxes = [];

        if ($order->getExtensionAttributes()) {
            $itemAppliedTaxes = $order->getExtensionAttributes()->getItemAppliedTaxes();
        }

        $itemTaxes = [];

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

        $data['item_applied_taxes'] = $itemTaxes;

        return $data;
    }

    /**
     * @return mixed
     */
    private function getCurrentRate()
    {
        if ($this->currentRate === null) {
            $orderId = $this->getRequest()->getParam('order_id');
            $order = $this->orderRepository->get($orderId);
            $this->currentRate = $order->getStore()
                ->getBaseCurrency()
                ->convert(1, $order->getOrderCurrencyCode());
        }

        return $this->currentRate;
    }

    /**
     * @param $payments
     *
     * @return float|int
     */
    public function getStoreCreditByPayments($payments)
    {
        $storeCredit = 0;
        foreach ($payments as $payment) {
            if ('refund_to_store_credit' === $payment['type']) {
                $storeCredit += $payment['refund_amount'] / $this->getCurrentRate();
            }
        }

        return $storeCredit;
    }

    /**
     * @param $storeCredit
     * @param $payments
     *
     * @return float|int
     */
    public function getStoreCreditData($storeCredit, $payments)
    {
        if (isset($storeCredit['customer_balance_base_currency'])) {
            $storeCreditData = $storeCredit['customer_balance_base_currency'] + $this->getStoreCreditByPayments($payments);
        } else {
            $storeCreditData = $this->getStoreCreditByPayments($payments);
        }

        return $storeCreditData;
    }

    /**
     * @return \Magento\Customer\Model\Customer
     */
    protected function getCustomerModel()
    {
        return $this->customerFactory->create();
    }

}

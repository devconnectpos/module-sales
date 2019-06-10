<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 10/01/2017
 * Time: 14:06
 */

namespace SM\Sales\Repositories;

use Exception;
use Magento\Config\Model\Config\Loader;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use SM\Integrate\Helper\Data as IntegrateHelper;
use SM\Payment\Model\RetailMultiple;
use SM\Shift\Helper\Data as ShiftHelper;
use SM\Shift\Model\RetailTransactionFactory;
use SM\XRetail\Helper\Data;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

/**
 * Class InvoiceManagement
 *
 * @package SM\Sales\Repositories
 */
class InvoiceManagement extends ServiceAbstract
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\ShipmentSender
     */
    protected $shipmentSender;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;
    /**
     * @var \SM\Sales\Repositories\OrderHistoryManagement
     */
    protected $orderHistoryManagement;

    /**
     * @var \SM\Shift\Model\RetailTransactionFactory
     */
    protected $retailTransactionFactory;
    /**
     * @var \SM\Shift\Helper\Data
     */
    private $shiftHelper;

    /**
     * @var \SM\XRetail\Helper\Data
     */
    protected $retailHelper;
    /** @var \SM\Integrate\Helper\Data $integrateHelper */
    protected $integrateHelper;
    /** @var \Magento\Config\Model\Config\Loader $configLoader */
    protected $configLoader;
    /** @var \Magento\Customer\Model\CustomerFactory $customerFactory */
    protected $customerFactory;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;


    /**
     * InvoiceManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                          $dataConfig
     * @param \SM\XRetail\Helper\Data                                $retailHelper
     * @param \Magento\Store\Model\StoreManagerInterface             $storeManager
     * @param \Magento\Framework\ObjectManagerInterface              $objectManager
     * @param \Magento\Sales\Model\Service\InvoiceService            $invoiceService
     * @param \Magento\Framework\Registry                            $registry
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender  $invoiceSender
     * @param \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $shipmentSender
     * @param \Magento\Sales\Model\OrderFactory                      $orderFactory
     * @param \SM\Sales\Repositories\OrderHistoryManagement          $orderHistoryManagement
     * @param \SM\Shift\Model\RetailTransactionFactory               $retailTransactionFactory
     * @param \SM\Shift\Helper\Data                                  $shiftHelper
     * @param \SM\Integrate\Helper\Data                              $integrateHelper
     * @param \Magento\Config\Model\Config\Loader                    $configLoader
     * @param \Magento\Customer\Model\CustomerFactory                $customerFactory
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        Data $retailHelper,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        InvoiceService $invoiceService,
        Registry $registry,
        InvoiceSender $invoiceSender,
        ShipmentSender $shipmentSender,
        OrderFactory $orderFactory,
        OrderHistoryManagement $orderHistoryManagement,
        RetailTransactionFactory $retailTransactionFactory,
        ShiftHelper $shiftHelper,
        IntegrateHelper $integrateHelper,
        Loader $configLoader,
        CustomerFactory $customerFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->orderHistoryManagement   = $orderHistoryManagement;
        $this->orderFactory             = $orderFactory;
        $this->shipmentSender           = $shipmentSender;
        $this->invoiceSender            = $invoiceSender;
        $this->registry                 = $registry;
        $this->invoiceService           = $invoiceService;
        $this->objectManager            = $objectManager;
        $this->retailTransactionFactory = $retailTransactionFactory;
        $this->shiftHelper              = $shiftHelper;
        $this->retailHelper             = $retailHelper;
        $this->integrateHelper          = $integrateHelper;
        $this->configLoader             = $configLoader;
        $this->customerFactory          = $customerFactory;
        $this->scopeConfig              = $scopeConfig;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @param $orderId
     *
     * @return \Magento\Sales\Model\Order
     * @throws \Exception
     */
    public function invoice($orderId)
    {
        try {
            $invoiceData  = $this->getRequest()->getParam('invoice', []);
            $invoiceItems = isset($invoiceData['items']) ? $invoiceData['items'] : [];
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->objectManager->create('Magento\Sales\Model\Order')->load($orderId);
            if (!$order->getId()) {
                throw new LocalizedException(__('The order no longer exists.'));
            }

            if (!$order->canInvoice()) {
                throw new LocalizedException(
                    __('The order does not allow an invoice to be created.')
                );
            }

            $invoice = $this->invoiceService->prepareInvoice($order, $invoiceItems);

            if (!$invoice) {
                throw new LocalizedException(__('We can\'t save the invoice right now.'));
            }

            if (!$invoice->getTotalQty()) {
                throw new LocalizedException(
                    __('You can\'t create an invoice without products.')
                );
            }
            $this->registry->unregister('current_invoice');
            $this->registry->register('current_invoice', $invoice);
            if (!empty($data['capture_case'])) {
                $invoice->setRequestedCaptureCase($data['capture_case']);
            }

            if (!empty($data['comment_text'])) {
                $invoice->addComment(
                    $data['comment_text'],
                    isset($data['comment_customer_notify']),
                    isset($data['is_visible_on_front'])
                );

                $invoice->setCustomerNote($data['comment_text']);
                $invoice->setCustomerNoteNotify(isset($data['comment_customer_notify']));
            }

            $invoice->register();

            $invoice->getOrder()->setCustomerNoteNotify(!empty($data['send_email']));
            $invoice->getOrder()->setIsInProcess(true);

            $order           = $invoice->getOrder();
            $transactionSave = $this->objectManager->create(
                'Magento\Framework\DB\Transaction'
            )->addObject(
                $invoice
            )->addObject(
                $order
            );
            $shipment        = false;
            if (!empty($data['do_shipment']) || (int)$invoice->getOrder()->getForcedShipmentWithInvoice()) {
                $shipment = $this->_prepareShipment($invoice);
                if ($shipment) {
                    $transactionSave->addObject($shipment);
                }
            }
            $transactionSave->save();

            try {
                if (!empty($data['send_email'])) {
                    $this->invoiceSender->send($invoice);
                }
            } catch (Exception $e) {
                $this->objectManager->get('Psr\Log\LoggerInterface')->critical($e);
            }
            if ($shipment) {
                try {
                    if (!empty($data['send_email'])) {
                        $this->shipmentSender->send($shipment);
                    }
                } catch (Exception $e) {
                    $this->objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }
            }
            $this->objectManager->get('Magento\Backend\Model\Session')->getCommentText(true);

        } catch (LocalizedException $e) {
            throw new Exception($e->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $order;
    }

    /**
     * @param $order
     *
     * @throws \Exception
     */
    public function checkPayment($order)
    {
        if ($order instanceof Order) {
        } else {
            $orderModel = $this->orderFactory->create();
            $order      = $orderModel->load($order);
        }
        if ($order->getPayment()->getMethod() == RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE) {
            $paymentData = json_decode($order->getPayment()->getAdditionalInformation('split_data'), true);
            if (is_array($paymentData)) {
                $payments  = array_filter(
                    $paymentData,
                    function ($val) {
                        return is_array($val);
                    }
                );
                $totalPaid = 0;
                foreach ($payments as $payment) {
                    $totalPaid += floatval($payment['amount']);
                }
                if ($totalPaid - floatval($order->getGrandTotal()) > 0.01) {
                    // in production we will not check this.
                    //throw new \Exception("Sorry, Not allow paid lager than grand total");
                }

                if ((abs($totalPaid - floatval($order->getGrandTotal())) < 0.07) || !!$order->getData('is_exchange')) {
                    // FULL PAID
                    if ($order->canInvoice()) {
                        $order = $this->invoice($order->getId());
                    }
                    if (!$order->hasCreditmemos()) {
                        if (($order->getData('retail_has_shipment'))
                            || (in_array('can_not_create_shipment_with_negative_qty', OrderManagement::$MESSAGE_ERROR))) {
                            if ($order->canShip()) {
                                if (!$order->getData('is_exchange')) {
                                    $order->setData('retail_has_shipment', 1);
                                    $order->setData('retail_status', OrderManagement::RETAIL_ORDER_COMPLETE_NOT_SHIPPED);
                                } else {
                                    if ($order->getShippingMethod() === 'smstorepickup_smstorepickup') {
                                        $order->setData('retail_status', OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_PICKING);
                                    } else {
                                        $order->setData('retail_status', OrderManagement::RETAIL_ORDER_EXCHANGE_NOT_SHIPPED);
                                    }
                                }
                            } else {
                                if (!$order->getData('is_exchange')) {
                                    $order->setData('retail_status', OrderManagement::RETAIL_ORDER_COMPLETE_SHIPPED);
                                } else {
                                    $order->setData('retail_status', OrderManagement::RETAIL_ORDER_EXCHANGE_SHIPPED);
                                }
                            }
                        } else {
                            if (!$order->getData('is_exchange')) {
                                $order->setData('retail_status', OrderManagement::RETAIL_ORDER_COMPLETE);
                            } else {
                                $order->setData('retail_status', OrderManagement::RETAIL_ORDER_EXCHANGE);
                            }
                        }
                    } else {
                        if ($order->getState() == Order::STATE_CLOSED) {
                            $order->setData('retail_status', OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                        } else {
                            if ($order->getData('retail_has_shipment')) {
                                if ($order->canShip()) {
                                    $order->setData(
                                        'retail_status',
                                        OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_NOT_SHIPPED
                                    );
                                } else {
                                    $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED);
                                }
                            } else {
                                $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND);
                            }
                        }
                    }
                } else {
                    // PARTIALLY
                    if (!$order->hasCreditmemos()) {
                        if ($order->getData('retail_has_shipment')) {
                            if ($order->canShip()) {
                                $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_NOT_SHIPPED);
                            } else {
                                $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_SHIPPED);
                            }
                        } else {
                            $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_PAID);
                        }
                    } else {
                        if ($order->canCreditmemo()) {
                            if (($order->getData('retail_has_shipment') ) || (in_array('can_not_create_shipment_with_negative_qty', OrderManagement::$MESSAGE_ERROR))) {
                                if ($order->canShip()) {
                                    $order->setData(
                                        'retail_status',
                                        OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_NOT_SHIPPED
                                    );
                                } else {
                                    $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED);
                                }
                            } else {
                                $order->setData('retail_status', OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND);
                            }
                        } else {
                            // full refund
                            $order->setData('retail_status', OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                        }
                    }
                }
                $order->save();
            }
        }
        else if ($order->getShippingMethod() === 'smstorepickup_smstorepickup') {
            if ($order->hasCreditmemos()) {
                if ($order->canCreditmemo()) {
                    $retail_status = $order->getData('retail_status');
                    $state = OrderManagement::checkClickAndCollectOrderByCode($retail_status);
                    switch ($state) {
                        case 'await_picking':
                            $new_status = OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_PICKING;
                            break;
                        case 'picking_in_progress':
                            $new_status = OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_PICKING_IN_PROGRESS;
                            break;
                        case 'await_collection':
                            $new_status = OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION;
                            break;
                        case 'done_collection':
                            if ($order->canShip()) {
                                $new_status = OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_NOT_SHIPPED;
                            } else {
                                $new_status = OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_SHIPPED;
                            }
                            break;
                        default:
                            $new_status = $order->getData('retail_status');
                            break;
                    }
                    if (!!$this->getRequest()->getParam('retail_status')) {
                        $new_status = $this->getRequest()->getParam('retail_status');
                    }
                    $order->setData('retail_status', $new_status);
                } else {
                    $order->setData('retail_status', OrderManagement::RETAIL_ORDER_FULLY_REFUND);
                }
                $order->save();
            }
        }
    }


    /**
     * Add payment to order created by X-Retail, this means adding a transaction
     * Function will add data in order payment and transaction
     *
     * @param null $data
     *
     * @param bool $isRefunding
     *
     * @return array
     * @throws \Exception
     */
    public function addPayment($data = null, $isRefunding = false)
    {
        if (is_null($data)) {
            $data = $this->getRequest()->getParams();
        }
        if (isset($data['order_id']) && isset($data['payment_data']) && is_array($data['payment_data'])) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create();
            $order->load($data['order_id']);

            if (!$order->getId()) {
                throw new Exception("Can not find order");
            }

            // If order was created on online/backend so we will not add payment data into it
            if ($order->getPayment()->getMethod() != RetailMultiple::PAYMENT_METHOD_RETAILMULTIPLE_CODE && $order->getShippingMethod() !== 'smstorepickup_smstorepickup') {
            } else {
                // save payment information to x-retail payment. It will display in order detail on CPOS
                $splitData = json_decode($order->getPayment()->getAdditionalInformation('split_data'), true);
                foreach ($data['payment_data'] as $payment) {
                    $splitData[] = $payment;
                }
            }

            $currentShift = $this->shiftHelper->getShiftOpening($data['outlet_id'], $data['register_id']);
            $shiftId      = $currentShift->getId();
            if (!$shiftId) {
                throw new Exception("No shift are opening");
            }

            if ($isRefunding) {
                if (count($data['payment_data']) > 2) {
                    throw new Exception("Refund only accept one payment method");
                }
                // within cash rounding payment
                foreach ($data['payment_data'] as $payment_datum) {
                    $created_at       = $this->retailHelper->getCurrentTime();
                    $transactionData  = [
                        "payment_id"    => isset($payment_datum['id']) ? $payment_datum['id'] : null,
                        "shift_id"      => $shiftId,
                        "outlet_id"     => $data['outlet_id'],
                        "register_id"   => $data['register_id'],
                        "payment_title" => $payment_datum['title'],
                        "payment_type"  => $payment_datum['type'],
                        "amount"        => floatval($payment_datum['amount']),
                        "is_purchase"   => 0,
                        "created_at"    => $created_at,
                        "order_id"      => $data['order_id']
                    ];
                    $transactionModel = $this->getRetailTransactionModel();
                    $transactionModel->addData($transactionData)->save();
                }
                // check if refund deduct reward point automatically
                $this->deductRewardPointWhenRefund($order);
            } else {
                foreach ($data['payment_data'] as $payment_datum) {
                    $created_at       = $this->retailHelper->getCurrentTime();
                    $transactionData  = [
                        "payment_id"    => isset($payment_datum['id']) ? $payment_datum['id'] : null,
                        "shift_id"      => $shiftId,
                        "outlet_id"     => $data['outlet_id'],
                        "register_id"   => $data['register_id'],
                        "payment_title" => $payment_datum['title'],
                        "payment_type"  => $payment_datum['type'],
                        "amount"        => floatval($payment_datum['amount']),
                        "is_purchase"   => 1,
                        "created_at"    => $created_at,
                        "order_id"      => $data['order_id']
                    ];
                    $transactionModel = $this->getRetailTransactionModel();
                    $transactionModel->addData($transactionData)->save();
                }
            }

            if (isset($splitData)) {
                $order->getPayment()->setAdditionalInformation('split_data', json_encode($splitData))->save();
            }

            $this->checkPayment($order->getEntityId());

            $criteria = new DataObject(
                [
                    'entity_id' => $order->getEntityId(),
                    'storeId' => $data['store_id'],
                    'outletId' => $data['outlet_id'],
                    'isSearchOnline' => true
                ]
            );

            return $this->orderHistoryManagement->loadOrders($criteria);
        } else {
            throw new Exception("Must required data");
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function takePayment()
    {
        return $this->addPayment($this->getRequest()->getParams(), false);
    }

    /**
     * @return \SM\Shift\Model\RetailTransaction
     */
    protected function getRetailTransactionModel()
    {
        return $this->retailTransactionFactory->create();
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @throws \Exception
     */
    protected function deductRewardPointWhenRefund($order)
    {
        if ($order->getData('reward_points_earned')) {
            $reward_point_deduct = $order->getData('reward_points_earned');
            $currentRewardPointBalance = $this->getCustomerCurrentRewardPointBalance(
                $order->getCustomerId(),
                $order->getStoreId()
            );
            $currentRewardPointBalance += $reward_point_deduct;
            $mage2EEConfig = $this->scopeConfig->getValue(
                'magento_reward/general/deduct_automatically',
                ScopeInterface::SCOPE_STORE,
                0
            );
            $aheadWorkConfig = $this->scopeConfig->getValue(
                'aw_rewardpoints/calculation/is_reimburse_refund_points',
                ScopeInterface::SCOPE_STORE,
                0
            );
            if ($this->integrateHelper->isIntegrateRP()) {
                if (($this->integrateHelper->isRewardPointMagento2EE() && $mage2EEConfig)
                || ($this->integrateHelper->isAHWRewardPoints() && $aheadWorkConfig)) {
                    $order->setData('reward_points_refunded', floatval($reward_point_deduct));
                    $order->setData('previous_reward_points_balance', floatval($currentRewardPointBalance));
                    $order->save();
                }
            }
        }
    }
    
    protected function getCustomerCurrentRewardPointBalance($customerId, $storeId)
    {
        $currentRewardPointBalance = 0;
        if ($this->integrateHelper->isIntegrateRP()
            && $this->integrateHelper->isAHWRewardPoints()) {
            $currentRewardPointBalance = $this->integrateHelper
                ->getRpIntegrateManagement()
                ->getCurrentIntegrateModel()
                ->getCurrentPointBalance(
                    $customerId,
                    $this->storeManager->getStore($storeId)->getWebsiteId()
                );
        }

        if ($this->integrateHelper->isIntegrateRP()
            && $this->integrateHelper->isRewardPointMagento2EE()) {
            $currentRewardPointBalance = $this->integrateHelper
                ->getRpIntegrateManagement()
                ->getCurrentIntegrateModel()
                ->getCurrentPointBalance(
                    $this->getCustomerModel()->load($customerId),
                    $this->storeManager->getStore($storeId)->getWebsiteId()
                );
        }
        return $currentRewardPointBalance;
    }

    /**
     * @return \Magento\Customer\Model\Customer
     */
    protected function getCustomerModel()
    {
        return $this->customerFactory->create();
    }
}

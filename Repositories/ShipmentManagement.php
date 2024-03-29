<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 10/01/2017
 * Time: 15:30
 */

namespace SM\Sales\Repositories;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;
use Magento\Store\Model\StoreManagerInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Sales\Model\Order\Shipment\Validation\QuantityValidator;
use SM\Integrate\Helper\Data;
use SM\Email\Helper\EmailSender;
use SM\XRetail\Helper\Data as RetailHelper;
use SM\Shift\Model\RetailTransactionFactory;
use SM\Shift\Helper\Data as ShiftHelper;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;

/**
 * Class ShipmentManagement
 *
 * @package SM\Sales\Repositories
 */
class ShipmentManagement extends ServiceAbstract
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader
     */
    protected $shipmentLoader;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\ShipmentSender
     */
    protected $shipmentSender;

    /**
     * @var
     */
    protected $shipmentValidator;

    /**
     * @var \SM\Sales\Repositories\InvoiceManagement
     */
    protected $invoiceManagement;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;
    private $orderHistoryManagement;

    public static $FROM_API = false;

    static $CREATE_SHIPMENT = false;
    private $integrateData;

    /**
     * @var \SM\Email\Helper\EmailSender
     */
    private $emailSender;

    /**
     * @var \SM\XRetail\Helper\Data
     */

    private $retailHelper;

    /**
     * @var \SM\Shift\Model\RetailTransactionFactory
     */
    protected $retailTransactionFactory;

    /**
     * @var \SM\Shift\Helper\Data
     */
    private $shiftHelper;

    /**
     * @var \Magento\Sales\Api\Data\ShipmentExtensionInterfaceFactory
     */
    private $shipmentExtensionInterfaceFactory;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * ShipmentManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                     $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                               $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface                  $storeManager
     * @param \Magento\Framework\ObjectManagerInterface                   $objectManager
     * @param \Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader $shipmentLoader
     * @param \Magento\Sales\Model\Order\Email\Sender\ShipmentSender      $shipmentSender
     * @param \Magento\Sales\Model\OrderFactory                           $orderFactory
     * @param \SM\Sales\Repositories\InvoiceManagement                    $invoiceManagement
     * @param \SM\Sales\Repositories\OrderHistoryManagement               $orderHistoryManagement
     * @param Data                                                        $integrateData
     * @param \SM\Email\Helper\EmailSender                                $emailSender
     * @param ShiftHelper                                                 $shiftHelper
     * @param RetailHelper                                                $retailHelper
     * @param RetailTransactionFactory                                    $retailTransactionFactory
     * @param \Magento\Sales\Api\Data\ShipmentExtensionInterfaceFactory   $shipmentExtensionInterfaceFactory
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        ShipmentLoader $shipmentLoader,
        ShipmentSender $shipmentSender,
        OrderFactory $orderFactory,
        InvoiceManagement $invoiceManagement,
        OrderHistoryManagement $orderHistoryManagement,
        Data $integrateData,
        EmailSender $emailSender,
        ShiftHelper $shiftHelper,
        RetailHelper $retailHelper,
        RetailTransactionFactory $retailTransactionFactory,
        \Magento\Sales\Api\Data\ShipmentExtensionInterfaceFactory $shipmentExtensionInterfaceFactory,
        OrderResource $orderResource,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->invoiceManagement = $invoiceManagement;
        $this->shipmentSender = $shipmentSender;
        $this->shipmentLoader = $shipmentLoader;
        $this->objectManager = $objectManager;
        $this->orderHistoryManagement = $orderHistoryManagement;
        $this->integrateData = $integrateData;
        $this->emailSender = $emailSender;
        $this->shiftHelper = $shiftHelper;
        $this->retailHelper = $retailHelper;
        $this->retailTransactionFactory = $retailTransactionFactory;
        $this->shipmentExtensionInterfaceFactory = $shipmentExtensionInterfaceFactory;
        $this->orderResource = $orderResource;
        $this->orderRepository = $orderRepository;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function createClickAndCollectInvoice()
    {
        self:: $FROM_API = true;
        if (!($orderId = $this->getRequest()->getParam('order_id'))) {
            throw new Exception("Must have param Order Id");
        }

        if (!($storeId = $this->getRequest()->getParam('store_id'))) {
            throw new Exception("Must have param Store Id");
        }

        if (!($outletId = $this->getRequest()->getParam('outlet_id'))) {
            throw new Exception("Must have param Outlet Id");
        }
        $is_pwa = $this->getRequest()->getParam('is_pwa');
        if ($is_pwa !== null && (int)$is_pwa === 1) {
            $order = $this->orderFactory->create()->load($orderId);
            if ($order->canShip()) {
                $order = $this->ship($orderId);
            }
            $this->invoiceManagement->checkPayment($order);
            $this->savePaymentTransaction();
        } else {
            $this->pick($orderId);
            $this->invoiceManagement->invoice($orderId);
        }
        $criteria = new DataObject(
            [
                'entity_id'      => $orderId,
                'storeId'        => $storeId,
                'outletId'       => $outletId,
                'isSearchOnline' => true,
            ]
        );

        return $this->orderHistoryManagement->loadOrders($criteria);
    }

    /**
     * @throws \Exception
     */
    public function createShipment()
    {
        self::$FROM_API = true;
        if (!($orderId = $this->getRequest()->getParam('order_id'))) {
            throw new Exception("Must have param Order Id");
        }

        if (!($storeId = $this->getRequest()->getParam('store_id'))) {
            throw new Exception("Must have param Store Id");
        }

        $outletId = $this->getRequest()->getParam('outlet_id');

        if ($this->getRequest()->getParam('create_shipment')) {
            self::$CREATE_SHIPMENT = $this->getRequest()->getParam('create_shipment') == 1;
        }
        if (self::$CREATE_SHIPMENT) {
            $order = $this->ship($orderId);
            $this->pick($orderId);
            $this->invoiceManagement->checkPayment($order);
        } else {
            $order = $this->pick($orderId);
        }

        $criteria = new DataObject(
            [
                'entity_id' => $order->getEntityId(),
                'storeId'   => $storeId,
                'outletId'  => $outletId,
            ]
        );

        return $this->orderHistoryManagement->loadOrders($criteria, true);
    }

    /**
     * @param $orderId
     *
     * @return \Magento\Sales\Model\Order|\Magento\Sales\Api\Data\OrderInterface
     * @throws \Exception
     */
    public function pick($orderId)
    {
        $retailStatus = $this->getRequest()->getParam('retail_status');
        $orderModel = $this->orderFactory->create();
        $order = $orderModel->load($orderId);

        if (!$order->getId()) {
            throw new Exception("Can not find order");
        }

        $arrAwaitCollection = [
            OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_COLLECTION,
            OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_COLLECTION,
            OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION,
            OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_COLLECTION,
        ];
        if (in_array((int)$retailStatus, $arrAwaitCollection)) {
            $template = $this->getRequest()->getParam('template');
            $email = $order->getShippingAddress()->getEmail();
            $name = $order->getShippingAddress()->getName();
            $tempId = "xpos_send_picking";
            if (!is_null($template) && !is_null($email) && !is_null($name)) {
                try {
                    $this->emailSender->sendEmailOrder(['template' => $template], ['email' => $email, 'name' => $name], $tempId, null);
                } catch (\Exception $e) {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $logger = $objectManager->get('Psr\Log\LoggerInterface');
                    $logger->critical('===> Unable to send order picking email');
                    $logger->critical($e->getMessage()."\n".$e->getTraceAsString());
                }
            }
        }

        $order->setData('retail_status', $retailStatus);
        return $this->orderRepository->save($order);
    }

    /**
     * @param $orderId
     *
     * @return \Magento\Sales\Model\Order
     * @throws \Exception
     */
    public function ship($orderId)
    {
        if (!empty($data['comment_text'])) {
            $this->objectManager->get('Magento\Backend\Model\Session')
                ->setCommentText($data['comment_text']);
        }
        try {
            $this->shipmentLoader->setOrderId($orderId);
            $shipment = $this->shipmentLoader->load();
            if (!$shipment) {
                throw new Exception("Can't create shipment");
            }

            if (!empty($data['comment_text'])) {
                $shipment->addComment(
                    $data['comment_text'],
                    isset($data['comment_customer_notify']),
                    isset($data['is_visible_on_front'])
                );
            }
            $validationResult = $this->getShipmentValidator()
                ->validate($shipment, [QuantityValidator::class]);

            if ($validationResult->hasMessages()) {
                throw new Exception(
                    __("Shipment Document Validation Error(s):\n".implode("\n", $validationResult->getMessages()))
                );
            }
            $shipment->register();
            $sourceCode = $this->request->getParam('sourceCode');
            if (!empty($sourceCode) && $this->integrateData->isMagentoInventory()) {
                $shipmentExtension = $shipment->getExtensionAttributes() ?? $this->shipmentExtensionInterfaceFactory->create();
                $shipmentExtension->setSourceCode($sourceCode);
            }

            $this->saveShipment($shipment);

            if (!empty($data['send_email'])) {
                $this->shipmentSender->send($shipment);
            }
        } catch (LocalizedException $e) {
            throw new Exception($e->getMessage());
        } catch (Exception $e) {
            $this->objectManager->get('Psr\Log\LoggerInterface')->critical($e);
            throw new Exception($e->getMessage());
        }

        return $shipment->getOrder();
    }

    /**
     * Save shipment and order in one transaction
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     *
     * @return $this
     */
    protected function saveShipment($shipment)
    {
        $shipment->getOrder()->setIsInProcess(true);
        $transaction = $this->objectManager->create(
            'Magento\Framework\DB\Transaction'
        );
        $transaction->addObject(
            $shipment
        )->addObject(
            $shipment->getOrder()
        )->save();

        return $this;
    }

    /**
     * @return \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface
     */
    private function getShipmentValidator()
    {
        if ($this->shipmentValidator === null) {
            $this->shipmentValidator = $this->objectManager->get(
                ShipmentValidatorInterface::class
            );
        }

        return $this->shipmentValidator;
    }

    /**
     * @throws \Exception
     */
    protected function savePaymentTransaction()
    {
        $data = $this->getRequest()->getParams();
        $order = $data['order'];
        if ($order['payment'] !== null) {
            $openingShift = $this->shiftHelper->getShiftOpening($data['outlet_id'], $data['register_id']);
            if ($order['payment'] !== null
                && is_array($order['payment'])
                && count($order['payment']) > 0
            ) {
                foreach ($order['payment'] as $payment_datum) {
                    if (!is_array($payment_datum)) {
                        continue;
                    }
                    if (!isset($payment_datum['id']) || !$payment_datum['id']) {
                        throw new Exception("Payment data not valid");
                    }
                    $created_at = $this->retailHelper->getCurrentTime();
                    $_p = $this->retailTransactionFactory->create();
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
                            'order_id'      => $order['order_id'],
                            'user_name'     => $data['user_name'],
                        ]
                    )->save();
                }
            }
        }
    }
}

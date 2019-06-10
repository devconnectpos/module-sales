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
     * @param \SM\Email\Helper\EmailSender                                $emailSender
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
        EmailSender $emailSender
    ) {
        $this->orderFactory           = $orderFactory;
        $this->invoiceManagement      = $invoiceManagement;
        $this->shipmentSender         = $shipmentSender;
        $this->shipmentLoader         = $shipmentLoader;
        $this->objectManager          = $objectManager;
        $this->orderHistoryManagement = $orderHistoryManagement;
        $this->integrateData          = $integrateData;
        $this->emailSender            = $emailSender;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function createClickAndCollectInvoice()
    {
        if (!($orderId = $this->getRequest()->getParam('order_id'))) {
            throw new Exception("Must have param Order Id");
        }

        if (!($storeId = $this->getRequest()->getParam('store_id'))) {
            throw new Exception("Must have param Store Id");
        }

        if (!($outletId = $this->getRequest()->getParam('outlet_id'))) {
            throw new Exception("Must have param Outlet Id");
        }
        $this->pick($orderId);
        $this->invoiceManagement->invoice($orderId);
        $criteria = new DataObject(
            [
                'entity_id' => $orderId,
                'storeId' => $storeId,
                'outletId' => $outletId,
                'isSearchOnline' => true
            ]
        );

        return $this->orderHistoryManagement->loadOrders($criteria);
    }

    /**
     * @throws \Exception
     */
    public function createShipment()
    {
        self:: $FROM_API = true;
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
                'storeId' => $storeId,
                'outletId' => $outletId
            ]
        );

        return $this->orderHistoryManagement->loadOrders($criteria);
    }

    /**
     * @param $orderId
     *
     * @return \Magento\Sales\Model\Order
     * @throws \Exception
     */
    public function pick($orderId)
    {
        $retail_status = $this->getRequest()->getParam('retail_status');
        $orderModel    = $this->orderFactory->create();
        $order         = $orderModel->load($orderId);

        if (!$order->getId()) {
            throw new Exception("Can not find order");
        }
        $arrAwaitCollection = [
            OrderManagement::RETAIL_ORDER_PARTIALLY_PAID_AWAIT_COLLECTION,
            OrderManagement::RETAIL_ORDER_COMPLETE_AWAIT_COLLECTION,
            OrderManagement::RETAIL_ORDER_PARTIALLY_REFUND_AWAIT_COLLECTION,
            OrderManagement::RETAIL_ORDER_EXCHANGE_AWAIT_COLLECTION
        ];
        if (in_array((int)$retail_status,$arrAwaitCollection)) {
            $template = $this->getRequest()->getParam('template');
            $email = $order->getShippingAddress()->getEmail();
            $name = $order->getShippingAddress()->getName();
            $tempId = "xpos_send_picking";
            if (!is_null($template) && !is_null($email) && !is_null($name)) {
                $this->emailSender->sendEmailOrder(['template' => $template], ['email' => $email, 'name' => $name], null, $tempId);
            }
        }
        $order->setData('retail_status', $retail_status);
        $order->save();

        return $order;
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
                    __("Shipment Document Validation Error(s):\n" . implode("\n", $validationResult->getMessages()))
                );
            }
            $shipment->register();
            $sourceCode = $this->request->getParam('sourceCode');
            if (!empty($sourceCode) && $this->integrateData->isMagentoInventory()) {
                $shipmentExtension = $shipment->getExtensionAttributes();
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
}

<?php

namespace SM\Sales\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use SM\Sales\Model\ResourceModel\OrderSyncError\CollectionFactory;
use \SM\Sales\Model\ResourceModel\OrderSyncError as OrderSyncErrorResource;

class AddIncrementIdPrefix implements ObserverInterface
{
    const XML_PATH_CPOS_ENABLE_PREFIX = 'xpos/order_settings/enable_prefix';
    const XML_PATH_CPOS_ORDER_PREFIX = 'xpos/order_settings/order_prefix';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CollectionFactory
     */
    protected $orderSyncErrorCollectionFactory;

    /**
     * @var OrderSyncErrorResource
     */
    protected $orderSyncErrorResource;

    /**
     * @var bool
     */
    protected $prefixAdded = false;

    /**
     * @var bool
     */
    protected $orderSyncErrorCleaned = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderRepository $orderRepository,
        LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        OrderSyncErrorResource $resource
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->orderSyncErrorCollectionFactory = $collectionFactory;
        $this->orderSyncErrorResource = $resource;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');

        if (is_null($order)) {
            return;
        }

        $this->handleCleanupOrderSyncError($order);
        $this->handleOrderPrefix($order);
    }

    private function handleCleanupOrderSyncError(Order $order)
    {
        if ($this->orderSyncErrorCleaned) {
            return;
        }

        if (empty($order->getData('retail_id'))) {
            return;
        }

        $collection = $this->orderSyncErrorCollectionFactory->create()
            ->addFieldToFilter('retail_id', $order->getData('retail_id'));

        if ($collection->count() === 0) {
            return;
        }

        /** @var \SM\Sales\Model\OrderSyncError $item */
        foreach ($collection->getItems() as $item) {
            try {
                $this->orderSyncErrorResource->delete($item);
            } catch (\Exception $e) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/connectpos.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $logger->info("===> Unable to delete order sync error entry with retail ID #" . $item->getData('retail_id'));
                $logger->info($e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }

        $this->orderSyncErrorCleaned = true;
    }

    private function handleOrderPrefix(Order $order)
    {
        if (!$this->isOrderPrefixEnabled()) {
            return;
        }

        if ($this->prefixAdded) {
            return;
        }

        // If order does not come from POS, skip
        if (empty($order->getData('retail_status')) && empty($order->getData('retail_id'))) {
            return;
        }

        $order->setIncrementId($this->getOrderIncrementIdPrefix().$order->getIncrementId());

        try {
            $this->orderRepository->save($order);
            $this->prefixAdded = true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @return bool
     */
    private function isOrderPrefixEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CPOS_ENABLE_PREFIX);
    }

    /**
     * @return mixed|string
     */
    private function getOrderIncrementIdPrefix()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CPOS_ORDER_PREFIX);
    }
}

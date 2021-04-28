<?php

namespace SM\Sales\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;

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
     * @var bool
     */
    protected $prefixAdded = false;

    public function __construct(ScopeConfigInterface $scopeConfig, OrderRepository $orderRepository, LoggerInterface $logger)
    {
        $this->scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getData('order');

        if (is_null($order)) {
            return;
        }

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

        $order->setIncrementId($this->getOrderIncrementIdPrefix() . $order->getIncrementId());

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

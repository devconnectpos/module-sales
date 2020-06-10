<?php

namespace SM\Sales\Plugin;

use Magento\CatalogInventory\Model\StockState;
use Magento\Sales\Model\Order;
use SM\Sales\Helper\Data;

class AllowRefundPendingOrder
{
    /**
     * @var \SM\Sales\Helper\Data
     */
    private $salesHelper;

    public function __construct(Data $salesHelper) {
        $this->salesHelper = $salesHelper;
    }

    /**
     * Set forced canCreditmemo flag
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function beforeCanCreditmemo($order)
    {
        if (!$order->canUnhold() && !$order->isCanceled() && $order->getState() != Order::STATE_CLOSED
            && (!$order->hasInvoices() ||
                ($order->hasInvoices() && $order->canInvoice() && $order->getIsRefundedPendingOrder() == 1))
            && !!$this->salesHelper->isEnableRefundPendingOrder()
            && $order->getRetailId() !== null
        ) {
            $order->setForcedCanCreditmemo(true);
        }
    }
}

<?php

namespace SM\Sales\Plugin;

use Magento\CatalogInventory\Model\StockState;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use SM\Sales\Helper\Data;

class SetQtyToRefundPendingOrder
{
    /**
     * @var \SM\Sales\Helper\Data
     */
    private $salesHelper;

    public function __construct(Data $salesHelper) {
        $this->salesHelper = $salesHelper;
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $subject
     * @param                                 $result
     *
     * @return int|mixed
     */
    public function afterGetQtyToRefund(Item $subject, $result)
    {
        if ($subject->isDummy()) {
            return 0;
        }

        $order = $subject->getOrder();

        if (!$order->canUnhold() && !$order->isCanceled() && $order->getState() != Order::STATE_CLOSED
            && (!$order->hasInvoices() || ($order->hasInvoices() && $order->getIsRefundedPendingOrder() == 1))
            && !!$this->salesHelper->isEnableRefundPendingOrder()
            && $order->getRetailId() !== null
        ) {
            return max($subject->getQtyOrdered() - $subject->getQtyRefunded(), 0);
        }
        return $result;
    }
}

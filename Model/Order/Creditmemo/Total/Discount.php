<?php

namespace SM\Sales\Model\Order\Creditmemo\Total;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order;
use SM\Sales\Helper\Data;
use Magento\Sales\Model\Order\Creditmemo\Total\Discount as MagentoDiscount;

class Discount extends MagentoDiscount
{
    /**
     * @var \SM\Sales\Helper\Data
     */
    private $salesHelper;

    public function __construct(
        Data $salesHelper
    ) {
        $this->salesHelper = $salesHelper;
    }

    /**
     * Collect discount
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return \Magento\Sales\Model\Order\Creditmemo\Total\Discount
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function collect(Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        if (!$order->canUnhold() && !$order->isCanceled() && $order->getState() != Order::STATE_CLOSED
            && (!$order->hasInvoices()
                || ($order->hasInvoices()
                    && $order->canInvoice()
                    && $order->getIsRefundedPendingOrder() == 1))
            && !!$this->salesHelper->isEnableRefundPendingOrder()
            && $order->getRetailId() !== null
        ) {
            $creditmemo->setDiscountAmount(0);
            $creditmemo->setBaseDiscountAmount(0);

            $order = $creditmemo->getOrder();

            $totalDiscountAmount     = 0;
            $baseTotalDiscountAmount = 0;

            /**
             * Calculate how much shipping discount should be applied
             * basing on how much shipping should be refunded.
             */
            $baseShippingAmount = $this->getBaseShippingAmount($creditmemo);

            /**
             * If credit memo's shipping amount is set and Order's shipping amount is 0,
             * throw exception with different message
             */
            if ($baseShippingAmount && $order->getBaseShippingAmount() <= 0) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("You can not refund shipping if there is no shipping amount.")
                );
            }
            if ($baseShippingAmount) {
                $baseShippingDiscount    = $baseShippingAmount *
                                           $order->getBaseShippingDiscountAmount() /
                                           $order->getBaseShippingAmount();
                $shippingDiscount        = $order->getShippingAmount() * $baseShippingDiscount / $order->getBaseShippingAmount();
                $totalDiscountAmount     = $totalDiscountAmount + $shippingDiscount;
                $baseTotalDiscountAmount = $baseTotalDiscountAmount + $baseShippingDiscount;
            }

            /** @var $item \Magento\Sales\Model\Order\Invoice\Item */
            foreach ($creditmemo->getAllItems() as $item) {
                $orderItem = $item->getOrderItem();

                if ($orderItem->isDummy()) {
                    continue;
                }

                $orderItemDiscount     = (double)$orderItem->getDiscountAmount();
                $baseOrderItemDiscount = (double)$orderItem->getBaseDiscountAmount();
                $orderItemQty          = $orderItem->getQtyOrdered();

                if ($orderItemDiscount && $orderItemQty) {
                    $discount     = $orderItemDiscount - $orderItem->getDiscountRefunded();
                    $baseDiscount = $baseOrderItemDiscount - $orderItem->getBaseDiscountRefunded();
                    if (!$item->isLast()) {
                        $availableQty = $orderItemQty - $orderItem->getQtyRefunded();
                        $discount     = $creditmemo->roundPrice($discount / $availableQty * $item->getQty(), 'regular', true);
                        $baseDiscount = $creditmemo->roundPrice(
                            $baseDiscount / $availableQty * $item->getQty(),
                            'base',
                            true
                        );
                    }

                    $item->setDiscountAmount($discount);
                    $item->setBaseDiscountAmount($baseDiscount);

                    $totalDiscountAmount     += $discount;
                    $baseTotalDiscountAmount += $baseDiscount;
                }
            }

            $creditmemo->setDiscountAmount(-$totalDiscountAmount);
            $creditmemo->setBaseDiscountAmount(-$baseTotalDiscountAmount);

            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - $totalDiscountAmount);
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - $baseTotalDiscountAmount);

            return $this;
        } else {
            parent::collect($creditmemo);
        }
        return $this;
    }
}

<?php

namespace SM\Sales\Model\Order\Creditmemo;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Sales\Model\Order\Creditmemo\Item as MagentoCreditmemoItem;
use SM\Sales\Helper\Data;
use Magento\Sales\Model\Order;

class Item extends MagentoCreditmemoItem
{

    /**
     * @var \SM\Sales\Helper\Data
     */
    private $salesHelper;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Sales\Model\Order\ItemFactory $orderItemFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        Data $salesHelper
    ) {
        $this->salesHelper = $salesHelper;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $orderItemFactory,
            $resource,
            $resourceCollection,
            $data);
    }

    /**
     * Invoice item row total calculation
     *
     * @return $this
     */
    public function calcRowTotal()
    {
        $creditmemo = $this->getCreditmemo();
        $order = $creditmemo->getOrder();
        $orderItem = $this->getOrderItem();

        if (!$order->canUnhold() && !$order->isCanceled() && $order->getState() != Order::STATE_CLOSED
            && (!$order->hasInvoices() || ($order->hasInvoices() && $order->getIsRefundedPendingOrder() == 1))
            && !!$this->salesHelper->isEnableRefundPendingOrder()
            && $order->getRetailId() !== null
        ) {
            $orderItemQtyInvoiced = $orderItem->getQtyOrdered();

            $rowTotal = $orderItem->getRowTotal() - $orderItem->getAmountRefunded();
            $baseRowTotal = $orderItem->getBaseRowTotal() - $orderItem->getBaseAmountRefunded();
        } else {
            $orderItemQtyInvoiced = $orderItem->getQtyInvoiced();

            $rowTotal = $orderItem->getRowInvoiced() - $orderItem->getAmountRefunded();
            $baseRowTotal = $orderItem->getBaseRowInvoiced() - $orderItem->getBaseAmountRefunded();
        }

        $rowTotalInclTax = $orderItem->getRowTotalInclTax();
        $baseRowTotalInclTax = $orderItem->getBaseRowTotalInclTax();

        $qty = $this->processQty();
        if (!$this->isLast() && $orderItemQtyInvoiced > 0 && $qty >= 0) {
            $availableQty = $orderItemQtyInvoiced - $orderItem->getQtyRefunded();
            $rowTotal = $creditmemo->roundPrice($rowTotal / $availableQty * $qty);
            $baseRowTotal = $creditmemo->roundPrice($baseRowTotal / $availableQty * $qty, 'base');
        }
        $this->setRowTotal($rowTotal);
        $this->setBaseRowTotal($baseRowTotal);

        if ($rowTotalInclTax && $baseRowTotalInclTax) {
            $orderItemQty = $orderItem->getQtyOrdered();
            $this->setRowTotalInclTax(
                $creditmemo->roundPrice($rowTotalInclTax / $orderItemQty * $qty, 'including')
            );
            $this->setBaseRowTotalInclTax(
                $creditmemo->roundPrice($baseRowTotalInclTax / $orderItemQty * $qty, 'including_base')
            );
        }
        return $this;
    }

    /**
     * Calculate qty for creditmemo item.
     *
     * @return int|float
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function processQty()
    {
        $orderItem = $this->getOrderItem();
        $qty = $this->getQty();
        if ($orderItem->getIsQtyDecimal()) {
            $qty = (double)$qty;
        } else {
            $qty = (int)$qty;
        }
        $qty = $qty > 0 ? $qty : 0;
        if ($this->isQtyAvailable($qty, $orderItem)) {
            return $qty;
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We found an invalid quantity to refund item "%1".', $this->getName())
            );
        }
    }

    /**
     * Checks if quantity available for refund
     *
     * @param int $qty
     * @param \Magento\Sales\Model\Order\Item $orderItem
     * @return bool
     */
    private function isQtyAvailable($qty, \Magento\Sales\Model\Order\Item $orderItem)
    {
        return $qty <= $orderItem->getQtyToRefund() || $orderItem->isDummy();
    }
}

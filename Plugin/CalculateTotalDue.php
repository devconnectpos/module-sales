<?php

namespace SM\Sales\Plugin;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use SM\Sales\Helper\Data;

class CalculateTotalDue
{
    /**
     * @var \SM\Sales\Helper\Data
     */
    private $salesHelper;
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    public function __construct(
        Data $salesHelper,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->salesHelper   = $salesHelper;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $result
     *
     * @return mixed
     */
    public function afterGetTotalDue(Order $order, $result)
    {
        if (!!$this->salesHelper->isEnableRefundPendingOrder() &&
            $order->getIsRefundedPendingOrder() == 1 &&
            $order->hasInvoices() && $order->hasCreditmemos() &&
            $order->getRetailId() !== null
        ) {
            $total = $order->getGrandTotal() - $order->getTotalPaid() - $order->getTotalRefunded();
            $total = $this->priceCurrency->round($total);
            return max($total, 0);
        }
        return $result;
    }
}

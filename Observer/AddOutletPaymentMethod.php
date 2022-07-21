<?php

namespace SM\Sales\Observer;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use SM\XRetail\Helper\DataConfig as DataConfigHelper;

class AddOutletPaymentMethod implements ObserverInterface
{
    /**
     * @var DataConfigHelper
     */
    protected $dataConfig;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    public function __construct(
        DataConfigHelper       $dataConfig,
        PriceCurrencyInterface $priceCurrency
    )
    {
        $this->dataConfig = $dataConfig;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if (is_null($order)) {
            return $this;
        }

        if (!$order->getPayment()) {
            return $this;
        }

        $paymentJsonData = $order->getPayment()->getAdditionalInformation('split_data');

        if (!is_string($paymentJsonData)) {
            return $this;
        }

        $splitData = json_decode($paymentJsonData, true);
        $outletPaymentMethod = [];

        foreach ($splitData as $payment) {
            $pAmount = $payment['amount'] ?? 0;
            $pTitle = $payment['title'] ?? '';
            $amount = floatval($pAmount);

            if ($amount == 0) {
                continue;
            }

            $outletPaymentMethod[$pTitle] = strtoupper($pTitle);
        }

        if ($this->dataConfig->isRoundingOrderStoreCreditData()) {
            // Attempt to round things up for fking Mr's Leather
            $order->setData('outlet_payment_method', implode('-', $outletPaymentMethod));
            $order->setData('base_customer_balance_amount', round($order->getData('base_customer_balance_amount')));
            $order->setData('customer_balance_amount', round($order->getData('customer_balance_amount')));
            $order->setData('base_grand_total', round($order->getData('base_grand_total')));
            $order->setData('grand_total', round($order->getData('grand_total')));
        }
        return $this;
    }
}

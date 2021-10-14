<?php

namespace SM\Sales\Observer;


use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class AddOutletPaymentMethod implements ObserverInterface
{
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

        $order->setData('outlet_payment_method', implode('-', $outletPaymentMethod));
        return $this;
    }
}

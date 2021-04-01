<?php

namespace SM\Sales\Plugin\WebkulPreorder;

class InvoiceTotal
{
    public function afterCollect(
        $subject,
        $result,
        \Magento\Sales\Model\Order\Invoice $invoice
    ) {
        if (!\SM\Sales\Repositories\OrderManagement::$FROM_API) {
            return $result;
        }

        $amount = $invoice->getOrder()->getPreorderFee();
        $invoice->setPreorderFee(0);

        $invoice->setGrandTotal($invoice->getGrandTotal() - $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() - $amount);

        return $subject;
    }
}

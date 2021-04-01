<?php

namespace SM\Sales\Plugin\WebkulPreorder;

class QuoteTotal
{
    public function afterCollect(
        $subject,
        $result,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        if (!\SM\Sales\Repositories\OrderManagement::$FROM_API) {
            return $result;
        }

        $total->setTotalAmount('preorder_fee', 0);
        $total->setBaseTotalAmount('preorder_fee', 0);
        $total->setPreorderFee(0);
        $quote->setPreorderFee(0);
        return $subject;
    }
}

<?php

namespace SM\Sales\Plugin;

use SM\Sales\Repositories\OrderManagement;
use Magento\Quote\Model\Quote\Address;

class AllowRetailShipping
{

    /**
     * @param \Magento\Quote\Model\Quote\Address $subject
     * @param                                    $result
     *
     * @return bool
     */
    public function afterGetShippingRateByCode(Address $subject, $result)
    {
        if (OrderManagement::$FROM_API === true && !$result) {
            return true;
        }

        return $result;
    }
}

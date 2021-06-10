<?php

namespace SM\Sales\Plugin;

class AlwaysIsSalable
{
    /**
     * @param \Magento\InventorySales\Model\IsProductSalableForRequestedQtyCondition\ProductSalableResult $subject
     * @param bool $result
     *
     * @return bool|mixed
     */
    public function afterIsSalable($subject, $result)
    {
        if (\SM\Sales\Repositories\OrderManagement::$FROM_API) {
            return true;
        }

        return $result;
    }
}

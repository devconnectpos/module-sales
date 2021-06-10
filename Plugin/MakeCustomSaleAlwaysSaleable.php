<?php

namespace SM\Sales\Plugin;

use SM\CustomSale\Helper\Data;

/**
 * Class MakeCustomSaleAlwaysSaleable
 *
 * @package SM\Sales\Plugin
 */
class MakeCustomSaleAlwaysSaleable
{
    /**
     * @param \Magento\Catalog\Model\Product $subject
     * @param bool $result
     */
    public function afterIsSaleable($subject, $result)
    {
        if ($subject->getSku() === Data::CUSTOM_SALES_PRODUCT_SKU) {
            return true;
        }

        return $result;
    }

    /**
     * @param \Magento\Catalog\Model\Product $subject
     * @param bool $result
     */
    public function afterIsSalable($subject, $result)
    {
        if ($subject->getSku() === Data::CUSTOM_SALES_PRODUCT_SKU) {
            return true;
        }

        return $result;
    }
}

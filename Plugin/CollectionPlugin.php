<?php

namespace SM\Sales\Plugin;

class CollectionPlugin
{
    /**
     * @param $subject
     * @param $result
     * @return array
     */
    public function afterGetItemsByColumnValue($subject, $result)
    {
        foreach ($subject->load() as $item) {
            if ($item->getData("sku") == "custom_sales_product_for_retail") {
                return [];
            }
        }
        return $result;
    }
}

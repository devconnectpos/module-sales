<?php
declare(strict_types=1);

namespace SM\Sales\Plugin;

use Magento\Quote\Model\Quote\Item\AbstractItem;

class PreventCustomSaleTaxClassIdChange
{
    public function beforeMapItem(
        $subject,
        \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $itemDataObjectFactory,
        AbstractItem $item,
        $priceIncludesTax,
        $useBaseCurrency,
        $parentCode = null
    ) {
        if ($item instanceof \Magento\Quote\Api\Data\CartItemInterface && method_exists($item, 'getBuyRequest')) {
            $buyRequest = $item->getBuyRequest();
            $customSaleConfig = $buyRequest->getData('custom_sale');

            if (!$customSaleConfig || !is_array($customSaleConfig)) {
                return [$itemDataObjectFactory, $item, $priceIncludesTax, $useBaseCurrency, $parentCode];
            }

            if (isset($customSaleConfig['tax_class_id'])) {
                $item->getProduct()->setData('tax_class_id', $customSaleConfig['tax_class_id']);
                $item->getProduct()->setIsSuperMode(true);
            }
        }

        return [$itemDataObjectFactory, $item, $priceIncludesTax, $useBaseCurrency, $parentCode];
    }
}

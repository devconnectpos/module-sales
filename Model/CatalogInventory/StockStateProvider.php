<?php

namespace SM\Sales\Model\CatalogInventory;

use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\DataObject\Factory as ObjectFactory;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Math\Division as MathDivision;

class StockStateProvider extends \Magento\CatalogInventory\Model\StockStateProvider
{
    /**
     * Validate quote qty
     *
     * @param StockItemInterface $stockItem
     * @param int|float          $qty
     * @param int|float          $summaryQty
     * @param int|float          $origQty
     *
     * @return \Magento\Framework\DataObject
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function checkQuoteItemQty(StockItemInterface $stockItem, $qty, $summaryQty, $origQty = 0)
    {
        $result = parent::checkQuoteItemQty($stockItem, $qty, $summaryQty, $origQty);
        // Get product for getting more specific out of stock message
        $product = $this->productFactory->create();
        $product->load($stockItem->getProductId());
        $result->setMessage($result->getMessage() . " {$product->getName()} ({$product->getSku()})");
        return $result;
    }
}

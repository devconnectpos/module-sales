<?php

namespace SM\Sales\Model\CatalogInventory;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use SM\Sales\Repositories\OrderManagement;

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

        if (OrderManagement::$FROM_API) {
            $result->setHasError(false);
        }

        return $result;
    }
}

<?php


namespace SM\Sales\Observer;


use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SM\CustomSale\Helper\Data;

/**
 * Class MakeCustomSaleAlwaysSalable
 *
 * @package SM\Sales\Observer
 */
class MakeCustomSaleAlwaysSalable implements ObserverInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getData('product');
        /** @var \Magento\Framework\DataObject $object */
        $object = $observer->getEvent()->getData('salable');

        if (empty($product) || empty($object) || !is_object($object)) {
            return $this;
        }

        if ($product->getSku() === Data::CUSTOM_SALES_PRODUCT_SKU) {
            $object->setIsSalable(true);
            return $this;
        }

        return $this;
    }
}

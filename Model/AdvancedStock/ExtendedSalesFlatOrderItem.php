<?php
declare(strict_types=1);

namespace SM\Sales\Model\AdvancedStock;

use BoostMyShop\AdvancedStock\Model\ExtendedSalesFlatOrderItem as BmsExtendedSalesFlatOrderItem;
use Exception;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * Class ExtendedSalesFlatOrderItem
 * @package SM\Sales\Model\AdvancedStock
 */
class ExtendedSalesFlatOrderItem extends BmsExtendedSalesFlatOrderItem
{
    /**
     * @param Order $order
     * @param Item $orderItem
     * @return $this|BmsExtendedSalesFlatOrderItem
     * @throws Exception
     */
    public function createFromOrderItem($order, $orderItem)
    {
        $warehouseId = $orderItem->getData('warehouse_id');

        $this->setesfoi_order_item_id($orderItem->getId());
        $this->setesfoi_warehouse_id($warehouseId);
        $this->setesfoi_qty_reserved(0);
        $this->setesfoi_qty_to_ship($this->getQuantityToShip());
        $this->save();

        return $this;
    }
}

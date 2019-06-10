<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 31/12/2016
 * Time: 22:26
 */

namespace SM\Sales\Plugin;

use Magento\Backend\Helper\Dashboard\Order;
use Magento\CatalogInventory\Model\StockState;
use SM\Sales\Repositories\OrderManagement;

class AllowCheckOutBackOrder
{

    /**
     * @param \Magento\CatalogInventory\Model\StockState $subject
     * @param                                            $result
     *
     * @return bool
     */
    public function afterCheckQty(StockState $subject, $result)
    {
        return OrderManagement::$ALLOW_BACK_ORDER == true ? OrderManagement::$ALLOW_BACK_ORDER : $result;
    }
}

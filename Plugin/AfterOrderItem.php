<?php
declare(strict_types=1);

namespace SM\Sales\Plugin;

use Magento\Quote\Model\Quote\Address\Item as AddressItem;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Class AfterOrderItem
 * @package SM\Sales\Plugin
 */
class AfterOrderItem
{
    /**
     * @param ToOrderItem $subject
     * @param OrderItemInterface $result
     * @param Item|AddressItem $item
     *
     * @return mixed
     */
    public function afterConvert(ToOrderItem $subject, $result, $item)
    {
        $result->setData('warehouse_id', $item->getDataByKey('warehouse_id'));

        return $result;
    }
}

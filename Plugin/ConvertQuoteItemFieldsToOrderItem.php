<?php

namespace SM\Sales\Plugin;

use Magento\Quote\Model\Quote\Address\Item as AddressItem;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;

class ConvertQuoteItemFieldsToOrderItem
{
    /**
     * @param ToOrderItem              $subject
     * @param OrderItemInterface|Order $orderItem
     * @param Item|AddressItem         $cartItem
     *
     * @return OrderItemInterface
     */
    public function afterConvert($subject, $orderItem, $cartItem)
    {
        if (!$cartItem->getData('serial_number')) {
            return $orderItem;
        }

        $orderItem->setData('serial_number', $cartItem->getData('serial_number'));

        return $orderItem;
    }
}

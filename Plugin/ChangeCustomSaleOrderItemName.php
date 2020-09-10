<?php

namespace SM\Sales\Plugin;

class ChangeCustomSaleOrderItemName
{
    /**
     * @param \Magento\Quote\Model\Quote\Item\ToOrderItem $subject
     * @param \Magento\Sales\Api\Data\OrderItemInterface $result
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return \Magento\Sales\Api\Data\OrderItemInterface
     */
    public function afterConvert(
        \Magento\Quote\Model\Quote\Item\ToOrderItem $subject,
        \Magento\Sales\Api\Data\OrderItemInterface $result,
        \Magento\Quote\Model\Quote\Item $item
    ) {
        if (strpos($item->getProduct()->getSku(), 'PVFCUS') === false) {
            return $result;
        }

        $buyRequest = null;

        if (isset($item->getData('product_order_options')['info_buyRequest'])) {
            $buyRequest = $item->getData('product_order_options')['info_buyRequest'];
        }

        if (!$buyRequest || !isset($buyRequest['custom_sale']) || !isset($buyRequest['custom_sale']['name'])) {
            return $result;
        }

        $result->setName($buyRequest['custom_sale']['name']);

        return $result;
    }
}

<?php

namespace SM\Sales\Observer;

use Magento\Framework\Event\ObserverInterface;

class UpdateQuoteItemComment implements ObserverInterface
{
    
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $item = $observer->getQuoteItem();
    
        $options = $item->getProductOrderOptions();
        if (!$options) {
            $options = $item->getProduct()->getTypeInstance()->getOrderOptions($item->getProduct());
        }
    
        $buyRequest = null;
        
        if (isset($options['info_buyRequest'])) {
            $buyRequest = $options['info_buyRequest'];
        }
    
        if (!$buyRequest || !isset($buyRequest['item_note']) || !$buyRequest['item_note']) {
            return $this;
        }
    
        $item->setProductComment($buyRequest['item_note']);
    }
}

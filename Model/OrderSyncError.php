<?php

namespace SM\Sales\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use SM\Sales\Api\Data\OrderSyncErrorInterface;

class OrderSyncError extends AbstractModel implements OrderSyncErrorInterface, IdentityInterface
{
    const CACHE_TAG = 'sm_order_sync_error';

    protected function _construct()
    {
        $this->_init('SM\Sales\Model\ResourceModel\OrderSyncError');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}

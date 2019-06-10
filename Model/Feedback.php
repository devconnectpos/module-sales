<?php

namespace SM\Sales\Model;

class Feedback extends \Magento\Framework\Model\AbstractModel
    implements \SM\Sales\Api\Data\FeedbackInterface, \Magento\Framework\DataObject\IdentityInterface {

    const CACHE_TAG = 'sm_feedback';

    protected function _construct()
    {
        $this->_init('SM\Sales\Model\ResourceModel\Feedback');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}

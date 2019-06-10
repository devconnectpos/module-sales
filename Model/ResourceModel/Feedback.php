<?php

namespace SM\Sales\Model\ResourceModel;

class Feedback extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {

    protected function _construct()
    {
        $this->_init('sm_feedback', 'id');
    }
}

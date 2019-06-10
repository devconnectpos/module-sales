<?php

namespace SM\Sales\Model\ResourceModel\Feedback;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection {

    protected function _construct()
    {
        $this->_init('SM\Sales\Model\Feedback', 'SM\Sales\Model\ResourceModel\Feedback');
    }
}

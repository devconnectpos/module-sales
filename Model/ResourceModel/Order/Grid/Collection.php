<?php
/**
 * Created by Nomad
 * Date: 10/11/19
 * Time: 4:54 PM
 */

namespace SM\Sales\Model\ResourceModel\Order\Grid;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OriginalCollection;

class Collection extends OriginalCollection
{
    protected function _renderFiltersBefore() {
        $this->addFilterToMap('retail_id', 'main_table.retail_id');
        parent::_renderFiltersBefore();
    }
}

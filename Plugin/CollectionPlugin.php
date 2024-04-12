<?php

namespace SM\Sales\Plugin;

use Magento\Framework\Webapi\Rest\Request;

class CollectionPlugin
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct(
        Request $request
    )
    {
        $this->request = $request;
    }

    /**
     * @param $subject
     * @param $result
     * @return array
     */
    public function afterGetItemsByColumnValue($subject, $result)
    {
        if (!$this->request->getRequestUri()) {
            return $result;
        }
        if (!strpos("xretail/load-order-data", $this->request->getRequestUri())
            || !strpos("xretail/save-order", $this->request->getRequestUri())) {
            foreach ($subject->load() as $item) {
                if ($item->getData("sku") == "custom_sales_product_for_retail") {
                    return [];
                }
            }
        }
        return $result;
    }
}


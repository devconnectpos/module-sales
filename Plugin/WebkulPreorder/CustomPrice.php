<?php

namespace SM\Sales\Plugin\WebkulPreorder;

class CustomPrice
{
    public function aroundRefreshProductPrice(
        $subject,
        callable $proceed,
        $item
    ) {
        if (\SM\Sales\Repositories\OrderManagement::$FROM_API) {
            return $subject;
        }
        return $proceed($item);
    }
}

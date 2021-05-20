<?php

namespace SM\Sales\Plugin;

class AlwaysIsSalable
{
    public function afterIsSalable($subject, $result)
    {
        if (\SM\Sales\Repositories\OrderManagement::$FROM_API) {
            return true;
        }

        return $result;
    }
}

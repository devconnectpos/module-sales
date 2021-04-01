<?php

namespace SM\Sales\Plugin\WebkulPreorder;

class CreditmemoTotal
{
    public function afterCollect(
        $subject,
        $result,
        \Magento\Sales\Model\Order\Creditmemo $creditmemo
    ) {
        if (!\SM\Sales\Repositories\CreditmemoManagement::$FROM_API) {
            return $result;
        }
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - $creditmemo->getOrder()->getPreorderFee());
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - $creditmemo->getOrder()->getPreorderFee());

        return $subject;
    }
}

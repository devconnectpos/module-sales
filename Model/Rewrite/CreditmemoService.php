<?php

namespace SM\Sales\Model\Rewrite;

class CreditmemoService extends \Magento\Sales\Model\Service\CreditmemoService
{
    /**
     * Validates if credit memo is available for refund.
     *
     * @param \Magento\Sales\Api\Data\CreditmemoInterface $creditmemo
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function validateForRefund(\Magento\Sales\Api\Data\CreditmemoInterface $creditmemo)
    {
        if ($creditmemo->getId() && $creditmemo->getState() != \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We cannot register an existing credit memo.')
            );
        }

        $baseOrderRefund = $this->priceCurrency->round(
            $creditmemo->getOrder()->getBaseTotalRefunded() + $creditmemo->getBaseGrandTotal()
        );

        if ($baseOrderRefund > $this->priceCurrency->round($creditmemo->getOrder()->getBaseTotalPaid())) {
            $baseAvailableRefund = $creditmemo->getOrder()->getBaseTotalPaid()
                - $creditmemo->getOrder()->getBaseTotalRefunded();
            $availableRefund = $creditmemo->getOrder()->getTotalPaid()
                - $creditmemo->getOrder()->getTotalRefunded();
            $creditmemo->setBaseGrandTotal($baseAvailableRefund);
            $creditmemo->setGrandTotal($availableRefund);
        }

        return true;
    }
}

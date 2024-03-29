<?php

namespace SM\Sales\Plugin;

use Magento\Sales\Model\Order;

class FixMagentoRewardPointRefundStatus
{
    /**
     * @param \Magento\Reward\Observer\CreditmemoRefund $subject
     * @param callable                                  $proceed
     * @param \Magento\Framework\Event\Observer         $observer
     */
    public function aroundExecute($subject, $proceed, $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        /* @var $order Order */
        $order = $creditmemo->getOrder();
        $refundedAmount = (double)($order->getBaseRwrdCrrncyAmntRefnded() + $creditmemo->getBaseRewardCurrencyAmount());
        $rewardAmount = (double)$order->getBaseRwrdCrrncyAmtInvoiced();
        if ($rewardAmount > 0 && $rewardAmount == $refundedAmount && $order->getGrandTotal() == 0 && $order->getTotalPaid() == 0) {
            $order->setForcedCanCreditmemo(false);
        }

        if ($creditmemo->getBaseRewardCurrencyAmount()) {
            $order->setRewardPointsBalanceRefunded(
                $order->getRewardPointsBalanceRefunded() + $creditmemo->getRewardPointsBalance()
            );
            $order->setRwrdCrrncyAmntRefunded(
                $order->getRwrdCrrncyAmntRefunded() + $creditmemo->getRewardCurrencyAmount()
            );
            $order->setBaseRwrdCrrncyAmntRefnded(
                $order->getBaseRwrdCrrncyAmntRefnded() + $creditmemo->getBaseRewardCurrencyAmount()
            );
            $order->setRewardPointsBalanceRefund(
                $order->getRewardPointsBalanceRefund() + $creditmemo->getRewardPointsBalanceRefund()
            );
        }
    }
}

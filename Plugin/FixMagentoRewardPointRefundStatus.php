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

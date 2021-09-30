<?php

namespace SM\Sales\Plugin;

use SM\Sales\Model\FloatComparator;
use Magento\Sales\Model\Order;

class FixOrderStateBug
{
    /**
     * @var FloatComparator
     */
    private $comparator;

    /**
     * @param FloatComparator $comparator
     */
    public function __construct(FloatComparator $comparator)
    {
        $this->comparator = $comparator;
    }

    /**
     * Checks if Credit Memo is available with Store Credit.
     *
     * @see Order::canCreditmemo()
     * @param Order $subject
     * @param boolean $result
     * @return boolean
     */
    public function afterCanCreditmemo(Order $subject, bool $result): bool
    {
        // process a case only if credit memo can be created
        if (!$result) {
            return $result;
        }

        // process a case only if reward points or customer balance were refunded
        if ($subject->getBaseRwrdCrrncyAmtRefunded() === null
            && $subject->getBaseCustomerBalanceRefunded() === null
        ) {
            return $result;
        }

        $totalInvoiced = $subject->getBaseTotalInvoiced()
            + $subject->getBaseRwrdCrrncyAmtInvoiced()
            + $subject->getBaseCustomerBalanceInvoiced()
            + $subject->getBaseGiftCardsInvoiced();
        $totalRefunded = $subject->getBaseTotalRefunded()
            + $subject->getBaseRwrdCrrncyAmntRefnded()
            + ($subject->getBaseCustomerBalanceInvoiced() ? $subject->getBaseCustomerBalanceRefunded() : 0)
            + $subject->getBaseGiftCardsRefunded();

        if ((float)$totalInvoiced > 0 && $this->comparator->greaterThan($totalInvoiced, $totalRefunded)) {
            return true;
        }

        if ((float)$totalRefunded > 0 && $this->comparator->greaterThanOrEqual($totalRefunded, (float)$subject->getBaseTotalPaid())) {
            return false;
        }

        return true;
    }
}

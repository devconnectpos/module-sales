<?php


namespace SM\Sales\Block\Adminhtml\Order\Totals;


class Deposit extends \Magento\Framework\View\Element\Template
{
	/** @var \Magento\Sales\Model\Order $order */
	protected $order;
	/**
	 * @var \SM\Shift\Api\RetailTransactionRepositoryInterface
	 */
	protected $retailTransactionRepository;
	/**
	 * @var \Magento\Framework\Api\SearchCriteriaBuilder
	 */
	protected $searchCriteriaBuilder;
	/**
	 * @var \Magento\Framework\DataObject\Factory
	 */
	private $factory;
	
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
		\SM\Shift\Api\RetailTransactionRepositoryInterface $retailTransactionRepository,
		\Magento\Framework\DataObject\Factory $factory,
		array $data = []
	) {
		$this->retailTransactionRepository = $retailTransactionRepository;
		$this->searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->factory = $factory;
		
		parent::__construct($context, $data);
	}
	
	public function initTotals()
	{
		$parent      = $this->getParentBlock();
		$this->order = $parent->getOrder();
		
		//ignore completed and closed orders
		if ($this->order->getState() === \Magento\Sales\Model\Order::STATE_COMPLETE
			|| $this->order->getState() === \Magento\Sales\Model\Order::STATE_CLOSED) {
			return $this;
		}
		
		//ignore order has no payment
		if (!$this->order->getPayment()) {
			return $this;
		}
		
		$grandTotal = $this->order->getGrandTotal();
		//ignore order fully invoiced
		if ($grandTotal === $this->order->getTotalPaid()) {
			return $this;
		}
		$notCountedPaymentType = [
		    \SM\Payment\Model\RetailPayment::GIFT_CARD_PAYMENT_TYPE,
		    \SM\Payment\Model\RetailPayment::REWARD_POINT_PAYMENT_TYPE,
		    \SM\Payment\Model\RetailPayment::STORE_CREDIT_PAYMENT_TYPE,
		    \SM\Payment\Model\RetailPayment::REFUND_GC_PAYMENT_TYPE,
		    \SM\Payment\Model\RetailPayment::REFUND_TO_STORE_CREDIT_PAYMENT_TYPE,
        ];
		
		$this->searchCriteriaBuilder->addFilter('order_id', $this->order->getId());
		$this->searchCriteriaBuilder->addFilter('is_purchase', 1);
		$this->searchCriteriaBuilder->addFilter('payment_type', $notCountedPaymentType, 'nin');

		$transactions = $this->retailTransactionRepository->getList($this->searchCriteriaBuilder->create())->getItems();
		
		if (empty($transactions)) {
			return $this;
		}
		
		$depositAmount = 0;
		$baseDepositAmount = 0;
		/** @var \SM\Shift\Api\Data\RetailTransactionInterface $transaction */
		foreach ($transactions as $transaction) {
			$depositAmount += $transaction->getAmount();
			$baseDepositAmount += $transaction->getBaseAmount();
		}
		
		$deposit = $this->factory->create(
			[
				'code'       => 'sm_deposit',
				'strong'     => true,
				'label'      => __('Deposit'),
				'value'      => $depositAmount,
				'base_value' => $baseDepositAmount,
			]
		);
		$parent->addTotal($deposit);
		
		//if the pending amount is equal to 0; do not show pending amount
		if ($grandTotal - $depositAmount == 0) {
			return $this;
		}
		
		$pending = $this->factory->create(
			[
				'code'       => 'sm_pending',
				'strong'     => true,
				'label'      => __('Pending Amount'),
				'value'      => $grandTotal - $depositAmount,
				'base_value' => $this->order->getBaseGrandTotal() - $baseDepositAmount,
			]
		);
		
		$parent->addTotal($pending);
		
		return $this;
	}
}

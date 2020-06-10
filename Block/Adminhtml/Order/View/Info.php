<?php


namespace SM\Sales\Block\Adminhtml\Order\View;


class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Info
{
	/**
	 * @var \Magento\Sales\Api\OrderRepositoryInterface
	 */
	private $orderRepository;
	
	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		\Magento\Framework\Registry $registry, \Magento\Sales\Helper\Admin $adminHelper,
		\Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
		\Magento\Customer\Api\CustomerMetadataInterface $metadata,
		\Magento\Customer\Model\Metadata\ElementFactory $elementFactory,
		\Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
		array $data = []
	) {
		parent::__construct($context, $registry, $adminHelper, $groupRepository, $metadata, $elementFactory, $addressRenderer, $data);
		$this->orderRepository = $orderRepository;
	}
	
	public function getIncrementIdByOrderId($orderId)
	{
		$order = $this->orderRepository->get($orderId);
		
		return $order->getIncrementId();
	}
	
	public function getRefundWithoutReceiptViewUrl($transactionId)
	{
		return $this->getUrl('smrefundwr/transaction/view', ['transaction_id' => $transactionId]);
	}
}

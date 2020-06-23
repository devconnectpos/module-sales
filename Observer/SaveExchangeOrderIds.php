<?php


namespace SM\Sales\Observer;


use SM\Performance\Helper\RealtimeManager;

class SaveExchangeOrderIds implements \Magento\Framework\Event\ObserverInterface
{
	
	/**
	 * @var \Magento\Sales\Api\OrderRepositoryInterface
	 */
	private $orderRepository;
	/**
	 * @var RealtimeManager
	 */
	private $realtimeManager;
	
	public function __construct(\Magento\Sales\Api\OrderRepositoryInterface $orderRepository, \SM\Performance\Helper\RealtimeManager $realtimeManager)
	{
		$this->orderRepository = $orderRepository;
		$this->realtimeManager = $realtimeManager;
	}
	
	/**
	 * @param \Magento\Framework\Event\Observer $observer
	 * @throws \Exception
	 */
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		/** @var \Magento\Sales\Model\Order $order */
		$order = $observer->getEvent()->getData('order');
		
		if (!$order->getData('is_exchange')) {
			return;
		}
		
		if (1 !== $order->getData('cpos_is_new')) {
			return;
		}
		if (!$order->getData('origin_order_id')) {
			return;
		}
		
		$originalOrder = $this->orderRepository->get($order->getData('origin_order_id'));
		
		if (!$originalOrder->getEntityId()) {
			return;
		}
		
		if (!$originalOrder->getData('exchange_order_ids')) {
			$originalOrder->setData('exchange_order_ids', json_encode([$order->getData('retail_id')]));
		} else {
			$exchangedOrderIds = json_decode($originalOrder->getData('exchange_order_ids'), true);
			
			if (in_array($order->getEntityId(), $exchangedOrderIds)) {
				return;
			}
			array_push($exchangedOrderIds, $order->getData('retail_id'));
			$originalOrder->setData('exchange_order_ids', json_encode($exchangedOrderIds));
		}
		
		$order->setData('cpos_is_new', 0);
		$this->orderRepository->save($originalOrder);
		$this->realtimeManager->trigger(RealtimeManager::ORDER_ENTITY, $originalOrder->getEntityId(), RealtimeManager::TYPE_CHANGE_UPDATE);
	}
}

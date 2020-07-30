<?php

namespace SM\Sales\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;

class OrderRepositoryPlugin
{
	
	/**
	 * @param \Emartech\Emarsys\Model\OrderRepositoryPlugin $subject
	 * @param OrderRepository $repository
	 * @param OrderInterface|Order $entity
	 * @return void
	 */
	public function beforeBeforeSave(
		\Emartech\Emarsys\Model\OrderRepositoryPlugin $subject,
		OrderRepository $repository,
		OrderInterface $entity
	) {
		if ($entity->getEntityId() && !$entity->getOrigData(Order::STATE)) {
			/** @var Order $entity */
			$entity->setOrigData(Order::STATE, $entity->getData(Order::STATE));
			$entity->setOrigData(Order::STATUS, $entity->getData(Order::STATUS));
		}
	}
}

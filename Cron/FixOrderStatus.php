<?php

namespace SM\Sales\Cron;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class FixOrderStatus
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * @var State
     */
    protected $appState;

    /**
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    public function __construct(
        State $appState,
        CollectionFactory $collectionFactory,
        OrderResource $orderResource,
        ManagerInterface $eventManager,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderCollectionFactory = $collectionFactory;
        $this->orderResource = $orderResource;
        $this->appState = $appState;
        $this->eventManager = $eventManager;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        try {
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () {
                $collection = $this->orderCollectionFactory->create();

                $collection->addFieldToFilter('status', Order::STATE_CLOSED)
                    ->addFieldToFilter('state', Order::STATE_CLOSED)
                    ->addFieldToFilter('base_grand_total', 0)
                    ->addFieldToFilter('retail_id', ['neq' => 'NULL']);

                $orders = $collection->getItems();

                /** @var Order $order */
                foreach ($orders as $order) {
                    if ($order->hasCreditmemos()) {
                        continue;
                    }

                    if ($order->canShip() || $order->canInvoice()) {
                        $order->setState(Order::STATE_PROCESSING)
                            ->setStatus(Order::STATE_PROCESSING);
                        $this->orderResource->saveAttribute($order, 'state');
                        $this->orderResource->saveAttribute($order, 'status');
                        $this->orderRepository->save($order);
                        $this->eventManager->dispatch('cpos_sales_order_place_after', ['order' => $order]);
                        continue;
                    }

                    $order->setState(Order::STATE_COMPLETE)
                        ->setStatus(Order::STATE_COMPLETE);

                    $this->orderResource->saveAttribute($order, 'state');
                    $this->orderResource->saveAttribute($order, 'status');
                    $this->orderRepository->save($order);
                    $this->eventManager->dispatch('cpos_sales_order_place_after', ['order' => $order]);
                }
            }, []);
        } catch (\Exception $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('Psr\Log\LoggerInterface');
            $logger->critical("Error when fixing order status: {$e->getMessage()}\n{$e->getTraceAsString()}");
        }
    }
}

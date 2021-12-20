<?php

namespace SM\Sales\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixClosedOrderStatus extends Command
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
        OrderRepositoryInterface $orderRepository,
        $name = null
    ) {
        $this->orderCollectionFactory = $collectionFactory;
        $this->orderResource = $orderResource;
        $this->appState = $appState;
        $this->eventManager = $eventManager;
        $this->orderRepository = $orderRepository;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName("cpos:fix_closed_order_status");
        $this->setDescription("Fix orders that have Closed status but does not have credit memo (use for Mr's Leather only)");
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function (InputInterface $input, OutputInterface $output) {
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

                    $output->writeln("Processing order #{$order->getIncrementId()}");

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
            }, [$input, $output]);
        } catch (\Exception $e) {
            $output->writeln("Error when processing orders: {$e->getMessage()}\n{$e->getTraceAsString()}");
        }

        $output->writeln("DONE!");
    }
}

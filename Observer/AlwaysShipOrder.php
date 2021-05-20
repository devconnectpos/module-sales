<?php

namespace SM\Sales\Observer;

use Magento\CatalogInventory\Model\StockState;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use SM\Integrate\Model\WarehouseIntegrateManagement;

class AlwaysShipOrder implements ObserverInterface
{
    /**
     * @var StockState
     */
    protected $stockState;
    /**
     * @var SourceItemsSaveInterface
     */
    protected $sourceItemsSaveInterface;
    /**
     * @var SourceItemInterfaceFactory
     */
    protected $sourceItemFactory;
    /**
     * @var WarehouseIntegrateManagement
     */
    protected $warehouseIntegrateManagement;
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;
    
    public function __construct(
        StockState $stockState,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        SourceItemInterfaceFactory $sourceItemFactory,
        WarehouseIntegrateManagement $warehouseIntegrateManagement,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->stockState = $stockState;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->warehouseIntegrateManagement = $warehouseIntegrateManagement;
        $this->productRepository = $productRepository;
    }
    
    public function execute(Observer $observer)
    {
        $warehouseId = WarehouseIntegrateManagement::getWarehouseId();
        if ($warehouseId == null) {
            return;
        }
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        $storeId = $shipment->getStoreId();
        $items = $shipment->getAllItems();
        if ($items) {
            /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
            foreach ($items as $item) {
                $productId = $item->getProductId();
                $product = $this->productRepository->getById($productId);
                $stockItem = $this->warehouseIntegrateManagement->getStockItem($product, $warehouseId, $storeId);
                if (isset($stockItem['manage_stock']) && $stockItem['manage_stock'] == 0) {
                    continue;
                }
                $qty = $this->stockState->getStockQty($productId);
                $itemQty = $item->getQty();
                if ($qty < $itemQty) {
                    $sourceItem = $this->sourceItemFactory->create();
                    $sourceItem->setSourceCode('default');
                    $sourceItem->setSku($item->getSku());
                    $sourceItem->setQuantity($itemQty);
                    $sourceItem->setStatus(1);
                    $this->sourceItemsSaveInterface->execute([$sourceItem]);
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace SM\Sales\Plugin;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use SM\Integrate\Helper\Data;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\XRetail\Model\OutletRepository;

/**
 * Only for Magento Multi Source Inventory integration
 * Class AllowVirtualProductNegativeQty
 *
 * @package SM\Sales\Plugin
 */
class AllowVirtualProductNegativeQty
{
    const SKU = 'sku';
    const SOURCE_CODE = 'source_code';
    const QUANTITY = 'quantity';
    const STATUS = 'status';

    /**
     * @var \Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionItemInterfaceFactory
     */
    private $sourceSelectionItemFactory;

    /**
     * @var \Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory
     */
    private $sourceSelectionResultFactory;

    /**
     * @var \Magento\InventorySourceSelectionApi\Model\GetInStockSourceItemsBySkusAndSortedSource
     */
    private $getInStockSourceItemsBySkusAndSortedSource;

    /**
     * @var \Magento\InventorySourceSelectionApi\Model\GetSourceItemQtyAvailableInterface
     */
    private $getSourceItemQtyAvailable;

    /**
     * @var \Magento\InventoryApi\Api\SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OutletRepository
     */
    private $outletRepository;

    /**
     * @var \SM\Integrate\Helper\Data
     */
    private $integrationHelper;

    public function __construct(
        Data $integrationHelper,
        ObjectManagerInterface $objectManager,
        OutletRepository $outletRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->outletRepository = $outletRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getInStockSourceItemsBySkusAndSortedSource = $objectManager->create('Magento\InventorySourceSelectionApi\Model\GetInStockSourceItemsBySkusAndSortedSource');
        $this->getSourceItemQtyAvailable = $objectManager->create('Magento\InventorySourceSelectionApi\Model\GetSourceItemQtyAvailableService');
        $this->sourceSelectionItemFactory = $objectManager->create('Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionItemInterfaceFactory');
        $this->sourceSelectionResultFactory = $objectManager->create('Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory');
        $this->sourceItemRepository = $objectManager->create('Magento\Inventory\Model\SourceItemRepository');
    }

    /**
     * @param \Magento\InventorySourceSelectionApi\Model\Algorithms\Result\GetDefaultSortedSourcesResult $subject
     * @param callable                                                                                   $proceed
     * @param \Magento\InventoryApi\Api\Data\SourceInterface[]                                           $sortedSources
     * @param \Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterface                    $inventoryRequest
     */
    public function aroundExecute($subject, $proceed, $inventoryRequest, array $sortedSources)
    {
        if (!$this->integrationHelper->isIntegrateWH() && !$this->integrationHelper->isMagentoInventory()) {
            return $proceed($inventoryRequest, $sortedSources);
        }

        $warehouseId = WarehouseIntegrateManagement::getWarehouseId();
        $outletId = WarehouseIntegrateManagement::getOutletId();

        if (is_null($warehouseId) && is_null($outletId)) {
            return $proceed($inventoryRequest, $sortedSources);
        }

        try {
            $outlet = $this->outletRepository->getById($outletId);

            if ($outlet->getData('allow_out_of_stock') == 0) {
                return $proceed($inventoryRequest, $sortedSources);
            }

            $sourceItemSelections = [];

            $itemsTdDeliver = [];
            foreach ($inventoryRequest->getItems() as $item) {
                $normalizedSku = $this->normalizeSku($item->getSku());
                $itemsTdDeliver[$normalizedSku] = $item->getQty();
            }

            $sortedSourceCodes = [];
            foreach ($sortedSources as $sortedSource) {
                $sortedSourceCodes[] = $sortedSource->getSourceCode();
            }

            $sourceItems = $this->getSourceItemsBySkusAndSortedSource(array_keys($itemsTdDeliver), $sortedSourceCodes);

            foreach ($sourceItems as $sourceItem) {
                $normalizedSku = $this->normalizeSku($sourceItem->getSku());
                $sourceItemQtyAvailable = $this->getSourceItemQtyAvailable->execute($sourceItem);
                $qtyToDeduct = $itemsTdDeliver[$normalizedSku] ?? 0.0;

                $sourceItemSelections[] = $this->sourceSelectionItemFactory->create(
                    [
                        'sourceCode'   => $sourceItem->getSourceCode(),
                        'sku'          => $sourceItem->getSku(),
                        'qtyToDeduct'  => $qtyToDeduct,
                        'qtyAvailable' => $sourceItemQtyAvailable,
                    ]
                );

                // Try to trim the SKU to make sure it is in the list
                if (!isset($itemsTdDeliver[$normalizedSku])) {
                    $normalizedSku = trim($normalizedSku);
                }

                // If we can't even find the SKU, just skip
                if (!isset($itemsTdDeliver[$normalizedSku])) {
                    continue;
                }

                $itemsTdDeliver[$normalizedSku] -= $qtyToDeduct;
            }

            $isShippable = true;
            foreach ($itemsTdDeliver as $itemToDeliver) {
                if (!$this->isZero($itemToDeliver)) {
                    $isShippable = false;
                    break;
                }
            }

            return $this->sourceSelectionResultFactory->create(
                [
                    'sourceItemSelections' => $sourceItemSelections,
                    'isShippable'          => $isShippable,
                ]
            );
        } catch (\Exception $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('Psr\Log\LoggerInterface');
            $logger->info("====> [CPOS] Error when deducting item quantity from stock: {$e->getMessage()}");
            $logger->info($e->getTraceAsString());
        }

        return $proceed($inventoryRequest, $sortedSources);
    }

    private function getSourceItemsBySkusAndSortedSource(array $skus, array $sortedSourceCodes)
    {
        $skus = array_map('strval', $skus);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(self::SKU, $skus, 'in')
            ->addFilter(self::SOURCE_CODE, $sortedSourceCodes, 'in')
            ->create();

        $items = $this->sourceItemRepository->getList($searchCriteria)->getItems();

        $itemsSorting = [];
        foreach ($items as $item) {
            $itemsSorting[] = array_search($item->getSourceCode(), $sortedSourceCodes, true);
        }

        array_multisort($itemsSorting, SORT_NUMERIC, SORT_ASC, $items);
        return $items;
    }

    /**
     * Compare float number with some epsilon
     *
     * @param float $floatNumber
     *
     * @return bool
     */
    private function isZero(float $floatNumber): bool
    {
        return $floatNumber < 0.0000001;
    }

    /**
     * Convert SKU to lowercase
     *
     * Normalize SKU by converting it to lowercase.
     *
     * @param string $sku
     *
     * @return string
     */
    private function normalizeSku(string $sku): string
    {
        return mb_convert_case($sku, MB_CASE_LOWER, 'UTF-8');
    }
}

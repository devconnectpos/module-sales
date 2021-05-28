<?php

declare(strict_types=1);

namespace SM\Sales\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use SM\Integrate\Helper\Data;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\XRetail\Model\OutletRepository;
use SM\XRetail\Repositories\OutletManagement;

/**
 * Only for Magento Multi Source Inventory integration
 * Class RemoveOutOfStockRestriction
 *
 * @package SM\Sales\Plugin
 */
class AllowProductNegativeQty
{
    const STATUS_OUT_OF_STOCK = 0;

    const STATUS_IN_STOCK = 1;

    /**
     * Constant for zero stock quantity value.
     */
    private const ZERO_STOCK_QUANTITY = 0.0;

    /**
     * @var \Magento\InventoryApi\Api\SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var \Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku
     */
    private $getSourceItemBySourceCodeAndSku;

    /**
     * @var \Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var \Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var OutletRepository
     */
    private $outletRepository;

    /**
     * @var Data
     */
    private $integrationHelper;

    public function __construct(
        Data $integrationHelper,
        ObjectManagerInterface $objectManager,
        OutletRepository $outletRepository
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->outletRepository = $outletRepository;
        $this->objectManager = $objectManager;

        $this->sourceItemsSave = $objectManager->create('Magento\Inventory\Model\SourceItem\Command\SourceItemsSave');
        $this->getSourceItemBySourceCodeAndSku = $objectManager->create('Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku');
        $this->getStockItemConfiguration = $objectManager->create('Magento\InventoryConfiguration\Model\GetStockItemConfiguration');
        $this->getStockBySalesChannel = $this->objectManager->create('Magento\InventorySales\Model\GetStockBySalesChannel');
    }

    /**
     * @param \Magento\InventorySourceDeductionApi\Model\SourceDeductionService          $subject
     * @param callable                                                                   $proceed
     * @param \Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface $sourceDeductionRequest
     *
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Validation\ValidationException
     * @throws \Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException
     */
    public function aroundExecute($subject, $proceed, $sourceDeductionRequest)
    {
        if (!$this->integrationHelper->isIntegrateWH() && !$this->integrationHelper->isMagentoInventory()) {
            $proceed($sourceDeductionRequest);

            return;
        }

        $warehouseId = WarehouseIntegrateManagement::getWarehouseId();
        $outletId = WarehouseIntegrateManagement::getOutletId();

        if (is_null($warehouseId) && is_null($outletId)) {
            $proceed($sourceDeductionRequest);

            return;
        }

        try {
            $outlet = $this->outletRepository->getById($outletId);

            if ($outlet->getData('allow_out_of_stock') == 0) {
                $proceed($sourceDeductionRequest);

                return;
            }

            $sourceItems = [];
            $sourceCode = $sourceDeductionRequest->getSourceCode();

            if ($sourceCode == "") {
                $sourceCode = $warehouseId;
            }

            $salesChannel = $sourceDeductionRequest->getSalesChannel();

            $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
            foreach ($sourceDeductionRequest->getItems() as $item) {
                $itemSku = $item->getSku();
                $qty = $item->getQty();
                $stockItemConfiguration = $this->getStockItemConfiguration->execute(
                    $itemSku,
                    $stockId
                );

                if (!$stockItemConfiguration->isManageStock()) {
                    //We don't need to Manage Stock
                    continue;
                }

                $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($sourceCode, $itemSku);
                $sourceItem->setQuantity($sourceItem->getQuantity() - $qty);
                $stockStatus = $this->getSourceStockStatus(
                    $stockItemConfiguration,
                    $sourceItem
                );
                $sourceItem->setStatus($stockStatus);
                $sourceItems[] = $sourceItem;
            }

            if (!empty($sourceItems)) {
                $this->sourceItemsSave->execute($sourceItems);
            }
        } catch (\Exception $e) {
            $proceed($sourceDeductionRequest);

            return;
        }
    }

    /**
     * Get source item stock status after quantity deduction.
     *
     * @param \Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface $stockItemConfiguration
     * @param \Magento\InventoryApi\Api\Data\SourceItemInterface                          $sourceItem
     *
     * @return int
     */
    private function getSourceStockStatus($stockItemConfiguration, $sourceItem): int
    {
        $sourceItemQty = $sourceItem->getQuantity() ?: self::ZERO_STOCK_QUANTITY;

        return $sourceItemQty <= $stockItemConfiguration->getMinQty() && !$stockItemConfiguration->getBackorders()
            ? self::STATUS_OUT_OF_STOCK
            : self::STATUS_IN_STOCK;
    }
}

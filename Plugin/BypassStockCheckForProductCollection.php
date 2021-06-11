<?php


namespace SM\Sales\Plugin;

use Magento\Framework\ObjectManagerInterface;
use SM\Integrate\Helper\Data;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\XRetail\Model\OutletRepository;

/**
 * Only for Magento Inventory integration
 * Class BypassStockCheckForProductCollection
 *
 * @package SM\Sales\Plugin
 */
class BypassStockCheckForProductCollection
{
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
        OutletRepository $outletRepository
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->outletRepository = $outletRepository;
    }

    /**
     * @param \Magento\CatalogInventory\Helper\Stock $subject
     * @param $proceed
     * @param $result
     */
    public function aroundAddInStockFilterToCollection($subject, $proceed, $result)
    {
        $this->bypassStockFilter($proceed, $result);
    }

    /**
     * @param $subject
     * @param $proceed
     * @param $result
     */
    public function aroundAddIsInStockFilterToCollection($subject, $proceed, $result)
    {
        $this->bypassStockFilter($proceed, $result);
    }

    /**
     * @param $proceed
     * @param $result
     */
    private function bypassStockFilter($proceed, $result)
    {
        if (!$this->integrationHelper->isIntegrateWH() && !$this->integrationHelper->isMagentoInventory()) {
            $proceed($result);

            return;
        }

        $outletId = WarehouseIntegrateManagement::getOutletId();

        if (is_null($outletId)) {
            $proceed($result);

            return;
        }

        try {
            $outlet = $this->outletRepository->getById($outletId);

            if ($outlet->getData('allow_out_of_stock') == 0) {
                $proceed($result);

                return;
            }
        } catch (\Exception $e) {
            $proceed($result);

            return;
        }
    }
}

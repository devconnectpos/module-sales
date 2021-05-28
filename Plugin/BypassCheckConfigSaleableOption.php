<?php

namespace SM\Sales\Plugin;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use SM\Integrate\Helper\Data;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\XRetail\Model\OutletRepository;

/**
 * Only for Magento Inventory
 *
 * Class BypassCheckConfigSaleableOption
 *
 * @package SM\Sales\Plugin
 */
class BypassCheckConfigSaleableOption
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
     * @param              $subject
     * @param              $proceed
     * @param Configurable $configType
     * @param array        $products
     *
     * @return array|mixed
     */
    public function aroundAfterGetUsedProducts($subject, $proceed, Configurable $configType, array $products)
    {
        if (!$this->integrationHelper->isIntegrateWH() && !$this->integrationHelper->isMagentoInventory()) {
            return $proceed($configType, $products);
        }

        $outletId = WarehouseIntegrateManagement::getOutletId();

        if (is_null($outletId)) {
            return $proceed($configType, $products);
        }

        try {
            $outlet = $this->outletRepository->getById($outletId);

            if ($outlet->getData('allow_out_of_stock') == 0) {
                return $proceed($configType, $products);
            }

            return $products;
        } catch (\Exception $e) {
            return $proceed($configType, $products);
        }
    }
}

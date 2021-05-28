<?php


namespace SM\Sales\Plugin;


use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use SM\Integrate\Helper\Data;
use SM\Integrate\Model\WarehouseIntegrateManagement;
use SM\XRetail\Model\OutletRepository;

/**
 * Only for magento inventory
 *
 * Class SkipConfigChildProductStockFilter
 *
 * @package SM\Sales\Plugin
 */
class SkipConfigChildProductStockFilter
{
    /**
     * @var OutletRepository
     */
    private $outletRepository;

    /**
     * @var Data
     */
    private $integrationHelper;

    /**
     * @var ProductAttributeRepositoryInterface|null
     */
    private $productAttributeRepository;

    /**
     * @var SearchCriteriaBuilder|null
     */
    private $searchCriteriaBuilder;

    /**
     * @var Config
     */
    private $catalogConfig;

    /**
     * Cache key for used products
     *
     * @var string
     */
    protected $_usedProducts = '_cache_instance_products';

    public function __construct(
        Data $integrationHelper,
        OutletRepository $outletRepository,
        ProductAttributeRepositoryInterface $productAttributeRepository = null,
        SearchCriteriaBuilder $searchCriteriaBuilder = null
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->outletRepository = $outletRepository;
        $this->productAttributeRepository = $productAttributeRepository
            ?:
            ObjectManager::getInstance()->get(ProductAttributeRepositoryInterface::class);
        $this->searchCriteriaBuilder = $searchCriteriaBuilder
            ?:
            ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
    }

    /**
     * @param \Magento\ConfigurableProduct\Model\Product\Type\Configurable $subject
     * @param                                                              $proceed
     * @param                                                              $product
     * @param null                                                         $requiredAttributeIds
     *
     * @return mixed
     */
    public function aroundGetUsedProducts($subject, $proceed, $product, $requiredAttributeIds = null)
    {
        if (!$this->integrationHelper->isIntegrateWH() && !$this->integrationHelper->isMagentoInventory()) {
            return $proceed($product, $requiredAttributeIds);
        }

        $outletId = WarehouseIntegrateManagement::getOutletId();

        if (is_null($outletId)) {
            return $proceed($product, $requiredAttributeIds);
        }

        try {
            $outlet = $this->outletRepository->getById($outletId);

            if ($outlet->getData('allow_out_of_stock') == 0) {
                return $proceed($product, $requiredAttributeIds);
            }

            if (!$product->hasData($this->_usedProducts)) {
                $collection = $this->getConfiguredUsedProductCollection($subject, $product, true, $requiredAttributeIds);
                $usedProducts = array_values($collection->getItems());
                $product->setData($this->_usedProducts, $usedProducts);
            }

            return $product->getData($this->_usedProducts);
        } catch (\Exception $e) {
            return $proceed($product, $requiredAttributeIds);
        }
    }

    /**
     * Prepare collection for retrieving sub-products of specified configurable product
     * Retrieve related products collection with additional configuration
     *
     * @param \Magento\ConfigurableProduct\Model\Product\Type\Configurable $subject
     * @param \Magento\Catalog\Model\Product                               $product
     * @param bool                                                         $skipStockFilter
     * @param array                                                        $requiredAttributeIds Attributes to include in the select
     *
     * @return \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getConfiguredUsedProductCollection(
        $subject,
        \Magento\Catalog\Model\Product $product,
        $skipStockFilter = true,
        $requiredAttributeIds = null
    ) {
        $collection = $subject->getUsedProductCollection($product);

        if ($skipStockFilter) {
            $collection->setFlag('has_stock_status_filter', true);
        }

        $attributesForSelect = $this->getAttributesForCollection($subject, $product);
        if ($requiredAttributeIds) {
            $this->searchCriteriaBuilder->addFilter('attribute_id', $requiredAttributeIds, 'in');
            $requiredAttributes = $this->productAttributeRepository
                ->getList($this->searchCriteriaBuilder->create())->getItems();
            $requiredAttributeCodes = [];
            foreach ($requiredAttributes as $requiredAttribute) {
                $requiredAttributeCodes[] = $requiredAttribute->getAttributeCode();
            }
            $attributesForSelect = array_unique(array_merge($attributesForSelect, $requiredAttributeCodes));
        }
        $collection
            ->addAttributeToSelect($attributesForSelect)
            ->addFilterByRequiredOptions()
            ->setStoreId($product->getStoreId());

        $collection->addMediaGalleryData();
        $collection->addTierPriceData();

        return $collection;
    }

    /**
     * Get Config instance
     * @return Config
     */
    private function getCatalogConfig()
    {
        if (!$this->catalogConfig) {
            $this->catalogConfig = ObjectManager::getInstance()->get(Config::class);
        }
        return $this->catalogConfig;
    }

    /**
     * @param \Magento\ConfigurableProduct\Model\Product\Type\Configurable $subject
     * @return array
     */
    private function getAttributesForCollection($subject, \Magento\Catalog\Model\Product $product)
    {
        $productAttributes = $this->getCatalogConfig()->getProductAttributes();

        $requiredAttributes = [
            'name',
            'price',
            'weight',
            'image',
            'thumbnail',
            'status',
            'visibility',
            'media_gallery'
        ];

        $usedAttributes = array_map(
            function($attr) {
                return $attr->getAttributeCode();
            },
            $subject->getUsedProductAttributes($product)
        );

        return array_unique(array_merge($productAttributes, $requiredAttributes, $usedAttributes));
    }
}

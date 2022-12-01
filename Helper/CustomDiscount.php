<?php

namespace SM\Sales\Helper;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;

class CustomDiscount
{
    const CATEGORY_ID_APPLY_CUSTOM_DISCOUNT = 1196;
    const CUSTOM_DISCOUNT_PRICE = 49.5;
    /**
     * @var ModuleManager
     */
    protected $moduleManager;
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @param ModuleManager $moduleManager
     * @param ProductRepository $productRepository
     */
    public function __construct(
        ModuleManager        $moduleManager,
        ProductRepository    $productRepository
    )
    {
        $this->moduleManager = $moduleManager;
        $this->productRepository = $productRepository;
    }

    /**
     * @return bool
     */
    public function hasVsourzCustomDiscountModule(): bool
    {
        return $this->moduleManager->isEnabled('Vsourz_Customdiscount');
    }

    /**
     * @param array $listProductData
     * @return array
     * @throws NoSuchEntityException
     */
    public function handleCustomDiscountApplication(array $listProductData)
    {
        if ($this->hasVsourzCustomDiscountModule()) {
            $listProductApplyDiscount = [];
            $productApplyDiscountAfter = [];
            $result = [];
            $count = 0;
            if (count($listProductData) > 0) {
                foreach ($listProductData as $item) {
                    if ($this->checkProductExistInCategory($item['product_id'])) {
                        $listProductApplyDiscount[] = $item;
                    } else {
                        $result[] = $item;
                    }
                }

                if (count($listProductApplyDiscount) > 0) {
                    foreach ($listProductApplyDiscount as $productData) {
                        ++$count;
                        if ($count <= 2 && count($listProductApplyDiscount) > 1) {
                            $productData['custom_price'] = self::CUSTOM_DISCOUNT_PRICE;
                        }
                        $productApplyDiscountAfter[] = $productData;
                    }
                }
            }
            return array_merge($result, $productApplyDiscountAfter);
        }

        return $listProductData;
    }

    /**
     * @param $productId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function checkProductExistInCategory($productId): bool
    {
        $product = $this->productRepository->getById($productId);
        $categoryIds = $product->getCategoryIds();
        if (in_array(self::CATEGORY_ID_APPLY_CUSTOM_DISCOUNT, $categoryIds)) {
            return true;
        }
        return false;
    }
}

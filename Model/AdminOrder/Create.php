<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 04/12/2016
 * Time: 11:15
 */

namespace SM\Sales\Model\AdminOrder;

/**
 * Class Create
 *
 * @package SM\Sales\Model\AdminOrder
 */

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use SM\Sales\Helper\Data;
use SM\Sales\Repositories\OrderManagement;

/**
 * Class Create
 *
 * @package SM\Sales\Model\AdminOrder
 */
class Create extends \Magento\Sales\Model\AdminOrder\Create
{

    /**
     * @var \SM\CustomSale\Helper\Data
     */
    protected $customSaleHelper;

    protected $checkSplitItem = [];

    /**
     * Override to output error as json
     *
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _validate()
    {
        $customerId = $this->getSession()->getCustomerId();
        if (is_null($customerId)) {
            throw new LocalizedException(__('Please select a customer'));
        }

        if (!$this->getSession()->getStore()->getId()) {
            throw new LocalizedException(__('Please select a store'));
        }
        $items = $this->getQuote()->getAllItems();

        if (is_array($items) && count($items) == 0) {
            $this->_errors[] = __('Please specify order items.');
        }

        foreach ($items as $item) {
            $messages = $item->getMessage(false);
            if ($item->getHasError() && is_array($messages) && !empty($messages)) {
                $this->_errors = array_merge($this->_errors, $messages);
            }
        }

        if (!$this->getQuote()->isVirtual()) {
            $addressHasShippingMethod = $this->getQuote()->getShippingAddress()->getShippingMethod();
            $requestedShippingMethod = $this->getRegistry()->registry('retail_shipping_method');
            if (!$addressHasShippingMethod) {
                if (empty($requestedShippingMethod)) {
                    $this->_errors[] = __('Please specify a shipping method.');
                } else {
                    $this->_errors[] = __('Invalid configuration for shipping method %1, the method may restrict your customer shipping address state or country.', $requestedShippingMethod);
                }
            }
        }

        if (!$this->getQuote()->getPayment()->getMethod()) {
            $this->_errors[] = __('Please specify a payment method.');
        } else {
            $method = $this->getQuote()->getPayment()->getMethodInstance();
            if (!$method->isAvailable($this->getQuote())) {
                $this->_errors[] = __('This payment method is not available.');
            } else {
                try {
                    $method->validate();
                } catch (LocalizedException $e) {
                    $this->_errors[] = $e->getMessage();
                }
            }
        }
        if (!empty($this->_errors)) {
            throw new Exception(json_encode($this->_errors));
        }

        return $this;
    }

    /**
     * Override to get product_id
     *
     * @param array $products
     *
     * @return $this
     * @throws \Exception
     */
    public function addProducts(array $products)
    {
        foreach ($products as $config) {
            if (isset($config['qty'])) {
                $config['qty'] = (double)$config['qty'];
            } else {
                $config['qty'] = 1;
            }
            if (!isset($config['product_id'])) {
                throw new Exception(__("Not found product ID"));
            }
            $this->addProduct($config['product_id'], $config);
        }

        return $this;
    }

    protected $bundleOptionCustomPrice = [];

    /**
     * Override for add custom sale data
     *
     * @param int|\Magento\Catalog\Model\Product $product
     * @param int                                $config
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
	public function addProduct($product, $config = 1)
	{
		if (!is_array($config) && !$config instanceof DataObject) {
			$config = ['qty' => $config];
		}
		$config = new DataObject($config);

		if (!$product instanceof Product) {
			$productId = $product;
			$product   = $this->_objectManager->create(
				'Magento\Catalog\Model\Product'
			)->setStore(
				$this->getSession()->getStore()
			)->setStoreId(
				$this->getSession()->getStoreId()
			)->load(
				$product
			);
			if (!$product->getId()) {
				throw new LocalizedException(
					__('We could not add a product to cart by the ID "%1".', $productId)
				);
			}
		}
		$this->attachDataSupportSplitItem($product);
		$this->attachCustomSaleData($product, $config);

		$item = $this->quoteInitializer->init($this->getQuote(), $product, $config);

		if (is_string($item)) {
			throw new LocalizedException(__($item));
		}

        // Set extra data like serial number to the quote item and remove it from item buy request
        if (is_object($config) && $config instanceof DataObject) {
            $item->setData('serial_number', $config->getData('serial_number'));
        }

		$item->checkData();
		$this->setRecollect(true);

		$integrateHelper = $this->_objectManager->get("SM\\Integrate\\Helper\\Data");
		$typeId = $item->getProduct()->getTypeId();
		if($typeId == 'bundle' && $integrateHelper->isExistKensiumCart())
		{
			$this->bundleOptionCustomPrice = [];

			$bundlePrice = $this->calculateBundlePrice($item, $product);

			$item->setCustomPrice($bundlePrice);
			$item->setOriginalCustomPrice($bundlePrice);
			$item->getProduct()->setIsSuperMode(true);

			//set custom price for bundle children
			$children = $item->getChildren();
			$i = 0;
			foreach ($children as $child) {
				$child->setCustomPrice($this->bundleOptionCustomPrice[$i]);
				$child->setOriginalCustomPrice($this->bundleOptionCustomPrice[$i]);
				$child->getProduct()->setIsSuperMode(true);
				$i++;
			}
		}

		return $this;
	}

	protected function calculateBundlePrice($item, $product)
	{
		$infoBuyRequest = $item->getBuyRequest()->toArray();
		$bundleOptions = $this->resolveBundleOptions($product, $infoBuyRequest['bundle_option']);
		$productId = $product->getId();
		$connection = $this->_objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();

		//init base price and final price, will update through the foreach loop base on options
		$basePrice = $item->getProduct()->getPrice();
		$finalPrice = $basePrice;

		$i = 0;
		foreach($bundleOptions as $optionId => $selectionId) {
			if (is_array($optionId)) {
				$optionId = $optionId[0];
			}
			if (is_array($selectionId)) {
				if (!empty($selectionId)) {
					$selectionId = $selectionId[0];
				} else {
					$selectionId = null;
				}
			}

			$isAffect = $this->checkOptionAffectBasePrice($connection, $optionId);

			if (null === $selectionId) {
				$optionPrice = 0;
				$priceType = 0;
			} else {
				$optionPrice = $this->getOptionPrice($connection, $optionId, $selectionId, $productId);
				$priceType = $this->getOptionPriceType($connection, $optionId, $selectionId, $productId);
			}

			if($priceType != 0) { //type percent
				$optionPrice = ($optionPrice * $basePrice / 100);
			}

			//store bundle option custom price
			$this->bundleOptionCustomPrice[$i] = $optionPrice;
			$i++;

			//update final price
			$finalPrice += $optionPrice;

			if ($isAffect == 1) { //option affects base price
				//update base price
				$basePrice += $optionPrice;
			}
		}

		return $finalPrice;
	}

	protected function checkOptionAffectBasePrice($connection, $optionId)
	{
		return $connection->fetchOne("SELECT affect_base_price FROM catalog_product_bundle_option where option_id = '" . $optionId . "'");
	}

	protected function getOptionPrice($connection, $optionId, $selectionId, $productId)
	{
		return $connection->fetchOne("SELECT selection_price_value FROM catalog_product_bundle_selection WHERE selection_id = '" . $selectionId . "' AND option_id = '" . $optionId . "' AND parent_product_id = '" . $productId . "' ");
	}

	protected function getOptionPriceType($connection, $optionId, $selectionId, $productId)
	{
		return $connection->fetchOne("SELECT selection_price_type FROM catalog_product_bundle_selection WHERE selection_id = '" . $selectionId . "' AND option_id = '" . $optionId . "' AND parent_product_id = '" . $productId . "' ");
	}

	/**
	 * @param \Magento\Catalog\Model\Product $product
	 * @param array $originalBundleOptions
	 * @return array
	 */
	protected function resolveBundleOptions($product, $originalBundleOptions)
    {
	    $this->resetProductInBlock($product);
	    $this->_objectManager->get('\Magento\Catalog\Helper\Product')->setSkipSaleableCheck(true);
	    $options       = $this->getBundleBlock()->decorateArray($this->getBundleBlock()->getOptions());
	    $outputOptions = [];
	    foreach ($options as $option) {
		    $optionData               = $option->getData();
		    $outputOptions[]          = $optionData;
	    }

	    $result = [];
	    foreach ($outputOptions as $outputOption) {
		    $resultKey = $outputOption['option_id'];
		    foreach ($originalBundleOptions as $key => $value) {
			   if ($resultKey == $key) {
			   	$result[$resultKey] = $value;
			   }
		    }
	    }
	    return $result;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return $this
     */
    protected function attachDataSupportSplitItem(Product $product)
    {
        if (isset($this->checkSplitItem[$product->getId()])) {
            $retailConfig = $this->_objectManager->get("SM\\XRetail\\Helper\\Data");
            // add to the additional options array
            $additionalOptions = [];
            if ($additionalOption = $product->getCustomOption('additional_options')) {
                $additionalOptions = (array)$retailConfig->unserialize($additionalOption->getValue());
            }
            $additionalOptions[] = [
                'label' => 'Item count',
                'value' => ++Data::$ITEM_COUNT
            ];
            $product->addCustomOption('additional_options', $retailConfig->serialize($additionalOptions));
        } else {
            $this->checkSplitItem[$product->getId()] = true;
        }

        return $this;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param                                $config
     *
     * @return $this
     */
    protected function attachCustomSaleData(Product $product, &$config)
    {
        if (!OrderManagement::$ORDER_HAS_CUSTOM_SALE) {
            return $this;
        }
        $retailConfig = $this->_objectManager->get("SM\\XRetail\\Helper\\Data");
        if (is_null($this->customSaleHelper)) {
            /** @var \SM\CustomSale\Helper\Data $customSaleHelper */
            $this->customSaleHelper = $this->_objectManager->get("SM\\CustomSale\\Helper\\Data");
        }
        if ($product->getId() != $this->customSaleHelper->getCustomSaleId()) {
            return $this;
        }
        // add to the additional options array
        $additionalOptions = [];
        if ($additionalOption = $product->getCustomOption('additional_options')) {
            $additionalOptions = (array)$retailConfig->unserialize($additionalOption->getValue());
        }
        if (is_null($configCustomSale = $config->getData("custom_sale"))) {
            $configCustomSale = [];
        }
        $additionalOptions[] = [
            'label' => 'name',
            'value' => isset($configCustomSale['name']) ? $configCustomSale['name'] : "Unknown Name"
        ];
        $additionalOptions[] = $configCustomSale[] = [
            'label' => '#custom_sale',
            'value' => ++\SM\CustomSale\Helper\Data::$COUNT
        ];

        if (isset($config['custom_sale']) && isset($config['custom_sale']['tax_class_id'])) {
            $product->setData('tax_class_id', $config['custom_sale']['tax_class_id']);
        }

        $product->addCustomOption('additional_options', $retailConfig->serialize($additionalOptions));

        return $this;
    }

    protected function getBundleBlock()
    {
	    return $this->_objectManager->create(
		    '\Magento\Bundle\Block\Adminhtml\Catalog\Product\Composite\Fieldset\Bundle'
	    );
    }

	/**
	 * @return \Magento\Framework\Registry
	 */
	public function getRegistry()
	{
		return $this->_coreRegistry;
	}

	/**
	 * @param $product
	 *
	 * @return $this
	 */
	protected function resetProductInBlock($product)
	{
		$this->getRegistry()->unregister('current_product');
		$this->getRegistry()->unregister('product');
		$this->getRegistry()->register('current_product', $product);
		$this->getRegistry()->register('product', $product);

		return $this;
	}
}

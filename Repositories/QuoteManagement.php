<?php
declare(strict_types=1);

namespace SM\Sales\Repositories;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use SM\Core\Api\Data\XQuote;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Customer\Model\CustomerFactory;

/**
 * Class QuoteManagement
 * @package SM\Sales\Repositories
 */
class QuoteManagement extends ServiceAbstract
{
    /**
     * @var QuoteCollection
     */
    protected $_quoteCollection;

    /**
     * @var CustomerFactory
     */
    protected $_customerFactory;
    
    /**
     * @var OrderHistoryManagement
     */
    protected $_orderHistoryManagement;
    
    /**
     * QuoteManagement constructor.
     * @param RequestInterface $requestInterface
     * @param DataConfig $dataConfig
     * @param StoreManagerInterface $storeManager
     * @param QuoteCollection $quoteCollection
     * @param CustomerFactory $customerFactory
     * @param OrderHistoryManagement $orderHistoryManagement
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        QuoteCollection $quoteCollection,
        CustomerFactory $customerFactory,
        OrderHistoryManagement $orderHistoryManagement
    ) {
        $this->_quoteCollection = $quoteCollection;
        $this->_customerFactory = $customerFactory;
        $this->_orderHistoryManagement = $orderHistoryManagement;

        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getOnlineCarts()
    {
        $quotes = [];
        $searchCriteria = $this->getSearchCriteria();
        $quoteCollection = $this->prepareQuoteCollection($searchCriteria);

        /** @var Quote $quoteModel */
        foreach ($quoteCollection as $quoteModel) {
            if ((int) $quoteModel->getItemsCount() === 0) {
                continue;
            }
            
            $quote = new XQuote();
            $quote->addData($quoteModel->getData());

            $quote->setData('items', $this->_orderHistoryManagement->getOrderItemData($quoteModel->getAllVisibleItems()));
            $quote->setData('customer', $this->prepareQuoteCustomer($quoteModel));
            $quote->setData('billing_address', $quoteModel->getBillingAddress()->getData());
            $quote->setData('currency', $quoteModel->getCurrency()->getData());

            $quotes[] = $quote;
        }

        return $this->getSearchResult()
            ->setItems($quotes)
            ->setLastPageNumber($quoteCollection->getLastPageNumber())
            ->setTotalCount($quoteCollection->getSize())
            ->getOutput();
    }

    /**
     * @param DataObject $searchCriteria
     * @return Collection
     */
    public function prepareQuoteCollection($searchCriteria)
    {
        $pageSize = $searchCriteria->getData('pageSize');
        $currentPage = $searchCriteria->getData('currentPage');
        $storeId = $searchCriteria->getData('storeId');
        $customerId = $searchCriteria->getData('customerId');

        $quoteCollection = $this->_quoteCollection->create();
        $quoteCollection->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('retail_id', ['null' => true])
            ->addFieldToFilter('outlet_id', ['null' => true]);

        return $quoteCollection->setPageSize($pageSize)->setCurPage($currentPage)->load();
    }

    /**
     * @param Quote $quote
     * @return array
     */
    public function prepareQuoteCustomer($quote)
    {
        $customer = $this->_customerFactory->create();
        $customer->load($quote->getCustomerId());
        $customer->unsetData([
            'password_hash',
            'rp_token',
            'rp_token_created_at',
            'confirmation',
            'failures_num',
            'first_failure',
            'lock_expires',
        ]);

        return $customer->getData();
    }
}

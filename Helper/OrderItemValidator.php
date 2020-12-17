<?php

declare(strict_types=1);

namespace SM\Sales\Helper;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderItemRepositoryInterface;

/**
 * Class OrderItemValidator
 *
 * @package SM\Sales\Helper
 */
class OrderItemValidator
{
    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $criteriaBuilder;

    public function __construct(
        OrderItemRepositoryInterface $orderItemRepository,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        SearchCriteriaBuilder $criteriaBuilder
    ) {
        $this->orderItemRepository = $orderItemRepository;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    /**
     * @param string $serialNumber
     * @param int    $itemId
     *
     * @return bool
     */
    public function validateSerialNumber(string $serialNumber, $itemId = null)
    {
        // Check for unique serial number
        $filter1 = $this->filterBuilder->setField('serial_number')
            ->setConditionType('eq')
            ->setValue($serialNumber)
            ->create();
        $group1 = $this->filterGroupBuilder->addFilter($filter1)->create();
        $filterGroups = [$group1];

        if ($itemId) {
            $filter2 = $this->filterBuilder->setField('item_id')
                ->setConditionType('neq')
                ->setValue($itemId)
                ->create();
            $group2 = $this->filterGroupBuilder->addFilter($filter2)->create();
            $filterGroups[] = $group2;
        }

        $searchCriteria = $this->criteriaBuilder
            ->setFilterGroups($filterGroups)
            ->setCurrentPage(1)
            ->setPageSize(1)
            ->create();
        $existingItems = $this->orderItemRepository->getList($searchCriteria)->getItems();

        return (count($existingItems) == 0);
    }
}

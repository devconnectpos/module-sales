<?php

namespace SM\Sales\Repositories;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\StoreManagerInterface;
use SM\Sales\Helper\OrderItemValidator;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;

/**
 * Class OrderItemManagement
 *
 * @package SM\Sales\Repositories
 */
class OrderItemManagement extends ServiceAbstract
{
    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;

    /**
     * @var OrderHistoryManagement
     */
    protected $orderHistoryManagement;

    /**
     * @var OrderItemValidator
     */
    protected $orderItemValidator;

    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        OrderItemRepositoryInterface $orderItemRepository,
        OrderHistoryManagement $orderHistoryManagement,
        OrderItemValidator $orderItemValidator
    ) {
        $this->orderItemRepository = $orderItemRepository;
        $this->orderItemValidator = $orderItemValidator;
        $this->orderHistoryManagement = $orderHistoryManagement;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @throws \Exception
     */
    public function updateOrderItem()
    {
        $data = $this->getRequest()->getParams()['item_data'];
        $itemId = $data['item_id'] ?? 0;

        if (!$itemId) {
            throw new \Exception(__('Please specify order item ID'));
        }

        if (isset($data['serial_number']) && !$this->orderItemValidator->validateSerialNumber($data['serial_number'], $itemId)) {
            throw new AlreadyExistsException(__('There exists an order item with the same serial number %1!', $data['serial_number']));
        }

        /** @var Item $item */
        $item = $this->orderItemRepository->get($itemId);
        $item->addData($data);
        $item = $this->orderItemRepository->save($item);

        return $this->orderHistoryManagement->getIndividualOrderItemData($item, $item->getStoreId());
    }
}

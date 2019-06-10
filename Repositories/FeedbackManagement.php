<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 19/01/2017
 * Time: 14:47
 */

namespace SM\Sales\Repositories;


use Magento\Framework\DataObject;
use SM\Core\Api\Data\Feedback;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use Magento\Framework\App\RequestInterface;
use SM\XRetail\Helper\DataConfig;
use Magento\Store\Model\StoreManagerInterface;
use SM\Sales\Model\ResourceModel\Feedback\CollectionFactory;
use SM\Sales\Model\FeedbackFactory;
/**
 * Class FeedbackManagement
 *
 * @package SM\Sales\Repositories
 */
class FeedbackManagement extends ServiceAbstract {

    /**
     * @var \SM\Sales\Model\ResourceModel\Feedback\CollectionFactory
     */
    protected $feedbackCollectionFactory;
    /**
     * @var \SM\XRetail\MediaLibrary\MediaLibraryFactory
     */
    protected $feedbackFactory;

    /**
     * MediaLibrabyManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                   $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                             $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface                $storeManager
     * @param \SM\XRetail\Model\ResourceModel\MediaLibrary\CollectionFactory $mediaLibraryCollectionFactory,
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        CollectionFactory $feedbackCollectionFactory,
        FeedbackFactory $feedbackFactory
    )
    {
        $this->feedbackFactory           = $feedbackFactory;
        $this->feedbackCollectionFactory = $feedbackCollectionFactory;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return array
     */
    public function getFeedbackData()
    {
        return $this->load($this->getSearchCriteria())->getOutput();
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function save()
    {
        $data = $this->getRequestData();

        /** @var \SM\Sales\Model\Feedback $feedback */
        $feedback = $this->feedbackFactory->create();
        $id      = $data->getId();
        if ($id) {
            $feedback->load($id);
            if (!$feedback->getId())
                throw new \Exception("Can't find feedback");
        }
        $data->unsetData('id');

        $feedback->addData($data->getData())->save();

        $searchCriteria = new DataObject(
            [
                'ids' => $feedback->getId()
            ]);

        return $this->load($searchCriteria)->getOutput();
    }

    public function delete()
    {
        $data = $this->getRequestData();
        if ($id = $data->getData('id')) {
            /** @var \SM\Sales\Model\Feedback $feedback */
            $feedback = $this->feedbackFactory->create();
            $feedback->load($id);
            if (!$feedback->getId()) {
                throw new \Exception("Can not find feedback data");
            } else {
                $feedback->delete();
            }
        } else {
            throw new \Exception("Please define id");
        }
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return \SM\Core\Api\SearchResult
     */
    public function load(DataObject $searchCriteria)
    {
        if (is_null($searchCriteria) || !$searchCriteria)
            $searchCriteria = $this->getSearchCriteria();

        $collection = $this->getFeedbackCollection($searchCriteria);

        $items = [];
        if ($collection->getLastPageNumber() < $searchCriteria->getData('currentPage')) {
        }
        else
            foreach ($collection as $item) {
                $i = new Feedback();
                $items[] = $i->addData($item->getData());
            }

        return $this->getSearchResult()
            ->setSearchCriteria($searchCriteria)
            ->setItems($items)
            ->setTotalCount($collection->getSize());
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return \SM\Sales\Model\ResourceModel\Feedback\Collection
     */
    public function getFeedbackCollection(DataObject $searchCriteria)
    {
        /** @var \SM\Sales\Model\ResourceModel\Feedback\Collection $collection */
        $collection = $this->feedbackCollectionFactory->create();

        if ($searchCriteria->getData('ids')) {
            $collection->addFieldToFilter('id', ['in' => explode(",", $searchCriteria->getData('ids'))]);
        }

        return $collection;
    }

}

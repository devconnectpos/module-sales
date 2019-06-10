<?php

namespace SM\Sales\Api;

use SM\Sales\Api\Data\FeedbackInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FeedbackRepositoryInterface {

    public function save(FeedbackInterface $page);

    public function getById($id);

    public function getList(SearchCriteriaInterface $criteria);

    public function delete(FeedbackInterface $page);

    public function deleteById($id);
}

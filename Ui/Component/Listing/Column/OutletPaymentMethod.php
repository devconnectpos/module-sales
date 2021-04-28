<?php

namespace SM\Sales\Ui\Component\Listing\Column;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use SM\Shift\Model\RetailTransaction;
use SM\Shift\Model\RetailTransactionRepository;

class OutletPaymentMethod extends Column
{
    /**
     * @var RetailTransactionRepository
     */
    private $transactionRepo;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RetailTransactionRepository $transactionRepository,
        array $components = [],
        array $data = []
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->transactionRepo = $transactionRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $notCountedPaymentType = [
                    \SM\Payment\Model\RetailPayment::GIFT_CARD_PAYMENT_TYPE,
                    \SM\Payment\Model\RetailPayment::REWARD_POINT_PAYMENT_TYPE,
                    \SM\Payment\Model\RetailPayment::STORE_CREDIT_PAYMENT_TYPE,
                    \SM\Payment\Model\RetailPayment::REFUND_GC_PAYMENT_TYPE,
                    \SM\Payment\Model\RetailPayment::REFUND_TO_STORE_CREDIT_PAYMENT_TYPE,
                ];

                $this->searchCriteriaBuilder->addFilter('order_id', $item["entity_id"]);
                $this->searchCriteriaBuilder->addFilter('is_purchase', 1);
                $this->searchCriteriaBuilder->addFilter('payment_type', $notCountedPaymentType, 'nin');
                $transactions = $this->transactionRepo->getList($this->searchCriteriaBuilder->create())->getItems();
                $paymentMethods = [];

                /** @var RetailTransaction $transaction */
                foreach ($transactions as $transaction) {
                    $paymentMethods[$transaction->getPaymentId()] = strtoupper($transaction->getPaymentTitle());
                }

                // $this->getData('name') returns the name of the column so in this case it would return export_status
                $item[$this->getData('name')] = implode("-", $paymentMethods);
            }
        }

        return $dataSource;
    }
}

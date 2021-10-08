<?php

namespace SM\Sales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use SM\Integrate\Helper\Data;
use SM\XRetail\Model\OutletFactory;

class SaveRetailDataToOrderAndQuote implements ObserverInterface
{

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;
    /**
     * @var OutletFactory
     */
    protected $outletFactory;
    /**
     * @var Data
     */
    private $integrateHelper;

    /**
     * SaveOutletIdToOrderAndQuote constructor.
     *
     * @param \Magento\Framework\Registry $registry
     * @param Data $integrateHelper
     */
    public function __construct(
        Registry $registry,
        Data $integrateHelper,
        OutletFactory $outletFactory
    ) {
        $this->registry = $registry;
        $this->integrateHelper = $integrateHelper;
        $this->outletFactory = $outletFactory;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $observer->getData('order');
        /** @var \Magento\Quote\Api\Data\CartInterface $quote */
        $quote = $observer->getData('quote');

        $outletId = $this->registry->registry('outlet_id');
        if (!!$outletId) {
            $outlet = $this->outletFactory->create()->load($outletId);
            $quote->setData('outlet_id', $outletId);
            $quote->setData('outlet_name', $outlet->getName());
            $order->setData('outlet_id', $outletId);
            $order->setData('outlet_name', $outlet->getName());
        }
        $register_id = $this->registry->registry('register_id');
        if (!!$register_id) {
            $quote->setData('register_id', $register_id);
            $order->setData('register_id', $register_id);
        }

        $retailId = $this->registry->registry('retail_id');
        if (!!$retailId) {
            $quote->setData('retail_id', $retailId);
            $order->setData('retail_id', $retailId);
        }

        $retailNote = $this->registry->registry('retail_note');
        if (!!$retailNote) {
            $quote->setData('retail_note', $retailNote);
            $order->setData('retail_note', $retailNote);

            //save pos retail note to bold commerce order comment field
            if ($this->integrateHelper->isIntegrateBoldOrderComment()) {
                $quote->setData(\Bold\OrderComment\Model\Data\OrderComment::COMMENT_FIELD_NAME, $retailNote);
                $order->setData(\Bold\OrderComment\Model\Data\OrderComment::COMMENT_FIELD_NAME, $retailNote);
            }
        }

        $orderRate = $this->registry->registry('order_rate');
        if (!!$orderRate) {
            $quote->setData('order_rate', $orderRate);
            $order->setData('order_rate', $orderRate);
        }

        $orderFeedback = $this->registry->registry('order_feedback');
        if (!!$orderFeedback) {
            $quote->setData('order_feedback', $orderFeedback);
            $order->setData('order_feedback', $orderFeedback);
        }

        $retailStatus = $this->registry->registry('retail_status');
        if (!!$outletId) {
            $quote->setData('retail_status', $retailStatus);
            $order->setData('retail_status', $retailStatus);
        }

        $retailHasShipment = $this->registry->registry('retail_has_shipment');
        if (!!$outletId) {
            $quote->setData('retail_has_shipment', $retailHasShipment);
            $order->setData('retail_has_shipment', $retailHasShipment);
        }

        $userId = $this->registry->registry('user_id');
        if (!!$outletId) {
            $quote->setData('user_id', $userId);
            $order->setData('user_id', $userId);
        }

        $sellerUsername = $this->registry->registry('sm_seller_username');
        if (!!$sellerUsername) {
            $quote->setData('sm_seller_username', $sellerUsername);
            $order->setData('sm_seller_username', $sellerUsername);
        }

        $sellerIds = $this->registry->registry('sm_seller_ids');
        if (!!$sellerIds) {
            $quote->setData('sm_seller_ids', $sellerIds);
            $order->setData('sm_seller_ids', $sellerIds);
        }

        $retailExchange = $this->registry->registry('is_exchange');
        if (!!$retailExchange) {
            $quote->setData('is_exchange', $retailExchange);
            $order->setData('is_exchange', $retailExchange);
        }

        $xRefNum = $this->registry->registry('xRefNum');
        if (!!$xRefNum) {
            $quote->setData('xRefNum', $xRefNum);
            $order->setData('xRefNum', $xRefNum);
        }

        $pickup_outlet_id = $this->registry->registry('pickup_outlet_id');
        if ($quote->getShippingAddress()->getShippingMethod() === 'smstorepickup_smstorepickup'
            && !$pickup_outlet_id
            && $quote->getOutletId()) {
            $pickup_outlet_id = $quote->getOutletId();
        }
        if (!!$pickup_outlet_id) {
            $quote->setData('pickup_outlet_id', $pickup_outlet_id);
            $order->setData('pickup_outlet_id', $pickup_outlet_id);
        }

        $transId = $this->registry->registry('transId');
        if (!!$transId) {
            $quote->setData('transId', $transId);
            $order->setData('transId', $transId);
        }

        $username = $this->registry->registry('user_name');
        if (!!$username) {
            $quote->setData('user_name', $username);
            $order->setData('user_name', $username);
        }

        $isPWA = $this->registry->registry('is_pwa');
        if (!!$isPWA) {
            $quote->setData('is_pwa', $isPWA);
            $order->setData('is_pwa', $isPWA);
        }

        $estimatedAvailability = $this->registry->registry('estimated_availability');
        if (!!$estimatedAvailability) {
            $quote->setData('estimated_availability', $estimatedAvailability);
            $order->setData('estimated_availability', $estimatedAvailability);
        }
    }
}

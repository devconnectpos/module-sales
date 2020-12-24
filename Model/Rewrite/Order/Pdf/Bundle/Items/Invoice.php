<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace SM\Sales\Model\Rewrite\Order\Pdf\Bundle\Items;

use Magento\Bundle\Model\Sales\Order\Pdf\Items\AbstractItems;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\StringUtils;

/**
 * Order invoice pdf default items renderer
 */
class Invoice extends AbstractItems
{
    /**
     * @return mixed|\Magento\Framework\Stdlib\StringUtils
     */
    protected function getString()
    {
        return ObjectManager::getInstance()->get(StringUtils::class);
    }

    /**
     * @return mixed|\Magento\Framework\Serialize\Serializer\Json
     */
    protected function getSerializer()
    {
        return ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * Draw item line
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function draw()
    {
        $order = $this->getOrder();
        $item = $this->getItem();
        $pdf = $this->getPdf();
        $page = $this->getPage();

        $this->_setFontRegular();
        $items = $this->getChildren($item);

        $prevOptionId = '';
        $drawItems = [];

        foreach ($items as $childItem) {
            $line = [];

            $attributes = $this->getSelectionAttributes($childItem);
            if (is_array($attributes)) {
                $optionId = $attributes['option_id'];
            } else {
                $optionId = 0;
            }

            if (!isset($drawItems[$optionId])) {
                $drawItems[$optionId] = ['lines' => [], 'height' => 15];
            }

            if ($childItem->getOrderItem()->getParentItem()) {
                if ($prevOptionId != $attributes['option_id']) {
                    $line[0] = [
                        'font' => 'italic',
                        'text' => $this->getString()->split($attributes['option_label'], 45, true, true),
                        'feed' => 35,
                    ];

                    $drawItems[$optionId] = ['lines' => [$line], 'height' => 15];

                    $line = [];
                    $prevOptionId = $attributes['option_id'];
                }
            }

            /* in case Product name is longer than 80 chars - it is written in a few lines */
            if ($childItem->getOrderItem()->getParentItem()) {
                $feed = 40;
                $name = $this->getValueHtml($childItem);
            } else {
                $feed = 35;
                $name = $childItem->getName();
            }
            $splitName = $this->getString()->split($name, 35, true, true);
            if ($childItem->getOrderItem()->getData('serial_number')) {
                $splitName[] = 'Serial Number: '.$childItem->getOrderItem()->getData('serial_number');
            }
            $line[] = ['text' => $splitName, 'feed' => $feed];

            // draw SKUs
            if (!$childItem->getOrderItem()->getParentItem()) {
                $text = [];
                foreach ($this->getString()->split($item->getSku(), 17) as $part) {
                    $text[] = $part;
                }
                $line[] = ['text' => $text, 'feed' => 255];
            }

            // draw prices
            if ($this->canShowPriceInfo($childItem)) {
                $price = $order->formatPriceTxt($childItem->getPrice());
                $line[] = ['text' => $price, 'feed' => 395, 'font' => 'bold', 'align' => 'right'];
                $line[] = ['text' => $childItem->getQty() * 1, 'feed' => 435, 'font' => 'bold'];

                $tax = $order->formatPriceTxt($childItem->getTaxAmount());
                $line[] = ['text' => $tax, 'feed' => 495, 'font' => 'bold', 'align' => 'right'];

                $row_total = $order->formatPriceTxt($childItem->getRowTotal());
                $line[] = ['text' => $row_total, 'feed' => 565, 'font' => 'bold', 'align' => 'right'];
            }

            $drawItems[$optionId]['lines'][] = $line;
        }

        // custom options
        $options = $item->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                foreach ($options['options'] as $option) {
                    $lines = [];
                    $lines[][] = [
                        'text' => $this->getString()->split(
                            $this->filterManager->stripTags($option['label']),
                            40,
                            true,
                            true
                        ),
                        'font' => 'italic',
                        'feed' => 35,
                    ];

                    if ($option['value']) {
                        $text = [];
                        $printValue = isset(
                            $option['print_value']
                        )
                            ? $option['print_value']
                            : $this->filterManager->stripTags(
                                $option['value']
                            );
                        $values = explode(', ', $printValue);
                        foreach ($values as $value) {
                            foreach ($this->getString()->split($value, 30, true, true) as $subValue) {
                                $text[] = $subValue;
                            }
                        }

                        $lines[][] = ['text' => $text, 'feed' => 40];
                    }

                    $drawItems[] = ['lines' => $lines, 'height' => 15];
                }
            }
        }

        $page = $pdf->drawLineBlocks($page, $drawItems, ['table_header' => true]);

        $this->setPage($page);
    }
}

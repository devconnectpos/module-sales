<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 2/21/17
 * Time: 4:13 PM
 */

namespace SM\Sales\Helper;

use Magento\Config\Model\Config\Loader;

class Data
{
    public static $ITEM_COUNT = 0;

    /**
     * @var \Magento\Config\Model\Config\Loader
     */
    protected $configLoader;
    protected $configData;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface          $objectManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\ModuleListInterface      $moduleList
     * @param \Magento\Config\Model\Config\Loader                $loader
     */
    public function __construct(
        Loader $loader
    ) {
        $this->configLoader  = $loader;
    }

    /**
     * @return mixed
     */
    private function getConfigLoaderData() {
        if ($this->configData === null) {
            $this->configData = $this->configLoader->getConfigByPath('xretail/pos', 'default', 0);
        }
        return $this->configData;
    }

    /**
     * @return bool
     */
    public function isEnableRefundPendingOrder()
    {
        $config      = $this->getConfigLoaderData();
        $configValue = isset($config['xretail/pos/allow_refund_pending_order']) ? $config['xretail/pos/allow_refund_pending_order']['value'] : '0';

        return $configValue == '1';
    }
}

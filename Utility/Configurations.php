<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Utility;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Configurations
 */
class Configurations
{
    /**
     * Configurations constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get boolean configuration value
     *
     * @param $path
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    private function _isSetFlag($path): bool
    {
        return $this->scopeConfig->isSetFlag(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    /**
     * Check if the module is enabled
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isModuleEnabled(): bool
    {
        return $this->_isSetFlag(Constants::POLYSHELL_ENABLED_PATH);
    }
}

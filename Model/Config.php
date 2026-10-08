<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor
    ) {
    }

    public function isReady(?int $storeId): bool
    {
        return $this->isActive($storeId)
            && $this->apiKey($storeId) !== ''
            && $this->webhookSecret($storeId) !== ''
            && $this->assetId($storeId) !== '';
    }

    public function isActive(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag('payment/voybit/active', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function title(?int $storeId): string
    {
        $title = trim((string) $this->scopeConfig->getValue('payment/voybit/title', ScopeInterface::SCOPE_STORE, $storeId));
        return $title !== '' ? $title : 'Voybit';
    }

    public function apiKey(?int $storeId): string
    {
        return $this->secret('payment/voybit/api_key', $storeId);
    }

    public function webhookSecret(?int $storeId): string
    {
        return $this->secret('payment/voybit/webhook_secret', $storeId);
    }

    public function assetId(?int $storeId): string
    {
        $value = strtolower(trim((string) $this->scopeConfig->getValue(
            'payment/voybit/asset_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )));
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) === 1
            ? $value
            : '';
    }

    private function secret(string $path, ?int $storeId): string
    {
        $stored = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        if ($stored === '') {
            return '';
        }
        $plain = $this->encryptor->decrypt($stored);
        return is_string($plain) ? trim($plain) : '';
    }
}

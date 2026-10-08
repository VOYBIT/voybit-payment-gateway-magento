<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor,
        private WriterInterface $writer,
        private ReinitableConfigInterface $reinitableConfig
    ) {
    }

    public function isReady(?int $storeId): bool
    {
        return $this->isActive($storeId)
            && $this->apiKey($storeId) !== '';
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

    public function saveWebhookSecret(int $storeId, string $secret): void
    {
        $secret = trim($secret);
        if (!preg_match('/^[A-Za-z0-9._:-]{8,256}$/', $secret)) {
            throw new \InvalidArgumentException('Voybit returned an invalid webhook secret.');
        }
        $this->writer->save(
            'payment/voybit/webhook_secret',
            $this->encryptor->encrypt($secret),
            ScopeInterface::SCOPE_STORES,
            $storeId
        );
        $this->reinitableConfig->reinit();
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

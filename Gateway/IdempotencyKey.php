<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Gateway;

final class IdempotencyKey
{
    public static function forOrder(string $incrementId): string
    {
        $suffix = preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $incrementId) ? $incrementId : hash('sha256', $incrementId);
        $key = 'magento:' . $suffix;
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $key)) {
            $key = 'magento:' . hash('sha256', $incrementId);
        }
        return $key;
    }
}

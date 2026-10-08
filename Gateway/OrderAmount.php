<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Gateway;

final class OrderAmount
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF',
        'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    private const MAX_MINOR = 9000000000000000;

    /** @return array{amount_minor: int, crypto_amount: string, fiat_currency: string} */
    public static function from(string $amount, string $currency): array
    {
        $currency = strtoupper(trim($currency));
        $amount = trim($amount);
        if (!preg_match('/^[A-Z]{3}$/', $currency) || !preg_match('/^(?:0|[1-9]\d*)(?:\.(\d+))?$/', $amount, $match)) {
            throw new \InvalidArgumentException('order total is not a valid amount');
        }
        $exponent = self::exponent($currency);
        if ($exponent > 4) {
            throw new \InvalidArgumentException('order total is not a valid amount');
        }
        $whole = explode('.', $amount, 2)[0];
        $fraction = $match[1] ?? '';
        if (strlen($fraction) > $exponent && preg_match('/[1-9]/', substr($fraction, $exponent))) {
            throw new \InvalidArgumentException('order total has more decimal places than the currency allows');
        }
        $fraction = str_pad(substr($fraction, 0, $exponent), $exponent, '0');
        $minor = ltrim($whole . $fraction, '0');
        if (
            $minor === ''
            || strlen($minor) > 16
            || (strlen($minor) === 16 && strcmp($minor, (string) self::MAX_MINOR) > 0)
        ) {
            throw new \InvalidArgumentException('order total is not a valid amount');
        }
        return [
            'amount_minor' => (int) $minor,
            'crypto_amount' => $exponent === 0 ? $whole : $whole . '.' . $fraction,
            'fiat_currency' => $currency,
        ];
    }

    private static function exponent(string $currency): int
    {
        if (in_array($currency, self::ZERO_DECIMAL, true)) {
            return 0;
        }
        if (in_array($currency, self::THREE_DECIMAL, true)) {
            return 3;
        }
        return 2;
    }
}

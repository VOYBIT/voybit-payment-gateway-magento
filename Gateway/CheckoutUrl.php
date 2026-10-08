<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Gateway;

final class CheckoutUrl
{
    public static function canonical(string $url): string
    {
        $parts = parse_url(trim($url));
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strcasecmp($host, 'voybit.com') !== 0
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !preg_match('#^/pay/([A-Za-z0-9_-]{22})/?$#', $path, $match)
        ) {
            throw new \InvalidArgumentException('checkout URL is not a Voybit checkout page');
        }
        return 'https://voybit.com/pay/' . $match[1];
    }
}

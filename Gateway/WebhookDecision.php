<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Gateway;

final class WebhookDecision
{
    public const ACK = 'ack';
    public const FULFIL = 'fulfil';
    public const RETRY = 'retry';
    public const REJECT = 'reject';

    /** @param array<string, mixed> $event
     *  @param array{public_id: string}|null $link */
    public static function forEvent(array $event, ?array $link): string
    {
        $status = (string) ($event['status'] ?? '');
        if ($status !== 'paid' && $status !== 'overpaid') {
            return self::ACK;
        }
        if ($link === null) {
            return self::RETRY;
        }
        $publicId = (string) ($event['checkout_public_id'] ?? $event['public_id'] ?? '');
        $stored = (string) $link['public_id'];
        if ($publicId === '' || strlen($publicId) !== strlen($stored) || !hash_equals($stored, $publicId)) {
            return self::REJECT;
        }
        return self::FULFIL;
    }
}

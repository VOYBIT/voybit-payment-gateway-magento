<?php

declare(strict_types=1);

require __DIR__ . '/../Gateway/OrderAmount.php';
require __DIR__ . '/../Gateway/CheckoutUrl.php';
require __DIR__ . '/../Gateway/IdempotencyKey.php';
require __DIR__ . '/../Gateway/WebhookDecision.php';

use Voybit\PaymentGateway\Magento\Gateway\CheckoutUrl;
use Voybit\PaymentGateway\Magento\Gateway\IdempotencyKey;
use Voybit\PaymentGateway\Magento\Gateway\OrderAmount;
use Voybit\PaymentGateway\Magento\Gateway\WebhookDecision;

function expect(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function fails(callable $fn): bool
{
    try {
        $fn();
    } catch (InvalidArgumentException) {
        return true;
    }
    return false;
}

$usd = OrderAmount::from('25.0000', 'usd');
expect($usd['fiat_amount'] === '25.00', 'usd amount');
expect($usd['fiat_currency'] === 'USD', 'usd code');

$yen = OrderAmount::from('25.000', 'JPY');
expect($yen['fiat_amount'] === '25', 'jpy');

$dinar = OrderAmount::from('1.234', 'BHD');
expect($dinar['fiat_amount'] === '1.234', 'bhd');

expect(fails(static fn () => OrderAmount::from('25.501', 'USD')), 'extra usd digit');
expect(fails(static fn () => OrderAmount::from('0.00', 'USD')), 'zero');
expect(fails(static fn () => OrderAmount::from('-1.00', 'USD')), 'negative');
expect(fails(static fn () => OrderAmount::from('10', 'US')), 'currency');

$id = 'nYVvXxsYGr5LZk8Dn7hU0Q';
expect(CheckoutUrl::canonical('https://voybit.com/pay/' . $id) === 'https://voybit.com/pay/' . $id, 'checkout');
expect(CheckoutUrl::canonical('https://voybit.com/pay/' . $id . '/') === 'https://voybit.com/pay/' . $id, 'slash');
expect(fails(static fn () => CheckoutUrl::canonical('http://voybit.com/pay/' . $id)), 'http');
expect(fails(static fn () => CheckoutUrl::canonical('https://user:pass@voybit.com/pay/' . $id)), 'userinfo');
expect(fails(static fn () => CheckoutUrl::canonical('https://voybit.com/pay/' . $id . '?x=1')), 'query');
expect(fails(static fn () => CheckoutUrl::canonical('https://example.com/pay/' . $id)), 'host');

expect(IdempotencyKey::forOrder('000000123') === 'magento:000000123', 'key');
expect(preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', IdempotencyKey::forOrder('order/1')) === 1, 'hashed key');

$link = ['public_id' => $id];
expect(WebhookDecision::forEvent(['status' => 'pending', 'public_id' => $id], $link) === WebhookDecision::ACK, 'pending');
expect(WebhookDecision::forEvent(['status' => 'paid', 'public_id' => $id], null) === WebhookDecision::RETRY, 'missing');
expect(WebhookDecision::forEvent(['status' => 'paid', 'public_id' => $id], $link) === WebhookDecision::FULFIL, 'paid');
expect(WebhookDecision::forEvent(['status' => 'overpaid', 'public_id' => $id], $link) === WebhookDecision::FULFIL, 'overpaid');
expect(WebhookDecision::forEvent([
    'status' => 'paid',
    'public_id' => 'x12345678901234567890x',
    'checkout_public_id' => $id,
], $link) === WebhookDecision::FULFIL, 'hosted checkout public id');
expect(WebhookDecision::forEvent(['status' => 'paid', 'public_id' => 'short'], $link) === WebhookDecision::REJECT, 'mismatch');

echo "magento gateway ok\n";

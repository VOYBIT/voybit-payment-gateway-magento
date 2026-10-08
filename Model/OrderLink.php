<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class OrderLink
{
    public function __construct(private ResourceConnection $resource)
    {
    }

    public function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    public function orderTable(): string
    {
        return $this->resource->getTableName('voybit_payment_gateway_order');
    }

    public function deliveryTable(): string
    {
        return $this->resource->getTableName('voybit_payment_gateway_delivery');
    }

    /** @return array{payment_id: string, public_id: string, checkout_url: string}|null */
    public function forOrder(int $orderId): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->orderTable(), ['payment_id', 'public_id', 'checkout_url'])
                ->where('order_id = ?', $orderId)
                ->limit(1)
        );
        return is_array($row) && isset($row['payment_id']) ? $row : null;
    }

    /** @return array{order_id: int, public_id: string}|null */
    public function forPayment(string $paymentId): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->orderTable(), ['order_id', 'public_id'])
                ->where('payment_id = ?', $paymentId)
                ->limit(1)
        );
        if (!is_array($row) || !isset($row['order_id'], $row['public_id'])) {
            return null;
        }
        $row['order_id'] = (int) $row['order_id'];
        return $row;
    }

    public function save(string $paymentId, int $orderId, string $publicId, string $checkoutUrl): void
    {
        $this->connection()->insertOnDuplicate(
            $this->orderTable(),
            [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'public_id' => $publicId,
                'checkout_url' => $checkoutUrl,
            ],
            ['public_id', 'checkout_url']
        );
    }

    public function acquire(string $name): bool
    {
        $name = 'voybit-' . $name;
        if (strlen($name) > 64) {
            return false;
        }
        return (int) $this->connection()->fetchOne('SELECT GET_LOCK(?, 15)', [$name]) === 1;
    }

    public function release(string $name): void
    {
        $this->connection()->fetchOne('SELECT RELEASE_LOCK(?)', ['voybit-' . $name]);
    }

    public function seen(string $webhookId): bool
    {
        $found = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->deliveryTable(), ['webhook_id'])
                ->where('webhook_id = ?', $webhookId)
                ->limit(1)
        );
        return is_string($found) && $found !== '';
    }
}

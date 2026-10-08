<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Voybit\PaymentGateway\Client;
use Voybit\PaymentGateway\Magento\Gateway\CheckoutUrl;
use Voybit\PaymentGateway\Magento\Gateway\IdempotencyKey;
use Voybit\PaymentGateway\Magento\Gateway\OrderAmount;
use Voybit\PaymentGateway\VoybitException;

class PaymentStarter
{
    public function __construct(
        private Config $config,
        private OrderLink $links,
        private OrderRepositoryInterface $orders,
        private LoggerInterface $logger
    ) {
    }

    public function checkoutUrl(Order $order): string
    {
        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0 || $order->getPayment() === null || $order->getPayment()->getMethod() !== 'voybit') {
            throw new \RuntimeException('This order cannot be paid with Voybit.');
        }
        if (!$this->links->acquire('order-' . $orderId)) {
            throw new \RuntimeException('Voybit checkout is already starting. Wait a moment and try again.');
        }
        try {
            $existing = $this->links->forOrder($orderId);
            if (is_array($existing) && ($existing['checkout_url'] ?? '') !== '') {
                return CheckoutUrl::canonical((string) $existing['checkout_url']);
            }
            $storeId = (int) $order->getStoreId();
            $priced = OrderAmount::from((string) $order->getGrandTotal(), (string) $order->getOrderCurrencyCode());
            $description = 'Order ' . (string) $order->getIncrementId();
            if (strlen($description) > 500) {
                $description = substr($description, 0, 500);
            }
            $created = (new Client($this->config->apiKey($storeId)))->createPayment([
                'asset_id' => $this->config->assetId($storeId),
                'crypto_amount' => $priced['crypto_amount'],
                'amount_minor' => $priced['amount_minor'],
                'fiat_currency' => $priced['fiat_currency'],
                'description' => $description,
                'metadata' => ['order_id' => (string) $order->getIncrementId()],
            ], IdempotencyKey::forOrder((string) $order->getIncrementId()));
            $payment = is_array($created['payment'] ?? null) ? $created['payment'] : [];
            $paymentId = (string) ($payment['id'] ?? '');
            $publicId = (string) ($payment['public_id'] ?? '');
            $checkoutUrl = CheckoutUrl::canonical((string) ($payment['checkout_url'] ?? ''));
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $paymentId)) {
                throw new \InvalidArgumentException('checkout URL is not a Voybit checkout page');
            }
            $this->links->save($paymentId, $orderId, $publicId, $checkoutUrl);
            $order->getPayment()->setAdditionalInformation('voybit_payment_id', $paymentId);
            $order->getPayment()->setAdditionalInformation('voybit_public_id', $publicId);
            $order->addCommentToStatusHistory('Voybit checkout is open.');
            $this->orders->save($order);
            return $checkoutUrl;
        } catch (VoybitException $error) {
            $this->logger->warning('Voybit checkout was not created.', [
                'order' => (string) $order->getIncrementId(),
                'code' => $error->errorCode,
            ]);
            $order->addCommentToStatusHistory('Voybit could not open checkout (' . $error->errorCode . ').');
            $this->orders->save($order);
            if ($error->errorCode === 'crypto_amount_in_use') {
                throw new \RuntimeException('Another Voybit payment is already open for this amount. Wait a few minutes and place the order again.');
            }
            throw new \RuntimeException('Voybit could not open checkout. This order is saved and is waiting for payment.');
        } catch (\Throwable $error) {
            $this->logger->warning('Voybit checkout was not created.', [
                'order' => (string) $order->getIncrementId(),
            ]);
            throw new \RuntimeException('Voybit could not open checkout. This order is saved and is waiting for payment.', 0, $error);
        } finally {
            $this->links->release('order-' . $orderId);
        }
    }
}

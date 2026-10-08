<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
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
        private LoggerInterface $logger,
        private StoreManagerInterface $stores
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
            $client = new Client($this->config->apiKey($storeId));
            if ($this->config->webhookSecret($storeId) === '') {
                $baseUrl = rtrim((string) $this->stores->getStore($storeId)->getBaseUrl(), '/');
                $configuration = $client->configureGatewayIntegration(
                    $baseUrl . '/voybit/webhook/index',
                    $baseUrl . '/voybit/payment/complete'
                );
                $this->config->saveWebhookSecret(
                    $storeId,
                    (string) ($configuration['webhook_secret'] ?? '')
                );
            }
            $created = $client->createCheckoutSession([
                'fiat_amount' => $priced['fiat_amount'],
                'fiat_currency' => $priced['fiat_currency'],
                'description' => $description,
                'metadata' => ['order_id' => (string) $order->getIncrementId()],
                'payment_window_seconds' => 900,
            ], IdempotencyKey::forOrder((string) $order->getIncrementId()));
            $session = is_array($created['checkout_session'] ?? null) ? $created['checkout_session'] : [];
            $sessionId = strtolower((string) ($session['session_id'] ?? $session['id'] ?? ''));
            $publicId = (string) ($session['public_id'] ?? '');
            $checkoutUrl = CheckoutUrl::canonical((string) ($session['checkout_url'] ?? ''));
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $sessionId)) {
                throw new \InvalidArgumentException('checkout URL is not a Voybit checkout page');
            }
            $this->links->save($sessionId, $orderId, $publicId, $checkoutUrl);
            $order->getPayment()->setAdditionalInformation('voybit_checkout_session_id', $sessionId);
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

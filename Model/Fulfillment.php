<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Voybit\PaymentGateway\Magento\Gateway\WebhookDecision;

class Fulfillment
{
    public function __construct(
        private OrderLink $links,
        private OrderRepositoryInterface $orders,
        private InvoiceService $invoices,
        private TransactionFactory $transactions
    ) {
    }

    /** @param array<string, mixed> $event */
    public function accept(string $webhookId, array $event): int
    {
        $paymentId = strtolower((string) ($event['payment_id'] ?? ''));
        $sessionId = strtolower((string) (
            $event['checkout_session_id']
            ?? $event['session_id']
            ?? ''
        ));
        if (
            !preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $webhookId)
            || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $paymentId)
        ) {
            return 400;
        }
        if (!$this->links->acquire('payment-' . $paymentId)) {
            return 503;
        }
        try {
            if ($this->links->seen($webhookId)) {
                return 204;
            }
            $lookupId = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $sessionId)
                ? $sessionId
                : $paymentId;
            $link = $this->links->forPayment($lookupId);
            $decision = WebhookDecision::forEvent($event, $link);
            if ($decision === WebhookDecision::RETRY) {
                return 503;
            }
            if ($decision === WebhookDecision::REJECT && $link !== null) {
                $order = $this->orders->get($link['order_id']);
                $order->addCommentToStatusHistory('Voybit webhook did not match this payment.');
                $this->orders->save($order);
            }
            if ($decision === WebhookDecision::FULFIL && $link !== null) {
                $order = $this->orders->get($link['order_id']);
                if ($order->getPayment() !== null && $order->getPayment()->getMethod() === 'voybit') {
                    $order->getPayment()->setAdditionalInformation('voybit_payment_id', $paymentId);
                    $order->getPayment()->setTransactionId($paymentId);
                    $this->invoice($order);
                }
            }
            $this->remember($webhookId);
            return 204;
        } finally {
            $this->links->release('payment-' . $paymentId);
        }
    }

    private function invoice(Order $order): void
    {
        $state = (string) $order->getState();
        if ($state === Order::STATE_CANCELED) {
            $order->addCommentToStatusHistory('Voybit reported a confirmed payment after this order was canceled. Review it before shipping.');
            $this->orders->save($order);
            return;
        }
        if (in_array($state, [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
            return;
        }
        if ((string) $order->getState() === Order::STATE_PENDING_PAYMENT) {
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
        }
        $order->addCommentToStatusHistory('Voybit confirmed this payment.');
        if (!$order->canInvoice()) {
            $this->orders->save($order);
            return;
        }
        $invoice = $this->invoices->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $order->setIsInProcess(true);
        $this->transactions->create()->addObject($invoice)->addObject($order)->save();
    }

    private function remember(string $webhookId): bool
    {
        try {
            $this->links->connection()->insert($this->links->deliveryTable(), ['webhook_id' => $webhookId]);
            return true;
        } catch (DuplicateException) {
            return false;
        } catch (\Zend_Db_Statement_Exception $error) {
            if (str_contains($error->getMessage(), 'Duplicate') || str_contains($error->getMessage(), '1062')) {
                return false;
            }
            throw $error;
        }
    }
}

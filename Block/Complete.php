<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Complete extends Template
{
    public function __construct(
        Template\Context $context,
        private Session $session,
        private SearchCriteriaBuilder $search,
        private OrderRepositoryInterface $orders,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function message(): string
    {
        $order = $this->order();
        if (!$order instanceof Order) {
            return (string) __('This browser does not have a Voybit order to show. If you paid, the store updates the order when the payment is confirmed.');
        }
        if ((string) $order->getState() === Order::STATE_CANCELED) {
            return (string) __('This order is canceled. If you already paid, contact the store before placing another order.');
        }
        return (string) __('Order %1 is waiting for the payment to be confirmed. You can close this page.', $order->getIncrementId());
    }

    private function order(): ?Order
    {
        $incrementId = (string) $this->session->getLastRealOrderId();
        if ($incrementId === '') {
            return null;
        }
        $items = $this->orders->getList(
            $this->search->addFilter('increment_id', $incrementId, 'eq')->create()
        )->getItems();
        $order = reset($items);
        return $order instanceof Order ? $order : null;
    }
}

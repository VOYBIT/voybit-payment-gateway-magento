<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class Complete implements HttpGetActionInterface
{
    public function __construct(
        private Session $session,
        private SearchCriteriaBuilder $search,
        private OrderRepositoryInterface $orders,
        private PageFactory $pages,
        private RedirectFactory $redirects
    ) {
    }

    public function execute()
    {
        $order = $this->order();
        if (
            $order instanceof Order
            && in_array((string) $order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)
        ) {
            $redirect = $this->redirects->create();
            $redirect->setPath('checkout/onepage/success');
            return $redirect;
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set((string) __('Payment'));
        return $page;
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

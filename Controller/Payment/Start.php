<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Voybit\PaymentGateway\Magento\Controller\Result\ExternalRedirectFactory;
use Voybit\PaymentGateway\Magento\Model\PaymentStarter;

class Start implements HttpGetActionInterface
{
    public function __construct(
        private Session $session,
        private SearchCriteriaBuilder $search,
        private OrderRepositoryInterface $orders,
        private PaymentStarter $starter,
        private ExternalRedirectFactory $external,
        private RedirectFactory $redirects,
        private ManagerInterface $messages
    ) {
    }

    public function execute()
    {
        try {
            $url = $this->starter->checkoutUrl($this->order());
            return $this->external->create(['url' => $url]);
        } catch (\Throwable $error) {
            $this->messages->addErrorMessage(
                $error instanceof \RuntimeException
                    ? $error->getMessage()
                    : (string) __('Voybit could not open checkout. This order is saved and is waiting for payment.')
            );
            $redirect = $this->redirects->create();
            $redirect->setPath('checkout/cart');
            return $redirect;
        }
    }

    private function order(): Order
    {
        $incrementId = (string) $this->session->getLastRealOrderId();
        if ($incrementId === '') {
            throw new \RuntimeException('The Voybit order is no longer in this browser.');
        }
        $items = $this->orders->getList(
            $this->search->addFilter('increment_id', $incrementId, 'eq')->create()
        )->getItems();
        $order = reset($items);
        if (!$order instanceof Order) {
            throw new \RuntimeException('The Voybit order is no longer in this browser.');
        }
        return $order;
    }
}

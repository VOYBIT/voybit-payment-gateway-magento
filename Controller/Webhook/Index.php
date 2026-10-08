<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Voybit\PaymentGateway\Magento\Model\Config;
use Voybit\PaymentGateway\Magento\Model\Fulfillment;
use Voybit\PaymentGateway\Webhook;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private Http $request,
        private RawFactory $raw,
        private Config $config,
        private Fulfillment $fulfillment,
        private StoreManagerInterface $stores
    ) {
    }

    public function execute()
    {
        $result = $this->raw->create();
        $body = (string) $this->request->getContent();
        if (strlen($body) > 65536) {
            $result->setHttpResponseCode(400);
            return $result;
        }
        $storeId = (int) $this->stores->getStore()->getId();
        try {
            Webhook::verify(
                $this->config->webhookSecret($storeId),
                $this->header('Voybit-Webhook-Id'),
                $this->header('Voybit-Webhook-Timestamp'),
                $this->header('Voybit-Webhook-Signature'),
                $body
            );
        } catch (\Throwable) {
            $result->setHttpResponseCode(401);
            return $result;
        }
        try {
            $event = Webhook::parse($body);
        } catch (\Throwable) {
            $result->setHttpResponseCode(400);
            return $result;
        }
        try {
            $status = $this->fulfillment->accept($this->header('Voybit-Webhook-Id'), $event);
        } catch (\Throwable) {
            $status = 500;
        }
        $result->setHttpResponseCode($status);
        return $result;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function header(string $name): string
    {
        $value = $this->request->getHeader($name);
        return is_string($value) ? trim($value) : '';
    }
}

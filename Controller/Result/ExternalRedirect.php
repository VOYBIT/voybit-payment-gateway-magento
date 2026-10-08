<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Controller\Result;

use Magento\Framework\App\Response\HttpInterface as HttpResponseInterface;
use Magento\Framework\Controller\AbstractResult;

class ExternalRedirect extends AbstractResult
{
    public function __construct(private string $url)
    {
    }

    protected function render(HttpResponseInterface $response)
    {
        $response->setRedirect($this->url);
        $response->setHeader('Cache-Control', 'no-store', true);
        return $this;
    }
}

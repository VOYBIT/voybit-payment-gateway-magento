<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Plugin;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\CsrfValidator;
use Magento\Framework\App\RequestInterface;
use Voybit\PaymentGateway\Magento\Controller\Webhook\Index;

class CsrfValidatorSkip
{
    public function aroundValidate(
        CsrfValidator $subject,
        \Closure $proceed,
        RequestInterface $request,
        ActionInterface $action
    ): void {
        if ($action instanceof Index) {
            return;
        }
        $proceed($request, $action);
    }
}

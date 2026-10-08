<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private UrlInterface $url,
        private Repository $assets
    ) {
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                'voybit' => [
                    'redirectUrl' => $this->url->getUrl('voybit/payment/start'),
                    'logo' => $this->assets->getUrl('Voybit_PaymentGateway::images/voybit.svg'),
                    'instructions' => (string) __('You pay on the Voybit page. The store confirms the order when the payment arrives.'),
                ],
            ],
        ];
    }
}

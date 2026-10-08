<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;

class Endpoints extends Field
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private StoreManagerInterface $stores,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $storeId = $this->getRequest()->getParam('store');
        $websiteId = $this->getRequest()->getParam('website');
        if ($storeId) {
            $store = $this->stores->getStore($storeId);
        } elseif ($websiteId) {
            $store = $this->stores->getWebsite($websiteId)->getDefaultStore();
        } else {
            $store = $this->stores->getDefaultStoreView();
        }
        $base = rtrim((string) $store->getBaseUrl(), '/');
        $webhook = $base . '/voybit/webhook/index';
        $complete = $base . '/voybit/payment/complete';
        return '<div><strong>' . $this->escapeHtml((string) __('Webhook')) . '</strong><br/><code>'
            . $this->escapeHtml($webhook) . '</code></div><div style="margin-top:8px"><strong>'
            . $this->escapeHtml((string) __('Customer return')) . '</strong><br/><code>'
            . $this->escapeHtml($complete) . '</code></div><p class="note">'
            . $this->escapeHtml((string) __('Paste both into the gateway in the Voybit dashboard. Both need to be HTTPS.'))
            . '</p>';
    }
}

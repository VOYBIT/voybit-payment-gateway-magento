<?php

declare(strict_types=1);

namespace Voybit\PaymentGateway\Magento\Model\Payment;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;
use Voybit\PaymentGateway\Magento\Model\Config;

class Voybit extends AbstractMethod
{
    public const CODE = 'voybit';

    protected $_code = self::CODE;

    protected $_isOffline = true;

    protected $_canUseInternal = false;

    protected $_canUseCheckout = true;

    private Config $voybitConfig;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        Config $voybitConfig,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?\Magento\Directory\Helper\Data $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->voybitConfig = $voybitConfig;
    }

    public function isAvailable(?CartInterface $quote = null)
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }
        $storeId = $quote ? (int) $quote->getStoreId() : null;
        return $this->voybitConfig->isReady($storeId);
    }
}

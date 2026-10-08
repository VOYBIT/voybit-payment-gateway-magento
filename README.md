# Voybit payment gateway for Magento

## Get an API key

1. Create an account at [dashboard.voybit.com](https://dashboard.voybit.com).
2. Open **Gateways**, create a payment gateway, and enable every asset and network customers may choose.
3. Open **API keys**, choose **Create secret key**, and bind it to that gateway. Copy the full `vb_live_…` value once.

Only the API key is pasted into Magento. It stays on the server and is never sent to the browser.

## Install

Magento 2.4.6 or newer, PHP 8.1 or newer. The store checkout is the standard Magento checkout. Not published to Packagist.

From the Magento project root:

```bash
composer config repositories.voybit-php vcs https://github.com/VOYBIT/voybit-payment-gateway-php
composer config repositories.voybit-magento vcs https://github.com/VOYBIT/voybit-payment-gateway-magento
composer require voybit/payment-gateway:dev-main voybit/payment-gateway-magento:dev-main
bin/magento module:enable Voybit_PaymentGateway
bin/magento setup:upgrade
bin/magento cache:flush
```

The project `composer.json` needs `"minimum-stability": "dev"` and `"prefer-stable": true`. In production mode, also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`.

In **Stores → Configuration → Sales → Payment Methods → Voybit**:

1. Enable the method.
2. Paste the gateway-scoped API key.
3. Make sure the Magento store URL uses HTTPS.

On the first checkout, the module registers its webhook and customer return URL with Voybit and stores the rotated signing secret encrypted. No asset ID or webhook secret is entered manually.

The module sends only the order total and currency. Voybit then shows the assets enabled on that gateway. The customer chooses one, reviews the live crypto conversion, and confirms before an address and QR code are created.

Placing the order opens Voybit checkout. The order stays unpaid until a signed webhook says `paid` or `overpaid`. The module then creates an offline invoice. A repeated delivery is ignored. The return page does not mark the order paid.

## Support

If the module does not install, or a payment does not complete as described here, contact Voybit support at [voybit.com/contact](https://voybit.com/contact).

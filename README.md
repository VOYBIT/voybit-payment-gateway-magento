# Voybit payment gateway for Magento

## Get an API key

1. Create an account at [dashboard.voybit.com](https://dashboard.voybit.com).
2. Open **Gateways** and create a payment gateway. Keep it enabled. Copy the asset ID you will charge, and store the webhook secret (`whsec_…`) shown once at creation.
3. Open **API keys**, choose **Create secret key**, and bind it to that gateway. Copy the full `vb_live_…` value once.

The API key and webhook secret stay in the Magento admin. They are not sent to the browser.

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
2. Paste the API key, webhook secret, and asset ID.
3. Copy the webhook URL and the customer return URL shown there.

On the gateway in the Voybit dashboard, paste those two URLs. Both have to be HTTPS. The webhook path is `/voybit/webhook/index`. The return path is `/voybit/payment/complete`.

The order total is the amount the customer pays, in the store currency. A 25.00 order asks for 25.00 of the selected asset. Use a stablecoin that matches the store currency, such as USDT for a USD store.

Placing the order opens Voybit checkout. The order stays unpaid until a signed webhook says `paid` or `overpaid`. The module then creates an offline invoice. A repeated delivery is ignored. The return page does not mark the order paid.

## Support

If the module does not install, or a payment does not complete as described here, contact Voybit support at [voybit.com/contact](https://voybit.com/contact).

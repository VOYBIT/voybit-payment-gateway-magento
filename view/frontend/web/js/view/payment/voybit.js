define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'voybit',
        component: 'Voybit_PaymentGateway/js/view/payment/method-renderer/voybit'
    });

    return Component.extend({});
});

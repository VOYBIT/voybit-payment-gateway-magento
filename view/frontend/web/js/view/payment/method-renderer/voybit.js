define([
    'Magento_Checkout/js/view/payment/default'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Voybit_PaymentGateway/payment/voybit',
            redirectAfterPlaceOrder: false
        },

        getLogo: function () {
            return window.checkoutConfig.payment.voybit.logo;
        },

        getInstructions: function () {
            return window.checkoutConfig.payment.voybit.instructions;
        },

        afterPlaceOrder: function () {
            window.location.replace(window.checkoutConfig.payment.voybit.redirectUrl);
        }
    });
});

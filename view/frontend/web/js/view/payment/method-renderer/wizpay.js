/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/

define(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/url-builder',
        'mage/url',
        'Magento_Checkout/js/model/quote',

        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/customer-data',
        'Magento_Customer/js/section-config'
    ],
    function ($, modal, Component, urlBuilder,
        url,
        quote,
        setPaymentInformationAction,
        additionalValidators,
        customerData,
        sectionConfig,
        errorProcessor
        ) {
        'use strict';


        if(window.checkoutConfig.payment.wizpay.default_country != 'AU' ){
            return Component.extend({
                defaults: {
                    template: 'Wizpay_Wizpay/payment/outzoneform',
                },
            });
        }



        return Component.extend({
            defaults: {
                template: 'Wizpay_Wizpay/payment/form',
            },


            redirectAfterPlaceOrder: false,


            continueToWizpay: function(data, event){
                const self = this;

                if (event) {
                    event.preventDefault();
                }
                

                var shippingAddress = quote.shippingAddress();
                var billingAddress = quote.billingAddress();

                var isInAU = true;
                if(shippingAddress && shippingAddress.countryId != 'AU'){
                    isInAU = false;
                }

                if(billingAddress && billingAddress.countryId != 'AU'){
                    isInAU = false;
                }

                if(isInAU){
                    //if (additionalValidators.validate() && this.isPlaceOrderActionAllowed() === true) {
                    if ( this.isPlaceOrderActionAllowed() === true) {
                        this.isPlaceOrderActionAllowed(false);
        
                        const captureUrlPath = 'wizpay/index?status=SUCCESS';
                        window.location.replace(url.build(captureUrlPath));
                    }
                }else{
                    alert('Wizpay is only available in Australia');
                }

                
            },


            getCode: function () {
                return 'wizpay';
            },

            getLogoUrl: function () {
                return window.checkoutConfig.payment.wizpay.wizpayLogoUrl;
            },
            getUrlc: function () {
                return window.checkoutConfig.payment.wizpay.urls;
            },
            getTitle : function(){
                return window.checkoutConfig.payment.wizpay.wizpayTitle;
            },

            totalamount: function () {
                var price = quote.getTotals()().base_grand_total;
                return price.toFixed(2);
            },

            installment: function () {
                var price = quote.getTotals()().base_grand_total;
                var formatedprice = price / 4;
                return formatedprice.toFixed(2);
            },

            getStoreCurrency: function () {
                return window.checkoutConfig.payment.wizpay.getStoreCurrency;

            },

            beforePlaceOrder: function () {
                window.location.replace(url.build('wizpay/index'));
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('wizpay/index'));
            },

            context: function () {
                return this;
            },

            isActive: function () {
                return true;
            },

            popimage: function () {
                return window.checkoutConfig.payment.wizpay.popimage;
            },

            showPopup: function () {
                var couponcodepopup = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    buttons: false,
                    modalClass: "wz-custom-modal",
                    clickableOverlay: true,
                    heightStyle: "content"
                };
                modal(couponcodepopup, $('#popup-modal'));
                $(".wz-custom-modal header.modal-header").appendTo("div#popup-modal");
                $('#popup-modal').modal('openModal');
            }
        });
    }
);
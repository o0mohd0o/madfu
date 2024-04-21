define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/modal/modal',
        'Magento_Checkout/js/action/place-order',
        'MadfuCheckout'
    ],
    function (
        Component,
        $,
        urlBuilder,
        quote,
        fullScreenLoader,
        redirectOnSuccessAction,
        additionalValidators,
        modal,
        placeOrderAction
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Madfu_MadfuPayment/payment/form',
                transactionResult: ''
            },

            initObservable: function () {
                this._super().observe([
                    'transactionResult'
                ]);
                return this;
            },

            getCode: function() {
                return 'madfu_gateway';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': null
                };
            },

            getTitle: function() {
                return 'Madfu';
            },

            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    fullScreenLoader.startLoader();

                    this.createOrder().then(function (response) {
                        self.initIframe(response.data.token);
                    }).catch(function () {
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                    });
                }

                return false;
            },

            createOrder: function () {
                // console.log(quote.billingAddress().telephone);
                var orderData = {
                    "GuestOrderData": {
                        "CustomerMobile": quote.billingAddress().telephone,
                        "CustomerName": quote.billingAddress().firstname + ' ' + quote.billingAddress().lastname,
                        "Lang": "ar"
                    },
                    "Order": {
                        "Taxes": quote.totals().tax_amount,
                        "ActualValue": quote.totals().grand_total,
                        "Amount": quote.totals().grand_total,
                        "MerchantReference": quote.getQuoteId()
                    },
                    "OrderDetails": quote.getItems().map(function (item) {
                        return {
                            "productName": item.name,
                            "SKU": item.sku,
                            "productImage": item.image,
                            "count": item.qty,
                            "totalAmount": item.row_total
                        };
                    })
                };

                return $.ajax({
                    url: urlBuilder.build('madfu_payment/payment/createOrder'),
                    type: 'POST',
                    data: JSON.stringify(orderData),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            },

            initIframe: function (token) {
                var self = this;

                if (typeof Checkout === 'undefined' || typeof Checkout.Checkout !== 'object') {
                    console.error('Checkout object is not available.');
                    fullScreenLoader.stopLoader();
                    self.isPlaceOrderActionAllowed(true);
                    return;
                }

                Checkout.Checkout.configure = {
                    token: token,
                    completeCallback: function (data) {
                        console.log('Payment Success');
                        fullScreenLoader.stopLoader();
                        $('#frameDiv').modal('closeModal');
                        self.sendPaymentStatus('success');
                    },
                    errorCallback: function (data) {
                        console.error('Payment Failed');
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                        self.sendPaymentStatus('error');
                    },
                    cancelCallback: function () {
                        console.log('Payment Cancelled');
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                        self.sendPaymentStatus('cancel');
                    }
                };


                // Get the checkout URL and set it as the iframe source
                var url = Checkout.Checkout.getCheckoutUrl();
                var iframe = document.getElementById("framePaymentPage");
                iframe.src = url;

                // Create a modal for the iframe
                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: false,
                    title: '',
                    buttons: [],
                    modalClass: 'no-header-footer',
                    opened: function () {
                        // Hide the loading mask when the modal is opened
                        $('.loading-mask').hide();
                    },
                    closed: function () {
                        // Place the order in Magento when the modal is closed
                        placeOrderAction(self.getData(), self.redirectAfterPlaceOrder).done(function () {
                            redirectOnSuccessAction.execute();
                        }).fail(function () {
                            // Handle order placement failure
                            console.error('Order placement failed');
                        });
                    }
                };

                var popup = modal(options, $('#frameDiv'));
                $('#frameDiv').modal('openModal');
            },

            sendPaymentStatus: function (status) {
                var quoteId = quote.getQuoteId();
                if (!quoteId) {
                    console.error('Order ID is undefined.');
                    return;
                }

                var payload = {
                    status: status, // 'success', 'error', 'cancel'
                    paymentData: {
                        quoteId: quoteId
                    }
                };

                var endpoint = urlBuilder.build('madfu_payment/payment/handlePaymentResult');

                console.log('Sending payment status to:', endpoint);
                console.log('Payload:', payload);

                return $.ajax({
                    url: endpoint,
                    type: 'POST',
                    contentType: 'application/json', // jQuery uses 'contentType' instead of 'headers'
                    data: JSON.stringify(payload),
                    success: function (data) {
                        if (data.success) {
                            console.log('Server response:', data.message);
                        } else {
                            console.error('Server error:', data.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Payment status handling error:', xhr.responseText || error);
                    }
                });
            },

        });
    }
);

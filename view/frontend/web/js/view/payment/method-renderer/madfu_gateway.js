define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'mage/url',
        'mage/storage',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Ui/js/modal/modal',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/action/place-order'
    ],
    function (
        Component,
        $,
        urlBuilder,
        storage,
        quote,
        fullScreenLoader,
        redirectOnSuccessAction,
        additionalValidators,
        modal,
        messageList,
        placeOrderAction
    ) {
        'use strict';

        function loadScript(url) {
            return new Promise((resolve, reject) => {
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                script.onload = function() {
                    resolve(true);
                };
                script.onerror = function() {
                    reject(new Error('Failed to load script ' + url));
                };
                document.head.appendChild(script);
            });
        }

        return Component.extend({
            defaults: {
                template: 'Madfu_MadfuPayment/payment/form',
                transactionResult: ''
            },

            initialize: function () {
                this._super();
                // Load the MadfuCheckout script based on the checkout URL provided via Magento config
                var checkoutUrl = window.checkoutConfig.payment.madfu_gateway.checkoutUrl;
                loadScript(checkoutUrl).then(function () {
                    console.log('MadfuCheckout script loaded successfully');
                    console.log('Checkout URL:', checkoutUrl);
                }).catch(function (error) {
                    console.error('Error loading MadfuCheckout script:', error.message);
                });
                return this;
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
                var customerData = window.customerData;
                var billingAddress = quote.billingAddress();
                var customerMobile = billingAddress.telephone;
                var customerName = billingAddress.firstname + ' ' + billingAddress.lastname;
                var locale = window.checkoutConfig.payment.madfu_gateway.locale;
                var lang = 'en'; // Default to English
                if (locale === 'ar_SA') {
                    lang = 'ar'; // Set to Arabic for Saudi locale
                }
                console.log('Locale:', lang);

                // Check if the user is logged in and use their data
                if (customerData && customerData.isLoggedIn) {
                    customerMobile = customerData.telephone || customerMobile;
                    customerName = customerData.firstname + ' ' + customerData.lastname || customerName;
                }

                // Validate Saudi mobile number
                var saudiMobileRegex = /^0(5\d{8})$/;
                var match = customerMobile.match(saudiMobileRegex);
                if (match) {
                    customerMobile = match[1]; // Remove the leading '0' if it's a valid Saudi number
                } else {
                    fullScreenLoader.stopLoader();
                    messageList.addErrorMessage({
                        message: 'Please enter a valid Saudi mobile number starting with 05 followed by 8 digits.'
                    });
                    return; // Stop execution if the phone number is invalid
                }

                var orderData = {
                    "GuestOrderData": {
                        "CustomerMobile": customerMobile,
                        "CustomerName": customerName,
                        "Lang": lang
                    },
                    "Order": {
                        "Taxes": quote.totals().tax_amount,
                        "ActualValue": quote.totals().grand_total,
                        "Amount": quote.totals().grand_total,
                        "MerchantReference": quote.getQuoteId() + '-mage' ,
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
                        self.sendPaymentStatus('success');
                        // Close the modal and trigger order placement
                        $('#frameDiv').modal('closeModal');
                        fullScreenLoader.startLoader();
                        placeOrderAction(self.getData(), self.redirectAfterPlaceOrder).done(function () {
                            redirectOnSuccessAction.execute();
                        }).fail(function () {
                            console.error('Order placement failed');
                        });
                    },
                    errorCallback: function (data) {
                        console.error('Payment Failed');
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                        $('#frameDiv').modal('closeModal');
                        messageList.addErrorMessage({ message: 'Payment failed. Please try again.' });
                    },
                    cancelCallback: function () {
                        console.log('Payment Cancelled');
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                        $('#frameDiv').modal('closeModal');
                        messageList.addErrorMessage({ message: 'Payment was cancelled.' });
                    },
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
                    clickableOverlay: false,
                    opened: function () {
                        // Hide the loading mask when the modal is opened
                        $('.loading-mask').hide();
                    },
                    closed: function () {
                        // Actions that should always take place when the modal is closed, regardless of payment outcome
                        console.log('Modal has been closed.');
                        fullScreenLoader.stopLoader();  // Ensure the loader is stopped in any case
                        self.isPlaceOrderActionAllowed(true);  // Re-enable the order action, necessary if the payment was not successful or cancelled
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

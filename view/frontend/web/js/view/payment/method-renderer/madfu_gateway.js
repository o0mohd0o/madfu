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

        function getDeviceType() {
            var userAgent = navigator.userAgent || navigator.vendor || window.opera;
            if (/android/i.test(userAgent)) {
                return 'Android';
            }
            if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                return 'iOS';
            }
            return '';
        }


        return Component.extend({
            defaults: {
                template: 'Madfu_MadfuPayment/payment/form',
                transactionResult: ''
            },

            initialize: function () {
                this._super();
                // Load the external jQuery library
                loadScript('https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js').then(function () {
                    console.log('External jQuery 3.6.0 has been loaded successfully');
                }).catch(function (error) {
                    console.error('Error loading external jQuery:', error.message);
                });
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
                return window.checkoutConfig.payment.madfu_gateway.title;
            },

            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();  // Prevent the default form submission event
                }

                // Retrieve the customer mobile number from billing address
                var billingAddress = quote.billingAddress();
                var customerMobile = billingAddress.telephone;

                // Validate the mobile number first
                if (!this.isValidSaudiNumber(customerMobile)) {
                    fullScreenLoader.stopLoader();
                    messageList.addErrorMessage({
                        message: 'Please enter a valid Saudi mobile number starting with 05 followed by 8 digits.'
                    });
                    return false; // Halt execution if the mobile number is invalid
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);  // Disable the place order button to prevent multiple submissions
                    fullScreenLoader.startLoader();  // Show a loading screen as the process begins

                    // Place the order first
                    placeOrderAction(this.getData(), false).done(function (orderPlacementResponse) {
                        var quoteId = quote.getQuoteId();  // Get the current quote ID
                        // Fetch the order ID from the controller
                        self.fetchOrderId(quoteId).done(function (response) {
                            var orderId = response.order_id; // Make sure the response has 'order_id'
                            if (orderId) {
                                self.createOrder(orderId).then(function (paymentResponse) {
                                    // Initiate the payment process with the token received from the server
                                    self.initIframe(paymentResponse.data.token);
                                }).catch(function (error) {
                                    console.error('Failed to initiate payment:', error);
                                    self.isPlaceOrderActionAllowed(true);
                                    fullScreenLoader.stopLoader();
                                    messageList.addErrorMessage({ message: 'Error initializing payment. Please try again.' });
                                });
                            } else {
                                console.error('Failed to fetch order ID:', response);
                                fullScreenLoader.stopLoader();
                                messageList.addErrorMessage({ message: 'Failed to fetch order ID. Please try again.' });
                            }
                        }).fail(function (error) {
                            console.error('Failed to fetch order ID:', error);
                            self.isPlaceOrderActionAllowed(true);
                            fullScreenLoader.stopLoader();
                            messageList.addErrorMessage({ message: 'Failed to fetch order ID. Please try again.' });
                        });
                    }).fail(function (error) {
                        console.error('Order placement failed:', error);
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                        messageList.addErrorMessage({ message: 'Order placement failed. Please try again.' });
                    });
                }

                return false;  // Prevent the default form submission
            },


            fetchOrderId: function() {
                var quoteId = quote.getQuoteId();  // Assuming you have access to quote object
                var url = urlBuilder.build('madfu_payment/order/getOrderId') + '?quote_id=' + quoteId;
                return $.ajax({
                    url: url,
                    type: 'GET',
                    contentType: 'application/json'
                });
            },

            isValidSaudiNumber: function (customerMobile) {
                var saudiMobileRegex = /^0(5\d{8})$/;
                var match = customerMobile.match(saudiMobileRegex);
                if (match) {
                    // Return the valid mobile number without the leading '0'
                    return match[1];
                }
                return false; // Return false if the number is invalid
            },

            createOrder: function (orderId) {
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

                // Format the Saudi mobile number by removing the leading '0'
                if (customerMobile.startsWith('0')) {
                    customerMobile = customerMobile.substring(1);
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
                        "MerchantReference": orderId,
                    },
                    "OrderDetails": quote.getItems().map(function (item) {
                        console.log('Item:', item);
                        return {
                            "productName": item.name,
                            "SKU": item.sku,
                            "productImage": item.thumbnail,
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
                        self.sendPaymentStatus('success');
                        // Close the modal and directly proceed to the success page after a short delay
                        setTimeout(function() {
                            fullScreenLoader.startLoader();
                            $('#frameDiv').modal('closeModal');
                            redirectOnSuccessAction.execute();  // Redirect to the success page directly
                        }, 3000);
                    },
                    errorCallback: function (data) {
                        console.error('Payment Failed');
                        self.sendPaymentStatus('failed');
                        // Close the modal and directly proceed to the success page after a short delay
                        setTimeout(function() {
                            fullScreenLoader.startLoader();
                            $('#frameDiv').modal('closeModal');
                            redirectOnSuccessAction.execute();
                        }, 3000);
                    },
                    cancelCallback: function () {
                        self.sendPaymentStatus('canceled');
                        // Close the modal and directly proceed to the success page after a short delay
                        setTimeout(function() {
                            fullScreenLoader.startLoader();
                            $('#frameDiv').modal('closeModal');
                            redirectOnSuccessAction.execute();
                        }, 3000);
                    },
                    deviceType: getDeviceType()
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
                    }

                };

                var popup = modal(options, $('#frameDiv'));
                $('#frameDiv').modal('openModal');

                // Close the modal when the back button is pressed
                window.onpopstate = function () {
                    $('#frameDiv').modal('closeModal');
                    fullScreenLoader.stopLoader();
                    self.isPlaceOrderActionAllowed(false);
                };
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

                return $.ajax({
                    url: endpoint,
                    type: 'POST',
                    contentType: 'application/json', // jQuery uses 'contentType' instead of 'headers'
                    data: JSON.stringify(payload),
                    success: function (data) {
                        if (data.success) {
                            // console.log('Server response:', data.message);
                        } else {
                            // console.error('Server error:', data.message);
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

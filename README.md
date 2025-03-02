# Madfu Payment Module for Magento 2

This module integrates the Madfu payment gateway with Magento 2, allowing customers to make payments using Madfu's payment services.

## Test Credentials

### Test Credit Card
- **Card Type**: Visa Card
- **Number**: 4111 1111 1111 1111
- **CVV**: 123
- **Expiry**: 05/25
- **Name**: test
- Valid for all payment methods

### Test API Credentials
- **AUTHORIZATION**: Basic ZGF6bWU6WkdGNmJXVkFNell3Tnc9PQ==
- **APPCODE**: dazme
- **APIKEY**: 691b6b6f-fdde-4b49-84ab-4

### Dashboard Access
- **URL**: [Madfu Vendor Dashboard](https://vendor-staging-new.madfu.com.sa/auth/login)
- **User**: admin@dazme.sa
- **Password**: Welcome@123

### Test OTP
- Use **1001** for OTP verification during testing

### Order Creation Credentials
Use these credentials when calling the Sign In API:
- **USERNAME**: Cashier@dazme.sa
- **PASSWORD**: Welcome@123

## API Documentation
For detailed API documentation, visit: [Madfu API Documentation](https://madfuapis.readme.io/reference/inittoken)

## Key Features
- Seamless integration with Magento 2 checkout
- Support for shipping address in payment requests
- Dynamic token generation for secure transactions
- Comprehensive error handling

## Implementation Details
This module implements:
1. A custom payment method for Magento 2
2. Integration with Madfu's payment API
3. Token-based authentication
4. Order status management based on payment results

## Configuration
The module can be configured in the Magento admin panel under:
Stores > Configuration > Sales > Payment Methods > Madfu Payment Gateway

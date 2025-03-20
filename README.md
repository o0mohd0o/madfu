# Madfu Payment Module for Magento 2

This module integrates the Madfu payment gateway with Magento 2, allowing customers to make payments using Madfu's payment services.

## Installation

### Manual Installation
1. Create the following directory structure in your Magento installation: `app/code/Madfu/MadfuPayment`
2. Copy all module files to the directory you created, maintaining the folder structure
3. Ensure proper file permissions (typically 644 for files and 755 for directories)
4. Enable the module by running the following commands from your Magento root directory:
   ```bash
   php bin/magento module:enable Madfu_MadfuPayment
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento setup:static-content:deploy -f
   php bin/magento cache:clean
   php bin/magento cache:flush
   ```
5. Verify the module is installed correctly by checking if it appears in the list of enabled modules:
   ```bash
   php bin/magento module:status Madfu_MadfuPayment
   ```

### Post-Installation
After installing the module, you need to configure it in the Magento admin panel:
1. Log in to your Magento admin panel
2. Navigate to Stores > Configuration > Sales > Payment Methods
3. Find and expand the 'Madfu Payment Gateway' section
4. Enter your API credentials and configure the payment method settings
5. Save the configuration

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

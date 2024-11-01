### Trust Payments Gateway for WooCommerce (JavaScript Library)
Contributors: illustratedigital
<br /><br />Tags: payment, trust payments, woocommerce, gateway, api, 3dsv2
<br />Requires at least: 5.2
<br />Tested up to: 6.6.1
<br />Requires PHP: 7.4
<br />Stable tag: 1.3.6
<br />License: GPLv3 or later
<br />License URI: http://www.gnu.org/licenses/gpl-3.0.html

The Trust Payments plugin offers a simple and easy to implement method for merchants to add e-payment capabilities to their WooCommerce online commerce setup.

### Description
The Trust Payments payment gateway for WooCommerce. Easily take payments using Trust Payments on your WooCommerce based store. Now with support for 3D secure v2.

### Features
With Trust Payments, merchants can:

* Accept all major payment cards supported (Visa, Mastercard, Amex, Diners/Discover)
* Support full integration with API, allowing refunds via back office
* Feature the gateway on your existing website by using a JavaScript Library integration
* Reduce your level of PCI DSS compliance to the lowest possible level by not handling sensitive payment data 
* Benefit from the “Saved Cards” feature, returning customers can save their card details for faster transactions in the future	
* Accept a large variety of currencies and settle in 15 of these 
* Restrict payment methods from being offered in specific countries
* Deploy other new payment methods quickly with minimal configuration needed
* View and manage all transactions using our online portal including a Virtual Terminal for transactions not processed in-person
* Offer recurring subscriptions


### Installation
#### Using the Wordpress Dashboard

**Please note: This plugin requires WooCommerce to work. Please install WooCommerce before proceeding.**

1. Navigate to Plugins -> Add New
2. Use the search field on the top right and enter "Trust Payments Gateway for WooCommerce (JavaScript Library)"
3. Click the "Install Now" button to install the plugin
4. Click the "Activate" button or navigate to Plugins -> Installed Plugins -> Find the "Trust Payments Gateway for WooCommerce (JavaScript Library)" plugin in the list and click "Activate"
5. Next, you are ready to configure the plugin with your unique account details provided by our Support team

#### Manual Installation - Using FTP

Manual installation method requires downloading the WooCommerce plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://wordpress.org/support/article/managing-plugins/).

### Frequently Asked Questions

#### How our service works?
Find out more about [our services](https://www.trustpayments.com/)

#### What are Terms of Use?
Please follow our terms of use at [Trust Payment - Terms of Use](https://www.trustpayments.com/legal-terms-of-use/)

#### Where can I find the user guide of the WooCommerce Trust Payments Gateway plugin?
You can find more information on the WooCommerce Trust Payments plugin [here](https://help.trustpayments.com/hc/en-us/sections/9682575230225-WooCommerce-using-API)

#### Where can I get support or talk to other users?
If you get stuck, you can contact us [here](https://www.trustpayments.com/contact-us/)

### Screenshots
1. Enter your card details
2. Front-end data input for credit card
3. Comprehensive back-end options to configure the plugin

### Changelog
#### 1.3.6
* Resolved a bug that caused duplicate order notes in WooCommerce
* Formatted the markup in the readme file
#### 1.3.5
* Updated support for PHP error messages.
* Updated customer address PHP variables.
* Fixed an issue where an address change wouldn't be saved
* Removed \n from updateJWT string.
* Fixed COF=1 not sent when saving card.
* Fixed issue which caused duplicated payment form.
* Fixed an issue where a Subscription signup fee would be missing from the total amount
* Fixed an issue where the checkout could freeze when using a coupon
* Fixed an issue where the billing name is sent to Trust Payments when using the Guest checkout
* Fixed JSINIT was active if plugin was disabled.
#### 1.3.4
* Updated how the cart is calculated to prevent conflicts with some plugins
* Changed an exit to a return as the exit was stopping other functions that were hooked into wp_footer from running
#### 1.3.3
* Changed the name of the plugin to  Trust Payments Gateway for WooCommerce (JavaScript Library)
* Fixed an issue where discounts were not being applied in some situations
* Fixed an issue where declined recurring transactions were showing as successful
* Added more information into some of the error messages
* Fix an issue with free trials not working properly with subscriptions
* Fixed an issue with some coupons not working with subscriptions
* Fixed na issue where declined refunds were sometimes showing as successful in WooCommerce
* Added the ability to buy multiple subscriptions at once
* Updated the error messages for decline payments
* VAT is now added to the total amount (base amount)
#### 1.3.2
* Removed from being default payment gateway
#### 1.3.1
* Fixed an issue with the wrong title being displayed
* Fixed and issue where the a subscription using a coupon applied the incorrect initial price
#### 1.3
* Various subscription updates.
* Fixed issue with billing name being sent.
* Fixed issue with JavaScript initialisation on account creation.
* Fixed issue when using special characters in billing name.
#### 1.2.7
* WordPress 6.0 compatibility
* Authentication updates and improvements
#### 1.2.6
* Security patches
#### 1.2.5
* Endpoint amends
#### 1.2.4
* Security patches
#### 1.2.3
* Security patches
#### 1.2.2
* Security patches
* Added Cloudflare IP validation
#### 1.2.0
* Updated documentation link
* Added fix for missing registration emails
* Fixed an issue with basemount field
* Fixed undefined address for virtual items
* Changed wording 'Expiration date' to 'Expiry date'
* Improved refund logic
#### 1.1.2
* Updated payment confirmation url
#### 1.1.1
* Cleaned up description
#### 1.1.0
* Added URL notifications in order to improve order status updates
* Fixed some jQuery checkout errors present for users who have jquery migrate disabled
* Improvements to debug logs
* Added a fix for missing order details at checkout confirmation
* Improved guest checkout
#### 1.0.5
* Improved order reference IDs
#### 1.0.4
* Fixed issue with description on the checkout page
* Checkout confirmation page updated to display all purchased products and customer address details
* Debug logging no longer shows when Live mode is active
#### 1.0.3
* Added extra check before updating order with payment details - Special thanks to Mikkel R for alerting us to this
#### 1.0.2
* Made description compulsory, as needed to display form
#### 1.0.1
* Live mode enabled by default
* Javascript only enabled on checkout
* Tightened security on payment function
* Tightened security on create user function
* Added official documentation link
#### 1.0.0
* Initial release

### Upgrade Notice

#### 1.2.1
Please update as soon as possible, this update fixes some security issues with v1.2.0.
#### 1.2.4
Please update as soon as possible, this update fixes some security issues with previous versions.
#### 1.2.5
Fixes transaction endpoint issue in v1.2.4
#### 1.2.6
Please update as soon as possible, this update fixes some security issues with previous versions.
<?php
/**
 * Plugin Name:       Trust Payments Gateway ( 3DS2 )
 * Description:       Extends WooCommerce with the official Trust Payments payment gateway with 3DSv2 support. Visit <a href="https://www.trustpayments.com/" target=_blank">Trust Payments</a> for more info.
 * Version:           1.3.6
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Tested up to:      6.6.1
 * Author:            Trust Payments
 * Author URI:        https://www.trustpayments.com
 * License:           GPL v3
 * Text Domain:       wc-gateway-tp
 * Domain Path:       /i18n/languages/.
 *
 * Copyright: (c) 2015-2021 Trust Payments and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    Trust Payments
 *
 * @category  Admin
 *
 * @copyright Copyright (c) 2015-2021, Trust Payments. and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This tp gateway forks the WooCommerce core "Cheque" payment gateway to create another tp payment method.
 */
defined('ABSPATH') || exit;
define('WC_TP_Gateway_Version', '1.3.6');

// Make sure WooCommerce is active.
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
    return;
}
// Make sure this plugin is active.
if (!in_array('trust-payments-gateway-3ds2/trust-payments-gateway-3ds2.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
	return;
}

/**
 * Add the gateway to WC Available Gateways.
 *
 * @since 1.0.0
 *
 * @param array $gateways all available WC gateways
 *
 * @return array $gateways all WC gateways + tp gateway
 */
function tpgw_add_to_gateways($gateways)
{
    $gateways[] = 'WC_TP_Gateway';

    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'tpgw_add_to_gateways');

/**
 * Adds plugin page links.
 *
 * @since 1.0.0
 *
 * @param array $links all plugin links
 *
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function tpgw_gateway_plugin_links($links)
{
    // Add a link to the plugin settings.
    $plugin_links = [
        '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=tp_gateway').'">'.__('Configure', 'wc-gateway-tp').'</a>',
    ];

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'tpgw_gateway_plugin_links');

/**
 * Include the class files needed.
 *
 * @return void
 */
function tpgw_woocommerce_gateway()
{
    include_once plugin_dir_path(__FILE__).'classes/class-wc-tp-gateway.php';
}
add_action('plugins_loaded', 'tpgw_woocommerce_gateway', 11);

// If Plugins > trust-payments-gateway-3ds2 is set to active, lets check if the admin settings are set to active too.
if ( empty( get_option( 'woocommerce_tp_gateway_settings' ) ) || 'no' === get_option( 'woocommerce_tp_gateway_settings' )['enabled'] ) {
	// The admin setting is set to inactive, so lets stop here.
	return;
}

/**
 * Include JS scripts.
 */
function tpgw_include_js_scripts()
{
    if (is_checkout()) {
        wp_enqueue_script(
            'webservices-securetrading',
            'https://cdn.eu.trustpayments.com/js/latest/st.js',
            [],
            '1.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'tpgw_include_js_scripts', 11);

/**
 * Set as default payment gateway.
 */
function define_default_payment_gateway()
{
    if (is_checkout() && !is_wc_endpoint_url()) {
        $default_payment_id = 'tp_gateway';
        WC()->session->set('chosen_payment_method', $default_payment_id);
    }
}
// add_action('template_redirect', 'define_default_payment_gateway');

/**
 * Ajax function to handle ajax payment.
 *
 * @return nothing or false depening on success
 */
function tpgw_handle_payment()
{
    global $wpdb;

    // Verify nonce.
    // If nonce is not valid we should exit here.
    $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!empty($_POST) && !wp_verify_nonce($nonce, 'checkout-nonce')) {
        echo 'Invalid Payment Nonce';
        exit();
    }

    // Get woocommerce tp gateway settings.
    $settings = get_option('woocommerce_tp_gateway_settings');

    // Set $_POST values.
    $post_orderid = (!empty($_POST['orderid'])) ? (int) $_POST['orderid'] : null;
    $post_orderrnd = (!empty($_POST['orderrnd'])) ? sanitize_text_field(wp_unslash($_POST['orderrnd'])) : null;
    $post_rules = (!empty($_POST['orderrules'])) ? sanitize_text_field(wp_unslash($_POST['orderrules'])) : null;
    $post_transactionreference = (!empty($_POST['transactionreference'])) ? sanitize_text_field(wp_unslash($_POST['transactionreference'])) : null;
    $post_transactiondata = (!empty($_POST['transactiondata'])) ? sanitize_text_field(wp_unslash($_POST['transactiondata'])) : null;
    $post_savecreditcardoption = (!empty($_POST['savecreditcardoption'])) ? sanitize_text_field(wp_unslash($_POST['savecreditcardoption'])) : null;
    $post_maskedpan = (!empty($_POST['maskedpan'])) ? sanitize_text_field(wp_unslash($_POST['maskedpan'])) : null;
    $post_paymenttypedescription = (!empty($_POST['paymenttypedescription'])) ? sanitize_text_field(wp_unslash($_POST['paymenttypedescription'])) : null;
    $post_settlestatus = (!empty($_POST['settlestatus'])) ? sanitize_text_field(wp_unslash($_POST['settlestatus'])) : null;

    // Process transaction data for the rule details.
    if (!empty($post_transactiondata)) {
        // Get transaction data.
        $json_str_array = json_decode($post_transactiondata, true);
        // If we have a result.
        if (!empty($json_str_array)) {
            // Get post meta for billing search.
            $post_meta = get_post_meta($post_orderid, '_billing_address_index');
            if (!empty($post_meta)) {
                // Append orderreference to the end of the search string.
                // ( so we can search for the orderreference on admin > orders page ).
                $post_meta = $post_meta[0].' '.$json_str_array['orderreference'];
                // Update post meta.
                update_post_meta($post_orderid, '_billing_address_index', $post_meta);
            }
            // get tp gateway settings.
            $settings = get_option('woocommerce_tp_gateway_settings');
            // get cvv2 required setting.
            $cvv2_required = ( ! empty( $settings['cvv2_required'] ) ) ? $settings['cvv2_required'] : '';
            // Set default status text value.
            $status = 'successful';
            // Set default error value.
            $error = false;
            // Loop for every rule.
            if ( ! empty($json_str_array['rules']) ) {
                foreach ($json_str_array['rules'] as $rule) {
                    // Successful authorisation.
                    if ('STR-8' === $rule['ruleidentifier']) {
                        // Set status text value.
                        $status = 'successful';
                        // Set error value.
                        $error = false;
                        break;
                    }
                    // Declined authorisation.
                    if ('STR-9' === $rule['ruleidentifier']) {
                        // Set status text value.
                        $status = 'declined';
                        // Set error value.
                        $error = true;
                        break;
                    }
                }
            }
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save to woocommerce log.
                if ( ! empty( $json_str_array['rules'] ) ) {
                    $logger = wc_get_logger();
                    $logger->debug(
                        '['.WC_TP_Gateway_Version.'] Order POST Data: Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - '.wc_print_r($json_str_array['rules'], true),
                        ['source' => 'trustpayments']
                    );
                }
                // Save to woocommerce log.
                if ( ! empty( $rule['ruleidentifier'] ) ) {
                    $logger = wc_get_logger();
                    $logger->debug(
                        '['.WC_TP_Gateway_Version.'] Order POST Data ( Result ): Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - Result: Rule identifier '.$rule['ruleidentifier'].', Status '.$status.'.',
                        ['source' => 'trustpayments']
                    );
                }
            }
            // If status is not successful.
            if ('successful' !== $status) {
                // We shouldn't go any further.
                // echo 'error: status non-successful';
                return;
            }
        }
    } else {
        // Debug.
        if ('yes' === $settings['enabled_log_details']) {
            // Save to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] Order POST Error: Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - Result: Post transaction data is empty.',
                ['source' => 'trustpayments']
            );
        }
        // We shouldn't go any further.
        // echo 'error: post transaction null';
        return;
    }

    // If not first subscription. 
    $subscriptiondata = json_decode( $post_transactiondata );
    if ( ! empty( $subscriptiondata->subscriptionnumber ) && $subscriptiondata->subscriptionnumber != "1" && $subscriptiondata->errorcode === "0" 
        || empty( $subscriptiondata->subscriptionnumber ) && $subscriptiondata->errorcode === "0" ) {
        // Add woocommerce order id to myst.
        $myst_updated = tpgw_add_woocommerce_order_id_to_myst($post_transactionreference, $post_orderid);
        // If myst isn't updated.
        if (empty($myst_updated)) {
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Order Update Failed: Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - Result: Add Woocommerce Order ID to MyST failed.',
                    ['source' => 'trustpayments']
                );
            }
            // We shouldn't go any further.
            // echo "error: order id rejected";
            return;
        }
    }

    // confirm post order data.
    $payment_confirmed = confirm_post_order_data( $post_orderid, $post_transactionreference) ;
    if ( 'Ok' !== $payment_confirmed ) {
        // We shouldn't go any further.
        // echo "error: payment confirmation issue";
        return;
    }

    // get current user id ( user must be logged in ).
    $current_user = wp_get_current_user();
    $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;

    // Get the WC Order.
    $order = new WC_Order($post_orderid);

    // If we have an order value.
    if (!empty($order) && is_object($order)) {
        global $woocommerce;
        // Add note.
        $order->add_order_note('Trust Payments Ref: '.$post_transactionreference);
        // Save transaction reference.
        update_post_meta($post_orderid, '_tp_transaction_reference', $post_transactionreference);
        // Save transaction data for this purchase.
        update_post_meta($post_orderid, '_tp_transaction_data', $post_transactiondata);
        // Save credit card option to db so user doesnt need to enter all their credit card details on next purchase, just the basics only.
        if (!empty($userid) && !empty($post_savecreditcardoption)) {
            // create unique id so we can save multiple credit cards.
            $uniqid = gmdate('U');
            // add reference.
            update_user_meta($userid, '_tp_transaction_reference.'.$uniqid, $post_transactionreference);
            // add maskedpan.
            update_user_meta($userid, '_tp_transaction_maskedpan.'.$uniqid, $post_maskedpan);
            // add paymenttypedescription.
            update_user_meta($userid, '_tp_transaction_paymenttypedescription.'.$uniqid, $post_paymenttypedescription);
            // add use previous credit card.
            update_user_meta($userid, '_tp_transaction_use_saved_card.'.$uniqid, $post_savecreditcardoption);
        }
        // Get post meta for billing search.
        $post_meta = get_post_meta($post_orderid, '_billing_address_index');
        if (!empty($post_meta)) {
            // Append transaction reference value to the end of the search string.
            // ( so we can search for the transaction reference value on admin > orders page ).
            $post_meta = $post_meta[0].' '.$post_transactionreference;
            // Update post meta.
            update_post_meta($post_orderid, '_billing_address_index', $post_meta);
        }
        // If settlestatus = 2 ( on-hold ).
        if ('2' === $json_str_array['settlestatus'] || 2 === $json_str_array['settlestatus']) {
            // Change order status to on-hold.
            $order->update_status('on-hold');
            // Add order note.
            $order->add_order_note('Trust Payments Settlement Suspended.');
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Order Update Failed: Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - Result: Trust Payments Settlement Suspended.',
                    ['source' => 'trustpayments']
                );
            }
        } else {
            // Get Order Key value.
            $order_key = $order->get_order_key();
            // Set order complete.
            $order->payment_complete();
            // Save order.
            $order->save();
            // Empty the cart.
            $woocommerce->cart->empty_cart();
			// Return key value so we can show the order details.
			echo $order_key;
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Order Update Success: Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - Result: Order details saved successfully.',
                    ['source' => 'trustpayments']
                );
            }
            // Exit.
            exit();
        }

    // Else, the order is invalid.
    } else {
        // Debug.
        if ('yes' === $settings['enabled_log_details']) {
            // Save to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] Order Error: Order ID '.$post_orderid.' Transaction Ref '.$post_transactionreference.' - Result: Order object is empty.',
                ['source' => 'trustpayments']
            );
        }
        // Exit here.
        // echo "error: order is invalid";
        return false;
    }
    exit();
}
add_action('wp_ajax_tpgw_handle_payment', 'tpgw_handle_payment');
add_action('wp_ajax_nopriv_tpgw_handle_payment', 'tpgw_handle_payment');

/**
 * Confirm post order data is correct.
 *
 * @param int    $post_orderid         order id
 * @param string $transactionreference tansaction reference
 *
 * @return string emptty or 'ok'
 */
function confirm_post_order_data($post_orderid = 0, $transactionreference = '')
{
    // If we have a transaction reference.
    // Let's get the confirmed transaction details.
    if (!empty($transactionreference)) {
        // Get tp gateway settings.
        $settings = get_option('woocommerce_tp_gateway_settings');

        // Check if this order has been paid before.
        $date_paid = get_post_meta($post_orderid, '_date_paid');
        if (!empty($date_paid[0])) {
            // Already paid, lets exit here.

            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save response to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Confirm post order data: Post order id: '.$post_orderid.', Transaction reference: '.$transactionreference.', Date paid: '.$date_paid[0],
                    ['source' => 'trustpayments']
                );
            }

            return 'Ok';
        }

        // Get settings details.
        $userpwd = $settings['ws_username'].':'.$settings['ws_password'];
        $alias = $settings['ws_username'];
        $sitereference = $settings['sitereference'];

        // Create args for transaction data.
        $args = [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($userpwd),
            ],
            'body' => '{
				"alias":"'.$alias.'",
				"version":"1.0",
				"request":[
				{
					"requesttypedescriptions":[
						"TRANSACTIONQUERY"
					],
					"filter":{
						"sitereference":[
							{
							"value":"'.$sitereference.'"
							}
						],
						"transactionreference":[
							{
							"value":"'.$transactionreference.'"
							}
						]
					}
				}
				]
			}',
        ];
        // Get response.
        $response = wp_remote_post('https://webservices.securetrading.net/json/', $args);
        $response_body = wp_remote_retrieve_body($response);
        $json_response = json_decode($response_body);

        // Debug.
        if ('yes' === $settings['enabled_log_details']) {
            // Save response to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] TRANSACTIONQUERY Request: '.wc_print_r($json_response, true),
                ['source' => 'trustpayments']
            );
        }

        // If response error message is OK ( alls good ), payment is confirmed.
        if (!empty($json_response->response[0]->errormessage) && 'Ok' === $json_response->response[0]->errormessage) {
            return 'Ok';
        }
    }
}

/**
 * Add woocommerce order id to MyST.
 *
 * @param string $transactionreference Eg. 1-2-3.
 * @param int    $orderreference       Eg. 123.
 */
function tpgw_add_woocommerce_order_id_to_myst($transactionreference = '', $orderreference = '')
{
    // get tp gateway settings.
    $settings = get_option('woocommerce_tp_gateway_settings');

    // get purchase details.
    $userpwd = $settings['ws_username'].':'.$settings['ws_password'];
    $alias = $settings['ws_username'];
    $sitereference = $settings['sitereference'];

    // Issue Refund.
    $args = [
        'headers' => [
            'Authorization' => 'Basic '.base64_encode($userpwd),
        ],
        'body' => '{
            "alias":"'.$alias.'",
            "version":"1.0",
            "request": [{
                "requesttypedescriptions": ["TRANSACTIONUPDATE"],
                "filter":{
                    "sitereference": [{"value":"'.$sitereference.'"}],
                    "transactionreference":[{"value":"'.$transactionreference.'"}]
                },
                "updates":{
                    "orderreference":"'.$orderreference.'"
                }
                }]
            }',
    ];
    $response = wp_remote_post('https://webservices.securetrading.net/json/', $args);
    $response_body = wp_remote_retrieve_body($response);
    $json_response = json_decode($response_body);

    // If Unauthoried access response.
    // ( trust payments doesnt include the unauthorised access error in the results array.
    // instead, it returns the unathorized error message in a seperate html format/page ).
    if (strpos($response_body, 'Unauthorized') !== false) {
        // Debug.
        if ('yes' === $settings['enabled_log_details']) {
            // Save error to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] MyST Update Failed: Order ID '.$orderreference.' Transaction Ref '.$transactionreference.' - Error: Unauthorised access response from Webservices.',
                ['source' => 'trustpayments']
            );
        }
    } else {
        // Debug.
        if ('yes' === $settings['enabled_log_details']) {
            // Save args to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] MyST Update Args: Order ID '.$orderreference.' Transaction Ref '.$transactionreference.' - '.wc_print_r($args, true),
                ['source' => 'trustpayments']
            );
            // Save response to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] MyST Update Response: Order ID '.$orderreference.' Transaction Ref '.$transactionreference.' - '.wc_print_r($json_response, true),
                ['source' => 'trustpayments']
            );
        }
    }  

    // Process response.
    if (!empty($json_response->response)) {
        foreach ($json_response->response as $response) {
            if ('0' !== $response->errorcode) {
                return ''; // if error.
            } else {
                return 'success'; // else successful.
            }
        }
    }
}

/**
 * Reset saved purchase card.
 */
function tpgw_reset_purchase_card()
{
    // Set $_GET values.
    $get_reset = (!empty($_GET['reset'])) ? sanitize_text_field(wp_unslash($_GET['reset'])) : null;

    // if reset is required.
    if (isset($get_reset)) {
        // get userid.
        $userid = get_current_user_id();
        // if we have reset data.
        if (!empty($get_reset)) {
            // set this users specific card to inactive ( using _tp_transaction_use_saved_card.XXXXXXX value ).
            $saved_card_inactive = '_tp_transaction_use_saved_card.'.$get_reset;
            // update.
            update_user_meta($userid, $saved_card_inactive, 0);
        }
        // redirect page.?>
		<script>	
		window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>';
		</script>
		<?php
    }
}
add_action('init', 'tpgw_reset_purchase_card');

/**
 * Refund puchase.
 */
function tpgw_refund_purchase()
{
    // Verify nonce.
    // If nonce is not valid we should exit here.
    $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!empty($_POST) && !wp_verify_nonce($nonce, 'refund-nonce')) {
        echo 'Invalid Refund Nonce';

        return;
    }

    // Get logged in users roles.
    $user = wp_get_current_user();
    $roles = (array) $user->roles;

    // If we dont have any user roles, stop here.
    if (empty($user->roles)) {
        echo 'No User Roles';

        return;
    } else { // else, if this user roles include customer we dont need to go any further.
        foreach ($roles as $role) {
            if ('customer' === $role) {
                echo 'Invalid Role';

                return;
            }
        }
    }

    // Set $_POST values.
    $post_baseamount = (!empty($_POST['baseamount'])) ? sanitize_text_field(wp_unslash($_POST['baseamount'])) : null;
    $post_parenttransactionreference = (!empty($_POST['parenttransactionreference'])) ? sanitize_text_field(wp_unslash($_POST['parenttransactionreference'])) : null;
    $post_orderid = (!empty($_POST['orderid'])) ? (int) $_POST['orderid'] : null;

    // get logged in userid.
    $userid = get_current_user_id();
    if (!$userid) {
        return;
    }

    // if refund is required.
    if (isset($post_baseamount)) {
        // get tp gateway settings.
        $settings = get_option('woocommerce_tp_gateway_settings');

        // get purchase details.
        $userpwd = $settings['ws_username'].':'.$settings['ws_password'];
        $alias = $settings['ws_username'];
        $sitereference = $settings['sitereference'];
        $parenttransactionreference = $post_parenttransactionreference;
        $baseamount = round($post_baseamount, 0);
        $orderreference = $post_orderid;

        // Issue Refund.
        $args = [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($userpwd),
            ],
            'body' => '{
				"alias":"'.$alias.'",
				"version":"1.0",
				"request":[{
					"requesttypedescriptions":["REFUND"],
					"sitereference":"'.$sitereference.'",
					"parenttransactionreference":"'.$parenttransactionreference.'",
					"baseamount":"'.$baseamount.'",
					"orderreference":"'.$orderreference.'"
				}]
			}',
        ];
        $response = wp_remote_post('https://webservices.securetrading.net/json/', $args);
        $response_body = wp_remote_retrieve_body($response);

        // Save REQUEST response to woocommerce log.
        if ('yes' === $settings['enabled_log_details']) {
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] MyST Refund: '.wc_print_r($args, true),
                ['source' => 'trustpayments']
            );
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] MyST Refund: '.wc_print_r($response_body, true),
                ['source' => 'trustpayments']
            );
        }

        // check if the response states it's an unauthorised action.
        $pos = strpos($response_body, 'Unauthorized');
        if (false !== $pos) {
            print_r($response_body);
            exit();
        }

        // check if result is error.
        $json = json_decode($response_body, true);
        if ('0' !== $json['response'][0]['errorcode']) {
            // return error message.
            print_r($json['response'][0]['errormessage']);
            exit();
        }

        // if result is authorised.
        if (false === strpos($response_body, 'Unauthorized')) {
            // add refund message to orders > edit order > order notes section in admin area.
            $order = wc_get_order($post_orderid);
            if (is_object($order)) {
                $total = $post_baseamount / 100;
                $order->add_order_note('Trust Payments Refund: '.get_woocommerce_currency_symbol().''.number_format($total, 2, '.', ''));
            }
        }

        // return result details.
        print_r($response_body);
    } else {
        // No base amount value has been set.
        print_r('NoBaseAmountValue');
    }
}
add_action('wp_ajax_tpgw_refund_purchase', 'tpgw_refund_purchase');
add_action('wp_ajax_nopriv_tpgw_refund_purchase', 'tpgw_refund_purchase');

// Prevent user data from being added to WordPress.
add_filter('woocommerce_checkout_update_customer_data', '__return_false');

/**
 * Log debug.
 */
function tpgw_log_debug()
{
    // Verify nonce.
    // If nonce is not valid we should exit here.
    $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!empty($_POST) && !wp_verify_nonce($nonce, 'log-debug-nonce')) {
        echo '['.WC_TP_Gateway_Version.'] Invalid Log Debug Nonce';
        exit();
    }

    // Set $_POST values.
    $post_message = (!empty($_POST['message'])) ? sanitize_text_field(wp_unslash($_POST['message'])) : null;
    $post_orderid = (!empty($_POST['orderid'])) ? (int) $_POST['orderid'] : null;

    // If we have an error message.
    if (isset($post_message)) {
        // Get Message.
        $message = str_replace('\\', '', $post_message);
        // Set order id.
        $order_id = (!empty($post_orderid)) ? 'Order ID: '.$post_orderid.' - ' : '';
        // Get log.
        $logger = wc_get_logger();
        // Add JSON Message.
        if (str_contains($message, 'ST Config')) {
            $message = str_replace('ST Config:', '', $message);
            $message = str_replace('ST Config', '', $message);
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] Log Debug: ST Config: '.json_encode(json_decode($message), JSON_PRETTY_PRINT),
                ['source' => 'trustpayments']
            );
        } elseif (str_contains($message, 'TP Submit Callback')) {
            $message = str_replace('TP Submit Callback:', '', $message);
            $message = str_replace('TP Submit Callback', '', $message);
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] Log Debug: TP Submit Callback: '.json_encode(json_decode($message), JSON_PRETTY_PRINT),
                ['source' => 'trustpayments']
            );
        } else {
            // Add message.
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] Log Debug: '.wc_print_r($order_id.$message, true),
                ['source' => 'trustpayments']
            );
        }
    }

	return '';
}
add_action('wp_ajax_tpgw_log_debug', 'tpgw_log_debug');
add_action('wp_ajax_nopriv_tpgw_log_debug', 'tpgw_log_debug');

/**
 * Add billing/customer address for MyST.
 */
function tpgw_update_address_myst()
{
    // Verify nonce.
    // If nonce is not valid we should exit here.
    $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!empty($_POST) && !wp_verify_nonce($nonce, 'update-address-myst-nonce')) {
        echo '['.WC_TP_Gateway_Version.'] Invalid Update Address MyST Nonce';
        exit();
    }

    // get tp gateway settings.
    $settings = get_option('woocommerce_tp_gateway_settings');

    // Debug.
    if ('yes' === $settings['enabled_log_details']) {
        // Save REQUEST response to woocommerce log.
        $logger = wc_get_logger();
        $logger->debug(
            '['.WC_TP_Gateway_Version.'] POST DATA: '.wc_print_r($_POST, true),
            ['source' => 'trustpayments']
        );
    }

    // Set $_POST update address value.
    $post_update_address = (!empty($_POST['update_address'])) ? sanitize_text_field(wp_unslash($_POST['update_address'])) : '';
    // Set $_POST orderid value.
    $post_orderid = (!empty($_POST['orderid'])) ? (int) $_POST['orderid'] : 0;
    // Set $_POST billing values.
    $post_billing_first_name = (!empty($_POST['billing_first_name'])) ? sanitize_text_field(wp_unslash($_POST['billing_first_name'])) : '';
    $post_billing_last_name = (!empty($_POST['billing_last_name'])) ? sanitize_text_field(wp_unslash($_POST['billing_last_name'])) : '';
    $post_billing_address_1 = (!empty($_POST['billing_address_1'])) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '';
    $post_billing_address_2 = (!empty($_POST['billing_address_2'])) ? sanitize_text_field(wp_unslash($_POST['billing_address_2'])) : '';
    $post_billing_city = (!empty($_POST['billing_city'])) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '';
    $post_billing_state = (!empty($_POST['billing_state'])) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '';
    $post_billing_postcode = (!empty($_POST['billing_postcode'])) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '';
    $post_billing_phone = (!empty($_POST['billing_phone'])) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '';
    $post_billing_email = (!empty($_POST['billing_email'])) ? sanitize_text_field(wp_unslash($_POST['billing_email'])) : '';
    // Set $_POST shipping values.
    $post_shipping_first_name = (!empty($_POST['shipping_first_name']) && 'undefined' !== $_POST['shipping_first_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_first_name'])) : '';
    $post_shipping_last_name = (!empty($_POST['shipping_last_name']) && 'undefined' !== $_POST['shipping_last_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_last_name'])) : '';
    $post_shipping_address_1 = (!empty($_POST['shipping_address_1']) && 'undefined' !== $_POST['shipping_address_1']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_1'])) : '';
    $post_shipping_address_2 = (!empty($_POST['shipping_address_2']) && 'undefined' !== $_POST['shipping_address_2']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_2'])) : '';
    $post_shipping_city = (!empty($_POST['shipping_city']) && 'undefined' !== $_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '';
    $post_shipping_state = (!empty($_POST['shipping_state']) && 'undefined' !== $_POST['shipping_state']) ? sanitize_text_field(wp_unslash($_POST['shipping_state'])) : '';
    $post_shipping_postcode = (!empty($_POST['shipping_postcode']) && 'undefined' !== $_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : '';
    // Set $_POST shipping rate.
    $post_shipping_rate = (!empty($_POST['shipping_rate'])) ? $_POST['shipping_rate'] : '';

    // if update address isset.
    if (isset($post_update_address)) {
        // get class.
        $wp_tc_gateway = new WC_TP_Gateway();

        // save credit card details.
        $save_credit_card_details = (!empty($_POST['save_credit_card_details_checkbox'])) ? sanitize_text_field(wp_unslash($_POST['save_credit_card_details_checkbox'])) : '';

        // billing details.
        $billing_details = [];
        $billing_details['billing_first_name'] = (!empty($post_billing_first_name)) ? str_replace('\\', '', $post_billing_first_name) : '';
        $billing_details['billing_last_name'] = (!empty($post_billing_last_name)) ? str_replace('\\', '', $post_billing_last_name) : '';
        $billing_details['billing_address_1'] = (!empty($post_billing_address_1)) ? str_replace('\\', '', $post_billing_address_1) : '';
        $billing_details['billing_address_2'] = (!empty($post_billing_address_2)) ? str_replace('\\', '', $post_billing_address_2) : '';
        $billing_details['billing_city'] = (!empty($post_billing_city)) ? str_replace('\\', '', $post_billing_city) : '';
        $billing_details['billing_state'] = (!empty($post_billing_state)) ? str_replace('\\', '', $post_billing_state) : '';
        $billing_details['billing_postcode'] = (!empty($post_billing_postcode)) ? str_replace('\\', '', $post_billing_postcode) : '';
        $billing_details['billing_phone'] = (!empty($post_billing_phone)) ? str_replace('\\', '', $post_billing_phone) : '';
        $billing_details['billing_email'] = (!empty($post_billing_email)) ? str_replace('\\', '', $post_billing_email) : '';

        // shipping details.
        $shipping_details = [];
        $shipping_details['shipping_first_name'] = (!empty($post_shipping_first_name)) ? str_replace('\\', '', $post_shipping_first_name) : '';
        $shipping_details['shipping_last_name'] = (!empty($post_shipping_last_name)) ? str_replace('\\', '', $post_shipping_last_name) : '';
        $shipping_details['shipping_address_1'] = (!empty($post_shipping_address_1)) ? str_replace('\\', '', $post_shipping_address_1) : '';
        $shipping_details['shipping_address_2'] = (!empty($post_shipping_address_2)) ? str_replace('\\', '', $post_shipping_address_2) : '';
        $shipping_details['shipping_city'] = (!empty($post_shipping_city)) ? str_replace('\\', '', $post_shipping_city) : '';
        $shipping_details['shipping_state'] = (!empty($post_shipping_state)) ? str_replace('\\', '', $post_shipping_state) : '';
        $shipping_details['shipping_postcode'] = (!empty($post_shipping_postcode)) ? str_replace('\\', '', $post_shipping_postcode) : '';

        // get current user id ( user must be logged in ).
        $current_user = wp_get_current_user();
        $user_id = (!empty($current_user->ID)) ? $current_user->ID : 0;

        if (!empty($user_id)) {
            // save users billing details.
            update_user_meta($user_id, 'billing_first_name', $billing_details['billing_first_name']);
            update_user_meta($user_id, 'billing_last_name', $billing_details['billing_last_name']);
            update_user_meta($user_id, 'billing_address_1', $billing_details['billing_address_1']);
            update_user_meta($user_id, 'billing_address_2', $billing_details['billing_address_2']);
            update_user_meta($user_id, 'billing_city', $billing_details['billing_city']);
            update_user_meta($user_id, 'billing_state', $billing_details['billing_state']);
            update_user_meta($user_id, 'billing_postcode', $billing_details['billing_postcode']);
            update_user_meta($user_id, 'billing_phone', $billing_details['billing_phone']);
            // save users shipping details.
            update_user_meta($user_id, 'shipping_first_name', $shipping_details['shipping_first_name']);
            update_user_meta($user_id, 'shipping_last_name', $shipping_details['shipping_last_name']);
            update_user_meta($user_id, 'shipping_address_1', $shipping_details['shipping_address_1']);
            update_user_meta($user_id, 'shipping_address_2', $shipping_details['shipping_address_2']);
            update_user_meta($user_id, 'shipping_city', $shipping_details['shipping_city']);
            update_user_meta($user_id, 'shipping_state', $shipping_details['shipping_state']);
            update_user_meta($user_id, 'shipping_postcode', $shipping_details['shipping_postcode']);
        }

        // get the WC Order.
        $order = new WC_Order($post_orderid);

        // get the order total.
        $order_total = $order->get_total();

        // get the order shipping total.
        $order_shipping_total = $order->get_shipping_total();

        // get shipping package rate
        $shipping_package_rate = 0;
        $all_shipping_package_rates = (!empty(WC()->session->get('shipping_for_package_0')['rates'])) ? WC()->session->get('shipping_for_package_0')['rates'] : '';
        if (!empty($all_shipping_package_rates)) {
            foreach ($all_shipping_package_rates as $key => $value) {
                if ($post_shipping_rate != '' && $post_shipping_rate === $value->get_id()) {
                    $shipping_package_rate = $value->get_cost() * 100;
                }
            }
        }

        // update payment details.
        // ( and update the JWT Token ).
        $result = $wp_tc_gateway->update_payment_address_details($post_orderid, $save_credit_card_details, $billing_details, $shipping_details, $shipping_package_rate, $order_total, $order_shipping_total);

        // return result.
        echo esc_html($result);
    }
}
add_action('wp_ajax_tpgw_update_address_myst', 'tpgw_update_address_myst');
add_action('wp_ajax_nopriv_tpgw_update_address_myst', 'tpgw_update_address_myst');

/**
 * Set payment card selected on checkout.
 */
function tpgw_select_saved_payment_card()
{
    global $wpdb;

    // Set $_GET values.
    $get_cid = (!empty($_GET['cID'])) ? (int) $_GET['cID'] : null;

    // get current user id ( user must be logged in ).
    $current_user = wp_get_current_user();
    $user_id = (!empty($current_user->ID)) ? $current_user->ID : 0;

    // set payment card selected.
    if (!empty($user_id) && !empty($get_cid)) {
        // set all cards to inactive.
        $set_inactive_cards = $wpdb->query(
            $wpdb->prepare("UPDATE {$wpdb->prefix}usermeta SET meta_value = '0' WHERE user_id = %s AND meta_key LIKE %s", $user_id, $wpdb->esc_like('_tp_transaction_use_saved_card.').'%')
        );
        // set selected card to active.
        $set_active_cards = $wpdb->query(
            $wpdb->prepare("UPDATE {$wpdb->prefix}usermeta SET meta_value = '1' WHERE user_id = %s AND meta_key = %s", $user_id, '_tp_transaction_use_saved_card.'.$get_cid)
        );
    }
}
add_action('init', 'tpgw_select_saved_payment_card');

/**
 * Subscription sign up fee is currently not supported.
 * ( option is currently hidden from the admin post new/edit
 *   screens and may be introduced in plugin future versions ).
 */
function tpgw_hide_subscription_sign_up_fee()
{
    ?>
	<script>
	window.onload = function() {
		jQuery( '._subscription_sign_up_fee_field' ).hide();
	};
	</script>
	<?php
}
add_action('admin_footer', 'tpgw_hide_subscription_sign_up_fee');

/**
 * Process payment result.
 * ( url notification ).
 */
function tpgw_url_notification()
{
    global $wpdb;

    // get tp gateway settings.
    $settings = get_option('woocommerce_tp_gateway_settings');

    // Have we got a payment result from Trust Payments?
    if (!empty($_REQUEST['responsesitesecurity'])) {
        // Debug.
        // Log all notifications.
        if ('yes' === $settings['enabled_log_details']) {
            // Save REQUEST response to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] MyST Url Notification: '.wc_print_r($_REQUEST, true),
                ['source' => 'trustpayments']
            );
        }
        // Get tp gateway settings.
        $settings = get_option('woocommerce_tp_gateway_settings');
        // Get url notification enabled.
        $url_notification_required = $settings['url_notification_required'];
        // If url notification isn't enabled, let's stop here.
        if ('yes' !== $url_notification_required) {
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save REQUEST response to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] MyST Url Notification Failed: Url Notification is not enabled. '.wc_print_r($_REQUEST, true),
                    ['source' => 'trustpayments']
                );
            }

            return;
        }
        // Get url notification key.
        $url_notification_password = $settings['url_notification_password'];
        // If url notification password has no value, let's stop here.
        if (empty($url_notification_password)) {
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save REQUEST response to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] MyST Url Notification Failed: No password assigned. '.wc_print_r($_REQUEST, true),
                    ['source' => 'trustpayments']
                );
            }

            return;
        }
        // Limit url notification access to Trust Payment IP only.
        $_REQUEST['notification_access_ip'] = '3.250.209.64';
        $notification_access_ip = (!empty($_REQUEST['notification_access_ip'])) ? sanitize_text_field(wp_unslash($_REQUEST['notification_access_ip'])) : '';
        // Check the IP of who wants to process this url notification request.
        $_REQUEST['notification_requested_by_ip'] = tpgw_get_ip_address();
        $notification_requested_by_ip = (!empty($_REQUEST['notification_requested_by_ip'])) ? sanitize_text_field(wp_unslash($_REQUEST['notification_requested_by_ip'])) : '';
        // Shorten the request IP 4 values to the first 3 only.
        // ( eg. instead of 1.2.3.4 we only need 1.2.3 ).
        $incoming_ip = substr($notification_requested_by_ip, 0, strrpos($notification_requested_by_ip, '.'));
        // Check if request ip is an allowed ip.
        $result = (strpos($notification_access_ip, $incoming_ip) !== false) ? true : false;
        // If we dont have a result, stop here.
        if (empty($result)) {
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save REQUEST response to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] MyST Url Notification Failed: Invalid IP request by '.$notification_requested_by_ip.' '.wc_print_r($_REQUEST, true),
                    ['source' => 'trustpayments']
                );
            }

            return;
        }
        // Default string value.
        $str = '';
        foreach ($_REQUEST as $key => $val) {
            // Keys to ignore.
            $ignore_keys = ['notificationreference', 'responsesitesecurity', 'notification_access_ip', 'notification_requested_by_ip'];
            // Add value(s) to string.
            if (!in_array($key, $ignore_keys, true)) {
                $str .= $val;
            }
        }
        // Add url notification password to end of string and check if it matches the responsesitesecurity value.
        if (hash('sha256', $str.$url_notification_password) === $_REQUEST['responsesitesecurity']) {
            // Add the order details to the db.
            // Get order details based on orderreference value.
            $orderreference = (!empty($_REQUEST['orderreference'])) ? sanitize_text_field(wp_unslash($_REQUEST['orderreference'])) : 0;
            $order_data = $wpdb->get_results(
                $wpdb->prepare("select * from {$wpdb->prefix}postmeta where meta_key = '_order_reference_id' and meta_value = %s", $orderreference)
            );
            // Set order id value.
            $orderid = (!empty($order_data[0]->post_id)) ? $order_data[0]->post_id : 0;
            // Log order id value.
            $_REQUEST['orderid'] = $orderid;
            // Get the WC Order.
            $order = new WC_Order($orderid);
            // If order value is zero.
            if (empty($order->get_total())) {
                wp_delete_post($orderid, true);
                exit();
            }
            // If we have an order value.
            // Update order details in db.
            if (!empty($order) && is_object($order)) {
                global $woocommerce;
                // If errorcode is 0 then this is a success.
                if (isset($_REQUEST['errorcode']) && '0' === $_REQUEST['errorcode']) {
                    // Set order complete.
                    $order->payment_complete();
                    // Save order.
                    $order->save();
                    // Send Email.
                    $allmails = WC()->mailer()->emails;
                    $email = $allmails['WC_Email_Customer_Processing_Order'];
                    $email->trigger($orderid);
                    // Send Email to Admin.
                    $email->settings['heading'] = __('New Customer Pending Order', 'woocommerce');
                    $email->settings['recipient'] = get_bloginfo('admin_email');
                    $email->settings['subject'] = '[Trust Payments]: New order #'.$orderid;
                    $email->trigger($orderid);
                    // Add purchase to order notes.
                    $order->add_order_note('"Order Processing" Email Sent');
                    // Empty the cart.
                    $woocommerce->cart->empty_cart();
                    // Debug.
                    if ('yes' === $settings['enabled_log_details']) {
                        // Save REQUEST result to woocommerce log.
                        $logger = wc_get_logger();
                        $logger->debug(
                            '['.WC_TP_Gateway_Version.']  has been processed. '.wc_print_r($_REQUEST, true),
                            ['source' => 'trustpayments']
                        );
                    }
                    // Add woocommerce order id to myst.
                    $transactionreference = (!empty($_REQUEST['transactionreference'])) ? sanitize_text_field(wp_unslash($_REQUEST['transactionreference'])) : 0;
                    $myst_updated = tpgw_add_woocommerce_order_id_to_myst($transactionreference, $orderid);
                    // If myst isn't updated.
                    if (empty($myst_updated)) {
                        // Debug.
                        if ('yes' === $settings['enabled_log_details']) {
                            // Save to woocommerce log.
                            $logger = wc_get_logger();
                            $logger->debug(
                                '['.WC_TP_Gateway_Version.'] Order Update Failed: Order ID '.$orderid.' Transaction Ref '.$transactionreference.' - Result: Add Woocommerce Order ID to MyST failed.',
                                ['source' => 'trustpayments']
                            );
                        }
                    } else {
                        // Debug.
                        if ('yes' === $settings['enabled_log_details']) {
                            // Save to woocommerce log.
                            $logger = wc_get_logger();
                            $logger->debug(
                                '['.WC_TP_Gateway_Version.'] Order Update Success: Order ID '.$orderid.' Transaction Ref '.$transactionreference.' - Result: Add Woocommerce Order ID to MyST was successful.',
                                ['source' => 'trustpayments']
                            );
                        }
                    }
                }
            }
        } else {
            // Else, Url transaction failed.
            $result = 'Value for Url Transaction [responsesitesecurity] and order details encoded sha256 value did not match.';
            // Debug.
            if ('yes' === $settings['enabled_log_details']) {
                // Save REQUEST response to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] MyST Url Notification Failed: '.$result.' '.wc_print_r($_REQUEST, true),
                    ['source' => 'trustpayments']
                );
            }
        }
        // We don't need to go any further.
        exit();
    }
}
add_action('init', 'tpgw_url_notification');

/**
 * Get IP Address.
 */
function tpgw_get_ip_address()
{
    // If IP is from Cloudflare.
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Set remote address.
        $remote_addr = (!empty($_SERVER['REMOTE_ADDR'])) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';

        // Set IP 0.0.0.0 by default.
        $ip = '0.0.0.0';

        // Assume that the request is invalid unless proven otherwise.
        $valid_cf_request = false;

        // get tp gateway settings.
        $settings = get_option('woocommerce_tp_gateway_settings');

        // Get the Cloudflare IP ranges.
        $cloudflare_ip_ranges = explode(',', $settings['cloudflare_ip_ranges']);

        // Make sure that the request came via Cloudflare.
        if (!empty($cloudflare_ip_ranges)) {
            foreach ($cloudflare_ip_ranges as $range) {
                // Remove empty character(s) from $range str.
                $range = str_replace(' ', '', $range);
                // Use the tgpw_ip_in_range function.
                if (tpgw_ip_in_range($remote_addr, $range)) {
                    // IP is valid. Belongs to Cloudflare.
                    $valid_cf_request = true;
                    break;
                }
            }
        }

        // If it's a valid Cloudflare request.
        if ($valid_cf_request) {
            // Use the CF-Connecting-IP header.
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        } else {
            // If it isn't valid, then use REMOTE_ADDR.
            $ip = $remote_addr;
        }

        // Else if IP is from the share internet.
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));

    // Else if IP is from the proxy.
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));

    // Else if IP is from the remote address.
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));

    // Else default IP.
    } else {
        $ip = '0.0.0.0';
    }

    // Validate is IP address.
    $ip = (rest_is_ip_address($ip)) ? $ip : '0.0.0.0';

    return $ip;
}

/**
 * IP in range.
 *
 * This function takes 2 arguments, an IP address and a "range" in several different formats.
 * Network ranges can be specified as:
 * 1. Wildcard format:     1.2.3.*
 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
 * The function will return true if the supplied IP is within the range.
 * Note little validation is done on the range inputs - it expects you to use one of the above 3 formats.
 *
 * @param string $ip    IP
 * @param array  $range range
 */
function tpgw_ip_in_range($ip, $range)
{
    if (strpos($range, '/') !== false) {
        // $range is in IP/NETMASK format
        list($range, $netmask) = explode('/', $range, 2);
        if (strpos($netmask, '.') !== false) {
            // $netmask is a 255.255.0.0 format
            $netmask = str_replace('*', '0', $netmask);
            $netmask_dec = ip2long($netmask);

            return (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec);
        } else {
            // $netmask is a CIDR size block
            // fix the range argument
            $x = explode('.', $range);
            $count_x = count($x);
            while ($count_x < 4) {
                $x[] = '0';
            }
            list($a, $b, $c, $d) = $x;
            $range = sprintf('%u.%u.%u.%u', empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d);
            $range_dec = ip2long($range);
            $ip_dec = ip2long($ip);

            // Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
            // $netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0')); // Currently not in use.

            // Strategy 2 - Use math to create it.
            $wildcard_dec = pow(2, (32 - $netmask)) - 1;
            $netmask_dec = ~$wildcard_dec;

            return ($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec);
        }
    } else {
        // Range might be 255.255.*.* or 1.2.3.0-1.2.3.255 .
        if (strpos($range, '*') !== false) { // a.b.*.* format
            // Just convert to A-B format by setting * to 0 for A and 255 for B.
            $lower = str_replace('*', '0', $range);
            $upper = str_replace('*', '255', $range);
            $range = "$lower-$upper";
        }

        if (strpos($range, '-') !== false) { // A-B format.
            list($lower, $upper) = explode('-', $range, 2);
            $lower_dec = (float) sprintf('%u', ip2long($lower));
            $upper_dec = (float) sprintf('%u', ip2long($upper));
            $ip_dec = (float) sprintf('%u', ip2long($ip));

            return ($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec);
        }

        return false;
    }
}

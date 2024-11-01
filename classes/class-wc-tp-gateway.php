<?php
/**
 * Class WC_TP_Gateway.
 */

/**
 * Trust Payments - Payment Gateway.
 *
 * Provides an TP Payment Gateway.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_TP_Gateway
 * @extends     WC_Payment_Gateway
 *
 * @version     1.3.6
 *
 * @author      Illustrate Digital
 */
class WC_TP_Gateway extends WC_Gateway_Cheque
{
	/**
	 * Live mode.
	 *
	 * @var int
	 */
	private $live_mode;

	/**
	 * JWT secret.
	 *
	 * @var string
	 */
	private $jwt_secret;

	/**
	 * JWT username.
	 *
	 * @var string
	 */
	private $jwt_username;

	/**
	 * Site reference.
	 *
	 * @var string
	 */
	private $sitereference;

	/**
	 * WS Username.
	 *
	 * @var string
	 */
	private $ws_username;

	/**
	 * WS password.
	 *
	 * @var string
	 */
	private $ws_password;

	/**
	 * Auth method.
	 *
	 * @var string
	 */
	private $auth_method;

	/**
	 * Order reference.
	 *
	 * @var int
	 */
	private $orderreference;

	/**
	 * Save cards.
	 *
	 * @var string
	 */
	private $save_cards;

	/**
	 * Cid.
	 *
	 * @var string
	 */
	private $c_id;

	/**
	 * Debug enabled.
	 *
	 * @var string
	 */
	private $debug_enabled;

	/**
	 * Disable saving new cards.
	 *
	 * @var string
	 */
	private $disable_saving_new_cards;

	/**
	 * Disable saving cards and using new cards.
	 *
	 * @var string
	 */
	private $disable_saving_cards_and_using_saved_cards;

	/**
	 * Users saved card details.
	 *
	 * @var string
	 */
	private $users_saved_card_details;

	/**
	 * TP transaction saved card id.
	 *
	 * @var int
	 */
	private $_tp_transaction_saved_card_id;

	/**
	 * TP transaction masked pan.
	 *
	 * @var string
	 */
	private $_tp_transaction_maskedpan;

	/**
	 * TP transaction saved card id.
	 *
	 * @var int
	 */
	private $_tp_transaction_paymenttypedescription;

	/**
	 * Parent transaction reference.
	 *
	 * @var int
	 */
	private $parenttransactionreference;

	/**
	 * Use users saved credit card details.
	 *
	 * @var string
	 */
	private $use_users_saved_credit_card_details;

	/**
	 * Update payment details.
	 *
	 * @var array
	 */
	private $update_payment_details;

	/**
	 * Subscription Id.
	 *
	 * @var int
	 */
	private $subscription_id;


    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = 'tp_gateway';
        $this->icon = apply_filters('woocommerce_tp_icon', '');
        $this->has_fields = false;
        $this->supports = [
            'products',
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_cancellation',
            'subscription_date_changes',
            'subscription_amount_changes',
            'multiple_subscriptions',
        ];
        $this->method_title = __('Trust Payments for WooCommerce', 'wc-gateway-tp');
        $this->method_description = __('Allows 3D Secure v2.0 payments to be taken using your Trust Payments account.', 'wc-gateway-tp');
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        // Define user set variables.
        $this->enabled = $this->get_option('enabled');
        $this->live_mode = $this->get_option('enabled_live_mode');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->jwt_secret = $this->get_option('jwt_secret');
        $this->jwt_username = $this->get_option('jwt_username');
        $this->sitereference = $this->get_option('sitereference');
        $this->ws_username = $this->get_option('ws_username');
        $this->ws_password = $this->get_option('ws_password');
        $this->auth_method = $this->get_option('auth_method');
        $this->save_cards = 'yes';
        $this->c_id = (!empty($_GET['cID'])) ? sanitize_text_field(wp_unslash($_GET['cID'])) : '';
        $this->instructions = 'Instructions';
        $this->orderreference = $this->order_reference_id();

        // Get debug status.
        $this->debug_enabled = $this->get_option('enabled_log_details');

        // Disable saving new cards, but allow customers to use previously saved cards.
        $this->disable_saving_new_cards = $this->get_option('disable_saving_new_cards');
        // Disable saved cards entirely, meaning customers can’t save new ones, or use old ones.
        $this->disable_saving_cards_and_using_saved_cards = $this->get_option('disable_saving_cards_and_using_saved_cards');

        // Users saved card details.
        $this->users_saved_card_details = $this->get_users_saved_card_details(); // get users saved card details from db.

        // If use saved card option is selected and using saved cards is not disabled.
        if ($this->users_saved_card_details && 'yes' !== $this->disable_saving_cards_and_using_saved_cards) {
            // loop for each card saved.
            foreach ($this->users_saved_card_details as $saved_card) {
                // get card set as active.
                if ('1' === $saved_card['_tp_transaction_use_saved_card']) {
                    $saved_tp_transaction_saved_card_id = $saved_card['_tp_transaction_saved_card_id']; // example: 1234567890.
                    $saved_tp_transaction_maskedpan = $saved_card['_tp_transaction_maskedpan']; // example: 0123 **** **** 1112.
                    $saved_tp_transaction_paymenttypedescription = $saved_card['_tp_transaction_paymenttypedescription']; // example: VISA.
                    $saved_tp_transaction_reference = $saved_card['_tp_transaction_reference']; // example: '57-9-788949'.
                    $saved_tp_transaction_use_saved_card = $saved_card['_tp_transaction_use_saved_card']; // use saved card details ( defaults to 0 ).
                }
            }
        }

        $this->_tp_transaction_saved_card_id = (!empty($saved_tp_transaction_saved_card_id)) ? $saved_tp_transaction_saved_card_id : '0';
        $this->_tp_transaction_maskedpan = (!empty($saved_tp_transaction_maskedpan)) ? $saved_tp_transaction_maskedpan : '';
        $this->_tp_transaction_paymenttypedescription = (!empty($saved_tp_transaction_paymenttypedescription)) ? $saved_tp_transaction_paymenttypedescription : '';
        $this->parenttransactionreference = (!empty($saved_tp_transaction_reference)) ? $saved_tp_transaction_reference : '';
        $this->use_users_saved_credit_card_details = (!empty($saved_tp_transaction_use_saved_card)) ? $saved_tp_transaction_use_saved_card : '';

        // Set payment details.
        $this->update_payment_details = $this->update_payment_address_details();

        // Save the options from init_form_fields().
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        // Display order meta data.
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_order_transaction_details'], 10, 1);
        // Add JS code to footer.
        add_action('wp_footer', [$this, 'footer_scripts'], 100);
        // Set form required fields.
        add_action('admin_footer', [$this, 'set_form_required_fields'], 100);
        // Add credit card details to my-account > account details.
        add_action('woocommerce_account_edit-account_endpoint', [$this, 'update_credit_card_details_info']);
        // Set choice fields for disabled options ( choose one or other ).
        add_action('admin_footer', [$this, 'set_disabled_choice_field'], 100);
        // Set option to show admin created orders on my-account/orders page that require payment.
        add_action('wp_footer', [$this, 'user_viewing_account_page'], 100);
        // Control admin payment form settings ( when changes are applied to fields ).
        add_action('admin_footer', [$this, 'control_admin_payment_form_settings'], 100);
        // Check jQuery version.
        add_action('admin_notices', [$this, 'check_jquery_version'], 100);
        // Check jQuery version.
        add_action('admin_notices', [$this, 'check_all_plugins'], 100);
        // Display order refernce id in admin area > orders.
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_verification_id_in_admin_order_meta'], 10, 1);
        // Add customer details to thankyou page.
        add_action('woocommerce_thankyou', [$this, 'add_customer_details_to_thankyou_page'], 10, 1);

        // Process recurring subscriptions.
        if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
            // Set active subscription id.
            $this->subscription_id = 0;
            // Subscription updates.
            add_action('wp_footer', [$this, 'process_subscription_updates'], 10, 2);
            // Update MyST with subscription recurring payment.
            add_action('woocommerce_scheduled_subscription_payment_'.$this->id, [$this, 'scheduled_subscription_payment'], 10, 2);
        }

        // If plugin is not enabled in WP admin.
        if (!$this->enabled) {
            return;
        }

        add_action('wp_footer', [$this, 'scheduled_subscription_payment'], 100);
    }

    /**
     * Add customer details to thankyou page.
     *
     * @param int $order_id order ID
     */
    public function add_customer_details_to_thankyou_page($order_id)
    {
        // If we dont have an order id, stop here.
        if (!$order_id) {
            return;
        }

        // Get order id.
        $order = wc_get_order($order_id);

        // Login user ( if not already logged in ).
        if (!is_user_logged_in()) {
            // Get user id so we can log the user in.
            $user_id = get_post_meta($order_id, 'tp_userid');
            $user_id = (!empty($user_id)) ? (int) $user_id[0] : 0;
            // Log in automatically.
            $user = get_user_by('id', $user_id);
            if ($user) {
                wp_set_current_user($user_id, $user->user_login);
                wp_set_auth_cookie($user_id);
                do_action('wp_login', $user->user_login, $user);
            }
            // set users role.
            $user_details = new WP_User($user_id);
            $user_details->set_role('customer');
            // on first new order, assign the customer order their user id.
            // or they wont see the first purchase in their logged in orders section.
            update_post_meta($order_id, '_customer_user', $user_id);
        }
		
		// If user is logged in.
		if (is_user_logged_in()) {
			// Get users details.
			$user = wp_get_current_user();
			// If user_login was Subcription.Customer.xxx then logout this user.
			if (str_contains($user->data->user_login, 'Subscription.Guest.')) {
				wp_logout();
			}
		}

        // Assign template to order details customer page.
        wc_get_template('order/order-details-customer.php', ['order' => $order]);
    }

    /**
     * Process subscription update.
     */
    public function process_subscription_updates()
    {
        // If we have ids to process.
        if (!empty($this->subscription_id)) {
            // Process subscription payment for ID.
            @do_action('woocommerce_scheduled_subscription_payment', $this->subscription_id);
        }
    }

    /**
     * Scheduled subscription payment.
     *
     * @param int $amount_to_charge amount to charge
     * @param obj $order            order details
     */
    public function scheduled_subscription_payment($amount_to_charge = '', $order = '')
    {
        // Check we have required data before we go any further.
        if (empty($amount_to_charge) || empty($order)) {
            // Value(s) empty? Let's exit here...
            return;
        }

        // Get subscription details.
        $subscription = new WC_Subscription($order->get_id());

        // Get tp gateway settings.
        $settings = get_option('woocommerce_tp_gateway_settings');

        // Get connection details.
        $userpwd = $settings['ws_username'].':'.$settings['ws_password'];
        $alias = $settings['ws_username'];
        $sitereference = $settings['sitereference'];

        // Get subscription parent order id.
        $parent_order_id = '';
		$child_order_id = '';
        $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), ['order_type' => 'any']);
        foreach ($subscriptions as $subscriptionId => $subscriptionObj) {
            $parent_order_id = $subscriptionObj->order->get_id();
			$child_order_id = $subscriptionId;
        }

        // If we have a parent id.
        if (!empty($parent_order_id)) {
            // Subscription parent transaction values.
            $parenttransactionreference = get_post_meta($parent_order_id, '_tp_transaction_reference', true); // eg. 1-23-456.
            $baseamount = $amount_to_charge * 100; // the recurring weekly / monthly cost.
            $subscriptionnumber = (get_post_meta($parent_order_id, '_subscriptionnumber', true)) ? get_post_meta($parent_order_id, '_subscriptionnumber', true) : 2; // for recurring payments the value must exceed 1.

			// Subscription child transaction values.
			if (!empty($child_order_id)) {
				$subscriptionnumber = (get_post_meta($parent_order_id, $child_order_id.'_subscriptionnumber', true)) ? get_post_meta($parent_order_id, $child_order_id.'_subscriptionnumber', true) : 2; // for recurring payments the value must exceed 1.
			}

            // Send the subscription payment.
            $args = [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($userpwd),
                ],
                'body' => '{
					"alias":"'.$alias.'",
					"version":"1.0",
					"request":[{
						"sitereference" : "'.$sitereference.'",
						"requesttypedescriptions" : ["AUTH"],
						"accounttypedescription" : "RECUR",
						"parenttransactionreference" : "'.$parenttransactionreference.'",
						"baseamount" : "'.$baseamount.'",
						"subscriptiontype" : "RECURRING",
						"subscriptionnumber" : "'.$subscriptionnumber.'",
						"credentialsonfile" : "2"
					}]
				}',
            ];

            $response = wp_remote_post('https://webservices.securetrading.net/json/', $args);
            $response_body = wp_remote_retrieve_body($response);
            $json_response = json_decode($response_body);

            // If there's a MyST processing error, stop here.
            // ( you have a choice for what subscription object you want to place on-hold ).
            if ('0' !== $json_response->response[0]->errorcode) {
                // Get Parent.
                $parentOrder = wc_get_order($parent_order_id);
                // Step 1. Change order status to on-hold.
                // $parentOrder->update_status('on-hold');
                $order->update_status('on-hold');
                // Step 2. Add error notice to the WooCommerce order details.
                // $parentOrder->add_order_note('Trust Payments:<br />Subscription payment failed.<br />Error code:<br />'.$json_response->response[0]->errorcode.' - '.$json_response->response[0]->errormessage);
                $order->add_order_note('Trust Payments:<br />Subscription payment failed.<br />Error code:<br />'.$json_response->response[0]->errorcode.' - '.$json_response->response[0]->errormessage);
                // Step 3. Save details to WooCommerce logs.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Scheduled subscription recurring payment error: Order ID '.$order->get_id().' ( Parent: '.$parentOrder->get_id().' ), Details '.wc_print_r($json_response->response, true),
                    ['source' => 'trustpayments']
                );
            }
            // Else, alls good lets update the subscription order.
            else {
                // Set subscription number new value.
                ++$subscriptionnumber;
                // Update subscription number count for parent or child id.
				if (!empty($child_order_id)) {
					update_post_meta($parent_order_id, $child_order_id.'_subscriptionnumber', $subscriptionnumber);
				} else {
                	update_post_meta($parent_order_id, '_subscriptionnumber', $subscriptionnumber);
				}

				// Process subscription payments on order.
                WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
                // Set this payment as complete.
                $order->payment_complete();
                // Save to woocommerce logs.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Scheduled subscription order details: Order ID '.$order->get_id().', Details '.wc_print_r($order->get_data(), true),
                    ['source' => 'trustpayments']
                );
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Scheduled subscription payment request: Order ID '.$order->get_id().', Total '.$amount_to_charge.', Request '.wc_print_r($args['body'], true),
                    ['source' => 'trustpayments']
                );
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Scheduled subscription payment result: Order ID '.$order->get_id().', Total '.$amount_to_charge.', Response '.wc_print_r($response_body, true),
                    ['source' => 'trustpayments']
                );
            }
        }
    }

    /**
     * Create random 10 character string for Order Reference ID value.
     */
    public function order_reference_id()
    {
        // Set cookie value.
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = 'Ref-';
        for ($i = 0; $i < 10; ++$i) {
            $index = wp_rand(0, strlen($characters) - 1);
            $random_string .= $characters[$index];
        }
        // If we dont have a cookie set and we are not viewing the order completed page.
        if (!isset($_COOKIE['order_reference_id']) && !isset($_GET['key'])) {
            // Set cookie value.
            @setcookie('order_reference_id', $random_string, time() + 86400, '/');
        }
        // On checkout confirmed / order completed page, we dont need the cookie anymore.
        if (isset($_GET['key'])) {
            // Unset cookie.
            unset($_COOKIE['order_reference_id']);
            // Set cookie value to ''.
            setcookie('order_reference_id', '', time() - 86400, '/');
        }
        // Set order reference id value.
        $order_reference_id = (!empty($_COOKIE['order_reference_id'])) ? sanitize_text_field(wp_unslash($_COOKIE['order_reference_id'])) : '';

        // Return.
        // ( default value is the woocommerce value but if this is empty/null set a custom value ).
        return $order_reference_id;
    }

    /**
     * Display 'Order Reference ID' on Admin order edit page.
     *
     * @param array $order order details
     */
    public function display_verification_id_in_admin_order_meta($order)
    {
        // Get order id.
        $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
        // Show order refernce id.
        echo '<p><strong>Order Reference ID:</strong> '.esc_attr(get_post_meta($order_id, '_myst_order_ref', true)).'</p>';
    }

    /**
     * On my-account/orders page, hide admin created entries that require paying.
     * ( development for payment currently in process ).
     */
    public function user_viewing_account_page()
    {
        ?>
		<script>
		jQuery( document ).ready(function() {
			jQuery( '.pay' ).parent().parent().hide();
		});
		</script>
		<?php
    }

    /**
     * Set disabled fields so that only one can be selected ( admin area ).
     */
    public function set_disabled_choice_field()
    {
        if (!empty($_GET['section']) && 'tp_gateway' === $_GET['section']) {
            ?>
			<script>		
			jQuery( '#woocommerce_tp_gateway_disable_saving_new_cards' ).on( 'click', function() {
				if ( document.getElementById( 'woocommerce_tp_gateway_disable_saving_new_cards' ).checked ) {
					document.getElementById( 'woocommerce_tp_gateway_disable_saving_cards_and_using_saved_cards' ).checked = false;
				}
			});
			jQuery( '#woocommerce_tp_gateway_disable_saving_cards_and_using_saved_cards' ).on( 'click', function() {
				if ( document.getElementById( 'woocommerce_tp_gateway_disable_saving_cards_and_using_saved_cards' ).checked ) {
					document.getElementById( 'woocommerce_tp_gateway_disable_saving_new_cards' ).checked = false;
				}	
			});	
			jQuery( '#woocommerce_tp_gateway_enabled_live_mode' ).on( 'click', function() {
				// show_debug_log_option();
			});	
			function show_debug_log_option() {
				if ( document.getElementById( 'woocommerce_tp_gateway_enabled_live_mode' ).checked 
					&& document.getElementById( 'woocommerce_tp_gateway_enabled_log_details' ).checked ) {
					alert( 'You need to uncheck the Debug Log option to continue. Debug Log is only available when Live Mode is unchecked.' );
					document.getElementById( 'woocommerce_tp_gateway_enabled_live_mode' ).checked = false;
					return false;
				}
				if ( document.getElementById( 'woocommerce_tp_gateway_enabled_live_mode' ).checked ) {
					document.getElementById( 'woocommerce_tp_gateway_enabled_log_details' ).checked = false;
					jQuery( '#woocommerce_tp_gateway_enabled_live_mode' ).parents("tr").next().hide();
				} else {
					jQuery( '#woocommerce_tp_gateway_enabled_live_mode' ).parents("tr").next().show();
				}
			}
			// show_debug_log_option();									
			</script>
			<?php
        }
    }

    /**
     * Set form required fields for payment settings ( admin area ).
     */
    public function set_form_required_fields()
    {
        ?>
		<script>
		if ( document.getElementById("woocommerce_tp_gateway_title") ) {
			document.getElementById("woocommerce_tp_gateway_title").required = true; // Title.
			document.getElementById("woocommerce_tp_gateway_description").required = true; // Description.
			document.getElementById("woocommerce_tp_gateway_jwt_username").required = true; // JWT Username.
			document.getElementById("woocommerce_tp_gateway_jwt_secret").required = true; // JWT Secret.
			document.getElementById("woocommerce_tp_gateway_sitereference").required = true; // Site Reference.
			document.getElementById("woocommerce_tp_gateway_ws_username").required = true; // WS Username.
			document.getElementById("woocommerce_tp_gateway_ws_password").required = true; // Ws password.
			document.getElementById("woocommerce_tp_gateway_delete_individual_saved_cards").value = '' // Delete individual user credit cards.
			document.getElementById("woocommerce_tp_gateway_delete_all_saved_cards").checked = false // Delete all users credit cards.
		}
		</script>
		<?php
    }

    /**
     * Update credit card details ( customer option, in edit account ).
     */
    public function update_credit_card_details_info()
    {
        // set card deleted values.
        $cards_deleted = false;
        $cards_updated = false;
        // update saved card details.
        if (isset($_REQUEST['update_cards'])) {
            global $wpdb;
            // get current user id ( user must be logged in ).
            $current_user = wp_get_current_user();
            $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;
            // delete cards.
            if (!empty($userid) && isset($_REQUEST['delete_card']) && is_array($_REQUEST['delete_card'])) {
                $delete_card = (is_array($_REQUEST['delete_card'])) ? array_map('absint', $_REQUEST['delete_card']) : '';
                if (!empty($delete_card)) {
                    foreach ($delete_card as $card_id) {
                        $card_id = sanitize_text_field(wp_unslash($card_id));
                        $wpdb->query(
                            $wpdb->prepare("delete from {$wpdb->prefix}usermeta where user_id = %d and meta_key = %s", $userid, '_tp_transaction_reference.'.$card_id)
                        );
                        $wpdb->query(
                            $wpdb->prepare("delete from {$wpdb->prefix}usermeta where user_id = %d and meta_key = %s", $userid, '_tp_transaction_maskedpan.'.$card_id)
                        );
                        // delete _tp_transaction_paymenttypedescription.savedcard_id.
                        $wpdb->query(
                            $wpdb->prepare("delete from {$wpdb->prefix}usermeta where user_id = %d and meta_key = %s", $userid, '_tp_transaction_paymenttypedescription.'.$card_id)
                        );
                        // delete _tp_transaction_use_saved_card.savedcard_id.
                        $wpdb->query(
                            $wpdb->prepare("delete from {$wpdb->prefix}usermeta where user_id = %d and meta_key = %s", $userid, '_tp_transaction_use_saved_card.'.$card_id)
                        );
                        // set cards deleted to true.
                        $cards_deleted = true;
                    }
                }
            }
            // set active card.
            if (!empty($userid) && isset($_REQUEST['active_card']) && is_array($_REQUEST['active_card'])) {
                $active_card = sanitize_text_field(wp_unslash($_REQUEST['active_card']));
                if (!empty($active_card)) {
                    foreach ($active_card as $card_id) {
                        $card_id = sanitize_text_field(wp_unslash($card_id));
                        // set all cards to inactive.
                        $like = '_tp_transaction_use_saved_card.%';
                        $wpdb->query(
                            $wpdb->prepare("update {$wpdb->prefix}usermeta set meta_value = '0' where user_id = %d and meta_key LIKE %s", $userid, $like)
                        );
                        // set selected card to active.
                        $wpdb->query(
                            $wpdb->prepare("update {$wpdb->prefix}usermeta set meta_value = '1' where user_id = %d and meta_key = %s", $userid, '_tp_transaction_use_saved_card.'.$card_id)
                        );
                        // set cards updated to true.
                        $active_card = $card_id;
                        $cards_updated = true;
                    }
                }
            }
        } ?>
		<!-- show saved credit/debit card(s) //-->		
		<form action="" method="post" name="saved-card-details" id="saved-card-details">
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<h3 style="padding: 20px 0 0 0;">Saved credit/debit card details</h3>
					<?php if ($cards_deleted) { ?>
					<div style="padding: 10px 0 10px 0; color:green;">
						<strong>Success, card(s) has been deleted.</strong>
					</div>
				<?php } ?>
				<?php if ($cards_updated) { ?>
					<div style="padding: 10px 0 10px 0; color:green;">
						<strong>Success, this card will automatically be selected during payment.</strong>
					</div>
				<?php } ?>							
				<div style="padding: 10px 0 25px 0;">
					<table>
						<tr>
							<td>
								<strong>Card Number</strong>
							</td>
							<td style="width: 75px;">
								<strong>Selected</strong>
							</td>							
							<td style="width: 50px;">
								<strong>Delete</strong>
							</td>
						</tr>
						<?php
                        $card_count = 0;
        $credit_cards = $this->get_users_saved_card_details();
        if (!empty($credit_cards[0]['_tp_transaction_maskedpan']) && !empty($credit_cards[0]['_tp_transaction_paymenttypedescription'])) {
            foreach ($credit_cards as $credit_card) {
                if (!empty($credit_card['_tp_transaction_saved_card_id'])) {
                    ++$card_count; ?>
									<tr>
										<td>
											<input type="hidden" name="update_cards[<?php echo esc_attr($card_count); ?>]" value="1">
											<input type="hidden" name="_tp_transaction_saved_card_id[<?php echo esc_attr($card_count); ?>]" value="<?php echo esc_html($credit_card['_tp_transaction_saved_card_id']); ?>">
											<input type="hidden" name="_tp_transaction_maskedpan[<?php echo esc_attr($card_count); ?>]" value="<?php echo esc_html($credit_card['_tp_transaction_maskedpan']); ?>">
											<input type="hidden" name="_tp_transaction_paymenttypedescription[<?php echo esc_attr($card_count); ?>]" value="<?php echo esc_html($credit_card['_tp_transaction_paymenttypedescription']); ?>">
											<input type="hidden" name="_tp_transaction_reference[<?php echo esc_attr($card_count); ?>]" value="<?php echo esc_html($credit_card['_tp_transaction_reference']); ?>">

											<?php echo esc_html($credit_card['_tp_transaction_maskedpan']); ?> (<?php echo esc_html($credit_card['_tp_transaction_paymenttypedescription']); ?>)
										</td>
										<td style="text-align: center;">
											<?php $checked = (!empty($credit_card['_tp_transaction_use_saved_card'])) ? 'checked' : ''; ?>
											<input type="checkbox" name="active_card[<?php echo esc_attr($card_count); ?>]" class="active_card" value="<?php echo esc_html($credit_card['_tp_transaction_saved_card_id']); ?>" <?php echo esc_html($checked); ?>>
										</td>									
										<td style="text-align: center;">
											<input type="checkbox" name="delete_card[<?php echo esc_attr($card_count); ?>]" value="<?php echo esc_html($credit_card['_tp_transaction_saved_card_id']); ?>">
										</td>
									</tr>
									<?php
                }
            }
        } else {
            ?>
							<tr>
								<td colspan="3">
									No saved credit/debit card(s) for this account.
								</td>
							</tr>
						<?php
        } ?>					
					</table>
					<button type="submit" class="woocommerce-Button button" name="update_card_details" value="Update details">Update card details</button>
				</div>
				<script>
				jQuery(document).ready(function(){
					jQuery('.active_card').click(function() {
						jQuery('.active_card').not(this).prop('checked', false);
					});
				});
				</script>
			</p>
		</form>
		<?php
    }

    /**
     * Credit card endpoint for My Account menu.
     *
     * @param array $items items
     *
     * @return array
     */
    public function my_account_credit_cards_menu_item($items)
    {
        // Remove the logout menu item.
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);

        // Insert your custom endpoint.
        $items['credit-cards'] = __('Credit Cards', 'woocommerce');

        // Insert back the logout item.
        $items['customer-logout'] = $logout;

        return $items;
    }

    /**
     *  Get users saved card details.
     */
    public function get_users_saved_card_details()
    {
        global $wpdb;

        // get current user id ( user must be logged in ).
        $current_user = wp_get_current_user();
        $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;

        // get details for this user.
        if (is_multisite()) {
            $data = $wpdb->get_results(
                $wpdb->prepare("select * from {$wpdb->usermeta} where user_id = %d", $userid)
            );
        } else {
            $data = $wpdb->get_results(
                $wpdb->prepare("select * from {$wpdb->prefix}usermeta where user_id = %d", $userid)
            );
        }

        // set payment details array.
        $payment_data = [];
        $payment_count = 0;
        $count = 1;
        if (!empty($data)) {
            foreach ($data as $result) {
                // _tp_transaction_reference.
                $meta_key = explode('.', $result->meta_key);
                if ('_tp_transaction_reference' === $meta_key['0'] && !empty($meta_key['1'])) {
                    $payment_data[$payment_count]['_tp_transaction_saved_card_id'] = $meta_key['1'];
                    $payment_data[$payment_count]['_tp_transaction_reference'] = $result->meta_value;
                    ++$count;
                }
                // _tp_transaction_paymenttypedescription.
                $meta_key = explode('.', $result->meta_key);
                if ('_tp_transaction_paymenttypedescription' === $meta_key['0'] && !empty($meta_key['1'])) {
                    $payment_data[$payment_count]['_tp_transaction_paymenttypedescription'] = $result->meta_value;
                    ++$count;
                }
                // _tp_transaction_maskedpan.
                $meta_key = explode('.', $result->meta_key);
                if ('_tp_transaction_maskedpan' === $meta_key['0'] && !empty($meta_key['1'])) {
                    $payment_data[$payment_count]['_tp_transaction_maskedpan'] = $result->meta_value;
                    ++$count;
                }
                // _tp_transaction_use_saved_card.
                $meta_key = explode('.', $result->meta_key);
                if ('_tp_transaction_use_saved_card' === $meta_key['0'] && !empty($meta_key['1'])) {
                    $payment_data[$payment_count]['_tp_transaction_use_saved_card'] = $result->meta_value;
                    ++$count;
                }
                // update payment_count & count based on count value.
                if ($count > 4) {
                    ++$payment_count;
                    $count = 1;
                }
            }
        }

        // return result.
        return (!empty($payment_data)) ? $payment_data : '';
    }

	/**
	 * Delete individual saved cards.
	 */
	public function delete_individual_saved_cards() {
		global $wpdb;
		if ( ! empty( $_POST['woocommerce_tp_gateway_delete_individual_saved_cards'] ) 
				&& '' !== $_POST['woocommerce_tp_gateway_delete_individual_saved_cards'] ) {
			// get current user.
			$user = wp_get_current_user();
			// set allowed roles.
			$allowed_roles = ['administrator'];
			// If user is in allowed role(s).
			if (array_intersect($allowed_roles, $user->roles)) {
				// Get user ID.
				$email = $_POST['woocommerce_tp_gateway_delete_individual_saved_cards'];
				$data = $wpdb->get_results(
					$wpdb->prepare("select * from {$wpdb->prefix}users where user_email = %s", $email)
				);
				// Delete card(s) data.
				if ( ! empty( $data[0] ) ) {
					$result = $wpdb->query(
						$wpdb->prepare("delete from {$wpdb->prefix}usermeta where meta_key LIKE %s and user_id = %d", '%_tp_transaction_%', $data[0]->ID)
					);
				}
			}
		}
	}

	/**
	 * Delete individual saved cards.
	 */
	public function delete_all_saved_cards() {
		global $wpdb;
		if ( ! empty( $_POST['woocommerce_tp_gateway_delete_all_saved_cards'] ) && '1' === $_POST['woocommerce_tp_gateway_delete_all_saved_cards'] ) {
			// get current user.
			$user = wp_get_current_user();
			// set allowed roles.
			$allowed_roles = ['administrator'];
			// If user is in allowed role(s).
			if (array_intersect($allowed_roles, $user->roles)) {
				$wpdb->query(
					$wpdb->prepare("delete from {$wpdb->prefix}usermeta where meta_key LIKE %s", '%_tp_transaction_%')
				);
			}
		}
	}

    /**
     * Initialize Gateway Settings Form Fields
     * ( for all other users excluding Administrator users ).
     */
    public function init_form_fields()
    {
        // get current user.
        $user = wp_get_current_user();

        // set allowed roles.
        $allowed_roles = ['administrator'];

        // show menu items.
        if (array_intersect($allowed_roles, $user->roles)) {

			// On settings update, delete saved cards.
			$this->delete_individual_saved_cards();
			$this->delete_all_saved_cards();

            // set options for allowed roles.
            $this->form_fields = apply_filters(
                'wc_tp_form_fields',
                [
                    'enabled' => [
                        'title' => __('Enable/Disable', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Enable Trust Payments', 'wc-gateway-tp'),
                        'default' => 'yes',
                    ],
                    'enabled_live_mode' => [
                        'title' => __('Enable/Disable Live Mode', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Enable live mode (uncheck only if testing payments, and remember to enter your test Site Reference below as well!)', 'wc-gateway-tp'),
                        'default' => 'yes',
                    ],
                    'enabled_log_details' => [
                        'title' => __('Enable/Disable Debug Log', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Enable debugging (DO NOT USE ON A PUBLIC/LIVE SITE - this exposes security sensitive information and should be only used for debug purposes only)', 'wc-gateway-tp'),
                        'description' => __('Enables/disables the display of debugging information for developers in WooCommerce > Status > Logs', 'wc-gateway-tp'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ],
                    'title' => [
                        'title' => __('Title*', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-tp'),
                        'default' => __('Trust Payments', 'wc-gateway-tp'),
                        'desc_tip' => true,
                    ],
                    'description' => [
                        'title' => __('Description*', 'wc-gateway-tp'),
                        'type' => 'textarea',
                        'description' => __('Payment method description that the customer will see on your checkout.', 'wc-gateway-tp'),
                        'default' => __('Trust Payments', 'wc-gateway-tp'),
                        'desc_tip' => true,
                    ],
                    'jwt_username' => [
                        'title' => __('JWT Username*', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Your Trust Payments JWT Username.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'jwt_secret' => [
                        'title' => __('JWT Secret*', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Your Trust Payments JWT Secret.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'sitereference' => [
                        'title' => __('Site Reference*', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Trust Payments Site Reference.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'ws_username' => [
                        'title' => __('WS Username*', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Your Trust Payments Webservices Username.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'ws_password' => [
                        'title' => __('WS Password*', 'wc-gateway-tp'),
                        'type' => 'password',
                        'description' => __('Your Trust Payments Webservices Password.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'url_notification_required' => [
                        'title' => __('Enable Url Notification', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'description' => __('Enable/Disable Url Notification.', 'wc-gateway-tp'),
                        'default' => 'no',
                        'label' => __('Enable Url Notification (If enabled, you will need to enter a Url Notification password below)', 'wc-gateway-tp'),
                        'desc_tip' => true,
                    ],
                    'url_notification_password' => [
                        'title' => __('Url Notification Password', 'wc-gateway-tp'),
                        'type' => 'password',
                        'description' => __('Your trust payments url notification password.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'auth_method' => [
                        'title' => __('Auth Method', 'wc-gateway-tp'),
                        'type' => 'select',
                        'description' => __('Auth methods are used to specify how a transaction is to be processed by the card issuer. A pre-authorisation is used to seek authorisation for a transaction and reserve the funds on the customer’s account, in cases where the final amount to be debited from the customer is not known at time of authorisation. A final authorisation is used to seek authorisation for a transaction and reserve the funds on the customer’s account, in cases where the final amount to be debited from the customer is known at time of authorisation.', 'wc-gateway-tp'),
                        'desc_tip' => true,
                        'options' => [
                            'PRE' => 'Pre',
                            'FINAL' => 'Final',
                        ],
                        'default' => 'FINAL',
                    ],
                    'disable_saving_new_cards' => [
                        'title' => __('Disable saving new cards', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Disables customers from saving any new credit/debit cards but allow customers to use previously saved cards ', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => false,
                    ],
                    'disable_saving_cards_and_using_saved_cards' => [
                        'title' => __('Disable save cards options', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Disables all saved cards options meaning customers can’t save new credit/debit cards or use saved credit/debit cards ', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => false,
                    ],			
                    'cloudflare_ip_ranges' => [
                        'title' => __('Cloudflare IP Ranges', 'wc-gateway-tp'),
                        'type' => 'textarea',
                        'label' => __('Cloudflare IP Ranges', 'wc-gateway-tp'),
                        'default' => '204.93.240.0/24, 204.93.177.0/24, 199.27.128.0/21, 173.245.48.0/20, 103.21.244.0/22, 103.22.200.0/22, 103.31.4.0/22, 141.101.64.0/18, 108.162.192.0/18, 190.93.240.0/20, 188.114.96.0/20, 197.234.240.0/22, 198.41.128.0/17, 162.158.0.0/15',
                        'description' => __('List of Cloudflare IP Ranges', 'wc-gateway-tp'),
                        'desc_tip' => true,
                    ],
                ]      
            );
        } else {
            // options for all other roles.
            $this->form_fields = apply_filters(
                'wc_tp_form_fields',
                [
                    'enabled' => [
                        'title' => __('Enable/Disable', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Enable Trust Payments', 'wc-gateway-tp'),
                        'default' => 'yes',
                    ],
                    'enabled_live_mode' => [
                        'title' => __('Enable/Disable Live Mode', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Enable live mode', 'wc-gateway-tp'),
                        'default' => 'yes',
                    ],
                    'title' => [
                        'title' => __('Title', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-tp'),
                        'default' => __('Trust Payments', 'wc-gateway-tp'),
                        'desc_tip' => true,
                    ],
                    'description' => [
                        'title' => __('Description*', 'wc-gateway-tp'),
                        'type' => 'textarea',
                        'description' => __('Payment method description that the customer will see on your checkout.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'jwt_username' => [
                        'title' => __('JWT Username', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Your Trust Payments JWT Username.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'jwt_secret' => [
                        'title' => __('JWT Secret', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Your Trust Payments JWT Secret.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'sitereference' => [
                        'title' => __('Site Reference', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Trust Payments Site Reference.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'ws_username' => [
                        'title' => __('WS Username*', 'wc-gateway-tp'),
                        'type' => 'text',
                        'description' => __('Your Trust Payments Webservices Username.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'ws_password' => [
                        'title' => __('WS Password*', 'wc-gateway-tp'),
                        'type' => 'password',
                        'description' => __('Your Trust Payments Webservices Password.', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'auth_method' => [
                        'title' => __('Auth Method', 'wc-gateway-tp'),
                        'type' => 'select',
                        'description' => __('Auth methods are used to specify how a transaction is to be processed by the card issuer. A pre-authorisation is used to seek authorisation for a transaction and reserve the funds on the customer’s account, in cases where the final amount to be debited from the customer is not known at time of authorisation. A final authorisation is used to seek authorisation for a transaction and reserve the funds on the customer’s account, in cases where the final amount to be debited from the customer is known at time of authorisation.', 'wc-gateway-tp'),
                        'desc_tip' => true,
                        'options' => [
                            'PRE' => 'Pre',
                            'FINAL' => 'Final',
                        ],
                        'default' => 'FINAL',
                    ],
                    'disable_saving_new_cards' => [
                        'title' => __('Disable saving new cards', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Disables customers from saving any new credit/debit cards but allow customers to use previously saved cards ', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => false,
                    ],
                    'disable_saving_cards_and_using_saved_cards' => [
                        'title' => __('Disable save cards options', 'wc-gateway-tp'),
                        'type' => 'checkbox',
                        'label' => __('Disables all saved cards options meaning customers can’t save new credit/debit cards or use saved credit/debit cards ', 'wc-gateway-tp'),
                        'default' => '',
                        'desc_tip' => false,
                    ],
                    'cloudflare_ip_ranges' => [
                        'title' => __('Cloudflare IP Ranges', 'wc-gateway-tp'),
                        'type' => 'textarea',
                        'label' => __('Cloudflare IP Ranges', 'wc-gateway-tp'),
                        'default' => '204.93.240.0/24, 204.93.177.0/24, 199.27.128.0/21, 173.245.48.0/20, 103.21.244.0/22, 103.22.200.0/22, 103.31.4.0/22, 141.101.64.0/18, 108.162.192.0/18, 190.93.240.0/20, 188.114.96.0/20, 197.234.240.0/22, 198.41.128.0/17, 162.158.0.0/15',
                        'description' => __('List of Cloudflare IP Ranges', 'wc-gateway-tp'),
                        'desc_tip' => true,
                    ],
                ]
            );
        }
    }

    /**
     * Generate JWT token.
     *
     * @param array  $data       data to send to payment processor
     * @param string $jwt_secret JWT secret
     */
    private static function generate_jwt_token($data = [], $jwt_secret = '')
    {
        // Create token header as a JSON string.
        $header = wp_json_encode(
            [
                'typ' => 'JWT',
                'alg' => 'HS256',
            ]
        );

        // Create token payload as a JSON string.
        $payload = wp_json_encode($data);

        // Encode header to Base64Url string.
        $base64_url_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        // Encode payload to Base64Url string.
        $base64_url_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        // Create signature hash.
        $signature = hash_hmac('sha256', $base64_url_header.'.'.$base64_url_payload, $jwt_secret, true);

        // Encode signature to Base64Url string.
        $base64_url_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        // Create JWT.
        $jwt = str_replace( "\n", "", $base64_url_header.'.'.$base64_url_payload.'.'.$base64_url_signature );

        return $jwt;
    }

    /**
     * Form Fields To Add To The Payment Page.
     *
     * @return void
     */
    public function payment_fields()
    {
        // Note: this is an extension of a woocommerce function.
        // Eg. class WC_TP_Gateway extends WC_Gateway_Cheque {.

        // If test mode is enabled.
        if ('no' === $this->live_mode) {
            ?>
			<div style="background-color: #F5F5F5;
						padding: 2px 12px;
						border: 1px solid #DCDCDC; 
						border-radius: 5px;
						color: #404040;">
				<strong>
					Payment Test Mode Enabled
				</strong>
			</div>
			<?php
        }

        // get current user id ( user must be logged in ).
        $current_user = wp_get_current_user();
        $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;

        // If save cards enabled.
        if ('yes' === $this->save_cards) {
            // get all saved cards.
            $cards = $this->get_users_saved_card_details(); ?>
			<?php
                // display this option only if user is allowed to use saved cards.
            if (!empty($cards) && 'yes' !== $this->disable_saving_cards_and_using_saved_cards) {
                ?>
					<div class="select-saved-card"> 
						<p>
							<strong>Select saved credit/debit card</strong>
						</p>
						<p>
							<?php
                            // set default value.
                            $card_checked = false;
                // show credit card(s).
                foreach ($cards as $card) {
                    // set checked value for saved credit/debit card(s).
                    $checked = ($this->_tp_transaction_maskedpan == $card['_tp_transaction_maskedpan']) ? ' checked="checked" ' : '';
                    if ($checked) {
                        $card_checked = true;
                    }
                    if (!empty($card['_tp_transaction_saved_card_id'])) {
                        ?>
									<input type="radio" id="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>" name="saved_card" value="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>" class="select_credit_card_checkbox" <?php echo esc_html($checked); ?>>
									<label for="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>"><?php echo esc_html($card['_tp_transaction_maskedpan']).' ('.esc_html($card['_tp_transaction_paymenttypedescription']).')'; ?></label><br>
									<?php
                    }
                }
                // set checked value for new credit/debit card.
                $checked = (empty($card_checked)) ? ' checked="checked" ' : ''; ?>
							<input type="radio" id="use_new_credit_card_checkbox" name="saved_card" value="1" <?php echo esc_html($checked); ?>>
							<label for="use_new_credit_card_checkbox">Make payment using a new credit/debit card.</label><br>
						</p>
					</div>
					<?php
            }

            // Display selected saved card data.
            if ($this->use_users_saved_credit_card_details) {
                ?>
				<div class="selected-saved-card" style="padding-top: 10px;">
					<p>
						<strong>Pay with selected card</strong>
					</p>					 
					<div id="show-credit-card-details" style="padding-bottom: 20px;">
						<?php echo esc_html($this->_tp_transaction_maskedpan); ?> (<?php echo esc_html($this->_tp_transaction_paymenttypedescription); ?>)
					</div>
				</div>	
			<?php
            } else { // Display new card fields.?>
				<div class="selected-saved-card" style="padding-top: 10px;">
					<p>
						<strong>Enter your credit/debit card details</strong>
					</p>					 
				</div>
				<script>
				// jQuery("#use_new_credit_card_checkbox").prop("checked", true);
				</script>
			<?php } ?>

		<?php
        } ?>

		<!--<div id="st-notification-frame"></div>//-->
		<div id="st-card-number" class="st-card-number"></div> 
		<div id="st-expiration-date" class="st-expiration-date"></div>
		<div id="st-security-code" class="st-security-code"></div>

		<!-- Payment options loading notification //-->
		<div class="tp-loading">
			<div style="display:inline-block;vertical-align:top;">
				<img src="<?php echo esc_attr(plugins_url()).'/trust-payments-gateway-3ds2/images/loading.gif'; ?>" alt="img"/>
			</div>
			<div style="display:inline-block;">
				<p>
					Loading, please wait...
				</p>
			</div>
		</div>

		<?php
        // If save cards enabled.
        if ('yes' === $this->save_cards) {
            ?>
			<?php
            // Customer is paying using a saved card.
            // ( Display option to make payment using different card ).
            if (empty($this->use_users_saved_credit_card_details)) {
                // if customer has no saved card.
                // and customer is logged in.
                // and option to save card is not disabled.
                // and option to save card and using saved cards is not disabled.
                if (!empty($userid) && 'yes' !== $this->disable_saving_new_cards && 'yes' !== $this->disable_saving_cards_and_using_saved_cards) {
                    ?>
					<p class="form-row ce-field form-row-wide" id="ce4wp_save_credit_card_details_checkbox" data-priority="">
						<span class="woocommerce-input-wrapper">
							<label class="checkbox ">
								<input type="checkbox" class="input-checkbox " name="save_credit_card_details_checkbox" id="save_credit_card_details_checkbox" value="1"> 
								Save payment information to my account for future purchases&nbsp;
								<?php if (empty($userid)) { ?>
									(note: you must be logged in to save your payment information during purchase).
								<?php } ?>
							</label>
						</span>
						<?php
                        // If customer is not logged in.
                        if (empty($userid)) {
                            ?>
							<script>
							document.getElementById("save_credit_card_details_checkbox").disabled = true;
							</script>
						<?php
                        } ?>
					</p>
				<?php
                } ?>
			<?php
            } ?>
		<?php
        } ?>

		<?php
        // Check if permalinks enabled.
        if (get_option('permalink_structure')) {
            $permalinks = 'true';
        } else {
            $permalinks = 'false';
        }
        // Get the order received URL - needed in JS redirect later.
        $order_received_url = wc_get_endpoint_url('order-received', '', wc_get_page_permalink('checkout')); ?>
		<span id="order_recieved_url" data-url="<?php echo esc_url($order_received_url); ?>" data-permalinks="<?php echo esc_attr($permalinks); ?>"></span>
		<script>
		// remove current payment option from screen when user clicks on option to choose a different saved payment option.
		jQuery( '[name="saved_card"]' ).on('click', function() {
			jQuery( '.selected-saved-card' ).text( 'Loading payment options... ' );
			jQuery( '.st-security-code' ).hide();
		});
		// Save user data form ( eg. address, etc ) when Place order button is clicked.
		jQuery( '#place_order' ).on('click', function() {
			// callback executed when canvas was found
			function handleCanvas(canvas) {
				save_billing_shipping_address( 'handleCanvas(canvas)' );
			}
			// set up the mutation observer
			var observer = new MutationObserver(function (mutations, me) {
				// `mutations` is an array of mutations that occurred
				// `me` is the MutationObserver instance
				var canvas = document.getElementById('order-number');
				if (canvas) {
					handleCanvas(canvas);
					me.disconnect(); // stop observing
					return;
				}
			});
			// start observing
			observer.observe(document, {
				childList: true,
				subtree: true
			});
		});
        // Save billing address details.
		function save_billing_shipping_address( func = '' ) {
			// Set form data.
			formData = new FormData();
			formData.append( 'action', 'tpgw_update_address_myst' );
			formData.append( 'update_address', true );			
			// Order id.
			formData.append( 'orderid', jQuery( '#order-number').data( 'orderid' ) );
			// Billing address.
			formData.append( 'save_credit_card_details_checkbox', jQuery( '#save_credit_card_details_checkbox' ).is( ':checked' ) );	
			formData.append( 'billing_address_1', jQuery( '#billing_address_1' ).val() );
			formData.append( 'billing_address_2', jQuery( '#billing_address_2' ).val() );
			formData.append( 'billing_city', jQuery( '#billing_city' ).val() );	
			formData.append( 'billing_state', jQuery( '#billing_state' ).val() );
			formData.append( 'billing_postcode', jQuery( '#billing_postcode' ).val() );	
			formData.append( 'billing_phone', jQuery( '#billing_phone' ).val() );
			formData.append( 'billing_email', jQuery( '#billing_email' ).val() );
			formData.append( 'billing_first_name', jQuery( '#billing_first_name' ).val() );
			formData.append( 'billing_last_name', jQuery( '#billing_last_name' ).val() );
			// Shipping address.
			formData.append( 'shipping_first_name', jQuery( '#shipping_first_name' ).val() );
			formData.append( 'shipping_last_name', jQuery( '#shipping_last_name' ).val() );	
			formData.append( 'shipping_address_1', jQuery( '#shipping_address_1' ).val() );
			formData.append( 'shipping_address_2', jQuery( '#shipping_address_2' ).val() );
			formData.append( 'shipping_city', jQuery( '#shipping_city' ).val() );	
			formData.append( 'shipping_state', jQuery( '#shipping_state' ).val() );
			formData.append( 'shipping_postcode', jQuery( '#shipping_postcode' ).val() );
            // Shipping rate.
            formData.append( 'shipping_rate', jQuery('.shipping input[type="radio"]:checked').val() );
			// Nonce
			<?php $nonce = wp_create_nonce('update-address-myst-nonce'); ?>
			formData.append( '_wpnonce', '<?php echo esc_attr($nonce); ?>' );
			// Ajax call.
			jQuery.ajax({
				url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
				type: 'post',
				data: formData,
				processData: false,
				contentType: false,
				error       : function(err) {				
					<?php if ('yes' === $this->debug_enabled) { ?>
						log_debug_msg( '',  'Add billing/customer data to updateJWT failed' );
					<?php } ?>
				},
				success     : function(data) {
					// debug.
					<?php if ('yes' === $this->debug_enabled) { ?>
					log_debug_msg( '',  'updateJWT value updated' );
					<?php } ?>
					// on success, update JWT.
					let new_jwt = data.slice( 0, -1 );
					let main_jwt = new_jwt.replace('\n','')
					st.updateJWT( main_jwt );
					// trust payments - place order
					jQuery( '#tp_place_order' ).trigger( 'click' );
				}
			});
		}
		</script>
		<?php
    }

    /**
     * Get WooCommerce Checkout Total.
     * ( based on current woocommerce session value ).
     * 
     * @param array  $arr  array of all cookies
     * @param string $like partial text to search cookie names
     * 
     * @return $k
     */
    public function key_like( array $arr, $like ) {
        foreach ( array_keys( $arr ) as $k ) {
            if ( preg_match( $like, $k ) ) {
                return $k;
            }
        }
        return false;
    }

    /**
     * Subscription payment.
     *
     * @return array $subscription_payment_details
     */
    public function subscription_payment()
    {
		// Set array for subscription payment details.
		$subscription_payment_details = [];

        // Get subscription product data from items in cart.
        if (!empty(WC()->cart->cart_contents)) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                $product_subscription = $cart_item['data'];

                // If item is subscription.
                if (class_exists('WC_Subscriptions_Product') && WC_Subscriptions_Product::is_subscription($product_subscription)) {
                    foreach ($product_subscription->get_meta_data() as $item) {
                        $item_data = $item->get_data();

                        // Set required subscription values for payment.
                        $key_values = [
                            '_subscription_price',           // baseamount ( eg. £3.00 ).
                            '_subscription_period',          // subscriptionunit ( eg. DAY/MONTH ).
                            '_subscription_length',          // subscriptionfinalnumber ( eg. Total Days/Months ).
                            '_subscription_trial_length',    // total number of days, weeks, months, years ( eg. 1 for 1 day ).
                            '_subscription_trial_period',    // days, weeks, months, years.
                            '_subscription_sign_up_fee',     // subscription sign up fee.
                            '_subscription_period_interval', // interval between payments ( eg. get payment every X days/months ).
                        ];

                        // If we have subscription values in item result.
                        if (in_array($item_data['key'], $key_values, true)) {
                            // Subscription price ( eg. £10 ).
                            if ('_subscription_price' === $item_data['key']) {
                                $item_data['value'] = $item_data['value'] * 100;
                            }
                            // Subscription period ( either day or month ).
                            if ('_subscription_period' === $item_data['key']) {
                                $item_data['value'] = strtoupper($item_data['value']);
                            }
                            // Subscription length ( total number of _subscription_period ).
                            if ('_subscription_length' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                            }
                            // Subscription trials period ( days, weeks, months, years ).
                            if ('_subscription_trial_period' === $item_data['key']) {
                                $item_data['value'] = strtoupper($item_data['value']);
                            }
                            // Subscription trials length ( total number of _subscription_trial_period ).
                            if ('_subscription_trial_length' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                            }
                            // Subscription sign up fee ( eg. £10 ).
                            if ('_subscription_sign_up_fee' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'] * 100;
                            }
                            // Subscription period interval ( eg. make payment every '5' days or '2' months, etc ).
                            if ('_subscription_period_interval' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                            }
                            // Add item data to subscription payment details array.
                            $subscription_payment_details[$item_data['key']] = $item_data['value'];
                        }
                    }
                }
            }
        }

        // Sort subscription payment details array by key in ascending order.
        ksort($subscription_payment_details);

        // Return subscription payment details.
        return $subscription_payment_details;
    }

    /**
     * Update Payment Address Details.
     *
     * @param int   $save_card            1/0
     * @param array $billing_details      array containing billing details
     * @param array $shipping_details     array containing shipping details
     * @param int   $order_total          order total
     * @param array $order_shipping_total order shipping total
     *
     * @return $jwt_token
     */
    public function update_payment_address_details($orderid = 0, $save_card = '', $billing_details = [], $shipping_details = [], $shipping_package_rate = 0, $order_total = 0, $order_shipping_total = 0)
    {
        // Global.
        global $wpdb;

		// get current user id ( user must be logged in ).
		$current_user = wp_get_current_user();
		$userid = (!empty($current_user->ID)) ? $current_user->ID : 0;
		$usermeta = (!empty($userid)) ? get_user_meta( $userid ) : [];

        // Get order data
        $order = new WC_Order($orderid);

        // Data to send to payment processor.
        $data = [];
        $data['payload'] = [];
        $data['payload']['accounttypedescription'] = 'ECOM'; // Fixed, (represents an e-commerce transaction).
        $data['payload']['currencyiso3a'] = get_woocommerce_currency(); // Currency code - eg GBP.
        $data['payload']['sitereference'] = $this->sitereference; // Unique reference that identifies the Trust Payments site.
        $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH']; // Force 3D Secure Transaction.
        $data['payload']['termurl'] = 'https://termurl.com'; // Set default value.

        // Order cart.
        $order_cart = (!empty(WC()->cart)) ? strval(round(WC()->cart->get_subtotal(), 2) * 100) : 0; // The amount of the transaction in base units (without any decimal places).

        // Discounts / Coupons ( applied before purchase ).
        if (empty($order)) {
            $order_total = ($order_total > 0) ? $order_total - WC()->cart->get_discount_total() : 0;
            $order_cart = $order_cart - strval(round(WC()->cart->get_discount_total(), 2) * 100);
        }

        // Order total.
        $data['payload']['baseamount'] = (!empty(WC()->cart)) ? strval(round(WC()->cart->get_total('raw'), 2) * 100) : 0;

        // Get WooCommerce Checkout Total.
        // ( based on current woocommerce session value ).
		$customer_details = '';
        $cookie_name  = $this->key_like( $_COOKIE, '/_woocommerce_session_.+/' );
        if ( ! empty( $cookie_name ) ) {
            $cookie_values = ( ! empty( $_COOKIE[$cookie_name] ) ) ? explode( '||', $_COOKIE[$cookie_name] ) : 0;

            if ( is_array( $cookie_values ) ) {
                $cart_data = $wpdb->get_results(
                    $wpdb->prepare("
                        select * 
                        from {$wpdb->prefix}woocommerce_sessions 
                        where session_key = %s", 
                        $cookie_values[0]
                    )
                );
                $decode_cart_data = @unserialize( $cart_data[0]->session_value );
                $cart_total = @unserialize( $decode_cart_data['cart_totals'] );
				$customer_details = @unserialize( $decode_cart_data['customer'] );
            }

            $data['payload']['baseamount'] = ( ! empty( $cart_total['total'] ) ) ? strval( round( $cart_total['total'], 2 ) * 100 ) : $order_cart + $shipping_package_rate;
        }

        // Order reference.
        $data['payload']['orderreference'] = (!empty($orderid)) ? $orderid : $this->orderreference;

		// Get customer data.
		$customer = [];
		if ( WC()->session ) {
			$customer = WC()->session->get('customer');
		}

        // Additional params for payment status result.
        $data['payload']['ruleidentifier'] = ['STR-8', 'STR-9'];
        $data['payload']['successfulurlnotification'] = get_site_url().'?payment_result=success';
        $data['payload']['declinedurlnotification'] = get_site_url().'?payment_result=decline';

        // Billing address.
        $data['payload']['billingfirstname'] = (empty($customer_details['first_name'])) ? $order->get_billing_first_name() : $customer_details['first_name']; // Bob.
        $data['payload']['billinglastname'] = (empty($customer_details['last_name'])) ? $order->get_billing_last_name() : $customer_details['last_name']; // Jones.
        $data['payload']['billingcountryiso2a'] = (empty($customer_details['country'])) ? $order->get_billing_country() : $customer_details['country']; // GB.
        $data['payload']['billingpremise'] = (empty($customer_details['address_1'])) ? $order->get_billing_address_1() : $customer_details['address_1']; // House number.
        $data['payload']['billingstreet'] = (empty($customer_details['address_2'])) ? $order->get_billing_address_2() : $customer_details['address_2']; // Street Name.
        $data['payload']['billingtown'] = (empty($customer_details['city'])) ? $order->get_billing_city() : $customer_details['city']; // Town / City.
        $data['payload']['billingcounty'] = ''; // County.
        $data['payload']['billingpostcode'] = (empty($customer_details['postcode'])) ? $order->get_billing_postcode() : $customer_details['postcode']; // Postcode.
        $data['payload']['billingtelephone'] = (empty($customer_details['phone'])) ? $order->get_billing_phone() : $customer_details['phone']; // Telephone.
        $data['payload']['billingemail'] = (empty($customer_details['email'])) ? $order->get_billing_email() : $customer_details['email']; // Email.

        // Loop throught cart items so we know if we should include the customers delivery address.
        $virtual_product = false;
        if (!empty($data['payload']['baseamount']) && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                if ($product->get_virtual('view')) {
                    $virtual_product = true;
                }
            }
        }

        // Delivery address.
        $data['payload']['customerfirstname'] = (false === $virtual_product && !empty($order->get_shipping_first_name())) ? $order->get_shipping_first_name() : ''; // Bob.
        $data['payload']['customerlastname'] = (false === $virtual_product && !empty($order->get_shipping_last_name())) ? $order->get_shipping_last_name() : ''; // Jones.
        $data['payload']['customercountryiso2a'] = (false === $virtual_product && !empty($order->get_shipping_country())) ? $order->get_shipping_country() : ''; // GB.
        $data['payload']['customerpremise'] = (false === $virtual_product && !empty($order->get_shipping_address_1())) ? $order->get_shipping_address_1() : ''; // House Number.
        $data['payload']['customerstreet'] = (false === $virtual_product && !empty($order->get_shipping_address_2())) ? $order->get_shipping_address_2() : ''; // Street Name.
        $data['payload']['customertown'] = (false === $virtual_product && !empty($order->get_shipping_city())) ? $order->get_shipping_city() : '';  // Town / City.
        $data['payload']['customercounty'] = ''; // County.
        $data['payload']['customerpostcode'] = (false === $virtual_product && !empty($order->get_shipping_postcode())) ? $order->get_shipping_postcode() : ''; // Postcode.

        // Save the card details.
        if (!empty($save_card) && 'true' === $save_card) {
            $data['payload']['credentialsonfile'] = '1';
        }

        // Use saved card details.
        // Check db wp_postmeta table for users previous purchase details, need _tp_transaction_reference value ).
        if (!empty($this->use_users_saved_credit_card_details)) {
            $data['payload']['credentialsonfile'] = '2';
            $data['payload']['parenttransactionreference'] = $this->parenttransactionreference;
            $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH']; // Force 3D Secure Transaction.
        }

		// Count item types in basket.
		$is_subscription = false;
		$count_subscription_type = 0;
		$count_product_type = 0;
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product = wc_get_product( $cart_item['product_id'] );
				if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
					$is_subscription = true;
					$count_subscription_type++;
				}
				$count_product_type++;
			}
		}

        // If basket contains 2+ product types and.
		// at least 1+ product is a subscription type. 
		// 1. Set frequency/unit values for initial MyST subscription, these values cant be null.
		// 2. Then for all further subscription payments, they will be processed by WooCommerce.
		if ( $count_product_type >= 2 && $count_subscription_type >= 1 ) {
			// Credentials on file.
			$data['payload']['credentialsonfile'] = '1';
            // Subscription details for setup.
            $data['payload']['subscriptiontype'] = 'RECURRING';
            $data['payload']['subscriptionnumber'] = '1';
            $data['payload']['subscriptionfrequency'] = '999';
            $data['payload']['subscriptionunit'] = 'MONTH';
		}

        // If purchase is for single subscription item only. 
		// ( or multiples of the same subscription item only ).
		// Additional subscription items in checkout are processed independently.
        if (!empty($this->subscription_payment()) && !empty($is_subscription) && 1 === count( WC()->cart->get_cart() )) {
            // Get subscription payment data.
            $subscription = $this->subscription_payment();

            // Set baseamount ( this is the recurring amount, deducted every period ( eg. day/week/month/year ).
            $data['payload']['baseamount'] = (!empty(WC()->cart->get_total())) ? strval(round(WC()->cart->get_total('raw'), 2) * 100) : 0;

            // Subscription details.
            $data['payload']['subscriptiontype'] = 'RECURRING';
            $data['payload']['subscriptionnumber'] = '1'; // this is the first payment.
            $data['payload']['subscriptionfrequency'] = $subscription['_subscription_period_interval']; // make subscription payment every X days. X months, etc.
            $data['payload']['subscriptionunit'] = $subscription['_subscription_period']; // DAY or MONTH ( UPPERCASE ).
            $data['payload']['subscriptionfinalnumber'] = $subscription['_subscription_length']; // Expiry length of DAY or MONTH ( eg. 13 days, 4 Months, use 0 for no expiry length ).

            // Subscription recurring periods.
            // Trust Payments only supports DAY and MONTH, WooCommerce includes option for WEEK and YEAR so we need to convert those values.
            if ('WEEK' === $subscription['_subscription_period']) { // Convert to 7 days.
                $data['payload']['subscriptionfrequency'] = 7;        // make subscription payment every X days. X months, etc.
                $data['payload']['subscriptionunit'] = 'DAY';    // DAY or MONTH ( UPPERCASE ).
            }
            if ('YEAR' === $subscription['_subscription_period']) { // Convert to 12 months.
                $data['payload']['subscriptionfrequency'] = 12;       // make subscription payment every X days. X months, etc.
                $data['payload']['subscriptionunit'] = 'MONTH';  // DAY or MONTH ( UPPERCASE ).
            }

            // Extra.
            $data['payload']['credentialsonfile'] = '1';
            $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH'];

            // Debug.
            if ('yes' === $this->debug_enabled && !empty($order_total)) {
                // Save REQUEST response to woocommerce log.
                $logger = wc_get_logger();
                $logger->debug(
                    '['.WC_TP_Gateway_Version.'] Subscription details: '.wc_print_r($subscription, true),
                    ['source' => 'trustpayments']
                );
            }

            // If a subscription has a free trial period to start.
            if (!empty($subscription['_subscription_trial_length']) && !empty($subscription['_subscription_trial_period'])) {
                // Get subscription begin date.
                $free_period = '+'.$subscription['_subscription_trial_length'].''.$subscription['_subscription_trial_period'];
                $date = new DateTime($free_period);
                // Add the Subscription begin date and related params to the payload data.
                $data['payload']['subscriptionbegindate'] = $date->format('Y-m-d');
                $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'ACCOUNTCHECK'];
                // If item is free for x time, then we need to calculate the regular cost for each subscription item in cart.
                $recurring_total = 0;
                foreach (WC()->cart->cart_contents as $item_key => $item) {
                    $item_quantity = $item['quantity'];
                    $item_monthly_price = $item['data']->subscription_price;
                    $item_recurring_total = floatval($item_quantity) * floatval($item_monthly_price);
                    $recurring_total += $item_recurring_total;
                }
                // Set the baseamount total
                $data['payload']['baseamount'] += $recurring_total * 100;	
            }
        }

        // Data to send to payment processor cont...
        $data['iat'] = time(); // Time in seconds since Unix epoch ( generated using UTC ).
        $data['iss'] = $this->jwt_username; // The JWT username.

        // Auth method.
        $data['payload']['authmethod'] = (!empty($this->auth_method)) ? $this->auth_method : 'FINAL';

        // Custom fields.
        $data['payload']['customfield4'] = 'Woocommerce';
        $data['payload']['customfield5'] = 'WooCommerce '.WC_VERSION.' (Trust Payments Gateway (3DS2) Version ['.WC_TP_Gateway_Version.'] )';

		// Set baseamount using the total order value.
		if ( ! empty( $orderid ) ) {
			// $data['payload']['baseamount'] = $order->get_total() * 100;
		}

        // Generate token.
        global $jwt_token;
        $jwt_token = self::generate_jwt_token($data, $this->jwt_secret);

        // Debug.
        if ('yes' === $this->debug_enabled && !empty($order_total)) {
            // Save REQUEST response to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] JWT Token Data: '.wc_print_r($data, true),
                ['source' => 'trustpayments']
            );
        }

        // Return token.
        return $jwt_token;
    }

    /**
     * Process The Payment.
     *
     * @param int $order_id the order ID
     */
    public function process_payment($order_id)
    {
        global $wpdb;

        // Debug.
        if ('yes' === $this->debug_enabled) {
            // Get the WC Order.
            $order = new WC_Order($order_id);
            // Save REQUEST response to woocommerce log.
            $logger = wc_get_logger();
            $logger->debug(
                '['.WC_TP_Gateway_Version.'] Process Payment: '.wc_print_r($order, true),
                ['source' => 'trustpayments']
            );
		}

        // Create a payment rnd value.
        $order_rnd = str_shuffle(md5(microtime()));
        update_post_meta($order_id, '_tp_gateway_payment_rnd', $order_rnd);
        $order_rnd = get_post_meta($order_id, '_tp_gateway_payment_rnd');

        // Add a notice message we can check in JS.
        wc_add_notice('<span id="order-number" data-orderid="'.$order_id.'" data-orderrnd="'.$order_rnd[0].'">Trust Payments: </span> '.__('Processing Order', 'woothemes'), 'notice');

        // Get post meta for billing search.
        $post_meta = get_post_meta($order_id, '_billing_address_index');
        if (!empty($post_meta)) {
            // Append order id to the end of the search string.
            // ( so we can search for the order reference on admin > orders page ).
            $post_meta = $post_meta[0].' '.$order_id;
            // Update post meta.
            update_post_meta($order_id, '_billing_address_index', $post_meta);
            // Save myst order ref so we can display on admin > order page(s).
            update_post_meta($order_id, '_myst_order_ref', $order_id);
        }

        // Save the order payment processing value for this payment.
        $order_payment_processing = get_post_meta($order_id, '_tp_gateway_payment_processing');
        if (empty($order_payment_processing)) {
            update_post_meta($order_id, '_tp_gateway_payment_processing', 'active');
        }

        // Save the order cookie so we can process url notifications.
        $order_reference_id = (!empty($_COOKIE['order_reference_id'])) ? sanitize_text_field(wp_unslash($_COOKIE['order_reference_id'])) : '';
        if (!empty($order_reference_id)) {
            update_post_meta($order_id, '_order_reference_id', $order_reference_id);
            // We don't need this cookie anymore, unset cookie.
            unset($_COOKIE['order_reference_id']);
            // Set cookie value to ''.
            setcookie('order_reference_id', '', time() - 86400, '/');
        }

        // Add new customer account.
        $this->new_customer_account($order_id);
    }

    /**
     * New customer account.
     *
     * @param int $order_id the order ID
     */
    public function new_customer_account($order_id = 0)
    {
        // Get $_POST variables.
        $post = (!empty($_POST)) ? $_POST : ''; // phpcs:ignore

        // If we don't have any post, stop here.
        if (empty($post)) {
            return;
        }

        // get tp gateway settings.
        $settings = get_option('woocommerce_tp_gateway_settings');

        // Set $post_ values.
        $post_username = (!empty($post['new_username'])) ? sanitize_text_field(wp_unslash($post['new_username'])) : null;
        $post_password = (!empty($post['new_password'])) ? sanitize_text_field(wp_unslash($post['new_password'])) : null;
        $post_email = (!empty($post['billing_email'])) ? sanitize_text_field(wp_unslash($post['billing_email'])) : null;

        // Set $post billing values.
        $post_billing_first_name = (!empty($post['billing_first_name'])) ? sanitize_text_field(wp_unslash($post['billing_first_name'])) : '';
        $post_billing_last_name = (!empty($post['billing_last_name'])) ? sanitize_text_field(wp_unslash($post['billing_last_name'])) : '';
        $post_billing_company = (!empty($post['billing_company'])) ? sanitize_text_field(wp_unslash($post['billing_company'])) : '';
        $post_billing_address_1 = (!empty($post['billing_address_1'])) ? sanitize_text_field(wp_unslash($post['billing_address_1'])) : '';
        $post_billing_address_2 = (!empty($post['billing_address_2'])) ? sanitize_text_field(wp_unslash($post['billing_address_2'])) : '';
        $post_billing_city = (!empty($post['billing_city'])) ? sanitize_text_field(wp_unslash($post['billing_city'])) : '';
        $post_billing_state = (!empty($post['billing_state'])) ? sanitize_text_field(wp_unslash($post['billing_state'])) : '';
        $post_billing_postcode = (!empty($post['billing_postcode'])) ? sanitize_text_field(wp_unslash($post['billing_postcode'])) : '';
        $post_billing_country = (!empty($post['billing_country'])) ? sanitize_text_field(wp_unslash($post['billing_country'])) : '';
        $post_billing_phone = (!empty($post['billing_phone'])) ? sanitize_text_field(wp_unslash($post['billing_phone'])) : '';

        // Set $post shipping values.
        $post_shipping_first_name = (!empty($post['shipping_first_name']) && 'undefined' !== $post['shipping_first_name']) ? sanitize_text_field(wp_unslash($post['shipping_first_name'])) : $post_billing_first_name;
        $post_shipping_last_name = (!empty($post['shipping_last_name']) && 'undefined' !== $post['shipping_last_name']) ? sanitize_text_field(wp_unslash($post['shipping_last_name'])) : $post_billing_last_name;
        $post_shipping_company = (!empty($post['shipping_company']) && 'undefined' !== $post['shipping_company']) ? sanitize_text_field(wp_unslash($post['shipping_company'])) : $post_billing_company;
        $post_shipping_address_1 = (!empty($post['shipping_address_1']) && 'undefined' !== $post['shipping_address_1']) ? sanitize_text_field(wp_unslash($post['shipping_address_1'])) : $post_billing_address_1;
        $post_shipping_address_2 = (!empty($post['shipping_address_2']) && 'undefined' !== $post['shipping_address_2']) ? sanitize_text_field(wp_unslash($post['shipping_address_2'])) : $post_billing_address_2;
        $post_shipping_city = (!empty($post['shipping_city']) && 'undefined' !== $post['shipping_city']) ? sanitize_text_field(wp_unslash($post['shipping_city'])) : $post_billing_city;
        $post_shipping_state = (!empty($post['shipping_state']) && 'undefined' !== $post['shipping_state']) ? sanitize_text_field(wp_unslash($post['shipping_state'])) : $post_billing_state;
        $post_shipping_postcode = (!empty($post['shipping_postcode']) && 'undefined' !== $post['shipping_postcode']) ? sanitize_text_field(wp_unslash($post['shipping_postcode'])) : $post_billing_postcode;
        $post_shipping_country = (!empty($post['shipping_country']) && 'undefined' !== $post['shipping_country']) ? sanitize_text_field(wp_unslash($post['shipping_country'])) : $post_billing_country;

        // If we want to create a new user.
        if (!empty($post['payment_method']) && 'tp_gateway' === $post['payment_method']) {
            // attempt to create user.
            if (!empty($post_username) && !empty($post_password) && !empty($post_email)) {
                // create user.
                $user_id = wp_create_user($post_username, $post_password, $post_email);
                // save userid for payment reference.
                update_post_meta($order_id, 'tp_userid', $user_id);
                // response based on result of creating user.
                if (is_wp_error($user_id)) {
                    // error with registering user.
                    $response = $user_id->get_error_message();
                } else {
                    // Save users billing details.
                    add_user_meta($user_id, 'billing_first_name', $post_billing_first_name);
                    add_user_meta($user_id, 'billing_last_name', $post_billing_last_name);
                    add_user_meta($user_id, 'billing_company', $post_billing_company);
                    add_user_meta($user_id, 'billing_address_1', $post_billing_address_1);
                    add_user_meta($user_id, 'billing_address_2', $post_billing_address_2);
                    add_user_meta($user_id, 'billing_city', $post_billing_city);
                    add_user_meta($user_id, 'billing_state', $post_billing_state);
                    add_user_meta($user_id, 'billing_postcode', $post_billing_postcode);
                    add_user_meta($user_id, 'billing_country', $post_billing_country);
                    add_user_meta($user_id, 'billing_phone', $post_billing_phone);
                    // Save users shipping details.
                    add_user_meta($user_id, 'shipping_first_name', $post_shipping_first_name);
                    add_user_meta($user_id, 'shipping_last_name', $post_shipping_last_name);
                    add_user_meta($user_id, 'shipping_company', $post_shipping_company);
                    add_user_meta($user_id, 'shipping_address_1', $post_shipping_address_1);
                    add_user_meta($user_id, 'shipping_address_2', $post_shipping_address_2);
                    add_user_meta($user_id, 'shipping_city', $post_shipping_city);
                    add_user_meta($user_id, 'shipping_state', $post_shipping_state);
                    add_user_meta($user_id, 'shipping_postcode', $post_shipping_postcode);
                    add_user_meta($user_id, 'shipping_country', $post_shipping_country);
                    // Send welcome email.
                    $wc = new WC_Emails();
                    $wc->customer_new_account($user_id);
                    // Debug.
                    if ('yes' === $settings['enabled_log_details']) {
                        // Set data for debug.
                        $debug_email = [];
                        $debug_email['email'] = $post_email;
                        // Save REQUEST response to woocommerce log.
                        $logger = wc_get_logger();
                        $logger->debug(
                            '['.WC_TP_Gateway_Version.'] Checkout Page: New Registered User => '.wc_print_r($debug_email, true),
                            ['source' => 'trustpayments']
                        );
                    }
                }
            }
        }
    }

    /**
     * Add code to footer - so it loads at end of page.
     *
     * @return void
     */
    public function footer_scripts()
    {
        // Only load on the checkout page.
        if (is_checkout()) {

			// If Plugins > trust-payments-gateway-3ds2 is set to active, lets check if the admin settings are set to active too.
			if ( empty( get_option( 'woocommerce_tp_gateway_settings' ) ) || 'no' === get_option( 'woocommerce_tp_gateway_settings' )['enabled'] ) {
				// The admin setting is set to inactive, so lets stop here.
				return;
			}

            // Get the token created in payment_fields().
            global $jwt_token;

            // If user is logging in on the checkout page.
            // Set cookie wordpress_logged_in_ path value to /.
            $listings = [];
            foreach ($_COOKIE as $name => $content) {
                if (strpos($name, 'wordpress_logged_in_') !== false) {
                    setcookie($name, $content, time() + 3600, '/');
                }
            } ?>
			<script>
				// Get user details after new registration.
				// Then set billing/shipping vals on new checkout page (after login reload).
				<?php
                $wc_session = WC()->session->get('customer');
            if (!empty($wc_session['first_name'])) {
                function formatStr($str = '')
                {
                    return str_replace('"', "'", $str);
                } ?>
                        jQuery( "#billing_first_name" ).val( "<?php echo formatStr($wc_session['first_name']); ?>" )
                        jQuery( "#billing_last_name" ).val( "<?php echo formatStr($wc_session['last_name']); ?>" )
                        jQuery( "#billing_company" ).val( "<?php echo formatStr($wc_session['company']); ?>" )
                        jQuery( "#billing_address_1" ).val( "<?php echo formatStr($wc_session['address_1']); ?>" )
                        jQuery( "#billing_address_2" ).val( "<?php echo formatStr($wc_session['address_2']); ?>" )
                        jQuery( "#billing_city" ).val( "<?php echo formatStr($wc_session['city']); ?>" )
                        jQuery( "#billing_state" ).val( "<?php echo formatStr($wc_session['state']); ?>" )
                        jQuery( "#billing_postcode" ).val( "<?php echo formatStr($wc_session['postcode']); ?>" )
                        jQuery( "#billing_phone" ).val( "<?php echo formatStr($wc_session['phone']); ?>" )
                        jQuery( "#billing_email" ).val( "<?php echo formatStr($wc_session['email']); ?>" )
                        jQuery( "#shipping_first_name" ).val( "<?php echo formatStr($wc_session['shipping_first_name']); ?>" )
                        jQuery( "#shipping_last_name" ).val( "<?php echo formatStr($wc_session['shipping_last_name']); ?>" )
                        jQuery( "#shipping_company" ).val( "<?php echo formatStr($wc_session['shipping_company']); ?>" )
                        jQuery( "#shipping_address_1" ).val( "<?php echo formatStr($wc_session['shipping_address_1']); ?>" )
                        jQuery( "#shipping_address_2" ).val( "<?php echo formatStr($wc_session['shipping_address_2']); ?>" )
                        jQuery( "#shipping_city" ).val( "<?php echo formatStr($wc_session['shipping_city']); ?>" )
                        jQuery( "#shipping_state" ).val( "<?php echo formatStr($wc_session['shipping_state']); ?>" )
                        jQuery( "#shipping_postcode" ).val( "<?php echo formatStr($wc_session['shipping_postcode']); ?>" )
                    <?php
            } ?>

				// Disable refresh button. 
				var ctrlKeyDown = false;
				jQuery( document ).ready( function() {  
					jQuery( document ).on( "keydown", keydown );
					jQuery( document ).on( "keyup", keyup );
				});
				function keydown( e ) {
					if ( ( e.which || e.keyCode ) == 116 || ( ( e.which || e.keyCode ) == 82 && ctrlKeyDown ) ) {
						// Pressing F5 or Ctrl+R.
						<?php if ('yes' === $this->debug_enabled) { ?>
						log_debug_msg( '',  'Pressed F5 key or Ctrl+R keys' );						 						
						<?php } ?>
						// Result.
						e.preventDefault();
					} else if ( ( e.which || e.keyCode ) == 17 ) {
						// Pressing Ctrl only.
						<?php if ('yes' === $this->debug_enabled) { ?>
						log_debug_msg( '',  'Pressed Ctrl key' );						
						<?php } ?>
						// Result.
						ctrlKeyDown = true;
					}
				};
				function keyup( e ){
					// Key up Ctrl.
					if ( ( e.which || e.keyCode ) == 17 ) {
						// Result.
						ctrlKeyDown = false;
					}
				};

				// Add description to credit card select option.
				jQuery( 'label[for="payment_method_tp_gateway"]' ).append( '<?php echo (!empty($this->description)) ? ' - '.esc_html(preg_replace("/\r|\n/", '', $this->description)) : ''; ?>' );

				// Reset enabling form.
				window.EnablingForm = false;

				// Works best with jQuery...
				jQuery ( window ).on('load', function () {
					// remove duplicated billing/shipping output on confirmation page
					jQuery( '.woocommerce-customer-details:not(:first)').remove();
					// Set placeholder values for fields.
					jQuery( '#billing_address_1' ).attr("placeholder", "House/Flat number");
					jQuery( '#billing_address_2' ).attr("placeholder", "House/Flat street name");
					jQuery( '#shipping_address_1' ).attr("placeholder", "House/Flat number");
					jQuery( '#shipping_address_2' ).attr("placeholder", "House/Flat street name");					
					// Check we are on the checkout page and have the form.
					var wcForm = document.getElementsByName( 'checkout' );
					if ( wcForm.length !== 0 ) {
						// Append the ID to the form.
						wcForm[0].setAttribute( 'id', 'tp-form' );

						// Trigger our function once the checkout has finished updating.
						jQuery( document.body ).on( 'updated_checkout', function () {
							// Check we haven't already ran the code. ( this runs on every page load... )
							if ( jQuery( '#tp_place_order' ).length <= 0 ) {
								setTimeout(function() {
									tpWCGatewayEnableForm( 'updated_checkout' );
								}, 500);
							}
						});

						// Check when our payment gateway is selected that the code ran
						jQuery( document.body ).on( 'payment_method_selected', function () {
							// Get the selected payment method.
							var selectedPaymentMethod = jQuery( '.woocommerce-checkout input[name="payment_method"]:checked' ).attr( 'id' );
							// If its Trust Payments.
							if ( 'payment_method_tp_gateway' == selectedPaymentMethod ) {
								// tpWCGatewayEnableForm( 'payment_method_selected' );
								// Check we haven't already ran the code.
								if ( jQuery( '#tp_place_order' ).length <= 0 ) {
									// tpWCGatewayEnableForm( 'payment_method_selected' );
								}
							}
						});

						// Handle 'false' checkout error (triggered via process_payment function).
						jQuery( document.body ).on( 'checkout_error', function () {
							// Check there is no active error and we have an info message.
							if ( ! jQuery( '.woocommerce-error' ).length && jQuery( '.woocommerce-info' ).length ) {
								// Check our message is present.
								if (jQuery( '#tp-form .woocommerce-info' ).html().toLowerCase().indexOf( 'trust payments:' ) >= 0) {
									// If so we have passed WC validation so lets take payment via original submit.
									// jQuery( '#tp_place_order' ).trigger( 'click' );
									// Add the loading overlay.
									jQuery( '#tp-form' ).block({
										message: null,
										overlayCSS: {
											background: "#fff",
											opacity: .6
										}
									});
								}
							}
							 // If postcode is not valid.
							 // else {
								// jQuery( '#Cardinal-ElementContainer' ).hide()
							 // }
						});

						// If user ticks the field to select saved credit card.
						jQuery( document ).ready( function() {	
							jQuery( document ).on( 'click', '.select_credit_card_checkbox', function() {
								if ( this.checked ) {
									// set card choice value.
									var c_id = this.value;
									// Reset saved cc details and reload the page ( onload, focus on payment form ).
									<?php if (strpos(wc_get_checkout_url(), 'page_id') !== false) { ?>	
										// Url contains: ?page_id=
										window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>&cID=' + c_id;
									<?php } else { ?>
										// Url contains: /checkout 
										window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>?cID=' + c_id;
									<?php } ?>								
								}
							});
						});						

						// If user ticks the field to use a new credit card.
						jQuery( document ).unbind( 'click' ).on( 'click', '#use_new_credit_card_checkbox', function() {
							if ( this.checked ) {
								// Reset saved cc details and reload the page ( onload, focus on payment form ).
								<?php if (strpos(wc_get_checkout_url(), 'page_id') !== false) { ?>	
									// Url contains: ?page_id=
									window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>&reset=<?php echo esc_attr($this->_tp_transaction_saved_card_id); ?>';
								<?php } else { ?>
									// Url contains: /checkout 
									window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>?reset=<?php echo esc_attr($this->_tp_transaction_saved_card_id); ?>';
								<?php } ?>
							}
						});	

						// Add register button on checkout page.
						if ( jQuery( '.create-account' ).length > -1 ) {
                            var register_submit = '<div class="button alt submit_register_account" name="submit_register_account" id="submit_register_account" style="width:100%;margin-top:10px;margin-bottom:20px;text-align:center;">Register</div>'
                            jQuery( '#account_password_field' ).append( register_submit )
							// On register button click
							jQuery('.submit_register_account').click(function() {
                                // Select Trust Payments as payment method
                           		jQuery('.payment_method_tp_gateway label').trigger('click')
                                // Update Place Order request
								jQuery( '#place_order' ).trigger( 'click' )
							});
						}		
					}
				});

				// Create ST object.
				var st;					

				// Enable the Trust Payments form
				function tpWCGatewayEnableForm(trigger) {
					<?php if ('yes' === $this->debug_enabled) { ?>
                        var debug_msg = '====== Transaction Start - tpWCGatewayEnableForm('+trigger+') ======';
						log_debug_msg( '', debug_msg );
					<?php } ?>

					// Check we are not already enabling the form.
					if ( window.EnablingForm == false ) {
						window.EnablingForm = true;
						// If we already have a st object. 
						if ( st ) {
							// We need to destroy the current st object and create a new updated one.
							st.destroy();
						}
						// Enable the form object.
						// Note for JS: By default, our 3-D Secure authentication targets the sandbox environment. To override this setting and instead target the production environment, you will need to ensure your payment form specifies.
						// livestatus: 1.
						st = SecureTrading({
							jwt : '<?php echo esc_html($jwt_token); ?>',
							deferInit: true,						  // Allow updates to order
							panIcon : true,                           // Enable card icon
							submitOnSuccess : false,                  // Do not submit
							submitCallback : tpGatewaySubmitCallback, // Process submit callback
							// successCallback : tpGatewaySuccessCallback,
							errorCallback: tpGatewayErrorCallback,
							formId: 'tp-form',                        // Specify our own form ID
                		    buttonId: 'tp_place_order',               // Specify our own button ID
							stopSubmitFormOnEnter: true,              // Stop enter key submitting the form
							// use saved credit card
							<?php if ($this->use_users_saved_credit_card_details) { ?>
								fieldsToSubmit: ['securitycode'],
							<?php } ?>
							<?php if ('yes' === $this->live_mode) { ?>
								livestatus: 1,
							<?php } ?>
							// submitOnCancel
							// cancelCallback
							translations:{'Expiration date': 'Expiry date'},
						});
						<?php if ('yes' === $this->debug_enabled) { ?>
							log_debug_msg( '',  'ST Config: ' + JSON.stringify( st.config, null, 1) );											
						<?php } ?>

						// Hide the loading text.
						jQuery( '.tp-loading' ).fadeOut();
						// Create our own input so we don't hijack the WC submit button.
						jQuery( '.place-order' ).append( '<input id="tp_place_order" type="hidden">' );
						// Initialise the payment fields.
						st.Components();
						// Completed enabling form.
						window.EnablingForm = false;
					}
					// Add description to credit card select option.
					jQuery( 'label[for="payment_method_tp_gateway"]' ).append( '<?php echo (!empty($this->description)) ? ' - '.esc_html(preg_replace("/\r|\n/", ' ', $this->description)) : ''; ?>' );
				}

				// Function called on submission
				function tpGatewaySubmitCallback( data ) {
					<?php if ('yes' === $this->debug_enabled) { ?>
						log_debug_msg( '',  'TP Submit Callback: ' + JSON.stringify( data, null, 1) );						
					<?php } ?>
					// Save transaction data
					window.transactiondata = data;					
					// Save the transaction reference
					window.transactionreference = data['transactionreference'];
					// Save maskedpan	
					window.transaction_maskedpan = data['maskedpan'];
					// Save paymenttypedescription
					window.transaction_paymenttypedescription = data['paymenttypedescription'];

                    // Set error msg.
                    // https://webapp.securetrading.net/errorcodes.html.
                    let error = false;
                    switch( Number( data.errorcode ) ) {
                        case 70000:
                            error = 'Transaction declined by card issuer. Please re-attempt with another card or contact your card issuer.';
                            break;
                        case 71000:
                            error = 'Transaction declined by card issuer. SCA Required. Please contact the merchant.';
                            break;
                        case 60010:
                            error = 'Unable to process transaction. Please try again and contact the merchant if the issue persists.';
                            break;
                        case 60110:
                            error = 'Unable to process transaction.';
                            break;
                        case 60022:
                            error = 'Transaction declined, 3-D Secure authentication has failed.';
                            break;
                        case 60102:
                            error = 'Transaction has been declined.';
                            break;
                        case 60103:
                            error = 'Transaction has been declined.';
                            break;
                        case 60104:
                            error = 'Transaction has been declined.';
                            break;
                        case 60105:
                            error = 'Transaction has been declined.';
                            break;
                        case 60106:
                            error = 'Transaction has been declined.';
                            break;
                        case 60108:
                            error = 'Transaction declined, 3-D Secure authentication has failed.';
                            break; 
                        case 50003:
                            error = 'jwt invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.';
                            break;
                        case 30006:
                            error = 'incorrect sitereference, please contact the merchant - Invalid data received (30006)';
                            break;
                        case 30000: 
                            error = 'Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.';
							if ( data.errormessage === 'Invalid field' ) {
								if ( data.errordata[0] ==='billingpostcode' ) {
									error = ( data.errordata[0] ==='billingpostcode') ? 'Invalid Billing Postcode' : '' ;
								}
								if ( data.errordata[0] ==='expirydate' ) {
									error = ( data.errordata[0] ==='expirydate') ? 'Invalid Expiry Date' : '' ;
								}
							}
                            break;                         
                    }
					// Error.
                    if ( error ) {
                        // Display error message.
                        let missingFields = '<ul class="woocommerce-error" role="alert">';
                            missingFields+= '	<li class="errorCallback">Unfortunately, the following error has occurred.</li>';
                            if ( data.errordata && data.errordata[0] === 'jwt' ) {
                                missingFields+= '<li> - jwt invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.</li>';
                            } 
                            else if ( data.errordata && data.errordata[0] === 'sitereference' ) {
                                missingFields+= '<li> - incorrect sitereference, please contact the merchant - Invalid data received (30000)</li>';
                            } 
                            else if ( data.errordata && data.errordata[0] === 'baseamount' ) {
                                return // We dont need to do anything on initial page load.
                            }
                            else {
                                missingFields+= '<li> - ' + error + '</li>';
                            }
					        missingFields+= '</ul>';

                        if ( jQuery( '.woocommerce-notices-wrapper' ) ) {
                            jQuery( '.woocommerce-notices-wrapper' )[1].innerHTML = missingFields;
                        }
                        else {
                            jQuery( '#tp-form .woocommerce-info' ).after( missingFields );
                        }
                        // Log callback error.
                        <?php if ('yes' === $this->debug_enabled) { ?>
							log_debug_msg( '',  'TP Submit Callback Error: ' + JSON.stringify( data, null, 1) );
						<?php } ?>
						// Return false.
						return;
                    } else {
						// Else, alls good.
						tpGatewaySuccessCallback( data )
					}
				}

				// function called on an error.
				function tpGatewayErrorCallback() {
					<?php if ('yes' === $this->debug_enabled) { ?>
						log_debug_msg( '',  'TP Error Callback' );
					<?php } ?>
					// Hide the processing order notice.
					jQuery( '.woocommerce-NoticeGroup-checkout .woocommerce-info' ).remove();
					// Remove loading overlay.
					jQuery( '#tp-form' ).unblock();
					return false;
				}

				// function called on succesful payment
				function tpGatewaySuccessCallback( data ) {
					<?php if ('yes' === $this->debug_enabled) { ?>
						log_debug_msg( '',  'TP Success Callback ( card details ): ' + window.transactionreference + ', ' + window.transaction_maskedpan + ', ' + window.transaction_paymenttypedescription );
						log_debug_msg( '',  'TP Success Callback ( process data ) ');
					<?php } ?>
					// Set form data.
					formData = new FormData();
					formData.append( 'action', 'tpgw_handle_payment' );
					// From tpGatewaySubmitCallback().
					formData.append( 'transactionreference', window.transactionreference);
					formData.append( 'transactiondata', JSON.stringify( window.transactiondata, null, 1) );					
					formData.append( 'maskedpan', window.transaction_maskedpan);
					formData.append( 'paymenttypedescription', window.transaction_paymenttypedescription);
					// Save option to reuse same credit card if user ticked box.
					var x = jQuery( '#save_credit_card_details_checkbox' ).is( ':checked' );
					var checked_value = ( true === x ) ? 1 : 0;
					formData.append( 'savecreditcardoption', checked_value );
					// From process_payment().
					var orderId = jQuery( '#order-number').data( 'orderid');
					formData.append( 'orderid', orderId);
					var orderRnd = jQuery( '#order-number').data( 'orderrnd');
					formData.append( 'orderrnd', orderRnd);
					// Nonce
					<?php $nonce = wp_create_nonce('checkout-nonce'); ?>
					formData.append( '_wpnonce', '<?php echo esc_attr($nonce); ?>' );
					// Order settle status.
					formData.append( 'settlestatus', window.transactiondata.settlestatus);				
					// Get the url for order recieved.
					var orderRecievedUrl = jQuery( '#order_recieved_url' ).data( 'url' );
					// If not using permalinks append = to url
					var permalinks = jQuery( '#order_recieved_url' ).data( 'permalinks' );
					if ( false == permalinks ) {
						orderRecievedUrl = orderRecievedUrl + '=';
					}
					// Ajax call.
					jQuery.ajax({
						url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
						type: 'post',
						data: formData,
						processData: false,
						contentType: false,
						error       : function(err) {
							<?php if ('yes' === $this->debug_enabled) { ?>
								log_debug_msg( '', '====== Transaction End - tpGatewaySuccessCallback Failed: ' + JSON.stringify( err, null, 1) + ' ======');
							<?php } ?>
						},
						success     : function( result ) {
							<?php if ('yes' === $this->debug_enabled) { ?>
								log_debug_msg( '', '====== Transaction End - tpGatewaySuccessCallback success: ' + JSON.stringify( result, null, 1) + ' ======' );
							<?php } ?>
							<?php
							 if( ! empty( $_COOKIE ) ) {
								foreach ( $_COOKIE as $key=>$val ){
								   if ( str_contains( $key, 'wp_woocommerce_session_' ) ) {
									   unset( $_COOKIE[ $key ] );
									   setcookie( $key, '', time()-3600 );
								   }
								}
							}
							?>
							// Redirect to order received page.
							if ( result ) {
								if ( false == permalinks ) {
									top.location.replace( orderRecievedUrl + orderId + '&key=' + result );
								} else {
									top.location.replace( orderRecievedUrl + '/' + orderId + '?key=' + result );
								}
							} else {
								top.location.replace( orderRecievedUrl + orderId );
							}
						}
					});

				}

				/**
				 * Log message in WooCommerce > Status > Logs.
				 *
				 * @param string $orderid order id.
				 * @param string $message debug message.
				 */
				function log_debug_msg( orderid = '', message = '' ) {
					// Ajax form.
					formData = new FormData();
					formData.append( 'action', 'tpgw_log_debug' );
					formData.append( 'orderid', orderid );
					formData.append( 'message', message );
					// Nonce
					<?php $nonce = wp_create_nonce('log-debug-nonce'); ?>
					formData.append( '_wpnonce', '<?php echo esc_attr($nonce); ?>' );			
					// Ajax call.
					jQuery.ajax({
						url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
						type: 'post',
						data: formData,
						processData: false,
						contentType: false,
						error       : function(err) {},
						success     : function(data) {},
					});
				}
			</script>
			<?php
        }
    }

    /**
     * Display transation details on order page.
     *
     * @param object $order the WooCommerce order
     *
     * @return void
     */
    public function display_order_transaction_details($order)
    {
        $order_id = $order->get_id();
        // If there is a transaction reference, display it.
        if ((get_post_meta($order_id, '_tp_transaction_reference', true)) || $this->get_transaction_query($order_id)) {
            echo '<p class="form-field form-field-wide '.esc_attr($this->id).'" style="margin-top:20px;">';
            echo '<h3 class="woocommerce-order-data__heading" style="margin-bottom: 15px;">Trust Payments Details</h3>';
            // show transaction data.
            $json_data = get_post_meta($order_id, '_tp_transaction_data', true);
            $json_result = json_decode(str_replace('\\', '', $json_data));
            // if transaction data is empty, use get_transaction_query().
            if (empty($json_result->transactionreference)) {
                $json_result = $this->get_transaction_query($order_id);
            }
            // display results.
            echo (!empty($json_result->transactionreference)) ? '<b>Transaction reference</b>: '.esc_html($json_result->transactionreference).'<br />' : '';
            echo (!empty($json_result->maskedpan)) ? '<b>Masked Pan</b>: '.esc_html($json_result->maskedpan).'<br />' : '';
            echo (!empty($json_result->paymenttypedescription)) ? '<b>Payment type description</b>: '.esc_html($json_result->paymenttypedescription).'<br />' : '';
            // ( in the future add Expiry date ? ).
            echo (!empty($json_result->issuer)) ? '<b>Issuer</b>: '.esc_html($json_result->issuer).'<br />' : '';
            echo (!empty($json_result->issuercountryiso2a)) ? '<b>Issuer country iso 2a</b>: '.esc_html($json_result->issuercountryiso2a).'<br />' : '';
            echo (!empty($json_result->securityresponseaddress)) ? '<b>Security response address</b>: '.esc_html($json_result->securityresponseaddress).'<br />' : '';
            echo (!empty($json_result->securityresponsepostcode)) ? '<b>Security response postcode</b>: '.esc_html($json_result->securityresponsepostcode).'<br />' : '';
            echo (!empty($json_result->securityresponsesecuritycode)) ? '<b>Security response security code</b>: '.esc_html($json_result->securityresponsesecuritycode).'<br />' : '';
            echo (!empty($json_result->enrolled)) ? '<b>3D Enrolled</b>: '.esc_html($json_result->enrolled).'<br />' : '';
            echo (!empty($json_result->status)) ? '<b>3D Status</b>: '.esc_html($json_result->status).'<br />' : '';
            echo (!empty($json_result->authcode)) ? '<b>Auth Code</b>: '.esc_html($json_result->authcode).'<br />' : '';
            echo '</p>'; ?>
			<script>
			jQuery(document).ready(function() {
				// Show refund button.
				jQuery( '.refund-items' ).click(function(){
					// If refund button has already been added
					if ( jQuery( '#tp_refund_button' ).length ) {
						return;
					}
					// Else, add button
					jQuery( '.refund-actions' ).prepend( '<button type="button" id="tp_refund_button" class="button button-primary do-trust-payment-refund tips">Refund <?php echo esc_attr(get_woocommerce_currency_symbol()); ?><span class="trust-payment-refund-amount">0</span> Trust Payments</button>' );						   
				}); 
				// Show refund amount on refund button.
				jQuery('.wc_input_price').on( 'change paste keyup', function() {
					jQuery('.trust-payment-refund-amount').text( jQuery(this).val() );
				});
				// Refund purchase.
				function refund_purchase() {
                    if ( false === window.confirm( woocommerce_admin_meta_boxes.i18n_do_refund ) ) {
                        // Close notice, the user clicked the cancel button, so lets stop here.
                    } else {
                        // Process refund with MyST.
                        // Check WS username/password, if not valid, stop here.
                        var ws_username = '<?php echo (!empty($this->ws_username)) ? esc_html($this->ws_username) : ''; ?>';
                        var ws_password = '<?php echo (!empty($this->ws_password)) ? esc_html($this->ws_password) : ''; ?>';
                        if ( ! ws_username ) { // WP Username empty.
                            alert( 'Error: Webservices username value is null. Check your Trust Payments settings for more details.' );
                            return false; // End here, we don't want to go any further.
                        }
                        if ( ! ws_password ) { // WP Password empty.
                            alert( 'Error: Webservices password value is null. Check your Trust Payments settings for more details.' );
                            return false; // End here, we don't want to go any further.
                        }       
                        // Ajax form.
                        formData = new FormData();
                        formData.append( 'action', 'tpgw_refund_purchase' ); 
                        formData.append( 'orderid', '<?php echo esc_html($order->get_id()); ?>' );
                        formData.append( 'parenttransactionreference', '<?php echo esc_html(get_post_meta($order_id, '_tp_transaction_reference', true)); ?>' );
                        formData.append( 'baseamount', ( jQuery('#refund_amount').val() * 100 ) );
                        // Nonce
                        <?php $nonce = wp_create_nonce('refund-nonce'); ?>
                        formData.append( '_wpnonce', '<?php echo esc_attr($nonce); ?>' );
                        // Ajax call.
                        jQuery.ajax({
                            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                            type: 'post',
                            data: formData,
                            processData: false,
                            contentType: false,
                            error   : function(err) {},
                            success : function(data) {
                                // If the WS username/password values are not current ( unauthorized ).
                                if ( data.includes( 'Unauthorized' ) ) {
                                    // Inform user of invalid details.
                                    console.log( '//  Unable to issue refund, invalid WS Username/Password.' )
                                    alert( 'Error: Unable to issue refund, invalid WS Username/Password.' );
                                } 
                                else if ( data.includes( 'NoBaseAmountValue' ) ) {
                                    // Unable to issue refund, no refund amount provided.
                                    console.log( '//  Unable to issue refund, no refund amount provided.' )
                                    alert( 'Error: Unable to issue refund, no refund amount provided.' );
                                }
                                else if ( data.includes( 'Decline' ) ) {
                                    // No baseamount value.
                                    console.log( '// No baseamount value.' )
                                    alert( 'Error: Refund has declined, please try again.' );
                                } 
                                else {
                                    // Process refund with WooCommerce.
                                    // Get refund details.
                                    var refund_amount   = jQuery( 'input#refund_amount' ).val();
                                    var refund_reason   = jQuery( 'input#refund_reason' ).val();
                                    var refunded_amount = jQuery( 'input#refunded_amount' ).val();
                                    // Get line item refunds.
                                    var line_item_qtys       = {};
                                    var line_item_totals     = {};
                                    var line_item_tax_totals = {};
                                    jQuery( '.refund input.refund_order_item_qty' ).each(function( index, item ) {
                                        if ( jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                                            if ( item.value ) {
                                                line_item_qtys[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] = item.value;
                                            }
                                        }
                                    });
                                    jQuery( '.refund input.refund_line_total' ).each(function( index, item ) {
                                        if ( jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                                            line_item_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] = accounting.unformat(
                                                item.value,
                                                woocommerce_admin.mon_decimal_point
                                            );
                                        }
                                    });
                                    jQuery( '.refund input.refund_line_tax' ).each(function( index, item ) {
                                        if ( jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                                            var tax_id = jQuery( item ).data( 'tax_id' );

                                            if ( ! line_item_tax_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] ) {
                                                line_item_tax_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ] = {};
                                            }

                                            line_item_tax_totals[ jQuery( item ).closest( 'tr' ).data( 'order_item_id' ) ][ tax_id ] = accounting.unformat(
                                                item.value,
                                                woocommerce_admin.mon_decimal_point
                                            );
                                        }
                                    });
                                    // Compile refund data.
                                    var data = {
                                        action                : 'woocommerce_refund_line_items',
                                        order_id              : woocommerce_admin_meta_boxes.post_id,
                                        refund_amount         : refund_amount,
                                        refunded_amount       : refunded_amount,
                                        refund_reason         : refund_reason,
                                        line_item_qtys        : JSON.stringify( line_item_qtys, null, '' ),
                                        line_item_totals      : JSON.stringify( line_item_totals, null, '' ),
                                        line_item_tax_totals  : JSON.stringify( line_item_tax_totals, null, '' ),
                                        api_refund            : false,
                                        restock_refunded_items:jQuery( '#restock_refunded_items:checked' ).length ? 'true': 'false',
                                        security              : woocommerce_admin_meta_boxes.order_item_nonce
                                    }; 
                                    // Send refund data.
                                    jQuery.ajax( {
                                        url:     woocommerce_admin_meta_boxes.ajax_url,
                                        data:    data,
                                        type:    'POST',
                                        success: function( response ) {
                                            if ( true === response.success ) {
                                                // Redirect to same page for show the refunded status.
                                                window.location.reload();
                                            } else {
                                                window.alert( response.data.error );
                                            }
                                        },
                                    } );
                                }                           
                            }
                        });
                    }
				}
				jQuery( '#woocommerce-order-items' ).on( 'click', 'button.do-trust-payment-refund', refund_purchase);
			});			
			</script>	
			<?php
        }
    }

    /**
     * Control admin payment form settings.
     * ( when a form value is changed ).
     */
    public function control_admin_payment_form_settings()
    {
        ?>
		<script>
		jQuery( '#woocommerce_tp_gateway_enabled_live_mode' ).on('click', function() {
			var live_mode_checked = jQuery( '#woocommerce_tp_gateway_enabled_live_mode:checkbox:checked' ).is( ':checked' );
			var debug_log_checked = jQuery( '#woocommerce_tp_gateway_enabled_log_details:checkbox:checked' ).is( ':checked' );
			if ( ! debug_log_checked && ! live_mode_checked ) {
				alert( 'You are enabling test mode. Please ensure you update the Site Reference to the test value too.' );
			}
		});
		jQuery( document ).on( 'submit', '#mainform', function( e ){
			if ( jQuery( '#woocommerce_tp_gateway_url_notification_required' ).is( ':checked' ) ) {
				if ( '' === jQuery( '#woocommerce_tp_gateway_url_notification_password' ).val() ) {
					e.preventDefault();
					alert( 'Please enter a URL Notification Password value.' );
					return false;
				}
			}
			return true;  
		});
		</script>
		<?php
    }

    /**
     * Check jQuery version.
     * ( show admin notice if version is less than 3.6.0 ).
     */
    public function check_jquery_version()
    {
        ?>
		<script>
		jQuery( document ).ready( function() {		
			// If jQuery is defined.
			if ( typeof jQuery !== 'undefined' ) {
				// Get jQuery version and set as number.
				// ( eg. replace 3.6.0 with 360 ).
				var ver = jQuery.fn.jquery.replace(/\./g,'');
				// If number is less than 360, show message.
				if ( Number( ver ) < 360 ) {
					// Set error message.
					var err = '<b>NOTICE:</b> Your version of jQuery is not supported. You are currently using version ' + jQuery.fn.jquery + '. Please upgrade to jQuery version 3.6.0';
					jQuery( '#jquery_version_error_message' ).html( err );
					// Show jQuery version error message.
					jQuery( '#jquery_version_error' ).show();
				}
			}
		});
		</script>
		<div id="jquery_version_error" class="notice notice-error is-dismissible" style="display:none;">
			<p id="jquery_version_error_message"></p>
		</div>	
		<?php
    }

    /**
     * Check all plugins.
     * ( show admin notice if plugin 'secure-trading-gateway-for-woocommerce' is found ).
     */
    public function check_all_plugins()
    {
        // Get plugins.
        if (!function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        // Get all plugins.
        $all_plugins = get_plugins();

        // If we have plugins.
        if (!empty($all_plugins)) {
            // Loop for each plugin.
            foreach ($all_plugins as $key => $val) {
                // Check if key for specific plugin exists.
                if ('secure-trading-gateway-for-woocommerce/secure-trading-gateway-for-woocommerce.php' === $key) {
                    // Show notice for this plugin.
                    ?>
					<div class="notice notice-error is-dismissible">
						<p><strong>Trust Payments Gateway (3DS2) Notice:</strong> It looks like you also have an old deprecated version of this plugin installed. Please note any previous versions should be removed entirely so as to not cause conflicts.</p>
					</div>
					<?php
                }
            }
        }
    }

    /**
     * Get transaction query.
     *
     * @param int $orderreference Eg.123.
     */
    public function get_transaction_query($orderreference = '')
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
					"requesttypedescriptions": ["TRANSACTIONQUERY"],
					"filter":{
						"sitereference": [{"value":"'.$sitereference.'"}],
						"orderreference":[{"value":"'.$orderreference.'"}]
					}
				}]
			}',
        ];
        $response = wp_remote_post('https://webservices.securetrading.net/json/', $args);
        $response_body = wp_remote_retrieve_body($response);
        $json_response = json_decode($response_body);

        // Return results.
        return (!empty($json_response->response[0]->records[0])) ? $json_response->response[0]->records[0] : [];
    }
}

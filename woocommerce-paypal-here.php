<?php

/**
 * Plugin Name:       WooCommerce PayPal Here
 * Plugin URI:
 * Description:       Allows merchants to use the checkout facility to do in-person payments via a mobile or tablet using the PayPal here app and a PayPal Here
 * Version:           1.0
 * Author:            @DeveloperSteve
 * Author URI:        http://PayPal.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Github URI:
	 */

if (!defined('WPINC')) {
	die;
}

//filters based on the user roles, if a user with the role Admin or PayPal Here is logged in then it will show the PayPal Here option otherwise its a no show.

add_filter( 'woocommerce_payment_gateways', 'payment_gateway_cleanup', 20, 1 );
function payment_gateway_cleanup( $load_gateways ) {

	if(current_user_can('PayPal_Here') or current_user_can('administrator')){
		foreach ( $load_gateways as $key => $value ) {
			if ( $value != 'WC_paypal_here_gateway' and !current_user_can('administrator') ) {
				unset( $load_gateways[ $key ] );
			}
		}
	}
	else{
		foreach ( $load_gateways as $key => $value ) {
			if ( $value == 'WC_paypal_here_gateway' ) {
				unset( $load_gateways[ $key ] );
			}
		}
	}

	return $load_gateways;
}

/**
 * Begins execution of the plugin.
 */
add_action('plugins_loaded', 'run_WC_PayPal_Here');

function run_WC_PayPal_Here(){

	add_role( 'PayPal_Here', 'PayPal Here', array(  ) );

	function add_WC_paypal_here_gateway($methods) {
		$methods[] = 'WC_paypal_here_gateway';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_WC_paypal_here_gateway');

	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	class WC_paypal_here_gateway extends WC_Payment_Gateway {

		public function __construct() {
			$this->id = 'pph';
			$this->has_fields = true;
			$this->method_title = 'PayPal Here';

			$this->icon = apply_filters('woocommerce_vzero_icon', plugins_url('/public/PPH_Logo.png', __FILE__));
			$this->method_description = 'Allows merchants to use the checkout facility to do in-person payments via a mobile or tablet using the PayPal here app and a PayPal Here. <br> <img src="'.plugins_url('/public/PPH.png', __FILE__).'"> <br> <br> For more info visit the <a href="https://www.paypal.com/webapps/mpp/emv" target="new">PayPal Here website</a><br><br>This plugin uses the PayPal Here Sideloader, For more information have a look <a href="https://github.com/paypal/here-sideloader-api-samples/blob/master/docs/README.md" target="new">here</a>';

			$this->init_form_fields();

			$this->init_settings();

			if ($this->get_option('chbutton')) {
				$this->order_button_text  = $this->get_option('chbutton');
			} else {
				$this->order_button_text  = 'Activate Card Reader';
			}

			$this->url = $this->get_option('url');
			$this->enabled = $this->get_option('enabled');

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}

		public function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'label' => __('Enable PayPal Here', 'woocommerce'),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'yes',
				),
				'chdesc' => array(
					'title' => __('Text for checkout payment message', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('Shows additional text to the user', 'woocommerce'),
					'default' => __('Pay via Credit Card using the PayPal here device and app. Android Pay/Apple Pay as well where available', 'woocommerce'),
					'desc_tip' => true,
				),
				'chbutton' => array(
					'title' => __('Text for checkout button', 'woocommerce'),
					'type' => 'text',
					'description' => __('Text to show on the checkout button, default is "Activate Card Reader"', 'woocommerce'),
					'default' => __('Activate Card Reader', 'woocommerce'),
					'desc_tip' => true,
				),
			);
		}

		public function process_payment( $order_id ) {

			global $woocommerce;

			$order = wc_get_order( $order_id );

			$items = $woocommerce->cart->get_cart();

			$iArr = array();

	        foreach($items as $item => $values) {

	        	$_product = $values['data']->post;
	        	$price = get_post_meta($values['product_id'] , '_price', true);

	        	$iArr[] = Array(
	                    "taxRate" => $values['line_subtotal_tax'],
	                    "name" => $_product->post_title,
	                    "description" => $_product->post_excerpt,
	                    "unitPrice" => number_format($price, 2),
	                    "taxName" => "Tax",
	                    "quantity" => $values['quantity']
	        		);
	        }

			$jCart = array(
				"paymentTerms" => "DueOnReceipt",
				"discountPercent" => "0",
				"currencyCode" => get_woocommerce_currency(),
				"number" => "1457",
				"itemList" => array(
					"item" => $iArr
				)
			);

			$jCart = urlencode(json_encode($jCart));

			$pphereUrl = "paypalhere://takePayment?returnUrl=";
			$pphereUrl .= $this->get_return_url( $order )."?InvoiceId={InvoiceId}";
			$pphereUrl .= "&accepted=card&step=choosePayment";
			$pphereUrl .= "&invoice=".$jCart;

			return array(
				'result'   => 'success',
				'redirect' => $pphereUrl,
			);
		}

		public function payment_fields() {

			global $woocommerce;

			echo $this->get_option('chdesc');

		}

	}
}
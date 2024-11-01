<?php
/*

 * Plugin Name: Payment Gateway for Billie.io on WooCommerce

 * Version: 1.0.0

 * Plugin URI: http://www.mlfactory.de

 * Description: Allow your customers to pay via Billie.io

 * Author: Michael Leithold

 * Author URI: https://profiles.wordpress.org/mlfactory/

 * Requires at least: 4.0

 * Tested up to: 5.5

 * License: GPLv2 or later

 * Text Domain: wc-billie-io-payment-gateway
 
 * Domain Path: /languages
 
 *

*/


add_action( 'plugins_loaded', 'billieio_load_textdomain');
add_filter( 'woocommerce_payment_gateways', 'billieio_load_gateway' );
add_action( 'plugins_loaded', 'billieio_init_gateway' );


function billieio_load_textdomain(){
    $loadfiles = load_plugin_textdomain('wc-billie-io-payment-gateway', false, 
    dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    plugin_basename(( __FILE__ ). '/languages/' );
}


function billieio_load_gateway( $gateways ) {
	$gateways[] = 'WC_Billieio_Gateway';
	return $gateways;
}


function billieio_init_gateway() {
	
	add_action( 'wp_ajax_nopriv_billieio_select_company', 'billieio_select_company', 1, 1);	
	add_action( 'wp_ajax_billieio_select_company', 'billieio_select_company', 1, 1);	
	function billieio_select_company() {
		if (isset($_POST['company'])) {
			$company = sanitize_text_field($_POST['company']);
			WC()->session->set( 'billieio_checkout_company' , $company );
		}
		wp_die();
	}
	
	class WC_Billieio_Gateway extends WC_Payment_Gateway {
 
		public function __construct() {
		 
			$this->id = 'billieio';
			$this->icon = plugin_dir_url( __FILE__ ).'/assets/billieio.jpg';
			$this->has_fields = true; 
			$this->method_title = __( 'Billie.io Gateway', 'wc-billie-io-payment-gateway' );
			$this->method_description = __( 'Allow your customers to pay via Billie.io.', 'wc-billie-io-payment-gateway' );
			$this->supports = array('products');
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option( 'title');
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->client_secret = $this->get_option( 'client_secret' );
			$this->client_id = $this->get_option( 'client_id' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
		 
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			add_action( 'wp_head', array( $this, 'billieiopg_css' ) );
			add_action( 'woocommerce_before_checkout_billing_form',array( $this, 'billieiopg_checkout_company_field' ), 1 );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'billieiopg_update_order_meta' ) );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'billieiopg_check_method' ), 99, 99);
			add_filter( 'woocommerce_default_address_fields' , array( $this, 'billieiopg_override_checkout_placeholder' ) );
			add_filter( 'woocommerce_checkout_fields' , array( $this, 'billieiopg_add_reorder_fields' ) );
			add_filter( 'woocommerce_order_formatted_billing_address' , array( $this, 'billieiopg_billing_address_fields' ), 10, 2 );
			add_filter( 'woocommerce_order_formatted_shipping_address' , array( $this, 'billieiopg_shipping_address_fields' ), 10, 2 );
			add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'billieiopg_replacement_fields' ),10,2 );
			add_filter( 'woocommerce_localisation_address_formats', array( $this, 'billieiopg_address_formats' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'billieiopg_thankyou' ), 1 );	
		}
	
		public function billieiopg_checkout_company_field( $checkout ) {
				
			function billieiopg_woocommerce_form_field_radio( $key, $args, $value = '' ) {
						global $woocommerce;
							$defaults = array(
											 'type' => 'radio',
											'label' => '',
											'placeholder' => '',
											'required' => false,
											'class' => array( ),
											'label_class' => array( ),
											'return' => false,
											'default' => 'private',
											'options' => array( )
							);
							$args     = wp_parse_args( $args, $defaults );
							if ( ( isset( $args[ 'clear' ] ) && $args[ 'clear' ] ) )
											$after = '<div class="clear"></div>';
							else
											$after = '';
							$required = ( $args[ 'required' ] ) ? ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>' : '';
							switch ( $args[ 'type' ] ) {
											case "select":
															$options = '';
															if ( !empty( $args[ 'options' ] ) )
																			foreach ( $args[ 'options' ] as $option_key => $option_text )
																		
																							$options .= '<input type="radio" name="' . $key . '" id="' . $key . '" value="' . $option_key . '" ' . selected( $value, $option_key, false ) . 'class="select">' . $option_text . '' . "\r\n";
															$field = '<p class="form-row ' . implode( ' ', $args[ 'class' ] ) . '" id="' . $key . '_field">
				<label for="' . $key . '" class="' . implode( ' ', $args[ 'label_class' ] ) . '">' . $args[ 'label' ] . $required . '</label>
				' . $options . '
				</p>' . $after;
																break;
								} 
								if ( $args[ 'return' ] )
												return $field;
								else
												echo $field;
				}
				
				echo '<div id="billio_select_company">';
				
				billieiopg_woocommerce_form_field_radio(	'clientstate', array(
												'type' => 'select',
												'class' => array('input-radio'),
												'label' => __( ' ' ),
												'placeholder' => __( ' ' ),
												'required' => true,
												'default' => 'private',
												'options' => array(	'private' => __( 'Private person', 'wc-billie-io-payment-gateway' ).'<br/>',
																	'company' => __( 'Company', 'wc-billie-io-payment-gateway' ).'<br/>')
												), $checkout->get_value( 'clientstate' ) );
				echo '</div>';
		}		
		 

		public function billieiopg_update_order_meta( $order_id ) {
			if ( $_POST[ 'clientstate' ] ) {			
				update_post_meta( $order_id, 'companyorprivate', sanitize_text_field( $_POST[ 'clientstate' ] ) );
			}
					
			if ( $_POST[ 'billing_houseno' ] ) {
				
				update_post_meta( $order_id, 'housenumber', sanitize_text_field( $_POST[ 'billing_houseno' ] ) );
				
				add_user_meta( get_current_user_id(), 'billig_housenr', sanitize_text_field( $_POST[ 'billing_houseno' ] ));	
				
				update_post_meta( $order_id, 'billig_houseno', sanitize_text_field( $_POST[ 'billing_houseno' ] ) );	
				
				update_post_meta( $order_id, '_billing_address_1', sanitize_text_field( $_POST[ 'billing_address_1' ] ).' '.sanitize_text_field( $_POST[ 'billing_houseno' ] ) );

				if (isset($_POST[ 'shipping_houseno' ]) && $_POST[ 'shipping_houseno' ] != "") {
					$shippinghousenr = sanitize_text_field( $_POST[ 'shipping_houseno' ] );
				} else {
					$shippinghousenr = sanitize_text_field( $_POST[ 'billing_houseno' ] );
				}
			
				update_post_meta( $order_id, '_shipping_address_1', sanitize_text_field( $_POST[ 'shipping_address_1' ] ).' '.$shippinghousenr );
			
			}		

			if ( $_POST[ 'shipping_houseno' ] ) {			
				
				add_user_meta( get_current_user_id(), 'shipping_houseno', sanitize_text_field( $_POST[ 'shipping_houseno' ] ));		
				
				update_post_meta( $order_id, 'shipping_houseno', sanitize_text_field( $_POST[ 'shipping_houseno' ] ) );							
			
			}							
		}			


		public function billieiopg_check_method( $gateways ){
			
			foreach( $gateways as $gateway_id => $gateway ) {
				
				if( WC()->session->get( 'billieio_checkout_company' ) != "company" && $gateway_id == 'billieio' ){
					
					unset( $gateways[$gateway_id] );
					
				}
			}
			return $gateways;
			
		}


		public function billieiopg_override_checkout_placeholder( $fields ) {
			
			 $fields['address_1']['placeholder'] = __( 'Street name', 'wc-billie-io-payment-gateway' );
			 
			 return $fields;
			 
		}

		   
		public function billieiopg_add_reorder_fields( $fields ) {
				
			$fields['billing']['billing_houseno'] = array(
			'label'     => __( 'House Number', 'wc-billie-io-payment-gateway' ),
			'placeholder'   => __( 'House Number', 'wc-billie-io-payment-gateway' ),
			'priority' => 51,
			'required'  => true,
			'clear'     => true
			 );
		   
			$fields['shipping']['shipping_houseno'] = array(
			'label'     => __( 'House Number', 'wc-billie-io-payment-gateway' ),
			'placeholder'   => __( 'House Number', 'wc-billie-io-payment-gateway' ),
			'priority' => 51,
			'required'  => true,
			'clear'     => true
			 );     
			 $fields['billing']['billing_houseno']['default'] = get_user_meta(get_current_user_id(), 'billing_houseno', true); 
			 $fields['shipping']['shipping_houseno']['default'] = get_user_meta(get_current_user_id(), 'shipping_houseno', true); 
			 
			return $fields;
		}
		  
		  
		public function billieiopg_billing_address_fields( $fields, $order ) {
			
			$fields['billing_houseno'] = get_post_meta( $order->get_id(), '_billing_houseno', true );
			
			return $fields;
			
		}
		  
		public function billieiopg_shipping_address_fields( $fields, $order ) {
			
			$fields['shipping_houseno'] = get_post_meta( $order->get_id(), '_shipping_houseno', true );
			
			return $fields;
			
		}
		  
		public function billieiopg_replacement_fields( $replacements, $address ) {
			
			$replacements['{billing_houseno}'] = isset($address['billing_houseno']) ? $address['billing_houseno'] : '';
			
			$replacements['{shipping_houseno}'] = isset($address['shipping_houseno']) ? $address['shipping_houseno'] : '';
			
			return $replacements;
		}

		  
		public function billieiopg_address_formats( $formats ) {
			
			return $formats;
			
		}
		
		 
		public function billieiopg_thankyou($order_id) {
			
			if (isset($order_id)) {
				
				$payment_status = get_post_meta( $order_id, 'billieio_payment_status', true);
				
				$payment_datetime = get_post_meta( $order_id, 'billieio_payment_created', true);
				
				if ($payment_status == "created") {
					
					$payment_status = __( 'successfully paid', 'wc-billie-io-payment-gateway' );
					
					$parts = explode('T', $payment_datetime);
					
					$payment_date = $parts[0];
					
					$payment_time = $parts[1];
					
					echo '<h2 class="h2thanks">'.__( 'Payment via Billie.io', 'wc-billie-io-payment-gateway' ).'</h2>
					<p class="billieio_thanks">
					<b>'.__( 'Status', 'wc-billie-io-payment-gateway' ).':</b> '.$payment_status.'<br />
					<b>'.__( 'Date/Time', 'wc-billie-io-payment-gateway' ).':</b> '.__( 'at', 'wc-billie-io-payment-gateway' ).'&nbsp;'.$payment_date.' - '.$payment_time.'<br />
					</p>';
					
				}
				
			}
			
		}


		public function init_form_fields(){
		 
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'wc-billie-io-payment-gateway' ),
					'label'       => __( 'Enable Billie.io Gateway', 'wc-billie-io-payment-gateway' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => __( 'Title', 'wc-billie-io-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wc-billie-io-payment-gateway' ),
					'default'     => __( 'Billie.io - Purchase on invoice', 'wc-billie-io-payment-gateway' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'wc-billie-io-payment-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'wc-billie-io-payment-gateway' ),
					'default'     => __( 'Pay comfortably after receiving the goods. Variable period 30-60-120 days.', 'wc-billie-io-payment-gateway' ),
				),
				'test_mode' => array(
					'title'       => __( 'Test Modus', 'wc-billie-io-payment-gateway' ),
					'label'       => __( 'Enable Test Mode', 'wc-billie-io-payment-gateway' ),
					'type'        => 'checkbox',
					'description' => __( 'Place the payment gateway in test mode using test API keys.', 'wc-billie-io-payment-gateway' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'client_id' => array(
					'title'       => __( 'Client ID', 'wc-billie-io-payment-gateway' ),
					'type'        => 'text'
				),
				'client_secret' => array(
					'title'       => __( 'Client Secret', 'wc-billie-io-payment-gateway' ),
					'type'        => 'password',
				)
			);
		}

 
		public function payment_fields() {
			
			if ( $this->description ) {
				
				if ( $this->get_option( 'test_mode' ) == "yes" ) {
					
					$this->description .= '<br /><span style="color:red;">'.__('TEST MODE ACTIVATED. No payments are really executed. If you want to execute payments, please deactivate the test mode.', 'wc-billie-io-payment-gateway').'</span>';
					
					$this->description  = trim( $this->description );
				
				}
				
				echo wpautop( wp_kses_post( $this->description ) );
				
			}
		 
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
			do_action( 'woocommerce_credit_card_form_start', $this->id );
		 
			echo '
				<div class="form-row form-row-first">
					<label>'.__('When do you intend to settle the amount?', 'wc-billie-io-payment-gateway').' <span class="required">*</span></label>
					<select id="billieioduration" name="billieioduration">
						<option value="30">'.__( 'in 30 Days', 'wc-billie-io-payment-gateway' ).'</option>
						<option value="60">'.__( 'in 60 Days', 'wc-billie-io-payment-gateway' ).'</option>
						<option value="120">'.__( 'in 120 Days', 'wc-billie-io-payment-gateway' ).'</option>
					</select>
				</div>
				<div class="clear"></div>';
		 
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		 
			echo '<div class="clear"></div></fieldset>';
		 
		}
		 
		 
		public function billieiopg_css() {		
		?>
			<style>
				.wc_payment_method.payment_method_billieio {}

				#billio_select_company input[type="radio"] {
					-moz-appearance: None;
					-webkit-appearance: none;
					width: 16px;
					height: 16px;
					background-image: url(<?php echo plugin_dir_url( __FILE__ ).'/assets/unchecked.png'; ?>);
					background-size: 16px 16px;
					background-position: Center Center;
					border: none;
					outline: none;
					vertical-align: Middle;
					box-shadow: none !important;
				}

				#billio_select_company input[type="radio"]:checked {
					background-image: url(<?php echo plugin_dir_url( __FILE__ ).'/assets/checked.png'; ?>);
				}
			</style>
		<?php
			echo '<script>' . PHP_EOL;
			echo "jQuery(document).ready(function($){";
			if (WC()->session->get( 'billieio_checkout_company' ) == "company") {
			echo "jQuery(\"input[name=clientstate][value='company']\").prop('checked', true);";
			}
			if (WC()->session->get( 'billieio_checkout_company' ) == "private") {
			echo "jQuery(\"input[name=clientstate][value='private']\").prop('checked', true);";
			}
			echo "
			jQuery('#billing_company_field').hide();
			jQuery('#shipping_company_field').hide();
			jQuery('input[type=radio][name=clientstate]').change(function() {
				var data = {action: 'billieio_select_company',company: this.value};
				jQuery.post( '".admin_url( 'admin-ajax.php' )."', data, function(d) {
					$( 'body' ).trigger( 'update_checkout' );	
				});				
				if (this.value == 'company') {		
					jQuery('#billing_company_field .optional').hide();
					jQuery('#shipping_company_field .optional').hide();
					jQuery('#billing_company_field').show();
					jQuery('#shipping_company_field').show();
				}
				else if (this.value == 'private') {
					jQuery('#billing_company_field').hide();
					jQuery('#shipping_company_field').hide();
				}
			});
			});
			";
			echo '</script>' . PHP_EOL;
		}

		public function payment_scripts() {
		 
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}
		 
			if ( 'no' === $this->enabled ) {
				return;
			}
		 
			if ( ! $this->testmode && ! is_ssl() ) {
				return;
			}
		 
		}
 

		public function validate_fields(){
			
			$errors = array();
			
			if( empty( $_POST[ 'clientstate' ]) ) {
				
				
				$errors[] = __('Please select whether you are a private person or a company.', 'wc-billie-io-payment-gateway');
			
			}				
			
			if( empty( $_POST[ 'billing_houseno' ]) ) {
				
				$errors[] = __('House Number is required!', 'wc-billie-io-payment-gateway');
			
			}	
			
			if (isset($errors)) {
				
				foreach ($errors as $error) {
					
					wc_add_notice(  $error, 'error' );
				
				}
				
				return false;
			
			}
			
			return true;
		 
		}
 

		public function process_payment( $order_id ) {

			$clientId = $this->settings['client_id'];
			
			$clientSecret = $this->settings['client_secret'];
			
			if (!isset($clientId) or empty($clientId)) {

				wc_add_notice( __('Unfortunately an error has occurred: There is no Client ID defined in the settings', 'wc-billie-io-payment-gateway'), 'error' );
				
				return;
				
			}
			
			if (!isset($clientSecret) or empty($clientSecret)) {
				
				wc_add_notice( __('Unfortunately an error has occurred: There is no Client Secret value defined in the settings!', 'wc-billie-io-payment-gateway'), 'error' );
				
				return;
				
			}
			
			error_log('[BILLIEIO:GATEWAY] Payment via Billie.io Payment Gateway started...');

			global $woocommerce;
		 
			$order = wc_get_order( $order_id );
			
			$order_items = $order->get_items();

			$args = array(
				'grant_type' => 'client_credentials', 
				'client_id' => $this->settings['client_id'],
				'client_secret' => $this->settings['client_secret']
			);
		 
			if ($this->settings['test_mode'] == "yes") {
				$url = "https://paella-sandbox.billie.io/api/v1/oauth/token";
			} else {
				$url = "https://paella.billie.io/api/v1/oauth/token";
			}
			 
			$response = wp_remote_post( $url , array(
				'headers'     => array('content-type' => 'application/json'),
				'body'        => json_encode($args),
				'method'      => 'POST',
				'data_format' => 'body',
			));
			
			if( !is_wp_error( $response ) ) {
		 
				$body = json_decode( $response['body'], true );
				
				if (isset($body['error'])) {
					
					wc_add_notice( __('Unfortunately an error has occurred in the communication with the payment server:', 'wc-billie-io-payment-gateway').$body['message'], 'error' );
					
					return;
				
				}
				
				if ( $body['token_type'] == 'Bearer' ) {
					
					$token_type = $body['token_type'];
					
					$expires_in = $body['expires_in'];
					
					$access_token = $body['access_token'];
					
					
					$args = array(
						'client_id' => $this->settings['client_id'],
						'scopes' => '['.$access_token.' ]'
					);	

					if ($this->settings['test_mode'] == "yes") {
						
						$url2 = "https://paella-sandbox.billie.io/api/v1/oauth/authorization";
						
					} else {
						
						$url2 = "https://paella.billie.io/api/v1/oauth/authorization";
						
					}					
					 
					$response2 = wp_remote_get( $url2 , array(
						'headers'     => array('content-type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
						'body'        => json_encode($args),
						'method'      => 'GET',
						'data_format' => 'body',
					));
					
					$body2 = json_decode( $response2['body'], true );
					
					$client_id = $body2['client_id'];
					
					if (isset($client_id)) {
						
						
						$args_create_order = array(
							'grant_type' => 'client_credentials', 
							'client_id' => $this->settings['client_id'],
							'client_secret' => $this->settings['client_secret']
						);
					 
						$net = $order->get_total()-$order->get_total_tax();
										
						if ($this->settings['test_mode'] == "yes") {
							
							$url = "https://paella-sandbox.billie.io/api/v1/order";
							
						} else {
							
							$url = "https://paella.billie.io/api/v1/order";
							
						}		
					
						$items = "";
						
						foreach ($order_items as $item) {
							
							$product_id = $item['product_id'];
							
							$name = $item['name'];
							
							$total = $item['total'];
							
							$quantity = $item['quantity'];
							
							$total_tax = $item['total_tax'];
							
							$gross = wc_format_decimal($total, 2)+wc_format_decimal($total_tax, 2);
							
							$items .= '
									{
									  "external_id": "'.$product_id.'",
									  "title": "'.$name.'",
									  "description": "string",
									  "quantity": '.$quantity.',
									  "category": "string",
									  "brand": "string",
									  "gtin": "string",
									  "mpn": "string",
									  "amount": {
										"net": '.wc_format_decimal($total, 2).',
										"gross": '.$gross.',
										"tax": '.wc_format_decimal($total_tax, 2).'
									  }
									},';
						}

						$items = rtrim($items, ',');
						
						if (isset($_POST['billieioduration'])) {
							
							$duration = sanitize_text_field($_POST['billieioduration']);
							
						} else {
							
							$duration = "30";
							
						}
						
						if ($duration != "30" && $duration != "60" && $duration != "120") {
							
							$duration = "30";
							
						}
						
						if ($order->get_user_id() != 0) {
							
							$established_customer = "true";
							
						} else {
							
							$established_customer = "false";
							
						}
						
						$houseno = sanitize_text_field($_POST['billing_houseno']);
						
						$response_create_order = wp_remote_post( $url , array(
							'headers'     => array('content-type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
							'body'        => '{
							  "amount": {
								"net": '.$net.',
								"gross": '.$order->get_total().',
								"tax": '.$order->get_total_tax().'
							  },
							  "comment": "string",
							  "duration": '.$duration.',
							  "order_id": "'.uniqid().'",
							  "delivery_address": {
								"addition": "string",
								"house_number": "'.$houseno.'",
								"street": "'.$order->get_shipping_address_1().'",
								"city": "'.$order->get_shipping_city().'",
								"postal_code": "'.$order->get_shipping_postcode().'",
								"country": "'.$order->get_shipping_country().'"
							  },
							  "billing_address": {
								"addition": "",
								"house_number": "'.$houseno.'",
								"street": "'.$order->get_billing_address_1().'",
								"city": "'.$order->get_billing_city().'",
								"postal_code": "'.$order->get_billing_postcode().'",
								"country": "'.$order->get_billing_country().'"
							  },
							  "debtor_company": {
								"merchant_customer_id": "'.$order->get_user_id().'",
								"name": "'.$order->get_billing_company().'",
								"tax_id": "string",
								"tax_number": "string",
								"registration_court": "string",
								"registration_number": "string",
								"industry_sector": "string",
								"subindustry_sector": "string",
								"employees_number": "1",
								"legal_form": "test",
								"established_customer": '.$established_customer.',
								"address_addition": "",
								"address_house_number": "'.$houseno.'",
								"address_street": "'.$order->get_billing_address_1().'",
								"address_city": "'.$order->get_billing_city().'",
								"address_postal_code": "'.$order->get_billing_postcode().'",
								"address_country": "'.$order->get_billing_country().'"
							  },
							  "debtor_person": {
								"salutation": "m",
								"first_name": "'.$order->get_billing_first_name().'",
								"last_name": "'.$order->get_billing_last_name().'",
								"phone_number": "'.$order->get_billing_phone().'",
								"email": "'.$order->get_billing_email().'"
							  },
							  "line_items": [
								'.$items.'
							  ]
							}',
							'method'      => 'POST',
							'data_format' => 'body',
						));						
						
					}
					
					$body_create_order = json_decode( $response_create_order['body'], true );

					if (!isset($body_create_order['errors'])) {
					
						if (isset($body_create_order['uuid']) && isset($body_create_order['state'])) {

							$uuid = $body_create_order['uuid'];
							
							$state = $body_create_order['state'];
							
							$created_at_raw = $body_create_order['created_at'];
							
							$parts = explode('T', $created_at_raw);
							
							$payment_date = $parts[0];
							
							$payment_time = $parts[1];
							
							$created_at = __('at ', 'wc-billie-io-payment-gateway').$payment_date." - ".$payment_time;
		
							if ($state == "declined") {
								
								$decline_reason = $body_create_order['decline_reason'];
								
								wc_add_notice( __('The payment via Billie.io was <b>not</b> successful.<br />You cannot use this payment method for this purchase.', 'wc-billie-io-payment-gateway')."<br /> (".$state."-".$created_at.")", 'error' );
								
								$order->add_order_note( __('Payment via Billie.io <b>NOT</b> successful.', 'wc-billie-io-payment-gateway')."<br/><b>".__('Status', 'wc-billie-io-payment-gateway').":</b> $state <br /><b>".__('At', 'wc-billie-io-payment-gateway').":</b> $created_at_raw <br /><b>".__('Reason', 'wc-billie-io-payment-gateway').":</b> ".$decline_reason, false );
								
								$order->update_meta_data( 'billieio_payment_status', 'declined' );
								
								$order->update_meta_data( 'billieio_payment_created', $created_at_raw );
								
								$order->save();
		
								return;
								
							} else if ($state == "created") {
								
								$duration = $body_create_order['duration'];
								
								$bank_account = $body_create_order['bank_account'];
								
								$iban = $bank_account['iban'];
								
								$bic = $bank_account['bic'];
																
								$admin_notice = __('Payment via Billie.io <b>successful</b>', 'wc-billie-io-payment-gateway')."<br/>".__('Status', 'wc-billie-io-payment-gateway').": $state <br /> ".__('Date/Time', 'wc-billie-io-payment-gateway').": $created_at <br />";
								
								$admin_notice .= __('IBAN', 'wc-billie-io-payment-gateway').": ".$iban."<br/>".__('BIC', 'wc-billie-io-payment-gateway').": ".$bic."<br/>";
								
								$admin_notice .= __('Duration', 'wc-billie-io-payment-gateway').": ".$duration."<br/>";
								
								$order->add_order_note($admin_notice , false );
								
								$order->update_meta_data( 'billieio_payment_status', 'created' );
								
								$order->update_meta_data( 'billieio_payment_created', $created_at_raw );
								
								$order->save();
								
								$order->update_status('processing');
								
								/**Billie.io ship order request***/
								if ($this->settings['test_mode'] == "yes") {
									
									$url = "https://paella-sandbox.billie.io/api/v1/order/".$uuid."/ship";
									
								} else {
									
									$url = "https://paella-sandbox.billie.io/api/v1/order/".$uuid."/ship";
									
								}									
								$response_ship_order = wp_remote_post( $url , array(
									'headers'     => array('content-type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
									'body'        => '{
													"external_order_id": "'.$order->get_order_number().'",
													"invoice_number": "string",
													"invoice_url": "https://example.com",
													"shipping_document_url": "https://example.com"
													}',
									'method'      => 'POST',
									'data_format' => 'body',
								));						
								
							
								$body_ship_order = json_decode( $response_ship_order['body'], true );
								
								if (!isset($body_ship_order['errors'])) {	
								
									$admin_notice_2 = __('Ship Order Request via Billie.io <b>OK</b>', 'wc-billie-io-payment-gateway');								
								
								} else {
									
									$errors = "";
									
									foreach ($body_ship_order['errors'] as $error) {
										
										$errors .= $error['title'];
									
									}
									
									$admin_notice_2 = __('Ship Order Request via Billie.io <b>NOT OK</b><br/>Error(s):'.$errors, 'wc-billie-io-payment-gateway');								
								
								}
								
								$order->add_order_note($admin_notice_2 , false );

								$order->reduce_order_stock();
								
								$woocommerce->cart->empty_cart();
								
								return array(
									'result' => 'success',
									'redirect' => $this->get_return_url( $order )
								);
								
								return;								
							}
							
						}
					
					} else {
						
						$errors = $body_create_order['errors'];
						
						foreach ($errors as $error) {
							
							$order_data = $order->get_data();
							
							$state = get_post_meta($order_data['id'], 'billieio_payment_status');
							
							if (isset($state[0]) && $state[0] == 'declined') {
								
								wc_add_notice(  __('Unfortunately the payment method Billie.io cannot be used for this purchase.<br />Please select another payment method.', 'wc-billie-io-payment-gateway')."<br />(".$state[0]."-".get_post_meta($order_data['id'], 'billieio_payment_created')[0].")<br /> ", 'error' );
							
							} else {
								
								if (isset($error['source'])) {
									
									$source = $error['source'];
									
									$source_part = explode('.', $source);
									
								if (isset($source_part[1])) {
									
									$source = $source_part[1];
									
								}
								
								} else {
									
									$source = "";
									
								}
								
								wc_add_notice(  __('Error:</b> Unfortunately an error has occurred', 'wc-billie-io-payment-gateway')."<b> (".$error['title']." - ".$source.").", 'error' );
							
							}
						
						}
						
						return;						
						
					}
					
				}
				
				die();				
		 
			} else {
				
				wc_add_notice(  'Connection error [Billie.io WooCommerce Gateway].', 'error' );
				
				return;
				
			}
		 
		}
 

		public function webhook() {
 
			$order = wc_get_order( $_GET['id'] );
			$order->payment_complete();
			$order->reduce_order_stock();
		 
			update_option('webhook_debug', $_GET);
 
	 	}
 	}
}

?>
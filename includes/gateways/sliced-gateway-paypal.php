<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_paypal_class() {
	new Sliced_Paypal();
}
add_action( 'sliced_loaded', 'sliced_call_paypal_class', 1 );


/**
 * Invoice Tags
 */
function sliced_get_gateway_paypal_label() {
	$translate = get_option( 'sliced_translate' );
	$label = isset( $translate['gateway-paypal-label'] ) ? $translate['gateway-paypal-label'] : __( 'Pay with PayPal', 'sliced-invoices');
	return apply_filters( 'sliced_get_gateway_paypal_label', $label );
}


/**
 * The Class.
 */
class Sliced_Paypal {


	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {
		
		add_filter( 'sliced_payment_option_fields', array( $this, 'add_options_fields') );
		add_filter( 'sliced_register_payment_method', array( $this, 'add_payment_method') );
		add_filter( 'sliced_business_details', array( $this, 'get_field_values') );
		add_action( 'sliced_do_payment', array( $this, 'process_payment') );
		add_action( 'sliced_do_payment', array( $this, 'payment_return'), 10 );

	}


	/**
	 * Add the options for this gateway to the admin payment settings.
	 *
	 * @since   2.0.0
	 */
	public function add_options_fields( $options ) {

		// add fields to end of options array
		$options['fields'][] = array(
			'name'    => __( 'PayPal Currency', 'sliced-invoices' ),
			'desc'    => __( '3 letter code - <a href="https://developer.paypal.com/docs/classic/api/currency_codes/" target="_blank">Full list of accepted currencies here</a>', 'sliced-invoices' ),
			'default' => 'USD',
			'type'    => 'text',
			'id'      => 'paypal_currency',
		);

		$options['fields'][] = array(
			'name' => __( 'PayPal API Username', 'sliced-invoices' ),
			'desc' => __( 'You will find your API Username under "Profile" and then "Request API credentials".' , 'sliced-invoices' ),
			'type' => 'text',
			'id'   => 'paypal_username',
		);
		$options['fields'][] = array(
			'name' => __( 'PayPal API Password', 'sliced-invoices' ),
			'desc' => __( 'You will find your API Password under "Profile" and then "Request API credentials".' , 'sliced-invoices' ),
			'type' => 'text',
			'id'   => 'paypal_password',
		);
		$options['fields'][] = array(
			'name' => __( 'PayPal Signature', 'sliced-invoices' ),
			'desc' => __( 'You will find your Signature under "Profile" and then "Request API credentials".' , 'sliced-invoices' ),
			'type' => 'text',
			'id'   => 'paypal_signature',
		);
		$options['fields'][] = array(
			'name'    => __( 'PayPal Mode', 'sliced-invoices' ),
			'desc'    => __( 'Set to Sandbox for testing purposes (you must have a <a href="https://developer.paypal.com/docs/classic/lifecycle/ug_sandbox/">Sandbox account</a> for this and be using the Sandbox API credentials).<br />Set to Live to accept payments from clients.', 'sliced-invoices' ),
			'default' => 'Live',
			'type'    => 'select',
			'id'      => 'paypal_mode',
			'options' => array(
				'sandbox' => 'Sandbox',
				'live' => 'Live',
			)
		);

		return $options;

	}
	
	
	/**
	 * Add this gateway to the list of registered payment methods.
	 *
	 * @since   2.0.0
	 */
	public function add_payment_method( $pay_array ) {

		$payments = get_option( 'sliced_payments' );

		if ( ! empty( $payments['paypal_signature'] ) && ! empty( $payments['paypal_username'] ) && ! empty( $payments['paypal_password'] ) ) {
			$pay_array['paypal'] = 'PayPal';
		}

		return $pay_array;
	}
	
	
	/**
	 * Cancel subscription invoice payments
	 *
	 * @since   3.3.0
	 */
	public function cancel_subscription( $id, $gateway_subscr_id ) {
		
		$gateway = $this->gateway();
		
		$payment_data = '&PROFILEID=' . urlencode( $gateway_subscr_id ) .
			'&ACTION=Cancel';
		
		$response = $this->paypal_request(
			'ManageRecurringPaymentsProfileStatus',
			$payment_data,
			$gateway['username'],
			$gateway['password'],
			$gateway['signature'],
			$gateway['mode'] );
		
		if( 'SUCCESS' == strtoupper( $response["ACK"] ) || 'SUCCESSWITHWARNING' == strtoupper( $response["ACK"] ) ) {
			return array(
				'status'  => 'success',
				'message' => urldecode( $response['L_LONGMESSAGE0'] ),
			);
		} else {
			return array(
				'status'  => 'error',
				'message' => 'Gateway says: ' . urldecode( $response['L_LONGMESSAGE0'] ),
			);
		}
		
	}


	/**
	 * Get the info for our gateway.
	 *
	 * @since   2.0.0
	 */
	private function gateway() {

		$payments = get_option( 'sliced_payments' );
		$gateway                 = array();
		$gateway['mode']         = $payments['paypal_mode']; // sandbox or live
		$gateway['currency']     = $payments['paypal_currency'];
		$gateway['username']     = $payments['paypal_username']; //PayPal API Username
		$gateway['password']     = $payments['paypal_password']; //Paypal API password
		$gateway['signature']    = $payments['paypal_signature']; //Paypal API Signature
		$gateway['payment_page'] = esc_url( get_permalink( (int)$payments['payment_page'] ) ); //Point to process.php page
		$gateway['cancel_page']  = esc_url( get_permalink( (int)$payments['payment_page'] ) ); //Cancel URL if user clicks cancel

		return $gateway;
	}
	
	
	/**
	 * Make the field values available.
	 *
	 * @since   2.0.0
	 */
	public function get_field_values( $fields ) {

		$payments = get_option( 'sliced_payments' );

		$fields['paypal_username']  = isset( $payments['paypal_username'] ) ? $payments['paypal_username'] : '';
		$fields['paypal_password']  = isset( $payments['paypal_password'] ) ? $payments['paypal_password'] : '';
		$fields['paypal_signature'] = isset( $payments['paypal_signature'] ) ? $payments['paypal_signature'] : '';
		$fields['paypal_mode']      = isset( $payments['paypal_mode'] ) ? $payments['paypal_mode'] : '';

		return $fields;

	}
	
	
	/**
	 * Helper to convert billing period value into the specific value needed by this gateway
	 *
	 * @since   3.3.0
	 */
	public function get_billing_period( $input ) {
		switch ( $input ) {
			case 'days':
				$output = 'Day';
				break;
			case 'months':
				$output = 'Month';
				break;
			case 'years':
				$output = 'Year';
				break;
			default:
				$output = 'Month';
		}
		return $output;
	}


	/**
	 * Further processing after returning from Paypal.
	 *
	 * @since   2.0.0
	 */
	public function payment_return( $data ) {
	
		// is this a paypal IPN?
		if ( isset( $_POST['ipn_track_id'] ) ) {
			$this->process_ipn();
			return;
		}

		// check for token back from paypal
		if ( ! isset( $_GET['token'] ) ) {
			return;
		}
		
		$token      = $_GET['token'];
		$payer_id   = isset( $_GET['PayerID'] ) ? $_GET['PayerID'] : false;

		/*
		 * get the invoice that matches the token we are receiving
		 */
		$args = array(
			'post_type'     =>  'sliced_invoice',
			'meta_query'    =>  array(
				array(
					'value' =>  esc_html( $token )
				)
			)
		);
		$query      = get_posts( $args );
		$id         = $query[0]->ID;
		
		// if we can't get the id, stop
		if ( ! $id ) {
			sliced_print_message( $id, __( 'Error processing the payment.<br> <a href="' . esc_url( sliced_get_the_link( $id ) ) . '">Go back</a> and try again?', 'sliced-invoices' ), 'failed' );
			return;
		}
		
		// otherwise, we go on...
		$gateway  = $this->gateway();
		$currency = sliced_get_invoice_currency( $id );
		$currency = $currency !== 'default' ? $currency : $gateway['currency'];
		
		/*
		 * Subscriptions only -- do GetExpressCheckoutDetails 
		 */
		$subscription_status = get_post_meta( $id, '_sliced_subscription_status', true );
		if ( $subscription_status === 'pending' ) {
			// if it's for a subscription invoice we have to get PayerID in a separate call, now:
			$payment_data = '&TOKEN=' . urlencode( $token );
			$response = $this->paypal_request(
				'GetExpressCheckoutDetails',
				$payment_data,
				$gateway['username'],
				$gateway['password'],
				$gateway['signature'],
				$gateway['mode']
			);
			if( "SUCCESS" === strtoupper( $response["ACK"] ) || "SUCCESSWITHWARNING" == strtoupper( $response["ACK"] ) ) {
				$payer_id = isset( $response['PAYERID'] ) ? $response['PAYERID'] : false;
			}
		}
		
		/*
		 * Last of our checks here:
		 */
		 
		// if cancelled at paypal, print message and die.
		if ( ! $payer_id ) {
			sliced_print_message( $id, __( 'Payment has been cancelled.<br> <a href="' . esc_url( sliced_get_the_link( $id ) ) . '">Go back</a> and try again?', 'sliced-invoices' ), 'failed' );
			return;
		}

		// if already been paid, print message and die.
		if( has_term( 'paid', 'invoice_status', $id ) ) {
			sliced_print_message( $id, __( 'This invoice has already been paid.', 'sliced-invoices' ), 'alert' );
			return;
		}
		
		/*
		 * Finalize payment
		 */
		$payment_data = '&TOKEN=' . urlencode( $token ) .
			'&PAYERID=' . urlencode( $payer_id ) .
			'&BUTTONSOURCE=' . 'SlicedInvoices_SP';
		$payment_data .= get_transient( 'sliced_paypal_'.$id );
		 
		if ( $subscription_status === 'pending' ) {
		
			/*
			 * subscription invoices
			 */
		
			$billing_period = $this->get_billing_period( get_post_meta( $id, '_sliced_subscription_interval_type', true ) );
			$billing_frequency = get_post_meta( $id, '_sliced_subscription_interval_number', true );
			$total_billing_cycles = ( get_post_meta( $id, '_sliced_subscription_cycles_type', true ) === 'fixed' ? get_post_meta( $id, '_sliced_subscription_cycles_count', true ) : '0' );
		
			$payment_data .= '&PROFILESTARTDATE=' . date( "Y-m-d" ) . 'T00:00:00Z' .
				'&DESC=' . urlencode( sliced_get_invoice_label( $id ) . ' ' . sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) ) .
				'&BILLINGPERIOD=' . $billing_period .
				'&BILLINGFREQUENCY=' . $billing_frequency .
				'&TOTALBILLINGCYCLES=' . $total_billing_cycles .
				'&CURRENCYCODE=' . urlencode( $currency );
				
			if ( get_post_meta( $id, '_sliced_subscription_trial', true ) == '1' ) {
				$trial_billing_period = $this->get_billing_period( get_post_meta( $id, '_sliced_subscription_trial_interval_type', true ) );
				$trial_billing_frequency = get_post_meta( $id, '_sliced_subscription_trial_interval_number', true );
				$trial_total_billing_cycles = get_post_meta( $id, '_sliced_subscription_trial_cycles_count', true );
				$trial_amount = get_post_meta( $id, '_sliced_subscription_trial_amount', true );
				$payment_data .= '&TRIALBILLINGPERIOD=' . $trial_billing_period .
					'&TRIALBILLINGFREQUENCY=' . $trial_billing_frequency .
					'&TRIALTOTALBILLINGCYCLES=' . $trial_total_billing_cycles .
					'&TRIALAMT=' . urlencode( $trial_amount );
			}
			
			$response = $this->paypal_request(
				'CreateRecurringPaymentsProfile',
				$payment_data,
				$gateway['username'],
				$gateway['password'],
				$gateway['signature'],
				$gateway['mode']
			);
			
			if( 'SUCCESS' == strtoupper( $response["ACK"] ) || 'SUCCESSWITHWARNING' == strtoupper( $response["ACK"] ) ) {
				// activate subscription
				if ( class_exists( 'Sliced_Subscriptions' ) ) {
					Sliced_Subscriptions::activate_subscription_invoice( 
						$id, 
						'Paypal', // must match class name
						urldecode( $response['PROFILEID'] ),
						$response
					);
				}
			}
		
		} else {
		
			/*
			 * regular invoices
			 */
			
			$response = $this->paypal_request(
				'DoExpressCheckoutPayment',
				$payment_data,
				$gateway['username'],
				$gateway['password'],
				$gateway['signature'],
				$gateway['mode'] );
			
		}

		/*
		 * Display result message and save data
		 */
			
		//Respond according to message we receive from Paypal
		if( 'SUCCESS' == strtoupper( $response["ACK"] ) || 'SUCCESSWITHWARNING' == strtoupper( $response["ACK"] ) ) {

			$message = '<h2>' . __( 'Success', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';

			/*
			 * Sometimes payments are kept pending even when transaction is complete.
			 * hence we need to notify user about it and ask them to manually approve the transaction
			*/
			if ( isset( $response['PROFILESTATUS'] ) ) {
				$amount     = urldecode( $response["PAYMENTINFO_0_AMT"] );
				$payment_id = urldecode( $response['PROFILEID'] );
				$status     = 'success';
				$message .= __( 'Subscription has been activated!', 'sliced-invoices' );
				$message .= '<br />';
				$message .= __( 'Your PayPal Transaction ID is: ', 'sliced-invoices' ) . $payment_id .'</p>';
				$message .= '</p>';
			} elseif ( 'Completed' == $response["PAYMENTINFO_0_PAYMENTSTATUS"] ) {
				$amount     = urldecode( $response["PAYMENTINFO_0_AMT"] );
				$payment_id = urldecode( $response['PAYMENTINFO_0_TRANSACTIONID'] );
				$status     = 'success';
				$message .= __( 'Payment has been received!', 'sliced-invoices' );
				$message .= '<br />';
				$message .= __( 'Your PayPal Transaction ID is: ', 'sliced-invoices' ) . $payment_id .'</p>';
				$message .= '</p>';
			} elseif ( 'Pending' == $response["PAYMENTINFO_0_PAYMENTSTATUS"] ) {
				$amount     = urldecode( $response["PAYMENTINFO_0_AMT"] );
				$payment_id = urldecode( $response['PAYMENTINFO_0_TRANSACTIONID'] );
				$status     = 'pending';
				$message .= __( 'Transaction complete, however payment is still pending.', 'sliced-invoices' );
				$message .= '<br />';
				$message .= __( 'You need to manually authorize this payment in your <a target="_new" href="http://www.paypal.com">PayPal Account</a>', 'sliced-invoices' );
				$message .= '<br />';
				$message .= __( 'Your PayPal Transaction ID is: ', 'sliced-invoices' ) . $payment_id .'</p>';
				$message .= '</p>';
			}

			$message .= '<p>';
			$message .= '<a href="' . apply_filters( 'sliced_get_the_link', get_permalink($id), $id ) . '">';
			$message .= sprintf( __( 'Click here to return to %s', 'sliced-invoices' ), sliced_get_invoice_label() );
			$message .= '</a>';
			$message .= '</p>';

			/**
			 * Update payment status
			 */
			$payments = get_post_meta( $id, '_sliced_payment', true );
			if ( ! is_array( $payments ) ) {
				$payments = array();
			}
			$payments[] = array(
				'gateway'    => 'paypal',
				'date'       => date("Y-m-d H:i:s"),
				'amount'     => $amount,
				'currency'   => $currency,
				'payment_id' => $payment_id,
				'status'     => $status,
				'extra_data' => json_encode( array( 
					'response'  => $response,
					'clientip'  => Sliced_Shared::get_ip(),
				) ),
			);
			update_post_meta( $id, '_sliced_payment', $payments );

			/**
			 * Print the message
			 */
			sliced_print_message( $id, $message, 'success' );

			/**
			 * send notifications, update status etc
			 */
			do_action( 'sliced_payment_made', $id, 'PayPal', 'success' );

		} else {

			/**
			 * Print the message
			 */
			sliced_print_message( $id, urldecode( $response["L_LONGMESSAGE0"] ), 'failed' );

			/**
			 * send notifications, update status etc
			 */
			do_action( 'sliced_payment_made', $id, 'PayPal', 'failed' );

		}

	}


	/**
	 * Make the requests to Paypal.
	 *
	 * @since   2.0.0
	 */
	private function paypal_request($methodName_, $nvpStr_, $PayPalApiUsername, $PayPalApiPassword, $PayPalApiSignature, $PayPalMode) {

		// Set up your API credentials, PayPal end point, and API version.
		$API_UserName  = urlencode($PayPalApiUsername);
		$API_Password  = urlencode($PayPalApiPassword);
		$API_Signature = urlencode($PayPalApiSignature);

		$paypalmode = ( $PayPalMode == 'sandbox' ) ? '.sandbox' : '';

		$API_Endpoint = "https://api-3t" . $paypalmode . ".paypal.com/nvp";
		$version = urlencode('109.0');

		// Set the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// Set the API operation, version, and API signature in the request.
		$nvpreq = "METHOD=$methodName_&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";

		// Set the request as a POST FIELD for curl.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		
		do_action( 'sliced_pre_curl_exec', $ch );

		// Get response from the server.
		$httpResponse = curl_exec($ch);

		if( ! $httpResponse ) {
			sliced_print_message( $id, "$methodName_ failed: ".curl_error($ch).'('.curl_errno($ch).')', 'failed' );
		}

		// Extract the response details.
		$httpResponseAr = explode("&", $httpResponse);

		$response = array();
		foreach ($httpResponseAr as $i => $value) {
			$tmpAr = explode("=", $value);
			if(sizeof($tmpAr) > 1) {
				$response[$tmpAr[0]] = $tmpAr[1];
			}
		}

		if((0 == sizeof($response)) || ! array_key_exists('ACK', $response)) {
			sliced_print_message( $id, "Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.", 'failed' );
		}

		return $response;
	}
	
	
	/**
	 * Process IPNs (for subscription invoice payments)
	 *
	 * @since   3.3.0
	 */
	public function process_ipn() {
	
		$gateway = $this->gateway();

		// STEP 1: read POST data
		// Reading POSTed data directly from $_POST causes serialization issues with array data in the POST.
		// Instead, read raw POST data from the input stream.
		$raw_post_data = file_get_contents('php://input');
		$raw_post_array = explode('&', $raw_post_data);
		$myPost = array();
		foreach ($raw_post_array as $keyval) {
		  $keyval = explode ('=', $keyval);
		  if (count($keyval) == 2)
			$myPost[$keyval[0]] = urldecode($keyval[1]);
		}
		// read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
		$req = 'cmd=_notify-validate';
		if (function_exists('get_magic_quotes_gpc')) {
		  $get_magic_quotes_exists = true;
		}
		foreach ($myPost as $key => $value) {
		  if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
			$value = urlencode(stripslashes($value));
		  } else {
			$value = urlencode($value);
		  }
		  $req .= "&$key=$value";
		}

		// Step 2: POST IPN data back to PayPal to validate
		$ch = curl_init('https://www'.( $gateway['mode'] == 'sandbox' ? '.sandbox' : '').'.paypal.com/cgi-bin/webscr');
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
		// In wamp-like environments that do not come bundled with root authority certificates,
		// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set
		// the directory path of the certificate as shown below:
		// curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
		do_action( 'sliced_gateway_paypal_ipn_verify', $ch );
		do_action( 'sliced_pre_curl_exec', $ch );
		if ( !($res = curl_exec($ch)) ) {
		  // error_log("Got " . curl_error($ch) . " when processing IPN data");
		  curl_close($ch);
		  exit;
		}
		curl_close($ch);
		
		// inspect IPN validation result and act accordingly
		if (strcmp ($res, "VERIFIED") == 0) {
		
			// The IPN is verified, process it
			switch ( $_POST['txn_type'] ) {
				
				case 'recurring_payment_profile_cancel':
					
					$args = array(
						'post_type'     =>  'sliced_invoice',
						'meta_key'      =>  '_sliced_subscription_gateway_subscr_id',
						'meta_value'    =>  $_POST['recurring_payment_id'],
					);
					$query      = get_posts( $args );
					$id         = $query[0]->ID;
					
					if ( class_exists( 'Sliced_Subscriptions' ) ) {
						Sliced_Subscriptions::cancel_subscription_invoice( $id, $_POST );
					}
					
					break;
					
				case 'recurring_payment':
				
					$args = array(
						'post_type'     =>  'sliced_invoice',
						'meta_key'      =>  '_sliced_subscription_gateway_subscr_id',
						'meta_value'    =>  $_POST['recurring_payment_id'],
					);
					$query      = get_posts( $args );
					$id         = $query[0]->ID;
					
					if ( class_exists( 'Sliced_Subscriptions' ) ) {
						Sliced_Subscriptions::create_receipt_invoice( $id, $_POST );
					}
					
					break;
				
			}
			
			
		} else {
			// IPN invalid, no further action
			
		}
	
	}


	/**
	 * Start processing the payment.
	 *
	 * @since   2.0.0
	 */
	public function process_payment() {

		// if we have POSTED from the invoice payment popup
		if ( ! isset( $_POST['start-payment'] ) ) {
			return;
		}

		// if not paypal return and look for another payment gateway
		if ( $_POST['sliced_gateway'] != 'paypal' ) {
			return;
		}

		// check the nonce
		if( ! isset( $_POST['sliced_payment_nonce'] ) || ! wp_verify_nonce( $_POST['sliced_payment_nonce'], 'sliced_invoices_payment' ) ) {
			sliced_print_message( $id, __( 'There was an error with the form submission, please try again.', 'sliced-invoices' ), 'failed' );
			return;
		}

		/*
		 * Get the invoice ID and gateway
		 */
		$id         = $_POST['sliced_payment_invoice_id'];
		$gateway    = $this->gateway();
		$currency   = sliced_get_invoice_currency( $id );
		$payment_data = '&METHOD=SetExpressCheckout'.
			'&RETURNURL=' . urlencode( $gateway['payment_page'] ) .
			'&CANCELURL=' . urlencode( $gateway['cancel_page'] );
		
		/*
		 * Build payment parameters
		 */
		
		// is it a subscription invoice?
		$subscription_status = get_post_meta( $id, '_sliced_subscription_status', true );
		
		if ( $subscription_status === 'pending' ) {
			
			$payment_data .= '&L_BILLINGTYPE0=RecurringPayments' .
				'&L_BILLINGAGREEMENTDESCRIPTION0='.urlencode( sliced_get_invoice_label( $id ) . ' ' . sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) );
			
			$payment_data_transient = '&AMT=' . urlencode( sliced_get_invoice_total_raw( $id ) );
			set_transient( 'sliced_paypal_'.$id, $payment_data_transient , 60*60*24 );
				
		} else {
		
			// regular invoice:
			$payment_data_transient = '&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode( $currency && $currency !== 'default' ? $currency : $gateway['currency'] ) .
				'&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode("SALE") .
				'&L_PAYMENTREQUEST_0_NAME0=' . urlencode( sliced_get_invoice_label() ) .
				'&L_PAYMENTREQUEST_0_NUMBER0=' . urlencode( sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) ) .
				'&L_PAYMENTREQUEST_0_AMT0=' . urlencode( sliced_get_invoice_total_raw( $id ) ) .
				'&L_PAYMENTREQUEST_0_QTY0='. urlencode( 1 ) .
				'&NOSHIPPING=1'. //set 1 to hide buyer's shipping address, in-case products that does not require shipping
				'&PAYMENTREQUEST_0_ITEMAMT=' . urlencode( sliced_get_invoice_total_raw( $id ) ) .
				//'&PAYMENTREQUEST_0_TAXAMT=' . urlencode( sliced_get_invoice_tax_raw( $id ) ) .
				'&PAYMENTREQUEST_0_AMT=' . urlencode( sliced_get_invoice_total_raw( $id ) ) .
				'&SOLUTIONTYPE=Sole'. // to make PayPal account not required
				'&LANDINGPAGE=Billing'. // this too
				'&LOCALECODE='. urlencode( get_option( 'WPLANG' ) ? get_option( 'WPLANG' ) : 'en_US' ) . //PayPal pages to match the language on your website.
				'&LOGOIMG='. urlencode( sliced_get_business_logo() ) .//site logo
				'&CARTBORDERCOLOR=FFFFFF' . //border color of cart
				'&PAYMENTREQUEST_0_CUSTOM=' .  urlencode( $id ) . //id
				'&ALLOWNOTE=1';
			set_transient( 'sliced_paypal_'.$id, $payment_data_transient , 60*60*24 );
			$payment_data .= $payment_data_transient;
		
		}

		/*
		 * Send request to Paypal
		 */
		$response = $this->paypal_request(
			'SetExpressCheckout',
			$payment_data,
			$gateway['username'],
			$gateway['password'],
			$gateway['signature'],
			$gateway['mode']
		);

		/*
		 * Respond according to message we receive from Paypal
		 */
		if( 'SUCCESS' == strtoupper( $response["ACK"] ) || 'SUCCESSWITHWARNING' == strtoupper( $response["ACK"] ) ) {

			/*
			 * Save the token
			 */
			update_post_meta( $id, '_sliced_paypal_token', rawurldecode( $response["TOKEN"] ) );

			/*
			 * Redirect user to PayPal store with Token received.
			 */
			$mode = ( $gateway['mode'] == 'sandbox' ) ? '.sandbox' : '';
			$url  = 'https://www' . $mode . '.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $response["TOKEN"] . '';

			wp_redirect( $url );

		} else {

			$message = 'Error Code: ' . urldecode( $response["L_ERRORCODE0"] ) . '<br />';
			$message .= urldecode( $response["L_LONGMESSAGE0"] );
			sliced_print_message( $id, urldecode( $message ), 'failed' );

		}


	}


}

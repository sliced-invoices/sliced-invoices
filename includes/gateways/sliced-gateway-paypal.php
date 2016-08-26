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
		//add_action( 'sliced_invoice_after_body', array( $this, 'display_payment_options' ) );
		add_filter( 'sliced_payment_option_fields', array( $this, 'add_options_fields') );
		add_filter( 'sliced_register_payment_method', array( $this, 'add_payment_method') );
		
		add_filter( 'sliced_translate_option_fields', array( $this, 'add_translate_options' ) );

		add_filter( 'sliced_business_details', array( $this, 'get_field_values') );

		add_action( 'sliced_do_payment', array( $this, 'process_payment') );
		add_action( 'sliced_do_payment', array( $this, 'payment_return'), 10 );

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
	 * Add the options for this gateway to the translate settings.
	 *
	 * @since   2.8.6
	 */
	public function add_translate_options( $options ) {
	
		if ( class_exists( 'Sliced_Translate' ) ) {

			// add fields to end of options array
			$options['fields'][] = array(
				'name'      => __( 'Pay with PayPal', 'sliced-invoices-translate' ),
				'desc'      => __( '', 'sliced-invoices-translate' ),
				'default'   => '',
				'type'      => 'text',
				'id'        => 'gateway-paypal-label',
				'attributes' => array(
					'class'      => 'i18n-multilingual regular-text',
				),
			);
		
		}

		return $options;

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

		// Get response from the server.
		$httpResponse = curl_exec($ch);

		if( ! $httpResponse ) {
			sliced_print_message( $id, "$methodName_ failed: ".curl_error($ch).'('.curl_errno($ch).')', 'failed', false );
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
			sliced_print_message( $id, "Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.", 'failed', false );
		}

		return $response;
	}


	/**
	 * Put our payment data in the one spot.
	 *
	 * @since   2.0.0
	 */
	private function payment_data( $id ) {
		$locale = get_option( 'WPLANG' ) ? get_option( 'WPLANG' ) : 'en_US';
		$payment_data =
			'&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode("SALE") .
			'&L_PAYMENTREQUEST_0_NAME0=' . urlencode( sliced_get_invoice_label() ) .
			'&L_PAYMENTREQUEST_0_NUMBER0=' . urlencode( sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) ) .
			'&L_PAYMENTREQUEST_0_AMT0=' . urlencode( sliced_get_invoice_total_raw( $id ) ) .
			'&L_PAYMENTREQUEST_0_QTY0='. urlencode( 1 ) .
			'&NOSHIPPING=1'. //set 1 to hide buyer's shipping address, in-case products that does not require shipping
			'&PAYMENTREQUEST_0_ITEMAMT=' . urlencode( sliced_get_invoice_total_raw( $id ) ) .
			//'&PAYMENTREQUEST_0_TAXAMT=' . urlencode( sliced_get_invoice_tax_raw( $id ) ) .
			'&PAYMENTREQUEST_0_AMT=' . urlencode( sliced_get_invoice_total_raw( $id ) ) .
			'&LOCALECODE='. urlencode( $locale ) . //PayPal pages to match the language on your website.
			'&LOGOIMG='. urlencode( sliced_get_business_logo() ) .//site logo
			'&CARTBORDERCOLOR=FFFFFF' . //border color of cart
			'&PAYMENTREQUEST_0_CUSTOM=' .  urlencode( $id ) . //id
			'&ALLOWNOTE=1';

		return $payment_data;
	}


	/**
	 * Start processing the payment.
	 *
	 * @since   2.0.0
	 */
	public function process_payment() {

		if ( empty( $_POST ) )
			return;

		// if we have POSTED from the invoice payment popup
		if ( ! isset( $_POST['start-payment'] ) )
			return;

		if ( $_POST['start-payment'] != 'Pay Now' )
			return;

		// if not paypal return and look for another payment gateway
		if ( $_POST['sliced_gateway'] != 'paypal' ) {
			return;
		}

		// check the nonce
		if( ! isset( $_POST['sliced_payment_nonce'] ) || ! wp_verify_nonce( $_POST['sliced_payment_nonce'], 'sliced_invoices_payment' ) ) {
			sliced_print_message( $id, __( 'There was an error with the form submission, please try again.', 'sliced-invoices' ), 'failed', false );
			return;
		}

		/*
		 * Get the invoice ID and gateway
		 */
		$id         = $_POST['sliced_payment_invoice_id'];
		$gateway    = $this->gateway();

		/*
		 * Send request to Paypal
		 */
		$payment_data = '&METHOD=SetExpressCheckout'.
			'&RETURNURL=' . urlencode( $gateway['payment_page'] ).
			'&CANCELURL=' . urlencode( $gateway['cancel_page'] ) .
			'&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode( $gateway['currency'] );
		$payment_data .= $this->payment_data( $id );

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
			 * Update the payment fields for the invoice
			 */
			$data = array(
				'token' => rawurldecode( $response["TOKEN"] ),
				'currency' => $gateway['currency'],
			);
			$this->update_post_payment_fields( $id, $data );

			/*
			 * Redirect user to PayPal store with Token received.
			 */
			$mode = ( $gateway['mode'] == 'sandbox' ) ? '.sandbox' : '';
			$url  = 'https://www' . $mode . '.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $response["TOKEN"] . '';

			wp_redirect( $url );

		} else {

			$message = 'Error Code: ' . urldecode( $response["L_ERRORCODE0"] ) . '<br />';
			$message .= urldecode( $response["L_LONGMESSAGE0"] );
			sliced_print_message( $id, urldecode( $message ), 'failed', false );

		}


	}



	/**
	 * Update the post meta payment field.
	 *
	 * @since   2.0.0
	 */
	public function update_post_payment_fields( $id, $data ) {

		update_post_meta( $id, '_sliced_paypal_token', $data['token'] );
		update_post_meta( $id, '_sliced_payment', array(
			'gateway'  => 'paypal',
			'token'    => $data['token'],
			'status'   => 'pending',
			'response' => '',
			'date'     => date("Y-m-d H:i:s"),
			'amount'   => sliced_get_invoice_total( $id ),
			'currency' => $data['currency'],
			)
		);

	}


	/**
	 * Further processing after returning from Paypal.
	 *
	 * @since   2.0.0
	 */
	public function payment_return( $data ) {

		// check for token and payerID back from paypal
		if ( ! isset( $_GET['token'] ) && ! isset( $_GET['PayerID'] ) )
			return;

		/*
		 * get the invoice that matches the token we are receiving
		 */
		$args = array(
			'post_type'     =>  'sliced_invoice',
			'meta_query'    =>  array(
				array(
					'value' =>  esc_html( $_GET['token'] )
				)
			)
		);
		$query      = get_posts( $args );
		$id         = $query[0]->ID;
		$payment    = get_post_meta( $id, '_sliced_payment', false );

		// if we can't get the id
		if( ! $id ) {
			sliced_print_message( $id, __( 'Error processing the payment.<br> <a href="' . esc_url( sliced_get_the_link( $id ) ) . '">Go back</a> and try again?', 'sliced-invoices' ), 'failed', false );
		}

		// if cancelled at paypal, print message and die.
		if( ! isset( $_GET["PayerID"] ) ) {
			sliced_print_message( $id, __( 'Payment has been cancelled.<br> <a href="' . esc_url( sliced_get_the_link( $id ) ) . '">Go back</a> and try again?', 'sliced-invoices' ), 'failed', true );
		}

		// if already been paid, print message and die.
		if( $payment[0]['status'] == 'success' ) {
			sliced_print_message( $id, __( 'This invoice has already been paid.', 'sliced-invoices' ), 'alert', true );
		}

		$gateway  = $this->gateway();
		$token    = $_GET["token"];
		$payer_id = $_GET["PayerID"];

		$payment_data   =   '&TOKEN=' . urlencode( $token ) .
			'&PAYERID=' . urlencode( $payer_id ) .
			'&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode( $gateway['currency'] );
		$payment_data   .= $this->payment_data( $id );

		$response = $this->paypal_request( 'DoExpressCheckoutPayment', $payment_data, $gateway['username'], $gateway['password'], $gateway['signature'], $gateway['mode'] );

		// set new values for the invoice payment meta. Values update further down.
		$payment[0]['date']     = date("Y-m-d H:i:s");
		$payment[0]['response'] = $response;
		$payment[0]['clientip'] = Sliced_Shared::get_ip();

		//Respond according to message we receive from Paypal
		if( 'SUCCESS' == strtoupper( $response["ACK"] ) || 'SUCCESSWITHWARNING' == strtoupper( $response["ACK"] ) ) {

			$message = '<h2>' . __( 'Success', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';

			/*
			 * Sometimes payments are kept pending even when transaction is complete.
			 * hence we need to notify user about it and ask them to manually approve the transiction
			*/
			if( 'Completed' == $response["PAYMENTINFO_0_PAYMENTSTATUS"] ) {
				$message .= __( 'Payment has been received!', 'sliced-invoices' );
				$message .= '<br />';
			}
			elseif ( 'Pending' == $response["PAYMENTINFO_0_PAYMENTSTATUS"] ) {
				$message .= __( 'Transaction complete, however payment is still pending.', 'sliced-invoices' );
				$message .= '<br />';
				$message .= __( 'You need to manually authorize this payment in your <a target="_new" href="http://www.paypal.com">PayPal Account</a>', 'sliced-invoices' );
				$message .= '<br />';
			}

			$message .= __( 'Your PayPal Transaction ID is: ', 'sliced-invoices' ) . urldecode( $response["PAYMENTINFO_0_TRANSACTIONID"] ) .'</p>';
			$message .= '</p>';

			$message .= '<p>';
			$message .= sprintf( __( '<a href="%1s">Click here to return to %s</a>', 'sliced-invoices' ), get_permalink($id), sliced_get_invoice_label() );
			$message .= '</p>';

			/*
			 * Get extra checkout details to store in database.
			*/
			$payment_data = '&TOKEN=' . urlencode( $_GET['token'] );
			$response = $this->paypal_request( 'GetExpressCheckoutDetails', $payment_data, $gateway['username'], $gateway['password'], $gateway['signature'], $gateway['mode'] );

			if( "SUCCESS" == strtoupper( $response["ACK"] ) || "SUCCESSWITHWARNING" == strtoupper( $response["ACK"] ) ) {
				$payment[0]['response'] = $response;
			}

			/**
			 * Update payment status
			 */
			$payment = get_post_meta( $id, '_sliced_payment', false );
			$payment[0]['status'] = $response["ACK"];
			update_post_meta( $id, '_sliced_payment', $payment[0] );

			/**
			 * Print the message
			 */
			sliced_print_message( $id, $message, 'success', false );

			/**
			 * send notifications, update status etc
			 */
			do_action( 'sliced_payment_made', $id, 'PayPal', 'success' );

		} else {

			/**
			 * Print the message
			 */
			sliced_print_message( $id, urldecode( $response["L_LONGMESSAGE0"] ), 'failed', false );

			/**
			 * send notifications, update status etc
			 */
			do_action( 'sliced_payment_made', $id, 'PayPal', 'failed' );

		}

	}


}

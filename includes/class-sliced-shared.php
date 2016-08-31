<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Calls the class.
 */
function sliced_call_shared_class() {
    new Sliced_Shared();

}
add_action( 'sliced_loaded', 'sliced_call_shared_class', 2 );



class Sliced_Shared {


	/**
	 * @var  object  Instance of this class
	 */
	protected static $instance;

	/**
	 * @var  array   Array of instantiated option objects
	 */
	protected static $option_instances;


    public function __construct() {
	}

    public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	private static $options = array(
		'general' =>  'sliced_general',
		'business' =>  'sliced_business',
		'quotes' =>  'sliced_quotes',
		'invoices' =>  'sliced_invoices',
		'payments' =>  'sliced_payments',
		'translate' =>  'sliced_translate'
	);


	public static function get_sliced_option( $get_option ) {
		$sliced_option = get_option( self::$options[$get_option] );
		return $sliced_option;
	}

	public static function get_sliced_options() {
		foreach ( self::$options as $option_name => $option ) {
			$sliced_options[$option_name] = get_option( $option );
		}
		return $sliced_options;
	}



	/**
	 * Get the id of the invoice/quote.
	 *
	 * @since   2.0.0
	 */
	public static function get_item_id( $id = 0 ) {

		global $post;

		$payments = get_option( 'sliced_payments' );
		$payment_page = $payments['payment_page'];

		if ( isset( $id ) && ! empty( $id ) ) {
			return (int)$id;
		} else if ( get_the_ID() && get_the_ID() != $payment_page ) {
			return (int) get_the_ID();
		} else if ( isset( $post->ID ) && ! empty( $post->ID ) && $post->ID != $payment_page ) {
			return (int) $post->ID;
		} else if ( isset( $_GET['post'] ) && ! empty( $_GET['post'] ) && $_GET['post'] != $payment_page ) {
			return (int) $_GET['post'];
		} else if ( isset( $_GET['id'] ) && ! empty( $_GET['id'] ) && $_GET['id'] != $payment_page ) {
			return (int) $_GET['id'];
		} else if ( isset( $_POST['id'] ) && ! empty( $_POST['id'] ) && $_POST['id'] != $payment_page ) {
			return (int) $_POST['id'];
		} else if ( isset( $_POST['sliced_payment_invoice_id'] ) && ! empty( $_POST['sliced_payment_invoice_id'] ) && $_POST['sliced_payment_invoice_id'] != $payment_page ) {
			return (int) $_POST['sliced_payment_invoice_id'];
		}

		return null;

	}


	/**
	 * Get the type - quote or invoice.
	 *
	 * @since   2.0.0
	 */
	public static function get_type( $id = 0 ) {
		if ( ! $id ) {
			$id = self::get_item_id();
		}
		$type = get_post_type( $id );
		if ( ! $type ) {
			$type = isset( $_GET['post_type'] ) && ! empty( $_GET['post_type'] ) ? $_GET['post_type'] : null;
		}
		if ( $type == 'sliced_quote' || $type == 'sliced_invoice' ) {
			return str_replace( 'sliced_', '', $type );
		}
		if( isset( $_POST['accept-quote'] ) ) {
			$type = 'invoice';
		}
		return null;
	}


	/**
	 * Get the status.
	 *
	 * @since   2.0.0
	 */
	public static function get_status( $id = 0, $type = null ) {
		if ( ! $id ) {
			$id = self::get_item_id();
		}
		if ( ! $type ) {
			$type = self::get_type();
		}
		$statuses = wp_get_post_terms( $id, $type . '_status', array( 'fields' => 'names' ) );
		return $statuses[0];
	}


	/**
	 * Get filename.
	 *
	 * @since   2.0.0
	 */
	public static function get_filename( $id = 0 ) {
		if ( ! $id ) {
			$id = self::get_item_id();
		}
		$type = self::get_type();
		$filename = sanitize_file_name( strtolower( $type . '-' . sliced_get_prefix( $id ) . sliced_get_number( $id ) ) );
		return apply_filters( 'sliced_get_the_filename', $filename, $id );
	}


	/**
	 * Get post meta.
	 *
	 * @since   2.0.0
	 */
	private static function get_sliced_meta( $id = 0, $key, $single = true ) {
		if ( ! $id ) {
			$id = self::get_item_id();
		}
		$meta = get_post_meta( $id, $key, $single );
		return $meta;
	}

	/**
	 * get the tax name.
	 *
	 * @since   2.0.0
	 */
	public static function get_tax_name() {
		$payments 	= self::get_sliced_option( 'payments' );
	    $name 		= $payments['tax_name'] ? $payments['tax_name'] : __( 'Tax', 'sliced-invoices' );
    	return $name;
	}

	/**
	 * Get the tax amount.
	 *
	 * @since   2.0.0
	 */
	public static function get_tax_amount( $id = 0 ) {

		$id = Sliced_Shared::get_item_id( $id );

	    if ( isset( $id ) ) {

	    	$amount = self::get_sliced_meta( $id, '_sliced_tax', false );

	    	if( ! empty( $amount ) ) {
	    		$amount 	= $amount[0];
	    	} else {
	    		$payments 	= self::get_sliced_option( 'payments' );
	    		$amount 	= isset( $payments['tax'] ) ? $payments['tax'] : '0.00';
	    	}
	    	
	    } else {
	    	$payments 	= self::get_sliced_option( 'payments' );
	    	$amount 	= isset( $payments['tax'] ) ? $payments['tax'] : '0.00';
	    }

    	return self::get_raw_number( $amount );;
	}

	/**
	 * Get the currency symbol.
	 *
	 * @since   2.0.0
	 */
	public static function get_currency_symbol() {

		$id = Sliced_Shared::get_item_id();

	    if ( isset( $id ) ) {

	    	$symbol = self::get_sliced_meta( $id, '_sliced_currency_symbol', false );
	    	if( !empty( $symbol ) ) {
	    		$symbol 	= $symbol[0];
	    	} else {
	    		$payments 	= self::get_sliced_option( 'payments' );
	    		$symbol 	= isset( $payments['currency_symbol'] ) ? $payments['currency_symbol'] : '$';
	    	}
	    	//var_dump($amount);
	    } else {
	    	$payments 	= self::get_sliced_option( 'payments' );
	    	$symbol 	= isset( $payments['currency_symbol'] ) ? $payments['currency_symbol'] : '$';
	    }

    	return $symbol;

	}

	/**
	 * Get the currency position.
	 *
	 * @since   2.0.0
	 */
	public static function get_currency_position() {
	    $payments 	= self::get_sliced_option( 'payments' );
	    $position 	= isset( $payments['currency_pos'] ) ? $payments['currency_pos'] : 'left';
	    return $position;
	}

	/**
	 * Get the decimals.
	 *
	 * @since   2.0.0
	 */
	public static function get_decimals() {
	    $payments 	= self::get_sliced_option( 'payments' );
	    $decimals 	= isset( $payments['decimals'] ) ? $payments['decimals'] : '2';
	    return $decimals;
	}

	/**
	 * Get the decimal seperator.
	 *
	 * @since   2.0.0
	 */
	public static function get_decimal_seperator() {
	    $payments 	= self::get_sliced_option( 'payments' );
	    $decimals 	= isset( $payments['decimal_sep'] ) ? $payments['decimal_sep'] : '.';
	    return $decimals;
	}

	/**
	 * Get the thousand seperator.
	 *
	 * @since   2.0.0
	 */
	public static function get_thousand_seperator() {
	    $payments 	= self::get_sliced_option( 'payments' );
	    $thousand 	= isset( $payments['thousand_sep'] ) ? $payments['thousand_sep'] : ',';
	    return $thousand;
	}


	/**
	 * Get the raw number with a period as the decimal. For calculations.
	 *
	 * @since   2.0.0
	 */
	public static function get_raw_number( $amount ) {
		$thou_sep 	= sliced_get_thousand_seperator();
	    $dec_sep 	= sliced_get_decimal_seperator();

		$pieces 	= explode($dec_sep, $amount);
		$whole 		= str_replace($thou_sep, '', $pieces[0] ); // whole
		$decimals 	= isset( $pieces[1] ) ? $pieces[1] : '00'; // decimals

		$amount 	= (float)$whole . '.' . $decimals;

	    return apply_filters( 'sliced_get_raw_number', $amount );
	}

	/**
	 * Get the formatted number only.
	 *
	 * @since   2.0.0
	 */
	public static function get_formatted_number( $amount ) {

	    $thou_sep 	= sliced_get_thousand_seperator();
	    $dec_sep 	= sliced_get_decimal_seperator();
	    $decimals 	= sliced_get_decimals();

	    $formatted 	= number_format( (float)$amount, (int)$decimals, $dec_sep, $thou_sep );

	    return apply_filters( 'sliced_get_formatted_number', $formatted );
	}

	/**
	 * Get the complete formatted currency.
	 *
	 * @since   2.0.0
	 */
	public static function get_formatted_currency( $amount ) {

	    $symbol 	= sliced_get_currency_symbol();
	    $position 	= sliced_get_currency_position();
	    $amount 	= self::get_formatted_number( $amount );

	    switch ($position) {
	    	case 'left':
	    		$formatted = $symbol . $amount;
	    		break;
	    	case 'right':
	    		$formatted = $amount . $symbol;
	    		break;
	    	case 'left_space':
	    		$formatted = $symbol . ' ' . $amount;
	    		break;
	    	case 'right_space':
	    		$formatted = $amount . ' ' . $symbol;
	    		break;

	    	default:
	    		$formatted = $symbol . $amount;
	    		break;
	    }

	    return apply_filters( 'sliced_get_formatted_currency', $formatted );
	}



	/**
	 * Get the line items.
	 *
	 * @since   2.0.0
	 */
	public static function get_line_items( $id = 0 ) {
		$items = self::get_sliced_meta( $id, '_sliced_items', false );
		return $items;
	}


	/**
	 * Get the line items totals.
	 *
	 * @since   2.0.0
	 */
	public static function get_totals( $id ) {

		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id( $id );
		}

	    $items = self::get_line_items( $id );

	    // if there are no line items, simply return zero for all amounts
	    if( ! $items || $items == null || empty( $items ) || empty( $items[0] ) ) {
	        return array(
	        	'sub_total' => 0,
	        	'tax' 		=> 0,
	        	'total' 	=> 0,
	        );
	    }

	    $total = array();
	    foreach ( $items[0] as $value ) {

	    	$qty = isset( $value['qty'] ) ? self::get_raw_number( $value['qty'] ) : 0;
	    	$amt = isset( $value['amount'] ) ? self::get_raw_number( $value['amount'] ) : 0;
	    	$tax = isset( $value['tax'] ) ? self::get_raw_number( $value['tax'] ) : 0;

	        $sub_total 	= self::get_line_item_sub_total( $qty, $amt, $tax );
	        $total[]    = $sub_total;

	    }

	    $sub_total = array_sum( $total );

	   	$global_tax = apply_filters( 'sliced_totals_global_tax', self::get_tax_amount( $id ), $id );

	    if( $global_tax == '' || $global_tax == '0' || $global_tax == null || $global_tax == '0.00' ) {
	        $total = $sub_total;
	        $tax_amount = '0';
	    } else {
	        $tax_percentage = $global_tax / 100;
	        $tax_amount    	= $sub_total * $tax_percentage;
	        $total      	= $sub_total + $tax_amount;
	    }

	    return apply_filters( 'sliced_invoice_totals', array(
	        'sub_total' 		=> $sub_total,
	        'tax' 				=> $tax_amount,
	        'total' 			=> $total,
	    ), $id );

	}


	/**
	 * Get the line item sub total for an individual row.
	 *
	 * @since   2.0.0
	 */
	public static function get_line_item_sub_total( $qty, $amt, $tax ) {

	   	$line_tax_perc   	= $tax != 0 ? $tax / 100 : 0; // 0.10
        $line_sub_total  	= $qty * $amt; // 100
        $line_tax_amt    	= $line_sub_total * $line_tax_perc; // 10
        $line_total      	= $line_sub_total + $line_tax_amt; // 110
        // pp($amt);
	    return apply_filters( 'sliced_get_line_item_sub_total', $line_total );
	}


	/**
	 * Get the accepted payment methods.
	 *
	 * @since   2.0.0
	 */
    public static function get_accepted_payment_methods() {
        $pay_array  = array();
        $payments   = get_option( 'sliced_payments' );
        if ( ! empty( $payments['bank'] ) ) {
            $pay_array['bank'] = 'Bank';
        }
        if ( ! empty( $payments['generic_pay'] ) ) {
            $pay_array['generic'] = 'Generic';
        }
        return apply_filters( 'sliced_register_payment_method', $pay_array );
    }


	/**
	 * Get the business details.
	 *
	 * @since   2.0.0
	 */
	public static function get_business_details() {

		$options = self::get_sliced_options();

	    return apply_filters( 'sliced_business_details', array(
	        'logo'				=> isset( $options['business']['logo'] ) ? $options['business']['logo'] : '',
	        'name'				=> isset( $options['business']['name'] ) ? $options['business']['name'] : '',
	        'address'			=> isset( $options['business']['address'] ) ? $options['business']['address'] : '',
	        'extra_info'		=> isset( $options['business']['extra_info'] ) ? $options['business']['extra_info'] : '',
	        'website'			=> isset( $options['business']['website'] ) ? $options['business']['website'] : '',
	        'bank'				=> isset( $options['payments']['bank'] ) ? $options['payments']['bank'] : '',
	        'generic_pay'		=> isset( $options['payments']['generic_pay'] ) ? $options['payments']['generic_pay'] : '',
	        'footer'			=> isset( $options['general']['footer'] ) ? $options['general']['footer'] : '',
	    ) );

	}


	/**
	 * Get the id of the client from the id of the invoice or quote.
	 *
	 * @since   2.0.0
	 */
	public static function get_client_id( $id = 0 ) {
		$id = self::get_item_id( $id );
		$client_id = self::get_sliced_meta( $id, '_sliced_client' );
		return $client_id;
	}

	/**
	 * Get the data of the client.
	 *
	 * @since   2.0.0
	 */
	public static function get_client_data( $id = 0 ) {
	    $client_data = get_userdata( self::get_client_id( $id ) );
		return $client_data;
	}

	/**
	 * Get the client details.
	 *
	 * @since   2.0.0
	 */
	public static function get_client_details( $id = 0 ) {

		$client = apply_filters( 'sliced_client_data', self::get_client_data( $id ) );
		//DG note: this is not used here --    $id     = apply_filters( 'sliced_client_id', self::get_client_id( $id ) );

		if ( ! $client ) {
			return;
		}

		return apply_filters( 'sliced_client_details', array(
			'id'         => $client->ID,
			'first_name' => isset( $client->first_name ) ? $client->first_name : '',
			'last_name'  => isset( $client->last_name ) ? $client->last_name : '',
			'business'   => get_user_meta( $client->ID, '_sliced_client_business', true ),
			'address'    => get_user_meta( $client->ID, '_sliced_client_address', true ),
			'extra_info' => get_user_meta( $client->ID, '_sliced_client_extra_info', true ),
			'website'    => isset( $client->data->user_url ) ? $client->data->user_url :  $client->user_url,
			'email'      => isset( $client->data->user_email ) ? $client->data->user_email :  $client->user_email,
		) );

	}


	/**
	 * Get todays date (localized format).
	 *
	 * @since   2.0.0
	 */
	public static function get_todays_date() {
		$format = get_option( 'date_format' );

		if (strpos( $format, 'd/m') !== false) {
			$format = str_replace("/", ".", $format);
		}

		$today = date_i18n( $format, (int) current_time( 'timestamp', true ) );
		return $today;
	}


	/**
	 * Get today's date, formatted ISO_8601.
	 *
	 * @since   2.873
	 */
	public static function get_todays_date_iso8601() {
		$format = "Y-m-d";
		$today = date_i18n( $format, (int) current_time( 'timestamp', true ) );
		return $today;
	}


	/**
	 * Get users IP.
	 *
	 * @since   2.0.0
	 */
	public static function get_ip() {

		$ipaddress = '';
	    if (getenv('HTTP_CLIENT_IP'))
	        $ipaddress = getenv('HTTP_CLIENT_IP');
	    else if(getenv('HTTP_X_FORWARDED_FOR'))
	        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
	    else if(getenv('HTTP_X_FORWARDED'))
	        $ipaddress = getenv('HTTP_X_FORWARDED');
	    else if(getenv('HTTP_FORWARDED_FOR'))
	        $ipaddress = getenv('HTTP_FORWARDED_FOR');
	    else if(getenv('HTTP_FORWARDED'))
	        $ipaddress = getenv('HTTP_FORWARDED');
	    else if(getenv('REMOTE_ADDR'))
	        $ipaddress = getenv('REMOTE_ADDR');
	    else
	        $ipaddress = 'UNKNOWN';

	    return $ipaddress;

	}


	/**
	 * Defines the function used to initial the cURL library.
	 *
	 * @since   2.0.0
	 */
	private static function curl( $url ) {

		$curl = curl_init( $url );

		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HEADER, 0 );
		curl_setopt( $curl, CURLOPT_USERAGENT, '' );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $curl, CURLOPT_TIMEOUT_MS, 20000 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false);

		$response = curl_exec( $curl );

		if( 0 !== curl_errno( $curl ) || 200 !== curl_getinfo( $curl, CURLINFO_HTTP_CODE ) ) {
			$response = null;
		} // end if
		curl_close( $curl );

		return $response;

	}


	/**
	 * Retrieves the response from the specified URL using one of PHP's outbound request facilities.
	 *
	 * @since   2.0.0
	 */
	public static function request_data( $url ) {

		$response = null;

		// First, we try to use wp_remote_get
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if( is_wp_error( $response ) ) {

			// If that doesn't work, then we'll try file_get_contents
			$response = @file_get_contents( $url );
			
			if( false == $response ) {

				// And if that doesn't work, then we'll try curl
				$response = self::curl( $url );
				if( null == $response ) {
					$response = 0;
				}

			}

		}

		// If the response is an array, it's coming from wp_remote_get,
		// so we just want to capture to the body index for json_decode.
		if( is_array( $response ) ) {
			$response = $response['body'];
		}

		// try sslverify
		if( ! $response || $response == false || $response == 0 ) {

			$general = get_option( 'sliced_general' );
			$sslverify = $general['pdf_ssl'] == 'true' ? true : false;

			$response = wp_remote_get( $url, array(
			    'sslverify' => $sslverify,
				'timeout'   => 10,
			));

			if( is_array( $response ) ) {
				$response = $response['body'];
			} 
			
			// if still nothing, show an error at least
			if( is_wp_error( $response ) ) {
				$error_string = $response->get_error_message();
				$response = '<div id="message" class="error"><p>' . $error_string . '</p></div>';
			}

		}

		return $response;

	}




}

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
		'general'   => 'sliced_general',
		'business'  => 'sliced_business',
		'quotes'    => 'sliced_quotes',
		'invoices'  => 'sliced_invoices',
		'payments'  => 'sliced_payments',
		'tax'       => 'sliced_tax',
		'translate' => 'sliced_translate'
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
			return intval( $_GET['post'] );
		} else if ( isset( $_GET['id'] ) && ! empty( $_GET['id'] ) && $_GET['id'] != $payment_page ) {
			return intval( $_GET['id'] );
		} else if ( isset( $_POST['id'] ) && ! empty( $_POST['id'] ) && $_POST['id'] != $payment_page ) {
			return intval( $_POST['id'] );
		} else if ( isset( $_POST['sliced_payment_invoice_id'] ) && ! empty( $_POST['sliced_payment_invoice_id'] ) && $_POST['sliced_payment_invoice_id'] != $payment_page ) {
			return intval( $_POST['sliced_payment_invoice_id'] );
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
		$statuses = wp_get_post_terms( $id, $type . '_status' );
		if ( is_array( $statuses ) && ! empty( $statuses ) ) {
			$term = $statuses[0];
			return $term->slug;
		} else {
			return false;
		}
	}


	/**
	 * Get filename.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function get_filename( $id = 0 ) {
		if ( ! $id ) {
			$id = self::get_item_id();
		}
		$type = self::get_type( $id );
		$filename = sanitize_file_name( strtolower( $type . '-' . sliced_get_prefix( $id ) . sliced_get_number( $id ) . sliced_get_suffix( $id ) ) );
		return apply_filters( 'sliced_get_the_filename', $filename, $id );
	}


	/**
	 * Get post meta.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	private static function get_sliced_meta( $id = 0, $key = '', $single = true ) {
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
		$tax  = self::get_sliced_option( 'tax' );
	    $name = $tax['tax_name'] ? $tax['tax_name'] : __( 'Tax', 'sliced-invoices' );
    	return $name;
	}

	/**
	 * Get the tax amount.
	 *
	 * @since   2.0.0
	 */
	public static function get_tax_amount( $id = 0, $formatted = false ) {

		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id( $id );
		}

	    if ( $id ) {

	    	$amount = self::get_sliced_meta( $id, '_sliced_tax', false );

	    	if( ! empty( $amount ) ) {
	    		$amount 	= $amount[0];
	    	} else {
	    		$tax    = self::get_sliced_option( 'tax' );
	    		$amount = isset( $tax['tax'] ) ? $tax['tax'] : '0.00';
	    	}
	    	
	    } else {
	    	$tax    = self::get_sliced_option( 'tax' );
	    	$amount = isset( $tax['tax'] ) ? $tax['tax'] : '0.00';
	    }

		if ( $formatted ) {
			return self::get_formatted_number( $amount );
		} else {
			return self::get_raw_number( $amount );
		}
	}
	
	/**
	 * Get the tax calculation method (exclusive or inclusive).
	 *
	 * @since   3.7.0
	 */
	public static function get_tax_calc_method( $id = 0 ) {

		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id( $id );
		}

	    if ( $id ) {

	    	$method = self::get_sliced_meta( $id, '_sliced_tax_calc_method', true );

	    	if( ! $method ) {
	    		$tax    = self::get_sliced_option( 'tax' );
	    		$method = isset( $tax['tax_calc_method'] ) ? $tax['tax_calc_method'] : 'exclusive';
	    	}
	    	
	    } else {
	    	$tax    = self::get_sliced_option( 'tax' );
	    	$method = isset( $tax['tax_calc_method'] ) ? $tax['tax_calc_method'] : 'exclusive';
	    }

		return $method;
	}
	
	/**
	 * Get the currency.
	 *
	 * @since   2.875
	 */
	public static function get_currency( $id = 0 ) {

		$currency = false;
		
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		
	    $currency = self::get_sliced_meta( $id, '_sliced_currency', true );
		
    	return $currency;

	}

	/**
	 * Get the currency symbol.
	 *
	 * @since   2.0.0
	 */
	public static function get_currency_symbol( $id = 0 ) {

		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		
	    if ( isset( $id ) ) {

	    	$symbol = self::get_sliced_meta( $id, '_sliced_currency_symbol', false );
	    	if( !empty( $symbol ) ) {
	    		$symbol 	= $symbol[0];
	    	} else {
	    		$payments 	= self::get_sliced_option( 'payments' );
	    		$symbol 	= isset( $payments['currency_symbol'] ) ? $payments['currency_symbol'] : '$';
	    	}
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
	    $thousand 	= isset( $payments['thousand_sep'] ) ? $payments['thousand_sep'] : '';
	    return $thousand;
	}


	/**
	 * Get the raw number with a period as the decimal. For calculations.
	 * 
	 * @version 3.9.1
	 * @since   2.0.0
	 */
	public static function get_raw_number( $amount, $id = 0 ) {
		
		$currency = sliced_get_currency_symbol( $id );
		$amount   = str_replace( $currency, '', $amount );
		
		$thou_sep = sliced_get_thousand_seperator();
		$dec_sep  = sliced_get_decimal_seperator();
		
		$pieces   = explode( $dec_sep, $amount );
		$whole    = str_replace( $thou_sep, '', $pieces[0] );
		$decimals = isset( $pieces[1] ) ? $pieces[1] : '00';
		
		$amount   = floatval( $whole . '.' . $decimals );
		
		return apply_filters( 'sliced_get_raw_number', $amount, $id );
	}
	
	/**
	 * Get the formatted number only.
	 *
	 * @since   2.0.0
	 *
	 * @var $amount  must be raw amount, either integer or float.
	 */
	public static function get_formatted_number( $amount ) {
		
	    $thou_sep 	= sliced_get_thousand_seperator();
	    $dec_sep 	= sliced_get_decimal_seperator();
	    $decimals 	= sliced_get_decimals();
		
	    $formatted 	= number_format( round( (float)$amount, $decimals ), (int)$decimals, $dec_sep, $thou_sep );
		
	    return apply_filters( 'sliced_get_formatted_number', $formatted );
	}

	/**
	 * Get the complete formatted currency.
	 *
	 * @since   2.0.0
	 */
	public static function get_formatted_currency( $amount, $id = 0 ) {

	    $symbol 	= sliced_get_currency_symbol( $id );
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
	 * Get the payments.
	 *
	 * @since   3.6.0
	 */
	public static function get_payments( $id = 0 ) {
		$items = self::get_sliced_meta( $id, '_sliced_payment', false );
		return $items;
	}


	/**
	 * Get the line items totals.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public static function get_totals( $id ) {
	
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id( $id );
		}
		
		$decimals = self::get_decimals();
		$items = self::get_line_items( $id );
		$tax_calc_method = self::get_tax_calc_method( $id );
		
		$totals = array(
			'sub_total'         => 0,
			'sub_total_taxable' => 0,
			'tax'               => 0,
			'discounts'         => 0,
			'payments'          => 0,
			'total'             => 0,
			'total_due'         => 0,
		);
		
	    // if there are no line items, simply return zero for all amounts
	    if( ! $items || $items == null || empty( $items ) || empty( $items[0] ) || ! is_array( $items[0] ) ) {
	        return $totals;
	    }
	    
        // work out the line item totals
	    foreach ( $items[0] as $value ) {

	    	$qty = isset( $value['qty'] ) ? self::get_raw_number( $value['qty'], $id ) : 0;
	    	$amt = isset( $value['amount'] ) ? self::get_raw_number( $value['amount'], $id ) : 0;
	    	
			// for historical reasons, the "adjust" field is named "tax" internally,
			// but it is unrelated to the actual tax field(s) in use today.
			$adj = isset( $value['tax'] ) ? self::get_raw_number( $value['tax'], $id ) : 0;

	        $line_total = self::get_line_item_sub_total( $qty, $amt, $adj );
			
			$totals['sub_total'] = $totals['sub_total'] + $line_total;
			if ( isset( $value['taxable'] ) && $value['taxable'] === 'on' ) {
				$totals['sub_total_taxable'] = $totals['sub_total_taxable'] + $line_total;
			}

	    }
		
		// add discounts, if any (part 1 of 2 -- before tax)
		$discounts = 0;
		$discount_value         = get_post_meta( $id, '_sliced_discount', true );            // for Sliced Invoices >= 3.9.0
		$discount_type          = get_post_meta( $id, '_sliced_discount_type', true );
		$discount_tax_treatment = get_post_meta( $id, '_sliced_discount_tax_treatment', true );
		if ( ! $discount_value ) {
			$discount_value         = get_post_meta( $id, 'sliced_invoice_discount', true ); // for Sliced Invoices < 3.9.0
			$discount_type          = 'amount';
			$discount_tax_treatment = 'after';
		}
		$discount_value = self::get_raw_number( $discount_value, $id );
		if ( $discount_type === 'percentage' ) {
			$discount_percentage = $discount_value / 100;
		}
		
		if ( $discount_tax_treatment === 'before' ) {
			if ( $discount_type === 'percentage' ) {
				$discounts = round( $totals['sub_total'] * $discount_percentage, $decimals );
			} else {
				$discounts = $discount_value;
			}
			$totals['sub_total_taxable'] = $totals['sub_total_taxable'] - $discounts;
			if ( $totals['sub_total_taxable'] < 0 ) {
				$totals['sub_total_taxable'] = 0;
			}
		}
		
		apply_filters( 'sliced_totals_discounts_before_tax', $discounts );
		
        // add tax, if any
	   	$tax_amount = apply_filters( 'sliced_totals_global_tax', self::get_tax_amount( $id ), $id );
		
	    if( $tax_amount == '' || $tax_amount == '0' || $tax_amount == null || $tax_amount == '0.00' ) {
	        $totals['total'] = $totals['sub_total'];
	    } else {
	        $tax_percentage  = $tax_amount / 100;
			if ( $tax_calc_method === 'inclusive' ) {
				// europe:
				$totals['tax']   = round(
					$totals['sub_total_taxable'] - ( $totals['sub_total_taxable'] / ( 1 + $tax_percentage ) ),
					$decimals 
				);
				$totals['total'] = $totals['sub_total'];
			} else {
				// everybody else:
				$totals['tax']   = round( $totals['sub_total_taxable'] * $tax_percentage, $decimals );
				$totals['total'] = $totals['sub_total'] + $totals['tax'];
			}
	    }
		
		// add discounts, if any (part 2 of 2 -- after tax)
		if ( $discount_tax_treatment !== 'before' ) {
			if ( $discount_type === 'percentage' ) {
				$discounts = round( $totals['total'] * $discount_percentage, $decimals );
			} else {
				$discounts = $discount_value;
			}
		}
		
		apply_filters( 'sliced_totals_discounts_after_tax', $discounts );
		
		if ( $discounts ) {
			$totals['discounts'] = $discounts;
			$totals['total'] = $totals['total'] - $totals['discounts'];
		} else {
			$totals['discounts'] = 0;
			$discounts = 0;
		}
		
		// work out the payments totals
		$payments = self::get_payments( $id );
		$payments_total = 0;
		
		if ( is_array( $payments ) && isset( $payments[0] ) && is_array( $payments[0] ) ) {
			foreach ( $payments[0] as $payment ) {
				$amount = isset( $payment['amount'] ) ? self::get_raw_number( $payment['amount'], $id ) : 0;
				$status = isset( $payment['status'] ) ? $payment['status'] : false;
				if ( $status === 'success' ) {
					// only count "Completed" payments
					$payments_total = $payments_total + $amount;
				}
			}
			$totals['payments'] = $payments_total;
		}

		// apply filters
		$totals = apply_filters( 'sliced_invoice_totals', $totals, $id );

		// patch for Deposit Invoices extension < 2.2.0, which overwrites $totals
		if ( defined( 'SI_DEPOSIT_VERSION' ) && version_compare( SI_DEPOSIT_VERSION, '2.2.0', '<=' ) ) {
			$totals['discounts'] = $discounts;
			$totals['payments'] = $payments_total;
		}

		// process any adjustments from external add-ons here
		// (avoids any potential race condition by doing this only here)
		if ( isset( $totals['addons'] ) && is_array( $totals['addons'] ) ) {
			foreach ( $totals['addons'] as $addon ) {
			
				if ( isset( $addon['_adjustments'] ) && is_array( $addon['_adjustments'] ) ) {
					foreach ( $addon['_adjustments'] as $adjustment ) {
						$type   = isset( $adjustment['type'] ) ? $adjustment['type'] : false;
						$source = isset( $adjustment['source'] ) ? $adjustment['source'] : false;
						$target = isset( $adjustment['target'] ) ? $adjustment['target'] : false;
						if ( ! $type || ! $source || ! $target ) {
							continue; // if missing required fields, skip
						}
						if ( ! isset( $addon[ $source ] ) ) {
							continue; // if can't map source, skip
						}
						if ( ! isset( $totals[ $target ] ) ) {
							continue; // if can't map target, skip
						}
						// we go on...
						switch ( $type ) {
							case 'add':
								$totals[ $target ] = $totals[ $target ] + $addon[ $source ];
								break;
							case 'subtract':
								$totals[ $target ] = $totals[ $target ] - $addon[ $source ];
								break;
						}
						
					}
				}
			
			}
		}

		// save this for last
		$totals['total_due'] = $totals['total'] - $totals['payments'];

		return $totals;

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
            $pay_array['bank'] = __( 'Bank', 'sliced-invoices' );
        }
        if ( ! empty( $payments['generic_pay'] ) ) {
            $pay_array['generic'] = __( 'Generic', 'sliced-invoices' );
        }
        return apply_filters( 'sliced_register_payment_method', $pay_array );
    }


	/**
	 * Get possible payment statuses
	 *
	 * @since   3.6.0
	 */
    public static function get_payment_statuses() {
        $statuses = array(
			'success'   => __( 'Completed', 'sliced-invoices' ),
			'pending'   => __( 'Pending', 'sliced-invoices' ),
			'failed'    => __( 'Failed', 'sliced-invoices' ),
			'refunded'  => __( 'Refunded', 'sliced-invoices' ),
			'cancelled' => __( 'Cancelled', 'sliced-invoices' ),
		);
        return apply_filters( 'sliced_payment_statuses', $statuses );
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
	        'footer'			=> isset( $options['payments']['footer'] ) ? $options['payments']['footer'] : '',
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
	 * Get localised date from utc timestamp
	 *
	 * @since   3.8.0
	 */
	public static function get_local_date_from_timestamp( $timestamp = 0, $format = '' ) {
		$date_iso = date( 'Y-m-d H:i:s', $timestamp );
		$date = get_date_from_gmt( $date_iso, $format );
		return $date;
	}
	
	
	/**
	 * Get full localised date from utc timestamp
	 *
	 * @since   3.8.0
	 */
	public static function get_local_date_i18n_from_timestamp( $timestamp = 0 ) {
		$date_iso = date( 'Y-m-d H:i:s', $timestamp );
		$date_i18n = date_i18n(
			get_option( 'date_format' ),
			strtotime( get_date_from_gmt( $date_iso ) )
		);
		return $date_i18n;
	}
	
	
	/**
	 * Get the site's timezone
	 *
	 * @since   3.8.0
	 */
	public static function get_local_timezone() {
		// get the local timezone
		$timezone_setting = get_option( 'timezone_string' );
		if ( ! $timezone_setting > '' ) {
			$timezone_setting = get_option( 'gmt_offset' );
			if ( floatval( $timezone_setting > 0 ) ) {
				$timezone_setting = '+' . $timezone_setting;
			}
		}
		if( ! $timezone_setting ) { // if set to "UTC+0" in WordPress it returns "0", but DateTimeZone doesn't recognize this
			$timezone_setting = 'UTC';
		}
		try {
			$timezone = new DateTimeZone( $timezone_setting );
		} catch (Exception $e) {
			// worst case scenario
			$timezone = new DateTimeZone( 'UTC' );			
		}
		return $timezone;
	}
	
	
	/**
	 * Convert localized time fields to utc timestamp for saving
	 *
	 * @since   3.8.0
	 */
	public static function get_timestamp_from_local_time( $Y, $m, $d, $H, $i, $s ) {
	
		// validate args
		$Y = intval( $Y );
		$m = intval( $m );
		$d = intval( $d );
		$H = intval( $H );
		$i = intval( $i );
		$s = intval( $s );
	
		$timezone = Sliced_Shared::get_local_timezone();
		
        $date = new DateTime();
		$date->setTimezone( $timezone );
		$date->setDate( $Y, $m, $d );
		$date->setTime( $H, $i, $s );
		
		return $date->getTimestamp();
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
	 * Determine if the current request is for any Sliced Invoices-related page.
	 *
	 * @since   3.8.3
	 */
	public static function is_sliced_invoices_page() {
	
		global $pagenow;
		
		$is_sliced_invoices_page = false;
		
		$payments = get_option( 'sliced_payments' );
		
		if (
			in_array( sliced_get_the_type(), array( 'invoice', 'quote' ) ) // quote or invoice listing page, editing page, or frontend page
			|| is_page( (int)$payments['payment_page'] )                   // payment page
			|| ( $pagenow === 'admin.php' &&                               // sliced settings pages
				in_array( $_GET['page'], array( 'sliced_invoices_settings', 'sliced_reports', 'sliced_tools', 'sliced_extras', 'sliced_licenses' ) )
				)
		) {
			$is_sliced_invoices_page = true;
		}
		
		return $is_sliced_invoices_page;
	}


	/**
	 * Defines the function used to initial the cURL library.
	 *
	 * @since   2.0.0
	 */
	private static function curl( $url ) {

		// removed 2019-10-14
		return null;
	}


	/**
	 * Retrieves the response from the specified URL using one of PHP's outbound request facilities.
	 *
	 * @since   2.0.0
	 */
	public static function request_data( $url ) {
		
		$general   = get_option( 'sliced_general' );
		$sslverify = $general['pdf_ssl'] == 'true' ? true : false;
		
		$response = apply_filters( 'sliced_invoices_request_data', false, $url );
		if ( ! $response ) {
			$response = wp_remote_get(
				$url, 
				array(
					'sslverify' => $sslverify,
					'timeout'   => 10,
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$response = $response['body'];
			} else {
				$response = false;
			}
		}
		
		if ( is_wp_error( $response ) ) {
			$error_string = $response->get_error_message();
			$response = '<div id="message" class="error"><p>' . $error_string . '</p></div>';
		}
		
		return $response;
	
	}
	
	
	/**
	 * Quote and invoice currencies data
	 *
	 * @since  2.875
	 */
	public static function currencies() {
		$currencies = array(
			array(
				'name' => 'UAE Dirham',
				'alpha3' => 'AED',
				'numeric' => '784',
				'exp' => 2,
				'country' => 'AE',
			),
			array(
				'name' => 'Afghan Afghani',
				'alpha3' => 'AFN',
				'numeric' => '971',
				'exp' => 2,
				'country' => 'AF',
			),
			array(
				'name' => 'Albanian Lek',
				'alpha3' => 'ALL',
				'numeric' => '008',
				'exp' => 2,
				'country' => 'AL',
			),
			array(
				'name' => 'Armenian Dram',
				'alpha3' => 'AMD',
				'numeric' => '051',
				'exp' => 2,
				'country' => 'AM',
			),
			array(
				'name' => 'Netherlands Antillean Guilder',
				'alpha3' => 'ANG',
				'numeric' => '532',
				'exp' => 2,
				'country' => array(
					'CW',
					'SX',
				),
			),
			array(
				'name' => 'Angolan Kwanza',
				'alpha3' => 'AOA',
				'numeric' => '973',
				'exp' => 2,
				'country' => 'AO',
			),
			array(
				'name' => 'Argentine Peso',
				'alpha3' => 'ARS',
				'numeric' => '032',
				'exp' => 2,
				'country' => 'AR',
			),
			array(
				'name' => 'Australian Dollar',
				'alpha3' => 'AUD',
				'numeric' => '036',
				'exp' => 2,
				'country' => array(
					'AU',
					'CC',
					'CX',
					'HM',
					'KI',
					'NF',
					'NR',
					'TV',
				),
			),
			array(
				'name' => 'Aruban Florin',
				'alpha3' => 'AWG',
				'numeric' => '533',
				'exp' => 2,
				'country' => 'AW',
			),
			array(
				'name' => 'Azerbaijani Manat',
				'alpha3' => 'AZN',
				'numeric' => '944',
				'exp' => 2,
				'country' => 'AZ',
			),
			array(
				'name' => 'Bosnia and Herzegovina Convertible Mark',
				'alpha3' => 'BAM',
				'numeric' => '977',
				'exp' => 2,
				'country' => 'BA',
			),
			array(
				'name' => 'Barbados Dollar',
				'alpha3' => 'BBD',
				'numeric' => '052',
				'exp' => 2,
				'country' => 'BB',
			),
			array(
				'name' => 'Bangladeshi Taka',
				'alpha3' => 'BDT',
				'numeric' => '050',
				'exp' => 2,
				'country' => 'BD',
			),
			array(
				'name' => 'Bulgarian Lev',
				'alpha3' => 'BGN',
				'numeric' => '975',
				'exp' => 2,
				'country' => 'BG',
			),
			array(
				'name' => 'Bahraini Dinar',
				'alpha3' => 'BHD',
				'numeric' => '048',
				'exp' => 3,
				'country' => 'BH',
			),
			array(
				'name' => 'Burundian Franc',
				'alpha3' => 'BIF',
				'numeric' => '108',
				'exp' => 0,
				'country' => 'BI',
			),
			array(
				'name' => 'Bermudian Dollar',
				'alpha3' => 'BMD',
				'numeric' => '060',
				'exp' => 2,
				'country' => 'BM',
			),
			array(
				'name' => 'Brunei Dollar',
				'alpha3' => 'BND',
				'numeric' => '096',
				'exp' => 2,
				'country' => 'BN',
			),
			array(
				'name' => 'Boliviano',
				'alpha3' => 'BOB',
				'numeric' => '068',
				'exp' => 2,
				'country' => 'BO',
			),
			array(
				'name' => 'Brazilian Real',
				'alpha3' => 'BRL',
				'numeric' => '986',
				'exp' => 2,
				'country' => 'BR',
			),
			array(
				'name' => 'Bahamian Dollar',
				'alpha3' => 'BSD',
				'numeric' => '044',
				'exp' => 2,
				'country' => 'BS',
			),
			array(
				'name' => 'Bhutanese Ngultrum',
				'alpha3' => 'BTN',
				'numeric' => '064',
				'exp' => 2,
				'country' => 'BT',
			),
			array(
				'name' => 'Botswana Pula',
				'alpha3' => 'BWP',
				'numeric' => '072',
				'exp' => 2,
				'country' => array(
					'BW',
					'ZW',
				),
			),
			array(
				'name' => 'Belarussian Ruble',
				'alpha3' => 'BYR',
				'numeric' => '974',
				'exp' => 0,
				'country' => 'BY',
			),
			array(
				'name' => 'Belize Dollar',
				'alpha3' => 'BZD',
				'numeric' => '084',
				'exp' => 2,
				'country' => 'BZ',
			),
			array(
				'name' => 'Canadian Dollar',
				'alpha3' => 'CAD',
				'numeric' => '124',
				'exp' => 2,
				'country' => 'CA',
			),
			array(
				'name' => 'Congolese Franc',
				'alpha3' => 'CDF',
				'numeric' => '976',
				'exp' => 2,
				'country' => 'CD',
			),
			array(
				'name' => 'Swiss Franc',
				'alpha3' => 'CHF',
				'numeric' => '756',
				'exp' => 2,
				'country' => array(
					'CH',
					'LI',
				),
			),
			array(
				'name' => 'Chilean Peso',
				'alpha3' => 'CLP',
				'numeric' => '152',
				'exp' => 0,
				'country' => 'CL',
			),
			array(
				'name' => 'Chinese Yuan',
				'alpha3' => 'CNY',
				'numeric' => '156',
				'exp' => 2,
				'country' => 'CN',
			),
			array(
				'name' => 'Colombian Peso',
				'alpha3' => 'COP',
				'numeric' => '170',
				'exp' => 2,
				'country' => 'CO',
			),
			array(
				'name' => 'Costa Rican Colon',
				'alpha3' => 'CRC',
				'numeric' => '188',
				'exp' => 2,
				'country' => 'CR',
			),
			array(
				'name' => 'Cuban Convertible Peso',
				'alpha3' => 'CUC',
				'numeric' => '931',
				'exp' => 2,
				'country' => 'CU',
			),
			array(
				'name' => 'Cuban Peso',
				'alpha3' => 'CUP',
				'numeric' => '192',
				'exp' => 2,
				'country' => 'CU',
			),
			array(
				'name' => 'Cape Verde Escudo',
				'alpha3' => 'CVE',
				'numeric' => '132',
				'exp' => 2,
				'country' => 'CV',
			),
			array(
				'name' => 'Czech Koruna',
				'alpha3' => 'CZK',
				'numeric' => '203',
				'exp' => 2,
				'country' => 'CZ',
			),
			array(
				'name' => 'Djiboutian Franc',
				'alpha3' => 'DJF',
				'numeric' => '262',
				'exp' => 0,
				'country' => 'DJ',
			),
			array(
				'name' => 'Danish Krone',
				'alpha3' => 'DKK',
				'numeric' => '208',
				'exp' => 2,
				'country' => array(
					'DK',
					'FO',
					'GL',
				),
			),
			array(
				'name' => 'Dominican Peso',
				'alpha3' => 'DOP',
				'numeric' => '214',
				'exp' => 2,
				'country' => 'DO',
			),
			array(
				'name' => 'Algerian Dinar',
				'alpha3' => 'DZD',
				'numeric' => '012',
				'exp' => 2,
				'country' => 'DZ',
			),
			array(
				'name' => 'Egyptian Pound',
				'alpha3' => 'EGP',
				'numeric' => '818',
				'exp' => 2,
				'country' => 'EG',
			),
			array(
				'name' => 'Eritrean Nakfa',
				'alpha3' => 'ERN',
				'numeric' => '232',
				'exp' => 2,
				'country' => 'ER',
			),
			array(
				'name' => 'Ethiopian Birr',
				'alpha3' => 'ETB',
				'numeric' => '230',
				'exp' => 2,
				'country' => 'ET',
			),
			array(
				'name' => 'Euro',
				'alpha3' => 'EUR',
				'numeric' => '978',
				'exp' => 2,
				'country' => array(
					'AD',
					'AT',
					'AX',
					'BE',
					'BL',
					'CY',
					'DE',
					'ES',
					'FI',
					'FR',
					'GF',
					'GP',
					'GR',
					'IE',
					'IT',
					'LT',
					'LU',
					'MC',
					'ME',
					'MF',
					'MQ',
					'MT',
					'NL',
					'PM',
					'PT',
					'RE',
					'SI',
					'SK',
					'SM',
					'TF',
					'VA',
					'YT',
					'ZW',
				),
			),
			array(
				'name' => 'Fiji Dollar',
				'alpha3' => 'FJD',
				'numeric' => '242',
				'exp' => 2,
				'country' => 'FJ',
			),
			array(
				'name' => 'Falkland Islands Pound',
				'alpha3' => 'FKP',
				'numeric' => '238',
				'exp' => 2,
				'country' => 'FK',
			),
			array(
				'name' => 'Pound Sterling',
				'alpha3' => 'GBP',
				'numeric' => '826',
				'exp' => 2,
				'country' => array(
					'GB',
					'GG',
					'GS',
					'IM',
					'IO',
					'JE',
					'ZW',
				),
			),
			array(
				'name' => 'Georgian Lari',
				'alpha3' => 'GEL',
				'numeric' => '981',
				'exp' => 2,
				'country' => 'GE',
			),
			array(
				'name' => 'Ghanaian Cedi',
				'alpha3' => 'GHS',
				'numeric' => '936',
				'exp' => 2,
				'country' => 'GH',
			),
			array(
				'name' => 'Gibraltar Pound',
				'alpha3' => 'GIP',
				'numeric' => '292',
				'exp' => 2,
				'country' => 'GI',
			),
			array(
				'name' => 'Gambian Dalasi',
				'alpha3' => 'GMD',
				'numeric' => '270',
				'exp' => 2,
				'country' => 'GM',
			),
			array(
				'name' => 'Guinean Franc',
				'alpha3' => 'GNF',
				'numeric' => '324',
				'exp' => 0,
				'country' => 'GN',
			),
			array(
				'name' => 'Guatemalan Quetzal',
				'alpha3' => 'GTQ',
				'numeric' => '320',
				'exp' => 2,
				'country' => 'GT',
			),
			array(
				'name' => 'Guyanese Dollar',
				'alpha3' => 'GYD',
				'numeric' => '328',
				'exp' => 2,
				'country' => 'GY',
			),
			array(
				'name' => 'Hong Kong Dollar',
				'alpha3' => 'HKD',
				'numeric' => '344',
				'exp' => 2,
				'country' => 'HK',
			),
			array(
				'name' => 'Honduran Lempira',
				'alpha3' => 'HNL',
				'numeric' => '340',
				'exp' => 2,
				'country' => 'HN',
			),
			array(
				'name' => 'Kuna',
				'alpha3' => 'HRK',
				'numeric' => '191',
				'exp' => 2,
				'country' => 'HR',
			),
			array(
				'name' => 'Haitian Gourde',
				'alpha3' => 'HTG',
				'numeric' => '332',
				'exp' => 2,
				'country' => 'HT',
			),
			array(
				'name' => 'Hungarian Forint',
				'alpha3' => 'HUF',
				'numeric' => '348',
				'exp' => 2,
				'country' => 'HU',
			),
			array(
				'name' => 'Indonesian Rupiah',
				'alpha3' => 'IDR',
				'numeric' => '360',
				'exp' => 2,
				'country' => 'ID',
			),
			array(
				'name' => 'Israeli New Sheqel',
				'alpha3' => 'ILS',
				'numeric' => '376',
				'exp' => 2,
				'country' => array(
					'IL',
					'PS',
				),
			),
			array(
				'name' => 'Indian Rupee',
				'alpha3' => 'INR',
				'numeric' => '356',
				'exp' => 2,
				'country' => 'IN',
			),
			array(
				'name' => 'Iraqi Dinar',
				'alpha3' => 'IQD',
				'numeric' => '368',
				'exp' => 3,
				'country' => 'IQ',
			),
			array(
				'name' => 'Iranian Rial',
				'alpha3' => 'IRR',
				'numeric' => '364',
				'exp' => 2,
				'country' => 'IR',
			),
			array(
				'name' => 'Icelandic Króna',
				'alpha3' => 'ISK',
				'numeric' => '352',
				'exp' => 0,
				'country' => 'IS',
			),
			array(
				'name' => 'Jamaican Dollar',
				'alpha3' => 'JMD',
				'numeric' => '388',
				'exp' => 2,
				'country' => 'JM',
			),
			array(
				'name' => 'Jordanian Dinar',
				'alpha3' => 'JOD',
				'numeric' => '400',
				'exp' => 3,
				'country' => 'JO',
			),
			array(
				'name' => 'Japanese Yen',
				'alpha3' => 'JPY',
				'numeric' => '392',
				'exp' => 0,
				'country' => 'JP',
			),
			array(
				'name' => 'Kenyan Shilling',
				'alpha3' => 'KES',
				'numeric' => '404',
				'exp' => 2,
				'country' => 'KE',
			),
			array(
				'name' => 'Kyrgyzstani Som',
				'alpha3' => 'KGS',
				'numeric' => '417',
				'exp' => 2,
				'country' => 'KG',
			),
			array(
				'name' => 'Cambodian Riel',
				'alpha3' => 'KHR',
				'numeric' => '116',
				'exp' => 2,
				'country' => 'KH',
			),
			array(
				'name' => 'Comoro Franc',
				'alpha3' => 'KMF',
				'numeric' => '174',
				'exp' => 0,
				'country' => 'KM',
			),
			array(
				'name' => 'North Korean Won',
				'alpha3' => 'KPW',
				'numeric' => '408',
				'exp' => 2,
				'country' => 'KP',
			),
			array(
				'name' => 'South Korean Won',
				'alpha3' => 'KRW',
				'numeric' => '410',
				'exp' => 0,
				'country' => 'KR',
			),
			array(
				'name' => 'Kuwaiti Dinar',
				'alpha3' => 'KWD',
				'numeric' => '414',
				'exp' => 3,
				'country' => 'KW',
			),
			array(
				'name' => 'Cayman Islands Dollar',
				'alpha3' => 'KYD',
				'numeric' => '136',
				'exp' => 2,
				'country' => 'KY',
			),
			array(
				'name' => 'Kazakhstani Tenge',
				'alpha3' => 'KZT',
				'numeric' => '398',
				'exp' => 2,
				'country' => 'KZ',
			),
			array(
				'name' => 'Lao Kip',
				'alpha3' => 'LAK',
				'numeric' => '418',
				'exp' => 2,
				'country' => 'LA',
			),
			array(
				'name' => 'Lebanese Pound',
				'alpha3' => 'LBP',
				'numeric' => '422',
				'exp' => 2,
				'country' => 'LB',
			),
			array(
				'name' => 'Sri Lankan Rupee',
				'alpha3' => 'LKR',
				'numeric' => '144',
				'exp' => 2,
				'country' => 'LK',
			),
			array(
				'name' => 'Liberian Dollar',
				'alpha3' => 'LRD',
				'numeric' => '430',
				'exp' => 2,
				'country' => 'LR',
			),
			array(
				'name' => 'Lesotho Loti',
				'alpha3' => 'LSL',
				'numeric' => '426',
				'exp' => 2,
				'country' => 'LS',
			),
			array(
				'name' => 'Latvian Lats',
				'alpha3' => 'LVL',
				'numeric' => '428',
				'exp' => 2,
				'country' => 'LV',
			),
			array(
				'name' => 'Libyan Dinar',
				'alpha3' => 'LYD',
				'numeric' => '434',
				'exp' => 3,
				'country' => 'LY',
			),
			array(
				'name' => 'Moroccan Dirham',
				'alpha3' => 'MAD',
				'numeric' => '504',
				'exp' => 2,
				'country' => array(
					'EH',
					'MA',
				),
			),
			array(
				'name' => 'Moldovan Leu',
				'alpha3' => 'MDL',
				'numeric' => '498',
				'exp' => 2,
				'country' => 'MD',
			),
			array(
				'name' => 'Malagasy Ariary',
				'alpha3' => 'MGA',
				'numeric' => '969',
				'exp' => 0,
				'country' => 'MG',
			),
			array(
				'name' => 'Macedonian Denar',
				'alpha3' => 'MKD',
				'numeric' => '807',
				'exp' => 2,
				'country' => 'MK',
			),
			array(
				'name' => 'Myanmar Kyat',
				'alpha3' => 'MMK',
				'numeric' => '104',
				'exp' => 2,
				'country' => 'MM',
			),
			array(
				'name' => 'Mongolian Tugrik',
				'alpha3' => 'MNT',
				'numeric' => '496',
				'exp' => 2,
				'country' => 'MN',
			),
			array(
				'name' => 'Macanese Pataca',
				'alpha3' => 'MOP',
				'numeric' => '446',
				'exp' => 2,
				'country' => 'MO',
			),
			array(
				'name' => 'Mauritanian Ouguiya',
				'alpha3' => 'MRO',
				'numeric' => '478',
				'exp' => 0,
				'country' => 'MR',
			),
			array(
				'name' => 'Mauritian Rupee',
				'alpha3' => 'MUR',
				'numeric' => '480',
				'exp' => 2,
				'country' => 'MU',
			),
			array(
				'name' => 'Maldivian Rufiyaa',
				'alpha3' => 'MVR',
				'numeric' => '462',
				'exp' => 2,
				'country' => 'MV',
			),
			array(
				'name' => 'Malawian Kwacha',
				'alpha3' => 'MWK',
				'numeric' => '454',
				'exp' => 2,
				'country' => 'MW',
			),
			array(
				'name' => 'Mexican Peso',
				'alpha3' => 'MXN',
				'numeric' => '484',
				'exp' => 2,
				'country' => 'MX',
			),
			array(
				'name' => 'Malaysian Ringgit',
				'alpha3' => 'MYR',
				'numeric' => '458',
				'exp' => 2,
				'country' => 'MY',
			),
			array(
				'name' => 'Mozambican Metical',
				'alpha3' => 'MZN',
				'numeric' => '943',
				'exp' => 2,
				'country' => 'MZ',
			),
			array(
				'name' => 'Namibian Dollar',
				'alpha3' => 'NAD',
				'numeric' => '516',
				'exp' => 2,
				'country' => 'NA',
			),
			array(
				'name' => 'Nigerian Naira',
				'alpha3' => 'NGN',
				'numeric' => '566',
				'exp' => 2,
				'country' => 'NG',
			),
			array(
				'name' => 'Nicaraguan Córdoba',
				'alpha3' => 'NIO',
				'numeric' => '558',
				'exp' => 2,
				'country' => 'NI',
			),
			array(
				'name' => 'Norwegian Krone',
				'alpha3' => 'NOK',
				'numeric' => '578',
				'exp' => 2,
				'country' => array(
					'AQ',
					'BV',
					'NO',
					'SJ',
				),
			),
			array(
				'name' => 'Nepalese Rupee',
				'alpha3' => 'NPR',
				'numeric' => '524',
				'exp' => 2,
				'country' => 'NP',
			),
			array(
				'name' => 'New Zealand Dollar',
				'alpha3' => 'NZD',
				'numeric' => '554',
				'exp' => 2,
				'country' => array(
					'CK',
					'NU',
					'NZ',
					'PN',
					'TK',
				),
			),
			array(
				'name' => 'Omani Rial',
				'alpha3' => 'OMR',
				'numeric' => '512',
				'exp' => 3,
				'country' => 'OM',
			),
			array(
				'name' => 'Panamanian Balboa',
				'alpha3' => 'PAB',
				'numeric' => '590',
				'exp' => 2,
				'country' => 'PA',
			),
			array(
				'name' => 'Peruvian Nuevo Sol',
				'alpha3' => 'PEN',
				'numeric' => '604',
				'exp' => 2,
				'country' => 'PE',
			),
			array(
				'name' => 'Papua New Guinean Kina',
				'alpha3' => 'PGK',
				'numeric' => '598',
				'exp' => 2,
				'country' => 'PG',
			),
			array(
				'name' => 'Philippine Peso',
				'alpha3' => 'PHP',
				'numeric' => '608',
				'exp' => 2,
				'country' => 'PH',
			),
			array(
				'name' => 'Pakistani Rupee',
				'alpha3' => 'PKR',
				'numeric' => '586',
				'exp' => 2,
				'country' => 'PK',
			),
			array(
				'name' => 'Polish Zloty',
				'alpha3' => 'PLN',
				'numeric' => '985',
				'exp' => 2,
				'country' => 'PL',
			),
			array(
				'name' => 'Paraguayan Guarani',
				'alpha3' => 'PYG',
				'numeric' => '600',
				'exp' => 0,
				'country' => 'PY',
			),
			array(
				'name' => 'Qatari Rial',
				'alpha3' => 'QAR',
				'numeric' => '634',
				'exp' => 2,
				'country' => 'QA',
			),
			array(
				'name' => 'Romanian Leu',
				'alpha3' => 'RON',
				'numeric' => '946',
				'exp' => 2,
				'country' => 'RO',
			),
			array(
				'name' => 'Serbian Dinar',
				'alpha3' => 'RSD',
				'numeric' => '941',
				'exp' => 0,
				'country' => 'RS',
			),
			array(
				'name' => 'Russian Ruble',
				'alpha3' => 'RUB',
				'numeric' => '643',
				'exp' => 2,
				'country' => 'RU',
			),
			array(
				'name' => 'Rwandan Franc',
				'alpha3' => 'RWF',
				'numeric' => '646',
				'exp' => 0,
				'country' => 'RW',
			),
			array(
				'name' => 'Saudi Riyal',
				'alpha3' => 'SAR',
				'numeric' => '682',
				'exp' => 2,
				'country' => 'SA',
			),
			array(
				'name' => 'Solomon Islands Dollar',
				'alpha3' => 'SBD',
				'numeric' => '090',
				'exp' => 2,
				'country' => 'SB',
			),
			array(
				'name' => 'Seychelles Rupee',
				'alpha3' => 'SCR',
				'numeric' => '690',
				'exp' => 2,
				'country' => 'SC',
			),
			array(
				'name' => 'Sudanese Pound',
				'alpha3' => 'SDG',
				'numeric' => '938',
				'exp' => 2,
				'country' => 'SD',
			),
			array(
				'name' => 'Swedish Krona',
				'alpha3' => 'SEK',
				'numeric' => '752',
				'exp' => 2,
				'country' => 'SE',
			),
			array(
				'name' => 'Singapore Dollar',
				'alpha3' => 'SGD',
				'numeric' => '702',
				'exp' => 2,
				'country' => array(
					'BN',
					'SG',
				),
			),
			array(
				'name' => 'Saint Helena Pound',
				'alpha3' => 'SHP',
				'numeric' => '654',
				'exp' => 2,
				'country' => 'SH',
			),
			array(
				'name' => 'Sierra Leonean Leone',
				'alpha3' => 'SLL',
				'numeric' => '694',
				'exp' => 2,
				'country' => 'SL',
			),
			array(
				'name' => 'Somali Shilling',
				'alpha3' => 'SOS',
				'numeric' => '706',
				'exp' => 2,
				'country' => 'SO',
			),
			array(
				'name' => 'Surinamese Dollar',
				'alpha3' => 'SRD',
				'numeric' => '968',
				'exp' => 2,
				'country' => 'SR',
			),
			array(
				'name' => 'South Sudanese Pound',
				'alpha3' => 'SSP',
				'numeric' => '728',
				'exp' => 2,
				'country' => 'SS',
			),
			array(
				'name' => 'São Tomé and Principe Dobra',
				'alpha3' => 'STD',
				'numeric' => '678',
				'exp' => 2,
				'country' => 'ST',
			),
			array(
				'name' => 'Syrian Pound',
				'alpha3' => 'SYP',
				'numeric' => '760',
				'exp' => 2,
				'country' => 'SY',
			),
			array(
				'name' => 'Swazi Lilangeni',
				'alpha3' => 'SZL',
				'numeric' => '748',
				'exp' => 2,
				'country' => 'SZ',
			),
			array(
				'name' => 'Thai Baht',
				'alpha3' => 'THB',
				'numeric' => '764',
				'exp' => 2,
				'country' => 'TH',
			),
			array(
				'name' => 'Tajikistani Somoni',
				'alpha3' => 'TJS',
				'numeric' => '972',
				'exp' => 2,
				'country' => 'TJ',
			),
			array(
				'name' => 'Turkmenistani Manat',
				'alpha3' => 'TMT',
				'numeric' => '934',
				'exp' => 2,
				'country' => 'TM',
			),
			array(
				'name' => 'Tunisian Dinar',
				'alpha3' => 'TND',
				'numeric' => '788',
				'exp' => 3,
				'country' => 'TN',
			),
			array(
				'name' => 'Tongan Paʻanga',
				'alpha3' => 'TOP',
				'numeric' => '776',
				'exp' => 2,
				'country' => 'TO',
			),
			array(
				'name' => 'Turkish Lira',
				'alpha3' => 'TRY',
				'numeric' => '949',
				'exp' => 2,
				'country' => 'TR',
			),
			array(
				'name' => 'Trinidad and Tobago Dollar',
				'alpha3' => 'TTD',
				'numeric' => '780',
				'exp' => 2,
				'country' => 'TT',
			),
			array(
				'name' => 'New Taiwan Dollar',
				'alpha3' => 'TWD',
				'numeric' => '901',
				'exp' => 2,
				'country' => 'TW',
			),
			array(
				'name' => 'Tanzanian Shilling',
				'alpha3' => 'TZS',
				'numeric' => '834',
				'exp' => 2,
				'country' => 'TZ',
			),
			array(
				'name' => 'Ukrainian Hryvnia',
				'alpha3' => 'UAH',
				'numeric' => '980',
				'exp' => 2,
				'country' => 'UA',
			),
			array(
				'name' => 'Ugandan Shilling',
				'alpha3' => 'UGX',
				'numeric' => '800',
				'exp' => 0,
				'country' => 'UG',
			),
			array(
				'name' => 'US Dollar',
				'alpha3' => 'USD',
				'numeric' => '840',
				'exp' => 2,
				'country' => array(
					'AS',
					'BQ',
					'EC',
					'FM',
					'GU',
					'MF',
					'MH',
					'MP',
					'PR',
					'PW',
					'SV',
					'TC',
					'TL',
					'UM',
					'US',
					'VG',
					'VI',
					'ZW',
				),
			),
			array(
				'name' => 'Uruguayan Peso',
				'alpha3' => 'UYU',
				'numeric' => '858',
				'exp' => 2,
				'country' => 'UY',
			),
			array(
				'name' => 'Uzbekistan Som',
				'alpha3' => 'UZS',
				'numeric' => '860',
				'exp' => 2,
				'country' => 'UZ',
			),
			array(
				'name' => 'Venezuelan Bolivar',
				'alpha3' => 'VEF',
				'numeric' => '937',
				'exp' => 2,
				'country' => 'VE',
			),
			array(
				'name' => 'Vietnamese Dong',
				'alpha3' => 'VND',
				'numeric' => '704',
				'exp' => 0,
				'country' => 'VN',
			),
			array(
				'name' => 'Vanuatu Vatu',
				'alpha3' => 'VUV',
				'numeric' => '548',
				'exp' => 0,
				'country' => 'VU',
			),
			array(
				'name' => 'Samoan Tala',
				'alpha3' => 'WST',
				'numeric' => '882',
				'exp' => 2,
				'country' => 'WS',
			),
			array(
				'name' => 'CFA Franc BEAC',
				'alpha3' => 'XAF',
				'numeric' => '950',
				'exp' => 0,
				'country' => array(
					'CF',
					'CG',
					'CM',
					'GA',
					'GQ',
					'TD',
				),
			),
			array(
				'name' => 'East Caribbean Dollar',
				'alpha3' => 'XCD',
				'numeric' => '951',
				'exp' => 2,
				'country' => array(
					'AG',
					'AI',
					'DM',
					'GD',
					'KN',
					'LC',
					'MS',
					'VC',
				),
			),
			array(
				'name' => 'CFA Franc BCEAO',
				'alpha3' => 'XOF',
				'numeric' => '952',
				'exp' => 0,
				'country' => array(
					'BJ',
					'BF',
					'CI',
					'GW',
					'ML',
					'NE',
					'SN',
					'TG',
				),
			),
			array(
				'name' => 'CFP Franc',
				'alpha3' => 'XPF',
				'numeric' => '953',
				'exp' => 0,
				'country' => array(
					'NC',
					'PF',
					'WF',
				),
			),
			array(
				'name' => 'Yemeni Rial',
				'alpha3' => 'YER',
				'numeric' => '886',
				'exp' => 2,
				'country' => 'YE',
			),
			array(
				'name' => 'South African Rand',
				'alpha3' => 'ZAR',
				'numeric' => '710',
				'exp' => 2,
				'country' => array(
					'NA',
					'LS',
					'SZ',
					'ZA',
					'ZW',
				),
			),
			array(
				'name' => 'Zambian Kwacha',
				'alpha3' => 'ZMW',
				'numeric' => '967',
				'exp' => 2,
				'country' => 'ZM',
			),
		);
		
		return $currencies;
		
	}
	
	
	/**
	 * Quote and invoice currency exponent value (see zero-decimal currencies)
	 *
	 * @since  2.875
	 */
	public static function currency_exponent( $currency_code ) {
		
		$currencies = Sliced_Shared::currencies();
		foreach ( $currencies as $currency ) {
			if ( $currency['alpha3'] === $currency_code ) {
				return $currency['exp'];
			}
		}
		
		// if not found, pow(10,0) leaves it as is
		return 0;
		
	}
	
	
	/**
	 * Quote and invoice currency options
	 *
	 * @since  2.875
	 */
	public static function currency_options() {
		
		$currencies = Sliced_Shared::currencies();
		$options = array( 'default' => 'Default Currency' );
		foreach ( $currencies as $currency ) {
			$options[ $currency['alpha3'] ] = $currency['alpha3'] . ' - ' . $currency['name'];
		}
		
		return ( $options );
		
	}
	
	/**
	 * Convert a quote to an invoice.
	 *
	 * @version 3.9.4
	 * @since   3.9.0
	 */
	public static function convert_quote_to_invoice( $id ) {
		
		$settings_invoices = get_option( 'sliced_invoices' );
		
		// convert
		$new_slug = '';
		$old_post = get_post( $id );
		if ( $old_post ) {
			$new_slug = sanitize_title( $old_post->post_title );
		}
		wp_update_post( array(
			'ID'             => $id,
			'post_type'      => 'sliced_invoice',
			'post_name'      => $new_slug,
			'comment_status' => 'closed',
		) );
		
		// update the appropriate post meta
		$number  = sliced_get_next_invoice_number();
		$payment = sliced_get_accepted_payment_methods();
		update_post_meta( $id, '_sliced_invoice_terms', $settings_invoices['terms'] );
		update_post_meta( $id, '_sliced_invoice_created', time() );
		update_post_meta( $id, '_sliced_invoice_number', $number );
		update_post_meta( $id, '_sliced_invoice_prefix', sliced_get_invoice_prefix() );
		update_post_meta( $id, '_sliced_invoice_suffix', sliced_get_invoice_suffix() );
		update_post_meta( $id, '_sliced_number', sliced_get_invoice_prefix() . $number . sliced_get_invoice_suffix() );
		update_post_meta( $id, '_sliced_payment_methods', array_keys( $payment ) );
		update_post_meta( $id, '_sliced_invoice_due', Sliced_Invoice::get_auto_due_date() );
		delete_post_meta( $id, '_sliced_quote_created' );
		delete_post_meta( $id, '_sliced_quote_number' );
		delete_post_meta( $id, '_sliced_quote_prefix' );
		delete_post_meta( $id, '_sliced_quote_suffix' );
		delete_post_meta( $id, '_sliced_quote_terms' );
		
		// update the invoice number
		Sliced_Invoice::update_invoice_number( $id );
		
		// set the status as draft
		wp_set_object_terms( $id, null, 'quote_status' ); // clear old status
		Sliced_Invoice::set_as_draft( $id ); // set new status
		
	}
	
	/**
	 * Create a new invoice from a quote.
	 *
	 * @version 3.9.4
	 * @since   3.9.0
	 */
	public static function create_invoice_from_quote( $id ) {
		global $wpdb;
		
		$settings_invoices = get_option( 'sliced_invoices' );
		
		// duplicate post
		$post = get_post( $id );
		$args = array(
			'comment_status' => 'closed',
			'ping_status'    => $post->ping_status,
			'post_author'    => $post->post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'publish',
			'post_title'     => $post->post_title,
			'post_type'      => 'sliced_invoice',
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);
		$new_post_id = wp_insert_post( $args );
		
		// get all current post terms and set them to the new post draft
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
		}
		
		// duplicate post metas
		$post_metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d",
				$id
			)
		);
		if ( $post_metas && count( $post_metas ) ) {
			$sql_query = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ";
			$sql_values = array();
			foreach ( $post_metas as $post_meta ) {
				$meta_key = esc_sql( $post_meta->meta_key );
				$meta_value = esc_sql( $post_meta->meta_value );
				$sql_values[]= "($new_post_id, '$meta_key', '$meta_value')";
			}
			$sql_query .= implode( ',', $sql_values );
			$wpdb->query( $sql_query );
		}
		
		// update the appropriate post meta on the new post
		$number  = sliced_get_next_invoice_number();
		$payment = sliced_get_accepted_payment_methods();
		update_post_meta( $new_post_id, '_sliced_invoice_terms', $settings_invoices['terms'] );
		update_post_meta( $new_post_id, '_sliced_invoice_created', time() );
		update_post_meta( $new_post_id, '_sliced_invoice_number', $number );
		update_post_meta( $new_post_id, '_sliced_invoice_prefix', sliced_get_invoice_prefix() );
		update_post_meta( $new_post_id, '_sliced_invoice_suffix', sliced_get_invoice_suffix() );
		update_post_meta( $new_post_id, '_sliced_number', sliced_get_invoice_prefix() . $number . sliced_get_invoice_suffix() );
		update_post_meta( $new_post_id, '_sliced_payment_methods', array_keys( $payment ) );
		update_post_meta( $new_post_id, '_sliced_invoice_due', Sliced_Invoice::get_auto_due_date() );
		delete_post_meta( $new_post_id, '_sliced_quote_created' );
		delete_post_meta( $new_post_id, '_sliced_quote_number' );
		delete_post_meta( $new_post_id, '_sliced_quote_prefix' );
		delete_post_meta( $new_post_id, '_sliced_quote_suffix' );
		delete_post_meta( $new_post_id, '_sliced_quote_terms' );
		
		// update the invoice number and set as draft
		Sliced_Invoice::update_invoice_number( $new_post_id );
		Sliced_Invoice::set_as_draft( $new_post_id );
		
		return $new_post_id;
	}
	
}

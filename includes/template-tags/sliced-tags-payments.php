<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


if ( ! function_exists( 'sliced_get_tax_amount' ) ) :

	function sliced_get_tax_amount() {
		$output = Sliced_Shared::get_tax_amount();
		return apply_filters( 'sliced_get_tax_amount', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_tax_amount_formatted' ) ) :

	function sliced_get_tax_amount_formatted() {
		$output = Sliced_Shared::get_tax_amount( 0, true );
		return apply_filters( 'sliced_get_tax_amount', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_tax_name' ) ) :

	function sliced_get_tax_name() {
		$output = Sliced_Shared::get_tax_name();
		return apply_filters( 'sliced_get_tax_name', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_currency_symbol' ) ) :

	function sliced_get_currency_symbol( $id = 0 ) {
		$output = Sliced_Shared::get_currency_symbol( $id );
		return apply_filters( 'sliced_get_currency_symbol', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_currency_position' ) ) :

	function sliced_get_currency_position() {
		$output = Sliced_Shared::get_currency_position();
		return apply_filters( 'sliced_get_currency_position', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_decimals' ) ) :

	function sliced_get_decimals() {
		$output = Sliced_Shared::get_decimals();
		return apply_filters( 'sliced_get_decimals', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_decimal_seperator' ) ) :

	function sliced_get_decimal_seperator() {
		$output = Sliced_Shared::get_decimal_seperator();
		return apply_filters( 'sliced_get_decimal_seperator', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_thousand_seperator' ) ) :

	function sliced_get_thousand_seperator() {
		$output = Sliced_Shared::get_thousand_seperator();
		return apply_filters( 'sliced_get_thousand_seperator', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_business_bank' ) ) :

	function sliced_get_business_bank() {
		$business = Sliced_Shared::get_business_details();
		return apply_filters( 'sliced_get_business_bank', $business['bank'], $business );
	}

endif;


if ( ! function_exists( 'sliced_get_business_generic_payment' ) ) :

	function sliced_get_business_generic_payment() {
		$business = Sliced_Shared::get_business_details();
		return apply_filters( 'sliced_get_business_generic_payment', $business['generic_pay'], $business );
	}

endif;


if ( ! function_exists( 'sliced_get_accepted_payment_methods' ) ) :

	function sliced_get_accepted_payment_methods() {
		$output = Sliced_Shared::get_accepted_payment_methods();
		return apply_filters( 'sliced_get_accepted_payment_methods', $output );
	}

endif;


if ( ! function_exists( 'sliced_is_payment_method' ) ) :

	function sliced_is_payment_method( $method = array() ) {
		$id = Sliced_Shared::get_item_id();
		$methods = Sliced_Invoice::get_payment_methods( $id );
		$output = false;
		if( ! empty( $methods[0] ) ) {
			if( in_array( $method, $methods[0] ) ) {
				$output = true;
			}
		}
		return apply_filters( 'sliced_is_payment_method', $output );
	}

endif;


if ( ! function_exists( 'sliced_get_total' ) ) :

	function sliced_get_total( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_total( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_total( $id );
		}
		return apply_filters( 'sliced_get_total', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_sub_total' ) ) :

	function sliced_get_sub_total( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_sub_total( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_sub_total( $id );
		}
		return apply_filters( 'sliced_get_sub_total', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_tax_total' ) ) :

	function sliced_get_tax_total( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_tax( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_tax( $id );
		}
		return apply_filters( 'sliced_get_tax_total', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_last_payment_amount' ) ) :

	function sliced_get_last_payment_amount( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$output = __( 'N/A', 'sliced-invoices' );
		$payments = get_post_meta( $id, '_sliced_payment', true );
		if ( $payments ) {
			$last_payment = end( $payments );
			$total_raw = Sliced_Shared::get_raw_number( $last_payment['amount'] );
			$output = Sliced_Shared::get_formatted_currency( $total_raw, $id );
		}
		return apply_filters( 'sliced_get_last_payment_amount', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_balance_outstanding' ) ) :

	function sliced_get_balance_outstanding( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$totals = Sliced_Shared::get_totals( $id );
		$balance = $totals['total'] - $totals['payments'];
		$output = Sliced_Shared::get_formatted_currency( $balance, $id );
		return apply_filters( 'sliced_get_balance_outstanding', $output, $id );
	}

endif;

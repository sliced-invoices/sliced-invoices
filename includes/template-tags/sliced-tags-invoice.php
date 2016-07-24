<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists( 'sliced_get_invoice_label' ) ) :

	function sliced_get_invoice_label() {
		$translate = get_option( 'sliced_translate' );
		$label = isset( $translate['invoice-label'] ) ? $translate['invoice-label'] : __( 'Invoice', 'sliced-invoices');
		return apply_filters( 'sliced_get_invoice_label', $label );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_label_plural' ) ) :

	function sliced_get_invoice_label_plural() {
		$translate = get_option( 'sliced_translate' );
		$label = isset( $translate['invoice-label-plural'] ) ? $translate['invoice-label-plural'] : __( 'Invoices', 'sliced-invoices');
		return apply_filters( 'sliced_get_invoice_label_plural', $label );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_id' ) ) :

	function sliced_get_invoice_id() {
		$id = Sliced_Shared::get_item_id();
		return apply_filters( 'sliced_get_invoice_id', $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_number' ) ) :

	function sliced_get_invoice_number( $id = 0 ) {
		$output = Sliced_Invoice::get_number( $id );
		return apply_filters( 'sliced_get_invoice_number', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_invoice_prefix' ) ) :

	function sliced_get_invoice_prefix( $id = 0 ) {
		$output = Sliced_Invoice::get_prefix( $id );
		return apply_filters( 'sliced_get_invoice_prefix', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_next_invoice_number' ) ) :

	function sliced_get_next_invoice_number() {
		$output = Sliced_Invoice::get_next_invoice_number();
		return apply_filters( 'sliced_get_next_invoice_number', $output);
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_order_number' ) ) :

	function sliced_get_invoice_order_number( $id = 0 ) {
		$output = Sliced_Invoice::get_order_number( $id );
		return apply_filters( 'sliced_get_invoice_order_number', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_created' ) ) :

	function sliced_get_invoice_created( $id = 0 ) {
		$output = Sliced_Invoice::get_created_date( $id );
		return apply_filters( 'sliced_get_invoice_created', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_due' ) ) :

	function sliced_get_invoice_due( $id = 0 ) {
		$output = Sliced_Invoice::get_due_date( $id );
		return apply_filters( 'sliced_get_invoice_due', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_status' ) ) :

	function sliced_get_invoice_status( $id = 0, $type = 'invoice' ) {
		$output = Sliced_Shared::get_status( $id, $type );
		return apply_filters( 'sliced_get_invoice_status', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_line_items' ) ) :

	function sliced_get_invoice_line_items( $id = 0 ) {
		$output = Sliced_Shared::get_line_items( $id );
		return apply_filters( 'sliced_get_invoice_line_items', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_sub_total' ) ) :

	function sliced_get_invoice_sub_total( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$sub_total = Sliced_Shared::get_formatted_currency( $output['sub_total'] );
		return apply_filters( 'sliced_get_invoice_sub_total', $sub_total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_total' ) ) :

	function sliced_get_invoice_total( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$total = Sliced_Shared::get_formatted_currency( $output['total'] );
		return apply_filters( 'sliced_get_invoice_total', $total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_tax' ) ) :

	function sliced_get_invoice_tax( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$tax = Sliced_Shared::get_formatted_currency( $output['tax'] );
		return apply_filters( 'sliced_get_invoice_tax', $tax, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_sub_total_raw' ) ) :

	function sliced_get_invoice_sub_total_raw( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$sub_total = round( $output['sub_total'], sliced_get_decimals() );
		return apply_filters( 'sliced_get_invoice_sub_total_raw', $sub_total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_total_raw' ) ) :

	function sliced_get_invoice_total_raw( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$total = round( $output['total'], sliced_get_decimals());
		return apply_filters( 'sliced_get_invoice_total_raw', $total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_tax_raw' ) ) :

	function sliced_get_invoice_tax_raw( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$tax = round( $output['tax'], sliced_get_decimals());
		return apply_filters( 'sliced_get_invoice_tax_raw', $tax, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_description' ) ) :

	function sliced_get_invoice_description( $id = 0 ) {
		$output = Sliced_Invoice::get_description( $id );
		return apply_filters( 'sliced_get_invoice_description', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_terms' ) ) :

	function sliced_get_invoice_terms( $id = 0 ) {
		$output = Sliced_Invoice::get_terms( $id );
		return apply_filters( 'sliced_get_invoice_terms', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_invoice_payment_methods' ) ) :

	function sliced_get_invoice_payment_methods( $id = 0 ) {
		$output = Sliced_Invoice::get_payment_methods( $id );
		return apply_filters( 'sliced_get_invoice_payment_methods', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_invoice_deposit' ) ) :

	function sliced_get_invoice_deposit( $id = 0 ) {
		$output = Sliced_Invoice::get_deposit( $id );
		return apply_filters( 'sliced_get_invoice_deposit', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_invoice_template' ) ) :

	function sliced_get_invoice_template( $id = 0 ) {
		$output = Sliced_Invoice::get_template( $id );
		return apply_filters( 'sliced_get_invoice_template', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_css' ) ) :

	function sliced_get_invoice_css( $id = 0 ) {
		$output = Sliced_Invoice::get_css( $id );
		return apply_filters( 'sliced_get_invoice_css', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_invoice_watermark' ) ) :

	function sliced_get_invoice_watermark( $id = 0 ) {
		$output = Sliced_Invoice::get_invoice_watermark( $id );
		return apply_filters( 'sliced_get_invoice_watermark', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_invoice_hide_adjust_field' ) ) :

	function sliced_invoice_hide_adjust_field() {
		$output = Sliced_Invoice::hide_adjustment_field();
		return apply_filters( 'sliced_invoice_hide_adjust_field', $output );
	}

endif;

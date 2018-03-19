<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists( 'sliced_get_quote_label' ) ) :

	function sliced_get_quote_label() {
		$translate = get_option( 'sliced_translate' );
		$label = isset( $translate['quote-label'] ) ? $translate['quote-label'] : __( 'Quote', 'sliced-invoices');
		return apply_filters( 'sliced_get_quote_label', $label );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_label_plural' ) ) :

	function sliced_get_quote_label_plural() {
		$translate = get_option( 'sliced_translate' );
		$label = isset( $translate['quote-label-plural'] ) ? $translate['quote-label-plural'] : __( 'Quotes', 'sliced-invoices');
		return apply_filters( 'sliced_get_quote_label_plural', $label );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_id' ) ) :

	function sliced_get_quote_id() {
		$id = Sliced_Shared::get_item_id();
		return apply_filters( 'sliced_get_quote_id', $id );
	}

endif;


if ( ! function_exists( 'sliced_get_quote_number' ) ) :

	function sliced_get_quote_number( $id = 0 ) {
		$output = Sliced_Quote::get_number( $id );
		return apply_filters( 'sliced_get_quote_number', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_quote_prefix' ) ) :

	function sliced_get_quote_prefix( $id = 0 ) {
		$output = Sliced_Quote::get_prefix( $id );
		return apply_filters( 'sliced_get_quote_prefix', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_quote_suffix' ) ) :

	function sliced_get_quote_suffix( $id = 0 ) {
		$output = Sliced_Quote::get_suffix( $id );
		return apply_filters( 'sliced_get_quote_suffix', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_next_quote_number' ) ) :

	function sliced_get_next_quote_number() {
		$output = Sliced_Quote::get_next_quote_number();
		return apply_filters( 'sliced_get_next_quote_number', $output );
	}

endif;


if ( ! function_exists( 'sliced_get_quote_created' ) ) :

	function sliced_get_quote_created( $id = 0 ) {
		$output = Sliced_Quote::get_created_date( $id );
		return apply_filters( 'sliced_get_quote_created', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_valid' ) ) :

	function sliced_get_quote_valid( $id = 0 ) {
		$output = Sliced_Quote::get_valid_date( $id );
		return apply_filters( 'sliced_get_quote_valid', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_quote_status' ) ) :

	function sliced_get_quote_status( $id = 0, $type = 'quote' ) {
		$output = Sliced_Shared::get_status( $id, $type );
		return apply_filters( 'sliced_get_quote_status', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_line_items' ) ) :

	function sliced_get_quote_line_items( $id = 0 ) {
		$output = Sliced_Shared::get_line_items( $id );
		return apply_filters( 'sliced_get_quote_line_items', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_sub_total' ) ) :

	function sliced_get_quote_sub_total( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$sub_total = Sliced_Shared::get_formatted_currency( $output['sub_total'] );
		return apply_filters( 'sliced_get_quote_sub_total', $sub_total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_total' ) ) :

	function sliced_get_quote_total( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$total = Sliced_Shared::get_formatted_currency( $output['total'] );
		return apply_filters( 'sliced_get_quote_total', $total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_tax' ) ) :

	function sliced_get_quote_tax( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$tax = Sliced_Shared::get_formatted_currency( $output['tax'] );
		return apply_filters( 'sliced_get_quote_tax', $tax, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_sub_total_raw' ) ) :

	function sliced_get_quote_sub_total_raw( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$sub_total = $output['sub_total'];
		return apply_filters( 'sliced_get_quote_sub_total_raw', $sub_total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_total_raw' ) ) :

	function sliced_get_quote_total_raw( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$total = $output['total'];
		return apply_filters( 'sliced_get_quote_total_raw', $total, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_tax_raw' ) ) :

	function sliced_get_quote_tax_raw( $id = 0 ) {
		$output = Sliced_Shared::get_totals( $id );
		$tax = $output['tax'];
		return apply_filters( 'sliced_get_quote_tax_raw', $tax, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_description' ) ) :

	function sliced_get_quote_description( $id = 0 ) {
		$output = Sliced_Quote::get_description( $id );
		return apply_filters( 'sliced_get_quote_description', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_terms' ) ) :

	function sliced_get_quote_terms( $id = 0 ) {
		$output = Sliced_Quote::get_terms( $id );
		return apply_filters( 'sliced_get_quote_terms', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_footer' ) ) :

	function sliced_get_quote_footer() {
		$output = Sliced_Quote::get_footer();
		return apply_filters( 'sliced_get_quote_footer', $output );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_template' ) ) :

	function sliced_get_quote_template( $id = 0 ) {
		$output = Sliced_Quote::get_template( $id );
		return apply_filters( 'sliced_get_quote_template', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_quote_css' ) ) :

	function sliced_get_quote_css( $id = 0 ) {
		$output = Sliced_Quote::get_css( $id );
		return apply_filters( 'sliced_get_quote_css', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_quote_hide_adjust_field' ) ) :

	function sliced_quote_hide_adjust_field() {
		$output = Sliced_Quote::hide_adjustment_field();
		return apply_filters( 'sliced_quote_hide_adjust_field', $output );
	}

endif;

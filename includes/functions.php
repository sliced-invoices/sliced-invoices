<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Create a new Sliced Invoice.
 *
 * Creates a new invoice based on the data provided in $args.  Client_id and line_items are required,
 * all others are optional.  Example $args:
 *
 *     $args = array(
 *
 *                           // REQUIRED. the WordPress User ID corresponding to the Sliced Client record.
 *         'client_id'       => 123,
 *
 *                           // Optional. The WordPress User ID of the person creating the invoice. Defaults to the current user.
 *         'creator_user_id' => 1,
 *
 *                           // Optional. UNIX timestamp to be used for invoice date. Defaults to current time.
 *         'created_at'      => date( "U", strtotime( '2021-10-29 22:29:00' ) ),
 *
 *                           // Optional. 3-letter currency code. Defaults according to your settings.
 *         'currency'        => 'USD',
 *
 *                           // Optional. Content of "Description" field.
 *         'description'     => 'Description of work to be provided to John Doe.',
 *
 *                           // Optional. A discount amount to apply to the invoice. Default is 0.
 *         'discount'        => 5,
 *
 *                           // Optional. Whether the number specified in 'discount' is a fixed amount, or a percentage.
 *                           // Possible values are 'amount' and 'percentage'.  Default is 'amount'.
 *         'discount_type'   => 'amount',
 *
 *                           // Optional. Whether to apply the discount before or after tax.
 *                           // Possible values are 'before' and 'after'.  Default is 'after'.
 *         'discount_tax_treatment' => 'after',
 *
 *                           // Optional. UNIX timestamp to be used for due date. Defaults according to your settings.
 *         'due_date'        => date( "U", strtotime( '2021-11-29 22:29:00' ) ),
 *
 *                           // Optional. The invoice number. Defaults to next available number according to your settings.
 *         'number'          => 123456,
 *
 *                           // Optional. A prefix to the invoice number. Defaults according to your settings.
 *         'number_prefix'   => 'INV-',
 *
 *                           // Optional. A suffix to the invoice number. Defaults according to your settings.
 *         'number_suffix'   => '-web',
 *
 *                           // REQUIRED. the line items of the invoice, in the following format:
 *         'line_items'      => array(
 *                                  array( 
 *                                      'qty'     => '1',
 *                                      'title'   => 'Test item',
 *                                      'amount'  => '100',
 *                                      'taxable' => 'on', // taxable
 *                                  ),
 *                                  array( 
 *                                      'qty'     => '1',
 *                                      'title'   => 'Another test item',
 *                                      'amount'  => '20',
 *                                      'taxable' => false, // not taxable
 *                                  ),
 *                              ),
 *
 *                           // Optional. Array of payment methods to enable for this invoice. Defaults to all available
 *                           // payment methods according to your settings.
 *         'payment_methods' => array( 'generic', 'bank', 'paypal' ),
 *
 *                           // Optional. The status of the invoice. Defaults to 'draft'.
 *         'status'          => 'unpaid',
 *
 *                           // Optional. Tax rate, expressed as percentage. Defaults according to your settings.
 *         'tax'             => '9.5',
 *
 *                           // Optional. Tax calculation method ('inclusive' or 'exclusive'). Defaults according to your settings.
 *         'tax_calc_method' => 'exclusive',
 *
 *                           // Optional. Tax name. Defaults according to your settings.
 *         'tax_name'        => 'Tax',
 *
 *                           // Optional. The invoice terms. Defaults according to your settings.
 *         'terms'           => 'Net 30 days.',
 *
 *                           // Optional. The invoice title.
 *         'title'           => 'Invoice for John Doe',
 *
 * @version 3.9.0
 * @since   3.9.0
 *
 * @return int $invoice_id The newly created invoice's ID.
 */
function sliced_invoices_create_invoice( $args ) {
	
	if ( ! $args['client_id'] ) {
		wp_die(
			__( 'Cannot create invoice: invalid client ID.', 'sliced-invoices' ),
			__( 'Error', 'sliced-invoices' ),
			array( 'response' => 400 )
		);
	}
	
	if ( ! $args['line_items'] ) {
		wp_die(
			__( 'Cannot create invoice: no line items.', 'sliced-invoices' ),
			__( 'Error', 'sliced-invoices' ),
			array( 'response' => 400 )
		);
	}
	
	$post_args = array(
		'post_type'       => 'sliced_invoice',
		'post_status'     => 'publish',
		'post_title'      => isset( $args['title'] ) ? $args['title'] : '',
		'post_content'    => isset( $args['description'] ) ? $args['description'] : '',
		'post_author'     => isset( $args['creator_user_id'] ) ? $args['creator_user_id'] : get_current_user_id(),
	);
	$invoice_id = wp_insert_post( $post_args );
	
	// REQUIRED, the user ID of the client the invoice is associated with:
	update_post_meta( $invoice_id, '_sliced_client', $args['client_id'] );
	
	// REQUIRED, line items:
	update_post_meta( $invoice_id, '_sliced_items', $args['line_items'] );
	
	// invoice created date:
	$created_at = isset( $args['created_at'] ) ? $args['created_at'] : time();
	update_post_meta( $invoice_id, '_sliced_invoice_created', $created_at );
	
	// invoice due date:
	update_post_meta(
		$invoice_id,
		'_sliced_invoice_due',
		isset( $args['due_date'] ) ? $args['due_date'] : Sliced_Invoice::get_auto_due_date()
	);
	
	// invoice number:
	$number = isset( $args['number'] ) ? $args['number'] : sliced_get_next_invoice_number();
	update_post_meta( $invoice_id, '_sliced_invoice_number', $number );
	Sliced_Invoice::update_invoice_number( $invoice_id ); // advance invoice number for the next time
	
	// invoice number prefix:
	$number_prefix = isset( $args['number_prefix'] ) ? $args['number_prefix'] : sliced_get_invoice_prefix();
	update_post_meta( $quote_id, '_sliced_invoice_prefix', $number_prefix );
	
	// invoice number suffix:
	$number_suffix = isset( $args['number_suffix'] ) ? $args['number_suffix'] : sliced_get_invoice_suffix();
	update_post_meta( $quote_id, '_sliced_invoice_suffix', $number_suffix );
	
	// invoice currency:
	update_post_meta(
		$invoice_id,
		'_sliced_currency',
		isset( $args['currency'] ) ? $args['currency'] : 'default'
	);
	
	// discount:
	if ( isset( $args['discount'] ) ) {
		update_post_meta( $invoice_id, '_sliced_discount', $args['discount'] );
	}
	if ( isset( $args['discount_type'] ) ) {
		update_post_meta( $invoice_id, '_sliced_discount_type', $args['discount_type'] );
	}
	if ( isset( $args['discount_tax_treatment'] ) ) {
		update_post_meta( $invoice_id, '_sliced_discount_tax_treatment', $args['discount_tax_treatment'] );
	}
	
	// automatically enable your chosen payment methods:
	update_post_meta(
		$invoice_id,
		'_sliced_payment_methods',
		isset( $args['payment_methods'] ) ? $args['payment_methods'] : array_keys( sliced_get_accepted_payment_methods() )
	);
	
	// tax settings: (if not set, invoice will default to global settings)
	if ( isset( $args['tax'] ) ) {
		update_post_meta( $invoice_id, '_sliced_tax', $args['tax'] );
	}
	if ( isset( $args['tax_calc_method'] ) ) {
		update_post_meta( $invoice_id, '_sliced_tax_calc_method', $args['tax_calc_method'] );
	}
	if ( isset( $args['tax_name'] ) ) {
		update_post_meta( $invoice_id, '_sliced_tax_name', $args['tax_name'] );
	}
	
	// invoice terms:
	update_post_meta(
		$invoice_id,
		'_sliced_invoice_terms',
		isset( $args['terms'] ) ? $args['terms'] : sliced_get_invoice_terms()
	);
	
	// set status as draft:
	Sliced_Invoice::set_status(
		isset( $args['status'] ) ? $args['status'] : 'draft',
		$invoice_id 
	);
	
	// miscellaneous bits:
	update_post_meta( $invoice_id, '_sliced_number', $number_prefix . $number . $number_suffix );
	$created_utc   = Sliced_Admin::work_out_date_format( $created_at ); // parses $created_at into UTC time formatted "Y-m-d H:i:s"
	$created_local = get_date_from_gmt( $created_utc, "Y-m-d H:i:s" ); // takes the above and converts it to local WordPress time
	wp_update_post( array(
		'ID'            => $invoice_id,
		'post_date'     => $created_local,
		'post_date_gmt' => $created_utc,
	) );
	
	return $invoice_id;
}

/**
 * Create a new Sliced Quote.
 *
 * Creates a new quote based on the data provided in $args.  Client_id and line_items are required,
 * all others are optional.  Example $args:
 *
 *     $args = array(
 *
 *                           // REQUIRED. the WordPress User ID corresponding to the Sliced Client record.
 *         'client_id'       => 123,
 *
 *                           // Optional. The WordPress User ID of the person creating the quote. Defaults to the current user.
 *         'creator_user_id' => 1,
 *
 *                           // Optional. UNIX timestamp to be used for quote date. Defaults to current time.
 *         'created_at'      => date( "U", strtotime( '2021-10-29 22:29:00' ) ),
 *
 *                           // Optional. 3-letter currency code. Defaults according to your settings.
 *         'currency'        => 'USD',
 *
 *                           // Optional. Content of "Description" field.
 *         'description'     => 'Description of work to be provided to John Doe.',
 *
 *                           // Optional. A discount amount to apply to the quote. Default is 0.
 *         'discount'        => 5,
 *
 *                           // Optional. Whether the number specified in 'discount' is a fixed amount, or a percentage.
 *                           // Possible values are 'amount' and 'percentage'.  Default is 'amount'.
 *         'discount_type'   => 'amount',
 *
 *                           // Optional. Whether to apply the discount before or after tax.
 *                           // Possible values are 'before' and 'after'.  Default is 'after'.
 *         'discount_tax_treatment' => 'after',
 *
 *                           // Optional. The quote number. Defaults to next available number according to your settings.
 *         'number'          => 123456,
 *
 *                           // Optional. A prefix to the quote number. Defaults according to your settings.
 *         'number_prefix'   => 'QUO-',
 *
 *                           // Optional. A suffix to the quote number. Defaults according to your settings.
 *         'number_suffix'   => '-web',
 *
 *                           // REQUIRED. the line items of the quote, in the following format:
 *         'line_items'      => array(
 *                                  array( 
 *                                      'qty'     => '1',
 *                                      'title'   => 'Test item',
 *                                      'amount'  => '100',
 *                                      'taxable' => 'on', // taxable
 *                                  ),
 *                                  array( 
 *                                      'qty'     => '1',
 *                                      'title'   => 'Another test item',
 *                                      'amount'  => '20',
 *                                      'taxable' => false, // not taxable
 *                                  ),
 *                              ),
 *
 *                           // Optional. The status of the quote. Defaults to 'draft'.
 *         'status'          => 'sent',
 *
 *                           // Optional. Tax rate, expressed as percentage. Defaults according to your settings.
 *         'tax'             => '9.5',
 *
 *                           // Optional. Tax calculation method ('inclusive' or 'exclusive'). Defaults according to your settings.
 *         'tax_calc_method' => 'exclusive',
 *
 *                           // Optional. Tax name. Defaults according to your settings.
 *         'tax_name'        => 'Tax',
 *
 *                           // Optional. The quote terms. Defaults according to your settings.
 *         'terms'           => 'Net 30 days.',
 *
 *                           // Optional. The quote title.
 *         'title'           => 'Quote for John Doe',
 *
 *                           // UNIX timestamp to be used for the "valid until" date. Defaults according to your settings.
 *         'valid_until'     => date( "U", strtotime( '2021-11-29 22:29:00' ) ),
 *
 *     );
 *
 * @version 3.9.0
 * @since   3.9.0
 *
 * @return int $quote_id The newly created quote's ID.
 */
function sliced_invoices_create_quote( $args ) {
	
	if ( ! $args['client_id'] ) {
		wp_die(
			__( 'Cannot create quote: invalid client ID.', 'sliced-invoices' ),
			__( 'Error', 'sliced-invoices' ),
			array( 'response' => 400 )
		);
	}
	
	if ( ! $args['line_items'] ) {
		wp_die(
			__( 'Cannot create quote: no line items.', 'sliced-invoices' ),
			__( 'Error', 'sliced-invoices' ),
			array( 'response' => 400 )
		);
	}
	
	$post_args = array(
		'post_type'       => 'sliced_quote',
		'post_status'     => 'publish',
		'post_title'      => isset( $args['title'] ) ? $args['title'] : '',
		'post_content'    => isset( $args['description'] ) ? $args['description'] : '',
		'post_author'     => isset( $args['creator_user_id'] ) ? $args['creator_user_id'] : get_current_user_id(),
	);
	$quote_id = wp_insert_post( $post_args );
	
	// REQUIRED, the user ID of the client the quote is associated with:
	update_post_meta( $quote_id, '_sliced_client', $args['client_id'] );
	
	// REQUIRED, line items:
	update_post_meta( $quote_id, '_sliced_items', $args['line_items'] );
	
	// quote created date:
	$created_at = isset( $args['created_at'] ) ? $args['created_at'] : time();
	update_post_meta( $quote_id, '_sliced_quote_created', $created_at );
	
	// quote "valid until" date:
	update_post_meta(
		$quote_id,
		'_sliced_quote_valid_until',
		isset( $args['valid_until'] ) ? $args['valid_until'] : Sliced_Quote::get_auto_valid_until_date()
	);
	
	// quote number:
	$number = isset( $args['number'] ) ? $args['number'] : sliced_get_next_quote_number();
	update_post_meta( $quote_id, '_sliced_quote_number', $number );
	Sliced_Quote::update_quote_number( $quote_id ); // advance quote number for the next time
	
	// quote number prefix:
	$number_prefix = isset( $args['number_prefix'] ) ? $args['number_prefix'] : sliced_get_quote_prefix();
	update_post_meta( $quote_id, '_sliced_quote_prefix', $number_prefix );
	
	// quote number suffix:
	$number_suffix = isset( $args['number_suffix'] ) ? $args['number_suffix'] : sliced_get_quote_suffix();
	update_post_meta( $quote_id, '_sliced_quote_suffix', $number_suffix );
	
	// quote currency:
	update_post_meta(
		$quote_id,
		'_sliced_currency',
		isset( $args['currency'] ) ? $args['currency'] : 'default'
	);
	
	// discount:
	if ( isset( $args['discount'] ) ) {
		update_post_meta( $quote_id, '_sliced_discount', $args['discount'] );
	}
	if ( isset( $args['discount_type'] ) ) {
		update_post_meta( $quote_id, '_sliced_discount_type', $args['discount_type'] );
	}
	if ( isset( $args['discount_tax_treatment'] ) ) {
		update_post_meta( $quote_id, '_sliced_discount_tax_treatment', $args['discount_tax_treatment'] );
	}
	
	// tax settings: (if not set, quote will default to global settings)
	if ( isset( $args['tax'] ) ) {
		update_post_meta( $quote_id, '_sliced_tax', $args['tax'] );
	}
	if ( isset( $args['tax_calc_method'] ) ) {
		update_post_meta( $quote_id, '_sliced_tax_calc_method', $args['tax_calc_method'] );
	}
	if ( isset( $args['tax_name'] ) ) {
		update_post_meta( $quote_id, '_sliced_tax_name', $args['tax_name'] );
	}
	
	// quote terms:
	update_post_meta(
		$quote_id,
		'_sliced_quote_terms',
		isset( $args['terms'] ) ? $args['terms'] : sliced_get_quote_terms()
	);
	
	// set status as draft:
	Sliced_Quote::set_status(
		$quote_id,
		isset( $args['status'] ) ? $args['status'] : 'draft'
	);
	
	// miscellaneous bits:
	update_post_meta( $quote_id, '_sliced_number', $number_prefix . $number . $number_suffix );
	$created_utc   = Sliced_Admin::work_out_date_format( $created_at ); // parses $created_at into UTC time formatted "Y-m-d H:i:s"
	$created_local = get_date_from_gmt( $created_utc, "Y-m-d H:i:s" ); // takes the above and converts it to local WordPress time
	wp_update_post( array(
		'ID'            => $quote_id,
		'post_date'     => $created_local,
		'post_date_gmt' => $created_utc,
	) );
	
	return $quote_id;
}

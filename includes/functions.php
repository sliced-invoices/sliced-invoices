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
 *         'title'           => 'Invoice for John Doe',
 *
 *         'description'     => 'Description of work to be provided to John Doe.',
 *
 *         'client_id'       => 123, // REQUIRED. the WordPress User ID corresponding to the Sliced Client record.
 *
 *         'creator_user_id' => 1,   // the WordPress User ID of the person creating the invoice.
 *
 *                           // UNIX timestamp to be used for invoice date. If not provided, defaults to now:
 *         'created_at'      => date( "U", strtotime( '2021-10-29 22:29:00' ) ),
 *
 *         'currency'        => 'USD', // 3-letter currency code. If not provided, defaults according to your settings.
 *
 *                           // UNIX timestamp to be used for due date. If not provided, defaults according to your settings:
 *         'due_date'        => date( "U", strtotime( '2021-11-29 22:29:00' ) ),
 *
 *                           // the number of the invoice. If not provided, defaults to next available number according to your settings.
 *         'number'          => 123456,
 *
 *                           // prefix to the invoice number. If not provided, defaults according to your settings.
 *         'number_prefix'   => 'INV-',
 *
 *                           // suffix to the invoice number. If not provided, defaults according to your settings.
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
 *                              );
 *
 *                           // array of payment methods to enable for this invoice. If not provided, defaults to all available
 *                           // payment methods according to your settings.
 *         'payment_methods' => array( 'generic', 'bank', 'paypal' ),
 *
 *         'status'          => 'unpaid', // the status of the invoice. If not provided, defaults to 'draft'.
 *
 *         'terms'           => 'Net 30 days.', // the invoice terms. If not provided, defaults according to your settings.
 *     );
 *
 * @version 3.x.x
 * @since   3.x.x
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
	
	// automatically enable your chosen payment methods:
	update_post_meta(
		$invoice_id,
		'_sliced_payment_methods',
		isset( $args['payment_methods'] ) ? $args['payment_methods'] : array_keys( sliced_get_accepted_payment_methods() )
	);
	
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
 *         'title'           => 'Quote for John Doe',
 *
 *         'description'     => 'Description of work to be provided to John Doe.',
 *
 *         'client_id'       => 123, // REQUIRED. the WordPress User ID corresponding to the Sliced Client record.
 *
 *         'creator_user_id' => 1,   // the WordPress User ID of the person creating the quote.
 *
 *                           // UNIX timestamp to be used for quote date. If not provided, defaults to now:
 *         'created_at'      => date( "U", strtotime( '2021-10-29 22:29:00' ) ),
 *
 *         'currency'        => 'USD', // 3-letter currency code. If not provided, defaults according to your settings.
 *
 *                           // UNIX timestamp to be used for the "valid until" date. If not provided, defaults according to your settings:
 *         'valid_until'     => date( "U", strtotime( '2021-11-29 22:29:00' ) ),
 *
 *                           // the number of the quote. If not provided, defaults to next available number according to your settings.
 *         'number'          => 123456,
 *
 *                           // prefix to the quote number. If not provided, defaults according to your settings.
 *         'number_prefix'   => 'QUO-',
 *
 *                           // suffix to the quote number. If not provided, defaults according to your settings.
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
 *                              );
 *
 *         'status'          => 'sent', // the status of the quote. If not provided, defaults to 'draft'.
 *
 *         'terms'           => 'Net 30 days.', // the quote terms. If not provided, defaults according to your settings.
 *     );
 *
 * @version 3.x.x
 * @since   3.x.x
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
	
	// quote terms:
	update_post_meta(
		$quote_id,
		'_sliced_quote_terms',
		isset( $args['terms'] ) ? $args['terms'] : sliced_get_quote_terms()
	);
	
	// set status as draft:
	Sliced_Quote::set_status(
		isset( $args['status'] ) ? $args['status'] : 'draft',
		$quote_id 
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

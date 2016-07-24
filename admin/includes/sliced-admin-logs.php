<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Calls the class.
 */
function sliced_call_logs_class() {
	date_default_timezone_set( SLICED_TIMEZONE );
	new Sliced_Logs;
}
add_action('sliced_loaded', 'sliced_call_logs_class' );


/**
 * The Class.
 */
class Sliced_Logs {

	private $logs;

	private $meta_key = '_sliced_log';

	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		add_action( 'publish_sliced_invoice', array( &$this, 'create_invoice' ), 10, 2 );
		add_action( 'publish_sliced_quote', array( &$this, 'create_quote' ), 10, 2 );

		// status change
		add_action( 'set_object_terms', array( &$this, 'status_change' ), 20, 6 );
		add_action( 'set_object_terms', array( &$this, 'marked_as_paid' ), 20, 6 );

		// client declined quote
		add_action( 'sliced_client_declined_quote', array( &$this, 'client_declined_quote' ), 10, 2 );

		// client accepted quote
		add_action( 'sliced_client_accepted_quote', array( &$this, 'client_accepted_quote' ), 10, 1 );
		add_action( 'sliced_client_accepted_quote', array( &$this, 'quote_to_invoice' ), 10, 1 );
		add_action( 'sliced_manual_convert_quote_to_invoice', array( &$this, 'quote_to_invoice' ) );

		// client makes a payment
		add_action( 'sliced_payment_made', array( &$this, 'payment_made' ), 10, 3);

		// notification sent
		add_action( 'sliced_quote_available_email_sent', array( &$this, 'quote_sent' ), 99, 1);
		add_action( 'sliced_invoice_available_email_sent', array( &$this, 'invoice_sent' ), 99, 1);
	}


	/**
	 * Send a quote
	 *
	 * @since 2.21
	 */
	public function quote_sent( $id ) {

		if( ! $id || ! isset( $id ) )
			return;

		$post = get_post( $id );

		if( ! $post || ! isset( $post ) )
			return;

		// if the post is being updated, return
		if( $post->post_date != $post->post_modified )
			return;

		$meta_value = array(
			'type'      => 'quote_sent',
			'by'        => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Send an invoice
	 *
	 * @since 2.21
	 */
	public function invoice_sent( $id ) {

		if( ! $id || ! isset( $id ) )
			return;

		$post = get_post( $id );

		if( ! $post || ! isset( $post ) )
			return;

		// if the post is being updated, return
		if( $post->post_date != $post->post_modified )
			return;

		$meta_value = array(
			'type'      => 'invoice_sent',
			'by'        => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Invoice creation
	 *
	 * @since 2.20
	 */
	public function create_invoice( $id, $post ) {

		if( ! $id || ! isset( $id ) )
			return;

		if( ! $post || ! isset( $post ) )
			return;

		// if the post is being updated, return
		if( $post->post_date != $post->post_modified )
			return;

		$meta_value = array(
			'type' => 'invoice_created',
			'by'   => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Quote creation
	 *
	 * @since 2.20
	 */
	public function create_quote( $id, $post ) {

		if( ! $id || ! isset( $id ) )
			return;

		if( ! $post || ! isset( $post ) )
			return;

		// if the post is being updated, return
		if( $post->post_date != $post->post_modified )
			return;

		$meta_value = array(
			'type' => 'quote_created',
			'by'   => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Status change
	 *
	 * @since 2.20
	 */
	public function status_change( $id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		// if no change, return
		if( $tt_ids == $old_tt_ids || ! isset( $id ) )
			return;

		if( ! isset( $tt_ids[0] ) || ! isset( $old_tt_ids[0] ) )
			return;

		$new = get_term( $tt_ids[0], $taxonomy );
		$new_status = $new->name;
		$old = get_term( $old_tt_ids[0], $taxonomy );
		$old_status = $old->name;

		$meta_value = array(
			'type'     => 'status_update',
			'taxonomy' => $taxonomy,
			'from'     => $old_status,
			'to'       => $new_status,
			'by'       => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Quote declined by client
	 *
	 * @since 2.20
	 */
	public function client_declined_quote( $id, $reason ) {

		if( ! isset( $id ) )
			return;

		$meta_value = array(
			'type'   => 'client_declined_quote',
			'reason' => $reason,
			'by'     => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Quote accepted by client
	 *
	 * @since 2.20
	 */
	public function client_accepted_quote( $id ) {

		if( ! isset( $id ) )
			return;

		$meta_value = array(
			'type' => 'client_accepted_quote',
			'by'   => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Online payment
	 *
	 * @since 2.20
	 */
	public function payment_made( $id, $gateway, $status ) {

		// if no gateway, return
		if( ! isset( $gateway ) || ! isset( $id ) )
			return;

		$meta_value = array(
			'type'    => 'payment_made',
			'gateway' => $gateway,
			'status'  => $status,
			'by'      => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Manually marked as payment
	 *
	 * @since 2.20
	 */
	public function marked_as_paid( $id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		// if no change, return
		if( $tt_ids == $old_tt_ids )
			return;

		// if not an invoice, return
		if( $taxonomy != 'invoice_status' )
			return;

		if( ! isset( $tt_ids[0] ) )
			return;

		$term = get_term( $tt_ids[0], $taxonomy );
		$status = $term->slug;

		if( $status != 'paid' )
			return;

		$meta_value = array(
			'type' => 'marked_as_paid',
			'from' => $old_tt_ids,
			'to'   => $tt_ids,
			'by'   => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Change from quote to invoice
	 *
	 * @since 2.20
	 */
	public function quote_to_invoice( $id ) {

		$meta_value = array(
			'type' => 'quote_to_invoice',
			'by'   => get_current_user_id(), // returns 0 if no user
		);
		$result = $this->update_log_meta( $id, $meta_value );

	}


	/**
	 * Price change - TO DO LATER
	 *
	 * @since 2.20
	 */
	// public function price_updated( $id ) {
	//     // $meta_value = array(
	//     //     'type'      => 'price_updated',
	//     //     'by'        => get_current_user_id(), // returns 0 if no user
	//     // );
	//     // $result = $this->update_log_meta( $id, $meta_value );
	// }





	/**
	 * Dispaly the logs within the invoice or quote
	 *
	 * @since 2.20
	 */
	public function display_the_logs( $id ) {

		$log_meta = $this->get_log_meta( $id, true );
		$notes = null;

		if( $log_meta ) {

			$log_meta = array_reverse($log_meta, true); // reverse the order. keep the keys
			$notes = '<ul class="notes">';

			// loop through each log entry
			foreach ($log_meta as $time => $log) {

				// get the user, date and time
				$user_info  = get_userdata( $log['by'] );
				$user_name  = $user_info ? $user_info->user_login : 'Guest';
				$the_date   = date( get_option( 'date_format' ), (int) $time );
				$the_time   = date( get_option( 'time_format' ), (int) $time );
				$time_date  = sprintf( __( '%1s on %2s', 'sliced-invoices' ), $the_time, $the_date );
				$by         = sprintf( __( 'by %s', 'sliced-invoices' ), $user_name );

				// work out the type of log entry
				switch ($log['type']) {
					case 'invoice_created':
						$message = sprintf( __( '%s was created.', 'sliced-invoices' ), sliced_get_invoice_label() );
						break;
					case 'quote_created':
						$message = sprintf( __( '%s was created.', 'sliced-invoices' ), sliced_get_quote_label() );
						break;
					case 'status_update':
						$message = sprintf( __( 'Status changed from %1s to %2s.', 'sliced-invoices' ), $log['from'], $log['to'] );
						break;
					case 'client_declined_quote':
						$message = sprintf( __( '%1s was declined. Reason: %2s', 'sliced-invoices' ), sliced_get_quote_label(), $log['reason'] );
						break;
					case 'client_accepted_quote':
						$message = sprintf( __( '%s was accepted by client.', 'sliced-invoices' ), sliced_get_quote_label() );
						break;
					case 'payment_made':
						$message = sprintf( __( 'Payment was made via %s.', 'sliced-invoices' ), $log['gateway'] );
						break;
					case 'marked_as_paid':
						$message = sprintf( __( '%s was marked as Paid.', 'sliced-invoices' ), sliced_get_invoice_label() );
						break;
					case 'quote_to_invoice':
						$message = sprintf( __( 'Converted from %1s to %2s.', 'sliced-invoices' ), sliced_get_quote_label(), sliced_get_invoice_label() );
						break;
					case 'quote_sent':
						$message = sprintf( __( '%s was sent.', 'sliced-invoices' ), sliced_get_quote_label() );
						break;
					case 'invoice_sent':
						$message = sprintf( __( '%s was sent.', 'sliced-invoices' ), sliced_get_invoice_label() );
						break;

					default:
						# code...
						break;
				}

				$notes .= '<li class="note">';
				$notes .= '<div class="note_content">' . esc_html( $message ) . '</div>';
				$notes .= '<p class="meta">' . esc_html( $time_date ) . '<br>' . esc_html( $by ) . '</p>';
				$notes .= '</li>';

			}

			$notes .= '</ul>';

		}

		return $notes;

	}


	private function get_log_meta( $id, $single ) {
		return get_post_meta( $id, $this->meta_key, $single );
	}

	private function update_log_meta( $id, $meta_value ) {
		sleep(1); // sleep for 1 second to avoid other logs overwriting each other if they go at the same time
		$log_meta = $this->get_log_meta( $id, true );
		$log_meta[time()] = $meta_value;
		return update_post_meta( $id, $this->meta_key, $log_meta );
	}
}

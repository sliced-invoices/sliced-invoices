<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Calls the class.
 */
function sliced_call_logs_class() {
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
	 * 
	 * @version 3.9.0
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
		add_action( 'sliced_invoices_admin_after_convert_quote_to_invoice', array( &$this, 'quote_to_invoice' ) );

		// client makes a payment
		add_action( 'sliced_payment_made', array( &$this, 'payment_made' ), 10, 3 );

		// notification sent
		add_action( 'sliced_quote_available_email_sent', array( &$this, 'quote_sent' ), 99, 1 );
		add_action( 'sliced_invoice_available_email_sent', array( &$this, 'invoice_sent' ), 99, 1 );
		add_action( 'sliced_invoice_payment_reminder_email_sent', array( &$this, 'payment_reminder_sent' ), 99, 1 );
		add_action( 'sliced_invoice_payment_received_email_sent', array( &$this, 'payment_received_sent' ), 99, 2 );
		
		// quote/invoice viewed
		add_action( 'shutdown', array( &$this, 'views_logger' ) ); // must run after Sliced_Secure, if present
		add_action( 'shutdown', array( &$this, 'views_logger' ) );
		
	}


	/**
	 * Send a quote
	 *
	 * @since 2.21
	 */
	public function quote_sent( $id ) {

		if ( ! $id || ! isset( $id ) ) {
			return;
		}

		/*
		$post = get_post( $id );

		if( ! $post || ! isset( $post ) )
			return;

		// if the post is being updated, return
		if( $post->post_date != $post->post_modified )
			return;
		*/
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type'      => 'quote_sent',
			'by'        => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Send an invoice
	 *
	 * @since 2.21
	 */
	public function invoice_sent( $id ) {

		if ( ! $id || ! isset( $id ) ) {
			return;
		}

		/*
		$post = get_post( $id );

		if( ! $post || ! isset( $post ) )
			return;

		// if the post is being updated, return
		//if( $post->post_date != $post->post_modified )
			//return;
		*/
			
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type'      => 'invoice_sent',
			'by'        => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}
	
	
	/**
	 * Log when payment reminder email sent
	 *
	 * @since 3.7.0
	 */
	public function payment_reminder_sent( $id ) {

		if ( ! $id || ! isset( $id ) ) {
			return;
		}

		$user_id = $this->identify_the_user();
		
		$meta_value = array(
			'type'      => 'payment_reminder_sent',
			'by'        => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}
	
	
	/**
	 * Log when payment reminder email sent
	 *
	 * @since 3.7.0
	 */
	public function payment_received_sent( $id, $status ) {

		if ( ! $id || ! isset( $id ) ) {
			return;
		}
		
		// we only log it if the email was sent manually
		if ( $status !== 'manual' ) {
			return;
		}

		$user_id = $this->identify_the_user();
		
		$meta_value = array(
			'type'      => 'payment_received_sent',
			'by'        => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}
	

	/**
	 * Invoice creation
	 *
	 * @since 2.20
	 */
	public function create_invoice( $id, $post ) {

		if ( ! $id || ! isset( $id ) ) {
			return;
		}

		if ( ! $post || ! isset( $post ) ) {
			return;
		}

		// if the post is being updated, return
		if ( $post->post_date != $post->post_modified ) {
			return;
		}
			
		// extra check to prevent duplicate entries
		$log = get_post_meta( $id, '_sliced_log', true );
		if ( is_array( $log ) && count( $log ) > 0 ) {
			return;
		}
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type' => 'invoice_created',
			'by'   => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}

	/**
	 * Quote creation
	 *
	 * @since 2.20
	 */
	public function create_quote( $id, $post ) {

		if ( ! $id || ! isset( $id ) ) {
			return;
		}

		if ( ! $post || ! isset( $post ) ) {
			return;
		}

		// if the post is being updated, return
		if ( $post->post_date != $post->post_modified ) {
			return;
		}
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type' => 'quote_created',
			'by'   => $user_id,
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
		if ( $tt_ids == $old_tt_ids || ! isset( $id ) ) {
			return;
		}

		if ( ! isset( $tt_ids[0] ) || ! isset( $old_tt_ids[0] ) ) {
			return;
		}

		$new = get_term_by( 'term_taxonomy_id', $tt_ids[0], $taxonomy );
		$new_status = $new->name;
		$old = get_term_by( 'term_taxonomy_id', $old_tt_ids[0], $taxonomy );
		$old_status = $old->name;
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type'     => 'status_update',
			'taxonomy' => $taxonomy,
			'from'     => $old_status,
			'to'       => $new_status,
			'by'       => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Quote declined by client
	 *
	 * @since 2.20
	 */
	public function client_declined_quote( $id, $reason ) {

		if ( ! isset( $id ) ) {
			return;
		}
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type'   => 'client_declined_quote',
			'reason' => $reason,
			'by'     => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Quote accepted by client
	 *
	 * @since 2.20
	 */
	public function client_accepted_quote( $id ) {

		if ( ! isset( $id ) ) {
			return;
		}
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type' => 'client_accepted_quote',
			'by'   => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
		
		// the rest is for showing an admin notice, if needed
		$settings = array();
		$quotes = get_option('sliced_quotes');
		if ( isset( $quotes['quote_admin_notices'] ) && is_array( $quotes['quote_admin_notices'] ) ) {
			$settings = $quotes['quote_admin_notices'];
		}
		if ( in_array( 'quote_accepted', $settings ) ) {
			$notice_args = array(
				'class' => 'notice-success',
				'content' => '<p>' . sprintf(
						/* translators: %1$s here is a placeholder for the word "Quote"; %2$s is a placeholder for the Quote number; for example: "Quote SI-123 was accepted" */
						__( '%1$s %2$s was accepted', 'sliced-invoices' ),
						sliced_get_quote_label(),
						'<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . sliced_get_quote_prefix( $id ) . sliced_get_quote_number( $id ) . sliced_get_quote_suffix( $id ) . '</a>'
					) . '</p>',
				'dismissable' => true
			);
			Sliced_Admin_Notices::add_custom_notice( 'quote_accepted_'.$id, $notice_args );
		}
	}


	/**
	 * Online payment
	 *
	 * @since 2.20
	 */
	public function payment_made( $id, $gateway, $status ) {

		// if no gateway, return
		if ( ! isset( $gateway ) || ! isset( $id ) ) {
			return;
		}
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type'    => 'payment_made',
			'gateway' => $gateway,
			'status'  => $status,
			'by'      => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
		
		// the rest is for showing an admin notice, if needed
		$settings = array();
		$invoices = get_option('sliced_invoices');
		if ( isset( $invoices['invoice_admin_notices'] ) && is_array( $invoices['invoice_admin_notices'] ) ) {
			$settings = $invoices['invoice_admin_notices'];
		}
		if ( in_array( 'invoice_paid', $settings ) ) {
			$notice_args = array(
				'class' => 'notice-success',
				'content' => '<p>' . sprintf(
						/* translators: %1$s here is a placeholder for the word "Invoice"; %2$s is a placeholder for the Invoice number; for example: "Invoice SI-123 was paid" */
						__( '%1$s %2$s was paid', 'sliced-invoices' ),
						sliced_get_invoice_label(),
						'<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) . '</a>'
					) . '</p>',
				'dismissable' => true
			);
			Sliced_Admin_Notices::add_custom_notice( 'invoice_paid_'.$id, $notice_args );
		}
	}

	/**
	 * Manually marked as payment
	 *
	 * @since 2.20
	 */
	public function marked_as_paid( $id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		
		// if no change, return
		if( $tt_ids == $old_tt_ids ) {
			return;
		}

		// if not an invoice, return
		if( $taxonomy != 'invoice_status' ) {
			return;
		}

		if( ! isset( $tt_ids[0] ) ) {
			return;
		}

		$term = get_term_by( 'term_taxonomy_id', $tt_ids[0], $taxonomy );
		$status = $term->slug;

		if( $status != 'paid' ) {
			return;
		}
		
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type' => 'marked_as_paid',
			'from' => $old_tt_ids,
			'to'   => $tt_ids,
			'by'   => $user_id,
		);
		$result = $this->update_log_meta( $id, $meta_value );
	}


	/**
	 * Change from quote to invoice
	 *
	 * @since 2.20
	 */
	public function quote_to_invoice( $id ) {
	
		$user_id = $this->identify_the_user();

		$meta_value = array(
			'type' => 'quote_to_invoice',
			'by'   => $user_id,
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
	 * Identify the current user
	 * 
	 *   returns:
	 *     $user_id = -1: System
	 *     $user_id = 0: Guest (or unknown)
	 *     $user_id = x: user with ID x
	 *
	 * @since 3.7.0
	 */
	public function identify_the_user() {
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$user_id = -1;
		} else {
			$user_id = get_current_user_id(); // returns 0 if no user
		}
		return $user_id;
	}


	/**
	 * Display the logs within the invoice or quote
	 *
	 * @version 3.9.4
	 * @since   2.20
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
				if ( $log['by'] == -1 ) {
					$user_name = __( 'System', 'sliced-invoices' );
				} elseif ( $log['by'] == 0 ) {
					$user_name = __( 'Guest', 'sliced-invoices' );
				} else {
					$user_info = get_userdata( $log['by'] );
					$user_name = $user_info ? $user_info->user_login : $log['by'];
				}
				$the_date   = get_date_from_gmt ( date( 'Y-m-d H:i:s', (int) $time ), get_option('date_format') );
				$the_time   = get_date_from_gmt ( date( 'Y-m-d H:i:s', (int) $time ), get_option('time_format') );
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
						$message = sprintf(
							__( 'Payment was initiated via %1s.', 'sliced-invoices' ) . ' (%2s)',
							$log['gateway'],
							$log['status']
						);
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
					case 'payment_reminder_sent':
						$message = __( 'Payment reminder email was sent.', 'sliced-invoices' );
						break;
					case 'payment_received_sent':
						$message = __( 'Payment received email was sent.', 'sliced-invoices' );
						break;
					case 'invoice_viewed':
						$message = sprintf( __( '%s was viewed.', 'sliced-invoices' ), sliced_get_invoice_label() );
						break;
					case 'quote_viewed':
						$message = sprintf( __( '%s was viewed.', 'sliced-invoices' ), sliced_get_quote_label() );
						break;

					default:
						# code...
						break;
				}

				$notes .= '<li class="note">';
				$notes .= '<div class="note_content">' . esc_html( $message ) . '</div>';
				$notes .= '<p class="meta">' . esc_html( $time_date ) . '<br>' . esc_html( $by );
				$notes .= ( $log['by'] === 0 && isset( $log['secured'] ) && $log['secured'] === 'yes' ? ', '.__( 'using the secure link', 'sliced-invoices' ) : '' );
				$notes .= '</p>';
				$notes .= '</li>';

			}

			$notes .= '</ul>';

		}

		return $notes;

	}


	private function get_log_meta( $id, $single ) {
		$meta_value = get_post_meta( $id, $this->meta_key, $single );
		if ( is_array( $meta_value ) ) {
			return $meta_value;
		}
		return array();
	}

	private function update_log_meta( $id, $meta_value ) {
		sleep(1); // sleep for 1 second to avoid other logs overwriting each other if they go at the same time
		$log_meta = $this->get_log_meta( $id, true );
		$log_meta[current_time( 'timestamp', 1 )] = $meta_value;
		return update_post_meta( $id, $this->meta_key, $log_meta );
	}
	
	
	/**
	 * Log unique views of quote/invoice, add notifications if applicable
	 *
	 * @version 3.8.16
	 * @since   3.5.0
	 */
	public function views_logger() {

		// double check here -- technically none of these should be possible at this point, but better safe than sorry
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}
		
		// don't log cron-initiated "views" (e.g. grabbing the invoice to make a PDF)
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}
		
		// don't log admins looking at their own invoices
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// don't log if access was denied
		if ( defined( 'SLICED_SECURE_ACCESS_DENIED' ) && SLICED_SECURE_ACCESS_DENIED ) {
			return;
		}
		
		// don't log known internal requests (for example, generating a PDF in response to a webhook)
		if ( defined( 'SLICED_SECURE_INTERNAL_REQUEST' ) && SLICED_SECURE_INTERNAL_REQUEST ) {
			return;
		}
		
		$type = get_post_type();
		
		if ( $type !== 'sliced_invoice' && $type !== 'sliced_quote' ) {
			return;
		}

		$id = get_the_ID();
		
		if ( ! $id > 0 ) {
			return;
		}
		
		$log = get_post_meta( $id, '_sliced_log', true );
		if ( ! is_array( $log ) ) {
			// something is wrong, abort
			return;
		}
		
		// don't log if it's an internal request
		$server_host = gethostname();
		$server_ip   = gethostbyname( $server_host );
		if ( Sliced_Shared::get_ip() === $server_ip ) {
			return;
		}
		
		$meta_value = array(
			'type'    => ( $type === 'sliced_invoice' ? 'invoice' : 'quote' ) . '_viewed',
			'by'      => get_current_user_id(), // returns 0 if no user,
			'ip'      => Sliced_Shared::get_ip(),
			'secured' => class_exists( 'Sliced_Secure' ) ? 'yes' : 'no',
		);
		
		// make sure visit is "unique"
		// we'll say an unique visit constitutes a any unique combination of user_id (by) and ip address (ip) within the last 24 hours
		$now = current_time( 'timestamp', 1 );
		$one_day = 86400; // 60 * 60 * 24
		$unique = true;
		foreach ( $log as $timestamp => $entry ) {
			if ( isset( $entry['type'] ) && ( $entry['type'] === 'invoice_viewed' || $entry['type'] === 'quote_viewed' ) ) {
				if (
					$entry['by'] === $meta_value['by'] &&
					$entry['ip'] === $meta_value['ip'] &&
					$timestamp > $now - $one_day
				) {
					$unique = false;
				}
			}
		}
		
		if ( $unique ) {
			
			// save it
			$this->update_log_meta( $id, $meta_value );
			
			// show admin notice, if needed
			$settings = array();
			if ( $type === 'sliced_invoice' ) {
				$invoices = get_option('sliced_invoices');
				if ( isset( $invoices['invoice_admin_notices'] ) && is_array( $invoices['invoice_admin_notices'] ) ) {
					$settings = $invoices['invoice_admin_notices'];
				}
			} else {
				$quotes = get_option('sliced_quotes');
				if ( isset( $quotes['quote_admin_notices'] ) && is_array( $quotes['quote_admin_notices'] ) ) {
					$settings = $quotes['quote_admin_notices'];
				}
			}
			if (
				( $type === 'sliced_invoice' && in_array( 'invoice_viewed', $settings ) ) ||
				( $type === 'sliced_quote' && in_array( 'quote_viewed', $settings ) )
			) {
				if ( $meta_value['by'] === 0 && $meta_value['secured'] === 'yes' ) {
					$message = sprintf(
						/* translators: %1$s here is a placeholder for the word "Invoice" or "Quote";
							%2$s is a placeholder for the Invoice or Quote number;
							for example: "Invoice SI-123 was viewed using the secure link" */
						__( '%1$s %2$s was viewed using the secure link', 'sliced-invoices' ),
						( $type === 'sliced_invoice' ? sliced_get_invoice_label() : sliced_get_quote_label() ),
						'<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . ( $type === 'sliced_invoice' ? sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) : sliced_get_quote_prefix( $id ) . sliced_get_quote_number( $id ) . sliced_get_quote_suffix( $id ) ) . '</a>'
					);
				} elseif ( $meta_value['by'] > 0 ) {
					$message = sprintf(
						/* translators: %1$s here is a placeholder for the word "Invoice" or "Quote";
							%2$s is a placeholder for the Invoice or Quote number;
							%3$s is a placeholder for the Client's name;
							for example: "Invoice SI-123 was viewed by John Doe" */
						__( '%1$s %2$s was viewed by %3$s', 'sliced-invoices' ),
						( $type === 'sliced_invoice' ? sliced_get_invoice_label() : sliced_get_quote_label() ),
						'<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . ( $type === 'sliced_invoice' ? sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) : sliced_get_quote_prefix( $id ) . sliced_get_quote_number( $id ) . sliced_get_quote_suffix( $id ) ) . '</a>',
						get_user_meta( (int)$meta_value['by'], '_sliced_client_business', true )
					);
				} else {
					$message = sprintf(
						/* translators: %1$s here is a placeholder for the word "Invoice" or "Quote";
							%2$s is a placeholder for the Invoice or Quote number;
							for example: "Invoice SI-123 was viewed" */
						__( '%1$s %2$s was viewed', 'sliced-invoices' ),
						( $type === 'sliced_invoice' ? sliced_get_invoice_label() : sliced_get_quote_label() ),
						'<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . ( $type === 'sliced_invoice' ? sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id ) . sliced_get_invoice_suffix( $id ) : sliced_get_quote_prefix( $id ) . sliced_get_quote_number( $id ) . sliced_get_quote_suffix( $id ) ) . '</a>'
					);
				}
				$notice_args = array(
					'class' => 'notice-success',
					'content' => '<p>' . $message . '</p>',
					'dismissable' => true
				);
				Sliced_Admin_Notices::add_custom_notice( ( $type === 'sliced_invoice' ? 'invoice' : 'quote' ).'_viewed_'.$id, $notice_args );
			}
			
		}
		
		if ( $type === 'sliced_invoice' ) {
			do_action( 'sliced_invoice_viewed', $id, $meta_value, $unique );
		}
		
		if ( $type === 'sliced_quote' ) {
			do_action( 'sliced_quote_viewed', $id, $meta_value, $unique );
		}
		
	}
	
	
	
}

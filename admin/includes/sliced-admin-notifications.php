<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }



/**
 * Calls the class.
 */
function sliced_call_notifications_class() {
	 new Sliced_Notifications();
}
add_action( 'init', 'sliced_call_notifications_class' );


/**
 * The Class.
 */
class Sliced_Notifications {

	public $id; // the invoice or quote id

	public $client_emails = array();

	public $admin_emails = array();

	public $settings;

	public $colors;

	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		$this->id = sliced_get_the_id();

		$this->client_emails = array(
			'invoice_available', // manually sent
			'quote_available', // manually sent
			'payment_received_client',
			'payment_reminder',
		);
		$this->admin_emails = array(
			'quote_accepted',
			'quote_declined',
			'payment_received',
		);

		// Settings
		$this->settings = get_option( 'sliced_emails' );
		$this->colors = $this->email_colors();

		$this->init_hooks();

	}


	public function init_hooks() {

		global $pagenow;

		add_filter( 'sliced_actions_column', array( $this, 'sliced_add_email_button' ) );

		if( $pagenow == 'edit.php' ){
			add_action( 'admin_footer', array( $this, 'email_popup' ) );
			add_action( 'admin_init', array( $this, 'send_quote_or_invoice_manually' ) );
		}
		add_action( 'wp_ajax_sliced_sure_to_email', array( $this, 'sure_to_email' ) );

		// send notifications
		add_action( 'sliced_send_payment_notification', array( $this, 'payment_received_client'), 9, 2 );
		add_action( 'sliced_send_payment_notification', array( $this, 'payment_received'), 10, 2 );
		add_action( 'sliced_send_payment_reminder_notification', array( $this, 'payment_reminder') );
		add_action( 'sliced_client_accepted_quote', array( $this, 'quote_accepted'), 10, 1 );
		add_action( 'sliced_client_declined_quote', array( $this, 'quote_declined'), 10, 2 );

		// modify the subject and content for admin notices
		add_filter( 'sliced_get_email_subject', array( $this, 'admin_notification_subject'), 10, 3 );
		add_filter( 'sliced_get_email_content', array( $this, 'admin_notification_content'), 10, 3 );

		// notifications sent
		add_action( 'sliced_quote_available_email_sent', array( $this, 'quote_sent' ), 10, 1);
		add_action( 'sliced_invoice_available_email_sent', array( $this, 'invoice_sent' ), 10, 1);

		add_action( 'admin_init', array( $this, 'check_for_reminder_dates' ) );
	}

	/**
	 * Send the payment received email to client.
	 *
	 * @return string
	 */
	public function payment_received( $id, $status ) {
		if ( $status != 'success' )
			return;
		$this->id = $id;
		$this->type = 'invoice';
		$type = 'payment_received';
		$this->send_mail( $type );
	}

	/**
	 * Send the payment received email to client.
	 *
	 * @return string
	 */
	public function payment_received_client( $id, $status ) {
		if ( $status != 'success' )
			return;
		$this->id = $id;
		$this->type = 'invoice';
		$type = 'payment_received_client';
		$this->send_mail( $type );
	}


	/**
	 * quote accepted notification. To admin only
	 *
	 * @since 2.10
	 */
	public function quote_accepted( $id ) {
		$this->id = $id;
		$this->type = 'quote';
		$type = 'quote_accepted';
		$this->send_mail( $type );
	}

	/**
	 * Declined quote notification. To admin only
	 *
	 * @since 2.10
	 */
	public function quote_declined( $id, $reason ) {
		$this->id = $id;
		$this->type = 'quote';
		$type = 'quote_declined';
		$this->send_mail( $type );
	}

	/**
	 * Send the invoice
	 *
	 * @since 1.0.0
	 */
	public function send_the_invoice( $id ) {
		$this->id = $id;
		$this->type = "invoice";
		$this->send_mail( "invoice_available" );
		do_action( "sliced_invoice_available_email_sent", $this->id );
	}

	/**
	 * Send the quote
	 *
	 * @since 1.0.0
	 */
	public function send_the_quote( $id ) {
		$this->id = $id;
		$this->type = "quote";
		$this->send_mail( "quote_available" );
		do_action( "sliced_quote_available_email_sent", $this->id );
	}


	/**
	 * Send the payment reminder email to client.
	 *
	 * @return string
	 */
	public function payment_reminder( $id ) {
		$this->id = $id;
		$this->payment_reminder_sent( $this->id );
		$type = 'payment_reminder';
		$this->send_mail( $type );
	}

	/**
	 * Send the quote or invoice using the email button.
	 *
	 * @since 1.0.0
	 */
	public function send_quote_or_invoice_manually() {

		if ( ! isset( $_POST['send_email'] ) || ! wp_verify_nonce( $_POST['send_email'], 'sliced-send-email') )
			return;

		if ( ! isset( $_POST['id'] ) )
			return;

		// get the ID and the type
		$id 	= $_POST['id'];
		$type 	= sliced_get_the_type( $id );

		if( $type == 'invoice' ) {
			$this->send_the_invoice( $id );
		} else {
			$this->send_the_quote( $id );
		}

		wp_redirect( admin_url( "edit.php?post_type=sliced_${type}&email=sent" ) );
		exit;

	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_subject( $type ) {
		// if we are sending a quote or an invoice manually
		if( isset( $_POST['email_subject'] ) ) {
			$output = $_POST['email_subject'];
		} elseif ( isset( $this->settings["${type}_subject"] ) ) {
			$output = $this->settings["${type}_subject"];
		} else {
			$output = $this->admin_notification_subject( array("${type}_subject"), $this->id, array("${type}_subject") );
		}
		return apply_filters( 'sliced_get_email_subject', $this->replace_wildcards( $output ), $this->id, $type );
	}

	/**
	 * Admin notifications subject.
	 *
	 * @return string
	 */
	public function admin_notification_subject( $subject, $id, $type ) {

		if( in_array( $type, $this->client_emails) )
			return $subject;

		$subject = null;
		switch ( $type ) {
			case 'quote_accepted':
				$subject = sprintf( __( 'Your %s has been accepted', 'sliced-invoices' ), sliced_get_quote_label() );
				break;
			case 'quote_declined':
				$subject = sprintf( __( 'Your %s has been declined', 'sliced-invoices' ), sliced_get_quote_label() );
				break;
			case 'payment_received':
				$subject = __( 'You\'ve received a payment!', 'sliced-invoices' );
				break;
		}
		return $subject;
	}

	/**
	 * Admin notifications content.
	 *
	 * @return string
	 */
	public function admin_notification_content( $message, $id, $type ) {

		if( in_array( $type, $this->client_emails) )
			return $message;

		$message = null;
		$message = $this->get_email_header();

		switch ( $type ) {

			case 'quote_accepted':

				$content = sprintf(
					__( '%1s has accepted your %2s of %3s.', 'sliced-invoices' ),
					sliced_get_client_business( $id ),
					sliced_get_quote_label(),
					sliced_get_total( $id ) );
				$content .= '<br>';
				$content .= sprintf(
					__( 'An %1s has automatically been created (%2s).', 'sliced-invoices' ),
					sliced_get_invoice_label(),
					sliced_get_invoice_prefix( $id ) . sliced_get_invoice_number( $id )
				);
				 $content .= '<br>';

				 $message .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( apply_filters( 'sliced_admin_notification_quote_accepted', $content, $this->id ) ) ) ) );

				 break;

			case 'quote_declined':

				$content = sprintf(
					__( '%1s has declined your %2s of %3s.', 'sliced-invoices' ),
					sliced_get_client_business( $id ),
					sliced_get_quote_label(),
					sliced_get_total( $id )
				);
				$content .= '<br>';

				$content .= get_post_meta( $id, '_sliced_declined_reason', true );

				$message .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( apply_filters( 'sliced_admin_notification_quote_declined', $content, $this->id ) ) ) ) );

				break;

			case 'payment_received':

				 $content = __( 'You\'ve received a payment!', 'sliced-invoices' );
				 $content .= '<br/>';
				 $content .= sprintf(
					__( '%1s has made a payment for %2s on %3s %4s.', 'sliced-invoices' ),
					sliced_get_client_business( $this->id ),
					sliced_get_total( $this->id ),
					sliced_get_invoice_label(),
					sliced_get_invoice_prefix( $this->id ) . sliced_get_invoice_number( $this->id )
				 );
				 $content .= '<br>';

				$message .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( apply_filters( 'sliced_admin_notification_payment_received', $content, $this->id ) ) ) ) );

				break;
		}


		$message .= $this->get_email_footer();

		return $message;
	}

	/**
	 * Get email content.
	 *
	 * @return string
	 */
	public function get_content( $type ) {

		$output = $this->get_email_header();
		// if we are sending a quote or an invoice manually
		if( isset( $_POST['email_content'] ) ) {
			$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( $_POST['email_content'] ) ) ) );
		} elseif ( isset( $this->settings["${type}_content"] ) ) {
			$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( $this->settings["${type}_content"] ) ) ) );
		} else {
			$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( $this->admin_notification_content( null, $this->id, ["${type}_content"] ) ) ) ) );
		}

		$output .= $this->get_email_footer();

		return apply_filters( 'sliced_get_email_content', $output, $this->id, $type );
	}


	/**
	 * Get email recipient.
	 *
	 * @return string
	 */
	public function get_recipient( $type ) {

		// send to client
		if( in_array( $type, $this->client_emails ) ) {
			$output = sliced_get_client_email( $this->id );
		}
		// send to admin
		if( in_array( $type, $this->admin_emails ) ) {
			$output = $this->settings['from'];
		}
		// if we are sending a quote or an invoice manually
		if( isset( $_POST['client_email'] ) && ! empty( $_POST['client_email'] ) ) {
			$output = $_POST['client_email'];
		}

		// for recurring invoices.
		// sliced_get_client_email() function not working here. Need to investigate
		if( in_array( $type, $this->client_emails ) && $this->settings['bcc'] == 'on' ) {
			$output = sliced_get_client_email( $this->id );
		}

		return $output;
	}


	/**
	 * Get email headers.
	 *
	 * @return string
	 */
	public function get_headers( $type ) {

		$output = 'From: ' . $this->settings['name'] . ' <' . $this->settings['from'] . '>' . "\r\n";

		if( in_array( $type, $this->client_emails ) && $this->settings['bcc'] == 'on' ) {
			$output .= 'Bcc: ' . $this->settings['name'] . ' <' . $this->settings['from'] . '>' . "\r\n";
		}
		return $output;
	}


	/**
	 * Get email footer text.
	 *
	 * @return string
	 */
	public function get_footer_text() {
		$output = $this->settings["footer"];
		return $output;
	}

	/**
	 * Get email headers.
	 *
	 * @return string
	 */
	public function get_attachments() {
		$attachment = null;
		$output = apply_filters( 'sliced_email_attachment', $attachment, $this->id );
		if( ! $output ) {
			$output = null;
		}
		return $output;
	}


	/**
	 * Send the mail
	 *
	 * @since 2.0.0
	 */
	public function send_mail( $type ) {

		add_filter( 'wp_mail_content_type', array( $this, 'set_email_type' ) );

		$send = wp_mail(
			$this->get_recipient( $type ),
			$this->get_subject( $type ),
			$this->get_content( $type ),
			$this->get_headers( $type ),
			$this->get_attachments()
		);

		remove_filter( 'wp_mail_content_type', array( $this, 'set_email_type' ) );

		do_action( 'sliced_after_send_email', $this->id );
	}


	/**
	 * Load the thickbox popup in the footer.
	 *
	 * @since 1.0.0
	 */
	public function email_popup() {	?>

		<div id="sliced-email-popup" style="display:none;">
			<form action="" method="post" name="sliced-send-email" id="sliced-send-email">

					<input name="action" type="hidden" value="sliced-send-email" />
					<?php wp_nonce_field( 'sliced-send-email', 'send_email' ); ?>

				 <div class="sliced-email-preview">
						  <p><?php _e( 'Loading the email preview....', 'sliced-invoices' ) ?></p>
					</div>

					 <?php submit_button( __( 'Send The Email', 'sliced-invoices' ), 'primary', 'sliced-send-email', true, array( 'id' => 'btn-send-email', 'class' => 'submit button button-primary button-large' ) ); ?>
				</form>
		</div>

		<?php
	}

	/**
	 * Load the fields via AJAX for the post.
	 *
	 * @since 1.0.0
	 */
	public function sure_to_email() {

		$id        = $_POST['id'];
		$type      = sliced_get_the_type();
		$content   = $this->get_preview_content( "${type}_available" );
		$subject   = $this->get_subject( "${type}_available" );
		$recipient = $this->get_recipient( "${type}_available" );

		$args = array(
			'wpautop'       => true,
			'media_buttons' => false,
			'textarea_rows' => 5,
			'teeny'         => true,
			'dfw'           => false,
			'tinymce'       => true,
			'quicktags'     => true,
			'textarea_name' => 'email_content',
			'editor_class'  => 'sliced-editor',
		);
		?>

		<input name="id" type="hidden" value="<?php echo (int)$id; ?>" />
		<table class="form-table popup-form">
			<tbody>
				<tr class="form-field form-required">
					<td>
						<label for="client_email"><?php _e('Send To', 'sliced-invoices' ); ?> <span class="description"><?php _e('(required)'); ?></span></label>
						<input name="client_email" type="text" id="client_email" value="<?php echo sanitize_email( $recipient ); ?>" aria-required="true" autocapitalize="none" autocorrect="off" />
						<p class="description"><?php _e('Use comma to separate multiple recipients', 'sliced-invoices' ); ?></p>
					</td>
				</tr>
				<tr class="form-field form-required">
					<td>
						<label for="email_subject"><?php _e('Subject', 'sliced-invoices' ); ?> <span class="description"><?php _e('(required)'); ?></span></label>
						<input name="email_subject" type="text" id="email_subject" value="<?php echo esc_html( $subject ); ?>" aria-required="true" autocapitalize="none" autocorrect="off" />
					</td>
				</tr>
				<tr class="form-field form-required">
					<td>
					<label for="email_content"><?php _e('Email Content', 'sliced-invoices' ); ?> <span class="description"><?php _e('(required)'); ?></span></label>
					<?php wp_editor( stripslashes( $content ), 'email_content', $args );
					do_action('admin_print_footer_scripts'); ?>
					</td>
				</tr>
			</tbody>
		  </table>

		<?php

		die();

	}

	/**
	 * Get email content.
	 *
	 * @return string
	 */
	public function get_preview_content( $type ) {
		$output = wp_kses_post( wpautop( $this->replace_wildcards( $this->settings["${type}_content"] ) ) );
		return wp_kses_post( wpautop( $this->replace_wildcards( $output ) ) );
	}



	/**
	 * Check the payment reminder dates and see if we need to send any reminders.
	 *
	 * @return string
	 */
	public function check_for_reminder_dates() {

		// check if reminders are set
		if ( ! isset( $this->settings['payment_reminder_days'] ) )
			return;
		$reminders = $this->settings['payment_reminder_days'];
		if ( ! $reminders )
			return;

		$args = array(
				'post_type'     =>  'sliced_invoice',
				'status'     	=>  'publish',
				'fields'     	=>  'ids',
				'meta_query'    =>  array(
					 array(
						  'key' 		=>  '_sliced_invoice_due',
						  'compare' 	=>  'EXISTS',
					 )
				),
				'tax_query' => array(
				array(
					'taxonomy' => 'invoice_status',
					'field'    => 'slug',
					'terms'    => array( 'unpaid', 'overdue' ),
				),

			),
		);
		$invoices = get_posts( $args );
		if( ! $invoices )
			return;

		// loop through the ids of the invoices
		foreach ( $invoices as $id ) {
			// get the due date of the invoice
			$due_date = get_post_meta( $id, '_sliced_invoice_due', true );
			// loop through the reminder dates
			foreach ($reminders as $key => $send_days) {
				// add each date that the reminder needs to be sent into a new array with id as the key
				$date_to_send[$id][] = date( 'Y-m-d', strtotime( $send_days . ' days', $due_date ) );
			}

		}

		$today = date( 'Y-m-d' );

		foreach ($date_to_send as $id => $values) {

			// if todays date is a date we should send
			if( in_array( $today, $values ) ) {
				$sent = get_post_meta( $id, 'sliced_invoice_reminder_sent', true );

				// if the sent filed exists
				if( isset( $sent ) && ! empty( $sent ) ) {
					if ( in_array( $today, $sent ) ) {
						// do nothing if it has already been sent today
					} else {
						do_action( 'sliced_send_payment_reminder_notification', $id );
					}

				} else {
					do_action( 'sliced_send_payment_reminder_notification', $id );
				}

			}

		}

	}


	/**
	 * What to do when we send a reminder
	 *
	 * @return string
	 */
	private function payment_reminder_sent( $id ) {
		$sent = get_post_meta( $id, 'sliced_invoice_reminder_sent', true );
		$sent[] = date( 'Y-m-d' );
		update_post_meta( $id, 'sliced_invoice_reminder_sent', $sent );
	}

	/**
	 * What to do when we send a quote
	 *
	 * @since 1.0.0
	 */
	public function quote_sent( $id ) {
		Sliced_Quote::set_as_sent( $id );
		update_post_meta( $id, "_sliced_quote_email_sent", current_time( 'timestamp' ) );
	}

	/**
	 * What to do when we send an invoice
	 *
	 * @since 1.0.0
	 */
	public function invoice_sent( $id ) {
		Sliced_Invoice::set_as_unpaid( $id );
		update_post_meta( $id, "_sliced_invoice_email_sent", current_time( 'timestamp' ) );
	}


	/**
	 * Set emails to HTML.
	 *
	 * @since 1.0.0
	 */
	public function set_email_type( $content_type ) {
		return 'text/html';
	}

	/**
	 * Get the action button for emailing
	 *
	 * @since 1.0.0
	 */
	public function get_email_button() {

		$id = sliced_get_the_id();
		$sent_text = $this->email_sent_text( $id );

		$button = '<a title="' . __( 'Email to client', 'sliced-invoices' ) . '" class="thickbox button ui-tip sliced-email-button" href="#TB_inline?width=760&height=500&inlineId=sliced-email-popup" data-id="' . (int)$id . '"><span class="dashicons dashicons-email-alt"></span></a>';
		$button .= $sent_text;

		return $button;

	}

	public function sliced_add_email_button( $button ) {
		$button .= $this->get_email_button();
		echo $button;
	}

	/**
	 * Add the sent text if it has been sent before
	 *
	 * @since 1.0.0
	 */
	private function email_sent_text( $id ) {

		$sent = get_post_meta( $id, '_sliced_' . sliced_get_the_type( $id ) . '_email_sent', true );
		$sent_text = null;

		if( ! empty( $sent ) ) {
			$time_sent = date_i18n( get_option( 'time_format' ), (int) $sent );
			$date_sent = date_i18n( get_option( 'date_format' ), (int) $sent );
			$time_ago  = sprintf( _x( '%s ago', 'sliced-invoices' ), human_time_diff( $sent, current_time( 'timestamp' ) ) );

			$sent_text = '<br /><span class="ui-tip description sliced-sent" title="Sent at ' . esc_html( $time_sent ) . ' on ' . esc_html( $date_sent ) . '">' . __( 'Sent ', 'sliced-invoices' ) . esc_html( ( $time_ago ) ) . '</span>';
		}

		return $sent_text;
	}


	/**
	 * Sets up all the color data for emails
	 *
	 * @since 1.0.0
	 */
	public function email_colors() {

		return apply_filters( 'sliced_email_color_options', array (
			'body_bg'       => isset( $this->settings['body_bg'] ) ? esc_html( $this->settings['body_bg'] ) : '#eeeeee',
			'header_bg'     => isset( $this->settings['header_bg'] ) ? esc_html( $this->settings['header_bg'] ) : '#dddddd',
			'content_bg'    => isset( $this->settings['content_bg'] ) ? esc_html( $this->settings['content_bg'] ) : '#ffffff',
			'content_color' => isset( $this->settings['content_color'] ) ? esc_html( $this->settings['content_color'] ) : '#444444',
			'footer_bg'     => isset( $this->settings['footer_bg'] ) ? esc_html( $this->settings['footer_bg'] ) : '#f6f6f6',
			'footer_color'  => isset( $this->settings['footer_color'] ) ? esc_html( $this->settings['footer_color'] ) : '#444444',
		) );

	}

	/**
	 * Get is or was depending on date
	 *
	 * @since 1.0.0
	 */
	public function is_was() {
		$today = current_time( 'timestamp' );
		$due_date = sliced_get_invoice_due( $this->id );
		$is_was = $today > $due_date ? __( 'was', 'sliced-invoices' ) : __( 'is', 'sliced-invoices' );
		return $is_was;
	}

	/**
	 * Replace strings in email content
	 *
	 * @since 1.0.0
	 */
	public function replace_wildcards( $string ) {

		$replace_array = array(
			'%client_business%'   => sliced_get_client_business( $this->id ),
			'%client_first_name%' => sliced_get_client_first_name( $this->id ),
			'%client_last_name%'  => sliced_get_client_last_name( $this->id ),
			'%client_email%'      => sliced_get_client_email( $this->id ),
			'%link%'              => "<a href='" . esc_url( sliced_get_the_link( $this->id ) ) . "'>" . esc_url( sliced_get_the_link( $this->id ) ) . "</a>",
			'%due_date%'          => date_i18n( get_option( 'date_format' ), (int) sliced_get_invoice_due( $this->id ) ),
			'%created%'           => date_i18n( get_option( 'date_format' ), (int) sliced_get_created( $this->id ) ),
			'%total%'             => sliced_get_total( $this->id ),
			'%order_number%'      => sliced_get_invoice_order_number( $this->id ),
			'%number%'            => sliced_get_prefix( $this->id ) . sliced_get_number( $this->id ),
			'%valid_until%'       => date_i18n( get_option( 'date_format' ), (int) sliced_get_quote_valid( $this->id ) ),
			'%is_was%'            => $this->is_was(),
			'%date%'              => date_i18n( get_option( 'date_format' ), (int) current_time( 'timestamp' ) ),
		);

		foreach ($replace_array as $key => $value) {
			$string = str_replace( $key, $value, $string );
		}

		return apply_filters( 'sliced_email_content_replace', $string );

	}

	/**
	 * Email header
	 *
	 * @since 1.0.0
	 */
	public function get_email_header() {

		$email_header = null;
		$email_header .= "<!DOCTYPE html><html><head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' /><title>" . sliced_get_business_name() . "</title></head><body marginwidth='0' topmargin='0' marginheight='0' offset='0' style='background: " . esc_html( $this->colors['body_bg'] ) . "; font-family: arial, helvetica; font-size: 16px;'>
				<div id='sliced-wrapper' style='margin-top:0'>

					<table border='0' topmargin='0' cellpadding='0' cellspacing='0' height='100%' width='640' align='center'>
						<tr>
							<td align='center' valign='top' style='background: " . esc_html( $this->colors['header_bg'] ) . "'>
							<div id='sliced-logo'>
									<h1 class='business-name' style='margin:20px 0 20px 0;''>
										  <a href='" . esc_url( sliced_get_business_website() ) . "'>";

										  if ( sliced_get_business_logo() ) {
											$email_header .= "<img src='" . esc_url( sliced_get_business_logo() ) . "' />";
										  } else {
											$email_header .= esc_html( sliced_get_business_name() );
										  }

										  $email_header .= "</a>
									 </h1>
							</div>
								<table border='0' cellpadding='10' cellspacing='0' width='640' id='sliced-container'  style='background: " . esc_html( $this->colors['content_bg'] ) . "; color: " . esc_html( $this->colors['content_color'] ) . "'>
									<tr>
										<td align='center' valign='top'>
												<!-- Body -->
											<table border='0' cellpadding='0' cellspacing='0' width='600' id='template_body'>
											<tr>
													 <td valign='top' id='body_content'>
														  <!-- Content -->
														  <table border='0' cellpadding='20' cellspacing='0' width='100%'>
														  <tr>
																<td valign='top' style='font-size: 16px;'>
																	 <div id='body_content_inner'>";


		return apply_filters( 'sliced_email_header', $email_header );
	}



	/**
	 * Email footer
	 *
	 * @since 1.0.0
	 */
	public function get_email_footer() {

		$email_footer = null;

		// include the button
		if( $this->type == 'invoice' || $this->type == 'quote' ) {
			$button_text = sprintf( __( 'View this %s online', 'sliced-invoices' ), $this->type );
			$email_footer .= "<br><a href='" . esc_url( sliced_get_the_link( $this->id ) ) . "' style='font-size: 100%; line-height: 2; color: #ffffff; border-radius: 5px; display: inline-block; cursor: pointer; font-weight: bold; text-decoration: none; background: #60ad5d; margin: 30px 0 10px 0; padding: 0; border-color: #60ad5d; border-style: solid; border-width: 10px 20px;'>" . esc_html( $button_text ) . "</a>";
		}

		$email_footer .=  "</div><!-- End body_content_inner -->
													</td>
																</tr>
														  </table><!-- End Content -->
													 </td>
												</tr>
										  </table><!-- End Body -->
									 </td>
								</tr>
							<tr>
									<td align='center' valign='top' style='background:" . esc_html( $this->colors['footer_bg'] ) . ";color:" . esc_html( $this->colors['footer_color'] ) . "'>
										  <!-- Footer -->
										<table border='0' cellpadding='10' cellspacing='0' width='600' id='sliced_footer' >
										<tr>
											<td valign='top'>
													 <table border='0' cellpadding='10' cellspacing='0' width='100%'>
													 <tr>
														  <td colspan='2' valign='middle' id='credit'>";
															$email_footer .= wpautop( wp_kses_post( stripslashes( $this->get_footer_text() ) ) ) . "</td>
													 </tr>
													 </table>
												</td>
										  </tr>
										  </table><!-- End Footer -->
									 </td>
								</tr>
						  </table>
					 </td>
				</tr>
		  </table>
		  </div><!-- End Sliced Wrapper -->
		 </body>
		</html>";

		return apply_filters( 'sliced_email_footer', $email_footer );

	}


}

<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }



/**
 * Calls the class.
 */
function sliced_call_notifications_class() {
	Sliced_Notifications::get_instance();
}
add_action( 'init', 'sliced_call_notifications_class' );


/**
 * The Class.
 */
class Sliced_Notifications {

	/**
	 * @var  object  Instance of this class
	 */
	protected static $instance;

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

    public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	public function init_hooks() {

		global $pagenow;

		add_filter( 'sliced_actions_column', array( $this, 'sliced_add_email_button' ) );

		if( $pagenow == 'edit.php' || ( $pagenow == 'post.php' && ( sliced_get_the_type() === 'invoice' || sliced_get_the_type() === 'quote' ) ) ) {
			add_action( 'admin_footer', array( $this, 'email_popup' ) );
		}
		add_action( 'wp_ajax_sliced_sure_to_email', array( $this, 'sure_to_email' ) );
		add_action( 'wp_ajax_sliced-send-email', array( $this, 'send_email' ) );

		// send notifications
		// may remove these... need to come up with something better.
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

		//add_action( 'admin_init', array( $this, 'check_for_reminder_dates' ) );
	}

	/**
	 * Send the payment received email to client.
	 *
	 * @return string
	 */
	public function payment_received( $id, $status ) {
		if ( $status != 'success' ) {
			return;
		}
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
		if ( $status !== 'success' && $status !== 'manual' ) {
			return;
		}
		$this->id = $id;
		$this->type = 'invoice';
		$type = 'payment_received_client';
		$this->send_mail( $type );
		do_action( "sliced_invoice_payment_received_email_sent", $this->id, $status );
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
		$this->type = 'invoice';
		$type = 'payment_reminder';
		$this->send_mail( $type );
		$this->payment_reminder_sent( $this->id );
		do_action( "sliced_invoice_payment_reminder_email_sent", $this->id );
	}

	/**
	 * Send the quote or invoice using the email button.
	 *
	 * @version 3.9.2
	 * @since   1.0.0
	 */
	public function send_email() {
	
		if ( ! isset( $_POST['send_email'] ) || ! wp_verify_nonce( $_POST['send_email'], 'sliced-send-email') )
			return;

		if ( ! isset( $_POST['id'] ) )
			return;

		$id       = intval( sanitize_text_field( $_POST['id'] ) );
		$template = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : 'default';
		$type     = sliced_get_the_type( $id );
		
		switch ( $template ) {
			case 'payment_reminder':
				$this->payment_reminder( $id );
				break;
			case 'payment_received':
				$this->payment_received_client( $id, 'manual' );
				break;
			default:
				if( $type == 'invoice' ) {
					$this->send_the_invoice( $id );
				} else {
					$this->send_the_quote( $id );
				}
				break;
		}
		
		?>
		<html>
			<head>
				<script type="text/javascript">
					var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
				</script>
				<?php 
				wp_enqueue_style( 'sliced-invoices', plugins_url( 'sliced-invoices' ) . '/admin/css/admin.css', array(), SLICED_VERSION, 'all' );
				wp_print_styles( array( 'wp-admin','sliced-invoices' ) );
				wp_print_scripts( array( 'jquery' ) );				
				?>
			</head>
			<body class="wp-admin wp-core-ui sliced sliced-email-ajax-page">
				<p><?php _e( 'Email was sent successfully.', 'sliced-invoices' ); ?></p>
				<script type="text/javascript">
					window.top.location.href = "<?php echo admin_url( "edit.php?post_type=sliced_{$type}&email=sent" ); ?>";
				</script>
			</body>
		</html>
		<?php
		exit;

	}

	/**
	 * Get email subject.
	 *
	 * @version 3.9.2
	 *
	 * @return string
	 */
	public function get_subject( $type ) {
		// if we are sending a quote or an invoice manually
		if( isset( $_POST['email_subject'] ) ) {
			$output = sanitize_text_field( $_POST['email_subject'] );
		} elseif ( isset( $this->settings["{$type}_subject"] ) ) {
			$output = $this->settings["{$type}_subject"];
		} else {
			$output = $this->admin_notification_subject( array("{$type}_subject"), $this->id, array("{$type}_subject") );
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

		$output = null;
		switch ( $type ) {
			case 'quote_accepted':
				$output = sprintf( __( 'Your %s has been accepted', 'sliced-invoices' ), sliced_get_quote_label() );
				break;
			case 'quote_declined':
				$output = sprintf( __( 'Your %s has been declined', 'sliced-invoices' ), sliced_get_quote_label() );
				break;
			case 'payment_received':
				$output = __( 'You\'ve received a payment!', 'sliced-invoices' );
				break;
			default:
				$output = $subject;
				break;
		}
		return $output;
	}

	/**
	 * Admin notifications content.
	 *
	 * @return string
	 */
	public function admin_notification_content( $message, $id, $type ) {

		if( in_array( $type, $this->client_emails) )
			return $message;

		$output = null;
		$output = $this->get_email_header();

		switch ( $type ) {

			case 'quote_accepted':

				if ( sliced_get_the_type( $id ) === 'invoice' ) {
					$related_invoice_id = $id;
				} else {
					$related_invoice_id = get_post_meta( $id, '_sliced_related_invoice_id', true );
				}
				
				$content = sprintf(
					__( '%1s has accepted your %2s of %3s.', 'sliced-invoices' ),
					sliced_get_client_business( $id ),
					sliced_get_quote_label(),
					sliced_get_total( $id ) );
				$content .= '<br>';
				
				if ( $related_invoice_id ) {
					$this->type = 'invoice'; // effects footer
					$content .= sprintf(
						__( 'An %1s has automatically been created (%2s).', 'sliced-invoices' ),
						sliced_get_invoice_label(),
						sliced_get_invoice_prefix( $related_invoice_id ) . sliced_get_invoice_number( $related_invoice_id ) . sliced_get_invoice_suffix( $related_invoice_id )
					);
					$content .= '<br>';
				}

				$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( apply_filters( 'sliced_admin_notification_quote_accepted', $content, $this->id ) ) ) ) );

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

				$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( apply_filters( 'sliced_admin_notification_quote_declined', $content, $this->id ) ) ) ) );

				break;

			case 'payment_received':
			
				$content = __( 'You\'ve received a payment!', 'sliced-invoices' );
				$content .= '<br/>';
				$content .= sprintf(
					__( '%1s has made a payment for %2s on %3s %4s.', 'sliced-invoices' ),
					sliced_get_client_business( $this->id ),
					sliced_get_last_payment_amount( $this->id ),
					sliced_get_invoice_label(),
					sliced_get_invoice_prefix( $this->id ) . sliced_get_invoice_number( $this->id ) . sliced_get_invoice_suffix( $this->id )
				);
				$content .= '<br>';

				$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( apply_filters( 'sliced_admin_notification_payment_received', $content, $this->id ) ) ) ) );

				break;
			
			default:
				return $message;
				break;
		}


		$output .= $this->get_email_footer();

		return $output;
	}

	/**
	 * Get email content.
	 *
	 * @version 3.9.2
	 *
	 * @return string
	 */
	public function get_content( $type ) {

		$output = $this->get_email_header();
		// if we are sending a quote or an invoice manually
		if( isset( $_POST['email_content'] ) ) {
			$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( $_POST['email_content'] ) ) ) );
		} elseif ( isset( $this->settings["{$type}_content"] ) ) {
			$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( $this->settings["{$type}_content"] ) ) ) );
		} else {
			$output .= wp_kses_post( wpautop( stripslashes( $this->replace_wildcards( $this->admin_notification_content( null, $this->id, array("{$type}_content") ) ) ) ) );
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
			// can't use sanitize_email() here, because $_POST['client_email'] may contain
			// multiple email addresses separated by commas.  sanitize_email() removes
			// the commas, breaking the functionality.
			$output = sanitize_text_field( $_POST['client_email'] );
		}

		return apply_filters( 'sliced_get_email_recipient', $output, $this->id, $type );
	}


	/**
	 * Get email headers.
	 * 
	 * @version 3.9.0
	 * 
	 * @return string
	 */
	public function get_the_headers( $type ) {

		// make sure the From name it's properly quoted, remove any extra double quotes from inside
		$email_name = '"' . str_replace( '"', '', $this->settings['name'] ) . '"';
		$output = 'From: ' . $email_name . ' <' . $this->settings['from'] . '>' . "\r\n";

		if( in_array( $type, $this->client_emails ) && $this->settings['bcc'] == 'on' ) {
			$output .= 'Bcc: ' . $this->settings['from'] . "\r\n";
		}
		
		return apply_filters( 'sliced_get_email_headers', $output, $this->id, $type );
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
	public function get_attachments( $type = '' ) {
		$attachment = null;
		$output = apply_filters( 'sliced_email_attachment', $attachment, $this->id, $type );
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

		$recipients = $this->get_recipient( $type );

		$recipients_array = str_getcsv( $recipients );
		foreach ( $recipients_array as $k => $v ) {
			if ( strpos($v,',') !== false ) {
				$recipients_array[$k] = '"'.str_replace( ' <', '" <', $v );
			}
		}

		$subject = $this->get_subject( $type );
		$content = $this->get_content( $type );
		$headers = $this->get_the_headers( $type );
		$attachments = $this->get_attachments( $type );

		foreach ( $recipients_array as $to ) {
			$send = wp_mail( $to, $subject, $content, $headers, $attachments );
		}

		remove_filter( 'wp_mail_content_type', array( $this, 'set_email_type' ) );

		do_action( 'sliced_after_send_email', $this->id );
	}


	/**
	 * Load the thickbox popup in the footer.
	 *
	 * @since 1.0.0
	 */
	public function email_popup() {

		$id = sliced_get_the_id();
		$type = sliced_get_the_type();
		?>
		
		<div id="sliced-email-popup" style="display:none;">
			<div class="sliced-email-preview">
				<div class="sliced-email-preview-loading">
					<div class="spinner" style="visibility: visible; float: left;"></div>
					<p><?php _e( 'Loading the email preview....', 'sliced-invoices' ) ?></p>
				</div>
				<?php if ( $type === 'invoice' ): ?>
				<div class="nav-tab-wrapper sliced-email-preview-menu" style="display:none;">
					<a class="nav-tab nav-tab-active" data-sliced-email-template="default" onclick="sliced_invoices.sliced_email_preview_switch('default')"><?php esc_html_e( 'Invoice Available', 'sliced-invoices' ); ?></a>
					<a class="nav-tab" data-sliced-email-template="payment_reminder" onclick="sliced_invoices.sliced_email_preview_switch('payment_reminder')"><?php esc_html_e( 'Payment Reminder', 'sliced-invoices' ); ?></a>
					<a class="nav-tab" data-sliced-email-template="payment_received" onclick="sliced_invoices.sliced_email_preview_switch('payment_received')"><?php esc_html_e( 'Payment Received', 'sliced-invoices' ); ?></a>
				</div>
				<?php endif; ?>
			</div>			
		</div>

		<?php
	}
	
	
	/**
	 * Load the fields via AJAX for the post.
	 *
	 * @version 3.9.3
	 * @since   1.0.0
	 */
	public function sure_to_email() {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			echo __( 'Error: insufficient permissions. Must be an administrator to send emails.', 'sliced-invoices' );
			echo '<br /><br />';
			return;
		}
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'sliced_ajax_nonce' ) ) {
			return;
		}
		
		$id        = intval( sanitize_text_field( $_GET['id'] ) );
		$template  = isset( $_GET['template'] ) ? sanitize_text_field( $_GET['template'] ) : 'default';
		
		switch ( $template ) {
			case 'payment_reminder':
				$content   = $this->get_preview_content( "payment_reminder" );
				$subject   = $this->get_subject( "payment_reminder" );
				$recipient = $this->get_recipient( "payment_reminder" );
				break;
			case 'payment_received':
				$content   = $this->get_preview_content( "payment_received_client" );
				$subject   = $this->get_subject( "payment_received_client" );
				$recipient = $this->get_recipient( "payment_received_client" );
				break;
			default:
				$type      = sliced_get_the_type( $id );
				$content   = $this->get_preview_content( "{$type}_available" );
				$subject   = $this->get_subject( "{$type}_available" );
				$recipient = $this->get_recipient( "{$type}_available" );
				break;
		}

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
		<html>
			<head>
				<script type="text/javascript">
					var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
				</script>
				<?php
				wp_enqueue_style( 'sliced-invoices', plugins_url( 'sliced-invoices' ) . '/admin/css/admin.css', array(), SLICED_VERSION, 'all' );
				wp_print_styles( array( 'wp-admin','sliced-invoices' ) );
				wp_print_scripts( array( 'jquery' ) );				
				?>
			</head>
			<body class="wp-admin wp-core-ui sliced sliced-email-ajax-page">

				<form action="<?php echo admin_url( 'admin-ajax.php' ); ?>" method="post" name="sliced-send-email" id="sliced-send-email">

					<input name="action" type="hidden" value="sliced-send-email" />
					<input name="id" type="hidden" value="<?php echo (int)$id; ?>" />
					<input name="template" type="hidden" value="<?php echo $template; ?>" />
					<?php wp_nonce_field( 'sliced-send-email', 'send_email' ); ?>

					<table class="form-table popup-form">
						<tbody>
							<tr class="form-field form-required">
								<td>
									<label for="client_email"><?php _e('Send To', 'sliced-invoices' ); ?> <span class="description"><?php _e('(required)'); ?></span></label>
									<input name="client_email" type="text" id="client_email" value="<?php echo esc_attr( $recipient ); ?>" aria-required="true" autocapitalize="none" autocorrect="off" />
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
				  
					<?php submit_button( __( 'Send The Email', 'sliced-invoices' ), 'primary', 'sliced-send-email', true, array( 'id' => 'btn-send-email', 'class' => 'submit button button-primary button-large' ) ); ?>
				</form>
			</body>
		</html>
		<?php
		
		exit();

	}

	/**
	 * Get email content.
	 *
	 * @version 3.9.2
	 *
	 * @return string
	 */
	public function get_preview_content( $type ) {
		$output = wp_kses_post( wpautop( $this->replace_wildcards( $this->settings["{$type}_content"] ) ) );
		return wp_kses_post( wpautop( $this->replace_wildcards( $output ) ) );
	}



	/**
	 * Check the payment reminder dates and see if we need to send any reminders.
	 *
	 * @version 3.9.2
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
			'post_type'      => 'sliced_invoice',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'     	 => 'ids',
			'meta_query'     => array(
				array(
					'key'       =>  '_sliced_invoice_due',
					'compare'   =>  'EXISTS',
				)
			),
			'tax_query'      => array(
				array(
					'taxonomy'  => 'invoice_status',
					'field'     => 'slug',
					'terms'     => array( 'unpaid', 'overdue' ),
				),
			),
		);
		$args = apply_filters( 'sliced_invoices_check_for_reminder_args', $args );
		$invoices = get_posts( $args );
		if( ! $invoices )
			return;

		// loop through the ids of the invoices
		foreach ( $invoices as $id ) {
			// get the due date of the invoice
			$due_date = get_post_meta( $id, '_sliced_invoice_due', true );
			if ( ! $due_date ) {
				continue;
			}
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
						$this->payment_reminder( $id );
					}
				} else {
					$this->payment_reminder( $id );
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
		if ( ! is_array( $sent ) ) {
			$sent = array();
		}
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

		$button = '<a title="' . __( 'Email to client', 'sliced-invoices' ) . '" class="thickbox button ui-tip sliced-email-button" href="#TB_inline?width=760&height=550&inlineId=sliced-email-popup" onclick="sliced_invoices.sliced_email_preview(' . (int)$id . ')"><span class="dashicons dashicons-email-alt"></span></a>';
		$button .= $sent_text;

		return $button;

	}

	public function sliced_add_email_button( $button ) {
		$button .= $this->get_email_button();
		return $button;
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
			$time_ago  = human_time_diff( $sent, current_time( 'timestamp' ) );
			$sent_text = '<br /><span class="ui-tip description sliced-sent" title="'
				/* translators: %1$s is a placeholder for the time the email was sent;
				  %2$s is the date the email was sent */
				. sprintf( __( 'Sent at %1$s on %2$s', 'sliced-invoices' ), $time_sent, $date_sent )
				. '">'
				/* translators: %s is a time duration, like "1 month" or "3 days" */
				. sprintf( __( 'Sent %s ago', 'sliced-invoices' ), $time_ago )
				. '</span>';
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
			'%link%'              => "<a href='" . sliced_get_the_link( $this->id ) . "'>" . sliced_get_the_link( $this->id ) . "</a>",
			'%due_date%'          => sliced_get_invoice_due( $this->id ) > 0 ? Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_invoice_due( $this->id ) ) : '',
			'%created%'           => sliced_get_created( $this->id ) > 0 ? Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_created( $this->id ) ) : '',
			'%total%'             => sliced_get_total( $this->id ),
			'%last_payment%'      => sliced_get_last_payment_amount( $this->id ),
			'%balance%'           => sliced_get_balance_outstanding( $this->id ),
			'%order_number%'      => sliced_get_invoice_order_number( $this->id ),
			'%number%'            => sliced_get_prefix( $this->id ) . sliced_get_number( $this->id ) . sliced_get_suffix( $this->id ),
			'%valid_until%'       => sliced_get_quote_valid( $this->id ) > 0 ? Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_quote_valid( $this->id ) ) : '',
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
											$email_header .= "<img src='" . esc_url( sliced_get_business_logo() ) . "' style='max-width: 100%; height: auto;' />";
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


		return apply_filters( 'sliced_email_header', $email_header, $this->id );
	}



	/**
	 * Email footer
	 *
	 * @since 1.0.0
	 */
	public function get_email_footer() {

		$email_footer = null;

		// include the button
		$button_text = '';
		if( $this->type == 'invoice' ) {
			$button_text = $this->settings['invoice_available_button'] > '' ? $this->settings['invoice_available_button'] : __( 'View this invoice online', 'sliced-invoices' );
		} elseif ( $this->type == 'quote' ) {
			$button_text = $this->settings['quote_available_button'] > '' ? $this->settings['quote_available_button'] : __( 'View this quote online', 'sliced-invoices' );
		}
		
		$email_footer .= "<br><a href='" . sliced_get_the_link( $this->id ) . "' style='font-size: 100%; line-height: 2; color: #ffffff; border-radius: 5px; display: inline-block; cursor: pointer; font-weight: bold; text-decoration: none; background: #60ad5d; margin: 30px 0 10px 0; padding: 0; border-color: #60ad5d; border-style: solid; border-width: 10px 20px;'>" . esc_html( $button_text ) . "</a>";

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

		return apply_filters( 'sliced_email_footer', $email_footer, $this->id );

	}


}

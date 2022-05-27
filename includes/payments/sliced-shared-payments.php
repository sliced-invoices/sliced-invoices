<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_payments_class() {
	return Sliced_Payments::get_instance();
}
add_action( 'sliced_loaded', 'sliced_call_payments_class' );


/**
 * The Class.
 */
class Sliced_Payments {


	/**
     * @var  object  Instance of this class
     */
    protected static $instance;


	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		add_action( 'sliced_invoice_top_bar_left', array( $this, 'get_gateway_buttons') );
		add_filter( 'sliced_invoice_footer', array( $this, 'create_payment_popup') );

		add_action( 'sliced_quote_top_bar_left', array( $this, 'get_accept_decline_quote_buttons') );
		add_filter( 'sliced_quote_footer', array( $this, 'create_accept_quote_popup') );
		add_filter( 'sliced_quote_footer', array( $this, 'create_decline_quote_popup') );

		add_action( 'sliced_payment_made', array( $this, 'completed_payment'), 10, 3 );

		add_action( 'sliced_do_payment', array( $this, 'client_accept_quote') );
		add_action( 'sliced_do_payment', array( $this, 'client_decline_quote') );

	}
	
	public static function get_instance() {
        if ( ! ( self::$instance instanceof self ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }


	/**
	 * Add the gateway button to invoice.
	 *
	 * @since   2.0.0
	 */
	public function get_gateway_buttons() {

		if( has_term( array( 'paid', 'cancelled' ), 'invoice_status' ) )
			return;

		// get the methods for this invoice
		$all_gateways = sliced_get_accepted_payment_methods();
		$invoice_gateways = sliced_get_invoice_payment_methods();

		// remove the non online gateway payment methods
		if( ! empty( $invoice_gateways[0] ) ) {
			$gateways = array_diff( $invoice_gateways[0], array( 'bank', 'generic' ) );
			foreach( $gateways as $index => $gateway ) {
				if ( isset( $all_gateways[$gateway] ) ) {
					$online_gateways[$gateway] = $all_gateways[$gateway];
				}
			}
		}

		// create the buttons
		if( ! empty( $online_gateways ) ) {
			foreach ( $online_gateways as $gateway => $readable) {
				$button_type = apply_filters( 'sliced_payment_button_type', 'inline', $gateway );
				if ( $button_type === 'inline' ) {
					$this->create_payment_button_inline( $gateway, $readable );
				} elseif ( $button_type === 'popup' ) {
					$this->create_payment_button_popup( $gateway, $readable );
				}
			}
		}

	}


	/**
	 * Payment button for inline form
	 *
	 * @since   3.1.0
	 */
	public function create_payment_button_inline( $gateway, $readable ) {

		$payments = get_option( 'sliced_payments' );
		?>
		
		<div class="sliced_gateway_button">
			<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments['payment_page'] ) ) ?>">
				<?php do_action( 'sliced_before_payment_form_fields' ) ?>

				<?php wp_nonce_field( 'sliced_invoices_payment', 'sliced_payment_nonce' ); ?>
				<input type="hidden" name="sliced_payment_invoice_id" value="<?php the_ID(); ?>">
				<input type="hidden" name="sliced_gateway" value="<?php echo $gateway; ?>" />
				<input type="submit" name="start-payment" class="gateway btn btn-success btn-sm" value="<?php
					if ( function_exists('sliced_get_gateway_'.$gateway.'_label') ) {
						echo call_user_func( 'sliced_get_gateway_'.$gateway.'_label' );
					} else {
						_e( 'Pay with', 'sliced-invoices' );
						echo ' ';
						esc_html_e( $readable );
					} ?>">

				<?php do_action( 'sliced_after_payment_form_fields' ) ?>
			</form>
		</div>

		<?php

	}


	/**
	 * Payment button for popup form
	 *
	 * @since   3.7.0 (resuscitated from 3.1.0)
	 */
	public function create_payment_button_popup( $gateway, $readable ) {

		$url = add_query_arg( array(
			'height'   => '500',
			'width'    => '450',
			'inlineId' => 'sliced_payment_form',
		) );
		$url = parse_url( $url );
		?>
		<div class="sliced_gateway_button">
			<a href="<?php echo $url['path']; ?>#TB_inline?<?php echo $url['query']; ?>" title="<?php _e( 'Pay This Invoice', 'sliced-invoices' ); ?>" class="gateway btn btn-success thickbox btn-sm" data-gateway-readable="<?php esc_html_e( $readable ) ?>" data-gateway="<?php esc_html_e( $gateway ) ?>">
			<?php
			if ( function_exists('sliced_get_gateway_'.$gateway.'_label') ) {
				echo call_user_func( 'sliced_get_gateway_'.$gateway.'_label' );
			} else {
				_e( 'Pay with', 'sliced-invoices' );
				echo ' ';
				esc_html_e( $readable );
			}
			?>
			</a>
		</div>
		<?php

	}
	
	
	/**
	 * Payment popup form
	 *
	 * UN-DEPRECATED as of 3.7.0, maybe we'll keep it after all
	 * DEPRECATED as of 3.1.0, may be removed in the future
	 * @since   2.0.0
	 */
	public function create_payment_popup() {

		$payments = get_option( 'sliced_payments' );
		?>

		<div id="sliced_payment_form" style="display:none">

			<div class="sliced_payment_form_wrap">

				<ul>
					<li><span><?php _e( 'Invoice Number', 'sliced-invoices' ); ?></span> <?php esc_html_e( sliced_get_invoice_prefix() ); ?><?php esc_html_e( sliced_get_invoice_number() ); ?><?php esc_html_e( sliced_get_invoice_suffix() ); ?></li>
					<li><span><?php _e( 'Total Due', 'sliced-invoices' ); ?></span> <?php _e( sliced_get_invoice_total() ); ?></li>
				</ul>

				<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments['payment_page'] ) ) ?>">
					<?php do_action( 'sliced_payment_popup_before_form_fields' ) ?>

					<?php wp_nonce_field( 'sliced_invoices_payment', 'sliced_payment_nonce' ); ?>
					<input type="hidden" name="sliced_payment_invoice_id" id="sliced_payment_invoice_id" value="<?php the_ID(); ?>">
					<input type="hidden" name="sliced_gateway" id="sliced_gateway" />
					<input type="submit" name="start-payment" class="btn btn-success btn-lg" id="start-payment" value="<?php _e( 'Pay Now', 'sliced-invoices' ) ?>">

					<?php do_action( 'sliced_payment_popup_after_form_fields' ) ?>
				</form>

				<?php do_action( 'sliced_after_payment_form' ) ?>

				<div class="gateway-image" id="sliced_gateway_image">
					
				</div>

			</div>

		</div>
		
		<script type="text/javascript">
			( function( $ ) {
				$(document).ready(function(){
					$( 'a.gateway' ).click(function(){
						/*
						var readable = $( this ).data( 'gateway-readable' );
						$( '#sliced_gateway_readable' ).html( readable );
						*/
						var gateway  = $( this ).data( 'gateway' );
						$( '#sliced_gateway' ).val( gateway );
						/*
						var src = "<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) ?>public/images/accept-" + gateway + ".png";
						$( '#sliced_gateway_image' ).html( '<img src="' + src + '" />' );
						*/
					});
				});
			} )( jQuery );
		</script>

		<?php

	}



	/**
	 * Add the accept and decline quote buttons to quotes.
	 *
	 * @since   2.10
	 */
	public function get_accept_decline_quote_buttons() {

		// get the quote options
		$quotes = get_option( 'sliced_quotes' );
		$accept = isset( $quotes['accept_quote'] ) ? $quotes['accept_quote'] : '';
		$output = '';
		
		if( ! empty( $accept ) && $accept == 'on' ) { 
		
			if ( has_term( 'declined', 'quote_status' ) ) {

				$output = '<p class="sliced-quote-declined">' . sprintf( __( 'You have declined this %s.', 'sliced-invoices' ), sliced_get_quote_label() ) . '</p>';
				
			} elseif ( has_term( 'cancelled', 'quote_status' ) ) {
			
				$output = '<p class="sliced-quote-cancelled">' . sprintf( __( 'This %s has been cancelled.', 'sliced-invoices' ), sliced_get_quote_label() ) . '</p>';
				
			} elseif ( has_term( 'accepted', 'quote_status' ) ) {
				
				$output = '<p class="sliced-quote-accepted">' . sprintf( __( 'You have accepted this %s.', 'sliced-invoices' ), sliced_get_quote_label() ) . '</p>';
				
			} elseif ( has_term( 'expired', 'quote_status' ) ) {
				
				$output = '<p class="sliced-quote-expired">' . sprintf( __( 'This %s has expired.', 'sliced-invoices' ), sliced_get_quote_label() ) . '</p>';
				
			} else {
			
				$output = '<a href="#TB_inline?height=300&width=450&inlineId=sliced_accept_quote" title="' . sprintf( esc_html__( 'Accept %s', 'sliced-invoices' ), sliced_get_quote_label() ) . '" class="accept_quote btn btn-success btn-sm thickbox">' . sprintf( esc_html__( 'Accept %s', 'sliced-invoices' ), sliced_get_quote_label() ) . '</a> ';
				$output .= '<a href="#TB_inline?height=300&width=450&inlineId=sliced_decline_quote" title="' . sprintf( esc_html__( 'Decline %s', 'sliced-invoices' ), sliced_get_quote_label() ) . '" class="decline_quote btn btn-danger btn-sm thickbox">' . sprintf( esc_html__( 'Decline %s', 'sliced-invoices' ), sliced_get_quote_label() ) . '</a> ';

			}

		}
		
		echo apply_filters( 'sliced_quote_accept_decline_buttons', $output );

	}


	/**
	 * Accept Quote popup form
	 *
	 * @version 3.9.0
	 * @since   2.10
	 */
	public function create_accept_quote_popup() {

		$payments = get_option( 'sliced_payments' );
		$quotes = get_option( 'sliced_quotes' );
		$text = isset( $quotes['accept_quote_text'] ) ? $quotes['accept_quote_text'] : '';
		?>

		<div id="sliced_accept_quote" style="display:none">

			<div class="sliced_accept_quote_form_wrap">

				<ul>
					<li><span><?php printf( esc_html__( '%s Number', 'sliced-invoices' ), sliced_get_quote_label() ); ?></span> <div class="quote-number"><?php esc_html_e( sliced_get_quote_prefix() ); ?><?php esc_html_e( sliced_get_quote_number() ); ?><?php esc_html_e( sliced_get_quote_suffix() ); ?></div></li>
					<li><span><?php printf( esc_html__( '%s Amount', 'sliced-invoices' ), sliced_get_quote_label() ); ?></span> <div class="quote-amount"><?php echo sliced_get_quote_total(); ?></div></li>
				</ul>

				<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments['payment_page'] ) ) ?>">
					<?php do_action( 'sliced_before_accept_quote_form_fields' ) ?>

					<?php wp_nonce_field( 'sliced_invoices_accept_quote_' . get_the_ID(), 'sliced_client_accept_quote_nonce' ); ?>
					<input type="hidden" name="sliced_accept_quote_id" id="sliced_accept_quote_id" value="<?php the_ID(); ?>">
					<input type="submit" name="accept-quote" class="btn btn-success btn-lg" id="accept-quote" value="<?php printf( esc_html__( 'Accept %s', 'sliced-invoices' ), sliced_get_quote_label() ); ?>">

					<div class="accept_quote_text"><?php echo wp_kses_post( $text ) ?></div>
					<?php do_action( 'sliced_after_accept_quote_form_fields' ) ?>
				</form>

				<?php do_action( 'sliced_after_accept_quote_form' ) ?>

			</div>

		</div>

		<script type="text/javascript">
			( function( $ ) {
				$( document ).ready( function(){
					
					$( '.sliced_accept_quote_form_wrap form' ).on( 'submit', function(){
						$( '#accept-quote' ).prop( 'disabled', true );
					});
					
				});
			} )( jQuery );
		</script>

		<?php

	}

	/**
	 * Decline Quote popup form
	 *
	 * @version 3.9.0
	 * @since   2.10
	 */
	public function create_decline_quote_popup() {
		#region create_decline_quote_popup
		
		$payments_settings       = get_option( 'sliced_payments' );
		$quotes_settings         = get_option( 'sliced_quotes' );
		$decline_reason_required = isset( $quotes_settings['decline_reason_required'] )
			&& $quotes_settings['decline_reason_required'] === 'on' ? true : false;
		
		?>
		<div id="sliced_decline_quote" style="display: none;">
			
			<div class="sliced_decline_quote_form_wrap">
				
				<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments_settings['payment_page'] ) ) ?>">
					<?php do_action( 'sliced_before_decline_quote_form_fields' ) ?>
					<?php wp_nonce_field( 'sliced_invoices_decline_quote_' . get_the_ID(), 'sliced_decline_quote_nonce' ); ?>
					<input type="hidden" name="sliced_decline_quote_id" id="sliced_decline_quote_id" value="<?php the_ID(); ?>">
					<p>
						<?php _e( 'Reason for declining', 'sliced-invoices' ); ?>
						<?php if ( $decline_reason_required ): ?> <span class="sliced_form_field_required">*</span><?php endif; ?>
					</p>
					<textarea name="decline_quote_reason" id="decline_quote_reason" cols="30" rows="5"></textarea>
					<input type="submit" name="decline-quote" class="btn btn-danger btn-lg" id="decline-quote" value="<?php printf( esc_html__( 'Decline %s', 'sliced-invoices' ), sliced_get_quote_label() ); ?>">
					<?php do_action( 'sliced_after_decline_quote_form_fields' ) ?>
				</form>
				
				<?php do_action( 'sliced_after_decline_quote_form' ) ?>
				
			</div>
			
		</div>
		
		<script type="text/javascript">
			( function( $ ) {
				$( document ).ready( function(){
					
					$( '.sliced_decline_quote_form_wrap form' ).on( 'submit', function(){
						$( '#decline-quote' ).prop( 'disabled', true );
					});
					
					<?php if ( $decline_reason_required ): ?>
						$( '#decline-quote' ).prop( 'disabled', true );
						$( '#decline_quote_reason' ).on( 'change keyup', function(){
							if ( $( '#decline_quote_reason' ).val().length > 0 ) {
								$( '#decline-quote' ).prop( 'disabled', false );
							} else {
								$( '#decline-quote' ).prop( 'disabled', true );
							}
						});
					<?php endif; ?>
					
				});
			} )( jQuery );
		</script>
		
		<?php
		#endregion create_decline_quote_popup
	}
	
	/**
	 * Handle actions when client declines quote from frontend.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function client_decline_quote() {
		
		/*
		 * Do the checks
		 */
		if ( ! isset( $_POST['sliced_decline_quote_nonce'] ) ) {
			return;
		}
		
		if ( ! isset( $_POST['sliced_decline_quote_id'] ) ) {
			return;
		}
		
		$id = intval( $_POST['sliced_decline_quote_id'] );
		
		if ( 
			! $id 
			|| ! wp_verify_nonce( $_POST['sliced_decline_quote_nonce'], 'sliced_invoices_decline_quote_' . $id )
		) {
			wp_die( __( 'Oops, something went wrong, please try again later.', 'sliced-invoices' ) );
		}
		
		$error = false;
		
		if ( has_term( 'accepted', 'quote_status', $id ) || get_post_type( $id ) === 'sliced_invoice' ) {
			$error = 'accepted';
		} elseif ( has_term( 'cancelled', 'quote_status', $id ) ) {
			$error = 'cancelled';
		} elseif ( has_term( 'declined', 'quote_status', $id ) ) {
			$error = 'already_declined';
		} elseif ( has_term( 'expired', 'quote_status', $id ) ) {
			$error = 'expired';
		}
		
		if ( $error ) {
			$message = '<h2>' . __( 'Error', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';
			switch ( $error ) {
				case 'accepted':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Sorry, this %s was already accepted. Please contact us for assistance.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
				case 'cancelled':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Sorry, this %s was cancelled. Please contact us for assistance.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
				case 'already_declined':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'This %s has already been declined.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
				case 'expired':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Sorry, this %s has expired. Please contact us for assistance.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
			}
			$message .= '<br />';
			$message .= sprintf( '<a href="%s">', esc_url( sliced_get_the_link( $id ) ) );
			$message .= sprintf(
				/* translators: %s is a placeholder for the localized word "Quote" (singular) */
				__( 'Go back to %s', 'sliced-invoices' ),
				sliced_get_quote_label()
			);
			$message .= '</a>';
			$message .= '</p>';
			sliced_print_message( $id, $message, 'error' );
			return;
		}
		
		/*
		 * Add the reason to the database
		 */
		$reason = esc_textarea( $_POST['decline_quote_reason'] );
		update_post_meta( $id, '_sliced_declined_reason', $reason );

		/*
		 * Set the status as declined
		 */
		Sliced_Quote::set_as_declined( $id );

		do_action( 'sliced_client_declined_quote', $id, $reason );

		/*
		 * Create and display the success message
		 */
		$quotes = get_option( 'sliced_quotes' );
		if ( $quotes['declined_quote_message'] > '' ) {
			$message = wp_kses_post( $quotes['declined_quote_message'] );
		} else {
			$message .= '<p>';
			$message .= sprintf(
				/* translators: %s is a placeholder for the localized word "Quote" (singular) */
				__( 'You have declined the %s.<br>We will be in touch shortly.', 'sliced-invoices' ),
				sliced_get_quote_label()
			);
			$message .= '</p>';
		}

		$message = apply_filters( 'sliced_decline_quote_message', $message, $id );

		sliced_print_message( $id, $message, 'failed' );

	}
	
	/**
	 * Handle actions when client accepts quote from frontend.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function client_accept_quote() {
		
		/*
		 * Do the checks
		 */
		if ( ! isset( $_POST['sliced_client_accept_quote_nonce'] ) ) {
			return;
		}
		
		if ( ! isset( $_POST['sliced_accept_quote_id'] ) ) {
			return;
		}
		
		$id = intval( $_POST['sliced_accept_quote_id'] );
		
		if ( 
			! $id 
			|| ! wp_verify_nonce( $_POST['sliced_client_accept_quote_nonce'], 'sliced_invoices_accept_quote_' . $id )
		) {
			wp_die( __( 'Oops, something went wrong, please try again later.', 'sliced-invoices' ) );
		}
		
		$error = false;
		
		if ( has_term( 'accepted', 'quote_status', $id ) || get_post_type( $id ) === 'sliced_invoice' ) {
			$error = 'already_accepted';
		} elseif ( has_term( 'cancelled', 'quote_status', $id ) ) {
			$error = 'cancelled';
		} elseif ( has_term( 'declined', 'quote_status', $id ) ) {
			$error = 'declined';
		} elseif ( has_term( 'expired', 'quote_status', $id ) ) {
			$error = 'expired';
		}
		
		if ( $error ) {
			$message = '<h2>' . __( 'Error', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';
			switch ( $error ) {
				case 'already_accepted':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'This %s has already been accepted.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
				case 'cancelled':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Sorry, this %s was cancelled. Please contact us for assistance.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
				case 'declined':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Sorry, this %s was declined. Please contact us for assistance.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
				case 'expired':
					$message .= sprintf(
						/* translators: %s is a placeholder for the localized word "Quote" (singular) */
						__( 'Sorry, this %s has expired. Please contact us for assistance.', 'sliced-invoices' ),
						sliced_get_quote_label()
					);
					break;
			}
			$message .= '<br />';
			$message .= sprintf( '<a href="%s">', esc_url( sliced_get_the_link( $id ) ) );
			$message .= sprintf(
				/* translators: %s is a placeholder for the localized word "Quote" (singular) */
				__( 'Go back to %s', 'sliced-invoices' ),
				sliced_get_quote_label()
			);
			$message .= '</a>';
			$message .= '</p>';
			sliced_print_message( $id, $message, 'error' );
			return;
		}
		
		/*
		 * Do the appropriate action(s) upon quote acceptance
		 */
		do_action( 'sliced_invoices_client_before_accept_quote', $id );
		
		$new_post_id     = false;
		$settings_quotes = get_option( 'sliced_quotes' );
		
		if (
			$settings_quotes['accepted_quote_action'] === 'convert'
			|| $settings_quotes['accepted_quote_action'] === 'convert_send'
			|| empty( $settings_quotes['accepted_quote_action'] )
		) {
			
			// convert
			Sliced_Shared::convert_quote_to_invoice( $id );
			
			// maybe send it
			if ( $settings_quotes['accepted_quote_action'] === 'convert_send' ) {
				$sliced_notifications = Sliced_Notifications::get_instance();
				$sliced_notifications->send_the_invoice( $id );
			}
			
		} elseif (
			$settings_quotes['accepted_quote_action'] === 'duplicate'
			|| $settings_quotes['accepted_quote_action'] === 'duplicate_send'
		) {
			
			// duplicate
			$new_post_id = Sliced_Shared::create_invoice_from_quote( $id );
			
			// mark the quote as accepted
			wp_set_object_terms( $id, 'Accepted', 'quote_status' );
			update_post_meta( $id, '_sliced_related_invoice_id', $new_post_id );
			do_action( 'sliced_quote_status_update', $id, 'Accepted' );
			
			// maybe send it
			if ( $settings_quotes['accepted_quote_action'] === 'duplicate_send' ) {
				$sliced_notifications = Sliced_Notifications::get_instance();
				$sliced_notifications->send_the_invoice( $new_post_id );
			}
			
		} else {
			
			// do nothing... just mark as accepted
			wp_set_object_terms( $id, 'Accepted', 'quote_status' );
			do_action( 'sliced_quote_status_update', $id, 'Accepted' );
			
		}
		
		/*
		 * The following applies to all accepted quote actions, including "do nothing"
		 */
		do_action( 'sliced_client_accepted_quote', $id, $new_post_id );
		
		/*
		 * Create and display the success message
		 */
		if ( isset( $settings_quotes['accepted_quote_message'] ) && $settings_quotes['accepted_quote_message'] > '' ) {
			$message = wp_kses_post( $settings_quotes['accepted_quote_message'] );
		} else {
			$message = '<h2>' . __( 'Success', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';
			$message .= sprintf(
				/* translators: %s is a placeholder for the localized word "Quote" (singular) */
				__( 'You have accepted the %s.<br>We will be in touch shortly.', 'sliced-invoices' ),
				sliced_get_quote_label()
			);
			$message .= '</p>';
		}
		
		$message = apply_filters( 'sliced_convert_quote_success_message', $message, $id );
		
		sliced_print_message( $id, $message, 'success' );
		
	}
	
	/**
	 * Update bits and pieces after payment complete.
	 *
	 * @since   2.0.0
	 */
	public function completed_payment( $id, $gateway, $status ) {

		/**
		 * Mark item as paid on success
		 */
		if( $status == 'success' ) {
			Sliced_Invoice::set_as_paid( $id );
		}

		/**
		 * Send notifications
		 */
		do_action( 'sliced_send_payment_notification', $id, $status );


	}


}

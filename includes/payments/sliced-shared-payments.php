<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_payments_class() {
	new Sliced_Payments();
}
add_action( 'sliced_loaded', 'sliced_call_payments_class' );


/**
 * The Class.
 */
class Sliced_Payments {


	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		add_action( 'sliced_invoice_top_bar_left', array( $this, 'get_gateway_buttons') );
		//add_filter( 'sliced_invoice_footer', array( $this, 'create_payment_popup') );

		add_action( 'sliced_quote_top_bar_left', array( $this, 'get_accept_decline_quote_buttons') );
		add_filter( 'sliced_quote_footer', array( $this, 'create_accept_quote_popup') );
		add_filter( 'sliced_quote_footer', array( $this, 'create_decline_quote_popup') );

		add_action( 'sliced_payment_made', array( $this, 'completed_payment'), 10, 3 );

		//add_action( 'init', array( $this, 'convert_quote_to_invoice') );
		add_action( 'sliced_do_payment', array( $this, 'client_accept_quote') );
		add_action( 'sliced_do_payment', array( $this, 'client_decline_quote') );


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
				$online_gateways[$gateway] = $all_gateways[$gateway];
			}
		}

		// create the buttons
		if( ! empty( $online_gateways ) ) {
			foreach ( $online_gateways as $gateway => $readable) {
				$this->create_payment_button_inline( $gateway, $readable );
			}
		}

	}



	/**
	 * Payment button inline form
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
	 * Payment popup form
	 * DEPRECATED as of 3.1.0, may be removed in the future
	 * @since   2.0.0
	 */
	public function create_payment_popup() {

		$payments = get_option( 'sliced_payments' );
		?>

		<div id="sliced_payment_form" style="display:none">

			<div class="sliced_payment_form_wrap">

				<ul>
					<li><span><?php _e( 'Invoice Number', 'sliced-invoices' ); ?></span> <?php esc_html_e( sliced_get_invoice_prefix() ); ?><?php esc_html_e( sliced_get_invoice_number() ); ?></li>
					<li><span><?php _e( 'Amount Payable', 'sliced-invoices' ); ?></span> <?php _e( sliced_get_invoice_total() ); ?></li>
					<li><span><?php _e( 'Payment Method', 'sliced-invoices' ); ?></span> <span id="sliced_gateway_readable"></span></li>
				</ul>

				<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments['payment_page'] ) ) ?>">
					<?php do_action( 'sliced_before_payment_form_fields' ) ?>

					<?php wp_nonce_field( 'sliced_invoices_payment', 'sliced_payment_nonce' ); ?>
					<input type="hidden" name="sliced_payment_invoice_id" id="sliced_payment_invoice_id" value="<?php the_ID(); ?>">
					<input type="hidden" name="sliced_gateway" id="sliced_gateway" />
					<input type="submit" name="start-payment" class="btn btn-success btn-lg" id="start-payment" value="<?php _e( 'Pay Now', 'sliced-invoices' ) ?>">

					<?php do_action( 'sliced_after_payment_form_fields' ) ?>
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

						var readable = $( this ).data( 'gateway-readable' );
						$( '#sliced_gateway_readable' ).html( readable );

						var gateway  = $( this ).data( 'gateway' );
						$( '#sliced_gateway' ).val( gateway );

						var src = "<?php echo plugin_dir_url( dirname( dirname( __FILE__ ) ) ) ?>public/images/accept-" + gateway + ".png";
						$( '#sliced_gateway_image' ).html( '<img src="' + src + '" />' );

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

		if( has_term( array( 'declined', 'cancelled' ), 'quote_status' ) )
			return;

		// get the quote options
		$quotes = get_option( 'sliced_quotes' );
		$accept = isset( $quotes['accept_quote'] ) ? $quotes['accept_quote'] : '';

		if( ! empty( $accept ) && $accept == 'on' ) { ?>

			<a href="#TB_inline?height=300&width=450&inlineId=sliced_accept_quote" title="<?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Accept Quote', 'Accept Quote', 'sliced-invoices' ); } else { printf( esc_html__( 'Accept This %s', 'sliced-invoices' ), sliced_get_quote_label() ); } ?>" class="accept_quote btn btn-success btn-sm thickbox"><?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Accept Quote', 'Accept Quote', 'sliced-invoices' ); } else { printf( esc_html__( 'Accept %s', 'sliced-invoices' ), sliced_get_quote_label() ); } ?></a>

			<a href="#TB_inline?height=300&width=450&inlineId=sliced_decline_quote" title="<?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Decline Quote', 'Decline Quote', 'sliced-invoices' ); } else { printf( esc_html__( 'Decline This %s', 'sliced-invoices' ), sliced_get_quote_label() ); } ?>" class="decline_quote btn btn-danger btn-sm thickbox"><?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Decline Quote', 'Decline Quote', 'sliced-invoices' ); } else { printf( esc_html__( 'Decline %s', 'sliced-invoices' ), sliced_get_quote_label() ); } ?></a>

		<?php

		}

	}


	/**
	 * Accept Quote popup form
	 *
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
					<li><span><?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Quote Number', 'Quote Number', 'sliced-invoices' ); } else { printf( esc_html__( '%s Number', 'sliced-invoices' ), sliced_get_quote_label() ); } ?></span> <div class="quote-number"><?php esc_html_e( sliced_get_quote_prefix() ); ?><?php esc_html_e( sliced_get_quote_number() ); ?></div></li>
					<li><span><?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Quote Amount', 'Quote Amount', 'sliced-invoices' ); } else { printf( esc_html__( '%s Amount', 'sliced-invoices' ), sliced_get_quote_label() ); } ?></span> <div class="quote-amount"><?php echo sliced_get_quote_total(); ?></div></li>
				</ul>

				<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments['payment_page'] ) ) ?>">
					<?php do_action( 'sliced_before_accept_quote_form_fields' ) ?>

					<?php wp_nonce_field( 'sliced_invoices_accept_quote', 'sliced_client_accept_quote_nonce' ); ?>
					<input type="hidden" name="sliced_accept_quote_id" id="sliced_accept_quote_id" value="<?php the_ID(); ?>">
					<input type="submit" name="accept-quote" class="btn btn-success btn-lg" id="accept-quote" value="<?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Accept Quote', 'Accept Quote', 'sliced-invoices' ); } else { printf( esc_html__( 'Accept %s', 'sliced-invoices' ), sliced_get_quote_label() ); } ?>">

					<div class="accept_quote_text"><?php echo wp_kses_post( $text ) ?></div>
					<?php do_action( 'sliced_after_accept_quote_form_fields' ) ?>
				</form>

				<?php do_action( 'sliced_after_accept_quote_form' ) ?>

			</div>

		</div>

		<?php

	}

	/**
	 * Decline Quote popup form
	 *
	 * @since   2.10
	 */
	public function create_decline_quote_popup() {

		$payments = get_option( 'sliced_payments' );
		?>

		<div id="sliced_decline_quote" style="display:none">

			<div class="sliced_decline_quote_form_wrap">

				<form method="POST" action="<?php echo esc_url( get_permalink( (int)$payments['payment_page'] ) ) ?>">
					<?php do_action( 'sliced_before_decline_quote_form_fields' ) ?>

					<?php wp_nonce_field( 'sliced_invoices_decline_quote', 'sliced_decline_quote_nonce' ); ?>

					<input type="hidden" name="sliced_decline_quote_id" id="sliced_decline_quote_id" value="<?php the_ID(); ?>">
					<p><?php _e( 'Reason for declining', 'sliced-invoices' ); ?>*</p>
					<textarea name="decline_quote_reason" id="decline_quote_reason" cols="30" rows="5"></textarea>
					<input type="submit" name="decline-quote" class="btn btn-danger btn-lg" id="decline-quote" value="<?php if ( class_exists( 'Sliced_Translate' ) ) { echo Sliced_Translate::sliced_translate_some_text( 'Decline Quote', 'Decline Quote', 'sliced-invoices' ); } else { printf( esc_html__( 'Decline %s', 'sliced-invoices' ), sliced_get_quote_label() ); } ?>">

					<?php do_action( 'sliced_after_decline_quote_form_fields' ) ?>
				</form>

				<?php do_action( 'sliced_after_decline_quote_form' ) ?>

			</div>

		</div>

		<script type="text/javascript">
			( function( $ ) {
				$(document).ready(function(){
					$('#decline-quote').prop("disabled", true);
					$('#decline_quote_reason').on("keyup", action);

					function action() {
					   if($('#decline_quote_reason').val().length > 0) {
						  $('#decline-quote').prop("disabled", false);
					   }else {
						  $('#decline-quote').prop("disabled", true);
					   }
					}
				});
			} )( jQuery );
		</script>

		<?php

	}

	/**
	 * Decline the quote action..
	 *
	 * @since   2.0.0
	 */
	public function client_decline_quote() {

		/*
		 * Do the checks
		 */
		if ( ! isset( $_POST['decline-quote'] ) )
			return;

		if ( ! isset( $_POST['sliced_decline_quote_id'] ) )
			return;

		$id = (int) $_POST['sliced_decline_quote_id'];

		if ( ! isset( $_POST['sliced_decline_quote_nonce'] ) || ! wp_verify_nonce( $_POST['sliced_decline_quote_nonce'], 'sliced_invoices_decline_quote') )
			wp_die( "Ooops, something went wrong, please try again later." );

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
			$message = '<h2>' . __( 'Bummer', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';
			$message .= sprintf( wp_kses_post( 'You have declined the %s.<br>We will be in touch shortly.', 'sliced-invoices' ), sliced_get_quote_label() );
			$message .= '</p>';
		}

		$message = apply_filters( 'sliced_decline_quote_message', $message, $id );

		sliced_print_message( $id, $message, 'failed', false );

	}

	/**
	 * Convert from quote to invoice action, on the front end.
	 *
	 * @since   2.0.0
	 */
	public function client_accept_quote() {

		/*
		 * Do the checks
		 */
		if ( ! isset( $_POST['accept-quote'] ) )
			return;

		if ( ! isset( $_POST['sliced_accept_quote_id'] ) )
			return;

		$id = (int) $_POST['sliced_accept_quote_id'];

		if ( ! isset( $_POST['sliced_client_accept_quote_nonce'] ) || ! wp_verify_nonce( $_POST['sliced_client_accept_quote_nonce'], 'sliced_invoices_accept_quote') )
			wp_die( "Ooops, something went wrong, please try again later." );

		/*
		 * Change the post type to invoice
		 */
		set_post_type( $id, 'sliced_invoice' );

		/*
		 * Update the appropriate post meta
		 */
		$invoice = get_option( 'sliced_invoices' );
		$payment = sliced_get_accepted_payment_methods();
		update_post_meta( $id, '_sliced_invoice_terms', $invoice['terms'] );
		update_post_meta( $id, '_sliced_invoice_created', current_time( 'timestamp' ) );
		update_post_meta( $id, '_sliced_invoice_number', sliced_get_next_invoice_number() );
		update_post_meta( $id, '_sliced_invoice_prefix', sliced_get_invoice_prefix() );
		update_post_meta( $id, '_sliced_payment_methods', array_keys($payment) );

		delete_post_meta( $id, '_sliced_quote_created' );
		delete_post_meta( $id, '_sliced_quote_number' );
		delete_post_meta( $id, '_sliced_quote_prefix' );
		delete_post_meta( $id, '_sliced_quote_terms' );

		// update the invoice number
		Sliced_Invoice::update_invoice_number();

		/*
		 * Set the status as draft
		 */
		Sliced_Invoice::set_as_draft( $id );

		do_action( 'sliced_client_accepted_quote', $id );

		/*
		 * Create and display the success message
		 */
		$quotes = get_option( 'sliced_quotes' );
		if ( $quotes['accepted_quote_message'] > '' ) {
			$message = wp_kses_post( $quotes['accepted_quote_message'] );
		} else {
			$message = '<h2>' . __( 'Success', 'sliced-invoices' ) .'</h2>';
			$message .= '<p>';
			$message .= sprintf( wp_kses_post( 'You have accepted the %s.<br>We will be in touch shortly.', 'sliced-invoices' ), sliced_get_quote_label() );
			$message .= '</p>';
		}

		$message = apply_filters( 'sliced_convert_quote_success_message', $message, $id );

		sliced_print_message( $id, $message, 'success', false );

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

<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Calls the class.
 */
function sliced_call_invoice_class() {

	new Sliced_Invoice();

}
add_action('sliced_loaded', 'sliced_call_invoice_class');


class Sliced_Invoice {

	/**
	 * @var  object  Instance of this class
	 */
	private static $instance;

	private static $meta_key = array(

		'items'           => '_sliced_items',
		'prefix'          => '_sliced_invoice_prefix',
		'number'          => '_sliced_invoice_number',
		'order_number'    => '_sliced_order_number',
		'created'         => '_sliced_invoice_created',
		'due'             => '_sliced_invoice_due',
		'email_sent'      => '_sliced_invoice_email_sent',
		'description'     => '_sliced_description',
		'terms'           => '_sliced_invoice_terms',
		'deposit'         => '_sliced_invoice_deposit',
		'payment_methods' => '_sliced_payment_methods',
		'currency'        => '_sliced_currency',
		'client'          => '_sliced_client',

	);


	 public function __construct() {

		add_action( 'admin_init', array( $this, 'update_invoice_number' ), 1 );

	}


	 public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Set the status of an item
	 *
	 * @since   2.0.0
	 */
	public static function set_status( $status, $id ) {

		// check status exists
		$term_id = term_exists( $status, 'invoice_status' );
		if ( ! $term_id ) {
			return;
		}
		// do the update
		$set = wp_set_post_terms( Sliced_Shared::get_item_id( $id ), $term_id, 'invoice_status' );
		do_action( 'sliced_invoice_status_update', Sliced_Shared::get_item_id( $id ), $status );

	}


	/**
	 * Change status to paid.
	 *
	 * @since   2.0.0
	 */
	public static function set_as_paid( $id ) {
		self::set_status( 'paid', $id );
	}

	/**
	 * Change status to draft.
	 *
	 * @since   2.0.0
	 */
	public static function set_as_draft( $id ) {
		self::set_status( 'draft', $id );
	}

	/**
	 * Change status to unpaid.
	 *
	 * @since   2.0.0
	 */
	public static function set_as_unpaid( $id ) {
		// set as unpaid if it is currently a 'draft' or has no status
		// we don't want to change it if cancelled, paid, or overdue are present
		if ( ( has_term( 'draft', 'invoice_status', $id ) || ! has_term( array(), 'invoice_status', $id ) ) && ! has_term( array( 'overdue' ), 'invoice_status', $id ) ) {
			self::set_status( 'unpaid', $id );
		}
	}

	/**
	 * Change status to paid.
	 * run on admin_init within admin class
	 *
	 * @since   2.0.0
	 */
	public static function set_as_overdue( $id ) {
		self::set_status( 'overdue', $id );
	}



	/**
	 * Get the post meta
	 *
	 * @since   2.0.0
	 */
	private static function get_sliced_meta( $id = 0, $key, $single = true ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$meta = get_post_meta( $id, $key, $single );
		return $meta;
	}

	public static function get_created_date( $id = 0 ) {
		$date = (int)self::get_sliced_meta( $id, self::$meta_key['created'] );
		return $date;
	}

	public static function get_due_date( $id = 0 ) {
		$date = (int) self::get_sliced_meta( $id, self::$meta_key['due'] );
		return $date;
	}

	public static function get_email_sent_date( $id = 0 ) {
		$date = (int)self::get_sliced_meta( $id, self::$meta_key['email_sent'] );
		return $date;
	}

	public static function get_number( $id = 0 ) {
		$number = self::get_sliced_meta( $id, self::$meta_key['number'] );
		return $number;
	}

	public static function get_order_number( $id = 0 ) {
		$order_number = self::get_sliced_meta( $id, self::$meta_key['order_number'] );
		return $order_number;
	}

	public static function get_description( $id = 0 ) {
		$description = self::get_sliced_meta( $id, self::$meta_key['description'] );
		return $description;
	}

	public static function get_deposit( $id = 0 ) {
		$deposit = self::get_sliced_meta( $id, self::$meta_key['deposit'] );
		return $deposit;
	}

	public static function get_payment_methods( $id = 0 ) {
		$payment_methods = self::get_sliced_meta( $id, self::$meta_key['payment_methods'], false );
		return $payment_methods;
	}

	public static function get_terms() {
		$id = Sliced_Shared::get_item_id();

		if ( isset( $id ) && 'auto-draft' !== get_post( $id )->post_status ) {
			$terms = self::get_sliced_meta( $id, self::$meta_key['terms'] );
		} else {
			$invoices = get_option( 'sliced_invoices' );
			$terms    = isset( $invoices['terms'] ) ? $invoices['terms'] : '';
		}
		return $terms;
	}

	public static function get_prefix( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$prefix = null;
		if ( isset( $id ) ) {
			$prefix = self::get_sliced_meta( $id, self::$meta_key['prefix'], true );
		}

		if ( ! $prefix ) {
			$invoices = get_option( 'sliced_invoices' );
			$prefix   = isset( $invoices['prefix'] ) ? $invoices['prefix'] : '';
		}
		return $prefix;
	}


	/**
	 * Get the invoice template.
	 *
	 * @since   2.0.0
	 */
	public static function get_template() {
		$invoices = get_option( 'sliced_invoices' );
		$template = isset( $invoices['template'] ) ? $invoices['template'] : 'template1';
		return $template;
	}

	/**
	 * Get the invoice custom css.
	 *
	 * @since   2.0.0
	 */
	public static function get_css() {
		$invoices 	= get_option( 'sliced_invoices' );
		$css 		= isset( $invoices['css'] ) ? $invoices['css'] : '';
		return $css;
	}


	/**
	 * Get the watermark for the invoice (if any).
	 *
	 * @since   2.0.0
	 */
	public static function get_invoice_watermark( $id ) {

		$id = Sliced_Shared::get_item_id();

		if( has_term( 'paid', 'invoice_status', $id ) ) {
			return __( 'Paid', 'sliced-invoices' );
		}
		if( has_term( 'cancelled', 'invoice_status', $id ) ) {
			return __( 'Cancelled', 'sliced-invoices' );
		}

	}


	/**
	 * Get the last invoice number that was used.
	 *
	 * @since   2.0.0
	 */
	public static function get_last_number() {

		$last_number = null;
		//$invoices    = get_option( 'sliced_invoices' );
		//$prefix      = $invoices['prefix'];

		$args = array(
			'post_type'      => 'sliced_invoice',
			'post_status'    => array( 'publish', 'future'  ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// 'meta_query' => array(
			// 	'relation' => 'OR',
			// 	// only get invoices matching the current prefix as the prefix can change year to year
			// 	array(
			// 		'key'     => '_sliced_invoice_prefix',
			// 		'value'   => $prefix,
			// 		'compare' => '=',
			// 	),
			// 	array(
			// 		'key'     => '_sliced_invoice_prefix',
			// 		'compare' => 'NOT EXISTS',
			// 	),
			// ),
		);

		$the_query = new WP_Query( $args );
		$ids = array();
		if( $the_query->posts ) :
			foreach ( $the_query->posts as $id ) {
				$number = sliced_get_invoice_number( $id );
				$ids[$id] = $number;
			};
		endif;
		if( ! empty( $ids ) ) {
			$last_number = max($ids);
		} else {
			$last_number = null;
		}

		wp_reset_postdata();
		return $last_number;

	}


	/**
	 * update the invoice number sequentially.
	 *
	 * @since   2.0.0
	 */
	public static function update_invoice_number() {

		$invoices    = get_option( 'sliced_invoices' );
		$last_number = self::get_last_number();

		if( (int)$invoices['number'] <= (int)$last_number ) {

			// clean up the number
			$length     = strlen( (string)$invoices['number'] ); // get the length of the number
			$new_number = (int)$last_number + 1; // increment number
			$number     = zeroise( $new_number, $length ); // return the new number, ensuring correct length (if using leading zeros)

			// set the number in the options as the new, next number and update it.
			$invoices['number'] = (string)$number;
			update_option( 'sliced_invoices', $invoices);

		}

	}


	/**
	 * Get the next invoice number.
	 *
	 * @since   2.0.0
	 */
	public static function get_next_invoice_number() {

		$invoices = get_option( 'sliced_invoices' );
		if ( isset( $invoices['increment'] ) && $invoices['increment'] == 'on' ) {
			return $invoices['number'];
		}
		else {
			return null;
		}

	}


	/**
	 * Automatically get the due date, if set.
	 *
	 * @since   2.07
	 */
	public static function get_auto_due_date() {

		$invoices = get_option( 'sliced_invoices' );
		if ( isset( $invoices['due_date'] ) && $invoices['due_date'] != '' ) {
			return strtotime( '+' . (int)$invoices['due_date'] . ' days' );
		}
		else {
			return null;
		}

	}


	/**
	 * Whether or not to hide the adjustment field on invoice front end.
	 *
	 * @since   2.07
	 */
	public static function hide_adjustment_field() {

		$invoices = get_option( 'sliced_invoices' );
		if ( isset( $invoices['adjustment'] ) && $invoices['adjustment'] == 'on' ) {
			return true;
		}
		else {
			return false;
		}

	}

}

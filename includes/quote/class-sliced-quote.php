<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Calls the class.
 */
function sliced_call_quote_class() {

	new Sliced_Quote();

}
add_action('sliced_loaded', 'sliced_call_quote_class');


class Sliced_Quote {

	/**
	 * @var  object  Instance of this class
	 */
	private static $instance;

	private static $meta_key = array(

		'items'       => '_sliced_items',
		'prefix'      => '_sliced_quote_prefix',
		'number'      => '_sliced_quote_number',
		'created'     => '_sliced_quote_created',
		'valid'       => '_sliced_quote_valid_until',
		'email_sent'  => '_sliced_quote_email_sent',
		'description' => '_sliced_description',
		'terms'       => '_sliced_quote_terms',
		'currency'    => '_sliced_currency',
		'client'      => '_sliced_client',

	);


	public function __construct() {

		add_action( 'admin_init', array( $this, 'update_quote_number' ), 1 );

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
	public static function set_status( $id = 0, $status ) {

		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}

		// check status exists
		$term_id = term_exists( $status, 'quote_status' );

		if ( ! $term_id ) {
			return;
		}

		// do the update
		$set = wp_set_post_terms( $id, $term_id['term_id'], 'quote_status' );
		do_action( 'sliced_quote_status_update', Sliced_Shared::get_item_id( $id ), $status );

	}

	/**
	 * Change status to sent.
	 *
	 * @since   2.0.0
	 */
	public static function set_as_sent( $id = 0 ) {
		$id = Sliced_Shared::get_item_id( $id );
		self::set_status( $id, 'sent' );
	}

	/**
	 * Change status to declined.
	 *
	 * @since   2.0.0
	 */
	public static function set_as_declined( $id = 0 ) {
		self::set_status( $id, 'declined' );
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

	public static function get_valid_date( $id = 0 ) {
		$date = (int) self::get_sliced_meta( $id, self::$meta_key['valid'] );
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

	public static function get_description( $id = 0 ) {
		$description = self::get_sliced_meta( $id, self::$meta_key['description'] );
		return $description;
	}

	public static function get_terms() {
		$id = Sliced_Shared::get_item_id();

		if ( isset( $id ) && 'auto-draft' !== get_post( $id )->post_status ) {
			$terms = self::get_sliced_meta( $id, self::$meta_key['terms'] );
		} else {
			$quotes = get_option( 'sliced_quotes' );
			$terms  = isset( $quotes['terms'] ) ? $quotes['terms'] : '';
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
			$quotes 	= get_option( 'sliced_quotes' );
			$prefix 	= isset( $quotes['prefix'] ) ? $quotes['prefix'] : '';
		}
		return $prefix;

	}


	/**
	  * Get the quote template.
	  *
	  * @since   2.0.0
	  */
	public static function get_template() {
		$quotes 	= get_option( 'sliced_quotes' );
		$template 	= isset( $quotes['template'] ) ? $quotes['template'] : 'template1';
		return $template;
	}


	/**
	  * Get the invoice custom css.
	  *
	  * @since   2.0.0
	  */
	public static function get_css() {
		$quotes 	= get_option( 'sliced_quotes' );
		$css 		= isset( $quotes['css'] ) ? $quotes['css'] : '';
		return $css;
	}


	/**
	  * Get the last quote number that was used.
	  *
	  * @since   2.0.0
	  */
	public static function get_last_number() {

		$last_number = null;
		// $quotes    	 = get_option( 'sliced_quotes' );
		// $prefix      = $quotes['prefix'];

		$args = array(
			'post_type'      => 'sliced_quote',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// 'meta_query' => array(
			// 	'relation' => 'OR',
			// 	// only get invoices matching the current prefix as the prefix can change year to year
			// 	array(
			// 		'key'     => '_sliced_quote_prefix',
			// 		'value'   => $prefix,
			// 		'compare' => '=',
			// 	),
			// 	array(
			// 		'key'     => '_sliced_quote_prefix',
			// 		'compare' => 'NOT EXISTS',
			// 	),
			// ),
		);

		$the_query = new WP_Query( $args );
		$ids = array();
		if( $the_query->posts ) :
			foreach ( $the_query->posts as $id ) {
				$number = sliced_get_quote_number( $id );
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
	  * update the quote number sequentially.
	  *
	  * @since   2.0.0
	  */
	public static function update_quote_number() {

		$quotes      = get_option( 'sliced_quotes' );
		$last_number = self::get_last_number( 'sliced_quote' );

		if( (int)$quotes['number'] <= (int)$last_number ) {

			// clean up the number
			$length     = strlen( (string)$quotes['number'] ); // get the length of the number
			$new_number = (int)$last_number + 1; // increment number
			$number     = zeroise( $new_number, $length ); // return the new number, ensuring correct length (if using leading zeros)

			// set the number in the options as the new, next number and update it.
			$quotes['number'] = (string)$number;
			update_option( 'sliced_quotes', $quotes);

		}

	}


	/**
	 * Get the next invoice number.
	 *
	 * @since   2.0.0
	 */
	public static function get_next_quote_number() {

		$quotes = get_option( 'sliced_quotes' );
		if ( isset( $quotes['increment'] ) && $quotes['increment'] == 'on' ) {
			return $quotes['number'];
		}
		else {
			return null;
		}

	}

	/**
	 * Automatically get the valid until date, if set.
	 *
	 * @since   2.07
	 */
	public static function get_auto_valid_until_date() {

		$quotes = get_option( 'sliced_quotes' );
		if ( isset( $quotes['valid_until'] ) && $quotes['valid_until'] != '' ) {
			return strtotime( '+' . (int)$quotes['valid_until'] . ' days' );
		}
		else {
			return null;
		}

	}

	/**
	  * Whether or not to hide the adjustment field on quotes front end.
	  *
	  * @since   2.07
	  */
	public static function hide_adjustment_field() {

		$quotes = get_option( 'sliced_quotes' );
		if ( isset( $quotes['adjustment'] ) && $quotes['adjustment'] == 'on' ) {
			return true;
		}
		else {
			return false;
		}

	}


}

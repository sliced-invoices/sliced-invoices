<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


if ( ! function_exists( 'sliced_get_the_id' ) ) :

	function sliced_get_the_id() {
		$output = Sliced_Shared::get_item_id();
		return apply_filters( 'sliced_get_the_id', $output );
	}

endif;


if ( ! function_exists( 'sliced_get_the_link' ) ) :

	function sliced_get_the_link( $id = 0) {
		$output = get_the_permalink( $id );
		return apply_filters( 'sliced_get_the_link', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_label' ) ) :

	function sliced_get_label( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_label( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_label( $id );
		}
		return apply_filters( 'sliced_get_label', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_label_plural' ) ) :

	function sliced_get_label_plural( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_label_plural( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_label_plural( $id );
		}
		return apply_filters( 'sliced_get_label_plural', $output, $id );
	}

endif;

if ( ! function_exists( 'sliced_get_number' ) ) :

	function sliced_get_number( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_number( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_number( $id );
		}
		return apply_filters( 'sliced_get_number', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_prefix' ) ) :

	function sliced_get_prefix( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_prefix( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_prefix( $id );
		}
		return apply_filters( 'sliced_get_prefix', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_suffix' ) ) :

	function sliced_get_suffix( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_suffix( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_suffix( $id );
		}
		return apply_filters( 'sliced_get_suffix', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_type' ) ) :

	function sliced_get_the_type( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		return apply_filters( 'sliced_get_type', $type, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_filename' ) ) :

	function sliced_get_filename( $id = 0 ) {
		$output = Sliced_Shared::get_filename( $id );
		return apply_filters( 'sliced_get_filename', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_created' ) ) :

	function sliced_get_created( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_created( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_created( $id );
		}
		return apply_filters( 'sliced_get_created', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_terms_conditions' ) ) :

	function sliced_get_terms_conditions( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		if ( $type == 'invoice' ) {
			$output = sliced_get_invoice_terms( $id );
		} else if ( $type == 'quote' ) {
			$output = sliced_get_quote_terms( $id );
		}
		return apply_filters( 'sliced_get_terms_conditions', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_status' ) ) :

	function sliced_get_status( $id = 0 ) {
		if ( ! $id ) {
			$id = Sliced_Shared::get_item_id();
		}
		$type = Sliced_Shared::get_type( $id );
		$statuses = wp_get_post_terms( $id, $type . '_status', array( "fields" => "names" ) );
		if ( count( $statuses ) > 0 ) { 
			$output = $statuses[0];
		} else {
			$output = '';
		}
		return apply_filters( 'sliced_get_status', $output, $id );
	}

endif;


if ( ! function_exists( 'sliced_get_pre_defined_items' ) ) :

	function sliced_get_pre_defined_items() {
		$output = Sliced_Admin::get_pre_defined_items();
		echo apply_filters( 'sliced_get_pre_defined_items', $output );
	}

endif;


if ( ! function_exists( 'sliced_print_message' ) ) :

	// 2017-01-22: argument $die will be removed in the near future
	function sliced_print_message( $id = null, $message = '', $type = 'success', $die = false ) {

		if ( $message ) {
			$icon = $type == 'success' ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>';
			?>

			<div class="sliced-message <?php esc_attr_e( $type, 'sliced-invoices' ) ?>">
				<?php   echo $icon;
						echo apply_filters( 'sliced_print_message', wp_kses_post( $message ), $id, $type );  ?>
			</div>

		<?php
			if ( $die ) { return; }
		}
	}

endif;


if ( ! function_exists( 'sliced_hide_adjust_field' ) ) :

	function sliced_hide_adjust_field() {
		$type = Sliced_Shared::get_type();
		if ( $type == 'invoice' ) {
			$output = sliced_invoice_hide_adjust_field();
		} else if ( $type == 'quote' ) {
			$output = sliced_quote_hide_adjust_field();
		}
		return apply_filters( 'sliced_hide_adjust_field', $output);
	}

endif;

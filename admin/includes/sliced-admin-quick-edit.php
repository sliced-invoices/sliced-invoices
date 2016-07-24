<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_quick_edit_class() {

	if ( ! is_admin() )
		return;

	new Sliced_Quick_Edit();
}
add_action('sliced_loaded', 'sliced_call_quick_edit_class');


/**
 * The Class.
 */
class Sliced_Quick_Edit {

	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		// initial order
		add_action( 'quick_edit_custom_box', array( $this, 'display_custom_quickedit' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_the_data' ), 10, 2 );
		add_action( 'admin_head-edit.php', array( $this, 'remove_some_fields' ) );
	}

	/**
	 * Hide fields on quick-edit.
	 *
	 * @since   2.0.0
	 */
	public function remove_some_fields() {

		if( ! sliced_get_the_type() )
			return;
		?>
		<script type="text/javascript">
			jQuery(document).ready( function($) {

				$('.inline-edit-date').each(function (i) {
					$(this).hide();
					$(this).next('br').hide();
				});

				$('.inline-edit-status').closest('.inline-edit-group').each(function (i) {
					$(this).hide();
				});

			});
		</script>
		<?php
	}


	/**
	 * Create the custome quick-edit fields.
	 *
	 * @since   2.0.0
	 */
	public function display_custom_quickedit( $column_name, $post_type ) {

		if( ! sliced_get_the_type() )
			return;

		$clients  = Sliced_Admin::get_clients();
		$statuses = Sliced_Admin::get_statuses();
		$id       = Sliced_Shared::get_item_id();
		$type     = sliced_get_the_type( $id );

		?>
		<fieldset class="inline-edit-col-left inline-edit-<?php echo $post_type; ?>">

				<div class="inline-edit-col column-<?php echo $column_name; ?>">

				<label class="inline-edit-group">
				<?php
				switch ( $column_name ) {

					case 'sliced_number': ?>
						<label>
							<span class="title"><?php printf( __( '%s Number', 'sliced-invoices' ), sliced_get_label() ); ?></span>
							<span class="input-text-wrap"><input type="text" value="" name="sliced_number"></span>
						</label>
						<?php if( $type == 'invoice' ) { ?>
							<label>
							<span class="title"><?php _e( 'Order Number', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><input type="text" value="" name="sliced_order_number"></span>
						</label>
						<?php } ?>
							<?php break;

					case 'sliced_client': ?>

						<label>
							<span class="title"><?php _e( 'Client', 'sliced-invoices' ) ?></span>
								<span class="input-text-wrap"><?php
									if ( ! empty( $clients ) ) {
										echo '<select name="sliced_client">';
										foreach ( $clients as $id => $name ) {
											if( $name ) {
												printf('<option value="%s">%s</option>', esc_attr( $id ), esc_html( $name ) );
											}
										}
										echo '</select>';
									}
								?></span>
						</label>

						<?php break;

					case 'taxonomy-' . $type . '_status': ?>

						<label>
							<span class="title"><?php _e( 'Status', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><?php
								if ( ! empty( $statuses ) ) {
									echo '<select name="sliced_status">';
									foreach ( $statuses as $status ) {
										printf('<option value="%s">%s</option>', esc_attr( $status->name ), esc_html($status->name));
									}

									echo '</select>';
								}
							?></span>
						</label>
						<?php break;


					case 'sliced_created': ?>

						<label>
							<span class="title"><?php _e( 'Created', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><input type="text" value="" name="sliced_created"></span>
						</label>

						<?php if( $type == 'quote' ) { ?>
								<label>
								<span class="title"><?php _e( 'Valid Until', 'sliced-invoices' ) ?></span>
								<span class="input-text-wrap"><input type="text" value="" name="sliced_valid"></span>
							</label>
						<?php } else if( $type == 'invoice' ) { ?>
							<label>
								<span class="title"><?php _e( 'Due Date', 'sliced-invoices' ) ?></span>
								<span class="input-text-wrap"><input type="text" value="" name="sliced_due"></span>
							</label>
						<?php } ?>

						<?php break;

					case 'sliced_total': ?>

							<label>
							<span class="title"><?php _e( 'Terms', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><textarea rows="3" name="sliced_terms"></textarea></span>
							</label>

						<?php break;

				}

				?>

				</label>
			</div>

		</fieldset>


		<?php

	}

	/**
    * Work out the date format
    *
    * @since   2.0.0
    */
	private function work_out_date_format( $date ) {

		$format = get_option( 'date_format' );

		if (strpos( $format, 'd/m') !== false) {
			$date = str_replace("/", ".", $date);
		}

		return $date;

	}

	/**
	 * Saving the data
	 *
	 * @since   2.0.0
	 */
	public function save_the_data( $post_id, $post ) {

		// pointless if $_POST is empty
		if ( empty( $_POST ) )
			return $post_id;

		//verify quick edit nonce
		if ( ! isset( $_POST[ '_inline_edit' ] ) )
			return $post_id;

		if ( isset( $_POST[ '_inline_edit' ] ) && ! wp_verify_nonce( $_POST[ '_inline_edit' ], 'inlineeditnonce' ) )
			return $post_id;

		//don't save for autosave
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return $post_id;

		// dont save for revisions
		if ( isset( $post->post_type ) && $post->post_type == 'revision' )
			return $post_id;

			global $wpdb;

			$type = sliced_get_the_type( $post_id );

			$number       = isset( $_POST['sliced_number'] ) ? $_POST['sliced_number'] : '';
			$order_number = isset( $_POST['sliced_order_number'] ) ? $_POST['sliced_order_number'] : '';
			$created      = isset( $_POST['sliced_created'] ) ? $_POST['sliced_created'] : '';
			$due          = isset( $_POST['sliced_due'] ) ? $_POST['sliced_due'] : '';
			$valid        = isset( $_POST['sliced_valid'] ) ? $_POST['sliced_valid'] : '';
			$status       = isset( $_POST['sliced_status'] ) ? $_POST['sliced_status'] : '';
			$terms        = isset( $_POST['sliced_terms'] ) ? $_POST['sliced_terms'] : '';
			$client       = isset( $_POST['sliced_client'] ) ? $_POST['sliced_client'] : '';

			if( $post->post_type == 'sliced_invoice' ) {
				if( ! empty( $order_number ) ) {
					update_post_meta( $post_id, '_sliced_order_number', sanitize_text_field( $order_number  ) );
				}

				if( ! empty( $due ) ) {
	    			$due = $this->work_out_date_format( $due );
					update_post_meta( $post_id, '_sliced_invoice_due', strtotime( esc_html( $due ) ) );
				}
			}

			if( $post->post_type == 'sliced_quote' ) {
				if( ! empty( $valid ) ) {
	    			$valid = $this->work_out_date_format( $valid );
					update_post_meta( $post_id, '_sliced_quote_valid_until', strtotime( esc_html( $valid ) ) );
				}
			}

			if( ! empty( $number ) ) {
				update_post_meta( $post_id, '_sliced_' . $type . '_number', sanitize_text_field( $number ) );
			}

			if( ! empty( $created ) ) {
	    		$created = $this->work_out_date_format( $created );
				update_post_meta( $post_id, '_sliced_' . $type . '_created', strtotime( esc_html( $created ) ) );
			}

			if( ! empty( $client ) ) {
				update_post_meta( $post_id, '_sliced_client', (int) $client );
			}

			if( ! empty( $terms ) ) {
				update_post_meta( $post_id, '_sliced_' . $type . '_terms', sanitize_text_field( $terms ) );
			}

			 $term_id = term_exists( $status, $type . '_status' );
			if ( $term_id ) {
				$set = wp_set_post_terms( $post_id, $term_id, $type . '_status' );
			}

			// force the status to publish - getting some errors on some server setups
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
			$old_status = $post->post_status;
			$post->post_status = 'publish';
			wp_transition_post_status( 'publish', $old_status, $post );

	}

}

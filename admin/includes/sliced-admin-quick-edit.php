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

		if( ! sliced_get_the_type() ) {
			return;
		}
		
		?>
		<script type="text/javascript">
			jQuery(document).ready( function($) {

				$('.inline-edit-date').each(function (i) {
					if ( 
						! $(this).parents( 'fieldset.inline-edit-sliced_invoice' ).length &&
						! $(this).parents( 'fieldset.inline-edit-sliced_quote' ).length
					) {
						$(this).hide();
						$(this).next('br').hide();
					}
				});

				$('.inline-edit-status').closest('.inline-edit-group').each(function (i) {
					$(this).hide();
				});

			});
		</script>
		<?php
	}


	/**
	 * Create the custom quick-edit fields.
	 *
	 * @since   2.0.0
	 */
	public function display_custom_quickedit( $column_name, $post_type ) {
		
		global $wp_locale;

		if( ! sliced_get_the_type() ) {
			return;
		}

		$clients  = Sliced_Admin::get_clients();
		$statuses = Sliced_Admin::get_statuses();
		$id       = Sliced_Shared::get_item_id();
		$type     = sliced_get_the_type( $id );
		
		$translate = get_option( 'sliced_translate' );

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
										printf(
											'<option value="%s">%s</option>',
											esc_attr( $status->slug ),
											( ( isset( $translate[$status->slug] ) && class_exists( 'Sliced_Translate' ) ) ? $translate[$status->slug] : __( ucfirst( $status->name ), 'sliced-invoices' ) )
										);
									}

									echo '</select>';
								}
							?></span>
						</label>
						<?php break;


					case 'sliced_created': ?>

						<label>
							<span class="title"><?php _e( 'Created', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><fieldset class="inline-edit-date">
							<?php

							$month = '<label><span class="screen-reader-text">' . __( 'Month' ) . '</span><select ' . 'name="sliced_created_m"' . ">\n";
							$month .= '<option value="0"></option>' . "\n";
							for ( $i = 1; $i < 13; $i = $i + 1 ) {
								$monthnum  = zeroise( $i, 2 );
								$monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
								$month    .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '">';
								/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
								$month .= sprintf( __( '%1$s-%2$s' ), $monthnum, $monthtext ) . "</option>\n";
							}
							$month .= '</select></label>';

							$day    = '<label><span class="screen-reader-text">' . __( 'Day' ) . '</span><input type="text" ' . 'name="sliced_created_d" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';
							$year   = '<label><span class="screen-reader-text">' . __( 'Year' ) . '</span><input type="text" ' . 'name="sliced_created_Y" value="" size="4" maxlength="4"' . ' autocomplete="off" /></label>';
							$hour   = '<label><span class="screen-reader-text">' . __( 'Hour' ) . '</span><input type="text" ' . 'name="sliced_created_H" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';
							$minute = '<label><span class="screen-reader-text">' . __( 'Minute' ) . '</span><input type="text" ' . 'name="sliced_created_i" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';

							/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
							printf( __( '%1$s %2$s, %3$s @ %4$s:%5$s' ), $month, $day, $year, $hour, $minute );

							echo '<input type="hidden" name="sliced_created_s" value="" />';

							?>
							</fieldset></span>
						</label>
						
						<?php if ( $type === 'quote' ): ?>
						<label>
							<span class="title"><?php _e( 'Valid Until', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><fieldset class="inline-edit-date">
							<?php

							$month = '<label><span class="screen-reader-text">' . __( 'Month' ) . '</span><select ' . 'name="sliced_valid_m"' . ">\n";
							$month .= '<option value="0"></option>' . "\n";
							for ( $i = 1; $i < 13; $i = $i + 1 ) {
								$monthnum  = zeroise( $i, 2 );
								$monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
								$month    .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '">';
								/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
								$month .= sprintf( __( '%1$s-%2$s' ), $monthnum, $monthtext ) . "</option>\n";
							}
							$month .= '</select></label>';

							$day    = '<label><span class="screen-reader-text">' . __( 'Day' ) . '</span><input type="text" ' . 'name="sliced_valid_d" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';
							$year   = '<label><span class="screen-reader-text">' . __( 'Year' ) . '</span><input type="text" ' . 'name="sliced_valid_Y" value="" size="4" maxlength="4"' . ' autocomplete="off" /></label>';
							$hour   = '<label><span class="screen-reader-text">' . __( 'Hour' ) . '</span><input type="text" ' . 'name="sliced_valid_H" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';
							$minute = '<label><span class="screen-reader-text">' . __( 'Minute' ) . '</span><input type="text" ' . 'name="sliced_valid_i" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';

							/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
							printf( __( '%1$s %2$s, %3$s @ %4$s:%5$s' ), $month, $day, $year, $hour, $minute );

							echo '<input type="hidden" name="sliced_valid_s" value="" />';

							?>
							</fieldset></span>
						</label>
						<?php endif; ?>
						
						<?php if( $type === 'invoice' ): ?>
						<label>
							<span class="title"><?php _e( 'Due Date', 'sliced-invoices' ) ?></span>
							<span class="input-text-wrap"><fieldset class="inline-edit-date">
							<?php

							$month = '<label><span class="screen-reader-text">' . __( 'Month' ) . '</span><select ' . 'name="sliced_due_m"' . ">\n";
							$month .= '<option value="0"></option>' . "\n";
							for ( $i = 1; $i < 13; $i = $i + 1 ) {
								$monthnum  = zeroise( $i, 2 );
								$monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
								$month    .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '">';
								/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
								$month .= sprintf( __( '%1$s-%2$s' ), $monthnum, $monthtext ) . "</option>\n";
							}
							$month .= '</select></label>';

							$day    = '<label><span class="screen-reader-text">' . __( 'Day' ) . '</span><input type="text" ' . 'name="sliced_due_d" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';
							$year   = '<label><span class="screen-reader-text">' . __( 'Year' ) . '</span><input type="text" ' . 'name="sliced_due_Y" value="" size="4" maxlength="4"' . ' autocomplete="off" /></label>';
							$hour   = '<label><span class="screen-reader-text">' . __( 'Hour' ) . '</span><input type="text" ' . 'name="sliced_due_H" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';
							$minute = '<label><span class="screen-reader-text">' . __( 'Minute' ) . '</span><input type="text" ' . 'name="sliced_due_i" value="" size="2" maxlength="2"' . ' autocomplete="off" /></label>';

							/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
							printf( __( '%1$s %2$s, %3$s @ %4$s:%5$s' ), $month, $day, $year, $hour, $minute );

							echo '<input type="hidden" name="sliced_due_s" value="" />';

							?>
							</fieldset></span>
						</label>
						<?php endif; ?>

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
	 * Saving the data.
	 *
	 * @version 3.9.0
	 * @since   2.0.0
	 */
	public function save_the_data( $post_id, $post ) {
		
		// global $wpdb;

		// pointless if $_POST is empty
		if ( empty( $_POST ) ) {
			return $post_id;
		}

		// verify quick edit nonce
		if ( ! isset( $_POST[ '_inline_edit' ] ) || ! wp_verify_nonce( $_POST[ '_inline_edit' ], 'inlineeditnonce' ) ) {
			return $post_id;
		}

		// don't save for autosave
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// don't save for revisions
		if ( isset( $post->post_type ) && $post->post_type === 'revision' ) {
			return $post_id;
		}

		$type = sliced_get_the_type( $post_id );

		$client       = isset( $_POST['sliced_client'] ) ? sanitize_text_field( $_POST['sliced_client'] ) : '';
		$created_d    = isset( $_POST['sliced_created_d'] ) ? sanitize_text_field( $_POST['sliced_created_d'] ) : '';
		$created_m    = isset( $_POST['sliced_created_m'] ) ? sanitize_text_field( $_POST['sliced_created_m'] ) : '';
		$created_Y    = isset( $_POST['sliced_created_Y'] ) ? sanitize_text_field( $_POST['sliced_created_Y'] ) : '';
		$created_H    = isset( $_POST['sliced_created_H'] ) ? sanitize_text_field( $_POST['sliced_created_H'] ) : '';
		$created_i    = isset( $_POST['sliced_created_i'] ) ? sanitize_text_field( $_POST['sliced_created_i'] ) : '';
		$created_s    = isset( $_POST['sliced_created_s'] ) ? sanitize_text_field( $_POST['sliced_created_s'] ) : '';
		$due_d        = isset( $_POST['sliced_due_d'] ) ? sanitize_text_field( $_POST['sliced_due_d'] ) : '';
		$due_m        = isset( $_POST['sliced_due_m'] ) ? sanitize_text_field( $_POST['sliced_due_m'] ) : '';
		$due_Y        = isset( $_POST['sliced_due_Y'] ) ? sanitize_text_field( $_POST['sliced_due_Y'] ) : '';
		$due_H        = isset( $_POST['sliced_due_H'] ) ? sanitize_text_field( $_POST['sliced_due_H'] ) : '';
		$due_i        = isset( $_POST['sliced_due_i'] ) ? sanitize_text_field( $_POST['sliced_due_i'] ) : '';
		$due_s        = isset( $_POST['sliced_due_s'] ) ? sanitize_text_field( $_POST['sliced_due_s'] ) : '';
		$number       = isset( $_POST['sliced_number'] ) ? sanitize_text_field( $_POST['sliced_number'] ) : '';
		$order_number = isset( $_POST['sliced_order_number'] ) ? sanitize_text_field( $_POST['sliced_order_number'] ) : '';
		$status       = isset( $_POST['sliced_status'] ) ? sanitize_text_field( $_POST['sliced_status'] ) : '';
		$terms        = isset( $_POST['sliced_terms'] ) ? wp_kses_post( $_POST['sliced_terms'] ) : '';
		$valid_d      = isset( $_POST['sliced_valid_d'] ) ? sanitize_text_field( $_POST['sliced_valid_d'] ) : '';
		$valid_m      = isset( $_POST['sliced_valid_m'] ) ? sanitize_text_field( $_POST['sliced_valid_m'] ) : '';
		$valid_Y      = isset( $_POST['sliced_valid_Y'] ) ? sanitize_text_field( $_POST['sliced_valid_Y'] ) : '';
		$valid_H      = isset( $_POST['sliced_valid_H'] ) ? sanitize_text_field( $_POST['sliced_valid_H'] ) : '';
		$valid_i      = isset( $_POST['sliced_valid_i'] ) ? sanitize_text_field( $_POST['sliced_valid_i'] ) : '';
		$valid_s      = isset( $_POST['sliced_valid_s'] ) ? sanitize_text_field( $_POST['sliced_valid_s'] ) : '';
		
		if ( $post->post_type === 'sliced_invoice' ) {
		
			$due = '';
			if ( $due_d > '' && $due_m > '' && $due_Y > '' ) {
				$due = Sliced_Shared::get_timestamp_from_local_time( $due_Y, $due_m, $due_d, $due_H, $due_i, $due_s );
			}
			
			update_post_meta( $post_id, '_sliced_invoice_due', $due );
			update_post_meta( $post_id, '_sliced_order_number', $order_number );
			
		}
		
		if ( $post->post_type === 'sliced_quote' ) {
		
			$valid = '';
			if ( $valid_d > '' && $valid_m > '' && $valid_Y > '' ) {
				$valid_H = $valid_H > '' ? $valid_H : '23';
				$valid_i = $valid_i > '' ? $valid_i : '59';
				$valid_s = $valid_s > '' ? $valid_s : '59';
				$valid = Sliced_Shared::get_timestamp_from_local_time( $valid_Y, $valid_m, $valid_d, $valid_H, $valid_i, $valid_s );
			}
			
			update_post_meta( $post_id, '_sliced_quote_valid_until', $valid );
			
		}
		
		$created = '';
		if ( $created_d > '' && $created_m > '' && $created_Y > '' ) {
			$created = Sliced_Shared::get_timestamp_from_local_time( $created_Y, $created_m, $created_d, $created_H, $created_i, $created_s );
		}
		
		update_post_meta( $post_id, '_sliced_client', $client );
		update_post_meta( $post_id, '_sliced_' . $type . '_created', $created );
		update_post_meta( $post_id, '_sliced_' . $type . '_number', $number );
		update_post_meta( $post_id, '_sliced_' . $type . '_terms', $terms );
		
		$term_id = term_exists( $status, $type . '_status' );
		if ( $term_id ) {
			$set = wp_set_post_terms( $post_id, $term_id, $type . '_status' );
		}

		// force the status to publish - getting some errors on some server setups
		/*
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		$old_status = $post->post_status;
		$post->post_status = 'publish';
		wp_transition_post_status( 'publish', $old_status, $post );
		*/
		do_action( 'sliced_quick_edit_save_the_data', $post_id, $post );
		
		return $post_id;

	}

}

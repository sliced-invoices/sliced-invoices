<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_columns_class() {

	// just a double check
	if( ! is_admin() )
		return;

	if ( sliced_get_the_type() ) {

		new Sliced_Columns();

	}
}
add_action('load-edit.php', 'sliced_call_columns_class');


/**
 * The Class.
 */
class Sliced_Columns {


	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		// initial order and filtering
		add_action( 'pre_get_posts', array( $this, 'initial_orderby_filtering' ) );
		/* Validate orderby and filters. */
		add_filter('request', array( $this, 'ordering') );
		/* Add dropdown filters. */
		add_action('restrict_manage_posts', array( $this, 'show_filters') );

		/* Modify the columns on the "quotes" screen. */
		add_filter('manage_edit-sliced_quote_columns', array( $this, 'manage_edit_columns') );
		add_filter('manage_edit-sliced_invoice_columns', array( $this, 'manage_edit_columns') );
		add_action('manage_sliced_quote_posts_custom_column', array( $this, 'manage_posts_custom_column'), 10, 2);
		add_action('manage_sliced_invoice_posts_custom_column', array( $this, 'manage_posts_custom_column'), 10, 2);
		add_filter('manage_edit-sliced_quote_sortable_columns', array( $this, 'manage_edit_sortable_columns') );
		add_filter('manage_edit-sliced_invoice_sortable_columns', array( $this, 'manage_edit_sortable_columns') );

		// adds links to top of quotes and invoices list for each term
		add_filter('views_edit-sliced_quote', array( $this, 'edit_posts_views'), 10, 2);
		add_filter('views_edit-sliced_invoice', array( $this, 'edit_posts_views'), 10, 2);

	}


	/**
	 * Create the columns
	 *
	 * @since   2.0.0
	 */
	public function manage_edit_columns( $post_columns ) {

		$post_type 	= $_GET['post_type'];

		$columns    = array();
		$taxonomies = array();

		/* Adds the checkbox column. */
		$columns['cb'] = '<input type="checkbox" />';

		/* Add custom columns and overwrite the 'title' column. */
		$columns['title']         = __('Title', 'sliced-invoices');
		$columns['sliced_number'] = __('Number', 'sliced-invoices');
		$columns['sliced_client'] = __('Client', 'sliced-invoices');

		/* Get taxonomies that should appear in the manage posts table. */
		$taxonomies = get_object_taxonomies($post_type, 'objects');
		$taxonomies = wp_filter_object_list($taxonomies, array( 'show_admin_column' => true ), 'and', 'name');

		/* Allow devs to filter the taxonomy columns. */
		$taxonomies = apply_filters("manage_taxonomies_for_sliced_{$post_type}_columns", $taxonomies, $post_type);
		$taxonomies = array_filter($taxonomies, 'taxonomy_exists');

		/* Loop through each taxonomy and add it as a column. */
		foreach ( $taxonomies as $taxonomy ) {
			$columns[ 'taxonomy-' . $taxonomy ] = get_taxonomy($taxonomy)->labels->name;
		}

		/* Add after quote status. */
		$columns['sliced_created'] = __( 'Created', 'sliced-invoices' );
		$columns['sliced_total']   = __( 'Total', 'sliced-invoices' );
		if( $post_type == 'sliced_quote' ) {
			$columns['comments'] = '<span><span title="Comments" class="vers comment-grey-bubble"><span class="screen-reader-text">Comments</span></span></span>';
		}
		$columns['sliced_actions'] = __( 'Actions', 'sliced-invoices' );
		
		/* allow 3rd-party columns */
		foreach ( $post_columns as $key => $value ) {
			if ( ! isset( $columns[ $key ] ) ) {
				$columns[ $key ] = $value;
			}
		}

		/* Return the columns. */
		return apply_filters( 'sliced_edit_admin_columns', $columns );

	}

	/**
	 * Add data to columns.
	 *
	 * @since   2.0.0
	 */
	public function manage_posts_custom_column( $column, $id ) {

		$type = sliced_get_the_type();

		switch( $column ) {

			case 'sliced_number':

				if ( $type == 'quote' ) {

					echo sliced_get_quote_number() != '' ? '<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . sliced_get_quote_prefix() . sliced_get_quote_number() . '</a>' : '';

				} else {

					echo sliced_get_invoice_number() != '' ? '<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . sliced_get_invoice_prefix() . sliced_get_invoice_number() . '</a>' : '';
					do_action( 'sliced_admin_col_after_invoice_number' );

				}

				break;


			case 'sliced_client':

				if ( sliced_get_client_id( $id ) ) {

					$client_link = admin_url( 'edit.php?post_type=sliced_' . $type . '&sliced_client=' . sliced_get_client_id( $id ) );
					$output = '<a href="' . esc_url( $client_link ) . '">' . sliced_get_client_business() . '</a><br /><span class="description">' . sliced_get_client_email() . '</span>';

				}

				echo ! empty( $output ) ? $output : '&mdash;';

				break;


			case 'sliced_created':

				if ( $type == 'quote' ) {

		        	echo sliced_get_quote_created() ? '<span class="created"><span class="dashicons dashicons-calendar-alt"></span> ' . date_i18n( get_option( 'date_format' ), (int) sliced_get_quote_created() ) . '</span><br />' : '';

		        	echo sliced_get_quote_valid() ? '<span class="description">' . sprintf( __( 'Valid: %s', 'sliced-invoices' ), date_i18n( get_option( 'date_format' ), (int) sliced_get_quote_valid() ) ) . '</span>': '';

				} else {

					echo sliced_get_invoice_created() ? '<span class="created"><span class="dashicons dashicons-calendar-alt"></span> ' . date_i18n( get_option( 'date_format' ), (int) sliced_get_invoice_created() ) . '</span><br />' : '';
					echo sliced_get_invoice_due() ? '<span class="description">' . sprintf( __( 'Due: %s', 'sliced-invoices' ), date_i18n( get_option( 'date_format' ), (int) sliced_get_invoice_due() ) ) . '</span>': '';

				}

				break;


			case 'sliced_total':

				if ( $type == 'quote' ) {

					$total = sliced_get_quote_total() ? '<strong>' . sprintf( __( 'Total: %s', 'sliced-invoices' ), sliced_get_quote_total() ) . '</strong><br />' : '&mdash;<br />';

				} else {

					$total = sliced_get_invoice_total() ? '<strong>' . sprintf( __( 'Total: %s', 'sliced-invoices' ), sliced_get_invoice_total() ) . '</strong><br />' : '&mdash;<br />';

				}

				echo apply_filters( 'sliced_admin_total_column', $total);

				/*
				 * Add custom inline data for quickedit use
				 */
				$valid = sliced_get_quote_valid() ? date_i18n( get_option( 'date_format' ), (int) sliced_get_quote_valid() ) : '';
				$due   = sliced_get_invoice_due() ? date_i18n( get_option( 'date_format' ), (int) sliced_get_invoice_due() ) : '';

				echo '
					<div class="hidden" id="sliced_inline_' . $id . '">
						<div class="number">' . sliced_get_number() . '</div>
						<div class="created">' . date_i18n( get_option( 'date_format' ), (int) sliced_get_created() ) . '</div>
						<div class="client">' . sliced_get_client_id() . '</div>
						<div class="due">' . $due . '</div>
						<div class="valid">' . $valid . '</div>
						<div class="order_number">' . sliced_get_invoice_order_number() . '</div>
						<div class="terms">' . sliced_get_terms_conditions() . '</div>
						<div class="status">' . sliced_get_status() . '</div>
					</div>
				';

				break;


			case 'sliced_actions' :

				$button = '<a target="_blank" title="' . __( 'View or download as a PDF (extension required)', 'sliced-invoices' ) . '" class="button ui-tip sliced-pdf-button" href="https://slicedinvoices.com/extensions/pdf-email?utm_campaign=Free&utm_medium=link&utm_source=plugin&utm_content=pdf-button"><span class="dashicons dashicons-media-default"></span></a>';

				// $button .= '<br /><a target="_blank" href="https://slicedinvoices.com/extensions/pdf-email?utm_campaign=Free&utm_medium=link&utm_source=plugin&utm_content=extension-required"><span class="ui-tip description" title="">' . __( 'Extension Required ', 'sliced-invoices' ) . '</span></a>';

				$button = apply_filters( 'sliced_actions_column', $button );
				echo $button;

			default :
				break;

		 }

	}

	/**
	 * Allow sortable columns.
	 *
	 * @since   2.0.0
	 */
	public function manage_edit_sortable_columns( $columns ) {

		$type = sliced_get_the_type();

		$columns['sliced_number']  	= 'sliced_number';
		$columns['sliced_created']  = 'sliced_created';
		$columns['sliced_total']    = 'sliced_total';
		$columns['taxonomy-' . $type . '_status'] = 'taxonomy-' . $type . '_status';

		return $columns;

	}


	/**
	 * Initial ordering of columns and filtering
	 *
	 * @since   2.0.0
	 */
	public function initial_orderby_filtering( $query ) {

		if ( isset( $_GET['orderby'] ) || isset( $_GET['order'] ) )
			return;

		$query->set('order','DESC');
		$query->set('orderby','post_date');
		//$query->set('meta_key','_' . esc_html( $_GET['post_type'] ) . '_created');
		
		// filtering
		if ( isset( $_GET['sliced_client'] ) && $_GET['sliced_client'] ) {
			$query->query_vars['meta_query'] = array(
				array(
					 'key'      => '_sliced_client',
					 'value'    => (int)$_GET['sliced_client']
				)
			);
		}

		 // filtering
		$type = sliced_get_the_type();
		if ( isset( $_GET[$type . '_status'] ) && $_GET[$type . '_status'] ) {
			$the_query->query_vars['tax_query'] = array(
				array(
					'taxonomy' => $type . '_status',
					'terms'    => $_GET[$type . '_status'],
					'field'    => 'slug',
				)
			);
		}

	}


	/**
	 * Ordering of columns.
	 *
	 * @since   2.0.0
	 */
	public function ordering( $vars ) {

		$type = sliced_get_the_type();

		// ordering by number
		if ( isset( $vars['orderby'] ) && 'sliced_number' === $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'orderby'  => 'meta_value_num',
					'meta_key' => '_' . esc_html( $_GET['post_type'] ) . '_number'
					)
				);
		}

		// ordering by created date
		if ( isset( $vars['orderby'] ) && 'sliced_created' === $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'orderby'  => 'meta_value_num',
					'meta_key' => '_sliced_' . esc_html( $type ) . '_created'
					)
				);
		}

		// ordering by the total
		if ( isset( $vars['orderby'] ) && 'sliced_total' === $vars['orderby'] ) {
			$vars = array_merge(
				$vars,
				array(
					'orderby'  => 'meta_value_num',
					'meta_key' => '_sliced_totals_for_ordering'
					)
				);
		}

		return $vars;
	}


	/**
	 * Display the filter dropdowns.
	 *
	 * @since   2.0.0
	 */
	public function show_filters() {

		// Get the clients filter dropdown
		$clients = Sliced_Admin::get_clients();

		$type = sliced_get_the_type();

		if ( ! empty( $clients ) ) {

			echo '<select name="sliced_client" class="postform">';

			foreach ( $clients as $id => $name ) {
				if( $name ) {
					$selected = isset( $_GET['sliced_client'] ) ? $_GET['sliced_client'] : null;
					 printf('<option value="%s"%s>%s</option>', esc_attr( $id ), selected( $id, $selected, false), esc_html( $name ) );
				}
			}

			echo '</select>';
		}


		// Get the quote status filter dropdown
		$tag   = isset( $_GET[ $type . '_status' ] ) ? esc_attr( $_GET[ $type . '_status' ] ) : '';
		$terms = get_terms( $type . '_status', array( 'hide_empty' => 0 ) );

		if ( ! empty( $terms ) ) {
			echo '<select name="' . esc_attr( $type ) . '_status" class="postform">';

			echo '<option value="" ' . selected('', $tag, false) . ' >' . __( 'View all statuses', 'sliced-invoices' ) . '</option>';

			foreach ( $terms as $term ) {
				printf('<option value="%s"%s>%s (%s)</option>', esc_attr( $term->slug ), selected($term->slug, $tag, false), esc_html($term->name), esc_html($term->count));
			}

			echo '</select>';
		}


		// Export to CSV button
		$url = add_query_arg( array( 'export' => 'csv' ) );
		echo '<a href="' . esc_url( $url ) . '" class="button alignright button-primary" >' . __( 'Export as CSV', 'sliced-invoices' ) . '</a>';


	}


	/**
	 * Add links for the statuses.
	 *
	 * @since   2.0.0
	 */
	public function edit_posts_views( $views ) {

		$type = sliced_get_the_type();

		foreach ( $views as $index => $view ) {
			$views[ $index ] = $views[ $index ];
		}

		$statuses = get_terms( $type . '_status' , array( 'hide_empty' => 0 ) );
		if ( !empty( $statuses ) && !is_wp_error( $statuses ) ) {
			foreach ( $statuses as $status ) {

				if( $status->slug == 'unpaid' )	{
					$status->slug = 'unpaid%2Coverdue';
				}
				$views[$status->slug] = "<a href='" . esc_url( add_query_arg( array( $type . '_status' => $status->slug ) ) ) . "'>" . esc_html( $status->name ) . " <span class='count'>(" . esc_html( $status->count ) . ")</span></a>";
			}
		}

		return apply_filters( 'sliced_admin_col_views', $views );

	}



}

/*----------------------------------------------------------------
Start User Columns
------------------------------------------------------------------
*/

/**
 * Calls the class.
 */
function sliced_call_user_columns_class() {

	// just a double check
	if( ! is_admin() )
		return;

	new Sliced_User_Columns();

}
add_action('load-users.php', 'sliced_call_user_columns_class');

/**
 * The Class.
 */
class Sliced_User_Columns {

	 /**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	 public function __construct() {

		add_action('manage_users_custom_column', array( $this, 'add_custom_user_columns'), 15, 3);
		add_filter('manage_users_columns', array( $this, 'add_user_columns'), 15, 1);
		add_filter('manage_users_sortable_columns', array( $this, 'user_sortable_columns') );
		add_action('pre_user_query', array( $this, 'user_column_orderby') );

	 }


	/**
	 * Add user columns.
	 *
	 * @since   2.0.0
	 */
	public function add_user_columns( $user_columns )  {

		$columns = array();
		foreach ( $user_columns as $key => $value ) {
			$columns[ $key ] = $value;
			// insert business column after user column:
			if ( $key === 'username' ) {
				$columns['sliced_client_business']  = __('Business', 'sliced-invoices');
			}
		}

		return $columns;

	}


	/**
	 * Sorting columns.
	 *
	 * @since   2.0.0
	 */
	public function user_sortable_columns( $columns ) {

		$columns['sliced_client_business'] = 'sliced_client_business';
		return $columns;

	}

	/**
	 * Add the content.
	 *
	 * @since   2.0.0
	 */
	public function add_custom_user_columns( $value, $column_name, $id ) {

		if( $column_name == 'sliced_client_business' ) {

			$client   = get_userdata( $id );
			$business = get_the_author_meta('_sliced_client_business', $id);
			$url = $client->user_url;
			$url = $url ? preg_replace('#^https?://#', '', $url) . ' <span class="dashicons dashicons-admin-links"></span>' : '';

			$billing_client = $business . '<br /><span class="description"><a href="' . esc_url( $client->user_url ) . '" target="_blank">' . $url . '</a></span>';

			return $billing_client;
		}

	}

	/**
	 * Order and filter.
	 *
	 * @since   2.0.0
	 */

	public function user_column_orderby( $user_search ) {
	    
	    global $wpdb,$current_screen;

	    if ( 'users' != $current_screen->id ) 
	        return;

	    $vars = $user_search->query_vars;

	    if('sliced_client_business' == $vars['orderby']) {
	        $user_search->query_from .= " INNER JOIN {$wpdb->usermeta} m1 ON {$wpdb->users}.ID=m1.user_id AND (m1.meta_key='_sliced_client_business')"; 
	        $user_search->query_orderby = ' ORDER BY UPPER(m1.meta_value) '. $vars['order'];
	    } 

	}



}

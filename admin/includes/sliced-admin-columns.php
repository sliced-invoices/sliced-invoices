<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_columns_class() {

	global $pagenow;
	
	// just a double check
	if( ! is_admin() ) {
		return;
	}
		
	$doing_quick_edit = false;
	if ( isset( $_POST[ '_inline_edit' ] ) && wp_verify_nonce( $_POST[ '_inline_edit' ], 'inlineeditnonce' ) ) {
		$doing_quick_edit = true;
	}

	if (
		( $pagenow === 'edit.php' && sliced_get_the_type() ) 
		|| $doing_quick_edit
	) {

		new Sliced_Columns();

	}
}
add_action('admin_init', 'sliced_call_columns_class');


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
		// extended search filtering
		add_action( 'pre_get_posts', array( $this, 'extend_admin_search' ) );
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
		
		// allow translating of status taxonomies
		add_filter( 'get_the_terms', array( $this, 'pre_get_the_terms' ), 10, 3 );

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

		$post_type = false;
		if ( isset( $_GET['post_type'] ) ) {
			$post_type = sanitize_text_field( $_GET['post_type'] );
		} elseif ( isset( $_POST['post_type'] ) ) {
			$post_type = sanitize_text_field( $_POST['post_type'] );
		}
		if ( $post_type !== 'sliced_invoice' && $post_type !== 'sliced_quote' ) {
			return $post_columns;
		}
		
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

					echo sliced_get_quote_number() != '' ? '<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . sliced_get_quote_prefix() . sliced_get_quote_number() . sliced_get_quote_suffix() . '</a>' : '';
					do_action( 'sliced_admin_col_after_quote_number' );

				} else {

					echo sliced_get_invoice_number() != '' ? '<a class="sliced-number" href="' . esc_url( admin_url( 'post.php?post=' . $id ) ) . '&action=edit' . '">' . sliced_get_invoice_prefix() . sliced_get_invoice_number() . sliced_get_invoice_suffix() . '</a>' : '';
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
			
				$created = sliced_get_created();
				$due     = sliced_get_invoice_due();
				$valid   = sliced_get_quote_valid();
				
				if ( $created ) {
					echo '<span class="created"><span class="dashicons dashicons-calendar-alt"></span> ' .
						Sliced_Shared::get_local_date_i18n_from_timestamp( $created )
						. '</span><br />';
				}
				
				if ( $type === 'quote' && $valid > 0 ) {
		        	echo '<span class="description">' . 
						sprintf(
							__( 'Valid: %s', 'sliced-invoices' ),
							Sliced_Shared::get_local_date_i18n_from_timestamp( $valid )
						)
						. '</span>';
				}
				
				if ( $type === 'invoice' && $due > 0 ) {
					echo '<span class="description">' .
						sprintf(
							__( 'Due: %s', 'sliced-invoices' ),
							Sliced_Shared::get_local_date_i18n_from_timestamp( $due )
						)
						. '</span>';
				}
				
				break;


			case 'sliced_total':

				if ( $type === 'quote' ) {

					$total = sliced_get_quote_total() ? '<strong>' . sprintf( __( 'Total: %s', 'sliced-invoices' ), sliced_get_quote_total() ) . '</strong><br />' : '&mdash;<br />';

				} else {

					$total = sliced_get_invoice_total() ? '<strong>' . sprintf( __( 'Total: %s', 'sliced-invoices' ), sliced_get_invoice_total() ) . '</strong><br />' : '&mdash;<br />';

				}

				echo apply_filters( 'sliced_admin_total_column', $total);

				/*
				 * Add custom inline data for quickedit use
				 */
				$created = sliced_get_created();
				$created = $created > 0 ? $created : time(); // if not set, use current time
				$due     = sliced_get_invoice_due();
				$valid   = sliced_get_quote_valid();
				
				$status = '';
				
				$terms = wp_get_post_terms( $id, $type . '_status', array( "fields" => "all" ) );
				if ( ! empty( $terms ) ) {
					$status = $terms[0]->slug;
				}
				
				echo '
					<div class="hidden" id="sliced_inline_' . $id . '">
						<div class="number">' . sliced_get_number() . '</div>
						<div class="created">' . $created . '</div>
						<div class="created_d">' . Sliced_Shared::get_local_date_from_timestamp( $created, 'd' ) . '</div>
						<div class="created_m">' . Sliced_Shared::get_local_date_from_timestamp( $created, 'm' ) . '</div>
						<div class="created_Y">' . Sliced_Shared::get_local_date_from_timestamp( $created, 'Y' ) . '</div>
						<div class="created_H">' . Sliced_Shared::get_local_date_from_timestamp( $created, 'H' ) . '</div>
						<div class="created_i">' . Sliced_Shared::get_local_date_from_timestamp( $created, 'i' ) . '</div>
						<div class="created_s">' . Sliced_Shared::get_local_date_from_timestamp( $created, 's' ) . '</div>
						<div class="client">' . sliced_get_client_id() . '</div>
						<div class="due">' . $due . '</div>
						<div class="due_d">' . ( $due > 0 ? Sliced_Shared::get_local_date_from_timestamp( $due, 'd' ) : '' ) . '</div>
						<div class="due_m">' . ( $due > 0 ? Sliced_Shared::get_local_date_from_timestamp( $due, 'm' ) : '0' ) . '</div>
						<div class="due_Y">' . ( $due > 0 ? Sliced_Shared::get_local_date_from_timestamp( $due, 'Y' ) : '' ) . '</div>
						<div class="due_H">' . ( $due > 0 ? Sliced_Shared::get_local_date_from_timestamp( $due, 'H' ) : '' ) . '</div>
						<div class="due_i">' . ( $due > 0 ? Sliced_Shared::get_local_date_from_timestamp( $due, 'i' ) : '' ) . '</div>
						<div class="due_s">' . ( $due > 0 ? Sliced_Shared::get_local_date_from_timestamp( $due, 's' ) : '' ) . '</div>
						<div class="valid">' . $valid . '</div>
						<div class="valid_d">' . ( $valid > 0 ? Sliced_Shared::get_local_date_from_timestamp( $valid, 'd' ) : '' ) . '</div>
						<div class="valid_m">' . ( $valid > 0 ? Sliced_Shared::get_local_date_from_timestamp( $valid, 'm' ) : '0' ) . '</div>
						<div class="valid_Y">' . ( $valid > 0 ? Sliced_Shared::get_local_date_from_timestamp( $valid, 'Y' ) : '' ) . '</div>
						<div class="valid_H">' . ( $valid > 0 ? Sliced_Shared::get_local_date_from_timestamp( $valid, 'H' ) : '' ) . '</div>
						<div class="valid_i">' . ( $valid > 0 ? Sliced_Shared::get_local_date_from_timestamp( $valid, 'i' ) : '' ) . '</div>
						<div class="valid_s">' . ( $valid > 0 ? Sliced_Shared::get_local_date_from_timestamp( $valid, 's' ) : '' ) . '</div>
						<div class="order_number">' . sliced_get_invoice_order_number() . '</div>
						<div class="terms">' . sliced_get_terms_conditions() . '</div>
						<div class="status">' . $status . '</div>
					</div>
				';

				break;


			case 'sliced_actions' :

				$button = '<a target="_blank" title="' . __( 'View or download as a PDF (extension required)', 'sliced-invoices' ) . '" class="button ui-tip sliced-pdf-button" href="https://slicedinvoices.com/extensions/pdf-email/?utm_source=pdf_button&utm_campaign=free&utm_medium=sliced_invoices"><span class="dashicons dashicons-media-default"></span></a>';

				// $button .= '<br /><a target="_blank" href="https://slicedinvoices.com/extensions/pdf-email?utm_campaign=Free&utm_medium=link&utm_source=plugin&utm_content=extension-required"><span class="ui-tip description" title="">' . __( 'Extension Required ', 'sliced-invoices' ) . '</span></a>';

				$button = apply_filters( 'sliced_actions_column', $button );
				echo $button;

			default :
				break;

		 }

	}
	
	
	/**
	 * Translate statuses.
	 *
	 * @version 3.9.0
	 * @since   3.3.2
	 */
	public function pre_get_the_terms( $terms, $post_id, $taxonomy ) {
		
		if ( $taxonomy === 'invoice_status' || $taxonomy === 'quote_status' ) {
			
			if (
				class_exists( 'Sliced_Translate' )
				&& defined( 'SI_TRANSLATE_VERSION' )
				&& version_compare( SI_TRANSLATE_VERSION, '2.0.0', '<' )
			) {
				// for backwards compatibility
				$translate = get_option( 'sliced_translate' );
				
				foreach ( $terms as &$term ) {
					$term->name = ( isset( $translate[$term->slug] ) && class_exists( 'Sliced_Translate' ) ) ? $translate[$term->slug] : __( ucfirst( $term->name ), 'sliced-invoices' );
				}
				
			} else {
				
				// preferred way going forward
				foreach ( $terms as &$term ) {
					$term->name = __( ucfirst( $term->name ), 'sliced-invoices' );
				}
				
			}
			
		}
		
		return $terms;
	}
	

	/**
	 * Allow sortable columns.
	 *
	 * @since   2.0.0
	 */
	public function manage_edit_sortable_columns( $columns ) {

		$type = sliced_get_the_type();

		if ( $type ) {
			$columns['sliced_number']  	= 'sliced_number';
			$columns['sliced_created']  = 'sliced_created';
			$columns['sliced_total']    = 'sliced_total';
			$columns['taxonomy-' . $type . '_status'] = 'taxonomy-' . $type . '_status';
		}

		return $columns;

	}


	/**
	 * Initial ordering of columns and filtering
	 *
	 * @since   2.0.0
	 */
	public function initial_orderby_filtering( $query ) {

		// double check to avoid interfering with any ajax requests
		if ( ! sliced_get_the_type() ) {
			return;
		}
		
		if ( ! isset( $_GET['order'] ) ) {
			$query->set('order','DESC');
		}

		if ( ! isset( $_GET['orderby'] ) ) {
			$query->set('orderby','ID');
		}
		
		// filtering
		if ( isset( $_GET['sliced_client'] ) && $_GET['sliced_client'] ) {
			$query->query_vars['meta_query'][] = array(
				 'key'      => '_sliced_client',
				 'value'    => intval( sanitize_text_field( $_GET['sliced_client'] ) ),
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

		$translate = get_option( 'sliced_translate' );
		
		// Get the clients filter dropdown
		$clients = Sliced_Admin::get_clients();

		$type = sliced_get_the_type();

		if ( ! empty( $clients ) ) {

			echo '<select name="sliced_client" class="postform">';

			foreach ( $clients as $id => $name ) {
				if( $name ) {
					$selected = isset( $_GET['sliced_client'] ) ? sanitize_text_field( $_GET['sliced_client'] ) : null;
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
				printf(
					'<option value="%s"%s>%s (%s)</option>',
					esc_attr( $term->slug ),
					selected( $term->slug, $tag, false ),
					( ( isset( $translate[$term->slug] ) && class_exists( 'Sliced_Translate' ) ) ? $translate[$term->slug] : __( ucfirst( $term->name ), 'sliced-invoices' ) ),
					esc_html( $term->count )
				);
			}

			echo '</select>';
		}


		// Export to CSV button
		$url = add_query_arg( array( 'sliced_export' => 'csv' ) );
		echo '<a href="' . esc_url( $url ) . '" class="button alignright button-primary sliced-export-csv" >' . __( 'Export as CSV', 'sliced-invoices' ) . '</a>';


	}


	/**
	 * Add links for the statuses.
	 *
	 * @since   2.0.0
	 */
	public function edit_posts_views( $views ) {

		$translate = get_option( 'sliced_translate' );
		
		$type = sliced_get_the_type();

		foreach ( $views as $index => $view ) {
			$views[ $index ] = $views[ $index ];
		}

		$statuses = get_terms( $type . '_status' , array( 'hide_empty' => 0 ) );
		if ( !empty( $statuses ) && !is_wp_error( $statuses ) ) {
			foreach ( $statuses as $status ) {

				$status_name = esc_html( $status->slug );
				if( $status->slug == 'unpaid' )	{
					$status->slug = 'unpaid%2Coverdue';
				}
				$views[$status->slug] = "<a href='"
					. esc_url( add_query_arg( array( $type . '_status' => $status->slug ) ) ) . "'>"
					. ( ( isset( $translate[$status_name] ) && class_exists( 'Sliced_Translate' ) ) ? $translate[$status_name] : __( ucfirst( $status_name ), 'sliced-invoices' ) )
					. " <span class='count'>(" . esc_html( $status->count ) . ")</span></a>";
				
			}
		}
		
		return apply_filters( 'sliced_admin_col_views', $views );

	}


	/**
	 * Allow search to include various custom fields
	 *
	 * @since   3.5.3
	 */
	public function extend_admin_search( $query ) {

		// Extend search for document post type
		$post_types = array(
			'sliced_invoice',
			'sliced_quote',
		);

		if( ! is_admin() ) {
			return;
		}
		
		if ( ! in_array( $query->query['post_type'], $post_types ) ) {
			return;
		}

		$search_term = $query->query_vars['s'];
		
		if ( $search_term > '' ) {

			// 1) search clients
			// if any clients match the search term, add their IDs to $user_ids for our meta query
			// we search 2 different ways, then combine the results
			$users1 = get_users( array(
				'search' => $search_term,
			) );

			$users2 = get_users( array(
				'meta_query' => array(
					array( 'relation' => 'OR' ),
					array(
						'key' => '_sliced_client_business',
						'value' => $search_term,
						'compare' => 'LIKE',
					),
				),
			) );

			$user_ids = array();
			foreach ( $users1 as $user ) {
				$user_ids[] = $user->ID;
			}
			foreach ( $users2 as $user ) {
				$user_ids[] = $user->ID;
			}
			$user_ids = array_unique( $user_ids );


			// 2) build our meta query, including clients and/or other fields
			$meta_query = array(
				'relation' => 'OR',
				array(
					'key' => '_sliced_number',
					'value' => $search_term,
					'compare' => 'LIKE'
				),
				array(
					'key' => '_sliced_order_number',
					'value' => $search_term,
					'compare' => 'LIKE'
				),
			);
			if ( count( $user_ids ) > 0 ) {
				$meta_query[] = array(
					'key' => '_sliced_client',
					'value' => $user_ids,
					'compare' => 'IN'
				);
			}
			
			
			// 3) Now do the search
			
			// Set the query var to empty here, otherwise it won't find anything...
			$query->query_vars['s'] = '';
	
			// ...but kick search query down the road so it still displays correctly on the results page
			add_filter( 'get_search_query', array( $this, 'extend_admin_search_return' ) );
			
			// prevent recursive calls from pre_get_posts
			remove_action( 'pre_get_posts', array( $this, 'extend_admin_search' ) );
			
			// now run our queries
			$posts1 = get_posts( array(
				'post_type' => $query->query['post_type'],
				'posts_per_page' => -1,
				's' => $search_term,
			));
			$posts2 = get_posts( array(
				'post_type' => $query->query['post_type'],
				'posts_per_page' => -1,
				'meta_query' => $meta_query,
			));
			
			$post_ids = array();
			foreach ( $posts1 as $post ) {
				$post_ids[] = $post->ID;
			}
			foreach ( $posts2 as $post ) {
				$post_ids[] = $post->ID;
			}
			$post_ids = array_unique( $post_ids );
			
			if ( count( $post_ids ) === 0 ) {
				// no results
				$post_ids = array( 0 );
			}
			
			$query->set( 'post__in', $post_ids );
			$query->set( 'ignore_sticky_posts', true );
		};
	}
	
	public function extend_admin_search_return( $query ) {
		if ( isset( $_GET['s'] ) ) {
			$query = esc_attr( $_GET['s'] );
		}
		return $query;
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
		
		return $value;

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

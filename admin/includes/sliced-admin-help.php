<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }


/**
 * Calls the class.
 */
function sliced_call_help_class() {

	// just a double check
	if( ! is_admin() )
		return;

	if ( sliced_get_the_type() ) {

		new Sliced_Help();

	}
}
add_action('init', 'sliced_call_help_class');


/**
 * The Class.
 */
class Sliced_Help {


	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {

		add_action( 'load-edit.php', array( $this, 'list_view_help' ) );
		add_action( 'load-post.php', array( $this, 'add_edit_help' ) );
		add_action( 'load-post-new.php', array( $this, 'add_edit_help' ) );

	}

	/**
	 * Add contextual help to add/edit screens.
	 *
	 * @since   2.0.0
	 */
	public function add_edit_help() {

		$screen = get_current_screen();

		if ( $screen->post_type == 'sliced_invoice' || $screen->post_type == 'sliced_quote' ) {

			$screen->add_help_tab( array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'sliced-invoices' ),
				'content' => '<p>' . sprintf( __( 'This screen allows you to add/edit a single %s. You can customize the display of this screen to suit your workflow by using the Screen Options tab.', 'sliced-invoices' ), sliced_get_label() ) . '</p>' .
					'<p>' . __( 'Some Quick Tips:', 'sliced-invoices' ) . '</p>' .
					'<ul>' .
						'<li>' . __( 'Title, Client, Status and Created Date are required fields.', 'sliced-invoices' ) . '</li>' .
						'<li>' . __( 'If Payment Methods is empty, you need to add a payment method in Sliced Invoices --> Payment.', 'sliced-invoices' ) . '</li>' .
						'<li>' . __( 'If there are no Pre-Defined Line Items, you you can add these in Sliced Invoices --> General.', 'sliced-invoices' ) . '</li>' .
						'<li>' . sprintf( __( 'All fields that are filled in will be shown to the client on the %s, except for the Title field. The title is only used in the admin area.', 'sliced-invoices' ), sliced_get_label() ) . '</li>' .
					'</ul>' .
					'<p>' . __( 'Click on the help tabs to your left to find out more info.', 'sliced-invoices' ) . '</p>',
			) );
			$screen->add_help_tab( array(
				'id'      => 'title-description',
				'title'   => __( 'Title and Description', 'sliced-invoices' ),
				'content' =>
				'<p>' . sprintf( __( '<strong>Title</strong> &mdash; Enter a title for your %1s. After you enter a title, you&#8217;ll see the permalink below, which you can edit. The title is not visible to your client on the %2s, it is only used in your admin area.', 'sliced-invoices' ), sliced_get_label(), sliced_get_label() ) . '</p>' .
				'<p>' . sprintf( __( '<strong>Description</strong> &mdash; An optional field to add a description to the %s.', 'sliced-invoices' ), sliced_get_label() ) . '</p>',
			) );
			$screen->add_help_tab( array(
				'id'      => 'line-items',
				'title'   => __( 'Line Items', 'sliced-invoices' ),
				'content' =>
				'<p>' . __( 'Each line item is grouped and labelled per Item #. Each line item group contains 5 fields, with Qty and Rate being the only required fields.', 'sliced-invoices' ) . '</p>' .
				'<p>' . __( '<strong>Qty</strong> &mdash; Add the quantity of your line item in this field.', 'sliced-invoices' ) . '</p>' .
				'<p>' . __( '<strong>Title</strong> &mdash; Add a title for this line item.', 'sliced-invoices' ) . '</p>' .
				'<p>' . __( '<strong>Adjust</strong> &mdash; This can be used as an extra Tax field by adding a positive number, or as a discount field by adding a negative number. The line item amount will be adjusted by the number (in percentage %) that is input into this field.', 'sliced-invoices' ) . '</p>' .
				'<p>' . __( '<strong>Rate</strong> &mdash; Add the rate or price of your line item in this field.', 'sliced-invoices' ) . '</p>' .
				'<p>' . __( '<strong>Description</strong> &mdash; You can add a description to each line item in this field.', 'sliced-invoices' ) . '</p>',
			) );
			$screen->add_help_tab( array(
				'id'      => 'side-details',
				'title'   => sprintf( __( '%s Details', 'sliced-invoices' ), sliced_get_label() ),
				'content' =>
				'<p>' . __( '<strong>Client</strong> &mdash; Choose the client or click on the Add New Client button to add a new client. Once added, the client will immediately appear in the dropdown for you to choose.', 'sliced-invoices' ) . '</p>' .
				'<p>' . __( '<strong>Status</strong> &mdash; Add a status.', 'sliced-invoices' ) . '</p>' .
				'<p>' . sprintf( __( '<strong>%1s Number</strong> &mdash; The prefix that is set in Sliced Invoices --> %2s is automatically added to the front of the number. If you have auto increment setup, then the next number should appear in this field.', 'sliced-invoices') , sliced_get_label(), sliced_get_label_plural() ) . '</p>' .
				'<p>' . __( '<strong>Created Date</strong> &mdash; This field is required.', 'sliced-invoices' ) . '</p>',
			) );

			// Help sidebars are optional
			// $screen->set_help_sidebar(
			// 	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
			// 	'<p><a href="http://wordpress.org/support/" target="_blank">' . _( 'Support Forums' ) . '</a></p>'
			// );

		}
	}

	/**
	 * Add contextual help to list view screens.
	 *
	 * @since   2.0.0
	 */
	public function list_view_help() {

		$screen = get_current_screen();

		if ( $screen->post_type == 'sliced_invoice' || $screen->post_type == 'sliced_quote' ) {

			$screen->add_help_tab( array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'sliced-invoices' ),
				'content' => '<p>' . sprintf( __( 'This screen provides access to all of your %s. You can customize the display of this screen to suit your workflow.', 'sliced-invoices' ), sliced_get_label_plural() ) . '</p>' .
					'<p>' . __( 'Click on the help tabs to your left to find out what can be done here.', 'sliced-invoices' ) . '</p>',
			) );
			$screen->add_help_tab( array(
				'id'      => 'screen-content',
				'title'   => __( 'Screen Content', 'sliced-invoices' ),
				'content' =>
				'<p>' . __( 'You can customize the display of this screen&#8217;s contents in a number of ways:', 'sliced-invoices' ) . '</p>' .
				'<ul>' .
					'<li>' . sprintf( __( '<strong>Hide/Display Columns</strong> and decide how many %s to list per screen by using the Screen Options tab.', 'sliced-invoices' ), sliced_get_label_plural() ) . '</li>' .
					'<li>' . sprintf( __( '<strong>Filter</strong> the list of %1s by their status using the text links in the upper left. You can show All, Published, Draft, Cancelled, Overdue, Paid and Unpaid %2s. The default view is to show all %3s.', 'sliced-invoices' ), sliced_get_label_plural(), sliced_get_label_plural(), sliced_get_label_plural() ) . '</li>' .
					'<li>' . sprintf( __( '<strong>Refine</strong> the list to show only %1s from specific dates, specific clients or specific statuses by using the dropdown menus above the %2s list. Click the Filter button after making your selection.', 'sliced-invoices' ), sliced_get_label_plural(), sliced_get_label_plural() ) . '</li>' .
					'<li>' . sprintf( __( '<strong>Export</strong> a list of %s in CSV format by clicking the Export to CSV button.', 'sliced-invoices' ), sliced_get_label_plural() ) . '</li>' .
				'</ul>'
			) );
			$screen->add_help_tab( array(
				'id'      => 'action-links',
				'title'   => __( 'Available Actions', 'sliced-invoices' ),
				'content' =>
					'<p>' . sprintf( __('Hovering over a row in the %1s list will display action links that allow you to manage your %2s. You can perform the following actions:', 'sliced-invoices' ), sliced_get_label(), sliced_get_label_plural() ) . '</p>' .
					'<ul>' .
						'<li>' . sprintf( __('<strong>Edit</strong> takes you to the editing screen for that %1s. You can also reach that screen by clicking on the %2s title.', 'sliced-invoices' ), sliced_get_label(), sliced_get_label_plural() ) . '</li>' .
						'<li>' . sprintf( __('<strong>Quick Edit</strong> provides inline access to the metadata of your %1s, allowing you to update the %2s details without leaving this screen.', 'sliced-invoices' ), sliced_get_label(), sliced_get_label() ) . '</li>' .
						'<li>' . sprintf( __('<strong>Trash</strong> removes your %s from this list and places it in the trash, from which you can permanently delete it.', 'sliced-invoices' ), sliced_get_label() ) . '</li>' .
						'<li>' . sprintf( __('<strong>View</strong> will take you to your live site to view the %s.', 'sliced-invoices' ), sliced_get_label() ) . '</li>' .
						'<li>' . sprintf( __('<strong>Clone</strong> will clone (or duplicate) the %s. ', 'sliced-invoices' ), sliced_get_label() ) . '</li>' .
					'</ul>'
			) );


			// Help sidebars are optional
			// $screen->set_help_sidebar(
			// 	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
			// 	'<p><a href="http://wordpress.org/support/" target="_blank">' . _( 'Support Forums' ) . '</a></p>'
			// );

		}
	}


}

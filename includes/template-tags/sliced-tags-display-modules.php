<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

if ( ! function_exists( 'sliced_display_business' ) ) :

	function sliced_display_business() { ?>

			<a target="_blank" href="<?php echo esc_url( sliced_get_business_website() ); ?>">
				<?php echo sliced_get_business_logo() ? '<img class="logo sliced-business-logo" src="' . esc_url( sliced_get_business_logo() ) . '">' : '<h1>' . esc_html( sliced_get_business_name() ) . '</h1>' ?>
			</a>

		<?php
	}

endif;


if ( ! function_exists( 'sliced_display_from_address' ) ) :

	function sliced_display_from_address() { ?>

			<div class="from"><strong><?php _e( 'From:', 'sliced-invoices' ) ?></strong></div>
			<div class="wrapper">
			<div class="name"><a target="_blank" href="<?php echo esc_url( sliced_get_business_website() ); ?>"><?php echo esc_html( sliced_get_business_name() ); ?></a></div>

			<?php echo sliced_get_business_address() ? '<div class="address">' . wpautop( wp_kses_post( sliced_get_business_address() ) ) . '</div>' : ''; ?>
			<?php echo sliced_get_business_extra_info() ? '<div class="extra_info">' . wpautop( wp_kses_post( sliced_get_business_extra_info() ) ) . '</div>' : ''; ?>
			</div>

		<?php
	}

endif;


if ( ! function_exists( 'sliced_display_to_address' ) ) :

	function sliced_display_to_address() {

		$output = '<div class="to"><strong>' . __( 'To:', 'sliced-invoices' ) . '</strong></div>';
		$output .= '<div class="wrapper">';
		$output .= '<div class="name">' . esc_html( sliced_get_client_business() ) . '</div>';
		$output .= sliced_get_client_address() ? '<div class="address">' . wpautop( wp_kses_post( sliced_get_client_address() ) ) . '</div>' : '';
		$output .= sliced_get_client_extra_info() ? '<div class="extra_info">' . wpautop( wp_kses_post( sliced_get_client_extra_info() ) ) . '</div>' : '';
		$output .= sliced_get_client_website() ? '<div class="website">' . esc_html( sliced_get_client_website() ) . '</div>' : '';
		$output .= sliced_get_client_email() ? '<div class="email">' . esc_html( sliced_get_client_email() ) . '</div>' : '';
		$output .= '</div>';
		$output = apply_filters( 'sliced_to_address_output', $output );

		echo $output;
	}

endif;


if ( ! function_exists( 'sliced_display_invoice_details' ) ) :

	function sliced_display_invoice_details() {

		$translate = get_option( 'sliced_translate' );

		?>

			<table class="table table-bordered table-sm">

				<?php if( sliced_get_invoice_number() ) : ?>
					<tr>
						<td><?php printf( esc_html_x( '%s Number', 'invoice number', 'sliced-invoices' ), sliced_get_invoice_label() ); ?></td>
						<td><?php echo esc_html( sliced_get_invoice_prefix() ); ?><?php echo esc_html( sliced_get_invoice_number() ); ?><?php echo esc_html( sliced_get_invoice_suffix() ); ?></td>
					</tr>
				<?php endif; ?>

				<?php if( sliced_get_invoice_order_number() ) : ?>
					<tr>
						<td><?php _e( 'Order Number', 'sliced-invoices' ) ?></td>
						<td><?php echo esc_html( sliced_get_invoice_order_number() ); ?></td>
					</tr>
				<?php endif; ?>

				<?php if( sliced_get_invoice_created() ) : ?>
					<tr>
						<td><?php printf( esc_html_x( '%s Date', 'invoice date', 'sliced-invoices' ), sliced_get_invoice_label() ); ?></td>
						<td><?php echo Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_invoice_created() ); ?></td>
					</tr>
				<?php endif; ?>

				<?php if( sliced_get_invoice_due() ) : ?>
					<tr>
						<td><?php _e( 'Due Date', 'sliced-invoices' ) ?></td>
						<td><?php echo Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_invoice_due() ); ?></td>
					</tr>
				<?php endif; ?>

					<tr class="table-active">
						<td><strong><?php echo ( isset( $translate['total_due'] ) ? $translate['total_due'] : __( 'Total Due', 'sliced-invoices') ); ?></strong></td>
						<td><strong><?php echo sliced_get_invoice_total_due(); ?></strong></td>
					</tr>

			</table>

		<?php
	}

endif;

if ( ! function_exists( 'sliced_display_quote_details' ) ) :

	function sliced_display_quote_details() {

		$translate = get_option( 'sliced_translate' );

		?>

			<table class="table table-bordered table-sm">

				<?php if( sliced_get_quote_number() ) : ?>
					<tr>
						<td><?php printf( esc_html_x( '%s Number', 'quote number', 'sliced-invoices' ), sliced_get_quote_label() ); ?></td>
						<td><?php esc_html_e( sliced_get_quote_prefix() ); ?><?php esc_html_e( sliced_get_quote_number() ); ?><?php esc_html_e( sliced_get_quote_suffix() ); ?></td>
					</tr>
				<?php endif; ?>

				<?php do_action( 'sliced_after_quote_number' ); ?>

				<?php if( sliced_get_quote_created() ) : ?>
					<tr>
						<td><?php printf( esc_html_x( '%s Date', 'quote date', 'sliced-invoices' ), sliced_get_quote_label() ); ?></td>
						<td><?php echo Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_quote_created() ); ?></td>
					</tr>
				<?php endif; ?>

				<?php if( sliced_get_quote_valid() ) : ?>
					<tr>
						<td><?php _e( 'Valid Until', 'sliced-invoices' ) ?></td>
						<td><?php echo Sliced_Shared::get_local_date_i18n_from_timestamp( sliced_get_quote_valid() ); ?></td>
					</tr>
				<?php endif; ?>

					<tr class="table-active">
						<td><strong><?php echo ( isset( $translate['total'] ) ? $translate['total'] : __( 'Total', 'sliced-invoices') ); ?></strong></td>
						<td><strong><?php echo sliced_get_quote_total(); ?></strong></td>
					</tr>

			</table>

		<?php
	}

endif;



if ( ! function_exists( 'sliced_display_line_items' ) ) :

	function sliced_display_line_items() {
	
		$shared = new Sliced_Shared;
		$translate = get_option( 'sliced_translate' );

		$output = '<table class="table table-sm table-bordered table-striped">
			<thead>
				<tr>
					<th class="qty"><strong>' . ( isset( $translate['hrs_qty'] ) ? $translate['hrs_qty'] : __( 'Hrs/Qty', 'sliced-invoices') ) . '</strong></th>
					<th class="service"><strong>' . ( isset( $translate['service'] ) ? $translate['service'] : __( 'Service', 'sliced-invoices') ) . '</strong></th>
					<th class="rate"><strong>' . ( isset( $translate['rate_price'] ) ? $translate['rate_price'] : __( 'Rate/Price', 'sliced-invoices') ) . '</strong></th>';
					if ( sliced_hide_adjust_field() === false ) {
						$output .= '<th class="adjust"><strong>' . ( isset( $translate['adjust'] ) ? $translate['adjust'] : __( 'Adjust', 'sliced-invoices') ) . '</strong></th>';
					}
					$output .= '<th class="total"><strong>' . ( isset( $translate['sub_total'] ) ? $translate['sub_total'] : __( 'Sub Total', 'sliced-invoices') ) . '</strong></th>
				</tr>
			</thead>
			<tbody>';

			$count = 0;
			$items = sliced_get_invoice_line_items(); // gets quote and invoice
			if( !empty( $items ) && !empty( $items[0] ) ) :

				foreach ( $items[0] as $item ) {
					
					$class = ( $count % 2 == 0 ) ? 'even' : 'odd';
					
					$qty = isset( $item['qty'] ) ? $item['qty'] : 0;
					$amt = isset( $item['amount'] ) ? $shared->get_raw_number( $item['amount'] ) : 0;
					$tax = isset( $item['tax'] ) ? $shared->get_raw_number( $item['tax'] ) : '0.00';
					$line_total = $shared->get_line_item_sub_total( $shared->get_raw_number( $qty ), $amt, $tax );
					
					$output .= '<tr class="row_' . $class . ' sliced-item">
						<td class="qty">' . $qty . '</td>
						<td class="service">' . ( isset( $item['title'] ) ? esc_html__( $item['title'] ) : '' );
					if ( isset( $item['description'] ) ) {
						$output .= '<br /><span class="description">' . wpautop( wp_kses_post( $item['description'] ) ) . '</span>';
					}
					$output .= '</td>
						<td class="rate">' . $shared->get_formatted_currency( $amt ) . '</td>';
					if ( sliced_hide_adjust_field() === false) {
						$output .= '<td class="adjust">' . sprintf( __( '%s%%' ), $tax ) . '</td>';
					}
					$output .= '<td class="total">' . $shared->get_formatted_currency( $line_total ) . '</td>
						</tr>';
					
					$count++;
				}
			
			endif;

			$output .= '</tbody></table>';

			$output = apply_filters( 'sliced_invoice_line_items_output', $output );

		echo $output;

	}

endif;



if ( ! function_exists( 'sliced_display_invoice_totals' ) ) :

	function sliced_display_invoice_totals() {
	
		$translate = get_option( 'sliced_translate' );

		ob_start();
		
		do_action( 'sliced_invoice_before_totals_table' ); 
		
		// need to fix this up
		if( function_exists('sliced_woocommerce_get_order_id') ) {
			$order_id = sliced_woocommerce_get_order_id( get_the_ID() );
			if ( $order_id ) {
				$output = ob_get_clean();
				echo $output;
				return;
			}
		}
		?>

		<table class="table table-sm table-bordered">
			<tbody>
				<?php do_action( 'sliced_invoice_before_totals' ); ?>
				<tr class="row-sub-total">
					<td class="rate"><?php echo ( isset( $translate['sub_total'] ) ? $translate['sub_total'] : __( 'Sub Total', 'sliced-invoices') ); ?></td>
					<td class="total"><?php _e( sliced_get_invoice_sub_total() ) ?></td>
				</tr>
				<?php do_action( 'sliced_invoice_after_sub_total' ); ?>
				<tr class="row-tax">
					<td class="rate"><?php _e( sliced_get_tax_name() ) ?></td>
					<td class="total"><?php _e( sliced_get_invoice_tax() ) ?></td>
				</tr>
				<?php do_action( 'sliced_invoice_after_tax' ); ?>
				<?php 
				$totals = Sliced_Shared::get_totals( get_the_id() );
				/*
				if ( $totals['payments'] || $totals['discount'] ) {
					$total = Sliced_Shared::get_formatted_currency( $totals['total'] );
					?>
					<tr class="row-total">
						<td class="rate"><strong><?php _e( 'Total', 'sliced-invoices' ) ?></strong></td>
						<td class="total"><?php echo esc_html( $total ) ?></td>
					</tr>
					<?php
				}
				*/
				if ( $totals['discounts'] ) {
					$discount = Sliced_Shared::get_formatted_currency( $totals['discounts'] );
					?>
					<tr class="row-discount">
						<td class="rate"><?php echo ( isset( $translate['discount'] ) ? $translate['discount'] : __( 'Discount', 'sliced-invoices') ); ?></td>
						<td class="total"><span style="color:red;">-<?php echo esc_html( $discount ) ?></span></td>
					</tr>
					<?php
				}

				if ( $totals['payments'] ) {
					$paid = Sliced_Shared::get_formatted_currency( $totals['payments'] );
					?>
					<tr class="row-paid">
						<td class="rate"><?php _e( 'Paid', 'sliced-invoices' ) ?></td>
						<td class="total"><span style="color:red;">-<?php echo esc_html( $paid ) ?></span></td>
					</tr>
					<?php
				}
				
				if ( $totals['payments'] || $totals['discounts'] ) {
					$total_due = Sliced_Shared::get_formatted_currency( $totals['total_due'] );
					?>
					<tr class="table-active row-total">
						<td class="rate"><strong><?php echo ( isset( $translate['total_due'] ) ? $translate['total_due'] : __( 'Total Due', 'sliced-invoices') ); ?></strong></td>
						<td class="total"><strong><?php echo esc_html( $total_due ) ?></strong></td>
					</tr>
					<?php
				} else {
					?>
					<tr class="table-active row-total">
						<td class="rate"><strong><?php echo ( isset( $translate['total_due'] ) ? $translate['total_due'] : __( 'Total Due', 'sliced-invoices') ); ?></strong></td>
						<td class="total"><strong><?php _e( sliced_get_invoice_total_due() ) ?></strong></td>
					</tr>
				<?php
				}
				?>
				<?php do_action( 'sliced_invoice_after_totals' ); ?>
			</tbody>

		</table>

		<?php do_action( 'sliced_invoice_after_totals_table' );

		$output = ob_get_clean();
		
		echo apply_filters( 'sliced_invoice_totals_output', $output );
	}

endif;

if ( ! function_exists( 'sliced_display_quote_totals' ) ) :

	function sliced_display_quote_totals() {
	
		$translate = get_option( 'sliced_translate' );

		ob_start();
		
		do_action( 'sliced_quote_before_totals_table' ); 
		
		// need to fix this up
		if( function_exists('sliced_woocommerce_get_order_id') ) {
			$order_id = sliced_woocommerce_get_order_id( get_the_ID() );
			if ( $order_id ) {
				$output = ob_get_clean();
				echo $output;
				return;
			}
		}
		?>

		<table class="table table-sm table-bordered">

			<tbody>
				<?php do_action( 'sliced_quote_before_totals' ); ?>
				<tr class="row-sub-total">
					<td class="rate"><?php echo ( isset( $translate['sub_total'] ) ? $translate['sub_total'] : __( 'Sub Total', 'sliced-invoices') ); ?></td>
					<td class="total"><?php echo esc_html( sliced_get_quote_sub_total() ); ?></td>
				</tr>
				<?php do_action( 'sliced_invoice_after_sub_total' ); ?>
				<tr class="row-tax">
					<td class="rate"><?php echo esc_html( sliced_get_tax_name() ); ?></td>
					<td class="total"><?php echo esc_html( sliced_get_quote_tax() ); ?></td>
				</tr>
				<?php do_action( 'sliced_invoice_after_tax' ); ?>
				<?php 
				$totals = Sliced_Shared::get_totals( get_the_id() );
				if ( $totals['discounts'] ) {
					$discount = Sliced_Shared::get_formatted_currency( $totals['discounts'] );
					?>
					<tr class="row-discount">
						<td class="rate"><?php echo ( isset( $translate['discount'] ) ? $translate['discount'] : __( 'Discount', 'sliced-invoices') ); ?></td>
						<td class="total"><span style="color:red;">-<?php echo esc_html( $discount ) ?></span></td>
					</tr>
					<?php
				}
				?>
				<tr class="table-active row-total">
					<td class="rate"><strong><?php echo ( isset( $translate['total'] ) ? $translate['total'] : __( 'Total', 'sliced-invoices') ); ?></strong></td>
					<td class="total"><strong><?php echo esc_html( sliced_get_quote_total() ); ?></strong></td>
				</tr>
				<?php do_action( 'sliced_quote_after_totals' ); ?>
			</tbody>

		</table>
		
		<?php do_action( 'sliced_quote_after_totals_table' );

		$output = ob_get_clean();
		
		echo apply_filters( 'sliced_quote_totals_output', $output );
		
	}

endif;

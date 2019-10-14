<?php

// Exit if accessed directly
if ( ! defined('ABSPATH') ) { exit; }

// add the calendar days in month function
if (!function_exists('cal_days_in_month')) {
	function cal_days_in_month($calendar, $month, $year) {
		return date('t', mktime(0, 0, 0, $month, 1, $year));
	}
}
if ( ! defined('CAL_GREGORIAN'))
	define('CAL_GREGORIAN', 1);


/**
 * Calls the class.
 */
function sliced_call_reports_class() {
	new Sliced_Reports();
}
add_action( 'admin_init', 'sliced_call_reports_class' );



class Sliced_Reports {

	public $pagehook = 'sliced_reports';

	public function __construct() {

		//ensure, that the needed javascripts been loaded to allow drag/drop, expand/collapse and hide/show of boxes
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');

		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
		add_meta_box('sliced-invoice_report', sprintf( __( '%s Report', 'sliced-invoices' ), sliced_get_invoice_label() ), array(&$this, 'invoice_report'), $this->pagehook, 'normal', 'core');
		add_meta_box('sliced-quote_report', sprintf( __( '%s Report', 'sliced-invoices' ), sliced_get_quote_label() ), array(&$this, 'quote_report'), $this->pagehook, 'normal', 'core');

		add_meta_box('sliced-invoice_quote_chart', sprintf( __( '%1s and %2s', 'sliced-invoices' ), sliced_get_quote_label_plural(), sliced_get_invoice_label_plural() ), array(&$this, 'invoice_quote_chart'), $this->pagehook, 'side', 'core');
		add_meta_box('sliced-invoice_status', sprintf( __( 'Current %s Statuses', 'sliced-invoices' ), sliced_get_invoice_label() ), array(&$this, 'invoice_status'), $this->pagehook, 'normal', 'default');
		add_meta_box('sliced-quote_status', sprintf( __( 'Current %s Statuses', 'sliced-invoices' ), sliced_get_quote_label() ), array(&$this, 'quote_status'), $this->pagehook, 'normal', 'default');

	}

	/**
	 * Setup the page display.
	 *
	 * @since   2.0.0
	 */
	public function display_reports_page() {
		@ini_set( 'max_execution_time', '300' );
	?>

	<div class="wrap">
		
		<h2><?php _e( 'Sliced Invoices Reports', 'sliced-invoices' ); ?></h2>
		
		<br />
		
		<div id="dashboard-widgets-wrap">

			<?php
				$screen = get_current_screen();
				$columns = absint( $screen->get_columns() );
				$columns_css = '';
				if ( $columns ) {
					$columns_css = " columns-$columns";
				}
			?>
			<div id="dashboard-widgets" class="metabox-holder<?php echo $columns_css; ?>">
				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( $this->pagehook, 'normal', '' ); ?>
				</div>
				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( $this->pagehook, 'side', '' ); ?>
				</div>
				<div id="postbox-container-3" class="postbox-container">
					<?php do_meta_boxes( $this->pagehook, 'column3', '' ); ?>
				</div>
				<div id="postbox-container-4" class="postbox-container">
					<?php do_meta_boxes( $this->pagehook, 'column4', '' ); ?>
				</div>
			</div>

			<?php
			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
			wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
			?>

		</div>

	</div>

	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			// close postboxes that should be closed
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			// postboxes setup
			postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
		});
		//]]>
	</script>

	<?php

	}


	/**
	 * Setup dates.
	 *
	 * @since   2.0.0
	 */
	private function get_dates() {

		$general = get_option( 'sliced_general' );

		$start_day = get_option( 'start_of_week' );

		// for the overview sections
		$current_month  = date( 'm' );
		$current_year   = date( 'Y' );
		$start_month    = strtotime( date( 'Y-m-01 00:00:00' ) );
		$end_month      = strtotime( date( 'Y-m-t 23:59:59' ) );

		// working out the fiscal year
		$year_month_start   = $general['year_start'];
		$year_month_end     = $general['year_end'];

		$start_year         = strtotime( date( 'Y-'. $year_month_start .'-01 00:00:00' ) ); // 2015-07-01
		$end_year           = strtotime( date( 'Y-'. $year_month_end .'-t 23:59:59' ) ); // 2015-06-31

		if( $current_month <= $year_month_start ) {
			$start_year = strtotime( date( 'Y-'. $year_month_start .'-01 00:00:00', strtotime( '-1 year' ) ) ); // 2015-07-01
		}

		if( $current_month >= $year_month_end ) {
			$end_year   = strtotime( date( 'Y-'. $year_month_end .'-t 23:59:59', strtotime('+1 year'))); // 2015-06-31
		}

		// for the charts
		$month_array    = array( 1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec' );

		return array(
			'start_day'     => $start_day,
			'current_month' => $current_month,
			'start_month'   => $start_month,
			'end_month'     => $end_month,
			'current_year'  => $current_year,
			'start_year'    => $start_year,
			'end_year'      => $end_year,
			'month_array'   => $month_array,
		);

	}


	/**
	 * Setup the fiscal year.
	 *
	 * @since   2.0.0
	 */
	private function get_fiscal_year() {

		$general = get_option( 'sliced_general' );

		// for the overview sections
		$current_month  = date( 'm' );
		$current_year   = date( 'Y' );

		// working out the fiscal year
		$year_month_start   = $general['year_start'];
		$year_month_end     = $general['year_end'];

		$start_year         = strtotime( date( 'Y-'. $year_month_start .'-01 00:00:00' ) ); // 2015-07-01
		$end_year           = strtotime( date( 'Y-'. $year_month_end .'-t 23:59:59' ) ); // 2015-06-31

		if( $current_month <= $year_month_start ) {
			$start_year = strtotime( date( 'Y-'. $year_month_start .'-01 00:00:00', strtotime( '-1 year' ) ) ); // 2015-07-01
		}

		if( $current_month >= $year_month_end ) {
			$end_year   = strtotime( date( 'Y-'. $year_month_end .'-t 23:59:59', strtotime('+1 year'))); // 2015-06-31
		}

		return array(
			'start_year'    => $start_year,
			'end_year'      => $end_year,
		);

	}


	/**
	 * Get the ids of items for timeframe.
	 *
	 * @since   2.0.0
	 */
	public function get_items_for_time_period( $type = 'invoice', $time = 'year', $period_ago = 0 ) {

		switch ( $time ) {
			case 'year':
				$fiscal   = $this->get_fiscal_year();
				$start    = $fiscal['start_year'];
				$end      = $fiscal['end_year'];
				break;
			case 'month': // gets current month start and finish
				$start  = strtotime( date( 'Y-m-01' ) );
				$end    = strtotime( date( 'Y-m-t' ) );
				break;
			case 'week':
				$start  = strtotime( 'this week' );
				$end    = strtotime( '+6 days', $start );
				break;
			default:
				break;
		}

		if( $period_ago != 0 ) {
			$start  = strtotime( $period_ago . $time, $start );
			$end    = strtotime( $period_ago . $time, $end );
			if ( $time == 'month' ) {
				$month  = date( 'm' ) + (int)$period_ago; // Numeric representation of a month, with leading zeros
				$days   = cal_days_in_month(CAL_GREGORIAN, date( 'm', $start) , date( 'Y', $start));
				$end    = strtotime( date( date( 'Y', $start) . '-' . date( 'm', $start) . '-' . $days . '' ) );

			}
		}

		// adding the times to start and end to ensure we get the full days
		$start = strtotime( date('Y-m-d 00:00:00', $start ) );
		$end   = strtotime( date('Y-m-d 23:59:59', $end ) );

		$args = array(
			'post_type' => 'sliced_' . $type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			// 'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => '_sliced_' . $type . '_created',
					'value'   => $start,
					'compare' => '>=',
				),
				array(
					'key'     => '_sliced_' . $type . '_created',
					'value'   => $end,
					'compare' => '<=',
				),
			),
		);
		
		if ( $type === 'invoice' ) {
			$args['tax_query'] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'invoice_status',
					'field'    => 'slug',
					'terms'    => array( 'draft', 'cancelled' ),
					'operator' => 'NOT IN',
				),
			);
		}
		
		if ( $type === 'quote' ) {
			$args['tax_query'] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'quote_status',
					'field'    => 'slug',
					'terms'    => array( 'draft', 'cancelled', 'declined' ),
					'operator' => 'NOT IN',
				),
			);
		}

		$the_query = new WP_Query( apply_filters( 'sliced_reports_query', $args ) );
		$total = array();
		if( $the_query->posts ) :
			foreach ( $the_query->posts as $post ) {
				if ( $type === 'invoice' ) {
					$total[$post->ID] = sliced_get_invoice_total_raw( $post->ID );
				}
				if ( $type === 'quote' ) {
					$total[$post->ID] = sliced_get_quote_total_raw( $post->ID );
				}
			};
		endif;

		return $total;

	}


	/**
	 * Display the invoice items.
	 *
	 * @since   2.0.0
	 */
	public function invoice_report() {

		$type = 'invoice';
		$this_year  = $this->do_the_math( $this->get_items_for_time_period( $type, 'year', 0 ) );
		$last_year  = $this->do_the_math( $this->get_items_for_time_period( $type, 'year', -1 ) );
		$this_month = $this->do_the_math( $this->get_items_for_time_period( $type, 'month', 0 ) );
		$last_month = $this->do_the_math( $this->get_items_for_time_period( $type, 'month', -1 ) );
		$this_week  = $this->do_the_math( $this->get_items_for_time_period( $type, 'week', 0 ) );
		$last_week  = $this->do_the_math( $this->get_items_for_time_period( $type, 'week', -1 ) );

	?>
		<div class="sliced-dashboard">
		<span class="description"><?php _e( '(Does not include Cancelled or Draft statuses)', 'sliced-invoices' ) ?></span>
			<ul class="invoices">
				<li class="label">
					<?php sprintf( __( 'Total %s', 'sliced-invoices' ), sliced_get_invoice_label_plural() ) ?>
				</li>
				<li class="number"><span><?php _e( 'Year to Date:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $this_year['total'] ) ?></li>
				<li class="number"><span><?php _e( 'This Month:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $this_month['total'] ) ?></li>
				<li class="number"><span><?php _e( 'This Week:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $this_week['total'] ) ?></li>

			</ul>
			<ul class="invoices">
				<li class="number"><span><?php _e( 'Last Year:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $last_year['total'] ) ?></li>
				<li class="number"><span><?php _e( 'Last Month:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $last_month['total'] ) ?></li>
				<li class="number"><span><?php _e( 'Last Week:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $last_week['total'] ) ?></li>

			</ul>
		</div>


	<?php

	}


	/**
	 * Display the quote items.
	 *
	 * @since   2.0.0
	 */
	public function quote_report() {

		// total unpaid
		// total overdue
		$type = 'quote';
		$this_year  = $this->do_the_math( $this->get_items_for_time_period( $type, 'year', 0 ) );
		$last_year  = $this->do_the_math( $this->get_items_for_time_period( $type, 'year', -1 ) );
		$this_month = $this->do_the_math( $this->get_items_for_time_period( $type, 'month', 0 ) );
		$last_month = $this->do_the_math( $this->get_items_for_time_period( $type, 'month', -1 ) );
		$this_week  = $this->do_the_math( $this->get_items_for_time_period( $type, 'week', 0 ) );
		$last_week  = $this->do_the_math( $this->get_items_for_time_period( $type, 'week', -1 ) );


	?>
		<div class="sliced-dashboard">
			<span class="description"><?php printf( __( '(Shows current Sent %s)', 'sliced-invoices' ), sliced_get_quote_label_plural() ) ?></span>
			<ul class="quotes">
			</span>
				<li class="label">
					<?php sprintf( __( 'Outstanding %s', 'sliced-invoices' ), sliced_get_quote_label_plural() ) ?>
				</li>
				<li class="number"><span><?php _e( 'Year to Date:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $this_year['total'] ) ?></li>
				<li class="number"><span><?php _e( 'This Month:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $this_month['total'] ) ?></li>
				<li class="number"><span><?php _e( 'This Week:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $this_week['total'] ) ?></li>
			</ul>

			<ul class="quotes">
				<li class="number"><span><?php _e( 'Last Year:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $last_year['total'] ) ?></li>
				<li class="number"><span><?php _e( 'Last Month:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $last_month['total'] ) ?></li>
				<li class="number"><span><?php _e( 'Last Week:', 'sliced-invoices' ) ?></span> <?php echo esc_html( $last_week['total'] ) ?></li>
			</ul>

		</div>

	<?php

	}


	/**
	 * Setup the colors for the reports.
	 * Use rgba so we can have hover opacity effects.
	 *
	 * @since   2.0.0
	 */
	private function get_reporting_colors() {

		return apply_filters( 'sliced_reporting_colors', array(
			'draft'     => 'rgba(37, 177, 209, 1)',
			'accepted'  => 'rgba(96, 173, 93, 1)',
			'paid'      => 'rgba(96, 173, 93, 1)',
			'sent'      => 'rgba(155, 204, 153, 1)',
			'expired'   => 'rgba(237, 144, 78, 1)',
			'unpaid'    => 'rgba(237, 144, 78, 1)',
			'overdue'   => 'rgba(216, 92, 39, 1)',
			'declined'  => 'rgba(216, 92, 39, 1)',
			'cancelled' => 'rgba(163, 151, 127, 1)',
		) );

	}



	/**
	 * Invoice statuses.
	 *
	 * @since   2.0.0
	 */
	public function invoice_status() {

		$taxonomy = 'invoice_status';
		$type = 'invoice';
		$statuses = get_terms( $taxonomy );

		$chart = $this->display_doughnut_chart( $type, $statuses );

		return $chart;

	}

	/**
	 * Quote statuses.
	 *
	 * @since   2.0.0
	 */
	public function quote_status() {

		$taxonomy = 'quote_status';
		$type = 'quote';
		$statuses = get_terms( $taxonomy );

		$chart = $this->display_doughnut_chart( $type, $statuses );

		return $chart;

	}


	/**
	 * Output doughnut chart.
	 *
	 * @since   2.0.0
	 */
	public function display_doughnut_chart( $type, $statuses ) {

		$data = array();
		$background_colors = array();
		$labels = array();
		
		$colors = $this->get_reporting_colors();
		
		foreach( $statuses as $status ) {
			$data[] = esc_html( $status->count );
			$background_colors[] = '"'.$colors[$status->slug].'"';
			$labels[] = '"'.esc_attr( $status->name ).'"';
		}

		?>
		<div class="labeled-chart-container">

			<div class="canvas-holder">
				<canvas height="250" width="250" id="<?php echo $type ?>_status" style="width: 250px; height: 250px;"></canvas>
			</div>

			<ul class="doughnut-legend">
				<?php foreach( $statuses as $status ) { ?>
					<li><span style="background-color: <?php echo esc_html( $colors[$status->slug] ); ?>"></span><?php echo esc_html( $status->name ) ?></li>
				<?php } ?>
			</ul>

		</div>

		<script type="text/javascript">
		
			var data_<?php echo $type ?> = {
				type: 'pie',
				data: {
					datasets: [{
						data: [
							<?php echo implode( ',', $data ); ?>
						],
						backgroundColor: [
							<?php echo implode( ',', $background_colors ); ?>
						],
					}],
					labels: [
						<?php echo implode( ',', $labels ); ?>
					]
				},
				options: {
					responsive: true,
					legend: {
						display: false
					}
				}
			};

			jQuery(document).ready(function(){
				var ctx_<?php echo $type ?> = document.getElementById("<?php echo esc_html( $type ) ?>_status").getContext("2d");
				window.Pie_<?php echo $type ?> = new Chart( ctx_<?php echo esc_html( $type ) ?>, data_<?php echo esc_html( $type ) ?> );
			});

		</script>

		<?php
	}


	/**
	 * Output invoice/quote bar chart.
	 *
	 * @since   2.0.0
	 */
	public function invoice_quote_chart() {

		$shared = new Sliced_Shared();

		$month_array = array( 1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December' );

		// sure there's an easier way.
		$count = date( 'm' );
		foreach( $month_array as $index => $month ) {
			$index = $index-1;//minus 1 so that we start on 0 for current month
			$items = $this->get_items_for_time_period( 'invoice', 'month', -$index );

			if( $items ) {
				$sum = array_sum( $items );
				$invoice_total[$month_array[(int)$count]] = $sum;
			} else {
				$invoice_total[$month_array[(int)$count]] = 0;
			}

			if( $count <= 1 ){ $count = 13; }
			$count--;
		}

		foreach( $month_array as $index => $month ) {
			$index = $index-1;//minus 1 so that we start on 0 for current month
			$items = $this->get_items_for_time_period( 'quote', 'month', -$index );

			if( $items ) {
				$sum = array_sum( $items );
				$quote_total[$month_array[(int)$count]] = $sum;
			} else {
				$quote_total[$month_array[(int)$count]] = 0;
			}

			if( $count <= 1 ){ $count = 13; }
			$count--;
		}

		$quotes = array_reverse( array_slice($quote_total, 0, 6) );
		$invoices = array_reverse( array_slice($invoice_total, 0, 6) );

		?>
		<ul class="bar-legend">
			<li><span style="background-color: rgba(23, 158, 200, 0.7)"></span><?php esc_html_e( sliced_get_invoice_label_plural() ) ?></li>
			<li><span style="background-color: rgba(42, 61, 80, 0.7)"></span><?php esc_html_e( sliced_get_quote_label_plural() ) ?></li>
		</ul>

		<div style="width: 100%">
			<canvas id="canvas" height="450" width="600"></canvas>
		</div>

		<script type="text/javascript">
		
			var barChartData = {
				labels: [ <?php
							foreach( $quotes as $month => $amount ) {
								echo '"' . $month . '",';
							} ?>],
				datasets: [
					{
						label: "Quotes",
						backgroundColor : "rgba(42, 61, 80, 0.7)",
						borderColor : "rgba(42, 61, 80, 1)",
						data : [<?php
									foreach( $quotes as $month => $amount ) {
										echo (int)$amount . ',';
									} ?>]
					},
					{
						label: "Invoices",
						backgroundColor : "rgba(23, 158, 200, 0.7)",
						borderColor : "rgba(23, 158, 200, 1)",
						data : [<?php
									foreach( $invoices as $month => $amount ) {
										echo (int)$amount . ',';
									} ?>]
					}
				]
			};
			
			jQuery(document).ready(function(){
				var ctx = document.getElementById("canvas").getContext("2d");
				window.myBar = new Chart(ctx, {
					type: 'bar',
					data: barChartData,
					options: {
						responsive: true,
						legend: {
							display: false
						},
						showScale: true,
						scaleBeginAtZero: true,
						tooltipTitleFontSize: 13,
						tooltipYPadding: 10,
						tooltipXPadding: 15,
						multiTooltipTemplate: " <%=datasetLabel%> : <?php echo $shared->get_currency_symbol(null) ?><%= value %> ",
					}
				});
			});

		</script>

		<?php
	}


	/**
	 * Do some math.
	 *
	 * @since   2.0.0
	 */
	private function do_the_math( $array = array() ) {

		$shared = new Sliced_Shared();

		$total  = !empty( $array ) ? array_sum($array) : 0;
		$items  = count( $array );
		$avg    = !empty( $array ) ? $total / $items : 0;
		$min    = !empty( $array ) ? min( $array ) : 0;
		$max    = !empty( $array ) ? max( $array ) : 0;

		return array(
			'total' => $shared->get_formatted_currency( $total ),
			'items' => $items,
			'avg' => $shared->get_formatted_currency( $avg ),
			'min' => $shared->get_formatted_currency( $min ),
			'max' => $shared->get_formatted_currency( $max ),
		);

	}

}

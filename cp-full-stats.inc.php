<?php
/**
 * Summary statistics overview (SQL-aggregated, zero full-table PHP loops).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

global $wpdb;

$table = $wpdb->prefix . $this->table_messages;

// 1. Yearly counts.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$yearly_rows = $wpdb->get_results( "SELECT YEAR(`time`) AS period_key, COUNT(*) AS total FROM `{$table}` GROUP BY YEAR(`time`) ORDER BY period_key DESC LIMIT 12" );
$yearly_data = array();
if ( is_array( $yearly_rows ) ) {
	foreach ( $yearly_rows as $r ) {
		if ( ! empty( $r->period_key ) ) {
			$yearly_data[] = array(
				'label' => (string) $r->period_key,
				'count' => (int) $r->total,
			);
		}
	}
}

// 2. Last 12 months.
$month_start_ts = strtotime( 'first day of -11 months', current_time( 'timestamp' ) );
$month_cutoff   = wp_date( 'Y-m-01 00:00:00', $month_start_ts );
$monthly_rows   = $wpdb->get_results(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT DATE_FORMAT(`time`, '%%Y%%m') AS period_key, COUNT(*) AS total FROM `{$table}` WHERE `time` >= %s GROUP BY period_key",
		$month_cutoff
	)
);
$monthly_map = array();
if ( is_array( $monthly_rows ) ) {
	foreach ( $monthly_rows as $r ) {
		$monthly_map[ (string) $r->period_key ] = (int) $r->total;
	}
}
$monthly_data = array();
$cursor_ts    = $month_start_ts;
for ( $i = 0; $i < 12; $i++ ) {
	$key            = wp_date( 'Ym', $cursor_ts );
	$monthly_data[] = array(
		'label' => wp_date( 'Y M', $cursor_ts ),
		'count' => isset( $monthly_map[ $key ] ) ? $monthly_map[ $key ] : 0,
	);
	$cursor_ts      = strtotime( '+1 month', $cursor_ts );
}

// 3. Last 12 ISO weeks.
$week_start_ts = strtotime( '-11 weeks', current_time( 'timestamp' ) );
$week_cutoff   = wp_date( 'Y-m-d 00:00:00', strtotime( '-85 days', current_time( 'timestamp' ) ) );
$weekly_rows   = $wpdb->get_results(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT DATE_FORMAT(`time`, '%%x%%v') AS period_key, COUNT(*) AS total FROM `{$table}` WHERE `time` >= %s GROUP BY period_key",
		$week_cutoff
	)
);
$weekly_map = array();
if ( is_array( $weekly_rows ) ) {
	foreach ( $weekly_rows as $r ) {
		$weekly_map[ (string) $r->period_key ] = (int) $r->total;
	}
}
$weekly_data = array();
$cursor_ts   = $week_start_ts;
for ( $i = 0; $i < 12; $i++ ) {
	$key           = wp_date( 'oW', $cursor_ts );
	$weekly_data[] = array(
		'label' => wp_date( 'o', $cursor_ts ) . ' W' . wp_date( 'W', $cursor_ts ),
		'count' => isset( $weekly_map[ $key ] ) ? $weekly_map[ $key ] : 0,
	);
	$cursor_ts     = strtotime( '+1 week', $cursor_ts );
}

// 4. Last 30 days.
$day_start_ts = strtotime( '-29 days', current_time( 'timestamp' ) );
$day_cutoff   = wp_date( 'Y-m-d 00:00:00', $day_start_ts );
$daily_rows   = $wpdb->get_results(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT DATE_FORMAT(`time`, '%%Y%%m%%d') AS period_key, COUNT(*) AS total FROM `{$table}` WHERE `time` >= %s GROUP BY period_key",
		$day_cutoff
	)
);
$daily_map = array();
if ( is_array( $daily_rows ) ) {
	foreach ( $daily_rows as $r ) {
		$daily_map[ (string) $r->period_key ] = (int) $r->total;
	}
}
$daily_data = array();
$cursor_ts  = $day_start_ts;
for ( $i = 0; $i < 30; $i++ ) {
	$key          = wp_date( 'Ymd', $cursor_ts );
	$daily_data[] = array(
		'label' => wp_date( 'M d', $cursor_ts ),
		'count' => isset( $daily_map[ $key ] ) ? $daily_map[ $key ] : 0,
	);
	$cursor_ts    = strtotime( '+1 day', $cursor_ts );
}

$period_panels = array(
	array(
		'title' => __( 'Yearly Stats', 'cp-referrer-and-conversions-tracking' ),
		'items' => $yearly_data,
	),
	array(
		'title' => __( 'Monthly (Last 12 Months)', 'cp-referrer-and-conversions-tracking' ),
		'items' => array_reverse( $monthly_data ),
	),
	array(
		'title' => __( 'Weekly (Last 12 Weeks)', 'cp-referrer-and-conversions-tracking' ),
		'items' => array_reverse( $weekly_data ),
	),
	array(
		'title' => __( 'Daily (Last 30 Days)', 'cp-referrer-and-conversions-tracking' ),
		'items' => array_reverse( $daily_data ),
	),
);
?>

<section class="cprt-card">
	<div class="cprt-card-header">
		<h2><?php esc_html_e( 'Historical Overview', 'cp-referrer-and-conversions-tracking' ); ?></h2>
		<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Aggregated across stored logs by registration date', 'cp-referrer-and-conversions-tracking' ); ?></span>
	</div>
	<div class="cprt-card-body">
		<div class="cprt-period-grid">
			<?php foreach ( $period_panels as $panel ) : ?>
				<?php
				$max_count = 0;
				foreach ( $panel['items'] as $entry ) {
					if ( $entry['count'] > $max_count ) {
						$max_count = $entry['count'];
					}
				}
				?>
				<div class="cprt-period-col">
					<h3 class="cprt-period-title"><?php echo esc_html( $panel['title'] ); ?></h3>
					<div class="cprt-period-list">
						<?php if ( empty( $panel['items'] ) ) : ?>
							<p class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'No records yet.', 'cp-referrer-and-conversions-tracking' ); ?></p>
						<?php else : ?>
							<?php foreach ( $panel['items'] as $entry ) : ?>
								<?php $pct = ( $max_count > 0 ) ? round( ( $entry['count'] / $max_count ) * 100 ) : 0; ?>
								<div class="cprt-period-row">
									<span class="cprt-period-label"><?php echo esc_html( $entry['label'] ); ?></span>
									<div class="cprt-mini-bar-track" aria-hidden="true">
										<div class="cprt-mini-bar-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
									</div>
									<strong class="cprt-period-count"><?php echo esc_html( number_format_i18n( $entry['count'] ) ); ?></strong>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

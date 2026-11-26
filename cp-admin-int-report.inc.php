<?php
/**
 * Tracking Stats & Analytics view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

global $wpdb;

$this->item   = 1;
$search_value = $this->get_get_param( 'search' );
$date_start   = $this->validate_date( $this->get_get_param( 'dfrom' ) );
$date_end     = $this->validate_date( $this->get_get_param( 'dto' ) );

$table  = $wpdb->prefix . $this->table_messages;
$filter = $this->build_logs_where( $search_value, $date_start, $date_end );

// 1. Total matching logs & min/max date bounds.
$summary_sql = "SELECT COUNT(*) AS total_logs, MIN(DATE(`time`)) AS min_date, MAX(DATE(`time`)) AS max_date FROM `{$table}` WHERE {$filter['where']}";
if ( ! empty( $filter['params'] ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$summary_sql = $wpdb->prepare( $summary_sql, $filter['params'] );
}
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$summary    = $wpdb->get_row( $summary_sql );
$total_logs = $summary ? (int) $summary->total_logs : 0;

if ( '' === $date_start && $summary && ! empty( $summary->min_date ) ) {
	$date_start = $this->validate_date( $summary->min_date );
}
if ( '' === $date_end && $summary && ! empty( $summary->max_date ) ) {
	$date_end = $this->validate_date( $summary->max_date );
}

// 2. Daily counts via SQL GROUP BY.
$daily_sql = "SELECT DATE(`time`) AS day_key, COUNT(*) AS total FROM `{$table}` WHERE {$filter['where']} GROUP BY day_key ORDER BY day_key ASC";
if ( ! empty( $filter['params'] ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$daily_sql = $wpdb->prepare( $daily_sql, $filter['params'] );
}
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$daily_rows = $wpdb->get_results( $daily_sql );
$daily_map  = array();
if ( is_array( $daily_rows ) ) {
	foreach ( $daily_rows as $r ) {
		$daily_map[ (string) $r->day_key ] = (int) $r->total;
	}
}

// Build continuous daily series safely (capped at 366 days to prevent unbounded loops).
$daily_labels = array();
$daily_values = array();
if ( '' !== $date_start && '' !== $date_end && $date_start <= $date_end ) {
	$start_ts = strtotime( $date_start );
	$end_ts   = strtotime( $date_end );
	if ( false !== $start_ts && false !== $end_ts ) {
		$max_span_ts = strtotime( '+365 days', $start_ts );
		if ( $end_ts > $max_span_ts ) {
			$start_ts = strtotime( '-365 days', $end_ts );
		}
		$cursor = $start_ts;
		$guard  = 0;
		while ( $cursor <= $end_ts && $guard < 370 ) {
			$d_key          = gmdate( 'Y-m-d', $cursor );
			$daily_labels[] = $d_key;
			$daily_values[] = isset( $daily_map[ $d_key ] ) ? $daily_map[ $d_key ] : 0;
			$cursor         = strtotime( '+1 day', $cursor );
			$guard++;
		}
	}
}

// 3. Hourly counts (0-23) via SQL GROUP BY.
$hourly_sql = "SELECT HOUR(`time`) AS hour_key, COUNT(*) AS total FROM `{$table}` WHERE {$filter['where']} GROUP BY hour_key";
if ( ! empty( $filter['params'] ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$hourly_sql = $wpdb->prepare( $hourly_sql, $filter['params'] );
}
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$hourly_rows = $wpdb->get_results( $hourly_sql );
$hourly_map  = array();
if ( is_array( $hourly_rows ) ) {
	foreach ( $hourly_rows as $r ) {
		$hourly_map[ (int) $r->hour_key ] = (int) $r->total;
	}
}
$hourly_labels = array();
$hourly_values = array();
for ( $h = 0; $h <= 23; $h++ ) {
	$hourly_labels[] = sprintf( '%02d:00', $h );
	$hourly_values[] = isset( $hourly_map[ $h ] ) ? $hourly_map[ $h ] : 0;
}

// 4. Breakdown dimension selector (allowlisted, including UTM dimensions).
$allowed_fields = array(
	'referrer'     => __( 'Referrer', 'cp-referrer-and-conversions-tracking' ),
	'utm_source'   => __( 'UTM Source', 'cp-referrer-and-conversions-tracking' ),
	'utm_medium'   => __( 'UTM Medium', 'cp-referrer-and-conversions-tracking' ),
	'utm_campaign' => __( 'UTM Campaign', 'cp-referrer-and-conversions-tracking' ),
	'date'         => __( 'Date', 'cp-referrer-and-conversions-tracking' ),
	'hour'         => __( 'Hour of Day', 'cp-referrer-and-conversions-tracking' ),
	'ip'           => __( 'Visitor ID / IP', 'cp-referrer-and-conversions-tracking' ),
);
$utm_dimensions = array( 'utm_source', 'utm_medium', 'utm_campaign' );
$raw_field      = $this->get_get_param( 'field', 'referrer' );
$field_filter   = array_key_exists( $raw_field, $allowed_fields ) ? $raw_field : 'referrer';
$is_utm_field   = in_array( $field_filter, $utm_dimensions, true );

$breakdown_rows  = array();
$breakdown_total = $total_logs;

if ( $is_utm_field ) {
	$utm_like   = '%' . $wpdb->esc_like( '"' . $field_filter . '"' ) . '%';
	$utm_params = $filter['params'];
	$utm_params[] = $utm_like;
	$utm_sql    = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT data FROM `{$table}` WHERE {$filter['where']} AND data LIKE %s",
		$utm_params
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$raw_utm_rows = $wpdb->get_col( $utm_sql );
	$utm_counts   = array();
	$utm_matched  = 0;

	if ( is_array( $raw_utm_rows ) ) {
		foreach ( $raw_utm_rows as $raw_blob ) {
			$decoded = $this->decode_log_data( $raw_blob );
			if ( isset( $decoded['utm'][ $field_filter ] ) && is_string( $decoded['utm'][ $field_filter ] ) ) {
				$val = trim( $decoded['utm'][ $field_filter ] );
				if ( '' !== $val ) {
					if ( ! isset( $utm_counts[ $val ] ) ) {
						$utm_counts[ $val ] = 0;
					}
					$utm_counts[ $val ]++;
					$utm_matched++;
				}
			}
		}
	}

	arsort( $utm_counts );
	$utm_counts      = array_slice( $utm_counts, 0, 50, true );
	$breakdown_total = $utm_matched;
	foreach ( $utm_counts as $utm_val => $utm_cnt ) {
		$breakdown_rows[] = (object) array(
			'label' => $utm_val,
			'total' => $utm_cnt,
		);
	}
} else {
	if ( 'referrer' === $field_filter ) {
		$b_sql = "SELECT referrer AS label, COUNT(*) AS total FROM `{$table}` WHERE {$filter['where']} GROUP BY referrer ORDER BY total DESC LIMIT 50";
	} elseif ( 'date' === $field_filter ) {
		$b_sql = "SELECT DATE(`time`) AS label, COUNT(*) AS total FROM `{$table}` WHERE {$filter['where']} GROUP BY label ORDER BY total DESC LIMIT 50";
	} elseif ( 'hour' === $field_filter ) {
		$b_sql = "SELECT LPAD(HOUR(`time`), 2, '0') AS label, COUNT(*) AS total FROM `{$table}` WHERE {$filter['where']} GROUP BY label ORDER BY total DESC LIMIT 24";
	} else {
		$b_sql = "SELECT id AS label, COUNT(*) AS total FROM `{$table}` WHERE {$filter['where']} GROUP BY id ORDER BY total DESC LIMIT 50";
	}

	if ( ! empty( $filter['params'] ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$b_sql = $wpdb->prepare( $b_sql, $filter['params'] );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$breakdown_rows = $wpdb->get_results( $b_sql );
}

$csv_nonce = wp_create_nonce( 'cpreftrack_export_csv' );
$csv_url   = add_query_arg(
	array(
		'page'                 => $this->menu_parameter . '_report',
		$this->prefix . '_csv' => '1',
		'_cprt_csv_nonce'      => $csv_nonce,
		'search'               => $search_value,
		'dfrom'                => $date_start,
		'dto'                  => $date_end,
	),
	admin_url( 'admin.php' )
);

$daily_chart_payload = wp_json_encode(
	array(
		'type'   => 'line',
		'color'  => '#2563eb',
		'labels' => $daily_labels,
		'values' => $daily_values,
	)
);

$hourly_chart_payload = wp_json_encode(
	array(
		'type'   => 'bar',
		'color'  => '#0ea5e9',
		'labels' => $hourly_labels,
		'values' => $hourly_values,
	)
);

$bar_palette = array( '#2563eb', '#0ea5e9', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899', '#14b8a6', '#6366f1' );

$this->render_admin_header(
	'stats',
	__( 'Tracking Stats & Analytics', 'cp-referrer-and-conversions-tracking' ),
	/* translators: %s: total logs matching filters */
	sprintf( __( 'Analyzing %s referral logs in the selected view.', 'cp-referrer-and-conversions-tracking' ), number_format_i18n( $total_logs ) )
);

require_once CPREFTRACK_PLUGIN_DIR . 'cp-full-stats.inc.php';
?>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-body">
		<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="cprt-filter-bar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_parameter ); ?>_report" />
			<input type="hidden" name="field" value="<?php echo esc_attr( $field_filter ); ?>" />

			<div class="cprt-field cprt-field-grow">
				<label for="cprt-rep-search"><?php esc_html_e( 'Search', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="search" id="cprt-rep-search" name="search" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'Filter analytics by referrer, UTM parameter, or entry...', 'cp-referrer-and-conversions-tracking' ); ?>" />
			</div>

			<div class="cprt-field">
				<label for="cprt-rep-from"><?php esc_html_e( 'From', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="date" id="cprt-rep-from" name="dfrom" value="<?php echo esc_attr( $date_start ); ?>" />
			</div>

			<div class="cprt-field">
				<label for="cprt-rep-to"><?php esc_html_e( 'To', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="date" id="cprt-rep-to" name="dto" value="<?php echo esc_attr( $date_end ); ?>" />
			</div>

			<div class="cprt-filter-actions">
				<button type="submit" class="cprt-btn cprt-btn-primary"><?php esc_html_e( 'Filter', 'cp-referrer-and-conversions-tracking' ); ?></button>
				<?php if ( '' !== $search_value || '' !== $this->get_get_param( 'dfrom' ) || '' !== $this->get_get_param( 'dto' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_report' ) ); ?>" class="cprt-btn cprt-btn-ghost"><?php esc_html_e( 'Reset', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $csv_url ); ?>" class="cprt-btn cprt-btn-secondary"><?php esc_html_e( 'Export CSV', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<button type="button" class="cprt-btn cprt-btn-secondary" data-cprt-print><?php esc_html_e( 'Print', 'cp-referrer-and-conversions-tracking' ); ?></button>
			</div>
		</form>
	</div>
</section>

<div class="cprt-grid cprt-grid-2">
	<section class="cprt-card">
		<div class="cprt-card-header">
			<h2><?php esc_html_e( 'Logs per Day', 'cp-referrer-and-conversions-tracking' ); ?></h2>
			<?php if ( '' !== $date_start && '' !== $date_end ) : ?>
				<span class="cprt-text-muted cprt-text-sm"><?php echo esc_html( $date_start . ' — ' . $date_end ); ?></span>
			<?php endif; ?>
		</div>
		<div class="cprt-card-body">
			<div class="cprt-chart-wrap">
				<canvas class="cprt-chart-canvas" height="240" data-cprt-chart="<?php echo esc_attr( $daily_chart_payload ); ?>"></canvas>
				<div class="cprt-chart-tooltip" hidden></div>
			</div>
		</div>
	</section>

	<section class="cprt-card">
		<div class="cprt-card-header">
			<h2><?php esc_html_e( 'Logs per Hour', 'cp-referrer-and-conversions-tracking' ); ?></h2>
			<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( '00:00 — 23:00 distribution', 'cp-referrer-and-conversions-tracking' ); ?></span>
		</div>
		<div class="cprt-card-body">
			<div class="cprt-chart-wrap">
				<canvas class="cprt-chart-canvas" height="240" data-cprt-chart="<?php echo esc_attr( $hourly_chart_payload ); ?>"></canvas>
				<div class="cprt-chart-tooltip" hidden></div>
			</div>
		</div>
	</section>
</div>

<section class="cprt-card">
	<div class="cprt-card-header cprt-card-header-flex">
		<div>
			<h2>
				<?php
				printf(
					/* translators: %s: selected breakdown dimension */
					esc_html__( 'Top Values by %s', 'cp-referrer-and-conversions-tracking' ),
					esc_html( $allowed_fields[ $field_filter ] )
				);
				?>
			</h2>
			<span class="cprt-text-muted cprt-text-sm">
				<?php
				if ( $is_utm_field ) {
					printf(
						/* translators: %s: number of UTM-tagged logs */
						esc_html__( 'Percentage of %s UTM-tagged logs (top 50 values)', 'cp-referrer-and-conversions-tracking' ),
						esc_html( number_format_i18n( $breakdown_total ) )
					);
				} else {
					esc_html_e( 'Percentage of matching logs (top 50 values)', 'cp-referrer-and-conversions-tracking' );
				}
				?>
			</span>
		</div>
		<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="cprt-inline-form cprt-no-print">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_parameter ); ?>_report" />
			<input type="hidden" name="search" value="<?php echo esc_attr( $search_value ); ?>" />
			<input type="hidden" name="dfrom" value="<?php echo esc_attr( $date_start ); ?>" />
			<input type="hidden" name="dto" value="<?php echo esc_attr( $date_end ); ?>" />
			<label for="cprt-breakdown-field" class="screen-reader-text"><?php esc_html_e( 'Select report dimension', 'cp-referrer-and-conversions-tracking' ); ?></label>
			<select id="cprt-breakdown-field" name="field" class="cprt-select" data-cprt-auto-submit>
				<?php foreach ( $allowed_fields as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $field_filter, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>
	<div class="cprt-card-body">
		<?php if ( empty( $breakdown_rows ) || $breakdown_total <= 0 ) : ?>
			<p class="cprt-empty-state">
				<?php
				echo $is_utm_field
					? esc_html__( 'No UTM-tagged log entries match the selected criteria yet.', 'cp-referrer-and-conversions-tracking' )
					: esc_html__( 'No log entries match the selected criteria.', 'cp-referrer-and-conversions-tracking' );
				?>
			</p>
		<?php else : ?>
			<div class="cprt-breakdown-list">
				<?php
				$idx = 0;
				foreach ( $breakdown_rows as $row ) :
					$count       = (int) $row->total;
					$pct         = round( ( $count / $breakdown_total ) * 100, 2 );
					$bar_width   = max( 2, min( 100, round( ( $count / $breakdown_total ) * 100 ) ) );
					$label_raw   = (string) $row->label;
					$label_short = ( strlen( $label_raw ) > 80 ) ? substr( $label_raw, 0, 77 ) . '...' : $label_raw;
					if ( 'hour' === $field_filter ) {
						$label_short .= ':00';
					}
					$color = $bar_palette[ $idx % count( $bar_palette ) ];
					$idx++;
					?>
					<div class="cprt-breakdown-item">
						<div class="cprt-breakdown-meta">
							<span class="cprt-breakdown-name" title="<?php echo esc_attr( $label_raw ); ?>"><?php echo esc_html( '' !== $label_short ? $label_short : __( '(empty)', 'cp-referrer-and-conversions-tracking' ) ); ?></span>
							<span class="cprt-breakdown-stats">
								<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
								<span class="cprt-text-muted">(<?php echo esc_html( $pct ); ?>%)</span>
							</span>
						</div>
						<div class="cprt-bar-track">
							<div class="cprt-bar-fill" style="width:<?php echo esc_attr( $bar_width ); ?>%;background-color:<?php echo esc_attr( $color ); ?>;"></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
$this->render_admin_footer();

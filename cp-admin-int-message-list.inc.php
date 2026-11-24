<?php
/**
 * Tracking Logs list view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

global $wpdb;

$this->item       = 1;
$records_per_page = 50;
$current_page     = max( 1, absint( $this->get_get_param( 'p', '1' ) ) );
$search_value     = $this->get_get_param( 'search' );
$rawfrom          = $this->validate_date( $this->get_get_param( 'dfrom' ) );
$rawto            = $this->validate_date( $this->get_get_param( 'dto' ) );

$table  = $wpdb->prefix . $this->table_messages;
$filter = $this->build_logs_where( $search_value, $rawfrom, $rawto );

$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$filter['where']}";
if ( ! empty( $filter['params'] ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$count_sql = $wpdb->prepare( $count_sql, $filter['params'] );
}
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$total_items = (int) $wpdb->get_var( $count_sql );
$total_pages = max( 1, (int) ceil( $total_items / $records_per_page ) );
if ( $current_page > $total_pages ) {
	$current_page = $total_pages;
}
$offset = ( $current_page - 1 ) * $records_per_page;

$data_params   = $filter['params'];
$data_params[] = $records_per_page;
$data_params[] = $offset;

$events_query = $wpdb->prepare(
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT id, time, referrer, data FROM `{$table}` WHERE {$filter['where']} ORDER BY `time` DESC LIMIT %d OFFSET %d",
	$data_params
);
$events_query = apply_filters( 'cpreftrack_messages_query', $events_query );
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$events = $wpdb->get_results( $events_query );

$logs_nonce = wp_create_nonce( 'cpreftrack_actions_logs' );
$csv_nonce  = wp_create_nonce( 'cpreftrack_export_csv' );

$csv_url = add_query_arg(
	array(
		'page'                  => $this->menu_parameter . '_list',
		$this->prefix . '_csv'  => '1',
		'_cprt_csv_nonce'       => $csv_nonce,
		'search'                => $search_value,
		'dfrom'                 => $rawfrom,
		'dto'                   => $rawto,
	),
	admin_url( 'admin.php' )
);

$this->render_admin_header(
	'logs',
	__( 'Tracking Logs', 'cp-referrer-and-conversions-tracking' ),
	/* translators: %s: total matching log count */
	sprintf( __( '%s matching visitor records found.', 'cp-referrer-and-conversions-tracking' ), number_format_i18n( $total_items ) )
);
?>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-body">
		<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="cprt-filter-bar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_parameter ); ?>_list" />

			<div class="cprt-field cprt-field-grow">
				<label for="cprt-logs-search"><?php esc_html_e( 'Search', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="search" id="cprt-logs-search" name="search" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'Visitor ID / IP, referrer URL, or entry page...', 'cp-referrer-and-conversions-tracking' ); ?>" />
			</div>

			<div class="cprt-field">
				<label for="cprt-logs-from"><?php esc_html_e( 'From', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="date" id="cprt-logs-from" name="dfrom" value="<?php echo esc_attr( $rawfrom ); ?>" />
			</div>

			<div class="cprt-field">
				<label for="cprt-logs-to"><?php esc_html_e( 'To', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="date" id="cprt-logs-to" name="dto" value="<?php echo esc_attr( $rawto ); ?>" />
			</div>

			<div class="cprt-filter-actions">
				<button type="submit" class="cprt-btn cprt-btn-primary"><?php esc_html_e( 'Filter', 'cp-referrer-and-conversions-tracking' ); ?></button>
				<?php if ( '' !== $search_value || '' !== $rawfrom || '' !== $rawto ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' ) ); ?>" class="cprt-btn cprt-btn-ghost"><?php esc_html_e( 'Reset', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $csv_url ); ?>" class="cprt-btn cprt-btn-secondary"><?php esc_html_e( 'Export CSV', 'cp-referrer-and-conversions-tracking' ); ?></a>
			</div>
		</form>
	</div>
</section>

<section class="cprt-card">
	<div class="cprt-table-toolbar cprt-no-print">
		<div class="cprt-btn-group">
			<button type="button" class="cprt-btn cprt-btn-danger-outline" data-cprt-bulk-delete="cprt-logs-bulk-form">
				<?php esc_html_e( 'Delete Selected', 'cp-referrer-and-conversions-tracking' ); ?>
			</button>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' ) ); ?>" class="cprt-inline-form" data-cprt-confirm="deleteAll">
				<input type="hidden" name="anonce" value="<?php echo esc_attr( $logs_nonce ); ?>" />
				<input type="hidden" name="cpreftrack_logs_action" value="delete_all" />
				<button type="submit" class="cprt-btn cprt-btn-ghost-danger">
					<?php esc_html_e( 'Delete All Logs', 'cp-referrer-and-conversions-tracking' ); ?>
				</button>
			</form>
		</div>
		<button type="button" class="cprt-btn cprt-btn-secondary" data-cprt-print>
			<?php esc_html_e( 'Print', 'cp-referrer-and-conversions-tracking' ); ?>
		</button>
	</div>

	<form id="cprt-logs-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' ) ); ?>">
		<input type="hidden" name="anonce" value="<?php echo esc_attr( $logs_nonce ); ?>" />
		<input type="hidden" name="cpreftrack_logs_action" value="delete_marked" />

		<div class="cprt-table-wrap">
			<table class="cprt-table">
				<thead>
					<tr>
						<th class="cprt-col-check cprt-no-print">
							<input type="checkbox" data-cprt-select-all aria-label="<?php esc_attr_e( 'Select all rows', 'cp-referrer-and-conversions-tracking' ); ?>" />
						</th>
						<th><?php esc_html_e( 'Visitor ID / IP', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Timestamp', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Referrer', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Entry Page', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th class="cprt-col-actions cprt-no-print"><?php esc_html_e( 'Actions', 'cp-referrer-and-conversions-tracking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="6" class="cprt-empty-state">
								<?php esc_html_e( 'No tracking logs found for the current filters.', 'cp-referrer-and-conversions-tracking' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $row ) : ?>
							<?php
							$data      = $this->decode_log_data( $row->data );
							$raw_entry = isset( $data['entry'] ) ? (string) $data['entry'] : '';
							$entry_url = $this->format_entry_url( $raw_entry );
							$entry_lbl = ( strlen( $raw_entry ) > 55 ) ? '...' . substr( $raw_entry, -52 ) : $raw_entry;
							$is_ref_url = (bool) preg_match( '#^https?://#i', (string) $row->referrer );
							?>
							<tr>
								<td class="cprt-col-check cprt-no-print">
									<input type="checkbox" name="selected_ids[]" value="<?php echo esc_attr( $row->id ); ?>" class="cprt-row-checkbox" />
								</td>
								<td><code class="cprt-mono-pill"><?php echo esc_html( $row->id ); ?></code></td>
								<td class="cprt-nowrap"><?php echo esc_html( $row->time ); ?></td>
								<td class="cprt-break-word">
									<?php if ( $is_ref_url ) : ?>
										<a href="<?php echo esc_url( $row->referrer ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-link">
											<?php echo esc_html( $row->referrer ); ?>
										</a>
									<?php else : ?>
										<span class="cprt-badge cprt-badge-blue"><?php echo esc_html( $row->referrer ); ?></span>
									<?php endif; ?>
								</td>
								<td class="cprt-break-word">
									<?php if ( '' !== $entry_url ) : ?>
										<a href="<?php echo esc_url( $entry_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $raw_entry ); ?>" class="cprt-link">
											<?php echo esc_html( $entry_lbl ); ?>
										</a>
									<?php elseif ( '' !== $raw_entry ) : ?>
										<span><?php echo esc_html( $entry_lbl ); ?></span>
									<?php else : ?>
										<span class="cprt-text-muted">&mdash;</span>
									<?php endif; ?>
								</td>
								<td class="cprt-col-actions cprt-no-print">
									<button type="button"
											class="cprt-btn cprt-btn-sm cprt-btn-ghost-danger"
											data-cprt-delete-one="<?php echo esc_attr( $row->id ); ?>"
											data-cprt-target-form="cprt-logs-single-delete-form">
										<?php esc_html_e( 'Delete', 'cp-referrer-and-conversions-tracking' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</form>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="cprt-pagination cprt-no-print">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg(
							array(
								'page'   => $this->menu_parameter . '_list',
								'dfrom'  => $rawfrom,
								'dto'    => $rawto,
								'search' => $search_value,
								'p'      => '%#%',
							),
							admin_url( 'admin.php' )
						),
						'format'    => '',
						'total'     => $total_pages,
						'current'   => $current_page,
						'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'cp-referrer-and-conversions-tracking' ),
						'next_text' => esc_html__( 'Next', 'cp-referrer-and-conversions-tracking' ) . ' &raquo;',
						'type'      => 'list',
					)
				)
			);
			?>
		</div>
	<?php endif; ?>
</section>

<form id="cprt-logs-single-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' ) ); ?>" class="cprt-hidden">
	<input type="hidden" name="anonce" value="<?php echo esc_attr( $logs_nonce ); ?>" />
	<input type="hidden" name="cpreftrack_logs_action" value="delete_one" />
	<input type="hidden" name="item_id" value="" />
</form>

<?php
$this->render_admin_footer();

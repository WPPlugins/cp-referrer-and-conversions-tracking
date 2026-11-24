<?php
/**
 * Conversions list view.
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

$table   = $wpdb->prefix . $this->table_conversions;
$clauses = array( '1 = 1' );
$params  = array();

if ( '' !== $search_value ) {
	$like      = '%' . $wpdb->esc_like( $search_value ) . '%';
	$clauses[] = '(convdesc LIKE %s OR convname LIKE %s OR referrer LIKE %s OR referrerlast LIKE %s)';
	$params[]  = $like;
	$params[]  = $like;
	$params[]  = $like;
	$params[]  = $like;
}

if ( '' !== $rawfrom ) {
	$clauses[] = '`time` >= %s';
	$params[]  = $rawfrom . ' 00:00:00';
}

if ( '' !== $rawto ) {
	$clauses[] = '`time` <= %s';
	$params[]  = $rawto . ' 23:59:59';
}

$where_sql = implode( ' AND ', $clauses );

$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
if ( ! empty( $params ) ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$count_sql = $wpdb->prepare( $count_sql, $params );
}
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$total_items = (int) $wpdb->get_var( $count_sql );
$total_pages = max( 1, (int) ceil( $total_items / $records_per_page ) );
if ( $current_page > $total_pages ) {
	$current_page = $total_pages;
}
$offset = ( $current_page - 1 ) * $records_per_page;

$data_params   = $params;
$data_params[] = $records_per_page;
$data_params[] = $offset;

$events = $wpdb->get_results(
	$wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT id, time, convname, convdesc, referrer, entry, referrerlast FROM `{$table}` WHERE {$where_sql} ORDER BY `time` DESC LIMIT %d OFFSET %d",
		$data_params
	)
);

$conv_nonce   = wp_create_nonce( 'cpreftrack_actions_conversions' );
$allowed_html = $this->get_allowed_conversion_html();

$this->render_admin_header(
	'conversions',
	__( 'Conversions', 'cp-referrer-and-conversions-tracking' ),
	/* translators: %s: total conversions count */
	sprintf( __( '%s tracked conversions recorded.', 'cp-referrer-and-conversions-tracking' ), number_format_i18n( $total_items ) )
);
?>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-body">
		<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="cprt-filter-bar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_parameter ); ?>_conversions" />

			<div class="cprt-field cprt-field-grow">
				<label for="cprt-conv-search"><?php esc_html_e( 'Search', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="search" id="cprt-conv-search" name="search" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'Conversion type, order/form details, or referrer...', 'cp-referrer-and-conversions-tracking' ); ?>" />
			</div>

			<div class="cprt-field">
				<label for="cprt-conv-from"><?php esc_html_e( 'From', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="date" id="cprt-conv-from" name="dfrom" value="<?php echo esc_attr( $rawfrom ); ?>" />
			</div>

			<div class="cprt-field">
				<label for="cprt-conv-to"><?php esc_html_e( 'To', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="date" id="cprt-conv-to" name="dto" value="<?php echo esc_attr( $rawto ); ?>" />
			</div>

			<div class="cprt-filter-actions">
				<button type="submit" class="cprt-btn cprt-btn-primary"><?php esc_html_e( 'Filter', 'cp-referrer-and-conversions-tracking' ); ?></button>
				<?php if ( '' !== $search_value || '' !== $rawfrom || '' !== $rawto ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' ) ); ?>" class="cprt-btn cprt-btn-ghost"><?php esc_html_e( 'Reset', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</div>
</section>

<section class="cprt-card">
	<div class="cprt-table-toolbar cprt-no-print">
		<div class="cprt-btn-group">
			<button type="button" class="cprt-btn cprt-btn-danger-outline" data-cprt-bulk-delete="cprt-conv-bulk-form">
				<?php esc_html_e( 'Delete Selected', 'cp-referrer-and-conversions-tracking' ); ?>
			</button>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' ) ); ?>" class="cprt-inline-form" data-cprt-confirm="deleteAll">
				<input type="hidden" name="anonce" value="<?php echo esc_attr( $conv_nonce ); ?>" />
				<input type="hidden" name="cpreftrack_conversions_action" value="delete_all" />
				<button type="submit" class="cprt-btn cprt-btn-ghost-danger">
					<?php esc_html_e( 'Delete All Conversions', 'cp-referrer-and-conversions-tracking' ); ?>
				</button>
			</form>
		</div>
		<button type="button" class="cprt-btn cprt-btn-secondary" data-cprt-print>
			<?php esc_html_e( 'Print', 'cp-referrer-and-conversions-tracking' ); ?>
		</button>
	</div>

	<form id="cprt-conv-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' ) ); ?>">
		<input type="hidden" name="anonce" value="<?php echo esc_attr( $conv_nonce ); ?>" />
		<input type="hidden" name="cpreftrack_conversions_action" value="delete_marked" />

		<div class="cprt-table-wrap">
			<table class="cprt-table">
				<thead>
					<tr>
						<th class="cprt-col-check cprt-no-print">
							<input type="checkbox" data-cprt-select-all aria-label="<?php esc_attr_e( 'Select all rows', 'cp-referrer-and-conversions-tracking' ); ?>" />
						</th>
						<th><?php esc_html_e( 'ID', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Timestamp', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Conversion', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Details', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Attribution & Entry', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th class="cprt-col-actions cprt-no-print"><?php esc_html_e( 'Actions', 'cp-referrer-and-conversions-tracking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="7" class="cprt-empty-state">
								<?php esc_html_e( 'No conversions recorded yet. Enable an integration in Add-Ons to start tracking conversions.', 'cp-referrer-and-conversions-tracking' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $row ) : ?>
							<?php
							$filtered_details = apply_filters( $row->convname, $row->convdesc );
							$ref              = (string) $row->referrer;
							$reflast          = (string) $row->referrerlast;
							$raw_entry        = isset( $row->entry ) ? (string) $row->entry : '';
							$entry_url        = $this->format_entry_url( $raw_entry );
							$entry_lbl        = ( strlen( $raw_entry ) > 42 ) ? '...' . substr( $raw_entry, -39 ) : $raw_entry;
							?>
							<tr>
								<td class="cprt-col-check cprt-no-print">
									<input type="checkbox" name="selected_ids[]" value="<?php echo esc_attr( $row->id ); ?>" class="cprt-row-checkbox" />
								</td>
								<td><code class="cprt-mono-pill">#<?php echo esc_html( $row->id ); ?></code></td>
								<td class="cprt-nowrap"><?php echo esc_html( $row->time ); ?></td>
								<td><span class="cprt-badge cprt-badge-emerald"><?php echo esc_html( $row->convname ); ?></span></td>
								<td class="cprt-break-word"><?php echo wp_kses( $filtered_details, $allowed_html ); ?></td>
								<td class="cprt-break-word">
									<?php if ( '' !== $ref ) : ?>
										<div class="cprt-attr-block">
											<div>
												<span class="cprt-attr-label"><?php esc_html_e( 'First referrer:', 'cp-referrer-and-conversions-tracking' ); ?></span>
												<?php if ( preg_match( '#^https?://#i', $ref ) ) : ?>
													<a href="<?php echo esc_url( $ref ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-link"><?php echo esc_html( $ref ); ?></a>
												<?php else : ?>
													<strong><?php echo esc_html( $ref ); ?></strong>
												<?php endif; ?>
											</div>
											<?php if ( '' !== $reflast && $ref !== $reflast ) : ?>
												<div class="cprt-text-muted">
													<span class="cprt-attr-label"><?php esc_html_e( 'Last referrer:', 'cp-referrer-and-conversions-tracking' ); ?></span>
													<?php echo esc_html( $reflast ); ?>
												</div>
											<?php endif; ?>
											<?php if ( '' !== $raw_entry ) : ?>
												<div>
													<span class="cprt-attr-label"><?php esc_html_e( 'Entry page:', 'cp-referrer-and-conversions-tracking' ); ?></span>
													<?php if ( '' !== $entry_url ) : ?>
														<a href="<?php echo esc_url( $entry_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $raw_entry ); ?>" class="cprt-link"><?php echo esc_html( $entry_lbl ); ?></a>
													<?php else : ?>
														<span><?php echo esc_html( $entry_lbl ); ?></span>
													<?php endif; ?>
												</div>
											<?php endif; ?>
										</div>
									<?php else : ?>
										<span class="cprt-text-muted"><?php esc_html_e( 'Direct / Unknown referrer', 'cp-referrer-and-conversions-tracking' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="cprt-col-actions cprt-no-print">
									<button type="button"
											class="cprt-btn cprt-btn-sm cprt-btn-ghost-danger"
											data-cprt-delete-one="<?php echo esc_attr( $row->id ); ?>"
											data-cprt-target-form="cprt-conv-single-delete-form">
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
								'page'   => $this->menu_parameter . '_conversions',
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

<form id="cprt-conv-single-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' ) ); ?>" class="cprt-hidden">
	<input type="hidden" name="anonce" value="<?php echo esc_attr( $conv_nonce ); ?>" />
	<input type="hidden" name="cpreftrack_conversions_action" value="delete_one" />
	<input type="hidden" name="item_id" value="" />
</form>

<?php
$this->render_admin_footer();

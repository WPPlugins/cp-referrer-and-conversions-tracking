<?php
/**
 * Referral Sources / Campaign Parameters view.
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

$table     = $wpdb->prefix . $this->table_items;
$where_sql = '1 = 1';
$params    = array();

if ( '' !== $search_value ) {
	$like      = '%' . $wpdb->esc_like( $search_value ) . '%';
	$where_sql = '(refname LIKE %s OR paramname LIKE %s OR paramvalue LIKE %s)';
	$params    = array( $like, $like, $like );
}

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
		"SELECT id, paramname, paramvalue, refname FROM `{$table}` WHERE {$where_sql} ORDER BY `refname` ASC, `id` DESC LIMIT %d OFFSET %d",
		$data_params
	)
);

$params_nonce = wp_create_nonce( 'cpreftrack_actions_params' );
$site_base    = $this->get_site_url();

$this->render_admin_header(
	'sources',
	__( 'Referral Sources & Campaign Links', 'cp-referrer-and-conversions-tracking' ),
	__( 'Define custom query parameters to attribute visitors from ads, newsletters, and social posts even when HTTP referers are stripped.', 'cp-referrer-and-conversions-tracking' )
);
?>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-header">
		<h2><?php esc_html_e( 'Create New Campaign Link', 'cp-referrer-and-conversions-tracking' ); ?></h2>
	</div>
	<div class="cprt-card-body">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>" class="cprt-builder-form" data-cprt-site-url="<?php echo esc_attr( $site_base ); ?>">
			<input type="hidden" name="anonce" value="<?php echo esc_attr( $params_nonce ); ?>" />
			<input type="hidden" name="cpreftrack_params_action" value="add" />

			<div class="cprt-form-grid-3">
				<div class="cprt-field">
					<label for="cprt-refname"><?php esc_html_e( 'Referral Name', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-refname" name="refname" required maxlength="250" placeholder="<?php esc_attr_e( 'e.g. Facebook Ads Q1', 'cp-referrer-and-conversions-tracking' ); ?>" />
				</div>
				<div class="cprt-field">
					<label for="cprt-paramname"><?php esc_html_e( 'Parameter Name', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-paramname" name="paramname" required maxlength="250" placeholder="<?php esc_attr_e( 'e.g. utm_source or ref', 'cp-referrer-and-conversions-tracking' ); ?>" />
				</div>
				<div class="cprt-field">
					<label for="cprt-paramvalue"><?php esc_html_e( 'Parameter Value', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-paramvalue" name="paramvalue" required maxlength="250" placeholder="<?php esc_attr_e( 'e.g. facebook', 'cp-referrer-and-conversions-tracking' ); ?>" />
				</div>
			</div>

			<div class="cprt-preview-bar">
				<div class="cprt-preview-url">
					<span class="cprt-preview-label"><?php esc_html_e( 'Live Preview:', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<code id="cprt-live-preview"><?php echo esc_html( $site_base ); ?>/?<span class="cprt-hl-key">source</span>=<span class="cprt-hl-val">fromfacebook</span></code>
				</div>
				<button type="submit" class="cprt-btn cprt-btn-primary"><?php esc_html_e( 'Add Referral Source', 'cp-referrer-and-conversions-tracking' ); ?></button>
			</div>
		</form>
	</div>
</section>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-body">
		<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="cprt-filter-bar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->menu_parameter ); ?>_parameters" />
			<div class="cprt-field cprt-field-grow">
				<label for="cprt-params-search"><?php esc_html_e( 'Search Sources', 'cp-referrer-and-conversions-tracking' ); ?></label>
				<input type="search" id="cprt-params-search" name="search" value="<?php echo esc_attr( $search_value ); ?>" placeholder="<?php esc_attr_e( 'Filter by referral name, parameter, or value...', 'cp-referrer-and-conversions-tracking' ); ?>" />
			</div>
			<div class="cprt-filter-actions">
				<button type="submit" class="cprt-btn cprt-btn-primary"><?php esc_html_e( 'Filter', 'cp-referrer-and-conversions-tracking' ); ?></button>
				<?php if ( '' !== $search_value ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>" class="cprt-btn cprt-btn-ghost"><?php esc_html_e( 'Reset', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</div>
</section>

<section class="cprt-card">
	<div class="cprt-table-toolbar cprt-no-print">
		<div class="cprt-btn-group">
			<button type="button" class="cprt-btn cprt-btn-danger-outline" data-cprt-bulk-delete="cprt-params-bulk-form">
				<?php esc_html_e( 'Delete Selected', 'cp-referrer-and-conversions-tracking' ); ?>
			</button>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>" class="cprt-inline-form" data-cprt-confirm="deleteAll">
				<input type="hidden" name="anonce" value="<?php echo esc_attr( $params_nonce ); ?>" />
				<input type="hidden" name="cpreftrack_params_action" value="delete_all" />
				<button type="submit" class="cprt-btn cprt-btn-ghost-danger">
					<?php esc_html_e( 'Delete All Sources', 'cp-referrer-and-conversions-tracking' ); ?>
				</button>
			</form>
		</div>
		<button type="button" class="cprt-btn cprt-btn-secondary" data-cprt-print>
			<?php esc_html_e( 'Print', 'cp-referrer-and-conversions-tracking' ); ?>
		</button>
	</div>

	<form id="cprt-params-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>">
		<input type="hidden" name="anonce" value="<?php echo esc_attr( $params_nonce ); ?>" />
		<input type="hidden" name="cpreftrack_params_action" value="delete_marked" />

		<div class="cprt-table-wrap">
			<table class="cprt-table">
				<thead>
					<tr>
						<th class="cprt-col-check cprt-no-print">
							<input type="checkbox" data-cprt-select-all aria-label="<?php esc_attr_e( 'Select all rows', 'cp-referrer-and-conversions-tracking' ); ?>" />
						</th>
						<th><?php esc_html_e( 'Referral Name', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Parameter', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Value', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th><?php esc_html_e( 'Campaign Link', 'cp-referrer-and-conversions-tracking' ); ?></th>
						<th class="cprt-col-actions cprt-no-print"><?php esc_html_e( 'Actions', 'cp-referrer-and-conversions-tracking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $events ) ) : ?>
						<tr>
							<td colspan="6" class="cprt-empty-state">
								<?php esc_html_e( 'No custom referral sources configured yet. Add one above to generate trackable campaign links.', 'cp-referrer-and-conversions-tracking' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $events as $row ) : ?>
							<?php
							$encoded_key = rawurlencode( $row->paramname );
							$encoded_val = rawurlencode( $row->paramvalue );
							$sample_url  = $site_base . '/?' . $encoded_key . '=' . $encoded_val;
							?>
							<tr>
								<td class="cprt-col-check cprt-no-print">
									<input type="checkbox" name="selected_ids[]" value="<?php echo esc_attr( $row->id ); ?>" class="cprt-row-checkbox" />
								</td>
								<td><strong><?php echo esc_html( $row->refname ); ?></strong></td>
								<td><code class="cprt-mono-pill"><?php echo esc_html( $row->paramname ); ?></code></td>
								<td><code class="cprt-mono-pill"><?php echo esc_html( $row->paramvalue ); ?></code></td>
								<td class="cprt-break-word">
									<a href="<?php echo esc_url( $sample_url ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-link">
										<?php echo esc_html( $site_base ); ?>/?<span class="cprt-hl-key"><?php echo esc_html( $encoded_key ); ?></span>=<span class="cprt-hl-val"><?php echo esc_html( $encoded_val ); ?></span>
									</a>
								</td>
								<td class="cprt-col-actions cprt-no-print">
									<div class="cprt-btn-group cprt-btn-group-end">
										<button type="button" class="cprt-btn cprt-btn-sm cprt-btn-secondary" data-cprt-copy="<?php echo esc_attr( $sample_url ); ?>">
											<?php esc_html_e( 'Copy Link', 'cp-referrer-and-conversions-tracking' ); ?>
										</button>
										<button type="button"
												class="cprt-btn cprt-btn-sm cprt-btn-ghost-danger"
												data-cprt-delete-one="<?php echo esc_attr( $row->id ); ?>"
												data-cprt-target-form="cprt-params-single-delete-form">
											<?php esc_html_e( 'Delete', 'cp-referrer-and-conversions-tracking' ); ?>
										</button>
									</div>
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
								'page'   => $this->menu_parameter . '_parameters',
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

<form id="cprt-params-single-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>" class="cprt-hidden">
	<input type="hidden" name="anonce" value="<?php echo esc_attr( $params_nonce ); ?>" />
	<input type="hidden" name="cpreftrack_params_action" value="delete_one" />
	<input type="hidden" name="item_id" value="" />
</form>

<?php
$this->render_admin_footer();

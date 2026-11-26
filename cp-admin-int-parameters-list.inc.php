<?php
/**
 * Referral Sources & UTM Campaign Link Builder view.
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
$utm_enabled  = $this->is_utm_tracking_enabled();

$this->render_admin_header(
	'sources',
	__( 'Referral Sources & UTM Link Builder', 'cp-referrer-and-conversions-tracking' ),
	__( 'Generate standard UTM campaign URLs or register custom query parameter rules to attribute traffic reliably across ads, emails, and social platforms.', 'cp-referrer-and-conversions-tracking' )
);
?>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-header">
		<div>
			<h2><?php esc_html_e( 'UTM Campaign URL Builder', 'cp-referrer-and-conversions-tracking' ); ?></h2>
		</div>
		<div class="cprt-btn-group">
			<a href="<?php echo esc_url( $this->get_help_url( 'help-utm-sources' ) ); ?>" class="cprt-inline-help-link">
				<?php esc_html_e( 'How UTM parameters work', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
			</a>
			<span class="cprt-badge <?php echo $utm_enabled ? 'cprt-badge-emerald' : 'cprt-badge-amber'; ?>">
				<?php echo $utm_enabled ? esc_html__( 'Tracked Automatically', 'cp-referrer-and-conversions-tracking' ) : esc_html__( 'Native UTM Tracking Paused', 'cp-referrer-and-conversions-tracking' ); ?>
			</span>
		</div>
	</div>
	<div class="cprt-card-body">
		<div class="cprt-utm-builder" data-cprt-site-url="<?php echo esc_attr( $site_base . '/' ); ?>">
			<div class="cprt-form-grid-3">
				<div class="cprt-field">
					<label for="cprt-utm-url"><?php esc_html_e( 'Landing Page URL', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-utm-url" value="<?php echo esc_attr( $site_base . '/' ); ?>" placeholder="<?php echo esc_attr( $site_base . '/landing-page/' ); ?>" />
					<span class="cprt-field-hint"><?php esc_html_e( 'Any page on your site (defaults to homepage).', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<div class="cprt-field">
					<label for="cprt-utm-source"><?php esc_html_e( 'Campaign Source (utm_source)', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-utm-source" maxlength="150" placeholder="<?php esc_attr_e( 'e.g. google, newsletter, instagram', 'cp-referrer-and-conversions-tracking' ); ?>" />
					<span class="cprt-field-hint"><?php esc_html_e( 'The referrer or platform sending traffic.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<div class="cprt-field">
					<label for="cprt-utm-medium"><?php esc_html_e( 'Campaign Medium (utm_medium)', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-utm-medium" maxlength="150" placeholder="<?php esc_attr_e( 'e.g. cpc, email, paid_social', 'cp-referrer-and-conversions-tracking' ); ?>" />
					<span class="cprt-field-hint"><?php esc_html_e( 'Marketing channel or traffic type.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
			</div>

			<div class="cprt-form-grid-3">
				<div class="cprt-field">
					<label for="cprt-utm-campaign"><?php esc_html_e( 'Campaign Name (utm_campaign)', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-utm-campaign" maxlength="150" placeholder="<?php esc_attr_e( 'e.g. spring_sale, product_launch', 'cp-referrer-and-conversions-tracking' ); ?>" />
					<span class="cprt-field-hint"><?php esc_html_e( 'Promo code, campaign slug, or offer name.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<div class="cprt-field">
					<label for="cprt-utm-term"><?php esc_html_e( 'Campaign Term (utm_term)', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-utm-term" maxlength="150" placeholder="<?php esc_attr_e( 'Optional paid search keyword', 'cp-referrer-and-conversions-tracking' ); ?>" />
					<span class="cprt-field-hint"><?php esc_html_e( 'Optional targeting keyword or audience.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<div class="cprt-field">
					<label for="cprt-utm-content"><?php esc_html_e( 'Campaign Content (utm_content)', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-utm-content" maxlength="150" placeholder="<?php esc_attr_e( 'Optional ad variant, e.g. hero_cta', 'cp-referrer-and-conversions-tracking' ); ?>" />
					<span class="cprt-field-hint"><?php esc_html_e( 'Optional A/B test or creative identifier.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
			</div>

			<div class="cprt-preview-bar">
				<div class="cprt-preview-url">
					<span class="cprt-preview-label"><?php esc_html_e( 'Generated UTM URL:', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<code id="cprt-utm-live-preview"><?php echo esc_html( $site_base ); ?>/?<span class="cprt-hl-key">utm_source</span>=<span class="cprt-hl-val">google</span>&amp;<span class="cprt-hl-key">utm_medium</span>=<span class="cprt-hl-val">cpc</span></code>
				</div>
				<div class="cprt-btn-group">
					<button type="button" id="cprt-utm-reset" class="cprt-btn cprt-btn-ghost"><?php esc_html_e( 'Reset', 'cp-referrer-and-conversions-tracking' ); ?></button>
					<button type="button" id="cprt-utm-copy-btn" class="cprt-btn cprt-btn-primary" data-cprt-copy="" disabled>
						<?php esc_html_e( 'Copy UTM Link', 'cp-referrer-and-conversions-tracking' ); ?>
					</button>
				</div>
			</div>
			<p class="cprt-text-muted cprt-text-sm" style="margin:10px 0 0;">
				<?php esc_html_e( 'Standard UTM links work out of the box with no manual registration needed—incoming UTM parameters are automatically stored in Tracking Logs, Tracking Stats, CSV exports, and Conversions.', 'cp-referrer-and-conversions-tracking' ); ?>
			</p>
		</div>
	</div>
</section>

<section class="cprt-card cprt-no-print">
	<div class="cprt-card-header">
		<h2><?php esc_html_e( 'Create Custom Referral Rule', 'cp-referrer-and-conversions-tracking' ); ?></h2>
		<a href="<?php echo esc_url( $this->get_help_url( 'help-custom-rules' ) ); ?>" class="cprt-inline-help-link">
			<?php esc_html_e( 'When to use custom rules', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
		</a>
	</div>
	<div class="cprt-card-body">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>" class="cprt-builder-form" data-cprt-site-url="<?php echo esc_attr( $site_base ); ?>">
			<input type="hidden" name="anonce" value="<?php echo esc_attr( $params_nonce ); ?>" />
			<input type="hidden" name="cpreftrack_params_action" value="add" />

			<div class="cprt-form-grid-3">
				<div class="cprt-field">
					<label for="cprt-refname"><?php esc_html_e( 'Referral Name', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-refname" name="refname" required maxlength="250" placeholder="<?php esc_attr_e( 'e.g. Partner Affiliate A', 'cp-referrer-and-conversions-tracking' ); ?>" />
				</div>
				<div class="cprt-field">
					<label for="cprt-paramname"><?php esc_html_e( 'Parameter Name', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-paramname" name="paramname" required maxlength="250" placeholder="<?php esc_attr_e( 'e.g. ref or partner', 'cp-referrer-and-conversions-tracking' ); ?>" />
				</div>
				<div class="cprt-field">
					<label for="cprt-paramvalue"><?php esc_html_e( 'Parameter Value', 'cp-referrer-and-conversions-tracking' ); ?></label>
					<input type="text" id="cprt-paramvalue" name="paramvalue" required maxlength="250" placeholder="<?php esc_attr_e( 'e.g. partner1', 'cp-referrer-and-conversions-tracking' ); ?>" />
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
				<label for="cprt-params-search"><?php esc_html_e( 'Search Custom Sources', 'cp-referrer-and-conversions-tracking' ); ?></label>
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
								<?php esc_html_e( 'No custom referral rules configured yet. Use the UTM Campaign URL Builder above or add a custom parameter rule.', 'cp-referrer-and-conversions-tracking' ); ?>
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

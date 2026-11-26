<?php
/**
 * Plugin Dashboard view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

global $wpdb, $cpreftrack_addons_active_list, $cpreftrack_addons_objs_list;

$active_addons = get_option( 'cpreftrack_addons_active_list', array() );
$cpreftrack_addons_active_list = is_array( $active_addons ) ? $active_addons : array();

$logs_table = $wpdb->prefix . $this->table_messages;
$conv_table = $wpdb->prefix . $this->table_conversions;
$refs_table = $wpdb->prefix . $this->table_items;

$since_30_days = wp_date( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$total_logs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$logs_table}`" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$logs_30d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$logs_table}` WHERE `time` >= %s", $since_30_days ) );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$total_conversions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$conv_table}`" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$total_sources = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$refs_table}`" );

$active_addons_count = count( $cpreftrack_addons_active_list );

$this->render_admin_header(
	'dashboard',
	$this->plugin_name,
	__( 'Monitor incoming referral traffic, campaign URLs, and plugin conversions from one place.', 'cp-referrer-and-conversions-tracking' )
);

// Collect any active add-on health notices.
$addon_alerts_html = '';
if ( ! empty( $cpreftrack_addons_active_list ) && is_array( $cpreftrack_addons_objs_list ) ) {
	foreach ( $cpreftrack_addons_active_list as $addon_id ) {
		if ( isset( $cpreftrack_addons_objs_list[ $addon_id ] ) ) {
			$notice = $cpreftrack_addons_objs_list[ $addon_id ]->get_addon_settings();
			if ( is_string( $notice ) && '' !== trim( $notice ) ) {
				$addon_alerts_html .= $notice;
			}
		}
	}
}
?>

<?php if ( '' !== $addon_alerts_html ) : ?>
	<div class="cprt-alerts-stack">
		<?php echo wp_kses_post( $addon_alerts_html ); ?>
	</div>
<?php endif; ?>

<section class="cprt-kpi-grid" aria-label="<?php esc_attr_e( 'Key Metrics', 'cp-referrer-and-conversions-tracking' ); ?>">
	<div class="cprt-kpi-card">
		<span class="cprt-kpi-label"><?php esc_html_e( 'Total Referrer Logs', 'cp-referrer-and-conversions-tracking' ); ?></span>
		<strong class="cprt-kpi-value"><?php echo esc_html( number_format_i18n( $total_logs ) ); ?></strong>
		<span class="cprt-kpi-meta"><?php esc_html_e( 'Unique tracked visitors', 'cp-referrer-and-conversions-tracking' ); ?></span>
	</div>
	<div class="cprt-kpi-card">
		<span class="cprt-kpi-label"><?php esc_html_e( 'Logs (Last 30 Days)', 'cp-referrer-and-conversions-tracking' ); ?></span>
		<strong class="cprt-kpi-value cprt-accent-blue"><?php echo esc_html( number_format_i18n( $logs_30d ) ); ?></strong>
		<span class="cprt-kpi-meta"><?php esc_html_e( 'Recent incoming referrals', 'cp-referrer-and-conversions-tracking' ); ?></span>
	</div>
	<div class="cprt-kpi-card">
		<span class="cprt-kpi-label"><?php esc_html_e( 'Recorded Conversions', 'cp-referrer-and-conversions-tracking' ); ?></span>
		<strong class="cprt-kpi-value cprt-accent-emerald"><?php echo esc_html( number_format_i18n( $total_conversions ) ); ?></strong>
		<span class="cprt-kpi-meta"><?php esc_html_e( 'Forms, orders & bookings', 'cp-referrer-and-conversions-tracking' ); ?></span>
	</div>
	<div class="cprt-kpi-card">
		<span class="cprt-kpi-label"><?php esc_html_e( 'Active Integrations', 'cp-referrer-and-conversions-tracking' ); ?></span>
		<strong class="cprt-kpi-value cprt-accent-violet"><?php echo esc_html( number_format_i18n( $active_addons_count ) ); ?></strong>
		<span class="cprt-kpi-meta">
			<?php
			printf(
				/* translators: %d: number of custom referral campaign rules */
				esc_html( _n( '%d custom referral source', '%d custom referral sources', $total_sources, 'cp-referrer-and-conversions-tracking' ) ),
				$total_sources
			);
			?>
		</span>
	</div>
</section>

<section class="cprt-feature-grid" aria-label="<?php esc_attr_e( 'Quick Navigation', 'cp-referrer-and-conversions-tracking' ); ?>">
	<a class="cprt-feature-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' ) ); ?>">
		<div class="cprt-feature-icon cprt-icon-blue" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
		</div>
		<h3><?php esc_html_e( 'Tracking Logs', 'cp-referrer-and-conversions-tracking' ); ?></h3>
		<p><?php esc_html_e( 'Inspect every visitor with an identified referral URL, landing page, IP address, and timestamp, or export filtered logs to CSV.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		<span class="cprt-feature-cta"><?php esc_html_e( 'View Logs', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;</span>
	</a>

	<a class="cprt-feature-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_report' ) ); ?>">
		<div class="cprt-feature-icon cprt-icon-indigo" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
		</div>
		<h3><?php esc_html_e( 'Tracking Stats', 'cp-referrer-and-conversions-tracking' ); ?></h3>
		<p><?php esc_html_e( 'Analyze daily and hourly referral trends, 30-day / 12-week / 12-month summaries, and top referrer breakdowns.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		<span class="cprt-feature-cta"><?php esc_html_e( 'Open Analytics', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;</span>
	</a>

	<a class="cprt-feature-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>">
		<div class="cprt-feature-icon cprt-icon-amber" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
		</div>
		<h3><?php esc_html_e( 'Referral Sources', 'cp-referrer-and-conversions-tracking' ); ?></h3>
		<p><?php esc_html_e( 'Build standard UTM campaign URLs or define custom referral parameters to attribute traffic from ads, emails, and social platforms.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		<span class="cprt-feature-cta"><?php esc_html_e( 'Manage Campaigns', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;</span>
	</a>

	<a class="cprt-feature-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' ) ); ?>">
		<div class="cprt-feature-icon cprt-icon-emerald" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
		</div>
		<h3><?php esc_html_e( 'Conversions', 'cp-referrer-and-conversions-tracking' ); ?></h3>
		<p><?php esc_html_e( 'Review orders, bookings, registrations, and form submissions alongside both first-touch and last-touch referrers.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		<span class="cprt-feature-cta"><?php esc_html_e( 'View Conversions', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;</span>
	</a>

	<a class="cprt-feature-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_addons' ) ); ?>">
		<div class="cprt-feature-icon cprt-icon-violet" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
		</div>
		<h3><?php esc_html_e( 'Add-Ons', 'cp-referrer-and-conversions-tracking' ); ?></h3>
		<p><?php esc_html_e( 'Enable or disable built-in conversion integrations for WooCommerce, Contact Form 7, booking plugins, and user registrations.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		<span class="cprt-feature-cta"><?php esc_html_e( 'Configure Add-Ons', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;</span>
	</a>

	<a class="cprt-feature-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_settings' ) ); ?>">
		<div class="cprt-feature-icon cprt-icon-slate" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
		</div>
		<h3><?php esc_html_e( 'General Settings', 'cp-referrer-and-conversions-tracking' ); ?></h3>
		<p><?php esc_html_e( 'Toggle referrer logging on or off, configure automatic log retention days, and run database cleanup on demand.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		<span class="cprt-feature-cta"><?php esc_html_e( 'Open Settings', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;</span>
	</a>
</section>

<section class="cprt-card cprt-help-banner">
	<div class="cprt-card-body cprt-help-banner-body">
		<div>
			<h3 style="margin:0 0 4px;font-size:15px;"><?php esc_html_e( 'New to CP Referrer Tracking or want to get more from UTM campaigns?', 'cp-referrer-and-conversions-tracking' ); ?></h3>
			<p class="cprt-text-muted cprt-text-sm" style="margin:0;"><?php esc_html_e( 'Read our practical step-by-step guide covering UTM tagging, first-touch vs. last-touch conversions, WooCommerce attribution, and GDPR privacy settings.', 'cp-referrer-and-conversions-tracking' ); ?></p>
		</div>
		<a href="<?php echo esc_url( $this->get_help_url( 'help-overview' ) ); ?>" class="cprt-btn cprt-btn-primary">
			<?php esc_html_e( 'Open Help & User Guide', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
		</a>
	</div>
</section>

<footer class="cprt-footer-links">
	<a href="<?php echo esc_url( $this->get_help_url() ); ?>">
		<?php esc_html_e( 'Help & User Guide', 'cp-referrer-and-conversions-tracking' ); ?>
	</a>
	<span aria-hidden="true">&middot;</span>
	<a href="<?php echo esc_url( 'https://wordpress.dwbooster.com/contact-us' ); ?>" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Request Custom Modifications', 'cp-referrer-and-conversions-tracking' ); ?>
	</a>
	<span aria-hidden="true">&middot;</span>
	<a href="<?php echo esc_url( $this->plugin_URL ); ?>" target="_blank" rel="noopener noreferrer">
		<?php esc_html_e( 'Online Documentation', 'cp-referrer-and-conversions-tracking' ); ?>
	</a>
</footer>

<?php
$this->render_admin_footer();

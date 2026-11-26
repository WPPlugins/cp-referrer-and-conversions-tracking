<?php
/**
 * Built-in Help & User Guide view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

$dashboard_url   = admin_url( 'admin.php?page=' . $this->menu_parameter );
$logs_url        = admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' );
$stats_url       = admin_url( 'admin.php?page=' . $this->menu_parameter . '_report' );
$sources_url     = admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' );
$conversions_url = admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' );
$addons_url      = admin_url( 'admin.php?page=' . $this->menu_parameter . '_addons' );
$settings_url    = admin_url( 'admin.php?page=' . $this->menu_parameter . '_settings' );
$support_url     = admin_url( 'admin.php?page=' . $this->menu_parameter . '_support' );
$site_example    = $this->get_site_url();

$this->render_admin_header(
	'help',
	__( 'Help & User Guide', 'cp-referrer-and-conversions-tracking' ),
	__( 'Practical step-by-step instructions to get the most out of referrer tracking, UTM campaigns, analytics, and conversion attribution.', 'cp-referrer-and-conversions-tracking' )
);
?>

<div class="cprt-help-layout">
	<aside class="cprt-help-toc" aria-label="<?php esc_attr_e( 'Guide sections', 'cp-referrer-and-conversions-tracking' ); ?>">
		<div class="cprt-card">
			<div class="cprt-card-header">
				<h2><?php esc_html_e( 'On This Page', 'cp-referrer-and-conversions-tracking' ); ?></h2>
			</div>
			<nav class="cprt-help-toc-nav">
				<a href="#help-overview" class="cprt-toc-link is-current"><?php esc_html_e( 'Quick Start & How It Works', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-logs" class="cprt-toc-link"><?php esc_html_e( 'Tracking Logs & CSV Export', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-stats" class="cprt-toc-link"><?php esc_html_e( 'Tracking Stats & Analytics', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-utm-sources" class="cprt-toc-link"><?php esc_html_e( 'Native UTM Tracking & Builder', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-custom-rules" class="cprt-toc-link"><?php esc_html_e( 'Custom Referral Rules', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-conversions" class="cprt-toc-link"><?php esc_html_e( 'Conversions (First & Last Touch)', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-addons" class="cprt-toc-link"><?php esc_html_e( 'Add-Ons & Integrations', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-woocommerce" class="cprt-toc-link"><?php esc_html_e( 'WooCommerce Order Metabox', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-dashboard-widget" class="cprt-toc-link"><?php esc_html_e( 'WP Dashboard Widget', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-settings" class="cprt-toc-link"><?php esc_html_e( 'Settings & GDPR Privacy', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<a href="#help-developers" class="cprt-toc-link"><?php esc_html_e( 'Troubleshooting & Hooks', 'cp-referrer-and-conversions-tracking' ); ?></a>
			</nav>
		</div>
	</aside>

	<div class="cprt-help-content">
		<!-- 1. Overview & Quick Start -->
		<section id="help-overview" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '1. Quick Start & How the Plugin Works', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Understand the complete visitor-to-conversion workflow in under two minutes.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $dashboard_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open Dashboard', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p class="cprt-help-lead">
					<?php esc_html_e( 'CP Referrer and Conversion Tracking answers one essential marketing question: which websites, ads, emails, and campaigns actually bring you leads, bookings, and sales?', 'cp-referrer-and-conversions-tracking' ); ?>
				</p>

				<div class="cprt-help-steps">
					<div class="cprt-help-step">
						<span class="cprt-step-num">1</span>
						<div>
							<h3><?php esc_html_e( 'Capture the Entry Source', 'cp-referrer-and-conversions-tracking' ); ?></h3>
							<p><?php esc_html_e( 'When a visitor arrives from another website, a UTM-tagged campaign link, or a custom referral URL, the plugin automatically records their referring source, UTM tags, landing page, and timestamp.', 'cp-referrer-and-conversions-tracking' ); ?></p>
						</div>
					</div>
					<div class="cprt-help-step">
						<span class="cprt-step-num">2</span>
						<div>
							<h3><?php esc_html_e( 'Preserve First-Touch & Last-Touch', 'cp-referrer-and-conversions-tracking' ); ?></h3>
							<p><?php esc_html_e( 'The very first external source is preserved in the database (first-touch), while any newer campaign or external visit before checkout or form submission is captured as last-touch.', 'cp-referrer-and-conversions-tracking' ); ?></p>
						</div>
					</div>
					<div class="cprt-help-step">
						<span class="cprt-step-num">3</span>
						<div>
							<h3><?php esc_html_e( 'Attribute Real Conversions', 'cp-referrer-and-conversions-tracking' ); ?></h3>
							<p><?php esc_html_e( 'When that visitor places a WooCommerce order, submits a contact or booking form, or registers an account, the plugin links the conversion straight back to both their first and last traffic sources.', 'cp-referrer-and-conversions-tracking' ); ?></p>
						</div>
					</div>
				</div>

				<div class="cprt-help-callout cprt-callout-blue">
					<strong><?php esc_html_e( '5-Minute Setup Checklist', 'cp-referrer-and-conversions-tracking' ); ?></strong>
					<ol class="cprt-help-list">
						<li>
							<?php esc_html_e( 'Verify tracking and native UTM capture are switched on under', 'cp-referrer-and-conversions-tracking' ); ?>
							<a href="<?php echo esc_url( $settings_url ); ?>" class="cprt-link"><?php esc_html_e( 'General Settings', 'cp-referrer-and-conversions-tracking' ); ?></a>
							<?php esc_html_e( 'and pick your preferred GDPR IP mode (Salted Hash is enabled by default).', 'cp-referrer-and-conversions-tracking' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Open', 'cp-referrer-and-conversions-tracking' ); ?>
							<a href="<?php echo esc_url( $addons_url ); ?>" class="cprt-link"><?php esc_html_e( 'Add-Ons', 'cp-referrer-and-conversions-tracking' ); ?></a>
							<?php esc_html_e( 'and enable the integrations installed on your site (e.g., WooCommerce, Contact Form 7, booking plugins, or User Registrations).', 'cp-referrer-and-conversions-tracking' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Use the', 'cp-referrer-and-conversions-tracking' ); ?>
							<a href="<?php echo esc_url( $sources_url ); ?>" class="cprt-link"><?php esc_html_e( 'UTM Campaign URL Builder', 'cp-referrer-and-conversions-tracking' ); ?></a>
							<?php esc_html_e( 'to generate tagged links for your ads, newsletters, and social media bios.', 'cp-referrer-and-conversions-tracking' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Test it yourself: open an Incognito/Private browser window, visit', 'cp-referrer-and-conversions-tracking' ); ?>
							<code class="cprt-mono-pill"><?php echo esc_html( $site_example ); ?>/?utm_source=test&amp;utm_medium=guide</code>,
							<?php esc_html_e( 'then check', 'cp-referrer-and-conversions-tracking' ); ?>
							<a href="<?php echo esc_url( $logs_url ); ?>" class="cprt-link"><?php esc_html_e( 'Tracking Logs', 'cp-referrer-and-conversions-tracking' ); ?></a>.
						</li>
					</ol>
				</div>

				<div class="cprt-help-callout cprt-callout-neutral">
					<strong><?php esc_html_e( 'How Source Priority Works:', 'cp-referrer-and-conversions-tracking' ); ?></strong>
					<?php esc_html_e( 'If a single visit includes multiple signals, the plugin chooses the primary Referrer label in this order: (1) a matching Custom Referral Rule, (2) the external HTTP Referer URL, or (3) an automatic "UTM: source / medium / campaign" label when the browser hides the HTTP referer. In all cases, any UTM parameters present on the URL are also saved separately.', 'cp-referrer-and-conversions-tracking' ); ?>
				</div>
			</div>
		</section>

		<!-- 2. Tracking Logs -->
		<section id="help-logs" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '2. Tracking Logs & CSV Export', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Inspect individual incoming visits, filter by campaign or date, and export clean spreadsheets.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $logs_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open Tracking Logs', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'Every visitor who arrives from an external website, a UTM link, or a custom referral rule creates one entry in Tracking Logs (keyed by their privacy-safe Visitor ID so repeat page views do not flood your database).', 'cp-referrer-and-conversions-tracking' ); ?></p>

				<ul class="cprt-help-list">
					<li><strong><?php esc_html_e( 'Visitor ID / IP:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Shows a one-way salted hash (e.g. h_9a4c...), anonymized IP, or full IP depending on your Privacy setting.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Referrer:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Clickable external website URL, custom campaign name badge, or automatic UTM summary badge.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'UTM Parameters:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Displays color-coded chips for any captured utm_source, utm_medium, utm_campaign, utm_term, and utm_content values.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Entry Page:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'The exact landing page on your site where the visitor first arrived.', 'cp-referrer-and-conversions-tracking' ); ?></li>
				</ul>

				<div class="cprt-help-tip">
					<strong><?php esc_html_e( 'Practical Tip — Filtering & CSV Export:', 'cp-referrer-and-conversions-tracking' ); ?></strong>
					<?php esc_html_e( 'The Search box matches Visitor IDs, referrer URLs, landing pages, AND UTM values (for example, type "spring_sale" or "cpc" to isolate one campaign). Clicking Export CSV downloads only your currently filtered date range and search results, with dedicated columns for UTM Source, UTM Medium, UTM Campaign, UTM Term, and UTM Content.', 'cp-referrer-and-conversions-tracking' ); ?>
				</div>
			</div>
		</section>

		<!-- 3. Tracking Stats -->
		<section id="help-stats" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '3. Tracking Stats & Analytics', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Spot traffic trends, peak hours, and winning UTM campaigns at a glance.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $stats_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open Tracking Stats', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<ul class="cprt-help-list">
					<li><strong><?php esc_html_e( 'Historical Summary Strip:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Displays log totals grouped by Year, Last 12 Months, Last 12 Weeks, and Last 30 Days. Keep in mind that expired logs removed by your retention setting are no longer included in older totals.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Interactive Daily & Hourly Charts:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Hover over any point or bar to inspect exact daily volume or see which hours of the day (00:00–23:00) bring the most referrals—useful for scheduling ad budgets and newsletter send times.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Top Values Breakdown Dropdown:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Switch between Referrer, UTM Source, UTM Medium, UTM Campaign, Date, Hour of Day, or Visitor ID to see the top 50 values and their exact percentage share.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Print-Ready Reports:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Use the Print button to print or Save as PDF—navigation menus and filter controls are hidden automatically for clean client or team reporting.', 'cp-referrer-and-conversions-tracking' ); ?></li>
				</ul>
			</div>
		</section>

		<!-- 4. Native UTM Tracking & UTM Builder -->
		<section id="help-utm-sources" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '4. Native UTM Tracking & Campaign URL Builder', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Track ads, emails, and social links reliably—even when mobile apps strip HTTP referers.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $sources_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open UTM Builder', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'Many mobile apps (Instagram, TikTok, WhatsApp, Gmail, and Apple Mail) strip the browser HTTP referer header for privacy. Adding standard UTM tags to your marketing links guarantees accurate attribution with zero manual rule setup—just share any URL containing UTM parameters and the plugin captures them automatically.', 'cp-referrer-and-conversions-tracking' ); ?></p>

				<div class="cprt-table-wrap cprt-help-table-wrap">
					<table class="cprt-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'cp-referrer-and-conversions-tracking' ); ?></th>
								<th><?php esc_html_e( 'What It Identifies', 'cp-referrer-and-conversions-tracking' ); ?></th>
								<th><?php esc_html_e( 'Practical Examples', 'cp-referrer-and-conversions-tracking' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code class="cprt-mono-pill">utm_source</code></td>
								<td><?php esc_html_e( 'The platform, site, or sender driving the click.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><code>google</code>, <code>facebook</code>, <code>newsletter</code>, <code>instagram</code></td>
							</tr>
							<tr>
								<td><code class="cprt-mono-pill">utm_medium</code></td>
								<td><?php esc_html_e( 'The marketing channel or traffic type.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><code>cpc</code>, <code>email</code>, <code>paid_social</code>, <code>qr_code</code></td>
							</tr>
							<tr>
								<td><code class="cprt-mono-pill">utm_campaign</code></td>
								<td><?php esc_html_e( 'The specific promotion, launch, or campaign name.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><code>spring_sale</code>, <code>black_friday</code>, <code>webinar_may</code></td>
							</tr>
							<tr>
								<td><code class="cprt-mono-pill">utm_term</code></td>
								<td><?php esc_html_e( 'Optional paid search keyword or targeting audience.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><code>booking_plugin</code>, <code>retargeting_30d</code></td>
							</tr>
							<tr>
								<td><code class="cprt-mono-pill">utm_content</code></td>
								<td><?php esc_html_e( 'Optional creative, link position, or A/B test variant.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><code>hero_button</code>, <code>banner_blue</code>, <code>footer_link</code></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="cprt-help-tip">
					<strong><?php esc_html_e( 'Naming Best Practices:', 'cp-referrer-and-conversions-tracking' ); ?></strong>
					<?php esc_html_e( 'Keep UTM values lowercase and use underscores instead of spaces (e.g. "paid_social" instead of "Paid Social"). Consistent naming prevents duplicate rows in Tracking Stats. Never add UTM parameters to internal links between pages on your own website, or they will overwrite the visitor\'s last-touch source.', 'cp-referrer-and-conversions-tracking' ); ?>
				</div>
			</div>
		</section>

		<!-- 5. Custom Referral Rules -->
		<section id="help-custom-rules" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '5. Custom Referral Rules', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Create short, readable tracking parameters for affiliates, partners, podcasts, or print materials.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $sources_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Manage Referral Sources', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'While standard UTM parameters work automatically, Custom Referral Rules let you define clean, branded query parameters and map them to a friendly display name in your reports.', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<ul class="cprt-help-list">
					<li><strong><?php esc_html_e( 'Example:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Set Referral Name to "Partner: Acme Blog", Parameter Name to "ref", and Parameter Value to "acme". Share', 'cp-referrer-and-conversions-tracking' ); ?> <code class="cprt-mono-pill"><?php echo esc_html( $site_example ); ?>/?ref=acme</code> <?php esc_html_e( 'and every visit will be logged cleanly as "Partner: Acme Blog".', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Highest Priority:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'If a visitor clicks a Custom Referral Rule link from another website, your friendly Referral Name overrides the raw external URL so partner traffic stays cleanly grouped.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'One-Click Copy:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Once saved, click Copy Link next to any row in the table to copy the full campaign URL immediately.', 'cp-referrer-and-conversions-tracking' ); ?></li>
				</ul>
			</div>
		</section>

		<!-- 6. Conversions -->
		<section id="help-conversions" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '6. Conversions: First-Touch vs. Last-Touch Attribution', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'See both how customers first discovered you and what finally convinced them to convert.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $conversions_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open Conversions', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'Many customers do not buy or inquire on their very first visit. The Conversions screen records both touchpoints whenever they differ:', 'cp-referrer-and-conversions-tracking' ); ?></p>

				<div class="cprt-grid cprt-grid-2 cprt-help-compare">
					<div class="cprt-help-mini-card">
						<h4><?php esc_html_e( 'First-Touch (Discovery)', 'cp-referrer-and-conversions-tracking' ); ?></h4>
						<p><?php esc_html_e( 'The original external website, custom link, or UTM campaign that first introduced the visitor to your site. Ideal for measuring top-of-funnel channels like SEO, blog mentions, and awareness ads.', 'cp-referrer-and-conversions-tracking' ); ?></p>
					</div>
					<div class="cprt-help-mini-card">
						<h4><?php esc_html_e( 'Last-Touch (Closer)', 'cp-referrer-and-conversions-tracking' ); ?></h4>
						<p><?php esc_html_e( 'Shown automatically if the visitor returned through a different external source or UTM campaign before converting. Ideal for measuring retargeting ads, email follow-ups, and promo codes.', 'cp-referrer-and-conversions-tracking' ); ?></p>
					</div>
				</div>

				<p class="cprt-text-muted cprt-text-sm" style="margin:12px 0 0;">
					<?php esc_html_e( 'Tip: Use the Conversions search bar to search by Order ID, customer email (when included in form details), conversion type, referrer domain, or UTM campaign name.', 'cp-referrer-and-conversions-tracking' ); ?>
				</p>
			</div>
		</section>

		<!-- 7. Add-Ons -->
		<section id="help-addons" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '7. Add-Ons & Supported Integrations', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Connect your e-commerce store, contact forms, and booking calendars with one switch.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $addons_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open Add-Ons', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'To keep your site fast, conversion listeners only run for integrations you enable under Add-Ons. Toggle on any plugins installed on your site and click Save:', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<div class="cprt-help-pill-grid">
					<span class="cprt-badge cprt-badge-emerald"><?php esc_html_e( 'WooCommerce (HPOS & Classic)', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Contact Form 7', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Calculated Fields Form', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Appointment Hour Booking', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Appointment Booking Calendar', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Booking Calendar Contact Form', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Contact Form to Email', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'Contact Form with PayPal', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'WP Time Slots Booking Form', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-blue"><?php esc_html_e( 'CP Polls', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-badge cprt-badge-muted"><?php esc_html_e( 'WordPress User Registrations', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<p class="cprt-text-muted cprt-text-sm" style="margin:12px 0 0;">
					<?php esc_html_e( 'If an enabled add-on\'s companion plugin is deactivated or missing, a configuration alert appears automatically on the plugin Dashboard. Need a custom integration? Use the cpreftrack_register_conversion hook in Section 11.', 'cp-referrer-and-conversions-tracking' ); ?>
				</p>
			</div>
		</section>

		<!-- 8. WooCommerce Order Attribution Metabox -->
		<section id="help-woocommerce" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '8. WooCommerce Order Attribution Metabox', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'See where every customer came from directly inside the WooCommerce Order screen.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $addons_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Check WooCommerce Add-On', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'When the WooCommerce Conversion Tracking add-on is enabled, every customer checkout saves full attribution metadata on the order and displays a dedicated CP Referrer & UTM Attribution box in the right sidebar of the Edit Order screen.', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<ul class="cprt-help-list">
					<li><strong><?php esc_html_e( 'HPOS & Classic Ready:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Works seamlessly with both WooCommerce High-Performance Order Storage (Custom Order Tables) and Classic Post Storage.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Complete Order Snapshot:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Shows the Origin Type badge (UTM Campaign, External Referral, Campaign Source, or Direct / Unknown), First-Touch and Last-Touch referrers, Initial Landing Page, and color-coded First/Last UTM chips.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Clean Manual Orders:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Orders created manually by store admins inside wp-admin are intentionally ignored so your own browsing session is never attributed to a customer\'s phone or invoice order.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'One-Click Jump:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Click View Conversion Log at the bottom of the metabox to open that exact order inside the plugin\'s Conversions table.', 'cp-referrer-and-conversions-tracking' ); ?></li>
				</ul>
			</div>
		</section>

		<!-- 9. WordPress Dashboard Widget -->
		<section id="help-dashboard-widget" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '9. WordPress Dashboard Overview Widget', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Check 7-day marketing performance every time you log into WordPress.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open WP Dashboard', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<p><?php esc_html_e( 'The CP Referrer & Conversion Overview widget appears on your main WordPress Dashboard for authorized administrators and gives you an instant 7-day pulse check without extra clicks:', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<ul class="cprt-help-list">
					<li><strong><?php esc_html_e( '7-Day KPIs & Sparkline:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Displays total referrals and conversions over the last 7 days, a relative conversion rate badge, and an interactive daily bar sparkline (hover any bar to view the date and exact count).', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Top 5 Referrers & Recent 5 Conversions:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Quickly spot which traffic source is driving clicks this week and which leads or orders just came in.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Show or Hide Anytime:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Hide the widget globally via Show WordPress Dashboard Widget in General Settings, or per-user via WordPress Screen Options in the top-right corner of the Dashboard.', 'cp-referrer-and-conversions-tracking' ); ?></li>
				</ul>
			</div>
		</section>

		<!-- 10. General Settings & GDPR Privacy -->
		<section id="help-settings" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '10. General Settings, Retention & GDPR Privacy', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Balance accurate attribution with privacy-first data collection.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Open Settings', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<div id="help-privacy" class="cprt-help-anchor"></div>
				<div class="cprt-table-wrap cprt-help-table-wrap">
					<table class="cprt-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'IP Handling Mode', 'cp-referrer-and-conversions-tracking' ); ?></th>
								<th><?php esc_html_e( 'How It Works', 'cp-referrer-and-conversions-tracking' ); ?></th>
								<th><?php esc_html_e( 'Best For', 'cp-referrer-and-conversions-tracking' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Salted One-Way Hash', 'cp-referrer-and-conversions-tracking' ); ?></strong> <span class="cprt-badge cprt-badge-emerald"><?php esc_html_e( 'Recommended', 'cp-referrer-and-conversions-tracking' ); ?></span></td>
								<td><?php esc_html_e( 'Converts the IP into an irreversible salted cryptographic hash before saving.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><?php esc_html_e( 'GDPR-conscious sites that still want accurate visitor attribution.', 'cp-referrer-and-conversions-tracking' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Masked / Anonymized IP', 'cp-referrer-and-conversions-tracking' ); ?></strong></td>
								<td><?php esc_html_e( 'Zeroes the final IPv4/IPv6 octets using WordPress Core anonymization.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><?php esc_html_e( 'Sites preferring human-readable subnet-masked IPs.', 'cp-referrer-and-conversions-tracking' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Full IP Address (Legacy)', 'cp-referrer-and-conversions-tracking' ); ?></strong></td>
								<td><?php esc_html_e( 'Stores the visitor\'s raw IP address in Tracking Logs.', 'cp-referrer-and-conversions-tracking' ); ?></td>
								<td><?php esc_html_e( 'Internal tools or legacy workflows requiring raw IPs.', 'cp-referrer-and-conversions-tracking' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<ul class="cprt-help-list">
					<li><strong><?php esc_html_e( 'Honor DNT & GPC:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Automatically skips logging, UTM capture, and cookies when a visitor\'s browser sends Do Not Track (DNT: 1) or Global Privacy Control (Sec-GPC: 1).', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Cookieless Mode:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Stops setting all four first-party cookies (cprreftrack, cprreftracklatest, cprreftrackutm, cprreftrackutmlatest). First-touch referrer and UTM attribution continue working server-side; only last-touch cookie updates are disabled.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'Automatic Log Retention & Manual Cleanup:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Old logs are automatically pruned once a day after your configured number of days (default: 90). You can also click Prune Expired Logs Now anytime.', 'cp-referrer-and-conversions-tracking' ); ?></li>
					<li><strong><?php esc_html_e( 'WordPress Privacy Tools & Clean Uninstall:', 'cp-referrer-and-conversions-tracking' ); ?></strong> <?php esc_html_e( 'Ready-to-use disclosure text is added to Settings > Privacy > Policy Guide, matching conversion records integrate with Personal Data Export/Erasure, and enabling Delete All Plugin Data on Uninstall drops all plugin tables and options if you remove the plugin.', 'cp-referrer-and-conversions-tracking' ); ?></li>
				</ul>
			</div>
		</section>

		<!-- 11. Troubleshooting & Developer Hooks -->
		<section id="help-developers" class="cprt-card cprt-help-section">
			<div class="cprt-card-header">
				<div>
					<h2><?php esc_html_e( '11. Troubleshooting & Developer Hooks', 'cp-referrer-and-conversions-tracking' ); ?></h2>
					<span class="cprt-text-muted cprt-text-sm"><?php esc_html_e( 'Quick answers to common questions and code snippets for custom integrations.', 'cp-referrer-and-conversions-tracking' ); ?></span>
				</div>
				<a href="<?php echo esc_url( $support_url ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary">
					<?php esc_html_e( 'Support & Docs', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
				</a>
			</div>
			<div class="cprt-card-body">
				<div class="cprt-help-faq-list">
					<details class="cprt-help-faq" open>
						<summary><?php esc_html_e( 'Why did my own test visit not appear in Tracking Logs?', 'cp-referrer-and-conversions-tracking' ); ?></summary>
						<p><?php esc_html_e( 'Three common reasons: (1) Direct visits without an external referrer or UTM parameter are intentionally skipped; (2) If you already visited earlier from the same IP/browser, your original first-touch log is kept rather than duplicated; (3) Your browser may have DNT/GPC enabled while Honor DNT is turned on. To verify tracking cleanly, open an Incognito/Private window and visit a UTM URL like ?utm_source=test&utm_medium=check.', 'cp-referrer-and-conversions-tracking' ); ?></p>
					</details>
					<details class="cprt-help-faq">
						<summary><?php esc_html_e( 'Why does a conversion show "Direct / Unknown"?', 'cp-referrer-and-conversions-tracking' ); ?></summary>
						<p><?php esc_html_e( 'The visitor typed your URL directly, used a bookmark, or clicked a link from an app that stripped the HTTP referer header without UTM tags. Adding UTM parameters to your ads, emails, and social links dramatically reduces "Direct / Unknown" conversions.', 'cp-referrer-and-conversions-tracking' ); ?></p>
					</details>
					<details class="cprt-help-faq">
						<summary><?php esc_html_e( 'How does full-page caching affect tracking?', 'cp-referrer-and-conversions-tracking' ); ?></summary>
						<p><?php esc_html_e( 'Most WordPress hosts bypass page cache when query strings like ?utm_source=... or ?ref=... are present, so UTM and custom campaign links are recorded reliably even on heavily cached sites. If your host strips UTM query strings at the CDN/cache layer, allowlist utm_* parameters in your cache settings.', 'cp-referrer-and-conversions-tracking' ); ?></p>
					</details>
				</div>

				<hr class="cprt-divider" />

				<p class="cprt-label"><?php esc_html_e( 'Register a custom conversion from your own theme or plugin:', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<pre class="cprt-code"><code>do_action( 'cpreftrack_register_conversion', 'my_custom_conversion', 'Lead #1042' );</code></pre>

				<p class="cprt-label"><?php esc_html_e( 'Read the current visitor\'s referrer, UTM tags, or full attribution snapshot:', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<pre class="cprt-code"><code>$referrer    = apply_filters( 'cpreftrack_referrer', '' );
$utm_params  = apply_filters( 'cpreftrack_utm_data', array() );
$attribution = apply_filters( 'cpreftrack_attribution_data', array() );</code></pre>

				<p class="cprt-label"><?php esc_html_e( 'Customize the capability required to view plugin screens (default: manage_options):', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<pre class="cprt-code"><code>add_filter( 'cpreftrack_capability', function() {
    return 'manage_woocommerce';
} );</code></pre>
			</div>
		</section>
	</div>
</div>

<?php
$this->render_admin_footer();

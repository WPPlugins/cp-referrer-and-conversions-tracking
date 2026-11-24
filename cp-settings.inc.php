<?php
/**
 * General & GDPR/Privacy Settings view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

$nonce               = wp_create_nonce( 'cpreftrack_actions_admin' );
$tracking_enabled    = ( '' === get_option( 'cp_cpreftrack_rep_enable', '' ) );
$retention_days      = max( 1, absint( get_option( 'cp_cpreftrack_rep_days', 90 ) ) );
$ip_mode             = $this->get_ip_storage_mode();
$respect_dnt         = ( 'yes' === get_option( 'cp_cpreftrack_respect_dnt', 'no' ) );
$cookieless          = $this->is_cookieless_mode();
$delete_on_uninstall = ( 'yes' === get_option( 'cp_cpreftrack_delete_on_uninstall', 'no' ) );
$next_cleanup_ts     = wp_next_scheduled( 'cpreftrack_del_old_hook_fmin' );

$ip_mode_labels = array(
	'hash'      => __( 'Salted Hash (GDPR Recommended)', 'cp-referrer-and-conversions-tracking' ),
	'anonymize' => __( 'Masked / Anonymized IP', 'cp-referrer-and-conversions-tracking' ),
	'full'      => __( 'Full IP Address (Legacy)', 'cp-referrer-and-conversions-tracking' ),
);

$this->render_admin_header(
	'settings',
	__( 'General & Privacy Settings', 'cp-referrer-and-conversions-tracking' ),
	__( 'Configure referrer logging, GDPR privacy controls, cookie behavior, and automatic database retention.', 'cp-referrer-and-conversions-tracking' )
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_settings' ) ); ?>" class="cprt-settings-form">
	<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
	<input type="hidden" name="CP_REFTRACK_post_edition" value="1" />

	<div class="cprt-grid cprt-grid-2">
		<section class="cprt-card">
			<div class="cprt-card-header">
				<h2><?php esc_html_e( 'Tracking & Retention', 'cp-referrer-and-conversions-tracking' ); ?></h2>
			</div>
			<div class="cprt-card-body">
				<div class="cprt-setting-row">
					<div class="cprt-setting-info">
						<label for="cprt-enable-tracking" class="cprt-setting-label">
							<?php esc_html_e( 'Enable Referrer Tracking', 'cp-referrer-and-conversions-tracking' ); ?>
						</label>
						<p class="cprt-setting-desc">
							<?php esc_html_e( 'When enabled, external HTTP referrers and matching campaign parameters are recorded for incoming site visitors.', 'cp-referrer-and-conversions-tracking' ); ?>
						</p>
					</div>
					<label class="cprt-switch">
						<input type="checkbox" id="cprt-enable-tracking" name="cp_cpreftrack_rep_enable_toggle" value="1" <?php checked( $tracking_enabled ); ?> />
						<span class="cprt-switch-slider"></span>
					</label>
				</div>

				<div class="cprt-setting-row">
					<div class="cprt-setting-info">
						<label for="cprt-retention-days" class="cprt-setting-label">
							<?php esc_html_e( 'Automatic Log Retention (Days)', 'cp-referrer-and-conversions-tracking' ); ?>
						</label>
						<p class="cprt-setting-desc">
							<?php esc_html_e( 'Tracking logs older than this number of days are automatically purged once per day to minimize stored data.', 'cp-referrer-and-conversions-tracking' ); ?>
						</p>
					</div>
					<div class="cprt-number-unit">
						<input type="number" id="cprt-retention-days" name="cp_cpreftrack_rep_days" min="1" max="3650" step="1" value="<?php echo esc_attr( $retention_days ); ?>" required />
						<span><?php esc_html_e( 'days', 'cp-referrer-and-conversions-tracking' ); ?></span>
					</div>
				</div>

				<div class="cprt-setting-row">
					<div class="cprt-setting-info">
						<label for="cprt-delete-uninstall" class="cprt-setting-label">
							<?php esc_html_e( 'Delete All Plugin Data on Uninstall', 'cp-referrer-and-conversions-tracking' ); ?>
						</label>
						<p class="cprt-setting-desc">
							<?php esc_html_e( 'When enabled, deleting this plugin from the WordPress Plugins screen will permanently drop all tracking tables and options.', 'cp-referrer-and-conversions-tracking' ); ?>
						</p>
					</div>
					<label class="cprt-switch">
						<input type="checkbox" id="cprt-delete-uninstall" name="cp_cpreftrack_delete_on_uninstall" value="1" <?php checked( $delete_on_uninstall ); ?> />
						<span class="cprt-switch-slider"></span>
					</label>
				</div>
			</div>
		</section>

		<section class="cprt-card">
			<div class="cprt-card-header">
				<h2><?php esc_html_e( 'GDPR & Privacy Controls', 'cp-referrer-and-conversions-tracking' ); ?></h2>
				<span class="cprt-badge <?php echo ( 'full' !== $ip_mode ) ? 'cprt-badge-emerald' : 'cprt-badge-amber'; ?>">
					<?php echo ( 'full' !== $ip_mode ) ? esc_html__( 'Privacy Protected', 'cp-referrer-and-conversions-tracking' ) : esc_html__( 'Raw IP Storage', 'cp-referrer-and-conversions-tracking' ); ?>
				</span>
			</div>
			<div class="cprt-card-body">
				<div class="cprt-setting-row cprt-setting-row-stack">
					<div class="cprt-setting-info">
						<label for="cprt-ip-mode" class="cprt-setting-label">
							<?php esc_html_e( 'Visitor IP Address Handling', 'cp-referrer-and-conversions-tracking' ); ?>
						</label>
						<p class="cprt-setting-desc">
							<?php esc_html_e( 'Choose how visitor IP addresses are stored in tracking logs. Salted hashing keeps accurate visitor attribution without storing personal IP data.', 'cp-referrer-and-conversions-tracking' ); ?>
						</p>
					</div>
					<select id="cprt-ip-mode" name="cp_cpreftrack_ip_mode" class="cprt-select">
						<option value="hash" <?php selected( $ip_mode, 'hash' ); ?>>
							<?php esc_html_e( 'Pseudonymize with Salted One-Way Hash (Recommended for GDPR)', 'cp-referrer-and-conversions-tracking' ); ?>
						</option>
						<option value="anonymize" <?php selected( $ip_mode, 'anonymize' ); ?>>
							<?php esc_html_e( 'Mask / Anonymize IP (WordPress Core Subnet Masking)', 'cp-referrer-and-conversions-tracking' ); ?>
						</option>
						<option value="full" <?php selected( $ip_mode, 'full' ); ?>>
							<?php esc_html_e( 'Store Full IP Address (Legacy Mode)', 'cp-referrer-and-conversions-tracking' ); ?>
						</option>
					</select>
				</div>

				<div class="cprt-setting-row">
					<div class="cprt-setting-info">
						<label for="cprt-respect-dnt" class="cprt-setting-label">
							<?php esc_html_e( 'Honor Do Not Track (DNT) & Global Privacy Control (GPC)', 'cp-referrer-and-conversions-tracking' ); ?>
						</label>
						<p class="cprt-setting-desc">
							<?php esc_html_e( 'Skip referrer logging and cookies for visitors whose browser sends DNT: 1 or Sec-GPC: 1 headers.', 'cp-referrer-and-conversions-tracking' ); ?>
						</p>
					</div>
					<label class="cprt-switch">
						<input type="checkbox" id="cprt-respect-dnt" name="cp_cpreftrack_respect_dnt" value="1" <?php checked( $respect_dnt ); ?> />
						<span class="cprt-switch-slider"></span>
					</label>
				</div>

				<div class="cprt-setting-row">
					<div class="cprt-setting-info">
						<label for="cprt-cookieless" class="cprt-setting-label">
							<?php esc_html_e( 'Cookieless Tracking Mode', 'cp-referrer-and-conversions-tracking' ); ?>
						</label>
						<p class="cprt-setting-desc">
							<?php esc_html_e( 'Prevent setting cprreftrack and cprreftracklatest cookies. First-touch referrers remain tracked server-side; last-touch cookie tracking is disabled.', 'cp-referrer-and-conversions-tracking' ); ?>
						</p>
					</div>
					<label class="cprt-switch">
						<input type="checkbox" id="cprt-cookieless" name="cp_cpreftrack_cookieless" value="1" <?php checked( $cookieless ); ?> />
						<span class="cprt-switch-slider"></span>
					</label>
				</div>
			</div>
		</section>
	</div>

	<div class="cprt-form-footer" style="border-top:0;margin-top:0;padding-top:0;">
		<button type="submit" class="cprt-btn cprt-btn-primary">
			<?php esc_html_e( 'Save All Settings', 'cp-referrer-and-conversions-tracking' ); ?>
		</button>
	</div>
</form>

<section class="cprt-card">
	<div class="cprt-card-header">
		<h2><?php esc_html_e( 'Database Maintenance & WordPress Privacy Tools', 'cp-referrer-and-conversions-tracking' ); ?></h2>
	</div>
	<div class="cprt-card-body">
		<div class="cprt-grid cprt-grid-2">
			<dl class="cprt-meta-list">
				<div>
					<dt><?php esc_html_e( 'Scheduled Daily Cleanup', 'cp-referrer-and-conversions-tracking' ); ?></dt>
					<dd>
						<?php
						if ( $next_cleanup_ts ) {
							echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_cleanup_ts ) );
						} else {
							esc_html_e( 'Not scheduled (will schedule on next activation)', 'cp-referrer-and-conversions-tracking' );
						}
						?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Active IP Storage Mode', 'cp-referrer-and-conversions-tracking' ); ?></dt>
					<dd><?php echo esc_html( isset( $ip_mode_labels[ $ip_mode ] ) ? $ip_mode_labels[ $ip_mode ] : $ip_mode ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Cookie Policy', 'cp-referrer-and-conversions-tracking' ); ?></dt>
					<dd>
						<?php
						echo $cookieless
							? esc_html__( 'Cookieless mode active (no cookies set)', 'cp-referrer-and-conversions-tracking' )
							: esc_html__( 'HttpOnly, SameSite=Lax, Secure on HTTPS', 'cp-referrer-and-conversions-tracking' );
						?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Database Schema Version', 'cp-referrer-and-conversions-tracking' ); ?></dt>
					<dd><code class="cprt-mono-pill"><?php echo esc_html( get_option( 'cpreftrack_db_version', CPREFTRACK_DB_VERSION ) ); ?></code></dd>
				</div>
			</dl>

			<div>
				<p class="cprt-text-muted cprt-text-sm" style="margin-top:0;">
					<?php esc_html_e( 'This plugin integrates with WordPress Privacy Tools: suggested disclosure text is automatically added to the Privacy Policy Guide, and matching conversion records are included in WordPress Personal Data Export and Erasure requests.', 'cp-referrer-and-conversions-tracking' ); ?>
				</p>
				<div class="cprt-btn-group" style="margin-bottom:14px;">
					<a href="<?php echo esc_url( admin_url( 'options-privacy.php?tab=policyguide' ) ); ?>" class="cprt-btn cprt-btn-secondary">
						<?php esc_html_e( 'Open Privacy Policy Guide', 'cp-referrer-and-conversions-tracking' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>" class="cprt-btn cprt-btn-ghost">
						<?php esc_html_e( 'Export / Erase Personal Data', 'cp-referrer-and-conversions-tracking' ); ?>
					</a>
				</div>

				<hr class="cprt-divider" />

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_settings' ) ); ?>" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
					<input type="hidden" name="cpreftrack_run_cleanup" value="1" />
					<span class="cprt-text-muted cprt-text-sm">
						<?php
						printf(
							/* translators: %d: retention days */
							esc_html__( 'Prune tracking logs older than %d days right now:', 'cp-referrer-and-conversions-tracking' ),
							$retention_days
						);
						?>
					</span>
					<button type="submit" class="cprt-btn cprt-btn-secondary">
						<?php esc_html_e( 'Prune Expired Logs Now', 'cp-referrer-and-conversions-tracking' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>
</section>

<?php
$this->render_admin_footer();

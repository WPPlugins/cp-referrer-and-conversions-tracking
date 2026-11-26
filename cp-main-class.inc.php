<?php
/**
 * Main plugin class for CP Referrer and Conversion Tracking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_REFTRACK_Plugin extends CP_REFTRACK_BaseClass {

	public $menu_parameter        = 'cp_reftrack';
	public $prefix                = 'cp_reftrack';
	public $plugin_name           = 'CP Referrer Tracking';
	protected $componentid        = 160;
	public $plugin_URL            = 'https://wordpress.dwbooster.com/';
	public $table_items           = 'cpreftrack_registeredrefs';
	public $table_messages        = 'cpreftrack_logs';
	public $table_conversions     = 'cpreftrack_conversions';
	public $no_ref_indicator      = 'cprefna37193';
	public $cookie_tracking_days  = 1;
	public $include_user_data_csv = false;
	protected $runtime_latest_ref = '';
	protected $runtime_latest_utm = array();

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'cp-referrer-and-conversions-tracking',
			false,
			dirname( plugin_basename( CPREFTRACK_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Run database migrations when plugin DB version changes.
	 */
	public function maybe_upgrade_db() {
		if ( get_option( 'cpreftrack_db_version', '' ) !== CPREFTRACK_DB_VERSION ) {
			$this->_install();
		}
	}

	/**
	 * Create or upgrade plugin database tables, default privacy/UTM options, and daily cleanup cron.
	 */
	public function _install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$logs_table = $wpdb->prefix . $this->table_messages;
		$sql_logs   = "CREATE TABLE {$logs_table} (
			id VARCHAR(45) NOT NULL DEFAULT '',
			time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			referrer VARCHAR(250) NOT NULL DEFAULT '',
			data mediumtext,
			PRIMARY KEY  (id),
			KEY idx_time (time),
			KEY idx_referrer (referrer(191))
		) {$charset_collate};";
		dbDelta( $sql_logs );

		$items_table = $wpdb->prefix . $this->table_items;
		$sql_items   = "CREATE TABLE {$items_table} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			paramname VARCHAR(250) NOT NULL DEFAULT '',
			paramvalue VARCHAR(250) NOT NULL DEFAULT '',
			refname VARCHAR(250) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY idx_refname (refname(191))
		) {$charset_collate};";
		dbDelta( $sql_items );

		$conv_table = $wpdb->prefix . $this->table_conversions;
		$sql_conv   = "CREATE TABLE {$conv_table} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			convname VARCHAR(250) NOT NULL DEFAULT '',
			convdesc mediumtext,
			referrer VARCHAR(250) NOT NULL DEFAULT '',
			entry VARCHAR(250) NOT NULL DEFAULT '',
			referrerlast VARCHAR(250) NOT NULL DEFAULT '',
			utm_data mediumtext,
			PRIMARY KEY  (id),
			KEY idx_time (time),
			KEY idx_convname (convname(191))
		) {$charset_collate};";
		dbDelta( $sql_conv );

		// Feature & privacy defaults (only set if not already configured).
		add_option( 'cp_cpreftrack_utm_enable', 'yes' );
		add_option( 'cp_cpreftrack_dashboard_widget', 'yes' );
		add_option( 'cp_cpreftrack_ip_mode', 'hash' );
		add_option( 'cp_cpreftrack_respect_dnt', 'no' );
		add_option( 'cp_cpreftrack_cookieless', 'no' );
		add_option( 'cp_cpreftrack_delete_on_uninstall', 'no' );

		if ( ! wp_next_scheduled( 'cpreftrack_del_old_hook_fmin' ) ) {
			wp_schedule_event( time(), 'cp_every_once_day', 'cpreftrack_del_old_hook_fmin' );
		}

		update_option( 'cpreftrack_db_version', CPREFTRACK_DB_VERSION );
	}

	/**
	 * Deactivation cleanup: remove scheduled cron hooks.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'cpreftrack_del_old_hook_fmin' );
	}

	/**
	 * Add quick links on the WordPress Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function plugin_page_links( $links ) {
		$custom_link   = '<a href="' . esc_url( 'https://wordpress.dwbooster.com/contact-us' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Request custom changes', 'cp-referrer-and-conversions-tracking' ) . '</a>';
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter ) ) . '">' . esc_html__( 'Settings', 'cp-referrer-and-conversions-tracking' ) . '</a>';
		$help_link     = '<a href="' . esc_url( $this->get_help_url() ) . '">' . esc_html__( 'Help & Guide', 'cp-referrer-and-conversions-tracking' ) . '</a>';

		array_unshift( $links, $help_link, $settings_link, $custom_link );
		return $links;
	}

	/**
	 * Register WordPress admin menus with consistent capability enforcement.
	 */
	public function admin_menu() {
		$cap = $this->get_capability();

		add_options_page(
			$this->plugin_name . ' ' . __( 'Options', 'cp-referrer-and-conversions-tracking' ),
			$this->plugin_name,
			$cap,
			$this->menu_parameter,
			array( $this, 'settings_page' )
		);

		add_menu_page(
			$this->plugin_name,
			$this->plugin_name,
			$cap,
			$this->menu_parameter,
			array( $this, 'settings_page' ),
			'dashicons-chart-line',
			58
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Dashboard', 'cp-referrer-and-conversions-tracking' ),
			__( 'Dashboard', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter,
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Tracking Logs', 'cp-referrer-and-conversions-tracking' ),
			__( 'Tracking Logs', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_list',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Tracking Stats', 'cp-referrer-and-conversions-tracking' ),
			__( 'Tracking Stats', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_report',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Referral Sources', 'cp-referrer-and-conversions-tracking' ),
			__( 'Referral Sources', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_parameters',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Conversions', 'cp-referrer-and-conversions-tracking' ),
			__( 'Conversions', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_conversions',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Add-Ons', 'cp-referrer-and-conversions-tracking' ),
			__( 'Add-Ons', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_addons',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'General Settings', 'cp-referrer-and-conversions-tracking' ),
			__( 'General Settings', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_settings',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Help & Guide', 'cp-referrer-and-conversions-tracking' ),
			__( 'Help & Guide', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_help',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			$this->menu_parameter,
			__( 'Support & Docs', 'cp-referrer-and-conversions-tracking' ),
			__( 'Support & Docs', 'cp-referrer-and-conversions-tracking' ),
			$cap,
			$this->menu_parameter . '_support',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Route admin screen rendering safely.
	 */
	public function settings_page() {
		$this->require_manage_access();

		global $wpdb;
		$page = $this->get_get_param( 'page' );

		if ( $page === $this->menu_parameter . '_conversions' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-admin-int-conversions-list.inc.php';
		} elseif ( $page === $this->menu_parameter . '_parameters' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-admin-int-parameters-list.inc.php';
		} elseif ( $page === $this->menu_parameter . '_list' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-admin-int-message-list.inc.php';
		} elseif ( $page === $this->menu_parameter . '_report' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-admin-int-report.inc.php';
		} elseif ( $page === $this->menu_parameter . '_settings' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-settings.inc.php';
		} elseif ( $page === $this->menu_parameter . '_addons' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-addons.inc.php';
		} elseif ( $page === $this->menu_parameter . '_help' ) {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-help.inc.php';
		} elseif ( $page === $this->menu_parameter . '_support' ) {
			$this->render_support_page();
		} else {
			require_once CPREFTRACK_PLUGIN_DIR . 'cp-admin-int-list.inc.php';
		}
	}

	/**
	 * Render inline Support & Developer Documentation page.
	 */
	protected function render_support_page() {
		$this->render_admin_header(
			'support',
			__( 'Support & Developer Reference', 'cp-referrer-and-conversions-tracking' ),
			__( 'Documentation, custom conversion hooks, UTM filters, and support channels.', 'cp-referrer-and-conversions-tracking' )
		);
		?>
		<div class="cprt-grid cprt-grid-2">
			<section class="cprt-card">
				<div class="cprt-card-header">
					<h2><?php esc_html_e( 'Get Help & Request Customizations', 'cp-referrer-and-conversions-tracking' ); ?></h2>
				</div>
				<div class="cprt-card-body">
					<p class="cprt-text-muted">
						<?php esc_html_e( 'Need assistance configuring referrer or UTM tracking, integrating another form or e-commerce plugin, or requesting custom features?', 'cp-referrer-and-conversions-tracking' ); ?>
					</p>
					<div class="cprt-btn-group">
						<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/cp-referrer-and-conversions-tracking/' ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-btn cprt-btn-primary">
							<?php esc_html_e( 'WordPress.org Support Forum', 'cp-referrer-and-conversions-tracking' ); ?>
						</a>
						<a href="<?php echo esc_url( 'https://wordpress.dwbooster.com/contact-us' ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-btn cprt-btn-secondary">
							<?php esc_html_e( 'Request Custom Modifications', 'cp-referrer-and-conversions-tracking' ); ?>
						</a>
						<a href="<?php echo esc_url( $this->get_help_url() ); ?>" class="cprt-btn cprt-btn-secondary">
							<?php esc_html_e( 'Open Built-in User Guide', 'cp-referrer-and-conversions-tracking' ); ?>
						</a>
						<a href="<?php echo esc_url( $this->plugin_URL ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-btn cprt-btn-ghost">
							<?php esc_html_e( 'Online Documentation', 'cp-referrer-and-conversions-tracking' ); ?>
						</a>
					</div>
				</div>
			</section>

			<section class="cprt-card">
				<div class="cprt-card-header">
					<h2><?php esc_html_e( 'Developer Hooks', 'cp-referrer-and-conversions-tracking' ); ?></h2>
				</div>
				<div class="cprt-card-body">
					<p class="cprt-label"><?php esc_html_e( 'Register a custom conversion:', 'cp-referrer-and-conversions-tracking' ); ?></p>
					<pre class="cprt-code"><code>do_action( 'cpreftrack_register_conversion', 'my_custom_conversion', 'Order #1234' );</code></pre>

					<p class="cprt-label"><?php esc_html_e( 'Retrieve the current visitor referrer:', 'cp-referrer-and-conversions-tracking' ); ?></p>
					<pre class="cprt-code"><code>$referrer = apply_filters( 'cpreftrack_referrer', '' );</code></pre>

					<p class="cprt-label"><?php esc_html_e( 'Retrieve the current visitor UTM parameters (associative array):', 'cp-referrer-and-conversions-tracking' ); ?></p>
					<pre class="cprt-code"><code>$utm = apply_filters( 'cpreftrack_utm_data', array() );</code></pre>

					<p class="cprt-label"><?php esc_html_e( 'Retrieve full attribution snapshot (referrer, last referrer, entry page, first/last UTM):', 'cp-referrer-and-conversions-tracking' ); ?></p>
					<pre class="cprt-code"><code>$attribution = apply_filters( 'cpreftrack_attribution_data', array() );</code></pre>

					<p class="cprt-label"><?php esc_html_e( 'Customize conversion list display:', 'cp-referrer-and-conversions-tracking' ); ?></p>
					<pre class="cprt-code"><code>add_filter( 'my_custom_conversion', function( $text ) {
    return esc_html( $text );
} );</code></pre>
				</div>
			</section>
		</div>
		<?php
		$this->render_admin_footer();
	}

	/**
	 * Enqueue cleanly scoped modern admin CSS and JS only on plugin pages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function insert_adminScripts( $hook ) {
		if ( 'index.php' === $hook && $this->user_can_manage() && $this->is_dashboard_widget_enabled() ) {
			wp_enqueue_style(
				'cprt-dashboard-widget',
				CPREFTRACK_PLUGIN_URL . 'css/dashboard-widget.css',
				array(),
				CPREFTRACK_VERSION
			);
			return;
		}

		$page = $this->get_get_param( 'page' );
		if ( 0 !== strpos( $page, $this->menu_parameter ) ) {
			return;
		}

		wp_enqueue_style(
			'cprt-admin-modern',
			CPREFTRACK_PLUGIN_URL . 'css/admin-modern.css',
			array(),
			CPREFTRACK_VERSION
		);

		wp_enqueue_script(
			'cprt-admin-modern',
			CPREFTRACK_PLUGIN_URL . 'js/admin-modern.js',
			array(),
			CPREFTRACK_VERSION,
			true
		);

		wp_localize_script(
			'cprt-admin-modern',
			'cprtAdminL10n',
			array(
				'confirmDeleteOne'    => __( 'Are you sure you want to delete this item?', 'cp-referrer-and-conversions-tracking' ),
				'confirmDeleteMarked' => __( 'Are you sure you want to delete the selected items?', 'cp-referrer-and-conversions-tracking' ),
				'confirmDeleteAll'    => __( 'Are you sure you want to permanently delete ALL items in this table? This cannot be undone.', 'cp-referrer-and-conversions-tracking' ),
				'noItemsSelected'     => __( 'Please select at least one item first.', 'cp-referrer-and-conversions-tracking' ),
				'copied'              => __( 'Copied!', 'cp-referrer-and-conversions-tracking' ),
				'copyLink'            => __( 'Copy Link', 'cp-referrer-and-conversions-tracking' ),
				'logsLabel'           => __( 'logs', 'cp-referrer-and-conversions-tracking' ),
				'hourLabel'           => __( 'Hour', 'cp-referrer-and-conversions-tracking' ),
				'noChartData'         => __( 'No data recorded for the selected range.', 'cp-referrer-and-conversions-tracking' ),
				'utmMissingRequired'  => __( 'Enter at least a UTM Source to build a campaign URL.', 'cp-referrer-and-conversions-tracking' ),
			)
		);
	}

	/**
	 * Handle admin POST mutations and CSV exports before headers are sent (PRG pattern).
	 */
	public function handle_admin_actions() {
		if ( ! $this->user_can_manage() ) {
			return;
		}

		// 1. CSV Export (requires nonce + capability).
		if ( '' !== $this->get_param( $this->prefix . '_csv' ) ) {
			$nonce = $this->get_param( '_cprt_csv_nonce' );
			$this->verify_nonce( $nonce, 'cpreftrack_export_csv' );
			$this->export_csv();
			return;
		}

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return;
		}

		// 2. General & Privacy Settings Save.
		if ( isset( $_POST['CP_REFTRACK_post_edition'] ) ) {
			$this->save_edition();
			return;
		}

		// 3. Manual Log Cleanup Trigger.
		if ( isset( $_POST['cpreftrack_run_cleanup'] ) ) {
			$this->verify_nonce( $this->get_post_param( 'nonce' ), 'cpreftrack_actions_admin' );
			$deleted = $this->delete_old_logs();
			$this->redirect_with_notice( $this->menu_parameter . '_settings', 'cleanup_done', $deleted );
		}

		// 4. Add-Ons Save.
		if ( isset( $_POST['cpreftrack_save_addons'] ) ) {
			$this->handle_addons_save();
			return;
		}

		// 5. Tracking Logs CRUD actions.
		if ( isset( $_POST['cpreftrack_logs_action'] ) ) {
			$this->handle_logs_post();
			return;
		}

		// 6. Conversions CRUD actions.
		if ( isset( $_POST['cpreftrack_conversions_action'] ) ) {
			$this->handle_conversions_post();
			return;
		}

		// 7. Referral Sources CRUD actions.
		if ( isset( $_POST['cpreftrack_params_action'] ) ) {
			$this->handle_parameters_post();
			return;
		}
	}

	/**
	 * Redirect to an admin page with a flash notice code (Post/Redirect/Get).
	 *
	 * @param string $page_slug Target plugin page slug.
	 * @param string $notice    Notice code.
	 * @param int    $count     Optional item count.
	 */
	protected function redirect_with_notice( $page_slug, $notice, $count = 0 ) {
		$args = array(
			'page'        => $page_slug,
			'cprt_notice' => $notice,
		);
		if ( $count > 0 ) {
			$args['cprt_count'] = absint( $count );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save General, UTM & GDPR/Privacy Settings securely.
	 */
	public function save_edition() {
		$this->require_manage_access();
		$this->verify_nonce( $this->get_post_param( 'nonce' ), 'cpreftrack_actions_admin' );

		// Preserve backward-compatible option values: '' = enabled, 'no' = disabled.
		$enabled_toggle = isset( $_POST['cp_cpreftrack_rep_enable_toggle'] ) ? 'yes' : 'no';
		$rep_enable     = ( 'yes' === $enabled_toggle ) ? '' : 'no';

		$utm_enable       = isset( $_POST['cp_cpreftrack_utm_enable'] ) ? 'yes' : 'no';
		$dashboard_widget = isset( $_POST['cp_cpreftrack_dashboard_widget'] ) ? 'yes' : 'no';

		$days = absint( $this->get_post_param( 'cp_cpreftrack_rep_days', '90' ) );
		if ( $days < 1 ) {
			$days = 1;
		} elseif ( $days > 3650 ) {
			$days = 3650;
		}

		$raw_ip_mode   = $this->get_post_param( 'cp_cpreftrack_ip_mode', 'hash' );
		$allowed_modes = array( 'hash', 'anonymize', 'full' );
		$ip_mode       = in_array( $raw_ip_mode, $allowed_modes, true ) ? $raw_ip_mode : 'hash';

		$respect_dnt         = isset( $_POST['cp_cpreftrack_respect_dnt'] ) ? 'yes' : 'no';
		$cookieless          = isset( $_POST['cp_cpreftrack_cookieless'] ) ? 'yes' : 'no';
		$delete_on_uninstall = isset( $_POST['cp_cpreftrack_delete_on_uninstall'] ) ? 'yes' : 'no';

		update_option( 'cp_cpreftrack_rep_enable', $rep_enable );
		update_option( 'cp_cpreftrack_utm_enable', $utm_enable );
		update_option( 'cp_cpreftrack_dashboard_widget', $dashboard_widget );
		update_option( 'cp_cpreftrack_rep_days', (string) $days );
		update_option( 'cp_cpreftrack_ip_mode', $ip_mode );
		update_option( 'cp_cpreftrack_respect_dnt', $respect_dnt );
		update_option( 'cp_cpreftrack_cookieless', $cookieless );
		update_option( 'cp_cpreftrack_delete_on_uninstall', $delete_on_uninstall );

		$this->redirect_with_notice( $this->menu_parameter . '_settings', 'settings_saved' );
	}

	/**
	 * Save active add-ons list via POST.
	 */
	protected function handle_addons_save() {
		global $cpreftrack_addons_objs_list;

		$this->verify_nonce( $this->get_post_param( 'anonce' ), 'cpreftrack_actions_addons' );

		$submitted = isset( $_POST['cpreftrack_addons_active_list'] ) && is_array( $_POST['cpreftrack_addons_active_list'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['cpreftrack_addons_active_list'] ) )
			: array();

		$valid_ids = is_array( $cpreftrack_addons_objs_list ) ? array_keys( $cpreftrack_addons_objs_list ) : array();
		if ( ! empty( $valid_ids ) ) {
			$submitted = array_values( array_intersect( $submitted, $valid_ids ) );
		}

		update_option( 'cpreftrack_addons_active_list', $submitted );

		if ( ! empty( $valid_ids ) ) {
			$initialized = get_option( 'cpreftrack_addons_initialized', array() );
			$initialized = is_array( $initialized ) ? $initialized : array();
			$initialized = array_values( array_unique( array_merge( $initialized, $valid_ids ) ) );
			update_option( 'cpreftrack_addons_initialized', $initialized );
		}

		$this->redirect_with_notice( $this->menu_parameter . '_addons', 'addons_saved' );
	}

	/**
	 * Process POST delete actions on Tracking Logs.
	 */
	protected function handle_logs_post() {
		global $wpdb;

		$this->verify_nonce( $this->get_post_param( 'anonce' ), 'cpreftrack_actions_logs' );
		$action = $this->get_post_param( 'cpreftrack_logs_action' );
		$table  = $wpdb->prefix . $this->table_messages;

		if ( 'delete_one' === $action ) {
			$id = $this->get_post_param( 'item_id' );
			if ( '' !== $id ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id = %s", $id ) );
			}
			$this->redirect_with_notice( $this->menu_parameter . '_list', 'item_deleted' );
		} elseif ( 'delete_marked' === $action ) {
			$ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_ids'] ) )
				: array();
			$ids = array_filter( $ids, 'strlen' );
			if ( empty( $ids ) ) {
				$this->redirect_with_notice( $this->menu_parameter . '_list', 'nothing_selected' );
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
			$this->redirect_with_notice( $this->menu_parameter . '_list', 'marked_deleted', max( 1, $deleted ) );
		} elseif ( 'delete_all' === $action ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM `{$table}`" );
			$this->redirect_with_notice( $this->menu_parameter . '_list', 'all_deleted' );
		}
	}

	/**
	 * Process POST delete actions on Conversions.
	 */
	protected function handle_conversions_post() {
		global $wpdb;

		$this->verify_nonce( $this->get_post_param( 'anonce' ), 'cpreftrack_actions_conversions' );
		$action = $this->get_post_param( 'cpreftrack_conversions_action' );
		$table  = $wpdb->prefix . $this->table_conversions;

		if ( 'delete_one' === $action ) {
			$id = absint( $this->get_post_param( 'item_id' ) );
			if ( $id > 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id = %d", $id ) );
			}
			$this->redirect_with_notice( $this->menu_parameter . '_conversions', 'item_deleted' );
		} elseif ( 'delete_marked' === $action ) {
			$ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] )
				? array_filter( array_map( 'absint', wp_unslash( $_POST['selected_ids'] ) ) )
				: array();
			if ( empty( $ids ) ) {
				$this->redirect_with_notice( $this->menu_parameter . '_conversions', 'nothing_selected' );
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
			$this->redirect_with_notice( $this->menu_parameter . '_conversions', 'marked_deleted', max( 1, $deleted ) );
		} elseif ( 'delete_all' === $action ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM `{$table}`" );
			$this->redirect_with_notice( $this->menu_parameter . '_conversions', 'all_deleted' );
		}
	}

	/**
	 * Process POST add & delete actions on Referral Sources.
	 */
	protected function handle_parameters_post() {
		global $wpdb;

		$this->verify_nonce( $this->get_post_param( 'anonce' ), 'cpreftrack_actions_params' );
		$action = $this->get_post_param( 'cpreftrack_params_action' );
		$table  = $wpdb->prefix . $this->table_items;

		if ( 'add' === $action ) {
			$refname    = trim( $this->get_post_param( 'refname' ) );
			$paramname  = trim( $this->get_post_param( 'paramname' ) );
			$paramvalue = trim( $this->get_post_param( 'paramvalue' ) );

			if ( '' === $refname || '' === $paramname || '' === $paramvalue ) {
				$this->redirect_with_notice( $this->menu_parameter . '_parameters', 'item_invalid' );
			}

			$wpdb->insert(
				$table,
				array(
					'paramname'  => substr( $paramname, 0, 250 ),
					'paramvalue' => substr( $paramvalue, 0, 250 ),
					'refname'    => substr( $refname, 0, 250 ),
				),
				array( '%s', '%s', '%s' )
			);
			$this->redirect_with_notice( $this->menu_parameter . '_parameters', 'item_added' );
		} elseif ( 'delete_one' === $action ) {
			$id = absint( $this->get_post_param( 'item_id' ) );
			if ( $id > 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id = %d", $id ) );
			}
			$this->redirect_with_notice( $this->menu_parameter . '_parameters', 'item_deleted' );
		} elseif ( 'delete_marked' === $action ) {
			$ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] )
				? array_filter( array_map( 'absint', wp_unslash( $_POST['selected_ids'] ) ) )
				: array();
			if ( empty( $ids ) ) {
				$this->redirect_with_notice( $this->menu_parameter . '_parameters', 'nothing_selected' );
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
			$this->redirect_with_notice( $this->menu_parameter . '_parameters', 'marked_deleted', max( 1, $deleted ) );
		} elseif ( 'delete_all' === $action ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM `{$table}`" );
			$this->redirect_with_notice( $this->menu_parameter . '_parameters', 'all_deleted' );
		}
	}

	/**
	 * Check whether native UTM parameter tracking is enabled.
	 *
	 * @return bool
	 */
	public function is_utm_tracking_enabled() {
		return ( 'no' !== get_option( 'cp_cpreftrack_utm_enable', 'yes' ) );
	}

	/**
	 * Check whether the WordPress Dashboard overview widget is enabled.
	 *
	 * @return bool
	 */
	public function is_dashboard_widget_enabled() {
		return ( 'no' !== get_option( 'cp_cpreftrack_dashboard_widget', 'yes' ) );
	}

	/**
	 * Register the WordPress Dashboard overview widget for authorized administrators.
	 */
	public function register_dashboard_widget() {
		if ( ! $this->user_can_manage() || ! $this->is_dashboard_widget_enabled() ) {
			return;
		}

		wp_add_dashboard_widget(
			'cprt_dashboard_overview',
			__( 'CP Referrer & Conversion Overview', 'cp-referrer-and-conversions-tracking' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the WordPress Dashboard overview widget (last 7 days summary, sparkline, top referrers, recent conversions).
	 */
	public function render_dashboard_widget() {
		if ( ! $this->user_can_manage() ) {
			return;
		}

		global $wpdb;

		$logs_table = $wpdb->prefix . $this->table_messages;
		$conv_table = $wpdb->prefix . $this->table_conversions;

		$now_ts         = current_time( 'timestamp' );
		$start_ts       = strtotime( '-6 days', $now_ts );
		$since_7d       = wp_date( 'Y-m-d 00:00:00', $start_ts );
		$tracking_on    = ( '' === get_option( 'cp_cpreftrack_rep_enable', '' ) );

		// 1. Daily referral counts for the last 7 calendar days (uses idx_time).
		$daily_rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT DATE(`time`) AS day_key, COUNT(*) AS total FROM `{$logs_table}` WHERE `time` >= %s GROUP BY day_key ORDER BY day_key ASC",
				$since_7d
			)
		);

		$daily_map = array();
		$logs_7d   = 0;
		if ( is_array( $daily_rows ) ) {
			foreach ( $daily_rows as $r ) {
				$cnt = (int) $r->total;
				$daily_map[ (string) $r->day_key ] = $cnt;
				$logs_7d                          += $cnt;
			}
		}

		$spark_days = array();
		$spark_max  = 1;
		for ( $i = 6; $i >= 0; $i-- ) {
			$day_ts       = strtotime( '-' . $i . ' days', $now_ts );
			$day_key      = wp_date( 'Y-m-d', $day_ts );
			$day_short    = wp_date( 'D', $day_ts );
			$day_count    = isset( $daily_map[ $day_key ] ) ? $daily_map[ $day_key ] : 0;
			if ( $day_count > $spark_max ) {
				$spark_max = $day_count;
			}
			$spark_days[] = array(
				'date'  => $day_key,
				'short' => $day_short,
				'count' => $day_count,
			);
		}

		// 2. Conversions count in last 7 days (uses idx_time).
		$conversions_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM `{$conv_table}` WHERE `time` >= %s",
				$since_7d
			)
		);

		// 3. Top 5 referrers over the last 7 days.
		$top_referrers = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT referrer, COUNT(*) AS total FROM `{$logs_table}` WHERE `time` >= %s GROUP BY referrer ORDER BY total DESC LIMIT 5",
				$since_7d
			)
		);

		// 4. Latest 5 conversions.
		$recent_conversions = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT id, time, convname, convdesc, referrer, utm_data FROM `{$conv_table}` ORDER BY `time` DESC LIMIT 5"
		);

		$conv_rate = ( $logs_7d > 0 && $conversions_7d > 0 )
			? round( ( $conversions_7d / $logs_7d ) * 100, 1 )
			: 0;
		?>
		<div class="cprt-dash-widget">
			<div class="cprt-dw-topbar">
				<span class="cprt-dw-range"><?php esc_html_e( 'Last 7 Days', 'cp-referrer-and-conversions-tracking' ); ?></span>
				<span class="cprt-dw-status <?php echo $tracking_on ? 'is-active' : 'is-paused'; ?>">
					<span class="cprt-dw-dot" aria-hidden="true"></span>
					<?php echo $tracking_on ? esc_html__( 'Tracking Active', 'cp-referrer-and-conversions-tracking' ) : esc_html__( 'Tracking Paused', 'cp-referrer-and-conversions-tracking' ); ?>
				</span>
			</div>

			<div class="cprt-dw-kpis">
				<div class="cprt-dw-kpi">
					<span class="cprt-dw-kpi-label"><?php esc_html_e( 'Referrals (7d)', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<strong class="cprt-dw-kpi-value cprt-dw-blue"><?php echo esc_html( number_format_i18n( $logs_7d ) ); ?></strong>
				</div>
				<div class="cprt-dw-kpi">
					<span class="cprt-dw-kpi-label"><?php esc_html_e( 'Conversions (7d)', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<div class="cprt-dw-kpi-row">
						<strong class="cprt-dw-kpi-value cprt-dw-emerald"><?php echo esc_html( number_format_i18n( $conversions_7d ) ); ?></strong>
						<?php if ( $conv_rate > 0 ) : ?>
							<span class="cprt-dw-rate" title="<?php esc_attr_e( '7-day conversions relative to tracked referrals', 'cp-referrer-and-conversions-tracking' ); ?>">
								<?php echo esc_html( $conv_rate . '%' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="cprt-dw-section">
				<div class="cprt-dw-section-head">
					<h4><?php esc_html_e( '7-Day Referral Activity', 'cp-referrer-and-conversions-tracking' ); ?></h4>
				</div>
				<div class="cprt-dw-sparkline" role="img" aria-label="<?php esc_attr_e( 'Daily referral counts over the last 7 days', 'cp-referrer-and-conversions-tracking' ); ?>">
					<svg viewBox="0 0 280 64" width="100%" height="64" preserveAspectRatio="none" aria-hidden="true">
						<line x1="0" y1="62" x2="280" y2="62" stroke="#e2e8f0" stroke-width="1" />
						<?php
						$slot_w = 40;
						$bar_w  = 22;
						$max_h  = 50;
						foreach ( $spark_days as $idx => $day ) :
							$bar_h = ( $day['count'] > 0 ) ? max( 4, (int) round( ( $day['count'] / $spark_max ) * $max_h ) ) : 2;
							$bar_x = ( $idx * $slot_w ) + ( ( $slot_w - $bar_w ) / 2 );
							$bar_y = 61 - $bar_h;
							$fill  = ( $day['count'] > 0 ) ? ( ( 6 === $idx ) ? '#2563eb' : '#60a5fa' ) : '#e2e8f0';
							$tip   = sprintf(
								/* translators: 1: date YYYY-MM-DD, 2: referral count */
								__( '%1$s: %2$s referrals', 'cp-referrer-and-conversions-tracking' ),
								$day['date'],
								number_format_i18n( $day['count'] )
							);
							?>
							<rect x="<?php echo esc_attr( $bar_x ); ?>" y="<?php echo esc_attr( $bar_y ); ?>" width="<?php echo esc_attr( $bar_w ); ?>" height="<?php echo esc_attr( $bar_h ); ?>" rx="3" fill="<?php echo esc_attr( $fill ); ?>">
								<title><?php echo esc_html( $tip ); ?></title>
							</rect>
						<?php endforeach; ?>
					</svg>
					<div class="cprt-dw-spark-labels">
						<?php foreach ( $spark_days as $day ) : ?>
							<span title="<?php echo esc_attr( $day['date'] . ': ' . $day['count'] ); ?>">
								<?php echo esc_html( $day['short'] ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="cprt-dw-section">
				<div class="cprt-dw-section-head">
					<h4><?php esc_html_e( 'Top Referrers (7d)', 'cp-referrer-and-conversions-tracking' ); ?></h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_report' ) ); ?>" class="cprt-dw-more">
						<?php esc_html_e( 'Full Report', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
					</a>
				</div>
				<?php if ( empty( $top_referrers ) ) : ?>
					<p class="cprt-dw-empty"><?php esc_html_e( 'No incoming referrals recorded in the last 7 days.', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<?php else : ?>
					<ul class="cprt-dw-bars">
						<?php
						$top_max = max( 1, (int) $top_referrers[0]->total );
						foreach ( $top_referrers as $ref_row ) :
							$ref_raw   = (string) $ref_row->referrer;
							$ref_cnt   = (int) $ref_row->total;
							$bar_pct   = max( 4, min( 100, (int) round( ( $ref_cnt / $top_max ) * 100 ) ) );
							$ref_label = $ref_raw;
							if ( preg_match( '#^https?://#i', $ref_raw ) ) {
								$parsed_host = wp_parse_url( $ref_raw, PHP_URL_HOST );
								if ( is_string( $parsed_host ) && '' !== $parsed_host ) {
									$parsed_path = wp_parse_url( $ref_raw, PHP_URL_PATH );
									$ref_label   = $parsed_host . ( ( is_string( $parsed_path ) && '/' !== $parsed_path && '' !== $parsed_path ) ? $parsed_path : '' );
								}
							}
							if ( strlen( $ref_label ) > 42 ) {
								$ref_label = substr( $ref_label, 0, 39 ) . '...';
							}
							?>
							<li>
								<div class="cprt-dw-bar-meta">
									<span class="cprt-dw-bar-name" title="<?php echo esc_attr( $ref_raw ); ?>">
										<?php echo esc_html( '' !== $ref_label ? $ref_label : __( '(unknown)', 'cp-referrer-and-conversions-tracking' ) ); ?>
									</span>
									<strong class="cprt-dw-bar-count"><?php echo esc_html( number_format_i18n( $ref_cnt ) ); ?></strong>
								</div>
								<div class="cprt-dw-bar-track">
									<div class="cprt-dw-bar-fill" style="width:<?php echo esc_attr( $bar_pct ); ?>%;"></div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="cprt-dw-section">
				<div class="cprt-dw-section-head">
					<h4><?php esc_html_e( 'Recent Conversions', 'cp-referrer-and-conversions-tracking' ); ?></h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_conversions' ) ); ?>" class="cprt-dw-more">
						<?php esc_html_e( 'View All', 'cp-referrer-and-conversions-tracking' ); ?> &rarr;
					</a>
				</div>
				<?php if ( empty( $recent_conversions ) ) : ?>
					<p class="cprt-dw-empty"><?php esc_html_e( 'No conversions recorded yet. Enable an integration in Add-Ons to track leads and orders.', 'cp-referrer-and-conversions-tracking' ); ?></p>
				<?php else : ?>
					<ul class="cprt-dw-conv-list">
						<?php
						foreach ( $recent_conversions as $conv_row ) :
							$conv_ref  = trim( (string) $conv_row->referrer );
							$conv_utm  = $this->decode_conversion_utm( isset( $conv_row->utm_data ) ? $conv_row->utm_data : '' );
							$utm_src   = isset( $conv_utm['first']['utm_source'] ) ? $conv_utm['first']['utm_source'] : '';
							$utm_cmp   = isset( $conv_utm['first']['utm_campaign'] ) ? $conv_utm['first']['utm_campaign'] : '';
							$src_label = '';

							if ( '' !== $utm_src ) {
								$src_label = 'UTM: ' . $utm_src . ( '' !== $utm_cmp ? ' / ' . $utm_cmp : '' );
							} elseif ( '' !== $conv_ref ) {
								$parsed_host = preg_match( '#^https?://#i', $conv_ref ) ? wp_parse_url( $conv_ref, PHP_URL_HOST ) : '';
								$src_label   = ( is_string( $parsed_host ) && '' !== $parsed_host ) ? $parsed_host : $conv_ref;
							} else {
								$src_label = __( 'Direct / Unknown', 'cp-referrer-and-conversions-tracking' );
							}

							if ( strlen( $src_label ) > 36 ) {
								$src_label = substr( $src_label, 0, 33 ) . '...';
							}

							$conv_ts  = strtotime( (string) $conv_row->time );
							$time_ago = ( false !== $conv_ts )
								/* translators: %s: human-readable time difference */
								? sprintf( __( '%s ago', 'cp-referrer-and-conversions-tracking' ), human_time_diff( $conv_ts, $now_ts ) )
								: (string) $conv_row->time;
							?>
							<li>
								<div class="cprt-dw-conv-main">
									<span class="cprt-dw-conv-badge"><?php echo esc_html( (string) $conv_row->convname ); ?></span>
									<span class="cprt-dw-conv-source" title="<?php echo esc_attr( '' !== $conv_ref ? $conv_ref : $src_label ); ?>">
										<?php echo esc_html( $src_label ); ?>
									</span>
								</div>
								<span class="cprt-dw-conv-time" title="<?php echo esc_attr( (string) $conv_row->time ); ?>">
									<?php echo esc_html( $time_ago ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="cprt-dw-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter ) ); ?>"><?php esc_html_e( 'Dashboard', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<span aria-hidden="true">&middot;</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_list' ) ); ?>"><?php esc_html_e( 'Logs', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<span aria-hidden="true">&middot;</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_parameters' ) ); ?>"><?php esc_html_e( 'UTM Builder', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<span aria-hidden="true">&middot;</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_settings' ) ); ?>"><?php esc_html_e( 'Settings', 'cp-referrer-and-conversions-tracking' ); ?></a>
				<span aria-hidden="true">&middot;</span>
				<a href="<?php echo esc_url( $this->get_help_url( 'help-dashboard-widget' ) ); ?>"><?php esc_html_e( 'Help', 'cp-referrer-and-conversions-tracking' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Return the active IP storage mode: 'hash', 'anonymize', or 'full'.
	 *
	 * @return string
	 */
	public function get_ip_storage_mode() {
		$mode = (string) get_option( 'cp_cpreftrack_ip_mode', 'hash' );
		return in_array( $mode, array( 'hash', 'anonymize', 'full' ), true ) ? $mode : 'hash';
	}

	/**
	 * Check whether cookieless tracking mode is enabled.
	 *
	 * @return bool
	 */
	public function is_cookieless_mode() {
		return ( 'yes' === get_option( 'cp_cpreftrack_cookieless', 'no' ) );
	}

	/**
	 * Check whether browser Do Not Track (DNT) or Global Privacy Control (GPC) should suppress tracking.
	 *
	 * @return bool
	 */
	public function is_do_not_track_active() {
		if ( 'yes' !== get_option( 'cp_cpreftrack_respect_dnt', 'no' ) ) {
			return false;
		}

		$dnt = isset( $_SERVER['HTTP_DNT'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_DNT'] ) ) : '';
		$gpc = isset( $_SERVER['HTTP_SEC_GPC'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_SEC_GPC'] ) ) : '';

		return ( '1' === $dnt || '1' === $gpc );
	}

	/**
	 * Safely set a tracking cookie with modern security attributes unless cookieless mode is active.
	 *
	 * @param string $name    Cookie name.
	 * @param string $value   Cookie value.
	 * @param int    $max_len Maximum value length.
	 */
	protected function set_tracking_cookie( $name, $value, $max_len = 250 ) {
		if ( $this->is_cookieless_mode() || headers_sent() ) {
			return;
		}

		$clean_value = substr( sanitize_text_field( (string) $value ), 0, absint( $max_len ) );
		$expires     = time() + ( $this->cookie_tracking_days * DAY_IN_SECONDS );
		$path        = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';

		setcookie(
			$name,
			$clean_value,
			array(
				'expires'  => $expires,
				'path'     => $path,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Safely store a sanitized UTM parameter array in a first-party cookie.
	 *
	 * @param string $name       Cookie name ('cprreftrackutm' or 'cprreftrackutmlatest').
	 * @param array  $utm_params Associative array of UTM parameters.
	 */
	protected function set_utm_cookie( $name, $utm_params ) {
		if ( $this->is_cookieless_mode() || headers_sent() ) {
			return;
		}

		$clean = $this->sanitize_utm_params( $utm_params );
		if ( empty( $clean ) ) {
			return;
		}

		$encoded = wp_json_encode( $clean );
		if ( ! is_string( $encoded ) || '' === $encoded || strlen( $encoded ) > 1000 ) {
			return;
		}

		$expires = time() + ( $this->cookie_tracking_days * DAY_IN_SECONDS );
		$path    = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';

		setcookie(
			$name,
			$encoded,
			array(
				'expires'  => $expires,
				'path'     => $path,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Read and sanitize a JSON-encoded UTM cookie.
	 *
	 * @param string $name Cookie name.
	 * @return array
	 */
	protected function get_utm_cookie( $name ) {
		if ( $this->is_cookieless_mode() || ! isset( $_COOKIE[ $name ] ) || is_array( $_COOKIE[ $name ] ) ) {
			return array();
		}

		$raw     = (string) wp_unslash( $_COOKIE[ $name ] );
		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return array();
		}

		return $this->sanitize_utm_params( $decoded );
	}

	/**
	 * Extract and sanitize standard UTM parameters from the current GET query string.
	 *
	 * @return array
	 */
	public function extract_utm_params_from_request() {
		if ( empty( $_GET ) || ! is_array( $_GET ) ) {
			return array();
		}

		$raw = array();
		foreach ( $this->get_utm_keys() as $key ) {
			if ( isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ) {
				$raw[ $key ] = wp_unslash( $_GET[ $key ] );
			}
		}

		return $this->sanitize_utm_params( $raw );
	}

	/**
	 * Build a readable referrer label when a visit arrives with UTM parameters but no external HTTP referer.
	 *
	 * @param array $utm_params Sanitized UTM parameter array.
	 * @return string
	 */
	public function format_utm_referrer_label( $utm_params ) {
		$clean = $this->sanitize_utm_params( $utm_params );
		if ( empty( $clean ) ) {
			return '';
		}

		$segments = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $primary_key ) {
			if ( isset( $clean[ $primary_key ] ) ) {
				$segments[] = $clean[ $primary_key ];
			}
		}

		if ( empty( $segments ) ) {
			$segments = array_values( $clean );
		}

		$label = 'UTM: ' . implode( ' / ', $segments );
		$label = (string) apply_filters( 'cpreftrack_utm_referrer_label', $label, $clean );

		return substr( sanitize_text_field( $label ), 0, 250 );
	}

	/**
	 * Match custom referral rules configured under Referral Sources against current GET parameters.
	 *
	 * @return string Matched custom referral name or empty string.
	 */
	protected function match_custom_campaign_param() {
		global $wpdb;

		if ( empty( $_GET ) ) {
			return '';
		}

		$items_table = $wpdb->prefix . $this->table_items;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events = $wpdb->get_results( "SELECT paramname, paramvalue, refname FROM `{$items_table}`" );
		if ( ! is_array( $events ) ) {
			return '';
		}

		foreach ( $events as $event ) {
			$pname = (string) $event->paramname;
			if ( '' !== $pname && isset( $_GET[ $pname ] ) && ! is_array( $_GET[ $pname ] ) ) {
				$pval = sanitize_text_field( wp_unslash( $_GET[ $pname ] ) );
				if ( $pval === (string) $event->paramvalue ) {
					return sanitize_text_field( (string) $event->refname );
				}
			}
		}

		return '';
	}

	/**
	 * Retrieve and validate the raw visitor IP address safely.
	 *
	 * @return string
	 */
	public function get_client_ip() {
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$valid  = filter_var( $raw_ip, FILTER_VALIDATE_IP );
		$ip     = $valid ? $valid : '0.0.0.0';

		return (string) apply_filters( 'cpreftrack_client_ip', $ip );
	}

	/**
	 * Transform a visitor IP into the storage identifier according to GDPR/privacy settings.
	 *
	 * @param string $raw_ip Optional explicit IP or already-hashed identifier.
	 * @return string
	 */
	public function get_visitor_identifier( $raw_ip = '' ) {
		$raw_ip = '' !== $raw_ip ? sanitize_text_field( (string) $raw_ip ) : $this->get_client_ip();

		if ( 0 === strpos( $raw_ip, 'h_' ) ) {
			return substr( $raw_ip, 0, 45 );
		}

		$mode = $this->get_ip_storage_mode();

		if ( 'hash' === $mode ) {
			return 'h_' . substr( hash_hmac( 'sha256', $raw_ip, wp_salt( 'auth' ) ), 0, 32 );
		}

		if ( 'anonymize' === $mode && function_exists( 'wp_privacy_anonymize_ip' ) ) {
			$masked = wp_privacy_anonymize_ip( $raw_ip );
			if ( is_string( $masked ) && '' !== $masked ) {
				return substr( $masked, 0, 45 );
			}
		}

		return substr( $raw_ip, 0, 45 );
	}

	/**
	 * Normalize a URL for same-domain comparison.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	protected function normalize_url_for_compare( $url ) {
		$url = strtolower( trim( (string) $url ) );
		$url = str_replace( array( '//www.', 'https://', 'http://' ), array( '//', '', '' ), $url );
		return $url;
	}

	/**
	 * Frontend visitor referrer and native UTM tracking handler.
	 */
	public function data_management() {
		global $wpdb;

		cpreftrack_loading_add_ons();

		// Do not run visitor tracking on admin, AJAX, cron, or REST requests.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// Respect tracking disabled setting ('' = enabled, 'no' = disabled) and DNT/GPC headers.
		if ( '' !== get_option( 'cp_cpreftrack_rep_enable', '' ) || $this->is_do_not_track_active() ) {
			return;
		}

		$referrer_browser = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$entry_page       = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$local_domain = $this->normalize_url_for_compare( $this->get_site_url() );
		$compare_ref  = $this->normalize_url_for_compare( $referrer_browser );
		$is_external  = ( '' !== $referrer_browser && substr( $compare_ref . '/', 0, strlen( $local_domain ) ) !== $local_domain );

		$utm_enabled = $this->is_utm_tracking_enabled();
		$utm_params  = $utm_enabled ? $this->extract_utm_params_from_request() : array();
		$has_utm     = ! empty( $utm_params );

		$cookie_ref = ( ! $this->is_cookieless_mode() && isset( $_COOKIE['cprreftrack'] ) )
			? sanitize_text_field( wp_unslash( $_COOKIE['cprreftrack'] ) )
			: '';

		// Returning visitor with an already established cookie.
		if (
			( '' !== $cookie_ref && 'na' !== $cookie_ref && $cookie_ref !== $this->no_ref_indicator ) ||
			( $cookie_ref === $this->no_ref_indicator && '' === $referrer_browser && empty( $_GET ) )
		) {
			if ( $cookie_ref !== $this->no_ref_indicator ) {
				$latest_ref = '';
				if ( ! empty( $_GET ) ) {
					$latest_ref = $this->match_custom_campaign_param();
				}
				if ( '' === $latest_ref && $is_external ) {
					$latest_ref = $referrer_browser;
				}
				if ( '' === $latest_ref && $has_utm ) {
					$latest_ref = $this->format_utm_referrer_label( $utm_params );
				}

				if ( '' !== $latest_ref && $latest_ref !== $cookie_ref ) {
					$this->runtime_latest_ref = substr( sanitize_text_field( $latest_ref ), 0, 250 );
					$this->set_tracking_cookie( 'cprreftracklatest', $latest_ref );
				}
				if ( $has_utm ) {
					$this->runtime_latest_utm = $utm_params;
					$this->set_utm_cookie( 'cprreftrackutmlatest', $utm_params );
				}
			}
			return;
		}

		// Determine referrer priority: 1) Custom rule, 2) External HTTP Referer, 3) Native UTM label.
		$referrer = $this->match_custom_campaign_param();

		if ( '' === $referrer ) {
			if ( $is_external ) {
				$referrer = $referrer_browser;
			} elseif ( $has_utm ) {
				$referrer = $this->format_utm_referrer_label( $utm_params );
			} else {
				if ( '' === $referrer_browser ) {
					$this->set_tracking_cookie( 'cprreftrack', $this->no_ref_indicator );
				}
				return;
			}
		}

		$visitor_id = $this->get_visitor_identifier();
		$logs_table = $wpdb->prefix . $this->table_messages;

		// Keep original first-touch referrer & UTM data if visitor is already logged.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT referrer, data FROM `{$logs_table}` WHERE id = %s LIMIT 1", $visitor_id ) );
		if ( $existing_row ) {
			$existing_ref = (string) $existing_row->referrer;
			$this->set_tracking_cookie( 'cprreftrack', $existing_ref );

			if ( '' !== $referrer && $referrer !== $existing_ref ) {
				$this->runtime_latest_ref = substr( sanitize_text_field( $referrer ), 0, 250 );
				$this->set_tracking_cookie( 'cprreftracklatest', $referrer );
			}

			$existing_data = $this->decode_log_data( $existing_row->data );
			$existing_utm  = isset( $existing_data['utm'] ) ? $this->sanitize_utm_params( $existing_data['utm'] ) : array();
			if ( ! empty( $existing_utm ) ) {
				$this->set_utm_cookie( 'cprreftrackutm', $existing_utm );
			}
			if ( $has_utm && $utm_params !== $existing_utm ) {
				$this->runtime_latest_utm = $utm_params;
				$this->set_utm_cookie( 'cprreftrackutmlatest', $utm_params );
			}
			return;
		}

		$clean_referrer = substr( sanitize_text_field( $referrer ), 0, 250 );
		$this->set_tracking_cookie( 'cprreftrack', $clean_referrer );
		if ( $has_utm ) {
			$this->set_utm_cookie( 'cprreftrackutm', $utm_params );
		}

		$log_payload = array(
			'entry' => substr( $entry_page, 0, 250 ),
		);
		if ( $has_utm ) {
			$log_payload['utm'] = $utm_params;
		}

		$wpdb->insert(
			$logs_table,
			array(
				'id'       => $visitor_id,
				'time'     => current_time( 'mysql' ),
				'referrer' => $clean_referrer,
				'data'     => wp_json_encode( $log_payload ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Filter callback returning the current visitor's recorded referrer.
	 *
	 * @param string $ref Default value.
	 * @return string
	 */
	public function referrer_filter( $ref = '' ) {
		return $this->get_referrer();
	}

	/**
	 * Filter callback returning the current visitor's recorded first-touch UTM parameters.
	 *
	 * @param array $utm Default value.
	 * @return array
	 */
	public function utm_data_filter( $utm = array() ) {
		return $this->get_utm_data();
	}

	/**
	 * Filter callback returning the current visitor's full attribution payload.
	 *
	 * @param array $attribution Default value.
	 * @return array
	 */
	public function attribution_data_filter( $attribution = array() ) {
		return $this->get_visitor_attribution();
	}

	/**
	 * Look up the most recent (last-touch) referrer from the current session.
	 *
	 * @return string
	 */
	public function get_latest_referrer() {
		if ( '' !== $this->runtime_latest_ref ) {
			return $this->runtime_latest_ref;
		}
		if ( ! $this->is_cookieless_mode() && isset( $_COOKIE['cprreftracklatest'] ) && ! is_array( $_COOKIE['cprreftracklatest'] ) ) {
			return substr( sanitize_text_field( wp_unslash( $_COOKIE['cprreftracklatest'] ) ), 0, 250 );
		}
		return '';
	}

	/**
	 * Retrieve the complete attribution snapshot for the current visitor.
	 *
	 * @param string $ip Optional explicit IP address or visitor ID.
	 * @return array{referrer: string, referrer_last: string, entry: string, utm_first: array, utm_last: array}
	 */
	public function get_visitor_attribution( $ip = '' ) {
		$first_ref = $this->get_referrer( $ip );
		$last_ref  = ( '' === $ip ) ? $this->get_latest_referrer() : '';
		if ( '' !== $last_ref && $last_ref === $first_ref ) {
			$last_ref = '';
		}

		$entry = $this->get_entry( $ip );
		if ( '' === $entry && '' === $ip && isset( $_SERVER['REQUEST_URI'] ) ) {
			$entry = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		$entry = substr( $entry, 0, 250 );

		$utm_first = array();
		$utm_last  = array();
		if ( $this->is_utm_tracking_enabled() ) {
			$utm_first = $this->get_utm_data( $ip );
			$raw_last  = ( '' === $ip ) ? $this->get_latest_utm_data() : array();
			if ( ! empty( $raw_last ) && $raw_last !== $utm_first ) {
				$utm_last = $raw_last;
			}
		}

		return array(
			'referrer'      => substr( sanitize_text_field( (string) $first_ref ), 0, 250 ),
			'referrer_last' => substr( sanitize_text_field( (string) $last_ref ), 0, 250 ),
			'entry'         => $entry,
			'utm_first'     => $utm_first,
			'utm_last'      => $utm_last,
		);
	}

	/**
	 * Look up the first-touch referrer for a visitor (respects configured privacy mode with raw fallback).
	 *
	 * @param string $ip     Optional IP address or visitor ID.
	 * @param string $cookie Unused legacy parameter.
	 * @return string
	 */
	public function get_referrer( $ip = '', $cookie = '' ) {
		global $wpdb;

		$raw_ip     = '' !== $ip ? sanitize_text_field( (string) $ip ) : $this->get_client_ip();
		$visitor_id = $this->get_visitor_identifier( $raw_ip );
		$table      = $wpdb->prefix . $this->table_messages;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$referrer = $wpdb->get_var( $wpdb->prepare( "SELECT referrer FROM `{$table}` WHERE id = %s LIMIT 1", $visitor_id ) );

		// Fallback to raw IP if site recently switched from full-IP mode to hashed/anonymized mode.
		if ( null === $referrer && $visitor_id !== $raw_ip ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$referrer = $wpdb->get_var( $wpdb->prepare( "SELECT referrer FROM `{$table}` WHERE id = %s LIMIT 1", $raw_ip ) );
		}

		return is_string( $referrer ) ? $referrer : '';
	}

	/**
	 * Look up the decoded log metadata array for a visitor.
	 *
	 * @param string $ip Optional IP address or visitor ID.
	 * @return array
	 */
	protected function get_visitor_log_data( $ip = '' ) {
		global $wpdb;

		$raw_ip     = '' !== $ip ? sanitize_text_field( (string) $ip ) : $this->get_client_ip();
		$visitor_id = $this->get_visitor_identifier( $raw_ip );
		$table      = $wpdb->prefix . $this->table_messages;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw_data = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM `{$table}` WHERE id = %s LIMIT 1", $visitor_id ) );

		if ( null === $raw_data && $visitor_id !== $raw_ip ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$raw_data = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM `{$table}` WHERE id = %s LIMIT 1", $raw_ip ) );
		}

		if ( ! is_string( $raw_data ) || '' === $raw_data ) {
			return array();
		}

		return $this->decode_log_data( $raw_data );
	}

	/**
	 * Look up the initial entry page URI for a visitor.
	 *
	 * @param string $ip     Optional IP address or visitor ID.
	 * @param string $cookie Unused legacy parameter.
	 * @return string
	 */
	public function get_entry( $ip = '', $cookie = '' ) {
		$data = $this->get_visitor_log_data( $ip );
		return isset( $data['entry'] ) ? (string) $data['entry'] : '';
	}

	/**
	 * Look up the first-touch UTM parameters for a visitor.
	 *
	 * @param string $ip Optional IP address or visitor ID.
	 * @return array
	 */
	public function get_utm_data( $ip = '' ) {
		$data = $this->get_visitor_log_data( $ip );
		if ( isset( $data['utm'] ) && is_array( $data['utm'] ) ) {
			$utm = $this->sanitize_utm_params( $data['utm'] );
			if ( ! empty( $utm ) ) {
				return $utm;
			}
		}

		if ( '' === $ip ) {
			return $this->get_utm_cookie( 'cprreftrackutm' );
		}

		return array();
	}

	/**
	 * Look up the most recent (last-touch) UTM parameters from the current session cookie.
	 *
	 * @return array
	 */
	public function get_latest_utm_data() {
		if ( ! empty( $this->runtime_latest_utm ) ) {
			return $this->runtime_latest_utm;
		}
		return $this->get_utm_cookie( 'cprreftrackutmlatest' );
	}

	/**
	 * Record a conversion event with first-touch/last-touch referrers and UTM attribution.
	 *
	 * @param string $conversion_name        Conversion type identifier.
	 * @param string $conversion_description Conversion summary or ID string.
	 * @param string $referrer               Optional explicit referrer override.
	 */
	public function register_conversion( $conversion_name, $conversion_description, $referrer = '' ) {
		global $wpdb;

		$clean_name = substr( sanitize_text_field( (string) $conversion_name ), 0, 250 );
		$clean_desc = wp_kses_post( (string) $conversion_description );
		$clean_ref  = '' !== $referrer ? substr( sanitize_text_field( (string) $referrer ), 0, 250 ) : $this->get_referrer();

		$latest_cookie = '' !== $this->runtime_latest_ref
			? $this->runtime_latest_ref
			: ( ( ! $this->is_cookieless_mode() && isset( $_COOKIE['cprreftracklatest'] ) )
				? sanitize_text_field( wp_unslash( $_COOKIE['cprreftracklatest'] ) )
				: '' );
		$referrer_last = ( '' !== $latest_cookie && $clean_ref !== $latest_cookie ) ? substr( $latest_cookie, 0, 250 ) : '';

		$entry = $this->get_entry();
		if ( '' === $entry && isset( $_SERVER['REQUEST_URI'] ) ) {
			$entry = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		$entry = substr( $entry, 0, 250 );

		$utm_json = '';
		if ( $this->is_utm_tracking_enabled() ) {
			$first_utm   = $this->get_utm_data();
			$last_utm    = $this->get_latest_utm_data();
			$utm_payload = array();

			if ( ! empty( $first_utm ) ) {
				$utm_payload['first'] = $first_utm;
			}
			if ( ! empty( $last_utm ) && $last_utm !== $first_utm ) {
				$utm_payload['last'] = $last_utm;
			}
			if ( ! empty( $utm_payload ) ) {
				$encoded  = wp_json_encode( $utm_payload );
				$utm_json = is_string( $encoded ) ? $encoded : '';
			}
		}

		$table = $wpdb->prefix . $this->table_conversions;
		$wpdb->insert(
			$table,
			array(
				'convname'     => $clean_name,
				'convdesc'     => $clean_desc,
				'referrer'     => $clean_ref,
				'referrerlast' => $referrer_last,
				'entry'        => $entry,
				'utm_data'     => $utm_json,
				'time'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Register suggested GDPR privacy policy text in WordPress Settings -> Privacy -> Policy Guide.
	 */
	public function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$days        = max( 1, absint( get_option( 'cp_cpreftrack_rep_days', 90 ) ) );
		$ip_mode     = $this->get_ip_storage_mode();
		$cookieless  = $this->is_cookieless_mode();
		$utm_enabled = $this->is_utm_tracking_enabled();

		if ( 'hash' === $ip_mode ) {
			$ip_text = __( 'Visitor IP addresses are converted into a one-way salted cryptographic hash before storage so raw IP addresses are not stored.', 'cp-referrer-and-conversions-tracking' );
		} elseif ( 'anonymize' === $ip_mode ) {
			$ip_text = __( 'Visitor IP addresses are anonymized (masked) before storage.', 'cp-referrer-and-conversions-tracking' );
		} else {
			$ip_text = __( 'Visitor IP addresses are stored alongside referral metadata.', 'cp-referrer-and-conversions-tracking' );
		}

		if ( $cookieless ) {
			$cookie_text = __( 'This site runs referrer tracking in cookieless mode and does not set referral or UTM tracking cookies.', 'cp-referrer-and-conversions-tracking' );
		} elseif ( $utm_enabled ) {
			$cookie_text = __( 'First-party cookies (cprreftrack, cprreftracklatest, cprreftrackutm, and cprreftrackutmlatest) may be set for 24 hours to preserve first-touch and last-touch referral and UTM campaign attribution.', 'cp-referrer-and-conversions-tracking' );
		} else {
			$cookie_text = __( 'Two first-party cookies (cprreftrack and cprreftracklatest) may be set for 24 hours to preserve first-touch and last-touch referral attribution.', 'cp-referrer-and-conversions-tracking' );
		}

		$content  = '<h3>' . esc_html__( 'Referrer and Conversion Tracking', 'cp-referrer-and-conversions-tracking' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'When you arrive on this website from an external link or marketing campaign URL (including standard UTM parameters such as utm_source, utm_medium, and utm_campaign), we record the referring website or campaign parameters, landing page path, and visit timestamp to measure marketing performance.', 'cp-referrer-and-conversions-tracking' ) . ' ' . esc_html( $ip_text ) . '</p>';
		$content .= '<p>' . esc_html( $cookie_text ) . ' ';
		$content .= sprintf(
			/* translators: %d: retention days */
			esc_html__( 'Referrer tracking logs are automatically deleted after %d days.', 'cp-referrer-and-conversions-tracking' ),
			$days
		);
		$content .= '</p>';

		wp_add_privacy_policy_content(
			__( 'CP Referrer and Conversion Tracking', 'cp-referrer-and-conversions-tracking' ),
			wp_kses_post( $content )
		);
	}

	/**
	 * Register WordPress GDPR Personal Data Exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_privacy_exporters( $exporters ) {
		$exporters['cp-referrer-and-conversions-tracking'] = array(
			'exporter_friendly_name' => __( 'CP Referrer and Conversion Tracking', 'cp-referrer-and-conversions-tracking' ),
			'callback'               => array( $this, 'privacy_personal_data_exporter' ),
		);
		return $exporters;
	}

	/**
	 * Export conversion records matching a data subject's email address or user ID.
	 *
	 * @param string $email_address Subject email address.
	 * @param int    $page          Pagination page (1-indexed).
	 * @return array
	 */
	public function privacy_personal_data_exporter( $email_address, $page = 1 ) {
		global $wpdb;

		$email_address = sanitize_email( (string) $email_address );
		$page          = max( 1, absint( $page ) );
		$per_page      = 50;
		$offset        = ( $page - 1 ) * $per_page;
		$export_items  = array();

		if ( '' === $email_address ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$table  = $wpdb->prefix . $this->table_conversions;
		$user   = get_user_by( 'email', $email_address );
		$like   = '%' . $wpdb->esc_like( $email_address ) . '%';
		$params = array( $like );
		$where  = 'convdesc LIKE %s';

		if ( $user && $user->ID > 0 ) {
			$where   .= ' OR (convname = %s AND convdesc = %s)';
			$params[] = 'New User Registration';
			$params[] = 'USER ID: ' . absint( $user->ID );
		}

		$params[] = $per_page;
		$params[] = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, time, convname, convdesc, referrer, referrerlast, entry, utm_data FROM `{$table}` WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d",
				$params
			)
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$utm_decoded = $this->decode_conversion_utm( isset( $row->utm_data ) ? $row->utm_data : '' );
				$item_data   = array(
					array(
						'name'  => __( 'Timestamp', 'cp-referrer-and-conversions-tracking' ),
						'value' => (string) $row->time,
					),
					array(
						'name'  => __( 'Conversion Type', 'cp-referrer-and-conversions-tracking' ),
						'value' => (string) $row->convname,
					),
					array(
						'name'  => __( 'Details', 'cp-referrer-and-conversions-tracking' ),
						'value' => wp_strip_all_tags( (string) $row->convdesc ),
					),
					array(
						'name'  => __( 'First Referrer', 'cp-referrer-and-conversions-tracking' ),
						'value' => (string) $row->referrer,
					),
					array(
						'name'  => __( 'Last Referrer', 'cp-referrer-and-conversions-tracking' ),
						'value' => (string) $row->referrerlast,
					),
					array(
						'name'  => __( 'Entry Page', 'cp-referrer-and-conversions-tracking' ),
						'value' => (string) $row->entry,
					),
				);

				if ( ! empty( $utm_decoded['first'] ) ) {
					$item_data[] = array(
						'name'  => __( 'First-Touch UTM', 'cp-referrer-and-conversions-tracking' ),
						'value' => $this->format_utm_plain_text( $utm_decoded['first'] ),
					);
				}
				if ( ! empty( $utm_decoded['last'] ) ) {
					$item_data[] = array(
						'name'  => __( 'Last-Touch UTM', 'cp-referrer-and-conversions-tracking' ),
						'value' => $this->format_utm_plain_text( $utm_decoded['last'] ),
					);
				}

				$export_items[] = array(
					'group_id'    => 'cp-reftrack-conversions',
					'group_label' => __( 'Conversion Attribution Records', 'cp-referrer-and-conversions-tracking' ),
					'item_id'     => 'cp-reftrack-conversion-' . absint( $row->id ),
					'data'        => $item_data,
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => count( $export_items ) < $per_page,
		);
	}

	/**
	 * Register WordPress GDPR Personal Data Eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_privacy_erasers( $erasers ) {
		$erasers['cp-referrer-and-conversions-tracking'] = array(
			'eraser_friendly_name' => __( 'CP Referrer and Conversion Tracking', 'cp-referrer-and-conversions-tracking' ),
			'callback'             => array( $this, 'privacy_personal_data_eraser' ),
		);
		return $erasers;
	}

	/**
	 * Erase conversion records matching a data subject's email address or user ID.
	 *
	 * @param string $email_address Subject email address.
	 * @param int    $page          Pagination page.
	 * @return array
	 */
	public function privacy_personal_data_eraser( $email_address, $page = 1 ) {
		global $wpdb;

		$email_address = sanitize_email( (string) $email_address );
		if ( '' === $email_address ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$table  = $wpdb->prefix . $this->table_conversions;
		$user   = get_user_by( 'email', $email_address );
		$like   = '%' . $wpdb->esc_like( $email_address ) . '%';
		$params = array( $like );
		$where  = 'convdesc LIKE %s';

		if ( $user && $user->ID > 0 ) {
			$where   .= ' OR (convname = %s AND convdesc = %s)';
			$params[] = 'New User Registration';
			$params[] = 'USER ID: ' . absint( $user->ID );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE {$where}", $params ) );

		return array(
			'items_removed'  => $deleted > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Prune tracking logs older than the configured retention period.
	 *
	 * @return int Number of deleted log rows.
	 */
	public function delete_old_logs() {
		global $wpdb;

		$days = absint( get_option( 'cp_cpreftrack_rep_days', 90 ) );
		if ( $days < 1 ) {
			$days = 90;
		}

		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$table  = $wpdb->prefix . $this->table_messages;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE time < %s", $cutoff ) );
		return ( false === $deleted ) ? 0 : (int) $deleted;
	}

	/**
	 * Build a prepared WHERE clause for log queries (shared by Logs, Stats, and CSV Export).
	 *
	 * @param string $search Search term.
	 * @param string $dfrom  Validated YYYY-MM-DD start date.
	 * @param string $dto    Validated YYYY-MM-DD end date.
	 * @return array{where: string, params: array}
	 */
	public function build_logs_where( $search = '', $dfrom = '', $dto = '' ) {
		global $wpdb;

		$clauses = array( '1 = 1' );
		$params  = array();

		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(id LIKE %s OR data LIKE %s OR referrer LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
		}

		if ( '' !== $dfrom ) {
			$clauses[] = '`time` >= %s';
			$params[]  = $dfrom . ' 00:00:00';
		}

		if ( '' !== $dto ) {
			$clauses[] = '`time` <= %s';
			$params[]  = $dto . ' 23:59:59';
		}

		return array(
			'where'  => implode( ' AND ', $clauses ),
			'params' => $params,
		);
	}

	/**
	 * Export filtered tracking logs as a UTF-8 CSV file with UTM columns and formula-injection protection.
	 */
	public function export_csv() {
		$this->require_manage_access();

		global $wpdb;

		$search = $this->get_param( 'search' );
		$dfrom  = $this->validate_date( $this->get_param( 'dfrom' ) );
		$dto    = $this->validate_date( $this->get_param( 'dto' ) );

		$table  = $wpdb->prefix . $this->table_messages;
		$filter = $this->build_logs_where( $search, $dfrom, $dto );

		$sql = "SELECT id, time, referrer, data FROM `{$table}` WHERE {$filter['where']} ORDER BY `time` DESC";
		if ( ! empty( $filter['params'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $filter['params'] );
		}

		$sql    = apply_filters( 'cpreftrack_csv_query', $sql );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$events = $wpdb->get_results( $sql );

		$filename = $this->generateSafeFileName( 'cp_referrer_logs_' . wp_date( 'Y_m_d' ) ) . '.csv';

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		// UTF-8 BOM for clean spreadsheet display.
		fwrite( $output, "\xEF\xBB\xBF" );

		$exclude_raw    = get_option( 'cp_cpreftrack_bocsvexclude', '' );
		$fields_exclude = array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $exclude_raw ) ) ) );

		$base_fields = array(
			'Visitor ID',
			'Time',
			'Referrer',
			'Entry Page',
			'UTM Source',
			'UTM Medium',
			'UTM Campaign',
			'UTM Term',
			'UTM Content',
		);
		$extra_keys  = array();
		$rows        = array();

		if ( is_array( $events ) ) {
			foreach ( $events as $item ) {
				$decoded = $this->decode_log_data( $item->data );
				$entry   = isset( $decoded['entry'] ) ? (string) $decoded['entry'] : '';
				$utm     = isset( $decoded['utm'] ) ? $this->sanitize_utm_params( $decoded['utm'] ) : array();
				unset( $decoded['entry'], $decoded['utm'], $decoded['apps'], $decoded['itemnumber'] );

				foreach ( $decoded as $k => $v ) {
					if ( ! in_array( strtolower( (string) $k ), $fields_exclude, true ) && ! in_array( $k, $extra_keys, true ) ) {
						$extra_keys[] = $k;
					}
				}

				$rows[] = array(
					'Visitor ID'   => (string) $item->id,
					'Time'         => (string) $item->time,
					'Referrer'     => (string) $item->referrer,
					'Entry Page'   => $entry,
					'UTM Source'   => isset( $utm['utm_source'] ) ? $utm['utm_source'] : '',
					'UTM Medium'   => isset( $utm['utm_medium'] ) ? $utm['utm_medium'] : '',
					'UTM Campaign' => isset( $utm['utm_campaign'] ) ? $utm['utm_campaign'] : '',
					'UTM Term'     => isset( $utm['utm_term'] ) ? $utm['utm_term'] : '',
					'UTM Content'  => isset( $utm['utm_content'] ) ? $utm['utm_content'] : '',
					'_extra'       => $decoded,
				);
			}
		}

		$columns = array();
		foreach ( $base_fields as $col ) {
			if ( ! in_array( strtolower( $col ), $fields_exclude, true ) ) {
				$columns[] = $col;
			}
		}
		foreach ( $extra_keys as $ek ) {
			$columns[] = $ek;
		}

		$header_row = array();
		foreach ( $columns as $col ) {
			$header_row[] = $this->clean_csv_value( $col );
		}
		fputcsv( $output, $header_row );

		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $col ) {
				if ( array_key_exists( $col, $row ) ) {
					$val = $row[ $col ];
				} elseif ( isset( $row['_extra'][ $col ] ) ) {
					$val = $row['_extra'][ $col ];
				} else {
					$val = '';
				}

				if ( is_array( $val ) ) {
					$val = implode( ',', array_map( 'strval', $val ) );
				}

				$line[] = $this->clean_csv_value( (string) $val );
			}
			fputcsv( $output, $line );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Mitigate CSV formula injection (Excel / Calc DDE and formula execution).
	 *
	 * @param string $value Raw cell value.
	 * @return string
	 */
	public function clean_csv_value( $value ) {
		$value = trim( (string) $value );
		if ( '' !== $value && preg_match( '/^[\=\+\-\@\t\r]/', $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	/**
	 * Generate a safe filename for downloads.
	 *
	 * @param string $filename Raw filename.
	 * @return string
	 */
	public function generateSafeFileName( $filename ) {
		$filename = strtolower( wp_strip_all_tags( (string) $filename ) );
		$filename = preg_replace( '/[^a-z0-9_\-]+/', '_', $filename );
		$filename = trim( (string) $filename, '_' );
		return sanitize_file_name( '' !== $filename ? $filename : 'export' );
	}

	/**
	 * Register daily custom cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function isa_add_cron_recurrence_interval( $schedules ) {
		if ( ! isset( $schedules['cp_every_once_day'] ) ) {
			$schedules['cp_every_once_day'] = array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Once a day', 'cp-referrer-and-conversions-tracking' ),
			);
		}
		return $schedules;
	}

	/**
	 * Set active item ID.
	 *
	 * @param int $id Item ID.
	 */
	public function setId( $id ) {
		$this->item = absint( $id );
	}

	/**
	 * Get active item ID.
	 *
	 * @return int
	 */
	public function getId() {
		return absint( $this->item );
	}
}

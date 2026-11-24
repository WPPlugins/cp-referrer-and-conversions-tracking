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
	 * Create or upgrade plugin database tables, default privacy options, and daily cleanup cron.
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
			PRIMARY KEY  (id),
			KEY idx_time (time),
			KEY idx_convname (convname(191))
		) {$charset_collate};";
		dbDelta( $sql_conv );

		// Privacy defaults (only set if not already configured).
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
		$help_link     = '<a href="' . esc_url( $this->plugin_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Help', 'cp-referrer-and-conversions-tracking' ) . '</a>';

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
			__( 'Documentation, custom conversion hooks, and support channels.', 'cp-referrer-and-conversions-tracking' )
		);
		?>
		<div class="cprt-grid cprt-grid-2">
			<section class="cprt-card">
				<div class="cprt-card-header">
					<h2><?php esc_html_e( 'Get Help & Request Customizations', 'cp-referrer-and-conversions-tracking' ); ?></h2>
				</div>
				<div class="cprt-card-body">
					<p class="cprt-text-muted">
						<?php esc_html_e( 'Need assistance configuring referrer tracking, integrating another form or e-commerce plugin, or requesting custom features?', 'cp-referrer-and-conversions-tracking' ); ?>
					</p>
					<div class="cprt-btn-group">
						<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/cp-referrer-and-conversions-tracking/' ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-btn cprt-btn-primary">
							<?php esc_html_e( 'WordPress.org Support Forum', 'cp-referrer-and-conversions-tracking' ); ?>
						</a>
						<a href="<?php echo esc_url( 'https://wordpress.dwbooster.com/contact-us' ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-btn cprt-btn-secondary">
							<?php esc_html_e( 'Request Custom Modifications', 'cp-referrer-and-conversions-tracking' ); ?>
						</a>
						<a href="<?php echo esc_url( $this->plugin_URL ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-btn cprt-btn-ghost">
							<?php esc_html_e( 'Plugin Documentation', 'cp-referrer-and-conversions-tracking' ); ?>
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
	 * Save General & GDPR/Privacy Settings securely.
	 */
	public function save_edition() {
		$this->require_manage_access();
		$this->verify_nonce( $this->get_post_param( 'nonce' ), 'cpreftrack_actions_admin' );

		// Preserve backward-compatible option values: '' = enabled, 'no' = disabled.
		$enabled_toggle = isset( $_POST['cp_cpreftrack_rep_enable_toggle'] ) ? 'yes' : 'no';
		$rep_enable     = ( 'yes' === $enabled_toggle ) ? '' : 'no';

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
	 * @param string $name  Cookie name.
	 * @param string $value Cookie value.
	 */
	protected function set_tracking_cookie( $name, $value ) {
		if ( $this->is_cookieless_mode() || headers_sent() ) {
			return;
		}

		$clean_value = substr( sanitize_text_field( (string) $value ), 0, 250 );
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
	 * Frontend visitor referrer tracking handler.
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

		$cookie_ref = ( ! $this->is_cookieless_mode() && isset( $_COOKIE['cprreftrack'] ) )
			? sanitize_text_field( wp_unslash( $_COOKIE['cprreftrack'] ) )
			: '';

		if (
			( '' !== $cookie_ref && 'na' !== $cookie_ref && $cookie_ref !== $this->no_ref_indicator ) ||
			( $cookie_ref === $this->no_ref_indicator && '' === $referrer_browser && empty( $_GET ) )
		) {
			if ( $is_external ) {
				$this->set_tracking_cookie( 'cprreftracklatest', $referrer_browser );
			}
			return;
		}

		$referrer = '';

		// Match custom campaign query parameters first.
		if ( ! empty( $_GET ) ) {
			$items_table = $wpdb->prefix . $this->table_items;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$events = $wpdb->get_results( "SELECT paramname, paramvalue, refname FROM `{$items_table}`" );
			if ( is_array( $events ) ) {
				foreach ( $events as $event ) {
					$pname = $event->paramname;
					if ( '' !== $pname && isset( $_GET[ $pname ] ) && ! is_array( $_GET[ $pname ] ) ) {
						$pval = sanitize_text_field( wp_unslash( $_GET[ $pname ] ) );
						if ( $pval === $event->paramvalue ) {
							$referrer = sanitize_text_field( $event->refname );
							break;
						}
					}
				}
			}
		}

		if ( '' === $referrer ) {
			if ( ! $is_external ) {
				if ( '' === $referrer_browser ) {
					$this->set_tracking_cookie( 'cprreftrack', $this->no_ref_indicator );
				}
				return;
			}
			$referrer = $referrer_browser;
		}

		$visitor_id = $this->get_visitor_identifier();
		$logs_table = $wpdb->prefix . $this->table_messages;

		// Keep original first-touch referrer if visitor is already logged.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing_ref = $wpdb->get_var( $wpdb->prepare( "SELECT referrer FROM `{$logs_table}` WHERE id = %s LIMIT 1", $visitor_id ) );
		if ( null !== $existing_ref ) {
			$this->set_tracking_cookie( 'cprreftrack', $existing_ref );
			if ( '' !== $referrer && $referrer !== $existing_ref ) {
				$this->set_tracking_cookie( 'cprreftracklatest', $referrer );
			}
			return;
		}

		$clean_referrer = substr( sanitize_text_field( $referrer ), 0, 250 );
		$this->set_tracking_cookie( 'cprreftrack', $clean_referrer );

		$wpdb->insert(
			$logs_table,
			array(
				'id'       => $visitor_id,
				'time'     => current_time( 'mysql' ),
				'referrer' => $clean_referrer,
				'data'     => wp_json_encode( array( 'entry' => substr( $entry_page, 0, 250 ) ) ),
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
	 * Look up the initial entry page URI for a visitor.
	 *
	 * @param string $ip     Optional IP address or visitor ID.
	 * @param string $cookie Unused legacy parameter.
	 * @return string
	 */
	public function get_entry( $ip = '', $cookie = '' ) {
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
			return '';
		}

		$data = $this->decode_log_data( $raw_data );
		return isset( $data['entry'] ) ? (string) $data['entry'] : '';
	}

	/**
	 * Record a conversion event with first-touch and last-touch referrers.
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

		$latest_cookie = ( ! $this->is_cookieless_mode() && isset( $_COOKIE['cprreftracklatest'] ) )
			? sanitize_text_field( wp_unslash( $_COOKIE['cprreftracklatest'] ) )
			: '';
		$referrer_last = ( '' !== $latest_cookie && $clean_ref !== $latest_cookie ) ? substr( $latest_cookie, 0, 250 ) : '';

		$entry = $this->get_entry();
		if ( '' === $entry && isset( $_SERVER['REQUEST_URI'] ) ) {
			$entry = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		$entry = substr( $entry, 0, 250 );

		$table = $wpdb->prefix . $this->table_conversions;
		$wpdb->insert(
			$table,
			array(
				'convname'     => $clean_name,
				'convdesc'     => $clean_desc,
				'referrer'     => $clean_ref,
				'referrerlast' => $referrer_last,
				'entry'        => $entry,
				'time'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Register suggested GDPR privacy policy text in WordPress Settings -> Privacy -> Policy Guide.
	 */
	public function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$days       = max( 1, absint( get_option( 'cp_cpreftrack_rep_days', 90 ) ) );
		$ip_mode    = $this->get_ip_storage_mode();
		$cookieless = $this->is_cookieless_mode();

		if ( 'hash' === $ip_mode ) {
			$ip_text = __( 'Visitor IP addresses are converted into a one-way salted cryptographic hash before storage so raw IP addresses are not stored.', 'cp-referrer-and-conversions-tracking' );
		} elseif ( 'anonymize' === $ip_mode ) {
			$ip_text = __( 'Visitor IP addresses are anonymized (masked) before storage.', 'cp-referrer-and-conversions-tracking' );
		} else {
			$ip_text = __( 'Visitor IP addresses are stored alongside referral metadata.', 'cp-referrer-and-conversions-tracking' );
		}

		if ( $cookieless ) {
			$cookie_text = __( 'This site runs referrer tracking in cookieless mode and does not set referral tracking cookies.', 'cp-referrer-and-conversions-tracking' );
		} else {
			$cookie_text = __( 'Two first-party cookies (cprreftrack and cprreftracklatest) may be set for 24 hours to preserve first-touch and last-touch referral attribution.', 'cp-referrer-and-conversions-tracking' );
		}

		$content  = '<h3>' . esc_html__( 'Referrer and Conversion Tracking', 'cp-referrer-and-conversions-tracking' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'When you arrive on this website from an external link or marketing campaign URL, we record the referring website or campaign identifier, landing page path, and visit timestamp to measure marketing performance.', 'cp-referrer-and-conversions-tracking' ) . ' ' . esc_html( $ip_text ) . '</p>';
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
				"SELECT id, time, convname, convdesc, referrer, referrerlast, entry FROM `{$table}` WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d",
				$params
			)
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$export_items[] = array(
					'group_id'    => 'cp-reftrack-conversions',
					'group_label' => __( 'Conversion Attribution Records', 'cp-referrer-and-conversions-tracking' ),
					'item_id'     => 'cp-reftrack-conversion-' . absint( $row->id ),
					'data'        => array(
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
					),
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
	 * Export filtered tracking logs as a UTF-8 CSV file with formula-injection protection.
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

		$base_fields = array( 'Visitor ID', 'Time', 'Referrer', 'Entry Page' );
		$extra_keys  = array();
		$rows        = array();

		if ( is_array( $events ) ) {
			foreach ( $events as $item ) {
				$decoded = $this->decode_log_data( $item->data );
				$entry   = isset( $decoded['entry'] ) ? (string) $decoded['entry'] : '';
				unset( $decoded['entry'], $decoded['apps'], $decoded['itemnumber'] );

				foreach ( $decoded as $k => $v ) {
					if ( ! in_array( strtolower( (string) $k ), $fields_exclude, true ) && ! in_array( $k, $extra_keys, true ) ) {
						$extra_keys[] = $k;
					}
				}

				$rows[] = array(
					'Visitor ID' => (string) $item->id,
					'Time'       => (string) $item->time,
					'Referrer'   => (string) $item->referrer,
					'Entry Page' => $entry,
					'_extra'     => $decoded,
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

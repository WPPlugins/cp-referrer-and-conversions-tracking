<?php
/**
 * Plugin Name: CP Referrer and Conversion Tracking
 * Plugin URI: https://wordpress.dwbooster.com/
 * Description: Track visitor referral sources, campaign links, analytics, and third-party plugin conversions with built-in GDPR privacy controls.
 * Version: 2.0.9
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: CodePeople
 * Author URI: https://wordpress.dwbooster.com/support
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cp-referrer-and-conversions-tracking
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CPREFTRACK_VERSION' ) ) {
	define( 'CPREFTRACK_VERSION', '2.0.0' );
}

if ( ! defined( 'CPREFTRACK_DB_VERSION' ) ) {
	define( 'CPREFTRACK_DB_VERSION', '2.0.0' );
}

if ( ! defined( 'CPREFTRACK_PLUGIN_FILE' ) ) {
	define( 'CPREFTRACK_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'CPREFTRACK_PLUGIN_DIR' ) ) {
	define( 'CPREFTRACK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CPREFTRACK_PLUGIN_URL' ) ) {
	define( 'CPREFTRACK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Global add-on registries (preserved for backward compatibility).
global $cpreftrack_addons_active_list, $cpreftrack_addons_objs_list;

$cpreftrack_addons_active_list = array();
$cpreftrack_addons_objs_list   = array();

/**
 * Load plugin add-ons safely and idempotently.
 */
function cpreftrack_loading_add_ons() {
	static $loaded = false;

	global $cpreftrack_addons_active_list, $cpreftrack_addons_objs_list;

	$active_option                 = get_option( 'cpreftrack_addons_active_list', array() );
	$cpreftrack_addons_active_list = is_array( $active_option ) ? $active_option : array();

	if ( $loaded ) {
		return;
	}

	$current_page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$is_plugin_admin_page = is_admin() && 0 === strpos( $current_page, 'cp_reftrack' );

	if ( empty( $cpreftrack_addons_active_list ) && ! $is_plugin_admin_page ) {
		return;
	}

	$loaded = true;
	$path   = CPREFTRACK_PLUGIN_DIR . 'addons';

	if ( ! is_dir( $path ) ) {
		return;
	}

	$base_file = $path . '/base.addon.php';
	if ( file_exists( $base_file ) ) {
		require_once $base_file;
	}

	$files = glob( $path . '/*.php' );
	if ( is_array( $files ) ) {
		sort( $files );
		foreach ( $files as $file ) {
			if ( basename( $file ) === 'base.addon.php' ) {
				continue;
			}
			require_once $file;
		}
	}
}

/* Initialization / Install */
require_once CPREFTRACK_PLUGIN_DIR . 'classes/cp-base-class.inc.php';
require_once CPREFTRACK_PLUGIN_DIR . 'cp-main-class.inc.php';

$cp_reftrack_plugin = new CP_REFTRACK_Plugin();

register_activation_hook( __FILE__, array( $cp_reftrack_plugin, 'install' ) );
register_deactivation_hook( __FILE__, array( $cp_reftrack_plugin, 'deactivate' ) );

add_action( 'plugins_loaded', array( $cp_reftrack_plugin, 'load_textdomain' ), 10 );
add_action( 'plugins_loaded', array( $cp_reftrack_plugin, 'maybe_upgrade_db' ), 15 );
add_action( 'plugins_loaded', 'cpreftrack_loading_add_ons', 20 );

add_action( 'init', array( $cp_reftrack_plugin, 'data_management' ) );
add_action( 'cpreftrack_register_conversion', array( $cp_reftrack_plugin, 'register_conversion' ), 10, 3 );
add_filter( 'cpreftrack_referrer', array( $cp_reftrack_plugin, 'referrer_filter' ), 10, 1 );

// Cron setup to prune old tracking logs.
add_filter( 'cron_schedules', array( $cp_reftrack_plugin, 'isa_add_cron_recurrence_interval' ) );
add_action( 'cpreftrack_del_old_hook_fmin', array( $cp_reftrack_plugin, 'delete_old_logs' ) );

// WordPress Core GDPR Personal Data Exporter & Eraser.
add_filter( 'wp_privacy_personal_data_exporters', array( $cp_reftrack_plugin, 'register_privacy_exporters' ) );
add_filter( 'wp_privacy_personal_data_erasers', array( $cp_reftrack_plugin, 'register_privacy_erasers' ) );

// Admin filters & actions.
if ( is_admin() ) {
	add_action( 'admin_init', array( $cp_reftrack_plugin, 'handle_admin_actions' ) );
	add_action( 'admin_init', array( $cp_reftrack_plugin, 'register_privacy_policy_content' ) );
	add_action( 'admin_enqueue_scripts', array( $cp_reftrack_plugin, 'insert_adminScripts' ), 10 );
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $cp_reftrack_plugin, 'plugin_page_links' ) );
	add_action( 'admin_menu', array( $cp_reftrack_plugin, 'admin_menu' ) );
}

// Review banner.
global $codepeople_promote_banner_plugins_cp_ref;
$codepeople_promote_banner_plugins_cp_ref['cp-referrer-and-conversions-tracking'] = array(
	'plugin_name' => 'CP Referrer and Conversion Tracking',
	'plugin_url'  => 'https://wordpress.org/support/plugin/cp-referrer-and-conversions-tracking/reviews/#new-post',
);
require_once CPREFTRACK_PLUGIN_DIR . 'banner.php';

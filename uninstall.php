<?php
/**
 * Uninstall handler for CP Referrer and Conversion Tracking.
 *
 * Executed automatically by WordPress when the plugin is deleted from the
 * Plugins screen. Scheduled events and review transients are always cleaned up.
 * Database tables and saved options are permanently removed when the
 * "Delete All Plugin Data on Uninstall" privacy setting is enabled (or when
 * CPREFTRACK_REMOVE_ALL_DATA is defined as true in wp-config.php).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'cpreftrack_uninstall_single_site' ) ) {
	/**
	 * Clean up plugin artifacts for the currently active site.
	 */
	function cpreftrack_uninstall_single_site() {
		global $wpdb;

		// Always unschedule recurring cron jobs and clear banner transients.
		wp_clear_scheduled_hook( 'cpreftrack_del_old_hook_fmin' );
		delete_transient( 'codepeople_promote_banner_cp-referrer-and-conversions-tracking' );

		$should_purge = (
			'yes' === get_option( 'cp_cpreftrack_delete_on_uninstall', 'no' ) ||
			( defined( 'CPREFTRACK_REMOVE_ALL_DATA' ) && true === CPREFTRACK_REMOVE_ALL_DATA )
		);

		if ( ! $should_purge ) {
			return;
		}

		// Drop custom plugin tables.
		$tables = array(
			$wpdb->prefix . 'cpreftrack_logs',
			$wpdb->prefix . 'cpreftrack_registeredrefs',
			$wpdb->prefix . 'cpreftrack_conversions',
		);

		foreach ( $tables as $table ) {
			if ( preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
			}
		}

		// Delete all plugin options.
		$options = array(
			'cp_cpreftrack_rep_enable',
			'cp_cpreftrack_utm_enable',
			'cp_cpreftrack_dashboard_widget',
			'cp_cpreftrack_rep_days',
			'cp_cpreftrack_ip_mode',
			'cp_cpreftrack_respect_dnt',
			'cp_cpreftrack_cookieless',
			'cp_cpreftrack_delete_on_uninstall',
			'cp_cpreftrack_bocsvexclude',
			'cpreftrack_addons_active_list',
			'cpreftrack_addons_initialized',
			'cpreftrack_db_version',
		);

		foreach ( $options as $option_name ) {
			delete_option( $option_name );
		}

		// Remove any legacy dynamic per-form options if present.
		$legacy_prefix = $wpdb->esc_like( 'cp_cpreftrack_last_sent_id_' ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$legacy_prefix
			)
		);
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		cpreftrack_uninstall_single_site();
		restore_current_blog();
	}
} else {
	cpreftrack_uninstall_single_site();
}

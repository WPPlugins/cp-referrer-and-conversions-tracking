<?php
/**
 * Base class for CP Referrer and Conversion Tracking add-ons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPREFTRACK_BaseAddon' ) ) {

	class CPREFTRACK_BaseAddon {

		/** @var string */
		protected $addonID = '';

		/** @var string */
		protected $name = '';

		/** @var string */
		protected $description = '';

		/** @var string */
		protected $conversion_submission_indicator = '';

		/** @var string */
		protected $conversion_details_prefix = '';

		public function get_addon_id() {
			return $this->addonID;
		}

		public function get_addon_name() {
			return $this->name;
		}

		public function get_addon_description() {
			return $this->description;
		}

		public function get_addon_form_settings( $form_id ) {
			return '';
		}

		public function get_addon_settings() {
			return '';
		}

		/**
		 * Return dependency status: 'active', 'inactive', 'not_installed', or 'core'.
		 *
		 * @return string
		 */
		public function get_status() {
			return 'core';
		}

		/**
		 * Check whether this add-on is enabled in the active add-ons list.
		 *
		 * @return bool
		 */
		public function addon_is_active() {
			global $cpreftrack_addons_active_list;
			if ( ! is_array( $cpreftrack_addons_active_list ) ) {
				return false;
			}
			return in_array( $this->get_addon_id(), $cpreftrack_addons_active_list, true );
		}

		/**
		 * Auto-enable an add-on at most once when its target plugin is detected.
		 * Prevents manually disabled add-ons from silently re-enabling on every page load.
		 *
		 * @param bool $target_is_active Whether the integrated third-party plugin is active.
		 */
		protected function maybe_auto_activate( $target_is_active ) {
			if ( ! $target_is_active || $this->addon_is_active() ) {
				return;
			}

			$initialized = get_option( 'cpreftrack_addons_initialized', array() );
			$initialized = is_array( $initialized ) ? $initialized : array();

			$addon_id = $this->get_addon_id();
			if ( in_array( $addon_id, $initialized, true ) ) {
				return;
			}

			global $cpreftrack_addons_active_list;
			if ( ! is_array( $cpreftrack_addons_active_list ) ) {
				$cpreftrack_addons_active_list = array();
			}

			$cpreftrack_addons_active_list[] = $addon_id;
			$cpreftrack_addons_active_list   = array_values( array_unique( $cpreftrack_addons_active_list ) );
			update_option( 'cpreftrack_addons_active_list', $cpreftrack_addons_active_list );

			$initialized[] = $addon_id;
			update_option( 'cpreftrack_addons_initialized', array_values( array_unique( $initialized ) ) );
		}

		/**
		 * Safely retrieve the latest referrer cookie value.
		 *
		 * @return string
		 */
		protected function get_latest_referrer_cookie() {
			if ( isset( $_COOKIE['cprreftracklatest'] ) && ! is_array( $_COOKIE['cprreftracklatest'] ) ) {
				return sanitize_text_field( wp_unslash( $_COOKIE['cprreftracklatest'] ) );
			}
			return '';
		}

		/**
		 * Build a safe WordPress plugin-information URL for add-on descriptions.
		 *
		 * @param string $slug  WordPress.org plugin slug.
		 * @param string $label Human-readable plugin name.
		 * @return string
		 */
		protected function build_plugin_info_link( $slug, $label ) {
			$url = add_query_arg(
				array(
					'tab'    => 'plugin-information',
					'plugin' => $slug,
				),
				admin_url( 'plugin-install.php' )
			);
			return sprintf(
				'<a href="%1$s" class="cprt-link"><strong>%2$s</strong></a>',
				esc_url( $url ),
				esc_html( $label )
			);
		}

		/**
		 * Check whether a companion plugin exists in wp-content/plugins.
		 *
		 * @param string $plugin_rel_path Relative path such as 'contact-form-7/wp-contact-form-7.php'.
		 * @return bool
		 */
		protected function is_plugin_installed( $plugin_rel_path ) {
			$plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( dirname( dirname( __FILE__ ) ) );
			return file_exists( trailingslashit( $plugins_dir ) . ltrim( $plugin_rel_path, '/' ) );
		}

		/**
		 * Render a clean, escaped dependency warning notice for the Dashboard when an enabled
		 * add-on's target plugin is missing or inactive.
		 *
		 * @param bool   $is_active       Whether target plugin is currently active.
		 * @param string $plugin_label    Display name of target plugin.
		 * @param string $plugin_slug     WordPress.org plugin slug.
		 * @param string $plugin_rel_path Relative plugin file path.
		 * @return string HTML markup (returned, never echoed).
		 */
		protected function render_dependency_notice( $is_active, $plugin_label, $plugin_slug, $plugin_rel_path ) {
			if ( $is_active ) {
				return '';
			}

			if ( ! $this->is_plugin_installed( $plugin_rel_path ) ) {
				$install_url = add_query_arg(
					array(
						'tab'    => 'plugin-information',
						'plugin' => $plugin_slug,
					),
					admin_url( 'plugin-install.php' )
				);

				return sprintf(
					'<div class="cprt-alert cprt-alert-warning"><div><strong>%1$s</strong> %2$s</div><a href="%3$s" class="cprt-btn cprt-btn-sm cprt-btn-secondary">%4$s</a></div>',
					esc_html__( 'Configuration issue:', 'cp-referrer-and-conversions-tracking' ),
					sprintf(
						/* translators: %s: add-on name */
						esc_html__( 'The %s add-on is enabled, but its companion plugin was not detected.', 'cp-referrer-and-conversions-tracking' ),
						'<strong>' . esc_html( $this->name ) . '</strong>'
					),
					esc_url( $install_url ),
					sprintf(
						/* translators: %s: plugin name */
						esc_html__( 'Install %s', 'cp-referrer-and-conversions-tracking' ),
						esc_html( $plugin_label )
					)
				);
			}

			return sprintf(
				'<div class="cprt-alert cprt-alert-warning"><div><strong>%1$s</strong> %2$s</div><a href="%3$s" class="cprt-btn cprt-btn-sm cprt-btn-secondary">%4$s</a></div>',
				esc_html__( 'Configuration issue:', 'cp-referrer-and-conversions-tracking' ),
				sprintf(
					/* translators: %s: add-on name */
					esc_html__( 'The %s add-on is enabled, but its companion plugin is currently inactive.', 'cp-referrer-and-conversions-tracking' ),
					'<strong>' . esc_html( $this->name ) . '</strong>'
				),
				esc_url( admin_url( 'plugins.php' ) ),
				esc_html__( 'Activate Plugin', 'cp-referrer-and-conversions-tracking' )
			);
		}
	}
}

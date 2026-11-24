<?php
/**
 * Contact Form to Email Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_CFTEForm' ) ) {

	class CPREFTRACK_CFTEForm extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-CFTEForm-20200119';
		protected $name                            = 'Contact Form to Email Conversion Tracking';
		protected $conversion_submission_indicator = 'Contact Form to Email - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				__( 'Enables referrer tracking for conversions in the %s plugin.', 'cp-referrer-and-conversions-tracking' ),
				$this->build_plugin_info_link( 'contact-form-to-email', 'Contact Form to Email' )
			);

			add_action( 'init', array( $this, 'check_active' ), 10 );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'cfte_process_data_before_insert', array( $this, 'register_referrer' ), 10, 1 );
			add_action( 'cfte_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			global $cp_cfte_plugin;
			if ( is_object( $cp_cfte_plugin ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'contact-form-to-email/form-to-email.php' ) ? 'inactive' : 'not_installed';
		}

		public function check_active() {
			global $cp_cfte_plugin;
			$this->maybe_auto_activate( is_object( $cp_cfte_plugin ) );
		}

		public function get_addon_settings() {
			global $cp_cfte_plugin;
			return $this->render_dependency_notice(
				is_object( $cp_cfte_plugin ),
				'Contact Form to Email',
				'contact-form-to-email',
				'contact-form-to-email/form-to-email.php'
			);
		}

		public function register_referrer( &$params ) {
			if ( ! is_array( $params ) ) {
				return;
			}
			$params['referrer_url']        = apply_filters( 'cpreftrack_referrer', '' );
			$params['referrer_url_latest'] = $this->get_latest_referrer_cookie();
		}

		public function track_conversion_submission( $params ) {
			$itemnumber = ( is_array( $params ) && isset( $params['itemnumber'] ) ) ? sanitize_text_field( (string) $params['itemnumber'] ) : '';
			do_action( 'cpreftrack_register_conversion', $this->conversion_submission_indicator, $this->conversion_details_prefix . $itemnumber );
		}

		public function filter_conversion_list( $param_registered ) {
			global $wpdb, $cp_cfte_plugin;
			if ( ! is_object( $cp_cfte_plugin ) || empty( $cp_cfte_plugin->table_messages ) ) {
				return esc_html( (string) $param_registered );
			}

			$inum   = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$table  = $wpdb->prefix . $cp_cfte_plugin->table_messages;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$myrows = $wpdb->get_results( $wpdb->prepare( "SELECT notifyto FROM `{$table}` WHERE id = %d LIMIT 1", $inum ) );

			if ( ! empty( $myrows ) ) {
				$email = sanitize_email( (string) $myrows[0]->notifyto );
				$url   = add_query_arg(
					array(
						'page'   => 'cp_contactformtoemail',
						'cal'    => '0',
						'list'   => '1',
						'search' => $email,
						'ds'     => 'Filter',
					),
					admin_url( 'admin.php' )
				);

				return sprintf(
					'<span class="cprt-nowrap">%1$s</span><br /><strong>%2$s</strong> <a href="%3$s" class="cprt-link">%4$s</a>',
					esc_html( (string) $param_registered ),
					esc_html__( 'Email:', 'cp-referrer-and-conversions-tracking' ),
					esc_url( $url ),
					esc_html( $email )
				);
			}

			return esc_html( (string) $param_registered );
		}
	}

	$CPREFTRACK_CFTEForm_obj = new CPREFTRACK_CFTEForm();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_CFTEForm_obj->get_addon_id() ] = $CPREFTRACK_CFTEForm_obj;
}

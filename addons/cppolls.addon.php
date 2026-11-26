<?php
/**
 * CP Polls Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_CPPollsForm' ) ) {

	class CPREFTRACK_CPPollsForm extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-CPPollsForm-20200119';
		protected $name                            = 'CP Polls Conversion Tracking';
		protected $conversion_submission_indicator = 'CP Polls - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				'Enables referrer tracking for conversions in the %s plugin.',
				$this->build_plugin_info_link( 'cp-polls', 'CP Polls' )
			);

			add_action( 'init', array( $this, 'check_active' ), 10 );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'cppolls_process_data_before_insert', array( $this, 'register_referrer' ), 10, 1 );
			add_action( 'cppolls_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			global $cp_pollsnv_plugin;
			if ( is_object( $cp_pollsnv_plugin ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'cp-polls/cp-polls.php' ) ? 'inactive' : 'not_installed';
		}

		public function check_active() {
			global $cp_pollsnv_plugin;
			$this->maybe_auto_activate( is_object( $cp_pollsnv_plugin ) );
		}

		public function get_addon_settings() {
			global $cp_pollsnv_plugin;
			return $this->render_dependency_notice(
				is_object( $cp_pollsnv_plugin ),
				'CP Polls',
				'cp-polls',
				'cp-polls/cp-polls.php'
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
			global $wpdb, $cp_pollsnv_plugin;
			if ( ! is_object( $cp_pollsnv_plugin ) || empty( $cp_pollsnv_plugin->table_messages ) ) {
				return esc_html( (string) $param_registered );
			}

			$inum   = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$table  = $wpdb->prefix . $cp_pollsnv_plugin->table_messages;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$myrows = $wpdb->get_results( $wpdb->prepare( "SELECT formid FROM `{$table}` WHERE id = %d LIMIT 1", $inum ) );

			if ( ! empty( $myrows ) ) {
				$url = add_query_arg(
					array(
						'page'   => 'CP_Polls',
						'cal'    => absint( $myrows[0]->formid ),
						'list'   => '1',
						'search' => $inum,
						'ds'     => 'Filter',
					),
					admin_url( 'admin.php' )
				);

				return sprintf(
					'<a href="%1$s" class="cprt-link cprt-nowrap">%2$s</a>',
					esc_url( $url ),
					esc_html( (string) $param_registered )
				);
			}

			return esc_html( (string) $param_registered );
		}
	}

	$CPPollsForm_obj = new CPREFTRACK_CPPollsForm();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPPollsForm_obj->get_addon_id() ] = $CPPollsForm_obj;
}

<?php
/**
 * Booking Calendar Contact Form Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_BCCF' ) ) {

	class CPREFTRACK_BCCF extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-BCCF-20200119';
		protected $name                            = 'Booking Calendar Contact Form Conversion Tracking';
		protected $conversion_submission_indicator = 'Booking Calendar Contact Form - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				'Enables referrer tracking for conversions in the %s plugin.',
				$this->build_plugin_info_link( 'booking-calendar-contact-form', 'Booking Calendar Contact Form' )
			);

			add_action( 'init', array( $this, 'check_active' ), 10 );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'dexbccf_process_data_before_insert', array( $this, 'register_referrer' ), 10, 1 );
			add_action( 'dexbccf_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			if ( defined( 'DEX_BCCF_CALENDARS_TABLE_NAME' ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'booking-calendar-contact-form/dex_bccf.php' ) ? 'inactive' : 'not_installed';
		}

		public function check_active() {
			$this->maybe_auto_activate( defined( 'DEX_BCCF_CALENDARS_TABLE_NAME' ) );
		}

		public function get_addon_settings() {
			return $this->render_dependency_notice(
				defined( 'DEX_BCCF_CALENDARS_TABLE_NAME' ),
				'Booking Calendar Contact Form',
				'booking-calendar-contact-form',
				'booking-calendar-contact-form/dex_bccf.php'
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
			global $wpdb;
			if ( ! defined( 'DEX_BCCF_CALENDARS_TABLE_NAME' ) ) {
				return esc_html( (string) $param_registered );
			}

			$inum   = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$table  = DEX_BCCF_CALENDARS_TABLE_NAME;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$myrows = $wpdb->get_results( $wpdb->prepare( "SELECT reservation_calendar_id, title FROM `{$table}` WHERE id = %d LIMIT 1", $inum ) );

			if ( ! empty( $myrows ) ) {
				$email = sanitize_email( (string) $myrows[0]->title );
				$url   = add_query_arg(
					array(
						'page'   => 'dex_bccf.php',
						'cal'    => absint( $myrows[0]->reservation_calendar_id ),
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

	$CPREFTRACK_BCCF_obj = new CPREFTRACK_BCCF();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_BCCF_obj->get_addon_id() ] = $CPREFTRACK_BCCF_obj;
}

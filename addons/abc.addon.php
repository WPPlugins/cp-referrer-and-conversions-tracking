<?php
/**
 * Appointment Booking Calendar Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_AppointmentBookingCalendar' ) ) {

	class CPREFTRACK_AppointmentBookingCalendar extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-ABC-20200119';
		protected $name                            = 'Appointment Booking Calendar Conversion Tracking';
		protected $conversion_submission_indicator = 'Appointment Booking Calendar - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				__( 'Enables referrer tracking for conversions in the %s plugin.', 'cp-referrer-and-conversions-tracking' ),
				$this->build_plugin_info_link( 'appointment-booking-calendar', 'Appointment Booking Calendar' )
			);

			add_action( 'init', array( $this, 'check_active' ), 10 );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'cpabc_process_data_before_insert', array( $this, 'register_referrer' ), 10, 1 );
			add_action( 'cpabc_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			if ( defined( 'CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME' ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'appointment-booking-calendar/cpabc_appointments.php' ) ? 'inactive' : 'not_installed';
		}

		public function check_active() {
			$this->maybe_auto_activate( defined( 'CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME' ) );
		}

		public function get_addon_settings() {
			return $this->render_dependency_notice(
				defined( 'CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME' ),
				'Appointment Booking Calendar',
				'appointment-booking-calendar',
				'appointment-booking-calendar/cpabc_appointments.php'
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
			if ( ! defined( 'CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME' ) ) {
				return esc_html( (string) $param_registered );
			}

			$inum   = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$table  = CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$myrows = $wpdb->get_results( $wpdb->prepare( "SELECT appointment_calendar_id, title FROM `{$table}` WHERE reference = %d LIMIT 1", $inum ) );

			if ( ! empty( $myrows ) ) {
				$email = sanitize_email( (string) $myrows[0]->title );
				$url   = add_query_arg(
					array(
						'page'   => 'cpabc_appointments.php',
						'cal'    => absint( $myrows[0]->appointment_calendar_id ),
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

	$CPREFTRACK_AppointmentBookingCalendar_obj = new CPREFTRACK_AppointmentBookingCalendar();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_AppointmentBookingCalendar_obj->get_addon_id() ] = $CPREFTRACK_AppointmentBookingCalendar_obj;
}

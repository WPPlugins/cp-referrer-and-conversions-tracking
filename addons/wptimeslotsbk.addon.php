<?php
/**
 * WP Time Slots Booking Form Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_WPTimeSlotsBK' ) ) {

	class CPREFTRACK_WPTimeSlotsBK extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-WPTimeSlotsBK-20200119';
		protected $name                            = 'WP Time Slots Booking Form Conversion Tracking';
		protected $conversion_submission_indicator = 'WP Time Slots Booking Form - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				__( 'Enables referrer tracking for conversions in the %s plugin.', 'cp-referrer-and-conversions-tracking' ),
				$this->build_plugin_info_link( 'wp-time-slots-booking-form', 'WP Time Slots Booking Form' )
			);

			add_action( 'init', array( $this, 'check_active' ), 10 );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'cptslotsb_process_data_before_insert', array( $this, 'register_referrer' ), 10, 1 );
			add_action( 'cptslotsb_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			global $cp_tslotsb_plugin;
			if ( is_object( $cp_tslotsb_plugin ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'wp-time-slots-booking-form/wp-time-slots-booking-plugin.php' ) ? 'inactive' : 'not_installed';
		}

		public function check_active() {
			global $cp_tslotsb_plugin;
			$this->maybe_auto_activate( is_object( $cp_tslotsb_plugin ) );
		}

		public function get_addon_settings() {
			global $cp_tslotsb_plugin;
			return $this->render_dependency_notice(
				is_object( $cp_tslotsb_plugin ),
				'WP Time Slots Booking Form',
				'wp-time-slots-booking-form',
				'wp-time-slots-booking-form/wp-time-slots-booking-plugin.php'
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
			global $wpdb, $cp_tslotsb_plugin;
			if ( ! is_object( $cp_tslotsb_plugin ) || empty( $cp_tslotsb_plugin->table_messages ) ) {
				return esc_html( (string) $param_registered );
			}

			$inum   = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$table  = $wpdb->prefix . $cp_tslotsb_plugin->table_messages;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$myrows = $wpdb->get_results( $wpdb->prepare( "SELECT notifyto FROM `{$table}` WHERE id = %d LIMIT 1", $inum ) );

			if ( ! empty( $myrows ) ) {
				$email = sanitize_email( (string) $myrows[0]->notifyto );
				$url   = add_query_arg(
					array(
						'page'   => 'cp_timeslotsbooking',
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

	$CPREFTRACK_WPTimeSlotsBK_obj = new CPREFTRACK_WPTimeSlotsBK();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_WPTimeSlotsBK_obj->get_addon_id() ] = $CPREFTRACK_WPTimeSlotsBK_obj;
}

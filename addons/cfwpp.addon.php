<?php
/**
 * CP Contact Form with PayPal Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_CPContactFormwPayPal' ) ) {

	class CPREFTRACK_CPContactFormwPayPal extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-CPContactFormwPayPal-20200119';
		protected $name                            = 'CP Contact Form with PayPal Conversion Tracking';
		protected $conversion_submission_indicator = 'CP Contact Form with PayPal - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				 'Enables referrer tracking for conversions in the %s plugin.',
				$this->build_plugin_info_link( 'cp-contact-form-with-paypal', 'CP Contact Form with PayPal' )
			);

			add_action( 'init', array( $this, 'check_active' ), 10 );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'cpcfwpp_process_data_before_insert', array( $this, 'register_referrer' ), 10, 1 );
			add_action( 'cpcfwpp_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			if ( defined( 'CP_CONTACTFORMPP_POSTS_TABLE_NAME_NO_PREFIX' ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'cp-contact-form-with-paypal/cp_contactformpp.php' ) ? 'inactive' : 'not_installed';
		}

		public function check_active() {
			$this->maybe_auto_activate( defined( 'CP_CONTACTFORMPP_POSTS_TABLE_NAME_NO_PREFIX' ) );
		}

		public function get_addon_settings() {
			return $this->render_dependency_notice(
				defined( 'CP_CONTACTFORMPP_POSTS_TABLE_NAME_NO_PREFIX' ),
				'CP Contact Form with PayPal',
				'cp-contact-form-with-paypal',
				'cp-contact-form-with-paypal/cp_contactformpp.php'
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
			if ( ! defined( 'CP_CONTACTFORMPP_POSTS_TABLE_NAME_NO_PREFIX' ) ) {
				return esc_html( (string) $param_registered );
			}

			$inum   = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$table  = $wpdb->prefix . CP_CONTACTFORMPP_POSTS_TABLE_NAME_NO_PREFIX;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$myrows = $wpdb->get_results( $wpdb->prepare( "SELECT notifyto, paid FROM `{$table}` WHERE id = %d LIMIT 1", $inum ) );

			if ( ! empty( $myrows ) ) {
				$email = sanitize_email( (string) $myrows[0]->notifyto );
				$url   = add_query_arg(
					array(
						'page'   => 'cp_contact_form_paypal.php',
						'cal'    => '0',
						'list'   => '1',
						'search' => $email,
						'ds'     => 'Filter',
					),
					admin_url( 'admin.php' )
				);

				$paid_state = isset( $myrows[0]->paid ) ? (int) $myrows[0]->paid : 0;
				if ( 1 === $paid_state ) {
					$badge = '<span class="cprt-badge cprt-badge-emerald">' . esc_html__( 'Paid', 'cp-referrer-and-conversions-tracking' ) . '</span>';
				} elseif ( 2 === $paid_state ) {
					$badge = '<span class="cprt-badge cprt-badge-muted">' . esc_html__( 'Refunded', 'cp-referrer-and-conversions-tracking' ) . '</span>';
				} else {
					$badge = '<span class="cprt-badge cprt-badge-danger">' . esc_html__( 'Not Paid', 'cp-referrer-and-conversions-tracking' ) . '</span>';
				}

				return sprintf(
					'<span class="cprt-nowrap">%1$s</span><br /><strong>%2$s</strong> <a href="%3$s" class="cprt-link">%4$s</a><br />%5$s',
					esc_html( (string) $param_registered ),
					esc_html__( 'Email:', 'cp-referrer-and-conversions-tracking' ),
					esc_url( $url ),
					esc_html( $email ),
					$badge
				);
			}

			return esc_html( (string) $param_registered );
		}
	}

	$CPREFTRACK_CPContactFormwPayPal_obj = new CPREFTRACK_CPContactFormwPayPal();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_CPContactFormwPayPal_obj->get_addon_id() ] = $CPREFTRACK_CPContactFormwPayPal_obj;
}

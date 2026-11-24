<?php
/**
 * WordPress User Registration Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_UserRegistration' ) ) {

	class CPREFTRACK_UserRegistration extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-UserRegistration-20220820';
		protected $name                            = 'User Registration Conversion Tracking';
		protected $conversion_submission_indicator = 'New User Registration';
		protected $conversion_details_prefix       = 'USER ID: ';

		public function __construct() {
			$this->description = __( 'Enables referrer tracking whenever a new WordPress user account is registered.', 'cp-referrer-and-conversions-tracking' );

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'user_register', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			return 'core';
		}

		public function track_conversion_submission( $user_id ) {
			do_action( 'cpreftrack_register_conversion', $this->conversion_submission_indicator, $this->conversion_details_prefix . absint( $user_id ) );
		}

		public function filter_conversion_list( $param_registered ) {
			$inum = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$user = ( $inum > 0 ) ? get_userdata( $inum ) : false;

			if ( $user ) {
				$edit_url = get_edit_user_link( $inum );
				return sprintf(
					'<span class="cprt-nowrap">%1$s</span><br /><strong>%2$s</strong> <a href="%3$s" class="cprt-link">%4$s</a>',
					esc_html( (string) $param_registered ),
					esc_html__( 'Username:', 'cp-referrer-and-conversions-tracking' ),
					esc_url( $edit_url ),
					esc_html( $user->user_login )
				);
			}

			return esc_html( (string) $param_registered );
		}
	}

	$CPREFTRACK_UserRegistration_obj = new CPREFTRACK_UserRegistration();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_UserRegistration_obj->get_addon_id() ] = $CPREFTRACK_UserRegistration_obj;
}

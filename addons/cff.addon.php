<?php
/**
 * Calculated Fields Form Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_CalculatedFieldsForm' ) ) {

	class CPREFTRACK_CalculatedFieldsForm extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-CalculatedFieldsForm-20200119';
		protected $name                            = 'Calculated Fields Form Conversion Tracking';
		protected $conversion_submission_indicator = 'Calculated Field Form - Form submission';
		protected $conversion_details_prefix       = 'Submission ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				'Enables referrer tracking for conversions in the %s plugin.',
				$this->build_plugin_info_link( 'calculated-fields-form', 'Calculated Fields Form' )
			);

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'cpcff_process_data_before_insert', array( $this, 'register_referrer' ), 10, 3 );
			add_action( 'cpcff_process_data', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			if ( defined( 'CP_CALCULATEDFIELDSF_VERSION' ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'calculated-fields-form/cp_calculatedfieldsf.php' ) ? 'inactive' : 'not_installed';
		}

		public function get_addon_settings() {
			return $this->render_dependency_notice(
				defined( 'CP_CALCULATEDFIELDSF_VERSION' ),
				'Calculated Fields Form',
				'calculated-fields-form',
				'calculated-fields-form/cp_calculatedfieldsf.php'
			);
		}

		public function register_referrer( &$params, &$str = '', $fields = array() ) {
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
			return esc_html( (string) $param_registered );
		}
	}

	$CPREFTRACK_CalculatedFieldsForm_obj = new CPREFTRACK_CalculatedFieldsForm();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_CalculatedFieldsForm_obj->get_addon_id() ] = $CPREFTRACK_CalculatedFieldsForm_obj;
}

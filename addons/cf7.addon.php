<?php
/**
 * Contact Form 7 Conversion Tracking Add-On.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_CF7' ) ) {

	class CPREFTRACK_CF7 extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-CF7-20200119';
		protected $name                            = 'Contact Form 7 Conversion Tracking';
		protected $conversion_submission_indicator = 'Contact Form 7 - Message';
		protected $conversion_details_prefix       = 'Email: ';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				'Enables referrer tracking for conversions in the %s plugin.',
				$this->build_plugin_info_link( 'contact-form-7', 'Contact Form 7' )
			);

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'wpcf7_before_send_mail', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			if ( defined( 'WPCF7_VERSION' ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'contact-form-7/wp-contact-form-7.php' ) ? 'inactive' : 'not_installed';
		}

		public function get_addon_settings() {
			return $this->render_dependency_notice(
				defined( 'WPCF7_VERSION' ),
				'Contact Form 7',
				'contact-form-7',
				'contact-form-7/wp-contact-form-7.php'
			);
		}

		public function track_conversion_submission( $contact_form ) {
			if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'prop' ) ) {
				return;
			}

			$referrer = apply_filters( 'cpreftrack_referrer', '' );
			$mail     = $contact_form->prop( 'mail' );
			$email    = '';

			if ( is_array( $mail ) ) {
				$body         = isset( $mail['body'] ) ? (string) $mail['body'] : '';
				$mail['body'] = $body . "\n\nReferrer: " . sanitize_text_field( (string) $referrer );
				if ( method_exists( $contact_form, 'set_properties' ) ) {
					$contact_form->set_properties( array( 'mail' => $mail ) );
				}
				if ( isset( $mail['recipient'] ) ) {
					$email = sanitize_email( (string) $mail['recipient'] );
				}
			}

			if ( class_exists( 'WPCF7_Submission' ) ) {
				$submission = WPCF7_Submission::get_instance();
				if ( $submission && method_exists( $submission, 'get_posted_data' ) ) {
					$posted_data = $submission->get_posted_data();
					if ( is_array( $posted_data ) && ! empty( $posted_data['your-email'] ) ) {
						$sender = is_array( $posted_data['your-email'] ) ? reset( $posted_data['your-email'] ) : $posted_data['your-email'];
						$sender = sanitize_email( (string) $sender );
						if ( '' !== $sender ) {
							$email .= ( '' !== $email ? ' | ' : '' ) . __( 'Sent by:', 'cp-referrer-and-conversions-tracking' ) . ' ' . $sender;
						}
					}
				}
			}

			do_action( 'cpreftrack_register_conversion', $this->conversion_submission_indicator, $this->conversion_details_prefix . $email );
		}

		public function filter_conversion_list( $param_registered ) {
			return esc_html( (string) $param_registered );
		}
	}

	$CPREFTRACK_CF7_obj = new CPREFTRACK_CF7();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_CF7_obj->get_addon_id() ] = $CPREFTRACK_CF7_obj;
}

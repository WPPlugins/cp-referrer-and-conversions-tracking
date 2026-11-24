<?php
/**
 * WooCommerce Conversion Tracking Add-On (HPOS & Classic Order Storage compatible).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/base.addon.php';

if ( ! class_exists( 'CPREFTRACK_WooCommerce' ) ) {

	class CPREFTRACK_WooCommerce extends CPREFTRACK_BaseAddon {

		protected $addonID                         = 'addon-WooCommerce-20200119';
		protected $name                            = 'WooCommerce Conversion Tracking';
		protected $conversion_submission_indicator = 'WooCommerce - Order Placed';
		protected $conversion_details_prefix       = 'Order ID #';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				__( 'Enables referrer tracking for orders placed in %s (supports HPOS and classic orders).', 'cp-referrer-and-conversions-tracking' ),
				$this->build_plugin_info_link( 'woocommerce', 'WooCommerce' )
			);

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'my_custom_checkout_field_display_admin_order_meta' ), 10, 1 );
			add_action( 'woocommerce_new_order', array( $this, 'track_conversion_submission' ), 10, 1 );
			add_filter( $this->conversion_submission_indicator, array( $this, 'filter_conversion_list' ), 10, 1 );
		}

		public function get_status() {
			if ( class_exists( 'WooCommerce' ) ) {
				return 'active';
			}
			return $this->is_plugin_installed( 'woocommerce/woocommerce.php' ) ? 'inactive' : 'not_installed';
		}

		public function get_addon_settings() {
			return $this->render_dependency_notice(
				class_exists( 'WooCommerce' ),
				'WooCommerce',
				'woocommerce',
				'woocommerce/woocommerce.php'
			);
		}

		/**
		 * Display the recorded referrer inside the WooCommerce order admin screen.
		 *
		 * @param WC_Order $order Order object.
		 */
		public function my_custom_checkout_field_display_admin_order_meta( $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
				return;
			}

			$referrer = '';
			if ( method_exists( $order, 'get_meta' ) ) {
				$referrer = (string) $order->get_meta( 'cpreftrack_woocommerce_referrer', true );
			}
			if ( '' === $referrer ) {
				$referrer = (string) get_post_meta( $order->get_id(), 'cpreftrack_woocommerce_referrer', true );
			}

			$conv_url = admin_url( 'admin.php?page=cp_reftrack_conversions' );
			echo '<p><strong><a href="' . esc_url( $conv_url ) . '">' . esc_html__( 'Referrer', 'cp-referrer-and-conversions-tracking' ) . '</a>:</strong><br />';
			if ( '' !== $referrer ) {
				echo esc_html( $referrer );
			} else {
				echo esc_html__( 'Direct / Unknown', 'cp-referrer-and-conversions-tracking' );
			}
			echo '</p>';
		}

		/**
		 * Store referrer on new WooCommerce orders using HPOS-safe CRUD API.
		 *
		 * @param int $order_id WooCommerce Order ID.
		 */
		public function track_conversion_submission( $order_id ) {
			$order_id = absint( $order_id );
			if ( $order_id <= 0 ) {
				return;
			}

			$referrer = sanitize_text_field( (string) apply_filters( 'cpreftrack_referrer', '' ) );

			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order && method_exists( $order, 'update_meta_data' ) ) {
					$order->update_meta_data( 'cpreftrack_woocommerce_referrer', $referrer );
					$order->save_meta_data();
				}
			} else {
				update_post_meta( $order_id, 'cpreftrack_woocommerce_referrer', $referrer );
			}

			do_action( 'cpreftrack_register_conversion', $this->conversion_submission_indicator, $this->conversion_details_prefix . $order_id );
		}

		/**
		 * Link order conversion entries to the HPOS-compatible edit order URL.
		 *
		 * @param string $param_registered Stored conversion detail string.
		 * @return string
		 */
		public function filter_conversion_list( $param_registered ) {
			$inum     = absint( str_replace( $this->conversion_details_prefix, '', (string) $param_registered ) );
			$edit_url = '';

			if ( $inum > 0 && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $inum );
				if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
					$edit_url = (string) $order->get_edit_order_url();
				}
			}

			if ( '' === $edit_url && $inum > 0 ) {
				$edit_url = add_query_arg(
					array(
						'post'   => $inum,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				);
			}

			if ( '' !== $edit_url ) {
				return sprintf(
					'<a href="%1$s" class="cprt-link cprt-nowrap">%2$s</a>',
					esc_url( $edit_url ),
					esc_html( (string) $param_registered )
				);
			}

			return esc_html( (string) $param_registered );
		}
	}

	$CPREFTRACK_WooCommerce_obj = new CPREFTRACK_WooCommerce();
	global $cpreftrack_addons_objs_list;
	$cpreftrack_addons_objs_list[ $CPREFTRACK_WooCommerce_obj->get_addon_id() ] = $CPREFTRACK_WooCommerce_obj;
}

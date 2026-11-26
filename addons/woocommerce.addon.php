<?php
/**
 * WooCommerce Conversion Tracking & Order Attribution Metabox Add-On
 * Fully compatible with WooCommerce HPOS (Custom Order Tables) and Classic Order Storage.
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

		const META_REFERRER      = 'cpreftrack_woocommerce_referrer';
		const META_REFERRER_LAST = '_cpreftrack_woocommerce_referrer_last';
		const META_ENTRY         = '_cpreftrack_woocommerce_entry';
		const META_UTM_FIRST     = '_cpreftrack_woocommerce_utm_first';
		const META_UTM_LAST      = '_cpreftrack_woocommerce_utm_last';
		const META_RECORDED      = '_cpreftrack_woocommerce_recorded';

		public function __construct() {
			$this->description = sprintf(
				/* translators: %s: companion plugin link */
				'Enables referrer and UTM attribution tracking for %s orders with a dedicated Order Attribution metabox (HPOS and classic orders supported).',
				$this->build_plugin_info_link( 'woocommerce', 'WooCommerce' )
			);

			if ( ! $this->addon_is_active() ) {
				return;
			}

			add_action( 'woocommerce_new_order', array( $this, 'track_conversion_submission' ), 10, 2 );
			add_action( 'add_meta_boxes', array( $this, 'register_order_metabox' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_metabox_styles' ), 10 );
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
		 * Return the active WooCommerce order edit screen IDs (Classic CPT + HPOS).
		 *
		 * @return array
		 */
		protected function get_order_screen_ids() {
			$screens = array( 'shop_order' );

			if ( function_exists( 'wc_get_page_screen_id' ) ) {
				$hpos_screen = wc_get_page_screen_id( 'shop-order' );
				if ( is_string( $hpos_screen ) && '' !== $hpos_screen ) {
					$screens[] = $hpos_screen;
				}
			}

			return array_values( array_unique( $screens ) );
		}

		/**
		 * Register the Order Attribution metabox on WooCommerce order edit screens (HPOS & Classic).
		 */
		public function register_order_metabox() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			add_meta_box(
				'cprt_woocommerce_order_attribution',
				__( 'CP Referrer & UTM Attribution', 'cp-referrer-and-conversions-tracking' ),
				array( $this, 'render_order_attribution_metabox' ),
				$this->get_order_screen_ids(),
				'side',
				'high'
			);
		}

		/**
		 * Enqueue the lightweight Order Metabox stylesheet only on WooCommerce Order edit screens.
		 *
		 * @param string $hook Current admin page hook suffix.
		 */
		public function enqueue_order_metabox_styles( $hook ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || ! in_array( $screen->id, $this->get_order_screen_ids(), true ) ) {
				return;
			}

			wp_enqueue_style(
				'cprt-wc-order-metabox',
				CPREFTRACK_PLUGIN_URL . 'css/wc-order-metabox.css',
				array(),
				CPREFTRACK_VERSION
			);
		}

		/**
		 * Safely read a meta value from a WC_Order object (HPOS-first with post meta fallback).
		 *
		 * @param WC_Order $order Order object.
		 * @param string   $key   Meta key.
		 * @return mixed
		 */
		protected function get_order_meta_value( $order, $key ) {
			$value = '';
			if ( is_object( $order ) && method_exists( $order, 'get_meta' ) ) {
				$value = $order->get_meta( $key, true );
			}
			if ( ( '' === $value || null === $value ) && is_object( $order ) && method_exists( $order, 'get_id' ) ) {
				$value = get_post_meta( $order->get_id(), $key, true );
			}
			return $value;
		}

		/**
		 * Sanitize a UTM array using the main plugin helper if available, or an inline allowlist.
		 *
		 * @param mixed $raw Raw UTM array or JSON string.
		 * @return array
		 */
		protected function normalize_utm_array( $raw ) {
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$decoded = json_decode( $raw, true );
				$raw     = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) ? $decoded : array();
			}

			if ( ! is_array( $raw ) ) {
				return array();
			}

			global $cp_reftrack_plugin;
			if ( isset( $cp_reftrack_plugin ) && is_object( $cp_reftrack_plugin ) && method_exists( $cp_reftrack_plugin, 'sanitize_utm_params' ) ) {
				return $cp_reftrack_plugin->sanitize_utm_params( $raw );
			}

			$allowed = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
			$clean   = array();
			foreach ( $allowed as $key ) {
				if ( isset( $raw[ $key ] ) && ! is_array( $raw[ $key ] ) ) {
					$val = trim( sanitize_text_field( (string) $raw[ $key ] ) );
					if ( '' !== $val ) {
						$clean[ $key ] = substr( $val, 0, 150 );
					}
				}
			}
			return $clean;
		}

		/**
		 * Load complete attribution details for an order (order meta with historical conversion-table fallback).
		 *
		 * @param WC_Order $order WooCommerce order object.
		 * @return array{referrer: string, referrer_last: string, entry: string, utm_first: array, utm_last: array}
		 */
		protected function get_order_attribution( $order ) {
			$order_id = absint( $order->get_id() );

			$data = array(
				'referrer'      => trim( (string) $this->get_order_meta_value( $order, self::META_REFERRER ) ),
				'referrer_last' => trim( (string) $this->get_order_meta_value( $order, self::META_REFERRER_LAST ) ),
				'entry'         => trim( (string) $this->get_order_meta_value( $order, self::META_ENTRY ) ),
				'utm_first'     => $this->normalize_utm_array( $this->get_order_meta_value( $order, self::META_UTM_FIRST ) ),
				'utm_last'      => $this->normalize_utm_array( $this->get_order_meta_value( $order, self::META_UTM_LAST ) ),
			);

			// Backfill missing fields for legacy orders placed before v2.1.0 from the conversions table.
			if (
				$order_id > 0 &&
				'' === $data['referrer_last'] &&
				'' === $data['entry'] &&
				empty( $data['utm_first'] ) &&
				empty( $data['utm_last'] )
			) {
				global $wpdb, $cp_reftrack_plugin;
				$conv_table = $wpdb->prefix . ( isset( $cp_reftrack_plugin->table_conversions ) ? $cp_reftrack_plugin->table_conversions : 'cpreftrack_conversions' );
				$row        = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"SELECT referrer, referrerlast, entry, utm_data FROM `{$conv_table}` WHERE convname = %s AND convdesc = %s ORDER BY id DESC LIMIT 1",
						$this->conversion_submission_indicator,
						$this->conversion_details_prefix . $order_id
					)
				);

				if ( $row ) {
					if ( '' === $data['referrer'] && ! empty( $row->referrer ) ) {
						$data['referrer'] = trim( (string) $row->referrer );
					}
					if ( ! empty( $row->referrerlast ) ) {
						$data['referrer_last'] = trim( (string) $row->referrerlast );
					}
					if ( ! empty( $row->entry ) ) {
						$data['entry'] = trim( (string) $row->entry );
					}
					if ( ! empty( $row->utm_data ) && isset( $cp_reftrack_plugin ) && method_exists( $cp_reftrack_plugin, 'decode_conversion_utm' ) ) {
						$decoded_utm       = $cp_reftrack_plugin->decode_conversion_utm( $row->utm_data );
						$data['utm_first'] = $decoded_utm['first'];
						$data['utm_last']  = $decoded_utm['last'];
					}
				}
			}

			if ( '' !== $data['referrer_last'] && $data['referrer_last'] === $data['referrer'] ) {
				$data['referrer_last'] = '';
			}
			if ( ! empty( $data['utm_last'] ) && $data['utm_last'] === $data['utm_first'] ) {
				$data['utm_last'] = array();
			}

			return $data;
		}

		/**
		 * Format a stored entry path or URL into a safe clickable href.
		 *
		 * @param string $entry Stored relative URI or URL.
		 * @return string
		 */
		protected function format_entry_href( $entry ) {
			global $cp_reftrack_plugin;
			if ( isset( $cp_reftrack_plugin ) && is_object( $cp_reftrack_plugin ) && method_exists( $cp_reftrack_plugin, 'format_entry_url' ) ) {
				return $cp_reftrack_plugin->format_entry_url( $entry );
			}

			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				return '';
			}
			if ( 0 === strpos( $entry, '/' ) ) {
				return esc_url( rtrim( get_home_url(), '/' ) . $entry );
			}
			if ( preg_match( '#^https?://#i', $entry ) ) {
				return esc_url( $entry );
			}
			return '';
		}

		/**
		 * Render compact UTM chips inside the Order Attribution metabox.
		 *
		 * @param array  $utm_params Associative array of UTM parameters.
		 * @param string $variant    'first' or 'last'.
		 */
		protected function render_metabox_utm_chips( $utm_params, $variant = 'first' ) {
			if ( empty( $utm_params ) ) {
				return;
			}

			$labels = array(
				'utm_source'   => __( 'Source', 'cp-referrer-and-conversions-tracking' ),
				'utm_medium'   => __( 'Medium', 'cp-referrer-and-conversions-tracking' ),
				'utm_campaign' => __( 'Campaign', 'cp-referrer-and-conversions-tracking' ),
				'utm_term'     => __( 'Term', 'cp-referrer-and-conversions-tracking' ),
				'utm_content'  => __( 'Content', 'cp-referrer-and-conversions-tracking' ),
			);

			$chip_class = ( 'last' === $variant ) ? 'cprt-wc-chip cprt-wc-chip-muted' : 'cprt-wc-chip';
			echo '<div class="cprt-wc-chips">';
			foreach ( $utm_params as $key => $val ) {
				$lbl = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
				printf(
					'<span class="%1$s" title="%2$s"><span class="cprt-wc-chip-key">%3$s:</span> %4$s</span>',
					esc_attr( $chip_class ),
					esc_attr( $key . '=' . $val ),
					esc_html( $lbl ),
					esc_html( $val )
				);
			}
			echo '</div>';
		}

		/**
		 * Render the WooCommerce Order Attribution sidebar metabox (HPOS & Classic compatible).
		 *
		 * @param WP_Post|WC_Order $post_or_order_object Post or Order object supplied by WordPress/WooCommerce.
		 */
		public function render_order_attribution_metabox( $post_or_order_object ) {
			$order = null;
			if ( is_object( $post_or_order_object ) && is_a( $post_or_order_object, 'WC_Order' ) ) {
				$order = $post_or_order_object;
			} elseif ( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( absint( $post_or_order_object->ID ) );
			}

			if ( ! $order || ! method_exists( $order, 'get_id' ) ) {
				echo '<p class="cprt-wc-empty">' . esc_html__( 'Order attribution data is unavailable.', 'cp-referrer-and-conversions-tracking' ) . '</p>';
				return;
			}

			$order_id   = absint( $order->get_id() );
			$attr       = $this->get_order_attribution( $order );
			$ref        = $attr['referrer'];
			$ref_last   = $attr['referrer_last'];
			$entry      = $attr['entry'];
			$utm_first  = $attr['utm_first'];
			$utm_last   = $attr['utm_last'];
			$entry_href = $this->format_entry_href( $entry );

			if ( ! empty( $utm_first ) || 0 === strpos( $ref, 'UTM:' ) ) {
				$channel_label = __( 'UTM Campaign', 'cp-referrer-and-conversions-tracking' );
				$channel_class = 'cprt-wc-badge-violet';
			} elseif ( '' !== $ref ) {
				$channel_label = preg_match( '#^https?://#i', $ref )
					? __( 'External Referral', 'cp-referrer-and-conversions-tracking' )
					: __( 'Campaign Source', 'cp-referrer-and-conversions-tracking' );
				$channel_class = 'cprt-wc-badge-blue';
			} else {
				$channel_label = __( 'Direct / Unknown', 'cp-referrer-and-conversions-tracking' );
				$channel_class = 'cprt-wc-badge-muted';
			}

			$can_view_plugin = current_user_can( apply_filters( 'cpreftrack_capability', 'manage_options' ) );
			?>
			<div class="cprt-wc-metabox">
				<div class="cprt-wc-channel-row">
					<span class="cprt-wc-label"><?php esc_html_e( 'Origin Type', 'cp-referrer-and-conversions-tracking' ); ?></span>
					<span class="cprt-wc-badge <?php echo esc_attr( $channel_class ); ?>"><?php echo esc_html( $channel_label ); ?></span>
				</div>

				<dl class="cprt-wc-dl">
					<div class="cprt-wc-row">
						<dt><?php esc_html_e( 'First-Touch Referrer', 'cp-referrer-and-conversions-tracking' ); ?></dt>
						<dd>
							<?php if ( '' !== $ref ) : ?>
								<?php if ( preg_match( '#^https?://#i', $ref ) ) : ?>
									<a href="<?php echo esc_url( $ref ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-wc-link"><?php echo esc_html( $ref ); ?></a>
								<?php else : ?>
									<strong><?php echo esc_html( $ref ); ?></strong>
								<?php endif; ?>
							<?php else : ?>
								<span class="cprt-wc-muted"><?php esc_html_e( 'Direct / Unknown', 'cp-referrer-and-conversions-tracking' ); ?></span>
							<?php endif; ?>
						</dd>
					</div>

					<?php if ( '' !== $ref_last ) : ?>
						<div class="cprt-wc-row">
							<dt><?php esc_html_e( 'Last-Touch Referrer', 'cp-referrer-and-conversions-tracking' ); ?></dt>
							<dd>
								<?php if ( preg_match( '#^https?://#i', $ref_last ) ) : ?>
									<a href="<?php echo esc_url( $ref_last ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-wc-link"><?php echo esc_html( $ref_last ); ?></a>
								<?php else : ?>
									<span><?php echo esc_html( $ref_last ); ?></span>
								<?php endif; ?>
							</dd>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $entry ) : ?>
						<div class="cprt-wc-row">
							<dt><?php esc_html_e( 'Initial Landing Page', 'cp-referrer-and-conversions-tracking' ); ?></dt>
							<dd>
								<?php if ( '' !== $entry_href ) : ?>
									<a href="<?php echo esc_url( $entry_href ); ?>" target="_blank" rel="noopener noreferrer" class="cprt-wc-link" title="<?php echo esc_attr( $entry ); ?>">
										<?php echo esc_html( $entry ); ?>
									</a>
								<?php else : ?>
									<code class="cprt-wc-mono"><?php echo esc_html( $entry ); ?></code>
								<?php endif; ?>
							</dd>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $utm_first ) ) : ?>
						<div class="cprt-wc-row">
							<dt><?php esc_html_e( 'First-Touch UTM', 'cp-referrer-and-conversions-tracking' ); ?></dt>
							<dd><?php $this->render_metabox_utm_chips( $utm_first, 'first' ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $utm_last ) ) : ?>
						<div class="cprt-wc-row">
							<dt><?php esc_html_e( 'Last-Touch UTM', 'cp-referrer-and-conversions-tracking' ); ?></dt>
							<dd><?php $this->render_metabox_utm_chips( $utm_last, 'last' ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<?php if ( $can_view_plugin ) : ?>
					<?php
					$conv_url = add_query_arg(
						array(
							'page'   => 'cp_reftrack_conversions',
							'search' => $this->conversion_details_prefix . $order_id,
						),
						admin_url( 'admin.php' )
					);
					$stats_url = admin_url( 'admin.php?page=cp_reftrack_report' );
					$help_url  = admin_url( 'admin.php?page=cp_reftrack_help' ) . '#help-woocommerce';
					?>
					<div class="cprt-wc-footer">
						<a href="<?php echo esc_url( $conv_url ); ?>"><?php esc_html_e( 'View Conversion Log', 'cp-referrer-and-conversions-tracking' ); ?></a>
						<span aria-hidden="true">&middot;</span>
						<a href="<?php echo esc_url( $stats_url ); ?>"><?php esc_html_e( 'Stats', 'cp-referrer-and-conversions-tracking' ); ?></a>
						<span aria-hidden="true">&middot;</span>
						<a href="<?php echo esc_url( $help_url ); ?>"><?php esc_html_e( 'Help', 'cp-referrer-and-conversions-tracking' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Backward-compatible legacy callback (replaced by dedicated Order Attribution sidebar metabox).
		 *
		 * @param WC_Order $order Order object.
		 */
		public function my_custom_checkout_field_display_admin_order_meta( $order ) {
			if ( is_object( $order ) ) {
				$this->render_order_attribution_metabox( $order );
			}
		}

		/**
		 * Store first-touch/last-touch referrer, entry page, and UTM parameters on new WooCommerce orders using HPOS-safe CRUD API.
		 *
		 * @param int           $order_id WooCommerce Order ID.
		 * @param WC_Order|null $order    Optional WooCommerce Order object passed by WC 7+.
		 */
		public function track_conversion_submission( $order_id, $order = null ) {
			$order_id = absint( $order_id );
			if ( $order_id <= 0 ) {
				return;
			}

			// Avoid attributing an administrator's own session to manually created backend orders.
			if ( is_admin() && ! wp_doing_ajax() ) {
				return;
			}

			if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
			}

			// Prevent duplicate attribution writes if hook fires more than once for the same order.
			if ( $order && method_exists( $order, 'get_meta' ) && '1' === (string) $order->get_meta( self::META_RECORDED, true ) ) {
				return;
			} elseif ( ! $order && '1' === (string) get_post_meta( $order_id, self::META_RECORDED, true ) ) {
				return;
			}

			$attribution = apply_filters( 'cpreftrack_attribution_data', array() );
			if ( ! is_array( $attribution ) ) {
				$attribution = array();
			}

			$referrer      = isset( $attribution['referrer'] )
				? substr( sanitize_text_field( (string) $attribution['referrer'] ), 0, 250 )
				: substr( sanitize_text_field( (string) apply_filters( 'cpreftrack_referrer', '' ) ), 0, 250 );
			$referrer_last = isset( $attribution['referrer_last'] )
				? substr( sanitize_text_field( (string) $attribution['referrer_last'] ), 0, 250 )
				: '';
			$entry         = isset( $attribution['entry'] )
				? substr( sanitize_text_field( (string) $attribution['entry'] ), 0, 250 )
				: '';
			$utm_first     = isset( $attribution['utm_first'] ) ? $this->normalize_utm_array( $attribution['utm_first'] ) : array();
			$utm_last      = isset( $attribution['utm_last'] ) ? $this->normalize_utm_array( $attribution['utm_last'] ) : array();

			$utm_first_json = ! empty( $utm_first ) ? (string) wp_json_encode( $utm_first ) : '';
			$utm_last_json  = ! empty( $utm_last ) ? (string) wp_json_encode( $utm_last ) : '';

			if ( $order && method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( self::META_REFERRER, $referrer );
				if ( '' !== $referrer_last ) {
					$order->update_meta_data( self::META_REFERRER_LAST, $referrer_last );
				}
				if ( '' !== $entry ) {
					$order->update_meta_data( self::META_ENTRY, $entry );
				}
				if ( '' !== $utm_first_json ) {
					$order->update_meta_data( self::META_UTM_FIRST, $utm_first_json );
				}
				if ( '' !== $utm_last_json ) {
					$order->update_meta_data( self::META_UTM_LAST, $utm_last_json );
				}
				$order->update_meta_data( self::META_RECORDED, '1' );
				$order->save_meta_data();
			} else {
				update_post_meta( $order_id, self::META_REFERRER, $referrer );
				if ( '' !== $referrer_last ) {
					update_post_meta( $order_id, self::META_REFERRER_LAST, $referrer_last );
				}
				if ( '' !== $entry ) {
					update_post_meta( $order_id, self::META_ENTRY, $entry );
				}
				if ( '' !== $utm_first_json ) {
					update_post_meta( $order_id, self::META_UTM_FIRST, $utm_first_json );
				}
				if ( '' !== $utm_last_json ) {
					update_post_meta( $order_id, self::META_UTM_LAST, $utm_last_json );
				}
				update_post_meta( $order_id, self::META_RECORDED, '1' );
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

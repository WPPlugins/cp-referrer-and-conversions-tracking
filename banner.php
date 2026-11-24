<?php
/**
 * Dismissible admin review notice for CP Referrer and Conversion Tracking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $codepeople_promote_banner_plugins_cp_ref;
if ( empty( $codepeople_promote_banner_plugins_cp_ref ) || ! is_array( $codepeople_promote_banner_plugins_cp_ref ) ) {
	$codepeople_promote_banner_plugins_cp_ref = array();
}

if ( ! function_exists( 'codepeople_handle_promote_banner_cpreferrer' ) ) {
	/**
	 * Handle banner dismissal via admin_init POST with capability & nonce checks.
	 */
	function codepeople_handle_promote_banner_cpreferrer() {
		global $codepeople_promote_banner_plugins_cp_ref;

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return;
		}

		if ( ! isset( $_POST['codepeople_promote_banner_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['codepeople_promote_banner_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'codepeople_promote_banner_cpreferrer' ) ) {
			return;
		}

		$plugin_slug = isset( $_POST['codepeople_promote_banner_plugin'] )
			? sanitize_key( wp_unslash( $_POST['codepeople_promote_banner_plugin'] ) )
			: '';

		if ( '' !== $plugin_slug && ! empty( $codepeople_promote_banner_plugins_cp_ref[ $plugin_slug ] ) ) {
			set_transient( 'codepeople_promote_banner_' . $plugin_slug, -1, 0 );

			$referer = wp_get_referer();
			if ( $referer ) {
				wp_safe_redirect( $referer );
				exit;
			}
		}
	}
	add_action( 'admin_init', 'codepeople_handle_promote_banner_cpreferrer' );
}

if ( ! function_exists( 'codepeople_add_promote_banner_cpreferrer' ) ) {
	/**
	 * Render a clean, WordPress-native review notice after 3 days of usage.
	 */
	function codepeople_add_promote_banner_cpreferrer() {
		global $codepeople_promote_banner_plugins_cp_ref;

		if ( empty( $codepeople_promote_banner_plugins_cp_ref ) || ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( is_object( $screen ) && 'post' === $screen->base && in_array( $screen->post_type, array( 'page', 'post' ), true ) ) {
			return;
		}

		$minimum_days = 3 * DAY_IN_SECONDS;
		$now          = time();

		foreach ( $codepeople_promote_banner_plugins_cp_ref as $plugin_slug => $plugin_data ) {
			$transient_key = 'codepeople_promote_banner_' . sanitize_key( $plugin_slug );
			$value         = get_transient( $transient_key );

			if ( false === $value ) {
				$value = $now;
				set_transient( $transient_key, $value, 0 );
			}

			if ( is_numeric( $value ) && (int) $value > 0 && $minimum_days <= abs( $now - (int) $value ) ) {
				$plugin_name = isset( $plugin_data['plugin_name'] ) ? $plugin_data['plugin_name'] : '';
				$plugin_url  = isset( $plugin_data['plugin_url'] ) ? $plugin_data['plugin_url'] : 'https://wordpress.org/support/plugin/cp-referrer-and-conversions-tracking/reviews/#new-post';
				$nonce       = wp_create_nonce( 'codepeople_promote_banner_cpreferrer' );
				?>
				<div class="notice notice-info cprt-review-notice" style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 16px;border-left-color:#2563eb;">
					<div>
						<p style="margin:0 0 6px;">
							<?php
							printf(
								/* translators: %s: plugin name */
								esc_html__( 'Thank you for using %s! If you find it useful, would you consider leaving a 5-star review on WordPress.org?', 'cp-referrer-and-conversions-tracking' ),
								'<strong>' . esc_html( $plugin_name ) . '</strong>'
							);
							?>
						</p>
						<p style="margin:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
							<a href="<?php echo esc_url( $plugin_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
								<?php esc_html_e( 'Leave a 5-Star Review', 'cp-referrer-and-conversions-tracking' ); ?>
							</a>
							<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/cp-referrer-and-conversions-tracking/' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
								<?php esc_html_e( 'Need Help?', 'cp-referrer-and-conversions-tracking' ); ?>
							</a>
							<form method="post" style="display:inline;margin:0;">
								<input type="hidden" name="codepeople_promote_banner_plugin" value="<?php echo esc_attr( $plugin_slug ); ?>" />
								<input type="hidden" name="codepeople_promote_banner_action" value="not-thanks" />
								<input type="hidden" name="codepeople_promote_banner_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
								<button type="submit" class="button button-link" style="text-decoration:none;color:#64748b;">
									<?php esc_html_e( 'Dismiss', 'cp-referrer-and-conversions-tracking' ); ?>
								</button>
							</form>
						</p>
					</div>
				</div>
				<?php
				return;
			}
		}
	}
	add_action( 'admin_notices', 'codepeople_add_promote_banner_cpreferrer' );
}

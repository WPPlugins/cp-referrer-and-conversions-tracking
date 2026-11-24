<?php
/**
 * Add-Ons Manager view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->require_manage_access();

global $cpreftrack_addons_active_list, $cpreftrack_addons_objs_list;

$nonce = wp_create_nonce( 'cpreftrack_actions_addons' );

$status_meta = array(
	'active'        => array( 'cprt-badge-emerald', __( 'Plugin Active', 'cp-referrer-and-conversions-tracking' ) ),
	'core'          => array( 'cprt-badge-blue', __( 'WordPress Core', 'cp-referrer-and-conversions-tracking' ) ),
	'inactive'      => array( 'cprt-badge-amber', __( 'Plugin Inactive', 'cp-referrer-and-conversions-tracking' ) ),
	'not_installed' => array( 'cprt-badge-muted', __( 'Not Installed', 'cp-referrer-and-conversions-tracking' ) ),
);

$allowed_desc_html = array(
	'a'      => array(
		'href'   => true,
		'target' => true,
		'rel'    => true,
		'class'  => true,
	),
	'strong' => array(),
	'em'     => array(),
	'code'   => array(),
);

$this->render_admin_header(
	'addons',
	__( 'Conversion Tracking Add-Ons', 'cp-referrer-and-conversions-tracking' ),
	__( 'Toggle built-in integrations to automatically attribute orders, form submissions, bookings, and user registrations.', 'cp-referrer-and-conversions-tracking' )
);
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_parameter . '_addons' ) ); ?>">
	<input type="hidden" name="anonce" value="<?php echo esc_attr( $nonce ); ?>" />
	<input type="hidden" name="cpreftrack_save_addons" value="1" />

	<div class="cprt-toolbar-top">
		<span class="cprt-text-muted">
			<?php
			$total_addons = is_array( $cpreftrack_addons_objs_list ) ? count( $cpreftrack_addons_objs_list ) : 0;
			printf(
				/* translators: %d: number of available add-ons */
				esc_html( _n( '%d integration available.', '%d integrations available.', $total_addons, 'cp-referrer-and-conversions-tracking' ) ),
				$total_addons
			);
			?>
		</span>
		<button type="submit" class="cprt-btn cprt-btn-primary">
			<?php esc_html_e( 'Save Add-On Changes', 'cp-referrer-and-conversions-tracking' ); ?>
		</button>
	</div>

	<?php if ( empty( $cpreftrack_addons_objs_list ) ) : ?>
		<section class="cprt-card">
			<div class="cprt-card-body">
				<p class="cprt-empty-state"><?php esc_html_e( 'No add-ons registered so far.', 'cp-referrer-and-conversions-tracking' ); ?></p>
			</div>
		</section>
	<?php else : ?>
		<div class="cprt-addons-grid">
			<?php foreach ( $cpreftrack_addons_objs_list as $key => $obj ) : ?>
				<?php
				$is_enabled = $obj->addon_is_active();
				$status_key = method_exists( $obj, 'get_status' ) ? $obj->get_status() : 'not_installed';
				$badge_cls  = isset( $status_meta[ $status_key ] ) ? $status_meta[ $status_key ][0] : 'cprt-badge-muted';
				$badge_txt  = isset( $status_meta[ $status_key ] ) ? $status_meta[ $status_key ][1] : __( 'Available', 'cp-referrer-and-conversions-tracking' );
				$input_id   = 'cprt-addon-' . sanitize_html_class( $key );
				?>
				<div class="cprt-addon-card <?php echo $is_enabled ? 'is-enabled' : ''; ?>">
					<div class="cprt-addon-header">
						<div>
							<label for="<?php echo esc_attr( $input_id ); ?>" class="cprt-addon-title">
								<?php echo esc_html( $obj->get_addon_name() ); ?>
							</label>
							<div class="cprt-addon-badges">
								<span class="cprt-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge_txt ); ?></span>
							</div>
						</div>
						<label class="cprt-switch">
							<input type="checkbox"
								   id="<?php echo esc_attr( $input_id ); ?>"
								   name="cpreftrack_addons_active_list[]"
								   value="<?php echo esc_attr( $key ); ?>"
								   <?php checked( $is_enabled ); ?> />
							<span class="cprt-switch-slider"></span>
						</label>
					</div>
					<p class="cprt-addon-desc">
						<?php echo wp_kses( $obj->get_addon_description(), $allowed_desc_html ); ?>
					</p>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="cprt-form-footer">
			<button type="submit" class="cprt-btn cprt-btn-primary">
				<?php esc_html_e( 'Save Add-On Changes', 'cp-referrer-and-conversions-tracking' ); ?>
			</button>
		</div>
	<?php endif; ?>
</form>

<?php
$this->render_admin_footer();

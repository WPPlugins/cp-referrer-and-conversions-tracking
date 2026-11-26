<?php
/**
 * Base class for CP Referrer and Conversion Tracking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CP_REFTRACK_BaseClass' ) ) {

	class CP_REFTRACK_BaseClass {

		/**
		 * Active item identifier.
		 *
		 * @var int
		 */
		protected $item = 1;

		/**
		 * Cached item row.
		 *
		 * @var object|false
		 */
		private $option_buffered_item = false;

		/**
		 * Cached item ID.
		 *
		 * @var int
		 */
		private $option_buffered_id = -1;

		/**
		 * Activation handler with multisite support.
		 *
		 * @param bool $networkwide Whether the plugin is network activated.
		 */
		public function install( $networkwide = false ) {
			if ( is_multisite() && $networkwide ) {
				$site_ids = get_sites( array( 'fields' => 'ids' ) );
				foreach ( $site_ids as $site_id ) {
					switch_to_blog( (int) $site_id );
					$this->_install();
					restore_current_blog();
				}
				return;
			}

			$this->_install();
		}

		/**
		 * Required capability to manage plugin screens and actions.
		 *
		 * @return string
		 */
		public function get_capability() {
			return apply_filters( 'cpreftrack_capability', 'manage_options' );
		}

		/**
		 * Check whether the current user is in wp-admin and authorized.
		 *
		 * @return bool
		 */
		public function user_can_manage() {
			return is_admin() && current_user_can( $this->get_capability() );
		}

		/**
		 * Enforce admin capability or terminate with HTTP 403.
		 */
		public function require_manage_access() {
			if ( ! $this->user_can_manage() ) {
				wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'cp-referrer-and-conversions-tracking' ),
					esc_html__( 'Access Denied', 'cp-referrer-and-conversions-tracking' ),
					array( 'response' => 403 )
				);
			}
		}

		/**
		 * Retrieve and sanitize a request parameter from GET or POST.
		 *
		 * @param string $key     Parameter name.
		 * @param string $default Default value.
		 * @return string
		 */
		public function get_param( $key, $default = '' ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] && ! is_array( $_GET[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
			if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] && ! is_array( $_POST[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			return $default;
		}

		/**
		 * Retrieve and sanitize a GET parameter.
		 *
		 * @param string $key     Parameter name.
		 * @param string $default Default value.
		 * @return string
		 */
		public function get_get_param( $key, $default = '' ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] && ! is_array( $_GET[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
			return $default;
		}

		/**
		 * Retrieve and sanitize a POST parameter.
		 *
		 * @param string $key     Parameter name.
		 * @param string $default Default value.
		 * @return string
		 */
		public function get_post_param( $key, $default = '' ) {
			if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] && ! is_array( $_POST[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			return $default;
		}

		/**
		 * Validate a YYYY-MM-DD date string.
		 *
		 * @param string $date_str Raw date input.
		 * @return string Valid YYYY-MM-DD date or empty string.
		 */
		public function validate_date( $date_str ) {
			$date_str = trim( (string) $date_str );
			if ( '' === $date_str ) {
				return '';
			}
			if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date_str, $matches ) ) {
				if ( checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
					return $date_str;
				}
			}
			return '';
		}

		/**
		 * Check administrator capability.
		 *
		 * @return bool
		 */
		public function is_administrator() {
			return current_user_can( $this->get_capability() );
		}

		/**
		 * Return the site or admin base URL without trailing slash.
		 *
		 * @param bool $admin Whether to return the admin URL.
		 * @return string
		 */
		public function get_site_url( $admin = false ) {
			$blog = get_current_blog_id();
			$url  = $admin ? get_admin_url( $blog ) : get_home_url( $blog );
			return rtrim( $url, '/' );
		}

		/**
		 * Backward-compatible alias for full site URL (does not trust Host header).
		 *
		 * @param bool $admin Whether to return the admin URL.
		 * @return string
		 */
		public function get_FULL_site_url( $admin = false ) {
			return $this->get_site_url( $admin );
		}

		/**
		 * Clean legacy JSON string formatting.
		 *
		 * @param string $str Input string.
		 * @return string
		 */
		public function cleanJSON( $str ) {
			$str = str_replace( '&qquot;', '"', (string) $str );
			$str = str_replace( "\t", ' ', $str );
			$str = str_replace( "\n", '\n', $str );
			$str = str_replace( "\r", '', $str );
			return $str;
		}

		/**
		 * Safely verify and add a column to a plugin table if missing.
		 *
		 * @param string $table Full table name.
		 * @param string $field Column name.
		 * @param string $type  Allowlisted SQL column type.
		 */
		public function add_field_verify( $table, $field, $type = 'text' ) {
			global $wpdb;

			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $field ) ) {
				return;
			}

			$allowed_types = array(
				'text'         => 'TEXT',
				'mediumtext'   => 'MEDIUMTEXT',
				'varchar(250)' => "VARCHAR(250) DEFAULT '' NOT NULL",
				'int'          => 'INT(11) DEFAULT 0 NOT NULL',
			);

			$type_key = strtolower( trim( $type ) );
			if ( ! isset( $allowed_types[ $type_key ] ) ) {
				return;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", $field ) );
			if ( empty( $results ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE `{$table}` ADD `{$field}` " . $allowed_types[ $type_key ] );
			}
		}

		/**
		 * Verify a WordPress nonce or fail cleanly with HTTP 403.
		 *
		 * @param string $nonce  Submitted nonce value.
		 * @param string $action Nonce action identifier.
		 */
		public function verify_nonce( $nonce, $action ) {
			if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
				wp_die(
					esc_html__( 'Security verification failed (invalid nonce). Please refresh the page and try again.', 'cp-referrer-and-conversions-tracking' ),
					esc_html__( 'Security Error', 'cp-referrer-and-conversions-tracking' ),
					array( 'response' => 403 )
				);
			}
		}

		/**
		 * Return the supported standard UTM parameter keys.
		 *
		 * @return array
		 */
		public function get_utm_keys() {
			return array(
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
			);
		}

		/**
		 * Return human-readable labels for standard UTM keys.
		 *
		 * @return array
		 */
		public function get_utm_labels() {
			return array(
				'utm_source'   => __( 'Source', 'cp-referrer-and-conversions-tracking' ),
				'utm_medium'   => __( 'Medium', 'cp-referrer-and-conversions-tracking' ),
				'utm_campaign' => __( 'Campaign', 'cp-referrer-and-conversions-tracking' ),
				'utm_term'     => __( 'Term', 'cp-referrer-and-conversions-tracking' ),
				'utm_content'  => __( 'Content', 'cp-referrer-and-conversions-tracking' ),
			);
		}

		/**
		 * Filter and sanitize an associative array of UTM parameters.
		 *
		 * @param mixed $raw Raw array of UTM values.
		 * @return array Clean associative array containing only allowlisted, non-empty UTM keys.
		 */
		public function sanitize_utm_params( $raw ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$clean = array();
			foreach ( $this->get_utm_keys() as $key ) {
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
		 * Safely decode log metadata stored as JSON (preferred) or legacy PHP serialized array.
		 *
		 * @param mixed $raw Raw database column content.
		 * @return array
		 */
		public function decode_log_data( $raw ) {
			if ( is_array( $raw ) ) {
				return $raw;
			}

			if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
				return array();
			}

			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}

			if ( is_serialized( $raw ) ) {
				$unserialized = @unserialize( $raw, array( 'allowed_classes' => false ) );
				if ( is_array( $unserialized ) ) {
					return $unserialized;
				}
			}

			return array();
		}

		/**
		 * Safely decode conversion UTM attribution JSON into normalized ['first' => array, 'last' => array].
		 *
		 * @param mixed $raw Raw utm_data column content.
		 * @return array{first: array, last: array}
		 */
		public function decode_conversion_utm( $raw ) {
			$result = array(
				'first' => array(),
				'last'  => array(),
			);

			$decoded = $this->decode_log_data( $raw );
			if ( empty( $decoded ) ) {
				return $result;
			}

			if ( isset( $decoded['first'] ) && is_array( $decoded['first'] ) ) {
				$result['first'] = $this->sanitize_utm_params( $decoded['first'] );
			}
			if ( isset( $decoded['last'] ) && is_array( $decoded['last'] ) ) {
				$result['last'] = $this->sanitize_utm_params( $decoded['last'] );
			}

			// Handle flat UTM array gracefully if stored directly.
			if ( empty( $result['first'] ) && empty( $result['last'] ) ) {
				$flat = $this->sanitize_utm_params( $decoded );
				if ( ! empty( $flat ) ) {
					$result['first'] = $flat;
				}
			}

			return $result;
		}

		/**
		 * Render compact UTM chips HTML for admin list tables.
		 *
		 * @param array  $utm_params Associative array of UTM key => value.
		 * @param string $variant    Optional CSS modifier ('first' or 'last').
		 * @return string Safe escaped HTML.
		 */
		public function render_utm_badges_html( $utm_params, $variant = 'first' ) {
			$clean = $this->sanitize_utm_params( $utm_params );
			if ( empty( $clean ) ) {
				return '';
			}

			$labels = $this->get_utm_labels();
			$class  = ( 'last' === $variant ) ? 'cprt-utm-chip cprt-utm-chip-muted' : 'cprt-utm-chip';
			$parts  = array();

			foreach ( $clean as $key => $value ) {
				$short_label = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
				$parts[]     = sprintf(
					'<span class="%1$s" title="%2$s"><span class="cprt-utm-key">%3$s:</span> %4$s</span>',
					esc_attr( $class ),
					esc_attr( $key . '=' . $value ),
					esc_html( $short_label ),
					esc_html( $value )
				);
			}

			return '<div class="cprt-utm-list">' . implode( '', $parts ) . '</div>';
		}

		/**
		 * Format a UTM array as a single plain-text summary string (useful for privacy exports & CSV).
		 *
		 * @param array $utm_params Associative array of UTM key => value.
		 * @return string
		 */
		public function format_utm_plain_text( $utm_params ) {
			$clean = $this->sanitize_utm_params( $utm_params );
			if ( empty( $clean ) ) {
				return '';
			}

			$pairs = array();
			foreach ( $clean as $key => $value ) {
				$pairs[] = $key . '=' . $value;
			}
			return implode( ', ', $pairs );
		}

		/**
		 * Retrieve an option field from the registered items table (preserved for compatibility).
		 *
		 * @param string $field         Field name.
		 * @param mixed  $default_value Fallback value.
		 * @return mixed
		 */
		public function get_option( $field, $default_value = '' ) {
			global $wpdb;

			$value = $default_value;

			if ( $this->option_buffered_id === $this->item && is_object( $this->option_buffered_item ) ) {
				$value = isset( $this->option_buffered_item->$field ) ? $this->option_buffered_item->$field : $default_value;
			} else {
				$table  = $wpdb->prefix . $this->table_items;
				$myrows = $wpdb->get_results(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $this->item )
				);
				if ( ! empty( $myrows ) ) {
					$this->option_buffered_item = $myrows[0];
					$this->option_buffered_id   = $this->item;
					$value                      = isset( $myrows[0]->$field ) ? $myrows[0]->$field : $default_value;
				}
			}

			return apply_filters( 'cpreftrack_get_option', $value, $field, $this->item );
		}

		/**
		 * Get sanitized entry URL for safe display in href attributes.
		 *
		 * @param string $entry Stored relative URI or full URL.
		 * @return string
		 */
		public function format_entry_url( $entry ) {
			$entry = trim( (string) $entry );
			if ( '' === $entry ) {
				return '';
			}
			if ( 0 === strpos( $entry, '/' ) ) {
				return esc_url( $this->get_site_url() . $entry );
			}
			if ( preg_match( '#^https?://#i', $entry ) ) {
				return esc_url( $entry );
			}
			return '';
		}

		/**
		 * Allowed HTML tags for filtered conversion descriptions.
		 *
		 * @return array
		 */
		public function get_allowed_conversion_html() {
			return array(
				'a'      => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
					'class'  => true,
					'title'  => true,
				),
				'span'   => array(
					'class' => true,
				),
				'strong' => array(),
				'b'      => array(),
				'em'     => array(),
				'br'     => array(),
				'small'  => array(
					'class' => true,
				),
				'code'   => array(),
				'div'    => array(
					'class' => true,
				),
			);
		}

		/**
		 * Render flash admin notices passed via PRG redirect query parameters.
		 */
		protected function render_flash_notice() {
			$code  = $this->get_get_param( 'cprt_notice' );
			$count = absint( $this->get_get_param( 'cprt_count', '0' ) );

			if ( '' === $code ) {
				return;
			}

			$messages = array(
				'settings_saved'   => array( 'success', __( 'Settings updated successfully.', 'cp-referrer-and-conversions-tracking' ) ),
				'addons_saved'     => array( 'success', __( 'Add-on settings updated successfully.', 'cp-referrer-and-conversions-tracking' ) ),
				'item_added'       => array( 'success', __( 'Referral source added successfully.', 'cp-referrer-and-conversions-tracking' ) ),
				'item_invalid'     => array( 'error', __( 'Please enter a Referral Name, Parameter Name, and Parameter Value.', 'cp-referrer-and-conversions-tracking' ) ),
				'item_deleted'     => array( 'success', __( 'Item deleted.', 'cp-referrer-and-conversions-tracking' ) ),
				'marked_deleted'   => array(
					'success',
					/* translators: %d: number of deleted items */
					sprintf( _n( '%d selected item deleted.', '%d selected items deleted.', $count, 'cp-referrer-and-conversions-tracking' ), $count ),
				),
				'all_deleted'      => array( 'success', __( 'All records have been deleted.', 'cp-referrer-and-conversions-tracking' ) ),
				'cleanup_done'     => array(
					'success',
					/* translators: %d: number of pruned logs */
					sprintf( _n( 'Cleanup complete: %d expired log removed.', 'Cleanup complete: %d expired logs removed.', $count, 'cp-referrer-and-conversions-tracking' ), $count ),
				),
				'nothing_selected' => array( 'warning', __( 'No items were selected.', 'cp-referrer-and-conversions-tracking' ) ),
			);

			if ( ! isset( $messages[ $code ] ) ) {
				return;
			}

			list( $type, $text ) = $messages[ $code ];
			printf(
				'<div class="cprt-notice cprt-notice-%1$s" role="status"><span class="cprt-notice-dot"></span><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( $text )
			);
		}

		/**
		 * Build a URL to the built-in Help & User Guide, optionally anchored to a specific section.
		 *
		 * @param string $section Optional section anchor ID (without leading #).
		 * @return string
		 */
		public function get_help_url( $section = '' ) {
			$url = admin_url( 'admin.php?page=' . $this->menu_parameter . '_help' );
			$section = preg_replace( '/[^a-z0-9\-_]/i', '', (string) $section );
			if ( '' !== $section ) {
				$url .= '#' . $section;
			}
			return $url;
		}

		/**
		 * Render the unified modern plugin header and navigation bar.
		 *
		 * @param string $active_slug Active tab key.
		 * @param string $page_title  Section title.
		 * @param string $subtitle    Optional subtitle.
		 */
		public function render_admin_header( $active_slug, $page_title, $subtitle = '' ) {
			$nav_items = array(
				'dashboard'   => array(
					'label' => __( 'Dashboard', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter,
				),
				'logs'        => array(
					'label' => __( 'Tracking Logs', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_list',
				),
				'stats'       => array(
					'label' => __( 'Tracking Stats', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_report',
				),
				'sources'     => array(
					'label' => __( 'Referral Sources', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_parameters',
				),
				'conversions' => array(
					'label' => __( 'Conversions', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_conversions',
				),
				'addons'      => array(
					'label' => __( 'Add-Ons', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_addons',
				),
				'settings'    => array(
					'label' => __( 'Settings', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_settings',
				),
				'help'        => array(
					'label' => __( 'Help & Guide', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_help',
				),
				'support'     => array(
					'label' => __( 'Support', 'cp-referrer-and-conversions-tracking' ),
					'page'  => $this->menu_parameter . '_support',
				),
			);

			$help_section_map = array(
				'dashboard'   => array( 'help-overview', __( 'Quick Start Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'logs'        => array( 'help-logs', __( 'Logs Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'stats'       => array( 'help-stats', __( 'Stats Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'sources'     => array( 'help-utm-sources', __( 'UTM & Links Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'conversions' => array( 'help-conversions', __( 'Conversions Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'addons'      => array( 'help-addons', __( 'Add-Ons Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'settings'    => array( 'help-settings', __( 'Settings & Privacy Guide', 'cp-referrer-and-conversions-tracking' ) ),
				'support'     => array( 'help-developers', __( 'Troubleshooting & Hooks', 'cp-referrer-and-conversions-tracking' ) ),
			);
			$contextual_help  = isset( $help_section_map[ $active_slug ] ) ? $help_section_map[ $active_slug ] : null;

			$tracking_enabled = ( '' === get_option( 'cp_cpreftrack_rep_enable', '' ) );
			?>
			<div class="wrap cprt-admin">
				<header class="cprt-header">
					<div class="cprt-header-top">
						<div class="cprt-brand">
							<div class="cprt-brand-icon" aria-hidden="true">
								<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
									<polyline points="16 7 22 7 22 13"></polyline>
								</svg>
							</div>
							<div>
								<h1 class="cprt-title"><?php echo esc_html( $page_title ); ?></h1>
								<?php if ( '' !== $subtitle ) : ?>
									<p class="cprt-subtitle"><?php echo esc_html( $subtitle ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="cprt-header-meta">
							<?php if ( $contextual_help ) : ?>
								<a href="<?php echo esc_url( $this->get_help_url( $contextual_help[0] ) ); ?>" class="cprt-btn cprt-btn-sm cprt-btn-secondary cprt-header-help-btn cprt-no-print" title="<?php echo esc_attr( $contextual_help[1] ); ?>">
									<span class="cprt-help-q" aria-hidden="true">?</span>
									<?php echo esc_html( $contextual_help[1] ); ?>
								</a>
							<?php endif; ?>
							<span class="cprt-status-pill <?php echo $tracking_enabled ? 'cprt-pill-success' : 'cprt-pill-muted'; ?>">
								<span class="cprt-pill-dot"></span>
								<?php echo $tracking_enabled ? esc_html__( 'Tracking Active', 'cp-referrer-and-conversions-tracking' ) : esc_html__( 'Tracking Paused', 'cp-referrer-and-conversions-tracking' ); ?>
							</span>
							<span class="cprt-version-badge">v<?php echo esc_html( defined( 'CPREFTRACK_VERSION' ) ? CPREFTRACK_VERSION : '2.1.0' ); ?></span>
						</div>
					</div>
					<nav class="cprt-nav" aria-label="<?php esc_attr_e( 'Plugin navigation', 'cp-referrer-and-conversions-tracking' ); ?>">
						<?php foreach ( $nav_items as $slug => $item ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['page'] ) ); ?>"
							   class="cprt-nav-link <?php echo ( $active_slug === $slug ) ? 'is-active' : ''; ?>">
								<?php echo esc_html( $item['label'] ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
				</header>
				<div class="cprt-body">
					<?php $this->render_flash_notice(); ?>
			<?php
		}

		/**
		 * Close the unified admin shell wrapper.
		 */
		public function render_admin_footer() {
			?>
				</div>
			</div>
			<?php
		}
	}
}

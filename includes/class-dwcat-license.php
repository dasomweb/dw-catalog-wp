<?php
/**
 * License Client Class
 *
 * Integrates with DW Site Builder License API.
 * Handles license activation, verification, deactivation, and update checks.
 *
 * Based on: DW Site Builder License Integration Guide
 * API: POST /activate, POST /verify, POST /deactivate, GET /check-update
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_License {

	const OPTION_KEY       = 'dwcat_license_key';
	const OPTION_STATUS    = 'dwcat_license_status';
	const OPTION_EXPIRES   = 'dwcat_license_expires';
	const TRANSIENT_VERIFY = 'dwcat_license_verified';
	const PRODUCT_SLUG     = 'dw-catalog-wp';

	/**
	 * License API base URL.
	 */
	private static function api_url() {
		return 'https://developer.dasomweb.com/api/licenses';
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_dwcat_license_activate', array( $this, 'handle_activate' ) );
		add_action( 'admin_post_dwcat_license_deactivate', array( $this, 'handle_deactivate' ) );
		add_action( 'admin_init', array( $this, 'schedule_verify' ) );
	}

	/**
	 * Add License submenu under DW Catalog settings.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'dw-catalog-settings',
			__( 'License', 'dw-catalog-wp' ),
			__( 'License', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-license',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render license management page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$license_key = get_option( self::OPTION_KEY, '' );
		$status      = get_option( self::OPTION_STATUS, 'inactive' );
		$expires     = get_option( self::OPTION_EXPIRES, '' );
		$domain      = self::get_domain();
		$is_active   = ( $status === 'active' );
		$message     = isset( $_GET['dwcat_msg'] ) ? sanitize_text_field( $_GET['dwcat_msg'] ) : '';
		$error       = isset( $_GET['dwcat_err'] ) ? sanitize_text_field( $_GET['dwcat_err'] ) : '';
		?>
		<div class="wrap">
			<h1><?php _e( 'DW Catalog — License', 'dw-catalog-wp' ); ?></h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:600px;">
				<table class="form-table">
					<tr>
						<th><?php _e( 'Status', 'dw-catalog-wp' ); ?></th>
						<td>
							<?php if ( $is_active ) : ?>
								<span style="color:#00a32a; font-weight:600;">&#10003; <?php _e( 'Active', 'dw-catalog-wp' ); ?></span>
							<?php else : ?>
								<span style="color:#d63638; font-weight:600;">&#10007; <?php _e( 'Inactive', 'dw-catalog-wp' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Domain', 'dw-catalog-wp' ); ?></th>
						<td><code><?php echo esc_html( $domain ); ?></code></td>
					</tr>
					<?php if ( $is_active && $expires ) : ?>
						<tr>
							<th><?php _e( 'Expires', 'dw-catalog-wp' ); ?></th>
							<td><?php echo esc_html( $expires ); ?></td>
						</tr>
					<?php endif; ?>
				</table>

				<?php if ( $is_active ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'dwcat_license_deactivate', 'dwcat_license_nonce' ); ?>
						<input type="hidden" name="action" value="dwcat_license_deactivate">
						<p>
							<strong><?php _e( 'License Key:', 'dw-catalog-wp' ); ?></strong>
							<code><?php echo esc_html( substr( $license_key, 0, 8 ) . '...' . substr( $license_key, -4 ) ); ?></code>
						</p>
						<?php submit_button( __( 'Deactivate License', 'dw-catalog-wp' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'dwcat_license_activate', 'dwcat_license_nonce' ); ?>
						<input type="hidden" name="action" value="dwcat_license_activate">
						<p>
							<label for="dwcat_license_key"><strong><?php _e( 'License Key', 'dw-catalog-wp' ); ?></strong></label><br>
							<input type="text" id="dwcat_license_key" name="license_key"
								   value="<?php echo esc_attr( $license_key ); ?>"
								   class="regular-text" required
								   placeholder="<?php esc_attr_e( 'Enter your license key', 'dw-catalog-wp' ); ?>">
						</p>
						<?php submit_button( __( 'Activate License', 'dw-catalog-wp' ), 'primary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle license activation.
	 */
	public function handle_activate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		check_admin_referer( 'dwcat_license_activate', 'dwcat_license_nonce' );

		$license_key = sanitize_text_field( $_POST['license_key'] );
		if ( empty( $license_key ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-license&dwcat_err=' . urlencode( __( 'License key is required.', 'dw-catalog-wp' ) ) ) );
			exit;
		}

		$response = self::api_request( '/activate', array(
			'license_key'  => $license_key,
			'domain'       => self::get_domain(),
			'product_slug' => self::PRODUCT_SLUG,
		) );

		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-license&dwcat_err=' . urlencode( $response->get_error_message() ) ) );
			exit;
		}

		if ( ! empty( $response['success'] ) ) {
			update_option( self::OPTION_KEY, $license_key );
			update_option( self::OPTION_STATUS, 'active' );
			if ( ! empty( $response['expires_at'] ) ) {
				update_option( self::OPTION_EXPIRES, $response['expires_at'] );
			}
			set_transient( self::TRANSIENT_VERIFY, true, DAY_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-license&dwcat_msg=' . urlencode( $response['message'] ?? __( 'License activated.', 'dw-catalog-wp' ) ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-license&dwcat_err=' . urlencode( $response['error'] ?? __( 'Activation failed.', 'dw-catalog-wp' ) ) ) );
		}
		exit;
	}

	/**
	 * Handle license deactivation.
	 */
	public function handle_deactivate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		check_admin_referer( 'dwcat_license_deactivate', 'dwcat_license_nonce' );

		$license_key = get_option( self::OPTION_KEY, '' );
		if ( ! empty( $license_key ) ) {
			self::api_request( '/deactivate', array(
				'license_key' => $license_key,
				'domain'      => self::get_domain(),
			) );
		}

		update_option( self::OPTION_STATUS, 'inactive' );
		delete_option( self::OPTION_EXPIRES );
		delete_transient( self::TRANSIENT_VERIFY );

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-license&dwcat_msg=' . urlencode( __( 'License deactivated.', 'dw-catalog-wp' ) ) ) );
		exit;
	}

	/**
	 * Periodic license verification (daily via transient).
	 */
	public function schedule_verify() {
		if ( get_option( self::OPTION_STATUS ) !== 'active' ) {
			return;
		}
		if ( get_transient( self::TRANSIENT_VERIFY ) ) {
			return;
		}

		$license_key = get_option( self::OPTION_KEY, '' );
		if ( empty( $license_key ) ) {
			return;
		}

		$response = self::api_request( '/verify', array(
			'license_key'  => $license_key,
			'domain'       => self::get_domain(),
			'product_slug' => self::PRODUCT_SLUG,
		) );

		if ( ! is_wp_error( $response ) && ! empty( $response['valid'] ) ) {
			set_transient( self::TRANSIENT_VERIFY, true, DAY_IN_SECONDS );
			if ( ! empty( $response['expires_at'] ) ) {
				update_option( self::OPTION_EXPIRES, $response['expires_at'] );
			}
		} else {
			update_option( self::OPTION_STATUS, 'inactive' );
			delete_transient( self::TRANSIENT_VERIFY );
		}
	}

	/**
	 * Check for plugin update via license API.
	 *
	 * @return array|false Update info or false.
	 */
	public static function check_update() {
		$license_key = get_option( self::OPTION_KEY, '' );
		if ( empty( $license_key ) || get_option( self::OPTION_STATUS ) !== 'active' ) {
			return false;
		}

		$config = dwcat_get_config();
		$response = wp_remote_get( add_query_arg( array(
			'license_key'     => $license_key,
			'product_slug'    => self::PRODUCT_SLUG,
			'current_version' => $config['plugin_version'],
		), self::api_url() . '/check-update' ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['update_available'] ) ) {
			return $body;
		}

		return false;
	}

	/**
	 * Whether the license is currently active.
	 */
	public static function is_active() {
		return get_option( self::OPTION_STATUS ) === 'active';
	}

	/**
	 * Get normalized site domain.
	 */
	private static function get_domain() {
		$url  = home_url();
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host ? strtolower( preg_replace( '/^www\./', '', $host ) ) : 'localhost';
	}

	/**
	 * Make a POST request to the license API.
	 */
	private static function api_request( $endpoint, $body ) {
		$response = wp_remote_post( self::api_url() . $endpoint, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || ! is_array( $data ) ) {
			return new WP_Error( 'dwcat_license_error', __( 'License server request failed.', 'dw-catalog-wp' ) );
		}

		return $data;
	}
}

<?php
/**
 * DW License Manager — WordPress Plugin License SDK
 *
 * Drop this file into your plugin and initialize it.
 * Handles: license activation, deactivation, verification, and auto-updates.
 *
 * Usage:
 *   require_once __DIR__ . '/class-dw-license-manager.php';
 *   DW_License_Manager::init([
 *       'product_slug'  => 'dw-catalog-wp',
 *       'plugin_slug'   => 'dw-catalog-wp/dw-catalog-wp.php',
 *       'plugin_name'   => 'DW Catalog WP',
 *       'version'       => '1.0.0',
 *       'api_url'       => 'https://api-production-a3f4.up.railway.app/api/v1',
 *   ]);
 *
 * Source: DW-Admin License Manager SDK (via DW-MCP)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DW_License_Manager' ) ) :

class DW_License_Manager {

	private static $instances = [];

	private $product_slug;
	private $plugin_slug;
	private $plugin_name;
	private $version;
	private $api_url;
	private $settings_page;
	private $option_key;

	public static function init( array $config ) {
		$slug = $config['product_slug'];
		if ( ! isset( self::$instances[ $slug ] ) ) {
			self::$instances[ $slug ] = new self( $config );
		}
		return self::$instances[ $slug ];
	}

	private function __construct( array $config ) {
		$this->product_slug  = $config['product_slug'];
		$this->plugin_slug   = $config['plugin_slug'];
		$this->plugin_name   = $config['plugin_name'];
		$this->version       = $config['version'];
		$this->api_url       = rtrim( $config['api_url'], '/' );
		$this->settings_page = $config['settings_page'] ?? $this->product_slug . '-license';
		$this->option_key    = 'dw_license_' . str_replace( '-', '_', $this->product_slug );

		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_dw_activate_license_' . $this->product_slug, [ $this, 'ajax_activate' ] );
		add_action( 'wp_ajax_dw_deactivate_license_' . $this->product_slug, [ $this, 'ajax_deactivate' ] );
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_init', [ $this, 'maybe_verify_license' ] );

		add_action( 'dw_verify_license_' . $this->product_slug, [ $this, 'verify_license' ] );
		if ( ! wp_next_scheduled( 'dw_verify_license_' . $this->product_slug ) ) {
			wp_schedule_event( time(), 'daily', 'dw_verify_license_' . $this->product_slug );
		}
	}

	private function get_license_data() {
		return get_option( $this->option_key, [
			'key'        => '',
			'status'     => 'inactive',
			'expires_at' => null,
			'checked_at' => null,
		] );
	}

	private function save_license_data( array $data ) {
		update_option( $this->option_key, $data );
	}

	public function get_license_key() {
		$data = $this->get_license_data();
		return $data['key'] ?? '';
	}

	public function is_active() {
		$data = $this->get_license_data();
		return ( $data['status'] ?? '' ) === 'active';
	}

	private function api_request( string $method, string $endpoint, array $body = [] ) {
		$url = $this->api_url . $endpoint;
		$args = [
			'method'  => $method,
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
		];
		if ( $method === 'GET' && ! empty( $body ) ) {
			$url = add_query_arg( $body, $url );
		} elseif ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'error' => $response->get_error_message() ];
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 ) {
			return [ 'success' => false, 'error' => $data['message'] ?? 'API error.' ];
		}
		return $data;
	}

	public function activate( string $license_key ) {
		$result = $this->api_request( 'POST', '/license/activate', [
			'license_key'  => $license_key,
			'domain'       => home_url(),
			'product_slug' => $this->product_slug,
		] );
		if ( ! empty( $result['success'] ) ) {
			$this->save_license_data( [
				'key'        => $license_key,
				'status'     => 'active',
				'expires_at' => $result['expires_at'] ?? null,
				'checked_at' => current_time( 'mysql' ),
			] );
			return [ 'success' => true, 'message' => $result['message'] ?? 'License activated.' ];
		}
		return [ 'success' => false, 'message' => $result['error'] ?? 'Activation failed.' ];
	}

	public function deactivate() {
		$data = $this->get_license_data();
		if ( ! empty( $data['key'] ) ) {
			$this->api_request( 'POST', '/license/deactivate', [
				'license_key' => $data['key'],
				'domain'      => home_url(),
			] );
		}
		$this->save_license_data( [
			'key' => '', 'status' => 'inactive', 'expires_at' => null, 'checked_at' => null,
		] );
		return [ 'success' => true, 'message' => 'License deactivated.' ];
	}

	public function verify_license() {
		$data = $this->get_license_data();
		if ( empty( $data['key'] ) ) return;
		$result = $this->api_request( 'POST', '/license/verify', [
			'license_key'  => $data['key'],
			'domain'       => home_url(),
			'product_slug' => $this->product_slug,
		] );
		$data['checked_at'] = current_time( 'mysql' );
		if ( ! empty( $result['valid'] ) ) {
			$data['status']     = 'active';
			$data['expires_at'] = $result['expires_at'] ?? null;
		} else {
			$data['status'] = 'inactive';
			$data['key']    = '';
		}
		$this->save_license_data( $data );
		delete_transient( 'dw_license_check_' . $this->product_slug );
	}

	public function maybe_verify_license() {
		$data = $this->get_license_data();
		if ( empty( $data['key'] ) || $data['status'] !== 'active' ) return;
		$tk = 'dw_license_check_' . $this->product_slug;
		if ( get_transient( $tk ) ) return;
		$this->verify_license();
		set_transient( $tk, 1, HOUR_IN_SECONDS );
	}

	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) return $transient;
		$data = $this->get_license_data();
		if ( empty( $data['key'] ) || $data['status'] !== 'active' ) return $transient;
		$result = $this->api_request( 'GET', '/releases/update-check', [
			'license_key'     => $data['key'],
			'product_slug'    => $this->product_slug,
			'current_version' => $this->version,
		] );
		if ( ! empty( $result['update_available'] ) ) {
			$transient->response[ $this->plugin_slug ] = (object) [
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $result['version'],
				'url'         => '',
				'package'     => $result['download_url'] ?? '',
			];
		} else {
			$transient->no_update[ $this->plugin_slug ] = (object) [
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $this->version,
			];
		}
		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) return $result;
		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) return $result;
		$data = $this->get_license_data();
		if ( empty( $data['key'] ) ) return $result;
		$update = $this->api_request( 'GET', '/releases/update-check', [
			'license_key'     => $data['key'],
			'product_slug'    => $this->product_slug,
			'current_version' => '0.0.0',
		] );
		if ( empty( $update['update_available'] ) ) return $result;
		return (object) [
			'name'          => $this->plugin_name,
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $update['version'],
			'download_link' => $update['download_url'] ?? '',
			'sections'      => [
				'changelog'   => nl2br( esc_html( $update['changelog'] ?? '' ) ),
				'description' => $this->plugin_name . ' WordPress Plugin',
			],
			'tested'        => '6.7',
			'requires'      => '5.0',
			'requires_php'  => '7.4',
		];
	}

	public function ajax_activate() {
		check_ajax_referer( 'dw_license_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$key = sanitize_text_field( $_POST['license_key'] ?? '' );
		if ( empty( $key ) ) wp_send_json_error( 'Please enter a license key.' );
		$result = $this->activate( $key );
		$result['success'] ? wp_send_json_success( $result['message'] ) : wp_send_json_error( $result['message'] );
	}

	public function ajax_deactivate() {
		check_ajax_referer( 'dw_license_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$this->deactivate();
		wp_send_json_success( 'License deactivated.' );
	}

	public function add_menu_page() {
		add_submenu_page(
			'dw-catalog-settings',
			$this->plugin_name . ' License',
			__( 'License', 'dw-catalog-wp' ),
			'manage_options',
			$this->settings_page,
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting( $this->settings_page, $this->option_key );
	}

	public function render_settings_page() {
		$data       = $this->get_license_data();
		$is_active  = $data['status'] === 'active';
		$status_map = [
			'active'   => [ 'label' => 'Active',  'color' => '#00a32a' ],
			'inactive' => [ 'label' => 'Inactive', 'color' => '#72777c' ],
			'expired'  => [ 'label' => 'Expired',  'color' => '#d63638' ],
			'invalid'  => [ 'label' => 'Invalid',  'color' => '#d63638' ],
		];
		$status_info = $status_map[ $data['status'] ] ?? $status_map['inactive'];
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->plugin_name ); ?> — License</h1>
			<div style="max-width:600px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:24px;margin-top:20px;">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Status</th>
						<td>
							<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $status_info['color'] ); ?>;margin-right:6px;"></span>
							<strong style="color:<?php echo esc_attr( $status_info['color'] ); ?>"><?php echo esc_html( $status_info['label'] ); ?></strong>
							<?php if ( $data['expires_at'] ) : ?>
								<span style="color:#72777c;margin-left:8px;">(Expires: <?php echo esc_html( date( 'Y-m-d', strtotime( $data['expires_at'] ) ) ); ?>)</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dw-license-key">License Key</label></th>
						<td>
							<?php if ( $is_active ) : ?>
								<code style="display:inline-block;padding:8px 12px;background:#f0f0f1;border-radius:4px;font-size:13px;">
									<?php echo esc_html( substr( $data['key'], 0, 8 ) . '...' . substr( $data['key'], -4 ) ); ?>
								</code>
							<?php else : ?>
								<input type="text" id="dw-license-key" class="regular-text" value="" placeholder="XXXXXX-XXXXXX-XXXXXX" />
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<?php if ( $is_active ) : ?>
								<button type="button" id="dw-deactivate-btn" class="button">Deactivate</button>
							<?php else : ?>
								<button type="button" id="dw-activate-btn" class="button button-primary">Activate</button>
							<?php endif; ?>
							<span id="dw-license-msg" style="margin-left:12px;"></span>
						</td>
					</tr>
					<?php if ( $data['checked_at'] ) : ?>
					<tr>
						<th>Last Checked</th>
						<td style="color:#72777c;"><?php echo esc_html( $data['checked_at'] ); ?></td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
		</div>
		<script>
		jQuery(function($){
			var slug=<?php echo wp_json_encode( $this->product_slug ); ?>;
			var nonce=<?php echo wp_json_encode( wp_create_nonce( 'dw_license_nonce' ) ); ?>;
			var $msg=$('#dw-license-msg');
			$('#dw-activate-btn').on('click',function(){
				var key=$('#dw-license-key').val().trim();
				if(!key){$msg.text('Enter a key.').css('color','red');return;}
				$(this).prop('disabled',true).text('Checking...');
				$.post(ajaxurl,{action:'dw_activate_license_'+slug,nonce:nonce,license_key:key},function(r){
					if(r.success)location.reload();
					else{$msg.text(r.data).css('color','red');$('#dw-activate-btn').prop('disabled',false).text('Activate');}
				});
			});
			$('#dw-deactivate-btn').on('click',function(){
				if(!confirm('Deactivate license?'))return;
				$(this).prop('disabled',true).text('Processing...');
				$.post(ajaxurl,{action:'dw_deactivate_license_'+slug,nonce:nonce},function(){location.reload();});
			});
		});
		</script>
		<?php
	}

	public function admin_notices() {
		$data   = $this->get_license_data();
		$screen = get_current_screen();
		if ( $data['status'] === 'invalid' ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html( $this->plugin_name ) . ':</strong> License is invalid. <a href="' . esc_url( admin_url( 'admin.php?page=' . $this->settings_page ) ) . '">Check settings</a></p></div>';
		} elseif ( $data['status'] === 'expired' ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html( $this->plugin_name ) . ':</strong> License expired. <a href="https://www.dwsitebuilder.com/pricing" target="_blank">Renew</a></p></div>';
		} elseif ( empty( $data['key'] ) && $screen && $screen->id !== 'dw-catalog_page_' . $this->settings_page ) {
			echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html( $this->plugin_name ) . ':</strong> Activate your license for auto-updates. <a href="' . esc_url( admin_url( 'admin.php?page=' . $this->settings_page ) ) . '">Enter license key</a></p></div>';
		}
	}
}

endif;

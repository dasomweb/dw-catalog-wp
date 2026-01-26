<?php
/**
 * GitHub Updater Class
 * 
 * Domain-agnostic GitHub updater that does not depend on site domain.
 * Uses only GitHub API, plugin file path, and plugin slug.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_GitHub_Updater Class
 * 
 * Handles plugin updates from GitHub repository.
 * Completely domain-independent.
 */
class PC_GitHub_Updater {

	/**
	 * Plugin file path
	 * 
	 * @var string
	 */
	private $plugin_file;

	/**
	 * GitHub repository owner
	 * 
	 * @var string
	 */
	private $repo_owner;

	/**
	 * GitHub repository name
	 * 
	 * @var string
	 */
	private $repo_name;

	/**
	 * Plugin slug
	 * 
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Current plugin version
	 * 
	 * @var string
	 */
	private $version;

	/**
	 * GitHub API base URL
	 * 
	 * @var string
	 */
	private $api_base = 'https://api.github.com';

	/**
	 * Constructor
	 * 
	 * @param string $plugin_file Plugin file path
	 * @param string $repo_owner  GitHub repository owner
	 * @param string $repo_name   GitHub repository name
	 * @param string $plugin_slug Plugin slug
	 * @param string $version     Current plugin version
	 */
	public function __construct( $plugin_file, $repo_owner, $repo_name, $plugin_slug, $version ) {
		$this->plugin_file = $plugin_file;
		$this->repo_owner  = $repo_owner;
		$this->repo_name   = $repo_name;
		$this->plugin_slug = $plugin_slug;
		$this->version     = $version;

		// Hook into WordPress update system
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
	}

	/**
	 * Check for updates from GitHub
	 * 
	 * This method is domain-independent - it only uses:
	 * - GitHub API (external, domain-independent)
	 * - Plugin file path (relative to WordPress)
	 * - Plugin slug (identifier, not URL)
	 * 
	 * @param object $transient Update transient object
	 * @return object Modified transient object
	 */
	public function check_for_updates( $transient ) {
		// Only check if we have update data
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get latest release from GitHub API
		$latest_release = $this->get_latest_release();

		if ( ! $latest_release || is_wp_error( $latest_release ) ) {
			return $transient;
		}

		// Compare versions
		$latest_version = $this->extract_version_from_tag( $latest_release->tag_name );
		
		if ( version_compare( $this->version, $latest_version, '<' ) ) {
			// Update available
			$plugin_basename = plugin_basename( $this->plugin_file );
			
			// Get download URL from release assets or fallback to zipball
			$package_url = $this->get_download_url( $latest_release );
			
			$transient->response[ $plugin_basename ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $plugin_basename,
				'new_version' => $latest_version,
				'url'         => $this->get_plugin_info_url(), // Uses WordPress function
				'package'     => $package_url, // GitHub release asset or zipball
			);
		}

		return $transient;
	}

	/**
	 * Get latest release from GitHub API
	 * 
	 * Uses GitHub API only - completely domain-independent
	 * 
	 * @return object|WP_Error Release object or error
	 */
	private function get_latest_release() {
		$cache_key = 'pc_github_latest_release_' . md5( $this->repo_owner . $this->repo_name );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// GitHub API endpoint - domain-independent
		$api_url = sprintf(
			'%s/repos/%s/%s/releases/latest',
			$this->api_base,
			$this->repo_owner,
			$this->repo_name
		);

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error( 'github_api_error', 'Failed to fetch release from GitHub API' );
		}

		$release = json_decode( $body );

		// Cache for 12 hours
		set_transient( $cache_key, $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Get download URL from release
	 * Prefers release assets (zip file) over zipball
	 * 
	 * @param object $release GitHub release object
	 * @return string Download URL
	 */
	private function get_download_url( $release ) {
		// Check for release assets (preferred - proper zip file)
		if ( isset( $release->assets ) && is_array( $release->assets ) && ! empty( $release->assets ) ) {
			// Look for zip file in assets
			foreach ( $release->assets as $asset ) {
				if ( isset( $asset->browser_download_url ) && 
					 ( strpos( $asset->name, '.zip' ) !== false || 
					   strpos( $asset->content_type, 'zip' ) !== false ) ) {
					return $asset->browser_download_url;
				}
			}
			// If no zip found, use first asset
			if ( isset( $release->assets[0]->browser_download_url ) ) {
				return $release->assets[0]->browser_download_url;
			}
		}
		
		// Fallback to zipball URL
		return isset( $release->zipball_url ) ? $release->zipball_url : '';
	}

	/**
	 * Extract version number from Git tag
	 * 
	 * @param string $tag Git tag (e.g., "v1.0.0" or "1.0.0")
	 * @return string Version number
	 */
	private function extract_version_from_tag( $tag ) {
		// Remove 'v' prefix if present
		return ltrim( $tag, 'v' );
	}

	/**
	 * Get plugin info URL
	 * Uses WordPress admin_url() - domain agnostic
	 * 
	 * @return string Plugin info URL
	 */
	private function get_plugin_info_url() {
		return admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->plugin_slug );
	}

	/**
	 * Plugin API call for update information
	 * 
	 * @param false|object|array $result Result object
	 * @param string             $action Action being performed
	 * @param object             $args   Arguments
	 * @return object|false Result object or false
	 */
	public function plugin_api_call( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		// Get plugin information from GitHub
		$release = $this->get_latest_release();

		if ( ! $release || is_wp_error( $release ) ) {
			return $result;
		}

		// Build response object
		$result = new stdClass();
		$result->name           = $this->plugin_slug;
		$result->slug           = $this->plugin_slug;
		$result->version        = $this->extract_version_from_tag( $release->tag_name );
		$result->author         = '<a href="https://github.com/' . esc_attr( $this->repo_owner ) . '">' . esc_html( $this->repo_owner ) . '</a>';
		$result->homepage       = 'https://github.com/' . esc_attr( $this->repo_owner ) . '/' . esc_attr( $this->repo_name );
		$result->download_link  = $this->get_download_url( $release );
		$result->sections       = array(
			'description' => $release->body ? $release->body : '',
		);

		return $result;
	}

	/**
	 * Post-install hook
	 * 
	 * @param bool  $response   Installation response
	 * @param array $hook_extra Extra arguments
	 * @param array $result     Installation result
	 * @return bool Response
	 */
	public function post_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check if this is our plugin
		$plugin_basename = plugin_basename( $this->plugin_file );
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $plugin_basename ) {
			return $response;
		}

		$install_directory = plugin_dir_path( $this->plugin_file );
		$source = $result['destination'];

		// If source is a subdirectory (common with GitHub zipballs), move contents up
		if ( is_dir( $source ) ) {
			$files = $wp_filesystem->dirlist( $source );
			
			if ( $files ) {
				// Check if there's a single subdirectory (GitHub zipball structure)
				$subdirs = array_filter( $files, function( $file ) {
					return $file['type'] === 'd';
				});
				
				if ( count( $subdirs ) === 1 ) {
					// Move into the subdirectory
					$subdir_name = key( $subdirs );
					$source = trailingslashit( $source ) . $subdir_name;
				}
				
				// Move all files from source to install directory
				$moved = $wp_filesystem->move( $source, $install_directory, true );
				
				if ( ! $moved ) {
					// Fallback: copy files
					$this->copy_directory( $source, $install_directory );
				}
			}
		}

		$result['destination'] = $install_directory;

		// Reactivate plugin if it was active
		if ( is_plugin_active( $plugin_basename ) ) {
			activate_plugin( $plugin_basename );
		}

		return $response;
	}

	/**
	 * Copy directory recursively
	 * 
	 * @param string $source Source directory
	 * @param string $destination Destination directory
	 * @return bool Success
	 */
	private function copy_directory( $source, $destination ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem->is_dir( $source ) ) {
			return false;
		}

		// Create destination directory if it doesn't exist
		if ( ! $wp_filesystem->is_dir( $destination ) ) {
			$wp_filesystem->mkdir( $destination, FS_CHMOD_DIR );
		}

		$files = $wp_filesystem->dirlist( $source );
		
		if ( ! $files ) {
			return false;
		}

		foreach ( $files as $file ) {
			$source_path = trailingslashit( $source ) . $file['name'];
			$dest_path = trailingslashit( $destination ) . $file['name'];

			if ( $file['type'] === 'd' ) {
				$this->copy_directory( $source_path, $dest_path );
			} else {
				$wp_filesystem->copy( $source_path, $dest_path, true );
			}
		}

		return true;
	}
}


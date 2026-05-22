<?php
/**
 * Checking for updates
 */


/**
 * Define Namespaces
 */
namespace Apos37\RoleVisibility;
use Apos37\RoleVisibility\License;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


// Instantiate
new Update();


/**
 * The class
 */
class Update {

	/**
     * Define plugin constants
     */
    private $svn_path;
    private $license_key;
    private $cache_key;
    private $cache_allowed;
    

    /**
     * Constructor
     */
    public function __construct() {

        // Check license validity
        if ( !(new License())->has_been_validated() ) {
            return;
        }

        // Set plugin details
        $this->svn_path = ROLEVISIBILITY_AUTHOR_URI . 'wp-content/svn/' . ROLEVISIBILITY_TEXTDOMAIN . '/';
        $this->license_key = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_license_id' );
        $this->cache_key = ROLEVISIBILITY__TEXTDOMAIN . '_update_check';
        $this->cache_allowed = true;

        // Hooks for update checking
        add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'pre_check' ] );
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );

    } // End __construct()


    /**
     * Make the request to the update server
     *
     * @return object|false
     */
    private function request() {
        $decoded = get_transient( $this->cache_key );

        // If cache is not set or cache is not allowed
        if ( false === $decoded || !$this->cache_allowed ) {
            $info_path = ROLEVISIBILITY_AUTHOR_URI . 'wp-content/svn/info.php?plugin=' . ROLEVISIBILITY_TEXTDOMAIN . '&license=' . $this->license_key . '&site=' . home_url();

            $remote = wp_remote_get(
                $info_path, 
                [
                    'timeout' => 10,
                    'headers' => [ 'Accept' => 'application/json' ]
                ]
            );

            if ( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
                error_log( ROLEVISIBILITY_NAME . ': Error fetching update info: ' . wp_remote_retrieve_response_message( $remote ) );
                return false;
            }

            $json_response = wp_remote_retrieve_body( $remote );

            // Trim the trailing comma if present
            $json_response = rtrim( $json_response, ',' );

            // Decode JSON and check for errors
            $decoded = json_decode( $json_response );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return false;
            }

            // Check for specific error messages in the response
            if ( isset( $decoded->error ) && !empty( $decoded->error ) ) {
                error_log( ROLEVISIBILITY_NAME . ': ' . __( 'License error', 'role-visibility' ) . ' - ' . $decoded->error );
                return false;
            }

            // Cache the result for 12 hours
            set_transient( $this->cache_key, $decoded, 12 * HOUR_IN_SECONDS );
            return $decoded;
        }

        return $decoded;
    } // End request()


    /**
     * Get plugin info (for the "View Details" link on the update page)
     *
     * @param false|object|array $res
     * @param string $action
     * @param object $args
     * @return object
     */
    public function info( $res, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $res;
        }

        if ( ROLEVISIBILITY_TEXTDOMAIN !== $args->slug ) {
            return $res;
        }

        $remote = $this->request();
        if ( !$remote ) {
            return $res;
        }

        return $this->prepare( $remote );
    } // End info()


    /**
     * Prepare the object
     *
     * @param object|false $remote
     * @return object
     */
    public function prepare( $remote ) {
        if ( !$remote ) {
            return false;
        }

        $tags_path = $this->svn_path . 'tags/';
        $assets_path = $this->svn_path . 'assets/';

        $res = new \stdClass();
        $res->id = 'wpe/plugins/' . ROLEVISIBILITY_TEXTDOMAIN;
        $res->name = $remote->name;
        $res->slug = ROLEVISIBILITY_TEXTDOMAIN;
        $res->plugin = ROLEVISIBILITY_BASENAME;
        $res->new_version = $remote->version;
        $res->url = ROLEVISIBILITY_PLUGIN_URI;
        $res->package = $tags_path . ROLEVISIBILITY_TEXTDOMAIN . '.' . $remote->version . '.zip';
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->requires_php = $remote->requires_php;
        $res->last_updated = $remote->last_updated;

        $res->sections = [
            'description' => $remote->sections->description,
            'changelog' => $remote->sections->changelog,
            'installation' => $remote->sections->installation,
        ];

        $res->icons = [
            '1x' => $assets_path . 'icon-128x128.png',
            '2x' => $assets_path . 'icon-256x256.png'
        ];
        $res->banners = [
            'low' => $assets_path . 'banner-772x250.png',
            'high' => $assets_path . 'banner-1544x500.png'
        ];

        return $res;
    } // End prepare()


    /**
     * Check for plugin updates
     *
     * @param object $transient
     * @return object
     */
    public function pre_check( $transient ) {
        $license = (new License())->has_valid_license();
        if ( !$license ) {
            add_action( 'admin_notices', [ $this, 'license_update_notice' ] );
            return $transient;
        }

        $remote = $this->request();
        if ( $remote && version_compare( ROLEVISIBILITY_VERSION, $remote->version, '<' ) && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' ) && version_compare( $remote->requires_php, PHP_VERSION, '<' ) ) {
            $transient->response[ ROLEVISIBILITY_BASENAME ] = $this->prepare( $remote );
        }
        return $transient;
    } // End pre_check()


    /**
     * License update notice
     *
     * @return void
     */
    public function license_update_notice() {
        echo '<div class="notice notice-error"><p>' . wp_kses( sprintf( __( 'Plugin updates are disabled because your %1$s license is invalid. Please enter a valid license key in the <a href="%2$s">settings</a>.', 'role-visibility' ),
                esc_html( ROLEVISIBILITY_NAME ),
                esc_url( ROLEVISIBILITY_SETTINGS_PATH )
            ), [ 'a' => [ 'href' => [], 'target' => [] ] ] ) . '</p></div>';
    } // license_update_notice()


    /**
     * Purge the cache after a successful plugin update
     *
     * @param [type] $upgrader
     * @param array $options
     * @return void
     */
    public function purge( $upgrader, $options ) {
        if ( isset( $options[ 'action' ] ) && 'update' === $options[ 'action' ] && 'plugin' === $options[ 'type' ] ) {
            delete_transient( $this->cache_key );
        }
    } // End purge()

}
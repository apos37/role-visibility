<?php
/**
 * Plugin Name:         Role Visibility — Hide Pages & Posts Based on Roles
 * Plugin URI:          https://pluginrx.com/plugin/role-visibility/
 * Description:         Control who can view your posts and pages by role, user or login status, with customizable access denied templates.
 * Version:             2.0.0
 * Requires at least:   6.0
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         role-visibility
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Created on:          October 15, 2024
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * BOOTSTRAP
 *
 * Loads plugin metadata, performs environment checks, and initializes the plugin.
 */
final class Bootstrap {

    /**
     * Plugin files to load, in order.
     */
    public const FILES = [
        'helpers.php',
        'settings.php',
        'menu.php',
        'plugin-page.php',
        'access.php',
        'restricted-index.php',
        'templates.php',
        'templates-list.php',
        'shortcode.php',
        'pickers.php',
        'meta-box.php',
        'list-table.php',
        'hide.php',
        'sitemaps.php',
        'nav-menus.php',
        'advanced.php',
        'migrate.php',
    ];


    /**
     * Plugin header keys for get_file_data()
     */
    public const HEADER_KEYS = [
        'name'         => 'Plugin Name',
        'description'  => 'Description',
        'version'      => 'Version',
        'plugin_uri'   => 'Plugin URI',
        'requires_php' => 'Requires PHP',
        'textdomain'   => 'Text Domain',
        'author'       => 'Author',
        'author_uri'   => 'Author URI',
        'discord_uri'  => 'Discord URI',
    ];


    /**
     * @var array Plugin metadata from file header
     */
    private array $meta;


    /**
     * @var Bootstrap|null Singleton instance
     */
    private static ?Bootstrap $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        $this->meta = get_file_data( __FILE__, self::HEADER_KEYS );
        $this->check_environment();
        add_action( 'plugins_loaded', [ $this, 'load_files' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    } // End __construct()


    /**
     * Get a meta value
     *
     * @param string $key
     * @return string
     */
    public function meta( string $key ) : string {
        return $this->meta[ $key ] ?? '';
    } // End meta()


    /**
     * Get plugin version
     *
     * @return string
     */
    public static function version() : string {
        return self::instance()->meta( 'version' );
    } // End version()


    /**
     * Check if the site is in test mode via Developer Debug Tools.
     *
     * @return bool
     */
    public static function test_mode() : bool {
        return (bool) get_option( 'ddtt_test_mode' );
    } // End test_mode()


    /**
     * Get the version string used for enqueued scripts/styles.
     *
     * @return string
     */
    public static function script_version() : string {
        return self::test_mode() ? (string) time() : self::version();
    } // End script_version()


    /**
     * Get the plugin's base directory path, with trailing slash.
     *
     * @return string
     */
    public static function dir() : string {
        return plugin_dir_path( __FILE__ );
    } // End dir()


    /**
     * Get the plugin's base URL, with trailing slash.
     *
     * @return string
     */
    public static function url() : string {
        return plugin_dir_url( __FILE__ );
    } // End url()


    /**
     * Get the plugin's main file path.
     *
     * @return string
     */
    public static function file() : string {
        return __FILE__;
    } // End file()


    /**
     * Check environment requirements
     *
     * @return void
     */
    private function check_environment() : void {
        $requires_php = $this->meta( 'requires_php' );

        if ( $requires_php && version_compare( PHP_VERSION, $requires_php, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'notice_php_version' ] );
        }
    } // End check_environment()


    /**
     * Admin notice for unsupported PHP version
     *
     * @return void
     */
    public function notice_php_version() : void {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Role Visibility requires a newer version of PHP. Please contact your host to upgrade.', 'role-visibility' ) . '</p></div>';
    } // End notice_php_version()


    /**
     * Load plugin files
     *
     * @return void
     */
    public function load_files() : void {
        if ( version_compare( PHP_VERSION, $this->meta( 'requires_php' ), '<' ) ) {
            return;
        }

        foreach ( self::FILES as $file ) {
            require_once self::dir() . 'inc/' . $file;
        }

        do_action( 'rolevisibility_loaded' );
    } // End load_files()


    /**
     * Activation hook. Setup and migration run on the next init, once all files are loaded.
     *
     * @return void
     */
    public function activate() : void {
        if ( ! get_option( 'rolevisibility_activated_time' ) ) {
            update_option( 'rolevisibility_activated_time', time(), false );
        }
    } // End activate()

} // End class Bootstrap

Bootstrap::instance();

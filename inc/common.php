<?php
/**
 * Methods commonly used by all of our plugins
 * Version check, activation, deactivation, uninstallation, translations etc.
 */


/**
 * Define Namespaces
 */
namespace Apos37\RoleVisibility;
use Apos37\RoleVisibility\Settings;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new Common();


/**
 * Register activation and deactivation hooks
 */
register_uninstall_hook( ROLEVISIBILITY_BASENAME, [ 'Apos37\RoleVisibility\Common', 'uninstall_plugin' ] );


/**
 * The class
 */
class Common {

	/**
	 * Defines
	 */
	private $basename = ROLEVISIBILITY_BASENAME;
	private $php_version = ROLEVISIBILITY_MIN_PHP_VERSION;
	private $plugin_name = ROLEVISIBILITY_NAME;
    private $text__domain = ROLEVISIBILITY__TEXTDOMAIN;


    /**
     * Constructor
     */
    public function __construct() {

        // PHP Version check
		$this->check_php_version();

        // Add links to the website and discord
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
        
    } // End __construct()


	/**
	 * Prevent loading the plugin if PHP version is not minimum
	 *
	 * @return void
	 */
	public function check_php_version() {
		if ( version_compare( PHP_VERSION, $this->php_version, '<=' ) ) {
			add_action( 'admin_init', function() {
					deactivate_plugins( $this->basename );
			} );
			add_action( 'admin_notices', function() {
				echo wp_kses_post(
                    /* translators: %1$s: Plugin name, %2$s: Required PHP version */
                    sprintf( '<div class="notice notice-error"><p>' . __( '"%1$s" requires PHP %2$s or newer.', 'role-visibility' ) . '</p></div>',
                        $this->plugin_name,
                        $this->php_version
                    )
				);
			} );
			return;
		}
	} // End check_php_version()


    /**
     * Add links to the website and discord
     *
     * @param array $links
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {
        $text_domain = ROLEVISIBILITY_TEXTDOMAIN;
        if ( $text_domain . '/' . $text_domain . '.php' == $file ) {

            $guide_url = ROLEVISIBILITY_GUIDE_URL;
            // $docs_url = ROLEVISIBILITY_DOCS_URL;
            $support_url = ROLEVISIBILITY_SUPPORT_URL;
            $plugin_name = ROLEVISIBILITY_NAME;

            $our_links = [
                'guide' => [
                    // translators: Link label for the plugin's user-facing guide.
                    'label' => __( 'How-To Guide', 'role-visibility' ),
                    'url'   => $guide_url
                ],
                // 'docs' => [
                //     // translators: Link label for the plugin's developer documentation.
                //     'label' => __( 'Developer Docs', 'role-visibility' ),
                //     'url'   => $docs_url
                // ],
                'support' => [
                    // translators: Link label for the plugin's support page.
                    'label' => __( 'Support', 'role-visibility' ),
                    'url'   => $support_url
                ],
            ];

            $row_meta = [];
            foreach ( $our_links as $key => $link ) {
                // translators: %1$s is the link label, %2$s is the plugin name.
                $aria_label = sprintf( __( '%1$s for %2$s', 'role-visibility' ), $link[ 'label' ], $plugin_name );
                $row_meta[ $key ] = '<a href="' . esc_url( $link[ 'url' ] ) . '" target="_blank" aria-label="' . esc_attr( $aria_label ) . '">' . esc_html( $link[ 'label' ] ) . '</a>';
            }

            // Add the links
            return array_merge( $links, $row_meta );
        }

        // Return the links
        return (array) $links;
    } // End plugin_row_meta()


    /**
     * Uninstall the plugin
	 * 
	 * @return void
     */
    public static function uninstall_plugin() {
        $instance = new self();
    
        // Perform cleanup
        $reset = get_option( $instance->text__domain . '_uninstall_reset' );
        if ( $reset ) {
            error_log( 'Begin uninstalling ' . $instance->plugin_name . '.' );
            error_log( 'Resetting ' . $instance->plugin_name . ' per user request. The "Delete All Data on Uninstall" option was checked.' );
    
            $SETTINGS = new Settings();
    
            // Clear all settings
            $settings = $SETTINGS->get_settings_fields( true );
            foreach ( $settings as $setting ) {
                delete_option( $instance->text__domain . '_' . $setting );
            }
    
            // Clean up the posts and pages in chunks
            $paged = 1;
            $chunk_size = 100;
            do {
                $args = [
                    'post_type'      => 'any',
                    'posts_per_page' => $chunk_size,
                    'fields'         => 'ids',
                    'paged'          => $paged,
                ];
                $posts = get_posts( $args );
    
                foreach ( $posts as $post_id ) {
                    delete_post_meta( $post_id, $SETTINGS->meta_key_option );
                    delete_post_meta( $post_id, $SETTINGS->meta_key_msg );
                }
    
                $paged++;
            } while ( count( $posts ) === $chunk_size );
    
            error_log( 'Reset complete for ' . $instance->plugin_name . '.' );
            error_log( 'Uninstall complete for ' . $instance->plugin_name . '.' );
        }
    } // End uninstall_plugin()    

}
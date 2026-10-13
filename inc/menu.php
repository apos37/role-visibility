<?php
/**
 * MENU
 *
 * Registers the top-level admin menu and renders the shared header and subheader on the plugin's tabs.
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class Menu {

    /**
     * Top-level menu slug.
     */
    public const SLUG = 'role-visibility';


    /**
     * @var Menu|null Singleton instance
     */
    private static ?Menu $instance = null;


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
        add_action( 'admin_menu', [ $this, 'register_menu' ], 9 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_shared_assets' ] );
        add_filter( 'admin_body_class', [ $this, 'body_class' ] );
        add_action( 'admin_head', [ $this, 'preload_logo' ], 1 );
        add_action( 'in_admin_header', [ $this, 'remove_screen_meta' ], 1 );
        add_action( 'in_admin_header', [ $this, 'maybe_render_header' ] );
        add_action( 'rolevisibility_subheader_left', [ $this, 'render_templates_buttons' ] );
        add_action( 'rolevisibility_subheader_right', [ $this, 'render_version' ], 5 );
        add_action( 'rolevisibility_subheader_right', [ $this, 'render_links' ] );
    } // End __construct()


    /**
     * Screen ID of the Settings tab.
     *
     * @return string
     */
    public static function settings_screen_id() : string {
        return 'toplevel_page_' . self::SLUG;
    } // End settings_screen_id()


    /**
     * Screen IDs that display the plugin header.
     *
     * @return array
     */
    public static function screen_ids() : array {
        return [ self::settings_screen_id(), 'edit-' . Templates::POST_TYPE ];
    } // End screen_ids()


    /**
     * Get the header tabs.
     *
     * @return array Associative array of key => [ label, url, screen ID ].
     */
    public static function tabs() : array {
        $tabs = [
            'settings'  => [
                'label'  => __( 'Settings', 'role-visibility' ),
                'url'    => Settings::url(),
                'screen' => self::settings_screen_id(),
            ],
            'templates' => [
                'label'  => __( 'Templates', 'role-visibility' ),
                'url'    => admin_url( 'edit.php?post_type=' . Templates::POST_TYPE ),
                'screen' => 'edit-' . Templates::POST_TYPE,
            ],
        ];

        return apply_filters( 'rolevisibility_tabs', $tabs );
    } // End tabs()


    /**
     * Register the top-level menu. Runs before post type submenus so Settings is the first item.
     *
     * @return void
     */
    public function register_menu() : void {
        $title = __( 'Role Visibility', 'role-visibility' );

        add_menu_page( $title, $title, 'manage_options', self::SLUG, [ Settings::class, 'render_page' ], 'dashicons-visibility', 81 );
        add_submenu_page( self::SLUG, __( 'Settings', 'role-visibility' ), __( 'Settings', 'role-visibility' ), 'manage_options', self::SLUG, [ Settings::class, 'render_page' ] );
    } // End register_menu()


    /**
     * Get the active tab key.
     *
     * @return string
     */
    public static function active_tab() : string {
        $screen = get_current_screen();

        foreach ( self::tabs() as $key => $tab ) {
            if ( $screen && $screen->id === $tab[ 'screen' ] ) {
                return $key;
            }
        }

        return '';
    } // End active_tab()


    /**
     * Add a body class on the plugin's screens.
     *
     * @param string $classes
     * @return string
     */
    public function body_class( $classes ) : string {
        return Helpers::is_plugin_screen() ? $classes . ' rolevisibility-admin-screen' : (string) $classes;
    } // End body_class()


    /**
     * Remove Screen Options and Help from the plugin's screens.
     *
     * @return void
     */
    public function remove_screen_meta() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        add_filter( 'screen_options_show_screen', '__return_false' );
        get_current_screen()->remove_help_tabs();
    } // End remove_screen_meta()


    /**
     * Render the header and subheader before admin notices, on the plugin's screens only.
     *
     * @return void
     */
    public function maybe_render_header() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        $tabs = self::tabs();
        $active = self::active_tab();
        $logo = self::logo_url();
        $logo_info = self::logo_info( $logo );
        ?>
        <div id="rolevisibility-header">
            <img src="<?php echo esc_url( $logo_info[ 'data' ] ?: $logo, [ 'http', 'https', 'data' ] ); ?>" alt="" class="<?php echo esc_attr( self::logo_needs_invert( $logo, $logo_info[ 'lum' ] ) ? 'logo is-inverted' : 'logo' ); ?>"<?php echo $logo_info[ 'width' ] ? ' width="' . esc_attr( (string) $logo_info[ 'width' ] ) . '" height="' . esc_attr( (string) $logo_info[ 'height' ] ) . '"' : ''; ?> decoding="sync" fetchpriority="high">

            <div class="title-cont">
                <h1><?php esc_html_e( 'Role Visibility', 'role-visibility' ); ?></h1>
            </div>

            <div class="tabs-wrapper">
                <?php foreach ( $tabs as $key => $tab ) : ?>
                    <a href="<?php echo esc_url( $tab[ 'url' ] ); ?>" class="<?php echo esc_attr( $active === $key ? 'rolevisibility-tab rolevisibility-tab-active' : 'rolevisibility-tab' ); ?>"><?php echo esc_html( $tab[ 'label' ] ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="rolevisibility-subheader">
            <div class="subheader-left">
                <h2 class="tab-title"><?php echo esc_html( $tabs[ $active ][ 'label' ] ?? '' ); ?></h2>
                <?php do_action( 'rolevisibility_subheader_left', $active ); ?>
            </div>
            <div class="subheader-right">
                <?php do_action( 'rolevisibility_subheader_right', $active ); ?>
            </div>
        </div>
        <?php
    } // End maybe_render_header()


    /**
     * Get the header background color: Admin Help Docs' color when it is active, otherwise the default.
     *
     * @return string Hex color.
     */
    private static function header_color() : string {
        $color = '#1D2327';

        if ( class_exists( '\\PluginRx\\AdminHelpDocs\\Colors' ) && method_exists( '\\PluginRx\\AdminHelpDocs\\Colors', 'get' ) ) {
            $color = \PluginRx\AdminHelpDocs\Colors::get( 'header_bg' ) ?: $color;
        }

        $color = (string) apply_filters( 'rolevisibility_header_color', $color );

        return sanitize_hex_color( $color ) ?: '#1D2327';
    } // End header_color()


    /**
     * Relative luminance of an sRGB color, 0 to 1.
     *
     * @param int $r
     * @param int $g
     * @param int $b
     * @return float
     */
    private static function luminance( int $r, int $g, int $b ) : float {
        $weights = [ 0.2126, 0.7152, 0.0722 ];
        $total = 0.0;

        foreach ( [ $r, $g, $b ] as $index => $value ) {
            $channel = $value / 255;
            $total += $weights[ $index ] * ( $channel <= 0.03928 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4 );
        }

        return $total;
    } // End luminance()


    /**
     * Get the header logo URL.
     *
     * @return string
     */
    private static function logo_url() : string {
        return (string) apply_filters( 'rolevisibility_logo', add_query_arg( 'ver', Bootstrap::script_version(), Bootstrap::url() . 'inc/img/logo.png' ) );
    } // End logo_url()


    /**
     * Get the width, height and average luminance of a local logo image. Cached until the file changes.
     *
     * Luminance is weighted by opacity. Values are null when the logo cannot be read.
     *
     * @param string $url
     * @return array [ width, height, lum ]
     */
    private static function logo_info( string $url ) : array {
        $info = [ 'width' => null, 'height' => null, 'lum' => null, 'data' => '' ];
        $uploads = wp_upload_dir( null, false );
        $path = '';

        if ( 0 === strpos( $url, Bootstrap::url() ) ) {
            $path = Bootstrap::dir() . ltrim( substr( strtok( $url, '?' ), strlen( Bootstrap::url() ) ), '/' );
        } elseif ( 0 === strpos( $url, $uploads[ 'baseurl' ] ) ) {
            $path = $uploads[ 'basedir' ] . substr( strtok( $url, '?' ), strlen( $uploads[ 'baseurl' ] ) );
        }

        if ( '' === $path || ! is_readable( $path ) ) {
            return $info;
        }

        $key = md5( '2|' . $path . filemtime( $path ) );
        $cached = get_transient( 'rolevisibility_logo_info' );

        if ( is_array( $cached ) && ( $cached[ 'key' ] ?? '' ) === $key ) {
            return array_merge( $info, (array) $cached[ 'info' ] );
        }

        $type = wp_check_filetype( $path )[ 'type' ];
        $raw = filesize( $path ) <= 24 * KB_IN_BYTES ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local file.

        if ( $raw && $type && 0 === strpos( $type, 'image/' ) ) {
            $info[ 'data' ] = 'data:' . $type . ';base64,' . base64_encode( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- embeds a small local image.
        }

        $size = wp_getimagesize( $path );

        if ( $size ) {
            $info[ 'width' ] = (int) $size[ 0 ];
            $info[ 'height' ] = (int) $size[ 1 ];
        }

        $data = filesize( $path ) <= 2 * MB_IN_BYTES && function_exists( 'imagecreatefromstring' ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local file.
        $source = $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unsupported image data returns false.

        if ( $source ) {
            $sample = 32;
            $small = imagecreatetruecolor( $sample, $sample );

            imagealphablending( $small, false );
            imagesavealpha( $small, true );
            imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 0, 0, 0, 127 ) );
            imagecopyresampled( $small, $source, 0, 0, 0, 0, $sample, $sample, imagesx( $source ), imagesy( $source ) );

            $total = 0.0;
            $weight = 0.0;

            for ( $y = 0; $y < $sample; $y++ ) {
                for ( $x = 0; $x < $sample; $x++ ) {
                    $rgba = imagecolorat( $small, $x, $y );
                    $alpha = 1 - ( ( $rgba >> 24 ) & 127 ) / 127;

                    $total += self::luminance( ( $rgba >> 16 ) & 255, ( $rgba >> 8 ) & 255, $rgba & 255 ) * $alpha;
                    $weight += $alpha;
                }
            }

            $info[ 'lum' ] = $weight > 0 ? $total / $weight : null;
        }

        set_transient( 'rolevisibility_logo_info', [ 'key' => $key, 'info' => $info ], MONTH_IN_SECONDS );

        return $info;
    } // End logo_info()


    /**
     * Check whether the logo has too little contrast against the header and should be inverted.
     *
     * @param string $url
     * @param float|null $lum
     * @return bool
     */
    private static function logo_needs_invert( string $url, ?float $lum ) : bool {
        if ( null === $lum ) {
            return (bool) apply_filters( 'rolevisibility_invert_logo', false, $url );
        }

        $hex = ltrim( self::header_color(), '#' );
        $hex = 3 === strlen( $hex ) ? preg_replace( '/(.)/', '$1$1', $hex ) : $hex;
        $bg = self::luminance( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
        $ratio = ( max( $lum, $bg ) + 0.05 ) / ( min( $lum, $bg ) + 0.05 );

        return (bool) apply_filters( 'rolevisibility_invert_logo', $ratio < 3, $url );
    } // End logo_needs_invert()


    /**
     * Preload the logo so it is ready when the header renders.
     *
     * @return void
     */
    public function preload_logo() : void {
        $logo = self::logo_url();

        if ( ! Helpers::is_plugin_screen() || self::logo_info( $logo )[ 'data' ] ) {
            return;
        }
        ?>
        <link rel="preload" as="image" href="<?php echo esc_url( $logo ); ?>">
        <?php
    } // End preload_logo()


    /**
     * Render the Add New button on the Templates tab.
     *
     * @param string $active_tab
     * @return void
     */
    public function render_templates_buttons( string $active_tab ) : void {
        if ( 'templates' !== $active_tab ) {
            return;
        }
        ?>
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Templates::POST_TYPE ) ); ?>" class="rolevisibility-button"><?php esc_html_e( '+ Add New', 'role-visibility' ); ?></a>
        <?php
    } // End render_templates_buttons()


    /**
     * Render the plugin version in the right subheader.
     *
     * @param string $active_tab
     * @return void
     */
    public function render_version( $active_tab = '' ) : void {
        if ( 'templates' === $active_tab ) {
            return;
        }
        ?>
        <span id="rolevisibility-version"><?php esc_html_e( 'Version', 'role-visibility' ); ?> <?php echo esc_html( Bootstrap::version() ); ?></span>
        <?php
    } // End render_version()


    /**
     * Render the guide, docs and support buttons in the right subheader.
     *
     * @param string $active_tab
     * @return void
     */
    public function render_links( $active_tab = '' ) : void {
        if ( 'templates' === $active_tab ) {
            return;
        }

        foreach ( PluginPage::links() as $link ) {
            ?>
            <a href="<?php echo esc_url( $link[ 'url' ] ); ?>" target="_blank" rel="noopener noreferrer" class="rolevisibility-button rolevisibility-external-link"><?php echo esc_html( $link[ 'label' ] ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
            <?php
        }
    } // End render_links()


    /**
     * Enqueue the shared admin stylesheet on the plugin's screens.
     *
     * @return void
     */
    public function enqueue_shared_assets() : void {
        if ( ! Helpers::is_plugin_screen() ) {
            return;
        }

        wp_enqueue_style( 'rolevisibility-theme', Bootstrap::url() . 'inc/css/theme.css', [], Bootstrap::script_version() );
    } // End enqueue_shared_assets()

} // End class Menu

Menu::instance();

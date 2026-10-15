<?php
/**
 * SETTINGS
 *
 * Option registry, getters, sanitizing, and the Settings tab, saved over AJAX.
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class Settings {

    /**
     * Option name prefix.
     */
    public const PREFIX = 'rolevisibility_';


    /**
     * Nonce action shared by the Settings tab requests.
     */
    public const NONCE = 'rolevisibility_settings_nonce';


    /**
     * Access values allowed as a post type default.
     */
    public const DEFAULT_ACCESS = [ 'everyone', 'logged-in', 'logged-out' ];


    /**
     * Roles included before the setting is first saved.
     */
    public const DEFAULT_ROLES = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];


    /**
     * @var array Resolved values for the current request
     */
    private static array $cache = [];


    /**
     * @var Settings|null Singleton instance
     */
    private static ?Settings $instance = null;


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
        add_action( 'wp_ajax_rolevisibility_save_settings', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_rolevisibility_create_page', [ $this, 'ajax_create_page' ] );
        add_action( 'rolevisibility_subheader_left', [ $this, 'render_subheader_buttons' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    } // End __construct()


    /**
     * Setting definitions, keyed without the option prefix.
     *
     * @return array
     */
    public static function fields() : array {
        $fields = [
            'no_access_page'          => [ 'type' => 'int', 'default' => 0, 'autoload' => true ],
            'login_url'               => [ 'type' => 'url', 'default' => '', 'autoload' => true ],
            'register_url'            => [ 'type' => 'url', 'default' => '', 'autoload' => true ],
            'admin_bypass'            => [ 'type' => 'bool', 'default' => 0, 'autoload' => true ],
            'post_types'              => [ 'type' => 'post_types', 'default' => null, 'autoload' => true ],
            'roles'                   => [ 'type' => 'roles', 'default' => null, 'autoload' => true ],
            'hide'                    => [ 'type' => 'contexts', 'default' => [ 'archives', 'search', 'feeds', 'rest', 'nav_menus', 'adjacent' ], 'autoload' => true ],
            'clear_data_on_uninstall' => [ 'type' => 'bool', 'default' => 0, 'autoload' => false ],
        ];

        return apply_filters( 'rolevisibility_settings_fields', $fields );
    } // End fields()


    /**
     * Get every option name managed by the Settings tab.
     *
     * @return array
     */
    public static function option_names() : array {
        return array_map( fn( $key ) => self::PREFIX . $key, array_keys( self::fields() ) );
    } // End option_names()


    /**
     * Get a setting value.
     *
     * @param string $key Key without the option prefix.
     * @return mixed
     */
    public static function get( string $key ) : mixed {
        if ( array_key_exists( $key, self::$cache ) ) {
            return self::$cache[ $key ];
        }

        $fields = self::fields();

        if ( ! isset( $fields[ $key ] ) ) {
            return null;
        }

        $value = get_option( self::PREFIX . $key, null );

        if ( null === $value ) {
            $value = self::default( $key );
        }

        if ( 'post_types' === $key ) {
            $value = (array) apply_filters( 'rolevisibility_post_types', (array) $value );
        } elseif ( 'roles' === $key ) {
            $value = (array) apply_filters( 'rolevisibility_roles', (array) $value );
        }

        if ( did_action( 'init' ) ) {
            self::$cache[ $key ] = $value;
        }

        return $value;
    } // End get()


    /**
     * Get a setting's default value.
     *
     * @param string $key
     * @return mixed
     */
    public static function default( string $key ) : mixed {
        if ( 'post_types' === $key ) {
            return array_fill_keys( array_keys( Helpers::public_post_types() ), 'everyone' );
        }

        if ( 'roles' === $key ) {
            return array_values( array_intersect( self::DEFAULT_ROLES, array_keys( Helpers::all_roles() ) ) );
        }

        return self::fields()[ $key ][ 'default' ] ?? null;
    } // End default()


    /**
     * Sanitize a value for a setting.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public static function sanitize( string $key, mixed $value ) : mixed {
        $field = self::fields()[ $key ] ?? [];

        if ( isset( $field[ 'sanitize' ] ) && is_callable( $field[ 'sanitize' ] ) ) {
            return call_user_func( $field[ 'sanitize' ], $value );
        }

        switch ( $field[ 'type' ] ?? '' ) {
            case 'int':
                return absint( $value );

            case 'url':
                return is_string( $value ) ? esc_url_raw( trim( $value ) ) : '';

            case 'bool':
                return empty( $value ) ? 0 : 1;

            case 'post_types':
                $clean = [];

                foreach ( (array) $value as $post_type => $access ) {
                    $post_type = sanitize_key( $post_type );

                    if ( '' !== $post_type && post_type_exists( $post_type ) ) {
                        $clean[ $post_type ] = in_array( $access, self::DEFAULT_ACCESS, true ) ? $access : 'everyone';
                    }
                }

                return $clean;

            case 'roles':
                return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), array_keys( Helpers::all_roles() ) ) );

            case 'contexts':
                return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), array_keys( Hide::contexts() ) ) );
        }

        return is_string( $value ) ? sanitize_text_field( $value ) : '';
    } // End sanitize()


    /**
     * Sanitize and store one setting.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public static function update( string $key, mixed $value ) : void {
        $fields = self::fields();

        if ( ! isset( $fields[ $key ] ) ) {
            return;
        }

        update_option( self::PREFIX . $key, self::sanitize( $key, $value ), ! empty( $fields[ $key ][ 'autoload' ] ) );
        unset( self::$cache[ $key ] );
    } // End update()


    /**
     * Sanitize and store several settings at once. Keys that are not provided are left alone.
     *
     * @param array $values Values keyed without the option prefix, in stored format.
     * @return void
     */
    public static function save( array $values ) : void {
        foreach ( array_keys( self::fields() ) as $key ) {
            if ( array_key_exists( $key, $values ) ) {
                self::update( $key, $values[ $key ] );
            }
        }

        do_action( 'rolevisibility_settings_saved', $values );
    } // End save()


    /**
     * Delete every setting so defaults apply again.
     *
     * @return void
     */
    public static function reset() : void {
        foreach ( self::option_names() as $option ) {
            delete_option( $option );
        }

        self::$cache = [];

        do_action( 'rolevisibility_settings_reset' );
    } // End reset()


    /**
     * Clear the request cache, used after options are changed outside this class.
     *
     * @return void
     */
    public static function flush_cache() : void {
        self::$cache = [];
    } // End flush_cache()


    /**
     * Get the URL of the Settings tab.
     *
     * @return string
     */
    public static function url() : string {
        return admin_url( 'admin.php?page=' . Menu::SLUG );
    } // End url()


    /**
     * Check whether the current screen is the Settings tab.
     *
     * @return bool
     */
    private static function is_settings_screen() : bool {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return $screen && Menu::settings_screen_id() === $screen->id;
    } // End is_settings_screen()


    /**
     * Enqueue assets on the Settings tab.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( ! self::is_settings_screen() ) {
            return;
        }

        wp_enqueue_script( 'rolevisibility-settings', Bootstrap::url() . 'inc/js/settings.js', [ 'jquery' ], Bootstrap::script_version(), true );

        wp_localize_script( 'rolevisibility-settings', 'roleVisibilitySettings', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( self::NONCE ),
            'savingText'    => __( 'Saving', 'role-visibility' ),
            'savedText'     => __( 'Settings saved successfully.', 'role-visibility' ),
            'errorText'     => __( 'Error saving settings. Please try again.', 'role-visibility' ),
            'importOk'      => __( 'Settings imported. Reloading…', 'role-visibility' ),
            'importError'   => __( 'That file is not a valid Role Visibility export.', 'role-visibility' ),
            'resetOk'       => __( 'Settings reset. Reloading…', 'role-visibility' ),
            'pageCreated'   => __( 'No Access Page created and saved.', 'role-visibility' ),
            'confirmReset'  => __( 'Reset all Role Visibility settings to their defaults? Your templates and the access rules on your posts are not affected.', 'role-visibility' ),
            'confirmImport' => __( 'Importing will overwrite your current Role Visibility settings. Continue?', 'role-visibility' ),
            'confirmMenus'  => __( 'Import the visibility rules from Nav Menu Roles into Role Visibility? Existing Role Visibility menu rules on the same items will be overwritten and Nav Menu Roles will be deactivated.', 'role-visibility' ),
            'exportName'    => 'role-visibility-settings-' . gmdate( 'Y-m-d' ) . '.json',
        ] );
    } // End enqueue()


    /**
     * Render the Save button and dirty-state reminder in the subheader.
     *
     * @param string $active_tab
     * @return void
     */
    public function render_subheader_buttons( string $active_tab ) : void {
        if ( 'settings' !== $active_tab ) {
            return;
        }
        ?>
        <button type="button" class="rolevisibility-button" id="rolevisibility-save"><?php esc_html_e( 'Save', 'role-visibility' ); ?></button>
        <span id="rolevisibility-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes to your settings.', 'role-visibility' ); ?></span>
        <?php
    } // End render_subheader_buttons()


    /**
     * Render the Settings tab.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'role-visibility' ) );
        }

        $no_access_page = (int) self::get( 'no_access_page' );
        $post_types = self::get( 'post_types' );
        $roles = self::get( 'roles' );
        $hide = self::get( 'hide' );
        $access_options = Access::options();
        unset( $access_options[ 'default' ] );
        ?>
        <div class="wrap rolevisibility-wrap rolevisibility-content-wrap">
            <?php if ( ! $no_access_page || 'publish' !== get_post_status( $no_access_page ) ) : ?>
                <div class="rolevisibility-note rolevisibility-note-warning">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <p><?php esc_html_e( 'Visitors are only redirected once a published No Access Page is selected below. Until then, restricted content stays viewable when visited directly.', 'role-visibility' ); ?></p>
                </div>
            <?php endif; ?>

            <form id="rolevisibility-settings-form" class="rolevisibility-settings-grid" autocomplete="off">

                <div class="rolevisibility-box">
                    <div class="rolevisibility-box-header"><h2><?php esc_html_e( 'General', 'role-visibility' ); ?></h2></div>
                    <div class="rolevisibility-box-body">
                        <div class="rolevisibility-field">
                            <label for="rolevisibility_no_access_page">
                                <?php esc_html_e( 'No Access Page', 'role-visibility' ); ?>
                                <?php
                                Helpers::tooltip( sprintf(
                                    /* translators: %s: The shortcode that displays the access denied template. */
                                    __( 'Visitors are redirected here when they cannot view a page. The page must include the %s shortcode, which displays the matching template.', 'role-visibility' ),
                                    '<code>[role_visibility_msg]</code>'
                                ) );
                                ?>
                            </label>
                            <div class="rolevisibility-inline-actions">
                                <?php
                                wp_dropdown_pages( [
                                    'name'              => 'rolevisibility[no_access_page]',
                                    'id'                => 'rolevisibility_no_access_page',
                                    'selected'          => absint( $no_access_page ),
                                    'show_option_none'  => esc_html__( '— Select a Page —', 'role-visibility' ),
                                    'option_none_value' => 0,
                                ] );
                                ?>
                                <button type="button" class="rolevisibility-button rolevisibility-button-secondary" id="rolevisibility-create-page"<?php echo $no_access_page ? ' hidden' : ''; ?>><?php esc_html_e( 'Create Page', 'role-visibility' ); ?></button>
                            </div>
                        </div>

                        <div class="rolevisibility-field">
                            <label for="rolevisibility_login_url">
                                <?php esc_html_e( 'Login URL', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Used by the login merge tags. Leave blank to use the default WordPress login page. Visitors are sent back to the page they tried to view after logging in.', 'role-visibility' ) ); ?>
                            </label>
                            <input type="url" id="rolevisibility_login_url" name="rolevisibility[login_url]" value="<?php echo esc_attr( self::get( 'login_url' ) ); ?>" placeholder="<?php echo esc_attr( wp_login_url() ); ?>">
                        </div>

                        <div class="rolevisibility-field">
                            <label for="rolevisibility_register_url">
                                <?php esc_html_e( 'Register URL', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Used by the register merge tags. Leave blank to use the default WordPress registration page.', 'role-visibility' ) ); ?>
                            </label>
                            <input type="url" id="rolevisibility_register_url" name="rolevisibility[register_url]" value="<?php echo esc_attr( self::get( 'register_url' ) ); ?>" placeholder="<?php echo esc_attr( wp_registration_url() ); ?>">
                        </div>

                        <div class="rolevisibility-field">
                            <label class="rolevisibility-inline-label">
                                <input type="checkbox" name="rolevisibility[admin_bypass]" value="1" <?php checked( (bool) self::get( 'admin_bypass' ) ); ?>>
                                <?php esc_html_e( 'Administrators Bypass Restrictions', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Lets users who can manage options view all restricted content, including in menus, searches and feeds. When unchecked, administrators follow the same rules as everyone else.', 'role-visibility' ) ); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="rolevisibility-box">
                    <div class="rolevisibility-box-header"><h2><?php esc_html_e( 'Hide Restricted Content', 'role-visibility' ); ?></h2></div>
                    <div class="rolevisibility-box-body">
                        <p class="rolevisibility-section-desc"><?php esc_html_e( 'Restricted content is always protected when visited directly. These options also remove it from the places below for anyone who cannot view it.', 'role-visibility' ); ?></p>
                        <?php foreach ( Hide::contexts() as $key => $context ) : ?>
                            <div class="rolevisibility-field">
                                <label class="rolevisibility-inline-label">
                                    <input type="checkbox" name="rolevisibility[hide][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $hide, true ) ); ?>>
                                    <?php echo esc_html( $context[ 'label' ] ); ?>
                                    <?php Helpers::tooltip( $context[ 'desc' ] ); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rolevisibility-box">
                    <div class="rolevisibility-box-header"><h2><?php esc_html_e( 'Post Types', 'role-visibility' ); ?></h2></div>
                    <div class="rolevisibility-box-body">
                        <p class="rolevisibility-section-desc"><?php esc_html_e( 'Enable access controls for each post type and choose the access its content uses unless a post says otherwise.', 'role-visibility' ); ?></p>
                        <table class="rolevisibility-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Enabled', 'role-visibility' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Post Type', 'role-visibility' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Default Access', 'role-visibility' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( Helpers::public_post_types() as $slug => $object ) : ?>
                                    <?php $field_id = 'rolevisibility_pt_' . $slug; ?>
                                    <tr>
                                        <td><input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="rolevisibility[post_types][<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( isset( $post_types[ $slug ] ) ); ?>></td>
                                        <td><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $object->labels->name ); ?> <code><?php echo esc_html( $slug ); ?></code></label></td>
                                        <td>
                                            <select name="rolevisibility[post_types][<?php echo esc_attr( $slug ); ?>][default]" aria-label="<?php echo esc_attr( $object->labels->name ); ?>">
                                                <?php foreach ( $access_options as $key => $label ) : ?>
                                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $post_types[ $slug ] ?? 'everyone', $key ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rolevisibility-box">
                    <div class="rolevisibility-box-header"><h2><?php esc_html_e( 'Roles', 'role-visibility' ); ?></h2></div>
                    <div class="rolevisibility-box-body">
                        <div class="rolevisibility-field">
                            <label>
                                <?php esc_html_e( 'Roles Available in Access Controls', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Choose which roles can be selected when limiting "Logged-In Only" content, menu items and templates.', 'role-visibility' ) ); ?>
                            </label>
                            <div class="rolevisibility-checkboxes">
                                <?php foreach ( Helpers::all_roles() as $slug => $label ) : ?>
                                    <label>
                                        <input type="checkbox" name="rolevisibility[roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $roles, true ) ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php do_action( 'rolevisibility_settings_boxes' ); ?>

                <div class="rolevisibility-box">
                    <div class="rolevisibility-box-header"><h2><?php esc_html_e( 'Advanced', 'role-visibility' ); ?></h2></div>
                    <div class="rolevisibility-box-body">
                        <div class="rolevisibility-field">
                            <label class="rolevisibility-inline-label">
                                <input type="checkbox" name="rolevisibility[clear_data_on_uninstall]" value="1" <?php checked( (bool) self::get( 'clear_data_on_uninstall' ) ); ?>>
                                <?php esc_html_e( 'Remove All Plugin Data on Uninstall', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Permanently deletes all Role Visibility settings, templates, menu rules and the access rules saved on your posts when the plugin is deleted.', 'role-visibility' ) ); ?>
                            </label>
                        </div>

                        <div class="rolevisibility-field">
                            <label>
                                <?php esc_html_e( 'Download/Upload Settings', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Export the settings on this page as a JSON file for backup or to copy to another site. Templates and the access rules on your posts are not included. Importing overwrites your current settings.', 'role-visibility' ) ); ?>
                            </label>
                            <div class="rolevisibility-inline-actions">
                                <button type="button" class="rolevisibility-button" id="rolevisibility-download-settings"><?php esc_html_e( 'Download Settings', 'role-visibility' ); ?></button>
                                <label for="rolevisibility-upload-settings" class="rolevisibility-button" tabindex="0"><?php esc_html_e( 'Upload Settings', 'role-visibility' ); ?></label>
                                <input type="file" id="rolevisibility-upload-settings" accept="application/json,.json" hidden>
                                <span id="rolevisibility-upload-filename"></span>
                            </div>
                        </div>

                        <?php if ( '' !== NavMenus::active_nav_menu_roles_plugin() ) : ?>
                            <div class="rolevisibility-field">
                                <label>
                                    <?php esc_html_e( 'Import from Nav Menu Roles', 'role-visibility' ); ?>
                                    <?php Helpers::tooltip( __( 'Copies the menu item rules saved by the Nav Menu Roles plugin turns on the Navigation Menus option, and deactivates Nav Menu Roles. Its own data is left untouched.', 'role-visibility' ) ); ?>
                                </label>
                                <div><button type="button" class="rolevisibility-button" id="rolevisibility-import-menus"><?php esc_html_e( 'Import Menu Rules', 'role-visibility' ); ?></button></div>
                            </div>
                        <?php endif; ?>

                        <?php if ( Migrate::has_legacy_data() ) : ?>
                            <div class="rolevisibility-field">
                                <label>
                                    <?php esc_html_e( 'Upgrade from Version 1.x', 'role-visibility' ); ?>
                                    <?php Helpers::tooltip( __( 'Version 1.x settings, access rules or custom messages were found. This converts them to the new format. It runs automatically on update, so you only need it if something was left behind.', 'role-visibility' ) ); ?>
                                </label>
                                <div><button type="button" class="rolevisibility-button" id="rolevisibility-run-migration"><?php esc_html_e( 'Run Migration Again', 'role-visibility' ); ?></button></div>
                            </div>
                        <?php endif; ?>

                        <div class="rolevisibility-field">
                            <label>
                                <?php esc_html_e( 'Reset Settings', 'role-visibility' ); ?>
                                <?php Helpers::tooltip( __( 'Resets every option on this page to its default. Templates and the access rules on your posts are never touched.', 'role-visibility' ) ); ?>
                            </label>
                            <div><button type="button" class="rolevisibility-button rolevisibility-button-danger" id="rolevisibility-reset-settings"><?php esc_html_e( 'Reset Settings', 'role-visibility' ); ?></button></div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
        <?php
    } // End render_page()


    /**
     * Convert the submitted form into stored-format values.
     *
     * @param array $form Unslashed values from the rolevisibility[] inputs.
     * @return array
     */
    private static function form_to_values( array $form ) : array {
        $values = [];

        foreach ( self::fields() as $key => $field ) {
            $raw = $form[ $key ] ?? null;

            if ( 'post_types' === $field[ 'type' ] ) {
                $enabled = [];

                foreach ( (array) $raw as $post_type => $row ) {
                    if ( is_array( $row ) && ! empty( $row[ 'enabled' ] ) ) {
                        $enabled[ $post_type ] = $row[ 'default' ] ?? 'everyone';
                    }
                }

                $values[ $key ] = $enabled;
            } elseif ( in_array( $field[ 'type' ], [ 'roles', 'contexts' ], true ) ) {
                $values[ $key ] = (array) $raw;
            } else {
                $values[ $key ] = $raw ?? '';
            }
        }

        return $values;
    } // End form_to_values()


    /**
     * AJAX: save every field on the Settings tab.
     *
     * @return void
     */
    public function ajax_save() : void {
        Helpers::verify_ajax( self::NONCE );

        $form = isset( $_POST[ 'rolevisibility' ] ) && is_array( $_POST[ 'rolevisibility' ] ) ? wp_unslash( $_POST[ 'rolevisibility' ] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the nonce is verified by Helpers::verify_ajax() before this runs, and every value is sanitized individually in self::sanitize().

        self::save( self::form_to_values( $form ) );

        wp_send_json_success();
    } // End ajax_save()


    /**
     * AJAX: create a published No Access Page containing the shortcode and select it.
     *
     * @return void
     */
    public function ajax_create_page() : void {
        Helpers::verify_ajax( self::NONCE );

        $page_id = wp_insert_post( [
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => __( 'Access Denied', 'role-visibility' ),
            'post_content' => '<!-- wp:shortcode -->[role_visibility_msg]<!-- /wp:shortcode -->',
        ], true );

        if ( is_wp_error( $page_id ) ) {
            wp_send_json_error( $page_id->get_error_message() );
        }

        self::update( 'no_access_page', $page_id );

        wp_send_json_success( [ 'id' => $page_id, 'title' => get_the_title( $page_id ) ] );
    } // End ajax_create_page()

} // End class Settings

Settings::instance();

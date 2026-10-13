<?php
/**
 * MIGRATE
 *
 * First-run setup and the one-time upgrade from version 1.x: options, post meta, and custom
 * messages converted into templates. Runs once on init, on any request, guarded by a lock.
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class Migrate {

    /**
     * Database version this release expects.
     */
    public const VERSION = '2.0.0';


    /**
     * Options used by the migration.
     */
    public const VERSION_OPTION = 'rolevisibility_db_version';
    public const LOCK_OPTION = 'rolevisibility_migration_lock';
    public const NOTICE_TRANSIENT = 'rolevisibility_migration_notice';
    public const ERROR_OPTION = 'rolevisibility_migration_error';
    public const CHECKED_OPTION = 'rolevisibility_legacy_checked';


    /**
     * Version 1.x names.
     */
    public const LEGACY_PREFIX = 'role_visibility_';
    public const LEGACY_META = 'role_visibility';
    public const LEGACY_MSG_META = 'role_visibility_msg';
    public const LEGACY_PLACEHOLDER = '{role_visibility_msg}';


    /**
     * Rows updated per query when rewriting post meta.
     */
    public const CHUNK = 500;


    /**
     * @var Migrate|null Singleton instance
     */
    private static ?Migrate $instance = null;


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
        add_action( 'init', [ $this, 'maybe_run' ], 20 );
        add_action( 'admin_notices', [ $this, 'render_notice' ] );
    } // End __construct()


    /**
     * Run setup or the upgrade on init. Also converts any version 1.x data left behind, once per release.
     *
     * @return void
     */
    public function maybe_run() : void {
        if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
            update_option( self::CHECKED_OPTION, self::VERSION, true );
            self::execute();

            return;
        }

        if ( self::VERSION === get_option( self::CHECKED_OPTION ) ) {
            return;
        }

        update_option( self::CHECKED_OPTION, self::VERSION, true );

        if ( self::has_legacy_data() ) {
            self::execute();
        }
    } // End maybe_run()


    /**
     * Check whether version 1.x options or post meta still exist.
     *
     * @return bool
     */
    public static function has_legacy_data() : bool {
        global $wpdb;

        $option = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1", $wpdb->esc_like( self::LEGACY_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- checks for leftover version 1.x data.
        $meta = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s ) LIMIT 1", self::LEGACY_META, self::LEGACY_MSG_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- checks for leftover version 1.x data.

        return (bool) ( $option || $meta );
    } // End has_legacy_data()


    /**
     * Run the migration under a lock, capturing any error instead of leaving it half done.
     *
     * @param bool $force Ignore a lock left by an earlier run. Used by the manual re-run.
     * @return array Summary, or [ 'error' => message ], or [ 'skipped' => true ] while another request is running it.
     */
    public static function execute( bool $force = false ) : array {
        if ( ! add_option( self::LOCK_OPTION, time(), '', false ) ) {
            if ( ! $force && time() - (int) get_option( self::LOCK_OPTION ) < 10 * MINUTE_IN_SECONDS ) {
                return [ 'skipped' => true ];
            }

            update_option( self::LOCK_OPTION, time(), false );
        }

        try {
            $summary = self::run();
        } catch ( \Throwable $error ) {
            $message = sprintf( '%1$s (%2$s:%3$d)', $error->getMessage(), basename( $error->getFile() ), $error->getLine() );

            update_option( self::ERROR_OPTION, $message, false );
            update_option( self::VERSION_OPTION, self::VERSION, true );
            delete_option( self::LOCK_OPTION );

            return [ 'error' => $message ];
        }

        delete_option( self::ERROR_OPTION );
        update_option( self::VERSION_OPTION, self::VERSION, true );
        delete_option( self::LOCK_OPTION );

        if ( ! empty( $summary[ 'legacy' ] ) ) {
            set_transient( self::NOTICE_TRANSIENT, $summary, WEEK_IN_SECONDS );
        }

        do_action( 'rolevisibility_migrated', $summary );

        return $summary;
    } // End execute()


    /**
     * Perform setup or the upgrade.
     *
     * @return array Summary of what changed.
     */
    private static function run() : array {
        global $wpdb;

        $legacy = [];
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::LEGACY_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration read of version 1.x options.

        foreach ( $rows as $row ) {
            $legacy[ substr( $row->option_name, strlen( self::LEGACY_PREFIX ) ) ] = maybe_unserialize( $row->option_value );
        }

        $has_meta = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s ) LIMIT 1", self::LEGACY_META, self::LEGACY_MSG_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration check.

        if ( empty( $legacy ) && ! $has_meta ) {
            Templates::create_defaults();

            return [ 'legacy' => false ];
        }

        $type_messages = self::migrate_settings( $legacy );
        $posts = self::migrate_access_meta();
        $bases = self::legacy_bases( $legacy );

        $global_contents = [];

        foreach ( array_keys( Templates::conditions() ) as $condition ) {
            $global_contents[ $condition ] = str_replace( self::LEGACY_PLACEHOLDER, wp_kses_post( self::legacy_message( $legacy, $condition ) ), $bases[ $condition ] );
        }

        Templates::create_defaults( $global_contents );

        $templates = self::migrate_messages( $bases, $type_messages );

        foreach ( array_keys( $legacy ) as $key ) {
            delete_option( self::LEGACY_PREFIX . $key );
        }

        delete_transient( 'role_visibility_license_data' );
        delete_transient( 'role_visibility_update_check' );

        Settings::flush_cache();
        Templates::rebuild_index();
        RestrictedIndex::rebuild();

        return [ 'legacy' => true, 'posts' => $posts, 'templates' => $templates ];
    } // End run()


    /**
     * Copy version 1.x settings into the new options.
     *
     * @param array $legacy Version 1.x options keyed without the prefix.
     * @return array Per post type messages: [ post_type => [ logged_in_msg, logged_out_msg ] ]
     */
    private static function migrate_settings( array $legacy ) : array {
        $values = [];
        $type_messages = [];

        if ( isset( $legacy[ 'no_access_page' ] ) ) {
            $values[ 'no_access_page' ] = absint( $legacy[ 'no_access_page' ] );
        }

        foreach ( [ 'login_url' => wp_login_url(), 'register_url' => wp_registration_url() ] as $key => $wp_default ) {
            if ( isset( $legacy[ $key ] ) && is_string( $legacy[ $key ] ) && $legacy[ $key ] !== $wp_default ) {
                $values[ $key ] = $legacy[ $key ];
            }
        }

        if ( isset( $legacy[ 'uninstall_reset' ] ) ) {
            $values[ 'clear_data_on_uninstall' ] = $legacy[ 'uninstall_reset' ];
        }

        if ( is_array( $legacy[ 'post_types' ] ?? null ) && ! empty( $legacy[ 'post_types' ] ) ) {
            $values[ 'post_types' ] = [];

            foreach ( $legacy[ 'post_types' ] as $row ) {
                if ( empty( $row[ 'post_type' ] ) ) {
                    continue;
                }

                $values[ 'post_types' ][ $row[ 'post_type' ] ] = $row[ 'default' ] ?? 'everyone';
                $type_messages[ $row[ 'post_type' ] ] = [
                    'logged_in_msg'  => trim( (string) ( $row[ 'logged_in_msg' ] ?? '' ) ),
                    'logged_out_msg' => trim( (string) ( $row[ 'logged_out_msg' ] ?? '' ) ),
                ];
            }
        }

        if ( is_array( $legacy[ 'roles' ] ?? null ) && ! empty( $legacy[ 'roles' ] ) ) {
            $values[ 'roles' ] = array_filter( array_map( fn( $row ) => $row[ 'role' ] ?? '', $legacy[ 'roles' ] ) );
        }

        Settings::save( $values );
        Settings::flush_cache();

        return $type_messages;
    } // End migrate_settings()


    /**
     * Normalize a version 1.x rule string to the access the editor actually chose.
     *
     * Version 1.x stored the dropdown value plus any role boxes that were checked, even when the boxes
     * were hidden, so roles only count alongside "logged-in".
     *
     * @param string $value
     * @return string New rule string, or "default" to remove it.
     */
    public static function normalize_legacy_rule( string $value ) : string {
        $tokens = array_values( array_filter( array_map( 'trim', explode( ',', html_entity_decode( $value, ENT_QUOTES ) ) ) ) );

        if ( in_array( 'logged-in', $tokens, true ) ) {
            $roles = array_diff( $tokens, [ 'logged-in', 'logged-out', 'everyone', 'default' ] );

            return Access::build( 'logged-in', array_values( array_map( 'sanitize_key', $roles ) ) );
        }

        foreach ( [ 'logged-out', 'everyone' ] as $access ) {
            if ( in_array( $access, $tokens, true ) ) {
                return $access;
            }
        }

        return 'default';
    } // End normalize_legacy_rule()


    /**
     * Rename and normalize the version 1.x access meta with a few grouped queries.
     *
     * @return int Number of posts with a rule kept.
     */
    private static function migrate_access_meta() : int {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::LEGACY_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration of version 1.x post meta.
        $groups = [];

        foreach ( $rows as $row ) {
            $groups[ self::normalize_legacy_rule( (string) $row->meta_value ) ][] = (int) $row->meta_id;
        }

        $kept = 0;

        foreach ( $groups as $value => $meta_ids ) {
            foreach ( array_chunk( $meta_ids, self::CHUNK ) as $chunk ) {
                $id_list = implode( ',', array_map( 'absint', $chunk ) );

                if ( 'default' === $value ) {
                    $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ( {$id_list} )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-time migration; the ID list is built from absint() values.
                } else {
                    $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = %s, meta_value = %s WHERE meta_id IN ( {$id_list} )", Access::META_KEY, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-time migration; the ID list is built from absint() values.
                    $kept += count( $chunk );
                }
            }
        }

        foreach ( $rows as $row ) {
            wp_cache_delete( (int) $row->post_id, 'post_meta' );
        }

        return $kept;
    } // End migrate_access_meta()


    /**
     * Get the version 1.x template for each condition, with the message placeholder left in place.
     *
     * @param array $legacy
     * @return array
     */
    private static function legacy_bases( array $legacy ) : array {
        $keys = [
            'login_required'  => 'logged_in_template',
            'missing_access'  => 'missing_role_template',
            'logout_required' => 'logged_out_template',
        ];

        $bases = [];

        foreach ( $keys as $condition => $key ) {
            $template = is_string( $legacy[ $key ] ?? null ) ? trim( $legacy[ $key ] ) : '';
            $bases[ $condition ] = '' !== $template ? $template : Templates::default_content( $condition, self::LEGACY_PLACEHOLDER );
        }

        return $bases;
    } // End legacy_bases()


    /**
     * Get the version 1.x global message for a condition.
     *
     * @param array  $legacy
     * @param string $condition
     * @return string
     */
    private static function legacy_message( array $legacy, string $condition ) : string {
        $keys = [
            'login_required'  => 'logged_in_msg',
            'missing_access'  => 'missing_role_msg',
            'logout_required' => 'logged_out_msg',
        ];

        $message = is_string( $legacy[ $keys[ $condition ] ] ?? null ) ? trim( $legacy[ $keys[ $condition ] ] ) : '';

        return '' !== $message ? $message : Templates::default_message( $condition );
    } // End legacy_message()


    /**
     * Turn per post type and per post messages into templates, grouping identical content.
     *
     * @param array $bases         Version 1.x templates per condition.
     * @param array $type_messages Per post type messages.
     * @return int Number of templates created.
     */
    private static function migrate_messages( array $bases, array $type_messages ) : int {
        global $wpdb;

        $groups = [];
        $add = function( string $scope, $target, array $conditions, string $message ) use ( &$groups, $bases ) : void {
            foreach ( $conditions as $condition ) {
                $content = str_replace( self::LEGACY_PLACEHOLDER, wp_kses_post( $message ), $bases[ $condition ] );
                $key = md5( $scope . '|' . $content );

                $groups[ $key ] ??= [ 'scope' => $scope, 'content' => $content, 'message' => $message, 'conditions' => [], 'targets' => [] ];
                $groups[ $key ][ 'conditions' ][ $condition ] = $condition;
                $groups[ $key ][ 'targets' ][ $target ] = $target;
            }
        };

        foreach ( $type_messages as $post_type => $messages ) {
            if ( '' !== $messages[ 'logged_in_msg' ] ) {
                $add( 'post_type', $post_type, [ 'login_required', 'missing_access' ], $messages[ 'logged_in_msg' ] );
            }

            if ( '' !== $messages[ 'logged_out_msg' ] ) {
                $add( 'post_type', $post_type, [ 'logout_required' ], $messages[ 'logged_out_msg' ] );
            }
        }

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", self::LEGACY_MSG_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration of version 1.x post meta.

        foreach ( $rows as $row ) {
            $post_id = (int) $row->post_id;
            $rule = Access::get_rule( $post_id );

            if ( 'logged-out' === $rule[ 'access' ] ) {
                $conditions = [ 'logout_required' ];
            } elseif ( 'logged-in' === $rule[ 'access' ] ) {
                $conditions = empty( $rule[ 'roles' ] ) && empty( $rule[ 'users' ] ) ? [ 'login_required' ] : [ 'login_required', 'missing_access' ];
            } else {
                continue;
            }

            $add( 'posts', $post_id, $conditions, trim( (string) $row->meta_value ) );
        }

        $created = 0;
        $labels = Templates::conditions();

        foreach ( $groups as $group ) {
            if ( 'post_type' === $group[ 'scope' ] ) {
                $name = implode( ', ', array_map( fn( $slug ) => get_post_type_object( $slug )?->labels->name ?? $slug, array_values( $group[ 'targets' ] ) ) );
            } else {
                $name = wp_trim_words( wp_strip_all_tags( $group[ 'message' ] ), 6 );
            }

            /* translators: 1: Post type names or the first words of the custom message, 2: When the template applies. */
            $title = sprintf( __( 'Custom Message: %1$s (%2$s)', 'role-visibility' ), $name, implode( ', ', array_intersect_key( $labels, $group[ 'conditions' ] ) ) );

            if ( Templates::create( $title, $group[ 'content' ], $group[ 'scope' ], array_values( $group[ 'conditions' ] ), array_values( $group[ 'targets' ] ) ) ) {
                $created++;
            }
        }

        delete_post_meta_by_key( self::LEGACY_MSG_META );

        return $created;
    } // End migrate_messages()


    /**
     * Show a one-time summary after upgrading from version 1.x.
     *
     * @return void
     */
    public function render_notice() : void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $error = (string) get_option( self::ERROR_OPTION, '' );

        if ( '' !== $error && Helpers::is_plugin_screen() ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    /* translators: %s: Error message. */
                    echo esc_html( sprintf( __( 'Role Visibility could not finish upgrading your version 1.x data: %s', 'role-visibility' ), $error ) );
                    ?>
                    <a href="<?php echo esc_url( Settings::url() ); ?>"><?php esc_html_e( 'Try again under Settings > Advanced', 'role-visibility' ); ?></a>
                </p>
            </div>
            <?php
        }

        $summary = get_transient( self::NOTICE_TRANSIENT );

        if ( ! is_array( $summary ) ) {
            return;
        }

        delete_transient( self::NOTICE_TRANSIENT );

        $message = sprintf(
            /* translators: 1: Number of posts with access rules, 2: Number of templates created from custom messages. */
            __( 'Role Visibility 2.0 upgraded your settings, kept the access rules on %1$d posts, and created %2$d templates from your custom messages. Messages are now edited as templates.', 'role-visibility' ),
            (int) ( $summary[ 'posts' ] ?? 0 ),
            (int) ( $summary[ 'templates' ] ?? 0 )
        );
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $message ); ?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Templates::POST_TYPE ) ); ?>"><?php esc_html_e( 'Review your templates', 'role-visibility' ); ?></a></p>
        </div>
        <?php
    } // End render_notice()

} // End class Migrate

Migrate::instance();

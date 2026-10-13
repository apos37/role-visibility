<?php
/**
 * ADVANCED
 *
 * Export, import and reset handlers for the Advanced panel on the Settings tab.
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class Advanced {

    /**
     * Current export format version. Bump when the export shape changes.
     */
    public const EXPORT_VERSION = 1;


    /**
     * @var Advanced|null Singleton instance
     */
    private static ?Advanced $instance = null;


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
        add_action( 'wp_ajax_rolevisibility_export', [ $this, 'ajax_export' ] );
        add_action( 'wp_ajax_rolevisibility_import', [ $this, 'ajax_import' ] );
        add_action( 'wp_ajax_rolevisibility_reset', [ $this, 'ajax_reset' ] );
        add_action( 'wp_ajax_rolevisibility_migrate', [ $this, 'ajax_migrate' ] );
    } // End __construct()


    /**
     * AJAX: return the export payload for the browser to download.
     *
     * @return void
     */
    public function ajax_export() : void {
        Helpers::verify_ajax( Settings::NONCE );

        $settings = [];

        foreach ( array_keys( Settings::fields() ) as $key ) {
            $value = get_option( Settings::PREFIX . $key, null );

            if ( null !== $value ) {
                $settings[ $key ] = $value;
            }
        }

        wp_send_json_success( apply_filters( 'rolevisibility_export_payload', [
            'plugin'         => 'role-visibility',
            'export_version' => self::EXPORT_VERSION,
            'plugin_version' => Bootstrap::version(),
            'exported_at'    => gmdate( 'c' ),
            'settings'       => $settings,
        ] ) );
    } // End ajax_export()


    /**
     * AJAX: import settings from an uploaded export.
     *
     * @return void
     */
    public function ajax_import() : void {
        Helpers::verify_ajax( Settings::NONCE );

        $raw = isset( $_POST[ 'payload' ] ) ? wp_unslash( $_POST[ 'payload' ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the nonce is verified by Helpers::verify_ajax() above, JSON is decoded and every setting is sanitized by Settings::save().
        $payload = is_string( $raw ) ? json_decode( $raw, true ) : null;

        if ( ! is_array( $payload ) || 'role-visibility' !== ( $payload[ 'plugin' ] ?? '' ) || ! is_array( $payload[ 'settings' ] ?? null ) ) {
            wp_send_json_error( __( 'That file is not a valid Role Visibility export.', 'role-visibility' ) );
        }

        Settings::save( array_intersect_key( $payload[ 'settings' ], Settings::fields() ) );

        do_action( 'rolevisibility_settings_imported', $payload );

        wp_send_json_success();
    } // End ajax_import()


    /**
     * AJAX: reset every setting to its default.
     *
     * @return void
     */
    public function ajax_reset() : void {
        Helpers::verify_ajax( Settings::NONCE );

        Settings::reset();

        wp_send_json_success();
    } // End ajax_reset()

    /**
     * AJAX: run the version 1.x migration again.
     *
     * @return void
     */
    public function ajax_migrate() : void {
        Helpers::verify_ajax( Settings::NONCE );

        $summary = Migrate::execute( true );

        if ( ! empty( $summary[ 'error' ] ) ) {
            wp_send_json_error( sprintf(
                /* translators: %s: Error message. */
                __( 'The migration failed: %s', 'role-visibility' ),
                $summary[ 'error' ]
            ) );
        }

        if ( ! empty( $summary[ 'skipped' ] ) ) {
            wp_send_json_error( __( 'The migration is already running. Try again in a moment.', 'role-visibility' ) );
        }

        wp_send_json_success( sprintf(
            /* translators: 1: Number of posts with access rules, 2: Number of templates created from custom messages. */
            __( 'Migration finished. Kept the access rules on %1$d posts and created %2$d templates. Reloading…', 'role-visibility' ),
            (int) ( $summary[ 'posts' ] ?? 0 ),
            (int) ( $summary[ 'templates' ] ?? 0 )
        ) );
    } // End ajax_migrate()

} // End class Advanced

Advanced::instance();

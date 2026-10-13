<?php
/**
 * ACCESS
 *
 * Access rules: parsing, evaluation, the No Access redirect, and saving rules from the editor screens.
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class Access {

    /**
     * Post meta key holding a post's rule, e.g. "logged-in, editor, user:12".
     */
    public const META_KEY = '_rolevisibility_access';


    /**
     * Nonce action used by the meta box, Quick Edit and Bulk Edit.
     */
    public const NONCE = 'rolevisibility_save_access';


    /**
     * @var array Parsed rules for the current request, keyed by post ID
     */
    private static array $rules = [];


    /**
     * @var array Access results for the current request, keyed by "post_id|user_id"
     */
    private static array $results = [];


    /**
     * @var Access|null Singleton instance
     */
    private static ?Access $instance = null;


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
        add_action( 'template_redirect', [ $this, 'maybe_redirect' ] );
        add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
    } // End __construct()


    /**
     * Access choices and their labels.
     *
     * @return array
     */
    public static function options() : array {
        $options = [
            'default'    => __( 'Default', 'role-visibility' ),
            'everyone'   => __( 'Everyone', 'role-visibility' ),
            'logged-in'  => __( 'Logged-In Only', 'role-visibility' ),
            'logged-out' => __( 'Logged-Out Only', 'role-visibility' ),
        ];

        return array_merge( $options, array_intersect_key( (array) apply_filters( 'rolevisibility_access_options', $options ), $options ) );
    } // End options()


    /**
     * Roles that can be chosen in access controls.
     *
     * @return array Associative array of slug => label.
     */
    public static function selectable_roles() : array {
        return array_intersect_key( Helpers::all_roles(), array_flip( (array) Settings::get( 'roles' ) ) );
    } // End selectable_roles()


    /**
     * Check whether access controls are enabled for a post type.
     *
     * @param string $post_type
     * @return bool
     */
    public static function is_enabled( string $post_type ) : bool {
        return isset( Settings::get( 'post_types' )[ $post_type ] );
    } // End is_enabled()


    /**
     * Get the default access for a post type.
     *
     * @param string $post_type
     * @return string
     */
    public static function post_type_default( string $post_type ) : string {
        return Settings::get( 'post_types' )[ $post_type ] ?? 'everyone';
    } // End post_type_default()


    /**
     * Parse a stored rule string.
     *
     * @param string $raw
     * @return array [ access, roles, users ]
     */
    public static function parse( string $raw ) : array {
        $tokens = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $flags = [];
        $roles = [];
        $users = [];

        foreach ( $tokens as $token ) {
            if ( in_array( $token, [ 'default', 'everyone', 'logged-in', 'logged-out' ], true ) ) {
                $flags[ $token ] = true;
            } elseif ( str_starts_with( $token, 'user:' ) ) {
                $users[] = absint( substr( $token, 5 ) );
            } else {
                $roles[] = sanitize_key( $token );
            }
        }

        $users = array_values( array_unique( array_filter( $users ) ) );
        $roles = array_values( array_unique( array_filter( $roles ) ) );

        if ( isset( $flags[ 'logged-out' ] ) ) {
            return [ 'access' => 'logged-out', 'roles' => [], 'users' => [] ];
        }

        if ( isset( $flags[ 'logged-in' ] ) || ! empty( $roles ) || ! empty( $users ) ) {
            return [ 'access' => 'logged-in', 'roles' => $roles, 'users' => $users ];
        }

        return [ 'access' => isset( $flags[ 'everyone' ] ) ? 'everyone' : 'default', 'roles' => [], 'users' => [] ];
    } // End parse()


    /**
     * Build a stored rule string.
     *
     * @param string $access
     * @param array  $roles
     * @param array  $users
     * @return string
     */
    public static function build( string $access, array $roles = [], array $users = [] ) : string {
        if ( 'logged-in' !== $access ) {
            return $access;
        }

        $users = array_map( fn( $id ) => 'user:' . absint( $id ), $users );

        return implode( ', ', array_merge( [ 'logged-in' ], $roles, $users ) );
    } // End build()


    /**
     * Get the effective rule for a post, with the post type default applied.
     *
     * @param int $post_id
     * @return array [ access, roles, users, source ]
     */
    public static function get_rule( int $post_id ) : array {
        if ( isset( self::$rules[ $post_id ] ) ) {
            return self::$rules[ $post_id ];
        }

        $post_type = (string) get_post_type( $post_id );

        if ( '' === $post_type || ! self::is_enabled( $post_type ) ) {
            $rule = [ 'access' => 'everyone', 'roles' => [], 'users' => [], 'source' => 'disabled' ];
        } else {
            $rule = self::parse( (string) get_post_meta( $post_id, self::META_KEY, true ) );
            $rule[ 'source' ] = 'post';

            if ( 'default' === $rule[ 'access' ] ) {
                $rule = [ 'access' => self::post_type_default( $post_type ), 'roles' => [], 'users' => [], 'source' => 'post_type' ];
            }
        }

        self::$rules[ $post_id ] = (array) apply_filters( 'rolevisibility_access_rule', $rule, $post_id );

        return self::$rules[ $post_id ];
    } // End get_rule()


    /**
     * Check whether a post is the No Access Page, which is always viewable.
     *
     * @param int $post_id
     * @return bool
     */
    public static function is_no_access_page( int $post_id ) : bool {
        return $post_id > 0 && (int) Settings::get( 'no_access_page' ) === $post_id;
    } // End is_no_access_page()


    /**
     * Check whether a user skips every restriction.
     *
     * @param int $user_id
     * @return bool
     */
    public static function user_bypasses( int $user_id ) : bool {
        static $cache = [];

        if ( ! isset( $cache[ $user_id ] ) ) {
            $bypass = $user_id > 0 && Settings::get( 'admin_bypass' ) && user_can( $user_id, 'manage_options' );
            $cache[ $user_id ] = (bool) apply_filters( 'rolevisibility_bypass', $bypass, $user_id );
        }

        return $cache[ $user_id ];
    } // End user_bypasses()


    /**
     * Get a user's roles.
     *
     * @param int $user_id
     * @return array
     */
    private static function user_roles( int $user_id ) : array {
        static $cache = [];

        if ( ! isset( $cache[ $user_id ] ) ) {
            $user = $user_id ? get_userdata( $user_id ) : false;
            $cache[ $user_id ] = $user ? (array) $user->roles : [];
        }

        return $cache[ $user_id ];
    } // End user_roles()


    /**
     * Evaluate a rule for a user, without filters or bypass checks.
     *
     * @param array $rule
     * @param int   $user_id 0 for a logged-out visitor.
     * @return bool
     */
    public static function evaluate( array $rule, int $user_id ) : bool {
        $access = $rule[ 'access' ] ?? 'everyone';

        if ( 'logged-out' === $access ) {
            return 0 === $user_id;
        }

        if ( 'logged-in' !== $access ) {
            return true;
        }

        if ( 0 === $user_id ) {
            return false;
        }

        if ( empty( $rule[ 'roles' ] ) && empty( $rule[ 'users' ] ) ) {
            return true;
        }

        return in_array( $user_id, $rule[ 'users' ] ?? [], true ) || ! empty( array_intersect( $rule[ 'roles' ] ?? [], self::user_roles( $user_id ) ) );
    } // End evaluate()


    /**
     * Check whether a user can view a post.
     *
     * @param int      $post_id
     * @param int|null $user_id Defaults to the current user.
     * @return bool
     */
    public static function user_has_access( int $post_id, ?int $user_id = null ) : bool {
        $user_id ??= get_current_user_id();
        $key = $post_id . '|' . $user_id;

        if ( isset( self::$results[ $key ] ) ) {
            return self::$results[ $key ];
        }

        $rule = self::get_rule( $post_id );
        $has_access = self::is_no_access_page( $post_id ) || self::user_bypasses( $user_id ) || self::evaluate( $rule, $user_id );

        self::$results[ $key ] = (bool) apply_filters( 'rolevisibility_user_has_access', $has_access, $post_id, $user_id, $rule );

        return self::$results[ $key ];
    } // End user_has_access()


    /**
     * Get why a user cannot view a post, matching the template conditions.
     *
     * @param int      $post_id
     * @param int|null $user_id
     * @return string Empty when the user has access, otherwise login_required, missing_access or logout_required.
     */
    public static function denial_reason( int $post_id, ?int $user_id = null ) : string {
        $user_id ??= get_current_user_id();

        if ( self::user_has_access( $post_id, $user_id ) ) {
            return '';
        }

        if ( 'logged-out' === self::get_rule( $post_id )[ 'access' ] ) {
            return 'logout_required';
        }

        return $user_id ? 'missing_access' : 'login_required';
    } // End denial_reason()


    /**
     * Get a readable summary of a post's stored rule.
     *
     * @param int $post_id
     * @return string
     */
    public static function describe( int $post_id ) : string {
        $options = self::options();
        $rule = self::parse( (string) get_post_meta( $post_id, self::META_KEY, true ) );

        if ( 'default' === $rule[ 'access' ] ) {
            $default = self::post_type_default( (string) get_post_type( $post_id ) );

            /* translators: %s: The post type's default access label, e.g. "Everyone". */
            return sprintf( __( 'Default (%s)', 'role-visibility' ), $options[ $default ] ?? $default );
        }

        $label = $options[ $rule[ 'access' ] ] ?? $rule[ 'access' ];
        $roles = Helpers::all_roles();
        $limits = array_merge( array_map( fn( $role ) => $roles[ $role ] ?? $role, $rule[ 'roles' ] ), array_values( Helpers::user_labels( $rule[ 'users' ] ) ) );

        return empty( $limits ) ? $label : $label . ': ' . implode( ', ', $limits );
    } // End describe()


    /**
     * Redirect visitors who cannot view the current singular content to the No Access Page.
     *
     * @return void
     */
    public function maybe_redirect() : void {
        if ( is_home() && ! is_front_page() ) {
            $post_id = (int) get_option( 'page_for_posts' );
        } elseif ( is_singular() ) {
            $post_id = get_queried_object_id();
        } else {
            return;
        }

        $page_id = (int) Settings::get( 'no_access_page' );

        if ( ! $post_id || ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
            return;
        }

        $reason = self::denial_reason( $post_id );

        if ( '' === $reason ) {
            return;
        }

        $url = add_query_arg( urlencode_deep( array_merge( Helpers::carry_query_args(), [ 'no_access' => $post_id ] ) ), get_permalink( $page_id ) );
        $url = apply_filters( 'rolevisibility_redirect_url', $url, $post_id, $reason );

        do_action( 'rolevisibility_before_redirect', $post_id, $reason );

        nocache_headers();
        wp_safe_redirect( $url, 302, 'Role Visibility' );
        exit;
    } // End maybe_redirect()


    /**
     * Save a rule submitted from the meta box, Quick Edit or Bulk Edit.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @return void
     */
    public function save_post( int $post_id, \WP_Post $post ) : void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! self::is_enabled( $post->post_type ) ) {
            return;
        }

        $is_bulk = isset( $_REQUEST[ 'bulk_edit' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects which nonce is verified next.
        $field = $is_bulk ? 'rolevisibility_bulk' : 'rolevisibility';
        $nonce = isset( $_REQUEST[ $field . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field . '_nonce' ] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( $is_bulk ) {
            $targets = isset( $_REQUEST[ 'post' ] ) ? wp_parse_id_list( wp_unslash( $_REQUEST[ 'post' ] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by wp_parse_id_list().
        } else {
            $targets = isset( $_REQUEST[ 'post_ID' ] ) ? [ absint( $_REQUEST[ 'post_ID' ] ) ] : [];
        }

        if ( ! in_array( $post_id, $targets, true ) ) {
            return;
        }

        $data = isset( $_REQUEST[ $field ] ) && is_array( $_REQUEST[ $field ] ) ? wp_unslash( $_REQUEST[ $field ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized in self::set().
        $access = sanitize_key( $data[ 'access' ] ?? '' );

        if ( ! array_key_exists( $access, self::options() ) ) {
            return;
        }

        self::set( $post_id, $access, (array) ( $data[ 'roles' ] ?? [] ), (array) ( $data[ 'users' ] ?? [] ) );
    } // End save_post()


    /**
     * Store a rule on a post.
     *
     * @param int    $post_id
     * @param string $access default|everyone|logged-in|logged-out
     * @param array  $roles
     * @param array  $users
     * @return void
     */
    public static function set( int $post_id, string $access, array $roles = [], array $users = [] ) : void {
        $roles = array_values( array_intersect( array_map( 'sanitize_key', $roles ), array_keys( Helpers::all_roles() ) ) );
        $users = array_values( array_unique( array_filter( array_map( 'absint', $users ) ) ) );

        if ( 'default' === $access ) {
            delete_post_meta( $post_id, self::META_KEY );
            $raw = '';
        } else {
            $raw = self::build( $access, $roles, $users );
            update_post_meta( $post_id, self::META_KEY, $raw );
        }

        unset( self::$rules[ $post_id ] );
        self::$results = [];

        do_action( 'rolevisibility_access_updated', $post_id, $raw );
    } // End set()

} // End class Access

Access::instance();

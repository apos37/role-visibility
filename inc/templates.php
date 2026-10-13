<?php
/**
 * TEMPLATES
 *
 * The access denied templates post type: registration, settings meta box, merge tags,
 * a cached lookup index, and rendering for the No Access Page.
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class Templates {

    /**
     * Post type slug.
     */
    public const POST_TYPE = 'rolevisibility_tpl';


    /**
     * Meta keys.
     */
    public const META_SCOPE = '_rolevisibility_tpl_scope';
    public const META_TARGETS = '_rolevisibility_tpl_targets';
    public const META_CONDITIONS = '_rolevisibility_tpl_conditions';


    /**
     * Option holding the lookup index.
     */
    public const INDEX_OPTION = 'rolevisibility_template_index';


    /**
     * Nonce action for the template settings meta box.
     */
    public const NONCE = 'rolevisibility_save_template';


    /**
     * @var Templates|null Singleton instance
     */
    private static ?Templates $instance = null;


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
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, [ $this, 'meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save' ], 10, 2 );
        add_action( 'save_post_' . self::POST_TYPE, [ __CLASS__, 'rebuild_index' ], 20 );
        add_action( 'trashed_post', [ $this, 'maybe_rebuild_index' ] );
        add_action( 'untrashed_post', [ $this, 'maybe_rebuild_index' ] );
        add_action( 'after_delete_post', [ $this, 'after_delete' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'media_buttons', [ $this, 'render_merge_tag_dropdown' ], 20 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
    } // End __construct()


    /**
     * Template conditions and their labels.
     *
     * @return array
     */
    public static function conditions() : array {
        return [
            'login_required'  => __( 'Visitor must log in', 'role-visibility' ),
            'missing_access'  => __( 'User lacks the required role or user', 'role-visibility' ),
            'logout_required' => __( 'Logged-in user on a logged-out-only page', 'role-visibility' ),
        ];
    } // End conditions()


    /**
     * Template scopes and their labels.
     *
     * @return array
     */
    public static function scopes() : array {
        return [
            'global'    => __( 'Global', 'role-visibility' ),
            'post_type' => __( 'Post Types', 'role-visibility' ),
            'posts'     => __( 'Specific Posts', 'role-visibility' ),
        ];
    } // End scopes()


    /**
     * Register the templates post type.
     *
     * @return void
     */
    public function register_post_type() : void {
        $caps = array_fill_keys( [ 'read', 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'create_posts' ], 'manage_options' );

        register_post_type( self::POST_TYPE, [
            'labels'                => [
                'name'               => __( 'Templates', 'role-visibility' ),
                'singular_name'      => __( 'Template', 'role-visibility' ),
                'add_new'            => __( 'Add New Template', 'role-visibility' ),
                'add_new_item'       => __( 'Add New Template', 'role-visibility' ),
                'edit_item'          => __( 'Edit Template', 'role-visibility' ),
                'new_item'           => __( 'New Template', 'role-visibility' ),
                'search_items'       => __( 'Search Templates', 'role-visibility' ),
                'not_found'          => __( 'No templates found.', 'role-visibility' ),
                'not_found_in_trash' => __( 'No templates found in Trash.', 'role-visibility' ),
                'all_items'          => __( 'Templates', 'role-visibility' ),
            ],
            'public'                => false,
            'publicly_queryable'    => false,
            'exclude_from_search'   => true,
            'show_ui'               => true,
            'show_in_menu'          => Menu::SLUG,
            'show_in_nav_menus'     => false,
            'show_in_admin_bar'     => false,
            'show_in_rest'          => true,
            'rest_base'             => 'rolevisibility-templates',
            'rest_controller_class' => TemplatesRestController::class,
            'capability_type'       => 'post',
            'capabilities'          => $caps,
            'map_meta_cap'          => true,
            'hierarchical'          => false,
            'supports'              => [ 'title', 'editor', 'revisions' ],
            'rewrite'               => false,
            'query_var'             => false,
            'delete_with_user'      => false,
        ] );
    } // End register_post_type()


    /**
     * Enqueue editor assets on the template edit screen.
     *
     * @return void
     */
    public function enqueue() : void {
        $screen = get_current_screen();

        if ( ! $screen || self::POST_TYPE !== $screen->id ) {
            return;
        }

        Pickers::enqueue_fields_assets();
        wp_enqueue_script( 'rolevisibility-template-editor', Bootstrap::url() . 'inc/js/template-editor.js', [ 'jquery', 'rolevisibility-fields' ], Bootstrap::script_version(), true );

        wp_localize_script( 'rolevisibility-template-editor', 'roleVisibilityTemplate', [
            'copied' => __( 'Copied!', 'role-visibility' ),
        ] );
    } // End enqueue()


    /**
     * Register the template meta boxes.
     *
     * @return void
     */
    public function meta_boxes() : void {
        add_meta_box( 'rolevisibility-template-settings', __( 'Template Settings', 'role-visibility' ), [ $this, 'render_settings_box' ], self::POST_TYPE, 'side', 'high' );
        add_meta_box( 'rolevisibility-merge-tags', __( 'Merge Tags', 'role-visibility' ), [ $this, 'render_merge_tags_box' ], self::POST_TYPE, 'side', 'default' );
    } // End meta_boxes()


    /**
     * Get a template's settings.
     *
     * @param int $template_id
     * @return array [ scope, targets, conditions ]
     */
    public static function get_settings( int $template_id ) : array {
        $scope = (string) get_post_meta( $template_id, self::META_SCOPE, true );

        return [
            'scope'      => array_key_exists( $scope, self::scopes() ) ? $scope : 'global',
            'targets'    => array_values( (array) get_post_meta( $template_id, self::META_TARGETS, true ) ),
            'conditions' => array_values( (array) get_post_meta( $template_id, self::META_CONDITIONS, true ) ),
        ];
    } // End get_settings()


    /**
     * Render the Template Settings meta box.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function render_settings_box( \WP_Post $post ) : void {
        $settings = self::get_settings( $post->ID );
        $target = isset( $_GET[ 'rolevisibility_target' ] ) ? absint( $_GET[ 'rolevisibility_target' ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- prefills a new template, no data is changed.

        if ( 'auto-draft' === $post->post_status && $target && get_post( $target ) ) {
            $settings = [ 'scope' => 'posts', 'targets' => [ $target ], 'conditions' => [] ];
        }

        $post_ids = 'posts' === $settings[ 'scope' ] ? array_map( 'absint', $settings[ 'targets' ] ) : [];
        $post_labels = [];

        foreach ( $post_ids as $post_id ) {
            $post_labels[ $post_id ] = self::post_label( $post_id );
        }

        wp_nonce_field( self::NONCE, 'rolevisibility_template_nonce' );
        ?>
        <div class="rolevisibility-template-settings">
            <p class="rolevisibility-sub-label"><?php esc_html_e( 'Applies When', 'role-visibility' ); ?></p>
            <?php foreach ( self::conditions() as $key => $label ) : ?>
                <label class="rolevisibility-check-row">
                    <input type="checkbox" name="rolevisibility_template[conditions][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings[ 'conditions' ], true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>

            <label class="rolevisibility-sub-label" for="rolevisibility-template-scope"><?php esc_html_e( 'Scope', 'role-visibility' ); ?></label>
            <select id="rolevisibility-template-scope" name="rolevisibility_template[scope]" class="widefat">
                <?php foreach ( self::scopes() as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings[ 'scope' ], $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="rolevisibility-scope-panel" data-scope="post_type"<?php echo 'post_type' === $settings[ 'scope' ] ? '' : ' hidden'; ?>>
                <?php foreach ( Settings::get( 'post_types' ) as $slug => $default ) : ?>
                    <?php $object = get_post_type_object( $slug ); ?>
                    <?php if ( $object ) : ?>
                        <label class="rolevisibility-check-row">
                            <input type="checkbox" name="rolevisibility_template[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( 'post_type' === $settings[ 'scope' ] && in_array( $slug, $settings[ 'targets' ], true ) ); ?>>
                            <?php echo esc_html( $object->labels->name ); ?>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="rolevisibility-scope-panel" data-scope="posts"<?php echo 'posts' === $settings[ 'scope' ] ? '' : ' hidden'; ?>>
                <?php Helpers::render_picker( 'posts', 'rolevisibility_template[posts][]', $post_labels, __( 'Search posts and pages…', 'role-visibility' ) ); ?>
            </div>

            <p class="rolevisibility-hint"><?php esc_html_e( 'The most specific match is shown: Specific Posts, then Post Types, then Global. If two templates tie, the most recently modified one wins.', 'role-visibility' ); ?></p>
        </div>
        <?php
    } // End render_settings_box()


    /**
     * Get a label for a post in the picker: title and post type.
     *
     * @param int $post_id
     * @return string
     */
    public static function post_label( int $post_id ) : string {
        $object = get_post_type_object( (string) get_post_type( $post_id ) );
        $title = get_the_title( $post_id );

        if ( '' === $title ) {
            /* translators: %d: Post ID. */
            $title = sprintf( __( '(no title) #%d', 'role-visibility' ), $post_id );
        }

        return $object ? $title . ' — ' . $object->labels->singular_name : $title;
    } // End post_label()


    /**
     * Render the Merge Tags meta box.
     *
     * @return void
     */
    public function render_merge_tags_box() : void {
        ?>
        <p class="rolevisibility-hint"><?php esc_html_e( 'Click a tag to insert it into the Classic Editor, or to copy it for the block editor. Tags also work inside link and button URLs.', 'role-visibility' ); ?></p>
        <ul class="rolevisibility-merge-tags">
            <?php foreach ( self::merge_tags() as $tag => $tag_data ) : ?>
                <li>
                    <button type="button" class="rolevisibility-merge-tag" data-tag="<?php echo esc_attr( '{' . $tag . '}' ); ?>"><code><?php echo esc_html( '{' . $tag . '}' ); ?></code></button>
                    <span><?php echo esc_html( $tag_data[ 'label' ] ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    } // End render_merge_tags_box()


    /**
     * Render an "Insert Merge Tag" dropdown beside Add Media in the Classic Editor.
     *
     * @param string $editor_id
     * @return void
     */
    public function render_merge_tag_dropdown( $editor_id ) : void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( 'content' !== $editor_id || ! $screen || self::POST_TYPE !== $screen->id ) {
            return;
        }
        ?>
        <select class="rolevisibility-merge-tag-select" aria-label="<?php esc_attr_e( 'Insert Merge Tag', 'role-visibility' ); ?>">
            <option value=""><?php esc_html_e( 'Insert Merge Tag', 'role-visibility' ); ?></option>
            <?php foreach ( self::merge_tags() as $tag => $tag_data ) : ?>
                <option value="<?php echo esc_attr( '{' . $tag . '}' ); ?>"><?php echo esc_html( $tag_data[ 'label' ] ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    } // End render_merge_tag_dropdown()


    /**
     * Save the Template Settings meta box.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @return void
     */
    public function save( int $post_id, \WP_Post $post ) : void {
        $nonce = isset( $_POST[ 'rolevisibility_template_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'rolevisibility_template_nonce' ] ) ) : '';

        if ( wp_is_post_revision( $post_id ) || ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $data = isset( $_POST[ 'rolevisibility_template' ] ) && is_array( $_POST[ 'rolevisibility_template' ] ) ? wp_unslash( $_POST[ 'rolevisibility_template' ] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized in self::set_settings().

        self::set_settings( $post_id, (string) ( $data[ 'scope' ] ?? 'global' ), (array) ( $data[ 'conditions' ] ?? [] ), (array) ( $data[ 'post_types' ] ?? [] ), (array) ( $data[ 'posts' ] ?? [] ) );
    } // End save()


    /**
     * Sanitize and store a template's settings.
     *
     * @param int    $template_id
     * @param string $scope
     * @param array  $conditions
     * @param array  $post_types Used when the scope is post_type.
     * @param array  $post_ids   Used when the scope is posts.
     * @return void
     */
    public static function set_settings( int $template_id, string $scope, array $conditions, array $post_types = [], array $post_ids = [] ) : void {
        $scope = array_key_exists( $scope, self::scopes() ) ? $scope : 'global';
        $conditions = array_values( array_intersect( array_map( 'sanitize_key', $conditions ), array_keys( self::conditions() ) ) );

        if ( 'post_type' === $scope ) {
            $targets = array_values( array_filter( array_map( 'sanitize_key', $post_types ), 'post_type_exists' ) );
        } elseif ( 'posts' === $scope ) {
            $targets = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
        } else {
            $targets = [];
        }

        update_post_meta( $template_id, self::META_SCOPE, $scope );
        update_post_meta( $template_id, self::META_TARGETS, $targets );
        update_post_meta( $template_id, self::META_CONDITIONS, $conditions );
    } // End set_settings()


    /**
     * Rebuild the index after a template is trashed or restored.
     *
     * @param int $post_id
     * @return void
     */
    public function maybe_rebuild_index( $post_id ) : void {
        if ( self::POST_TYPE === get_post_type( $post_id ) ) {
            self::rebuild_index();
        }
    } // End maybe_rebuild_index()


    /**
     * Rebuild the index after a template is permanently deleted.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @return void
     */
    public function after_delete( $post_id, $post ) : void {
        if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type ) {
            self::rebuild_index();
        }
    } // End after_delete()


    /**
     * Rebuild the lookup index from published templates. Later-modified templates win ties.
     *
     * @return array [ condition => [ global => id, post_type => [ type => id ], posts => [ post_id => id ] ] ]
     */
    public static function rebuild_index() : array {
        $template_ids = get_posts( [
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'modified',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
        ] );

        update_meta_cache( 'post', $template_ids );

        $index = [];

        foreach ( $template_ids as $template_id ) {
            $settings = self::get_settings( $template_id );

            foreach ( $settings[ 'conditions' ] as $condition ) {
                if ( 'global' === $settings[ 'scope' ] ) {
                    $index[ $condition ][ 'global' ] = $template_id;
                    continue;
                }

                foreach ( $settings[ 'targets' ] as $target ) {
                    $index[ $condition ][ $settings[ 'scope' ] ][ $target ] = $template_id;
                }
            }
        }

        update_option( self::INDEX_OPTION, $index, false );

        return $index;
    } // End rebuild_index()


    /**
     * Get the lookup index.
     *
     * @return array
     */
    public static function get_index() : array {
        $index = get_option( self::INDEX_OPTION, null );

        return is_array( $index ) ? $index : self::rebuild_index();
    } // End get_index()


    /**
     * Find the template for a post and condition.
     *
     * @param int    $post_id
     * @param string $condition
     * @return int Template ID, or 0 to use the built-in default.
     */
    public static function resolve( int $post_id, string $condition ) : int {
        $index = self::get_index()[ $condition ] ?? [];
        $post_type = (string) get_post_type( $post_id );
        $template_id = (int) ( $index[ 'posts' ][ $post_id ] ?? $index[ 'post_type' ][ $post_type ] ?? $index[ 'global' ] ?? 0 );

        return (int) apply_filters( 'rolevisibility_template_id', $template_id, $post_id, $condition );
    } // End resolve()


    /**
     * Built-in default content for a condition.
     *
     * @param string      $condition
     * @param string|null $message Message to use instead of the default one.
     * @return string
     */
    public static function default_content( string $condition, ?string $message = null ) : string {
        $message ??= self::default_message( $condition );

        if ( 'logout_required' === $condition ) {
            /* translators: 1: Home link merge tag, 2: Log out link merge tag, 3: Retry link merge tag. */
            $links = sprintf( __( 'You can %1$s, %2$s, or %3$s.', 'role-visibility' ), '{home_link}', '{logout_link}', '{retry_link}' );

            return '<p>' . $message . '</p>' . "\n\n" . '<p>' . $links . '</p>';
        }

        if ( 'missing_access' === $condition ) {
            /* translators: 1: Home link merge tag, 2: Retry link merge tag. */
            $links = sprintf( __( 'You can %1$s or %2$s.', 'role-visibility' ), '{home_link}', '{retry_link}' );
        } else {
            /* translators: 1: Home link merge tag, 2: Log in link merge tag, 3: Retry link merge tag. */
            $links = sprintf( __( 'You can %1$s, %2$s, or %3$s.', 'role-visibility' ), '{home_link}', '{login_link}', '{retry_link}' );
        }

        return '<h2>' . __( 'Access Denied', 'role-visibility' ) . '</h2>' . "\n\n" . '<p>' . $message . '</p>' . "\n\n" . '<p>' . $links . '</p>';
    } // End default_content()


    /**
     * Built-in default message for a condition.
     *
     * @param string $condition
     * @return string
     */
    public static function default_message( string $condition ) : string {
        if ( 'logout_required' === $condition ) {
            return __( 'Oops, this page can only be viewed if you are logged out.', 'role-visibility' );
        }

        return __( 'Sorry, you do not have permission to access this page. Please contact the site administrator if you believe this is an error.', 'role-visibility' );
    } // End default_message()


    /**
     * Convert plain HTML into block markup so the block editor opens it as editable blocks.
     *
     * Paragraphs and headings become core blocks. Anything else is kept in one Custom HTML block.
     *
     * @param string $content
     * @return string
     */
    public static function to_blocks( string $content ) : string {
        $original = trim( $content );

        if ( '' === $original || has_blocks( $original ) ) {
            return $original;
        }

        $classes = '';
        $html = $original;

        if ( preg_match( '#^<div([^>]*)>(.*)</div>$#s', $original, $wrapper ) && false === stripos( $wrapper[ 2 ], '<div' ) ) {
            $html = trim( $wrapper[ 2 ] );

            if ( preg_match( '#class=["\']([^"\']*)["\']#', $wrapper[ 1 ], $class_match ) ) {
                $classes = implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $class_match[ 1 ] ) ) ) );
            }
        }

        $parts = preg_split( '#(?<=</p>|</h[1-6]>)\s*#', trim( wpautop( $html ) ), -1, PREG_SPLIT_NO_EMPTY );
        $blocks = [];

        foreach ( $parts as $part ) {
            if ( preg_match( '#^<p>.*</p>$#s', $part ) && 1 === substr_count( $part, '<p' ) ) {
                $blocks[] = "<!-- wp:paragraph -->\n" . $part . "\n<!-- /wp:paragraph -->";
            } elseif ( preg_match( '#^<h([1-6])>(.*)</h\1>$#s', $part, $match ) ) {
                $level = (int) $match[ 1 ];
                $attrs = 2 === $level ? '' : ' ' . wp_json_encode( [ 'level' => $level ] );
                $blocks[] = '<!-- wp:heading' . $attrs . " -->\n<h" . $level . ' class="wp-block-heading">' . $match[ 2 ] . '</h' . $level . ">\n<!-- /wp:heading -->";
            } else {
                return "<!-- wp:html -->\n" . $original . "\n<!-- /wp:html -->";
            }
        }

        $inner = implode( "\n\n", $blocks );

        if ( '' === $classes ) {
            return $inner;
        }

        return '<!-- wp:group ' . wp_json_encode( [ 'className' => $classes ] ) . " -->\n" . '<div class="wp-block-group ' . esc_attr( $classes ) . '">' . "\n\n" . $inner . "\n\n</div>\n<!-- /wp:group -->";
    } // End to_blocks()


    /**
     * Create a published template.
     *
     * @param string $title
     * @param string $content
     * @param string $scope
     * @param array  $conditions
     * @param array  $targets Post types or post IDs, depending on the scope.
     * @return int Template ID, or 0 on failure.
     */
    public static function create( string $title, string $content, string $scope, array $conditions, array $targets = [] ) : int {
        $template_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => self::to_blocks( $content ),
        ] );

        if ( ! $template_id || is_wp_error( $template_id ) ) {
            return 0;
        }

        self::set_settings( $template_id, $scope, $conditions, 'post_type' === $scope ? $targets : [], 'posts' === $scope ? $targets : [] );

        return (int) $template_id;
    } // End create()


    /**
     * Create the three global templates when none exist.
     *
     * @param array $contents Optional content keyed by condition.
     * @return void
     */
    public static function create_defaults( array $contents = [] ) : void {
        $existing = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        if ( ! empty( $existing ) ) {
            return;
        }

        $titles = [
            'login_required'  => __( 'Login Required', 'role-visibility' ),
            'missing_access'  => __( 'Missing Role or User', 'role-visibility' ),
            'logout_required' => __( 'Logged-Out Only', 'role-visibility' ),
        ];

        foreach ( $titles as $condition => $title ) {
            self::create( $title, $contents[ $condition ] ?? self::default_content( $condition ), 'global', [ $condition ] );
        }

        self::rebuild_index();
    } // End create_defaults()


    /**
     * Merge tags available in templates.
     *
     * Each tag has a label and a callback that receives the render context and returns safe HTML or an escaped URL.
     *
     * @return array
     */
    public static function merge_tags() : array {
        $link = fn( string $url, string $text ) : string => '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';

        $tags = [
            'login_link'        => [ 'label' => __( 'Log in link', 'role-visibility' ), 'callback' => fn( $c ) => $link( self::login_url( $c ), __( 'log in', 'role-visibility' ) ) ],
            'login_url'         => [ 'label' => __( 'Log in URL', 'role-visibility' ), 'callback' => fn( $c ) => esc_url( self::login_url( $c ) ) ],
            'logout_link'       => [ 'label' => __( 'Log out link', 'role-visibility' ), 'callback' => fn( $c ) => $link( wp_logout_url( $c[ 'redirect_to' ] ), __( 'log out', 'role-visibility' ) ) ],
            'logout_url'        => [ 'label' => __( 'Log out URL', 'role-visibility' ), 'callback' => fn( $c ) => esc_url( wp_logout_url( $c[ 'redirect_to' ] ) ) ],
            'register_link'     => [ 'label' => __( 'Register link', 'role-visibility' ), 'callback' => fn( $c ) => $link( self::register_url( $c ), __( 'register', 'role-visibility' ) ) ],
            'register_url'      => [ 'label' => __( 'Register URL', 'role-visibility' ), 'callback' => fn( $c ) => esc_url( self::register_url( $c ) ) ],
            'retry_link'        => [ 'label' => __( 'Retry link', 'role-visibility' ), 'callback' => fn( $c ) => $link( self::retry_url( $c ), __( 'retry', 'role-visibility' ) ) ],
            'retry_url'         => [ 'label' => __( 'Retry URL', 'role-visibility' ), 'callback' => fn( $c ) => esc_url( self::retry_url( $c ) ) ],
            'home_link'         => [ 'label' => __( 'Homepage link', 'role-visibility' ), 'callback' => fn( $c ) => $link( home_url( '/' ), __( 'return to the homepage', 'role-visibility' ) ) ],
            'home_url'          => [ 'label' => __( 'Homepage URL', 'role-visibility' ), 'callback' => fn( $c ) => esc_url( home_url( '/' ) ) ],
            'post_title'        => [ 'label' => __( 'Title of the restricted page', 'role-visibility' ), 'callback' => fn( $c ) => esc_html( get_the_title( $c[ 'post_id' ] ) ) ],
            'post_url'          => [ 'label' => __( 'URL of the restricted page', 'role-visibility' ), 'callback' => fn( $c ) => esc_url( $c[ 'redirect_to' ] ) ],
            'required_roles'    => [ 'label' => __( 'Roles that can view the page', 'role-visibility' ), 'callback' => fn( $c ) => esc_html( self::required_roles( $c ) ) ],
            'user_display_name' => [ 'label' => __( 'Current user\'s display name', 'role-visibility' ), 'callback' => fn( $c ) => esc_html( $c[ 'user_id' ] ? (string) ( get_userdata( $c[ 'user_id' ] ) ?: null )?->display_name : '' ) ],
            'site_name'         => [ 'label' => __( 'Site name', 'role-visibility' ), 'callback' => fn( $c ) => esc_html( get_bloginfo( 'name' ) ) ],
        ];

        return (array) apply_filters( 'rolevisibility_merge_tags', $tags );
    } // End merge_tags()


    /**
     * Get the login URL for a context.
     *
     * @param array $context
     * @return string
     */
    private static function login_url( array $context ) : string {
        $custom = (string) Settings::get( 'login_url' );

        return $custom ? add_query_arg( 'redirect_to', rawurlencode( $context[ 'redirect_to' ] ), $custom ) : wp_login_url( $context[ 'redirect_to' ] );
    } // End login_url()


    /**
     * Get the register URL for a context.
     *
     * @param array $context
     * @return string
     */
    private static function register_url( array $context ) : string {
        $custom = (string) Settings::get( 'register_url' );

        return $custom ? add_query_arg( 'redirect_to', rawurlencode( $context[ 'redirect_to' ] ), $custom ) : wp_registration_url();
    } // End register_url()


    /**
     * Get the retry URL for a context, with a cache buster.
     *
     * @param array $context
     * @return string
     */
    private static function retry_url( array $context ) : string {
        return add_query_arg( 'cb', time(), $context[ 'redirect_to' ] );
    } // End retry_url()


    /**
     * Get the labels of the roles allowed to view the restricted post.
     *
     * @param array $context
     * @return string
     */
    private static function required_roles( array $context ) : string {
        $roles = Helpers::all_roles();

        return implode( ', ', array_map( fn( $role ) => $roles[ $role ] ?? $role, $context[ 'rule' ][ 'roles' ] ?? [] ) );
    } // End required_roles()


    /**
     * Render the template for a restricted post and condition.
     *
     * @param int    $post_id
     * @param string $condition
     * @param string $redirect_to URL of the restricted post, with the visitor's query string.
     * @return string
     */
    public static function render( int $post_id, string $condition, string $redirect_to ) : string {
        $template_id = self::resolve( $post_id, $condition );
        $template = $template_id ? get_post( $template_id ) : null;
        $content = $template && 'publish' === $template->post_status ? $template->post_content : self::default_content( $condition );

        $html = has_blocks( $content ) ? do_blocks( $content ) : wpautop( $content );
        $html = do_shortcode( $html );

        $context = [
            'post_id'     => $post_id,
            'condition'   => $condition,
            'user_id'     => get_current_user_id(),
            'rule'        => Access::get_rule( $post_id ),
            'redirect_to' => $redirect_to,
            'template_id' => $template_id,
        ];

        foreach ( self::merge_tags() as $tag => $tag_data ) {
            $token = '{' . $tag . '}';

            if ( str_contains( $html, $token ) && is_callable( $tag_data[ 'callback' ] ?? null ) ) {
                $html = str_replace( $token, (string) call_user_func( $tag_data[ 'callback' ], $context ), $html );
            }
        }

        return (string) apply_filters( 'rolevisibility_template_content', $html, $template_id, $post_id, $condition );
    } // End render()


    /**
     * Add columns to the templates list.
     *
     * @param array $columns
     * @return array
     */
    public function columns( array $columns ) : array {
        $date = $columns[ 'date' ] ?? null;
        unset( $columns[ 'date' ] );

        $columns[ 'rolevisibility_conditions' ] = __( 'Applies When', 'role-visibility' );
        $columns[ 'rolevisibility_scope' ] = __( 'Scope', 'role-visibility' );

        if ( $date ) {
            $columns[ 'date' ] = $date;
        }

        return $columns;
    } // End columns()


    /**
     * Render the custom column content.
     *
     * @param string $column
     * @param int    $post_id
     * @return void
     */
    public function column_content( string $column, int $post_id ) : void {
        $settings = self::get_settings( $post_id );

        if ( 'rolevisibility_conditions' === $column ) {
            $labels = array_intersect_key( self::conditions(), array_flip( $settings[ 'conditions' ] ) );
            echo empty( $labels ) ? '<em>' . esc_html__( 'None — this template is not used', 'role-visibility' ) . '</em>' : esc_html( implode( ', ', $labels ) );
            return;
        }

        if ( 'rolevisibility_scope' !== $column ) {
            return;
        }

        echo esc_html( self::scopes()[ $settings[ 'scope' ] ] );

        if ( 'post_type' === $settings[ 'scope' ] ) {
            $names = array_map( fn( $slug ) => get_post_type_object( $slug )?->labels->name ?? $slug, $settings[ 'targets' ] );
            echo ': ' . esc_html( implode( ', ', $names ) );
        } elseif ( 'posts' === $settings[ 'scope' ] ) {
            $links = [];

            foreach ( array_slice( $settings[ 'targets' ], 0, 5 ) as $target ) {
                $links[] = '<a href="' . esc_url( (string) get_edit_post_link( $target ) ) . '">' . esc_html( get_the_title( $target ) ) . '</a>';
            }

            $more = count( $settings[ 'targets' ] ) - 5;

            echo ': ' . wp_kses_post( implode( ', ', $links ) );

            if ( $more > 0 ) {
                /* translators: %d: Number of additional posts. */
                echo ' ' . esc_html( sprintf( _n( 'and %d more', 'and %d more', $more, 'role-visibility' ), $more ) );
            }
        }
    } // End column_content()

} // End class Templates


/**
 * REST controller that limits reading templates to users who can edit them.
 */
class TemplatesRestController extends \WP_REST_Posts_Controller {

    /**
     * Templates are only readable by users who can edit them.
     *
     * @param \WP_Post $post
     * @return bool
     */
    public function check_read_permission( $post ) {
        return current_user_can( 'edit_post', $post->ID ) && parent::check_read_permission( $post );
    } // End check_read_permission()


    /**
     * Listing templates requires the capability to manage them.
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function get_items_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view templates.', 'role-visibility' ), [ 'status' => rest_authorization_required_code() ] );
        }

        return parent::get_items_permissions_check( $request );
    } // End get_items_permissions_check()

} // End class TemplatesRestController

Templates::instance();

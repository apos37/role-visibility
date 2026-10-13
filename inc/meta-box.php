<?php
/**
 * META BOX
 *
 * Access controls on the post edit screen for enabled post types, with the templates in use.
 * Saving is handled by Access::save_post().
 */

namespace PluginRx\RoleVisibility;

if ( ! defined( 'ABSPATH' ) ) exit;


class MetaBox {

    /**
     * @var MetaBox|null Singleton instance
     */
    private static ?MetaBox $instance = null;


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
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    } // End __construct()


    /**
     * Check whether the current screen is the edit screen of an enabled post type.
     *
     * @return bool
     */
    private static function is_edit_screen() : bool {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return $screen && 'post' === $screen->base && Access::is_enabled( (string) $screen->post_type );
    } // End is_edit_screen()


    /**
     * Enqueue the field assets on enabled edit screens.
     *
     * @return void
     */
    public function enqueue() : void {
        if ( self::is_edit_screen() ) {
            Pickers::enqueue_fields_assets();
        }
    } // End enqueue()


    /**
     * Add the meta box to enabled post types.
     *
     * @param string   $post_type
     * @param \WP_Post $post
     * @return void
     */
    public function add_meta_box( $post_type, $post ) : void {
        if ( ! Access::is_enabled( (string) $post_type ) ) {
            return;
        }

        add_meta_box( 'rolevisibility', __( 'Role Visibility', 'role-visibility' ), [ $this, 'render' ], $post_type, 'side', 'default' );
    } // End add_meta_box()


    /**
     * Render the meta box.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function render( \WP_Post $post ) : void {
        if ( Access::is_no_access_page( $post->ID ) ) {
            echo '<p>' . esc_html__( 'This is the No Access Page. It is always visible to everyone.', 'role-visibility' ) . '</p>';
            return;
        }

        $rule = Access::parse( (string) get_post_meta( $post->ID, Access::META_KEY, true ) );
        $options = Access::options();
        $default = Access::post_type_default( $post->post_type );

        wp_nonce_field( Access::NONCE, 'rolevisibility_nonce' );
        Helpers::render_access_fields( 'rolevisibility', $rule, $options[ $default ] ?? $default );

        $this->render_templates( $post->ID );
    } // End render()


    /**
     * Show which templates are used for this post, with links to edit them or create a unique one.
     *
     * @param int $post_id
     * @return void
     */
    private function render_templates( int $post_id ) : void {
        $rule = Access::get_rule( $post_id );

        if ( 'logged-out' === $rule[ 'access' ] ) {
            $conditions = [ 'logout_required' ];
        } elseif ( 'logged-in' === $rule[ 'access' ] ) {
            $conditions = empty( $rule[ 'roles' ] ) && empty( $rule[ 'users' ] ) ? [ 'login_required' ] : [ 'login_required', 'missing_access' ];
        } else {
            return;
        }

        $labels = Templates::conditions();
        ?>
        <div class="rolevisibility-templates-in-use">
            <span class="rolevisibility-sub-label"><?php esc_html_e( 'Templates in use', 'role-visibility' ); ?></span>
            <ul>
                <?php foreach ( $conditions as $condition ) : ?>
                    <?php $template_id = Templates::resolve( $post_id, $condition ); ?>
                    <li>
                        <span><?php echo esc_html( $labels[ $condition ] ); ?>:</span>
                        <?php if ( $template_id && current_user_can( 'edit_post', $template_id ) ) : ?>
                            <a href="<?php echo esc_url( (string) get_edit_post_link( $template_id ) ); ?>"><?php echo esc_html( get_the_title( $template_id ) ); ?></a>
                        <?php elseif ( $template_id ) : ?>
                            <?php echo esc_html( get_the_title( $template_id ) ); ?>
                        <?php else : ?>
                            <em><?php esc_html_e( 'Built-in default', 'role-visibility' ); ?></em>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( add_query_arg( [ 'post_type' => Templates::POST_TYPE, 'rolevisibility_target' => $post_id ], admin_url( 'post-new.php' ) ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create a unique template for this post', 'role-visibility' ); ?></a>
            <?php endif; ?>
        </div>
        <?php
    } // End render_templates()

} // End class MetaBox

MetaBox::instance();

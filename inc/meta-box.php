<?php
/**
 * Post/page meta box
 */


/**
 * Define Namespaces
 */
namespace Apos37\RoleVisibility;
use Apos37\RoleVisibility\License;
use Apos37\RoleVisibility\Settings;
use Apos37\RoleVisibility\Helpers;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new MetaBox();


/**
 * The class
 */
class MetaBox {

	/**
	 * Nonce
	 *
	 * @var string
	 */
	private $nonce = ROLEVISIBILITY__TEXTDOMAIN . '_nonce';


    /**
     * Constructor
     */
    public function __construct() {

        // Check license validity
        if ( !(new License())->has_been_validated() ) {
            return;
        }
        
		// Add the meta box
        add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );

        // Save the post data
        add_action( 'save_post', [ $this, 'save_post' ] );

		// JQuery and CSS
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End __construct()


	/**
     * Meta box
     *
     * @return void
     */
    public function meta_boxes() {
		$no_access_page = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_no_access_page', true );
		if ( $no_access_page && $no_access_page == get_the_ID() ) {
			return;
		}

		$post_types = (new Settings())->get_post_types();
		$current_post_type = get_current_screen()->post_type;

		foreach ( $post_types as $pt ) {
			if ( $pt[ 'post_type' ] == $current_post_type ) {
				add_meta_box( 
					'role-visibility',
					__( 'Role Visibility', 'role-visibility' ),
					[ $this, 'meta_box_content' ],
					$current_post_type,
				); 
			}
		}
    } // End meta_box()


    /**
     * Meta box content
     *
     * @param object $post
     * @return void
     */
    public function meta_box_content( $post ) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field( $this->nonce, $this->nonce );

		// Instantiate
		$SETTINGS = new Settings();

		// Get the options
		$options = $SETTINGS->options;
		$post_types = $SETTINGS->get_post_types();
        $roles = $SETTINGS->get_roles();
    
        // Get the current values
        $get_values = get_post_meta( $post->ID, $SETTINGS->meta_key_option, true );
        $get_message = get_post_meta( $post->ID, $SETTINGS->meta_key_msg, true );
		$post_type = get_post_type();
    
        // Convert string to array
        $values = explode( ',', str_replace( ' ', '', $get_values ) );

		// Default label
		$default = 'Everyone';
		foreach ( $post_types as $pt ) {
			if ( $pt[ 'post_type' ] == $post_type ) {
				$def_key = $pt[ 'default' ];
				if ( in_array( $def_key, array_keys( $options ) ) ) {
					$default = $options[ $def_key ];
				}
				break;
			}
		}
		
        echo '<form>
        <div class="role-visibility-select-cont">
            <label for="role-visibility-select" class="role-visibility-select-label">' . esc_html__( 'Who should have access to this page?', 'role-visibility' ) . '</label>
            <select name="role_visibility[]" id="role-visibility-select">';

				if ( !empty( $options ) ) {
					foreach ( $options as $key => $label ) {
						$selected = in_array( $key, $values ) ? ' selected' : '';
						$label = $key == 'default' ? $label.' ('.$default.')' : $label;
						echo '<option value="' . esc_attr( $key ) . '"'.esc_attr( $selected ).'>'.esc_attr( $label ).'</option>';
					}
				}

            echo '</select>
        </div>';
    
        // For each role, make a checkbox
        foreach ( $roles as $key => $value ) {
            $checked = '';
            if ( in_array( $key, $values ) ) {
                $checked = 'checked';
            }

            echo '<div class="role_visibility-selection">
				<input type="checkbox" id="role_visibility-' . esc_attr( $key ) . '" 
					name="role_visibility[]" 
					value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
				<span id="role_visibility-label-' . esc_attr( $key ) . '" class="role_visibility-labels">
					<label for="role_visibility-' . esc_attr( $key ) . '">' . esc_html( $value ) . '</label>
				</span>
			</div>';
        }
    
        echo '</form>';
    
        // Add a note at the bottom for roles
        echo '<div class="role_visibility-selection">
            <br><br><em>' . esc_html__( 'Note: All logged-in roles will have access unless you specify specific ones.', 'role-visibility' ) . '</em><br><br>
        </div>';
    
		// Message
        echo '<label for="role_visibility_msg">' . esc_html__( 'Choose a message to display instead of the default', 'role-visibility' ) . ':</label>
        <br><input type="text" name="role_visibility_msg" id="role_visibility_msg" value="'.esc_html( $get_message ).'"/>';
    } // End meta_box_content()


    /**
     * Save the post data
     *
     * @param int $post_id
     * @return void
     */
    public function save_post( $post_id ) {
		$SETTINGS = new Settings();

        // Verify can save
		$get_post_types = $SETTINGS->get_post_types();
		if ( !empty( $get_post_types ) ) {
			$post_types = [];
            foreach ( $get_post_types as $pt ) {
                $post_types[] = $pt[ 'post_type' ];
            }
		} else {
            return;
        }
        if ( !(new Helpers())->can_save_post( $post_id, $post_types, $this->nonce, $this->nonce ) ) {
            return;
        }
     
        /* OK, it's safe for us to save the data now. */
     
        // Option
		$option_mk = $SETTINGS->meta_key_option;
        if ( !isset( $_POST[ $option_mk ] ) ) {
            return;
        }
        $roles = filter_var_array( $_POST[ $option_mk ], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $my_data = implode( ', ', $roles );
        update_post_meta( $post_id, $option_mk, $my_data );
     
        // Message
		$msg_mk = $SETTINGS->meta_key_msg;
        if ( !isset( $_POST[ $msg_mk ] ) ) {
            return;
        }
        $msg = sanitize_text_field( $_POST[ $msg_mk ] );
        update_post_meta( $post_id, $msg_mk, $msg );
    } // End save_post()


	/**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct page
        if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
            return;
        }

		$no_access_page = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_no_access_page', true );
		if ( $no_access_page && $no_access_page == get_the_ID() ) {
			return;
		}

		$post_types = (new Settings())->get_post_types();
		$current_post_type = get_current_screen()->post_type;
		$include = false;

		foreach ( $post_types as $pt ) {
			if ( $pt[ 'post_type' ] == $current_post_type ) {
				$include = true;
				break;
			}
		}
		if ( !$include ) {
			return;
		}

        $handle = ROLEVISIBILITY_TEXTDOMAIN.'-metabox';

		// Register and enqueue your JavaScript
        wp_register_script( $handle, ROLEVISIBILITY_JS_PATH.'meta-box.js', [ 'jquery' ], time(), true );
		wp_enqueue_script( $handle );

        // Localize the script with translation messages
        wp_localize_script( $handle, ROLEVISIBILITY__TEXTDOMAIN, [
            'duplicatePostType' => __( 'Duplicate post type detected! It must be unique.', 'role-visibility' ),
        ] );

		// Register and enqueue your CSS
		wp_enqueue_style( $handle, ROLEVISIBILITY_CSS_PATH . 'meta-box.css', [], time() );
    } // End enqueue_scripts()

}

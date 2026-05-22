<?php
/**
 * Class description
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
new Content();


/**
 * The class
 */
class Content {

    /**
     * Constructor
     */
    public function __construct() {

		// Check license validity
        if ( !(new License())->has_been_validated() ) {
            return;
        }

		// Template redirect
		add_action( 'template_redirect', [ $this, 'template_redirect' ] );
        
		// Shortcode for displaying the message
		add_shortcode( 'role_visibility_msg', [ $this, 'display_message' ] );

    } // End __construct()

	
	/**
	 * Template redirect
	 *
	 * @return void
	 */
	public function template_redirect() {
		// Get no access page or stop here
		$no_access_page = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_no_access_page', true );
		if ( !$no_access_page || !get_post( $no_access_page ) ) {
			return;
		}

		// Settings
		$SETTINGS = new Settings();

		// Get the post id
		$post_id = get_the_ID();

		// Get the option and roles
		$option = sanitize_text_field( get_post_meta( $post_id, $SETTINGS->meta_key_option, true ) );
		$roles = [];

		// If everyone, there was a mistake somewhere
		if ( $option == 'everyone' ) {
			return;

		// If the option exists, let's split the option from the roles
		} elseif ( $option ) {
			if ( strpos( $option, ',' ) !== false ) {
				$r_array = explode( ',', $option );
				$r_array = array_map( 'trim', $r_array );

				foreach ( $r_array as $r ) {
					if ( $r == 'logged-in' ) {
						$option = $r;
					} else {
						$roles[] = $r;
					}
				}
			}

		// Otherwise we will use default
		} else  {
			$option = 'default';
		}

		// Further validation
		$post_type = get_post_type( $post_id );
		$post_type_settings = $SETTINGS->get_post_types( $post_type );
		$is_user_logged_in = is_user_logged_in();

		if ( $post_type_settings && $option == 'default' ) {
			$option = $post_type_settings[ 'default' ];
		}

		if ( $option == 'logged-in' && !empty( $roles ) ) {
			$user = wp_get_current_user();
			$user_roles = $user->roles;

			foreach ( $roles as $role ) {
				if ( in_array( $role, $user_roles ) ) {
					return;
				}
			}

		} elseif ( ( $option != 'logged-in' && $option != 'logged-out' ) || 
			 ( $is_user_logged_in && $option == 'logged-in' ) || 
			 ( !$is_user_logged_in && $option == 'logged-out' ) ) {
			return;
		}

		// Get the current query string
		$current_query_args = array_map( 'sanitize_text_field', $_GET );
		$redirect_url = add_query_arg( [ 'no_access' => $post_id ], get_the_permalink( $no_access_page ) );
		$redirect_url = add_query_arg( $current_query_args, $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	} // End template_redirect()


	/**
	 * Load the message
	 *
	 * @return string
	 */
	public function display_message() {
		// Instantiate
		$SETTINGS = new Settings();

		// If they have not set up a no access page yet, let's set it up for them
		$no_access_page = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_no_access_page', true );
		if ( !$no_access_page ) {
			$no_access_page = get_the_ID();
			update_option( ROLEVISIBILITY__TEXTDOMAIN . '_no_access_page', $no_access_page );
			return __( 'This page has automatically been set as your "No Access Page". You can change it from Settings > Role Visibility. From now on, users will be directed here when they do not have access to the page they are trying to visit.', 'role-visibility' );
		}

		// Is the current user an admin?
		$is_admin_user = current_user_can( 'administrator' );

		// ASlt message
		$alt_msg = __( 'Sorry, you do not have access to view this page.', 'role-visibility' );

		// Get the post id
		$post_id = isset( $_GET[ 'no_access' ] ) ? absint( $_GET[ 'no_access' ] ) : false;
		if ( !$post_id ) {
			if ( $is_admin_user ) {

				if ( $no_access_page != get_the_ID() ) {
					return __( 'Hello, Admin!<br><br>This page is not set as your "No Access Page". To ensure that users will be redirected here, you must set this page in Settings > Role Visibility.', 'role-visibility' );
				}

				return sprintf( 
					__( 'Hello, Admin!<br><br>This is your "No Access Page". Users will see a customized message here when the post/page ID is included in the URL, which is the default behavior when they are redirected here.<br><br>Example: %s.', 'role-visibility' ),
					'<code>' . add_query_arg( 'no_access', '{post_id}', (new Helpers())->get_current_url( false ) ) . '</code>'
				);
			}
			return $alt_msg;
		}

		// Carryover query strings
		$current_query_args = array_map( 'sanitize_text_field', $_GET );
		unset( $current_query_args[ 'no_access' ] );
		$og_permalink = add_query_arg( $current_query_args, get_the_permalink( $post_id ) );

		// Get the options
		$option = sanitize_text_field( get_post_meta( $post_id, $SETTINGS->meta_key_option, true ) );
		$roles = [];

		// Return link
		$cache_buster = time();
		$retry_link = '<a href="' . add_query_arg( 'cb', $cache_buster, $og_permalink ) . '">' . __( 'retry', 'role-visibilty' ) . '</a>';

		// Everyone message
		$mistake_msg = sprintf( __( 'Oops! It looks like there was a mistake giving you access. Please %s.', 'role-visibility' ), $retry_link );

		// Get the option or return a msg
		if ( $post_id == $no_access_page && $is_admin_user ) {
			return __( 'Hi Admin!<br><br>The "No Access Page" is set to be visible by everyone, which is exactly what you want.', 'role-visibility' );

		} elseif ( $post_id == $no_access_page ) {
			return $alt_msg;

		} elseif ( $option == 'everyone' ) {
			return $mistake_msg;

		} elseif ( $option ) {
			if ( strpos( $option, ',' ) !== false ) {
				$r_array = explode( ',', $option );
				$r_array = array_map( 'trim', $r_array );

				foreach ( $r_array as $r ) {
					if ( $r == 'logged-in' ) {
						$option = $r;
					} else {
						$roles[] = $r;
					}
				}
			}

		} else  {
			$option = 'default';
		}

		// Further validation
		$post_type = get_post_type( $post_id );
		$post_type_settings = $SETTINGS->get_post_types( $post_type );
		$is_user_logged_in = is_user_logged_in();

		if ( $post_type_settings && $option == 'default' ) {
			$option = $post_type_settings[ 'default' ];
		}

		$missing_role = false;
		if ( $option == 'logged-in' && !empty( $roles ) ) {
			$user = wp_get_current_user();
			$user_roles = $user->roles;

			foreach ( $roles as $role ) {
				if ( in_array( $role, $user_roles ) ) {
					$missing_role = true;
				}
			}

		} elseif ( ( $option != 'logged-in' && $option != 'logged-out' ) || 
			 ( $is_user_logged_in && $option == 'logged-in' ) || 
			 ( !$is_user_logged_in && $option == 'logged-out' ) ) {
			return $mistake_msg;
		}

		// The message
		$template = $SETTINGS->get_message_template( $option, $roles );
		$message = sanitize_text_field( get_post_meta( $post_id, $SETTINGS->meta_key_msg, true ) );
		$option = str_replace( '-', '_', $option );
		
		if ( $post_message = get_post_meta( $post_id, $SETTINGS->meta_key_msg, true ) ) {
			$message = wp_kses_post( $post_message );

		} elseif ( $missing_role ) {
			$message = $SETTINGS->default_msg_missing_role;

		} elseif ( $post_type_settings && isset( $post_type_settings[ $option.'_msg' ] ) && sanitize_text_field( $post_type_settings[ $option.'_msg' ] ) ) {
			$message = sanitize_text_field( $post_type_settings[ $option.'_msg' ] );
			
		} elseif ( $option == 'logged_in' ) {
			$message = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_logged_in_msg', true );
			if ( !$message ) {
				$message = $SETTINGS->default_msg_logged_in;
			}
		} else {
			$message = $SETTINGS->default_msg_logged_out;
		}

		// Merge tags
		$template = str_replace( '{role_visibility_msg}', $message, $template );
		$template = str_replace( '{retry_link}', $retry_link, $template );
		
		if ( $option == 'logged_in' || $option == 'logged_out' ) {
			$redirect_to = get_the_permalink( $post_id );
			$redirect_to = add_query_arg( $current_query_args, $redirect_to );
		}

		// Login link
		if ( $option == 'logged_in' ) {
			$login_url = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_login_url', true );
			if ( $login_url ) {
				$login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
			} else {
				$login_url = wp_login_url( $redirect_to );
			}
			
			$login_msg = '<a href="' . $login_url . '">' . __( 'log in', 'role-visibility' ) . '</a>';
			$template = str_replace( '{login_link}', $login_msg, $template );
		}

		// Logout link
		if ( $option == 'logged_out' ) {
			$login_url = wp_logout_url( $redirect_to );
			
			$login_msg = '<a href="' . $login_url . '">' . __( 'log out', 'role-visibility' ) . '</a>';
			$template = str_replace( '{logout_link}', $login_msg, $template );
		}

		// register link
		if ( $option == 'logged_in' ) {
			$register_url = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_register_url', true );
			if ( $register_url ) {
				$register_url = add_query_arg( 'redirect_to', $redirect_to, $register_url );
			} else {
				$register_url = wp_registration_url();
			}

			$register_msg = '<a href="' . $register_url . '">' . __( 'register', 'role-visibility' ) . '</a>';
			$template = str_replace( '{register_link}', $register_msg, $template );
		}

		// Insert the message into the template
		$template = '<div class="access-denied-content" data-option="' . $option . '" data-roles="' . implode( ' ', $roles ) . '">' . wpautop( $template ) . '</div>';
		
		return $template;		
	} // End display_message()

}

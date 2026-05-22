<?php
/**
 * Non-common helpers
 */


/**
 * Define Namespaces
 */
namespace Apos37\RoleVisibility;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * The class
 */
class Helpers {

	/**
	 * Check if the post can be saved based on various conditions.
	 *
	 * @param int    $post_id       The ID of the post being saved.
	 * @param mixed  $the_post_type The post type or array of post types to validate against.
	 * @param string $nonce_value   The nonce value to check for validity.
	 * @param string $nonce_action  The nonce action to verify.
	 * 
	 * @return bool True if the post can be saved, false otherwise.
	 */
    public function can_save_post( $post_id, $the_post_type, $nonce_value, $nonce_action, $quick_edit = false ) {        
		// Verify that the nonce is valid
		if ( !isset( $_POST[ $nonce_value ] ) || 
			 !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_value ] ) ), $nonce_action ) ) {
			return false;
		}
	
		// Validate post type
        if ( !$quick_edit ) {
            global $post_type;
            if ( ( is_array( $the_post_type ) && !in_array( $post_type, $the_post_type ) ) ||
                 ( !is_array( $the_post_type ) && $the_post_type !== $post_type ) ) {
                return false;
            }
        
            // Common checks to prevent saving
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
                defined( 'DOING_AJAX' ) && DOING_AJAX ||
                get_post_status( $post_id ) === 'auto-draft' ||
                get_post_status( $post_id ) === 'trash' ||
                wp_is_post_revision( $post_id ) ||
                isset( $_REQUEST[ 'bulk_edit' ] ) ) {
                return false;
            }
        }

		// Check user permissions
		if ( $the_post_type == 'page' ) {
			if ( !current_user_can( 'edit_page', $post_id ) ) {
				return false;
			}
		} else {
			if ( !current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}
		}
	
		// All checks passed
		return true;
	} // End can_save_post()
	

	/**
     * Get current URL with query string
     *
     * @param boolean $params
     * @param boolean $domain
     * @return string
     */
    public function get_current_url( $params = true, $domain = true ) {
        if ( $domain === true ) {
            // Check if HTTP_HOST is set before using it
            if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
                $protocol = isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] !== 'off' ? 'https' : 'http';
                $domain_without_protocol = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_HOST' ] ) );
                $domain = $protocol.'://'.$domain_without_protocol;
            } else {
                // Handle case where HTTP_HOST is not set
                $domain = 'http://localhost';
            }
    
        } elseif ( $domain === 'only' ) {
            // Check if HTTP_HOST is set before using it
            if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
                $domain = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_HOST' ] ) );
                return $domain;
            } else {
                return 'localhost';
            }
    
        } else {
            $domain = '';
        }
    
        $uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
        $full_url = $domain.$uri;
    
        if ( !$params ) {
            return strtok( $full_url, '?' );
        } else {
            return $full_url;
        }
    } // End get_current_url()


	/**
     * Remove query strings from url without refresh
     *
     * @param null|string|array $qs
     * @param boolean $is_admin
     * @return void
     */
    public function remove_qs_without_refresh( $qs = null, $is_admin = false ) {
        // Get the current title
        $page_title = get_the_title();

        // Get the current url without the query string
        if ( !is_null( $qs ) ) {

            // Check if $qs is an array
            if ( !is_array( $qs ) ) {
                $qs = [ $qs ];
            }
            $new_url = remove_query_arg( $qs, $this->get_current_url() );

        } else {
            $new_url = $this->get_current_url( false );
        }

        // Write the script
        $args = [ 
            'title' => $page_title,
            'url'   => $new_url
        ];

        // Admin or not
        if ( $is_admin ) {
            $hook = 'admin_footer';
        } else {
            $hook = 'wp_footer';
        }

		// Handle
		$handle = ROLEVISIBILITY__TEXTDOMAIN . '_remove_qs';
		$args[ 'handle' ] = $handle;

        // Enqueue the script only when the shortcode is used
        wp_enqueue_script( $handle, ROLEVISIBILITY_JS_PATH . 'remove-qs.js', [ 'jquery' ], time(), true );

        // Add the script to the admin footer
        add_action( $hook, function() use ( $args ) {
            wp_localize_script( $args[ 'handle' ], $args[ 'handle' ], [
                'title' => $args[ 'title' ],
                'url'   => $args[ 'url' ]
            ] );
        } );

        // Return
        return;
    } // End remove_qs_without_refresh()


    /**
     * Convert timezone
     * 
     * @param string $date
     * @param string $format
     * @param string $timezone
     * @return string
     */
    public function convert_timezone( $date = null, $format = 'F j, Y g:i A', $timezone = null ) {
        // Get today as default
        if ( is_null( $date ) || !$date ) {
            $date = gmdate( 'Y-m-d H:i:s' );

        // Or else format it properly for converting purposes
        } else {

            // Check if the date is a timestamp
            if ( is_numeric( $date ) && (int)$date == $date ) {
                $date = gmdate( 'Y-m-d H:i:s', $date );
            } else {
                $date = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
            }
        }

        // Get the date in UTC time
        $date = new \DateTime( $date, new \DateTimeZone( 'UTC' ) );

        // Get the timezone string
        if ( !is_null( $timezone ) ) {
            $timezone_string = $timezone;
        } else {
            $timezone_string = wp_timezone_string();
        }

        // Set the timezone to the new one
        $date->setTimezone( new \DateTimeZone( $timezone_string ) );

        // Format it the way we way
        $new_date = $date->format( $format );

        // Return it
        return $new_date;
    } // End convert_timezone()

}
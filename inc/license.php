<?php
/**
 * Validate license
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
 * Instantiate the class
 */
add_action( 'init', function() {
	(new License())->init();
} );


/**
 * The class
 */
class License {

    /**
     * Options
     *
     * @var string
     */
    private $key_option = ROLEVISIBILITY__TEXTDOMAIN . '_license_id';
    private $data_option = ROLEVISIBILITY__TEXTDOMAIN . '_license_data';
    private $result_option = ROLEVISIBILITY__TEXTDOMAIN . '_license_result';
    private $has_been_validated_option = ROLEVISIBILITY__TEXTDOMAIN . '_license_hbv';
    private $license_key;
    

    /**
     * Constructor
     */
    public function __construct() {

        // Option keys
        $this->license_key = get_option( $this->key_option );
        
    } // End __construct()


    /**
     * Load on init
     */
    public function init() {

        // Initialize license validation
        add_action( 'init', [ $this, 'maybe_check_license' ] );

        // Validate license
        add_action( 'admin_notices', [ $this, 'invalid_license_notice' ] );

    } // End init()


    /**
     * Get the license results
     *
     * @return array|false
     */
    public function get_license_results() {
        $result = get_option( $this->result_option );
        return $result ? $this->unprocess( $result ) : false;
    } // End get_license_results()


    /**
     * Get the time we last checked for a valid license
     *
     * @return string|false
     */
    public function get_license_check_time() {
        $result = get_transient( $this->data_option );
        return $result ? $this->unprocess( $result ) : false;
    } // End get_license_check_time()


    /**
     * Maybe check the license based on timestamp
     *
     * @return void
     */
    public function maybe_check_license() {
        if ( !$this->license_key ) {
            return;
        }

        $time = $this->get_license_check_time();
        if ( !$time || ( time() - $time > DAY_IN_SECONDS ) ) {
            $this->check_license();
        }
    } // End maybe_check_license()


    /**
     * Check the license
     *
     * @param boolean $return_result
     * @return array|void
     */
    public function check_license( $return_result = false ) {
        // error_log( 'License check triggered at ' . date( 'Y-m-d H:i:s' ) );
        if ( !$this->license_key ) {
            return;
        }

        $api_url = ROLEVISIBILITY_AUTHOR_URI . 'wp-json/wpe-licenses/v1/validation';
        $response = wp_remote_post( $api_url, [
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'Expect'       => '',
            ],
            'body' => wp_json_encode( [
                'license_key' => $this->license_key,
                'site_url'    => home_url(),
                'text_domain' => ROLEVISIBILITY_TEXTDOMAIN
            ] ),
        ] );

        // Check for errors
        if ( is_wp_error( $response ) ) {
            $validation_result = [
                'status'  => 'error',
                'code'    => 500,
                'message' => __( 'Error communicating with the license validation API: ', 'css-organizer' ) .$response->get_error_message()
            ];
        } else {
            
            // Check the response code
            $status_code = wp_remote_retrieve_response_code( $response );
            if ( $status_code !== 200 ) {
                $validation_result = [
                    'status'  => 'invalid',
                    'code'    => $status_code,
                    'message' => __( 'Could not validate. Status code: ', 'css-organizer' ) . $status_code
                ];

            } else {
                
                // Process the response body
                $body = wp_remote_retrieve_body( $response );
                $validation_result = json_decode( $body, true );
            }            
        }        

        // Store the validation result
        $processed_result = $this->process( $validation_result );
        update_option( $this->result_option, $processed_result );
        
        $processed_time = $this->process( time() );
        set_transient( $this->data_option, $processed_time, DAY_IN_SECONDS );

        // Return the result
        if ( $return_result ) {
            return $validation_result;
        }
        return;
    } // End check_license()


    /**
     * Check if the license has been validated at least once
     *
     * @return boolean|int
     */
    public function has_been_validated( $return_timestamp = false ) {
        $timestamp = absint( get_option( $this->has_been_validated_option ) );
        $has_timestamp = $timestamp > 0;
        return $has_timestamp && $return_timestamp ? $timestamp : $has_timestamp;
    } // End has_been_validated()


    /**
     * Check for validity everywhere else
     *
     * @return boolean
     */
    public function has_valid_license() {
        $validation_result = $this->get_license_results();
    
        // Check if the result is an array and process the status
        if ( is_array( $validation_result ) ) {
            $status = $validation_result[ 'status' ] ?? '';

            // Check for a valid license
            if ( $status === 'active' ) {
                if ( !$this->has_been_validated() ) {
                    update_option( $this->has_been_validated_option, time(), false );
                }
                return true;
            } 

            // Check for an expired license with grace period
            if ( $status === 'expired' ) {
                $expiration_date = ( isset( $validation_result[ 'expires' ] ) && strtotime( $validation_result[ 'expires' ] ) ) ? strtotime( $validation_result[ 'expires' ] ) : false;
                if ( !$expiration_date ) {
                    return false;
                }

                $grace_period = 14 * DAY_IN_SECONDS;
                $current_time = time();

                // Check if within the grace period
                if ( ( $current_time - $expiration_date ) <= $grace_period ) {
                    return true;
                }
            }
        }
    
        return false;
    } // End has_valid_license()


    /**
     * Notice for invalid license
     *
     * @return void
     */
    public function invalid_license_notice() {
        $current_screen = get_current_screen();
        if ( $current_screen->id !== 'settings_page_' . ROLEVISIBILITY_TEXTDOMAIN && !$this->has_valid_license() ) {
            echo '<div class="notice notice-warning"><p>';

            /* translators: 1: Plugin name, 2: Settings page URL */
            echo wp_kses( sprintf( __( 'Your %1$s license is invalid or expired. Please enter a valid license key in the <a href="%2$s">settings</a>.', 'role-visibility' ),
                esc_html( ROLEVISIBILITY_NAME ),
                esc_url( ROLEVISIBILITY_SETTINGS_PATH )
            ), [ 'a' => [ 'href' => [], 'target' => [] ] ] );

            echo '</p></div>';
        }
    } // End invalid_license_notice()


    /**
     * Calculate key
     *
     * @return string
     */
    private function calculate_key() {
        return hash( 'sha256', $this->license_key, true );
    } // calculate_key()


    /**
     * Process
     *
     * @param mixed $data
     * @return string
     */
    private function process( $data ) {
        $iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
        $enc_s = openssl_encrypt( serialize( $data ), 'aes-256-cbc', $this->calculate_key(), 0, $iv );
        return base64_encode( $iv . $enc_s );
    } // End process()
    

    /**
     * Unprocess
     *
     * @param string $data
     * @return mixed
     */
    private function unprocess( $data ) {
        $data = base64_decode( $data );
        $iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
        $iv = substr( $data, 0, $iv_length );
        $enc_s = substr( $data, $iv_length );
        $decrypted = openssl_decrypt( $enc_s, 'aes-256-cbc', $this->calculate_key(), 0, $iv );
        return unserialize( $decrypted );
    } // End unprocess()

}
<?php
/**
 * Settings
 */


/**
 * Define Namespaces
 */
namespace Apos37\RoleVisibility;
use Apos37\RoleVisibility\License;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
add_action( 'init', function() {
	(new Settings())->init();
} );


/**
 * The class
 */
class Settings {
	
	/**
	 * Meta keys
	 * 
	 * @var string
	 */
	public $meta_key_option = 'role_visibility';
	public $meta_key_msg = 'role_visibility_msg';


	/**
	 * Default messages
	 * 
	 * @var string
	 */
	public $default_msg_logged_in;
	public $default_msg_logged_out;
	public $default_msg_missing_role;


	/**
	 * Default templates
	 * 
	 * @var string
	 */
	public $default_template_logged_in;
	public $default_template_logged_out;
	public $default_template_missing_role;


	/**
	 * Default post types
	 *
	 * @var array
	 */
	public $default_post_types;


    /**
	 * Default roles
	 *
	 * @var array
	 */
	public $default_roles;

	
	/**
	 * Options
	 *
	 * @var array
	 */
	public $options = [];


    /**
     * Constructor
     */
    public function __construct() {

		// Allow meta keys to be changed for older versions
		$this->meta_key_option = apply_filters( 'role_visibility_meta_key_option', $this->meta_key_option );
		$this->meta_key_msg = apply_filters( 'role_visibility_meta_key_msg', $this->meta_key_msg );

		// Default messages
		$this->default_msg_logged_in = gettext( 'Sorry, you do not have permission to access this page. Please contact the site administrator if you believe this is an error.' );

		$this->default_msg_logged_out = gettext( 'Oops, this page can only be viewed if you are logged out.' );

		$this->default_msg_missing_role = gettext( 'Sorry, you do not have permission to access this page. Please contact the site administrator if you believe this is an error.' );

		// Default templates
        $template_return_link = '<a href="' . home_url() . '">' . gettext( 'return to the homepage' ) . '</a>';

		$this->default_template_logged_in = '<div class="access-denied">
            <h2>' . gettext( 'Access Denied' ) . '</h2>
            <p>{role_visibility_msg}</p>
            <p>' . sprintf( 
                gettext( 'You can %1$s, %2$s, or %3$s.' ), 
                $template_return_link, 
                '{login_link}', 
                '{retry_link}' 
            ) . '</p>
        </div>';

		$this->default_template_logged_out = '<div class="access-denied">
            <p>{role_visibility_msg}</p>
            <p>' . sprintf( 
                gettext( 'You can %1$s, %2$s, or %3$s.' ), 
                $template_return_link,
                '{logout_link}',
                '{retry_link}'
            ) . '</p>
        </div>';

		$this->default_template_missing_role = '<div class="access-denied">
            <h2>' . gettext( 'Access Denied' ) . '</h2>
            <p>{role_visibility_msg}</p>
            <p>' . sprintf( 
                gettext( 'You can %1$s or %2$s.' ), 
                $template_return_link, 
                '{retry_link}' 
            ) . '</p>
        </div>';

		// Default post types
		$public_post_types = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $public_post_types as $public_post_type ) {
			if ( $public_post_type->name == 'attachment' ) {
				continue;
			}

			$this->default_post_types[] = [
				'post_type'      => $public_post_type->name,
				'default'        => 'everyone',
				'logged_in_msg'  => '',
				'logged_out_msg' => ''
			];
		}

        // Default roles
        $this->default_roles = [
            'administrator' => gettext( 'Administrator' ),
            'editor'        => gettext( 'Editor' ),
            'author'        => gettext( 'Author' ),
            'contributor'   => gettext( 'Contributor' ),
            'subscriber'    => gettext( 'Subscriber' ),
        ];
        
		// Set up the options
		$this->options = [
			'default'    => gettext( 'Default' ),
			'everyone'   => gettext( 'Everyone' ),
			'logged-in'  => gettext( 'Logged-In Only' ),
			'logged-out' => gettext( 'Logged-Out Only' )
		];

    } // End __construct()


	/**
     * Load on init only
     */
    public function init() {

        // Settings page
        add_action( 'admin_menu', [ $this, 'settings_page_submenu' ] );

        // Settings page fields
        add_action( 'admin_init', [  $this, 'settings_fields' ] );

		// JQuery and CSS
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


	/**
	 * Get the message template
	 *
	 * @param string $type
	 * @param string $roles
	 * @return string|false
	 */
	public function get_message_template( $type, $roles ) {
		$template = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_' . str_replace( '-', '_', $type ) . '_template', '' );
		if ( $template ) {
			return $template;
		}

		if ( !$template ) {
			if ( !empty( $roles ) && $type == 'logged-in' ) {
				return $this->default_template_missing_role;
			} elseif ( $type == 'logged-in' ) {
				return $this->default_template_logged_in;
			} elseif ( $type == 'logged-out' ) {
				return $this->default_template_logged_out;
			}
		}

		return false;
	} // End get_post_types()


	/**
	 * Get the post types and their defaults from our settings
	 *
	 * @return array
	 */
	public function get_post_types( $post_type_slug = null ) {
		$post_types = get_option( ROLEVISIBILITY__TEXTDOMAIN.'_post_types', [] );
		if ( empty( $post_types ) ) {
			$post_types = $this->default_post_types;
		}

		if ( !is_null( $post_type_slug ) ) {
			foreach ( $post_types as $post_type ) {
				if ( isset( $post_type[ 'post_type' ] ) && $post_type[ 'post_type' ] === $post_type_slug ) {
					return $post_type;
				}
			}
			return false;
		}
		return $post_types;
	} // End get_post_types()


    /**
	 * Get the roles from our settings
	 *
	 * @return array
	 */
	public function get_roles() {
        $saved_roles = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_roles', [] );
        if ( !empty( $saved_roles ) ) {
            
            if ( function_exists( 'get_editable_roles' ) ) {
                $editable_roles = get_editable_roles();
            } else {
                $editable_roles = [];
            }

            $roles = [];
            foreach ( $saved_roles as $saved_role ) {
                if ( isset( $saved_role[ 'role' ] ) ) {
                    $saved_role_slug = $saved_role[ 'role' ];
                    if ( isset( $editable_roles[ $saved_role_slug ] ) ) {
                        $label = $editable_roles[ $saved_role_slug ][ 'name' ];
                    } else {
                        $label = $saved_role_slug;
                    }
                    $roles[ $saved_role_slug ] = $label;
                }
            }
            return $roles;
        } else {
            return $this->default_roles;
        }
    } // End get_roles()


	/**
     * Settings page
     *
     * @return void
     */
    public function settings_page_submenu() {
        add_submenu_page(
            'options-general.php',
            ROLEVISIBILITY_NAME . ' — ' . __( 'Settings', 'role-visibility' ),
            ROLEVISIBILITY_NAME,
            'manage_options',
            ROLEVISIBILITY_TEXTDOMAIN,
            [ $this, 'settings_page' ],
            null
        );
    } // End settings_page_submenu()

    
    /**
     * Settings page
     *
     * @return void
     */
    public function settings_page() {
        global $current_screen;
        if ( $current_screen->id != 'settings_page_' . ROLEVISIBILITY_TEXTDOMAIN ) {
            return;
        }

        // Reset all settings
        if ( isset( $_GET[ 'reset_settings' ] ) && isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), 'wpe_reset_nonce' ) ) {
            
            $settings = $this->get_settings_fields( true );
            foreach ( $settings as $setting ) {
                delete_option( ROLEVISIBILITY__TEXTDOMAIN . '_' . $setting );
            }
            (new Helpers())->remove_qs_without_refresh( [ 'reset_settings', '_wpnonce' ], true );
        }
        ?>
        <style>
        h2 { margin: 3rem 0 1rem 0; }
        </style>
            <div class="wrap">
                <h1><?php echo get_admin_page_title() ?></h1>
                <form method="post" action="options.php">
                    <?php
                        settings_fields( ROLEVISIBILITY_TEXTDOMAIN.'-settings' );
                        do_settings_sections( ROLEVISIBILITY_TEXTDOMAIN.'-settings' );
                        submit_button();
                    ?>
                </form>
            </div>
        <?php
    } // End settings_page()


    /**
     * Store the settings fields here so we can call them on uninstall as well
     *
     * @return array
     */
    public function get_settings_fields( $return_keys_only = false ) {
        if ( isset( $_GET[ 'page' ] ) && sanitize_key( $_GET[ 'page' ] ) === ROLEVISIBILITY_TEXTDOMAIN ) {
            
            // Check license validation
            $LICENSE = new License();
            if ( isset( $_GET[ 'settings-updated' ] ) && sanitize_key( $_GET[ 'settings-updated' ] ) === 'true' ) { // phpcs:ignore
                $license = $LICENSE->check_license( true );
                $just_updated = true;
            } else {
                $license = $LICENSE->get_license_results();
                $just_updated = false;
            }
            
            if ( $license && isset( $license[ 'status' ] ) ) {
                if ( !$just_updated && $license[ 'status' ] == 'error' ) {
                    $license = $LICENSE->check_license( true );
                }

                $time = $LICENSE->get_license_check_time();
                $display_time = $time ? (new Helpers())->convert_timezone( $time, 'Y-m-d H:i:s T' ) : 'Unknown';

                $license_comments = '<span class="license-status ' . sanitize_key( $license[ 'status' ] ) . '" title="' . __( 'Last checked: ', 'role-visibility' ) . '' . $display_time . '">' . wp_kses_post( $license[ 'message' ] ) . '</span>';
            } else {
                $license_comments = '<em>' . sprintf( 
                    __( 'Make sure to add your website to your account on %s, then paste your License ID here.', 'role-visibility' ),
                    '<a href="' . ROLEVISIBILITY_AUTHOR_URI . '" target="_blank">' . str_replace( [ 'https://', '/' ], '', ROLEVISIBILITY_AUTHOR_URI ) . '</a>',
                ) . '</em>';
            }
        } else {
            $license_comments = '';
        }

        $page_options = [
			'' => '-- ' . __( 'Select a Page', 'role-visibility' ) . ' --'
		];
		$pages = get_pages();
		if ( !empty( $pages ) ) {
			foreach ( $pages as $page ) {
				$page_options[ $page->ID ] = $page->post_title;
			}
		}

		$post_types = $this->default_post_types;

		$post_type_choices = [
			'' => '-- ' . __( 'Select a Post Type', 'role-visibility' ) . ' --'
		];
		foreach ( $post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type[ 'post_type' ] );
			if ( $post_type_object ) {
				$post_type_label = $post_type_object->label;
				$post_type_choices[ $post_type[ 'post_type' ] ] = $post_type_label;
			}
		}

		$post_type_option_choices = $this->options;
		unset( $post_type_option_choices[ 'default' ] );
		$post_type_options = [
			[
				'type'     => 'select',
				'name'     => 'post_type',
				'label'    => __( 'Post Type', 'role-visibility' ),
				'choices'  => $post_type_choices
			],
			[
				'type'     => 'select',
				'name'     => 'default',
				'label'    => __( 'Default', 'role-visibility' ),
				'choices'  => $post_type_option_choices
			],
		];

		$post_type_defaults = [];
		foreach ( $post_types as $post_type ) {
			$post_type_defaults[] = [
				'post_type'      => $post_type[ 'post_type' ],
				'default'        => 'everyone',
				'logged_in_msg'  => '',
				'logged_out_msg' => ''
			];
		}

        $roles = get_editable_roles();

		$role_choices =[
			'' => '-- ' . __( 'Select a Post Type', 'role-visibility' ) . ' --'
		];
		foreach ( $roles as $key => $role ) {
            $role_choices[ $key ] = $role[ 'name' ];
		}

		$role_options = [
			[
				'type'     => 'select',
				'name'     => 'role',
				'label'    => __( 'Role', 'role-visibility' ),
				'choices'  => $role_choices
			],
		];

		$role_defaults = [];
		foreach ( $this->default_roles as $key => $default_role ) {
			$role_defaults[] = [
				'role' => $key
			];
		}

        $fields = [
            [
                'key'        => 'license_id',
                'title'      => __( 'License ID', 'role-visibility' ),
                'type'       => 'text',
                'section'    => 'license', 
                'comments'   => '<br>'.$license_comments
            ],
            [ 
                'key'       => 'no_access_page', 
                'title'     => __( 'No Access Page', 'role-visibility' ), 
                'type'      => 'select',
                'section'   => 'general', 
                'comments'  => '<br><em>' . sprintf( 
					__( 'Select the page that you want users to be redirected to when they have no access. The page must include the %1$s shortcode.', 'role-visibility' ), 
					'<code>[role_visibility_msg]</code>'
				) . '</em>',
				'options'   => $page_options,
            ],
            [
                'key'        => 'login_url',
                'title'      => __( 'Login URL', 'role-visibility' ),
                'type'       => 'text',
				'input_type' => 'url',
                'section'    => 'general', 
                'comments'   => '<br><em>' . __( 'The url of your login page.', 'role-visibility' ) . '</em>',
				'default'    => wp_login_url(),
            ],
            [
                'key'        => 'register_url',
                'title'      => __( 'Register URL', 'role-visibility' ),
                'type'       => 'text',
				'input_type' => 'url',
                'section'    => 'general', 
                'comments'   => '<br><em>' . __( 'The url of your registration page.', 'role-visibility' ) . '</em>',
				'default'    => wp_registration_url(),
            ],
            [
                'key'        => 'uninstall_reset',
                'title'      => __( 'Delete All Data on Uninstall', 'role-visibility' ),
                'type'       => 'checkbox',
                'section'    => 'general',
                'comments'   => '<em>' . __( 'This will clear all of your settings from this page and all of your posts and pages when you uninstall the plugin.', 'role-visibility' ) . '</em>',
            ],
            [
                'key'        => 'reset_settings_page',
                'title'      => __( 'Reset Plugin Settings to Default', 'role-visibility' ),
                'type'       => 'desc',
                'section'    => 'general',
                'comments'   => '<a id="reset-settings-page" class="button-secondary" href="' . add_query_arg( [
                    'reset_settings' => true,
                    '_wpnonce'       => wp_create_nonce( 'wpe_reset_nonce' )
                ] ) . '">' . __( 'Reset Now', 'role-visibility' ) . '</a><br><em>' . __( 'Does not reset posts/pages, just the settings on this page.', 'role-visibility' ) . '</em>',
            ],
			[ 
                'key'       => 'logged_in_msg', 
                'title'     => __( 'Default Message for Logged-In Only', 'role-visibility' ), 
                'type'      => 'text', 
                'section'   => 'messages', 
                'comments'  => '<br><em>' . __( 'This is the default message that people will see if they are logged out and the page is set to "Logged-In Only". You can override this on the individual post/page settings.', 'role-visibility' ) . '</em>',
				'width'		=> '100%',
                'default'   => $this->default_msg_logged_in
            ],
            [ 
                'key'       => 'logged_in_template', 
                'title'     => __( 'Message Template for Logged-In Only', 'role-visibility' ), 
                'type'      => 'wp_editor',
                'section'   => 'messages', 
                'comments'  => '<em>' . sprintf(
                    __( 'This is the template used to display the message you set above for people that are logged out and the page is set to "Logged-In Only". You must include %1$s to display the message. If you want them to have the ability to login and be redirected to the page they were trying to access, include %2$s. If you want to add a retry link, include %3$s. If you want to add a registration link, include %4$s.', 'role-visibility' ),
                    '<code>{role_visibility_msg}</code>',
                    '<code>{login_link}</code>',
                    '<code>{retry_link}</code>',
                    '<code>{register_link}</code>'
                ) . '</em>',
                'default'   => $this->default_template_logged_in
            ],
			[ 
                'key'       => 'logged_out_msg', 
                'title'     => __( 'Default Message for Logged-Out Only', 'role-visibility' ), 
                'type'      => 'text',
                'section'   => 'messages', 
                'comments'  => '<br><em>' . __( 'This is the default message that people will see if they are logged in and the page is set to "Logged-Out Only". Useful for custom login pages. You can override this on the individual post/page settings.', 'role-visibility' ) . '</em>',
				'width'		=> '100%',
                'default'   => $this->default_msg_logged_out
            ],
            [ 
                'key'       => 'logged_out_template', 
                'title'     => __( 'Message Template for Logged-Out Only', 'role-visibility' ), 
                'type'      => 'wp_editor',
                'section'   => 'messages', 
				'comments'  => '<em>' . sprintf(
					__( 'This is the template used to display the message you set above for people that are logged in and the page is set to "Logged-Out Only". You must include %1$s to display the message. If you want to add a lougout link, include %2$s. If you want to add a retry link, include %3$s.', 'role-visibility' ),
					'<code>{role_visibility_msg}</code>',
                    '<code>{logout_link}</code>',
                    '<code>{retry_link}</code>'
				) . '</em>',
                'default'   => $this->default_template_logged_out
            ],
			[ 
                'key'       => 'missing_role_msg', 
                'title'     => __( 'Missing Role Message', 'role-visibility' ), 
                'type'      => 'text',
                'section'   => 'messages', 
                'comments'  => '<br><em>' . __( 'This is the message that users will receive when they try to access a page that is set to logged-in only with a specific role that they do not have. You can override this on the individual post/page settings.', 'role-visibility' ) . '</em>',
				'width'		=> '100%',
                'default'   => $this->default_msg_missing_role
            ],
            [ 
                'key'       => 'missing_role_template', 
                'title'     => __( 'Message Template for Missing Role', 'role-visibility' ), 
                'type'      => 'wp_editor',
                'section'   => 'messages', 
				'comments'  => '<em>' . sprintf(
					__( 'This is the template used to display the message you set above for people that are trying to access a page that they do not have the proper role for. You must include %1$s to display the message. If you want to add a retry link, include %2$s.', 'role-visibility' ),
					'<code>{role_visibility_msg}</code>',
                    '<code>{retry_link}</code>'
				) . '</em>',
                'default'   => $this->default_template_missing_role
            ],
			[
                'key'       => 'post_types',
                'title'     => __( 'Which Post Types to Enable', 'role-visibility' ),
                'type'      => 'text_plus',
                'section'   => 'post_types',
                'comments'  => '<br><em>' . __( 'Set the post types you want to include role visibility options for, and choose a default access option for it.', 'role-visibility' ) . '</em>',
                'button'    => __( 'Add New Post Type +', 'role-visibility' ),
				'options'   => $post_type_options,
				'default'   => $post_type_defaults
            ],
            [
                'key'       => 'roles',
                'title'     => __( 'Which Roles to Include', 'role-visibility' ),
                'type'      => 'text_plus',
                'section'   => 'roles',
                'comments'  => '<br><em>' . __( 'Add the roles you want to include for the "Logged-In Only" option.', 'role-visibility' ) . '</em>',
                'button'    => __( 'Add New Role +', 'role-visibility' ),
				'options'   => $role_options,
				'default'   => $role_defaults
            ]
        ];

        // Return
        if ( $return_keys_only ) {
            $field_keys = [];
            foreach ( $fields as $field ) {
                $field_keys[] = $field[ 'key' ];
            }
            return $field_keys;
        }
        return $fields;
    } // End get_settings_fields()


    /**
     * Settings fields
     *
     * @return void
     */
    public function settings_fields() {
        // Slug
        $slug = ROLEVISIBILITY_TEXTDOMAIN.'-settings';

        /**
         * Sections
         */
        $sections = [
            [ 'license', __( 'License', 'role-visibility' ), '' ],
            [ 'general', __( 'General', 'role-visibility' ), '' ],
			[ 'messages', __( 'Default Messages', 'role-visibility' ), '' ],
            [ 'post_types', __( 'Post Types', 'role-visibility' ), '' ],
            [ 'roles', __( 'Roles', 'role-visibility' ), '' ],
        ];

        // Validate license
        if ( isset( $_GET[ 'page' ] ) && sanitize_key( $_GET[ 'page' ] ) === ROLEVISIBILITY_TEXTDOMAIN ) {
            if ( isset( $_GET[ 'settings-updated' ] ) ) {
                (new License())->check_license();
            }
        }

        // Iter the sections
        foreach ( $sections as $section ) {
            add_settings_section(
                $section[0],
                $section[1],
                $section[2],
                $slug,
            );
        }

        /**
         * Fields
         */
        $fields = $this->get_settings_fields( false );

        // Iter the fields
        foreach ( $fields as $field ) {
            $option_name = ROLEVISIBILITY__TEXTDOMAIN.'_'.$field[ 'key' ];
            $callback = 'settings_field_'.$field[ 'type' ];
            $args = [
                'id'    => $option_name,
                'class' => $option_name,
                'name'  => $option_name,
            ];

            if ( isset( $field[ 'title' ] ) ) {
                $args[ 'title' ] = $field[ 'title' ];
            }

            if ( isset( $field[ 'input_type' ] ) ) {
                $args[ 'input_type' ] = $field[ 'input_type' ];
            }

            if ( isset( $field[ 'width' ] ) ) {
                $args[ 'width' ] = $field[ 'width' ];
            }

            if ( isset( $field[ 'comments' ] ) ) {
                $args[ 'comments' ] = $field[ 'comments' ];
            }

            if ( isset( $field[ 'button' ] ) ) {
                $args[ 'button' ] = $field[ 'button' ];
            }

            if ( isset( $field[ 'options' ] ) ) {
                $args[ 'options' ] = $field[ 'options' ];
            }

            if ( isset( $field[ 'default' ] ) ) {
                $args[ 'default' ] = $field[ 'default' ];
            }

            if ( isset( $field[ 'revert' ] ) ) {
                $args[ 'revert' ] = $field[ 'revert' ];
            }

            register_setting( $slug, $option_name );
            add_settings_field( $option_name, $field[ 'title' ], [ $this, $callback ], $slug, $field[ 'section' ], $args );
        }
    } // End settings_fields()


    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_desc( $args ) {
        echo wp_kses_post( $args[ 'comments' ] );
    } // settings_field_text()
    

	/**
     * Custom callback function to print url field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_text( $args ) {
		$input_type = isset( $args[ 'input_type' ] ) ? $args[ 'input_type' ] : 'text';
        $width = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '30rem';
        $comments = isset( $args[ 'comments' ] ) ? $args[ 'comments' ] : '';
        $default = isset( $args[ 'default' ] )  ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }
        printf(
            '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" style="width: %5$s"/>%6$s',
			esc_attr( $input_type ),
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( $value ),
			esc_attr( $width ),
			wp_kses_post( $comments )
        );
    } // settings_field_url()


	/**
     * Custom callback function to print textarea field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_textarea( $args ) {
        $value = get_option( $args[ 'name' ] );
        ?>
            <textarea id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>"  rows="<?php echo esc_attr( $args[ 'rows' ] ); ?>" cols="<?php echo esc_attr( $args[ 'cols' ] ); ?>"><?php echo wp_kses_post( $value ); ?></textarea> <?php echo wp_kses_post( $args[ 'comments' ] ); ?>
        <?php
    } // settings_field_textarea()


	/**
	 * Custom callback function to print wp_editor field
	 *
	 * @param array $args
	 * @return void
	 */
	public function settings_field_wp_editor( $args ) {
		$default = isset( $args[ 'default' ] ) ? $args[ 'default' ] : '';
		$value = get_option( $args[ 'name' ], $default );

		// Check for the revert condition
		if ( isset( $args['revert'] ) && $args['revert'] == true && trim( $value ) == '' ) {
			$value = $default;
		}

		// Output the wp_editor
		wp_editor( $value, esc_attr( $args['id'] ), [
			'textarea_name' => esc_attr( $args[ 'name' ] ),
			'media_buttons' => true,
			'textarea_rows' => 10,
		] );

		// Output comments if provided
		if ( isset( $args[ 'comments' ] ) ) {
			echo '<p class="description">' . wp_kses_post( $args[ 'comments' ] ) . '</p>';
		}
	} // settings_field_wp_editor()


	/**
	 * Custom callback function to print text plus field
	 *
	 * @param array $args The field properties
	 */
    public function settings_field_text_plus( $args ) {
        $fields = $args[ 'options' ];
		$default = isset( $args[ 'default' ] )  ? $args[ 'default' ] : '';
        $values = get_option( $args[ 'name' ], $default );

        // Allowed HTML
        $allowed_html = [
            'a'   => [
                'href'        => [],
                'id'          => []
            ],
            'br' => [],
            'div' => [
                'class'       => [],
                'id'          => [],
                'data-row'    => [],
                'data-name'   => [],
            ],
            'input' => [
                'type'        => [],
                'name'        => [],
                'value'       => [],
                'id'          => [],
                'placeholder' => [],
                'class'       => [],
                'required'    => [],
                'disabled'    => [],
                'data-type'   => []
            ],
            'select' => [
                'name'        => [],
                'id'          => [],
                'class'       => [],
                'data-type'   => []
            ],
            'option' => [
                'value'       => [],
                'selected'    => []
            ],
            'button' => [
                'type'        => [],
                'id'          => [],
                'class'       => [],
                'data-name'   => [],
				'disabled'    => []
			],
			'em' => [],
        ];

        // Count
        $incl_class = empty( $values ) ? ' empty' : '';

		// Disable add button
		$choice_qty = count( $fields[0][ 'choices' ] ) -1;
		if ( ( !empty( $values ) && count( $values ) >= $choice_qty ) ||
			 ( empty( $values ) && count( $default ) >= $choice_qty ) ) {
			$disable_add_btn = ' disabled="disabled"';
		} else {
			$disable_add_btn = '';
		}

        // Start with the add new field link
        $results = '<button type="button" class="button add-new-field" data-name="'.$args[ 'name' ].'"' . $disable_add_btn . '>' . $args[ 'button' ] . '</button><br><br>
        <div id="fields_container_'.$args[ 'name' ].'" class="fields_container'.$incl_class.'" data-name="'.$args[ 'name' ].'">';
            // Add the rows
            if ( !empty( $values ) ) {
                foreach ( $values as $index => $value ) {
                    $results .= $this->create_text_plus_row( $fields, $args[ 'name' ], $value, $index );
                }
            } else {
                $results .= $this->create_text_plus_row( $fields, $args[ 'name' ], [], 0 );
            }
            
        // End container
        $results .= '</div>';

		// Comments
		if ( isset( $args[ 'comments' ] ) ) {
			$results .= '<div class="comments">' . $args[ 'comments' ] . '</div>';
		}

        // Echo
        echo wp_kses( $results, $allowed_html );
	} // End settings_field_text_plus()


    /**
     * Create a row for text+ fields
     *
     * @param array $fields
     * @param string $field_name
     * @param array $values
     * @param int $index
     * @return string
     */
    public function create_text_plus_row( $fields, $field_name, $values, $index ) {
        // Start row container
        $results = '<div class="text-plus-row" data-row="'.$index.'">';

            // Iter the fields
            foreach ( $fields as $field ) {
                $type = $field[ 'type' ];
                $input_type = isset( $field[ 'input_type' ] ) ? $field[ 'input_type' ] : false;
                $name = $field[ 'name' ];
                $label = $field[ 'label' ];
                $class = isset( $field[ 'class' ] ) ? ' class="'.$field[ 'class' ].'"' : '';
                $lock = isset( $field[ 'lock' ] ) && $field[ 'lock' ] ? true : false;
                
                // The value
                $field_value = '';
                if ( isset( $values[ $name ] ) ) {
                    if ( $type == 'number' ) {
                        $field_value = absint( $values[ $name ] );
                    } elseif ( $input_type == 'metakey' ) {
                        $field_value = sanitize_key( $values[ $name ] );
                    } else {
                        $field_value = sanitize_text_field( $values[ $name ] );
                    }
                }
                
                if ( $lock ) {
                    $incl_disabled = ' disabled="disabled"';
                } else {
                    $incl_disabled = '';
                }
                    
                // The input
                switch ( $type ) {
                    case 'select':
                        $results .= '<select name="'.$field_name.'['.$index.']['.$name.']" data-type="'.$name.'"'.$class.'>';

                        foreach ( $field[ 'choices' ] as $key => $label ) {
                            $is_selected = ( $field_value == $key ) ? ' selected' : '';
                            $results .= '<option value="'.$key.'"'.$is_selected.'>'.$label.'</option>';
                        }

                        $results .= '</select>';
                        break;

                    default:
                        $results .= '<input type="'.$type.'" name="'.$field_name.'['.$index.']['.$name.']" data-type="'.$name.'" value="'.$field_value.'"'.$class.' placeholder="'.$label.'" required="required"'.$incl_disabled.'/>';
                        break;
                }
            }

            // Remove button
            $results .= '<div><button type="button" class="button remove-row">(-) ' . __( 'Delete', 'role-visibility' ) . '</button></div><div class="warning-message"></div>';

        // End container
        $results .= '</div>';

        // Return
        return $results;
    } // End create_text_plus_row()


    /**
     * Custom callback function to print select field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_select( $args ) {
        $default = isset( $args[ 'default' ] ) ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }
        ?>
            <select id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>">
                <?php 
                if ( isset( $args[ 'options'] ) ) {
                    foreach ( $args[ 'options'] as $key => $option ) {
                        ?>
                        <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $value ); ?>><?php echo esc_attr( $option ); ?></option>
                        <?php 
                    }
                }
                ?>
            </select> <?php echo isset( $args[ 'comments' ] ) ? wp_kses_post( $args[ 'comments' ] ) : ''; ?>
        <?php
    } // settings_field_select()


    /**
     * Custom callback function to print checkbox field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_checkbox( $args ) {
        $value = get_option( $args[ 'name' ] );
        $comments = isset( $args[ 'comments' ] ) ? $args[ 'comments' ] : '';
        ?>
            <label>
                <input type="checkbox" id="<?php echo esc_attr( $args[ 'name' ] ); ?>" name="<?php echo esc_attr( $args[ 'name' ] ); ?>" value="1" <?php checked( $value, 1 ) ?> /> <?php echo isset( $args[ 'label' ] ) ? esc_attr( $args[ 'label' ] ) : ''; ?> <?php echo wp_kses_post( $comments ); ?>
            </label>
        <?php
    } // End settings_field_checkbox()

    
    /**
     * Sanitize checkbox
     *
     * @param int $value
     * @return void
     */
    public function sanitize_checkbox( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkbox()


	/**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'settings_page_' . ROLEVISIBILITY_TEXTDOMAIN ) {
            return;
        }

		// Register and enqueue your JavaScript
        wp_register_script( ROLEVISIBILITY_TEXTDOMAIN.'-settings', ROLEVISIBILITY_JS_PATH.'settings.js', [ 'jquery' ], time(), true );
		wp_enqueue_script( ROLEVISIBILITY_TEXTDOMAIN.'-settings' );

		// How many post types are there
		$post_type_count = count( $this->default_post_types );
        $role_count = count( get_editable_roles() );

        // Localize the script with translation messages
        wp_localize_script( ROLEVISIBILITY_TEXTDOMAIN.'-settings', ROLEVISIBILITY__TEXTDOMAIN, [
            'emptyLicenseId'    => __( 'Please enter your License ID', 'role-visibility' ),
            'confirmReset'      => __( 'Are you sure you want to reset the settings? This action cannot be undone.', 'role-visibility' ),
            'duplicatePostType' => __( 'Duplicate post type detected! It must be unique.', 'role-visibility' ),
            'duplicateRole'     => __( 'Duplicate role detected! It must be unique.', 'role-visibility' ),
			'postTypeCount'     => $post_type_count,
            'roleCount'         => $role_count,
        ] );

		// Register and enqueue your CSS
		wp_enqueue_style( ROLEVISIBILITY_TEXTDOMAIN . '-styles', ROLEVISIBILITY_CSS_PATH . 'settings.css', [], time() );
    } // End enqueue_scripts()

}

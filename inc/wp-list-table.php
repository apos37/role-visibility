<?php
/**
 * WP List Table (admin columns)
 */


/**
 * Define Namespaces
 */
namespace Apos37\RoleVisibility;
use Apos37\RoleVisibility\License;
use Apos37\RoleVisibility\Settings;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */

new WpListTable();


/**
 * The class
 */
class WpListTable {

	/**
	 * Nonce
	 *
	 * @var string
	 */
	private $quick_edit_nonce = ROLEVISIBILITY__TEXTDOMAIN . '_quick_edit_nonce';
	private $bulk_edit_nonce = ROLEVISIBILITY__TEXTDOMAIN . '_bulk_edit_nonce';


    /**
     * Constructor
     */
    public function __construct() {

		// Check license validity
        if ( !(new License())->has_been_validated() ) {
            return;
        }
        
		 // Post/Page Column
		$post_types = (new Settings())->get_post_types();
		
		if ( !empty( $post_types ) ) {
			foreach( $post_types as $pt ) {
				add_filter( 'manage_'.$pt[ 'post_type' ].'_posts_columns', [ $this, 'posts_column' ] );
				add_action( 'manage_'.$pt[ 'post_type' ].'_posts_custom_column', [ $this, 'posts_col_content' ], 10, 2 );
			}
			add_action( 'admin_head', [ $this, 'posts_col_width' ] );
		}

		// The quick edit / bulk edit box
		add_action( 'bulk_edit_custom_box', [ $this, 'quick_edit_box' ], 10, 2 );
		add_action( 'quick_edit_custom_box', [ $this, 'quick_edit_box' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_post' ] );

		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_' . ROLEVISIBILITY__TEXTDOMAIN . '_save_bulk_edit', [ $this, 'ajax_bulk_edit' ] );

    } // End __construct()


	/**
     * Add a column in the posts admin list
     *
     * @param array $columns
     * @return array
     */
    public function posts_column( $columns ) {
        $columns[ 'role_visibility' ] = __( 'Role Visibility', 'role-visibility' );
        return $columns;
    } // End posts_column()


    /**
     * The column content
     *
     * @param string $column
     * @param int $post_id
     * @return void
     */
    public function posts_col_content( $column, $post_id ) {
        // Image column
        if ( 'role_visibility' === $column ) {

			$SETTINGS = new Settings();
			$options = $SETTINGS->options;

			$no_access_page = get_option( ROLEVISIBILITY__TEXTDOMAIN . '_no_access_page', true );
			if ( $no_access_page && $no_access_page == $post_id ) {
				echo '<span data-option="everyone">' . esc_attr( $options[ 'everyone' ] ) . '</span>';

			} else {

				$post_types = $SETTINGS->get_post_types();
				$post_type = get_post_type();

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
		
				// Get the role visibility        
				$role_visibility = get_post_meta( $post_id, $SETTINGS->meta_key_option, true );
				if ( $role_visibility ) {
					$role_visibility = sanitize_text_field( $role_visibility );

					if ( strpos( $role_visibility, ',' ) !== false ) {
						$r_array = explode( ',', $role_visibility );
						$r_array = array_map( 'trim', $r_array );

						// Store new roles
						$roles_visibility = [];
		
						// Cycle through each
						foreach ( $r_array as $r ) {
							if ( in_array( $r, array_keys( $options ) ) ) {
								$span = 'option';
							} else {
								$span = 'role';
							}
							if ( isset( $options[ $r ] ) ) {
								$roles_visibility[] = '<span data-' . $span . '="' . $r . '">' . sanitize_text_field( $options[ $r ] ) . '</span>';
							} else {
								$roles_visibility[] = '<span data-' . $span . '="' . $r . '">' . ucfirst( $r ) . '</span>';
							}
						}
						echo wp_kses_post( implode( ', ', $roles_visibility ) );

					} elseif ( isset( $options[ $role_visibility ] ) ) {
						$label = ( $role_visibility == 'default' ) ? $options[ 'default' ] . ' (' . $default . ')' : $options[ $role_visibility ];
						echo '<span data-option="' . esc_attr( $role_visibility ) . '">' . esc_attr( $label ) . '</span>';

					}
				} else  {
					echo'<span data-option="default">' .  esc_attr( $options[ 'default' ] ).' ('.esc_attr( $default ).')</span>';
				}
			}
        }
    } // End posts_col_content()


    /**
     * Column width
     *
     * @return void
     */
    public function posts_col_width() {
        echo '<style type="text/css">
        .column-role_visibility {
            width: 10%;
        }
        </style>';
    } // End posts_col_width()

	
	/**
	 * Quick Edit
	 * Instructions: https://webberzone.com/add-custom-fields-to-quick-edit-and-bulk-edit/
	 *
	 * @param string $column_name
	 * @return void
	 */
	public function quick_edit_box( $column_name, $current_post_type ) {
		// Our own section
		switch ( $column_name ) {
			case 'role_visibility': // This must match a column name, which means one must exist

				$post_types = (new Settings())->get_post_types();
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
	
				// Create a nonce field
				if ( current_filter() === 'quick_edit_custom_box' ) {
					wp_nonce_field( $this->quick_edit_nonce, $this->quick_edit_nonce );
				} else {
					wp_nonce_field( $this->bulk_edit_nonce, $this->bulk_edit_nonce );
				}

				// Instantiate
				$SETTINGS = new Settings();
				$options = $SETTINGS->options;
				$roles = $SETTINGS->get_roles();
	
				// Default label
				$default = 'Everyone';
				foreach ( $post_types as $pt ) {
					if ( $pt[ 'post_type' ] == $current_post_type ) {
						$def_key = $pt[ 'default' ];
						if ( in_array( $def_key, array_keys( $options ) ) ) {
							$default = $options[ $def_key ];
						}
						break;
					}
				}

				// Add the fields
				?>
				<fieldset class="inline-edit-col-left col-<?php echo esc_attr( $column_name ); ?>">
					<div class="inline-edit-col quick-edit-<?php echo esc_attr( $column_name ); ?> option">
						<label class="inline-edit-group">
							<span><?php esc_html_e( 'Role Visibility', 'role-visibility' ); ?></span>
							<select name="<?php echo esc_attr( $SETTINGS->meta_key_option ); ?>[]" class="role-visibility-select">
								<?php foreach ( $options as $key => $label ) : ?>
									<?php $label = $key == 'default' ? $label.' ('.$default.')' : $label; ?>
									<option value="<?php echo esc_attr( $key ); ?>">
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
	
					<!-- Role Selection -->
					<div class="inline-edit-col quick-edit-<?php echo esc_attr( $column_name ); ?> roles">
						<?php foreach ( $roles as $key => $value ) : ?>
							<div class="<?php echo esc_attr( $column_name ); ?>-selection">
								<label class="role_visibility-labels">
									<input type="checkbox" name="<?php echo esc_attr( $SETTINGS->meta_key_option ); ?>[]" value="<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( $value ); ?>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<?php
				break;
		}
	} // End quick_edit_box()


	/**
	 * Save the posts
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

        if ( !(new Helpers())->can_save_post( $post_id, $post_types, $this->quick_edit_nonce, $this->quick_edit_nonce, true ) ) {
            return;
        }
	
		/* OK, it's safe for us to save the data now. */
     
        // Option
		$option_mk = $SETTINGS->meta_key_option;
        if ( !isset( $_POST[ $option_mk ] ) ) {
            return;
        }

        $roles = filter_var_array( wp_unslash( $_POST[ $option_mk ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $my_data = implode( ', ', $roles );
        update_post_meta( $post_id, $option_mk, $my_data );
	} // End save_post()

	
	/**
	 * Enqueue scripts
	 *
	 * @param string $hook
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'edit.php' !== $hook ) {
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

		$handle = ROLEVISIBILITY_TEXTDOMAIN.'-wp-list-table';
	
		// Register and enqueue your JavaScript
        wp_register_script( $handle, ROLEVISIBILITY_JS_PATH.'wp-list-table.js', [ 'jquery' ], ROLEVISIBILITY_VERSION, true );
		wp_enqueue_script( $handle );

        // Localize the script with translation messages
        wp_localize_script( $handle, ROLEVISIBILITY__TEXTDOMAIN, [
            'nonce' => wp_create_nonce( $this->bulk_edit_nonce ),
        ] );

		// Register and enqueue your CSS
		wp_enqueue_style( $handle, ROLEVISIBILITY_CSS_PATH . 'wp-list-table.css', [], ROLEVISIBILITY_VERSION );
	} // End enqueue_scripts()


	/**
	 * Ajax for bulk edit
	 *
	 * @return void
	 */
	public function ajax_bulk_edit() {
		// Security check.
		check_ajax_referer( $this->bulk_edit_nonce, $this->bulk_edit_nonce );
	
		// Get the post IDs.
		$post_ids = isset( $_POST[ 'post_ids'] ) ? wp_parse_id_list( wp_unslash( $_POST[ 'post_ids' ] ) ) : [];
		
		// Instantiate
		$SETTINGS = new Settings();

		// Option
		$option_mk = $SETTINGS->meta_key_option;
		if ( isset( $_POST[ $option_mk ] ) ) {

			$roles = sanitize_text_field( wp_unslash( $_POST[ $option_mk ] ) );
			
			foreach ( $post_ids as $post_id ) {
				if ( !current_user_can( 'edit_post', $post_id ) ) {
					continue;
				}
				update_post_meta( $post_id, $option_mk, $roles );
			}
		}
	
		// Send success response
		wp_send_json_success();
	} // End ajax_bulk_edit()
}

<?php
/**
 * Plugin Name:         Role Visibility
 * Plugin URI:          https://pluginrx.com/plugin/role-visibility/
 * Description:         Manage visibility of posts and pages by role or guest.
 * Version:             1.1.1
 * Requires at least:   6.0
 * Tested up to:        7.0
 * Requires PHP:        8.0
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         role-visibility
 * License:             Proprietary
 * License URI:         https://pluginrx.com/proprietary-license-agreement/
 * Created on:          October 15, 2024
 * Premium:             true
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
 * Defines
 */
$plugin_data = get_file_data( __FILE__, [
    'name'         => 'Plugin Name',
    'description'  => 'Description',
    'version'      => 'Version',
    'plugin_uri'   => 'Plugin URI',
    'requires_php' => 'Requires PHP',
    'textdomain'   => 'Text Domain',
    'author'       => 'Author',
    'author_uri'   => 'Author URI',
    'discord_uri'  => 'Discord URI'
] );

// Versions
define( 'ROLEVISIBILITY_VERSION', $plugin_data[ 'version' ] );
define( 'ROLEVISIBILITY_MIN_PHP_VERSION', $plugin_data[ 'requires_php' ] );

// Names
define( 'ROLEVISIBILITY_NAME', $plugin_data[ 'name' ] );
define( 'ROLEVISIBILITY_TEXTDOMAIN', $plugin_data[ 'textdomain' ] );
define( 'ROLEVISIBILITY__TEXTDOMAIN', str_replace( '-', '_', ROLEVISIBILITY_TEXTDOMAIN ) );
define( 'ROLEVISIBILITY_AUTHOR', $plugin_data[ 'author' ] );
define( 'ROLEVISIBILITY_AUTHOR_URI', $plugin_data[ 'author_uri' ] );
define( 'ROLEVISIBILITY_PLUGIN_URI', $plugin_data[ 'plugin_uri' ] );
define( 'ROLEVISIBILITY_GUIDE_URL', ROLEVISIBILITY_AUTHOR_URI . 'guide/plugin/' . ROLEVISIBILITY_TEXTDOMAIN . '/' );
define( 'ROLEVISIBILITY_SUPPORT_URL', ROLEVISIBILITY_AUTHOR_URI . 'support/plugin/' . ROLEVISIBILITY_TEXTDOMAIN . '/' );
define( 'ROLEVISIBILITY_DISCORD_URL', $plugin_data[ 'discord_uri' ] );

// Paths
define( 'ROLEVISIBILITY_BASENAME', plugin_basename( __FILE__ ) );                                     //: text-domain/text-domain.php
define( 'ROLEVISIBILITY_ABSPATH', plugin_dir_path( __FILE__ ) );                                      //: /home/.../public_html/wp-content/plugins/text-domain/
define( 'ROLEVISIBILITY_DIR', plugins_url( '/' . ROLEVISIBILITY_TEXTDOMAIN . '/' ) );                 //: https://domain.com/wp-content/plugins/text-domain/
define( 'ROLEVISIBILITY_INCLUDES_ABSPATH', ROLEVISIBILITY_ABSPATH . 'inc/' );                         //: /home/.../public_html/wp-content/plugins/text-domain/includes/
define( 'ROLEVISIBILITY_INCLUDES_DIR', ROLEVISIBILITY_DIR . 'inc/' );                                 //: https://domain.com/wp-content/plugins/text-domain/includes/
define( 'ROLEVISIBILITY_IMG_PATH', ROLEVISIBILITY_INCLUDES_DIR . 'img/' );                            //: https://domain.com/wp-content/plugins/text-domain/includes/img/
define( 'ROLEVISIBILITY_CSS_PATH', ROLEVISIBILITY_INCLUDES_DIR . 'css/' );                            //: https://domain.com/wp-content/plugins/text-domain/includes/css/
define( 'ROLEVISIBILITY_JS_PATH', ROLEVISIBILITY_INCLUDES_DIR . 'js/' );                              //: https://domain.com/wp-content/plugins/text-domain/includes/js/
define( 'ROLEVISIBILITY_LANG_PATH', ROLEVISIBILITY_INCLUDES_DIR . 'lang/' );                          //: https://domain.com/wp-content/plugins/text-domain/includes/lang/
define( 'ROLEVISIBILITY_SETTINGS_PATH', admin_url( 'admin.php?page='.ROLEVISIBILITY_TEXTDOMAIN ) );   //: https://domain.com/wp-admin/?page=text-domain


/**
 * Includes
 */
require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'common.php';
require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'license.php';
require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'update.php';
require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'settings.php';
require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'helpers.php';
require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'content.php';

if ( is_admin() ) {
    require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'meta-box.php';
    require_once ROLEVISIBILITY_INCLUDES_ABSPATH . 'wp-list-table.php';
}
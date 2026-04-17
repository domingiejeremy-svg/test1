<?php
/**
 * Plugin Name:       Wheel Game — Roue des cadeaux
 * Plugin URI:        https://boostezvotrereputation.fr
 * Description:       Plateforme de jeux de roue pour collecter leads et avis Google. Chaque campagne a son URL, ses prix, son suivi, ses quotas.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Boostez Votre Réputation
 * License:           GPL v2 or later
 * Text Domain:       wheel-game
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WHEEL_GAME_VERSION',    '2.0.0' );
define( 'WHEEL_GAME_DB_VERSION', '2.0' );
define( 'WHEEL_GAME_FILE',       __FILE__ );
define( 'WHEEL_GAME_DIR',        plugin_dir_path( __FILE__ ) );
define( 'WHEEL_GAME_URL',        plugin_dir_url( __FILE__ ) );
define( 'WHEEL_GAME_BASENAME',   plugin_basename( __FILE__ ) );

spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Wheel_Game' ) !== 0 ) return;
    $slug = strtolower( str_replace( [ 'Wheel_Game_', '_' ], [ '', '-' ], $class ) );
    $file = WHEEL_GAME_DIR . 'includes/class-' . $slug . '.php';
    if ( file_exists( $file ) ) require_once $file;
} );

require_once WHEEL_GAME_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__,   [ 'Wheel_Game_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Wheel_Game_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'wheel-game', false, dirname( WHEEL_GAME_BASENAME ) . '/languages' );
    Wheel_Game_Plugin::get_instance();
} );

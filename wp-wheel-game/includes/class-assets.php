<?php
/**
 * Enregistre CSS/JS admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Assets {

    public function init() {
        add_action( 'admin_enqueue_scripts', [ $this, 'admin' ] );
    }

    public function admin( $hook ) {
        global $post, $typenow;
        $is_edit = in_array( $hook, [ 'post.php', 'post-new.php' ], true )
            && ( $typenow === Wheel_Game_Cpt::POST_TYPE
                || ( isset( $post ) && $post->post_type === Wheel_Game_Cpt::POST_TYPE ) );
        $is_list = $hook === 'edit.php' && $typenow === Wheel_Game_Cpt::POST_TYPE;
        $is_page = isset( $_GET['page'] ) && strpos( $_GET['page'], 'wheel-game-' ) === 0;

        if ( ! ( $is_edit || $is_list || $is_page ) ) return;

        wp_enqueue_style( 'wheel-game-admin', WHEEL_GAME_URL . 'assets/css/admin.css', [], WHEEL_GAME_VERSION );

        if ( $is_edit ) {
            wp_enqueue_media();
            wp_enqueue_script( 'wheel-game-admin', WHEEL_GAME_URL . 'assets/js/admin.js',
                [ 'jquery' ], WHEEL_GAME_VERSION, true );
            wp_localize_script( 'wheel-game-admin', 'WheelGameAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wheel_admin' ),
                'i18n'    => [
                    'minPrizes'  => __( 'Minimum 2 prix requis sur la roue.', 'wheel-game' ),
                    'copied'     => __( '✓ Copié', 'wheel-game' ),
                    'copy'       => __( 'Copier URL', 'wheel-game' ),
                    'chooseLogo' => __( 'Choisir le logo', 'wheel-game' ),
                    'useLogo'    => __( 'Utiliser ce logo', 'wheel-game' ),
                    'simulating' => __( 'Simulation en cours…', 'wheel-game' ),
                ],
            ] );
        }

        if ( $is_list ) {
            wp_enqueue_script( 'wheel-game-list', WHEEL_GAME_URL . 'assets/js/list.js',
                [], WHEEL_GAME_VERSION, true );
        }
    }
}

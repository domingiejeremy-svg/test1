<?php
/**
 * Router : décide quel template charger selon l'étape.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Router {

    public function init() {
        add_filter( 'template_include', [ $this, 'template_include' ] );
    }

    public function template_include( $template ) {
        if ( ! is_singular( Wheel_Game_Cpt::POST_TYPE ) ) return $template;

        $step = sanitize_key( $_GET['step'] ?? '' );

        switch ( $step ) {
            case 'reward': $file = WHEEL_GAME_DIR . 'templates/reward.php'; break;
            default:       $file = WHEEL_GAME_DIR . 'templates/wheel.php';
        }

        return file_exists( $file ) ? $file : $template;
    }
}

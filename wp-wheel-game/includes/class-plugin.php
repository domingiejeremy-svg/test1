<?php
/**
 * Orchestrateur principal.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class Wheel_Game_Plugin {

    private static $instance = null;

    public $cpt, $admin, $ajax, $google, $cron, $leads, $analytics, $router, $assets;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', [ 'Wheel_Game_Activator', 'maybe_upgrade_db' ] );

        $this->cpt       = new Wheel_Game_Cpt();
        $this->router    = new Wheel_Game_Router();
        $this->assets    = new Wheel_Game_Assets();
        $this->admin     = new Wheel_Game_Admin();
        $this->ajax      = new Wheel_Game_Ajax();
        $this->google    = new Wheel_Game_Google_Api();
        $this->cron      = new Wheel_Game_Cron();
        $this->leads     = new Wheel_Game_Leads();
        $this->analytics = new Wheel_Game_Analytics();

        $this->cpt->init();
        $this->router->init();
        $this->assets->init();
        $this->admin->init();
        $this->ajax->init();
        $this->cron->init();
    }
}

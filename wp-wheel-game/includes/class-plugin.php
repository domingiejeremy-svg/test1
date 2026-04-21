<?php
/**
 * Orchestrateur principal.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class Wheel_Game_Plugin {

    private static $instance = null;

    public $cpt, $admin, $ajax, $google, $cron, $leads, $analytics, $router, $assets;
    public $config_page, $product_meta, $order_hook, $sales_rep, $sales_space;

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
        $this->config_page = new Wheel_Game_Config_Page();
        $this->sales_space = new Wheel_Game_Sales_Space();

        $this->cpt->init();
        $this->router->init();
        $this->assets->init();
        $this->admin->init();
        $this->ajax->init();
        $this->cron->init();
        $this->config_page->init();
        $this->sales_space->init();

        // Modules conditionnels : WooCommerce
        if ( class_exists( 'WooCommerce' ) ) {
            $this->product_meta = new Wheel_Game_Product_Meta();
            $this->order_hook   = new Wheel_Game_Order_Hook();
            $this->sales_rep    = new Wheel_Game_Sales_Rep();
            $this->product_meta->init();
            $this->order_hook->init();
            $this->sales_rep->init();
        } else {
            add_action( 'admin_notices', function () {
                if ( ! current_user_can( 'activate_plugins' ) ) return;
                echo '<div class="notice notice-warning"><p><strong>Wheel Game :</strong> ' .
                    esc_html__( 'WooCommerce n\'est pas actif. L\'auto-création de roue à la commande est désactivée.', 'wheel-game' ) .
                    '</p></div>';
            } );
        }
    }
}

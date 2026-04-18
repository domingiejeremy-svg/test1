<?php
/**
 * Champ "Offre BVR" sur la page produit WooCommerce.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Product_Meta {

    const META_KEY = '_bvr_offer';

    public function init() {
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_field' ] );
        add_action( 'woocommerce_process_product_meta',                  [ $this, 'save_field' ] );
    }

    public function render_field() {
        $options = [
            ''                              => __( '— Aucune offre BVR —', 'wheel-game' ),
            Wheel_Game_Offer::STARTER       => '🥉 Starter',
            Wheel_Game_Offer::BOOSTER       => '🥈 Booster',
            Wheel_Game_Offer::ALL_INCLUSIVE => '🥇 All Inclusive',
        ];

        global $post;
        $current = get_post_meta( $post->ID, self::META_KEY, true );

        echo '<div class="options_group">';
        woocommerce_wp_select( [
            'id'      => self::META_KEY,
            'label'   => __( 'Offre BVR (Wheel Game)', 'wheel-game' ),
            'desc_tip'=> true,
            'description' => __( 'Si ce produit correspond à une offre Roue des cadeaux, sélectionnez laquelle. À l\'achat, une roue brouillon sera créée automatiquement pour le client.', 'wheel-game' ),
            'options' => $options,
            'value'   => $current,
        ] );
        echo '</div>';
    }

    public function save_field( $post_id ) {
        $val = isset( $_POST[ self::META_KEY ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';
        if ( $val && ! in_array( $val, Wheel_Game_Offer::all_slugs(), true ) ) {
            $val = '';
        }
        update_post_meta( $post_id, self::META_KEY, $val );
    }
}

<?php
/**
 * Helper offres (Starter / Booster / Premium).
 * Les features sont gérées par Wheel_Game_Features — cette classe ne gère que
 * l'identité de l'offre, son libellé, et le compteur de modifications.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Offer {

    const STARTER = 'starter';
    const BOOSTER = 'booster';
    const PREMIUM = 'premium';

    // Compat : ancienne constante
    const ALL_INCLUSIVE = 'premium';

    public static function labels() {
        return [
            self::STARTER => [ 'label' => __( 'Starter', 'wheel-game' ), 'emoji' => '🥉' ],
            self::BOOSTER => [ 'label' => __( 'Booster', 'wheel-game' ), 'emoji' => '🥈' ],
            self::PREMIUM => [ 'label' => __( 'Premium', 'wheel-game' ), 'emoji' => '🥇' ],
        ];
    }

    public static function all_slugs() {
        return array_keys( self::labels() );
    }

    public static function get( $slug ) {
        // Migration douce : "all_inclusive" → "premium"
        if ( $slug === 'all_inclusive' ) $slug = self::PREMIUM;
        return self::labels()[ $slug ] ?? self::labels()[ self::STARTER ];
    }

    public static function label( $slug ) {
        $o = self::get( $slug );
        return $o['emoji'] . ' ' . $o['label'];
    }

    /**
     * Offre d'une campagne (migration auto all_inclusive → premium).
     */
    public static function for_campaign( $campaign_id ) {
        $slug = get_post_meta( $campaign_id, '_wheel_offer', true ) ?: self::STARTER;
        if ( $slug === 'all_inclusive' ) {
            $slug = self::PREMIUM;
            update_post_meta( $campaign_id, '_wheel_offer', $slug );
        }
        return $slug;
    }

    public static function set_for_campaign( $campaign_id, $slug ) {
        if ( $slug === 'all_inclusive' ) $slug = self::PREMIUM;
        if ( ! in_array( $slug, self::all_slugs(), true ) ) $slug = self::STARTER;
        update_post_meta( $campaign_id, '_wheel_offer', $slug );
    }

    /**
     * Compteur de modifications consommées.
     */
    public static function mods_used( $campaign_id ) {
        return (int) get_post_meta( $campaign_id, '_wheel_mods_used', true );
    }

    public static function mods_remaining( $campaign_id ) {
        $max = Wheel_Game_Features::mods_per_year( $campaign_id );
        if ( $max === -1 ) return -1;
        $used = self::mods_used( $campaign_id );
        return max( 0, $max - $used );
    }

    public static function increment_mods( $campaign_id ) {
        $used = self::mods_used( $campaign_id );
        update_post_meta( $campaign_id, '_wheel_mods_used', $used + 1 );
        update_post_meta( $campaign_id, '_wheel_last_mod_at', current_time( 'mysql' ) );
    }

    public static function can_modify( $campaign_id ) {
        $max = Wheel_Game_Features::mods_per_year( $campaign_id );
        if ( $max === -1 ) return true;
        return self::mods_used( $campaign_id ) < $max;
    }

    /**
     * Détermine l'offre à partir d'une commande WooCommerce.
     * Si plusieurs produits avec offres → prend la meilleure.
     */
    public static function from_wc_order( $order ) {
        if ( ! $order || ! is_object( $order ) ) return self::STARTER;

        $priority = [ self::PREMIUM => 3, self::BOOSTER => 2, self::STARTER => 1 ];
        $best = self::STARTER;
        $best_rank = 1;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $offer = get_post_meta( $product_id, '_bvr_offer', true );
            // Migration : all_inclusive → premium
            if ( $offer === 'all_inclusive' ) $offer = self::PREMIUM;
            if ( ! $offer || ! isset( $priority[ $offer ] ) ) continue;
            if ( $priority[ $offer ] > $best_rank ) {
                $best = $offer;
                $best_rank = $priority[ $offer ];
            }
        }

        return $best;
    }
}

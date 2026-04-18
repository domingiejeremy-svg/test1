<?php
/**
 * Helper offres (Starter / Booster / All Inclusive).
 * Règles de quotas, features, libellés.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Offer {

    const STARTER       = 'starter';
    const BOOSTER       = 'booster';
    const ALL_INCLUSIVE = 'all_inclusive';

    /**
     * Règles par offre : quota modifs/an, quota tirages/mois, features.
     */
    public static function rules() {
        return [
            self::STARTER => [
                'label'            => __( 'Starter', 'wheel-game' ),
                'emoji'            => '🥉',
                'mods_per_year'    => 1,
                'plays_per_month'  => 500,
                'features'         => [ 'basic_stats' ],
            ],
            self::BOOSTER => [
                'label'            => __( 'Booster', 'wheel-game' ),
                'emoji'            => '🥈',
                'mods_per_year'    => 5,
                'plays_per_month'  => 2000,
                'features'         => [ 'basic_stats', 'advanced_stats', 'lead_capture', 'monthly_report' ],
            ],
            self::ALL_INCLUSIVE => [
                'label'            => __( 'All Inclusive', 'wheel-game' ),
                'emoji'            => '🥇',
                'mods_per_year'    => -1, // illimité
                'plays_per_month'  => -1, // illimité
                'features'         => [ 'basic_stats', 'advanced_stats', 'lead_capture', 'monthly_report', 'review_alerts', 'seasonal_wheels', 'mailchimp_sync', 'shared_dashboard' ],
            ],
        ];
    }

    public static function all_slugs() {
        return array_keys( self::rules() );
    }

    public static function get( $slug ) {
        return self::rules()[ $slug ] ?? self::rules()[ self::STARTER ];
    }

    public static function label( $slug ) {
        $o = self::get( $slug );
        return $o['emoji'] . ' ' . $o['label'];
    }

    /**
     * Offre d'une campagne (stockée en méta).
     */
    public static function for_campaign( $campaign_id ) {
        return get_post_meta( $campaign_id, '_wheel_offer', true ) ?: self::STARTER;
    }

    public static function set_for_campaign( $campaign_id, $slug ) {
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
        $offer = self::get( self::for_campaign( $campaign_id ) );
        if ( $offer['mods_per_year'] === -1 ) return -1; // illimité
        $used = self::mods_used( $campaign_id );
        return max( 0, $offer['mods_per_year'] - $used );
    }

    public static function increment_mods( $campaign_id ) {
        $used = self::mods_used( $campaign_id );
        update_post_meta( $campaign_id, '_wheel_mods_used', $used + 1 );
        update_post_meta( $campaign_id, '_wheel_last_mod_at', current_time( 'mysql' ) );
    }

    public static function can_modify( $campaign_id ) {
        $offer = self::get( self::for_campaign( $campaign_id ) );
        if ( $offer['mods_per_year'] === -1 ) return true;
        return self::mods_used( $campaign_id ) < $offer['mods_per_year'];
    }

    /**
     * Vérifie si une feature est disponible pour l'offre.
     */
    public static function has_feature( $campaign_id, $feature ) {
        $offer = self::get( self::for_campaign( $campaign_id ) );
        return in_array( $feature, $offer['features'], true );
    }

    /**
     * Détermine l'offre à partir d'une commande WooCommerce.
     * Si plusieurs produits avec offres différentes → prend la "meilleure".
     */
    public static function from_wc_order( $order ) {
        if ( ! $order || ! is_object( $order ) ) return self::STARTER;

        $priority = [ self::ALL_INCLUSIVE => 3, self::BOOSTER => 2, self::STARTER => 1 ];
        $best = self::STARTER;
        $best_rank = 1;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $offer = get_post_meta( $product_id, '_bvr_offer', true );
            if ( ! $offer || ! isset( $priority[ $offer ] ) ) continue;
            if ( $priority[ $offer ] > $best_rank ) {
                $best = $offer;
                $best_rank = $priority[ $offer ];
            }
        }

        return $best;
    }
}

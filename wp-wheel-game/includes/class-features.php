<?php
/**
 * Registre centralisé des features + gating par offre + overrides par campagne.
 *
 * Architecture :
 * 1. Registre maître : liste des features avec label/description
 * 2. Option 'wheel_game_offer_features' : mapping offre → [feature_slugs] (modifiable via UI admin)
 * 3. Meta campagne '_wheel_features_extra' : features AJOUTÉES en plus de l'offre
 * 4. Meta campagne '_wheel_features_removed' : features RETIRÉES de l'offre
 *
 * Accès final : (offre_default ∪ extra) - removed
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Features {

    /**
     * Registre maître : toutes les features connues du plugin.
     */
    public static function registry() {
        return [
            // Création
            'wheel_creation' => [
                'label'    => __( 'Création de roue personnalisée', 'wheel-game' ),
                'desc'     => __( 'Créer et publier une roue avec prix, probabilités, couleurs, logo.', 'wheel-game' ),
                'category' => 'creation',
                'icon'     => '🎡',
            ],
            'qr_code' => [
                'label'    => __( 'Génération QR Code', 'wheel-game' ),
                'desc'     => __( 'QR Code téléchargeable en PNG (600/1200/2000) et SVG vectoriel.', 'wheel-game' ),
                'category' => 'creation',
                'icon'     => '🔲',
            ],

            // Modifications
            'mods_1_per_year' => [
                'label'    => __( '1 modification par an', 'wheel-game' ),
                'desc'     => __( 'Le client peut modifier sa roue 1 fois par an.', 'wheel-game' ),
                'category' => 'mods',
                'icon'     => '✏️',
                'mutually_exclusive' => [ 'mods_5_per_year', 'mods_unlimited' ],
            ],
            'mods_5_per_year' => [
                'label'    => __( '5 modifications par an', 'wheel-game' ),
                'desc'     => __( 'Le client peut modifier sa roue 5 fois par an.', 'wheel-game' ),
                'category' => 'mods',
                'icon'     => '✏️',
                'mutually_exclusive' => [ 'mods_1_per_year', 'mods_unlimited' ],
            ],
            'mods_unlimited' => [
                'label'    => __( 'Modifications illimitées', 'wheel-game' ),
                'desc'     => __( 'Le client peut modifier sa roue sans limite.', 'wheel-game' ),
                'category' => 'mods',
                'icon'     => '♾️',
                'mutually_exclusive' => [ 'mods_1_per_year', 'mods_5_per_year' ],
            ],

            // Stats & tracking
            'google_reviews_tracking' => [
                'label'    => __( 'Suivi statistiques des avis Google', 'wheel-game' ),
                'desc'     => __( 'Historique note + nombre d\'avis, comparatif avant/après campagne.', 'wheel-game' ),
                'category' => 'stats',
                'icon'     => '📈',
            ],

            // Marketing
            'lead_capture' => [
                'label'    => __( 'Capture de leads', 'wheel-game' ),
                'desc'     => __( 'Formulaire avant tirage : prénom, nom, email, téléphone, consentement RGPD. Export CSV.', 'wheel-game' ),
                'category' => 'marketing',
                'icon'     => '👤',
            ],
            'monthly_report' => [
                'label'    => __( 'Rapport mensuel automatique', 'wheel-game' ),
                'desc'     => __( 'Rapport mensuel envoyé par email au client avec ses KPIs.', 'wheel-game' ),
                'category' => 'marketing',
                'icon'     => '📧',
            ],
            'conversion_optimization' => [
                'label'    => __( 'Optimisation conversion', 'wheel-game' ),
                'desc'     => __( 'Accès au simulateur Monte-Carlo, templates préfaits et A/B testing.', 'wheel-game' ),
                'category' => 'marketing',
                'icon'     => '🎯',
            ],
        ];
    }

    /**
     * Mapping par défaut offre → features.
     * Utilisé à la première installation ou en fallback si l'option n'existe pas encore.
     */
    public static function default_mapping() {
        return [
            Wheel_Game_Offer::STARTER => [
                'wheel_creation', 'qr_code', 'mods_1_per_year',
            ],
            Wheel_Game_Offer::BOOSTER => [
                'wheel_creation', 'qr_code', 'mods_5_per_year',
                'google_reviews_tracking', 'conversion_optimization',
                'lead_capture', 'monthly_report',
            ],
            Wheel_Game_Offer::PREMIUM => [
                'wheel_creation', 'qr_code', 'mods_unlimited',
                'google_reviews_tracking', 'conversion_optimization',
                'lead_capture', 'monthly_report',
            ],
        ];
    }

    /**
     * Mapping effectif offre → features (lu depuis les options, modifiable en UI).
     */
    public static function offer_mapping() {
        $stored = get_option( 'wheel_game_offer_features' );
        if ( ! is_array( $stored ) || empty( $stored ) ) {
            return self::default_mapping();
        }
        // Merge avec defaults au cas où des offres manquent
        return array_merge( self::default_mapping(), $stored );
    }

    public static function save_offer_mapping( array $mapping ) {
        $valid_features = array_keys( self::registry() );
        $clean = [];
        foreach ( Wheel_Game_Offer::all_slugs() as $offer ) {
            $list = (array) ( $mapping[ $offer ] ?? [] );
            $clean[ $offer ] = array_values( array_intersect( $list, $valid_features ) );
        }
        update_option( 'wheel_game_offer_features', $clean );
    }

    /**
     * Features de base d'une offre (sans override campagne).
     */
    public static function for_offer( $offer_slug ) {
        $mapping = self::offer_mapping();
        return (array) ( $mapping[ $offer_slug ] ?? [] );
    }

    /**
     * Features effectives pour une campagne (avec overrides).
     */
    public static function for_campaign( $campaign_id ) {
        $offer = Wheel_Game_Offer::for_campaign( $campaign_id );
        $base  = self::for_offer( $offer );

        $extra   = (array) get_post_meta( $campaign_id, '_wheel_features_extra',   true );
        $removed = (array) get_post_meta( $campaign_id, '_wheel_features_removed', true );

        $result = array_unique( array_merge( $base, $extra ) );
        $result = array_values( array_diff( $result, $removed ) );
        return $result;
    }

    /**
     * Check si une feature est active pour une campagne.
     */
    public static function has( $campaign_id, $feature_slug ) {
        return in_array( $feature_slug, self::for_campaign( $campaign_id ), true );
    }

    /**
     * Helper : retourne le quota de modifications (-1 = illimité) selon les features actives.
     */
    public static function mods_per_year( $campaign_id ) {
        if ( self::has( $campaign_id, 'mods_unlimited' ) )  return -1;
        if ( self::has( $campaign_id, 'mods_5_per_year' ) ) return 5;
        if ( self::has( $campaign_id, 'mods_1_per_year' ) ) return 1;
        return 0; // aucune modif possible
    }

    /**
     * Features groupées par catégorie pour l'UI.
     */
    public static function by_category() {
        $groups = [];
        foreach ( self::registry() as $slug => $def ) {
            $cat = $def['category'];
            if ( ! isset( $groups[ $cat ] ) ) $groups[ $cat ] = [];
            $groups[ $cat ][ $slug ] = $def;
        }
        return $groups;
    }

    public static function category_label( $cat ) {
        return [
            'creation'  => __( '🎨 Création', 'wheel-game' ),
            'mods'      => __( '✏️ Modifications', 'wheel-game' ),
            'stats'     => __( '📊 Suivi & Analyses', 'wheel-game' ),
            'marketing' => __( '🚀 Marketing & Conversion', 'wheel-game' ),
        ][ $cat ] ?? $cat;
    }

    /**
     * Enregistrer un override pour une campagne (+ ou -).
     */
    public static function add_extra( $campaign_id, $feature_slug ) {
        if ( ! isset( self::registry()[ $feature_slug ] ) ) return false;
        $extra = (array) get_post_meta( $campaign_id, '_wheel_features_extra', true );
        if ( ! in_array( $feature_slug, $extra, true ) ) {
            $extra[] = $feature_slug;
            update_post_meta( $campaign_id, '_wheel_features_extra', $extra );
        }
        return true;
    }

    public static function remove_feature( $campaign_id, $feature_slug ) {
        $removed = (array) get_post_meta( $campaign_id, '_wheel_features_removed', true );
        if ( ! in_array( $feature_slug, $removed, true ) ) {
            $removed[] = $feature_slug;
            update_post_meta( $campaign_id, '_wheel_features_removed', $removed );
        }
        return true;
    }

    public static function reset_overrides( $campaign_id ) {
        delete_post_meta( $campaign_id, '_wheel_features_extra' );
        delete_post_meta( $campaign_id, '_wheel_features_removed' );
    }
}

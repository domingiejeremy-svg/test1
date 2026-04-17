<?php
/**
 * Templates préfaits par secteur.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Templates_Library {

    public static function all() {
        return [
            'restaurant' => [
                'name' => __( 'Restaurant / Brasserie', 'wheel-game' ), 'emoji' => '🍽️',
                'title' => __( 'Tournez et régalez-vous !', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '🍰', 'line1' => __( 'Dessert',   'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#e91e63', 'percent' => 30 ],
                    [ 'emoji' => '🍷', 'line1' => __( 'Apéritif',  'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#8e44ad', 'percent' => 20 ],
                    [ 'emoji' => '☕', 'line1' => __( 'Café',      'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#6c5ce7', 'percent' => 20 ],
                    [ 'emoji' => '💰', 'line1' => '-10%',                           'line2' => __( 'sur l\'addition', 'wheel-game' ), 'color' => '#16a085', 'percent' => 15 ],
                    [ 'emoji' => '🍽️', 'line1' => __( 'Entrée',    'wheel-game' ), 'line2' => __( 'offerte',         'wheel-game' ), 'color' => '#d35400', 'percent' => 10 ],
                    [ 'emoji' => '🎁', 'line1' => __( 'Menu',      'wheel-game' ), 'line2' => __( 'surprise',        'wheel-game' ), 'color' => '#c0392b', 'percent' => 5  ],
                ],
            ],
            'coiffeur' => [
                'name' => __( 'Coiffeur / Barbier', 'wheel-game' ), 'emoji' => '💇',
                'title' => __( 'Un petit plaisir pour vos cheveux', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '💆', 'line1' => __( 'Massage',    'wheel-game' ), 'line2' => __( 'cuir chevelu',    'wheel-game' ), 'color' => '#e91e63', 'percent' => 25 ],
                    [ 'emoji' => '✨', 'line1' => __( 'Soin',       'wheel-game' ), 'line2' => __( 'profond offert',  'wheel-game' ), 'color' => '#9b59b6', 'percent' => 20 ],
                    [ 'emoji' => '💰', 'line1' => '-15%',                            'line2' => __( 'prochaine visite', 'wheel-game' ), 'color' => '#16a085', 'percent' => 20 ],
                    [ 'emoji' => '💧', 'line1' => __( 'Shampoing',  'wheel-game' ), 'line2' => __( 'offert',           'wheel-game' ), 'color' => '#2980b9', 'percent' => 20 ],
                    [ 'emoji' => '🎁', 'line1' => __( 'Produit',    'wheel-game' ), 'line2' => __( 'en cadeau',        'wheel-game' ), 'color' => '#d35400', 'percent' => 10 ],
                    [ 'emoji' => '✂️', 'line1' => __( 'Coupe',      'wheel-game' ), 'line2' => '-30%',                                  'color' => '#c0392b', 'percent' => 5  ],
                ],
            ],
            'boulangerie' => [
                'name' => __( 'Boulangerie / Pâtisserie', 'wheel-game' ), 'emoji' => '🥐',
                'title' => __( 'Une douceur vous attend', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '🥐', 'line1' => __( 'Croissant',  'wheel-game' ), 'line2' => __( 'offert',    'wheel-game' ), 'color' => '#f39c12', 'percent' => 30 ],
                    [ 'emoji' => '🍞', 'line1' => __( 'Pain',       'wheel-game' ), 'line2' => __( 'au choix',  'wheel-game' ), 'color' => '#d35400', 'percent' => 25 ],
                    [ 'emoji' => '🍰', 'line1' => __( 'Pâtisserie', 'wheel-game' ), 'line2' => __( 'offerte',   'wheel-game' ), 'color' => '#e91e63', 'percent' => 20 ],
                    [ 'emoji' => '☕', 'line1' => __( 'Café',       'wheel-game' ), 'line2' => __( 'offert',    'wheel-game' ), 'color' => '#6c5ce7', 'percent' => 15 ],
                    [ 'emoji' => '💰', 'line1' => '-10%',                             'line2' => __( 'sur 20€',   'wheel-game' ), 'color' => '#16a085', 'percent' => 8  ],
                    [ 'emoji' => '🎉', 'line1' => __( 'Surprise',   'wheel-game' ), 'line2' => __( 'du boulanger','wheel-game' ), 'color' => '#c0392b', 'percent' => 2  ],
                ],
            ],
            'garage' => [
                'name' => __( 'Garage / Auto', 'wheel-game' ), 'emoji' => '🔧',
                'title' => __( 'Votre voiture a de la chance', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '🚿', 'line1' => __( 'Lavage',   'wheel-game' ), 'line2' => __( 'offert',   'wheel-game' ), 'color' => '#3498db', 'percent' => 30 ],
                    [ 'emoji' => '💰', 'line1' => '-10%',                           'line2' => __( 'vidange',  'wheel-game' ), 'color' => '#16a085', 'percent' => 25 ],
                    [ 'emoji' => '🛞', 'line1' => __( 'Pneus',    'wheel-game' ), 'line2' => '-5%',                             'color' => '#2c3e50', 'percent' => 20 ],
                    [ 'emoji' => '🔍', 'line1' => __( 'Check-up', 'wheel-game' ), 'line2' => __( 'gratuit',  'wheel-game' ), 'color' => '#9b59b6', 'percent' => 15 ],
                    [ 'emoji' => '🧽', 'line1' => __( 'Intérieur','wheel-game' ), 'line2' => __( 'nettoyé',  'wheel-game' ), 'color' => '#d35400', 'percent' => 8  ],
                    [ 'emoji' => '🎁', 'line1' => __( 'Révision', 'wheel-game' ), 'line2' => '-15%',                            'color' => '#c0392b', 'percent' => 2  ],
                ],
            ],
            'beaute' => [
                'name' => __( 'Institut de beauté / Spa', 'wheel-game' ), 'emoji' => '💅',
                'title' => __( 'Laissez-vous chouchouter', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '💆', 'line1' => __( 'Massage',   'wheel-game' ), 'line2' => __( '15 min',        'wheel-game' ), 'color' => '#e91e63', 'percent' => 25 ],
                    [ 'emoji' => '✨', 'line1' => __( 'Soin',      'wheel-game' ), 'line2' => __( 'visage -20%',   'wheel-game' ), 'color' => '#9b59b6', 'percent' => 20 ],
                    [ 'emoji' => '💅', 'line1' => __( 'Manucure',  'wheel-game' ), 'line2' => __( 'offerte',       'wheel-game' ), 'color' => '#f39c12', 'percent' => 20 ],
                    [ 'emoji' => '💰', 'line1' => '-10%',                            'line2' => __( 'toutes presta', 'wheel-game' ), 'color' => '#16a085', 'percent' => 20 ],
                    [ 'emoji' => '🎁', 'line1' => __( 'Produit',   'wheel-game' ), 'line2' => __( 'en cadeau',     'wheel-game' ), 'color' => '#d35400', 'percent' => 10 ],
                    [ 'emoji' => '👑', 'line1' => __( 'Journée',   'wheel-game' ), 'line2' => __( 'spa',           'wheel-game' ), 'color' => '#c0392b', 'percent' => 5  ],
                ],
            ],
            'cafe' => [
                'name' => __( 'Café / Bar', 'wheel-game' ), 'emoji' => '☕',
                'title' => __( 'Un p\'tit verre ça tente ?', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '☕', 'line1' => __( 'Café',         'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#6c5ce7', 'percent' => 35 ],
                    [ 'emoji' => '🍺', 'line1' => __( 'Bière',        'wheel-game' ), 'line2' => __( 'pression',        'wheel-game' ), 'color' => '#f39c12', 'percent' => 25 ],
                    [ 'emoji' => '🥤', 'line1' => __( 'Soft',         'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#2980b9', 'percent' => 20 ],
                    [ 'emoji' => '🥐', 'line1' => __( 'Viennoiserie', 'wheel-game' ), 'line2' => __( 'offerte',         'wheel-game' ), 'color' => '#d35400', 'percent' => 12 ],
                    [ 'emoji' => '💰', 'line1' => '-10%',                              'line2' => __( 'sur l\'addition', 'wheel-game' ), 'color' => '#16a085', 'percent' => 6  ],
                    [ 'emoji' => '🍷', 'line1' => __( 'Bouteille',    'wheel-game' ), 'line2' => __( 'maison',          'wheel-game' ), 'color' => '#c0392b', 'percent' => 2  ],
                ],
            ],
            'fleuriste' => [
                'name' => __( 'Fleuriste', 'wheel-game' ), 'emoji' => '🌸',
                'title' => __( 'Faites-vous plaisir !', 'wheel-game' ),
                'prizes' => [
                    [ 'emoji' => '🌸', 'line1' => __( 'Fleur',      'wheel-game' ), 'line2' => __( 'offerte',       'wheel-game' ), 'color' => '#e91e63', 'percent' => 30 ],
                    [ 'emoji' => '💐', 'line1' => __( 'Bouquet',    'wheel-game' ), 'line2' => '-15%',                                'color' => '#9b59b6', 'percent' => 25 ],
                    [ 'emoji' => '🌿', 'line1' => __( 'Plante',     'wheel-game' ), 'line2' => '-10%',                                'color' => '#27ae60', 'percent' => 20 ],
                    [ 'emoji' => '🎁', 'line1' => __( 'Emballage',  'wheel-game' ), 'line2' => __( 'cadeau offert', 'wheel-game' ), 'color' => '#f39c12', 'percent' => 15 ],
                    [ 'emoji' => '💰', 'line1' => '-20%',                            'line2' => __( 'dès 30€',       'wheel-game' ), 'color' => '#16a085', 'percent' => 8  ],
                    [ 'emoji' => '🌷', 'line1' => __( 'Composition','wheel-game' ), 'line2' => __( 'au choix',      'wheel-game' ), 'color' => '#c0392b', 'percent' => 2  ],
                ],
            ],
            'polyvalent' => [
                'name' => __( 'Polyvalent (8 prix équilibrés)', 'wheel-game' ), 'emoji' => '🎁',
                'title' => __( 'Tentez votre chance !', 'wheel-game' ),
                'prizes' => Wheel_Game_Campaign::default_prizes(),
            ],
        ];
    }

    public static function get( $slug ) {
        return self::all()[ $slug ] ?? null;
    }

    public static function apply( $slug, $campaign_id ) {
        $tpl = self::get( $slug );
        if ( ! $tpl ) return new WP_Error( 'not_found', __( 'Template introuvable', 'wheel-game' ) );
        update_post_meta( $campaign_id, '_wheel_prizes', $tpl['prizes'] );
        update_post_meta( $campaign_id, '_wheel_title',  $tpl['title'] );
        return true;
    }
}

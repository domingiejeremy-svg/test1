<?php
/**
 * Helper : lire toute la config d'une campagne en un seul appel.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Campaign {

    public static function get( $post_id ) {
        $meta = get_post_meta( $post_id );
        $g = fn( $key, $default = '' ) => isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : $default;

        $prizes = isset( $meta['_wheel_prizes'][0] ) ? maybe_unserialize( $meta['_wheel_prizes'][0] ) : [];
        if ( ! is_array( $prizes ) || empty( $prizes ) ) $prizes = self::default_prizes();

        $end_date    = $g( '_wheel_end_date', '' );
        $quota_max   = (int) $g( '_wheel_quota_max', 0 );
        $active_flag = $g( '_wheel_active', '1' ) !== '0';

        $config = [
            'id'                => (int) $post_id,
            'active'            => $active_flag,
            'prizes'            => $prizes,
            'end_date'          => $end_date,
            'quota_max'         => $quota_max,

            'title'             => $g( '_wheel_title',    __( 'Tentez votre chance !', 'wheel-game' ) ),
            'subtitle'          => $g( '_wheel_subtitle', __( 'Tournez la roue et gagnez un cadeau exclusif', 'wheel-game' ) ),
            'footer'            => $g( '_wheel_footer',   __( '1 participation par client · Offre non cumulable', 'wheel-game' ) ),

            'lead_required'     => $g( '_wheel_lead_required', '1' ) !== '0',
            'lead_fields'       => self::parse_lead_fields( $g( '_wheel_lead_fields', 'email' ) ),
            'lead_title'        => $g( '_wheel_lead_title',   __( 'Avant de jouer…', 'wheel-game' ) ),
            'lead_subtitle'     => $g( '_wheel_lead_subtitle', __( 'Laissez-nous vos coordonnées pour tenter votre chance', 'wheel-game' ) ),
            'lead_consent_text' => $g( '_wheel_lead_consent', __( 'J\'accepte de recevoir des communications commerciales', 'wheel-game' ) ),
            'lead_button'       => $g( '_wheel_lead_button',  __( 'Accéder à la roue', 'wheel-game' ) ),

            'bg_color_1'        => $g( '_wheel_bg_color_1', '#0d1b2a' ),
            'bg_color_2'        => $g( '_wheel_bg_color_2', '#1a1a2e' ),
            'accent_color'      => $g( '_wheel_accent_color', '#ffd700' ),
            'font_family'       => $g( '_wheel_font_family', 'Segoe UI' ),
            'sound_enabled'     => $g( '_wheel_sound', '0' ) === '1',

            'google_url'        => $g( '_reward_google_url', '' ),
            'google_place_id'   => $g( '_google_place_id', '' ),

            'logo'              => $g( '_reward_logo', '' ),
            'validity'          => $g( '_reward_validity',        __( 'Présentez cette page à notre équipe pour récupérer votre cadeau', 'wheel-game' ) ),
            'review_title'      => $g( '_reward_review_title',    __( 'Un petit avis Google en échange ? ⭐', 'wheel-game' ) ),
            'review_subtitle'   => $g( '_reward_review_subtitle', __( "Votre avis nous aide énormément.\nÇa ne prend que 30 secondes !", 'wheel-game' ) ),
            'step1'             => $g( '_reward_step1', __( 'Cliquez sur le bouton ci-dessous', 'wheel-game' ) ),
            'step2'             => $g( '_reward_step2', __( 'Donnez-nous 5 étoiles et laissez un petit commentaire', 'wheel-game' ) ),
            'step3'             => $g( '_reward_step3', __( 'Revenez montrer cette page pour récupérer votre cadeau', 'wheel-game' ) ),
            'btn_main'          => $g( '_reward_btn_main', __( 'Laisser un avis Google', 'wheel-game' ) ),
            'btn_sub'           => $g( '_reward_btn_sub',  __( 'Ouvre la page Google de notre établissement', 'wheel-game' ) ),
            'urgency'           => $g( '_reward_urgency',  __( 'Votre avis est totalement libre et facultatif — votre cadeau vous est acquis quoi qu\'il arrive ✅', 'wheel-game' ) ),
            'reward_footer'     => $g( '_reward_footer',   __( "En laissant un avis, vous acceptez les conditions d'utilisation de Google.\nCadeau non échangeable · Une utilisation par personne.", 'wheel-game' ) ),

            'notify_email'      => $g( '_wheel_notify_email', '' ),
            'notify_threshold'  => (float) $g( '_wheel_notify_threshold', 0 ),
        ];

        $config['expired'] = ! empty( $end_date ) && strtotime( $end_date . ' 23:59:59' ) < current_time( 'timestamp' );
        $plays_count = self::get_plays_count( $post_id );
        $config['plays_count']   = $plays_count;
        $config['quota_reached'] = $quota_max > 0 && $plays_count >= $quota_max;
        $config['available']     = $config['active'] && ! $config['expired'] && ! $config['quota_reached'];

        return $config;
    }

    public static function get_plays_count( $post_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wheel_plays WHERE campaign_id = %d", $post_id ) );
    }

    public static function parse_lead_fields( $raw ) {
        $valid  = [ 'first_name', 'last_name', 'email', 'phone' ];
        $fields = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
        $fields = array_values( array_intersect( $fields, $valid ) );
        return $fields ?: [ 'email' ];
    }

    public static function default_prizes() {
        return [
            [ 'emoji' => '☕', 'line1' => __( 'Café',      'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#e74c3c', 'percent' => 15 ],
            [ 'emoji' => '💜', 'line1' => '10%',                            'line2' => __( 'de réduction',   'wheel-game' ), 'color' => '#8e44ad', 'percent' => 20 ],
            [ 'emoji' => '🍰', 'line1' => __( 'Dessert',   'wheel-game' ), 'line2' => __( 'offert',          'wheel-game' ), 'color' => '#2980b9', 'percent' => 10 ],
            [ 'emoji' => '💰', 'line1' => '-5€',                            'line2' => __( 'prochaine visite', 'wheel-game' ), 'color' => '#16a085', 'percent' => 5  ],
            [ 'emoji' => '🚚', 'line1' => __( 'Livraison', 'wheel-game' ), 'line2' => __( 'gratuite',        'wheel-game' ), 'color' => '#d35400', 'percent' => 15 ],
            [ 'emoji' => '🔥', 'line1' => '15%',                            'line2' => __( 'de réduction',   'wheel-game' ), 'color' => '#c0392b', 'percent' => 20 ],
            [ 'emoji' => '🥗', 'line1' => __( 'Entrée',    'wheel-game' ), 'line2' => __( 'offerte',         'wheel-game' ), 'color' => '#27ae60', 'percent' => 10 ],
            [ 'emoji' => '🥤', 'line1' => __( 'Boisson',   'wheel-game' ), 'line2' => __( 'offerte',         'wheel-game' ), 'color' => '#2471a3', 'percent' => 5  ],
        ];
    }
}

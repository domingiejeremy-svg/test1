<?php
/**
 * Custom Post Type + colonnes admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Cpt {

    const POST_TYPE = 'wheel_campaign';

    public function init() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',         [ $this, 'admin_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column',   [ $this, 'admin_column_content' ], 10, 2 );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );
    }

    public function register_cpt() {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Campagnes Roue', 'wheel-game' ),
                'singular_name' => __( 'Campagne', 'wheel-game' ),
                'add_new'       => __( 'Nouvelle campagne', 'wheel-game' ),
                'add_new_item'  => __( 'Nouvelle campagne', 'wheel-game' ),
                'edit_item'     => __( 'Modifier la campagne', 'wheel-game' ),
                'all_items'     => __( 'Toutes les campagnes', 'wheel-game' ),
                'menu_name'     => __( 'Roue des cadeaux', 'wheel-game' ),
                'search_items'  => __( 'Rechercher une campagne', 'wheel-game' ),
            ],
            'public'             => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-games',
            'menu_position'      => 30,
            'supports'           => [ 'title' ],
            'rewrite'            => [ 'slug' => 'roue', 'with_front' => false ],
            'has_archive'        => false,
            'show_in_rest'       => false,
            'publicly_queryable' => true,
        ] );
    }

    public function admin_columns( $cols ) {
        return [
            'cb'     => $cols['cb'],
            'title'  => __( 'Campagne', 'wheel-game' ),
            'offer'  => __( 'Offre', 'wheel-game' ),
            'url'    => __( 'URL publique', 'wheel-game' ),
            'plays'  => __( 'Tirages', 'wheel-game' ),
            'leads'  => __( 'Leads', 'wheel-game' ),
            'clicks' => __( 'Clics Google', 'wheel-game' ),
            'status' => __( 'Statut', 'wheel-game' ),
            'date'   => __( 'Créée le', 'wheel-game' ),
        ];
    }

    public function sortable_columns( $cols ) {
        $cols['offer']  = 'offer';
        $cols['plays']  = 'plays';
        $cols['leads']  = 'leads';
        $cols['clicks'] = 'clicks';
        return $cols;
    }

    public function admin_column_content( $col, $post_id ) {
        global $wpdb;

        if ( $col === 'offer' ) {
            $slug = Wheel_Game_Offer::for_campaign( $post_id );
            $offer = Wheel_Game_Offer::get( $slug );
            $colors = [
                'starter' => '#c2410c',
                'booster' => '#6b21a8',
                'premium' => '#b45309',
            ];
            $bgs = [
                'starter' => '#fff0e6',
                'booster' => '#f3e8ff',
                'premium' => '#fef3c7',
            ];
            printf(
                '<span style="background:%s;color:%s;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;letter-spacing:.3px;white-space:nowrap">%s</span>',
                esc_attr( $bgs[ $slug ] ?? '#f3f4f6' ),
                esc_attr( $colors[ $slug ] ?? '#374151' ),
                esc_html( $offer['emoji'] . ' ' . $offer['label'] )
            );
            return;
        }

        if ( $col === 'url' ) {
            $url = get_permalink( $post_id );
            echo '<a href="' . esc_url( $url ) . '" target="_blank" style="font-size:12px">' . esc_html( $url ) . '</a><br>';
            printf( '<button class="button button-small wg-copy-btn" data-url="%s" style="margin-top:4px">%s</button>',
                esc_attr( $url ), esc_html__( 'Copier URL', 'wheel-game' ) );
            return;
        }

        if ( $col === 'plays' ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wheel_plays WHERE campaign_id = %d", $post_id ) );
            echo '<strong style="font-size:16px">' . $count . '</strong>';
            return;
        }

        if ( $col === 'leads' ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wheel_leads WHERE campaign_id = %d", $post_id ) );
            echo '<strong style="font-size:16px;color:#00b894">' . $count . '</strong>';
            return;
        }

        if ( $col === 'clicks' ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wheel_plays WHERE campaign_id = %d AND clicked_google = 1", $post_id ) );
            echo '<strong style="font-size:16px;color:#6c5ce7">' . $count . '</strong>';
            return;
        }

        if ( $col === 'status' ) {
            $config = Wheel_Game_Campaign::get( $post_id );
            if ( ! $config['active'] )           echo '<span style="color:#e74c3c;font-weight:700">● ' . esc_html__( 'Désactivé', 'wheel-game' ) . '</span>';
            elseif ( $config['expired'] )        echo '<span style="color:#e67e22;font-weight:700">● ' . esc_html__( 'Expiré', 'wheel-game' ) . '</span>';
            elseif ( $config['quota_reached'] )  echo '<span style="color:#e67e22;font-weight:700">● ' . esc_html__( 'Quota atteint', 'wheel-game' ) . '</span>';
            else                                 echo '<span style="color:#00b894;font-weight:700">● ' . esc_html__( 'Actif', 'wheel-game' ) . '</span>';
        }
    }
}

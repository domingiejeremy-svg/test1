<?php
/**
 * UI admin : meta boxes, pages dashboard, sauvegarde.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Admin {

    public function init() {
        add_action( 'add_meta_boxes',                         [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_' . Wheel_Game_Cpt::POST_TYPE, [ $this, 'save_meta' ] );
        add_action( 'admin_menu',                             [ $this, 'register_submenus' ] );
        add_filter( 'plugin_action_links_' . WHEEL_GAME_BASENAME, [ $this, 'action_links' ] );
    }

    public function action_links( $links ) {
        $dashboard = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Wheel_Game_Cpt::POST_TYPE . '&page=wheel-game-dashboard' ) ) . '">'
            . esc_html__( 'Dashboard', 'wheel-game' ) . '</a>';
        array_unshift( $links, $dashboard );
        return $links;
    }

    public function register_submenus() {
        $parent = 'edit.php?post_type=' . Wheel_Game_Cpt::POST_TYPE;
        add_submenu_page( $parent, __( 'Dashboard ROI', 'wheel-game' ),    '📊 ' . __( 'Dashboard ROI', 'wheel-game' ),
            'manage_options', 'wheel-game-dashboard', [ $this, 'render_dashboard' ] );
        add_submenu_page( $parent, __( 'Leads', 'wheel-game' ),            '👤 ' . __( 'Leads', 'wheel-game' ),
            'manage_options', 'wheel-game-leads',     [ $this, 'render_leads_page' ] );
        add_submenu_page( $parent, __( 'Templates', 'wheel-game' ),        '📋 ' . __( 'Templates', 'wheel-game' ),
            'manage_options', 'wheel-game-templates', [ $this, 'render_templates_page' ] );
        add_submenu_page( $parent, __( 'Offres & Features', 'wheel-game' ), '⚙️ ' . __( 'Offres & Features', 'wheel-game' ),
            'manage_options', 'wheel-game-features',  [ $this, 'render_features_page' ] );
        add_submenu_page( $parent, __( 'Réglages', 'wheel-game' ),         '🔧 ' . __( 'Réglages', 'wheel-game' ),
            'manage_options', 'wheel-game-settings',  [ $this, 'render_settings_page' ] );
    }

    public function render_features_page() {
        include WHEEL_GAME_DIR . 'includes/views/page-features-matrix.php';
    }

    public function register_meta_boxes() {
        add_meta_box( 'wheel_tabs',    '🎡 ' . __( 'Configuration de la campagne', 'wheel-game' ),
            [ $this, 'render_tabs_box' ],     Wheel_Game_Cpt::POST_TYPE, 'normal', 'high' );
        add_meta_box( 'wheel_sidebar', '📊 ' . __( 'Stats & QR Code', 'wheel-game' ),
            [ $this, 'render_sidebar_box' ],  Wheel_Game_Cpt::POST_TYPE, 'side', 'default' );
        add_meta_box( 'wheel_google',  '📈 ' . __( 'Suivi avis Google', 'wheel-game' ),
            [ $this, 'render_google_box' ],   Wheel_Game_Cpt::POST_TYPE, 'side', 'default' );

        if ( current_user_can( 'manage_options' ) ) {
            add_meta_box( 'wheel_features_override', '🎛️ ' . __( 'Features & exceptions', 'wheel-game' ),
                [ $this, 'render_features_override_box' ], Wheel_Game_Cpt::POST_TYPE, 'side', 'default' );
        }
    }

    public function render_features_override_box( $post ) {
        $c = Wheel_Game_Campaign::get( $post->ID );
        include WHEEL_GAME_DIR . 'includes/views/meta-box-features-override.php';
    }

    public function render_tabs_box( $post ) {
        wp_nonce_field( 'wheel_save_meta', 'wheel_nonce' );
        $c = Wheel_Game_Campaign::get( $post->ID );
        include WHEEL_GAME_DIR . 'includes/views/meta-box-tabs.php';
    }

    public function render_sidebar_box( $post ) {
        $c = Wheel_Game_Campaign::get( $post->ID );
        include WHEEL_GAME_DIR . 'includes/views/meta-box-sidebar.php';
    }

    public function render_google_box( $post ) {
        $c = Wheel_Game_Campaign::get( $post->ID );
        include WHEEL_GAME_DIR . 'includes/views/meta-box-google.php';
    }

    public function render_dashboard()      { include WHEEL_GAME_DIR . 'includes/views/page-dashboard.php'; }
    public function render_leads_page()     { include WHEEL_GAME_DIR . 'includes/views/page-leads.php'; }
    public function render_templates_page() { include WHEEL_GAME_DIR . 'includes/views/page-templates.php'; }
    public function render_settings_page()  { include WHEEL_GAME_DIR . 'includes/views/page-settings.php'; }

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['wheel_nonce'] )
            || ! wp_verify_nonce( $_POST['wheel_nonce'], 'wheel_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $text_fields = [
            '_wheel_active'           => [ 'wheel_active',           'bool' ],
            '_wheel_title'            => [ 'wheel_title',            'text' ],
            '_wheel_subtitle'         => [ 'wheel_subtitle',         'text' ],
            '_wheel_footer'           => [ 'wheel_footer',           'text' ],
            '_wheel_end_date'         => [ 'wheel_end_date',         'date' ],
            '_wheel_quota_max'        => [ 'wheel_quota_max',        'int'  ],
            '_wheel_lead_required'    => [ 'wheel_lead_required',    'bool' ],
            '_wheel_lead_fields'      => [ 'wheel_lead_fields',      'csv'  ],
            '_wheel_lead_title'       => [ 'wheel_lead_title',       'text' ],
            '_wheel_lead_subtitle'    => [ 'wheel_lead_subtitle',    'textarea' ],
            '_wheel_lead_consent'     => [ 'wheel_lead_consent',     'textarea' ],
            '_wheel_lead_button'      => [ 'wheel_lead_button',      'text' ],
            '_wheel_bg_color_1'       => [ 'wheel_bg_color_1',       'color' ],
            '_wheel_bg_color_2'       => [ 'wheel_bg_color_2',       'color' ],
            '_wheel_accent_color'     => [ 'wheel_accent_color',     'color' ],
            '_wheel_font_family'      => [ 'wheel_font_family',      'text' ],
            '_wheel_sound'            => [ 'wheel_sound',            'bool' ],
            '_wheel_notify_email'     => [ 'wheel_notify_email',     'email' ],
            '_wheel_notify_threshold' => [ 'wheel_notify_threshold', 'float' ],
            '_reward_google_url'      => [ 'reward_google_url',      'url' ],
            '_google_place_id'        => [ 'google_place_id',        'text' ],
            '_reward_logo'            => [ 'reward_logo',            'url' ],
            '_reward_validity'        => [ 'reward_validity',        'text' ],
            '_reward_review_title'    => [ 'reward_review_title',    'text' ],
            '_reward_review_subtitle' => [ 'reward_review_subtitle', 'textarea' ],
            '_reward_step1'           => [ 'reward_step1',           'text' ],
            '_reward_step2'           => [ 'reward_step2',           'text' ],
            '_reward_step3'           => [ 'reward_step3',           'text' ],
            '_reward_btn_main'        => [ 'reward_btn_main',        'text' ],
            '_reward_btn_sub'         => [ 'reward_btn_sub',         'text' ],
            '_reward_urgency'         => [ 'reward_urgency',         'text' ],
            '_reward_footer'          => [ 'reward_footer',          'textarea' ],
        ];

        foreach ( $text_fields as $meta_key => $spec ) {
            [ $post_key, $type ] = $spec;
            if ( ! isset( $_POST[ $post_key ] ) ) continue;
            $raw = wp_unslash( $_POST[ $post_key ] );
            $val = self::sanitize_value( $raw, $type );
            update_post_meta( $post_id, $meta_key, $val );
        }

        // Prizes JSON
        if ( isset( $_POST['wheel_prizes'] ) ) {
            $raw = json_decode( wp_unslash( $_POST['wheel_prizes'] ), true );
            if ( is_array( $raw ) ) {
                $clean = array_values( array_filter( array_map( function( $p ) {
                    $color = sanitize_hex_color( $p['color'] ?? '#6c5ce7' );
                    return [
                        'emoji'   => sanitize_text_field( $p['emoji'] ?? '🎁' ),
                        'line1'   => sanitize_text_field( $p['line1'] ?? '' ),
                        'line2'   => sanitize_text_field( $p['line2'] ?? '' ),
                        'color'   => $color ?: '#6c5ce7',
                        'percent' => max( 0, round( floatval( $p['percent'] ?? 10 ), 2 ) ),
                    ];
                }, $raw ), fn( $p ) => ! empty( $p['line1'] ) ) );
                if ( count( $clean ) >= 2 ) {
                    update_post_meta( $post_id, '_wheel_prizes', $clean );
                }
            }
        }

        // Clé API globale
        if ( isset( $_POST['google_api_key'] ) ) {
            $key = sanitize_text_field( wp_unslash( $_POST['google_api_key'] ) );
            if ( $key !== '' ) update_option( 'wheel_game_google_api_key', $key );
        }

        // Overrides features (admin only)
        if ( current_user_can( 'manage_options' )
            && isset( $_POST['wheel_features_override_nonce'] )
            && wp_verify_nonce( $_POST['wheel_features_override_nonce'], 'wheel_features_override' )
            && isset( $_POST['feat_override'] ) ) {
            $extra   = [];
            $removed = [];
            foreach ( (array) $_POST['feat_override'] as $slug => $action ) {
                $slug   = sanitize_key( $slug );
                $action = sanitize_key( $action );
                if ( ! isset( Wheel_Game_Features::registry()[ $slug ] ) ) continue;
                if ( $action === 'add' )    $extra[]   = $slug;
                if ( $action === 'remove' ) $removed[] = $slug;
            }
            update_post_meta( $post_id, '_wheel_features_extra',   $extra );
            update_post_meta( $post_id, '_wheel_features_removed', $removed );
        }

        do_action( 'wheel_game_after_save_campaign', $post_id );
    }

    private static function sanitize_value( $raw, $type ) {
        switch ( $type ) {
            case 'bool':     return $raw === '1' ? '1' : '0';
            case 'int':      return (string) max( 0, (int) $raw );
            case 'float':    return (string) max( 0, (float) $raw );
            case 'date':     return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
            case 'color':    return sanitize_hex_color( $raw ) ?: '';
            case 'email':    return is_email( $raw ) ? sanitize_email( $raw ) : '';
            case 'url':      return esc_url_raw( $raw );
            case 'csv':      return implode( ',', Wheel_Game_Campaign::parse_lead_fields( $raw ) );
            case 'textarea': return sanitize_textarea_field( $raw );
            default:         return sanitize_text_field( $raw );
        }
    }
}

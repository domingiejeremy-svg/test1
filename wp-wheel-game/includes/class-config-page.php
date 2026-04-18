<?php
/**
 * Page publique /configurer-ma-roue/
 * - Rewrite rule dédiée
 * - Auth via token unique OU user connecté propriétaire
 * - Template standalone (pas wp-admin)
 * - Endpoint AJAX de sauvegarde + publication
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Config_Page {

    const SLUG = 'configurer-ma-roue';

    public function init() {
        add_action( 'init',              [ $this, 'add_rewrite' ] );
        add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render' ] );

        // AJAX sauvegarde + publication
        add_action( 'wp_ajax_nopriv_wheel_config_save',    [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_wheel_config_save',           [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_nopriv_wheel_config_publish', [ $this, 'ajax_publish' ] );
        add_action( 'wp_ajax_wheel_config_publish',        [ $this, 'ajax_publish' ] );
        add_action( 'wp_ajax_nopriv_wheel_config_upload_logo', [ $this, 'ajax_upload_logo' ] );
        add_action( 'wp_ajax_wheel_config_upload_logo',        [ $this, 'ajax_upload_logo' ] );
    }

    public function add_rewrite() {
        add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?wheel_config=1', 'top' );
    }

    public function add_query_var( $vars ) {
        $vars[] = 'wheel_config';
        return $vars;
    }

    public function maybe_render() {
        if ( ! (int) get_query_var( 'wheel_config' ) ) return;

        $campaign_id = absint( $_GET['c'] ?? 0 );
        $token       = sanitize_text_field( wp_unslash( $_GET['t'] ?? '' ) );

        $campaign = $campaign_id ? get_post( $campaign_id ) : null;
        if ( ! $campaign || $campaign->post_type !== Wheel_Game_Cpt::POST_TYPE ) {
            wp_die( esc_html__( 'Campagne introuvable.', 'wheel-game' ), 404 );
        }

        // Authentification : token OU owner connecté OU admin
        if ( ! self::can_access( $campaign_id, $token ) ) {
            wp_die( esc_html__( 'Accès refusé. Vérifiez votre lien de configuration dans l\'email.', 'wheel-game' ), 403 );
        }

        // Variables pour le template
        $c = Wheel_Game_Campaign::get( $campaign_id );
        $offer_slug  = Wheel_Game_Offer::for_campaign( $campaign_id );
        $offer       = Wheel_Game_Offer::get( $offer_slug );
        $is_draft    = $campaign->post_status === 'draft';
        $is_first    = $is_draft; // première config si encore en brouillon
        $mods_remaining = Wheel_Game_Offer::mods_remaining( $campaign_id );
        $campaign_url   = get_permalink( $campaign_id );

        // Si campagne publiée et plus de modifs dispo
        $is_locked = ! $is_first && ! Wheel_Game_Offer::can_modify( $campaign_id );

        include WHEEL_GAME_DIR . 'templates/config.php';
        exit;
    }

    /**
     * Vérifie l'accès à la page de config.
     */
    public static function can_access( $campaign_id, $token = '' ) {
        // Admin WP : toujours
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return true;

        // User connecté propriétaire : OK
        $owner_id = (int) get_post_meta( $campaign_id, '_wheel_owner_id', true );
        if ( is_user_logged_in() && get_current_user_id() === $owner_id ) return true;

        // Token correspond : OK (lien email)
        $expected = get_post_meta( $campaign_id, '_wheel_config_token', true );
        if ( $token && $expected && hash_equals( (string) $expected, (string) $token ) ) return true;

        return false;
    }

    /**
     * Sauvegarde intermédiaire (auto-save avant publication).
     */
    public function ajax_save() {
        check_ajax_referer( 'wheel_config', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $token       = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        if ( ! self::can_access( $campaign_id, $token ) ) {
            wp_send_json_error( __( 'Accès refusé', 'wheel-game' ) );
        }

        // Blocage si quota atteint (sauf si brouillon initial ou admin)
        $is_draft = get_post_status( $campaign_id ) === 'draft';
        if ( ! $is_draft && ! ( is_user_logged_in() && current_user_can( 'manage_options' ) )
            && ! Wheel_Game_Offer::can_modify( $campaign_id ) ) {
            wp_send_json_error( __( 'Vous avez atteint votre quota de modifications. Contactez-nous pour augmenter votre limite.', 'wheel-game' ) );
        }

        $this->apply_changes( $campaign_id );
        wp_send_json_success( [ 'saved_at' => current_time( 'mysql' ) ] );
    }

    /**
     * Validation finale : sauvegarde + passage en "publish" + invalidation du token.
     */
    public function ajax_publish() {
        check_ajax_referer( 'wheel_config', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $token       = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        if ( ! self::can_access( $campaign_id, $token ) ) {
            wp_send_json_error( __( 'Accès refusé', 'wheel-game' ) );
        }

        $this->apply_changes( $campaign_id );

        $was_draft = get_post_status( $campaign_id ) === 'draft';

        // Passage en publish + activation
        wp_update_post( [ 'ID' => $campaign_id, 'post_status' => 'publish' ] );
        update_post_meta( $campaign_id, '_wheel_active', '1' );

        // Si ce n'est PAS la première publication → on décompte une modif
        if ( ! $was_draft ) {
            Wheel_Game_Offer::increment_mods( $campaign_id );
        } else {
            // Première config validée → on garde le token actif pour la prochaine fois
            // (il permet au client d'accéder à son interface sans login)
        }

        $campaign_url = get_permalink( $campaign_id );

        wp_send_json_success( [
            'url'          => $campaign_url,
            'was_first'    => $was_draft,
            'mods_remaining' => Wheel_Game_Offer::mods_remaining( $campaign_id ),
        ] );
    }

    /**
     * Upload de logo (front public).
     * Check token + user or admin. Utilise la médiathèque WP.
     */
    public function ajax_upload_logo() {
        check_ajax_referer( 'wheel_config', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $token       = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        if ( ! self::can_access( $campaign_id, $token ) ) {
            wp_send_json_error( __( 'Accès refusé', 'wheel-game' ) );
        }

        if ( empty( $_FILES['logo'] ) ) wp_send_json_error( __( 'Aucun fichier reçu', 'wheel-game' ) );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Validation : image, taille max 2 MB
        $file = $_FILES['logo'];
        $allowed = [ 'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml' ];
        if ( ! in_array( $file['type'], $allowed, true ) ) {
            wp_send_json_error( __( 'Format non supporté. Utilisez JPG, PNG, WebP ou SVG.', 'wheel-game' ) );
        }
        if ( $file['size'] > 2 * 1024 * 1024 ) {
            wp_send_json_error( __( 'Fichier trop lourd (2 Mo max).', 'wheel-game' ) );
        }

        $attachment_id = media_handle_upload( 'logo', $campaign_id );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( $attachment_id->get_error_message() );
        }

        $url = wp_get_attachment_url( $attachment_id );
        update_post_meta( $campaign_id, '_reward_logo', $url );

        wp_send_json_success( [ 'url' => $url ] );
    }

    /**
     * Applique les modifications envoyées depuis la page de config.
     */
    private function apply_changes( $campaign_id ) {
        // Titre
        if ( isset( $_POST['campaign_title'] ) ) {
            $title = sanitize_text_field( wp_unslash( $_POST['campaign_title'] ) );
            if ( $title ) wp_update_post( [ 'ID' => $campaign_id, 'post_title' => $title ] );
        }

        // Prix
        if ( isset( $_POST['prizes'] ) ) {
            $raw = json_decode( wp_unslash( $_POST['prizes'] ), true );
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
                    update_post_meta( $campaign_id, '_wheel_prizes', $clean );
                }
            }
        }

        // Couleurs
        foreach ( [ 'bg_color_1' => '_wheel_bg_color_1',
                    'bg_color_2' => '_wheel_bg_color_2',
                    'accent_color' => '_wheel_accent_color' ] as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                $v = sanitize_hex_color( wp_unslash( $_POST[ $post_key ] ) );
                if ( $v ) update_post_meta( $campaign_id, $meta_key, $v );
            }
        }

        // Logo (URL déjà uploadé via ajax_upload_logo)
        if ( isset( $_POST['logo_url'] ) ) {
            update_post_meta( $campaign_id, '_reward_logo', esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) );
        }
    }
}

<?php
/**
 * Hook sur la validation de commande WooCommerce :
 * - Crée une campagne brouillon avec template générique
 * - Applique l'offre selon le produit acheté
 * - Génère un token de configuration unique
 * - Envoie un email au client avec lien de config
 * - Redirige la page "merci" vers la page de configuration
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Order_Hook {

    public function init() {
        // Dès que le paiement est reçu
        add_action( 'woocommerce_payment_complete',               [ $this, 'on_payment_complete' ], 10, 1 );
        // Sécurité : aussi au passage en "processing" (pour modes de paiement manuels type virement)
        add_action( 'woocommerce_order_status_processing',         [ $this, 'on_payment_complete' ], 10, 1 );
        add_action( 'woocommerce_order_status_completed',          [ $this, 'on_payment_complete' ], 10, 1 );

        // Redirection thank-you → page de config
        add_action( 'template_redirect', [ $this, 'redirect_thankyou' ] );
    }

    /**
     * Crée la campagne brouillon si pas déjà fait pour cette commande.
     */
    public function on_payment_complete( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Évite les doublons : on check une méta sur la commande
        if ( $order->get_meta( '_bvr_wheel_created' ) ) return;

        // Check qu'il y a au moins un produit avec une offre BVR
        $has_bvr_product = false;
        foreach ( $order->get_items() as $item ) {
            $offer = get_post_meta( $item->get_product_id(), Wheel_Game_Product_Meta::META_KEY, true );
            if ( $offer ) { $has_bvr_product = true; break; }
        }

        // Si aucun produit BVR → on crée quand même (car "toutes commandes = accès roue")
        // mais avec offre Starter par défaut
        $offer_slug = Wheel_Game_Offer::from_wc_order( $order );

        // Identifier ou créer le user (devrait exister via WC)
        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            // Guest checkout : créer un user
            $email = $order->get_billing_email();
            if ( $email && ! email_exists( $email ) ) {
                $user_id = wp_create_user(
                    $email,
                    wp_generate_password( 16, true, true ),
                    $email
                );
                if ( is_wp_error( $user_id ) ) return;
                $user = new WP_User( $user_id );
                $user->set_role( 'wheel_merchant' );
                $order->set_customer_id( $user_id );
                $order->save();
            } elseif ( $email ) {
                $user_id = email_exists( $email );
            }
        }

        if ( ! $user_id ) return;

        // Titre de la campagne : prénom + nom ou nom de société
        $company = $order->get_billing_company();
        $fname   = $order->get_billing_first_name();
        $lname   = $order->get_billing_last_name();
        $title   = trim( $company ?: trim( $fname . ' ' . $lname ) );
        if ( ! $title ) $title = 'Ma roue cadeaux';

        // Créer la campagne en brouillon avec les prix par défaut
        $campaign_id = wp_insert_post( [
            'post_type'   => Wheel_Game_Cpt::POST_TYPE,
            'post_status' => 'draft',
            'post_title'  => $title,
            'post_author' => $user_id,
        ] );

        if ( ! $campaign_id || is_wp_error( $campaign_id ) ) return;

        // Template générique + méta par défaut
        update_post_meta( $campaign_id, '_wheel_prizes', Wheel_Game_Campaign::default_prizes() );
        update_post_meta( $campaign_id, '_wheel_active', '0' ); // inactive tant que non publiée
        update_post_meta( $campaign_id, '_wheel_owner_id', $user_id );
        update_post_meta( $campaign_id, '_wheel_order_id', $order_id );
        Wheel_Game_Offer::set_for_campaign( $campaign_id, $offer_slug );

        // Token unique pour la config sans login
        $config_token = wp_generate_password( 32, false, false );
        update_post_meta( $campaign_id, '_wheel_config_token', $config_token );

        // Marquer la commande
        $order->update_meta_data( '_bvr_wheel_created',  $campaign_id );
        $order->update_meta_data( '_bvr_wheel_token',    $config_token );
        $order->save();

        // Envoyer l'email de config
        $this->send_config_email( $order, $campaign_id, $config_token );

        do_action( 'wheel_game_after_auto_create_campaign', $campaign_id, $order_id, $user_id );
    }

    /**
     * Redirige le client vers la page /configurer-ma-roue/ après paiement
     * si une campagne a été créée pour cette commande.
     */
    public function redirect_thankyou() {
        if ( ! function_exists( 'is_wc_endpoint_url' ) ) return;
        if ( ! is_wc_endpoint_url( 'order-received' ) ) return;

        global $wp;
        $order_id = absint( $wp->query_vars['order-received'] ?? 0 );
        if ( ! $order_id ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Seulement si order-key correspond (sécurité : URL directe ne redirige pas n'importe qui)
        $order_key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
        if ( ! $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) return;

        $campaign_id = (int) $order->get_meta( '_bvr_wheel_created' );
        $token       = $order->get_meta( '_bvr_wheel_token' );
        if ( ! $campaign_id || ! $token ) return;

        $config_url = self::build_config_url( $campaign_id, $token );
        wp_safe_redirect( $config_url );
        exit;
    }

    /**
     * Construit l'URL de configuration.
     */
    public static function build_config_url( $campaign_id, $token ) {
        return add_query_arg( [
            'c' => $campaign_id,
            't' => $token,
        ], home_url( '/configurer-ma-roue/' ) );
    }

    /**
     * Email de reprise de config.
     */
    private function send_config_email( $order, $campaign_id, $token ) {
        $to       = $order->get_billing_email();
        if ( ! $to ) return;

        $fname    = $order->get_billing_first_name();
        $config_url = self::build_config_url( $campaign_id, $token );
        $offer = Wheel_Game_Offer::for_campaign( $campaign_id );
        $offer_label = Wheel_Game_Offer::label( $offer );

        $subject = sprintf(
            /* translators: 1: site name */
            __( '🎡 Paramétrez votre roue cadeaux — %s', 'wheel-game' ),
            get_bloginfo( 'name' )
        );

        $body  = sprintf( __( "Bonjour %s,\n\n", 'wheel-game' ), $fname ) .
            __( "Merci pour votre commande ! Votre roue cadeaux vous attend : il ne reste qu'à la personnaliser.\n\n", 'wheel-game' ) .
            sprintf( __( "Offre : %s\n\n", 'wheel-game' ), $offer_label ) .
            __( "👉 Cliquez ici pour paramétrer votre roue :\n", 'wheel-game' ) .
            $config_url . "\n\n" .
            __( "Vous pourrez :\n", 'wheel-game' ) .
            __( "• Choisir vos cadeaux et leurs probabilités\n", 'wheel-game' ) .
            __( "• Personnaliser les couleurs aux couleurs de votre marque\n", 'wheel-game' ) .
            __( "• Ajouter votre logo\n\n", 'wheel-game' ) .
            __( "Une fois validée, vous recevrez votre lien public + QR code.\n\n", 'wheel-game' ) .
            __( "À très vite,\nL'équipe Boostez Votre Réputation", 'wheel-game' );

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $to, $subject, $body, $headers );

        // Log
        Wheel_Game_Cron::log( $campaign_id, 'config_email', 'sent', 'to=' . $to );
    }
}

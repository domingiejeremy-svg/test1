<?php
/**
 * Centralise l'envoi + le logging + l'édition des templates d'emails.
 *
 * - Templates stockés dans wheel_mail_template_{type}
 * - Log BDD dans wheel_mail_log
 * - Variables dynamiques : {variable} remplacées à l'envoi
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Mail {

    // Types d'emails supportés
    const TYPE_CONFIG_EMAIL  = 'config_email';
    const TYPE_BIG_PRIZE     = 'big_prize';
    const TYPE_SALES_DIRECT  = 'sales_direct';
    const TYPE_SALES_LIFETIME = 'sales_lifetime';

    /**
     * Retourne les types d'email avec leur métadonnée (label, variables dispo).
     */
    public static function types() {
        return [
            self::TYPE_CONFIG_EMAIL => [
                'label'  => __( 'Paramétrage de la roue (après commande)', 'wheel-game' ),
                'icon'   => '🎡',
                'desc'   => __( 'Envoyé au client dès paiement reçu, avec le lien pour paramétrer sa roue.', 'wheel-game' ),
                'vars'   => [
                    'client_first_name' => __( 'Prénom du client', 'wheel-game' ),
                    'client_last_name'  => __( 'Nom du client', 'wheel-game' ),
                    'client_full_name'  => __( 'Nom complet', 'wheel-game' ),
                    'offer_label'       => __( 'Offre souscrite', 'wheel-game' ),
                    'config_url'        => __( 'Lien unique de paramétrage', 'wheel-game' ),
                    'site_name'         => __( 'Nom du site', 'wheel-game' ),
                ],
            ],
            self::TYPE_BIG_PRIZE => [
                'label'  => __( 'Alerte gros lot gagné', 'wheel-game' ),
                'icon'   => '🎉',
                'desc'   => __( 'Envoyé au commerçant quand un prix avec % ≤ seuil est tiré.', 'wheel-game' ),
                'vars'   => [
                    'campaign_name'  => __( 'Nom de la campagne', 'wheel-game' ),
                    'prize_emoji'    => __( 'Emoji du prix', 'wheel-game' ),
                    'prize_line1'    => __( 'Ligne 1 du prix', 'wheel-game' ),
                    'prize_line2'    => __( 'Ligne 2 du prix', 'wheel-game' ),
                    'prize_percent'  => __( '% du prix gagné', 'wheel-game' ),
                    'lead_info'      => __( 'Infos du participant (nom, email, tél)', 'wheel-game' ),
                    'edit_url'       => __( 'Lien vers la campagne (admin)', 'wheel-game' ),
                ],
            ],
            self::TYPE_SALES_DIRECT => [
                'label'  => __( 'Notif commercial — Vente directe', 'wheel-game' ),
                'icon'   => '🔥',
                'desc'   => __( 'Envoyé au commercial quand un client utilise son coupon pour la première fois.', 'wheel-game' ),
                'vars'   => [
                    'rep_first_name'    => __( 'Prénom du commercial', 'wheel-game' ),
                    'rep_full_name'     => __( 'Nom complet commercial', 'wheel-game' ),
                    'order_number'      => __( 'Numéro de commande', 'wheel-game' ),
                    'client_name'       => __( 'Nom du client', 'wheel-game' ),
                    'amount_ht'         => __( 'Montant HT formaté', 'wheel-game' ),
                    'commission_rate'   => __( 'Taux de commission (%)', 'wheel-game' ),
                    'commission_amount' => __( 'Commission formatée', 'wheel-game' ),
                    'space_url'         => __( 'Lien vers espace commercial', 'wheel-game' ),
                ],
            ],
            self::TYPE_SALES_LIFETIME => [
                'label'  => __( 'Notif commercial — Renouvellement', 'wheel-game' ),
                'icon'   => '🔁',
                'desc'   => __( 'Envoyé au commercial quand un de ses clients passe une commande sans coupon (renouvellement).', 'wheel-game' ),
                'vars'   => [
                    'rep_first_name'    => __( 'Prénom du commercial', 'wheel-game' ),
                    'rep_full_name'     => __( 'Nom complet commercial', 'wheel-game' ),
                    'order_number'      => __( 'Numéro de commande', 'wheel-game' ),
                    'client_name'       => __( 'Nom du client', 'wheel-game' ),
                    'amount_ht'         => __( 'Montant HT formaté', 'wheel-game' ),
                    'commission_rate'   => __( 'Taux de commission (%)', 'wheel-game' ),
                    'commission_amount' => __( 'Commission formatée', 'wheel-game' ),
                    'space_url'         => __( 'Lien vers espace commercial', 'wheel-game' ),
                ],
            ],
        ];
    }

    /**
     * Templates par défaut (sujet + corps en texte brut).
     */
    public static function defaults() {
        return [
            self::TYPE_CONFIG_EMAIL => [
                'subject' => '🎡 Paramétrez votre roue cadeaux — {site_name}',
                'body'    => "Bonjour {client_first_name},\n\n"
                    . "Merci pour votre commande ! Votre roue cadeaux vous attend : il ne reste qu'à la personnaliser.\n\n"
                    . "Offre : {offer_label}\n\n"
                    . "👉 Cliquez ici pour paramétrer votre roue :\n{config_url}\n\n"
                    . "Vous pourrez :\n"
                    . "• Choisir vos cadeaux et leurs probabilités\n"
                    . "• Personnaliser les couleurs aux couleurs de votre marque\n"
                    . "• Ajouter votre logo\n\n"
                    . "Une fois validée, vous recevrez votre lien public + QR code.\n\n"
                    . "À très vite,\nL'équipe Boostez Votre Réputation",
            ],
            self::TYPE_BIG_PRIZE => [
                'subject' => '🎉 Gros lot gagné sur votre roue — {campaign_name}',
                'body'    => "Un participant vient de gagner un gros lot sur votre roue !\n\n"
                    . "Prix : {prize_emoji} {prize_line1} {prize_line2} ({prize_percent}%)\n"
                    . "Participant : {lead_info}\n\n"
                    . "Voir la campagne : {edit_url}",
            ],
            self::TYPE_SALES_DIRECT => [
                'subject' => '🎉 Nouvelle vente — {client_name}',
                'body'    => "Bonjour {rep_first_name},\n\n"
                    . "Une nouvelle commande vient d'être passée avec votre code :\n\n"
                    . "Type : 🔥 Nouvelle vente directe\n"
                    . "Commande : #{order_number}\n"
                    . "Client : {client_name}\n"
                    . "Montant HT : {amount_ht}\n"
                    . "Votre commission ({commission_rate}%) : {commission_amount}\n\n"
                    . "Retrouvez tous vos détails dans votre espace :\n{space_url}\n\n"
                    . "Bonne journée !\nL'équipe BVR",
            ],
            self::TYPE_SALES_LIFETIME => [
                'subject' => '🎉 Renouvellement — {client_name}',
                'body'    => "Bonjour {rep_first_name},\n\n"
                    . "Bonne nouvelle ! Un de vos clients vient de renouveler/repasser commande — votre commission tombe automatiquement :\n\n"
                    . "Type : 🔁 Renouvellement client\n"
                    . "Commande : #{order_number}\n"
                    . "Client : {client_name}\n"
                    . "Montant HT : {amount_ht}\n"
                    . "Votre commission ({commission_rate}%) : {commission_amount}\n\n"
                    . "Retrouvez tous vos détails dans votre espace :\n{space_url}\n\n"
                    . "Bonne journée !\nL'équipe BVR",
            ],
        ];
    }

    /**
     * Récupère un template (option ou défaut).
     */
    public static function get_template( $type ) {
        $stored = get_option( 'wheel_mail_template_' . $type );
        if ( is_array( $stored ) && ! empty( $stored['subject'] ) && ! empty( $stored['body'] ) ) {
            return $stored;
        }
        return self::defaults()[ $type ] ?? [ 'subject' => '', 'body' => '' ];
    }

    public static function save_template( $type, $subject, $body ) {
        if ( ! isset( self::types()[ $type ] ) ) return false;
        update_option( 'wheel_mail_template_' . $type, [
            'subject' => sanitize_text_field( $subject ),
            'body'    => wp_kses_post( $body ), // autorise HTML basique si besoin plus tard
        ] );
        return true;
    }

    public static function reset_template( $type ) {
        delete_option( 'wheel_mail_template_' . $type );
    }

    /**
     * Envoie un email du type donné avec substitution des variables.
     *
     * @param string $type      Type (constante self::TYPE_*)
     * @param string $to        Destinataire
     * @param array  $vars      Variables à remplacer dans le template
     * @param array  $metadata  Méta optionnelles pour le log : ['campaign_id' => N, 'order_id' => N]
     * @return bool
     */
    public static function send( $type, $to, array $vars = [], array $metadata = [] ) {
        $template = self::get_template( $type );
        if ( empty( $template['subject'] ) ) return false;

        // Variables toujours disponibles
        $vars = array_merge( [
            'site_name' => get_bloginfo( 'name' ),
        ], $vars );

        $subject = self::render( $template['subject'], $vars );
        $body    = self::render( $template['body'], $vars );

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        $sent = wp_mail( $to, wp_strip_all_tags( $subject ), $body, $headers );

        self::log( [
            'type'        => $type,
            'recipient'   => $to,
            'subject'     => $subject,
            'body'        => $body,
            'status'      => $sent ? 'sent' : 'failed',
            'campaign_id' => (int) ( $metadata['campaign_id'] ?? 0 ),
            'order_id'    => (int) ( $metadata['order_id'] ?? 0 ),
        ] );

        return $sent;
    }

    /**
     * Remplace {variable} par la valeur dans vars.
     */
    public static function render( $template, array $vars ) {
        foreach ( $vars as $key => $val ) {
            $template = str_replace( '{' . $key . '}', (string) $val, $template );
        }
        return $template;
    }

    /**
     * Insère une entrée dans le log + purge auto > 90j.
     */
    private static function log( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wheel_mail_log';
        $wpdb->insert( $table, [
            'type'        => $data['type'],
            'recipient'   => $data['recipient'],
            'subject'     => $data['subject'],
            'body'        => $data['body'],
            'status'      => $data['status'],
            'campaign_id' => $data['campaign_id'],
            'order_id'    => $data['order_id'],
            'created_at'  => current_time( 'mysql' ),
        ] );

        // Purge > 90j (1 fois sur 20 pour limiter l'overhead)
        if ( wp_rand( 1, 20 ) === 1 ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS )
            ) );
        }
    }

    /**
     * Liste les derniers emails envoyés (pour page admin).
     */
    public static function get_log( $filters = [], $limit = 100 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wheel_mail_log';
        $where = [ '1=1' ];
        $params = [];

        if ( ! empty( $filters['type'] ) ) {
            $where[] = 'type = %s';
            $params[] = $filters['type'];
        }
        if ( ! empty( $filters['status'] ) ) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        if ( ! empty( $filters['recipient'] ) ) {
            $where[] = 'recipient LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $filters['recipient'] ) . '%';
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where )
             . ' ORDER BY created_at DESC LIMIT ' . (int) $limit;

        if ( $params ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }
        return $wpdb->get_results( $sql );
    }

    public static function get_log_entry( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wheel_mail_log WHERE id = %d", $id
        ) );
    }

    /**
     * Renvoie un email à partir d'un log (même contenu, même destinataire).
     */
    public static function resend( $log_id ) {
        $entry = self::get_log_entry( $log_id );
        if ( ! $entry ) return false;
        $sent = wp_mail( $entry->recipient, $entry->subject, $entry->body,
            [ 'Content-Type: text/plain; charset=UTF-8' ] );
        self::log( [
            'type'        => $entry->type . '_resent',
            'recipient'   => $entry->recipient,
            'subject'     => $entry->subject,
            'body'        => $entry->body,
            'status'      => $sent ? 'sent' : 'failed',
            'campaign_id' => (int) $entry->campaign_id,
            'order_id'    => (int) $entry->order_id,
        ] );
        return $sent;
    }
}

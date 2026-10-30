<?php
/**
 * Plugin Name: Padel Occas — Suivi des demandes
 * Description: Ajoute un statut de traitement (menu déroulant), un historique et l'envoi de mails types directement depuis la liste des demandes (réparation / reprise).
 * Version:     1.3.0
 * Author:      Padel Occas
 * License:     GPL v2 or later
 * Text Domain: poc-suivi
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'POC_SUIVI_VERSION', '1.3.0' );

/* ════════════════════════════════════════════════════════════════════════════
   CLASSE PRINCIPALE
   ════════════════════════════════════════════════════════════════════════════ */
class POC_Suivi_Demandes {

    const POST_TYPE   = 'poc_demande';
    const META_STATUT = '_poc_statut';
    const META_HISTO  = '_poc_historique';
    const NONCE       = 'poc_suivi';
    const DEFAUT      = 'nouveau';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Colonne + filtres de la liste
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',       [ $this, 'add_column' ], 20 );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_filter( 'views_edit-' . self::POST_TYPE,                      [ $this, 'add_views' ] );
        add_action( 'restrict_manage_posts',                              [ $this, 'filter_dropdown' ] );
        add_action( 'pre_get_posts',                                      [ $this, 'filter_query' ] );

        // AJAX
        add_action( 'wp_ajax_poc_set_statut', [ $this, 'ajax_set_statut' ] );
        add_action( 'wp_ajax_poc_send_mail',  [ $this, 'ajax_send_mail' ] );

        // CSS / JS / fenêtre d'envoi
        add_action( 'admin_head',   [ $this, 'print_css' ] );
        add_action( 'admin_footer', [ $this, 'print_modal_and_js' ] );
    }

    /* ── Données ──────────────────────────────────────────────────────────── */

    public static function statuts() {
        return apply_filters( 'poc_suivi_statuts', [
            'nouveau'          => [ 'label' => 'À traiter',                      'groupe' => 'a_traiter' ],
            'mail_photos'      => [ 'label' => 'Mail envoyé · photos demandées', 'groupe' => 'attente' ],
            'mail_reparation'  => [ 'label' => 'Mail envoyé · réparation',       'groupe' => 'attente' ],
            'mail_irreparable' => [ 'label' => 'Mail envoyé · irréparable',      'groupe' => 'attente' ],
            'mail_reprise'     => [ 'label' => 'Mail envoyé · offre de reprise', 'groupe' => 'attente' ],
            'relance'          => [ 'label' => 'Relance envoyée',                'groupe' => 'attente' ],
            'accord'           => [ 'label' => 'Accord trouvé',                  'groupe' => 'en_cours' ],
            'recue'            => [ 'label' => 'Raquette reçue / en cours',      'groupe' => 'en_cours' ],
            'refus'            => [ 'label' => 'Refus · clos',                   'groupe' => 'clos' ],
            'clos'             => [ 'label' => 'Clos',                           'groupe' => 'clos' ],
            'doublon'          => [ 'label' => 'Doublon / spam',                 'groupe' => 'clos' ],
        ] );
    }

    public static function groupes() {
        return [
            'a_traiter' => 'À traiter',
            'attente'   => 'En attente du client',
            'en_cours'  => 'Accord / en cours',
            'clos'      => 'Clos',
        ];
    }

    /**
     * Mails types. {modele} est remplacé automatiquement ;
     * les [À COMPLÉTER : …] doivent être remplis avant l'envoi.
     */
    public static function modeles_mail() {
        $signature = "\n\nÀ bientôt,\nL'équipe Padel Occas\npadeloccas.fr";
        return apply_filters( 'poc_suivi_modeles_mail', [
            'photos' => [
                'label'  => 'Demande de photos supplémentaires',
                'statut' => 'mail_photos',
                'objet'  => 'Votre demande pour {modele} – photos supplémentaires',
                'corps'  => "Bonjour,\n\nMerci pour votre demande concernant votre raquette {modele}.\n\nPour vous faire une proposition précise, nous avons besoin de quelques photos supplémentaires de votre raquette :\n- la face avant et la face arrière en entier\n- le tour du cadre (la tranche), y compris le haut de la raquette\n- un gros plan de chaque zone abîmée (fissure, éclat, choc)\n- une photo nette du nom du modèle exact inscrit sur la raquette (nom, année, et la référence sur le cœur ou le manche si elle y figure)\n\nMerci d'envoyer des photos de votre propre raquette, prises par vous, et non des images tirées d'une annonce ou d'un site internet : elles nous servent à vérifier son état réel et son modèle exact.\n\nVous pouvez simplement répondre à ce mail en joignant les photos." . $signature,
            ],
            'reparation' => [
                'label'  => 'Réparation possible (devis)',
                'statut' => 'mail_reparation',
                'objet'  => 'Votre demande de réparation – {modele}',
                'corps'  => "Bonjour,\n\nMerci pour votre demande concernant votre raquette {modele}.\n\nAprès examen de vos photos, la réparation est possible :\n- Intervention : [À COMPLÉTER : type de réparation]\n- Tarif : [À COMPLÉTER : prix] €\n- Délai : [À COMPLÉTER : délai] jours après réception\n\nSi cela vous convient, répondez simplement à ce mail et nous vous enverrons les instructions pour nous faire parvenir la raquette." . $signature,
            ],
            'irreparable' => [
                'label'  => 'Réparation impossible (avec code 10 %)',
                'statut' => 'mail_irreparable',
                'objet'  => 'Votre raquette {modele} – retour sur la réparation',
                'corps'  => "Bonjour,\n\nMerci pour votre demande concernant votre raquette {modele}.\n\nMalheureusement, d'après vos photos, les dommages ne permettent pas une réparation fiable : [À COMPLÉTER : raison].\n\nNous préférons vous le dire franchement plutôt que de vous facturer une réparation qui ne tiendrait pas dans le temps.\n\nSi vous cherchez une nouvelle raquette, vous trouverez sur padeloccas.fr des raquettes d'occasion contrôlées et révisées. Pour vous aider à repartir sur le terrain, voici un code de 10 % sur votre prochaine commande : OCCAS10 (valable 30 jours, une utilisation, hors articles déjà en promotion).\n\nN'hésitez pas à répondre à ce mail si vous voulez un conseil sur le modèle qui vous conviendrait." . $signature,
            ],
            'reprise' => [
                'label'  => 'Offre de reprise',
                'statut' => 'mail_reprise',
                'objet'  => 'Notre offre de reprise pour votre {modele}',
                'corps'  => "Bonjour,\n\nMerci pour votre demande de reprise concernant votre raquette {modele}.\n\nVoici notre offre :\n- Reprise en cash : [À COMPLÉTER : montant] €\n- Reprise en avoir sur padeloccas.fr : [À COMPLÉTER : montant] €\n\nL'offre est valable 7 jours, sous réserve de l'état constaté à réception. Répondez à ce mail pour l'accepter : nous vous enverrons ensuite les instructions d'envoi." . $signature,
            ],
            'refus_offre' => [
                'label'  => "Refus de l'offre (avec code 10 %)",
                'statut' => 'refus',
                'objet'  => 'Votre raquette {modele} – suite à notre offre',
                'corps'  => "Bonjour,\n\nMerci pour votre retour concernant notre offre de reprise pour votre raquette {modele}. Nous comprenons tout à fait votre décision.\n\nSi vous cherchez une nouvelle raquette, vous trouverez sur padeloccas.fr des raquettes d'occasion contrôlées et révisées. Pour vous remercier de nous avoir contactés, voici un code de 10 % sur votre prochaine commande : OCCAS10 (valable 30 jours, une utilisation, hors articles déjà en promotion).\n\nEt si vous changez d'avis sur la reprise, il suffit de répondre à ce mail." . $signature,
            ],
            'reparee' => [
                'label'  => 'Réparation terminée (avec code 10 %)',
                'statut' => 'clos',
                'objet'  => 'Votre raquette {modele} est réparée',
                'corps'  => "Bonjour,\n\nBonne nouvelle : la réparation de votre raquette {modele} est terminée. Elle vous a été renvoyée [À COMPLÉTER : date d'envoi et numéro de suivi].\n\nMerci de nous avoir fait confiance. Pour votre prochaine commande sur padeloccas.fr, voici un code de 10 % sur votre prochaine commande : OCCAS10 (valable 30 jours, une utilisation, hors articles déjà en promotion)." . $signature,
            ],
            'relance' => [
                'label'  => 'Relance',
                'statut' => 'relance',
                'objet'  => 'Relance – votre demande pour {modele}',
                'corps'  => "Bonjour,\n\nNous revenons vers vous au sujet de votre demande pour votre raquette {modele}.\n\nNotre proposition est toujours d'actualité : avez-vous pu y réfléchir ? N'hésitez pas à répondre à ce mail si vous avez la moindre question." . $signature,
            ],
            'libre' => [
                'label'  => 'Message libre',
                'statut' => '',
                'objet'  => 'Votre demande – {modele}',
                'corps'  => "Bonjour,\n\n" . $signature,
            ],
        ] );
    }

    public static function get_statut( $post_id ) {
        $s = get_post_meta( $post_id, self::META_STATUT, true );
        return isset( self::statuts()[ $s ] ) ? $s : self::DEFAUT;
    }

    private static function get_histo( $post_id ) {
        $h = get_post_meta( $post_id, self::META_HISTO, true );
        return is_array( $h ) ? $h : [];
    }

    private static function add_histo( $post_id, $texte ) {
        $h   = self::get_histo( $post_id );
        $h[] = [
            't'    => current_time( 'timestamp' ),
            'user' => wp_get_current_user()->display_name,
            'txt'  => $texte,
        ];
        update_post_meta( $post_id, self::META_HISTO, $h );
    }

    /** "Accord trouvé · Mathieu, 30/10 14h05" + historique complet en infobulle */
    private static function meta_html( $post_id ) {
        $h = self::get_histo( $post_id );
        if ( ! $h ) return '<span class="poc-meta">Aucune action pour l\'instant</span>';

        $last  = end( $h );
        $lines = array_map( function ( $e ) {
            return date_i18n( 'd/m H\hi', $e['t'] ) . ' · ' . $e['user'] . ' · ' . $e['txt'];
        }, array_reverse( $h ) );

        return sprintf(
            '<span class="poc-meta" title="%s">%s, %s · historique (%d)</span>',
            esc_attr( implode( "\n", $lines ) ),
            esc_html( $last['user'] ),
            esc_html( date_i18n( 'd/m H\hi', $last['t'] ) ),
            count( $h )
        );
    }

    private static function is_list_screen() {
        if ( ! function_exists( 'get_current_screen' ) ) return false;
        $s = get_current_screen();
        return $s && 'edit-' . self::POST_TYPE === $s->id;
    }

    /* ── Colonne ──────────────────────────────────────────────────────────── */

    public function add_column( $cols ) {
        $new = [];
        foreach ( $cols as $k => $v ) {
            if ( 'date' === $k ) $new['poc_statut'] = 'Suivi';
            $new[ $k ] = $v;
        }
        if ( ! isset( $new['poc_statut'] ) ) $new['poc_statut'] = 'Suivi';
        return $new;
    }

    public function render_column( $col, $post_id ) {
        if ( 'poc_statut' !== $col ) return;

        $statut = self::get_statut( $post_id );
        $titre  = get_the_title( $post_id );
        $parts  = preg_split( '/\s+[—–-]\s+/u', $titre, 2 );
        $modele = isset( $parts[1] ) ? $parts[1] : $titre;
        $type   = ( false !== stripos( $titre, 'reprise' ) ) ? 'reprise' : 'reparation';

        echo '<div class="poc-suivi" data-id="' . esc_attr( $post_id ) . '" data-modele="' . esc_attr( $modele ) . '" data-type="' . esc_attr( $type ) . '">';
        echo '<select class="poc-statut" data-statut="' . esc_attr( $statut ) . '">';
        foreach ( self::statuts() as $k => $s ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $statut, $k, false ), esc_html( $s['label'] ) );
        }
        echo '</select>';
        echo '<button type="button" class="button button-small poc-mail-btn">✉ Envoyer un mail</button>';
        echo '<div class="poc-meta-wrap">' . self::meta_html( $post_id ) . '</div>'; // phpcs:ignore -- échappé dans meta_html
        echo '</div>';
    }

    /* ── Onglets « À traiter (3) | En attente du client (5) … » ───────────── */

    private static function statuts_du_groupe( $groupe ) {
        return array_keys( array_filter( self::statuts(), function ( $s ) use ( $groupe ) {
            return $s['groupe'] === $groupe;
        } ) );
    }

    private static function meta_query_for( $statuts ) {
        $mq = [ 'relation' => 'OR', [ 'key' => self::META_STATUT, 'value' => $statuts, 'compare' => 'IN' ] ];
        if ( in_array( self::DEFAUT, $statuts, true ) ) {
            $mq[] = [ 'key' => self::META_STATUT, 'compare' => 'NOT EXISTS' ];
        }
        return $mq;
    }

    public function add_views( $views ) {
        $courant = isset( $_GET['poc_statut'] ) ? sanitize_key( $_GET['poc_statut'] ) : '';
        foreach ( self::groupes() as $g => $label ) {
            $q = new WP_Query( [
                'post_type'      => self::POST_TYPE,
                'post_status'    => [ 'publish', 'pending', 'draft', 'private', 'future' ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => self::meta_query_for( self::statuts_du_groupe( $g ) ),
            ] );
            $url = add_query_arg( [ 'post_type' => self::POST_TYPE, 'poc_statut' => 'g_' . $g ], admin_url( 'edit.php' ) );
            $views[ 'poc_' . $g ] = sprintf(
                '<a href="%s" class="poc-view poc-view-%s%s">%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                esc_attr( $g ),
                'g_' . $g === $courant ? ' current' : '',
                esc_html( $label ),
                count( $q->posts )
            );
        }
        return $views;
    }

    /* ── Filtre déroulant au-dessus de la liste ──────────────────────────── */

    public function filter_dropdown( $post_type ) {
        if ( self::POST_TYPE !== $post_type ) return;
        $courant = isset( $_GET['poc_statut'] ) ? sanitize_key( $_GET['poc_statut'] ) : '';
        echo '<select name="poc_statut"><option value="">Tous les suivis</option>';
        foreach ( self::statuts() as $k => $s ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $courant, $k, false ), esc_html( $s['label'] ) );
        }
        echo '</select>';
    }

    public function filter_query( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) return;
        if ( empty( $_GET['poc_statut'] ) ) return;

        $f = sanitize_key( $_GET['poc_statut'] );
        if ( 0 === strpos( $f, 'g_' ) ) {
            $statuts = self::statuts_du_groupe( substr( $f, 2 ) );
        } else {
            $statuts = isset( self::statuts()[ $f ] ) ? [ $f ] : [];
        }
        if ( $statuts ) $query->set( 'meta_query', self::meta_query_for( $statuts ) );
    }

    /* ── AJAX ─────────────────────────────────────────────────────────────── */

    private static function check_request() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }
        return $post_id;
    }

    private static function set_statut( $post_id, $statut, $note = '' ) {
        update_post_meta( $post_id, self::META_STATUT, $statut );
        $label = self::statuts()[ $statut ]['label'];
        self::add_histo( $post_id, $note ? $label . ' (' . $note . ')' : $label );
    }

    public function ajax_set_statut() {
        $post_id = self::check_request();
        $statut  = isset( $_POST['statut'] ) ? sanitize_key( $_POST['statut'] ) : '';
        if ( ! isset( self::statuts()[ $statut ] ) ) wp_send_json_error( [ 'message' => 'Statut inconnu.' ], 400 );

        self::set_statut( $post_id, $statut );
        wp_send_json_success( [ 'statut' => $statut, 'meta' => self::meta_html( $post_id ) ] );
    }

    public function ajax_send_mail() {
        $post_id = self::check_request();

        $to     = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
        $objet  = isset( $_POST['objet'] ) ? sanitize_text_field( wp_unslash( $_POST['objet'] ) ) : '';
        $corps  = isset( $_POST['corps'] ) ? sanitize_textarea_field( wp_unslash( $_POST['corps'] ) ) : '';
        $statut = isset( $_POST['statut'] ) ? sanitize_key( $_POST['statut'] ) : '';

        if ( ! is_email( $to ) )   wp_send_json_error( [ 'message' => 'Adresse e-mail invalide.' ], 400 );
        if ( ! $objet || ! $corps ) wp_send_json_error( [ 'message' => 'Objet ou message vide.' ], 400 );

        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1d2327">'
              . nl2br( esc_html( $corps ) ) . '</div>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $reply   = apply_filters( 'poc_suivi_reply_to', get_option( 'admin_email' ) );
        if ( is_email( $reply ) ) $headers[] = 'Reply-To: ' . $reply;

        if ( ! wp_mail( $to, $objet, $html, $headers ) ) {
            wp_send_json_error( [ 'message' => "Le mail n'est pas parti (configuration d'envoi du site). Rien n'a été modifié." ], 500 );
        }

        if ( isset( self::statuts()[ $statut ] ) ) {
            self::set_statut( $post_id, $statut, 'mail « ' . $objet . ' »' );
        } else {
            self::add_histo( $post_id, 'Mail envoyé « ' . $objet . ' »' );
        }

        wp_send_json_success( [ 'statut' => self::get_statut( $post_id ), 'meta' => self::meta_html( $post_id ) ] );
    }

    /* ── Interface ────────────────────────────────────────────────────────── */

    public function print_css() {
        if ( ! self::is_list_screen() ) return;
        ?>
        <style>
            .column-poc_statut { width: 230px; }
            .poc-suivi select.poc-statut { width: 100%; max-width: 230px; font-weight: 600; border-radius: 6px; border-width: 1px; margin-bottom: 6px; }
            .poc-suivi .poc-mail-btn { margin-bottom: 4px; }
            .poc-meta { display: block; font-size: 11px; color: #757575; cursor: help; }
            .poc-statut[data-statut="nouveau"]                                                           { background: #fcf0f1; border-color: #d63638; color: #8a1f21; }
            .poc-statut[data-statut^="mail_"], .poc-statut[data-statut="relance"]                        { background: #eef4fc; border-color: #2271b1; color: #135e96; }
            .poc-statut[data-statut="accord"], .poc-statut[data-statut="recue"]                          { background: #edfaef; border-color: #00a32a; color: #0a6b25; }
            .poc-statut[data-statut="refus"], .poc-statut[data-statut="clos"], .poc-statut[data-statut="doublon"] { background: #f0f0f1; border-color: #c3c4c7; color: #646970; }
            tr.poc-row-nouveau td:first-child, tr.poc-row-nouveau th:first-child { box-shadow: inset 4px 0 0 #d63638; }
            tr.poc-row-clos { opacity: .55; }
            tr.poc-row-clos:hover { opacity: 1; }
            .poc-view-a_traiter { color: #b32d2e !important; font-weight: 600; }

            #poc-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 100000; }
            #poc-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 640px; max-width: calc(100vw - 32px); max-height: calc(100vh - 40px); overflow: auto; background: #fff; border-radius: 10px; padding: 22px 24px; box-shadow: 0 10px 40px rgba(0,0,0,.25); }
            #poc-modal h2 { margin: 0 0 14px; }
            #poc-modal label { display: block; font-weight: 600; margin: 12px 0 4px; }
            #poc-modal input[type=text], #poc-modal input[type=email], #poc-modal select, #poc-modal textarea { width: 100%; }
            #poc-modal textarea { min-height: 260px; font-family: inherit; }
            #poc-modal .poc-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; gap: 12px; }
            #poc-modal .poc-hint { color: #757575; font-size: 12px; margin-top: 4px; }
            #poc-modal .poc-error { color: #b32d2e; font-weight: 600; }
        </style>
        <?php
    }

    public function print_modal_and_js() {
        if ( ! self::is_list_screen() ) return;

        $data = [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE ),
            'modeles' => self::modeles_mail(),
            'statuts' => wp_list_pluck( self::statuts(), 'label' ),
        ];
        ?>
        <div id="poc-modal-bg">
            <div id="poc-modal" role="dialog" aria-modal="true" aria-labelledby="poc-modal-title">
                <h2 id="poc-modal-title">Envoyer un mail</h2>

                <label for="poc-m-modele">Modèle de mail</label>
                <select id="poc-m-modele"></select>

                <label for="poc-m-to">À</label>
                <input type="email" id="poc-m-to">

                <label for="poc-m-objet">Objet</label>
                <input type="text" id="poc-m-objet">

                <label for="poc-m-corps">Message</label>
                <textarea id="poc-m-corps"></textarea>
                <div class="poc-hint">Remplacez chaque [À COMPLÉTER : …] avant d'envoyer. Le client répondra à l'adresse e-mail du site.</div>

                <label><input type="checkbox" id="poc-m-maj" checked> <span id="poc-m-maj-txt"></span></label>

                <div class="poc-actions">
                    <span class="poc-error" id="poc-m-err"></span>
                    <span>
                        <button type="button" class="button" id="poc-m-cancel">Annuler</button>
                        <button type="button" class="button button-primary" id="poc-m-send">Envoyer</button>
                    </span>
                </div>
            </div>
        </div>

        <script>
        jQuery(function ($) {
            var D = <?php echo wp_json_encode( $data ); ?>;
            var current = null; // .poc-suivi du mail en cours

            function paintRow($wrap, statut) {
                var $tr = $wrap.closest('tr');
                $tr.removeClass('poc-row-nouveau poc-row-clos');
                if (statut === 'nouveau') $tr.addClass('poc-row-nouveau');
                if (['refus', 'clos', 'doublon'].indexOf(statut) !== -1) $tr.addClass('poc-row-clos');
                $wrap.find('.poc-statut').val(statut).attr('data-statut', statut);
            }
            $('.poc-suivi').each(function () { paintRow($(this), $(this).find('.poc-statut').val()); });

            /* Changement de statut */
            $(document).on('change', '.poc-statut', function () {
                var $sel = $(this), $wrap = $sel.closest('.poc-suivi'), before = $sel.attr('data-statut');
                $sel.prop('disabled', true);
                $.post(D.ajax, { action: 'poc_set_statut', nonce: D.nonce, post_id: $wrap.data('id'), statut: $sel.val() })
                    .done(function (r) {
                        if (!r.success) { alert(r.data.message); paintRow($wrap, before); return; }
                        paintRow($wrap, r.data.statut);
                        $wrap.find('.poc-meta-wrap').html(r.data.meta);
                    })
                    .fail(function () { alert('Erreur réseau, statut non enregistré.'); paintRow($wrap, before); })
                    .always(function () { $sel.prop('disabled', false); });
            });

            /* Fenêtre d'envoi */
            $.each(D.modeles, function (k, m) { $('#poc-m-modele').append($('<option>').val(k).text(m.label)); });

            function findEmail($tr) {
                var $a = $tr.find('a[href^="mailto:"]').first();
                if ($a.length) return $a.attr('href').replace(/^mailto:/i, '').split('?')[0];
                var m = $tr.text().match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
                return m ? m[0] : '';
            }

            function applyModele() {
                var m = D.modeles[$('#poc-m-modele').val()], mod = current.data('modele');
                $('#poc-m-objet').val(m.objet.split('{modele}').join(mod));
                $('#poc-m-corps').val(m.corps.split('{modele}').join(mod));
                if (m.statut) {
                    $('#poc-m-maj').prop('checked', true).closest('label').show();
                    $('#poc-m-maj-txt').text('Passer le suivi à « ' + D.statuts[m.statut] + ' » après envoi');
                } else {
                    $('#poc-m-maj').prop('checked', false).closest('label').hide();
                }
            }
            $('#poc-m-modele').on('change', applyModele);

            $(document).on('click', '.poc-mail-btn', function () {
                current = $(this).closest('.poc-suivi');
                $('#poc-m-err').text('');
                $('#poc-m-to').val(findEmail(current.closest('tr')));
                $('#poc-m-modele').val(current.data('type') === 'reprise' ? 'reprise' : 'reparation');
                applyModele();
                $('#poc-modal-bg').show();
                $('#poc-m-corps').trigger('focus');
            });

            function closeModal() { $('#poc-modal-bg').hide(); current = null; }
            $('#poc-m-cancel').on('click', closeModal);
            $('#poc-modal-bg').on('click', function (e) { if (e.target === this) closeModal(); });
            $(document).on('keydown', function (e) { if (e.key === 'Escape' && current) closeModal(); });

            $('#poc-m-send').on('click', function () {
                var $btn = $(this), corps = $('#poc-m-corps').val(), objet = $('#poc-m-objet').val();
                if (/\[À COMPLÉTER/i.test(corps + objet)) {
                    $('#poc-m-err').text('Il reste des [À COMPLÉTER : …] dans le message.');
                    return;
                }
                var m = D.modeles[$('#poc-m-modele').val()];
                $btn.prop('disabled', true).text('Envoi…');
                $.post(D.ajax, {
                    action: 'poc_send_mail', nonce: D.nonce, post_id: current.data('id'),
                    to: $('#poc-m-to').val(), objet: objet, corps: corps,
                    statut: $('#poc-m-maj').is(':checked') ? m.statut : ''
                }).done(function (r) {
                    if (!r.success) { $('#poc-m-err').text(r.data.message); return; }
                    paintRow(current, r.data.statut);
                    current.find('.poc-meta-wrap').html(r.data.meta);
                    closeModal();
                }).fail(function (x) {
                    var msg = x.responseJSON && x.responseJSON.data ? x.responseJSON.data.message : 'Erreur réseau.';
                    $('#poc-m-err').text(msg);
                }).always(function () { $btn.prop('disabled', false).text('Envoyer'); });
            });
        });
        </script>
        <?php
    }
}

POC_Suivi_Demandes::get_instance();

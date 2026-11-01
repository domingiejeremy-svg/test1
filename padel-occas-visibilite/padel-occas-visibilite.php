<?php
/**
 * Plugin Name: Padel Occas — Visibilité IA et moteurs
 * Description: llms.txt, IndexNow (Bing, ChatGPT), FAQ structurées lues dans le contenu des pages, description SEO des nouvelles pages et question « Comment nous avez-vous connus ? » au paiement.
 * Version:     1.0.1
 * Author:      Padel Occas
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ════════════════════════════════════════════════════════════════════════════
   CLASSE PRINCIPALE
   ════════════════════════════════════════════════════════════════════════════ */
class POC_Visibilite {

    const VERSION      = '1.0.1';
    const INDEXNOW_KEY = 'd6ce2802a50793899c9855035e542a3d';
    const SOURCE_FIELD = 'padeloccas/source';

    /** Descriptions SEO des pages qui n'en ont pas dans Rank Math (slug => texte) */
    const DESCRIPTIONS = [
        'raquette-padel-cassee-reparable' => 'Raquette de padel cassée ou fissurée : réparable ou pas selon la zone touchée, prix (30 à 40 €), délais et diagnostic gratuit sur photo sous 24 h.',
    ];

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',              [ $this, 'serve_text_files' ], 0 );
        add_action( 'wp_head',           [ $this, 'print_faq_schema' ], 20 );
        add_filter( 'rank_math/frontend/description', [ $this, 'description' ] );
        add_action( 'transition_post_status', [ $this, 'indexnow_on_publish' ], 10, 3 );
        add_action( 'woocommerce_init',  [ $this, 'register_source_field' ] );
        add_action( 'init',              [ $this, 'purge_cache_once' ], 20 );
    }

    /** Vide le cache LiteSpeed des fichiers texte une fois par version (un 404 a pu y rester) */
    public function purge_cache_once() {
        if ( get_option( 'poc_visibilite_purged' ) === self::VERSION ) return;
        do_action( 'litespeed_purge_url', home_url( '/llms.txt' ) );
        do_action( 'litespeed_purge_url', home_url( '/' . self::INDEXNOW_KEY . '.txt' ) );
        update_option( 'poc_visibilite_purged', self::VERSION, false );
    }

    /* ── /llms.txt et clé IndexNow ───────────────────────────────────────── */

    public function serve_text_files() {
        $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

        if ( 'llms.txt' === $path ) {
            $this->send_text( self::llms_txt() );
        }
        if ( self::INDEXNOW_KEY . '.txt' === $path ) {
            $this->send_text( self::INDEXNOW_KEY );
        }
    }

    private function send_text( $body ) {
        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        echo $body; // phpcs:ignore -- texte brut
        exit;
    }

    private static function llms_txt() {
        $u = home_url( '/' );
        return <<<TXT
# Padel Occas

> Atelier de réparation de raquettes de padel et boutique de raquettes d'occasion contrôlées, en France. Diagnostic gratuit sur photo sous 24 h ouvrées, réparation au carbone et à la résine époxy dès 30 €, garantie 3 mois.

## Réparation de raquettes de padel

- Diagnostic gratuit sur photo, réponse sous 24 h ouvrées : {$u}reparation-raquette-padel/
- Fissure de moins de 3 cm : 30 €. Fissure de plus de 3 cm ou deux zones : 40 €.
- Port aller-retour : 5 € en point relais, offert au-delà de deux raquettes.
- Délai : 7 jours transport compris. Garantie : 3 mois sur la zone réparée.
- Réparable : haut du cadre, tranches latérales. À examiner : plan de frappe, épaules basses (refus au-delà de 6 cm). Non réparable : cœur / pont, délaminage.
- Si la réparation est refusée après réception, la raquette est renvoyée sans frais.

## Guides

- [Raquette cassée : est-elle réparable ?]({$u}raquette-padel-cassee-reparable/)
- [Raquette fissurée : réparer ou racheter ?]({$u}raquette-padel-fissuree-reparer-ou-racheter/)
- [Prix d'une réparation de raquette de padel]({$u}prix-reparation-raquette-padel/)
- [Raquette délaminée : le test en deux minutes]({$u}raquette-padel-delaminee/)
- [Réparer sa raquette soi-même]({$u}reparer-raquette-padel-soi-meme/)
- [Où faire réparer sa raquette ?]({$u}ou-faire-reparer-raquette-padel/)
- [Protecteur de raquette : utile ou pas ?]({$u}protecteur-raquette-padel/)
- [Durée de vie d'une raquette de padel]({$u}duree-de-vie-raquette-padel/)

## Boutique et reprise

- [Raquettes de padel d'occasion contrôlées]({$u}boutique/) : inspectées une par une, pesées, défauts photographiés.
- [Le contrôle en 12 points]({$u}le-controle/)
- [Revendre sa raquette]({$u}revendre/) : estimation immédiate, reprise en cash ou en avoir.
- [Trouver ma raquette]({$u}trouver-ma-raquette/) : questionnaire pour choisir un modèle.
- [Livraison et retours]({$u}livraison-retours/) · [Garantie]({$u}garantie/) · [Contact]({$u}contact/)
TXT;
    }

    /* ── FAQPage lue dans le contenu, entre <!-- poc:faq --> et <!-- /poc:faq --> ── */

    public function print_faq_schema() {
        if ( ! is_singular() ) return;
        $content = get_post_field( 'post_content', get_queried_object_id() );
        if ( ! preg_match( '/<!--\s*poc:faq\s*-->(.*?)<!--\s*\/poc:faq\s*-->/s', $content, $m ) ) return;

        preg_match_all( '/<h3[^>]*>(.*?)<\/h3>(.*?)(?=<h3|$)/s', $m[1], $qa, PREG_SET_ORDER );
        $items = [];
        foreach ( $qa as $row ) {
            $q = trim( wp_strip_all_tags( $row[1] ) );
            $a = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $row[2] ) ) );
            if ( $q && $a ) {
                $items[] = [ '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a ] ];
            }
        }
        if ( ! $items ) return;

        echo '<script type="application/ld+json">'
            . wp_json_encode( [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            . "</script>\n";
    }

    /* ── Description SEO ─────────────────────────────────────────────────── */

    public function description( $desc ) {
        if ( ! is_singular() ) return $desc;
        $slug = get_post_field( 'post_name', get_queried_object_id() );
        return isset( self::DESCRIPTIONS[ $slug ] ) ? self::DESCRIPTIONS[ $slug ] : $desc;
    }

    /* ── IndexNow : prévient Bing (et ChatGPT, qui s'appuie sur Bing) à chaque publication ── */

    public function indexnow_on_publish( $new, $old, $post ) {
        if ( 'publish' !== $new || ! in_array( $post->post_type, [ 'page', 'post', 'product' ], true ) ) return;
        if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) return;
        self::indexnow_submit( [ get_permalink( $post ) ] );
    }

    public static function indexnow_submit( array $urls ) {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        wp_remote_post( 'https://api.indexnow.org/indexnow', [
            'blocking' => false,
            'timeout'  => 5,
            'headers'  => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'     => wp_json_encode( [
                'host'        => $host,
                'key'         => self::INDEXNOW_KEY,
                'keyLocation' => home_url( '/' . self::INDEXNOW_KEY . '.txt' ),
                'urlList'     => array_values( $urls ),
            ] ),
        ] );
    }

    /* ── « Comment nous avez-vous connus ? » au paiement (bloc Commande) ── */

    public function register_source_field() {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) return;

        woocommerce_register_additional_checkout_field( [
            'id'       => self::SOURCE_FIELD,
            'label'    => 'Comment nous avez-vous connus ?',
            'location' => 'order',
            'type'     => 'select',
            'required' => false,
            'options'  => [
                [ 'value' => 'google',    'label' => 'Recherche Google' ],
                [ 'value' => 'ia',        'label' => 'ChatGPT ou une autre IA' ],
                [ 'value' => 'instagram', 'label' => 'Instagram' ],
                [ 'value' => 'vinted',    'label' => 'Vinted' ],
                [ 'value' => 'facebook',  'label' => 'Facebook' ],
                [ 'value' => 'club',      'label' => 'Bouche-à-oreille ou club' ],
                [ 'value' => 'autre',     'label' => 'Autre' ],
            ],
        ] );
    }
}

POC_Visibilite::get_instance();

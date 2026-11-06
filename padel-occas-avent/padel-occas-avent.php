<?php
/**
 * Plugin Name: Padel Occas — Prévente Calendrier de l'Avent
 * Description: Prix de lancement automatique (79 € pour les 30 premiers, puis 89 €), compteur sur 50, vente jusqu'à épuisement, point relais seul et offert, grille des 24 sachets, lien d'aperçu secret, mise en avant accueil + menu.
 * Version:     1.1.0
 * Author:      Padel Occas
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ════════════════════════════════════════════════════════════════════════════
   RÉGLAGES — à modifier ici
   ════════════════════════════════════════════════════════════════════════════ */
class POC_Avent_Config {
    const SKU            = 'CAL-AVENT-2026';
    const PAGE_SLUG      = 'calendrier-avent-padel';
    const TOTAL          = 50;                     // exemplaires
    const LANCEMENT_QTE  = 30;                     // les N premiers au prix de lancement
    const PRIX_LANCEMENT = 79;
    const PRIX           = 89;                     // affiché seulement une fois les N premiers vendus
    const EXPEDITION     = '2026-11-26';           // commandes passées avant : expédiées ce jour-là au plus tard
    const MAX_PAR_COMMANDE = 5;

    // Interrupteurs. Mettre true au moment de publier, false pour retirer.
    const MISE_EN_AVANT  = false;  // bandeau sur l'accueil + lien dans le menu principal
    const VALEUR_VALIDEE = false;  // bloc « plus de 130 € de matériel »
    const VALEUR_TEXTE   = 'Plus de 130 € de matériel'; // pour mémoire

    // Lien d'aperçu secret : /?page_id=<ID>&apercu=<jeton> (et /?apercu=<jeton> pour l'accueil)
    const APERCU = 'tfr_4MhWFUn95gNzG_2WP1DE';

    /** Contenu jour par jour : non affiché sur la page (effet surprise), gardé pour la préparation */
    const JOURS = [
        1 => 'Surgrip', 2 => 'Grip de remplacement', 3 => 'Dragonne de rechange', 4 => 'Poignet éponge',
        5 => 'Chaussettes padel', 6 => 'Deux surgrips', 7 => 'Porte-clés mini-raquette', 8 => 'Protecteur de cadre',
        9 => 'Surgrip', 10 => 'Sac à chaussures pliable', 11 => 'Bandeau', 12 => 'Deux surgrips',
        13 => 'Serviette microfibre', 14 => 'Surgrip', 15 => 'Gomme nettoyante raquette', 16 => 'Pansements anti-ampoules',
        17 => 'Poignet éponge', 18 => 'Surgrip', 19 => 'Sac à dos à cordon', 20 => 'Chaussettes padel',
        21 => 'Protecteur de cadre', 22 => 'Deux surgrips', 23 => 'Magnésie liquide',
        24 => 'Tube de 3 balles neuves + bon d’achat de 15 € sur une raquette d’occasion',
    ];
}

/* ════════════════════════════════════════════════════════════════════════════
   CLASSE PRINCIPALE
   ════════════════════════════════════════════════════════════════════════════ */
class POC_Avent {

    private static $instance = null;
    private static $product_id = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Prix, achat, panier
        add_filter( 'woocommerce_product_get_price',         [ $this, 'filter_price' ], 20, 2 );
        add_filter( 'woocommerce_product_get_regular_price', [ $this, 'filter_price' ], 20, 2 );
        add_filter( 'woocommerce_get_price_html',            [ $this, 'price_html' ], 20, 2 );
        add_action( 'woocommerce_before_calculate_totals',   [ $this, 'cart_prices' ], 20 );
        add_filter( 'woocommerce_add_to_cart_validation',    [ $this, 'validate_qty' ], 10, 3 );
        add_filter( 'woocommerce_store_api_product_quantity_maximum', [ $this, 'store_api_max' ], 10, 2 );
        add_filter( 'woocommerce_get_item_data',             [ $this, 'cart_item_data' ], 10, 2 );
        add_filter( 'woocommerce_package_rates',             [ $this, 'shipping_rates' ], 100, 2 );
        add_action( 'woocommerce_email_before_order_table',  [ $this, 'email_notice' ], 5, 4 );

        // Page, aperçu, mise en avant
        add_filter( 'posts_results', [ $this, 'preview_draft' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'preview_headers' ], 0 );
        add_filter( 'body_class',    [ $this, 'body_class' ] );
        add_action( 'wp_head',       [ $this, 'print_css' ], 30 );
        add_action( 'wp_body_open',  [ $this, 'home_banner' ] );
        add_filter( 'wp_nav_menu_items', [ $this, 'menu_item' ], 10, 2 );

        add_shortcode( 'poc_avent_hero',   [ $this, 'sc_hero' ] );
        add_shortcode( 'poc_avent_grille', [ $this, 'sc_grille' ] );
        add_shortcode( 'poc_avent_valeur', [ $this, 'sc_valeur' ] );
        add_shortcode( 'poc_avent_visuel', [ $this, 'sc_visuel' ] );
    }

    /* ── État de la prévente ──────────────────────────────────────────────── */

    public static function product_id() {
        if ( null === self::$product_id ) {
            self::$product_id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( POC_Avent_Config::SKU ) : 0;
        }
        return self::$product_id;
    }

    private static function is_calendar( $product ) {
        if ( ! $product ) return false;
        $id = $product->get_parent_id() ?: $product->get_id();
        return $id && $id === self::product_id();
    }

    /** Exemplaires réservés (payés) : stock de départ moins stock restant */
    public static function sold() {
        $p = self::product_id() ? wc_get_product( self::product_id() ) : null;
        if ( ! $p ) return 0;
        $stock = (int) $p->get_stock_quantity( 'edit' );
        return max( 0, min( POC_Avent_Config::TOTAL, POC_Avent_Config::TOTAL - $stock ) );
    }

    public static function launch_left() {
        return max( 0, POC_Avent_Config::LANCEMENT_QTE - self::sold() );
    }

    public static function unit_price() {
        return self::launch_left() > 0 ? POC_Avent_Config::PRIX_LANCEMENT : POC_Avent_Config::PRIX;
    }

    public static function is_open() {
        return self::sold() < POC_Avent_Config::TOTAL;
    }

    /** Avant le jour d'expédition groupée ? */
    public static function before_shipping_day() {
        $j = new DateTime( POC_Avent_Config::EXPEDITION . ' 00:00:00', new DateTimeZone( 'Europe/Paris' ) );
        return time() < $j->getTimestamp();
    }

    public static function shipping_text() {
        return self::before_shipping_day()
            ? 'Expédition le 26 novembre au plus tard, réception avant le 1er décembre'
            : 'Expédition sous 24 h ouvrées';
    }

    public static function is_preview() {
        return isset( $_GET['apercu'] ) && hash_equals( POC_Avent_Config::APERCU, (string) wp_unslash( $_GET['apercu'] ) );
    }

    private static function is_sales_page() {
        $o = get_queried_object();
        return $o instanceof WP_Post && POC_Avent_Config::PAGE_SLUG === $o->post_name;
    }

    private static function eur( $n ) {
        return number_format( (float) $n, ( floor( $n ) == $n ) ? 0 : 2, ',', ' ' ) . ' €';
    }

    /* ── Prix ─────────────────────────────────────────────────────────────── */

    public function filter_price( $price, $product ) {
        return self::is_calendar( $product ) ? (string) self::unit_price() : $price;
    }

    public function price_html( $html, $product ) {
        if ( ! self::is_calendar( $product ) ) return $html;
        if ( self::launch_left() > 0 ) {
            return sprintf( '%s <small>prix de lancement réservé aux %d premiers</small>', self::eur( POC_Avent_Config::PRIX_LANCEMENT ), POC_Avent_Config::LANCEMENT_QTE );
        }
        return self::eur( POC_Avent_Config::PRIX );
    }

    /** Dans le panier : les places restantes au prix de lancement, le reste à 89 € */
    public function cart_prices( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) return;
        $left = self::launch_left();
        foreach ( $cart->get_cart() as $item ) {
            if ( ! self::is_calendar( $item['data'] ) ) continue;
            $qty     = max( 1, (int) $item['quantity'] );
            $launch  = min( $qty, $left );
            $left   -= $launch;
            $total   = $launch * POC_Avent_Config::PRIX_LANCEMENT + ( $qty - $launch ) * POC_Avent_Config::PRIX;
            $item['data']->set_price( round( $total / $qty, 4 ) );
        }
    }

    public function validate_qty( $ok, $product_id, $qty ) {
        if ( (int) $product_id !== self::product_id() ) return $ok;
        $in_cart = 0;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $item ) {
                if ( (int) $item['product_id'] === self::product_id() ) $in_cart += (int) $item['quantity'];
            }
        }
        if ( $in_cart + (int) $qty > POC_Avent_Config::MAX_PAR_COMMANDE ) {
            wc_add_notice( sprintf( 'Le calendrier est limité à %d exemplaires par commande.', POC_Avent_Config::MAX_PAR_COMMANDE ), 'error' );
            return false;
        }
        return $ok;
    }

    public function store_api_max( $max, $product ) {
        return self::is_calendar( $product ) ? min( (int) $max, POC_Avent_Config::MAX_PAR_COMMANDE ) : $max;
    }

    public function cart_item_data( $data, $item ) {
        if ( self::is_calendar( $item['data'] ) ) {
            $data[] = [ 'key' => 'Livraison', 'value' => self::shipping_text() . ', en point relais (offert)' ];
        }
        return $data;
    }

    /* ── Livraison : point relais seul, offert ───────────────────────────── */

    public function shipping_rates( $rates, $package ) {
        $has = false;
        foreach ( $package['contents'] as $item ) {
            if ( self::is_calendar( $item['data'] ) ) { $has = true; break; }
        }
        if ( ! $has ) return $rates;

        $relais = array_filter( $rates, function ( $r ) { return false !== strpos( $r->get_method_id(), 'service_point' ); } );
        if ( ! $relais ) return $rates; // sécurité : on ne bloque jamais la commande

        foreach ( $relais as $r ) {
            $r->set_cost( 0 );
            $r->set_taxes( [] );
            $r->set_label( 'Point relais Mondial Relay — offert' );
        }
        return $relais;
    }

    /* ── Mails de commande ───────────────────────────────────────────────── */

    public function email_notice( $order, $sent_to_admin, $plain_text, $email = null ) {
        if ( $sent_to_admin || ! $order instanceof WC_Order ) return;
        $has = false;
        foreach ( $order->get_items() as $item ) {
            if ( (int) $item->get_product_id() === self::product_id() ) { $has = true; break; }
        }
        if ( ! $has || ! $email || ! in_array( $email->id, [ 'customer_processing_order', 'customer_on_hold_order' ], true ) ) return;

        $txt = 'Calendrier de l’Avent Padel Occas : ' . lcfirst( self::shipping_text() ) . ', en point relais Mondial Relay.';
        if ( $plain_text ) {
            echo "\n" . $txt . "\n\n";
        } else {
            echo '<div style="margin:0 0 24px;padding:14px 16px;border:1px solid #DFE4E9;border-radius:8px;background:#FFF4E5">' . esc_html( $txt ) . '</div>';
        }
    }

    /* ── Aperçu secret d'une page en brouillon ───────────────────────────── */

    public function preview_draft( $posts, $query ) {
        if ( ! self::is_preview() || ! $query->is_main_query() || 1 !== count( $posts ) ) return $posts;
        $p = $posts[0];
        if ( 'page' === $p->post_type && POC_Avent_Config::PAGE_SLUG === $p->post_name && in_array( $p->post_status, [ 'draft', 'pending', 'private' ], true ) ) {
            $p->post_status = 'publish'; // en mémoire seulement, pour cet affichage
        }
        return $posts;
    }

    public function preview_headers() {
        if ( ! self::is_preview() ) return;
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
        do_action( 'litespeed_control_set_nocache', 'aperçu calendrier' );
        header( 'X-Robots-Tag: noindex, nofollow' );
    }

    public function body_class( $c ) {
        if ( self::is_sales_page() ) $c[] = 'poc-av-page';
        return $c;
    }

    private static function show_promo() {
        return self::is_open() && ( POC_Avent_Config::MISE_EN_AVANT || self::is_preview() );
    }

    private static function page_url() {
        $page = get_page_by_path( POC_Avent_Config::PAGE_SLUG );
        if ( ! $page ) return home_url( '/' );
        if ( 'publish' !== $page->post_status ) {
            return add_query_arg( [ 'page_id' => $page->ID, 'apercu' => POC_Avent_Config::APERCU ], home_url( '/' ) );
        }
        return get_permalink( $page );
    }

    public function home_banner() {
        if ( ! is_front_page() || ! self::show_promo() ) return;
        printf(
            '<a class="poc-av-bandeau" href="%s"><span class="poc-av-bandeau__tag">Prévente</span> <span>Calendrier de l’Avent Padel Occas : 24 objets de padel, 50 exemplaires</span> <span class="poc-av-bandeau__cta">Découvrir</span></a>',
            esc_url( self::page_url() )
        );
    }

    public function menu_item( $items, $args ) {
        if ( empty( $args->theme_location ) || 'principal' !== $args->theme_location || ! self::show_promo() ) return $items;
        $li = sprintf( '<li class="menu-item poc-av-menu"><a href="%s">Calendrier de l’Avent</a></li>', esc_url( self::page_url() ) );
        return $li . $items;
    }

    /* ── Blocs de la page ────────────────────────────────────────────────── */

    private static function ball( $n, $size = 'md' ) {
        return sprintf(
            '<span class="poc-av-balle poc-av-balle--%s" aria-hidden="true"><svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="48" fill="#FC9A20"/><path d="M9 27c13 10 13 36 0 46M91 27c-13 10-13 36 0 46" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" opacity=".75"/></svg><b>%d</b></span>',
            esc_attr( $size ), (int) $n
        );
    }

    public function sc_hero() {
        $pid = self::product_id();
        $p   = $pid ? wc_get_product( $pid ) : null;
        if ( ! $p ) return '<p><em>Produit du calendrier introuvable (UGS ' . esc_html( POC_Avent_Config::SKU ) . ').</em></p>';

        $sold   = self::sold();
        $left   = self::launch_left();
        $total  = POC_Avent_Config::TOTAL;
        $pct    = round( 100 * $sold / $total );
        $img_id = $p->get_image_id();

        ob_start(); ?>
        <section class="poc-av-hero" id="reserver">
          <div class="poc-av-hero__visuel">
            <?php if ( $img_id ) : ?>
              <figure class="poc-av-fig">
                <?php echo wp_get_attachment_image( $img_id, 'large', false, [ 'loading' => 'eager', 'fetchpriority' => 'high' ] ); ?>
                <figcaption>Visuel non contractuel</figcaption>
              </figure>
            <?php endif; ?>
          </div>
          <div class="poc-av-hero__infos">
            <p class="poc-av-eyebrow">Prévente · Série limitée de <?php echo (int) $total; ?> exemplaires numérotés</p>
            <h1>Calendrier de l’Avent Padel&nbsp;Occas</h1>
            <p class="poc-av-lead">24 sachets kraft numérotés à accrocher, avec un vrai objet de padel dans chacun. Le 24 : un tube de 3 balles neuves et un bon d’achat de 15&nbsp;€ sur une raquette d’occasion.</p>

            <div class="poc-av-prix">
              <span class="poc-av-prix__montant"><?php echo esc_html( self::eur( self::unit_price() ) ); ?></span>
              <?php if ( $left > 0 ) : ?><span class="poc-av-prix__note">prix de lancement réservé aux <?php echo (int) POC_Avent_Config::LANCEMENT_QTE; ?> premiers</span><?php endif; ?>
            </div>

            <div class="poc-av-compteur">
              <div class="poc-av-compteur__ligne"><strong><?php echo (int) $sold; ?> / <?php echo (int) $total; ?></strong> réservés</div>
              <div class="poc-av-barre" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $total; ?>" aria-valuenow="<?php echo (int) $sold; ?>"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
              <?php if ( $left > 0 && self::is_open() ) : ?>
                <p class="poc-av-compteur__reste">Plus que <strong><?php echo (int) $left; ?></strong> au prix de lancement</p>
              <?php endif; ?>
            </div>

            <?php echo $this->buy_form( $p ); ?>

            <ul class="poc-av-rassure">
              <li><?php echo esc_html( self::shipping_text() ); ?></li>
              <li>Point relais Mondial Relay <strong>offert</strong></li>
              <li>Série limitée à <?php echo (int) $total; ?> exemplaires numérotés</li>
            </ul>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function buy_form( $p ) {
        if ( self::sold() >= POC_Avent_Config::TOTAL || ! $p->is_in_stock() ) {
            return '<p class="poc-av-statut">Les 50 calendriers sont réservés. Merci !</p>';
        }
        $max = min( POC_Avent_Config::MAX_PAR_COMMANDE, POC_Avent_Config::TOTAL - self::sold() );
        $opts = '';
        for ( $i = 1; $i <= $max; $i++ ) $opts .= sprintf( '<option value="%1$d">%1$d</option>', $i );

        $disabled = 'publish' !== get_post_status( $p->get_id() );
        return sprintf(
            '<form class="poc-av-achat" method="post" action="%s">'
            . '<label class="poc-av-qte"><span>Quantité</span><select name="quantity">%s</select></label>'
            . '<input type="hidden" name="add-to-cart" value="%d">'
            . '<button type="submit" class="po-btn po-btn--rouge po-btn--lg"%s>Réserver mon calendrier</button>'
            . '</form>%s',
            esc_url( wc_get_checkout_url() ),
            $opts,
            (int) $p->get_id(),
            $disabled ? ' disabled' : '',
            $disabled ? '<p class="poc-av-statut poc-av-statut--apercu">Aperçu : le bouton s’activera à la publication.</p>' : ''
        );
    }

    /** 24 balles numérotées ; seul le 24 est dévoilé (le contenu des autres reste une surprise) */
    public function sc_grille() {
        $out = '<ol class="poc-av-grille" aria-label="Les 24 sachets du calendrier">';
        for ( $n = 1; $n <= 24; $n++ ) {
            $out .= 24 === $n
                ? '<li class="poc-av-jour poc-av-jour--24">' . self::ball( 24 ) . '<span class="poc-av-jour__objet">3 balles + bon de 15&nbsp;€</span></li>'
                : sprintf( '<li class="poc-av-jour">%s<span class="screen-reader-text">Sachet %d : surprise</span></li>', self::ball( $n ), $n );
        }
        return $out . '</ol>';
    }

    /** Visuel n° N de la galerie du produit (1 = image principale) : à remplacer dans la fiche produit */
    public function sc_visuel( $atts ) {
        $a   = shortcode_atts( [ 'n' => 2, 'legende' => '' ], $atts );
        $p   = self::product_id() ? wc_get_product( self::product_id() ) : null;
        if ( ! $p ) return '';
        $ids = array_merge( [ $p->get_image_id() ], $p->get_gallery_image_ids() );
        $id  = $ids[ max( 0, (int) $a['n'] - 1 ) ] ?? 0;
        if ( ! $id ) return '';
        return '<figure class="poc-av-fig poc-av-fig--sec">' . wp_get_attachment_image( $id, 'large', false, [ 'loading' => 'lazy' ] )
            . '<figcaption>' . ( $a['legende'] ? esc_html( $a['legende'] ) . ' · ' : '' ) . 'Visuel non contractuel</figcaption></figure>';
    }

    public function sc_valeur( $atts, $content = '' ) {
        if ( POC_Avent_Config::VALEUR_VALIDEE ) {
            return '<section class="poc-av-valeur">' . do_shortcode( $content ) . '</section>';
        }
        if ( self::is_preview() ) {
            return '<section class="poc-av-valeur poc-av-valeur--avalider"><p class="poc-av-avalider">À valider : ce bloc n’apparaîtra pas sur le site tant que le chiffre n’est pas confirmé.</p>' . do_shortcode( $content ) . '</section>';
        }
        return '';
    }

    /* ── Styles (page de vente + bandeau) ────────────────────────────────── */

    public function print_css() {
        $page = self::is_sales_page();
        if ( ! $page && ! ( is_front_page() && self::show_promo() ) && ! self::show_promo() ) return;
        ?>
<style id="poc-avent">
:root{--av-nuit:#060A2A;--av-orange:#FC9A20;--av-neutre:#EEF0F3;--av-ink:#0E1226;--av-ink2:#4A5168;--av-line:#E2E6F0}
.poc-av-bandeau{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px 12px;padding:10px 16px;background:var(--av-nuit);color:#fff;font-size:14px;line-height:1.35;text-align:center;text-decoration:none}
.poc-av-bandeau:hover{color:#fff}
.poc-av-bandeau__tag{background:var(--av-orange);color:var(--av-nuit);font-weight:700;border-radius:999px;padding:2px 10px;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
.poc-av-bandeau__cta{font-weight:700;text-decoration:underline;text-underline-offset:3px}
.poc-av-menu>a{color:var(--av-orange)!important;font-weight:700}

.poc-av-page .r-titre{display:none}
.poc-av-page .po-contenu{max-width:none}
.poc-av-page .po-contenu>*+*{margin-top:0}
.poc-av{--gap:16px;color:var(--av-ink)}
.poc-av h1,.poc-av h2{font-family:"Archivo",system-ui,sans-serif;font-variation-settings:"wdth" 112;font-weight:800;letter-spacing:-.028em;color:var(--av-nuit)}
.poc-av h2{font-size:clamp(26px,6vw,40px);line-height:1.04;margin:0 0 10px}
.poc-av-sec{padding:44px 0 0}
.poc-av-sec>p.poc-av-intro{color:var(--av-ink2);font-size:16px;max-width:60ch;margin:0 0 22px}

.poc-av-hero{display:grid;gap:22px;padding-top:8px}
.poc-av-fig{margin:0}
.poc-av-fig img{display:block;width:100%;height:auto;aspect-ratio:1/1;object-fit:contain;background:var(--av-neutre);border-radius:22px}
.poc-av-fig--sec{margin-top:18px;max-width:560px}
.poc-av-fig figcaption{margin-top:6px;font-size:12px;color:#828AA3;text-align:right}
.poc-av-eyebrow{margin:0;font-family:"IBM Plex Mono",ui-monospace,monospace;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#B86A06}
.poc-av-hero h1{font-size:clamp(32px,8.5vw,52px);line-height:.98;margin:8px 0 12px}
.poc-av-lead{font-size:16px;line-height:1.55;color:var(--av-ink2);margin:0 0 18px}
.poc-av-prix{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 12px;margin:0 0 14px}
.poc-av-prix__montant{font-family:"Archivo",sans-serif;font-variation-settings:"wdth" 112;font-weight:800;font-size:44px;line-height:1;color:var(--av-nuit)}
.poc-av-prix__note{font-size:14px;color:var(--av-ink2)}
.poc-av-compteur{background:#fff;border:1px solid var(--av-line);border-radius:16px;padding:14px 16px;margin:0 0 16px}
.poc-av-compteur__ligne{font-size:15px}
.poc-av-compteur__ligne strong{font-family:"Archivo",sans-serif;font-variation-settings:"wdth" 112;font-size:20px;color:var(--av-nuit)}
.poc-av-barre{position:relative;height:10px;border-radius:999px;background:var(--av-neutre);margin:10px 0 8px;overflow:visible}
.poc-av-barre span{display:block;height:100%;border-radius:999px;background:var(--av-orange);min-width:6px}
.poc-av-barre i{position:absolute;top:-4px;bottom:-4px;width:2px;background:var(--av-nuit);opacity:.5}
.poc-av-compteur__reste{margin:0;font-size:14px;font-weight:600;color:var(--av-nuit)}
.poc-av-compteur__seuil{margin:4px 0 0;font-size:13px;color:var(--av-ink2)}
.poc-av-achat{display:flex;gap:10px;align-items:stretch;margin:0 0 6px}
.poc-av-qte{display:flex;flex-direction:column;justify-content:center;font-size:12px;color:var(--av-ink2)}
.poc-av-qte select{height:52px;min-width:64px;border:1px solid var(--av-line);border-radius:12px;padding:0 10px;font-size:16px;background:#fff}
.poc-av-achat .po-btn{flex:1;min-height:56px;font-size:16px}
.poc-av-achat .po-btn[disabled]{opacity:.55;cursor:not-allowed}
.poc-av-statut{margin:6px 0 0;font-size:14px;font-weight:600}
.poc-av-statut--apercu{color:#B86A06;font-weight:500}
.poc-av-rassure{list-style:none;padding:0!important;margin:14px 0 0;display:grid;gap:8px;font-size:14px;color:var(--av-ink2)}
.poc-av-rassure li{position:relative;padding-left:22px}
.poc-av-rassure li::before{content:"";position:absolute;left:2px;top:.45em;width:10px;height:10px;border-radius:50%;background:var(--av-orange)}

.poc-av-grille{list-style:none;padding:0!important;margin:0;display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.poc-av-grille li+li{margin-top:0}
.poc-av-jour{background:#fff;border:1px solid var(--av-line);border-radius:16px;padding:12px 6px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:6px;min-height:84px}
.poc-av-jour__objet{font-size:13px;line-height:1.3;color:var(--av-ink2)}
.poc-av-jour--24{background:var(--av-nuit);border-color:var(--av-nuit)}
.poc-av-jour--24 .poc-av-jour__objet{color:#fff;font-weight:600;font-size:11px;line-height:1.25}
.poc-av-balle{position:relative;display:inline-block;width:52px;height:52px;flex:none}
.poc-av-balle--lg{width:72px;height:72px}
.poc-av-balle svg{display:block;width:100%;height:100%}
.poc-av-balle b{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:"Archivo",sans-serif;font-variation-settings:"wdth" 112;font-weight:800;font-size:22px;color:var(--av-nuit)}
.poc-av-balle--lg b{font-size:30px}

.poc-av-valeur{margin-top:44px;background:var(--av-nuit);color:#fff;border-radius:22px;padding:26px 20px}
.poc-av-valeur h2{color:#fff}
.poc-av-valeur p{color:#C9CFE4;margin:0}
.poc-av-valeur--avalider{outline:3px dashed var(--av-orange);outline-offset:4px}
.poc-av-avalider{background:var(--av-orange);color:var(--av-nuit)!important;font-weight:700;border-radius:10px;padding:8px 12px;margin:0 0 14px!important;font-size:14px}

.poc-av-etapes{list-style:none;padding:0!important;margin:0;display:grid;gap:12px;counter-reset:e}
.poc-av-etapes li{background:#fff;border:1px solid var(--av-line);border-radius:16px;padding:16px 16px 16px 64px;position:relative;margin:0}
.poc-av-etapes li::before{counter-increment:e;content:counter(e);position:absolute;left:16px;top:16px;width:34px;height:34px;border-radius:50%;background:var(--av-nuit);color:#fff;display:flex;align-items:center;justify-content:center;font-family:"Archivo",sans-serif;font-weight:800}
.poc-av-etapes strong{display:block;font-size:16px;color:var(--av-nuit);margin-bottom:2px}
.poc-av-etapes span{font-size:14px;color:var(--av-ink2);line-height:1.5}

.poc-av-conditions{background:#fff;border:1px solid var(--av-line);border-radius:18px;padding:18px 18px 18px 36px;margin:0}
.poc-av-conditions li{font-size:15px;line-height:1.55}
.poc-av-conditions li+li{margin-top:8px}

.poc-av .po-faq{display:block;max-width:none;margin:0}
.poc-av .po-faq>p{display:none}
.poc-av .po-faq details{border:1px solid var(--av-line);border-radius:16px;background:#fff;padding:0 16px;margin:0 0 10px}
.poc-av .po-faq summary{cursor:pointer;list-style:none;padding:16px 0}
.poc-av .po-faq summary::-webkit-details-marker{display:none}
.poc-av .po-faq summary h3{display:inline;font-size:16px;margin:0}
.poc-av .po-faq details p{margin:0 0 16px;color:var(--av-ink2);font-size:15px;line-height:1.55}

.poc-av-cta-bas{margin-top:40px;text-align:center}
body.po-r .poc-av a.po-btn,body.po-r .poc-av a.po-btn:hover{color:var(--av-nuit);text-decoration:none}
.poc-av-page .po-contenu>p:empty{display:none}

@media (min-width:600px){.poc-av-grille{grid-template-columns:repeat(6,1fr)}}
@media (min-width:960px){
  .poc-av-hero{grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);gap:48px;align-items:start;padding-top:24px}
  .poc-av-hero__visuel{position:sticky;top:24px}
  .poc-av-grille{grid-template-columns:repeat(8,1fr);gap:12px}
  .poc-av-etapes{grid-template-columns:repeat(3,1fr)}
  .poc-av-sec{padding-top:64px}
  .poc-av-valeur{padding:36px 40px}
}
</style>
        <?php
    }
}

POC_Avent::get_instance();

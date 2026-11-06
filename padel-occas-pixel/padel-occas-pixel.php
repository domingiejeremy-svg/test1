<?php
/**
 * Plugin Name: Padel Occas — Bandeau cookies + pixel Meta
 * Description: Bandeau de consentement (Accepter / Refuser, conforme CNIL) et pixel Meta chargé uniquement après accord : PageView, ViewContent, AddToCart, InitiateCheckout, Purchase (avec montant).
 * Version:     1.0.0
 * Author:      Padel Occas
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ════════════════════════════════════════════════════════════════════════════
   CLASSE PRINCIPALE
   ════════════════════════════════════════════════════════════════════════════ */
class POC_Pixel {

    const PIXEL_ID   = '1589434027744405';
    const COOKIE     = 'poc_consent';          // « oui » ou « non », 6 mois
    const META_SENT  = '_poc_pixel_purchase';  // achat déjà envoyé (pas de doublon au rechargement)

    private static $instance = null;
    private $events = [];

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_add_to_cart', [ $this, 'remember_add_to_cart' ], 10, 4 );
        add_action( 'wp', [ $this, 'collect_events' ] );
        add_action( 'wp_footer', [ $this, 'print_banner_and_pixel' ], 99 );
        add_filter( 'wp_nav_menu_items', [ $this, 'footer_link' ], 10, 2 );
    }

    /* ── Événements ───────────────────────────────────────────────────────── */

    /** L'ajout au panier se fait souvent juste avant une redirection : on le garde en session */
    public function remember_add_to_cart( $key, $product_id, $qty, $variation_id ) {
        if ( ! WC()->session ) return;
        $p = wc_get_product( $variation_id ?: $product_id );
        if ( ! $p ) return;
        $list   = (array) WC()->session->get( 'poc_pixel_atc', [] );
        $list[] = [ 'content_ids' => [ (string) $p->get_id() ], 'content_name' => $p->get_name(), 'content_type' => 'product',
                    'value' => round( (float) $p->get_price() * $qty, 2 ), 'currency' => get_woocommerce_currency() ];
        WC()->session->set( 'poc_pixel_atc', $list );
    }

    private function add( $name, $data = [], $event_id = '' ) {
        $this->events[] = [ 'name' => $name, 'data' => (object) $data, 'id' => $event_id ];
    }

    public function collect_events() {
        if ( is_admin() || ! function_exists( 'WC' ) ) return;

        // Ajouts au panier mémorisés
        if ( WC()->session ) {
            foreach ( (array) WC()->session->get( 'poc_pixel_atc', [] ) as $d ) $this->add( 'AddToCart', $d );
            WC()->session->set( 'poc_pixel_atc', [] );
        }

        // Fiche produit
        if ( is_product() ) {
            $p = wc_get_product( get_queried_object_id() );
            if ( $p ) $this->add( 'ViewContent', $this->product_data( $p ) );
        }

        // Page de vente du calendrier
        if ( is_page( 'calendrier-avent-padel' ) && class_exists( 'POC_Avent' ) && POC_Avent::product_id() ) {
            $p = wc_get_product( POC_Avent::product_id() );
            if ( $p ) $this->add( 'ViewContent', $this->product_data( $p ) );
        }

        // Achat (page de remerciement)
        if ( is_order_received_page() ) {
            $order_id = absint( get_query_var( 'order-received' ) );
            $order    = $order_id ? wc_get_order( $order_id ) : null;
            $key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
            if ( $order && hash_equals( $order->get_order_key(), $key ) && ! $order->get_meta( self::META_SENT ) && ! $order->has_status( 'failed' ) ) {
                $ids = [];
                $n   = 0;
                foreach ( $order->get_items() as $item ) { $ids[] = (string) ( $item->get_variation_id() ?: $item->get_product_id() ); $n += $item->get_quantity(); }
                $this->add( 'Purchase', [ 'content_ids' => $ids, 'content_type' => 'product', 'num_items' => $n,
                                          'value' => (float) $order->get_total(), 'currency' => $order->get_currency() ], 'order-' . $order->get_id() );
                $this->purchase_order = $order;
            }
        } elseif ( is_checkout() ) {
            $cart = WC()->cart;
            if ( $cart && ! $cart->is_empty() ) {
                $ids = [];
                foreach ( $cart->get_cart() as $it ) $ids[] = (string) ( $it['variation_id'] ?: $it['product_id'] );
                $this->add( 'InitiateCheckout', [ 'content_ids' => $ids, 'content_type' => 'product', 'num_items' => $cart->get_cart_contents_count(),
                                                  'value' => (float) $cart->get_total( 'edit' ), 'currency' => get_woocommerce_currency() ] );
            }
        }
    }

    private $purchase_order = null;

    private function product_data( $p ) {
        return [ 'content_ids' => [ (string) $p->get_id() ], 'content_name' => $p->get_name(), 'content_type' => 'product',
                 'value' => (float) $p->get_price(), 'currency' => get_woocommerce_currency() ];
    }

    /* ── Lien « Gérer les cookies » dans le pied de page (menu Aide) ─────── */

    public function footer_link( $items, $args ) {
        if ( empty( $args->theme_location ) || 'pied_aide' !== $args->theme_location ) return $items;
        return $items . '<li class="menu-item"><a href="#cookies" class="poc-cookies-ouvrir">Gérer les cookies</a></li>';
    }

    /* ── Bandeau + pixel ─────────────────────────────────────────────────── */

    public function print_banner_and_pixel() {
        if ( is_admin() ) return;
        // L'achat est marqué comme envoyé une fois la page servie (le pixel l'enverra si le visiteur a accepté)
        if ( $this->purchase_order ) {
            $this->purchase_order->update_meta_data( self::META_SENT, current_time( 'mysql' ) );
            $this->purchase_order->save_meta_data();
        }
        $cfg = [ 'pixel' => self::PIXEL_ID, 'cookie' => self::COOKIE, 'events' => $this->events ];
        ?>
<div id="poc-cookies" class="poc-cookies" role="dialog" aria-live="polite" aria-label="Cookies" hidden>
  <p>Nous utilisons des cookies pour mesurer l’efficacité de nos publicités (Meta). Ils ne sont déposés qu’avec votre accord. <a href="<?php echo esc_url( get_privacy_policy_url() ?: home_url( '/politique-de-confidentialite/' ) ); ?>">En savoir plus</a></p>
  <div class="poc-cookies__btns">
    <button type="button" data-choix="non">Refuser</button>
    <button type="button" data-choix="oui">Accepter</button>
  </div>
</div>
<style>
.poc-cookies{position:fixed;left:12px;right:12px;bottom:12px;z-index:100;max-width:560px;margin:0 auto;background:#060A2A;color:#EDF0F6;border-radius:16px;padding:16px 18px;box-shadow:0 10px 40px rgba(6,10,42,.35);font-size:14px;line-height:1.5}
.poc-cookies[hidden]{display:none}
.poc-cookies p{margin:0 0 12px}
.poc-cookies a{color:#8AA4FF;text-decoration:underline}
.poc-cookies__btns{display:flex;gap:10px}
.poc-cookies__btns button{flex:1;min-height:44px;border-radius:999px;border:1.5px solid #EDF0F6;background:transparent;color:#fff;font-weight:650;font-size:15px;cursor:pointer}
.poc-cookies__btns button[data-choix="oui"]{background:#fff;color:#060A2A;border-color:#fff}
</style>
<script>
(function(){
  var C=<?php echo wp_json_encode( $cfg ); ?>, box=document.getElementById('poc-cookies'), started=false;
  function get(){var m=document.cookie.match(new RegExp('(?:^|; )'+C.cookie+'=([^;]*)'));return m?m[1]:'';}
  function set(v){document.cookie=C.cookie+'='+v+'; Max-Age='+(60*60*24*182)+'; Path=/; SameSite=Lax; Secure';}
  function pixel(){
    if(started)return;started=true;
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init',C.pixel);fbq('track','PageView');
    C.events.forEach(function(e){e.id?fbq('track',e.name,e.data,{eventID:e.id}):fbq('track',e.name,e.data);});
  }
  var v=get();
  if(v==='oui')pixel(); else if(v!=='non')box.hidden=false;
  function purge(){['_fbp','_fbc'].forEach(function(n){var h=location.hostname.replace(/^www\./,'');document.cookie=n+'=; Max-Age=0; Path=/';document.cookie=n+'=; Max-Age=0; Path=/; Domain=.'+h;});}
  if(v==='non')purge();
  box.addEventListener('click',function(ev){var c=ev.target.getAttribute('data-choix');if(!c)return;set(c);box.hidden=true;if(c==='oui')pixel();else purge();});
  document.addEventListener('click',function(ev){var a=ev.target.closest&&ev.target.closest('.poc-cookies-ouvrir');if(!a)return;ev.preventDefault();box.hidden=false;});
})();
</script>
        <?php
    }
}

POC_Pixel::get_instance();

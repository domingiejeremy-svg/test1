<?php
/**
 * Sidebar : stats + QR Code + exports.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$analytics = Wheel_Game_Plugin::get_instance()->analytics;
$stats     = $analytics->campaign_stats( $post->ID );
$url       = get_permalink( $post->ID );
$url_enc   = rawurlencode( $url );
$slug      = $post->post_name ?: 'campagne';
$export_nonce = wp_create_nonce( 'wheel_admin' );

$qr_base    = 'https://api.qrserver.com/v1/create-qr-code/';
$qr_img     = $qr_base . '?size=200x200&margin=8&data=' . $url_enc;
$qr_png_sm  = $qr_base . '?size=600x600&format=png&margin=12&data=' . $url_enc;
$qr_png_md  = $qr_base . '?size=1200x1200&format=png&margin=20&data=' . $url_enc;
$qr_png_lg  = $qr_base . '?size=2000x2000&format=png&margin=30&data=' . $url_enc;
$qr_svg     = $qr_base . '?format=svg&margin=10&data=' . $url_enc;
?>
<div class="wg-sidebar-stats">
  <div class="stat-block"><div class="stat-num"><?php echo (int) $stats['plays']; ?></div><div class="stat-label"><?php esc_html_e( 'Tirages', 'wheel-game' ); ?></div></div>
  <div class="stat-block"><div class="stat-num" style="color:#00b894"><?php echo (int) $stats['leads']; ?></div><div class="stat-label"><?php esc_html_e( 'Leads', 'wheel-game' ); ?></div></div>
  <div class="stat-block"><div class="stat-num" style="color:#6c5ce7"><?php echo (int) $stats['clicks_google']; ?></div><div class="stat-label"><?php esc_html_e( 'Clics', 'wheel-game' ); ?></div></div>
</div>

<div class="wg-conversion">
  <span><?php esc_html_e( 'Taux conversion avis :', 'wheel-game' ); ?></span>
  <strong><?php echo esc_html( $stats['click_rate'] ); ?>%</strong>
</div>

<?php $dist = $analytics->prize_distribution( $post->ID ); ?>
<?php if ( $dist ) : ?>
<hr>
<p class="wg-side-label"><?php esc_html_e( 'Distribution', 'wheel-game' ); ?></p>
<?php foreach ( $dist as $row ) : ?>
  <div class="wg-dist-row"><span><?php echo esc_html( $row->prize_label ); ?></span><strong><?php echo (int) $row->cnt; ?></strong></div>
<?php endforeach; ?>
<?php endif; ?>

<hr>
<p class="wg-side-label"><?php esc_html_e( 'URL publique', 'wheel-game' ); ?></p>
<input type="text" value="<?php echo esc_attr( $url ); ?>" readonly class="wg-url-field">
<button type="button" class="button button-small wg-copy-btn" data-url="<?php echo esc_attr( $url ); ?>" style="width:100%;margin-bottom:12px">📋 <?php esc_html_e( 'Copier URL', 'wheel-game' ); ?></button>

<p class="wg-side-label"><?php esc_html_e( 'QR Code', 'wheel-game' ); ?></p>
<div style="text-align:center;margin-bottom:8px">
  <img src="<?php echo esc_url( $qr_img ); ?>" alt="QR" style="width:180px;height:180px;background:#fff;padding:8px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.15)">
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
  <a class="button button-small" href="<?php echo esc_url( $qr_png_sm ); ?>" download="qr-<?php echo esc_attr( $slug ); ?>-600.png">PNG 600</a>
  <a class="button button-small" href="<?php echo esc_url( $qr_png_md ); ?>" download="qr-<?php echo esc_attr( $slug ); ?>-1200.png">PNG 1200</a>
  <a class="button button-small" href="<?php echo esc_url( $qr_png_lg ); ?>" download="qr-<?php echo esc_attr( $slug ); ?>-2000.png">PNG 2000 (vinyle)</a>
  <a class="button button-small" href="<?php echo esc_url( $qr_svg ); ?>" download="qr-<?php echo esc_attr( $slug ); ?>.svg">🎨 SVG (vectoriel)</a>
</div>

<hr>
<p class="wg-side-label"><?php esc_html_e( 'Exports CSV', 'wheel-game' ); ?></p>
<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'wheel_export_leads', 'campaign_id' => $post->ID, 'nonce' => $export_nonce ], admin_url( 'admin-ajax.php' ) ) ); ?>"
   class="button button-small" style="width:100%;text-align:center;margin-bottom:4px">👤 <?php esc_html_e( 'Leads', 'wheel-game' ); ?></a>
<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'wheel_export_plays', 'campaign_id' => $post->ID, 'nonce' => $export_nonce ], admin_url( 'admin-ajax.php' ) ) ); ?>"
   class="button button-small" style="width:100%;text-align:center">🎡 <?php esc_html_e( 'Tirages', 'wheel-game' ); ?></a>

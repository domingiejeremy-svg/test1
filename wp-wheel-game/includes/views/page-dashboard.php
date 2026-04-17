<?php
/**
 * Dashboard ROI global.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

global $wpdb;
$p = $wpdb->prefix;

$total_plays     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wheel_plays" );
$total_leads     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wheel_leads" );
$total_clicks    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wheel_plays WHERE clicked_google = 1" );
$total_campaigns = wp_count_posts( Wheel_Game_Cpt::POST_TYPE )->publish ?? 0;

$campaigns = get_posts( [
    'post_type'   => Wheel_Game_Cpt::POST_TYPE,
    'post_status' => 'publish',
    'numberposts' => -1,
] );
$analytics = Wheel_Game_Plugin::get_instance()->analytics;
?>
<div class="wrap wheel-dashboard">
  <h1>📊 <?php esc_html_e( 'Dashboard ROI — Roue des cadeaux', 'wheel-game' ); ?></h1>

  <div class="wg-kpi-grid">
    <div class="wg-kpi"><div class="val"><?php echo (int) $total_campaigns; ?></div><div class="lbl"><?php esc_html_e( 'Campagnes actives', 'wheel-game' ); ?></div></div>
    <div class="wg-kpi"><div class="val"><?php echo (int) $total_plays; ?></div><div class="lbl"><?php esc_html_e( 'Tirages totaux', 'wheel-game' ); ?></div></div>
    <div class="wg-kpi" style="border-left:4px solid #00b894"><div class="val"><?php echo (int) $total_leads; ?></div><div class="lbl"><?php esc_html_e( 'Leads capturés', 'wheel-game' ); ?></div></div>
    <div class="wg-kpi" style="border-left:4px solid #6c5ce7"><div class="val"><?php echo (int) $total_clicks; ?></div><div class="lbl"><?php esc_html_e( 'Clics Google', 'wheel-game' ); ?></div></div>
    <div class="wg-kpi"><div class="val"><?php echo $total_plays > 0 ? round( $total_clicks / $total_plays * 100, 1 ) : 0; ?>%</div><div class="lbl"><?php esc_html_e( 'Taux conversion avis', 'wheel-game' ); ?></div></div>
  </div>

  <h2 style="margin-top:30px"><?php esc_html_e( 'Performance par campagne', 'wheel-game' ); ?></h2>

  <?php if ( empty( $campaigns ) ) : ?>
    <p><?php esc_html_e( 'Aucune campagne publiée pour le moment.', 'wheel-game' ); ?></p>
  <?php else : ?>
  <table class="wp-list-table widefat striped">
    <thead>
      <tr>
        <th><?php esc_html_e( 'Campagne', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Tirages', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Leads', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Taux lead', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Clics Google', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Taux conv.', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Note Google', 'wheel-game' ); ?></th>
        <th><?php esc_html_e( 'Avis gagnés', 'wheel-game' ); ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ( $campaigns as $camp ) :
      $s = $analytics->campaign_stats( $camp->ID ); ?>
    <tr>
      <td><strong><a href="<?php echo esc_url( get_edit_post_link( $camp->ID ) ); ?>"><?php echo esc_html( $camp->post_title ); ?></a></strong></td>
      <td><?php echo (int) $s['plays']; ?></td>
      <td style="color:#00b894;font-weight:700"><?php echo (int) $s['leads']; ?></td>
      <td><?php echo esc_html( $s['lead_rate'] ); ?>%</td>
      <td style="color:#6c5ce7;font-weight:700"><?php echo (int) $s['clicks_google']; ?></td>
      <td><?php echo esc_html( $s['click_rate'] ); ?>%</td>
      <td>
        <?php if ( $s['rating_last'] !== null ) : ?>
          <?php echo number_format( $s['rating_last'], 1 ); ?> ⭐
          <?php if ( abs( $s['rating_delta'] ) >= 0.01 ) : ?>
            <span style="color:<?php echo $s['rating_delta'] > 0 ? '#00b894' : '#e74c3c'; ?>;font-size:11px">
              (<?php echo $s['rating_delta'] > 0 ? '+' : ''; ?><?php echo number_format( $s['rating_delta'], 2 ); ?>)
            </span>
          <?php endif; ?>
        <?php else : ?>—<?php endif; ?>
      </td>
      <td style="color:#00b894;font-weight:700">
        <?php echo $s['reviews_gained'] > 0 ? '+' . (int) $s['reviews_gained'] : ( $s['reviews_gained'] < 0 ? (int) $s['reviews_gained'] : '—' ); ?>
      </td>
      <td>
        <a href="<?php echo esc_url( get_edit_post_link( $camp->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Éditer', 'wheel-game' ); ?></a>
        <a href="<?php echo esc_url( get_permalink( $camp->ID ) ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'Voir', 'wheel-game' ); ?></a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

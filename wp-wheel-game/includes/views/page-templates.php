<?php
/**
 * Galerie de templates préfaits.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;
?>
<div class="wrap">
  <h1>📋 <?php esc_html_e( 'Templates de roues préfaites', 'wheel-game' ); ?></h1>
  <p><?php esc_html_e( 'Créez une nouvelle campagne puis appliquez un template depuis l\'onglet « Prix & probabilités ».', 'wheel-game' ); ?></p>

  <div class="wg-tpl-grid">
    <?php foreach ( Wheel_Game_Templates_Library::all() as $slug => $tpl ) : ?>
      <div class="wg-tpl-card">
        <div class="wg-tpl-head">
          <span class="wg-tpl-emoji"><?php echo esc_html( $tpl['emoji'] ); ?></span>
          <h3><?php echo esc_html( $tpl['name'] ); ?></h3>
        </div>
        <p class="wg-tpl-title">"<?php echo esc_html( $tpl['title'] ); ?>"</p>
        <ul class="wg-tpl-prizes">
          <?php foreach ( $tpl['prizes'] as $p ) : ?>
            <li>
              <span class="wg-tpl-color" style="background:<?php echo esc_attr( $p['color'] ); ?>"></span>
              <?php echo esc_html( $p['emoji'] ); ?>
              <strong><?php echo esc_html( $p['line1'] ); ?></strong>
              <?php if ( ! empty( $p['line2'] ) ) : ?> <?php echo esc_html( $p['line2'] ); ?><?php endif; ?>
              <em>— <?php echo esc_html( $p['percent'] ); ?>%</em>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</div>

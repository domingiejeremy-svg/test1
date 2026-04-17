<?php
/**
 * Meta box : clé API Google + suivi avis.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$gtable  = $wpdb->prefix . 'wheel_google_stats';
$api_key = get_option( 'wheel_game_google_api_key', '' );

$latest  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$gtable} WHERE campaign_id = %d ORDER BY recorded_at DESC LIMIT 1", $post->ID ) );
$first   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$gtable} WHERE campaign_id = %d ORDER BY recorded_at ASC LIMIT 1",  $post->ID ) );
$history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$gtable} WHERE campaign_id = %d ORDER BY recorded_at DESC", $post->ID ) );
$nonce   = wp_create_nonce( 'wheel_google' );
?>
<p class="wg-side-label"><?php esc_html_e( 'Clé API Google Places', 'wheel-game' ); ?></p>
<input type="password" name="google_api_key" id="wg-api-key"
       value="<?php echo esc_attr( $api_key ); ?>"
       class="wg-url-field" placeholder="AIzaSy..." autocomplete="off">
<label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#666;margin-top:3px;margin-bottom:10px">
  <input type="checkbox" onchange="document.getElementById('wg-api-key').type = this.checked ? 'text' : 'password'">
  <?php esc_html_e( 'Afficher la clé', 'wheel-game' ); ?>
</label>

<?php if ( $latest ) : ?>
<hr>
<p class="wg-side-label"><?php esc_html_e( 'Dernière mesure', 'wheel-game' ); ?> — <span style="font-weight:400"><?php echo esc_html( $latest->recorded_at ); ?></span></p>
<div style="display:flex;gap:8px;margin-bottom:8px;text-align:center">
  <div style="flex:1;background:#f8f9fb;border-radius:8px;padding:8px 4px">
    <div style="font-size:20px;font-weight:800;color:#6c5ce7"><?php echo number_format( (float) $latest->rating, 1 ); ?> ⭐</div>
    <div style="font-size:10px;color:#999;margin-top:2px"><?php esc_html_e( 'Note moy.', 'wheel-game' ); ?></div>
  </div>
  <div style="flex:1;background:#f8f9fb;border-radius:8px;padding:8px 4px">
    <div style="font-size:20px;font-weight:800;color:#6c5ce7"><?php echo number_format( (int) $latest->review_count ); ?></div>
    <div style="font-size:10px;color:#999;margin-top:2px"><?php esc_html_e( 'Avis totaux', 'wheel-game' ); ?></div>
  </div>
  <?php if ( $first && $first->id !== $latest->id ) : $gain = (int) $latest->review_count - (int) $first->review_count; ?>
  <div style="flex:1;background:#f0fdf8;border:1px solid #d1fae5;border-radius:8px;padding:8px 4px">
    <div style="font-size:20px;font-weight:800;color:#00b894">+<?php echo (int) $gain; ?></div>
    <div style="font-size:10px;color:#999;margin-top:2px"><?php esc_html_e( 'Depuis début', 'wheel-game' ); ?></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ( $history ) : ?>
<hr>
<div style="display:flex;gap:0;margin-bottom:8px;border:1px solid #ddd;border-radius:6px;overflow:hidden">
  <?php foreach ( [ '7j' => __( '7 jours', 'wheel-game' ), '30j' => __( '30 jours', 'wheel-game' ), 'all' => __( 'Tout', 'wheel-game' ) ] as $tab => $label ) : ?>
    <button type="button" class="wg-hist-tab" data-tab="<?php echo esc_attr( $tab ); ?>"
            style="flex:1;padding:5px 0;font-size:11px;font-weight:600;border:none;cursor:pointer;background:<?php echo $tab === '7j' ? '#6c5ce7' : '#f8f9fb'; ?>;color:<?php echo $tab === '7j' ? '#fff' : '#555'; ?>"><?php echo esc_html( $label ); ?></button>
  <?php endforeach; ?>
</div>
<div style="max-height:220px;overflow-y:auto">
<table style="width:100%;font-size:11px;border-collapse:collapse">
  <thead><tr style="color:#aaa;border-bottom:1px solid #eee">
    <th style="text-align:left;padding:3px 0"><?php esc_html_e( 'Date', 'wheel-game' ); ?></th>
    <th style="text-align:center;padding:3px 0">⭐</th>
    <th style="text-align:right;padding:3px 0"><?php esc_html_e( 'Avis', 'wheel-game' ); ?></th>
    <th style="text-align:right;padding:3px 4px">Δ</th>
  </tr></thead>
  <tbody id="wg-history-body">
  <?php foreach ( $history as $k => $row ) :
    $next  = $history[ $k + 1 ] ?? null;
    $delta = $next ? ( (int) $row->review_count - (int) $next->review_count ) : null;
    $dc    = $delta > 0 ? '#00b894' : ( $delta < 0 ? '#e74c3c' : '#bbb' ); ?>
  <tr data-date="<?php echo esc_attr( $row->recorded_at ); ?>" style="border-bottom:1px solid #f5f5f5">
    <td style="padding:4px 0;color:#555"><?php echo esc_html( $row->recorded_at ); ?></td>
    <td style="text-align:center;padding:4px 0"><?php echo number_format( (float) $row->rating, 1 ); ?></td>
    <td style="text-align:right;padding:4px 0;font-weight:700"><?php echo (int) $row->review_count; ?></td>
    <td style="text-align:right;padding:4px 4px;color:<?php echo esc_attr( $dc ); ?>">
      <?php echo $delta !== null ? ( $delta > 0 ? '+' . (int) $delta : ( $delta === 0 ? '—' : (int) $delta ) ) : '—'; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<hr>
<button type="button" class="button button-primary button-small" style="width:100%"
        id="wg-fetch-btn" data-campaign="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
  🔄 <?php esc_html_e( 'Actualiser maintenant', 'wheel-game' ); ?>
</button>
<div id="wg-fetch-msg" style="font-size:11px;margin-top:6px;text-align:center;min-height:16px"></div>

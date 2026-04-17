<?php
/**
 * Réglages globaux.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

if ( isset( $_POST['wheel_settings_nonce'] ) && wp_verify_nonce( $_POST['wheel_settings_nonce'], 'wheel_settings' ) ) {
    if ( isset( $_POST['google_api_key'] ) ) {
        update_option( 'wheel_game_google_api_key', sanitize_text_field( wp_unslash( $_POST['google_api_key'] ) ) );
    }
    echo '<div class="notice notice-success"><p>' . esc_html__( 'Réglages enregistrés.', 'wheel-game' ) . '</p></div>';
}

$api_key   = get_option( 'wheel_game_google_api_key', '' );
$next_cron = wp_next_scheduled( 'wheel_daily_google_fetch' );

global $wpdb;
$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wheel_cron_log ORDER BY created_at DESC LIMIT 30" );
?>
<div class="wrap">
  <h1>⚙️ <?php esc_html_e( 'Réglages — Wheel Game', 'wheel-game' ); ?></h1>

  <form method="post">
    <?php wp_nonce_field( 'wheel_settings', 'wheel_settings_nonce' ); ?>
    <h2><?php esc_html_e( 'Clé API Google Places', 'wheel-game' ); ?></h2>
    <table class="form-table">
      <tr>
        <th><label for="google_api_key"><?php esc_html_e( 'Clé API', 'wheel-game' ); ?></label></th>
        <td>
          <input type="password" id="google_api_key" name="google_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off">
          <label style="margin-left:10px;font-size:12px">
            <input type="checkbox" onchange="document.getElementById('google_api_key').type = this.checked ? 'text' : 'password'">
            <?php esc_html_e( 'Afficher', 'wheel-game' ); ?>
          </label>
          <p class="description">
            <?php esc_html_e( 'Activez Places API sur Google Cloud Console.', 'wheel-game' ); ?>
            <a href="https://console.cloud.google.com/apis/library/places-backend.googleapis.com" target="_blank">→</a>
          </p>
        </td>
      </tr>
    </table>
    <?php submit_button(); ?>
  </form>

  <h2 style="margin-top:30px"><?php esc_html_e( 'Tâche planifiée quotidienne', 'wheel-game' ); ?></h2>
  <p>
    <?php esc_html_e( 'Prochaine exécution :', 'wheel-game' ); ?>
    <strong><?php echo $next_cron ? esc_html( wp_date( 'Y-m-d H:i', $next_cron ) ) : '<span style="color:#e74c3c">' . esc_html__( 'Non planifiée', 'wheel-game' ) . '</span>'; ?></strong>
  </p>

  <h2><?php esc_html_e( 'Journal cron (30 derniers)', 'wheel-game' ); ?></h2>
  <?php if ( empty( $logs ) ) : ?>
    <p><?php esc_html_e( 'Aucun log.', 'wheel-game' ); ?></p>
  <?php else : ?>
  <table class="wp-list-table widefat striped">
    <thead><tr>
      <th><?php esc_html_e( 'Date', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Action', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Statut', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Campagne', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Message', 'wheel-game' ); ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ( $logs as $log ) :
      $color = '#6c5ce7';
      if ( $log->status === 'done' )     $color = '#00b894';
      elseif ( $log->status === 'error' )  $color = '#e74c3c';
      elseif ( $log->status === 'skipped' ) $color = '#999';
    ?>
    <tr>
      <td><?php echo esc_html( $log->created_at ); ?></td>
      <td><?php echo esc_html( $log->action ); ?></td>
      <td><span style="color:<?php echo esc_attr( $color ); ?>;font-weight:700"><?php echo esc_html( $log->status ); ?></span></td>
      <td><?php echo $log->campaign_id ? esc_html( get_the_title( $log->campaign_id ) ?: '#' . $log->campaign_id ) : '—'; ?></td>
      <td style="font-family:monospace;font-size:11px"><?php echo esc_html( $log->message ); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

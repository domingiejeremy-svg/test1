<?php
/**
 * Page admin : Journal d'emails envoyés.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

// Resend
if ( isset( $_POST['resend_id'] ) && check_admin_referer( 'wheel_mail_resend' ) ) {
    $id = absint( $_POST['resend_id'] );
    $ok = Wheel_Game_Mail::resend( $id );
    echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>'
        . ( $ok ? esc_html__( 'Email renvoyé.', 'wheel-game' ) : esc_html__( 'Échec du renvoi.', 'wheel-game' ) )
        . '</p></div>';
}

$filters = [
    'type'      => sanitize_key( $_GET['type'] ?? '' ),
    'status'    => sanitize_key( $_GET['status'] ?? '' ),
    'recipient' => sanitize_text_field( $_GET['recipient'] ?? '' ),
];
$view_id = absint( $_GET['view'] ?? 0 );
$logs    = Wheel_Game_Mail::get_log( $filters, 100 );
$types   = Wheel_Game_Mail::types();
?>
<div class="wrap">
  <h1>📬 <?php esc_html_e( 'Journal des emails', 'wheel-game' ); ?></h1>
  <p><?php esc_html_e( 'Historique des emails envoyés par le plugin (90 jours glissants). Cliquez sur "Voir" pour consulter le contenu exact envoyé.', 'wheel-game' ); ?></p>

  <form method="get" style="background:#fff;padding:14px;border:1px solid #e5e7eb;border-radius:8px;margin:14px 0;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="post_type" value="<?php echo esc_attr( Wheel_Game_Cpt::POST_TYPE ); ?>">
    <input type="hidden" name="page" value="wheel-game-mail-log">
    <label><?php esc_html_e( 'Type :', 'wheel-game' ); ?><br>
      <select name="type">
        <option value=""><?php esc_html_e( 'Tous', 'wheel-game' ); ?></option>
        <?php foreach ( $types as $slug => $def ) : ?>
          <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['type'], $slug ); ?>>
            <?php echo esc_html( $def['icon'] . ' ' . $def['label'] ); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?php esc_html_e( 'Statut :', 'wheel-game' ); ?><br>
      <select name="status">
        <option value=""><?php esc_html_e( 'Tous', 'wheel-game' ); ?></option>
        <option value="sent"   <?php selected( $filters['status'], 'sent' ); ?>>✓ Envoyé</option>
        <option value="failed" <?php selected( $filters['status'], 'failed' ); ?>>✗ Échec</option>
      </select>
    </label>
    <label><?php esc_html_e( 'Destinataire :', 'wheel-game' ); ?><br>
      <input type="text" name="recipient" value="<?php echo esc_attr( $filters['recipient'] ); ?>" placeholder="email@...">
    </label>
    <div>
      <?php submit_button( __( 'Filtrer', 'wheel-game' ), 'secondary', 'submit', false ); ?>
      <a href="<?php echo esc_url( remove_query_arg( [ 'type', 'status', 'recipient', 'view' ] ) ); ?>" class="button"><?php esc_html_e( 'Réinitialiser', 'wheel-game' ); ?></a>
    </div>
  </form>

  <?php if ( $view_id ) :
    $entry = Wheel_Game_Mail::get_log_entry( $view_id );
    if ( $entry ) : ?>
    <div style="background:#fff;padding:20px 24px;border:1px solid #e5e7eb;border-radius:8px;margin:14px 0">
      <h2 style="margin-top:0">📧 <?php echo esc_html( $entry->subject ); ?></h2>
      <p style="color:#6b7280">
        <strong><?php esc_html_e( 'À :', 'wheel-game' ); ?></strong> <?php echo esc_html( $entry->recipient ); ?> ·
        <strong><?php esc_html_e( 'Envoyé :', 'wheel-game' ); ?></strong> <?php echo esc_html( $entry->created_at ); ?> ·
        <strong><?php esc_html_e( 'Statut :', 'wheel-game' ); ?></strong>
        <?php if ( $entry->status === 'sent' ) : ?>
          <span style="color:#059669;font-weight:700">✓ Envoyé</span>
        <?php else : ?>
          <span style="color:#b91c1c;font-weight:700">✗ Échec</span>
        <?php endif; ?>
        · <strong><?php esc_html_e( 'Type :', 'wheel-game' ); ?></strong>
        <code><?php echo esc_html( $entry->type ); ?></code>
      </p>
      <pre style="background:#f9fafb;padding:16px;border-radius:6px;white-space:pre-wrap;font-family:inherit;line-height:1.5;font-size:14px"><?php echo esc_html( $entry->body ); ?></pre>
      <form method="post" style="display:inline">
        <?php wp_nonce_field( 'wheel_mail_resend' ); ?>
        <input type="hidden" name="resend_id" value="<?php echo esc_attr( $entry->id ); ?>">
        <button class="button button-primary" onclick="return confirm('Renvoyer cet email à <?php echo esc_js( $entry->recipient ); ?> ?')">📤 <?php esc_html_e( 'Renvoyer', 'wheel-game' ); ?></button>
      </form>
      <a href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>" class="button"><?php esc_html_e( 'Retour à la liste', 'wheel-game' ); ?></a>
    </div>
  <?php endif; endif; ?>

  <?php if ( empty( $logs ) ) : ?>
    <p><?php esc_html_e( 'Aucun email dans le journal.', 'wheel-game' ); ?></p>
  <?php else : ?>
  <table class="wp-list-table widefat striped">
    <thead><tr>
      <th><?php esc_html_e( 'Date', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Type', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Destinataire', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Sujet', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Statut', 'wheel-game' ); ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ( $logs as $log ) :
      $type_def = $types[ str_replace( '_resent', '', $log->type ) ] ?? null; ?>
    <tr>
      <td><?php echo esc_html( $log->created_at ); ?></td>
      <td>
        <?php if ( $type_def ) : ?>
          <?php echo esc_html( $type_def['icon'] . ' ' . $type_def['label'] ); ?>
          <?php if ( strpos( $log->type, '_resent' ) !== false ) echo ' <em style="color:#6b7280">(renvoi)</em>'; ?>
        <?php else : ?>
          <code><?php echo esc_html( $log->type ); ?></code>
        <?php endif; ?>
      </td>
      <td><code><?php echo esc_html( $log->recipient ); ?></code></td>
      <td><?php echo esc_html( mb_strimwidth( $log->subject, 0, 60, '…' ) ); ?></td>
      <td>
        <?php if ( $log->status === 'sent' ) : ?>
          <span style="color:#059669;font-weight:700">✓</span>
        <?php else : ?>
          <span style="color:#b91c1c;font-weight:700">✗</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="<?php echo esc_url( add_query_arg( 'view', $log->id ) ); ?>" class="button button-small">
          <?php esc_html_e( 'Voir', 'wheel-game' ); ?>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

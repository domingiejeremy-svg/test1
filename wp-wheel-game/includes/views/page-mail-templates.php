<?php
/**
 * Page admin : Éditeur des templates d'emails.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$types = Wheel_Game_Mail::types();
$current_type = sanitize_key( $_GET['type'] ?? '' );
if ( ! $current_type || ! isset( $types[ $current_type ] ) ) {
    $current_type = array_key_first( $types );
}

// Save
if ( isset( $_POST['wheel_mail_save'] ) && check_admin_referer( 'wheel_mail_template' ) ) {
    $subject = wp_unslash( $_POST['subject'] ?? '' );
    $body    = wp_unslash( $_POST['body'] ?? '' );
    Wheel_Game_Mail::save_template( $current_type, $subject, $body );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template enregistré.', 'wheel-game' ) . '</p></div>';
}

// Reset
if ( isset( $_POST['wheel_mail_reset'] ) && check_admin_referer( 'wheel_mail_template' ) ) {
    Wheel_Game_Mail::reset_template( $current_type );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template réinitialisé au défaut.', 'wheel-game' ) . '</p></div>';
}

$template = Wheel_Game_Mail::get_template( $current_type );
$type_def = $types[ $current_type ];
?>
<div class="wrap">
  <h1>✉️ <?php esc_html_e( 'Templates d\'emails', 'wheel-game' ); ?></h1>
  <p><?php esc_html_e( 'Personnalisez le sujet et le corps de chaque email automatique. Utilisez les variables {variable} pour insérer des valeurs dynamiques.', 'wheel-game' ); ?></p>

  <nav class="nav-tab-wrapper" style="margin:20px 0 0">
    <?php foreach ( $types as $slug => $def ) : ?>
      <a href="<?php echo esc_url( add_query_arg( 'type', $slug ) ); ?>"
         class="nav-tab <?php echo $current_type === $slug ? 'nav-tab-active' : ''; ?>">
        <?php echo esc_html( $def['icon'] . ' ' . $def['label'] ); ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div style="background:#fff;padding:24px;border:1px solid #c3c4c7;border-top:none">

    <p style="color:#6b7280;margin-top:0"><?php echo esc_html( $type_def['desc'] ); ?></p>

    <form method="post">
      <?php wp_nonce_field( 'wheel_mail_template' ); ?>

      <div style="display:grid;grid-template-columns:1fr 280px;gap:24px">

        <div>
          <h2><?php esc_html_e( 'Sujet', 'wheel-game' ); ?></h2>
          <input type="text" name="subject" value="<?php echo esc_attr( $template['subject'] ); ?>"
                 style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">

          <h2 style="margin-top:24px"><?php esc_html_e( 'Corps du message', 'wheel-game' ); ?></h2>
          <textarea name="body" rows="18"
                    style="width:100%;padding:12px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:'SF Mono',Consolas,monospace;font-size:13px;line-height:1.5"><?php echo esc_textarea( $template['body'] ); ?></textarea>
          <p class="description"><?php esc_html_e( 'Texte brut. Les variables {nom_variable} seront remplacées automatiquement au moment de l\'envoi.', 'wheel-game' ); ?></p>
        </div>

        <aside>
          <h2 style="margin-top:0">🧩 <?php esc_html_e( 'Variables disponibles', 'wheel-game' ); ?></h2>
          <p style="font-size:12px;color:#6b7280;margin-top:0"><?php esc_html_e( 'Cliquez pour copier.', 'wheel-game' ); ?></p>
          <ul style="list-style:none;padding:0;margin:0;font-size:13px">
            <?php
            $vars = array_merge( [ 'site_name' => __( 'Nom du site (global)', 'wheel-game' ) ], $type_def['vars'] );
            foreach ( $vars as $key => $label ) : ?>
              <li style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;margin-bottom:6px;display:flex;justify-content:space-between;gap:8px;align-items:center">
                <div>
                  <code style="cursor:pointer" class="wheel-var-copy" data-var="{<?php echo esc_attr( $key ); ?>}">{<?php echo esc_html( $key ); ?>}</code>
                  <div style="font-size:11px;color:#6b7280;margin-top:2px"><?php echo esc_html( $label ); ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </aside>
      </div>

      <p class="submit" style="display:flex;gap:10px">
        <?php submit_button( __( '💾 Enregistrer', 'wheel-game' ), 'primary', 'wheel_mail_save', false ); ?>
        <button type="submit" name="wheel_mail_reset" value="1" class="button button-secondary"
                onclick="return confirm('<?php echo esc_js( __( 'Restaurer le template par défaut ? Vos modifications seront perdues.', 'wheel-game' ) ); ?>')">
          ↺ <?php esc_html_e( 'Restaurer le défaut', 'wheel-game' ); ?>
        </button>
      </p>
    </form>
  </div>
</div>

<script>
(function() {
  document.querySelectorAll('.wheel-var-copy').forEach(el => {
    el.addEventListener('click', () => {
      const text = el.dataset.var;
      navigator.clipboard.writeText(text).then(() => {
        const orig = el.textContent;
        el.textContent = '✓ Copié';
        el.style.color = '#059669';
        setTimeout(() => { el.textContent = orig; el.style.color = ''; }, 1500);
      });
    });
  });
})();
</script>

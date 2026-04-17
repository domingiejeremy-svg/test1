<?php
/**
 * Meta box principale avec onglets.
 * Variables disponibles : $post, $c.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$prizes_json = esc_attr( wp_json_encode( $c['prizes'] ) );
$lead_labels = [
    'first_name' => __( 'Prénom', 'wheel-game' ),
    'last_name'  => __( 'Nom', 'wheel-game' ),
    'email'      => __( 'Email', 'wheel-game' ),
    'phone'      => __( 'Téléphone', 'wheel-game' ),
];
?>
<div class="wg-tabs-wrap">
  <nav class="wg-tabs-nav">
    <button type="button" class="wg-tab-btn is-active" data-tab="general">⚙️ <?php esc_html_e( 'Statut', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="prizes">🎁 <?php esc_html_e( 'Prix & probabilités', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="lead">👤 <?php esc_html_e( 'Capture lead', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="wheel-texts">🎡 <?php esc_html_e( 'Textes roue', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="reward-texts">🏆 <?php esc_html_e( 'Page cadeau', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="google">🔗 <?php esc_html_e( 'Google', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="design">🎨 <?php esc_html_e( 'Design', 'wheel-game' ); ?></button>
    <button type="button" class="wg-tab-btn" data-tab="notify">📧 <?php esc_html_e( 'Notifications', 'wheel-game' ); ?></button>
  </nav>

  <div class="wg-tab-content is-active" data-panel="general">
    <label class="wg-toggle">
      <input type="hidden" name="wheel_active" value="0">
      <input type="checkbox" name="wheel_active" value="1" <?php checked( $c['active'] ); ?>>
      <span><strong><?php esc_html_e( 'Campagne active', 'wheel-game' ); ?></strong> — <?php esc_html_e( 'la roue est accessible au public via son URL', 'wheel-game' ); ?></span>
    </label>
    <div class="wg-grid2" style="margin-top:14px">
      <div class="wg-field">
        <label><?php esc_html_e( 'Date de fin', 'wheel-game' ); ?></label>
        <input type="date" name="wheel_end_date" value="<?php echo esc_attr( $c['end_date'] ); ?>">
        <p class="wg-hint"><?php esc_html_e( 'Laisser vide = pas de limite.', 'wheel-game' ); ?></p>
      </div>
      <div class="wg-field">
        <label><?php esc_html_e( 'Quota max de tirages', 'wheel-game' ); ?></label>
        <input type="number" min="0" step="1" name="wheel_quota_max" value="<?php echo esc_attr( $c['quota_max'] ); ?>" placeholder="0">
        <p class="wg-hint"><?php printf( esc_html__( '0 = illimité. Actuellement %d participations.', 'wheel-game' ), $c['plays_count'] ); ?></p>
      </div>
    </div>
    <?php if ( $c['quota_reached'] || $c['expired'] ) : ?>
      <div class="wg-warn">⚠️ <?php echo esc_html( $c['expired'] ? __( 'Cette campagne a expiré.', 'wheel-game' ) : __( 'Le quota de tirages est atteint.', 'wheel-game' ) ); ?></div>
    <?php endif; ?>
  </div>

  <div class="wg-tab-content" data-panel="prizes">
    <div class="wg-tpl-picker">
      <span class="wg-tpl-label">📋 <?php esc_html_e( 'Template préfait :', 'wheel-game' ); ?></span>
      <?php foreach ( Wheel_Game_Templates_Library::all() as $slug => $tpl ) : ?>
        <button type="button" class="button wg-tpl-btn" data-template="<?php echo esc_attr( $slug ); ?>" data-campaign="<?php echo esc_attr( $post->ID ); ?>">
          <?php echo esc_html( $tpl['emoji'] ); ?> <?php echo esc_html( $tpl['name'] ); ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="prizes-hdr">
      <span>Emoji</span><span>Ligne 1</span><span>Ligne 2</span><span>%</span><span>Couleur</span><span></span>
    </div>
    <div id="prizes-list"></div>
    <div id="weight-bar"></div>
    <input type="hidden" name="wheel_prizes" id="wheel-prizes-json" value="<?php echo $prizes_json; ?>">
    <button type="button" id="add-prize-btn">+ <?php esc_html_e( 'Ajouter un prix', 'wheel-game' ); ?></button>

    <p class="wg-hint" style="display:flex;align-items:center;gap:14px;margin-top:10px">
      <span><?php esc_html_e( 'Somme idéale = 100%.', 'wheel-game' ); ?></span>
      <span id="percent-total" style="white-space:nowrap;font-size:12px"><?php esc_html_e( 'Total : —', 'wheel-game' ); ?></span>
    </p>

    <div class="wg-mc-panel">
      <div class="wg-mc-head">
        <strong>🎲 <?php esc_html_e( 'Simulateur Monte-Carlo', 'wheel-game' ); ?></strong>
        <label><?php esc_html_e( 'Itérations :', 'wheel-game' ); ?>
          <select id="mc-iter">
            <option value="1000">1 000</option>
            <option value="10000" selected>10 000</option>
            <option value="100000">100 000</option>
          </select>
        </label>
        <button type="button" class="button button-primary" id="mc-run"><?php esc_html_e( 'Simuler', 'wheel-game' ); ?></button>
      </div>
      <div id="mc-results"></div>
    </div>
  </div>

  <div class="wg-tab-content" data-panel="lead">
    <label class="wg-toggle">
      <input type="hidden" name="wheel_lead_required" value="0">
      <input type="checkbox" name="wheel_lead_required" value="1" <?php checked( $c['lead_required'] ); ?>>
      <span><strong><?php esc_html_e( 'Capture de lead obligatoire avant tirage', 'wheel-game' ); ?></strong></span>
    </label>
    <div class="wg-field" style="margin-top:14px">
      <label><?php esc_html_e( 'Champs demandés', 'wheel-game' ); ?></label>
      <div style="display:flex;gap:16px;flex-wrap:wrap">
        <?php foreach ( $lead_labels as $k => $label ) : ?>
          <label style="display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="wheel_lead_fields_check[]" value="<?php echo esc_attr( $k ); ?>"
                   <?php checked( in_array( $k, $c['lead_fields'], true ) ); ?>
                   onchange="document.getElementById('wheel_lead_fields').value = Array.from(document.querySelectorAll('[name=\'wheel_lead_fields_check[]\']:checked')).map(x=>x.value).join(',')">
            <?php echo esc_html( $label ); ?>
          </label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="wheel_lead_fields" id="wheel_lead_fields" value="<?php echo esc_attr( implode( ',', $c['lead_fields'] ) ); ?>">
    </div>
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Titre formulaire', 'wheel-game' ); ?></label>
        <input type="text" name="wheel_lead_title" value="<?php echo esc_attr( $c['lead_title'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Texte bouton', 'wheel-game' ); ?></label>
        <input type="text" name="wheel_lead_button" value="<?php echo esc_attr( $c['lead_button'] ); ?>"></div>
    </div>
    <div class="wg-field"><label><?php esc_html_e( 'Sous-titre / incitation', 'wheel-game' ); ?></label>
      <textarea name="wheel_lead_subtitle"><?php echo esc_textarea( $c['lead_subtitle'] ); ?></textarea></div>
    <div class="wg-field"><label><?php esc_html_e( 'Texte consentement RGPD', 'wheel-game' ); ?></label>
      <textarea name="wheel_lead_consent"><?php echo esc_textarea( $c['lead_consent_text'] ); ?></textarea></div>
  </div>

  <div class="wg-tab-content" data-panel="wheel-texts">
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Titre', 'wheel-game' ); ?></label>
        <input type="text" name="wheel_title" value="<?php echo esc_attr( $c['title'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Sous-titre', 'wheel-game' ); ?></label>
        <input type="text" name="wheel_subtitle" value="<?php echo esc_attr( $c['subtitle'] ); ?>"></div>
    </div>
    <div class="wg-field"><label><?php esc_html_e( 'Note de bas de page', 'wheel-game' ); ?></label>
      <input type="text" name="wheel_footer" value="<?php echo esc_attr( $c['footer'] ); ?>"></div>
  </div>

  <div class="wg-tab-content" data-panel="reward-texts">
    <div class="wg-field">
      <label><?php esc_html_e( 'Logo', 'wheel-game' ); ?></label>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:4px">
        <img id="reward-logo-preview" src="<?php echo esc_url( $c['logo'] ); ?>" alt=""
             style="<?php echo $c['logo'] ? '' : 'display:none;'; ?>width:72px;height:72px;object-fit:contain;border:2px solid #e0e4ea;border-radius:8px;background:#f8f9fb">
        <div style="display:flex;flex-direction:column;gap:6px">
          <button type="button" id="reward-logo-btn" class="button"><?php esc_html_e( 'Choisir un logo', 'wheel-game' ); ?></button>
          <button type="button" id="reward-logo-remove" class="button" style="<?php echo $c['logo'] ? '' : 'display:none;'; ?>color:#e74c3c"><?php esc_html_e( 'Supprimer', 'wheel-game' ); ?></button>
        </div>
      </div>
      <input type="hidden" name="reward_logo" id="reward-logo-input" value="<?php echo esc_attr( $c['logo'] ); ?>">
    </div>
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Validité du cadeau', 'wheel-game' ); ?></label>
        <input type="text" name="reward_validity" value="<?php echo esc_attr( $c['validity'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Titre section avis', 'wheel-game' ); ?></label>
        <input type="text" name="reward_review_title" value="<?php echo esc_attr( $c['review_title'] ); ?>"></div>
    </div>
    <div class="wg-field"><label><?php esc_html_e( 'Texte d\'incitation', 'wheel-game' ); ?></label>
      <textarea name="reward_review_subtitle"><?php echo esc_textarea( $c['review_subtitle'] ); ?></textarea></div>
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Étape 1', 'wheel-game' ); ?></label>
        <input type="text" name="reward_step1" value="<?php echo esc_attr( $c['step1'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Étape 2', 'wheel-game' ); ?></label>
        <input type="text" name="reward_step2" value="<?php echo esc_attr( $c['step2'] ); ?>"></div>
    </div>
    <div class="wg-field"><label><?php esc_html_e( 'Étape 3', 'wheel-game' ); ?></label>
      <input type="text" name="reward_step3" value="<?php echo esc_attr( $c['step3'] ); ?>"></div>
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Bouton principal', 'wheel-game' ); ?></label>
        <input type="text" name="reward_btn_main" value="<?php echo esc_attr( $c['btn_main'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Bouton sous-texte', 'wheel-game' ); ?></label>
        <input type="text" name="reward_btn_sub" value="<?php echo esc_attr( $c['btn_sub'] ); ?>"></div>
    </div>
    <div class="wg-field"><label><?php esc_html_e( 'Réassurance', 'wheel-game' ); ?></label>
      <input type="text" name="reward_urgency" value="<?php echo esc_attr( $c['urgency'] ); ?>"></div>
    <div class="wg-field"><label><?php esc_html_e( 'Note légale', 'wheel-game' ); ?></label>
      <textarea name="reward_footer"><?php echo esc_textarea( $c['reward_footer'] ); ?></textarea></div>
  </div>

  <div class="wg-tab-content" data-panel="google">
    <div class="wg-info">⚠️ <?php esc_html_e( 'Le lien Google Reviews est l\'élément clé : c\'est là que vos clients seront redirigés.', 'wheel-game' ); ?></div>
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'URL Google Reviews', 'wheel-game' ); ?></label>
        <input type="url" name="reward_google_url" value="<?php echo esc_attr( $c['google_url'] ); ?>" placeholder="https://search.google.com/local/writereview?placeid=..."></div>
      <div class="wg-field"><label><?php esc_html_e( 'Google Place ID', 'wheel-game' ); ?></label>
        <input type="text" name="google_place_id" value="<?php echo esc_attr( $c['google_place_id'] ); ?>" placeholder="ChIJ...">
        <p class="wg-hint"><a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Place ID Finder →</a></p></div>
    </div>
  </div>

  <div class="wg-tab-content" data-panel="design">
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Couleur fond 1', 'wheel-game' ); ?></label>
        <input type="color" name="wheel_bg_color_1" value="<?php echo esc_attr( $c['bg_color_1'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Couleur fond 2', 'wheel-game' ); ?></label>
        <input type="color" name="wheel_bg_color_2" value="<?php echo esc_attr( $c['bg_color_2'] ); ?>"></div>
    </div>
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Couleur d\'accent', 'wheel-game' ); ?></label>
        <input type="color" name="wheel_accent_color" value="<?php echo esc_attr( $c['accent_color'] ); ?>"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Police', 'wheel-game' ); ?></label>
        <select name="wheel_font_family">
          <?php foreach ( [ 'Segoe UI', 'Inter', 'Roboto', 'Poppins', 'Montserrat', 'Open Sans', 'Lato', 'Nunito' ] as $font ) : ?>
            <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $c['font_family'], $font ); ?>><?php echo esc_html( $font ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label class="wg-toggle">
      <input type="hidden" name="wheel_sound" value="0">
      <input type="checkbox" name="wheel_sound" value="1" <?php checked( $c['sound_enabled'] ); ?>>
      <span><strong><?php esc_html_e( 'Son de célébration', 'wheel-game' ); ?></strong></span>
    </label>
  </div>

  <div class="wg-tab-content" data-panel="notify">
    <div class="wg-grid2">
      <div class="wg-field"><label><?php esc_html_e( 'Email destinataire', 'wheel-game' ); ?></label>
        <input type="email" name="wheel_notify_email" value="<?php echo esc_attr( $c['notify_email'] ); ?>" placeholder="commercant@exemple.fr"></div>
      <div class="wg-field"><label><?php esc_html_e( 'Seuil (% max du prix)', 'wheel-game' ); ?></label>
        <input type="number" min="0" max="100" step="0.1" name="wheel_notify_threshold" value="<?php echo esc_attr( $c['notify_threshold'] ); ?>" placeholder="5">
        <p class="wg-hint"><?php esc_html_e( 'Alerte envoyée si prix gagné avec ≤ ce %. 0 = désactivé.', 'wheel-game' ); ?></p></div>
    </div>
  </div>
</div>

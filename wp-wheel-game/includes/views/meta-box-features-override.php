<?php
/**
 * Meta box : exceptions de features pour une campagne (admin only).
 * Permet d'ajouter ou retirer des features par rapport à l'offre de base.
 * Variables : $post, $c
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$offer_slug = Wheel_Game_Offer::for_campaign( $post->ID );
$offer      = Wheel_Game_Offer::get( $offer_slug );
$base_features  = Wheel_Game_Features::for_offer( $offer_slug );
$extra          = (array) get_post_meta( $post->ID, '_wheel_features_extra', true );
$removed        = (array) get_post_meta( $post->ID, '_wheel_features_removed', true );
$all_features   = Wheel_Game_Features::registry();

wp_nonce_field( 'wheel_features_override', 'wheel_features_override_nonce' );
?>
<p style="margin-top:0;margin-bottom:8px">
  <label for="wheel_offer_slug" style="display:block;font-weight:700;margin-bottom:4px"><?php esc_html_e( 'Offre du client', 'wheel-game' ); ?></label>
  <select name="wheel_offer_slug" id="wheel_offer_slug" style="width:100%;padding:6px 8px;font-size:13px">
    <?php foreach ( Wheel_Game_Offer::all_slugs() as $slug ) :
      $o = Wheel_Game_Offer::get( $slug ); ?>
      <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $offer_slug, $slug ); ?>>
        <?php echo esc_html( $o['emoji'] . ' ' . $o['label'] ); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <span style="color:#999;font-size:11px;display:block;margin-top:4px">
    <?php esc_html_e( 'Définit le pack de features de base (voir matrice) et les quotas.', 'wheel-game' ); ?>
    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Wheel_Game_Cpt::POST_TYPE . '&page=wheel-game-features' ) ); ?>"><?php esc_html_e( 'matrice', 'wheel-game' ); ?></a>
  </span>
</p>
<hr style="margin:10px 0">

<p style="font-size:12px;color:#666;margin-bottom:12px">
  <?php esc_html_e( 'Cette section vous permet d\'ajouter ou retirer des features spécifiquement pour ce client (sympathie, promo, SAV, etc.). Les changements s\'appliquent uniquement à cette campagne.', 'wheel-game' ); ?>
</p>

<table style="width:100%;border-collapse:collapse">
  <thead>
    <tr style="background:#f8f9fb;font-size:11px;color:#666;text-transform:uppercase">
      <th style="text-align:left;padding:6px 8px"><?php esc_html_e( 'Feature', 'wheel-game' ); ?></th>
      <th style="text-align:center;padding:6px 8px;width:60px"><?php esc_html_e( 'Base', 'wheel-game' ); ?></th>
      <th style="text-align:center;padding:6px 8px;width:60px"><?php esc_html_e( 'Effectif', 'wheel-game' ); ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ( $all_features as $slug => $def ) :
    $in_base   = in_array( $slug, $base_features, true );
    $in_extra  = in_array( $slug, $extra, true );
    $in_remove = in_array( $slug, $removed, true );
    $effective = ( $in_base && ! $in_remove ) || $in_extra;
  ?>
    <tr style="border-bottom:1px solid #f0f0f0">
      <td style="padding:6px 8px;font-size:12px">
        <?php echo esc_html( $def['icon'] . ' ' . $def['label'] ); ?>
      </td>
      <td style="text-align:center;padding:6px 8px;color:<?php echo $in_base ? '#00b894' : '#ccc'; ?>;font-size:14px">
        <?php echo $in_base ? '✓' : '—'; ?>
      </td>
      <td style="text-align:center;padding:6px 8px">
        <select name="feat_override[<?php echo esc_attr( $slug ); ?>]" style="width:100%;font-size:11px;padding:2px 4px">
          <option value=""
            <?php selected( ! $in_extra && ! $in_remove ); ?>>
            <?php esc_html_e( 'Défaut', 'wheel-game' ); ?>
          </option>
          <option value="add"
            <?php selected( $in_extra ); ?>
            <?php disabled( $in_base && ! $in_remove ); ?>>
            ✅ <?php esc_html_e( 'Ajouter', 'wheel-game' ); ?>
          </option>
          <option value="remove"
            <?php selected( $in_remove ); ?>
            <?php disabled( ! $in_base ); ?>>
            ❌ <?php esc_html_e( 'Retirer', 'wheel-game' ); ?>
          </option>
        </select>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p style="font-size:11px;color:#999;margin-top:10px">
  <strong>Base</strong> = feature incluse dans l'offre <?php echo esc_html( $offer['label'] ); ?>.<br>
  <strong>Effectif</strong> = statut final après override. <span style="color:#00b894">✓</span> = active.
</p>

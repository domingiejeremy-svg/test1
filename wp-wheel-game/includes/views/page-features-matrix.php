<?php
/**
 * Page admin : Matrice offres × features (configurable).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

// Sauvegarde
if ( isset( $_POST['wheel_matrix_nonce'] ) && wp_verify_nonce( $_POST['wheel_matrix_nonce'], 'wheel_matrix' ) ) {
    $posted = isset( $_POST['features'] ) ? wp_unslash( $_POST['features'] ) : [];
    $mapping = [];
    foreach ( Wheel_Game_Offer::all_slugs() as $offer ) {
        $mapping[ $offer ] = array_map( 'sanitize_key', (array) ( $posted[ $offer ] ?? [] ) );
    }
    Wheel_Game_Features::save_offer_mapping( $mapping );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Matrice enregistrée.', 'wheel-game' ) . '</p></div>';
}

if ( isset( $_POST['wheel_reset_nonce'] ) && wp_verify_nonce( $_POST['wheel_reset_nonce'], 'wheel_reset' ) ) {
    Wheel_Game_Features::save_offer_mapping( Wheel_Game_Features::default_mapping() );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Matrice réinitialisée aux valeurs par défaut.', 'wheel-game' ) . '</p></div>';
}

$mapping = Wheel_Game_Features::offer_mapping();
$offers  = Wheel_Game_Offer::all_slugs();
$groups  = Wheel_Game_Features::by_category();
?>
<div class="wrap">
  <h1>⚙️ <?php esc_html_e( 'Offres & Fonctionnalités', 'wheel-game' ); ?></h1>
  <p><?php esc_html_e( 'Cochez les fonctionnalités actives pour chaque offre. Les modifications prennent effet immédiatement sur tous les nouveaux clients. Pour ajouter une exception sur un client existant, utilisez la fiche de sa campagne.', 'wheel-game' ); ?></p>

  <form method="post" style="margin-top:20px">
    <?php wp_nonce_field( 'wheel_matrix', 'wheel_matrix_nonce' ); ?>

    <table class="widefat wg-matrix">
      <thead>
        <tr>
          <th style="width:40%"><?php esc_html_e( 'Fonctionnalité', 'wheel-game' ); ?></th>
          <?php foreach ( $offers as $offer_slug ) :
            $offer = Wheel_Game_Offer::get( $offer_slug ); ?>
            <th class="wg-matrix-col"><?php echo esc_html( $offer['emoji'] . ' ' . $offer['label'] ); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $groups as $cat => $features ) : ?>
          <tr class="wg-matrix-cat">
            <td colspan="<?php echo count( $offers ) + 1; ?>"><strong><?php echo esc_html( Wheel_Game_Features::category_label( $cat ) ); ?></strong></td>
          </tr>
          <?php foreach ( $features as $slug => $def ) : ?>
            <tr>
              <td>
                <strong><?php echo esc_html( $def['icon'] . ' ' . $def['label'] ); ?></strong>
                <br><span class="description"><?php echo esc_html( $def['desc'] ); ?></span>
                <?php if ( ! empty( $def['mutually_exclusive'] ) ) : ?>
                  <br><em style="font-size:11px;color:#e67e22">⚠️ <?php esc_html_e( 'Exclusif avec :', 'wheel-game' ); ?>
                    <?php
                    $me_labels = [];
                    foreach ( $def['mutually_exclusive'] as $me ) {
                        $me_def = Wheel_Game_Features::registry()[ $me ] ?? null;
                        if ( $me_def ) $me_labels[] = $me_def['label'];
                    }
                    echo esc_html( implode( ', ', $me_labels ) );
                    ?></em>
                <?php endif; ?>
              </td>
              <?php foreach ( $offers as $offer_slug ) :
                $active = in_array( $slug, (array) ( $mapping[ $offer_slug ] ?? [] ), true ); ?>
                <td class="wg-matrix-col">
                  <label class="wg-matrix-check">
                    <input type="checkbox" name="features[<?php echo esc_attr( $offer_slug ); ?>][]"
                           value="<?php echo esc_attr( $slug ); ?>"
                           <?php checked( $active ); ?>
                           data-feature="<?php echo esc_attr( $slug ); ?>"
                           data-offer="<?php echo esc_attr( $offer_slug ); ?>"
                           <?php if ( ! empty( $def['mutually_exclusive'] ) ) : ?>
                             data-exclusive="<?php echo esc_attr( implode( ',', $def['mutually_exclusive'] ) ); ?>"
                           <?php endif; ?>>
                  </label>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p class="submit">
      <?php submit_button( __( '💾 Enregistrer la matrice', 'wheel-game' ), 'primary', 'submit', false ); ?>
    </p>
  </form>

  <hr>
  <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Réinitialiser toutes les offres aux valeurs par défaut ? Les personnalisations seront perdues (les exceptions client ne sont pas touchées).', 'wheel-game' ) ); ?>');">
    <?php wp_nonce_field( 'wheel_reset', 'wheel_reset_nonce' ); ?>
    <?php submit_button( __( '↺ Réinitialiser aux valeurs par défaut', 'wheel-game' ), 'secondary', 'reset', false ); ?>
  </form>
</div>

<style>
  .wg-matrix { max-width: 1000px; margin-top: 14px; }
  .wg-matrix th, .wg-matrix td { padding: 10px 14px; vertical-align: top; }
  .wg-matrix-col { width: 130px; text-align: center; }
  .wg-matrix-cat td { background: #f3f4f6; font-size: 13px; padding: 10px 14px !important; }
  .wg-matrix-check input[type=checkbox] { width: 20px; height: 20px; cursor: pointer; }
  .wg-matrix .description { color: #666; font-size: 12px; line-height: 1.4; }
</style>

<script>
// Gère l'exclusivité : cocher "5 modifs/an" → décoche les autres mods-*
(function() {
  document.querySelectorAll('.wg-matrix input[type=checkbox][data-exclusive]').forEach(cb => {
    cb.addEventListener('change', function() {
      if (!this.checked) return;
      const exclusive = this.dataset.exclusive.split(',');
      const offer = this.dataset.offer;
      exclusive.forEach(feat => {
        const other = document.querySelector(`.wg-matrix input[name="features[${offer}][]"][value="${feat}"]`);
        if (other) other.checked = false;
      });
    });
  });
})();
</script>

<?php
/**
 * Liste des leads.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$campaign_id = absint( $_GET['campaign_id'] ?? 0 );
$leads       = Wheel_Game_Plugin::get_instance()->leads->get_all( $campaign_id, 1000 );
$total       = Wheel_Game_Plugin::get_instance()->leads->count( $campaign_id );
$nonce       = wp_create_nonce( 'wheel_admin' );
$export_url  = add_query_arg( [
    'action' => 'wheel_export_leads', 'campaign_id' => $campaign_id, 'nonce' => $nonce,
], admin_url( 'admin-ajax.php' ) );

$campaigns = get_posts( [
    'post_type'   => Wheel_Game_Cpt::POST_TYPE,
    'post_status' => [ 'publish', 'draft' ],
    'numberposts' => -1,
] );
?>
<div class="wrap">
  <h1>👤 <?php esc_html_e( 'Leads capturés', 'wheel-game' ); ?></h1>

  <form method="get" style="margin:14px 0;display:flex;gap:10px;align-items:center">
    <input type="hidden" name="post_type" value="<?php echo esc_attr( Wheel_Game_Cpt::POST_TYPE ); ?>">
    <input type="hidden" name="page" value="wheel-game-leads">
    <label><?php esc_html_e( 'Filtrer par campagne :', 'wheel-game' ); ?></label>
    <select name="campaign_id" onchange="this.form.submit()">
      <option value="0"><?php esc_html_e( 'Toutes', 'wheel-game' ); ?></option>
      <?php foreach ( $campaigns as $camp ) : ?>
        <option value="<?php echo esc_attr( $camp->ID ); ?>" <?php selected( $campaign_id, $camp->ID ); ?>>
          <?php echo esc_html( $camp->post_title ); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">⬇️ <?php esc_html_e( 'Exporter CSV', 'wheel-game' ); ?></a>
    <span style="margin-left:auto;color:#666"><?php printf( esc_html__( '%d leads', 'wheel-game' ), $total ); ?></span>
  </form>

  <?php if ( empty( $leads ) ) : ?>
    <p><?php esc_html_e( 'Aucun lead pour le moment.', 'wheel-game' ); ?></p>
  <?php else : ?>
  <table class="wp-list-table widefat striped">
    <thead><tr>
      <th><?php esc_html_e( 'Date', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Campagne', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Prénom', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Nom', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Email', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Téléphone', 'wheel-game' ); ?></th>
      <th><?php esc_html_e( 'Consent.', 'wheel-game' ); ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ( $leads as $l ) : ?>
    <tr>
      <td><?php echo esc_html( $l->created_at ); ?></td>
      <td><a href="<?php echo esc_url( get_edit_post_link( $l->campaign_id ) ); ?>"><?php echo esc_html( get_the_title( $l->campaign_id ) ); ?></a></td>
      <td><?php echo esc_html( $l->first_name ); ?></td>
      <td><?php echo esc_html( $l->last_name ); ?></td>
      <td><?php echo $l->email ? '<a href="mailto:' . esc_attr( $l->email ) . '">' . esc_html( $l->email ) . '</a>' : '—'; ?></td>
      <td><?php echo $l->phone ? '<a href="tel:' . esc_attr( $l->phone ) . '">' . esc_html( $l->phone ) . '</a>' : '—'; ?></td>
      <td><?php echo $l->consent ? '<span style="color:#00b894;font-weight:700">✓</span>' : '—'; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php
/**
 * Plugin Name: Wheel Game — Roue des cadeaux
 * Description: Hébergez des jeux de roue personnalisés pour vos clients. Chaque campagne a sa propre URL, ses propres prix et son propre suivi des participations.
 * Version:     1.0.0
 * Author:      Votre Nom
 * License:     GPL v2 or later
 * Text Domain: wheel-game
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WHEEL_GAME_VERSION', '1.1.0' );
define( 'WHEEL_GAME_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WHEEL_GAME_URL',     plugin_dir_url( __FILE__ ) );

/* ════════════════════════════════════════════════════════════════════════════
   CLASSE PRINCIPALE
   ════════════════════════════════════════════════════════════════════════════ */
class Wheel_Game {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Init
        add_action( 'init',                  [ $this, 'register_cpt' ] );
        add_filter( 'template_include',      [ $this, 'template_include' ] );

        // Admin
        add_action( 'add_meta_boxes',                                [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_wheel_campaign',                      [ $this, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts',                         [ $this, 'admin_scripts' ] );
        add_filter( 'manage_wheel_campaign_posts_columns',           [ $this, 'admin_columns' ] );
        add_action( 'manage_wheel_campaign_posts_custom_column',     [ $this, 'admin_column_content' ], 10, 2 );
        add_filter( 'manage_edit-wheel_campaign_sortable_columns',   [ $this, 'sortable_columns' ] );

        // AJAX (connecté + non connecté pour le jeu public)
        add_action( 'wp_ajax_nopriv_wheel_save_play', [ $this, 'ajax_save_play' ] );
        add_action( 'wp_ajax_wheel_save_play',        [ $this, 'ajax_save_play' ] );

        // Activation / désactivation
        register_activation_hook( __FILE__,   [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
    }

    /* ── Activation ─────────────────────────────────────────────────────────── */

    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'wheel_plays';
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            campaign_id  bigint(20)   NOT NULL,
            prize_index  tinyint(3)   NOT NULL DEFAULT 0,
            prize_label  varchar(255) NOT NULL DEFAULT '',
            played_at    datetime     NOT NULL,
            ip_hash      varchar(64)  NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ── Custom Post Type ───────────────────────────────────────────────────── */

    public function register_cpt() {
        register_post_type( 'wheel_campaign', [
            'labels' => [
                'name'               => 'Campagnes Roue',
                'singular_name'      => 'Campagne',
                'add_new'            => 'Nouvelle campagne',
                'add_new_item'       => 'Nouvelle campagne',
                'edit_item'          => 'Modifier la campagne',
                'all_items'          => 'Toutes les campagnes',
                'menu_name'          => 'Roue des cadeaux',
                'search_items'       => 'Rechercher une campagne',
            ],
            'public'             => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-games',
            'menu_position'      => 30,
            'supports'           => [ 'title' ],
            'rewrite'            => [ 'slug' => 'roue', 'with_front' => false ],
            'has_archive'        => false,
            'show_in_rest'       => false,
            'publicly_queryable' => true,
        ] );
    }

    /* ── Routing des templates ───────────────────────────────────────────────── */

    public function template_include( $template ) {
        if ( ! is_singular( 'wheel_campaign' ) ) return $template;

        $step = sanitize_key( $_GET['step'] ?? 'wheel' );

        if ( $step === 'reward' ) {
            $file = WHEEL_GAME_DIR . 'templates/reward.php';
        } else {
            $file = WHEEL_GAME_DIR . 'templates/wheel.php';
        }

        return file_exists( $file ) ? $file : $template;
    }

    /* ── Scripts admin ───────────────────────────────────────────────────────── */

    public function admin_scripts( $hook ) {
        global $post;
        if ( ( $hook === 'post-new.php' || $hook === 'post.php' )
            && isset( $post ) && $post->post_type === 'wheel_campaign' ) {
            wp_enqueue_script(
                'wheel-admin',
                WHEEL_GAME_URL . 'assets/admin.js',
                [],
                WHEEL_GAME_VERSION,
                true
            );
        }
    }

    /* ── Colonnes admin ─────────────────────────────────────────────────────── */

    public function admin_columns( $cols ) {
        return [
            'cb'       => $cols['cb'],
            'title'    => 'Nom de la campagne',
            'url'      => 'URL publique',
            'plays'    => 'Participations',
            'status'   => 'Statut',
            'date'     => 'Créée le',
        ];
    }

    public function sortable_columns( $cols ) {
        $cols['plays'] = 'plays';
        return $cols;
    }

    public function admin_column_content( $col, $post_id ) {
        global $wpdb;

        if ( $col === 'url' ) {
            $url = get_permalink( $post_id );
            echo '<a href="' . esc_url( $url ) . '" target="_blank" style="font-size:12px">' . esc_html( $url ) . '</a><br>';
            echo '<button class="button button-small" style="margin-top:4px"
                onclick="navigator.clipboard.writeText(\'' . esc_js( $url ) . '\');
                         this.textContent=\'✓ Copié\';
                         setTimeout(()=>this.textContent=\'Copier URL\',2000)">
                Copier URL
            </button>';
        }

        if ( $col === 'plays' ) {
            $table = $wpdb->prefix . 'wheel_plays';
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d", $post_id
            ) );
            echo '<strong style="font-size:16px">' . $count . '</strong>';
        }

        if ( $col === 'status' ) {
            $active = get_post_meta( $post_id, '_wheel_active', true );
            if ( $active === '0' ) {
                echo '<span style="color:#e74c3c;font-weight:700">● Désactivé</span>';
            } else {
                echo '<span style="color:#00b894;font-weight:700">● Actif</span>';
            }
        }
    }

    /* ── Meta boxes ─────────────────────────────────────────────────────────── */

    public function register_meta_boxes() {
        add_meta_box(
            'wheel_settings',
            '⚙️ Configuration de la campagne',
            [ $this, 'render_settings_box' ],
            'wheel_campaign', 'normal', 'high'
        );
        add_meta_box(
            'wheel_sidebar',
            '📊 Statistiques & QR Code',
            [ $this, 'render_sidebar_box' ],
            'wheel_campaign', 'side', 'default'
        );
    }

    public function render_settings_box( $post ) {
        wp_nonce_field( 'wheel_save_meta', 'wheel_nonce' );

        // Valeurs sauvegardées
        $active  = get_post_meta( $post->ID, '_wheel_active', true );
        $active  = $active === '' ? '1' : $active;
        $prizes  = get_post_meta( $post->ID, '_wheel_prizes', true ) ?: [];

        // Textes roue
        $w_title = get_post_meta( $post->ID, '_wheel_title',    true ) ?: 'Tentez votre chance !';
        $w_sub   = get_post_meta( $post->ID, '_wheel_subtitle', true ) ?: 'Tournez la roue et gagnez un cadeau exclusif';
        $w_foot  = get_post_meta( $post->ID, '_wheel_footer',   true ) ?: '1 participation par client · Offre non cumulable';

        // Textes récompense
        $g_url   = get_post_meta( $post->ID, '_reward_google_url',      true );
        $r_valid = get_post_meta( $post->ID, '_reward_validity',        true ) ?: 'Valable lors de votre prochaine visite · Présentez cette page';
        $r_rtit  = get_post_meta( $post->ID, '_reward_review_title',    true ) ?: 'Laissez-nous un avis Google ⭐';
        $r_rsub  = get_post_meta( $post->ID, '_reward_review_subtitle', true ) ?: "Votre avis nous aide énormément.\nÇa ne prend que 30 secondes !";
        $r_s1    = get_post_meta( $post->ID, '_reward_step1',           true ) ?: 'Cliquez sur le bouton ci-dessous';
        $r_s2    = get_post_meta( $post->ID, '_reward_step2',           true ) ?: 'Donnez-nous 5 étoiles et laissez un petit commentaire';
        $r_s3    = get_post_meta( $post->ID, '_reward_step3',           true ) ?: 'Revenez récupérer votre cadeau en montrant cette page !';
        $r_bm    = get_post_meta( $post->ID, '_reward_btn_main',        true ) ?: 'Laisser un avis Google';
        $r_bs    = get_post_meta( $post->ID, '_reward_btn_sub',         true ) ?: 'Ouvre la page Google de notre établissement';
        $r_urg   = get_post_meta( $post->ID, '_reward_urgency',         true ) ?: 'Votre cadeau sera validé après vérification de votre avis.';
        $r_foot  = get_post_meta( $post->ID, '_reward_footer',          true ) ?: "En laissant un avis, vous acceptez les conditions d'utilisation de Google.\nCadeau non échangeable · Une utilisation par personne.";

        if ( empty( $prizes ) ) {
            $prizes = [
                [ 'emoji' => '☕', 'line1' => 'Café',      'line2' => 'offert',           'color' => '#e74c3c', 'percent' => 15   ],
                [ 'emoji' => '💜', 'line1' => '10%',       'line2' => 'de réduction',     'color' => '#8e44ad', 'percent' => 20   ],
                [ 'emoji' => '🍰', 'line1' => 'Dessert',   'line2' => 'offert',           'color' => '#2980b9', 'percent' => 10   ],
                [ 'emoji' => '💰', 'line1' => '-5€',       'line2' => 'prochaine visite', 'color' => '#16a085', 'percent' => 5    ],
                [ 'emoji' => '🚚', 'line1' => 'Livraison', 'line2' => 'gratuite',         'color' => '#d35400', 'percent' => 15   ],
                [ 'emoji' => '🔥', 'line1' => '15%',       'line2' => 'de réduction',     'color' => '#c0392b', 'percent' => 20   ],
                [ 'emoji' => '🥗', 'line1' => 'Entrée',    'line2' => 'offerte',          'color' => '#27ae60', 'percent' => 10   ],
                [ 'emoji' => '🥤', 'line1' => 'Boisson',   'line2' => 'offerte',          'color' => '#2471a3', 'percent' => 5    ],
            ];
        }

        $prizes_json = esc_attr( wp_json_encode( $prizes ) );
        ?>
        <style>
            .wg-wrap     { max-width:920px }
            .wg-section  { margin-bottom:26px; padding-bottom:22px; border-bottom:1px solid #e8ecf0 }
            .wg-section:last-child { border-bottom:none; margin-bottom:0 }
            .wg-section h3 { font-size:13.5px; font-weight:700; margin:0 0 14px; color:#1a1a2e; display:flex; align-items:center; gap:6px }
            .wg-grid2    { display:grid; grid-template-columns:1fr 1fr; gap:14px }
            .wg-field    { margin-bottom:12px }
            .wg-field:last-child { margin-bottom:0 }
            .wg-field label { display:block; font-size:11.5px; font-weight:700; color:#666; text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px }
            .wg-field input[type=text],
            .wg-field input[type=url],
            .wg-field textarea { width:100%; border:2px solid #e0e4ea; border-radius:7px; padding:8px 11px; font-size:13px; font-family:inherit; transition:border-color .2s }
            .wg-field input:focus,
            .wg-field textarea:focus { border-color:#6c5ce7; outline:none; box-shadow:0 0 0 3px rgba(108,92,231,.1) }
            .wg-field textarea { min-height:62px; resize:vertical }
            .wg-hint     { font-size:11px; color:#999; margin-top:3px; line-height:1.4 }
            .wg-info     { background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:10px 14px; font-size:12px; color:#856404; margin-bottom:14px; line-height:1.5 }
            /* Prize rows */
            .prizes-hdr  { display:grid; grid-template-columns:52px 1fr 1fr 80px 46px 34px; gap:8px; padding:0 6px; margin-bottom:6px }
            .prizes-hdr span { font-size:11px; font-weight:700; color:#aaa; text-transform:uppercase }
            .prize-row   { display:grid; grid-template-columns:52px 1fr 1fr 80px 46px 34px; gap:8px; align-items:center; background:#f8f9fb; border:1.5px solid #e0e4ea; border-radius:9px; padding:8px 6px; margin-bottom:6px; transition:border-color .2s }
            .prize-row:hover { border-color:#c4c9d4 }
            .prize-row input[type=text]   { padding:6px 8px; border:1.5px solid #e0e4ea; border-radius:6px; font-size:13px; width:100%; font-family:inherit }
            .prize-row input[type=text]:focus { border-color:#6c5ce7; outline:none }
            .prize-row input[type=number] { padding:6px 8px; border:1.5px solid #e0e4ea; border-radius:6px; font-size:13px; width:100%; font-family:inherit; text-align:center }
            .prize-row input[type=number]:focus { border-color:#6c5ce7; outline:none }
            .prize-row input[type=color]  { width:46px; height:34px; border:1.5px solid #e0e4ea; border-radius:6px; padding:2px; cursor:pointer }
            .prize-row .del-btn { background:#fff0f0; color:#e74c3c; border:none; border-radius:6px; width:32px; height:32px; cursor:pointer; font-size:14px; font-weight:700 }
            .prize-row .del-btn:hover { background:#ffe0e0 }
            #weight-bar  { height:28px; border-radius:8px; overflow:hidden; display:flex; margin-top:10px; border:1px solid #e0e4ea }
            #weight-bar div { display:flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:700; color:rgba(255,255,255,0.95); overflow:hidden; white-space:nowrap; padding:0 3px; transition:flex .3s }
            #add-prize-btn { margin-top:6px; width:100%; padding:9px; background:#f4f6f9; border:2px dashed #ccc; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; color:#666; font-family:inherit }
            #add-prize-btn:hover { background:#eef0f3; border-color:#aaa }
        </style>

        <div class="wg-wrap">

            <!-- Statut -->
            <div class="wg-section">
                <h3>⚙️ Statut</h3>
                <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-size:14px">
                    <input type="hidden" name="wheel_active" value="0">
                    <input type="checkbox" name="wheel_active" value="1" <?php checked( $active, '1' ); ?> style="width:18px;height:18px">
                    <span><strong>Campagne active</strong> — la roue est accessible au public via son URL</span>
                </label>
            </div>

            <!-- Roue — textes -->
            <div class="wg-section">
                <h3>🎡 Page Roue — Textes</h3>
                <div class="wg-grid2">
                    <div class="wg-field">
                        <label>Titre</label>
                        <input type="text" name="wheel_title" value="<?php echo esc_attr( $w_title ); ?>">
                    </div>
                    <div class="wg-field">
                        <label>Sous-titre</label>
                        <input type="text" name="wheel_subtitle" value="<?php echo esc_attr( $w_sub ); ?>">
                    </div>
                </div>
                <div class="wg-field">
                    <label>Note de bas de page</label>
                    <input type="text" name="wheel_footer" value="<?php echo esc_attr( $w_foot ); ?>">
                </div>
            </div>

            <!-- Prix -->
            <div class="wg-section">
                <h3>🎁 Prix de la roue</h3>
                <div class="prizes-hdr">
                    <span>Emoji</span><span>Ligne 1</span><span>Ligne 2</span><span>%</span><span>Couleur</span><span></span>
                </div>
                <div id="prizes-list"></div>
                <div id="weight-bar"></div>
                <input type="hidden" name="wheel_prizes" id="wheel-prizes-json" value="<?php echo $prizes_json; ?>">
                <button type="button" id="add-prize-btn">+ Ajouter un prix</button>
                <p class="wg-hint" style="margin-top:8px;display:flex;align-items:center;gap:14px">
                    <span>Entrez la <strong>probabilité en %</strong> pour chaque cadeau (ex : 60% pour un café, 0.01% pour un gros lot).
                    La somme idéale est 100%.</span>
                    <span id="percent-total" style="white-space:nowrap;font-size:12px">Total : —</span>
                </p>
            </div>

            <!-- Google Reviews -->
            <div class="wg-section">
                <h3>🔗 Lien Google Reviews</h3>
                <div class="wg-info">⚠️ Ce lien est l'élément le plus important : c'est là que vos clients seront redirigés pour laisser un avis.</div>
                <div class="wg-field">
                    <label>URL Google Reviews</label>
                    <input type="url" name="reward_google_url" value="<?php echo esc_attr( $g_url ); ?>"
                           placeholder="https://search.google.com/local/writereview?placeid=...">
                    <p class="wg-hint">Trouvez votre Place ID sur <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Google Place ID Finder</a></p>
                </div>
            </div>

            <!-- Récompense — textes -->
            <div class="wg-section">
                <h3>🏆 Page Récompense — Textes</h3>
                <div class="wg-grid2">
                    <div class="wg-field">
                        <label>Validité du cadeau</label>
                        <input type="text" name="reward_validity" value="<?php echo esc_attr( $r_valid ); ?>">
                    </div>
                    <div class="wg-field">
                        <label>Titre section avis</label>
                        <input type="text" name="reward_review_title" value="<?php echo esc_attr( $r_rtit ); ?>">
                    </div>
                </div>
                <div class="wg-field">
                    <label>Texte d'incitation</label>
                    <textarea name="reward_review_subtitle"><?php echo esc_textarea( $r_rsub ); ?></textarea>
                    <p class="wg-hint">Retour à la ligne = nouvelle ligne affichée.</p>
                </div>
                <div class="wg-grid2">
                    <div class="wg-field">
                        <label>Étape 1</label>
                        <input type="text" name="reward_step1" value="<?php echo esc_attr( $r_s1 ); ?>">
                    </div>
                    <div class="wg-field">
                        <label>Étape 2</label>
                        <input type="text" name="reward_step2" value="<?php echo esc_attr( $r_s2 ); ?>">
                    </div>
                </div>
                <div class="wg-field">
                    <label>Étape 3</label>
                    <input type="text" name="reward_step3" value="<?php echo esc_attr( $r_s3 ); ?>">
                </div>
                <div class="wg-grid2">
                    <div class="wg-field">
                        <label>Bouton — texte principal</label>
                        <input type="text" name="reward_btn_main" value="<?php echo esc_attr( $r_bm ); ?>">
                    </div>
                    <div class="wg-field">
                        <label>Bouton — sous-texte</label>
                        <input type="text" name="reward_btn_sub" value="<?php echo esc_attr( $r_bs ); ?>">
                    </div>
                </div>
                <div class="wg-field">
                    <label>Message d'urgence</label>
                    <input type="text" name="reward_urgency" value="<?php echo esc_attr( $r_urg ); ?>">
                </div>
                <div class="wg-field">
                    <label>Note légale (bas de page)</label>
                    <textarea name="reward_footer"><?php echo esc_textarea( $r_foot ); ?></textarea>
                </div>
            </div>

        </div>
        <?php
    }

    public function render_sidebar_box( $post ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'wheel_plays';
        $total  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d", $post->ID
        ) );

        // Distribution des prix
        $dist = $wpdb->get_results( $wpdb->prepare(
            "SELECT prize_label, COUNT(*) as cnt
             FROM {$table}
             WHERE campaign_id = %d
             GROUP BY prize_label
             ORDER BY cnt DESC",
            $post->ID
        ) );

        $url = get_permalink( $post->ID );
        ?>
        <p style="font-size:13px"><strong>Participations totales :</strong>
            <span style="font-size:22px;font-weight:800;color:#6c5ce7;display:block;margin-top:2px"><?php echo $total; ?></span>
        </p>

        <?php if ( $dist ) : ?>
        <hr style="margin:12px 0">
        <p style="font-size:12px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Distribution</p>
        <?php foreach ( $dist as $row ) : ?>
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                <span><?php echo esc_html( $row->prize_label ); ?></span>
                <strong><?php echo (int) $row->cnt; ?></strong>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <hr style="margin:12px 0">
        <p style="font-size:12px;font-weight:700;color:#666;margin-bottom:6px">URL publique</p>
        <input type="text" value="<?php echo esc_attr( $url ); ?>" readonly
               style="width:100%;font-size:11px;margin-bottom:6px">
        <button class="button button-small" style="width:100%;margin-bottom:12px"
            onclick="navigator.clipboard.writeText('<?php echo esc_js( $url ); ?>');
                     this.textContent='✓ Copié !';
                     setTimeout(()=>this.textContent='📋 Copier l\'URL',2000)">
            📋 Copier l'URL
        </button>

        <p style="font-size:12px;font-weight:700;color:#666;margin-bottom:6px">QR Code</p>
        <div id="qr-container" style="text-align:center;margin-bottom:8px">
            <img id="qr-img" src="" alt="" style="display:none;width:150px;height:150px;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.2);border-radius:8px">
        </div>
        <button class="button button-small" style="width:100%;margin-bottom:6px" onclick="showQR()">🔲 Générer le QR Code</button>
        <a id="qr-download" href="#" download="qrcode-<?php echo esc_attr( $post->post_name ); ?>.png"
           class="button button-small" style="width:100%;text-align:center;display:none">
            ⬇️ Télécharger (400px)
        </a>

        <script>
        function showQR() {
            var url = <?php echo wp_json_encode( $url ); ?>;
            var slug = <?php echo wp_json_encode( $post->post_name ); ?>;
            var src  = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' + encodeURIComponent(url);
            var img  = document.getElementById('qr-img');
            img.src  = src;
            img.style.display = 'block';
            var dl   = document.getElementById('qr-download');
            dl.href  = src;
            dl.style.display = 'block';
        }
        </script>
        <?php
    }

    /* ── Sauvegarde ─────────────────────────────────────────────────────────── */

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['wheel_nonce'] )
            || ! wp_verify_nonce( $_POST['wheel_nonce'], 'wheel_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $text_fields = [
            '_wheel_active'            => 'wheel_active',
            '_wheel_title'             => 'wheel_title',
            '_wheel_subtitle'          => 'wheel_subtitle',
            '_wheel_footer'            => 'wheel_footer',
            '_reward_google_url'       => 'reward_google_url',
            '_reward_validity'         => 'reward_validity',
            '_reward_review_title'     => 'reward_review_title',
            '_reward_review_subtitle'  => 'reward_review_subtitle',
            '_reward_step1'            => 'reward_step1',
            '_reward_step2'            => 'reward_step2',
            '_reward_step3'            => 'reward_step3',
            '_reward_btn_main'         => 'reward_btn_main',
            '_reward_btn_sub'          => 'reward_btn_sub',
            '_reward_urgency'          => 'reward_urgency',
            '_reward_footer'           => 'reward_footer',
        ];

        foreach ( $text_fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ) ) );
            }
        }

        // Prizes (JSON)
        if ( isset( $_POST['wheel_prizes'] ) ) {
            $raw = json_decode( wp_unslash( $_POST['wheel_prizes'] ), true );
            if ( is_array( $raw ) ) {
                $clean = array_values( array_filter( array_map( function( $p ) {
                    $color = sanitize_hex_color( $p['color'] ?? '#6c5ce7' );
                    return [
                        'emoji'   => sanitize_text_field( $p['emoji'] ?? '🎁' ),
                        'line1'   => sanitize_text_field( $p['line1'] ?? '' ),
                        'line2'   => sanitize_text_field( $p['line2'] ?? '' ),
                        'color'   => $color ?: '#6c5ce7',
                        'percent' => max( 0, round( floatval( $p['percent'] ?? 10 ), 2 ) ),
                    ];
                }, $raw ), fn( $p ) => ! empty( $p['line1'] ) ) );

                if ( count( $clean ) >= 2 ) {
                    update_post_meta( $post_id, '_wheel_prizes', $clean );
                }
            }
        }
    }

    /* ── AJAX : enregistrer une participation ───────────────────────────────── */

    public function ajax_save_play() {
        check_ajax_referer( 'wheel_play', 'nonce' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $prize_index = absint( $_POST['prize_index'] ?? 0 );
        $prize_label = sanitize_text_field( wp_unslash( $_POST['prize_label'] ?? '' ) );

        if ( ! $campaign_id ) {
            wp_send_json_error( 'Invalid campaign' );
        }

        // Vérification anti-replay côté serveur
        $cookie_key = 'wheel_played_' . $campaign_id;
        if ( isset( $_COOKIE[ $cookie_key ] ) ) {
            wp_send_json_error( 'Already played' );
        }

        // Sauvegarder en base de données
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'wheel_plays', [
            'campaign_id' => $campaign_id,
            'prize_index' => $prize_index,
            'prize_label' => $prize_label,
            'played_at'   => current_time( 'mysql' ),
            'ip_hash'     => hash( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? '' ) . NONCE_SALT ),
        ] );

        // Cookie serveur (httpOnly, 30 jours) — plus sécurisé que localStorage
        setcookie(
            $cookie_key,
            base64_encode( wp_json_encode( [ 'index' => $prize_index, 'label' => $prize_label ] ) ),
            time() + ( 30 * DAY_IN_SECONDS ),
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true   // httpOnly
        );

        wp_send_json_success( [ 'prize_label' => $prize_label ] );
    }
}

Wheel_Game::get_instance();

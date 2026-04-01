<?php
/**
 * Plugin Name: Wheel Game — Roue des cadeaux
 * Description: Hébergez des jeux de roue personnalisés pour vos clients. Chaque campagne a sa propre URL, ses propres prix et son propre suivi des participations.
 * Version:     1.6.1
 * Author:      Votre Nom
 * License:     GPL v2 or later
 * Text Domain: wheel-game
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WHEEL_GAME_VERSION', '1.6.1' );
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
        add_action( 'wp_ajax_nopriv_wheel_save_play',      [ $this, 'ajax_save_play' ] );
        add_action( 'wp_ajax_wheel_save_play',             [ $this, 'ajax_save_play' ] );

        // AJAX admin — suivi Google
        add_action( 'wp_ajax_wheel_fetch_google_stats',    [ $this, 'ajax_fetch_google_stats' ] );
        add_action( 'wp_ajax_wheel_save_google_api_key',   [ $this, 'ajax_save_google_api_key' ] );

        // Cron quotidien
        add_action( 'wheel_daily_google_fetch', [ $this, 'daily_fetch_google_stats' ] );

        // Mise à jour BDD sans réactivation
        add_action( 'admin_init', [ $this, 'maybe_upgrade_db' ] );

        // Activation / désactivation
        register_activation_hook( __FILE__,   [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    /* ── Activation ─────────────────────────────────────────────────────────── */

    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
        $this->create_table();
        if ( ! wp_next_scheduled( 'wheel_daily_google_fetch' ) ) {
            wp_schedule_event( time(), 'daily', 'wheel_daily_google_fetch' );
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'wheel_daily_google_fetch' );
        flush_rewrite_rules();
    }

    public function maybe_upgrade_db() {
        if ( get_option( 'wheel_game_db_version' ) !== '1.1' ) {
            $this->create_table();
            update_option( 'wheel_game_db_version', '1.1' );
        }
    }

    public function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_plays (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            campaign_id  bigint(20)   NOT NULL,
            prize_index  tinyint(3)   NOT NULL DEFAULT 0,
            prize_label  varchar(255) NOT NULL DEFAULT '',
            played_at    datetime     NOT NULL,
            ip_hash      varchar(64)  NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_google_stats (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            campaign_id  bigint(20)   NOT NULL,
            rating       decimal(3,1) NOT NULL DEFAULT 0.0,
            review_count int(11)      NOT NULL DEFAULT 0,
            recorded_at  date         NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_date (campaign_id, recorded_at),
            KEY campaign_id (campaign_id)
        ) {$charset};" );
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
            wp_enqueue_media();
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
        add_meta_box(
            'wheel_google_tracking',
            '📈 Suivi des avis Google',
            [ $this, 'render_google_tracking_box' ],
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
        $r_logo  = get_post_meta( $post->ID, '_reward_logo',            true ) ?: '';
        $g_url   = get_post_meta( $post->ID, '_reward_google_url',      true );
        $r_valid = get_post_meta( $post->ID, '_reward_validity',        true ) ?: 'Valable lors de votre prochaine visite · Présentez cette page';
        $r_rtit  = get_post_meta( $post->ID, '_reward_review_title',    true ) ?: 'Laissez-nous un avis Google ⭐';
        $r_rsub  = get_post_meta( $post->ID, '_reward_review_subtitle', true ) ?: "Votre avis nous aide énormément.\nÇa ne prend que 30 secondes !";
        $r_s1    = get_post_meta( $post->ID, '_reward_step1',           true ) ?: 'Cliquez sur le bouton ci-dessous';
        $r_s2    = get_post_meta( $post->ID, '_reward_step2',           true ) ?: 'Donnez-nous 5 étoiles et laissez un petit commentaire';
        $r_s3    = get_post_meta( $post->ID, '_reward_step3',           true ) ?: 'Revenez récupérer votre cadeau en montrant cette page !';
        $r_bm    = get_post_meta( $post->ID, '_reward_btn_main',        true ) ?: 'Laisser un avis Google';
        $r_bs    = get_post_meta( $post->ID, '_reward_btn_sub',         true ) ?: 'Ouvre la page Google de notre établissement';
        $r_urg   = get_post_meta( $post->ID, '_reward_urgency',         true ) ?: "Votre avis est totalement libre et facultatif — votre cadeau vous est acquis quoi qu'il arrive ✅";
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
                <?php
                $g_place_id = get_post_meta( $post->ID, '_google_place_id', true ) ?: '';
                // Extraire le Place ID de l'URL si non défini
                if ( ! $g_place_id && $g_url ) {
                    parse_str( (string) parse_url( $g_url, PHP_URL_QUERY ), $qs );
                    $g_place_id = $qs['placeid'] ?? $qs['place_id'] ?? '';
                }
                ?>
                <div class="wg-grid2">
                    <div class="wg-field">
                        <label>URL Google Reviews</label>
                        <input type="url" name="reward_google_url" value="<?php echo esc_attr( $g_url ); ?>"
                               placeholder="https://search.google.com/local/writereview?placeid=...">
                    </div>
                    <div class="wg-field">
                        <label>Google Place ID <span style="color:#6c5ce7">(suivi avis)</span></label>
                        <input type="text" name="google_place_id" value="<?php echo esc_attr( $g_place_id ); ?>"
                               placeholder="ChIJxxxxxxxx">
                        <p class="wg-hint">Requis pour le suivi automatique. Trouvez-le sur <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Place ID Finder</a></p>
                    </div>
                </div>
            </div>

            <!-- Récompense — textes -->
            <div class="wg-section">
                <h3>🏆 Page Récompense — Textes</h3>

                <div class="wg-field">
                    <label>Logo de l'entreprise</label>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:4px">
                        <img id="reward-logo-preview"
                             src="<?php echo esc_url( $r_logo ); ?>"
                             alt=""
                             style="<?php echo $r_logo ? '' : 'display:none;'; ?>width:72px;height:72px;object-fit:contain;border:2px solid #e0e4ea;border-radius:8px;background:#f8f9fb">
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <button type="button" id="reward-logo-btn" class="button">Choisir un logo</button>
                            <button type="button" id="reward-logo-remove" class="button"
                                    style="<?php echo $r_logo ? '' : 'display:none;'; ?>color:#e74c3c">Supprimer le logo</button>
                        </div>
                    </div>
                    <input type="hidden" name="reward_logo" id="reward-logo-input" value="<?php echo esc_attr( $r_logo ); ?>">
                    <p class="wg-hint">Affiché en haut de la page récompense. Recommandé : PNG carré transparent, 200×200 px minimum.</p>
                </div>

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
                    <label>Message de réassurance</label>
                    <input type="text" name="reward_urgency" value="<?php echo esc_attr( $r_urg ); ?>">
                    <p class="wg-hint">Affiché sous le bouton Google. Rappelle que le cadeau est inconditionnel.</p>
                </div>
                <div class="wg-field">
                    <label>Note légale (bas de page)</label>
                    <textarea name="reward_footer"><?php echo esc_textarea( $r_foot ); ?></textarea>
                </div>
            </div>

        </div>
        <script>
        (function() {
            var frame;
            var btn     = document.getElementById('reward-logo-btn');
            var remove  = document.getElementById('reward-logo-remove');
            var input   = document.getElementById('reward-logo-input');
            var preview = document.getElementById('reward-logo-preview');
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: 'Choisir le logo', button: { text: 'Utiliser ce logo' }, multiple: false, library: { type: 'image' } });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    input.value         = att.url;
                    preview.src         = att.url;
                    preview.style.display = '';
                    remove.style.display  = '';
                });
                frame.open();
            });
            remove.addEventListener('click', function() {
                input.value           = '';
                preview.src           = '';
                preview.style.display = 'none';
                remove.style.display  = 'none';
            });
        })();
        </script>
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

        // Place ID Google
        if ( isset( $_POST['google_place_id'] ) ) {
            update_post_meta( $post_id, '_google_place_id', sanitize_text_field( wp_unslash( $_POST['google_place_id'] ) ) );
        }

        // Clé API Google (globale)
        if ( isset( $_POST['google_api_key'] ) ) {
            update_option( 'wheel_game_google_api_key', sanitize_text_field( wp_unslash( $_POST['google_api_key'] ) ) );
        }

        // Logo (URL)
        if ( isset( $_POST['reward_logo'] ) ) {
            update_post_meta( $post_id, '_reward_logo', esc_url_raw( wp_unslash( $_POST['reward_logo'] ) ) );
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

        // Cookie serveur (httpOnly, 30 jours) — chemin '/' pour couvrir tout le domaine
        setcookie(
            $cookie_key,
            base64_encode( wp_json_encode( [ 'index' => $prize_index, 'label' => $prize_label ] ) ),
            time() + ( 30 * DAY_IN_SECONDS ),
            '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true   // httpOnly
        );

        wp_send_json_success( [ 'prize_label' => $prize_label ] );
    }

    /* ── Meta box : suivi des avis Google ───────────────────────────────────── */

    public function render_google_tracking_box( $post ) {
        global $wpdb;
        $gtable   = $wpdb->prefix . 'wheel_google_stats';
        $api_key  = get_option( 'wheel_game_google_api_key', '' );
        $place_id = get_post_meta( $post->ID, '_google_place_id', true ) ?: '';

        $latest  = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$gtable} WHERE campaign_id = %d ORDER BY recorded_at DESC LIMIT 1",
            $post->ID
        ) );
        $first   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$gtable} WHERE campaign_id = %d ORDER BY recorded_at ASC LIMIT 1",
            $post->ID
        ) );
        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$gtable} WHERE campaign_id = %d ORDER BY recorded_at DESC",
            $post->ID
        ) );

        $nonce = wp_create_nonce( 'wheel_google' );
        ?>
        <p style="font-size:12px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Clé API Google Places</p>
        <input type="text" name="google_api_key" id="wg-api-key"
               value="<?php echo esc_attr( $api_key ); ?>"
               style="width:100%;font-size:11px;margin-bottom:3px" placeholder="AIzaSy...">
        <p style="font-size:11px;color:#999;margin-bottom:12px">
            Partagée entre toutes les campagnes.
            <a href="https://console.cloud.google.com/apis/library/places-backend.googleapis.com" target="_blank">Activer Places API →</a>
        </p>

        <?php if ( $latest ) : ?>
        <hr style="margin:10px 0">
        <p style="font-size:12px;font-weight:700;color:#666;margin-bottom:8px">
            Dernière mesure — <span style="font-weight:400"><?php echo esc_html( $latest->recorded_at ); ?></span>
        </p>
        <div style="display:flex;gap:8px;margin-bottom:8px;text-align:center">
            <div style="flex:1;background:#f8f9fb;border-radius:8px;padding:8px 4px">
                <div style="font-size:20px;font-weight:800;color:#6c5ce7"><?php echo number_format( (float) $latest->rating, 1 ); ?> ⭐</div>
                <div style="font-size:10px;color:#999;margin-top:2px">Note moy.</div>
            </div>
            <div style="flex:1;background:#f8f9fb;border-radius:8px;padding:8px 4px">
                <div style="font-size:20px;font-weight:800;color:#6c5ce7"><?php echo number_format( (int) $latest->review_count ); ?></div>
                <div style="font-size:10px;color:#999;margin-top:2px">Avis totaux</div>
            </div>
            <?php if ( $first && $first->id !== $latest->id ) :
                $gain = (int) $latest->review_count - (int) $first->review_count;
            ?>
            <div style="flex:1;background:#f0fdf8;border:1px solid #d1fae5;border-radius:8px;padding:8px 4px">
                <div style="font-size:20px;font-weight:800;color:#00b894">+<?php echo $gain; ?></div>
                <div style="font-size:10px;color:#999;margin-top:2px">Depuis début</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( $history ) : ?>
        <hr style="margin:10px 0">
        <div style="display:flex;gap:0;margin-bottom:8px;border:1px solid #ddd;border-radius:6px;overflow:hidden">
            <?php foreach ( [ '7j' => '7 jours', '30j' => '30 jours', 'all' => 'Tout' ] as $tab => $label ) : ?>
            <button type="button" class="wg-tab" data-tab="<?php echo $tab; ?>"
                    style="flex:1;padding:5px 0;font-size:11px;font-weight:600;border:none;cursor:pointer;background:<?php echo $tab === '7j' ? '#6c5ce7' : '#f8f9fb'; ?>;color:<?php echo $tab === '7j' ? '#fff' : '#555'; ?>;transition:background .15s">
                <?php echo $label; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div id="wg-history-wrap" style="max-height:220px;overflow-y:auto">
        <table style="width:100%;font-size:11px;border-collapse:collapse">
            <thead><tr style="color:#aaa;border-bottom:1px solid #eee;background:#fff">
                <th style="text-align:left;padding:3px 0;font-weight:600;position:sticky;top:0;background:#fff">Date</th>
                <th style="text-align:center;padding:3px 0;font-weight:600;position:sticky;top:0;background:#fff">⭐</th>
                <th style="text-align:right;padding:3px 0;font-weight:600;position:sticky;top:0;background:#fff">Avis</th>
                <th style="text-align:right;padding:3px 4px;font-weight:600;position:sticky;top:0;background:#fff">Δ</th>
            </tr></thead>
            <tbody id="wg-history-body">
            <?php foreach ( $history as $k => $row ) :
                $next  = $history[ $k + 1 ] ?? null;
                $delta = $next ? ( (int) $row->review_count - (int) $next->review_count ) : null;
                $dc    = $delta > 0 ? '#00b894' : ( $delta < 0 ? '#e74c3c' : '#bbb' );
            ?>
            <tr data-date="<?php echo esc_attr( $row->recorded_at ); ?>" style="border-bottom:1px solid #f5f5f5">
                <td style="padding:4px 0;color:#555"><?php echo esc_html( $row->recorded_at ); ?></td>
                <td style="text-align:center;padding:4px 0"><?php echo number_format( (float) $row->rating, 1 ); ?></td>
                <td style="text-align:right;padding:4px 0;font-weight:700"><?php echo (int) $row->review_count; ?></td>
                <td style="text-align:right;padding:4px 4px;color:<?php echo $dc; ?>">
                    <?php echo $delta !== null ? ( $delta > 0 ? '+' . $delta : ( $delta === 0 ? '—' : $delta ) ) : '—'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else : ?>
        <p style="font-size:12px;color:#999;margin:10px 0">Aucune donnée — cliquez sur "Actualiser" pour lancer la première mesure.</p>
        <?php endif; ?>

        <hr style="margin:10px 0">
        <button type="button" class="button button-primary button-small" style="width:100%"
                id="wg-fetch-btn" onclick="wgFetchStats(<?php echo $post->ID; ?>, '<?php echo esc_js( $nonce ); ?>')">
            🔄 Actualiser maintenant
        </button>
        <div id="wg-fetch-msg" style="font-size:11px;margin-top:6px;text-align:center;min-height:16px"></div>

        <script>
        (function() {
            // ── Onglets historique ──
            var tabs = document.querySelectorAll('.wg-tab');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    tabs.forEach(function(t) {
                        t.style.background = '#f8f9fb';
                        t.style.color      = '#555';
                    });
                    tab.style.background = '#6c5ce7';
                    tab.style.color      = '#fff';
                    var filter = tab.dataset.tab;
                    var cutoff = null;
                    if (filter !== 'all') {
                        var days   = filter === '7j' ? 7 : 30;
                        var d      = new Date();
                        d.setDate(d.getDate() - days + 1);
                        cutoff = d.toISOString().slice(0, 10);
                    }
                    document.querySelectorAll('#wg-history-body tr').forEach(function(row) {
                        row.style.display = (!cutoff || row.dataset.date >= cutoff) ? '' : 'none';
                    });
                });
            });
            // Appliquer le filtre par défaut (7j)
            tabs[0] && tabs[0].click();
        })();

        function wgFetchStats(campaignId, nonce) {
            var btn = document.getElementById('wg-fetch-btn');
            var msg = document.getElementById('wg-fetch-msg');
            var key = document.getElementById('wg-api-key').value.trim();
            if (!key) { msg.style.color='#e74c3c'; msg.textContent='Renseignez d\'abord la clé API.'; return; }
            btn.disabled = true;
            msg.style.color = '#666';
            msg.textContent = 'Récupération en cours…';
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'wheel_fetch_google_stats', nonce: nonce, campaign_id: campaignId, api_key: key })
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    msg.style.color = '#00b894';
                    msg.textContent = '✓ ' + data.data.rating + ' ⭐ · ' + data.data.review_count + ' avis — rechargement…';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.style.color = '#e74c3c';
                    msg.textContent = '✗ ' + (data.data || 'Erreur inconnue');
                }
            })
            .catch(() => { btn.disabled = false; msg.style.color='#e74c3c'; msg.textContent='Erreur réseau'; });
        }
        </script>
        <?php
    }

    /* ── Google Places API : récupérer note + nombre d'avis ─────────────────── */

    public function fetch_google_stats( $place_id, $api_key ) {
        $url = add_query_arg( [
            'place_id' => $place_id,
            'fields'   => 'rating,user_ratings_total',
            'key'      => $api_key,
        ], 'https://maps.googleapis.com/maps/api/place/details/json' );

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) return null;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ( $body['status'] ?? '' ) !== 'OK' ) return null;

        return [
            'rating'       => round( floatval( $body['result']['rating'] ?? 0 ), 1 ),
            'review_count' => intval( $body['result']['user_ratings_total'] ?? 0 ),
        ];
    }

    /* ── AJAX : actualisation manuelle ──────────────────────────────────────── */

    public function ajax_fetch_google_stats() {
        check_ajax_referer( 'wheel_google', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        $api_key     = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

        if ( ! $campaign_id || ! $api_key ) wp_send_json_error( 'Paramètres manquants' );

        $place_id = get_post_meta( $campaign_id, '_google_place_id', true );
        if ( ! $place_id ) wp_send_json_error( 'Place ID non configuré — sauvegardez la campagne d\'abord.' );

        $stats = $this->fetch_google_stats( $place_id, $api_key );
        if ( ! $stats ) wp_send_json_error( 'Impossible de récupérer les données (clé API ou Place ID invalide ?)' );

        global $wpdb;
        $wpdb->replace( $wpdb->prefix . 'wheel_google_stats', [
            'campaign_id'  => $campaign_id,
            'rating'       => $stats['rating'],
            'review_count' => $stats['review_count'],
            'recorded_at'  => current_time( 'Y-m-d' ),
        ] );

        update_option( 'wheel_game_google_api_key', $api_key );
        wp_send_json_success( $stats );
    }

    public function ajax_save_google_api_key() {
        check_ajax_referer( 'wheel_google', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        update_option( 'wheel_game_google_api_key', sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) );
        wp_send_json_success();
    }

    /* ── Cron quotidien ─────────────────────────────────────────────────────── */

    public function daily_fetch_google_stats() {
        $api_key = get_option( 'wheel_game_google_api_key', '' );
        if ( ! $api_key ) return;

        $ids = get_posts( [
            'post_type'   => 'wheel_campaign',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] );

        global $wpdb;
        $gtable = $wpdb->prefix . 'wheel_google_stats';
        $today  = current_time( 'Y-m-d' );

        foreach ( $ids as $campaign_id ) {
            $place_id = get_post_meta( $campaign_id, '_google_place_id', true );
            if ( ! $place_id ) continue;

            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$gtable} WHERE campaign_id = %d AND recorded_at = %s",
                $campaign_id, $today
            ) );
            if ( $exists ) continue;

            $stats = $this->fetch_google_stats( $place_id, $api_key );
            if ( ! $stats ) continue;

            $wpdb->insert( $gtable, [
                'campaign_id'  => $campaign_id,
                'rating'       => $stats['rating'],
                'review_count' => $stats['review_count'],
                'recorded_at'  => $today,
            ] );
        }
    }
}

Wheel_Game::get_instance();

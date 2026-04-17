<?php
/**
 * Activation, désactivation et mises à jour BDD.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Activator {

    public static function activate() {
        require_once WHEEL_GAME_DIR . 'includes/class-cpt.php';
        ( new Wheel_Game_Cpt() )->register_cpt();
        flush_rewrite_rules();

        self::create_tables();
        update_option( 'wheel_game_db_version', WHEEL_GAME_DB_VERSION );

        if ( ! wp_next_scheduled( 'wheel_daily_google_fetch' ) ) {
            $timestamp = strtotime( 'tomorrow 03:17' );
            wp_schedule_event( $timestamp, 'daily', 'wheel_daily_google_fetch' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'wheel_daily_google_fetch' );
        flush_rewrite_rules();
    }

    public static function maybe_upgrade_db() {
        if ( get_option( 'wheel_game_db_version' ) !== WHEEL_GAME_DB_VERSION ) {
            self::create_tables();
            update_option( 'wheel_game_db_version', WHEEL_GAME_DB_VERSION );
        }
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_plays (
            id             bigint(20)   NOT NULL AUTO_INCREMENT,
            campaign_id    bigint(20)   NOT NULL,
            lead_id        bigint(20)   NOT NULL DEFAULT 0,
            prize_index    tinyint(3)   NOT NULL DEFAULT 0,
            prize_label    varchar(255) NOT NULL DEFAULT '',
            played_at      datetime     NOT NULL,
            ip_hash        varchar(64)  NOT NULL DEFAULT '',
            clicked_google tinyint(1)   NOT NULL DEFAULT 0,
            clicked_at     datetime     NULL,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id),
            KEY ip_hash (ip_hash),
            KEY played_at (played_at),
            KEY lead_id (lead_id)
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

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_leads (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            campaign_id  bigint(20)   NOT NULL,
            first_name   varchar(100) NOT NULL DEFAULT '',
            last_name    varchar(100) NOT NULL DEFAULT '',
            email        varchar(190) NOT NULL DEFAULT '',
            phone        varchar(40)  NOT NULL DEFAULT '',
            consent      tinyint(1)   NOT NULL DEFAULT 0,
            ip_hash      varchar(64)  NOT NULL DEFAULT '',
            user_agent   varchar(255) NOT NULL DEFAULT '',
            created_at   datetime     NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_email (campaign_id, email),
            KEY campaign_id (campaign_id),
            KEY created_at (created_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_cron_log (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            campaign_id  bigint(20)   NOT NULL DEFAULT 0,
            action       varchar(50)  NOT NULL DEFAULT '',
            status       varchar(20)  NOT NULL DEFAULT '',
            message      text,
            created_at   datetime     NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset};" );
    }

    public static function uninstall() {
        global $wpdb;
        $tables = [
            "{$wpdb->prefix}wheel_plays",
            "{$wpdb->prefix}wheel_google_stats",
            "{$wpdb->prefix}wheel_leads",
            "{$wpdb->prefix}wheel_cron_log",
        ];
        foreach ( $tables as $t ) $wpdb->query( "DROP TABLE IF EXISTS {$t}" );

        foreach ( [ 'wheel_game_db_version', 'wheel_game_google_api_key', 'wheel_game_hmac_secret' ] as $o ) {
            delete_option( $o );
        }

        $posts = get_posts( [
            'post_type'   => 'wheel_campaign',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] );
        foreach ( $posts as $pid ) wp_delete_post( $pid, true );

        wp_clear_scheduled_hook( 'wheel_daily_google_fetch' );
    }
}

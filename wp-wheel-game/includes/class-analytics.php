<?php
/**
 * Stats, ROI, notification gros lot.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Analytics {

    public function campaign_stats( $campaign_id ) {
        global $wpdb;
        $p = $wpdb->prefix;

        $plays  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}wheel_plays WHERE campaign_id = %d", $campaign_id ) );
        $leads  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}wheel_leads WHERE campaign_id = %d", $campaign_id ) );
        $clicks = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}wheel_plays WHERE campaign_id = %d AND clicked_google = 1", $campaign_id ) );

        $first = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}wheel_google_stats WHERE campaign_id = %d ORDER BY recorded_at ASC LIMIT 1", $campaign_id ) );
        $last  = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}wheel_google_stats WHERE campaign_id = %d ORDER BY recorded_at DESC LIMIT 1", $campaign_id ) );

        $reviews_gained = 0; $rating_delta = 0.0;
        if ( $first && $last && $first->id !== $last->id ) {
            $reviews_gained = (int) $last->review_count - (int) $first->review_count;
            $rating_delta   = (float) $last->rating - (float) $first->rating;
        }

        return [
            'plays'          => $plays,
            'leads'          => $leads,
            'clicks_google'  => $clicks,
            'click_rate'     => $plays > 0 ? round( $clicks / $plays * 100, 1 ) : 0.0,
            'lead_rate'      => $plays > 0 ? round( $leads / $plays * 100, 1 ) : 0.0,
            'rating_first'   => $first ? (float) $first->rating : null,
            'rating_last'    => $last  ? (float) $last->rating  : null,
            'rating_delta'   => round( $rating_delta, 2 ),
            'reviews_first'  => $first ? (int) $first->review_count : null,
            'reviews_last'   => $last  ? (int) $last->review_count  : null,
            'reviews_gained' => $reviews_gained,
        ];
    }

    public function prize_distribution( $campaign_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT prize_label, COUNT(*) AS cnt FROM {$wpdb->prefix}wheel_plays
             WHERE campaign_id = %d GROUP BY prize_label ORDER BY cnt DESC", $campaign_id ) );
    }

    public function maybe_notify_big_prize( $campaign_id, array $config, $prize_index, $lead_id = 0 ) {
        $threshold = (float) $config['notify_threshold'];
        $email     = $config['notify_email'];
        if ( $threshold <= 0 || ! is_email( $email ) ) return;

        $prize = $config['prizes'][ $prize_index ] ?? null;
        if ( ! $prize ) return;

        $percent = (float) ( $prize['percent'] ?? 100 );
        if ( $percent > $threshold ) return;

        $lead_info = __( '(pas d\'infos)', 'wheel-game' );
        if ( $lead_id ) {
            global $wpdb;
            $lead = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wheel_leads WHERE id = %d", $lead_id ) );
            if ( $lead ) {
                $parts = array_filter( [
                    trim( $lead->first_name . ' ' . $lead->last_name ),
                    $lead->email,
                    $lead->phone,
                ] );
                $lead_info = implode( ' · ', $parts );
            }
        }

        $subject = sprintf(
            __( '🎉 Gros lot gagné sur votre roue — %s', 'wheel-game' ),
            get_the_title( $campaign_id ) );
        $body = sprintf(
            "%s\n\n%s : %s %s %s (%.2f%%)\n%s : %s\n\n%s : %s",
            __( 'Un participant vient de gagner un gros lot sur votre roue !', 'wheel-game' ),
            __( 'Prix', 'wheel-game' ),
            $prize['emoji'] ?? '', $prize['line1'] ?? '', $prize['line2'] ?? '',
            $percent,
            __( 'Participant', 'wheel-game' ), $lead_info,
            __( 'Voir la campagne', 'wheel-game' ), get_edit_post_link( $campaign_id, '' )
        );
        wp_mail( $email, $subject, $body );
    }
}

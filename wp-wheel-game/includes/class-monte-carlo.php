<?php
/**
 * Simulation Monte-Carlo.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Wheel_Game_Monte_Carlo {

    public static function simulate( array $prizes, $iterations = 10000 ) {
        $weights = [];
        $eligible_total = 0;
        foreach ( $prizes as $p ) {
            $w = max( 0, floatval( $p['percent'] ?? 0 ) );
            $weights[] = $w;
            $eligible_total += $w;
        }

        if ( $eligible_total <= 0 ) {
            return [
                'results'    => array_map( fn( $p ) => [
                    'label' => trim( ( $p['emoji'] ?? '' ) . ' ' . ( $p['line1'] ?? '' ) ),
                    'color' => $p['color'] ?? '#6c5ce7',
                    'percent_set' => 0, 'theoretical' => 0, 'observed' => 0, 'count' => 0,
                ], $prizes ),
                'iterations' => 0,
                'error' => 'Toutes les probabilités sont à 0',
            ];
        }

        $counts = array_fill( 0, count( $prizes ), 0 );
        for ( $i = 0; $i < $iterations; $i++ ) {
            $rand = mt_rand() / mt_getrandmax() * $eligible_total;
            $acc = 0;
            foreach ( $weights as $idx => $w ) {
                $acc += $w;
                if ( $rand <= $acc ) { $counts[ $idx ]++; break; }
            }
        }

        $results = [];
        foreach ( $prizes as $i => $p ) {
            $theoretical = $eligible_total > 0 ? ( $weights[ $i ] / $eligible_total * 100 ) : 0;
            $observed    = $iterations > 0 ? ( $counts[ $i ] / $iterations * 100 ) : 0;
            $results[] = [
                'label'       => trim( ( $p['emoji'] ?? '' ) . ' ' . ( $p['line1'] ?? '' ) . ' ' . ( $p['line2'] ?? '' ) ),
                'color'       => $p['color'] ?? '#6c5ce7',
                'percent_set' => round( (float) ( $p['percent'] ?? 0 ), 2 ),
                'theoretical' => round( $theoretical, 2 ),
                'observed'    => round( $observed, 2 ),
                'count'       => $counts[ $i ],
            ];
        }

        return [ 'results' => $results, 'iterations' => $iterations ];
    }
}

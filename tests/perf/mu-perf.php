<?php
/**
 * Plugin Name: VergeML perf probe
 * Description: Reports what a REST request actually cost, separated from the boot that surrounds it.
 *
 * Wall-clock HTTP timing is useless in Playground: PHP-wasm spends ~2.5s booting
 * WordPress on every request, so core's own endpoints time the same as ours and
 * every number drowns in the constant.
 *
 * The constant is bootstrap. So measure the handler alone, and -- more usefully --
 * count the queries it runs. Query count is a property of the algorithm, not of the
 * machine: it is the same integer in Playground and on real MariaDB. That makes it
 * the one performance figure Playground can report truthfully.
 *
 * Drop into wp-content/mu-plugins/. Test-box only; never ships in the plugin.
 */

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		$GLOBALS['vgml_perf'] = array(
			'start'   => microtime( true ),
			'queries' => get_num_queries(),
		);
		return $result;
	},
	0,
	3
);

add_filter(
	'rest_post_dispatch',
	function ( $response, $server, $request ) {
		if ( empty( $GLOBALS['vgml_perf'] ) || ! is_object( $response ) ) {
			return $response;
		}

		global $wpdb;
		$p       = $GLOBALS['vgml_perf'];
		$handler = ( microtime( true ) - $p['start'] ) * 1000;
		$count   = get_num_queries() - $p['queries'];

		// Time actually spent in the database, for the handler's queries only.
		$db = 0.0;
		if ( ! empty( $wpdb->queries ) ) {
			foreach ( array_slice( $wpdb->queries, $p['queries'] ) as $q ) {
				$db += (float) $q[1];
			}
		}

		$boot = defined( 'WP_START_TIMESTAMP' )
			? ( $p['start'] - WP_START_TIMESTAMP ) * 1000
			: ( $p['start'] - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000;

		$response->header( 'X-Vgml-Queries', (string) $count );
		$response->header( 'X-Vgml-Handler-Ms', (string) round( $handler, 2 ) );
		$response->header( 'X-Vgml-Db-Ms', (string) round( $db * 1000, 2 ) );
		$response->header( 'X-Vgml-Boot-Ms', (string) round( $boot, 2 ) );

		return $response;
	},
	PHP_INT_MAX,
	3
);

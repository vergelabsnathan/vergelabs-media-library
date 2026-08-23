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

/*
 *  Admin pages, not just REST.
 *
 *  Competitors do not all expose a tree endpoint -- FileBird builds its tree into the
 *  media page itself. Comparing our endpoint against their nothing would flatter us
 *  and tell us nothing, so measure what a user actually waits for: the cost of the
 *  media library screen with each plugin active. One line per request, appended to
 *  wp-content/perf-admin.log.
 */
add_action(
	'shutdown',
	function () {
		if ( ! is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( strpos( $uri, 'upload.php' ) === false ) {
			return;
		}
		$ms     = ( microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000;
		$active = implode(
			',',
			array_map(
				function ( $p ) {
					$slash = strpos( $p, '/' );
					return false === $slash ? $p : substr( $p, 0, $slash );
				},
				(array) get_option( 'active_plugins', array() )
			)
		);
		error_log(
			sprintf(
				"%s\tqueries=%d\tms=%.1f\tmem=%.1fMB\tactive=%s\n",
				$uri,
				get_num_queries(),
				$ms,
				memory_get_peak_usage( true ) / 1048576,
				$active
			),
			3,
			WP_CONTENT_DIR . '/perf-admin.log'
		);
	},
	PHP_INT_MAX
);

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

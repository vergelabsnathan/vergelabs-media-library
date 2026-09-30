<?php
/**
 *  What a wordpress.org reviewer flags that PHPCS does not.
 *
 *  WordPress.Security.EscapeOutput reads the echo, not the string it echoes.
 *  `$html .= '<h4>' . __( 'Settings' ) . '</h4>'; ... echo $html;` is one
 *  finding on the echo line -- and none at all once that echo carries a
 *  phpcs:ignore. The review team quotes the line that builds the markup, so
 *  this reads every concatenation that has a markup literal in it and names
 *  each part that is neither a literal nor passed through an escaper.
 *
 *      php markup-scan.php <plugin-root>     one finding per line, then counts
 *
 *  Codes:
 *    Translation  a __() / _x() / _n() / _nx() result concatenated into markup,
 *                 or appended with .= , without esc_html__ / esc_attr__ / esc_*()
 *    Value        a variable, property, method or function result concatenated
 *                 into markup without an escaper, a cast or a known-safe call
 *    InlineTag    a <script> or <style> tag written by PHP instead of enqueued
 *                 (a <script type="text/html"> wp.template is markup, not script,
 *                 and is how core prints its own media templates)
 *
 *  Reads text; it decides nothing it cannot see. A value escaped where it was
 *  assigned is still reported where it is concatenated -- escape late.
 */

$root  = rtrim( $argv[1] ?? '.', '/' );
$found = array();

$escapers = array_flip( array(
	'esc_html', 'esc_attr', 'esc_url', 'esc_textarea', 'esc_js', 'esc_xml', 'esc_sql',
	'esc_html__', 'esc_attr__', 'esc_html_x', 'esc_attr_x', 'esc_html_e', 'esc_attr_e',
	'wp_kses', 'wp_kses_post', 'wp_kses_data', 'wp_json_encode', 'json_encode',
	'absint', 'intval', 'floatval', 'boolval', 'count', 'number_format_i18n', 'number_format',
	'checked', 'selected', 'disabled', 'readonly', 'wp_readonly', 'sanitize_html_class', 'sanitize_key', 'tag_escape',
	'wp_create_nonce', 'wp_nonce_field', 'get_submit_button', 'submit_button', 'wp_referer_field',
	'size_format', 'human_time_diff', 'esc_url_raw', 'rawurlencode', 'urlencode', 'md5', 'sha1',
	'min', 'max', 'round', 'ceil', 'floor', 'abs', 'strlen', 'mb_strlen',
) );

// Not output: a value built here goes to a log, a JSON reply or an exception.
$silent = array_flip( array(
	'error_log', 'wp_send_json', 'wp_send_json_success', 'wp_send_json_error', 'wp_error',
	'exception', 'runtimeexception', 'invalidargumentexception', 'trigger_error', '_doing_it_wrong',
	'__', '_x', '_n', '_nx', 'esc_html__', 'esc_attr__', 'esc_html_x', 'esc_attr_x', 'wp_die_silent',
	'wpdb_prepare', 'prepare', 'query', 'get_var', 'get_results', 'get_col', 'get_row', 'hash', 'md5', 'sha1',
	'set_transient', 'update_option', 'add_option', 'update_post_meta', 'update_term_meta', 'wp_cache_set',
	'wp_remote_post', 'wp_remote_get', 'wp_safe_remote_post', 'wp_safe_remote_get', 'wp_redirect', 'wp_safe_redirect',
	'add_query_arg', 'remove_query_arg', 'admin_url', 'home_url', 'site_url', 'plugins_url', 'network_admin_url',
	'str_replace', 'preg_replace', 'preg_match', 'strpos', 'explode', 'implode_silent', 'substr', 'in_array', 'array_key_exists',
	'wp_add_inline_script', 'wp_add_inline_style', 'wp_localize_script', 'apply_filters', 'do_action', 'define', 'setcookie',
) );

$translations = array_flip( array( '__', '_x', '_n', '_nx' ) );

$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

foreach ( $iter as $file ) {

	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$rel = ltrim( substr( $file->getPathname(), strlen( $root ) ), '/\\' );
	$src = file_get_contents( $file->getPathname() );
	$raw = token_get_all( $src );

	// Line numbers for single-character tokens, which token_get_all leaves bare.
	$toks = array();
	$line = 1;
	foreach ( $raw as $t ) {
		if ( is_array( $t ) ) {
			$toks[] = array( $t[0], $t[1], $t[2] );
			$line   = $t[2] + substr_count( $t[1], "\n" );
		} else {
			$toks[] = array( $t, $t, $line );
		}
	}

	foreach ( $toks as $t ) {
		// A whole tag, so the '<script ' a script_loader_tag filter rewrites is not one; a text/html template is markup for wp.template, not script.
		if ( ( T_INLINE_HTML === $t[0] || T_CONSTANT_ENCAPSED_STRING === $t[0] || T_ENCAPSED_AND_WHITESPACE === $t[0] ) && preg_match_all( '/<(script|style)\b([^>]*)>/i', $t[1], $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				if ( ! preg_match( '#type=["\']text/(html|template)["\']#i', $m[2] ) ) {
					$found[] = array( $rel, $t[2], 'InlineTag', '<' . strtolower( $m[1] ) . '>' );
				}
			}
		}
	}

	$sig = array();
	foreach ( $toks as $i => $t ) {
		if ( in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		$sig[] = $t;
	}

	/*
	 *  A frame per bracket. Each holds the expression being read at that depth
	 *  and the name of the function whose arguments it is, if any.
	 */
	$frames = array( array( 'fn' => null, 'items' => array(), 'concat_eq' => false ) );

	$evaluate = function ( $frame, $ancestors ) use ( &$found, $rel, $escapers, $silent, $translations ) {

		foreach ( $ancestors as $fn ) {
			if ( null !== $fn && ( isset( $escapers[ $fn ] ) || isset( $silent[ $fn ] ) ) ) {
				return;
			}
		}

		$items = $frame['items'];
		$has_concat = false;
		foreach ( $items as $it ) {
			if ( 'op' === $it['kind'] ) {
				$has_concat = true;
			}
		}
		if ( ! $has_concat && ! $frame['concat_eq'] ) {
			return;
		}

		// Split into operands at the concatenation operators.
		$operands = array( array() );
		foreach ( $items as $it ) {
			if ( 'op' === $it['kind'] ) {
				$operands[] = array();
			} else {
				$operands[ count( $operands ) - 1 ][] = $it;
			}
		}

		$markup = false;
		foreach ( $operands as $op ) {
			foreach ( $op as $it ) {
				if ( 'lit' === $it['kind'] && preg_match( '#<[/!]?[a-z]#i', $it['text'] ) ) {
					$markup = true;
				}
			}
		}

		foreach ( $operands as $op ) {

			if ( ! $op ) {
				continue;
			}

			$first = $op[0];

			if ( 'lit' === $first['kind'] || 'cast' === $first['kind'] || 'const' === $first['kind'] ) {
				continue;
			}

			if ( 'call' === $first['kind'] && isset( $translations[ $first['fn'] ] ) ) {
				if ( $markup || $frame['concat_eq'] ) {
					$found[] = array( $rel, $first['line'], 'Translation', $first['fn'] . '()' );
				}
				continue;
			}

			if ( 'call' === $first['kind'] && ( isset( $escapers[ $first['fn'] ] ) ) ) {
				continue;
			}

			if ( ! $markup ) {
				continue;
			}

			$found[] = array( $rel, $first['line'], 'Value', $first['text'] );
		}
	};

	$delims = array( ';', ',', '=', '?', ':', T_DOUBLE_ARROW, T_RETURN, T_ECHO, T_PRINT, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG,
		T_COALESCE, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL,
		'<', '>', T_IS_SMALLER_OR_EQUAL, T_IS_GREATER_OR_EQUAL, T_PLUS_EQUAL, T_MINUS_EQUAL, T_COALESCE_EQUAL, T_AS, T_CASE, T_THROW, T_NEW, '!', '+', '-', '*', '/', '%' );

	$casts = array( T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST );

	$in_string = false;
	$n = count( $sig );

	for ( $i = 0; $i < $n; $i++ ) {

		$t   = $sig[ $i ];
		$top = count( $frames ) - 1;

		// An interpolated string is one operand; any variable inside it is a value.
		if ( '"' === $t[0] || T_START_HEREDOC === $t[0] ) {
			$end   = '"' === $t[0] ? '"' : T_END_HEREDOC;
			$text  = '';
			$vars  = false;
			$line  = $t[2];
			for ( $i++; $i < $n && $sig[ $i ][0] !== $end; $i++ ) {
				$text .= $sig[ $i ][1];
				if ( T_VARIABLE === $sig[ $i ][0] ) {
					$vars = true;
				}
			}
			$frames[ $top ]['items'][] = $vars ? array( 'kind' => 'value', 'text' => '"' . substr( $text, 0, 40 ) . '"', 'line' => $line ) : array( 'kind' => 'lit', 'text' => $text, 'line' => $line );
			if ( $vars && preg_match( '#<[/!]?[a-z]#i', $text ) ) {
				// Markup and a variable in one string: the string is its own concatenation.
				$found[] = array( $rel, $line, 'Value', '"' . substr( trim( $text ), 0, 40 ) . '"' );
			}
			continue;
		}

		if ( '(' === $t[0] || '[' === $t[0] || '{' === $t[0] || T_CURLY_OPEN === $t[0] || T_DOLLAR_OPEN_CURLY_BRACES === $t[0] ) {
			$fn   = null;
			$prev = $sig[ $i - 1 ] ?? null;
			if ( '(' === $t[0] && $prev && T_STRING === $prev[0] ) {
				$fn = strtolower( $prev[1] );
			}
			if ( '(' === $t[0] && $prev && in_array( $prev[0], array( T_ISSET, T_EMPTY, T_ARRAY, T_LIST, T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH, T_FUNCTION, T_FN, T_USE, T_CATCH ), true ) ) {
				$fn = '#' . token_name( $prev[0] );
			}
			$frames[] = array( 'fn' => $fn, 'items' => array(), 'concat_eq' => false, 'open' => $t[0], 'line' => $t[2] );
			continue;
		}

		if ( ')' === $t[0] || ']' === $t[0] || '}' === $t[0] ) {
			if ( $top > 0 ) {
				$frame     = array_pop( $frames );
				$ancestors = array_map( function ( $f ) { return $f['fn']; }, $frames );
				$ancestors[] = $frame['fn'];
				$evaluate( $frame, $ancestors );
				$top = count( $frames ) - 1;
				if ( ')' === $t[0] ) {
					$last = end( $frames[ $top ]['items'] );
					// The call replaces the name token pushed just before it.
					if ( $last && 'name' === $last['kind'] ) {
						array_pop( $frames[ $top ]['items'] );
						$frames[ $top ]['items'][] = array( 'kind' => 'call', 'fn' => strtolower( $last['text'] ), 'text' => $last['text'] . '()', 'line' => $last['line'] );
					} elseif ( ! $last || 'op' === $last['kind'] || ( 'cast' === $last['kind'] ) ) {
						$frames[ $top ]['items'][] = array( 'kind' => 'value', 'text' => '( ... )', 'line' => $frame['line'] );
					}
				}
				if ( '}' === $t[0] ) {
					$frames[ $top ]['items'] = array();
					$frames[ $top ]['concat_eq'] = false;
				}
			}
			continue;
		}

		if ( '.' === $t[0] ) {
			$frames[ $top ]['items'][] = array( 'kind' => 'op', 'line' => $t[2] );
			continue;
		}

		if ( T_CONCAT_EQUAL === $t[0] ) {
			$ancestors = array_map( function ( $f ) { return $f['fn']; }, $frames );
			$evaluate( $frames[ $top ], $ancestors );
			$frames[ $top ]['items']     = array();
			$frames[ $top ]['concat_eq'] = true;
			continue;
		}

		if ( in_array( $t[0], $delims, true ) ) {
			$ancestors = array_map( function ( $f ) { return $f['fn']; }, $frames );
			$evaluate( $frames[ $top ], $ancestors );
			$frames[ $top ]['items']     = array();
			$frames[ $top ]['concat_eq'] = false;
			continue;
		}

		$items = &$frames[ $top ]['items'];
		$last  = $items ? $items[ count( $items ) - 1 ] : null;
		$fresh = ! $last || 'op' === $last['kind'];

		if ( T_CONSTANT_ENCAPSED_STRING === $t[0] || T_LNUMBER === $t[0] || T_DNUMBER === $t[0] ) {
			if ( $fresh ) {
				$items[] = array( 'kind' => 'lit', 'text' => $t[1], 'line' => $t[2] );
			}
		} elseif ( in_array( $t[0], $casts, true ) ) {
			if ( $fresh ) {
				$items[] = array( 'kind' => 'cast', 'text' => $t[1], 'line' => $t[2] );
			}
		} elseif ( T_STRING === $t[0] ) {
			$next = $sig[ $i + 1 ] ?? null;
			if ( $fresh ) {
				if ( $next && '(' === $next[0] ) {
					$items[] = array( 'kind' => 'name', 'text' => $t[1], 'line' => $t[2] );
				} elseif ( $next && T_DOUBLE_COLON === $next[0] ) {
					$items[] = array( 'kind' => 'value', 'text' => $t[1] . '::', 'line' => $t[2] );
				} else {
					$items[] = array( 'kind' => 'const', 'text' => $t[1], 'line' => $t[2] );
				}
			}
		} elseif ( T_UNSET_CAST !== $t[0] ) {
			if ( $fresh ) {
				$items[] = array( 'kind' => 'value', 'text' => $t[1], 'line' => $t[2] );
			}
		}
		unset( $items );
	}
}

usort( $found, function ( $a, $b ) { return array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ); } );

$counts = array();
foreach ( $found as $f ) {
	echo $f[0] . ':' . $f[1] . "\tVergeML.Markup." . $f[2] . "\t" . $f[3] . "\n";
	$counts[ $f[2] ] = ( $counts[ $f[2] ] ?? 0 ) + 1;
}
echo "\nVergeML.Markup totals:";
foreach ( array( 'Translation', 'Value', 'InlineTag' ) as $k ) {
	echo ' ' . $k . '=' . ( $counts[ $k ] ?? 0 );
}
echo "\n";

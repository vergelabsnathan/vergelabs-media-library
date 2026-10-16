<?php
/**
 *  Every function and class the shipped PHP calls, checked on the oldest PHP the
 *  header claims (run on WP 6.5 / PHP 7.4 by tools/runtime-checks.mjs).
 *
 *  Round 7 (2026-10-15) rejected str_contains() under "Requires PHP: 7.4". It
 *  never failed here because WordPress 5.9+ polyfills it in wp-includes/compat.php,
 *  so a run on PHP 7.4 cannot go red on it; the reviewer reads the header. This
 *  fails on any call that only exists through such a polyfill, and on any call
 *  that does not exist at all on this PHP. Extension functions and classes
 *  (mbstring, zip, gd, openssl, ...) are listed with whether the same file checks
 *  for them, and fail when it does not.
 */

require __DIR__ . '/common.php';

$dir = WP_PLUGIN_DIR . '/vergelabs-media-library';

$files = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( 'php' === $f->getExtension() ) {
		$files[] = $f->getPathname();
	}
}
sort( $files );

$defined   = get_defined_functions();
$internal  = array_flip( array_map( 'strtolower', $defined['internal'] ) );
$polyfills = array();
foreach ( array( 'wp-includes/compat.php' ) as $compat ) {
	if ( preg_match_all( '/^\s*function\s+(\w+)\s*\(/m', (string) file_get_contents( ABSPATH . $compat ), $m ) ) {
		foreach ( $m[1] as $name ) {
			$polyfills[ strtolower( $name ) ] = $compat;
		}
	}
}

// Every function WordPress declares, admin and multisite included: a front-end request loads only part of them.
$wp_fn = array();
foreach ( array( 'wp-includes', 'wp-admin' ) as $wp_dir ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ABSPATH . $wp_dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
		if ( 'php' === $f->getExtension() && preg_match_all( '/^\s*function\s+&?\s*(\w+)\s*\(/m', (string) file_get_contents( $f->getPathname() ), $m ) ) {
			foreach ( $m[1] as $name ) {
				$wp_fn[ strtolower( $name ) ] = true;
			}
		}
	}
}

// Built into every PHP 7.4 (core, standard, date, pcre, spl, reflection, hash, json) or the database WordPress
// itself needs. ctype, SimpleXML, DOM, XML, filter, zlib and the rest can be compiled out, and Site Health
// lists them as optional (round 7 review: simplexml_load_file() unchecked).
$base = array_flip( array( 'core', 'standard', 'date', 'pcre', 'spl', 'reflection', 'hash', 'json', 'random', 'mysqli', 'mysqlnd', 'pdo', 'sqlite3', 'pdo_sqlite' ) );

/*
 *  Files the main file includes inside `if ( ! vergeml_features_off() ) {`, which is
 *  false without mbstring (vergeml_requirements_missing()): mbstring is checked
 *  for them once, centrally. The rest -- the main file, uninstall.php and what
 *  loads before the gate -- must check for themselves.
 */
$gated = array();
$main  = (string) file_get_contents( $dir . '/vergelabs-media-library.php' );
$def   = strpos( $main, 'function vergeml_features_off' );
$open  = false === $def ? false : strpos( $main, 'if ( ! vergeml_features_off() ) {', $def );
if ( false !== $open ) {
	$depth = 0;
	$at    = strpos( $main, '{', $open );
	for ( $i = $at, $len = strlen( $main ); $i < $len; $i++ ) {
		if ( '{' === $main[ $i ] ) {
			$depth++;
		} elseif ( '}' === $main[ $i ] && 0 === --$depth ) {
			break;
		}
	}
	if ( preg_match_all( "/'(core\/[\w-]+\.php)'/", substr( $main, $at, $i - $at ), $m ) ) {
		$gated = array_flip( $m[1] );
	}
}
rt_t( 'the features-off gate was found and holds the feature files', count( $gated ) > 30, count( $gated ) . ' files' );

$tokens_of = array();
$own_fn    = array();
$own_class = array();
foreach ( $files as $file ) {
	$tokens = token_get_all( (string) file_get_contents( $file ) );
	$tokens_of[ $file ] = $tokens;
	$n = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], array( T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT ), true ) ) {
			for ( $j = $i + 1; $j < $n && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE ), true ); $j++ );
			if ( isset( $tokens[ $j ] ) && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				if ( T_FUNCTION === $tokens[ $i ][0] ) {
					$own_fn[ strtolower( $tokens[ $j ][1] ) ] = true;
				} else {
					$own_class[ strtolower( $tokens[ $j ][1] ) ] = true;
				}
			}
		}
	}
}

$fails = array();
$exts  = array();
$calls = 0;

foreach ( $tokens_of as $file => $tokens ) {
	$rel  = substr( $file, strlen( $dir ) + 1 );
	$text = strtolower( (string) file_get_contents( $file ) );
	$n    = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] ) {
			continue;
		}
		$prev = null;
		for ( $p = $i - 1; $p >= 0; $p-- ) {
			if ( is_array( $tokens[ $p ] ) && in_array( $tokens[ $p ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_NS_SEPARATOR ), true ) ) {
				continue;
			}
			$prev = $tokens[ $p ];
			break;
		}
		$next = null;
		for ( $q = $i + 1; $q < $n; $q++ ) {
			if ( is_array( $tokens[ $q ] ) && in_array( $tokens[ $q ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$next = $tokens[ $q ];
			break;
		}
		$name  = strtolower( $tokens[ $i ][1] );
		$line  = $tokens[ $i ][2];
		$after = is_array( $prev ) ? $prev[0] : $prev;

		$is_class = ( T_NEW === $after ) || ( T_DOUBLE_COLON === ( is_array( $next ) ? $next[0] : $next ) ) || ( T_INSTANCEOF === $after );
		$is_call  = '(' === $next && ! in_array( $after, array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST ), true );

		if ( $is_class ) {
			if ( in_array( $name, array( 'self', 'static', 'parent' ), true ) || isset( $own_class[ $name ] ) ) {
				continue;
			}
			// \Elementor\Controls_Manager: another plugin's namespace, fine when the file checks that plugin's classes first.
			$root = null;
			for ( $r = $i - 1; $r >= 0 && is_array( $tokens[ $r ] ) && in_array( $tokens[ $r ][0], array( T_NS_SEPARATOR, T_STRING ), true ); $r-- ) {
				if ( T_STRING === $tokens[ $r ][0] ) {
					$root = strtolower( $tokens[ $r ][1] );
				}
			}
			if ( null !== $root && false !== strpos( $text, "class_exists( '\\" . $root . "\\" ) ) {
				continue;
			}
			if ( ! class_exists( $name ) && ! interface_exists( $name ) ) {
				// WordPress loads some of its own classes on demand (WP_List_Table, WP_Filesystem_*).
				if ( 0 === strpos( $name, 'wp_' ) || 0 === strpos( $name, 'walker' ) ) {
					continue;
				}
				$fails[] = "$rel:$line class $name does not exist on PHP " . PHP_VERSION;
				continue;
			}
			$ref = new ReflectionClass( $name );
			$ext = strtolower( (string) $ref->getExtensionName() );
			if ( $ref->isInternal() && '' !== $ext && ! isset( $base[ $ext ] ) ) {
				$exts[ "$ext $name" ][] = array( $rel, $line, $ext );
			}
			continue;
		}

		if ( ! $is_call || isset( $own_fn[ $name ] ) ) {
			continue;
		}
		if ( in_array( $name, array( 'isset', 'empty', 'unset', 'list', 'array', 'exit', 'die', 'eval', 'include', 'require', 'echo', 'print' ), true ) ) {
			continue;
		}
		$calls++;

		if ( ! isset( $internal[ $name ] ) && isset( $polyfills[ $name ] ) ) {
			$fails[] = "$rel:$line $name() is not in PHP " . PHP_VERSION . '; it exists only through ' . $polyfills[ $name ];
			continue;
		}
		if ( ! isset( $internal[ $name ] ) ) {
			// Another plugin's function (a page builder's) is fine behind function_exists() in the same file.
			if ( ! isset( $wp_fn[ $name ] ) && false === strpos( $text, "function_exists( '$name'" ) ) {
				$fails[] = "$rel:$line $name() is neither PHP's, WordPress's nor this plugin's, and is not behind function_exists()";
			}
			continue;
		}
		$ext = strtolower( (string) ( new ReflectionFunction( $name ) )->getExtensionName() );
		if ( '' !== $ext && ! isset( $base[ $ext ] ) ) {
			$exts[ "$ext $name()" ][] = array( $rel, $line, $ext );
		}
	}
}

// Checked when the file tests for the extension, or for any of its functions or classes before use.
$guarded = function ( $site ) use ( $dir, $gated ) {
	list( $rel, , $ext ) = $site;
	if ( 'mbstring' === $ext && isset( $gated[ $rel ] ) ) {
		return true;
	}
	$text = strtolower( (string) file_get_contents( $dir . '/' . $rel ) );
	if ( false !== strpos( $text, "extension_loaded( '$ext'" ) ) {
		return true;
	}
	if ( preg_match_all( "/(?:function|class)_exists\(\s*'(\w+)'/", $text, $m ) ) {
		foreach ( $m[1] as $sym ) {
			if ( function_exists( $sym ) && strtolower( (string) ( new ReflectionFunction( $sym ) )->getExtensionName() ) === $ext ) {
				return true;
			}
			if ( class_exists( $sym, false ) && strtolower( (string) ( new ReflectionClass( $sym ) )->getExtensionName() ) === $ext ) {
				return true;
			}
		}
	}
	return false;
};

rt_t( count( $files ) . ' shipped PHP files read, ' . $calls . ' function calls checked', count( $files ) > 0 && $calls > 100 );
rt_t( 'no call exists only through a WordPress polyfill or not at all on PHP ' . PHP_VERSION, array() === $fails, "\n      " . implode( "\n      ", $fails ) );

ksort( $exts );
foreach ( $exts as $what => $sites ) {
	$unguarded = array_filter( $sites, function ( $s ) use ( $guarded ) { return ! $guarded( $s ); } );
	$where     = array_map( function ( $s ) { return $s[0] . ':' . $s[1]; }, $unguarded );
	rt_t( "extension $what: checked in every file that calls it (" . count( $sites ) . ' calls)', array() === $unguarded, implode( ', ', array_slice( $where, 0, 8 ) ) );
}

rt_done( 'scan' );

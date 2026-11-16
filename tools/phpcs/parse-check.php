<?php
/**
 *  Every shipped PHP file parsed by the PHP that runs this, the way php -l
 *  would: token_get_all with TOKEN_PARSE throws on the first syntax error.
 *
 *      php parse-check.php <plugin-root>
 */

$root   = rtrim( $argv[1] ?? '.', '/' );
$files  = 0;
$broken = 0;

$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

foreach ( $iter as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$files++;
	try {
		token_get_all( file_get_contents( $file->getPathname() ), TOKEN_PARSE );
	} catch ( ParseError $e ) {
		$broken++;
		echo 'PARSE ERROR ' . ltrim( substr( $file->getPathname(), strlen( $root ) ), '/' ) . ':' . $e->getLine() . ' ' . $e->getMessage() . "\n";
	}
}

echo 'parse: ' . $files . ' files, ' . $broken . ' with a syntax error, PHP ' . PHP_VERSION . "\n";

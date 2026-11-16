<?php
/**
 *  The duplicate finder's picture hash on a real image, with no deprecation: round 7 deeper pass
 *  (2026-10-18) replaced imagedestroy(), deprecated in PHP 8.5, by dropping the last reference.
 *  Run on PHP 7.4 (GD resources) and 8.5 (GdImage objects).
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'activate':
		rt_quiet( 'activation', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_done( 'activate' );
		break;

	case 'hash':
		rt_t( 'GD is here to hash with', function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' ) );
		$up = wp_upload_dir();
		wp_mkdir_p( $up['path'] );
		$path = trailingslashit( $up['path'] ) . 'hash-me.png';
		$img  = imagecreatetruecolor( 40, 30 );
		for ( $x = 0; $x < 40; $x++ ) {
			imagefilledrectangle( $img, $x, 0, $x, 29, imagecolorallocate( $img, $x * 6, 255 - $x * 6, 128 ) );
		}
		imagepng( $img, $path );
		$img = null;

		$hashes  = array();
		$watched = rt_watch( function () use ( $path, &$hashes ) {
			for ( $i = 0; $i < 50; $i++ ) {
				$hashes[] = vergeml_health_dhash( $path );
			}
			return true;
		} );
		rt_quiet( 'hashing one picture fifty times', $watched );
		rt_t( 'the hash is 16 hex characters', 1 === preg_match( '/^[0-9a-f]{16}$/', (string) $hashes[0] ), (string) $hashes[0] );
		rt_t( 'the same picture hashes the same every time', 1 === count( array_unique( $hashes ) ) );
		rt_done( 'hash' );
		break;
}

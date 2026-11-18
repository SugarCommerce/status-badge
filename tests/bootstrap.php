<?php
/**
 * PHPUnit bootstrap.
 *
 * This is a library, not a plugin: there is no WordPress to load. Styles
 * registered and enqueued are recorded rather than performed, so a test
 * can ask what the library asked WordPress for.
 *
 * @package SugarCommerce\StatusBadge
 */

declare( strict_types=1 );

define( 'ABSPATH', '/srv/www/' );
define( 'WP_CONTENT_DIR', '/srv/www/wp-content' );

$GLOBALS['sb_registered'] = [];
$GLOBALS['sb_enqueued']   = [];

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
	$GLOBALS['sb_registered'][ $handle ] = [
		'src'  => $src,
		'deps' => $deps,
		'ver'  => $ver,
	];

	return true;
}

function wp_style_is( $handle, $status = 'enqueued' ) {
	return 'registered' === $status ? isset( $GLOBALS['sb_registered'][ $handle ] ) : in_array( $handle, $GLOBALS['sb_enqueued'], true );
}

function wp_enqueue_style( $handle, ...$args ) {
	$GLOBALS['sb_enqueued'][] = $handle;
}

function wp_normalize_path( $path ) {
	return (string) preg_replace( '#/+#', '/', str_replace( '\\', '/', (string) $path ) );
}

function site_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function content_url( $path = '' ) {
	return 'https://example.test/wp-content' . $path;
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

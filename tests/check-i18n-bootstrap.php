<?php
/**
 * Runtime harness for the i18n bootstrap. Stubs the WordPress API the plugin
 * touches while loading, then exercises the textdomain loader, the
 * plugin_locale mapping and the RTL filters.
 *
 * Usage: php tests/check-i18n-bootstrap.php [plugin-root]
 */

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__ );

$GLOBALS['hooks']            = array();
$GLOBALS['filters']          = array();
$GLOBALS['locale']           = 'en_US';
$GLOBALS['textdomain_calls'] = array();
$failures                    = 0;

/**
 * Prints the outcome of one assertion and tracks failures.
 *
 * @param string $label  What is being asserted.
 * @param bool   $ok     Result.
 * @param string $detail Optional actual value.
 *
 * @return void
 */
function check( $label, $ok, $detail = '' ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
	}
	echo ( $ok ? '✅ ' : '❌ ' ) . $label . ( '' !== $detail ? " ({$detail})" : '' ) . "\n";
}

define( 'ABSPATH', '/tmp/wp/' );
define( 'DOING_CRON', false );

// phpcs:disable
function plugin_basename( $file ) {
	// Simulate a git clone landing in a folder that is not the plugin slug.
	return 'wordpressiccllmfiles/' . basename( $file );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['filters'][ $hook ][ $priority ][] = array( $callback, $args );
	return true;
}

function apply_filters( $hook, $value, ...$rest ) {
	if ( empty( $GLOBALS['filters'][ $hook ] ) ) {
		return $value;
	}
	ksort( $GLOBALS['filters'][ $hook ] );
	foreach ( $GLOBALS['filters'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$args  = array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] );
			$value = call_user_func_array( $entry[0], $args );
		}
	}
	return $value;
}

function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
	$GLOBALS['textdomain_calls'][] = array( $domain, $deprecated, $path );
	return true;
}

function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function is_admin() {
	return true;
}
function current_user_can( $cap ) {
	return false;
}
function get_option( $name, $default = false ) {
	return $default;
}
function update_option( $name, $value ) {
	return true;
}
function determine_locale() {
	return $GLOBALS['locale'];
}
function get_locale() {
	return $GLOBALS['locale'];
}
// phpcs:enable

require $root . '/icc-gg-llm-files-generator.php';


// 1. The loader runs on plugins_loaded at priority 5.
$loaders = isset( $GLOBALS['hooks']['plugins_loaded'][5] ) ? $GLOBALS['hooks']['plugins_loaded'][5] : array();
check( 'load_textdomain is hooked on plugins_loaded @5', in_array( 'icc_gg_llm_files_generator_load_textdomain', $loaders, true ) );

call_user_func( 'icc_gg_llm_files_generator_load_textdomain' );

$call = end( $GLOBALS['textdomain_calls'] );
check( 'load_plugin_textdomain() was called', false !== $call );
if ( $call ) {
	check( 'text domain is icc-gg-llm-files-generator', 'icc-gg-llm-files-generator' === $call[0], $call[0] );
	check( 'deprecated argument is false', false === $call[1] );
	check( 'relative path is <plugin folder>/languages', 'wordpressiccllmfiles/languages' === $call[2], (string) $call[2] );
}

$languages = $root . '/languages';
check( 'the languages folder exists', is_dir( $languages ) );
foreach ( array( 'en_US', 'pt_BR', 'es_MX', 'fr_FR', 'zh_CN', 'hi_IN', 'ar' ) as $locale ) {
	check( "catalog shipped for {$locale}", is_file( "{$languages}/icc-gg-llm-files-generator-{$locale}.mo" ) );
}

// 2. plugin_locale mapping.
$map = array(
	'pt-BR' => 'pt_BR',
	'pt_BR' => 'pt_BR',
	'es-MX' => 'es_MX',
	'fr_FR' => 'fr_FR',
	'zh-CN' => 'zh_CN',
	'hi_IN' => 'hi_IN',
	'en_US' => 'en_US',
	'en_GB' => 'en_US',
	'en'    => 'en_US',
	'ar'    => 'ar',
	'ar_SA' => 'ar',
	'ar-EG' => 'ar',
	'de_DE' => 'de_DE',
);
foreach ( $map as $requested => $expected ) {
	$actual = apply_filters( 'plugin_locale', $requested, 'icc-gg-llm-files-generator' );
	check( "plugin_locale('{$requested}') === '{$expected}'", $expected === $actual, $actual );
}
check( 'plugin_locale leaves other text domains alone', 'ar_SA' === apply_filters( 'plugin_locale', 'ar_SA', 'some-other-plugin' ) );

// 3. RTL detection and the two markup filters.
foreach ( array( 'ar', 'ar_SA', 'ar-EG', 'he_IL', 'fa_IR' ) as $locale ) {
	$GLOBALS['locale'] = $locale;
	check( "is_rtl_locale() is true for {$locale}", true === icc_gg_llm_files_generator_is_rtl_locale() );
}
foreach ( array( 'en_US', 'pt_BR', 'fr_FR', 'hi_IN', 'zh_CN' ) as $locale ) {
	$GLOBALS['locale'] = $locale;
	check( "is_rtl_locale() is false for {$locale}", false === icc_gg_llm_files_generator_is_rtl_locale() );
}

$GLOBALS['locale'] = 'ar_SA';
$attributes        = apply_filters( 'language_attributes', 'lang="ar"', 'html' );
check( 'language_attributes adds dir="rtl"', 'lang="ar" dir="rtl"' === $attributes, $attributes );

$attributes = apply_filters( 'language_attributes', 'lang="ar" dir="rtl"', 'html' );
check( 'language_attributes keeps an existing direction', 'lang="ar" dir="rtl"' === $attributes, $attributes );

$classes = apply_filters( 'admin_body_class', 'wp-admin' );
check( 'admin_body_class adds rtl', 'wp-admin rtl' === $classes, $classes );

$classes = apply_filters( 'admin_body_class', 'wp-admin rtl' );
check( 'admin_body_class never duplicates rtl', 'wp-admin rtl' === $classes, $classes );

$GLOBALS['locale'] = 'fr_FR';
$attributes        = apply_filters( 'language_attributes', 'lang="fr-FR"', 'html' );
check( 'language_attributes is untouched for fr_FR', 'lang="fr-FR"' === $attributes, $attributes );

$classes = apply_filters( 'admin_body_class', 'wp-admin' );
check( 'admin_body_class is untouched for fr_FR', 'wp-admin' === $classes, $classes );

echo $failures ? "\n{$failures} failure(s)\n" : "\nAll i18n bootstrap checks passed.\n";
exit( $failures ? 1 : 0 );

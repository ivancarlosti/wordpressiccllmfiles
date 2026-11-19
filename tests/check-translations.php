<?php
/**
 * Validates the compiled translation catalogs without WordPress.
 *
 * The gettext extension is not enough on its own: `bindtextdomain()` needs the
 * locale installed on the machine, which is rarely true in CI. The .mo format
 * is a documented binary layout, so the catalogs are read directly instead.
 *
 * Checks per catalog:
 *   - the file is a valid little- or big-endian .mo;
 *   - the header is UTF-8, declares the expected language and plural forms;
 *   - no entry is left untranslated;
 *   - every printf placeholder and HTML tag survives translation;
 *   - a known string resolves to the expected translation, which catches two
 *     catalogs being swapped.
 *
 * Usage: php tests/check-translations.php [plugin-root]
 *
 * @package ICC_GG_LLM_Files_Generator
 */

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__ );
$slug = 'icc-gg-llm-files-generator';

/**
 * Expected translation of "Settings" per locale, used as a smoke test.
 *
 * @var array<string, string>
 */
$expected = array(
	'en_US' => 'Settings',
	'pt_BR' => 'Configurações',
	'es_MX' => 'Ajustes',
	'fr_FR' => 'Réglages',
	'zh_CN' => '设置',
	'hi_IN' => 'सेटिंग्स',
	'ar'    => 'الإعدادات',
);

/**
 * Reads a compiled gettext catalog.
 *
 * @param string $file Path to the .mo file.
 *
 * @throws RuntimeException When the file is missing or malformed.
 *
 * @return array<string, string> Map of msgid => msgstr.
 */
function icc_check_read_mo( $file ) {
	$data = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( false === $data ) {
		throw new RuntimeException( "cannot read {$file}" );
	}

	if ( strlen( $data ) < 28 ) {
		throw new RuntimeException( 'file is shorter than the .mo header' );
	}

	$magic = unpack( 'V', substr( $data, 0, 4 ) );
	$big   = false;
	if ( 0x950412de !== $magic[1] ) {
		$magic = unpack( 'N', substr( $data, 0, 4 ) );
		if ( 0x950412de !== $magic[1] ) {
			throw new RuntimeException( 'bad magic number, not a .mo file' );
		}
		$big = true;
	}

	$read = static function ( $offset ) use ( $data, $big ) {
		$value = unpack( $big ? 'N' : 'V', substr( $data, $offset, 4 ) );
		return $value[1];
	};

	$count   = $read( 8 );
	$origin  = $read( 12 );
	$trans   = $read( 16 );
	$length  = strlen( $data );
	$strings = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$id_length    = $read( $origin + $i * 8 );
		$id_offset    = $read( $origin + $i * 8 + 4 );
		$str_length   = $read( $trans + $i * 8 );
		$str_offset   = $read( $trans + $i * 8 + 4 );

		if ( $id_offset + $id_length > $length || $str_offset + $str_length > $length ) {
			throw new RuntimeException( "entry {$i} points outside the file" );
		}

		$strings[ substr( $data, $id_offset, $id_length ) ] = substr( $data, $str_offset, $str_length );
	}

	return $strings;
}

/**
 * Extracts printf placeholders so both sides of a pair can be compared.
 *
 * @param string $text Translation unit.
 *
 * @return string[] Sorted placeholders.
 */
function icc_check_placeholders( $text ) {
	preg_match_all( '/%(?:\d+\$)?[sd]/', str_replace( '%%', '', $text ), $matches );
	$found = $matches[0];
	sort( $found );
	return $found;
}

$errors = array();
$files  = glob( $root . '/languages/' . $slug . '-*.mo' );

if ( ! $files ) {
	echo "::error::No compiled catalogs found in {$root}/languages.\n";
	exit( 1 );
}

foreach ( $expected as $locale => $probe ) {
	$file = "{$root}/languages/{$slug}-{$locale}.mo";

	if ( ! is_file( $file ) ) {
		$errors[] = "missing catalog: {$slug}-{$locale}.mo";
		continue;
	}

	try {
		$strings = icc_check_read_mo( $file );
	} catch ( RuntimeException $exception ) {
		$errors[] = "{$locale}: " . $exception->getMessage();
		continue;
	}

	if ( ! isset( $strings[''] ) ) {
		$errors[] = "{$locale}: no header entry";
		continue;
	}

	$header = $strings[''];
	unset( $strings[''] );

	if ( ! preg_match( '/Language:\s*' . preg_quote( $locale, '/' ) . '\b/', $header ) ) {
		$errors[] = "{$locale}: header does not declare Language: {$locale}";
	}
	if ( ! preg_match( '/charset=UTF-8/i', $header ) ) {
		$errors[] = "{$locale}: header is not UTF-8";
	}
	if ( ! preg_match( '/Plural-Forms:\s*nplurals=\d+;/', $header ) ) {
		$errors[] = "{$locale}: header has no Plural-Forms";
	}

	if ( ! isset( $strings['Settings'] ) ) {
		$errors[] = "{$locale}: the source string \"Settings\" is not in the catalog";
	} elseif ( $probe !== $strings['Settings'] ) {
		$errors[] = "{$locale}: \"Settings\" is \"{$strings['Settings']}\", expected \"{$probe}\"";
	}

	foreach ( $strings as $msgid => $msgstr ) {
		if ( '' === trim( $msgstr ) ) {
			$errors[] = "{$locale}: empty translation for \"{$msgid}\"";
			continue;
		}

		if ( icc_check_placeholders( $msgid ) !== icc_check_placeholders( $msgstr ) ) {
			$errors[] = "{$locale}: placeholders differ for \"{$msgid}\"";
		}

		if ( substr_count( $msgid, '<strong>' ) !== substr_count( $msgstr, '<strong>' )
			|| substr_count( $msgid, '</strong>' ) !== substr_count( $msgstr, '</strong>' ) ) {
			$errors[] = "{$locale}: <strong> markup is unbalanced in \"{$msgstr}\"";
		}
	}

	printf( "✅ %s (%d entries)\n", basename( $file ), count( $strings ) );
}

if ( $errors ) {
	foreach ( $errors as $error ) {
		echo "::error::{$error}\n";
	}
	exit( 1 );
}

echo "✅ All compiled catalogs are valid.\n";

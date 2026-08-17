<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Global plugin helper functions.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Force (re)generation of the llms.txt and llms-full.txt files.
 *
 * @return bool True on success, false on failure.
 */
function icc_gg_llm_files_generator_generate_files() {
	$result = \ICC_GG_LLM_Files_Generator::instance()->generator->generate_all();
	return ! is_wp_error( $result );
}

/**
 * Retrieve the generated llms.txt content.
 *
 * @return string
 */
function icc_gg_llm_files_generator_get_llms_txt() {
	return (string) get_option( \ICC_GG_LLM_Files_Generator_Generator::OPTION_LLMS_TXT, '' );
}

/**
 * Retrieve the generated llms-full.txt content.
 *
 * @return string
 */
function icc_gg_llm_files_generator_get_llms_full_txt() {
	return (string) get_option( \ICC_GG_LLM_Files_Generator_Generator::OPTION_LLMS_FULL_TXT, '' );
}

/**
 * Add a Settings link to the plugin action links on the Plugins screen.
 *
 * @param array $links The existing plugin action links.
 *
 * @return array
 */
function icc_gg_llm_files_generator_add_settings_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=icc-gg-llm-files-generator-settings' ) ),
		esc_html__( 'Settings', 'icc-gg-llm-files-generator' )
	);

	return array_merge( array( $settings_link ), $links );
}

/**
 * Add a Check for updates link to the plugin row meta on the Plugins screen.
 *
 * @param array  $plugin_meta An array of the plugin's metadata.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 *
 * @return array
 */
function icc_gg_llm_files_generator_add_plugin_row_meta( $plugin_meta, $plugin_file ) {
	if ( strpos( $plugin_file, 'icc-gg-llm-files-generator.php' ) === false ) {
		return $plugin_meta;
	}

	$plugin_meta[] = sprintf(
		'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
		'https://github.com/ivancarlosti/wordpressiccllmfiles/releases',
		esc_html__( 'Check for updates', 'icc-gg-llm-files-generator' )
	);

	return $plugin_meta;
}

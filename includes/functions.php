<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Global plugin helper functions.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @author    Ivan Carlos
 * @copyright 2025-2026 Ivan Carlos
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

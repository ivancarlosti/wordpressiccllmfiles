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
 * Add update related links to the plugin row meta on the Plugins screen.
 *
 * "Check for updates" performs a real, forced GitHub Releases lookup — the
 * result is reported in an admin notice and in the native WordPress update
 * row — while "Release notes" opens the plugin releases page on GitHub.
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

	if ( current_user_can( 'update_plugins' ) ) {
		$plugin_meta[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( ICC_GG_LLM_Files_Generator_Github_Updater::check_url() ),
			esc_html__( 'Check for updates', 'icc-gg-llm-files-generator' )
		);
	}

	$plugin_meta[] = sprintf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
		'https://github.com/ivancarlosti/wordpressiccllmfiles/releases',
		esc_html__( 'Release notes', 'icc-gg-llm-files-generator' )
	);

	return $plugin_meta;
}

/**
 * Handle the "Check for updates" link on the Plugins screen.
 *
 * Forces a GitHub Releases lookup, stores the outcome in a short lived
 * per-user transient and redirects back to the Plugins screen, where
 * `icc_gg_llm_files_generator_show_update_check_notice()` reports it.
 *
 * @return void
 */
function icc_gg_llm_files_generator_handle_check_updates() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to check for plugin updates on this site.', 'icc-gg-llm-files-generator' ) );
	}

	check_admin_referer( 'icc_gg_llm_files_generator_check_updates' );

	if ( ! class_exists( 'ICC_GG_LLM_Files_Generator_Github_Updater' ) ) {
		wp_die( esc_html__( 'The update checker is not available.', 'icc-gg-llm-files-generator' ) );
	}

	$result = ICC_GG_LLM_Files_Generator_Github_Updater::check_for_updates();

	set_transient(
		ICC_GG_LLM_Files_Generator_Github_Updater::RESULT_CACHE_KEY . '_' . get_current_user_id(),
		$result,
		5 * MINUTE_IN_SECONDS
	);

	wp_safe_redirect( self_admin_url( 'plugins.php' ) );
	exit;
}

/**
 * Display the result of a manual "Check for updates" request.
 *
 * The result is stored in a short lived per-user transient by
 * `icc_gg_llm_files_generator_handle_check_updates()` and removed once
 * rendered, so the notice appears exactly once.
 *
 * @return void
 */
function icc_gg_llm_files_generator_show_update_check_notice() {
	if ( ! class_exists( 'ICC_GG_LLM_Files_Generator_Github_Updater' ) ) {
		return;
	}

	$key    = ICC_GG_LLM_Files_Generator_Github_Updater::RESULT_CACHE_KEY . '_' . get_current_user_id();
	$result = get_transient( $key );

	if ( ! is_array( $result ) || empty( $result['state'] ) ) {
		return;
	}

	delete_transient( $key );

	$version   = isset( $result['version'] ) ? $result['version'] : '';
	$installed = isset( $result['installed'] ) ? $result['installed'] : ICC_GG_LLM_Files_Generator::VERSION;
	$release   = isset( $result['url'] ) ? $result['url'] : '';

	if ( 'update' === $result['state'] ) {
		$message = sprintf(
			// translators: 1: New version number, 2: Installed version number.
			__( '<strong>ICC.gg LLM Files Generator %1$s is available.</strong> You are running version %2$s.', 'icc-gg-llm-files-generator' ),
			esc_html( $version ),
			esc_html( $installed )
		);

		$actions = '';

		if ( current_user_can( 'update_plugins' ) ) {
			$actions .= sprintf(
				'<a class="button button-primary" href="%1$s">%2$s</a>',
				esc_url( ICC_GG_LLM_Files_Generator_Github_Updater::update_url() ),
				esc_html__( 'Update now', 'icc-gg-llm-files-generator' )
			);
		}

		if ( '' !== $release ) {
			$actions .= sprintf(
				' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $release ),
				esc_html__( 'Release notes', 'icc-gg-llm-files-generator' )
			);
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p>%2$s</p></div>',
			wp_kses_post( $message ),
			wp_kses_post( $actions )
		);

		return;
	}

	if ( 'current' === $result['state'] ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					// translators: %s: Installed version number.
					__( 'ICC.gg LLM Files Generator is up to date (version %s).', 'icc-gg-llm-files-generator' ),
					$installed
				)
			)
		);

		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'The update check could not reach GitHub. Please try again in a few minutes.', 'icc-gg-llm-files-generator' )
	);
}

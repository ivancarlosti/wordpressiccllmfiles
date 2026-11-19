<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * ICC.gg LLM Files Generator - WordPress llms.txt / llms-full.txt Generator
 *
 * Generates and serves llms.txt and llms-full.txt files for site visitors and
 * AI agents, with optional AI-assisted content generation and automatic
 * updates whenever content changes.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  General
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/ivancarlosti/wordpressiccllmfiles
 *
 * @wordpress-plugin
 * Plugin Name:       ICC.gg LLM Files Generator
 * Plugin URI:        https://github.com/ivancarlosti/wordpressiccllmfiles
 * Description:       Generates and serves llms.txt and llms-full.txt files for AI agents, with optional AI-assisted content generation and automatic updates.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      8.2
 * Author:            ivancarlosti
 * Author URI:        https://ivancarlos.com.br
 * Text Domain:       icc-gg-llm-files-generator
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/*
Notes
  llms.txt is an emerging convention that provides LLM/AI agents with a concise
  index of a website's content. This plugin generates two plain-text files:
    - /llms.txt      - a concise index of pages, posts and custom post types.
    - /llms-full.txt - the full Markdown content of all published public items.

  The generated content is stored in WordPress options (not the filesystem) so
  the plugin works on any host.

  Filters
  - icc_gg_llm_files_generator_settings               - modify settings values early in plugin bootstrap.
  - icc_gg_llm_files_generator_settings_fields        - modify the fields provided on the settings page.
  - icc_gg_llm_files_generator_content_types          - modify the post types collected for generation.
  - icc_gg_llm_files_generator_collect_item           - 2 args: item array, WP_Post. Modify each collected item.
  - icc_gg_llm_files_generator_request_body           - modify the AI request body before it is sent.
  - icc_gg_llm_files_generator_llms_txt_content       - modify the final llms.txt content before it is stored.
  - icc_gg_llm_files_generator_llms_full_txt_content  - modify the final llms-full.txt content before it is stored.

  Actions
  - icc_gg_llm_files_generator_files_updated          - 1 arg: array of generated content. Fires after files are regenerated.
  - icc_gg_llm_files_generator_generation_failed      - 1 arg: WP_Error. Fires when generation fails.

  Options
  - icc_gg_llm_files_generator_settings               - plugin settings.
  - icc-gg-llm-files-generator-llms-txt               - generated llms.txt content.
  - icc-gg-llm-files-generator-llms-full-txt          - generated llms-full.txt content.
  - icc-gg-llm-files-generator-last-updated           - timestamp of the last successful generation.
  - icc-gg-llm-files-generator-last-error             - the most recent generation error (admin-facing).

  Translations
  - Bundled catalogs live in languages/ and are loaded on plugins_loaded (5).
  - Supported locales: en-US (fallback), pt-BR, es-MX, fr-FR, zh-CN, hi-IN and ar-SA.
  - Arabic sets dir="rtl" on <html>, adds the rtl admin body class and loads
    css/styles-admin-rtl.css. See docs/i18n.md.
*/


/*
 * Hard requirement: PHP 8.2+.
 *
 * Kept in sync with the "Requires PHP" plugin header and
 * composer.json. WordPress validates the header on install and activation, but
 * a site can still auto-update the plugin while running an older PHP version,
 * so the plugin disables itself and explains why instead of failing later.
 */
define( 'ICC_GG_LLM_FILES_GENERATOR_MIN_PHP', '8.2' );

if ( version_compare( PHP_VERSION, ICC_GG_LLM_FILES_GENERATOR_MIN_PHP, '<' ) ) {

	/**
	 * Admin notice shown when the site runs an unsupported PHP version.
	 *
	 * @return void
	 */
	function icc_gg_llm_files_generator_php_version_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					// translators: 1: plugin name, 2: required PHP version, 3: current PHP version.
					__( '%1$s requires PHP %2$s or later. This site runs PHP %3$s, so the plugin has been deactivated.', 'icc-gg-llm-files-generator' ),
					'ICC.gg LLM Files Generator',
					ICC_GG_LLM_FILES_GENERATOR_MIN_PHP,
					PHP_VERSION
				)
			)
		);
	}

	/**
	 * Deactivate the plugin when the site runs an unsupported PHP version.
	 *
	 * @return void
	 */
	function icc_gg_llm_files_generator_php_version_deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		deactivate_plugins( plugin_basename( __FILE__ ) );

		// Drop the "Plugin activated." notice for a plugin that never started.
		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['activate'] );
		}
	}

	add_action( 'admin_notices', 'icc_gg_llm_files_generator_php_version_notice' );
	add_action( 'admin_init', 'icc_gg_llm_files_generator_php_version_deactivate' );

	return;
}

/**
 * Plugin basename, e.g. icc-gg-llm-files-generator/icc-gg-llm-files-generator.php.
 *
 * Computed once here because `plugin_basename()` reflects the folder the
 * plugin is actually installed in, which is not necessarily the plugin slug
 * (a git clone lands in `wordpressiccllmfiles/`). The GitHub updater needs it
 * to build the native WordPress upgrade URL.
 */
define( 'ICC_GG_LLM_FILES_GENERATOR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Loads the bundled translations.
 *
 * WordPress only auto-loads language packs installed under
 * `wp-content/languages/plugins`. The plugin ships its own catalogs in
 * `languages/`, so they must be loaded explicitly.
 *
 * @return void
 */
function icc_gg_llm_files_generator_load_textdomain() {
	load_plugin_textdomain(
		'icc-gg-llm-files-generator',
		false,
		dirname( ICC_GG_LLM_FILES_GENERATOR_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'icc_gg_llm_files_generator_load_textdomain', 5 );

/**
 * Maps the locales supported by the bundled catalogs to WordPress locales.
 *
 * The documented codes use hyphens (`pt-BR`, `ar-SA`), while WordPress
 * reports locales with underscores (`pt_BR`, `ar_SA`). Every English variant
 * resolves to the `en_US` catalog, which is the fallback language (an identity
 * catalog, so untranslated locales keep the source English strings). Arabic
 * variants are mapped to `ar` so the RTL catalog is used for `ar_SA`, `ar_AE`
 * and similar locales.
 *
 * @param string $locale Locale requested by WordPress.
 * @param string $domain Text domain being loaded.
 *
 * @return string
 */
function icc_gg_llm_files_generator_plugin_locale( $locale, $domain ) {
	if ( 'icc-gg-llm-files-generator' !== $domain ) {
		return $locale;
	}

	$normalized = str_replace( '-', '_', (string) $locale );

	$supported = array( 'en_US', 'pt_BR', 'es_MX', 'fr_FR', 'zh_CN', 'hi_IN' );

	if ( in_array( $normalized, $supported, true ) ) {
		return $normalized;
	}

	if ( preg_match( '/^en([_-]|$)/i', $normalized ) ) {
		return 'en_US';
	}

	if ( preg_match( '/^ar([_-]|$)/i', $normalized ) ) {
		return 'ar';
	}

	return $locale;
}
add_filter( 'plugin_locale', 'icc_gg_llm_files_generator_plugin_locale', 10, 2 );

/**
 * Tells whether the current locale is written right-to-left.
 *
 * `is_rtl()` follows the site locale, which is wrong in the admin: a user whose
 * profile language is Arabic sees an LTR admin on an English site. This uses
 * the locale that actually drives translations.
 *
 * @return bool
 */
function icc_gg_llm_files_generator_is_rtl_locale() {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

	return (bool) preg_match( '/^(ar|fa|he|ur|ckb|dv|ps|sd|ug|yi)([_-]|$)/i', (string) $locale );
}

/**
 * Adds `dir="rtl"` when the active locale is right-to-left.
 *
 * WordPress core does this for the locales it ships, but `ar_SA` and other
 * Arabic variants may not be recognised. The filter is defensive: it never
 * overrides a direction that core already set.
 *
 * @param string $output  Language attributes.
 * @param string $doctype Document type.
 *
 * @return string
 */
function icc_gg_llm_files_generator_language_attributes( $output, $doctype ) {
	if ( icc_gg_llm_files_generator_is_rtl_locale() && false === stripos( $output, 'dir=' ) ) {
		$output .= ' dir="rtl"';
	}

	return $output;
}
add_filter( 'language_attributes', 'icc_gg_llm_files_generator_language_attributes', 10, 2 );

/**
 * Adds the `rtl` body class for right-to-left locales.
 *
 * @param string $classes Space-separated list of body classes.
 *
 * @return string
 */
function icc_gg_llm_files_generator_admin_body_class( $classes ) {
	if ( icc_gg_llm_files_generator_is_rtl_locale() && false === strpos( $classes, 'rtl' ) ) {
		$classes .= ' rtl';
	}

	return $classes;
}
add_filter( 'admin_body_class', 'icc_gg_llm_files_generator_admin_body_class' );

/**
 * ICC_GG_LLM_Files_Generator class.
 *
 * Defines plugin initialization functionality.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  General
 */
if ( ! class_exists( 'ICC_GG_LLM_Files_Generator' ) ) {
class ICC_GG_LLM_Files_Generator {

	/**
	 * Singleton instance of self
	 *
	 * @var ICC_GG_LLM_Files_Generator
	 */
	protected static $_instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Plugin settings.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Option_Settings
	 */
	private $settings;

	/**
	 * Content collector.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Content_Collector
	 */
	private $collector;

	/**
	 * Diff applier.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Diff_Applier
	 */
	private $diff_applier;

	/**
	 * File generator (AI orchestration).
	 *
	 * @var ICC_GG_LLM_Files_Generator_Generator
	 */
	public $generator;

	/**
	 * Router (rewrite rules + serving).
	 *
	 * @var ICC_GG_LLM_Files_Generator_Router
	 */
	private $router;

	/**
	 * Automation hooks.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Hooks
	 */
	private $hooks;

	/**
	 * Setup the plugin
	 *
	 * @param ICC_GG_LLM_Files_Generator_Option_Settings $settings The settings object.
	 *
	 * @return void
	 */
	public function __construct( ICC_GG_LLM_Files_Generator_Option_Settings $settings ) {
		$this->settings = $settings;
		self::$_instance = $this;
	}

	/**
	 * WordPress Hook 'init'.
	 *
	 * @return void
	 */
	public function init() {

		// Allow altering the settings.
		$this->settings = apply_filters( 'icc_gg_llm_files_generator_settings', $this->settings );

		$this->collector = new ICC_GG_LLM_Files_Generator_Content_Collector();
		$this->diff_applier = new ICC_GG_LLM_Files_Generator_Diff_Applier();
		$this->generator = new ICC_GG_LLM_Files_Generator_Generator( $this->settings, $this->collector, $this->diff_applier );
		$this->router = new ICC_GG_LLM_Files_Generator_Router( $this->settings );
		$this->hooks = new ICC_GG_LLM_Files_Generator_Hooks( $this->settings, $this->generator );

		$this->router->register();
		$this->hooks->register();

		if ( is_admin() ) {
			ICC_GG_LLM_Files_Generator_Settings_Page::register( $this->settings, $this->generator );
		}
	}

	/**
	 * Get the plugin settings object.
	 *
	 * @return ICC_GG_LLM_Files_Generator_Option_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activation() {
		$router = new ICC_GG_LLM_Files_Generator_Router( self::instance()->get_settings() );
		$router->register_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivation() {
		wp_clear_scheduled_hook( 'icc_gg_llm_files_generator_deferred_generation' );
		delete_transient( 'icc-gg-llm-files-generator-generating' );
		flush_rewrite_rules();
	}

	/**
	 * Simple autoloader.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = 'ICC_GG_LLM_Files_Generator_';

		if ( stripos( $class, $prefix ) !== 0 ) {
			return;
		}

		$filename = $class . '.php';

		// Internal files are all lowercase and use dashes in filenames.
		if ( false === strpos( $filename, '\\' ) ) {
			$filename = strtolower( str_replace( '_', '-', $filename ) );
		} else {
			$filename  = str_replace( '\\', DIRECTORY_SEPARATOR, $filename );
		}

		$filepath = __DIR__ . '/includes/' . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}

	/**
	 * Instantiate the plugin and hook into WordPress.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		$vendor_autoload = __DIR__ . '/vendor/autoload.php';

		if ( file_exists( $vendor_autoload ) ) {
			require_once $vendor_autoload;
		}

		// Register the plugin's custom autoloader before instantiating classes.
		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		$settings = new ICC_GG_LLM_Files_Generator_Option_Settings(
			// Default settings values.
			array(
				// File delivery settings.
				'enable_llms_txt'      => 1,
				'enable_llms_full_txt' => 1,

				// AI integration settings.
				'ai_api_url'           => '',
				'ai_api_key'           => '',
				'ai_model'             => 'gpt-4o-mini',
			)
		);

		$plugin = new self( $settings );

		add_action( 'init', array( $plugin, 'init' ) );
	}

	/**
	 * Create (if needed) and return a singleton of self.
	 *
	 * @return ICC_GG_LLM_Files_Generator
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::bootstrap();
		}
		return self::$_instance;
	}
}
ICC_GG_LLM_Files_Generator::instance();

register_activation_hook( __FILE__, array( 'ICC_GG_LLM_Files_Generator', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'ICC_GG_LLM_Files_Generator', 'deactivation' ) );

// Provide publicly accessible plugin helper functions.
require_once 'includes/functions.php';

add_filter( 'plugin_action_links_' . ICC_GG_LLM_FILES_GENERATOR_BASENAME, 'icc_gg_llm_files_generator_add_settings_link' );
add_filter( 'plugin_row_meta', 'icc_gg_llm_files_generator_add_plugin_row_meta', 10, 2 );

/**
 * Initialize the GitHub updater.
 *
 * Runs on `init` and also during the WordPress cron job so the plugin can be
 * updated automatically, mirroring the updater bootstrap used by plugins that
 * rely on a custom update server.
 *
 * @return void
 */
function icc_gg_llm_files_generator_init_github_updater() {
	$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
	if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
		return;
	}

	new ICC_GG_LLM_Files_Generator_Github_Updater(
		ICC_GG_LLM_FILES_GENERATOR_BASENAME,
		ICC_GG_LLM_Files_Generator::VERSION
	);
}
add_action( 'init', 'icc_gg_llm_files_generator_init_github_updater' );

// Report the outcome of a manual "Check for updates" click on the Plugins screen.
add_action( 'admin_post_icc_gg_llm_files_generator_check_updates', 'icc_gg_llm_files_generator_handle_check_updates' );
add_action( 'admin_notices', 'icc_gg_llm_files_generator_show_update_check_notice' );

} // End if ( ! class_exists( 'ICC_GG_LLM_Files_Generator' ) )

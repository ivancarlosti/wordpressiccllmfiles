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
 * @copyright 2025-2026 Ivan Carlos
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/ivancarlosti/icc-gg-llm-files-generator
 *
 * @wordpress-plugin
 * Plugin Name:       ICC.gg LLM Files Generator
 * Plugin URI:        https://github.com/ivancarlosti/icc-gg-llm-files-generator
 * Description:       Generates and serves llms.txt and llms-full.txt files for AI agents, with optional AI-assisted content generation and automatic updates.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      8.1
 * Author:            ivancarlosti
 * Author URI:        https://ivancarlos.me
 * Text Domain:       icc-gg-llm-files-generator
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
*/


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

} // End if ( ! class_exists( 'ICC_GG_LLM_Files_Generator' ) )

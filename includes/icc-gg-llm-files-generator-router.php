<?php
/**
 * Rewrite rule registration and file serving class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Routing
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Router class.
 *
 * Registers rewrite rules for /llms.txt and /llms-full.txt and serves the
 * generated content on template_redirect.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Routing
 */
class ICC_GG_LLM_Files_Generator_Router {

	/**
	 * The custom query var used to route file requests.
	 *
	 * @var string
	 */
	const FILE_QUERY_VAR = 'icc_gg_llm_files_generator_file';

	/**
	 * Plugin settings.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Option_Settings
	 */
	private $settings;

	/**
	 * The class constructor.
	 *
	 * @param ICC_GG_LLM_Files_Generator_Option_Settings $settings The plugin settings object.
	 */
	public function __construct( ICC_GG_LLM_Files_Generator_Option_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hook the router into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ) );
	}

	/**
	 * Register the rewrite rules and rewrite tag.
	 *
	 * @return void
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule( '^llms\.txt/?$', 'index.php?' . self::FILE_QUERY_VAR . '=llms.txt', 'top' );
		add_rewrite_rule( '^llms-full\.txt/?$', 'index.php?' . self::FILE_QUERY_VAR . '=llms-full.txt', 'top' );
		add_rewrite_tag( '%' . self::FILE_QUERY_VAR . '%', '([^&]+)' );
	}

	/**
	 * Register the custom query var.
	 *
	 * @param array $vars The existing query vars.
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::FILE_QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve the requested file on template_redirect.
	 *
	 * @return void
	 */
	public function maybe_serve() {
		if ( is_admin() ) {
			return;
		}

		$file = get_query_var( self::FILE_QUERY_VAR );

		if ( empty( $file ) ) {
			return;
		}

		if ( 'llms.txt' === $file ) {
			$this->serve( 'index', 'llms.txt' );
		} elseif ( 'llms-full.txt' === $file ) {
			$this->serve( 'full', 'llms-full.txt' );
		}
	}

	/**
	 * Serve a file if delivery is enabled and content exists.
	 *
	 * @param string $file_type The file type (index|full).
	 * @param string $file_name The file name being served.
	 *
	 * @return void
	 */
	private function serve( $file_type, $file_name ) {
		$enabled = ( 'index' === $file_type )
			? boolval( $this->settings->enable_llms_txt )
			: boolval( $this->settings->enable_llms_full_txt );

		$content = $this->get_content_for( $file_type );

		if ( ! $enabled || '' === $content ) {
			$this->serve_404();
			return;
		}

		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Content-Length: ' . strlen( $content ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional raw plain-text file delivery.
		echo $content;
		exit;
	}

	/**
	 * Set a 404 response for disabled/empty file requests.
	 *
	 * @return void
	 */
	private function serve_404() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Retrieve the stored content for a file type.
	 *
	 * @param string $file_type The file type (index|full).
	 *
	 * @return string
	 */
	private function get_content_for( $file_type ) {
		$option = ( 'index' === $file_type )
			? ICC_GG_LLM_Files_Generator_Generator::OPTION_LLMS_TXT
			: ICC_GG_LLM_Files_Generator_Generator::OPTION_LLMS_FULL_TXT;

		return (string) get_option( $option, '' );
	}
}

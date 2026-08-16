<?php
/**
 * File generation and AI orchestration class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Generation
 * @author    Ivan Carlos
 * @copyright 2025-2026 Ivan Carlos
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Generator class.
 *
 * Orchestrates file generation:
 *
 * - DIFF MODE (primary): on an incremental update the AI is sent a compact
 *   context (site info, the changed post(s) and the current file content) and
 *   is asked to return a strict JSON object of insert/remove operations. Those
 *   operations are applied via ICC_GG_LLM_Files_Generator_Diff_Applier.
 *
 * - FULL REGENERATION FALLBACK: if the AI response is not valid JSON, the diff
 *   cannot be applied cleanly, or the AI returns an error, the plugin falls
 *   back to a full regeneration of the affected file(s) using complete site
 *   data. If the AI fails during full regeneration, the deterministic Markdown
 *   produced by ICC_GG_LLM_Files_Generator_Content_Collector is used so the
 *   files always remain valid, and the error is recorded for admins.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Generation
 */
class ICC_GG_LLM_Files_Generator_Generator {

	/**
	 * Option name for the generated llms.txt content.
	 *
	 * @var string
	 */
	const OPTION_LLMS_TXT = 'icc-gg-llm-files-generator-llms-txt';

	/**
	 * Option name for the generated llms-full.txt content.
	 *
	 * @var string
	 */
	const OPTION_LLMS_FULL_TXT = 'icc-gg-llm-files-generator-llms-full-txt';

	/**
	 * Option name for the last successful generation timestamp.
	 *
	 * @var string
	 */
	const OPTION_LAST_UPDATED = 'icc-gg-llm-files-generator-last-updated';

	/**
	 * Option name for the most recent generation error (admin-facing).
	 *
	 * @var string
	 */
	const OPTION_LAST_ERROR = 'icc-gg-llm-files-generator-last-error';

	/**
	 * Transient key used as a lightweight debounce lock.
	 *
	 * @var string
	 */
	const LOCK_TRANSIENT = 'icc-gg-llm-files-generator-generating';

	/**
	 * The deferred generation hook name.
	 *
	 * @var string
	 */
	const DEFERRED_HOOK = 'icc_gg_llm_files_generator_deferred_generation';

	/**
	 * How long the debounce lock is held, in seconds.
	 *
	 * @var int
	 */
	const LOCK_TTL = 10;

	/**
	 * How long to wait before the deferred final-state generation, in seconds.
	 *
	 * @var int
	 */
	const DEFERRED_DELAY = 15;

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
	 * The class constructor.
	 *
	 * @param ICC_GG_LLM_Files_Generator_Option_Settings   $settings     The plugin settings object.
	 * @param ICC_GG_LLM_Files_Generator_Content_Collector $collector    The content collector object.
	 * @param ICC_GG_LLM_Files_Generator_Diff_Applier      $diff_applier The diff applier object.
	 */
	public function __construct( ICC_GG_LLM_Files_Generator_Option_Settings $settings, ICC_GG_LLM_Files_Generator_Content_Collector $collector, ICC_GG_LLM_Files_Generator_Diff_Applier $diff_applier ) {
		$this->settings = $settings;
		$this->collector = $collector;
		$this->diff_applier = $diff_applier;
	}

	/**
	 * Check whether the AI integration is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( trim( $this->settings->ai_api_url ) ) && ! empty( trim( $this->settings->ai_api_key ) );
	}

	/**
	 * Retrieve the most recent generation error.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return (string) get_option( self::OPTION_LAST_ERROR, '' );
	}

	/**
	 * Force full (re)generation of both files.
	 *
	 * @param bool $force Unused; retained for API consistency.
	 *
	 * @return true|WP_Error
	 */
	public function generate_all( $force = false ) {
		delete_option( self::OPTION_LAST_ERROR );

		$items = $this->collector->get_items();
		$generated = array();
		$errors = array();

		foreach ( array( 'index', 'full' ) as $file_type ) {
			$content = $this->regenerate_file( $file_type, $items );

			if ( is_wp_error( $content ) ) {
				$errors[] = $content;
				continue;
			}

			$generated[ $file_type ] = $content;
		}

		if ( empty( $generated ) && ! empty( $errors ) ) {
			$this->record_error( $errors[0] );
			do_action( 'icc_gg_llm_files_generator_generation_failed', $errors[0] );
			return $errors[0];
		}

		$this->persist_files( $generated );

		do_action( 'icc_gg_llm_files_generator_files_updated', $generated );

		return true;
	}

	/**
	 * Handle an incremental content update.
	 *
	 * @param int    $post_id     The changed post ID.
	 * @param string $change_type The change type (updated, published, deleted, etc.).
	 *
	 * @return bool
	 */
	public function update_incremental( $post_id, $change_type ) {
		// Debounce: if a generation is already in progress (or happened
		// moments ago), coalesce this update into a deferred full regeneration
		// so bulk edits do not fire dozens of AI calls.
		if ( $this->is_generation_locked() ) {
			$this->schedule_deferred_generation();
			return true;
		}

		$this->lock_generation();

		$changed_items = $this->collector->collect_changed_item( $post_id, $change_type );
		$result = $this->run_incremental( $changed_items, $change_type );

		if ( is_wp_error( $result ) ) {
			$this->record_error( $result );
			do_action( 'icc_gg_llm_files_generator_generation_failed', $result );
			return false;
		}

		return true;
	}

	/**
	 * Handle the deferred full regeneration scheduled by the debounce logic.
	 *
	 * @return void
	 */
	public function handle_deferred_generation() {
		delete_transient( self::LOCK_TRANSIENT );
		$this->generate_all();
	}

	/**
	 * Run the incremental generation for both file types.
	 *
	 * @param array  $changed_items The changed items.
	 * @param string $change_type   The change type.
	 *
	 * @return true|WP_Error
	 */
	private function run_incremental( $changed_items, $change_type ) {
		$is_removal = in_array( $change_type, array( 'deleted', 'trashed', 'unpublished' ), true );

		$generated = array();
		$errors = array();

		foreach ( array( 'index', 'full' ) as $file_type ) {
			$current = $this->get_stored_content( $file_type );

			// Without stored content, on removals, or without a configured AI,
			// a diff cannot be meaningfully applied: fall back to full
			// regeneration.
			if ( $is_removal || '' === $current || ! $this->is_configured() ) {
				$content = $this->regenerate_file( $file_type, $this->collector->get_items() );
			} else {
				$content = $this->apply_diff_or_regenerate( $file_type, $changed_items, $change_type, $current );
			}

			if ( is_wp_error( $content ) ) {
				$errors[] = $content;
				continue;
			}

			$generated[ $file_type ] = $content;
		}

		if ( empty( $generated ) && ! empty( $errors ) ) {
			return $errors[0];
		}

		$this->persist_files( $generated );

		do_action( 'icc_gg_llm_files_generator_files_updated', $generated );

		return true;
	}

	/**
	 * Apply an AI diff to a single file, falling back to full regeneration.
	 *
	 * This is the primary DIFF MODE path with the documented FULL REGENERATION
	 * FALLBACK.
	 *
	 * @param string $file_type     The file type (index|full).
	 * @param array  $changed_items The changed items.
	 * @param string $change_type   The change type.
	 * @param string $current       The current file content.
	 *
	 * @return string|WP_Error
	 */
	private function apply_diff_or_regenerate( $file_type, $changed_items, $change_type, $current ) {
		// DIFF MODE (primary): ask the AI for insert/remove operations.
		$ops_json = $this->request_diff( $file_type, $changed_items, $change_type, $current );

		if ( is_wp_error( $ops_json ) ) {
			$this->record_error( $ops_json );
			// FULL REGENERATION FALLBACK.
			return $this->regenerate_file( $file_type, $this->collector->get_items() );
		}

		$operations = $this->parse_operations( $ops_json );

		if ( is_wp_error( $operations ) ) {
			$this->record_error( $operations );
			// FULL REGENERATION FALLBACK.
			return $this->regenerate_file( $file_type, $this->collector->get_items() );
		}

		$applied = $this->diff_applier->apply( $current, $operations );

		if ( is_wp_error( $applied ) ) {
			$this->record_error( $applied );
			// FULL REGENERATION FALLBACK.
			return $this->regenerate_file( $file_type, $this->collector->get_items() );
		}

		$applied = trim( $applied );

		if ( '' === $applied ) {
			// The diff produced empty content, which is unsafe to store.
			$error = new WP_Error(
				'empty-diff-result',
				__( 'The AI diff produced empty content.', 'icc-gg-llm-files-generator' )
			);
			$this->record_error( $error );
			// FULL REGENERATION FALLBACK.
			return $this->regenerate_file( $file_type, $this->collector->get_items() );
		}

		return $applied;
	}

	/**
	 * Regenerate a single file.
	 *
	 * Builds the deterministic baseline via the content collector, then
	 * optionally refines it through the AI. If the AI is unavailable or fails,
	 * the deterministic baseline is returned so the file remains valid.
	 *
	 * @param string $file_type The file type (index|full).
	 * @param array  $items     The collected content items.
	 *
	 * @return string|WP_Error
	 */
	public function regenerate_file( $file_type, $items ) {
		// Deterministic baseline: guaranteed to always produce valid content.
		$baseline = ( 'index' === $file_type )
			? $this->collector->build_index( $items )
			: $this->collector->build_full( $items );

		if ( ! $this->is_configured() ) {
			return $baseline;
		}

		// Optional AI-assisted full regeneration.
		$ai_content = $this->request_full_regeneration( $file_type, $items );

		if ( is_wp_error( $ai_content ) ) {
			$this->record_error( $ai_content );
			// Fall back to the deterministic baseline.
			return $baseline;
		}

		$ai_content = trim( (string) $ai_content );

		if ( '' === $ai_content ) {
			return $baseline;
		}

		return $ai_content;
	}

	/**
	 * Request AI-generated insert/remove operations for a file (diff mode).
	 *
	 * @param string $file_type     The file type (index|full).
	 * @param array  $changed_items The changed items.
	 * @param string $change_type   The change type.
	 * @param string $current       The current file content.
	 *
	 * @return string|WP_Error
	 */
	private function request_diff( $file_type, $changed_items, $change_type, $current ) {
		$site = $this->collector->get_site_context();
		$file_label = $this->get_file_label( $file_type );
		$changed_md = $this->collector->format_changed_context( $changed_items, $change_type );

		$system = sprintf(
			/* translators: %s: file name */
			__( 'You maintain the %s file for a WordPress site. You return ONLY strict JSON describing edits to the existing file.', 'icc-gg-llm-files-generator' ),
			$file_label
		);

		$user = 'Site: ' . $site['name'] . ' (' . $site['home_url'] . ")\n";
		$user .= 'Target file: ' . $file_label . "\n";
		$user .= 'Change type: ' . $change_type . "\n\n";
		$user .= "Changed item(s):\n" . $changed_md . "\n\n";
		$user .= "Current file content:\n" . $this->truncate( $current ) . "\n\n";
		$user .= __( 'Return ONLY a JSON object with keys "insert" (array of strings to add) and "remove" (array of exact strings to delete). Example: {"insert":["- New page: Title (https://example.com/page)"],"remove":["- Old page: Title (https://example.com/page)"]}. Do not include Markdown fences or commentary.', 'icc-gg-llm-files-generator' );

		return $this->request_chat_completion(
			array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			)
		);
	}

	/**
	 * Request full AI regeneration of a file.
	 *
	 * @param string $file_type The file type (index|full).
	 * @param array  $items     The collected content items.
	 *
	 * @return string|WP_Error
	 */
	private function request_full_regeneration( $file_type, $items ) {
		$site = $this->collector->get_site_context();
		$file_label = $this->get_file_label( $file_type );
		$site_data = $this->collector->build_site_data_markdown( $items );

		$system = __( 'You generate SEO and LLM-friendly plain-text Markdown files for a WordPress website.', 'icc-gg-llm-files-generator' );

		$user = sprintf(
			/* translators: %s: file name */
			__( 'Generate the complete %s file content for the following WordPress site. Use the llms.txt convention: an H1 site title, a blockquote summary, and Markdown lists of links with short descriptions.', 'icc-gg-llm-files-generator' ),
			$file_label
		);
		$user .= "\n\n" . $site_data . "\n\n";
		$user .= __( 'Return ONLY the raw file content. Do not include Markdown fences, explanations or commentary.', 'icc-gg-llm-files-generator' );

		return $this->request_chat_completion(
			array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			)
		);
	}

	/**
	 * Send a chat completion request to the configured AI API.
	 *
	 * @param array $messages The chat messages array.
	 *
	 * @return string|WP_Error The assistant content or WP_Error on failure.
	 */
	private function request_chat_completion( $messages ) {
		$api_url = trim( $this->settings->ai_api_url );
		$api_key = trim( $this->settings->ai_api_key );
		$model = trim( $this->settings->ai_model );

		if ( empty( $api_url ) ) {
			return new WP_Error(
				'missing-api-url',
				__( 'The AI API URL is not configured.', 'icc-gg-llm-files-generator' )
			);
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing-api-key',
				__( 'The AI API key is not configured.', 'icc-gg-llm-files-generator' )
			);
		}

		$body = array(
			'model'       => ! empty( $model ) ? $model : 'gpt-4o-mini',
			'messages'    => $messages,
			'temperature' => 0.2,
		);

		// Allow other plugins to modify the AI request body.
		$body = apply_filters( 'icc_gg_llm_files_generator_request_body', $body );

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ai-request-failed',
				sprintf(
					/* translators: %s: error message */
					__( 'AI request failed: %s', 'icc-gg-llm-files-generator' ),
					$response->get_error_message()
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== intval( $response_code ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			return new WP_Error(
				'ai-http-error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body */
					__( 'The AI API returned HTTP %1$d: %2$s', 'icc-gg-llm-files-generator' ),
					intval( $response_code ),
					$response_body
				)
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( ! is_array( $data ) || ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'ai-invalid-response',
				__( 'The AI API returned a malformed response.', 'icc-gg-llm-files-generator' )
			);
		}

		return trim( (string) $data['choices'][0]['message']['content'] );
	}

	/**
	 * Parse the AI response into a decoded operations array.
	 *
	 * @param string $content The raw AI response content.
	 *
	 * @return array|WP_Error
	 */
	public function parse_operations( $content ) {
		$json = trim( (string) $content );

		// Strip Markdown code fences if the model wrapped the JSON.
		$json = preg_replace( '/^```(?:json)?\s*/i', '', $json );
		$json = preg_replace( '/\s*```$/', '', $json );

		// Extract the first JSON object if the model added surrounding text.
		if ( preg_match( '/\{.*\}/s', $json, $matches ) ) {
			$json = $matches[0];
		}

		$operations = json_decode( $json, true );

		if ( ! is_array( $operations ) ) {
			return new WP_Error(
				'ai-invalid-diff-json',
				__( 'The AI returned invalid JSON operations.', 'icc-gg-llm-files-generator' )
			);
		}

		return $operations;
	}

	/**
	 * Persist generated file content to WordPress options.
	 *
	 * @param array $generated Associative array of file_type => content.
	 *
	 * @return bool
	 */
	private function persist_files( $generated ) {
		$saved_any = false;

		if ( isset( $generated['index'] ) ) {
			$content = apply_filters( 'icc_gg_llm_files_generator_llms_txt_content', $generated['index'] );
			update_option( self::OPTION_LLMS_TXT, $content, false );
			$saved_any = true;
		}

		if ( isset( $generated['full'] ) ) {
			$content = apply_filters( 'icc_gg_llm_files_generator_llms_full_txt_content', $generated['full'] );
			update_option( self::OPTION_LLMS_FULL_TXT, $content, false );
			$saved_any = true;
		}

		if ( $saved_any ) {
			update_option( self::OPTION_LAST_UPDATED, gmdate( 'Y-m-d H:i:s', time() ), false );
		}

		return $saved_any;
	}

	/**
	 * Retrieve the stored content for a file type.
	 *
	 * @param string $file_type The file type (index|full).
	 *
	 * @return string
	 */
	private function get_stored_content( $file_type ) {
		$option = ( 'index' === $file_type ) ? self::OPTION_LLMS_TXT : self::OPTION_LLMS_FULL_TXT;
		return (string) get_option( $option, '' );
	}

	/**
	 * Get the human-readable file label for a file type.
	 *
	 * @param string $file_type The file type (index|full).
	 *
	 * @return string
	 */
	private function get_file_label( $file_type ) {
		return ( 'index' === $file_type ) ? 'llms.txt' : 'llms-full.txt';
	}

	/**
	 * Truncate a string to a safe length for AI prompts.
	 *
	 * @param string $text  The text to truncate.
	 * @param int    $limit The character limit.
	 *
	 * @return string
	 */
	private function truncate( $text, $limit = 12000 ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $limit ) {
			return mb_substr( $text, 0, $limit ) . "\n[... truncated ...]";
		}

		if ( strlen( $text ) > $limit ) {
			return substr( $text, 0, $limit ) . "\n[... truncated ...]";
		}

		return $text;
	}

	/**
	 * Record an admin-facing generation error.
	 *
	 * @param WP_Error|string $error The error object or message.
	 *
	 * @return void
	 */
	private function record_error( $error ) {
		$message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;

		update_option( self::OPTION_LAST_ERROR, $message, false );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional server-side diagnostics for generation failures.
		error_log( '[ICC.gg LLM Files Generator] ' . $message );
	}

	/**
	 * Check whether the debounce lock is currently held.
	 *
	 * @return bool
	 */
	private function is_generation_locked() {
		return false !== get_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Acquire the debounce lock.
	 *
	 * @return void
	 */
	private function lock_generation() {
		set_transient( self::LOCK_TRANSIENT, time(), self::LOCK_TTL );
	}

	/**
	 * Schedule a deferred full regeneration that captures the final state.
	 *
	 * @return void
	 */
	private function schedule_deferred_generation() {
		if ( ! wp_next_scheduled( self::DEFERRED_HOOK ) ) {
			wp_schedule_single_event( time() + self::DEFERRED_DELAY, self::DEFERRED_HOOK );
		}
	}
}

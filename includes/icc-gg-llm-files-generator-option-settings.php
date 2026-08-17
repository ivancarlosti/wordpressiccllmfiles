<?php
/**
 * WordPress options handling class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Settings
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Option_Settings class.
 *
 * WordPress options handling.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Settings
 *
 * File Delivery Settings:
 *
 * @property bool $enable_llms_txt      Whether delivery of llms.txt is enabled.
 * @property bool $enable_llms_full_txt Whether delivery of llms-full.txt is enabled.
 *
 * AI Integration Settings:
 *
 * @property string $ai_api_url The OpenAI-compatible chat completions endpoint URL.
 * @property string $ai_api_key The API key used to authenticate with the AI API.
 * @property string $ai_model   The AI model name used for chat completions.
 */
class ICC_GG_LLM_Files_Generator_Option_Settings {

	/**
	 * WordPress option name/key.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'icc_gg_llm_files_generator_settings';

	/**
	 * Stored option values array.
	 *
	 * @var array<mixed>
	 */
	private $values;

	/**
	 * Default plugin settings values.
	 *
	 * @var array<mixed>
	 */
	private $default_settings;

	/**
	 * The class constructor.
	 *
	 * @param array<mixed> $default_settings  The default plugin settings values.
	 * @param bool         $granular_defaults The granular defaults.
	 */
	public function __construct( $default_settings = array(), $granular_defaults = true ) {
		$this->default_settings = $default_settings;
		$this->values = array();

		$this->values = (array) get_option( self::OPTION_NAME, $this->default_settings );

		if ( $granular_defaults ) {
			$this->values = array_replace_recursive( $this->default_settings, $this->values );
		}
	}

	/**
	 * Magic getter for settings.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( isset( $this->values[ $key ] ) ) {
			return $this->values[ $key ];
		}
	}

	/**
	 * Magic setter for settings.
	 *
	 * @param string $key   The array key/option name.
	 * @param mixed  $value The option value.
	 *
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->values[ $key ] = $value;
	}

	/**
	 * Magic method to check is an attribute isset.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->values[ $key ] );
	}

	/**
	 * Magic method to clear an attribute.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return void
	 */
	public function __unset( $key ) {
		unset( $this->values[ $key ] );
	}

	/**
	 * Get the plugin settings array.
	 *
	 * @return array
	 */
	public function get_values() {
		return $this->values;
	}

	/**
	 * Get the plugin WordPress options name.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return self::OPTION_NAME;
	}

	/**
	 * Save the plugin options to the WordPress options table.
	 *
	 * @return void
	 */
	public function save() {
		update_option( self::OPTION_NAME, $this->values );
	}
}

<?php
/**
 * Plugin Admin settings page class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Settings
 * @author    Ivan Carlos
 * @copyright 2025-2026 Ivan Carlos
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Settings_Page class.
 *
 * Admin settings page.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Settings
 */
class ICC_GG_LLM_Files_Generator_Settings_Page {

	/**
	 * Local copy of the settings provided by the base plugin.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Option_Settings
	 */
	private $settings;

	/**
	 * Instance of the file generator.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Generator
	 */
	private $generator;

	/**
	 * The controlled list of settings & associated defined during
	 * construction for i18n reasons.
	 *
	 * @var array
	 */
	private $settings_fields = array();

	/**
	 * Settings fields that hold URLs and must be sanitized with esc_url_raw().
	 *
	 * @var array
	 */
	private $url_settings_fields = array(
		'ai_api_url',
	);

	/**
	 * Options page slug.
	 *
	 * @var string
	 */
	private $options_page_name = 'icc-gg-llm-files-generator-settings';

	/**
	 * Options page settings group name.
	 *
	 * @var string
	 */
	private $settings_field_group;

	/**
	 * Settings page class constructor.
	 *
	 * @param ICC_GG_LLM_Files_Generator_Option_Settings $settings  The plugin settings object.
	 * @param ICC_GG_LLM_Files_Generator_Generator       $generator The plugin generator object.
	 */
	public function __construct( ICC_GG_LLM_Files_Generator_Option_Settings $settings, ICC_GG_LLM_Files_Generator_Generator $generator ) {

		$this->settings             = $settings;
		$this->generator            = $generator;
		$this->settings_field_group = $this->settings->get_option_name() . '-group';

		$fields = $this->get_settings_fields();

		// Some simple pre-processing.
		foreach ( $fields as $key => &$field ) {
			$field['key']  = $key;
			$field['name'] = $this->settings->get_option_name() . '[' . $key . ']';
		}

		// Allow alterations of the fields.
		$this->settings_fields = $fields;
	}

	/**
	 * Hook the settings page into WordPress.
	 *
	 * @param ICC_GG_LLM_Files_Generator_Option_Settings $settings  A plugin settings object instance.
	 * @param ICC_GG_LLM_Files_Generator_Generator       $generator A plugin generator object instance.
	 *
	 * @return void
	 */
	public static function register( ICC_GG_LLM_Files_Generator_Option_Settings $settings, ICC_GG_LLM_Files_Generator_Generator $generator ) {
		$settings_page = new self( $settings, $generator );

		// Add our options page the the admin menu.
		add_action( 'admin_menu', array( $settings_page, 'admin_menu' ) );

		// Register our settings.
		add_action( 'admin_init', array( $settings_page, 'admin_init' ) );
	}

	/**
	 * Implements hook admin_menu to add our options/settings page to the
	 *  dashboard menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__( 'LLM Files Generator', 'icc-gg-llm-files-generator' ),
			__( 'LLM Files Generator', 'icc-gg-llm-files-generator' ),
			'manage_options',
			$this->options_page_name,
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Implements hook admin_init to register our settings.
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			$this->settings_field_group,
			$this->settings->get_option_name(),
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'delivery_settings',
			__( 'File Delivery', 'icc-gg-llm-files-generator' ),
			array( $this, 'delivery_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'ai_settings',
			__( 'AI Settings', 'icc-gg-llm-files-generator' ),
			array( $this, 'ai_settings_description' ),
			$this->options_page_name
		);

		// Preprocess fields and add them to the page.
		foreach ( $this->settings_fields as $key => $field ) {
			// Make sure each key exists in the settings array.
			if ( ! isset( $this->settings->{ $key } ) ) {
				$this->settings->{ $key } = null;
			}

			// Determine appropriate output callback.
			switch ( $field['type'] ) {
				case 'checkbox':
					$callback = 'do_checkbox';
					break;

				case 'password':
					$callback = 'do_password_field';
					break;

				case 'text':
				default:
					$callback = 'do_text_field';
					break;
			}

			// Add the field.
			add_settings_field(
				$key,
				$field['title'],
				array( $this, $callback ),
				$this->options_page_name,
				$field['section'],
				$field
			);
		}
	}

	/**
	 * Get the plugin settings fields definition.
	 *
	 * @return array
	 */
	private function get_settings_fields() {

		/**
		 * Simple settings fields have:
		 *
		 * - title
		 * - description
		 * - type ( checkbox | text | password )
		 * - section - settings/option page section ( delivery_settings | ai_settings )
		 * - example (optional example will appear beneath description and be wrapped in <code>;
		 *            pass a string for a single example or an array of label => value pairs
		 *            for multiple examples)
		 */
		$fields = array(
			'enable_llms_txt'      => array(
				'title'       => __( 'Enable llms.txt', 'icc-gg-llm-files-generator' ),
				'description' => __( 'Deliver the concise /llms.txt index to visitors and AI agents.', 'icc-gg-llm-files-generator' ),
				'type'        => 'checkbox',
				'section'     => 'delivery_settings',
			),
			'enable_llms_full_txt' => array(
				'title'       => __( 'Enable llms-full.txt', 'icc-gg-llm-files-generator' ),
				'description' => __( 'Deliver the full /llms-full.txt content to visitors and AI agents.', 'icc-gg-llm-files-generator' ),
				'type'        => 'checkbox',
				'section'     => 'delivery_settings',
			),
			'ai_api_url'           => array(
				'title'       => __( 'AI API URL', 'icc-gg-llm-files-generator' ),
				'description' => __( 'The OpenAI-compatible chat completions endpoint URL. Leave empty to disable AI-assisted generation.', 'icc-gg-llm-files-generator' ),
				'example'     => array(
					'OpenAI'   => 'https://api.openai.com/v1/chat/completions',
					'DeepSeek' => 'https://api.deepseek.com/v1/chat/completions',
				),
				'type'        => 'text',
				'section'     => 'ai_settings',
			),
			'ai_api_key'           => array(
				'title'       => __( 'API Key', 'icc-gg-llm-files-generator' ),
				'description' => __( 'The API key used to authenticate with the AI API. Stored in the WordPress options table.', 'icc-gg-llm-files-generator' ),
				'type'        => 'password',
				'section'     => 'ai_settings',
			),
			'ai_model'             => array(
				'title'       => __( 'Model', 'icc-gg-llm-files-generator' ),
				'description' => __( 'The AI model name used for chat completions.', 'icc-gg-llm-files-generator' ),
				'example'     => array(
					'OpenAI'   => 'gpt-4o-mini',
					'DeepSeek' => 'deepseek-v4-flash',
				),
				'type'        => 'text',
				'section'     => 'ai_settings',
			),
		);

		return apply_filters( 'icc_gg_llm_files_generator_settings_fields', $fields );
	}

	/**
	 * Sanitization callback for settings/option page.
	 *
	 * @param array $input The submitted settings values.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$options = array();

		// Loop through settings fields to control what we're saving.
		foreach ( $this->settings_fields as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				switch ( $field['type'] ) {
					case 'checkbox':
						$options[ $key ] = ( '1' === strval( $input[ $key ] ) ) ? 1 : 0;
						break;

					case 'password':
						$options[ $key ] = sanitize_text_field( $input[ $key ] );
						break;

					case 'text':
					default:
						$value = trim( $input[ $key ] );

						// URL fields must be sanitized with a URL-aware sanitizer.
						if ( in_array( $key, $this->url_settings_fields, true ) ) {
							$options[ $key ] = esc_url_raw( $value );
						} else {
							$options[ $key ] = sanitize_text_field( $value );
						}
						break;
				}
			} else {
				// Unchecked checkboxes must store 0.
				$options[ $key ] = ( 'checkbox' === $field['type'] ) ? 0 : '';
			}
		}

		return $options;
	}

	/**
	 * Output the options/settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		// Handle force generation form submission before any output.
		$this->handle_force_generate();

		wp_enqueue_style( 'icc-gg-llm-files-generator-admin', plugin_dir_url( __DIR__ ) . 'css/styles-admin.css', array(), ICC_GG_LLM_Files_Generator::VERSION, 'all' );

		$llms_txt_url = home_url( '/llms.txt' );
		$llms_full_txt_url = home_url( '/llms-full.txt' );
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<?php
			// Render force generation form.
			$this->render_force_generate_form();
			?>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();
				?>
			</form>

			<h4><?php esc_html_e( 'Notes', 'icc-gg-llm-files-generator' ); ?></h4>

			<p class="description">
				<strong><?php esc_html_e( 'llms.txt', 'icc-gg-llm-files-generator' ); ?></strong>
				<code><?php print esc_url( $llms_txt_url ); ?></code>
			</p>
			<p class="description">
				<strong><?php esc_html_e( 'llms-full.txt', 'icc-gg-llm-files-generator' ); ?></strong>
				<code><?php print esc_url( $llms_full_txt_url ); ?></code>
			</p>

			<?php if ( ! empty( $this->generator->get_last_error() ) ) { ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Last generation warning', 'icc-gg-llm-files-generator' ); ?>:</strong>
						<?php print esc_html( $this->generator->get_last_error() ); ?>
					</p>
				</div>
			<?php } ?>

			<hr style="margin-top: 30px;">
			<p style="text-align: center; color: #666; font-size: 12px;">
				<?php esc_html_e( 'ICC.gg LLM Files Generator - llms.txt / llms-full.txt Generator for WordPress', 'icc-gg-llm-files-generator' ); ?><br>
				<a href="https://github.com/ivancarlosti/icc-gg-llm-files-generator" target="_blank" rel="noopener noreferrer">
					github.com/ivancarlosti/icc-gg-llm-files-generator
				</a><br>
				<?php echo esc_html( sprintf( 'v%s', ICC_GG_LLM_Files_Generator::VERSION ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Output a standard text field.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_text_field( $field ) {
		?>
		<input type="text"
			id="<?php print esc_attr( $field['key'] ); ?>"
			class="large-text"
			name="<?php print esc_attr( $field['name'] ); ?>"
			value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a password field.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_password_field( $field ) {
		?>
		<input type="password"
			id="<?php print esc_attr( $field['key'] ); ?>"
			class="large-text"
			name="<?php print esc_attr( $field['name'] ); ?>"
			autocomplete="off"
			value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a checkbox for a boolean setting.
	 *  - hidden field is default value so we don't have to check isset() on save.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_checkbox( $field ) {
		?>
		<input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="0">
		<input type="checkbox"
			id="<?php print esc_attr( $field['key'] ); ?>"
			name="<?php print esc_attr( $field['name'] ); ?>"
			value="1"
			<?php checked( $this->settings->{ $field['key'] }, 1 ); ?>>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output the field description, and example if present.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_field_description( $field ) {
		$examples = isset( $field['example'] ) ? (array) $field['example'] : array();
		?>
		<p class="description">
			<?php print wp_kses_post( $field['description'] ); ?>
			<?php foreach ( $examples as $example_label => $example_value ) : ?>
				<br/><strong><?php esc_html_e( 'Example', 'icc-gg-llm-files-generator' ); ?><?php if ( ! is_int( $example_label ) ) : ?> (<?php print esc_html( $example_label ); ?>)<?php endif; ?>: </strong>
				<code><?php print esc_html( $example_value ); ?></code>
			<?php endforeach; ?>
		</p>
		<?php
	}

	/**
	 * Output the 'File Delivery' plugin setting section description.
	 *
	 * @return void
	 */
	public function delivery_settings_description() {
		esc_html_e( 'Control which generated files are delivered to visitors and AI agents.', 'icc-gg-llm-files-generator' );
	}

	/**
	 * Output the 'AI Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function ai_settings_description() {
		esc_html_e( 'Configure an OpenAI-compatible chat completions API to assist with file generation. Leave the API URL empty to use deterministic generation only.', 'icc-gg-llm-files-generator' );
	}

	/**
	 * Handle the Force Generate/Update Files form submission.
	 *
	 * @return void
	 */
	private function handle_force_generate() {
		// Check if force generation form was submitted.
		if ( ! isset( $_POST['icc_gg_llm_files_generator_generate_submit'] ) ) {
			return;
		}

		// Verify nonce.
		if (
			! isset( $_POST['icc_gg_llm_files_generator_generate_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['icc_gg_llm_files_generator_generate_nonce'] ) ), 'icc_gg_llm_files_generator_generate' )
		) {
			add_settings_error(
				'icc-gg-llm-files-generator',
				'invalid-nonce',
				__( 'Security check failed. Please try again.', 'icc-gg-llm-files-generator' ),
				'error'
			);
			return;
		}

		// Trigger generation.
		$result = $this->generator->generate_all( true );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'icc-gg-llm-files-generator',
				$result->get_error_code(),
				$result->get_error_message(),
				'error'
			);
			return;
		}

		// Report any AI fallback warnings alongside the success message.
		$last_error = $this->generator->get_last_error();
		if ( ! empty( $last_error ) ) {
			add_settings_error(
				'icc-gg-llm-files-generator',
				'generation-warning',
				sprintf(
					/* translators: %s: warning message */
					__( 'Files generated using the deterministic fallback. AI warning: %s', 'icc-gg-llm-files-generator' ),
					$last_error
				),
				'warning'
			);
			return;
		}

		add_settings_error(
			'icc-gg-llm-files-generator',
			'generation-success',
			__( 'Files generated successfully!', 'icc-gg-llm-files-generator' ),
			'success'
		);
	}

	/**
	 * Render the Force Generate/Update Files form.
	 *
	 * @return void
	 */
	private function render_force_generate_form() {
		$last_updated = get_option( ICC_GG_LLM_Files_Generator_Generator::OPTION_LAST_UPDATED, '' );
		?>
		<details class="icc-gg-llm-files-generator-force-generate" open>
			<summary class="icc-gg-llm-files-generator-force-generate-summary">
				⚡ <?php esc_html_e( 'Generate Files', 'icc-gg-llm-files-generator' ); ?>
			</summary>
			<div class="notice notice-info inline icc-gg-llm-files-generator-force-generate-content">
				<p>
					<?php esc_html_e( 'Click the button below to generate (or regenerate) the llms.txt and llms-full.txt files immediately.', 'icc-gg-llm-files-generator' ); ?>
				</p>
				<?php if ( ! empty( $last_updated ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Last updated', 'icc-gg-llm-files-generator' ); ?>:</strong>
						<?php print esc_html( $last_updated ); ?>
					</p>
				<?php endif; ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'icc_gg_llm_files_generator_generate', 'icc_gg_llm_files_generator_generate_nonce' ); ?>
					<?php submit_button( __( 'Force Generate/Update Files', 'icc-gg-llm-files-generator' ), 'secondary', 'icc_gg_llm_files_generator_generate_submit', false ); ?>
				</form>
			</div>
		</details>
		<hr class="icc-gg-llm-files-generator-force-generate-separator">
		<?php
	}
}

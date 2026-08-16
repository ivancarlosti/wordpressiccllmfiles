<?php
/**
 * Content automation hooks class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Automation
 * @author    Ivan Carlos
 * @copyright 2025-2026 Ivan Carlos
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Hooks class.
 *
 * Wires the post save/delete/transition automation to the generator.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Automation
 */
class ICC_GG_LLM_Files_Generator_Hooks {

	/**
	 * Plugin settings.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Option_Settings
	 */
	private $settings;

	/**
	 * File generator.
	 *
	 * @var ICC_GG_LLM_Files_Generator_Generator
	 */
	private $generator;

	/**
	 * The class constructor.
	 *
	 * @param ICC_GG_LLM_Files_Generator_Option_Settings $settings  The plugin settings object.
	 * @param ICC_GG_LLM_Files_Generator_Generator       $generator The generator object.
	 */
	public function __construct( ICC_GG_LLM_Files_Generator_Option_Settings $settings, ICC_GG_LLM_Files_Generator_Generator $generator ) {
		$this->settings = $settings;
		$this->generator = $generator;
	}

	/**
	 * Hook the automation into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'on_wp_trash_post' ), 10, 1 );
		add_action( 'delete_post', array( $this, 'on_delete_post' ), 10, 1 );
		add_action( 'untrashed_post', array( $this, 'on_untrashed_post' ), 10, 1 );
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );

		// Deferred final-state regeneration scheduled by the debounce logic.
		add_action( 'icc_gg_llm_files_generator_deferred_generation', array( $this->generator, 'handle_deferred_generation' ) );
	}

	/**
	 * Implements hook save_post.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function on_save_post( $post_id, $post, $update ) {
		// Skip revisions and autosaves.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $this->is_relevant_post( $post ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$this->generator->update_incremental( $post_id, 'updated' );
	}

	/**
	 * Implements hook wp_trash_post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function on_wp_trash_post( $post_id ) {
		$this->handle_removal( $post_id, 'trashed' );
	}

	/**
	 * Implements hook delete_post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function on_delete_post( $post_id ) {
		$this->handle_removal( $post_id, 'deleted' );
	}

	/**
	 * Implements hook untrashed_post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function on_untrashed_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $this->is_relevant_post( $post ) ) {
			return;
		}

		$this->generator->update_incremental( $post_id, 'published' );
	}

	/**
	 * Implements hook transition_post_status.
	 *
	 * Catches publish/unpublish transitions that save_post does not cover.
	 *
	 * @param string  $new_status The new post status.
	 * @param string  $old_status The old post status.
	 * @param WP_Post $post       The post object.
	 *
	 * @return void
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( ! $this->is_relevant_post( $post ) ) {
			return;
		}

		$is_unpublished = ( 'publish' === $old_status && 'publish' !== $new_status );
		$is_published = ( 'publish' === $new_status && 'publish' !== $old_status );

		if ( $is_unpublished ) {
			$this->generator->update_incremental( $post->ID, 'unpublished' );
		} elseif ( $is_published ) {
			$this->generator->update_incremental( $post->ID, 'published' );
		}
	}

	/**
	 * Handle a removal (trash/delete) of a post.
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $change_type The change type.
	 *
	 * @return void
	 */
	private function handle_removal( $post_id, $change_type ) {
		$post = get_post( $post_id );

		if ( ! $this->is_relevant_post( $post ) ) {
			return;
		}

		$this->generator->update_incremental( $post_id, $change_type );
	}

	/**
	 * Determine whether a post is relevant for generation.
	 *
	 * Only public post types that support the editor are relevant, which
	 * naturally excludes revisions, autosaves, attachments and nav menu items.
	 *
	 * @param WP_Post|null $post The post object.
	 *
	 * @return bool
	 */
	private function is_relevant_post( $post ) {
		if ( ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return false;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		if ( ! $post_type_object || empty( $post_type_object->public ) ) {
			return false;
		}

		if ( ! post_type_supports( $post->post_type, 'editor' ) ) {
			return false;
		}

		return true;
	}
}

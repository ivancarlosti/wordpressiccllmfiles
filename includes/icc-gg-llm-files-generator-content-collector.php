<?php
/**
 * Site content collection and Markdown formatting class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Content
 * @author    Ivan Carlos
 * @copyright 2007-2026 Ivan Carlos Consultoria
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Content_Collector class.
 *
 * Collects published public site content and formats it into Markdown. This
 * output is used by both the AI diff path and the full-regeneration fallback.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Content
 */
class ICC_GG_LLM_Files_Generator_Content_Collector {

	/**
	 * Retrieve the site context used in generated files.
	 *
	 * @return array
	 */
	public function get_site_context() {
		$name = get_bloginfo( 'name' );
		$tagline = get_bloginfo( 'description' );
		$home_url = home_url( '/' );

		if ( empty( $tagline ) ) {
			$tagline = $name;
		}

		return array(
			'name'        => $name,
			'tagline'     => $tagline,
			'description' => $tagline,
			'home_url'    => $home_url,
		);
	}

	/**
	 * Retrieve the public post types that contain editable content.
	 *
	 * Only post types that are public and support the editor are collected,
	 * which naturally excludes revisions, autosaves, attachments and nav menu
	 * items.
	 *
	 * @return array
	 */
	public function get_content_types() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		$content_types = array();
		foreach ( $post_types as $post_type ) {
			if ( ! post_type_supports( $post_type, 'editor' ) ) {
				continue;
			}
			$content_types[] = $post_type;
		}

		// Always include the core post and page types.
		$content_types = array_values( array_unique( array_merge( array( 'post', 'page' ), $content_types ) ) );

		return apply_filters( 'icc_gg_llm_files_generator_content_types', $content_types );
	}

	/**
	 * Retrieve all published public content items.
	 *
	 * @return array
	 */
	public function get_items() {
		$post_types = $this->get_content_types();
		$items = array();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'publish',
					'numberposts'      => -1,
					'orderby'          => 'post_title',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);

			foreach ( $posts as $post ) {
				$items[] = $this->format_item( $post );
			}
		}

		return $items;
	}

	/**
	 * Collect a single changed item for the diff context.
	 *
	 * @param int    $post_id     The post ID.
	 * @param string $change_type The change type (updated, published, deleted, etc.).
	 *
	 * @return array
	 */
	public function collect_changed_item( $post_id, $change_type ) {
		$post = get_post( $post_id );
		$is_removal = in_array( $change_type, array( 'deleted', 'trashed', 'unpublished' ), true );

		if ( $is_removal && ! $post ) {
			// The post no longer exists; provide only an identifying placeholder.
			return array(
				array(
					'id'      => intval( $post_id ),
					'type'    => '',
					'title'   => __( '(removed item)', 'icc-gg-llm-files-generator' ),
					'url'     => '',
					'slug'    => '',
					'excerpt' => '',
					'date'    => '',
					'content' => '',
					'status'  => $change_type,
				),
			);
		}

		if ( ! $post || ( ! $is_removal && 'publish' !== $post->post_status ) ) {
			return array();
		}

		return array( $this->format_item( $post, $change_type ) );
	}

	/**
	 * Format a WP_Post object into a normalized content item array.
	 *
	 * @param WP_Post $post   The post object.
	 * @param string  $status The item status/change type.
	 *
	 * @return array
	 */
	private function format_item( $post, $status = 'publish' ) {
		$content = apply_filters( 'the_content', $post->post_content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$content = trim( preg_replace( '/[ \t]+/', ' ', $content ) );

		$excerpt = get_the_excerpt( $post );
		if ( empty( $excerpt ) || is_wp_error( $excerpt ) ) {
			$excerpt = wp_trim_words( $content, 40, '…' );
		} else {
			$excerpt = trim( wp_strip_all_tags( $excerpt ) );
		}

		$item = array(
			'id'      => intval( $post->ID ),
			'type'    => $post->post_type,
			'title'   => get_the_title( $post ),
			'url'     => get_permalink( $post ),
			'slug'    => $post->post_name,
			'excerpt' => $excerpt,
			'date'    => get_the_date( 'Y-m-d', $post ),
			'content' => $content,
			'status'  => $status,
		);

		return apply_filters( 'icc_gg_llm_files_generator_collect_item', $item, $post );
	}

	/**
	 * Build the concise llms.txt index Markdown.
	 *
	 * @param array|null $items Optional pre-collected items.
	 *
	 * @return string
	 */
	public function build_index( $items = null ) {
		if ( null === $items ) {
			$items = $this->get_items();
		}

		$site = $this->get_site_context();

		$lines = array();
		$lines[] = '# ' . $site['name'];
		$lines[] = '';
		$lines[] = '> ' . $site['tagline'];
		$lines[] = '';

		// Group items by post type while preserving the collected order.
		$grouped = array();
		foreach ( $items as $item ) {
			$grouped[ $item['type'] ][] = $item;
		}

		foreach ( $grouped as $type => $type_items ) {
			$lines[] = '## ' . $this->get_post_type_label( $type );
			$lines[] = '';

			foreach ( $type_items as $item ) {
				$line = '- [' . $item['title'] . '](' . $item['url'] . ')';
				if ( ! empty( $item['excerpt'] ) ) {
					$line .= ': ' . $item['excerpt'];
				}
				$lines[] = $line;
			}

			$lines[] = '';
		}

		return trim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Build the full llms-full.txt Markdown.
	 *
	 * @param array|null $items Optional pre-collected items.
	 *
	 * @return string
	 */
	public function build_full( $items = null ) {
		if ( null === $items ) {
			$items = $this->get_items();
		}

		$site = $this->get_site_context();

		$lines = array();
		$lines[] = '# ' . $site['name'];
		$lines[] = '';
		$lines[] = '> ' . $site['tagline'];
		$lines[] = '';
		$lines[] = 'URL: ' . $site['home_url'];
		$lines[] = '';

		foreach ( $items as $item ) {
			$lines[] = '---';
			$lines[] = '';
			$lines[] = '## [' . $item['title'] . '](' . $item['url'] . ')';
			$lines[] = '';

			if ( ! empty( $item['date'] ) ) {
				$lines[] = '*Published: ' . $item['date'] . '*';
				$lines[] = '';
			}

			if ( ! empty( $item['content'] ) ) {
				$lines[] = $item['content'];
				$lines[] = '';
			}
		}

		return trim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Build a compact Markdown representation of changed items for the AI diff prompt.
	 *
	 * @param array  $items       The changed items.
	 * @param string $change_type The change type.
	 *
	 * @return string
	 */
	public function format_changed_context( $items, $change_type ) {
		$out = '';

		foreach ( $items as $item ) {
			$out .= '- Title: ' . $item['title'] . "\n";
			if ( ! empty( $item['type'] ) ) {
				$out .= '  Type: ' . $item['type'] . "\n";
			}
			if ( ! empty( $item['url'] ) ) {
				$out .= '  URL: ' . $item['url'] . "\n";
			}
			if ( ! empty( $item['slug'] ) ) {
				$out .= '  Slug: ' . $item['slug'] . "\n";
			}
			$out .= '  Change: ' . $change_type . "\n";
			if ( ! empty( $item['excerpt'] ) ) {
				$out .= '  Excerpt: ' . $item['excerpt'] . "\n";
			}
			if ( ! empty( $item['content'] ) ) {
				$out .= '  Content: ' . $this->truncate( $item['content'], 2000 ) . "\n";
			}
			$out .= "\n";
		}

		return rtrim( $out );
	}

	/**
	 * Build full formatted site data Markdown for full regeneration prompts.
	 *
	 * @param array|null $items Optional pre-collected items.
	 *
	 * @return string
	 */
	public function build_site_data_markdown( $items = null ) {
		if ( null === $items ) {
			$items = $this->get_items();
		}

		$site = $this->get_site_context();

		$lines = array();
		$lines[] = 'Site name: ' . $site['name'];
		$lines[] = 'Site tagline: ' . $site['tagline'];
		$lines[] = 'Site URL: ' . $site['home_url'];
		$lines[] = '';

		foreach ( $items as $item ) {
			$lines[] = '---';
			$lines[] = 'Type: ' . $item['type'];
			$lines[] = 'Title: ' . $item['title'];
			$lines[] = 'URL: ' . $item['url'];
			$lines[] = 'Slug: ' . $item['slug'];
			$lines[] = 'Published: ' . $item['date'];
			$lines[] = 'Excerpt: ' . $item['excerpt'];
			$lines[] = 'Content:';
			$lines[] = $this->truncate( $item['content'], 4000 );
			$lines[] = '';
		}

		return trim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Retrieve a human-readable label for a post type.
	 *
	 * @param string $type The post type.
	 *
	 * @return string
	 */
	private function get_post_type_label( $type ) {
		$object = get_post_type_object( $type );
		if ( $object && ! empty( $object->labels->name ) ) {
			return $object->labels->name;
		}
		return ucfirst( $type );
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
}

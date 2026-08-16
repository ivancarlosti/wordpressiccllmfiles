<?php
/**
 * AI diff operations applier class.
 *
 * @package   ICC_GG_LLM_Files_Generator
 * @category  Generation
 * @author    Ivan Carlos
 * @copyright 2025-2026 Ivan Carlos
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * ICC_GG_LLM_Files_Generator_Diff_Applier class.
 *
 * Safely applies AI-returned insert/remove operations to existing file content.
 *
 * The expected operations schema is a strict JSON object:
 *
 * {
 *   "insert": [ "line or section to add", "..." ],
 *   "remove": [ "exact substring to remove", "..." ]
 * }
 *
 * - "remove" entries are removed from the existing content wherever they occur
 *   (exact substring match, idempotent).
 * - "insert" entries are appended to the end of the file with blank-line
 *   separation.
 *
 * @package ICC_GG_LLM_Files_Generator
 * @category  Generation
 */
class ICC_GG_LLM_Files_Generator_Diff_Applier {

	/**
	 * Validate a decoded operations array and normalize it.
	 *
	 * @param array $operations The decoded operations array.
	 *
	 * @return array|WP_Error Normalized operations array or WP_Error.
	 */
	public function validate_operations( $operations ) {
		if ( ! is_array( $operations ) ) {
			return new WP_Error(
				'invalid-diff-operations',
				__( 'Diff operations must be a JSON object.', 'icc-gg-llm-files-generator' )
			);
		}

		$normalized = array(
			'insert' => array(),
			'remove' => array(),
		);

		foreach ( array( 'insert', 'remove' ) as $key ) {
			if ( ! array_key_exists( $key, $operations ) ) {
				continue;
			}

			if ( ! is_array( $operations[ $key ] ) ) {
				return new WP_Error(
					'invalid-diff-operations-key',
					sprintf(
						/* translators: %s: operations key */
						__( 'Diff operations key "%s" must be an array of strings.', 'icc-gg-llm-files-generator' ),
						$key
					)
				);
			}

			foreach ( $operations[ $key ] as $entry ) {
				if ( ! is_string( $entry ) ) {
					return new WP_Error(
						'invalid-diff-operations-entry',
						__( 'Diff operations entries must be strings.', 'icc-gg-llm-files-generator' )
					);
				}
				$normalized[ $key ][] = $entry;
			}
		}

		// A diff that does nothing is not an error, but is useless.
		if ( empty( $normalized['insert'] ) && empty( $normalized['remove'] ) ) {
			return new WP_Error(
				'empty-diff-operations',
				__( 'The AI returned an empty diff.', 'icc-gg-llm-files-generator' )
			);
		}

		return $normalized;
	}

	/**
	 * Apply insert/remove operations to existing file content.
	 *
	 * @param string $current    The current file content.
	 * @param array  $operations The decoded operations array.
	 *
	 * @return string|WP_Error
	 */
	public function apply( $current, $operations ) {
		if ( ! is_string( $current ) ) {
			return new WP_Error(
				'invalid-current-content',
				__( 'Current file content must be a string.', 'icc-gg-llm-files-generator' )
			);
		}

		$ops = $this->validate_operations( $operations );

		if ( is_wp_error( $ops ) ) {
			return $ops;
		}

		$result = $current;

		// Remove exact substrings first (safe and idempotent).
		foreach ( $ops['remove'] as $remove ) {
			if ( '' === $remove ) {
				continue;
			}
			$result = str_replace( $remove, '', $result );
		}

		// Append inserted lines/sections at the end of the file.
		if ( ! empty( $ops['insert'] ) ) {
			$result = rtrim( $result, "\n" );
			$result .= "\n\n" . implode( "\n\n", $ops['insert'] ) . "\n";
		}

		// Collapse any run of 3+ blank lines introduced by the operations.
		$result = preg_replace( '/\n{3,}/', "\n\n", $result );

		return $result;
	}
}

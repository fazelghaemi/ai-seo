<?php
/**
 * Ready Studio SEO Engine - Core Data Helper
 *
 * v15.0: MASTERPIECE EDITION
 * - FEATURE: Two-way Title Sync (Updates main WP post_title from AI title).
 * - FIX: Smart Tag Handling (Accepts both Array and String to fix explode errors).
 * - SAFETY: Enhanced sanitization and error checking for all meta updates.
 * - COMPATIBILITY: Full support for RankMath, Yoast, and JetEngine CPTs.
 *
 * @package   ReadyStudio
 * @version   15.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_Data {

	public function __construct() {
		// Ready hooks if needed later
	}

	/**
	 * The Master Save Function.
	 * Orchestrates saving data from any module (SEO, Content, Vision) to the database.
	 *
	 * @param int   $post_id The ID of the post to update.
	 * @param array $data    A sanitized array of data from the AI response.
	 */
	public function save_all_metabox_data( $post_id, $data ) {
		// 1. Save Core SEO Data (Title, Desc, Keyword)
		// This handles RankMath/Yoast AND updates the main WP Post Title.
		$this->save_seo_meta( $post_id, $data );

		// 2. Save Tags (Smart handling for arrays/strings)
		if ( ! empty( $data['tags'] ) ) {
			$this->save_post_tags( $post_id, $data['tags'] );
		}

		// 3. Save CPT-Specific Data (Latin Name / Slug)
		// Only for 'prompts' post type
		if ( ! empty( $data['latin_name'] ) && get_post_type( $post_id ) === 'prompts' ) {
			$this->save_prompt_cpt_data( $post_id, $data['latin_name'] );
		}

		// 4. Save Content Body (Main Editor Content)
		if ( ! empty( $data['content_body'] ) ) {
			$this->save_post_content( $post_id, $data['content_body'] );
		}

		// 5. Save Image Alt Text (Featured Image)
		// Priority: Vision Alt > Content Alt > Existing Alt (don't overwrite with null)
		$alt_text = null;
		if ( ! empty( $data['vision_alt'] ) ) {
			$alt_text = $data['vision_alt'];
		} elseif ( ! empty( $data['image_alt'] ) ) {
			$alt_text = $data['image_alt'];
		}

		if ( $alt_text !== null ) {
			$this->save_image_alt_text( $post_id, $alt_text );
		}
	}

	/**
	 * Saves SEO meta fields and Synchronizes the Main Post Title.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $data    Array containing 'title', 'description', 'keyword'.
	 */
	public function save_seo_meta( $post_id, $data ) {
		// Sanitize inputs before usage
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : null;
		$desc  = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : null;
		$kw    = isset( $data['keyword'] ) ? sanitize_text_field( $data['keyword'] ) : null;

		// --- A. Sync Main WordPress Post Title ---
		// This ensures the post title in the admin list matches the SEO title.
		if ( ! empty( $title ) ) {
			$current_post = get_post( $post_id );
			// Only update if the title is actually different (optimization)
			if ( $current_post && $current_post->post_title !== $title ) {
				wp_update_post( array(
					'ID'         => $post_id,
					'post_title' => $title
				) );
			}
		}

		// --- B. Save to RankMath Fields ---
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			if ( $title ) {
				update_post_meta( $post_id, 'rank_math_title', $title );
			}
			if ( $desc ) {
				update_post_meta( $post_id, 'rank_math_description', $desc );
			}
			if ( $kw ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
			}
		}

		// --- C. Save to Yoast SEO Fields (Compatibility) ---
		if ( defined( 'WPSEO_VERSION' ) ) {
			if ( $title ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $title );
			}
			if ( $desc ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
			}
			if ( $kw ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $kw );
			}
		}
	}

	/**
	 * Saves tags to the post safely.
	 * *** CRITICAL FIX: Handles both Array and String inputs to prevent explode() errors. ***
	 *
	 * @param int          $post_id    The post ID.
	 * @param string|array $tags_input Tags as comma-separated string OR array.
	 */
	public function save_post_tags( $post_id, $tags_input ) {
		if ( empty( $tags_input ) ) {
			return;
		}

		$tags_array = [];

		// Intelligent Type Handling
		if ( is_array( $tags_input ) ) {
			// Input is already an array (e.g., from JSON API response)
			$tags_array = $tags_input;
		} elseif ( is_string( $tags_input ) ) {
			// Input is a string (e.g., from CSV manual input or comma-separated response)
			// We only explode if it's a string!
			$tags_array = explode( ',', $tags_input );
		}

		// Sanitize and Clean
		$sanitized_tags = array_map( 'sanitize_text_field', $tags_array );
		$sanitized_tags = array_map( 'trim', $sanitized_tags );
		$sanitized_tags = array_filter( $sanitized_tags ); // Remove empty items (e.g., from trailing commas)

		if ( ! empty( $sanitized_tags ) ) {
			// 'true' as the last argument appends tags to existing ones.
			// Set to 'false' if you want to overwrite existing tags completely.
			wp_set_object_terms( $post_id, $sanitized_tags, 'post_tag', true );
		}
	}

	/**
	 * Saves data specific to the 'prompts' Custom Post Type.
	 * Updates the custom field AND the post slug (URL).
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $latin_name The English/Latin name for the prompt.
	 */
	public function save_prompt_cpt_data( $post_id, $latin_name ) {
		if ( empty( $latin_name ) ) {
			return;
		}

		$sanitized_name = sanitize_text_field( $latin_name );

		// 1. Save to the JetEngine/ACF custom field 'latin-name-prompt'
		update_post_meta( $post_id, 'latin-name-prompt', $sanitized_name );

		// 2. Update the WordPress Post Slug (post_name) for clean URLs
		$new_slug = sanitize_title( $sanitized_name );
		
		$post = get_post( $post_id );
		// Only update if different to save resources
		if ( $post && $post->post_name !== $new_slug ) {
			wp_update_post( array(
				'ID'        => $post_id,
				'post_name' => $new_slug,
			) );
		}
	}

	/**
	 * Updates the main content body of a post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $content The new HTML content.
	 */
	public function save_post_content( $post_id, $content ) {
		if ( empty( $content ) ) {
			return;
		}

		// wp_kses_post allows safe HTML tags (p, strong, a, img, etc.) but strips scripts/iframes.
		$safe_content = wp_kses_post( $content );

		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $safe_content,
		) );
	}

	/**
	 * Saves the Alt Text to the post's Featured Image (Thumbnail).
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $alt_text The new alt text.
	 */
	public function save_image_alt_text( $post_id, $alt_text ) {
		if ( empty( $alt_text ) ) {
			return;
		}
		
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		
		if ( $thumbnail_id ) {
			// '_wp_attachment_image_alt' is the standard meta key for alt text
			update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}
	}

	/**
	 * Retrieves the content to be analyzed by the AI.
	 * Specialized logic for 'prompts' CPT (reads custom field).
	 *
	 * @param int $post_id The post ID.
	 * @return string The raw text content.
	 */
	public function get_content_for_analysis( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Specialized: Check if it's the 'prompts' CPT and get content from 'prompts-text' meta
		if ( $post->post_type === 'prompts' ) {
			$prompt_text = get_post_meta( $post_id, 'prompts-text', true );
			if ( ! empty( $prompt_text ) ) {
				return $prompt_text;
			}
		}
		
		// Fallback: Return the main post content
		return $post->post_content;
	}

} // End class ReadyStudio_Core_Data
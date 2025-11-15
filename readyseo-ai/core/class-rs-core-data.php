<?php
/**
 * Ready Studio SEO Engine - Core Data Helper
 *
 * This class is a dedicated helper for all database read/write operations.
 * It abstracts the saving logic (e.g., for RankMath, Yoast, CPTs)
 * so that modules don't need to handle it themselves.
 *
 * @package   ReadyStudio
 * @version   12.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_Data {

	/**
	 * Constructor.
	 * (Currently empty, can be used for hooks if needed later)
	 */
	public function __construct() {
		// Ready for any data-related hooks if necessary
	}

	/**
	 * The main function for saving all data from the metabox AJAX call.
	 * It receives the sanitized data array and the post ID.
	 *
	 * @param int   $post_id The ID of the post to update.
	 * @param array $data    A sanitized array of data to save.
	 */
	public function save_all_metabox_data( $post_id, $data ) {
		// --- 1. Save Core SEO Data ---
		// These fields are common (title, desc, kw)
		$this->save_seo_meta( $post_id, $data );

		// --- 2. Save Tags ---
		if ( isset( $data['tags'] ) ) {
			$this->save_post_tags( $post_id, $data['tags'] );
		}

		// --- 3. Save CPT-Specific Data (e.g., Latin Name) ---
		if ( isset( $data['latin_name'] ) && get_post_type( $post_id ) === 'prompts' ) {
			$this->save_prompt_cpt_data( $post_id, $data['latin_name'] );
		}

		// --- 4. Save Content Body ---
		if ( isset( $data['content_body'] ) && ! empty( $data['content_body'] ) ) {
			$this->save_post_content( $post_id, $data['content_body'] );
		}

		// --- 5. Save Image Alt Text ---
		// We prioritize vision_alt > image_alt > existing
		$alt_text = ! empty( $data['vision_alt'] ) ? $data['vision_alt'] : ( ! empty( $data['image_alt'] ) ? $data['image_alt'] : null );
		if ( $alt_text !== null ) {
			$this->save_image_alt_text( $post_id, $alt_text );
		}
	}

	/**
	 * Saves the primary SEO meta fields to the database.
	 * Handles compatibility with RankMath and Yoast automatically.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $data    Array containing 'title', 'description', 'keyword'.
	 */
	public function save_seo_meta( $post_id, $data ) {
		// Check if keys exist before saving
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : null;
		$desc = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : null;
		$kw = isset( $data['keyword'] ) ? sanitize_text_field( $data['keyword'] ) : null;

		// --- Save to RankMath Fields ---
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			if ( $title !== null ) {
				update_post_meta( $post_id, 'rank_math_title', $title );
			}
			if ( $desc !== null ) {
				update_post_meta( $post_id, 'rank_math_description', $desc );
			}
			if ( $kw !== null ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
			}
		}

		// --- Save to Yoast Fields (for compatibility) ---
		if ( defined( 'WPSEO_VERSION' ) ) {
			if ( $title !== null ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $title );
			}
			if ( $desc !== null ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
			}
			if ( $kw !== null ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $kw );
			}
		}
	}

	/**
	 * Saves tags to the post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $tags_string A comma-separated string of tags.
	 */
	public function save_post_tags( $post_id, $tags_string ) {
		if ( empty( $tags_string ) ) {
			return; // Do nothing if tags are empty
		}
		// Convert comma-separated string to an array
		$tags_array = array_map( 'trim', explode( ',', $tags_string ) );
		$sanitized_tags = array_map( 'sanitize_text_field', $tags_array );
		
		// Append tags to the post
		// This respects existing tags and only adds new ones.
		wp_set_object_terms( $post_id, $sanitized_tags, 'post_tag', true ); // true = append
	}

	/**
	 * Saves data specific to the 'prompts' CPT.
	 * Updates the 'latin-name-prompt' meta field and the post slug.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $latin_name The English/Latin name for the prompt.
	 */
	public function save_prompt_cpt_data( $post_id, $latin_name ) {
		if ( empty( $latin_name ) ) {
			return;
		}

		$sanitized_name = sanitize_text_field( $latin_name );

		// 1. Save to the JetEngine custom field 'latin-name-prompt'
		update_post_meta( $post_id, 'latin-name-prompt', $sanitized_name );

		// 2. Update the post_name (slug)
		$new_slug = sanitize_title( $sanitized_name );
		
		// Check if the slug already exists or is different
		$post = get_post( $post_id );
		if ( $post && $post->post_name !== $new_slug ) {
			$post_data = [
				'ID'        => $post_id,
				'post_name' => $new_slug, // wp_update_post handles uniqueness
			];
			// Use wp_update_post to safely update the slug
			wp_update_post( $post_data );
		}
	}

	/**
	 * Updates the main content of a post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $content The new content.
	 */
	public function save_post_content( $post_id, $content ) {
		if ( empty( $content ) ) {
			return;
		}

		$post_data = [
			'ID'           => $post_id,
			'post_content' => wp_kses_post( $content ), // Sanitize for safe HTML
		];

		// We must unhook our own save actions to prevent infinite loops
		// (This is a placeholder for safety, real hooks would be in modules)
		// remove_action( 'save_post', [ $this, 'my_save_hook' ] );

		wp_update_post( $post_data );
		
		// Re-hook
		// add_action( 'save_post', [ $this, 'my_save_hook' ] );
	}

	/**
	 * Saves the Alt Text to the post's Featured Image.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $alt_text The new alt text.
	 */
	public function save_image_alt_text( $post_id, $alt_text ) {
		if ( empty( $alt_text ) ) {
			return;
		}
		
		// Get the ID of the post's featured image
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		
		if ( $thumbnail_id ) {
			// Update the meta field for the attachment post
			update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}
	}

	/**
	 * Retrieves the content to be analyzed by the AI.
	 * Specialized for 'prompts' CPT.
	 *
	 * @param int $post_id The post ID.
	 * @return string The raw text content.
	 */
	public function get_content_for_analysis( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Check if it's the 'prompts' CPT and get content from meta
		if ( $post->post_type === 'prompts' ) {
			$prompt_text = get_post_meta( $post_id, 'prompts-text', true );
			if ( ! empty( $prompt_text ) ) {
				return $prompt_text;
			}
		}
		
		// Fallback to main post content
		return $post->post_content;
	}

} // End class ReadyStudio_Core_Data
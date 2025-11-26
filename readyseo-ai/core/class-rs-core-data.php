<?php
/**
 * Ready Studio SEO Engine - Core Data Helper
 *
 * v15.0: MASTERPIECE EDITION
 * - Added logic to update the main WP Post Title (`post_title`) from SEO title.
 * - Fixed critical `explode()` bug by handling both array and string inputs for tags.
 * - Improved sanitization and error safety.
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
		// Ready hooks
	}

	/**
	 * The main function for saving all data from the metabox AJAX call.
	 */
	public function save_all_metabox_data( $post_id, $data ) {
		// 1. Save Core SEO Data (Title, Desc, Keyword)
		// This now also updates the main post title!
		$this->save_seo_meta( $post_id, $data );

		// 2. Save Tags (Handles arrays correctly now)
		if ( ! empty( $data['tags'] ) ) {
			$this->save_post_tags( $post_id, $data['tags'] );
		}

		// 3. Save CPT-Specific Data
		if ( ! empty( $data['latin_name'] ) && get_post_type( $post_id ) === 'prompts' ) {
			$this->save_prompt_cpt_data( $post_id, $data['latin_name'] );
		}

		// 4. Save Content Body
		if ( ! empty( $data['content_body'] ) ) {
			$this->save_post_content( $post_id, $data['content_body'] );
		}

		// 5. Save Image Alt Text
		$alt_text = ! empty( $data['vision_alt'] ) ? $data['vision_alt'] : ( ! empty( $data['image_alt'] ) ? $data['image_alt'] : null );
		if ( $alt_text !== null ) {
			$this->save_image_alt_text( $post_id, $alt_text );
		}
	}

	/**
	 * Saves SEO meta and UPDATES MAIN POST TITLE.
	 */
	public function save_seo_meta( $post_id, $data ) {
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : null;
		$desc = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : null;
		$kw = isset( $data['keyword'] ) ? sanitize_text_field( $data['keyword'] ) : null;

		// --- A. Update Main WordPress Post Title ---
		if ( $title ) {
			$current_post = get_post( $post_id );
			// Only update if title is different to save DB calls
			if ( $current_post && $current_post->post_title !== $title ) {
				wp_update_post( [
					'ID'         => $post_id,
					'post_title' => $title
				] );
			}
		}

		// --- B. Save to RankMath ---
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			if ( $title ) update_post_meta( $post_id, 'rank_math_title', $title );
			if ( $desc ) update_post_meta( $post_id, 'rank_math_description', $desc );
			if ( $kw ) update_post_meta( $post_id, 'rank_math_focus_keyword', $kw );
		}

		// --- C. Save to Yoast ---
		if ( defined( 'WPSEO_VERSION' ) ) {
			if ( $title ) update_post_meta( $post_id, '_yoast_wpseo_title', $title );
			if ( $desc ) update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
			if ( $kw ) update_post_meta( $post_id, '_yoast_wpseo_focuskw', $kw );
		}
	}

	/**
	 * Saves tags safely (String or Array).
	 * *** FIXES THE EXPLODE ERROR ***
	 */
	public function save_post_tags( $post_id, $tags_input ) {
		if ( empty( $tags_input ) ) return;

		// Check if input is already an array (from JSON) or a string (from CSV)
		if ( is_array( $tags_input ) ) {
			$tags_array = $tags_input;
		} else {
			$tags_array = explode( ',', $tags_input );
		}

		// Clean up tags
		$sanitized_tags = array_map( 'sanitize_text_field', $tags_array );
		$sanitized_tags = array_map( 'trim', $sanitized_tags );
		$sanitized_tags = array_filter( $sanitized_tags ); // Remove empties

		if ( ! empty( $sanitized_tags ) ) {
			// 'true' means append to existing tags. Set to 'false' to overwrite.
			wp_set_object_terms( $post_id, $sanitized_tags, 'post_tag', true );
		}
	}

	public function save_prompt_cpt_data( $post_id, $latin_name ) {
		if ( empty( $latin_name ) ) return;
		$sanitized_name = sanitize_text_field( $latin_name );
		update_post_meta( $post_id, 'latin-name-prompt', $sanitized_name );
		
		$new_slug = sanitize_title( $sanitized_name );
		$post = get_post( $post_id );
		if ( $post && $post->post_name !== $new_slug ) {
			wp_update_post( [ 'ID' => $post_id, 'post_name' => $new_slug ] );
		}
	}

	public function save_post_content( $post_id, $content ) {
		if ( empty( $content ) ) return;
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => wp_kses_post( $content ),
		] );
	}

	public function save_image_alt_text( $post_id, $alt_text ) {
		if ( empty( $alt_text ) ) return;
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}
	}

	public function get_content_for_analysis( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) return '';
		
		if ( $post->post_type === 'prompts' ) {
			$prompt_text = get_post_meta( $post_id, 'prompts-text', true );
			if ( ! empty( $prompt_text ) ) return $prompt_text;
		}
		return $post->post_content;
	}

} // End class ReadyStudio_Core_Data
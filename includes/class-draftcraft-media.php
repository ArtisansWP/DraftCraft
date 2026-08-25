<?php
/**
 * DraftCraft — Automated Featured Image Generator
 *
 * Generates a featured image via OpenRouter image models or Unsplash,
 * sideloads into the Media Library, and sets it as the post thumbnail.
 *
 * @package DraftCraft
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_Media
 */
class DraftCraft_Media {


	const PROVIDER_OPENROUTER = 'openrouter';
	const PROVIDER_UNSPLASH   = 'unsplash';
	const PROVIDER_NONE       = 'none';
	const MAX_DATA_URI_BYTES  = 5242880;
	// 5 MB.


	/**
	 * Whether featured image generation is enabled.
	 *
	 * @since  1.2.0
	 * @param  array $settings Plugin settings.
	 * @return boolean
	 */
	public static function is_enabled( array $settings ): bool {
		$provider = ( $settings['image_provider'] ?? self::PROVIDER_NONE );
		return self::PROVIDER_NONE !== $provider && ! empty( $provider );
	}//end is_enabled()


	/**
	 * Generate and attach a featured image for a post.
	 *
	 * Returns 0 on failure.
	 *
	 * @since  1.2.0
	 * @param  integer $post_id  Post ID.
	 * @param  string  $title    Post title.
	 * @param  string  $keyword  Focus keyword (optional).
	 * @param  array   $settings Plugin settings.
	 * @return integer Attachment ID, or 0 on failure.
	 */
	public static function generate_featured_image( int $post_id, string $title, string $keyword, array $settings ): int {
		if ( ! self::is_enabled( $settings ) || $post_id < 1 ) {
			return 0;
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			include_once ABSPATH . 'wp-admin/includes/media.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$provider = sanitize_key( ( $settings['image_provider'] ?? self::PROVIDER_NONE ) );
		$prompt   = self::build_image_prompt( $title, $keyword );

		/*
		 * Filter the image generation prompt.
		 *
		 * @since 1.2.0
		 * @param string $prompt   Image prompt.
		 * @param string $title    Post title.
		 * @param string $keyword  Focus keyword.
		 * @param array  $settings Plugin settings.
		 */
		$prompt = (string) apply_filters( 'draftcraft_image_prompt', $prompt, $title, $keyword, $settings );

		/*
		 * Fires before featured image generation.
		 *
		 * @since 1.2.0
		 * @param int    $post_id  Post ID.
		 * @param string $prompt   Image prompt.
		 * @param string $provider Provider slug.
		 */
		do_action( 'draftcraft_before_image_generate', $post_id, $prompt, $provider );

		$image_url = '';

		if ( self::PROVIDER_UNSPLASH === $provider ) {
			$query_term = ! empty( $keyword ) ? $keyword : $title;
			$image_url  = self::fetch_unsplash_image( $query_term, $settings );
		} elseif ( self::PROVIDER_OPENROUTER === $provider ) {
			$image_url = self::fetch_openrouter_image( $prompt, $settings );
		}

		/*
		 * Filter the remote image URL before sideload.
		 *
		 * @since 1.2.0
		 * @param string $image_url Remote URL or data URI.
		 * @param int    $post_id   Post ID.
		 * @param array  $settings  Plugin settings.
		 */
		$image_url = (string) apply_filters( 'draftcraft_image_url', $image_url, $post_id, $settings );

		if ( empty( $image_url ) || ! self::is_allowed_image_source( $image_url ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'Featured image: invalid or disallowed URL for post ' . $post_id );
			}

			return 0;
		}

		$alt_term = ! empty( $keyword ) ? $keyword : $title;
		$alt      = sanitize_text_field( $alt_term );

		$attachment_id = self::sideload_and_attach( $image_url, $post_id, $title, $alt );

		if ( $attachment_id > 0 ) {
			/*
			 * Fires after a featured image is successfully attached.
			 *
			 * @since 1.2.0
			 * @param int    $attachment_id Attachment ID.
			 * @param int    $post_id       Post ID.
			 * @param string $provider      Provider used.
			 */
			do_action( 'draftcraft_after_image_generate', $attachment_id, $post_id, $provider );
		}

		return $attachment_id;
	}//end generate_featured_image()


	/**
	 * Validate that an image URL/data-URI is safe to fetch.
	 *
	 * Blocks SSRF to internal networks; allows data URIs and known CDNs.
	 *
	 * @since  1.2.0
	 * @param  string $url URL or data URI.
	 * @return boolean
	 */
	public static function is_allowed_image_source( string $url ): bool {
		if ( 0 === strpos( $url, 'data:image/' ) ) {
			return true;
		}

		// WordPress core rejects private/reserved IPs (SSRF protection).
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		/*
		 * Optional hostname allowlist. Return null (default) to allow any
		 * URL that passes wp_http_validate_url(). Return an array of hosts
		 * to restrict further.
		 *
		 * @since 1.2.0
		 * @param string[]|null $allowed Allowed hostnames, or null for any safe URL.
		 * @param string        $url     Candidate URL.
		 */
		$allowed = apply_filters( 'draftcraft_allowed_image_hosts', null, $url );

		if ( null === $allowed ) {
			return true;
		}

		$host = strtolower( $host );
		foreach ( (array) $allowed as $allowed_host ) {
			$allowed_host = strtolower( (string) $allowed_host );
			if ( $host === $allowed_host || str_ends_with( $host, '.' . $allowed_host ) ) {
				return true;
			}
		}

		return false;
	}//end is_allowed_image_source()


	/**
	 * Build a concise image generation prompt.
	 *
	 * @since  1.2.0
	 * @param  string $title   Post title.
	 * @param  string $keyword Focus keyword.
	 * @return string
	 */
	private static function build_image_prompt( string $title, string $keyword ): string {
		if ( $keyword ) {
			return sprintf(
				'Create a blog featured image about "%s", clearly related to the topic "%s". Landscape 16:9 composition.',
				$title,
				$keyword
			);
		}

		return sprintf(
			'Create a blog featured image for the article: "%s". Landscape 16:9 composition.',
			$title
		);
	}//end build_image_prompt()


	/**
	 * Build OpenRouter image system instructions.
	 *
	 * User Image Style is preserved; DraftCraft platform rules always win on conflict.
	 *
	 * @since  1.2.1
	 * @param  array  $settings Plugin settings.
	 * @param  string $prompt   User image prompt (subject).
	 * @return string
	 */
	private static function compose_image_system_prompt( array $settings, string $prompt ): string {
		$user_style = trim( (string) ( $settings['image_system_prompt'] ?? '' ) );
		if ( '' === $user_style ) {
			$user_style = 'Clean editorial photography style with natural lighting and a realistic look.';
		}

		/*
		 * Filter the user-facing image style portion only.
		 *
		 * @since 1.2.1
		 * @param string $user_style Image style text.
		 * @param string $prompt     Subject prompt.
		 * @param array  $settings   Plugin settings.
		 */
		$user_style = trim( (string) apply_filters( 'draftcraft_image_system_prompt', $user_style, $prompt, $settings ) );
		if ( '' === $user_style ) {
			$user_style = 'Clean editorial photography style with natural lighting and a realistic look.';
		}

		$platform_rules = 'Platform rules (mandatory):
- Generate exactly one featured/hero image suitable for a blog post.
- Landscape orientation (about 16:9).
- No text, letters, numbers, logos, watermarks, UI chrome, or captions in the image.
- No borders, frames, collages, or stock-photo watermarks.
- Keep the subject clear, high quality, and safe for a general audience.
- Follow Image style for mood and aesthetics, but never break these Platform rules.';

		return "## Image style\nUse the following as visual mood and aesthetic preferences. If anything here conflicts with Platform rules, Platform rules win.\n\n" . $user_style . "\n\n## Platform rules (mandatory)\nThese requirements always apply. They take priority over Image style when there is any conflict. Do not ignore, weaken, skip, or reinterpret them.\n\n" . $platform_rules;
	}//end compose_image_system_prompt()


	/**
	 * Fetch an image URL from Unsplash Search API.
	 *
	 * @since  1.2.0
	 * @param  string $query    Search query.
	 * @param  array  $settings Plugin settings.
	 * @return string Image URL or empty string.
	 */
	private static function fetch_unsplash_image( string $query, array $settings ): string {
		$access_key = ( $settings['unsplash_access_key'] ?? '' );
		if ( empty( $access_key ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'Unsplash: access key not configured.' );
			}

			return '';
		}

		$url = add_query_arg(
			array(
				'query'          => $query,
				'per_page'       => 1,
				'orientation'    => 'landscape',
				'content_filter' => 'high',
			),
			'https://api.unsplash.com/search/photos'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'  => 'Client-ID ' . $access_key,
					'Accept-Version' => 'v1',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'Unsplash error: ' . $response->get_error_message() );
			}

			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'Unsplash HTTP ' . $code );
			}

			return '';
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$photo = $body['results'][0] ?? null;
		if ( empty( $photo ) ) {
			return '';
		}

		return ( $photo['urls']['regular'] ?? $photo['urls']['full'] ?? '' );
	}//end fetch_unsplash_image()


	/**
	 * Generate an image via OpenRouter image models.
	 *
	 * @since  1.2.0
	 * @param  string $prompt   Image prompt.
	 * @param  array  $settings Plugin settings.
	 * @return string Image URL or data-URI.
	 */
	private static function fetch_openrouter_image( string $prompt, array $settings ): string {
		$api_key = ( $settings['api_key'] ?? '' );
		$model   = sanitize_text_field( ( $settings['image_model'] ?? 'black-forest-labs/flux-1.1-pro' ) );

		if ( empty( $api_key ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'OpenRouter image: API key missing.' );
			}

			return '';
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => self::compose_image_system_prompt( $settings, $prompt ),
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$payload = array(
			'model'      => $model,
			'messages'   => $messages,
			'modalities' => array(
				'image',
				'text',
			),
		);

		/*
		 * Filter the OpenRouter image API payload.
		 *
		 * @since 1.2.0
		 * @param array  $payload  Request payload.
		 * @param string $prompt   Image prompt.
		 * @param array  $settings Plugin settings.
		 */
		$payload = (array) apply_filters( 'draftcraft_image_api_payload', $payload, $prompt, $settings );

		$response = wp_remote_post(
			DRAFTCRAFT_API_ENDPOINT,
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => get_bloginfo( 'name' ),
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'OpenRouter image error: ' . $response->get_error_message() );
			}

			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( sprintf( 'OpenRouter image HTTP %d: %s', $code, substr( $body, 0, 300 ) ) );
			}

			return '';
		}

		$decoded = json_decode( $body, true );
		$message = ( $decoded['choices'][0]['message'] ?? array() );

		if ( ! empty( $message['images'][0]['image_url']['url'] ) ) {
			return $message['images'][0]['image_url']['url'];
		}

		if ( ! empty( $message['images'][0]['url'] ) ) {
			return $message['images'][0]['url'];
		}

		$content = ( $message['content'] ?? '' );
		if ( is_string( $content ) && preg_match( '#https?://[^\s"\']+\.(?:jpg|jpeg|png|webp|gif)(?:\?[^\s"\']*)?#i', $content, $m ) ) {
			return esc_url_raw( $m[0] );
		}

		if ( is_string( $content ) && 0 === strpos( $content, 'data:image/' ) ) {
			return $content;
		}

		if ( function_exists( 'draftcraft_log' ) ) {
			draftcraft_log( 'OpenRouter image: no image URL in response.' );
		}

		return '';
	}//end fetch_openrouter_image()


	/**
	 * Sideload a remote (or data-URI) image and set as featured image.
	 *
	 * @since  1.2.0
	 * @param  string  $image_url Image URL or data URI.
	 * @param  integer $post_id   Post ID.
	 * @param  string  $title     Attachment title.
	 * @param  string  $alt       Alt text.
	 * @return integer Attachment ID or 0.
	 */
	private static function sideload_and_attach( string $image_url, int $post_id, string $title, string $alt ): int {
		if ( 0 === strpos( $image_url, 'data:image/' ) ) {
			$image_url = self::data_uri_to_temp_url( $image_url );
			if ( empty( $image_url ) ) {
				return 0;
			}
		}

		$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'media_sideload_image error: ' . $attachment_id->get_error_message() );
			}

			return 0;
		}

		$attachment_id = absint( $attachment_id );
		if ( $attachment_id < 1 ) {
			return 0;
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		update_post_meta( $post_id, '_draftcraft_featured_image', '1' );

		return $attachment_id;
	}//end sideload_and_attach()


	/**
	 * Convert a data URI to an uploads file and return its public URL.
	 *
	 * @since  1.2.0
	 * @param  string $data_uri Data URI string.
	 * @return string Public URL or empty.
	 */
	private static function data_uri_to_temp_url( string $data_uri ): string {
		if ( ! preg_match( '#^data:image/(png|jpeg|jpg|webp|gif);base64,(.+)$#i', $data_uri, $m ) ) {
			return '';
		}

		$ext = strtolower( $m[1] );
		if ( 'jpeg' === $ext ) {
			$ext = 'jpg';
		}

		// Rough size check before decode (base64 expands ~4/3).
		if ( strlen( $m[2] ) > ( self::MAX_DATA_URI_BYTES * 1.4 ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'data URI too large.' );
			}

			return '';
		}

		$data = base64_decode( $m[2], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $data || strlen( $data ) > self::MAX_DATA_URI_BYTES ) {
			return '';
		}

		$filename = 'draftcraft-' . wp_generate_password( 8, false ) . '.' . $ext;
		$upload   = wp_upload_bits( $filename, null, $data );
		if ( ! empty( $upload['error'] ) ) {
			if ( function_exists( 'draftcraft_log' ) ) {
				draftcraft_log( 'data URI upload error: ' . $upload['error'] );
			}

			return '';
		}

		return ( $upload['url'] ?? '' );
	}//end data_uri_to_temp_url()
}//end class

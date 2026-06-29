<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAIPT_Ajax {

	const GEMINI_MODEL = 'gemini-2.5-flash';
	const CURL_TIMEOUT = 30;

	public function __construct() {
		add_action( 'wp_ajax_haipt_generate_tags', array( $this, 'handle_generate_tags' ) );
	}

	public function handle_generate_tags() {
		check_ajax_referer( 'haipt_generate_tags_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'herlan-ai-product-tags' ) ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a product title first.', 'herlan-ai-product-tags' ) ) );
		}

		$tag_count = isset( $_POST['tag_count'] ) ? (int) $_POST['tag_count'] : (int) HAIPT_Settings::get( HAIPT_Settings::OPTION_DEFAULT_TAG_COUNT, HAIPT_Settings::DEFAULT_TAG_COUNT );
		$tag_count = max( 5, min( 500, $tag_count ) );

		// If no vendor_* params were sent (e.g. stale cached JS), default to using all settings-enabled vendors.
		$has_vendor_params = isset( $_POST['vendor_gemini'] ) || isset( $_POST['vendor_openrouter'] ) || isset( $_POST['vendor_groq'] );
		$vendor_selection  = array(
			'gemini'     => ! $has_vendor_params || ( isset( $_POST['vendor_gemini'] )     && '1' === $_POST['vendor_gemini'] ),
			'openrouter' => ! $has_vendor_params || ( isset( $_POST['vendor_openrouter'] ) && '1' === $_POST['vendor_openrouter'] ),
			'groq'       => ! $has_vendor_params || ( isset( $_POST['vendor_groq'] )       && '1' === $_POST['vendor_groq'] ),
		);

		$short_description = isset( $_POST['short_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['short_description'] ) ) : '';
		$description       = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$categories        = isset( $_POST['categories'] ) ? sanitize_text_field( wp_unslash( $_POST['categories'] ) ) : '';
		$sku               = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
		$regular_price     = isset( $_POST['regular_price'] ) ? sanitize_text_field( wp_unslash( $_POST['regular_price'] ) ) : '';
		$product_type      = isset( $_POST['product_type'] ) ? sanitize_text_field( wp_unslash( $_POST['product_type'] ) ) : '';
		$attributes        = isset( $_POST['attributes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['attributes'] ) ) : '';

		$prompt = $this->build_prompt( $tag_count, $title, $short_description, $description, $categories, $sku, $regular_price, $product_type, $attributes );
		$result = $this->fetch_tags_from_all_vendors( $prompt, $tag_count, $vendor_selection );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'tags' => $result ) );
	}

	private function build_prompt( $tag_count, $title, $short_description, $description, $categories, $sku, $regular_price, $product_type, $attributes = '' ) {
		$lines = array(
			"Generate exactly {$tag_count} unique product tags/keywords for the following WooCommerce product.",
			'',
			'Note: Product information may contain typos, shorthand, informal or dialect words — interpret and normalize these to standard product terms.',
			'',
			'Include a diverse mix of:',
			'- Direct product name and type keywords',
			'- Synonyms, alternative names, and semantically related terms',
			'- Names of similar or competing brand products in the same category (e.g. if the product is "Lily Shampoo", also include tags like "sunsilk shampoo", "pantene shampoo", "dove shampoo" so cross-brand searches return this product)',
			'- Related products, accessories, and complementary items customers also search for',
			'- Related product categories and variants',
			'- Common customer search phrases and buying intent keywords',
			'- Feature, benefit, and specification keywords',
			'',
			'Return ONLY a JSON array of strings. No other text, no markdown, no code fences.',
			'Rules: each tag 1-4 words, lowercase unless a brand or proper noun, no leading "#", strictly no duplicate tags.',
			'',
			'Product title: ' . $title,
		);

		if ( '' !== $product_type ) {
			$lines[] = 'Product type: ' . $product_type;
		}
		if ( '' !== $categories ) {
			$lines[] = 'Categories: ' . $categories;
		}
		if ( '' !== $attributes ) {
			$lines[] = 'Attributes: ' . $attributes;
		}
		if ( '' !== $sku ) {
			$lines[] = 'SKU: ' . $sku;
		}
		if ( '' !== $regular_price ) {
			$lines[] = 'Price: ' . $regular_price;
		}
		if ( '' !== $short_description ) {
			$lines[] = 'Short description: ' . $short_description;
		}
		if ( '' !== $description ) {
			$lines[] = 'Full description: ' . $description;
		}

		return implode( "\n", $lines );
	}

	private function fetch_tags_from_all_vendors( $prompt, $tag_count, $vendor_selection ) {
		$multi   = curl_multi_init();
		$handles = array();

		$gemini_key = HAIPT_Settings::get( HAIPT_Settings::OPTION_GEMINI_KEY );
		if ( HAIPT_Settings::is_vendor_enabled( HAIPT_Settings::OPTION_GEMINI_KEY, HAIPT_Settings::OPTION_GEMINI_ENABLED ) && ! empty( $vendor_selection['gemini'] ) ) {
			$handles['Gemini'] = $this->make_gemini_curl( $prompt, $gemini_key );
			curl_multi_add_handle( $multi, $handles['Gemini'] );
		}

		$or_key   = HAIPT_Settings::get( HAIPT_Settings::OPTION_OPENROUTER_KEY );
		$or_model = HAIPT_Settings::get( HAIPT_Settings::OPTION_OPENROUTER_MODEL, HAIPT_Settings::DEFAULT_OPENROUTER_MODEL );
		if ( HAIPT_Settings::is_vendor_enabled( HAIPT_Settings::OPTION_OPENROUTER_KEY, HAIPT_Settings::OPTION_OPENROUTER_ENABLED ) && ! empty( $vendor_selection['openrouter'] ) ) {
			$handles['OpenRouter'] = $this->make_openai_compat_curl(
				'https://openrouter.ai/api/v1/chat/completions',
				$or_key,
				$or_model,
				$prompt
			);
			curl_multi_add_handle( $multi, $handles['OpenRouter'] );
		}

		$groq_key   = HAIPT_Settings::get( HAIPT_Settings::OPTION_GROQ_KEY );
		$groq_model = HAIPT_Settings::get( HAIPT_Settings::OPTION_GROQ_MODEL, HAIPT_Settings::DEFAULT_GROQ_MODEL );
		if ( HAIPT_Settings::is_vendor_enabled( HAIPT_Settings::OPTION_GROQ_KEY, HAIPT_Settings::OPTION_GROQ_ENABLED ) && ! empty( $vendor_selection['groq'] ) ) {
			$handles['Groq'] = $this->make_openai_compat_curl(
				'https://api.groq.com/openai/v1/chat/completions',
				$groq_key,
				$groq_model,
				$prompt
			);
			curl_multi_add_handle( $multi, $handles['Groq'] );
		}

		if ( empty( $handles ) ) {
			curl_multi_close( $multi );
			return new WP_Error( 'haipt_no_vendors', __( 'No vendors selected or no API keys configured. Please check Settings → AI Product Tags.', 'herlan-ai-product-tags' ) );
		}

		$running = null;
		$start   = microtime( true );
		do {
			curl_multi_exec( $multi, $running );
			if ( $running ) {
				$select = curl_multi_select( $multi, 1.0 );
				if ( -1 === $select ) {
					usleep( 100 );
				}
			}
			if ( ( microtime( true ) - $start ) > self::CURL_TIMEOUT ) {
				break;
			}
		} while ( $running > 0 );

		$raw_tags      = array();
		$vendor_errors = array();

		foreach ( $handles as $vendor => $ch ) {
			$body      = curl_multi_getcontent( $ch );
			$info      = curl_getinfo( $ch );
			$curl_err  = curl_errno( $ch );
			$curl_msg  = curl_error( $ch );
			curl_multi_remove_handle( $multi, $ch );
			curl_close( $ch );

			$http_code = (int) $info['http_code'];

			if ( 0 !== $curl_err ) {
				$vendor_errors[] = $vendor . ': connection error — ' . $curl_msg;
				continue;
			}

			if ( 200 !== $http_code ) {
				$vendor_errors[] = $vendor . ': HTTP ' . $http_code;
				continue;
			}

			$tags = $this->parse_response( $vendor, $body );
			if ( is_array( $tags ) && ! empty( $tags ) ) {
				$raw_tags = array_merge( $raw_tags, $tags );
			} else {
				$vendor_errors[] = $vendor . ': empty or unparseable response';
			}
		}

		curl_multi_close( $multi );

		if ( empty( $raw_tags ) ) {
			$detail  = empty( $vendor_errors ) ? '' : ' (' . implode( '; ', $vendor_errors ) . ')';
			return new WP_Error( 'haipt_all_failed', __( 'All AI vendors failed.', 'herlan-ai-product-tags' ) . $detail );
		}

		return $this->sanitize_tags( $raw_tags, $tag_count );
	}

	private function ca_bundle() {
		return ABSPATH . WPINC . '/certificates/ca-bundle.crt';
	}

	private function make_gemini_curl( $prompt, $api_key ) {
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . self::GEMINI_MODEL . ':generateContent';
		$body = wp_json_encode(
			array(
				'contents'         => array(
					array( 'parts' => array( array( 'text' => $prompt ) ) ),
				),
				'generationConfig' => array(
					'temperature'      => 0.5,
					'responseMimeType' => 'application/json',
				),
			)
		);

		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO         => $this->ca_bundle(),
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'x-goog-api-key: ' . $api_key,
				'Content-Length: ' . strlen( $body ),
			),
		) );

		return $ch;
	}

	private function make_openai_compat_curl( $endpoint, $api_key, $model, $prompt ) {
		$body = wp_json_encode(
			array(
				'model'       => $model,
				'temperature' => 0.5,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are an e-commerce SEO assistant. Product info may contain typos, slang, or informal language — interpret and normalize to proper product terms. Generate diverse tags including synonyms, related terms, and names of similar/competing brand products in the same category. Return ONLY a JSON array of strings — no other text, no markdown, no code fences.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			)
		);

		$ch = curl_init( $endpoint );
		curl_setopt_array( $ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO         => $this->ca_bundle(),
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $api_key,
				'Content-Length: ' . strlen( $body ),
			),
		) );

		return $ch;
	}

	private function parse_response( $vendor, $body ) {
		$data = json_decode( $body, true );

		if ( 'Gemini' === $vendor ) {
			$text = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
				? $data['candidates'][0]['content']['parts'][0]['text']
				: '';
		} else {
			$text = isset( $data['choices'][0]['message']['content'] )
				? $data['choices'][0]['message']['content']
				: '';
		}

		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}

		$tags = json_decode( $text, true );
		if ( is_array( $tags ) ) {
			return $tags;
		}

		// Fallback: strip markdown fences if model wrapped the JSON.
		if ( preg_match( '/\[.*\]/s', $text, $matches ) ) {
			$tags = json_decode( $matches[0], true );
			if ( is_array( $tags ) ) {
				return $tags;
			}
		}

		return null;
	}

	private function sanitize_tags( $tags, $limit ) {
		$seen  = array();
		$clean = array();

		foreach ( $tags as $tag ) {
			if ( ! is_string( $tag ) ) {
				continue;
			}

			$tag = sanitize_text_field( $tag );
			$tag = ltrim( $tag, '#' );
			$tag = trim( $tag );

			if ( '' === $tag ) {
				continue;
			}

			$key = mb_strtolower( $tag );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$clean[]      = $tag;

			if ( count( $clean ) >= $limit ) {
				break;
			}
		}

		return $clean;
	}
}

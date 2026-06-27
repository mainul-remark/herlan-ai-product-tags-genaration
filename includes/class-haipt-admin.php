<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAIPT_Admin {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( 'post-new.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'haipt-admin',
			HAIPT_URL . 'assets/css/admin-style.css',
			array(),
			HAIPT_VERSION
		);

		wp_enqueue_script(
			'haipt-admin',
			HAIPT_URL . 'assets/js/admin-generate-tags.js',
			array( 'jquery', 'tags-box' ),
			HAIPT_VERSION,
			true
		);

		wp_localize_script(
			'haipt-admin',
			'HAIPT',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'haipt_generate_tags_nonce' ),
				'defaultTagCount' => (int) HAIPT_Settings::get( HAIPT_Settings::OPTION_DEFAULT_TAG_COUNT, HAIPT_Settings::DEFAULT_TAG_COUNT ),
				'vendors'         => array(
					'gemini'     => HAIPT_Settings::is_vendor_enabled( HAIPT_Settings::OPTION_GEMINI_KEY, HAIPT_Settings::OPTION_GEMINI_ENABLED ),
					'openrouter' => HAIPT_Settings::is_vendor_enabled( HAIPT_Settings::OPTION_OPENROUTER_KEY, HAIPT_Settings::OPTION_OPENROUTER_ENABLED ),
					'groq'       => HAIPT_Settings::is_vendor_enabled( HAIPT_Settings::OPTION_GROQ_KEY, HAIPT_Settings::OPTION_GROQ_ENABLED ),
				),
				'i18n'            => array(
					'vendors'     => __( 'Vendors:', 'herlan-ai-product-tags' ),
					'tagCount'    => __( 'Number of tags:', 'herlan-ai-product-tags' ),
					'generate'    => __( 'Generate Tags with AI', 'herlan-ai-product-tags' ),
					'generating'  => __( 'Generating…', 'herlan-ai-product-tags' ),
					'done'        => __( '{n} tags added.', 'herlan-ai-product-tags' ),
					'outputLabel' => __( 'Generated Tags (comma-separated, editable):', 'herlan-ai-product-tags' ),
					'placeholder' => __( 'Generated tags will appear here, comma-separated. Edit freely before saving the product.', 'herlan-ai-product-tags' ),
					'clear'       => __( '[ clear ]', 'herlan-ai-product-tags' ),
					'noTitle'     => __( 'Please enter a product title first.', 'herlan-ai-product-tags' ),
					'error'       => __( 'Could not generate tags. Please try again.', 'herlan-ai-product-tags' ),
				),
			)
		);
	}
}

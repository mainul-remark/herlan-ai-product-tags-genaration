<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAIPT_Settings {

	const CAPABILITY               = 'manage_woocommerce';

	// API keys
	const OPTION_GEMINI_KEY        = 'haipt_gemini_api_key';
	const OPTION_OPENROUTER_KEY    = 'haipt_openrouter_api_key';
	const OPTION_OPENROUTER_MODEL  = 'haipt_openrouter_model';
	const OPTION_GROQ_KEY          = 'haipt_groq_api_key';
	const OPTION_GROQ_MODEL        = 'haipt_groq_model';

	// Enable/disable per vendor
	const OPTION_GEMINI_ENABLED      = 'haipt_gemini_enabled';
	const OPTION_OPENROUTER_ENABLED  = 'haipt_openrouter_enabled';
	const OPTION_GROQ_ENABLED        = 'haipt_groq_enabled';

	// General
	const OPTION_DEFAULT_TAG_COUNT = 'haipt_default_tag_count';

	const DEFAULT_OPENROUTER_MODEL = 'meta-llama/llama-3.1-8b-instruct:free';
	const DEFAULT_GROQ_MODEL       = 'llama-3.1-8b-instant';
	const DEFAULT_TAG_COUNT        = 20;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HAIPT_PATH . 'herlan-ai-product-tags.php' ), array( $this, 'add_settings_link' ) );
	}

	public function add_settings_page() {
		add_options_page(
			__( 'AI Product Tags', 'herlan-ai-product-tags' ),
			__( 'AI Product Tags', 'herlan-ai-product-tags' ),
			self::CAPABILITY,
			'haipt-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		// Text / key fields
		foreach (
			array(
				self::OPTION_GEMINI_KEY,
				self::OPTION_OPENROUTER_KEY,
				self::OPTION_OPENROUTER_MODEL,
				self::OPTION_GROQ_KEY,
				self::OPTION_GROQ_MODEL,
			) as $option
		) {
			register_setting( 'haipt_settings_group', $option, array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			) );
		}

		// Enable/disable checkboxes (default: enabled)
		foreach (
			array(
				self::OPTION_GEMINI_ENABLED,
				self::OPTION_OPENROUTER_ENABLED,
				self::OPTION_GROQ_ENABLED,
			) as $option
		) {
			register_setting( 'haipt_settings_group', $option, array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '1',
			) );
		}

		// Default tag count
		register_setting( 'haipt_settings_group', self::OPTION_DEFAULT_TAG_COUNT, array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_tag_count' ),
			'default'           => self::DEFAULT_TAG_COUNT,
		) );
	}

	public function sanitize_checkbox( $value ) {
		return '1' === $value ? '1' : '0';
	}

	public function sanitize_tag_count( $value ) {
		return max( 5, min( 500, (int) $value ) );
	}

	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=haipt-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'herlan-ai-product-tags' ) . '</a>' );
		return $links;
	}

	public static function get( $option, $default = '' ) {
		return get_option( $option, $default );
	}

	public static function is_vendor_enabled( $vendor_key_option, $vendor_enabled_option ) {
		return ! empty( self::get( $vendor_key_option ) ) && '1' === self::get( $vendor_enabled_option, '1' );
	}

	public function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$default_count = (int) self::get( self::OPTION_DEFAULT_TAG_COUNT, self::DEFAULT_TAG_COUNT );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Product Tags — Settings', 'herlan-ai-product-tags' ); ?></h1>
			<p><?php esc_html_e( 'Configure AI vendors and defaults. Enable a vendor and add its API key to make it appear on the product edit screen. Vendors that fail (rate limit, error) are skipped automatically.', 'herlan-ai-product-tags' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'haipt_settings_group' ); ?>
				<table class="form-table" role="presentation">

					<!-- ── General ── -->
					<tr>
						<th colspan="2">
							<h2 style="margin:0;padding:8px 0 4px;border-bottom:1px solid #c3c4c7;">
								<?php esc_html_e( 'General', 'herlan-ai-product-tags' ); ?>
							</h2>
						</th>
					</tr>
					<tr>
						<th scope="row">
							<label for="haipt_default_tag_count"><?php esc_html_e( 'Default number of tags', 'herlan-ai-product-tags' ); ?></label>
						</th>
						<td>
							<input type="number" id="haipt_default_tag_count" name="<?php echo esc_attr( self::OPTION_DEFAULT_TAG_COUNT ); ?>" value="<?php echo esc_attr( $default_count ); ?>" min="5" max="500" style="width:90px;" />
							<p class="description"><?php esc_html_e( 'Pre-fills the tag count input on the product page. Min 5, max 500.', 'herlan-ai-product-tags' ); ?></p>
						</td>
					</tr>

					<!-- ── Gemini ── -->
					<tr>
						<th colspan="2">
							<h2 style="margin:0;padding:16px 0 4px;border-bottom:1px solid #c3c4c7;">
								Google Gemini (AI Studio)
							</h2>
						</th>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active', 'herlan-ai-product-tags' ); ?></th>
						<td>
							<?php $gemini_enabled = self::get( self::OPTION_GEMINI_ENABLED, '1' ); ?>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_GEMINI_ENABLED ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_GEMINI_ENABLED ); ?>" value="1" <?php checked( $gemini_enabled, '1' ); ?> />
								<?php esc_html_e( 'Enable Gemini', 'herlan-ai-product-tags' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="haipt_gemini_api_key"><?php esc_html_e( 'API Key', 'herlan-ai-product-tags' ); ?></label></th>
						<td>
							<input type="password" id="haipt_gemini_api_key" name="<?php echo esc_attr( self::OPTION_GEMINI_KEY ); ?>" value="<?php echo esc_attr( self::get( self::OPTION_GEMINI_KEY ) ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Model used: gemini-2.5-flash', 'herlan-ai-product-tags' ); ?></p>
						</td>
					</tr>

					<!-- ── OpenRouter ── -->
					<tr>
						<th colspan="2">
							<h2 style="margin:0;padding:16px 0 4px;border-bottom:1px solid #c3c4c7;">
								OpenRouter <span style="font-weight:400;font-size:13px;">(<?php esc_html_e( 'free tier available', 'herlan-ai-product-tags' ); ?>)</span>
							</h2>
						</th>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active', 'herlan-ai-product-tags' ); ?></th>
						<td>
							<?php $or_enabled = self::get( self::OPTION_OPENROUTER_ENABLED, '1' ); ?>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_OPENROUTER_ENABLED ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_OPENROUTER_ENABLED ); ?>" value="1" <?php checked( $or_enabled, '1' ); ?> />
								<?php esc_html_e( 'Enable OpenRouter', 'herlan-ai-product-tags' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="haipt_openrouter_api_key"><?php esc_html_e( 'API Key', 'herlan-ai-product-tags' ); ?></label></th>
						<td>
							<input type="password" id="haipt_openrouter_api_key" name="<?php echo esc_attr( self::OPTION_OPENROUTER_KEY ); ?>" value="<?php echo esc_attr( self::get( self::OPTION_OPENROUTER_KEY ) ); ?>" class="regular-text" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="haipt_openrouter_model"><?php esc_html_e( 'Model', 'herlan-ai-product-tags' ); ?></label></th>
						<td>
							<input type="text" id="haipt_openrouter_model" name="<?php echo esc_attr( self::OPTION_OPENROUTER_MODEL ); ?>" value="<?php echo esc_attr( self::get( self::OPTION_OPENROUTER_MODEL, self::DEFAULT_OPENROUTER_MODEL ) ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Default: meta-llama/llama-3.1-8b-instruct:free — append :free to any model slug to use the free tier.', 'herlan-ai-product-tags' ); ?><br>
								<?php esc_html_e( 'Click a model to use it:', 'herlan-ai-product-tags' ); ?><br>
								<?php
								foreach ( array(
									'meta-llama/llama-3.1-8b-instruct:free',
									'meta-llama/llama-3.2-3b-instruct:free',
									'meta-llama/llama-3.2-1b-instruct:free',
									'google/gemma-2-9b-it:free',
									'mistralai/mistral-7b-instruct:free',
									'qwen/qwen-2.5-7b-instruct:free',
									'deepseek/deepseek-r1:free',
									'deepseek/deepseek-chat-v3-0324:free',
									'microsoft/phi-3-mini-128k-instruct:free',
								) as $m ) {
									echo '<a href="#" class="haipt-model-chip" data-input="haipt_openrouter_model" data-model="' . esc_attr( $m ) . '">' . esc_html( $m ) . '</a> ';
								}
								?>
							</p>
						</td>
					</tr>

					<!-- ── Groq ── -->
					<tr>
						<th colspan="2">
							<h2 style="margin:0;padding:16px 0 4px;border-bottom:1px solid #c3c4c7;">
								Groq <span style="font-weight:400;font-size:13px;">(<?php esc_html_e( 'free tier available', 'herlan-ai-product-tags' ); ?>)</span>
							</h2>
						</th>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active', 'herlan-ai-product-tags' ); ?></th>
						<td>
							<?php $groq_enabled = self::get( self::OPTION_GROQ_ENABLED, '1' ); ?>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_GROQ_ENABLED ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_GROQ_ENABLED ); ?>" value="1" <?php checked( $groq_enabled, '1' ); ?> />
								<?php esc_html_e( 'Enable Groq', 'herlan-ai-product-tags' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="haipt_groq_api_key"><?php esc_html_e( 'API Key', 'herlan-ai-product-tags' ); ?></label></th>
						<td>
							<input type="password" id="haipt_groq_api_key" name="<?php echo esc_attr( self::OPTION_GROQ_KEY ); ?>" value="<?php echo esc_attr( self::get( self::OPTION_GROQ_KEY ) ); ?>" class="regular-text" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="haipt_groq_model"><?php esc_html_e( 'Model', 'herlan-ai-product-tags' ); ?></label></th>
						<td>
							<input type="text" id="haipt_groq_model" name="<?php echo esc_attr( self::OPTION_GROQ_MODEL ); ?>" value="<?php echo esc_attr( self::get( self::OPTION_GROQ_MODEL, self::DEFAULT_GROQ_MODEL ) ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Default: llama-3.1-8b-instant', 'herlan-ai-product-tags' ); ?><br>
								<?php esc_html_e( 'Click a model to use it:', 'herlan-ai-product-tags' ); ?><br>
								<?php
								foreach ( array(
									'llama-3.1-8b-instant',
									'llama-3.1-70b-versatile',
									'llama-3.3-70b-versatile',
									'llama-3.3-70b-specdec',
									'gemma2-9b-it',
									'gemma-7b-it',
									'mixtral-8x7b-32768',
								) as $m ) {
									echo '<a href="#" class="haipt-model-chip" data-input="haipt_groq_model" data-model="' . esc_attr( $m ) . '">' . esc_html( $m ) . '</a> ';
								}
								?>
							</p>
						</td>
					</tr>

				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<style>
			.haipt-model-chip {
				display: inline-block;
				margin: 3px 4px 3px 0;
				padding: 2px 8px;
				background: #f0f0f1;
				border: 1px solid #c3c4c7;
				border-radius: 3px;
				font-size: 12px;
				font-family: monospace;
				text-decoration: none;
				color: #1d2327;
				line-height: 1.6;
			}
			.haipt-model-chip:hover {
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
			}
		</style>
		<script>
		jQuery( function( $ ) {
			$( '.haipt-model-chip' ).on( 'click', function( e ) {
				e.preventDefault();
				$( '#' + $( this ).data( 'input' ) ).val( $( this ).data( 'model' ) );
			} );
		} );
		</script>
		<?php
	}
}

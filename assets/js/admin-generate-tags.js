( function ( $ ) {
	'use strict';

	$( function () {
		var $box = $( '#tagsdiv-product_tag' );
		if ( ! $box.length || typeof HAIPT === 'undefined' ) return;

		// Hidden textarea WP reads on save (name="tax_input[product_tag]")
		var $taxInput = $( '#tax-input-product_tag' );

		// ── Vendor checkboxes (only for vendors with an API key set) ──
		var vendorLabels = { gemini: 'Gemini', openrouter: 'OpenRouter', groq: 'Groq' };
		var $vendorRow   = $( '<p class="haipt-vendors-row"></p>' ).append(
			'<span class="haipt-row-label">' + HAIPT.i18n.vendors + '</span>'
		);

		var configuredCount = 0;
		$.each( HAIPT.vendors, function ( key, hasKey ) {
			if ( ! hasKey ) return;
			configuredCount++;
			$vendorRow.append(
				$( '<label class="haipt-vendor-label"></label>' ).append(
					$( '<input type="checkbox" class="haipt-vendor-cb">' )
						.attr( 'data-vendor', key )
						.prop( 'checked', true )
				).append( ' ' + vendorLabels[ key ] )
			);
		} );

		if ( ! configuredCount ) return; // No vendors configured — do nothing.

		// ── Tag count input ───────────────────────────────────────────
		var $countRow = $( '<p class="haipt-count-row"></p>' ).append(
			$( '<label></label>' )
				.append( '<span class="haipt-row-label">' + HAIPT.i18n.tagCount + '</span>' )
				.append(
					$( '<input type="number" id="haipt-tag-count" min="5" max="500">' )
						.val( HAIPT.defaultTagCount )
				)
		);

		// ── Generate button + status ──────────────────────────────────
		var $generateBtn = $( '<button type="button" class="button button-primary haipt-gen-btn"></button>' )
			.text( HAIPT.i18n.generate );
		var $status  = $( '<span class="haipt-status"></span>' );
		var $btnRow  = $( '<p class="haipt-btn-row"></p>' )
			.append( $generateBtn )
			.append( $status );

		// ── Output textarea ───────────────────────────────────────────
		var $outputLabel = $( '<p class="haipt-output-label"></p>' ).html(
			'<strong>' + HAIPT.i18n.outputLabel + '</strong>' +
			' <a href="#" class="haipt-clear">' + HAIPT.i18n.clear + '</a>'
		);

		var $outputArea = $( '<textarea id="haipt-tags-output" rows="7" spellcheck="false"></textarea>' )
			.attr( 'placeholder', HAIPT.i18n.placeholder );

		// Pre-fill from existing saved tags on the product
		if ( $taxInput.length && $taxInput.val() ) {
			$outputArea.val( $taxInput.val() );
		}

		// Sync edits in our textarea → hidden WP tax-input field (so tags save on product update)
		$outputArea.on( 'input', function () {
			if ( $taxInput.length ) {
				$taxInput.val( $( this ).val() );
			}
			// Also target #postdivrich if it is a textarea (some setups use this)
			var $pd = $( '#postdivrich' );
			if ( $pd.is( 'textarea' ) ) {
				$pd.val( $( this ).val() );
			}
		} );

		// ── Inject into metabox (before the default WP tag input row) ─
		$box.find( '.jaxtag' ).before(
			$( '<div class="haipt-ui"></div>' )
				.append( $vendorRow )
				.append( $countRow )
				.append( $btnRow )
				.append( $outputLabel )
				.append( $outputArea )
		);

		// ── Clear button ──────────────────────────────────────────────
		$outputLabel.find( '.haipt-clear' ).on( 'click', function ( e ) {
			e.preventDefault();
			$outputArea.val( '' ).trigger( 'input' );
		} );

		// ── Helpers ───────────────────────────────────────────────────
		function getEditorContent( id ) {
			if ( typeof tinymce !== 'undefined' ) {
				var ed = tinymce.get( id );
				if ( ed && ! ed.isHidden() ) {
					return ed.getContent( { format: 'text' } );
				}
			}
			return $( '#' + id ).val() || '';
		}

		function getCheckedCategories() {
			var labels = [];
			$( '#product_catchecklist input:checked' ).each( function () {
				labels.push( $.trim( $( this ).parent().text() ) );
			} );
			return labels.join( ', ' );
		}

		function getProductAttributes() {
			var attrs = [];
			$( '.woocommerce_attribute' ).each( function () {
				var $row  = $( this );
				var name  = $.trim( $row.find( 'input[name^="attribute_names"]' ).val() || '' );
				if ( ! name ) return;
				var values = [];
				$row.find( 'select[name^="attribute_values"] option:selected' ).each( function () {
					values.push( $.trim( $( this ).text() ) );
				} );
				if ( ! values.length ) {
					var raw = $row.find( 'textarea[name^="attribute_values"]' ).val() || '';
					if ( raw ) {
						values = raw.split( '|' ).map( function ( v ) { return $.trim( v ); } ).filter( Boolean );
					}
				}
				attrs.push( name + ( values.length ? ': ' + values.join( ', ' ) : '' ) );
			} );
			return attrs.join( ' | ' );
		}

		function getVendorSelections() {
			var out = {};
			$( '.haipt-vendor-cb' ).each( function () {
				out[ 'vendor_' + $( this ).data( 'vendor' ) ] = $( this ).is( ':checked' ) ? '1' : '0';
			} );
			return out;
		}

		// ── Generate handler ──────────────────────────────────────────
		$generateBtn.on( 'click', function ( e ) {
			e.preventDefault();

			var title = $( '#title' ).val() || '';
			if ( ! title ) {
				$status.text( HAIPT.i18n.noTitle );
				return;
			}

			var tagCount = parseInt( $( '#haipt-tag-count' ).val(), 10 );
			if ( isNaN( tagCount ) || tagCount < 5 ) tagCount = 20;
			if ( tagCount > 500 ) tagCount = 500;

			$generateBtn.prop( 'disabled', true );
			$status.text( HAIPT.i18n.generating );

			var data = $.extend(
				{
					action            : 'haipt_generate_tags',
					nonce             : HAIPT.nonce,
					title             : title,
					tag_count         : tagCount,
					short_description : getEditorContent( 'excerpt' ),
					description       : getEditorContent( 'content' ),
					categories        : getCheckedCategories(),
					sku               : $( '#_sku' ).val() || '',
					regular_price     : $( '#_regular_price' ).val() || '',
					product_type      : $( '#product-type' ).val() || '',
					attributes        : getProductAttributes()
				},
				getVendorSelections()
			);

			$.post( HAIPT.ajaxUrl, data )
				.done( function ( response ) {
					if ( response && response.success && response.data && response.data.tags ) {
						var newTags  = response.data.tags;

						// Merge with existing tags in the textarea (append, deduplicate)
						var existing = $outputArea.val().trim();
						var current  = existing
							? existing.split( ',' ).map( function ( t ) { return t.trim(); } ).filter( Boolean )
							: [];

						newTags.forEach( function ( tag ) {
							var tl = tag.toLowerCase();
							var isDupe = current.some( function ( t ) { return t.toLowerCase() === tl; } );
							if ( ! isDupe ) current.push( tag );
						} );

						$outputArea.val( current.join( ', ' ) ).trigger( 'input' );
						$status.text( HAIPT.i18n.done.replace( '{n}', newTags.length ) );
					} else {
						var msg = ( response && response.data && response.data.message )
							? response.data.message
							: HAIPT.i18n.error;
						$status.text( msg );
					}
				} )
				.fail( function () {
					$status.text( HAIPT.i18n.error );
				} )
				.always( function () {
					$generateBtn.prop( 'disabled', false );
					setTimeout( function () { $status.text( '' ); }, 8000 );
				} );
		} );
	} );
} )( jQuery );

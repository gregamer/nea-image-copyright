( function ( $ ) {
	'use strict';

	var FIELD_NAME = 'nea_image_author';
	var LABEL = 'Avtor';

	/**
	 * In the media modal, WordPress renders attachment_fields_to_edit inside
	 * .attachment-compat (below Description). Clone the Avtor field above
	 * Alternative text, hide the original compat row, and keep them in sync
	 * so saving still goes through the standard attachment fields flow.
	 */
	function repositionAuthorField( $container ) {
		if ( ! $container || ! $container.length ) {
			return;
		}

		var $compat = $container.find( '.attachment-compat' );
		if ( ! $compat.length ) {
			return;
		}

		var $originalRow = $compat.find(
			'tr.compat-field-' + FIELD_NAME + ', .compat-field-' + FIELD_NAME
		);
		if ( ! $originalRow.length ) {
			return;
		}

		var $altSetting = $container.find( '[data-setting="alt"]' ).first();
		if ( ! $altSetting.length ) {
			return;
		}

		if ( $altSetting.prev( '.nea-image-author-setting' ).length ) {
			syncExistingClone( $altSetting.prev( '.nea-image-author-setting' ), $originalRow );
			return;
		}

		var $originalInput = $originalRow
			.find( 'input[type="text"], input[name*="' + FIELD_NAME + '"], textarea' )
			.first();
		if ( ! $originalInput.length ) {
			return;
		}

		$originalRow.hide();

		var $clone = $(
			'<span class="setting nea-image-author-setting" data-setting="nea_image_author">' +
				'<label for="nea-image-author-clone" class="name">' +
				LABEL +
				'</label>' +
				'<input type="text" id="nea-image-author-clone" value="" />' +
				'</span>'
		);

		var $cloneInput = $clone.find( 'input' );
		$cloneInput.val( $originalInput.val() || '' );

		$cloneInput.on( 'input change', function () {
			$originalInput.val( $cloneInput.val() ).trigger( 'change' );
		} );

		$originalInput.on( 'input change', function () {
			if ( $cloneInput.val() !== $originalInput.val() ) {
				$cloneInput.val( $originalInput.val() );
			}
		} );

		$altSetting.before( $clone );
	}

	function syncExistingClone( $clone, $originalRow ) {
		var $cloneInput = $clone.find( 'input' );
		var $originalInput = $originalRow
			.find( 'input[type="text"], input[name*="' + FIELD_NAME + '"], textarea' )
			.first();
		if ( $cloneInput.length && $originalInput.length ) {
			$originalRow.hide();
			if ( document.activeElement !== $cloneInput[ 0 ] ) {
				$cloneInput.val( $originalInput.val() || '' );
			}
		}
	}

	function observeDetails( el ) {
		if ( ! el || el._neaAuthorObserved ) {
			return;
		}
		el._neaAuthorObserved = true;

		var observer = new MutationObserver( function () {
			repositionAuthorField( $( el ) );
		} );

		observer.observe( el, { childList: true, subtree: true } );
		repositionAuthorField( $( el ) );
	}

	function bindMediaFrame() {
		if ( typeof wp === 'undefined' || ! wp.media || ! wp.media.view ) {
			return;
		}

		var AttachmentDetails = wp.media.view.Attachment.Details;
		if ( ! AttachmentDetails ) {
			return;
		}

		var originalRender = AttachmentDetails.prototype.render;

		AttachmentDetails.prototype.render = function () {
			var result = originalRender.apply( this, arguments );
			var view = this;

			setTimeout( function () {
				observeDetails( view.el );
				repositionAuthorField( view.$el );
			}, 0 );

			setTimeout( function () {
				repositionAuthorField( view.$el );
			}, 150 );

			return result;
		};

		// Two-column media library details view.
		if ( wp.media.view.Attachment.Details.TwoColumn ) {
			var TwoColumn = wp.media.view.Attachment.Details.TwoColumn;
			var originalTwoColumnRender = TwoColumn.prototype.render;

			TwoColumn.prototype.render = function () {
				var result = originalTwoColumnRender.apply( this, arguments );
				var view = this;

				setTimeout( function () {
					observeDetails( view.el );
					repositionAuthorField( view.$el );
				}, 0 );

				setTimeout( function () {
					repositionAuthorField( view.$el );
				}, 150 );

				return result;
			};
		}
	}

	$( bindMediaFrame );
} )( jQuery );

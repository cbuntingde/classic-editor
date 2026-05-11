( function( $ ) {
	if ( ! $ ) {
		return;
	}

	function getEditorFromUrl() {
		const params = new URLSearchParams( window.location.search );
		return params.has( 'classic-editor' ) ? 'classic' : 'block';
	}

	function updateToolbarButton() {
		if ( ! $( '#classic-editor-toolbar-switch' ).length ) {
			return;
		}

		const isClassic = getEditorFromUrl() === 'classic';
		let $btn = $( '#classic-editor-toolbar-switch' );

		if ( isClassic ) {
			$btn.html( classicEditorData.labels.switchToBlock );
			$btn.attr( 'aria-label', classicEditorData.labels.switchToBlockAria );
		} else {
			$btn.html( classicEditorData.labels.switchToClassic );
			$btn.attr( 'aria-label', classicEditorData.labels.switchToClassicAria );
		}
	}

	function initToolbarButton() {
		const $body = $( 'body' );
		const isClassic = getEditorFromUrl() === 'classic';

		if ( $body.hasClass( 'block-editor-page' ) && classicEditorData.allowUsers ) {
			const label = isClassic ? classicEditorData.labels.switchToBlock : classicEditorData.labels.switchToClassic;
			const ariaLabel = isClassic ? classicEditorData.labels.switchToBlockAria : classicEditorData.labels.switchToClassicAria;

			$( '.edit-post-header__toolbar' ).append(
				'<button id="classic-editor-toolbar-switch" class="components-button editor-post__switch-to-classic is-secondary" aria-label="' + ariaLabel + '">' + label + '</button>'
			);

			$( '#classic-editor-toolbar-switch' ).on( 'click', function( e ) {
				e.preventDefault();
				const url = new URL( window.location.href );

				if ( getEditorFromUrl() === 'classic' ) {
					url.searchParams.delete( 'classic-editor' );
					url.searchParams.set( 'classic-editor__forget', '1' );
				} else {
					url.searchParams.set( 'classic-editor', '1' );
				}

				window.location.href = url.toString();
			} );
		}

		if ( $body.hasClass( 'post-php' ) || $body.hasClass( 'post-new-php' ) ) {
			const $title = $( '#title' );

			if ( $title.length && classicEditorData.allowUsers ) {
				const hint = classicEditorData.shortcutHint;
				if ( ! window.localStorage.getItem( 'classic-editor-hint-shown' ) ) {
					setTimeout( function() {
						$( '#title' ).after( '<p class="classic-editor-hint" style="color: #646970; font-size: 13px; margin-top: -10px; margin-bottom: 20px;">' + hint + '</p>' );
					}, 1000 );
				}
			}
		}

		updateToolbarButton();
	}

	$( document ).ready( function() {
		initToolbarButton();

		$( document ).on( 'keydown', function( e ) {
			if ( e.altKey && e.shiftKey && e.key.toLowerCase() === 'e' ) {
				e.preventDefault();
				const url = new URL( window.location.href );

				if ( getEditorFromUrl() === 'classic' ) {
					url.searchParams.delete( 'classic-editor' );
					url.searchParams.set( 'classic-editor__forget', '1' );
				} else {
					url.searchParams.set( 'classic-editor', '1' );
				}

				window.location.href = url.toString();
			}
		} );
	} );

	$( window ).on( 'popstate', function() {
		updateToolbarButton();
	} );
} )( window.jQuery );
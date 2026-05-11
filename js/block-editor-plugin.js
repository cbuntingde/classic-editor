( function( wp ) {
	if ( ! wp ) {
		return;
	}

	wp.plugins.registerPlugin( 'classic-editor-plugin', {
		render: function() {
			const createElement = wp.element.createElement;
			const PluginMoreMenuItem = wp.editPost.PluginMoreMenuItem;
			const url = wp.url.addQueryArgs( document.location.href, { 'classic-editor': '', 'classic-editor__forget': '' } );
			const linkText = lodash.get( window, [ 'classicEditorPluginL10n', 'linkText' ] ) || 'Switch to classic editor';

			return createElement(
				PluginMoreMenuItem,
				{
					icon: 'editor-kitchensink',
					href: url,
				},
				linkText
			);
		},
	} );
} )( window.wp );

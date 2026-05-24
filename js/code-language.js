( function ( wp ) {
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { Fragment, createElement: el } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;

	// The list of choices is built server-side from the active baseline +
	// language packs (Tools → Code Blocks → Language packs) and injected
	// via wp_add_inline_script as window.cbeLanguageChoices. Each entry
	// is { value, label } and the empty value is the "None" option.
	const LANGUAGES = Array.isArray( window.cbeLanguageChoices ) && window.cbeLanguageChoices.length
		? window.cbeLanguageChoices
		: [ { value: '', label: 'None (plain text)' } ];

	// 1. Register a `language` attribute on the core Code block.
	addFilter(
		'blocks.registerBlockType',
		'cbe/code-language-attribute',
		function ( settings, name ) {
			if ( name !== 'core/code' ) {
				return settings;
			}
			settings.attributes = Object.assign( {}, settings.attributes, {
				language: { type: 'string', default: '' },
			} );
			return settings;
		}
	);

	// 2. Add a language dropdown to the Code block sidebar.
	const withLanguageControl = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( props.name !== 'core/code' ) {
				return el( BlockEdit, props );
			}
			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Code language', initialOpen: true },
						el( SelectControl, {
							label: 'Syntax highlighting',
							value: props.attributes.language,
							options: LANGUAGES,
							onChange: function ( value ) {
								props.setAttributes( { language: value } );
							},
						} )
					)
				)
			);
		};
	}, 'withLanguageControl' );

	addFilter(
		'editor.BlockEdit',
		'cbe/code-language-control',
		withLanguageControl
	);
} )( window.wp );

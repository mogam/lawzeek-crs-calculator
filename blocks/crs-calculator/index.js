/**
 * Editor side of the "CRS Calculator" block. Shows a placeholder card in the editor;
 * the real calculator is rendered on the site by PHP.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'lawzeek/crs-calculator', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps();
			return el(
				'div',
				blockProps,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Calculator', 'lawzeek-crs-calculator' ) },
						el( components.TextControl, {
							label: __( 'Heading', 'lawzeek-crs-calculator' ),
							help: __( 'Leave empty to use “Check your CRS score”.', 'lawzeek-crs-calculator' ),
							value: props.attributes.title,
							onChange: function ( v ) { props.setAttributes( { title: v } ); },
						} )
					)
				),
				el(
					components.Placeholder,
					{
						icon: 'calculator',
						label: __( 'CRS Calculator', 'lawzeek-crs-calculator' ),
						instructions: __( 'Visitors will see the CRS calculator here. Leads appear under CRS Leads in your dashboard.', 'lawzeek-crs-calculator' ),
					}
				)
			);
		},
		save: function () {
			return null; // Rendered by PHP.
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );

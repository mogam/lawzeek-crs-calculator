/**
 * Editor side of the "CRS Calculator" block. Shows a placeholder card in the editor;
 * the real calculator is rendered on the site by PHP.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	// The Lawzeek logo as the block icon.
	var logo = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 100 100', width: 24, height: 24, 'aria-hidden': 'true' },
		el( 'rect', { width: 100, height: 100, rx: 10, fill: '#2a2219' } ),
		el(
			'g',
			{ transform: 'translate(8 8) scale(0.84)' },
			el( 'path', { d: 'M18 38H34M18 62H34', fill: 'none', stroke: '#f5bc41', strokeWidth: 7.5, strokeLinecap: 'round' } ),
			el( 'path', { d: 'M72 50H81', fill: 'none', stroke: '#7473ed', strokeWidth: 7.5, strokeLinecap: 'round' } ),
			el( 'path', { d: 'M34 26H48A24 24 0 0 1 48 74H34Z', fill: 'none', stroke: '#f7f4ed', strokeWidth: 7.5, strokeLinejoin: 'round' } ),
			el( 'circle', { cx: 84, cy: 50, r: 6, fill: '#7473ed' } )
		)
	);

	blocks.registerBlockType( 'lawzeek/crs-calculator', {
		icon: logo,
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
						icon: logo,
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

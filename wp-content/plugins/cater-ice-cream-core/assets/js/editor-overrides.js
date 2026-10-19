/**
 * Block editor: "Cater style overrides" panel for section and element blocks.
 * Stores sparse values in the cicStyle attribute (empty = inherit). The server validates the same
 * allowlist and applies the values as inline custom properties on that block instance only.
 * Plain JavaScript using WordPress packages; no build step.
 */
( function ( wp, data ) {
	'use strict';
	if ( ! wp || ! data || ! wp.hooks || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var addFilter = wp.hooks.addFilter;
	var createHOC = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var C = wp.components;
	var __ = wp.i18n.__;

	var scopes = data.blockScopes;
	var colorByKey = {};
	data.colors.forEach( function ( c ) { colorByKey[ c.key ] = c; } );
	var fontByKey = {};
	data.fonts.forEach( function ( f ) { fontByKey[ f.key ] = f; } );
	var brandRoles = data.contexts.filter( function ( c ) { return 'brand' === c.key; } )[ 0 ].roles;
	var HEX = /^#[0-9A-F]{6}$/i;

	var fieldsFor = function ( name ) {
		var scope = scopes[ name ];
		return 'section' === scope ? data.scopeFields : data.elementFields[ scope ];
	};
	var typeRole = function ( name, attrs ) {
		var scope = scopes[ name ];
		if ( 'heading' === scope ) {
			return 'h' + Math.min( 4, Math.max( 1, attrs.level || 2 ) );
		}
		return 'text' === scope ? 'body' : null;
	};
	var colorValue = function ( ref ) {
		if ( colorByKey[ ref ] ) {
			return 'var(--wp--preset--color--' + colorByKey[ ref ].slug + ')';
		}
		return HEX.test( ref ) ? ref : null;
	};
	var colorLabel = function ( ref ) {
		return colorByKey[ ref ] ? colorByKey[ ref ].label + ' — ' + colorByKey[ ref ].hex : ref;
	};

	/* Same mapping as CIC\Design\Overrides::declarations(), for the editor preview. */
	var declarations = function ( name, attrs ) {
		var values = attrs.cicStyle || {};
		var style = {};
		var classes = [];
		var scope = scopes[ name ];
		var role = typeRole( name, attrs );
		Object.keys( values ).forEach( function ( key ) {
			var value = values[ key ];
			if ( '' === value || null === value || undefined === value ) {
				return;
			}
			if ( 'context' === key ) {
				classes.push( 'cic-ctx', 'cic-context-' + value );
				return;
			}
			if ( data.roles[ key ] ) {
				var css = colorValue( value );
				if ( css ) {
					style[ '--cic-color-' + key.replace( /_/g, '-' ) ] = css;
				}
				if ( 'section' === scope && ( 'surface' === key || 'text' === key ) ) {
					classes.push( 'cic-ctx' );
				}
				return;
			}
			if ( 'font_heading' === key || 'font_body' === key ) {
				if ( fontByKey[ value ] ) {
					style[ '--cic-' + key.replace( '_', '-' ) ] = 'var(--wp--preset--font-family--' + fontByKey[ value ].slug + ')';
				}
				return;
			}
			if ( 'heading_weight' === key ) {
				style[ '--cic-heading-weight' ] = String( value );
				return;
			}
			if ( 'heading_accent_style' === key ) {
				style[ '--cic-heading-accent-style' ] = value;
				return;
			}
			var m = /^(h1|h2|h3|body)_(min|max)$/.exec( key );
			if ( m ) {
				var t = data.type[ m[ 1 ] ];
				style[ '--cic-fs-' + m[ 1 ] + '-' + m[ 2 ] ] = String( value );
				style[ '--cic-fs-' + m[ 1 ] + '-lh-' + m[ 2 ] ] = String( Math.round( value * t[ 'lh_' + m[ 2 ] ] / t[ m[ 2 ] ] ) );
			}
		} );
		if ( role && ( values.size_min || values.size_max ) ) {
			var r = data.type[ role ];
			classes.push( 'cic-has-size' );
			[ 'min', 'max' ].forEach( function ( edge ) {
				var size = values[ 'size_' + edge ];
				if ( size ) {
					style[ '--cic-fs-' + edge ] = String( size );
					style[ '--cic-lh-' + edge ] = String( Math.round( size * r[ 'lh_' + edge ] / r[ edge ] ) );
				} else {
					style[ '--cic-fs-' + edge ] = 'var(--cic-fs-' + role + '-' + edge + ')';
					style[ '--cic-lh-' + edge ] = 'var(--cic-fs-' + role + '-lh-' + edge + ')';
				}
			} );
		}
		return { style: style, classes: classes };
	};

	/* 1. Attribute on the client (the server registers it too). */
	addFilter( 'blocks.registerBlockType', 'cic/style-attribute', function ( settings, name ) {
		if ( ! scopes[ name ] ) {
			return settings;
		}
		settings.attributes = Object.assign( {}, settings.attributes, { cicStyle: { type: 'object' } } );
		return settings;
	} );

	var inheritText = function ( name, attrs, key, def ) {
		switch ( def.type ) {
			case 'context':
				return __( 'Inherits the page preset (site default: Default brand).', 'cater-ice-cream-core' );
			case 'color':
				return __( 'Inherits from the surrounding preset. Brand default:', 'cater-ice-cream-core' ) + ' ' + colorLabel( brandRoles[ def.role ] );
			case 'font':
				return __( 'Inherits the global font:', 'cater-ice-cream-core' ) + ' ' + fontByKey[ data.fontHeading ].label;
			case 'weight':
				return __( 'Inherits the global or preset heading weight.', 'cater-ice-cream-core' );
			case 'select':
				return __( 'Inherits the global or preset style.', 'cater-ice-cream-core' );
			case 'size':
				var role = def.type_role || typeRole( name, attrs );
				return role ? __( 'Inherits:', 'cater-ice-cream-core' ) + ' ' + data.type[ role ][ def.edge ] + ' px' : '';
		}
		return '';
	};

	var control = function ( name, attrs, key, def, value, set ) {
		var label = def.label;
		switch ( def.type ) {
			case 'context':
				return el( C.SelectControl, {
					label: label,
					value: value || '',
					options: [ { label: __( 'Inherit', 'cater-ice-cream-core' ), value: '' } ].concat( data.contexts.map( function ( c ) { return { label: c.label, value: c.key }; } ) ),
					onChange: set,
					__nextHasNoMarginBottom: true
				} );
			case 'color':
				var isCustom = value && HEX.test( value );
				var children = [
					el( C.SelectControl, {
						key: 'select',
						label: label,
						value: isCustom ? 'custom' : ( value || '' ),
						options: [ { label: __( 'Inherit', 'cater-ice-cream-core' ), value: '' } ]
							.concat( data.colors.map( function ( c ) { return { label: c.label + ' — ' + c.hex, value: c.key }; } ) )
							.concat( [ { label: __( 'Custom colour…', 'cater-ice-cream-core' ), value: 'custom' } ] ),
						onChange: function ( choice ) {
							if ( 'custom' === choice ) {
								set( colorByKey[ brandRoles[ def.role ] ].hex );
							} else {
								set( choice );
							}
						},
						__nextHasNoMarginBottom: true
					} )
				];
				if ( isCustom ) {
					children.push( el( C.TextControl, {
						key: 'hex',
						label: __( 'Custom hex', 'cater-ice-cream-core' ),
						value: value,
						maxLength: 7,
						onChange: function ( hex ) {
							if ( HEX.test( hex ) ) {
								set( hex.toUpperCase() );
							}
						},
						__nextHasNoMarginBottom: true
					} ) );
				}
				return el( Fragment, null, children );
			case 'font':
				return el( C.SelectControl, {
					label: label,
					value: value || '',
					options: [ { label: __( 'Inherit', 'cater-ice-cream-core' ), value: '' } ].concat( data.fonts.map( function ( f ) { return { label: f.label, value: f.key }; } ) ),
					onChange: set,
					__nextHasNoMarginBottom: true
				} );
			case 'weight':
				var family = ( attrs.cicStyle && attrs.cicStyle.font_heading ) || data.fontHeading;
				return el( C.SelectControl, {
					label: label,
					value: value ? String( value ) : '',
					options: [ { label: __( 'Inherit', 'cater-ice-cream-core' ), value: '' } ].concat( fontByKey[ family ].weights.map( function ( w ) { return { label: String( w ), value: String( w ) }; } ) ),
					onChange: function ( w ) { set( w ? parseInt( w, 10 ) : '' ); },
					__nextHasNoMarginBottom: true
				} );
			case 'select':
				return el( C.SelectControl, {
					label: label,
					value: value || '',
					options: [ { label: __( 'Inherit', 'cater-ice-cream-core' ), value: '' } ].concat( Object.keys( def.choices ).map( function ( k ) { return { label: def.choices[ k ], value: k }; } ) ),
					onChange: set,
					__nextHasNoMarginBottom: true
				} );
			case 'size':
				return el( C.TextControl, {
					label: label,
					type: 'number',
					min: data.bounds.min,
					max: data.bounds.max,
					value: value || '',
					onChange: function ( raw ) {
						var n = parseInt( raw, 10 );
						if ( '' === raw ) {
							set( '' );
						} else if ( n >= data.bounds.min && n <= data.bounds.max ) {
							set( n );
						}
					},
					__nextHasNoMarginBottom: true
				} );
		}
		return null;
	};

	/* 2. Inspector panel. */
	var withPanel = createHOC( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! scopes[ props.name ] ) {
				return el( BlockEdit, props );
			}
			var fields = fieldsFor( props.name );
			var values = props.attributes.cicStyle || {};
			var activeCount = Object.keys( values ).filter( function ( k ) { return fields[ k ] && '' !== values[ k ]; } ).length;
			var update = function ( key, value ) {
				var next = Object.assign( {}, values );
				if ( '' === value || null === value || undefined === value ) {
					delete next[ key ];
				} else {
					next[ key ] = value;
				}
				props.setAttributes( { cicStyle: Object.keys( next ).length ? next : undefined } );
			};
			var rows = Object.keys( fields ).map( function ( key ) {
				var def = fields[ key ];
				var value = values[ key ];
				var active = undefined !== value && '' !== value;
				return el( 'div', { key: key, className: 'cic-ov-field' + ( active ? ' is-active' : '' ), style: { marginBottom: '16px' } },
					control( props.name, props.attributes, key, def, value, function ( v ) { update( key, v ); } ),
					el( 'p', { style: { margin: '4px 0 0', fontSize: '12px', color: '#50575e' } },
						active ?
							el( Fragment, null,
								el( 'strong', { style: { color: '#7a1f4a' } }, __( 'Override active', 'cater-ice-cream-core' ) ), ' · ',
								el( C.Button, { variant: 'link', onClick: function () { update( key, '' ); } }, __( 'Reset to inherited', 'cater-ice-cream-core' ) )
							) :
							inheritText( props.name, props.attributes, key, def )
					)
				);
			} );
			var title = __( 'Cater style overrides', 'cater-ice-cream-core' ) + ( activeCount ? ' (' + activeCount + ')' : '' );
			return el( Fragment, null,
				el( BlockEdit, props ),
				el( InspectorControls, null,
					el( C.PanelBody, { title: title, initialOpen: activeCount > 0 },
						el( 'p', { style: { fontSize: '12px' } }, __( 'Everything inherits from the Design System. Set a field only for a deliberate exception on this block.', 'cater-ice-cream-core' ) ),
						rows,
						activeCount ? el( C.Button, { variant: 'secondary', isDestructive: true, onClick: function () { props.setAttributes( { cicStyle: undefined } ); } }, __( 'Reset all overrides on this block', 'cater-ice-cream-core' ) ) : null
					)
				)
			);
		};
	}, 'withCicStylePanel' );
	addFilter( 'editor.BlockEdit', 'cic/style-panel', withPanel );

	/* 3. Canvas preview with the same custom properties the server prints. */
	var withPreview = createHOC( function ( BlockListBlock ) {
		return function ( props ) {
			if ( ! scopes[ props.name ] || ! props.attributes.cicStyle ) {
				return el( BlockListBlock, props );
			}
			var d = declarations( props.name, props.attributes );
			var wrapperProps = Object.assign( {}, props.wrapperProps );
			wrapperProps.style = Object.assign( {}, wrapperProps.style || {}, d.style );
			return el( BlockListBlock, Object.assign( {}, props, {
				wrapperProps: wrapperProps,
				className: [ props.className, 'cic-has-override' ].concat( d.classes ).filter( Boolean ).join( ' ' )
			} ) );
		};
	}, 'withCicStylePreview' );
	addFilter( 'editor.BlockListBlock', 'cic/style-preview', withPreview );
}( window.wp, window.cicDesign ) );

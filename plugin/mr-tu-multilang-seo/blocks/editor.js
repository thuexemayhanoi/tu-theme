/**
 * Mr Tu business blocks — editor UI without a build step.
 *
 * Block metadata (title, attributes, supports) comes from each blocks/<name>/block.json, which
 * WordPress bootstraps into the editor. Here we only add the sidebar controls and a live
 * server-side preview (the same PHP renderer as the public site).
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor || ! wp.components || ! wp.serverSideRender ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;
	var DOMAIN = 'mr-tu-multilang-seo';

	function select( label, value, options, onChange, help ) {
		return el( SelectControl, {
			label: label,
			value: value,
			options: options,
			onChange: onChange,
			help: help,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );
	}

	function toggle( label, checked, onChange, help ) {
		return el( ToggleControl, {
			label: label,
			checked: !! checked,
			onChange: onChange,
			help: help,
			__nextHasNoMarginBottom: true,
		} );
	}

	function setter( props, key ) {
		return function ( value ) {
			var next = {};
			next[ key ] = value;
			props.setAttributes( next );
		};
	}

	/**
	 * Builds an edit component: sidebar controls + disabled server-side preview.
	 *
	 * @param {string}   name     Block name.
	 * @param {Function} controls ( props ) => array of controls.
	 * @return {Function} Edit component.
	 */
	function makeEdit( name, controls ) {
		return function Edit( props ) {
			var blockProps = useBlockProps( { className: 'mrtu-block-preview' } );
			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					// Children passed as separate arguments (no React "key" warnings).
					el.apply( null, [ PanelBody, { title: __( 'Settings', DOMAIN ), initialOpen: true } ].concat( controls( props ) ) )
				),
				el(
					Disabled,
					null,
					el( ServerSideRender, { block: name, attributes: props.attributes } )
				)
			);
		};
	}

	function register( name, controls ) {
		wp.blocks.registerBlockType( name, {
			edit: makeEdit( name, controls ),
			save: function () {
				return null;
			},
		} );
	}

	register( 'mrtu/pricing-table', function ( props ) {
		var a = props.attributes;
		return [
			select(
				__( 'Tab shown first', DOMAIN ),
				a.period,
				[
					{ label: __( 'Daily', DOMAIN ), value: 'day' },
					{ label: __( 'Weekly', DOMAIN ), value: 'week' },
					{ label: __( 'Monthly', DOMAIN ), value: 'month' },
				],
				setter( props, 'period' )
			),
			toggle( __( 'Show deposit column', DOMAIN ), a.showDeposit, setter( props, 'showDeposit' ) ),
			el(
				'p',
				{ className: 'components-base-control__help' },
				__( 'Prices are edited in Mr Tu → Prices. Unconfirmed prices show “Price on request”.', DOMAIN )
			),
		];
	} );

	register( 'mrtu/fleet', function ( props ) {
		var a = props.attributes;
		return [
			select(
				__( 'Vehicle type', DOMAIN ),
				a.category,
				[
					{ label: __( 'All (with filter buttons)', DOMAIN ), value: 'all' },
					{ label: __( 'Semi-automatic', DOMAIN ), value: 'manual' },
					{ label: __( 'Scooters', DOMAIN ), value: 'scooter' },
					{ label: __( '50cc', DOMAIN ), value: '50cc' },
					{ label: __( 'Electric', DOMAIN ), value: 'electric' },
				],
				setter( props, 'category' )
			),
			toggle( __( 'Show prices', DOMAIN ), a.showPrices, setter( props, 'showPrices' ) ),
		];
	} );

	register( 'mrtu/contact-card', function ( props ) {
		var a = props.attributes;
		var help = __( 'Shown only when verified in Mr Tu → Store information.', DOMAIN );
		return [
			toggle( __( 'Show address', DOMAIN ), a.showAddress, setter( props, 'showAddress' ), help ),
			toggle( __( 'Show opening hours', DOMAIN ), a.showHours, setter( props, 'showHours' ), help ),
		];
	} );

	register( 'mrtu/cta', function ( props ) {
		var a = props.attributes;
		return [
			select(
				__( 'Main button', DOMAIN ),
				a.variant,
				[
					{ label: __( 'Call', DOMAIN ), value: 'call' },
					{ label: __( 'Contact page', DOMAIN ), value: 'contact' },
				],
				setter( props, 'variant' ),
				__( 'The call button appears only when the phone number is verified.', DOMAIN )
			),
		];
	} );
} )( window.wp );

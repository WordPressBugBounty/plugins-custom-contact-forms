/**
 * Login Form (CCF) block.
 *
 * @since 7.21.0
 */
( function() {
	'use strict';

	if ( ! window.wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var cfg = window.ccfLoginBlock || {};

	var SSR = null;
	if ( wp.serverSideRender ) {
		SSR = wp.serverSideRender.default || wp.serverSideRender;
	} else if ( wp.editor && wp.editor.ServerSideRender ) {
		SSR = wp.editor.ServerSideRender;
	}

	var styles = [
		{ value: 'match', label: __( 'Match my site', 'custom-contact-forms' ) },
		{ value: 'clean', label: __( 'Clean', 'custom-contact-forms' ) },
		{ value: 'rounded', label: __( 'Rounded', 'custom-contact-forms' ) },
		{ value: 'bold', label: __( 'Bold', 'custom-contact-forms' ) },
		{ value: 'light', label: __( 'Light', 'custom-contact-forms' ) },
		{ value: 'dark', label: __( 'Dark', 'custom-contact-forms' ) }
	];

	wp.blocks.registerBlockType( 'ccf/login-form', {
		title: __( 'Login Form (CCF)', 'custom-contact-forms' ),
		description: __( 'Let people log in from any page of your site.', 'custom-contact-forms' ),
		icon: 'admin-users',
		category: 'widgets',
		keywords: [ 'login', 'log in', 'sign in', 'account', 'ccf' ],
		supports: { html: false },
		attributes: {
			title: { type: 'string', default: '' },
			redirect: { type: 'string', default: '' },
			style: { type: 'string', default: 'match' },
			buttonText: { type: 'string', default: '' },
			showRemember: { type: 'boolean', default: true },
			showLostPassword: { type: 'boolean', default: true },
			showRegister: { type: 'boolean', default: true }
		},

		edit: function( props ) {
			var a = props.attributes;

			function set( key ) {
				return function( value ) {
					var update = {};
					update[ key ] = value;
					props.setAttributes( update );
				};
			}

			var registerHelp = cfg.canRegister
				? __( 'Links to the WordPress sign-up page.', 'custom-contact-forms' )
				: __( 'Shows only when "Anyone can register" is on under Settings > General.', 'custom-contact-forms' );

			var panels = [
				el( PanelBody, { title: __( 'Login form', 'custom-contact-forms' ), initialOpen: true, key: 'main' },
					el( TextControl, {
						label: __( 'Title (optional)', 'custom-contact-forms' ),
						value: a.title,
						onChange: set( 'title' )
					} ),
					el( SelectControl, {
						label: __( 'Style', 'custom-contact-forms' ),
						value: a.style,
						options: styles,
						onChange: set( 'style' )
					} ),
					el( TextControl, {
						label: __( 'Button text', 'custom-contact-forms' ),
						placeholder: __( 'Log In', 'custom-contact-forms' ),
						value: a.buttonText,
						onChange: set( 'buttonText' )
					} ),
					el( TextControl, {
						label: __( 'After logging in, go to', 'custom-contact-forms' ),
						help: __( 'A page on your site, for example /my-account/. Leave empty to stay on this page.', 'custom-contact-forms' ),
						placeholder: '/my-account/',
						value: a.redirect,
						onChange: set( 'redirect' )
					} ),
					el( ToggleControl, {
						label: __( 'Show "Remember me"', 'custom-contact-forms' ),
						checked: a.showRemember,
						onChange: set( 'showRemember' )
					} ),
					el( ToggleControl, {
						label: __( 'Show "Forgot your password?"', 'custom-contact-forms' ),
						checked: a.showLostPassword,
						onChange: set( 'showLostPassword' )
					} ),
					el( ToggleControl, {
						label: __( 'Show "Create an account"', 'custom-contact-forms' ),
						help: registerHelp,
						checked: a.showRegister,
						onChange: set( 'showRegister' )
					} )
				)
			];

			if ( cfg.proUrl ) {
				panels.push(
					el( PanelBody, { title: __( 'Registration forms', 'custom-contact-forms' ), initialOpen: false, key: 'pro' },
						el( 'p', null, __( 'Need people to sign up too? Custom Contact Forms Pro adds registration and profile forms that you build in the form builder.', 'custom-contact-forms' ) ),
						el( 'a', { href: cfg.proUrl, target: '_blank', rel: 'noopener noreferrer', className: 'components-button is-secondary' }, __( 'See Pro', 'custom-contact-forms' ) )
					)
				);
			}

			var preview = SSR
				? el( SSR, { block: 'ccf/login-form', attributes: a } )
				: el( 'p', null, __( 'The login form shows on your site.', 'custom-contact-forms' ) );

			return el( Fragment, null,
				el( InspectorControls, null, panels ),
				el( 'div', { className: 'ccf-gutenberg-block-preview' }, preview )
			);
		},

		save: function() {
			return null;
		}
	} );
}() );

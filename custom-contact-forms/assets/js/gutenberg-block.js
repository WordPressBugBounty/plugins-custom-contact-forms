( function() {
	'use strict';

	var blocks = wp.blocks;
	var element = wp.element;
	var components = wp.components;
	var blockEditor = wp.blockEditor;
	var i18n = wp.i18n;

	var el = element.createElement;
	var Fragment = element.Fragment;
	var SelectControl = components.SelectControl;
	var Placeholder = components.Placeholder;
	var PanelBody = components.PanelBody;
	var InspectorControls = blockEditor.InspectorControls;
	var __ = i18n.__;

	// ServerSideRender moved between packages across WP versions
	var ServerSideRender = ( wp.serverSideRender && wp.serverSideRender.default )
		? wp.serverSideRender.default
		: ( wp.serverSideRender || ( wp.editor && wp.editor.ServerSideRender ) || null );

	var blockData = window.ccfBlockData || {};
	var forms = blockData.forms || [];

	// Build select options
	var selectOptions = [
		{ value: 0, label: blockData.selectText || '— Select a form —' }
	];
	forms.forEach( function( form ) {
		selectOptions.push( { value: form.value, label: form.label } );
	} );

	blocks.registerBlockType( 'ccf/form-block', {
		title: blockData.blockTitle || 'Contact Form (CCF)',
		description: blockData.blockDesc || 'Display an existing Custom Contact Form.',
		icon: 'feedback',
		category: 'widgets',
		keywords: [ 'form', 'contact', 'ccf', 'custom contact forms' ],
		attributes: {
			formId: {
				type: 'number',
				default: 0
			}
		},
		supports: {
			html: false,
			multiple: true,
			reusable: true
		},

		edit: function( props ) {
			var formId = props.attributes.formId;

			function onChangeForm( newVal ) {
				props.setAttributes( { formId: parseInt( newVal, 10 ) || 0 } );
			}

			var formSelect = el( SelectControl, {
				label: blockData.selectText || 'Select a form',
				value: formId,
				options: selectOptions,
				onChange: onChangeForm
			} );

			// No forms available
			if ( forms.length === 0 ) {
				return el( Placeholder, {
					icon: 'feedback',
					label: blockData.blockTitle || 'Contact Form (CCF)'
				},
					el( 'p', {}, blockData.noFormsText || 'No forms found. Create a form first.' ),
					el( 'a', {
						href: blockData.adminUrl || '#',
						className: 'components-button is-primary'
					}, __( 'Create a Form', 'custom-contact-forms' ) )
				);
			}

			// No form selected yet
			if ( ! formId || formId === 0 ) {
				return el( Fragment, {},
					el( InspectorControls, {},
						el( PanelBody, { title: blockData.blockTitle || 'Contact Form (CCF)' },
							formSelect
						)
					),
					el( Placeholder, {
						icon: 'feedback',
						label: blockData.blockTitle || 'Contact Form (CCF)'
					},
						el( SelectControl, {
							label: blockData.selectText || 'Select a form',
							value: formId,
							options: selectOptions,
							onChange: onChangeForm
						} )
					)
				);
			}

			// Form selected — build the preview
			var preview;
			if ( ServerSideRender ) {
				preview = el( ServerSideRender, {
					block: 'ccf/form-block',
					attributes: props.attributes
				} );
			} else {
				// Fallback if ServerSideRender is unavailable
				var selectedForm = forms.find( function( f ) { return f.value === formId; } );
				var formName = selectedForm ? selectedForm.label : ( '#' + formId );
				preview = el( 'div', {
					style: { padding: '20px', background: '#f0f0f0', border: '1px dashed #ccc', textAlign: 'center' }
				},
					el( 'span', { className: 'dashicons dashicons-feedback', style: { fontSize: '24px', marginBottom: '8px', display: 'block' } } ),
					el( 'strong', {}, formName ),
					el( 'p', { style: { color: '#666', margin: '8px 0 0' } }, __( 'Form will render on the front end.', 'custom-contact-forms' ) )
				);
			}

			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: blockData.blockTitle || 'Contact Form (CCF)' },
						formSelect
					)
				),
				el( 'div', { className: 'ccf-gutenberg-block-preview' }, preview )
			);
		},

		save: function() {
			// Dynamic block — rendered server-side
			return null;
		}
	} );

} )();

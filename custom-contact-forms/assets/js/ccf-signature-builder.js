/**
 * Signature field: form builder model and settings view.
 *
 * @package Custom_Contact_Forms
 * @since 7.17.0
 */
( function() {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.ccf || ! wp.ccf.models || ! wp.ccf.views ) {
		return;
	}

	function value( el, selector ) {
		var node = el.querySelector( selector );
		return node ? node.value : '';
	}

	wp.ccf.models.Fields.signature = wp.ccf.models.Fields.signature || wp.ccf.models.StandardField.extend(
		{
			defaults: function() {
				return _.defaults( { type: 'signature' }, this.constructor.__super__.defaults() );
			}
		}
	);

	wp.ccf.views.Fields.signature = wp.ccf.views.Fields.signature || wp.ccf.views.FieldBase.extend(
		{
			template: wp.ccf.utils.template( 'ccf-signature-template' ),

			saveField: function() {
				if ( this.el.innerHTML === '' ) {
					return;
				}

				this.model.set( 'slug', value( this.el, '.field-slug' ) );
				this.model.set( 'label', value( this.el, '.field-label' ) );
				this.model.set( 'description', value( this.el, '.field-description' ) );
				this.model.set( 'required', value( this.el, '.field-required' ) === '1' );

				if ( this.el.querySelector( '.field-class-name' ) ) {
					this.model.set( 'className', value( this.el, '.field-class-name' ) );
				}

				if ( this.el.querySelector( '.field-width' ) ) {
					this.model.set( 'fieldWidth', value( this.el, '.field-width' ) );
				}

				this.constructor.__super__.saveField.apply( this, arguments );

				return this;
			}
		}
	);
}() );

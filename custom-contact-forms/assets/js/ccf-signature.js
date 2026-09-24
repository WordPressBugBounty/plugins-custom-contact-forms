/**
 * Signature field: front-end drawing pad and validation.
 *
 * @package Custom_Contact_Forms
 * @since 7.17.0
 */
( function() {
	'use strict';

	var settings = window.ccfSignature || {};

	window.wp = window.wp || {};
	wp.ccf = wp.ccf || {};
	wp.ccf.validators = wp.ccf.validators || {};

	/**
	 * Required check. CCF calls a validator for every field type on submit,
	 * so the signature type must always have one.
	 *
	 * @param {Element} fieldWrapperElement Field wrapper.
	 */
	wp.ccf.validators.signature = wp.ccf.validators.signature || function( fieldWrapperElement ) {
		this.wrapper = fieldWrapperElement;
		this.errors = {};

		var oldErrors = fieldWrapperElement.querySelectorAll( '.error' );
		for ( var i = oldErrors.length - 1; i >= 0; i-- ) {
			oldErrors[ i ].parentNode.removeChild( oldErrors[ i ] );
		}

		if ( ! fieldWrapperElement.className.match( / field-required/ ) ) {
			return;
		}

		var input = fieldWrapperElement.querySelector( 'input[type="hidden"]' );

		if ( input && '' === input.value ) {
			var name = input.getAttribute( 'name' );
			this.errors[ name ] = { required: true };

			var errorNode = document.createElement( 'div' );
			errorNode.className = 'error required-error';
			errorNode.setAttribute( 'data-field-name', name );
			errorNode.innerHTML = ( window.ccfSettings && window.ccfSettings.required ) ? window.ccfSettings.required : '';

			var wrap = fieldWrapperElement.querySelector( '.ccf-signature-wrap' ) || input;
			wrap.parentNode.insertBefore( errorNode, wrap.nextSibling );
		}
	};

	function setup( wrap ) {
		if ( wrap.getAttribute( 'data-ccf-signature-ready' ) ) {
			return;
		}

		var mount = wrap.querySelector( '.ccf-signature-pad' );
		var input = wrap.querySelector( '.ccf-signature-value' );
		var clear = wrap.querySelector( '.ccf-signature-clear' );

		if ( ! mount || ! input || typeof window.SignaturePad === 'undefined' ) {
			return;
		}

		wrap.setAttribute( 'data-ccf-signature-ready', '1' );

		var canvas = document.createElement( 'canvas' );
		canvas.className = 'ccf-signature-canvas';
		canvas.setAttribute( 'role', 'img' );
		canvas.setAttribute( 'aria-label', settings.canvasLabel || '' );

		var labelId = wrap.getAttribute( 'data-label-id' );
		if ( labelId ) {
			canvas.setAttribute( 'aria-describedby', labelId );
		}

		mount.appendChild( canvas );

		var pad = new window.SignaturePad( canvas, {
			penColor: '#111111',
			backgroundColor: 'rgba(0,0,0,0)'
		} );

		function resize() {
			var ratio = Math.max( window.devicePixelRatio || 1, 1 );
			var width = mount.clientWidth || 400;
			var height = mount.clientHeight || 160;
			var kept = pad.isEmpty() ? null : pad.toData();

			canvas.width = width * ratio;
			canvas.height = height * ratio;
			canvas.style.width = width + 'px';
			canvas.style.height = height + 'px';
			canvas.getContext( '2d' ).scale( ratio, ratio );

			pad.clear();

			if ( kept ) {
				pad.fromData( kept );
			}
		}

		function store() {
			input.value = pad.isEmpty() ? '' : pad.toDataURL( 'image/png' );

			var field = wrap.closest ? wrap.closest( '.field' ) : null;
			if ( field && input.value ) {
				var errors = field.querySelectorAll( '.error' );
				for ( var i = errors.length - 1; i >= 0; i-- ) {
					errors[ i ].parentNode.removeChild( errors[ i ] );
				}
			}
		}

		resize();

		pad.addEventListener( 'endStroke', store );

		if ( clear ) {
			clear.addEventListener( 'click', function( event ) {
				event.preventDefault();
				pad.clear();
				input.value = '';
			} );
		}

		// Keep the drawing surface matched to the box, including when a form
		// starts hidden (a tab, a popup, a conditional section) and is shown
		// later. Existing strokes are redrawn at the new size.
		var timer;
		var lastWidth = mount.clientWidth;
		var onResize = function() {
			clearTimeout( timer );
			timer = setTimeout( function() {
				if ( mount.clientWidth && mount.clientWidth !== lastWidth ) {
					lastWidth = mount.clientWidth;
					resize();
				}
			}, 100 );
		};

		if ( typeof window.ResizeObserver !== 'undefined' ) {
			new window.ResizeObserver( onResize ).observe( mount );
		} else {
			window.addEventListener( 'resize', onResize );
		}
	}

	function init() {
		var wraps = document.querySelectorAll( '.ccf-signature-wrap' );
		for ( var i = 0; i < wraps.length; i++ ) {
			setup( wraps[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

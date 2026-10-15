/**
 * After a submission comes back, reset hCaptcha and Turnstile so the visitor
 * can try again (each token works once), and explain a failed check.
 *
 * Forms post into a hidden iframe named ccf_form_frame_{id}; this listens to
 * the same iframe and does not change how forms are submitted.
 *
 * @since 7.18.0
 */
( function() {
	'use strict';

	var message = ( window.ccfCaptcha && window.ccfCaptcha.failed ) || 'Please complete the security check and try again.';

	function resetWidgets( form ) {
		var widgets;
		var i;

		if ( window.hcaptcha && typeof window.hcaptcha.reset === 'function' ) {
			widgets = form.querySelectorAll( '.h-captcha iframe' );

			for ( i = 0; i < widgets.length; i++ ) {
				try {
					window.hcaptcha.reset( widgets[ i ].getAttribute( 'data-hcaptcha-widget-id' ) || undefined );
				} catch ( e ) {}
			}

			if ( ! widgets.length ) {
				try {
					window.hcaptcha.reset();
				} catch ( e ) {}
			}
		}

		if ( window.turnstile && typeof window.turnstile.reset === 'function' ) {
			widgets = form.querySelectorAll( '.cf-turnstile' );

			for ( i = 0; i < widgets.length; i++ ) {
				try {
					window.turnstile.reset( widgets[ i ] );
				} catch ( e ) {}
			}
		}
	}

	function showMessage( form ) {
		var existing = form.querySelector( '.ccf-captcha-error' );
		var anchor = form.querySelector( '.ccf-hcaptcha-wrapper, .ccf-turnstile-wrapper' );

		if ( existing || ! anchor ) {
			return;
		}

		var node = document.createElement( 'div' );
		node.className = 'error ccf-captcha-error';
		node.setAttribute( 'role', 'alert' );
		node.textContent = message;
		anchor.appendChild( node );
	}

	function watch( frame ) {
		var formId = frame.name.replace( 'ccf_form_frame_', '' );

		frame.addEventListener( 'load', function() {
			var form = document.querySelector( 'form.ccf-form[data-form-id="' + formId + '"]' );
			var text = '';
			var data;

			if ( ! form ) {
				return;
			}

			try {
				text = frame.contentDocument.body.textContent;
				data = JSON.parse( text );
			} catch ( e ) {
				return;
			}

			var old = form.querySelector( '.ccf-captcha-error' );

			if ( old ) {
				old.parentNode.removeChild( old );
			}

			if ( data && ! data.success ) {
				resetWidgets( form );

				if ( 'hcaptcha_failed' === data.error || 'turnstile_failed' === data.error ) {
					showMessage( form );
				}
			}
		} );
	}

	function init() {
		var frames = document.querySelectorAll( 'iframe[name^="ccf_form_frame_"]' );

		for ( var i = 0; i < frames.length; i++ ) {
			watch( frames[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

/**
 * Opt-in signup for tips and release news.
 *
 * Subscribe sends the form to WordPress, which passes it on. No thanks only
 * records the answer on this site.
 *
 * @since 7.19.0
 */
( function() {
	'use strict';

	var cfg = window.ccfNewsletter;

	if ( ! cfg ) {
		return;
	}

	function post( action, data ) {
		var body = new URLSearchParams();

		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );

		Object.keys( data ).forEach( function( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function( response ) {
			return response.json().catch( function() {
				return { success: false };
			} );
		} );
	}

	function container( form ) {
		return form.closest( '.ccf-nl-card, .ccf-nl-overlay, .ccf-nl-settings' );
	}

	function closePopup() {
		var popup = document.getElementById( 'ccf-nl-popup' );

		if ( popup ) {
			popup.classList.add( 'ccf-nl-gone' );
		}
	}

	function decline( form ) {
		post( 'ccf_newsletter_decline', { source: form.getAttribute( 'data-source' ) } );

		var box = container( form );

		if ( box && box.classList.contains( 'ccf-nl-overlay' ) ) {
			closePopup();
		} else if ( box ) {
			box.parentNode.removeChild( box );
		}

		// Once answered anywhere, the dashboard card goes too.
		var card = document.getElementById( 'ccf-nl-card' );

		if ( card && card.parentNode ) {
			card.parentNode.removeChild( card );
		}
	}

	function subscribe( form ) {
		var email = form.querySelector( '[name="email"]' );
		var name = form.querySelector( '[name="name"]' );
		var button = form.querySelector( '.ccf-nl-subscribe' );
		var message = form.querySelector( '.ccf-nl-message' );
		var label = button.textContent;

		if ( ! email.value || ! email.checkValidity() ) {
			message.textContent = cfg.i18n.email;
			message.className = 'ccf-nl-message is-error';
			email.focus();
			return;
		}

		button.disabled = true;
		button.textContent = cfg.i18n.sending;
		message.textContent = '';
		message.className = 'ccf-nl-message';

		post( 'ccf_newsletter_subscribe', {
			email: email.value,
			name: name ? name.value : '',
			source: form.getAttribute( 'data-source' )
		} ).then( function( result ) {
			if ( result && result.success ) {
				var box = container( form );
				var thanks = box ? box.querySelector( '.ccf-nl-thanks' ) : null;

				form.setAttribute( 'hidden', '' );

				if ( box ) {
					var intro = box.querySelector( '.ccf-nl-intro' );

					if ( intro ) {
						intro.setAttribute( 'hidden', '' );
					}
				}

				if ( thanks ) {
					thanks.removeAttribute( 'hidden' );
				}

				if ( box && box.classList.contains( 'ccf-nl-overlay' ) ) {
					setTimeout( closePopup, 2200 );
				}

				return;
			}

			button.disabled = false;
			button.textContent = label;
			message.textContent = result && result.data && result.data.message ? result.data.message : cfg.i18n.failed;
			message.className = 'ccf-nl-message is-error';
		} ).catch( function() {
			button.disabled = false;
			button.textContent = label;
			message.textContent = cfg.i18n.failed;
			message.className = 'ccf-nl-message is-error';
		} );
	}

	document.addEventListener( 'submit', function( event ) {
		var form = event.target;

		if ( form && form.classList && form.classList.contains( 'ccf-nl-form' ) ) {
			event.preventDefault();
			subscribe( form );
		}
	} );

	document.addEventListener( 'click', function( event ) {
		var target = event.target;

		if ( ! target || ! target.closest ) {
			return;
		}

		if ( target.closest( '.ccf-nl-decline' ) ) {
			decline( target.closest( '.ccf-nl-decline' ).closest( '.ccf-nl-form' ) );
			return;
		}

		// Closing the popup without answering just closes it. The dashboard
		// card stays until they choose.
		if ( target.closest( '.ccf-nl-x' ) || target.id === 'ccf-nl-popup' ) {
			closePopup();
		}
	} );

	document.addEventListener( 'keydown', function( event ) {
		if ( 'Escape' === event.key && document.getElementById( 'ccf-nl-popup' ) ) {
			closePopup();
		}
	} );
}() );

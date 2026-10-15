/**
 * "How are we doing?" card on the Forms dashboard.
 *
 * @package Custom_Contact_Forms
 * @since 7.17.1
 */
( function() {
	'use strict';

	var data = window.ccfReviewCard;
	var card = document.querySelector( '.ccf-rate' );

	if ( ! data || ! card ) {
		return;
	}

	var stars = 0;

	function send( response, extra ) {
		var body = new FormData();
		body.append( 'action', 'ccf_review_response' );
		body.append( 'nonce', data.nonce );
		body.append( 'response', response );
		body.append( 'milestone', data.milestone );
		body.append( 'stars', stars );

		if ( extra ) {
			for ( var key in extra ) {
				if ( extra.hasOwnProperty( key ) ) {
					body.append( key, extra[ key ] );
				}
			}
		}

		return fetch( data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function( r ) {
				return r.json();
			} );
	}

	function show( step ) {
		card.setAttribute( 'data-step', step );
		[ 'ask', 'happy', 'unhappy', 'thanks' ].forEach( function( name ) {
			var part = card.querySelector( '.ccf-rate-' + name );
			if ( part ) {
				part.hidden = name !== step;
			}
		} );
	}

	function thanks( message ) {
		card.querySelector( '.ccf-rate-thanks-text' ).textContent = message;
		show( 'thanks' );
	}

	function currentText() {
		var box = card.querySelector( '.ccf-rate-' + card.getAttribute( 'data-step' ) + ' textarea' );
		return box ? box.value.trim() : '';
	}

	// Copy text the old way when the Clipboard API is unavailable, for
	// example on sites not served over HTTPS.
	function copy( text ) {
		if ( ! text ) {
			return Promise.resolve( false );
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then( function() {
				return true;
			}, function() {
				return false;
			} );
		}

		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( area );
		return Promise.resolve( ok );
	}

	// Stars: highlight on hover and focus, choose on click.
	var starButtons = card.querySelectorAll( '.ccf-rate-star' );

	function paint( upTo ) {
		for ( var i = 0; i < starButtons.length; i++ ) {
			starButtons[ i ].classList.toggle( 'is-lit', i < upTo );
		}
	}

	Array.prototype.forEach.call( starButtons, function( button ) {
		var value = parseInt( button.getAttribute( 'data-stars' ), 10 );

		button.addEventListener( 'mouseenter', function() {
			paint( value );
		} );
		button.addEventListener( 'focus', function() {
			paint( value );
		} );
		button.addEventListener( 'click', function() {
			stars = value;
			paint( value );
			Array.prototype.forEach.call( starButtons, function( b ) {
				b.setAttribute( 'aria-pressed', b === button ? 'true' : 'false' );
			} );
			show( value >= 4 ? 'happy' : 'unhappy' );
			var box = card.querySelector( '.ccf-rate-' + ( value >= 4 ? 'happy' : 'unhappy' ) + ' textarea' );
			if ( box ) {
				box.focus();
			}
		} );
	} );

	card.querySelector( '.ccf-rate-stars' ).addEventListener( 'mouseleave', function() {
		paint( stars );
	} );

	card.addEventListener( 'click', function( event ) {
		var trigger = event.target.closest ? event.target.closest( '[data-ccf-rate]' ) : null;

		if ( ! trigger ) {
			return;
		}

		var action = trigger.getAttribute( 'data-ccf-rate' );

		if ( 'later' === action ) {
			send( 'later' );
			card.parentNode.removeChild( card );
			return;
		}

		if ( 'post' === action ) {
			// Open the tab straight away, inside the click, so popup blockers
			// allow it; then copy the words and record the answer.
			window.open( data.reviewUrl, '_blank', 'noopener' );
			var text = currentText();
			copy( text ).then( function( copied ) {
				thanks( copied ? data.i18n.copied : data.i18n.opened );
			} );
			send( 'post' );
			return;
		}

		if ( 'feedback' === action ) {
			trigger.disabled = true;
			send( 'feedback', { comment: currentText() } ).then( function( json ) {
				if ( json && json.success ) {
					thanks( data.i18n.sent );
				} else {
					trigger.disabled = false;
					window.alert( data.i18n.failed );
				}
			}, function() {
				trigger.disabled = false;
				window.alert( data.i18n.failed );
			} );
		}
	} );
}() );

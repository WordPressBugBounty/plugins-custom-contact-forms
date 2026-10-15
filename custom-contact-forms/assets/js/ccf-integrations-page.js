/**
 * Forms > Integrations: filters, settings panels and on/off switches.
 *
 * @since 7.18.0
 */
( function() {
	'use strict';

	var cfg = window.ccfIntegrationsPage || { i18n: {} };
	var overlay = document.querySelector( '.ccf-int-overlay' );
	var filters = { category: 'all', tier: 'all' };
	var lastFocus = null;

	function openDrawer( card ) {
		var drawer = card.querySelector( '.ccf-int-drawer' );

		if ( ! drawer ) {
			return;
		}

		closeAll();
		lastFocus = document.activeElement;
		drawer.removeAttribute( 'hidden' );

		if ( overlay ) {
			overlay.removeAttribute( 'hidden' );
		}

		var focusable = drawer.querySelector( 'input:not([type=hidden]), select, textarea, button' );

		if ( focusable ) {
			focusable.focus();
		}
	}

	function closeAll() {
		Array.prototype.forEach.call( document.querySelectorAll( '.ccf-int-drawer' ), function( drawer ) {
			drawer.setAttribute( 'hidden', '' );
		} );

		if ( overlay ) {
			overlay.setAttribute( 'hidden', '' );
		}
	}

	function close() {
		closeAll();

		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function applyFilters() {
		var shown = 0;

		Array.prototype.forEach.call( document.querySelectorAll( '.ccf-int-card' ), function( card ) {
			var categoryOk = 'all' === filters.category || filters.category === card.getAttribute( 'data-category' );
			var tierOk = 'all' === filters.tier ||
				( 'active' === filters.tier && '1' === card.getAttribute( 'data-active' ) ) ||
				filters.tier === card.getAttribute( 'data-tier' );

			if ( categoryOk && tierOk ) {
				card.removeAttribute( 'hidden' );
				shown++;
			} else {
				card.setAttribute( 'hidden', '' );
			}
		} );

		var none = document.querySelector( '.ccf-int-none' );

		if ( none ) {
			if ( shown ) {
				none.setAttribute( 'hidden', '' );
			} else {
				none.removeAttribute( 'hidden' );
			}
		}
	}

	function toggle( button ) {
		if ( button.classList.contains( 'is-busy' ) ) {
			return;
		}

		var card = button.closest( '.ccf-int-card' );
		var turningOn = 'true' !== button.getAttribute( 'aria-checked' );
		var body = new URLSearchParams();

		body.append( 'action', button.getAttribute( 'data-action' ) );
		body.append( 'nonce', button.getAttribute( 'data-nonce' ) );
		body.append( 'module', button.getAttribute( 'data-module' ) );
		body.append( 'enabled', turningOn ? '1' : '0' );

		button.classList.add( 'is-busy' );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function( response ) {
			return response.json();
		} ).then( function( result ) {
			button.classList.remove( 'is-busy' );

			if ( ! result || ! result.success ) {
				window.alert( cfg.i18n.error );
				return;
			}

			button.setAttribute( 'aria-checked', turningOn ? 'true' : 'false' );
			card.setAttribute( 'data-active', turningOn ? '1' : '0' );

			var status = card.querySelector( '.ccf-int-status' );

			if ( status ) {
				status.textContent = turningOn ? cfg.i18n.on : cfg.i18n.off;
				status.className = 'ccf-int-status ccf-int-status-' + ( turningOn ? 'ok' : 'off' );
			}

			var settings = card.querySelector( '.ccf-int-open' );

			if ( settings ) {
				if ( turningOn ) {
					settings.removeAttribute( 'hidden' );
				} else {
					settings.setAttribute( 'hidden', '' );
				}
			}

			// Switching something on usually means setting it up next.
			if ( turningOn && settings ) {
				openDrawer( card );
			}
		} ).catch( function() {
			button.classList.remove( 'is-busy' );
			window.alert( cfg.i18n.error );
		} );
	}

	document.addEventListener( 'click', function( event ) {
		var target = event.target;

		if ( ! target.closest ) {
			return;
		}

		var openButton = target.closest( '.ccf-int-open' );

		if ( openButton ) {
			openDrawer( openButton.closest( '.ccf-int-card' ) );
			return;
		}

		if ( target.closest( '.ccf-int-close' ) || target === overlay ) {
			close();
			return;
		}

		var switchButton = target.closest( '.ccf-int-toggle' );

		if ( switchButton ) {
			event.preventDefault();
			toggle( switchButton );
			return;
		}

		var filter = target.closest( '.ccf-int-filter' );

		if ( filter ) {
			var group = filter.getAttribute( 'data-group' );

			Array.prototype.forEach.call( document.querySelectorAll( '.ccf-int-filter[data-group="' + group + '"]' ), function( item ) {
				item.classList.toggle( 'is-active', item === filter );
			} );

			filters[ group ] = filter.getAttribute( 'data-filter' );
			applyFilters();
		}
	} );

	document.addEventListener( 'keydown', function( event ) {
		if ( 'Escape' === event.key && document.querySelector( '.ccf-int-drawer:not([hidden])' ) ) {
			close();
		}
	} );

	// A card opened from a link or after saving: show its panel.
	var opened = document.querySelector( '.ccf-int-card.is-open' );

	if ( opened ) {
		openDrawer( opened );
	}
}() );

/**
 * Pro field teasers in the builder palette.
 *
 * Locked field types are display-only: clicking one opens a small,
 * dismissible modal describing the feature with a link to upgrade.
 * This file loads only when the Pro add-on is not active.
 *
 * @package Custom_Contact_Forms
 */
( function () {
	'use strict';

	var cfg = window.ccfProTeaser || {};

	function closeModal() {
		var overlay = document.querySelector( '.ccf-pro-modal-overlay' );
		if ( overlay && overlay.parentNode ) {
			overlay.parentNode.removeChild( overlay );
		}
		document.removeEventListener( 'keydown', onKey );
	}

	function onKey( e ) {
		if ( 'Escape' === e.key ) {
			closeModal();
		}
	}

	function openModal( name, desc ) {
		closeModal();

		var overlay = document.createElement( 'div' );
		overlay.className = 'ccf-pro-modal-overlay';

		var modal = document.createElement( 'div' );
		modal.className = 'ccf-pro-modal';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-label', name );

		var title = document.createElement( 'h2' );
		title.textContent = name;

		var badge = document.createElement( 'span' );
		badge.className = 'ccf-pro-modal-badge';
		badge.textContent = cfg.badge || 'Pro';
		title.appendChild( badge );

		var body = document.createElement( 'p' );
		body.textContent = desc || '';

		var actions = document.createElement( 'div' );
		actions.className = 'ccf-pro-modal-actions';

		var upgrade = document.createElement( 'a' );
		upgrade.className = 'button button-primary';
		upgrade.href = cfg.url || '#';
		upgrade.target = '_blank';
		upgrade.rel = 'noopener';
		upgrade.textContent = cfg.cta || 'Get Pro';

		var dismiss = document.createElement( 'button' );
		dismiss.type = 'button';
		dismiss.className = 'button';
		dismiss.textContent = cfg.dismiss || 'Maybe later';
		dismiss.addEventListener( 'click', closeModal );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'ccf-pro-modal-close';
		close.setAttribute( 'aria-label', 'Close' );
		close.innerHTML = '&times;';
		close.addEventListener( 'click', closeModal );

		actions.appendChild( upgrade );
		actions.appendChild( dismiss );
		modal.appendChild( close );
		modal.appendChild( title );
		modal.appendChild( body );
		modal.appendChild( actions );
		overlay.appendChild( modal );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );
		document.addEventListener( 'keydown', onKey );
		document.body.appendChild( overlay );
	}

	// Delegated: the builder modal is created dynamically.
	document.addEventListener( 'click', function ( e ) {
		var teaser = e.target && e.target.closest ? e.target.closest( '.ccf-pro-teaser' ) : null;
		if ( ! teaser ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		openModal(
			teaser.getAttribute( 'data-pro-name' ) || 'Pro Field',
			teaser.getAttribute( 'data-pro-desc' ) || ''
		);
	}, true );

	/* Inject locked Pro field items at the end of the builder's Special
	 * Fields list. The list is populated dynamically by the builder, so we
	 * watch for it rather than assuming it exists. */
	function injectFieldTeasers() {
		if ( ! cfg.fields || ! cfg.fields.length ) {
			return;
		}
		var lists = document.querySelectorAll( '.ccf-main-modal .special-fields.draggable-fields' );
		for ( var i = 0; i < lists.length; i++ ) {
			var list = lists[ i ];
			if ( list.getAttribute( 'data-ccf-teased' ) || ! list.children.length ) {
				continue; // Wait until the builder has populated the list.
			}
			list.setAttribute( 'data-ccf-teased', '1' );
			for ( var j = 0; j < cfg.fields.length; j++ ) {
				var def = cfg.fields[ j ];
				var item = document.createElement( 'div' );
				item.className = 'field ccf-pro-teaser';
				item.setAttribute( 'data-pro-name', def.name );
				item.setAttribute( 'data-pro-desc', def.desc || '' );
				var h = document.createElement( 'h4' );
				h.textContent = def.name;
				var pill = document.createElement( 'span' );
				pill.className = 'ccf-pro-mini';
				pill.textContent = cfg.badge || 'Pro';
				h.appendChild( pill );
				var lock = document.createElement( 'span' );
				lock.className = 'ccf-pro-lock';
				h.appendChild( lock );
				item.appendChild( h );
				list.appendChild( item );
			}
		}
	}

	if ( window.MutationObserver ) {
		new MutationObserver( injectFieldTeasers ).observe( document.documentElement, { childList: true, subtree: true } );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', injectFieldTeasers );
	} else {
		injectFieldTeasers();
	}
} )();

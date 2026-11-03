/**
 * Pro field and template teasers.
 *
 * Locked field types and templates are display-only: clicking one opens a
 * small, dismissible popup that says what the feature does, what Pro costs
 * and how to get it. This file loads only when the Pro add-on is not active.
 *
 * Everything shown comes from the localized ccfProTeaser object and from
 * data attributes set by PHP; all text is inserted with textContent.
 *
 * @package Custom_Contact_Forms
 */
( function () {
	'use strict';

	var cfg = window.ccfProTeaser || {};
	var lastFocus = null;

	function addStyles() {
		if ( document.getElementById( 'ccf-pro-teaser-css' ) ) {
			return;
		}
		var css = '' +
			'.ccf-pro-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1700000;display:flex;align-items:center;justify-content:center}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal{background:#fff;border-radius:10px;padding:26px 28px 20px;max-width:430px;width:92%;position:relative;box-shadow:0 10px 34px rgba(0,0,0,.3);box-sizing:border-box;text-align:left}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal h2{margin:0 26px 8px 0;font-size:19px;line-height:1.3;color:#1d2327}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pro-modal-badge{display:inline-block;margin-left:8px;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:600;background:#0e9f6e;color:#fff;vertical-align:middle}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-desc{margin:0 0 12px;font-size:14px;line-height:1.5;color:#3c434a}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-points{margin:0 0 14px;padding:0;list-style:none}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-points li{position:relative;margin:0 0 6px;padding-left:24px;font-size:13.5px;line-height:1.45;color:#2c3338}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-points li:before{content:"";position:absolute;left:0;top:3px;width:15px;height:15px;border-radius:50%;background:#0e9f6e}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-points li:after{content:"";position:absolute;left:5px;top:6px;width:4px;height:7px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-price{margin:0 0 2px;font-size:14px;font-weight:600;color:#1d2327}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-guarantee{margin:0 0 14px;font-size:13px;color:#50575e}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-promo{margin:0 0 14px;padding:9px 12px;border-radius:6px;background:#fff7e6;border:1px solid #f5d38a;font-size:13px;font-weight:600;color:#7a4b00}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pro-modal-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-cta.button-primary{background:#0e9f6e;border-color:#0e9f6e;color:#fff}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-cta.button-primary:hover,.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-cta.button-primary:focus{background:#0b8259;border-color:#0b8259;color:#fff}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pt-purchased{display:block;margin:14px 0 0;font-size:12.5px;color:#646970}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pro-modal-close{position:absolute;top:10px;right:12px;border:0;background:none;font-size:24px;line-height:1;color:#787c82;cursor:pointer;padding:4px}' +
			'.ccf-pro-modal-overlay .ccf-pro-modal .ccf-pro-modal-close:hover{color:#1d2327}';
		var style = document.createElement( 'style' );
		style.id = 'ccf-pro-teaser-css';
		style.appendChild( document.createTextNode( css ) );
		document.head.appendChild( style );
	}

	function closeModal() {
		var overlay = document.querySelector( '.ccf-pro-modal-overlay' );
		if ( overlay && overlay.parentNode ) {
			overlay.parentNode.removeChild( overlay );
		}
		document.removeEventListener( 'keydown', onKey );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function onKey( e ) {
		if ( 'Escape' === e.key ) {
			closeModal();
		}
	}

	function para( className, text ) {
		var p = document.createElement( 'p' );
		p.className = className;
		p.textContent = text;
		return p;
	}

	function openModal( item ) {
		closeModal();
		addStyles();
		lastFocus = document.activeElement;

		var overlay = document.createElement( 'div' );
		overlay.className = 'ccf-pro-modal-overlay';

		var modal = document.createElement( 'div' );
		modal.className = 'ccf-pro-modal';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', item.name );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'ccf-pro-modal-close';
		close.setAttribute( 'aria-label', 'Close' );
		close.textContent = '×';
		close.addEventListener( 'click', closeModal );
		modal.appendChild( close );

		var title = document.createElement( 'h2' );
		title.textContent = item.name;
		var badge = document.createElement( 'span' );
		badge.className = 'ccf-pro-modal-badge';
		badge.textContent = cfg.badge || 'Pro';
		title.appendChild( badge );
		modal.appendChild( title );

		if ( item.desc ) {
			modal.appendChild( para( 'ccf-pt-desc', item.desc ) );
		}

		if ( item.points && item.points.length ) {
			var list = document.createElement( 'ul' );
			list.className = 'ccf-pt-points';
			for ( var i = 0; i < item.points.length; i++ ) {
				var li = document.createElement( 'li' );
				li.textContent = item.points[ i ];
				list.appendChild( li );
			}
			modal.appendChild( list );
		}

		if ( cfg.price ) {
			modal.appendChild( para( 'ccf-pt-price', cfg.price ) );
		}
		if ( cfg.guarantee ) {
			modal.appendChild( para( 'ccf-pt-guarantee', cfg.guarantee ) );
		}
		if ( cfg.promo ) {
			modal.appendChild( para( 'ccf-pt-promo', cfg.promo ) );
		}

		var actions = document.createElement( 'div' );
		actions.className = 'ccf-pro-modal-actions';

		var upgrade = document.createElement( 'a' );
		upgrade.className = 'button button-primary ccf-pt-cta';
		upgrade.href = item.url || cfg.url || '#';
		upgrade.target = '_blank';
		upgrade.rel = 'noopener';
		upgrade.textContent = cfg.ctaPattern ? cfg.ctaPattern.replace( '%s', item.name ) : ( cfg.cta || 'Get Pro' );

		var dismiss = document.createElement( 'button' );
		dismiss.type = 'button';
		dismiss.className = 'button';
		dismiss.textContent = cfg.dismiss || 'Maybe later';
		dismiss.addEventListener( 'click', closeModal );

		actions.appendChild( upgrade );
		actions.appendChild( dismiss );
		modal.appendChild( actions );

		if ( cfg.purchasedUrl && cfg.purchasedText ) {
			var bought = document.createElement( 'a' );
			bought.className = 'ccf-pt-purchased';
			bought.href = cfg.purchasedUrl;
			bought.target = '_blank';
			bought.rel = 'noopener';
			bought.textContent = cfg.purchasedText;
			modal.appendChild( bought );
		}

		overlay.appendChild( modal );
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );
		document.addEventListener( 'keydown', onKey );
		document.body.appendChild( overlay );
		upgrade.focus();
	}

	function findField( name ) {
		var fields = cfg.fields || [];
		for ( var i = 0; i < fields.length; i++ ) {
			if ( fields[ i ].name === name ) {
				return fields[ i ];
			}
		}
		return null;
	}

	// Delegated: the builder modal is created dynamically.
	document.addEventListener( 'click', function ( e ) {
		var teaser = e.target && e.target.closest ? e.target.closest( '.ccf-pro-teaser' ) : null;
		if ( ! teaser ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();

		var name = teaser.getAttribute( 'data-pro-name' ) || 'Pro Field';
		var def = findField( name );

		openModal( {
			name: name,
			desc: def ? def.desc : ( teaser.getAttribute( 'data-pro-desc' ) || '' ),
			points: def && def.points ? def.points : [],
			url: def && def.url ? def.url : ( teaser.getAttribute( 'data-pro-url' ) || '' )
		} );
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

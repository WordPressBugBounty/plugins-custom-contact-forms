/**
 * CCF Builder Modern — Yes/No dropdown -> toggle switch enhancer.
 *
 * Progressive enhancement only. The original Backbone <select> elements are
 * left untouched in the DOM (just visually hidden); they remain the single
 * source of truth. Backbone reads `select.value` and listens for `change`
 * events, so this layer simply renders a switch on top of each Yes/No select
 * and writes the value + fires a native bubbling `change` event when toggled.
 *
 * No edits to the Backbone bundle, the minified build, or the templates.
 *
 * Critical visual styles are applied INLINE (the 2016 builder CSS has very
 * high specificity and overrides external stylesheets), so the switch renders
 * reliably regardless of cascade.
 *
 * @package Custom_Contact_Forms
 * @author  Dmitry Alexander
 */
( function () {
	'use strict';

	// Only the genuinely binary Yes/No controls. Token-based class matching,
	// so e.g. `.form-pause` never matches `form-pause-message`, and
	// `.form-post-creation` never matches `form-post-creation-type`.
	var SELECTORS = [
		'select.field-required',
		'select.field-conditionals-enabled',
		'select.hide-title',
		'select.form-pause',
		'select.form-require-logged-in',
		'select.form-post-creation',
		'select.form-email-notification-active',
		'select.form-email-notification-include-uploads'
	].join( ',' );

	var ENHANCED = 'data-ccf-toggle-enhanced';
	var ON_COLOR = '#2271b1';  // WP admin primary blue
	var OFF_COLOR = '#c3c4c7'; // WP admin grey

	/**
	 * A real Yes/No control: exactly two options valued "0" and "1".
	 * value "1" is always the affirmative/on state across every CCF control.
	 */
	function isBinary( select ) {
		var opts = select.options;
		if ( ! opts || opts.length !== 2 ) {
			return false;
		}
		var a = opts[0].value;
		var b = opts[1].value;
		return ( a === '0' && b === '1' ) || ( a === '1' && b === '0' );
	}

	/**
	 * Find a human label for accessibility (the field's existing <label>).
	 */
	function labelText( select ) {
		var txt = '';
		if ( select.id ) {
			var assoc = document.querySelector( 'label[for="' + select.id + '"]' );
			if ( assoc ) {
				txt = assoc.textContent || '';
			}
		}
		if ( ! txt ) {
			var prev = select.previousElementSibling;
			if ( prev && prev.tagName === 'LABEL' ) {
				txt = prev.textContent || '';
			}
		}
		return txt.replace( /[:\s]+$/, '' ).trim();
	}

	function paint( track, knob, on ) {
		track.style.background = on ? ON_COLOR : OFF_COLOR;
		track.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		knob.style.transform = on ? 'translateX(18px)' : 'translateX(0)';
	}

	function fireChange( select ) {
		var evt;
		try {
			evt = new Event( 'change', { bubbles: true } );
		} catch ( e ) {
			evt = document.createEvent( 'HTMLEvents' );
			evt.initEvent( 'change', true, false );
		}
		select.dispatchEvent( evt );
	}

	function buildToggle( select ) {
		var on = ( select.value === '1' );

		var track = document.createElement( 'button' );
		track.type = 'button';
		track.className = 'ccf-toggle';
		track.setAttribute( 'role', 'switch' );
		var name = labelText( select );
		if ( name ) {
			track.setAttribute( 'aria-label', name );
		}
		// Inline = bulletproof against the builder's high-specificity CSS.
		track.style.cssText =
			'position:relative;display:inline-block;box-sizing:border-box;' +
			'width:40px;height:22px;min-width:40px;padding:0;margin:0 0 0 2px;' +
			'border:0;border-radius:22px;cursor:pointer;vertical-align:middle;' +
			'-webkit-appearance:none;appearance:none;box-shadow:none;' +
			'transition:background .15s ease;outline-offset:2px;';

		var knob = document.createElement( 'span' );
		knob.className = 'ccf-toggle-knob';
		knob.style.cssText =
			'position:absolute;top:2px;left:2px;width:18px;height:18px;' +
			'border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);' +
			'transition:transform .15s ease;pointer-events:none;';
		track.appendChild( knob );

		paint( track, knob, on );

		function reflect() {
			paint( track, knob, select.value === '1' );
		}

		function flip() {
			var next = ( select.value === '1' ) ? '0' : '1';
			if ( select.value === next ) {
				return;
			}
			select.value = next;
			fireChange( select ); // drives Backbone save + dependent UI
			reflect();
		}

		track.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			flip();
		} );

		track.addEventListener( 'keydown', function ( e ) {
			if ( e.key === ' ' || e.key === 'Enter' || e.keyCode === 32 || e.keyCode === 13 ) {
				e.preventDefault();
				flip();
			}
		} );

		// If Backbone (or anything else) changes the select, keep the switch in sync.
		select.addEventListener( 'change', reflect );

		return track;
	}

	function enhance( select ) {
		if ( ! select || select.getAttribute( ENHANCED ) === '1' || ! isBinary( select ) ) {
			return;
		}
		select.setAttribute( ENHANCED, '1' );
		select.style.display = 'none'; // kept in DOM; Backbone still reads .value
		var toggle = buildToggle( select );
		select.parentNode.insertBefore( toggle, select.nextSibling );
	}

	function scan( root ) {
		var nodes = ( root || document ).querySelectorAll( SELECTORS );
		for ( var i = 0; i < nodes.length; i++ ) {
			enhance( nodes[ i ] );
		}
	}

	function init() {
		scan( document );

		// Field modals and form settings re-render through Backbone, producing
		// fresh selects. Enhance them as they appear.
		if ( ! window.MutationObserver ) {
			return;
		}
		var observer = new MutationObserver( function ( mutations ) {
			for ( var i = 0; i < mutations.length; i++ ) {
				var added = mutations[ i ].addedNodes;
				for ( var j = 0; j < added.length; j++ ) {
					var node = added[ j ];
					if ( node.nodeType !== 1 ) {
						continue;
					}
					if ( node.matches && node.matches( SELECTORS ) ) {
						enhance( node );
					}
					if ( node.querySelectorAll ) {
						scan( node );
					}
				}
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

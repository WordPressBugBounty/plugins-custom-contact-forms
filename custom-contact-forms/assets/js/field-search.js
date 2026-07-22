/**
 * Field search for the form builder palette.
 *
 * Injects a search input above the field list in the builder's left
 * sidebar and filters field buttons by their visible name as you type.
 * The builder modal is created dynamically by Backbone, so we watch for
 * it rather than assuming it exists at load.
 *
 * @package Custom_Contact_Forms
 */
( function () {
	'use strict';

	function inject( sidebar ) {
		if ( sidebar.querySelector( '.ccf-field-search' ) ) {
			return;
		}

		var wrap = document.createElement( 'div' );
		wrap.className = 'ccf-field-search-wrap';
		var input = document.createElement( 'input' );
		input.type = 'search';
		input.className = 'ccf-field-search';
		input.placeholder = ( window.ccfFieldSearch && window.ccfFieldSearch.placeholder ) || 'Search for a field…';
		input.setAttribute( 'aria-label', input.placeholder );
		wrap.appendChild( input );
		sidebar.insertBefore( wrap, sidebar.firstChild );

		input.addEventListener( 'input', function () {
			var term = input.value.toLowerCase().replace( /^\s+|\s+$/g, '' );
			var fields = sidebar.querySelectorAll( '.field[data-field-type]' );
			for ( var i = 0; i < fields.length; i++ ) {
				var label = fields[ i ].querySelector( 'h4' );
				var text = label ? label.textContent.toLowerCase() : '';
				fields[ i ].style.display = ( ! term || -1 !== text.indexOf( term ) ) ? '' : 'none';
			}
		} );

		// Don't let typing bubble into the builder's own key handlers.
		input.addEventListener( 'keydown', function ( e ) { e.stopPropagation(); } );
	}

	function scan() {
		var sidebars = document.querySelectorAll( '.ccf-main-modal .left-sidebar' );
		for ( var i = 0; i < sidebars.length; i++ ) {
			inject( sidebars[ i ] );
		}
	}

	if ( window.MutationObserver ) {
		var mo = new MutationObserver( scan );
		mo.observe( document.documentElement, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', scan );
	} else {
		scan();
	}
} )();

/**
 * "Match my site" form style: for themes without theme.json colors, borrow
 * the accent color from the page itself (a theme button, or a link in the
 * content) so the form's button and focus color fit the site.
 *
 * @since 7.21.0
 */
( function() {
	'use strict';

	function visible( color ) {
		if ( ! color || 'transparent' === color ) {
			return false;
		}

		var m = color.match( /rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)(?:[,\s/]+([\d.]+))?/ );

		if ( ! m ) {
			return false;
		}

		if ( undefined !== m[4] && parseFloat( m[4] ) < 0.5 ) {
			return false;
		}

		return true;
	}

	function luminance( color ) {
		var m = color.match( /(\d+)[,\s]+(\d+)[,\s]+(\d+)/ );

		if ( ! m ) {
			return 0;
		}

		return ( 0.299 * m[1] + 0.587 * m[2] + 0.114 * m[3] ) / 255;
	}

	function outsideForms( el ) {
		return ! el.closest( '.ccf-form-wrapper, .ccf-login-wrapper, #wpadminbar' );
	}

	function findAccent() {
		var buttons = document.querySelectorAll( '.wp-block-button__link, .wp-element-button, button[type="submit"], input[type="submit"], .button' );
		var i, style;

		for ( i = 0; i < buttons.length; i++ ) {
			if ( ! outsideForms( buttons[ i ] ) ) {
				continue;
			}

			style = window.getComputedStyle( buttons[ i ] );

			if ( visible( style.backgroundColor ) ) {
				return { bg: style.backgroundColor, text: style.color, radius: style.borderTopLeftRadius };
			}
		}

		var links = document.querySelectorAll( '.entry-content a, .post-content a, .page-content a, article a, main a' );

		for ( i = 0; i < links.length; i++ ) {
			if ( ! outsideForms( links[ i ] ) ) {
				continue;
			}

			style = window.getComputedStyle( links[ i ] );

			if ( visible( style.color ) ) {
				return { bg: style.color, text: luminance( style.color ) > 0.6 ? '#111111' : '#ffffff', radius: '' };
			}
		}

		return null;
	}

	function run() {
		var wrappers = document.querySelectorAll( '[data-ccf-match="1"]' );

		if ( ! wrappers.length ) {
			return;
		}

		var accent = findAccent();

		if ( ! accent ) {
			return;
		}

		for ( var i = 0; i < wrappers.length; i++ ) {
			wrappers[ i ].style.setProperty( '--ccf-accent', accent.bg );
			wrappers[ i ].style.setProperty( '--ccf-accent-text', accent.text );

			if ( accent.radius && '0px' !== accent.radius ) {
				wrappers[ i ].style.setProperty( '--ccf-button-radius', accent.radius );
			}
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
}() );

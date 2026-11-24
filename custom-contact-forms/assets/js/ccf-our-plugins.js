/**
 * Forms > Our Plugins: Install and Activate buttons.
 *
 * Installing goes through WordPress's own installer (wp.updates), so it
 * downloads from WordPress.org and asks for FTP details when the site
 * needs them, exactly like Plugins > Add New Plugin.
 *
 * @package Custom_Contact_Forms
 * @since   7.21.0
 */
( function( $ ) {
	'use strict';

	var cfg = window.ccfOurPlugins;

	if ( ! cfg ) {
		return;
	}

	// WordPress's installer errors can contain HTML; show them as plain text.
	function plain( message ) {
		if ( ! message ) {
			return '';
		}

		try {
			return new window.DOMParser().parseFromString( String( message ), 'text/html' ).body.textContent.trim();
		} catch ( e ) {
			return String( message );
		}
	}

	function showError( $card, message ) {
		var text = plain( message );
		text = text ? text + ' ' + cfg.i18n.alsoManual : cfg.i18n.failed;
		$card.find( '.ccf-our-plugin-error' ).text( text ).prop( 'hidden', false );
	}

	function busy( $button, text ) {
		$button.prop( 'disabled', true ).addClass( 'updating-message' ).text( text );
	}

	function activate( $card, $button ) {
		busy( $button, cfg.i18n.activating );
		$card.find( '.ccf-our-plugin-error' ).prop( 'hidden', true );

		$.post( cfg.ajaxUrl, {
			action: 'ccf_activate_our_plugin',
			nonce: cfg.nonce,
			slug: $card.data( 'slug' )
		} ).done( function( response ) {
			if ( response && response.success ) {
				// Reload so the card can link to the plugin's own page.
				window.location.reload();
				return;
			}

			$button.prop( 'disabled', false ).removeClass( 'updating-message' ).text( cfg.i18n.activate );
			showError( $card, response && response.data && response.data.message );
		} ).fail( function( xhr ) {
			var data = xhr.responseJSON && xhr.responseJSON.data;
			$button.prop( 'disabled', false ).removeClass( 'updating-message' ).text( cfg.i18n.activate );
			showError( $card, data && data.message );
		} );
	}

	$( document ).on( 'click', '.ccf-our-plugin-install', function() {
		var $button = $( this );
		var $card = $button.closest( '.ccf-our-plugin' );
		var original = $button.text();

		if ( ! window.wp || ! wp.updates || ! wp.updates.installPlugin ) {
			showError( $card );
			return;
		}

		busy( $button, cfg.i18n.installing );
		$card.find( '.ccf-our-plugin-error' ).prop( 'hidden', true );

		wp.updates.installPlugin( {
			slug: $card.data( 'slug' ),
			success: function() {
				$button
					.removeClass( 'updating-message ccf-our-plugin-install' )
					.addClass( 'ccf-our-plugin-activate' )
					.prop( 'disabled', false )
					.text( cfg.i18n.activate );
			},
			error: function( response ) {
				$button.prop( 'disabled', false ).removeClass( 'updating-message' ).text( original );
				showError( $card, response && response.errorMessage );
			}
		} );
	} );

	$( document ).on( 'click', '.ccf-our-plugin-activate', function() {
		var $button = $( this );
		activate( $button.closest( '.ccf-our-plugin' ), $button );
	} );
}( jQuery ) );

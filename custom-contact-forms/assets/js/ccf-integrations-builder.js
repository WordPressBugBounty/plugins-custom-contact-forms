/**
 * Form builder: Email Marketing and Webhooks under Form Settings.
 *
 * Extends wp.ccf.views.FormSettings without replacing it: the panel is added
 * after the existing settings render, and its values are copied onto the
 * form model whenever the existing settings are saved.
 *
 * @since 7.18.0
 */
( function( $, _ ) {
	'use strict';

	if ( ! window.wp || ! wp.ccf || ! wp.ccf.views || ! wp.ccf.views.FormSettings || ! window.ccfIntegrations ) {
		return;
	}

	var cfg = window.ccfIntegrations;
	var proto = wp.ccf.views.FormSettings.prototype;
	var originalRender = proto.render;
	var originalSave = proto.save;
	var listCache = {};

	function providerById( id ) {
		return _.findWhere( cfg.providers, { id: id } ) || null;
	}

	function emailSettings( model ) {
		return _.defaults( _.clone( model.get( 'emailMarketing' ) || {} ), {
			provider: '',
			list: '',
			doubleOptin: true,
			optinField: ''
		} );
	}

	function checkboxFields( model ) {
		var out = [];
		var fields = model.get( 'fields' );

		if ( fields && fields.each ) {
			fields.each( function( field ) {
				var type = field.get( 'type' );

				if ( 'checkboxes' === type || 'email-optin' === type ) {
					out.push( {
						slug: field.get( 'slug' ),
						label: field.get( 'label' ) || field.get( 'slug' )
					} );
				}
			} );
		}

		return out;
	}

	function fillLists( panel, lists, selected ) {
		var $select = panel.find( '.ccf-em-list' );

		$select.empty();
		$select.append( $( '<option>' ).val( '' ).text( lists.length ? cfg.i18n.choose : cfg.i18n.noLists ) );

		var found = false;

		_.each( lists, function( list ) {
			var $option = $( '<option>' ).val( list.id ).text( list.name );

			if ( list.id === selected ) {
				$option.prop( 'selected', true );
				found = true;
			}

			$select.append( $option );
		} );

		// Keep a saved list visible even if it could not be loaded, so
		// saving the form does not silently clear it.
		if ( selected && ! found ) {
			$select.append( $( '<option>' ).val( selected ).text( selected + ' ' + cfg.i18n.notSaved ).prop( 'selected', true ) );
		}
	}

	function loadLists( view, refresh ) {
		var panel = view.$integrations;
		var providerId = panel.find( '.ccf-em-provider' ).val();
		var saved = emailSettings( view.model );
		var selected = panel.find( '.ccf-em-list' ).val() || ( providerId === saved.provider ? saved.list : '' );
		var $status = panel.find( '.ccf-em-list-status' );

		if ( ! providerId ) {
			return;
		}

		if ( ! refresh && listCache[ providerId ] ) {
			fillLists( panel, listCache[ providerId ], selected );
			return;
		}

		$status.text( cfg.i18n.loading );

		$.post( cfg.ajaxUrl, {
			action: 'ccf_email_lists',
			nonce: cfg.nonce,
			provider: providerId,
			refresh: refresh ? 1 : 0
		} ).done( function( response ) {
			if ( response && response.success ) {
				listCache[ providerId ] = response.data.lists;
				fillLists( panel, response.data.lists, selected );
				$status.text( '' );
			} else {
				fillLists( panel, [], selected );
				$status.text( response && response.data && response.data.message ? response.data.message : '' );
			}
		} ).fail( function() {
			fillLists( panel, [], selected );
			$status.text( cfg.i18n.testFailed );
		} );
	}

	function toggleDetails( view ) {
		var panel = view.$integrations;
		var provider = providerById( panel.find( '.ccf-em-provider' ).val() );

		if ( ! provider ) {
			panel.find( '.ccf-em-details' ).hide();
			return;
		}

		panel.find( '.ccf-em-details' ).show();
		panel.find( '.ccf-em-list-label' ).text( provider.listLabel + ':' );
		panel.find( '.ccf-em-double-row' ).toggle( !! provider.doubleOptin );
	}

	proto.render = function() {
		var result = originalRender.apply( this, arguments );
		var view = this;
		var settings = emailSettings( this.model );
		var webhooks = this.model.get( 'webhooks' ) || {};
		var template = wp.ccf.utils.template( 'ccf-form-integrations-template' );

		var $panel = $( '<div class="ccf-form-integrations"></div>' ).html( template( {
			data: {
				connected: _.where( cfg.providers, { connected: true } ),
				settings: settings,
				checkboxes: checkboxFields( this.model ),
				webhookUrls: webhooks.urls || '',
				settingsUrl: cfg.settingsUrl
			}
		} ) );

		// Show the panel after the notification settings and before post
		// creation, which is the least used section.
		var $postCreation = $( this.el ).find( '.form-post-creation' ).closest( 'p' ).prevAll( 'h3' ).first();

		if ( $postCreation.length ) {
			$postCreation.before( $panel );
		} else {
			$( this.el ).append( $panel );
		}

		this.$integrations = $panel;

		$panel.on( 'change', '.ccf-em-provider', function() {
			// A list from another service never applies to this one.
			$panel.find( '.ccf-em-list' ).empty();
			$panel.find( '.ccf-em-list-status' ).text( '' );
			view.save();
			toggleDetails( view );
			loadLists( view, false );
		} );

		$panel.on( 'change', 'select', function() {
			view.save();
		} );

		$panel.on( 'change blur', 'textarea', function() {
			view.save();
		} );

		$panel.on( 'click', '.ccf-em-refresh', function( event ) {
			event.preventDefault();
			loadLists( view, true );
		} );

		$panel.on( 'click', '.ccf-webhook-test', function( event ) {
			event.preventDefault();

			var $status = $panel.find( '.ccf-webhook-status' );
			$status.text( cfg.i18n.sending );

			$.post( cfg.ajaxUrl, {
				action: 'ccf_test_webhook',
				nonce: cfg.nonce,
				form_id: view.model.get( 'id' ) || 0,
				urls: $panel.find( '.ccf-webhook-urls' ).val()
			} ).done( function( response ) {
				$status.text( response && response.data && response.data.message ? response.data.message : cfg.i18n.testFailed );
			} ).fail( function() {
				$status.text( cfg.i18n.testFailed );
			} );
		} );

		// Until the lists arrive, keep the saved choice selected so an early
		// save cannot clear it.
		var $list = $panel.find( '.ccf-em-list' );
		$list.append( $( '<option>' ).val( settings.list ).text( settings.list ? cfg.i18n.loading : cfg.i18n.choose ) );

		toggleDetails( this );
		loadLists( this, false );

		return result;
	};

	proto.save = function() {
		var result = originalSave.apply( this, arguments );

		if ( ! this.$integrations || ! this.$integrations.closest( 'body' ).length ) {
			return result;
		}

		var panel = this.$integrations;
		var $provider = panel.find( '.ccf-em-provider' );

		if ( $provider.length ) {
			this.model.set( 'emailMarketing', {
				provider: $provider.val() || '',
				list: panel.find( '.ccf-em-list' ).val() || '',
				doubleOptin: '0' !== panel.find( '.ccf-em-double' ).val(),
				optinField: panel.find( '.ccf-em-optin' ).val() || ''
			} );
		}

		this.model.set( 'webhooks', {
			urls: panel.find( '.ccf-webhook-urls' ).val() || ''
		} );

		return result;
	};
}( jQuery, _ ) );

/**
 * Form builder: accent color, schedule and entry limit, and entry cleanup
 * under Form Settings.
 *
 * Extends wp.ccf.views.FormSettings the same way the integrations panel
 * does: the panels are added after the existing settings render, and their
 * values are copied onto the form model whenever the settings are saved.
 *
 * @since 7.21.0
 */
( function( $, _ ) {
	'use strict';

	if ( ! window.wp || ! wp.ccf || ! wp.ccf.views || ! wp.ccf.views.FormSettings || ! window.ccfFormExtras ) {
		return;
	}

	var cfg = window.ccfFormExtras;
	var proto = wp.ccf.views.FormSettings.prototype;
	var originalRender = proto.render;
	var originalSave = proto.save;

	function availability( model ) {
		return _.defaults( _.clone( model.get( 'availability' ) || {} ), {
			openAt: '',
			closeAt: '',
			beforeMessage: '',
			afterMessage: '',
			limit: 0,
			limitMessage: '',
			showRemaining: false,
			count: 0
		} );
	}

	function toggleAccent( view ) {
		var theme = $( view.el ).find( '.form-theme' ).val();
		var $row = view.$ccfAccent;

		if ( ! $row ) {
			return;
		}

		$row.toggle( _.indexOf( cfg.presets, theme ) !== -1 );
		$row.find( '.ccf-accent-color' ).toggle( '1' === $row.find( '.ccf-accent-mode' ).val() );
	}

	function toggleAvailability( view ) {
		var $panel = view.$ccfAvailability;

		if ( ! $panel ) {
			return;
		}

		var openAt = $panel.find( '.ccf-open-at' ).val();
		var closeAt = $panel.find( '.ccf-close-at' ).val();
		var limit = parseInt( $panel.find( '.ccf-entry-limit' ).val(), 10 ) || 0;

		$panel.find( '.ccf-before-message-row' ).toggle( !! openAt );
		$panel.find( '.ccf-after-message-row' ).toggle( !! closeAt );
		$panel.find( '.ccf-limit-details' ).toggle( limit > 0 );

		var $warning = $panel.find( '.ccf-date-warning' );

		// Both values use the same "YYYY-MM-DDTHH:MM" format, so they
		// compare correctly as text.
		if ( openAt && closeAt && closeAt <= openAt ) {
			$warning.text( cfg.i18n.closeBefore ).show();
		} else {
			$warning.hide();
		}
	}

	proto.render = function() {
		var result = originalRender.apply( this, arguments );
		var view = this;
		var $el = $( this.el );

		// Accent color, right under Form Theme.
		var accentTemplate = wp.ccf.utils.template( 'ccf-form-accent-template' );
		var $accent = $( accentTemplate( { data: { accent: this.model.get( 'accentColor' ) || '' } } ) ).filter( 'p' );
		var $themeRow = $el.find( '.form-theme' ).closest( 'p' );

		if ( $themeRow.length ) {
			$themeRow.after( $accent );
			this.$ccfAccent = $accent;
		}

		// Schedule, entry limit and cleanup, after "Require User to Be
		// Logged In" and before the email notifications.
		var settings = availability( this.model );
		var availabilityTemplate = wp.ccf.utils.template( 'ccf-form-availability-template' );
		var cleanupTemplate = wp.ccf.utils.template( 'ccf-form-cleanup-template' );

		var $availability = $( '<div class="ccf-form-availability"></div>' ).html( availabilityTemplate( {
			data: _.extend( {}, settings, { defaults: cfg.messages } )
		} ) );

		var $cleanup = $( '<div class="ccf-form-cleanup"></div>' ).html( cleanupTemplate( {
			data: { days: parseInt( this.model.get( 'entryCleanupDays' ), 10 ) || 0 }
		} ) );

		var $loginRow = $el.find( '.form-require-logged-in' ).closest( 'p' );

		if ( $loginRow.length ) {
			$loginRow.after( $availability );
		} else {
			$el.append( $availability );
		}

		$availability.after( $cleanup );

		this.$ccfAvailability = $availability;
		this.$ccfCleanup = $cleanup;

		var count = parseInt( settings.count, 10 ) || 0;
		$availability.find( '.ccf-entry-count' ).text(
			1 === count ? cfg.i18n.oneEntry : cfg.i18n.entriesSoFar.replace( '%s', count )
		);

		// The settings view saves on "blur input" and "change select". Dates,
		// numbers and text areas need a nudge.
		$availability.on( 'change input', 'input, textarea', function() {
			view.save();
			toggleAvailability( view );
		} );

		$availability.on( 'click', '.ccf-clear-date', function( event ) {
			event.preventDefault();
			$availability.find( $( this ).data( 'target' ) ).val( '' );
			view.save();
			toggleAvailability( view );
		} );

		$el.off( 'change.ccfExtras' ).on( 'change.ccfExtras', '.form-theme, .ccf-accent-mode', function() {
			toggleAccent( view );
		} );

		if ( this.$ccfAccent ) {
			this.$ccfAccent.on( 'input change', '.ccf-accent-color', function() {
				view.save();
			} );
		}

		toggleAccent( this );
		toggleAvailability( this );

		return result;
	};

	proto.save = function() {
		var result = originalSave.apply( this, arguments );

		if ( this.$ccfAccent && this.$ccfAccent.closest( 'body' ).length ) {
			var custom = '1' === this.$ccfAccent.find( '.ccf-accent-mode' ).val();
			this.model.set( 'accentColor', custom ? this.$ccfAccent.find( '.ccf-accent-color' ).val() : '' );
		}

		if ( this.$ccfAvailability && this.$ccfAvailability.closest( 'body' ).length ) {
			var $a = this.$ccfAvailability;
			var old = availability( this.model );

			this.model.set( 'availability', {
				openAt: $a.find( '.ccf-open-at' ).val() || '',
				closeAt: $a.find( '.ccf-close-at' ).val() || '',
				beforeMessage: $a.find( '.ccf-before-message' ).val() || '',
				afterMessage: $a.find( '.ccf-after-message' ).val() || '',
				limit: Math.max( 0, parseInt( $a.find( '.ccf-entry-limit' ).val(), 10 ) || 0 ),
				limitMessage: $a.find( '.ccf-limit-message' ).val() || '',
				showRemaining: '1' === $a.find( '.ccf-show-remaining' ).val(),
				count: old.count
			} );
		}

		if ( this.$ccfCleanup && this.$ccfCleanup.closest( 'body' ).length ) {
			this.model.set( 'entryCleanupDays', parseInt( this.$ccfCleanup.find( '.ccf-cleanup-days' ).val(), 10 ) || 0 );
		}

		return result;
	};
}( jQuery, _ ) );

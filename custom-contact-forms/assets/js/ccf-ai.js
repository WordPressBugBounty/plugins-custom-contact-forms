/**
 * "Build with AI" — describe a form, get one built.
 *
 * Deliberately creates a new form server-side and opens it in the builder,
 * rather than injecting fields into the running Backbone builder. The builder
 * needs a registered model and view per field type, and inserting into it from
 * outside is where subtle breakage lives. Going through the same code path the
 * REST controller uses means an AI-built form is indistinguishable from a
 * hand-built one.
 *
 * @package Custom_Contact_Forms
 */

( function () {
	'use strict';

	var CFG = window.CCF_AI || null;

	if ( ! CFG ) {
		return;
	}

	var I18N = CFG.i18n;
	var dialog = null;

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( 'class' === key ) {
				node.className = attrs[ key ];
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	function close() {
		if ( dialog ) {
			dialog.hidden = true;
		}
	}

	/**
	 * When the feature is advertised but not yet usable, explain what is
	 * missing and link straight to it. Discovering the feature and being told
	 * it needs setting up is better than never discovering it at all.
	 */
	function showSetup( inner ) {
		var setup = CFG.setup || {};

		inner.appendChild( el( 'h2', { id: 'ccf-ai-heading' }, setup.heading || I18N.title ) );
		inner.appendChild( el( 'p', { class: 'ccf-ai-intro' }, setup.body || '' ) );

		var actions = el( 'div', { class: 'ccf-ai-actions' } );

		if ( setup.url && setup.label ) {
			var go = el( 'a', { href: setup.url, class: 'button button-primary' }, setup.label );
			actions.appendChild( go );
		}

		var close_btn = el( 'button', { type: 'button', class: 'button' }, I18N.cancel );
		close_btn.addEventListener( 'click', close );
		actions.appendChild( close_btn );

		inner.appendChild( actions );
	}

	/**
	 * Fill the wait with something to look at.
	 *
	 * The status lines are not real progress — we cannot know how far along a
	 * generation is — but a still screen for several seconds reads as broken,
	 * and the skeleton rows set the expectation that fields are coming.
	 */
	function showWorking( inner, textarea, actions ) {
		textarea.style.display = 'none';
		actions.style.display = 'none';

		var wrap = el( 'div', { class: 'ccf-ai-working' } );
		var orb = el( 'div', { class: 'ccf-ai-orb' } );
		var step = el( 'span', { class: 'ccf-ai-step' }, ( I18N.steps && I18N.steps[ 0 ] ) || I18N.working );

		wrap.appendChild( orb );
		wrap.appendChild( step );

		var skeleton = el( 'ul', { class: 'ccf-ai-skeleton' } );

		for ( var i = 0; i < 4; i++ ) {
			var row = el( 'li', {} );
			row.style.animationDelay = ( i * 0.12 ) + 's';
			skeleton.appendChild( row );
		}

		inner.appendChild( wrap );
		inner.appendChild( skeleton );

		var index = 0;
		var timer = setInterval( function () {
			if ( ! I18N.steps || ! I18N.steps.length ) {
				return;
			}

			index = ( index + 1 ) % I18N.steps.length;
			step.textContent = I18N.steps[ index ];

			// Restart the fade so each line arrives rather than swapping.
			step.style.animation = 'none';
			void step.offsetWidth;
			step.style.animation = '';
		}, 1600 );

		return function stop() {
			clearInterval( timer );

			if ( wrap.parentNode ) {
				wrap.parentNode.removeChild( wrap );
			}

			if ( skeleton.parentNode ) {
				skeleton.parentNode.removeChild( skeleton );
			}

			textarea.style.display = '';
			actions.style.display = '';
		};
	}

	function open() {
		dialog = document.getElementById( 'ccf-ai-dialog' );

		if ( ! dialog ) {
			// The markup is printed in admin_footer on CCF screens. If it is
			// missing the button would just sit there doing nothing, with no
			// way to tell a broken build from a broken click.
			window.console && console.error( 'CCF: AI dialog markup not found on this screen.' );
			return;
		}

		var inner = dialog.querySelector( '.ccf-ai-dialog-inner' );
		inner.innerHTML = '';

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				close();
			}
		} );

		if ( ! CFG.ready ) {
			showSetup( inner );
			dialog.hidden = false;
			return;
		}

		var heading = el( 'h2', { id: 'ccf-ai-heading' }, I18N.title );
		var intro = el( 'p', { class: 'ccf-ai-intro' }, I18N.intro );
		var textarea = el( 'textarea', { rows: '4', placeholder: I18N.placeholder, class: 'ccf-ai-input' } );
		var status = el( 'p', { class: 'ccf-ai-status' } );

		var submit = el( 'button', { type: 'button', class: 'button button-primary' }, I18N.submit );
		var cancel = el( 'button', { type: 'button', class: 'button' }, I18N.cancel );

		var actions = el( 'div', { class: 'ccf-ai-actions' } );
		actions.appendChild( submit );
		actions.appendChild( cancel );

		inner.appendChild( heading );
		inner.appendChild( intro );
		inner.appendChild( textarea );
		inner.appendChild( status );
		inner.appendChild( actions );

		dialog.hidden = false;
		textarea.focus();

		cancel.addEventListener( 'click', close );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function escHandler( event ) {
			if ( 'Escape' === event.key ) {
				close();
				document.removeEventListener( 'keydown', escHandler );
			}
		} );

		submit.addEventListener( 'click', function () {
			var description = textarea.value.trim();

			if ( ! description ) {
				status.textContent = I18N.empty;
				status.style.color = '#b32d2e';
				return;
			}

			submit.disabled = true;
			cancel.disabled = true;
			status.textContent = '';

			var stopWorking = showWorking( inner, textarea, actions );

			var body = new FormData();
			body.append( 'action', 'ccf_ai_build_form' );
			body.append( 'nonce', CFG.nonce );
			body.append( 'description', description );

			fetch( CFG.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).then( function ( res ) {
				// Read as text first. A PHP fatal returns an HTML error page,
				// and res.json() would throw on it — sending us to the generic
				// "something went wrong" with the actual cause discarded.
				return res.text().then( function ( raw ) {
					try {
						return JSON.parse( raw );
					} catch ( e ) {
						window.console && console.error( 'CCF AI: non-JSON response', res.status, raw );

						return {
							success: false,
							data: {
								message: I18N.failed + ' (HTTP ' + res.status + ' — see the browser console for the server response.)'
							}
						};
					}
				} );
			} ).then( function ( json ) {
				stopWorking();
				submit.disabled = false;
				cancel.disabled = false;

				if ( ! json || ! json.success ) {
					status.style.color = '#b32d2e';
					status.textContent = ( json && json.data && json.data.message ) || I18N.failed;
					return;
				}

				showResult( inner, json.data );
			} ).catch( function () {
				stopWorking();
				submit.disabled = false;
				cancel.disabled = false;
				status.style.color = '#b32d2e';
				status.textContent = I18N.failed;
			} );
		} );
	}

	/**
	 * Show what was built, then hand over to the builder.
	 *
	 * Anything the form needed that only Pro provides is named here rather
	 * than inserted into the form — a field type the free plugin cannot render
	 * would break submission entirely.
	 */
	function showResult( inner, data ) {
		inner.innerHTML = '';

		inner.appendChild( el( 'h2', {}, data.title ) );

		// Show the fields rather than a count. It confirms the thing did what
		// was asked before the page changes under them, and it is the only
		// moment they see the whole form at a glance.
		if ( data.fields && data.fields.length ) {
			var built = el( 'ul', { class: 'ccf-ai-built' } );

			data.fields.forEach( function ( field, i ) {
				var row = el( 'li', {} );
				row.style.animationDelay = ( i * 0.05 ) + 's';
				row.appendChild( el( 'span', {}, field.label || field.type ) );
				row.appendChild( el( 'span', { class: 'ccf-ai-type' }, field.type.replace( /-/g, ' ' ) ) );
				built.appendChild( row );
			} );

			inner.appendChild( built );
		}

		inner.appendChild( el( 'p', { class: 'ccf-ai-status' }, I18N.opening ) );

		if ( data.pro && data.pro.length ) {
			var proBox = el( 'div', { class: 'ccf-ai-pro' } );
			proBox.appendChild( el( 'p', { class: 'ccf-ai-pro-intro' }, I18N.proIntro ) );

			var list = el( 'ul', {} );

			data.pro.forEach( function ( label ) {
				list.appendChild( el( 'li', {}, label ) );
			} );

			proBox.appendChild( list );

			if ( CFG.proUrl ) {
				var link = el( 'a', { href: CFG.proUrl, target: '_blank', rel: 'noopener', class: 'button' }, I18N.proLink );
				proBox.appendChild( link );
			}

			inner.appendChild( proBox );

			// Give them a moment to read it rather than yanking the page away.
			setTimeout( function () {
				window.location.href = data.editUrl;
			}, 5000 );

			return;
		}

		// Long enough for the field list to finish animating in and be read.
		setTimeout( function () {
			window.location.href = data.editUrl;
		}, 1800 );
	}

	/**
	 * Put the button on the forms list and in the builder.
	 */
	/**
	 * Wire every AI button on the page, wherever it came from.
	 *
	 * Some are rendered server-side (the builder call-to-action), one is added
	 * here beside the page title. Binding by class covers both and stays
	 * correct if more are added later.
	 */
	function bindButtons() {
		var buttons = document.querySelectorAll( '.ccf-ai-open' );

		for ( var i = 0; i < buttons.length; i++ ) {
			if ( buttons[ i ].hasAttribute( 'data-ccf-ai-bound' ) ) {
				continue;
			}

			buttons[ i ].setAttribute( 'data-ccf-ai-bound', '1' );
			buttons[ i ].addEventListener( 'click', function ( event ) {
				event.preventDefault();
				open();
			} );
		}
	}

	/**
	 * Add the button beside the page title on the forms list.
	 */
	function addTitleButton() {
		var heading = document.querySelector( '.wp-heading-inline' );

		if ( ! heading || ! heading.parentNode || heading.parentNode.querySelector( '.ccf-ai-open' ) ) {
			return;
		}

		var button = el( 'button', { type: 'button', class: 'page-title-action ccf-ai-open' }, I18N.button );

		// A quiet sparkle rather than a coloured button — this sits beside
		// core's own actions and should not shout over them.
		button.insertBefore( document.createTextNode( '\u2728 ' ), button.firstChild );

		var addNew = heading.parentNode.querySelector( '.page-title-action' );

		if ( addNew ) {
			addNew.parentNode.insertBefore( button, addNew.nextSibling );
		} else {
			heading.parentNode.insertBefore( button, heading.nextSibling );
		}
	}

	function init() {
		addTitleButton();
		bindButtons();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

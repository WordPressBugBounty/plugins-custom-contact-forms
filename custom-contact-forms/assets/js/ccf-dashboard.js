/**
 * Forms dashboard: stat cards, entries and views chart, per-form table and
 * date range controls. The chart is drawn as SVG with no external library.
 *
 * @package Custom_Contact_Forms
 * @since 7.17.0
 */
( function() {
	'use strict';

	var data = window.ccfDashboard;
	var root = document.querySelector( '.ccf-dash' );

	if ( ! data || ! root ) {
		return;
	}

	var i18n = data.i18n;
	var SVG = 'http://www.w3.org/2000/svg';
	var numberFormat = window.Intl ? new Intl.NumberFormat( data.locale ) : null;

	function fmt( n ) {
		return numberFormat ? numberFormat.format( n ) : String( n );
	}

	function sprintf( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return text.replace( /%(\d+)\$s|%s/g, function( match, index ) {
			var value = index ? args[ index - 1 ] : args.shift();
			return undefined === value ? '' : value;
		} );
	}

	function prettyDate( ymd, withYear ) {
		var parts = ymd.split( '-' );
		var date = new Date( parts[0], parts[1] - 1, parts[2] );
		var options = withYear ? { month: 'short', day: 'numeric', year: 'numeric' } : { month: 'short', day: 'numeric' };
		try {
			return date.toLocaleDateString( data.locale, options );
		} catch ( e ) {
			return ymd;
		}
	}

	function el( name, attrs, parent ) {
		var node = document.createElementNS( SVG, name );
		for ( var key in attrs ) {
			if ( attrs.hasOwnProperty( key ) ) {
				node.setAttribute( key, attrs[ key ] );
			}
		}
		if ( parent ) {
			parent.appendChild( node );
		}
		return node;
	}

	/* Cards ------------------------------------------------------------- */

	function renderCards( report ) {
		var totals = report.totals;
		var values = {
			entries: fmt( totals.entries ),
			views: fmt( totals.views ),
			conversion: null === totals.conversion ? i18n.noViews : totals.conversion + '%',
			spam: fmt( totals.spam ),
			forms: fmt( totals.forms )
		};

		var cards = root.querySelectorAll( '.ccf-dash-card' );
		for ( var i = 0; i < cards.length; i++ ) {
			var key = cards[ i ].getAttribute( 'data-card' );
			if ( values.hasOwnProperty( key ) ) {
				cards[ i ].querySelector( '.ccf-dash-card-value' ).textContent = values[ key ];
			}
		}
	}

	/* Chart ------------------------------------------------------------- */

	// Round the top of the scale up to a tidy number, in four gridline steps.
	function niceMax( value ) {
		if ( value <= 4 ) {
			return 4;
		}
		var raw = value / 4;
		var magnitude = Math.pow( 10, Math.floor( Math.log( raw ) / Math.LN10 ) );
		var steps = [ 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10 ];
		for ( var i = 0; i < steps.length; i++ ) {
			// Counts are whole numbers, so gridlines should be too.
			if ( steps[ i ] * magnitude % 1 !== 0 ) {
				continue;
			}
			if ( steps[ i ] * magnitude >= raw ) {
				return steps[ i ] * magnitude * 4;
			}
		}
		return magnitude * 40;
	}

	var visible = { entries: true, views: false };

	function renderChart( report ) {
		var box = root.querySelector( '.ccf-dash-chart' );
		box.innerHTML = '';

		var width = Math.max( box.clientWidth, 300 );
		var height = 260;
		var pad = { top: 16, right: 16, bottom: 32, left: 40 };
		var innerW = width - pad.left - pad.right;
		var innerH = height - pad.top - pad.bottom;
		var days = report.days;
		var count = days.length;
		var shown = [ 1 ];
		if ( visible.entries ) {
			shown = shown.concat( report.entries );
		}
		if ( visible.views ) {
			shown = shown.concat( report.views );
		}
		var max = niceMax( Math.max.apply( null, shown ) );

		var svg = el( 'svg', {
			width: width,
			height: height,
			viewBox: '0 0 ' + width + ' ' + height,
			role: 'img',
			'aria-label': i18n.chartLabel,
			'class': 'ccf-dash-svg'
		}, box );

		var defs = el( 'defs', {}, svg );
		var gradient = el( 'linearGradient', { id: 'ccfDashFill', x1: 0, y1: 0, x2: 0, y2: 1 }, defs );
		el( 'stop', { offset: '0%', 'class': 'ccf-dash-fill-top' }, gradient );
		el( 'stop', { offset: '100%', 'class': 'ccf-dash-fill-bottom' }, gradient );

		function x( index ) {
			return pad.left + ( count > 1 ? index / ( count - 1 ) * innerW : innerW / 2 );
		}

		function y( value ) {
			return pad.top + innerH - value / max * innerH;
		}

		// Grid and y-axis labels.
		for ( var g = 0; g <= 4; g++ ) {
			var gv = max / 4 * g;
			var gy = y( gv );
			el( 'line', { x1: pad.left, x2: width - pad.right, y1: gy, y2: gy, 'class': 'ccf-dash-grid-line' }, svg );
			el( 'text', { x: pad.left - 8, y: gy + 4, 'class': 'ccf-dash-axis', 'text-anchor': 'end' }, svg ).textContent = fmt( Math.round( gv ) );
		}

		// X-axis labels, spaced to avoid overlap.
		var every = Math.max( 1, Math.ceil( count / Math.max( 2, Math.floor( innerW / 70 ) ) ) );
		for ( var d = 0; d < count; d += every ) {
			el( 'text', { x: x( d ), y: height - 10, 'class': 'ccf-dash-axis', 'text-anchor': 'middle' }, svg ).textContent = prettyDate( days[ d ] );
		}

		function path( series ) {
			var line = '';
			for ( var i = 0; i < count; i++ ) {
				line += ( i ? 'L' : 'M' ) + x( i ).toFixed( 1 ) + ' ' + y( series[ i ] ).toFixed( 1 );
			}
			return line;
		}

		// Views as a dashed line, entries as a filled area on top.
		if ( visible.views ) {
			el( 'path', { d: path( report.views ), 'class': 'ccf-dash-line-views' }, svg );
		}

		if ( visible.entries ) {
			var entriesLine = path( report.entries );
			el( 'path', { d: entriesLine + 'L' + x( count - 1 ).toFixed( 1 ) + ' ' + y( 0 ) + 'L' + x( 0 ).toFixed( 1 ) + ' ' + y( 0 ) + 'Z', 'class': 'ccf-dash-area', fill: 'url(#ccfDashFill)' }, svg );
			el( 'path', { d: entriesLine, 'class': 'ccf-dash-line-entries' }, svg );
		}

		if ( visible.entries && count <= 31 ) {
			for ( var p = 0; p < count; p++ ) {
				el( 'circle', { cx: x( p ), cy: y( report.entries[ p ] ), r: 3, 'class': 'ccf-dash-dot' }, svg );
			}
		}

		// Hover and focus readout.
		var guide = el( 'line', { y1: pad.top, y2: pad.top + innerH, 'class': 'ccf-dash-guide', visibility: 'hidden' }, svg );
		var tip = document.createElement( 'div' );
		tip.className = 'ccf-dash-tip';
		tip.hidden = true;
		box.appendChild( tip );

		function show( index ) {
			var gx = x( index );
			guide.setAttribute( 'x1', gx );
			guide.setAttribute( 'x2', gx );
			guide.setAttribute( 'visibility', 'visible' );
			tip.innerHTML = '';
			var strong = document.createElement( 'strong' );
			strong.textContent = sprintf( i18n.tipEntries, fmt( report.entries[ index ] ), prettyDate( days[ index ], true ) );
			var views = document.createElement( 'span' );
			views.textContent = sprintf( i18n.tipViews, fmt( report.views[ index ] ) );
			tip.appendChild( strong );
			tip.appendChild( views );
			tip.hidden = false;
			var left = Math.min( Math.max( gx - tip.offsetWidth / 2, 0 ), width - tip.offsetWidth );
			tip.style.left = left + 'px';
		}

		function hide() {
			guide.setAttribute( 'visibility', 'hidden' );
			tip.hidden = true;
		}

		var overlay = el( 'rect', { x: pad.left, y: pad.top, width: innerW, height: innerH, fill: 'transparent', tabindex: 0 }, svg );
		var current = count - 1;

		overlay.addEventListener( 'mousemove', function( event ) {
			var rect = svg.getBoundingClientRect();
			var rel = ( event.clientX - rect.left - pad.left ) / innerW;
			current = Math.max( 0, Math.min( count - 1, Math.round( rel * ( count - 1 ) ) ) );
			show( current );
		} );
		overlay.addEventListener( 'mouseleave', hide );
		overlay.addEventListener( 'focus', function() {
			show( current );
		} );
		overlay.addEventListener( 'blur', hide );
		overlay.addEventListener( 'keydown', function( event ) {
			if ( 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) {
				event.preventDefault();
				current = Math.max( 0, Math.min( count - 1, current + ( 'ArrowLeft' === event.key ? -1 : 1 ) ) );
				show( current );
			}
		} );
	}

	/* Table ------------------------------------------------------------- */

	function renderTable( report ) {
		var body = root.querySelector( '.ccf-dash-table tbody' );
		body.innerHTML = '';

		if ( ! report.forms.length ) {
			var row = body.insertRow();
			var cell = row.insertCell();
			cell.colSpan = 5;
			cell.className = 'ccf-dash-empty';
			cell.textContent = i18n.noForms;
			return;
		}

		var top = Math.max.apply( null, report.forms.map( function( form ) {
			return form.entries;
		} ).concat( [ 1 ] ) );

		report.forms.forEach( function( form ) {
			var tr = body.insertRow();

			var name = tr.insertCell();
			var link = document.createElement( 'a' );
			link.href = form.edit_url;
			link.textContent = form.title;
			name.appendChild( link );

			var entries = tr.insertCell();
			entries.className = 'num';
			var bar = document.createElement( 'span' );
			bar.className = 'ccf-dash-bar';
			bar.style.width = Math.round( form.entries / top * 100 ) + '%';
			var label = document.createElement( 'span' );
			label.className = 'ccf-dash-bar-label';
			label.textContent = fmt( form.entries );
			entries.appendChild( bar );
			entries.appendChild( label );

			var views = tr.insertCell();
			views.className = 'num';
			views.textContent = fmt( form.views );

			var conversion = tr.insertCell();
			conversion.className = 'num';
			conversion.textContent = null === form.conversion ? i18n.noViews : form.conversion + '%';

			var last = tr.insertCell();
			last.textContent = form.last_entry || i18n.never;
		} );
	}

	function render( report ) {
		renderCards( report );
		renderChart( report );
		renderTable( report );
		lastReport = report;
	}

	/* Ranges ------------------------------------------------------------ */

	var lastReport = data.report;
	var status = root.querySelector( '.ccf-dash-status' );
	var custom = root.querySelector( '.ccf-dash-custom' );
	var startInput = root.querySelector( '.ccf-dash-start' );
	var endInput = root.querySelector( '.ccf-dash-end' );
	var buttons = root.querySelectorAll( '.ccf-dash-range-btn' );

	startInput.value = data.report.start;
	endInput.value = data.report.end;
	startInput.max = data.today;
	endInput.max = data.today;

	function ymd( date ) {
		var m = date.getMonth() + 1;
		var d = date.getDate();
		return date.getFullYear() + '-' + ( m < 10 ? '0' : '' ) + m + '-' + ( d < 10 ? '0' : '' ) + d;
	}

	function load( start, end ) {
		status.textContent = i18n.loading;
		root.classList.add( 'is-loading' );

		var body = new FormData();
		body.append( 'action', 'ccf_dashboard_report' );
		body.append( 'nonce', data.nonce );
		body.append( 'start', start );
		body.append( 'end', end );

		fetch( data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( json ) {
				root.classList.remove( 'is-loading' );
				if ( ! json || ! json.success ) {
					throw new Error( 'report' );
				}
				status.textContent = '';
				render( json.data );
			} )
			.catch( function() {
				root.classList.remove( 'is-loading' );
				status.textContent = i18n.error;
			} );
	}

	function select( button ) {
		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].classList.toggle( 'is-active', buttons[ i ] === button );
			buttons[ i ].setAttribute( 'aria-pressed', buttons[ i ] === button ? 'true' : 'false' );
		}
	}

	Array.prototype.forEach.call( buttons, function( button ) {
		button.addEventListener( 'click', function() {
			select( button );
			var days = button.getAttribute( 'data-days' );

			if ( 'custom' === days ) {
				custom.hidden = false;
				startInput.focus();
				return;
			}

			custom.hidden = true;
			var parts = data.today.split( '-' );
			var end = new Date( parts[0], parts[1] - 1, parts[2] );
			var start = new Date( end.getTime() );
			start.setDate( start.getDate() - ( parseInt( days, 10 ) - 1 ) );
			load( ymd( start ), data.today );
		} );
	} );

	root.querySelector( '.ccf-dash-apply' ).addEventListener( 'click', function() {
		var start = startInput.value;
		var end = endInput.value;
		var span = ( new Date( end ) - new Date( start ) ) / 86400000;

		if ( ! start || ! end || start > end || span > 366 ) {
			status.textContent = i18n.badRange;
			return;
		}

		load( start, end );
	} );

	Array.prototype.forEach.call( root.querySelectorAll( '.ccf-dash-key' ), function( key ) {
		key.addEventListener( 'click', function() {
			var series = key.getAttribute( 'data-series' );
			var next = ! visible[ series ];

			// Keep at least one line on the chart.
			if ( ! next && ! visible[ 'entries' === series ? 'views' : 'entries' ] ) {
				return;
			}

			visible[ series ] = next;
			key.setAttribute( 'aria-pressed', next ? 'true' : 'false' );
			renderChart( lastReport );
		} );
	} );

	var resizeTimer;
	window.addEventListener( 'resize', function() {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( function() {
			renderChart( lastReport );
		}, 150 );
	} );

	render( data.report );
}() );

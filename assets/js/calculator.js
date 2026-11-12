/**
 * Lawzeek CRS Calculator — front end.
 * Draws the steps and posts the answers to the site's own REST endpoint. Scoring happens in PHP.
 * No external requests, no cookies, no storage.
 */
( function () {
	'use strict';

	var D = window.lzcrsData;
	if ( ! D ) {
		return;
	}
	var t = D.i18n;

	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return str.replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var v = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
			return v === undefined ? m : String( v );
		} );
	}
	function select( id, value, opts ) {
		return '<select id="' + id + '">' + opts.map( function ( o ) {
			return '<option value="' + esc( o[ 0 ] ) + '"' + ( String( o[ 0 ] ) === String( value ) ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>';
		} ).join( '' ) + '</select>';
	}

	var ARROW = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	var BACK = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13 8H3M7 4 3 8l4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	/* ---- Match the site's theme: read the theme's own button and heading styles. ---- */
	function rgb( str ) {
		var m = /rgba?\(\s*([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:[ ,/]+([\d.]+%?))?/.exec( str || '' );
		if ( ! m ) {
			return null;
		}
		var a = m[ 4 ] === undefined ? 1 : ( m[ 4 ].indexOf( '%' ) > 0 ? parseFloat( m[ 4 ] ) / 100 : parseFloat( m[ 4 ] ) );
		return { r: +m[ 1 ], g: +m[ 2 ], b: +m[ 3 ], a: a };
	}
	function lum( c ) {
		var f = function ( v ) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); };
		return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
	}
	function contrast( a, b ) {
		var x = lum( a ), y = lum( b );
		return ( Math.max( x, y ) + 0.05 ) / ( Math.min( x, y ) + 0.05 );
	}
	function isBrowserDefault( c ) {
		// Unstyled buttons are light grey in every browser; a theme that styles buttons picks its own colour.
		return ! c || c.a < 0.5 || ( Math.abs( c.r - c.g ) < 6 && Math.abs( c.g - c.b ) < 10 && c.r > 225 );
	}
	/** The colour actually behind the calculator: the first ancestor with a solid background, else white. */
	function pageBackground( el ) {
		for ( var n = el.parentNode; n && n.nodeType === 1; n = n.parentNode ) {
			var c = rgb( getComputedStyle( n ).backgroundColor );
			if ( c && c.a > 0.5 ) {
				return c;
			}
		}
		return { r: 255, g: 255, b: 255, a: 1 };
	}
	function hexToRgb( v ) {
		var m = /^\s*#([0-9a-f]{6})\s*$/i.exec( v || '' );
		return m ? 'rgb(' + parseInt( m[ 1 ].slice( 0, 2 ), 16 ) + ',' + parseInt( m[ 1 ].slice( 2, 4 ), 16 ) + ',' + parseInt( m[ 1 ].slice( 4 ), 16 ) + ')' : v;
	}

	function matchTheme( root ) {
		if ( root.getAttribute( 'data-lzcrs-colors' ) !== 'theme' || ! window.getComputedStyle ) {
			return;
		}
		var host = root.parentNode;
		var probe = document.createElement( 'div' );
		probe.setAttribute( 'aria-hidden', 'true' );
		probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;height:0;overflow:hidden';
		probe.innerHTML = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">x</a></div><button type="button">x</button><h2>x</h2><a href="#">x</a>';
		host.insertBefore( probe, root );
		try {
			var link = probe.querySelector( '.wp-element-button' );
			var btn = probe.querySelector( 'button' );
			var h2 = probe.querySelector( 'h2' );
			var a = probe.querySelector( 'h2 + a' );
			var pick = [ link, btn ].map( function ( el ) {
				var cs = getComputedStyle( el );
				return { el: el, bg: rgb( cs.backgroundColor ), fg: rgb( cs.color ), radius: cs.borderRadius };
			} ).filter( function ( p ) { return ! isBrowserDefault( p.bg ); } )[ 0 ];
			var vars = {};
			if ( pick ) {
				vars[ '--lzcrs-accent' ] = 'rgb(' + pick.bg.r + ',' + pick.bg.g + ',' + pick.bg.b + ')';
				var ink = pick.fg && pick.fg.a > 0.5 && contrast( pick.fg, pick.bg ) >= 3 ? pick.fg : ( lum( pick.bg ) > 0.4 ? { r: 19, g: 34, b: 42 } : { r: 255, g: 255, b: 255 } );
				vars[ '--lzcrs-accent-ink' ] = 'rgb(' + ink.r + ',' + ink.g + ',' + ink.b + ')';
				if ( pick.radius && pick.radius !== '0px' ) {
					vars[ '--lzcrs-btn-radius' ] = pick.radius;
				}
			} else {
				// No styled buttons: use the theme's link colour if it is a real colour (not grey or black).
				var lc = rgb( getComputedStyle( a ).color );
				if ( lc && Math.max( lc.r, lc.g, lc.b ) - Math.min( lc.r, lc.g, lc.b ) > 40 ) {
					vars[ '--lzcrs-accent' ] = 'rgb(' + lc.r + ',' + lc.g + ',' + lc.b + ')';
					vars[ '--lzcrs-accent-ink' ] = lum( lc ) > 0.4 ? '#13222a' : '#ffffff';
				}
			}
			// The accent must stand out from the page. On a dark page with a dark theme button (or the reverse),
			// fall back to the text colour, with the page colour as the button text.
			var bg = pageBackground( root ), text = rgb( getComputedStyle( root ).color );
			var acc = rgb( vars[ '--lzcrs-accent' ] || hexToRgb( getComputedStyle( root ).getPropertyValue( '--lzcrs-accent' ) ) );
			if ( text && acc && contrast( acc, bg ) < 2 ) {
				vars[ '--lzcrs-accent' ] = 'rgb(' + text.r + ',' + text.g + ',' + text.b + ')';
				vars[ '--lzcrs-accent-ink' ] = 'rgb(' + bg.r + ',' + bg.g + ',' + bg.b + ')';
				root.setAttribute( 'data-lzcrs-contrast', 'text' );
			}
			var hs = getComputedStyle( h2 );
			vars[ '--lzcrs-heading-font' ] = hs.fontFamily;
			vars[ '--lzcrs-heading-weight' ] = hs.fontWeight;
			Object.keys( vars ).forEach( function ( k ) { root.style.setProperty( k, vars[ k ] ); } );
			root.setAttribute( 'data-lzcrs-theme', pick ? 'button' : 'fallback' );
		} catch ( e ) {
			// Keep the default look.
		}
		host.removeChild( probe );
	}

	var BANDS = [ [ 3, t.below4 ] ];
	for ( var b = 4; b <= 9; b += 0.5 ) {
		BANDS.push( [ b, b.toFixed( 1 ) ] );
	}

	function mount( root, n ) {
		var uid = 'lzcrs' + n + '-';
		var state = {
			step: 0, busy: false, err: '', result: null,
			a: { age: 29, edu: 'bachelor', caEdu: 'none', L: 7, R: 6.5, W: 6.5, S: 6.5, fr: 'none', caExp: 0, frExp: 2, sibling: false, pnp: false },
			c: { name: '', email: '', phone: '', privacy: false, marketing: false, website: '' },
		};
		var heading = root.getAttribute( 'data-title' ) || '';
		var id = function ( k ) { return uid + k; };

		function field( k, label, control, hint ) {
			return '<div class="lzcrs-field"><label for="' + id( k ) + '">' + esc( label ) + '</label>' + control +
				( hint ? '<span class="lzcrs-hint">' + esc( hint ) + '</span>' : '' ) + '</div>';
		}
		function check( k, checked, label, hint ) {
			return '<label class="lzcrs-check"><input type="checkbox" id="' + id( k ) + '"' + ( checked ? ' checked' : '' ) + '><span>' + label +
				( hint ? '<small>' + esc( hint ) + '</small>' : '' ) + '</span></label>';
		}

		function body() {
			var a = state.a, c = state.c, o = D.options;
			switch ( state.step ) {
				case 0:
					return field( 'age', t.age, '<input type="number" id="' + id( 'age' ) + '" min="16" max="80" inputmode="numeric" value="' + esc( a.age ) + '">', t.ageHint ) +
						'<p class="lzcrs-note">' + esc( t.single ) + '</p>';
				case 1:
					return field( 'edu', t.edu, select( id( 'edu' ), a.edu, o.edu ) ) + field( 'caEdu', t.caEdu, select( id( 'caEdu' ), a.caEdu, o.caEdu ) );
				case 2:
					return '<fieldset class="lzcrs-group"><legend>' + esc( t.ielts ) + '</legend><div class="lzcrs-grid4">' +
						[ 'L', 'R', 'W', 'S' ].map( function ( k ) { return field( k, t[ k ], select( id( k ), a[ k ], BANDS ) ); } ).join( '' ) +
						'</div></fieldset>' + field( 'fr', t.fr, select( id( 'fr' ), a.fr, o.fr ) );
				case 3:
					return field( 'caExp', t.caExp, select( id( 'caExp' ), a.caExp, o.caExp ) ) + field( 'frExp', t.frExp, select( id( 'frExp' ), a.frExp, o.frExp ), t.frExpHint );
				case 4:
					return check( 'pnp', a.pnp, esc( t.pnp ), t.pnpHint ) + check( 'sibling', a.sibling, esc( t.sibling ), t.siblingHint );
				case 5:
					var privacy = esc( fmt( t.privacy, D.site ) ) + ( D.privacyUrl ? ' <a href="' + esc( D.privacyUrl ) + '" target="_blank" rel="noopener">' + esc( t.privacyLink ) + '</a>' : '' );
					return '<div class="lzcrs-teaser"><div class="lzcrs-blur" aria-hidden="true">000</div><p>' + esc( t.teaser ) + '</p></div>' +
						field( 'name', t.name, '<input type="text" id="' + id( 'name' ) + '" autocomplete="name" maxlength="120" value="' + esc( c.name ) + '">' ) +
						field( 'email', t.email, '<input type="email" id="' + id( 'email' ) + '" autocomplete="email" maxlength="200" value="' + esc( c.email ) + '">' ) +
						( D.phone === 'hidden' ? '' : field( 'phone', t.phone + ( D.phone === 'optional' ? ' ' + t.optional : '' ), '<input type="tel" id="' + id( 'phone' ) + '" autocomplete="tel" maxlength="30" value="' + esc( c.phone ) + '">' ) ) +
						'<div class="lzcrs-hp" aria-hidden="true"><label for="' + id( 'website' ) + '">' + esc( t.website ) + '</label><input type="text" id="' + id( 'website' ) + '" tabindex="-1" autocomplete="off" value=""></div>' +
						check( 'privacy', c.privacy, privacy ) + check( 'marketing', c.marketing, esc( t.marketing ), t.marketingHint );
				default:
					return result();
			}
		}

		function result() {
			var r = state.result, diff = r.total - r.cutoff, br = r.breakdown, rows = t.rows;
			var list = [ 'age', 'education', 'firstLanguage', 'secondLanguage', 'canadianWork' ];
			return '<div class="lzcrs-score"><div class="lzcrs-big" data-lzcrs-total>' + esc( r.total ) + '<small>' + esc( t.estimate ) + '</small></div>' +
				'<span class="lzcrs-pill ' + ( diff >= 0 ? 'good' : 'warn' ) + '">' + esc( fmt( diff >= 0 ? t.above : t.below, Math.abs( diff ), r.cutoff ) ) + '</span></div>' +
				'<p class="lzcrs-section">' + esc( t.fastest ) + '</p><ul class="lzcrs-gaps">' + r.gaps.map( function ( g ) {
					return '<li><b>' + esc( g.label ) + '</b><span class="lzcrs-delta">+' + esc( g.delta ) + '</span><span class="lzcrs-d">' + esc( g.detail ) +
						( g.reachesCutoff ? ' · <span class="lzcrs-reach">' + esc( t.reaches ) + '</span>' : '' ) + '</span></li>';
				} ).join( '' ) + '</ul>' +
				booking( r.cta ) +
				'<details><summary>' + esc( t.breakdown ) + '</summary><table><tbody>' +
				list.map( function ( k ) { return '<tr><td>' + esc( rows[ k ] ) + '</td><td>' + esc( br[ k ] ) + '</td></tr>'; } ).join( '' ) +
				'<tr class="sub"><td>' + esc( rows.core ) + '</td><td>' + esc( br.core ) + '</td></tr>' +
				'<tr><td>' + esc( rows.skillTransferability ) + '</td><td>' + esc( br.skillTransferability ) + '</td></tr>' +
				'<tr><td>' + esc( rows.additional ) + '</td><td>' + esc( br.additional ) + '</td></tr>' +
				'<tr class="sub"><td>' + esc( rows.total ) + '</td><td>' + esc( r.total ) + '</td></tr></tbody></table></details>' +
				'<p class="lzcrs-disclaimer">' + esc( fmt( t.disclaimer, D.site ) ) + '</p>';
		}

		/* The last step of the form: the consultant's booking link (button, or Calendly calendar inline). */
		function booking( cta ) {
			if ( ! cta || ! /^https?:\/\//.test( cta.url ) ) {
				return '';
			}
			var inline = cta.mode === 'inline' && /^https:\/\/(www\.)?calendly\.com\//.test( cta.embed || '' );
			return '<section class="lzcrs-book" aria-label="' + esc( t.nextStep ) + '"><span class="lzcrs-chip">' + esc( t.nextStep ) + '</span>' +
				'<h3>' + esc( t.bookTitle ) + '</h3><p>' + esc( t.bookText ) + '</p>' +
				( inline
					? '<iframe src="' + esc( cta.embed ) + '" title="' + esc( cta.text ) + '" loading="lazy"></iframe><a class="lzcrs-alt" href="' + esc( cta.url ) + '" target="_blank" rel="noopener">' + esc( t.openBooking ) + '</a>'
					: '<a class="lzcrs-btn primary lzcrs-cta" href="' + esc( cta.url ) + '" target="_blank" rel="noopener">' + esc( cta.text ) + ARROW + '</a>' ) +
				'</section>';
		}

		function render( focusHeading ) {
			var s = state.step;
			var done = s >= 5 ? 5 : s;
			var kicker = s < 5 ? fmt( t.stepOf, s + 1 ) : ( s === 5 ? t.almost : t.done );
			var dis = state.busy ? ' disabled' : '';
			var foot;
			if ( s < 5 ) {
				foot = '<button type="button" class="lzcrs-btn" data-act="back"' + ( s === 0 ? ' hidden' : '' ) + '>' + BACK + esc( t.back ) + '</button>' +
					'<button type="button" class="lzcrs-btn primary" data-act="next">' + esc( t.next ) + ARROW + '</button>';
			} else if ( s === 5 ) {
				foot = '<button type="button" class="lzcrs-btn" data-act="back"' + dis + '>' + BACK + esc( t.back ) + '</button>' +
					( state.busy
						? '<span class="lzcrs-status" role="status"><span class="lzcrs-pulse" aria-hidden="true"></span>' + esc( t.saving ) + '</span>'
						: '<button type="button" class="lzcrs-btn primary" data-act="submit">' + esc( t.showScore ) + ARROW + '</button>' );
			} else {
				foot = '<button type="button" class="lzcrs-btn" data-act="restart">' + esc( t.restart ) + '</button>';
			}
			var bars = '';
			for ( var i = 0; i < 5; i++ ) {
				bars += '<span' + ( i < done || s >= 5 ? ' class="on"' : ( i === done ? ' class="on"' : '' ) ) + '></span>';
			}
			root.innerHTML = '<div class="lzcrs-card" role="form" aria-label="' + esc( heading ) + '">' +
				'<div class="lzcrs-head"><div class="lzcrs-kicker"><span class="lzcrs-chip">' + esc( heading ) + '</span><span class="lzcrs-count">' + esc( kicker ) + '</span></div>' +
				'<h2 class="lzcrs-title" tabindex="-1">' + esc( t.titles[ s ] ) + '</h2>' +
				'<div class="lzcrs-steps" role="progressbar" aria-valuemin="0" aria-valuemax="5" aria-valuenow="' + Math.min( s + 1, 5 ) + '" aria-label="' + esc( kicker ) + '">' + bars + '</div></div>' +
				'<div class="lzcrs-body">' + body() + '<div class="lzcrs-alert" role="alert">' + ( state.err ? '<p class="lzcrs-err">' + esc( state.err ) + '</p>' : '' ) + '</div></div>' +
				'<div class="lzcrs-foot">' + foot + '</div></div>';
			if ( focusHeading ) {
				var h = root.querySelector( '.lzcrs-title' );
				if ( h ) {
					h.focus( { preventScroll: true } );
				}
			}
		}

		function read( el ) {
			var k = el.id.slice( uid.length ), a = state.a, c = state.c;
			if ( el.type === 'checkbox' ) {
				if ( k in a ) { a[ k ] = el.checked; } else { c[ k ] = el.checked; }
			} else if ( k in c ) {
				c[ k ] = el.value;
			} else if ( k === 'age' || k === 'caExp' || k === 'frExp' ) {
				var v = parseInt( el.value, 10 );
				if ( ! isNaN( v ) ) { a[ k ] = v; } else if ( k === 'age' ) { a.age = NaN; }
			} else if ( k === 'L' || k === 'R' || k === 'W' || k === 'S' ) {
				a[ k ] = parseFloat( el.value );
			} else if ( k in a ) {
				a[ k ] = el.value;
			}
		}
		root.addEventListener( 'change', function ( e ) { if ( e.target.id ) { read( e.target ); } } );
		root.addEventListener( 'input', function ( e ) {
			if ( e.target.id && e.target.tagName === 'INPUT' && e.target.type !== 'checkbox' ) { read( e.target ); }
		} );

		function submit() {
			var c = state.c;
			if ( ! c.name.trim() ) { state.err = t.errName; }
			else if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( c.email.trim() ) ) { state.err = t.errEmail; }
			else if ( D.phone === 'required' && ! c.phone.trim() ) { state.err = t.errPhone; }
			else if ( ! c.privacy ) { state.err = t.errPrivacy; }
			if ( state.err ) { return render(); }
			state.busy = true;
			render();
			var payload = {
				answers: state.a, name: c.name.trim(), email: c.email.trim(), phone: D.phone === 'hidden' ? '' : c.phone.trim(),
				privacy: c.privacy, marketing: c.marketing, website: c.website, page: window.location.href.split( '#' )[ 0 ],
			};
			fetch( D.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( payload ), credentials: 'omit' } )
				.then( function ( res ) {
					return res.json().catch( function () { return {}; } ).then( function ( body ) { return { ok: res.ok, body: body }; } );
				} )
				.then( function ( r ) {
					state.busy = false;
					if ( r.ok && typeof r.body.total === 'number' ) {
						state.result = r.body;
						state.step = 6;
						render( true );
					} else {
						state.err = ( r.body && r.body.message ) || t.errServer;
						render();
					}
				} )
				.catch( function () {
					state.busy = false;
					state.err = t.errNetwork;
					render();
				} );
		}

		root.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '[data-act]' ) : null;
			if ( ! btn || state.busy ) {
				return;
			}
			var act = btn.getAttribute( 'data-act' );
			state.err = '';
			if ( act === 'back' ) { state.step--; return render( true ); }
			if ( act === 'restart' ) { state.step = 0; state.result = null; return render( true ); }
			if ( act === 'next' ) {
				if ( state.step === 0 && ! ( state.a.age >= 16 && state.a.age <= 80 ) ) { state.err = t.errAge; return render(); }
				state.step++;
				return render( true );
			}
			if ( act === 'submit' ) { submit(); }
		} );

		render();
	}

	function boot() {
		var nodes = document.querySelectorAll( '[data-lzcrs]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( ! nodes[ i ].getAttribute( 'data-lzcrs-ready' ) ) {
				nodes[ i ].setAttribute( 'data-lzcrs-ready', '1' );
				matchTheme( nodes[ i ] );
				mount( nodes[ i ], i );
			}
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );

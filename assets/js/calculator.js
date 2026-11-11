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
				( r.cta ? '<a class="lzcrs-btn primary lzcrs-cta" href="' + esc( r.cta.url ) + '">' + esc( r.cta.text ) + '</a>' : '' ) +
				'<p class="lzcrs-section">' + esc( t.fastest ) + '</p><ul class="lzcrs-gaps">' + r.gaps.map( function ( g ) {
					return '<li><b>' + esc( g.label ) + '</b><span class="lzcrs-delta">+' + esc( g.delta ) + '</span><span class="lzcrs-d">' + esc( g.detail ) +
						( g.reachesCutoff ? ' · <span class="lzcrs-reach">' + esc( t.reaches ) + '</span>' : '' ) + '</span></li>';
				} ).join( '' ) + '</ul>' +
				'<details><summary>' + esc( t.breakdown ) + '</summary><table><tbody>' +
				list.map( function ( k ) { return '<tr><td>' + esc( rows[ k ] ) + '</td><td>' + esc( br[ k ] ) + '</td></tr>'; } ).join( '' ) +
				'<tr class="sub"><td>' + esc( rows.core ) + '</td><td>' + esc( br.core ) + '</td></tr>' +
				'<tr><td>' + esc( rows.skillTransferability ) + '</td><td>' + esc( br.skillTransferability ) + '</td></tr>' +
				'<tr><td>' + esc( rows.additional ) + '</td><td>' + esc( br.additional ) + '</td></tr>' +
				'<tr class="sub"><td>' + esc( rows.total ) + '</td><td>' + esc( r.total ) + '</td></tr></tbody></table></details>' +
				'<p class="lzcrs-disclaimer">' + esc( fmt( t.disclaimer, D.site ) ) + '</p>';
		}

		function render( focusHeading ) {
			var s = state.step;
			var pct = s >= 5 ? 100 : ( s / 5 ) * 100;
			var kicker = s < 5 ? fmt( t.stepOf, s + 1 ) : ( s === 5 ? t.almost : t.done );
			var dis = state.busy ? ' disabled' : '';
			var foot;
			if ( s < 5 ) {
				foot = '<button type="button" class="lzcrs-btn" data-act="back"' + ( s === 0 ? ' hidden' : '' ) + '>' + esc( t.back ) + '</button>' +
					'<button type="button" class="lzcrs-btn primary" data-act="next">' + esc( t.next ) + '</button>';
			} else if ( s === 5 ) {
				foot = '<button type="button" class="lzcrs-btn" data-act="back">' + esc( t.back ) + '</button>' +
					'<button type="button" class="lzcrs-btn primary" data-act="submit"' + dis + '>' + esc( state.busy ? t.saving : t.showScore ) + '</button>';
			} else {
				foot = '<button type="button" class="lzcrs-btn" data-act="restart">' + esc( t.restart ) + '</button>';
			}
			root.innerHTML = '<div class="lzcrs-card" role="form" aria-label="' + esc( heading ) + '">' +
				'<div class="lzcrs-head"><div class="lzcrs-kicker"><span>' + esc( heading ) + '</span><span>' + esc( kicker ) + '</span></div>' +
				'<h2 class="lzcrs-title" tabindex="-1">' + esc( t.titles[ s ] ) + '</h2>' +
				'<div class="lzcrs-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round( pct ) + '"><span style="width:' + pct + '%"></span></div></div>' +
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

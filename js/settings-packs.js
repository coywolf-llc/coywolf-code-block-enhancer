/**
 * Tools → Code Blocks: per-pack "Select all in pack" / "Clear pack"
 * helpers for the Languages checkbox groups rendered by
 * Coywolf_CBE_Settings::render_language_packs_field().
 *
 * Enqueued (footer) only on the settings page. Purely additive — with
 * JS disabled the checkboxes still work individually; only the two
 * convenience links do nothing.
 */
( function () {
	'use strict';

	document.querySelectorAll( '.cbe-lang-pack' ).forEach( function ( pack ) {
		pack.querySelectorAll( '[data-cbe-pack-action]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var checked = link.getAttribute( 'data-cbe-pack-action' ) === 'all';
				pack.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
					cb.checked = checked;
				} );
			} );
		} );
	} );
} )();

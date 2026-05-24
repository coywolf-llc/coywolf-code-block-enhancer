/**
 * Tools → Code Blocks unsaved-changes guard.
 *
 * Compares the live form state against a snapshot taken on page load.
 * When the user tries to navigate away dirty:
 *
 *   - Internal link click (admin menu, plugin row, etc.) is intercepted
 *     and a custom 3-button modal opens: Cancel / Don't save / Save
 *     changes. Save uses sessionStorage to remember the intended URL,
 *     submits the Settings API form, and a tiny redirect-after-save
 *     handler at the top of this file completes the jump once the
 *     post-save settings page loads.
 *
 *   - Tab close, reload, address-bar nav — beyond JS's reach for a
 *     custom dialog, so we fall through to the browser's native
 *     beforeunload prompt (a generic 2-option Leave / Stay).
 *
 * Form submissions of the Settings API form itself, the custom-theme
 * upload form, and the custom-theme remove form all bypass the guard:
 * they're intentional saves / navigations, not accidental drift away.
 */
( function () {
	'use strict';

	// ----- Redirect-after-save bootstrap ---------------------------------
	// Runs immediately so it doesn't race with anything. If we landed
	// back on the settings page after a "Save and leave" flow, finish
	// the jump now.
	try {
		var pending = window.sessionStorage.getItem( 'cbe_after_save_redirect' );
		if ( pending && window.location.search.indexOf( 'settings-updated=true' ) !== -1 ) {
			window.sessionStorage.removeItem( 'cbe_after_save_redirect' );
			window.location.replace( pending );
			return; // Stop initialising — page is about to unload.
		}
	} catch ( e ) {
		// sessionStorage disabled (privacy mode / quota). Fall through.
	}

	// ----- Dirty-state tracking ------------------------------------------

	var FORM_ID  = 'cbe-theme-form';
	var settingsForm = document.getElementById( FORM_ID );
	if ( ! settingsForm ) {
		return;
	}

	function getBoundInputs() {
		return document.querySelectorAll( '[form="' + FORM_ID + '"]' );
	}

	function snapshot() {
		var parts = [];
		var inputs = getBoundInputs();
		for ( var i = 0; i < inputs.length; i++ ) {
			var el = inputs[ i ];
			var tag = ( el.tagName || '' ).toLowerCase();
			if ( el.type === 'submit' || el.type === 'button' ) {
				continue; // Save Changes etc. — not state.
			}
			if ( el.type === 'checkbox' || el.type === 'radio' ) {
				parts.push( el.name + '|' + el.value + '|' + ( el.checked ? '1' : '0' ) );
			} else if ( tag === 'select' || el.type === 'text' || el.type === 'hidden' ) {
				parts.push( el.name + '|' + el.value );
			}
		}
		parts.sort();
		return parts.join( '\n' );
	}

	var initialSnapshot = snapshot();
	var suppressGuard   = false;

	function isDirty() {
		return ! suppressGuard && snapshot() !== initialSnapshot;
	}

	// ----- Suppress guard on intentional submissions ----------------------

	// Settings API form submit (Save Changes button, or Enter inside an
	// input that's bound via the form= attribute). The Save Changes
	// button itself is bound via form="cbe-theme-form" so a click on it
	// dispatches a submit on settingsForm.
	settingsForm.addEventListener( 'submit', function () {
		suppressGuard = true;
	} );

	// Custom-theme upload / remove forms — they post to admin-post.php
	// and intentionally navigate away. Any form on the page other than
	// our Settings API form counts.
	document.querySelectorAll( 'form' ).forEach( function ( f ) {
		if ( f.id === FORM_ID ) {
			return;
		}
		f.addEventListener( 'submit', function () {
			suppressGuard = true;
		} );
	} );

	// ----- beforeunload (tab close / reload / address bar) ---------------

	window.addEventListener( 'beforeunload', function ( e ) {
		if ( ! isDirty() ) {
			return;
		}
		// Modern browsers ignore the message string and show their own
		// generic prompt; we just need to set returnValue to anything
		// truthy to trigger it.
		e.preventDefault();
		e.returnValue = '';
		return '';
	} );

	// ----- Internal link interception ------------------------------------

	document.addEventListener( 'click', function ( e ) {
		if ( ! isDirty() ) {
			return;
		}
		// Find the nearest <a> ancestor of the click target.
		var a = e.target;
		while ( a && a !== document.body && a.tagName !== 'A' ) {
			a = a.parentElement;
		}
		if ( ! a || a.tagName !== 'A' ) {
			return;
		}
		if ( ! a.href ) {
			return;
		}
		// Same-page anchor — don't bother.
		var hrefBase  = a.href.split( '#' )[ 0 ];
		var hereBase  = window.location.href.split( '#' )[ 0 ];
		if ( hrefBase === hereBase && a.href.indexOf( '#' ) !== -1 ) {
			return;
		}
		// New tab / new window — doesn't unload current page.
		if ( a.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey ) {
			return;
		}
		// "javascript:" / "mailto:" / "tel:" — not navigation.
		var lowerHref = a.href.toLowerCase();
		if ( lowerHref.indexOf( 'javascript:' ) === 0
			|| lowerHref.indexOf( 'mailto:' ) === 0
			|| lowerHref.indexOf( 'tel:' ) === 0 ) {
			return;
		}

		e.preventDefault();
		openUnsavedModal( a.href );
	}, true ); // capture phase so we win against link-handlers further down.

	// ----- Custom modal --------------------------------------------------

	function openUnsavedModal( intendedUrl ) {
		// Avoid stacking modals if the user double-clicks.
		if ( document.getElementById( 'cbe-unsaved-modal' ) ) {
			return;
		}

		var overlay = document.createElement( 'div' );
		overlay.id = 'cbe-unsaved-modal';
		overlay.style.cssText = [
			'position:fixed', 'inset:0', 'background:rgba(0,0,0,0.55)',
			'z-index:160000', 'display:flex', 'align-items:center',
			'justify-content:center', 'padding:1rem'
		].join( ';' );

		var dialog = document.createElement( 'div' );
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', 'cbe-unsaved-title' );
		dialog.style.cssText = [
			'background:#fff', 'border-radius:4px', 'padding:1.25rem 1.5rem',
			'max-width:28rem', 'width:100%',
			'box-shadow:0 8px 30px rgba(0,0,0,0.25)',
			'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif'
		].join( ';' );

		var title = document.createElement( 'h2' );
		title.id = 'cbe-unsaved-title';
		title.style.cssText = 'margin:0 0 0.5rem;font-size:1.1em;';
		title.textContent = 'Unsaved changes';

		var msg = document.createElement( 'p' );
		msg.style.cssText = 'margin:0 0 1.25rem;color:#1d2327;';
		msg.textContent = 'You have unsaved changes on this page. Do you want to save them before leaving?';

		var btnRow = document.createElement( 'p' );
		btnRow.style.cssText = 'display:flex;gap:0.5rem;justify-content:flex-end;flex-wrap:wrap;margin:0;';

		function makeBtn( label, cls, onClick ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = cls;
			b.textContent = label;
			b.addEventListener( 'click', onClick );
			return b;
		}

		var cancelBtn   = makeBtn( 'Cancel', 'button', closeModal );
		var discardBtn  = makeBtn( "Don't save", 'button', function () {
			suppressGuard = true;
			window.location.href = intendedUrl;
		} );
		var saveBtn     = makeBtn( 'Save changes', 'button button-primary', function () {
			try {
				window.sessionStorage.setItem( 'cbe_after_save_redirect', intendedUrl );
			} catch ( err ) {
				// sessionStorage unavailable — fall back to "save here,
				// user has to click their link again." Better than no
				// save at all.
			}
			suppressGuard = true;
			closeModal();
			settingsForm.submit();
		} );

		btnRow.appendChild( cancelBtn );
		btnRow.appendChild( discardBtn );
		btnRow.appendChild( saveBtn );

		dialog.appendChild( title );
		dialog.appendChild( msg );
		dialog.appendChild( btnRow );
		overlay.appendChild( dialog );
		document.body.appendChild( overlay );

		// Backdrop click = cancel.
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closeModal();
			}
		} );

		// ESC = cancel. Tab is left to its natural order — focus stays
		// inside the dialog because the rest of the page is overlayed,
		// but it's not a true focus trap.
		var keyHandler = function ( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				closeModal();
			}
		};
		document.addEventListener( 'keydown', keyHandler );

		function closeModal() {
			document.removeEventListener( 'keydown', keyHandler );
			if ( overlay.parentNode ) {
				overlay.parentNode.removeChild( overlay );
			}
		}

		// Default focus on the primary action.
		saveBtn.focus();
	}
} )();

/**
 * Adds a copy-to-clipboard button to every core Code block.
 *
 * Prism handles syntax highlighting on its own (auto-runs on DOMContentLoaded),
 * so this file only builds the copy button. Reading code.textContent returns the
 * original source even after Prism wraps tokens in spans, so the copied text is
 * unaffected by highlighting.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const copyIcon =
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
	const checkIcon =
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="20 6 9 17 4 12"></polyline></svg>';

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for non-HTTPS / older browsers.
		return new Promise( function ( resolve, reject ) {
			const ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' ) ? resolve() : reject();
			} catch ( e ) {
				reject( e );
			} finally {
				document.body.removeChild( ta );
			}
		} );
	}

	document.querySelectorAll( 'pre.wp-block-code' ).forEach( function ( pre ) {
		const code = pre.querySelector( 'code' );
		if ( ! code ) {
			return;
		}

		// Wrap <pre> so the button is pinned to the wrapper, not the scroll area.
		const wrapper = document.createElement( 'div' );
		wrapper.className = 'code-block-wrapper';
		pre.parentNode.insertBefore( wrapper, pre );
		wrapper.appendChild( pre );

		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'code-copy-btn';
		button.setAttribute( 'aria-label', 'Copy code to clipboard' );
		button.innerHTML = copyIcon;

		const feedback = document.createElement( 'span' );
		feedback.className = 'code-copy-feedback';
		feedback.setAttribute( 'role', 'status' );
		feedback.setAttribute( 'aria-live', 'polite' );

		wrapper.appendChild( button );
		wrapper.appendChild( feedback );

		let timer;
		button.addEventListener( 'click', function () {
			copyText( code.textContent ).then( function () {
				button.innerHTML = checkIcon;
				feedback.textContent = 'Copied to clipboard';
				feedback.classList.add( 'is-visible' );
				clearTimeout( timer );
				timer = setTimeout( function () {
					button.innerHTML = copyIcon;
					feedback.classList.remove( 'is-visible' );
					feedback.textContent = '';
				}, 2000 );
			} ).catch( function () {
				feedback.textContent = 'Copy failed';
				feedback.classList.add( 'is-visible' );
				clearTimeout( timer );
				timer = setTimeout( function () {
					feedback.classList.remove( 'is-visible' );
					feedback.textContent = '';
				}, 2000 );
			} );
		} );
	} );
} );

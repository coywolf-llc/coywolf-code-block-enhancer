/**
 * Tools → Code Blocks live theme preview.
 *
 * Watches the theme <select> and swaps the preview pane's stylesheet on
 * change. Nothing is written to the database until the user clicks
 * Save Changes — this script only manipulates the admin DOM.
 *
 * Data is injected via wp_localize_script as window.cbeSettingsPreview:
 *   {
 *     baseUrl: string,                          // .../assets/themes/
 *     themes:  { [key]: { file, lock } }        // file: css filename;
 *                                               // lock: 'light' | 'dark' | null
 *   }
 *
 * Lock classes toggle on <body> rather than on a scoped wrapper because
 * coywolf-claude.css's override selectors are written as
 * `body.cbe-theme-light .wp-block-code`. Toggling body class on the
 * settings page is safe — the only `.wp-block-code` element here is our
 * preview, and the rest of WP admin doesn't react to these classes.
 */
( function () {
	'use strict';

	const select = document.getElementById( 'cbe_theme' );
	const data   = window.cbeSettingsPreview || {};
	const themes = data.themes || {};
	const baseUrl = data.baseUrl || '';

	if ( ! select || ! baseUrl ) {
		return;
	}

	// The server already enqueued the saved theme as <link id="cbe-preview-theme-css">.
	// Re-use it so the first dropdown change just rewrites href instead of stacking links.
	let linkEl = document.getElementById( 'cbe-preview-theme-css-css' )
		|| document.getElementById( 'cbe-preview-theme-css' );

	function setLockClass( lock ) {
		const body = document.body;
		body.classList.remove( 'cbe-theme-light', 'cbe-theme-dark' );
		if ( lock === 'light' ) {
			body.classList.add( 'cbe-theme-light' );
		} else if ( lock === 'dark' ) {
			body.classList.add( 'cbe-theme-dark' );
		}
	}

	function apply( key ) {
		const entry = themes[ key ];
		if ( ! entry || ! entry.file ) {
			return;
		}

		const href = baseUrl + entry.file;

		if ( ! linkEl ) {
			linkEl = document.createElement( 'link' );
			linkEl.id  = 'cbe-preview-theme-css';
			linkEl.rel = 'stylesheet';
			document.head.appendChild( linkEl );
		}
		if ( linkEl.getAttribute( 'href' ) !== href ) {
			linkEl.setAttribute( 'href', href );
		}

		setLockClass( entry.lock || null );
	}

	// Initial sync — make sure the body lock class matches the saved value
	// even before the user touches the dropdown (server-side admin_body_class
	// also sets it, but this guards against drift if the option moves).
	apply( select.value );

	select.addEventListener( 'change', function () {
		apply( select.value );
	} );
} )();

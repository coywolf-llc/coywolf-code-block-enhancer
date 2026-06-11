/**
 * Tools → Code Blocks live theme preview.
 *
 * Watches the theme <select> and swaps the preview pane's stylesheet on
 * change. Nothing is written to the database until the user clicks
 * Save Changes — this script only manipulates the admin DOM.
 *
 * Data is injected via wp_add_inline_script as
 * window.coywolfCbeSettingsPreview:
 *   {
 *     baseUrl: string,                          // .../assets/themes/
 *     themes:  { [key]: { file?, css?, lock } } // `css` = raw stylesheet
 *                                               //   text (the DB-stored
 *                                               //   custom theme; turned
 *                                               //   into a Blob URL here),
 *                                               //   else baseUrl + file.
 *                                               // lock: 'light'|'dark'|null
 *   }
 *
 * Lock classes toggle on <body> rather than on a scoped wrapper because
 * default.css's override selectors are written as
 * `body.cbe-theme-light .wp-block-code`. Toggling body class on the
 * settings page is safe — the only `.wp-block-code` element here is our
 * preview, and the rest of WP admin doesn't react to these classes.
 */
( function () {
	'use strict';

	const select  = document.getElementById( 'coywolf_cbe_theme' );
	const data    = window.coywolfCbeSettingsPreview || {};
	const themes  = data.themes || {};
	const baseUrl = data.baseUrl || '';

	if ( ! select ) {
		return;
	}

	// The server already enqueued the saved theme as
	// <link id="coywolf-cbe-preview-theme-css">. Re-use it so the first
	// dropdown change just rewrites href instead of stacking links.
	let linkEl = document.getElementById( 'coywolf-cbe-preview-theme-css-css' )
		|| document.getElementById( 'coywolf-cbe-preview-theme-css' );

	function setLockClass( lock ) {
		const body = document.body;
		body.classList.remove( 'cbe-theme-light', 'cbe-theme-dark' );
		if ( lock === 'light' ) {
			body.classList.add( 'cbe-theme-light' );
		} else if ( lock === 'dark' ) {
			body.classList.add( 'cbe-theme-dark' );
		}
	}

	function urlFor( entry ) {
		if ( ! entry ) {
			return '';
		}
		if ( typeof entry.css === 'string' ) {
			// The custom theme is stored in the database and shipped as
			// stylesheet text — wrap it in a Blob URL (once) so the same
			// <link>-swapping path and the download anchor work unchanged.
			if ( ! entry._blobUrl ) {
				entry._blobUrl = URL.createObjectURL(
					new Blob( [ entry.css ], { type: 'text/css' } )
				);
			}
			return entry._blobUrl;
		}
		if ( entry.file && baseUrl ) {
			return baseUrl + entry.file;
		}
		return '';
	}

	function updateDownloadLink( entry, href ) {
		const a = document.getElementById( 'cbe-preview-download' ); // DOM id only — not a WP-registered name.
		if ( ! a || ! entry ) {
			return;
		}
		const name = entry.download || entry.file || 'theme.css';
		a.setAttribute( 'href', href );
		a.setAttribute( 'download', name );
		// Re-render the "Download <code>name</code>" label using DOM
		// APIs so the filename can never be parsed as HTML — defence
		// in depth on top of the sanitize_file_name() pass the PHP
		// side already applies. The static PHP-rendered version uses
		// a localized format string; the dynamic re-render here
		// mirrors it in English (translators see the correct prefix
		// on first paint; a dropdown change re-paints in the source
		// language — acceptable for an admin-only preview).
		while ( a.firstChild ) {
			a.removeChild( a.firstChild );
		}
		a.appendChild( document.createTextNode( 'Download ' ) );
		const code = document.createElement( 'code' );
		code.textContent = name;
		a.appendChild( code );
	}

	function apply( key ) {
		const entry = themes[ key ];
		const href  = urlFor( entry );
		if ( ! href ) {
			return;
		}

		if ( ! linkEl ) {
			linkEl = document.createElement( 'link' );
			linkEl.id  = 'coywolf-cbe-preview-theme-css';
			linkEl.rel = 'stylesheet';
			document.head.appendChild( linkEl );
		}
		if ( linkEl.getAttribute( 'href' ) !== href ) {
			linkEl.setAttribute( 'href', href );
		}

		setLockClass( entry.lock || null );
		updateDownloadLink( entry, href );
	}

	apply( select.value );

	select.addEventListener( 'change', function () {
		apply( select.value );
	} );
} )();

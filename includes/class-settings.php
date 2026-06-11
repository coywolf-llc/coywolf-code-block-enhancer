<?php
/**
 * Settings page (Tools → Code Blocks) for Coywolf Code Block Enhancer.
 *
 * Stores one option, `coywolf_cbe_theme`, with one of the keys defined in
 * {@see self::themes()}. The chosen theme is applied on the front end by
 * enqueueing the matching stylesheet under assets/themes/; the
 * Default-palette variants (coywolf-auto / coywolf-light / coywolf-dark)
 * also add a `cbe-theme-light` / `cbe-theme-dark` body class so the
 * lock-class selectors in default.css beat its @media
 * (prefers-color-scheme) defaults.
 *
 * Custom-theme upload: a single CSS file can be uploaded; the sanitised
 * stylesheet text is stored in the `coywolf_cbe_custom_theme_css` option
 * (never written to disk — WordPress.org disallows user-supplied code
 * files in uploads) and printed via wp_add_inline_style() when the
 * `custom` theme key is active. Uploading again replaces it — there is
 * only ever one custom theme at a time.
 *
 * Migrations (each one-shot, run on admin_init):
 *   - legacy `cbe_theme_mode` (auto / light / dark) → "coywolf-{mode}".
 *   - pre-1.0.55 short-prefix options (`cbe_*`) → `coywolf_cbe_*`.
 *   - pre-1.0.55 uploads/code-block-enhancer/custom.css file → the
 *     CSS option (the file, its .htaccess hint, and the directory are
 *     removed from disk).
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Settings {

	const OPTION            = 'coywolf_cbe_theme';
	const LEGACY_OPTION     = 'cbe_theme_mode';
	const CUSTOM_OPTION     = 'coywolf_cbe_custom_theme';
	const CUSTOM_CSS_OPTION = 'coywolf_cbe_custom_theme_css';
	const PAGE              = 'coywolf-cbe-settings';
	const GROUP             = 'coywolf_cbe_settings';
	const CAP               = 'manage_options';
	const DEFAULT_THEME     = 'coywolf-auto';
	const CUSTOM_KEY        = 'custom';
	const CUSTOM_MAX_BYTES  = 262144; // 256 KB.

	// Legacy on-disk locations (pre-1.0.55, when the custom theme was a
	// real file under uploads). Only referenced by the one-shot migration
	// and uninstall cleanup.
	const LEGACY_DIRNAME = 'code-block-enhancer';
	const LEGACY_FILE    = 'custom.css';

	// One-shot flag set after the prefix/file migration below has run.
	const MIGRATED_FLAG = 'coywolf_cbe_prefix_migrated_v1';

	/**
	 * Pre-1.0.55 option names (short `cbe_` prefix — below the 4-character
	 * minimum WordPress.org requires) → their current names. Consumed by
	 * {@see self::maybe_migrate_option_prefixes()}.
	 */
	const PREFIX_RENAMES = array(
		'cbe_theme'               => self::OPTION,
		'cbe_custom_theme'        => self::CUSTOM_OPTION,
		'cbe_languages'           => 'coywolf_cbe_languages',
		'cbe_baseline_merged_v1'  => 'coywolf_cbe_baseline_merged_v1',
	);

	/**
	 * Full theme registry. Each entry:
	 *   key     → option value + dropdown <option> value
	 *   label   → human-readable name shown in the dropdown
	 *   file    → relative path under assets/themes/, OR null when the
	 *             entry is inline (the custom upload, stored in the DB)
	 *   inline  → true when the theme has no stylesheet URL and is printed
	 *             via wp_add_inline_style() from the CSS option instead
	 *   group   → optgroup label
	 *   lock    → 'light' | 'dark' | null — body class added when active
	 *
	 * The `custom` entry only appears when a custom CSS has been uploaded
	 * — see {@see self::custom_theme_meta()}.
	 *
	 * @return array<string,array>
	 */
	public static function themes() {
		$coywolf = array(
			'coywolf-auto'  => array(
				'label'    => __( 'Default — Auto (follow OS dark mode)', 'coywolf-code-block-enhancer' ),
				'file'     => 'default.css',
				'download' => 'default.css',
				'group'    => __( 'Coywolf', 'coywolf-code-block-enhancer' ),
				'lock'     => null,
			),
			'coywolf-light' => array(
				'label'    => __( 'Default — Always light', 'coywolf-code-block-enhancer' ),
				'file'     => 'default.css',
				'download' => 'default.css',
				'group'    => __( 'Coywolf', 'coywolf-code-block-enhancer' ),
				'lock'     => 'light',
			),
			'coywolf-dark'  => array(
				'label'    => __( 'Default — Always dark', 'coywolf-code-block-enhancer' ),
				'file'     => 'default.css',
				'download' => 'default.css',
				'group'    => __( 'Coywolf', 'coywolf-code-block-enhancer' ),
				'lock'     => 'dark',
			),
		);

		// Custom theme entry — only present if an upload has been stored.
		// Display label prefers the user-provided `name` then falls back to
		// the original filename.
		$custom = array();
		$meta   = self::custom_theme_meta();
		if ( null !== $meta ) {
			$display_name = ! empty( $meta['name'] ) ? $meta['name'] : $meta['original_name'];
			$custom[ self::CUSTOM_KEY ] = array(
				'label'    => sprintf(
					/* translators: %s is the user-provided theme name (or the uploaded filename). */
					__( 'Custom — %s', 'coywolf-code-block-enhancer' ),
					$display_name
				),
				'file'     => null,
				'inline'   => true,
				'download' => $meta['original_name'],
				'group'    => __( 'Custom', 'coywolf-code-block-enhancer' ),
				'lock'     => null,
			);
		}

		// 8 built-in Prism themes (PrismJS/prism v1.30.0, MIT — see
		// assets/themes/LICENSE-prism). Minified files.
		$builtin_group = __( 'Prism (built-in)', 'coywolf-code-block-enhancer' );
		$builtin       = array(
			'prism'              => 'Prism Default',
			'prism-coy'          => 'Coy',
			'prism-dark'         => 'Dark',
			'prism-funky'        => 'Funky',
			'prism-okaidia'      => 'Okaidia',
			'prism-solarizedlight' => 'Solarized Light',
			'prism-tomorrow'     => 'Tomorrow Night',
			'prism-twilight'     => 'Twilight',
		);
		$builtin_themes = array();
		foreach ( $builtin as $key => $label ) {
			$file = $key . '.min.css';
			$builtin_themes[ $key ] = array(
				'label'    => $label,
				'file'     => $file,
				'download' => $file,
				'group'    => $builtin_group,
				'lock'     => null,
			);
		}

		// 37 community themes (PrismJS/prism-themes, MIT — see
		// assets/themes/LICENSE-prism-themes). Original .css files.
		$community_group = __( 'Prism Themes (community)', 'coywolf-code-block-enhancer' );
		$community       = array(
			'prism-a11y-dark'                       => 'a11y Dark',
			'prism-atom-dark'                       => 'Atom Dark',
			'prism-base16-ateliersulphurpool.light' => 'Base16 Ateliersulphurpool (Light)',
			'prism-cb'                              => 'CB',
			'prism-coldark-cold'                    => 'Coldark Cold',
			'prism-coldark-dark'                    => 'Coldark Dark',
			'prism-coy-without-shadows'             => 'Coy Without Shadows',
			'prism-darcula'                         => 'Darcula',
			'prism-dracula'                         => 'Dracula',
			'prism-duotone-dark'                    => 'Duotone Dark',
			'prism-duotone-earth'                   => 'Duotone Earth',
			'prism-duotone-forest'                  => 'Duotone Forest',
			'prism-duotone-light'                   => 'Duotone Light',
			'prism-duotone-sea'                     => 'Duotone Sea',
			'prism-duotone-space'                   => 'Duotone Space',
			'prism-ghcolors'                        => 'GH Colors',
			'prism-gruvbox-dark'                    => 'Gruvbox Dark',
			'prism-gruvbox-light'                   => 'Gruvbox Light',
			'prism-holi-theme'                      => 'Holi',
			'prism-hopscotch'                       => 'Hopscotch',
			'prism-laserwave'                       => 'Laserwave',
			'prism-lucario'                         => 'Lucario',
			'prism-material-dark'                   => 'Material Dark',
			'prism-material-light'                  => 'Material Light',
			'prism-material-oceanic'                => 'Material Oceanic',
			'prism-night-owl'                       => 'Night Owl',
			'prism-nord'                            => 'Nord',
			'prism-one-dark'                        => 'One Dark',
			'prism-one-light'                       => 'One Light',
			'prism-pojoaque'                        => 'Pojoaque',
			'prism-shades-of-purple'                => 'Shades of Purple',
			'prism-solarized-dark-atom'             => 'Solarized Dark (Atom)',
			'prism-synthwave84'                     => 'Synthwave \'84',
			'prism-vs'                              => 'Visual Studio',
			'prism-vsc-dark-plus'                   => 'VS Code Dark+',
			'prism-xonokai'                         => 'Xonokai',
			'prism-z-touch'                         => 'Z-Touch',
		);
		$community_themes = array();
		foreach ( $community as $key => $label ) {
			$file = $key . '.css';
			$community_themes[ $key ] = array(
				'label'    => $label,
				'file'     => $file,
				'download' => $file,
				'group'    => $community_group,
				'lock'     => null,
			);
		}

		return array_merge( $coywolf, $custom, $builtin_themes, $community_themes );
	}

	/**
	 * Build the public URL for any theme entry.
	 *
	 * @param array $entry One entry from {@see self::themes()}.
	 * @return string Absolute URL to the theme stylesheet, or '' for an
	 *                inline entry (the custom theme has no URL — its CSS
	 *                is printed from the database).
	 */
	public static function theme_url( array $entry ) {
		if ( ! empty( $entry['inline'] ) || empty( $entry['file'] ) ) {
			return '';
		}
		return COYWOLF_CBE_URL . 'assets/themes/' . $entry['file'];
	}

	public function init() {
		// Prefix migration must run before anything that reads the renamed
		// options (same hook, registered first → runs first).
		add_action( 'admin_init',            array( __CLASS__, 'maybe_migrate_option_prefixes' ) );
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_setting' ) );
		add_action( 'admin_init',            array( $this, 'maybe_migrate_legacy_option' ) );
		add_action( 'admin_init',            array( 'Coywolf_CBE_Language_Packs', 'maybe_migrate_legacy_packs' ) );
		add_action( 'admin_init',            array( 'Coywolf_CBE_Language_Packs', 'maybe_merge_baseline_into_languages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_coywolf_cbe_upload_custom_theme', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_coywolf_cbe_remove_custom_theme', array( $this, 'handle_remove' ) );
		add_filter( 'admin_body_class',      array( $this, 'admin_body_class_for_lock' ) );
		add_filter( 'body_class',            array( $this, 'maybe_add_lock_class' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( COYWOLF_CBE_PLUGIN_FILE ),
			array( $this, 'add_settings_action_link' )
		);
	}

	private function page_hook_suffix() {
		return 'tools_page_' . self::PAGE;
	}

	// ----- Custom-theme storage helpers ------------------------------------

	/**
	 * Absolute filesystem path to the legacy (pre-1.0.55) custom-theme
	 * directory under uploads. Migration / cleanup only.
	 */
	private static function legacy_dir() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . self::LEGACY_DIRNAME . '/';
	}

	/**
	 * Metadata for the uploaded custom theme, or null if no upload exists.
	 *
	 * @return array{original_name:string, name:string, uploaded_at:int, byte_size:int}|null
	 */
	public static function custom_theme_meta() {
		// Memoize per request: this runs on wp_enqueue_scripts and the body_class
		// filter (and again via themes()), so without caching the get_option
		// repeats several times on every front-end page. The custom theme can
		// only change via an admin POST that redirects, so the value is stable
		// within a single request. ( false = not yet computed. )
		static $cache = false;
		if ( false !== $cache ) {
			return $cache;
		}
		$cache = null;
		$meta  = get_option( self::CUSTOM_OPTION, null );
		if ( ! is_array( $meta ) || empty( $meta['original_name'] ) ) {
			return $cache;
		}
		$cache = array(
			'original_name' => (string) $meta['original_name'],
			'name'          => isset( $meta['name'] ) ? (string) $meta['name'] : '',
			'uploaded_at'   => isset( $meta['uploaded_at'] ) ? (int) $meta['uploaded_at'] : 0,
			'byte_size'     => isset( $meta['byte_size'] ) ? (int) $meta['byte_size'] : 0,
		);
		return $cache;
	}

	/**
	 * The stored custom-theme stylesheet text ('' if none). Deliberately
	 * NOT folded into custom_theme_meta(): the CSS can be up to 256 KB and
	 * the option is saved with autoload off, so it should only be loaded
	 * on requests that actually print it.
	 */
	public static function custom_theme_css() {
		$css = get_option( self::CUSTOM_CSS_OPTION, '' );
		return is_string( $css ) ? $css : '';
	}

	/**
	 * One-shot migrations for installs upgrading from ≤1.0.54:
	 *
	 *   1. Rename the short-prefix `cbe_*` options to `coywolf_cbe_*`
	 *      (WordPress.org requires prefixes of at least 4 characters).
	 *   2. Import the on-disk custom theme (uploads/code-block-enhancer/
	 *      custom.css) into the CSS option, then delete the file, the
	 *      .htaccess MIME hint beside it, and the directory. WordPress.org
	 *      disallows both user-supplied code files and server-config files
	 *      in uploads, so nothing is ever written back to disk.
	 *
	 * Idempotent: every step only acts when the legacy artefact exists,
	 * and the whole pass is skipped once the one-shot flag is set (so
	 * steady-state admin requests pay one autoloaded option read, not a
	 * batch of filesystem stats).
	 */
	public static function maybe_migrate_option_prefixes() {
		if ( get_option( self::MIGRATED_FLAG ) ) {
			return;
		}

		$sentinel = '__coywolf_cbe_unset__';

		foreach ( self::PREFIX_RENAMES as $old => $new ) {
			$value = get_option( $old, $sentinel );
			if ( $sentinel === $value ) {
				continue;
			}
			if ( get_option( $new, $sentinel ) === $sentinel ) {
				update_option( $new, $value );
			}
			delete_option( $old );
		}

		// Import the legacy custom-theme file into the database.
		$dir  = self::legacy_dir();
		$path = $dir . self::LEGACY_FILE;
		if ( file_exists( $path ) ) {
			if ( '' === self::custom_theme_css() && null !== self::custom_theme_meta() ) {
				$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading our own legacy upload during a one-shot migration.
				if ( is_string( $raw ) ) {
					$clean = self::sanitise_css( $raw );
					if ( null !== $clean ) {
						update_option( self::CUSTOM_CSS_OPTION, $clean, false );
					}
				}
			}
			wp_delete_file( $path );
		}
		$htaccess = $dir . '.htaccess';
		if ( file_exists( $htaccess ) ) {
			wp_delete_file( $htaccess );
		}
		if ( is_dir( $dir ) ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			global $wp_filesystem;
			if ( WP_Filesystem() ) {
				$wp_filesystem->rmdir( $dir ); // No-op if the dir still has other contents.
			}
		}

		// A meta entry without stored CSS (e.g. the legacy file was deleted
		// by hand before this migration ran) is dead — drop it so the
		// dropdown doesn't offer a theme that renders nothing.
		if ( null !== self::custom_theme_meta() && '' === self::custom_theme_css() ) {
			delete_option( self::CUSTOM_OPTION );
			if ( self::CUSTOM_KEY === get_option( self::OPTION, self::DEFAULT_THEME ) ) {
				update_option( self::OPTION, self::DEFAULT_THEME );
			}
		}

		update_option( self::MIGRATED_FLAG, 1 );
	}

	/**
	 * CSS sanitiser. Returns the cleaned-up content, or null if the file
	 * contains constructs we refuse to store under any circumstances
	 * (script tags, PHP open tags, javascript:/vbscript: URIs,
	 * data:text/html, IE expression(), CSS behavior:).
	 *
	 * This is defence in depth — a CSS file served with the correct
	 * Content-Type does not execute JS — but blocking obvious injection
	 * vectors keeps us safe if the file is ever served with a different
	 * MIME type or imported into an unexpected context.
	 *
	 * @param string $raw Raw bytes from the upload.
	 * @return string|null Sanitised CSS, or null if it must be rejected.
	 */
	public static function sanitise_css( $raw ) {
		// Strip UTF-8 BOM.
		if ( 0 === strncmp( $raw, "\xef\xbb\xbf", 3 ) ) {
			$raw = substr( $raw, 3 );
		}
		// Normalise line endings.
		$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

		// Reject if any of these dangerous tokens appear anywhere. Besides the
		// classic injection vectors, we block @import and remote / protocol-
		// relative url() so an uploaded stylesheet cannot pull from or beacon to
		// a third-party origin (privacy / SSRF) on the front end. Relative and
		// data:image url()s stay allowed.
		$forbidden = array(
			'/<\s*script\b/i',
			'/<\s*\/\s*script\s*>/i',
			'/<\s*\?\s*php\b/i',
			'/<\s*\?\s*=/',
			'/<\s*\?\s*[\r\n\s]/',
			'/javascript\s*:/i',
			'/vbscript\s*:/i',
			'/data\s*:\s*text\/html/i',
			'/expression\s*\(/i',
			'/behavior\s*:/i',
			'/-moz-binding\s*:/i',
			'/@import\b/i',
			'/url\s*\(\s*[\'"]?\s*(?:https?:)?\/\//i',
		);
		foreach ( $forbidden as $pattern ) {
			if ( preg_match( $pattern, $raw ) ) {
				return null;
			}
		}

		return $raw;
	}

	// ----- Upload / remove handlers ----------------------------------------

	/**
	 * Process a custom-theme upload submitted from the settings page.
	 *
	 * Capability + nonce + extension + MIME + size + content sanitiser
	 * checks must all pass before the file is written. Any failure
	 * redirects back with a query arg consumed by {@see self::render_notices()}.
	 */
	public function handle_upload() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to upload themes.', 'coywolf-code-block-enhancer' ) );
		}
		check_admin_referer( 'coywolf_cbe_upload_custom_theme' );

		$back = admin_url( 'tools.php?page=' . self::PAGE );

		if ( empty( $_FILES['coywolf_cbe_custom_theme'] ) || ! is_array( $_FILES['coywolf_cbe_custom_theme'] ) ) {
			$this->redirect_with( $back, 'missing' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload array; nonce + manage_options verified above, then validated field-by-field (error/size/extension/MIME/content) below. $_FILES is not a sanitizable scalar.
		$f = $_FILES['coywolf_cbe_custom_theme'];

		if ( ! isset( $f['error'] ) || UPLOAD_ERR_OK !== (int) $f['error'] ) {
			$this->redirect_with( $back, 'error' );
		}
		if ( ! isset( $f['size'] ) || (int) $f['size'] <= 0 ) {
			$this->redirect_with( $back, 'error' );
		}
		if ( (int) $f['size'] > self::CUSTOM_MAX_BYTES ) {
			$this->redirect_with( $back, 'too_large' );
		}

		$name = isset( $f['name'] ) ? (string) $f['name'] : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'css' !== $ext ) {
			$this->redirect_with( $back, 'not_css' );
		}

		// Confirm the tmp path is actually from a PHP upload (defence
		// against a caller passing an arbitrary local path as tmp_name).
		// We deliberately do NOT call wp_check_filetype_and_ext() here:
		// it runs finfo_file() on the upload, and CSS has no magic-byte
		// signature, so legitimate CSS files come back as `text/plain`
		// and get rejected even though they're valid. The extension
		// check above (.css required) and the content sanitiser below
		// (rejects script tags / PHP / javascript: / etc.) cover the
		// same risk surface without the false-positive — and since the
		// CSS is stored in the database and printed inline (never served
		// as a file), there is no MIME-type concern at all.
		$tmp = isset( $f['tmp_name'] ) ? (string) $f['tmp_name'] : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$this->redirect_with( $back, 'error' );
		}

		$raw = file_get_contents( $tmp );
		if ( false === $raw ) {
			$this->redirect_with( $back, 'read_error' );
		}

		$clean = self::sanitise_css( $raw );
		if ( null === $clean ) {
			$this->redirect_with( $back, 'unsafe' );
		}

		// Store the stylesheet text in the database (autoload off — up to
		// 256 KB, only needed on requests that print it). Nothing is ever
		// written to the filesystem: WordPress.org disallows user-supplied
		// code files in uploads, and the plugin folder is wiped on update.
		update_option( self::CUSTOM_CSS_OPTION, $clean, false );

		// Optional user-provided display name. Sanitised hard (text only,
		// 60-char cap) since this string is rendered as the dropdown label
		// — esc_html on the way out, but we also keep the stored value tidy.
		$display = isset( $_POST['coywolf_cbe_custom_theme_name'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['coywolf_cbe_custom_theme_name'] ) )
			: '';
		if ( strlen( $display ) > 60 ) {
			$display = substr( $display, 0, 60 );
		}

		update_option(
			self::CUSTOM_OPTION,
			array(
				'original_name' => sanitize_file_name( $name ),
				'name'          => $display,
				'uploaded_at'   => time(),
				'byte_size'     => strlen( $clean ),
			)
		);

		// Auto-activate the custom theme on a fresh upload so the user
		// doesn't have to click Save Changes a second time just to see it
		// take effect. Re-uploading while `custom` is already the active
		// theme is a no-op for the option.
		update_option( self::OPTION, self::CUSTOM_KEY );

		$this->redirect_with( $back, 'ok' );
	}

	/**
	 * Delete the stored custom theme. If `coywolf_cbe_theme` was set to
	 * `custom`, fall back to the default so the front end doesn't try to
	 * print a theme that no longer exists.
	 */
	public function handle_remove() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to remove the custom theme.', 'coywolf-code-block-enhancer' ) );
		}
		check_admin_referer( 'coywolf_cbe_remove_custom_theme' );

		delete_option( self::CUSTOM_CSS_OPTION );
		delete_option( self::CUSTOM_OPTION );

		if ( self::CUSTOM_KEY === get_option( self::OPTION, self::DEFAULT_THEME ) ) {
			update_option( self::OPTION, self::DEFAULT_THEME );
		}

		$this->redirect_with( admin_url( 'tools.php?page=' . self::PAGE ), 'removed' );
	}

	/**
	 * Redirect helper. Always exits.
	 */
	private function redirect_with( $url, $status ) {
		wp_safe_redirect( add_query_arg( 'coywolf_cbe_upload', $status, $url ) );
		exit;
	}

	// ----- Enqueue (admin preview) -----------------------------------------

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->page_hook_suffix() ) {
			return;
		}

		$prism_url = COYWOLF_CBE_URL . 'assets/prism/';

		$chain = array(
			'coywolf-cbe-prism'                   => 'prism-core.min.js',
			'coywolf-cbe-prism-markup'            => 'prism-markup.min.js',
			'coywolf-cbe-prism-markup-templating' => 'prism-markup-templating.min.js',
			'coywolf-cbe-prism-clike'             => 'prism-clike.min.js',
			'coywolf-cbe-prism-css'               => 'prism-css.min.js',
			'coywolf-cbe-prism-javascript'        => 'prism-javascript.min.js',
			'coywolf-cbe-prism-bash'              => 'prism-bash.min.js',
			'coywolf-cbe-prism-json'              => 'prism-json.min.js',
			'coywolf-cbe-prism-php'               => 'prism-php.min.js',
			'coywolf-cbe-prism-python'            => 'prism-python.min.js',
			'coywolf-cbe-prism-sql'               => 'prism-sql.min.js',
			'coywolf-cbe-prism-yaml'              => 'prism-yaml.min.js',
		);
		$prev = array();
		foreach ( $chain as $handle => $file ) {
			wp_enqueue_script( $handle, $prism_url . $file, $prev, '1.30.0', true );
			$prev = array( $handle );
		}

		wp_enqueue_script(
			'coywolf-cbe-code-blocks',
			COYWOLF_CBE_URL . 'js/code-blocks.js',
			array( array_key_last( $chain ) ),
			COYWOLF_CBE_VERSION,
			true
		);

		wp_enqueue_style( 'coywolf-cbe-style', COYWOLF_CBE_URL . 'css/code-block.css', array(), COYWOLF_CBE_VERSION );

		// Preview stylesheet <link>. An inline (custom) theme has no URL —
		// start from the default theme's file and let settings-preview.js
		// swap the href to a Blob URL built from the stored CSS on init.
		$current_entry = self::current_theme_entry();
		$preview_url   = self::theme_url( $current_entry );
		if ( '' === $preview_url ) {
			$themes_all  = self::themes();
			$preview_url = self::theme_url( $themes_all[ self::DEFAULT_THEME ] );
		}
		wp_enqueue_style(
			'coywolf-cbe-preview-theme-css',
			$preview_url,
			array( 'coywolf-cbe-style' ),
			COYWOLF_CBE_VERSION
		);

		// Admin-only preview tweaks (scoped to .cbe-preview so the
		// front-end layout is untouched):
		//
		//   1. Turn the absolutely-positioned language label into a
		//      block-level caption above the code so `php` and the first
		//      code line line up at the left edge with no big gap.
		//   2. Give the preview pre real left/right/bottom padding (front-
		//      end themes usually do this; admin doesn't).
		//   3. Force the inner <code> element to transparent + block with
		//      no padding/margin. WP admin's `code { background:
		//      rgba(0,0,0,.07); padding: 3px 5px; margin: 0 1px }` would
		//      otherwise paint a striped grey panel behind each text run
		//      inside the otherwise-white Default theme background.
		//      `!important` is the simplest way to beat the admin rule
		//      regardless of cascade order or any future `.wrap code`-
		//      style selectors WP admin might add.
		wp_add_inline_style(
			'coywolf-cbe-style',
			'.cbe-preview .wp-block-code[data-language]{padding:.75rem 1rem 1rem}'
			. '.cbe-preview .wp-block-code[data-language]::before{position:static;display:block;top:auto;left:auto;margin:0 0 .5rem 0;font-family:inherit}'
			. '.cbe-preview .wp-block-code code{background:transparent!important;padding:0!important;margin:0!important;display:block}'
		);

		wp_enqueue_script(
			'coywolf-cbe-settings-preview',
			COYWOLF_CBE_URL . 'js/settings-preview.js',
			array(),
			COYWOLF_CBE_VERSION,
			true
		);

		// Unsaved-changes guard. Loads in the footer like the rest of
		// our admin JS; the redirect-after-save bootstrap at the top
		// of the file runs immediately on parse so the post-save jump
		// is fast.
		wp_enqueue_script(
			'coywolf-cbe-settings-unsaved',
			COYWOLF_CBE_URL . 'js/settings-unsaved.js',
			array(),
			COYWOLF_CBE_VERSION,
			true
		);
		$payload = array(
			'baseUrl' => esc_url_raw( COYWOLF_CBE_URL . 'assets/themes/' ),
			'themes'  => array(),
		);
		foreach ( self::themes() as $key => $info ) {
			$entry = array( 'lock' => $info['lock'] );
			if ( ! empty( $info['inline'] ) ) {
				// The custom theme lives in the database, not at a URL.
				// Ship the stylesheet text itself; settings-preview.js
				// turns it into a Blob URL for the preview <link> and the
				// download anchor. Admin-only payload, capped at 256 KB.
				// wp_json_encode() escapes `/`, so a literal `</script>`
				// can never appear inside the inline JSON (the sanitiser
				// already rejects script tags anyway).
				$entry['css'] = self::custom_theme_css();
			} else {
				$entry['file'] = $info['file'];
			}
			// Suggested filename for the download link in the preview pane.
			$entry['download'] = ! empty( $info['download'] )
				? $info['download']
				: ( ! empty( $info['file'] ) ? $info['file'] : 'theme.css' );
			$payload['themes'][ $key ] = $entry;
		}
		wp_add_inline_script(
			'coywolf-cbe-settings-preview',
			'window.coywolfCbeSettingsPreview = ' . wp_json_encode( $payload ) . ';',
			'before'
		);

		// Per-pack "select all / clear" helpers for the Languages section.
		wp_enqueue_script(
			'coywolf-cbe-settings-packs',
			COYWOLF_CBE_URL . 'js/settings-packs.js',
			array(),
			COYWOLF_CBE_VERSION,
			true
		);
	}

	public function admin_body_class_for_lock( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== $this->page_hook_suffix() ) {
			return $classes;
		}
		$entry = self::current_theme_entry();
		if ( 'light' === $entry['lock'] ) {
			$classes .= ' cbe-theme-light';
		} elseif ( 'dark' === $entry['lock'] ) {
			$classes .= ' cbe-theme-dark';
		}
		return $classes;
	}

	// ----- Settings API ----------------------------------------------------

	public function register_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Code Block Enhancer', 'coywolf-code-block-enhancer' ),
			__( 'Code Blocks', 'coywolf-code-block-enhancer' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_theme' ),
				'default'           => self::DEFAULT_THEME,
				'show_in_rest'      => false,
			)
		);

		// Languages: flat array of enabled Prism handle strings. Default =
		// the web_app pack's languages.
		register_setting(
			self::GROUP,
			Coywolf_CBE_Language_Packs::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Coywolf_CBE_Language_Packs', 'sanitize_languages' ),
				'default'           => Coywolf_CBE_Language_Packs::default_languages(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'coywolf_cbe_appearance',
			__( 'Appearance', 'coywolf-code-block-enhancer' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			self::OPTION,
			__( 'Code block theme', 'coywolf-code-block-enhancer' ),
			array( $this, 'render_theme_field' ),
			self::PAGE,
			'coywolf_cbe_appearance',
			array( 'label_for' => self::OPTION )
		);

		// Custom-theme upload lives inside Appearance as a peer row.
		// Its `render_custom_theme_field()` emits its own <form>
		// elements that post to admin-post.php (file upload + remove),
		// so they sit visually inside the Appearance form-table row
		// but aren't part of the Settings API form's submission.
		add_settings_field(
			'coywolf_cbe_custom_theme_ui',
			__( 'Custom theme', 'coywolf-code-block-enhancer' ),
			array( $this, 'render_custom_theme_field' ),
			self::PAGE,
			'coywolf_cbe_appearance'
		);

		// Languages comes after Appearance.
		add_settings_section(
			'coywolf_cbe_languages',
			__( 'Languages', 'coywolf-code-block-enhancer' ),
			array( $this, 'render_languages_section_intro' ),
			self::PAGE
		);

		add_settings_field(
			Coywolf_CBE_Language_Packs::OPTION,
			__( 'Language packs', 'coywolf-code-block-enhancer' ),
			array( $this, 'render_language_packs_field' ),
			self::PAGE,
			'coywolf_cbe_languages'
		);
	}

	public function render_languages_section_intro() {
		echo '<p>' . esc_html__(
			'Tick the individual languages you want to appear in the Code block sidebar dropdown. The first 9 entries in Web / App dev (Bash, CSS, HTML/Markup, JavaScript, JSON, PHP, Python, SQL, YAML) are checked by default on a fresh install; everything else is optional. Visitors only download the grammar file for the language a code block actually uses on the page, plus any Prism dependencies that grammar needs (typically 1–6 KB each).',
			'coywolf-code-block-enhancer'
		) . '</p>';
	}

	public function render_language_packs_field() {
		$enabled = Coywolf_CBE_Language_Packs::enabled_languages();
		$packs   = Coywolf_CBE_Language_Packs::all_packs();
		$name    = Coywolf_CBE_Language_Packs::OPTION;

		// Empty hidden so unchecking every box still submits an empty
		// array rather than dropping the key entirely (and falling back
		// to the registered default on the next request). `form` attr
		// binds this and every checkbox below to the Settings API form
		// at the top of the page (same reason as the theme <select>).
		printf( '<input type="hidden" name="%s[]" value="" form="cbe-theme-form" />', esc_attr( $name ) );

		echo '<div class="cbe-lang-packs" style="max-width:48rem;">';
		foreach ( $packs as $key => $pack ) {
			$pack_handles = array_keys( $pack['langs'] );
			$on_count     = count( array_intersect( $pack_handles, $enabled ) );
			$total        = count( $pack_handles );
			// Auto-expand any pack that has at least one language enabled,
			// so the admin can see at a glance which entries are checked.
			$open_attr    = $on_count > 0 ? ' open' : '';
			?>
			<details<?php echo esc_attr( $open_attr ); ?> class="cbe-lang-pack" style="margin:0.5rem 0;border:1px solid #c3c4c7;border-radius:4px;padding:0.5rem 0.85rem;background:#fff;">
				<summary style="cursor:pointer;padding:0.25rem 0;list-style:revert;">
					<strong><?php echo esc_html( $pack['label'] ); ?></strong>
					<span style="color:#646970;margin-left:0.5rem;font-weight:normal;">
						(<?php
						printf(
							/* translators: 1: number enabled, 2: total in pack */
							esc_html__( '%1$d of %2$d enabled', 'coywolf-code-block-enhancer' ),
							(int) $on_count,
							(int) $total
						);
						?>)
					</span>
				</summary>
				<p style="color:#646970;margin:0.5rem 0;">
					<?php echo esc_html( $pack['description'] ); ?>
				</p>
				<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0.25rem 1rem;margin:0.5rem 0 0.25rem;">
					<?php foreach ( $pack['langs'] as $handle => $info ) :
						$checked = in_array( $handle, $enabled, true );
						?>
						<label style="display:block;">
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $handle ); ?>" form="cbe-theme-form" <?php checked( $checked, true ); ?> />
							<?php echo esc_html( $info['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p style="margin:0.5rem 0 0;font-size:0.85em;">
					<a href="#" class="cbe-pack-toggle" data-cbe-pack-action="all"><?php esc_html_e( 'Select all in pack', 'coywolf-code-block-enhancer' ); ?></a>
					&nbsp;|&nbsp;
					<a href="#" class="cbe-pack-toggle" data-cbe-pack-action="none"><?php esc_html_e( 'Clear pack', 'coywolf-code-block-enhancer' ); ?></a>
				</p>
			</details>
			<?php
		}
		echo '</div>';

		// The per-pack "select all / clear" behaviour lives in
		// js/settings-packs.js, enqueued by enqueue_admin_assets() — raw
		// <script> output isn't allowed on WordPress.org.

		echo '<p class="description">' . esc_html__(
			'Tick the individual languages you want to appear in the Code block sidebar dropdown. Only ticked grammars are downloaded by visitors, and even then only when a page actually contains a code block in that language (the front-end loader fetches one grammar file per language used on the page, plus any Prism dependencies).',
			'coywolf-code-block-enhancer'
		) . '</p>';
	}

	public function maybe_migrate_legacy_option() {
		$legacy = get_option( self::LEGACY_OPTION, null );
		if ( null === $legacy ) {
			return;
		}
		if ( false === get_option( self::OPTION, false ) ) {
			$map = array(
				'auto'  => 'coywolf-auto',
				'light' => 'coywolf-light',
				'dark'  => 'coywolf-dark',
			);
			$new = isset( $map[ $legacy ] ) ? $map[ $legacy ] : self::DEFAULT_THEME;
			update_option( self::OPTION, $new );
		}
		delete_option( self::LEGACY_OPTION );
	}

	public static function sanitize_theme( $value ) {
		$value  = is_string( $value ) ? $value : '';
		$themes = self::themes();
		return array_key_exists( $value, $themes ) ? $value : self::DEFAULT_THEME;
	}

	public static function current_theme() {
		$stored = get_option( self::OPTION, self::DEFAULT_THEME );
		$themes = self::themes();
		return array_key_exists( $stored, $themes ) ? $stored : self::DEFAULT_THEME;
	}

	public static function current_theme_entry() {
		$themes = self::themes();
		$key    = self::current_theme();
		return $themes[ $key ];
	}

	public function maybe_add_lock_class( $classes ) {
		$entry = self::current_theme_entry();
		if ( 'light' === $entry['lock'] ) {
			$classes[] = 'cbe-theme-light';
		} elseif ( 'dark' === $entry['lock'] ) {
			$classes[] = 'cbe-theme-dark';
		}
		return $classes;
	}

	public function add_settings_action_link( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}
		$url  = admin_url( 'tools.php?page=' . self::PAGE );
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Settings', 'coywolf-code-block-enhancer' )
		);
		array_unshift( $links, $link );
		return $links;
	}

	// ----- Rendering -------------------------------------------------------

	public function render_theme_field() {
		$current = self::current_theme();
		$themes  = self::themes();

		$by_group = array();
		foreach ( $themes as $key => $info ) {
			$by_group[ $info['group'] ][ $key ] = $info['label'];
		}

		// `form` attribute binds this input to the Settings API form at
		// the top of the page; the input itself lives outside the form
		// so the Custom theme upload's own forms can sit inline as
		// siblings without illegal HTML nesting.
		printf( '<select id="%1$s" name="%1$s" form="cbe-theme-form">', esc_attr( self::OPTION ) );
		foreach ( $by_group as $group => $options ) {
			printf( '<optgroup label="%s">', esc_attr( $group ) );
			foreach ( $options as $value => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $value ),
					selected( $current, $value, false ),
					esc_html( $label )
				);
			}
			echo '</optgroup>';
		}
		echo '</select>';

		echo '<p class="description">' . esc_html__(
			'The "Default — Auto" palette follows each visitor\'s OS dark-mode preference and is selected on first install. The two "Always" variants lock it to one appearance. The Prism themes below are static — they always render in their designed light or dark colours.',
			'coywolf-code-block-enhancer'
		) . ' ';
		echo '<strong>' . esc_html__( 'Changing the dropdown only updates the preview below — your site keeps the saved theme until you click Save Changes.', 'coywolf-code-block-enhancer' ) . '</strong>';
		echo '</p>';

		$sample = "<?php\n"
			. "// Send a thank-you email when a new comment is approved.\n"
			. "add_action( 'comment_post', function ( int \$id, \$approved ) {\n"
			. "    if ( 1 !== \$approved ) {\n"
			. "        return;\n"
			. "    }\n"
			. "    \$comment = get_comment( \$id );\n"
			. "    wp_mail(\n"
			. "        \$comment->comment_author_email,\n"
			. "        __( 'Thanks for your comment!', 'mytheme' ),\n"
			. "        sprintf(\n"
			. "            'We approved your reply to \"%s\".',\n"
			. "            get_the_title( \$comment->comment_post_ID )\n"
			. "        )\n"
			. "    );\n"
			. "}, 10, 2 );\n";

		$current_entry = self::current_theme_entry();
		// An inline (custom) theme has no URL; settings-preview.js swaps in
		// a Blob URL built from the stored CSS as soon as it initialises.
		$dl_url        = self::theme_url( $current_entry );
		if ( '' === $dl_url ) {
			$dl_url = '#';
		}
		$dl_name       = ! empty( $current_entry['download'] )
			? $current_entry['download']
			: ( ! empty( $current_entry['file'] ) ? $current_entry['file'] : 'theme.css' );
		?>
		<div class="cbe-preview" style="max-width:48rem;margin-top:1rem;">
			<p style="margin:0 0 0.5rem;color:#646970;font-size:0.85em;">
				<?php esc_html_e( 'Preview', 'coywolf-code-block-enhancer' ); ?>
			</p>
			<pre class="wp-block-code" data-language="php"><code class="language-php"><?php echo esc_html( $sample ); ?></code></pre>
			<p style="margin:0.5rem 0 0;font-size:0.85em;">
				<a id="cbe-preview-download"
				   href="<?php echo esc_url( $dl_url ); ?>"
				   download="<?php echo esc_attr( $dl_name ); ?>">
					<?php
					printf(
						/* translators: %s is the CSS filename. */
						esc_html__( 'Download %s', 'coywolf-code-block-enhancer' ),
						'<code>' . esc_html( $dl_name ) . '</code>'
					);
					?>
				</a>
				<span style="color:#646970;">
					— <?php esc_html_e( 'edit it and re-upload as a custom theme below.', 'coywolf-code-block-enhancer' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	private function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a post-redirect status flag set by handle_upload()/handle_remove() (both nonce-verified); no state change here.
		if ( empty( $_GET['coywolf_cbe_upload'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only post-redirect status flag, sanitized with sanitize_key(); no state change here.
		$status = sanitize_key( wp_unslash( $_GET['coywolf_cbe_upload'] ) );
		$kb     = (int) round( self::CUSTOM_MAX_BYTES / 1024 );
		$map = array(
			'ok'           => array( 'success', __( 'Custom theme uploaded.', 'coywolf-code-block-enhancer' ) ),
			'removed'      => array( 'success', __( 'Custom theme removed.', 'coywolf-code-block-enhancer' ) ),
			'missing'      => array( 'error',   __( 'No file was uploaded.', 'coywolf-code-block-enhancer' ) ),
			'error'        => array( 'error',   __( 'The upload failed. Please try again.', 'coywolf-code-block-enhancer' ) ),
			'too_large'    => array(
				'error',
				sprintf(
					/* translators: %d is the size limit in KB. */
					__( 'The file is larger than the %dKB limit.', 'coywolf-code-block-enhancer' ),
					$kb
				),
			),
			'not_css'      => array( 'error',   __( 'Only .css files are accepted.', 'coywolf-code-block-enhancer' ) ),
			'read_error'   => array( 'error',   __( 'Could not read the uploaded file.', 'coywolf-code-block-enhancer' ) ),
			'unsafe'       => array( 'error',   __( 'The file contained markup or scripts that are not allowed in a CSS theme (e.g. <script>, <?php, javascript: URIs, expression()). Nothing was saved.', 'coywolf-code-block-enhancer' ) ),
		);
		if ( ! isset( $map[ $status ] ) ) {
			return;
		}
		list( $type, $msg ) = $map[ $status ];
		printf(
			'<div class="notice notice-%s is-dismissible" role="alert"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $msg )
		);
	}

	/**
	 * Renders inside the Appearance section's form-table as the second
	 * row (after the theme dropdown). Emits its OWN standalone <form>
	 * elements (upload + remove) that post to admin-post.php — they sit
	 * inline among the Settings API rows but aren't part of the Settings
	 * API form's submission (the page-level Settings API form uses HTML5
	 * `form` attribute association, so its inputs live outside the form
	 * and the custom-theme forms below can be free-standing siblings
	 * without illegal nesting).
	 */
	public function render_custom_theme_field() {
		$meta = self::custom_theme_meta();
		$kb   = (int) round( self::CUSTOM_MAX_BYTES / 1024 );
		?>
		<p style="margin-top:0;">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d is the upload size limit in KB. */
					__( 'Upload a single .css file (up to %dKB). Only one custom theme is stored at a time — uploading a new file replaces the existing one. The file is checked for unsafe content (script tags, PHP open tags, javascript: URIs, expression(), etc.) and the stylesheet is then stored in your database — nothing is written to the filesystem.', 'coywolf-code-block-enhancer' )
					, $kb
				)
			);
			?>
		</p>

		<?php if ( $meta ) : ?>
			<p>
				<strong><?php esc_html_e( 'Current custom theme:', 'coywolf-code-block-enhancer' ); ?></strong>
				<?php
				$display = ! empty( $meta['name'] ) ? $meta['name'] : $meta['original_name'];
				echo esc_html( $display );
				if ( ! empty( $meta['name'] ) ) {
					echo ' <code>' . esc_html( $meta['original_name'] ) . '</code>';
				}
				?>
				(<?php echo esc_html( size_format( $meta['byte_size'] ) ); ?>,
				<?php
				printf(
					/* translators: %s is a human-readable time difference, e.g. "5 minutes". */
					esc_html__( 'uploaded %s ago', 'coywolf-code-block-enhancer' ),
					esc_html( human_time_diff( $meta['uploaded_at'], time() ) )
				);
				?>)
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin:0.5rem 0 0.75rem;">
			<input type="hidden" name="action" value="coywolf_cbe_upload_custom_theme" />
			<?php wp_nonce_field( 'coywolf_cbe_upload_custom_theme' ); ?>
			<p style="margin:0.25rem 0;">
				<label for="coywolf_cbe_custom_theme_name"><strong><?php esc_html_e( 'Theme name', 'coywolf-code-block-enhancer' ); ?></strong></label><br />
				<input type="text"
				       id="coywolf_cbe_custom_theme_name"
				       name="coywolf_cbe_custom_theme_name"
				       maxlength="60"
				       class="regular-text"
				       value="<?php echo esc_attr( $meta ? $meta['name'] : '' ); ?>"
				       placeholder="<?php esc_attr_e( 'e.g. My Brand Dark', 'coywolf-code-block-enhancer' ); ?>" />
				<span class="description" style="display:block;">
					<?php esc_html_e( 'Shown as the dropdown label, e.g. "Custom — My Brand Dark". Optional — the filename is used if left blank. Max 60 characters.', 'coywolf-code-block-enhancer' ); ?>
				</span>
			</p>
			<p style="margin:0.5rem 0;">
				<label for="coywolf_cbe_custom_theme"><strong><?php esc_html_e( 'CSS file', 'coywolf-code-block-enhancer' ); ?></strong></label><br />
				<input type="file" id="coywolf_cbe_custom_theme" name="coywolf_cbe_custom_theme" accept=".css,text/css" required />
			</p>
			<p style="margin:0.5rem 0;">
				<?php submit_button(
					$meta ? __( 'Replace custom theme', 'coywolf-code-block-enhancer' ) : __( 'Upload custom theme', 'coywolf-code-block-enhancer' ),
					'secondary',
					'coywolf_cbe_upload_submit',
					false
				); ?>
			</p>
		</form>

		<?php if ( $meta ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_attr( esc_js( __( 'Remove the custom theme? This cannot be undone.', 'coywolf-code-block-enhancer' ) ) ); ?>');" style="margin:0;">
				<input type="hidden" name="action" value="coywolf_cbe_remove_custom_theme" />
				<?php wp_nonce_field( 'coywolf_cbe_remove_custom_theme' ); ?>
				<?php submit_button( __( 'Remove custom theme', 'coywolf-code-block-enhancer' ), 'delete', 'coywolf_cbe_remove_submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_notices(); ?>

			<!--
				Empty Settings API form: carries only the nonce / option_page
				hidden fields that settings_fields() emits. Every actual input
				(theme <select>, language checkboxes) lives outside this form
				below and uses the HTML5 `form="cbe-theme-form"` attribute to
				bind to it. That keeps the custom-theme upload/remove <form>
				elements (which post to admin-post.php) as legal free-standing
				siblings instead of nested forms.
			-->
			<form id="cbe-theme-form" method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
			</form>

			<?php do_settings_sections( self::PAGE ); ?>

			<?php
			submit_button(
				null,
				'primary large',
				'submit',
				true,
				array( 'form' => 'cbe-theme-form' )
			);
			?>
		</div>
		<?php
	}
}

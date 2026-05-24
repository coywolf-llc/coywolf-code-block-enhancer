<?php
/**
 * Settings page (Tools → Code Blocks) for Coywolf Code Block Enhancer.
 *
 * Stores one option, `cbe_theme`, with one of the keys defined in
 * {@see self::themes()}. The chosen theme is applied on the front end by
 * enqueueing the matching stylesheet under assets/themes/; the
 * Default-palette variants (coywolf-auto / coywolf-light / coywolf-dark)
 * also add a `cbe-theme-light` / `cbe-theme-dark` body class so the
 * lock-class selectors in default.css beat its @media
 * (prefers-color-scheme) defaults.
 *
 * Custom-theme upload: a single CSS file can be uploaded; it lives at
 * `<uploads>/code-block-enhancer/custom.css` and is registered as the
 * `custom` theme key in the dropdown. Uploading again replaces it —
 * there is only ever one custom theme at a time.
 *
 * Migration: the legacy `cbe_theme_mode` option (auto / light / dark) is
 * translated once into the new option ("coywolf-{mode}") and then deleted.
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Settings {

	const OPTION         = 'cbe_theme';
	const LEGACY_OPTION  = 'cbe_theme_mode';
	const CUSTOM_OPTION  = 'cbe_custom_theme';
	const PAGE           = 'cbe-settings';
	const GROUP          = 'cbe_settings';
	const CAP            = 'manage_options';
	const DEFAULT_THEME  = 'coywolf-auto';
	const CUSTOM_KEY     = 'custom';
	const CUSTOM_DIRNAME = 'code-block-enhancer';
	const CUSTOM_FILE    = 'custom.css';
	const CUSTOM_MAX_BYTES = 262144; // 256 KB.

	/**
	 * Full theme registry. Each entry:
	 *   key     → option value + dropdown <option> value
	 *   label   → human-readable name shown in the dropdown
	 *   file    → relative path under assets/themes/, OR null when the
	 *             entry overrides with an absolute `url`
	 *   url     → absolute URL override (used for the custom upload, which
	 *             lives under wp-content/uploads/, not the plugin dir)
	 *   group   → optgroup label
	 *   lock    → 'light' | 'dark' | null — body class added when active
	 *
	 * The `custom` entry only appears when a custom CSS has been uploaded
	 * AND the file is still on disk — see {@see self::custom_theme_meta()}.
	 *
	 * @return array<string,array>
	 */
	public static function themes() {
		$coywolf = array(
			'coywolf-auto'  => array(
				'label'    => __( 'Default — Auto (follow OS dark mode)', 'code-block-enhancer' ),
				'file'     => 'default.css',
				'download' => 'default.css',
				'group'    => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'     => null,
			),
			'coywolf-light' => array(
				'label'    => __( 'Default — Always light', 'code-block-enhancer' ),
				'file'     => 'default.css',
				'download' => 'default.css',
				'group'    => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'     => 'light',
			),
			'coywolf-dark'  => array(
				'label'    => __( 'Default — Always dark', 'code-block-enhancer' ),
				'file'     => 'default.css',
				'download' => 'default.css',
				'group'    => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'     => 'dark',
			),
		);

		// Custom theme entry — only present if an upload exists on disk.
		// Display label prefers the user-provided `name` then falls back to
		// the original filename.
		$custom = array();
		$meta   = self::custom_theme_meta();
		if ( null !== $meta ) {
			$display_name = ! empty( $meta['name'] ) ? $meta['name'] : $meta['original_name'];
			$custom[ self::CUSTOM_KEY ] = array(
				'label'    => sprintf(
					/* translators: %s is the user-provided theme name (or the uploaded filename). */
					__( 'Custom — %s', 'code-block-enhancer' ),
					$display_name
				),
				'file'     => null,
				'url'      => self::custom_theme_url(),
				'download' => $meta['original_name'],
				'group'    => __( 'Custom', 'code-block-enhancer' ),
				'lock'     => null,
			);
		}

		// 8 built-in Prism themes (PrismJS/prism v1.30.0, MIT — see
		// assets/themes/LICENSE-prism). Minified files.
		$builtin_group = __( 'Prism (built-in)', 'code-block-enhancer' );
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
		$community_group = __( 'Prism Themes (community)', 'code-block-enhancer' );
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
	 * @return string Absolute URL to the theme stylesheet.
	 */
	public static function theme_url( array $entry ) {
		if ( ! empty( $entry['url'] ) ) {
			return $entry['url'];
		}
		return CBE_URL . 'assets/themes/' . $entry['file'];
	}

	public function init() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_setting' ) );
		add_action( 'admin_init',            array( $this, 'maybe_migrate_legacy_option' ) );
		add_action( 'admin_init',            array( 'Coywolf_CBE_Language_Packs', 'maybe_migrate_legacy_packs' ) );
		add_action( 'admin_init',            array( 'Coywolf_CBE_Language_Packs', 'maybe_merge_baseline_into_languages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_cbe_upload_custom_theme', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_cbe_remove_custom_theme', array( $this, 'handle_remove' ) );
		add_filter( 'admin_body_class',      array( $this, 'admin_body_class_for_lock' ) );
		add_filter( 'body_class',            array( $this, 'maybe_add_lock_class' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( CBE_PLUGIN_FILE ),
			array( $this, 'add_settings_action_link' )
		);
	}

	private function page_hook_suffix() {
		return 'tools_page_' . self::PAGE;
	}

	// ----- Custom-theme storage helpers ------------------------------------

	/**
	 * Absolute filesystem path to the custom-theme directory under uploads.
	 */
	private static function custom_dir() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . self::CUSTOM_DIRNAME . '/';
	}

	/**
	 * Public URL of the custom-theme directory under uploads.
	 */
	private static function custom_dir_url() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['baseurl'] ) . self::CUSTOM_DIRNAME . '/';
	}

	/**
	 * Metadata for the uploaded custom theme, or null if no upload exists.
	 *
	 * Re-validates that the file is still on disk so an externally-deleted
	 * file doesn't keep the option entry alive.
	 *
	 * @return array{original_name:string, name:string, uploaded_at:int, byte_size:int}|null
	 */
	public static function custom_theme_meta() {
		$meta = get_option( self::CUSTOM_OPTION, null );
		if ( ! is_array( $meta ) || empty( $meta['original_name'] ) ) {
			return null;
		}
		$path = self::custom_dir() . self::CUSTOM_FILE;
		if ( ! file_exists( $path ) ) {
			return null;
		}
		return array(
			'original_name' => (string) $meta['original_name'],
			'name'          => isset( $meta['name'] ) ? (string) $meta['name'] : '',
			'uploaded_at'   => isset( $meta['uploaded_at'] ) ? (int) $meta['uploaded_at'] : 0,
			'byte_size'     => isset( $meta['byte_size'] ) ? (int) $meta['byte_size'] : (int) filesize( $path ),
		);
	}

	/**
	 * Public URL of the custom theme stylesheet, with a cache-buster.
	 */
	public static function custom_theme_url() {
		$meta = self::custom_theme_meta();
		if ( null === $meta ) {
			return '';
		}
		return add_query_arg(
			'v',
			(int) $meta['uploaded_at'],
			self::custom_dir_url() . self::CUSTOM_FILE
		);
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

		// Reject if any of these dangerous tokens appear anywhere.
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
			wp_die( esc_html__( 'You do not have permission to upload themes.', 'code-block-enhancer' ) );
		}
		check_admin_referer( 'cbe_upload_custom_theme' );

		$back = admin_url( 'tools.php?page=' . self::PAGE );

		if ( empty( $_FILES['cbe_custom_theme'] ) || ! is_array( $_FILES['cbe_custom_theme'] ) ) {
			$this->redirect_with( $back, 'missing' );
		}

		$f = $_FILES['cbe_custom_theme'];

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
		// check above (.css required), the content sanitiser below
		// (rejects script tags / PHP / javascript: / etc.), and the
		// .htaccess `AddType text/css .css` we write into the uploads
		// directory together cover the same risk surface without the
		// false-positive.
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

		$dir = self::custom_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			$this->redirect_with( $back, 'mkdir_failed' );
		}

		// Best-effort .htaccess hint so Apache serves with text/css even
		// if a host's mime.types is incomplete. Harmless on Nginx (Nginx
		// ignores .htaccess) — there text/css is configured globally.
		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "AddType text/css .css\n", LOCK_EX );
		}

		$path = $dir . self::CUSTOM_FILE;
		if ( false === file_put_contents( $path, $clean, LOCK_EX ) ) {
			$this->redirect_with( $back, 'write_failed' );
		}

		// Optional user-provided display name. Sanitised hard (text only,
		// 60-char cap) since this string is rendered as the dropdown label
		// — esc_html on the way out, but we also keep the stored value tidy.
		$display = isset( $_POST['cbe_custom_theme_name'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['cbe_custom_theme_name'] ) )
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
	 * Delete the uploaded custom theme. If `cbe_theme` was set to
	 * `custom`, fall back to the default so the front end doesn't try to
	 * load a missing file.
	 */
	public function handle_remove() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to remove the custom theme.', 'code-block-enhancer' ) );
		}
		check_admin_referer( 'cbe_remove_custom_theme' );

		$path = self::custom_dir() . self::CUSTOM_FILE;
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
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
		wp_safe_redirect( add_query_arg( 'cbe_upload', $status, $url ) );
		exit;
	}

	// ----- Enqueue (admin preview) -----------------------------------------

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->page_hook_suffix() ) {
			return;
		}

		$prism_url = CBE_URL . 'assets/prism/';

		$chain = array(
			'cbe-prism'                   => 'prism-core.min.js',
			'cbe-prism-markup'            => 'prism-markup.min.js',
			'cbe-prism-markup-templating' => 'prism-markup-templating.min.js',
			'cbe-prism-clike'             => 'prism-clike.min.js',
			'cbe-prism-css'               => 'prism-css.min.js',
			'cbe-prism-javascript'        => 'prism-javascript.min.js',
			'cbe-prism-bash'              => 'prism-bash.min.js',
			'cbe-prism-json'              => 'prism-json.min.js',
			'cbe-prism-php'               => 'prism-php.min.js',
			'cbe-prism-python'            => 'prism-python.min.js',
			'cbe-prism-sql'               => 'prism-sql.min.js',
			'cbe-prism-yaml'              => 'prism-yaml.min.js',
		);
		$prev = array();
		foreach ( $chain as $handle => $file ) {
			wp_enqueue_script( $handle, $prism_url . $file, $prev, '1.30.0', true );
			$prev = array( $handle );
		}

		wp_enqueue_script(
			'cbe-code-blocks',
			CBE_URL . 'js/code-blocks.js',
			array( array_key_last( $chain ) ),
			CBE_VERSION,
			true
		);

		wp_enqueue_style( 'cbe-style', CBE_URL . 'css/code-block.css', array(), CBE_VERSION );

		$current_entry = self::current_theme_entry();
		wp_enqueue_style(
			'cbe-preview-theme-css',
			self::theme_url( $current_entry ),
			array( 'cbe-style' ),
			CBE_VERSION
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
			'cbe-style',
			'.cbe-preview .wp-block-code[data-language]{padding:.75rem 1rem 1rem}'
			. '.cbe-preview .wp-block-code[data-language]::before{position:static;display:block;top:auto;left:auto;margin:0 0 .5rem 0;font-family:inherit}'
			. '.cbe-preview .wp-block-code code{background:transparent!important;padding:0!important;margin:0!important;display:block}'
		);

		wp_enqueue_script(
			'cbe-settings-preview',
			CBE_URL . 'js/settings-preview.js',
			array(),
			CBE_VERSION,
			true
		);

		// Unsaved-changes guard. Loads in the footer like the rest of
		// our admin JS; the redirect-after-save bootstrap at the top
		// of the file runs immediately on parse so the post-save jump
		// is fast.
		wp_enqueue_script(
			'cbe-settings-unsaved',
			CBE_URL . 'js/settings-unsaved.js',
			array(),
			CBE_VERSION,
			true
		);
		$payload = array(
			'baseUrl' => esc_url_raw( CBE_URL . 'assets/themes/' ),
			'themes'  => array(),
		);
		foreach ( self::themes() as $key => $info ) {
			$entry = array( 'lock' => $info['lock'] );
			if ( ! empty( $info['url'] ) ) {
				$entry['url'] = $info['url'];
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
			'cbe-settings-preview',
			'window.cbeSettingsPreview = ' . wp_json_encode( $payload ) . ';',
			'before'
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
			__( 'Code Block Enhancer', 'code-block-enhancer' ),
			__( 'Code Blocks', 'code-block-enhancer' ),
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
			'cbe_appearance',
			__( 'Appearance', 'code-block-enhancer' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			self::OPTION,
			__( 'Code block theme', 'code-block-enhancer' ),
			array( $this, 'render_theme_field' ),
			self::PAGE,
			'cbe_appearance',
			array( 'label_for' => self::OPTION )
		);

		// Custom-theme upload lives inside Appearance as a peer row.
		// Its `render_custom_theme_field()` emits its own <form>
		// elements that post to admin-post.php (file upload + remove),
		// so they sit visually inside the Appearance form-table row
		// but aren't part of the Settings API form's submission.
		add_settings_field(
			'cbe_custom_theme_ui',
			__( 'Custom theme', 'code-block-enhancer' ),
			array( $this, 'render_custom_theme_field' ),
			self::PAGE,
			'cbe_appearance'
		);

		// Languages comes after Appearance.
		add_settings_section(
			'cbe_languages',
			__( 'Languages', 'code-block-enhancer' ),
			array( $this, 'render_languages_section_intro' ),
			self::PAGE
		);

		add_settings_field(
			Coywolf_CBE_Language_Packs::OPTION,
			__( 'Language packs', 'code-block-enhancer' ),
			array( $this, 'render_language_packs_field' ),
			self::PAGE,
			'cbe_languages'
		);
	}

	public function render_languages_section_intro() {
		echo '<p>' . esc_html__(
			'Tick the individual languages you want to appear in the Code block sidebar dropdown. The first 9 entries in Web / App dev (Bash, CSS, HTML/Markup, JavaScript, JSON, PHP, Python, SQL, YAML) are checked by default on a fresh install; everything else is optional. Visitors only download the grammar file for the language a code block actually uses on the page, plus any Prism dependencies that grammar needs (typically 1–6 KB each).',
			'code-block-enhancer'
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
			<details<?php echo $open_attr; ?> class="cbe-lang-pack" style="margin:0.5rem 0;border:1px solid #c3c4c7;border-radius:4px;padding:0.5rem 0.85rem;background:#fff;">
				<summary style="cursor:pointer;padding:0.25rem 0;list-style:revert;">
					<strong><?php echo esc_html( $pack['label'] ); ?></strong>
					<span style="color:#646970;margin-left:0.5rem;font-weight:normal;">
						(<?php
						printf(
							/* translators: 1: number enabled, 2: total in pack */
							esc_html__( '%1$d of %2$d enabled', 'code-block-enhancer' ),
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
					<a href="#" class="cbe-pack-toggle" data-cbe-pack-action="all"><?php esc_html_e( 'Select all in pack', 'code-block-enhancer' ); ?></a>
					&nbsp;|&nbsp;
					<a href="#" class="cbe-pack-toggle" data-cbe-pack-action="none"><?php esc_html_e( 'Clear pack', 'code-block-enhancer' ); ?></a>
				</p>
			</details>
			<?php
		}
		echo '</div>';

		// Tiny inline JS for the per-pack "select all / clear" helpers.
		// Self-contained — no separate JS file needed.
		?>
		<script>
		( function () {
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
		</script>
		<?php

		echo '<p class="description">' . esc_html__(
			'Tick the individual languages you want to appear in the Code block sidebar dropdown. Only ticked grammars are downloaded by visitors, and even then only when a page actually contains a code block in that language (the front-end loader fetches one grammar file per language used on the page, plus any Prism dependencies).',
			'code-block-enhancer'
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
			esc_html__( 'Settings', 'code-block-enhancer' )
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
			'code-block-enhancer'
		) . ' ';
		echo '<strong>' . esc_html__( 'Changing the dropdown only updates the preview below — your site keeps the saved theme until you click Save Changes.', 'code-block-enhancer' ) . '</strong>';
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
		$dl_url        = self::theme_url( $current_entry );
		$dl_name       = ! empty( $current_entry['download'] )
			? $current_entry['download']
			: ( ! empty( $current_entry['file'] ) ? $current_entry['file'] : 'theme.css' );
		?>
		<div class="cbe-preview" style="max-width:48rem;margin-top:1rem;">
			<p style="margin:0 0 0.5rem;color:#646970;font-size:0.85em;">
				<?php esc_html_e( 'Preview', 'code-block-enhancer' ); ?>
			</p>
			<pre class="wp-block-code" data-language="php"><code class="language-php"><?php echo esc_html( $sample ); ?></code></pre>
			<p style="margin:0.5rem 0 0;font-size:0.85em;">
				<a id="cbe-preview-download"
				   href="<?php echo esc_url( $dl_url ); ?>"
				   download="<?php echo esc_attr( $dl_name ); ?>">
					<?php
					printf(
						/* translators: %s is the CSS filename. */
						esc_html__( 'Download %s', 'code-block-enhancer' ),
						'<code>' . esc_html( $dl_name ) . '</code>'
					);
					?>
				</a>
				<span style="color:#646970;">
					— <?php esc_html_e( 'edit it and re-upload as a custom theme below.', 'code-block-enhancer' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	private function render_notices() {
		if ( empty( $_GET['cbe_upload'] ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_GET['cbe_upload'] ) );
		$kb     = (int) round( self::CUSTOM_MAX_BYTES / 1024 );
		$map = array(
			'ok'           => array( 'success', __( 'Custom theme uploaded.', 'code-block-enhancer' ) ),
			'removed'      => array( 'success', __( 'Custom theme removed.', 'code-block-enhancer' ) ),
			'missing'      => array( 'error',   __( 'No file was uploaded.', 'code-block-enhancer' ) ),
			'error'        => array( 'error',   __( 'The upload failed. Please try again.', 'code-block-enhancer' ) ),
			'too_large'    => array(
				'error',
				sprintf(
					/* translators: %d is the size limit in KB. */
					__( 'The file is larger than the %dKB limit.', 'code-block-enhancer' ),
					$kb
				),
			),
			'not_css'      => array( 'error',   __( 'Only .css files are accepted.', 'code-block-enhancer' ) ),
			'bad_mime'     => array( 'error',   __( "The file didn't look like a CSS file (MIME mismatch).", 'code-block-enhancer' ) ),
			'read_error'   => array( 'error',   __( 'Could not read the uploaded file.', 'code-block-enhancer' ) ),
			'unsafe'       => array( 'error',   __( 'The file contained markup or scripts that are not allowed in a CSS theme (e.g. <script>, <?php, javascript: URIs, expression()). Nothing was saved.', 'code-block-enhancer' ) ),
			'mkdir_failed' => array( 'error',   __( 'Could not create the uploads directory.', 'code-block-enhancer' ) ),
			'write_failed' => array( 'error',   __( 'Could not write the file to disk.', 'code-block-enhancer' ) ),
		);
		if ( ! isset( $map[ $status ] ) ) {
			return;
		}
		list( $type, $msg ) = $map[ $status ];
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
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
					__( 'Upload a single .css file (up to %dKB). Only one custom theme is stored at a time — uploading a new file replaces the existing one. The file is checked for unsafe content (script tags, PHP open tags, javascript: URIs, expression(), etc.) before being written, and is served from your uploads directory with the rest of your media.', 'code-block-enhancer' )
					, $kb
				)
			);
			?>
		</p>

		<?php if ( $meta ) : ?>
			<p>
				<strong><?php esc_html_e( 'Current custom theme:', 'code-block-enhancer' ); ?></strong>
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
					esc_html__( 'uploaded %s ago', 'code-block-enhancer' ),
					esc_html( human_time_diff( $meta['uploaded_at'], time() ) )
				);
				?>)
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin:0.5rem 0 0.75rem;">
			<input type="hidden" name="action" value="cbe_upload_custom_theme" />
			<?php wp_nonce_field( 'cbe_upload_custom_theme' ); ?>
			<p style="margin:0.25rem 0;">
				<label for="cbe_custom_theme_name"><strong><?php esc_html_e( 'Theme name', 'code-block-enhancer' ); ?></strong></label><br />
				<input type="text"
				       id="cbe_custom_theme_name"
				       name="cbe_custom_theme_name"
				       maxlength="60"
				       class="regular-text"
				       value="<?php echo esc_attr( $meta ? $meta['name'] : '' ); ?>"
				       placeholder="<?php esc_attr_e( 'e.g. My Brand Dark', 'code-block-enhancer' ); ?>" />
				<span class="description" style="display:block;">
					<?php esc_html_e( 'Shown as the dropdown label, e.g. "Custom — My Brand Dark". Optional — the filename is used if left blank. Max 60 characters.', 'code-block-enhancer' ); ?>
				</span>
			</p>
			<p style="margin:0.5rem 0;">
				<label for="cbe_custom_theme"><strong><?php esc_html_e( 'CSS file', 'code-block-enhancer' ); ?></strong></label><br />
				<input type="file" id="cbe_custom_theme" name="cbe_custom_theme" accept=".css,text/css" required />
			</p>
			<p style="margin:0.5rem 0;">
				<?php submit_button(
					$meta ? __( 'Replace custom theme', 'code-block-enhancer' ) : __( 'Upload custom theme', 'code-block-enhancer' ),
					'secondary',
					'cbe_upload_submit',
					false
				); ?>
			</p>
		</form>

		<?php if ( $meta ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_attr( esc_js( __( 'Remove the custom theme? This cannot be undone.', 'code-block-enhancer' ) ) ); ?>');" style="margin:0;">
				<input type="hidden" name="action" value="cbe_remove_custom_theme" />
				<?php wp_nonce_field( 'cbe_remove_custom_theme' ); ?>
				<?php submit_button( __( 'Remove custom theme', 'code-block-enhancer' ), 'delete', 'cbe_remove_submit', false ); ?>
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

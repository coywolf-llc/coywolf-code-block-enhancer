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
 * Migrations (each one-shot, run on admin_init):
 *   - legacy `cbe_theme_mode` (auto / light / dark) → "coywolf-{mode}".
 *   - pre-1.0.55 short-prefix options (`cbe_*`) → `coywolf_cbe_*`.
 *   - the removed custom-theme feature: its stored options are deleted,
 *     any site still set to the `custom` theme is reset to the default,
 *     and the legacy uploads/code-block-enhancer/ directory (file +
 *     .htaccess hint + directory) is removed from disk.
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Settings {

	const OPTION            = 'coywolf_cbe_theme';
	const LEGACY_OPTION     = 'cbe_theme_mode';
	const PAGE              = 'coywolf-cbe-settings';
	const GROUP             = 'coywolf_cbe_settings';
	const CAP               = 'manage_options';
	const DEFAULT_THEME     = 'coywolf-auto';

	// Legacy custom-theme storage (the feature was removed). Only referenced
	// by the one-shot cleanup and uninstall: the DB option names that held
	// the upload, plus the on-disk locations used before 1.0.55 when the
	// custom theme was a real file under uploads.
	const LEGACY_CUSTOM_OPTION     = 'coywolf_cbe_custom_theme';
	const LEGACY_CUSTOM_CSS_OPTION = 'coywolf_cbe_custom_theme_css';
	const LEGACY_CUSTOM_KEY        = 'custom';
	const LEGACY_DIRNAME           = 'code-block-enhancer';
	const LEGACY_FILE              = 'custom.css';

	// One-shot flag set after the prefix migration below has run.
	const MIGRATED_FLAG = 'coywolf_cbe_prefix_migrated_v1';

	// One-shot flag set after the removed custom-theme feature's leftover
	// state has been cleaned up.
	const CUSTOM_REMOVED_FLAG = 'coywolf_cbe_custom_theme_removed_v1';

	/**
	 * Pre-1.0.55 option names (short `cbe_` prefix — below the 4-character
	 * minimum WordPress.org requires) → their current names. Consumed by
	 * {@see self::maybe_migrate_option_prefixes()}.
	 */
	const PREFIX_RENAMES = array(
		'cbe_theme'               => self::OPTION,
		'cbe_languages'           => 'coywolf_cbe_languages',
		'cbe_baseline_merged_v1'  => 'coywolf_cbe_baseline_merged_v1',
	);

	/**
	 * Full theme registry. Each entry:
	 *   key     → option value + dropdown <option> value
	 *   label   → human-readable name shown in the dropdown
	 *   file    → relative path under assets/themes/
	 *   group   → optgroup label
	 *   lock    → 'light' | 'dark' | null — body class added when active
	 *
	 * @return array<string,array>
	 */
	public static function themes() {
		$coywolf = array(
			'coywolf-auto'  => array(
				'label'    => __( 'Default — Auto (follow OS dark mode)', 'coywolf-code-block-enhancer' ),
				'file'     => 'default.css',
				'group'    => __( 'Coywolf', 'coywolf-code-block-enhancer' ),
				'lock'     => null,
			),
			'coywolf-light' => array(
				'label'    => __( 'Default — Always light', 'coywolf-code-block-enhancer' ),
				'file'     => 'default.css',
				'group'    => __( 'Coywolf', 'coywolf-code-block-enhancer' ),
				'lock'     => 'light',
			),
			'coywolf-dark'  => array(
				'label'    => __( 'Default — Always dark', 'coywolf-code-block-enhancer' ),
				'file'     => 'default.css',
				'group'    => __( 'Coywolf', 'coywolf-code-block-enhancer' ),
				'lock'     => 'dark',
			),
		);

		// 8 built-in Prism themes (PrismJS/prism v1.30.0, MIT — see
		// assets/themes/LICENSE-prism.txt). Minified files.
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
				'label' => $label,
				'file'  => $file,
				'group' => $builtin_group,
				'lock'  => null,
			);
		}

		// 37 community themes (PrismJS/prism-themes, MIT — see
		// assets/themes/LICENSE-prism-themes.txt). Original .css files.
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
				'label' => $label,
				'file'  => $file,
				'group' => $community_group,
				'lock'  => null,
			);
		}

		return array_merge( $coywolf, $builtin_themes, $community_themes );
	}

	/**
	 * Build the public URL for any theme entry.
	 *
	 * @param array $entry One entry from {@see self::themes()}.
	 * @return string Absolute URL to the theme stylesheet, or '' if the
	 *                entry has no file.
	 */
	public static function theme_url( array $entry ) {
		if ( empty( $entry['file'] ) ) {
			return '';
		}
		return COYWOLF_CBE_URL . 'assets/themes/' . $entry['file'];
	}

	public function init() {
		// Prefix migration must run before anything that reads the renamed
		// options (same hook, registered first → runs first).
		add_action( 'admin_init',            array( __CLASS__, 'maybe_migrate_option_prefixes' ) );
		add_action( 'admin_init',            array( __CLASS__, 'maybe_cleanup_custom_theme' ) );
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_setting' ) );
		add_action( 'admin_init',            array( $this, 'maybe_migrate_legacy_option' ) );
		add_action( 'admin_init',            array( 'Coywolf_CBE_Language_Packs', 'maybe_migrate_legacy_packs' ) );
		add_action( 'admin_init',            array( 'Coywolf_CBE_Language_Packs', 'maybe_merge_baseline_into_languages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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

	// ----- Legacy custom-theme cleanup -------------------------------------

	/**
	 * Absolute filesystem path to the legacy (pre-1.0.55) custom-theme
	 * directory under uploads. Cleanup only.
	 */
	private static function legacy_dir() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . self::LEGACY_DIRNAME . '/';
	}

	/**
	 * One-shot migration for installs upgrading from ≤1.0.54: rename the
	 * short-prefix `cbe_*` options to `coywolf_cbe_*` (WordPress.org
	 * requires prefixes of at least 4 characters).
	 *
	 * Idempotent: every rename only acts when the legacy option exists,
	 * and the whole pass is skipped once the one-shot flag is set (so
	 * steady-state admin requests pay a single autoloaded option read).
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

		update_option( self::MIGRATED_FLAG, 1 );
	}

	/**
	 * One-shot cleanup for the removed custom-theme feature. Drops the
	 * options that held the upload, resets any site still pointing at the
	 * now-defunct `custom` theme back to the default, and removes the
	 * pre-1.0.55 on-disk custom-theme directory (file + .htaccess hint +
	 * directory) from uploads.
	 *
	 * Idempotent and skipped after the one-shot flag is set, so the
	 * sanitize_theme()/current_theme() fallback is all that handles a
	 * leftover `custom` selection in steady state.
	 */
	public static function maybe_cleanup_custom_theme() {
		if ( get_option( self::CUSTOM_REMOVED_FLAG ) ) {
			return;
		}

		// Reset any site still set to the removed `custom` theme.
		if ( self::LEGACY_CUSTOM_KEY === get_option( self::OPTION, self::DEFAULT_THEME ) ) {
			update_option( self::OPTION, self::DEFAULT_THEME );
		}

		// Drop the stored upload (current and pre-1.0.55 option names).
		delete_option( self::LEGACY_CUSTOM_OPTION );
		delete_option( self::LEGACY_CUSTOM_CSS_OPTION );
		delete_option( 'cbe_custom_theme' );

		// Remove the legacy on-disk custom theme (pre-1.0.55 file storage),
		// its .htaccess MIME hint, and the now-empty directory.
		$dir = self::legacy_dir();
		foreach ( array( self::LEGACY_FILE, '.htaccess' ) as $file ) {
			$path = $dir . $file;
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
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

		update_option( self::CUSTOM_REMOVED_FLAG, 1 );
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

		// Preview stylesheet <link> for the saved theme; settings-preview.js
		// swaps the href as the dropdown changes.
		$preview_url = self::theme_url( self::current_theme_entry() );
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
			$payload['themes'][ $key ] = array(
				'lock' => $info['lock'],
				'file' => $info['file'],
			);
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

		// `form` attribute binds this input to the empty Settings API form at
		// the top of the page; the inputs themselves live outside that form
		// (alongside the language checkboxes) so the page markup stays flat.
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

		?>
		<div class="cbe-preview" style="max-width:48rem;margin-top:1rem;">
			<p style="margin:0 0 0.5rem;color:#646970;font-size:0.85em;">
				<?php esc_html_e( 'Preview', 'coywolf-code-block-enhancer' ); ?>
			</p>
			<pre class="wp-block-code" data-language="php"><code class="language-php"><?php echo esc_html( $sample ); ?></code></pre>
		</div>
		<?php
	}

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!--
				Empty Settings API form: carries only the nonce / option_page
				hidden fields that settings_fields() emits. Every actual input
				(theme <select>, language checkboxes) lives outside this form
				below and uses the HTML5 `form="cbe-theme-form"` attribute to
				bind to it, keeping the page markup flat instead of nesting
				inputs inside the form.
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

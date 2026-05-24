<?php
/**
 * Settings page (Tools → Code Blocks) for Coywolf Code Block Enhancer.
 *
 * Stores one option, `cbe_theme`, with one of the keys defined in
 * {@see self::themes()}. The chosen theme is applied on the front end by
 * enqueueing the matching stylesheet under assets/themes/; the Coywolf
 * Default-palette variants (coywolf-auto / coywolf-light / coywolf-dark) also add a
 * `cbe-theme-light` / `cbe-theme-dark` body class so the lock-class
 * selectors in coywolf-claude.css beat its @media (prefers-color-scheme)
 * defaults.
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

	const OPTION        = 'cbe_theme';
	const LEGACY_OPTION = 'cbe_theme_mode';
	const PAGE          = 'cbe-settings';
	const GROUP         = 'cbe_settings';
	const CAP           = 'manage_options';
	const DEFAULT_THEME = 'coywolf-auto';

	/**
	 * Full theme registry. Each entry:
	 *   key     → option value + dropdown <option> value
	 *   label   → human-readable name shown in the dropdown
	 *   file    → relative path under assets/themes/ (or null for built-ins
	 *             that have no theme stylesheet of their own)
	 *   group   → optgroup label
	 *   lock    → 'light' | 'dark' | null — body class added when active
	 *
	 * @return array<string,array>
	 */
	public static function themes() {
		$coywolf = array(
			'coywolf-auto'  => array(
				'label' => __( 'Default — Auto (follow OS dark mode)', 'code-block-enhancer' ),
				'file'  => 'coywolf-claude.css',
				'group' => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'  => null,
			),
			'coywolf-light' => array(
				'label' => __( 'Default — Always light', 'code-block-enhancer' ),
				'file'  => 'coywolf-claude.css',
				'group' => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'  => 'light',
			),
			'coywolf-dark'  => array(
				'label' => __( 'Default — Always dark', 'code-block-enhancer' ),
				'file'  => 'coywolf-claude.css',
				'group' => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'  => 'dark',
			),
		);

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
			$builtin_themes[ $key ] = array(
				'label' => $label,
				'file'  => $key . '.min.css',
				'group' => $builtin_group,
				'lock'  => null,
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
			$community_themes[ $key ] = array(
				'label' => $label,
				'file'  => $key . '.css',
				'group' => $community_group,
				'lock'  => null,
			);
		}

		return array_merge( $coywolf, $builtin_themes, $community_themes );
	}

	public function init() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_setting' ) );
		add_action( 'admin_init',            array( $this, 'maybe_migrate_legacy_option' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'admin_body_class',      array( $this, 'admin_body_class_for_lock' ) );
		add_filter( 'body_class',            array( $this, 'maybe_add_lock_class' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( CBE_PLUGIN_FILE ),
			array( $this, 'add_settings_action_link' )
		);
	}

	/**
	 * Hook suffix added by add_submenu_page() for this page. Used to scope
	 * admin_enqueue_scripts so we only load Prism / theme CSS on our screen.
	 */
	private function page_hook_suffix() {
		return 'tools_page_' . self::PAGE;
	}

	/**
	 * Enqueue the live-preview assets on Tools → Code Blocks only.
	 *
	 * Mirrors the front-end enqueue chain: Prism core + grammars, the
	 * copy-button script, the chrome CSS, and the currently-saved theme
	 * CSS. The theme CSS handle is well-known (`cbe-preview-theme-css`)
	 * so the preview JS can rewrite its href on every dropdown change
	 * without enqueueing a second link tag.
	 *
	 * Nothing here writes to the database — the option only updates when
	 * the Settings API form submits via Save Changes.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->page_hook_suffix() ) {
			return;
		}

		$prism_url   = CBE_URL . 'assets/prism/';
		$themes_url  = CBE_URL . 'assets/themes/';

		// Same dependency chain used on the front end. No `defer` strategy
		// — admin pages don't need the rendering optimisation and we want
		// Prism's DOMContentLoaded auto-highlight to fire normally.
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

		// Copy button: depends on the last grammar so all of Prism is present.
		wp_enqueue_script(
			'cbe-code-blocks',
			CBE_URL . 'js/code-blocks.js',
			array( array_key_last( $chain ) ),
			CBE_VERSION,
			true
		);

		// Chrome (layout / language label / copy button) + saved theme CSS.
		wp_enqueue_style( 'cbe-style', CBE_URL . 'css/code-block.css', array(), CBE_VERSION );

		$current_entry = self::current_theme_entry();
		wp_enqueue_style(
			'cbe-preview-theme-css',
			$themes_url . $current_entry['file'],
			array( 'cbe-style' ),
			CBE_VERSION
		);

		// Live-preview controller. Localize the full theme registry (just
		// file + lock — labels are already in the dropdown DOM) so the
		// dropdown change handler can swap stylesheets client-side.
		wp_enqueue_script(
			'cbe-settings-preview',
			CBE_URL . 'js/settings-preview.js',
			array(),
			CBE_VERSION,
			true
		);
		$payload = array(
			'baseUrl' => esc_url_raw( $themes_url ),
			'themes'  => array(),
		);
		foreach ( self::themes() as $key => $info ) {
			$payload['themes'][ $key ] = array(
				'file' => $info['file'],
				'lock' => $info['lock'],
			);
		}
		wp_add_inline_script(
			'cbe-settings-preview',
			'window.cbeSettingsPreview = ' . wp_json_encode( $payload ) . ';',
			'before'
		);
	}

	/**
	 * Mirror maybe_add_lock_class() onto the admin <body> so the preview
	 * pane on our settings page renders the saved theme's locked palette
	 * correctly before the preview JS runs.
	 *
	 * Scoped to our hook suffix to avoid polluting other admin screens.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
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
	}

	/**
	 * One-shot migration from the legacy `cbe_theme_mode` (auto / light /
	 * dark) to the new `cbe_theme` key. Runs once on any admin pageload
	 * after upgrade, then deletes the legacy option.
	 */
	public function maybe_migrate_legacy_option() {
		$legacy = get_option( self::LEGACY_OPTION, null );
		if ( null === $legacy ) {
			return;
		}
		// Only migrate if the new option hasn't already been set explicitly.
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

	/**
	 * Whitelist sanitiser — collapse anything off the registry to the default.
	 *
	 * @param mixed $value Raw input.
	 * @return string A valid theme key.
	 */
	public static function sanitize_theme( $value ) {
		$value  = is_string( $value ) ? $value : '';
		$themes = self::themes();
		return array_key_exists( $value, $themes ) ? $value : self::DEFAULT_THEME;
	}

	/**
	 * Currently selected theme key (always one of {@see self::themes()}).
	 */
	public static function current_theme() {
		$stored = get_option( self::OPTION, self::DEFAULT_THEME );
		$themes = self::themes();
		return array_key_exists( $stored, $themes ) ? $stored : self::DEFAULT_THEME;
	}

	/**
	 * Full registry entry for the active theme.
	 */
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

	/**
	 * Theme dropdown, grouped into <optgroup>s.
	 */
	public function render_theme_field() {
		$current = self::current_theme();
		$themes  = self::themes();

		// Bucket by group, preserving registry order within each group.
		$by_group = array();
		foreach ( $themes as $key => $info ) {
			$by_group[ $info['group'] ][ $key ] = $info['label'];
		}

		printf( '<select id="%1$s" name="%1$s">', esc_attr( self::OPTION ) );
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

		// Live preview pane. The HTML is the same shape the front-end
		// `core/code` block renders (`<pre class="wp-block-code" data-language="…">
		// <code class="language-…">…</code></pre>`), so the plugin's chrome
		// CSS + Prism + the selected theme CSS apply unchanged. js/settings-preview.js
		// rewrites the <link href> on every dropdown change; no DB write
		// happens until the form submits.
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
				<?php esc_html_e( 'Preview', 'code-block-enhancer' ); ?>
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
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

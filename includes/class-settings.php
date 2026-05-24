<?php
/**
 * Settings page (Tools → Code Blocks) for Coywolf Code Block Enhancer.
 *
 * Stores one option, `cbe_theme`, with one of the keys defined in
 * {@see self::themes()}. The chosen theme is applied on the front end by
 * enqueueing the matching stylesheet under assets/themes/; the Coywolf
 * Claude variants (coywolf-auto / coywolf-light / coywolf-dark) also add a
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
				'label' => __( 'Coywolf Claude — Auto (follow OS dark mode)', 'code-block-enhancer' ),
				'file'  => 'coywolf-claude.css',
				'group' => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'  => null,
			),
			'coywolf-light' => array(
				'label' => __( 'Coywolf Claude — Always light', 'code-block-enhancer' ),
				'file'  => 'coywolf-claude.css',
				'group' => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'  => 'light',
			),
			'coywolf-dark'  => array(
				'label' => __( 'Coywolf Claude — Always dark', 'code-block-enhancer' ),
				'file'  => 'coywolf-claude.css',
				'group' => __( 'Coywolf', 'code-block-enhancer' ),
				'lock'  => 'dark',
			),
		);

		// 8 built-in Prism themes (PrismJS/prism v1.30.0, MIT — see
		// assets/themes/LICENSE-prism). Minified files.
		$builtin_group = __( 'Prism (built-in)', 'code-block-enhancer' );
		$builtin       = array(
			'prism'              => 'Default',
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate_legacy_option' ) );
		add_filter( 'body_class', array( $this, 'maybe_add_lock_class' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( CBE_PLUGIN_FILE ),
			array( $this, 'add_settings_action_link' )
		);
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
			'The "Coywolf Claude — Auto" default follows each visitor\'s OS dark-mode preference. The two "Always" variants lock it to one appearance. The Prism themes below are static — they always render in their designed light or dark colours.',
			'code-block-enhancer'
		) . ' ';
		printf(
			/* translators: %s is a link to the Prism themes preview page. */
			esc_html__( 'See live previews on the %s preview page.', 'code-block-enhancer' ),
			'<a href="https://prismjs.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Prism', 'code-block-enhancer' ) . '</a>'
		);
		echo '</p>';
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

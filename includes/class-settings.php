<?php
/**
 * Settings page (Tools → Code Blocks) for Coywolf Code Block Enhancer.
 *
 * Single option, `cbe_theme_mode`, with three valid values:
 *   - 'auto'  (default) — follow the visitor's prefers-color-scheme
 *   - 'light' — always light, regardless of OS
 *   - 'dark'  — always dark, regardless of OS
 *
 * The chosen mode is applied on the front end by adding cbe-theme-light /
 * cbe-theme-dark to <body> via body_class. CSS in css/code-block.css uses
 * those classes to override the @media (prefers-color-scheme: dark) rule.
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Settings {

	const OPTION   = 'cbe_theme_mode';
	const PAGE     = 'cbe-settings';
	const GROUP    = 'cbe_settings';
	const CAP      = 'manage_options';
	const DEFAULT_MODE = 'auto';

	public static function valid_modes() {
		return array( 'auto', 'light', 'dark' );
	}

	public function init() {
		add_action( 'admin_menu',  array( $this, 'register_menu' ) );
		add_action( 'admin_init',  array( $this, 'register_setting' ) );
		add_filter( 'body_class',  array( $this, 'maybe_add_lock_class' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( CBE_PLUGIN_FILE ),
			array( $this, 'add_settings_action_link' )
		);
	}

	/**
	 * Add the submenu under Tools.
	 */
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

	/**
	 * Register the option with the Settings API and wire up the field.
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_mode' ),
				'default'           => self::DEFAULT_MODE,
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
			array( 'label_for' => self::OPTION . '-auto' )
		);
	}

	/**
	 * Whitelist sanitiser — anything off the list collapses to the default.
	 *
	 * @param mixed $value Raw input from the Settings API.
	 * @return string One of 'auto' | 'light' | 'dark'.
	 */
	public static function sanitize_mode( $value ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, self::valid_modes(), true ) ? $value : self::DEFAULT_MODE;
	}

	/**
	 * Read the current mode, falling back to the default if the option is
	 * absent or invalid (defence in case the option was set outside the UI).
	 */
	public static function current_mode() {
		$stored = get_option( self::OPTION, self::DEFAULT_MODE );
		return in_array( $stored, self::valid_modes(), true ) ? $stored : self::DEFAULT_MODE;
	}

	/**
	 * Add cbe-theme-light / cbe-theme-dark to <body> when the admin has
	 * locked the appearance; do nothing when the mode is "auto".
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public function maybe_add_lock_class( $classes ) {
		$mode = self::current_mode();
		if ( 'light' === $mode ) {
			$classes[] = 'cbe-theme-light';
		} elseif ( 'dark' === $mode ) {
			$classes[] = 'cbe-theme-dark';
		}
		return $classes;
	}

	/**
	 * Add a "Settings" link next to Deactivate on the Plugins screen row.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
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
	 * Radio group for the theme mode.
	 */
	public function render_theme_field() {
		$current = self::current_mode();
		$options = array(
			'auto'  => __( "Auto — follow the visitor's OS or browser preference (default)", 'code-block-enhancer' ),
			'light' => __( 'Always light', 'code-block-enhancer' ),
			'dark'  => __( 'Always dark', 'code-block-enhancer' ),
		);
		echo '<fieldset>';
		foreach ( $options as $value => $label ) {
			printf(
				'<label style="display:block;margin:0.25rem 0;"><input type="radio" id="%1$s" name="%2$s" value="%3$s" %4$s /> %5$s</label>',
				esc_attr( self::OPTION . '-' . $value ),
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__(
			'Choose whether code blocks adapt to the visitor\'s OS dark-mode preference, or are locked to one appearance for everyone.',
			'code-block-enhancer'
		) . '</p>';
	}

	/**
	 * Settings page wrapper.
	 */
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

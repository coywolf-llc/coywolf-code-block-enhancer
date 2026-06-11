<?php
/**
 * Language registry for Coywolf Code Block Enhancer.
 *
 * Every user-pickable grammar lives in one of five UI packs. The Web /
 * App dev pack is the "baseline" — its first nine entries (Bash, CSS,
 * HTML/Markup, JavaScript, JSON, PHP, Python, SQL, YAML) are checked
 * by default on a fresh install; the remaining entries in that pack
 * (TypeScript, JSX, TSX, SCSS, Sass, Less, GraphQL) are unchecked.
 * All four other packs are unchecked by default.
 *
 * Two grammars (`markup-templating` and `clike`) exist only as
 * dependencies of user-pickable grammars and don't appear in any pack
 * or in the editor dropdown. They get pulled into the loaded set
 * automatically whenever a grammar that needs them is enabled (PHP
 * needs markup-templating; JavaScript and most C-family backend langs
 * need clike).
 *
 * The active state is persisted as a flat list of enabled language
 * handles in the `coywolf_cbe_languages` option. Two migrations run on
 * `admin_init` and are each idempotent (a third — the short-prefix
 * option rename — lives in Coywolf_CBE_Settings and runs first):
 *
 *   maybe_migrate_legacy_packs()      — expand the v1.0.27
 *                                       `cbe_language_packs` pack-keys
 *                                       array into per-language
 *                                       handles, then delete the
 *                                       legacy option.
 *   maybe_merge_baseline_into_languages()
 *                                     — for sites upgrading from
 *                                       v1.0.28 (or earlier via
 *                                       pack-migration), merge the 9
 *                                       former-baseline handles into
 *                                       the languages option so the editor
 *                                       dropdown doesn't lose them.
 *                                       Tracked by a one-shot flag
 *                                       option.
 *
 * Languages users sometimes ask about that ARE intentionally omitted:
 * Vue, Svelte (Prism ships no standalone grammar); XML (alias for the
 * markup grammar); T-SQL (covered by the sql grammar).
 *
 * Public surface:
 *
 *   self::all_packs()              → registry of pack metadata + langs
 *   self::default_languages()      → handles enabled on first install
 *   self::enabled_languages()      → the admin's saved selection
 *   self::sanitize_languages()     → whitelist sanitiser for the option
 *   self::active_handles_with_deps()
 *                                  → ordered [handle => [requires]] map
 *                                    suitable for wp_register_script,
 *                                    with transitive deps pulled in
 *   self::active_language_choices()
 *                                  → { value, label } pairs for the
 *                                    editor dropdown (enabled langs
 *                                    only, grouped by pack)
 *   self::active_language_handles()
 *                                  → flat list of selectable handles
 *                                    (render_block's allowlist)
 *   self::maybe_migrate_legacy_packs()
 *   self::maybe_merge_baseline_into_languages()
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Language_Packs {

	const OPTION              = 'coywolf_cbe_languages';
	const LEGACY_PACKS_OPTION = 'cbe_language_packs'; // Legacy (≤1.0.27) option — read by migration only.
	const BASELINE_MERGE_FLAG = 'coywolf_cbe_baseline_merged_v1';

	/**
	 * Per-request memo store. The pack registry and everything derived
	 * purely from it are constant within a request; the derived "active"
	 * sets depend only on the enabled-language option, which is stable
	 * within a front-end request. Memoising here turns what used to be
	 * dozens of registry rebuilds (each with ~22 __() i18n lookups) per
	 * page — render_block alone recomputes the allowlist once per code
	 * block — into a single build.
	 *
	 * Entries keyed on the enabled set ('handles:<hash>', 'choices:<hash>')
	 * recompute automatically if that set ever changes mid-request (e.g.
	 * an option update during an admin save), so the cache can't go stale.
	 *
	 * @var array<string, mixed>
	 */
	private static $memo = array();

	/**
	 * Grammars that aren't user-pickable but are pulled in automatically
	 * when something that needs them is enabled. Indexed the same way as
	 * a pack entry so the dependency resolver can look them up uniformly.
	 */
	private static function dep_grammars() {
		return array(
			'markup-templating' => array( 'requires' => array( 'markup' ) ),
			'clike'             => array( 'requires' => array() ),
		);
	}

	/**
	 * Handles checked by default on first install — the 9 entries at the
	 * top of the Web/App dev pack. Mirrors what older versions of the
	 * plugin loaded as the always-on baseline.
	 */
	public static function default_languages() {
		return array( 'bash', 'css', 'markup', 'javascript', 'json', 'php', 'python', 'sql', 'yaml' );
	}

	/**
	 * The same nine handles as a private list, used by the
	 * baseline-merge migration so existing installs don't lose them.
	 */
	private static function former_baseline_handles() {
		return self::default_languages();
	}

	/**
	 * UI groupings — packs. The Web/App dev pack now holds the 9
	 * default-checked baseline grammars at the top followed by the 7
	 * default-unchecked web/app extras.
	 */
	private static function packs() {
		if ( isset( self::$memo['packs'] ) ) {
			return self::$memo['packs'];
		}
		self::$memo['packs'] = self::build_packs();
		return self::$memo['packs'];
	}

	private static function build_packs() {
		return array(
			'web_app'    => array(
				'label'       => __( 'Web / App dev', 'coywolf-code-block-enhancer' ),
				'description' => __( 'Bash, CSS, HTML/Markup, JavaScript, JSON, PHP, Python, SQL, YAML — plus TypeScript, JSX, TSX, SCSS, Sass, Less, GraphQL.', 'coywolf-code-block-enhancer' ),
				'langs'       => array(
					// Default-checked: the former always-on baseline.
					'bash'       => array( 'label' => 'Bash / Shell',  'requires' => array() ),
					'css'        => array( 'label' => 'CSS',           'requires' => array( 'markup' ) ),
					'markup'     => array( 'label' => 'HTML / Markup', 'requires' => array() ),
					'javascript' => array( 'label' => 'JavaScript',    'requires' => array( 'clike' ) ),
					'json'       => array( 'label' => 'JSON',          'requires' => array() ),
					'php'        => array( 'label' => 'PHP',           'requires' => array( 'markup-templating' ) ),
					'python'     => array( 'label' => 'Python',        'requires' => array() ),
					'sql'        => array( 'label' => 'SQL',           'requires' => array() ),
					'yaml'       => array( 'label' => 'YAML',          'requires' => array() ),
					// Default-unchecked: web/app extras.
					'typescript' => array( 'label' => 'TypeScript',    'requires' => array( 'javascript' ) ),
					'jsx'        => array( 'label' => 'React JSX',     'requires' => array( 'markup', 'javascript' ) ),
					'tsx'        => array( 'label' => 'React TSX',     'requires' => array( 'jsx', 'typescript' ) ),
					'scss'       => array( 'label' => 'Sass (SCSS)',   'requires' => array( 'css' ) ),
					'sass'       => array( 'label' => 'Sass (Sass)',   'requires' => array( 'css' ) ),
					'less'       => array( 'label' => 'Less',          'requires' => array( 'css' ) ),
					'graphql'    => array( 'label' => 'GraphQL',       'requires' => array() ),
				),
			),
			'backend'    => array(
				'label'       => __( 'Backend languages', 'coywolf-code-block-enhancer' ),
				'description' => __( 'Go, Rust, Ruby, Java, C, C++, C#, Kotlin, Scala, Swift, Elixir, Erlang, Dart, Lua, Perl, R, Objective-C.', 'coywolf-code-block-enhancer' ),
				'langs'       => array(
					'go'         => array( 'label' => 'Go',          'requires' => array( 'clike' ) ),
					'rust'       => array( 'label' => 'Rust',        'requires' => array() ),
					'ruby'       => array( 'label' => 'Ruby',        'requires' => array( 'clike' ) ),
					'java'       => array( 'label' => 'Java',        'requires' => array( 'clike' ) ),
					'c'          => array( 'label' => 'C',           'requires' => array( 'clike' ) ),
					'cpp'        => array( 'label' => 'C++',         'requires' => array( 'c' ) ),
					'csharp'     => array( 'label' => 'C#',          'requires' => array( 'clike' ) ),
					'kotlin'     => array( 'label' => 'Kotlin',      'requires' => array( 'clike' ) ),
					'scala'      => array( 'label' => 'Scala',       'requires' => array( 'java' ) ),
					'swift'      => array( 'label' => 'Swift',       'requires' => array() ),
					'elixir'     => array( 'label' => 'Elixir',      'requires' => array() ),
					'erlang'     => array( 'label' => 'Erlang',      'requires' => array() ),
					'dart'       => array( 'label' => 'Dart',        'requires' => array( 'clike' ) ),
					'lua'        => array( 'label' => 'Lua',         'requires' => array() ),
					'perl'       => array( 'label' => 'Perl',        'requires' => array() ),
					'r'          => array( 'label' => 'R',           'requires' => array() ),
					'objectivec' => array( 'label' => 'Objective-C', 'requires' => array( 'c' ) ),
				),
			),
			'shells_ops' => array(
				'label'       => __( 'Shells / Ops', 'coywolf-code-block-enhancer' ),
				'description' => __( 'PowerShell, Docker, nginx, Apache Configuration, systemd.', 'coywolf-code-block-enhancer' ),
				'langs'       => array(
					'powershell' => array( 'label' => 'PowerShell',    'requires' => array() ),
					'docker'     => array( 'label' => 'Docker',        'requires' => array() ),
					'nginx'      => array( 'label' => 'nginx',         'requires' => array() ),
					'apacheconf' => array( 'label' => 'Apache config', 'requires' => array() ),
					'systemd'    => array( 'label' => 'systemd',       'requires' => array() ),
				),
			),
			'data_docs'  => array(
				'label'       => __( 'Data / Docs', 'coywolf-code-block-enhancer' ),
				'description' => __( 'Markdown, TOML, INI, Diff, Git, Regex. (XML is the HTML / Markup grammar in the Web/App dev pack.)', 'coywolf-code-block-enhancer' ),
				'langs'       => array(
					'markdown' => array( 'label' => 'Markdown', 'requires' => array( 'markup' ) ),
					'toml'     => array( 'label' => 'TOML',     'requires' => array() ),
					'ini'      => array( 'label' => 'INI',      'requires' => array() ),
					'diff'     => array( 'label' => 'Diff',     'requires' => array() ),
					'git'      => array( 'label' => 'Git',      'requires' => array() ),
					'regex'    => array( 'label' => 'Regex',    'requires' => array() ),
				),
			),
			'db'         => array(
				'label'       => __( 'DB', 'coywolf-code-block-enhancer' ),
				'description' => __( 'PL/SQL, Cypher (Neo4j), MongoDB, SPARQL, Turtle. (T-SQL syntax is covered by the SQL grammar in the Web/App dev pack.)', 'coywolf-code-block-enhancer' ),
				'langs'       => array(
					'plsql'   => array( 'label' => 'PL/SQL',  'requires' => array( 'sql' ) ),
					'cypher'  => array( 'label' => 'Cypher',  'requires' => array() ),
					'mongodb' => array( 'label' => 'MongoDB', 'requires' => array( 'javascript' ) ),
					'sparql'  => array( 'label' => 'SPARQL',  'requires' => array( 'turtle' ) ),
					'turtle'  => array( 'label' => 'Turtle',  'requires' => array() ),
				),
			),
		);
	}

	public static function all_packs() {
		return self::packs();
	}

	/** Flat map of every grammar's info (deps + dep-only grammars). */
	private static function all_known_grammars() {
		if ( isset( self::$memo['known'] ) ) {
			return self::$memo['known'];
		}
		$out = self::dep_grammars();
		foreach ( self::packs() as $pack ) {
			foreach ( $pack['langs'] as $h => $info ) {
				$out[ $h ] = array( 'requires' => isset( $info['requires'] ) ? $info['requires'] : array() );
			}
		}
		self::$memo['known'] = $out;
		return $out;
	}

	/** Whitelist of every user-pickable handle in any pack. */
	private static function all_pack_handles() {
		if ( isset( self::$memo['pack_handles'] ) ) {
			return self::$memo['pack_handles'];
		}
		$out = array();
		foreach ( self::packs() as $pack ) {
			$out = array_merge( $out, array_keys( $pack['langs'] ) );
		}
		self::$memo['pack_handles'] = $out;
		return $out;
	}

	public static function sanitize_languages( $value ) {
		if ( ! is_array( $value ) ) {
			return self::default_languages();
		}
		$valid = self::all_pack_handles();
		$out   = array();
		foreach ( $value as $h ) {
			if ( is_string( $h ) && in_array( $h, $valid, true ) && ! in_array( $h, $out, true ) ) {
				$out[] = $h;
			}
		}
		return $out; // Empty array is allowed (means "no languages offered in the dropdown").
	}

	public static function enabled_languages() {
		$sentinel = '__cbe_unset__';
		$stored   = get_option( self::OPTION, $sentinel );
		if ( $sentinel === $stored ) {
			return self::default_languages();
		}
		return self::sanitize_languages( $stored );
	}

	/**
	 * One-shot migration from v1.0.27's `cbe_language_packs` (array of
	 * pack keys) to the languages option (array of handle strings).
	 */
	public static function maybe_migrate_legacy_packs() {
		$sentinel = '__cbe_unset__';
		if ( get_option( self::OPTION, $sentinel ) !== $sentinel ) {
			return;
		}

		$legacy = get_option( self::LEGACY_PACKS_OPTION, $sentinel );
		if ( $sentinel === $legacy ) {
			return;
		}

		$packs = self::packs();
		$langs = array();
		if ( is_array( $legacy ) ) {
			foreach ( $legacy as $pack_key ) {
				if ( ! empty( $packs[ $pack_key ] ) ) {
					$langs = array_merge( $langs, array_keys( $packs[ $pack_key ]['langs'] ) );
				}
			}
		}
		$langs = array_values( array_unique( $langs ) );

		update_option( self::OPTION, $langs );
		delete_option( self::LEGACY_PACKS_OPTION );
	}

	/**
	 * One-shot merge for sites upgrading from v1.0.28 (or earlier,
	 * post-pack-migration). The 9 former-baseline grammars used to be
	 * loaded unconditionally; they're now toggle-able pack entries.
	 * Without this merge, an upgrade would silently strip them from the
	 * editor dropdown and break highlighting on existing code blocks.
	 *
	 * No-op on fresh installs (languages option not set yet — defaults will
	 * already include the baseline) and on sites that have already run
	 * the merge (tracked by a one-shot flag option).
	 */
	public static function maybe_merge_baseline_into_languages() {
		if ( get_option( self::BASELINE_MERGE_FLAG, false ) ) {
			return;
		}

		$sentinel = '__cbe_unset__';
		$current  = get_option( self::OPTION, $sentinel );

		// Fresh install — defaults apply, nothing to merge. Still set the
		// flag so this never re-runs.
		if ( $sentinel === $current ) {
			update_option( self::BASELINE_MERGE_FLAG, true );
			return;
		}

		if ( is_array( $current ) ) {
			$merged = array_values( array_unique( array_merge( $current, self::former_baseline_handles() ) ) );
			if ( $merged !== $current ) {
				update_option( self::OPTION, $merged );
			}
		}

		update_option( self::BASELINE_MERGE_FLAG, true );
	}

	/**
	 * Build the load set: enabled languages + every transitive dep
	 * (whether the dep itself is enabled or not), topologically sorted.
	 *
	 * A user can enable TypeScript while leaving JavaScript unchecked —
	 * javascript still has to load because typescript requires it. Same
	 * for the dep-only grammars (clike, markup-templating) that aren't
	 * pickable but are pulled in by their dependents.
	 *
	 * @return array<string, string[]> Ordered map of handle → requires.
	 */
	public static function active_handles_with_deps() {
		$enabled = self::enabled_languages();
		$key     = 'handles:' . md5( implode( ',', $enabled ) );
		if ( isset( self::$memo[ $key ] ) ) {
			return self::$memo[ $key ];
		}

		$known = self::all_known_grammars();

		$registry = array();
		foreach ( $enabled as $h ) {
			if ( isset( $known[ $h ] ) ) {
				$registry[ $h ] = $known[ $h ];
			}
		}

		// BFS to pull in transitive deps that aren't already in the registry.
		$queue = array_keys( $registry );
		while ( ! empty( $queue ) ) {
			$h    = array_shift( $queue );
			$reqs = isset( $registry[ $h ]['requires'] ) ? $registry[ $h ]['requires'] : array();
			foreach ( $reqs as $r ) {
				if ( ! isset( $registry[ $r ] ) && isset( $known[ $r ] ) ) {
					$registry[ $r ] = $known[ $r ];
					$queue[]        = $r;
				}
			}
		}

		// Topologically sort.
		$order   = array();
		$visited = array();
		foreach ( array_keys( $registry ) as $start ) {
			self::visit( $start, $registry, $visited, $order );
		}

		$out = array();
		foreach ( $order as $h ) {
			$out[ $h ] = $registry[ $h ]['requires'];
		}

		self::$memo[ $key ] = $out;
		return $out;
	}

	public static function active_handles() {
		return array_keys( self::active_handles_with_deps() );
	}

	private static function visit( $handle, $registry, &$visited, &$order ) {
		if ( isset( $visited[ $handle ] ) ) {
			return;
		}
		$visited[ $handle ] = 1;
		$reqs = isset( $registry[ $handle ]['requires'] ) ? $registry[ $handle ]['requires'] : array();
		foreach ( $reqs as $req ) {
			if ( ! isset( $registry[ $req ] ) ) {
				continue;
			}
			self::visit( $req, $registry, $visited, $order );
		}
		$visited[ $handle ] = 2;
		$order[] = $handle;
	}

	/**
	 * { value, label } pairs for the editor dropdown — enabled langs
	 * only, grouped by pack (packs in registry order, langs A-Z within
	 * each pack).
	 */
	public static function active_language_choices() {
		$enabled = self::enabled_languages();
		$key     = 'choices:' . md5( implode( ',', $enabled ) );
		if ( isset( self::$memo[ $key ] ) ) {
			return self::$memo[ $key ];
		}

		$choices = array(
			array( 'value' => '', 'label' => __( 'None (plain text)', 'coywolf-code-block-enhancer' ) ),
		);

		foreach ( self::packs() as $pack ) {
			$pack_labels = array();
			foreach ( $pack['langs'] as $h => $info ) {
				if ( in_array( $h, $enabled, true ) ) {
					$pack_labels[ $h ] = isset( $info['label'] ) ? $info['label'] : $h;
				}
			}
			asort( $pack_labels );
			foreach ( $pack_labels as $h => $label ) {
				$choices[] = array( 'value' => $h, 'label' => $label );
			}
		}

		self::$memo[ $key ] = $choices;
		return $choices;
	}

	public static function active_language_handles() {
		$out = array();
		foreach ( self::active_language_choices() as $c ) {
			if ( '' !== $c['value'] ) {
				$out[] = $c['value'];
			}
		}
		return $out;
	}
}

<?php
/**
 * Language registry for Coywolf Code Block Enhancer.
 *
 * Splits the Prism grammars into a small always-loaded BASELINE (the
 * original 9 languages the plugin shipped with) plus five thematic
 * "packs." The packs are a UI grouping only — what's actually persisted
 * is a flat list of enabled language handles in the `cbe_languages`
 * option, so the admin can toggle any subset of a pack's grammars on
 * or off individually.
 *
 * Pack contents come from PrismJS/prism v1.30.0 components.json. A few
 * languages users sometimes ask about (Vue, Svelte, XML, T-SQL) are
 * intentionally omitted: Prism doesn't ship standalone grammars for
 * Vue or Svelte; XML is just an alias for the baseline `markup`
 * grammar; T-SQL syntax is covered by the baseline `sql` grammar.
 *
 * Public surface:
 *
 *   self::all_packs()              → registry of pack metadata + langs
 *   self::baseline_handles()       → list of always-loaded handles
 *   self::baseline_label_handles() → baseline handles minus the deps-only
 *                                    ones (markup-templating, clike)
 *   self::default_languages()      → handles enabled on first install
 *   self::enabled_languages()      → the admin's saved selection
 *   self::sanitize_languages()     → whitelist sanitiser for the option
 *   self::active_handles_with_deps()
 *                                  → topologically-ordered map of
 *                                    [handle => [requires_handles]] for
 *                                    every grammar currently in play
 *                                    (baseline + enabled selection +
 *                                    transitive deps). Used by the
 *                                    enqueue layer to register each
 *                                    grammar with its real deps.
 *   self::active_language_choices()
 *                                  → { value, label } pairs for the
 *                                    editor dropdown
 *   self::active_language_handles()
 *                                  → flat list of selectable handles
 *                                    (for render_block's allowlist)
 *   self::maybe_migrate_legacy_packs()
 *                                  → one-shot: expand the old
 *                                    `cbe_language_packs` pack-keys
 *                                    array into the new flat
 *                                    `cbe_languages` handles array,
 *                                    then delete the legacy option.
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Language_Packs {

	const OPTION              = 'cbe_languages';
	const LEGACY_PACKS_OPTION = 'cbe_language_packs';

	/**
	 * Baseline grammars — always loaded regardless of selection.
	 * The `label` key is null for deps-only entries (markup-templating,
	 * clike) which shouldn't appear in the editor dropdown.
	 */
	private static $baseline = array(
		'markup'              => array( 'label' => 'HTML / Markup' ),
		'markup-templating'   => array( 'label' => null ), // dep only.
		'clike'               => array( 'label' => null ), // dep only.
		'css'                 => array( 'label' => 'CSS' ),
		'javascript'          => array( 'label' => 'JavaScript' ),
		'bash'                => array( 'label' => 'Bash / Shell' ),
		'json'                => array( 'label' => 'JSON' ),
		'php'                 => array( 'label' => 'PHP' ),
		'python'              => array( 'label' => 'Python' ),
		'sql'                 => array( 'label' => 'SQL' ),
		'yaml'                => array( 'label' => 'YAML' ),
	);

	/**
	 * Optional language packs. Generated from Prism v1.30.0 components.json.
	 */
	private static function packs() {
		return array(
			'web_app'    => array(
				'label'       => __( 'Web / App dev', 'code-block-enhancer' ),
				'description' => __( 'TypeScript, JSX, TSX, SCSS, Sass, Less, GraphQL.', 'code-block-enhancer' ),
				'langs'       => array(
					'typescript' => array( 'label' => 'TypeScript', 'requires' => array( 'javascript' ) ),
					'jsx'        => array( 'label' => 'React JSX',  'requires' => array( 'markup', 'javascript' ) ),
					'tsx'        => array( 'label' => 'React TSX',  'requires' => array( 'jsx', 'typescript' ) ),
					'scss'       => array( 'label' => 'Sass (SCSS)', 'requires' => array( 'css' ) ),
					'sass'       => array( 'label' => 'Sass (Sass)', 'requires' => array( 'css' ) ),
					'less'       => array( 'label' => 'Less',       'requires' => array( 'css' ) ),
					'graphql'    => array( 'label' => 'GraphQL',    'requires' => array() ),
				),
			),
			'backend'    => array(
				'label'       => __( 'Backend languages', 'code-block-enhancer' ),
				'description' => __( 'Go, Rust, Ruby, Java, C, C++, C#, Kotlin, Scala, Swift, Elixir, Erlang, Dart, Lua, Perl, R, Objective-C.', 'code-block-enhancer' ),
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
				'label'       => __( 'Shells / Ops', 'code-block-enhancer' ),
				'description' => __( 'PowerShell, Docker, nginx, Apache Configuration, systemd.', 'code-block-enhancer' ),
				'langs'       => array(
					'powershell' => array( 'label' => 'PowerShell', 'requires' => array() ),
					'docker'     => array( 'label' => 'Docker',     'requires' => array() ),
					'nginx'      => array( 'label' => 'nginx',      'requires' => array() ),
					'apacheconf' => array( 'label' => 'Apache config', 'requires' => array() ),
					'systemd'    => array( 'label' => 'systemd',    'requires' => array() ),
				),
			),
			'data_docs'  => array(
				'label'       => __( 'Data / Docs', 'code-block-enhancer' ),
				'description' => __( 'Markdown, TOML, INI, Diff, Git, Regex. (XML is the baseline HTML / Markup grammar.)', 'code-block-enhancer' ),
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
				'label'       => __( 'DB', 'code-block-enhancer' ),
				'description' => __( 'PL/SQL, Cypher (Neo4j), MongoDB, SPARQL, Turtle. (T-SQL syntax is covered by the baseline SQL grammar.)', 'code-block-enhancer' ),
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

	public static function baseline_handles() {
		return array_keys( self::$baseline );
	}

	/**
	 * Baseline handles that have a label (i.e. user-pickable in the
	 * dropdown). Excludes deps-only entries.
	 */
	public static function baseline_label_handles() {
		$out = array();
		foreach ( self::$baseline as $h => $meta ) {
			if ( null !== $meta['label'] ) {
				$out[ $h ] = $meta['label'];
			}
		}
		return $out;
	}

	/** Default enabled languages on first install = the web_app pack. */
	public static function default_languages() {
		$packs = self::packs();
		return array_keys( $packs['web_app']['langs'] );
	}

	/** Whitelist of every handle in any pack — the universe of valid keys. */
	private static function all_pack_handles() {
		$out = array();
		foreach ( self::packs() as $pack ) {
			$out = array_merge( $out, array_keys( $pack['langs'] ) );
		}
		return $out;
	}

	/** Sanitise a stored value (array of valid handle strings). */
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
		return $out; // empty array is allowed (means "baseline only").
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
	 * One-shot migration from the v1.0.27 `cbe_language_packs` (array of
	 * pack keys) to the new flat `cbe_languages` (array of handle
	 * strings). Runs once on the first admin pageload after upgrade.
	 *
	 * No-ops if the new option already has a stored row, OR if the
	 * legacy option was never persisted.
	 */
	public static function maybe_migrate_legacy_packs() {
		$sentinel = '__cbe_unset__';
		if ( get_option( self::OPTION, $sentinel ) !== $sentinel ) {
			return; // already on the new option.
		}

		$legacy = get_option( self::LEGACY_PACKS_OPTION, $sentinel );
		if ( $sentinel === $legacy ) {
			return; // legacy never set; let default_languages() apply.
		}

		// Expand pack keys into the underlying language handles.
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
	 * Build the union of baseline + enabled languages, walk each entry's
	 * `requires` list to pull in any transitive dep that isn't already
	 * present, then topologically sort. Returns an ordered map of
	 * [handle => [requires_handles]] suitable for wp_register_script.
	 *
	 * @return array<string, string[]>
	 */
	public static function active_handles_with_deps() {
		$registry = array();
		foreach ( self::$baseline as $h => $_meta ) {
			$registry[ $h ] = array( 'requires' => self::baseline_requires( $h ) );
		}

		$enabled = self::enabled_languages();
		foreach ( self::packs() as $pack ) {
			foreach ( $pack['langs'] as $h => $info ) {
				if ( in_array( $h, $enabled, true ) ) {
					$registry[ $h ] = array( 'requires' => isset( $info['requires'] ) ? $info['requires'] : array() );
				}
			}
		}

		// Iterative-safe DFS topo-sort.
		$order   = array();
		$visited = array();
		foreach ( array_keys( $registry ) as $start ) {
			self::visit( $start, $registry, $visited, $order );
		}

		$out = array();
		foreach ( $order as $h ) {
			$out[ $h ] = $registry[ $h ]['requires'];
		}
		return $out;
	}

	/**
	 * Backwards-compatible flat handle list (just the keys of
	 * active_handles_with_deps()).
	 *
	 * @return string[]
	 */
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
				continue; // unknown dep — skip rather than abort.
			}
			self::visit( $req, $registry, $visited, $order );
		}
		$visited[ $handle ] = 2;
		$order[] = $handle;
	}

	private static function baseline_requires( $handle ) {
		$map = array(
			'markup'              => array(),
			'markup-templating'   => array( 'markup' ),
			'clike'               => array(),
			'css'                 => array( 'markup' ),
			'javascript'          => array( 'clike' ),
			'bash'                => array(),
			'json'                => array(),
			'php'                 => array( 'markup-templating' ),
			'python'              => array(),
			'sql'                 => array(),
			'yaml'                => array(),
		);
		return isset( $map[ $handle ] ) ? $map[ $handle ] : array();
	}

	/**
	 * { value, label } pairs for the editor dropdown. Baseline labels
	 * first (A-Z), then each pack's enabled languages (A-Z within the
	 * pack, packs in registry order).
	 */
	public static function active_language_choices() {
		$choices = array(
			array( 'value' => '', 'label' => __( 'None (plain text)', 'code-block-enhancer' ) ),
		);

		$base = self::baseline_label_handles();
		asort( $base );
		foreach ( $base as $h => $label ) {
			$choices[] = array( 'value' => $h, 'label' => $label );
		}

		$enabled = self::enabled_languages();
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

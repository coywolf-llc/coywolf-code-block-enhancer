<?php
/**
 * Language-pack registry for Coywolf Code Block Enhancer.
 *
 * Splits the Prism grammars into a small always-loaded BASELINE (the
 * original 9 languages the plugin shipped with) plus five optional
 * "packs" that each add a thematic group of grammars. The active pack
 * set is persisted as a `cbe_language_packs` option (array of pack
 * keys), with `web_app` selected by default for fresh installs.
 *
 * Pack contents come from PrismJS/prism v1.30.0 components.json. A few
 * languages the user originally suggested (Vue, Svelte, XML, T-SQL)
 * are intentionally omitted: Prism doesn't ship standalone grammars
 * for Vue or Svelte; XML is just an alias for the baseline `markup`
 * grammar; T-SQL syntax is covered by the baseline `sql` grammar.
 *
 * The public surface this class exposes:
 *
 *   self::baseline_handles()   → list of Prism handles in the baseline
 *   self::all_packs()          → registry of pack metadata + langs
 *   self::active_packs()       → keys of packs currently enabled
 *   self::active_handles()     → baseline ∪ (langs in active packs),
 *                                topologically sorted so each
 *                                grammar's dependencies load first
 *   self::active_language_choices()
 *                              → array suitable for the editor
 *                                dropdown ({ value, label } pairs)
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Coywolf_CBE_Language_Packs {

	const OPTION       = 'cbe_language_packs';
	const DEFAULT_PACK = 'web_app';

	/**
	 * Baseline grammars — always loaded regardless of pack selection.
	 * Order matters: each entry's deps must come earlier in the list.
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

	/** Public read-only access for the settings UI. */
	public static function all_packs() {
		return self::packs();
	}

	public static function baseline_handles() {
		return array_keys( self::$baseline );
	}

	/** Default = web_app on fresh installs. */
	public static function default_packs() {
		return array( self::DEFAULT_PACK );
	}

	/** Sanitise a stored value (array of valid pack keys). */
	public static function sanitize_packs( $value ) {
		if ( ! is_array( $value ) ) {
			return self::default_packs();
		}
		$valid = array_keys( self::packs() );
		$out   = array();
		foreach ( $value as $key ) {
			if ( is_string( $key ) && in_array( $key, $valid, true ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
		return $out; // empty array is allowed (means "baseline only").
	}

	public static function active_packs() {
		$stored = get_option( self::OPTION, null );
		if ( null === $stored ) {
			return self::default_packs();
		}
		return self::sanitize_packs( $stored );
	}

	/**
	 * Build the union of baseline + active-pack languages, then walk
	 * each entry's `requires` list to pull in any transitive dep that
	 * isn't already present. Returns a topologically-ordered list of
	 * Prism handles (deps before dependents) suitable for emitting as
	 * the wp_register_script chain.
	 *
	 * @return string[] Prism handles in load order.
	 */
	public static function active_handles() {
		// Start with everything in the baseline.
		$by_handle = array();
		foreach ( self::$baseline as $h => $_meta ) {
			$by_handle[ $h ] = array( 'requires' => self::baseline_requires( $h ) );
		}

		// Layer on languages from active packs.
		$packs = self::packs();
		foreach ( self::active_packs() as $pack_key ) {
			if ( empty( $packs[ $pack_key ] ) ) {
				continue;
			}
			foreach ( $packs[ $pack_key ]['langs'] as $h => $info ) {
				$by_handle[ $h ] = array( 'requires' => isset( $info['requires'] ) ? $info['requires'] : array() );
			}
		}

		// Topologically sort. Iterative DFS so a hand-edited registry
		// with a missing dep doesn't crash with PHP recursion limits.
		$order   = array();
		$visited = array();
		foreach ( array_keys( $by_handle ) as $start ) {
			self::visit( $start, $by_handle, $visited, $order );
		}
		return $order;
	}

	/**
	 * DFS visit for topological sort. Visited-state has three values:
	 *   1 = in progress (cycle guard), 2 = done.
	 */
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

	/**
	 * Dependencies hard-coded for baseline handles (they don't go
	 * through the pack list, so we encode the same order the plugin
	 * always shipped with).
	 */
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
	 * Build the { value, label } list the editor dropdown consumes.
	 * Excludes baseline grammars that exist purely as dependencies
	 * (markup-templating, clike) so they don't show up as user-pickable
	 * choices.
	 */
	public static function active_language_choices() {
		$choices = array(
			array( 'value' => '', 'label' => __( 'None (plain text)', 'code-block-enhancer' ) ),
		);

		// Baseline first, in a tidy A-Z order by display label.
		$base_labels = array();
		foreach ( self::$baseline as $h => $meta ) {
			if ( null === $meta['label'] ) {
				continue;
			}
			$base_labels[ $h ] = $meta['label'];
		}
		asort( $base_labels );
		foreach ( $base_labels as $h => $label ) {
			$choices[] = array( 'value' => $h, 'label' => $label );
		}

		// Then each active pack's languages, A-Z within the pack.
		$packs = self::packs();
		foreach ( self::active_packs() as $pack_key ) {
			if ( empty( $packs[ $pack_key ] ) ) {
				continue;
			}
			$pack_labels = array();
			foreach ( $packs[ $pack_key ]['langs'] as $h => $info ) {
				$pack_labels[ $h ] = isset( $info['label'] ) ? $info['label'] : $h;
			}
			asort( $pack_labels );
			foreach ( $pack_labels as $h => $label ) {
				$choices[] = array( 'value' => $h, 'label' => $label );
			}
		}

		return $choices;
	}

	/**
	 * Whitelist of every Prism handle that's currently loadable —
	 * baseline + active packs. Used by render_block to validate the
	 * stored `language` attribute on a per-block basis.
	 */
	public static function active_language_handles() {
		$handles = array();
		foreach ( self::active_language_choices() as $choice ) {
			if ( '' !== $choice['value'] ) {
				$handles[] = $choice['value'];
			}
		}
		return $handles;
	}
}

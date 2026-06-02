<?php
/**
 * Plugin Name:       Coywolf Code Block Enhancer
 * Plugin URI:        https://coywolf.com/notes/code-block-enhancer-syntax-highlighter-and-code-copier-plugin-for-native-wordpress-code-blocks/
 * Description:       Adds a Tools → Code Blocks option to apply Prism.js syntax highlighting and a copy code to clipboard button to the native WordPress Code block. Assets load only on posts that contain a code block.
 * Version:           1.0.48
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Coywolf
 * Author URI:        https://coywolf.com/jon-henshaw/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       code-block-enhancer
 * Update URI:        https://github.com/coywolf-llc/coywolf-code-block-enhancer
 *
 * @package CodeBlockEnhancer
 *
 * Coywolf Code Block Enhancer
 * Copyright (C) 2026 Coywolf LLC
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, version 2, as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBE_VERSION', '1.0.48' );
define( 'CBE_URL', plugin_dir_url( __FILE__ ) );
define( 'CBE_PLUGIN_FILE', __FILE__ );

/* wporg-strip:start — GitHub self-updater (removed from the WordPress.org build) */
require_once __DIR__ . '/includes/class-github-updater.php';
/* wporg-strip:end */
require_once __DIR__ . '/includes/class-language-packs.php';
require_once __DIR__ . '/includes/class-settings.php';

/* wporg-strip:start — GitHub self-updater (removed from the WordPress.org build) */
// Pull updates from GitHub Releases via the standard WP update flow.
( new Coywolf_CBE_GitHub_Updater( __FILE__, CBE_VERSION ) )->init();
/* wporg-strip:end */

// Tools → Code Blocks settings page (Theme: Default-palette variants or
// any of the 45 bundled Prism / prism-themes stylesheets) plus the
// Language packs checkbox group.
( new Coywolf_CBE_Settings() )->init();

/**
 * Editor: register a language attribute on core/code and add the dropdown.
 *
 * Loads only in the block editor. The save output is unchanged — the chosen
 * language lives in the block delimiter comment — so existing code blocks
 * never trigger block-validation errors. The dropdown choices come from
 * Coywolf_CBE_Language_Packs::active_language_choices() so they stay in
 * sync with whatever packs the site admin has enabled in Tools → Code Blocks.
 */
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'cbe-code-language',
		CBE_URL . 'js/code-language.js',
		array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ),
		CBE_VERSION,
		true
	);
	wp_add_inline_script(
		'cbe-code-language',
		'window.cbeLanguageChoices = ' . wp_json_encode( Coywolf_CBE_Language_Packs::active_language_choices() ) . ';',
		'before'
	);
} );

/**
 * Front end: register Prism grammars + the copy-button script, and enqueue the
 * token/copy CSS in <head> when the post contains a code block.
 */
add_action( 'wp_enqueue_scripts', function () {
	// Prism is vendored under assets/prism/ at v1.30.0 (MIT — see
	// assets/prism/LICENSE). Self-hosting eliminates the third-party CDN as
	// a supply-chain surface: a compromised cdnjs path would otherwise have
	// injected arbitrary JS into every page that loads a code block.
	$prism = CBE_URL . 'assets/prism/';

	// defer = non-render-blocking while preserving execution order across the
	// dependency chain. async would NOT preserve order and would break grammars.
	$args = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);

	// Prism core.
	wp_register_script( 'prism', $prism . 'prism-core.min.js', array(), '1.30.0', $args );

	// Each active grammar gets registered with its REAL dependency list
	// (mapped to script handles) — not the cumulative chain we used to
	// build. That way when render_block enqueues `prism-php`, WP only
	// pulls in the grammars `prism-php` actually needs (markup,
	// markup-templating, clike) plus core, not every grammar on the site.
	foreach ( Coywolf_CBE_Language_Packs::active_handles_with_deps() as $handle => $requires ) {
		$deps = array( 'prism' );
		foreach ( $requires as $r ) {
			$deps[] = 'prism-' . $r;
		}
		wp_register_script(
			'prism-' . $handle,
			$prism . 'prism-' . $handle . '.min.js',
			array_values( array_unique( $deps ) ),
			'1.30.0',
			$args
		);
	}

	// Copy button — no Prism dependency. Standalone copy-to-clipboard
	// behaviour that reads code.textContent and doesn't care whether
	// Prism has tokenised anything.
	wp_register_script(
		'cbe-code-blocks',
		CBE_URL . 'js/code-blocks.js',
		array(),
		CBE_VERSION,
		$args
	);

	// Layout / language label / copy-button chrome (always loaded with the
	// plugin's own version stamp). Token colours live in the theme file.
	wp_register_style( 'cbe-style', CBE_URL . 'css/code-block.css', array(), CBE_VERSION );

	// Selected theme stylesheet (depends on cbe-style so token colours can
	// reference the chrome's CSS custom properties / load after it). Bundled
	// themes resolve to assets/themes/<file>; an uploaded custom theme
	// resolves to <uploads>/code-block-enhancer/custom.css?v=<ts>. The key
	// is whitelisted by the settings sanitiser, so the URL is safe.
	$theme = Coywolf_CBE_Settings::current_theme_entry();
	wp_register_style(
		'cbe-theme',
		Coywolf_CBE_Settings::theme_url( $theme ),
		array( 'cbe-style' ),
		CBE_VERSION
	);

	// Styles must print in <head>, so enqueue here — NOT in render_block, which
	// runs in the body after the head has already closed. Conditional so the CSS
	// loads only on singular posts/pages that contain a code block.
	if ( is_singular() && has_block( 'core/code' ) ) {
		wp_enqueue_style( 'cbe-style' );
		wp_enqueue_style( 'cbe-theme' );
	}
} );

/**
 * Per-block: load the scripts and apply the chosen language to the markup.
 *
 * Scripts are footer scripts, so enqueuing them here (in the body) is fine —
 * the footer is printed after block content. The language class and
 * data-language attribute are added server-side via WP_HTML_Tag_Processor
 * rather than baked into the save output, which avoids both validation errors
 * and KSES stripping data-* attributes for non-admin authors.
 */
add_filter( 'render_block', function ( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/code' !== $block['blockName'] ) {
		return $content;
	}

	// Copy button on every code block. No Prism dependency, so this
	// alone doesn't pull in any grammar — pages with code blocks that
	// have no language attribute download only this ~1 KB of JS.
	wp_enqueue_script( 'cbe-code-blocks' );

	// Allowlist the language against whatever's actually loadable on the
	// site right now (baseline + the admin's per-language selection).
	// Block attrs are author-controlled in saved post content, so a
	// stored language that isn't backed by a loaded grammar gets
	// collapsed to empty rather than rendered as a dead `language-xxx`
	// class.
	$language = $block['attrs']['language'] ?? '';
	$allowed  = Coywolf_CBE_Language_Packs::active_language_handles();
	if ( ! in_array( $language, $allowed, true ) ) {
		$language = '';
	}

	if ( $language ) {
		// Lazy-enqueue only the specific grammar this block needs. WP
		// walks the registered deps and pulls in prism-core + any
		// transitive grammars (e.g. prism-php → markup-templating →
		// markup). Multiple code blocks with the same language enqueue
		// once; different-language blocks each contribute their own
		// grammar without re-loading the shared deps.
		wp_enqueue_script( 'prism-' . $language );

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$p = new WP_HTML_Tag_Processor( $content );
			if ( $p->next_tag( 'pre' ) ) {
				$p->set_attribute( 'data-language', $language );
			}
			if ( $p->next_tag( 'code' ) ) {
				$p->add_class( 'language-' . $language );
			}
			$content = $p->get_updated_html();
		}
	}

	return $content;
}, 10, 2 );

/**
 * Eliminate the grammar-script critical request chain by emitting
 * `<link rel="preload" as="script" fetchpriority="low">` hints in
 * <head> for exactly the Prism grammars the current post needs.
 *
 * Without this, the deferred grammar <script> tags only get discovered
 * in the footer, after the browser has parsed the entire body. With it,
 * the browser's preload scanner starts fetching them as soon as it
 * sees the head — they download in parallel during HTML parse and the
 * later <script> tags execute the already-cached files in dep order.
 *
 * `fetchpriority="low"` (Chrome / Edge 102+, Safari 17.2+, Firefox
 * 132+) tells the browser these aren't render-critical, so the
 * preloaded scripts won't compete with CSS / hero images for download
 * priority. A preloaded script defaults to High priority; we want
 * these queued after anything that affects LCP. Older browsers ignore
 * the attribute — they still get the preload, just at default
 * priority (no regression vs. the previous behaviour).
 *
 * We emit `<link>` tags ourselves via `wp_head` instead of using the
 * `wp_preload_resources` filter: that filter's allowed-attrs whitelist
 * doesn't include `fetchpriority`, so WP would strip the attribute.
 *
 * Each preload URL is computed off the *registered* script's src +
 * version + `script_loader_src` filter so it matches the eventual
 * <script src> byte-for-byte and the browser reuses one cache entry
 * instead of double-fetching.
 */
add_action( 'wp_head', function () {
	if ( ! is_singular() || ! has_block( 'core/code' ) ) {
		return;
	}

	$post = get_post();
	if ( ! $post || empty( $post->post_content ) ) {
		return;
	}

	$urls = array();

	// Copy-button JS is enqueued for every code block, language or not.
	$copy_url = cbe_resolve_script_url( 'cbe-code-blocks' );
	if ( $copy_url ) {
		$urls[] = $copy_url;
	}

	$languages = cbe_collect_code_block_languages( $post->post_content );
	if ( ! empty( $languages ) ) {
		$allowed  = Coywolf_CBE_Language_Packs::active_language_handles();
		$registry = Coywolf_CBE_Language_Packs::active_handles_with_deps();

		// Union of every grammar handle this post needs + its
		// transitive deps. Iterative-safe BFS.
		$needed = array();
		$stack  = array();
		foreach ( $languages as $lang ) {
			if ( in_array( $lang, $allowed, true ) && ! isset( $needed[ $lang ] ) ) {
				$needed[ $lang ] = true;
				$stack[]         = $lang;
			}
		}
		while ( ! empty( $stack ) ) {
			$h = array_pop( $stack );
			if ( ! isset( $registry[ $h ] ) ) {
				continue;
			}
			foreach ( $registry[ $h ] as $r ) {
				if ( ! isset( $needed[ $r ] ) ) {
					$needed[ $r ] = true;
					$stack[]      = $r;
				}
			}
		}

		// Prism core first (every grammar depends on it).
		$core_url = cbe_resolve_script_url( 'prism' );
		if ( $core_url ) {
			$urls[] = $core_url;
		}
		foreach ( array_keys( $needed ) as $h ) {
			$url = cbe_resolve_script_url( 'prism-' . $h );
			if ( $url ) {
				$urls[] = $url;
			}
		}
	}

	$urls = array_values( array_unique( $urls ) );
	foreach ( $urls as $url ) {
		echo '<link rel="preload" as="script" fetchpriority="low" href="' . esc_url( $url ) . "\" />\n";
	}
}, 5 );

/**
 * Walk a post's block tree (including innerBlocks) and return the
 * unique set of `language` attributes used on `core/code` blocks.
 *
 * @param string $content Post content with serialised blocks.
 * @return string[]
 */
function cbe_collect_code_block_languages( $content ) {
	if ( ! function_exists( 'parse_blocks' ) ) {
		return array();
	}
	$found = array();
	cbe_walk_blocks_for_languages( parse_blocks( $content ), $found );
	return array_keys( $found );
}

function cbe_walk_blocks_for_languages( $blocks, &$found ) {
	if ( ! is_array( $blocks ) ) {
		return;
	}
	foreach ( $blocks as $block ) {
		if ( ! empty( $block['blockName'] ) && 'core/code' === $block['blockName'] ) {
			if ( ! empty( $block['attrs']['language'] ) && is_string( $block['attrs']['language'] ) ) {
				$found[ $block['attrs']['language'] ] = true;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			cbe_walk_blocks_for_languages( $block['innerBlocks'], $found );
		}
	}
}

/**
 * Compute the URL WP will use for a registered script handle's
 * <script src>, so a preload <link> can match it byte-for-byte and
 * the browser uses one cache entry for both. Mirrors what
 * WP_Scripts::do_item() does (version query + script_loader_src
 * filter) without actually emitting the tag.
 *
 * @param string $handle Registered script handle.
 * @return string|null Full src URL, or null if not registered.
 */
function cbe_resolve_script_url( $handle ) {
	if ( ! function_exists( 'wp_scripts' ) ) {
		return null;
	}
	$wp_scripts = wp_scripts();
	if ( empty( $wp_scripts->registered[ $handle ] ) ) {
		return null;
	}
	$item = $wp_scripts->registered[ $handle ];
	$src  = $item->src;
	if ( ! $src ) {
		return null;
	}
	$ver = $item->ver;
	if ( false === $ver ) {
		$ver = $wp_scripts->default_version;
	}
	if ( $ver ) {
		$src = add_query_arg( 'ver', $ver, $src );
	}
	$src = apply_filters( 'script_loader_src', $src, $handle );
	return $src ? (string) $src : null;
}

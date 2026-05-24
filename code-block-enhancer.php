<?php
/**
 * Plugin Name:       Code Block Enhancer
 * Plugin URI:        https://coywolf.com/
 * Description:       Adds a language selector to the core Code block, Prism.js syntax highlighting with a custom token palette, and a copy-to-clipboard button. Assets load only on posts that contain a code block.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Jon Henshaw
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       code-block-enhancer
 *
 * @package CodeBlockEnhancer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBE_VERSION', '1.0.0' );
define( 'CBE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Editor: register a language attribute on core/code and add the dropdown.
 *
 * Loads only in the block editor. The save output is unchanged — the chosen
 * language lives in the block delimiter comment — so existing code blocks
 * never trigger block-validation errors.
 */
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'cbe-code-language',
		CBE_URL . 'js/code-language.js',
		array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ),
		CBE_VERSION,
		true
	);
} );

/**
 * Front end: register Prism grammars + the copy-button script, and enqueue the
 * token/copy CSS in <head> when the post contains a code block.
 */
add_action( 'wp_enqueue_scripts', function () {
	$prism = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.30.0/components/';

	// defer = non-render-blocking while preserving execution order across the
	// dependency chain. async would NOT preserve order and would break grammars.
	$args = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);

	// Core first, then grammars. Each depends on the previous handle so they
	// load in order (markup-templating before php; clike before languages that
	// extend it). Add more grammars here and to the editor dropdown to support
	// additional languages.
	$chain = array(
		'prism'                   => 'prism-core.min.js',
		'prism-markup'            => 'prism-markup.min.js',
		'prism-markup-templating' => 'prism-markup-templating.min.js', // required by php
		'prism-clike'             => 'prism-clike.min.js',
		'prism-css'               => 'prism-css.min.js',
		'prism-javascript'        => 'prism-javascript.min.js',
		'prism-bash'              => 'prism-bash.min.js',
		'prism-json'              => 'prism-json.min.js',
		'prism-php'               => 'prism-php.min.js',
		'prism-python'            => 'prism-python.min.js',
		'prism-sql'               => 'prism-sql.min.js',
		'prism-yaml'              => 'prism-yaml.min.js',
	);
	$prev = array();
	foreach ( $chain as $handle => $file ) {
		wp_register_script( $handle, $prism . $file, $prev, '1.30.0', $args );
		$prev = array( $handle ); // next grammar depends on this one
	}

	// Copy button — depends on the last grammar, so all Prism is present first.
	wp_register_script(
		'cbe-code-blocks',
		CBE_URL . 'js/code-blocks.js',
		array( array_key_last( $chain ) ),
		CBE_VERSION,
		$args
	);

	// Token palette + copy-button styling.
	wp_register_style( 'cbe-style', CBE_URL . 'css/code-block.css', array(), CBE_VERSION );

	// Styles must print in <head>, so enqueue here — NOT in render_block, which
	// runs in the body after the head has already closed. Conditional so the CSS
	// loads only on singular posts/pages that contain a code block.
	if ( is_singular() && has_block( 'core/code' ) ) {
		wp_enqueue_style( 'cbe-style' );
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

	wp_enqueue_script( 'cbe-code-blocks' ); // pulls in core + all grammars

	$language = $block['attrs']['language'] ?? '';
	if ( $language && class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$p = new WP_HTML_Tag_Processor( $content );
		if ( $p->next_tag( 'pre' ) ) {
			$p->set_attribute( 'data-language', $language );
		}
		if ( $p->next_tag( 'code' ) ) {
			$p->add_class( 'language-' . $language );
		}
		$content = $p->get_updated_html();
	}

	return $content;
}, 10, 2 );

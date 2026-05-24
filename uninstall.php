<?php
/**
 * Uninstall cleanup for Coywolf Code Block Enhancer.
 *
 * Runs when the user deletes the plugin from WP Admin → Plugins. Removes
 * the persistent state the plugin creates:
 *   - GitHub-updater release cache (site transients).
 *   - cbe_theme_mode option (Tools → Code Blocks setting).
 *
 * The per-block `language` attribute lives inside post content (block
 * delimiter comment) and is intentionally preserved.
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_transient( 'coywolf_cbe_gh_release' );
delete_site_transient( 'coywolf_cbe_gh_release_neg' );

delete_option( 'cbe_theme_mode' );

<?php
/**
 * Uninstall cleanup for Coywolf Code Block Enhancer.
 *
 * Runs when the user deletes the plugin from WP Admin → Plugins. Removes
 * the persistent state the plugin creates:
 *   - GitHub-updater release cache (site transients).
 *   - cbe_theme option (Tools → Code Blocks setting).
 *   - cbe_theme_mode option (legacy pre-theme-picker setting, normally
 *     auto-migrated and deleted on first admin pageload after upgrade,
 *     but cleaned up here in case the plugin is deleted before that
 *     migration runs).
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

delete_option( 'cbe_theme' );
delete_option( 'cbe_theme_mode' );

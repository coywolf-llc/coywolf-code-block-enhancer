<?php
/**
 * Uninstall cleanup for Coywolf Code Block Enhancer.
 *
 * Runs when the user deletes the plugin from WP Admin → Plugins. Removes
 * the only persistent state the plugin creates: the GitHub-updater
 * release cache transients. The plugin stores no other options, and the
 * per-block `language` attribute lives inside post content (block
 * delimiter comment) and is intentionally preserved.
 *
 * @package CodeBlockEnhancer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_transient( 'coywolf_cbe_gh_release' );
delete_site_transient( 'coywolf_cbe_gh_release_neg' );

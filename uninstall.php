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
 *   - cbe_custom_theme option (custom-theme upload metadata).
 *   - The uploaded custom-theme file (and its directory if empty) under
 *     wp-content/uploads/code-block-enhancer/.
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
delete_option( 'cbe_custom_theme' );

// Remove uploaded custom theme file (and the .htaccess hint), then try
// to remove the now-empty directory. rmdir() is a no-op if the dir has
// other contents — safe.
$u = wp_upload_dir( null, false );
if ( is_array( $u ) && ! empty( $u['basedir'] ) ) {
	$dir = trailingslashit( $u['basedir'] ) . 'code-block-enhancer/';
	foreach ( array( 'custom.css', '.htaccess' ) as $name ) {
		$path = $dir . $name;
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}
	if ( is_dir( $dir ) ) {
		@rmdir( $dir );
	}
}

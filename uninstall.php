<?php
/**
 * Uninstall cleanup for Coywolf Code Block Enhancer.
 *
 * Runs when the user deletes the plugin from WP Admin → Plugins. Removes
 * the persistent state the plugin creates:
 *   - GitHub-updater release cache (site transients).
 *   - coywolf_cbe_theme option (Tools → Code Blocks setting).
 *   - coywolf_cbe_custom_theme option (custom-theme upload metadata) and
 *     coywolf_cbe_custom_theme_css option (the stored stylesheet text).
 *   - coywolf_cbe_languages option (enabled grammars) and the one-shot
 *     migration flags.
 *   - Every pre-1.0.55 short-prefix (`cbe_*`) option, in case the plugin
 *     is deleted before the prefix migration ran.
 *   - The legacy on-disk custom theme (pre-1.0.55 file storage) under
 *     wp-content/uploads/code-block-enhancer/, if it still exists.
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

// Current option names.
delete_option( 'coywolf_cbe_theme' );
delete_option( 'coywolf_cbe_custom_theme' );
delete_option( 'coywolf_cbe_custom_theme_css' );
delete_option( 'coywolf_cbe_languages' );
delete_option( 'coywolf_cbe_baseline_merged_v1' );
delete_option( 'coywolf_cbe_prefix_migrated_v1' );
delete_option( 'coywolf_cbe_custom_theme_removed_v1' );

// Legacy (≤1.0.54) names — short prefix and pre-migration options.
delete_option( 'cbe_theme' );
delete_option( 'cbe_theme_mode' );
delete_option( 'cbe_custom_theme' );
delete_option( 'cbe_language_packs' );
delete_option( 'cbe_languages' );
delete_option( 'cbe_baseline_merged_v1' );

// Remove the legacy on-disk custom theme file (and the .htaccess hint),
// then try to remove the now-empty directory. Current versions store the
// custom theme in the database, so this only matters for installs that
// never ran the 1.0.55 migration. rmdir() is a no-op if the dir has
// other contents — safe.
$coywolf_cbe_uploads = wp_upload_dir( null, false );
if ( is_array( $coywolf_cbe_uploads ) && ! empty( $coywolf_cbe_uploads['basedir'] ) ) {
	$coywolf_cbe_dir = trailingslashit( $coywolf_cbe_uploads['basedir'] ) . 'code-block-enhancer/';
	foreach ( array( 'custom.css', '.htaccess' ) as $coywolf_cbe_name ) {
		$coywolf_cbe_path = $coywolf_cbe_dir . $coywolf_cbe_name;
		if ( file_exists( $coywolf_cbe_path ) ) {
			wp_delete_file( $coywolf_cbe_path );
		}
	}
	if ( is_dir( $coywolf_cbe_dir ) ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( WP_Filesystem() ) {
			$wp_filesystem->rmdir( $coywolf_cbe_dir );
		}
	}
}

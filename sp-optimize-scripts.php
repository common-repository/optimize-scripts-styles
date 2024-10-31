<?php
/**
 * Plugin Name: Optimize Scripts & Styles
 * Plugin URI: https://www.seismicpixels.com/optimize-scripts-styles-for-wordpress/
 * Description: Provides a quick way to combine and minify all scripts and styles and cache them in your content folder.
 * Version: 1.9.5
 * Author: Seismic Pixels
 * Author URI: https://www.seismicpixels.com
 * Copyright 2024 Sean Michaud - Seismic Pixels, LLC
*/

global $spos_version;
$spos_version = '1.9.5';

global $spos_settings;
$spos_settings = get_option( 'spos_settings' );

function spos_init() {
	global $spos_settings;

	if ( is_admin() ) {
		// on an admin page
		require_once('library/admin.php');
	}

	if ( !is_admin() && !is_login() ) {
		// not on an admin page		
		if ( !is_user_logged_in() || isset( $spos_settings['enable_logged_in'] ) && $spos_settings['enable_logged_in'] == 1 ) {
			require_once('library/functions.php');
			add_action( 'wp_footer', 'spos_footer_message', 99 );
		} else {
			add_action( 'wp_footer', 'spos_logged_in_message', 99 );
		}
	}
}
if ( !wp_doing_ajax() ) {
	add_action( 'init', 'spos_init', 100 );
}

function spos_activation_hook() {
	set_transient('spos-activation', true, 5 );
	spos_clear_cache();	
}
register_activation_hook( __FILE__, 'spos_activation_hook' );

function spos_deactivation_hook() {
	spos_clear_cache();
}
register_deactivation_hook( __FILE__, 'spos_deactivation_hook' );

function spos_activation_notice() {
	if ( get_transient('spos-activation') ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>Optimize Scripts &amp; Styles activated! Click <a href="<?php echo admin_url('options-general.php?page=spos'); ?>">Optimization</a> to configure.</p>
		</div>
		<?php
		delete_transient( 'spos-activation' );
	}
}
add_action( 'admin_notices', 'spos_activation_notice' );

function spos_clear_cache() {
	$styles = glob( WP_CONTENT_DIR . '/cache/styles/*' );
	foreach ( $styles as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		} 
	}
	$scripts = glob( WP_CONTENT_DIR . '/cache/scripts/*' );
	foreach ( $scripts as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		} 
	}
	
	if ( function_exists( 'w3tc_pgcache_flush' ) ) {
		// clear W3 Total Cache
		w3tc_pgcache_flush();
	}
	elseif ( function_exists( 'wp_cache_clear_cache' ) ) {
		// clear WP Super Cache
		wp_cache_clear_cache();
	}
}

function spos_logged_in_message() {
	echo "\r\n" . '<!-- Optimized scripts disabled for logged in users -->' . "\r\n";
}

function spos_footer_message() {
	echo "\r\n" . '<!-- Optimize Scripts & Styles by Seismic Pixels -->' . "\r\n";
}

?>
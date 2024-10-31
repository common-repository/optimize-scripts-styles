<?php

/* The following functions were created to speed up the site. W3 Total Cache minification
   does not work, so this is an alternative. The W3 empty cache function will empty this
   cache as well. It has since been upgraded to work with WP Super Cache.
 */

/* INCLUDES */
$path = dirname(__FILE__);
require_once( $path . '/classes/SPOS.php' );

use SPOS\Classes\SPOS;

/* SETTINGS */
global $spos_settings;
$remove_scripts = isset( $spos_settings['remove_scripts'] ) && $spos_settings['remove_scripts'] !== '' ? true : false;
$remove_styles = isset( $spos_settings['remove_styles'] ) && $spos_settings['remove_styles'] !== '' ? true : false;
$optimize_scripts = isset( $spos_settings['optimize_scripts'] ) && $spos_settings['optimize_scripts'] == 1 ? true : false;
$optimize_styles = isset( $spos_settings['optimize_styles'] ) && $spos_settings['optimize_styles'] == 1 ? true : false;
$remove_script_type = isset( $spos_settings['remove_script_type'] ) && $spos_settings['remove_script_type'] == 1 ? true : false;
$remove_style_type = isset( $spos_settings['remove_style_type'] ) && $spos_settings['remove_style_type'] == 1 ? true : false;

$spos = new SPOS();

/* ACTIONS & FILTERS */
if ( $remove_scripts ) {
	add_action( 'wp_print_scripts', 'spos_dequeue_scripts', 98 );
	add_action( 'wp_footer', 'spos_dequeue_scripts', 18 ); // some additional scripts may be added after wp_print_scripts
}

if ( $remove_styles ) {
	add_action( 'wp_print_styles', 'spos_dequeue_styles', 98 );
}

if ( $optimize_scripts ) {
	add_action( 'wp_print_scripts', [ $spos, 'process_head_scripts' ], 99 ); // header scripts are all enqueued here
	add_action( 'wp_footer', [ $spos, 'process_footer_scripts' ], 19 ); // enqueued scripts are executed at 20
}

if ( $optimize_styles ) {
	add_action( 'wp_print_styles', [ $spos, 'process_styles' ], 99 );
	//add_action( 'enqueue_block_assets', [ $spos, 'process_styles' ], 99 );
	//add_action( 'wp_print_late_styles', [ $spos, 'process_styles' ], 99 );
	//add_action( 'wp_enqueue_scripts', [ $spos, 'process_styles' ], 9999 );
}

if ( $remove_script_type ) {
	// remove type='text/javascript' in localized data
	if ( !$optimize_scripts ) {
		// only do this if scripts aren't being optimized already since data is pulled out via
		// the optimization function anyway
		add_action( 'wp_loaded', function() {
			// this adds lots of extra junk... not an ideal solution, but there are a lack of
			// hooks to accomplish this
			global $wp_scripts;
			$wp_scripts = new SPOS_Scripts;
		});
	}

	// remove the type='text/javascript' tags that are added by WordPress
	add_filter( 'script_loader_tag', 'spos_remove_tag_type', 10, 2 );	
}

if ( $remove_style_type ) {
	// remove the type='text/css' tags that are added by WordPress
	add_filter( 'style_loader_tag', 'spos_remove_tag_type', 10, 2 );	
}

/**************************
  REMOVE SCRIPT TYPE
**************************/

/**
 * Extend WP_Scripts - only used if $optimize_scripts == false and $remove_script_type == true
 */
class SPOS_Scripts extends WP_Scripts {

	/**
	 * Override print_extra_script from class.wp-scripts.php line 198
	 * @param string $handle
	 * @param bool $echo
	 * @return string
	 */
	public function print_extra_script( $handle, $echo = true ) {
		if ( !$output = $this->get_data( $handle, 'data' ) )
			return;

		if ( !$echo )
			return $output;

		echo "<script>\n"; // CDATA and type='text/javascript' is not needed for HTML 5 - REMOVED!
		echo "/* <![CDATA[ */\n";
		echo "$output\n";
		echo "/* ]]> */\n";
		echo "</script>\n";

		return true;
	}
}

/**
 * Remove the type= attribute from <script> and <style> tags
 * @param string $tag
 * @param string $handle
 * @return string
 */
function spos_remove_tag_type( $tag, $handle ) {
	return preg_replace( "/\stype=['\"]text\/(javascript|css)['\"]/", '', $tag );
}

/**************************
 REMOVE SCRIPTS
**************************/
function spos_dequeue_scripts() {
	global $spos_settings;
	$remove_setting = sanitize_text_field( $spos_settings['remove_scripts'] );
	if ( $remove_setting !== '' ) {
		// separate into an array
		$remove_arr = explode( ',', $remove_setting );
		foreach( $remove_arr as $remove_handle ) {
			// remove the script from the queue
			wp_dequeue_script( trim( $remove_handle ) );
		}
	}

}

/**************************
 REMOVE STYLES
**************************/

function spos_dequeue_styles() {
	global $spos_settings;
	$remove_setting = sanitize_text_field( $spos_settings['remove_styles'] );
	if ( $remove_setting !== '' ) {
		// separate into an array
		$remove_arr = explode( ',', $remove_setting );
		foreach( $remove_arr as $remove_handle ) {
			// remove the style from the queue
			wp_dequeue_style( trim( $remove_handle ) );
		}
	}
}
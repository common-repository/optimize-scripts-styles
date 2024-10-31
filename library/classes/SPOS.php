<?php

namespace SPOS\Classes;

/* INCLUDES */
$path = dirname(__DIR__, 1);
require_once( $path . '/includes/minify/src/Minify.php' );
require_once( $path . '/includes/minify/src/CSS.php' );
require_once( $path . '/includes/minify/src/JS.php' );
require_once( $path . '/includes/minify/src/Exception.php' );
require_once( $path . '/includes/minify/src/Exceptions/BasicException.php' );
require_once( $path . '/includes/minify/src/Exceptions/FileImportException.php' );
require_once( $path . '/includes/minify/src/Exceptions/IOException.php' );
require_once( $path . '/includes/path-converter/src/ConverterInterface.php' );
require_once( $path . '/includes/path-converter/src/Converter.php' );
 
use MatthiasMullie\Minify;

class SPOS {

    private $blog_url;
    private $cache_lifespan;    
    private $ignore_scripts;
    private $ignore_styles;
    private $remove_script_type;
    private $disable_cache;
    private $debug;
    private $minify;
    private $requires_jquery;

    public function __construct()
    {
        global $spos_settings;

        $this->blog_url = get_bloginfo( 'url' );
        $this->cache_lifespan = $this->get_cache_lifespan();
        $this->ignore_scripts = $this->get_ignored_scripts( ['jquery','jquery-core','jquery-migrate'] );
        $this->ignore_styles = $this->get_ignored_styles();    
        
        $this->remove_script_type = isset( $spos_settings['remove_script_type'] ) && $spos_settings['remove_script_type'] == 1 ? true : false;
        $this->disable_cache = isset( $spos_settings['disable_cache'] ) && $spos_settings['disable_cache'] == 1 ? true : false;
        $this->debug = isset( $spos_settings['display_debug'] ) && $spos_settings['display_debug'] == 1 ? true : false;
        $this->minify = ( !isset( $spos_settings['optimize_behavior'] ) || isset( $spos_settings['optimize_behavior'] ) && $spos_settings['optimize_behavior'] == 'minify' ) ? true : false;

        // create the scripts cache directory if it doesn't exist
        if ( !file_exists( WP_CONTENT_DIR . '/cache/scripts' ) ) {
            mkdir( WP_CONTENT_DIR . '/cache/scripts', 0755, true );
        }

        // create the styles cache directory if it doesn't exist
        if ( !file_exists( WP_CONTENT_DIR . '/cache/styles' ) ) {
            mkdir( WP_CONTENT_DIR . '/cache/styles', 0755, true );
        }
    }

    /**************************
     PROCESS HEADER SCRIPTS
    **************************/

    public function process_head_scripts()
    {        
        global $wp_scripts, $spos_settings;
        $start_time = microtime(true);

        // find scripts meant to be loaded in the header
        $header_scripts = [];
        $included_scripts = [];
        
        foreach ( $wp_scripts->queue as $handle ) {
            // does the file end in .js?
            // this helps to eliminate problems with php files masquerading as javascript
            if ( substr( $wp_scripts->registered[$handle]->src, -3 ) !== '.js' ) continue;

            $footer = isset( $wp_scripts->registered[$handle]->extra['group'] ) && $wp_scripts->registered[$handle]->extra['group'] == 1 ? true : false;
            if ( !$footer &&
                !in_array( $handle, $this->ignore_scripts ) && 
                !in_array( $handle, $wp_scripts->done ) &&
                isset( $wp_scripts->registered[$handle] ) &&
                $this->is_internal_src( $wp_scripts->registered[$handle]->src )
            ) {
                // keep track of the handles
                $header_scripts[] = $handle;                
            } // end if
        } // end foreach

        // keep track of inline data
        $inline_data = $this->get_inline_data( $header_scripts );
        
        $md5 = md5( implode( ':', $header_scripts ) );
        $script_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5;
        $flag_path = $script_path . '.txt';
        
        // check if the file exists and is less than $this->cache_lifespan old
        if ( file_exists( $flag_path ) && filemtime( $flag_path ) > ( time() - $this->cache_lifespan ) && !$this->disable_cache ) {
            // the files have already been created. remove original scripts and register the existing optimized files
            
            // remove existing scripts since they are included in the stored files
            foreach ( $header_scripts as $handle ) {
                $wp_scripts->done[] = $handle;
                wp_dequeue_script( $handle );
            }
            
            // register & enqueue the exising scripts
            $script_types = $this->get_script_types( $header_scripts );
            foreach( $script_types as $k ) {

                $script_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5 . '.' . $k . '.header.js';
                $script_url = WP_CONTENT_URL . '/cache/scripts/' . $md5 . '.' . $k . '.header.js';

                if ( file_exists( $script_path ) ) {
                    // if jQuery isn't required here, WordPress will move it to the footer
                    $deps = $this->requires_jquery ? ['jquery'] : []; //@todo: make this a little more robust by checking all ignored & external dependencies
                    $strategy = $k == 'base' ? false : $k;
                    $args = $strategy ? [ 'in_footer' => false, 'strategy' => $strategy ] : false;
                    wp_register_script( 'spos-' . $k . '-header', $script_url, $deps, filemtime( $script_path ), $args );
                    wp_enqueue_script( 'spos-' . $k . '-header' );

                    if ( isset( $inline_data[$k]['before'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-header', implode( "\r\n", $inline_data[$k]['before'] ), 'before' );
                    }

                    if ( isset( $inline_data[$k]['after'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-header', implode( "\r\n", $inline_data[$k]['after'] ) );
                    }
                }
                
            }
                    
        } else {
            // there are no existing files, so let's create them

            $scripts_content = $this->get_scripts_content( $header_scripts, $included_scripts );
            
            if ( !empty( $scripts_content ) ) {

                foreach( $scripts_content as $k => $v ) {
                
                    // scripts file
                    $script_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5 . '.' . $k . '.header.js';
                    $script_url = WP_CONTENT_URL . '/cache/scripts/' . $md5 . '.' . $k . '.header.js';

                    $buffer = implode( '', $v['content'] );

                    if ( $this->minify ) {
                        // Minify!						
                        $minifier = new Minify\JS( $v['content'] );
                        $buffer = $minifier->minify();
                    }

                    $script_file = fopen( $script_path, 'w' );
                    fwrite( $script_file, $buffer );
                    fclose( $script_file );

                    // if jQuery isn't required here, WordPress will move it to the footer
                    $deps = $this->requires_jquery ? ['jquery'] : []; //@todo: make this a little more robust by checking all ignored & external dependencies
                    $strategy = $k == 'base' ? false : $k;
                    $args = $strategy ? [ 'in_footer' => false, 'strategy' => $strategy ] : false;
                    wp_register_script( 'spos-' . $k . '-header', $script_url, $deps, filemtime( $script_path ), $args );
                    wp_enqueue_script( 'spos-' . $k . '-header' );

                    if ( isset( $inline_data[$k]['before'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-header', implode( "\r\n", $inline_data[$k]['before'] ), 'before' );
                    }

                    if ( isset( $inline_data[$k]['after'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-header', implode( "\r\n", $inline_data[$k]['after'] ) );
                    }

                }
            
                // flag file
                $end_time = microtime(true);
                $execution_time = ( $end_time - $start_time );
                $gmt_offset = get_option('gmt_offset');
                $info = 'Cached ' . date('l jS \of F Y h:i:s A', time() + ( $gmt_offset * 60 * 60 ) ) . "\r\n";
                    $info .= 'Initial ' . ucfirst( get_post_type() ) . ': ' . get_the_title() . "\r\n";
                    $info .= 'Location: Header' . "\r\n";
                    $info .= 'Includes: ' . implode( ', ', $included_scripts ) . "\r\n";
                    $info .= 'Execution time: ' . $execution_time . ' seconds';

                //$flag_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5 . '.txt';
                $flag_file = fopen( $flag_path, 'w' );
                fwrite( $flag_file, $info );
                fclose( $flag_file );

                if ( $this->debug ) {
                    echo '<pre>' . $info . '</pre>';
                }
            
            }
        }
        
    }

    /**************************
     PROCESS FOOTER SCRIPTS
    **************************/

    public function process_footer_scripts()
    {
        global $wp_scripts, $spos_settings;        
        $start_time = microtime(true);

        $footer_scripts = [];
        $included_scripts = [];

        foreach ( $wp_scripts->queue as $handle ) {
            // does the file end in .js?
            // this helps to eliminate problems with php files masquerading as javascript
            if ( substr( $wp_scripts->registered[$handle]->src, -3 ) !== '.js' ) continue;
            
            if ( !in_array( $handle, $this->ignore_scripts ) && 
                !in_array( $handle, $wp_scripts->done ) &&
                isset( $wp_scripts->registered[$handle] ) && 
                ( strpos( $wp_scripts->registered[$handle]->src, $this->blog_url ) !== false ||
                strpos( $wp_scripts->registered[$handle]->src, 'wp-includes' ) !== false )
            ) {
                $footer_scripts[] = $handle;
            }
        }

        // keep track of inline data
        $inline_data = $this->get_inline_data( $footer_scripts );
        
        $md5 = md5( implode( ':', $footer_scripts ) ); // some elements in the queue may already be done
        $script_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5;
        $flag_path = $script_path . '.txt';
        
        // check if the file exists and is less than $this->cache_lifespan old
        if ( file_exists( $flag_path ) && filemtime( $flag_path ) > ( time() - $this->cache_lifespan ) && !$this->disable_cache ) {
            // the files have already been created. register the existing files
            
            // remove existing scripts since they are included in the stored files
            foreach ( $footer_scripts as $handle ) {
                if ( !in_array( $handle, $this->ignore_scripts ) && strpos( $wp_scripts->registered[$handle]->src, $this->blog_url ) !== false ) {
                    wp_dequeue_script( $handle );
                    $wp_scripts->done[] = $handle;
                }
            }
            
            // register & enqueue the exising scripts
            $script_types = $this->get_script_types( $footer_scripts );
            foreach( $script_types as $k ) {

                $script_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5 . '.' . $k . '.footer.js';
                $script_url = WP_CONTENT_URL . '/cache/scripts/' . $md5 . '.' . $k . '.footer.js';

                if ( file_exists( $script_path ) ) {
                    $deps = $this->requires_jquery ? ['jquery'] : []; //@todo: make this a little more robust by checking all ignored & external dependencies
                    $strategy = $k == 'base' ? false : $k;
                    $args = $strategy ? [ 'in_footer' => true, 'strategy' => $strategy ] : true;
                    wp_register_script( 'spos-' . $k . '-footer', $script_url, $deps, filemtime( $script_path ), true );
                    wp_enqueue_script( 'spos-' . $k . '-footer' );

                    if ( isset( $inline_data[$k]['before'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-footer', implode( "\r\n", $inline_data[$k]['before'] ), 'before' );
                    }

                    if ( isset( $inline_data[$k]['after'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-footer', implode( "\r\n", $inline_data[$k]['after'] ) );
                    }
                }
            }
                    
        } else {
            // there are no existing files, so let's create them		
            
            $scripts_content = $this->get_scripts_content( $footer_scripts, $included_scripts );
            
            if ( !empty( $scripts_content ) ) {

                foreach( $scripts_content as $k => $v ) {
                
                    // scripts file
                    $script_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5 . '.' . $k . '.footer.js';
                    $script_url = WP_CONTENT_URL . '/cache/scripts/' . $md5 . '.' . $k . '.footer.js';

                    $buffer = implode( '', $v['content'] );

                    if ( $this->minify ) {
                        // Minify!						
                        $minifier = new Minify\JS( $v['content'] );
                        $buffer = $minifier->minify();
                    }

                    $script_file = fopen( $script_path, 'w' );
                    fwrite( $script_file, $buffer );
                    fclose( $script_file );

                    $deps = $this->requires_jquery ? ['jquery'] : []; //@todo: make this a little more robust by checking all ignored & external dependencies
                    $strategy = $k == 'base' ? false : $k;
                    $args = $strategy ? [ 'in_footer' => true, 'strategy' => $strategy ] : true;
                    wp_register_script( 'spos-' . $k . '-footer', $script_url, $deps, filemtime( $script_path ), $args );
                    wp_enqueue_script( 'spos-' . $k . '-footer' );

                    if ( isset( $inline_data[$k]['before'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-footer', implode( "\r\n", $inline_data[$k]['before'] ), 'before' );
                    }

                    if ( isset( $inline_data[$k]['after'] ) ) {
                        wp_add_inline_script( 'spos-' . $k . '-footer', implode( "\r\n", $inline_data[$k]['after'] ) );
                    }

                }
                
                // flag file
                $end_time = microtime(true);
                $execution_time = ( $end_time - $start_time );
                $gmt_offset = get_option('gmt_offset');
                $info = 'Cached ' . date('l jS \of F Y h:i:s A', time() + ( $gmt_offset * 60 * 60 ) ) . "\r\n";
                    $info .= 'Initial ' . ucfirst( get_post_type() ) . ': ' . get_the_title() . "\r\n";
                    $info .= 'Location: Footer' . "\r\n";
                    $info .= 'Includes: ' . implode( ', ', $included_scripts ) . "\r\n";
                    $info .= 'Execution time: ' . $execution_time . ' seconds';

                //$flag_path = WP_CONTENT_DIR . '/cache/scripts/' . $md5;
                $flag_file = fopen( $flag_path, 'w' );
                fwrite( $flag_file, $info );
                fclose( $flag_file );

                if ( $this->debug ) {
                    echo '<pre>' . $info . '</pre>';
                }
            }
            
            
        }
        
    }

    /********************
     PROCESS STYLES
    ********************/

    public function process_styles()
    {
        global $spos_settings, $wp_styles;
        
        $start_time = microtime(true);
        $buffer = array();
        
        if ( count( array_diff( $wp_styles->queue, $this->ignore_styles ) ) == 0 ) {
            // ignoring everything
            return;
        }

        $md5 = md5( implode( ':', $wp_styles->queue ) );
        $style_path = WP_CONTENT_DIR . '/cache/styles/' . $md5;
        $flag_path = $style_path . '.txt';
        
        // check if the file exists and is less than $this->cache_lifespan old
        if ( file_exists( $flag_path ) && filemtime( $flag_path ) > ( time() - $this->cache_lifespan ) && !$this->disable_cache ) {
            // register the existing files
            foreach ( $wp_styles->queue as $k => $handle ) {
                if ( in_array( $handle, $this->ignore_styles ) ) continue;

                //@todo: for some reason mega menu is running process_styles via the wp_print_styles hook.. 
                //       other plugins may also. since all of the styles are already processed, it throws errors
                //       check into why...
                if ( !isset( $wp_styles->registered[$handle] ) ) continue;

                // does the file end in .css?
                // this helps to eliminate problems with php files masquerading as css
                if ( substr( $wp_styles->registered[$handle]->src, -4 ) !== '.css' ) continue;

                if ( strpos( $wp_styles->registered[$handle]->src, $this->blog_url ) !== false ) {
                    if ( !in_array( $wp_styles->registered[$handle]->args, $buffer ) ) {
                        $buffer[] = $wp_styles->registered[$handle]->args;
                    }

                    wp_deregister_style( $handle );
                }
            }
            
            foreach( $buffer as $media ) {
                $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $media);
                
                $style_url = WP_CONTENT_URL . '/cache/styles/' . $md5 . '.' . $slug . '.css';
                
                wp_register_style( 'min-' . $slug, $style_url, '', filemtime( $style_path . '.' . $slug . '.css' ), $media );				
                wp_enqueue_style( 'min-' . $slug );
                
                // put the optimized styles first
                $s = array_pop( $wp_styles->queue );
                array_unshift( $wp_styles->queue, $s );
            }
                    
        } else {                
            // keep track of which styles are in the file
            $included_styles = array();
            // separate out conditional styles to add in afterwards
            $conditional_styles = array();
            
            // create new files
            foreach( $wp_styles->queue as $k => $handle ) {				
                // skip ignored styles
                if ( in_array( $handle, $this->ignore_styles ) ) continue;
                
                // does the file end in .css?
                // this helps to eliminate problems with php files masquerading as css
                if ( substr( $wp_styles->registered[$handle]->src, -4 ) !== '.css' ) continue;
                
                // make sure it's a local stylesheet
                if ( isset( $wp_styles->registered[$handle] ) && strpos( $wp_styles->registered[$handle]->src, $this->blog_url ) !== false ) {
                    $obj = $wp_styles->registered[$handle];
                    
                    // keep track of conditionals to add in afterwards
                    // these won't be included in the optimized file
                    if ( array_key_exists( 'conditional', $obj->extra ) ) {
                        $conditional_styles[] = $obj->handle;
                        continue; // skip to the next stylesheet
                    }

                    $content = $this->get_styles_content(
                        $obj->handle,
                        $obj->src
                    );

                    if ( $content ) {
                        // check for dependencies
                        if ( $obj->deps ) {
                            $deps_content = $this->get_stylesheet_deps_content( $obj->deps, $included_styles );
                            if ( !$deps_content ) {
                                continue; // dependencies weren't able to load - skip this stylesheet
                            }
                        }

                        $media_type = isset( $wp_styles->registered[$handle]->args ) ? $wp_styles->registered[$handle]->args : 'all';
                        
                        if ( !isset( $buffer[$media_type] ) ) {
                            $buffer[$media_type] = $content;
                        } else {
                            $buffer[$media_type] .= $content;
                        }
                        $included_styles[] = $handle;
                        //wp_deregister_style( $handle );
                        wp_dequeue_style( $handle );
                    } // end if content

                } // end if it's local

            } // end foreach
            
            if ( !empty( $included_styles ) ) { // check to make sure there are valid styles

                $end_time = microtime(true);
                $execution_time = ( $end_time - $start_time );
                $gmt_offset = get_option('gmt_offset');
                $info = 'Cached ' . date('l jS \of F Y h:i:s A', time() + ( $gmt_offset * 60 * 60 ) ). "\r\n";
                    $info .= 'Initial ' . ucfirst( get_post_type() ) . ': ' . get_the_title() . "\r\n";
                    $info .= 'Includes: ' . implode( ', ', $included_styles ) . "\r\n";
                    $info .= 'Execution time: ' . $execution_time . ' seconds';

                //$flag_path = WP_CONTENT_DIR . '/cache/styles/' . $md5;
                $flag_file = fopen( $flag_path, 'w' );
                fwrite( $flag_file, $info );
                fclose( $flag_file );

                if ( $this->debug ) {
                    echo '<pre>' . $info . '</pre>';
                }

                foreach ( $buffer as $media => $src ) {
                    $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $media);

                    $style_path = WP_CONTENT_DIR . '/cache/styles/' . $md5 . '.' . $slug . '.css';
                    $style_url = WP_CONTENT_URL . '/cache/styles/' . $md5 . '.' . $slug . '.css';

                    if ( $this->minify ) {
                        // Minify!						
                        $minifier = new Minify\CSS( $src );
                        $src = $minifier->minify();
                    }

                    $style_file = fopen( $style_path, 'w' );
                    $charset = '@charset "utf-8";';
                    fwrite( $style_file, $charset . "\r\n" . $src );
                    fclose( $style_file );
                    wp_register_style( 'min-styles-' . $slug, $style_url, '', filemtime( $style_path ), $media );
                    wp_enqueue_style( 'min-styles-' . $slug );
                    
                    // put the optimized styles first
                    $s = array_pop( $wp_styles->queue );
                    array_unshift( $wp_styles->queue, $s );
                }

                // re-sort the queue to have conditionals at the end
                foreach ( $conditional_styles as $conditional_handle ) {
                    $key = array_search( $conditional_handle, $wp_styles->queue );
                    if ( $key !== false ) {
                        unset( $wp_styles->queue[$key] );
                        $wp_styles->queue[] = $conditional_handle;
                    }
                }
            }
        }
    }

    /********************
     UTILITY
    ********************/

    /**
     * Get the path from the src of a stylesheet
     * @param string $src - from wp_styles
     * @return string or false if it's not valid
     */
    // returns false if the file doesn't exist
    private function get_path_from_src( $src )
    {	
        // break the blog URL apart to replace all parts separately
        $protocols = ['https://', 'http://'];
        $blog_uri =  str_replace( $protocols, '', $this->blog_url );
        $path = str_replace( array_merge( $protocols, [$blog_uri] ), '', preg_replace( '/\?.*/', '', $src ) );
        // grab the file system path
        $realpath = realpath( '.' . $path );
        return $realpath;
    }

    /**
     * The main function to process stylesheet content
     * @param string $handle
     * @param string $src
     * @return string
     */
    private function get_styles_content( $handle, $src )
    {
        $path = $this->get_path_from_src( $src );
        if ( !$path ) {
            return false;
        }

        // get the file's contents
        $content = file_get_contents( $path );

        // include @imports at the beginning of the content
        $content = $this->include_imports( $content );
        
        // update url's to point to the right place
        $content = $this->update_urls( $content, $src );

        // remove charset
        $content = $this->remove_charset( $content );

        // add the handle name before the content
        $content = "\r\n\r\n" . '/* !!!!!!!!!!!!!!!!!!!! ' . $handle . ' !!!!!!!!!!!!!!!!!!!!! */ ' . "\r\n" . $content;
        
        return $content;
    }

    /**
     * Find @import statements in the CSS and include those files before the
     * rest of the content
     * @param string $content
     * @return string
     */
    private function include_imports( $content )
    {
        // does the file have @imports?
        preg_match_all( '/@import url\((.*?)\);/', $content, $matches );

        if ( !empty( $matches[0] ) ) {
            $imports = '';
            foreach( $matches[0] as $at_import ) {
                // remove the import
                $content = str_replace( $at_import, '', $content );
            }
            foreach ( $matches[1] as $import_path ) {
                $full_path = str_replace( '\\', '/', dirname( $path ) ) . '/' . str_replace( array( '\'', '"' ), '', $import_path );
                $real_import_path = realpath( $full_path );
                if ( $real_import_path ) {
                    $import_content = file_get_contents( $real_import_path );
                    $imports .= '/* @import url(' . $import_path . ') */' . "\r\n" . $import_content . "\r\n";	
                }
            }
            $content = $imports . $content;
        }

        return $content;
    }

    /**
     * Update the URLs to match the new location of the cached stylesheet
     * @param string $content
     * @param string $src
     * @return string
     */
    private function update_urls( $content, $src )
    {
        $patharr = explode( '/', $src );
        $pathlen = count( $patharr );
        $pattern = '/url\((.*?)\)/';
        $content = preg_replace_callback(
            $pattern, 
            function( $matches ) {
                if ( strpos( $matches[0], 'http' ) === false ) {
                    return 'url(INSERT_URL/' . str_replace( array( '\'', '"', 'url(', ')' ), '', $matches[0] ) . ')';
                } else {
                    return $matches[0];
                }
            },
            $content
        );
        
        $content = str_replace( 'INSERT_URL', implode( '/', array_slice( $patharr, 0, $pathlen - 1 ) ), $content );

        return $content;
    }

    /**
     * Remove the charset so that it's not declared more than once
     * @param string $content
     * @return string
     */
    private function remove_charset( $content )
    {
        return str_ireplace( '@charset "utf-8";', '', $content );
    }

    /**
     * Recursive function to grab stylesheet dependency content
     * @global object $wp_styles
     * @param array $deps
     * @param array $included_styles
     * @return string
     */
    private function get_stylesheet_deps_content( $deps, &$included_styles )
    {	
        global $wp_styles;
        $content = '';

        foreach( $deps as $dep ) {
            if ( in_array( $dep, $this->ignore_styles ) ) continue;

            if ( !in_array( $dep, $included_styles ) ) {
                // include nested dependencies
                if ( $wp_styles->registered[$dep]->deps ) {
                    $dep_dep_content = $this->get_stylesheet_deps_content( $wp_styles->registered[$dep]->deps, $included_styles );
                    if ( !$dep_dep_content ) {
                        return false;
                    } else {
                        $content .= $dep_dep_content;
                    }
                }	

                $dep_content = $this->get_styles_content(
                    $wp_styles->registered[$dep]->handle,
                    $wp_styles->registered[$dep]->src
                );
                if ( !$dep_content ) {
                    return false;
                } else {
                    $content .= $dep_content;
                    $included_styles[] = $dep;
                }
            } else {
                // already included
                return true;
            }
        }

        return $content;
    }

    /**
     * The main function to process script content
     * @param string $handle
     * @param string $src
     * @return string
     */
    private function get_script_content( $handle, $path )
    {
        $script_path = realpath( '.' . str_replace( $this->blog_url, '', preg_replace( '/\?.*/', '', $path ) ) );
        $content = "\r\n\r\n" . '/* !!!!!!!!!!!!!!!!!!!! ' . $handle . ' !!!!!!!!!!!!!!!!!!!!! */ ' . "\r\n" . file_get_contents( $path );

        // add in semicolon if it's missing
        if ( substr( $content, -1, 1 ) !== ';' ) $content = $content . ';';

        return $content;
    }

    /**
     * Recursive function to grab javascript dependency content
     * @global object $wp_scripts
     * @param array $deps An array of dependency handles to be included
     * @param array $included_scripts
     * @return string
     */
    private function get_scripts_content( $scripts, &$included_scripts = [] )
    {
        global $wp_scripts;
        $data = [];
        
        foreach( $scripts as $handle ) {

            if ( in_array( $handle, $this->ignore_scripts ) ) continue;
            if ( in_array( $handle, $wp_scripts->done ) ) continue;
            if ( !$this->is_internal_src( $wp_scripts->registered[$handle]->src ) ) continue;

            // include nested dependencies
            if ( !empty( $wp_scripts->registered[$handle]->deps ) ) {
                $dep_data = $this->get_scripts_content( $wp_scripts->registered[$handle]->deps, $included_scripts );
                if ( !empty( $dep_data ) ) {
                    $data = array_merge_recursive( $data, $dep_data );
                }
            }

            // does the file exist?
            $path = $this->get_real_path( $wp_scripts->registered[$handle]->src );
            if ( $path ) {
                $content = $this->get_script_content( 
                    $wp_scripts->registered[$handle]->handle,
                    $path
                );
                if ( !empty( $content ) ) {                  
                    if ( isset( $wp_scripts->registered[$handle]->extra['strategy'] ) ) {
                        $data[$wp_scripts->registered[$handle]->extra['strategy']]['content'][] = $content;
                    } else {
                        $data['base']['content'][] = $content;
                    }

                    $included_scripts[] = $handle;
                    $wp_scripts->done[] = $handle;
                    wp_dequeue_script( $handle );
                }
            }
        }
        
        return $data;
    }

    /**
     * Recursive function to get inline data added by wp_localize_script or wp_inline_script
     * 
     * @global object $wp_scripts
     * @param array $scripts A list of scripts to run through
     * @return array The inline scripts separated by base, async, defer and before or after
     */
    private function get_inline_data( $scripts, &$included_scripts = [] ) {
        global $wp_scripts;
        $data = [];

        foreach ( $scripts as $handle ) {
            if ( $handle == 'jquery' ) $this->requires_jquery = true;
            if ( in_array( $handle, $this->ignore_scripts ) ) continue;
            if ( in_array( $handle, $included_scripts ) ) continue;
            if ( !$this->is_internal_src( $wp_scripts->registered[$handle]->src ) ) continue;

            // include nested dependencies
            if ( !empty( $wp_scripts->registered[$handle]->deps ) ) {
                $dep_data = $this->get_inline_data( $wp_scripts->registered[$handle]->deps, $included_scripts );
                if ( !empty( $dep_data ) ) {
                    $data = array_merge_recursive( $data, $dep_data );
                }
            }

            $content = '';
            if ( isset( $wp_scripts->registered[$handle]->extra['data'] ) ) { // localized scripts need to be before
                $content = rtrim( $wp_scripts->registered[$handle]->extra['data'], "\r\n" );
                if ( isset( $wp_scripts->registered[$handle]->extra['strategy'] ) ) {
                    $data[$wp_scripts->registered[$handle]->extra['strategy']]['before'][] = $content;
                } else {
                    $data['base']['before'][] = $content;
                }
            }
            if ( isset( $wp_scripts->registered[$handle]->extra['before'] ) ) {
                $content = rtrim( $wp_scripts->registered[$handle]->extra['before'][1], "\r\n" );
                if ( isset( $wp_scripts->registered[$handle]->extra['strategy'] ) ) {
                    $data[$wp_scripts->registered[$handle]->extra['strategy']]['before'][] = $content;
                } else {
                    $data['base']['before'][] = $content;
                }
            }
            if ( isset( $wp_scripts->registered[$handle]->extra['after'] ) ) {
                $content = rtrim( $wp_scripts->registered[$handle]->extra['after'][1], "\r\n" );
                if ( isset( $wp_scripts->registered[$handle]->extra['strategy'] ) ) {
                    $data[$wp_scripts->registered[$handle]->extra['strategy']]['after'][] = $content;
                } else {
                    $data['base']['after'][] = $content;
                }
            }

            $included_scripts[] = $handle;

        }
        return $data;
    }

    private function get_script_types( $scripts, &$included_scripts = [] )
    {
        global $wp_scripts;
        $data = [];

        foreach ( $scripts as $handle ) {

            if ( in_array( $handle, $this->ignore_scripts ) ) continue;
            if ( in_array( $handle, $included_scripts ) ) continue;
            if ( !$this->is_internal_src( $wp_scripts->registered[$handle]->src ) ) continue;

            if ( !empty( $wp_scripts->registered[$handle]->deps ) ) {
                $dep_data = $this->get_script_types( $wp_scripts->registered[$handle]->deps, $included_scripts );
                if ( !empty( $dep_data ) ) {
                    $data = array_merge_recursive( $data, $dep_data );
                }
            }

            if ( isset( $wp_scripts->registered[$handle]->extra['strategy'] ) ) {
                $data[] = $wp_scripts->registered[$handle]->extra['strategy'];
                continue;
            }

            $data[] = 'base';
        }

        return array_unique( $data );
    }

    private function is_internal_src( $src )
    {
        $protocols = ['https://', 'http://', '//'];
        $blog_uri =  str_replace( $protocols, '', $this->blog_url );

        if ( strpos( $src, $blog_uri ) !== false ) { // src matches blog uri
            return true;
        }

        if ( strpos( $src, 'wp-includes' ) !== false ) { // wp-includes directory
            return true;
        }

        return false;
    }

    private function get_real_path( $src )
    {
        $protocols = ['http://', 'https://', '//'];
        $blog_url = str_replace( $protocols, '', $this->blog_url );
        $path = '.' . str_replace( $blog_url, '', preg_replace( '/\?.*/', '', str_replace( $protocols, '', $src ) ) );
        return realpath( $path );
    }

    /**
     * Determine the cache lifespan in seconds based on the plugin settings
     * @global object $spos_settings
     * @return integer
     */
    private function get_cache_lifespan()
    {
        global $spos_settings;
        if ( isset( $spos_settings['cache_lifespan'] ) ) {
            switch ( $spos_settings['cache_lifespan'] ) {
                case 'day':
                    return 24 * 60 * 60;
                    break;
                case 'twodays':
                    return 2 * 24 * 60 * 60; 
                    break;
                case 'week':
                    return 7 * 24 * 60 * 60;
                    break;
                case 'month':
                    return 30 * 24 * 60 * 60;
                    break;
                case 'manual':
                    return 99 * 365 * 24 * 60 * 60; // 99 years
                default:
                    return 48 * 60 * 60; // 48 hours
            }
        } else {
            // default
            return 48 * 60 * 60; // 48 hours
        }
    }

    private function get_ignored_scripts( $ignored = [] )
    {
        global $spos_settings;

        // grab ignored scripts from the plugin settings
        if ( isset( $spos_settings['ignore_scripts'] ) ) {
            $ignore_setting = sanitize_text_field( $spos_settings['ignore_scripts'] );
            if ( $ignore_setting !== '' ) {
                // separate into an array
                $ignore_arr = explode( ',', $ignore_setting );
                foreach( $ignore_arr as $ignore_handle ) {
                    // add trimmed handle to the ignore array
                    $ignored[] = trim( $ignore_handle );
                }
            }
        }
        
        return $ignored;
    }

    private function get_ignored_styles( $ignored = [] )
    {
        global $spos_settings;

        // grab ignored styles from the plugin settings
        if ( isset( $spos_settings['ignore_styles'] ) ) {
            $ignore_setting = sanitize_text_field( $spos_settings['ignore_styles'] );
            if ( $ignore_setting !== '' ) {
                // separate into an array
                $ignore_arr = explode( ',', $ignore_setting );
                foreach( $ignore_arr as $ignore_handle ) {
                    // add trimmed handle to the ignore array
                    $ignored = trim( $ignore_handle );
                }
            }		
        }

        return $ignored;
    }
	
}

?>
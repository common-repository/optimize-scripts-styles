=== Optimize Scripts & Styles ===
Contributors: seismicpixels
Donate link: http://www.seismicpixels.com/coffee.html
Tags: scripts, styles, optimize, optimization, minify, compress, seo, performance, combine
Requires at least: 4.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.9.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimize Scripts & Styles combines scripts and styles on your site, minifies them and provides cachable versions for improved site performance.

== Description ==

Optimize Scripts & Styles optimizes your site's JavaScript and CSS files by combining, minifying and caching them. This will help your site's overall performance and user experience by reducing the number of files served up and the overall size of these files.

For developers, this allows you to maintain uncompressed versions for development themes or plugins, while compressing them for a production environment.

For you SEO buffs, it cleans up the number of scripts that get included and downloaded to your site visitors, helping your overall PageSpeed score.

Header and footer script locations are maintained as well as any localized data used for the scripts and the minified files are stored in the /cache folder, making it friendly for plugins like WP Super Cache. Media attributes are also maintained for print stylesheets.

### Features
* Combine & minify all JavaScript & CSS that is enqueued
* Stores the optimized files in the cache folder for faster page loads
* Compatible with caching plugins like WP Super Cache and W3 Total Cache
* Enable for logged in users (not recommended)
* Available options to remove or ignore enqueued scripts and styles
* Optional button in the admin bar to clear scripts and styles
* Includes an option to remove script type attributes for HTML5 validation


== Installation ==

1. Upload the plugin files to the /plugins/ directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin through 'Settings' > 'Optimization'

== Frequently Asked Questions ==

= Will this work with caching plugins? =

This plugin was born out of a frustration with the method of combining scripts in W3 Total Cache. Simply disable the minification portion of W3 and use this plugin to manage it instead. Clearing the cache will also clear these scripts for you.

It has also been tested with WP Super Cache. When you clear the cache in WP Super Cache, the contents of the optimized scripts and styles folders are also removed, triggering the creation of new versions.

= How are scripts and styles found in the site? =

All local scripts and styles registered via wp_register_script, wp_enqueue_script, wp_register_style, wp_enqueue_style will be included in the optimized and versions. Plugins or themes that add scripts directly will not have their scripts or styles included.

This plugin ignores off-site scripts and styles. Those will load as they normally would.

= Why are some scripts or styles not included? =

Certain custom implementations of scripts or styles that do not use the WordPress standard methods may not be included. The administrative interface provides you a quick way of tracking down exactly what is in each file so that you can make tweaks if needed.

= Can I ignore certain scripts or styles? =

Yes! You can provide a comma separated list of script or style handles to ignore them. View the Cached files section to get the handles you need. jQuery is always ignored as many components rely on jQuery being available.

= Where can I see what scripts are included? =

Each file that is generated has a matching .txt file in the cache folder which gives details about what was included. The content of these files is listed in the admin area as well.

= Why does it look like my site is broken after optimizing styles?

Theme and plugin developers can include their styles in multiple ways, some correct, some a bit hacky. You may need to use the Ignore Settings to ignore certain stylesheets if they are not cascading correctly. Use the Cached files section to discover which styles are included and add the handles in the Ignore styles area, separated by commas. Ignored stylesheets will load after the compressed version used by this plugin. This can be helpful if you want one file with some overrides in it.

= Does this include PHP scripts that masquerade as JavaScript or CSS?

The plugin checks for .css and .js file extensions before including them in the optimized files. If you have a theme that uses php files that masquerade as JavaScript or CSS, those PHP files will be ignored and load after the compressed scripts or styles used in the plugin. If you have a CSS file that needs to override the styles from the PHP generated styles, you may need to add that stylesheet to the ignore list so that it can load in the correct order.

= Does this plugin combine and minimize Admin scripts? =

No.

= Why does this not work while logged in? =

Optimization may be disabled for logged in users. Check your plugin settings. It is recommended to leave this option off.

== Screenshots ==

1. Admin Options
2. Cached Files List
3. Pre-Optimized Header
4. Optimized Header
5. Pre-Optimized Footer
6. Optimized Footer

== Changelog ==

= 1.9.5 =
* PHP 8.3 compatibility
* Updated minify package to 1.3.73
* Added better recursion for JavaScript dependencies
* Added better handling of localized scripts before & after javascript
* Added handling for async and defer scripts, retaining their intended load strategy
* Code refactoring

= 1.9.4 =
* PHP 8.2 compatibility
* Updated minify package to 1.3.71
* Fixed a bug with stylesheet dependencies

= 1.9.3 =
* Added recursive stylesheet dependency inclusion
* Better code comments

= 1.9.2 =
* Fixed an issue with the HTML5 text/css feature inadvertently converting link tags to script tags

= 1.9.1 =
* [New Feature] Added an option to enable/disable caching. Turn caching off to process scripts on every page load in order to debug potential script or style conflicts.
* [New Feature] Added a cache lifespan option. If your site doesn't change often, it would be beneficial to choose the Manually Refresh option.
* [New Feature] Added the ability to output script info on the front end of the site. Make sure to disable this for production environments.
* Fixed a bug with the optimization behavior select not having a previous value
* Verified compatibility up to WordPress 5.5

= 1.9.0 =
* [New Feature] Added an option to minify OR concatenate only. This could be helpful when trying to track down any issues sites may have due to optimization.
* Updated minification library to the latest version. Fixes a few bugs.

= 1.8.9 =
* Fixed an unset variable error for remove_script_type and remove_style_type
* Verified WordPress 5.0/Gutenberg compatibility. Optimize Scripts & Styles will work along-side the Gutenberg blocks scripts, but the Gutenberg blocks handle their own enqueue, concatenation and minification.

= 1.8.8 =
* Tweaked localized script output to only remove the type='text/javscript' if that option is set in the admin
* Added location (header or footer) to the cached files description
* The plugin no longer initializes on AJAX requests
* Added a hash to the script handles to allow them be unique. This fixed a problem with Gravity Forms using AJAX

= 1.8.7 =
* [New Feature] Added an option to remove type='text/javscript' from script tags so that HTML5 sites can be W3C compliant (other optimizations may be needed)
* [New Feature] Added an option to remove type='text/css' from style tags so that HTML5 sites can be W3C compliant (other optimizations may be needed)
* [New Feature] Added an option to REMOVE scripts and/or styles. Add script or style handles to the list and they will no longer load for the site
* Modified the Clear Optimize Scripts action to better handle a variety of existing query string formats
* Removed the type='text/javscript' that SPOS scripts output for localized data
* Cosmetic udpates to the admin area
* Added a Refresh button to the cached files area for easier testing

= 1.8.6 =
* Fixed a bug with the Clear Optimized Scripts button in the admin bar not having the correct link when viewing the admin via SSL but having a non-SSL front-end to the site

= 1.8.5 =
* Added activation message to remind admins to enable the options
* Added a check for file extensions to make sure only css and js files are included
* Changed the order to include optimized styles before all ignored styles
* Separated the Ignore Settings to provide a better description for usage

= 1.8.4 =
* Fixed a bug that output duplicate localized data
* Changed menu title to Optimization

= 1.8.3 =
* Updating missing SVN files

= 1.8.2 =
* Optimize Scripts & Styles now has an admin screen under Settings!
* On/off for scripts optimization
* On/off for styles optimization
* On/off to enable optimization for logged in users
* On/off to show the Clear Optimized Scripts quick link in the admin header
* Added the ability to ignore specific scripts
* Added the ability to ignore specific styles
* The admin page has a Delete Cache button
* Added a section to view cached scripts for debug & fine-tuning
* Added a section to view cached styles for debug & fine-tuning
* Removed gzip - this should be done in the .htaccess file if possible
* Removed a default exclusion of a Visual Composer stylesheet
* Added a check for all styles being ignored. A flag file will no longer be generated

= 1.8.1 =
* Changed absolute paths to use WordPress' built in features
* Added better cache clearing for W3 Total Cache & WP Super Cache

= 1.8 =
* Major update in the way header/footer scripts are managed
* Much more reliable in making sure everything gets loaded in the right place
* Added .txt to flag files to make it easier to open them
* Added comment to footer when not logged in

= 1.7 =
* Added comment to footer when logged in
* Changed script version in wp_register to match filemtime to encourage caching/refreshing in browsers

= 1.6 =
* Disabled for logged in users
* Fixed an issue where the script wasn't ignoring css files that were in $ignore
* Added js_composer_front to ignore since it's causing an issue with minification

= 1.5 =
* Major update
* Added better support to catch javascript in the footer via two separate functions/hooks

= 1.4 =
* Cleaned up files
* Added minify! (always on)
* Added gzip (turned off - still testing)

= 1.3 =
Added check for file_get_contents http wrapper

= 1.2 =
Added in support for conditional stylesheets

== Upgrade Notice ==

= 1.8 =
This update fixes the way the plugin keeps track of already processed scripts. You should be using at least this version.
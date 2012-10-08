=== Combine CSS ===
Contributors: timmcdaniels
Donate link: http://WeAreConvoy.com
Tags: CSS, gzip
Requires at least: 3.0.1
Tested up to: 3.4.2
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress plugin that combines, minifies, and compresses CSS files.

== Description ==

WordPress plugin that combines, minifies, and compresses CSS files. The CSS files that this plugin combines and minifies must be enqueued by using wp_enqueue_style. The plugin combines and minifies CSS and writes the output into files in the uploads directory and makes attempts to correct paths for images and fonts.

Features include:

* option to change the CSS domain if a CDN is used
* option to change how often CSS files get refreshed
* option to exclude certain CSS files from combining
* option to turn on/off gzip compression
* option to include Gravity Forms CSS (this is due to Gravity Forms not using wp_enqueue_style)
* option to turn on debugging

== Installation ==

1. Upload `combine-css` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= CSS is not displaying properly after activating the plugin. What can I do? =

You can debug the plugin by activating the debug option on the settings page and reviewing the server's error log for details. You can try excluding certain CSS files from getting combined to see if that fixes the issue.

== Screenshots ==
1. This is a screenshot of the Combine CSS settings page.

== Changelog ==

= 0.1 =
* First release!

== Upgrade Notice ==

= 0.1 =
First release!

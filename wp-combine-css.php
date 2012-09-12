<?php
/*
Plugin Name: WP Combine CSS Plugin
Plugin URI: http://www.category4.com
Description: Combine and minify CSS and attempt to preserve image paths in the process.
Author: Category 4
Version: 0.1
Author: Tim McDaniels
Author URI: http://www.category4.com
Requires at least: 3.0.0
Tested up to: 3.2

Copyright 2010-2011 by Tim McDaniels http://www.category4.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License,or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not,write to the Free Software
Foundation,Inc.,51 Franklin St,Fifth Floor,Boston,MA 02110-1301 USA
*/
?>
<?php

// don't allow direct access of this file

if ( preg_match( '#'.basename(__FILE__).'#', $_SERVER['PHP_SELF'] ) ) die();

// increase memory

ini_set( 'memory_limit', '150M' );

// define plugin file path

define( 'WPCCP_PLUGIN_FILE', __FILE__ );

// define directory name of plugin

define( 'WPCCP_PLUGIN_DIR', basename( dirname( WPCCP_PLUGIN_FILE ) ) );

// path to this plugin

define( 'WPCCP_PLUGIN_PATH', dirname( __FILE__ ) );

// require base objects and do instantiation

if ( !class_exists( 'WPCombineCSS' ) ) {
        require_once( dirname( __FILE__ ) . '/classes/combine-css.php' );
}
$wp_combine_css = new WPCombineCSS();

?>

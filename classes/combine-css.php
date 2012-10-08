<?php

class CombineCSS {

        /**
        *Variables
        */
        const version = '0.1';
	const nspace = 'combine-css';
	const pname = 'Combine CSS';
        protected $_plugin_file;
        protected $_plugin_dir;
        protected $_plugin_path;
        protected $_plugin_url;

	var $cachetime = '';
	var $css_domain = '';
	var $create_cache = false;
	var $wpccp_path = '';
	var $wpccp_uri = '';
	var $css_path = '';
	var $css_uri = '';
	var $css_path_tmp = '';
	var $settings_data = array();
	var $css_files_ignore = array( 'admin-bar.css', 'login.css', 'colors-fresh.css', 'wp-admin.css' );
	var $css_handles_found = array();
	var $debug = false;

        /**
        *Constructor
        *
        *@return void
        *@since 0.1
        */
        function __construct() {}

	/**
        *Init function
        *
        *@return void
        *@since 0.1
        */
        function init() {

                // settings data

                $this->settings_data = unserialize( get_option( self::nspace . '-settings' ) );
		$this->cachetime = $this->get_settings_value( 'cachetime' );
		if ( ! @strlen( $this->cachetime ) ) $this->cachetime = 300;
		$this->css_domain = $this->get_settings_value( 'css_domain' );
		if ( ! @strlen( $this->css_domain ) ) $this->css_domain = get_option( 'siteurl' );

                // set debugging

                if ( $this->settings_data['debug'] == 'Yes' ) $this->debug = true;

		// add ignore files

		$ignore_list = explode( "\n", $this->settings_data['ignore_files'] );
		foreach ( $ignore_list as $item ) {
			$this->css_files_ignore[] = $item;
		}

                // set compress

                if ( ! $this->settings_data['compress'] ) $this->settings_data['compress'] = 'Yes';

		// set file path and uri

		$upload_dir = wp_upload_dir();
		$this->wpccp_path = $upload_dir['basedir'] . '/' . self::nspace . '/';
		$this->wpccp_uri = $upload_dir['baseurl'] . '/' . self::nspace . '/';

		// make sure wpccp directory exists

		if ( ! file_exists( $this->wpccp_path ) ) mkdir ( $this->wpccp_path );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( &$this, 'add_settings_page' ), 30 );

			// settings fields

			$this->settings_fields = array(
							'legend_1' => array(
									'label' => 'General Settings',
									'type' => 'legend'
									),
							'css_domain' => array(
									'label' => 'CSS Domain',
									'type' => 'text',
									'default' => get_option( 'siteurl' )
									),
							'cachetime' => array(
									'label' => 'Cache Expiration',
                                                                        'instruction' => 'How often to refresh CSS files in seconds.',
									'type' => 'select',
									'values' => array( '60' => '1 minute', '300' => '5 minutes', '900' => '15 minutes', '1800' => '30 minutes', '3600' => '1 hour' ),
									'default' => '300'
									),
							'htaccess_user_pw' => array(
                                                                        'label' => 'Username and Password',
                                                                        'instruction' => 'Use when site is accessed behind htaccess authentication -- syntax: username:password.',
                                                                        'type' => 'text',
                                                                        'default' => 'username:password'
                                                                        ),
							'add_gf_css' => array(
									'label' => 'Add Gravity Forms CSS',
									'type' => 'select',
									'values' => array( 'No' => 'No', 'Yes' => 'Yes' ),
                                                                        'default' => 'No'
									),
							'ignore_files' => array(
									'label' => 'CSS Files to Ignore',
                                                                        'instruction' => 'Enter one per line. Only use the name of the CSS file (like style.css). You can be more specific about what CSS file to ignore by specifying the plugin and then the CSS file (like plugin:style.css).',	
									'type' => 'textarea'
									),
                                                        'compress' => array(
                                                                        'label' => 'GZip Compress CSS output?',
                                                                        'type' => 'select',
                                                                        'values' => array( 'No' => 'No', 'Yes' => 'Yes' ),
                                                                        'default' => 'Yes'
                                                                        ),
                                                        'debug' => array(
                                                                        'label' => 'Turn on debugging?',
                                                                        'type' => 'select',
                                                                        'values' => array( 'No' => 'No', 'Yes' => 'Yes' ),
                                                                        'default' => 'No'
                                                                        )
						);
		}
		elseif ( strstr( $_SERVER['REQUEST_URI'], 'wp-login' ) || strstr( $_SERVER['REQUEST_URI'], 'gf_page=' ) || strstr( $_SERVER['REQUEST_URI'], 'preview=' ) ) {}
		else {

			if ( $this->cache_expired( $this->css_path ) && $this->cache_expired( $this->css_path_tmp ) )  {
				$this->create_cache = true;
			}

			add_action( 'style_loader_tag', array( $this, 'gather_css' ), 500 );
			add_filter( 'wp_head', array( $this, 'combine_css' ), 501 );

			/* get rid of browser prefetching of next page from link rel="next" tag */

			remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
			remove_action('wp_head', 'adjacent_posts_rel_link');
		}
        }

	/**
        *Debug function
        *
        *@return void
        *@since 0.1
        */
        function debug ( $msg ) {
                if ( $this->debug ) error_log( 'DEBUG: ' . $msg );
        }

	/**
        *Cache expired?
        *
        *@return boolean
        *@since 0.1
        */
	function cache_expired ( $path ) {
		$mtime = 0;
		if( file_exists( $path ) ) $mtime = @filemtime( $path );
		$now = time();
		$transpired = $now - $mtime;
		if ( $transpired > $this->cachetime ) return true;
		return false;
	}

	/**
        *Gather CSS
        *
        *@return string
        *@since 0.1
        */
        function gather_css ( $src ) {

		if ( is_admin() ) return;

		$this->debug( 'Function gather_css' );

                // get src of CSS file

                preg_match( "/<link.*href\=('|\")(http.*(\/wp\-(content|includes|admin).*))(\?.*)('|\") type.*$/", $src, $matches );
                $css_src = $this->strip_domain( $matches[2] );
                $css_file = $this->get_file_from_src( $css_src );

                // get context (plugin or theme)

                preg_match( "/(plugins|themes)\/(.*)\/.*/", $css_src, $cmatches );
                $context = '';
                if ( $cmatches ) {
                        $context = $cmatches[2];
                        if ( strstr( $context, '/' ) ) $context = dirname( $cmatches[2] );
                }
                $this->debug( '     -> context ' . $context );

                // add this css file to the found handles if it is not to be ignored

		if ( ! in_array( $context . ':' . $css_file, $this->css_files_ignore ) && ! in_array( $css_file, $this->css_files_ignore ) ) {

                        // save all the scripts that we've gathered

			$this->debug( '     -> found ' . $css_src );
			$this->css_handles_found[$css_src]['css_file'] = $css_file;
			$this->css_handles_found[$css_src]['css_src'] = $matches[2] . $matches[5];
                        $this->css_handles_found[$css_src]['context'] = $context;
		}

                // get name of file based on md5 hash of js handles

                $file_name = md5( @implode( '', array_keys( $this->css_handles_found ) ) );

                // paths to wpccp css

                $this->css_path = $this->wpccp_path . $file_name . '.css';
                if ( $this->settings_data['compress'] == 'Yes' ) $this->css_path .= '.php';
                $this->css_uri = $this->wpccp_uri . $file_name . '.css';
                if ( $this->settings_data['compress'] == 'Yes' ) $this->css_uri .= '.php';
                $this->css_path_tmp = $this->css_path . '.tmp';

		// add gravity forms css, if told to

		if ( $this->settings_data['add_gf_css'] == 'Yes' ) {
                        $gf_src = $this->strip_domain( plugins_url( 'css/forms.css', 'gravityforms' ) );
                        $gf_file = $this->get_file_from_src( $gf_src );
			$this->css_handles_found[$gf_src]['css_file'] = $gf_file;
		}

                if ( $this->css_handles_found[$css_src] ) {}
                else return $src;
        }

	/**
        *Combine CSS
        *
        *@return void
        *@since 0.1
        */
	function combine_css ( ) {

		$this->debug( 'Function combine_css' );

		$content = '';

		if ( $this->create_cache ) { 

			// get scripts from options

			foreach ( $this->css_handles_found as $css_src => $array ) {
				$css_file = $array['css_file'];
				if( $this->file_exists( $css_src ) ) {

					$this->debug( "     -> caching $css_src" );

					$css_content = '';

					// if file is a PHP script, pull content via curl

					if ( preg_match( "/\.php/", $css_src ) ) {
						$css_content = $this->curl_file_get_contents ( $this->css_handles_found[$css_src]['css_src'] );
						$css_src = $this->css_handles_found[$css_src]['css_src'];
					}
					else {
						$css_content = file_get_contents( ABSPATH . $css_src );
					}

					// change path to images

					$css_content = str_replace(
									array( 
										'url(images/',
										"url('images/",
										'url("images/',
										'url(fonts/',
										"url('fonts/",
										'url("fonts/'
									),
									array( 
										'url(/' . dirname( $css_src ) . '/images/',
										'url(\'/' . dirname( $css_src ) . '/images/',
										'url("/' . dirname( $css_src ) . '/images/',
										'url(/' . dirname( $css_src ) . '/fonts/',
                                                                                'url(\'/' . dirname( $css_src ) . '/fonts/',
                                                                                'url("/' . dirname( $css_src ) . '/fonts/'
									), 
									$css_content
								);

					$content .= $this->compress( $css_content );
					unset( $css_content );

				}

                        }
			$this->cache_content( $content );

                        @rename( $this->css_path_tmp, $this->css_path );

		}

		if ( file_exists ( $this->css_path ) ) {
			echo "\t\t<link rel='stylesheet' id='wpccp-css' href='" . str_replace( get_option( 'siteurl' ), $this->css_domain, $this->css_uri ) . "' type='text/css' media='all' />\n";
		}

	}

	/**
        *File exists
        *
        *@return boolean
        *@since 0.1
        */
	function file_exists( $src ) {
		if ( @strlen( $src ) && file_exists( ABSPATH . $src ) ) return true;
		return false;
	}

	/**
        *Get file from src
        *
        *@return string
        *@since 0.1
        */
	function get_file_from_src( $src ) {
		$frags = explode( '/', $src );
		return $frags[count( $frags ) -1];
	}

        /**
        *Cache content
        *
        *@return void
        *@since 0.1
        */
        function cache_content( $content ) {
                $this->debug( 'Function cache_content' );
                if ( @strlen( $content ) ) {
			$this->cache( 'css_path_tmp', $content );
                }
        }

        /**
        *Write data to file system
        *
        *@return void
        *@since 0.1
        */
        function cache( $tmp_file, $content ) {
                if ( ! file_exists( $this->$tmp_file ) ) {
                        $fp = fopen( $this->$tmp_file, "w" );
			$this->debug( $this->$tmp_file . ' created' );
                        if ( flock( $fp, LOCK_EX ) ) { // do an exclusive lock
                                if ( $this->settings_data['compress'] == 'Yes' ) {
                                        $content = '<?php if(extension_loaded("zlib")){ob_start("ob_gzhandler");} header("Content-type: text/css"); ?>' .
                                                $content . '<?php if(extension_loaded("zlib")){ob_end_flush();}?>';
                                }
                                fwrite( $fp, $content );
                                flock( $fp, LOCK_UN ); // release the lock
                        }
                        fclose( $fp );
                }
        }

	/**
        *Get content via curl
        *
        *@return string
        *@since 0.1
        */
	function curl_file_get_contents( $src ) {
                $url = trim( $src );
		$url = preg_replace( "/http(|s):\/\//", "http://" . $this->get_settings_value( 'htaccess_user_pw' ) . "@", $url );
                $c = curl_init();
                curl_setopt( $c, CURLOPT_URL, $url );
                curl_setopt( $c, CURLOPT_FAILONERROR, false );
                curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $c, CURLOPT_VERBOSE, false );
                curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
                curl_setopt( $c, CURLOPT_SSL_VERIFYHOST, false );
                if( count( $header ) ) {
                        curl_setopt( $c, CURLOPT_HTTPHEADER, $header );
                }
                $contents = curl_exec( $c );
                curl_close( $c );
		return $contents;
	}

	/**
        *Strip domain from string
        *
        *@return string
        *@since 0.1
        */
	function strip_domain( $src ) {
		$src = str_replace( array( 'http://', 'https://' ), array( '', '' ), $src );
		$frags = explode( '/', $src );
		array_shift( $frags );
		return implode( '/', $frags );
	}

	/**
        *Compress 
        *
        *@return string
        *@since 0.1
        */
	function compress( $content ) {
		$this->debug( '     -> compress ' . $handle );
		/* remove comments */
		$content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
		/* remove tabs, spaces, newlines, etc. */
		$content = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $content);
		return $content;
	}

        /**
        *Add settings page
        *
        *@return void
        *@since 0.1
        */
        function add_settings_page() {
                if ( current_user_can( 'manage_options' ) ) {
                        add_options_page( self::pname, self::pname, 'manage_options', 'wpccp-settings', array( &$this, 'settings_page' ) );
                }
        }

        /**
        *Settings page
        *
        *@return void
        *@since 0.1
        */
        function settings_page() {
                if($_POST['wpccp_update_settings']) {
                        $this->update_settings();
                }
                $this->show_settings_form();
        }

        /**
        *Show settings form
        *
        *@return void
        *@since 0.1
        */
        function show_settings_form () {
                include( $this->get_plugin_path() . '/views/admin_settings_form.php' );
        }

        /**
        *Get single value from unserialized data
        *
        *@return string
        *@since 0.1
        */
        function get_settings_value( $key = '' ) {
                return $this->settings_data[$key];
        }

        /**
        *Remove option when plugin is deactivated
        *
        *@return void
        *@since 0.1
        */
        function delete_settings() {
                delete_option( $this->option_key );
        }

        /**
        *Is associative array function
        *
        *@return string
        *@since 0.1
        */
        function is_assoc( $arr ) {
                if ( isset ( $arr[0] ) ) return false;
                return true;
        }

        /**
        *Display a select form element
        *
        *@return string
        *@since 0.1
        */
        function select_field( $name, $values, $value, $use_label = false, $default_value = '', $custom_label = '' ) {
                ob_start();
                $label = '-- please make a selection --';
                if ( @strlen( $custom_label ) ) {
                        $label = $custom_label;
                }

                // convert indexed array into associative

                if ( ! $this->is_assoc( $values ) ) {
                        $tmp_values = $values;
                        $values = array();
                        foreach ( $tmp_values as $tmp_value ) {
                                $values[$tmp_value] = $tmp_value;
                        }
                }
?>
        <select name="<?php echo $name; ?>" id="<?php echo $name; ?>">
                <?php if ( $use_label ): ?>
                <option value=""><?php echo $label; ?></option>

                <?php endif; ?>
                <?php foreach ( $values as $val => $label ) : ?>
                        <option value="<?php echo $val; ?>"<?php if ($value == $val || ( $default_value == $val && @strlen( $default_value ) && ! @strlen( $value ) ) ) : ?> selected="selected"<?php endif; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
        </select>
<?php
                $content = ob_get_contents();
                ob_end_clean();
                return $content;
        }

        /**
        *Update settings form
        *
        *@return void
        *@since 0.1
        */
        function update_settings() {
                $data = array();
                foreach( $this->settings_fields as $key => $val ) {
                        if( $val['type'] != 'legend' ) {
                                $data[$key] = $_POST[$key];
                        }
                }
                $this->set_settings( $data );
		$this->delete_cache();
        }

        /**
        *Update serialized array option
        *
        *@return void
        *@since 0.1
        */
        function set_settings( $data ) {
                update_option( self::nspace . '-settings', serialize( $data ) );
                $this->settings_data = $data;
        }

	/**
        *Delete cache
        *
        *@return void
        *@since 0.1
        */
	function delete_cache() {
		@unlink( $this->css_path );
                if ( function_exists( 'wp_cache_clear_cache' ) ) {
                        wp_cache_clear_cache();
                }
	}

        /**
        *Set plugin file
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_file( $plugin_file ) {
                $this->_plugin_file = $plugin_file;
        }

        /**
        *Get plugin file
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_file() {
                return $this->_plugin_file;
        }

        /**
        *Set plugin directory
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_dir( $plugin_dir ) {
                $this->_plugin_dir = $plugin_dir;
        }

        /**
        *Get plugin directory
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_dir() {
                return $this->_plugin_dir;
        }

        /**
        *Set plugin file path
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_path( $plugin_path ) {
                $this->_plugin_path = $plugin_path;
        }

        /**
        *Get plugin file path
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_path() {
                return $this->_plugin_path;
        }

	/**
        *Set plugin URL
        *
        *@return void
        *@since 0.1
        */
        function set_plugin_url( $plugin_url ) {
                $this->_plugin_url = $plugin_url;
        }

        /**
        *Get plugin URL
        *
        *@return string
        *@since 0.1
        */
        function get_plugin_url() {
                return $this->_plugin_url;
        }

}

?>

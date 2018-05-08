<?php

class Doorbitch {
	//TODO: initiated defaults to false, save as an option
	public static $debug_mode = true;
	public static $debug_messages = array();
	public static $table_suffix = 'doorbitch';

	private static $options = array();

	public function __construct() {
		$options = self::get_options();

		// Run the install function if we're not already initiated.
		if ( ! isset( $options[ 'initiated' ] ) || $options[ 'initiated' ] == false ) {
			self::install();
		} 

		// Show _POST data:
		foreach ( $_POST as $key => $value) {
			self::debug( $key . ':' . $value );
		}

		// Show options array in debug area:
		// foreach ( $options as $option => $value ) {
		// 	if (! is_array( $value ) ){
		// 		self::debug( $option . ' : ' . $value );
		// 	}
		// 	else {
		// 		$list = $option . ': ';
		// 		foreach ($value as $item) {
		// 			$list .= $item . ', ';
		// 		}
		// 		self::debug( $list );
		// 	}
		// }

        // Add debugger assets if we're in debug mode.
		if ( DOORBITCH__DEBUG_MODE ){
			function enqueue_debug_styles() { 
				wp_enqueue_style( 'debug', plugins_url( '/css/debug.css', __FILE__ ) ); 
			}
			add_action( 'wp_enqueue_scripts', 'enqueue_debug_styles' );
			add_action( 'admin_notices', array( get_called_class(), 'debug_show' ) );
			add_action( 'wp_footer', array( get_called_class(), 'debug_show' ) );
		}

		//Add virtual page for the frontend form:
		require_once( DOORBITCH__PLUGIN_DIR . 'class.doorbitch-virtual-pages.php' );
		$doorbitch_virtual_pages = new Doorbitch_Virtual_Pages();
	
		//Add admin options page under 'tools' section:
		if( is_admin() ) {
		    // include PhpSpreadsheet library:
		    require 'vendor/autoload.php';
			require_once( DOORBITCH__PLUGIN_DIR . 'class.doorbitch-admin.php' );
			$doorbitch_admin = new Doorbitch_Admin();
			function enqueue_admin_styles() {
				wp_enqueue_style( 'doorbitch-admin', plugins_url( '/css/doorbitch-admin.css', __FILE__ ) );
			}
			add_action( 'admin_enqueue_scripts', 'enqueue_admin_styles' );
		}

		//upgrade the database if neccessary:
		// add_action( 'plugins_loaded', array( get_called_class(), 'update_db_check' ) );

	}

	public static function get_options() {
		// if ( empty( self::$options ) ) {
		// 	if ( ! self::$options = get_option( 'doorbitch_options' ) ) {
		// 		// get_option will return false if the option doesn't exist.
		// 		self::$options = array();
		// 	}
		// }
		self::$options = get_option( 'doorbitch_options' );
		return self::$options;
	}

	public static function install() {
		global $wpdb;
	    $options = self::get_options();

		self::debug( 'initiating...' );

		$db_current_version = 0.0;
 		if ( array_key_exists( 'db_version' , $options ) ) {
		    $db_current_version = $options[ 'db_version' ];
	    }
	    $db_current_version = self::update_db_check( $db_current_version );

		$table_name = $wpdb->prefix . self::$table_suffix;
		$charset_collate = $wpdb->get_charset_collate();

		// add the table to store data from the frontend form
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			event tinytext NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			data text NOT NULL,	
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );


		self::add_event( 'Example' );
		self::set_current_event( 'Example' );
		self::add_data( 'Example', 'Name:Joe Bloggs,Age:42,email:nobody@nowhere' );
		// TODO: scan database for events
		$events = $wpdb->get_results ( "SELECT DISTINCT event FROM {$wpdb->prefix}doorbitch" );
        $options[ 'events' ] = array();
        foreach ($events as $event) {
        	self::debug( 'adding event: ' . $event->event );
        	array_push( $options[ 'events' ], $event->event );
        }

		$options[ 'form_html' ] = file_get_contents( DOORBITCH__PLUGIN_DIR . '/forms/default.php' );
		$options[ 'initiated' ] = true;

		update_option( 'doorbitch_options', $options );
		self::debug( 'saving options' );

		// show error output on plugin activation
		if ( defined('WP_DEBUG') && true === WP_DEBUG && DOORBITCH__DEBUG_MODE ) { 
		    function doorbitch_activated_plugin_error() {
		        update_option( 'doorbitch_error',  ob_get_contents() );
		    }
		    function doorbitch_deactivated_plugin_error() {
		        delete_option( 'doorbitch_error' );
		    }
		    add_action( 'activated_plugin', 'doorbitch_activated_plugin_error' );
		    add_action( 'deactivated_plugin', 'doorbitch_deactivated_plugin_error' );
		     
		    function doorbitch_message_plugin_error() {
		        ?>
		        <div class="notice notice-error">
		            <p><?php echo get_option( 'doorbitch_error' ); ?></p>
		        </div>
		        <?php
		        }
		    if( get_option( 'doorbitch_error' ) ) {
		        add_action( 'admin_notices', 'doorbitch_message_plugin_error' ); 
		    }
		}
	}

	public static function add_event( $event_name ) {
		$options = self::get_options();
		$events = $options[ 'events' ];
		// add the event iff it doesn't already exist
		if ( ! in_array( $event_name, $options[ 'events' ] ) ) {
			array_push( $options[ 'events' ], $event_name );
		}
		$options[ 'current_event' ] = $event_name;
		update_option( 'doorbitch_options', $options );
	}

	public static function set_current_event( $event_name ) {
		$options = self::get_options();
		if ( in_array( $event_name, $options[ 'events' ] ) ) {
			$options[ 'current_event' ] = $event_name;
		}
		else {
			self::debug( 'event \'' . $event_name . '\' not found' );
		}
		update_option( 'doorbitch_options', $options );
	}

	public static function upgrade_database() {
		global $wpdb;
		$options = self::get_options();

		$table_name = $wpdb->prefix . self::$table_suffix;

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			event tinytext NOT NULL,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			data text NOT NULL,	
			PRIMARY KEY  (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

	    $options[ 'db_version' ] = DOORBITCH__DATABASE_VERSION;
		update_option( 'doorbitch_options', $options );
	}

	//Since 3.1 the activation function registered with register_activation_hook() is not called when a plugin is updated:
	public static function update_db_check( $db_current_version ) {
		// global $db_version;
		// TODO: Check if database needs upgrading
		$this_db_version = DOORBITCH__DATABASE_VERSION;
		if ( $this_db_version > $db_current_version ) {
			self::debug( "Installing database v." . DOORBITCH__DATABASE_VERSION );
			self::upgrade_database();
		}
		return $this_db_version;
	}

	public static function add_data( $event, $data ) {
		global $wpdb;
		$options = self::get_options();

		$table_name = $wpdb->prefix . self::$table_suffix;

		$wpdb->insert(
			$table_name,
			array(
				'event' => $event,
				'time' => current_time( 'mysql' ),
				'data' => $data
			)
		);
		return true;
	}

	public static function debug_show() {
		echo "<div class='doorbitch-debug'><h4>Debug</h4>";
		for ($i = 0; $i < count( self::$debug_messages ); $i++ ) {
			print_r( self::$debug_messages[$i] );
		}
		echo "</div>";
	}

	public static function debug( $debug_text ) {
		$file = basename( debug_backtrace()[0]['file'] );
		self::$debug_messages[] = '<p><i>' . htmlspecialchars( $debug_text ) . '</i> -> ' . $file . '</p>';
		//TODO: Print errors from table of common errors.

	}
}


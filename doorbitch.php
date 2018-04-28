<?php
/**
 * @package Doorbitch
 * @version 0.0.2
 *
 */
/*
Plugin Name: Doorbitch
Plugin URI: https://github.com/jahnertz/wordpress-plugin-doorbitch/tree/master
Description: A wordpress plugin to used to collect and export patrons' basic information. Use the 'Doorbitch' admin page (under Tools) to configure the plugin.
Login to the collection page via http://yoursite.com/doorbitch
Author: Jordan Han
Version: 0.0.1
Author URI: https://jhanrahan.com.au
*/
define( 'DOORBITCH__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( DOORBITCH__PLUGIN_DIR . 'class.doorbitch.php' );
global $bitch_db_version;
$bitch_db_version = '1.0';

function bitch_install() {

	global $wpdb;
	global $bitch_db_version;

	$table_name = $wpdb->prefix . 'doorbitch';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		name tinytext NOT NULL,
		text text NOT NULL,
		url varchar(55) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'bitch_db_version', $bitch_db_version );
}

function bitch_install_data() {
	global $wpdb;

	$welcome_name = "Mr. Doorbitch";
	$welcome_text = "Congratulations, the plugin was successfully installed";

	$table_name = $wpdb->prefix . 'doorbitch';

	$wpdb->insert(
		$table_name,
		array(
				'time' => current_time( 'mysql' ),
				'name' => $welcome_name,
				'text' => $welcome_text,
			)
	);
}

register_activation_hook( __FILE__, 'bitch_install' );
register_activation_hook( __FILE__, 'bitch_install_data' );

//upgrade the database if necessary:
global $wpdb;
$installed_ver = get_option( "jal_db_version" );
if ( $installed_ver != $bitch_db_version ) {

	$table_name = $wpdb->prefix . 'doorbitch';

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		name tinytext NOT NULL,
		text text NOT NULL,
		url varchar(100) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	);";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	update_option( "bitch_db_version", $bitch_db_version );
}
//Since 3.1 the activation function registered with register_activation_hook() is not called when a plugin is updated:
function doorbitch_update_db_check() {
	global $bitch_db_version;
	if ( get_site_option( 'bitch_db_version' ) != $bitch_db_version ) {
		bitch_install();
	}
}
add_action( 'plugins_loaded', 'doorbitch_update_db_check' );

//Add admin options page under 'tools' section:
include( plugin_dir_path( __FILE__ ) . 'class.doorbitch-admin.php');

if( is_admin() ) $doorbitch_admin = new Doorbitch_Admin();

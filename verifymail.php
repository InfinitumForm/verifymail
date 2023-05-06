<?php
/**
 * @wordpress-plugin
 *
 * Plugin Name:       Verify Email Addresses
 * Description:       Check to see if an email address or domain are disposable, temporary, or even have an existing deliverable mailbox.
 * Version:           1.0.0
 * Requires PHP:      7.0
 * Requires at least: 5.0
 * Author:            INFINITUM FORM
 * Author URI:        https://infinitumform.com/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       verifymail
 * Domain Path:       /languages
 * Contributors:      creativform, ivijanstefan
 * Developed By:      Ivijan-Stefan Stipic <infinitumform@gmail.com>
 */
 
// If someone try to called this file directly via URL, abort.
if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Set globals
 */
global $verifymail;
$verifymail = ( (object) [
	'file' => __FILE__,
	'path' => rtrim(plugin_dir_path(__FILE__), '/')
] );

/**
 * Get plugin version
 */
if (function_exists('get_file_data') && $plugin_data = get_file_data($verifymail->file, array(
    'Version' => 'Version'
) , false)) {
    $verifymail->version = (string)$plugin_data['Version'];
}

if (!$verifymail->version && preg_match('/\*[\s\t]+?version:[\s\t]+?([0-9.]+)/i', file_get_contents($verifymail->file) , $v)) {
    $verifymail->version = (string)$v[1];
}

/**
 * Final class
 */
if( !class_exists('VerifyMail', false) ) : final class VerifyMail {
	
	private function __construct () {
		$this->includes();
	}
	
	/**
	 * Global includes
	 */
	private function includes () {
		global $verifymail;
		
		$include_classes = [
			'API' => false
		];
		
		foreach($include_classes as $include => $init) {
			if( file_exists("{$verifymail->path}/init/{$include}.php") ) {
				include_once "{$verifymail->path}/init/{$include}.php";
			}
			
			if( $init === true && class_exists("VerifyMail_{$include}", false) ) {
				$class_name = "VerifyMail_{$include}";
				$class_name::init();
			}
		}
	}
	
	/**
	 * Run the plugin
	 */
	private static $run;
	public static function run () {
		
		if( !self::$run ) {
			self::$run = new self();
		}
		
		return self::$run;
	}
} endif;

/**
 * Run the plugin
 */
VerifyMail::run();
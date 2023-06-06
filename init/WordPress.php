<?php if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
/*!
 * WordPress protection by verifymail.io
 *
 * @author            Ivijan-Stefan Stipic
 */
if(!class_exists('VerifyMail_WordPress')) : class VerifyMail_WordPress {
	
	private function __construct () {
		
	}
	
	/**
	 * Run the plugin
	 */
	private static $init;
	public static function init () {
		
		if( !self::$init ) {
			self::$init = new self();
		}
		
		return self::$init;
	}
	
} endif;
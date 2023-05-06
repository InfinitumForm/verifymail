<?php if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
/*!
 * Verify Email API via verifymail.io
 *
 * @author            Ivijan-Stefan Stipic
 */
if(!class_exists('VerifyMail_API')) : class VerifyMail_API {
	
	#########################
	#### PLUGIN SETTINGS ####
	#########################
	
	/**
	 * SETTINGS: Verify Email API KEY
	 */
	private const API_KEY = 'daff1f6d4dc44586b42978a03c95eb17';
	
	/**
	 * SETTINGS: Cache group (optional)
	 */
	private const CACHE_GROUP = 'verifymail';
	
	/**
	 * SETTINGS: Keep Cache (optional)
	 */
	private const CACHE_PERIOD = 'monthly';
	
	
	
	
	########################
	#### PUBLIC OBJECTS ####
	########################
	
	/**
	 * Returning public objects and defaults
	 */
	 
	// Basic, Premium & Pro Plan
	public $block;
	public $disposable;
	public $domain;
	public $email_address;
	
	// Premium & Pro Plan
	public $email_provider;
	public $deliverable_email;
	public $catch_all;
	public $mx;
	public $mx_fallback;
	public $mx_host;
	public $mx_ip;
	public $mx_priority;
	public $privacy;
	public $related_domains;
	
	// Error handler
	public $message = NULL;
	public $error = true;
	
	
	
	
	##########################
	#### PUBLIC FUNCTIONS ####
	##########################
	
	/**
	 * Email Address Lookup
	 *
	 * @pharam   string     Email address
	 * @return   objects
	 */
	public static function lookup( $email ) {
		global $verifymail;
		
		if( !( $verifymail->lookup ?? NULL ) ) {
			$verifymail->lookup = ( new self($email) )->request;
		}		
		return $verifymail->lookup;
	}
	
	/**
	 * Email Address Verification
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	private static $__verify;
	public static function verify( $email ) {
		if( !self::$__verify ) {
			$lookup = self::lookup( $email );
			self::$__verify = ( !( $lookup->error || $lookup->block || $lookup->disposable || !$lookup->deliverable_email ) );
		}		
		return self::$__verify;
	}
	
	/**
	 * Is Email Address Disposable
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function is_disposable( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->disposable;
	}
	
	/**
	 * Is Email Address Blocked
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function is_blocked( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->block;
	}
	
	/**
	 * Is Deliverable Email
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function is_deliverable_email( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->deliverable_email;
	}
	
	/**
	 * Get Email Address Domain
	 *
	 * @pharam   string     Email address
	 * @return   string     URL
	 */
	public static function get_domain( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->domain;
	}
	
	/**
	 * Get MX Hosts
	 *
	 * @pharam   string     Email address
	 * @return   array
	 */
	public static function get_mx_hosts( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->mx_host ?? [];
	}
	
	/**
	 * Get MX Hosts IP
	 *
	 * @pharam   string     Email address
	 * @return   array
	 */
	public static function get_mx_ip( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->mx_ip ?? [];
	}
	
	/**
	 * Get MX Hosts Priority
	 *
	 * @pharam   string     Email address
	 * @return   array
	 */
	public static function get_mx_priority( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->mx_priority ?? [];
	}
	
	/**
	 * Is Error Present
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function has_error( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->error;
	}
	
	/**
	 * Get Error Message
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function get_error_message( $email ) {
		$lookup = self::lookup( $email );
		return $lookup->message;
	}
	
	/**
	 * Flush Email Cache
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function flush_cache( $email ) {
		$email = strtolower( $email );
		return wp_cache_delete('verifymail_' . $email, self::CACHE_GROUP);
	}
	
	/**
	 * Flush All Email Caches
	 *
	 * @return   bool
	 */
	public static function flush_cache_all() {
		return wp_cache_flush_group( self::CACHE_GROUP );
	}
	
	
	
	
	######################
	#### PRIVATE AREA ####
	######################
	
	/**
	 * Private constructor, keep everything inside the class
	 *
	 * @pharam   string     Email address
	 */
	private function __construct( $email ) {
		$this->request = $this->request( $email );
	}
	
	/**
	 * Send a request to the main API
	 *
	 * @pharam   string     Email address
	 * @return   objects
	 */
	private $request;
	private function request( $email ) {
		
		// Keep email in lowercase
		$email = strtolower($email);
		
		// Get the cache
		if( $response = wp_cache_get('verifymail_' . $email, self::CACHE_GROUP) ){
			return $this->render( $response );
		}
		
		// Ask API for the email
		$response = wp_remote_get('https://verifymail.io/api/' . $email . '?key=' . self::API_KEY . '&referrer=wordpress' );
		
		// Verify API response
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			
			// Proccess response
			$response = $this->proccess( wp_remote_retrieve_body( $response ) );
			
			// Render response
			$response = $this->render( $response );
			
			// Save to cache
			if($response->error === false) {
				wp_cache_set( 'verifymail_' . $email, $response, self::CACHE_GROUP, $this->cache_period() );
			}
			
			// Response
			return $response;
		}
		
		// On the Fail
		return $this->proccess_error_response();
	}
	
	/**
	 * Proccess Response
	 *
	 * @pharam   JSON     API Response
	 * @return   objects
	 */
	private function proccess ( $response ) {
		
		// Get public objects
		$__this = (object)get_object_vars($this);
		unset( $__this->request );
		
		// Decode JSON response
		if( $response = json_decode($response, true) ) {
			
			// Loop, sanitize and build objects
			foreach($response as $column => $value) {
				
				if ( !property_exists($this, $column) ) {
					continue;
				}
				
				$__this->{$column} = $value;
			}
			
			// Error handler
			if( empty($__this->message) ) {
				$__this->error = false;
			}
			
		}
		
		// Return
		return $__this;
	}
	
	/**
	 * Render Objects
	 *
	 * @pharam   objects     internal objects
	 * @return   objects
	 */
	private function render ($__this) {
		
		// If deliverable email is not provided 
		if( NULL === $__this->deliverable_email ) {
			$__this->deliverable_email = !($__this->error || $__this->block || $__this->disposable);
		}
		
		// If MX record is not provided
		if( NULL === $__this->mx && function_exists('dns_get_record') && function_exists('gethostbyname') && function_exists('getmxrr') ) {
			if( $mx = dns_get_record($__this->domain, DNS_MX) ) {
				
				$__this->mx_host = $__this->mx_priority = $__this->mx_ip=[];
				
				foreach($mx as $record) {
					if($record['type'] === 'MX') {
						$__this->mx_host[] = $record['target'];
						$__this->mx_priority[$record['target']] = $record['pri'];
						$__this->mx_ip[] = gethostbyname($record['target']);
					}
				}
				
				$__this->mx = !empty($__this->domain);
				$__this->mx_fallback = ($__this->mx ? !getmxrr($__this->domain, $__this->mx_host) : false);
			}
		}
		
		return $__this;
	}
	
	/**
	 * Proccess Error Response
	 *
	 * @return   objects
	 */
	private function proccess_error_response () {
		$__this = (object)get_object_vars($this);
		unset( $__this->request );
		
		$__this->message = __( 'Unable to communicate with `verifymail.io` API service.', 'tourly-platform' );
		$__this->message = true;
		
		return $__this;
	}
	
	/**
	 * Get cache period
	 *
	 * @return   integer      Timestamp
	 */
	private function cache_period() {
		if( is_numeric(self::CACHE_PERIOD) ) {
			return absint(self::CACHE_PERIOD);
		}
		
		switch(self::CACHE_PERIOD) {
			
			case 'hourly':
				return HOUR_IN_SECONDS;
				break;
			
			case 'daily':
				return DAY_IN_SECONDS;
				break;
			
			default:			
			case 'monthly':
				return MONTH_IN_SECONDS;
				break;
			
			case 'yearly':
				return YEAR_IN_SECONDS;
				break;
		}
	}
	
} endif;
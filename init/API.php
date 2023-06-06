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
	 * SETTINGS: Cache group (optional)
	 */
	private const CACHE_GROUP = 'verifymail';
	
	
	
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
	public $status = 404;
	
	
	
	
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
		
		if( !isset($verifymail->lookup) ) {
			$verifymail->lookup = [];
		}
		
		if( !( $verifymail->lookup[$email] ?? NULL ) ) {
			$verifymail->lookup[$email] = ( new self($email) )->request;
		}		
		return $verifymail->lookup[$email];
	}
	
	/**
	 * Email Address Verification
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function verify( $email ) {
		$lookup = self::lookup( $email );
		
		if( $lookup->deliverable_email ) {
			return true;
		}
		
		if( !$lookup->block ) {
			return true;
		}
		
		return false;
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
	
	
	/**
	 * Validate email address
	 *
	 * @return   bool
	 */
	public static function validate( $email ) {
		return (boolean) filter_var($email, FILTER_VALIDATE_EMAIL);
	}
	
	
	
	
	######################
	#### PRIVATE AREA ####
	######################
	
	/**
	 * Private constructor, keep everything inside the class
	 *
	 * @pharam   string     Email address
	 */
	private $request;
	private function __construct( $email ) {
		$this->request = $this->request( $email );
	}
	
	/**
	 * Send a request to the main API
	 *
	 * @pharam   string     Email address
	 * @return   objects
	 */
	private function request( $email ) {
		
		// Keep email in lowercase
		$email = strtolower($email);
		
		// If mail is not verified
		if( !self::validate($email) ) {
			return $this->render( $this );
		}
		
		// Get the cache
		if( $response = wp_cache_get('verifymail_' . $email, self::CACHE_GROUP) ){
			return $this->render( $response );
		}
		
		// Ask API for the email
		$response = wp_remote_get('https://verifymail.io/api/' . $email . '?key=' . VerifyMail_Settings::get('api_key', '') );
		
		// Verify API response
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			
			// Get response code
			$response_code = wp_remote_retrieve_response_code( $response );
			
			// Proccess response
			$response = $this->proccess( wp_remote_retrieve_body( $response ), $response_code );
			
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
	private function proccess ( $response, $response_code = 404 ) {
		
		// Get public objects
		$__this = (object)get_object_vars($this);
		unset( $__this->request );
		
		// Decode JSON response
		if( $response = json_decode($response, true) ) {
			
			// Append code		
			$response['status'] = $response_code;
			
			// Debugging
			// $response['checkdnsrr'] = checkdnsrr( $response['domain'], 'MX' );
			// tp_dump($response);
			
			// Verify domain
			if( !$response['block'] && !$response['deliverable_email'] ) {
				$response['domain_verified'] = $this->verify_domain( $response['domain'] );				
				if( !$response['domain_verified'] ) {
					$response['block'] = true;
					$response['deliverable_email'] = false;
				}
			}
			
			$response = apply_filters('verifymail_proccess_response', $response, $this);
			
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
		return apply_filters('verifymail_proccess_return_response', $__this, $response, $this);
	}
	
	/**
	 * Render Objects
	 *
	 * @pharam   objects     internal objects
	 * @return   objects
	 */
	private function render ($__this) {
		
		$__this = apply_filters('verifymail_before_render_response', $__this, $this);
		
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
		
		return apply_filters('verifymail_render_response', $__this, $this);
	}
	
	/**
	 * Proccess Error Response
	 *
	 * @return   objects
	 */
	private function proccess_error_response () {
		$__this = (object)get_object_vars($this);
		unset( $__this->request );
		
		$__this->message = __( 'Unable to communicate with `verifymail.io` API service.', 'verifymail' );
		$__this->message = true;
		
		return $__this;
	}
	
	
	/**
	 * Check and verify whether a domain exists or not in the fastest possible way
	 *
	 * @return   bool
	 */
	private function verify_domain( $url )
	{
		// Initialize domain
		$ch = curl_init( esc_url($url) );
		
		// Special settings
		curl_setopt_array( $ch, [
			CURLOPT_NOBODY 			=> true,
			CURLOPT_FAILONERROR 	=> true,
			CURLOPT_RETURNTRANSFER 	=> true,
			CURLOPT_NOSIGNAL 		=> true,
			CURLOPT_SSL_VERIFYPEER 	=> false,
			CURLOPT_SSL_VERIFYHOST 	=> false,
			CURLOPT_HEADER 			=> false,
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_VERBOSE			=> false,
			CURLOPT_USERAGENT 		=> ( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			CURLOPT_TIMEOUT_MS 		=> 3000, // TImeout in miliseconds
			CURLOPT_MAXREDIRS 		=> 2,
		] );
		
		// Execute
		curl_exec( $ch );
		
		// Get domain informations
		$info = curl_getinfo($ch);
		
		// Close cURL
		curl_close($ch);
		
		// Return
		return !empty($info['primary_ip'] ?? NULL);
	}
	
	
	/**
	 * Get cache period
	 *
	 * @return   integer      Timestamp
	 */
	private function cache_period() {
		$cache_period = VerifyMail_Settings::get('cache_period', 'monthly');
		
		if( is_numeric($cache_period) ) {
			return absint($cache_period);
		}
		
		switch($cache_period) {
			
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
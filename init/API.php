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
	
	
	/**
	 * PROTECTED: Default values if API response fail
	 */
	protected $response_default = [
		'block' 			=> false,
		'catch_all' 		=> false,
		'deliverable_email' => false,
		'disposable' 		=> false,
		'domain' 			=> '',
		'email_address' 	=> '',
		'email_provider' 	=> '',
		'mx' 				=> true,
		'mx_fallback' 		=> false,
		'mx_host' 			=> [],
		'mx_ip' 			=> [],
		'mx_priority' 		=> [],
		'privacy' 			=> false,
		'related_domains' 	=> []
	];
	
	
	##########################
	#### PUBLIC FUNCTIONS ####
	##########################
	
	/**
	 * Email Address Lookup
	 *
	 * @pharam   string     Email address
	 * @return   objects
	 */
	public static function lookup( string $email ) {
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
	public static function verify( string $email ) {
		
		if( defined('VERIFY_MAIL_TEST_MODE') && VERIFY_MAIL_TEST_MODE ) {
			return true;
		}
		
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
	public static function is_disposable( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->disposable;
	}
	
	/**
	 * Is Email Address Blocked
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function is_blocked( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->block;
	}
	
	/**
	 * Is Deliverable Email
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function is_deliverable_email( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->deliverable_email;
	}
	
	/**
	 * Get Email Address Domain
	 *
	 * @pharam   string     Email address
	 * @return   string     URL
	 */
	public static function get_domain( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->domain;
	}
	
	/**
	 * Get MX Hosts
	 *
	 * @pharam   string     Email address
	 * @return   array
	 */
	public static function get_mx_hosts( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->mx_host ?? [];
	}
	
	/**
	 * Get MX Hosts IP
	 *
	 * @pharam   string     Email address
	 * @return   array
	 */
	public static function get_mx_ip( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->mx_ip ?? [];
	}
	
	/**
	 * Get MX Hosts Priority
	 *
	 * @pharam   string     Email address
	 * @return   array
	 */
	public static function get_mx_priority( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->mx_priority ?? [];
	}
	
	/**
	 * Is Error Present
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function has_error( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->error;
	}
	
	/**
	 * Get Error Message
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function get_error_message( string $email ) {
		$lookup = self::lookup( $email );
		return $lookup->message;
	}
	
	/**
	 * Flush Email Cache
	 *
	 * @pharam   string     Email address
	 * @return   bool
	 */
	public static function flush_cache( string $email ) {
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
	public static function validate( string $email ) {
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
	private function __construct( string $email ) {
		$this->request = $this->request( $email );
	}
	
	/**
	 * Send a request to the main API
	 *
	 * @pharam   string     Email address
	 * @return   objects
	 */
	private function request( string $email ) {
		
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
	private function proccess ( string $response, int $response_code = 404 ) {
		
		// Get public objects
		$__this = (object)get_object_vars($this);
		unset( $__this->request );
		
		// Decode JSON response
		if( $response = json_decode($response, true) ) {
			
			// Merge defaults
			$response = array_merge( $this->response_default, $response );
			
			// Append code		
			$response['status'] = $response_code;
			
			// Debugging
			// $response['checkdnsrr'] = checkdnsrr( $response['domain'], 'MX' );
			// tp_dump($response);
			
			// Verify domain
			if( !$response['block'] && !$response['deliverable_email'] ) {
				$response['domain_verified'] = $this->verify_domain( $response['domain'] ?? '' );				
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
	private function render ( stdClass|VerifyMail_API $__this) {
		
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
	 * Check and verify that the domain exists in the fastest way possible.
	 *
	 * This MUST go via cURL directly, as we are only looking to see if the server IP address exists 
	 * and if the domain is available in the short term.
	 *
	 * `wp_remote_get` can't be used here because it uses additional libraries that we don't need
	 * and slow down the response.
	 *
	 * @return   `bool` on success or `NULL` on failure
	 */
	private function verify_domain( string $url )
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
			CURLOPT_USERAGENT 		=> sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			CURLOPT_TIMEOUT_MS 		=> 5000, // Timeout in miliseconds
			CURLOPT_MAXREDIRS 		=> 2
		] );
		
		// Execute
		curl_exec( $ch );
		
		// Get the primary IP address
		$info = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
		
		// Get rrror codes that we need
		$error_code = curl_errno($ch);
		
		// Close cURL
		curl_close($ch);
		
		// return NULL on the timeout
		if( in_array($error_code, [28, 33, 34, 35, 47]) ) {
			return NULL;
		}
		
		// Return bool
		return !empty($info);
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
	
	
	/**
	 * Translate cURL error codes
	 *
	 * @param    int        curl_errno() code
	 * @return   string     constant name
	 */
	private function translate_curl_errno( int $code ) {
		$cURL_error_codes = array(
			0 => 'CURLE_OK',
			1 => 'CURLE_UNSUPPORTED_PROTOCOL',
			2 => 'CURLE_FAILED_INIT',
			3 => 'CURLE_URL_MALFORMAT',
			4 => 'CURLE_URL_MALFORMAT_USER',
			5 => 'CURLE_COULDNT_RESOLVE_PROXY',
			6 => 'CURLE_COULDNT_RESOLVE_HOST',
			7 => 'CURLE_COULDNT_CONNECT',
			8 => 'CURLE_FTP_WEIRD_SERVER_REPLY',
			9 => 'CURLE_FTP_ACCESS_DENIED',
			10 => 'CURLE_FTP_USER_PASSWORD_INCORRECT',
			11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
			12 => 'CURLE_FTP_WEIRD_USER_REPLY',
			13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
			14 => 'CURLE_FTP_WEIRD_227_FORMAT',
			15 => 'CURLE_FTP_CANT_GET_HOST',
			17 => 'CURLE_FTP_COULDNT_SET_TYPE',
			18 => 'CURLE_PARTIAL_FILE',
			19 => 'CURLE_FTP_COULDNT_RETR_FILE',
			21 => 'CURLE_QUOTE_ERROR',
			22 => 'CURLE_HTTP_RETURNED_ERROR',
			23 => 'CURLE_WRITE_ERROR',
			25 => 'CURLE_UPLOAD_FAILED',
			26 => 'CURLE_READ_ERROR',
			27 => 'CURLE_OUT_OF_MEMORY',
			28 => 'CURLE_OPERATION_TIMEDOUT',
			30 => 'CURLE_FTP_PORT_FAILED',
			31 => 'CURLE_FTP_COULDNT_USE_REST',
			33 => 'CURLE_RANGE_ERROR',
			34 => 'CURLE_HTTP_POST_ERROR',
			35 => 'CURLE_SSL_CONNECT_ERROR',
			36 => 'CURLE_BAD_DOWNLOAD_RESUME',
			37 => 'CURLE_FILE_COULDNT_READ_FILE',
			38 => 'CURLE_LDAP_CANNOT_BIND',
			39 => 'CURLE_LDAP_SEARCH_FAILED',
			41 => 'CURLE_FUNCTION_NOT_FOUND',
			42 => 'CURLE_ABORTED_BY_CALLBACK',
			43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
			45 => 'CURLE_INTERFACE_FAILED',
			47 => 'CURLE_TOO_MANY_REDIRECTS',
			48 => 'CURLE_UNKNOWN_OPTION',
			49 => 'CURLE_TELNET_OPTION_SYNTAX',
			51 => 'CURLE_PEER_FAILED_VERIFICATION',
			52 => 'CURLE_GOT_NOTHING',
			53 => 'CURLE_SSL_ENGINE_NOTFOUND',
			54 => 'CURLE_SSL_ENGINE_SETFAILED',
			55 => 'CURLE_SEND_ERROR',
			56 => 'CURLE_RECV_ERROR',
			58 => 'CURLE_SSL_CERTPROBLEM',
			59 => 'CURLE_SSL_CIPHER',
			60 => 'CURLE_SSL_CACERT',
			61 => 'CURLE_BAD_CONTENT_ENCODING',
			62 => 'CURLE_LDAP_INVALID_URL',
			63 => 'CURLE_FILESIZE_EXCEEDED',
			64 => 'CURLE_USE_SSL_FAILED',
			65 => 'CURLE_SEND_FAIL_REWIND',
			66 => 'CURLE_SSL_ENGINE_INITFAILED',
			67 => 'CURLE_LOGIN_DENIED',
			68 => 'CURLE_TFTP_NOTFOUND',
			69 => 'CURLE_TFTP_PERM',
			70 => 'CURLE_REMOTE_DISK_FULL',
			71 => 'CURLE_TFTP_ILLEGAL',
			72 => 'CURLE_TFTP_UNKNOWNID',
			73 => 'CURLE_REMOTE_FILE_EXISTS',
			74 => 'CURLE_TFTP_NOSUCHUSER',
			75 => 'CURLE_CONV_FAILED',
			76 => 'CURLE_CONV_REQD',
			77 => 'CURLE_SSL_CACERT_BADFILE',
			78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
			79 => 'CURLE_SSH',
			80 => 'CURLE_SSL_SHUTDOWN_FAILED',
			81 => 'CURLE_AGAIN',
			82 => 'CURLE_SSL_CRL_BADFILE',
			83 => 'CURLE_SSL_ISSUER_ERROR',
			84 => 'CURLE_FTP_PRET_FAILED',
			85 => 'CURLE_RTSP_CSEQ_ERROR',
			86 => 'CURLE_RTSP_SESSION_ERROR',
			87 => 'CURLE_FTP_BAD_FILE_LIST',
			88 => 'CURLE_CHUNK_FAILED',
			89 => 'CURLE_NO_CONNECTION_AVAILABLE',
			90 => 'CURLE_SSL_PINNEDPUBKEYNOTMATCH',
			91 => 'CURLE_SSL_INVALIDCERTSTATUS',
			92 => 'CURLE_HTTP2_STREAM',
			93 => 'CURLE_RECURSIVE_API_CALL',
			94 => 'CURLE_AUTH_ERROR',
			95 => 'CURLE_HTTP3',
			96 => 'CURLE_QUIC_CONNECT_ERROR',
			97 => 'CURLE_PROXY',
			98 => 'CURLE_SSL_INVALIDCERT',
			99 => 'CURLE_MISSING_FILE',
			100 => 'CURLE_REMOTE_FILE_NO_PERMISSION',
			101 => 'CURLE_INVALID_URL',
			102 => 'CURLE_HTTPS_PROXY_TUNNEL',
			103 => 'CURLE_SSL_CIPHER_NOT_FOUND',
			104 => 'CURLE_SSL_CACERT_REJECTED',
			105 => 'CURLE_BAD_CERTIFICATE',
			106 => 'CURLE_THREAD_FAILED',
			107 => 'CURLE_TOO_MANY_REDIRECTS',
			108 => 'CURLE_UNKNOWN_TELNET_OPTION',
			109 => 'CURLE_TELNET_OPTION_SYNTAX',
			110 => 'CURLE_OBSOLETE',
			111 => 'CURLE_SSL_PEER_CERTIFICATE',
			112 => 'CURLE_GOT_NOTHING',
			113 => 'CURLE_SSL_ENGINE_NOTFOUND',
			114 => 'CURLE_SSL_ENGINE_SETFAILED',
			115 => 'CURLE_SEND_ERROR',
			116 => 'CURLE_RECV_ERROR',
			117 => 'CURLE_SSL_CERTPROBLEM',
			118 => 'CURLE_SSL_CIPHER',
			119 => 'CURLE_SSL_CACERT',
			120 => 'CURLE_BAD_CONTENT_ENCODING',
			121 => 'CURLE_LDAP_INVALID_URL',
			122 => 'CURLE_FILESIZE_EXCEEDED',
			123 => 'CURLE_USE_SSL_FAILED',
			124 => 'CURLE_SEND_FAIL_REWIND',
			125 => 'CURLE_SSL_ENGINE_INITFAILED',
			126 => 'CURLE_LOGIN_DENIED',
			127 => 'CURLE_TFTP_NOTFOUND',
			128 => 'CURLE_TFTP_PERM',
			129 => 'CURLE_REMOTE_DISK_FULL',
			130 => 'CURLE_TFTP_ILLEGAL',
			131 => 'CURLE_TFTP_UNKNOWNID',
			132 => 'CURLE_REMOTE_FILE_EXISTS',
			133 => 'CURLE_TFTP_NOSUCHUSER',
			134 => 'CURLE_CONV_FAILED',
			135 => 'CURLE_CONV_REQD',
			136 => 'CURLE_SSL_CACERT_BADFILE',
			137 => 'CURLE_REMOTE_FILE_NOT_FOUND',
			138 => 'CURLE_SSH',
			139 => 'CURLE_SSL_SHUTDOWN_FAILED',
			140 => 'CURLE_AGAIN',
			141 => 'CURLE_SSL_CRL_BADFILE',
			142 => 'CURLE_SSL_ISSUER_ERROR',
			143 => 'CURLE_FTP_PRET_FAILED',
			144 => 'CURLE_RTSP_CSEQ_ERROR',
			145 => 'CURLE_RTSP_SESSION_ERROR',
			146 => 'CURLE_FTP_BAD_FILE_LIST',
			147 => 'CURLE_CHUNK_FAILED'
		);
		
		return $cURL_error_codes[$code] ?? 'CURLE_UNDEFINED_ERROR';
	}
	
} endif;
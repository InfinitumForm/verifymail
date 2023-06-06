<?php if ( ! defined( 'WPINC' ) ) { die( "Don't mess with us." ); }
/*!
 * Verify Email API via verifymail.io
 *
 * @author            Ivijan-Stefan Stipic
 */
if(!class_exists('VerifyMail_Settings')) : class VerifyMail_Settings {
	
	private function __construct () {
		/**
		 * Register our add_admin_menu to the admin_menu action hook.
		 */
		add_action('admin_menu', [$this, 'add_admin_menu'], 9);
		/**
		 * Register our settings_init to the admin_init action hook.
		 */
		add_action( 'admin_init', [$this, 'settings_init'] );		
	}
	
	/**
	 * Get the plugin settings option
	 */
	public static function get($name, $default=NULL) {
		global $verifymail;
		
		if( !($verifymail->settings ?? NULL) ) {
			$verifymail->settings = get_option('verifymail_settings', []);
		}
		
		$option = apply_filters('verifymail_get_option', ( $verifymail->settings[$name] ?? NULL ), $name, $default);
		
		if( $option ) {
			return $option;
		}
		
		return apply_filters('verifymail_get_option_default', $default, $name);
	}
	
	/**
	 * Register submenu under Settings
	 */
	public function add_admin_menu () {
		add_submenu_page(
			'options-general.php',
			__('Verify Mail', 'verifymail'),
			__('Verify Mail', 'verifymail'),
			'administrator',
			'verifymail',
			[$this, 'verifymail__callback']
		);
	}
	
	/**
	 * Plugin option and settings
	 */
	function settings_init() {
		global $verifymail;
		
		// Register General Settings to the "verifymail" page.
		add_settings_section(
			'general_settings',
			__( 'General Settings', 'verifymail' ),
			function() {
				printf(
					'<p>%s</p>',
					esc_html__( 'These are the most important settings for the overall functionality of the plugin.', 'verifymail' )
				);
			},
			'verifymail'
		);

		add_settings_field(
			'api_key',
			__( 'API KEY', 'verifymail' ),
			[$this, 'input_callback'],
			'verifymail',
			'general_settings',
			[
				'type'			=> 'password',
				'value'         => self::get('api_key', NULL),
				'placeholder'   => __('Insert API KEY', 'verifymail'),
				'id'			=> 'verifymail_api_key',
				'name'			=> 'verifymail_settings[api_key]',
				'class'			=> 'regular-text',
				'autocomplete'	=> 'off',
				'desc'			=> sprintf(
					__('Our non-paying clients can still use our services, however non-paying clients are limited to 3 API requests per a day and server speeds may vary, depending on the amount of free users using the API at the same time. For faster API queries, please %s.', 'verifymail'),
					'<a href="' . esc_url($verifymail->api_pricing_url) . '" target="_blank"><b>' . __('consider purchasing an API plan', 'verifymail') . '</b></a>'
				)
			]
		);
		
		// Register WordPress Settings to the "verifymail" page.
		add_settings_section(
			'wordpress_settings',
			__( 'WordPress Settings', 'verifymail' ),
			function() {
				printf(
					'<p>%s</p>',
					esc_html__( 'Adjust how the plugin will behave and which sections it will protect.', 'verifymail' )
				);
			},
			'verifymail'
		);
		
		// For the filter "wp_authenticate_user"
		add_settings_field(
			'protect_wp_login',
			__( 'Protect WP Login', 'verifymail' ),
			[$this, 'select_callback'],
			'verifymail',
			'wordpress_settings',
			[
				'default'       => self::get('protect_wp_login', 'yes'),
				'id'			=> 'verifymail_protect_wp_login',
				'name'			=> 'verifymail_settings[protect_wp_login]',
				'desc'			=> __('Prevent site users who do not have a verified email address from logging in.', 'verifymail'),
				'options' => [
					'yes' => __( 'Enable', 'verifymail' ),
					'no' => __( 'Disable', 'verifymail' )
				]
			]
		);
		
		// For the filter "allow_password_reset"
		add_settings_field(
			'protect_password_reset',
			__( 'Protect Password Reset', 'verifymail' ),
			[$this, 'select_callback'],
			'verifymail',
			'wordpress_settings',
			[
				'default'       => self::get('protect_password_reset', 'yes'),
				'id'			=> 'verifymail_protect_password_reset',
				'name'			=> 'verifymail_settings[protect_password_reset]',
				'desc'			=> __('Prevent site users who do not have a verified email address from resetting their password.', 'verifymail'),
				'options' => [
					'yes' => __( 'Enable', 'verifymail' ),
					'no' => __( 'Disable', 'verifymail' )
				]
			]
		);
		
		// For the filter "registration_errors"
		add_settings_field(
			'protect_signup_form',
			__( 'Protect Registration Form', 'verifymail' ),
			[$this, 'select_callback'],
			'verifymail',
			'wordpress_settings',
			[
				'default'       => self::get('protect_signup_form', 'yes'),
				'id'			=> 'verifymail_protect_signup_form',
				'name'			=> 'verifymail_settings[protect_signup_form]',
				'desc'			=> __('Prevent site visitors who do not have a verified email address to register.', 'verifymail'),
				'options' => [
					'yes' => __( 'Enable', 'verifymail' ),
					'no' => __( 'Disable', 'verifymail' )
				]
			]
		);
		
		// Register Verify Mail Cache Settings to the "verifymail" page.
		add_settings_section(
			'cache_settings',
			__( 'Cache Settings', 'verifymail' ),
			function() {
				printf(
					'<p>%s</p>',
					esc_html__( 'Adjust the caching provided by the plugin.', 'verifymail' )
				);
			},
			'verifymail'
		);
		
		add_settings_field(
			'cache_period',
			__( 'Caching Period', 'verifymail' ),
			[$this, 'select_callback'],
			'verifymail',
			'cache_settings',
			[
				'default'       => self::get('cache_period', 'monthly'),
				'id'			=> 'cache_period',
				'name'			=> 'verifymail_settings[cache_period]',
				'desc'			=> __('Choose a caching period for API calls to avoid frequent duplication. Default: "Monthly"', 'verifymail'),
				'options' => [
					'hourly'	=> __( 'Hourly', 'verifymail' ),
					'daily'		=> __( 'Daily', 'verifymail' ),
					'monthly'	=> __( 'Monthly', 'verifymail' ),
					'yearly'	=> __( 'Yearly', 'verifymail' )
				]
			]
		);
		
		
		// Register Verify Mail Labels to the "verifymail" page.
		add_settings_section(
			'verifymail_labels',
			__( 'Custom Labels', 'verifymail' ),
			function() {
				printf(
					'<p>%s</p>',
					esc_html__( 'Define the messages you will leave to your visitors if their email address is blocked.', 'verifymail' )
				);
			},
			'verifymail'
		);
		
		add_settings_field(
			'label_error_wp_login',
			__( 'WP Login Error Message', 'verifymail' ),
			[$this, 'textarea_callback'],
			'verifymail',
			'verifymail_labels',
			[
				'value'       	=> self::get('label_error_wp_login', ''),
				'placeholder'	=> __('You are not allowed to log in with the email address you are currently using.', 'verifymail'),
				'id'			=> 'verifymail_label_error_wp_login',
				'name'			=> 'verifymail_settings[label_error_wp_login]',
				'desc'			=> __('A custom message that is displayed to all users with an unverified email address who try to log in.', 'verifymail'),
				'class'			=> 'large-text'
			]
		);
		
		add_settings_field(
			'label_error_password_reset',
			__( 'Password Reset Error Message', 'verifymail' ),
			[$this, 'textarea_callback'],
			'verifymail',
			'verifymail_labels',
			[
				'value'       	=> self::get('label_error_password_reset', ''),
				'placeholder'	=> __('You are not allowed to reset your account with this email address you are currently using.', 'verifymail'),
				'id'			=> 'verifymail_label_error_wp_login',
				'name'			=> 'verifymail_settings[label_error_password_reset]',
				'desc'			=> __('Custom message displayed to all users with an unverified email address trying to reset their password.', 'verifymail'),
				'class'			=> 'large-text'
			]
		);
		
		add_settings_field(
			'label_error_signup_form',
			__( 'Password Reset Error Message', 'verifymail' ),
			[$this, 'textarea_callback'],
			'verifymail',
			'verifymail_labels',
			[
				'value'       	=> self::get('label_error_signup_form', ''),
				'placeholder'	=> __('You are not allowed to create an account with this email address that you are currently using.', 'verifymail'),
				'id'			=> 'verifymail_label_error_wp_login',
				'name'			=> 'verifymail_settings[label_error_signup_form]',
				'desc'			=> __('Custom message displayed to all users with an unverified email address trying to register their profile.', 'verifymail'),
				'class'			=> 'large-text'
			]
		);
		
		// Register a new setting for "verifymail" page.
		register_setting( 'verifymail', 'verifymail_settings', [
			'show_in_rest' => false,
			'type' => 'array'
		] );
	}
	
	/**
	 * Submenu page
	 */
	public function verifymail__callback () { global $verifymail; ?>
<div class="wrap" id="verifymail-settings">
	<h1><?php esc_html_e('Verify Email Addresses', 'verifymail'); ?></h1>
	<p><?php echo wp_kses_post( sprintf(__('Set up %s and prevent spammers and all malicious visitors from creating an account on your site.', 'verifymail'), '<a href="' . $verifymail->api_pricing_url . '" target="_blank">VERIFYMAIL.IO</a>') ); ?></p>
	<hr class="wp-header-end">
	<div class="metabox-holder has-right-sidebar">
		
		<div class="inner-sidebar" id="verifymail-settings-sidebar">
			SIDEBAR
		</div>
		
		<div id="post-body">
			<div id="post-body-content">
				<form action="<?php echo esc_url( admin_url('/options.php') ); ?>" method="post">
				<?php
					// output security fields for the registered setting "verifymail"
					settings_fields( 'verifymail' );
					// output setting sections and their fields
					// (sections are registered for "verifymail", each field is registered to a specific section)
					do_settings_sections( 'verifymail' );
					// output save settings button
					submit_button( 'Save Settings' );
				?>
				</form>
			</div>
		</div>
		
	</div>
</div>
	<?php }
	
	/**
	 * Input field callback
	 */
	public function select_callback ($args) {
		
		$kses_input = array_keys($args);
		$kses_input = array_filter($kses_input, function($key) {
			return !in_array($key, ['desc', 'info', 'type', 'options', 'value', 'default', 'selected']);
		});
		$kses_input = array_flip($kses_input);
		$kses_input = array_map(function($x){
			return [];
		}, $kses_input);
		
		?><select <?php
			$attr = []; 
			foreach($args as $key => $value) {
				
				if( in_array($key, ['desc', 'info', 'type', 'options', 'value', 'default', 'selected']) ) continue;
				
				if( $value ) {
					$attr[] = esc_attr($key) . '="' . (
						(filter_var($value, FILTER_VALIDATE_URL) !== false) && esc_url($value)===$value 
							? esc_url($value) 
							: esc_attr($value) 
					) . '"';
				}
			}
			// Sanitization and escaping is done inside a loop
			echo wp_kses(
				join( ' ', $attr ),
				[
					'select' => $kses_input,
					'option' => [
						'selected' => true,
						'value' => true
					]
				]
			);
		?>>
		<?php foreach(($args['options'] ?? []) as $value => $text) : ?>
			<option value="<?php echo esc_attr($value); ?>" <?php selected( $value, ($args['value'] ?? $args['default'] ?? $args['selected'] ?? '') ); ?>><?php echo esc_html($text); ?></option>
		<?php endforeach; ?>
		</select><?php if( $args['desc'] ?? NULL ) : ?>
		<p class="description"><?php echo wp_kses_post( $args['desc'] ); ?></p>
		<?php endif;
	}
	
	/**
	 * Input field callback
	 */
	public function input_callback ($args) {
		
		$kses_input = array_keys($args);
		$kses_input = array_filter($kses_input, function($key) {
			return !in_array($key, ['desc', 'info']);
		});
		$kses_input = array_flip($kses_input);
		$kses_input = array_map(function($x){
			return [];
		}, $kses_input);
		
		?><input <?php
			$attr = []; 
			foreach($args as $key => $value) {
				
				if( in_array($key, ['desc', 'info']) ) continue;
				
				if( $value ) {
					$attr[] = esc_attr($key) . '="' . (
						(filter_var($value, FILTER_VALIDATE_URL) !== false) && esc_url($value)===$value 
							? esc_url($value) 
							: esc_attr($value) 
					) . '"';
				}
			}
			// Sanitization and escaping is done inside a loop
			echo wp_kses(
				join( ' ', $attr ),
				[
					'input' => $kses_input
				]
			);
		?>><?php if( $args['desc'] ?? NULL ) : ?>
		<p class="description"><?php echo wp_kses_post( $args['desc'] ); ?></p>
		<?php endif;
	}
	
	/**
	 * Textarea callback
	 */
	public function textarea_callback ($args) {
		
		$kses_input = array_keys($args);
		$kses_input = array_filter($kses_input, function($key) {
			return !in_array($key, ['desc', 'info', 'type', 'value']);
		});
		$kses_input = array_flip($kses_input);
		$kses_input = array_map(function($x){
			return [];
		}, $kses_input);
		
		?><textarea <?php
			$attr = []; 
			foreach($args as $key => $value) {
				
				if( in_array($key, ['desc', 'info', 'type', 'value']) ) continue;
				
				if( $value ) {
					$attr[] = esc_attr($key) . '="' . (
						(filter_var($value, FILTER_VALIDATE_URL) !== false) && esc_url($value)===$value 
							? esc_url($value) 
							: esc_attr($value) 
					) . '"';
				}
			}
			// Sanitization and escaping is done inside a loop
			echo wp_kses(
				join( ' ', $attr ),
				[
					'textarea' => $kses_input
				]
			);
		?>><?php echo wp_kses_post( $args['value'] ); ?></textarea><?php if( $args['desc'] ?? NULL ) : ?>
		<p class="description"><?php echo wp_kses_post( $args['desc'] ); ?></p>
		<?php endif;
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
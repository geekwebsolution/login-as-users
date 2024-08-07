<?php
/*
Plugin Name: Login As Users
Description: Using this plugin, admin can access user's account in one click.
Author: Geek Code Lab
Version: 1.4.4
Author URI: https://geekcodelab.com/
Text Domain: login-as-users
*/

if(!defined('ABSPATH')) exit;

if(!defined("GWSLAU_PLUGIN_DIR_PATH"))
	define("GWSLAU_PLUGIN_DIR_PATH",plugin_dir_path(__FILE__));	
	
if(!defined("GWSLAU_PLUGIN_URL"))
	define("GWSLAU_PLUGIN_URL",plugins_url().'/'.basename(dirname(__FILE__)));

define("GWSLAU_BUILD",'1.4.4');

register_activation_hook( __FILE__, 'gwslau_reg_activation_callback' );
function gwslau_reg_activation_callback() {	

	$gwslau_loginas_status = 1;
	$gwslau_loginas_role = array("Administrator" => "Administrator");
	$gwslau_loginas_for  = array("users_page" => "users_page", "users_profile_page" => "users_profile_page", "orders_page" => "orders_page", "order_edit_page" => "order_edit_page");
	$gwslau_loginas_redirect  = '';
	$gwslau_loginas_name_show = 'user_login';
	$gwslau_loginas_sticky_position = 'left';

	$def_data = array();

	$setting = get_option('gwslau_loginas_options');
	if(!isset($setting['gwslau_loginas_status']))  			$def_data['gwslau_loginas_status'] 			= $gwslau_loginas_status;
	if(!isset($setting['gwslau_loginas_role']))  			$def_data['gwslau_loginas_role'] 			= $gwslau_loginas_role;
	if(!isset($setting['gwslau_loginas_for']))  			$def_data['gwslau_loginas_for'] 			= $gwslau_loginas_for;
	if(!isset($setting['gwslau_loginas_redirect']))  		$def_data['gwslau_loginas_redirect'] 		= $gwslau_loginas_redirect;
	if(!isset($setting['gwslau_loginas_name_show']))  		$def_data['gwslau_loginas_name_show'] 		= $gwslau_loginas_name_show;
	if(!isset($setting['gwslau_loginas_sticky_position']))  $def_data['gwslau_loginas_sticky_position'] = $gwslau_loginas_sticky_position;
	if(count($def_data) > 0)
	{
		update_option( 'gwslau_loginas_options', $def_data );
	}
}

require_once GWSLAU_PLUGIN_DIR_PATH . 'options.php';
require_once GWSLAU_PLUGIN_DIR_PATH . 'settings.php';

/**
 * Class of Login as users plugin.
 */
class user_switcher {
	
	/**
	 * Sets up all the filters and actions.
	 */
	public function init_hooks() {
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links' ) );

		// Neccessary Hooks and Filters
		add_filter( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'admin_enqueue_scripts', array( $this, 'backned_enqueue_scripts' ) );
		add_filter( 'user_has_cap', array( $this, 'filter_user_has_cap' ), 10, 4 );
		add_filter( 'map_meta_cap', array( $this, 'filter_map_meta_cap' ), 10, 4 );
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ), 1 );
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'wp_logout', 'gwslau_user_switcher_clear_olduser_cookie' );
		add_action( 'wp_login', 'gwslau_user_switcher_clear_olduser_cookie' );
		add_filter( 'removable_query_args', array( $this, 'filter_removable_query_args' ) );
		add_action( 'wp_footer', array( $this, 'action_wp_footer' ) );
	}

	/**
	 * Defines plugin action links on admin plugin listing page 
	 */
	public function plugin_action_links( $actions ) {
		$url = add_query_arg( 'page', 'login-as-user-settings', get_admin_url() . 'admin.php' );
		$actions[] = '<a href="'. esc_url( $url ) .'">Settings</a>';
		$actions = array_reverse($actions);
		return $actions;
	}

	/**
	 * Enqueue scripts
	 */
	function enqueue_scripts(){
		wp_register_style( 'gwslau-main-style', GWSLAU_PLUGIN_URL.'/assets/css/main.css', array(), GWSLAU_BUILD);
		wp_enqueue_style( 'gwslau-main-style' );

		wp_register_script( 'gwslau-main-script', GWSLAU_PLUGIN_URL.'/assets/js/login-as-users.js', array('jquery'), GWSLAU_BUILD );
		wp_enqueue_script( 'gwslau-main-script' );
	}

	/**
	 * Admin Enqueue scripts
	 */
	function backned_enqueue_scripts($hook) {
		wp_enqueue_style( 'gwslau-admin-style', GWSLAU_PLUGIN_URL.'/assets/css/admin-style.css', array(), GWSLAU_BUILD);
	}

	/**
	 * Defines the names of the cookies used by Login as users.
	 *
	 * @return void
	 */
	public function action_plugins_loaded() {
		// Login as user's auth_cookie
		if ( ! defined( 'GWSLAU_LOGIN_AS_USERS_COOKIE' ) ) {
			define( 'GWSLAU_LOGIN_AS_USERS_COOKIE', 'wordpress_user_gwslau_' . COOKIEHASH );
		}

		// Login as user's secure_auth_cookie
		if ( ! defined( 'GWSLAU_LOGIN_AS_USERS_SECURE_COOKIE' ) ) {
			define( 'GWSLAU_LOGIN_AS_USERS_SECURE_COOKIE', 'wordpress_user_gwslau_secure_' . COOKIEHASH );
		}

		// Login as user's logged_in_cookie
		if ( ! defined( 'GWSLAU_LOGIN_AS_USERS_OLDUSER_COOKIE' ) ) {
			define( 'GWSLAU_LOGIN_AS_USERS_OLDUSER_COOKIE', 'wordpress_user_gwslau_olduser_' . COOKIEHASH );
		}
	}

	/**
	 * Returns whether the current logged in user is being remembered in the form of a persistent browser cookie
	 * (ie. they checked the 'Remember Me' check box when they logged in). This is used to persist the 'remember me'
	 * value when the user switches to another user.
	 */
	public static function remember() {
		/** This filter is documented in wp-includes/pluggable.php */
		$cookie_life = apply_filters( 'auth_cookie_expiration', 172800, get_current_user_id(), false );
		$current = wp_parse_auth_cookie( '', 'logged_in' );

		if ( ! $current ) {
			return false;
		}

		// Here we calculate the expiration length of the current auth cookie and compare it to the default expiration.
		// If it's greater than this, then we know the user checked 'Remember Me' when they logged in.
		return ( intval( $current['expiration'] ) - time() > $cookie_life );
	}

	/**
	 * Loads localisation files and routes actions depending on the 'action' query var.
	 */
	public function action_init() {
		load_plugin_textdomain( 'login-as-users', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( ! isset( $_REQUEST['action'] ) ) {
			return;
		}

		$current_user = ( is_user_logged_in() ) ? wp_get_current_user() : null;

		switch ( $_REQUEST['action'] ) {

			// We're attempting to switch to another user:
			case 'gwslau_switch_to_user':
				$user_id = absint( $_REQUEST['user_id'] ?? 0 );

				// Check authentication:
				if ( ! current_user_can( 'gwslau_switch_to_user', $user_id ) ) {
					wp_die( esc_html__( 'Could not switch users.', 'login-as-users' ), 403 );
				}

				// Check intent:
				check_admin_referer( "gwslau_switch_to_user_{$user_id}" );

				// Switch user:
				$user = gwslau_switch_to_user( $user_id, self::remember() );
				if ( $user ) {
					$redirect_to = self::gwslau_get_redirect( $user, $current_user );

					// Redirect to the dashboard or the home URL depending on capabilities:
					$args = [
						'user_switched' => 'true',
					];

					if ( $redirect_to ) {
						wp_safe_redirect( add_query_arg( $args, $redirect_to ), 302, 'WordPress/Login as users' );
					}
					exit;
				} else {
					wp_die( esc_html__( 'Could not switch users.', 'login-as-users' ), 404 );
				}
				break;

			// We're attempting to switch back to the originating user:
			case 'gwslau_switch_to_olduser':
				// Fetch the originating user data:
				$old_user = self::get_old_user();
				if ( ! $old_user ) {
					wp_die( esc_html__( 'Could not switch users.', 'login-as-users' ), 400 );
				}

				// Check authentication:
				if ( ! self::authenticate_old_user( $old_user ) ) {
					wp_die( esc_html__( 'Could not switch users.', 'login-as-users' ), 403 );
				}

				// Check intent:
				check_admin_referer( "gwslau_switch_to_olduser_{$old_user->ID}" );

				// Switch user:
				if ( gwslau_switch_to_user( $old_user->ID, self::remember(), false ) ) {

					if ( ! empty( $_REQUEST['interim-login'] ) && function_exists( 'login_header' ) ) {
						$GLOBALS['interim_login'] = 'success'; // @codingStandardsIgnoreLine
						login_header( '', '' );
						exit;
					}

					$redirect_to = self::gwslau_get_redirect( $old_user, $current_user );
					$args = [
						'user_switched' => 'true',
						'switched_back' => 'true',
					];

					wp_safe_redirect( add_query_arg( $args, admin_url( 'users.php' ) ), 302, 'WordPress/Login as users' );
					exit;
				} else {
					wp_die( esc_html__( 'Could not switch users.', 'login-as-users' ), 404 );
				}
				break;

			// We're attempting to switch off the current user:
			case 'gwslau_switch_off':
				// Check authentication:
				if ( ! $current_user || ! current_user_can( 'gwslau_switch_off' ) ) {
					/* Translators: "switch off" means to temporarily log out */
					wp_die( esc_html__( 'Could not switch off.', 'login-as-users' ), 403 );
				}

				// Check intent:
				check_admin_referer( "gwslau_switch_off_{$current_user->ID}" );

				// Switch off:
				if ( gwslau_switch_off_user() ) {
					$redirect_to = self::gwslau_get_redirect( null, $current_user );
					$args = [
						'switched_off' => 'true',
					];

					if ( $redirect_to ) {
						wp_safe_redirect( add_query_arg( $args, $redirect_to ), 302, 'WordPress/Login as users' );
					} else {
						wp_safe_redirect( add_query_arg( $args, home_url() ), 302, 'WordPress/Login as users' );
					}
					exit;
				} else {
					/* Translators: "switch off" means to temporarily log out */
					wp_die( esc_html__( 'Could not switch off.', 'login-as-users' ), 403 );
				}
				break;

		}
	}

	/**
	 * Fetches the URL to redirect to for a given user (used after switching).
	 */
	protected static function gwslau_get_redirect( ?WP_User $new_user = null, ?WP_User $old_user = null ) {
		$redirect_to = '';

		if ( ! $new_user ) {
			$redirect_to = '';
		} else {
			$options = get_option( 'gwslau_loginas_options' );
			$redirect_to = home_url()."/".$options['gwslau_loginas_redirect'];
		}

		return $redirect_to;
	}


	/**
	 * Validates the old user cookie and returns its user data.
	 */
	public static function get_old_user() {
		$cookie = gwslau_user_switcher_get_olduser_cookie();
		if ( ! empty( $cookie ) ) {
			$old_user_id = wp_validate_auth_cookie( $cookie, 'logged_in' );

			if ( $old_user_id ) {
				return get_userdata( $old_user_id );
			}
		}
		return false;
	}

	/**
	 * Authenticates an old user by verifying the latest entry in the auth cookie.
	 */
	public static function authenticate_old_user( WP_User $user ) {
		$cookie = gwslau_user_switcher_get_auth_cookie();
		if ( ! empty( $cookie ) ) {
			if ( self::secure_auth_cookie() ) {
				$scheme = 'secure_auth';
			} else {
				$scheme = 'auth';
			}

			$old_user_id = wp_validate_auth_cookie( end( $cookie ), $scheme );

			if ( $old_user_id ) {
				return ( $user->ID === $old_user_id );
			}
		}
		return false;
	}


	/**
	 * Adds a 'Switch back to {user}' link to the WordPress footer if the admin toolbar isn't showing.
	 */
	public function action_wp_footer() {

		$old_user = self::get_old_user();

		if ( $old_user instanceof WP_User ) {
			$current_user = wp_get_current_user();
			$options = get_option( 'gwslau_loginas_options' );
			$loginas_sticky_class = 'gwslau-sidepane-left';
			if($options['gwslau_loginas_sticky_position'] == 'left'){
				$loginas_sticky_class = 'gwslau-sidepane-left';
			}
			else if($options['gwslau_loginas_sticky_position'] == 'right'){
				$loginas_sticky_class = 'gwslau-sidepane-right';
			}
			else if($options['gwslau_loginas_sticky_position'] == 'top'){
				$loginas_sticky_class = 'gwslau-sidepane-top';
			}
			else if($options['gwslau_loginas_sticky_position'] == 'bottom'){
				$loginas_sticky_class = 'gwslau-sidepane-bottom';
			}
			$user_name_type = $options['gwslau_loginas_name_show'];
			$user_name_data = gwslau_get_display_name($current_user->ID, $user_name_type);

			$redirect_to = self::gwslau_get_redirect( $old_user, $current_user );

			$url = add_query_arg( [
				'redirect_to' => $redirect_to,
			], self::switch_back_url( $old_user ) );

			printf(
				'<div class="gwslau_logout_box gwslau_sidepanel %s" id="gwslau_sidepanel">
					<div class="gwslau-logout-box-inner">
						<a href="javascript:void(0)" class="gwslau-closebtn">×</a>
						<div class="gwslau-logged-user-container">
							<div class="gwslau-logged-user-name">%s 
								<span>%s</span>
							</div>
							<a id="gwslau_logout_btn_logout_box" class="gwslau_btn_logout_box" href="%s">%s</a>
						</div>
					</div> 
					<div class="gwslau-logout-user-icon">
						<img src="%s" alt="User Icon">
					</div>                
				</div>',
				esc_attr($loginas_sticky_class),
				__('You are logged in as ', 'login-as-users'),
				esc_html($user_name_data),
				esc_url( $url ),
				__('Back To Your Account', 'login-as-users'),
				esc_url(GWSLAU_PLUGIN_URL . '/assets/images/user-icon.svg')
			);
		}
	}

	/**
	 * Filters the list of query arguments which get removed from admin area URLs in WordPress.
	 */
	public function filter_removable_query_args( array $args ) {
		return array_merge( $args, [
			'user_switched',
			'switched_off',
			'switched_back',
		] );
	}

	/**
	 * Returns the switch to or switch back URL for a given user.
	 */
	public static function maybe_switch_url( WP_User $user ) {
		$old_user = self::get_old_user();

		if ( $old_user && ( $old_user->ID === $user->ID ) ) {
			return self::switch_back_url( $old_user );
		} elseif ( current_user_can( 'gwslau_switch_to_user', $user->ID ) ) {
			return self::switch_to_url( $user );
		} else {
			return false;
		}
	}

	/**
	 * Returns the nonce-secured URL needed to switch to a given user ID.
	 */
	public static function switch_to_url( WP_User $user ) {
		return wp_nonce_url( add_query_arg( [
			'action' => 'gwslau_switch_to_user',
			'user_id' => $user->ID,
			'nr' => 1,
		], wp_login_url() ), "gwslau_switch_to_user_{$user->ID}" );
	}

	/**
	 * Returns the nonce-secured URL needed to switch back to the originating user.
	 */
	public static function switch_back_url( WP_User $user ) {
		return wp_nonce_url( add_query_arg( [
			'action' => 'gwslau_switch_to_olduser',
			'nr' => 1,
		], wp_login_url() ), "gwslau_switch_to_olduser_{$user->ID}" );
	}


	/**
	 * Returns whether Login as user's equivalent of the 'logged_in' cookie should be secure.
	 */
	public static function secure_olduser_cookie() {
		return ( is_ssl() && ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ) );
	}

	/**
	 * Returns whether Login as user's equivalent of the 'auth' cookie should be secure.
	 * This is used to determine whether to set a secure auth cookie.
	 */
	public static function secure_auth_cookie() {
		return ( is_ssl() && ( 'https' === wp_parse_url( wp_login_url(), PHP_URL_SCHEME ) ) );
	}

	/**
	 * Filters a user's capabilities so they can be altered at runtime.
	 */
	public function filter_user_has_cap( array $user_caps, array $required_caps, array $args, WP_User $user ) {
		$options = get_option( 'gwslau_loginas_options');

		if ( 'gwslau_switch_to_user' === $args[0] ) {
			if ( empty( $args[2] ) ) {
				$user_caps['gwslau_switch_to_user'] = false;
				return $user_caps;
			}
			if ( array_key_exists( 'switch_users', $user_caps ) ) {
				$user_caps['gwslau_switch_to_user'] = $user_caps['switch_users'];
				return $user_caps;
			}

			$user_caps['gwslau_switch_to_user'] = ( !gwslau_user_conditional($options) );
		} elseif ( 'gwslau_switch_off' === $args[0] ) {
			if ( array_key_exists( 'switch_users', $user_caps ) ) {
				$user_caps['gwslau_switch_off'] = $user_caps['switch_users'];
				return $user_caps;
			}

			$user_caps['gwslau_switch_off'] = ( !gwslau_user_conditional($options) );
		}

		return $user_caps;
	}

	/**
	 * Filters the required primitive capabilities for the given primitive or meta capability.
	 */
	public function filter_map_meta_cap( array $required_caps, $cap, $user_id, array $args ) {
		if ( 'gwslau_switch_to_user' === $cap ) {
			if ( empty( $args[0] ) || $args[0] === $user_id ) {
				$required_caps[] = 'do_not_allow';
			}
		}
		return $required_caps;
	}

	/**
	 * Singleton instantiator.
	 */
	public static function get_instance() {
		static $instance;

		if ( ! isset( $instance ) ) {
			$instance = new user_switcher();
		}

		return $instance;
	}

	/**
	 * Private class constructor. Use `get_instance()` to get the instance.
	 */
	private function __construct() {}
}

if ( ! function_exists( 'gwslau_user_switcher_set_olduser_cookie' ) ) {
	/**
	 * Sets authorisation cookies containing the originating user information.
	 */
	function gwslau_user_switcher_set_olduser_cookie( $old_user_id, $pop = false, $token = '' ) {
		$secure_auth_cookie = user_switcher::secure_auth_cookie();
		$secure_olduser_cookie = user_switcher::secure_olduser_cookie();
		$expiration = time() + 172800; // 48 hours
		$auth_cookie = gwslau_user_switcher_get_auth_cookie();
		$olduser_cookie = wp_generate_auth_cookie( $old_user_id, $expiration, 'logged_in', $token );

		if ( $secure_auth_cookie ) {
			$auth_cookie_name = GWSLAU_LOGIN_AS_USERS_SECURE_COOKIE;
			$scheme = 'secure_auth';
		} else {
			$auth_cookie_name = GWSLAU_LOGIN_AS_USERS_COOKIE;
			$scheme = 'auth';
		}

		if ( $pop ) {
			array_pop( $auth_cookie );
		} else {
			array_push( $auth_cookie, wp_generate_auth_cookie( $old_user_id, $expiration, $scheme, $token ) );
		}

		$auth_cookie = wp_json_encode( $auth_cookie );

		if ( false === $auth_cookie ) {
			return;
		}

		$scheme = 'logged_in';


		setcookie( $auth_cookie_name, $auth_cookie, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_auth_cookie, true );
		setcookie( GWSLAU_LOGIN_AS_USERS_OLDUSER_COOKIE, $olduser_cookie, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure_olduser_cookie, true );
	}
}

if ( ! function_exists( 'gwslau_user_switcher_clear_olduser_cookie' ) ) {
	/**
	 * Clears the cookies containing the originating user, or pops the latest item off the end if there's more than one.
	 */
	function gwslau_user_switcher_clear_olduser_cookie( $clear_all = true ) {
		$auth_cookie = gwslau_user_switcher_get_auth_cookie();
		if ( ! empty( $auth_cookie ) ) {
			array_pop( $auth_cookie );
		}
		if ( $clear_all || empty( $auth_cookie ) ) {

			$expire = time() - 31536000;
			setcookie( GWSLAU_LOGIN_AS_USERS_COOKIE,         ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
			setcookie( GWSLAU_LOGIN_AS_USERS_SECURE_COOKIE,  ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
			setcookie( GWSLAU_LOGIN_AS_USERS_OLDUSER_COOKIE, ' ', $expire, COOKIEPATH, COOKIE_DOMAIN );
		} else {
			if ( user_switcher::secure_auth_cookie() ) {
				$scheme = 'secure_auth';
			} else {
				$scheme = 'auth';
			}

			$old_cookie = end( $auth_cookie );

			$old_user_id = wp_validate_auth_cookie( $old_cookie, $scheme );
			if ( $old_user_id ) {
				$parts = wp_parse_auth_cookie( $old_cookie, $scheme );

				if ( false !== $parts ) {
					gwslau_user_switcher_set_olduser_cookie( $old_user_id, true, $parts['token'] );
				}
			}
		}
	}
}

if ( ! function_exists( 'gwslau_user_switcher_get_olduser_cookie' ) ) {
	/**
	 * Gets the value of the cookie containing the originating user.
	 */
	function gwslau_user_switcher_get_olduser_cookie() {
		if ( isset( $_COOKIE[ GWSLAU_LOGIN_AS_USERS_OLDUSER_COOKIE ] ) ) {
			return wp_unslash( $_COOKIE[ GWSLAU_LOGIN_AS_USERS_OLDUSER_COOKIE ] );
		} else {
			return false;
		}
	}
}

if ( ! function_exists( 'gwslau_user_switcher_get_auth_cookie' ) ) {
	/**
	 * Gets the value of the auth cookie containing the list of originating users.
	 */
	function gwslau_user_switcher_get_auth_cookie() {
		if ( user_switcher::secure_auth_cookie() ) {
			$auth_cookie_name = GWSLAU_LOGIN_AS_USERS_SECURE_COOKIE;
		} else {
			$auth_cookie_name = GWSLAU_LOGIN_AS_USERS_COOKIE;
		}

		if ( isset( $_COOKIE[ $auth_cookie_name ] ) && is_string( $_COOKIE[ $auth_cookie_name ] ) ) {
			$cookie = json_decode( wp_unslash( $_COOKIE[ $auth_cookie_name ] ) );
		}
		if ( ! isset( $cookie ) || ! is_array( $cookie ) ) {
			$cookie = [];
		}
		return $cookie;
	}
}

if ( ! function_exists( 'gwslau_switch_to_user' ) ) {
	/**
	 * Switches the current logged in user to the specified user.
	 */
	function gwslau_switch_to_user( $user_id, $remember = false, $set_old_user = true ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$old_user_id = ( is_user_logged_in() ) ? get_current_user_id() : false;
		$old_token = wp_get_session_token();
		$auth_cookies = gwslau_user_switcher_get_auth_cookie();
		$auth_cookie = end( $auth_cookies );
		$cookie_parts = $auth_cookie ? wp_parse_auth_cookie( $auth_cookie ) : false;

		if ( $set_old_user && $old_user_id ) {
			// Switching to another user
			$new_token = '';
			gwslau_user_switcher_set_olduser_cookie( $old_user_id, false, $old_token );
		} else {
			// Switching back, either after being switched off or after being switched to another user
			$new_token = $cookie_parts['token'] ?? '';
			gwslau_user_switcher_clear_olduser_cookie( false );
		}

		/**
		 * Attaches the original user ID and session token to the new session when a user switches to another user.
		 *
		 * @param array<string, mixed> $session Array of extra data.
		 * @return array<string, mixed> Array of extra data.
		 */
		$session_filter = function ( array $session ) use ( $old_user_id, $old_token ) {
			$session['switched_from_id'] = $old_user_id;
			$session['switched_from_session'] = $old_token;
			return $session;
		};

		add_filter( 'attach_session_information', $session_filter, 99 );

		wp_clear_auth_cookie();
		wp_set_auth_cookie( $user_id, $remember, '', $new_token );
		wp_set_current_user( $user_id );

		remove_filter( 'attach_session_information', $session_filter, 99 );

		if ( $old_token && $old_user_id && ! $set_old_user ) {
			// When switching back, destroy the session for the old user
			$manager = WP_Session_Tokens::get_instance( $old_user_id );
			$manager->destroy( $old_token );
		}

		return $user;
	}
}

if ( ! function_exists( 'gwslau_switch_off_user' ) ) {
	/**
	 * Switches off the current logged in user. This logs the current user out while retaining a cookie allowing them to log
	 * straight back in using the 'Switch back to {user}' system.
	 */
	function gwslau_switch_off_user() {
		$old_user_id = get_current_user_id();

		if ( ! $old_user_id ) {
			return false;
		}

		$old_token = wp_get_session_token();

		gwslau_user_switcher_set_olduser_cookie( $old_user_id, false, $old_token );
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );

		return true;
	}
}

if ( ! function_exists( 'gwslau_user_conditional' ) ) {
	/**
	 * Check logged in user can access Login as button or not
	 */
	function gwslau_user_conditional($options = array()){

		if(empty($options)){
			return true;
		}
		if(!isset($options['gwslau_loginas_status']) || $options['gwslau_loginas_status'] == 0){
			return true;
		}

		if(is_user_logged_in()) {
			$user = wp_get_current_user();
			if(isset($options['gwslau_loginas_role']) && !empty($options['gwslau_loginas_role'])){
				$in_role = false;
				foreach($options['gwslau_loginas_role'] as $name){
					$name = str_replace(' ','_',$name);
					if(in_array(strtolower($name), $user->roles)){
						$in_role = true;
					}
				}
				if(!$in_role){
					return true;
				}
			}

		}
		return false;
	}
}

if ( ! function_exists( 'gwslau_get_display_name' ) ) {
	/**
	 * Returns display name of user for Login as button.
	 */
	function gwslau_get_display_name($user_id, $name_type) {
		if (!$user = get_userdata($user_id))
			return false;
		if($name_type == 'user_login'){
			return $user->user_login;
		}
		elseif($name_type == 'firstname'){
			return $user->first_name;
		}
		elseif($name_type == 'full_name'){
			return $user->first_name.' '.$user->last_name;
		}
		elseif($name_type == 'nickname'){
			return $user->nickname;
		}
		else{
			return $user->user_login;
		}
	}
}

/* Main Instance */
$GLOBALS['user_switcher'] = user_switcher::get_instance();
$GLOBALS['user_switcher']->init_hooks();
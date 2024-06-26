<?php
/*
Plugin Name: Stop Spammers
Plugin URI: https://stopspammers.io/
Description: Secure your WordPress sites and stop spam dead in its tracks. Designed to secure your website immediately. Enhance your visitors' UX with 50+ configurable options, an allow access form, and a testing tool.
Version: 2021.5
Author: Trumani
Author URI: https://stopspammers.io/
License: https://www.gnu.org/licenses/gpl.html
Domain Path: /languages
Text Domain: stop-spammer-registrations-plugin
*/

// networking requires a couple of globals
define( 'SS_VERSION', '2021.5' );
define( 'SS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SS_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
define( 'SS_PLUGIN_DATA', plugin_dir_path( __FILE__ ) . 'data/' );
$ss_check_sempahore = false;

if ( !defined( 'ABSPATH' ) ) {
	http_response_code( 404 );
	die();
}

// making translation-ready
function ss_load_plugin_textdomain() {
	load_plugin_textdomain( 'stop-spammer-registrations-plugin', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'ss_load_plugin_textdomain' );

// load admin styles
function ss_styles() {
	wp_enqueue_style( 'ss-admin', plugin_dir_url( __FILE__ ) . 'css/admin.css' );
}
add_action( 'admin_print_styles', 'ss_styles' );

// admin notice for users
function ss_admin_notice() {
	if ( !is_plugin_active( 'stop-spammers-premium/stop-spammers-premium.php' ) ) {
		$user_id = get_current_user_id();
		if ( !get_user_meta( $user_id, 'ss_notice_dismissed_6' ) && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-info"><p>' . __( '<big><strong>Stop Spammers</strong></big> | Get even more options when you <a href="https://stopspammers.io/downloads/stop-spammers-premium/" target="_blank" class="button-primary">Upgrade to Premium</a>', 'stop-spammers' ) . '<a href="?ss-dismiss" class="alignright">' . __( 'Dismiss', 'stop-spammer-registrations-plugin' ) . '</a></p></div>';
		}
	}
}
add_action( 'admin_notices', 'ss_admin_notice' );

// dismiss admin notice for users
function ss_notice_dismissed() {
	if ( !is_plugin_active( 'stop-spammers-premium/stop-spammers-premium.php' ) ) {
		$user_id = get_current_user_id();
		if ( isset( $_GET['ss-dismiss'] ) ) {
			add_user_meta( $user_id, 'ss_notice_dismissed_6', 'true', true );
		}
	}
}
add_action( 'admin_init', 'ss_notice_dismissed' );

// WooCommerce warning for users
function ss_wc_admin_notice() {
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		$user_id = get_current_user_id();
		if ( !get_user_meta( $user_id, 'ss_wc_notice_dismissed' ) && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-info"><p style="color:purple">' . __( '<big><strong>WooCommerce Detected</strong></big> | We recommend keeping <a href="admin.php?page=ss_options">this option</a> enabled to avoid blocking customers.', 'stop-spammers' ) . '<a href="?sswc-dismiss" class="alignright">' . __( 'Dismiss', 'stop-spammer-registrations-plugin' ) . '</a></p></div>';
		}
	}
}
add_action( 'admin_notices', 'ss_wc_admin_notice' );

// dismiss WooCommerce warning for users
function ss_wc_notice_dismissed() {
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		$user_id = get_current_user_id();
		if ( isset( $_GET['sswc-dismiss'] ) ) {
			add_user_meta( $user_id, 'ss_wc_notice_dismissed', 'true', true );
		}
	}
}
add_action( 'admin_init', 'ss_wc_notice_dismissed' );

// hook the init event to start work
add_action( 'init', 'ss_init', 0 );

// dummy filters for addons
add_filter( 'ss_addons_allow', 'ss_addons_d', 0 );
add_filter( 'ss_addons_deny', 'ss_addons_d', 0 );
add_filter( 'ss_addons_get', 'ss_addons_d', 0 );

// done - the reset will be done in the init event
/*******************************************************************
 * How it works:
 * 1) Network blog MU installation
 * if networked switch then install network filters for options
 * all options will redirect to Blog #1 options so no need to set up for each blog
 * else
 * normal install
 * 2) Case when user is logged in
 * setup user and comment actions
 * if networked
 * set up right-now and options to install only on Network Admin page
 * else
 * install hooks and filters for right-now options screens
 * 3) When user is not logged in
 * hook template redirect
 * hook init
 * 4) on template redirect check for bad requests and 404s on exploit items
 * 5) on init check for POST or GET
 * 6) on post gather post variables and check for spam, logins, or exploits
 * 7) on get check for access blocking
 * 8) on deny
 * update counters
 * update cache
 * update log
 * present rejection screen - could contain email request form or CAPTCHA
 * 9) on email request form send email to admin requesting Allow List
 * 10) on CAPTCHA success add to Good Cache, remove from Bad Cache, update counters, log success
 */
function ss_init() {
	remove_action( 'init', 'ss_init' );
	add_filter( 'pre_user_login', 'ss_user_reg_filter', 1, 1 );
	// incompatible with a Jetpack submit
	if ( $_POST != null && array_key_exists( 'jetpack_protect_num', $_POST ) ) {
		return;
	}
	// eMember trying to log in - disable plugin for eMember logins
	if ( function_exists( 'wp_emember_is_member_logged_in' ) ) {
		// only eMember function I could find after 30 seconds of Googling
		if ( !empty( $_POST ) && array_key_exists( 'login_pwd', $_POST ) ) {
			return;
		}
	}
	// set up the Akismet hit
	add_action( 'akismet_spam_caught', 'ss_log_akismet' ); // hook Akismet spam
	$muswitch = 'N';
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		switch_to_blog( 1 );
		$muswitch = get_option( 'ss_muswitch' );
		if ( $muswitch != 'N' ) {
			$muswitch = 'Y';
		}
		restore_current_blog();
		if ( $muswitch == 'Y' ) {
			// install the hooks for options
			define( 'SS_MU', $muswitch );
			ss_sp_require( 'includes/ss-mu-options.php' );
			ssp_global_setup();
		}
	} else {
		define( 'SS_MU', $muswitch );
	}
	if ( function_exists( 'is_user_logged_in' ) ) {
		// check to see if we need to hook the settings
		// load the settings if logged in
		if ( is_user_logged_in() ) {
			remove_filter( 'pre_user_login', 'ss_user_reg_filter', 1 );
			if ( current_user_can( 'manage_options' ) ) {
				ss_sp_require( 'includes/ss-admin-options.php' );
				return;
			}
		}
	}
	// user is not logged in - we can do checks
	// add the new user hooks
	global $wp_version;
	if ( !version_compare( $wp_version, "3.1", "<" ) ) { // only in newer versions
		add_action( 'user_register', 'ss_new_user_ip' );
		add_action( 'wp_login', 'ss_log_user_ip', 10, 2 );
	}
	// don't do anything else if the eMember is logged in
	if ( function_exists( 'wp_emember_is_member_logged_in' ) ) {
		if ( wp_emember_is_member_logged_in() ) {
			return;
		}
	}
	// can we check for $_GET registrations?
	if ( isset( $_POST ) && !empty( $_POST ) ) {
		// see if we are returning from a deny
		if ( array_key_exists( 'ss_deny', $_POST ) && array_key_exists( 'kn', $_POST ) ) {
			// deny form hit
			$knonce = $_POST['kn'];
			if ( !empty( $knonce ) && wp_verify_nonce( $knonce, 'ss_stopspam_deny' ) ) {
				// call the checker program
				sfs_errorsonoff();
				$options = ss_get_options();
				$stats   = ss_get_stats();
				$post	 = get_post_variables();
				be_load( 'ss_challenge', ss_get_ip(), $stats, $options, $post );
				// if we come back we continue as normal
				sfs_errorsonoff( 'off' );
				return;
			}
		}
		// need to check that we are not Allow Listed
		// don't check if IP is Google, etc.
		// check to see if we are doing a post with values
		// better way to check for Jetpack
		if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'protect' ) ) {
			return;
		}
		$post = get_post_variables();
		if ( !empty( $post['email'] ) || !empty( $post['author'] ) || !empty( $post['comment'] ) ) { // must be a login or a comment which require minimum stuff
			// remove_filter( 'pre_user_login', 'ss_user_reg_filter', 1 );
			// sfs_debug_msg( 'email or author ' . print_r( $post, true ) );
			$reason = ss_check_white();
			if ( $reason !== false ) {
				// sfs_debug_msg( "return from white $reason" );
				return;
			}
			// sfs_debug_msg( 'past white ' );
			ss_check_post(); // on POST check if we need to stop comments or logins
		} else {
		// sfs_debug_msg( 'no email or author ' . print_r( $post, true ) );
		}
	} else {
		// this is a get - check for get addons
		$addons = array();
		$addons = apply_filters( 'ss_addons_get', $addons );
		// these are the allow before addons
		// returns array 
		// [0] = class location
		// [1] = class name (also used as counter)
		// [2] = addon name
		// [3] = addon author
		// [4] = addon description
		if ( !empty( $addons ) && is_array( $addons ) ) {
			foreach ( $addons as $add ) {
				if ( !empty( $add ) && is_array( $add ) ) {
					$options = ss_get_options();
					$stats   = ss_get_stats();
					$post	 = get_post_variables();
					$reason  = be_load( $add, ss_get_ip(), $stats, $options );
					if ( $reason !== false ) {
						// need to log a passed hit on post here
						remove_filter( 'pre_user_login', 'ss_user_reg_filter', 1 );
						ss_log_bad( ss_get_ip(), $reason, $add[1], $add );
						return;
					}
				}
			}
		}
	}
	add_action( 'template_redirect', 'ss_check_404s' ); // check missed hits for robots scanning for exploits
	add_action( 'ss_stop_spam_caught', 'ss_caught_action', 10, 2 ); // hook stop spam  - for testing
	add_action( 'ss_stop_spam_ok', 'ss_stop_spam_ok', 10, 2 ); // hook stop spam - for testing
}

// start of loadable functions
function ss_sp_require( $file ) {
	require_once( $file );
}

/************************************************************
 * function ss_sfs_check_admin()
 * checks to see if the current admin can login
 *************************************************************/
function ss_sfs_check_admin() {
	ss_sfs_reg_add_user_to_allowlist(); // also saves options
}

function ss_sfs_reg_add_user_to_allowlist() {
	$options = ss_get_options();
	$stats   = ss_get_stats();
	$post	 = get_post_variables();
	return be_load( 'ss_addtoallowlist', ss_get_ip(), $stats, $options );
}

function ss_set_stats( &$stats, $addon = array() ) {
	// this sets the stats
	if ( empty( $addon ) || !is_array( $addon ) ) {
		// need to know if the spam count has changed
		if ( $stats['spcount'] == 0 || empty( $stats['spdate'] ) ) {
			$stats['spdate'] = date( 'Y/m/d', time() + ( get_option( 'gmt_offset' ) * 3600 ) );
		}
		if ( $stats['spmcount'] == 0 || empty( $stats['spmdate'] ) ) {
			$stats['spmdate'] = date( 'Y/m/d', time() + ( get_option( 'gmt_offset' ) * 3600 ) );
		}
	} else {
		// update addon stats
		// addon stats are kept in addonstats array in stats
		$addonstats = array();
		if ( array_key_exists( 'addonstats', $stats ) ) {
			$addonstats = $stats['addonstats'];
		}
		$addstats = array();
		if ( array_key_exists( $addon[1], $addonstats ) ) {
			$addstats = $addonstats[ $addon[1] ];
		} else {
			$addstats = array( 0, $addon );
		}
		$addstats[0] ++;
		$addonstats[ $addon[1] ] = $addstats;
		$stats['addonstats']	 = $addonstats;
	}
	// other checks? - I might start compressing this, since it can get large
	update_option( 'ss_stop_sp_reg_stats', $stats );
}

function ss_get_now() {
	// to use a safe date everywhere
	if ( function_exists( 'date_default_timezone_set' ) ) {
		date_default_timezone_set( 'UTC' ); // WP is a UTC base date - if default tz is not set this fixes it
	}
	$now = date( 'Y/m/d H:i:s', time() + ( get_option( 'gmt_offset' ) * 3600 ) );
}

function ss_get_stats() {
	$stats = get_option( 'ss_stop_sp_reg_stats' );
	if ( !empty( $stats ) && is_array( $stats ) && array_key_exists( 'version', $stats ) && $stats['version'] == SS_VERSION ) {
		return $stats;
	}
	return be_load( 'ss_get_stats', '' );
}

function ss_get_options() {
	$options = get_option( 'ss_stop_sp_reg_options' );
	$st	     = array();
	if ( !empty( $options ) && is_array( $options ) && array_key_exists( 'version', $options ) && $options['version'] == SS_VERSION ) {
		return $options;
	}
	return be_load( 'ss_get_options', '' );
}

function ss_set_options( $options ) {
	update_option( 'ss_stop_sp_reg_options', $options );
}

function ss_get_ip() {
	$ip = $_SERVER['REMOTE_ADDR'];
	return $ip;
}

function ss_admin_menu() {
	if ( !function_exists( 'ss_admin_menu_l' ) ) {
		ss_sp_require( 'settings/settings.php' );
	}
	sfs_errorsonoff();
	ss_admin_menu_l();
	sfs_errorsonoff( 'off' );
}

function ss_check_site_get() {
	$options = ss_get_options();
	$stats   = ss_get_stats();
	$post	 = get_post_variables();
	sfs_errorsonoff();
	$ret = be_load( 'ss_check_site_get', ss_get_ip(), $stats, $options, $post );
	sfs_errorsonoff( 'off' );
	return $ret;
}

function ss_check_post() {
	sfs_errorsonoff();
	$options = ss_get_options();
	$stats   = ss_get_stats();
	$post	 = get_post_variables();
	$ret	 = be_load( 'ss_check_post', ss_get_ip(), $stats, $options, $post );
	sfs_errorsonoff( 'off' );
	return $ret;
}

function ss_check_404s() { // check for exploits on 404s
	sfs_errorsonoff();
	$options = ss_get_options();
	$stats   = ss_get_stats();
	$ret	 = be_load( 'ss_check_404s', ss_get_ip(), $stats, $options );
	sfs_errorsonoff( 'off' );
	return $ret;
}

function ss_log_bad( $ip, $reason, $chk, $addon = array() ) {
	$options		= ss_get_options();
	$stats		    = ss_get_stats();
	$post		    = get_post_variables();
	$post['reason'] = $reason;
	$post['chk']	= $chk;
	$post['addon']  = $addon;
	return be_load( 'ss_log_bad', ss_get_ip(), $stats, $options, $post );
}

function ss_log_akismet() {
	sfs_errorsonoff();
	$options = ss_get_options();
	$stats   = ss_get_stats();
	if ( $options['chkakismet'] != 'Y' ) {
		return false;
	}
	// check whitelists first
	$reason = ss_check_white();
	if ( $reason !== false ) {
		return;
	}
	// not on Allow Lists
	$post		    = get_post_variables();
	$post['reason'] = __( 'from Akismet', 'stop-spammer-registrations-plugin' );
	$post['chk']	= 'chkakismet';
	$ansa		    = be_load( 'ss_log_bad', ss_get_ip(), $stats, $options, $post );
	sfs_errorsonoff( 'off' );
	return $ansa;
}

function ss_log_good( $ip, $reason, $chk, $addon = array() ) {
	$options		= ss_get_options();
	$stats		    = ss_get_stats();
	$post		    = get_post_variables();
	$post['reason'] = $reason;
	$post['chk']	= $chk;
	$post['addon']  = $addon;
	return be_load( 'ss_log_good', ss_get_ip(), $stats, $options, $post );
}

function ss_check_white() {
	sfs_errorsonoff();
	$options = ss_get_options();
	$stats   = ss_get_stats();
	$post	 = get_post_variables();
	$ansa	 = be_load( 'ss_check_white', ss_get_ip(), $stats, $options, $post );
	sfs_errorsonoff( 'off' );
	return $ansa;
}

function ss_check_white_block() {
	sfs_errorsonoff();
	$options	   = ss_get_options();
	$stats		   = ss_get_stats();
	$post		   = get_post_variables();
	$post['block'] = true;
	$ansa		   = be_load( 'ss_check_white', ss_get_ip(), $stats, $options, $post );
	sfs_errorsonoff( 'off' );
	return $ansa;
}

function be_load( $file, $ip, &$stats = array(), &$options = array(), &$post = array() ) {
	// all classes have a process() method
	// all classes have the same name as the file being loaded
	// only executes the file if there is an option set with value 'Y' for the name
	if ( empty( $file ) ) {
		return false;
	}
	// load the be_module if does not exist
	if ( !class_exists( 'be_module' ) ) {
		require_once( 'classes/be_module.class.php' );
	}
	// if ( $ip == null ) $ip = ss_get_ip();
	// for some loads we use an absolute path
	// if it is an addon, it has the absolute path to the be_module
	if ( is_array( $file ) ) { // add-ons pass their array
		// this is an absolute location so load it directly
		if ( !file_exists( $file[0] ) ) {
			sfs_debug_msg( __( 'not found ', 'stop-spammer-registrations-plugin' ) . print_r( $add, true ) );
			return false;
		}
		// require_once( $file[0] );
		// this loads a be_module class
		$class  = new $file[1]();
		$result = $class->process( $ip, $stats, $options, $post );
		$class  = null;
		unset( $class ); // doesn't do anything
		// memory_get_usage( true ); // force a garage collection
		return $result;
	}
	$ppath = plugin_dir_path( __FILE__ ) . 'classes/';
	$fd	= $ppath . $file . '.php';
	$fd	= str_replace( "/", DIRECTORY_SEPARATOR, $fd ); // Windows fix
	if ( !file_exists( $fd ) ) {
		// echo "<br /><br />Missing $file $fd<br /><br />";
		$ppath = plugin_dir_path( __FILE__ ) . 'modules/';
		$fd	= $ppath . $file . '.php';
		$fd	= str_replace( "/", DIRECTORY_SEPARATOR, $fd ); // Windows fix
	}
	if ( !file_exists( $fd ) ) {
		$ppath = plugin_dir_path( __FILE__ ) . 'modules/countries/';
		$fd	= $ppath . $file . '.php';
		$fd	= str_replace( "/", DIRECTORY_SEPARATOR, $fd ); // Windows fix
	}
	if ( !file_exists( $fd ) ) {
		_e( '<br /><br />Missing ' . $file, $fd . '<br /><br />', 'stop-spammer-registrations-plugin' );
		return false;
	}
	require_once( $fd );
	// this loads a be_module class
	// sfs_debug_msg( "loading $fd" );
	$class  = new $file();
	$result = $class->process( $ip, $stats, $options, $post );
	$class  = null;
	unset( $class ); // does nothing - take out
	return $result;
}

// this should be moved to a dynamic load, perhaps - it is one of the most common things
function get_post_variables() {
	// for WordPress and other login and comment programs
	// need to find: login password comment author email
	// copied from stop spammers plugin
	// made generic so it also checks "head" and "get" (as well as cookies)
	$p	  = $_POST;
	$ansa = array(
		'email'   => '',
		'author'  => '',
		'pwd'	  => '',
		'comment' => '',
		'subject' => '',
		'url'	  => ''
	);
	if ( empty( $p ) || !is_array( $p ) ) {
		return $ansa;
	}
	$search  = array(
		'email'   => array( 'user_email', 'email', 'address' ),
		// 'input_' = WooCommerce forms
		'author'  => array(
			'author',
			'name',
			'user_login',
			'signup_for',
			'log',
			'user',
			'name',
			'_id'
		),
		'pwd'	  => array( 'psw', 'pwd', 'pass', 'secret' ),
		'comment' => array( 'comment', 'message', 'body', 'excerpt' ),
		'subject' => array( 'subj', 'topic' ),
		'url'	  => array( 'url', 'blog_name', 'blogname' )
	);
	$emfound = false;
	// rewrite this
	foreach ( $search as $var => $sa ) {
		foreach ( $sa as $srch ) {
			foreach ( $p as $pkey => $pval ) {
				// see if the things in $srch live in post
				if ( stripos( $pkey, $srch ) !== false ) {
					// got a hit
					if ( is_array( $pval ) ) {
						$pval = print_r( $pval, true );
					}
					$ansa[ $var ] = $pval;
					break;
				}
			}
			if ( !empty( $ansa[ $var ] ) ) {
				break;
			}
		}
		if ( empty( $ansa[ $var ] ) && $var == 'email' ) { // empty email
			// did not get a hit so we need to try again and look for something that looks like an email
			foreach ( $p as $pkey => $pval ) {
				if ( stripos( $pkey, 'input_' ) ) {
					// might have an email
					if ( is_array( $pval ) ) {
						$pval = print_r( $pval, true );
					}
					if ( strpos( $pval, '@' ) !== false && strrpos( $pval, '.' ) > strpos( $pval, '@' ) ) {
						// close enough
						$ansa[ $var ] = $pval;
						break;
					}
				}
			}
		}
	}
	/*	
	foreach ( $search as $var => $sa ) {
	foreach ( $sa as $srch ) {
	foreach ( $p as $pkey => $pval ) {
	if ( is_string( $pval ) && !is_array( $pval ) ) { // WooCommerce fix - overkill
	if ( strpos( $pkey, $srch ) !==false ) {
	if ( $var=='email' && strpos( $pval, '@' ) !== false && strrpos( $pval, '.' ) > strpos( $pval, '@' ) ) { // only valid with @ before last dot sign and .
	$ansa[$var] = $pval;
	$emfound = true;
	break;
	} else { // no @ sign - save for now, hope for better
	if ( empty( $ansa[$var] ) ) $ansa[$var] = $pval;
	}
	}
	if ( $var != 'email' && $emfound ) break; // keep checking email even if we have one - look for better
	}
	}
	}
	}
	*/
	// sanitize input - some of this is stored in history and needs to be cleaned up
	foreach ( $ansa as $key => $value ) {
		// clean the variables even more
		$ansa[$key] = sanitize_text_field( $value ); // really clean gets rid of high value characters
	}
	if ( strlen( $ansa['email'] ) > 80 ) {
		$ansa['email'] = substr( $ansa['email'], 0, 77 ) . '...';
	}
	if ( strlen( $ansa['author'] ) > 80 ) {
		$ansa['author'] = substr( $ansa['author'], 0, 77 ) . '...';
	}
	if ( strlen( $ansa['pwd'] ) > 32 ) {
		$ansa['pwd'] = substr( $ansa['pwd'], 0, 29 ) . '...';
	}
	if ( strlen( $ansa['comment'] ) > 999 ) {
		$ansa['comment'] = substr( $ansa['comment'], 0, 996 ) . '...';
	}
	if ( strlen( $ansa['subject'] ) > 80 ) {
		$ansa['subject'] = substr( $ansa['subject'], 0, 77 ) . '...';
	}
	if ( strlen( $ansa['url'] ) > 80 ) {
		$ansa['url'] = substr( $ansa['url'], 0, 77 ) . '...';
	}
	// print_r( $ansa );
	// exit;
	return $ansa;
}

function ss_addons_d( $config = array() ) {
	// dummy function for testing
	return $config;
}

function ss_caught_action( $ip = '', $post = array() ) {
	// this is hit on spam detect for addons - added this for a template for testing - not needed
	// $post has all the standardized post variables plus reason and the chk that found the problem
	// good add-on would be a plugin to manage an SQL table where this stuff is stored
}

function ss_stop_spam_ok( $ip = '', $post = array() ) {
	// dummy function for testing
	// unreports spam
}

function really_clean( $s ) {
	// try to get all non-7-bit things out of the string
	if ( empty( $s ) ) {
		return '';
	}
	$ss = array_slice( unpack( "c*", "\0" . $s ), 1 );
	if ( empty( $ss ) ) {
		return $s;
	}
	$s = '';
	for ( $j = 0; $j < count( $ss ); $j ++ ) {
		if ( $ss[ $j ] < 127 && $ss[ $j ] > 31 ) {
			$s .= pack( 'C', $ss[ $j ] );
		}
	}
	return $s;
}

function load_be_module() {
	if ( !class_exists( 'be_module' ) ) {
		require_once( 'classes/be_module.class.php' );
	}
}

function ss_new_user_ip( $user_id ) {
	$x  = $_SERVER['REQUEST_URI'];
	$ip = ss_get_ip();
	// sfs_debug_msg( "Checking reg filter login $x ( ss_user_ip ) = " . $ip . ", method = " . $_SERVER['REQUEST_METHOD'] . ", request = " . print_r( $_REQUEST, true ) );
	// check to see if the user is OK
	// add the users IP to new users
	update_user_meta( $user_id, 'signup_ip', $ip );
}

function ss_sfs_ip_column_head( $column_headers ) {
	$column_headers['signup_ip'] = 'User IP';
	return $column_headers;
}

function ss_log_user_ip( $user_login = "", $user = "" ) {
	if ( empty( $user ) ) {
		return;
	}
	if ( empty( $user_login ) ) {
		return;
	}
	// add the user's IP to new users
	if ( !isset( $user->ID ) ) {
		return;
	}
	$user_id = $user->ID;
	// $ip = ss_get_ip();
	$ip		 = $_SERVER['REMOTE_ADDR'];
	$oldip   = get_user_meta( $user_id, 'signup_ip', true );
	if ( empty( $oldip ) || $ip != $oldip ) {
		update_user_meta( $user_id, 'signup_ip', $ip );
	}
}

/***********************************
 * $user_email = apply_filters( 'user_registration_email', $user_email );
 * I am going to start checking this filter for registrations
 * add_filter( 'user_registration_email', ss_user_reg_filter, 1, 1 );
 ***********************************/
function ss_user_reg_filter( $user_login ) {
	// the plugin should be all initialized
	// check the IP, etc.
	sfs_errorsonoff();
	$options		= ss_get_options();
	$stats		    = ss_get_stats();
	// fake out the post variables
	$post		    = get_post_variables();
	$post['author'] = $user_login;
	$post['addon']  = 'chkRegister'; // not really an add-on - but may be moved out when working
	if ( $options['filterregistrations'] != 'Y' ) {
		remove_filter( 'pre_user_login', 'ss_user_reg_filter', 1 );
		sfs_errorsonoff( 'off' );
		return $user_login;
	}
	// if the suspect is already in the Bad Cache he does not get a second chance?
	// prevents looping	
	$reason = be_load( 'chkbcache', ss_get_ip(), $stats, $options, $post );
	sfs_errorsonoff();
	if ( $reason !== false ) {
		$rejectmessage  = $options['rejectmessage'];
		$post['reason'] = __( 'Failed Registration: Bad Cache', 'stop-spammer-registrations-plugin' );
		$host['chk']	= 'chkbcache';
		$ansa		    = be_load( 'ss_log_bad', ss_get_ip(), $stats, $options, $post );
		wp_die( '$rejectmessage', __( 'Login Access Denied', 'stop-spammer-registrations-plugin' ), array( 'response' => 403 ) );
		exit();
	}
	// check periods
	$reason = be_load( 'chkperiods', ss_get_ip(), $stats, $options, $post );
	if ( $reason !== false ) { 
		wp_die( 'Registration Access Denied', __( 'Login Access Denied', 'stop-spammer-registrations-plugin' ), array( 'response' => 403 ) );
	}
	// check the whitelist
	$reason = ss_check_white();
	sfs_errorsonoff();
	if ( $reason !== false ) {
		$post['reason'] = __( 'Passed Registration:', 'stop-spammer-registrations-plugin' ) . $reason;
		$ansa		    = be_load( 'ss_log_good', ss_get_ip(), $stats, $options, $post );
		sfs_errorsonoff( 'off' );
		return $user_login;
	}
	// check the blacklist
	// sfs_debug_msg( "Checking blacklist on registration: /r/n".print_r( $post, true ) );
	$ret			= be_load( 'ss_check_post', ss_get_ip(), $stats, $options, $post );
	$post['reason'] = __( 'Passed Registration ', 'stop-spammer-registrations-plugin' ) . $ret;
	$ansa		    = be_load( 'ss_log_good', ss_get_ip(), $stats, $options, $post );
	return $user_login;
}

// private mode
function login_redirect() {
	global $pagenow, $post;
	$options = ss_get_options();
	if ( get_option( 'ssp_enable_custom_login', '' ) and $options['ss_private_mode'] == "Y" and ( !is_user_logged_in() && $post->post_name != 'login' ) ) {
		wp_redirect( site_url( 'login' ) ); exit;
	} else if ( $options['ss_private_mode'] == "Y" and ( !is_user_logged_in() && ( $pagenow != 'wp-login.php' and $post->post_name != 'login' ) ) ) {
		auth_redirect();
	}
}
add_action( 'wp', 'login_redirect' );

// action links
function ss_summary_link( $links ) {
	$links = array_merge( array( '<a href="' . admin_url( 'admin.php?page=stop_spammers' ) . '">' . __( 'Settings', 'stop-spammer-registrations-plugin' ) . '</a>' ), $links );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'ss_summary_link' );

function check_for_premium() {
	if ( !is_plugin_active( 'stop-spammers-premium/stop-spammers-premium.php' ) ) {
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'ss_upgrade_link' );
		function ss_upgrade_link( $links ) {
			$links = array_merge( array( '<a href="https://stopspammers.io/" title="' . esc_attr__( 'Get Maximum Dynamic Security', 'stop-spammer-registrations-plugin' ) . '" target="_blank" style="font-weight:bold">' . __( 'Upgrade', 'stop-spammer-registrations-plugin' ) . '</a>' ), $links );
			return $links;
		}
	}
}
add_action( 'admin_init', 'check_for_premium' );

require_once( 'includes/stop-spam-utils.php' );
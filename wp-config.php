<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */

//fix for wordpress ssl

if($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'){

    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

define ('WP_MEMORY_LIMIT', '256M');
define( 'DB_NAME', 'wordpress_db' );

/** MySQL database username */
define( 'DB_USER', 'wp_user' );

/** MySQL database password */
define( 'DB_PASSWORD', 'wp_password' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
//define( 'WP_HOME', 'http://159.65.158.206:8000/wordpress/' );
//define( 'WP_SITEURL', 'http://159.65.158.206:8000/wordpress/' );
define( 'AUTH_KEY',         'u?raIMMxY8`$>I$l;]JMQ/+[!6N}[V-qc[V.VU}/x~(]nSh4/xxF_RIYB0Jg}.{j' );
define( 'SECURE_AUTH_KEY',  '>GiR524CjIIK+waD2pKO[05USubvn^G*h:^1L_%Pa{*8hP;vGB]sfc9f}T@)8|nO' );
define( 'LOGGED_IN_KEY',    'i:rkzo:W)@)G<+am~)VXGDWCOn5 |QofC44CfiH,J$bi16sNLTg:Dp05-{atct-9' );
define( 'NONCE_KEY',        'BH&%gHkLqUKZN-GKcND1Y2ie.![sy+B24BL#z%zP[[Jk}a#O:17%n(dUKSrxtF+^' );
define( 'AUTH_SALT',        'm?$%Y1#hl5wSm`mYC4N/8Qid||o-GbLE_yN%iD0QT#~m<F)0nvq!K^bS+RY?eA.B' );
define( 'SECURE_AUTH_SALT', '2_|tA:27q]ru-?i8H{2Mvzu+w4obIJ&%A_&$(60J.sD5*D jmXBAXCEp3U%1}a#y' );
define( 'LOGGED_IN_SALT',   'k/B2H&w9}r$z7Kp{8VO:P<.$9uyFVwB47eU,&k4a.>xr?=gGq[^4.oqi.kfNhRoA' );
define( 'NONCE_SALT',       'f*D?xI?J|1iKggb|i<v^l.@-)X*H;&56y3T-}i1`<*?_D]vyAh*^)8ck-gEPFHr)' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
//define( 'WP_DEBUG',true);
// Enable debug logging to the wp-content/debug.log file
//define('WP_DEBUG_LOG', true);
// Enable debug logging on frontend
//define('WP_DEBUG_DISPLAY', false);

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors',0);
@ini_set('track_errors',1);
define( 'SCRIPT_DEBUG', true );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

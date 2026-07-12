<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'user' );

/** Database password */
define( 'DB_PASSWORD', 'password' );

/** Database hostname */
define( 'DB_HOST', 'db' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'MQpJj6m{xSe4Oi!Pc6ozAYL^{%Qx0Im^)0j*t%piT<+GBOf/$A`>D<vKxUFh6<13' );
define( 'SECURE_AUTH_KEY',  'adZRs I56p$4WzjlO8|=f-K=$4 8d&n;% eQ:M:oFtm+/Y:iT=8T_iM4A)9d3U1g' );
define( 'LOGGED_IN_KEY',    '5y1nBo Zy(PkGp 0oe=vJXf0>;F}C/aq8,E_ry;~p$>5TZD5B?7R#Qr!p3M6xz1+' );
define( 'NONCE_KEY',        ',L?h0XxM`lS-zy2E_YywIASjb @cmFbOl`ohhP/>k2I$wbDis}<{.@[t(==`Pw*%' );
define( 'AUTH_SALT',        'Yc?uKCfXud7;$A2>MK1Vt[YF{R6drKY=C35G/>?u!0$C2Zy9t#c/j2h;f)avk1G&' );
define( 'SECURE_AUTH_SALT', 'K<ToaUY%qKyKff]bzdKRFNZJdh@2C>KdAKraR-kFm :hP-4(wW)dMbYHBk%]ymGo' );
define( 'LOGGED_IN_SALT',   '*xo9QzJD8y8RO?x&6BHmJQftTr(c/06fKuNT9OWDS2)I+:Uld2Ud5`<WRt]]/Snm' );
define( 'NONCE_SALT',       '#%Bfl!:=`nqyf#tim&M^sf`O/f(JhYj@9T>aX[!WRT+O!#j<}37d&/F~L!w.H}w1' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'ex_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

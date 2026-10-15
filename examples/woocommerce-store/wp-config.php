<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'woocommerce' );

/** Database username */
define( 'DB_USER', 'woocommerce_user' );

/** Database password */
define( 'DB_PASSWORD', 'your_secure_password' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'u`a00Q<1-KO=t@oknN%4<Nkx7(u;> +V<u!;e%*QUA.%6~s~da.(HXd- ~e|D8N3' );
define( 'SECURE_AUTH_KEY',   'q;f)0+SL?`|@>_dy.1<ptsll~P)Cqkj+]qu=R&Oa`S$>1;L+z_Y]Q[ye5699>,EE' );
define( 'LOGGED_IN_KEY',     'mGLT-$K%%F0n=4WtexuIbz;Mzb.M7TC:[0>G1?v!ra5$--2CV>n2^>c+~*?h]#bE' );
define( 'NONCE_KEY',         '~T{OyM_Y!+]+></zi*{fL&HjWXQPfUIe}$sF$%AZ3@FG];|s{AP=7}aa8IO9Pez}' );
define( 'AUTH_SALT',         'mK3klEes5}+AflK68S>i<5_2|GN|~K[,5j#,#[4jj^BHhH[ 760W7+(&:}(JR%Wn' );
define( 'SECURE_AUTH_SALT',  ':?MXf&HvM*lAd!3f+S}i#2b4V4*9pudq8-~:Y5xH ]!$XVXZNU@2C$)!1?[BtO@]' );
define( 'LOGGED_IN_SALT',    '|n #n()T_GIeHJwnAOJ2PqFv~7;j6W*cX>O(U2{pe QyX9#sJG[CeeiokBzZvEcV' );
define( 'NONCE_SALT',        'w14j;zz,[wAEqM-U7]J.Z(G<)cv0(5V6[*5|pH~onT*1$#m=Se##imF.R5|wUSV>' );
define( 'WP_CACHE_KEY_SALT', '7*N`#<X.iuNm2!rwn{ eXV!BN9&YE*:*799)SRO|8TcLu,D-^YruI]Vk?jo@(_Mb' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

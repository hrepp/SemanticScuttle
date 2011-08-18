<?php
/**
 * Configuration for SemanticScuttle.
 *
 * Copy this file to config.inc.php and adjust it.
 *
 * See config.default.inc.php for more options.
 */


$root = 'http://localhost/ewurzel/semscu/';


/**
 * The name of this site.
 *
 * @var string
 */
$sitename = 'Neptun-SemanticScuttle';

/**
 * The welcome message on the homepage.
 *
 * @var string
 */
$welcomeMessage = '';

/**
 * Translation from locales/ folder.
 *
 * Examples: de_DE, en_GB, fr_FR
 *
 * @var string
 */
$locale = 'de_DE';

/**
 * Use clean urls without .php filenames.
 * Requires mod_rewrite (for Apache) to be active.
 *
 * @var boolean
 */
$cleanurls = false;

/**
 * Show debug messages.
 * This setting is recommended when setting up SemanticScuttle,
 * and when hacking on it.
 *
 * @var boolean
 */
//$debugMode = true;
$debugMode = false;

/***************************************************
 * Database configuration
 */

/**
 * Database driver
 *
 * available:
 * mysql4, mysqli, mysql, oracle, postgres, sqlite, db2, firebird,
 * mssql, mssq-odbc
 *
 * @var string
 */
$dbtype = 'mysql';
/**
 * Database username
 *
 * @var string
 */
$dbuser = 'semscuttle';

/**
 * Database password
 *
 * @var string
 */
$dbpass = 'testest';

/**
 * Name of database
 *
 * @var string
 */
$dbname = 'neptun_semscuttle';

/**
 * Database hostname/IP
 *
 * @var string
 */
$dbhost = '127.0.0.1';


/***************************************************
 * Users
 */

/**
 * Contact address for the site administrator.
 * Used as the FROM address in password retrieval e-mails.
 *
 * @var string
 */
$adminemail = 'hr.epprecht@ewurzel.ch';

/**
 * Array of user names who have admin rights
 *
 * Example:
 * <code>
 * $admin_users = array('adminnickname', 'user1nick', 'user2nick');
 * </code>
 *
 * @var array
 */
$admin_users = array('hrepp');


/***************************************************
 * Bookmarks 
 */

/**
 * Default privacy setting for bookmarks.
 * 0 - Public
 * 1 - Shared with Watchlist
 * 2 - Private
 *
 * @var integer 
 */
$defaults['privacy'] = 2;


/**
* You have completed the basic configuration!
* More options can be found in config.default.php.
*/

// neu eingeführte Variable zum Darstellen der Tags (vgl. alltags.php)
// $defaultTagSortOrder = '' ;
$defaultTagSortOrder = 'alphabet_asc' ;
// $defaultTagSortOrder = 'popularity_asc' ;

?>

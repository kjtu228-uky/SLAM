<?php

use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

/**
 * Configuration settings for the application.
 *
 * @author  Kyle Tuck <kjtu228@uky.edu>
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('vendor/autoload.php');
###
###  Application settings
###
// Uncomment the next line to log all PHP messages
//  error_reporting(E_ALL);
// Set the application logging level
Util::$logLevel = LogLevel::Error;

// Specify a prefix (starting with '/') when the REQUEST_URI server variable is missing the first part of the real path
define('REQUEST_URI_PREFIX', '');

###
###  App specific settings
###
define('TOOL_ID', 'slam13');
define('SESSION_NAME', 'slam13');
define('TOOL_BASE_URL', ''); // e.g. https://your_domain.com/SLAM/
define('TOOL_UUID', '<your_UUID>'); // Linux command: uuidgen
define('APP_NAME', 'SLAM');
define('APP_DESCRIPTION', 'Self-service LTI App Manager for Canvas.');
define('APP_VERSION', '1.3.1');
define('APP_URL', 'https://github.com/kjtu228-uky/SLAM');
define('VENDOR_CODE', 'ukonline');
define('VENDOR_NAME', 'UK Online');
define('VENDOR_DESCRIPTION', 'University of Kentucky | UK Online');
define('VENDOR_URL', 'https://online.uky.edu/');
define('VENDOR_EMAIL', 'elearning@uky.edu');
define('INSTRUCTOR_ONLY', true);
define('DEFAULT_DISABLED', true); // change to false if you want this enabled in course nav by default
define('CUSTOM_FIELDS', array( // you shouldn't need anything here unless you are customizing SLAM
#	'COURSE_NUMBER'=>"Canvas.course.id",
#	'COURSE_NAME'=>"Canvas.course.name",
#	'COURSE_SIS_ID'=>"Canvas.course.sisSourceId",
#	'USER_DISPLAY_NAME'=>"Person.name.display",
#	'USER_USERNAME'=>"Canvas.user.loginId"
));

###
###  Database connection settings
###
define('DB_NAME', '');  // e.g. 'mysql:dbname=MyDb;host=localhost' or 'sqlite:php-rating.sqlitedb'
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_TABLENAME_PREFIX', '');

###
###  LTI 1.3 Security settings
###
define('SIGNATURE_METHOD', 'RS256');
define('KID', '');  // A random string to identify the key value; in Linux use `pwgen 16` and choose one of the results
// For the PRIVATE_KEY, in Linux use `openssl genrsa -out custom.key`
// Copy the contents of custom.key into the following
define('PRIVATE_KEY', <<< EOD
-----BEGIN RSA PRIVATE KEY-----
Insert private key here
-----END RSA PRIVATE KEY-----
EOD
);

###
###  Canvas API Settings
###
### NOTE: The "controls" endpoints are not yet available for enforced scopes
define('API_SCOPES', array('url:GET|/api/v1/accounts/:account_id/lti_registrations',
							'url:GET|/api/v1/accounts/:account_id/lti_registrations/:id',
							'url:GET|/api/v1/accounts/:account_id/lti_registrations/:id/controls',
							'url:POST|/api/v1/accounts/:account_id/lti_registrations/:id/controls',
							'url:DELETE|/api/v1/accounts/:account_id/lti_registrations/:id/controls/:id'));

###
###  Dynamic registration settings
###
define('AUTO_ENABLE', false); // if false, you must manually enable the platform in the admin page
define('ENABLE_FOR_DAYS', 0); // if you want to automatically enable but for a limited time, set this value
?>

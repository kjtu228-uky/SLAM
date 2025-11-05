<?php

use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

/**
 * This page contains the configuration settings for the application.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
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
define('TOOL_ID', 'lti13');
define('SESSION_NAME', 'lti13');
define('TOOL_BASE_URL', '');
define('TOOL_UUID', '<your_UUID>'); // Linux command: uuidgen
define('APP_NAME', 'Basic LTI 1.3');
define('APP_DESCRIPTION', 'An LTI 1.3 test app.');
define('APP_VERSION', '0.1.0');
define('APP_URL', 'https://github.com/kylejtuck/Basic-LTI-PHP/');
define('VENDOR_CODE', 'kjt');
define('VENDOR_NAME', 'Kyle J Tuck');
define('VENDOR_DESCRIPTION', 'Independent developer');
define('VENDOR_URL', 'https://github.com/kylejtuck');
define('VENDOR_EMAIL', 'kylejtuck@gmail.com');
define('INSTRUCTOR_ONLY', true);
define('DEFAULT_DISABLED', true);
define('CUSTOM_FIELDS', array(
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
define('KID', '');  // A random string to identify the key value
define('PRIVATE_KEY', <<< EOD
-----BEGIN RSA PRIVATE KEY-----
Insert private key here
-----END RSA PRIVATE KEY-----
EOD
);

###
###  Canvas API Settings
###
/*define('CANVAS_URL', 'https://uk.instructure.com');*/
define('API_SCOPES', array(	'url:GET|/api/v1/accounts/:account_id/admins',
							'url:GET|/api/v1/courses/:course_id/external_tools',
							'url:POST|/api/v1/courses/:course_id/external_tools',
							'url:DELETE|/api/v1/courses/:course_id/external_tools/:external_tool_id'));

###
###  Dynamic registration settings
###
define('AUTO_ENABLE', false);
define('ENABLE_FOR_DAYS', 0);
?>

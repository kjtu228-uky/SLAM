<?php

use ceLTIc\LTI;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\OAuth;
use ceLTIc\LTI\Enum\ServiceAction;

/**
 * This page provides general functions to support the application.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('db.php');
require_once('SLAM.php');

LTI\ResourceLink::registerApiHook(LTI\ResourceLink::$MEMBERSHIPS_SERVICE_HOOK, 'moodle',
    'ceLTIc\LTI\ApiHook\moodle\MoodleApiResourceLink');
LTI\Tool::registerApiHook(LTI\Tool::$USER_ID_HOOK, 'canvas', 'ceLTIc\LTI\ApiHook\canvas\CanvasApiTool');
LTI\ResourceLink::registerApiHook(LTI\ResourceLink::$MEMBERSHIPS_SERVICE_HOOK, 'canvas',
    'ceLTIc\LTI\ApiHook\canvas\CanvasApiResourceLink');

###
###  Initialise application session and database connection
###

function init(&$db, $checkSession = null, $currentLevel = 0)
{
    $ok = true;

// Check if path value passed by web server needs amending
    if (defined('REQUEST_URI_PREFIX') && !empty(REQUEST_URI_PREFIX)) {
        $_SERVER['REQUEST_URI'] = REQUEST_URI_PREFIX . $_SERVER['REQUEST_URI'];
    }

// Set timezone
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('UTC');
    }

// Set session cookie path
    ini_set('session.cookie_path', getAppPath($currentLevel));

// Set samesite value for cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_samesite', 'none');
        ini_set('session.cookie_secure', true);
    }

// Open session
    session_name(SESSION_NAME);
    session_start();

// Set the default tool
    LTI\Tool::$defaultTool = new SLAM(null);

    if (!is_null($checkSession) && $checkSession) {
        $ok = isset($_SESSION['consumer_pk']) && (isset($_SESSION['resource_pk']) || is_null($_SESSION['resource_pk'])) &&
            isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk'])) && isset($_SESSION['isStudent']);
    }

    if (!$ok) {
        $_SESSION['error_message'] = 'Unable to open session.';
    } else {
// Open database connection
        $db = open_db(!$checkSession);
        $ok = $db !== false;
        if (!$ok) {
            if (!is_null($checkSession) && $checkSession) {
// Display a more user-friendly error message to LTI users
                $_SESSION['error_message'] = 'Unable to open database.';
            }
        } else if (!is_null($checkSession) && !$checkSession) {
// Create database tables (if needed)
            $ok = init_db($db);  // assumes a MySQL/SQLite database is being used
            if (!$ok) {
                $_SESSION['error_message'] = 'Unable to initialise database: \'' . $db->errorInfo()[2] . '\'';
            }
        }
    }

    return $ok;
}

###
###  Get the web path to the application
###

function getAppPath($currentLevel = 0)
{
    $path = getAppUrl($currentLevel);
    $pos = strpos($path, '/', 8);
    if ($pos !== false) {
        $path = substr($path, $pos);
    }

    return $path;
}

###
###  Get the URL to the application
###

function getAppUrl($currentLevel = 0)
{
    $request = OAuth\OAuthRequest::from_request();
    $url = $request->get_normalized_http_url();
    for ($i = 1; $i <= $currentLevel; $i++) {
        $pos = strrpos($url, '/');
        if ($pos === false) {
            break;
        } else {
            $url = substr($url, 0, $pos);
        }
    }
    $pos = strrpos($url, '/');
    if ($pos !== false) {
        $url = substr($url, 0, $pos + 1);
    }

    return $url;
}

###
###  Return a string representation of a float value
###

function floatToStr($num)
{
    $str = sprintf('%f', $num);
    $str = preg_replace('/0*$/', '', $str);
    if (substr($str, -1) == '.') {
        $str = substr($str, 0, -1);
    }

    return $str;
}

###
###  Return the value of a POST parameter
###

function postValue($name, $defaultValue = null)
{
    $value = $defaultValue;
    if (isset($_POST[$name])) {
        $value = $_POST[$name];
    }

    return $value;
}

function pageFooter()
{
    $here = function($val) {
        return $val;
    };

    return <<< EOD
    <footer>
      <div>{$here(APP_NAME)} version {$here(APP_VERSION)} &copy; {$here(date('Y'))} <a href="//celtic-project.org/" target="_blank">ceLTIc Project</a> (powered by its open source <a href="https://github.com/celtic-project/LTI-PHP" target="_blank">LTI-PHP library</a>)</div>
    </footer>

EOD;
}

function LTIhtmlentities($string, $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encoding = null, $double_encode = true)
{
    if (is_int($string) || is_float($string)) {
        return strval($string);
    } else if (!is_string($string)) {
        return '';
    } else {
        return htmlentities($string, $flags, $encoding, $double_encode);
    }
}

/**
 * Returns a string representation of a version 4 GUID, which uses random
 * numbers.There are 6 reserved bits, and the GUIDs have this format:
 *     xxxxxxxx-xxxx-4xxx-[8|9|a|b]xxx-xxxxxxxxxxxx
 * where 'x' is a hexadecimal digit, 0-9a-f.
 *
 * See http://tools.ietf.org/html/rfc4122 for more information.
 *
 * Note: This function is available on all platforms, while the
 * com_create_guid() is only available for Windows.
 *
 * Source: https://github.com/Azure/azure-sdk-for-php/issues/591
 *
 * @return string A new GUID.
 */
function getGuid()
{
    return sprintf('%04x%04x-%04x-%04x-%02x%02x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
        mt_rand(0, 65535), // 16 bits for "time_mid"
        mt_rand(0, 4096) + 16384, // 16 bits for "time_hi_and_version", with
// the most significant 4 bits being 0100
// to indicate randomly generated version
        mt_rand(0, 64) + 128, // 8 bits  for "clock_seq_hi", with
// the most significant 2 bits being 10,
// required by version 4 GUIDs.
        mt_rand(0, 256), // 8 bits  for "clock_seq_low"
        mt_rand(0, 65535), // 16 bits for "node 0" and "node 1"
        mt_rand(0, 65535), // 16 bits for "node 2" and "node 3"
        mt_rand(0, 65535)         // 16 bits for "node 4" and "node 5"
    );
}

/**
 * Determine if the user is an administrator of the LTI tools for
 * the specified platform. This is not necessarily the same thing
 * as a platform admin.
 *
 * @return boolean.
 */
function isToolAdmin($user, $platform) {
	$tool_admins = $platform->getSetting(‘TOOL_ADMINS’);
	if (!empty($tool_admins)) {
		$tool_admin_array = explode(",", $tool_admins);
		return in_array($user, $tool_admin_array);
	}
	return false;
}

function platformHasToken($platform, $refresh = false) {
	// the API URL, API client ID, and client secret must be defined in the platform settings, otherwise API calls won't work
	$api_url = $platform->getSetting('api_url'); // not sure if we can use $platform->deploymentId
	$api_client_id = $platform->getSetting('api_client_id');
	$api_client_secret = $platform->getSetting('api_client_secret');
	if (!$api_url || !$api_client_id || !$api_client_secret) return false;
	// check if the platform has an access token; if not, request one from Canvas
	$access_token = $platform->getSetting('access_token');
	if ($access_token) $access_token = json_decode($access_token);
	if (!$access_token || !$access_token->access_token) requestNewToken($platform);
	// check if we need to refresh the token
	if ($refresh || (isset($access_token->refresh_at) && $access_token->refresh_at < time())) {
		$url = $api_url . '/login/oauth2/token';
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_HEADER, true);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query(array(
						'grant_type' => 'refresh_token',
						'client_id' => $api_client_id,
						'client_secret' => $api_client_secret,
						'refresh_token' => $access_token->refresh_token)));
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
// TO DO: check $header for WWW-Authenticate
//$for_session['headers'] = $this->get_headers_from_curl_response($header);
//Util::logError($this->get_headers_from_curl_response($header));
		$response_data = json_decode(substr($response, $header_size), true);
		curl_close($ch);
		// if there was an error using the refresh token, request a brand new token
		if (isset($response_data['error'])) {
			$_SESSION['error_message'] = $response_data['error'];
//Util::logError($response_data['error']);
			// delete the token and request a new one
			$platform->setSetting('access_token');
			$platform->save();
			requestNewToken($platform);
		}
		$access_token->access_token = null;
		$access_token->refresh_at = null;
		if (isset($response_data['access_token']) && preg_match('/^[0-9a-zA-Z~]+$/', $response_data['access_token']))
			$access_token->access_token = $response_data['access_token'];
		if (isset($response_data['expires_in']) && is_numeric($response_data['expires_in']))
			$access_token->refresh_at = time() + intval($response_data['expires_in']);
		if (isset($access_token->access_token) && isset($access_token->refresh_at)) {
			$platform->setSetting('access_token', json_encode($access_token));
			$platform->save();
		}
	}
	return true;
}

function requestNewToken($platform) {
	$api_url = $platform->getSetting('api_url'); // not sure if we can use $platform->deploymentId
	if (!$api_url) return false;
	header(	'Location: ' . $api_url . '/login/oauth2/auth?client_id=' . $platform->getSetting('api_client_id') . 
			'&response_type=code&state=' . $_SESSION['consumer_pk'] . '&scope=' . implode("%20", API_SCOPES) .
//			'&response_type=code&state=' . session_id() . '&scope=' . implode("%20", API_SCOPES) .
//				'&response_type=code&scope=' . implode("%20", API_SCOPES) .
			'&redirect_uri=' . TOOL_BASE_URL . 'oauth2response.php');
	exit(0);
}
?>
<?php

use ceLTIc\LTI;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\OAuth;
use ceLTIc\LTI\Enum\ServiceAction;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

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
function isToolAdmin($platform, $user = null) {
	if (is_null($user)) {
		if (isset($_SESSION['username'])) $user = $_SESSION['username'];
		else return false;
	}
	$tool_admins = $platform->getSetting('TOOL_ADMINS');
	if (!empty($tool_admins)) {
		$tool_admin_array = explode(",", $tool_admins);
		return in_array($user, $tool_admin_array);
	}
	return false;
}

/**
 * Check if the platform has a stored auth token. If it doesn't, request one.
 * Optionally force a refresh (request new token).
 *
 * @return boolean.
 */
function platformHasToken($platform, $refresh = false) {
	// the API URL, API client ID, and client secret must be defined in the platform settings, otherwise API calls won't work
	$api_url = $platform->getSetting('api_url');
	$api_client_id = $platform->getSetting('api_client_id');
	$api_client_secret = $platform->getSetting('api_client_secret');
	if (!$api_url || !$api_client_id || !$api_client_secret) return false;
	// check if the platform has an access token; if not, request one from Canvas
	$access_token = $platform->getSetting('access_token');
	if ($access_token) $access_token = json_decode($access_token);
	if ((!$access_token || !$access_token->access_token) && !requestNewToken($platform)) return false;
	// check if we need to refresh the token
	if ($refresh || (isset($access_token->refresh_at) && $access_token->refresh_at < time())) {
		$url = $api_url . '/login/oauth2/token';
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_HEADER, true);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded",
			"User-Agent: LTIPHP/1.0"));
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
			// delete the token and request a new one
			$platform->setSetting('access_token');
			$platform->save();
			if (!requestNewToken($platform)) return false;
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

/**
 * Redirect to the platform oauth to request a new token.
 *
 */
function requestNewToken($platform) {
	if ($_SESSION['username'] != $platform->getSetting('api_user_id')) return false;
	$api_url = $platform->getSetting('api_url'); // not sure if we can use $platform->deploymentId
	if (!$api_url) return false;
	header(	'Location: ' . $api_url . '/login/oauth2/auth?client_id=' . $platform->getSetting('api_client_id') . 
			'&response_type=code&state=' . $_SESSION['consumer_pk'] . '&scope=' . implode("%20", API_SCOPES) .
			'&redirect_uri=' . TOOL_BASE_URL . 'oauth2response.php');
	exit(0);
}

/**
 * Retrieve the list of LTI registrations for the platform.
 *
 * @return array.
 */
function getLTIRegistrations($platform) {
	$LTIregistrations = array();
 	if (isToolAdmin($platform) && platformHasToken($platform)) {
		// the API URL must be defined in the platform settings
		$api_url = $platform->getSetting('api_url');
		if (!$api_url) return array("errors" => "The API URL is not defined for the platform.");
		// check if the platform has an access token; if not, request one from Canvas
		$access_token = $platform->getSetting('access_token');
		if ($access_token) $access_token = json_decode($access_token);
		if (!$access_token || !$access_token->access_token) return array("errors" => "The platform does not have an access token.");
		$headers = array("Authorization: Bearer " . $access_token->access_token,
			"User-Agent: LTIPHP/1.0");
		$url = $api_url . '/api/v1/accounts/self/lti_registrations?per_page=25';
		while ($url) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			$response = curl_exec($ch);
			$response_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$response_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$response_headers = substr($response, 0, $response_header_size);
			$response_body = substr($response, $response_header_size);
			curl_close($ch);
			if ($response_http_code != 200)
				return array("errors" => "Error: API request failed with status $response_http_code");
			// append the decoded JSON results to the registrations
			$pagedLTIregistrations = json_decode($response_body, true);
			if (isset($pagedLTIregistrations['data']))
				$LTIregistrations = array_merge($LTIregistrations, $pagedLTIregistrations['data']);
			// Extract the 'next' page URL from the Link header
			$url = null;
			if (preg_match('/<([^>]+)>;\s*rel="next"/i', $response_headers, $matches)) {
				$url = $matches[1];
			}
		}
	}
	return sortAssociativeArrayBy(array $LTIregistrations, 'name');
}

/**
 * Retrieve all available tools configured for the platform.
 *
 * @return array.
 */
function getAllTools($platform) {
	$registrations = getLTIRegistrations($platform);
	$all_tools = array();
	foreach ($registrations as $registration) {
		$registration = getRegistrationConfig($platform, $registration);
		if ($registration) $all_tools[$registration['id']] = $registration;
	}
	return $all_tools;
}



/**
 * Retrieve the list of configured tools from the database.
 *
 * @return array.
 */
/* function getConfiguredLTITools($platform, $courseNumber = null, $onlyVisible = false) {
	$enabled_tools = getEnabledTools($platform, $courseNumber);
	if (isset($enabled_tools['errors'])) return $enabled_tools;
	$registrations = getLTIRegistrations($platform);
	$all_tools = array();
	try {
		$platform_tools = getToolsForPlatform($platform, $onlyVisible);
		foreach ($platform_tools as $tool_config) {
			$all_tools[$tool_config['id']]['name'] = json_decode($tool_config['config'], true)['name'];
			$all_tools[$tool_config['id']]['enabled'] = 0;
			$all_tools[$tool_config['id']]['visible'] = $tool_config['visible'];
			$all_tools[$tool_config['id']]['dependency'] = $tool_config['dependency'];
			$all_tools[$tool_config['id']]['user_notice'] = $tool_config['user_notice'];
			$all_tools[$tool_config['id']]['support_info'] = $tool_config['support_info'];
		}
		foreach ($all_tools as $key => $tool) {
			if (in_array($tool['name'], array_keys($enabled_tools))) {
				$all_tools[$key]['enabled'] = $enabled_tools[$tool['name']]['id'];
				$all_tools[$key]['deployment_id'] = $enabled_tools[$tool['name']]['deployment_id'];
			}
		}
		$db = null;
	} catch (PDOException $e) {
		return array("errors" => "There was a problem accessing the tools from the database.");
	}
	return $all_tools;
} */

/**
 * Retrieve the list of external tools that are enabled in the course.
 *
 * @return array.
 */
function getEnabledTools($platform, $courseNumber) {
	$enabled_tools = array();
	if ($courseNumber && platformHasToken($platform)) {
		// the API URL, API client ID, and client secret must be defined in the platform settings, otherwise API calls won't work
		$api_url = $platform->getSetting('api_url');
		if (!$api_url) return array("errors" => "The API URL is not defined for the platform.");
		// check if the platform has an access token; if not, request one from Canvas
		$access_token = $platform->getSetting('access_token');
		if ($access_token) $access_token = json_decode($access_token);
		if (!$access_token || !$access_token->access_token) return array("errors" => "The platform does not have an access token.");
		$headers = array("Authorization: Bearer " . $access_token->access_token,
			"User-Agent: LTIPHP/1.0");
		$url = $api_url . '/api/v1/courses/' . $courseNumber . '/external_tools?per_page=100';	
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		$response = json_decode(curl_exec($ch), true);
		if (isset($response['errors'])) return $response;
		foreach ($response as $tool_detail) {
			$enabled_tools[$tool_detail['name']] = array('id'=>$tool_detail['id'], 'deployment_id'=>$tool_detail['deployment_id']);
		}
		curl_close($ch);
	}
	return $enabled_tools;
}

/**
 * Try to add the specified tool to the specified course.
 *
 * @return array.
 */
function addLTIToolToCourse($platform, $courseNumber, $tool_id) {
	$response = array();
	$tool_config = getToolConfig($tool_id);
	if ($tool_config && isset($tool_config['config'])) {
//Util::logError(json_encode($tool_config, JSON_PRETTY_PRINT));
		if (is_string($tool_config['config'])) $tool_config['config'] = json_decode($tool_config['config'], true);
		if (platformHasToken($platform)) {
			// the API URL, API client ID, and client secret must be defined in the platform settings, otherwise API calls won't work
			$api_url = $platform->getSetting('api_url');
			if (!$api_url) return array("errors" => "The API URL is not defined for the platform.");
			// check if the platform has an access token; if not, request one from Canvas
			$access_token = $platform->getSetting('access_token');
			if ($access_token) $access_token = json_decode($access_token);
			if (!$access_token || !$access_token->access_token) return array("errors" => "The platform does not have an access token.");
			$headers = array("Authorization: Bearer " . $access_token->access_token,
				"User-Agent: LTIPHP/1.0");
			$url = $api_url . '/api/v1/courses/' . $courseNumber . '/external_tools';
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, $url);
			curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt ($ch, CURLOPT_POST, true);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $tool_config['config']);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
			$response = json_decode(curl_exec($ch), true);
			curl_close($ch);
/*
		if ($response) $this->logToolChange($tool_id, 1, $response['id']);
		else $this->logToolChange($tool_id, 1, -1);
		// check the name returned by Canvas and ensure it matches the config
		if (strcmp($response['name'], $tool_config['config']['name']) != 0 && $this->isAdmin()) {
			$tool_config['config']['name'] = $response['name'];
			$this->updateToolDetails($tool_config, false);
			$response['alert'] = "The tool name was automatically updated to reflect its name in Canvas.";
		}
//Util::logError(json_encode($response, JSON_PRETTY_PRINT));
*/
		} else {
			$response['errors'] = "There is no token for this platform.";
		}
	} else {
		$response['errors'] = "Could not find a tool with id " . $tool_id;
	}
	return $response;
}

/**
 * Converts an associative array to a sorted array based on the "name" key.
 *
 * @param array $associativeArray The associative array to convert.  Must contain elements with a "name" key.
 * @return array A numerically indexed array sorted by the "name" key.  Returns an empty array if the input is not an array.
 * How missing keys are handled - they remain in their relative positions.
 */
function sortAssociativeArrayBy(array $associativeArray, string $sortKey): array
{
    if (!is_array($associativeArray)) {
        return []; // Return an empty array if the input isn't an array.
    }

    // Use usort to sort the array by the "name" key.
    usort($associativeArray, function ($a, $b) {
        // Ensure both $a and $b have a 'name' key before comparing
        if (!isset($a[$sortKey])) {
            return 0; // Or throw an exception, or handle the missing key differently
        }
        if (!isset($b[$sortKey])) {
            return 0;
        }

        return strcmp($a[$sortKey], $b[$sortKey]); // Compare names using strcmp (case-sensitive).
    });

    // Re-index the array to create a numerically indexed array.
    return array_values($associativeArray);
}
?>
?>
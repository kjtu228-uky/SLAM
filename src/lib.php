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

function pageFooter()
{
    $here = function($val) {
        return $val;
    };

    return <<< EOD
    <footer>
      <div>{$here(APP_NAME)} version {$here(APP_VERSION)} &copy; {$here(date('Y'))} <a href="https://online.uky.edu/" target="_blank">UK Online</a> (powered by <a href="https://celtic-project.org/" target="_blank">the ceLTIc Project's</a> open source <a href="https://github.com/celtic-project/LTI-PHP" target="_blank">LTI-PHP library</a>)</div>
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
        mt_rand(0, 4096) + 16384, // 16 bits for "time_hi_and_version", with the most significant 4 bits being 0100 to indicate randomly generated version
        mt_rand(0, 64) + 128, // 8 bits  for "clock_seq_hi", with the most significant 2 bits being 10, required by version 4 GUIDs.
        mt_rand(0, 256), // 8 bits  for "clock_seq_low"
        mt_rand(0, 65535), // 16 bits for "node 0" and "node 1"
        mt_rand(0, 65535), // 16 bits for "node 2" and "node 3"
        mt_rand(0, 65535)  // 16 bits for "node 4" and "node 5"
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
	$tool_admins = $platform->getSetting('tool_admins');
	if (!empty($tool_admins)) {
		$tool_admin_array = explode(",", $tool_admins);
		// the api_user_id can be considered a tool admin that can define other tool admins
		$tool_admin_array[] = $platform->getSetting('api_user_id');
		return in_array($user, $tool_admin_array);
	} else {
		if ($user == $platform->getSetting('api_user_id')) return true;
	}
	return false;
}

function updatePlatformSettings($platform, $settings) {
	// only update recognized settings: tool_admins, tool_list_header
	if (!is_array($settings)) return false;
	if (isset($settings['tool_admins'])) {
		// make sure it is a comma-separated string
		$tool_admins = explode(',', $settings['tool_admins']);
		if (!is_array($tool_admins)) return false;
		$platform->setSetting('tool_admins', $settings['tool_admins']);
	}
	if (isset($settings['tool_list_header'])) {
		// strip unsupported HTML tags
		$tool_list_header = strip_tags($settings['tool_list_header'], ['p', 'a', 'br', 'strong', 'i', 'u', 'hr']);
		$tool_list_header = json_encode($tool_list_header);
		$platform->setSetting('tool_list_header', $tool_list_header);
	}
	$platform->save();
	return true;
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
	if (!$api_url || !$api_client_id || !$api_client_secret) {
		Util::logError("Platform settings are not fully configured (ID: " . $platform->getRecordId() . ")");
		return false;
	}
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
	if ($_SESSION['username'] != $platform->getSetting('api_user_id')) {
		Util::logError("The logged in user (" . $_SESSION['username'] . ") is not the user configured to request a token for SLAM (" . $platform->getSetting('api_user_id') . "). Platform ID: " . $platform->getRecordId());
		return false;
	}
	$api_url = $platform->getSetting('api_url');
	if (!$api_url) return false;
	header(	'Location: ' . $api_url . '/login/oauth2/auth?client_id=' . $platform->getSetting('api_client_id') . 
			'&response_type=code&state=' . $_SESSION['consumer_pk'] . '&scope=' . implode("%20", API_SCOPES) .
			'&redirect_uri=' . rtrim(TOOL_BASE_URL, '/') . '/oauth2response.php');
	exit(0);
}

/**
 * Canvas API helper – generic, self‑contained, no dependencies.
 *
 * @param string       $method      HTTP method: GET, POST, PUT, DELETE, PATCH, etc.
 * @param string|array $endpoint    Canvas endpoint (e.g. "/api/v1/courses/12345/assignments").
 * @param array        $options     Optional keys:
 *                            - 'body'      => array|json string  (will be JSON‑encoded)
 *                            - 'query'     => array of key=>value for query string
 *                            - 'headers'   => array of additional headers
 *                            - 'raw'       => bool – return raw cURL output (headers+body)
 * @return array  an associative array with the 'headers' and 'response' (associative array representing the json response)
 * @throws RuntimeException on HTTP errors or cURL problems.
 */
function canvasApiRequest($platform, string $method, $endpoint, array $options = []): array {
	$allResults = [];
	if (platformHasToken($platform)) {
		// the API URL must be defined in the platform settings
		$api_url = $platform->getSetting('api_url');
		if (!$api_url) return ['errors' => 'The API URL is not defined for the platform.'];
		// check if the platform has an access token; if not, request one from Canvas
		$access_token = $platform->getSetting('access_token');
		if ($access_token) $access_token = json_decode($access_token);
		if (!$access_token || !$access_token->access_token) return ['errors' => 'The platform does not have an access token.'];

		$endpoints = [];
		if (is_string($endpoint)) $endpoints[] = $endpoint;
		elseif (is_array($endpoint)) $endpoints = $endpoint;
		else return ['errors' => 'String or array must be provided to canvasApiRequest().'];

		// Build the headers
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer ' . $access_token->access_token,
			'User-Agent: LTIPHP/1.0'
		];
		// Merge user‑supplied headers
		if (!empty($options['headers']) && is_array($options['headers'])) {
			$headers = array_merge($headers, $options['headers']);
		}
		
		// Build the payload (if necessary)
		$payload = null;
		if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
			if (!empty($options['body'])) {
				// Accept array or string; JSON‑encode if array
				$payload = is_array($options['body'])
					? json_encode($options['body'])
					: (string)$options['body'];
			}
		}		
		
		// Build the URLs and curl handles
		$multiHandle = curl_multi_init();
		$handles = [];
		foreach ($endpoints as $ep) {
			// build the url
			$url = rtrim($api_url, '/') . $ep;
			if (!empty($options['query']) && is_array($options['query'])) {
				$url .= '?' . http_build_query($options['query']);
			}
			// prepare cURL
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
			if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
//			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$handles[$ep] = $ch;
			curl_multi_add_handle($multiHandle, $ch);
		}
		
		// execute the calls
		$running = null;
		do {
			curl_multi_exec($multiHandle, $running);
			curl_multi_select($multiHandle);
		} while ($running > 0);
		
		// get the responses
		foreach ($handles as $ep => $ch) {
			$response = curl_multi_getcontent($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			// separate the headers and body
			list($rawHeaders, $body) = explode("\r\n\r\n", $response, 2);
			$responseHeaders = [];
			$json = json_decode($body, true);
			foreach (explode("\r\n", $rawHeaders) as $h) {
				$header = explode(':', $h, 2) ?? [];
				if (is_array($header) && count($header) == 2 && $header[0] && $header[1])
					$responseHeaders[trim($header[0])] = trim($header[1]);
			}
			
			// Retry on rate‑limit (429)
			if ($httpCode === 429) {
				$retryAfter = $responseHeaders['Retry-After'] ?? 1;
				sleep((int)$retryAfter);
				// Recursive retry for the single endpoint
				$retryResults = canvasApiRequest($platform, $method, $ep, $options);
				if (isset($retryResults['errors'])) return $retryResults;
				$allResults[$ep] = $retryResults;
				curl_multi_remove_handle($multiHandle, $ch);
				curl_close($ch);
				continue;
			}
			
			// check for error codes
			if ($httpCode < 200 || $httpCode >= 300) {
				$error  = "Canvas API error $httpCode";
				if (!empty($body)) {
					if (json_last_error() === JSON_ERROR_NONE && isset($json['message']))
						$error .= ": {$json['message']}";
					else
						$error .= ": $body";
				}
				Util::logError("cURL error: $error");
				return ['errors' => $error];
			}
			
			$allResults[$ep] = [
				'headers' => $responseHeaders,
				'response' => $json
			];
			curl_multi_remove_handle($multiHandle, $ch);
			curl_close($ch);
		}
		curl_multi_close($multiHandle);
	} else {
		return ['errors' => 'Platform does not have a valid token.'];
	}
	return $allResults;
}

/**
 * Canvas API helper to get all pages for a GET request.
 *
 * @param array | string $endpoint    Canvas endpoint (e.g. "/api/v1/courses/12345/assignments").
 * @param array          $options     Optional keys:
 *                            - 'body'      => array|json string  (will be JSON‑encoded)
 *                            - 'query'     => array of key=>value for query string
 *                            - 'headers'   => array of additional headers
 *                            - 'raw'       => bool – return raw cURL output (headers+body)
 * @return array  index 0 contains the headers array and index 1 is the decoded json as associative array from the body
 * @throws RuntimeException on HTTP errors or cURL problems.
 */
function canvasApiAllPages($platform, $endpoint, array $options = []): array {
	$all = [];
	$page = 1;
	$endpoints = $endpoint;
	do {
		$options['query']['page'] = $page;
		$response = canvasApiRequest($platform, 'GET', $endpoints, $options);
		if (isset($response['errors'])) return $response;

		$endpoints = [];
		foreach ($response as $ep => $resp) {
			if (isset($resp['response']['data'])) {
				if (!isset($all[$ep])) $all[$ep] = $resp['response']['data'];
				else $all[$ep] = array_merge($all[$ep], $resp['response']['data']);
			} else {
				if (!isset($all[$ep])) $all[$ep] = $resp['response'];
				else $all[$ep] = array_merge($all[$ep], $resp['response']);
			}
			if (isset($resp['headers']['link'])) {
				// Link header can have multiple, comma-separated links with each defined as one of:
				//    rel="current", rel="next", rel="first", rel="last"
				foreach (explode(',', $resp['headers']['link']) as $part) {
					if (preg_match('/<([^>]+)>;\s*rel="next"/i', trim($part), $matches)) {
						$page = $page + 1;
						$endpoints[] = $ep;
						break;
					}	
				}
			}
		}
	} while (count($endpoints) > 0);
	// $all will be an array with a key for each endpoint
	return $all;
}

/**
 * Retrieve the list of LTI registrations for the platform.
 *
 * @return array.
 */
function getLTIRegistrations($platform) {
	$LTIregistrations = array();
 	if (isToolAdmin($platform))
		$LTIregistrations = canvasApiAllPages($platform, '/api/v1/accounts/self/lti_registrations', ['query' => ['per_page' => 100]]);
	if (isset($LTIregistrations['errors'])) return $LTIregistrations;
	if (!isset($LTIregistrations['/api/v1/accounts/self/lti_registrations'])) return ['errors' => 'No results returned from canvasApiAllPages()'];
	return sortAssociativeArrayByKey($LTIregistrations['/api/v1/accounts/self/lti_registrations'], 'name');
}

/**
 * Get the details for the specified registration Id.
 *
 * @return array.
 */
function getLTIRegistration($platform, $registrationIds) {
	$endpoints = [];
	// make sure $registrationIds is number or array of numbers
	if (is_numeric($registrationIds)) $endpoints[] = '/api/v1/accounts/self/lti_registrations/' . $registrationIds;
	elseif (is_array($registrationIds)) {
		foreach($registrationIds as $id) {
			if (is_numeric($id))
				$endpoints[] = '/api/v1/accounts/self/lti_registrations/' . $id;
		}
	} else {
		return ['errors' => 'Provided registration ID must be integer or array of integers.'];
	}
	$response = canvasApiRequest($platform, 'GET', $endpoints);
	if (isset($response['errors'])) return $response;
	// build registrations; should only be one result per endpoint
	$registrations = [];
	foreach ($response as $ep => $resp) {
		if (!isset($resp['response'])) return ['errors' => 'No registration in Canvas for $ep'];
		if (is_array($resp['response']) && array_is_list($resp['response']) && count($resp['response']) > 1) return ['errors' => 'More than one registration returned for $ep'];
		$id = intval(substr($ep, strrpos($ep, "/") + 1));
		$registrations[$id] = $resp['response'];
	}
	return $registrations;
}

/**
 * Retrieve all available tools configured for the platform.
 *
 * @return array.
 */
function getAllTools($platform) {
	$registrations = getLTIRegistrations($platform);
	$configuredTools = getToolConfigs($platform);
	$all_tools = array();
	foreach ($registrations as $registration) {
		$registration = getToolConfig($platform, $registration, $configuredTools);
		if ($registration) $all_tools[$registration['id']] = $registration;
	}
	return $all_tools;
}

/**
 * Determine if the LTI registration is enabled (available) for the specified course.
 *
 * @return array: array('available' => false or context_control['id'], 'errors' => if_any).
 */
function isAvailable($platform, $registrationIds, $courseNumber) {
	$endpoints = [];
	$availability = [];
	// make sure $registrationIds is number or array of numbers
	if (is_numeric($registrationIds)) $endpoints[] = '/api/v1/accounts/self/lti_registrations/' . $registrationIds . '/controls';
	elseif (is_array($registrationIds)) {
		foreach($registrationIds as $id) {
			if (is_numeric($id))
				$endpoints[] = '/api/v1/accounts/self/lti_registrations/' . $id . '/controls';
		}
	} else {
		return ['errors' => 'Provided registration ID must be integer or array of integers.'];
	}
	$options = ['query' => ['per_page' => 100]];
	
	$controls = canvasApiAllPages($platform, $endpoints, $options);
	if (isset($controls['errors'])) return $controls;
	foreach ($controls as $ep => $registrationControls) {
		foreach ($registrationControls as $control) {
			$availability[$control['registration_id']] = ['available' => false];
			if (isset($control['context_controls']) && is_array($control['context_controls']) && count($control['context_controls']) > 0) {
				foreach ($control['context_controls'] as $context_control) {
					if (isset($context_control['course_id']) && !is_null($context_control['course_id']) &&
						$context_control['course_id'] == $courseNumber && isset($context_control['available']) && $context_control['available']) {
							$availability[$control['registration_id']]['available'] = true;
							$availability[$control['registration_id']]['context_id'] = $context_control['id'];
							$availability[$control['registration_id']]['deployment_id'] = $control['deployment_id'];
							break;
					}
				}
				if ($availability[$control['registration_id']]['available']) break;
			}
		}
	}
	return $availability;
}

/**
 * Retrieve the list of external tools that are enabled in the course.
 *
 * @return array.
 */
function getCourseTools($platform, $course_number) {
	$courseTools = [];
	$registrationIds = [];
	// get the tool IDs that are enabled in SLAM for this platform
	$platformEnabledTools = getToolConfigs($platform, true);
	foreach ($platformEnabledTools as $tool) {
		// gather registration IDs to make concurrent API calls
		$registrationIds[] = $tool['canvas_id'];
	}
	$registrations = getLTIRegistration($platform, $registrationIds);
	if (isset($registrations['errors'])) return $registrations['errors'];
	$availability = isAvailable($platform, $registrationIds, $course_number);
	if (isset($availability['errors'])) return $availability['errors'];
	// build the array of tools and status specific to this course
	foreach ($platformEnabledTools as $tool) {
		if (isset($registrations[$tool['canvas_id']])) {
			if (isset($registrations[$tool['canvas_id']]['name']))
				$tool['name'] = $registrations[$tool['canvas_id']]['name'];
			if (isset($registrations[$tool['canvas_id']]['admin_nickname']))
				$tool['name'] = $registrations[$tool['canvas_id']]['admin_nickname'];
			if (isset($availability[$tool['canvas_id']]) && $availability[$tool['canvas_id']]['available'])
				$tool['enabled'] = true;
			else
				$tool['enabled'] = false;
		}
		$courseTools[] = $tool;
	}
	return sortAssociativeArrayByKey($courseTools, "name");
}

/**
 * Add an exception for a tool to a course.
 *
 * @return array of tool ids that were added, or false if tool (or dependency) could not be added.
 */
function addToolToCourse($platform, $tool_id, $course_number) {
	$tool_id = intval($tool_id);
	$tool_config = getToolConfigById($tool_id);
	if ($tool_config) {
		$success = array($tool_id);
		if (isset($tool_config['dependency']) && !is_null($tool_config['dependency'])) {
			$dependency_result = addToolToCourse($platform, $tool_config['dependency'], $course_number);
			if ($dependency_result) $success = array_merge($success, $dependency_result);
			else {
				logToolChange($platform, $tool_id, 1, $course_number, 0);
				return false;
			}
		}
		// check if it's already enabled/available
		$availability = isAvailable($platform, $tool_config['canvas_id'], $course_number);
		if (isset($availability[$tool_config['canvas_id']]) && $availability[$tool_config['canvas_id']]['available']) return $success;
		
		// try to add the tool to the course
		$endpoint = '/api/v1/accounts/self/lti_registrations/' . $tool_config['canvas_id'] . '/controls';
		$options = ['body' => [
			'course_id' => $course_number,
			'available' => true
		]];
		$response = canvasApiRequest($platform, 'POST', $endpoint, $options);
		// check the response for issues
		if (isset($response['errors'])) {
			Util::logError($response['errors']);
			logToolChange($platform, $tool_id, 1, $course_number, 0);
			return false;
		}
		if (!isset($response[$endpoint]['response'])) {
			Util::logError(json_encode($response, JSON_PRETTY_PRINT));
			logToolChange($platform, $tool_id, 1, $course_number, 0);
			return false;
		}
		// check if the exception was successfully applied to the course
		$controls = $response[$endpoint]['response'];
		if (isset($controls['course_id']) && isset($controls['available']) && $controls['available']) {
			logToolChange($platform, $tool_id, 1, $course_number, 1);
			return $success;
		}
	}
	logToolChange($platform, $tool_id, 1, $course_number, 0);
	return false;
}

/**
 * Remove an exception for a tool from a course.
 *
 * @return array of tool ids that were removed, or false if tool (or dependency) could not be removed.
 */
function removeToolFromCourse($platform, $tool_id, $course_number, $dependents = array()) {
	$tool_id = intval($tool_id);
	$tool_config = getToolConfigById($tool_id);
	if ($tool_config) {
		// check if other enabled tools are dependent on this one
		$otherEnabledTools = getCourseTools($platform, $course_number);
		foreach ($otherEnabledTools as $tool) {
			if ($tool['dependency'] == $tool_id && !in_array($tool['id'], $dependents))
				return array();
		}
		$success = array($tool_id);
		// check if it's already enabled/available
		$availability = isAvailable($platform, $tool_config['canvas_id'], $course_number);
		if (isset($availability[$tool_config['canvas_id']])) $availability = $availability[$tool_config['canvas_id']];
		if (isset($availability['available']) && $availability['available']) {
			if (isset($tool_config['dependency']) && !is_null($tool_config['dependency'])) {
				$dependents[] = $tool_id;
				$dependency_result = removeToolFromCourse($platform, $tool_config['dependency'], $course_number, $dependents);
				if ($dependency_result) $success = array_merge($success, $dependency_result);
				else {
					logToolChange($platform, $tool_id, 0, $course_number, 0);
					return false;
				}
			}
			// try to add the tool to the course
			$endpoint = '/api/v1/accounts/self/lti_registrations/' . $tool_config['canvas_id'] . '/controls/' . $availability['context_id'];
			$response = canvasApiRequest($platform, 'DELETE', $endpoint);
			// check the response for issues
			if (isset($response['errors'])) {
				Util::logError($response['errors']);
				logToolChange($platform, $tool_id, 0, $course_number, 0);
				return false;
			}
			if (!isset($response[$endpoint]['response'])) {
				Util::logError(json_encode($response, JSON_PRETTY_PRINT));
				logToolChange($platform, $tool_id, 0, $course_number, 0);
				return false;
			}
			// check if the exception was successfully removed from the course
			$controls = $response[$endpoint]['response'];
			if (isset($controls['course_id']) && isset($controls['available']) && $controls['available']) {
				logToolChange($platform, $tool_id, 0, $course_number, 1);
				return $success;
			}
		} else {
			return $success;
		}
	}
	logToolChange($platform, $tool_id, 0, $course_number, 0);
	return false;
}

/**
 * Converts an associative array to a sorted array based on a specified key.
 *
 * @param array $associativeArray The associative array to convert.
 * @param string $sortKey The key to sort the array by.  Must be a string.
 * @return array A numerically indexed array sorted by the specified key. Returns an empty array if the input is not an array or the sort key is invalid.
 * If the sortKey is missing, the sort order of the item is not changed.
 */
function sortAssociativeArrayByKey(array $associativeArray, string $sortKey): array {
	if (!is_array($associativeArray)) return [];
	if (!is_string($sortKey) || empty($sortKey)) return []; // Invalid sort key

	// Use usort to sort the array by the specified key.
	usort($associativeArray, function ($a, $b) use ($sortKey) {
		// Ensure both $a and $b have the sort key before comparing
		if (!isset($a[$sortKey])) return 0; // Or throw an exception, or handle the missing key differently
		if (!isset($b[$sortKey])) return 0;
		return strcasecmp($a[$sortKey], $b[$sortKey]); // Compare values using strcmp (case-sensitive).
	});
	// Re-index the array to create a numerically indexed array.
	return array_values($associativeArray);
}
?>
<?php

use ceLTIc\LTI;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\OAuth;
use ceLTIc\LTI\Enum\ServiceAction;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

/**
 * This script provides general functions to support the application.
 *
 * Functions: init, getAppPath, getAppUrl, pageFooter, LTIhtmlentities, getGuid
 *   @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 *   @copyright  SPV Software Products
 *   @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 * Remaining functions:
 *   @author  Kyle Tuck <Kyle.Tuck@uky.edu>
 *   @copyright  Kyle Tuck
 *   @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
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
            isset($_SESSION['user_consumer_pk']) && (isset($_SESSION['user_pk']) || is_null($_SESSION['user_pk']));
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
 * Determine if the specified user (or the current session user) is a tool
 * administrator for the given LTI platform. Tool admins are defined in the
 * platform's 'tool_admins' setting as a comma-separated list of usernames.
 * The platform's 'api_user_id' is always treated as a tool admin and may
 * also define other tool admins via that setting.
 *
 * Note: Being a tool admin is not necessarily the same as being a platform
 * (Canvas) administrator.
 *
 * @param \ceLTIc\LTI\Platform      $platform The LTI platform instance to check admin status against.
 * @param string|null $user     Optional. The username to check. If null, the current
 *                              session user ($_SESSION['username']) is used. Returns
 *                              false if null is passed and no session user exists.
 *
 * @return bool True if the user is a tool administrator for the platform, false otherwise.
 */
function isToolAdmin($platform, $user = null) {
	if (is_null($user)) {
		if (isset($_SESSION['username'])) $user = $_SESSION['username'];
		else return false;
	}
	$tool_admins = $platform->getSetting('tool_admins');
	if (!empty($tool_admins)) {
		$tool_admin_array = array_map("trim", explode(",", $tool_admins));
		// the api_user_id can be considered a tool admin that can define other tool admins
		$tool_admin_array[] = $platform->getSetting('api_user_id');
		return in_array($user, $tool_admin_array);
	} else {
		if ($user == $platform->getSetting('api_user_id')) return true;
	}
	return false;
}

/**
 * Update recognized settings for the given LTI platform. Only processes the
 * following keys in the $settings array:
 *
 *   - 'tool_admins'     : A comma-separated string of usernames to be granted
 *                         tool admin access. Unrecognized or malformed values
 *                         are rejected. The current session user will be
 *                         automatically re-added if they attempt to remove
 *                         themselves, unless they are the platform's api_user_id.
 *
 *   - 'tool_list_header': An HTML string displayed as the tool list header.
 *                         Only the following tags are permitted; all others are
 *                         stripped: <p>, <a>, <br>, <strong>, <i>, <u>, <hr>.
 *                         The value is JSON-encoded before being saved.
 *
 * Unrecognized keys in $settings are silently ignored. Changes are persisted
 * by calling save() on the platform instance.
 *
 * @param \ceLTIc\LTI\Platform $platform The LTI platform instance whose settings will be updated.
 * @param array                $settings An associative array of settings to update.
 *                                       Must be an array; returns false otherwise.
 *
 * @return bool True if the settings were successfully validated and saved,
 *              false if the current user is not a tool admin or $settings
 *              is not a valid array.
 */
function updatePlatformSettings($platform, $settings) {
	if (!isToolAdmin($platform)) return false;
	// only update recognized settings: tool_admins, tool_list_header
	if (!is_array($settings)) return false;
	if (isset($settings['tool_admins'])) {
		// make sure it is a comma-separated string
		$tool_admins = explode(',', $settings['tool_admins']);
		if (!is_array($tool_admins)) return false;
		// make sure admins do not remove themselves
		if ($_SESSION['username'] != $platform->getSetting('api_user_id') && !in_array($_SESSION['username'], $tool_admins))
			$tool_admins[] = $_SESSION['username'];
		$platform->setSetting('tool_admins', implode(",", $tool_admins));
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
 * Ensure the given LTI platform has a valid API access token. If no token is
 * stored, a new one is requested. If a token exists but is expired or a refresh
 * is explicitly requested, the token is refreshed via an OAuth2 refresh_token
 * grant against the platform's Canvas API endpoint.
 *
 * Prerequisites: The platform must have the following settings defined:
 *   - 'api_url'           : Base URL of the Canvas API.
 *   - 'api_client_id'     : OAuth2 client ID.
 *   - 'api_client_secret' : OAuth2 client secret.
 * If any of these are missing, an error is logged via Util::logError() and
 * false is returned immediately.
 *
 * Token storage:
 *   - Tokens are stored in the platform's 'tokens' setting as a JSON-encoded
 *     object containing 'access_token', 'refresh_token', and 'refresh_at'.
 *   - A legacy 'access_token' setting is recognized as a fallback and migrated
 *     to the 'tokens' setting upon the next successful refresh.
 *
 * Refresh behavior:
 *   - A refresh is triggered if $refresh is true, or if the stored token's
 *     'refresh_at' timestamp is in the past.
 *   - If the refresh token grant fails, both the 'tokens' and legacy
 *     'access_token' settings are cleared, an error message is stored in
 *     $_SESSION['error_message'], and a new token is requested via
 *     requestNewToken().
 *   - The access token returned by the refresh is validated against the
 *     pattern /^[0-9a-zA-Z~]+$/ before being stored.
 *
 * @param \ceLTIc\LTI\Platform $platform The LTI platform instance for which to
 *                                        verify or obtain an access token.
 * @param bool                 $refresh  Optional. If true, forces a token refresh
 *                                        even if the stored token has not yet expired.
 *                                        Defaults to false.
 *
 * @return bool True if a valid access token is available after the function
 *              completes, false if required settings are missing, a new token
 *              could not be obtained, or a refresh attempt ultimately failed.
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
	$platform_tokens = getPlatformTokens($platform);
	if ((!$platform_tokens || !$platform_tokens->access_token) && !requestNewToken($platform)) return false;
	// check if we need to refresh the token
	if ($refresh || (isset($platform_tokens->refresh_at) && $platform_tokens->refresh_at < time())) {
		$url = $api_url . '/login/oauth2/token';
		$userAgent = "User-Agent: " . APP_NAME . "/" . APP_VERSION . " (" . VENDOR_NAME . "; " . VENDOR_EMAIL . ")";
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_HEADER, true);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded", $userAgent));
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query(array(
						'grant_type' => 'refresh_token',
						'client_id' => $api_client_id,
						'client_secret' => $api_client_secret,
						'refresh_token' => $platform_tokens->refresh_token)));
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$response_data = json_decode(substr($response, $header_size), true);
		curl_close($ch);
		// if there was an error using the refresh token, request a brand new token
		if (isset($response_data['error'])) {
			$_SESSION['error_message'] = $response_data['error'];
			// delete the token (and legacy key) and request a new one
			$platform->setSetting('tokens');
			$platform->setSetting('access_token');
			$platform->save();
			if (!requestNewToken($platform)) return false;
		}
		$platform_tokens->access_token = null;
		$platform_tokens->refresh_at = null;
		if (isset($response_data['access_token']) && preg_match('/^[0-9a-zA-Z~]+$/', $response_data['access_token']))
			$platform_tokens->access_token = $response_data['access_token'];
		if (isset($response_data['expires_in']) && is_numeric($response_data['expires_in']))
			$platform_tokens->refresh_at = time() + intval($response_data['expires_in']);
		if (isset($platform_tokens->access_token) && isset($platform_tokens->refresh_at))
			setPlatformTokens($platform, $platform_tokens);
	}
	return true;
}

function getPlatformTokens($platform) {
	// check if the platform has an access token; if not, request one from Canvas
	$platform_tokens = $platform->getSetting('tokens');
	// check for a legacy setting
	if (!$platform_tokens) $platform_tokens = $platform->getSetting('access_token');
	if ($platform_tokens) {
		// check if the tokens are encrypted
		if (json_validate($platform_tokens)) {
			// not encrypted, so encrypt and re-save
			$platform_tokens = json_decode($platform_tokens);
			setPlatformTokens($platform, $platform_tokens);
			return $platform_tokens;
		}
		// decode and separate the nonce from the ciphertext
        $decoded = base64_decode($platform_tokens);
		$nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
		            
Util::logError("nonceLen: " . $nonceLen);
		$nonce = mb_substr($decoded, 0, $nonceLen, '8bit');
Util::logError("length of nonce: " . strlen($nonce));
		$ciphertext = mb_substr($decoded, $nonceLen, null, '8bit');
        // decrypt and authenticate the token
        $decrypted = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, '', $nonce, TOKEN_KEY);
		if ($decrypted === false) return false;
		if (!json_validate($decrypted)) return false;
		return json_decode($decrypted);
	} else return false;
}

function setPlatformTokens($platform, $tokens) {
	// generate unique nonce for every encryption (must never be reused)
	$nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
	// encrypt the JSON-encoded token
	$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(json_encode($tokens), '', $nonce, TOKEN_KEY);
	$platform->setSetting('tokens', base64_encode($nonce . $ciphertext));
	// clear the legacy setting
	$platform->setSetting('access_token');
	// save the platform
	$platform->save();
}

/**
 * Redirect the current user to the platform's Canvas OAuth2 authorization
 * endpoint to obtain a new access/refresh token pair. Only the user designated
 * as the platform's 'api_user_id' may trigger this flow; any other session user
 * will cause an error to be logged and false to be returned. On success, a
 * Location header is issued and exit(0) is called — this function does not return.
 *
 * @param \ceLTIc\LTI\Platform $platform The LTI platform instance for which a
 *                                        new OAuth2 token is being requested.
 *
 * @return bool False if the current session user is not the platform's
 *              api_user_id, or if the platform's 'api_url' setting is missing.
 *              Does not return on success (redirects and exits).
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
			'&redirect_uri=' . getAppUrl() . 'oauth2response.php');
	exit(0);
}

/**
 * Canvas API helper
 *
 * @param \ceLTIc\LTI\Platform $platform    The LTI platform instance to make the API call against.
 * @param string               $method      HTTP method: GET, POST, PUT, DELETE, PATCH, etc.
 * @param string|array         $endpoint    Canvas endpoint (e.g. "https://<api_url>/api/v1/courses/12345/assignments").
 * @param array                $options     Optional keys:
 *                            - 'body'      => array|json string  (will be JSON‑encoded)
 *                            - 'query'     => array of key=>value for query string
 *                            - 'headers'   => array of additional headers
 *                            - 'raw'       => bool – return raw cURL output (headers+body)
 * @return array  an associative array with the 'headers' and 'response' (associative array representing the json response)
 */
function canvasApiRequest($platform, string $method, $endpoint, array $options = []): array {
	$allResults = [];
	if (platformHasToken($platform)) {
		// the API URL must be defined in the platform settings
		$api_url = $platform->getSetting('api_url');
		if (!$api_url) return ['errors' => 'The API URL is not defined for the platform.'];
		// check if the platform has an access token; if not, request one from Canvas
/* 		$platform_tokens = $platform->getSetting('tokens');
		// check for a legacy setting
		if (!$platform_tokens) $platform_tokens = $platform->getSetting('access_token');
		if ($platform_tokens) $platform_tokens = json_decode($platform_tokens); */
		$platform_tokens = getPlatformTokens($platform);
		if (!$platform_tokens || !$platform_tokens->access_token) return ['errors' => 'The platform does not have an access token.'];

		$endpoints = [];
		if (is_string($endpoint)) $endpoints[] = $endpoint;
		elseif (is_array($endpoint)) $endpoints = $endpoint;
		else return ['errors' => 'String or array must be provided to canvasApiRequest().'];

		// Build the headers
		$userAgent = "User-Agent: " . APP_NAME . "/" . APP_VERSION . " (" . VENDOR_NAME . "; " . VENDOR_EMAIL . ")";
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer ' . $platform_tokens->access_token,
			$userAgent
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
				$payload = is_array($options['body']) ? json_encode($options['body']):(string)$options['body'];
			}
		}
		
		while (count($endpoints) > 0) {
			// Build the URLs and curl handles
			$multiHandle = curl_multi_init();
			curl_multi_setopt($multiHandle, CURLMOPT_MAX_HOST_CONNECTIONS, CONCURRENT_API_MAX_HOST_CONNECTIONS);
			curl_multi_setopt($multiHandle, CURLMOPT_MAX_TOTAL_CONNECTIONS, CONCURRENT_API_MAX_TOTAL_CONNECTIONS);
			$handles = [];
			foreach ($endpoints as $ep) {
				$url = $ep;
				// check if the options are already specified in the URL
				if (!strpos($ep, "?") && !empty($options['query']) && is_array($options['query']))
					$url .= '?' . http_build_query($options['query']);
				// prepare cURL
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
				if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HEADER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
		
			// reset $endpoints in case additional calls need to be made (paging, rate limit)
			$endpoints = [];
			// define a variable that can be set if rate limits apply
			$retryAfter = 0;
			$error = false;
			// get the responses
			foreach ($handles as $ep => $ch) {
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				// make sure there is a valid response
				if ($httpCode > 0) {
					$response = curl_multi_getcontent($ch);
					$header_split = strpos($response, "\r\n\r\n");
					if ($header_split) {
						// separate the headers and body
						list($rawHeaders, $body) = explode("\r\n\r\n", $response, 2);
					} else
						$rawHeaders = $response;
					$body_data = json_decode($body, true);

					$responseHeaders = [];
					foreach (explode("\r\n", $rawHeaders) as $h) {
						$header = explode(':', $h, 2) ?? [];
						if (is_array($header) && count($header) == 2 && $header[0] && $header[1])
							$responseHeaders[trim($header[0])] = trim($header[1]);
					}
			
					// Retry on rate‑limit (429)
					if ($httpCode === 429) {
						$retryAfter = $responseHeaders['Retry-After'] ?? ($retryAfter ? $retryAfter : 1);
						$endpoints[] = $ep;
					// check for error codes
					} else if ($httpCode < 200 || $httpCode >= 300) {
						$error  = "Canvas API error $httpCode";
						if (!empty($body)) {
							if (json_last_error() === JSON_ERROR_NONE && isset($body_data['message']))
								$error .= ": {$body_data['message']}";
							else
								$error .= ": $body";
						}
						Util::logError("cURL error: $error");
					} else {
						$ep_parts = parse_url($ep);
						$endpoint_key = $ep_parts['scheme'] . '://' . $ep_parts['host'] . $ep_parts['path'];
						// check if key already exists and append
						if (isset($allResults[$endpoint_key])) {
							// replace previous header
							$allResults[$endpoint_key]['headers'] = $responseHeaders;
							// determine how to merge the response data
							if (isset($body_data['data'])) {
								if (isset($allResults[$endpoint_key]['response']['data']))
									$allResults[$endpoint_key]['response']['data'] = array_merge($allResults[$endpoint_key]['response']['data'], $body_data['data']);
								else
									$allResults[$endpoint_key]['response']['data'] = $body_data['data'];
							} else {
								if (!is_array($body_data)) $body_data = [$body_data];
								$allResults[$endpoint_key]['response'] = array_merge($allResults[$endpoint_key]['response'], $body_data);
							}							
						} else {
							$allResults[$endpoint_key] = [
								'headers' => $responseHeaders,
								'response' => $body_data
							];
						}
					}
					// check if there are more pages
					if (isset($responseHeaders['link'])) {
						foreach (explode(',', $responseHeaders['link']) as $part) {
							if (preg_match('/<([^>]+)>;\s*rel="next"/i', trim($part), $matches)) {
								$endpoints[] = $matches[1];
								break;
							}	
						}
					}
				}
				curl_multi_remove_handle($multiHandle, $ch);
				curl_close($ch);
			}
			curl_multi_close($multiHandle);
			if ($error) return ['errors' => $error];
			if ($retryAfter) sleep((int)$retryAfter);
		}
	} else {
		return ['errors' => 'Platform does not have a valid token.'];
	}
	return $allResults;
}

/**
 * Retrieve the full list of LTI registrations for the platform's account,
 * sorted alphabetically by name, via the Canvas API endpoint:
 *   GET /api/v1/accounts/self/lti_registrations
 *
 * This includes ALL registrations, including those not visible to instructors
 * when adding apps to a course, so access is restricted to tool admins only.
 * Returns an errors array if the current user is not a tool admin, if the API
 * request fails, or if no results are returned.
 *
 * @param \ceLTIc\LTI\Platform $platform The LTI platform instance to retrieve
 *                                        registrations from.
 *
 * @return array On success, an array of LTI registration records. On failure,
 *               an associative array with an 'errors' key describing the reason.
 */
function getLTIRegistrations($platform) {
	$LTIregistrations = array();
	$url = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations';
 	if (isToolAdmin($platform))
		$LTIregistrations = canvasApiRequest($platform, 'GET', $url, ['query' => ['per_page' => 100, 'sort' => 'name', 'dir' => 'asc']]);
	else
		return ['errors' => 'You cannot complete that action.'];
	if (isset($LTIregistrations['errors'])) return $LTIregistrations;
	if (!isset($LTIregistrations[$url])) return ['errors' => 'No results returned from canvasApiAllPages()'];
	return $LTIregistrations[$url]['response']['data'];
}

/**
 * Retrieve the details for one or more LTI registrations by ID via the
 * Canvas API endpoint:
 *   GET /api/v1/accounts/self/lti_registrations/{id}
 *
 * $registrationIds may be a single numeric ID or an array of numeric IDs.
 * Non-numeric values within an array are silently skipped. Returns an errors
 * array if $registrationIds is neither a number nor an array, if the API
 * request fails, if no registration is found for a given ID, or if more than
 * one result is returned for a given ID.
 *
 * @param \ceLTIc\LTI\Platform $platform        The LTI platform instance to
 *                                               retrieve registrations from.
 * @param int|int[]            $registrationIds A single registration ID or an
 *                                               array of registration IDs to look up.
 *
 * @return array On success, an associative array keyed by integer registration
 *               ID, each containing the corresponding registration data. On
 *               failure, an associative array with an 'errors' key describing
 *               the reason.
 */
function getLTIRegistration($platform, $registrationIds) {
	$endpoints = [];
	// make sure $registrationIds is number or array of numbers
	if (is_numeric($registrationIds)) $endpoints[] = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations/' . $registrationIds;
	elseif (is_array($registrationIds)) {
		foreach($registrationIds as $id) {
			if (is_numeric($id))
				$endpoints[] = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations/' . $id;
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
 * Retrieve all LTI registrations for the platform, enriched with
 * SLAM-specific configuration details from the database. Combines the
 * results of getLTIRegistrations() with per-registration data from
 * getToolConfig(), keying the result array by registration ID.
 * Registrations for which no tool config can be resolved are excluded
 * from the result. Returns an errors array if getLTIRegistrations() fails.
 *
 * @param \ceLTIc\LTI\Platform $platform The LTI platform instance to
 *                                        retrieve tools for.
 *
 * @return array On success, an associative array keyed by integer registration
 *               ID, each containing the registration data merged with its
 *               SLAM configuration. On failure, an associative array with an
 *               'errors' key describing the reason.
 */
function getAllTools($platform) {
	$registrations = getLTIRegistrations($platform);
	if (isset($registrations['errors'])) return $registrations;
	$configuredTools = getToolConfigs($platform);
	$all_tools = array();
	foreach ($registrations as $registration) {
		$registration = getToolConfig($platform, $registration, $configuredTools);
		if ($registration) $all_tools[$registration['id']] = $registration;
	}
	return $all_tools;
}

/**
 * Determine whether one or more LTI registrations are available for the
 * specified course by querying each registration's deployment controls via
 * the Canvas API endpoint:
 *   GET /api/v1/accounts/self/lti_registrations/{id}/controls
 *
 * $registrationIds may be a single numeric ID or an array of numeric IDs.
 * Non-numeric values within an array are silently skipped. Returns an errors
 * array if $registrationIds is neither a number nor an array, or if the API
 * request fails.
 *
 * @param \ceLTIc\LTI\Platform $platform        The LTI platform instance to
 *                                               check availability against.
 * @param int|int[]            $registrationIds A single registration ID or an
 *                                               array of registration IDs to check.
 * @param int                  $courseNumber    The Canvas course ID to check
 *                                               availability for.
 *
 * @return array On success, an associative array keyed by integer registration
 *               ID. Each entry contains:
 *                 - 'available'     : (bool) True if the registration is enabled
 *                                    for the specified course, false otherwise.
 *                 - 'context_id'    : (int) The context_control ID for the course,
 *                                    present only if available is true.
 *                 - 'deployment_id' : The deployment ID for the registration,
 *                                    present only if available is true.
 *               On failure, an associative array with an 'errors' key describing
 *               the reason.
 */
function isAvailable($platform, $registrationIds, $courseNumber) {
	$endpoints = [];
	$availability = [];
	// make sure $registrationIds is number or array of numbers
	if (is_numeric($registrationIds)) $endpoints[] = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations/' . $registrationIds . '/controls';
	elseif (is_array($registrationIds)) {
		foreach($registrationIds as $id) {
			if (is_numeric($id))
				$endpoints[] = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations/' . $id . '/controls';
		}
	} else {
		return ['errors' => 'Provided registration ID must be integer or array of integers.'];
	}
	$options = ['query' => ['per_page' => 100]];
	
	$controls = canvasApiRequest($platform, 'GET', $endpoints, $options);
	if (isset($controls['errors'])) return $controls;
	foreach ($controls as $ep => $registrationControls) {
		foreach ($registrationControls['response'] as $control) {
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
 * Retrieve the SLAM-enabled LTI tools for the platform and determine whether
 * each is currently enabled in the specified course. Combines data from three
 * sources: the SLAM tool configuration (getToolConfigs()), Canvas registration
 * details (getLTIRegistration()), and per-course availability controls
 * (isAvailable()). Registration API calls are batched concurrently by
 * collecting all registration IDs before dispatching requests.
 *
 * Each tool entry in the result is augmented with:
 *   - 'name'    : The registration's 'admin_nickname' if set, otherwise 'name'.
 *   - 'enabled' : (bool) True if the tool is available for the specified course.
 *
 * The result is sorted alphabetically by name. Returns the raw error value
 * (not an errors array) if getLTIRegistration() or isAvailable() fails.
 *
 * @param \ceLTIc\LTI\Platform $platform      The LTI platform instance to
 *                                             retrieve tools for.
 * @param int                  $course_number The Canvas course ID to check
 *                                             tool availability against.
 *
 * @return array On success, a list of tool entries sorted alphabetically by
 *               name, each containing SLAM configuration data merged with
 *               Canvas registration details and course availability status.
 *               On failure, the raw error value from the first failed
 *               internal call.
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
 * Enable an LTI tool for the specified course by creating a context control
 * exception via the Canvas API endpoint:
 *   POST /api/v1/accounts/self/lti_registrations/{canvas_id}/controls
 *
 * If the tool has a configured dependency, that dependency is added
 * recursively first; if the dependency cannot be added, the operation is
 * aborted and false is returned. If the tool is already available in the
 * course, no API call is made and success is returned immediately.
 *
 * All add attempts, whether successful or not, are recorded via
 * logToolChange(). Returns an errors array if the availability check fails.
 *
 * @param \ceLTIc\LTI\Platform $platform      The LTI platform instance to
 *                                             add the tool on.
 * @param int                  $tool_id       The SLAM tool ID to add. Cast
 *                                             to int before processing.
 * @param int                  $course_number The Canvas course ID to enable
 *                                             the tool for.
 *
 * @return array|false On success, an array of integer tool IDs that were
 *                     added (including any resolved dependencies). Returns
 *                     an associative array with an 'errors' key if the
 *                     availability check fails. Returns false if the tool
 *                     config could not be found, a dependency could not be
 *                     added, or the Canvas API call failed.
 */
function addToolToCourse($platform, $tool_id, $course_number) {
	$tool_id = intval($tool_id);
	$tool_config = getToolConfigById($tool_id);
	if ($tool_config) {
		$success = array($tool_id);
		if (isset($tool_config['dependency']) && !is_null($tool_config['dependency'])) {
			$dependency_result = addToolToCourse($platform, $tool_config['dependency'], $course_number);
			if (isset($dependency_result['errors'])) return $dependency_result;
			if ($dependency_result) $success = array_merge($success, $dependency_result);
			else {
				logToolChange($platform, $tool_id, 1, $course_number, 0);
				return false;
			}
		}
		// check if it's already enabled/available
		$availability = isAvailable($platform, $tool_config['canvas_id'], $course_number);
		if (isset($availability['errors'])) return $availability;
		if (isset($availability[$tool_config['canvas_id']]) && $availability[$tool_config['canvas_id']]['available']) return $success;
		
		// try to add the tool to the course
		$endpoint = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations/' . $tool_config['canvas_id'] . '/controls';
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
 * Disable an LTI tool for the specified course by deleting its context control
 * exception via the Canvas API endpoint:
 *   DELETE /api/v1/accounts/self/lti_registrations/{canvas_id}/controls/{context_id}
 *
 * Before removing the tool, checks whether any other course-enabled tools depend
 * on it. If a blocking dependent is found that is not already part of the current
 * removal chain ($dependents), the operation is aborted and an empty array is
 * returned. If the tool itself has a dependency, that dependency is removed
 * recursively first, with the current tool ID appended to $dependents to prevent
 * circular blocking. If the tool is not currently enabled in the course, success
 * is returned without making an API call.
 *
 * All removal attempts, whether successful or not, are recorded via logToolChange().
 * Returns an errors array if getCourseTools() or isAvailable() fails.
 *
 * @param \ceLTIc\LTI\Platform $platform      The LTI platform instance to remove
 *                                             the tool from.
 * @param int                  $tool_id       The SLAM tool ID to remove. Cast to
 *                                             int before processing.
 * @param int                  $course_number The Canvas course ID to disable the
 *                                             tool for.
 * @param array                $dependents    Optional. Tool IDs already being removed
 *                                             in the current recursive chain, used to
 *                                             avoid circular blocking. Defaults to an
 *                                             empty array.
 *
 * @return array|false On success, an array of integer tool IDs that were removed
 *                     (including any resolved dependencies). Returns an empty array
 *                     if removal is blocked by a dependent tool not in the current
 *                     removal chain. Returns an associative array with an 'errors'
 *                     key if getCourseTools() or isAvailable() fails. Returns false
 *                     if the tool config could not be found, a dependency could not
 *                     be removed, or the Canvas API call failed.
 */
function removeToolFromCourse($platform, $tool_id, $course_number, $dependents = array()) {
	$tool_id = intval($tool_id);
	$tool_config = getToolConfigById($tool_id);
	if ($tool_config) {
		// check if other enabled tools are dependent on this one
		$otherEnabledTools = getCourseTools($platform, $course_number);
		if (isset($otherEnabledTools['errors'])) return $otherEnabledTools;
		foreach ($otherEnabledTools as $tool) {
			if ($tool['dependency'] == $tool_id && !in_array($tool['id'], $dependents))
				return array();
		}
		$success = array($tool_id);
		// check if it's already enabled/available
		$availability = isAvailable($platform, $tool_config['canvas_id'], $course_number);
		if (isset($availability['errors'])) return $availability;
		if (isset($availability[$tool_config['canvas_id']])) $availability = $availability[$tool_config['canvas_id']];
		if (isset($availability['available']) && $availability['available']) {
			if (isset($tool_config['dependency']) && !is_null($tool_config['dependency'])) {
				$dependents[] = $tool_id;
				$dependency_result = removeToolFromCourse($platform, $tool_config['dependency'], $course_number, $dependents);
				if (isset($dependency_result['errors'])) return $dependency_result;
				if ($dependency_result) $success = array_merge($success, $dependency_result);
				else {
					logToolChange($platform, $tool_id, 0, $course_number, 0);
					return false;
				}
			}
			// try to add the tool to the course
			$endpoint = rtrim($platform->getSetting('api_url'), '/') . '/api/v1/accounts/self/lti_registrations/' . $tool_config['canvas_id'] . '/controls/' . $availability['context_id'];
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
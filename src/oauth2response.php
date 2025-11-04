<?php
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

// Don't do anything if no $_GET value is sent.
require_once('config.php');
require_once('lib.php');

// Initialise session and database
$db = null;
$ok = init($db, true);

if(count($_GET) > 0) {
	if(isset($_GET['code']) && isset($_GET['state'])) {
		// get the platform associated with the state id
		$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
		$platform = Platform::fromRecordId($_GET['state'], $dataConnector);
		$url = $platform->getSetting('api_url') . '/login/oauth2/token';
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_HEADER, true);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query(array(
						'grant_type' => 'authorization_code',
						'client_id' => $platform->getSetting('api_client_id'),
						'client_secret' => $platform->getSetting('api_client_secret'),
						'redirect_uri' => TOOL_BASE_URL . 'oauth2response.php',
						'code' => $_GET['code'],
						'replace_tokens' => '1'
						)));
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);	
		$response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		// TO DO: check $header to make sure we're OK
		//$for_session['headers'] = get_headers_from_curl_response($header);
		$response_data = json_decode(substr($response, $header_size), true);
		Util::logError(substr($response, $header_size));
		if (isset($response_data['expires_in'])) $response_data['refresh_at'] = time() + intval($response_data['expires_in']);
		// update the token values
		$platform->setSetting('access_token', json_encode($response_data));
		$platform->save();
		// redirect
		header('Location: ' . TOOL_BASE_URL . 'index.php');
		exit(0);
	} else if(isset($_GET['error'])) {
		print('<p><strong>Error: </strong>' . $_GET['error']);
		print('<p><strong>Description: </strong>' . $_GET['error_description']);
	}
}
?>
<?php
use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

require_once('lib.php');

$ok = true;
if (isset($_SESSION['error_message'])) $ok = false;

// Initialise session and database
if ($ok) {
	$db = null;
	$ok = init($db, true);
	// Initialise parameters
	$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
	$platform = Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
	if (!platformHasToken($platform)) $ok = false;
}

if (!$ok || !isToolAdmin($platform)) {
	header(	'Location: ' . CANVAS_URL);
	exit(0);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Self LTI App Management - Tool Configuration and Availability</title>
	<meta name="description" content="An LTI app that allows Canvas users to self-manage LTI apps in their course." />
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="css/slam.css">
</head>
<body>
	<pre>
<?php
print(json_encode(getAllTools($platform), JSON_PRETTY_PRINT));
/*
$body = <<< EOD

	<div id='toolList' class='lti-tools-container'>
EOD;

$lti_tools = getAllTools($platform);
foreach ($lti_tools as $key => $lti_tool) {
	
	
	
	if (!empty($courseNumber) && isset($lti_tool['name']) && $lti_tool['visible']) {
		$body .= "\n		<div id='lti_tool_" . $key . "' class='lti-tool" .
			((isset($lti_tool['enabled']) && $lti_tool['enabled'] > 0)?" lti-tool-enabled":"") . "'>\n";
		$body .= "			<div class='switch' id='switch_" . $key . "' onclick='tool_select_" . $key . ".click();'>\n" .
			"				<input type='checkbox' id='tool_select_" . $key .
			"' onchange='updateToolInstall(" . $key . ", " . $courseNumber . ");'";
		if (isset($lti_tool['enabled']) && $lti_tool['enabled'] > 0) $body .= " checked";
		$body .= ">\n				<span class='slider round'></span>\n			</div>\n			<div>\n" .
			"				<label for='tool_select_" . $key . "' class='toggle-label'>" .
			$lti_tool['name'] . "</label>\n			</div>\n";
		if (isset($lti_tool['support_info']))
			$body .= "			<div class='tool-support'>". 
			preg_replace('/\[TOOL_NAME\]/', $lti_tool['name'], $lti_tool['support_info']) . 
			"</div>\n";
		$body .= "		</div>\n";
	}
}
$body .= "	</div>\n";
print($body);
*/
?>
	</pre>
</body>
</html>
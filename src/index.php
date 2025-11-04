<?php

use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\ResourceLink;
use ceLTIc\LTI\UserResult;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

/**
 * This page displays information passed to the LTI tool from the platform.
 *
 * @author  Kyle Tuck <kylejtuck@gmail.com>
 * @copyright  Kyle Tuck
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('lib.php');

// Initialise session and database
$db = null;
$ok = init($db, true);
// Initialise parameters
$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
$platform = Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
/* $platformCheck = new Platform($dataConnector);
$platformCheck->platformId = $platform->platformId;
$platformCheck->clientId = $platform->clientId;
$platformCheck->deploymentId = null;
if ($dataConnector->loadPlatform($platformCheck))
	$platform = $platformCheck; */
// check $platform->authenticationUrl
// check $platform->authorizationServerId
$resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
//			$resourceLink->getSetting('custom_course_number');


$showVal = function($val) {
    return $val;
};

$page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="content-language" content="EN" />
  <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
  <title>{$showVal(APP_NAME)}</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
  <link rel="stylesheet" href="css/slam.css">
  <script type="text/javascript" src="js/slam.js"></script>
</head>
<body onload="window.addEventListener('resize', setToolContainerSize); setToolContainerSize();">

EOD;

if (!platformHasToken($platform)) $ok = false;
		
if ($ok) {
	$page .= <<< EOD
	<div id='slamTitle' class='slam-title'>
		<h1><img src='https://www.uky.edu/canvas/branding/slam.png' style='height:1.2em;' alt='SLAM logo'>Self-Service LTI App Management</h1>
	</div>

EOD;
	$page .= "<pre>\n" . json_encode($_SESSION, JSON_PRETTY_PRINT) . "\n</pre>\n";
/*
	if ($slam->isAdmin())
		$page .= <<< EOD
	<div class='tool-settings'>
		<a href='./tools_admin.php'><img src='images/settings_icon.png' alt='Configure user-selectable LTI tools' style='width: 1em; height: 1em;'></a>
	</div>
EOD;
*/
	
	$page .= <<< EOD

	<div id='slamDescription' class='slam-description'>
		The following LTI apps are not endorsed or supported by the University of Kentucky
		or Instructure Canvas. If you have questions or need assistance with a third-party LTI app, please contact
		the LTI app provider.
		<p>Clicking a toggle next to a tool will enable/disable that tool automatically. You do not need to save
		changes. If there is a problem enabling a tool, the toggle will automatically turn off on its own. In this
		situation, please contact <a href='mailto:elearning@uky.edu?subject=SLAM%20LTI%20App$20Issue&body=Hello%20eLearning,%0D%0A%0D%0AThe%20LTI%20App%20[please%20tell%20us%20the%20name%20of%20the%20app%20you%20are%20trying%20to%20install]%20cannot%20be%20enabled%20in%20SLAM.%0D%0A%0D%0AThank%20you.'>eLearning</a> for additional help.
	</div>

EOD;
	
/*
	if ($slam->getCourseTitle())
		$page .= <<< EOD
	<div id='courseTitle' class='course-title'>
		<h2>LTI Tools for {$resourceLink->title}</h2>
	</div>
EOD;
*/
	$page .= <<< EOD

	<div id='toolList' class='lti-tools-container'>

EOD;
/*
	$lti_tools = $slam->getConfiguredLTITools();
	$message_boxes = "";
	foreach ($lti_tools as $key => $lti_tool) {
		if ($slam->getCourseNumber() && isset($lti_tool['name']) && $lti_tool['visible']) {
			$page .= "\n		<div id='lti_tool_" . $key . "' class='lti-tool" .
				((isset($lti_tool['enabled']) && $lti_tool['enabled'] > 0)?" lti-tool-enabled":"") . "'>\n";
			$page .= "			<div class='switch' id='switch_" . $key . "' onclick='tool_select_" . $key . ".click();'>\n" .
				"				<input type='checkbox' id='tool_select_" . $key .
				"' onchange='updateToolInstall(" . $key . ", " . $slam->getCourseNumber() . ");'";
			if (isset($lti_tool['enabled']) && $lti_tool['enabled'] > 0) $page .= " checked";
			$page .= ">\n				<span class='slider round'></span>\n			</div>\n			<div>\n" .
				"				<label for='tool_select_" . $key . "' class='toggle-label'>" .
				$lti_tool['name'] . "</label>\n			</div>\n";
			if (isset($lti_tool['support_info']))
				$page .= "			<div class='tool-support'>". 
				preg_replace('/\[TOOL_NAME\]/', $lti_tool['name'], $lti_tool['support_info']) . 
				"</div>\n";
			$page .= "		</div>\n";
		}

		if (isset($lti_tool['user_notice']) && $lti_tool['user_notice'] != '') {
			$message_boxes .= "<div class='tool-message' id='tool_message_" . $key . "'>\n" .
				"	<div id='tool_message_text_" . $key . "'>" . $lti_tool['user_notice'] . "</div>\n" .
				" <div style='clear: both; text-align: center;'>" .
				"<input type='button' class='tool-message-button' value='Cancel' onclick='toolNoticeResponse(" .
				$key . ", true);'>" . "<input type='button' class='tool-message-button' value='OK' onclick='toolNoticeResponse(" .
				$key . ", false);'></div>\n</div>";
		}
	}
	$slam->saveSession();
*/
	$page .= "	</div>\n";
	$page .= $message_boxes;
} else {
	$page .= <<< EOD
	<p style="font-weight: bold; color: #f00;">There was an error initializing the LTI application.</p>
EOD;
	// Check for any messages to be displayed
	if (isset($_SESSION['error_message'])) {
		$page .= <<< EOD
	<p style="font-weight: bold; color: #f00;">ERROR: {$_SESSION['error_message']}</p>
EOD;
		unset($_SESSION['error_message']);
	}

	if (isset($_SESSION['message'])) {
		$page .= <<< EOD
	<p style="font-weight: bold; color: #00f;">{$_SESSION['message']}</p>
EOD;
		unset($_SESSION['message']);
	}
}
$page .= <<< EOD
</body>
</html>
EOD;

// Display page
echo $page;
?>

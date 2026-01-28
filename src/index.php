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

// check if there is already an error message
$ok = true;
if (isset($_SESSION['error_message'])) $ok = false;

// Initialise session and database
if ($ok) {
	$db = null;
	$ok = init($db, true);
	// Initialise parameters
	$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
	$platform = Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
	$resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
	$courseName = $resourceLink->getSetting('custom_course_name');
	$courseSISId = $resourceLink->getSetting('custom_course_sis_id');
	$courseNumber = $resourceLink->getSetting('custom_course_number');
/* 	$allSettings = $resourceLink->getSettings(); // will return all settings
	foreach ($allSettings as $key => $setting) {
		Util::logError("key: " . $key . ", setting: " . $setting);
	} */
	if (!platformHasToken($platform)) $ok = false;
}

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
<body onload="window.addEventListener('resize', setToolContainerSize); setToolContainerSize(); getCourseTools(); initializeTimer({$showVal(IDLE_TIME)});">

EOD;

		
if ($ok) {
	$page .= <<< EOD
	<div id='slamTitle' class='slam-title'>
		<div style='width: 100%;'>
			<h1><img src='https://www.uky.edu/canvas/branding/slam.png' style='height:1.2em;' alt='SLAM logo'>Self-Service LTI App Management</h1>
		</div>
EOD;
	if (isToolAdmin($platform))
		$page .= <<< EOD
		<div class='tool-settings'>
			<a href='./admin/tools_admin.php'><img src='images/settings_icon.png' alt='Configure user-selectable LTI tools' style='width: 1em; height: 1em;'></a>
		</div>
EOD;
	$tool_list_header = htmlspecialchars($platform->getSetting('tool_list_header'), ENT_QUOTES | ENT_HTML401, 'UTF-8');
	$page .= <<< EOD
	</div>

	<div id='slamDescription' class='slam-description'>
		{$tool_list_header}
	</div>

EOD;
	if (isset($courseName)) {
		$header_course_title = $courseName;
		if (isset($courseSISId)) $header_course_title .= " (" . $courseSISId . ")";
		$page .= <<< EOD
	<div id='courseTitle' class='course-title'>
		<h2>LTI Tools for {$header_course_title}</h2>
	</div>
EOD;
	}
	$page .= <<< EOD

	<div id='toolList' class='lti-tools-container'>
	</div>
	<div id='messageBoxes'>
	</div>

EOD;
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

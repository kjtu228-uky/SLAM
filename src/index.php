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
	//$resourceLink->getSettings() will return all settings
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
  <script>
	let idleTimer; // Variable to hold the timeout ID
	const timeoutDuration = {$showVal(IDLE_TIME)};

	function resetTimer() {
		clearTimeout(idleTimer); // Clear the previous timer
		idleTimer = setTimeout(onIdle, timeoutDuration); // Set a new one
	}

	function onIdle() {
		console.log("User has been idle for a while. Taking action (e.g., logout, show message).");
		// Set the body of the page to ask user to relaunch SLAM
		relaunchSLAM = `
	<div id='slamTitle' class='slam-title'>
		<div style='width: 100%;'>
			<h1><img src='https://www.uky.edu/canvas/branding/slam.png' style='height:1.2em;' alt='SLAM logo'>Self-Service LTI App Management</h1>
		</div>
	</div>
	<h2>Page timeout</h2>
	<p>Your session has timed out. Please re-launch SLAM from the course menu.</p>`;
		document.body.innerHTML = relaunchSLAM;
	}

	// Event listeners to detect user activity
	document.addEventListener('mousemove', resetTimer);
	document.addEventListener('keydown', resetTimer);
	document.addEventListener('click', resetTimer);
	document.addEventListener('scroll', resetTimer);

	// Start the timer initially
	resetTimer();
  </script>
</head>
<body onload="window.addEventListener('resize', setToolContainerSize); setToolContainerSize(); getCourseTools();">

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
	$page .= <<< EOD
	</div>

	<div id='slamDescription' class='slam-description'>
		The following LTI apps are not endorsed or supported by the University of Kentucky
		or Instructure Canvas. If you have questions or need assistance with a third-party LTI app, please contact
		the LTI app provider.
		<p>Clicking a toggle next to a tool will enable/disable that tool automatically. You do not need to save
		changes. If there is a problem enabling a tool, the toggle will automatically turn off on its own. In this
		situation, please contact <a href='mailto:elearning@uky.edu?subject=SLAM%20LTI%20App$20Issue&body=Hello%20eLearning,%0D%0A%0D%0AThe%20LTI%20App%20[please%20tell%20us%20the%20name%20of%20the%20app%20you%20are%20trying%20to%20install]%20cannot%20be%20enabled%20in%20SLAM.%0D%0A%0D%0AThank%20you.'>eLearning</a> for additional help.
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

<?php
use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

require_once('../lib.php');

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
	header(	'Location: ' . $platform->getSetting('api_url'));
	exit(0);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Self LTI App Management - Tool Configuration and Availability</title>
	<meta name="description" content="An LTI app that allows Canvas users to self-manage LTI apps in their course." />
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="../css/slam.css">
	<script>
		let idleTimer; // Variable to hold the timeout ID
		const timeoutDuration = <?php echo IDLE_TIME; ?>;

		function resetTimer() {
			clearTimeout(idleTimer); // Clear the previous timer
			idleTimer = setTimeout(onIdle, timeoutDuration); // Set a new one
		}

		function onIdle() {
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
<body>
<div style='display: flex; flex-direction: column; height: 98vh;'>
<?php
$showVal = function($val) {
	return $val;
};
$body = <<< EOD
	<div class='slam-title'>
		<h1><img src='{$showVal(TOOL_BASE_URL)}/images/icon50.png' alt='SLAM logo'>Self-Service LTI App Management</h1>
	</div>
	<div class='slam-description'>
		The following LTI apps are available for configuration in your instance.
	</div>
	<div id='toolList' class='lti-tools-container'>
EOD;

$lti_tools = getAllTools($platform);
foreach ($lti_tools as $key => $lti_tool) {
	if (isset($lti_tool['name'])) {
		$body .= "\n		<div id='lti_tool_" . $key . "' class='lti-tool" .
			((isset($lti_tool['visible']) && $lti_tool['visible'] > 0)?" lti-tool-enabled":"") . "'>\n";
		$body .= '<div class="lti-tool-text">' . $lti_tool['name'] . '</div>';
		$body .= '<div class="lti-tool-icon"><a href="./edit_tool.php?id=' . $key . '">';
		$body .= '<img src="' . TOOL_BASE_URL . '/images/edit.png" alt="Edit settings for ' . $lti_tool['name'] . '"></a></div>';
		$body .= "		</div>\n";
	}
}
$body .= "	</div>\n";
print($body);
?>
</div>
</body>
</html>
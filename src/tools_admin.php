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
<div style='display: flex; flex-direction: column; height: 98vh;'>
<?php
//print(json_encode(getAllTools($platform), JSON_PRETTY_PRINT));

$body = <<< EOD
	<div id='slamTitle' class='slam-title'>
		<h1><img src='https://www.uky.edu/canvas/branding/slam.png' style='height:1.2em;' alt='SLAM logo'>Self-Service LTI App Management</h1>
	</div>
	<div id='slamDescription' class='slam-description'>
		The following LTI apps are not endorsed or supported by the University of Kentucky
		or Instructure Canvas. If you have questions or need assistance with a third-party LTI app, please contact
		the LTI app provider.
		<p>Clicking a toggle next to a tool will enable/disable that tool automatically. You do not need to save
		changes. If there is a problem enabling a tool, the toggle will automatically turn off on its own. In this
		situation, please contact <a href='mailto:elearning@uky.edu?subject=SLAM%20LTI%20App$20Issue&body=Hello%20eLearning,%0D%0A%0D%0AThe%20LTI%20App%20[please%20tell%20us%20the%20name%20of%20the%20app%20you%20are%20trying%20to%20install]%20cannot%20be%20enabled%20in%20SLAM.%0D%0A%0D%0AThank%20you.'>eLearning</a> for additional help.
	</div>
	<div id='toolList' class='lti-tools-container'>
EOD;

$lti_tools = getAllTools($platform);
foreach ($lti_tools as $key => $lti_tool) {
	if (isset($lti_tool['name'])) {
		$body .= "\n		<div id='lti_tool_" . $key . "' class='lti-tool" .
			((isset($lti_tool['visible']) && $lti_tool['visible'] > 0)?" lti-tool-enabled":"") . "'>\n";
		$body .= '<div class="lti-tool-text">' . $lti_tool['name'] . '</div>';
		$body .= '<div class="lti-tool-icon"><img src="./images/edit.png" alt="Edit settings for ' . $lti_tool['name'] . '"></div>';
		$body .= "		</div>\n";
	}
}
$body .= "	</div>\n";
print($body);
?>
</div>
</body>
</html>
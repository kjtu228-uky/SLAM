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
	<script type="text/javascript" src="js/slam.js"></script>
</head>
<body onload="initializeTimer(<?php echo IDLE_TIME; ?>);">
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
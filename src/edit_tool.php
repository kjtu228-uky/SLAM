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

if (!$ok || !isToolAdmin($platform) || !isset($_GET['id'])) {
	header('Location: ' . TOOL_BASE_URL . 'index.php');
	exit(0);
}
$lti_tools = getAllTools($platform);
if (!isset($lti_tools[$_GET['id']])) {
	header('Location: ' . TOOL_BASE_URL . 'index.php');
	exit(0);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Self LTI App Management - Tool Configuration</title>
	<meta name="description" content="An LTI app that allows Canvas users to self-manage LTI apps in their course." />
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="css/slam.css">
</head>
<body>
<div style='display: flex; flex-direction: column; height: 98vh;'>
<?php
$showVal = function($val) {
	return $val;
};
$tool_id = $_GET['id'];
$tool_name = $lti_tools[$tool_id]['name'];
if (isset($lti_tools[$tool_id]['admin_nickname'])) $tool_name = $lti_tools[$tool_id]['admin_nickname'];
$is_visible = $lti_tools[$tool_id]['visible']?" checked":"";
$body = <<< EOD
	<div class='slam-title'>
		<h1><img src='{$showVal(TOOL_BASE_URL)}/images/icon50.png' alt='SLAM logo'>Self-Service LTI App Management</h1>
	</div>
	<div class='slam-description'>
		The form to edit will follow this section. Tool : {$lti_tools[$_GET['id']]['name']}
	</div>
	<div class='lti-tool-editor'>
		<form action="edit_tool.php" method="get">
			<input type="hidden" id="id" name="tool_id" value="{$_GET['id']}">
			<input type="hidden" id="update_tool" value="true">
			<div style="display: flex; flex-direction: row;">
				<div>
					<label for="visible" style="margin: 2px; padding: 2px;">Visible</label>
				</div>
				<div class="switch" onclick="visible.click();">
					<input type="checkbox" id="visible" {$is_visible}">
					<span class="slider round"></span>
				</div>
			</div>
		</form>
	</div>
EOD;
print($body);
?>
</div>
</body>
</html>
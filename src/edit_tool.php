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
		<h2>Edit {$tool_name}</h2>
	</div>
	<div class='lti-tool-editor'>
		<form action="edit_tool.php" method="get">
			<input type="hidden" id="id" name="tool_id" value="{$_GET['id']}">
			<input type="hidden" id="update_tool" value="true">
			<div class="lti-tool-editor-form-item">
				<div>
					<label for="visible" class="lti-tool-editor-label">Visible</label>
				</div>
				<div class="switch" onclick="visible.click();">
					<input type="checkbox" id="visible" {$is_visible}">
					<span class="slider round"></span>
				</div>
			</div>

			<div class='lti-tool-editor-form-item'>
				<label for="dependency" class="lti-tool-editor-label">Depends on:</label>
				<select id="dependency" name="dependency">
					<option value="">-- None --</option>
EOD;
	foreach ($lti_tools as $lti_tool) {
		$body .= <<< EOD
					<option value="{$lti_tool['id']}">{$lti_tool['name']}</option>
EOD;
	}
	$body .= <<< EOD
				</select>
			</div>

			<div class='lti-tool-editor-form-item'>
				<label for="config" class="lti-tool-editor-label">Config (JSON):</label>
				<textarea id="config" name="config" rows="5" cols="50"></textarea>
			</div>

			<div class='lti-tool-editor-form-item'>
				<label for="userNotice" class="lti-tool-editor-label">User Notice:</label>
				<textarea id="userNotice" name="userNotice" rows="5" cols="50"></textarea>
			</div>

			<div class='lti-tool-editor-form-item'>
				<label for="supportInfo" class="lti-tool-editor-label">Support Info:</label>
				<textarea id="supportInfo" name="supportInfo" rows="5" cols="50"></textarea>
			</div>
			
			<div class='lti-tool-editor-form-item'>
				<button type="submit">Update Object</button>
			</div>
		</form>
	</div>
EOD;
print($body);
?>
</div>
</body>
</html>
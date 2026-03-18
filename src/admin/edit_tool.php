<?php
use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\Util;
use ceLTIc\LTI\Enum\LogLevel;

require_once('../lib.php');

$ok = true;
if (isset($_SESSION['error_message'])) $ok = false;
$tool_base_url = getAppUrl(1);

// Initialise session and database
if ($ok) {
	$db = null;
	$ok = init($db, true);
	if (!isset($_SESSION['consumer_pk'])) $ok = false;
	else {
		// Initialise parameters
		$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
		$platform = Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
		if (!platformHasToken($platform)) $ok = false;
	}
}
// make sure user is an admin
if (!$ok || !isToolAdmin($platform) || !isset($_GET['id'])) {
	header('Location: ' . $tool_base_url . 'index.php');
	exit(0);
}
// is this a request to update the configuration of the tool settings in the database?
if (isset($_GET['update_tool'])) {
	$updateResult = setToolConfig($platform, $_GET);
}
// retrieve all of the tool configurations
$lti_tools = getAllTools($platform);
if (!isset($lti_tools[$_GET['id']])) $_SESSION['error_message'] = "Unable to edit tool. Tool ID does not exist.";
if (isset($lti_tools['errors'])) $_SESSION['error_message'] = $lti_tools['errors'];
if (isset($_SESSION['error_message'])) {
	header('Location: ' . $tool_base_url . 'index.php');
	exit(0);
}
$tool_id = $_GET['id'];
$tool_name = $lti_tools[$tool_id]['name'];
if (isset($lti_tools[$tool_id]['admin_nickname'])) $tool_name = $lti_tools[$tool_id]['admin_nickname'];
$changes_saved = (isset($_GET['update_tool']) && $updateResult)?"<span class='update-notification'>Changes saved</span>":"";
$is_visible = $lti_tools[$tool_id]['visible']?" checked":"";
// prepare text for textareas
$html_tool_support = "";
$html_tool_adv_config = "";
$html_tool_notice = "";
if ($lti_tools[$tool_id]['support_info'] != null)
	$html_tool_support = htmlspecialchars($lti_tools[$tool_id]['support_info'], ENT_QUOTES | ENT_HTML401, 'UTF-8');
if ($lti_tools[$tool_id]['user_notice'] != null)
	$html_tool_notice = htmlspecialchars($lti_tools[$tool_id]['user_notice'], ENT_QUOTES | ENT_HTML401, 'UTF-8');
if ($lti_tools[$tool_id]['config'] != null)
	$html_tool_adv_config = htmlspecialchars($lti_tools[$tool_id]['config'], ENT_QUOTES | ENT_HTML401, 'UTF-8');
// simple function to output defined values in heredoc
$showVal = function($val) {
	return $val;
};
$body = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Self LTI App Management - Tool Configuration</title>
	<meta name="description" content="An LTI app that allows Canvas users to self-manage LTI apps in their course." />
	<meta name="viewport" content="width=device-width, initial-scale=0.9">
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="../css/slam.css?v=202602241616">
	<link rel="stylesheet" href="../css/wysi.min.css"/>
	<script src="../js/wysi.min.js"></script>
	<script type="text/javascript" src="../js/slam.js"></script>
	<script>
		var changeTimer;
		Wysi({
			el: '.tool-admin-textarea',
			autoGrow: true,
			tools: [
				'bold', 'italic', 'underline', '|', 
				'link', '|', 
				'removeFormat'
			],
			onChange: (content) => {
				//console.log('Content changed:', content);
				changeNotify(true);
			}
		});
		function changeNotify(showMessage = false) {
			if (showMessage) {
				clearTimeout(changeTimer); // Clear the previous timer
				document.getElementById('changeNotice').innerHTML = "<span class='update-notification'>** Unsaved changes **</span>";
				document.getElementById('tool_update_button').disabled = false;
			}
			else document.getElementById('changeNotice').innerHTML = "";
		}
		function changesTimer() {
			changeTimer = setTimeout(changeNotify, 5000); // Set a new one
		}
	</script>
</head>
<body onload="initializeTimer({$showVal(IDLE_TIME)}); changesTimer();">
	<div class='slam-title'>
		<h1><img src='{$tool_base_url}images/icon50.png' alt='SLAM logo'>Self-Service LTI App Management</h1>
	</div>
	<div style='width: 100%;'>
		<a href='{$tool_base_url}index.php'>SLAM</a> &gt; <a href='{$tool_base_url}admin/tools_admin.php'>Tools Admin</a> &gt; Edit Tool
	</div>
	<div class='slam-description'>
		<h2>{$tool_name} Configuration</h2>
	</div>
	<div class='tool-admin-panel'>
		<form action="edit_tool.php" method="get" onsubmit="tool_update_button.disabled = true; return true;">
			<input type="hidden" name="id" value="{$_GET['id']}">
			<input type="hidden" name="update_tool" value="true">
			<div id='changeNotice' class='tool-admin-button-panel'>
				{$changes_saved}
			</div>
			<div class="tool-admin-form-item">
				<div>
					<label for="visible" class="tool-admin-label">Visible</label>
				</div>
				<div class="switch" onclick="visible.click();" onchange="changeNotify(true);">
					<input type="checkbox" id="visible" name="visible" {$is_visible}>
					<span class="slider round"></span>
				</div>
			</div>

			<div class='tool-admin-form-item'>
				<label for="supportInfo" class="tool-admin-label">Support Info:</label>
				<textarea id="supportInfo"
					name="supportInfo" rows="5" class="tool-admin-textarea"
					placeholder="Information displayed beneath the tool name to provide support information to the instructor.">{$html_tool_support}</textarea>
			</div>

			<div class='tool-admin-form-item'>
				<label for="userNotice" class="tool-admin-label">User Notice:</label>
				<textarea id="userNotice"
					name="userNotice" rows="5" class="tool-admin-textarea"
					placeholder="This will be displayed to the instructor as a pop-up when they enable the tool.">{$html_tool_notice}</textarea>
			</div>

			<div class='tool-admin-form-item'>
				<label for="dependency" class="tool-admin-label">Depends on:</label>
				<select id="dependency" name="dependency" class="tool-admin-select" onchange="changeNotify(true);">
					<option value="">-- None --</option>
EOD;
	foreach ($lti_tools as $lti_tool) {
		// don't allow a tool to depend on itself
		if ($lti_tool['id'] != $_GET['id']) {
			$selected_option = ($lti_tool['id'] == $lti_tools[$tool_id]['dependency'])?" selected":"";
			$body .= <<< EOD
					<option value="{$lti_tool['id']}"{$selected_option}>{$lti_tool['name']}</option>
EOD;
		}
	}
	$body .= <<< EOD
				</select>
			</div>
			
			<div class='tool-admin-button-panel'>
				<button type="button" onclick="window.location.href='{$tool_base_url}admin/tools_admin.php'" class='button button-primary'>Cancel</button>
				<button id="tool_update_button" type="submit" class='button button-primary' disabled>Update</button>
			</div>
			<div class='tool-admin-text'>
				<p>The <strong>User Notice</strong> text field supports the &#91;DEPLOYMENT_ID&#93; custom tag.
					SLAM will substitute the corresponding value in the message displayed to the instructor.</p>
			</div>
		</form>
	</div>
</body>
</html>
EOD;
print($body);
?>
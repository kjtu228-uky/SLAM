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
// make sure user is an admin
if (!$ok || !isToolAdmin($platform) || !isset($_GET['id'])) {
	header('Location: ' . TOOL_BASE_URL . 'index.php');
	exit(0);
}
// is this a request to update the configuration of the tool settings in the database?
if (isset($_GET['update_tool'])) {
	$updateResult = setToolConfig($platform, $_GET);
}
// retrieve all of the tool configurations
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
	<link rel="stylesheet" href="../css/slam.css">
	<script type="text/javascript" src="../js/slam.js"></script>
</head>
<body onload="initializeTimer(<?php echo IDLE_TIME; ?>);">
<div style='display: flex; flex-direction: column; height: 98vh;'>
<?php
$showVal = function($val) {
	return $val;
};
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
$body = <<< EOD
	<div class='slam-title'>
		<div style='width: 100%;'>
			<h1><img src='{$showVal(TOOL_BASE_URL)}/images/icon50.png' alt='SLAM logo'>Self-Service LTI App Management</h1>
		</div>
		<div class='tool-settings'>
			<a href='./tools_admin.php'><img src='../images/settings_icon.png' alt='Configure user-selectable LTI tools' style='width: 1em; height: 1em;'></a>
		</div>
	</div>
	<div class='slam-description'>
		<h2>{$tool_name} Configuration</h2>
	</div>
	<div class='lti-tool-editor'>
		<form action="edit_tool.php" method="get">
			<input type="hidden" name="id" value="{$_GET['id']}">
			<input type="hidden" name="update_tool" value="true">
			<div class="lti-tool-editor-form-item">
				<div>
					<label for="visible" class="lti-tool-editor-label">Visible</label>
				</div>
				<div class="switch" onclick="visible.click();">
					<input type="checkbox" id="visible" name="visible" {$is_visible}>
					<span class="slider round"></span>
				</div>
			</div>

			<div class='lti-tool-editor-form-item'>
				<label for="dependency" class="lti-tool-editor-label">Depends on:</label>
				<select id="dependency" name="dependency" class="lti-tool-editor-select">
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

			<div class='lti-tool-editor-form-item'>
				<label for="userNotice" class="lti-tool-editor-label">User Notice:</label>
				<textarea id="userNotice"
					name="userNotice" rows="5" class="lti-tool-editor-textarea"
					placeholder="This will be displayed to the instructor as a pop-up when they enable the tool.">{$html_tool_notice}</textarea>
			</div>

			<div class='lti-tool-editor-form-item'>
				<label for="supportInfo" class="lti-tool-editor-label">Support Info:</label>
				<textarea id="supportInfo"
					name="supportInfo" rows="5" class="lti-tool-editor-textarea"
					placeholder="Information displayed beneath the tool name to provide support information to the instructor.">{$html_tool_support}</textarea>
			</div>
			
			<div class='lti-tool-editor-button-panel'>
				<button type="button" onclick="window.location.href='{$showVal(TOOL_BASE_URL)}/admin/tools_admin.php'" class='lti-tool-editor-button'>Cancel</button>
				<button type="submit" class='lti-tool-editor-button'>Update</button>
			</div>
			<div class='lti-tool-editor-button-panel'>
				{$changes_saved}
			</div>
			<div class='lti-tool-editor-text'>
				<p>The <strong>User Notice</strong> and <strong>Support Info</strong> text fields will accept a subset of HTML tags (strong, href, i).</p>
				<p>The <strong>User Notice</strong> text field supports custom tags &#91;DEPLOYMENT_ID&#93; and &#91;TOOL_NAME&#93;.
					SLAM will substitute the corresponding values in the message displayed to the instructor.</p>
			</div>
		</form>
	</div>
EOD;
print($body);
?>
</div>
</body>
</html>
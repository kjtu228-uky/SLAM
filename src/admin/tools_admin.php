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
if (!$ok || !isToolAdmin($platform)) {
	header(	'Location: ' . $platform->getSetting('api_url'));
	exit(0);
}
// is this a request to update the configuration of the tool settings in the database?
if (isset($_GET['update_platform_settings'])) {
	$updateResult = updatePlatformSettings($platform, $_GET);
}
$changes_saved = (isset($_GET['update_platform_settings']) && $updateResult)?"<span class='update-notification'>Changes saved</span>":"";
$tool_admins = $platform->getSetting('tool_admins');
if ($tool_admins)
	$tool_admins = htmlspecialchars($tool_admins, ENT_QUOTES | ENT_HTML401, 'UTF-8');
else $tool_admins = "";
$tool_list_header = $platform->getSetting('tool_list_header');
if ($tool_list_header)
	$tool_list_header = htmlspecialchars(json_decode($tool_list_header), ENT_QUOTES | ENT_HTML401, 'UTF-8');
else $tool_list_header = "";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Self LTI App Management - Tool Configuration and Availability</title>
	<meta name="description" content="An LTI app that allows Canvas users to self-manage LTI apps in their course." />
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="../css/slam.css">
	<link rel="stylesheet" href="../css/wysi.min.css"/>
	<script src="../js/wysi.min.js"></script>
	<script type="text/javascript" src="../js/slam.js"></script>
	<script>
		var changeTimer;
		Wysi({
			el: '#tool_list_header',
			autoGrow: false,
			tools: [
				'bold', 'italic', 'underline', '|', 
				'link', 'hr', '|', 
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
			}
			else document.getElementById('changeNotice').innerHTML = "";
		}
		function changesTimer() {
			changeTimer = setTimeout(changeNotify, 5000); // Set a new one
		}
	</script>
</head>
<body onload="initializeTimer(<?php echo IDLE_TIME; ?>); changesTimer();">
<div style='display: flex; flex-direction: column; height: 98vh;'>
<?php
$showVal = function($val) {
	return $val;
};
$body = <<< EOD
	<div class='slam-title'>
		<h1><img src='{$showVal(TOOL_BASE_URL)}/images/icon50.png' alt='SLAM logo'>Self-Service LTI App Management</h1>
	</div>
	<div class='tool-admin-panel'>
		<form action="tools_admin.php" method="get" onsubmit="tool_admin_button.disabled = true; return true;">
			<input type="hidden" name="update_platform_settings" value="true">
			<div class='tool-admin-form-item'>
				<label for="tool_admins" class="tool-admin-label">Tool Admins:</label>
				<input type="text" id="tool_admins"
					name="tool_admins" rows="1" class="tool-admin-textarea"
					placeholder="A comma-separated list of login IDs of tool administrators (people who can access this page)."
					value="{$tool_admins}"
					oninput="changeNotify();"
				</input>
			</div>

			<div class='tool-admin-form-item'>
				<label for="tool_list_header" class="tool-admin-label">Tool List Header:</label>
				<textarea id="tool_list_header"
					name="tool_list_header" rows="5" class="tool-admin-textarea"
					placeholder="This text appears above the list of tools that instructors see.">{$tool_list_header}</textarea>
			</div>
			
			<div class='tool-admin-button-panel'>
				<button id='tool_admin_button' type='submit' class='button button-primary'>Update</button>
			</div>
			<div id='changeNotice' class='tool-admin-button-panel'>
				{$changes_saved}
			</div>
			<div class='tool-admin-text'>
				<p>The <strong>Tool Admins</strong> is a comma-separated list of login IDs.</p>
				<p>The <strong>Tool List Header</strong> text is displayed above the tools instructors see.</p>
			</div>
		</form>
	</div>
	<div class='tool-admin-text'>
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
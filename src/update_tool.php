<?php
use ceLTIc\LTI;
use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;
use ceLTIc\LTI\ResourceLink;

/**
 * update_tool.php
 *
 * Used by the primary index.php file to make calls to add/remove LTI Apps for user.
 *
 * @author     Kyle Tuck
 * @version    0.1.3
 * @since      File available since Release 0.1
 */
require_once('config.php');
require_once('lib.php');

/*
session_name(SESSION_NAME);
session_start();
*/

header("Content-Type: application/json; ");
$result = array('messages' => array());

// must include in $_GET: tool_id, action
// actions:
//		add: add the tool to a course
//		remove: remove the tool from a course
//		update: change the details of the tool in the database
//		detail: get the configuration details of the tool from the database

//		delete: remove the tool from the database

$make_change = true;
if (!isset($_GET['tool_id'])) {
	$result['messages'][] = "tool_id not provided";
	$make_change = false;
}
if (!isset($_GET['action'])) {
	$result['messages'][] = "action not provided";
	$make_change = false;
}

// check if there is already an error message
$ok = true;
if (isset($_SESSION['error_message'])) $make_change = false;

// Initialise session and database
if ($make_change) {
	$db = null;
	$make_change = init($db, true);
	// Initialise parameters
	$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
	$platform = Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
	$resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
	$courseName = $resourceLink->getSetting('custom_course_name');
	$courseSISId = $resourceLink->getSetting('custom_course_sis_id');
	$courseNumber = $resourceLink->getSetting('custom_course_number');
	//$resourceLink->getSettings() will return all settings
	if (!platformHasToken($platform)) $make_change = false;
}

if ($make_change) {
	$lti_tools = getConfiguredLTITools($platform, $courseNumber);
	$tool_id = $_GET['tool_id'];
	$result = array();
	if ($_GET['action'] == 'add' && $lti_tools[$tool_id]['enabled'] == 0) {
		if ($_SESSION['isAdmin'] || $_SESSION['isStaff']) {
			if (isset($lti_tools[$tool_id]['dependency']) && $lti_tools[$lti_tools[$tool_id]['dependency']]['enabled'] == 0)
				$result = addLTIToolToCourse($platform, $courseNumber, $lti_tools[$tool_id]['dependency']);
			if (!isset($result['errors'])) $result = addLTIToolToCourse($platform, $courseNumber, $tool_id);
			if (!isset($result['errors'])) $result = getConfiguredLTITools($platform, $courseNumber);
		} else {
			$result['errors'] = "You are not authorized to add a tool to this course.";
		}
	}
	
/*
		$user_id = $slam->getUserId();

			// check if this tool has a dependency on another tool
			if (isset($lti_tools[$tool_id]['dependency']) && $lti_tools[$lti_tools[$tool_id]['dependency']]['enabled'] == 0)
				$success = $slam->addLTIToolToCourse($lti_tools[$tool_id]['dependency']);
			if ($success) $slam->addLTIToolToCourse($tool_id);
			$result = $slam->getConfiguredLTITools();
		} else if ($_GET['action'] == 'remove' && $lti_tools[$tool_id]['enabled'] > 0) {
			$success = true;
			// check if this tool has a dependency on another tool
			if (isset($lti_tools[$tool_id]['dependency'])) {
				// check if that tool is meeting any other tool dependency
				$dependencies = 0;
				foreach ($lti_tools as $key=>$lti_tool) {
					if ($lti_tool['enabled'] && isset($lti_tool['dependency']) && $lti_tool['dependency'] == $lti_tools[$tool_id]['dependency'])
						$dependencies++;
				}
				if ($dependencies < 2) $success = $slam->removeLTIToolFromCourse($lti_tools[$tool_id]['dependency']);
			}
			if ($success) $slam->removeLTIToolFromCourse($tool_id);
			$result = $slam->getConfiguredLTITools();
		} else if ($_GET['action'] == 'detail') {
			$tool_config = $slam->getToolConfig($tool_id);
			if ($tool_config) $result = $tool_config;
			else $result['messages'][] = "No tool with id " . $tool_id;
		} else if ($_GET['action'] == 'update') {
			$tool_details = json_decode(file_get_contents('php://input'), true);
			if (!$tool_details) $result['messages'][] = "No tool details provided.";
			else $result = $slam->updateToolDetails($tool_details);
		} else if ($_GET['action'] == 'delete') {
			$result = $slam->deleteLTITool($tool_id);
		} else if ($_GET['action'] == 'usage') {
			$result = $slam->getCoursesUsingTool($tool_id);
		}

	} else {
		$result['messages'][] = "No auth token to change tool.";
	}
*/
}
print(json_encode($result));
?>
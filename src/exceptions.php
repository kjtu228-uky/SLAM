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

header("Content-Type: application/json; ");
$result = array();

// must include in $_GET: action
// actions:
//		list: list the tools for a course
//		add: add the tool to a course
//		remove: remove the tool from a course
if (!isset($_GET['action'])) {
	print(json_encode(array('success' => false, 'errors' => 'No action specified.')));
	exit;
}

// Initialise parameters
$db = null;
if (!init($db, true)) {
	print(json_encode(array('success' => false, 'errors' => 'Unable to initialize.')));
	exit;
}
$dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
$platform = Platform::fromRecordId($_SESSION['consumer_pk'], $dataConnector);
$resourceLink = ResourceLink::fromRecordId($_SESSION['resource_pk'], $dataConnector);
$courseNumber = $resourceLink->getSetting('custom_course_number');

// make sure we have an API token
if (!platformHasToken($platform)) {
	print(json_encode(array('success' => false, 'errors' => 'No API token available.')));
	exit;
}

// check if just requesting the tool list for the course
if ($_GET['action'] == 'list') {
	print(json_encode(getCourseTools($platform, $courseNumber)));
	exit;
}

// if not just checking for the tool list, the tool_id must be specified in addition to the action
if (!isset($_GET['tool_id'])) {
	print(json_encode(array('success' => false, 'errors' => 'No tool_id provided.')));
	exit;
}

if ($_GET['action'] == "add") {
	$result = addToolToCourse($platform, $_GET['tool_id'], $courseNumber);
	if (!$result) {
		print(json_encode(array('success' => false, 'errors' => 'Unable to add tool to course.')));
		exit;
	} else {
		print(json_encode(array('success' => true, 'installed' => $result)));
		exit;
	}
} else if ($_GET['action'] == 'remove') {
	if (removeToolFromCourse($platform, $_GET['tool_id'], $courseNumber)) {
		print(json_encode(array('success' => true)));
		exit;
	} else {
		print(json_encode(array('success' => false, 'errors' => 'Unable to remove tool from course.')));
		exit;
	}
} else {
	print(json_encode(array('success' => false, 'errors' => 'Invalid action.')));
	exit;
}

print(json_encode(array('success' => false, 'errors' => 'Unexpected result.')));
?>
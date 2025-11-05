<?php

use ceLTIc\LTI\DataConnector;
use ceLTIc\LTI\Platform;

/**
 * This page processes a launch request from an LTI platform.
 *
 * @author  Stephen P Vickers <stephen@spvsoftwareproducts.com>
 * @copyright  SPV Software Products
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3
 */
require_once('config.php');
require_once('SLAM.php');

// Cancel any existing session
session_name(SESSION_NAME);
session_start();
$_SESSION = array();
session_destroy();

// Initialise database
$db = null;
if (init($db)) {
    $dataConnector = DataConnector\DataConnector::getDataConnector($db, DB_TABLENAME_PREFIX);
	// If the consumer (platform) was auto-registered, there may not be a deployment_id. This will add it
	//  if the platform exists, is enabled, and is not protected.
	if (!isset($_POST['deployment_id']) && isset($_POST['lti_deployment_id']))
		$_POST['deployment_id'] = $_POST['lti_deployment_id'];
	if (isset($_POST['iss']) && isset($_POST['client_id']) && isset($_POST['deployment_id'])) {
		$platformCheck = new Platform($dataConnector);
		$platformCheck->platformId = $_POST['iss'];
		$platformCheck->clientId = $_POST['client_id'];
		$platformCheck->deploymentId = $_POST['deployment_id'];
		if (!$dataConnector->loadPlatform($platformCheck)) {
			$platformCheck->deploymentId = null;
			if ($dataConnector->loadPlatform($platformCheck)) {
				$platformCheck = Platform::fromPlatformId($_POST['iss'], $_POST['client_id'], null, $dataConnector);
				if ($platformCheck->enabled && !$platformCheck->protected) {
					$platformCheck->deploymentId = $_POST['deployment_id'];
					$platformCheck->save();
				}
			}
		}
	}
	$tool = new SLAM($dataConnector);
	$tool->setParameterConstraint('resource_link_id', true, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('user_id', true, 50, array('basic-lti-launch-request'));
	$tool->setParameterConstraint('roles', true, null, array('basic-lti-launch-request'));
} else {
	$tool = new SLAM(null);
	$tool->reason = $_SESSION['error_message'];
}
$tool->handleRequest();
?>
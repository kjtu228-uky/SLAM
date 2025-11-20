<?php
require_once('config.php');
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
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Self LTI App Management - Tool Configuration and Availability</title>
	<meta name="description" content="An LTI app that allows Canvas users to self-manage LTI apps in their course." />
	<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
	<link rel="stylesheet" href="css/slam.css">
</head>
<body>
	<p>List the tools for the admin.</p>
	<pre>
<?php
/*
	foreach ($lti_tools as $key => $lti_tool) {
		print("			<option value='" . $key . "'>" . $lti_tool['name'] . "</option>\n");
	}
*/
?>
	</pre>
</body>
</html>
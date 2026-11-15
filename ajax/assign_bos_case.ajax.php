<?php
/**
 * Assigns BoS case boxes/slots to newly received entries (action=assign, the default),
 * or clears all assignments (action=reset). Admin (level 0) only.
 */

ob_start();
require('../paths.php');
require(CONFIG.'bootstrap.php');
ini_set('display_errors', 0); // Change to 0 for prod; change to 1 for testing.
ini_set('display_startup_errors', 0); // Change to 0 for prod; change to 1 for testing.
error_reporting(0); // Change to error_reporting(0) for prod; change to E_ALL for testing.

$return_json = array();
$status = 0;
$assigned = 0;
$cases = 0;

// CSRF: require a same-origin Referer for this session-authenticated, data-changing action.
$referrer_ok = (isset($_SERVER['HTTP_REFERER'])) && (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $_SERVER['SERVER_NAME']);

if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] == 0) && ($referrer_ok)) {

	require_once(LIB.'bos_case.lib.php');

	if ((isset($_GET['action'])) && ($_GET['action'] == "reset")) {
		if (reset_bos_case_slots($prefix."brewing") == 0) $status = 1;
	}

	else {
		$bos_case_result = assign_bos_case_slots($prefix."brewing");
		if ($bos_case_result['status'] == 0) $status = 1;
		$assigned = $bos_case_result['assigned'];
		$cases = $bos_case_result['cases'];
	}

}

else $status = 9; // Session expired, not enabled, etc.

$return_json = array(
	"status" => "$status",
	"assigned" => "$assigned",
	"cases" => "$cases"
);

echo json_encode($return_json);
mysqli_close($connection);

?>

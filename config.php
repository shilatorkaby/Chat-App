<?php

if (!defined("a328763fe27bba")) {
	die("you can't access this file directly");
}
$config_json = file_get_contents(__DIR__ . '/config.json');
$config = json_decode($config_json, true);

// Define MySQL connection constants
define("MYSQL_SERVERNAME", $config['mysql']['servername']);
define("MYSQL_USERNAME", $config['mysql']['username']);
define("MYSQL_DATABASE", $config['mysql']['db_name']);
define("MYSQL_PASSWORD", $config['mysql']['password']);


define("CONFIG_FILE_FIRED", true);
define("APP_ROOT_ABS_PATH", __dir__);
define("MODULES_DIR_NAME", "modules");

define("SAVE_ERRORS_TO_LOG_ON_SHUTDOWN_FUNCTION", true);
define("CLEAR_POST_GET_ON_API_ACCESS", true);

define("CONFIG_FILE_PATH", APP_ROOT_ABS_PATH . "/config.json");
define("APP_TIMEZONE", "Asia/Jerusalem");

define("DELETE_ERRORS_LOG_FILE_ON_START", true);

define("INI_SET_DISPLAY_ERRORS", 1);
define("INI_SET_LOG_ERRORS", 1);
define("ERROR_REPORTING", E_ALL & ~E_NOTICE & ~E_WARNING);
define("ERRORS_LOG_FILE_PATH", APP_ROOT_ABS_PATH . "/errors.log");

define("APP_MODULES", [
	"errors_report",
	"timezone_set",
	"mysql",
	"shutdown_function",
	"str_contains",
	"json_utilities",
	"ip_utilities",
	"get_a_config_value",
	"plugins",
	"general_functions",
]);

$GLOBALS["ini_set_display_errors"] = INI_SET_DISPLAY_ERRORS ?? $GLOBALS["ini_set_display_errors"] ?? true;
$GLOBALS["ini_set_log_errors"] = INI_SET_LOG_ERRORS ?? $GLOBALS["ini_set_log_errors"] ?? true;
$GLOBALS["errors_log_file_path"] = ERRORS_LOG_FILE_PATH ?? $GLOBALS["errors_log_file_path"] ?? true;
$GLOBALS["error_reporting"] = constant("ERROR_REPORTING") ?? $GLOBALS["error_reporting"] ?? E_ALL & ~E_NOTICE & ~E_WARNING;
ini_set('display_errors', $GLOBALS["ini_set_display_errors"]);
ini_set("log_errors", $GLOBALS["ini_set_log_errors"]);
ini_set("error_log", $GLOBALS["errors_log_file_path"]);
error_reporting($GLOBALS["error_reporting"]);

define("APP_INIT_FILE_PATH", __dir__ . "/app_init.php");
$app_init_file_path = constant("APP_INIT_FILE_PATH");

if (!file_exists($app_init_file_path)) {
	error_log("app_init_file_path: $app_init_file_path is not exists");
	die("ERROR 943867289736487325647832543726523745");
}

try {
	require_once($app_init_file_path);
} catch (Throwable $e) {
	$msg = "[INCLUDE FAILED] [2098739876382175478623547326] "
		. $e->getMessage()
		. "File: " . $e->getFile() . " | "
		. "Line: " . $e->getLine();

	error_log($msg);
	die($msg);
	die("ERROR 403987390247329874329036789746327");
}

include_all_modules();
include_all_plugins();

// Store app load datetime with a safe fallback
// IDE helper only (won't run at runtime)
if (false) {
    function get_now(): string { return date('Y-m-d H:i:s'); }
}
if (function_exists('get_now')) {
    $GLOBALS['app_loaded_datetime'] = get_now();
} else {
    // Fallback so IDEs stop complaining and runtime still works
    $GLOBALS['app_loaded_datetime'] = date('Y-m-d H:i:s');
}

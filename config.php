<?php

if (!defined("a328763fe27bba")) {
	die("you can't access this file directly");
}

$mysql_host = getenv("MYSQL_HOST") ?: "db";
$mysql_db   = getenv("MYSQL_DATABASE") ?: "whatsapp_clone";
$mysql_user = getenv("MYSQL_USER") ?: "root";
$mysql_pass = getenv("MYSQL_PASSWORD") ?: (getenv("MYSQL_ROOT_PASSWORD") ?: "");

define("MYSQL_DEFAULT_SERVERNAME", $mysql_host);
define("MYSQL_DEFAULT_USERNAME",   $mysql_user);
define("MYSQL_DEFAULT_DB_NAME",    $mysql_db);
define("MYSQL_DEFAULT_DB_PASSWORD", $mysql_pass);

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

if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
	define("MYSQL_DEFAULT_SERVERNAME", "localhost");
	define("MYSQL_DEFAULT_USERNAME", "root");
	define("MYSQL_DEFAULT_DB_NAME", "waclonedem_db28072025135752");
	define("MYSQL_DEFAULT_DB_PASSWORD", "");
	define("ENV", "dev");
} else {
	define("MYSQL_DEFAULT_SERVERNAME", "localhost");
	define("MYSQL_DEFAULT_USERNAME", "root");
	define("MYSQL_DEFAULT_DB_NAME", "");
	define("MYSQL_DEFAULT_DB_PASSWORD", "");
	define("ENV", "prod");
}

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

if (function_exists("get_now")) {
	$globals["app_loaded_datetime"] = get_now();
}

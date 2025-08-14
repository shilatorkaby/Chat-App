<?php
	if(!defined("a328763fe27bba")){
		die("you can't access this file directly");
	}
	
	define("APP_ERROR_REPORT_MODULE",true);
	
	$GLOBALS["ini_set_display_errors"] = INI_SET_DISPLAY_ERRORS ?? $GLOBALS["ini_set_display_errors"] ?? true;
	$GLOBALS["ini_set_log_errors"] = INI_SET_LOG_ERRORS ?? $GLOBALS["ini_set_log_errors"] ?? true;
	$GLOBALS["errors_log_file_path"] = ERRORS_LOG_FILE_PATH ?? $GLOBALS["errors_log_file_path"] ?? true;
	$GLOBALS["error_reporting"] = constant("ERROR_REPORTING") ?? $GLOBALS["error_reporting"] ?? E_ALL & ~E_NOTICE &~E_WARNING;
	ini_set('display_errors', $GLOBALS["ini_set_display_errors"]);
	ini_set("log_errors", $GLOBALS["ini_set_log_errors"]);
	ini_set("error_log", $GLOBALS["errors_log_file_path"]);
	error_reporting($GLOBALS["error_reporting"]);	
		
	if(DELETE_ERRORS_LOG_FILE_ON_START===true){
		if(file_exists($GLOBALS["errors_log_file_path"])){
			unlink($GLOBALS["errors_log_file_path"]);
		}
	}
	
	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	});	
	
	set_exception_handler(function($e){
		$msg = "
			[".date("Y-m-d H:i:s")."]
			UNCAUGHT EXCEPTION: ".$e->getMessage()."
			in ".$e->getFile()."
			on line ".$e->getLine()."\n";
		
		try{				
			error_log($msg);
		}catch(Throwable $e2){
			$msg2 = "
				[".date("Y-m-d H:i:s")."]
				UNCAUGHT EXCEPTION: ".$e2->getMessage()."
				in ".$e2->getFile()."
				on line ".$e2->getLine()."\n";			
				
			file_put_contents($GLOBALS["errors_log_file_path"], $msg, FILE_APPEND);
			file_put_contents($GLOBALS["errors_log_file_path"], $msg2, FILE_APPEND);
		}
		
	});	

	$GLOBALS["error_reporting"] = [];
	$GLOBALS["error_reporting"]["level"] = error_reporting();
	$GLOBALS["error_reporting"]["E_ALL"] = false;
	$GLOBALS["error_reporting"]["E_NOTICE"] = false;
	$GLOBALS["error_reporting"]["E_WARNING"] = false;
	$GLOBALS["error_reporting"]["E_DEPRECATED"] = false;
	
	if($GLOBALS["error_reporting"]["level"] & E_ALL){
		$GLOBALS["error_reporting"]["E_ALL"] = true;
	}
	
	if($GLOBALS["error_reporting"]["level"] & E_NOTICE){
		$GLOBALS["error_reporting"]["E_NOTICE"] = true;
	}	
	
	if($GLOBALS["error_reporting"]["level"] & E_WARNING){
		$GLOBALS["error_reporting"]["E_WARNING"] = true;
	}		
	
	if($GLOBALS["error_reporting"]["level"] & E_DEPRECATED){
		$GLOBALS["error_reporting"]["E_DEPRECATED"] = true;
	}	
	
?>
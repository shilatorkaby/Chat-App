<?php
	if(!defined("a328763fe27bba")){
		die("you can't access this file directly");
	}
	
	define("APP_TIMEZONE_MODULE",true);
	
	if(defined("APP_TIMEZONE")){
		$globals["app_timezone"]["timezone_alias"] = constant("APP_TIMEZONE");
	}
		
	if(defined("APP_TIMEZONE") || $globals["app_timezone"]["timezone_alias"]){
		try{
			date_default_timezone_set($globals["app_timezone"]["timezone_alias"]);
		}catch(Throwable $e){
			error_log("Could not set PHP system timezone to ".$globals["app_timezone"]["timezone_alias"]." Make it UTC");
			$globals["app_timezone"]["timezone_alias"] = "UTC";
			date_default_timezone_set($globals["app_timezone"]["timezone_alias"]);
		}
	}else{
		$globals["app_timezone"]["timezone_alias"] = "UTC";
		date_default_timezone_set($globals["app_timezone"]["timezone_alias"]);		
	}
?>
<?php
	if(!defined("a328763fe27bba")){
		die("you can't access this file directly");
	}

	define("APP_INIT_FILE_FIRED",true);

	function basic_log_to_file($msg) {
		$file_path = __DIR__ . "/log_me.txt";
		$now = date("Y-m-d H:i:s");

		// קבלת מידע על הקריאה לפונקציה
		$debug_backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2); // לוקחים את השורה הקוראת
		$caller_info = $debug_backtrace[1] ?? $debug_backtrace[0]; // אם קוראים ישירות מהלוג, ניקח את הראשון

		$file = $caller_info['file'] ?? 'unknown_file';
		$line = $caller_info['line'] ?? 'unknown_line';
		$function = $caller_info['function'] ?? 'global_scope';

		// המרת מערך ל־JSON אם צריך
		if (is_array($msg)) {
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
		}

		// הרכבת שורת הלוג
		$log_line = "$now | [$function] in $file:$line | $msg\n";

		file_put_contents($file_path, $log_line, FILE_APPEND);
	}
	
	function include_module($name=null,$base_directory=null){
		if(!$name){
			error_log("you called include_module function without name argument: $name");
			return false;
		}
		
		$app_root_absolute_dir = APP_ROOT_ABS_PATH ?? $GLOBALS["app_root_abs_path"] ?? __dir__;
		
		if(!$base_directory){
			$modules_dir_name = MODULES_DIR_NAME ?? $GLOBALS["modules_dir_name"] ?? "modules";
			$base_directory = $app_root_absolute_dir.DIRECTORY_SEPARATOR.$modules_dir_name.DIRECTORY_SEPARATOR ;
		}
		
		$module_file_path = $base_directory.DIRECTORY_SEPARATOR.$name.".php";
		$module_file_path_root = $app_root_absolute_dir.DIRECTORY_SEPARATOR.$name.".php";
				
		if(!file_exists($module_file_path)){
						
			if(!file_exists($module_file_path_root)){				
				error_log("[INCLUDE FAILED] [3420987328946732986432897643827] files ".$module_file_path.", ".$module_file_path_root." ARE NOT EXIST");
				return false;
			}
			
			$module_file_path = $module_file_path_root;
		}
		
		try{
			require_once($module_file_path);
		}catch(Throwable $e){
			$msg = "[INCLUDE FAILED] [3876239876478392764328765832546] ".
				"Message: ".$e->getMessage()." | ".
				"File: ".$e->getFile()." | ".
				"Line: ".$e->getLine()." | ".
				"Module: $name | ".
				"Trace: ".$e->getTraceAsString();
				
		    error_log($msg);
			return false;
		}	

		
		$GLOBALS["modules"][] = $name;
		return true;
	}	
	
	function include_all_plugins($file_name="functions.php",$base_directory=__dir__."/plugins/") {		
		$plugins = [];
		$included_files = [];
		
		if(!is_dir($base_directory)){
			return false;
		}
		
		$files_and_folders_under_this_path = scandir($base_directory);
		
		foreach($files_and_folders_under_this_path as $this_file_or_folder){
			if($this_file_or_folder === '.' || $this_file_or_folder === '..'){
				continue;
			}

			if(is_dir($base_directory.$this_file_or_folder)){
				
				$plugins[] = $this_file_or_folder;
				$file_to_include = $base_directory.$this_file_or_folder.DIRECTORY_SEPARATOR.$file_name;
				if(file_exists($file_to_include)){
					$included_files[] = $file_to_include;
					try{
						include_once($file_to_include);
					}catch(Throwable $e){
						$GLOBALS["errors"][] = $e;
						$msg = "[".date("Y-m-d H:i:s")."] [INCLUDE FAILED] [3290847632986] IN INCLIDED FILE: $file_to_include UNCAUGHT EXCEPTION: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
						error_log($msg);
					}
				}
				
			}
		}
		
		$GLOBALS["plugins"] = $GLOBALS["plugins"] ?? [];
		$GLOBALS["plugins"]["included_files"] = $GLOBALS["plugins"]["included_files"] ?? [];
						
		try{
			$plugins_array_unique = array_unique($plugins);
			$included_files_array_unique = array_unique($included_files);
			
			$GLOBALS["plugins"] = $GLOBALS["plugins"]+$plugins_array_unique;
			$GLOBALS["plugins"]["included_files"] = $GLOBALS["plugins"]["included_files"] + $included_files_array_unique;			
		}catch(Throwable $e){
			$msg = "[".date("Y-m-d H:i:s")."] UNCAUGHT EXCEPTION: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
			error_log($msg);
			$GLOBALS["errors"][] = $e;
		}		
	}	

	function include_all_modules(){		
		$GLOBALS["app_modules"] = constant("APP_MODULES") ?? NULL;
				
		if(!$GLOBALS["app_modules"]){
			return false;
		}
		
		foreach($GLOBALS["app_modules"] as $module_name){
			include_module($module_name);
		}		
	}

	$GLOBALS = [];
	
	$GLOBALS["php_now"] = date("Y-m-d H:i:s T");
	$GLOBALS["app_timezone"]["php_date_timezone_returns"] = date("T");	
	$GLOBALS["app_root_absolute_dir"] = __dir__;
?>
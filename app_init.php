<?php
	if(!defined("a328763fe27bba")){
		die("you can't access this file directly");
	}

	// Marker to indicate this init file has been loaded
	define("APP_INIT_FILE_FIRED", true);

	/**
	 * Basic file logger (append-only).
	 * Writes timestamp, caller (file:line, function), and the message.
	 */
	function basic_log_to_file($msg) {
		$file_path = __DIR__ . "/log_me.txt";
		$now = date("Y-m-d H:i:s");

		// Capture the immediate caller for trace context (best-effort)
		$debug_backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$caller_info = $debug_backtrace[1] ?? $debug_backtrace[0];

		$file = $caller_info['file'] ?? 'unknown_file';
		$line = $caller_info['line'] ?? 'unknown_line';
		$function = $caller_info['function'] ?? 'global_scope';

		if (is_array($msg)) {
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
		}

		$log_line = "$now | [$function] in $file:$line | $msg\n";
		file_put_contents($file_path, $log_line, FILE_APPEND);
	}
	
	/**
	 * Includes a single module by name.
	 * Search order:
	 *   1) <APP_ROOT>/<modules_dir>/<name>.php
	 *   2) <APP_ROOT>/<name>.php
	 */
	function include_module($name = null, $base_directory = null){
		if(!$name){
			error_log("include_module called without name argument");
			return false;
		}
		
		$app_root_absolute_dir = APP_ROOT_ABS_PATH ?? $GLOBALS["app_root_abs_path"] ?? __DIR__;
		
		if(!$base_directory){
			$modules_dir_name = MODULES_DIR_NAME ?? $GLOBALS["modules_dir_name"] ?? "modules";
			$base_directory = $app_root_absolute_dir . DIRECTORY_SEPARATOR . $modules_dir_name . DIRECTORY_SEPARATOR;
		}
		
		$module_file_path = $base_directory . DIRECTORY_SEPARATOR . $name . ".php";
		$module_file_path_root = $app_root_absolute_dir . DIRECTORY_SEPARATOR . $name . ".php";
				
		if(!file_exists($module_file_path)){
			if(!file_exists($module_file_path_root)){
				error_log("[INCLUDE FAILED] files {$module_file_path}, {$module_file_path_root} do not exist");
				return false;
			}
			$module_file_path = $module_file_path_root;
		}
		
		try{
			require_once($module_file_path);
		}catch(Throwable $e){
			$msg = "[INCLUDE FAILED] ".
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
	
	/**
	 * Includes plugins by scanning <base>/PLUGIN_NAME/functions.php for each plugin folder.
	 * Collects the included files under $GLOBALS["plugins"]["included_files"].
	 */
	function include_all_plugins($file_name = "functions.php", $base_directory = __DIR__ . "/plugins/") {
		$plugins = [];
		$included_files = [];
		
		if(!is_dir($base_directory)){
			return false;
		}
		
		$files_and_folders = scandir($base_directory);
		
		foreach($files_and_folders as $entry){
			if($entry === '.' || $entry === '..'){
				continue;
			}

			if(is_dir($base_directory.$entry)){
				$plugins[] = $entry;
				$file_to_include = $base_directory . $entry . DIRECTORY_SEPARATOR . $file_name;
				if(file_exists($file_to_include)){
					$included_files[] = $file_to_include;
					try{
						include_once($file_to_include);
					}catch(Throwable $e){
						$GLOBALS["errors"][] = $e;
						$msg = "[".date("Y-m-d H:i:s")."] [INCLUDE FAILED] in file: $file_to_include UNCAUGHT EXCEPTION: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
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
			
			$GLOBALS["plugins"] = $GLOBALS["plugins"] + $plugins_array_unique;
			$GLOBALS["plugins"]["included_files"] = $GLOBALS["plugins"]["included_files"] + $included_files_array_unique;
		}catch(Throwable $e){
			$msg = "[".date("Y-m-d H:i:s")."] UNCAUGHT EXCEPTION: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()."\n";
			error_log($msg);
			$GLOBALS["errors"][] = $e;
		}
	}	

	/**
	 * Includes modules listed in APP_MODULES constant (if defined).
	 */
	function include_all_modules(){		
		$GLOBALS["app_modules"] = defined("APP_MODULES") ? constant("APP_MODULES") : null;
		if(!$GLOBALS["app_modules"]){
			return false;
		}
		foreach($GLOBALS["app_modules"] as $module_name){
			include_module($module_name);
		}
	}

	// Convenience globals
	$GLOBALS["php_now"] = date("Y-m-d H:i:s T");
	$GLOBALS["app_timezone"]["php_date_timezone_returns"] = date("T");
	$GLOBALS["app_root_absolute_dir"] = __DIR__;

	// Bootstrap toggles (tune these for dev/prod)
	if (!defined('APP_DB_BOOTSTRAP')) define('APP_DB_BOOTSTRAP', true);              // Set to false in production if you prefer migrations
	if (!defined('APP_DB_SEED_SAMPLE_USER')) define('APP_DB_SEED_SAMPLE_USER', false); // Set true to insert a sample user if absent
	if (!defined('APP_DB_BOOTSTRAP_FORCE')) define('APP_DB_BOOTSTRAP_FORCE', false);   // Set true to force re-run even if lock file exists

	if (APP_DB_BOOTSTRAP) {
		$lock = __DIR__ . '/bootstrap.lock';
		$shouldRun = APP_DB_BOOTSTRAP_FORCE || !is_file($lock);

		if ($shouldRun) {
			// Connect using constants you already define in config.php
			$host = defined('MYSQL_SERVERNAME') ? MYSQL_SERVERNAME : (getenv('DB_HOST') ?: 'localhost');
			$user = defined('MYSQL_USERNAME') ? MYSQL_USERNAME : (getenv('DB_USER') ?: 'root');
			$pass = defined('MYSQL_PASSWORD') ? MYSQL_PASSWORD : (getenv('DB_PASS') ?: '');
			$name = defined('MYSQL_DATABASE') ? MYSQL_DATABASE : (getenv('DB_NAME') ?: 'home_test');

			$db = @new mysqli($host, $user, $pass, $name);
			if ($db->connect_errno) {
				basic_log_to_file('[bootstrap] DB connect failed: ' . $db->connect_error);
			} else {
				$db->set_charset('utf8mb4');

				// Helper to execute an SQL statement and log on error
				$safe = function(string $sql) use ($db) {
					if ($db->query($sql) === false) {
						basic_log_to_file('[bootstrap][SQL ERR] ' . $db->error . ' | ' . $sql);
						return false;
					}
					return true;
				};

				// Helper: insert config only if the key is missing (no overwrite)
				$put_cfg_if_missing = function(string $key, string $val) use ($db) {
					$k = $db->real_escape_string($key);
					$v = $db->real_escape_string($val);
					$sql = "INSERT INTO config (setting,value)
					        SELECT '$k','$v' FROM DUAL
					        WHERE NOT EXISTS (SELECT 1 FROM config WHERE setting='$k' LIMIT 1)";
					$db->query($sql);
				};

				/* --- Tables required by the auth/OTP flow --- */
				$safe("CREATE TABLE IF NOT EXISTS users (
					id INT AUTO_INCREMENT PRIMARY KEY,
					username VARCHAR(191) NOT NULL,
					email VARCHAR(191) NOT NULL,
					display_name VARCHAR(191) DEFAULT NULL,
					created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
					UNIQUE KEY uniq_username (username),
					UNIQUE KEY uniq_email (email)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				$safe("CREATE TABLE IF NOT EXISTS config (
					setting VARCHAR(191) NOT NULL PRIMARY KEY,
					value   TEXT NOT NULL
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				$safe("CREATE TABLE IF NOT EXISTS otp_requests (
					id INT AUTO_INCREMENT PRIMARY KEY,
					user_id INT NOT NULL,
					ip VARCHAR(64) NOT NULL,
					requested_at DATETIME NOT NULL,
					INDEX (user_id), INDEX (ip), INDEX (requested_at)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				$safe("CREATE TABLE IF NOT EXISTS otp_codes (
					id INT AUTO_INCREMENT PRIMARY KEY,
					user_id INT NOT NULL,
					otp_hash CHAR(64) NOT NULL,
					created_at DATETIME NOT NULL,
					expires_at DATETIME NOT NULL,
					used_at DATETIME NULL,
					ip VARCHAR(64) DEFAULT NULL,
					INDEX (user_id), INDEX (expires_at)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				$safe("CREATE TABLE IF NOT EXISTS auth_tokens (
					id INT AUTO_INCREMENT PRIMARY KEY,
					user_id INT NOT NULL,
					token VARCHAR(255) NOT NULL,
					ip VARCHAR(64) DEFAULT NULL,
					user_agent VARCHAR(255) DEFAULT NULL,
					created_at DATETIME NOT NULL,
					expires_at DATETIME NOT NULL,
					revoked_at DATETIME NULL,
					UNIQUE KEY uniq_token (token),
					INDEX (user_id), INDEX (expires_at)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				$safe("CREATE TABLE IF NOT EXISTS login_attempts (
					id INT AUTO_INCREMENT PRIMARY KEY,
					user_id INT NOT NULL,
					ip VARCHAR(64) NOT NULL,
					success TINYINT(1) NOT NULL,
					created_at DATETIME NOT NULL,
					INDEX (user_id), INDEX (ip), INDEX (created_at)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				$safe("CREATE TABLE IF NOT EXISTS login_blocks (
					user_id INT NOT NULL,
					ip VARCHAR(64) NOT NULL,
					blocked_until DATETIME NOT NULL,
					PRIMARY KEY (user_id, ip),
					INDEX (blocked_until)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

				/* --- Default config (insert only if missing; do not overwrite) --- */
				$put_cfg_if_missing('otp_cooldown_seconds', '30');
				$put_cfg_if_missing('otp_max_per_hour', '4');
				$put_cfg_if_missing('otp_max_per_day', '10');
				$put_cfg_if_missing('otp_length', '6');
				$put_cfg_if_missing('otp_valid_minutes', '10');
				$put_cfg_if_missing('token_ttl_hours', '24');
				$put_cfg_if_missing('login_block_after_failed_per_hour', '10');
				$put_cfg_if_missing('login_block_hours', '24');
				$put_cfg_if_missing('otp_email_from', 'no-reply@example.com');
				$put_cfg_if_missing('otp_email_from_name', 'Your App');

				// Do NOT override existing Brevo key. Insert a placeholder only if missing.
				$put_cfg_if_missing('brevo_api_key', 'SET_ME_IN_CONFIG_OR_ENV');

				// Generate a strong otp_secret only if it does not exist yet
				$res = $db->query("SELECT 1 FROM config WHERE setting='otp_secret' LIMIT 1");
				if ($res && !$res->fetch_row()) {
					$secret = bin2hex(random_bytes(32)); // 64 hex chars
					$secEsc = $db->real_escape_string($secret);
					$db->query("INSERT INTO config (setting,value) VALUES ('otp_secret','$secEsc')");
				}

				/* --- Optional: seed a sample user for testing (disabled by default) --- */
				if (APP_DB_SEED_SAMPLE_USER) {
					$u  = $db->real_escape_string('testuser');
					$em = $db->real_escape_string('test@example.com'); // change later to your email
					$dn = $db->real_escape_string('Test User');
					// Insert if absent (do not overwrite existing user)
					$db->query("INSERT IGNORE INTO users (username,email,display_name)
					            VALUES ('$u','$em','$dn')");
				}

				// Write a lock file to ensure this bootstrap runs only once
				@file_put_contents($lock, date('c')." bootstrap ok\n");
				basic_log_to_file('[bootstrap] completed');
				$db->close();
			}
		}
	}

	?>

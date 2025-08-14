<?php

	function config_file_str_to_numeric_cast($value){

		if(is_numeric($value)){
			if (str_contains($value, '.') || str_contains(strtolower($value), 'e')) {
				return (float) $value;
			} else {
				return (int) $value;
			}
		}

		return $value;
	}

	function config_value_str_to_datetime_obj($value,$format="Y-m-d H:i:s"){
		if(!is_string($value)){
			return $value;
		}

		try{
			$dt = DateTime::createFromFormat($format, $value);
			if ($dt && $dt->format($format) === $value) {
				return $dt;
			}
		}catch(Exception $e){
			return $value;
		}

		return $value;
	}

	function config_file_is_datetime_pass($datetime_obj,$equal_to="now"){
				
		if($equal_to === "now"){
			$equal_to = date("Y-m-d H:i:s");
		}
		
		$equal_to_as_a_datetime_obj = new DateTime($equal_to);
				
		if($equal_to_as_a_datetime_obj>$datetime_obj){
			return true;
		}
		
		return false;
	}

	function get_a_config_value($setting){
		if(defined("CONFIG")){
			if(isset(constant("CONFIG")[$setting])){
				return constant("CONFIG")[$setting];
			}
		}

		if(isset($GLOBALS["config"][$setting])){
			return $GLOBALS["config"][$setting];
		}

		if(APP_MYSQL_MODULE===true && mysql_is_there_any_mysql_connection()){
			$rows = mysql_fetch_array(
				"SELECT value FROM config WHERE `setting` = ? LIMIT 1",
				[$setting]
			);

			$result = null;

			if(!$result && isset($rows[0][0])){
				$result = $rows[0][0];
			}

			if(!$result && isset($rows[0])){
				$result = $rows[0];
			}

			if(!$result && isset($rows)){
				$result = $rows;
			}
		}

		if(defined("CONFIG_FILE_PATH") && file_exists(CONFIG_FILE_PATH)){

			$config_json = file_get_contents(CONFIG_FILE_PATH);
			$config_values = json_decode($config_json,true);

			if($config_values && isset($config_values[$setting])){
				$result = $config_values[$setting];
			}
		}

		if(isset($result) && !empty($result)){
			$result = config_file_str_to_numeric_cast($result);
			$result = config_value_str_to_datetime_obj($result);
			
			$return = $result;
			
			if(is_string($result)){
				if(strtolower($result)=="true"){
					$return = true;
				}

				if(strtolower($result)=="false"){
					$return = false;
				}
			}

			$GLOBALS["config"][$setting] = $return;

			return $return;
		}

		return null;
	}

?>
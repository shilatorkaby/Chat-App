<?php
	
	define("APP_MYSQL_MODULE",true);
	
	if(!(function_exists("mysql_connect"))){
		function mysql_connect($mysql_servername=null,$mysql_username=null,$mysql_db_name=null,$mysql_password=null,$connection_id=0,$mysql_charset="utf8mb4"){
			
			if(!$mysql_servername){
				$mysql_servername = constant("MYSQL_DEFAULT_SERVERNAME") ?? null;
			}
			
			if(!$mysql_username){
				$mysql_username = constant("MYSQL_DEFAULT_USERNAME") ?? null;
			}
			
			if(!$mysql_db_name){
				$mysql_db_name = constant("MYSQL_DEFAULT_DB_NAME") ?? null;
			}
			
			if(!$mysql_password){
				$mysql_password = constant("MYSQL_DEFAULT_DB_PASSWORD") ?? null;
			}
			
			if(!$mysql_servername || !$mysql_username || !$mysql_db_name || !$mysql_password){
				error_log(date("Y-m-d H:i:s")."It seems that MYSQL server details, user, password is not set");
				return false;
			}
			
			try{
				$mysqli = new mysqli($mysql_servername, $mysql_username, $mysql_password);
			}catch(Throwable $e){
				$msg = date("Y-m-d H:i:s")." - COULD NOT CONNECT MYSQL SERVER WITH $mysql_servername, $mysql_username, $mysql_password ".$e->getMessage();
				error_log($msg);
				return false;				
			}
			
			try{
				$mysqli->select_db($mysql_db_name);
				$mysqli->set_charset($mysql_charset);
			}catch(Throwable $e){
				$msg = date("Y-m-d H:i:s")." - ".$e->getMessage();
				error_log($msg);
				return false;				
			}			
			
			mysqli_report(MYSQLI_REPORT_OFF);
				
			if(isset($GLOBALS["app_timezone"]["timezone_alias"])){
				try{
					$mysqli->query('SET @@session.time_zone = "'.$GLOBALS["app_timezone"]["timezone_alias"].'";');
				}catch(Exception $e){
					error_log(date("Y-m-d H:i:s")."Could not set DB @@session.time_zone to ".$GLOBALS["app_timezone"]["timezone_alias"]." I'd try with a different way");
					
					try{
						$app_timezone_element = new DateTimeZone($GLOBALS["app_timezone"]["timezone_alias"]);
					}catch(Exception $e){
						error_log(date("Y-m-d H:i:s")."It seems that GLOBALS[app_timezone][timezone_alias] is wrong, setting it to UTC");
						$GLOBALS["app_timezone"]["timezone_alias"] = "UTC";
						date_default_timezone_set($GLOBALS["app_timezone"]["timezone_alias"]);
						
						$app_timezone_element = new DateTimeZone($GLOBALS["app_timezone"]["timezone_alias"]);
					}
					
					$now_utc = new DateTime('now', new DateTimeZone('UTC'));

					$offset_in_seconds = $app_timezone_element->getOffset($now_utc);
					$offset_in_hours = $offset_in_seconds/3600;
					
					$sign = ($offset_in_hours >= 0) ? '+' : '-';
					$abs_hours = abs(floor($offset_in_hours));
					$abs_minutes = abs(($offset_in_hours - floor($offset_in_hours)) * 60);

					$formatted_offset = sprintf('%s%02d:%02d', $sign, $abs_hours, $abs_minutes);
					$GLOBALS["app_timezone"]["mysql_offset"] = $formatted_offset;
						
					
					try{
						$mysqli->query('SET @@session.time_zone = "'.$formatted_offset.'";');
					}catch(Exception $e){
						error_log(date("Y-m-d H:i:s")." | Error while trying to run MySql Query 'SET @@session.time_zone = '".$formatted_offset."';' ".$e->getMessage());
						die("ERROR 4309878984732986743827364823756487325487623");
					}
				}
			}
			
			if(isset($GLOBALS["mysql_servername"])){
				unset($GLOBALS["mysql_servername"]);
			}
			
			
			if(isset($GLOBALS["mysql_username"])){
				unset($GLOBALS["mysql_username"]);
			}
			
			
			if(isset($GLOBALS["mysql_db_name"])){
				unset($GLOBALS["mysql_db_name"]);
			}
			
			
			if(isset($GLOBALS["mysql_password"])){
				unset($GLOBALS["mysql_password"]);
			}

			if($mysqli->connect_error){
				$GLOBALS["mysql_last_error"] = $mysqli->connect_error;
				return false;
			}else{
				if(!isset($GLOBALS["mysql_connections"])){
					$GLOBALS["mysql_connections"] = [];
				}
				
				$GLOBALS["mysql_connections"][$connection_id] = $mysqli;
				
				return $mysqli;
			}
		}
	}
	
	function get_mysqli_connection($connection_id=0){
		if(!isset($GLOBALS["mysql_connections"])){
			return mysql_connect();
		}
		
		if($GLOBALS["mysql_connections"][$connection_id] instanceof mysqli){
			return $GLOBALS["mysql_connections"][$connection_id];
		}
		return false;
	}
	
	function mysql_close_all_connections(){

		if(!isset($GLOBALS["mysql_connections"])){
			return false;
		}
		foreach($GLOBALS["mysql_connections"] as $this_connection){
			if ($this_connection instanceof mysqli) {
				$this_connection->close();
			}
		}
		return true;
	}

	function mysql_prepared_execute($query, $params = [], $conn = false) {
		
		if (!$conn) {
			$conn = get_mysqli_connection();
		}
		
		$stmt = $conn->prepare($query);
		
		if (!$stmt) {
			$GLOBALS["mysql_last_error"] = $conn->error;
			return false;
		}
		
		if (!empty($params)) {
			$types = '';
			$values = [];
			foreach ($params as $param) {
				if (is_int($param)) {
					$types .= 'i';
				} elseif (is_float($param)) {
					$types .= 'd';
				} elseif (is_string($param)) {
					$types .= 's';
				} else {
					$types .= 'b';
				}
				$values[] = $param;
			}
			$stmt_bind_params = array_merge([$types], $values);
			$tmp = [];
			foreach ($stmt_bind_params as $key => $value) {
				$tmp[$key] = &$stmt_bind_params[$key];
			}
			call_user_func_array([$stmt, 'bind_param'], $tmp);
		}
		
		if (!$stmt->execute()) {
			$GLOBALS["mysql_last_error"] = $stmt->error;
			return false;
		}
		
		if (stripos(trim($query), 'SELECT') === 0) {
			return $stmt->get_result();
		}
		
		return [
			"success" => true,
			"affected_rows" => $stmt->affected_rows,
			"insert_id" => $stmt->insert_id,
			"error" => $stmt->error,
			"errno" => $stmt->errno,
			"sqlstate" => $stmt->sqlstate,
			"query" => $query,
			"mysql_return_final_query" => mysql_return_final_query($query,$params),
		];		
		
		return $stmt;
	}

	if(!(function_exists("mysql_query"))){
		function mysql_query($query, $conn = false){
			if(!$conn){
				$conn = get_mysqli_connection();
			}
			return mysqli_query($conn, $query);
		}
	}

	function mysql_insert($table, $data, $conn = false){
		if (!$conn) {
			$conn = get_mysqli_connection();
		}
		$columns = array_keys($data);
		$placeholders = array_fill(0, count($columns), '?');
		$query = "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
		$GLOBALS["last_db_query"][] = mysql_return_final_query($query,$data);		
		return mysql_prepared_execute($query, array_values($data), $conn);
	}
	
	function mysql_insert_ignore($table, $data, $conn = false){
		if (!$conn) {
			$conn = get_mysqli_connection();
		}
		$columns = array_keys($data);
		$placeholders = array_fill(0, count($columns), '?');
		$query = "INSERT IGNORE INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
		$GLOBALS["last_db_query"][] = mysql_return_final_query($query,$data);		
		return mysql_prepared_execute($query, array_values($data), $conn);
	}	

	function mysql_update($table, $data, $where, $limit = null, $conn = false) {
		
		if(!$conn){
			$conn = get_mysqli_connection();
		}
		
		$set_columns = array_keys($data);
		$set_placeholders = array_map(fn($col) => "`$col` = ?", $set_columns);
		$set_clause = implode(", ", $set_placeholders);
		$where_columns = array_keys($where);
		$where_placeholders = array_map(fn($col) => "`$col` = ?", $where_columns);
		$where_clause = implode(" AND ", $where_placeholders);
		$query = "UPDATE `$table` SET $set_clause WHERE $where_clause";
		$GLOBALS["last_db_query"][] = mysql_return_final_query($query,$data);
		if ($limit !== null) {
			$query .= " LIMIT " . intval($limit);
		}
		$params = array_merge(array_values($data), array_values($where));
		return mysql_prepared_execute($query, $params, $conn);
	}

	function mysql_delete($table, $where, $limit = null, $conn = false) {
		if (!$conn) {
			$conn = get_mysqli_connection();
		}
		if(empty($where)){
			throw new Exception("mysql_delete requires a WHERE clause to prevent full table deletion.");
		}
		$where_columns = array_keys($where);
		$where_placeholders = array_map(fn($col) => "`$col` = ?", $where_columns);
		$where_clause = implode(" AND ", $where_placeholders);
		$query = "DELETE FROM `$table` WHERE $where_clause";
		if ($limit !== null) {
			$query .= " LIMIT " . intval($limit);
		}
		$params = array_values($where);
		return mysql_prepared_execute($query, $params, $conn);
	}

	function mysql_escape($string, $conn = false){
		if(!$conn){
			$conn = get_mysqli_connection();
		}
		return $conn->real_escape_string($string);
	}

	if(!(function_exists("mysql_fetch_array"))){
		function mysql_fetch_array($query_or_result, $params = [], $result_type = MYSQLI_BOTH, $conn = false){
			
			if (!$conn) {
				$conn = get_mysqli_connection();
			}
						
			if(is_string($query_or_result) && !empty($params)) {
				$GLOBALS["last_db_query"][] = mysql_return_final_query($query_or_result,$params);
				$result = mysql_prepared_execute($query_or_result, $params, $conn);
				if (!$result || !$result instanceof mysqli_result) {
					return [];
				}
				$query_result = $result;
			} elseif(is_string($query_or_result)) {
				$GLOBALS["last_db_query"][] = mysql_return_final_query($query_or_result,$params);
				try{
					$query_result = mysql_query($query_or_result,$conn);
				}catch(Exception $e){
					$query_result = false;
					error_log("YOU'VE TRYIED TO EXECUTE A SQL QUERY THAT RETURNS AN ERROR ".$e);
				}
				if (!$query_result){
					$GLOBALS["mysql_last_error"] = $conn->error;
					error_log("MYSQL ERROR: ".$conn->error);
					return [];
				}
			} elseif($query_or_result instanceof mysqli_result){
				$GLOBALS["last_db_result_obj"] = $query_or_result;
				$query_result = $query_or_result;
			} else {
				return [];
			}
			
			$return = [];
			
			try{
				while($row = mysqli_fetch_array($query_result, $result_type)) {
					$return[] = $row;
				}
			}catch(Exception $e){
				error_log("YOU'VE TRYIED TO EXECUTE A SQL QUERY THAT RETURNS AN ERROR ".$e);
				return false;
			}
			
			if ($query_result instanceof mysqli_result) {
				mysqli_free_result($query_result);
			}
			
			return $return;
		}
	}

	function prepare_mysql_date($date = null){
		if ($date == null) {
			return "CURRENT_DATE()";
		}
		$date = "$date " . date('H:i:s');
		return "STR_TO_DATE('$date', '%d/%m/%Y %H:%i:%s')";
	}

	function mysql_return_final_query($query, $params){
		$escaped_params = array_map(function($param) {
			if (is_null($param)) return 'NULL';
			if (is_string($param)) return "'" . addslashes($param) . "'";
			if (is_bool($param)) return $param ? '1' : '0';
			return $param;
		}, $params);

		foreach ($escaped_params as $param) {
			$query = preg_replace('/\?/', $param, $query, 1);
		}
		
		return $query;		
	}

	function mysql_is_there_any_mysql_connection($array_of_connections=null){
		if(!$array_of_connections){
			$array_of_connections = $GLOBALS["mysql_connections"];
		}
		
		if(!$array_of_connections){
			return false;
		}
		
		foreach ($array_of_connections as $value){
			if($value !== false){
				return true;
			}
		}
		return false;
	}

	$GLOBALS["mysql_connections"][0] = mysql_connect();	
	$GLOBALS["mysql_now"] = mysql_fetch_array("SELECT NOW();")[0][0];
?>

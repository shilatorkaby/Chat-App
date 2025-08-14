<?php
	
	define("a328763fe27bba","TRUE");
	
	#region start
	require_once("config.php");
						
	header("Content-Type: application/json; charset=utf-8");

	$data = $_GET["data"] ?? null;
	$globals["_GET_DATA"] = $data;

	#endregion start
	
	switch($data){
				
		case "get_chats":
			#region get_chats
			$username = $_POST["username"] ?? null;

			if(!$username){
				error_log("ERROR 547389478934729837493287649827634");
				echo json_encode(false);
				die();
			}
			
			$limit = $_POST["limit"] ?? "6";
			
			$query = "
				SELECT
					m.contact_id,
					m.msg_type,
					m.msg_body,
					m.msg_datetime,
					c.contact_name,
					c.profile_picture_url
				FROM messages m
				INNER JOIN (
					SELECT contact_id, MAX(msg_datetime) AS latest_msg
					FROM messages
					WHERE belongs_to_username = ?
					GROUP BY contact_id
				) latest
					ON m.contact_id = latest.contact_id AND m.msg_datetime = latest.latest_msg
				LEFT JOIN contacts c
					ON c.belongs_to_username = ? AND c.contact_id = m.contact_id
				WHERE m.belongs_to_username = ?
				ORDER BY m.msg_datetime DESC
				LIMIT $limit;
			";
			
			$results = mysql_fetch_array($query,[$username,$username,$username]);
			echo json_encode($results);
			die();
			
			#endregion get_chats
		break;
		
		case "get_msgs":
			#region get_msgs
			
			$username = $_POST["username"] ?? null;
			$contact_id = $_POST["contact_id"] ?? null;

			if(!$username){
				error_log("ERROR 4355408743987597759348098734985739745");
				echo json_encode(false);
				die();
			}
			
			if(!$contact_id){
				error_log("ERROR 43509743598567439865439786543874568743");
				echo json_encode(false);
				die();
			}
			
			if(isset($_POST["limit"])){
				if($_POST["limit"]=="null"){$_POST["limit"] = null;}
			}
						
			$limit = $_POST["limit"] ?? "6";
			
			$query = "SELECT * FROM messages WHERE `belongs_to_username` = ? AND `contact_id` = ? ORDER BY `msg_datetime` DESC LIMIT $limit;";
			
			$results = mysql_fetch_array($query,[$username,$contact_id]);
			echo json_encode($results);
			die();
			
			#endregion get_msgs
		break;
		
		case "get_new_msgs":
			#region get_msgs
			
			$username = $_POST["username"] ?? null;
			$contact_id = $_POST["contact_id"] ?? null;
			$last_id = ((int)$_POST["last_id"]) ?? null;

			if(!$last_id){
				error_log("ERROR 1049785978436553489267542384627363444");
				echo json_encode(false);
				die();
			}

			if(!$username){
				error_log("ERROR 34249837498327498327478374837498273974");
				echo json_encode(false);
				die();
			}
			
			if(!$contact_id){
				error_log("ERROR 34082374983279487398748392748725637861");
				echo json_encode(false);
				die();
			}
						
			$query = "SELECT * FROM messages WHERE `row_id` > ? AND `belongs_to_username` = ? AND `contact_id` = ? ORDER BY `msg_datetime` DESC;";
			$mysql_return_final_query = mysql_return_final_query($query,[$last_id,$username,$contact_id]);
			//basic_log_to_file($mysql_return_final_query);
			
			$results = mysql_fetch_array($query,[$last_id,$username,$contact_id]);
			echo json_encode($results);
			die();
			
			#endregion get_msgs
		break;
		
		case "get_contact_name_by_contact_id":
			#region get_contact_name_by_contact_id
			
			$username = $_POST["username"] ?? null;
			$contact_id = $_POST["contact_id"] ?? null;

			if(!$username){
				error_log("ERROR 34984723987463278648237648723648768326");
				echo json_encode(false);
				die();
			}
			
			if(!$contact_id){
				error_log("ERROR 10297830812753349873988467364764255871");
				echo json_encode(false);
				die();
			}
						
			$query = "SELECT `contact_name` FROM contacts WHERE `belongs_to_username` = ? AND `contact_id` = ? LIMIT 1;";
			
			$results = mysql_fetch_array($query,[$username,$contact_id]);
			echo json_encode($results);
			die();
			
			#endregion get_contact_name_by_contact_id
		break;
		
		case "get_profile_pic_by_contact_id":
			#region get_profile_pic_by_contact_id
			
			$username = $_POST["username"] ?? null;
			$contact_id = $_POST["contact_id"] ?? null;

			if(!$username){
				error_log("ERROR 39087443298764378263837276549873264643");
				echo json_encode(false);
				die();
			}
			
			if(!$contact_id){
				error_log("ERROR 543087432896723498673427896328658437256");
				echo json_encode(false);
				die();
			}
						
			$query = "SELECT profile_picture_url FROM contacts WHERE `belongs_to_username` = ? AND `contact_id` = ? LIMIT 1;";
			
			$results = mysql_fetch_array($query,[$username,$contact_id]);
			echo json_encode($results);
			die();
			
			#endregion get_profile_pic_by_contact_id
		break;
		
		case "send_wa_txt_msg":
			#region send_wa_txt_msg
			
			$msg = $_POST["msg"] ?? null;
			$contact_id = $_POST["contact_id"] ?? null;
			$username = $_POST["username"] ?? null;
		
			if(!$msg){
				error_log("ERROR 34097329087643298674938647892367364647");
				echo json_encode(false);
				die();
			}
		
			if(!$username){
				error_log("ERROR 35408437590347698007689068997689867866");
				echo json_encode(false);
				die();
			}
			
			if(!$contact_id){
				error_log("ERROR 1115439720378540937409-095479854768954");
				echo json_encode(false);
				die();
			}
			
			$my_contact_id_query = "SELECT `id` FROM users WHERE `username` = ?  LIMIT 1";
			$des_username_query = "SELECT `username` FROM users WHERE `id` = ?  LIMIT 1";
			
			$mysql_return_final_query1 = mysql_return_final_query($my_contact_id_query,[$username]);		
			$mysql_return_final_query2 = mysql_return_final_query($des_username_query,[$contact_id]);
			
			$my_contact_id = mysql_fetch_array($my_contact_id_query,[$username]);
			$des_username = mysql_fetch_array($des_username_query,[$contact_id]);
			
			$my_contact_id = $my_contact_id[0][0] ?? null;
			$des_username = $des_username[0][0] ?? null;
			
			if(!$my_contact_id || !$des_username){
				error_log("ERROR 203987923846793274683297649238745637826458726");
				error_log($mysql_return_final_query1);
				error_log($mysql_return_final_query2);
				echo json_encode(false);
				die();
			}
			
			$results1 = mysql_insert("messages",[
				"belongs_to_username" => $username,
				"contact_id" => $contact_id,
				"is_from_me" => 1,
				"msg_type" => "text",
				"msg_body" => $msg,
			]);
			
			$results2 = mysql_insert("messages",[
				"belongs_to_username" => $des_username,
				"contact_id" => $my_contact_id,
				"is_from_me" => 0,
				"msg_type" => "text",
				"msg_body" => $msg,
			]);

			if($results1["success"] && $results2["success"]){
				echo json_encode(true);
				die();
			}
			
			echo json_encode(false);
			
			#endregion send_wa_txt_msg
		break;			
	}
	
	include_all_plugins("api.php");
	die();
?>
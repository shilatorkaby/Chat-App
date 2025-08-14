<?php

if (!defined('a328763fe27bba')) {
	// config.php guards direct access; define the guard BEFORE including it.
	define('a328763fe27bba', 'TRUE');
}

require_once __DIR__ . '/config.php';

if (!defined('APP_INIT_FILE_FIRED')) {
	require_once __DIR__ . '/app_init.php';
}

require_once __DIR__ . '/app_init.php';

header("Content-Type: application/json; charset=utf-8");

// We now check for the action in POST data, as per client-side.
$action = $_POST['action'] ?? null;

if ($action) {
	// Only include auth_api.php for auth-related actions
	$auth_actions = ['login', 'otp_verify', 'logout'];
	if (in_array($action, $auth_actions)) {
		require_once __DIR__ . '/auth_api.php';
	} else {
		// Handle other API actions here
		json_out(["ok" => false, "error" => "unknown_action"]);
	}
} else {
	json_out(["ok" => false, "error" => "no_action_specified"]);
}

$data = $_GET["data"] ?? null;

// Build DB connection for token validation and (optional) username resolution
$db = new mysqli(
	MYSQL_SERVERNAME,
	MYSQL_USERNAME,
	MYSQL_PASSWORD,
	MYSQL_DATABASE
);
if ($db->connect_errno) {
	http_response_code(500);
	echo json_encode(["ok" => false, "error" => "db_connect"]);
	exit;
}
$db->set_charset('utf8mb4');

// ============== TOKEN ENFORCEMENT + USERNAME OVERRIDE ======================
/**
 * SECURITY IMPROVEMENT:
 * Force the effective username to be the one tied to the token.
 * This prevents a client from supplying someone else’s username.
 * It does not change the API shape (the same parameters are present),
 * only ensures they refer to the authenticated user.
 */

$token = $_POST['token'] ?? '';


list($authed, $auth_user_id) = require_auth_token($db, $token);
if (!$authed) {
	http_response_code(401);
	echo json_encode(["ok" => false, "error" => "auth"]);
	exit;
}

// Resolve the username for this token
$st = $db->prepare("SELECT username FROM users WHERE id=? LIMIT 1");
$st->bind_param("i", $auth_user_id);
$st->execute();
$token_username = ($st->get_result()->fetch_row()[0]) ?? null;

if (!$token_username) {
	http_response_code(401);
	echo json_encode(["ok" => false, "error" => "auth_user_missing"]);
	exit;
}

$_POST['username'] = $token_username;

#endregion start
switch ($data) {

	case "get_chats":
		#region get_chats
		$username = $_POST["username"] ?? null;
		if (!$username) {
			error_log("ERROR 547389478934729837493287649827634");
			echo json_encode(false);
			die(); // exit
		}

		// Cast to int and require >0; else fallback to 6 (no upper cap).
		$limitSql = isset($_POST['limit'])
			? ($_POST['limit'] === 'null' ? '' : ' LIMIT ' . (((int)$_POST['limit'] > 0) ? (int)$_POST['limit'] : 6))
			: ' LIMIT 6';

		// ^ instead of $limit = $_POST["limit"] ?? "6"; that accepts any set value 

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

		$results = mysql_fetch_array($query, [$username, $username, $username]);
		echo json_encode($results);
		die();

		#endregion get_chats
		break;

	case "get_msgs":
		#region get_msgs

		$username = $_POST["username"] ?? null;
		$contact_id = $_POST["contact_id"] ?? null;

		if (!$username) {
			error_log("ERROR 4355408743987597759348098734985739745");
			echo json_encode(false);
			die();
		}

		if (!$contact_id) {
			error_log("ERROR 43509743598567439865439786543874568743");
			echo json_encode(false);
			die();
		}

		if (isset($_POST["limit"])) {
			if ($_POST["limit"] == "null") {
				$_POST["limit"] = null;
			}
		}

		$limit = $_POST["limit"] ?? "6"; 

		// exclude deleted rows
		$query = "SELECT * FROM messages
          WHERE `belongs_to_username` = ?
            AND `contact_id` = ?
            AND (`msg_type` IS NULL OR `msg_type` <> 'deleted')
          ORDER BY `msg_datetime` DESC
          LIMIT $limit;";
		$results = mysql_fetch_array($query, [$username, $contact_id]);
		echo json_encode($results);
		die();

		#endregion get_msgs
		break;

	case "get_new_msgs":
		#region get_msgs

		$username = $_POST["username"] ?? null;
		$contact_id = $_POST["contact_id"] ?? null;
		$last_id = ((int)$_POST["last_id"]) ?? null;

		if (!$last_id) {
			error_log("ERROR 1049785978436553489267542384627363444");
			echo json_encode(false);
			die();
		}

		if (!$username) {
			error_log("ERROR 34249837498327498327478374837498273974");
			echo json_encode(false);
			die();
		}

		if (!$contact_id) {
			error_log("ERROR 34082374983279487398748392748725637861");
			echo json_encode(false);
			die();
		}

		$query = "SELECT * FROM messages WHERE `row_id` > ? AND `belongs_to_username` = ? AND `contact_id` = ? ORDER BY `msg_datetime` DESC;";
		$mysql_return_final_query = mysql_return_final_query($query, [$last_id, $username, $contact_id]);
		//basic_log_to_file($mysql_return_final_query);
		$results = mysql_fetch_array($query, [$last_id, $username, $contact_id]);

		echo json_encode($results);
		die();

		#endregion get_msgs
		break;

	case "get_contact_name_by_contact_id":
		#region get_contact_name_by_contact_id

		$username = $_POST["username"] ?? null;
		$contact_id = $_POST["contact_id"] ?? null;

		if (!$username) {
			error_log("ERROR 34984723987463278648237648723648768326");
			echo json_encode(false);
			die();
		}

		if (!$contact_id) {
			error_log("ERROR 10297830812753349873988467364764255871");
			echo json_encode(false);
			die();
		}

		$query = "SELECT `contact_name` FROM contacts WHERE `belongs_to_username` = ? AND `contact_id` = ? LIMIT 1;";
		$results = mysql_fetch_array($query, [$username, $contact_id]);

		echo json_encode($results);
		die();

		#endregion get_contact_name_by_contact_id
		break;

	case "get_profile_pic_by_contact_id":
		#region get_profile_pic_by_contact_id

		$username = $_POST["username"] ?? null;
		$contact_id = $_POST["contact_id"] ?? null;

		if (!$username) {
			error_log("ERROR 39087443298764378263837276549873264643");
			echo json_encode(false);
			die();
		}

		if (!$contact_id) {
			error_log("ERROR 543087432896723498673427896328658437256");
			echo json_encode(false);
			die();
		}

		$query = "SELECT profile_picture_url FROM contacts WHERE `belongs_to_username` = ? AND `contact_id` = ? LIMIT 1;";

		$results = mysql_fetch_array($query, [$username, $contact_id]);
		echo json_encode($results);
		die();

		#endregion get_profile_pic_by_contact_id
		break;

	case "send_wa_txt_msg":
		#region send_wa_txt_msg

		$msg = $_POST["msg"] ?? null;
		$contact_id = $_POST["contact_id"] ?? null;
		$username = $_POST["username"] ?? null;

		if (!$msg) {
			error_log("ERROR 34097329087643298674938647892367364647");
			echo json_encode(false);
			die();
		}

		if (!$username) {
			error_log("ERROR 35408437590347698007689068997689867866");
			echo json_encode(false);
			die();
		}

		if (!$contact_id) {
			error_log("ERROR 1115439720378540937409-095479854768954");
			echo json_encode(false);
			die();
		}

		$my_contact_id_query = "SELECT `id` FROM users WHERE `username` = ?  LIMIT 1";
		$des_username_query = "SELECT `username` FROM users WHERE `id` = ?  LIMIT 1";

		$mysql_return_final_query1 = mysql_return_final_query($my_contact_id_query, [$username]);
		$mysql_return_final_query2 = mysql_return_final_query($des_username_query, [$contact_id]);

		$my_contact_id = mysql_fetch_array($my_contact_id_query, [$username]);
		$des_username = mysql_fetch_array($des_username_query, [$contact_id]);

		$my_contact_id = $my_contact_id[0][0] ?? null;
		$des_username = $des_username[0][0] ?? null;

		if (!$my_contact_id || !$des_username) {
			error_log("ERROR 203987923846793274683297649238745637826458726");
			error_log($mysql_return_final_query1);
			error_log($mysql_return_final_query2);
			echo json_encode(false);
			die();
		}

		$results1 = mysql_insert("messages", [
			"belongs_to_username" => $username,
			"contact_id" => $contact_id,
			"is_from_me" => 1,
			"msg_type" => "text",
			"msg_body" => $msg,
		]);

		$results2 = mysql_insert("messages", [
			"belongs_to_username" => $des_username,
			"contact_id" => $my_contact_id,
			"is_from_me" => 0,
			"msg_type" => "text",
			"msg_body" => $msg,
		]);

		if ($results1["success"] && $results2["success"]) {
			echo json_encode(true);
			die();
		}

		echo json_encode(false);

		#endregion send_wa_txt_msg
		break;


	case "delete_message":
		#region delete_message (delete-for-everyone, soft delete)

		// Enforced earlier: $_POST['username'] is already overridden from the token
		$username   = $_POST["username"] ?? null;
		$message_id = isset($_POST["message_id"]) ? (int)$_POST["message_id"] : 0;

		if (!$username || $message_id <= 0) {
			echo json_encode(["ok" => false, "error" => "bad_params"]);
			die();
		}

		// 1) Fetch the message row we want to delete (must belong to the authed user)
		$fetchSql = "SELECT id, belongs_to_username, contact_id, is_from_me, msg_body, msg_datetime
		             FROM messages WHERE id = ? AND belongs_to_username = ? LIMIT 1;";
		$stmt = $db->prepare($fetchSql);
		$stmt->bind_param("is", $message_id, $username);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();

		if (!$row) {
			echo json_encode(["ok" => false, "error" => "not_found"]);
			die();
		}

		// 2) Soft-delete this row
		$updSql = "UPDATE messages SET msg_type='deleted', msg_body=NULL WHERE id=? LIMIT 1;";
		$stmt = $db->prepare($updSql);
		$stmt->bind_param("i", $message_id);
		$stmt->execute();
		$affectedA = $stmt->affected_rows;

		// 3) Delete the mirrored row for the other participant.
		//    We look up the "other username" from users.id = contact_id,
		//    and our own id from users.username = $username,
		//    then match by text and near-equal timestamp (±2 seconds).
		$otherUsername = null;
		$myId = null;

		// Resolve other username
		$q1 = $db->prepare("SELECT username FROM users WHERE id=? LIMIT 1;");
		$q1->bind_param("i", $row['contact_id']);
		$q1->execute();
		if ($r1 = $q1->get_result()->fetch_assoc()) $otherUsername = $r1['username'];

		// Resolve my ID
		$q2 = $db->prepare("SELECT id FROM users WHERE username=? LIMIT 1;");
		$q2->bind_param("s", $username);
		$q2->execute();
		if ($r2 = $q2->get_result()->fetch_assoc()) $myId = (int)$r2['id'];

		$affectedB = 0;
		if ($otherUsername && $myId && !is_null($row['msg_body'])) {
			// Try to find the "other side" copy and mark it deleted as well
			$mirrorSql = "
				UPDATE messages
				SET msg_type='deleted', msg_body=NULL
				WHERE belongs_to_username=?    -- other participant owns the mirror row
				  AND contact_id=?             -- contact points back to me
				  AND msg_type <> 'deleted'
				  AND msg_body = ?             -- same text
				  AND ABS(TIMESTAMPDIFF(SECOND, msg_datetime, ?)) <= 2
				LIMIT 1;
			";
			$stmt = $db->prepare($mirrorSql);
			$stmt->bind_param("siss", $otherUsername, $myId, $row['msg_body'], $row['msg_datetime']);
			$stmt->execute();
			$affectedB = $stmt->affected_rows;
		}

		echo json_encode(["ok" => ($affectedA > 0), "deleted_here" => $affectedA, "deleted_there" => $affectedB]);
		die();

		#endregion delete_message
		break;
}

// Fallthrough: unknown action
echo json_encode(["ok" => false, "error" => "unknown_action"]);


include_all_plugins("api.php");
die();

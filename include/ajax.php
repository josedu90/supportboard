<?php
/*
 * ======================================
 * SUPPORT BOARD - AJAX FUNCTIONS FILE
 * ======================================
 *
 * These functions are called via Javascript from main.js file
 */

include_once("functions.php");

if (isset($_POST['action']) && $_POST['action'] != "" && strpos($_POST['action'],"sb_") === 0) {
    $sb_function = $_POST['action'];
    $_POST['action'] = null;
    if (function_exists($sb_function)) $sb_function();
}

function sb_ajax_add_message() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    global $sb_config;
    global $users_arr;
    global $agents_arr;
    $environment = "wp";
    if ($_POST['environment'] == "php") $environment = "php";

    if (!isset($sb_config)) {
        $sb_config = json_decode(str_replace('\\"','"', sb_get_option("sb-settings")), true);
    }

    $user_type = "user";
    if (isset($_POST['user_type']) && $_POST['user_type'] == "agent") $user_type = "agent";

    $costumer_id = "";
    if (!isset($_POST['costumer_id']) && isset($_SESSION['sb-user-infos'])) {
        $costumer_id = $_SESSION['sb-user-infos']['id'];
    } else {
        if (isset($_POST['costumer_id'])) {
            $costumer_id = $_POST['costumer_id'];
        }
    }

    $arr_conversation = sb_add_message($costumer_id, $_POST['msg'], $_POST['time'], $_POST['user_id'], $_POST['user_img'], $_POST['user_name'], $_POST['files']);

    //Email notification - Agents
    $agents_arr = json_decode(str_replace('\\"','"', sb_get_option("sb-agents-arr")), true);
    if (sb_get($sb_config,"notify-agent-email") && !isset($_SESSION['sb-activity-email'])) {
        if (!isset($agents_arr)) {
            $agents_arr = json_decode(str_replace('\\"','"', sb_get_option("sb-agents-arr")), true);
        }
        if ($user_type == "user") {
            $emails = sb_get_emails($_POST['user_name'], $_POST['msg'], sb_get_files_arr($_POST['files']), false, "agent", $environment);
            $agent_id = "";
            if (sb_check_email_allowed('agent' . $costumer_id)) {
                for ($i = count($arr_conversation) - 1; $i > 0; $i--) {
                    $id = $arr_conversation[$i]["user_id"];
                    if ($id != $costumer_id && $id != "10000") {
                        $agent_id = $arr_conversation[$i]["user_id"];
                        break;
                    }
                }
                $agents_emails = "";
                for ($i = 0; $i < count($agents_arr); $i++)  {
                    if ($agent_id != "" && $agents_arr[$i]["id"] == $agent_id) {
                        $agents_emails = $agents_arr[$i]["email"];
                        $agents_arr[$i]["last_email"] = time();
                    }
                    if ($agent_id == "") $agents_emails .= $agents_arr[$i]["email"] . ",";
                }
                if ($agent_id == "" && $agents_emails != "") {
                    $agents_emails = substr($agents_emails,0,strlen($agents_emails) - 1);
                }
                $_SESSION['sb-temp'] = array($agents_emails, $emails[2], $emails[3]);
                $_SESSION['sb-activity-email'] = "yes";
            }
        }
    }

    //Dialogflow
    $is_bot = false;
    $item_bot = null;
    if ($user_type == "user") {
        if (sb_get($sb_config,"bot-active")) {
            $response = sb_bot_message($_POST['msg'], $costumer_id, $_POST['sb_lang']);
            $msg_bot = sb_read_bot_response($response);
            if (isset($msg_bot["success"]) && $msg_bot["success"]) {
                $bot = sb_get_agent_bot();
                $item_bot = array("msg" => sb_parse_message($msg_bot["msg"]), "files" => $msg_bot["files"], "time" => $_POST['time'], "user_id" => "10000", "user_img" => $bot["img"], "user_name" => $bot["username"]);
                sb_add_message($costumer_id, $item_bot["msg"], $_POST['time'], "10000", $bot["img"], $bot["username"], $msg_bot["files"]);
                $is_bot = true;
            }
        }
    }

    //Email notification - Users
    if (sb_get($sb_config, "notify-user-email") && !isset($_SESSION['sb-activity-email'])) {
        if (!isset($users_arr)) {
            if (sb_get($sb_config, "users-engine") == "wp") {
                $users_arr = sb_get_wp_users();
            } else {
                $users_arr = json_decode(str_replace('\\"','"', sb_get_option("sb-users-arr")), true);
            }
        }
        if ($user_type == "agent") {
            if ($users_arr != false) {
                if (sb_check_email_allowed($costumer_id)) {
                    for ($i = 0; $i < count($users_arr); $i++) {
                        if ($users_arr[$i]["id"] == $costumer_id) {
                            if (isset($users_arr[$i]["email"])) {
                                $emails = sb_get_emails("Support", $_POST['msg'], sb_get_files_arr($_POST['files']), false, "user", $environment);
                                sb_send_email($users_arr[$i]["email"], $emails[0], $emails[1]);
                                $_SESSION['sb-activity-email'] = "yes";
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    if ($is_bot) {
        die(json_encode(array("success-bot", $item_bot), JSON_UNESCAPED_UNICODE));
    } else {
        die(json_encode(array("success", "")));
    }
}
function sb_ajax_bot_message() {
    $response = sb_bot_message($_POST['msg'], $_POST['user_id'], $_POST['sb_lang']);
    $msg_bot = sb_read_bot_response($response);
    if ($msg_bot["success"]) {
        $bot = sb_get_agent_bot();
        sb_add_message($_POST['user_id'], $msg_bot["msg"], $_POST['time'], "10000", $bot["img"], $bot["username"], $msg_bot["files"]);
    }
    die($msg_bot["success"]);
}
function sb_ajax_read_messages() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = "";
    if (isset($_POST["user_id"])) $user_id = $_POST["user_id"];
    if ($user_id == "" && isset($_SESSION['sb-user-infos'])) $user_id = $_SESSION['sb-user-infos']['id'];
    if ($user_id != "") {
        $arr_conversation = sb_get_option("sb-conversation-" . $user_id);
        if ($arr_conversation != false) {
            die(stripslashes($arr_conversation));
        }
        die("");
    }
    die("error");
}
function sb_ajax_slack_send_message() {
    //{"ok":true,"access_token":"xoxp-220847440245-220642241523-220782015812-318fbb518374e4d11eb7503fac2a24572","scope":"identify,incoming-webhook,chat:write:user,identity.basic","user_id":"U6GHGW73FD","team_name":"Test","team_id":"T6G55XCY77","incoming_webhook":{"channel":"#general","channel_id":"C6GM1656E","configuration_url":"https:\/\/schiocco.slack.com\/services\/B6GJI8B7","url":"https:\/\/hooks.slack.com\/services\/B6GJI8B7\/B6GJI8B7\/B6GJI8B7"}}
    global $sb_config;
    global $sb_slack_channels_arr;
    $username = "Test User";
    $user_id = "test-user-1";
    $msg = $_POST["msg"];
    $user_img = "https://board.support/wp-content/plugins/supportboard/media/icon-sb.png";
    $is_bot = false;
    if (!isset($sb_config)) {
        $sb_config = json_decode(str_replace('\\"','"', sb_get_option("sb-settings")), true);
    }
    if (isset($_POST["user_name"])) $username = $_POST["user_name"];
    if (isset($_POST["user_id"])) $user_id = $_POST["user_id"];
    if (isset($_POST["user_img"])) $user_img = $_POST["user_img"];
    if (isset($_POST["is_bot"]) && $_POST["is_bot"] == "true") {
        $is_bot = true;
    }
    $token = $sb_config["slack-token"];
    $channel_id = $sb_config["slack-channel"];
    $result = "slack-not-active";

    if (sb_get($sb_config,"slack-active") && isset($token)) {
        //Channel
        $newChannel = true;
        if ($user_id == "test-user-1") {
            $channel_id = $sb_config["slack-channel"];
            $newChannel = false;
        } else {
            if (!isset($sb_slack_channels_arr)) {
                $sb_slack_channels_arr = json_decode(sb_get_option("sb-slack-channels"), true);
                if (isset($sb_slack_channels_arr)) {
                    if (isset($sb_slack_channels_arr[$user_id]["channel_id"]) && $user_id != "test-user-1") {
                        $channel_id = $sb_slack_channels_arr[$user_id]["channel_id"];
                        $newChannel = false;
                    }
                } else {
                    $sb_slack_channels_arr = array();
                }
            }
        }
        if ($newChannel && !$is_bot) {
            $raw = sb_run_curl("https://slack.com/api/channels.create", array("token" => $token, "name" => $username));
            $arr = json_decode($raw, true);
            if (isset($arr["ok"])) {
                if ($arr["ok"] == "true") {
                    $channel_id = $arr["channel"]["id"];
                    $sb_slack_channels_arr[$user_id] = array("channel_id" => $channel_id,"channel_name" => $arr["channel"]["name"]);
                    sb_update_option("sb-slack-channels",json_encode($sb_slack_channels_arr));
                } else {
                    if ($arr["error"] == "name_taken") {
                        $channel_name = str_replace(" ","-", strtolower($username));
                        $raw = sb_run_curl("https://slack.com/api/channels.list", array("token" => $token, "exclude_archived" => true, "exclude_members" => true));
                        $arr = json_decode($raw, true);
                        if (isset($arr["channels"])) {
                            foreach ($arr["channels"] as $value) {
                                if ($value["name"] == $channel_name) {
                                    $channel_id = $value["id"];
                                    $sb_slack_channels_arr[$user_id] = array("channel_id" => $channel_id,"channel_name" => $value["name"]);
                                    sb_update_option("sb-slack-channels",json_encode($sb_slack_channels_arr));
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        //Message
        $msg = str_replace("&#39;","'",$msg);

        $data = array(
            "token" => $token,
            "channel" => $channel_id,
            "text" => $msg,
            "username" => $username,
            "bot_id" => "support-board",
            "icon_url" => $user_img,
            "as_user" => false
        );

        //Rich messages
        if (strpos($msg,"{") === 0) {
            try {
                $tmp = json_decode($msg, true);
                if ($tmp != false) {
                    $tmp_msg = "";
                    for ($i = 0; $i < count($tmp["replies"]); $i++)  {
                        $tmp_msg .= "\n" . ($i + 1) . ". " . $tmp["replies"][$i];
                    }
                    $data["attachments"] = '[{"title": "","pretext": "*' . $tmp["title"] . '*","text": "' . $tmp["text"] . ' ' . $tmp_msg . '","mrkdwn_in": ["text","pretext"]}]';
                }
                $data["text"] = "";
            }  catch (Exception $e) {  }
        }

        //Attachments - www.link.com|Title 1?www.link.com|Title 2 ...
        if (isset($_POST["files"]) && $_POST["files"] != "") {
            $arr = explode("?",$_POST["files"]);
            $json = "[";
            for ($i = 0; $i < count($arr); $i++) {
                $sub = explode("|", $arr[$i]);
                $json .= '{"title": "' . $sub[1] . '","title_link": "' .  $sub[0] . '"},';
            }
            $json = substr($json,0, strlen($json) - 1);
            $json .= "]";
            $data["attachments"] = $json;
        }

        $result = sb_run_curl("https://slack.com/api/chat.postMessage", $data);
    }
    die($result);
}
function sb_send_async_email() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['sb-temp'])) {
        try {
            $session_arr = $_SESSION['sb-temp'];
            $_SESSION['sb-temp'] = null;
            sb_send_email($session_arr[0], $session_arr[1], $session_arr[2]);
        } catch (Exception $exception) { die("error"); }
    }
    die("success");
}
function sb_send_test_email() {
    $emails = sb_get_emails("Test", "This is a lorem ipsum message for the test email.", array());
    sb_send_email($_POST['email'], $emails[0], $emails[1]);
    die("success");
}
function sb_ajax_init_user() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['sb-user-infos'])) {
        if (isset($_POST["user_name"]) && isset($_POST["user_id"]) && isset($_POST["user_img"])){
            $email = "";
            if ($_POST["user_email"]) $email = $_POST["user_email"];
            $_SESSION['sb-user-infos'] = array("id" => $_POST["user_id"], "img" => $_POST["user_img"], "username" => $_POST["user_name"], "email" => $email);
        } else {
            die("error");
        }
    } else {
        die(json_encode($_SESSION['sb-user-infos']));
    }
    die("success");
}
function sb_ajax_save_option() {
    $result = update_option($_POST['option_name'], $_POST['content']);
    die($result);
}
function sb_ajax_update_option() {
    $result = sb_update_option($_POST['option_name'], $_POST['content']);
    die($result);
}
function sb_ajax_login() {
    session_start();
    $users_arr = json_decode(str_replace('\\"','"', get_option("sb-users-arr")),true);
    if ($users_arr != false) {
        for ($i = 0; $i < count($users_arr); $i++){
            if ($users_arr[$i]["username"] == $_POST['user'] || $users_arr[$i]["email"] == $_POST['user']) {
                if (password_verify($_POST['password'],$users_arr[$i]["psw"]) || $users_arr[$i]["psw"] == $_POST['password']) {
                    $_SESSION['sb-login'] = encryptor("encrypt", "sb-logged-in-" . rand());
                    $_SESSION['sb-user-infos'] = $users_arr[$i];
                    die("success");
                }
            }
        }
    }
    die("error");
}
function sb_ajax_logout() {
    session_start();
    global $sb_config;
    session_unset();
    if (sb_get($sb_config,"users-engine") == "wp") {
        $root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($root . "/wp-load.php");
        require_once($root . "/wp-includes/pluggable.php");
        wp_logout();
    }
    die("success");
}
function sb_ajax_register() {
    $img = "";
    $email = "";
    $extra1 = "";
    $extra2 = "";
    $extra3 = "";
    $extra4 = "";
    if(isset($_POST['img'])) htmlspecialchars($img = $_POST['img']);
    if(isset($_POST['email'])) $email = htmlspecialchars($_POST['email']);
    if(isset($_POST['extra1'])) $extra1 = htmlspecialchars($_POST['extra1']);
    if(isset($_POST['extra2'])) $extra2 = htmlspecialchars($_POST['extra2']);
    if(isset($_POST['extra3'])) $extra3 = htmlspecialchars($_POST['extra3']);
    if(isset($_POST['extra4'])) $extra4 = htmlspecialchars($_POST['extra4']);
    $result = sb_register_user($_POST['id'], $img, $_POST['username'], $_POST['psw'], $email, $extra1, $extra2, $extra3, $extra4);
    die($result);
}
function sb_register_user($id="", $img="", $username="", $psw="", $email="", $extra1="", $extra2="", $extra3="", $extra4="") {
    global $sb_config;
    $users_arr = json_decode(str_replace('\\"','"', get_option("sb-users-arr")), true);
    if ($users_arr != false) {
        for ($i = 0; $i < count($users_arr); $i++){
            if ($users_arr[$i]["username"] == $username) {
                return "error-user-double";
            }
        }
    } else {
        $users_arr = array();
    }
    if ($img == "") {
        $img = SB_PLUGIN_URL . "/media/user-2.jpg";
    }
    $user = array("id" => $id, "img" => $img, "username" => $username, "psw" => password_hash($psw, PASSWORD_DEFAULT), "email" => $email);

    if (isset($sb_config)) {
        if ($sb_config["user-extra-1"] != "") $user["extra1"] = $extra1;
        if ($sb_config["user-extra-2"] != "") $user["extra2"] = $extra2;
        if ($sb_config["user-extra-3"] != "") $user["extra3"] = $extra3;
        if ($sb_config["user-extra-4"] != "") $user["extra4"] = $extra4;
    }
    $user["last-email"] = "-1";
    array_push($users_arr, $user);
    update_option("sb-users-arr",json_encode($users_arr));
    return "success";
}
function sb_ajax_update_user() {
    if (isset($_POST['id'])) {
        $username = "";
        $img = "";
        $email = "";
        $psw = "";
        $extra1 = "";
        $extra2 = "";
        $extra3 = "";
        $extra4 = "";
        $last_email = "";
        if(isset($_POST['username'])) $email = $_POST['username'];
        if(isset($_POST['img'])) $email = $_POST['img'];
        if(isset($_POST['email'])) $email = $_POST['email'];
        if(isset($_POST['psw'])) $email = $_POST['psw'];
        if(isset($_POST['extra1'])) $extra1 = $_POST['extra1'];
        if(isset($_POST['extra2'])) $extra2 = $_POST['extra2'];
        if(isset($_POST['extra3'])) $extra3 = $_POST['extra3'];
        if(isset($_POST['extra4'])) $extra4 = $_POST['extra4'];
        if(isset($_POST['last_email'])) $last_email = $_POST['last_email'];
        $result = sb_update_user($_POST['id'], $img, $username, $psw, $email, $extra1, $extra2, $extra3, $extra4, $last_email);
        die($result);
    } else {
        die("error_no_user_id");
    }
}
function sb_ajax_update_message() {
    sb_update_message($_POST['user_id'], $_POST['index'], $_POST['msg_action'], $_POST['content']);
}
function sb_ajax_delete_conversation() {
    if (isset($_POST['costumer_id'])) {
        $option_name = "sb-conversation-" . $_POST['costumer_id'];
        sb_delete_option($option_name);
        delete_option($option_name);
        die("success");
    }
    die("error");
}
function sb_ajax_get_tickets() {
    global $sb_config;
    $users_arr = json_decode(str_replace('\\"','"', get_option("sb-users-arr")), true);
    if ($users_arr == false) $users_arr = array();
    if (sb_get($sb_config,"users-engine") == "wp") {
        $users_arr = array_merge($users_arr, sb_get_wp_users());
    }
    $tickets_arr = array();
    if ($users_arr != false) {
        for ($i = 0; $i < count($users_arr); $i++){
            $tickets_user = json_decode(str_replace('\\"','"', sb_get_option("sb-conversation-" . $users_arr[$i]["id"])), true);
            if ($tickets_user != false) {
                array_push($tickets_arr, array("id" => $users_arr[$i]["id"], "username" => $users_arr[$i]["username"], "img" =>  $users_arr[$i]["img"], "tickets" => $tickets_user[count($tickets_user) - 1]));
            }
        }
    }
    $tickets_arr = stripslashes(json_encode($tickets_arr,JSON_UNESCAPED_UNICODE));
    die($tickets_arr);
}
function sb_ajax_delete_all_tickets() {
    global $sb_config;
    $users_arr = json_decode(str_replace('\\"','"', get_option("sb-users-arr")), true);
    if ($users_arr == false) $users_arr = array();
    if (sb_get($sb_config,"users-engine") == "wp") {
        $root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
        require_once($root . "/wp-load.php");
        require_once($root . "/wp-includes/pluggable.php");
        $users = get_users();
        foreach ($users as $user) {
            array_push($users_arr,array("id" => $user->ID, "img" => get_avatar_url($user->ID), "username" => $user->user_login));
        }
    }
    if ($users_arr != false) {
        for ($i = 0; $i < count($users_arr); $i++){
            $option_name = "sb-conversation-" . $users_arr[$i]["id"];
            delete_option($option_name);
            sb_delete_option($option_name);
        }
    }
    die("success");
}
function sb_ajax_slack_get_users() {
    global $sb_config;
    if (isset($sb_config["slack-token"])) {
        $raw = sb_run_curl("https://slack.com/api/users.list", array("token" => $sb_config["slack-token"]));
        die($raw);
    }
    die("slack-not-active");
}
?>

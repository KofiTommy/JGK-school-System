<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if(!isset($_SESSION['USERID']) || trim((string)$_SESSION['USERID']) === ''){
    http_response_code(401);
    echo json_encode(array(
        'ok' => false,
        'message' => 'Session expired.'
    ));
    exit();
}

include("dbstring.php");
include_once("user-management-utils.php");

if(!$con){
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'message' => 'Database connection failed.'
    ));
    exit();
}

ensure_user_management_columns($con);
$currentScript = isset($_SERVER['HTTP_REFERER']) ? basename((string)parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH)) : '';
um_touch_current_user_activity($con, $currentScript);

$isAdmin = isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] === 'administrator';
$liveUsers = $isAdmin ? um_count_live_users($con, 5) : null;

echo json_encode(array(
    'ok' => true,
    'live_users' => $liveUsers,
    'touched_at' => date('c')
));
exit();
?>

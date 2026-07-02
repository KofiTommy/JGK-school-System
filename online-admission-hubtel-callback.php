<?php
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

header("Content-Type: application/json");

$rawBody = file_get_contents("php://input");
$payload = json_decode((string)$rawBody, true);
if(!is_array($payload)){
    http_response_code(400);
    echo json_encode(array("received" => false, "message" => "Invalid callback payload."));
    exit();
}

$data = isset($payload["data"]) && is_array($payload["data"]) ? $payload["data"] : array();
$reference = trim((string)(isset($data["clientReference"]) ? $data["clientReference"] : ""));
if($reference === ""){
    http_response_code(400);
    echo json_encode(array("received" => false, "message" => "Missing client reference."));
    exit();
}

$payment = online_admission_get_payment_by_reference($con, $reference);
if(!$payment){
    http_response_code(404);
    echo json_encode(array("received" => false, "message" => "Payment record not found."));
    exit();
}

$status = online_admission_hubtel_callback_status($data);
$admissionCode = trim((string)$payment["admissioncode"]);
if($status === "success" && $admissionCode === ""){
    $admissionCode = online_admission_generate_payment_code($con);
}

$callbackMobile = isset($data["phoneNumber"]) ? online_admission_normalize_mobile_money_number((string)$data["phoneNumber"]) : "";
if($callbackMobile === false || $callbackMobile === ""){
    $callbackMobile = trim((string)$payment["mobile"]);
}

$paidAt = $status === "success" ? date("Y-m-d H:i:s") : null;
online_admission_update_payment_record($con, $payment["paymentid"], array(
    "accesscode" => isset($data["paylinkId"]) ? (string)$data["paylinkId"] : (string)$payment["accesscode"],
    "gatewaytransactionid" => isset($data["paylinkId"]) ? (string)$data["paylinkId"] : (string)$payment["gatewaytransactionid"],
    "mobile" => $callbackMobile,
    "status" => $status,
    "gatewayresponse" => isset($payload["message"]) ? (string)$payload["message"] : (isset($data["status"]) ? (string)$data["status"] : ucfirst($status)),
    "admissioncode" => ($status === "success" && $admissionCode !== "") ? $admissionCode : null,
    "codeissuedat" => ($status === "success" && $admissionCode !== "") ? date("Y-m-d H:i:s") : null,
    "rawresponse" => (string)$rawBody,
    "paidat" => $paidAt,
    "verifiedat" => date("Y-m-d H:i:s")
));

http_response_code(200);
echo json_encode(array("received" => true));

<?php
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function paystack_webhook_response($statusCode, $message){
    http_response_code((int)$statusCode);
    header("Content-Type: text/plain; charset=UTF-8");
    echo (string)$message;
    exit();
}

$config = online_admission_paystack_config();
if(!online_admission_paystack_is_ready($config)){
    paystack_webhook_response(500, "PAYSTACK_NOT_CONFIGURED");
}

$rawPayload = file_get_contents("php://input");
if($rawPayload === false || trim((string)$rawPayload) === ""){
    paystack_webhook_response(400, "EMPTY_PAYLOAD");
}

$signature = isset($_SERVER["HTTP_X_PAYSTACK_SIGNATURE"]) ? (string)$_SERVER["HTTP_X_PAYSTACK_SIGNATURE"] : "";
if(!online_admission_paystack_signature_is_valid($config, $rawPayload, $signature)){
    paystack_webhook_response(401, "INVALID_SIGNATURE");
}

$payload = json_decode($rawPayload, true);
if(!is_array($payload)){
    paystack_webhook_response(400, "INVALID_JSON");
}

$event = trim((string)(isset($payload["event"]) ? $payload["event"] : ""));
$data = isset($payload["data"]) && is_array($payload["data"]) ? $payload["data"] : array();
if($event !== "charge.success"){
    paystack_webhook_response(200, "IGNORED");
}

$reference = trim((string)(isset($data["reference"]) ? $data["reference"] : ""));
if($reference === ""){
    paystack_webhook_response(200, "MISSING_REFERENCE");
}

$payment = online_admission_get_payment_by_reference($con, $reference);
if(!$payment){
    paystack_webhook_response(200, "UNKNOWN_REFERENCE");
}

$processed = online_admission_process_paystack_payment_result(
    $con,
    $payment,
    $data,
    $rawPayload,
    isset($payload["message"]) ? (string)$payload["message"] : ""
);

if(!empty($processed["integrity_failed"])){
    paystack_webhook_response(200, "INTEGRITY_FAILED");
}
if(isset($processed["stored_status"]) && (string)$processed["stored_status"] === "success"){
    paystack_webhook_response(200, "OK");
}

paystack_webhook_response(200, "IGNORED");
?>

<?php
session_start();
include("dbstring.php");
include_once("voting-utils.php");
include_once("online-admission-utils.php");
ensure_voting_tables($con);

function voting_callback_redirect($type, $message, $contestId = ""){
    $class = "vote-alert vote-alert--info";
    if($type === "success"){ $class = "vote-alert vote-alert--success"; }
    elseif($type === "error"){ $class = "vote-alert vote-alert--error"; }
    elseif($type === "warning"){ $class = "vote-alert vote-alert--warning"; }
    $_SESSION["ONLINE_VOTING_MESSAGE"] = "<div class=\"".$class."\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    $target = "online-voting.php".($contestId !== "" ? "?contest=".rawurlencode((string)$contestId) : "");
    header("location:".$target);
    exit();
}

$reference = trim((string)(isset($_GET["reference"]) ? $_GET["reference"] : ""));
if($reference === ""){
    voting_callback_redirect("warning", "Payment reference was not returned by Paystack.");
}

$payment = voting_get_payment_by_reference($con, $reference);
if(!$payment){
    voting_callback_redirect("error", "We could not match that payment attempt to a contest vote.");
}

$config = online_admission_paystack_config();
$config["callback_path"] = "online-voting-paystack-callback.php";
if(!online_admission_paystack_is_ready($config)){
    voting_callback_redirect("error", "Paystack is not configured yet. Please contact the school.", isset($payment["contestid"]) ? $payment["contestid"] : "");
}

$errorMessage = "";
$response = online_admission_paystack_verify($config, $reference, $errorMessage);
if($response === false || !isset($response["data"]) || !is_array($response["data"])){
    voting_update_payment_record($con, isset($payment["paymentid"]) ? $payment["paymentid"] : 0, array(
        "status" => "pending",
        "gatewayresponse" => $errorMessage !== "" ? $errorMessage : "Verification could not be completed.",
        "verifiedat" => date("Y-m-d H:i:s")
    ));
    voting_callback_redirect("warning", $errorMessage !== "" ? $errorMessage : "Payment verification could not be completed right now. Please check again shortly.", isset($payment["contestid"]) ? $payment["contestid"] : "");
}

$processed = voting_process_paystack_payment_result(
    $con,
    $payment,
    $response["data"],
    isset($response["_raw"]) ? (string)$response["_raw"] : "",
    isset($response["message"]) ? (string)$response["message"] : ""
);

$contestId = isset($payment["contestid"]) ? (string)$payment["contestid"] : "";
$candidateName = ($processed["candidate"] && isset($processed["candidate"]["candidatename"])) ? trim((string)$processed["candidate"]["candidatename"]) : "your selected contestant";
$voteQuantity = isset($processed["payment"]["votequantity"]) ? (int)$processed["payment"]["votequantity"] : 0;
$storedStatus = isset($processed["stored_status"]) ? (string)$processed["stored_status"] : "";

if($storedStatus === "success"){
    voting_callback_redirect("success", "Payment completed successfully. ".$voteQuantity." vote".($voteQuantity === 1 ? "" : "s")." have been added to ".$candidateName.".", $contestId);
}
if(!empty($processed["integrity_failed"])){
    voting_callback_redirect("warning", "Payment was received, but the returned details did not match the expected contest vote record. Please contact the school before trying again.", $contestId);
}
if($storedStatus === "abandoned"){
    voting_callback_redirect("warning", "Voting payment was not completed. You can return and try again when you are ready.", $contestId);
}
if($storedStatus === "failed"){
    voting_callback_redirect("warning", "Voting payment failed or was declined. Please try again.", $contestId);
}
voting_callback_redirect("warning", "Payment is still awaiting final confirmation. Please refresh the voting page shortly.", $contestId);

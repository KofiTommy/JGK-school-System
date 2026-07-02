<?php
session_start();
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function admission_payment_flash($type, $message){
    $class = "oa-alert oa-alert--info";
    if($type === "success"){ $class = "oa-alert oa-alert--success"; }
    elseif($type === "error"){ $class = "oa-alert oa-alert--error"; }
    elseif($type === "warning"){ $class = "oa-alert oa-alert--warning"; }
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = "<div class=\"".$class."\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
}

function admission_payment_redirect($type, $message){
    admission_payment_flash($type, $message);
    header("location:online-admission.php");
    exit();
}

$reference = trim((string)(isset($_GET["reference"]) ? $_GET["reference"] : ""));
if($reference === ""){
    admission_payment_redirect("warning", "Hubtel checkout was cancelled.");
}

$payment = online_admission_get_payment_by_reference($con, $reference);
if($payment){
    $_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$payment["postingid"];
    $_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$payment["admissionyear"];
    if(trim((string)$payment["mobile"]) !== ""){
        $_SESSION["ONLINE_ADMISSION_PAYMENT_MOBILE"] = (string)$payment["mobile"];
    }

    if(strtolower(trim((string)$payment["status"])) !== "success"){
        online_admission_update_payment_record($con, $payment["paymentid"], array(
            "status" => "abandoned",
            "gatewayresponse" => "Cancelled by customer before payment completion.",
            "verifiedat" => date("Y-m-d H:i:s")
        ));
    }
}

admission_payment_redirect("warning", "Hubtel checkout was cancelled before payment completion.");

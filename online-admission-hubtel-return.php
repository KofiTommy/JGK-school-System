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
    admission_payment_redirect("warning", "Payment reference was not returned by Hubtel.");
}

$payment = online_admission_get_payment_by_reference($con, $reference);
if(!$payment){
    admission_payment_redirect("error", "We could not match that payment attempt to an admission record.");
}

$_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$payment["postingid"];
$_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$payment["admissionyear"];
if(trim((string)$payment["mobile"]) !== ""){
    $_SESSION["ONLINE_ADMISSION_PAYMENT_MOBILE"] = (string)$payment["mobile"];
}

$status = strtolower(trim((string)$payment["status"]));
if($status === "success"){
    $admissionCode = trim((string)$payment["admissioncode"]);
    if($admissionCode === ""){
        $admissionCode = online_admission_generate_payment_code($con);
        online_admission_update_payment_record($con, $payment["paymentid"], array(
            "admissioncode" => $admissionCode,
            "codeissuedat" => date("Y-m-d H:i:s")
        ));
    }
    admission_payment_redirect("success", "Admission payment completed successfully. Your form code is ".$admissionCode.". Enter this exact code on the admission form.");
}
if($status === "abandoned"){
    admission_payment_redirect("warning", "Payment was cancelled before completion. You can start another Hubtel payment when ready.");
}
if($status === "failed"){
    admission_payment_redirect("warning", "Payment failed or was declined. Please try again or contact the school if the issue continues.");
}
admission_payment_redirect("warning", "Hubtel checkout has returned, but final confirmation is still pending. Refresh this page shortly if your code is not showing yet.");

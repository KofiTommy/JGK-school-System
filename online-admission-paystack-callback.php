<?php
session_start();
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function admission_payment_redirect($type, $message){
    $class = "oa-alert oa-alert--info";
    if($type === "success"){ $class = "oa-alert oa-alert--success"; }
    elseif($type === "error"){ $class = "oa-alert oa-alert--error"; }
    elseif($type === "warning"){ $class = "oa-alert oa-alert--warning"; }
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = "<div class=\"".$class."\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    header("location:online-admission.php");
    exit();
}

$reference = trim((string)(isset($_GET["reference"]) ? $_GET["reference"] : ""));
if($reference === ""){
    admission_payment_redirect("warning", "Payment reference was not returned by Paystack.");
}

$payment = online_admission_get_payment_by_reference($con, $reference);
if(!$payment){
    admission_payment_redirect("error", "We could not match that payment attempt to an admission application.");
}

$config = online_admission_paystack_config();
if(!online_admission_paystack_is_ready($config)){
    admission_payment_redirect("error", "Paystack is not configured yet. Please contact the school.");
}

$errorMessage = "";
$response = online_admission_paystack_verify($config, $reference, $errorMessage);
if($response === false || !isset($response["data"]) || !is_array($response["data"])){
    online_admission_update_payment_record($con, $payment["paymentid"], array(
        "status" => "pending",
        "gatewayresponse" => $errorMessage !== "" ? $errorMessage : "Verification could not be completed.",
        "verifiedat" => date("Y-m-d H:i:s")
    ));
    admission_payment_redirect("warning", $errorMessage !== "" ? $errorMessage : "Payment verification could not be completed right now. Please refresh the page later.");
}

$processed = online_admission_process_paystack_payment_result(
    $con,
    $payment,
    $response["data"],
    isset($response["_raw"]) ? (string)$response["_raw"] : "",
    isset($response["message"]) ? (string)$response["message"] : ""
);
$application = $processed["application"];
$postedStudent = $processed["postedStudent"];
$storedStatus = $processed["stored_status"];
$integrityFailed = !empty($processed["integrity_failed"]);
$payment = $processed["payment"];

if($application){
    $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"] = (string)$application["applicationid"];
    $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] = "1";
}
if($postedStudent){
    $_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$postedStudent["postingid"];
    $_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$postedStudent["admissionyear"];
}else{
    $_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$payment["postingid"];
    $_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$payment["admissionyear"];
}

if($storedStatus === "success"){
    $smsNotice = "";
    if(trim((string)(isset($payment["studentsmssentat"]) ? $payment["studentsmssentat"] : "")) !== ""){
        if(trim((string)(isset($payment["studentsmsstatus"]) ? $payment["studentsmsstatus"] : "")) === "1000" || trim((string)(isset($payment["studentsmsstatus"]) ? $payment["studentsmsstatus"] : "")) === "SENT"){
            $smsNotice = " We have also sent the token to the candidate mobile number by SMS.";
        }
    }elseif(trim((string)(isset($payment["studentsmsstatus"]) ? $payment["studentsmsstatus"] : "")) !== "" && trim((string)$payment["studentsmsstatus"]) !== "ALREADY_SENT" && trim((string)$payment["studentsmsstatus"]) !== "NO_STUDENT_PHONE"){
        $smsNotice = " We could not send the SMS token right now, so please use the token shown here.";
    }
    unset($_SESSION["ONLINE_ADMISSION_POSTING_ID"], $_SESSION["ONLINE_ADMISSION_YEAR"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"], $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"]);
    $_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"] = "1";
    $message = "Admission payment completed successfully. Continue Admission is now available. Enter your BECE index number, date of birth, and verification token to open the form.";
    if($application && trim((string)$application["verificationtoken"]) !== ""){
        $message .= " Keep your verification token ".trim((string)$application["verificationtoken"])." safe.";
    }
    $message .= $smsNotice;
    admission_payment_redirect("success", $message);
}
if($storedStatus === "abandoned"){
    admission_payment_redirect("warning", "Payment was not completed. You can return and try again when you are ready.");
}
if($storedStatus === "failed"){
    admission_payment_redirect("warning", "Payment failed or was declined. Please try again or contact the school if the issue continues.");
}
if($integrityFailed){
    admission_payment_redirect("warning", "Payment was received, but the returned transaction details did not match the expected admission payment record. Please contact the school before trying again.");
}
admission_payment_redirect("warning", "Payment was received but is still awaiting final confirmation. Please check again shortly.");

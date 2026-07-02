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

$branchContext = online_admission_default_branch_context($con);
$branchId = $branchContext["branchid"];
if($branchId === ""){
    admission_payment_redirect("error", "The school branch configuration is not ready for online admission payment.");
}

if(
    !isset($_SESSION["ONLINE_ADMISSION_APPLICATION_ID"], $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"]) ||
    trim((string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) === "" ||
    (string)$_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] !== "1"
){
    admission_payment_redirect("warning", "Verify your posting before starting payment.");
}

$application = online_admission_get_application_by_id($con, (string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]);
if(!$application || (string)$application["branchid"] !== (string)$branchId){
    unset($_SESSION["ONLINE_ADMISSION_POSTING_ID"], $_SESSION["ONLINE_ADMISSION_YEAR"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"], $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"]);
    admission_payment_redirect("warning", "Please verify your posting again before continuing with payment.");
}

$postedStudent = online_admission_get_posted_student_by_id($con, $branchId, $application["postingid"]);
if(!$postedStudent){
    unset($_SESSION["ONLINE_ADMISSION_POSTING_ID"], $_SESSION["ONLINE_ADMISSION_YEAR"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"], $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"]);
    admission_payment_redirect("warning", "Please verify your posting again before continuing with payment.");
}

online_admission_attach_payments_to_application($con, $postedStudent["postingid"], $application["applicationid"]);
$_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$postedStudent["postingid"];
$_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$postedStudent["admissionyear"];

$paymentSetting = online_admission_get_payment_setting($con, $branchId);
if(!online_admission_portal_is_open($paymentSetting)){
    admission_payment_redirect("warning", "Online admission is currently closed by the school. Payment is not available right now.");
}
if(!online_admission_payment_open_for_student($postedStudent, $application, $paymentSetting)){
    admission_payment_redirect("warning", "This application is not open for payment yet.");
}

$successfulPayment = online_admission_get_successful_payment_by_application($con, $application["applicationid"]);
if(online_admission_payment_is_paid($successfulPayment)){
    $application = online_admission_ensure_application_token($con, $application);
    $existingCode = trim((string)$successfulPayment["admissioncode"]);
    if($existingCode === ""){
        $existingCode = online_admission_generate_payment_code($con);
        online_admission_update_payment_record($con, $successfulPayment["paymentid"], array(
            "admissioncode" => $existingCode,
            "codeissuedat" => date("Y-m-d H:i:s")
        ));
    }
    unset($_SESSION["ONLINE_ADMISSION_POSTING_ID"], $_SESSION["ONLINE_ADMISSION_YEAR"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"], $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"]);
    admission_payment_redirect("success", "Admission payment has already been completed. Log in again with your BECE index number, date of birth, and verification token to open the form.");
}

$config = online_admission_paystack_config();
if(!online_admission_paystack_is_ready($config)){
    admission_payment_redirect("error", "Paystack is not configured yet. Please contact the school.");
}

$amount = (float)$paymentSetting["feeamount"];
if($amount <= 0){
    admission_payment_redirect("warning", "The school has not set the admission fee amount yet.");
}

$reference = online_admission_payment_reference();
$studentName = trim($postedStudent["firstname"]." ".$postedStudent["othernames"]." ".$postedStudent["surname"]);
$continueUrl = online_admission_app_url("online-admission.php");
$callbackUrl = online_admission_payment_callback_url($config);
$paymentProfile = $application;
$payload = array(
    "reference" => $reference,
    "email" => online_admission_payment_customer_email($paymentProfile),
    "amount" => online_admission_money_minor_units($amount),
    "currency" => strtoupper(trim((string)$paymentSetting["currency"])) !== "" ? strtoupper(trim((string)$paymentSetting["currency"])) : "GHS",
    "callback_url" => $callbackUrl,
    "metadata" => array(
        "applicationid" => (string)$application["applicationid"],
        "postingid" => (string)$postedStudent["postingid"],
        "beceindexnumber" => (string)$postedStudent["beceindexnumber"],
        "admissionyear" => (string)$postedStudent["admissionyear"],
        "mobile" => (string)$postedStudent["mobile"],
        "cancel_action" => $continueUrl."?payment_cancel=1",
        "custom_fields" => array(
            array(
                "display_name" => "Student Name",
                "variable_name" => "student_name",
                "value" => $studentName
            ),
            array(
                "display_name" => "BECE Index Number",
                "variable_name" => "bece_index_number",
                "value" => (string)$postedStudent["beceindexnumber"]
            ),
            array(
                "display_name" => "Admission Year",
                "variable_name" => "admission_year",
                "value" => (string)$postedStudent["admissionyear"]
            )
        )
    )
);

$errorMessage = "";
$response = online_admission_paystack_initialize($config, $payload, $errorMessage);
if($response === false || !isset($response["data"]) || !is_array($response["data"]) || trim((string)(isset($response["data"]["authorization_url"]) ? $response["data"]["authorization_url"] : "")) === ""){
    $message = $errorMessage !== "" ? $errorMessage : "Paystack could not start this payment right now.";
    admission_payment_redirect("error", $message);
}

$data = $response["data"];
$saved = online_admission_create_payment_record($con, array(
    "applicationid" => (string)$application["applicationid"],
    "postingid" => (string)$postedStudent["postingid"],
    "beceindexnumber" => (string)$postedStudent["beceindexnumber"],
    "admissionyear" => (string)$postedStudent["admissionyear"],
    "branchid" => (string)$postedStudent["branchid"],
    "gateway" => "paystack",
    "reference" => $reference,
    "accesscode" => isset($data["access_code"]) ? (string)$data["access_code"] : "",
    "authorizationurl" => isset($data["authorization_url"]) ? (string)$data["authorization_url"] : "",
    "gatewaytransactionid" => "",
    "amount" => $amount,
    "currency" => strtoupper(trim((string)$paymentSetting["currency"])) !== "" ? strtoupper(trim((string)$paymentSetting["currency"])) : "GHS",
    "email" => online_admission_payment_customer_email($paymentProfile),
    "mobile" => (string)$postedStudent["mobile"],
    "status" => "initialized",
    "gatewayresponse" => isset($response["message"]) ? (string)$response["message"] : "Initialized",
    "rawresponse" => isset($response["_raw"]) ? (string)$response["_raw"] : ""
));

if($saved === false){
    admission_payment_redirect("error", "The payment session could not be recorded right now. Please try again.");
}

header("location:".(string)$data["authorization_url"]);
exit();

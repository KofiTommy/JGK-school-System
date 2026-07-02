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

if(!isset($_SESSION["ONLINE_ADMISSION_POSTING_ID"]) || trim((string)$_SESSION["ONLINE_ADMISSION_POSTING_ID"]) === ""){
    admission_payment_redirect("warning", "Verify your posting before starting payment.");
}

$postingIdEsc = mysqli_real_escape_string($con, (string)$_SESSION["ONLINE_ADMISSION_POSTING_ID"]);
$branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
$postedRes = mysqli_query($con, "SELECT * FROM tbladmissionpostedstudent WHERE postingid='$postingIdEsc' AND branchid='$branchIdEsc' AND status='active' LIMIT 1");
if(!$postedRes || !($postedStudent = mysqli_fetch_array($postedRes, MYSQLI_ASSOC))){
    unset($_SESSION["ONLINE_ADMISSION_POSTING_ID"], $_SESSION["ONLINE_ADMISSION_YEAR"]);
    admission_payment_redirect("warning", "Please verify your posting again before continuing with payment.");
}

$application = online_admission_get_application_by_posting($con, $postedStudent["postingid"]);
$paymentSetting = online_admission_get_payment_setting($con, $branchId);
if(!online_admission_payment_open_for_student($postedStudent, $application, $paymentSetting)){
    admission_payment_redirect("warning", "This application is not open for payment yet.");
}

$successfulPayment = online_admission_get_successful_payment_by_posting($con, $postedStudent["postingid"]);
if(online_admission_payment_is_paid($successfulPayment)){
    $existingCode = trim((string)$successfulPayment["admissioncode"]);
    if($existingCode === ""){
        $existingCode = online_admission_generate_payment_code($con);
        online_admission_update_payment_record($con, $successfulPayment["paymentid"], array(
            "admissioncode" => $existingCode,
            "codeissuedat" => date("Y-m-d H:i:s")
        ));
    }
    admission_payment_redirect("success", "Admission payment has already been completed. Use code ".$existingCode." on the form.");
}

$config = online_admission_hubtel_config();
if(!online_admission_hubtel_is_ready($config)){
    admission_payment_redirect("error", "Hubtel is not configured yet. Please contact the school.");
}

$amount = (float)$paymentSetting["feeamount"];
if($amount <= 0){
    admission_payment_redirect("warning", "The school has not set the admission fee amount yet.");
}

$rawMobile = trim((string)(isset($_POST["paymentmobile"]) ? $_POST["paymentmobile"] : ""));
if($rawMobile === "" && isset($_SESSION["ONLINE_ADMISSION_PAYMENT_MOBILE"])){
    $rawMobile = trim((string)$_SESSION["ONLINE_ADMISSION_PAYMENT_MOBILE"]);
}
if($rawMobile === "" && $application && trim((string)$application["mobile"]) !== ""){
    $rawMobile = trim((string)$application["mobile"]);
}
if($rawMobile === "" && trim((string)$postedStudent["mobile"]) !== ""){
    $rawMobile = trim((string)$postedStudent["mobile"]);
}
$_SESSION["ONLINE_ADMISSION_PAYMENT_MOBILE"] = $rawMobile;

$mobileNumber = online_admission_normalize_mobile_money_number($rawMobile);
if($mobileNumber === false || $mobileNumber === ""){
    admission_payment_redirect("warning", "Enter a valid Ghana Mobile Money number, for example 0241234567 or +233241234567.");
}

$reference = online_admission_payment_reference();
$studentName = trim($postedStudent["firstname"]." ".$postedStudent["othernames"]." ".$postedStudent["surname"]);
$paymentProfile = $application ? $application : array(
    "applicationid" => (string)$postedStudent["postingid"],
    "email" => "",
    "mobile" => $mobileNumber
);

$title = trim((string)$config["title"]);
if($title === ""){
    $title = trim((string)$branchContext["company"])." Admission Payment";
}

$description = trim((string)$config["description"]);
if($description === ""){
    $description = "Admission fee payment for ".$studentName;
}

$payload = array(
    "amount" => (float)number_format($amount, 2, ".", ""),
    "title" => $title,
    "description" => $description,
    "clientReference" => $reference,
    "callbackUrl" => online_admission_hubtel_callback_url($config),
    "cancellationUrl" => online_admission_hubtel_cancel_url($config, $reference),
    "returnUrl" => online_admission_hubtel_return_url($config, $reference)
);

if(trim((string)$config["logo_url"]) !== ""){
    $payload["logo"] = trim((string)$config["logo_url"]);
}

$errorMessage = "";
$response = online_admission_hubtel_request_money($config, $mobileNumber, $payload, $errorMessage);
if($response === false || !isset($response["data"]) || !is_array($response["data"]) || trim((string)(isset($response["data"]["paylinkUrl"]) ? $response["data"]["paylinkUrl"] : "")) === ""){
    $message = $errorMessage !== "" ? $errorMessage : "Hubtel could not start this payment right now.";
    admission_payment_redirect("error", $message);
}

$data = $response["data"];
$saved = online_admission_create_payment_record($con, array(
    "applicationid" => $application ? (string)$application["applicationid"] : "",
    "postingid" => (string)$postedStudent["postingid"],
    "beceindexnumber" => (string)$postedStudent["beceindexnumber"],
    "admissionyear" => (string)$postedStudent["admissionyear"],
    "branchid" => (string)$postedStudent["branchid"],
    "reference" => $reference,
    "accesscode" => isset($data["paylinkId"]) ? (string)$data["paylinkId"] : "",
    "authorizationurl" => isset($data["paylinkUrl"]) ? (string)$data["paylinkUrl"] : "",
    "gatewaytransactionid" => isset($data["paylinkId"]) ? (string)$data["paylinkId"] : "",
    "amount" => $amount,
    "currency" => strtoupper(trim((string)$paymentSetting["currency"])) !== "" ? strtoupper(trim((string)$paymentSetting["currency"])) : "GHS",
    "email" => online_admission_payment_customer_email($paymentProfile),
    "mobile" => $mobileNumber,
    "status" => "initialized",
    "gatewayresponse" => isset($response["message"]) ? (string)$response["message"] : "Initialized",
    "rawresponse" => isset($response["_raw"]) ? (string)$response["_raw"] : ""
));

if($saved === false){
    admission_payment_redirect("error", "The payment session could not be recorded right now. Please try again.");
}

header("location:".(string)$data["paylinkUrl"]);
exit();

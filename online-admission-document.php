<?php
session_start();
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function oa_document_redirect($message){
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = "<div class=\"oa-alert oa-alert--warning\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    header("location:online-admission.php");
    exit();
}

$documentId = trim((string)(isset($_GET["documentid"]) ? $_GET["documentid"] : ""));
if($documentId === ""){
    if(online_admission_is_admin()){
        header("location:online-admission-admin.php#admission-documents");
        exit();
    }
    oa_document_redirect("Select a valid admission document first.");
}

$document = online_admission_get_document_by_id($con, $documentId);
if(!$document){
    if(online_admission_is_admin()){
        header("location:online-admission-admin.php#admission-documents");
        exit();
    }
    oa_document_redirect("That admission document is no longer available.");
}

if(!online_admission_is_admin() && online_admission_document_is_admission_letter($document)){
    oa_document_redirect("Admission letters are printed and issued by the school office when students report. Other admission documents remain available on your portal.");
}

$allowed = false;
$application = null;
$postedStudent = null;
if(online_admission_is_admin()){
    $allowed = true;
}elseif(
    isset($_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) &&
    (string)$_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] === "1" &&
    trim((string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) !== ""
){
    $application = online_admission_get_application_by_id($con, (string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]);
    if($application &&
        (string)$application["branchid"] === (string)$document["branchid"] &&
        (string)$application["admissionyear"] === (string)$document["admissionyear"]){
        $paymentSetting = online_admission_get_payment_setting($con, (string)$application["branchid"]);
        $paymentEnabled = (int)$paymentSetting["enabled"] === 1 && (float)$paymentSetting["feeamount"] > 0;
        $successfulPayment = online_admission_get_successful_payment_by_application($con, (string)$application["applicationid"]);
        $allowed = online_admission_documents_unlocked($application, $successfulPayment, $paymentEnabled ? 1 : 0);
        if($allowed){
            $postedStudent = online_admission_get_posted_student_by_id($con, (string)$application["branchid"], (string)$application["postingid"]);
            $allowed = online_admission_document_matches_application($document, $application, $postedStudent);
            if($allowed && online_admission_document_random_enabled($document)){
                $allowed = online_admission_document_assigned_to_application($con, (string)$application["applicationid"], $document);
            }
        }
    }
}

if(!$allowed){
    oa_document_redirect("This document is only available for the correct admission record after you complete the required steps.");
}

$filePath = online_admission_document_file_path((string)$document["filename"]);
if($filePath === ""){
    if(online_admission_is_admin()){
        header("location:online-admission-admin.php?document_year=".rawurlencode((string)$document["admissionyear"])."#admission-documents");
        exit();
    }
    oa_document_redirect("The selected document file could not be found on the server.");
}

$downloadName = trim((string)$document["originalfilename"]);
if(trim((string)(isset($document["title"]) ? $document["title"] : "")) !== ""){
    $extension = strtolower(pathinfo($downloadName !== "" ? $downloadName : (string)$document["filename"], PATHINFO_EXTENSION));
    $safeTitle = preg_replace('/[^A-Za-z0-9]+/', '-', online_admission_document_display_title($document));
    $safeTitle = trim((string)$safeTitle, '-');
    if($safeTitle === ""){
        $safeTitle = "admission-document";
    }
    $downloadName = strtolower($safeTitle).($extension !== "" ? ".".$extension : "");
}elseif($downloadName === ""){
    $extension = strtolower(pathinfo((string)$document["filename"], PATHINFO_EXTENSION));
    $downloadName = strtolower(str_replace(" ", "-", online_admission_document_label((string)$document["doctype"])));
    if($extension !== ""){
        $downloadName .= ".".$extension;
    }
}
$mimeType = trim((string)$document["mimetype"]);
if($mimeType === ""){
    $mimeType = "application/octet-stream";
}

header("Content-Description: File Transfer");
header("Content-Type: ".$mimeType);
header("Content-Disposition: attachment; filename=\"".str_replace('"', '', $downloadName)."\"");
header("Content-Length: ".filesize($filePath));
header("Cache-Control: private, max-age=0, must-revalidate");
header("Pragma: public");
readfile($filePath);
exit();
?>

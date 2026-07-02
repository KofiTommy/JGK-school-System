<?php
session_start();
include("dbstring.php");
include_once("company.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function oa_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function oa_alert($type, $message){
    $class = "oa-alert oa-alert--info";
    if($type === "success"){ $class = "oa-alert oa-alert--success"; }
    elseif($type === "error"){ $class = "oa-alert oa-alert--error"; }
    elseif($type === "warning"){ $class = "oa-alert oa-alert--warning"; }
    return "<div class=\"$class\">".oa_esc($message)."</div>";
}
function oa_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "reviewed"){ return "oa-status oa-status--success"; }
    if($status === "needs_attention"){ return "oa-status oa-status--warning"; }
    if($status === "submitted"){ return "oa-status oa-status--info"; }
    return "oa-status oa-status--neutral";
}
function oa_status_summary($status){
    $status = strtolower(trim((string)$status));
    if($status === "reviewed"){
        return "Reviewed by the school. Editing is now closed.";
    }
    if($status === "needs_attention"){
        return "Update the form and submit again.";
    }
    if($status === "submitted"){
        return "Submitted successfully. Wait for the school to review it.";
    }
    return "Draft not submitted yet.";
}
function oa_application_status($application){
    if(!is_array($application) || empty($application)){
        return "";
    }
    return strtolower(trim((string)(isset($application["status"]) ? $application["status"] : "")));
}
function oa_application_is_locked($application){
    return in_array(oa_application_status($application), array("submitted", "reviewed"), true);
}
function oa_application_lock_message($application){
    $status = oa_application_status($application);
    if($status === "reviewed"){
        return "This form has already been reviewed by the school, so editing and resubmission are now closed.";
    }
    if($status === "submitted"){
        return "This form has already been submitted. It is now waiting for school review, so you cannot submit it again unless the school marks it as Needs Attention.";
    }
    return "";
}
function oa_payment_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "success"){ return "oa-status oa-status--success"; }
    if($status === "pending" || $status === "initialized"){ return "oa-status oa-status--info"; }
    if($status === "failed" || $status === "abandoned"){ return "oa-status oa-status--warning"; }
    return "oa-status oa-status--neutral";
}
function oa_format_date($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date("d M Y", $timestamp);
}
function oa_format_datetime($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date("d M Y, g:i a", $timestamp);
}
function oa_money($amount, $currency){
    $currency = strtoupper(trim((string)$currency));
    if($currency === ""){
        $currency = "GHS";
    }
    return $currency." ".number_format((float)$amount, 2);
}
function oa_clear_access_session(){
    unset(
        $_SESSION["ONLINE_ADMISSION_POSTING_ID"],
        $_SESSION["ONLINE_ADMISSION_YEAR"],
        $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"],
        $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"],
        $_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"]
    );
}
function oa_clear_public_session(){
    oa_clear_access_session();
    unset($_SESSION["ONLINE_ADMISSION_MESSAGE"]);
}
function oa_set_public_session($postedStudent, $application){
    $_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$postedStudent["postingid"];
    $_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$postedStudent["admissionyear"];
    $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"] = (string)$application["applicationid"];
    $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] = "1";
    unset($_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"]);
}

$branchContext = online_admission_default_branch_context($con);
$branchId = $branchContext["branchid"];
$branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
$companyName = isset($_CompanyName) && trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName) : $branchContext["company"];

if(isset($_GET["reset_admission"])){
    oa_clear_public_session();
    header("location:online-admission.php");
    exit();
}

if(isset($_GET["logout_admission"])){
    oa_clear_public_session();
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", "You have logged out of the online admission portal successfully.");
    header("location:online-admission.php");
    exit();
}

if(isset($_GET["payment_cancel"])){
    unset($_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"]);
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("warning", "Payment was not completed. You can return and continue when you are ready.");
    header("location:online-admission.php");
    exit();
}

$flashMessage = isset($_SESSION["ONLINE_ADMISSION_MESSAGE"]) ? (string)$_SESSION["ONLINE_ADMISSION_MESSAGE"] : "";
unset($_SESSION["ONLINE_ADMISSION_MESSAGE"]);

if($branchId === ""){
    $flashMessage = oa_alert("error", "The school branch configuration is not ready for online admission yet.");
}

$paymentSetting = ($branchId !== "") ? online_admission_get_payment_setting($con, $branchId) : online_admission_get_payment_setting($con, "");
$paymentEnabled = (int)$paymentSetting["enabled"] === 1 && (float)$paymentSetting["feeamount"] > 0;
$portalOpen = ($branchId !== "") ? online_admission_portal_is_open($paymentSetting) : false;
if($branchId !== "" && !$portalOpen){
    oa_clear_access_session();
}

$verifyForm = array(
    "beceindexnumber" => isset($_POST["beceindexnumber"]) ? trim((string)$_POST["beceindexnumber"]) : "",
    "birthdate" => isset($_POST["birthdate"]) ? trim((string)$_POST["birthdate"]) : "",
    "admissionyear" => isset($_POST["admissionyear"]) ? trim((string)$_POST["admissionyear"]) : date("Y")
);
$accessForm = array(
    "access_beceindexnumber" => isset($_POST["access_beceindexnumber"]) ? trim((string)$_POST["access_beceindexnumber"]) : "",
    "access_birthdate" => isset($_POST["access_birthdate"]) ? trim((string)$_POST["access_birthdate"]) : "",
    "verificationtoken" => isset($_POST["verificationtoken"]) ? strtoupper(trim((string)$_POST["verificationtoken"])) : ""
);
$resumeRequested = isset($_GET["resume_admission"]) || isset($_POST["continue_admission"]);

if(isset($_POST["verify_posting"]) && $branchId !== ""){
    if(!$portalOpen){
        $flashMessage = oa_alert("warning", "Online admission is currently closed.");
    }else{
    $beceIndex = online_admission_normalize_bece(isset($_POST["beceindexnumber"]) ? $_POST["beceindexnumber"] : "");
    $birthdate = online_admission_normalize_date(isset($_POST["birthdate"]) ? $_POST["birthdate"] : "");
    $admissionYear = trim((string)(isset($_POST["admissionyear"]) ? $_POST["admissionyear"] : date("Y")));

    if($beceIndex === "" || $birthdate === false || $birthdate === "" || $admissionYear === ""){
        $flashMessage = oa_alert("warning", "Enter your BECE index number, date of birth, and admission year to continue.");
    }else{
        $postedStudent = online_admission_find_posted_student($con, $branchId, $beceIndex, $birthdate, $admissionYear);
        if($postedStudent){
            $application = online_admission_ensure_application_for_posting($con, $postedStudent);
            if($application){
                $successfulPayment = online_admission_get_successful_payment_by_application($con, $application["applicationid"]);
                if($paymentEnabled && online_admission_payment_is_paid($successfulPayment)){
                    $application = online_admission_ensure_application_token($con, $application);
                    oa_clear_access_session();
                    $_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"] = "1";
                    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", "Posting verified. Payment already confirmed. Continue Admission is now available. Use token ".trim((string)$application["verificationtoken"])." to sign in.");
                }else{
                    oa_set_public_session($postedStudent, $application);
                    if($paymentEnabled){
                        $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", "Posting verified. Continue to payment. Your verification token will be issued after payment is confirmed.");
                    }else{
                        $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", "Posting verified. Your form is now open.");
                    }
                }
                header("location:online-admission.php");
                exit();
            }
            $flashMessage = oa_alert("error", "Your posting was verified, but the admission record could not be prepared right now. Please try again.");
        }else{
            $flashMessage = oa_alert("warning", "We could not verify your placement with those details. Check your BECE index number, date of birth, and admission year, or contact the school for support.");
        }
    }
    }
}

$postedStudent = null;
$application = null;
$accessAuthorized = false;

if(isset($_POST["continue_admission"]) && $branchId !== ""){
    if(!$portalOpen){
        $flashMessage = oa_alert("warning", "Online admission is currently closed.");
    }else{
    $beceIndex = online_admission_normalize_bece(isset($_POST["access_beceindexnumber"]) ? $_POST["access_beceindexnumber"] : "");
    $birthdate = online_admission_normalize_date(isset($_POST["access_birthdate"]) ? $_POST["access_birthdate"] : "");
    $verificationToken = strtoupper(trim((string)(isset($_POST["verificationtoken"]) ? $_POST["verificationtoken"] : "")));

    if($beceIndex === "" || $birthdate === false || $birthdate === "" || $verificationToken === ""){
        $flashMessage = oa_alert("warning", $paymentEnabled ? "Enter your BECE index number, date of birth, and token." : "Enter your BECE index number, date of birth, and resume token.");
    }else{
        $application = online_admission_find_application_by_access($con, $branchId, $beceIndex, $birthdate, $verificationToken);
        if($application){
            $postedStudent = online_admission_get_posted_student_by_id($con, $branchId, $application["postingid"]);
            if($postedStudent && $application){
                online_admission_attach_payments_to_application($con, $postedStudent["postingid"], $application["applicationid"]);
                online_admission_mark_token_used($con, $application["applicationid"]);
                oa_set_public_session($postedStudent, $application);
                unset($_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"]);
                $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", $paymentEnabled ? "Admission reopened." : "Draft reopened.");
                header("location:online-admission.php");
                exit();
            }
        }
        $flashMessage = oa_alert("warning", $paymentEnabled ? "We could not reopen your admission with those details." : "We could not reopen your saved admission with those details.");
    }
    }
}

if($branchId !== "" &&
    isset($_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) &&
    (string)$_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] === "1" &&
    trim((string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) !== ""){
    $application = online_admission_get_application_by_id($con, (string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]);
    if($application && (string)$application["branchid"] === (string)$branchId){
        $postedStudent = online_admission_get_posted_student_by_id($con, $branchId, $application["postingid"]);
        if($postedStudent){
            online_admission_attach_payments_to_application($con, $postedStudent["postingid"], $application["applicationid"]);
            $_SESSION["ONLINE_ADMISSION_POSTING_ID"] = (string)$postedStudent["postingid"];
            $_SESSION["ONLINE_ADMISSION_YEAR"] = (string)$postedStudent["admissionyear"];
            $accessAuthorized = true;
        }
    }
    if(!$accessAuthorized){
        unset($_SESSION["ONLINE_ADMISSION_POSTING_ID"], $_SESSION["ONLINE_ADMISSION_YEAR"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"], $_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"]);
        $postedStudent = null;
        $application = null;
    }
}

$form = array(
    "mobile" => $application ? (string)$application["mobile"] : ($postedStudent ? (string)$postedStudent["mobile"] : ""),
    "email" => $application ? (string)$application["email"] : "",
    "residencetype" => $application ? (string)$application["residencetype"] : ($postedStudent ? (string)$postedStudent["residentialstatus"] : ""),
    "hometown" => $application ? (string)$application["hometown"] : "",
    "postaladdress" => $application ? (string)$application["postaladdress"] : "",
    "homeaddress" => $application ? (string)$application["homeaddress"] : "",
    "religion" => $application ? (string)$application["religion"] : "",
    "guardianname" => $application ? (string)$application["guardianname"] : "",
    "guardianrelationship" => $application ? (string)$application["guardianrelationship"] : "",
    "guardiancontact" => $application ? (string)$application["guardiancontact"] : "",
    "medicalnotes" => $application ? (string)$application["medicalnotes"] : "",
    "studentnote" => $application ? (string)$application["studentnote"] : ""
);
$fixedResidenceType = online_admission_application_residence($application, $postedStudent);
if($fixedResidenceType !== ""){
    $form["residencetype"] = $fixedResidenceType;
}

$helpForm = array(
    "studentname" => isset($_POST["help_studentname"]) ? trim((string)$_POST["help_studentname"]) : ($postedStudent ? online_admission_candidate_name($postedStudent) : ($application ? online_admission_candidate_name($application) : "")),
    "contactphone" => isset($_POST["help_contactphone"]) ? trim((string)$_POST["help_contactphone"]) : ($application ? (string)$application["mobile"] : ($postedStudent ? (string)$postedStudent["mobile"] : "")),
    "beceindexnumber" => isset($_POST["help_beceindexnumber"]) ? trim((string)$_POST["help_beceindexnumber"]) : ($postedStudent ? (string)$postedStudent["beceindexnumber"] : ($accessForm["access_beceindexnumber"] !== "" ? $accessForm["access_beceindexnumber"] : $verifyForm["beceindexnumber"])),
    "admissionyear" => isset($_POST["help_admissionyear"]) ? trim((string)$_POST["help_admissionyear"]) : ($postedStudent ? (string)$postedStudent["admissionyear"] : $verifyForm["admissionyear"]),
    "verificationtoken" => isset($_POST["help_verificationtoken"]) ? strtoupper(trim((string)$_POST["help_verificationtoken"])) : ($application && trim((string)$application["verificationtoken"]) !== "" ? trim((string)$application["verificationtoken"]) : $accessForm["verificationtoken"]),
    "helpmessage" => isset($_POST["helpmessage"]) ? trim((string)$_POST["helpmessage"]) : ""
);

$isLocked = oa_application_is_locked($application);

if(isset($_POST["submit_help_request"])){
    if($branchId === ""){
        $flashMessage = oa_alert("error", "Help request could not be sent right now.");
    }else{
        $helpErrors = array();
        if($helpForm["studentname"] === ""){ $helpErrors[] = "Enter your name."; }
        if($helpForm["contactphone"] === ""){ $helpErrors[] = "Enter a contact number."; }
        if($helpForm["helpmessage"] === ""){ $helpErrors[] = "Enter your message."; }
        if(empty($helpErrors)){
            $savedHelp = online_admission_create_help_request($con, array(
                "applicationid" => $application ? (string)$application["applicationid"] : "",
                "postingid" => $postedStudent ? (string)$postedStudent["postingid"] : "",
                "beceindexnumber" => $helpForm["beceindexnumber"],
                "admissionyear" => $helpForm["admissionyear"],
                "studentname" => $helpForm["studentname"],
                "contactphone" => $helpForm["contactphone"],
                "verificationtoken" => $helpForm["verificationtoken"],
                "helpmessage" => $helpForm["helpmessage"],
                "branchid" => $branchId
            ));
            if($savedHelp){
                online_admission_log_help_request_notification($con, $savedHelp, array(
                    "studentname" => $helpForm["studentname"],
                    "contactphone" => $helpForm["contactphone"],
                    "beceindexnumber" => $helpForm["beceindexnumber"],
                    "admissionyear" => $helpForm["admissionyear"],
                    "helpmessage" => $helpForm["helpmessage"]
                ));
                $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", "Your help request has been sent.");
                header("location:online-admission.php#help-request");
                exit();
            }
            $flashMessage = oa_alert("error", "Your help request could not be sent right now.");
        }else{
            $flashMessage = oa_alert("warning", implode(" ", $helpErrors));
        }
    }
}

if((isset($_POST["save_draft"]) || isset($_POST["submit_admission"])) && !$portalOpen){
    $flashMessage = oa_alert("warning", "Online admission is currently closed by the school. Your form cannot be updated right now.");
}elseif($postedStudent && $application && $accessAuthorized && $portalOpen && $isLocked && (isset($_POST["save_draft"]) || isset($_POST["submit_admission"]))){
    $flashMessage = oa_alert("warning", oa_application_lock_message($application));
}elseif($postedStudent && $application && $accessAuthorized && $portalOpen && !$isLocked && (isset($_POST["save_draft"]) || isset($_POST["submit_admission"]))){
    foreach($form as $key => $value){
        $form[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
    }
    $form["residencetype"] = online_admission_application_residence($application, $postedStudent);

    $errors = array();
    $isSubmit = isset($_POST["submit_admission"]);
    if($isSubmit){
        if($paymentEnabled){
            $successfulPayment = online_admission_get_successful_payment_by_application($con, $application["applicationid"]);
            if(!$successfulPayment){
                $errors[] = "Please complete the admission payment first. The form unlocks only after your payment is confirmed.";
            }
        }
        foreach(array(
            "mobile" => "Mobile number",
            "residencetype" => "Residence type",
            "hometown" => "Hometown",
            "homeaddress" => "Home address",
            "religion" => "Religion",
            "guardianname" => "Parent / guardian name",
            "guardianrelationship" => "Guardian relationship",
            "guardiancontact" => "Guardian contact"
        ) as $field => $label){
            if(trim((string)$form[$field]) === ""){
                $errors[] = $label." is required.";
            }
        }
    }

    if($form["email"] !== "" && !filter_var($form["email"], FILTER_VALIDATE_EMAIL)){
        $errors[] = "Please enter a valid email address.";
    }

    $imageName = $application ? trim((string)$application["filename"]) : "";
    if(isset($_FILES["admissionphoto"]) && isset($_FILES["admissionphoto"]["error"]) && (int)$_FILES["admissionphoto"]["error"] !== UPLOAD_ERR_NO_FILE){
        $imageError = "";
        $storedImage = online_admission_store_image($_FILES["admissionphoto"], $imageError);
        if($storedImage === false){
            $errors[] = $imageError;
        }elseif($storedImage !== ""){
            $imageName = $storedImage;
        }
    }

    if(empty($errors)){
        $status = $isSubmit ? "submitted" : "draft";
        $applicationId = (string)$application["applicationid"];
        $applicationIdEsc = mysqli_real_escape_string($con, $applicationId);
        $postingIdEsc = mysqli_real_escape_string($con, (string)$postedStudent["postingid"]);
        $beceEsc = mysqli_real_escape_string($con, (string)$postedStudent["beceindexnumber"]);
        $yearEsc = mysqli_real_escape_string($con, (string)$postedStudent["admissionyear"]);
        $firstEsc = mysqli_real_escape_string($con, (string)$postedStudent["firstname"]);
        $surnameEsc = mysqli_real_escape_string($con, (string)$postedStudent["surname"]);
        $otherEsc = mysqli_real_escape_string($con, (string)$postedStudent["othernames"]);
        $genderEsc = mysqli_real_escape_string($con, (string)$postedStudent["gender"]);
        $birthEsc = mysqli_real_escape_string($con, (string)$postedStudent["birthdate"]);
        $emailEsc = mysqli_real_escape_string($con, $form["email"]);
        $mobileEsc = mysqli_real_escape_string($con, $form["mobile"]);
        $residenceEsc = mysqli_real_escape_string($con, $form["residencetype"]);
        $hometownEsc = mysqli_real_escape_string($con, $form["hometown"]);
        $postalEsc = mysqli_real_escape_string($con, $form["postaladdress"]);
        $homeEsc = mysqli_real_escape_string($con, $form["homeaddress"]);
        $religionEsc = mysqli_real_escape_string($con, $form["religion"]);
        $guardianNameEsc = mysqli_real_escape_string($con, $form["guardianname"]);
        $guardianRelEsc = mysqli_real_escape_string($con, $form["guardianrelationship"]);
        $guardianContactEsc = mysqli_real_escape_string($con, $form["guardiancontact"]);
        $medicalEsc = mysqli_real_escape_string($con, $form["medicalnotes"]);
        $studentNoteEsc = mysqli_real_escape_string($con, $form["studentnote"]);
        $filenameEsc = mysqli_real_escape_string($con, $imageName);
        $statusEsc = mysqli_real_escape_string($con, $status);

        $exists = mysqli_query($con, "SELECT applicationid FROM tblonlineadmissionapplication WHERE postingid='$postingIdEsc' LIMIT 1");
        if($exists && mysqli_num_rows($exists) > 0){
            $sql = "UPDATE tblonlineadmissionapplication SET
                beceindexnumber='$beceEsc',
                admissionyear='$yearEsc',
                firstname='$firstEsc',
                surname='$surnameEsc',
                othernames='$otherEsc',
                gender='$genderEsc',
                birthdate='$birthEsc',
                email='$emailEsc',
                mobile='$mobileEsc',
                residencetype='$residenceEsc',
                hometown='$hometownEsc',
                postaladdress='$postalEsc',
                homeaddress='$homeEsc',
                religion='$religionEsc',
                guardianname='$guardianNameEsc',
                guardianrelationship='$guardianRelEsc',
                guardiancontact='$guardianContactEsc',
                medicalnotes='$medicalEsc',
                studentnote='$studentNoteEsc',
                filename='$filenameEsc',
                uploadeddatetime=".($imageName !== "" ? "NOW()" : "uploadeddatetime").",
                status='$statusEsc',
                submittedat=".($isSubmit ? "NOW()" : "submittedat").",
                updatedat=NOW()
                WHERE postingid='$postingIdEsc'
                LIMIT 1";
            $saved = mysqli_query($con, $sql);
        }else{
            $sql = "INSERT INTO tblonlineadmissionapplication(
                applicationid, postingid, beceindexnumber, admissionyear, firstname, surname, othernames, gender, birthdate,
                email, mobile, residencetype, hometown, postaladdress, homeaddress, religion, guardianname,
                guardianrelationship, guardiancontact, medicalnotes, studentnote, filename, uploadeddatetime, status,
                submittedat, updatedat, branchid
            ) VALUES(
                '$applicationIdEsc', '$postingIdEsc', '$beceEsc', '$yearEsc', '$firstEsc', '$surnameEsc', '$otherEsc', '$genderEsc', '$birthEsc',
                '$emailEsc', '$mobileEsc', '$residenceEsc', '$hometownEsc', '$postalEsc', '$homeEsc', '$religionEsc', '$guardianNameEsc',
                '$guardianRelEsc', '$guardianContactEsc', '$medicalEsc', '$studentNoteEsc', '$filenameEsc', ".($imageName !== "" ? "NOW()" : "NULL").", '$statusEsc',
                ".($isSubmit ? "NOW()" : "NULL").", NOW(), '$branchIdEsc'
            )";
            $saved = mysqli_query($con, $sql);
        }

        if($saved){
            $savedApplication = online_admission_get_application_by_id($con, $applicationId);
            if((!$paymentEnabled && !$isSubmit) || $isSubmit){
                $savedApplication = online_admission_ensure_application_token($con, $savedApplication);
                if($savedApplication){
                    $application = $savedApplication;
                }
            }
            $assignedHouse = null;
            if($isSubmit && $savedApplication){
                $assignedHouse = online_admission_assign_house_for_application($con, $savedApplication, $postedStudent);
                $savedApplication = online_admission_get_application_by_id($con, $applicationId);
                online_admission_log_submission_notification($con, $savedApplication, $postedStudent);
            }
            if(!$paymentEnabled && !$isSubmit){
                $resumeToken = $savedApplication ? trim((string)$savedApplication["verificationtoken"]) : "";
                $_SESSION["ONLINE_ADMISSION_MESSAGE"] = $resumeToken !== ""
                    ? oa_alert("success", "Draft saved. Your resume token is ".$resumeToken.".")
                    : oa_alert("success", "Admission draft saved successfully.");
            }elseif($isSubmit){
                $guardianSmsResult = $savedApplication ? online_admission_send_guardian_submission_sms($con, $savedApplication, $companyName) : array("sent" => false, "status" => "INVALID_CONTEXT");
                $accessToken = $savedApplication ? trim((string)$savedApplication["verificationtoken"]) : "";
                $houseMessage = ($assignedHouse && trim((string)$assignedHouse["housename"]) !== "") ? " Auto house: ".$assignedHouse["housename"]."." : "";
                if($paymentEnabled){
                    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", !empty($guardianSmsResult["sent"])
                        ? "Admission submitted successfully. A confirmation SMS has been sent.".$houseMessage
                        : "Admission submitted successfully.".$houseMessage);
                }else{
                    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", !empty($guardianSmsResult["sent"])
                        ? "Admission submitted successfully. A confirmation SMS has been sent. Resume token: ".($accessToken !== "" ? $accessToken : "available on your portal").".".$houseMessage
                        : "Admission submitted successfully. Resume token: ".($accessToken !== "" ? $accessToken : "available on your portal").".".$houseMessage);
                }
            }else{
                $_SESSION["ONLINE_ADMISSION_MESSAGE"] = oa_alert("success", "Draft saved successfully.");
            }
            header("location:online-admission.php");
            exit();
        }
        $flashMessage = oa_alert("error", "Your admission details could not be saved right now. Please try again.");
    }else{
        $flashMessage = oa_alert("warning", implode(" ", $errors));
    }
}

$application = ($accessAuthorized && $application) ? online_admission_get_application_by_id($con, $application["applicationid"]) : null;
$assignedHouse = ($application) ? online_admission_application_assigned_house($con, $application) : null;
$isLocked = oa_application_is_locked($application);
$applicationLockMessage = $isLocked ? oa_application_lock_message($application) : "";
$paystackConfig = online_admission_paystack_config();
$latestPayment = ($application) ? online_admission_get_latest_payment_by_application($con, $application["applicationid"]) : null;
$successfulPayment = ($application) ? online_admission_get_successful_payment_by_application($con, $application["applicationid"]) : null;
$paymentReady = online_admission_paystack_is_ready($paystackConfig);
$paymentRequiredStatus = online_admission_payment_required_status($paymentSetting);
$paymentAllowed = ($postedStudent && $application) ? online_admission_payment_open_for_student($postedStudent, $application, $paymentSetting) : false;
$paymentPaid = online_admission_payment_is_paid($successfulPayment);
$verificationToken = ($application && trim((string)$application["verificationtoken"]) !== "") ? trim((string)$application["verificationtoken"]) : "";
$paymentContinueReady = isset($_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"]) && (string)$_SESSION["ONLINE_ADMISSION_PAYMENT_READY_TO_CONTINUE"] === "1";
$showResumeAccess = $paymentEnabled ? ($paymentContinueReady || $resumeRequested) : $resumeRequested;
$resumeOnlyMode = (!$postedStudent && $showResumeAccess);
$applicationStatusText = $application ? online_admission_status_label($application["status"]) : "Not started";
$applicationStatusSummary = $application ? oa_status_summary($application["status"]) : "";
$downloadUrl = ($application && $accessAuthorized) ? "online-admission-download.php" : "";
$paymentContinueUrl = ($latestPayment && !$paymentPaid && strtolower(trim((string)$latestPayment["status"])) === "initialized" && trim((string)$latestPayment["authorizationurl"]) !== "")
    ? trim((string)$latestPayment["authorizationurl"])
    : "online-admission-paystack-init.php";
$showAdmissionForm = $accessAuthorized && (!$paymentEnabled || $paymentPaid);
$documentsUnlocked = ($application && $postedStudent) ? online_admission_documents_unlocked($application, $successfulPayment, $paymentEnabled ? 1 : 0) : false;
$studentDocuments = ($documentsUnlocked && $postedStudent)
    ? online_admission_resolve_random_documents_for_application(
        $con,
        online_admission_list_documents($con, $branchId, (string)$postedStudent["admissionyear"]),
        $application,
        $postedStudent
    )
    : array();
$uploadedAdmissionLetter = null;
$assignedProspectus = online_admission_find_matching_prospectus($studentDocuments, $application, $postedStudent);
$visibleStudentDocuments = $studentDocuments;
foreach($studentDocuments as $index => $documentRow){
    if($uploadedAdmissionLetter === null && online_admission_document_is_admission_letter($documentRow)){
        $uploadedAdmissionLetter = $documentRow;
        unset($visibleStudentDocuments[$index]);
        continue;
    }
    if($assignedProspectus && (string)$assignedProspectus["documentid"] === (string)$documentRow["documentid"]){
        $assignedProspectus = $documentRow;
        unset($visibleStudentDocuments[$index]);
    }
}
$visibleStudentDocuments = array_values($visibleStudentDocuments);
$admissionLetterWithheld = ($documentsUnlocked && $application && $accessAuthorized && online_admission_application_is_submitted($application));
$admissionLetterUrl = "";
$admissionLetterLabel = $uploadedAdmissionLetter
    ? online_admission_document_display_title($uploadedAdmissionLetter)
    : "Admission Letter";
$admissionLetterNote = "The school office will print and issue this letter when you report.";
$prospectusUrl = $assignedProspectus
    ? "online-admission-document.php?documentid=".rawurlencode((string)$assignedProspectus["documentid"])
    : "";
$prospectusLabel = $assignedProspectus
    ? online_admission_document_display_title($assignedProspectus)
    : "Prospectus";
$prospectusNote = $assignedProspectus
    ? trim((string)$assignedProspectus["originalfilename"])
    : "";
$hasStudentDownloads = ($prospectusUrl !== "" || !empty($visibleStudentDocuments) || $downloadUrl !== "" || $admissionLetterWithheld);
?>
<!DOCTYPE html>
<html>
<head>
<?php include("title.php"); include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/online-admission.css">
</head>
<body class="online-admission-page">
<div class="oa-shell" id="admission-top">
    <header class="oa-topbar">
        <div class="oa-brand">
            <div class="oa-brand__mark"><img src="images/nexgen-logo.png" alt="NexGen"></div>
            <div class="oa-brand__text">
                <span class="oa-kicker">Online Admission</span>
                <h2><?php echo oa_esc($companyName); ?></h2>
            </div>
        </div>
        <div class="oa-topbar__actions">
            <a href="index.php" class="oa-top-link"><i class="fa fa-home"></i> Landing Page</a>
            <a href="#help-request" class="oa-top-link oa-top-link--help"><i class="fa fa-life-ring"></i> Need Help?</a>
            <?php if($postedStudent){ ?><a href="online-admission.php?logout_admission=1" class="oa-top-link oa-top-link--ghost"><i class="fa fa-sign-out"></i> Log Out</a><?php } ?>
            <?php if($postedStudent){ ?><a href="online-admission.php?reset_admission=1" class="oa-top-link oa-top-link--ghost"><i class="fa fa-refresh"></i> Verify Another Student</a><?php } ?>
        </div>
    </header>
    <a href="#help-request" class="oa-floating-help"><i class="fa fa-life-ring"></i> <span>Need Help?</span></a>

    <?php if($flashMessage !== ""){ ?><div class="oa-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="oa-hero">
        <div class="oa-hero__copy">
            <span class="oa-kicker"><i class="fa fa-graduation-cap"></i> Posted Students Portal</span>
            <h1><?php echo oa_esc($resumeOnlyMode ? "Token Verification" : "Online Admission"); ?></h1>
            <p><?php echo oa_esc($resumeOnlyMode ? "Enter the student's details and token to continue." : ($paymentEnabled ? "Verify posting, pay, and complete the form." : "Verify posting and complete the form.")); ?></p>
            <div class="oa-step-row">
                <?php if($resumeOnlyMode){ ?>
                <span>1. Enter student details</span>
                <span>2. Enter token</span>
                <span>3. Continue admission</span>
                <?php }elseif($paymentEnabled){ ?>
                <span>1. Verify posting</span>
                <span>2. Pay admission fee</span>
                <span>3. Receive token</span>
                <span>4. Fill and submit form</span>
                <?php }else{ ?>
                <span>1. Verify posting</span>
                <span>2. Fill form</span>
                <span>3. Submit or save draft</span>
                <?php } ?>
            </div>
        </div>
        <aside class="oa-hero-card">
            <h3><?php echo oa_esc($resumeOnlyMode ? "Required" : "Have These Ready"); ?></h3>
            <ul>
                <li>BECE index number</li>
                <li>Date of birth</li>
                <li><?php echo oa_esc($resumeOnlyMode ? "Token" : "Token if returning"); ?></li>
                <?php if(!$resumeOnlyMode && $paymentEnabled){ ?><li>Phone or payment method for Paystack</li><?php } ?>
                <?php if(!$resumeOnlyMode){ ?><li>Parent or guardian contact</li><?php } ?>
            </ul>
            <?php if($branchContext["telephone1"] !== ""){ ?><p><i class="fa fa-phone"></i> Help line: <?php echo oa_esc($branchContext["telephone1"]); ?></p><?php } ?>
        </aside>
    </section>

    <?php if(!$portalOpen){ ?>
    <section class="oa-card oa-card--stacked">
        <div class="oa-section-head">
            <h2>Online Admission Is Currently Closed</h2>
            <p>Please try again later or contact the school.</p>
        </div>
    </section>
    <?php }elseif(!$postedStudent){ ?>
    <section class="oa-verify-section">
        <?php if(!$resumeOnlyMode){ ?>
        <div class="oa-card oa-card--verify">
            <div class="oa-section-head">
                <h2>Verify Your Posting</h2>
            </div>
            <form method="post" action="online-admission.php" class="oa-form oa-form--verify">
                <div class="oa-field">
                    <label for="beceindexnumber">BECE Index Number</label>
                    <input type="text" id="beceindexnumber" name="beceindexnumber" placeholder="Example: 1234567890" value="<?php echo oa_esc($verifyForm["beceindexnumber"]); ?>" required>
                </div>
                <div class="oa-field">
                    <label for="birthdate">Date of Birth</label>
                    <input type="date" id="birthdate" name="birthdate" value="<?php echo oa_esc($verifyForm["birthdate"]); ?>" required>
                </div>
                <div class="oa-field">
                    <label for="admissionyear">Admission Year</label>
                    <input type="text" id="admissionyear" name="admissionyear" value="<?php echo oa_esc($verifyForm["admissionyear"]); ?>" required>
                </div>
                <button type="submit" name="verify_posting" class="oa-submit"><i class="fa fa-check-circle"></i> <?php echo oa_esc($paymentEnabled ? "Verify and Continue" : "Verify and Open Form"); ?></button>
            </form>
            <div class="oa-step-guide">
                <article>
                    <strong>Before You Click Verify</strong>
                    <span>Use the BECE index number and date of birth exactly as they appear on the student placement records.</span>
                </article>
                <article>
                    <strong>For Parents</strong>
                    <span>If you are helping a student, verify the details first before moving to payment or the form.</span>
                </article>
                <article>
                    <strong>What Happens Next</strong>
                    <span><?php echo oa_esc($paymentEnabled ? "Once the record is confirmed, the payment stage will open." : "Once the record is confirmed, the admission form will open immediately."); ?></span>
                </article>
            </div>
        </div>
        <?php } ?>

        <?php if($showResumeAccess){ ?>
        <div class="oa-card">
            <div class="oa-section-head">
                <h2><?php echo oa_esc($paymentEnabled ? "Enter Verification Token" : "Enter Resume Token"); ?></h2>
            </div>
            <form method="post" action="online-admission.php" class="oa-form oa-form--verify">
                <div class="oa-field">
                    <label for="access_beceindexnumber">BECE Index Number</label>
                    <input type="text" id="access_beceindexnumber" name="access_beceindexnumber" placeholder="Example: 1234567890" value="<?php echo oa_esc($accessForm["access_beceindexnumber"]); ?>" required>
                </div>
                <div class="oa-field">
                    <label for="access_birthdate">Date of Birth</label>
                    <input type="date" id="access_birthdate" name="access_birthdate" value="<?php echo oa_esc($accessForm["access_birthdate"]); ?>" required>
                </div>
                <div class="oa-field">
                    <label for="verificationtoken"><?php echo oa_esc($paymentEnabled ? "Verification Token" : "Resume Token"); ?></label>
                    <input type="text" id="verificationtoken" name="verificationtoken" value="<?php echo oa_esc($accessForm["verificationtoken"]); ?>" placeholder="<?php echo oa_esc($paymentEnabled ? "Enter your token" : "Enter your resume token"); ?>" required>
                </div>
                <button type="submit" name="continue_admission" class="oa-submit"><i class="fa fa-unlock-alt"></i> <?php echo oa_esc($paymentEnabled ? "Verify Token and Continue" : "Verify Resume Token"); ?></button>
            </form>
            <div class="oa-step-guide oa-step-guide--compact">
                <article>
                    <strong>Already Have a Token?</strong>
                    <span><?php echo oa_esc($paymentEnabled ? "Use the token issued after payment to reopen your admission form." : "Use your resume token to reopen and continue from where you stopped."); ?></span>
                </article>
            </div>
            <?php if($resumeOnlyMode){ ?>
            <div class="oa-form-actions oa-form-actions--stacked">
                <a href="online-admission.php" class="oa-secondary"><i class="fa fa-arrow-left"></i> New applicant? Verify posting first</a>
            </div>
            <?php } ?>
        </div>
        <?php }elseif(!$paymentEnabled){ ?>
        <div class="oa-card">
            <div class="oa-section-head">
                <h2>Already Have a Token?</h2>
            </div>
            <div class="oa-form-actions oa-form-actions--stacked">
                <a href="online-admission.php?resume_admission=1" class="oa-secondary"><i class="fa fa-unlock-alt"></i> Enter Resume Token</a>
            </div>
        </div>
        <?php } ?>
    </section>
    <?php }else{ ?>
    <div class="oa-verified-bar">
        <div>
            <span class="oa-kicker oa-kicker--dark">Verified Student</span>
            <h2><?php echo oa_esc(trim($postedStudent["firstname"]." ".$postedStudent["othernames"]." ".$postedStudent["surname"])); ?></h2>
            <p><?php echo oa_esc($postedStudent["beceindexnumber"]); ?> - <?php echo oa_esc($postedStudent["admissionyear"]); ?><?php if($assignedHouse && trim((string)$assignedHouse["housename"]) !== ""){ ?> - <?php echo oa_esc($assignedHouse["housename"]); ?><?php } ?></p>
        </div>
        <span class="oa-verified-bar__meta"><?php echo oa_esc($paymentEnabled ? "Token: ".($verificationToken !== "" ? $verificationToken : "Pending") : ($verificationToken !== "" ? "Resume Token: ".$verificationToken : "Direct form access")); ?></span>
        <?php if($application){ ?><span class="<?php echo oa_status_class($application["status"]); ?>"><?php echo oa_esc(online_admission_status_label($application["status"])); ?></span><?php } ?>
    </div>

    <div class="oa-layout">
        <?php if(!$showAdmissionForm){ ?>
        <section class="oa-card oa-card--main oa-card--gate">
            <div class="oa-section-head">
                <h2>Complete Payment</h2>
            </div>

            <div class="oa-student-summary">
                <article><span>Programme</span><strong><?php echo oa_esc($postedStudent["offeredprogram"] !== "" ? $postedStudent["offeredprogram"] : "To be confirmed"); ?></strong></article>
                <article><span>Class</span><strong><?php echo oa_esc($postedStudent["offeredclass"] !== "" ? $postedStudent["offeredclass"] : "To be assigned"); ?></strong></article>
                <article><span>Gender</span><strong><?php echo oa_esc($postedStudent["gender"] !== "" ? $postedStudent["gender"] : "Not set"); ?></strong></article>
                <article><span>Date of Birth</span><strong><?php echo oa_esc(oa_format_date($postedStudent["birthdate"])); ?></strong></article>
            </div>

            <div class="oa-form oa-form--stage">
                <div class="oa-payment-callout">
                    <strong>Verification Token</strong>
                    <span><code><?php echo oa_esc($verificationToken); ?></code></span>
                </div>
                <div class="oa-step-guide">
                    <article>
                        <strong>Step 1: Start Payment</strong>
                        <span>Click <b>Pay with Paystack</b>. You will be taken to Paystack's secure page to enter Mobile Money or card details.</span>
                    </article>
                    <article>
                        <strong>Step 2: Wait For Confirmation</strong>
                        <span>Do not close the page during payment. When payment succeeds, the system will issue your verification token.</span>
                    </article>
                    <article>
                        <strong>Step 3: Reopen Admission</strong>
                        <span>After payment, come back and log in again with the student's BECE index number, date of birth, and the issued token.</span>
                    </article>
                    <article>
                        <strong>Important</strong>
                        <span>Parents should keep the phone used for payment nearby in case Paystack asks for a confirmation prompt.</span>
                    </article>
                </div>
                <div class="oa-payment-summary">
                    <article>
                        <span>Admission Fee</span>
                        <strong><?php echo oa_esc($paymentEnabled ? oa_money($paymentSetting["feeamount"], $paymentSetting["currency"]) : "Not configured"); ?></strong>
                    </article>
                    <article>
                        <span>Current Step</span>
                        <strong>Payment Before Form</strong>
                    </article>
                    <article>
                        <span>Latest Payment</span>
                        <strong><?php echo oa_esc($latestPayment ? online_admission_payment_status_label($latestPayment["status"]) : "Not started"); ?></strong>
                    </article>
                </div>

                <?php if(trim((string)$paymentSetting["note"]) !== ""){ ?>
                <p class="oa-payment-note"><?php echo oa_esc($paymentSetting["note"]); ?></p>
                <?php } ?>

                <?php if(!$paymentEnabled){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Online payment is not enabled.</div>
                <?php }elseif(!$paymentReady){ ?>
                <div class="oa-payment-state oa-payment-state--warning">Payment setup is not ready yet.</div>
                <?php }elseif(!$paymentAllowed){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Payment is not open for this record yet.</div>
                <?php }else{ ?>
                <div class="oa-payment-state oa-payment-state--info">Pay on Paystack first. Your verification token will be issued after payment, then you can sign in again.</div>
                <?php if($latestPayment && trim((string)$latestPayment["reference"]) !== ""){ ?>
                <div class="oa-payment-meta">
                    <span><strong>Reference:</strong> <?php echo oa_esc($latestPayment["reference"]); ?></span>
                    <span class="<?php echo oa_payment_status_class($latestPayment["status"]); ?>"><?php echo oa_esc(online_admission_payment_status_label($latestPayment["status"])); ?></span>
                </div>
                <?php } ?>
                <div class="oa-form-actions oa-form-actions--stacked">
                    <a href="<?php echo oa_esc($paymentContinueUrl); ?>" class="oa-submit"><i class="fa fa-credit-card"></i> <?php echo ($latestPayment && strtolower(trim((string)$latestPayment["status"])) === "initialized") ? "Continue Paystack Checkout" : "Pay with Paystack"; ?></a>
                    <?php if($latestPayment && strtolower(trim((string)$latestPayment["status"])) !== "success"){ ?>
                    <a href="online-admission-paystack-init.php" class="oa-secondary"><i class="fa fa-refresh"></i> Start Fresh Payment</a>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </section>

        <aside class="oa-side">
            <section class="oa-card">
                <div class="oa-section-head">
                    <h2>Quick Note</h2>
                </div>
                <div class="oa-note-grid">
                    <article><strong>Pay on Paystack</strong><span>Enter payment details there.</span></article>
                    <article><strong>Get your token</strong><span>It will be issued after payment and used to reopen the form.</span></article>
                </div>
            </section>
        </aside>
        <?php }else{ ?>
        <section class="oa-card oa-card--main">
            <div class="oa-section-head">
                <h2>Admission Form</h2>
            </div>

            <div class="oa-student-summary">
                <article><span>Programme</span><strong><?php echo oa_esc($postedStudent["offeredprogram"] !== "" ? $postedStudent["offeredprogram"] : "To be confirmed"); ?></strong></article>
                <article><span>Class</span><strong><?php echo oa_esc($postedStudent["offeredclass"] !== "" ? $postedStudent["offeredclass"] : "To be assigned"); ?></strong></article>
                <article><span>Gender</span><strong><?php echo oa_esc($postedStudent["gender"] !== "" ? $postedStudent["gender"] : "Not set"); ?></strong></article>
                <article><span>Date of Birth</span><strong><?php echo oa_esc(oa_format_date($postedStudent["birthdate"])); ?></strong></article>
            </div>

            <?php if($application && trim((string)$application["reviewnote"]) !== ""){ ?>
            <div class="oa-review-note"><?php echo oa_esc($application["reviewnote"]); ?></div>
            <?php } ?>

            <?php if($applicationLockMessage !== ""){ ?>
            <div class="oa-payment-state <?php echo oa_application_status($application) === "reviewed" ? "oa-payment-state--success" : "oa-payment-state--info"; ?>">
                <?php echo oa_esc($applicationLockMessage); ?>
            </div>
            <?php } ?>

            <?php if($verificationToken !== ""){ ?>
            <div class="oa-payment-callout">
                <strong><?php echo oa_esc($paymentEnabled ? "Verification Token" : "Resume Token"); ?></strong>
                <span><code><?php echo oa_esc($verificationToken); ?></code></span>
            </div>
            <?php }else{ ?>
            <div class="oa-payment-callout">
                <strong>Continue Later</strong>
                <span>Save draft to get a resume token.</span>
            </div>
            <?php } ?>

            <div class="oa-step-guide">
                <article>
                    <strong>Fill Carefully</strong>
                    <span>Check phone numbers, parent or guardian details, and address information before you submit.</span>
                </article>
                <article>
                    <strong>Photo And Contact</strong>
                    <span>Upload a clear student photo and make sure the student and guardian phone numbers are correct.</span>
                </article>
                <article>
                    <strong>Submit Once</strong>
                    <span>After final submission, the form locks unless the school marks it as needing attention.</span>
                </article>
            </div>

            <form method="post" action="online-admission.php" enctype="multipart/form-data" class="oa-form">
                <div class="oa-inline-photo-card">
                    <div class="oa-photo-preview">
                        <img src="<?php echo oa_esc($application ? online_admission_photo_src($application["filename"]) : "uploads/comm.gif"); ?>" alt="Admission photo preview" id="oa-photo-preview">
                    </div>
                    <div class="oa-photo-copy">
                        <label for="admissionphoto">Upload Photo</label>
                        <input type="file" id="admissionphoto" name="admissionphoto" accept=".jpg,.jpeg,.png,.gif,.webp,image/*"<?php echo $isLocked ? " disabled" : ""; ?>>
                        <small>Accepted formats: JPG, PNG, GIF, WEBP. Maximum size: 5MB.</small>
                    </div>
                </div>

                <section class="oa-form-section">
                    <div class="oa-form-head">
                        <h3>Contact Details</h3>
                    </div>
                    <div class="oa-grid oa-grid--two">
                        <div class="oa-field"><label for="mobile">Student Mobile Number</label><input type="tel" id="mobile" name="mobile" value="<?php echo oa_esc($form["mobile"]); ?>"<?php echo $isLocked ? " readonly" : ""; ?>></div>
                        <div class="oa-field"><label for="email">Email Address</label><input type="email" id="email" name="email" value="<?php echo oa_esc($form["email"]); ?>"<?php echo $isLocked ? " readonly" : ""; ?>></div>
                        <div class="oa-field">
                            <label for="residencetype_display">Residence Type</label>
                            <input type="text" id="residencetype_display" value="<?php echo oa_esc($form["residencetype"] !== "" ? $form["residencetype"] : "Not set by school yet"); ?>" readonly>
                            <input type="hidden" name="residencetype" value="<?php echo oa_esc($form["residencetype"]); ?>">
                            <small>Locked to your placement record.</small>
                        </div>
                        <div class="oa-field"><label for="religion">Religion</label><select id="religion" name="religion"<?php echo $isLocked ? " disabled" : ""; ?>><option value="">Select religion</option><option value="Christian"<?php echo $form["religion"] === "Christian" ? " selected" : ""; ?>>Christian</option><option value="Muslim"<?php echo $form["religion"] === "Muslim" ? " selected" : ""; ?>>Muslim</option><option value="Tradition"<?php echo $form["religion"] === "Tradition" ? " selected" : ""; ?>>Tradition</option><option value="Others"<?php echo $form["religion"] === "Others" ? " selected" : ""; ?>>Others</option></select></div>
                    </div>
                </section>

                <section class="oa-form-section">
                    <div class="oa-form-head">
                        <h3>Address</h3>
                    </div>
                    <div class="oa-grid oa-grid--two">
                        <div class="oa-field"><label for="hometown">Hometown</label><input type="text" id="hometown" name="hometown" value="<?php echo oa_esc($form["hometown"]); ?>"<?php echo $isLocked ? " readonly" : ""; ?>></div>
                        <div class="oa-field"><label for="postaladdress">Postal Address</label><textarea id="postaladdress" name="postaladdress" rows="3"<?php echo $isLocked ? " readonly" : ""; ?>><?php echo oa_esc($form["postaladdress"]); ?></textarea></div>
                        <div class="oa-field oa-field--full"><label for="homeaddress">Home Address</label><textarea id="homeaddress" name="homeaddress" rows="3"<?php echo $isLocked ? " readonly" : ""; ?>><?php echo oa_esc($form["homeaddress"]); ?></textarea></div>
                    </div>
                </section>

                <section class="oa-form-section">
                    <div class="oa-form-head">
                        <h3>Parent / Guardian</h3>
                    </div>
                    <div class="oa-grid oa-grid--two">
                        <div class="oa-field"><label for="guardianname">Parent / Guardian Name</label><input type="text" id="guardianname" name="guardianname" value="<?php echo oa_esc($form["guardianname"]); ?>"<?php echo $isLocked ? " readonly" : ""; ?>></div>
                        <div class="oa-field"><label for="guardianrelationship">Relationship</label><input type="text" id="guardianrelationship" name="guardianrelationship" value="<?php echo oa_esc($form["guardianrelationship"]); ?>"<?php echo $isLocked ? " readonly" : ""; ?>></div>
                        <div class="oa-field"><label for="guardiancontact">Contact Number</label><input type="tel" id="guardiancontact" name="guardiancontact" value="<?php echo oa_esc($form["guardiancontact"]); ?>"<?php echo $isLocked ? " readonly" : ""; ?>></div>
                    </div>
                </section>

                <section class="oa-form-section">
                    <div class="oa-form-head">
                        <h3>Extra Information</h3>
                    </div>
                    <div class="oa-grid oa-grid--two">
                        <div class="oa-field"><label for="medicalnotes">Medical Notes</label><textarea id="medicalnotes" name="medicalnotes" rows="3"<?php echo $isLocked ? " readonly" : ""; ?>><?php echo oa_esc($form["medicalnotes"]); ?></textarea></div>
                        <div class="oa-field"><label for="studentnote">Student Note</label><textarea id="studentnote" name="studentnote" rows="3"<?php echo $isLocked ? " readonly" : ""; ?>><?php echo oa_esc($form["studentnote"]); ?></textarea></div>
                    </div>
                </section>

                <?php if(!$isLocked){ ?>
                <div class="oa-form-actions">
                    <button type="submit" name="save_draft" class="oa-secondary"><i class="fa fa-save"></i> Save Draft</button>
                    <button type="submit" name="submit_admission" class="oa-submit"><i class="fa fa-paper-plane"></i> Submit Admission</button>
                </div>
                <?php } ?>
            </form>
        </section>

        <aside class="oa-side">
            <section class="oa-card">
                <div class="oa-section-head">
                    <h2>Your Application</h2>
                </div>
                <div class="oa-payment-summary">
                    <article>
                        <span>Current Status</span>
                        <strong><?php echo oa_esc($applicationStatusText); ?></strong>
                    </article>
                    <article>
                        <span>Submitted On</span>
                        <strong><?php echo oa_esc($application ? oa_format_datetime($application["submittedat"]) : "Not available"); ?></strong>
                    </article>
                    <article>
                        <span>Last Updated</span>
                        <strong><?php echo oa_esc($application ? oa_format_datetime($application["updatedat"]) : "Not available"); ?></strong>
                    </article>
                    <article>
                        <span>Reviewed On</span>
                        <strong><?php echo oa_esc($application ? oa_format_datetime($application["revieweddatetime"]) : "Not available"); ?></strong>
                    </article>
                    <article>
                        <span>Assigned House</span>
                        <strong><?php echo oa_esc($assignedHouse && trim((string)$assignedHouse["housename"]) !== "" ? $assignedHouse["housename"] : "Pending"); ?></strong>
                    </article>
                </div>
                <div class="oa-payment-state oa-payment-state--info"><?php echo oa_esc($applicationStatusSummary); ?></div>
                <div class="oa-download-hub">
                    <div class="oa-form-head oa-form-head--compact">
                        <h3>Downloads</h3>
                    </div>
                <?php if(!$application || !online_admission_application_is_submitted($application)){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Submit your online form first to unlock your downloads.</div>
                <?php }elseif($paymentEnabled && !$paymentPaid){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Confirm your online payment first to unlock your downloads.</div>
                <?php }elseif(!$hasStudentDownloads){ ?>
                <div class="oa-payment-state oa-payment-state--warning">Your downloads are not ready yet.</div>
                <?php }else{ ?>
                <div class="oa-payment-state oa-payment-state--info">Download the required documents below and keep them safely. Bring them with you when reporting to the school, and return any document the school expects you to submit after signing or completing it.</div>
                <div class="oa-document-list">
                    <?php if($admissionLetterWithheld){ ?>
                    <article class="oa-document-card oa-document-card--notice">
                        <strong><?php echo oa_esc($admissionLetterLabel); ?></strong>
                        <span><?php echo oa_esc($admissionLetterNote !== "" ? $admissionLetterNote : "The school office will print and issue this letter when you report."); ?></span>
                        <span class="oa-document-badge"><i class="fa fa-lock"></i> Issued at school</span>
                    </article>
                    <?php } ?>
                    <?php if($prospectusUrl !== ""){ ?>
                    <article class="oa-document-card">
                        <strong><?php echo oa_esc($prospectusLabel); ?></strong>
                        <span><?php echo oa_esc($prospectusNote !== "" ? $prospectusNote : "Assigned automatically for your residence and gender."); ?></span>
                        <a href="<?php echo oa_esc($prospectusUrl); ?>" class="oa-secondary"><i class="fa fa-download"></i> Download</a>
                    </article>
                    <?php } ?>
                    <?php foreach($visibleStudentDocuments as $documentRow){ ?>
                    <article class="oa-document-card">
                        <strong><?php echo oa_esc(online_admission_document_display_title($documentRow)); ?></strong>
                        <span><?php echo oa_esc(trim((string)$documentRow["originalfilename"]) !== "" ? $documentRow["originalfilename"] : $documentRow["filename"]); ?></span>
                        <a href="online-admission-document.php?documentid=<?php echo oa_esc($documentRow["documentid"]); ?>" class="oa-secondary"><i class="fa fa-download"></i> Download</a>
                    </article>
                    <?php } ?>
                    <?php if($downloadUrl !== ""){ ?>
                    <article class="oa-document-card">
                        <strong>Admission Form PDF</strong>
                        <span>Download a copy of your completed online admission form.</span>
                        <a href="<?php echo oa_esc($downloadUrl); ?>" class="oa-secondary"><i class="fa fa-download"></i> Download</a>
                    </article>
                    <?php } ?>
                </div>
                <?php } ?>
                </div>
            </section>

            <section class="oa-card">
                <div class="oa-section-head">
                    <h2>Admission Status</h2>
                </div>
                <div class="oa-status-guide">
                    <article><strong>Draft</strong><span>Not submitted.</span></article>
                    <article><strong>Submitted</strong><span>Received by school.</span></article>
                    <article><strong>Needs Attention</strong><span>Update required.</span></article>
                    <article><strong>Reviewed</strong><span>Checked by school.</span></article>
                </div>
            </section>

            <?php if($paymentEnabled){ ?>
            <section class="oa-card">
                <div class="oa-section-head">
                    <h2>Admission Payment</h2>
                </div>

                <?php if(!$postedStudent){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Verify your posting to see your payment details.</div>
                <?php }else{ ?>
                <div class="oa-payment-summary">
                    <article>
                        <span>Admission Fee</span>
                        <strong><?php echo oa_esc($paymentEnabled ? oa_money($paymentSetting["feeamount"], $paymentSetting["currency"]) : "Not configured"); ?></strong>
                    </article>
                    <article>
                        <span>Payment Stage</span>
                        <strong><?php echo oa_esc(ucwords(str_replace("_", " ", $paymentRequiredStatus))); ?></strong>
                    </article>
                    <article>
                        <span>Latest Payment</span>
                        <strong><?php echo oa_esc($paymentPaid ? "Paid and confirmed" : ($latestPayment ? online_admission_payment_status_label($latestPayment["status"]) : "Not started")); ?></strong>
                    </article>
                </div>

                <?php if(trim((string)$paymentSetting["note"]) !== ""){ ?>
                <p class="oa-payment-note"><?php echo oa_esc($paymentSetting["note"]); ?></p>
                <?php } ?>

                <?php if($paymentPaid){ ?>
                <div class="oa-payment-state oa-payment-state--success">Payment completed successfully.</div>
                <div class="oa-payment-meta">
                    <span><strong>Reference:</strong> <?php echo oa_esc($successfulPayment["reference"]); ?></span>
                    <span><strong>Paid:</strong> <?php echo oa_esc(oa_format_datetime($successfulPayment["paidat"])); ?></span>
                    <span class="<?php echo oa_payment_status_class($successfulPayment["status"]); ?>"><?php echo oa_esc(online_admission_payment_status_label($successfulPayment["status"])); ?></span>
                </div>
                <?php }elseif(!$paymentEnabled){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Online payment is not enabled.</div>
                <?php }elseif(!$paymentReady){ ?>
                <div class="oa-payment-state oa-payment-state--warning">Payment setup is not ready yet.</div>
                <?php }elseif(!$paymentAllowed){ ?>
                <?php if(!$application){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Verify posting to see payment.</div>
                <?php }elseif(strtolower((string)$application["status"]) === "draft"){ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Payment is not open for this stage yet.</div>
                <?php }elseif(strtolower((string)$application["status"]) === "needs_attention"){ ?>
                <div class="oa-payment-state oa-payment-state--warning">Update your form before payment can continue.</div>
                <?php }else{ ?>
                <div class="oa-payment-state oa-payment-state--neutral">Payment is not open yet.</div>
                <?php } ?>
                <?php }else{ ?>
                <div class="oa-payment-state oa-payment-state--info">Pay first, then sign in again with your token.</div>
                <?php if($latestPayment && trim((string)$latestPayment["reference"]) !== ""){ ?>
                <div class="oa-payment-meta">
                    <span><strong>Reference:</strong> <?php echo oa_esc($latestPayment["reference"]); ?></span>
                    <span class="<?php echo oa_payment_status_class($latestPayment["status"]); ?>"><?php echo oa_esc(online_admission_payment_status_label($latestPayment["status"])); ?></span>
                </div>
                <?php } ?>
                <div class="oa-form-actions oa-form-actions--stacked">
                    <a href="<?php echo oa_esc($paymentContinueUrl); ?>" class="oa-submit"><i class="fa fa-credit-card"></i> <?php echo ($latestPayment && strtolower(trim((string)$latestPayment["status"])) === "initialized") ? "Continue Paystack Checkout" : "Pay with Paystack"; ?></a>
                    <?php if($latestPayment && strtolower(trim((string)$latestPayment["status"])) !== "success"){ ?>
                    <a href="online-admission-paystack-init.php" class="oa-secondary"><i class="fa fa-refresh"></i> Start Fresh Payment</a>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php } ?>
            </section>
            <?php } ?>

        </aside>
        <?php } ?>
    </div>
    <?php } ?>

    <section class="oa-card oa-card--stacked oa-help-card" id="help-request">
        <div class="oa-help-card__intro">
            <div>
                <span class="oa-kicker oa-kicker--dark"><i class="fa fa-life-ring"></i> Admission Support</span>
                <h2>Need Help?</h2>
                <p>If you are stuck with verification, payment, token, or the admission form, send a message to the admission office.</p>
            </div>
            <div class="oa-help-card__actions">
                <a href="#help-request" class="oa-submit"><i class="fa fa-comments"></i> Open Help Form</a>
                <a href="#admission-top" class="oa-secondary oa-help-close"><i class="fa fa-times"></i> Hide Help</a>
            </div>
        </div>
        <form method="post" action="online-admission.php#help-request" class="oa-form oa-form--verify oa-help-form">
            <div class="oa-grid oa-grid--two">
                <div class="oa-field">
                    <label for="help_studentname">Full Name</label>
                    <input type="text" id="help_studentname" name="help_studentname" value="<?php echo oa_esc($helpForm["studentname"]); ?>" required>
                </div>
                <div class="oa-field">
                    <label for="help_contactphone">Contact Number</label>
                    <input type="tel" id="help_contactphone" name="help_contactphone" value="<?php echo oa_esc($helpForm["contactphone"]); ?>" required>
                </div>
                <div class="oa-field">
                    <label for="help_beceindexnumber">BECE Index Number</label>
                    <input type="text" id="help_beceindexnumber" name="help_beceindexnumber" value="<?php echo oa_esc($helpForm["beceindexnumber"]); ?>">
                </div>
                <div class="oa-field">
                    <label for="help_admissionyear">Admission Year</label>
                    <input type="text" id="help_admissionyear" name="help_admissionyear" value="<?php echo oa_esc($helpForm["admissionyear"]); ?>">
                </div>
                <div class="oa-field oa-field--full">
                    <label for="help_verificationtoken">Token</label>
                    <input type="text" id="help_verificationtoken" name="help_verificationtoken" value="<?php echo oa_esc($helpForm["verificationtoken"]); ?>" placeholder="Optional">
                </div>
                <div class="oa-field oa-field--full">
                    <label for="helpmessage">Message</label>
                    <textarea id="helpmessage" name="helpmessage" rows="4" required><?php echo oa_esc($helpForm["helpmessage"]); ?></textarea>
                </div>
            </div>
            <div class="oa-form-actions">
                <button type="submit" name="submit_help_request" class="oa-submit"><i class="fa fa-paper-plane"></i> Send Help Request</button>
            </div>
        </form>
    </section>
</div>

<script>
(function () {
    var input = document.getElementById("admissionphoto");
    var preview = document.getElementById("oa-photo-preview");
    if (!input || !preview) { return; }
    input.addEventListener("change", function () {
        var file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function (event) {
            preview.src = event.target && event.target.result ? event.target.result : preview.src;
        };
        reader.readAsDataURL(file);
    });
}());
</script>
</body>
</html>

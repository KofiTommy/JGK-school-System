<?php
session_start();
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function oa_download_redirect($message){
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = "<div class=\"oa-alert oa-alert--warning\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    header("location:online-admission.php");
    exit();
}

if(!isset($_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) ||
    (string)$_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] !== "1" ||
    trim((string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) === ""){
    oa_download_redirect("Log in with your token first before downloading your admission form.");
}

$application = online_admission_get_application_by_id($con, (string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]);
if(!$application){
    oa_download_redirect("We could not find that admission record anymore. Please log in again.");
}

$postedStudent = online_admission_get_posted_student_by_id($con, (string)$application["branchid"], (string)$application["postingid"]);
if(!$postedStudent){
    oa_download_redirect("The posted student record linked to this admission form is no longer available.");
}

$payment = online_admission_get_successful_payment_by_application($con, (string)$application["applicationid"]);
$assignedHouse = online_admission_application_assigned_house($con, $application);

$school = array(
    "name" => "School Management System",
    "address" => "",
    "location" => "",
    "telephone1" => "",
    "telephone2" => "",
    "logo" => ""
);
$branchIdEsc = mysqli_real_escape_string($con, (string)$application["branchid"]);
$schoolRes = mysqli_query($con, "SELECT cm.fullname, br.address, br.location, br.telephone1, br.telephone2, cm.logo AS company_logo, br.logo AS branch_logo
    FROM tblbranch br
    INNER JOIN tblcompany cm ON br.companyid=cm.companyid
    WHERE br.branchid='$branchIdEsc'
    LIMIT 1");
if($schoolRes && $schoolRow = mysqli_fetch_array($schoolRes, MYSQLI_ASSOC)){
    $school["name"] = trim((string)$schoolRow["fullname"]) !== "" ? trim((string)$schoolRow["fullname"]) : $school["name"];
    $school["address"] = trim((string)$schoolRow["address"]);
    $school["location"] = trim((string)$schoolRow["location"]);
    $school["telephone1"] = trim((string)$schoolRow["telephone1"]);
    $school["telephone2"] = trim((string)$schoolRow["telephone2"]);
    $logoFile = trim((string)$schoolRow["company_logo"]);
    if($logoFile === ""){
        $logoFile = trim((string)$schoolRow["branch_logo"]);
    }
    if($logoFile !== ""){
        foreach(array(
            "images/logo/".$logoFile,
            "logo/".$logoFile,
            $logoFile
        ) as $candidate){
            $fullPath = __DIR__.DIRECTORY_SEPARATOR.str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $candidate);
            if(file_exists($fullPath)){
                $school["logo"] = $fullPath;
                break;
            }
        }
    }
}

$candidateName = trim(online_admission_candidate_name($application));
if($candidateName === ""){
    $candidateName = trim((string)$postedStudent["firstname"]." ".(string)$postedStudent["othernames"]." ".(string)$postedStudent["surname"]);
}

require_once("fpdf181/fpdf.php");

class OnlineAdmissionPdf extends FPDF {
    function sectionHeading($title){
        $this->Ln(4);
        $this->SetFillColor(237, 244, 250);
        $this->SetDrawColor(215, 228, 240);
        $this->SetTextColor(18, 48, 70);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 9, $title, 1, 1, 'L', true);
    }

    function detailRow($label, $value){
        $label = trim((string)$label);
        $value = trim((string)$value);
        if($value === ""){
            $value = "Not available";
        }
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(70, 86, 106);
        $this->Cell(48, 7, $label, 0, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(23, 49, 75);
        $this->MultiCell(0, 7, $value, 0, 'L');
    }
}

function oa_pdf_value($value, $fallback = "Not available"){
    $value = trim((string)$value);
    return $value !== "" ? $value : $fallback;
}

function oa_pdf_datetime($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date("d M Y, g:i a", $timestamp);
}

function oa_pdf_date($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date("d M Y", $timestamp);
}

$pdf = new OnlineAdmissionPdf("P", "mm", "A4");
$pdf->SetMargins(14, 12, 14);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 14);

if($school["logo"] !== ""){
    $ext = strtolower(pathinfo($school["logo"], PATHINFO_EXTENSION));
    if(in_array($ext, array("jpg", "jpeg", "png"), true)){
        $pdf->Image($school["logo"], 14, 12, 20);
    }
}

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(17, 58, 72);
$pdf->Cell(0, 8, $school["name"], 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(76, 95, 112);
$addressLine = trim($school["address"].($school["location"] !== "" ? ", ".$school["location"] : ""));
if($addressLine !== ""){
    $pdf->Cell(0, 6, $addressLine, 0, 1, 'C');
}
$phoneLine = trim($school["telephone1"].($school["telephone2"] !== "" ? "  ".$school["telephone2"] : ""));
if($phoneLine !== ""){
    $pdf->Cell(0, 6, "Tel: ".$phoneLine, 0, 1, 'C');
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(18, 48, 70);
$pdf->Cell(0, 8, "Online Admission Form Summary", 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(99, 115, 133);
$pdf->Cell(0, 6, "Generated on ".date("d M Y, g:i a"), 0, 1, 'C');

$pdf->sectionHeading("Application Overview");
$pdf->detailRow("Student Name", $candidateName);
$pdf->detailRow("Current Status", online_admission_status_label($application["status"]));
$pdf->detailRow("Admission Year", oa_pdf_value($postedStudent["admissionyear"]));
$pdf->detailRow("Submitted On", oa_pdf_datetime($application["submittedat"]));
$pdf->detailRow("Last Updated", oa_pdf_datetime($application["updatedat"]));
$pdf->detailRow("Reviewed On", oa_pdf_datetime($application["revieweddatetime"]));
$pdf->detailRow("Verification / Resume Token", oa_pdf_value($application["verificationtoken"]));

$pdf->sectionHeading("Student Details");
$pdf->detailRow("BECE Index Number", oa_pdf_value($postedStudent["beceindexnumber"]));
$pdf->detailRow("Date of Birth", oa_pdf_date($postedStudent["birthdate"]));
$pdf->detailRow("Gender", oa_pdf_value($postedStudent["gender"]));
$pdf->detailRow("Programme", oa_pdf_value($postedStudent["offeredprogram"], "To be confirmed"));
$pdf->detailRow("Class", oa_pdf_value($postedStudent["offeredclass"], "To be assigned"));
$pdf->detailRow("Assigned House", oa_pdf_value($assignedHouse && trim((string)$assignedHouse["housename"]) !== "" ? $assignedHouse["housename"] : ""));
$pdf->detailRow("Residence Type", oa_pdf_value($application["residencetype"] !== "" ? $application["residencetype"] : $postedStudent["residentialstatus"]));
$pdf->detailRow("Student Mobile", oa_pdf_value($application["mobile"]));
$pdf->detailRow("Email Address", oa_pdf_value($application["email"]));

$pdf->sectionHeading("Address Information");
$pdf->detailRow("Hometown", oa_pdf_value($application["hometown"]));
$pdf->detailRow("Postal Address", oa_pdf_value($application["postaladdress"]));
$pdf->detailRow("Home Address", oa_pdf_value($application["homeaddress"]));
$pdf->detailRow("Religion", oa_pdf_value($application["religion"]));

$pdf->sectionHeading("Parent / Guardian");
$pdf->detailRow("Name", oa_pdf_value($application["guardianname"]));
$pdf->detailRow("Relationship", oa_pdf_value($application["guardianrelationship"]));
$pdf->detailRow("Contact Number", oa_pdf_value($application["guardiancontact"]));

$pdf->sectionHeading("Additional Notes");
$pdf->detailRow("Medical Notes", oa_pdf_value($application["medicalnotes"]));
$pdf->detailRow("Student Note", oa_pdf_value($application["studentnote"]));
$pdf->detailRow("School Review Note", oa_pdf_value($application["reviewnote"]));

$pdf->sectionHeading("Payment");
if($payment){
    $pdf->detailRow("Payment Status", "Paid and confirmed");
    $pdf->detailRow("Reference", oa_pdf_value($payment["reference"]));
    $pdf->detailRow("Amount", strtoupper(trim((string)$payment["currency"])) !== "" ? strtoupper(trim((string)$payment["currency"]))." ".number_format((float)$payment["amount"], 2) : number_format((float)$payment["amount"], 2));
    $pdf->detailRow("Paid On", oa_pdf_datetime($payment["paidat"]));
}else{
    $pdf->detailRow("Payment Status", "No confirmed online payment on this form");
}

$filenameSlug = preg_replace('/[^A-Za-z0-9]+/', '-', $candidateName);
$filenameSlug = trim((string)$filenameSlug, '-');
if($filenameSlug === ""){
    $filenameSlug = "admission-form";
}
$pdf->Output("D", strtolower($filenameSlug)."-online-admission.pdf");
exit();
?>

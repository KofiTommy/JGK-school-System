<?php
session_start();
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function oa_letter_redirect($message){
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = "<div class=\"oa-alert oa-alert--warning\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    header("location:online-admission.php");
    exit();
}

function oa_letter_admin_redirect($message){
    $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = "<div class=\"rs-alert rs-alert--warning\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    header("location:online-admission-admin.php#applications");
    exit();
}

$officeApplicationId = trim((string)(isset($_GET["applicationid"]) ? $_GET["applicationid"] : ""));
$officePrint = online_admission_is_admin() && $officeApplicationId !== "";

if(!$officePrint){
    if(online_admission_is_admin()){
        oa_letter_admin_redirect("Choose a student application before printing an admission letter.");
    }
    oa_letter_redirect("Admission letters are printed and issued by the school office when students report. You can still download your other admission documents from this portal.");
}

$application = online_admission_get_application_by_id($con, $officeApplicationId);
if(!$application){
    oa_letter_admin_redirect("We could not find that admission record anymore.");
}

$adminBranchId = isset($_SESSION["BRANCHID"]) ? trim((string)$_SESSION["BRANCHID"]) : "";
if($adminBranchId !== "" && (string)$application["branchid"] !== $adminBranchId){
    oa_letter_admin_redirect("That admission record does not belong to your current branch.");
}

if(!online_admission_application_is_submitted($application)){
    oa_letter_admin_redirect("Submit the student's online admission form before printing the admission letter.");
}

$postedStudent = online_admission_get_posted_student_by_id($con, (string)$application["branchid"], (string)$application["postingid"]);
if(!$postedStudent){
    oa_letter_admin_redirect("The posted student record linked to this admission form is no longer available.");
}

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
$programme = trim((string)$postedStudent["offeredprogram"]) !== "" ? trim((string)$postedStudent["offeredprogram"]) : "To be confirmed";
$className = trim((string)$postedStudent["offeredclass"]) !== "" ? trim((string)$postedStudent["offeredclass"]) : "To be assigned";
$residence = trim((string)$application["residencetype"]) !== "" ? trim((string)$application["residencetype"]) : trim((string)$postedStudent["residentialstatus"]);
if($residence === ""){
    $residence = "To be confirmed";
}
$assignedHouse = online_admission_application_assigned_house($con, $application);
$assignedHouseName = ($assignedHouse && trim((string)$assignedHouse["housename"]) !== "") ? trim((string)$assignedHouse["housename"]) : "To be announced";
$admissionYear = trim((string)$postedStudent["admissionyear"]);
$token = trim((string)$application["verificationtoken"]);
$submittedAt = trim((string)$application["submittedat"]);
$submittedText = $submittedAt !== "" ? date("d M Y", strtotime($submittedAt)) : date("d M Y");

require_once("fpdf181/fpdf.php");

$fpdiAutoload = __DIR__.DIRECTORY_SEPARATOR."third_party".DIRECTORY_SEPARATOR."fpdi".DIRECTORY_SEPARATOR."src".DIRECTORY_SEPARATOR."autoload.php";
if(file_exists($fpdiAutoload)){
    require_once($fpdiAutoload);
}

function oa_letter_pdf_text($text){
    $text = trim((string)$text);
    if($text === ""){
        return "";
    }
    if(function_exists("iconv")){
        $converted = @iconv("UTF-8", "windows-1252//TRANSLIT//IGNORE", $text);
        if($converted !== false){
            return $converted;
        }
    }
    return preg_replace('/[^\x20-\x7E]/', '', $text);
}

function oa_letter_body_text($pdf, $text, $fontFamily = "Arial", $fontStyle = "", $fontSize = 12, $lineHeight = 7, $textColor = array(45, 58, 74)){
    $pdf->SetFont($fontFamily, $fontStyle, $fontSize);
    $pdf->SetTextColor((int)$textColor[0], (int)$textColor[1], (int)$textColor[2]);
    $pdf->MultiCell(0, $lineHeight, oa_letter_pdf_text($text), 0, "L");
    $pdf->Ln(1);
}

function oa_letter_find_template_pdf(){
    $candidates = array(
        __DIR__.DIRECTORY_SEPARATOR."Admission_letter-Ayisec.pdf",
        __DIR__.DIRECTORY_SEPARATOR."Admission_Letter-Ayisec.pdf",
        __DIR__.DIRECTORY_SEPARATOR."admission_letter-ayisec.pdf"
    );
    foreach($candidates as $candidate){
        if(file_exists($candidate)){
            return $candidate;
        }
    }
    return "";
}

function oa_letter_academic_year_label($year){
    $year = trim((string)$year);
    if($year !== "" && ctype_digit($year)){
        return $year."/".((int)$year + 1);
    }
    $currentYear = (int)date("Y");
    return $currentYear."/".($currentYear + 1);
}

function oa_letter_gender_short($application, $postedStudent = null){
    $gender = online_admission_application_gender($application, $postedStudent);
    $gender = strtolower(trim((string)$gender));
    if($gender === "male"){
        return "M";
    }
    if($gender === "female"){
        return "F";
    }
    return "-";
}

$academicYearLabel = oa_letter_academic_year_label($admissionYear);
$genderShort = oa_letter_gender_short($application, $postedStudent);
$beceIndexNumber = trim((string)$postedStudent["beceindexnumber"]);
$ourReferenceNumber = "ADM/".$academicYearLabel;
$yourReferenceNumber = $token !== "" ? $token : $beceIndexNumber;
$letterDateLong = date("d F Y");
$schoolNameForLetter = trim($school["name"]) !== "" ? trim($school["name"]) : "the school";
$programmeText = (trim((string)$programme) !== "" && strtolower(trim((string)$programme)) !== "to be confirmed")
    ? trim((string)$programme)
    : "the programme assigned to you";
$residenceValueLine = "Residence Status: ".$residence;
$houseValueLine = "Assigned House: ".$assignedHouseName;
$residenceSentence = "Your current placement is recorded as ".$residence." status.";
if($assignedHouseName !== "" && strtolower($assignedHouseName) !== "to be announced"){
    $residenceSentence .= " Your assigned house is ".$assignedHouseName.".";
}
$classSentence = (trim((string)$className) !== "" && strtolower(trim((string)$className)) !== "to be assigned")
    ? " Your reporting class is ".$className."."
    : "";
$templatePdfPath = oa_letter_find_template_pdf();
$usedTemplateLetter = false;

if($templatePdfPath !== "" && class_exists("\\setasign\\Fpdi\\Fpdi")){
    try{
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile($templatePdfPath);
        if($pageCount >= 1){
            $templateId = $pdf->importPage(1);
            $templateSize = $pdf->getTemplateSize($templateId);
            $pageWidth = isset($templateSize["width"]) ? (float)$templateSize["width"] : 210.0;
            $pageHeight = isset($templateSize["height"]) ? (float)$templateSize["height"] : 297.0;
            $pageOrientation = isset($templateSize["orientation"]) ? $templateSize["orientation"] : "P";
            $scaleX = $pageWidth / 210;
            $scaleY = $pageHeight / 297;
            $X = function($value) use ($scaleX){ return $value * $scaleX; };
            $Y = function($value) use ($scaleY){ return $value * $scaleY; };

            $pdf->AddPage($pageOrientation, array($pageWidth, $pageHeight));
            $pdf->useTemplate($templateId, 0, 0, $pageWidth, $pageHeight, true);
            $pdf->SetMargins(0, 0, 0);

            $whiteBlock = function($x, $y, $w, $h) use ($pdf, $X, $Y){
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($X($x), $Y($y), $X($w), $Y($h), "F");
            };

            $firstParagraph = "I wish to inform you that following your success in the Basic Education Certificate Examination (BECE) and the placement made through the Computerised School Selection and Placement System (CSSPS), you have been offered admission to ".$schoolNameForLetter." to pursue a three-year course programme in:";
            $secondParagraph = "Please find attached the prospectus and all other admission documents for your ward. Download them from your portal, complete the required forms, and bring them when reporting to school.";
            $thirdParagraph = "Forms are to be completed and returned with a valid National Health Insurance Scheme (NHIS) card. Students are not allowed to bring phones to school.";
            $fourthParagraph = trim($classSentence." Keep this letter safely and present it whenever the school requests it. Accept our congratulations.");

            $whiteBlock(12, 80.9, 60, 7.0);
            $whiteBlock(70, 80.9, 76, 7.0);
            $whiteBlock(147, 80.9, 50, 7.0);
            $whiteBlock(34, 91, 145, 8);
            $whiteBlock(18, 100, 136, 7);
            $whiteBlock(156, 100, 22, 7);
            $whiteBlock(18, 108, 118, 7);
            $whiteBlock(18, 113, 172, 28);
            $whiteBlock(44, 141, 120, 8);
            $whiteBlock(18, 152, 172, 16);
            $whiteBlock(18, 170, 172, 16);
            $whiteBlock(18, 188, 172, 18);
            $whiteBlock(18, 206, 125, 13);
            $whiteBlock(136, 233, 50, 22);

            $pdf->SetTextColor(38, 38, 38);
            $pdf->SetFont("Times", "", 8.6);
            $pdf->SetXY($X(16), $Y(83.0));
            $pdf->Cell($X(56), $Y(5), oa_letter_pdf_text("OUR REF. NO.: ".$ourReferenceNumber), 0, 0, "L");
            $pdf->SetXY($X(73), $Y(83.0));
            $pdf->Cell($X(70), $Y(5), oa_letter_pdf_text("YOUR REF. NO.: ".$yourReferenceNumber), 0, 0, "L");
            $pdf->SetXY($X(150), $Y(83.0));
            $pdf->Cell($X(40), $Y(5), oa_letter_pdf_text("Date: ".$letterDateLong), 0, 0, "L");

            $pdf->SetFont("Times", "B", 11);
            $pdf->SetXY($X(34), $Y(92));
            $pdf->Cell($X(145), $Y(6), oa_letter_pdf_text("OFFER OF ADMISSION ".$academicYearLabel." ACADEMIC YEAR"), 0, 0, "C");

            $pdf->SetFont("Times", "B", 10);
            $pdf->SetXY($X(18), $Y(103));
            $pdf->Cell($X(136), $Y(5), oa_letter_pdf_text("CANDIDATE'S NAME: ".$candidateName), 0, 0, "L");
            $pdf->SetXY($X(156), $Y(103));
            $pdf->Cell($X(20), $Y(5), oa_letter_pdf_text("M/F: ".$genderShort), 0, 0, "L");

            $pdf->SetXY($X(18), $Y(111));
            $pdf->Cell($X(118), $Y(5), oa_letter_pdf_text("INDEX NUMBER: ".$beceIndexNumber), 0, 0, "L");

            $pdf->SetFont("Times", "", 10);
            $pdf->SetXY($X(18), $Y(125));
            $pdf->MultiCell($X(170), $Y(5.8), oa_letter_pdf_text($firstParagraph), 0, "L");

            $pdf->SetFont("Times", "B", 10.5);
            $pdf->SetXY($X(44), $Y(152));
            $pdf->Cell($X(120), $Y(5), oa_letter_pdf_text(strtoupper($programmeText)), 0, 0, "C");

            $pdf->SetFont("Times", "", 10);
            $pdf->SetXY($X(18), $Y(164));
            $pdf->MultiCell($X(170), $Y(5.8), oa_letter_pdf_text($secondParagraph), 0, "L");

            $pdf->SetXY($X(18), $Y(182));
            $pdf->MultiCell($X(170), $Y(5.8), oa_letter_pdf_text($thirdParagraph), 0, "L");

            $pdf->SetXY($X(18), $Y(200));
            $pdf->MultiCell($X(170), $Y(5.8), oa_letter_pdf_text($fourthParagraph), 0, "L");

            $pdf->SetFont("Times", "B", 10);
            $pdf->SetXY($X(18), $Y(208));
            $pdf->Cell($X(110), $Y(5), oa_letter_pdf_text($residenceValueLine), 0, 1, "L");
            $pdf->SetX($X(18));
            $pdf->Cell($X(120), $Y(5), oa_letter_pdf_text($houseValueLine), 0, 1, "L");

            $pdf->SetFont("Times", "", 10);
            $pdf->SetXY($X(140), $Y(235));
            $pdf->Cell($X(34), $Y(5), oa_letter_pdf_text("Signed"), 0, 1, "L");
            $pdf->SetFont("Times", "B", 9.5);
            $pdf->SetX($X(140));
            $pdf->Cell($X(44), $Y(5), oa_letter_pdf_text("Emmanuel O-Frimpong Adjorlolo"), 0, 1, "L");
            $pdf->SetFont("Times", "", 10);
            $pdf->SetX($X(140));
            $pdf->Cell($X(40), $Y(5), oa_letter_pdf_text("HEADMASTER"), 0, 1, "L");

            $usedTemplateLetter = true;
        }
    } catch(Exception $e){
        $usedTemplateLetter = false;
    }
}

if(!$usedTemplateLetter){
    $pdf = new FPDF("P", "mm", "A4");
    $pdf->SetMargins(18, 14, 18);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 16);

    if($school["logo"] !== ""){
        $ext = strtolower(pathinfo($school["logo"], PATHINFO_EXTENSION));
        if(in_array($ext, array("jpg", "jpeg", "png"), true)){
            $pdf->Image($school["logo"], 18, 14, 22);
        }
    }

    $pdf->SetFont('Arial', 'B', 17);
    $pdf->SetTextColor(17, 58, 72);
    $pdf->Cell(0, 8, oa_letter_pdf_text($school["name"]), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(92, 108, 124);
    $addressLine = trim($school["address"].($school["location"] !== "" ? ", ".$school["location"] : ""));
    if($addressLine !== ""){
        $pdf->Cell(0, 6, oa_letter_pdf_text($addressLine), 0, 1, 'C');
    }
    $phoneLine = trim($school["telephone1"].($school["telephone2"] !== "" ? "  ".$school["telephone2"] : ""));
    if($phoneLine !== ""){
        $pdf->Cell(0, 6, oa_letter_pdf_text("Tel: ".$phoneLine), 0, 1, 'C');
    }

    $pdf->Ln(6);
    $pdf->SetDrawColor(212, 223, 233);
    $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 15);
    $pdf->SetTextColor(18, 48, 70);
    $pdf->Cell(0, 8, oa_letter_pdf_text("Admission Letter"), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(90, 107, 123);
    $pdf->Cell(0, 6, oa_letter_pdf_text("Generated on ".date("d M Y, g:i a")), 0, 1, 'C');

    $pdf->Ln(8);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(45, 58, 74);
    $pdf->Cell(0, 7, oa_letter_pdf_text("Date: ".$submittedText), 0, 1, 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 7, oa_letter_pdf_text($candidateName), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, oa_letter_pdf_text("BECE Index Number: ".$beceIndexNumber), 0, 1, 'L');
    $pdf->Cell(0, 7, oa_letter_pdf_text("Admission Year: ".($admissionYear !== "" ? $admissionYear : "Current Year")), 0, 1, 'L');

    $pdf->Ln(6);
    oa_letter_body_text($pdf, "Dear ".$candidateName.".");
    oa_letter_body_text($pdf, "Congratulations. This letter confirms your admission processing with ".$school["name"]." for the ".$admissionYear." academic year through the online admission portal.");
    oa_letter_body_text($pdf, "You have been considered for ".$programme.". Your reporting class is currently recorded as ".$className.".");
    oa_letter_body_text($pdf, $residenceValueLine, 'Arial', 'B');
    oa_letter_body_text($pdf, $houseValueLine, 'Arial', 'B');
    oa_letter_body_text($pdf, "Please keep this letter together with your online admission summary and any other school documents made available on your portal. You may be asked to present them during reporting or further admission checks.");

    $pdf->Ln(3);
    $pdf->SetFillColor(240, 246, 251);
    $pdf->SetDrawColor(213, 224, 234);
    $pdf->SetTextColor(23, 49, 75);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 9, oa_letter_pdf_text("Admission Details"), 1, 1, 'L', true);

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(55, 8, oa_letter_pdf_text("Programme"), 1, 0, 'L');
    $pdf->Cell(0, 8, oa_letter_pdf_text($programme), 1, 1, 'L');
    $pdf->Cell(55, 8, oa_letter_pdf_text("Class"), 1, 0, 'L');
    $pdf->Cell(0, 8, oa_letter_pdf_text($className), 1, 1, 'L');
    $pdf->Cell(55, 8, oa_letter_pdf_text("Residence"), 1, 0, 'L');
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, oa_letter_pdf_text($residence), 1, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(55, 8, oa_letter_pdf_text("Assigned House"), 1, 0, 'L');
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, oa_letter_pdf_text($assignedHouseName), 1, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(55, 8, oa_letter_pdf_text("Application Status"), 1, 0, 'L');
    $pdf->Cell(0, 8, oa_letter_pdf_text(online_admission_status_label($application["status"])), 1, 1, 'L');
    $pdf->Cell(55, 8, oa_letter_pdf_text("Verification Token"), 1, 0, 'L');
    $pdf->Cell(0, 8, oa_letter_pdf_text($token !== "" ? $token : "Not available"), 1, 1, 'L');

    $pdf->Ln(7);
    oa_letter_body_text($pdf, "We look forward to welcoming you. For support, contact the school using the details on the portal or the help options provided on the admission page.");

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, oa_letter_pdf_text("Admissions Office"), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, oa_letter_pdf_text($school["name"]), 0, 1, 'L');
}

$filenameSlug = preg_replace('/[^A-Za-z0-9]+/', '-', $candidateName);
$filenameSlug = trim((string)$filenameSlug, '-');
if($filenameSlug === ""){
    $filenameSlug = "admission-letter";
}
$pdf->Output("I", strtolower($filenameSlug)."-admission-letter.pdf");
exit();
?>

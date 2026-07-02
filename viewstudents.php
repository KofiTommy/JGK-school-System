<?php
session_start();
$_SESSION['Message']="";

include_once("check-login.php");
include_once("user-management-utils.php");

if(!function_exists('student_directory_is_headmaster_viewer')){
function student_directory_is_headmaster_viewer(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Headmaster';
}
}

if(!function_exists('student_directory_can_access')){
function student_directory_can_access(){
    if(!isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])){
        return false;
    }

    if($_SESSION['ACCESSLEVEL'] === 'administrator' && in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true)){
        return true;
    }

    if($_SESSION['ACCESSLEVEL'] === 'user' && in_array($_SESSION['SYSTEMTYPE'], array('User', 'Headmaster'), true)){
        return true;
    }

    return false;
}
}

if(!function_exists('student_directory_can_manage_records')){
function student_directory_can_manage_records(){
    return !student_directory_is_headmaster_viewer();
}
}

if(!function_exists('student_directory_branch_filter_sql')){
function student_directory_branch_filter_sql($con, $alias = 'su'){
    if(!student_directory_is_headmaster_viewer()){
        return '';
    }

    $branchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : '';
    if($branchId === ''){
        return '';
    }

    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'su';
    }

    return " AND ".$alias.".branchid='".mysqli_real_escape_string($con, $branchId)."'";
}
}

if(!student_directory_can_access()){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

if(!function_exists('normalize_gender_label')){
function normalize_gender_label($gender){
    $g = strtoupper(trim((string)$gender));
    if(in_array($g, array('M','MALE','BOY','B'))){ return 'Boy'; }
    if(in_array($g, array('F','FEMALE','GIRL','G'))){ return 'Girl'; }
    return '';
}
}

if(!function_exists('normalize_residence_label')){
function normalize_residence_label($residence){
    $r = strtoupper(trim((string)$residence));
    if($r==='DAY' || $r==='D'){ return 'Day'; }
    if($r==='BOARDING' || $r==='BOARDER' || $r==='B'){ return 'Boarding'; }
    return '';
}
}

if(!function_exists('extract_programme_label')){
function extract_programme_label($className){
    $cn = strtoupper(trim((string)$className));
    if($cn===''){ return 'Others'; }

    // Normalize punctuation/spacing so variants like "3 Gen. Art1" collapse correctly.
    $norm = preg_replace('/[^A-Z0-9]+/', ' ', $cn);
    $norm = preg_replace('/([A-Z])([0-9])/', '$1 $2', $norm);
    $norm = preg_replace('/\s+/', ' ', trim($norm));

    if(preg_match('/\bGEN(?:ERAL)?\s*ARTS?\b/', $norm)){ return 'General Arts'; }
    if(preg_match('/\bBUS(?:INESS)?\b|\bBIZ\b/', $norm)){ return 'Business'; }
    if(preg_match('/\bVIS(?:UAL)?\s*ARTS?\b|\bV\s*ARTS?\b/', $norm)){ return 'Visual Arts'; }
    if(preg_match('/\bGEN(?:ERAL)?\s*SCI(?:ENCE)?\b|\bSCI(?:ENCE)?\b/', $norm)){ return 'General Science'; }
    if(preg_match('/\bTECH(?:NICAL)?\b/', $norm)){ return 'Technical'; }
    if(preg_match('/\bHOME\s*ECON(?:S|OMICS)?\b|\bH\s*E\b/', $norm)){ return 'Home Economics'; }
    if(preg_match('/\bAGRIC(?:ULTURE)?\b|\bAGRI\b/', $norm)){ return 'Agric Science'; }
    if(preg_match('/\bSTEM\b/', $norm)){ return 'STEM'; }
    if(preg_match('/\bLANG(?:UAGE)?\b/', $norm)){ return 'LANG'; }

    $clean = preg_replace('/\b(SHS|FORM)\s*[123]\b/i', '', (string)$className);
    $clean = preg_replace('/\s+[0-9]+\s*$/', '', $clean);
    $clean = trim(preg_replace('/\s+/', ' ', (string)$clean));
    if($clean===''){ return 'Others'; }
    return ucwords(strtolower($clean));
}
}

if(isset($_POST["print_batch_programme_summary"]))
{
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/print-error.log');
error_reporting(E_ALL);
include("dbstring.php");
include("company.php");
if(!file_exists(__DIR__.'/fpdf181/fpdf.php')){
    http_response_code(500);
    exit("Print setup error: PDF library file not found.");
}
require(__DIR__.'/fpdf181/fpdf.php');
$_BranchStudentFilter = student_directory_branch_filter_sql($con, 'su');

@$_BatchID=$_POST["print_batchid"];
if($_BatchID==""){
    http_response_code(400);
    exit("Print request error: missing batch.");
}

$_BatchName="";
$_SQL_BATCH=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchID' LIMIT 1");
if($_SQL_BATCH && $row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
    $_BatchName=$row_ba['batch'];
}

$_Counts = array();
$_Seen = array();
$_Unclassified = 0;

$_SQL_STUDENTS=mysqli_query($con,"
    SELECT DISTINCT su.userid,su.gender,su.residencetype,ce.class_name
    FROM tblsystemuser su
    INNER JOIN tblclass cl ON cl.userid=su.userid
    INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
    WHERE su.systemtype='Student'
      AND su.status='active'
      AND cl.status='active'
      AND cl.batchid='".mysqli_real_escape_string($con, $_BatchID)."'
      $_BranchStudentFilter
");
if($_SQL_STUDENTS){
    while($row=mysqli_fetch_array($_SQL_STUDENTS,MYSQLI_ASSOC)){
        $_key = $row['userid']."|".$row['class_name'];
        if(isset($_Seen[$_key])){ continue; }
        $_Seen[$_key] = 1;

        $_programme = extract_programme_label($row['class_name']);
        $_gender = normalize_gender_label($row['gender']);
        $_residence = normalize_residence_label($row['residencetype']);

        if(!isset($_Counts[$_programme])){
            $_Counts[$_programme] = array(
                "DayBoy" => 0,
                "DayGirl" => 0,
                "BoardingBoy" => 0,
                "BoardingGirl" => 0
            );
        }

        if($_gender==='' || $_residence===''){
            $_Unclassified++;
            continue;
        }

        if($_residence==="Day" && $_gender==="Boy"){ $_Counts[$_programme]["DayBoy"]++; }
        if($_residence==="Day" && $_gender==="Girl"){ $_Counts[$_programme]["DayGirl"]++; }
        if($_residence==="Boarding" && $_gender==="Boy"){ $_Counts[$_programme]["BoardingBoy"]++; }
        if($_residence==="Boarding" && $_gender==="Girl"){ $_Counts[$_programme]["BoardingGirl"]++; }
    }
}

if(count($_Counts)===0){
    $_Counts["Others"] = array("DayBoy"=>0,"DayGirl"=>0,"BoardingBoy"=>0,"BoardingGirl"=>0);
}

$_PreferredOrder = array("General Arts","Business","Visual Arts","General Science","Technical","Home Economics","Agric Science","STEM","LANG","Others");
$_Programmes = array();
foreach($_PreferredOrder as $_p){
    if(isset($_Counts[$_p])){ $_Programmes[] = $_p; }
}
foreach(array_keys($_Counts) as $_p){
    if(!in_array($_p, $_Programmes, true)){ $_Programmes[] = $_p; }
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true,15);
$pdf->SetFont('Arial','B',10);

$logoPath = "";
if(!empty($_Logo)){
    $candidate = "logo/".$_Logo;
    if(file_exists($candidate)){
        $logoPath = $candidate;
    }
}
if($logoPath=="" && file_exists("logo/logo.png")){
    $logoPath = "logo/logo.png";
}
if($logoPath=="" && file_exists("logo/logo.jpeg")){
    $logoPath = "logo/logo.jpeg";
}
if($logoPath!=""){
    $pdf->Image($logoPath,95,4,20);
}
$pdf->Ln(14);

$tableTotalWidth = 48+14+14+14+14+14+14+22;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($tableTotalWidth,7,strtoupper($_CompanyName),0,1,'C',true);
$pdf->SetFont('Arial','',9);
$pdf->Cell($tableTotalWidth,6,$_Address.", ".$_Location,0,1,'C',true);
$pdf->Cell($tableTotalWidth,6,'TEL:'. $_Telephone1. " ". $_Telephone2,0,1,'C',true);
$pdf->Ln(2);

$caption = "Total number of students in the school by programme";
$pdf->SetFont('Arial','B',9);
$pdf->Cell($tableTotalWidth,7,$caption,0,1,'L',true);
$pdf->SetFont('Arial','',8);
$pdf->Cell($tableTotalWidth,5,"Batch: ".$_BatchName,0,1,'L',true);
$pdf->Ln(2);

$wProg = 48;
$w = 14;
$wGrand = 22;
$hTop = 7;
$hSub = 7;

$x = $pdf->GetX();
$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg,$hTop+$hSub,'Programme',1,0,'L');
$pdf->Cell($w*2,$hTop,'Day',1,0,'C');
$pdf->Cell($w*2,$hTop,'Boarding',1,0,'C');
$pdf->Cell($w*2,$hTop,'Total',1,0,'C');
$pdf->Cell($wGrand,$hTop+$hSub,'Grand Total',1,1,'C');

$pdf->SetX($x+$wProg);
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,1,'C');

$pdf->SetFont('Arial','',8);
$_GrandAll = 0;
foreach($_Programmes as $_P){
    $_DayBoy = $_Counts[$_P]["DayBoy"];
    $_DayGirl = $_Counts[$_P]["DayGirl"];
    $_BoardBoy = $_Counts[$_P]["BoardingBoy"];
    $_BoardGirl = $_Counts[$_P]["BoardingGirl"];
    $_TotBoy = $_DayBoy + $_BoardBoy;
    $_TotGirl = $_DayGirl + $_BoardGirl;
    $_RowGrand = $_TotBoy + $_TotGirl;
    $_GrandAll += $_RowGrand;

    $pdf->Cell($wProg,7,$_P,1,0,'L');
    $pdf->Cell($w,7,($_DayBoy>0 ? $_DayBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_DayGirl>0 ? $_DayGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardBoy>0 ? $_BoardBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardGirl>0 ? $_BoardGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotBoy>0 ? $_TotBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotGirl>0 ? $_TotGirl : ''),1,0,'C');
    $pdf->Cell($wGrand,7,($_RowGrand>0 ? $_RowGrand : ''),1,1,'C');
}

$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg+(6*$w),7,'Grand Total',1,0,'R');
$pdf->Cell($wGrand,7,$_GrandAll,1,1,'C');

$pdf->Ln(8);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,'Print Date/Time: '.date("d/m/Y H:i:s"),0,1,'L');
if($_Unclassified>0){
    $pdf->Cell(0,6,'Unclassified (missing gender/residence): '.$_Unclassified,0,1,'L');
}

$__pdfName = 'students-programme-summary-batch.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
}

if(isset($_POST["print_programme_summary"]))
{
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/print-error.log');
error_reporting(E_ALL);
include("dbstring.php");
include("company.php");
if(!file_exists(__DIR__.'/fpdf181/fpdf.php')){
    http_response_code(500);
    exit("Print setup error: PDF library file not found.");
}
require(__DIR__.'/fpdf181/fpdf.php');
$_BranchStudentFilter = student_directory_branch_filter_sql($con, 'su');

@$_ClassentryID=$_POST["print_class_id"];
@$_BatchID=$_POST["print_batchid"];
if($_ClassentryID=="" || $_BatchID==""){
    http_response_code(400);
    exit("Print request error: missing class or batch.");
}

$_ClassName = "";
$_SQLGC=mysqli_query($con,"SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassentryID' LIMIT 1");
if($_SQLGC && $rowc=mysqli_fetch_array($_SQLGC,MYSQLI_ASSOC)){
    $_ClassName=$rowc["class_name"];
}

$_BatchName="";
$_SQL_BATCH=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchID' LIMIT 1");
if($_SQL_BATCH && $row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
    $_BatchName=$row_ba['batch'];
}

$_FormPrefix = "";
$_FormLabel = "Selected Form";
if(preg_match('/\b(SHS\s*[123])\b/i', $_ClassName, $mForm)){
    $_FormPrefix = strtoupper(trim($mForm[1]));
    if($_FormPrefix=="SHS 1"){ $_FormLabel = "Form One (1)"; }
    elseif($_FormPrefix=="SHS 2"){ $_FormLabel = "Form Two (2)"; }
    elseif($_FormPrefix=="SHS 3"){ $_FormLabel = "Form Three (3)"; }
    else{ $_FormLabel = $_FormPrefix; }
}

$_ClassFilter = "";
if($_FormPrefix!=""){
    $_ClassFilter = " AND UPPER(ce.class_name) LIKE '".mysqli_real_escape_string($con, $_FormPrefix)."%' ";
}else{
    $_ClassFilter = " AND ce.class_entryid='".mysqli_real_escape_string($con, $_ClassentryID)."' ";
}

$_Counts = array();
$_Seen = array();
$_Unclassified = 0;

$_SQL_STUDENTS=mysqli_query($con,"
    SELECT DISTINCT su.userid,su.gender,su.residencetype,ce.class_name
    FROM tblsystemuser su
    INNER JOIN tblclass cl ON cl.userid=su.userid
    INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
    WHERE su.systemtype='Student'
      AND su.status='active'
      AND cl.status='active'
      AND cl.batchid='".mysqli_real_escape_string($con, $_BatchID)."'
      $_ClassFilter
      $_BranchStudentFilter
");
if($_SQL_STUDENTS){
    while($row=mysqli_fetch_array($_SQL_STUDENTS,MYSQLI_ASSOC)){
        $_key = $row['userid']."|".$row['class_name'];
        if(isset($_Seen[$_key])){ continue; }
        $_Seen[$_key] = 1;

        $_programme = extract_programme_label($row['class_name']);
        $_gender = normalize_gender_label($row['gender']);
        $_residence = normalize_residence_label($row['residencetype']);

        if(!isset($_Counts[$_programme])){
            $_Counts[$_programme] = array(
                "DayBoy" => 0,
                "DayGirl" => 0,
                "BoardingBoy" => 0,
                "BoardingGirl" => 0
            );
        }

        if($_gender==='' || $_residence===''){
            $_Unclassified++;
            continue;
        }

        if($_residence==="Day" && $_gender==="Boy"){ $_Counts[$_programme]["DayBoy"]++; }
        if($_residence==="Day" && $_gender==="Girl"){ $_Counts[$_programme]["DayGirl"]++; }
        if($_residence==="Boarding" && $_gender==="Boy"){ $_Counts[$_programme]["BoardingBoy"]++; }
        if($_residence==="Boarding" && $_gender==="Girl"){ $_Counts[$_programme]["BoardingGirl"]++; }
    }
}

if(count($_Counts)===0){
    $_Counts["Others"] = array("DayBoy"=>0,"DayGirl"=>0,"BoardingBoy"=>0,"BoardingGirl"=>0);
}

$_PreferredOrder = array("General Arts","Business","Visual Arts","General Science","Technical","Home Economics","Agric Science","STEM","LANG","Others");
$_Programmes = array();
foreach($_PreferredOrder as $_p){
    if(isset($_Counts[$_p])){ $_Programmes[] = $_p; }
}
foreach(array_keys($_Counts) as $_p){
    if(!in_array($_p, $_Programmes, true)){ $_Programmes[] = $_p; }
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true,15);
$pdf->SetFont('Arial','B',10);

$logoPath = "";
if(!empty($_Logo)){
    $candidate = "logo/".$_Logo;
    if(file_exists($candidate)){
        $logoPath = $candidate;
    }
}
if($logoPath=="" && file_exists("logo/logo.png")){
    $logoPath = "logo/logo.png";
}
if($logoPath=="" && file_exists("logo/logo.jpeg")){
    $logoPath = "logo/logo.jpeg";
}
if($logoPath!=""){
    $pdf->Image($logoPath,95,4,20);
}
$pdf->Ln(14);

$tableTotalWidth = 48+14+14+14+14+14+14+22;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($tableTotalWidth,7,strtoupper($_CompanyName),0,1,'C',true);
$pdf->SetFont('Arial','',9);
$pdf->Cell($tableTotalWidth,6,$_Address.", ".$_Location,0,1,'C',true);
$pdf->Cell($tableTotalWidth,6,'TEL:'. $_Telephone1. " ". $_Telephone2,0,1,'C',true);
$pdf->Ln(2);

$caption = "3. d. Total number of ".$_FormLabel." students in the school by programme";
$pdf->SetFont('Arial','B',9);
$pdf->Cell($tableTotalWidth,7,$caption,0,1,'L',true);
$pdf->SetFont('Arial','',8);
$pdf->Cell($tableTotalWidth,5,"Batch: ".$_BatchName,0,1,'L',true);
$pdf->Ln(2);

$wProg = 48;
$w = 14;
$wGrand = 22;
$hTop = 7;
$hSub = 7;

$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg,$hTop+$hSub,'Programme',1,0,'L');
$pdf->Cell($w*2,$hTop,'Day',1,0,'C');
$pdf->Cell($w*2,$hTop,'Boarding',1,0,'C');
$pdf->Cell($w*2,$hTop,'Total',1,0,'C');
$pdf->Cell($wGrand,$hTop+$hSub,'Grand Total',1,1,'C');

$pdf->SetX($x+$wProg);
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,1,'C');

$pdf->SetFont('Arial','',8);
$_GrandAll = 0;
foreach($_Programmes as $_P){
    $_DayBoy = $_Counts[$_P]["DayBoy"];
    $_DayGirl = $_Counts[$_P]["DayGirl"];
    $_BoardBoy = $_Counts[$_P]["BoardingBoy"];
    $_BoardGirl = $_Counts[$_P]["BoardingGirl"];
    $_TotBoy = $_DayBoy + $_BoardBoy;
    $_TotGirl = $_DayGirl + $_BoardGirl;
    $_RowGrand = $_TotBoy + $_TotGirl;
    $_GrandAll += $_RowGrand;

    $pdf->Cell($wProg,7,$_P,1,0,'L');
    $pdf->Cell($w,7,($_DayBoy>0 ? $_DayBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_DayGirl>0 ? $_DayGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardBoy>0 ? $_BoardBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardGirl>0 ? $_BoardGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotBoy>0 ? $_TotBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotGirl>0 ? $_TotGirl : ''),1,0,'C');
    $pdf->Cell($wGrand,7,($_RowGrand>0 ? $_RowGrand : ''),1,1,'C');
}

$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg+(6*$w),7,'Grand Total',1,0,'R');
$pdf->Cell($wGrand,7,$_GrandAll,1,1,'C');

$pdf->Ln(8);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,'Print Date/Time: '.date("d/m/Y H:i:s"),0,1,'L');
if($_Unclassified>0){
    $pdf->Cell(0,6,'Unclassified (missing gender/residence): '.$_Unclassified,0,1,'L');
}

$__pdfName = 'students-programme-summary.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
}

if(isset($_POST["print_student"]))
{
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/print-error.log');
error_reporting(E_ALL);
include("dbstring.php");
include("company.php");
//Get all the ordered items
if(!file_exists(__DIR__.'/fpdf181/fpdf.php')){
    http_response_code(500);
    exit("Print setup error: PDF library file not found.");
}
require(__DIR__.'/fpdf181/fpdf.php');
$pdf = new FPDF();
$pdf->AddPage();
$_BranchStudentFilter = student_directory_branch_filter_sql($con, 'su');

$width_cell=array(7,55,20,20,20,20,10,25,15);
$pdf->SetFont('Arial','B',10);
//Background color of header//
//Heading of the pdf
// Logo (safe fallback to avoid hard failure when DB logo path is missing on server)
$logoPath = "";
if(!empty($_Logo)){
    $candidate = "logo/".$_Logo;
    if(file_exists($candidate)){
        $logoPath = $candidate;
    }
}
if($logoPath=="" && file_exists("logo/logo.png")){
    $logoPath = "logo/logo.png";
}
if($logoPath=="" && file_exists("logo/logo.jpeg")){
    $logoPath = "logo/logo.jpeg";
}
if($logoPath!=""){
    $pdf->Image($logoPath,100,3,20);
}
$pdf->Ln(15);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8],10,strtoupper($_CompanyName),0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8],10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);

$text_height = 5;
$text_length = 70;
$n=7;
$pdf->SetFillColor(255,255,255);

@$_ClassentryID=$_POST["print_class_id"];
@$_BatchID=$_POST["print_batchid"];
if($_ClassentryID=="" || $_BatchID==""){
    http_response_code(400);
    exit("Print request error: missing class or batch.");
}

include("dbstring.php");
$_SQL_EXECUTE2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl 
ON su.userid=cl.userid WHERE cl.class_entryid='$_ClassentryID'AND cl.batchid='$_BatchID' AND su.systemtype='Student' $_BranchStudentFilter ORDER BY su.firstname ASC");

//Registered clients
@$_ClassName="";
$_SQLGC=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassentryID'");
if($rowc=mysqli_fetch_array($_SQLGC,MYSQLI_ASSOC)){
$_ClassName=$rowc["class_name"];
}

@$_BatchName="";
$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchID'");
if($row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
		$_BatchName=$row_ba['batch'];
}

if(mysqli_num_rows($_SQL_EXECUTE2)>0){
	$pdf->SetFont('Arial','',9);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6],10,strtoupper(mysqli_num_rows($_SQL_EXECUTE2)." ".$_ClassName ." STUDENT(S) FOUND FOR ". $_BatchName." Batch"),0,0,'L',true);
$pdf->Ln(10);
$pdf->SetFont('Arial','B',7);
//Header starts //
//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'STUDENTS',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'BECE INDEX',1,0,'C',true);
$pdf->Cell($width_cell[3],10,'EMAIL',1,0,'C',true);
$pdf->Cell($width_cell[4],10,'MOBILE',1,0,'C',true);
$pdf->Cell($width_cell[5],10,'USERNAME',1,0,'C',true);
$pdf->Cell($width_cell[6],10,'TYPE',1,0,'C',true);
$pdf->Cell($width_cell[7],10,'DATE/TIME',1,0,'C',true);
$pdf->Cell($width_cell[8],10,'STATUS',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',6);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$serial=0;
$pdf->Ln(10);
	while($row=mysqli_fetch_array($_SQL_EXECUTE2,MYSQLI_ASSOC)){
	$_FullName=$row["firstname"]." ".$row["surname"]." ". $row["othernames"]."(".$row["userid"].")";
	$bece_index = isset($row["BECEIndexNumber"]) && !empty($row["BECEIndexNumber"])
		? $row["BECEIndexNumber"]
		: (isset($row["BECEIndex"]) && !empty($row["BECEIndex"]) ? $row["BECEIndex"] : "N/A");
	$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
	$pdf->Cell($width_cell[1],10,$_FullName,1,0,'L',$fill);
	$pdf->Cell($width_cell[2],10,$bece_index,1,0,'L',$fill);
	$pdf->Cell($width_cell[3],10,$row["email"],1,0,'L',$fill);
	$pdf->Cell($width_cell[4],10,$row["mobile"],1,0,'C',$fill);
	$pdf->Cell($width_cell[5],10,$row["username"],1,0,'L',$fill);
	$pdf->Cell($width_cell[6],10,$row["systemtype"],1,0,'L',$fill);
	$pdf->Cell($width_cell[7],10,$row["registereddatetime"],1,0,'L',$fill);
	$pdf->Cell($width_cell[8],10,$row["status"],1,0,'C',$fill);
	$fill = !$fill;
	$pdf->Ln(10);
}

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

$pdf->Ln(10); 
$pdf->Cell(0,10,'ADMINISTRATOR:',0);
 
$pdf->Ln(10); 
$pdf->Cell(0,10,'SIGNATURE:.......................................................',0);

$pdf->Ln(85); 
$pdf->Cell(0,10,' ',0);

 //}
//}
}
$__pdfName = 'students-list.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
}
?>

<?php
include("dbstring.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_ResidenceType = $_POST['residencetype'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_BECEIndexNumber = $_POST['beceindexnumber'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=$_POST['password'];
@$_AccessLevel="user";
@$_SystemType=$_POST['systemtype'];
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user']) && student_directory_can_manage_records()){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,residencetype,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,beceindexnumber,nextofkin_fullname,nextofkin_contact,registereddatetime,status,username,password,accesslevel,systemtype)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender','$_ResidenceType',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_BECEIndexNumber,'$_Nextofkin_fullname','$_Nextofcontact',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>User Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>User Information Failed to save,Error:$_Error</div>";
}
}elseif(isset($_POST['register_user'])){
$_SESSION['Message']="<div style='color:red'>You cannot manage student records from this page.</div>";
}
?>

<?php
include("dbstring.php");

if(isset($_GET["block_user"]) && student_directory_can_manage_records())
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='block' WHERE userid='$_GET[block_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User is blocked</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to block</div>";
	}
}elseif(isset($_GET["block_user"])){
    $_SESSION['Message']="<div style='color:red'>You cannot manage student records from this page.</div>";
}
?>

<?php
include("dbstring.php");

if(isset($_GET["unblock_user"]) && student_directory_can_manage_records())
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='active' WHERE userid='$_GET[unblock_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>User is active</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to unblock</div>";
	}
}elseif(isset($_GET["unblock_user"])){
    $_SESSION['Message']="<div style='color:red'>You cannot manage student records from this page.</div>";
}
?>

<?php
include("dbstring.php");

if(isset($_GET["delete_user"]) && student_directory_can_manage_records())
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsystemuser WHERE userid='$_GET[delete_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User Record Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to delete</div>";
	}
}elseif(isset($_GET["delete_user"])){
    $_SESSION['Message']="<div style='color:red'>You cannot manage student records from this page.</div>";
}
?>

<?php
if(!function_exists('student_directory_safe')){
function student_directory_safe($value){
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('student_directory_format_date')){
function student_directory_format_date($value, $format = "d M Y, g:i a"){
	if(empty($value)){
		return "--";
	}
	$timestamp = strtotime((string)$value);
	if($timestamp === false){
		return (string)$value;
	}
	return date($format, $timestamp);
}
}

if(!function_exists('student_directory_full_name')){
function student_directory_full_name($row){
	return trim(((string)$row["firstname"])." ".((string)$row["surname"])." ".((string)$row["othernames"]));
}
}

$_SelectedClassentryID = isset($_POST["class_entryid"]) ? trim((string)$_POST["class_entryid"]) : "";
$_SelectedBatchID = isset($_POST["batchid"]) ? trim((string)$_POST["batchid"]) : "";
$_ShowStudents = isset($_POST["showstudent"]);
$_FlashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";

$_ClassOptions = array();
$_BatchOptions = array();
$_StudentRows = array();
$_ClassName = "";
$_BatchName = "";
$_StudentTotal = 0;
$_ActiveStudentTotal = 0;
$_BlockedStudentTotal = 0;
$_BoardingStudentTotal = 0;
$_DayStudentTotal = 0;
$_IsHeadmasterViewer = student_directory_is_headmaster_viewer();
$_BranchStudentFilter = student_directory_branch_filter_sql($con, 'su');

$_ClassOptionsSql = "SELECT * FROM tblclassentry ORDER BY class_name";
if($_IsHeadmasterViewer && $_BranchStudentFilter !== ''){
	$_ClassOptionsSql = "SELECT DISTINCT ce.* FROM tblclassentry ce
		INNER JOIN tblclass cl ON cl.class_entryid=ce.class_entryid AND cl.status='active'
		INNER JOIN tblsystemuser su ON su.userid=cl.userid
		WHERE su.systemtype='Student'
		  AND su.status='active'
		  $_BranchStudentFilter
		ORDER BY ce.class_name";
}
$_SQL_CLASS_OPTIONS = mysqli_query($con, $_ClassOptionsSql);
if($_SQL_CLASS_OPTIONS){
	while($row=mysqli_fetch_array($_SQL_CLASS_OPTIONS, MYSQLI_ASSOC)){
		$_ClassOptions[] = $row;
	}
}

$_BatchOptionsSql = "SELECT * FROM tblbatch ORDER BY batch ASC";
if($_IsHeadmasterViewer && $_BranchStudentFilter !== ''){
	$_BatchOptionsSql = "SELECT DISTINCT b.* FROM tblbatch b
		INNER JOIN tblclass cl ON cl.batchid=b.batchid AND cl.status='active'
		INNER JOIN tblsystemuser su ON su.userid=cl.userid
		WHERE su.systemtype='Student'
		  AND su.status='active'
		  $_BranchStudentFilter
		ORDER BY b.batch ASC";
}
$_SQL_BATCH_OPTIONS = mysqli_query($con, $_BatchOptionsSql);
if($_SQL_BATCH_OPTIONS){
	while($row=mysqli_fetch_array($_SQL_BATCH_OPTIONS, MYSQLI_ASSOC)){
		$_BatchOptions[] = $row;
	}
}

if($_ShowStudents && $_SelectedClassentryID !== "" && $_SelectedBatchID !== ""){
	$_SQLGC = mysqli_query($con, "SELECT * FROM tblclassentry WHERE class_entryid='".mysqli_real_escape_string($con, $_SelectedClassentryID)."' LIMIT 1");
	if($_SQLGC && $rowc=mysqli_fetch_array($_SQLGC, MYSQLI_ASSOC)){
		$_ClassName = $rowc["class_name"];
	}

	$_SQL_BATCH = mysqli_query($con, "SELECT * FROM tblbatch WHERE batchid='".mysqli_real_escape_string($con, $_SelectedBatchID)."' LIMIT 1");
	if($_SQL_BATCH && $row_ba=mysqli_fetch_array($_SQL_BATCH, MYSQLI_ASSOC)){
		$_BatchName = $row_ba["batch"];
	}

	$_SQL_EXECUTE2 = mysqli_query($con, "SELECT * FROM tblsystemuser su INNER JOIN tblclass cl 
ON su.userid=cl.userid WHERE cl.class_entryid='".mysqli_real_escape_string($con, $_SelectedClassentryID)."' AND cl.batchid='".mysqli_real_escape_string($con, $_SelectedBatchID)."' AND su.systemtype='Student' $_BranchStudentFilter ORDER BY su.firstname ASC");

	if($_SQL_EXECUTE2){
		while($row=mysqli_fetch_array($_SQL_EXECUTE2, MYSQLI_ASSOC)){
			$_StudentTotal++;
			$row["_fullname"] = student_directory_full_name($row);
			$row["_bece_index"] = isset($row["BECEIndexNumber"]) && !empty($row["BECEIndexNumber"])
				? $row["BECEIndexNumber"]
				: (isset($row["BECEIndex"]) && !empty($row["BECEIndex"]) ? $row["BECEIndex"] : "N/A");
			$row["_status_key"] = strtolower(trim((string)$row["status"]));
			$row["_status_label"] = ($row["_status_key"] === "active") ? "Active" : "Blocked";
			$row["_residence_label"] = normalize_residence_label(isset($row["residencetype"]) ? $row["residencetype"] : "");
			if($row["_residence_label"] === ""){
				$row["_residence_label"] = trim((string)$row["residencetype"]);
			}
			if($row["_residence_label"] === ""){
				$row["_residence_label"] = "--";
			}
			$row["_entry_label"] = student_directory_format_date(isset($row["registereddatetime"]) ? $row["registereddatetime"] : "");
			$row["_search_index"] = strtolower(trim(
				$row["_fullname"]." ".
				((string)$row["userid"])." ".
				((string)$row["_bece_index"])." ".
				((string)$row["mobile"])." ".
				((string)$row["username"])." ".
				((string)$row["_residence_label"])." ".
				((string)$row["_status_label"])
			));
			if($row["_status_key"] === "active"){
				$_ActiveStudentTotal++;
			}else{
				$_BlockedStudentTotal++;
			}
			if(strtolower($row["_residence_label"]) === "boarding"){
				$_BoardingStudentTotal++;
			}
			if(strtolower($row["_residence_label"]) === "day"){
				$_DayStudentTotal++;
			}
			$_StudentRows[] = $row;
		}
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/viewstudents.css?v=20260514">
</head>
<body class="student-directory-page">
<div class="header">
<?php
include("menu.php");
?>		
</div>
<main class="student-directory-shell">
	<?php if($_FlashMessage!=""){ ?>
	<div class="student-directory-message"><?php echo $_FlashMessage; ?></div>
	<?php } ?>

	<section class="student-directory-hero">
		<div class="student-directory-hero__copy">
			<span class="student-directory-eyebrow">Student Directory</span>
			<h1>View Students</h1>
			<p>Load a class and batch, review student records clearly, and print the exact list or programme summaries without losing the existing workflow.</p>
		</div>
		<div class="student-directory-hero__meta">
			<?php if($_ShowStudents && $_ClassName !== "" && $_BatchName !== ""){ ?>
			<div class="student-directory-hero-chip"><i class="fa fa-graduation-cap"></i> <?php echo student_directory_safe($_ClassName); ?></div>
			<div class="student-directory-hero-chip"><i class="fa fa-calendar"></i> <?php echo student_directory_safe($_BatchName); ?> Batch</div>
			<?php } else { ?>
			<div class="student-directory-hero-chip"><i class="fa fa-search"></i> Select a class and batch to load students</div>
			<?php } ?>
		</div>
	</section>

	<section class="student-directory-panel">
		<div class="student-directory-panel__top">
			<div>
				<span class="student-directory-eyebrow">Filters</span>
				<h2>Class Student Register</h2>
			</div>
		</div>
		<form method="post" action="viewstudents.php" class="student-directory-filter-form">
			<label class="student-directory-field">
				<span>Class</span>
				<select id="class_entryid" name="class_entryid" required>
					<option value="">Select Class</option>
					<?php foreach($_ClassOptions as $row){ ?>
					<option value="<?php echo student_directory_safe($row["class_entryid"]); ?>"<?php echo ((string)$row["class_entryid"] === (string)$_SelectedClassentryID ? " selected" : ""); ?>><?php echo student_directory_safe($row["class_name"]); ?></option>
					<?php } ?>
				</select>
			</label>

			<label class="student-directory-field">
				<span>Batch</span>
				<select id="batchid" name="batchid" required>
					<option value="">Select Batch</option>
					<?php foreach($_BatchOptions as $row){ ?>
					<option value="<?php echo student_directory_safe($row["batchid"]); ?>"<?php echo ((string)$row["batchid"] === (string)$_SelectedBatchID ? " selected" : ""); ?>><?php echo student_directory_safe($row["batch"]); ?></option>
					<?php } ?>
				</select>
			</label>

			<div class="student-directory-filter-actions">
				<button class="student-directory-primary-btn" name="showstudent" type="submit"><i class="fa fa-search"></i> Load Students</button>
			</div>
		</form>
	</section>

	<?php if($_ShowStudents){ ?>
	<section class="student-directory-stats" aria-label="Student Summary">
		<article class="student-directory-stat">
			<span>Total Students</span>
			<strong><?php echo number_format($_StudentTotal); ?></strong>
		</article>
		<article class="student-directory-stat">
			<span>Active</span>
			<strong><?php echo number_format($_ActiveStudentTotal); ?></strong>
		</article>
		<article class="student-directory-stat">
			<span>Day</span>
			<strong><?php echo number_format($_DayStudentTotal); ?></strong>
		</article>
		<article class="student-directory-stat">
			<span>Boarding</span>
			<strong><?php echo number_format($_BoardingStudentTotal); ?></strong>
		</article>
	</section>

	<section class="student-directory-panel">
		<div class="student-directory-panel__top">
			<div>
				<span class="student-directory-eyebrow">Loaded Records</span>
				<h2><?php echo student_directory_safe($_ClassName !== "" ? $_ClassName : "Students"); ?></h2>
				<p class="student-directory-scope"><?php echo student_directory_safe($_BatchName !== "" ? $_BatchName." Batch" : ""); ?></p>
			</div>
			<div class="student-directory-print-actions">
				<form method="post">
					<input type="hidden" name="print_batchid" value="<?php echo student_directory_safe($_SelectedBatchID); ?>">
					<button class="student-directory-secondary-btn" id="print_batch_programme_summary_top" name="print_batch_programme_summary" type="submit"><i class="fa fa-print"></i> Print Batch Course Summary</button>
				</form>
				<?php if($_StudentTotal > 0){ ?>
				<form method="post">
					<input type="hidden" name="print_class_id" value="<?php echo student_directory_safe($_SelectedClassentryID); ?>">
					<input type="hidden" name="print_batchid" value="<?php echo student_directory_safe($_SelectedBatchID); ?>">
					<button class="student-directory-secondary-btn" id="print_student" name="print_student" type="submit"><i class="fa fa-print"></i> Print Students</button>
				</form>
				<form method="post">
					<input type="hidden" name="print_class_id" value="<?php echo student_directory_safe($_SelectedClassentryID); ?>">
					<input type="hidden" name="print_batchid" value="<?php echo student_directory_safe($_SelectedBatchID); ?>">
					<button class="student-directory-secondary-btn" id="print_programme_summary" name="print_programme_summary" type="submit"><i class="fa fa-print"></i> Print Programme Summary</button>
				</form>
				<?php } ?>
			</div>
		</div>

		<?php if($_StudentTotal > 0){ ?>
		<div class="student-directory-toolbar">
			<label class="student-directory-search">
				<i class="fa fa-search"></i>
				<input type="text" id="studentDirectorySearch" placeholder="Search by student name, index number, username, mobile, residence, or status">
			</label>
			<div class="student-directory-results" id="studentDirectoryResultCount">
				<?php echo number_format($_StudentTotal); ?> student(s) shown
			</div>
		</div>

		<div class="student-directory-print-head">
			<h2>Ayirebi Senior High School</h2>
			<p><?php echo student_directory_safe($_ClassName); ?> Students List</p>
			<span><?php echo student_directory_safe($_BatchName); ?> Batch</span>
		</div>

		<div class="student-directory-table-wrap">
			<table class="student-directory-table" id="studentDirectoryTable">
				<caption><?php echo number_format($_StudentTotal)." ".student_directory_safe($_ClassName)." student(s) found for ".student_directory_safe($_BatchName)." Batch"; ?></caption>
				<thead>
					<tr>
						<th>#</th>
						<th>Student</th>
						<th>Index Number</th>
						<th>Residence</th>
						<th>BECE Index No</th>
						<th>Mobile</th>
						<th>Username</th>
						<th>Entry Date</th>
						<th>Status</th>
						<th class="student-directory-actions-col">Task</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($_StudentRows as $index => $row){ ?>
					<tr class="student-directory-row" data-search="<?php echo student_directory_safe($row["_search_index"]); ?>">
						<td data-label="#"><?php echo number_format($index + 1); ?>.</td>
						<td data-label="Student">
							<div class="student-directory-name">
								<strong><?php echo student_directory_safe($row["_fullname"]); ?></strong>
								<span><?php echo student_directory_safe($row["userid"]); ?></span>
							</div>
						</td>
						<td data-label="Index Number"><?php echo student_directory_safe($row["userid"]); ?></td>
						<td data-label="Residence"><?php echo student_directory_safe($row["_residence_label"]); ?></td>
						<td data-label="BECE Index No"><?php echo student_directory_safe($row["_bece_index"]); ?></td>
						<td data-label="Mobile"><?php echo student_directory_safe($row["mobile"]); ?></td>
						<td data-label="Username"><?php echo student_directory_safe($row["username"]); ?></td>
						<td data-label="Entry Date"><?php echo student_directory_safe($row["_entry_label"]); ?></td>
						<td data-label="Status">
							<span class="student-directory-badge student-directory-badge--<?php echo ($row["_status_key"] === "active" ? "active" : "blocked"); ?>">
								<?php echo student_directory_safe($row["_status_label"]); ?>
							</span>
						</td>
						<td data-label="Task" class="student-directory-actions-cell">
							<div class="student-directory-actions">
								<a class="student-directory-action student-directory-action--view" title="View <?php echo student_directory_safe($row["firstname"]); ?> (<?php echo student_directory_safe($row["userid"]); ?>)" href="user-profile.php?view_user=<?php echo urlencode((string)$row["userid"]); ?>">
									<i class="fa fa-book"></i> View
								</a>
								<?php if($_IsHeadmasterViewer){ ?>
								<a class="student-directory-action student-directory-action--edit" title="Open transcript for <?php echo student_directory_safe($row["firstname"]); ?> (<?php echo student_directory_safe($row["userid"]); ?>)" href="student-history.php?userid=<?php echo urlencode((string)$row["userid"]); ?>">
									<i class="fa fa-history"></i> Transcript
								</a>
								<?php } else { ?>
								<a class="student-directory-action student-directory-action--edit" title="Edit <?php echo student_directory_safe($row["firstname"]); ?> (<?php echo student_directory_safe($row["userid"]); ?>)" href="register_edit.php?edit_user=<?php echo urlencode((string)$row["userid"]); ?>">
									<i class="fa fa-edit"></i> Edit
								</a>
								<?php } ?>
							</div>
						</td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<?php } else { ?>
		<div class="student-directory-empty">
			<i class="fa fa-users"></i>
			<h3>No students found</h3>
			<p>No student record matched the selected class and batch.</p>
		</div>
		<?php } ?>
	</section>
	<?php } else { ?>
	<section class="student-directory-empty student-directory-empty--standalone">
		<i class="fa fa-folder-open"></i>
		<h3>Nothing loaded yet</h3>
		<p>Select a class and batch, then load the student register.</p>
	</section>
	<?php } ?>

	<button onclick="topFunction()" id="myBtn" class="student-directory-top-btn" title="Go to top">Top</button>
</main>

<script>
var mybutton = document.getElementById("myBtn");
var searchInput = document.getElementById("studentDirectorySearch");
var resultCountNode = document.getElementById("studentDirectoryResultCount");

window.onscroll = function() {scrollFunction()};

function scrollFunction() {
	if (!mybutton) {
		return;
	}
	if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
		mybutton.style.display = "block";
	} else {
		mybutton.style.display = "none";
	}
}

function topFunction() {
	document.body.scrollTop = 0;
	document.documentElement.scrollTop = 0;
}

if (searchInput) {
	searchInput.addEventListener("input", function () {
		var query = (this.value || "").toLowerCase().trim();
		var rows = document.querySelectorAll(".student-directory-row");
		var visible = 0;
		for (var i = 0; i < rows.length; i++) {
			var haystack = rows[i].getAttribute("data-search") || "";
			var match = query === "" || haystack.indexOf(query) !== -1;
			rows[i].style.display = match ? "" : "none";
			if (match) {
				visible++;
			}
		}
		if (resultCountNode) {
			resultCountNode.textContent = visible.toLocaleString() + " student(s) shown";
		}
	});
}
</script>
</body>
</html>

<?php
session_start();
$_SESSION['Message']="";
if(file_exists(__DIR__.DIRECTORY_SEPARATOR."semester-registry-utils.php")){
include_once("semester-registry-utils.php");
}

if(!function_exists('examanalysis_rank_esc')){
function examanalysis_rank_esc($value){
	return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
}
}

if(!function_exists('examanalysis_subject_assignment_year_sql')){
function examanalysis_subject_assignment_year_sql($alias='sa'){
	if(function_exists('semester_registry_assignment_year_sql')){
		return semester_registry_assignment_year_sql($alias);
	}
	$alias=trim((string)$alias);
	if($alias===""){
		$alias='sa';
	}
	return "DATE_FORMAT(".$alias.".datetimeentry, '%Y')";
}
}

function getPositionSuffixText($position){
	if ($position % 100 >= 11 && $position % 100 <= 13) {
		return $position . "th";
	}
	switch ($position % 10) {
		case 1: return $position . "st";
		case 2: return $position . "nd";
		case 3: return $position . "rd";
		default: return $position . "th";
	}
}

function getSubjectAnalysisRows($con,$subjectId,$batchId,$classId,$termId,$academicYear=""){
	$_Rows=array();
	$academicYear=trim((string)$academicYear);
	$_AcademicYearSql="";
	if($academicYear!==""){
		$_AcademicYearSafe=mysqli_real_escape_string($con,$academicYear);
		$_AcademicYearSql=" AND ".examanalysis_subject_assignment_year_sql("sa")."='$_AcademicYearSafe'";
	}
	$_SQL=mysqli_query($con,"SELECT mk.userid,su.firstname,su.othernames,su.surname,SUM(mk.mark) AS totalmark
		FROM tblmark mk
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		WHERE sa.batchid='$batchId' AND sa.termname='$termId' AND sc.classid='$classId' 
		AND sc.subjectid='$subjectId' AND su.systemtype='Student'
		$_AcademicYearSql
		GROUP BY mk.userid,su.firstname,su.othernames,su.surname
		ORDER BY totalmark DESC,su.userid ASC");

	while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
		$_Rows[]=array(
			"userid"=>$row["userid"],
			"fullname"=>strtoupper($row["firstname"]." ".$row["othernames"]." ".$row["surname"]),
			"totalmark"=>(int)$row["totalmark"]
		);
	}

	$_ScorePosition=array();
	for($k=0;$k<count($_Rows);$k++){
		$_ScoreKey=$_Rows[$k]["totalmark"];
		if(!isset($_ScorePosition[$_ScoreKey])){
			$_ScorePosition[$_ScoreKey]=$k+1;
		}
	}

	for($k=0;$k<count($_Rows);$k++){
		$_PosNum=$_ScorePosition[$_Rows[$k]["totalmark"]];
		$_Rows[$k]["position"]=getPositionSuffixText($_PosNum);
	}

	return $_Rows;
}
?>

<?php
//Declare the variables
@$_SubjectID=$_POST['subjectid'];
@$_BatchId=$_POST['batchid'];
@$_AcademicYear=trim((string)$_POST['academicyear']);
@$_ClassId=$_POST['classid'];
@$_TermId=$_POST['termid'];

if(isset($_POST["print_examanalysis_report"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
      include("remark.php");
      include("gradingsystem.php");
      include("positions.php");

@$_grade_obj=new GradingSystem;
@$_position_obj_1=new Position;

@$_SchoolCloses="";
@$_NextTermBegins="";
@$_AcademicYearLabel="";
$_TermFilter = (isset($_TermId) && trim((string)$_TermId)!=="") ? (int)$_TermId : 0;
$_AcademicYearFilter = trim((string)$_AcademicYear);
if($_TermFilter>0 && $_AcademicYearFilter!==""){
$_AcademicYearFilterEsc=mysqli_real_escape_string($con,$_AcademicYearFilter);
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' AND termname='$_TermFilter' AND academicyear='$_AcademicYearFilterEsc' ORDER BY datetimeentry DESC LIMIT 1");
}elseif($_TermFilter>0){
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' AND termname='$_TermFilter' ORDER BY datetimeentry DESC LIMIT 1");
}else{
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' ORDER BY termname DESC, datetimeentry DESC LIMIT 1");
}
if((!$_SQL_IN || mysqli_num_rows($_SQL_IN)===0) && $_TermFilter>0){
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' ORDER BY termname DESC, datetimeentry DESC LIMIT 1");
}
if($row_in=mysqli_fetch_array($_SQL_IN,MYSQLI_ASSOC))
{
$_SchoolCloses=$row_in['schoolcloses'];
$_NextTermBegins=$row_in['schoolresumes'];
$_AcademicYearLabel=trim((string)(isset($row_in['academicyear']) ? $row_in['academicyear'] : ''));
if($_AcademicYearLabel===""){
    $_AcademicYearLabel=(trim((string)$row_in['datetimeentry'])!=="" ? date("Y",strtotime((string)$row_in['datetimeentry'])) : "");
}
}

@$_Roll=0;
@$_Attendance=0;
@$_TotalAttendance=0;
@$_Promotedto="";
@$_Conduct="";
@$_Interest="";
@$_Class_Teacher_Remark="";
@$_Head_Teacher_Remark="";



@$_SubjectName="";
@$_BatchName="";
@$_ClassName="";

$_SQL_SUBNAME=mysqli_query($con,"SELECT subject FROM tblsubject WHERE subjectid='$_SubjectID'");
if($rowsn=mysqli_fetch_array($_SQL_SUBNAME,MYSQLI_ASSOC)){ $_SubjectName=$rowsn["subject"]; }

$_SQL_BNAME=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchId'");
if($rowbn=mysqli_fetch_array($_SQL_BNAME,MYSQLI_ASSOC)){ $_BatchName=$rowbn["batch"]; }

$_SQL_CNAME=mysqli_query($con,"SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassId'");
if($rowcn=mysqli_fetch_array($_SQL_CNAME,MYSQLI_ASSOC)){ $_ClassName=$rowcn["class_name"]; }

$_AnalysisRows=getSubjectAnalysisRows($con,$_SubjectID,$_BatchId,$_ClassId,$_TermId,$_AcademicYear);

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(15,85,30,30,30,35);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',$width_cell[0]+$width_cell[1],3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"GHANA EDUCATION SERVICE",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);

  
$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //

//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'STUDENT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'TOTAL SCORE',1,0,'C',true);
$pdf->Cell($width_cell[3],10,'POSITION',1,0,'C',true);
$pdf->Cell($width_cell[4],10,'GRADE',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);

@$serial1=0;
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,strtoupper($_SubjectName).": ".strtoupper($_BatchName)." | ".strtoupper($_ClassName)." | AY ".strtoupper($_AcademicYearLabel!=="" ? $_AcademicYearLabel : $_BatchName)." | SEM ".$_TermId,1,0,'L',$fill);
$pdf->Ln(10);
for($idx=0;$idx<count($_AnalysisRows);$idx++){
$row_rsu=$_AnalysisRows[$idx];
$serial1=$serial1+1;

$pdf->Cell($width_cell[0],10,$serial1,1,0,'C',$fill);

$_FullName= $row_rsu['fullname'];
$_User_Id="(".$row_rsu['userid'].")";

 $pdf->Cell($width_cell[1],10,$_FullName.$_User_Id,1,0,'L',$fill);
	@$_TotalMark=$row_rsu["totalmark"];
	$pdf->Cell($width_cell[2],10,$_TotalMark,1,0,'C',$fill);
	$pdf->Cell($width_cell[3],10,$row_rsu["position"],1,0,'C',$fill);
	@$_final_grade=0;
	$_grade_obj->setMark($_TotalMark);
	$_final_grade=$_grade_obj->getMark($_TotalMark);
	$pdf->Cell($width_cell[4],10,$_final_grade,1,0,'C',$fill);
	$fill = !$fill;
	$pdf->Ln(10);
}
/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
?>

<?php
include("dbstring.php");
@$_Mark=$_POST['marks'];
@$_AssignmentId=$_POST['assignmentid'];
@$_UserId=$_POST['userid'];
@$_TotalMark=$_POST['totalscore'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_all_mark']))
{
	@$_CheckMark=0;
	foreach ($_Mark as $_Selected_Mark) 
	{
		if($_Selected_Mark>$_TotalMark){
			$_CheckMark=1;
		}
	}
//Check if mark entered is more than the total mark
if($_CheckMark==1){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white;'>Total Mark is less than the mark entered</div>";
}else/*No mark is greater than the total mark*/
{

$_TotalUsers =count($_UserId);

for($k=0;$k<$_TotalUsers;$k++)
{
$_Selected_User=$_UserId[$k];
$_Selected_Mark=$_Mark[$k];

		include("code.php");
	@$_MarkId=$code;
	@$_UserFullname="";

	$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_Selected_User'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

		$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
		VALUES('$_MarkId','$_AssignmentId','$_Selected_User','Class Score','$_Selected_Mark','$_TotalMark',NOW(),'active','$_Recordedby')");
			if($_SQL_EXECUTE)
			{
		
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Selected_Mark Successfully Stored for $_UserFullname</div>";
			}
			else{
				$_Error=mysqli_error($con);
				$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Mark failed to save,$_Error</div>";
			}
	}
	}	
	
}
?>

<?php
include("dbstring.php");
@$_Update_subject=$_POST['update_item'];
@$_Update_subjectid=$_POST['update_subjectid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsubject SET subject='$_Update_subject' WHERE subjectid='$_Update_subjectid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Subject Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Subject failed to update,$_Error</div>";
	}
}
?>
<?php
include("dbstring.php");

if(isset($_GET["delete_mark"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_GET[delete_mark]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
	}
}

@$_FilterBatch=isset($_GET["batchid"])?$_GET["batchid"]:"";
@$_FilterAcademicYear=isset($_GET["academic_year"])?trim((string)$_GET["academic_year"]):"";
@$_FilterClass=isset($_GET["class_id"])?$_GET["class_id"]:"";
@$_FilterTerm=isset($_GET["term_id"])?$_GET["term_id"]:"";
@$_FilterSubject=isset($_GET["subject_id"])?$_GET["subject_id"]:"";
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/examanalysis-subject.css">
<script type="text/javascript">
function applySubjectFilter(){
	var batch=document.getElementById("filter_batchid").value;
	var academicYear=document.getElementById("filter_academicyear").value;
	var cls=document.getElementById("filter_classid").value;
	var term=document.getElementById("filter_termid").value;
	var subject=document.getElementById("filter_subjectid").value;
	if(batch=="" || academicYear=="" || cls=="" || term=="" || subject==""){
		alert("Select Batch, Academic Year, Class, Term and Subject.");
		return;
	}
	window.location.href="examanalysis-subject.php?batchid="+encodeURIComponent(batch)+"&academic_year="+encodeURIComponent(academicYear)+"&class_id="+encodeURIComponent(cls)+"&term_id="+encodeURIComponent(term)+"&subject_id="+encodeURIComponent(subject);
}
</script>
</head>
<body>
<div class="header">
<?php
include("menu.php");
?>		
</div>

<div class="main-platform subject-analysis-page">
<main class="subject-analysis-shell">
	<section class="subject-analysis-hero">
		<div>
			<span class="subject-analysis-eyebrow">Exam Analysis</span>
			<h1>Subject Performance</h1>
			<p>Filter by batch, academic year, class, semester, and subject to review ranked student performance.</p>
		</div>
		<div class="subject-analysis-hero__chips">
			<span><i class="fa fa-filter"></i> Scoped analysis</span>
			<span><i class="fa fa-trophy"></i> Positions</span>
			<span><i class="fa fa-print"></i> Printable</span>
		</div>
	</section>

	<section class="subject-analysis-grid">
		<aside class="subject-analysis-panel subject-analysis-panel--filters">
			<div class="subject-analysis-panel__head">
				<div>
					<span class="subject-analysis-eyebrow">Filters</span>
					<h2>Load Report</h2>
				</div>
			</div>
<?php
include("dbstring.php");
@$_TeacherWhere="";
if($_SESSION["ACCESSLEVEL"]=="user" && $_SESSION["SYSTEMTYPE"]=="Teacher"){
	$_TeacherWhere=" AND sa.userid='$_SESSION[USERID]'";
}
echo "<div class='subject-analysis-filter-form'>";

echo "<label class='subject-analysis-field'><span>Batch</span>";
echo "<select id='filter_batchid'>";
echo "<option value=''>Batch</option>";
$_SQL_FB=mysqli_query($con,"SELECT DISTINCT bch.batchid,bch.batch FROM tblsubjectassignment sa 
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid WHERE 1=1 $_TeacherWhere ORDER BY bch.batch DESC");
while($row_fb=mysqli_fetch_array($_SQL_FB,MYSQLI_ASSOC)){
	$_Sel=($_FilterBatch==$row_fb["batchid"])?"selected":"";
	echo "<option value='$row_fb[batchid]' $_Sel>$row_fb[batch]</option>";
}
echo "</select>";
echo "</label>";

echo "<label class='subject-analysis-field'><span>Academic Year</span>";
echo "<select id='filter_academicyear'>";
echo "<option value=''>Academic Year</option>";
@$_YearFilterWhere="";
if($_FilterBatch!=""){ $_YearFilterWhere=$_YearFilterWhere." AND batchid='$_FilterBatch'"; }
$_SQL_FY=mysqli_query($con,"
SELECT DISTINCT academic_year FROM (
	SELECT CASE
		WHEN TRIM(COALESCE(academicyear,''))<>'' THEN academicyear
		ELSE YEAR(datetimeentry)
	END AS academic_year
	FROM tblschoolinfo
	".($_YearFilterWhere!=="" ? " WHERE 1=1 $_YearFilterWhere " : "")."
	UNION
	SELECT YEAR(datetimeentry) AS academic_year
	FROM tblsubjectassignment
	".($_YearFilterWhere!=="" ? " WHERE 1=1 $_YearFilterWhere " : "")."
) year_options
WHERE academic_year IS NOT NULL AND academic_year<>''
ORDER BY academic_year DESC");
if($_SQL_FY){
while($row_fy=mysqli_fetch_array($_SQL_FY,MYSQLI_ASSOC)){
	$_Sel=($_FilterAcademicYear==(string)$row_fy["academic_year"])?"selected":"";
	echo "<option value='$row_fy[academic_year]' $_Sel>$row_fy[academic_year]</option>";
}
}
echo "</select>";
echo "</label>";

echo "<label class='subject-analysis-field'><span>Class</span>";
echo "<select id='filter_classid'>";
echo "<option value=''>Class</option>";
@$_ClassFilterWhere="";
if($_FilterBatch!=""){ $_ClassFilterWhere=$_ClassFilterWhere." AND sa.batchid='$_FilterBatch'"; }
$_SQL_FC=mysqli_query($con,"SELECT DISTINCT ce.class_entryid,ce.class_name FROM tblsubjectassignment sa
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE 1=1 $_TeacherWhere $_ClassFilterWhere ORDER BY ce.class_name ASC");
while($row_fc=mysqli_fetch_array($_SQL_FC,MYSQLI_ASSOC)){
	$_Sel=($_FilterClass==$row_fc["class_entryid"])?"selected":"";
	echo "<option value='$row_fc[class_entryid]' $_Sel>$row_fc[class_name]</option>";
}
echo "</select>";
echo "</label>";

echo "<label class='subject-analysis-field'><span>Semester</span>";
echo "<select id='filter_termid'>";
echo "<option value=''>Term</option>";
@$_TermFilterWhere="";
if($_FilterBatch!=""){ $_TermFilterWhere=$_TermFilterWhere." AND sa.batchid='$_FilterBatch'"; }
if($_FilterClass!=""){ $_TermFilterWhere=$_TermFilterWhere." AND sc.classid='$_FilterClass'"; }
$_SQL_FT=mysqli_query($con,"SELECT DISTINCT sa.termname FROM tblsubjectassignment sa
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
	WHERE 1=1 $_TeacherWhere $_TermFilterWhere ORDER BY sa.termname ASC");
while($row_ft=mysqli_fetch_array($_SQL_FT,MYSQLI_ASSOC)){
	$_Sel=($_FilterTerm==$row_ft["termname"])?"selected":"";
	echo "<option value='$row_ft[termname]' $_Sel>Semester $row_ft[termname]</option>";
}
echo "</select>";
echo "</label>";

echo "<label class='subject-analysis-field'><span>Subject</span>";
echo "<select id='filter_subjectid'>";
echo "<option value=''>Subject</option>";
$_SQL_FS=mysqli_query($con,"SELECT DISTINCT sub.subjectid,sub.subject FROM tblsubjectassignment sa
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
	WHERE 1=1 $_TeacherWhere ORDER BY sub.subject ASC");
while($row_fs=mysqli_fetch_array($_SQL_FS,MYSQLI_ASSOC)){
	$_Sel=($_FilterSubject==$row_fs["subjectid"])?"selected":"";
	echo "<option value='$row_fs[subjectid]' $_Sel>$row_fs[subject]</option>";
}
echo "</select>";
echo "</label>";

echo "<div class='subject-analysis-actions'>";
echo "<button type='button' class='subject-analysis-btn subject-analysis-btn--primary' onclick='applySubjectFilter()'><i class='fa fa-search'></i> Apply</button>";
echo "<a href='examanalysis-subject.php' class='subject-analysis-btn subject-analysis-btn--ghost'><i class='fa fa-refresh'></i> Reset</a>";
echo "</div>";
echo "</div>";
?>
		</aside>

		<section class="subject-analysis-panel subject-analysis-panel--report">
			<div class="subject-analysis-panel__head">
				<div>
					<span class="subject-analysis-eyebrow">Report</span>
					<h2>Scores Report</h2>
				</div>
			</div>
<form id="formID2" name="formID2" method="post" action="examanalysis-subject.php">
<?php
include("positions.php");
//include("class-position.php");
include("gradingsystem.php");

//@$_position_obj=new Position;
@$_position_obj_1=new Position;
//@$_class_position_obj=new ClassPosition;
@$_grade_obj=new GradingSystem;

if(isset($_SESSION['Message']) && trim((string)$_SESSION['Message'])!==""){
echo "<div class='subject-analysis-message'>".$_SESSION['Message']."</div>";
}
include("dbstring.php");

if($_FilterBatch!="" && $_FilterClass!="" && $_FilterTerm!="" && $_FilterSubject!="")
{
@$_SubjectId=$_FilterSubject;
@$_BatchId2=$_FilterBatch;
@$_ClassId2=$_FilterClass;
@$_TermId2=$_FilterTerm;

@$_SubjectName="";
@$_BatchName="";
@$_ClassName="";
$_SQL_SUBNAME=mysqli_query($con,"SELECT subject FROM tblsubject WHERE subjectid='$_SubjectId'");
if($rowsn=mysqli_fetch_array($_SQL_SUBNAME,MYSQLI_ASSOC)){ $_SubjectName=$rowsn["subject"]; }
$_SQL_BNAME=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchId2'");
if($rowbn=mysqli_fetch_array($_SQL_BNAME,MYSQLI_ASSOC)){ $_BatchName=$rowbn["batch"]; }
$_SQL_CNAME=mysqli_query($con,"SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassId2'");
if($rowcn=mysqli_fetch_array($_SQL_CNAME,MYSQLI_ASSOC)){ $_ClassName=$rowcn["class_name"]; }

$_AnalysisRows=getSubjectAnalysisRows($con,$_SubjectId,$_BatchId2,$_ClassId2,$_TermId2,$_FilterAcademicYear);

echo "<input type='hidden' name='subjectid' value='$_SubjectId' />";
echo "<input type='hidden' name='batchid' value='$_BatchId2' />";
echo "<input type='hidden' name='academicyear' value='$_FilterAcademicYear' />";
echo "<input type='hidden' name='classid' value='$_ClassId2' />";
echo "<input type='hidden' name='termid' value='$_TermId2' />";
$_AcademicYearLabel2 = "";
if($_FilterAcademicYear!==""){
$_AcademicYearFilterEsc2=mysqli_real_escape_string($con,$_FilterAcademicYear);
$_SQL_IN_2=mysqli_query($con,"SELECT academicyear,datetimeentry FROM tblschoolinfo WHERE batchid='$_BatchId2' AND termname='$_TermId2' AND academicyear='$_AcademicYearFilterEsc2' ORDER BY datetimeentry DESC LIMIT 1");
}else{
$_SQL_IN_2=mysqli_query($con,"SELECT academicyear,datetimeentry FROM tblschoolinfo WHERE batchid='$_BatchId2' AND termname='$_TermId2' ORDER BY datetimeentry DESC LIMIT 1");
}
if($_SQL_IN_2 && ($row_in_2=mysqli_fetch_array($_SQL_IN_2,MYSQLI_ASSOC))){
    $_AcademicYearLabel2=trim((string)(isset($row_in_2['academicyear']) ? $row_in_2['academicyear'] : ''));
    if($_AcademicYearLabel2===""){
        $_AcademicYearLabel2=(trim((string)$row_in_2['datetimeentry'])!=="" ? date("Y",strtotime((string)$row_in_2['datetimeentry'])) : "");
    }
}
$_ReportScopeText=strtoupper($_SubjectName)." | ".strtoupper($_BatchName)." | ".strtoupper($_ClassName)." | AY ".strtoupper($_AcademicYearLabel2!=="" ? $_AcademicYearLabel2 : $_BatchName)." | SEM ".$_TermId2;
echo "<div class='subject-analysis-report-toolbar'>";
echo "<div><strong>".examanalysis_rank_esc($_SubjectName)."</strong><span>".examanalysis_rank_esc($_ReportScopeText)."</span></div>";
echo "<button class='subject-analysis-btn subject-analysis-btn--primary' id='print_examanalysis_report' name='print_examanalysis_report'><i class='fa fa-print'></i> Print Report</button>";
echo "</div>";
echo "<div class='subject-analysis-stats'>";
echo "<article><span>Students</span><strong>".count($_AnalysisRows)."</strong></article>";
echo "<article><span>Class</span><strong>".examanalysis_rank_esc($_ClassName)."</strong></article>";
echo "<article><span>Semester</span><strong>".$_TermId2."</strong></article>";
echo "</div>";

echo "<div class='subject-analysis-table-wrap'>";
echo "<table class='subject-analysis-table'>";
echo "<thead><tr><th>#</th><th>Student</th><th>Total</th><th>Position</th><th>Grade</th></tr></thead>";
echo "<tbody>";
@$serial1=0;
for($idx=0;$idx<count($_AnalysisRows);$idx++){
$row_rsu=$_AnalysisRows[$idx];
$serial1=$serial1+1;
echo "<tr>";
echo "<td data-label='#'><span class='subject-analysis-rank'>$serial1</span></td>";
echo "<td data-label='Student'>";
echo $row_rsu['fullname'];
echo "<br><small>(".$row_rsu['userid'].")</small>";
echo "</td>";
@$_TotalMark=$row_rsu["totalmark"];
echo "<td data-label='Total'><strong>".$_TotalMark."</strong></td>";
echo "<td data-label='Position'>";
echo $row_rsu["position"];
echo "</td>";
echo "<td data-label='Grade'>";
@$_final_grade=0;
$_grade_obj->setMark($_TotalMark);
$_final_grade=$_grade_obj->getMark($_TotalMark);
echo $_final_grade;
echo "</td>";
echo "</tr>";
}
echo "</tbody>";
echo "</table>";
echo "</div>";
}elseif(isset($_GET["batchid"]) || isset($_GET["academic_year"]) || isset($_GET["class_id"]) || isset($_GET["term_id"]) || isset($_GET["subject_id"])){
echo "<div class='subject-analysis-empty subject-analysis-empty--warning'>Select all filters: Batch, Academic Year, Class, Semester and Subject.</div>";
}else{
echo "<div class='subject-analysis-empty'>Select the filters to load a subject performance report.</div>";
}
?>
</form>
		</section>
	</section>

<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
</main>
</div>
</body>
</html>

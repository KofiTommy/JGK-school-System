<?php
session_start();
if(file_exists(__DIR__.DIRECTORY_SEPARATOR."semester-registry-utils.php")){
include_once("semester-registry-utils.php");
}

if(!function_exists('examanalysis_rank_registry_year_sql')){
function examanalysis_rank_registry_year_sql($alias='tr'){
	if(function_exists('semester_registry_resolved_year_sql')){
		return semester_registry_resolved_year_sql($alias);
	}
	$alias=trim((string)$alias);
	if($alias===""){
		$alias='tr';
	}
	return "DATE_FORMAT(".$alias.".datetimeentry, '%Y')";
}
}

if(!function_exists('examanalysis_rank_assignment_year_sql')){
function examanalysis_rank_assignment_year_sql($alias='sa'){
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

if(!function_exists('examanalysis_rank_esc')){
function examanalysis_rank_esc($value){
	return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
}
}

if(!function_exists('examanalysis_rank_column_exists')){
function examanalysis_rank_column_exists($con,$tableName,$columnName){
	$tableSafe=mysqli_real_escape_string($con,(string)$tableName);
	$columnSafe=mysqli_real_escape_string($con,(string)$columnName);
	$_SQL_COLUMN=mysqli_query($con,"SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'");
	return ($_SQL_COLUMN && mysqli_num_rows($_SQL_COLUMN)>0);
}
}

if(!function_exists('examanalysis_rank_compare_classes')){
function examanalysis_rank_compare_classes($firstRow,$secondRow){
	$firstName=strtolower(trim((string)$firstRow['class_name']));
	$secondName=strtolower(trim((string)$secondRow['class_name']));
	if($firstName===$secondName){
		return 0;
	}
	return ($firstName<$secondName) ? -1 : 1;
}
}

if(!function_exists('examanalysis_rank_compare_students')){
function examanalysis_rank_compare_students($firstRow,$secondRow){
	$firstTotal=(float)$firstRow['total'];
	$secondTotal=(float)$secondRow['total'];
	if($firstTotal==$secondTotal){
		$firstName=strtolower(trim((string)$firstRow['name']));
		$secondName=strtolower(trim((string)$secondRow['name']));
		if($firstName===$secondName){
			return 0;
		}
		return ($firstName<$secondName) ? -1 : 1;
	}
	return ($firstTotal>$secondTotal) ? -1 : 1;
}
}

if(!function_exists('examanalysis_rank_batch_label')){
function examanalysis_rank_batch_label($con,$batchId){
	$batchId=trim((string)$batchId);
	if($batchId===""){
		return "";
	}
	$batchIdSafe=mysqli_real_escape_string($con,$batchId);
	$_SQL_BA=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$batchIdSafe' LIMIT 1");
	if($_SQL_BA && $rowb=mysqli_fetch_array($_SQL_BA,MYSQLI_ASSOC)){
		return trim((string)$rowb["batch"]);
	}
	return "";
}
}

if(!function_exists('examanalysis_rank_student_name')){
function examanalysis_rank_student_name($studentRow){
	$_NameParts=array();
	foreach(array('firstname','othernames','surname') as $_NameKey){
		$_NameValue=trim((string)(isset($studentRow[$_NameKey]) ? $studentRow[$_NameKey] : ""));
		if($_NameValue!==""){
			$_NameParts[]=$_NameValue;
		}
	}
	$_FullName=trim(preg_replace('/\s+/',' ',implode(" ",$_NameParts)));
	$_UserId=trim((string)(isset($studentRow['userid']) ? $studentRow['userid'] : ""));
	if($_UserId!==""){
		$_FullName=$_FullName."(".$_UserId.")";
	}
	return trim($_FullName);
}
}

if(!function_exists('examanalysis_rank_available_years')){
function examanalysis_rank_available_years($con){
	$_Years=array();
	if(function_exists('semester_registry_ensure_academic_year_column')){
		semester_registry_ensure_academic_year_column($con);
	}
	$_RegistryYearSql=examanalysis_rank_registry_year_sql("tr");
	$_SQL_YEARS=mysqli_query($con,"SELECT DISTINCT $_RegistryYearSql AS academicyear FROM tbltermregistry tr ORDER BY academicyear DESC");
	if($_SQL_YEARS){
		while($row_year=mysqli_fetch_array($_SQL_YEARS,MYSQLI_ASSOC)){
			$_Year=trim((string)$row_year['academicyear']);
			if($_Year!==""){
				$_Years[$_Year]=$_Year;
			}
		}
	}
	$_AssignmentYearSql=examanalysis_rank_assignment_year_sql("sa");
	$_SQL_ASSIGN_YEARS=mysqli_query($con,"SELECT DISTINCT $_AssignmentYearSql AS academicyear FROM tblsubjectassignment sa ORDER BY academicyear DESC");
	if($_SQL_ASSIGN_YEARS){
		while($row_year=mysqli_fetch_array($_SQL_ASSIGN_YEARS,MYSQLI_ASSOC)){
			$_Year=trim((string)$row_year['academicyear']);
			if($_Year!==""){
				$_Years[$_Year]=$_Year;
			}
		}
	}
	rsort($_Years,SORT_NATURAL);
	return $_Years;
}
}

if(!function_exists('examanalysis_rank_latest_term_context')){
function examanalysis_rank_latest_term_context($con,$userId,$classId,$batchId,$academicYear="",$semester=""){
	$_Context=array(
		'termname'=>'',
		'academicyear'=>''
	);

	$userId=trim((string)$userId);
	$classId=trim((string)$classId);
	$batchId=trim((string)$batchId);
	if($userId==="" || $classId==="" || $batchId===""){
		return $_Context;
	}

	$userIdSafe=mysqli_real_escape_string($con,$userId);
	$classIdSafe=mysqli_real_escape_string($con,$classId);
	$batchIdSafe=mysqli_real_escape_string($con,$batchId);
	$academicYear=trim((string)$academicYear);
	$semester=trim((string)$semester);
	$_RegistryYearSql=examanalysis_rank_registry_year_sql("tr");
	$_ScopeSql="";
	if($academicYear!==""){
		$academicYearSafe=mysqli_real_escape_string($con,$academicYear);
		$_ScopeSql.=" AND $_RegistryYearSql='$academicYearSafe'";
	}
	if($semester!==""){
		$semesterSafe=mysqli_real_escape_string($con,$semester);
		$_ScopeSql.=" AND tr.termname='$semesterSafe'";
	}
	$_SQL_CONTEXT=mysqli_query($con,"SELECT tr.termname,$_RegistryYearSql AS academicyear
		FROM tbltermregistry tr
		WHERE tr.userid='$userIdSafe' AND tr.class_entryid='$classIdSafe' AND tr.batchid='$batchIdSafe'
		$_ScopeSql
		ORDER BY academicyear DESC,tr.termname DESC,tr.datetimeentry DESC
		LIMIT 1");

	if($_SQL_CONTEXT && $row_context=mysqli_fetch_array($_SQL_CONTEXT,MYSQLI_ASSOC)){
		$_Context['termname']=trim((string)$row_context['termname']);
		$_Context['academicyear']=trim((string)$row_context['academicyear']);
	}
	return $_Context;
}
}

if(!function_exists('examanalysis_rank_student_total')){
function examanalysis_rank_student_total($con,$studentRow){
	$_UserId=trim((string)(isset($studentRow['userid']) ? $studentRow['userid'] : ""));
	$_ClassId=trim((string)(isset($studentRow['class_entryid']) ? $studentRow['class_entryid'] : ""));
	$_BatchId=trim((string)(isset($studentRow['batchid']) ? $studentRow['batchid'] : ""));
	$_TermName=trim((string)(isset($studentRow['termname']) ? $studentRow['termname'] : ""));
	$_AcademicYear=trim((string)(isset($studentRow['academicyear']) ? $studentRow['academicyear'] : ""));

	if($_UserId==="" || $_ClassId==="" || $_BatchId==="" || $_TermName===""){
		return 0;
	}

	$_UserIdSafe=mysqli_real_escape_string($con,$_UserId);
	$_ClassIdSafe=mysqli_real_escape_string($con,$_ClassId);
	$_BatchIdSafe=mysqli_real_escape_string($con,$_BatchId);
	$_TermNameSafe=mysqli_real_escape_string($con,$_TermName);
	$_TermNameSql = ($_TermName!=="") ? " AND sa.termname='$_TermNameSafe'" : "";
	$_AcademicYearSql = "";
	if($_AcademicYear!==""){
		$_AcademicYearSafe=mysqli_real_escape_string($con,$_AcademicYear);
		$_AcademicYearSql = " AND ".examanalysis_rank_assignment_year_sql("sa")."='$_AcademicYearSafe'";
	}

	$_SQL_TOTAL=mysqli_query($con,"SELECT COALESCE(SUM(mk.mark+0),0) AS total_score
		FROM tblmark mk
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		WHERE mk.userid='$_UserIdSafe'
		AND mk.status='active'
		AND sa.status='active'
		AND sa.classid='$_ClassIdSafe'
		AND sa.batchid='$_BatchIdSafe'
		$_TermNameSql $_AcademicYearSql");

	if($_SQL_TOTAL && $row_total=mysqli_fetch_array($_SQL_TOTAL,MYSQLI_ASSOC)){
		return round((float)$row_total['total_score'],2);
	}
	return 0;
}
}

if(!function_exists('examanalysis_rank_class_rows')){
function examanalysis_rank_class_rows($con,$batchId,$academicYear="",$semester=""){
	$batchId=trim((string)$batchId);
	if($batchId===""){
		return array();
	}
	$academicYear=trim((string)$academicYear);
	$semester=trim((string)$semester);

	if(function_exists('semester_registry_ensure_academic_year_column')){
		semester_registry_ensure_academic_year_column($con);
	}

	$batchIdSafe=mysqli_real_escape_string($con,$batchId);
	$_LatestStudentRows=array();
	$_HasClassBatch=examanalysis_rank_column_exists($con,'tblclass','batchid');
	$_HasClassStatus=examanalysis_rank_column_exists($con,'tblclass','status');
	$_UseRegistryScope=($academicYear!=="" || $semester!=="");

	if($_HasClassBatch && !$_UseRegistryScope){
		$_ClassWhereSql=" WHERE cl.batchid='$batchIdSafe' AND su.systemtype='Student'";
		if($_HasClassStatus){
			$_ClassWhereSql.=" AND cl.status='active'";
		}
		$_SQL_CLASS=mysqli_query($con,"SELECT cl.classid,cl.userid,cl.class_entryid,cl.batchid,cl.datetimeentry,
			ce.class_name,su.firstname,su.othernames,su.surname
			FROM tblclass cl
			INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
			INNER JOIN tblsystemuser su ON cl.userid=su.userid
			$_ClassWhereSql
			ORDER BY cl.userid ASC,cl.datetimeentry DESC,ce.class_name ASC");

		if($_SQL_CLASS){
			while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
				$_ClassUserId=trim((string)$row_class['userid']);
				if($_ClassUserId==="" || isset($_LatestStudentRows[$_ClassUserId])){
					continue;
				}
				$_LatestStudentRows[$_ClassUserId]=$row_class;
			}
		}
	}

	if(empty($_LatestStudentRows)){
		$_RegistryYearSql=examanalysis_rank_registry_year_sql("tr");
		$_RegistryScopeSql="";
		if($academicYear!==""){
			$academicYearSafe=mysqli_real_escape_string($con,$academicYear);
			$_RegistryScopeSql.=" AND $_RegistryYearSql='$academicYearSafe'";
		}
		if($semester!==""){
			$semesterSafe=mysqli_real_escape_string($con,$semester);
			$_RegistryScopeSql.=" AND tr.termname='$semesterSafe'";
		}
		$_SQL_REGISTRY=mysqli_query($con,"SELECT tr.termid,tr.userid,tr.class_entryid,tr.termname,tr.batchid,tr.datetimeentry,
			$_RegistryYearSql AS academicyear,ce.class_name,su.firstname,su.othernames,su.surname
			FROM tbltermregistry tr
			INNER JOIN tblclassentry ce ON tr.class_entryid=ce.class_entryid
			INNER JOIN tblsystemuser su ON tr.userid=su.userid
			WHERE tr.batchid='$batchIdSafe' AND su.systemtype='Student'
			$_RegistryScopeSql
			ORDER BY tr.userid ASC,tr.datetimeentry DESC,tr.termname DESC,ce.class_name ASC");

		if(!$_SQL_REGISTRY){
			return array();
		}

		while($row_registry=mysqli_fetch_array($_SQL_REGISTRY,MYSQLI_ASSOC)){
			$_RegistryUserId=trim((string)$row_registry['userid']);
			if($_RegistryUserId==="" || isset($_LatestStudentRows[$_RegistryUserId])){
				continue;
			}
			$_LatestStudentRows[$_RegistryUserId]=$row_registry;
		}
	}

	$_ClassRowsById=array();
	foreach($_LatestStudentRows as $row_registry){
		$_ClassId=trim((string)$row_registry['class_entryid']);
		if($_ClassId===""){
			continue;
		}
		$_TermContext=examanalysis_rank_latest_term_context($con,$row_registry['userid'],$_ClassId,$batchId,$academicYear,$semester);
		if(!isset($_ClassRowsById[$_ClassId])){
			$_ClassRowsById[$_ClassId]=array(
				'class_entryid'=>$_ClassId,
				'class_name'=>trim((string)$row_registry['class_name']),
				'students'=>array()
			);
		}
		$_ClassRowsById[$_ClassId]['students'][]=array(
			'userid'=>$row_registry['userid'],
			'firstname'=>$row_registry['firstname'],
			'othernames'=>$row_registry['othernames'],
			'surname'=>$row_registry['surname'],
			'class_entryid'=>$row_registry['class_entryid'],
			'class_name'=>$row_registry['class_name'],
			'batchid'=>$batchId,
			'termname'=>$_TermContext['termname'],
			'academicyear'=>$_TermContext['academicyear']
		);
	}

	$_ClassRows=array_values($_ClassRowsById);
	if(!empty($_ClassRows)){
		usort($_ClassRows,'examanalysis_rank_compare_classes');
	}

	foreach($_ClassRows as $classIndex=>$classRow){
		$_RankedStudents=array();
		foreach($classRow['students'] as $studentRow){
			$_RankedStudents[]=array(
				'userid'=>$studentRow['userid'],
				'name'=>examanalysis_rank_student_name($studentRow),
				'total'=>examanalysis_rank_student_total($con,$studentRow),
				'termname'=>$studentRow['termname'],
				'academicyear'=>$studentRow['academicyear']
			);
		}
		if(!empty($_RankedStudents)){
			usort($_RankedStudents,'examanalysis_rank_compare_students');
		}
		$_ClassRows[$classIndex]['students']=$_RankedStudents;
	}

	return $_ClassRows;
}
}
//Declare the variables
@$_Batch_ID=$_POST["batchid"];

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


require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(25,90,40);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',$width_cell[0]+55,3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"GHANA EDUCATION SERVICE",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);
$pdf->Ln(10);

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
$pdf->Cell($width_cell[2],10,'TOTAL',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
//$pdf->Ln(10);
	
@$_Batch_ID=$_POST["batchid"];
@$_AcademicYear=$_POST["academicyear"];
@$_Semester=$_POST["semester"];
include("dbstring.php");
//echo "<input type='hidden' name='batchid' value='$_Batch_ID' />";

$_RankedClasses=examanalysis_rank_class_rows($con,$_Batch_ID,$_AcademicYear,$_Semester);
$_BatchLabel=examanalysis_rank_batch_label($con,$_Batch_ID);
$_ScopeLabel=array();
if(trim((string)$_AcademicYear)!==""){
	$_ScopeLabel[]="Academic Year ".$_AcademicYear;
}
if(trim((string)$_Semester)!==""){
	$_ScopeLabel[]="Semester ".$_Semester;
}

foreach($_RankedClasses as $row_acl)
{
@$_ClassName=$row_acl["class_name"];
$count=count($row_acl['students']);
$pdf->Ln(10);
if($_BatchLabel!==""){
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_BatchLabel." Class Ranked".(!empty($_ScopeLabel) ? " - ".implode(" | ",$_ScopeLabel) : ""),1,0,'L',$fill); 
}
$pdf->Ln(10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_ClassName,1,0,'L',$fill); 

@$serial=0;
for($g=0;$g<$count;$g++)
{
$pdf->Ln(10);
$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill); 
$pdf->Cell($width_cell[1],10,$row_acl['students'][$g]['name'],1,0,'L',$fill); 
$pdf->Cell($width_cell[2],10,$row_acl['students'][$g]['total'],1,0,'C',$fill); 
}

}
//}
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

	//@$_Subject="";
	//Check if subject already registered
	/*$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassId'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	$_ClassId=$row_s['classid'];
	//@$_getUser_ID=$row_s['userid'];

	}
	*/

	/*$_SQL_EXECUTE_USER=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsystemuser su ON sa.userid=su.userid WHERE sa.classificationid='$_Selected_ClassId'");
	if(!mysqli_num_rows($_SQL_EXECUTE_USER)>0){
		$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_UserId'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	}else{
		if($row_u=mysqli_fetch_array($_SQL_EXECUTE_USER,MYSQLI_ASSOC)){
		$_UserFullname=$row_u['firstname']." ".$row_u['othernames']." ".$row_u['surname']." (".$row_u['userid'].")";
		}
	}
	*/

	//$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId' AND sa.userid='$_UserId' AND sa.classid='$_ClassId'");
	/*$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId'");
	
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Assigned To $_UserFullname</div>";
		
	}else{
		*/

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
?>
<?php
include("dbstring.php");
if(isset($_POST["updatemark"])){
@$_MarkId=$_POST["newmarkid"];
@$_User_Id=$_POST["userid"];
@$_NewMark=$_POST["newmark"];

$_SQLUM=mysqli_query($con,"UPDATE tblmark SET mark='$_NewMark' WHERE userid='$_User_Id' AND markid='$_MarkId'");
if($_SQLUM){
	$_SESSION['Message']="<div style='border:1px solid #4f5;color:green;text-align:left;background-color:#efe;padding:5px;'>Mark Successfully Updated</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/examanalysis-rank.css">
<script type="text/javascript">
function checkMark(){
	var total=document.getElementById("totalmark").value;
	var mark=document.getElementById("newmark").value;

	if(mark>total){
		document.getElementById("newmark").value="";
		
	}

}

function examanalysisRankPrintClass(sectionId, titleText){
	var source=document.getElementById(sectionId);
	var styleTag=document.getElementById('rankdash-print-style');
	if(!source){
		return;
	}
	var printWindow=window.open('', '_blank', 'width=1000,height=720');
	if(!printWindow){
		return;
	}
	var printTitle=(titleText || 'Class Ranking');
	printWindow.document.open();
	printWindow.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>'+printTitle.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</title>');
	if(styleTag){
		printWindow.document.write('<style>'+styleTag.innerHTML+'</style>');
	}
	printWindow.document.write('</head><body><div class="rankdash-shell"><h2 class="rankdash-print-title">'+printTitle.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</h2>'+source.outerHTML+'</div></body></html>');
	printWindow.document.close();
	printWindow.focus();
	printWindow.onload=function(){
		printWindow.print();
	};
}
</script>
<style id="rankdash-print-style"><?php @readfile(__DIR__.DIRECTORY_SEPARATOR."css".DIRECTORY_SEPARATOR."examanalysis-rank.css"); ?></style>
</head>
<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="background-color:white">
<div class="rankdash-shell">
	<div class="rankdash-hero">
		<h1>Exams Analysis Ranking</h1>
		<p>Load a batch, review each current class ranking, and print one class at a time.</p>
		<div class="rankdash-badges">
			<span class="rankdash-badge"><i class="fa fa-filter"></i> Batch-based ranking</span>
			<span class="rankdash-badge"><i class="fa fa-users"></i> Current class placement first</span>
			<span class="rankdash-badge"><i class="fa fa-print"></i> Print all or print one class</span>
		</div>
	</div>

	<div class="rankdash-layout">
		<aside class="rankdash-sidebar">
			<div class="rankdash-card rankdash-print-hide">
				<h3>Load Ranking</h3>
				<form id="formID" name="formID" method="post" action="examanalysis-rank.php" class="rankdash-filter-form">
				<?php
				include("dbstring.php");
				echo "<fieldset><legend>Batch Filter</legend>";
				$_SelectedBatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : '';
				$_SelectedAcademicYear = isset($_POST['academicyear']) ? trim((string)$_POST['academicyear']) : '';
				$_SelectedSemester = isset($_POST['semester']) ? trim((string)$_POST['semester']) : '';
				$_SQL_2=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
				$_AvailableYears=examanalysis_rank_available_years($con);
				?>
				<div class="rankdash-field">
					<label for="batchid">Batch</label>
					<select id="batchid" name="batchid" class="validate[required]">
						<option value="">Select Batch</option>
						<?php
						while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
							$_Selected = ($_SelectedBatchId==trim((string)$row['batchid'])) ? "selected" : "";
							echo "<option value='".examanalysis_rank_esc($row['batchid'])."' $_Selected>".examanalysis_rank_esc($row['batch'])."</option>";
						}
						?>
					</select>
				</div>
				<div class="rankdash-field">
					<label for="academicyear">Academic Year</label>
					<select id="academicyear" name="academicyear">
						<option value="">All Years</option>
						<?php
						foreach($_AvailableYears as $_YearOption){
							$_Selected = ($_SelectedAcademicYear===$_YearOption) ? "selected" : "";
							echo "<option value='".examanalysis_rank_esc($_YearOption)."' $_Selected>".examanalysis_rank_esc($_YearOption)."</option>";
						}
						?>
					</select>
				</div>
				<div class="rankdash-field">
					<label for="semester">Semester</label>
					<select id="semester" name="semester">
						<option value="">All Semesters</option>
						<option value="1" <?php echo ($_SelectedSemester==="1" ? "selected" : ""); ?>>Semester 1</option>
						<option value="2" <?php echo ($_SelectedSemester==="2" ? "selected" : ""); ?>>Semester 2</option>
					</select>
				</div>
				<div class="rankdash-actions">
					<button class="rankdash-btn" id="show_terminal_report" name="show_terminal_report" type="submit"><i class="fa fa-search"></i> Show Report</button>
					<a class="rankdash-btn rankdash-btn--ghost" href="examanalysis-rank.php"><i class="fa fa-refresh"></i> Reset</a>
				</div>
				<?php
				echo "</fieldset>";
				?>
				</form>
			</div>

			<?php
			if(isset($_GET["edit_mark"]))
			{
			?>
			<div class="rankdash-card">
				<h3>Update Student Mark</h3>
				<form id="formID3" name="formID3" method="post" action="examanalysis-rank.php">
				<?php
				$_SQL_ED=mysqli_query($con,"SELECT * FROM tblmark mk INNER JOIN tblsystemuser su ON mk.userid=su.userid WHERE mk.markid='$_GET[edit_mark]'");
				if($rows_m=mysqli_fetch_array($_SQL_ED,MYSQLI_ASSOC))
				{
					@$_BATCH="";
					$_SQLBh=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[edit_batch]'");
					if($rowb=mysqli_fetch_array($_SQLBh,MYSQLI_ASSOC)){
						$_BATCH=$rowb["batch"];
					}
					echo "<table class='rankdash-update-table'>";
					echo "<thead><tr><th>Student</th><th>Batch</th><th>Subject</th><th>Mark</th><th>Total</th></tr></thead>";
					echo "<tbody>";
					echo "<tr>";
					echo "<td data-label='Student'>";
					echo "<input type='hidden' id='newmarkid' name='newmarkid' value='".examanalysis_rank_esc($_GET['edit_mark'])."' readonly/>";
					echo "<input type='hidden' id='userid' name='userid' value='".examanalysis_rank_esc($rows_m['userid'])."' readonly/>";
					echo examanalysis_rank_esc($rows_m['firstname']." ".$rows_m['othernames']." ".$rows_m['surname']." (".$rows_m['userid'].")");
					echo "</td>";
					echo "<td data-label='Batch'>";
					echo "<input type='hidden' id='batchid' name='batchid' value='".examanalysis_rank_esc($_GET['edit_batch'])."'/>";
					echo examanalysis_rank_esc($_BATCH);
					echo "</td>";
					echo "<td data-label='Subject'>".examanalysis_rank_esc($_GET['edit_subject'])."</td>";
					echo "<td data-label='Mark'><input type='text' id='newmark' name='newmark' value='".examanalysis_rank_esc($rows_m['mark'])."' onchange='checkMark()' class='validate[required]'/></td>";
					echo "<td data-label='Total'><input type='text' id='totalmark' name='totalmark' value='".examanalysis_rank_esc($rows_m['totalmark'])."' readonly/></td>";
					echo "</tr>";
					echo "</tbody>";
					echo "</table>";
					echo "<div class='rankdash-actions'><button class='rankdash-btn' id='updatemark' name='updatemark' type='submit'><i class='fa fa-edit'></i> Update Mark</button></div>";
				}else{
					echo "<div class='rankdash-empty'>The selected mark could not be loaded for editing.</div>";
				}
				?>
				</form>
			</div>
			<?php
			}
			?>
		</aside>

		<section class="rankdash-main">
			<div class="rankdash-card">
				<h3>Class Ranking</h3>
				<?php
				include("gradingsystem.php");
				$_grade_obj=new GradingSystem();
				if(isset($_SESSION['Message']) && trim((string)$_SESSION['Message'])!==""){
					echo "<div class='rankdash-message'>".$_SESSION['Message']."</div>";
					$_SESSION['Message']="";
				}
				?>
				<form id="formID2" name="formID2" method="post" action="examanalysis-rank.php">
				<?php
				if(isset($_POST["show_terminal_report"])){
					@$_Batch_ID=$_POST["batchid"];
					@$_AcademicYear=$_POST["academicyear"];
					@$_Semester=$_POST["semester"];
					include("dbstring.php");
					echo "<input type='hidden' name='batchid' value='".examanalysis_rank_esc($_Batch_ID)."' />";
					echo "<input type='hidden' name='academicyear' value='".examanalysis_rank_esc($_AcademicYear)."' />";
					echo "<input type='hidden' name='semester' value='".examanalysis_rank_esc($_Semester)."' />";

					$_RankedClasses=examanalysis_rank_class_rows($con,$_Batch_ID,$_AcademicYear,$_Semester);
					$_BatchLabel=examanalysis_rank_batch_label($con,$_Batch_ID);
					$_ScopeText=array();
					if(trim((string)$_AcademicYear)!==""){
						$_ScopeText[]="Academic Year ".$_AcademicYear;
					}
					if(trim((string)$_Semester)!==""){
						$_ScopeText[]="Semester ".$_Semester;
					}
					$_TotalStudentsAcrossClasses=0;
					foreach($_RankedClasses as $_RankedClassSummary){
						$_TotalStudentsAcrossClasses += count($_RankedClassSummary['students']);
					}
					?>
					<div class="rankdash-summary">
						<div class="rankdash-stat">
							<h4>Batch</h4>
							<strong><?php echo examanalysis_rank_esc($_BatchLabel!=="" ? $_BatchLabel : $_Batch_ID); ?></strong>
							<span><?php echo examanalysis_rank_esc(!empty($_ScopeText) ? implode(" | ",$_ScopeText) : "All years and semesters"); ?></span>
						</div>
						<div class="rankdash-stat">
							<h4>Classes</h4>
							<strong><?php echo (int)count($_RankedClasses); ?></strong>
							<span>Each class card can now be printed separately.</span>
						</div>
						<div class="rankdash-stat">
							<h4>Students Ranked</h4>
							<strong><?php echo (int)$_TotalStudentsAcrossClasses; ?></strong>
							<span>Totals follow the selected batch, academic year, and semester scope.</span>
						</div>
					</div>
					<div class="rankdash-actions rankdash-print-hide" style="margin-top:16px;">
						<button class="rankdash-btn" id="print_examanalysis_report" name="print_examanalysis_report" type="submit"><i class="fa fa-print"></i> Print All Classes</button>
					</div>
					<?php

					if(empty($_RankedClasses)){
						echo "<div class='rankdash-empty' style='margin-top:16px;'>No active class ranking data was found for the selected batch, year, and semester. Check semester registration and score entry for that scope.</div>";
					}else{
						echo "<div class='rankdash-class-grid'>";
						foreach($_RankedClasses as $classIndex=>$row_acl)
						{
							@$_ClassName=$row_acl["class_name"];
							$count=count($row_acl['students']);
							$_ClassPrintId="rank-class-".($classIndex+1);
							?>
							<article class="rankdash-class-card" id="<?php echo examanalysis_rank_esc($_ClassPrintId); ?>">
								<div class="rankdash-class-card__header">
									<div>
										<h4 class="rankdash-class-card__title"><?php echo examanalysis_rank_esc($_ClassName); ?></h4>
										<div class="rankdash-class-card__meta">
											<span><i class="fa fa-graduation-cap"></i> <?php echo examanalysis_rank_esc($_BatchLabel); ?></span>
											<?php if(!empty($_ScopeText)){ ?><span><i class="fa fa-calendar"></i> <?php echo examanalysis_rank_esc(implode(" | ",$_ScopeText)); ?></span><?php } ?>
											<span><i class="fa fa-users"></i> <?php echo (int)$count; ?> Student(s)</span>
										</div>
									</div>
									<div class="rankdash-class-card__actions rankdash-print-hide">
										<button type="button" class="rankdash-btn rankdash-btn--ghost" onclick="examanalysisRankPrintClass('<?php echo examanalysis_rank_esc($_ClassPrintId); ?>', <?php echo htmlspecialchars(json_encode($_ClassName.' - '.$_BatchLabel.' Ranked Class'), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fa fa-print"></i> Print This Class</button>
									</div>
								</div>
								<div class="rankdash-class-card__body">
									<div class="rankdash-table-wrap">
										<table class="rankdash-table">
											<thead>
												<tr><th>Rank</th><th>Student</th><th>Total</th></tr>
											</thead>
											<tbody>
											<?php
											@$serial=0;
											for($g=0;$g<$count;$g++)
											{
												echo "<tr>";
												echo "<td data-label='Rank'><span class='rankdash-rank-pill'>".(++$serial)."</span></td>";
												echo "<td data-label='Student'>".examanalysis_rank_esc($row_acl['students'][$g]['name'])."</td>";
												echo "<td data-label='Total'><span class='rankdash-total'>".examanalysis_rank_esc($row_acl['students'][$g]['total'])."</span></td>";
												echo "</tr>";
											}
											?>
											</tbody>
										</table>
									</div>
								</div>
							</article>
							<?php
						}
						echo "</div>";
					}
				}else{
					echo "<div class='rankdash-empty'>Select a batch on the left to load the ranked classes here.</div>";
				}
				?>
				</form>
			</div>
		</section>
	</div>
</div>

<br/><br/>
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
</div>
</body>
</html>

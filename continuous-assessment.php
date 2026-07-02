<?php
session_start();
$_SESSION['Message']="";
include("positions.php");
include("class-position.php");
include_once("semester-registry-utils.php");

@$_position_obj=new Position;
@$_position_obj_1=new Position;
@$_class_position_obj=new ClassPosition;
?>

<?php
//Declare the variables
@$_SubjectID=$_POST['subjectid'];

//@$todayTime =$_POST['today_time2'];
@$_BatchId=$_POST['batchid'];
@$_AcademicYear=trim((string)$_POST['academicyear']);
@$_TermId=$_POST['termid'];

if(isset($_POST["print_terminal_report"]))
{
      include("dbstring.php");
      include("class-teacher-utils.php");
      ensure_student_terminal_term_column($con);
      include("config.php");
      include("company.php");
      include("remark.php");
      include("gradingsystem.php");

     //include("positions.php");

      // @$_remark_obj=new Remark;

@$_grade_obj=new GradingSystem;
     
@$_SchoolCloses="";
@$_NextTermBegins="";
$_AcademicYearFilter = trim((string)$_AcademicYear);
@$_AcademicYearLabel=$_AcademicYearFilter;
$_TermFilter = (isset($_TermId) && trim((string)$_TermId)!=="") ? (int)$_TermId : 0;
if($_TermFilter>0 && $_AcademicYearFilter!==""){
$_AcademicYearFilterEsc=mysqli_real_escape_string($con,$_AcademicYearFilter);
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' AND termname='$_TermFilter' AND academicyear='$_AcademicYearFilterEsc' ORDER BY datetimeentry DESC LIMIT 1");
}elseif($_TermFilter>0){
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' AND termname='$_TermFilter' ORDER BY datetimeentry DESC LIMIT 1");
}else{
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' ORDER BY termname DESC, datetimeentry DESC LIMIT 1");
}
if((!$_SQL_IN || mysqli_num_rows($_SQL_IN)===0) && $_TermFilter>0 && $_AcademicYearFilter===""){
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

$_SQL_Terminal=mysqli_query($con,"SELECT * FROM tblstudentterminalreport WHERE userid='$_UserID' AND batchid='$_BatchId' ORDER BY datetimeentry DESC LIMIT 1");
if($row_ter=mysqli_fetch_array($_SQL_Terminal,MYSQLI_ASSOC)){
	$_Roll=$row_ter['roll'];
	$_Attendance=$row_ter['attendance'];
	$_TotalAttendance=$row_ter['totalattendance'];
	$_Promotedto=$row_ter['promotedto'];
	$_Conduct=$row_ter['conduct'];
	$_Interest=$row_ter['interest'];
	$_Class_Teacher_Remark=$row_ter['class_teacher_remark'];
	$_Head_Teacher_Remark=$row_ter['head_teacher_remark'];
}
      //Get all the ordered items

  //ob_start();
//Declare the variables
//$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
//$today = date("Y-m-d",$tomorrow);
 //@$_GrandTotal=0;

$_SQL_EXECUTE_SP="SELECT *,su.userid FROM tblmark mk 
INNER JOIN tblsystemuser su ON mk.userid=su.userid
INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
INNER JOIN tblbatch bh ON sa.batchid=bh.batchid
WHERE su.userid='$_UserID' AND sa.batchid='$_BatchId' GROUP BY mk.assignmentid";

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(45,30,25,30,25,35);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',$width_cell[0]+$width_cell[1]+$width_cell[2],3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"GHANA EDUCATION SERVICE",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);

  //Get the summation of all the marks
  @$_OverallScore=0;
  $_SQL_OM=mysqli_query($con,"SELECT SUM(mk.mark) AS OverallScore FROM tblmark mk INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
 WHERE sa.batchid='$_BatchId' AND mk.userid='$_UserID'");
 if($row_om=mysqli_fetch_array($_SQL_OM,MYSQLI_ASSOC)){
$_OverallScore=$row_om['OverallScore'];
}


 $_class_position_obj->setClassPosition($_BatchId,$_OverallScore);
 $_Get_Class_Position = $_class_position_obj->getClassPosition();

      $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,"Position:". $_Get_Class_Position,0,0,'R',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',10);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_EXECUTE_SP);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC))
      {
      	@$_StudentName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      $pdf->Cell($text_length,$text_height,'Name: '.$_StudentName,0,0,'L',true);
      $pdf->Ln($n);
       $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'Class/Form:'.$row_ps['class_name'],0,0,'L',true);
      //$pdf->Ln($n);

      $pdf->Cell($width_cell[3]+$width_cell[4],10,'No. On Roll: '.$_Roll,0,0,'L',true);
  $pdf->Ln($n);

      $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'School Closes: '.$_SchoolCloses,0,0,'L',true);
       $pdf->Cell($width_cell[3]+$width_cell[4],10,'Batch: '.$row_ps['batch'],0,0,'L',true);
 $pdf->Ln($n);

       $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'Next Term Begins: '.$_NextTermBegins,0,0,'L',true);
       $pdf->Cell($width_cell[3]+$width_cell[4],10,'Academic Year: '.($_AcademicYearLabel!=="" ? $_AcademicYearLabel : $row_ps['batch']).' | Semester: '.$row_ps['termname'],0,0,'L',true);
 $pdf->Ln($n);
      }
  

$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //

//First header column //
$pdf->Cell($width_cell[0],10,'SUBJECT',1,0,'C',true);

$pdf->Cell($width_cell[1],10,'CLASS SCORE',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'EXAM SCORE',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'TOTAL SCORE',1,0,'C',true);

$pdf->Cell($width_cell[4],10,'POS IN SUB',1,0,'C',true);

//$pdf->Cell($width_cell[5],10,'REMARKS',1,0,'C',true);
$pdf->Cell($width_cell[5],10,'GRADE',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_SP) as $row) 
{

 $pdf->Cell($width_cell[0],10,$row['subject'],1,0,'L',$fill);

@$_ExamScore=0;
 @$_ClassScore=0;

$_SQL_TOT2=mysqli_query($con,"SELECT * FROM tblmark mk 
 	WHERE mk.assignmentid='$row[assignmentid]' AND mk.testtype='Class Score' AND mk.userid='$_UserID'");
 if($row_cl=mysqli_fetch_array($_SQL_TOT2,MYSQLI_ASSOC)){
$_ClassScore=$row_cl['mark'];
 $pdf->Cell($width_cell[1],10,$_ClassScore,1,0,'C',$fill);
 }

 $_SQL_TOT=mysqli_query($con,"SELECT * FROM tblmark mk 
 	WHERE mk.assignmentid='$row[assignmentid]' AND mk.testtype='Exam Score' AND mk.userid='$_UserID'");
 if($row_ex=mysqli_fetch_array($_SQL_TOT,MYSQLI_ASSOC)){
$_ExamScore=$row_ex['mark'];
 $pdf->Cell($width_cell[2],10,$_ExamScore,1,0,'C',$fill); 
 }


@$_TotalScore=$_ExamScore+$_ClassScore;

 $pdf->Cell($width_cell[3],10,$_TotalScore,1,0,'C',$fill);
 
 //Get the positions
 @$_Final_Position=0;

$_position_obj->setPosition($row['assignmentid'],$_TotalScore);
$_Final_Position= $_position_obj->getPosition();

 $pdf->Cell($width_cell[4],10,$_Final_Position,1,0,'C',$fill);

//$_remark_obj->setMark($_TotalScore);
//$_final_remark=$_remark_obj->getMark($_TotalScore);

$_grade_obj->setMark($_TotalScore);
$_final_grade=$_grade_obj->getMark($_TotalScore);

// $pdf->Cell($width_cell[5],10,$_final_remark,1,0,'C',$fill);
$pdf->Cell($width_cell[5],10,$_final_grade,1,0,'C',$fill);

 $fill = !$fill;
 $pdf->Ln(10);
}
 $pdf->Ln(1);
//Footer of the table
$pdf->Cell(0,10,'Attendance:........................'.$_Attendance. '...........................Out of............................ '.$_TotalAttendance. '.............................   Promoted to:..................'.$_Promotedto,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,'Conduct:  '.$_Conduct,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,'Interest(Special Aptitude):  '.$_Interest,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,"Class Teacher's Remarks:  ".$_Class_Teacher_Remark,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,"Head Teacher's Remarks:  ".$_Head_Teacher_Remark,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,'Signature:................................................',0,0,'R',true);


$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(255,255,255);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");

 $pdf->Ln(7); 
 $pdf->SetFont('Arial','U',8);
 $pdf->Cell(0,10,'GRADING(S):',0,0,'L',true);
  $pdf->SetFont('Arial','',8);
 $pdf->Ln(6); 
 $pdf->Cell($width_cell[0],10,'1. A1 (80%-100%)',0,0,'L',true);
 $pdf->Cell($width_cell[1],10,'3. B3 (65%-69%) ',0,0,'L',true);
 $pdf->Cell($width_cell[2]+$width_cell[3],10,'5. C5 (55%-59%)',0,0,'C',true);
 $pdf->Cell($width_cell[4]+$width_cell[5],10,'7. D7 (45%-49%)',0,0,'C',true);
 $pdf->Ln(6);
 
 $pdf->Cell($width_cell[0],10,'2. B2 (70%-79%)',0,0,'L',true);
 $pdf->Cell($width_cell[1],10,'4. C4 (60%-64%) ',0,0,'L',true);
 $pdf->Cell($width_cell[2]+$width_cell[3],10,'6 C6 (50%-54%) ',0,0,'C',true);
 $pdf->Cell($width_cell[4]+$width_cell[5],10,'8 E8 (40%-44%)',0,0,'C',true); 
 $pdf->Ln(6); 
 $pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'9. F9 (0%-39%)',0,0,'L',true); 
 $pdf->Ln(6); 

$pdf->SetFont('Arial','B',8);

 //$pdf->Ln(10); 
 //$pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 //$pdf->Ln(6); 
 //$pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
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
<link rel="stylesheet" href="css/continuous-assessment.css">
<script type="text/javascript">
function checkMark(){
	var total=document.getElementById("totalmark").value;
	var mark=document.getElementById("newmark").value;

	if(+mark>+total){
		document.getElementById("newmark").value="";
		
	}

}
</script>
</head>
<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform ca-page">
	<section class="ca-hero">
		<div>
			<span class="ca-kicker">Academic Reports</span>
			<h1>Continuous Assessment</h1>
			<p>Review student assessment records by subject, batch, academic year, class, and semester.</p>
		</div>
		<div class="ca-hero-card">
			<i class="fa fa-line-chart"></i>
			<span>Assessment Workspace</span>
		</div>
	</section>

	<div class="ca-layout">
<aside class="ca-panel ca-filter-panel">
<form id="formID" name="formID" method="post" action="continuous-assessment.php">
	<div class="ca-panel-heading">
		<span class="ca-icon"><i class="fa fa-filter"></i></span>
		<div>
			<h2>Filter Report</h2>
			<p>Choose the exact class and semester to load.</p>
		</div>
	</div>
<?php	
include("dbstring.php");
/*$_SQL_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
	INNER JOIN tblsubjectassignment sa ON tr.batchid=sa.batchid AND tr.termname=sa.termname
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE tr.userid='$_SESSION[USERID]' ORDER BY tr.termname ASC");

echo "<select id='classid' name='classid' class='validate[required]'>";
	echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<option value='$row[class_entryid]'>$row[class_name]:Term: $row[termname] : $row[subject]</option>";
	}
echo "</select><br/><br/>";
*/
echo "<fieldset class='ca-fieldset'><legend>Report Details</legend>";

$_SelectedSubjectId = isset($_POST['subjectid']) ? $_POST['subjectid'] : '';
$_SelectedBatchId = isset($_POST['batchid']) ? $_POST['batchid'] : '';
$_SelectedAcademicYear = isset($_POST['academicyear']) ? trim((string)$_POST['academicyear']) : '';
$_SelectedClassId = isset($_POST['classid']) ? $_POST['classid'] : '';
$_SelectedTermId = isset($_POST['termid']) ? $_POST['termid'] : '';

$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubject ORDER BY subject ASC");
echo "<label for='subjectid'>Subject</label>";
echo "<select id='subjectid' name='subjectid' class='validate[required]'>";
echo "<option value=''>Select Subject</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
$_SelSub = ($_SelectedSubjectId==$row['subjectid']) ? "selected" : "";
echo "<option value='$row[subjectid]' $_SelSub>$row[subject]</option>";
}
echo "</select>";
			
$_SQL_2=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");

echo "<label for='batchid'>Academic Year Batch</label>";
echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Academic Year (Batch)</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
$_SelBatch = ($_SelectedBatchId==$row['batchid']) ? "selected" : "";
echo "<option value='$row[batchid]' $_SelBatch>$row[batch]</option>";
}
echo "</select>";

echo "<label for='academicyear'>Academic Year</label>";
echo "<select id='academicyear' name='academicyear' class='validate[required]'>";
echo "<option value=''>Select Academic Year</option>";
$_YearWhereSql = "";
if($_SelectedBatchId!==""){
$_SelectedBatchIdEsc = mysqli_real_escape_string($con,$_SelectedBatchId);
$_YearWhereSql = " WHERE batchid='$_SelectedBatchIdEsc' ";
}
$_SQL_YEAR_OPT=mysqli_query($con,"
SELECT DISTINCT academic_year FROM (
	SELECT CASE
		WHEN TRIM(COALESCE(academicyear,''))<>'' THEN academicyear
		ELSE YEAR(datetimeentry)
	END AS academic_year
	FROM tblschoolinfo
	$_YearWhereSql
	UNION
	SELECT YEAR(datetimeentry) AS academic_year
	FROM tblsubjectassignment
	$_YearWhereSql
) year_options
WHERE academic_year IS NOT NULL AND academic_year<>''
ORDER BY academic_year DESC");
if($_SQL_YEAR_OPT){
while($row_year=mysqli_fetch_array($_SQL_YEAR_OPT,MYSQLI_ASSOC)){
$_SelYear = ($_SelectedAcademicYear===(string)$row_year['academic_year']) ? "selected" : "";
echo "<option value='$row_year[academic_year]' $_SelYear>$row_year[academic_year]</option>";
}
}
echo "</select>";

$_SQL_2=mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
echo "<label for='classid'>Class</label>";
echo "<select id='classid' name='classid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
$_SelClass = ($_SelectedClassId==$row['class_entryid']) ? "selected" : "";
echo "<option value='$row[class_entryid]' $_SelClass>$row[class_name]</option>";
}
echo "</select>";

echo "<label for='termid'>Semester</label>";
echo "<select id='termid' name='termid' class='validate[required]'>";
echo "<option value=''>Select Semester</option>";
echo "<option value='1' ".($_SelectedTermId==='1' ? "selected" : "").">1</option>";
echo "<option value='2' ".($_SelectedTermId==='2' ? "selected" : "").">2</option>";
echo "<option value='3' ".($_SelectedTermId==='3' ? "selected" : "").">3</option>";
echo "</select>";

echo "<div class='ca-actions'>";
echo "<button class='button-show ca-btn ca-btn-primary' id='show_terminal_report' name='show_terminal_report'><i class='fa fa-search'></i> Show Report</button> ";
echo "<a href='continuous-assessment.php' class='button-show ca-btn ca-btn-light'><i class='fa fa-undo'></i> Reset</a>";
echo "</div>";
if($_SelectedAcademicYear!=="" || $_SelectedTermId!==""){
echo "<div class='ca-selected'><i class='fa fa-check-circle'></i> Selected: ".($_SelectedAcademicYear!=="" ? $_SelectedAcademicYear." | " : "").($_SelectedTermId!=="" ? "Semester ".$_SelectedTermId : "")."</div>";
}
echo "</fieldset>";
?>

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>
-->

</form>
</aside>
<main class="ca-panel ca-results-panel">

<form id="formID3" name="formID3" method="post" action="continuous-assessment.php">
<?php
if(isset($_GET["edit_mark"]))
{
	echo "<div class='ca-panel-heading ca-edit-heading'><span class='ca-icon'><i class='fa fa-pencil'></i></span><div><h2>Update Student Mark</h2><p>Edit the selected assessment score below.</p></div></div>";
$_SQL_ED=mysqli_query($con,"SELECT * FROM tblmark mk INNER JOIN 
	tblsystemuser su ON mk.userid=su.userid WHERE mk.markid='$_GET[edit_mark]'");
echo "<div class='ca-table-wrap'><table class='ca-table ca-edit-table'>";
echo "<caption>Mark of Student</caption>";
echo "<thead><th>STUDENT</th><th>BATCH</th><th>SUBJECT</th><th>MARK</th><th>TOTAL</th></thead>";
echo "<tbody>";
if($rows_m=mysqli_fetch_array($_SQL_ED,MYSQLI_ASSOC))
{
echo "<tr>";
echo "<td width='30%'>";
echo "<input type='hidden' id='newmarkid' name='newmarkid' value='$_GET[edit_mark]'readonly/>";

echo "<input type='hidden' id='userid' name='userid' value='$rows_m[userid]'readonly/>";

echo "$rows_m[firstname] $rows_m[othernames] $rows_m[surname] ($rows_m[userid])";
echo "</td>";

echo "<td>";
@$_BATCH="";
$_SQLBh=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[edit_batch]'");
if($rowb=mysqli_fetch_array($_SQLBh,MYSQLI_ASSOC)){
$_BATCH=$rowb["batch"];
}
echo "<input type='hidden' id='batchid' name='batchid' value='$_GET[edit_batch]'/>";
echo "$_BATCH";
echo "</td>";

echo "<td>";
//echo "<input type='hidden' id='userid' name='userid' value='$rows_m[userid]'readonly/>";
echo "$_GET[edit_subject]";
echo "</td>";

echo "<td>";
echo "<label>Old Mark</label>";
echo "<input type='text' id='oldmark' name='oldmark' value='$rows_m[mark]' readonly/>";
echo "<label>New Mark</label>";
echo "<input type='text' id='newmark' name='newmark' value='$rows_m[mark]' onchange='checkMark()' class='validate[required]'/>";
echo "</td>";

echo "<td>";
//echo "<input type='text' id='getmark' name='getmark' value='$rows_m[mark]' readonly/>";

echo "<input type='text' id='totalmark' name='totalmark' value='$rows_m[totalmark]' readonly/>";
echo "</td>";

echo "</tr>";
}
echo "<tr>";
echo "<td>";
echo "<button class='button-edit ca-btn ca-btn-primary' id='updatemark' name='updatemark'><i class='fa fa-edit'></i> Update Mark</button>";
echo "</td>";
echo "</tr>";

echo "</tbody>";
echo "</table></div>";
}
?>
</form>
	<form id="formID2" name="formID2" method="post" action="continuous-assessment.php">
		<div class="ca-panel-heading">
			<span class="ca-icon"><i class="fa fa-table"></i></span>
			<div>
				<h2>Assessment Results</h2>
				<p>Loaded records will appear here with totals, position, and grade.</p>
			</div>
		</div>
<?php
include("gradingsystem.php");
	$_grade_obj=new GradingSystem();
echo $_SESSION['Message'];
if(isset($_POST["show_terminal_report"]))
{
@$_Subject_ID=$_POST["subjectid"];
@$_Batch_ID=$_POST["batchid"];
@$_Academic_Year=$_POST["academicyear"];
@$_Class_ID=$_POST["classid"];
$_AcademicYearSql = $_Academic_Year!=="" ? " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_Academic_Year)."'" : "";
@$_Term_ID=$_POST["termid"];

include("dbstring.php");
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsubject su WHERE su.subjectid='$_Subject_ID' ORDER BY su.subjectid");
if(mysqli_num_rows($_SQL_USER)>0){
echo "<input type='hidden' name='subjectid' value='$_Subject_ID' />";
echo "<input type='hidden' name='batchid' value='$_Batch_ID' />";
echo "<input type='hidden' name='academicyear' value='$_Academic_Year' />";
echo "<input type='hidden' name='classid' value='$_Class_ID' />";
echo "<input type='hidden' name='termid' value='$_Term_ID' />";
//echo "<button class='button-pay' id='print_terminal_report' name='print_terminal_report'><i class='fa fa-print' style='color:white'></i> Print Report</button><br/><br/>";		
}
echo "<div class='ca-table-wrap'>";
echo "<table class='ca-table ca-results-table'>";
echo "<caption>";
$_SQL_USER_2=mysqli_query($con,"SELECT * FROM tblsubject su WHERE su.subjectid='$_Subject_ID'");
if($rowst=mysqli_fetch_array($_SQL_USER_2,MYSQLI_ASSOC)){
echo $rowst["subject"];
}
echo "</caption>";
echo "<thead><th>STUDENT</th><th>CLASS</th><th>SEM.</th><th>*</th><th>TYPE</th><th>MARK</th><th>POSITION</th><th>GRADE</th></thead>";
echo "<tbody>";
while($row_us=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC))
{
$_SQL_SU=mysqli_query($con,"SELECT su.* FROM tblsystemuser su
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
WHERE su.systemtype='Student' AND tr.batchid='$_Batch_ID' AND tr.class_entryid='$_Class_ID' $_AcademicYearSql
GROUP BY su.userid");
while($row_rsu=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){

//SUBJECT
echo "<tr class='ca-student-row'>";
//echo "<td colspan='1'></td>";
echo "<td align='left' colspan='8'>";
echo $row_rsu["firstname"]." ".$row_rsu["othernames"]." ".$row_rsu["surname"]." (".$row_rsu["userid"].")";
echo "</td></tr>";

//$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
//	ON ce.class_entryid=tr.class_entryid GROUP BY tr.class_entryid");

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
ON ce.class_entryid=tr.class_entryid WHERE tr.userid='$row_rsu[userid]' AND tr.batchid='$_Batch_ID' AND tr.class_entryid='$_Class_ID' $_AcademicYearSql");

if(mysqli_num_rows($_SQL_CLASS)==0){

}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
echo "<tr class='ca-class-row'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_ce['class_name']);
echo "</td></tr>";

$_StartTerm=intval($_Term_ID);
$_EndTerm=intval($_Term_ID);
for($k=$_StartTerm;$k<=$_EndTerm;$k++)
{
/*	$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_us[userid]' AND sub.subjectid='$row_rsu[subjectid]' 
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k'
		ORDER BY su.userid ASC");
		*/

		$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_rsu[userid]' AND sub.subjectid='$rowst[subjectid]' 
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k' AND
		sa.batchid='$_Batch_ID'".($_Academic_Year!=="" ? " AND ".semester_registry_assignment_year_sql("sa")."='".mysqli_real_escape_string($con,$_Academic_Year)."'" : "")."
		ORDER BY su.userid ASC");


if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr class='ca-semester-row'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='6'>";
	echo "Semester: ".$k;
	echo "</td></tr>";

	@$_TotalMark=0;
	@$_getAssignment_Id=0;


	
	
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	$_getAssignment_Id=$row['assignmentid'];

	echo "<tr>";
	echo "<td colspan='2' align='right'>";
	echo "<a  href='continuous-assessment.php?edit_mark=$row[markid]&edit_batch=$row[batchid]&edit_subject=$row[subject]'><i class='fa fa-edit' style='color:royalblue'></i></a>";
	echo "</td>";
	echo "<td>";
	echo "<a onclick=\"javascript:return confirm('Do you want to delete mark?')\" href='continuous-assessment.php?delete_mark=$row[markid]'><i class='fa fa-trash-o' style='color:red'></i></a>";
	echo "</td>";

	echo "<td align='center' width='5%' colspan='1'>";
	echo $serial=$serial+1;
	echo "</td>";

	/*echo "<td align='left' width='20%'>";
	echo $row['subject'];
	echo "</td>";
	*/
	echo "<td align='left' width='15%'>";
	echo $row['testtype'];
	echo "</td>";

	echo "<td align='center' width='15%'>";
	echo $row['mark'];
	$_TotalMark=$_TotalMark+$row['mark'];
	echo "</td>";
	echo "</tr>";
	}	
	echo "<tr class='ca-total-row'>";
	echo "<td colspan='4'>";
	echo "</td>";

	echo "<td align='right' colspan='1'>";
	echo "TOTAL:";
	echo "</td>";
	echo "<td align='center'>";
	echo $_TotalMark;
	echo "</td>";

	echo "<td align='center' width='5%'>";
	 //Get the positions
	
	 @$_Final_Position=0;

	$_position_obj_1->setPosition($_getAssignment_Id,$_TotalMark);
	$_Final_Position= $_position_obj_1->getPosition();
	echo $_Final_Position;
	echo "</td>";


		echo "<td align='center' width='5%'>";
	 //Get the positions
		@$_final_grade=0;

		$_grade_obj->setMark($_TotalMark);
		$_final_grade=$_grade_obj->getMark($_TotalMark);

	echo $_final_grade;
	echo "</td>";

	echo "</tr>";
	}
	}
	}
}
}
}
echo "</tbody>";
echo "</table>";
echo "</div>";
}
?>
</form>
</main>
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

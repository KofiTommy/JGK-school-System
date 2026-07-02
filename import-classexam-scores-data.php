
<?php
@$_SESSION['Message'] ="";
session_start();
include("dbstring.php");
include("audit_notifications.php");
include_once("score-entry-utils.php");
$_AllowedCourseStudentMap = array();
function insertExamClassScoresData($_UserId,$_Class_Name,$_Semester,$_Batch,$_SubjectId,$_ClassMark,$_ExamMark,$_TotalMark,$_AssignmentId)	
{
include("dbstring.php");
include("links.php");
global $_AllowedCourseStudentMap;
//Declaration of variables
@$_Class_EntryId="";
@$_BatchId="";

if($_UserId=="userid")
{}
else
{
if(count($_AllowedCourseStudentMap) > 0 && !isset($_AllowedCourseStudentMap[$_UserId]))
{
$_SESSION['Message']=$_SESSION['Message']."<div style='color:orange;text-align:left;background-color:white'><i class='fa fa-exclamation-triangle' style='color:orange'></i> ".$_UserId." skipped because the student did not register this course for the semester</div>";
return;
}
$sql=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_name='$_Class_Name'");
if($rowc=mysqli_fetch_array($sql,MYSQLI_ASSOC))
{
$_Class_EntryId=$rowc["class_entryid"];
}

/*
$sqlb=mysqli_query($con,"SELECT * FROM tblbatch WHERE batch='$_Batch'");
if($rowcb=mysqli_fetch_array($sqlb,MYSQLI_ASSOC))
{
$_BatchId=$rowcb["batchid"];
}
*/
$_BatchId=$_Batch;

	@$_UserFullname="";
	@$_Subject="";
	$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_UserId'");
		
	if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
	$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
	}

$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub  WHERE sub.subjectid='$_SubjectId'");
	
	if($row_subject=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_subject['subject'];
	}
//Check if class/exam mark is already entered for this assignment
$_SQL=mysqli_query($con,"SELECT * FROM tblmark mk WHERE mk.userid='$_UserId' AND mk.assignmentid='$_AssignmentId' AND mk.testtype IN ('Class Score','Exam Score')");
if(mysqli_num_rows($_SQL)==0)
{
	include("code.php");
$_MarkId=$code;

	$_SQL_EXECUTE_CLASS=mysqli_query($con,"INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
		VALUES('$_MarkId','$_AssignmentId','$_UserId','Class Score','$_ClassMark','$_TotalMark',NOW(),'active','$_SESSION[USERID]')");

include("code.php");
$_MarkId=$code;	

	$_SQL_EXECUTE_EXAM=mysqli_query($con,"INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
		VALUES('$_MarkId','$_AssignmentId','$_UserId','Exam Score','$_ExamMark','$_TotalMark',NOW(),'active','$_SESSION[USERID]')");
			if($_SQL_EXECUTE_CLASS && $_SQL_EXECUTE_EXAM)
			{
		
			//$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Mark Successfully Stored for $_Subject for $_UserFullname</div>";
			}
			else{
				$_Error=mysqli_error($con);
				$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Mark failed to save,$_Error</div>";
			}
	}
	else{
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-times' style='color:red'></i> Class/Exam mark already stored for $_Subject for $_UserFullname</div>";
		
	}
}
}
?>

<?php
require_once 'simplexlsx.class.php';
@$counter=0;
//@$message ="";

@$_TotalMark=$_POST["totalscore"];
@$_AssignmentId=trim($_POST["assignment-id"]);

if(trim((string)$_AssignmentId)!==""){
$_AssignmentIdSafe = mysqli_real_escape_string($con, trim((string)$_AssignmentId));
$_AssignmentScopeSql = mysqli_query($con, "SELECT sa.assignmentid, sa.classid, sa.batchid, sa.termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year
FROM tblsubjectassignment sa
WHERE sa.assignmentid='$_AssignmentIdSafe'
LIMIT 1");
if($_AssignmentScopeSql && ($_AssignmentScopeRow = mysqli_fetch_array($_AssignmentScopeSql, MYSQLI_ASSOC))){
$_AssignmentStudentContext = score_entry_assignment_student_context(
    $con,
    $_AssignmentScopeRow['assignmentid'],
    $_AssignmentScopeRow['classid'],
    $_AssignmentScopeRow['batchid'],
    $_AssignmentScopeRow['assignment_year'],
    $_AssignmentScopeRow['termname']
);
foreach($_AssignmentStudentContext['userids'] as $_AllowedStudentId){
    $_AllowedCourseStudentMap[trim((string)$_AllowedStudentId)] = true;
}
}
}

if(isset($_POST['submit_group_data']))
{
@$file = $_FILES['file1']['tmp_name'];
$xlsx = new SimpleXLSX($file);

//@$_CheckMark=0;
//@$_CheckSubject=0;
//	foreach ($xlsx->rows() as $fields) 
//	{
//		$_Selected_SubjectId=$fields[4];
//		$_Selected_Mark=round($fields[6],2)+round($fields[7],2);
//		if($fields[0]=="userid"){}
//		else{
//			if($_Selected_Mark>$_TotalMark){
//				$_CheckMark=1;
//			}
//		}

$_CheckMark = 0;
$_CheckSubject = 0;

// Loop through the rows of the Excel file
foreach ($xlsx->rows() as $fields) 
{
    $_Selected_SubjectId = $fields[4];

    // Ensure fields[6] and fields[7] are numeric before rounding
    $mark1 = isset($fields[6]) && is_numeric($fields[6]) ? round($fields[6], 2) : 0;
    $mark2 = isset($fields[7]) && is_numeric($fields[7]) ? round($fields[7], 2) : 0;
    $_Selected_Mark = $mark1 + $mark2;

    // Skip header row or any row where the first field is "userid"
    if ($fields[0] === "userid") {
        continue;
    }

    // Compare the selected mark to the total mark
    if ($_Selected_Mark > $_TotalMark) {
        $_CheckMark = 1;
    }




//AD_20200113080103_owKJ5ZXY9D
		//Check if it is the right subject uploaded
include("dbstring.php");
//echo $_Selected_SubjectId.", ".$_AssignmentId. "<br/>";

$_SQL_CHECK_SUB=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
WHERE sc.subjectid='$_Selected_SubjectId' AND sa.assignmentid='$_AssignmentId'");
if(mysqli_num_rows($_SQL_CHECK_SUB)>0){$_CheckSubject=0;}
else{$_CheckSubject=1;}


/*$_SQL_CHECK_SUB=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
WHERE sc.subjectid='0132WFjcN' AND sa.assignmentid='AD_20200113080103_owKJ5ZXY9D'");
if(mysqli_num_rows($_SQL_CHECK_SUB)>0){$_CheckSubject=0;}
else{$_CheckSubject=1;}
*/

}
//	}
//Check if mark entered is more than the total mark
if($_CheckMark==1){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white;'>Total Mark is less than the mark entered</div>";
}
elseif($_CheckSubject==1){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white;'>Subject Uploaded does not match!!</div>";

}
else/*No mark is greater than the total mark*/
{
	foreach($xlsx->rows() as $field)
	{
	$_UserId = $field[0];
	$_Class_Name = trim($field[1]);
	$_Semester =$field[2];
	$_Batch =$field[3];
	$_SubjectId=$field[4];
	$_ClassMark=$field[6];
	$_ExamMark=$field[7];

	$counter = $counter + 1;
	insertExamClassScoresData($_UserId,$_Class_Name,$_Semester,$_Batch,$_SubjectId,$_ClassMark,$_ExamMark,$_TotalMark,$_AssignmentId);
	}
}
	if($counter>0){
	$_SESSION['Message'] =$_SESSION['Message']."<div style='background-color:white;color:green;' align='center'>Class & Exam Scores Data Successfully Uploaded </div><br>";
    if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
        logSystemChange(
            $con,
            "SCORE_UPLOAD_CLASS_EXAM",
            "Teacher uploaded class and exam scores. Rows processed: ".$counter.", Assignment: ".$_AssignmentId.", Total: ".$_TotalMark
        );
    }
	}
	else{
	$_SESSION['Message'] =$_SESSION['Message']."<div style='background-color:white;color:red;' align='center'>Class & Exam Scores Data Failed To Upload</div><br>";
	}
}
?>
<?php
echo $_SESSION['Message'];
?>

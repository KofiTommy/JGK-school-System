<?php
session_start();
if(!isset($_SESSION['Message'])){
$_SESSION['Message']="";
}
?>
<?php
include("dbstring.php");
include("audit_notifications.php");
include_once("semester-registry-utils.php");
if(file_exists(__DIR__.DIRECTORY_SEPARATOR."score-entry-utils.php")){
include_once("score-entry-utils.php");
}
semester_registry_ensure_academic_year_column($con);
if(!function_exists('score_report_session_label')){
function score_report_session_label($dateTimeValue, $batchLabel, $termValue, $yearOverride = ""){
    $yearValue = semester_registry_normalize_year($yearOverride);
    if(trim((string)$dateTimeValue) !== ""){
        if($yearValue === ""){
            $time = strtotime((string)$dateTimeValue);
            if($time){
                $yearValue = date("Y", $time);
            }
        }
    }
    if($yearValue === ""){
        $yearValue = date("Y");
    }

    $batchText = trim((string)$batchLabel);
    if($batchText === ""){
        $batchText = "Not Set";
    }

    $termText = trim((string)$termValue);
    if($termText === ""){
        $termText = "Not Set";
    }

    return trim($yearValue." Batch ".$batchText." Semester ".$termText);
}
}
if(!function_exists('score_report_safe')){
function score_report_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}
if(!function_exists('score_report_is_admin_viewer')){
function score_report_is_admin_viewer(){
    return (
        isset($_SESSION["ACCESSLEVEL"], $_SESSION["SYSTEMTYPE"]) &&
        ($_SESSION["ACCESSLEVEL"]=="administrator" || $_SESSION["ACCESSLEVEL"]=="user") &&
        ($_SESSION["SYSTEMTYPE"]=="super_user" || $_SESSION["SYSTEMTYPE"]=="normal_user" || $_SESSION["SYSTEMTYPE"]=="User")
    );
}
}
if(!function_exists('score_report_link_is_active')){
function score_report_link_is_active($row, $classId, $termId, $subjectId, $batchId, $yearBatch){
    $rowTerm = '';
    if(isset($row["assignment_termname"])){
        $rowTerm = trim((string)$row["assignment_termname"]);
    }
    if($rowTerm === '' && isset($row["termname"])){
        $rowTerm = trim((string)$row["termname"]);
    }
    $rowYear = "";
    if(isset($row["assignment_year"])){
        $rowYear = semester_registry_normalize_year($row["assignment_year"]);
    }
    if($rowYear === ""){
        $rowYear = semester_registry_normalize_year(date("Y", strtotime((string)$row["assignment_datetimeentry"])));
    }
    return (
        trim((string)$row["class_entryid"]) === trim((string)$classId) &&
        $rowTerm === trim((string)$termId) &&
        trim((string)$row["subjectid"]) === trim((string)$subjectId) &&
        trim((string)$row["batchid"]) === trim((string)$batchId) &&
        trim((string)$rowYear) === trim((string)$yearBatch)
    );
}
}
if(!function_exists('score_report_assignment_student_ids')){
function score_report_assignment_student_ids($con, $assignmentRow){
    $studentIds = array();
    if(function_exists('score_entry_assignment_student_context')){
        $context = score_entry_assignment_student_context(
            $con,
            isset($assignmentRow['assignmentid']) ? $assignmentRow['assignmentid'] : '',
            isset($assignmentRow['class_entryid']) ? $assignmentRow['class_entryid'] : '',
            isset($assignmentRow['batchid']) ? $assignmentRow['batchid'] : '',
            isset($assignmentRow['assignment_year']) ? $assignmentRow['assignment_year'] : '',
            isset($assignmentRow['termname']) ? $assignmentRow['termname'] : ''
        );
        if(isset($context['userids']) && is_array($context['userids'])){
            foreach($context['userids'] as $userId){
                $userId = trim((string)$userId);
                if($userId !== ''){
                    $studentIds[$userId] = $userId;
                }
            }
        }
    }

    if(empty($studentIds) && isset($assignmentRow['assignmentid'])){
        $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentRow['assignmentid']));
        $fallbackSql = mysqli_query($con, "SELECT DISTINCT userid FROM tblmark WHERE assignmentid='$assignmentIdEsc' AND status='active' ORDER BY userid ASC");
        if($fallbackSql){
            while($fallbackRow = mysqli_fetch_array($fallbackSql, MYSQLI_ASSOC)){
                $userId = trim((string)$fallbackRow['userid']);
                if($userId !== ''){
                    $studentIds[$userId] = $userId;
                }
            }
        }
    }

    return array_values($studentIds);
}
}
if(!function_exists('score_report_student_rows')){
function score_report_student_rows($con, $studentIds){
    $rows = array();
    if(!is_array($studentIds) || count($studentIds) === 0){
        return $rows;
    }
    $parts = array();
    foreach($studentIds as $studentId){
        $studentId = trim((string)$studentId);
        if($studentId !== ''){
            $parts[] = "'".mysqli_real_escape_string($con, $studentId)."'";
        }
    }
    if(count($parts) === 0){
        return $rows;
    }
    $sql = mysqli_query($con, "SELECT userid, firstname, othernames, surname, systemtype, status
        FROM tblsystemuser
        WHERE userid IN (".implode(",", $parts).")
          AND systemtype='Student'
        ORDER BY userid ASC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[trim((string)$row['userid'])] = $row;
        }
    }
    return $rows;
}
}
@$_YearBatchFilter = semester_registry_normalize_year($_GET["year_batch"] ?? "");
@$_YearBatchFilterSafe = mysqli_real_escape_string($con, $_YearBatchFilter);
@$_TermFilter = isset($_GET["term_filter"]) ? trim((string)$_GET["term_filter"]) : "";
if($_TermFilter !== "1" && $_TermFilter !== "2"){
    $_TermFilter = "";
}
@$_TermFilterSafe = mysqli_real_escape_string($con, $_TermFilter);
@$_CurrentClassId = isset($_GET["class_id"]) ? trim($_GET["class_id"]) : "";
@$_CurrentTermId = isset($_GET["term_id"]) ? trim($_GET["term_id"]) : "";
@$_CurrentSubjectId = isset($_GET["subject_id"]) ? trim($_GET["subject_id"]) : "";
@$_CurrentBatchId = isset($_GET["batchid"]) ? trim($_GET["batchid"]) : "";
@$_CurrentClassIdSafe = mysqli_real_escape_string($con, $_CurrentClassId);
@$_CurrentTermIdSafe = mysqli_real_escape_string($con, $_CurrentTermId);
@$_CurrentSubjectIdSafe = mysqli_real_escape_string($con, $_CurrentSubjectId);
@$_CurrentBatchIdSafe = mysqli_real_escape_string($con, $_CurrentBatchId);
@$_Mark=$_POST['marks'];
@$_AssignmentId=$_POST['assignmentid'];
@$_UserId=$_POST['userid'];
@$_TotalMark=$_POST['totalscore'];
@$_Recordedby=$_SESSION['USERID'];

if(
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    $_CurrentClassId === '' &&
    $_CurrentSubjectId === '' &&
    !isset($_GET['edit_mark']) &&
    !isset($_GET['delete_mark'])
){
    $_AutoWhere = array("sa.status='active'");
    if($_YearBatchFilterSafe !== ""){
        $_AutoWhere[] = semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe'";
    }
    if($_TermFilterSafe !== ""){
        $_AutoWhere[] = "sa.termname='$_TermFilterSafe'";
    }
    if(score_report_is_admin_viewer()){
        // no extra teacher scope
    }elseif(isset($_SESSION["ACCESSLEVEL"], $_SESSION["SYSTEMTYPE"]) && $_SESSION["ACCESSLEVEL"]=="user" && $_SESSION["SYSTEMTYPE"]=="Teacher"){
        $_AutoWhere[] = "sa.userid='".mysqli_real_escape_string($con, $_SESSION['USERID'])."'";
    }

    $_AutoSql = "SELECT sc.classid, sa.termname, sc.subjectid, sa.batchid
        FROM tblsubjectassignment sa
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        WHERE ".implode(" AND ", $_AutoWhere)."
        ORDER BY sa.datetimeentry DESC, sa.termname ASC
        LIMIT 1";
    $_AutoRes = mysqli_query($con, $_AutoSql);
    if($_AutoRes && ($_AutoRow = mysqli_fetch_array($_AutoRes, MYSQLI_ASSOC))){
        header(
            "Location: scores-report.php?class_id=".urlencode((string)$_AutoRow['classid']).
            "&term_id=".urlencode((string)$_AutoRow['termname']).
            "&subject_id=".urlencode((string)$_AutoRow['subjectid']).
            "&batchid=".urlencode((string)$_AutoRow['batchid']).
            "&year_batch=".urlencode((string)$_YearBatchFilter).
            "&term_filter=".urlencode((string)$_TermFilter)
        );
        exit();
    }
}

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

if(isset($_POST["update_mark"]))
{
@$_MarkId = $_POST["markid"];
@$_NewMark = trim($_POST["new_mark"]);
@$_ReturnClass = $_POST["return_class_id"];
@$_ReturnTerm = $_POST["return_term_id"];
@$_ReturnSubject = $_POST["return_subject_id"];
@$_ReturnBatch = $_POST["return_batchid"];
@$_ReturnYearBatch = isset($_POST["return_year_batch"]) ? $_POST["return_year_batch"] : "";

$_MarkIdSafe = mysqli_real_escape_string($con, $_MarkId);
$_SQL_AUTH = mysqli_query($con,"SELECT mk.markid,mk.totalmark,sa.userid AS teacher_userid
 ,mk.mark AS old_mark,mk.userid AS student_userid,mk.testtype
FROM tblmark mk
INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
WHERE mk.markid='$_MarkIdSafe' LIMIT 1");

if($row_auth=mysqli_fetch_array($_SQL_AUTH,MYSQLI_ASSOC))
{
    $isAdmin = (isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']=="administrator");
    $isOwnerTeacher = ($row_auth['teacher_userid']==$_SESSION['USERID']);

    if(!$isAdmin && !$isOwnerTeacher){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not allowed to edit this mark.</div>";
    } else if(!is_numeric($_NewMark) || $_NewMark<0 || $_NewMark>$row_auth['totalmark']){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Invalid mark. It must be between 0 and ".$row_auth['totalmark'].".</div>";
    } else {
        $_NewMarkSafe = mysqli_real_escape_string($con, $_NewMark);
        $_RecordedbySafe = mysqli_real_escape_string($con, $_SESSION['USERID']);
        $_SQL_EXECUTE=mysqli_query($con,"UPDATE tblmark SET mark='$_NewMarkSafe', datetimeentry=NOW(), recordedby='$_RecordedbySafe' WHERE markid='$_MarkIdSafe'");
        if($_SQL_EXECUTE){
            if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
                logSystemChange(
                    $con,
                    "SCORE_EDIT",
                    "Teacher edited ".$row_auth['testtype']." from ".$row_auth['old_mark']." to ".$_NewMark." for student ".$row_auth['student_userid'].". MarkId: ".$_MarkId
                );
            }
            $_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Mark updated successfully.</div>";
        } else {
            $_Error=mysqli_error($con);
            $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Failed to update mark: $_Error</div>";
        }
    }
} else {
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Mark record not found.</div>";
}

header("Location: scores-report.php?class_id=".urlencode($_ReturnClass)."&term_id=".urlencode($_ReturnTerm)."&subject_id=".urlencode($_ReturnSubject)."&batchid=".urlencode($_ReturnBatch)."&year_batch=".urlencode($_ReturnYearBatch));
exit();
}

if(isset($_POST["bulk_delete_students_scores"]))
{
@$_ReturnClass = isset($_POST["return_class_id"]) ? trim($_POST["return_class_id"]) : "";
@$_ReturnTerm = isset($_POST["return_term_id"]) ? trim($_POST["return_term_id"]) : "";
@$_ReturnSubject = isset($_POST["return_subject_id"]) ? trim($_POST["return_subject_id"]) : "";
@$_ReturnBatch = isset($_POST["return_batchid"]) ? trim($_POST["return_batchid"]) : "";
@$_ReturnYearBatch = isset($_POST["return_year_batch"]) ? trim($_POST["return_year_batch"]) : "";
@$_BulkStudents = (isset($_POST["bulk_userid"]) && is_array($_POST["bulk_userid"])) ? $_POST["bulk_userid"] : array();

$_RedirectUrl = "scores-report.php?class_id=".urlencode($_ReturnClass)."&term_id=".urlencode($_ReturnTerm)."&subject_id=".urlencode($_ReturnSubject)."&batchid=".urlencode($_ReturnBatch)."&year_batch=".urlencode($_ReturnYearBatch);

if($_ReturnClass=="" || $_ReturnSubject=="" || $_ReturnBatch==""){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Bulk delete failed: missing class, subject or batch context.</div>";
    header("Location: ".$_RedirectUrl);
    exit();
}

if(count($_BulkStudents)<1){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Select at least one student for bulk delete.</div>";
    header("Location: ".$_RedirectUrl);
    exit();
}

$_StudentInList=array();
$_SeenStudents=array();
foreach($_BulkStudents as $_StudentId){
    $_StudentId=trim($_StudentId);
    if($_StudentId=="" || isset($_SeenStudents[$_StudentId])){
        continue;
    }
    $_SeenStudents[$_StudentId]=1;
    $_StudentInList[]="'".mysqli_real_escape_string($con,$_StudentId)."'";
}

if(count($_StudentInList)<1){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>No valid student selected for bulk delete.</div>";
    header("Location: ".$_RedirectUrl);
    exit();
}

$_ClassSafe = mysqli_real_escape_string($con, $_ReturnClass);
$_SubjectSafe = mysqli_real_escape_string($con, $_ReturnSubject);
$_BatchSafe = mysqli_real_escape_string($con, $_ReturnBatch);
$_TermSafe = mysqli_real_escape_string($con, $_ReturnTerm);
$_SessionUserSafe = mysqli_real_escape_string($con, $_SESSION['USERID']);
$_StudentInSql = implode(",", $_StudentInList);
$isAdmin = (isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']=="administrator");
$_TeacherScopeSql = (!$isAdmin ? " AND sa.userid='$_SessionUserSafe' " : "");

if(!$isAdmin){
    $_SQL_ASSIGN = mysqli_query(
        $con,
        "SELECT sa.assignmentid
         FROM tblsubjectassignment sa
         INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
         WHERE sa.userid='$_SessionUserSafe'
           AND sc.classid='$_ClassSafe'
           AND sc.subjectid='$_SubjectSafe'
           AND sa.batchid='$_BatchSafe'
           ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")."
           ".($_TermSafe!="" ? " AND sa.termname='$_TermSafe' " : "")."
         LIMIT 1"
    );
    if(!$_SQL_ASSIGN || mysqli_num_rows($_SQL_ASSIGN)<1){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not allowed to bulk delete these scores.</div>";
        header("Location: ".$_RedirectUrl);
        exit();
    }
}

mysqli_begin_transaction($con);
$_DeleteCount=0;
$_DeleteStudentCount=0;
$_DeletedStudents=array();
$_DeleteError="";

$_SQL_CHECK_DELETE = mysqli_query(
    $con,
    "SELECT mk.markid,mk.userid
     FROM tblmark mk
     INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
     INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
     WHERE sc.classid='$_ClassSafe'
       AND sc.subjectid='$_SubjectSafe'
       AND sa.batchid='$_BatchSafe'"
    .($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")
    .($_TermSafe!="" ? " AND sa.termname='$_TermSafe' " : "")
    ." AND mk.userid IN ($_StudentInSql)
       AND mk.testtype IN ('Class Score','Exam Score')
       $_TeacherScopeSql"
);

if($_SQL_CHECK_DELETE){
    while($row_del=mysqli_fetch_array($_SQL_CHECK_DELETE,MYSQLI_ASSOC)){
        $_DeleteCount++;
        $_DeletedStudents[$row_del['userid']]=1;
    }
    $_DeleteStudentCount=count($_DeletedStudents);
}else{
    $_DeleteError=mysqli_error($con);
}

if($_DeleteError==""){
    $_SQL_DELETE = mysqli_query(
        $con,
        "DELETE mk
         FROM tblmark mk
         INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
         INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
         WHERE sc.classid='$_ClassSafe'
           AND sc.subjectid='$_SubjectSafe'
           AND sa.batchid='$_BatchSafe'"
        .($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")
        .($_TermSafe!="" ? " AND sa.termname='$_TermSafe' " : "")
        ." AND mk.userid IN ($_StudentInSql)
           AND mk.testtype IN ('Class Score','Exam Score')
           $_TeacherScopeSql"
    );
    if(!$_SQL_DELETE){
        $_DeleteError=mysqli_error($con);
    }
}

if($_DeleteError!=""){
    mysqli_rollback($con);
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Bulk delete failed: $_DeleteError</div>";
}else{
    mysqli_commit($con);
    if($_DeleteCount>0){
        if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
            logSystemChange(
                $con,
                "SCORE_BULK_DELETE",
                "Teacher bulk deleted ".$_DeleteCount." score row(s) for ".$_DeleteStudentCount." student(s). Types: Class Score/Exam Score. Class: ".$_ReturnClass.", Subject: ".$_ReturnSubject.", Batch: ".$_ReturnBatch
            );
        }
        $_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Bulk delete successful. Removed ".$_DeleteCount." mark row(s) for ".$_DeleteStudentCount." student(s).</div>";
    }else{
        $_SESSION['Message']="<div style='color:#8a6d3b;text-align:center;background-color:white'>No Class Score/Exam Score found for selected students in this report context.</div>";
    }
}

header("Location: ".$_RedirectUrl);
exit();
}

if(isset($_GET["delete_mark"]))
{
$_MarkIdSafe = mysqli_real_escape_string($con, $_GET["delete_mark"]);
$_SQL_AUTH = mysqli_query($con,"SELECT mk.markid,sa.userid AS teacher_userid
 ,mk.mark,mk.userid AS student_userid,mk.testtype
FROM tblmark mk
INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
WHERE mk.markid='$_MarkIdSafe' LIMIT 1");
if($row_auth=mysqli_fetch_array($_SQL_AUTH,MYSQLI_ASSOC))
{
    $isAdmin = (isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']=="administrator");
    $isOwnerTeacher = ($row_auth['teacher_userid']==$_SESSION['USERID']);
    if(!$isAdmin && !$isOwnerTeacher){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not allowed to delete this mark.</div>";
    } else {
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_MarkIdSafe'");
	if($_SQL_EXECUTE){
    if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
        logSystemChange(
            $con,
            "SCORE_DELETE",
            "Teacher deleted ".$row_auth['testtype']." mark ".$row_auth['mark']." for student ".$row_auth['student_userid'].". MarkId: ".$_MarkIdSafe
        );
    }
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
	}
    }
} else {
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Mark record not found.</div>";
}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/scores-report.css">
</head>
<body class="scores-report-page">
<div class="header">
<?php
include("menu.php");
?>		
</div>

<?php
$_ViewerLabel = score_report_is_admin_viewer() ? "Admin Score Workspace" : "Teacher Score Workspace";
$_SelectionLabel = $_CurrentClassId !== "" ? "Report loaded" : "Choose a subject";
$_YearLabel = $_YearBatchFilter !== "" ? $_YearBatchFilter : "All Years";
$_SemesterScope = $_CurrentTermId !== "" ? $_CurrentTermId : $_TermFilter;
$_SemesterLabel = $_SemesterScope !== "" ? "Semester ".$_SemesterScope : "All Semesters";
?>

<div class="main-platform scores-report-shell"><br/>
<section class="scores-report-hero">
    <div class="scores-report-hero__copy">
        <span class="scores-report-kicker">Score Reports</span>
        <h1>Review and manage score entries.</h1>
        <p>Select a subject and academic year to review the score report.</p>
        <div class="scores-report-hero__stats">
            <article class="scores-report-stat-card">
                <span>Viewer</span>
                <strong><?php echo score_report_safe($_ViewerLabel); ?></strong>
            </article>
            <article class="scores-report-stat-card">
                <span>Academic Year</span>
                <strong><?php echo score_report_safe($_YearLabel); ?></strong>
            </article>
            <article class="scores-report-stat-card">
                <span>Semester</span>
                <strong><?php echo score_report_safe($_SemesterLabel); ?></strong>
            </article>
            <article class="scores-report-stat-card">
                <span>Status</span>
                <strong><?php echo score_report_safe($_SelectionLabel); ?></strong>
            </article>
        </div>
    </div>
    <div class="scores-report-hero__aside">
        <div class="scores-report-tip-card">
            <span class="scores-report-tip-card__eyebrow">Better flow</span>
            <h2>Stay focused on one session at a time</h2>
            <ul>
                <li>Use the year filter before choosing the subject report.</li>
                <li>Edit only the score row you want to correct.</li>
                <li>Bulk delete works only within the visible report context.</li>
            </ul>
        </div>
    </div>
</section>

<div class="scores-report-grid">
<div class="scores-report-column scores-report-column--sidebar">
<div class="form-entry scores-report-panel">
<form id="formID" name="formID" method="get" action="scores-report.php" class="scores-report-filter-form">
<div class="scores-report-panel__header">
<span class="scores-report-panel__eyebrow">Filter</span>
<h4>Subjects</h4>
<p>Open the report you want to review.</p>
</div>
<?php
include("dbstring.php");
echo "<div class='scores-report-field-group'>";
echo "<label for='year_batch'>Academic Year</label>";
echo "<select id='year_batch' name='year_batch' onchange='this.form.submit()'>";
echo "<option value=''>All Years</option>";
$_SQL_BF=mysqli_query($con,"SELECT DISTINCT YEAR(datetimeentry) AS academicyear FROM tblsubjectassignment ORDER BY academicyear DESC");
while($row_bf=mysqli_fetch_array($_SQL_BF,MYSQLI_ASSOC)){
    $_YearOption = trim((string)$row_bf["academicyear"]);
    $_sel = ($_YearBatchFilter===$_YearOption) ? "selected" : "";
    echo "<option value='$_YearOption' $_sel>$_YearOption</option>";
}
echo "</select>";
echo "</div>";
echo "<div class='scores-report-field-group'>";
echo "<label for='term_filter'>Semester</label>";
echo "<select id='term_filter' name='term_filter' onchange='this.form.submit()'>";
echo "<option value=''>All Semesters</option>";
echo "<option value='1'".($_TermFilter==="1" ? " selected" : "").">Semester 1</option>";
echo "<option value='2'".($_TermFilter==="2" ? " selected" : "").">Semester 2</option>";
echo "</select>";
echo "</div>";
?>
<?php	
if(($_SESSION["ACCESSLEVEL"]=="administrator"||$_SESSION["ACCESSLEVEL"]=="user") && ($_SESSION["SYSTEMTYPE"]=="super_user" ||$_SESSION["SYSTEMTYPE"]=="normal_user"||$_SESSION["SYSTEMTYPE"]=="User"))
{
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT sa.*, sa.termname AS assignment_termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.*, bch.batch FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
	WHERE sa.status='active' ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")."
	".($_TermFilterSafe!="" ? " AND sa.termname='$_TermFilterSafe' " : "")."
	ORDER BY ce.class_name,sa.termname ASC");

	$_HasSubjectLinks = false;
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
		$_HasSubjectLinks = true;
		$_SessionLabel = score_report_session_label($row['assignment_datetimeentry'], $row['batch'], $row['assignment_termname'], isset($row['assignment_year']) ? $row['assignment_year'] : "");
		$_IsActiveLink = ($_CurrentClassId==$row['class_entryid'] && $_CurrentTermId==$row['assignment_termname'] && $_CurrentSubjectId==$row['subjectid'] && $_CurrentBatchId==$row['batchid']);
		echo "<a class='scores-report-subject-link".($_IsActiveLink ? " scores-report-subject-link--active" : "")."' href='scores-report.php?class_id=$row[class_entryid]&term_id=$row[assignment_termname]&subject_id=$row[subjectid]&batchid=$row[batchid]&year_batch=".urlencode($_YearBatchFilter)."&term_filter=".urlencode($_TermFilter)."'><span class='scores-report-subject-link__class'>$row[class_name]</span><strong class='scores-report-subject-link__subject'>$row[subject]</strong><span class='scores-report-subject-link__session'>$_SessionLabel</span></a>";
	}
	if(!$_HasSubjectLinks){
		echo "<div class='scores-report-empty-state'><h3>No subject reports found</h3><p>No active score-report subjects matched the current year and semester filter.</p></div>";
	}
}
elseif($_SESSION["ACCESSLEVEL"]=="user" && $_SESSION["SYSTEMTYPE"]=="Teacher")
{
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT sa.*, sa.termname AS assignment_termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.*, bch.batch FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
	WHERE sa.userid='$_SESSION[USERID]' AND sa.status='active' ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")." ".($_TermFilterSafe!="" ? " AND sa.termname='$_TermFilterSafe' " : "")." ORDER BY ce.class_name,sa.termname ASC");

	$_HasSubjectLinks = false;
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
		$_HasSubjectLinks = true;
		$_SessionLabel = score_report_session_label($row['assignment_datetimeentry'], $row['batch'], $row['assignment_termname'], isset($row['assignment_year']) ? $row['assignment_year'] : "");
		$_IsActiveLink = ($_CurrentClassId==$row['class_entryid'] && $_CurrentTermId==$row['assignment_termname'] && $_CurrentSubjectId==$row['subjectid'] && $_CurrentBatchId==$row['batchid']);
		echo "<a class='scores-report-subject-link".($_IsActiveLink ? " scores-report-subject-link--active" : "")."' href='scores-report.php?class_id=$row[class_entryid]&term_id=$row[assignment_termname]&subject_id=$row[subjectid]&batchid=$row[batchid]&year_batch=".urlencode($_YearBatchFilter)."&term_filter=".urlencode($_TermFilter)."'><span class='scores-report-subject-link__class'>$row[class_name]</span><strong class='scores-report-subject-link__subject'>$row[subject]</strong><span class='scores-report-subject-link__session'>$_SessionLabel</span></a>";
	}
	if(!$_HasSubjectLinks){
		echo "<div class='scores-report-empty-state'><h3>No subject reports found</h3><p>No active score-report subjects matched this teacher, academic-year, and semester filter.</p></div>";
	}
}
?>

</form>
</div>
</div>
<div class="scores-report-column scores-report-column--main">
<div class="form-entry scores-report-panel scores-report-panel--main">
<div class="scores-report-panel__header">
<span class="scores-report-panel__eyebrow">Report</span>
<h4>Scores Report</h4>
<p><?php echo $_CurrentClassId !== "" ? score_report_safe("Selected batch ".$_CurrentBatchId.", semester ".$_CurrentTermId) : "Select a subject to load the report details here."; ?></p>
</div>
<form id="formID2" name="formID2" method="post" action="scores-report.php" class="scores-report-main-form">
<input type="hidden" name="return_class_id" value="<?php echo htmlspecialchars($_CurrentClassId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_term_id" value="<?php echo htmlspecialchars($_CurrentTermId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_subject_id" value="<?php echo htmlspecialchars($_CurrentSubjectId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_batchid" value="<?php echo htmlspecialchars($_CurrentBatchId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_year_batch" value="<?php echo htmlspecialchars($_YearBatchFilter,ENT_QUOTES); ?>" />
<?php
include("positions.php");
include("gradingsystem.php");
@$_position_obj_1=new Position;
@$_grade_obj=new GradingSystem;

if(trim((string)$_SESSION['Message']) !== ""){
echo "<div class='scores-report-flash'>".$_SESSION['Message']."</div>";
$_SESSION['Message']="";
}
include("dbstring.php");

if(isset($_GET['class_id']))
{
echo "<div class='scores-report-toolbar'>";
echo "<label class='scores-report-select-all'><input type='checkbox' id='bulk_select_students' onclick='toggleBulkStudents(this)' /> <span>Select all visible students</span></label>";
echo "<button type='submit' name='bulk_delete_students_scores' onclick='return confirmBulkDeleteStudents();' class='scores-report-button scores-report-button--danger'><i class='fa fa-trash-o'></i> Delete Selected Class + Exam Scores</button>";
echo "</div>";
$_ReportTeacherScope = score_report_is_admin_viewer() ? "" : " AND sa.userid='$_SESSION[USERID]'";
$_SQL_2=mysqli_query($con,"SELECT sa.*, sa.termname AS assignment_termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.*, bch.batch FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
	WHERE sa.status='active'
      AND sc.classid='$_CurrentClassIdSafe'
      AND sc.subjectid='$_CurrentSubjectIdSafe'
      AND sa.batchid='$_CurrentBatchIdSafe'
      AND sa.termname='$_CurrentTermIdSafe'
      $_ReportTeacherScope ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe'" : "")." ORDER BY ce.class_name,sa.termname ASC");


//$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student'  ORDER BY su.userid");

echo "<div class='scores-report-table-wrap'>";
echo "<table width='100%' class='scores-report-table'>";
echo "<caption>Scores Report</caption>";
echo "<thead><th>*</th><th>SUBJECT</th><th>STUDENT</th><th>CLASS</th><th>SESSION</th><th>TYPE</th><th>MARK</th><th>TOTAL</th><th>POSITION</th><th>GRADE</th></thead>";
echo "<tbody>";
@$serial=0;
$_ReportHasRows = false;
$_RenderedStudentRows = false;
while($row_sub=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
{
$_ReportHasRows = true;
@$_BatchName="";
$_SQL_Batch=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$row_sub[batchid]'");
if($rowb=mysqli_fetch_array($_SQL_Batch,MYSQLI_ASSOC)){
$_BatchName=$rowb["batch"];	
}
$_SessionHeading = score_report_session_label($row_sub['assignment_datetimeentry'], $_BatchName, $row_sub['assignment_termname'], isset($row_sub['assignment_year']) ? $row_sub['assignment_year'] : "");
echo "<tr class='scores-report-row scores-report-row--section'><td align='left' colspan='10'>".strtoupper($row_sub['subject']).": ".strtoupper($_SessionHeading) ."</td></tr>";

$_SQL_CLASS=mysqli_query($con,"SELECT DISTINCT tr.userid, ce.class_name, ce.class_entryid
    FROM tblclassentry ce
    INNER JOIN tbltermregistry tr ON ce.class_entryid=tr.class_entryid
    WHERE tr.batchid='$row_sub[batchid]'
      AND tr.class_entryid='$row_sub[class_entryid]'
    ORDER BY tr.userid ASC");
if($_SQL_CLASS && mysqli_num_rows($_SQL_CLASS)>0){
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$row_ce[userid]' AND su.systemtype='Student' ORDER BY su.userid");
if(!$_SQL_USER || !($row_rsu=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC))){
    continue;
}
$_RenderedStudentRows = true;
echo "<tr class='scores-report-row scores-report-row--student'>";
echo "<td colspan='1'>";
echo "<input type='checkbox' class='bulk-student-checkbox' name='bulk_userid[]' value='$row_rsu[userid]' style='margin-right:6px;' />";
echo $serial=$serial+1 .".";
echo "</td>";
echo "<td align='left' colspan='9'>";
echo strtoupper($row_rsu['firstname']." ".$row_rsu['othernames']." ".$row_rsu['surname']);
echo "(".$row_rsu['userid'].")";
echo "</td></tr>";

for($k=(int)$row_sub['assignment_termname'];$k<=(int)$row_sub['assignment_termname'];$k++){
$_AssignmentIdSafe = mysqli_real_escape_string($con, $row_sub['assignmentid']);
$_StudentIdSafe = mysqli_real_escape_string($con, $row_rsu['userid']);
$_SQL_EXECUTE=mysqli_query($con,"SELECT mk.*, su.userid
        FROM tblmark mk
        INNER JOIN tblsystemuser su ON mk.userid=su.userid
        WHERE mk.assignmentid='$_AssignmentIdSafe'
          AND mk.status='active'
          AND su.userid='$_StudentIdSafe'
        ORDER BY mk.datetimeentry ASC, mk.markid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr class='scores-report-row scores-report-row--session'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='3'>$row_ce[class_name]</td>";
	echo "<td colspan='5'>";
	echo "SESSION: ".score_report_session_label($row_sub['assignment_datetimeentry'], $_BatchName, $k, isset($row_sub['assignment_year']) ? $row_sub['assignment_year'] : "");
	echo "</td></tr>";

	@$_TotalMark=0;
    @$_getAssignment_Id="";
    @$serial_mark=0;

	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	$_getAssignment_Id=$row['assignmentid'];
	echo "<tr class='scores-report-row scores-report-row--mark' id='mark-row-$row[markid]'>";
	echo "<td colspan='4' align='right'>";
	echo "<a class='scores-report-action scores-report-action--edit' title='Edit score: $row[mark]' href='scores-report.php?class_id=$_GET[class_id]&term_id=$_GET[term_id]&subject_id=$_GET[subject_id]&batchid=$_GET[batchid]&year_batch=".urlencode($_YearBatchFilter)."&edit_mark=$row[markid]#edit-mark-$row[markid]'><i class='fa fa-edit'></i></a> ";
	echo "<a class='scores-report-action scores-report-action--delete' onclick=\"javascript:return confirm('Do you to delete mark?')\" title='Delete score: $row[mark]' href='scores-report.php?class_id=$_GET[class_id]&term_id=$_GET[term_id]&subject_id=$_GET[subject_id]&batchid=$_GET[batchid]&year_batch=".urlencode($_YearBatchFilter)."&delete_mark=$row[markid]'><i class='fa fa-trash-o'></i></a>";
	echo "</td>";

	echo "<td align='center' width='5%' colspan='1'>";
	echo $serial_mark=$serial_mark+1;
	echo "</td>";

	echo "<td align='left' width='15%'>";
	echo $row['testtype'];
	echo "</td>";

	echo "<td align='center' width='15%'>";
	echo $row['mark'];
	$_TotalMark=$_TotalMark+$row['mark'];
	echo "</td>";

	echo "</tr>";

    if(isset($_GET['edit_mark']) && $_GET['edit_mark']==$row['markid']){
    echo "<tr class='scores-report-row scores-report-row--edit' id='edit-mark-$row[markid]'>";
    echo "<td colspan='8'>";
    echo "<form method='post' action='scores-report.php' class='scores-report-edit-form'>";
    echo "<input type='hidden' name='markid' value='$row[markid]' />";
    echo "<input type='hidden' name='return_class_id' value='$_GET[class_id]' />";
    echo "<input type='hidden' name='return_term_id' value='$_GET[term_id]' />";
    echo "<input type='hidden' name='return_subject_id' value='$_GET[subject_id]' />";
    echo "<input type='hidden' name='return_batchid' value='$_GET[batchid]' />";
    echo "<input type='hidden' name='return_year_batch' value='".htmlspecialchars($_YearBatchFilter,ENT_QUOTES)."' />";
    echo "<label>Edit Mark (Max $row[totalmark])</label>";
    echo "<input type='number' name='new_mark' min='0' max='$row[totalmark]' value='$row[mark]' step='0.01' required />";
    echo "<button class='scores-report-button scores-report-button--save' name='update_mark' value='1'><i class='fa fa-save'></i> Save</button> ";
    echo "<a class='scores-report-button scores-report-button--ghost' href='scores-report.php?class_id=$_GET[class_id]&term_id=$_GET[term_id]&subject_id=$_GET[subject_id]&batchid=$_GET[batchid]&year_batch=".urlencode($_YearBatchFilter)."#mark-row-$row[markid]'>Cancel</a>";
    echo "</form>";
    echo "</td>";
    echo "</tr>";
    }
	}	
	echo "<tr class='scores-report-row scores-report-row--total'>";
	echo "<td colspan='6'>";
	echo "</td>";

	echo "<td align='right' colspan='1'>";
	echo "TOTAL:";
	echo "</td>";
	echo "<td align='center'>";
	echo $_TotalMark;
	echo "</td>";

    echo "<td align='center' width='5%'>";
    $_Final_Position=0;
    if(trim((string)$_getAssignment_Id) !== ""){
        $_position_obj_1->setPosition($_getAssignment_Id,$_TotalMark);
        $_Final_Position= $_position_obj_1->getPosition();
    }
    echo $_Final_Position;
    echo "</td>";

    echo "<td align='center' width='5%'>";
    $_final_grade="";
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
if(!$_ReportHasRows){
echo "<tr class='scores-report-row'><td colspan='10'><div class='scores-report-empty-state'><h3>No scores found for this scope</h3><p>The selected class, subject, batch, academic year, and semester did not return a teacher score report.</p></div></td></tr>";
}elseif(!$_RenderedStudentRows){
echo "<tr class='scores-report-row'><td colspan='10'><div class='scores-report-empty-state'><h3>No student records found</h3><p>The teacher report did not find class students for this selected score scope.</p></div></td></tr>";
}
echo "</tbody>";
echo "</table>";
echo "</div>";
}
else{
echo "<div class='scores-report-empty-state'><h3>Select a subject to start.</h3><p>Pick a subject from the left column and the score report will appear here.</p></div>";
}
?>
</form>

<?php 
/*echo $_SESSION['Message'];
include("dbstring.php");

if(isset($_GET['admin_class_id']))
{
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE  sc.subjectid='$_GET[subject_id]' ORDER BY ce.class_name,sa.termname ASC");


//$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student'  ORDER BY su.userid");

echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
echo "</caption>";
echo "<thead><th>SUBJECT</th><th>STUDENT</th><th>CLASS</th><th>SEMESTER</th><th>*</th><th>TYPE</th><th>MARK</th><th>TOTAL</th></thead>";
echo "<tbody>";
while($row_sub=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
{
echo "<tr style='background-color:#dee;font-weight:bold'><td align='left' colspan='8'>".strtoupper($row_sub['subject'])."</td></tr>";




$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
	ON ce.class_entryid=tr.class_entryid WHERE tr.batchid='$row_sub[batchid]'");
if(mysqli_num_rows($_SQL_CLASS)==0){

}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{

$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$row_ce[userid]' AND su.systemtype='Student'  ORDER BY su.userid");

while($row_rsu=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC)){

echo "<tr style='background-color:#fee;font-weight:bold'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['firstname']." ".$row_rsu['othernames']." ".$row_rsu['surname']);
echo "</td></tr>";

for($k=1;$k<3;$k++)
{
	$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_rsu[userid]'
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k' AND sub.subjectid='$_GET[subject_id]'
		ORDER BY su.userid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr style='background-color:#dee;font-weight:bold'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='1'>$row_ce[class_name]</td>";
	echo "<td colspan='5'>";
	echo "SEMESTER: ".$k;
	echo "</td></tr>";

	@$_TotalMark=0;
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	echo "<tr>";
	echo "<td colspan='4' align='right'>";
	echo "<a onclick=\"javascript:return confirm('Do you to delete mark?')\" href='scores-report.php?delete_mark=$row[markid]'><i class='fa fa-times' style='color:red'></i></a>";
	echo "</td>";

	echo "<td align='center' width='5%' colspan='1'>";
	echo $serial=$serial+1;
	echo "</td>";

	echo "<td align='left' width='15%'>";
	echo $row['testtype'];
	echo "</td>";

	echo "<td align='center' width='15%'>";
	echo $row['mark'];
	$_TotalMark=$_TotalMark+$row['mark'];
	echo "</td>";

	echo "</tr>";
	}	
	echo "<tr style='background-color:#fed;font-weight:bold'>";
	echo "<td colspan='6'>";
	echo "</td>";

	echo "<td align='right' colspan='1'>";
	echo "TOTAL:";
	echo "</td>";
	echo "<td align='center'>";
	echo $_TotalMark;
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
}
*/
?>
</div>
</div>
</div>

<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
function toggleBulkStudents(toggle){
  var boxes=document.getElementsByClassName("bulk-student-checkbox");
  for(var i=0;i<boxes.length;i++){
    boxes[i].checked=toggle.checked;
  }
}

function confirmBulkDeleteStudents(){
  var boxes=document.getElementsByClassName("bulk-student-checkbox");
  var selected=0;
  for(var i=0;i<boxes.length;i++){
    if(boxes[i].checked){
      selected++;
    }
  }
  if(selected<1){
    alert("Select at least one student.");
    return false;
  }
  return confirm("Delete both Class Score and Exam Score for "+selected+" selected student(s)? This cannot be undone.");
}

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

window.addEventListener("load", function () {
  if (window.location.hash && window.location.hash.indexOf("#edit-mark-") === 0) {
    var editRow = document.querySelector(window.location.hash);
    if (editRow) {
      setTimeout(function () {
        editRow.scrollIntoView({ behavior: "smooth", block: "center" });
      }, 120);
    }
  }
});
</script>
</div>
</body>
</html>

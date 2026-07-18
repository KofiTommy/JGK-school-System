<?php
session_start();
$_SESSION['Message']="";
include("positions.php");
include("class-position.php");
include_once("dbstring.php");
include_once("semester-registry-utils.php");
include_once("report-approval-utils.php");
include_once("school-data-utils.php");
include_once("terminal-report-pdf-utils.php");
semester_registry_ensure_academic_year_column($con);

@$_position_obj=new Position;
@$_position_obj_1=new Position;
@$_class_position_obj=new ClassPosition;
?>

<?php
//Declare the variables
@$_UserID=$_POST['userid'];

//@$todayTime =$_POST['today_time2'];
@$_BatchId=$_POST['batchid'];
@$_AcademicYear=trim((string)$_POST['academicyear']);
@$_TermId=$_POST['termid'];
@$_ClassId=$_POST['classid'];
@$_ReportApprovalAdminMessage="";
@$_ReportPrintMessage="";

if(isset($_POST['approve_class_report']) || isset($_POST['hold_class_report'])){
      include("dbstring.php");
      $_ApprovalStatus = isset($_POST['approve_class_report']) ? 'approved' : 'pending';
      if(report_approval_is_admin_user()){
          if(report_approval_scope_requires_release($_AcademicYear, $_TermId)){
              $_ApprovalSaved = report_approval_set_scope_status($con, $_BatchId, $_AcademicYear, $_TermId, $_ClassId, $_ApprovalStatus, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '');
              if($_ApprovalSaved){
                  $_ReportApprovalAdminMessage = ($_ApprovalStatus === 'approved')
                      ? "<div style='color:green;text-align:center;background-color:white;padding:10px;'>Class report approved for student viewing.</div>"
                      : "<div style='color:maroon;text-align:center;background-color:white;padding:10px;'>Student access to this class report has been held.</div>";
              }else{
                  $_ReportApprovalAdminMessage = "<div style='color:red;text-align:center;background-color:white;padding:10px;'>Class report approval could not be updated.</div>";
              }
          }else{
              $_ReportApprovalAdminMessage = "<div style='color:#0b63ce;text-align:center;background-color:white;padding:10px;'>This report scope does not require student approval yet.</div>";
          }
      }
}

if(isset($_POST['allow_score_corrections']) || isset($_POST['lock_score_corrections'])){
      include("dbstring.php");
      $_OverrideEnabled = isset($_POST['allow_score_corrections']);
      if(report_approval_is_admin_user()){
          if(report_approval_scope_requires_release($_AcademicYear, $_TermId)){
              $_OverrideSaved = report_approval_set_score_edit_override($con, $_BatchId, $_AcademicYear, $_TermId, $_ClassId, $_OverrideEnabled, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '');
              if($_OverrideSaved){
                  $_ReportApprovalAdminMessage = $_OverrideEnabled
                      ? "<div style='color:#0f5132;text-align:center;background-color:white;padding:10px;'>Score correction has been reopened for this class result.</div>"
                      : "<div style='color:#7c2d12;text-align:center;background-color:white;padding:10px;'>Score correction has been locked again for this class result.</div>";
              }else{
                  $_ReportApprovalAdminMessage = "<div style='color:red;text-align:center;background-color:white;padding:10px;'>Score correction access could not be updated for this class report.</div>";
              }
          }else{
              $_ReportApprovalAdminMessage = "<div style='color:#0b63ce;text-align:center;background-color:white;padding:10px;'>This report scope does not require an approval lock for score correction.</div>";
          }
      }
}

if(isset($_POST["print_terminal_report"]))
{
      $_PrintResult = tr_terminal_report_print_single_pdf($con, $_UserID, $_BatchId, $_AcademicYear, $_TermId, $_ClassId);
      if(empty($_PrintResult['success'])){
          $_ReportPrintMessage = "<div class='tr-status-card tr-status-pending'><i class='fa fa-exclamation-circle'></i> ".htmlspecialchars((string)$_PrintResult['message'], ENT_QUOTES, 'UTF-8')."</div>";
      }
}

if(isset($_POST["print_class_report_pack"]))
{
      $_PrintPackResult = tr_terminal_report_print_scope_pack_pdf($con, $_BatchId, $_AcademicYear, $_TermId, $_ClassId);
      if(empty($_PrintPackResult['success'])){
          $_ReportPrintMessage = "<div class='tr-status-card tr-status-pending'><i class='fa fa-exclamation-circle'></i> ".htmlspecialchars((string)$_PrintPackResult['message'], ENT_QUOTES, 'UTF-8')."</div>";
      }
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
    $_AssignmentApprovalMeta = report_approval_assignment_scope_meta($con, $_AssignmentId);
    if($_AssignmentApprovalMeta && !empty($_AssignmentApprovalMeta['score_edit_locked'])){
        $_SESSION['Message'] = "<div style='color:red;padding:10px;background-color:white;'>".htmlspecialchars(report_approval_score_edit_locked_message(), ENT_QUOTES, 'UTF-8')."</div>";
    }else{
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
    $_DeleteMarkId = trim((string)$_GET["delete_mark"]);
    $_DeleteMarkMeta = report_approval_mark_scope_meta($con, $_DeleteMarkId);
    if($_DeleteMarkMeta && !empty($_DeleteMarkMeta['score_edit_locked'])){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>".htmlspecialchars(report_approval_score_edit_locked_message(), ENT_QUOTES, 'UTF-8')."</div>";
    }else{
        $_DeleteMarkIdSafe = mysqli_real_escape_string($con, $_DeleteMarkId);
        $_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_DeleteMarkIdSafe'");
	    if($_SQL_EXECUTE){
	    $_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	    }
	    else{
		    $_Error=mysqli_error($con);
		    $_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
	    }
    }
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/terminal-report.css">

</head>

<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform tr-page">
	<section class="tr-hero">
		<div>
			<span class="tr-kicker">Academic Reports</span>
			<h1>Terminal Report</h1>
			<p>Generate, review, approve, and print student terminal reports.</p>
		</div>
		<div class="tr-hero-card">
			<i class="fa fa-file-text-o"></i>
			<span>Report Generator</span>
		</div>
	</section>

<div class="tr-layout">
<aside class="tr-panel tr-filter-panel">
<form id="formID" name="formID" method="post" action="terminal-report.php">
	<div class="tr-panel-heading">
		<span class="tr-icon"><i class="fa fa-filter"></i></span>
		<div>
			<h2>Report Filters</h2>
			<p>Select the student and academic scope.</p>
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
echo "<fieldset class='tr-fieldset'><legend>Report Details</legend>";
$_SelectedTermLabel = "";
$_SelectedUserId = isset($_POST['userid']) ? $_POST['userid'] : '';

$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student' ORDER BY su.firstname");
echo "<label for='userid'>Student</label>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Student</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
$_SelUser = ($_SelectedUserId==$row['userid']) ? "selected" : "";
echo "<option value='$row[userid]' $_SelUser>$row[firstname] $row[othernames] $row[surname]($row[userid]) </option>";
}
echo "</select>";
			
$_SelectedBatchId = isset($_POST['batchid']) ? $_POST['batchid'] : '';
$_SQL_2=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");

echo "<label for='batchid'>Academic Year Batch</label>";
echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Academic Year (Batch)</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
$_Sel = ($_SelectedBatchId==$row['batchid']) ? "selected" : "";
echo "<option value='$row[batchid]' $_Sel>$row[batch]</option>";
}
echo "</select>";

$_SelectedAcademicYear = isset($_POST['academicyear']) ? trim((string)$_POST['academicyear']) : '';
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

$_SelectedTermId = isset($_POST['termid']) ? $_POST['termid'] : '';
if($_SelectedTermId!==""){
    $_SelectedTermLabel = ($_SelectedAcademicYear!=="" ? $_SelectedAcademicYear." | " : "")."Semester ".$_SelectedTermId;
}
echo "<label for='termid'>Semester</label>";
echo "<select id='termid' name='termid' class='validate[required]'>";
echo "<option value=''>Select Semester</option>";
echo "<option value='1' ".($_SelectedTermId==='1' ? "selected" : "").">1</option>";
echo "<option value='2' ".($_SelectedTermId==='2' ? "selected" : "").">2</option>";
echo "<option value='3' ".($_SelectedTermId==='3' ? "selected" : "").">3</option>";
echo "</select>";

$_SelectedClassId = isset($_POST['classid']) ? $_POST['classid'] : '';
$_SelectedClassLabel = "";
if($_SelectedUserId!="" && $_SelectedBatchId!=""){
    $_SQL_CLASS_OPT=mysqli_query($con,"SELECT DISTINCT ce.class_entryid,ce.class_name
        FROM tbltermregistry tr
        INNER JOIN tblclassentry ce ON tr.class_entryid=ce.class_entryid
        WHERE tr.userid='".mysqli_real_escape_string($con,$_SelectedUserId)."'
          AND tr.batchid='".mysqli_real_escape_string($con,$_SelectedBatchId)."'
          ".($_SelectedAcademicYear!=="" ? " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_SelectedAcademicYear)."'" : "")."
        ORDER BY ce.class_name ASC");
} else {
    $_SQL_CLASS_OPT=mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
}
echo "<label for='classid'>Class</label>";
echo "<select id='classid' name='classid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row_cls=mysqli_fetch_array($_SQL_CLASS_OPT,MYSQLI_ASSOC)){
    $_SelClass = ($_SelectedClassId==$row_cls['class_entryid']) ? "selected" : "";
    echo "<option value='$row_cls[class_entryid]' $_SelClass>$row_cls[class_name]</option>";
    if($_SelectedClassId!="" && $_SelectedClassId==$row_cls['class_entryid']){
        $_SelectedClassLabel = $row_cls['class_name'];
    }
}
echo "</select>";

$_SelectedScopeApprovalMeta = report_approval_scope_meta($con, $_SelectedBatchId, $_SelectedAcademicYear, $_SelectedTermId, $_SelectedClassId);
$_SelectedScopeStudentCount = 0;
if($_SelectedBatchId!=='' && $_SelectedAcademicYear!=='' && $_SelectedTermId!=='' && $_SelectedClassId!==''){
    $_SelectedScopeStudents = tr_terminal_report_fetch_scope_students($con, $_SelectedBatchId, $_SelectedAcademicYear, $_SelectedTermId, $_SelectedClassId);
    $_SelectedScopeStudentCount = count($_SelectedScopeStudents);
}
if($_ReportApprovalAdminMessage!==""){
echo $_ReportApprovalAdminMessage;
}
if($_ReportPrintMessage!==""){
echo $_ReportPrintMessage;
}
if($_SelectedClassId!=='' && $_SelectedTermId!=='' && $_SelectedAcademicYear!=='' && report_approval_is_admin_user()){
    if($_SelectedScopeApprovalMeta['required']){
        $_ApprovalTone = $_SelectedScopeApprovalMeta['allowed'] ? "tr-status-approved" : "tr-status-pending";
        echo "<div class='tr-status-card ".$_ApprovalTone."'><i class='fa fa-shield'></i> Student Portal Status: ".$_SelectedScopeApprovalMeta['status_label']."</div>";
        echo "<div class='tr-actions tr-approval-actions'>";
        echo "<button class='button-pay tr-btn tr-btn-primary' type='submit' name='approve_class_report'><i class='fa fa-check'></i> Approve Student View</button>";
        echo "<button class='button-show tr-btn tr-btn-warning' type='submit' name='hold_class_report'><i class='fa fa-pause'></i> Hold Student View</button>";
        echo "</div>";
        if($_SelectedScopeApprovalMeta['approved']){
            $_ScoreEditTone = !empty($_SelectedScopeApprovalMeta['score_edit_locked']) ? "tr-status-pending" : "tr-status-approved";
            echo "<div class='tr-status-card ".$_ScoreEditTone."'><i class='fa fa-pencil-square-o'></i> Score Entry: ".$_SelectedScopeApprovalMeta['score_edit_status_label']."</div>";
            echo "<div class='tr-actions tr-approval-actions'>";
            if(!empty($_SelectedScopeApprovalMeta['score_edit_locked'])){
                echo "<button class='button-show tr-btn tr-btn-primary' type='submit' name='allow_score_corrections'><i class='fa fa-unlock-alt'></i> Reopen Score Corrections</button>";
            }else{
                echo "<button class='button-show tr-btn tr-btn-warning' type='submit' name='lock_score_corrections'><i class='fa fa-lock'></i> Lock Score Corrections</button>";
            }
            echo "</div>";
        }else{
            echo "<div class='tr-status-card tr-status-info'><i class='fa fa-pencil-square-o'></i> Score entry remains open until this class result is approved.</div>";
        }
    }else{
        echo "<div class='tr-status-card tr-status-info'><i class='fa fa-info-circle'></i> Student approval is not required for this semester scope.</div>";
    }
}

echo "<div class='tr-actions'>";
echo "<button class='button-show tr-btn tr-btn-primary' id='show_terminal_report' name='show_terminal_report'><i class='fa fa-search'></i> Show Report</button> ";
echo "<a href='terminal-report.php' class='button-show tr-btn tr-btn-light'><i class='fa fa-undo'></i> Reset</a>";
echo "</div>";
if($_SelectedTermLabel!=""){
echo "<div class='tr-selected'><i class='fa fa-check-circle'></i> Selected: $_SelectedTermLabel".($_SelectedClassLabel!="" ? " | Class: ".$_SelectedClassLabel : "")."</div>";
}
echo "</fieldset>";
?>

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>
-->

</form>
<?php if($_SelectedBatchId!=='' && $_SelectedAcademicYear!=='' && $_SelectedTermId!=='' && $_SelectedClassId!==''){ ?>
<form method="post" action="terminal-report.php" class="tr-bulk-form">
    <input type="hidden" name="batchid" value="<?php echo htmlspecialchars((string)$_SelectedBatchId, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="academicyear" value="<?php echo htmlspecialchars((string)$_SelectedAcademicYear, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="termid" value="<?php echo htmlspecialchars((string)$_SelectedTermId, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="classid" value="<?php echo htmlspecialchars((string)$_SelectedClassId, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="tr-status-card tr-status-info" style="margin-top:14px;">
        <i class="fa fa-files-o"></i>
        <?php echo $_SelectedScopeStudentCount > 0 ? htmlspecialchars((string)$_SelectedScopeStudentCount, ENT_QUOTES, 'UTF-8') . ' student report(s) are ready in this class scope.' : 'No students were found in this class scope yet.'; ?>
    </div>
    <div class="tr-actions" style="margin-top:12px;">
        <button class="button-pay tr-btn tr-btn-print" type="submit" name="print_class_report_pack" <?php echo $_SelectedScopeStudentCount > 0 ? '' : 'disabled'; ?>>
            <i class="fa fa-print"></i> Print Class Report Pack
        </button>
    </div>
</form>
<?php } ?>
</aside>
<main class="tr-panel tr-results-panel">
	<form id="formID2" name="formID2" method="post" action="terminal-report.php">
	<div class="tr-panel-heading">
		<span class="tr-icon"><i class="fa fa-table"></i></span>
		<div>
			<h2>Report Preview</h2>
			<p>View the selected student marks before printing the terminal report.</p>
		</div>
	</div>
<?php
echo $_SESSION['Message'];
if(isset($_POST["show_terminal_report"]))
{
@$_User_ID=$_POST["userid"];
@$_Batch_ID=$_POST["batchid"];
@$_Academic_Year=$_POST["academicyear"];
@$_Term_ID=$_POST["termid"];
@$_Class_ID=$_POST["classid"];
$_AcademicYearSql = $_Academic_Year!=="" ? " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_Academic_Year)."'" : "";

include("dbstring.php");
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_User_ID' AND su.systemtype='Student'  ORDER BY su.userid");
if(mysqli_num_rows($_SQL_USER)>0){
echo "<input type='hidden' name='userid' value='$_User_ID' />";
echo "<input type='hidden' name='batchid' value='$_Batch_ID' />";
echo "<input type='hidden' name='academicyear' value='$_Academic_Year' />";
echo "<input type='hidden' name='termid' value='$_Term_ID' />";
echo "<input type='hidden' name='classid' value='$_Class_ID' />";
echo "<button class='button-pay tr-btn tr-btn-print' id='print_terminal_report' name='print_terminal_report'><i class='fa fa-print'></i> Print Report</button>";		
}
echo "<div class='tr-table-wrap'>";
echo "<table class='tr-table tr-results-table'>";
echo "<caption>";
$_SQL_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_User_ID' AND su.systemtype='Student'");
if($rowst=mysqli_fetch_array($_SQL_USER_2,MYSQLI_ASSOC)){
echo $rowst["firstname"]." ".$rowst["othernames"]." ".$rowst["surname"]." (".$rowst["userid"].")";
}
echo "</caption>";
echo "<thead><th>SUBJECT</th><th>CLASS</th><th>SEM.</th><th>*</th><th>TYPE</th><th>MARK</th><th>POSITION</th></thead>";
echo "<tbody>";
while($row_us=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC))
{
$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc 
	ON sub.subjectid=sc.subjectid INNER JOIN tbltermregistry tr ON sc.classid=tr.class_entryid
	WHERE tr.batchid='$_Batch_ID' AND tr.class_entryid='$_Class_ID' $_AcademicYearSql GROUP BY sub.subjectid");
while($row_rsu=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){

//SUBJECT
echo "<tr class='tr-subject-row'>";
//echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['subject']);
echo "</td></tr>";

//$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
//	ON ce.class_entryid=tr.class_entryid GROUP BY tr.class_entryid");

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
ON ce.class_entryid=tr.class_entryid WHERE tr.userid='$_User_ID' AND tr.batchid='$_Batch_ID' AND tr.class_entryid='$_Class_ID' $_AcademicYearSql");

if(mysqli_num_rows($_SQL_CLASS)==0){

}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
echo "<tr class='tr-class-row'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='6'>";
echo strtoupper($row_ce['class_name']);
echo "</td></tr>";

$_StartTerm = intval($_Term_ID);
$_EndTerm = intval($_Term_ID);
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
		WHERE su.userid='$row_us[userid]' AND sub.subjectid='$row_rsu[subjectid]' 
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k' AND
		sa.batchid='$_Batch_ID'".($_Academic_Year!=="" ? " AND ".semester_registry_assignment_year_sql("sa")."='".mysqli_real_escape_string($con,$_Academic_Year)."'" : "")."
		ORDER BY su.userid ASC");


if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr class='tr-semester-row'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='5'>";
	echo "Semester: ".$k;
	echo "</td></tr>";

	@$_TotalMark=0;
	@$_getAssignment_Id=0;
	
	
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	$_getAssignment_Id=$row['assignmentid'];

	echo "<tr>";
	echo "<td colspan='3' align='right'>";
	echo "<a onclick=\"javascript:return confirm('Do you to delete mark?')\" href='terminal-report.php?delete_mark=$row[markid]'><i class='fa fa-trash-o' style='color:red'></i></a>";
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
	echo "<tr class='tr-total-row'>";
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

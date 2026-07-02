<?php
session_start();
if(!isset($_SESSION['Message'])){
$_SESSION['Message']="";
}
?>
<?php
include("dbstring.php");
if(!function_exists('score_report_session_label')){
function score_report_session_label($dateTimeValue, $batchLabel, $termValue){
    $yearValue = "";
    if(trim((string)$dateTimeValue) !== ""){
        $time = strtotime((string)$dateTimeValue);
        if($time){
            $yearValue = date("Y", $time);
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
@$_FilterBatch = isset($_GET["filter_batch"]) ? trim($_GET["filter_batch"]) : "";
@$_FilterClass = isset($_GET["filter_class"]) ? trim($_GET["filter_class"]) : "";
@$_FilterTerm  = isset($_GET["filter_term"]) ? trim($_GET["filter_term"]) : "";
@$_FilterSubject  = isset($_GET["filter_subject"]) ? trim($_GET["filter_subject"]) : "";
@$_FilterBatchSafe = mysqli_real_escape_string($con, $_FilterBatch);
@$_FilterClassSafe = mysqli_real_escape_string($con, $_FilterClass);
@$_FilterTermSafe  = mysqli_real_escape_string($con, $_FilterTerm);
@$_FilterSubjectSafe  = mysqli_real_escape_string($con, $_FilterSubject);
@$_ReportClassId = isset($_GET["class_id"]) ? trim($_GET["class_id"]) : $_FilterClass;
@$_ReportSubjectId = isset($_GET["subject_id"]) ? trim($_GET["subject_id"]) : $_FilterSubject;
@$_ReportBatchId = isset($_GET["batchid"]) ? trim($_GET["batchid"]) : $_FilterBatch;
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

if(isset($_GET["delete_mark"]) && isset($_GET["class_id"]) && isset($_GET["subject_id"]) && isset($_GET["batchid"])){
    header("Location: scores-report-all.php?class_id=".urlencode($_GET["class_id"])."&subject_id=".urlencode($_GET["subject_id"])."&batchid=".urlencode($_GET["batchid"])."&filter_batch=".urlencode($_FilterBatch)."&filter_class=".urlencode($_FilterClass)."&filter_term=".urlencode($_FilterTerm)."&filter_subject=".urlencode($_FilterSubject));
    exit();
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
$_AdminSelectionReady = ($_ReportClassId!="" && $_ReportSubjectId!="" && $_ReportBatchId!="");
$_AdminSemesterLabel = $_FilterTerm !== "" ? "Semester ".$_FilterTerm : "All Semesters";
?>

<div class="main-platform scores-report-shell"><br/>
<section class="scores-report-hero">
    <div class="scores-report-hero__copy">
        <span class="scores-report-kicker">Admin Score Reports</span>
        <h1>Filter, review, and confirm student scores.</h1>
        <p>Use the left filters to narrow the exact batch, class, subject, and semester you want, then review the score breakdown in the report area.</p>
        <div class="scores-report-hero__stats">
            <article class="scores-report-stat-card">
                <span>Viewer</span>
                <strong>Admin Score Workspace</strong>
            </article>
            <article class="scores-report-stat-card">
                <span>Batch</span>
                <strong><?php echo score_report_safe($_FilterBatch !== "" ? $_FilterBatch : "All"); ?></strong>
            </article>
            <article class="scores-report-stat-card">
                <span>Semester</span>
                <strong><?php echo score_report_safe($_AdminSemesterLabel); ?></strong>
            </article>
            <article class="scores-report-stat-card">
                <span>Status</span>
                <strong><?php echo $_AdminSelectionReady ? "Report loaded" : "Waiting"; ?></strong>
            </article>
        </div>
    </div>
    <div class="scores-report-hero__aside">
        <div class="scores-report-tip-card">
            <span class="scores-report-tip-card__eyebrow">Admin flow</span>
            <h2>Stay on one clear report scope</h2>
            <ul>
                <li>Filter the batch, class, subject, and semester first.</li>
                <li>Review totals, positions, and grades in one table.</li>
                <li>Delete only the exact score row you intend to remove.</li>
            </ul>
        </div>
    </div>
</section>

<div class="scores-report-grid">
<div class="scores-report-column scores-report-column--sidebar">
<div class="form-entry scores-report-panel">
<form id="formID" name="formID" method="get" action="scores-report-all.php">
<div class="scores-report-panel__header">
<span class="scores-report-panel__eyebrow">Filter</span>
<h4>Admin Filters</h4>
<p>Choose the exact report scope to load.</p>
</div>
<?php	
echo "<div class='scores-report-field-group'>";
echo "<label for='filter_batch'>Academic Year (Batch)</label>";
echo "<select id='filter_batch' name='filter_batch'>";
echo "<option value=''>All Batches</option>";
$_SQL_F_B=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
while($rowfb=mysqli_fetch_array($_SQL_F_B,MYSQLI_ASSOC)){
    $_Sel = ($_FilterBatch==$rowfb['batchid']) ? "selected" : "";
    echo "<option value='$rowfb[batchid]' $_Sel>".score_report_safe($rowfb['batch'])."</option>";
}
echo "</select>";
echo "</div>";

echo "<div class='scores-report-field-group'>";
echo "<label for='filter_class'>Class</label>";
echo "<select id='filter_class' name='filter_class'>";
echo "<option value=''>All Classes</option>";
$_SQL_F_C=mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
while($rowfc=mysqli_fetch_array($_SQL_F_C,MYSQLI_ASSOC)){
    $_Sel = ($_FilterClass==$rowfc['class_entryid']) ? "selected" : "";
    echo "<option value='$rowfc[class_entryid]' $_Sel>".score_report_safe($rowfc['class_name'])."</option>";
}
echo "</select>";
echo "</div>";

echo "<div class='scores-report-field-group'>";
echo "<label for='filter_subject'>Subject</label>";
echo "<select id='filter_subject' name='filter_subject'>";
echo "<option value=''>Select Subject</option>";
$_SQL_F_S=mysqli_query($con,"SELECT subjectid,subject FROM tblsubject ORDER BY subject ASC");
while($rowfs=mysqli_fetch_array($_SQL_F_S,MYSQLI_ASSOC)){
    $_Sel = ($_FilterSubject==$rowfs['subjectid']) ? "selected" : "";
    echo "<option value='$rowfs[subjectid]' $_Sel>".score_report_safe($rowfs['subject'])."</option>";
}
echo "</select>";
echo "</div>";

echo "<div class='scores-report-field-group'>";
echo "<label for='filter_term'>Semester</label>";
echo "<select id='filter_term' name='filter_term'>";
echo "<option value=''>All Semesters</option>";
echo "<option value='1' ".($_FilterTerm==='1'?'selected':'').">1</option>";
echo "<option value='2' ".($_FilterTerm==='2'?'selected':'').">2</option>";
echo "<option value='3' ".($_FilterTerm==='3'?'selected':'').">3</option>";
echo "</select>";
echo "</div>";
echo "<div class='scores-report-toolbar'>";
echo "<button class='scores-report-button scores-report-button--save' type='submit'><i class='fa fa-filter'></i> Apply Filter</button>";
echo "<a href='scores-report-all.php' class='scores-report-button scores-report-button--ghost'><i class='fa fa-undo'></i> Reset</a>";
echo "</div>";

echo "<div class='scores-report-empty-block' style='margin-top:12px;text-align:left;'><strong>Tip</strong><span>Select batch, class, subject, and semester to focus the correct session before opening the report.</span></div>";
?>

</form>
</div>
 </div>
<div class="scores-report-column scores-report-column--main">
<div class="form-entry scores-report-panel scores-report-panel--main">
<div class="scores-report-panel__header">
<span class="scores-report-panel__eyebrow">Report</span>
<h4>Scores Report</h4>
<p><?php echo $_AdminSelectionReady ? score_report_safe("Batch ".$_ReportBatchId." | ".$_AdminSemesterLabel) : "Apply the filters on the left to load the report details here."; ?></p>
</div>
<form id="formID2" name="formID2" method="post" action="scores-report-all.php">
<?php
include("positions.php");
include("class-position.php");
include("gradingsystem.php");

@$_position_obj=new Position;
@$_position_obj_1=new Position;
@$_class_position_obj=new ClassPosition;
@$_grade_obj=new GradingSystem;

if(trim((string)$_SESSION['Message']) !== ""){
echo "<div class='scores-report-flash'>".$_SESSION['Message']."</div>";
$_SESSION['Message']="";
}
include("dbstring.php");

if($_ReportClassId!="" && $_ReportSubjectId!="" && $_ReportBatchId!="")
{
$_SQL_2=mysqli_query($con,"SELECT sa.*, sa.termname AS assignment_termname, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.* FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE  sc.subjectid='".mysqli_real_escape_string($con,$_ReportSubjectId)."' AND sa.batchid='".mysqli_real_escape_string($con,$_ReportBatchId)."' ".($_FilterClassSafe!="" ? " AND sa.classid='$_FilterClassSafe'" : "")." ".($_FilterTermSafe!="" ? " AND sa.termname='$_FilterTermSafe'" : "")." ORDER BY ce.class_name,sa.termname ASC");


//$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student'  ORDER BY su.userid");

echo "<div class='scores-report-table-wrap'>";
echo "<table width='100%' class='scores-report-table'>";
echo "<caption>Scores Report</caption>";
echo "<thead><th>SUBJECT</th><th>STUDENT</th><th>CLASS</th><th>SESSION</th><th>*</th><th>TYPE</th><th>MARK</th><th>TOTAL</th><th>POSITION</th><th>GRADE</th></thead>";
echo "<tbody>";
while($row_sub=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
{
@$_BatchName="";
$_SQL_Batch=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$row_sub[batchid]'");
if($rowb=mysqli_fetch_array($_SQL_Batch,MYSQLI_ASSOC)){
$_BatchName=$rowb["batch"];	
}
$_SessionHeading = score_report_session_label($row_sub['assignment_datetimeentry'], $_BatchName, $row_sub['assignment_termname']);
echo "<tr class='scores-report-row scores-report-row--section'><td align='left' colspan='10'>".strtoupper($row_sub['subject']).": ".strtoupper($_SessionHeading) ."</td></tr>";


//$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsubject");

//SUBJECT
/*echo "<tr style='background-color:#cff;font-weight:bold'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['subject']);
echo "</td></tr>";
*/
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
	ON ce.class_entryid=tr.class_entryid WHERE tr.batchid='$row_sub[batchid]' ".($_FilterClassSafe!="" ? " AND tr.class_entryid='$_FilterClassSafe'" : ""));
if(mysqli_num_rows($_SQL_CLASS)==0){
}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$row_ce[userid]' AND su.systemtype='Student'  ORDER BY su.userid");

while($row_rsu=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC)){
echo "<tr class='scores-report-row scores-report-row--student'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='9'>";
echo strtoupper($row_rsu['firstname']." ".$row_rsu['othernames']." ".$row_rsu['surname']);
echo "(".$row_rsu['userid'].")";
echo "</td></tr>";

$_StartTerm = ($_FilterTermSafe!="" ? intval($_FilterTermSafe) : intval($row_sub['assignment_termname']));
$_EndTerm = ($_FilterTermSafe!="" ? intval($_FilterTermSafe) : intval($row_sub['assignment_termname']));
for($k=$_StartTerm;$k<=$_EndTerm;$k++){
$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_rsu[userid]' AND sa.batchid='$row_sub[batchid]'
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k' 
		AND sub.subjectid='".mysqli_real_escape_string($con,$_ReportSubjectId)."'
		ORDER BY su.userid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr class='scores-report-row scores-report-row--session'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='3'>$row_ce[class_name]</td>";
	echo "<td colspan='5'>";
	echo "SESSION: ".score_report_session_label($row_sub['assignment_datetimeentry'], $_BatchName, $k);
	echo "</td></tr>";

	@$_TotalMark=0;
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
		$_getAssignment_Id=$row['assignmentid'];
	echo "<tr class='scores-report-row scores-report-row--mark'>";
	echo "<td colspan='4' align='right'>";
	echo "<a class='scores-report-action scores-report-action--delete' onclick=\"javascript:return confirm('Do you to delete mark?')\" title='Delete score: $row[mark]' href='scores-report-all.php?delete_mark=$row[markid]&class_id=".urlencode($_ReportClassId)."&subject_id=".urlencode($_ReportSubjectId)."&batchid=".urlencode($_ReportBatchId)."&filter_batch=".urlencode($_FilterBatch)."&filter_class=".urlencode($_FilterClass)."&filter_term=".urlencode($_FilterTerm)."&filter_subject=".urlencode($_FilterSubject)."'><i class='fa fa-trash-o'></i></a>";
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
else{
echo "<div class='scores-report-empty-state'><h3>Select filters to load the admin score report.</h3><p>Choose the batch, class, subject, and semester from the left panel, then apply the filter.</p></div>";
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
	echo "<a onclick=\"javascript:return confirm('Do you to delete mark?')\" href='scores-report-all.php?delete_mark=$row[markid]'><i class='fa fa-times' style='color:red'></i></a>";
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

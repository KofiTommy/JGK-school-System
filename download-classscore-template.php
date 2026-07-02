<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include_once("score-entry-utils.php");
include_once("semester-registry-utils.php");
semester_registry_ensure_academic_year_column($con);
if(!(class_teacher_is_teacher() || class_teacher_is_admin())){
    header("location:".class_teacher_landing_page());
    exit();
}
@$_Mark=$_POST['marks'];
@$_AssignmentId=$_POST['assignmentid'];
@$_UserId=$_POST['userid'];
@$_TotalMark=$_POST['totalscore'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['download_score_template']))
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

if(isset($_GET["assign_subject"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsubjectclassification WHERE classificationid='$_GET[assign_subject]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Subject Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Subject failed to delete,Error:$_Error</div>";
	}
}
?>
<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 

 function uncheckBox(){
   var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }

  function checkBox(){
var inputs = document.getElementsByName("userid[]");
   
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
</script>

</head>
<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="background-color:white">
<table width="100%">
<tr>
<td width="30%">
	<div class="form-entry">
<form id="formID" name="formID" method="post" action="download-classscore-template.php">
	<h4>DOWNLOAD CLASS SCORE TEMPLATE</h4>
<?php	
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
	WHERE sa.userid='$_SESSION[USERID]' ORDER BY sa.termname ASC");

/*echo "<select id='classid' name='classid' class='validate[required]'>";
	echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<option value='$row[class_entryid]'>$row[class_name]:Term $row[termname]:$row[subject]</option>";
	}
echo "</select><br/><br/>";

	$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			*/
echo "<table>";
echo "<thead><th>CLASS</th><th>SEM.</th><th>SUBJECT</th><th>TASK</th></thead>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<tr><td>";
	echo $row['class_name'];
	echo "</td>";
	echo "<td align='center'>";
	echo $row['termname'];
	echo "</td>";
	echo "<td>";
	echo $row['subject']."(".$row['subjectid'].") - ".semester_registry_session_label(date('Y-m-d H:i:s', strtotime($row['datetimeentry'])), $row['batch'], $row['termname']);
	echo "</td>";
	echo "<td align='center'>";
	echo "<a href='download-classscore-template.php?class_ID=$row[class_entryid]&term_ID=$row[termname]&batch_ID=$row[batchid]&subject_ID=$row[subjectid]&year_ID=".date('Y', strtotime($row['datetimeentry']))."'><i class='fa fa-plus' style='color:blue'></i></a>";
	echo "</td>";
	echo "</tr>";
	}
echo "</table>";
?>

<!--
		<select id="term" name="term" class="validate[required]">
				<option value="" >Select Term</option>
				<option value="1">1</option>
				<option value="2">2</option>
				<option value="3">3</option>
			</select><br/><br/>
		-->

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>

<button class="button-pay" id="get_student" name="get_student"><i class="fa fa-plus" style="color:white"></i> Get Student</button>
-->
</form>
</div>
</td>
<td width="70%">
<div class="form-entry">
<form id="formID2" name="formID2" method="post" action="download-classscore-template.php">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
@$serial=0;

if(isset($_GET['class_ID']))
{
@$_BatchId=$_GET['batch_ID'];
@$_ClassId=$_GET['class_ID'];
@$_Term=$_GET['term_ID'];
@$_SubjectId=$_GET['subject_ID'];
@$_AcademicYear=semester_registry_normalize_year($_GET['year_ID'] ?? '');
@$_ClassName="";
@$_BatchName="";
$_AcademicYearWhere = "";
$_AllowedStudentWhere = " AND 1=0";
if($_AcademicYear!==""){
$_AcademicYearWhere = " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_AcademicYear)."'";
}

$_AssignmentScopeSql=mysqli_query($con,"SELECT sa.assignmentid, sa.classid, sa.batchid, sa.termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year
FROM tblsubjectassignment sa
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
WHERE sa.classid='$_ClassId'
AND sa.batchid='$_BatchId'
AND sa.termname='$_Term'
AND sc.subjectid='$_SubjectId'
AND sa.userid='$_SESSION[USERID]'
AND ".semester_registry_assignment_year_sql("sa")."='".mysqli_real_escape_string($con,$_AcademicYear)."'
LIMIT 1");
if($_AssignmentScopeSql && ($_AssignmentScopeRow=mysqli_fetch_array($_AssignmentScopeSql,MYSQLI_ASSOC))){
$_AssignmentStudentContext = score_entry_assignment_student_context(
    $con,
    $_AssignmentScopeRow['assignmentid'],
    $_AssignmentScopeRow['classid'],
    $_AssignmentScopeRow['batchid'],
    $_AssignmentScopeRow['assignment_year'],
    $_AssignmentScopeRow['termname']
);
if(count($_AssignmentStudentContext['userids'])>0){
    $_AllowedIds=array();
    foreach($_AssignmentStudentContext['userids'] as $_AllowedStudentId){
        $_AllowedIds[]="'".mysqli_real_escape_string($con,trim((string)$_AllowedStudentId))."'";
    }
    $_AllowedStudentWhere = " AND su.userid IN (".implode(",",$_AllowedIds).")";
}
}

$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 $_AcademicYearWhere
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]' $_AllowedStudentWhere");


$_SQL_EXE=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 $_AcademicYearWhere
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]' $_AllowedStudentWhere");

/*
$_SubjectName="";
$_SQL_SUB=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_POST[subjectid]'");
if($row_sub=mysqli_fetch_array($_SQL_SUB,MYSQLI_ASSOC)){
$_SubjectName=$row_sub['subject'];
}
*/
//echo "<div id='subject' name='subject' style='padding:10px;color:blue'>SUBJECT: $_SubjectName </div>";
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_GET[class_ID]'");
if($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
$_ClassName=$row_cl['class_name'];
}

$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[batch_ID]'");
if($row_bch=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
$_BatchName=$row_bch['batch'];
}
echo "<div align='right'><button id='download_score_template' name='download_score_template' class='button-save'><i class='fa fa-download' style='color:white'></i> DOWNLOAD ". strtoupper($_ClassName)." SCORE TEMPLATE</button></div><br/>";
//echo "<label>*Total Score</label><br/><input type='text' id='totalscore' name='totalscore' placeholder='Enter Total Score' class='validate[required,custom[number]]'/><br/><br/>";
echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
if($row_ss=mysqli_fetch_array($_SQL_EXE,MYSQLI_ASSOC)){
echo strtoupper($_ClassName)." : ".strtoupper(semester_registry_session_label($_AcademicYear!=="" ? $_AcademicYear : date('Y'), $_BatchName, $_Term))." : ". strtoupper($row_ss['subject']);
}
echo "</caption>";
echo "<thead><th>*</th><th>STUDENT</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
{
$_SQL=mysqli_query($con,"SELECT * FROM tblmark mk WHERE mk.userid='$row[userid]' AND mk.testtype='Class Score'  AND mk.assignmentid='$row[assignmentid]'");
	if(mysqli_num_rows($_SQL)==0){
	echo "<tr>";
	//echo "<td align='center' width='10%'>";
	//echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
	echo "<input type='hidden' id='assignmentid' name='assignmentid' value='$row[assignmentid]' />";
	//echo "</td>";
	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";
	echo "<td align='left'>$row[firstname] $row[othernames] $row[surname]($row[userid])</td>";
	echo "</tr>";
	}
}	
echo "</tbody>";
echo "</table>";
}
?>
</form>
</div>
</td>
</tr>
</table>
</div>
</body>
</html>

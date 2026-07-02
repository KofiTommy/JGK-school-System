<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include_once("semester-registry-utils.php");
semester_registry_ensure_academic_year_column($con);
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
</head>
<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="background-color:white"><br/>
<table width="100%">
<tr>
<td width="30%">
<div class="form-entry">
<form id="formID" name="formID" method="post" action="upload-classexam-score.php">
	<h4>GROUP CLASS/EXAM SCORES UPLOAD</h4>
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
echo "<thead><th>CLASS</th><th>SEMESTER</th><th>SUBJECT</th><th>TASK</th></thead>";
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
	echo "<a href='upload-classexam-score.php?class_ID=$row[class_entryid]&term_ID=$row[termname]&batch_ID=$row[batchid]&subject_ID=$row[subjectid]&year_ID=".date('Y', strtotime($row['datetimeentry']))."'><i class='fa fa-plus' style='color:blue'></i></a>";
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
if($_AcademicYear!==""){
$_AcademicYearWhere = " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_AcademicYear)."'";
}

$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 $_AcademicYearWhere
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]'");


$_SQL_EXE=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 $_AcademicYearWhere
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]'");

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
?>
<form id="formID2" method="post" action="import-classexam-scores-data.php"  enctype="multipart/form-data">

<?php

echo "<div class='form-entry'>";
if($row_ss=mysqli_fetch_array($_SQL_EXE,MYSQLI_ASSOC)){
echo "<div style='background-color:lightblue;color:black;padding:10px;text-align:center'>". strtoupper($_ClassName)." : ".strtoupper(semester_registry_session_label($_AcademicYear!=="" ? $_AcademicYear : date('Y'), $_BatchName, $_Term))." : ". strtoupper($row_ss['subject'])."</div>";

echo "<input type='hidden' id='assignment-id' name='assignment-id' value='$row_ss[assignmentid]' />";

echo "<label>*Total Score</label><br/><input type='text' id='totalscore' name='totalscore' placeholder='Enter Total Score' class='validate[required,custom[number]]'/><br/><br/>";
?>

		<div align="center">
		<div id="subscription-style1" align="left">
	
			<label>Upload Excel File*</label><br>
			
			<input type="file" id="file1" name="file1" class="validate[required]"/><br><br>				
			
			<!--Submit form's data -->
			<div align="right">
			<button class="button-pay" id="submit_group_data" name="submit_group_data"><i class="fa fa-upload"></i> Upload Data</button>
		</div>
		</div>
	</div>
	

<?php
}
else{
	echo "<div style='padding:10px;color:red;background-color:#fee;text-align:center'>**********No Student Available to upload scores***************</div>";
}
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

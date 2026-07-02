<?php
session_start();
$_SESSION['Message']="";
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
		VALUES('$_MarkId','$_AssignmentId','$_Selected_User','Exam Score','$_Selected_Mark','$_TotalMark',NOW(),'active','$_Recordedby')");
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
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	<?php
	include("side-menu.php");

	?>
	</div>


<br/>
<br/>

<br/>
<br/>
<br/>
<br/>
<div class="main" style="background-color:white">
<table width="100%">
<tr>
<td width="30%">
<form id="formID" name="formID" method="post" action="exam-score.php">
	<h4>EXAM SCORE ENTRY</h4>
<?php	
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
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
echo "<thead><th>CLASS</th><th>TERM</th><th>SUBJECT</th><th>TASK</th></thead>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<tr><td>";
	echo $row['class_name'];
	echo "</td>";
	echo "<td align='center'>";
	echo $row['termname'];
	echo "</td>";
	echo "<td>";
	echo $row['subject'];
	echo "</td>";
	echo "<td align='center'>";
	echo "<a href='exam-score.php?class_ID=$row[class_entryid]&term_ID=$row[termname]&batch_ID=$row[batchid]&subject_ID=$row[subjectid]'><i class='fa fa-plus' style='color:blue'></i></a>";
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
</td>
<td width="70%">
	<form id="formID2" name="formID2" method="post" action="exam-score.php">
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
@$_ClassName="";
@$_BatchName="";

$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]'");


$_SQL_EXE=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 AND su.systemtype='Student' AND sa.userid='$_SESSION[USERID]'");

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

echo "<label>*Total Score</label><br/><input type='number' id='totalscore' name='totalscore' placeholder='Enter Total Score' class='validate[required,custom[number]]'/><br/><br/>";
echo "<table width='100%' style='background-color:white'>";
echo "<caption style='color:blue;font-weight:bold'>";
if($row_ss=mysqli_fetch_array($_SQL_EXE,MYSQLI_ASSOC)){
echo strtoupper($_ClassName)." : TERM ".$_Term ." : ". strtoupper($row_ss['subject'])." ---BATCH: ".strtoupper($_BatchName);
}
echo "</caption>";
echo "<thead><th>SELECT ALL</th><th>*</th><th>STUDENT</th><th>MARK</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
{
$_SQL=mysqli_query($con,"SELECT * FROM tblmark mk WHERE mk.userid='$row[userid]' AND mk.testtype='Exam Score' AND mk.assignmentid='$row[assignmentid]'");
	if(mysqli_num_rows($_SQL)==0)
	{
	echo "<tr>";
	echo "<td align='center' width='10%'>";
	echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
	echo "<input type='hidden' id='assignmentid' name='assignmentid' value='$row[assignmentid]' />";
	echo "</td>";
	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";
	echo "<td align='left'>$row[firstname] $row[othernames] $row[surname]($row[userid])</td>";
	echo "<td align='left' width='15%'>";
	echo "<input type='text' style='text-align:center' id='marks' name='marks[]' value='' placeholder='Enter Mark' class='validate[required,custom[number]]'/>";
	echo "</td>";

	echo "</tr>";
	}
}	
echo "<tr style='background-color:#efc'>";
echo "<td colspan='4' align='right'>";
echo "<button id='save_all_mark' name='save_all_mark' class='button-pay'><i class='fa fa-save' style='color:white'></i> Save</button>";
echo "</td>";
echo "</tr>";
echo "</tbody>";
echo "</table>";
}
?>
</form>
</td>
</tr>
</table>
<br/><br/><br/>
</div>
</body>
</html>

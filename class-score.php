<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_ClassificationId=$_POST['classificationid'];
@$_UserId=$_POST['userid'];
//@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['add_score']))
{
	foreach ($_ClassificationId as $_Selected_ClassId) 
	{
		include("code.php");
	@$_AssignmentId=$code;
	@$_UserFullname="";
	//@$_Subject="";
	//Check if subject already registered
	$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassId'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	$_ClassId=$row_s['classid'];
	//@$_getUser_ID=$row_s['userid'];
	}

	$_SQL_EXECUTE_USER=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsystemuser su ON sa.userid=su.userid WHERE sa.classificationid='$_Selected_ClassId'");
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

	//$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId' AND sa.userid='$_UserId' AND sa.classid='$_ClassId'");
	$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId'");
	
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Assigned To $_UserFullname</div>";
		
	}else{

	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsubjectassignment(assignmentid,userid,classid,classificationid,datetimeentry,status,recordedby)
	VALUES('$_AssignmentId','$_UserId','$_ClassId','$_Selected_ClassId',NOW(),'active','$_Recordedby')");
		if($_SQL_EXECUTE)
		{
	
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Subject Successfully Assigned To $_UserFullname</div>";
		}
		else{
			$_Error=mysqli_error($con);
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_Subject failed to classify,$_Error</div>";
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

<div class="main" style="background-color:white">
<table width="100%">
<tr>
<td width="30%">
<form id="formID" name="formID" action="class-score.php">
	<h4>SUBJECT</h4>
<?php	
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE sa.userid='$_SESSION[USERID]'");
echo "<select id='subjectid' name='subjectid' class='validate[required]'>";
	echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<option value='$row[subjectid]'>$row[class_name]:$row[subject]</option>";
	}
echo "</select><br/><br/>";

	$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
?>
<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>
<button class="button-pay" id="add_score" name="add_score"><i class="fa fa-plus" style="color:white"></i> Add Score</button>


</td>
<td width="70%">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
@$serial=0;


		
$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblsystemuser su 
INNER JOIN tblclass cl ON su.userid=cl.userid WHERE su.systemtype='Student'");

echo "<table width='100%' style='background-color:white'>";
echo "<thead><th>Select If Absent</th><th>*</th><th>STUDENT</th><th>MARK</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
{
echo "<tr>";
echo "<td align='center'>";
echo "<input type='checkbox' id='userid' name='userid' value='$row[userid]' />";
echo "</td>";

echo "<td align='center'>";
echo $serial=$serial+1;
echo "</td>";
echo "<td align='left'>$row[firstname] $row[othernames] $row[surname]($row[userid])</td>";
echo "<td align='left'>";
echo "<input type='text' id='marks' name='marks' value='' placeholder='Enter Mark' class='validate[required,custom[number]]'/>";
echo "</td>";
echo "</tr>";
}	
echo "</tbody>";
echo "</table>";
?>
</form>
</td>
</tr>
</table>
<br/><br/><br/>
</div>
</body>
</html>
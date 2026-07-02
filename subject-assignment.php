<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_ClassificationId=$_POST['classificationid'];
@$_UserId=$_POST['userid'];
@$_BatchId=$_POST['batchid'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];

if(!function_exists('sa_session_label')){
function sa_session_label($yearValue, $batchLabel, $termValue){
	$yearValue = trim((string)$yearValue);
	if($yearValue === ""){
		$yearValue = date("Y");
	}
	$batchLabel = trim((string)$batchLabel);
	$termValue = trim((string)$termValue);
	return trim($yearValue." Batch ".$batchLabel." Semester ".$termValue);
}
}

if(isset($_POST['register_subject_assignment']))
{
	if(!is_array($_ClassificationId) || count($_ClassificationId)===0 || !$_UserId || !$_BatchId || !$_Term){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white;padding:4px;'><i class='fa fa-check' style='color:red'></i> Select teacher, batch, semester and at least one subject.</div>";
	}else{
		$_UserId=mysqli_real_escape_string($con,$_UserId);
		$_BatchId=mysqli_real_escape_string($con,$_BatchId);
		$_Term=(int)$_Term;
		$_Recordedby=mysqli_real_escape_string($con,$_Recordedby);

		@$_UserFullname=$_UserId;
		$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_UserId' LIMIT 1");
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
			$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

		@$_BatchName=$_BatchId;
		$_SQL_BATCH=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchId' LIMIT 1");
		if($row_batch=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
			$_BatchName=$row_batch['batch'];
		}
		@$_SessionLabel=sa_session_label(date("Y"), $_BatchName, $_Term);

		foreach ($_ClassificationId as $_Selected_ClassificationId) 
		{
			$_Selected_ClassificationId=mysqli_real_escape_string($con,$_Selected_ClassificationId);
			include("code.php");
			@$_AssignmentId=$code;
			@$_Subject="";
			@$_ClassId="";

			$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassificationId'");
			if(!($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC))){
				$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white;padding:4px;'><i class='fa fa-check' style='color:red'></i> Skipped unknown subject classification.</div>";
				continue;
			}

			$_Subject=$row_s['subject'];
			$_ClassId=mysqli_real_escape_string($con,$row_s['classid']);
			@$_CLassName="";
			$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassId'");
			if($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
				$_CLassName=$row_cl['class_name'];
			}

			// One active teacher per subject-classification, batch and semester.
			$_SQL_EXIST=mysqli_query($con,"SELECT sa.assignmentid,sa.userid,su.firstname,su.othernames,su.surname
				FROM tblsubjectassignment sa
				LEFT JOIN tblsystemuser su ON su.userid=sa.userid
				WHERE sa.classificationid='$_Selected_ClassificationId' AND sa.batchid='$_BatchId' AND sa.termname='$_Term' AND sa.status='active'
				LIMIT 1");

			if($_SQL_EXIST && $row_exist=mysqli_fetch_array($_SQL_EXIST,MYSQLI_ASSOC)){
				@$_ExistingName=$row_exist['userid'];
				if(isset($row_exist['firstname']) && $row_exist['firstname']!==""){
					$_ExistingName=$row_exist['firstname']." ".$row_exist['othernames']." ".$row_exist['surname']." (".$row_exist['userid'].")";
				}

				if($row_exist['userid']===$_UserId){
					$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white;padding:4px;'><i class='fa fa-check' style='color:red'></i> $_CLassName : $_Subject already assigned to $_UserFullname for $_SessionLabel.</div>";
				}else{
					$_ExistingAssignmentId=mysqli_real_escape_string($con,$row_exist['assignmentid']);
					$_SQL_UPDATE=mysqli_query($con,"UPDATE tblsubjectassignment SET userid='$_UserId',recordedby='$_Recordedby',datetimeentry=NOW(),status='active' WHERE assignmentid='$_ExistingAssignmentId'");
					if($_SQL_UPDATE){
						$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:4px;'><i class='fa fa-check' style='color:green'></i> $_CLassName : $_Subject reassigned from $_ExistingName to $_UserFullname for $_SessionLabel.</div>";
					}else{
						$_Error=mysqli_error($con);
						$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_Subject failed to reassign,$_Error</div>";
					}
				}
			}else{
				$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsubjectassignment(assignmentid,userid,classid,classificationid,batchid,termname,datetimeentry,status,recordedby)
				VALUES('$_AssignmentId','$_UserId','$_ClassId','$_Selected_ClassificationId','$_BatchId','$_Term',NOW(),'active','$_Recordedby')");
				if($_SQL_EXECUTE)
				{
					$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:4px;'><i class='fa fa-check' style='color:green'></i> $_CLassName : $_Subject successfully assigned to $_UserFullname for $_SessionLabel.</div>";
				}
				else{
					$_Error=mysqli_error($con);
					$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_Subject failed to classify,$_Error</div>";
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
<link rel="stylesheet" href="css/subject-assignment.css">

</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	<?php
	//include("side-menu.php");

	?>
	</div>

<div class="main-platform assignment-page">
	<section class="assignment-hero">
		<div>
			<span class="assignment-kicker">Academic Tools</span>
			<h1>Subject Assignment</h1>
			<p>Assign classified subjects to teachers by batch and semester.</p>
		</div>
		<div class="assignment-hero-card">
			<i class="fa fa-user-plus"></i>
			<span>Teacher Subjects</span>
		</div>
	</section>

	<div class="assignment-layout">
		<aside class="assignment-side">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<section class='assignment-panel assignment-edit-panel'>";
			echo "<div class='assignment-panel-heading'><span class='assignment-icon'><i class='fa fa-edit'></i></span><div><h2>Update Subject</h2><p>Edit the selected subject name.</p></div></div>";
			echo "<form method='post' id='formID' name='formID' action='subject-assignment.php'>";
			echo "<label>Subject Id</label>";
			echo "<input type='text' id='update_subjectid' name='update_subjectid' value='$row[subjectid]' readonly>";

			echo "<label>Subject</label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[subject]' class='validate[required]'>";
			echo "<div class='assignment-actions'><button class='btn assignment-btn assignment-btn-primary' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit'></i> Update Subject</button></div>";
			echo "<div class='assignment-actions assignment-cancel-row'><a class='assignment-btn assignment-btn-light' href='subject-assignment.php'><i class='fa fa-times'></i> Cancel Edit</a></div>";

			echo "</form>";
			echo "</section>";
			}
		}
		}
		?>
			
			<section class="assignment-panel assignment-filter-panel">
			<div class="assignment-panel-heading">
				<span class="assignment-icon"><i class="fa fa-filter"></i></span>
				<div>
					<h2>Assignment Setup</h2>
					<p>Select teacher, batch, semester, then tick subjects.</p>
				</div>
			</div>
		
<form method="post" id="formID" name="formID" action="subject-assignment.php">
		<label>Teacher</label>
		<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Teacher' ORDER BY su.firstname ASC");

			echo "<select id='userid' name='userid' class='validate[required]'>";
			echo "<option value=''>Select Teacher</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[userid]'>$row[firstname] $row[othernames] $row[surname]($row[userid])</option>";
				}
				
			echo "</select>";
			?>

			<label>Batch</label>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch ORDER BY datetimeentry DESC");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select>";
			?>

			<div class="assignment-grid">
			<div>
			<label>Assignment Year</label>
			<input type="text" value="<?php echo date("Y"); ?>" readonly/>
			</div>

			<div>
			<label>Semester</label>
			<select id="term" name="term" class="validate[required]">
				<option value="" >Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
			
			</select>
			</div>
			</div>
			<div class="assignment-actions"><button class="button-save assignment-btn assignment-btn-primary" id="register_subject_assignment" name="register_subject_assignment"><i class="fa fa-save"></i> Save Assignment</button></div>
		
		</section>
		</aside>
			<main class="assignment-panel assignment-list-panel">
				<div class="assignment-panel-heading">
					<span class="assignment-icon"><i class="fa fa-check-square-o"></i></span>
					<div>
						<h2>Classified Subjects</h2>
						<p>Tick one or more classified subjects to assign to the selected teacher.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");

				$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name ASC");
				
				//Registered clients
				echo "<div class='assignment-table-wrap'>";
				echo "<table class='assignment-table'>";
				echo "<caption>List Of Subjects</caption>";
				echo "<thead><th>*</th><th colspan=1>Task</th><th>Subject Id</th><th>Subject</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
				{
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsubjectclassification sc
				INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid 
				WHERE sc.classid='$row_v[class_entryid]'");
				
				if(mysqli_num_rows($_SQL_EXECUTE)==0)	
				{

				}
				else{
				echo "<tr class='assignment-class-row'>";
				echo "<td colspan='9'>";
				echo $row_v['class_name'];
				echo "</td>";
				echo "</tr>";				
				@$serial=0;
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr class='assignment-subject-row'>";

				echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'>";
				//echo "<td align='center'><a title='Assign $row[subject]' onclick=\"javascript:return confirm('Do you want to assign $row[subject]?');\" href='subject-assignment.php?assign_subject=$row[classificationid]'<i class='fa fa-plus' style='color:green'></i></a></td>";
				echo "<input type='checkbox' id='classificationid' name='classificationid[]' value='$row[classificationid]'>";
				echo "</td>";
				//echo "<td align='center'>$row[classificationid]</td>";
				echo "<td align='left'>$row[subjectid]</td>";
				echo "<td align='left'>$row[subject]</td>";
echo "<td align='center'>$row[datetimeentry]</td>";
echo "<td align='center'>$row[status]</td>";
echo "</tr>";
}
}			
}
echo "</tbody>";
echo "</table>";
echo "</div>";
?>
</main>
</div>
</form>
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

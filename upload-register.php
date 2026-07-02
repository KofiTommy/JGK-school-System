<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Email=$_POST['email'];
@$_Mobile=$_POST['mobile'];

@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=md5($_POST['password']);
@$_AccessLevel="user";
@$_SystemType="Student";
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,email,mobile,registereddatetime,status,username,password,accesslevel,systemtype,branchid)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin_fullname','$_Nextofcontact','$_Email','$_Mobile',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>Teacher/Student Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>Teacher/Student Information Failed to save,Error:$_Error</div>";
}

}
?>


<?php
include("dbstring.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];

if(isset($_POST["send_message"])){
$_SQL=mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby)
VALUES('$_MessageId','$_Message',NOW(),'active','$_SESSION[USERID]')");
if($_SQL){
$_SESSION['Message']="<div style='color:green;padding:10px;'>Message Successfully Submitted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to submit</div>";
}
}
?>

<?php

if(isset($_GET["delete_message"])){
$_SQL_D=mysqli_query($con,"DELETE FROM tblmessages WHERE messageid='$_GET[delete_message]'");
if($_SQL_D){
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message Successfully Deleted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to delete</div>";
}

}
?>
<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/upload-register.css">
</head>
<body class="body-style">
<div class="header">
<?php
include("menu.php");
?>
</div>
<div class="main-platform upload-register-page">
	<?php
	echo $_SESSION["Message"];
	?>

	<section class="upload-register-hero">
		<div>
			<span class="upload-register-kicker">Bulk registration</span>
			<h1>Upload Register</h1>
			<p>Import student or teacher records from Excel and review the accounts created today.</p>
		</div>
		<div class="upload-register-hero-card">
			<i class="fa fa-cloud-upload"></i>
			<span>Excel upload</span>
			<small>Use the approved register template before uploading.</small>
		</div>
	</section>

	<div class="upload-register-layout">
		<aside class="upload-register-panel upload-register-form-panel">
			<div class="upload-register-panel-heading">
				<span class="upload-register-icon"><i class="fa fa-upload"></i></span>
				<div>
					<h2>Group Student Registration</h2>
					<p>Select the register file and upload it safely.</p>
				</div>
			</div>

		<form method="post" action="import-data.php" id="form1" enctype="multipart/form-data">
			<div class="upload-register-dropzone">
				<i class="fa fa-file-excel-o"></i>
				<label for="file1">Upload Excel File*</label>
				<p>Choose the completed register spreadsheet from your device.</p>
				<input type="file" id="file1" name="file1" />
			</div>

			<div class="upload-register-actions">
				<button class="button-pay upload-register-btn upload-register-btn-primary" id="submit_group_data" name="submit_group_data"><i class="fa fa-upload" ></i> Upload Data</button>
			</div>
		</form>
		</aside>

		<main class="upload-register-panel upload-register-list-panel">
			<div class="upload-register-panel-heading">
				<span class="upload-register-icon"><i class="fa fa-users"></i></span>
				<div>
					<h2>Today's Uploaded Users</h2>
					<p>Recently created student and teacher accounts appear here for quick review.</p>
				</div>
			</div>
				<?php
				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE date_format(registereddatetime,'%d-%m-%Y')=date_format(NOW(),'%d-%m-%Y') AND (systemtype='Student' OR systemtype='Teacher') ORDER BY registereddatetime DESC");

				//Registered clients
				echo "<div class='upload-register-table-wrap'>";
				echo "<table class='upload-register-table'>";
				echo "<caption>Uploaded Records</caption>";
				echo "<thead><tr><th colspan=2>TASK</th><th>STUDENTS</th><th>GENDER</th><th>BIRTHDAY</th><th>USERNAME</th><th>TYPE</th><th>DATE/TIME</th><th>STATUS</th></tr></thead>";
				echo "<tbody>";
				if(mysqli_num_rows($_SQL_EXECUTE)<1){
					echo "<tr><td colspan='9' class='upload-register-empty-row'>No users uploaded today.</td></tr>";
				}
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr class='upload-register-user-row'>";
				echo "<td align='center'><a class='upload-register-row-action' title='View $row[firstname] ($row[userid])' href='user-profile.php?view_user=$row[userid]'><i class='fa fa-book'></i></a></td>";
				//echo "<td align='center'><a title='Delete $row[firstname] ($row[userid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='viewstudents.php?delete_user=$row[userid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a class='upload-register-row-action upload-register-action-success' title='Edit $row[firstname] ($row[userid])' href='register_edit.php?edit_user=$row[userid]'><i class='fa fa-edit'></i></a></td>";
				//echo "<td>";
				if($row['status']=="active"){
				//echo"<a title='Block $row[firstname] ($row[userid])' href='viewstudents.php?block_user=$row[userid]'<i class='fa fa-user' style='color:orange'></i></a>";
					
			}else{
				//echo"<a title='Unblock $row[firstname] ($row[userid])' href='viewstudents.php?unblock_user=$row[userid]'<i class='fa fa-user' style='color:red'></i></a>";
				
			}
			//	echo "</td>";


				echo "<td>$row[firstname] $row[surname] $row[othernames]($row[userid])</td>";
				echo "<td align='center'>$row[gender]</td>";
				echo "<td align='center'>$row[birthday]</td>";
				echo "<td align='center'>$row[username]</td>";
				
				echo "<td align='center'>$row[systemtype]</td>";
				echo "<td align='center'>$row[registereddatetime]</td>";
				echo "<td align='center'>";
				if($row['status']=="active"){
					echo "<span class='upload-register-status upload-register-status-active'>Active</span>";
				}
				else{
					echo "<span class='upload-register-status upload-register-status-blocked'>Blocked</span>";
				}
				echo "</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				echo "</div>";
				?>
		</main>
	</div>
</div>
</body>
</html>

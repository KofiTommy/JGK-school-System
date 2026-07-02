<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
include_once("student-index-utils.php");
$_SchoolIndexReady = student_index_ensure_schema($con);

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
<link rel="stylesheet" href="css/upload-class-registry.css">

</head>

<body class="body-style">
	<!--Header-->
	
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	<?php
	//include("side-menu.php");
	?>
	</div>
<div class="main-platform upload-registry-page">
	<section class="upload-registry-hero">
		<div>
			<span class="upload-registry-kicker">Student Records</span>
			<h1>Upload Class Registry</h1>
			<p>Import class registration records from Excel and review the uploaded student class assignments.</p>
		</div>
		<div class="upload-registry-hero-card">
			<i class="fa fa-upload"></i>
			<span>Bulk Registry Upload</span>
		</div>
	</section>

	<?php
	echo $_SESSION["Message"];
	?>
	<div class="upload-registry-layout">
		<aside class="upload-registry-panel upload-registry-form-panel">
			<div class="upload-registry-panel-heading">
				<span class="upload-registry-icon"><i class="fa fa-file-excel-o"></i></span>
				<div>
					<h2>Upload Register</h2>
					<p>Select an Excel file containing class registration data.</p>
				</div>
			</div>
		<form method="post" action="import-class-data.php" id="form1" enctype="multipart/form-data">
			<div class="upload-registry-dropzone">
				<i class="fa fa-cloud-upload"></i>
				<label for="file1">Upload Excel File</label>
				<p>Choose the prepared class registry spreadsheet.</p>
				<input type="file" id="file1" name="file1" />
			</div>
			
			<!--Submit form's data -->
			<div class="upload-registry-actions"><button class="button-pay upload-registry-btn upload-registry-btn-primary" id="submit_group_data" name="submit_group_data"><i class="fa fa-upload"></i> Upload Data</button></div>
	</form>
		</aside>

			<main class="upload-registry-panel upload-registry-list-panel">
				<div class="upload-registry-panel-heading">
					<span class="upload-registry-icon"><i class="fa fa-list"></i></span>
					<div>
						<h2>Student Class Registration</h2>
						<p>Review active class assignments after upload.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC");

				//Registered clients
				echo "<div class='upload-registry-table-wrap'>";
				echo "<table class='upload-registry-table'>";
				echo "<caption>STUDENT CLASS REGISTRATION</caption>";
				echo "<thead><th>*</th><th colspan=1>TASK</th><th>STUDENT</th><th>CLASS</th><th>BATCH</th><th>ENTRY DATE/TIME</th></thead>";
				echo "<tbody>";
				@$serial=0;

				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr class='upload-registry-student-row'>";
				echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				echo "<td align='center' ><a class='upload-registry-row-action' title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'><i class='fa fa-plus'></i></a></td>";
				$_DisplayIndex=trim((string)(isset($row['schoolindexnumber']) ? $row['schoolindexnumber'] : ""));
				$_DisplayRef=$_DisplayIndex !== "" ? $_DisplayIndex : $row['userid'];
				echo "<td colspan='4'>$row[firstname] $row[othernames] $row[surname] ($_DisplayRef)";
				if($_DisplayIndex !== "" && $_DisplayIndex !== $row['userid']){
					echo " <span class='upload-registry-index-chip'>Login ID: $row[userid]</span>";
				}
				echo "</td>";
			
				echo "</tr>";
				
				$_SQL_CLASS=mysqli_query($con,"SELECT *,cl.datetimeentry FROM tblclass cl 
				INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
				INNER JOIN tblbatch bh ON cl.batchid=bh.batchid
				 WHERE cl.userid='$row[userid]' AND cl.status='active' ORDER BY ce.class_name ASC");
				while($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
				echo "<tr class='upload-registry-class-row'>";
			
				echo "<td colspan='1'>";
				echo "</td>";

				echo "<td align='center'><a class='upload-registry-row-action upload-registry-action-danger' onclick=\"javascript:return confirm('Do you want to remove class?')\" title='Remove class $row_cl[class_name]' href='upload-class-registry.php?delete_class=$row_cl[classid]'><i class='fa fa-trash-o'></i></a></td>";
				echo "<td colspan='1' align='right'>";
				echo "Class:";
				echo "</td>";
				echo "<td colspan='1'>";
				echo $row_cl['class_name'];
				echo "</td>";

				echo "<td colspan='1'>";
				echo $row_cl['batch'];
				echo "</td>";


				echo "<td colspan='1'>";
				echo $row_cl['datetimeentry'];
				echo "</td>";

				echo "</tr>";
				}
			}
				echo "</tbody>";
				echo "</table>";
				echo "</div>";
				?>
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

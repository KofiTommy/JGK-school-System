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
@$_SystemType=$_POST['systemtype'];
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
<style>
:root {
    --bg-1: #f3f4f6;
    --bg-2: #fffaf0;
    --ink: #1f2937;
    --panel: #ffffff;
    --line: #e5e7eb;
    --brand: #0f766e;
    --muted: #64748b;
    --accent: #b45309;
}

.body-style {
    margin: 0;
    color: var(--ink);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: radial-gradient(circle at 0% 0%, #fef3c7 0%, transparent 24%),
                radial-gradient(circle at 100% 0%, #dbeafe 0%, transparent 28%),
                linear-gradient(180deg, var(--bg-2), var(--bg-1));
}

.header {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid var(--line);
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    padding: 12px 20px;
}

.main-platform {
    max-width: 1420px;
    margin: 0 auto;
    padding: 20px 18px 28px;
}

.dashboard-headline {
    margin: 0 0 14px;
    padding: 16px 18px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: linear-gradient(135deg, #0f766e 0%, #155e75 100%);
    color: #ecfeff;
    box-shadow: 0 14px 36px rgba(8, 47, 73, 0.22);
}

.dashboard-headline h2 {
    margin: 0 0 6px;
}

.dashboard-headline p {
    margin: 0;
    color: #cffafe;
}

.main-platform > table {
    border-collapse: separate;
    border-spacing: 16px 0;
}

.main-platform > table td {
    vertical-align: top;
}

.main-platform > table td:first-child > * {
    position: sticky;
    top: 14px;
}

.form-entry {
    border-radius: 14px;
    border: 1px solid var(--line);
    background: var(--panel);
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    padding: 14px;
}

.form-entry table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

.form-entry table caption {
    text-align: left;
    font-weight: 700;
    margin-bottom: 9px;
    color: #0f172a;
}

.form-entry table thead th {
    text-align: left;
    font-weight: 700;
    color: #334155;
    border-bottom: 1px solid var(--line);
    padding: 9px 10px;
    background: #f8fafc;
}

.form-entry table td {
    border-bottom: 1px solid #f1f5f9;
    padding: 10px;
}

.form-entry table tbody tr:hover {
    background: #f8fafc;
}

@media (max-width: 980px) {
    .main-platform > table,
    .main-platform > table tbody,
    .main-platform > table tr,
    .main-platform > table td {
        display: block;
        width: 100%;
    }
    .main-platform > table {
        border-spacing: 0;
    }
    .main-platform > table td:first-child > * {
        position: static;
        margin-bottom: 14px;
    }
}
</style>
</head>
<body class="body-style">
	<?php
if(!$_SESSION["AUDITDATE"]){
header("location:auditdatealert.php");
}
else{
echo "<div style='font-size:11px;font-family:tahoma;color:blue;text-align:right'>Audit Date:". $_SESSION["AUDITDATE"]."</div>";  
}
?>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>

<div class="main-platform">
	<?php
	echo $_SESSION["Message"];
	?>
    <div class="dashboard-headline">
        <h2>Operations Dashboard</h2>
        <p>Monitor daily registrations and manage academic operations in one view.</p>
    </div>
<table border="0" width="100%">
<tr>
<td width="25%" valign="top">
<?php
include("welcome.php");
include("menuboard.php");
?>
</td>
<td width="75%" valign="top" align="left">
<div class="form-entry">
<?php
include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE date_format(registereddatetime,'%d-%m-%Y')=date_format(NOW(),'%d-%m-%Y') AND (systemtype='Student' OR systemtype='Teacher') ORDER BY registereddatetime DESC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>DAILY REGISTRATION</caption>";
				echo "<thead><th colspan=2>Task</th><th>User</th><th>Gender</th><th>Birthday</th><th>Username</th><th>Type</th><th>Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='user-profile.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				//echo "<td align='center'><a title='Delete $row[firstname] ($row[userid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='viewstudents.php?delete_user=$row[userid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[firstname] ($row[userid])' href='register_edit.php?edit_user=$row[userid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
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
					echo "<strong style='color:green'>Active</strong>";
				}
				else{
					echo "<strong style='color:red'>Blocked</strong>";
				}
				echo "</td>";
				echo "</tr>";
				}
				echo "</tbody>";
echo "</table>";
?>
</div>
</td>
</tr>
</table>
</div>
</body>
</html>

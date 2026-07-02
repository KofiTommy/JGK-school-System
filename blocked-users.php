<?php
session_start();
$_SESSION['Message']="";
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
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=$_POST['password'];
@$_AccessLevel="user";
@$_SystemType=$_POST['systemtype'];
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,registereddatetime,status,username,password,accesslevel,systemtype,branchid)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin_fullname','$_Nextofcontact',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>User Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>User Information Failed to save,Error:$_Error</div>";
}
}
?>
<?php
include("dbstring.php");
if(isset($_GET["block_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='block' WHERE userid='$_GET[block_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User is blocked</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User failed to block</div>";
	}
}
?>
<?php
include("dbstring.php");
if(isset($_GET["unblock_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='active' WHERE userid='$_GET[unblock_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>User is active</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to unblock</div>";
	}
}
?>
<?php
include("dbstring.php");
if(isset($_GET["delete_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsystemuser WHERE userid='$_GET[delete_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User Record Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User failed to delete</div>";
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
</div><br/>
<div class="main-platform">
<table width="100%">
<tr>
<td width="100%">
<div class="form-entry">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype<>'super_user' AND status='block' ORDER BY firstname ASC");
//Registered clients
if(mysqli_num_rows($_SQL_EXECUTE)>0){
echo "<table width='100%' style='background-color:white'>";
echo "<caption> ALL BLOCKED USERS </caption>";
echo "<thead><th colspan=4>Task</th><th>User Id</th><th>First Name</th><th>Surname</th><th>Othernames</th><th>Gender</th><th>Birthday</th><th>System Type</th><th>Entry Date/Time</th><th>Status</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
echo "<tr>";
echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='user-profile.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
echo "<td align='center'><a title='Delete $row[firstname] ($row[userid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='blocked-users.php?delete_user=$row[userid]'<i class='fa fa-times' style='color:red'></i></a></td>";
echo "<td align='center'><a title='Edit $row[firstname] ($row[userid])' href='register_edit.php?edit_user=$row[userid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
echo "<td>";
if($row['status']=="active"){
echo"<a title='Block $row[firstname] ($row[userid])' href='blocked-users.php?block_user=$row[userid]'<i class='fa fa-user' style='color:orange'></i></a>";
}else{
echo"<a title='Unblock $row[firstname] ($row[userid])' href='blocked-users.php?unblock_user=$row[userid]'<i class='fa fa-user' style='color:red'></i></a>";
}
echo "</td>";
echo "<td align='center'>$row[userid]</td>";
echo "<td>$row[firstname]</td>";
echo "<td>$row[surname]</td>";
echo "<td>$row[othernames]</td>";
echo "<td align='center'>$row[gender]</td>";
echo "<td align='center'>$row[birthday]</td>";
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
}
else{
	echo "<div style='color:red;text-align:center'>*****************NO BLOCKED USERS FOUND******************** </div>";
}
?>
</div>
</td>
</tr>
</table>

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
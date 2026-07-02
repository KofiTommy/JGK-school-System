<?php
session_start();

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
@$_Nextofkin=$_POST['nextofkin'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,nextofkin_fullname,nextofkin_contact,registereddatetime,recordedby,status,username,password,accesslevel,finame)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender','$_Birthday','$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin','')");
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
<div class="main-platform" style="background-color:white">
<table width="200%">
<tr>
<td width="100%" style="border:1px solid #fff">
<?php
include("dbstring.php");
$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='user' OR systemtype='normal_user'");
if(mysqli_num_rows($_SQL_EXECUTE)<=0){
echo "<div style='color:red;text-align:center'>************NO USER FOUND*******************</div>";
}
else{
	//Registered clients
				echo "<table width='100%'>";
				echo "<caption>USERS</caption>";
				echo "<thead><th>User Id</th><th>Username</th><th>First Name</th><th>Surname</th><th>Othernames</th><th>Gender</th><th>Birthday</th><th>Age</th><th>Postal Address</th><th>Home Address</th><th>Home Town</th><th>Religion</th><th>Relationship</th><th>Next Of Kin Fullname</th><th>Next Of Kin Contact</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				
				echo "<td align='center'>$row[userid]</td>";
				echo "<td align='center'>$row[username]</td>";
				echo "<td align='center'>$row[firstname]</td>";
				echo "<td align='center'>$row[surname]</td>";
				echo "<td align='center'>$row[othernames]</td>";
				echo "<td align='center'>$row[gender]</td>";
				echo "<td align='center'>$row[birthday]</td>";
				echo "<td align='center'>$row[age]</td>";
				echo "<td align='center'>$row[postaladdress]</td>";
				echo "<td align='center'>$row[homeaddress]</td>";
				echo "<td align='center'>$row[hometown]</td>";
				echo "<td align='center'>$row[religion]</td>";
				echo "<td align='center'>$row[relationship]</td>";
				echo "<td align='center'>$row[nextofkin_fullname]</td>";
				echo "<td align='center'>$row[nextofkin_contact]</td>";
				echo "<td align='center'>$row[registereddatetime]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
			}
				?>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
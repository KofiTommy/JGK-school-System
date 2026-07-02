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

<div class="main" style="">
	<table width="200%">
		<tr>
			<td valign="top" width="50%">
				<div class="form-entry" align="left">
			<form method="post" id="formID" name="formID" action="register.php">

			<h3>Registration 
				</h3>
			<br/>
			<label>User Id</label><br/>
			<input type="text" id="userid" name="userid" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>STUDENT NAME</legend>
			<label>First Name</label><br/>
			<input type="text" id="firstname" name="firstname" class="validate[required]"/><br/><br/>

			<label>Surname</label><br/>
			<input type="text" id="surname" name="surname" /><br/><br/>

			<label>Othernames</label><br/>
			<input type="text" id="othernames" name="othernames" />
			</fieldset><br/><br/>

			<fieldset><legend>GENDER</legend>
			<input type="radio" id="gender" name="gender" value="male">
			<label>Male</label>
			<input type="radio" id="gender" name="gender" value="female">
			<label>Female</label>
			</fieldset><br/><br/>

			<label>Birthday</label><br/>
			<input type="date" id="birthday" name="birthday" value=""/><br/><br/>

			<label>Age</label><br/>
			<input type="number" id="age" name="age" /><br/><br/>

			<label>Postal Address</label>
			<textarea id="postaladdress" name="postaladdress" ></textarea>
			<br/><br/>

			<label>Home Address</label>
			<textarea id="homeaddress" name="homeaddress" ></textarea>
			<br/><br/>
			<select id="religion" name="religion">
				<option value="">Select Religion</option>
				<option value="Christian">Christian</option>
				<option value="Muslim">Muslim</option>
				<option value="Tradition">Tradition</option>
				<option value="Others">Others</option>
			</select><br/><br/>
			<select id="relationship" name="relationship">
				<option value="">Select Relationship</option>
				<option value="Father">Father</option>
				<option value="Mother">Mother</option>
				<option value="Uncle">Uncle</option>
				<option value="Brother">Brother</option>
				<option value="Sister">Sister</option>
				<option value="Daughter">Daughter</option>
				<option value="Others">Others</option>
			</select><br/><br/>

			<fieldset><legend>Next Of Kin</legend>
			<label>Full Name</label><br/>
			<input type="text" id="nextoffullname" name="nextoffullname" /><br/><br/>
			<label>Contact</label>
			<input type="text" id="nextofkincontact" name="nextofkincontact" /><br/><br/>

			</fieldset><br/>

			<label>Username</label><br/>
			<input type="text" id="username" name="username" /><br/><br/>

			<label>Password</label><br/>
			<input type="password" id="password" name="password" /><br/><br/>
			
			<select>
				<option value="">Select System Type</option>
				<option value="Student">Student</option>
				<option value="Teacher">Teacher</option>
				<option value="User">User</option>
			</select><br/><br/>

			<div align="center"><button class="btn" id="register_user" name="register_user"><i class="fa fa-send"></i> Submit</button></div>

		</form>

		</div>
			</td>
			<td width="70%">
				<?php
				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser");

				//Registered clients
				echo "<table width='100%'>";
				echo "<thead><th colspan=3>Task</th><th>User Id</th><th>First Name</th><th>Surname</th><th>Othernames</th><th>Gender</th><th>Birthday</th><th>Age</th><th>Postal Address</th><th>Home Address</th><th>Home Town</th><th>Religion</th><th>Relationship</th><th>Next Of Kin Fullname</th><th>Next Of Kin Contact</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td><a onclick=\"javascript:return confirm('Do you want to delete?');\" href='register.php?delete_user=$row[userid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td><a href='register.php?edit_user=$row[userid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				echo "<td><a href='register.php?block_user=$row[userid]'<i class='fa fa-user' style='color:orange'></i></a></td>";


				echo "<td>$row[userid]</td>";
				echo "<td>$row[firstname]</td>";
				echo "<td>$row[surname]</td>";
				echo "<td>$row[othernames]</td>";
				echo "<td>$row[gender]</td>";
				echo "<td>$row[birthday]</td>";
				echo "<td>$row[age]</td>";
				echo "<td>$row[postaladdress]</td>";
				echo "<td>$row[homeaddress]</td>";
				echo "<td>$row[hometown]</td>";
				echo "<td>$row[religion]</td>";
				echo "<td>$row[relationship]</td>";
				echo "<td>$row[nextofkin_fullname]</td>";
				echo "<td>$row[nextofkin_contact]</td>";
				echo "<td>$row[registereddatetime]</td>";
				echo "<td>$row[status]</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				?>


			</td>
		</tr>

	</table>
		<!--Login-->
		
		<br/><br/><br/>

</div>

<!--Footer-->

</body>
</html>
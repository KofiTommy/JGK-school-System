<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch_month']." ".$_POST['batch_year'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['register_class'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclass(classid,userid,class_entryid,batch,datetimeentry,recordedby,status)
	VALUES('$_ClassId','$_UserId','$_Class','$_Batch',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Class Successfully Registered</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Class failed to register,$_Error</div>";
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

<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
			<h3>Term Registration 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="class-registry.php">

			<label>Term Id</label><br/>
			<input type="text" id="termid" name="termid" value="<?php echo date("Y").date("m").date("d").date("h").date("i")."_".mt_rand(10,9999999);?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>STUDENT NAME</legend>
			<?php
			if(isset($_GET['view_user'])){
			@$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[view_user]'");
			
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
			$_FullName=$row['firstname']." ".$row['surname']." ".$row['othernames']."(".$row['userid'].")";
			echo "<input type='text' id='firstname' name='firstname' value='$_FullName' class='validate[required]' readonly/><br/><br/>";
			echo "<input type='hidden' id='userid' name='userid' value='$row[userid]' class='validate[required]' readonly/>";
			
			}
			}
			?>
			
			</fieldset><br/>
			
				<select id="term" name="term">
					<option value="" class="validate[required]">Select Term</option>
					<option value="1">1</option>
					<option value="2">2</option>
					<option value="3">3</option>
				</select>
				<br/><br/>

			
			<?php
			echo "<fieldset><legend>BATCH</legend>";
			echo "<table>";
			echo "<tr>";
			echo "<td>";
			echo "<select id='batch_month' name='batch_month' class='validate[required]'>";
			echo "<option value=''>Select Month</option>";
			echo "<option value='January'>January</option>";
			echo "<option value='February'>February</option>";
			echo "<option value='March'>March</option>";
			echo "<option value='April'>April</option>";
			echo "<option value='May'>May</option>";
			echo "<option value='June'>June</option>";
			echo "<option value='July'>July</option>";
			echo "<option value='August'>August</option>";
			echo "<option value='September'>September</option>";
			echo "<option value='October'>October</option>";
			echo "<option value='November'>November</option>";
			echo "<option value='December'>December</option>";
			echo "</select>";
			echo "</td>";

			echo "<td>";
			$k=2018;
			echo "<select id='batch_year' name='batch_year' class='validate[required]'>";
			echo "<option value=''>Select Year</option>";
			while($k<=2030){
			echo "<option value='$k'>$k</option>";
			$k++;
			}
			echo "</select>";
			echo "</td>";
			echo "</tr>";
			echo "</table>";
			echo "</fieldset>";
			//echo "<br/>";
			?>

			<br/><br/>
			<div align="center"><button class="btn" id="register_class" name="register_class"><i class="fa fa-plus"></i> Save</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype<>'super_user' ORDER BY firstname ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<thead><th colspan=1>Task</th><th>User Id</th><th>First Name</th><th>Surname</th><th>Othernames</th><th>Gender</th><th>Birthday</th><th>Age</th><th>Entry Date/Time</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='term-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				
				echo "<td>$row[userid]</td>";
				echo "<td>$row[firstname]</td>";
				echo "<td>$row[surname]</td>";
				echo "<td>$row[othernames]</td>";
				echo "<td>$row[gender]</td>";
				echo "<td>$row[birthday]</td>";
				echo "<td>$row[age]</td>";
				echo "<td>$row[registereddatetime]</td>";
				
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
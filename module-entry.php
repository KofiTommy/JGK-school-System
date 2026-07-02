<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_moduleid=$_POST['moduleid'];
@$_module=$_POST['Module'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_module'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblmodule(moduleid,module,datetimeentry,status,recordedby)
	VALUES('$_moduleid','$_module',NOW(),'active','$_Recordedby')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Module Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Module failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_module=$_POST['update_item'];
@$_Update_moduleid=$_POST['update_moduleid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblmodule SET module='$_Update_module' WHERE moduleid='$_Update_moduleid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Module Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Module failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmodule WHERE moduleid='$_GET[delete_item]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Module Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Module failed to delete,Error:$_Error</div>";
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
<br/>
<br/>
<div class="main" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblmodule WHERE moduleid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Item Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='Module-entry.php'>";
			echo "<label>Item Id</label>";
			echo "<input type='text' id='update_moduleid' name='update_moduleid' value='$row[moduleid]'><br/>";

			echo "<label>Item </label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[module]'><br/><br/>";
			echo "<div align='center'><button class='btn' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit' style='color:white'></i> Update</button></div>";

			echo "</form>";
			}
		}
		}
		?>


			
			<h3>Module Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="Module-entry.php">

			<label>Module Id</label><br/>
			<input type="text" id="moduleid" name="moduleid" value="<?php include("code.php");echo $code;?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>Module</legend>
			<input type="text" id="Module" name="Module" value="" class="validate[required]"/><br/><br/>
			</fieldset><br/>

			
			<div align="center"><button class="btn" id="register_module" name="register_module"><i class="fa fa-plus"></i> Save</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblmodule ORDER BY module ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Items</caption>";
				echo "<thead><th colspan=2>Task</th><th>Module Id</th><th>Module</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[module] ($row[moduleid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='Module-entry.php?delete_item=$row[moduleid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[module] ($row[moduleid])'  href='Module-entry.php?edit_item=$row[moduleid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'>$row[moduleid]</td>";
				echo "<td align='center'>$row[module]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
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
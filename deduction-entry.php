<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_deductionid=$_POST['deductionid'];
@$_deductionname=$_POST['Deduction'];
@$_percent=$_POST['percent'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_Deduction'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tbldeductions(deductionid,deductionname,percent,datetimeentry,status,recordedby)
	VALUES('$_deductionid','$_deductionname','$_percent',NOW(),'active','$_Recordedby')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Deduction Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Deduction failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_deductionname=$_POST['update_deduction'];
@$_Update_Percentage=$_POST['update_percentage'];
@$_Update_deductionid=$_POST['update_deductionid'];

if(isset($_POST['update_deduction_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tbldeductions SET deductionname='$_Update_deductionname',percent='$_Update_Percentage' WHERE deductionid='$_Update_deductionid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;padding:10px;text-align:center;background-color:white'>Deduction Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Deduction failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tbldeductions WHERE deductionid='$_GET[delete_item]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Deduction Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Deduction failed to delete,Error:$_Error</div>";
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
<div class="main" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tbldeductions WHERE deductionid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Deduction Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='deduction-entry.php'>";
			echo "<label>Deduction Id</label>";
			echo "<input type='text' id='update_deductionid' name='update_deductionid' value='$row[deductionid]' readonly><br/>";

			echo "<label>Deduction </label>";
			echo "<input type='text' id='update_deduction' name='update_deduction' value='$row[deductionname]'><br/><br/>";
			
			echo "<label>Percentage </label>";
			echo "<input type='text' id='update_percentage' name='update_percentage' value='$row[percent]'><br/><br/>";
			
			echo "<div align='center'><button class='btn' id='update_deduction_entry' name='update_deduction_entry'><i class='fa fa-edit' style='color:white'></i> Update</button></div>";

			echo "</form>";
			}
		}
		}
		?>
		<br/><br/>
			
			<h3>Deduction Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="deduction-entry.php">

			<label>Deduction Id</label><br/>
			<input type="text" id="deductionid" name="deductionid" value="<?php include("code.php");echo $code;?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>Deduction</legend>
				<label>Deduction</label>
			<input type="text" id="Deduction" name="Deduction" value="" class="validate[required]"/><br/><br/>
			<label>Percentage</label>
			<input type="text" id="percent" name="percent" value="" class="validate[required]"/><br/><br/>
			
			</fieldset><br/>

			
			<div align="center"><button class="btn" id="register_Deduction" name="register_Deduction"><i class="fa fa-plus"></i> Save</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tbldeductions ORDER BY deductionname ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Items</caption>";
				echo "<thead><th colspan=2>Task</th><th>Deduction Id</th><th>Deduction</th><th>Percentage</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[deductionname] ($row[deductionid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='deduction-entry.php?delete_item=$row[deductionid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[deductionname] ($row[deductionid])'  href='deduction-entry.php?edit_item=$row[deductionid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'>$row[deductionid]</td>";
				echo "<td align='center'>$row[deductionname]</td>";
				echo "<td align='center'>$row[percent]</td>";
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
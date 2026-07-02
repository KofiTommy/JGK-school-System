<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_typeid=$_POST['typeid'];
@$_accounttype=strtoupper($_POST['accountname']);
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_item'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblaccounttype(typeid,accounttype,datetimeentry,recordedby,status)
	VALUES('$_typeid','$_accounttype',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Account Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Account failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_accounttype=$_POST['update_item'];
@$_Update_typeid=$_POST['update_typeid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblitem SET accounttype='$_Update_accounttype' WHERE typeid='$_Update_typeid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Item Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Item failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblaccounttype WHERE typeid='$_GET[delete_item]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Account Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Account failed to delete,Error:$_Error</div>";
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

	</div>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitem WHERE typeid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Item Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='account-type.php'>";
			echo "<label>Item Id</label>";
			echo "<input type='text' id='update_typeid' name='update_typeid' value='$row[typeid]'><br/>";

			echo "<label>Item </label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[accounttype]'><br/><br/>";
			echo "<div align='center'><button class='button-edit' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit'></i> Update</button></div>";

			echo "</form>";
			}
		}
		}
		?>
			<h3>Account Type 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="account-type.php">

			<label>Account Id</label><br/>
			<input type="text" id="typeid" name="typeid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" /><br/><br/>

			<fieldset><legend>Account Name</legend>
			<input type="text" id="accountname" name="accountname" value="" class="validate[required]"/><br/><br/>

			
			</fieldset><br/>

			
			<div align="center"><button class="button-save" id="register_item" name="register_item"><i class="fa fa-save"></i> SAVE </button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<div class="form-entry">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblaccounttype ORDER BY accounttype ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Accounts</caption>";
				echo "<thead><th colspan=2>Task</th><th>Item Id</th><th>Item</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[accounttype] ($row[typeid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='account-type.php?delete_item=$row[typeid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[accounttype] ($row[typeid])'  href='account-type.php?edit_item=$row[typeid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'>$row[typeid]</td>";
				echo "<td align='center'>$row[accounttype]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
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
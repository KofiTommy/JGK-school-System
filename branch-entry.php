<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_branchid=$_POST['branchid'];
@$_companyid=$_POST['companyid'];

@$_address=$_POST['address'];
@$_location=$_POST['location'];
@$_telephone1=$_POST['telephone1'];
@$_telephone2=$_POST['telephone2'];
@$_Initials=$_POST['initials'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_branch'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbranch(branchid,companyid,address,location,telephone1,telephone2,createdby,datetimeentry,status,initials)
VALUES('$_branchid','$_companyid','$_address','$_location','$_telephone1','$_telephone2','$_Recordedby',NOW(),'active','$_Initials')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Branch Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Branch failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_Address=$_POST['update_address'];
@$_Update_Location=$_POST['update_location'];
@$_Update_Telephone1=$_POST['update_telephone1'];
@$_Update_Telephone2=$_POST['update_telephone2'];
@$_Update_branchid=$_POST['update_branchid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblbranch SET address='$_Update_Address',location='$_Update_Location',telephone1='$_Update_Telephone1',telephone2='$_Update_Telephone2' 
	WHERE branchid='$_Update_branchid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Branch Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Branch failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblbranch WHERE branchid='$_GET[delete_item]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Branch Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Branch failed to delete,Error:$_Error</div>";
	}
}
?>



<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/branch-entry.css">
<script type="text/javascript">
function getInitials(){
	var ini=document.getElementById("location").value;
	document.getElementById("initials").value= ini.substring(0,2).toUpperCase()+"_";

}
</script>
</head>

<body>

	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	<?php
	//include("side-menu.php");

	?>
	</div>

<div class="main-platform branch-page">
	<section class="branch-hero">
		<div>
			<span class="branch-kicker">Academic Setup</span>
			<h1>Branch Entry</h1>
			<p>Create and manage school branches, locations, contact numbers, and initials.</p>
		</div>
		<div class="branch-hero-card">
			<i class="fa fa-map-marker"></i>
			<span>Branch Manager</span>
		</div>
	</section>

	<div class="branch-layout">
		<aside class="branch-side">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblbranch WHERE branchid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<section class='branch-panel branch-edit-panel'>";
			echo "<div class='branch-panel-heading'><span class='branch-icon'><i class='fa fa-edit'></i></span><div><h2>Update Branch</h2><p>Edit the selected branch details.</p></div></div>";
			echo "<form method='post' id='formID' name='formID' action='branch-entry.php'>";
			echo "<label>Branch Id</label>";
			echo "<input type='text' id='update_branchid' name='update_branchid' value='$row[branchid]' readonly>";

			echo "<label>Address</label>";
			
			echo "<textarea id='update_address' name='update_address'>$row[address] </textarea>";
			echo "<label>Location</label>";
			echo "<input type='text' id='update_location' name='update_location' value='$row[location]'>";
			echo "<div class='branch-grid'>";
			echo "<div><label>Telephone 1</label><input type='text' id='update_telephone1' name='update_telephone1' value='$row[telephone1]' maxlength='10' /></div>";
			echo "<div><label>Telephone 2</label><input type='text' id='update_telephone2' name='update_telephone2' value='$row[telephone2]' maxlength='10' /></div>";
			echo "</div>";
			
			echo "<div class='branch-actions'><button class='button-edit branch-btn branch-btn-primary' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit'></i> Update Branch</button></div>";
			echo "<div class='branch-actions branch-cancel-row'><a class='branch-btn branch-btn-light' href='branch-entry.php'><i class='fa fa-times'></i> Cancel Edit</a></div>";

			echo "</form>";
			echo "</section>";
			}
		}
		}
		?>


			
			<section class="branch-panel branch-create-panel">
			<div class="branch-panel-heading">
				<span class="branch-icon"><i class="fa fa-plus"></i></span>
				<div>
					<h2>Create Branch</h2>
					<p>Add a new branch under a school record.</p>
				</div>
			</div>
		
			<form method="post" id="formID" name="formID" action="branch-entry.php">
			<label>School</label>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblcompany");

			echo "<select id='companyid' name='companyid' class='validate[required]'>";
			echo "<option value=''>Select Company</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[companyid]'>$row[fullname]</option>";
				}
				
			echo "</select>";
			?>

			<label>Branch Id</label>
			<input type="text" id="branchid" name="branchid" value="<?php echo date("Y").date("m").date("d").date("h").date("i")."_".mt_rand(10,9999999);?>" class="validate[required]" readonly/>


			<fieldset class="branch-fieldset"><legend>Branch Details</legend>
			<label for="address">Address</label>
			<input type="text" id="address" name="address" value="" placeholder="Type Address" class="validate[required]"/>
			<label for="location">Location</label>
			<input type="text" id="location" name="location" value="" placeholder="Type Location" class="validate[required]" onchange="getInitials()"/>
			<div class="branch-grid">
			<div><label for="telephone1">Telephone 1</label><input type="text" id="telephone1" name="telephone1" value="" maxlength="10" placeholder="Type Telephone 1" class="validate[required,custom[phone]]"/></div>
			<div><label for="telephone2">Telephone 2</label><input type="text" id="telephone2" name="telephone2" value="" maxlength="10" placeholder="Type Telephone 2" class="validate[required,custom[phone]]"/></div>
			</div>
						
			
			<label>Initials</label>
			<input type="text" id="initials" name="initials" placeholder="Type Initials" class="validate[required]" readonly/>
			</fieldset>
			
			<div class="branch-actions"><button class="button-save branch-btn branch-btn-primary" id="register_branch" name="register_branch"><i class="fa fa-save"></i> Save Branch</button></div>
		</form>

		</section>
		</aside>
			<main class="branch-panel branch-list-panel">
				<div class="branch-panel-heading">
					<span class="branch-icon"><i class="fa fa-list"></i></span>
					<div>
						<h2>Branch List</h2>
						<p>Review branch records grouped by school.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];
				include("dbstring.php");
				$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblcompany");
				//Registered clients
				echo "<div class='branch-table-wrap'>";
				echo "<table class='branch-table'>";
				echo "<caption>LIST OF BRANCHES</caption>";
				echo "<thead><th colspan='2'>TASK</th><th>ADDRESS</th><th>LOCATION</th><th>TEL. 1</th><th>TEL. 2</th><th>DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
			
				while($row_c=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC)){

				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblbranch br INNER JOIN tblcompany c
				ON br.companyid=c.companyid WHERE br.companyid='$row_c[companyid]' ORDER BY br.location ASC");

				echo "<tr class='branch-company-row'>";
				echo "<td align='left' colspan='8'>$row_c[fullname]($row_c[companyid])</td>";
				echo "</tr>";

				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a class='branch-row-action branch-action-danger' title='Delete $row[fullname] ($row[branchid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='branch-entry.php?delete_item=$row[branchid]'><i class='fa fa-trash-o'></i></a></td>";
				echo "<td align='center'><a class='branch-row-action' title='Edit $row[fullname] ($row[branchid])'  href='branch-entry.php?edit_item=$row[branchid]'><i class='fa fa-edit'></i></a></td>";
				//echo "<td align='center'>$row[location]($row[branchid])</td>";
				echo "<td align='center'>$row[address]</td>";
				echo "<td align='center'>$row[location]</td>";
				echo "<td align='center'>$row[telephone1]</td>";
				echo "<td align='center'>$row[telephone2]</td>";
			
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[status]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
			}
				echo "</tbody>";
				echo "</table>";
				echo "</div>";
			///}
				?>
			</main>
		</div>
</div>
</body>
</html>

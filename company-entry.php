<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_companyid=$_POST['companyid'];
@$_fullname=$_POST['company'];
@$_Recordedby=$_SESSION['USERID'];
@$_FileName=$_FILES["logo"]["name"];

if(isset($_POST['register_company'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblcompany(companyid,fullname,logo,datetimeentry,status,recordedby)
	VALUES('$_companyid','$_fullname','$_FileName',NOW(),'active','$_Recordedby')");
if($_SQL_EXECUTE){
	move_uploaded_file($_FILES["logo"]["tmp_name"], "logo/".$_FileName);
	//move_uploaded_file($_FILES["file1"]["tmp_name"],"uploads/" . $_FILES["file1"]["name"]);
	
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>School Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>School failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_fullname=$_POST['update_item'];
@$_Update_companyid=$_POST['update_companyid'];
@$_FileName=$_FILES["logo"]["name"];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblcompany SET fullname='$_Update_fullname',logo='$_FileName' WHERE companyid='$_Update_companyid'");
if($_SQL_EXECUTE){
	move_uploaded_file($_FILES["logo"]["tmp_name"], "logo/".$_FileName);
	
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>School Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>School failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblcompany WHERE companyid='$_GET[delete_item]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>School Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>School failed to delete,Error:$_Error</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/company-entry.css">
</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform company-page">
	<section class="company-hero">
		<div>
			<span class="company-kicker">Academic Setup</span>
			<h1>School Information</h1>
			<p>Manage the school name and logo used across reports, letters, and official documents.</p>
		</div>
		<div class="company-hero-card">
			<i class="fa fa-building-o"></i>
			<span>School Profile</span>
		</div>
	</section>

	<div class="company-layout">
		<aside class="company-side">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblcompany WHERE companyid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<section class='company-panel company-edit-panel'>";
			echo "<div class='company-panel-heading'><span class='company-icon'><i class='fa fa-edit'></i></span><div><h2>Update School</h2><p>Edit the school name and upload a replacement logo.</p></div></div>";
			echo "<form method='post' id='formID' name='formID' action='company-entry.php' enctype='multipart/form-data'>";
			echo "<label>School Id</label>";
			echo "<input type='text' id='update_companyid' name='update_companyid' value='$row[companyid]' readonly>";

			echo "<label>School</label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[fullname]' class='validate[required]'>";
			echo "<label>Logo</label>";
			echo "<input type='file' id='logo' name='logo' value='' />";
			echo "<div class='company-actions'><button class='button-edit company-btn company-btn-primary' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit'></i> Update School</button></div>";
			echo "<div class='company-actions company-cancel-row'><a class='company-btn company-btn-light' href='company-entry.php'><i class='fa fa-times'></i> Cancel Edit</a></div>";

			echo "</form>";
			echo "</section>";
			}
		}
		}
		?>


			
			<section class="company-panel company-create-panel">
			<div class="company-panel-heading">
				<span class="company-icon"><i class="fa fa-plus"></i></span>
				<div>
					<h2>Create School</h2>
					<p>Add school information and upload the official logo.</p>
				</div>
			</div>
		
			<form method="post" id="formID" name="formID" action="company-entry.php" enctype="multipart/form-data">

			<label>School Id</label>
			<input type="text" id="companyid" name="companyid" value="<?php include("shortcode.php"); echo $shortcode;?>" class="validate[required]" readonly/>

			<fieldset class="company-fieldset"><legend>School Details</legend>
			<label for="company">School Name</label>
			<input type="text" id="company" name="company" value="" class="validate[required]"/>
			<label for="logo">Logo</label>
			<input type="file" id="logo" name="logo" value="" class="validate[required]"/>
			</fieldset>

			
			<div class="company-actions"><button class="button-save company-btn company-btn-primary" id="register_company" name="register_company"><i class="fa fa-save"></i> Save School</button></div>
		</form>

		</section>
		</aside>
			<main class="company-panel company-list-panel">
				<div class="company-panel-heading">
					<span class="company-icon"><i class="fa fa-list"></i></span>
					<div>
						<h2>School Records</h2>
						<p>Review, edit, or remove saved school profiles.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblcompany ORDER BY fullname ASC");

				//Registered clients
				echo "<div class='company-table-wrap'>";
				echo "<table class='company-table'>";
				echo "<caption>School Information</caption>";
				echo "<thead><th colspan=2>TASK</th><th>SCHOOL ID</th><th>SCHOOL</th><th>LOGO</th><th>DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a class='company-row-action company-action-danger' title='Delete $row[fullname] ($row[companyid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='company-entry.php?delete_item=$row[companyid]'><i class='fa fa-trash-o'></i></a></td>";
				echo "<td align='center'><a class='company-row-action' title='Edit $row[fullname] ($row[companyid])'  href='company-entry.php?edit_item=$row[companyid]'><i class='fa fa-edit'></i></a></td>";
				
				echo "<td align='center'>$row[companyid]</td>";
				echo "<td align='center'>$row[fullname]</td>";
				echo "<td align='center'>$row[logo]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				echo "</div>";
				?>
			</main>
	</div>
</div>
</body>
</html>

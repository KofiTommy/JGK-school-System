<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_class_entryid=$_POST['class_entryid'];
@$_className=$_POST['class'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_class'])){
//Check duplicate entry
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclassentry(class_entryid,class_name,datetimeentry,recordedby,status,branchid)
	VALUES('$_class_entryid','$_className',NOW(),'$_Recordedby','active','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>$_className Successfully Added</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Class failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_className=$_POST['update_class'];
@$_Update_class_entryid=$_POST['update_class_entryid'];

if(isset($_POST['update_class_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblclassentry SET class_name='$_Update_className' WHERE class_entryid='$_Update_class_entryid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Class Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>class failed to update,$_Error</div>";
	}
}
?>
<?php
include("dbstring.php");

if(isset($_GET["delete_class"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblclassentry WHERE class_entryid='$_GET[delete_class]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Class Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>class failed to delete,Error:$_Error</div>";
	}
}
?>
<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/class-entry.css">

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

<div class="main-platform class-page">
	<section class="class-hero">
		<div>
			<span class="class-kicker">Academic Setup</span>
			<h1>Class Entry</h1>
			<p>Create and manage the classes/forms used for registration, reports, subjects, and billing.</p>
		</div>
		<div class="class-hero-card">
			<i class="fa fa-university"></i>
			<span>Class Manager</span>
		</div>
	</section>

	<div class="class-layout">
		<aside class="class-side">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_class']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_GET[edit_class]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<section class='class-panel class-edit-panel'>";
			echo "<div class='class-panel-heading'><span class='class-icon'><i class='fa fa-edit'></i></span><div><h2>Update Class</h2><p>Edit the selected class name.</p></div></div>";
			echo "<form method='post' id='formID' name='formID' action='class-entry.php'>";
			echo "<label>Class Id</label>";
			echo "<input type='text' id='update_class_entryid' name='update_class_entryid' value='$row[class_entryid]' readonly>";

			echo "<label>Class</label>";
			echo "<input type='text' id='update_class' name='update_class' value='$row[class_name]' class='validate[required]'>";
			echo "<div class='class-actions'><button class='button-edit class-btn class-btn-primary' id='update_class_entry' name='update_class_entry'><i class='fa fa-edit'></i> Update Class</button></div>";
			echo "<div class='class-actions class-cancel-row'><a class='class-btn class-btn-light' href='class-entry.php'><i class='fa fa-times'></i> Cancel Edit</a></div>";

			echo "</form>";
			echo "</section>";
			}
		}
		}
		?>
			<section class="class-panel class-create-panel">
			<div class="class-panel-heading">
				<span class="class-icon"><i class="fa fa-plus"></i></span>
				<div>
					<h2>Create Class</h2>
					<p>Add a new class or form to the system.</p>
				</div>
			</div>
		
			<form method="post" id="formID" name="formID" action="class-entry.php">
			<label>Class Id</label>
			<input type="text" id="class_entryid" name="class_entryid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/>
			<fieldset class="class-fieldset"><legend>Class Details</legend>
			<label for="class">Class Name</label>
			<input type="text" id="class" name="class" value="" class="validate[required]"/>
			</fieldset>
			<div class="class-actions"><button class="button-save class-btn class-btn-primary" id="register_class" name="register_class"><i class="fa fa-save"></i> Save Class</button></div>
		</form>

		</section>
		</aside>
			<main class="class-panel class-list-panel">
				<div class="class-panel-heading">
					<span class="class-icon"><i class="fa fa-list"></i></span>
					<div>
						<h2>Class List</h2>
						<p>Review existing classes or make updates.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name ASC");

				//Registered clients
				echo "<div class='class-table-wrap'>";
				echo "<table class='class-table'>";
				echo "<caption>List Of Classes</caption>";
				echo "<thead><th colspan=2>TASK</th><th>CLASS ID</th><th>CLASS</th><th>ENTRY DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a class='class-row-action class-action-danger' title='Delete $row[class_name] ($row[class_entryid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='class-entry.php?delete_class=$row[class_entryid]'><i class='fa fa-trash-o'></i></a></td>";
				echo "<td align='center'><a class='class-row-action' title='Edit $row[class_name] ($row[class_entryid])'  href='class-entry.php?edit_class=$row[class_entryid]'><i class='fa fa-edit'></i></a></td>";
				
				echo "<td>$row[class_entryid]</td>";
				echo "<td>$row[class_name]</td>";
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

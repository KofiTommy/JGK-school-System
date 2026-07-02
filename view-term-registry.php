<?php
session_start();
$_SESSION['Message']="";
?>


<?php
include("dbstring.php");

if(isset($_GET["delete_term"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tbltermregistry WHERE termid='$_GET[delete_term]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Term Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Term failed to delete</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_class'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclass(classid,userid,class_name,batch,datetimeentry,recordedby,status)
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

<div class="main" style="">
	<br/><br/>
	<table width="100%">
		<tr>
			<td width="70%">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT *,tr.datetimeentry FROM tbltermregistry tr 
					INNER JOIN tblsystemuser sy ON tr.userid=sy.userid 
					INNER JOIN tblclassentry ce ON tr.class_entryid=ce.class_entryid
					INNER JOIN tblbatch b ON b.batchid=tr.batchid");

				//Registered clients
				if(mysqli_num_rows($_SQL_EXECUTE)<=0){
				echo"<div style='color:red;text-align:center;background-color:white;padding:5px;'>There is no class registered</div>";
				}
				else{
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>TERM REGISTRY</caption>";
				echo "<thead><th colspan=2>Task</th><th>Full Name</th><th>Class</th><th>Term</th><th>Batch</th><th>Entry Date/Time</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='Delete $row[firstname] ($row[userid])' href='view-term-registry.php?delete_term=$row[termid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='view-term-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				
				echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
				echo "<td align='center'>$row[class_name]</td>";
				echo "<td align='center'>$row[termname]</td>";
				
				echo "<td align='center'>$row[batch]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				}
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
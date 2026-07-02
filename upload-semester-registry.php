<?php
session_start();
$_SESSION['Message']="";
?>

<html>
<head>
<?php
include("links.php");
?>

</head>

<body class="body-style">
	<!--Header-->
	
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	<?php
	//include("side-menu.php");

	?>
	</div>
<div class="main-platform" align="center" style="padding-left:20%;padding-right:20%;padding-top:5%">
	<?php
	echo $_SESSION["Message"];
	?><br/>
	<table border="0" width="100%">
		<tr>
			<td  valign="top" align="center">
				<h4>SEMESTER REGISTRATION</h4>		
				
	<div class="form-entry" align="left">
		<form id="formID" name="formID" method="post" action="import-semester-data.php" id="form1" enctype="multipart/form-data">
		<div align="center">

		<div id="subscription-style1" align="left">
			<!--Employee Details -->
			<h3>Group Semester Registration</h3>
			<div style="padding:10px;margin-bottom:12px;background:#eef6ff;border:1px solid #bcd;color:#0b63ce;line-height:1.6;">
				Upload columns in this order:
				<strong>User Id</strong>,
				<strong>Student Name</strong>,
				<strong>Class Name</strong>,
				<strong>Semester</strong>,
				<strong>Batch</strong>,
				<strong>Academic Year</strong>.
				<br/>Example Academic Year: <strong>2026</strong>
			</div>
			<label>Upload Excel File*</label><br>
			<input type="file" id="file1" name="file1" class="validate[required]"/><br><br>				
			
			<!--Submit form's data -->
			<button class="button-pay" id="submit_group_data" name="submit_group_data"><i class="fa fa-upload"></i> Upload Data</button>
			
			
			
		</div>
	</div>
	</form>
					
	
		</div>

 
			</td>

		<!--	<td width="70%" valign="top">
				<div class="form-entry" align="left">
			<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				

	$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student'");
	if(mysqli_num_rows($_SQL_SU)>0){


				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>STUDENT SEMESTER REGISTRATION</caption>";
				echo "<thead><th colspan=1>Task</th><th>Class</th><th>Semester</th><th>Batch</th><th>Entry Date/Time</th></thead>";
				echo "<tbody>";
	while($row_c=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#ffffff'>";
				//echo "<td align='center'><a title='View $row_c[firstname] ($row_c[userid])' href='term-registry.php?view_user=$row_c[userid]&class_id=$row_c[class_entryid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td colspan='4'>$row_c[firstname] $row_c[othernames] $row_c[surname] ($row_c[userid])</td>";
				echo "<td align='center'></td>";
				
				//echo "<td align='center'>$row[class_name]</td>";
				//echo "<td align='center'>$row[batch]</td>";
				//echo "<td align='center'>$row[datetimeentry]</td>";
				
				echo "</tr>";

				

				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclass cl 
					INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid 
					WHERE cl.userid='$row_c[userid]' ORDER BY ce.class_name ASC");

				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='View ($row[class_name])' href='term-registry.php?view_user=$row[userid]&class_id=$row[class_entryid]'><i class='fa fa-plus' style='color:royalblue'></i></a></td>";
				
				//echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
				//echo "<td align='center'></td>";
				//echo "<td align='center'></td>";
				
				echo "<td align='center'>$row[class_name]</td>";
				echo "<td align='center'></td>";
				
				echo "</tr>";


				$_SQL_TERM=mysqli_query($con,"SELECT *,tr.datetimeentry FROM tbltermregistry tr 
				INNER JOIN tblbatch b ON tr.batchid=b.batchid
				WHERE tr.userid='$row[userid]' AND tr.class_entryid='$row[class_entryid]' ORDER BY tr.termname ASC");
				while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#ffffff;border-bottom:1px solid gray'>";
				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove term?')\" title='Remove term $row_tr[termname]' href='term-registry.php?delete_term=$row_tr[termid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td colspan='1' align='right'>";
echo "</td>";
echo "<td align='center'>";
echo $row_tr['termname'];
echo "</td>";
echo "<td align='center'>$row_tr[batch]</td>";
echo "<td align='center'>";
echo $row_tr['datetimeentry'];
echo "</td>";
echo "</tr>";
}	
}
}
echo "</tbody>";
echo "</table>";
}
?>
</div>
</td>-->
</tr>
</table>
</div>
</body>
</html>

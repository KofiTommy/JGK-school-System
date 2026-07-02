<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_port=$_POST['port'];

if(isset($_POST['update_port'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblport SET port='$_port'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Port Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Port failed to update,$_Error</div>";
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
		
			<h3>Port Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="port.php">		
			<fieldset><legend>Port</legend>
			<input type="text" id="port" name="port" value="" class="validate[required]"/><br/><br/>
			</fieldset><br/>

			
			<div align="center"><button class="btn" id="update_port" name="update_port"><i class="fa fa-edit"></i> UPDATE</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblport");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>PORT SETTING</caption>";
				echo "<thead><th>PORT</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'>$row[port]</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				?>
			</td>
		</tr>

	</table>		
		
</div>

</body>
</html>
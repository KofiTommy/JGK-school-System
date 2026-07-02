<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];

if(isset($_POST["send_message"])){
$_SQL=mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby)
VALUES('$_MessageId','$_Message',NOW(),'active','$_SESSION[USERID]')");
if($_SQL){
$_SESSION['Message']="<div style='color:green;padding:10px;'>Message Successfully Submitted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to submit</div>";
}
}
?>

<?php

if(isset($_GET["delete_message"])){
$_SQL_D=mysqli_query($con,"DELETE FROM tblmessages WHERE messageid='$_GET[delete_message]'");
if($_SQL_D){
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message Successfully Deleted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to delete</div>";
}

}
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
	include("side-menu.php");

	?>
	</div>
<br/><br/><br/><br/>
<br/><br/><br/><br/>
<div class="main-platform" align="center" >
	<br/><br/>
	<table border="0" width="100%">
		<tr>
			<td width="25%" valign="top">
			<h4>DASHBOARD</h4>
			<?php
			include("welcome.php");
			?>

			<?php
			//include("menu-side.php");
			?>	

			</td>

			<td width="50%" valign="top" align="center">
				
				<h4 align="left">MESSAGES</h4>
				
				<?php
				echo $_SESSION['Message'];
				?>
<div class="form-entry" align="center">
	<?php
	include("dbstring.php");
	$_SQL_Msg=mysqli_query($con,"SELECT * FROM tblmessages WHERE sentby='$_SESSION[USERID]'");
	while($row=mysqli_fetch_array($_SQL_Msg,MYSQLI_ASSOC)){
		echo "<div style='padding:10px;border-bottom:1px solid #ddd;text-align:justify'>";
		echo $row['messages'];
		echo "<br/><strong style='color:darkblue;font-size:10px;;text-align:right'> $row[datetimeentry] </strong>";

		echo "<div style='color:red;text-align:right'><a href='user.php?delete_message=$row[messageid]'><i class='fa fa-times' style='color:red'></i></a></div>";
		echo "</div><br/><br/>";
	}


	?>
			
<h3>SEND MESSAGE 
</h3>
	
			<form method="post" id="formID" name="formID" action="user.php">

			<input type="hidden" id="userid" name="userid" value="<?php echo $_SESSION['USERID'];?>" class="validate[required]" readonly/>

			<label>Message</label><br/>
			<textarea id="message" name="message" style="background-color:white;"></textarea><br/><br/>
			
			<div align="right"><button class="button-pay" id="send_message" name="send_message"><i class="fa fa-send"></i> SEND</button></div>
		</form>

		</div>
	


 
			</td>

			<td width="25%" valign="top">
				<h4>TRANSACTION SUMMARY</h4>
				<?php
				
				include("transaction-summary.php");
				//include("salary-details.php");
				?>
			</td>
		</tr>
	</table>
</div>
</body>
</html>
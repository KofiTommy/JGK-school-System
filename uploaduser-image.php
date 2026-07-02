<?php 
session_start();
?>
<html>
<head>
<?php
include("links.php");
?>

<?php
@$_SESSION['Message']="";
?>

<style type="text/css">
th{
	border:1px solid gray;
	color:royalblue;
}
td{
	border:1px solid gray;
}
</style>
</head>

<body style="background-color:transparent">
	<!--Header-->
	<div class="header">

		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
<?php
include("menu.php");
?>

	</div>
<div class="main-platform" align="center" style="background-color:transparent;"><br/>
	<table width="100%">
		<caption>Upload Profile Photo</caption>
<tr>
<td valign="top" align="center" width="20%">

<?php 
echo "<div style='font-weight:bold;color:white'>$_SESSION[FULLNAME]</div>"; 
?>
</td>

<td valign="top" width="80%" >
	<h3 style="color:red">*Upload Passport Size Photo</h3>

<div>
				<form method="post" action="upload-image.php" enctype="multipart/form-data">
					<?php
					@$FileName="";
					include("dbstring.php");


					$sql ="SELECT * FROM tblsystemuser WHERE userid='$_SESSION[USERID]'";
					$result = mysqli_query($con,$sql);
					if($row=mysqli_fetch_array($result,MYSQLI_ASSOC)){
					$FileName = $row['filename'];

					if($FileName){
						echo "<img src='uploads/$FileName' width='200px' height='200px' style='border-radius:100%'/><br/><br/>";
						echo "<strong style='color:gray;font-size:14px;color:orange'>Uploaded Date/Time:$row[uploadeddatetime]</strong>";

					}
					else{
						echo "<img src='uploads/comm.gif' width='200px' height='200px' style='border-radius:100%'/><br/><br/>";
echo "<strong style='color:gray;font-size:12px'>Image Not Uploaded</strong>";
}
}
?>
<br/><br/>
<input type="hidden" id="userid" name="userid" value="<?php echo $_SESSION['USERID']?>" />
<div align="right">
<input type="file" id="file1" name="file1" value="" style="cursor:pointer" required/><br/><br/><button class="button-pay" id="submit_photo" name="submit_photo"><i class="fa fa-arrow-circle-up" style="color:white"></i> Upload Image</button>
</div>	
</td>
</tr>
</table>
</div>
</body>
</html>
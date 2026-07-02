<?php
session_start();
?>
<?php
$_SESSION['Message']="";
include("dbstring.php");

if(isset($_POST["authenticate"])){
@$_APIKey=md5($_POST["apikey"]);
//@$_APIKey=$_POST["apikey"];

$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblapi  WHERE apikey='$_APIKey'");
if(mysqli_num_rows($_SQL_EXECUTE)>0)
{	
$_SQL_U=mysqli_query($con,"UPDATE tblapi SET apikeyvalid='$_APIKey',status='inuse' WHERE apikey='$_APIKey'");
if($_SQL_U){
header("location:index.php");
}
else{
$_Error=mysqli_error($con);
$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;'>Failed to update.$_Error. Try again!!</div>";
	}
}
else
	{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;'>Failed to authenticate.$_Error. Try again!!</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<?php
include("validation/header.php");
?>
</head>

<body style="background-image:url('images/IMG-20191113-WA0000s.jpg');background-size:cover">

<div class="main-platform" align="center" style="">
		<!--Login-->
		<div class="form-entry" align="left">
			
			<form id="formID" name="formID" method="post" action="app_authentication.php">
				<h3 style="color:royalblue" align="left"> API ANTHENTICATION</h3>
				<?php
			echo @$_SESSION['Message'];
			//echo $_SESSION['USERID'];
			?>
			<label>API Key</label><br/>
			<input type="text" id="apikey" name="apikey" placeholder="Type API Key" class="validate[required]"/><br/>
			<br/>
			<br/>
<div align="center"><button class="button-save" id="authenticate" name="authenticate"><i class="fa fa-lock"></i> VALIDATE</button></div>
</form>
<br/>
    <p align="center" style="color:royalblue;font-family:tahoma;border-top:1px solid #dddddd;"><i class="fa fa-phone"></i> BTC: +233(0)342292121</p>
    <p align="center" style="font-size:10px;color:maroon;font-family:helvetica;">&copy 2020. XSCHOOL V2.20.1.2<br/> All Rights Reserved</p>

		</div>

</div>

</body>
</html>
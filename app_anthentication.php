<?php
session_start();
?>
<?php
$_SESSION['Message']="";
$_SESSION['USERID']="";
$_SESSION['USERNAME']="";
$_SESSION['CURRENCY']="";
$_SESSION['SYMBOL']="";
$_SESSION['ACCESSLEVEL']="";
$_SESSION['SYSTEMTYPE']="";
$_SESSION['BRANCHID']="";
?>

<?php
include("dbstring.php");
$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblcurrency");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
$_SESSION['CURRENCY']=$row_item_2['currencyname'];
$_SESSION['SYMBOL']=$row_item_2['symbol'];
}

if(isset($_POST["login"])){
$_Username=$_POST["username"];
$_Password=md5($_POST["password"]);

//$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblbranch br ON su.branchid=br.branchid  WHERE su.username='$_Username' AND su.password='$_Password'");

$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.username='$_Username' AND su.password='$_Password'");
	if(mysqli_num_rows($_SQL_EXECUTE)>0){
		if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
			@$_AccessLevel=$row['accesslevel'];
			@$_SystemType=$row['systemtype'];
			$_SESSION['USERID']=$row['userid'];
			$_SESSION['USERNAME']=$row['username'];
			@$_Userfullname=$row['firstname']." ".$row['othernames']." ".$row['surname'];
			$_SESSION['FULLNAME']=$_Userfullname;
			$_SESSION['ACCESSLEVEL']=$row['accesslevel'];
			$_SESSION['SYSTEMTYPE']=$row['systemtype'];
			$_SESSION['BRANCHID']=$row['branchid'];

			if($row['status']=="block"){
			$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;text-transform:blink'>Account is blocked!! Please contact administrator</div>";
			}else{
				if($_AccessLevel=="administrator" && $_SystemType=="super_user"){
					header("location:super.php");
				}
				elseif($_AccessLevel=="administrator" && $_SystemType=="normal_user"){
					header("location:admin.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Student"){
					header("location:student-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Teacher"){
					header("location:teacher-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="User"){
					header("location:user.php");
				}	
			}
		}
	}
	else
	{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;'>Failed to login.$_Error. Try again!!</div>";
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

<body style="background-image:url('images/IMG-20191113-WA0000.jpg');background-size:cover">
	<!--Header-->
	<div class="index-header">
	<?php 
	include("header.php");
	?>
	</div>

<div class="main-platform" align="center" style="">
		<!--Login-->
		<div class="form-entry" align="left">
			
			<form id="formID" name="formID" method="post" action="app_anthentication.php">
				<h3 style="color:royalblue" align="left"><img src="images/nexgen-logo.png" alt="NexGen" style="border:0; display:block; width:35%; max-width:220px; min-width:140px; height:auto; margin:0 0 10px;"> API ANTHENTICATION</h3>
				<?php
			echo @$_SESSION['Message'];
			//echo $_SESSION['USERID'];
			?>
			<label>API Key</label><br/>
			<input type="text" id="username" name="username" placeholder="Type API Key" class="validate[required]"/><br/>
			<br/>
			<br/>
<div align="center"><button class="button-save" id="login" name="login"><i class="fa fa-lock"></i> VALIDATE</button></div>
</form>
<br/>
    <p align="center" style="color:royalblue;font-family:tahoma;border-top:1px solid #dddddd;"><i class="fa fa-phone"></i> BTC: +233(0)342292121</p>
    <p align="center" style="font-size:10px;color:maroon;font-family:helvetica;">&copy 2020. XSCHOOL V2.20.1.2<br/> All Rights Reserved</p>

		</div>

</div>

</body>
</html>

<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
include("audit_notifications.php");
include_once("user-management-utils.php");
ensure_user_management_columns($con);
@$_Oldpassword=md5($_POST['oldpassword']);
@$_NewUsername=$_POST['username'];
@$_Newpassword=md5($_POST['newpassword']);
@$_ForceReset=(isset($_GET['force']) && $_GET['force']=="1");

if(isset($_POST['update_account'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser 
	SET username='$_NewUsername',password='$_Newpassword',password_reset_required=0,password_last_reset_at=NOW() 
	WHERE userid='$_SESSION[USERID]' AND password='$_Oldpassword'");
if($_SQL_EXECUTE){
    if (mysqli_affected_rows($con) > 0) {
        logSystemChange(
            $con,
            "PASSWORD_CHANGE",
            "Password was changed by ".$_SESSION['SYSTEMTYPE']." user."
        );
    }
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Account Successfully Updated<br/><br/><a href='index.php' style='color:blue'>Login</a><br/><br/></div>";

	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Account failed to update,$_Error</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/change-password.css">

</head>

<body>

	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>

<div class="main-platform password-page">
	<section class="password-hero">
		<div>
			<span class="password-kicker">Account Security</span>
			<h1>Change Password</h1>
			<p>Update your login details to keep your account secure.</p>
		</div>
		<div class="password-hero-card">
			<i class="fa fa-lock"></i>
			<span>Secure Account</span>
		</div>
	</section>

	<div class="password-shell">
		<section class="password-panel">
			<div class="password-panel-heading">
				<span class="password-icon"><i class="fa fa-shield"></i></span>
				<div>
					<h2>Update Account</h2>
					<p>Enter your current password, then choose a new username and password.</p>
				</div>
			</div>
	<?php
	echo $_SESSION['Message'];
	?>
<?php
if($_ForceReset){
    echo "<div class='password-alert'><i class='fa fa-exclamation-triangle'></i> Your password was reset by an administrator. Please choose a new username and password before continuing.</div>";
}
?>
	
			<form method="post" id="formID" name="formID" action="change-password.php">

			<label>User Id</label>
			<input type="text" id="userid" name="userid" value="<?php echo $_SESSION['USERID'];?>" class="validate[required]" readonly/>

			<label>Old Password</label>
			<input type="password" id="oldpassword" name="oldpassword" value="" class="validate[required]" placeholder="Type Old Password"/>

			<label>New Username</label>
			<input type="text" id="username" name="username" value="" placeholder="Type New Username" />

			<label>New Password</label>
			<input type="password" id="newpassword" name="newpassword" value="" class="validate[required]" placeholder="Type New Password"/>

			<label>Repeat Password</label>
			<input type="password" id="repeatpassword" name="repeatpassword" value="" class="validate[required,equals[newpassword]]" placeholder="Repeat Password"/><br/><br/>
			<div class="password-actions"><button class="button-edit password-btn password-btn-primary" id="update_account" name="update_account"><i class="fa fa-edit"></i> Update Account</button></div>
		</form>
		</section>
	</div>
</div>
</body>
</html>

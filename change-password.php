<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
include("audit_notifications.php");
include_once("user-management-utils.php");
ensure_user_management_columns($con);
@$_ForceReset=(isset($_GET['force']) && $_GET['force']=="1");
$_CurrentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$_CurrentUserRow = $_CurrentUserId !== "" ? um_fetch_user_row($con, $_CurrentUserId) : null;
$_CurrentUsername = $_CurrentUserRow ? trim((string)$_CurrentUserRow['username']) : "";

if(!function_exists('cp_safe')){
function cp_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(isset($_POST['update_account'])){
    $_OldPasswordRaw = isset($_POST['oldpassword']) ? (string)$_POST['oldpassword'] : "";
    $_NewPasswordRaw = isset($_POST['newpassword']) ? (string)$_POST['newpassword'] : "";
    $_NewUsername = isset($_POST['username']) ? um_normalize_username($_POST['username']) : "";
    if($_NewUsername === ""){
        $_NewUsername = $_CurrentUsername;
    }

    if($_CurrentUserId === "" || !$_CurrentUserRow){
        $_SESSION['Message']="<div style='color:red'>Your account could not be loaded. Please log in again.</div>";
    }elseif($_NewUsername === ""){
        $_SESSION['Message']="<div style='color:red'>Username is required.</div>";
    }elseif(strlen($_NewPasswordRaw) < 6){
        $_SESSION['Message']="<div style='color:red'>New password must be at least 6 characters.</div>";
    }elseif(um_is_username_taken($con, $_NewUsername, $_CurrentUserId)){
        $_SESSION['Message']="<div style='color:red'>That username is already in use by another account.</div>";
    }else{
        $_Oldpassword = md5($_OldPasswordRaw);
        $_Newpassword = md5($_NewPasswordRaw);
        $_SQL_EXECUTE = false;
        $_AffectedRows = 0;

        $stmtUpdate = mysqli_prepare($con, "UPDATE tblsystemuser
            SET username=?, password=?, password_reset_required=0, password_last_reset_at=NOW()
            WHERE userid=? AND password=?
            LIMIT 1");
        if($stmtUpdate){
            mysqli_stmt_bind_param($stmtUpdate, "ssss", $_NewUsername, $_Newpassword, $_CurrentUserId, $_Oldpassword);
            $_SQL_EXECUTE = mysqli_stmt_execute($stmtUpdate);
            $_AffectedRows = mysqli_stmt_affected_rows($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);
        }

        if($_SQL_EXECUTE && $_AffectedRows > 0){
            $_SESSION['USERNAME'] = $_NewUsername;
            logSystemChange(
                $con,
                "PASSWORD_CHANGE",
                "Password was changed by ".$_SESSION['SYSTEMTYPE']." user."
            );
            $_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Account Successfully Updated<br/><br/><a href='index.php' style='color:blue'>Login</a><br/><br/></div>";
        }elseif($_SQL_EXECUTE){
            $_SESSION['Message']="<div style='color:red'>Account failed to update. Please confirm your old password and try again.</div>";
        }else{
            $_Error=mysqli_error($con);
            $_SESSION['Message']="<div style='color:red'>Account failed to update,$_Error</div>";
        }
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
	
			<form method="post" id="formID" name="formID" action="<?php echo $_ForceReset ? 'change-password.php?force=1' : 'change-password.php'; ?>">

			<label>User Id</label>
			<input type="text" id="userid" name="userid" value="<?php echo $_SESSION['USERID'];?>" class="validate[required]" readonly/>

			<label>Old Password</label>
			<input type="password" id="oldpassword" name="oldpassword" value="" class="validate[required]" placeholder="Type Old Password"/>

			<label>New Username</label>
			<input type="text" id="username" name="username" value="<?php echo cp_safe($_CurrentUsername); ?>" placeholder="Type New Username" autocomplete="username" />

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

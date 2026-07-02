<?php
session_start();
$_SESSION['Message']="";
include("dbstring.php");
@$_Oldpassword=md5($_POST['oldpassword']);
@$_NewUsername=$_POST['username'];
@$_Newpassword=md5($_POST['newpassword']);

if(isset($_POST['update_account'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser 
  SET username='$_NewUsername',password='$_Newpassword',firsttimeuser=0 
  WHERE userid='$_SESSION[USERID]' AND password='$_Oldpassword'");
if($_SQL_EXECUTE){
  $_SESSION['Message']="<div style='color:green;text-align:left;background-color:#efe;padding:15px;'>Account Successfully Updated</div>";
  header("refresh:2;url='login.php'");
  }
  else{
    $_Error=mysqli_error($con);
    $_SESSION['Message']="<div style='background-color:#fee;padding:15px;color:red'>Account failed to update,$_Error</div>";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
<?php
include("validation/header.php");
?>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../favicon.ico">

    <title>WEBSAS&reg || Change Password</title>
    <!-- Bootstrap core CSS -->
    <link href="bootstrap-3.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="css/dashboard.css" rel="stylesheet">
    <script src="bootstrap-3.3.5/docs/assets/js/ie-emulation-modes-warning.js"></script>
    <link rel="stylesheet" type="text/css" href="css/font-awesome-4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

</head>
<body>
<div class="container">
<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
<?php
  echo $_SESSION['Message'];
  ?>
<h4 style="color:maroon">*Welcome, <?php echo $_SESSION['FULLNAME'];?>, you have to change your account before accessing the system! </h4>
<br/>
      <form method="post" id="formID" name="formID" action="firsttimeuser.php">
      <input type="hidden" id="userid" name="userid" value="<?php echo $_SESSION['USERID'];?>" class="validate[required] form-control" readonly/>
      <label>Old Password</label>
      <input type="password" id="oldpassword" name="oldpassword" value="" class="validate[required] form-control" placeholder="Type Old Password"/>
      <label>New Username</label><br/>
      <input type="text" id="username" name="username" value="" class="validate[required] form-control" placeholder="Type New Username" />
      <label>New Password</label><br/>
      <input type="password" id="newpassword" name="newpassword" value="" class="validate[required,minSize[6]] form-control" placeholder="Type New Password"/>
      <label>Repeat Password</label><br/>
      <input type="password" id="repeatpassword" name="repeatpassword" value="" class="validate[required,equals[newpassword]] form-control" placeholder="Repeat Password"/><br/>
      <div align="center"><button class="btn btn-primary" id="update_account" name="update_account"><i class="fa fa-edit"></i> UPDATE ACCOUNT</button></div>
    </form>
</div>
</div>
</html>

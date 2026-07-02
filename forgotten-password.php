<!--https://phptopdf.com -->

<?php session_start();?>
<?php include("dbstring.php"); ?>


<?php
include("validation/header.php"); 
?>
<html>
<head>
   <?php
      include("links.php");
      ?>
</head>
<body>
  <div class="header">
  <?php
  include("header.php");
  ?>
    <div align="right" style="margin-top:-40px;">
        <a href="index.php"><i class="fa fa-home"></i> Home</a>
    </div>
  </div>
<div class="main" align="center" style="">
<table  width="100%" height="0%">
<tr>
<td valign="top" align="center" width="50%" style="background-color:#fff;">

</td>        

<td valign="top" align="center" width="50%" style="background-color:white;">
<br/>

<div class="form-entry" align="left">
<form id="formID"  method="post" action="account-recovery.php"  enctype="multipart/form-data">
 <h2 align="left" style="color:darkblue">Account Recovery</h2>
   <hr>
<p align='justify' style="color:olive"><b>Note:</b><br/>Provide your E-mail Address</p>
 <div id="error_msg"> </div>    
<label>E-mail</label><br/>
<input type="text" id="email" name="email" value="" class="validate[required]" /><br/><br/>
<div align="right">
<div align="center"><button class="button-pay" id="submit_recovery" name="submit_recovery"><i class="fa fa-lock"></i> Recover Account</button></div>
</div>
</div> <!--End of pos-inner-board-->
</form>
</td>
</td>
</tr>
</table>
</div> <!--End of pos-main-board -->
</body>
</html>
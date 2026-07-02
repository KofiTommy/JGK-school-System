<?php session_start();?>
<?php include("dbstring.php"); ?>


<?php
//Declare the variables
@$email =trim($_POST['email']);
//Connection to database
include('dbstring.php');

//Check connection
if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL:" .mysqli_connect_error();
}
//Set variables for security
/*if(isset($_POST['email']))
{
$email = $_POST['email'];
}*/
if(isset($_POST['submit_recovery']))
{
  @$Verification_Code = mt_rand(100,90000);



  $sql ="SELECT * FROM tblsystemuser WHERE email='$email'";

  $results = mysqli_query($con,$sql);
  $counts = mysqli_num_rows($results);

  if(!mysqli_query($con,$sql) )
  {
  die('Error:' .mysqli_error($con));
  }
  else
  {
    if($counts>0)
    {
        $sqlU ="UPDATE tblsystemuser SET verificationcode='$Verification_Code' WHERE email='$email'";

          if(!mysqli_query($con,$sqlU))
          {
            die('Error:' .mysqli_error($con));
          }
          else
          {
            @$_SESSION['recovery-email'] = $email;
          }

    }
    else
    {
      header("location:login.php");
    }
   // @$_SESSION['recovery-email'] = $email;
   // header("location:account-recovery.php");
  }
}
mysqli_close($con);
?>


<?php
include('dbstring.php');
//Declare the variables
@$verificationcode =$_POST['verification_code'];

@$UserName =$_POST['username'];
@$NewPassword =md5($_POST['new-password']);
//@$RepeatPassword =md5($_POST['repeat-password']);


@$errMessage="";


//insert category record
if(isset($_POST["submit_verification_code"]))
{
//echo $verificationcode;
    //Check connection
    if(mysqli_connect_errno())
    {
    echo "Failed to connect to MySQL:" .mysqli_connect_error();
    }
        $sql2 ="UPDATE tblsystemuser SET username='$UserName',password='$NewPassword' WHERE verificationcode='$verificationcode'";

        if(!mysqli_query($con,$sql2))
        {
        die('Error:' .mysqli_error($con));
        }
        else
        {

        $errMessage = "<div align='center' class='errorMsg'  style='background-color:#afa;color:black;padding:5px;border:1px solid green;'> Account Successfully Changed   </div><br/>";
        mysqli_close($con);
        }    
}
?>




<?php
include("validation/header.php"); 
?>


<html>
<head>
<?php

//include("title.php");
?>

   <?php
      include("links.php");
      ?>
</head>

<body>
<div class="header"> <!--Start of pos-main-board -->
       <?php
      include("header.php");
      ?>
    
    <div align="right" style="margin-top:-40px;">
    <a href="live-chat.php"> <button class="btn-menu"><i class="fa fa-comment"></i> Live Chat</button></a>
    <a href="contact-us.php"> <button class="btn-menu"><i class="fa fa-phone"></i> Contact Us</button></a>
        <a href="about-us.php"> <button class="btn-menu"><i class="fa fa-phone"></i> About Us</button></a>
    <a href="index.php"><button class="btn-menu"><i class="fa fa-home"></i> Home</button></a>
    </div>
  </div>

<br/><br/><br/><br/>
<div class="main" align="center" style="">
<br/>
       <table  width="100%" height="0%">
      <tr>
         <td valign="top" align="center" width="25%" style="background-color:whitesmoke">

          <br/>
    <div id="login" align="left">
   <h2 align="left" style="color:darkblue">Account Update</h2>
   <hr>
 
        <div id="error_msg"> </div>    

          <label>Your E-mail Address</label><br/><br/>
         <?php  
         echo "<div style='border-bottom:1px solid lightblue;font-size:28px;'>". $_SESSION['recovery-email'] ."</div><br/><br/>";
         ?>

<?php
//Declare the variables
@$email = $_SESSION['recovery-email'];

//Connection to database
include('dbstring.php');

//Check connection
if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL:" .mysqli_connect_error();
}

  $sql ="SELECT * FROM tblsystemuser WHERE email='$email'";
  $result = mysqli_query($con,$sql);

   $count = mysqli_num_rows($result);

   if($count <1)
   {
    header("location:login.php");
   }
   else
   {

    if($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
      {

        echo "<label>Your Name: </label><br/><br/>";
         echo "<div style='border-bottom:1px solid lightblue;font-size:28px;'>".  $row['firstname']." ". $row['othernames']." ". $row['surname'] ."</div><br/><br/>";

          echo "<label>Your Address: </label><br/><br/>";
         echo "<div style='border-bottom:1px solid lightblue;font-size:28px;'>".  $row['postaladdress'] ."</div><br/><br/>";
      

        echo "<label>Mobile Phone: </label><br/><br/>";
         echo "<div style='border-bottom:1px solid lightblue;font-size:28px;'>*******".  substr($row['mobile'] , 6)."</div><br/><br/>";
      
        
      }  
   }
  


mysqli_close($con);
?>
      
  </div>

       </td>

<td width="50%" valign="top" align="center">
    <div class="form-entry" align="left">
         <form id="formID"  method="post" action="account-recovery.php"  enctype="multipart/form-data">
      <?php
        echo  $errMessage;
      ?>
      
   <h2 align="left" style="color:darkblue">Pasword Reset</h2>
   <hr>
    <p align='justify' style="color:maroon"><b>Alert:</b><br/>Please, enter the Verification Code sent to your mobile phone</p>
 
            <div id="error_msg"> </div>    

          <label>Verification Code:</label><br/>
          <input type="text" id="verification_code" name="verification_code"  value="" class="validate[required]" /><br/><br/>

          <label>New User Name:</label><br/>
          <input type="text" id="username" name="username"  value="" class="validate[required]" /><br/><br/>


          <label>New Passowrd:</label><br/>
          <input type="password" id="new-password" name="new-password" value="" class="validate[required,minSize[6]]" /><br/><br/>

          <label>Repeat Password:</label><br/>
          <input type="password" id="repeat-password" name="repeat-password"  value="" class="validate[required,equals[new-password]]" /><br/><br/>
          
 
          <div align="right">

            <button class="button-pay" id="submit_verification_code" name="submit_verification_code"><i class="fa fa-lock" style="color:white"></i> Reset Password </button>
          </div>
   </form>
  </div>

</td>
</tr>
</table>

   </div> <!--End of pos-inner-board-->

   <?php
  include("footer.php");
  ?>
</body>
</html>
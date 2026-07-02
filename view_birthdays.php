<?php session_start();?>
<?php include("dbstring.php"); 
$_SESSION['Message']="";
?>
<?php

@$_UserID=$_POST["userid"];
@$_BirthdayMessage="Happy Birthday! May God Bless You";

if(isset($_POST["sendmessage"]))
{
  //Send SMS
  @$_SMS_Alert="Not Sent";
  foreach ($_UserID as $_Selected_UserID)
  {
 //Generate different birthd ids for all users available at the moment
 include("code.php");
 @$_BirthdayID=$code;

    $_SQLB=mysqli_query($con,"INSERT INTO tblbirthday(birthdayid,userid,message,messagedatetime,status,action)values('$_BirthdayID','$_Selected_UserID','$_BirthdayMessage',NOW(),'active','$_SMS_Alert')");
       if($_SQLB){
        //Update action tblsystemuser that has the main records of users
        $_SQL_UB=mysqli_query($con,"UPDATE tblsystemuser SET action='sent' WHERE userid='$_Selected_UserID'");
        if($_SQL_UB){}
       }
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
<meta name="viewport" content="width=device-width, initial-scale=1">

<script>
  var rnd;
function getStaffId()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("staff-id").value=rnd;
}
</script>

<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("allusers").checked;

  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 

 function uncheckBox(){
  document.getElementById("sendmessage").style.display="none";
   var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }

  function checkBox(){
    document.getElementById("sendmessage").style.display="block";
var inputs = document.getElementsByName("userid[]");
   
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
</script>
<script type="text/javascript">
function initialize(){
  document.getElementById("sendmessage").style.display="none";
}
</script>


</head>
<body onload="initialize()">

<div class="pos-main-board"> <!--Start of pos-main-board -->
   <div class="pos-inner-board"><!--Start of pos-inner-board -->
   
  <?php
  include("check-login.php");
?>
<br/>

 <div class="users-login">
<?php
  include("loginlogout.php");
?>
<br/>

<table> 
<tr>
<td>
  <?php
 // echo $_SESSION['Message'];
  ?>

<div class="form-entry">
  <?php
         //Connection to database
         include'dbstring.php';
     echo "<form method='post' action='view_birthdays.php'>";
         $_SQL=mysqli_query($con,"SELECT * FROM vw_birthday WHERE branchid='$_SESSION[BRANCHID]'");
         echo "<table border='1' width='100%'>";
         echo "<caption>ALL BIRTHDAYS</caption>";
          echo "<thead>";
          echo "<th> * </th><th >USER ID</th><th>FULL NAME </th><th>BIRTH DATE </th><th>MOBILE</th><th colspan='1'><input type='checkbox' id='allusers' name='allusers' onclick='selectAll()'/></th>";
          echo "</thead>";
          $serial=0;
         while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
         
          echo "<tr>";
          echo "<td>";
          echo $serial=$serial+1;
          echo "</td>";

          echo "<td align='center'>";
          echo $row["userid"];
          echo "</td>";
          echo "<td align='center'>";
          echo $row["firstname"]." ".$row["surname"]." ".$row["othernames"];
          echo "</td>";


          echo "<td align='center'>";
          echo $row["birthday"];
          echo "</td>";


          echo "<td align='center'>";
          echo $row["mobile"];
          echo "</td>";


          echo "<td align='center'>";
          echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
          echo "</td>";

          echo "</tr>";
         
         }
         mysqli_close($con);       
?>
      </div>
          </td>
      </tr>
      <tr><td colspan="6">
      <button class='button' id='sendmessage' name='sendmessage'><i class='fa fa-send'></i> Send Message</button>
    </td>
  </tr>

   </table>
    </form>
   </div> <!--End of pos-inner-board-->

</div> <!--End of pos-main-board -->

</body>
</html>
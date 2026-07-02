<?php session_start();?>
<?php include("dbstring.php");
$_SESSION["Message"]="";
 ?>

<?php
//Declare the variables
@$_CompanyId =$_POST['companyid'];
@$_SMSAlert =$_POST['smsalert'];
@$_Mobile =$_POST['mobile'];

if(isset($_POST["button_save"]))
{
    //Check connection
    if(mysqli_connect_errno())
    {
    echo "Failed to connect to MySQL:" .mysqli_connect_error();
    }
include("code.php");      
$sql2 ="INSERT INTO tblsmsalert(smsenabled,mobile,companyid,datetimeentry,status,recordedby)
VALUES(1,'$_Mobile','$_CompanyId',NOW(),'active','$_SESSION[FULLNAME]')";

              if(!mysqli_query($con,$sql2))
              {
              die('Error:' .mysqli_error($con));
              }
              else
              {
                header("location:smsalert.php");
              }
        mysqli_close($con); 
}
?>

<?php
//Declare the variables
@$_Mobile=$_GET['delete_mobile_id'];

//Connection to database
include('dbstring.php');
if(isset($_GET['delete_mobile_id'])){

//Check connection
if(mysqli_connect_errno())
{
echo "Failed to connect to MySQL:" .mysqli_connect_error();
}
$sql =mysqli_query($con,"DELETE FROM tblsmsalert WHERE mobile='$_Mobile'");
if($sql)
{
$_SESSION["Message"]="<div style='color:red;padding:10px'>Mobile Successfully Deleted</div>";
}
else{
  $_Error=mysqli_error($con);
  $_SESSION["Message"]="<div style='color:red;padding:10px'>Mobile failed to delete, Error:$_Error</div>";
}
mysqli_close($con);
}
?>

<?php
include("validation/header.php"); 
?>

<html>
<head>
 <?php
    //  include("title.php");
      ?>
   <?php
      include("links.php");
      ?>


<script>
  var rnd;
function getStaffId()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("staff-id").value=rnd;
}
</script>


<script type="text/javascript">
function getInitials(){
  var ini=document.getElementById("location").value;
  document.getElementById("initials").value= ini.substring(0,2).toUpperCase()+"_";

}
</script>
</head>

<body >

  <div class="header">
    <!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
  <?php
  include("menu.php");

  ?>    
  <?php
  include("side-menu.php");

  ?>
  </div>

<br/><br/><br/><br/><br/><br/><br/>


       <table> 
      <tr>
         <td valign="top">
          <div id="message" name="message" value="" ></div>
<?php
if(isset($_GET['edit_subcompany_id']))
{
  $_SQL=mysqli_query($con,"SELECT * FROM tblcompany WHERE companyid='$_GET[edit_subcompany_id]'");
  if($row_su=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
?> 
  <div id="stock-item-style">
      <h4> Sub-Company Data Update</h4>       
<form id="formID"  method="post"  enctype="multipart/form-data">
<br/>
<label>Sub Company Id</label><br/>
<input type="text" id="subcompanyid" name="subcompanyid" value="<?php echo $_GET['edit_subcompany_id']?>" readonly/>
<br/>
<label>Sub Company</label><br/>
<input type="text" id="subcompany" name="subcompany" value="<?php echo $row_su['subcompany'];?>" maxlength="100" class="validate[custom[onlyLetterSp]]"  /><br/>

<div id="save">
<button id="button_update" name="button_update" onclick="getStaffId()"><i class="fa fa-save" style="color:white"></i> Update</button>
 </div>  
</div>
</form>
<?php
}
}
?>
   
<?php
if(isset($_GET['sms_company_id']))
{
?> 
  <div id="stock-item-style">
      <h4> SMS ALERT Data Entry</h4>       
<form id="formID"  method="post"  enctype="multipart/form-data">
<br/>
<label>Company Id</label><br/>
<input type="text" id="companyid" name="companyid" value="<?php echo $_GET['sms_company_id']?>" readonly/>
<br/>
<fieldset><legend>SMS ALERT</legend>

<label>Mobile</label><br/>
<input type="text" id="mobile" name="mobile" value="" class="validate[required,custom[phone]]" maxlength="10"/>
<br/>


<input type="radio" id="enablesmsalert" name="smsalert" value="enabled" />
<label>Activate</label><br/>

<input type="radio" id="disablesmsalert" name="smsalert" value="disabled" />
<label>Deactivate</label><br/>
</fieldset>
<br/>


<div id="save">
<button class="btn" id="button_save" name="button_save" onclick="getStaffId()"><i class="fa fa-save" style="color:white"></i> Save</button>
 </div>  

</div>
</form>
<?php
}
?>
</td>
 <td>
  <?php
  echo $_SESSION["Message"];
  ?>
<div id="account-display-styless">

             <?php
         //Connection to database
         include'dbstring.php';
         //Check connection
         if(mysqli_connect_errno()){
         echo "Failed to connect to MySQL:" .mysqli_connect_error();
         }

       
         $sql1="SELECT * FROM tblcompany ";
         $result=mysqli_query($con,$sql1);

         $count = mysqli_num_rows( $result);
         if($count>0)
         {
         echo "<div align='center' style='text-transform:uppercase;'> Company Information</div>";
         }
          echo "<table border='1' width='100%'>";
          echo "<thead>";
          echo "<th colspan='4'>Task </th><th>*</th><th>Company </th><th>Date/Time</th><th>Status </th>";
          echo "</thead>";
          echo "<tbody>";
          @$serial1=0;
         while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
         {
         // echo "<form method='post' action='delete-category.php'>";
            echo "<tr >";
            echo "<td style='min-width:5%;cursor:pointer' align='center'>";  
            echo "<a onClick=\"javascript: return confirm('Please confirm deletion');\" href='companysettings.php?company_id=$row[companyid]' title='Delete ".$row['fullname']."'><i style='color:red' class='fa fa-times'></i></a>";          
            echo "</td>";
            echo "<td align='center'>";
            echo "<a href='companysettings.php?view_company_id=$row[companyid]' title='View ".$row['fullname']."'><i style='color:olive' class='fa fa-edit'></i></a>";          

            echo "</td>";

            echo "<td align='center'>";
            echo "<a href='smsalert.php?sub_company_id=$row[companyid]' title='Create Sub Company of ".$row['fullname']."'><i style='color:darkgreen' class='fa fa-plus'></i></a>";          
            echo "</td>";

            echo "<td align='center'>";
            echo "<a href='smsalert.php?sms_company_id=$row[companyid]' title='Subscribe to SMS Alert'><i style='color:blue' class='fa fa-inbox'></i></a>";          

            echo "</td>";

            

             echo "<td align='center'>";
            echo $serial1=$serial1+1;
            echo "</td>";

            echo "<td>";
            echo $row['fullname']. "(".$row['companyid'].")";
             //echo "<input type='hidden' id='delete-staff' name='delete-staff' value='".$row['StaffID']."'";
             echo "</td>";


            echo "<td align='center'>";
            echo $row['datetimeentry'];
            echo "</td>";


            echo "<td align='center'>";
            echo $row['status'];
            echo "</td>";

            //echo "<td style='min-width:5%;cursor:pointer' align='center'>";          
           // echo "<a href='edit-staff.php?staff_ids=$row[StaffID]' title='Edit ".$row['fullname']."'><i style='color:blue' class='fa fa-edit'></i></a>";          
            //echo "</td>";
           
           // echo "<button style='border:0px solid white;cursor:pointer'><img src='images/icons/remove.png' alt='Remove' width='30px' height='30px'></button>";
            echo "</tr>";
            echo "<tr>";
            echo "<td colspan='8'>";

            $_SQL=mysqli_query($con,"SELECT * FROM tblsmsalert WHERE companyid='$row[companyid]'");
            echo "<table>";
            echo "<thead><th colspan='2'>Task</th><th>*</th><th>Mobile</th><th>Enabled</th><th>Date/Time</th><th>Status</th></thead>";
            echo "<tbody>";
            @$serial=0;
            while($row_c=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
            echo "<tr>";
            echo "<td style='min-width:5%;cursor:pointer' align='center'>";  
            echo "<a onClick=\"javascript: return confirm('Please confirm deletion');\" href='smsalert.php?delete_mobile_id=$row_c[mobile]' title='Delete ".$row_c['mobile']."'><i style='color:red' class='fa fa-times'></i></a>";          
            echo "</td>";
            echo "<td align='center'>";
            echo "<a href='smsalert.php?edit_smsalert_id=$row_c[mobile]' title='Edit ".$row_c['mobile']."'><i style='color:olive' class='fa fa-edit'></i></a>";          
            echo "</td>";

            echo "<td>";
            echo $serial=$serial+1;
            echo "</td>";
            echo "<td align='center'>$row_c[mobile]</td>";
            echo "<td align='center'>$row_c[smsenabled]</td>";
            echo "<td align='center'>$row_c[datetimeentry]</td>";
            echo "<td align='center'>$row_c[status]</td>";
            echo "</tr>";             
            }
            echo "</tbody>";
            echo "</table>";

            echo "</td>";
            echo "</tr>";
         }
         echo "</tbody>";
          echo "</table>";
         mysqli_close($con);
?>
      </div>
          </td>
      </tr>
   </table>

   </div> <!--End of pos-inner-board-->
</div> <!--End of pos-main-board -->

</body>
</html>
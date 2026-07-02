<?php
session_start();
$_SESSION['Message']="";

include("dbstring.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=$_POST['password'];
@$_AccessLevel="user";
@$_SystemType=$_POST['systemtype'];
@$_Filename=$_POST['filename'];
//,birthday=STR_TO_DATE('$_Birthday'
if(isset($_POST['update_user'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET firstname='$_Firstname',
	surname='$_Surname',othernames='$_Othernames',gender='$_Gender',age='$_Age',postaladdress='$_PostalAddress',homeaddress='$_HomeAddress',hometown='$_HomeTown',
	religion='$_Religion',relationship='$_Relationship',nextofkin_fullname='$_Nextofkin_fullname',
	nextofkin_contact='$_Nextofcontact' WHERE userid='$_SESSION[USERID]'");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>User Information Successfully Updated</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>User Information Failed to update,Error:$_Error</div>";
}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/edit-account.css">
</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	<?php
	//include("side-menu.php");
	?>
	</div>
<div class="main-platform account-page">
<section class="account-hero">
	<div>
		<span class="account-kicker">My Account</span>
		<h1>Edit Account</h1>
		<p>Update your personal profile, contact details, and next-of-kin information.</p>
	</div>
	<div class="account-hero-card">
		<i class="fa fa-user-circle"></i>
		<span>Profile Settings</span>
	</div>
</section>

<div class="account-shell">
<section class="account-panel">
	<div class="account-panel-heading">
		<span class="account-icon"><i class="fa fa-id-card-o"></i></span>
		<div>
			<h2>Update Profile</h2>
			<p>Keep your account information accurate and current.</p>
		</div>
	</div>
	<?php
	echo $_SESSION['Message'];
	?>
<form method="post" id="formID" name="formID" action="edit-account.php">
	<?php
	$_SQL_EXECUTE_A=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_SESSION[USERID]'");
	if($row_a=mysqli_fetch_array($_SQL_EXECUTE_A,MYSQLI_ASSOC)){
	?>
			<label>User Id</label>
			<input type="text" id="userid" name="userid" value="<?php echo $row_a['userid'];?>" class="validate[required]" readonly/>

			<fieldset class="account-fieldset"><legend>Bio Data</legend>
			<div class="account-grid">
			<div>
			<label>First Name</label>
			<input type="text" id="firstname" name="firstname" value="<?php echo $row_a['firstname'];?>" class="validate[required]"/>
			</div>

			<div>
			<label>Surname</label>
			<input type="text" id="surname" name="surname" value="<?php echo $row_a['surname'];?>" />
			</div>

			<div class="account-span-2">
			<label>Othernames</label>
			<input type="text" id="othernames" name="othernames" value="<?php echo $row_a['othernames'];?>"/>
			</div>
			</div>
			</fieldset>

			<fieldset class="account-fieldset"><legend>Gender</legend>
			<div class="account-radio-row">
			<?php
			if($row_a['gender']=="male")
			{
			?>
			<input type="radio" id="gender" name="gender" value="male" checked>
			<label>Male</label>
			<input type="radio" id="gender" name="gender" value="female">
			<label>Female</label>
			<?php
			}else{?>
			<input type="radio" id="gender" name="gender" value="male">
			<label>Male</label>
			
			<input type="radio" id="gender" name="gender" value="female" checked>
			<label>Female</label>
			<?php
			}?>	

			</div>
			</fieldset>

			<div class="account-grid">
			<div>
			<label>Birthday</label>

			<script type="text/javascript">
            function show_alert()
            {
               alert("Please select Date Time Picker");
            }
            </script>
            <script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('birthday','ddMMyyyy','','','','','')" id="birthday" name="birthday" value="<?php echo $row_a['birthday'];?>" class="validate[required]" readonly   onchange="CheckDateOfBirth()"/>
      </div>

	<div>
	<label>Age</label>
	<input type="text" id="age" name="age" value="<?php echo $row_a['age'];?>" readonly/>
	</div>
	</div>
	

	<fieldset class="account-fieldset"><legend>Contact And Background</legend>
	<div class="account-grid">
	<div class="account-span-2">
	<label>Postal Address</label>
			<textarea id="postaladdress" name="postaladdress" ><?php echo $row_a['postaladdress'];?></textarea>
			</div>
			<div class="account-span-2">
			<label>Home Address</label>
			<textarea id="homeaddress" name="homeaddress" ><?php echo $row_a['homeaddress'];?></textarea>
			</div>
			<div>
			<label>Religion</label>
			<select id="religion" name="religion">
				<option value="<?php echo $row_a['religion'];?>"><?php echo $row_a['religion'];?></option>
				<option value="Christian">Christian</option>
				<option value="Muslim">Muslim</option>
				<option value="Tradition">Tradition</option>
				<option value="Others">Others</option>
			</select>
			</div>

			<div>
			<label>Relationship</label>
			<select id="relationship" name="relationship">
				<option value="<?php echo $row_a['relationship'];?>"><?php echo $row_a['relationship'];?></option>
				<option value="Father">Father</option>
				<option value="Mother">Mother</option>
				<option value="Uncle">Uncle</option>
				<option value="Brother">Brother</option>
				<option value="Sister">Sister</option>
				<option value="Daughter">Daughter</option>
				<option value="Others">Others</option>
			</select>
			</div>
			</div>
			</fieldset>

			<fieldset class="account-fieldset"><legend>Next Of Kin</legend>
			<div class="account-grid">
			<div>
			<label>Full Name</label>
			<input type="text" id="nextoffullname" name="nextoffullname" value="<?php echo $row_a['nextofkin_fullname'];?>"/>
			</div>
			<div>
			<label>Contact</label>
			<input type="text" id="nextofkincontact" name="nextofkincontact" value="<?php echo $row_a['nextofkin_contact'];?>" class="validate[required,custom[onlyNumberSp]]" maxlength="10">
			</div>
			</div>

			</fieldset>
			<div class="account-actions"><button class="button-edit account-btn account-btn-primary" id="update_user" name="update_user"><i class="fa fa-edit"></i> Update Account</button></div>
		</form>

	<?php
	}?>
</section>
</div>

<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
</div>
</body>
</html>

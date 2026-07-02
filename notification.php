<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];
$_UserId=(isset($_POST['userid']) && is_array($_POST['userid'])) ? $_POST['userid'] : array();
$_SelectedRecipient=isset($_POST["recipient"]) ? trim($_POST["recipient"]) : "";
$_SelectedBatchId=isset($_POST["batchid"]) ? trim($_POST["batchid"]) : "";

if(isset($_POST['send_message'])){
		if(empty($_UserId))
		{
		$_SESSION['Message']="<div style='color:red'>No user selected</div>";
		}
		else{
			foreach($_UserId as $selecteduser)
			{	
				$_Mobile="";
				$_SelectedUserSafe=mysqli_real_escape_string($con,$selecteduser);
				//Get mobile number from users	
				$_SQL_H=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_SelectedUserSafe'");
				if($rowm=mysqli_fetch_array($_SQL_H,MYSQLI_ASSOC)){
				$_Mobile=$rowm["mobile"];
				}
				if($_Mobile!=""){
					$message=$_Message;
					$phone=$_Mobile;
					include("bulksms/bulksms.php");
				}
			}
	   }
}
?>

<?php
if(isset($_GET["delete_message"])){
$_SQL_D=mysqli_query($con,"DELETE FROM tbladministration WHERE messageid='$_GET[delete_message]'");
if($_SQL_D){
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Notice Successfully Deleted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Notice failed to delete</div>";
}
}
?>
<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/notification.css">

<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 function uncheckBox(){
   var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }
 function checkBox(){
var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
 function toggleBatchFilter(){
   var recipient=document.getElementById("recipient");
   var batchWrap=document.getElementById("batch_wrap");
   if(!recipient || !batchWrap){
     return false;
   }
   if(recipient.value=="Student"){
     batchWrap.style.display="block";
   }
   else{
     batchWrap.style.display="none";
   }
   return false;
 }
 document.addEventListener("DOMContentLoaded",function(){
   toggleBatchFilter();
 });
</script>
</head>
<body class="body-style">
<div class="header">
<?php
include("menu.php");
?>		
</div>
<div class="main-platform notify-page">
<section class="notify-hero">
	<div>
		<span class="notify-kicker">Communication</span>
		<h1>Notifications</h1>
		<p>Send SMS notices to staff or students by selecting recipients and composing one message.</p>
	</div>
	<div class="notify-hero-card">
		<i class="fa fa-bullhorn"></i>
		<span>Bulk SMS Notice</span>
	</div>
</section>

<section class="notify-panel notify-filter-panel">
	<div class="notify-panel-heading">
		<span class="notify-icon"><i class="fa fa-filter"></i></span>
		<div>
			<h2>Load Recipients</h2>
			<p>Choose a recipient group first, then load the users to select.</p>
		</div>
	</div>
<form id="formID" method="post" class="notify-filter-form">
<label>Recipient</label>
<select id="recipient" name="recipient" class="validate[required]" onchange="toggleBatchFilter()">
<option value="">Select Recipient</option>
<option value="Teaching Staff" <?php if($_SelectedRecipient=="Teaching Staff"){ echo "selected"; } ?>>Teaching Staff</option>
<option value="Non-Teaching Staff" <?php if($_SelectedRecipient=="Non-Teaching Staff"){ echo "selected"; } ?>>Non Teaching Staff</option>
<option value="Student" <?php if($_SelectedRecipient=="Student"){ echo "selected"; } ?>>Student</option>
</select>
<div id="batch_wrap" class="notify-batch-wrap" style="display:none;">
<label>Batch</label>
<select id="batchid" name="batchid">
<option value="">Select Batch</option>
<option value="__all_students__" <?php if($_SelectedBatchId=="__all_students__"){ echo "selected"; } ?>>All Students</option>
<?php
$_SQL_BATCH=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
while($row_bh=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
	$_SelBatch=($_SelectedBatchId==$row_bh['batchid']) ? "selected" : "";
	echo "<option value='$row_bh[batchid]' $_SelBatch>$row_bh[batch]</option>";
}
?>
</select>
</div>
<div class="notify-filter-actions">
<button class="button-show notify-btn notify-btn-primary" id="showrecipient" name="showrecipient"><i class="fa fa-search"></i> Show Users</button>
</div>
</form>
<?php
echo $_SESSION['Message'];
?>
</section>

<form method="post" id="formID" name="formID" action="notification.php">
<div class="notify-layout">
<section class="notify-panel notify-message-panel">
	<div class="notify-panel-heading">
		<span class="notify-icon"><i class="fa fa-pencil"></i></span>
		<div>
			<h2>Compose Message</h2>
			<p>Write the SMS message and send it to the selected users.</p>
		</div>
	</div>
<label>Message</label>
<textarea id="message" name="message" class="validate[required]" placeholder="Type the message you want to send..."></textarea>
<div class="notify-actions"><button class="button-save notify-btn notify-btn-send" id="send_message" name="send_message"><i class="fa fa-send"></i> Send Message</button></div>
</section>

<section class="notify-panel notify-users-panel">
	<div class="notify-panel-heading">
		<span class="notify-icon"><i class="fa fa-users"></i></span>
		<div>
			<h2>Recipient List</h2>
			<p>Select all or choose individual users before sending.</p>
		</div>
	</div>
<?php	
if(isset($_POST["showrecipient"])){
	$_SQL_2=false;
	if($_SelectedRecipient=="Student"){
		if($_SelectedBatchId==""){
			echo "<div class='notify-alert'><i class='fa fa-exclamation-circle'></i> Select batch to view students.</div>";
		}
		else if($_SelectedBatchId=="__all_students__"){
			$_SQL_2=mysqli_query($con,"SELECT DISTINCT su.* FROM tblsystemuser su WHERE su.systemtype='Student' AND su.status='active' ORDER BY su.firstname ASC,su.surname ASC");
		}
		else{
			$_BatchSafe=mysqli_real_escape_string($con,$_SelectedBatchId);
			$_SQL_2=mysqli_query($con,"SELECT DISTINCT su.* FROM tblclass cl INNER JOIN tblsystemuser su ON su.userid=cl.userid WHERE su.systemtype='Student' AND cl.batchid='$_BatchSafe' AND cl.status='active' ORDER BY su.firstname ASC,su.surname ASC");
		}
	}
	else{
		$_RecipientSafe=mysqli_real_escape_string($con,$_SelectedRecipient);
		$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.staffstatus='$_RecipientSafe' ORDER BY su.userid ASC");
	}
	if($_SQL_2){
		echo "<div class='notify-table-wrap'>";
		echo "<table class='notify-table'>";
		echo "<caption>LIST OF USERS</caption>";
		echo "<thead><th><input type='checkbox' id='all' name='all' Onclick='selectAll()' /></th><th>*</th><th>MOBILE</th><th>FULL NAME</th></thead>";
		echo "<tbody>";
		@$serial=0;
		if(mysqli_num_rows($_SQL_2)<1){
			echo "<tr><td colspan='4' class='notify-empty-row'>No users found</td></tr>";
		}
		while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
		{
		echo "<tr class='notify-user-row'>";
		echo "<td>";
		echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
		echo "</td>";
		echo "<td>";
		echo $serial=$serial+1;
		echo "</td>";
		echo "<td>";
		echo $row['mobile'];
		echo "</td>";			
		echo "<td>";
		echo $row['firstname']." ". $row['othernames']." ". $row['surname']."(".$row['userid'].")";
		echo "</td>";
		echo "</tr>";
		}	
		echo "</tbody>";
		echo "</table>";
		echo "</div>";
	}
}else{
	echo "<div class='notify-empty-state'><i class='fa fa-users'></i><h3>No recipients loaded</h3><p>Select a recipient group above and click Show Users.</p></div>";
}
?>
</section>
</div>
</form>

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

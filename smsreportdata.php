<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("config.php");

@$_UserId=$_POST['userid'];
@$_BatchId=$_POST['batch_selected_id'];

if(isset($_POST['deletesendsms'])){
		if(!$_UserId)
		{
		$_SESSION['Message']="<div style='color:red'>No user selected</div>";
		}
		else{
			foreach($_UserId as $selecteduser)
			{
				$_SQLD=mysqli_query($con,"DELETE FROM tblsmsexamresults WHERE userid='$selecteduser' AND batchid='$_BatchId'");
				if($_SQLD){
					$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>SMS Data Successfully Deleted</div>";
					}else{
					$_Error=mysqli_error($con);
					$_SESSION['Message']="<div style='color:red'>Company failed to update,$_Error</div>";
				}
			}		
		}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
     document.getElementById("deletesendsms").style.display="block";

  }
  else if(selall==false){
    uncheckBox();
    document.getElementById("deletesendsms").style.display="none";
  }  
 }
 function hidebutton(){
 	  document.getElementById("deletesendsms").style.display="none";
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
</script>
<style type="text/css">
#showst{
	width:50%;
	float:left;
}
#showst2{
	margin-left:-5px;
	float:left;
}
</style>
</head>
<body onload="hidebutton()">
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style=""><br/>
<table width="100%">
		<tr>
			<td valign="top" width="40%" align="center">
		<div class="form-entry" align="left">
			<h3>REPORTS OF SMS EXAM RESULTS</h3>
				<?php
				echo $_SESSION['Message'];
				?>
			<form method="post" action="smsreportdata.php">
				<div id="showst">
				<?php
				$_SQL_5=mysqli_query($con,"SELECT * FROM tblbatch");
				echo "<select id='batchid' name='batchid' class='validate[required]'>";
				echo "<option value=''>Select Batch</option>";
				while($rowl=mysqli_fetch_array($_SQL_5,MYSQLI_ASSOC)){
				echo "<option value='$rowl[batchid]'>$rowl[batch]</option>";
				}
				echo "</select>";
				?>
			</div>
				<div id="showst2">
				<button class="button-show" id="showstudent" name="showstudent"><i class="fa fa-search"></i> Show Student</button>
				</div>
			</form><br/><br/><br/>
<form method="post" id="formID" name="formID" action="smsreportdata.php">

<?php
if(isset($_POST["showstudent"])){
	echo "<input type='hidden' id='batch_selected_id' name='batch_selected_id' value='$_POST[batchid]' />";

			$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblsmsexamresults str ON su.userid=str.userid
			
			WHERE  str.batchid='$_POST[batchid]' ORDER BY su.userid ASC");
			@$_Batch="";
			$_SQL_6=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_POST[batchid]'");
			if($rowb=mysqli_fetch_array($_SQL_6,MYSQLI_ASSOC)){
			$_Batch=$rowb['batch'];
			}
			if(!(mysqli_num_rows($_SQL_2)>0)){
				echo "<div style='color:red'>There is no SMS Exam Results Sent</div>";
			}else{
			echo "<table>";
			echo "<caption>BATCH: ".strtoupper($_Batch)."</caption>";
			echo "<thead><th><input type='checkbox' id='all' name='all' Onclick='selectAll()' /></th><th>*</th><th>MOBILE</th><th>EMAIL</th><th>STUDENT</th><th>SMS EXAM RESULTS</th></thead>";
			echo "<tbody>";
			@$serial=0;
			while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
			{
			echo "<tr class='light'>";
			echo "<td align='center'>";
			echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
			echo "</td>";
			echo "<td align='center'>";
			echo $serial=$serial+1;
			echo "</td>";

			echo "<td width='10%'>";
			echo $row["mobile"];
			echo "</td>";

			echo "<td width='10%'>";
			echo $row["email"];
			echo "</td>";
			
			
			echo "<td width='30%'>";
			echo $row['firstname']." ". $row['othernames']." ". $row['surname']."(".$row['userid'].")";
			echo "</td>";

			echo "<td width='55%'>";
			echo $row["examresults"];
			echo "</td>";
			echo "</tr>";
			}	
			echo "</tbody>";
			echo "</table>";

			if(mysqli_num_rows($_SQL_2)>0){
			echo "<button class='button-delete' id='deletesendsms' name='deletesendsms'><i class='fa fa-trash-o'></i> DELETE SMS</button>";
		}
		}
		}
	?>
</div>
</div>

</td>
</tr>
</table>
</div>
</body>
</html>
<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_ValueId=$_POST["valueid"];
@$_APIKey=$_POST["apikey"];
@$_ExpiryDate=$_POST["expirydate"];
@$_Finalkey=md5($_APIKey.$_ExpiryDate);
echo $_APIKey.$_ExpiryDate;
if(isset($_POST['save_key'])){
//$_SQL=mysqli_query($con,"DELETE FROM tblapi");
//if($_SQL){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblapi(valueid,apikey,startdate,enddate,status)
	VALUES('$_ValueId','$_Finalkey',NOW(),STR_TO_DATE('$_ExpiryDate','%d-%m-%Y'),'active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>API Key Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>API Key failed to save,$_Error</div>";
	}
}
//}
?>

<?php
include("dbstring.php");
@$_Update_Batch=$_POST['update_batch_month']." ".$_POST['update_batch_year'];
@$_update_batch_entryid=$_POST['update_batch_entryid'];

if(isset($_POST['update_batch_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblbatch SET batch='$_Update_Batch' WHERE batchid='$_update_batch_entryid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Batch Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Batch failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_batch"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblbatch WHERE batchid='$_GET[delete_batch]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Batch Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Batch failed to delete,Error:$_Error</div>";
	}
}
?>



<html>
<head>
<?php
include("links.php");
?>

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

<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_batch']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[edit_batch]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Batch Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='generateapikey.php'>";
			echo "<label>Batch Id</label>";
			echo "<input type='text' id='update_batch_entryid' name='update_batch_entryid' value='$row[batchid]'><br/>";

			echo "<input type='text' id='update_batch' name='update_batch' value='$row[batch]' readonly><br/><br/>";

			echo "<fieldset><legend>BATCH</legend>";
			echo "<table>";
			echo "<tr>";
			echo "<td>";
			echo "<select id='update_batch_month' name='update_batch_month' class='validate[required]'>";
			echo "<option value=''>Select Month</option>";
			echo "<option value='January'>January</option>";
			echo "<option value='February'>February</option>";
			echo "<option value='March'>March</option>";
			echo "<option value='April'>April</option>";
			echo "<option value='May'>May</option>";
			echo "<option value='June'>June</option>";
			echo "<option value='July'>July</option>";
			echo "<option value='August'>August</option>";
			echo "<option value='September'>September</option>";
			echo "<option value='October'>October</option>";
			echo "<option value='November'>November</option>";
			echo "<option value='December'>December</option>";
			echo "</select>";
			echo "</td>";

			echo "<td>";
			$k=2018;
			echo "<select id='update_batch_year' name='update_batch_year' class='validate[required]'>";
			echo "<option value=''>Select Year</option>";
			while($k<=2030){
			echo "<option value='$k'>$k</option>";
			$k++;
			}
			echo "</select>";
			echo "</td>";
			echo "</tr>";
			echo "</table>";
			echo "</fieldset>";
			echo "<br/>";

			echo "<div align='center'><button class='button-edit' id='update_batch_entry' name='update_batch_entry'><i class='fa fa-edit' style='color:white'></i> UPDATE BATCH</button></div>";
			echo "<br/>";

			echo "</form>";
			}
		}
		}
		?>
	</div>

			<div class="form-entry" align="left">
			<h3>API KEY GENERATION 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="generateapikey.php">

			<label>VALUE ID</label><br/>
			<input type="text" id="valueid" name="valueid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>

			<label>Expiry Date</label><br/>

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
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('expirydate','ddMMyyyy','','','','','')" id="expirydate" name="expirydate" value="" class="validate[required]" readonly   onchange=""/>
      <br/><br/>
      <label>API Key</label><br/><br/>
<?php
include("apikey.php");
?>
<input type="text" id="apikey" name="apikey" value="<?php echo $_FinalKey; ?>" readonly/>
			<br/><br/>
			
			
			<div align="center"><button class="button-save" id="save_key" name="save_key"><i class="fa fa-save"></i> SAVE KEY</button></div>
		</form>

		</div>
			</td>
			
		</tr>

	</table>
		<!--Login-->
		
		<br/><br/><br/>

</div>

<!--Footer-->

</body>
</html>
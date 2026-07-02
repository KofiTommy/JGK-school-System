<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
?>


<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_StartTime=$_POST['starttime'];
@$_EndTime=$_POST['endtime'];
@$_Tabledate=$_POST['timetabledate'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_timetable']))
{
//Create payment container
include("shortcode.php");
@$_TimeId=$shortcode;
@$_Transaction_Code=$transaction_id;
@$_TransId=0;

$_SQL_Time=mysqli_query($con,"INSERT INTO tbltimetable(timeid,subjectid,tablestarttime,tableendtime,tabledate,class_entryid,termname,batchid,recordedby,status)
	VALUES('$_TimeId','$_SubjectId','$_StartTime','$_EndTime',STR_TO_DATE('$_Tabledate','%d-%m-%Y'),'$_ClassId','$_Term','$_Batch','$_SESSION[USERID]','active')");
if($_SQL_Time){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:green'></i> Time Table Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>No Time Table saved,$_Error</div>";
}
}
?>

<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_StartTime=$_POST['starttime'];
@$_EndTime=$_POST['endtime'];
@$_Tabledate=$_POST['timetabledate'];
@$_Recordedby=$_SESSION['USERID'];
@$_TimeId=$_POST["timeid"];

if(isset($_POST['update_timetable']))
{
//Create payment container
include("code.php");
@$_TimeId=$code;
@$_Transaction_Code=$transaction_id;
@$_TransId=0;

$_SQL_Time=mysqli_query($con,"UPDATE tbltimetable SET subjectid='$_SubjectId',tablestarttime='$_StartTime',
	tableendtime='$_EndTime',tabledate=STR_TO_DATE('$_Tabledate','%d-%m-%Y'),class_entryid='$_ClassId'
	,termname='$_Term',batchid='$_Batch' WHERE timeid='$_TimeId'");
if($_SQL_Time){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:#efe;padding:5px;border:1px solid #4a4;'><i class='fa fa-check' style='color:green'></i> Time Table Successfully Updated</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>No Time Table update,$_Error</div>";
}
}
?>

<?php
include("dbstring.php");
if(isset($_GET["delete_timetable"]))
{
$_SQLDelete=mysqli_query($con,"DELETE FROM tbltimetable WHERE timeid='$_GET[delete_timetable]'");
if($_SQLDelete){

	}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script>
  var rnd;
function getItemID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("item-id").value=rnd;
}
</script>

<script type="text/javascript">
var gbatch;
function getBatch()
{
gbatch=getElementById("batch").value;
 //return _batch;  
}
function getStudentBill(str)
{
	if(str=="")
  {
  
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-class-bill.php?search-bill="+str+"&batch="+gbatch,true);
    xmlhttp.send();
  }
}
</script>
</head>

<body width="120%">
	<div class="header">
<?php
include("menu.php");
?>		
</div>
<div class="main-platform" style="">
<table width="100%" style="background-color:white">
<tr>
<td valign="top" width="30%" align="center">
	<!--UPDATE EXAMINATINO TIME TABLE-->
<?php
if(isset($_GET["edit_timeid"])){
?>
	<div class="form-entry" align="left">
<h3>EXAMINATION TIME TABLE UPDATE
</h3>
<br/>
<form method="post" id="formID2" name="formID2" action="examinationtimetable.php">
<input type="hidden" id="timeid" name="timeid" value="<?php echo $_GET["edit_timeid"];?>" />
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubject");
echo "<select id='subjectid' name='subjectid' class='validate[required]'>";
echo "<option value=''>Select Subject</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[subjectid]'>$row[subject]</option>";
}
echo "</select><br/><br/>";
?>
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");
echo "<select id='class' name='class' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
}
echo "</select><br/><br/>";
?>
<select id="term" name="term" class="validate[required]">
<option value="" >Select Semester</option>
<option value="1">1</option>
<option value="2">2</option>
</select><br/><br/>

<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");
echo "<select id='batch' name='batch' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select><br/><br/>";
?>
<label>Start Time</label><br/>
<input type="time" id="starttime" name="starttime" value=""/>
<br/><br/>
<label>End Time</label><br/>
<input type="time" id="endtime" name="endtime" value=""/>
			<br/><br/>


			<label>Date</label><br/>

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
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('timetabledate','ddMMyyyy','','','','','')" id="timetabledate" name="timetabledate" value="" class="validate[required]" readonly   onchange="CheckDateOfBirth()"/>
      <br/><br/>
			
<div align="center"><button class="button-edit" id="update_timetable" name="update_timetable"><i class="fa fa-edit"></i> UPDATE TIME TABLE</button></div>
		</form>

		</div>
<?php
}
?>
<!--ADD NEW EXAMINATION TIME TABLE-->
<div class="form-entry" align="left">
<h3>EXAMINATION TIME TABLE ENTRY
</h3>
<br/>
<form method="post" id="formID" name="formID" action="examinationtimetable.php">
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubject");
echo "<select id='subjectid' name='subjectid' class='validate[required]'>";
echo "<option value=''>Select Subject</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[subjectid]'>$row[subject]</option>";
}
echo "</select><br/><br/>";
?>
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");
echo "<select id='class' name='class' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
}
echo "</select><br/><br/>";
?>
<select id="term" name="term" class="validate[required]">
<option value="" >Select Semester</option>
<option value="1">1</option>
<option value="2">2</option>
</select><br/><br/>

<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");
echo "<select id='batch' name='batch' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select><br/><br/>";
?>
<label>Start Time</label><br/>
<input type="time" id="starttime" name="starttime" value=""/>
<br/><br/>
<label>End Time</label><br/>
<input type="time" id="endtime" name="endtime" value=""/>
			<br/><br/>


			<label>Date</label><br/>

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
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('timetabledate','ddMMyyyy','','','','','')" id="timetabledate" name="timetabledate" value="" class="validate[required]" readonly   onchange="CheckDateOfBirth()"/>
      <br/><br/>
			
<div align="center"><button class="button-save" id="save_timetable" name="save_timetable"><i class="fa fa-save"></i> SAVE TIME TABLE</button></div>
		</form>

		</div>
</td>
<td width="70%">
<div class="form-entry">
<?php
echo $_SESSION["Message"];
?>
	<?php
	include("dbstring.php");
	$_SQL=mysqli_query($con,"SELECT * FROM tbltimetable tt INNER JOIN tblclassentry ce ON tt.class_entryid=ce.class_entryid
	INNER JOIN tblbatch bch  ON tt.batchid=bch.batchid INNER JOIN tblsubject sub ON tt.subjectid=sub.subjectid");
	echo "<table style='background-color:white'>";
	echo "<caption>EXAMINATION TIME TABLE</caption>";
	echo "<thead><th colspan='2'>TASK</th><th>*</th><th>TIME ID</th><th>SUBJECT</th><th>CLASS</th><th>SEM.</th><th>BATCH</th><th>START TIME</th><th>END START</th><th>DATE</th></thead>";
	echo "<tbody>";
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
	echo "<tr>";
	//echo "<td align='center'><a title='View $row[subject]' href='examinationtimetable.php?view_user=$row[timeid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
	echo "<td align='center'><a title='Delete $row[subject]' onclick=\"javascript:return confirm('Do you want to delete?');\" href='examinationtimetable.php?delete_timetable=$row[timeid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
	echo "<td align='center'><a title='Edit Exam Time for $row[subject]' href='examinationtimetable.php?edit_timeid=$row[timeid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				


	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";
	
	echo "<td>";
	echo $row['timeid'];
	echo "</td>";

	echo "<td>";
	echo $row['subject'];
	echo "</td>";
	echo "<td align='center'>";
	echo $row['class_name'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['termname'];
	echo "</td>";

	echo "<td align='center'>";
	echo $row['batch'];
	echo "</td>";

	echo "<td align='center'>";
	echo $row['tablestarttime'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tableendtime'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tabledate'];
	echo "</td>";
	echo "</tr>";
	}
	?>
</div>
</td>
</tr>
</table>

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
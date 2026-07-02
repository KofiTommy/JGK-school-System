 <?php
session_start();
$_SESSION['Message']="";
include("check-login.php");

class Days{
var $_days;

	public function setDay($strdate)
	{
	$strdate = date("l",strtotime($strdate));

//echo $strdate;
		switch ($strdate) {
			case "Sunday":
				$this->_days="Sunday";

				break;
			case "Monday";
				$this->_days="Monday";
				break;
			case "Tuesday":
				$this->_days="Tuesday";
				break;
			case "Wednesday":
				$this->_days="Wednesday";
				break;
			case "Thursday":
				$this->_days="Thursday";
				break;
			case "Friday":
				$this->_days="Friday";
				break;
			case "Saturday":
				$this->_days="Saturday";
				break;
			default:
				return $this->_days="";
				break;
		}
		return $this->_days;
	}
	public function getDay(){
		return $this->_days;
	}
}
?>

<?php
  @$Overall_amount = 0;
  @$from_date = date("Y-m-d",strtotime($_POST['fromdate']));
  @$to_date = date("Y-m-d",strtotime($_POST['todate']));
      
if(isset($_POST["print_timetable"]))
{
@$_ClassID=$_POST["class"];
@$_Term=$_POST["termname"];
@$_Batch=$_POST["batch"];

include("dbstring.php");
include("config.php");
include("company.php");             
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(40,70,40,45,20);
$pdf->SetFont('Arial','B',14);
//Background color of header//
//Heading of the pdf
// Logo
$k=8;
     // $pdf->Image('images/ike.png',5,3,13);
// $pdf->Ln($k);

$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($k);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Address: '.$_Address,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Location: '.$_Location,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel: '.$_Telephone1.'  '.$_Telephone2,0,0,'C',true);
$pdf->Ln($k);

@$_ClassName="";
$_SQL2=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassID'");
if($row2=mysqli_fetch_array($_SQL2,MYSQLI_ASSOC)){
$_ClassName=$row2["class_name"];
}
@$_Batch_ID="";
$_SQL3=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_Batch'");
if($row3=mysqli_fetch_array($_SQL3,MYSQLI_ASSOC)){
$_Batch_ID=$row3["batch"];
}

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'EXAMINATION TIME TABLE',0,0,'C',true);
$pdf->Ln($k);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'CLASS :'.$_ClassName. '       Batch :'. $_Batch_ID. '     Semester: '.$_Term,0,0,'C',true);
$pdf->Ln($k);

$pdf->SetFillColor(255,255,255);

//$pdf->SetMargin(100);
$pdf->SetFont('Arial','',10);
//Header starts //
   
//First header column //
$pdf->Cell($width_cell[0],10,'DAYS',1,0,'C',true);
//Third header column //
$pdf->Cell($width_cell[1],10,'SUBJECT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'TIME',1,0,'C',true);
//Fourth header column
$pdf->Cell($width_cell[3],10,'DATE',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
@$serial=0;


//$sql1 = "SELECT * FROM tbltimetable WHERE class_entryid='$_ClassID' AND termname='$_Term' AND batchid='$_Batch'";

$_SQL=mysqli_query($con,"SELECT * FROM tbltimetable tt INNER JOIN tblclassentry ce ON tt.class_entryid=ce.class_entryid
INNER JOIN tblbatch bch  ON tt.batchid=bch.batchid INNER JOIN tblsubject sub ON tt.subjectid=sub.subjectid
WHERE tt.class_entryid='$_ClassID' AND tt.termname='$_Term' AND tt.batchid='$_Batch' ORDER BY tt.tabledate ASC");

$count = mysqli_num_rows( $_SQL);
@$serial =0;
@$_Total=0;
if($count>0)
{  
   while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC))
    {     
    $days = new Days;
    $days->setDay($row['tabledate']);
    $_GetDays=$days->getDay();

    $pdf->Cell($width_cell[0],10,$_GetDays,1,0,'C',$fill);
    $pdf->Cell($width_cell[1],10,$row['subject'],1,0,'L',$fill);
    $pdf->Cell($width_cell[2],10,$row['tablestarttime']."-".$row['tableendtime'],1,0,'C',$fill);
    $pdf->Cell($width_cell[3],10,$row['tabledate'],1,0,'C',$fill);
    
    $fill = !$fill;
    $pdf->Ln(10);                 
   }  
$pdf->Ln(10); 
}

$pdf->Ln(10);
/// end of records ///
$pdf->Output();
mysqli_close($con);
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

if(isset($_POST['save_timetable']))
{
//Create payment container
include("code.php");
@$_TimeId=$code;
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
<link rel="stylesheet" href="css/examinationtimetablereport.css">

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

<body class="body-style">
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>

<div class="main-platform exam-timetable-report-page">
	<?php
	echo $_SESSION["Message"];
	?>

	<section class="exam-timetable-report-hero">
		<div>
			<span class="exam-timetable-report-kicker">Examination timetable</span>
			<h1>Examination Timetable Report</h1>
			<p>Select the class, semester and batch to print the examination timetable, while reviewing all scheduled papers below.</p>
		</div>
		<div class="exam-timetable-report-hero-card">
			<i class="fa fa-calendar"></i>
			<span>Print-ready report</span>
			<small>Generate a PDF timetable for a selected class.</small>
		</div>
	</section>

	<div class="exam-timetable-report-layout">
		<aside class="exam-timetable-report-panel exam-timetable-report-filter-panel">
			<div class="exam-timetable-report-panel-heading">
				<span class="exam-timetable-report-icon"><i class="fa fa-filter"></i></span>
				<div>
					<h2>Report Filters</h2>
					<p>Choose the details for the timetable PDF.</p>
				</div>
			</div>
			<form method="post" id="formID" name="formID" action="examinationtimetablereport.php">
			
<?php
include("dbstring.php");
if(
    $_SESSION['SYSTEMTYPE']=="normal_user" ||
    $_SESSION['SYSTEMTYPE']=="super_user" ||
    $_SESSION['SYSTEMTYPE']=="User" ||
    $_SESSION['SYSTEMTYPE']=="Teacher" ||
    $_SESSION['SYSTEMTYPE']=="Headmaster" ||
    (function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user())
)
{
	$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<div class='exam-timetable-report-field'>";
			echo "<label for='class'>Class</label>";
			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select>";
			echo "</div>";
}
elseif( $_SESSION['SYSTEMTYPE']=="Student")
{

$_SQL_C=mysqli_query($con,"SELECT * FROM tblclass cl WHERE cl.userid='$_SESSION[USERID]'");

		echo "<div class='exam-timetable-report-field'>";
		echo "<label for='class'>Class</label>";
		echo "<select id='class' name='class' class='validate[required]'>";
		while($rows=mysqli_fetch_array($_SQL_C,MYSQLI_ASSOC))
		{	
		$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$rows[class_entryid]'");

			echo "<option value=''>Select Class</option>";
			while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
			echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
			}
		}
		echo "</select>";
		echo "</div>";
}
?>

			<div class="exam-timetable-report-field">
			<label for="termname">Semester</label>
			<select id="termname" name="termname" class="validate[required]">
				<option value="" >Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
			</select>
			</div>

			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<div class='exam-timetable-report-field'>";
			echo "<label for='batch'>Batch</label>";
			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select>";
			echo "</div>";
			?>
<div class="exam-timetable-report-actions"><button class="button-pay exam-timetable-report-btn exam-timetable-report-btn-primary" id="print_timetable" name="print_timetable"><i class="fa fa-print"></i> PRINT TIME TABLE</button></div>
</form>
		</aside>

		<main class="exam-timetable-report-panel exam-timetable-report-list-panel">
			<div class="exam-timetable-report-panel-heading">
				<span class="exam-timetable-report-icon"><i class="fa fa-clock-o"></i></span>
				<div>
					<h2>Scheduled Papers</h2>
					<p>All examination timetable entries currently saved in the system.</p>
				</div>
			</div>
	<?php
	include("dbstring.php");
	$_SQL=mysqli_query($con,"SELECT * FROM tbltimetable tt INNER JOIN tblclassentry ce ON tt.class_entryid=ce.class_entryid
	INNER JOIN tblbatch bch  ON tt.batchid=bch.batchid INNER JOIN tblsubject sub ON tt.subjectid=sub.subjectid");
	echo "<div class='exam-timetable-report-table-wrap'>";
	echo "<table class='exam-timetable-report-table'>";
	echo "<caption>EXAMINATION TIME TABLE</caption>";
	echo "<thead><tr><th>*</th><th>DAY</th><th>START TIME</th><th>END TIME</th><th>DATE</th><th>SUBJECT</th><th>CLASS</th><th>SEMESTER</th><th>BATCH</th></tr></thead>";
	echo "<tbody>";
	@$serial=0;
	if($_SQL && mysqli_num_rows($_SQL)<1){
	echo "<tr><td colspan='9' class='exam-timetable-report-empty-row'>No examination timetable entries found.</td></tr>";
	}
	while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
	echo "<tr>";
	//echo "<td align='center'><a title='View $row[subject]' href='examinationtimetablereport.php?view_user=$row[timeid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
	//echo "<td align='center'><a title='Delete $row[subject]' onclick=\"javascript:return confirm('Do you want to delete?');\" href='examinationtimetablereport.php?delete_timetable=$row[timeid]'<i class='fa fa-times' style='color:red'></i></a></td>";
	//echo "<td align='center'><a title='Edit $row[subject]' href='examinationtimetablereport.php?edit_user=$row[timeid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				
	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";

	 $days = new Days;
    $days->setDay($row['tabledate']);
    echo "<td align='center'>";
    echo $_GetDays=$days->getDay();
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
	echo "</tr>";
	}
	echo "</tbody>";
	echo "</table>";
	echo "</div>";
	?>
		</main>
	</div>
</div>
</body>
</html>

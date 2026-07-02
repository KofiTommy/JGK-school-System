<?php
session_start();
$_SESSION['Message']="";
?>

<?php
//Declare the variables
if(isset($_POST["print_all_reports"])){
   include("dbstring.php");
   include("config.php");
   include("company.php");
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(25,40,120);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',100,3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);
$pdf->Ln(10);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);

$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //

//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'SUBJECT ID',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'SUBJECT',1,0,'C',true);

///header ends///

//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
	
include("dbstring.php");

$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry");
while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC)){
$_SQL_EXECUTE=mysqli_query($con,"SELECT *,sc.datetimeentry FROM tblsubjectclassification sc
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid WHERE sc.classid='$row_v[class_entryid]'");

if(mysqli_num_rows($_SQL_EXECUTE)==0){}
else{
$pdf->SetFont('Arial','B',9);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$row_v['class_name'],1,0,'L',$fill); 
$pdf->Ln(10);
@$serial=0;
$pdf->SetFont('Arial','',9);
while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$row["subjectid"],1,0,'L',$fill); 
	$pdf->Cell($width_cell[2],10,$row["subject"],1,0,'L',$fill); 
	$pdf->Ln(10);
}
}	
}
/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
?>

<?php
@$_Class_EntryId=$_POST["class_entryid"];
//Declare the variables
if(isset($_POST["print_report"])){
   include("dbstring.php");
   include("config.php");
   include("company.php");
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(25,40,120);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',100,3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);
$pdf->Ln(10);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);

$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //

//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'SUBJECT ID',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'SUBJECT',1,0,'C',true);

//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
	
include("dbstring.php");

$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_Class_EntryId'");
while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC)){
$_SQL_EXECUTE=mysqli_query($con,"SELECT *,sc.datetimeentry FROM tblsubjectclassification sc
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid WHERE sc.classid='$row_v[class_entryid]'");

if(mysqli_num_rows($_SQL_EXECUTE)==0){}
else{
$pdf->SetFont('Arial','B',9);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$row_v['class_name'],1,0,'L',$fill); 
$pdf->Ln(10);
@$serial=0;
$pdf->SetFont('Arial','',9);
while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$row["subjectid"],1,0,'L',$fill); 
	$pdf->Cell($width_cell[2],10,$row["subject"],1,0,'L',$fill); 
	$pdf->Ln(10);
}
}	
}
/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
?>

<?php
include("dbstring.php");
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['classid'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_subject_classification']))
{
	foreach ($_SubjectId as $_Selected_Subject) 
	{
		include("code.php");
	@$_ClassificationId=$code;
	@$_Subject="";
	//Check if subject already registered
	$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_Selected_Subject'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	}

	$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectclassification sc WHERE sc.classid='$_ClassId' AND sc.subjectid='$_Selected_Subject' AND sc.term='$_Term'");
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Classified</div>";
		
	}else{

	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsubjectclassification(classificationid,classid,subjectid,term,status,datetimeentry,recordedby)
	VALUES('$_ClassificationId','$_ClassId','$_Selected_Subject','$_Term','active',NOW(),'$_Recordedby')");
	if($_SQL_EXECUTE)
	{
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Subject Successfully Classified</div>";
		}
		else{
			$_Error=mysqli_error($con);
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_Subject failed to classify,$_Error</div>";
		}
	}	
	}
}
?>

<?php
include("dbstring.php");
@$_Update_subject=$_POST['update_item'];
@$_Update_subjectid=$_POST['update_subjectid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsubject SET subject='$_Update_subject' WHERE subjectid='$_Update_subjectid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Subject Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Subject failed to update,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
if(isset($_GET["delete_subject"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsubjectclassification WHERE classificationid='$_GET[delete_subject]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Subject Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Subject failed to delete,Error:$_Error</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/view-subject-classified.css">
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

<div class="main-platform classified-page">
	<section class="classified-hero">
		<div>
			<span class="classified-kicker">Academic Reports</span>
			<h1>View Subject Classified</h1>
			<p>Review subjects grouped by class and print class-specific or full subject classification reports.</p>
		</div>
		<div class="classified-hero-card">
			<i class="fa fa-list-alt"></i>
			<span>Classified Subjects</span>
		</div>
	</section>

	<section class="classified-panel classified-toolbar">
		<div class="classified-panel-heading">
			<span class="classified-icon"><i class="fa fa-print"></i></span>
			<div>
				<h2>Print Reports</h2>
				<p>Select a class to print one report, or print all classified subjects.</p>
			</div>
		</div>
		<div class="classified-actions-grid">
			<form id="formID" name="formID" method="post" action="view-subject-classified.php" class="classified-print-form">
							<?php	
							$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

							echo "<select id='class_entryid' name='class_entryid' class='validate[required]'>";
							echo "<option value=''>Select Class</option>";
								while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
									echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
								}
								
							echo "</select>";
							?>
				<button class="button-print classified-btn classified-btn-primary" name="print_report"><i class="fa fa-print"></i> Print Report</button>
			</form>
			<form method="post" class="classified-print-all">
				<button class="button-print classified-btn classified-btn-light" name="print_all_reports"><i class="fa fa-print"></i> Print All Reports</button>							
			</form>
		</div>
	</section>

	<section class="classified-panel classified-list-panel">
		<div class="classified-panel-heading">
			<span class="classified-icon"><i class="fa fa-table"></i></span>
			<div>
				<h2>Subject Classification List</h2>
				<p>Each class is shown as a group with its classified subjects below.</p>
			</div>
		</div>
			
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");

				$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry");
				
				//Registered clients
				echo "<div class='classified-table-wrap'>";
				echo "<table class='classified-table'>";
				echo "<caption>List Of Subjects</caption>";
				echo "<thead><th colspan=1>Task</th><th>Subject Id</th><th>Subject</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
				{
				$_SQL_EXECUTE=mysqli_query($con,"SELECT *,sc.datetimeentry FROM tblsubjectclassification sc
				INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid WHERE sc.classid='$row_v[class_entryid]'");
				
				if(mysqli_num_rows($_SQL_EXECUTE)==0)	
				{

				}
				else{
				echo "<tr class='classified-class-row'>";
				echo "<td colspan='9'>";
				echo $row_v['class_name'];
				echo "</td>";
				echo "</tr>";				
			
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr class='classified-subject-row'>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				//echo "<td align='center'>";
				echo "<td align='center'><a class='classified-row-action classified-action-danger' title='Delete $row[subject]' onclick=\"javascript:return confirm('Do you want to delete $row[subject]?');\" href='view-subject-classified.php?delete_subject=$row[classificationid]'><i class='fa fa-trash-o'></i></a></td>";
				
				//echo "</td>";
				echo "<td align='left'>$row[subjectid]</td>";
				echo "<td align='left'>$row[subject]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
			}			
}
echo "</tbody>";
echo "</table>";
echo "</div>";
?>

	</section>
</div>
</body>
</html>

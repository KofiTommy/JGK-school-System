<?php
session_start();
$_SESSION['Message']="";
?>

<?php
if(isset($_POST["printsubject"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
$_SQL_SUB="SELECT * FROM tblsubject";

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(40,95,45,30);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
$pdf->Image("logo/".$_Logo,$width_cell[0]+55,3,22);
$pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);


$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //
//First header column //
$pdf->Cell($width_cell[0],10,'SUBJECT CODE',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'SUBJECT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'ENTRY DATE/TIME',1,0,'C',true);
///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);


@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_SUB) as $row)
{
$pdf->Cell($width_cell[0],10,$row['subjectid'],1,0,'L',$fill);
$pdf->Cell($width_cell[1],10,$row['subject'],1,0,'L',$fill);
$pdf->Cell($width_cell[2],10,$row['datetimeentry'],1,0,'C',$fill); 

 $fill = !$fill;
 $pdf->Ln(10);
}

$pdf->Output();
 //ob_end_flush(); 
}
?>


<?php
include("dbstring.php");
@$_subjectid=$_POST['subjectid'];
@$_subject=$_POST['subject'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_subject'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsubject(subjectid,subject,datetimeentry,status,recordedby,branchid)
	VALUES('$_subjectid','$_subject',NOW(),'active','$_Recordedby','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Subject Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Subject failed to save,$_Error</div>";
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
if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsubject WHERE subjectid='$_GET[delete_item]'");
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
<link rel="stylesheet" href="css/subject-entry.css">
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
<div class="main-platform subject-page">
	<section class="subject-hero">
		<div>
			<span class="subject-kicker">Academic Setup</span>
			<h1>Subject Entry</h1>
			<p>Create, update, print, and manage the subjects offered by the school.</p>
		</div>
		<div class="subject-hero-card">
			<i class="fa fa-book"></i>
			<span>Subject Manager</span>
		</div>
	</section>

	<div class="subject-layout">
		<aside class="subject-side">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<section class='subject-panel subject-edit-panel'>";
			echo "<div class='subject-panel-heading'><span class='subject-icon'><i class='fa fa-edit'></i></span><div><h2>Update Subject</h2><p>Edit the selected subject name.</p></div></div>";
			echo "<form method='post' id='formID' name='formID' action='subject-entry.php'>";
			echo "<label>Subject Id</label>";
			echo "<input type='text' id='update_subjectid' name='update_subjectid' value='$row[subjectid]' readonly>";

			echo "<label>Subject</label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[subject]' class='validate[required]'>";
			echo "<div class='subject-actions'><button class='button-edit subject-btn subject-btn-primary' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit'></i> Update Subject</button></div>";
			echo "<div class='subject-actions subject-cancel-row'><a class='subject-btn subject-btn-light' href='subject-entry.php'><i class='fa fa-times'></i> Cancel Edit</a></div>";
			echo "</form>";
			echo "</section>";
			}
		}
		}
		?>
			<section class="subject-panel subject-create-panel">
			<div class="subject-panel-heading">
				<span class="subject-icon"><i class="fa fa-plus"></i></span>
				<div>
					<h2>Create Subject</h2>
					<p>Add a new subject to the system.</p>
				</div>
			</div>
			<form method="post" id="formID" name="formID" action="subject-entry.php">
			<label>Subject Id</label>
			<input type="text" id="subjectid" name="subjectid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/>

			<fieldset class="subject-fieldset"><legend>Subject Details</legend>
			<label for="subject">Subject Name</label>
			<input type="text" id="subject" name="subject" value="" class="validate[required]"/>
			</fieldset>
			<div class="subject-actions"><button class="button-save subject-btn subject-btn-primary" id="register_subject" name="register_subject"><i class="fa fa-save"></i> Save Subject</button></div>
		</form>

		</section>
		</aside>
			<main class="subject-panel subject-list-panel">
				<div class="subject-panel-heading">
					<span class="subject-icon"><i class="fa fa-list"></i></span>
					<div>
						<h2>Subject List</h2>
						<p>Review existing subjects, print the list, or make updates.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsubject ORDER BY subject ASC");

				//Registered clients
				if(mysqli_num_rows($_SQL_EXECUTE)>0){
				echo "<form method='post'>";
				echo"<div class='subject-print-row'><button class='button-print subject-btn subject-btn-print' id='printsubject' name='printsubject'><i class='fa fa-print'></i> Print Subject</button></div>";
				echo "</form>";
				}
				echo "<div class='subject-table-wrap'>";
				echo "<table class='subject-table'>";
				echo "<caption>List Of Subjects</caption>";
				echo "<thead><th colspan=2>TASK</th><th>SUBJECT ID</th><th>SUBJECT</th><th>ENTRY DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a class='subject-row-action subject-action-danger' title='Delete $row[subject] ($row[subjectid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='subject-entry.php?delete_item=$row[subjectid]'><i class='fa fa-trash-o'></i></a></td>";
				echo "<td align='center'><a class='subject-row-action' title='Edit $row[subject] ($row[subjectid])'  href='subject-entry.php?edit_item=$row[subjectid]'><i class='fa fa-edit'></i></a></td>";
				
				echo "<td align='left'>$row[subjectid]</td>";
				echo "<td align='left'>$row[subject]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
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

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

$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr ON ce.class_entryid=tr.class_entryid WHERE tr.userid='$_SESSION[USERID]'");
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
<br/>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td width="100%">
				<div class="form-entry" align="left">					
				<?php
				echo $_SESSION['Message'];
				include("dbstring.php");

				$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr ON ce.class_entryid=tr.class_entryid WHERE tr.userid='$_SESSION[USERID]'");
				
				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Class</caption>";
				echo "<thead><th>CLASS ID</th><th>CLASS</th></thead>";
				echo "<tbody>";
				while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
				{
		
				echo "<tr style='background-color:#FFF;'>";
				echo "<td width='15%'>";
				echo $row_v['class_entryid'];
				echo "</td>";

				echo "<td>";
				echo $row_v['class_name'];
				echo "</td>";
				echo "</tr>";				
				}
				
echo "</tbody>";
echo "</table>";
?>

</div>
</td>
</tr>
</table>
</form>
</div>
</body>
</html>
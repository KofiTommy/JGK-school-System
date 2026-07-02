<?php
session_start();
//Declare the variables
//@$_SubjectID=$_POST['subjectid'];
@$_Batch_ID=$_POST["batchid"];
@$_Class_ID=$_POST["classid"];

if(isset($_POST["print_examanalysis_report"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
      include("remark.php");
      include("gradingsystem.php");
      include("positions.php");

@$_grade_obj=new GradingSystem;
@$_position_obj_1=new Position;


require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(80,80,30);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',$width_cell[0],3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"GHANA EDUCATION SERVICE",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
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
//$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Ln(10);
$pdf->Cell($width_cell[0],10,'GRADE',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'TOTAL',1,0,'C',true);
//$pdf->Cell($width_cell[3],10,'POSITION',1,0,'C',true);
//$pdf->Cell($width_cell[4],10,'GRADE',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);

$_SQL_SUB2=mysqli_query($con,"SELECT DISTINCT su.subjectid,su.subject FROM tblsubject su
	INNER JOIN tblsubjectclassification sc ON su.subjectid=sc.subjectid
	INNER JOIN tblsubjectassignment sa ON sc.classificationid=sa.classificationid
	WHERE sc.classid='$_Class_ID' AND sa.batchid='$_Batch_ID'
	ORDER BY su.subject ASC");
while($row_us=mysqli_fetch_array($_SQL_SUB2,MYSQLI_ASSOC))
{
$pdf->Cell($width_cell[0]+$width_cell[1],10,$row_us['subject'],1,0,'L',$fill); 

	@$_Grade_A1="A1";
	@$_Grade_B2="B2";
	@$_Grade_B3="B3";
	@$_Grade_C4="C4";
	@$_Grade_C5="C5";
	@$_Grade_C6="C6";
	@$_Grade_D7="D7";
	@$_Grade_E8="E8";
	@$_Grade_F9="F9";

	@$_CountA1=0;
	@$_CountB2=0;
	@$_CountB3=0;
	@$_CountC4=0;
	@$_CountC5=0;
	@$_CountC6=0;
	@$_CountD7=0;
	@$_CountE8=0;
	@$_CountF9=0;

$pdf->Ln(10);
$_SQL_CLASS_User=mysqli_query($con,"SELECT class_name FROM tblclassentry WHERE class_entryid='$_Class_ID'");
if($row_ceuser=mysqli_fetch_array($_SQL_CLASS_User,MYSQLI_ASSOC))
{
$pdf->Cell($width_cell[0]+$width_cell[1],10,$row_ceuser['class_name'],1,0,'L',$fill); 
}

$_SQL_STU=mysqli_query($con,"SELECT su.userid FROM tbltermregistry tr 
	INNER JOIN tblsystemuser su ON tr.userid=su.userid
	WHERE tr.batchid='$_Batch_ID' AND tr.class_entryid='$_Class_ID' AND su.systemtype='Student'
	GROUP BY su.userid ORDER BY su.userid ASC");
while($row_stu=mysqli_fetch_array($_SQL_STU,MYSQLI_ASSOC))
{
	@$_TotalMark=0;		
	@$_EntryCount=0;

		$_SQL_EXECUTE=mysqli_query($con,"SELECT SUM(mk.mark) AS TotalMark,COUNT(mk.mark) AS EntryCount FROM tblmark mk 
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		WHERE mk.userid='$row_stu[userid]' AND sc.subjectid='$row_us[subjectid]' 
		AND sc.classid='$_Class_ID' AND sa.batchid='$_Batch_ID'");

	if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	$_TotalMark=(int)$row['TotalMark'];
	$_EntryCount=(int)$row['EntryCount'];
	}
	if($_EntryCount==0){
		continue;
	}

		///Get the grade
		@$_final_grade=0;

		$_grade_obj->setMark($_TotalMark);
		$_final_grade=$_grade_obj->getMark($_TotalMark);

		if($_Grade_A1==$_final_grade){
			$_CountA1=$_CountA1+1;
		}
		elseif($_Grade_B2==$_final_grade){
			$_CountB2=$_CountB2+1;
		}
		elseif($_Grade_B3==$_final_grade){
			$_CountB3=$_CountB3+1;
		}
		elseif($_Grade_C4==$_final_grade){
			$_CountC4=$_CountC4+1;
		}
		elseif($_Grade_C5==$_final_grade){
			$_CountC5=$_CountC5+1;
			
		}
		elseif($_Grade_C6==$_final_grade){
			$_CountC6=$_CountC6+1;

		}
		elseif($_Grade_D7==$_final_grade){
			$_CountD7=$_CountD7+1;
		}
		elseif($_Grade_E8==$_final_grade){
			$_CountE8=$_CountE8+1;
		}
		elseif($_Grade_F9==$_final_grade){
			$_CountF9=$_CountF9+1;
		}
	//}

}
$_TotalGrade=$_CountA1+$_CountB2+$_CountB3+$_CountC4+$_CountC5+$_CountC6+$_CountD7+$_CountE8+$_CountF9;
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_A1,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountA1,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_B2,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountB2,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_B3,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountB3,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_C4,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountC4,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_C5,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountC5,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_C6,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountC6,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_D7,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountD7,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,$_Grade_E8,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountE8,1,0,'C',$fill); 
$pdf->Ln(10);	
	$pdf->Cell($width_cell[0],10,$_Grade_F9,1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_CountF9,1,0,'C',$fill); 
$pdf->Ln(10);
	$pdf->Cell($width_cell[0],10,"TOTAL STUDENT:",1,0,'C',$fill); 
	$pdf->Cell($width_cell[1],10,$_TotalGrade,1,0,'C',$fill); 
$pdf->Ln(10);
}
/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
?>

<?php
include("dbstring.php");
@$_Mark=$_POST['marks'];
@$_AssignmentId=$_POST['assignmentid'];
@$_UserId=$_POST['userid'];
@$_TotalMark=$_POST['totalscore'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_all_mark']))
{
	@$_CheckMark=0;
	foreach ($_Mark as $_Selected_Mark) 
	{
		if($_Selected_Mark>$_TotalMark){
			$_CheckMark=1;
		}
	}
//Check if mark entered is more than the total mark
if($_CheckMark==1){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white;'>Total Mark is less than the mark entered</div>";
}else/*No mark is greater than the total mark*/
{

$_TotalUsers =count($_UserId);

for($k=0;$k<$_TotalUsers;$k++)
{
$_Selected_User=$_UserId[$k];
$_Selected_Mark=$_Mark[$k];

		include("code.php");
	@$_MarkId=$code;
	@$_UserFullname="";

	$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_Selected_User'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	//@$_Subject="";
	//Check if subject already registered
	/*$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassId'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	$_ClassId=$row_s['classid'];
	//@$_getUser_ID=$row_s['userid'];

	}
	*/

	/*$_SQL_EXECUTE_USER=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsystemuser su ON sa.userid=su.userid WHERE sa.classificationid='$_Selected_ClassId'");
	if(!mysqli_num_rows($_SQL_EXECUTE_USER)>0){
		$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_UserId'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	}else{
		if($row_u=mysqli_fetch_array($_SQL_EXECUTE_USER,MYSQLI_ASSOC)){
		$_UserFullname=$row_u['firstname']." ".$row_u['othernames']." ".$row_u['surname']." (".$row_u['userid'].")";
		}
	}
	*/

	//$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId' AND sa.userid='$_UserId' AND sa.classid='$_ClassId'");
	/*$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId'");
	
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Assigned To $_UserFullname</div>";
		
	}else{
		*/

		$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
		VALUES('$_MarkId','$_AssignmentId','$_Selected_User','Class Score','$_Selected_Mark','$_TotalMark',NOW(),'active','$_Recordedby')");
			if($_SQL_EXECUTE)
			{
		
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Selected_Mark Successfully Stored for $_UserFullname</div>";
			}
			else{
				$_Error=mysqli_error($con);
				$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Mark failed to save,$_Error</div>";
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

if(isset($_GET["delete_mark"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_GET[delete_mark]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
	}
}
?>
<?php
include("dbstring.php");
if(isset($_POST["updatemark"])){
@$_MarkId=$_POST["newmarkid"];
@$_User_Id=$_POST["userid"];
@$_NewMark=$_POST["newmark"];

$_SQLUM=mysqli_query($con,"UPDATE tblmark SET mark='$_NewMark' WHERE userid='$_User_Id' AND markid='$_MarkId'");
if($_SQLUM){
	$_SESSION['Message']="<div style='border:1px solid #4f5;color:green;text-align:left;background-color:#efe;padding:5px;'>Mark Successfully Updated</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<script type="text/javascript">
function checkMark(){
	var total=document.getElementById("totalmark").value;
	var mark=document.getElementById("newmark").value;

	if(mark>total){
		document.getElementById("newmark").value="";
		
	}

}
</script>
</head>
<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="background-color:white">
	<br/>
<table width="100%">
<tr>
<td width="30%">
<div class="form-entry">
<form id="formID" name="formID" method="post" action="examanalysis.php">
	<h4>EXAMS ANALYSIS</h4>
<?php	
include("dbstring.php");
/*$_SQL_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
	INNER JOIN tblsubjectassignment sa ON tr.batchid=sa.batchid AND tr.termname=sa.termname
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE tr.userid='$_SESSION[USERID]' ORDER BY tr.termname ASC");

echo "<select id='classid' name='classid' class='validate[required]'>";
	echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<option value='$row[class_entryid]'>$row[class_name]:Term: $row[termname] : $row[subject]</option>";
	}
echo "</select><br/><br/>";
*/
echo "<fieldset><legend>BATCH</legend>";		
$_SQL_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr INNER JOIN tblbatch bch ON tr.batchid=bch.batchid
			GROUP BY tr.batchid");

echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
if(isset($_POST["batchid"]) && $_POST["batchid"]==$row["batchid"]){
echo "<option value='$row[batchid]' selected>$row[batch]</option>";
}else{
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
}
echo "</select><br/><br/>";

echo "<select id='classid' name='classid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
$_SQL_CLASS_FILTER=mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
while($row_cf=mysqli_fetch_array($_SQL_CLASS_FILTER,MYSQLI_ASSOC)){
	if(isset($_POST["classid"]) && $_POST["classid"]==$row_cf["class_entryid"]){
		echo "<option value='$row_cf[class_entryid]' selected>$row_cf[class_name]</option>";
	}else{
		echo "<option value='$row_cf[class_entryid]'>$row_cf[class_name]</option>";
	}
}
echo "</select><br/><br/>";
echo "<button class='button-show' id='show_terminal_report' name='show_terminal_report'><i class='fa fa-search' style='color:white'></i> SHOW REPORT</button> ";
echo "</fieldset>";
?>

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>
-->

</form>
</div>
</td>
<td width="70%">
<div class="form-entry">

<form id="formID3" name="formID3" method="post" action="examanalysis.php">
<?php
if(isset($_GET["edit_mark"]))
{
	echo "<h3>UPDATE STUDENT'S MARK</h3>";
$_SQL_ED=mysqli_query($con,"SELECT * FROM tblmark mk INNER JOIN 
	tblsystemuser su ON mk.userid=su.userid WHERE mk.markid='$_GET[edit_mark]'");
echo "<table>";
echo "<caption>Mark of Student</caption>";
echo "<thead><th>STUDENT</th><th>BATCH</th><th>SUBJECT</th><th>MARK</th><th>TOTAL</th></thead>";
echo "<tbody>";
if($rows_m=mysqli_fetch_array($_SQL_ED,MYSQLI_ASSOC))
{
echo "<tr>";
echo "<td width='30%'>";
echo "<input type='hidden' id='newmarkid' name='newmarkid' value='$_GET[edit_mark]'readonly/>";

echo "<input type='hidden' id='userid' name='userid' value='$rows_m[userid]'readonly/>";

echo "$rows_m[firstname] $rows_m[othernames] $rows_m[surname] ($rows_m[userid])";
echo "</td>";

echo "<td>";
@$_BATCH="";
$_SQLBh=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[edit_batch]'");
if($rowb=mysqli_fetch_array($_SQLBh,MYSQLI_ASSOC)){
$_BATCH=$rowb["batch"];
}
echo "<input type='hidden' id='batchid' name='batchid' value='$_GET[edit_batch]'/>";
echo "$_BATCH";
echo "</td>";

echo "<td>";
//echo "<input type='hidden' id='userid' name='userid' value='$rows_m[userid]'readonly/>";
echo "$_GET[edit_subject]";
echo "</td>";

echo "<td>";
echo "<input type='text' id='newmark' name='newmark' value='$rows_m[mark]' onchange='checkMark()' class='validate[required]'/>";
echo "</td>";

echo "<td>";
echo "<input type='text' id='totalmark' name='totalmark' value='$rows_m[totalmark]'readonly/>";
echo "</td>";

echo "</tr>";
}
echo "<tr>";
echo "<td>";
echo "<button class='button-edit' id='updatemark' name='updatemark'><i class='fa fa-edit'></i> UPDATE MARK</button>";
echo "</td>";
echo "</tr>";

echo "</tbody>";
echo "</table>";
}
?>
</form>
	<form id="formID2" name="formID2" method="post" action="examanalysis.php">
		<h3>Courses' Grades Statistics</h3>
<?php
include("gradingsystem.php");
	$_grade_obj=new GradingSystem();
echo $_SESSION['Message'];
if(isset($_POST["show_terminal_report"])){
echo "<button class='button-pay' id='print_examanalysis_report' name='print_examanalysis_report'><i class='fa fa-print'></i> Print Report</button><br/><br/>";		
	
@$_Batch_ID=$_POST["batchid"];
@$_Class_ID=$_POST["classid"];
include("dbstring.php");
echo "<input type='hidden' name='batchid' value='$_Batch_ID' />";
echo "<input type='hidden' name='classid' value='$_Class_ID' />";

echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
$_SQL_BA=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_Batch_ID'");
if($rowb=mysqli_fetch_array($_SQL_BA,MYSQLI_ASSOC)){
	echo  $rowb["batch"];
}
$_SQL_CL=mysqli_query($con,"SELECT class_name FROM tblclassentry WHERE class_entryid='$_Class_ID'");
if($rowcl=mysqli_fetch_array($_SQL_CL,MYSQLI_ASSOC)){
	echo " - ".$rowcl["class_name"];
}
echo "</caption>";
echo "<thead><th>GRADE</th><th>TOTAL</th></thead>";
echo "<tbody>";
$_SQL_SUB2=mysqli_query($con,"SELECT DISTINCT su.subjectid,su.subject FROM tblsubject su
	INNER JOIN tblsubjectclassification sc ON su.subjectid=sc.subjectid
	INNER JOIN tblsubjectassignment sa ON sc.classificationid=sa.classificationid
	WHERE sc.classid='$_Class_ID' AND sa.batchid='$_Batch_ID'
	ORDER BY su.subject ASC");
while($row_us=mysqli_fetch_array($_SQL_SUB2,MYSQLI_ASSOC))
{
echo "<tr style='background-color:#fee;font-weight:bold'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='2'>";
echo strtoupper($row_us['subject']);
echo "</td></tr>";

	@$_Grade_A1="A1";
	@$_Grade_B2="B2";
	@$_Grade_B3="B3";
	@$_Grade_C4="C4";
	@$_Grade_C5="C5";
	@$_Grade_C6="C6";
	@$_Grade_D7="D7";
	@$_Grade_E8="E8";
	@$_Grade_F9="F9";

	@$_CountA1=0;
	@$_CountB2=0;
	@$_CountB3=0;
	@$_CountC4=0;
	@$_CountC5=0;
	@$_CountC6=0;
	@$_CountD7=0;
	@$_CountE8=0;
	@$_CountF9=0;

$_SQL_STU=mysqli_query($con,"SELECT su.userid FROM tbltermregistry tr 
	INNER JOIN tblsystemuser su ON tr.userid=su.userid
	WHERE tr.batchid='$_Batch_ID' AND tr.class_entryid='$_Class_ID' AND su.systemtype='Student'
	GROUP BY su.userid ORDER BY su.userid ASC");
while($row_stu=mysqli_fetch_array($_SQL_STU,MYSQLI_ASSOC))
{
	@$_TotalMark=0;		
	@$_EntryCount=0;
		$_SQL_EXECUTE=mysqli_query($con,"SELECT SUM(mk.mark) AS TotalMark,COUNT(mk.mark) AS EntryCount FROM tblmark mk 
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		WHERE mk.userid='$row_stu[userid]' AND sc.subjectid='$row_us[subjectid]' 
		AND sc.classid='$_Class_ID' AND sa.batchid='$_Batch_ID'");
	if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
		$_TotalMark=(int)$row['TotalMark'];
		$_EntryCount=(int)$row['EntryCount'];
	}
	if($_EntryCount==0){
		continue;
	}

		///Get the grade
		@$_final_grade=0;

		$_grade_obj->setMark($_TotalMark);
		$_final_grade=$_grade_obj->getMark($_TotalMark);

		if($_Grade_A1==$_final_grade){
			$_CountA1=$_CountA1+1;
		}
		elseif($_Grade_B2==$_final_grade){
			$_CountB2=$_CountB2+1;
		}
		elseif($_Grade_B3==$_final_grade){
			$_CountB3=$_CountB3+1;
		}
		elseif($_Grade_C4==$_final_grade){
			$_CountC4=$_CountC4+1;
		}
		elseif($_Grade_C5==$_final_grade){
			$_CountC5=$_CountC5+1;
			
		}
		elseif($_Grade_C6==$_final_grade){
			$_CountC6=$_CountC6+1;

		}
		elseif($_Grade_D7==$_final_grade){
			$_CountD7=$_CountD7+1;
		}
		elseif($_Grade_E8==$_final_grade){
			$_CountE8=$_CountE8+1;
		}
		elseif($_Grade_F9==$_final_grade){
			$_CountF9=$_CountF9+1;
		}
	//}

	}
$_TotalGrade=$_CountA1+$_CountB2+$_CountB3+$_CountC4+$_CountC5+$_CountC6+$_CountD7+$_CountE8+$_CountF9;
echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_A1;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountA1;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_B2;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountB2;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_B3;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountB3;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_C4;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountC4;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_C5;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountC5;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_C6;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountC6;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_D7;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountD7;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_E8;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountE8;
	echo "</td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td align='center'>";
	echo $_Grade_F9;
	echo "</td>";
	echo "<td align='center' >";
	echo $_CountF9;
	echo "</td>";
	echo "</tr>";

echo "<tr style='background-color:#eeeeee;color:darkblue'>";
echo "<td colspan='1' align='right'>";
echo "TOTAL STUDENTS:";
echo "</td>";
echo "<td>";
echo $_TotalGrade;
echo "</td>";
echo "</tr>";

}

	
//}
echo "</tbody>";
echo "</table>";
}
//}
?>
</form>
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

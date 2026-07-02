
<?php
@$_SESSION['Message'] ="";
session_start();
include_once("semester-registry-utils.php");
function insertSemesterData($_UserId,$_Class_Name,$_Semester,$_Batch,$_AcademicYear="")
{

include("dbstring.php");
semester_registry_ensure_academic_year_column($con);

//Declaration of variables

@$_Class_EntryId="";
@$_BatchId="";
$_AcademicYear = semester_registry_normalize_year($_AcademicYear);
if($_AcademicYear===""){
$_AcademicYear = date("Y");
}


if($_UserId=="userid")
{}
else
{


$sql=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_name='$_Class_Name'");
if($rowc=mysqli_fetch_array($sql,MYSQLI_ASSOC))
{
$_Class_EntryId=$rowc["class_entryid"];
}

$sqlb=mysqli_query($con,"SELECT * FROM tblbatch WHERE batch='$_Batch'");
if($rowcb=mysqli_fetch_array($sqlb,MYSQLI_ASSOC))
{
$_BatchId=$rowcb["batchid"];
}
include("code.php");
$_SemesterId=$code;

$_AcademicYearSafe = mysqli_real_escape_string($con, $_AcademicYear);
$resolvedYearSql = semester_registry_resolved_year_sql("tr");
$sql="SELECT * FROM tblsystemuser su INNER JOIN tbltermregistry tr 
ON su.userid=tr.userid WHERE su.userid='$_UserId' AND tr.class_entryid='$_Class_EntryId' AND tr.batchid='$_BatchId' AND tr.termname='$_Semester' AND $resolvedYearSql='$_AcademicYearSafe'";
$result = mysqli_query($con,$sql);
$count = mysqli_num_rows($result);

if($count>0){
	$_SESSION['Message'] =$_SESSION['Message'] ."<div style='background-color:white;color:red;' align='center'>Semester information already created for academic year $_AcademicYear.</div><br>";
	}
	else
	{
	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tbltermregistry(termid,userid,class_entryid,termname,batchid,academicyear,status,datetimeentry,recordedby)
			VALUES('$_SemesterId','$_UserId','$_Class_EntryId','$_Semester','$_BatchId','$_AcademicYearSafe','active',NOW(),'$_SESSION[USERID]')");

		if($_SQL_EXECUTE){
		//$_SESSION['Message'] =$_SESSION['Message'] ."<div style='background-color:white;color:green;' align='center'>Semester Successfully Saved </div><br>";
		}
		else{
			$_Error=mysqli_error($con);
			echo "<div style='background-color:white;color:red;' align='center'>Semester failed to save,Error: $_Error </div><br>";
		}
	}
}
}
?>

<?php
require_once 'simplexlsx.class.php';
@$counter=0;
//@$message ="";

if(isset($_POST['submit_group_data']))
{

	@$file = $_FILES['file1']['tmp_name'];
	$xlsx = new SimpleXLSX($file);

	foreach($xlsx->rows() as $field)
	{
		$_UserId = $field[0];
		$_Class_Name = $field[2];
		$_Semester =$field[3];
		$_Batch =$field[4];
		$_AcademicYear = isset($field[5]) ? $field[5] : '';
		
	$counter = $counter + 1;

//echo "User:".$_Batch ."<br/>";

insertSemesterData($_UserId,$_Class_Name,$_Semester,$_Batch,$_AcademicYear);
	}
	
	if($counter>0)
	{
		$_SESSION['Message'] =$_SESSION['Message']."<div style='background-color:white;color:green;' align='center'>Semester Data Successfully Uploaded </div><br>";

	}
	else
	{
		$_SESSION['Message'] =$_SESSION['Message']."<div style='background-color:white;color:red;' align='center'>Semester Data Failed To Upload</div><br>";

	}
}
?>
<?php
echo $_SESSION['Message'];
?>





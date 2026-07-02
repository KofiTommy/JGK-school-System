
<?php
session_start();
include_once("student-index-utils.php");
function insertClassData($_UserId,$_Class_Entry_Id,$_Class_Name,$_Batch_Id){	
include("dbstring.php");
student_index_ensure_schema($con);
//Declaration of variables
//@$_Class_EntryId="";
@$_SESSION['Message'] ="";

if($_UserId=="userid" || $_Class_Name=="class_name")
{}
else{
//$sql=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_name='$_Class_Name'");
//if($rowc=mysqli_fetch_array($sql,MYSQLI_ASSOC))
//{
//$_Class_EntryId=$rowc["class_entryid"];
//}
include("code.php");
$_ClassId=$code;

$sqlg="SELECT * FROM tblsystemuser su INNER JOIN tblclass cl 
ON su.userid=cl.userid WHERE (su.userid='$_UserId' AND cl.class_entryid='$_Class_Entry_Id' AND  cl.batchid='$_Batch_Id')";
$result = mysqli_query($con,$sqlg);
$count = mysqli_num_rows($result);

if($count>0){
	$_SESSION['Message'] =$_SESSION['Message'] ."<div style='background-color:white;color:red;' align='center'>Class Information already created!! </div><br>";
	}
	else{
	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclass(classid,userid,class_entryid,batchid,datetimeentry,recordedby,status)
			VALUES('$_ClassId','$_UserId','$_Class_Entry_Id','$_Batch_Id',NOW(),'$_SESSION[USERID]','active')");

			if($_SQL_EXECUTE){
			$_IndexResult=student_index_assign_for_class($con,$_UserId,$_Class_Entry_Id,$_Batch_Id,$_SESSION['USERID']);
			if(!$_IndexResult["success"]){
				$_SESSION['Message']=$_SESSION['Message']."<div style='background-color:white;color:#8a6d3b;' align='center'>Class saved for ".htmlspecialchars($_UserId, ENT_QUOTES, 'UTF-8').", but school index was not generated.</div><br>";
			}
			//$_SESSION['Message']=$_SESSION['Message'] ."<div style='background-color:white;color:green;' align='center'>Class Successfully Saved </div><br>";
			}
			else{
				$_Error=mysqli_error($con);
				echo "<div style='background-color:white;color:red;' align='center'>Class failed to save,Error: $_Error </div><br>";
		}
	}
}
}
?>

<?php
require_once 'simplexlsx.class.php';
@$counter=0;
@$message ="";

if(isset($_POST['submit_group_data'])){
@$file = $_FILES['file1']['tmp_name'];
$xlsx = new SimpleXLSX($file);
foreach($xlsx->rows() as $field)
{
$_UserId = $field[0];
$_Class_Entry_Id = $field[1];
$_Class_Name = $field[2];
$_Batch_Id=$field[3];
				
$counter = $counter + 1;
insertClassData($_UserId,$_Class_Entry_Id,$_Class_Name,$_Batch_Id);
}
if($counter>0){
$_SESSION['Message'] =$_SESSION['Message']."<div style='background-color:white;color:green;' align='center'>Class Data Successfully Uploaded </div><br>";
}
else{
	$_SESSION['Message'] =$_SESSION['Message']."<div style='background-color:white;color:red;' align='center'>Class Data Failed To Upload</div><br>";
	}
}
?>
<?php
echo $_SESSION['Message'];
?>






<?php
session_start();
?>
<?php
include("dbstring.php");
@$_UserId = $_SESSION['USERID'];
@$_FileName = $_FILES["file1"]["name"];

if(isset($_POST['submit_photo'])){
	$_SQL=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_UserId'");
	if($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
	@$_Old_Filename=$row['filename'];
	}
$sql="UPDATE tblsystemuser SET filename='$_FileName',uploadeddatetime=NOW() WHERE userid='$_UserId'";
$result =mysqli_query($con,$sql);
if($result){
	//Upload the image
	if($_FileName){
		//Remove old picture
		if(file_exists("uploads/".$_Old_Filename)){
			unlink("uploads/".$_Old_Filename);
		}
		if(!file_exists("uploads/".$_Old_Filename)){
		move_uploaded_file($_FILES["file1"]["tmp_name"],"uploads/" . $_FILES["file1"]["name"]);
		}
	}
	if(file_exists("uploads/". $_FILES["file1"]["name"])){
		header("location:uploaduser-image.php");
	}
	else{
		echo "<div style='color:red;text-align:center'>File failed to upload</div>";
	}
}
}
?>

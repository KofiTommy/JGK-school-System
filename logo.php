<?php
@$_Logo="";
include("dbstring.php");
$_SQL_Image=mysqli_query($con,"SELECT * FROM tblbranch WHERE branchid='$_SESSION[BRANCHID]'");
if($rows=mysqli_fetch_array($_SQL_Image,MYSQLI_ASSOC)){
$_Logo=$rows["logo"];
}

/*$_SQL_SubImage=mysqli_query($con,"SELECT * FROM tblsubbranch WHERE subbranchid='$_SESSION[branch]'");
if($rowus=mysqli_fetch_array($_SQL_SubImage,MYSQLI_ASSOC)){
$_Logo=$rowus["logo"];
}*/
?>

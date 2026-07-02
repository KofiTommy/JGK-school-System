<?php
//session_start();

include("dbstring.php");
@$_Username=$_SESSION['USERNAME'];
@$_UserId=$_SESSION['USERID'];
$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_UserId' AND username='$_Username'");
if($_Row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
{
 echo "<div style='color:white'>". $_Row['firstname']." ".$_Row['othernames']." ".$_Row['surname'] ."</div>";
echo "<img src='uploads/$_Row[filename]' style='border-radius:50%' width='15px' height='15px' alt='No Upload' />";
}

?>
<?php
$_Localhost="localhost";
$_Database="u131576917_oiti";
$_User="u131576917_oiti";
$_Password="Akusika.Tom???@@1";
mysqli_report(MYSQLI_REPORT_OFF);
$con=mysqli_connect($_Localhost,$_User,$_Password,$_Database);
if(!$con){
    die("Database connection failed.");
}
mysqli_set_charset($con, "utf8mb4");
mysqli_query($con, "SET SESSION sql_mode=''");

//update tblsystemuser set username=userid where userid=userid
//update tblsystemuser set password=md5(userid) where userid=userid
?>

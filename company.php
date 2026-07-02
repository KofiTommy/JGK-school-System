<?php
include("dbstring.php");

@$_CompanyName="";
@$_Address="";
@$_Location="";
@$_Telephone1="";
@$_Telephone2="";
@$_Logo="";

$sqlx="SELECT cm.fullname, br.address, br.location, br.telephone1, br.telephone2, cm.logo AS company_logo, br.logo AS branch_logo
FROM tblbranch br
INNER JOIN tblcompany cm ON br.companyid=cm.companyid
WHERE br.status='active'";
$resultx = mysqli_query($con,$sqlx);

if($rowx=mysqli_fetch_array($resultx,MYSQLI_ASSOC)){
$_CompanyName = $rowx['fullname'];
$_Address = $rowx['address'];
$_Location = $rowx['location'];
$_Telephone1 = $rowx['telephone1'];
$_Telephone2 = $rowx['telephone2'];
$_Logo = $rowx["company_logo"];
if($_Logo=="" && !empty($rowx["branch_logo"])){
$_Logo = $rowx["branch_logo"];
}
}
//mysqli_close($con);

?>

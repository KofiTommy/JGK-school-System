<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("teacher-billing-utils.php");
ensure_teacher_billing_table($con);
teacher_billing_enforce_page_access($con);

$_ClassID = isset($_GET['search-bill']) ? trim((string)$_GET['search-bill']) : '';
$_BatchID = isset($_GET['batch']) ? trim((string)$_GET['batch']) : '';
$_TeacherUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$_IsTeacherBillingAdmin = teacher_billing_is_admin();

if($_ClassID === ''){
    exit();
}

if(!$_IsTeacherBillingAdmin && !teacher_billing_is_assigned_pair($con, $_TeacherUserId, $_ClassID, $_BatchID)){
    echo "<div style='color:red;padding:8px;background-color:white;'>No billing access for that class and batch.</div>";
    exit();
}

$_ClassIDEsc = mysqli_real_escape_string($con, $_ClassID);
$_BatchFilter = "";
if($_BatchID !== ''){
    $_BatchIDEsc = mysqli_real_escape_string($con, $_BatchID);
    $_BatchFilter = " AND ip.batch='$_BatchIDEsc'";
}

$sql = "SELECT *
    FROM tblitemprice ip
    INNER JOIN tblitem itm ON ip.itemid=itm.itemid
    INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
    LEFT JOIN tblbatch b ON ip.batch=b.batchid
    WHERE ip.class_entryid='$_ClassIDEsc'
      $_BatchFilter
      AND ip.status='active'
      AND itm.status='active'
    ORDER BY ip.term, ip.batch ASC";
$result = mysqli_query($con, $sql);

echo "<table border='1'>";
echo "<thead>";
echo "<th>Class</th><th>Semester</th><th>Batch</th><th>Item</th><th>Amount</th><th>Date/Time</th><th>Status</th>";
echo "</thead>";
echo "<tbody>";
while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
    echo "<tr>";
    echo "<td align='center'>".$row['class_name']."</td>";
    echo "<td align='center'>".$row['term']."</td>";
    echo "<td align='center'>".($row['batch'] !== null ? $row['batch'] : $row['batchid'])."</td>";
    echo "<td align='center'>".$row['itemname']."</td>";
    echo "<td align='center'>".$row['price']."</td>";
    echo "<td align='center'>".$row['datetimeprice']."</td>";
    echo "<td align='center'>".$row['status']."</td>";
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";

mysqli_close($con);
?>

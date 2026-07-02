<?php
session_start();

if (!isset($_SESSION['Message'])) {
    $_SESSION['Message'] = "";
}

$_SESSION['Message'] = "<div style='color:#9a3412;background-color:white;padding:8px;border:1px solid #fdba74;border-radius:8px;'>Rebill Group Students has been removed. Use <strong>Billing Manager</strong> to update class fee prices and <strong>Bill Student</strong> or <strong>Bill Group Students</strong> to create any missing bills.</div>";

header("location:class-billing.php");
exit();
?>

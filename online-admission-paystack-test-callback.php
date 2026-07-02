<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("company.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

if(!online_admission_is_admin()){
    header("location:".online_admission_landing_page());
    exit();
}

function ptc_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$config = online_admission_paystack_config();
$reference = trim((string)(isset($_GET["reference"]) ? $_GET["reference"] : ""));
$errorMessage = "";
$result = false;

if($reference !== "" && online_admission_paystack_is_ready($config)){
    $result = online_admission_paystack_verify($config, $reference, $errorMessage);
}
?>
<!DOCTYPE html>
<html>
<head>
<?php include("title.php"); include("links.php"); ?>
<style>
body{margin:0;background:#eef3f7;color:#16324b;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif}
.ptc-shell{max-width:900px;margin:0 auto;padding:28px 20px 42px}
.ptc-card{background:#fff;border:1px solid #d9e5ef;border-radius:24px;box-shadow:0 20px 42px rgba(10,24,38,.08);padding:24px}
.ptc-card h1{margin:0 0 10px;font-family:Georgia,"Palatino Linotype","Book Antiqua",serif}
.ptc-copy{margin:0 0 16px;color:#61788f;line-height:1.7}
.ptc-link{display:inline-flex;align-items:center;gap:10px;min-height:48px;padding:0 18px;border-radius:16px;text-decoration:none;font-weight:700;background:#edf4fa;color:#16324b;border:1px solid #d6e3ee}
pre{margin:18px 0 0;padding:16px;border-radius:18px;background:#0f172a;color:#e2e8f0;overflow:auto;white-space:pre-wrap;word-break:break-word}
</style>
</head>
<body>
<div class="ptc-shell">
    <div class="ptc-card">
        <h1>Paystack Sandbox Callback</h1>
        <p class="ptc-copy">This page verifies the reference returned by Paystack and shows the raw response for sandbox testing.</p>
        <p class="ptc-copy"><strong>Reference:</strong> <?php echo ptc_esc($reference !== "" ? $reference : "Missing"); ?></p>
        <?php if($reference === ""){ ?>
        <p class="ptc-copy">No Paystack reference was returned.</p>
        <?php }elseif($result === false){ ?>
        <p class="ptc-copy"><?php echo ptc_esc($errorMessage !== "" ? $errorMessage : "Paystack verification could not be completed."); ?></p>
        <?php }else{ ?>
        <pre><?php echo ptc_esc(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        <?php } ?>
        <p style="margin-top:18px"><a href="online-admission-paystack-test.php" class="ptc-link"><i class="fa fa-arrow-left"></i> Back to Paystack Sandbox Tester</a></p>
    </div>
</div>
</body>
</html>

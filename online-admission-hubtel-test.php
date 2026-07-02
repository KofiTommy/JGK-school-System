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

function hat_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function hat_alert($type, $message){
    $class = "hat-alert hat-alert--info";
    if($type === "success"){ $class = "hat-alert hat-alert--success"; }
    elseif($type === "error"){ $class = "hat-alert hat-alert--error"; }
    elseif($type === "warning"){ $class = "hat-alert hat-alert--warning"; }
    return "<div class=\"".$class."\">".hat_esc($message)."</div>";
}

$config = online_admission_hubtel_config();
$isReady = online_admission_hubtel_is_ready($config);
$branchContext = online_admission_default_branch_context($con);
$companyName = isset($_CompanyName) && trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName) : "School Management System";

$form = array(
    "mobile" => "+233241234567",
    "amount" => "1.00",
    "title" => trim((string)$config["title"]) !== "" ? trim((string)$config["title"]) : $companyName." Sandbox Test",
    "description" => trim((string)$config["description"]) !== "" ? trim((string)$config["description"]) : "Sandbox request-money test from the online admission module"
);

$alert = "";
$result = null;
$reference = "";

if(isset($_POST["run_hubtel_test"])){
    foreach($form as $key => $value){
        $form[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
    }

    $errors = array();
    $mobileNumber = online_admission_normalize_mobile_money_number($form["mobile"]);
    if($mobileNumber === false || $mobileNumber === ""){
        $errors[] = "Enter a valid Ghana Mobile Money number in E.164 format or local format.";
    }else{
        $form["mobile"] = $mobileNumber;
    }

    $amount = (float)$form["amount"];
    if($amount <= 0 || $amount > 30000){
        $errors[] = "Enter a test amount between 0.01 and 30000.";
    }
    if($form["title"] === ""){
        $errors[] = "A test title is required.";
    }
    if($form["description"] === ""){
        $errors[] = "A test description is required.";
    }
    if(!$isReady){
        $errors[] = "Hubtel config is incomplete. Add the sandbox Client ID, Client Secret, and request-money URL template first.";
    }

    if(empty($errors)){
        $reference = "HUBTEST-".date("YmdHis")."-".strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $payload = array(
            "amount" => (float)number_format($amount, 2, ".", ""),
            "title" => $form["title"],
            "description" => $form["description"],
            "clientReference" => $reference,
            "callbackUrl" => online_admission_hubtel_callback_url($config),
            "cancellationUrl" => online_admission_hubtel_cancel_url($config, $reference),
            "returnUrl" => online_admission_hubtel_return_url($config, $reference)
        );
        if(trim((string)$config["logo_url"]) !== ""){
            $payload["logo"] = trim((string)$config["logo_url"]);
        }

        $errorMessage = "";
        $response = online_admission_hubtel_request_money($config, $form["mobile"], $payload, $errorMessage);
        if($response === false){
            $alert = hat_alert("error", $errorMessage !== "" ? $errorMessage : "Hubtel did not return a valid sandbox response.");
        }else{
            $result = $response;
            $alert = hat_alert("success", "Hubtel returned a sandbox response successfully.");
        }
    }else{
        $alert = hat_alert("warning", implode(" ", $errors));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<?php include("title.php"); include("links.php"); ?>
<style>
body.hat-page{margin:0;background:#eef3f7;color:#16324b;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif}
.hat-shell{max-width:1080px;margin:0 auto;padding:28px 20px 42px}
.hat-topbar,.hat-card{background:#fff;border:1px solid #d9e5ef;border-radius:24px;box-shadow:0 20px 42px rgba(10,24,38,.08)}
.hat-topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:20px 24px}
.hat-topbar h1,.hat-card h2{margin:0;font-family:Georgia,"Palatino Linotype","Book Antiqua",serif}
.hat-topbar p,.hat-copy,.hat-meta,.hat-field small{margin:0;color:#61788f;line-height:1.65}
.hat-actions{display:flex;flex-wrap:wrap;gap:10px}
.hat-link,.hat-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:48px;padding:0 18px;border-radius:16px;text-decoration:none;font-weight:700}
.hat-link{background:#edf4fa;color:#16324b;border:1px solid #d6e3ee}
.hat-button{border:0;background:linear-gradient(145deg,#14324d,#1f5e8f);color:#fff;cursor:pointer}
.hat-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr);gap:24px;margin-top:24px}
.hat-card{padding:24px}
.hat-form{display:grid;gap:18px}
.hat-field label{display:block;margin-bottom:8px;font-weight:700}
.hat-field input,.hat-field textarea{width:100%;min-height:50px;padding:14px 16px;border-radius:16px;border:1px solid #cfdbe7;background:#fff;color:#16324b;font-size:.98rem}
.hat-field textarea{min-height:110px;resize:vertical}
.hat-field input:focus,.hat-field textarea:focus{outline:none;border-color:#2f7e8d;box-shadow:0 0 0 4px rgba(47,126,141,.12)}
.hat-stack{display:grid;gap:12px}
.hat-meta-grid{display:grid;gap:12px}
.hat-meta-item{padding:16px;border-radius:18px;background:#f7fafc;border:1px solid #dbe6f0}
.hat-meta-item span{display:block;font-size:.76rem;text-transform:uppercase;letter-spacing:.12em;color:#70859a}
.hat-meta-item strong{display:block;margin-top:8px;word-break:break-word}
.hat-alert{padding:15px 18px;border-radius:18px;font-weight:600}
.hat-alert--success{background:#ecfdf3;color:#166534;border:1px solid rgba(22,101,52,.14)}
.hat-alert--error{background:#fef2f2;color:#b91c1c;border:1px solid rgba(185,28,28,.14)}
.hat-alert--warning{background:#fff7ed;color:#c2410c;border:1px solid rgba(194,65,12,.14)}
.hat-alert--info{background:#eff6ff;color:#1d4ed8;border:1px solid rgba(29,78,216,.14)}
.hat-result{margin-top:18px;display:grid;gap:14px}
.hat-result pre{margin:0;padding:16px;border-radius:18px;background:#0f172a;color:#e2e8f0;overflow:auto;white-space:pre-wrap;word-break:break-word}
@media (max-width:900px){.hat-grid{grid-template-columns:1fr}.hat-topbar{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body class="hat-page">
<div class="hat-shell">
    <header class="hat-topbar">
        <div>
            <h1>Hubtel Sandbox Tester</h1>
            <p>Use this page to initialize a request-money payment from inside the project before testing the full admission flow.</p>
        </div>
        <div class="hat-actions">
            <a href="online-admission-admin.php#payment-settings" class="hat-link"><i class="fa fa-arrow-left"></i> Back to Admission Admin</a>
            <a href="online-admission.php" class="hat-link" target="_blank"><i class="fa fa-external-link"></i> Open Public Portal</a>
        </div>
    </header>

    <?php if($alert !== ""){ ?><div style="margin-top:18px"><?php echo $alert; ?></div><?php } ?>

    <div class="hat-grid">
        <section class="hat-card">
            <h2>Run Test Request</h2>
            <p class="hat-copy">This creates a Hubtel paylink using the same helper your admission portal uses. It does not create a student admission application.</p>
            <form method="post" action="online-admission-hubtel-test.php" class="hat-form">
                <div class="hat-field">
                    <label for="mobile">Sandbox Mobile Number</label>
                    <input type="text" id="mobile" name="mobile" value="<?php echo hat_esc($form["mobile"]); ?>" placeholder="+233241234567" required>
                    <small>Use a Ghana MoMo number in E.164 format if possible.</small>
                </div>
                <div class="hat-field">
                    <label for="amount">Amount</label>
                    <input type="text" id="amount" name="amount" value="<?php echo hat_esc($form["amount"]); ?>" placeholder="1.00" required>
                </div>
                <div class="hat-field">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo hat_esc($form["title"]); ?>" required>
                </div>
                <div class="hat-field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?php echo hat_esc($form["description"]); ?></textarea>
                </div>
                <button type="submit" name="run_hubtel_test" class="hat-button"><i class="fa fa-play-circle"></i> Run Hubtel Sandbox Test</button>
            </form>

            <?php if($result){ ?>
            <div class="hat-result">
                <div class="hat-meta-grid">
                    <div class="hat-meta-item"><span>Paylink Id</span><strong><?php echo hat_esc(isset($result["data"]["paylinkId"]) ? $result["data"]["paylinkId"] : ""); ?></strong></div>
                    <div class="hat-meta-item"><span>Client Reference</span><strong><?php echo hat_esc(isset($result["data"]["clientReference"]) ? $result["data"]["clientReference"] : $reference); ?></strong></div>
                    <div class="hat-meta-item"><span>Paylink URL</span><strong><a href="<?php echo hat_esc(isset($result["data"]["paylinkUrl"]) ? $result["data"]["paylinkUrl"] : "#"); ?>" target="_blank"><?php echo hat_esc(isset($result["data"]["paylinkUrl"]) ? $result["data"]["paylinkUrl"] : ""); ?></a></strong></div>
                    <div class="hat-meta-item"><span>Gateway Message</span><strong><?php echo hat_esc(isset($result["message"]) ? $result["message"] : ""); ?></strong></div>
                </div>
                <pre><?php echo hat_esc(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            </div>
            <?php } ?>
        </section>

        <aside class="hat-card">
            <h2>Current Config</h2>
            <div class="hat-stack">
                <div class="hat-meta-item"><span>Client ID</span><strong><?php echo hat_esc(trim((string)$config["client_id"]) !== "" ? "Set" : "Missing"); ?></strong></div>
                <div class="hat-meta-item"><span>Client Secret</span><strong><?php echo hat_esc(trim((string)$config["client_secret"]) !== "" ? "Set" : "Missing"); ?></strong></div>
                <div class="hat-meta-item"><span>Request Money URL Template</span><strong><?php echo hat_esc(trim((string)$config["request_money_url_template"]) !== "" ? $config["request_money_url_template"] : "Missing"); ?></strong></div>
                <div class="hat-meta-item"><span>Callback URL</span><strong><?php echo hat_esc(online_admission_hubtel_callback_url($config)); ?></strong></div>
                <div class="hat-meta-item"><span>Return URL</span><strong><?php echo hat_esc(online_admission_hubtel_return_url($config, "HUBTEST-DEMO")); ?></strong></div>
                <div class="hat-meta-item"><span>Cancel URL</span><strong><?php echo hat_esc(online_admission_hubtel_cancel_url($config, "HUBTEST-DEMO")); ?></strong></div>
                <div class="hat-meta-item"><span>Branch</span><strong><?php echo hat_esc($branchContext["location"]); ?></strong></div>
                <div class="hat-meta-item"><span>Readiness</span><strong><?php echo hat_esc($isReady ? "Ready for remote test" : "Config incomplete"); ?></strong></div>
            </div>
            <p class="hat-copy" style="margin-top:16px">For a full end-to-end sandbox test, your Hubtel callback, return, and cancellation URLs should be publicly reachable from Hubtel.</p>
        </aside>
    </div>
</div>
</body>
</html>

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

function pat_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function pat_alert($type, $message){
    $class = "pat-alert pat-alert--info";
    if($type === "success"){ $class = "pat-alert pat-alert--success"; }
    elseif($type === "error"){ $class = "pat-alert pat-alert--error"; }
    elseif($type === "warning"){ $class = "pat-alert pat-alert--warning"; }
    return "<div class=\"".$class."\">".pat_esc($message)."</div>";
}

$config = online_admission_paystack_config();
$isReady = online_admission_paystack_is_ready($config);
$publicKey = trim((string)(isset($config["public_key"]) ? $config["public_key"] : ""));
$secretKey = trim((string)(isset($config["secret_key"]) ? $config["secret_key"] : ""));
$isTestMode = strpos($publicKey, "pk_test_") === 0 && strpos($secretKey, "sk_test_") === 0;
$companyName = isset($_CompanyName) && trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName) : "School Management System";

$form = array(
    "email" => "sandbox@test.com",
    "amount" => "1.00",
    "currency" => "GHS",
    "name" => $companyName." Sandbox Test"
);

$alert = "";
$result = null;
$reference = "";

if(isset($_POST["run_paystack_test"])){
    foreach($form as $key => $value){
        $form[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
    }

    $errors = array();
    if(!filter_var($form["email"], FILTER_VALIDATE_EMAIL)){
        $errors[] = "Enter a valid test email address.";
    }
    $amount = (float)$form["amount"];
    if($amount <= 0){
        $errors[] = "Enter a test amount greater than zero.";
    }
    if($form["currency"] === ""){
        $form["currency"] = "GHS";
    }
    if(!$isReady){
        $errors[] = "Paystack is not configured yet. Add your test secret key first.";
    }

    if(empty($errors)){
        $reference = "PSTEST-".date("YmdHis")."-".strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $payload = array(
            "reference" => $reference,
            "email" => $form["email"],
            "amount" => online_admission_money_minor_units($amount),
            "currency" => strtoupper($form["currency"]),
            "callback_url" => online_admission_app_url("online-admission-paystack-test-callback.php"),
            "metadata" => array(
                "test_name" => $form["name"],
                "test_reference" => $reference
            )
        );

        $errorMessage = "";
        $response = online_admission_paystack_initialize($config, $payload, $errorMessage);
        if($response === false){
            $alert = pat_alert("error", $errorMessage !== "" ? $errorMessage : "Paystack did not return a valid sandbox response.");
        }else{
            $result = $response;
            $alert = pat_alert("success", "Paystack returned a sandbox response successfully.");
        }
    }else{
        $alert = pat_alert("warning", implode(" ", $errors));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<?php include("title.php"); include("links.php"); ?>
<style>
body.pat-page{margin:0;background:#eef3f7;color:#16324b;font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif}
.pat-shell{max-width:1080px;margin:0 auto;padding:28px 20px 42px}
.pat-topbar,.pat-card{background:#fff;border:1px solid #d9e5ef;border-radius:24px;box-shadow:0 20px 42px rgba(10,24,38,.08)}
.pat-topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:20px 24px}
.pat-topbar h1,.pat-card h2{margin:0;font-family:Georgia,"Palatino Linotype","Book Antiqua",serif}
.pat-topbar p,.pat-copy,.pat-meta,.pat-field small{margin:0;color:#61788f;line-height:1.65}
.pat-actions{display:flex;flex-wrap:wrap;gap:10px}
.pat-link,.pat-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:48px;padding:0 18px;border-radius:16px;text-decoration:none;font-weight:700}
.pat-link{background:#edf4fa;color:#16324b;border:1px solid #d6e3ee}
.pat-button{border:0;background:linear-gradient(145deg,#14324d,#1f5e8f);color:#fff;cursor:pointer}
.pat-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr);gap:24px;margin-top:24px}
.pat-card{padding:24px}
.pat-form{display:grid;gap:18px}
.pat-field label{display:block;margin-bottom:8px;font-weight:700}
.pat-field input{width:100%;min-height:50px;padding:14px 16px;border-radius:16px;border:1px solid #cfdbe7;background:#fff;color:#16324b;font-size:.98rem}
.pat-field input:focus{outline:none;border-color:#2f7e8d;box-shadow:0 0 0 4px rgba(47,126,141,.12)}
.pat-stack{display:grid;gap:12px}
.pat-meta-grid{display:grid;gap:12px}
.pat-meta-item{padding:16px;border-radius:18px;background:#f7fafc;border:1px solid #dbe6f0}
.pat-meta-item span{display:block;font-size:.76rem;text-transform:uppercase;letter-spacing:.12em;color:#70859a}
.pat-meta-item strong{display:block;margin-top:8px;word-break:break-word}
.pat-alert{padding:15px 18px;border-radius:18px;font-weight:600}
.pat-alert--success{background:#ecfdf3;color:#166534;border:1px solid rgba(22,101,52,.14)}
.pat-alert--error{background:#fef2f2;color:#b91c1c;border:1px solid rgba(185,28,28,.14)}
.pat-alert--warning{background:#fff7ed;color:#c2410c;border:1px solid rgba(194,65,12,.14)}
.pat-alert--info{background:#eff6ff;color:#1d4ed8;border:1px solid rgba(29,78,216,.14)}
.pat-result{margin-top:18px;display:grid;gap:14px}
.pat-result pre{margin:0;padding:16px;border-radius:18px;background:#0f172a;color:#e2e8f0;overflow:auto;white-space:pre-wrap;word-break:break-word}
.pat-list{margin:0;padding-left:18px;color:#4e6479;line-height:1.8}
@media (max-width:900px){.pat-grid{grid-template-columns:1fr}.pat-topbar{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body class="pat-page">
<div class="pat-shell">
    <header class="pat-topbar">
        <div>
            <h1>Paystack Sandbox Tester</h1>
            <p>Use this page to initialize a Paystack test transaction from inside the project before testing the full admission flow.</p>
        </div>
        <div class="pat-actions">
            <a href="online-admission-admin.php#payment-settings" class="pat-link"><i class="fa fa-arrow-left"></i> Back to Admission Admin</a>
            <a href="online-admission.php" class="pat-link" target="_blank"><i class="fa fa-external-link"></i> Open Public Portal</a>
        </div>
    </header>

    <?php if($alert !== ""){ ?><div style="margin-top:18px"><?php echo $alert; ?></div><?php } ?>

    <div class="pat-grid">
        <section class="pat-card">
            <h2>Run Sandbox Transaction</h2>
            <p class="pat-copy">This initializes a Paystack transaction using your current test secret key and returns the hosted checkout URL.</p>
            <form method="post" action="online-admission-paystack-test.php" class="pat-form">
                <div class="pat-field">
                    <label for="email">Test Email</label>
                    <input type="email" id="email" name="email" value="<?php echo pat_esc($form["email"]); ?>" required>
                </div>
                <div class="pat-field">
                    <label for="amount">Amount</label>
                    <input type="text" id="amount" name="amount" value="<?php echo pat_esc($form["amount"]); ?>" placeholder="1.00" required>
                </div>
                <div class="pat-field">
                    <label for="currency">Currency</label>
                    <input type="text" id="currency" name="currency" value="<?php echo pat_esc($form["currency"]); ?>" required>
                </div>
                <div class="pat-field">
                    <label for="name">Label</label>
                    <input type="text" id="name" name="name" value="<?php echo pat_esc($form["name"]); ?>" required>
                </div>
                <button type="submit" name="run_paystack_test" class="pat-button"><i class="fa fa-play-circle"></i> Run Paystack Sandbox Test</button>
            </form>

            <?php if($result){ ?>
            <div class="pat-result">
                <div class="pat-meta-grid">
                    <div class="pat-meta-item"><span>Reference</span><strong><?php echo pat_esc(isset($result["data"]["reference"]) ? $result["data"]["reference"] : $reference); ?></strong></div>
                    <div class="pat-meta-item"><span>Access Code</span><strong><?php echo pat_esc(isset($result["data"]["access_code"]) ? $result["data"]["access_code"] : ""); ?></strong></div>
                    <div class="pat-meta-item"><span>Checkout URL</span><strong><a href="<?php echo pat_esc(isset($result["data"]["authorization_url"]) ? $result["data"]["authorization_url"] : "#"); ?>" target="_blank"><?php echo pat_esc(isset($result["data"]["authorization_url"]) ? $result["data"]["authorization_url"] : ""); ?></a></strong></div>
                    <div class="pat-meta-item"><span>Gateway Message</span><strong><?php echo pat_esc(isset($result["message"]) ? $result["message"] : ""); ?></strong></div>
                </div>
                <pre><?php echo pat_esc(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            </div>
            <?php } ?>
        </section>

        <aside class="pat-card">
            <h2>Current Config</h2>
            <div class="pat-stack">
                <div class="pat-meta-item"><span>Public Key</span><strong><?php echo pat_esc($publicKey !== "" ? $publicKey : "Missing"); ?></strong></div>
                <div class="pat-meta-item"><span>Secret Key</span><strong><?php echo pat_esc($secretKey !== "" ? "Set" : "Missing"); ?></strong></div>
                <div class="pat-meta-item"><span>Mode</span><strong><?php echo pat_esc($isTestMode ? "Test mode" : ($isReady ? "Live key detected" : "Config incomplete")); ?></strong></div>
                <div class="pat-meta-item"><span>Callback URL</span><strong><?php echo pat_esc(online_admission_payment_callback_url($config, "callback_path", "online-admission-paystack-callback.php")); ?></strong></div>
            </div>

            <h2 style="margin-top:22px">Quick Test Data</h2>
            <ul class="pat-list">
                <li>Use Paystack test keys on this page, not live keys.</li>
                <li>After initialization, open the checkout URL and use Paystack’s published test payment details there.</li>
                <li>If you want to test the full admission flow, use the public online admission page after adding the same test keys.</li>
            </ul>
        </aside>
    </div>
</div>
</body>
</html>

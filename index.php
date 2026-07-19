<?php
session_start();
if (!isset($_SESSION['SESSION_INIT'])) {
    session_regenerate_id(true);
    $_SESSION['SESSION_INIT'] = 1;
}
?>
<?php
$_SESSION['Message']="";
$_SESSION['USERID']="";
$_SESSION['USERNAME']="";
$_SESSION['CURRENCY']="";
$_SESSION['SYMBOL']="";
$_SESSION['ACCESSLEVEL']="";
$_SESSION['SYSTEMTYPE']="";
$_SESSION['BRANCHID']="";
$_SESSION["AUDITDATE"]="";
$_SESSION["SCHOOLACCOUNT"]="12311";
$_SESSION["PAYMENTACCOUNT"]="12314";



include("deviceinformation.php");
@$obj_device=new DeviceInformation();
$obj_device->setIPaddr(1);
@$IPAddress=$obj_device->getIPaddr();
@$obj_os=new DeviceInformation();
$obj_os->setOS(1);
@$OS=$obj_os->getOS();
@$obj_browser=new DeviceInformation();
$obj_browser->setBrowser(1);
@$_Browser=$obj_browser->getBrowser();
@$_DeviceInfo="IP:".$IPAddress.", OS:".$OS.", Browser:".$_Browser;
?>

<?php
include("dbstring.php");
include_once(__DIR__.DIRECTORY_SEPARATOR."api-auth-utils.php");
include_once("online-admission-utils.php");
include_once(__DIR__.DIRECTORY_SEPARATOR."portal-help-utils.php");
include_once("user-management-utils.php");
ensure_online_admission_tables($con);
ensure_user_management_columns($con);
ensure_api_auth_record($con);

$_PublicAdmissionOpen=false;
$_PublicAdmissionPaymentEnabled=false;
$__AdmissionBranchContext=online_admission_default_branch_context($con);
if(trim((string)$__AdmissionBranchContext["branchid"]) !== ""){
    $__AdmissionSetting=online_admission_get_payment_setting($con, $__AdmissionBranchContext["branchid"]);
    $_PublicAdmissionOpen=online_admission_portal_is_open($__AdmissionSetting);
    $_PublicAdmissionPaymentEnabled=((int)$__AdmissionSetting["enabled"] === 1 && (float)$__AdmissionSetting["feeamount"] > 0);
}

$_LandingHelpDefaultRole=$_PublicAdmissionOpen ? "applicant" : "visitor";
$_LandingHelpDefaultTopic=$_PublicAdmissionOpen ? "admission" : "login";
$_LandingHelpFormState=array(
    "requestername" => "",
    "requesterrole" => $_LandingHelpDefaultRole,
    "contactphone" => "",
    "contactemail" => "",
    "helptopic" => $_LandingHelpDefaultTopic,
    "helpmessage" => ""
);
$_LandingHelpFlash="";
$_LandingHelpFlashClass="";
$_LandingHelpOpen=false;

if(isset($_POST["send_portal_help_request"])){
    $_LandingHelpOpen=true;
    $_LandingHelpFormState["requestername"]=substr(trim((string)(isset($_POST["requestername"]) ? $_POST["requestername"] : "")),0,150);
    $_LandingHelpFormState["requesterrole"]=portal_help_normalize_role(isset($_POST["requesterrole"]) ? $_POST["requesterrole"] : $_LandingHelpDefaultRole);
    $_LandingHelpFormState["contactphone"]=substr(trim((string)(isset($_POST["contactphone"]) ? $_POST["contactphone"] : "")),0,40);
    $_LandingHelpFormState["contactemail"]=substr(strtolower(trim((string)(isset($_POST["contactemail"]) ? $_POST["contactemail"] : ""))),0,120);
    $_LandingHelpFormState["helptopic"]=portal_help_normalize_topic(isset($_POST["helptopic"]) ? $_POST["helptopic"] : $_LandingHelpDefaultTopic);
    $_LandingHelpFormState["helpmessage"]=substr(trim((string)(isset($_POST["helpmessage"]) ? $_POST["helpmessage"] : "")),0,2000);

    $_LandingHelpErrors=array();
    if($_LandingHelpFormState["requestername"]===""){
        $_LandingHelpErrors[]="Enter your name so the school can identify your request.";
    }
    if($_LandingHelpFormState["contactphone"]==="" && $_LandingHelpFormState["contactemail"]===""){
        $_LandingHelpErrors[]="Add a phone number or email address for follow-up.";
    }
    if($_LandingHelpFormState["contactemail"]!=="" && !filter_var($_LandingHelpFormState["contactemail"], FILTER_VALIDATE_EMAIL)){
        $_LandingHelpErrors[]="Enter a valid email address.";
    }
    if(strlen(trim($_LandingHelpFormState["helpmessage"]))<10){
        $_LandingHelpErrors[]="Describe the problem in a little more detail.";
    }

    if(count($_LandingHelpErrors)===0){
        $_LandingHelpRequestId=portal_help_create_request($con, array(
            "requestername" => $_LandingHelpFormState["requestername"],
            "requesterrole" => $_LandingHelpFormState["requesterrole"],
            "contactphone" => $_LandingHelpFormState["contactphone"],
            "contactemail" => $_LandingHelpFormState["contactemail"],
            "helptopic" => $_LandingHelpFormState["helptopic"],
            "helpmessage" => $_LandingHelpFormState["helpmessage"],
            "sourcepage" => "index.php",
            "ipaddress" => substr((string)(isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : ""),0,64),
            "useragent" => substr((string)(isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : ""),0,255),
            "branchid" => trim((string)(isset($__AdmissionBranchContext["branchid"]) ? $__AdmissionBranchContext["branchid"] : ""))
        ));
        if($_LandingHelpRequestId){
            $_LandingHelpFlash="Help request sent successfully. Reference: ".$_LandingHelpRequestId.".";
            $_LandingHelpFlashClass="landing-help-flash landing-help-flash--success";
            $_LandingHelpFormState=array(
                "requestername" => "",
                "requesterrole" => $_LandingHelpDefaultRole,
                "contactphone" => "",
                "contactemail" => "",
                "helptopic" => $_LandingHelpDefaultTopic,
                "helpmessage" => ""
            );
        }
        else{
            $_LandingHelpFlash="We could not send your help request right now. Please try again or use the phone and WhatsApp options.";
            $_LandingHelpFlashClass="landing-help-flash landing-help-flash--error";
        }
    }
    else{
        $_LandingHelpFlash=implode(" ", $_LandingHelpErrors);
        $_LandingHelpFlashClass="landing-help-flash landing-help-flash--error";
    }
}

$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblcurrency");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
$_SESSION['CURRENCY']=$row_item_2['currencyname'];
$_SESSION['SYMBOL']=$row_item_2['symbol'];
}

if(isset($_POST["login"])){
@$_Username=$_POST["username"];
@$_Password=md5($_POST["password"]);
@$_User =strtolower($_POST["user"]);

$_SQL_EXECUTE=false;
$stmt_login=mysqli_prepare($con,"SELECT *
FROM tblsystemuser su
INNER JOIN tblbranch br ON su.branchid=br.branchid
WHERE (su.userid=? OR su.username=?)
  AND su.password=?
ORDER BY CASE
    WHEN su.userid=? THEN 0
    WHEN su.username=? THEN 1
    ELSE 2
END,
su.registereddatetime DESC
LIMIT 1");
if($stmt_login){
mysqli_stmt_bind_param($stmt_login,"sssss",$_Username,$_Username,$_Password,$_Username,$_Username);
mysqli_stmt_execute($stmt_login);
$_SQL_EXECUTE=mysqli_stmt_get_result($stmt_login);
}

//$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.username='$_Username' AND su.password='$_Password'");
	if($_SQL_EXECUTE && mysqli_num_rows($_SQL_EXECUTE)>0){
		if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
			@$_AccessLevel=$row['accesslevel'];
			@$_SystemType=$row['systemtype'];
			$_SESSION['USERID']=$row['userid'];
			$_SESSION['USERNAME']=$row['username'];
			@$_Userfullname=$row['firstname']." ".$row['othernames']." ".$row['surname'];
			$_SESSION['FULLNAME']=$_Userfullname;
			$_SESSION['ACCESSLEVEL']=$row['accesslevel'];
			$_SESSION['SYSTEMTYPE']=$row['systemtype'];
			$_SESSION['BRANCHID']=$row['branchid'];
			$_SESSION['COMPANY']=$row['companyid'];


//Get the Audit Date
$_SESSION["AUDITDATE"]="";

$_SQLAD=mysqli_query($con,"SELECT ad.auditdate
FROM tblauditdate ad
WHERE ad.auditdate >= CURDATE()
AND ad.auditdate < (CURDATE() + INTERVAL 1 DAY)
AND ad.status='active'
LIMIT 1");
if($_SQLAD && ($rowad=mysqli_fetch_array($_SQLAD,MYSQLI_ASSOC))){
		  $_SESSION["AUDITDATE"]=$rowad["auditdate"];
}
else{
		   //CREATE AUDIT DATE
		  include("code.php");
		  @$_AuditId=$code;
		  $_SQLAD=mysqli_query($con,"INSERT INTO tblauditdate(auditid,auditdate,status,deviceinformation,recordedby,branchid)
		  VALUES('$_AuditId',NOW(),'active','$_DeviceInfo','$_SESSION[USERID]','$_SESSION[BRANCHID]')");
		  if($_SQLAD)
			  {
			    $_SQLU=mysqli_query($con,"UPDATE tblauditdate ad SET ad.status='sealed' WHERE ad.status='active' AND date_format(ad.auditdate,'%d-%m-%Y')<>date_format(NOW(),'%d-%m-%Y')");
				    if($_SQLU){
				    	//SET GLOBAL event_scheduler="ON" 
				    	$_SET_Global=mysqli_query($con,"SET GLOBAL event_scheduler='ON'");
				    	if($_SET_Global){/*Global event put on*/}

				    }
		  }

		  $_SQLAD=mysqli_query($con,"SELECT ad.auditdate
		  FROM tblauditdate ad
		  WHERE ad.auditdate >= CURDATE()
		  AND ad.auditdate < (CURDATE() + INTERVAL 1 DAY)
		  AND ad.status='active'
		  LIMIT 1");
		  if($rowad=mysqli_fetch_array($_SQLAD,MYSQLI_ASSOC)){
		  $_SESSION["AUDITDATE"]=$rowad["auditdate"];
		  }
		 
	}
			if($row['status']=="block"){
			$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;text-transform:blink'>Account is blocked!! Please contact administrator</div>";
			}else{
				if(isset($row['password_reset_required']) && (int)$row['password_reset_required'] === 1){
					header("location:change-password.php?force=1");
					exit();
				}
				if($_AccessLevel=="administrator" && $_SystemType=="super_user"){
					header("location:super.php");
				}
				elseif($_AccessLevel=="administrator" && $_SystemType=="normal_user"){
					//header("location:admin.php");
					header("location:select-branch.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Student"){
					header("location:student-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Teacher"){
					header("location:teacher-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Headmaster"){
					header("location:headmaster-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="AssistantHeadAcademic"){
					header("location:assistant-head-academics-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="User"){
					header("location:user.php");
				}	
			}
		}
	}
	else
	{
		$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;'>Failed to login. Try again!!</div>";
	}
if(isset($stmt_login) && $stmt_login){
mysqli_stmt_close($stmt_login);
}
}

if(!function_exists('landing_index_safe')){
function landing_index_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}
?>

<html>
<head>

<?php
include("title.php");
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/index-landing.css">
<?php
include("validation/header.php");
?>
</head>
<?php
include("backgroundphoto.php");
$_LandingBackgroundPhoto = htmlspecialchars((string)$_BackgroundPhoto, ENT_QUOTES, "UTF-8");
$_LandingHelpLine = trim((string)(isset($_Telephone1) ? $_Telephone1 : ""));
if($_LandingHelpLine === ""){
    $_LandingHelpLine = "+233(0)243317232";
}
$_LandingSchoolName = trim((string)(isset($_CompanyName) ? $_CompanyName : ""));
if($_LandingSchoolName === ""){
    $_LandingSchoolName = "Live Campus";
}
$_LandingSchoolNameSafe = htmlspecialchars($_LandingSchoolName, ENT_QUOTES, "UTF-8");
$_LandingTiktokLabel = "JG Knol Technical";
$_LandingTiktokUrl = "https://www.tiktok.com/search?q=".rawurlencode($_LandingTiktokLabel);
$_LandingWhatsappNumber = "+233243317232";
$_LandingWhatsappUrl = "https://wa.me/233243317232?text=".rawurlencode("Hello, I need help with admission.");
$_LandingPhoneHref = preg_replace('/[^0-9+]/', '', $_LandingHelpLine);
if($_LandingPhoneHref !== ""){
    $_LandingPhoneHref = "tel:".$_LandingPhoneHref;
}
$_LandingQuickActionHref = $_PublicAdmissionOpen ? "online-admission.php" : "#portal-login";
$_LandingQuickActionLabel = $_PublicAdmissionOpen ? "Admission" : "Login";
$_LandingQuickActionIcon = $_PublicAdmissionOpen ? "fa-arrow-right" : "fa-sign-in";
$_LandingLogoHref = "images/nexgen-logo.png";
if(isset($_Logo) && trim((string)$_Logo) !== ""){
    $__LandingLogoFile = trim((string)$_Logo);
    $__LandingLogoCandidates = array(
        "images/logo/".$__LandingLogoFile,
        "logo/".$__LandingLogoFile,
        $__LandingLogoFile,
    );
    foreach($__LandingLogoCandidates as $__LandingLogoCandidate){
        if($__LandingLogoCandidate !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR.str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $__LandingLogoCandidate))){
            $_LandingLogoHref = str_replace("\\", "/", $__LandingLogoCandidate);
            break;
        }
    }
}
?>
<body class="landing-page<?php echo $_LandingHelpOpen ? " landing-help-open" : ""; ?>" style="--landing-photo:url('images/logo/<?php echo $_LandingBackgroundPhoto; ?>');">
<div class="landing-shell">
    <div class="landing-currents" aria-hidden="true">
        <span class="landing-current landing-current--one"></span>
        <span class="landing-current landing-current--two"></span>
        <span class="landing-current landing-current--three"></span>
        <span class="landing-current landing-current--four"></span>
        <span class="landing-beam landing-beam--one"></span>
        <span class="landing-beam landing-beam--two"></span>
    </div>
    <div class="landing-orb landing-orb-a"></div>
    <div class="landing-orb landing-orb-b"></div>
    <div class="landing-orb landing-orb-c"></div>

    <header class="landing-topbar">
        <div class="landing-brand">
            <div class="landing-brand__mark">
                <img src="<?php echo htmlspecialchars($_LandingLogoHref, ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo $_LandingSchoolNameSafe; ?>">
            </div>
            <div class="landing-brand__text">
                <span class="landing-kicker">School Portal</span>
                <h2><?php echo $_LandingSchoolNameSafe; ?></h2>
            </div>
        </div>
        <div class="landing-topbar__meta">
            <?php if($_PublicAdmissionOpen){ ?>
            <div class="landing-chip landing-chip--accent"><i class="fa fa-check-circle"></i> Online Admission Open</div>
            <?php } ?>
            <div class="landing-chip"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($_LandingHelpLine, ENT_QUOTES, "UTF-8"); ?></div>
        </div>
    </header>

    <main class="landing-grid">
        <section class="landing-hero">
            <div class="landing-hero__watermark" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($_LandingLogoHref, ENT_QUOTES, "UTF-8"); ?>" alt="">
            </div>
            <div class="landing-copy">
                <span class="landing-eyebrow">Mobile-First Access</span>
                <h1>Welcome to <?php echo $_LandingSchoolNameSafe ; ?> Student Management System.</h1>
                <p><?php echo $_PublicAdmissionOpen ? "New students should use start admission. Existing users should use portal login." : "Sign in to continue."; ?></p>
            </div>

            <div class="landing-route-grid">
                <?php if($_PublicAdmissionOpen){ ?>
                <article class="landing-route-card landing-route-card--admission">
                    <div class="landing-route-card__head">
                        <span class="landing-route-card__badge">New Student Admission</span>
                        <h3>Start admission</h3>
                    </div>
                    <div class="landing-step-grid">
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa fa-check-circle"></i></span>
                            <span class="landing-step__copy">
                                <strong>Verify Posting</strong>
                                <small>Step 1</small>
                            </span>
                        </article>
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa <?php echo $_PublicAdmissionPaymentEnabled ? "fa-credit-card" : "fa-file-text-o"; ?>"></i></span>
                            <span class="landing-step__copy">
                                <strong><?php echo $_PublicAdmissionPaymentEnabled ? "Pay" : "Open Form"; ?></strong>
                                <small>Step 2</small>
                            </span>
                        </article>
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa <?php echo $_PublicAdmissionPaymentEnabled ? "fa-key" : "fa-folder-open-o"; ?>"></i></span>
                            <span class="landing-step__copy">
                                <strong><?php echo $_PublicAdmissionPaymentEnabled ? "Log In With Token" : "Resume Form"; ?></strong>
                                <small>Step 3</small>
                            </span>
                        </article>
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa fa-paper-plane"></i></span>
                            <span class="landing-step__copy">
                                <strong>Fill And Submit Form</strong>
                                <small>Step 4</small>
                            </span>
                        </article>
                    </div>
                    <div class="landing-admission-actions">
                        <a class="landing-admission-link" href="online-admission.php">
                            <i class="fa fa-arrow-right"></i> Start Online Admission
                        </a>
                        <a class="landing-admission-link landing-admission-link--ghost" href="online-admission.php?resume_admission=1">
                            <i class="fa fa-unlock-alt"></i> Continue With Token
                        </a>
                    </div>
                </article>
                <?php } ?>

                <article class="landing-route-card landing-route-card--login">
                    <div class="landing-route-card__head">
                        <span class="landing-route-card__badge landing-route-card__badge--soft">Existing Users</span>
                        <h3>Portal login</h3>
                    </div>
                    <div class="landing-role-row">
                        <span>Admin</span>
                        <span>Teacher</span>
                        <span>Student</span>
                        <span>Staff</span>
                    </div>
                    <a class="landing-admission-link landing-admission-link--ghost" href="#portal-login">
                        <i class="fa fa-sign-in"></i> Go to Login
                    </a>
                </article>
            </div>

        </section>

        <aside class="landing-auth" id="portal-login">
            <div class="landing-auth__card">
                <div class="landing-auth__header">
                    <img src="<?php echo htmlspecialchars($_LandingLogoHref, ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo $_LandingSchoolNameSafe; ?>" class="landing-auth__logo">
                    <div>
                        <span class="landing-auth__eyebrow">System Access</span>
                        <h3>Sign in to continue</h3>
                    </div>
                </div>

                <div class="landing-auth__message">
                    <?php echo @$_SESSION['Message']; ?>
                </div>

                <form id="formID" name="formID" method="post" action="index.php" class="landing-form">
                    <div class="landing-form__group">
                        <label for="username">User Name</label>
                        <div class="landing-field">
                            <span class="landing-field__icon"><i class="fa fa-user"></i></span>
                            <input type="text" id="username" name="username" placeholder="Type Username" class="validate[required]" autocomplete="username" style="text-align:left !important; direction:ltr; padding-left:16px;">
                        </div>
                    </div>

                    <div class="landing-form__group">
                        <label for="password">Password</label>
                        <div class="landing-field">
                            <span class="landing-field__icon"><i class="fa fa-lock"></i></span>
                            <input type="password" id="password" name="password" placeholder="Type Password" class="validate[required]" autocomplete="current-password" style="text-align:left !important; direction:ltr; padding-left:16px;">
                        </div>
                    </div>

                    <button class="landing-submit" id="login" name="login" type="submit">
                        <i class="fa fa-sign-in"></i> Login
                    </button>
                </form>

                <div class="landing-support">
                    <span><i class="fa fa-phone"></i> Help line: <?php echo htmlspecialchars($_LandingHelpLine, ENT_QUOTES, "UTF-8"); ?></span>
                    <span><i class="fa fa-lock"></i> Session protected</span>
                </div>

                <?php if($_LandingWhatsappUrl !== ""){ ?>
                <a href="<?php echo htmlspecialchars($_LandingWhatsappUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-social-link landing-social-link--whatsapp" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-whatsapp"></i> Chat On WhatsApp
                </a>
                <?php } ?>

                <a href="<?php echo htmlspecialchars($_LandingTiktokUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-social-link landing-social-link--tiktok" target="_blank" rel="noopener noreferrer">
                    <span class="landing-social-mark">TT</span> <?php echo htmlspecialchars($_LandingTiktokLabel, ENT_QUOTES, "UTF-8"); ?> On TikTok
                </a>
            </div>
        </aside>
    </main>

    <button type="button" class="landing-help-fab" id="landingHelpFab" aria-controls="landingHelpModal" aria-expanded="<?php echo $_LandingHelpOpen ? "true" : "false"; ?>" data-open-landing-help>
        <span class="landing-help-fab__pulse" aria-hidden="true"></span>
        <i class="fa fa-life-ring" aria-hidden="true"></i>
        <span>Need help?</span>
    </button>

    <div class="landing-help-modal<?php echo $_LandingHelpOpen ? " is-open" : ""; ?>" id="landingHelpModal" aria-hidden="<?php echo $_LandingHelpOpen ? "false" : "true"; ?>">
        <button type="button" class="landing-help-modal__backdrop" data-close-landing-help aria-label="Close help desk"></button>
        <div class="landing-help-window" id="landing-help-desk" role="dialog" aria-modal="true" aria-labelledby="landingHelpTitle">
            <div class="landing-help-window__header">
                <div>
                    <span class="landing-help-window__eyebrow"><i class="fa fa-life-ring"></i> Help Desk</span>
                    <h3 id="landingHelpTitle">Tell us what you need</h3>
                    <p>Share your issue and the school team can follow up with the right next step.</p>
                </div>
                <button type="button" class="landing-help-window__close" data-close-landing-help aria-label="Close help desk">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="landing-help-chat">
                <div class="landing-help-bubble landing-help-bubble--admin">
                    <strong>School Help Desk</strong>
                    <span><?php echo $_PublicAdmissionOpen ? "Need help with admission, login, or payments? Leave a short message and your contact details." : "Need help with login, results, fees, or a technical issue? Leave a short message and your contact details."; ?></span>
                </div>
                <div class="landing-help-bubble landing-help-bubble--user">
                    <strong>Example</strong>
                    <span><?php echo $_PublicAdmissionOpen ? "I need help continuing my admission and I want a call back." : "I cannot log in to the portal and I need help resetting access."; ?></span>
                </div>
            </div>

            <div class="landing-help-message">
                <?php if($_LandingHelpFlash !== ""){ ?>
                <div class="<?php echo landing_index_safe($_LandingHelpFlashClass); ?>"><?php echo landing_index_safe($_LandingHelpFlash); ?></div>
                <?php } ?>

                <div class="landing-help-chip-row">
                    <button type="button" class="landing-help-chip" data-help-topic="login" data-help-message="I cannot log in to the portal. Please help me regain access.">Login issue</button>
                    <button type="button" class="landing-help-chip" data-help-topic="admission" data-help-message="I need help with my admission process and the next step.">Admission</button>
                    <button type="button" class="landing-help-chip" data-help-topic="fees" data-help-message="I need help with fees, payment confirmation, or billing.">Fees</button>
                    <button type="button" class="landing-help-chip" data-help-topic="technical" data-help-message="I found a technical issue on the portal and need support.">Technical</button>
                    <button type="button" class="landing-help-chip" data-help-topic="general" data-help-message="I need general help from the school support team.">General help</button>
                </div>
            </div>

            <form method="post" action="index.php#landing-help-desk" class="landing-help-form">
                <input type="hidden" name="send_portal_help_request" value="1">
                <div class="landing-help-form__grid">
                    <label class="landing-help-field">
                        <span>Your name</span>
                        <input type="text" id="landingHelpName" name="requestername" value="<?php echo landing_index_safe($_LandingHelpFormState["requestername"]); ?>" placeholder="Type your name">
                    </label>
                    <label class="landing-help-field">
                        <span>I am a</span>
                        <select id="landingHelpRole" name="requesterrole">
                            <option value="visitor" <?php echo $_LandingHelpFormState["requesterrole"]==="visitor" ? "selected" : ""; ?>>Visitor</option>
                            <option value="parent" <?php echo $_LandingHelpFormState["requesterrole"]==="parent" ? "selected" : ""; ?>>Parent</option>
                            <option value="student" <?php echo $_LandingHelpFormState["requesterrole"]==="student" ? "selected" : ""; ?>>Student</option>
                            <option value="teacher" <?php echo $_LandingHelpFormState["requesterrole"]==="teacher" ? "selected" : ""; ?>>Teacher</option>
                            <option value="staff" <?php echo $_LandingHelpFormState["requesterrole"]==="staff" ? "selected" : ""; ?>>Staff</option>
                            <option value="applicant" <?php echo $_LandingHelpFormState["requesterrole"]==="applicant" ? "selected" : ""; ?>>Applicant</option>
                            <option value="other" <?php echo $_LandingHelpFormState["requesterrole"]==="other" ? "selected" : ""; ?>>Other</option>
                        </select>
                    </label>
                    <label class="landing-help-field">
                        <span>Phone number</span>
                        <input type="text" name="contactphone" value="<?php echo landing_index_safe($_LandingHelpFormState["contactphone"]); ?>" placeholder="Type phone number">
                    </label>
                    <label class="landing-help-field">
                        <span>Email address</span>
                        <input type="email" name="contactemail" value="<?php echo landing_index_safe($_LandingHelpFormState["contactemail"]); ?>" placeholder="Type email address">
                    </label>
                    <label class="landing-help-field landing-help-field--full">
                        <span>Help topic</span>
                        <select id="landingHelpTopic" name="helptopic">
                            <option value="login" <?php echo $_LandingHelpFormState["helptopic"]==="login" ? "selected" : ""; ?>>Login Problem</option>
                            <option value="admission" <?php echo $_LandingHelpFormState["helptopic"]==="admission" ? "selected" : ""; ?>>Admission</option>
                            <option value="results" <?php echo $_LandingHelpFormState["helptopic"]==="results" ? "selected" : ""; ?>>Results</option>
                            <option value="fees" <?php echo $_LandingHelpFormState["helptopic"]==="fees" ? "selected" : ""; ?>>Fees / Payments</option>
                            <option value="technical" <?php echo $_LandingHelpFormState["helptopic"]==="technical" ? "selected" : ""; ?>>Technical Issue</option>
                            <option value="general" <?php echo $_LandingHelpFormState["helptopic"]==="general" ? "selected" : ""; ?>>General Help</option>
                            <option value="other" <?php echo $_LandingHelpFormState["helptopic"]==="other" ? "selected" : ""; ?>>Other</option>
                        </select>
                    </label>
                    <label class="landing-help-field landing-help-field--full">
                        <span>Message</span>
                        <textarea id="landingHelpMessage" name="helpmessage" placeholder="Briefly describe what you need help with."><?php echo landing_index_safe($_LandingHelpFormState["helpmessage"]); ?></textarea>
                    </label>
                </div>
                <div class="landing-help-form__footer">
                    <small>Leave a phone number or email so the school can respond quickly.</small>
                    <button type="submit" class="landing-help-submit">
                        <i class="fa fa-paper-plane"></i> Send Help Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="landing-footer">
        <p>&copy 2026. LiveCampus V2.20.2.2</p>
        <p>
            <?php if($_LandingWhatsappUrl !== ""){ ?><a href="<?php echo htmlspecialchars($_LandingWhatsappUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-footer__link" target="_blank" rel="noopener noreferrer">WhatsApp</a> | <?php } ?>
            <a href="<?php echo htmlspecialchars($_LandingTiktokUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-footer__link" target="_blank" rel="noopener noreferrer">TikTok: <?php echo htmlspecialchars($_LandingTiktokLabel, ENT_QUOTES, "UTF-8"); ?></a>
        </p>
    </footer>

    <div class="landing-mobile-help" aria-label="Quick help">
        <?php if($_LandingWhatsappUrl !== ""){ ?>
        <a href="<?php echo htmlspecialchars($_LandingWhatsappUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-mobile-help__link landing-mobile-help__link--whatsapp" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <?php } ?>
        <?php if($_LandingPhoneHref !== ""){ ?>
        <a href="<?php echo htmlspecialchars($_LandingPhoneHref, ENT_QUOTES, "UTF-8"); ?>" class="landing-mobile-help__link">
            <i class="fa fa-phone"></i>
            <span>Call</span>
        </a>
        <?php } ?>
        <a href="<?php echo htmlspecialchars($_LandingQuickActionHref, ENT_QUOTES, "UTF-8"); ?>" class="landing-mobile-help__link landing-mobile-help__link--accent">
            <i class="fa <?php echo htmlspecialchars($_LandingQuickActionIcon, ENT_QUOTES, "UTF-8"); ?>"></i>
            <span><?php echo htmlspecialchars($_LandingQuickActionLabel, ENT_QUOTES, "UTF-8"); ?></span>
        </a>
    </div>
</div>
<script>
(function () {
    var body = document.body;
    var modal = document.getElementById('landingHelpModal');
    if (!modal) {
        return;
    }

    var fab = document.getElementById('landingHelpFab');
    var nameField = document.getElementById('landingHelpName');
    var topicField = document.getElementById('landingHelpTopic');
    var messageField = document.getElementById('landingHelpMessage');

    function setHelpOpen(isOpen) {
        modal.classList.toggle('is-open', isOpen);
        modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        body.classList.toggle('landing-help-open', isOpen);
        if (fab) {
            fab.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
        if (isOpen && nameField && nameField.value === '') {
            window.setTimeout(function () {
                nameField.focus();
            }, 60);
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-open-landing-help]'), function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            setHelpOpen(true);
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-close-landing-help]'), function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            setHelpOpen(false);
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-help-topic]'), function (chip) {
        chip.addEventListener('click', function () {
            var presetTopic = chip.getAttribute('data-help-topic') || 'general';
            var presetMessage = chip.getAttribute('data-help-message') || '';
            if (topicField) {
                topicField.value = presetTopic;
            }
            if (messageField && messageField.value.trim() === '') {
                messageField.value = presetMessage;
            }
            setHelpOpen(true);
            if (messageField) {
                messageField.focus();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            setHelpOpen(false);
        }
    });

    setHelpOpen(modal.classList.contains('is-open'));
})();
</script>
</body>
</html>

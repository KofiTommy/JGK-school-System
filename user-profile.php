<?php
session_start();

include("check-login.php");
include_once("user-management-utils.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin=$_POST['nextofkin'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,nextofkin_fullname,nextofkin_contact,registereddatetime,recordedby,status,username,password,accesslevel,finame)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender','$_Birthday','$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin','')");
}
?>

<?php
if(!function_exists('up_safe')){
function up_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('up_value')){
function up_value($row, $key, $default = "--"){
    if(is_array($row) && array_key_exists($key, $row) && trim((string)$row[$key]) !== ""){
        return (string)$row[$key];
    }
    return $default;
}
}

if(!function_exists('up_format_date')){
function up_format_date($value, $format = "d M Y"){
    $value = trim((string)$value);
    if($value === ""){
        return "--";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date($format, $timestamp);
}
}

if(!function_exists('up_full_name')){
function up_full_name($row){
    $parts = array();
    foreach(array("firstname", "othernames", "surname") as $field){
        if(isset($row[$field]) && trim((string)$row[$field]) !== ""){
            $parts[] = trim((string)$row[$field]);
        }
    }
    return trim(implode(" ", $parts));
}
}

if(!function_exists('up_avatar_path')){
function up_avatar_path($row){
    $fallback = "uploads/comm.gif";
    $filename = isset($row["filename"]) ? trim((string)$row["filename"]) : "";
    if($filename !== ""){
        $candidate = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename;
        if(file_exists($candidate)){
            return "uploads/".$filename;
        }
    }
    return $fallback;
}
}

$profileRow = null;
$viewUserId = isset($_GET["view_user"]) ? trim((string)$_GET["view_user"]) : "";
if($viewUserId !== ""){
    $viewUserEsc = mysqli_real_escape_string($con, $viewUserId);
    $profileQuery = mysqli_query($con, "SELECT * FROM tblsystemuser WHERE userid='$viewUserEsc' LIMIT 1");
    if($profileQuery && mysqli_num_rows($profileQuery) > 0){
        $profileRow = mysqli_fetch_array($profileQuery, MYSQLI_ASSOC);
    }
}

$profileFullName = $profileRow ? up_full_name($profileRow) : "";
$profileSystemType = $profileRow ? trim((string)up_value($profileRow, "systemtype", "")) : "";
$profileIsStudent = strcasecmp($profileSystemType, "Student") === 0;
$profileIsTeacher = strcasecmp($profileSystemType, "Teacher") === 0;
$relationshipLabel = $profileIsTeacher ? "Contact Relationship" : "Relationship";
$contactNameLabel = $profileIsTeacher ? "Emergency Contact Full Name" : "Next Of Kin Full Name";
$contactPhoneLabel = $profileIsTeacher ? "Emergency Contact Number" : "Next Of Kin Contact";
$profileStatus = $profileRow ? strtolower(trim((string)up_value($profileRow, "status", ""))) : "";
$profileStatusLabel = ($profileStatus === "active") ? "Active" : (($profileStatus !== "") ? "Blocked" : "--");
$profileAvatar = $profileRow ? up_avatar_path($profileRow) : "uploads/comm.gif";
$isAdminViewer = isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    $_SESSION['ACCESSLEVEL'] === 'administrator' &&
    in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
$isHeadmasterViewer = function_exists('um_is_headmaster_user') && um_is_headmaster_user();
$isAssistantAcademicViewer = function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user();
$isOwnProfile = $viewUserId !== "" && isset($_SESSION['USERID']) && trim((string)$_SESSION['USERID']) === $viewUserId;
$canViewProfile = false;

if($profileRow){
    if($isAdminViewer || $isOwnProfile){
        $canViewProfile = true;
    }elseif(($isHeadmasterViewer || $isAssistantAcademicViewer) && ($profileIsStudent || $profileIsTeacher)){
        $canViewProfile = true;
    }
}

if(!$canViewProfile){
    $_SESSION['Message'] = "<div style='color:red;text-align:center;padding:10px;'>You do not have access to that profile.</div>";
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}

$canEditProfile = $isAdminViewer;
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/user-profile.css?v=20260514">
</head>
<body class="user-profile-page">
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>

<main class="user-profile-shell">
    <?php if($profileRow){ ?>
    <section class="user-profile-hero">
        <div class="user-profile-hero__copy">
            <span class="user-profile-eyebrow">User Profile</span>
            <h1><?php echo up_safe($profileFullName !== "" ? $profileFullName : up_value($profileRow, "userid")); ?></h1>
            <p><?php echo up_safe($profileSystemType !== "" ? $profileSystemType : "User"); ?> record overview with core personal, contact, and account details.</p>
            <div class="user-profile-badges">
                <span class="user-profile-badge user-profile-badge--role"><?php echo up_safe($profileSystemType !== "" ? $profileSystemType : "User"); ?></span>
                <span class="user-profile-badge user-profile-badge--<?php echo ($profileStatus === "active" ? "active" : "blocked"); ?>"><?php echo up_safe($profileStatusLabel); ?></span>
            </div>
        </div>
        <div class="user-profile-hero__actions">
            <button type="button" class="user-profile-link" onclick="window.history.back();"><i class="fa fa-arrow-left"></i> Back</button>
            <?php if($profileIsStudent){ ?>
            <a href="student-history.php?userid=<?php echo urlencode($viewUserId); ?>" class="user-profile-link"><i class="fa fa-history"></i> Transcript</a>
            <?php } ?>
            <?php if($canEditProfile){ ?>
            <a href="register_edit.php?edit_user=<?php echo urlencode($viewUserId); ?>" class="user-profile-link user-profile-link--primary"><i class="fa fa-edit"></i> Edit Profile</a>
            <?php } ?>
        </div>
    </section>

    <div class="user-profile-layout">
        <aside class="user-profile-card">
            <div class="user-profile-card__top">
                <img src="<?php echo up_safe($profileAvatar); ?>" alt="<?php echo up_safe($profileFullName !== "" ? $profileFullName : "User"); ?>">
                <div>
                    <span class="user-profile-card__eyebrow"><?php echo up_safe($profileSystemType !== "" ? $profileSystemType : "User"); ?></span>
                    <h2><?php echo up_safe($profileFullName !== "" ? $profileFullName : up_value($profileRow, "userid")); ?></h2>
                    <p><?php echo up_safe(up_value($profileRow, "userid")); ?></p>
                </div>
            </div>
            <div class="user-profile-meta">
                <article class="user-profile-meta__item">
                    <span>Username</span>
                    <strong><?php echo up_safe(up_value($profileRow, "username")); ?></strong>
                </article>
                <article class="user-profile-meta__item">
                    <span>Gender</span>
                    <strong><?php echo up_safe(up_value($profileRow, "gender")); ?></strong>
                </article>
                <article class="user-profile-meta__item">
                    <span>Birthday</span>
                    <strong><?php echo up_safe(up_format_date(up_value($profileRow, "birthday", ""), "d M Y")); ?></strong>
                </article>
                <article class="user-profile-meta__item">
                    <span>Age</span>
                    <strong><?php echo up_safe(up_value($profileRow, "age")); ?></strong>
                </article>
                <article class="user-profile-meta__item">
                    <span>Entry Date</span>
                    <strong><?php echo up_safe(up_format_date(up_value($profileRow, "registereddatetime", ""), "d M Y, g:i a")); ?></strong>
                </article>
                <article class="user-profile-meta__item">
                    <span>Status</span>
                    <strong><?php echo up_safe($profileStatusLabel); ?></strong>
                </article>
            </div>
        </aside>

        <section class="user-profile-content">
            <section class="user-profile-panel">
                <div class="user-profile-panel__head">
                    <span class="user-profile-eyebrow">Personal Details</span>
                    <h2>Core Profile</h2>
                </div>
                <div class="user-profile-grid">
                    <div class="user-profile-field"><span>User ID</span><strong><?php echo up_safe(up_value($profileRow, "userid")); ?></strong></div>
                    <div class="user-profile-field"><span>First Name</span><strong><?php echo up_safe(up_value($profileRow, "firstname")); ?></strong></div>
                    <div class="user-profile-field"><span>Surname</span><strong><?php echo up_safe(up_value($profileRow, "surname")); ?></strong></div>
                    <div class="user-profile-field"><span>Other Names</span><strong><?php echo up_safe(up_value($profileRow, "othernames")); ?></strong></div>
                    <div class="user-profile-field"><span>Gender</span><strong><?php echo up_safe(up_value($profileRow, "gender")); ?></strong></div>
                    <div class="user-profile-field"><span>Religion</span><strong><?php echo up_safe(up_value($profileRow, "religion")); ?></strong></div>
                    <div class="user-profile-field"><span>Home Town</span><strong><?php echo up_safe(up_value($profileRow, "hometown")); ?></strong></div>
                    <div class="user-profile-field"><span>Birthday</span><strong><?php echo up_safe(up_format_date(up_value($profileRow, "birthday", ""), "d M Y")); ?></strong></div>
                    <div class="user-profile-field"><span>Age</span><strong><?php echo up_safe(up_value($profileRow, "age")); ?></strong></div>
                    <?php if($profileIsStudent){ ?>
                    <div class="user-profile-field"><span>Residence Type</span><strong><?php echo up_safe(up_value($profileRow, "residencetype")); ?></strong></div>
                    <div class="user-profile-field"><span>BECE Index Number</span><strong><?php echo up_safe(up_value($profileRow, "beceindexnumber")); ?></strong></div>
                    <?php } ?>
                    <?php if($profileIsTeacher){ ?>
                    <div class="user-profile-field"><span>Staff Status</span><strong><?php echo up_safe(up_value($profileRow, "staffstatus")); ?></strong></div>
                    <div class="user-profile-field"><span>Branch</span><strong><?php echo up_safe(up_value($profileRow, "branchid")); ?></strong></div>
                    <?php } ?>
                </div>
            </section>

            <section class="user-profile-panel">
                <div class="user-profile-panel__head">
                    <span class="user-profile-eyebrow">Contact Details</span>
                    <h2>Addresses and Contact</h2>
                </div>
                <div class="user-profile-grid">
                    <div class="user-profile-field"><span>Mobile</span><strong><?php echo up_safe(up_value($profileRow, "mobile")); ?></strong></div>
                    <div class="user-profile-field"><span>Email</span><strong><?php echo up_safe(up_value($profileRow, "email")); ?></strong></div>
                    <div class="user-profile-field user-profile-field--wide"><span>Postal Address</span><strong><?php echo up_safe(up_value($profileRow, "postaladdress")); ?></strong></div>
                    <div class="user-profile-field user-profile-field--wide"><span>Home Address</span><strong><?php echo up_safe(up_value($profileRow, "homeaddress")); ?></strong></div>
                </div>
            </section>

            <section class="user-profile-panel">
                <div class="user-profile-panel__head">
                    <span class="user-profile-eyebrow"><?php echo $profileIsTeacher ? "Emergency Contact" : "Guardian Details"; ?></span>
                    <h2><?php echo $profileIsTeacher ? "Emergency Contact Record" : "Next of Kin Record"; ?></h2>
                </div>
                <div class="user-profile-grid">
                    <div class="user-profile-field"><span><?php echo up_safe($relationshipLabel); ?></span><strong><?php echo up_safe(up_value($profileRow, "relationship")); ?></strong></div>
                    <div class="user-profile-field"><span><?php echo up_safe($contactNameLabel); ?></span><strong><?php echo up_safe(up_value($profileRow, "nextofkin_fullname")); ?></strong></div>
                    <div class="user-profile-field"><span><?php echo up_safe($contactPhoneLabel); ?></span><strong><?php echo up_safe(up_value($profileRow, "nextofkin_contact")); ?></strong></div>
                </div>
            </section>

            <section class="user-profile-panel">
                <div class="user-profile-panel__head">
                    <span class="user-profile-eyebrow">Account Details</span>
                    <h2>Portal Access</h2>
                </div>
                <div class="user-profile-grid">
                    <div class="user-profile-field"><span>Username</span><strong><?php echo up_safe(up_value($profileRow, "username")); ?></strong></div>
                    <div class="user-profile-field"><span>System Type</span><strong><?php echo up_safe(up_value($profileRow, "systemtype")); ?></strong></div>
                    <div class="user-profile-field"><span>Status</span><strong><?php echo up_safe($profileStatusLabel); ?></strong></div>
                    <div class="user-profile-field"><span>Entry Date/Time</span><strong><?php echo up_safe(up_format_date(up_value($profileRow, "registereddatetime", ""), "d M Y, g:i a")); ?></strong></div>
                </div>
            </section>
        </section>
    </div>
    <?php } else { ?>
    <section class="user-profile-empty">
        <i class="fa fa-user-o"></i>
        <h2>User not found</h2>
        <p>The requested profile could not be loaded.</p>
        <button type="button" class="user-profile-link" onclick="window.history.back();"><i class="fa fa-arrow-left"></i> Go Back</button>
    </section>
    <?php } ?>
</main>
</body>
</html>

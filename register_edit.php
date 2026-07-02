<?php
session_start();
include("dbstring.php");

function re_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function re_alert($type, $message){
    $class = "rs-alert rs-alert--info";
    if($type === "success"){
        $class = "rs-alert rs-alert--success";
    }elseif($type === "error"){
        $class = "rs-alert rs-alert--error";
    }elseif($type === "warning"){
        $class = "rs-alert rs-alert--warning";
    }
    return "<div class=\"$class\">".re_safe($message)."</div>";
}

function re_normalize_birthday($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])){
        return sprintf("%04d-%02d-%02d", $m[1], $m[2], $m[3]);
    }
    if(preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])){
        return sprintf("%04d-%02d-%02d", $m[3], $m[2], $m[1]);
    }
    $timestamp = strtotime($value);
    if($timestamp !== false){
        return date("Y-m-d", $timestamp);
    }
    return false;
}

function re_birthday_input_value($value){
    $normalized = re_normalize_birthday($value);
    return $normalized === false ? "" : $normalized;
}

function re_row($row, $keys, $default = ""){
    if(!is_array($keys)){
        $keys = array($keys);
    }
    foreach($keys as $key){
        if(is_array($row) && array_key_exists($key, $row) && $row[$key] !== null){
            return $row[$key];
        }
    }
    return $default;
}

function re_age($birthday){
    if(!$birthday){
        return "";
    }
    try{
        $dob = new DateTime($birthday);
        return (string)$dob->diff(new DateTime("today"))->y;
    }catch(Exception $e){
        return "";
    }
}

function re_selected($current, $value){
    return strcasecmp(trim((string)$current), trim((string)$value)) === 0 ? " selected" : "";
}

function re_checked_attr($current, $value){
    return strcasecmp(trim((string)$current), trim((string)$value)) === 0 ? " checked" : "";
}

function re_upload_error_text($code){
    $map = array(
        UPLOAD_ERR_INI_SIZE => "The image is larger than the server allows.",
        UPLOAD_ERR_FORM_SIZE => "The image is larger than the allowed form size.",
        UPLOAD_ERR_PARTIAL => "The image upload was interrupted. Please try again.",
        UPLOAD_ERR_NO_TMP_DIR => "The server is missing a temporary upload folder.",
        UPLOAD_ERR_CANT_WRITE => "The server could not write the image file.",
        UPLOAD_ERR_EXTENSION => "The upload was stopped by a server extension."
    );
    return isset($map[$code]) ? $map[$code] : "The image could not be uploaded.";
}

function re_store_profile_image($con, $userId, $file, &$errorMessage){
    $errorMessage = "";
    if(!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE || !isset($file["name"]) || trim((string)$file["name"]) === ""){
        return true;
    }
    if($file["error"] !== UPLOAD_ERR_OK){
        $errorMessage = re_upload_error_text((int)$file["error"]);
        return false;
    }
    if(!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])){
        $errorMessage = "The selected image upload is invalid.";
        return false;
    }
    if(isset($file["size"]) && (int)$file["size"] > 5 * 1024 * 1024){
        $errorMessage = "The image is too large. Please use a file smaller than 5MB.";
        return false;
    }

    $ext = strtolower(pathinfo((string)$file["name"], PATHINFO_EXTENSION));
    $allowedExtensions = array("jpg", "jpeg", "png", "gif", "webp");
    if(!in_array($ext, $allowedExtensions, true)){
        $errorMessage = "Please upload a JPG, PNG, GIF, or WEBP image.";
        return false;
    }

    $mime = "";
    if(function_exists("finfo_open")){
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if($finfo){
            $mime = (string)@finfo_file($finfo, $file["tmp_name"]);
            finfo_close($finfo);
        }
    }
    $allowedMimes = array("image/jpeg", "image/png", "image/gif", "image/webp");
    if($mime !== "" && !in_array($mime, $allowedMimes, true)){
        $errorMessage = "The selected file is not a valid image.";
        return false;
    }

    $uploadDir = __DIR__.DIRECTORY_SEPARATOR."uploads";
    if(!is_dir($uploadDir)){
        $errorMessage = "The uploads folder is missing on the server.";
        return false;
    }

    $safeUserId = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$userId);
    $storedName = $safeUserId."-".date("YmdHis")."-".substr(md5(uniqid('', true)), 0, 8).".".$ext;
    $destination = $uploadDir.DIRECTORY_SEPARATOR.$storedName;
    if(!move_uploaded_file($file["tmp_name"], $destination)){
        $errorMessage = "The image could not be moved to the uploads folder.";
        return false;
    }

    $storedEsc = mysqli_real_escape_string($con, $storedName);
    $userEsc = mysqli_real_escape_string($con, (string)$userId);
    $update = mysqli_query($con, "UPDATE tblsystemuser SET filename='$storedEsc', uploadeddatetime=NOW() WHERE userid='$userEsc' LIMIT 1");
    if(!$update){
        @unlink($destination);
        $errorMessage = "The user was updated, but the image record could not be saved.";
        return false;
    }
    return true;
}

function re_fetch_user($con, $userId){
    $userEsc = mysqli_real_escape_string($con, (string)$userId);
    $sql = "SELECT su.*, br.location AS branch_location
        FROM tblsystemuser su
        LEFT JOIN tblbranch br ON br.branchid=su.branchid
        WHERE su.userid='$userEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if($result && mysqli_num_rows($result) > 0){
        return mysqli_fetch_array($result, MYSQLI_ASSOC);
    }
    return null;
}

function re_person_name($row){
    $parts = array();
    foreach(array("firstname", "othernames", "surname") as $field){
        $value = trim((string)re_row($row, $field));
        if($value !== ""){
            $parts[] = $value;
        }
    }
    return trim(implode(" ", $parts));
}

$staffOptions = array("Teaching Staff", "Non-Teaching Staff", "Non Teaching Staff", "Student");
$flashMessage = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

$editUserId = "";
if(isset($_GET["edit_user"])){
    $editUserId = trim((string)$_GET["edit_user"]);
}
if($editUserId === "" && isset($_POST["userid"])){
    $editUserId = trim((string)$_POST["userid"]);
}

$branches = array();
$branchRes = mysqli_query($con, "SELECT branchid,location FROM tblbranch ORDER BY location ASC");
if($branchRes){
    while($branchRow = mysqli_fetch_array($branchRes, MYSQLI_ASSOC)){
        $branches[] = $branchRow;
    }
}

$currentRow = $editUserId !== "" ? re_fetch_user($con, $editUserId) : null;
$form = array();

if($currentRow){
    $form = array(
        "userid" => re_row($currentRow, "userid"),
        "username" => re_row($currentRow, "username"),
        "firstname" => re_row($currentRow, "firstname"),
        "surname" => re_row($currentRow, "surname"),
        "othernames" => re_row($currentRow, "othernames"),
        "gender" => re_row($currentRow, "gender"),
        "residencetype" => re_row($currentRow, "residencetype"),
        "birthday" => re_birthday_input_value(re_row($currentRow, array("birthday", "Birthday"))),
        "age" => re_row($currentRow, "age"),
        "postaladdress" => re_row($currentRow, "postaladdress"),
        "homeaddress" => re_row($currentRow, "homeaddress"),
        "hometown" => re_row($currentRow, "hometown"),
        "email" => re_row($currentRow, "email"),
        "mobile" => re_row($currentRow, "mobile"),
        "religion" => re_row($currentRow, "religion"),
        "relationship" => re_row($currentRow, "relationship"),
        "beceindexnumber" => re_row($currentRow, array("beceindexnumber", "BECEIndexNumber")),
        "nextoffullname" => re_row($currentRow, "nextofkin_fullname"),
        "nextofkincontact" => re_row($currentRow, "nextofkin_contact"),
        "branchid" => re_row($currentRow, "branchid"),
        "staffstatus" => re_row($currentRow, "staffstatus"),
        "systemtype" => re_row($currentRow, "systemtype"),
        "status" => re_row($currentRow, "status"),
        "filename" => re_row($currentRow, "filename"),
        "branch_location" => re_row($currentRow, "branch_location")
    );
}

if(isset($_POST["register_update"])){
    if(!$currentRow){
        $flashMessage = re_alert("error", "The selected user could not be found.");
    }else{
        $form = array(
            "userid" => $editUserId,
            "username" => trim((string)(isset($_POST["username"]) ? $_POST["username"] : re_row($currentRow, "username"))),
            "firstname" => trim((string)(isset($_POST["firstname"]) ? $_POST["firstname"] : "")),
            "surname" => trim((string)(isset($_POST["surname"]) ? $_POST["surname"] : "")),
            "othernames" => trim((string)(isset($_POST["othernames"]) ? $_POST["othernames"] : "")),
            "gender" => trim((string)(isset($_POST["gender"]) ? $_POST["gender"] : "")),
            "residencetype" => trim((string)(isset($_POST["residencetype"]) ? $_POST["residencetype"] : "")),
            "birthday" => trim((string)(isset($_POST["birthday"]) ? $_POST["birthday"] : "")),
            "age" => trim((string)(isset($_POST["age"]) ? $_POST["age"] : "")),
            "postaladdress" => trim((string)(isset($_POST["postaladdress"]) ? $_POST["postaladdress"] : "")),
            "homeaddress" => trim((string)(isset($_POST["homeaddress"]) ? $_POST["homeaddress"] : "")),
            "hometown" => trim((string)(isset($_POST["hometown"]) ? $_POST["hometown"] : "")),
            "email" => trim((string)(isset($_POST["email"]) ? $_POST["email"] : "")),
            "mobile" => trim((string)(isset($_POST["mobile"]) ? $_POST["mobile"] : "")),
            "religion" => trim((string)(isset($_POST["religion"]) ? $_POST["religion"] : "")),
            "relationship" => trim((string)(isset($_POST["relationship"]) ? $_POST["relationship"] : "")),
            "beceindexnumber" => trim((string)(isset($_POST["beceindexnumber"]) ? $_POST["beceindexnumber"] : "")),
            "nextoffullname" => trim((string)(isset($_POST["nextoffullname"]) ? $_POST["nextoffullname"] : "")),
            "nextofkincontact" => trim((string)(isset($_POST["nextofkincontact"]) ? $_POST["nextofkincontact"] : "")),
            "branchid" => trim((string)(isset($_POST["branchid"]) ? $_POST["branchid"] : "")),
            "staffstatus" => trim((string)(isset($_POST["recipient"]) ? $_POST["recipient"] : "")),
            "systemtype" => re_row($currentRow, "systemtype"),
            "status" => re_row($currentRow, "status"),
            "filename" => re_row($currentRow, "filename"),
            "branch_location" => re_row($currentRow, "branch_location")
        );

        $errors = array();
        $birthday = re_normalize_birthday($form["birthday"]);
        if($birthday === false){
            $errors[] = "Please choose a valid date of birth.";
        }else{
            $form["birthday"] = $birthday;
            $form["age"] = re_age($birthday);
        }

        foreach(array(
            "username" => "Username",
            "firstname" => "First name",
            "surname" => "Surname",
            "gender" => "Gender",
            "residencetype" => "Residence type",
            "birthday" => "Date of birth",
            "mobile" => "Mobile number",
            "religion" => "Religion",
            "relationship" => "Relationship",
            "nextoffullname" => "Next of kin full name",
            "nextofkincontact" => "Next of kin contact",
            "branchid" => "Branch",
            "staffstatus" => "Staff status"
        ) as $field => $label){
            if(trim((string)$form[$field]) === ""){
                $errors[] = $label." is required.";
            }
        }
        if($form["email"] !== "" && !filter_var($form["email"], FILTER_VALIDATE_EMAIL)){
            $errors[] = "Please enter a valid email address.";
        }
        if($form["username"] !== ""){
            $usernameEsc = mysqli_real_escape_string($con, $form["username"]);
            $userIdEsc = mysqli_real_escape_string($con, $form["userid"]);
            $dupResult = mysqli_query($con, "SELECT userid FROM tblsystemuser WHERE username='$usernameEsc' AND userid<>'$userIdEsc' LIMIT 1");
            if($dupResult && mysqli_num_rows($dupResult) > 0){
                $errors[] = "That username is already in use by another account.";
            }
        }

        if(empty($errors)){
            $stmt = mysqli_prepare($con, "UPDATE tblsystemuser SET
                username=?, firstname=?, surname=?, othernames=?, gender=?, residencetype=?, birthday=?, age=?, postaladdress=?, homeaddress=?, hometown=?, email=?, mobile=?, religion=?, relationship=?, BECEIndexNumber=?, nextofkin_fullname=?, nextofkin_contact=?, staffstatus=?, branchid=?
                WHERE userid=?
                LIMIT 1");
            if($stmt){
                mysqli_stmt_bind_param(
                    $stmt,
                    str_repeat("s", 21),
                    $form["username"], $form["firstname"], $form["surname"], $form["othernames"], $form["gender"], $form["residencetype"], $form["birthday"], $form["age"], $form["postaladdress"], $form["homeaddress"], $form["hometown"], $form["email"], $form["mobile"], $form["religion"], $form["relationship"], $form["beceindexnumber"], $form["nextoffullname"], $form["nextofkincontact"], $form["staffstatus"], $form["branchid"], $form["userid"]
                );
                if(mysqli_stmt_execute($stmt)){
                    $type = "success";
                    $message = "User information updated successfully.";
                    $imageError = "";
                    if(isset($_FILES["profileimage"]) && !re_store_profile_image($con, $form["userid"], $_FILES["profileimage"], $imageError)){
                        $type = "warning";
                        $message .= " ".$imageError;
                    }elseif(isset($_FILES["profileimage"]) && isset($_FILES["profileimage"]["error"]) && (int)$_FILES["profileimage"]["error"] === UPLOAD_ERR_OK){
                        $message .= " Profile image uploaded successfully.";
                    }
                    mysqli_stmt_close($stmt);
                    $_SESSION["Message"] = re_alert($type, $message);
                    header("location:register_edit.php?edit_user=".urlencode($form["userid"]));
                    exit();
                }
                $flashMessage = re_alert("error", "User information failed to update. Error: ".mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            }else{
                $flashMessage = re_alert("error", "The update form could not be prepared right now.");
            }
        }else{
            $flashMessage = re_alert("warning", implode(" ", $errors));
        }
    }
}

$displayName = $currentRow ? re_person_name($currentRow) : "";
$currentImage = "uploads/comm.gif";
$currentFilename = $currentRow ? trim((string)re_row($currentRow, "filename")) : "";
if($currentFilename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$currentFilename)){
    $currentImage = "uploads/".rawurlencode($currentFilename);
}
$isStudentEdit = $currentRow ? (strcasecmp(trim((string)re_row($currentRow, "systemtype")), "Student") === 0) : false;
$backPage = "user-management.php";
$backLabel = "Back to User Management";
if($currentRow){
    $systemTypeLower = strtolower(trim((string)re_row($currentRow, "systemtype")));
    if($systemTypeLower === "student"){
        $backPage = "viewstudents.php";
        $backLabel = "Back to Students List";
    }elseif($systemTypeLower === "teacher"){
        $backPage = "viewusers.php";
        $backLabel = "Back to Teachers List";
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/register-student.css">
</head>
<body class="body-style student-register-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="rs-shell">
    <?php if($flashMessage !== ""){ ?><div class="rs-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <?php if(!$currentRow){ ?>
    <section class="rs-panel">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark"><i class="fa fa-user-times"></i> Edit User</span>
            <h2>User record not found</h2>
            <p>The requested user could not be loaded. Please go back and choose the user again.</p>
        </div>
        <div class="rs-form-foot">
            <p><i class="fa fa-info-circle"></i> Nothing has been changed.</p>
            <a class="rs-submit" href="user-management.php" style="text-decoration:none;"><i class="fa fa-arrow-left"></i> Back to User Management</a>
        </div>
    </section>
    <?php } else { ?>
    <section class="rs-hero">
        <div>
            <span class="rs-kicker"><i class="fa fa-pencil-square-o"></i> Account Editor</span>
            <h1>Update Registered Users</h1>
            <p>Edit user details and update the profile picture when needed.</p>
            <div class="rs-pills">
                <span>Mobile friendly</span>
                <span>Photo upload ready</span>
                <span>Auto age from birthday</span>
            </div>
        </div>
        <aside class="rs-hero-card">
            <h2><?php echo re_safe($displayName !== "" ? $displayName : re_row($currentRow, "userid")); ?></h2>
            <p><?php echo re_safe(re_row($currentRow, "username") !== "" ? re_row($currentRow, "username") : "Username not set"); ?></p>
            <div class="rs-metrics">
                <article><span>User ID</span><strong><?php echo re_safe(re_row($currentRow, "userid")); ?></strong></article>
                <article><span>Account Type</span><strong><?php echo re_safe(re_row($currentRow, "systemtype") !== "" ? re_row($currentRow, "systemtype") : "User"); ?></strong></article>
                <article><span>Status</span><strong><?php echo re_safe(re_row($currentRow, "status") !== "" ? ucfirst((string)re_row($currentRow, "status")) : "Unknown"); ?></strong></article>
                <article><span>Branch</span><strong><?php echo re_safe(re_row($currentRow, "branch_location") !== "" ? re_row($currentRow, "branch_location") : "Branch not set"); ?></strong></article>
            </div>
            <small>Leaving the picture field empty keeps the current profile image.</small>
        </aside>
    </section>

    <div class="rs-layout">
        <section class="rs-panel rs-panel--form">
            <div class="rs-panel-head">
                <div>
                    <span class="rs-kicker rs-kicker--dark">User Form</span>
                    <h2>Edit Registered User</h2>
                    <p>You are updating the selected user record.</p>
                </div>
                <div class="rs-id-chip">
                    <span>Editing ID</span>
                    <strong><?php echo re_safe($form["userid"]); ?></strong>
                </div>
            </div>

            <form method="post" action="register_edit.php?edit_user=<?php echo urlencode($form["userid"]); ?>" class="rs-form" enctype="multipart/form-data">
                <input type="hidden" name="userid" value="<?php echo re_safe($form["userid"]); ?>">

                <section class="rs-section rs-section--photo">
                    <div class="rs-section-head"><h3>Profile Image</h3><p>Replace the existing profile picture if needed. Leaving this blank keeps the current image.</p></div>
                    <div class="rs-photo-card">
                        <div class="rs-photo-preview">
                            <img src="<?php echo re_safe($currentImage); ?>" alt="User image preview" id="rs-image-preview">
                        </div>
                        <div class="rs-photo-copy">
                            <label for="profileimage">Upload Picture</label>
                            <input type="file" id="profileimage" name="profileimage" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                            <small>Accepted formats: JPG, PNG, GIF, WEBP. Maximum size: 5MB.</small>
                            <small>If the page shows a validation error, please re-select the image before saving again.</small>
                        </div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Identity</h3><p>Core personal and account identity details.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="userid_view">User ID</label><input type="text" id="userid_view" value="<?php echo re_safe($form["userid"]); ?>" readonly></div>
                        <div class="rs-field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?php echo re_safe($form["username"]); ?>" required></div>
                        <div class="rs-field"><label for="systemtype_view">System Type</label><input type="text" id="systemtype_view" value="<?php echo re_safe($form["systemtype"] !== "" ? $form["systemtype"] : "User"); ?>" readonly></div>
                        <div class="rs-field"><label for="firstname">First Name</label><input type="text" id="firstname" name="firstname" value="<?php echo re_safe($form["firstname"]); ?>" required></div>
                        <div class="rs-field"><label for="surname">Surname</label><input type="text" id="surname" name="surname" value="<?php echo re_safe($form["surname"]); ?>" required></div>
                        <div class="rs-field"><label for="othernames">Other Names</label><input type="text" id="othernames" name="othernames" value="<?php echo re_safe($form["othernames"]); ?>"></div>
                        <?php if($isStudentEdit){ ?>
                        <div class="rs-field"><label for="beceindexnumber">BECE Index Number</label><input type="text" id="beceindexnumber" name="beceindexnumber" value="<?php echo re_safe($form["beceindexnumber"]); ?>"></div>
                        <?php } ?>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>School Profile</h3><p>Gender, residence, birthday, branch, and staff profile.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field">
                            <label>Gender</label>
                            <div class="rs-choices">
                                <label class="rs-choice"><input type="radio" name="gender" value="Male"<?php echo re_checked_attr($form["gender"], "Male"); ?>><span>Male</span></label>
                                <label class="rs-choice"><input type="radio" name="gender" value="Female"<?php echo re_checked_attr($form["gender"], "Female"); ?>><span>Female</span></label>
                            </div>
                        </div>
                        <?php if($isStudentEdit){ ?>
                        <div class="rs-field">
                            <label>Residence Type</label>
                            <div class="rs-choices">
                                <label class="rs-choice"><input type="radio" name="residencetype" value="Day"<?php echo re_checked_attr($form["residencetype"], "Day"); ?>><span>Day</span></label>
                                <label class="rs-choice"><input type="radio" name="residencetype" value="Boarding"<?php echo re_checked_attr($form["residencetype"], "Boarding"); ?>><span>Boarding</span></label>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="rs-field">
                            <label for="religion">Religion</label>
                            <select id="religion" name="religion" required>
                                <option value="">Select religion</option>
                                <option value="Christian"<?php echo re_selected($form["religion"], "Christian"); ?>>Christian</option>
                                <option value="Muslim"<?php echo re_selected($form["religion"], "Muslim"); ?>>Muslim</option>
                                <option value="Tradition"<?php echo re_selected($form["religion"], "Tradition"); ?>>Tradition</option>
                                <option value="Others"<?php echo re_selected($form["religion"], "Others"); ?>>Others</option>
                            </select>
                        </div>
                        <div class="rs-field"><label for="birthday">Date of Birth</label><input type="date" id="birthday" name="birthday" value="<?php echo re_safe(re_birthday_input_value($form["birthday"])); ?>" max="<?php echo date("Y-m-d"); ?>" required></div>
                        <div class="rs-field"><label for="age">Age</label><input type="text" id="age" name="age" value="<?php echo re_safe($form["age"]); ?>" readonly></div>
                        <div class="rs-field">
                            <label for="branchid">Branch</label>
                            <select id="branchid" name="branchid" required>
                                <option value="">Select branch</option>
                                <?php foreach($branches as $branch){ ?>
                                <option value="<?php echo re_safe($branch["branchid"]); ?>"<?php echo re_selected($form["branchid"], $branch["branchid"]); ?>><?php echo re_safe($branch["location"]); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="rs-field">
                            <label for="recipient">Staff Status</label>
                            <select id="recipient" name="recipient" required>
                                <option value="">Select staff status</option>
                                <?php foreach($staffOptions as $staffOption){ ?>
                                <option value="<?php echo re_safe($staffOption); ?>"<?php echo re_selected($form["staffstatus"], $staffOption); ?>><?php echo re_safe($staffOption); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="rs-field"><label for="status_view">Account Status</label><input type="text" id="status_view" value="<?php echo re_safe($form["status"] !== "" ? ucfirst((string)$form["status"]) : "Unknown"); ?>" readonly></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Contact</h3><p>Address and communication details.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="mobile">Mobile Number</label><input type="tel" id="mobile" name="mobile" value="<?php echo re_safe($form["mobile"]); ?>" required></div>
                        <div class="rs-field"><label for="email">Email Address</label><input type="email" id="email" name="email" value="<?php echo re_safe($form["email"]); ?>" placeholder="Optional"></div>
                        <div class="rs-field"><label for="hometown">Home Town</label><input type="text" id="hometown" name="hometown" value="<?php echo re_safe($form["hometown"]); ?>"></div>
                        <div class="rs-field rs-span-2"><label for="postaladdress">Postal Address</label><textarea id="postaladdress" name="postaladdress" rows="3"><?php echo re_safe($form["postaladdress"]); ?></textarea></div>
                        <div class="rs-field"><label for="homeaddress">Home Address</label><textarea id="homeaddress" name="homeaddress" rows="3"><?php echo re_safe($form["homeaddress"]); ?></textarea></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head">
                        <h3><?php echo $isStudentEdit ? "Guardian" : "Emergency Contact"; ?></h3>
                        <p><?php echo $isStudentEdit ? "Emergency and next of kin details." : "Emergency contact details for this staff record."; ?></p>
                    </div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field">
                            <label for="relationship"><?php echo $isStudentEdit ? "Relationship" : "Contact Relationship"; ?></label>
                            <select id="relationship" name="relationship" required>
                                <option value=""><?php echo $isStudentEdit ? "Select relationship" : "Select contact relationship"; ?></option>
                                <option value="Father"<?php echo re_selected($form["relationship"], "Father"); ?>>Father</option>
                                <option value="Mother"<?php echo re_selected($form["relationship"], "Mother"); ?>>Mother</option>
                                <option value="Uncle"<?php echo re_selected($form["relationship"], "Uncle"); ?>>Uncle</option>
                                <option value="Brother"<?php echo re_selected($form["relationship"], "Brother"); ?>>Brother</option>
                                <option value="Sister"<?php echo re_selected($form["relationship"], "Sister"); ?>>Sister</option>
                                <option value="Daughter"<?php echo re_selected($form["relationship"], "Daughter"); ?>>Daughter</option>
                                <option value="Others"<?php echo re_selected($form["relationship"], "Others"); ?>>Others</option>
                            </select>
                        </div>
                        <div class="rs-field rs-span-2"><label for="nextoffullname"><?php echo $isStudentEdit ? "Next of Kin Full Name" : "Emergency Contact Full Name"; ?></label><input type="text" id="nextoffullname" name="nextoffullname" value="<?php echo re_safe($form["nextoffullname"]); ?>" required></div>
                        <div class="rs-field"><label for="nextofkincontact"><?php echo $isStudentEdit ? "Next of Kin Contact" : "Emergency Contact Number"; ?></label><input type="tel" id="nextofkincontact" name="nextofkincontact" value="<?php echo re_safe($form["nextofkincontact"]); ?>" required></div>
                    </div>
                </section>

                <div class="rs-form-foot">
                    <p><i class="fa fa-info-circle"></i> Leaving the picture field empty keeps the current image. Birthday will continue to recalculate age automatically.</p>
                    <button type="submit" name="register_update" class="rs-submit"><i class="fa fa-save"></i> Update User</button>
                </div>
            </form>
        </section>

        <aside class="rs-side">
            <section class="rs-panel">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Profile Snapshot</span>
                    <h2>Current account details</h2>
                    <p>A quick summary while you edit.</p>
                </div>
                <div class="rs-photo-card">
                    <div class="rs-photo-preview">
                        <img src="<?php echo re_safe($currentImage); ?>" alt="<?php echo re_safe($displayName !== "" ? $displayName : $form["userid"]); ?>">
                    </div>
                    <div class="rs-photo-copy">
                        <label>Current picture</label>
                        <small><?php echo re_safe($currentFilename !== "" ? $currentFilename : "No uploaded picture yet."); ?></small>
                        <small><?php echo re_safe($displayName !== "" ? $displayName : $form["userid"]); ?></small>
                    </div>
                </div>
                <div class="rs-tags">
                    <span><?php echo re_safe($form["systemtype"] !== "" ? $form["systemtype"] : "User"); ?></span>
                    <span><?php echo re_safe($form["staffstatus"] !== "" ? $form["staffstatus"] : "Status pending"); ?></span>
                    <?php if($isStudentEdit){ ?>
                    <span><?php echo re_safe($form["residencetype"] !== "" ? $form["residencetype"] : "Residence pending"); ?></span>
                    <?php } ?>
                </div>
                <ul class="rs-list">
                    <?php if($isStudentEdit){ ?>
                    <li>Username can be updated here if the student login name needs correction.</li>
                    <li>Student-specific fields like BECE index and residence type stay available in the edit flow.</li>
                    <li>The form stays easier to work with on phones and tablets.</li>
                    <?php } else { ?>
                    <li>Username can be updated here if the staff login name needs correction.</li>
                    <li>Emergency contact details are separated from the teacher's own contact information.</li>
                    <li>The form stays easier to work with on phones and tablets.</li>
                    <?php } ?>
                </ul>
            </section>

            <section class="rs-panel">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Quick Actions</span>
                    <h2>Move around easily</h2>
                    <p>Open the full profile or return to the staff list after saving.</p>
                </div>
                <div class="rs-recent">
                    <article class="rs-recent-item">
                        <div class="rs-recent-top">
                            <div>
                                <h3><?php echo re_safe($displayName !== "" ? $displayName : $form["userid"]); ?></h3>
                                <p><?php echo re_safe($form["userid"]); ?><?php if(trim((string)$form["username"]) !== ""){ ?> · <?php echo re_safe($form["username"]); ?><?php } ?></p>
                            </div>
                            <span class="rs-status"><?php echo re_safe($form["status"] !== "" ? $form["status"] : "active"); ?></span>
                        </div>
                        <div class="rs-tags">
                            <span><?php echo re_safe($form["gender"] !== "" ? $form["gender"] : "Gender pending"); ?></span>
                            <span><?php echo re_safe($form["branch_location"] !== "" ? $form["branch_location"] : "No branch"); ?></span>
                        </div>
                        <div class="rs-recent-foot">
                            <small>Use these links after saving.</small>
                            <div>
                                <a href="user-profile.php?view_user=<?php echo urlencode($form["userid"]); ?>">View Profile</a>
                                <a href="<?php echo re_safe($backPage); ?>"><?php echo re_safe($backLabel); ?></a>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </aside>
    </div>
    <?php } ?>
</main>
<?php include("footer.php"); ?>
<script>
(function () {
    var birthday = document.getElementById("birthday");
    var age = document.getElementById("age");
    var imageInput = document.getElementById("profileimage");
    var imagePreview = document.getElementById("rs-image-preview");

    function syncAge() {
        if (!birthday || !age || !birthday.value) {
            if (age) { age.value = ""; }
            return;
        }
        var dob = new Date(birthday.value + "T00:00:00");
        if (isNaN(dob.getTime())) {
            age.value = "";
            return;
        }
        var today = new Date();
        var years = today.getFullYear() - dob.getFullYear();
        var monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            years--;
        }
        age.value = years >= 0 ? years : "";
    }

    if (birthday) {
        birthday.addEventListener("change", syncAge);
        syncAge();
    }

    if (imageInput && imagePreview) {
        imageInput.addEventListener("change", function () {
            var file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
            if (!file) {
                imagePreview.src = "<?php echo re_safe($currentImage); ?>";
                return;
            }
            var reader = new FileReader();
            reader.onload = function (event) {
                imagePreview.src = event.target && event.target.result ? event.target.result : "<?php echo re_safe($currentImage); ?>";
            };
            reader.readAsDataURL(file);
        });
    }
}());
</script>
</body>
</html>

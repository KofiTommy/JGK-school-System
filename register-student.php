<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("house-master-utils.php");
include_once("online-admission-utils.php");
include_once("company.php");
ensure_house_tables($con);
ensure_online_admission_tables($con);

if(!house_master_can_manage_module($con, 'student_teacher_registration')){
    header("location:".house_master_landing_page());
    exit();
}

function rs_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function rs_alert($type, $message){
    $class = "rs-alert rs-alert--info";
    if($type === "success"){ $class = "rs-alert rs-alert--success"; }
    elseif($type === "error"){ $class = "rs-alert rs-alert--error"; }
    elseif($type === "warning"){ $class = "rs-alert rs-alert--warning"; }
    return "<div class=\"$class\">".rs_esc($message)."</div>";
}
function rs_old($form, $key){ return isset($form[$key]) ? $form[$key] : ""; }
function rs_selected($form, $key, $value){ return rs_old($form, $key) === $value ? " selected" : ""; }
function rs_checked($form, $key, $value){ return rs_old($form, $key) === $value ? " checked" : ""; }
function rs_normalize_birthday($value){
    $value = trim((string)$value);
    if($value === ""){ return ""; }
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])){ return sprintf("%04d-%02d-%02d", $m[1], $m[2], $m[3]); }
    if(preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])){ return sprintf("%04d-%02d-%02d", $m[3], $m[2], $m[1]); }
    $digits = preg_replace('/\D+/', '', $value);
    if(strlen($digits) === 8){
        $d = (int)substr($digits, 0, 2);
        $m = (int)substr($digits, 2, 2);
        $y = (int)substr($digits, 4, 4);
        if(checkdate($m, $d, $y)){ return sprintf("%04d-%02d-%02d", $y, $m, $d); }
    }
    $timestamp = strtotime($value);
    if($timestamp !== false){ return date("Y-m-d", $timestamp); }
    return false;
}
function rs_birthday_input_value($value){
    $normalized = rs_normalize_birthday($value);
    return $normalized === false ? "" : $normalized;
}
function rs_age($birthday){
    if(!$birthday){ return ""; }
    try{
        $dob = new DateTime($birthday);
        return (string)$dob->diff(new DateTime("today"))->y;
    }catch(Exception $e){
        return "";
    }
}
function rs_student_id($con){
    $year = date("Y");
    $count = 0;
    $res = mysqli_query($con, "SELECT COUNT(*) AS total FROM tblsystemuser");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){ $count = (int)$row["total"]; }
    $next = $count + 1;
    while(true){
        $candidate = "MB_".$year."/".$next;
        $esc = mysqli_real_escape_string($con, $candidate);
        $check = mysqli_query($con, "SELECT userid FROM tblsystemuser WHERE userid='$esc' LIMIT 1");
        if(!$check || mysqli_num_rows($check) === 0){ return $candidate; }
        $next++;
    }
}
function rs_date($value, $format){ $time = strtotime((string)$value); return $time ? date($format, $time) : ""; }
function rs_upload_error_text($code){
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
function rs_store_student_image($con, $userId, $file, &$errorMessage){
    $errorMessage = "";
    if(!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE || !isset($file["name"]) || trim((string)$file["name"]) === ""){
        return true;
    }
    if($file["error"] !== UPLOAD_ERR_OK){
        $errorMessage = rs_upload_error_text((int)$file["error"]);
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
        $errorMessage = "The student was saved, but the image record could not be updated.";
        return false;
    }
    return true;
}

$branchId = isset($_SESSION["BRANCHID"]) ? (string)$_SESSION["BRANCHID"] : "";
$branchIdEsc = mysqli_real_escape_string($con, $branchId);
$branchName = "Current Branch";
$branchRes = mysqli_query($con, "SELECT location FROM tblbranch WHERE branchid='$branchIdEsc' LIMIT 1");
if($branchRes && ($row = mysqli_fetch_array($branchRes, MYSQLI_ASSOC)) && trim((string)$row["location"]) !== ""){
    $branchName = trim((string)$row["location"]);
}
$companyName = isset($_CompanyName) && trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName) : "School Management System";

$form = array(
    "userid" => rs_student_id($con),
    "firstname" => "", "surname" => "", "othernames" => "", "gender" => "", "residencetype" => "",
    "birthday" => "", "age" => "", "beceindexnumber" => "", "houseid" => "", "postaladdress" => "",
    "homeaddress" => "", "hometown" => "", "email" => "", "mobile" => "", "religion" => "",
    "relationship" => "", "nextoffullname" => "", "nextofkincontact" => "", "username" => ""
);

$flashMessage = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

if(isset($_POST["register_user"])){
    foreach($form as $key => $value){
        $form[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ($key === "userid" ? rs_student_id($con) : "")));
    }
    $password = isset($_POST["password"]) ? (string)$_POST["password"] : "";
    $password2 = isset($_POST["password2"]) ? (string)$_POST["password2"] : "";
    $errors = array();

    $birthday = rs_normalize_birthday($form["birthday"]);
    if($birthday === false){ $errors[] = "Please choose a valid date of birth."; }
    else { $form["birthday"] = $birthday; $form["age"] = rs_age($birthday); }

    foreach(array(
        "firstname" => "First name", "surname" => "Surname", "gender" => "Gender", "residencetype" => "Residence type",
        "birthday" => "Date of birth", "mobile" => "Mobile number", "religion" => "Religion", "relationship" => "Relationship",
        "nextoffullname" => "Next of kin full name", "nextofkincontact" => "Next of kin contact", "username" => "Username"
    ) as $field => $label){
        if(trim((string)$form[$field]) === ""){ $errors[] = $label." is required."; }
    }
    if($password === ""){ $errors[] = "Password is required."; }
    elseif(strlen($password) < 6){ $errors[] = "Password must be at least 6 characters."; }
    if($password !== $password2){ $errors[] = "Passwords do not match."; }
    if($form["email"] !== "" && !filter_var($form["email"], FILTER_VALIDATE_EMAIL)){ $errors[] = "Please enter a valid email address."; }

    if(empty($errors)){
        $verification = substr(strtoupper(md5(uniqid('', true))), 0, 8);
        $hash = md5($password);
        $access = "user";
        $systemType = "Student";
        $stmt = mysqli_prepare($con, "INSERT INTO tblsystemuser(
            userid,firstname,surname,othernames,gender,residencetype,birthday,age,postaladdress,homeaddress,hometown,
            religion,relationship,BECEIndexNumber,nextofkin_fullname,nextofkin_contact,email,verificationcode,mobile,
            registereddatetime,status,username,password,accesslevel,systemtype,branchid
        ) VALUES(
            ?,?,?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,NOW(),'active',?,?,?, ?,?
        )");
        if($stmt){
            mysqli_stmt_bind_param(
                $stmt,
                str_repeat("s", 24),
                $form["userid"], $form["firstname"], $form["surname"], $form["othernames"], $form["gender"], $form["residencetype"], $form["birthday"], $form["age"],
                $form["postaladdress"], $form["homeaddress"], $form["hometown"], $form["religion"], $form["relationship"], $form["beceindexnumber"], $form["nextoffullname"],
                $form["nextofkincontact"], $form["email"], $verification, $form["mobile"], $form["username"], $hash, $access, $systemType, $branchId
            );
            if(mysqli_stmt_execute($stmt)){
                $type = "success";
                $message = "Student information saved successfully.";
                if($form["houseid"] !== ""){
                    if(assign_student_to_house($con, $form["userid"], $form["houseid"], isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "")){
                        $message = "Student information saved and house assigned successfully.";
                    }else{
                        $type = "warning";
                        $message = "Student information saved, but the house assignment could not be completed.";
                    }
                    $onlineApplication = online_admission_find_unlinked_registration_application($con, $branchId, $form["beceindexnumber"], $form["birthday"]);
                    if($onlineApplication){
                        if(online_admission_link_registered_student($con, $onlineApplication["applicationid"], $form["userid"], $form["houseid"])){
                            if($type === "success"){
                                $message = "Student information saved, house assigned, and online admission linked successfully.";
                            }else{
                                $message .= " The online admission record was linked.";
                            }
                        }elseif($type !== "warning"){
                            $type = "warning";
                            $message .= " The online admission record could not be linked.";
                        }
                    }
                }else{
                    $admissionHouseResult = online_admission_finalize_registration_house(
                        $con,
                        $form["userid"],
                        $branchId,
                        $form["beceindexnumber"],
                        $form["birthday"],
                        isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "",
                        ""
                    );
                    if($admissionHouseResult["linked"] && trim((string)$admissionHouseResult["message"]) !== ""){
                        $message = $admissionHouseResult["message"];
                    }elseif($admissionHouseResult["found"] && trim((string)$admissionHouseResult["message"]) !== ""){
                        $type = "warning";
                        $message .= " ".$admissionHouseResult["message"];
                    }
                }
                $imageError = "";
                if(isset($_FILES["studentimage"]) && !rs_store_student_image($con, $form["userid"], $_FILES["studentimage"], $imageError)){
                    $type = "warning";
                    $message .= " ".$imageError;
                }elseif(isset($_FILES["studentimage"]) && isset($_FILES["studentimage"]["error"]) && (int)$_FILES["studentimage"]["error"] === UPLOAD_ERR_OK){
                    $message .= " Profile image uploaded successfully.";
                }
                mysqli_stmt_close($stmt);
                $_SESSION["Message"] = rs_alert($type, $message);
                header("location:register-student.php");
                exit();
            }
            $flashMessage = mysqli_stmt_errno($stmt) == 1062
                ? rs_alert("warning", "That username or student ID already exists. Please try a different username.")
                : rs_alert("error", "Student information failed to save. Error: ".mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }else{
            $flashMessage = rs_alert("error", "The registration form could not be prepared right now.");
        }
    }else{
        $flashMessage = rs_alert("warning", implode(" ", $errors));
    }
}

$houses = array();
$houseRes = mysqli_query($con, "SELECT houseid,housename FROM tblhouse WHERE status='active' ORDER BY housename ASC");
if($houseRes){ while($row = mysqli_fetch_array($houseRes, MYSQLI_ASSOC)){ $houses[] = $row; } }

$stats = array("today" => 0, "week" => 0, "boarding" => 0, "day" => 0);
$statsRes = mysqli_query($con, "SELECT
    SUM(CASE WHEN DATE(registereddatetime)=CURDATE() THEN 1 ELSE 0 END) AS today_total,
    SUM(CASE WHEN YEARWEEK(registereddatetime,1)=YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END) AS week_total,
    SUM(CASE WHEN residencetype='Boarding' THEN 1 ELSE 0 END) AS boarding_total,
    SUM(CASE WHEN residencetype='Day' THEN 1 ELSE 0 END) AS day_total
    FROM tblsystemuser WHERE systemtype='Student' AND branchid='$branchIdEsc'");
if($statsRes && $row = mysqli_fetch_array($statsRes, MYSQLI_ASSOC)){
    $stats["today"] = (int)$row["today_total"];
    $stats["week"] = (int)$row["week_total"];
    $stats["boarding"] = (int)$row["boarding_total"];
    $stats["day"] = (int)$row["day_total"];
}

$recent = array();
$recentSql = "SELECT su.userid,su.firstname,su.surname,su.othernames,su.gender,su.residencetype,su.birthday,su.username,su.BECEIndexNumber,su.registereddatetime,su.status,h.housename
    FROM tblsystemuser su
    LEFT JOIN (
        SELECT sh.userid, sh.houseid
        FROM tblstudenthouse sh
        INNER JOIN (
            SELECT userid, MAX(datetimeentry) AS latest_date FROM tblstudenthouse WHERE status='active' GROUP BY userid
        ) latest ON latest.userid=sh.userid AND latest.latest_date=sh.datetimeentry
        WHERE sh.status='active'
    ) sh_active ON sh_active.userid=su.userid
    LEFT JOIN tblhouse h ON h.houseid=sh_active.houseid
    WHERE DATE(su.registereddatetime)=CURDATE() AND su.systemtype='Student' AND su.branchid='$branchIdEsc'
    ORDER BY su.registereddatetime DESC LIMIT 16";
$recentRes = mysqli_query($con, $recentSql);
if($recentRes){ while($row = mysqli_fetch_array($recentRes, MYSQLI_ASSOC)){ $recent[] = $row; } }

$signedInName = isset($_SESSION["FULLNAME"]) ? trim((string)$_SESSION["FULLNAME"]) : "Admissions Officer";
?>
<!DOCTYPE html>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/register-student.css">
</head>
<body class="body-style student-register-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="rs-shell">
    <?php if($flashMessage !== ""){ ?><div class="rs-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="rs-hero">
        <div>
            <span class="rs-kicker"><i class="fa fa-address-card-o"></i> Admissions Workspace</span>
            <h1>Register students with less clutter and better flow.</h1>
            <p>Identity first, contact and guardian details next, then login details at the end. The form stays usable on mobile and keeps values when something needs fixing.</p>
            <div class="rs-pills">
                <span>Mobile friendly</span>
                <span>Auto age from birthday</span>
                <span>Optional house assignment</span>
            </div>
        </div>
        <aside class="rs-hero-card">
            <h2><?php echo rs_esc($branchName); ?></h2>
            <p><?php echo rs_esc($companyName); ?></p>
            <div class="rs-metrics">
                <article><span>Today</span><strong><?php echo number_format($stats["today"]); ?></strong></article>
                <article><span>This Week</span><strong><?php echo number_format($stats["week"]); ?></strong></article>
                <article><span>Active Houses</span><strong><?php echo number_format(count($houses)); ?></strong></article>
                <article><span>Boarding / Day</span><strong><?php echo number_format($stats["boarding"])." / ".number_format($stats["day"]); ?></strong></article>
            </div>
            <small>Signed in as <?php echo rs_esc($signedInName); ?></small>
        </aside>
    </section>

    <div class="rs-layout">
        <section class="rs-panel rs-panel--form">
            <div class="rs-panel-head">
                <div>
                    <span class="rs-kicker rs-kicker--dark">Student Form</span>
                    <h2>New Student Registration</h2>
                    <p>The same registration logic is preserved, but the page is now grouped into easier steps.</p>
                </div>
                <div class="rs-id-chip">
                    <span>Generated ID</span>
                    <strong><?php echo rs_esc($form["userid"]); ?></strong>
                </div>
            </div>

            <form method="post" action="register-student.php" class="rs-form" enctype="multipart/form-data">
                <section class="rs-section rs-section--photo">
                    <div class="rs-section-head"><h3>Student Image</h3><p>Add a passport-style image during registration so the profile is complete immediately.</p></div>
                    <div class="rs-photo-card">
                        <div class="rs-photo-preview">
                            <img src="uploads/comm.gif" alt="Student image preview" id="rs-image-preview">
                        </div>
                        <div class="rs-photo-copy">
                            <label for="studentimage">Upload Photo</label>
                            <input type="file" id="studentimage" name="studentimage" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                            <small>Accepted formats: JPG, PNG, GIF, WEBP. Maximum size: 5MB.</small>
                            <small>If the form shows an error, please re-select the image before saving again.</small>
                        </div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Identity</h3><p>Core student bio-data.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="userid">Student ID</label><input type="text" id="userid" name="userid" value="<?php echo rs_esc($form["userid"]); ?>" readonly></div>
                        <div class="rs-field"><label for="firstname">First Name</label><input type="text" id="firstname" name="firstname" value="<?php echo rs_esc(rs_old($form, "firstname")); ?>" required></div>
                        <div class="rs-field"><label for="surname">Surname</label><input type="text" id="surname" name="surname" value="<?php echo rs_esc(rs_old($form, "surname")); ?>" required></div>
                        <div class="rs-field rs-span-2"><label for="othernames">Other Names</label><input type="text" id="othernames" name="othernames" value="<?php echo rs_esc(rs_old($form, "othernames")); ?>"></div>
                        <div class="rs-field"><label for="beceindexnumber">BECE Index Number</label><input type="text" id="beceindexnumber" name="beceindexnumber" value="<?php echo rs_esc(rs_old($form, "beceindexnumber")); ?>" placeholder="Optional"></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>School Profile</h3><p>Residence, religion, birthday and house.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field">
                            <label>Gender</label>
                            <div class="rs-choices">
                                <label class="rs-choice"><input type="radio" name="gender" value="Male"<?php echo rs_checked($form, "gender", "Male"); ?>><span>Male</span></label>
                                <label class="rs-choice"><input type="radio" name="gender" value="Female"<?php echo rs_checked($form, "gender", "Female"); ?>><span>Female</span></label>
                            </div>
                        </div>
                        <div class="rs-field">
                            <label>Residence Type</label>
                            <div class="rs-choices">
                                <label class="rs-choice"><input type="radio" name="residencetype" value="Day"<?php echo rs_checked($form, "residencetype", "Day"); ?>><span>Day</span></label>
                                <label class="rs-choice"><input type="radio" name="residencetype" value="Boarding"<?php echo rs_checked($form, "residencetype", "Boarding"); ?>><span>Boarding</span></label>
                            </div>
                        </div>
                        <div class="rs-field">
                            <label for="religion">Religion</label>
                            <select id="religion" name="religion" required>
                                <option value="">Select religion</option>
                                <option value="Christian"<?php echo rs_selected($form, "religion", "Christian"); ?>>Christian</option>
                                <option value="Muslim"<?php echo rs_selected($form, "religion", "Muslim"); ?>>Muslim</option>
                                <option value="Tradition"<?php echo rs_selected($form, "religion", "Tradition"); ?>>Tradition</option>
                                <option value="Others"<?php echo rs_selected($form, "religion", "Others"); ?>>Others</option>
                            </select>
                        </div>
                        <div class="rs-field"><label for="birthday">Date of Birth</label><input type="date" id="birthday" name="birthday" value="<?php echo rs_esc(rs_birthday_input_value(rs_old($form, "birthday"))); ?>" max="<?php echo date("Y-m-d"); ?>" required></div>
                        <div class="rs-field"><label for="age">Age</label><input type="text" id="age" name="age" value="<?php echo rs_esc(rs_old($form, "age")); ?>" readonly></div>
                        <div class="rs-field"><label for="houseid">House</label><select id="houseid" name="houseid"><option value="">No house selected yet</option><?php foreach($houses as $house){ ?><option value="<?php echo rs_esc($house["houseid"]); ?>"<?php echo rs_selected($form, "houseid", $house["houseid"]); ?>><?php echo rs_esc($house["housename"]); ?></option><?php } ?></select><small>Optional during registration.</small></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Contact</h3><p>Communication and address details.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="mobile">Mobile Number</label><input type="tel" id="mobile" name="mobile" value="<?php echo rs_esc(rs_old($form, "mobile")); ?>" required></div>
                        <div class="rs-field"><label for="email">Email Address</label><input type="email" id="email" name="email" value="<?php echo rs_esc(rs_old($form, "email")); ?>" placeholder="Optional"></div>
                        <div class="rs-field"><label for="hometown">Home Town</label><input type="text" id="hometown" name="hometown" value="<?php echo rs_esc(rs_old($form, "hometown")); ?>"></div>
                        <div class="rs-field rs-span-2"><label for="postaladdress">Postal Address</label><textarea id="postaladdress" name="postaladdress" rows="3"><?php echo rs_esc(rs_old($form, "postaladdress")); ?></textarea></div>
                        <div class="rs-field"><label for="homeaddress">Home Address</label><textarea id="homeaddress" name="homeaddress" rows="3"><?php echo rs_esc(rs_old($form, "homeaddress")); ?></textarea></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Guardian</h3><p>Emergency contact details.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="relationship">Relationship</label><select id="relationship" name="relationship" required><option value="">Select relationship</option><option value="Father"<?php echo rs_selected($form, "relationship", "Father"); ?>>Father</option><option value="Mother"<?php echo rs_selected($form, "relationship", "Mother"); ?>>Mother</option><option value="Uncle"<?php echo rs_selected($form, "relationship", "Uncle"); ?>>Uncle</option><option value="Brother"<?php echo rs_selected($form, "relationship", "Brother"); ?>>Brother</option><option value="Sister"<?php echo rs_selected($form, "relationship", "Sister"); ?>>Sister</option><option value="Daughter"<?php echo rs_selected($form, "relationship", "Daughter"); ?>>Daughter</option><option value="Others"<?php echo rs_selected($form, "relationship", "Others"); ?>>Others</option></select></div>
                        <div class="rs-field rs-span-2"><label for="nextoffullname">Next of Kin Full Name</label><input type="text" id="nextoffullname" name="nextoffullname" value="<?php echo rs_esc(rs_old($form, "nextoffullname")); ?>" required></div>
                        <div class="rs-field"><label for="nextofkincontact">Next of Kin Contact</label><input type="tel" id="nextofkincontact" name="nextofkincontact" value="<?php echo rs_esc(rs_old($form, "nextofkincontact")); ?>" required></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Account Access</h3><p>Create the login at the end.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?php echo rs_esc(rs_old($form, "username")); ?>" required></div>
                        <div class="rs-field"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
                        <div class="rs-field"><label for="password2">Repeat Password</label><input type="password" id="password2" name="password2" required></div>
                    </div>
                </section>

                <div class="rs-form-foot">
                    <p><i class="fa fa-info-circle"></i> The student will be saved under the current branch and can still be edited later.</p>
                    <button type="submit" name="register_user" class="rs-submit"><i class="fa fa-save"></i> Save Student</button>
                </div>
            </form>
        </section>

        <aside class="rs-side">
            <section class="rs-panel">
                <div class="rs-side-head"><span class="rs-kicker rs-kicker--dark">Quick Notes</span><h2>Why this feels easier</h2></div>
                <ul class="rs-list">
                    <li>Birthday uses a phone-friendly date picker and fills age automatically.</li>
                    <li>House assignment is available immediately, but it stays optional.</li>
                    <li>Failed validation keeps the form values on the page.</li>
                </ul>
            </section>

            <section class="rs-panel">
                <div class="rs-side-head"><span class="rs-kicker rs-kicker--dark">Today</span><h2>Recent Student Registrations</h2></div>
                <div class="rs-search"><i class="fa fa-search"></i><input type="search" id="rs-search" placeholder="Search name, ID, username or house"></div>
                <div class="rs-recent" id="rs-recent">
                    <?php if(count($recent) > 0){ foreach($recent as $row){ $name = trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]); $search = strtolower($name." ".$row["userid"]." ".$row["username"]." ".$row["housename"]); ?>
                    <article class="rs-recent-item" data-search="<?php echo rs_esc($search); ?>">
                        <div class="rs-recent-top">
                            <div><h3><?php echo rs_esc($name !== "" ? $name : $row["userid"]); ?></h3><p><?php echo rs_esc($row["userid"]); ?><?php if(trim((string)$row["username"]) !== ""){ ?> · <?php echo rs_esc($row["username"]); ?><?php } ?></p></div>
                            <span class="rs-status"><?php echo rs_esc($row["status"]); ?></span>
                        </div>
                        <div class="rs-tags">
                            <span><?php echo rs_esc($row["gender"]); ?></span>
                            <span><?php echo rs_esc($row["residencetype"] !== "" ? $row["residencetype"] : "Residence pending"); ?></span>
                            <span><?php echo rs_esc($row["housename"] !== "" ? $row["housename"] : "No house"); ?></span>
                        </div>
                        <div class="rs-recent-foot">
                            <small><?php echo rs_esc(rs_date($row["registereddatetime"], "d M Y, g:i a")); ?></small>
                            <div><a href="user-profile.php?view_user=<?php echo urlencode($row["userid"]); ?>">View</a><a href="register_edit.php?edit_user=<?php echo urlencode($row["userid"]); ?>">Edit</a></div>
                        </div>
                    </article>
                    <?php } } else { ?>
                    <div class="rs-empty"><h3>No student registrations yet today</h3><p>Newly saved students will appear here for quick review.</p></div>
                    <?php } ?>
                </div>
            </section>
        </aside>
    </div>
</main>
<?php include("footer.php"); ?>
<script>
(function () {
    var birthday = document.getElementById("birthday");
    var age = document.getElementById("age");
    var search = document.getElementById("rs-search");
    var items = document.querySelectorAll(".rs-recent-item");
    var imageInput = document.getElementById("studentimage");
    var imagePreview = document.getElementById("rs-image-preview");
    function syncAge() {
        if (!birthday || !age || !birthday.value) { if (age) { age.value = ""; } return; }
        var dob = new Date(birthday.value + "T00:00:00");
        if (isNaN(dob.getTime())) { age.value = ""; return; }
        var today = new Date();
        var years = today.getFullYear() - dob.getFullYear();
        var monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) { years--; }
        age.value = years >= 0 ? years : "";
    }
    function filterItems() {
        if (!search) { return; }
        var term = search.value.toLowerCase().trim();
        items.forEach(function (item) {
            item.style.display = (item.getAttribute("data-search") || "").indexOf(term) !== -1 ? "" : "none";
        });
    }
    if (birthday) { birthday.addEventListener("change", syncAge); syncAge(); }
    if (search) { search.addEventListener("input", filterItems); }
    if (imageInput && imagePreview) {
        imageInput.addEventListener("change", function () {
            var file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
            if (!file) {
                imagePreview.src = "uploads/comm.gif";
                return;
            }
            var reader = new FileReader();
            reader.onload = function (event) {
                imagePreview.src = event.target && event.target.result ? event.target.result : "uploads/comm.gif";
            };
            reader.readAsDataURL(file);
        });
    }
}());
</script>
</body>
</html>

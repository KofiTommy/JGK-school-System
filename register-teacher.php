<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("house-master-utils.php");
include_once("company.php");
if(!house_master_can_manage_module($con, 'student_teacher_registration')){ header("location:".house_master_landing_page()); exit(); }

function tesc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function talert($type,$message){
    $class="rs-alert rs-alert--info";
    if($type==="success"){$class="rs-alert rs-alert--success";}
    elseif($type==="error"){$class="rs-alert rs-alert--error";}
    elseif($type==="warning"){$class="rs-alert rs-alert--warning";}
    return "<div class=\"$class\">".tesc($message)."</div>";
}
function told($form,$key){ return isset($form[$key]) ? $form[$key] : ""; }
function tselected($form,$key,$value){ return told($form,$key)===$value ? " selected" : ""; }
function tchecked($form,$key,$value){ return told($form,$key)===$value ? " checked" : ""; }
function tbirthday($value){
    $value=trim((string)$value);
    if($value===""){ return ""; }
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/',$value,$m) && checkdate((int)$m[2],(int)$m[3],(int)$m[1])){ return sprintf("%04d-%02d-%02d",$m[1],$m[2],$m[3]); }
    if(preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/',$value,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])){ return sprintf("%04d-%02d-%02d",$m[3],$m[2],$m[1]); }
    $digits=preg_replace('/\D+/','',$value);
    if(strlen($digits)===8){
        $d=(int)substr($digits,0,2); $m=(int)substr($digits,2,2); $y=(int)substr($digits,4,4);
        if(checkdate($m,$d,$y)){ return sprintf("%04d-%02d-%02d",$y,$m,$d); }
    }
    $timestamp=strtotime($value);
    if($timestamp!==false){ return date("Y-m-d",$timestamp); }
    return false;
}
function tbirthday_input_value($value){
    $normalized=tbirthday($value);
    return $normalized===false ? "" : $normalized;
}
function tage($birthday){
    if(!$birthday){ return ""; }
    try{ $dob=new DateTime($birthday); return (string)$dob->diff(new DateTime("today"))->y; }catch(Exception $e){ return ""; }
}
function tuserid($con){
    $year=date("Y"); $count=0;
    $res=mysqli_query($con,"SELECT COUNT(*) AS total FROM tblsystemuser");
    if($res && $row=mysqli_fetch_array($res,MYSQLI_ASSOC)){ $count=(int)$row["total"]; }
    $next=$count+1;
    while(true){
        $candidate="MB_".$year."/".$next;
        $esc=mysqli_real_escape_string($con,$candidate);
        $check=mysqli_query($con,"SELECT userid FROM tblsystemuser WHERE userid='$esc' LIMIT 1");
        if(!$check || mysqli_num_rows($check)===0){ return $candidate; }
        $next++;
    }
}
function tupload_error($code){
    $map=array(
        UPLOAD_ERR_INI_SIZE=>"The image is larger than the server allows.",
        UPLOAD_ERR_FORM_SIZE=>"The image is larger than the allowed form size.",
        UPLOAD_ERR_PARTIAL=>"The image upload was interrupted. Please try again.",
        UPLOAD_ERR_NO_TMP_DIR=>"The server is missing a temporary upload folder.",
        UPLOAD_ERR_CANT_WRITE=>"The server could not write the image file.",
        UPLOAD_ERR_EXTENSION=>"The upload was stopped by a server extension."
    );
    return isset($map[$code]) ? $map[$code] : "The image could not be uploaded.";
}
function tstore_image($con,$userId,$file,&$error){
    $error="";
    if(!isset($file["error"]) || $file["error"]===UPLOAD_ERR_NO_FILE || !isset($file["name"]) || trim((string)$file["name"])===""){ return true; }
    if($file["error"]!==UPLOAD_ERR_OK){ $error=tupload_error((int)$file["error"]); return false; }
    if(!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])){ $error="The selected image upload is invalid."; return false; }
    if(isset($file["size"]) && (int)$file["size"]>5*1024*1024){ $error="The image is too large. Please use a file smaller than 5MB."; return false; }
    $ext=strtolower(pathinfo((string)$file["name"],PATHINFO_EXTENSION));
    if(!in_array($ext,array("jpg","jpeg","png","gif","webp"),true)){ $error="Please upload a JPG, PNG, GIF, or WEBP image."; return false; }
    $mime="";
    if(function_exists("finfo_open")){
        $finfo=@finfo_open(FILEINFO_MIME_TYPE);
        if($finfo){ $mime=(string)@finfo_file($finfo,$file["tmp_name"]); finfo_close($finfo); }
    }
    if($mime!=="" && !in_array($mime,array("image/jpeg","image/png","image/gif","image/webp"),true)){ $error="The selected file is not a valid image."; return false; }
    $uploadDir=__DIR__.DIRECTORY_SEPARATOR."uploads";
    if(!is_dir($uploadDir)){ $error="The uploads folder is missing on the server."; return false; }
    $safeId=preg_replace('/[^A-Za-z0-9_-]+/','-',(string)$userId);
    $storedName=$safeId."-".date("YmdHis")."-".substr(md5(uniqid('',true)),0,8).".".$ext;
    $destination=$uploadDir.DIRECTORY_SEPARATOR.$storedName;
    if(!move_uploaded_file($file["tmp_name"],$destination)){ $error="The image could not be moved to the uploads folder."; return false; }
    $userEsc=mysqli_real_escape_string($con,(string)$userId);
    $storedEsc=mysqli_real_escape_string($con,$storedName);
    $update=mysqli_query($con,"UPDATE tblsystemuser SET filename='$storedEsc', uploadeddatetime=NOW() WHERE userid='$userEsc' LIMIT 1");
    if(!$update){ @unlink($destination); $error="Teacher information was saved, but the image record could not be updated."; return false; }
    return true;
}
function timg($filename){
    $filename=trim((string)$filename);
    if($filename!=="" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename)){ return "uploads/".rawurlencode($filename); }
    return "uploads/comm.gif";
}
function tdate($value,$format){ $time=strtotime((string)$value); return $time ? date($format,$time) : ""; }

$branchId=isset($_SESSION["BRANCHID"]) ? (string)$_SESSION["BRANCHID"] : "";
$branchIdEsc=mysqli_real_escape_string($con,$branchId);
$branchName="Current Branch";
$branchPhone="";
$branchRes=mysqli_query($con,"SELECT location,telephone1 FROM tblbranch WHERE branchid='$branchIdEsc' LIMIT 1");
if($branchRes && ($row=mysqli_fetch_array($branchRes,MYSQLI_ASSOC))){
    if(trim((string)$row["location"])!==""){ $branchName=trim((string)$row["location"]); }
    $branchPhone=trim((string)$row["telephone1"]);
}
$companyName=(isset($_CompanyName) && trim((string)$_CompanyName)!=="") ? trim((string)$_CompanyName) : "School Management System";

$form=array(
    "userid"=>tuserid($con),"firstname"=>"","surname"=>"","othernames"=>"","gender"=>"","birthday"=>"","age"=>"",
    "postaladdress"=>"","homeaddress"=>"","hometown"=>"","email"=>"","mobile"=>"","religion"=>"","relationship"=>"",
    "nextoffullname"=>"","nextofkincontact"=>"","username"=>"","recipient"=>""
);
$flashMessage=isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"]="";

if(isset($_POST["register_user"])){
    foreach($form as $key=>$value){ $form[$key]=trim((string)(isset($_POST[$key]) ? $_POST[$key] : ($key==="userid" ? tuserid($con) : ""))); }
    $password=isset($_POST["password"]) ? (string)$_POST["password"] : "";
    $password2=isset($_POST["password2"]) ? (string)$_POST["password2"] : "";
    $errors=array();
    $birthday=tbirthday($form["birthday"]);
    if($birthday===false){ $errors[]="Please choose a valid date of birth."; }
    else{ $form["birthday"]=$birthday; $form["age"]=tage($birthday); }
    foreach(array(
        "firstname"=>"First name","surname"=>"Surname","gender"=>"Gender","birthday"=>"Date of birth","mobile"=>"Mobile number",
        "email"=>"Email address","religion"=>"Religion","relationship"=>"Relationship","nextoffullname"=>"Next of kin full name",
        "nextofkincontact"=>"Next of kin contact","username"=>"Username","recipient"=>"Staff category"
    ) as $field=>$label){
        if(trim((string)$form[$field])===""){ $errors[]=$label." is required."; }
    }
    if($password===""){ $errors[]="Password is required."; }
    elseif(strlen($password)<6){ $errors[]="Password must be at least 6 characters."; }
    if($password!==$password2){ $errors[]="Passwords do not match."; }
    if($form["email"]!=="" && !filter_var($form["email"],FILTER_VALIDATE_EMAIL)){ $errors[]="Please enter a valid email address."; }

    if(empty($errors)){
        $passwordHash=md5($password);
        $access="user"; $systemType="Teacher";
        $stmt=mysqli_prepare($con,"INSERT INTO tblsystemuser(
            userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,
            religion,relationship,nextofkin_fullname,nextofkin_contact,email,mobile,registereddatetime,status,
            username,password,accesslevel,systemtype,staffstatus,branchid
        ) VALUES(
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,NOW(),'active',
            ?,?,?,?,?,?
        )");
        if($stmt){
            mysqli_stmt_bind_param(
                $stmt,
                str_repeat("s",22),
                $form["userid"],$form["firstname"],$form["surname"],$form["othernames"],$form["gender"],$form["birthday"],$form["age"],$form["postaladdress"],$form["homeaddress"],$form["hometown"],
                $form["religion"],$form["relationship"],$form["nextoffullname"],$form["nextofkincontact"],$form["email"],$form["mobile"],
                $form["username"],$passwordHash,$access,$systemType,$form["recipient"],$branchId
            );
            if(mysqli_stmt_execute($stmt)){
                $messageType="success";
                $messageText="Teacher information saved successfully.";
                $imageError="";
                if(isset($_FILES["teacherimage"]) && !tstore_image($con,$form["userid"],$_FILES["teacherimage"],$imageError)){
                    $messageType="warning";
                    $messageText.=" ".$imageError;
                }elseif(isset($_FILES["teacherimage"]) && isset($_FILES["teacherimage"]["error"]) && (int)$_FILES["teacherimage"]["error"]===UPLOAD_ERR_OK){
                    $messageText.=" Profile image uploaded successfully.";
                }
                mysqli_stmt_close($stmt);
                $_SESSION["Message"]=talert($messageType,$messageText);
                header("location:register-teacher.php");
                exit();
            }
            $flashMessage=mysqli_stmt_errno($stmt)==1062
                ? talert("warning","That username or teacher ID already exists. Please try a different username.")
                : talert("error","Teacher information failed to save. Error: ".mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }else{
            $flashMessage=talert("error","The teacher registration form could not be prepared right now.");
        }
    }else{
        $flashMessage=talert("warning",implode(" ",$errors));
    }
}

$stats=array("today"=>0,"week"=>0,"teaching"=>0,"non_teaching"=>0);
$statsRes=mysqli_query($con,"SELECT
    SUM(CASE WHEN DATE(registereddatetime)=CURDATE() THEN 1 ELSE 0 END) AS today_total,
    SUM(CASE WHEN YEARWEEK(registereddatetime,1)=YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END) AS week_total,
    SUM(CASE WHEN staffstatus='Teaching Staff' THEN 1 ELSE 0 END) AS teaching_total,
    SUM(CASE WHEN staffstatus IN ('Non Teaching Staff','Non-Teaching Staff') THEN 1 ELSE 0 END) AS non_teaching_total
    FROM tblsystemuser WHERE systemtype='Teacher' AND branchid='$branchIdEsc'");
if($statsRes && ($row=mysqli_fetch_array($statsRes,MYSQLI_ASSOC))){
    $stats["today"]=(int)$row["today_total"];
    $stats["week"]=(int)$row["week_total"];
    $stats["teaching"]=(int)$row["teaching_total"];
    $stats["non_teaching"]=(int)$row["non_teaching_total"];
}

$recentTeachers=array();
$recentRes=mysqli_query($con,"SELECT userid,firstname,surname,othernames,gender,birthday,username,status,registereddatetime,staffstatus,filename
    FROM tblsystemuser
    WHERE systemtype='Teacher' AND branchid='$branchIdEsc' AND DATE(registereddatetime)=CURDATE()
    ORDER BY registereddatetime DESC LIMIT 18");
if($recentRes){ while($row=mysqli_fetch_array($recentRes,MYSQLI_ASSOC)){ $recentTeachers[]=$row; } }
$signedInName=isset($_SESSION["FULLNAME"]) ? trim((string)$_SESSION["FULLNAME"]) : "Administrator";
?>
<!DOCTYPE html>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/register-student.css">
<link rel="stylesheet" type="text/css" href="css/register-teacher.css">
</head>
<body class="body-style student-register-page teacher-register-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="rs-shell">
    <?php if($flashMessage!==""){ ?><div class="rs-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="rs-hero">
        <div>
            <span class="rs-kicker"><i class="fa fa-id-badge"></i> Staff Onboarding</span>
            <h1>Register teachers</h1>
            <p>The screen now follows the actual onboarding sequence better: photo and identity first, then personal details, support contacts, and login setup in one responsive page.</p>
            <div class="rs-pills">
                <span>Mobile friendly</span>
                <span>Photo upload in one save</span>
                <span>Same-day review panel</span>
            </div>
        </div>
        <aside class="rs-hero-card">
            <span class="rs-kicker">Branch Snapshot</span>
            <h2><?php echo tesc($branchName); ?></h2>
            <p><?php echo tesc($companyName); ?></p>
            <div class="rs-metrics">
                <article><span>Today</span><strong><?php echo number_format($stats["today"]); ?></strong></article>
                <article><span>This Week</span><strong><?php echo number_format($stats["week"]); ?></strong></article>
                <article><span>Teaching Staff</span><strong><?php echo number_format($stats["teaching"]); ?></strong></article>
                <article><span>Non-Teaching</span><strong><?php echo number_format($stats["non_teaching"]); ?></strong></article>
            </div>
            <small>Signed in as <?php echo tesc($signedInName); ?><?php if($branchPhone!==""){ ?> · <?php echo tesc($branchPhone); ?><?php } ?></small>
        </aside>
    </section>

    <div class="rs-layout">
        <section class="rs-panel rs-panel--form">
            <div class="rs-panel-head">
                <div>
                    <span class="rs-kicker rs-kicker--dark">Teacher Form</span>
                    <h2>New Teacher Registration</h2>
                    <p>Enter the teacher details below and save the record.</p>
                </div>
                <div class="rs-id-chip">
                    <span>Generated ID</span>
                    <strong><?php echo tesc($form["userid"]); ?></strong>
                </div>
            </div>

            <form method="post" action="register-teacher.php" class="rs-form" enctype="multipart/form-data">
                <section class="rs-section rs-section--photo">
                    <div class="rs-section-head"><h3>Profile Image</h3><p>Add a staff image during registration so the teacher profile is complete immediately.</p></div>
                    <div class="rs-photo-card">
                        <div class="rs-photo-preview"><img src="uploads/comm.gif" alt="Teacher image preview" id="teacherimage_preview"></div>
                        <div class="rs-photo-copy">
                            <label for="teacherimage">Upload Photo</label>
                            <input type="file" id="teacherimage" name="teacherimage" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                            <small>Accepted formats: JPG, PNG, GIF, WEBP. Maximum size: 5MB.</small>
                            <small>If the form returns an error, please re-select the image before saving again.</small>
                        </div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Identity</h3><p>Core teacher bio-data and staff category.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="userid">Teacher ID</label><input type="text" id="userid" name="userid" value="<?php echo tesc($form["userid"]); ?>" readonly></div>
                        <div class="rs-field"><label for="firstname">First Name</label><input type="text" id="firstname" name="firstname" value="<?php echo tesc(told($form,"firstname")); ?>" required></div>
                        <div class="rs-field"><label for="surname">Surname</label><input type="text" id="surname" name="surname" value="<?php echo tesc(told($form,"surname")); ?>" required></div>
                        <div class="rs-field rs-span-2"><label for="othernames">Other Names</label><input type="text" id="othernames" name="othernames" value="<?php echo tesc(told($form,"othernames")); ?>"></div>
                        <div class="rs-field"><label for="recipient">Staff Category</label><select id="recipient" name="recipient" required><option value="">Select category</option><option value="Teaching Staff"<?php echo tselected($form,"recipient","Teaching Staff"); ?>>Teaching Staff</option><option value="Non Teaching Staff"<?php echo tselected($form,"recipient","Non Teaching Staff"); ?>>Non Teaching Staff</option></select></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Personal Details</h3><p>Gender, date of birth, and direct contact details.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field">
                            <label>Gender</label>
                            <div class="rs-choices">
                                <label class="rs-choice"><input type="radio" name="gender" value="male"<?php echo tchecked($form,"gender","male"); ?>><span>Male</span></label>
                                <label class="rs-choice"><input type="radio" name="gender" value="female"<?php echo tchecked($form,"gender","female"); ?>><span>Female</span></label>
                            </div>
                        </div>
                        <div class="rs-field"><label for="birthday">Date of Birth</label><input type="date" id="birthday" name="birthday" value="<?php echo tesc(tbirthday_input_value(told($form,"birthday"))); ?>" max="<?php echo date("Y-m-d"); ?>" required></div>
                        <div class="rs-field"><label for="age">Age</label><input type="text" id="age" name="age" value="<?php echo tesc(told($form,"age")); ?>" readonly></div>
                        <div class="rs-field"><label for="mobile">Mobile Number</label><input type="tel" id="mobile" name="mobile" value="<?php echo tesc(told($form,"mobile")); ?>" required></div>
                        <div class="rs-field"><label for="email">Email Address</label><input type="email" id="email" name="email" value="<?php echo tesc(told($form,"email")); ?>" required></div>
                        <div class="rs-field"><label for="religion">Religion</label><select id="religion" name="religion" required><option value="">Select religion</option><option value="Christian"<?php echo tselected($form,"religion","Christian"); ?>>Christian</option><option value="Muslim"<?php echo tselected($form,"religion","Muslim"); ?>>Muslim</option><option value="Tradition"<?php echo tselected($form,"religion","Tradition"); ?>>Tradition</option><option value="Others"<?php echo tselected($form,"religion","Others"); ?>>Others</option></select></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Address</h3><p>Keep location details together for quick review.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field rs-span-2"><label for="postaladdress">Postal Address</label><textarea id="postaladdress" name="postaladdress" rows="3"><?php echo tesc(told($form,"postaladdress")); ?></textarea></div>
                        <div class="rs-field"><label for="homeaddress">Home Address</label><textarea id="homeaddress" name="homeaddress" rows="3"><?php echo tesc(told($form,"homeaddress")); ?></textarea></div>
                        <div class="rs-field"><label for="hometown">Home Town</label><input type="text" id="hometown" name="hometown" value="<?php echo tesc(told($form,"hometown")); ?>"></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Next of Kin</h3><p>Emergency support contact details.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="relationship">Relationship</label><select id="relationship" name="relationship" required><option value="">Select relationship</option><option value="Father"<?php echo tselected($form,"relationship","Father"); ?>>Father</option><option value="Mother"<?php echo tselected($form,"relationship","Mother"); ?>>Mother</option><option value="Uncle"<?php echo tselected($form,"relationship","Uncle"); ?>>Uncle</option><option value="Brother"<?php echo tselected($form,"relationship","Brother"); ?>>Brother</option><option value="Sister"<?php echo tselected($form,"relationship","Sister"); ?>>Sister</option><option value="Daughter"<?php echo tselected($form,"relationship","Daughter"); ?>>Daughter</option><option value="Others"<?php echo tselected($form,"relationship","Others"); ?>>Others</option></select></div>
                        <div class="rs-field rs-span-2"><label for="nextoffullname">Full Name</label><input type="text" id="nextoffullname" name="nextoffullname" value="<?php echo tesc(told($form,"nextoffullname")); ?>" required></div>
                        <div class="rs-field"><label for="nextofkincontact">Contact</label><input type="tel" id="nextofkincontact" name="nextofkincontact" value="<?php echo tesc(told($form,"nextofkincontact")); ?>" required></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-section-head"><h3>Account Access</h3><p>Create the teacher login at the end of the process.</p></div>
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?php echo tesc(told($form,"username")); ?>" required></div>
                        <div class="rs-field"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
                        <div class="rs-field"><label for="password2">Repeat Password</label><input type="password" id="password2" name="password2" required></div>
                    </div>
                </section>

                <div class="rs-form-foot">
                    <p><i class="fa fa-info-circle"></i> The teacher is saved to the current branch and can still be updated later from the edit screen.</p>
                    <button type="submit" name="register_user" class="rs-submit"><i class="fa fa-save"></i> Save Teacher</button>
                </div>
            </form>
        </section>

        <aside class="rs-side">
            <section class="rs-panel">
                <div class="rs-side-head"><span class="rs-kicker rs-kicker--dark">Quick Notes</span><h2>Why this layout works better</h2></div>
                <ul class="rs-list">
                    <li>The profile image, teacher details, and account setup now live in one screen.</li>
                    <li>Date of birth uses a phone-friendly input and calculates age automatically.</li>
                    <li>The recent-teacher panel makes same-day follow-up much easier.</li>
                </ul>
            </section>

            <section class="rs-panel">
                <div class="rs-side-head"><span class="rs-kicker rs-kicker--dark">Today</span><h2>Recent Teacher Registrations</h2></div>
                <div class="rs-search"><i class="fa fa-search"></i><input type="search" id="tr-search" placeholder="Search teacher name, ID, username or category"></div>
                <div class="rs-recent">
                    <?php if(count($recentTeachers)>0){ foreach($recentTeachers as $teacher){ $name=trim($teacher["firstname"]." ".$teacher["othernames"]." ".$teacher["surname"]); $search=strtolower($name." ".$teacher["userid"]." ".$teacher["username"]." ".$teacher["staffstatus"]); ?>
                    <article class="rs-recent-item tr-recent-item" data-search="<?php echo tesc($search); ?>">
                        <div class="rs-recent-top tr-recent-top">
                            <img src="<?php echo tesc(timg($teacher["filename"])); ?>" alt="<?php echo tesc($name); ?>">
                            <div class="rs-recent-meta">
                                <h3><?php echo tesc($name!=="" ? $name : $teacher["userid"]); ?></h3>
                                <p><?php echo tesc($teacher["userid"]); ?><?php if(trim((string)$teacher["username"])!==""){ ?> · <?php echo tesc($teacher["username"]); ?><?php } ?></p>
                            </div>
                        </div>
                        <div class="rs-tags"><span><?php echo tesc($teacher["gender"]); ?></span><span><?php echo tesc($teacher["staffstatus"]!=="" ? $teacher["staffstatus"] : "Category pending"); ?></span><span><?php echo tesc($teacher["status"]); ?></span></div>
                        <div class="rs-recent-foot">
                            <small><?php echo tesc(tdate($teacher["registereddatetime"],"d M Y, g:i a")); ?></small>
                            <div><a href="user-profile.php?view_user=<?php echo urlencode($teacher["userid"]); ?>">View</a><a href="register_edit.php?edit_user=<?php echo urlencode($teacher["userid"]); ?>">Edit</a></div>
                        </div>
                    </article>
                    <?php } } else { ?>
                    <div class="rs-empty"><h3>No teacher registrations yet today</h3><p>Newly saved teachers will appear here for quick review and verification.</p></div>
                    <?php } ?>
                </div>
            </section>
        </aside>
    </div>
</main>
<?php include("footer.php"); ?>
<script>
(function () {
    var birthday=document.getElementById("birthday"), age=document.getElementById("age"), search=document.getElementById("tr-search"), items=document.querySelectorAll(".tr-recent-item"), imageInput=document.getElementById("teacherimage"), imagePreview=document.getElementById("teacherimage_preview");
    function syncAge(){
        if(!birthday || !age || !birthday.value){ if(age){ age.value=""; } return; }
        var dob=new Date(birthday.value+"T00:00:00");
        if(isNaN(dob.getTime())){ age.value=""; return; }
        var today=new Date(), years=today.getFullYear()-dob.getFullYear(), monthDiff=today.getMonth()-dob.getMonth();
        if(monthDiff<0 || (monthDiff===0 && today.getDate()<dob.getDate())){ years--; }
        age.value=years>=0 ? years : "";
    }
    function filterItems(){
        if(!search){ return; }
        var term=search.value.toLowerCase().trim();
        items.forEach(function(item){ item.style.display=(item.getAttribute("data-search") || "").indexOf(term)!==-1 ? "" : "none"; });
    }
    if(birthday){ birthday.addEventListener("change",syncAge); syncAge(); }
    if(search){ search.addEventListener("input",filterItems); }
    if(imageInput && imagePreview){
        imageInput.addEventListener("change",function(){
            var file=imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
            if(!file){ imagePreview.src="uploads/comm.gif"; return; }
            var reader=new FileReader();
            reader.onload=function(event){ imagePreview.src=event.target && event.target.result ? event.target.result : "uploads/comm.gif"; };
            reader.readAsDataURL(file);
        });
    }
}());
</script>
</body>
</html>

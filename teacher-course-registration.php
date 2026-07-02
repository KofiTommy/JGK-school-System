<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("course-registration-utils.php");
course_registration_ensure_tables($con);

if(!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Teacher')){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

function ctr_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function ctr_flash($type, $message){
    $class = "cra-alert cra-alert--info";
    if($type === "success"){
        $class = "cra-alert cra-alert--success";
    }elseif($type === "error"){
        $class = "cra-alert cra-alert--error";
    }elseif($type === "warning"){
        $class = "cra-alert cra-alert--warning";
    }
    return "<div class=\"$class\">".ctr_safe($message)."</div>";
}

$teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$selectedAcademicYear = course_registration_normalize_year(isset($_GET['academicyear']) ? $_GET['academicyear'] : "");
$selectedTermName = isset($_GET['termname']) ? trim((string)$_GET['termname']) : "";

$teacherRows = course_registration_fetch_teacher_rows($con, $teacherId, $selectedAcademicYear, $selectedTermName);
$flashMessage = "";
if(isset($_SESSION['Message']) && trim((string)$_SESSION['Message']) !== ""){
    $flashMessage = (string)$_SESSION['Message'];
    $_SESSION['Message'] = "";
}

$totalRegistered = 0;
$openWindowCourses = 0;
$distinctWindows = array();
foreach($teacherRows as $teacherRow){
    $totalRegistered += (int)$teacherRow['registered_students'];
    if(course_registration_window_is_open($teacherRow)){
        $openWindowCourses++;
    }
    $distinctWindows[$teacherRow['windowid']] = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/course-registration.css">
</head>
<body class="cr-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="cr-shell">
    <?php if($flashMessage !== ""){ ?><div class="cr-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="cr-hero">
        <div>
            <span class="cr-kicker">Teacher Course View</span>
            <h1>See exactly how many students registered for each course you teach.</h1>
            <p>This page follows the student course registration windows, so your counts reflect only the students who have actually submitted for that semester.</p>
        </div>
        <div class="cr-hero__meta">
            <article><span>Course Windows</span><strong><?php echo count($distinctWindows); ?></strong></article>
            <article><span>Visible Courses</span><strong><?php echo count($teacherRows); ?></strong></article>
            <article><span>Total Registered Picks</span><strong><?php echo (int)$totalRegistered; ?></strong></article>
            <article><span>Open Window Courses</span><strong><?php echo (int)$openWindowCourses; ?></strong></article>
        </div>
    </section>

    <section class="cr-panel cr-panel--form">
        <div class="cr-panel__header">
            <div><span class="cr-panel__eyebrow">Filters</span><h2>Academic Year and Semester</h2></div>
            <div class="cr-section__actions">
                <button type="button" onclick="window.print();" class="cr-secondary-button"><i class="fa fa-print"></i> Print View</button>
            </div>
        </div>
        <form method="get" action="teacher-course-registration.php" class="cr-filter-grid">
            <label>
                <span>Academic Year</span>
                <select name="academicyear">
                    <option value="">All Academic Years</option>
                    <?php foreach(course_registration_year_options() as $yearOption){ ?>
                    <option value="<?php echo ctr_safe($yearOption); ?>" <?php echo $selectedAcademicYear === $yearOption ? 'selected' : ''; ?>><?php echo ctr_safe($yearOption); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label>
                <span>Semester</span>
                <select name="termname">
                    <option value="">All Semesters</option>
                    <option value="1" <?php echo $selectedTermName === '1' ? 'selected' : ''; ?>>Semester 1</option>
                    <option value="2" <?php echo $selectedTermName === '2' ? 'selected' : ''; ?>>Semester 2</option>
                </select>
            </label>
            <div class="cr-filter-grid__actions">
                <button type="submit" class="button-show"><i class="fa fa-search"></i> Apply Filter</button>
                <a href="teacher-course-registration.php" class="cr-reset-link">Reset</a>
            </div>
        </form>
    </section>

    <?php if(count($teacherRows) === 0){ ?>
    <?php echo ctr_flash("warning", "No registered course data matched your current filter yet. Your courses will appear here after the school assigns the classified subjects to your account."); ?>
    <?php }else{ ?>
    <section class="cr-section">
        <div class="cr-section__header">
            <div><span class="cr-panel__eyebrow">Registration Counts</span><h2>My semester courses</h2></div>
        </div>
        <div class="cr-course-grid">
            <?php foreach($teacherRows as $teacherRow){ ?>
            <article class="cr-course-card">
                <div class="cr-course-card__head">
                    <div>
                        <h3><?php echo ctr_safe($teacherRow['subjectlabel']); ?></h3>
                        <p><?php echo ctr_safe($teacherRow['class_name']); ?> | <?php echo ctr_safe($teacherRow['session_label']); ?></p>
                    </div>
                    <span class="cr-pill <?php echo course_registration_window_is_open($teacherRow) ? 'cr-pill--success' : 'cr-pill--warning'; ?>"><?php echo ctr_safe(course_registration_window_status_label($teacherRow)); ?></span>
                </div>
                <div class="cr-course-card__stats">
                    <span><?php echo (int)$teacherRow['registered_students']; ?> registered</span>
                    <strong>of <?php echo (int)$teacherRow['eligible_students']; ?></strong>
                </div>
                <div class="cr-course-card__meta">
                    <span><?php echo ctr_safe(course_registration_selection_type_label($teacherRow['selectiontype'])); ?></span>
                    <strong><?php echo ctr_safe($teacherRow['teacherlabel']); ?></strong>
                </div>
                <?php if(count($teacherRow['student_list']) > 0){ ?>
                <ul class="cr-inline-list">
                    <?php foreach($teacherRow['student_list'] as $studentLabel){ ?>
                    <li><?php echo ctr_safe($studentLabel); ?></li>
                    <?php } ?>
                </ul>
                <?php }else{ ?>
                <p class="cr-muted">No student has submitted this course yet.</p>
                <?php } ?>
            </article>
            <?php } ?>
        </div>
    </section>
    <?php } ?>
</main>
</body>
</html>

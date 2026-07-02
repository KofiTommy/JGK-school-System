<?php
session_start();
include("check-login.php");
include("dbstring.php");
include_once("lesson-timetable-utils.php");
ensure_lesson_timetable_table($con);

if(!lesson_timetable_can_manage($con)){
    header("location:".lesson_timetable_landing_page());
    exit();
}

function lt_flash($type, $message){
    $class = "lesson-alert lesson-alert--info";
    if($type === "success"){ $class = "lesson-alert lesson-alert--success"; }
    elseif($type === "error"){ $class = "lesson-alert lesson-alert--error"; }
    elseif($type === "warning"){ $class = "lesson-alert lesson-alert--warning"; }
    return "<div class=\"".$class."\">".lesson_timetable_escape($message)."</div>";
}

function lt_redirect_with_filters($batchId, $academicYear, $termName, $classId){
    $query = http_build_query(array(
        'batchid' => $batchId,
        'academicyear' => $academicYear,
        'termname' => $termName,
        'classid' => $classId
    ));
    header("location:lesson-timetable.php".($query !== '' ? "?".$query : ""));
    exit();
}

$flashMessage = isset($_SESSION['LESSON_TIMETABLE_MESSAGE']) ? (string)$_SESSION['LESSON_TIMETABLE_MESSAGE'] : "";
unset($_SESSION['LESSON_TIMETABLE_MESSAGE']);

$filterBatchId = isset($_GET['batchid']) ? trim((string)$_GET['batchid']) : lesson_timetable_default_batch_id($con);
$filterAcademicYear = isset($_GET['academicyear']) ? lesson_timetable_normalize_year($_GET['academicyear']) : lesson_timetable_default_academic_year($con);
$filterTermName = isset($_GET['termname']) && trim((string)$_GET['termname']) !== '' ? (string)((int)$_GET['termname']) : "1";
$filterClassId = isset($_GET['classid']) ? trim((string)$_GET['classid']) : "";

$form = array(
    'lessonid' => '',
    'batchid' => $filterBatchId,
    'academicyear' => $filterAcademicYear,
    'termname' => $filterTermName,
    'classid' => $filterClassId,
    'weekday' => 'Monday',
    'subjectid' => '',
    'teacherid' => '',
    'starttime' => '',
    'endtime' => '',
    'location' => '',
    'note' => ''
);

if(isset($_GET['delete_lesson']) && trim((string)$_GET['delete_lesson']) !== ''){
    $deleteId = mysqli_real_escape_string($con, trim((string)$_GET['delete_lesson']));
    $deleted = mysqli_query($con, "DELETE FROM tbllessontimetable WHERE lessonid='$deleteId'");
    $_SESSION['LESSON_TIMETABLE_MESSAGE'] = $deleted
        ? lt_flash('success', 'Lesson timetable entry deleted.')
        : lt_flash('error', 'The lesson entry could not be deleted.');
    lt_redirect_with_filters($filterBatchId, $filterAcademicYear, $filterTermName, $filterClassId);
}

if(isset($_POST['save_lesson_timetable'])){
    $form['lessonid'] = trim((string)(isset($_POST['lessonid']) ? $_POST['lessonid'] : ''));
    $form['batchid'] = trim((string)(isset($_POST['batchid']) ? $_POST['batchid'] : ''));
    $form['academicyear'] = lesson_timetable_normalize_year(isset($_POST['academicyear']) ? $_POST['academicyear'] : '');
    $form['termname'] = trim((string)(isset($_POST['termname']) ? $_POST['termname'] : ''));
    $form['classid'] = trim((string)(isset($_POST['classid']) ? $_POST['classid'] : ''));
    $form['weekday'] = lesson_timetable_normalize_weekday(isset($_POST['weekday']) ? $_POST['weekday'] : '');
    $form['subjectid'] = trim((string)(isset($_POST['subjectid']) ? $_POST['subjectid'] : ''));
    $form['teacherid'] = trim((string)(isset($_POST['teacherid']) ? $_POST['teacherid'] : ''));
    $form['starttime'] = trim((string)(isset($_POST['starttime']) ? $_POST['starttime'] : ''));
    $form['endtime'] = trim((string)(isset($_POST['endtime']) ? $_POST['endtime'] : ''));
    $form['location'] = trim((string)(isset($_POST['location']) ? $_POST['location'] : ''));
    $form['note'] = trim((string)(isset($_POST['note']) ? $_POST['note'] : ''));

    $filterBatchId = trim((string)(isset($_POST['filter_batchid']) ? $_POST['filter_batchid'] : $form['batchid']));
    $filterAcademicYear = lesson_timetable_normalize_year(isset($_POST['filter_academicyear']) ? $_POST['filter_academicyear'] : $form['academicyear']);
    $filterTermName = trim((string)(isset($_POST['filter_termname']) ? $_POST['filter_termname'] : $form['termname']));
    $filterClassId = trim((string)(isset($_POST['filter_classid']) ? $_POST['filter_classid'] : $form['classid']));

    if($form['batchid'] === '' || $form['academicyear'] === '' || $form['termname'] === '' || $form['classid'] === '' || $form['weekday'] === '' ||
       $form['subjectid'] === '' || $form['teacherid'] === '' || $form['starttime'] === '' || $form['endtime'] === ''){
        $flashMessage = lt_flash('warning', 'Please complete the batch, academic year, semester, class, day, teacher, subject, and time fields.');
    } elseif(!lesson_timetable_valid_time($form['starttime']) || !lesson_timetable_valid_time($form['endtime'])){
        $flashMessage = lt_flash('warning', 'Please enter a valid lesson start and end time.');
    } elseif($form['endtime'] <= $form['starttime']){
        $flashMessage = lt_flash('warning', 'Lesson end time must be later than the start time.');
    } elseif(!lesson_timetable_teacher_subject_is_valid($con, $form['teacherid'], $form['subjectid'], $form['classid'], $form['batchid'], $form['academicyear'], $form['termname'])){
        $flashMessage = lt_flash('error', 'That teacher is not currently assigned to teach the selected subject for this class, batch, academic year, and semester. Please update Subject Assignment first.');
    } elseif(lesson_timetable_has_class_overlap($con, $form['classid'], $form['batchid'], $form['academicyear'], $form['termname'], $form['weekday'], $form['starttime'], $form['endtime'], $form['lessonid'])){
        $flashMessage = lt_flash('error', 'That class already has another lesson scheduled during the selected time.');
    } elseif(lesson_timetable_has_teacher_overlap($con, $form['teacherid'], $form['academicyear'], $form['weekday'], $form['starttime'], $form['endtime'], $form['lessonid'])){
        $flashMessage = lt_flash('error', 'That teacher already has another lesson at the selected time.');
    } else {
        $lessonId = $form['lessonid'] !== '' ? $form['lessonid'] : lesson_timetable_make_id('LESSON');
        $lessonIdEsc = mysqli_real_escape_string($con, $lessonId);
        $batchIdEsc = mysqli_real_escape_string($con, $form['batchid']);
        $academicYearEsc = mysqli_real_escape_string($con, $form['academicyear']);
        $classIdEsc = mysqli_real_escape_string($con, $form['classid']);
        $weekdayEsc = mysqli_real_escape_string($con, $form['weekday']);
        $subjectIdEsc = mysqli_real_escape_string($con, $form['subjectid']);
        $teacherIdEsc = mysqli_real_escape_string($con, $form['teacherid']);
        $startTimeEsc = mysqli_real_escape_string($con, $form['starttime']);
        $endTimeEsc = mysqli_real_escape_string($con, $form['endtime']);
        $locationEsc = mysqli_real_escape_string($con, $form['location']);
        $noteEsc = mysqli_real_escape_string($con, $form['note']);
        $recordedByEsc = mysqli_real_escape_string($con, (string)$_SESSION['USERID']);
        $termValue = (int)$form['termname'];

        if($form['lessonid'] !== ''){
            $saved = mysqli_query($con, "UPDATE tbllessontimetable
                SET classid='$classIdEsc',
                    batchid='$batchIdEsc',
                    academicyear='$academicYearEsc',
                    termname='$termValue',
                    weekday='$weekdayEsc',
                    subjectid='$subjectIdEsc',
                    teacherid='$teacherIdEsc',
                    starttime='$startTimeEsc',
                    endtime='$endTimeEsc',
                    location='$locationEsc',
                    note='$noteEsc',
                    updatedat=NOW(),
                    recordedby='$recordedByEsc'
                WHERE lessonid='$lessonIdEsc'");
            $_SESSION['LESSON_TIMETABLE_MESSAGE'] = $saved
                ? lt_flash('success', 'Lesson timetable updated successfully.')
                : lt_flash('error', 'The lesson timetable entry could not be updated.');
        } else {
            $saved = mysqli_query($con, "INSERT INTO tbllessontimetable(
                    lessonid,classid,batchid,academicyear,termname,weekday,subjectid,teacherid,starttime,endtime,location,note,status,datetimeentry,updatedat,recordedby
                ) VALUES(
                    '$lessonIdEsc','$classIdEsc','$batchIdEsc','$academicYearEsc','$termValue','$weekdayEsc','$subjectIdEsc','$teacherIdEsc','$startTimeEsc','$endTimeEsc','$locationEsc','$noteEsc','active',NOW(),NOW(),'$recordedByEsc'
                )");
            $_SESSION['LESSON_TIMETABLE_MESSAGE'] = $saved
                ? lt_flash('success', 'Lesson timetable saved successfully.')
                : lt_flash('error', 'The lesson timetable entry could not be saved.');
        }
        lt_redirect_with_filters(
            $filterBatchId !== '' ? $filterBatchId : $form['batchid'],
            $filterAcademicYear !== '' ? $filterAcademicYear : $form['academicyear'],
            $filterTermName !== '' ? $filterTermName : $form['termname'],
            $filterClassId !== '' ? $filterClassId : $form['classid']
        );
    }
}

if(isset($_GET['edit_lesson']) && trim((string)$_GET['edit_lesson']) !== ''){
    $editIdEsc = mysqli_real_escape_string($con, trim((string)$_GET['edit_lesson']));
    $editResult = mysqli_query($con, "SELECT * FROM tbllessontimetable WHERE lessonid='$editIdEsc' LIMIT 1");
    if($editResult && ($editRow = mysqli_fetch_array($editResult, MYSQLI_ASSOC))){
        $form['lessonid'] = (string)$editRow['lessonid'];
        $form['batchid'] = (string)$editRow['batchid'];
        $form['academicyear'] = lesson_timetable_normalize_year((string)$editRow['academicyear']);
        $form['termname'] = (string)$editRow['termname'];
        $form['classid'] = (string)$editRow['classid'];
        $form['weekday'] = (string)$editRow['weekday'];
        $form['subjectid'] = (string)$editRow['subjectid'];
        $form['teacherid'] = (string)$editRow['teacherid'];
        $form['starttime'] = substr((string)$editRow['starttime'], 0, 5);
        $form['endtime'] = substr((string)$editRow['endtime'], 0, 5);
        $form['location'] = (string)$editRow['location'];
        $form['note'] = (string)$editRow['note'];
        $filterBatchId = $form['batchid'];
        $filterAcademicYear = $form['academicyear'];
        $filterTermName = $form['termname'];
        $filterClassId = $form['classid'];
    }
}

$academicYearOptions = lesson_timetable_year_options($con);
$batchRows = array();
$batchResult = mysqli_query($con, "SELECT batchid,batch,status FROM tblbatch ORDER BY status='active' DESC, datetimeentry DESC");
if($batchResult){
    while($row = mysqli_fetch_array($batchResult, MYSQLI_ASSOC)){
        $batchRows[] = $row;
    }
}

$classRows = array();
$classResult = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
if($classResult){
    while($row = mysqli_fetch_array($classResult, MYSQLI_ASSOC)){
        $classRows[] = $row;
    }
}

$assignmentOptions = lesson_timetable_fetch_assignment_options($con, $form['batchid'], $form['academicyear'], $form['termname'], $form['classid']);
$teacherOptions = array();
$subjectOptions = array();
foreach($assignmentOptions as $option){
    $teacherOptions[$option['teacherid']] = $option['teacher_name'].' ('.$option['teacherid'].')';
    $subjectOptions[$option['subjectid']] = $option['subject'];
}
asort($teacherOptions);
asort($subjectOptions);

$currentRows = lesson_timetable_fetch_rows($con, array(
    'batchid' => $filterBatchId,
    'academicyear' => $filterAcademicYear,
    'termname' => $filterTermName,
    'classid' => $filterClassId
));
$groupedRows = lesson_timetable_group_rows_by_day($currentRows);
$summaryTeachers = array();
$summaryClasses = array();
$summarySubjects = array();
foreach($currentRows as $row){
    $summaryTeachers[$row['teacherid']] = true;
    $summaryClasses[$row['classid']] = true;
    $summarySubjects[$row['subjectid']] = true;
}

$batchNames = array();
foreach($batchRows as $batchRow){
    $batchNames[$batchRow['batchid']] = $batchRow['batch'];
}
$classNames = array();
foreach($classRows as $classRow){
    $classNames[$classRow['class_entryid']] = $classRow['class_name'];
}
$weekdays = lesson_timetable_weekdays();
$timeSlots = lesson_timetable_extract_time_slots($currentRows);
$matrixRows = lesson_timetable_group_rows_by_day_and_slot($currentRows);
$dayCounts = array();
foreach($weekdays as $day){
    $dayCounts[$day] = isset($groupedRows[$day]) ? count($groupedRows[$day]) : 0;
}
$activeBatchLabel = ($filterBatchId !== '' && isset($batchNames[$filterBatchId])) ? $batchNames[$filterBatchId] : 'All Batches';
$activeClassLabel = ($filterClassId !== '' && isset($classNames[$filterClassId])) ? $classNames[$filterClassId] : 'All Classes';
$activeYearLabel = $filterAcademicYear !== '' ? $filterAcademicYear : 'All Years';
$activeTermLabel = $filterTermName !== '' ? 'Semester '.$filterTermName : 'All Semesters';
$timeWindowLabel = 'No lesson periods yet';
if(count($timeSlots) > 0){
    $firstSlot = $timeSlots[0];
    $lastSlot = $timeSlots[count($timeSlots) - 1];
    $timeWindowLabel = lesson_timetable_format_time($firstSlot['starttime']).' - '.lesson_timetable_format_time($lastSlot['endtime']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/lesson-timetable.css">
</head>
<body class="lesson-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="lesson-shell">
    <section class="lesson-hero lesson-hero--planner">
        <div class="lesson-hero__copy">
            <span class="lesson-eyebrow"><i class="fa fa-calendar"></i> Lesson Timetable Studio</span>
            <h1>Build a timetable that looks clear before the bell rings.</h1>
            <p>Plan teacher lessons in a polished weekly view, keep every class and teacher clash-free, and make the schedule easy to scan on both desktop and mobile.</p>
            <div class="lesson-hero__chips">
                <span class="lesson-hero-chip"><i class="fa fa-clone"></i> <?php echo lesson_timetable_escape($activeBatchLabel); ?></span>
                <span class="lesson-hero-chip"><i class="fa fa-calendar-check-o"></i> <?php echo lesson_timetable_escape($activeYearLabel); ?></span>
                <span class="lesson-hero-chip"><i class="fa fa-graduation-cap"></i> <?php echo lesson_timetable_escape($activeClassLabel); ?></span>
                <span class="lesson-hero-chip"><i class="fa fa-book"></i> <?php echo lesson_timetable_escape($activeTermLabel); ?></span>
            </div>
        </div>
        <div class="lesson-stat-grid">
            <article class="lesson-stat-card"><span>Visible Lessons</span><strong><?php echo count($currentRows); ?></strong></article>
            <article class="lesson-stat-card"><span>Teachers In View</span><strong><?php echo count($summaryTeachers); ?></strong></article>
            <article class="lesson-stat-card"><span>Daily Window</span><strong><?php echo lesson_timetable_escape($timeWindowLabel); ?></strong></article>
        </div>
    </section>

    <?php if($flashMessage !== ''){ echo $flashMessage; } ?>

    <section class="lesson-toolbar lesson-print-hide">
        <div class="lesson-toolbar__group">
            <span class="lesson-pill"><?php echo count($summaryClasses); ?> Class<?php echo count($summaryClasses) === 1 ? '' : 'es'; ?></span>
            <span class="lesson-pill"><?php echo count($summarySubjects); ?> Subject<?php echo count($summarySubjects) === 1 ? '' : 's'; ?></span>
            <span class="lesson-pill"><?php echo count($timeSlots); ?> Time Slot<?php echo count($timeSlots) === 1 ? '' : 's'; ?></span>
        </div>
        <a class="lesson-btn lesson-btn--secondary" href="lesson-timetable-report.php?batchid=<?php echo urlencode($filterBatchId); ?>&academicyear=<?php echo urlencode($filterAcademicYear); ?>&termname=<?php echo urlencode($filterTermName); ?>&classid=<?php echo urlencode($filterClassId); ?>"><i class="fa fa-eye"></i> Open Print Report</a>
    </section>

    <div class="lesson-grid lesson-grid--planner">
        <section class="lesson-card lesson-card--sticky">
            <div class="lesson-card__header">
                <div>
                    <h2><?php echo $form['lessonid'] !== '' ? 'Update Lesson Slot' : 'Create Lesson Slot'; ?></h2>
                    <p>Match the class, teacher, subject, and time once. The system protects the timetable from clashes before it saves.</p>
                </div>
                <?php if($form['lessonid'] !== ''){ ?>
                <a class="lesson-btn lesson-btn--secondary lesson-print-hide" href="lesson-timetable.php?batchid=<?php echo urlencode($filterBatchId); ?>&academicyear=<?php echo urlencode($filterAcademicYear); ?>&termname=<?php echo urlencode($filterTermName); ?>&classid=<?php echo urlencode($filterClassId); ?>"><i class="fa fa-times"></i> Cancel Edit</a>
                <?php } ?>
            </div>
            <div class="lesson-card__body">
                <form method="post" class="lesson-form" action="lesson-timetable.php">
                    <input type="hidden" name="lessonid" value="<?php echo lesson_timetable_escape($form['lessonid']); ?>">
                    <input type="hidden" name="filter_batchid" value="<?php echo lesson_timetable_escape($filterBatchId); ?>">
                    <input type="hidden" name="filter_academicyear" value="<?php echo lesson_timetable_escape($filterAcademicYear); ?>">
                    <input type="hidden" name="filter_termname" value="<?php echo lesson_timetable_escape($filterTermName); ?>">
                    <input type="hidden" name="filter_classid" value="<?php echo lesson_timetable_escape($filterClassId); ?>">
                    <div class="lesson-form-grid">
                        <div class="lesson-form-field">
                            <label for="batchid">Batch</label>
                            <select id="batchid" name="batchid" required>
                                <option value="">Select Batch</option>
                                <?php foreach($batchRows as $batch){ ?>
                                <option value="<?php echo lesson_timetable_escape($batch['batchid']); ?>"<?php echo $form['batchid'] === $batch['batchid'] ? ' selected' : ''; ?>>
                                    <?php echo lesson_timetable_escape($batch['batch']); ?><?php echo trim((string)$batch['status']) === 'active' ? ' (Active)' : ''; ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="academicyear">Academic Year</label>
                            <select id="academicyear" name="academicyear" required>
                                <option value="">Select Year</option>
                                <?php foreach($academicYearOptions as $yearOption){ ?>
                                <option value="<?php echo lesson_timetable_escape($yearOption); ?>"<?php echo $form['academicyear'] === $yearOption ? ' selected' : ''; ?>>
                                    <?php echo lesson_timetable_escape($yearOption); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="termname">Semester</label>
                            <select id="termname" name="termname" required>
                                <option value="">Select Semester</option>
                                <option value="1"<?php echo $form['termname'] === '1' ? ' selected' : ''; ?>>1</option>
                                <option value="2"<?php echo $form['termname'] === '2' ? ' selected' : ''; ?>>2</option>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="classid">Class</label>
                            <select id="classid" name="classid" required>
                                <option value="">Select Class</option>
                                <?php foreach($classRows as $classRow){ ?>
                                <option value="<?php echo lesson_timetable_escape($classRow['class_entryid']); ?>"<?php echo $form['classid'] === $classRow['class_entryid'] ? ' selected' : ''; ?>>
                                    <?php echo lesson_timetable_escape($classRow['class_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="weekday">Weekday</label>
                            <select id="weekday" name="weekday" required>
                                <option value="">Select Day</option>
                                <?php foreach(lesson_timetable_weekdays() as $day){ ?>
                                <option value="<?php echo lesson_timetable_escape($day); ?>"<?php echo $form['weekday'] === $day ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($day); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="teacherid">Teacher</label>
                            <select id="teacherid" name="teacherid" required>
                                <option value="">Select Teacher</option>
                                <?php foreach($teacherOptions as $teacherId => $teacherLabel){ ?>
                                <option value="<?php echo lesson_timetable_escape($teacherId); ?>"<?php echo $form['teacherid'] === $teacherId ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($teacherLabel); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="subjectid">Subject</label>
                            <select id="subjectid" name="subjectid" required>
                                <option value="">Select Subject</option>
                                <?php foreach($subjectOptions as $subjectId => $subjectName){ ?>
                                <option value="<?php echo lesson_timetable_escape($subjectId); ?>"<?php echo $form['subjectid'] === $subjectId ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($subjectName); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="starttime">Start Time</label>
                            <input type="time" id="starttime" name="starttime" value="<?php echo lesson_timetable_escape($form['starttime']); ?>" required>
                        </div>
                        <div class="lesson-form-field">
                            <label for="endtime">End Time</label>
                            <input type="time" id="endtime" name="endtime" value="<?php echo lesson_timetable_escape($form['endtime']); ?>" required>
                        </div>
                        <div class="lesson-form-field lesson-form-field--full">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" value="<?php echo lesson_timetable_escape($form['location']); ?>" placeholder="Optional room, lab, or block">
                        </div>
                        <div class="lesson-form-field lesson-form-field--full">
                            <label for="note">Note</label>
                            <textarea id="note" name="note" placeholder="Optional note for the lesson slot"><?php echo lesson_timetable_escape($form['note']); ?></textarea>
                            <small>Every save checks both class-time clashes and teacher-time clashes before the lesson is accepted.</small>
                        </div>
                    </div>
                    <div class="lesson-form-actions">
                        <button class="lesson-btn" type="submit" name="save_lesson_timetable"><i class="fa fa-save"></i> <?php echo $form['lessonid'] !== '' ? 'Update Lesson' : 'Save Lesson'; ?></button>
                    </div>
                </form>
            </div>
        </section>

        <section class="lesson-card">
            <div class="lesson-card__header">
                <div>
                    <h2>Planning Filters</h2>
                    <p>Focus the board by batch, academic year, semester, and class, then use the live week view below to make fast adjustments.</p>
                </div>
                <span class="lesson-pill"><?php echo lesson_timetable_escape($activeYearLabel.' | '.$activeTermLabel); ?></span>
            </div>
            <div class="lesson-card__body">
                <form method="get" action="lesson-timetable.php" class="lesson-form lesson-print-hide">
                    <div class="lesson-filter-grid">
                        <div class="lesson-form-field">
                            <label for="filter-batchid">Batch</label>
                            <select id="filter-batchid" name="batchid">
                                <option value="">All Batches</option>
                                <?php foreach($batchRows as $batch){ ?>
                                <option value="<?php echo lesson_timetable_escape($batch['batchid']); ?>"<?php echo $filterBatchId === $batch['batchid'] ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($batch['batch']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="filter-academicyear">Academic Year</label>
                            <select id="filter-academicyear" name="academicyear">
                                <?php foreach($academicYearOptions as $yearOption){ ?>
                                <option value="<?php echo lesson_timetable_escape($yearOption); ?>"<?php echo $filterAcademicYear === $yearOption ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($yearOption); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="filter-termname">Semester</label>
                            <select id="filter-termname" name="termname">
                                <option value="">All</option>
                                <option value="1"<?php echo $filterTermName === '1' ? ' selected' : ''; ?>>1</option>
                                <option value="2"<?php echo $filterTermName === '2' ? ' selected' : ''; ?>>2</option>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label for="filter-classid">Class</label>
                            <select id="filter-classid" name="classid">
                                <option value="">All Classes</option>
                                <?php foreach($classRows as $classRow){ ?>
                                <option value="<?php echo lesson_timetable_escape($classRow['class_entryid']); ?>"<?php echo $filterClassId === $classRow['class_entryid'] ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($classRow['class_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="lesson-form-field">
                            <label>&nbsp;</label>
                            <button class="lesson-btn lesson-btn--secondary" type="submit"><i class="fa fa-filter"></i> Apply</button>
                        </div>
                    </div>
                </form>

                <div class="lesson-helper-list lesson-helper-list--compact">
                    <?php if(count($assignmentOptions) > 0){ ?>
                        <?php foreach($assignmentOptions as $option){ $tokens = lesson_timetable_visual_tokens($option['subjectid'].'-'.$option['teacherid']); ?>
                        <div class="lesson-helper-item lesson-helper-item--tinted" style="--lesson-accent: <?php echo lesson_timetable_escape($tokens['accent']); ?>; --lesson-surface: <?php echo lesson_timetable_escape($tokens['surface']); ?>;">
                            <strong><?php echo lesson_timetable_escape($option['subject']); ?></strong>
                            <span><?php echo lesson_timetable_escape($option['teacher_name']); ?> handles <?php echo lesson_timetable_escape($option['class_name']); ?> in <?php echo lesson_timetable_escape($option['academicyear']); ?>, semester <?php echo lesson_timetable_escape($option['termname']); ?>, <?php echo lesson_timetable_escape($option['batch']); ?>.</span>
                        </div>
                        <?php } ?>
                    <?php } else { ?>
                    <div class="lesson-empty">No active subject assignments matched the selected class, batch, academic year, and semester yet. Assign the teacher to the subject first, then the lesson timetable can be created safely.</div>
                    <?php } ?>
                </div>
            </div>
        </section>
    </div>

    <section class="lesson-card">
        <div class="lesson-card__header">
            <div>
                <h2>Weekly Planner</h2>
                <p><?php echo $filterClassId !== '' ? 'Showing the selected class timetable in a full-week planner.' : 'Showing all visible lessons in the current weekly planner.'; ?></p>
            </div>
            <span class="lesson-pill"><?php echo lesson_timetable_escape($activeYearLabel.' | '.$activeTermLabel.' | '.$timeWindowLabel); ?></span>
        </div>
        <div class="lesson-card__body">
            <div class="lesson-day-strip">
                <?php foreach($weekdays as $day){ ?>
                <article class="lesson-day-pill<?php echo $day === lesson_timetable_today_name() ? ' lesson-day-pill--today' : ''; ?>">
                    <span><?php echo lesson_timetable_escape(substr($day, 0, 3)); ?></span>
                    <strong><?php echo (int)$dayCounts[$day]; ?></strong>
                    <small><?php echo (int)$dayCounts[$day] === 1 ? 'Lesson' : 'Lessons'; ?></small>
                </article>
                <?php } ?>
            </div>

            <?php if(count($currentRows) > 0 && count($timeSlots) > 0){ ?>
            <div class="lesson-matrix lesson-matrix--desktop">
                <div class="lesson-matrix__head lesson-matrix__head--time">Period</div>
                <?php foreach($weekdays as $day){ ?>
                <div class="lesson-matrix__head<?php echo $day === lesson_timetable_today_name() ? ' lesson-matrix__head--today' : ''; ?>">
                    <strong><?php echo lesson_timetable_escape($day); ?></strong>
                    <span><?php echo (int)$dayCounts[$day]; ?> lesson<?php echo (int)$dayCounts[$day] === 1 ? '' : 's'; ?></span>
                </div>
                <?php } ?>

                <?php foreach($timeSlots as $slot){ ?>
                    <div class="lesson-matrix__time">
                        <strong><?php echo lesson_timetable_escape($slot['label']); ?></strong>
                        <span><?php echo (int)$slot['duration']; ?> mins</span>
                    </div>
                    <?php foreach($weekdays as $day){ ?>
                    <div class="lesson-matrix__cell<?php echo $day === lesson_timetable_today_name() ? ' lesson-matrix__cell--today' : ''; ?>">
                        <?php $slotRows = isset($matrixRows[$day][$slot['key']]) ? $matrixRows[$day][$slot['key']] : array(); ?>
                        <?php if(count($slotRows) > 0){ ?>
                            <?php foreach($slotRows as $row){ $tokens = lesson_timetable_visual_tokens($row['subjectid'].'-'.$row['classid'].'-'.$row['teacherid']); ?>
                            <article class="lesson-entry-card" style="--lesson-accent: <?php echo lesson_timetable_escape($tokens['accent']); ?>; --lesson-soft: <?php echo lesson_timetable_escape($tokens['soft']); ?>; --lesson-surface: <?php echo lesson_timetable_escape($tokens['surface']); ?>; --lesson-ink: <?php echo lesson_timetable_escape($tokens['ink']); ?>;">
                                <div class="lesson-entry-card__timeband"><?php echo lesson_timetable_escape(lesson_timetable_format_time($row['starttime']).' - '.lesson_timetable_format_time($row['endtime'])); ?></div>
                                <div class="lesson-entry-card__topline">
                                    <span class="lesson-entry-card__subject"><?php echo lesson_timetable_escape($row['subject']); ?></span>
                                    <span class="lesson-entry-card__tag"><?php echo lesson_timetable_escape($row['class_name']); ?></span>
                                </div>
                                <div class="lesson-entry-card__title"><?php echo lesson_timetable_escape($row['teacher_name']); ?></div>
                                <div class="lesson-entry-card__meta">
                                    <span><i class="fa fa-calendar"></i> <?php echo lesson_timetable_escape($row['academicyear']); ?></span>
                                    <span><i class="fa fa-calendar-check-o"></i> Semester <?php echo lesson_timetable_escape($row['termname']); ?></span>
                                    <span><i class="fa fa-users"></i> <?php echo lesson_timetable_escape($row['batch']); ?></span>
                                    <?php if(trim((string)$row['location']) !== ''){ ?><span><i class="fa fa-map-marker"></i> <?php echo lesson_timetable_escape($row['location']); ?></span><?php } ?>
                                </div>
                                <?php if(trim((string)$row['note']) !== ''){ ?><p class="lesson-entry-card__note"><?php echo lesson_timetable_escape($row['note']); ?></p><?php } ?>
                                <div class="lesson-entry-card__actions lesson-print-hide">
                                    <a class="lesson-chip-btn" href="lesson-timetable.php?batchid=<?php echo urlencode($row['batchid']); ?>&academicyear=<?php echo urlencode($row['academicyear']); ?>&termname=<?php echo urlencode($row['termname']); ?>&classid=<?php echo urlencode($row['classid']); ?>&edit_lesson=<?php echo urlencode($row['lessonid']); ?>"><i class="fa fa-edit"></i> Edit</a>
                                    <a class="lesson-chip-btn lesson-chip-btn--danger" href="lesson-timetable.php?batchid=<?php echo urlencode($row['batchid']); ?>&academicyear=<?php echo urlencode($row['academicyear']); ?>&termname=<?php echo urlencode($row['termname']); ?>&classid=<?php echo urlencode($row['classid']); ?>&delete_lesson=<?php echo urlencode($row['lessonid']); ?>" onclick="return confirm('Delete this lesson slot?');"><i class="fa fa-trash"></i> Delete</a>
                                </div>
                            </article>
                            <?php } ?>
                        <?php } else { ?>
                        <div class="lesson-cell-empty"><span></span></div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="lesson-agenda-mobile">
                <?php foreach($groupedRows as $day => $rows){ ?>
                <section class="lesson-agenda-day">
                    <div class="lesson-agenda-day__head">
                        <h3><?php echo lesson_timetable_escape($day); ?></h3>
                        <span><?php echo count($rows); ?> lesson<?php echo count($rows) === 1 ? '' : 's'; ?></span>
                    </div>
                    <div class="lesson-agenda-day__body">
                        <?php if(count($rows) > 0){ ?>
                            <?php foreach($rows as $row){ $tokens = lesson_timetable_visual_tokens($row['subjectid'].'-'.$row['classid'].'-'.$row['teacherid']); ?>
                            <article class="lesson-entry-card lesson-entry-card--mobile" style="--lesson-accent: <?php echo lesson_timetable_escape($tokens['accent']); ?>; --lesson-soft: <?php echo lesson_timetable_escape($tokens['soft']); ?>; --lesson-surface: <?php echo lesson_timetable_escape($tokens['surface']); ?>; --lesson-ink: <?php echo lesson_timetable_escape($tokens['ink']); ?>;">
                                <div class="lesson-entry-card__timeband"><?php echo lesson_timetable_escape(lesson_timetable_format_time($row['starttime']).' - '.lesson_timetable_format_time($row['endtime'])); ?></div>
                                <div class="lesson-entry-card__topline">
                                    <span class="lesson-entry-card__subject"><?php echo lesson_timetable_escape($row['subject']); ?></span>
                                    <span class="lesson-entry-card__tag"><?php echo lesson_timetable_escape($row['class_name']); ?></span>
                                </div>
                                <div class="lesson-entry-card__title"><?php echo lesson_timetable_escape($row['teacher_name']); ?></div>
                                <div class="lesson-entry-card__meta">
                                    <span><i class="fa fa-calendar"></i> <?php echo lesson_timetable_escape($row['academicyear']); ?></span>
                                    <span><i class="fa fa-calendar-check-o"></i> Semester <?php echo lesson_timetable_escape($row['termname']); ?></span>
                                    <span><i class="fa fa-users"></i> <?php echo lesson_timetable_escape($row['batch']); ?></span>
                                    <?php if(trim((string)$row['location']) !== ''){ ?><span><i class="fa fa-map-marker"></i> <?php echo lesson_timetable_escape($row['location']); ?></span><?php } ?>
                                </div>
                                <?php if(trim((string)$row['note']) !== ''){ ?><p class="lesson-entry-card__note"><?php echo lesson_timetable_escape($row['note']); ?></p><?php } ?>
                                <div class="lesson-entry-card__actions lesson-print-hide">
                                    <a class="lesson-chip-btn" href="lesson-timetable.php?batchid=<?php echo urlencode($row['batchid']); ?>&academicyear=<?php echo urlencode($row['academicyear']); ?>&termname=<?php echo urlencode($row['termname']); ?>&classid=<?php echo urlencode($row['classid']); ?>&edit_lesson=<?php echo urlencode($row['lessonid']); ?>"><i class="fa fa-edit"></i> Edit</a>
                                    <a class="lesson-chip-btn lesson-chip-btn--danger" href="lesson-timetable.php?batchid=<?php echo urlencode($row['batchid']); ?>&academicyear=<?php echo urlencode($row['academicyear']); ?>&termname=<?php echo urlencode($row['termname']); ?>&classid=<?php echo urlencode($row['classid']); ?>&delete_lesson=<?php echo urlencode($row['lessonid']); ?>" onclick="return confirm('Delete this lesson slot?');"><i class="fa fa-trash"></i> Delete</a>
                                </div>
                            </article>
                            <?php } ?>
                        <?php } else { ?>
                        <div class="lesson-empty">No lesson has been assigned for <?php echo lesson_timetable_escape($day); ?> yet.</div>
                        <?php } ?>
                    </div>
                </section>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="lesson-empty lesson-empty--large">No lesson periods are available in the current filter yet. Create the first slot and the weekly planner will fill in automatically.</div>
            <?php } ?>
        </div>
    </section>
</main>
<script>
(function(){
    function getSelectedLabel(select){
        if(!select || !select.options || select.selectedIndex < 0){
            return '';
        }
        return (select.options[select.selectedIndex].text || '').trim();
    }

    function syncSelect(select){
        if(!select){
            return;
        }
        var wrap = select.parentNode;
        if(!wrap || !wrap.classList.contains('lesson-select-wrap')){
            return;
        }
        var label = wrap.querySelector('.lesson-select-label');
        if(!label){
            return;
        }
        var selectedValue = select.value || '';
        var selectedLabel = getSelectedLabel(select);
        label.textContent = selectedLabel;
        wrap.classList.toggle('is-placeholder', selectedValue === '');
    }

    function enhanceSelect(select){
        if(!select || select.classList.contains('lesson-select-enhanced')){
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'lesson-select-wrap';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        var label = document.createElement('span');
        label.className = 'lesson-select-label';
        wrap.appendChild(label);
        select.classList.add('lesson-select-enhanced');
        select.addEventListener('change', function(){ syncSelect(select); });
        syncSelect(select);
    }

    document.addEventListener('DOMContentLoaded', function(){
        var selects = document.querySelectorAll('.lesson-form select');
        for(var i = 0; i < selects.length; i++){
            enhanceSelect(selects[i]);
        }
    });
})();
</script>
</body>
</html>

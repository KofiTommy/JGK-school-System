<?php
session_start();
include("check-login.php");
include("dbstring.php");
include_once("lesson-timetable-utils.php");
ensure_lesson_timetable_table($con);

if(!lesson_timetable_can_view($con)){
    header("location:".lesson_timetable_landing_page());
    exit();
}

$isTeacherView = lesson_timetable_is_teacher();
$isStudentView = lesson_timetable_is_student();
$teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$studentId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';

$studentContexts = $isStudentView ? lesson_timetable_fetch_student_contexts($con, $studentId) : array();
$studentContextMap = array();
foreach($studentContexts as $contextRow){
    $studentContextMap[(string)$contextRow['context_key']] = $contextRow;
}
$selectedStudentContextKey = $isStudentView ? trim((string)(isset($_GET['context']) ? $_GET['context'] : '')) : '';
if($isStudentView && $selectedStudentContextKey === '' && count($studentContexts) > 0){
    $selectedStudentContextKey = (string)$studentContexts[0]['context_key'];
}
$selectedStudentContext = ($isStudentView && $selectedStudentContextKey !== '' && isset($studentContextMap[$selectedStudentContextKey]))
    ? $studentContextMap[$selectedStudentContextKey]
    : null;

$filterBatchId = $isStudentView
    ? ($selectedStudentContext ? (string)$selectedStudentContext['batchid'] : '')
    : (isset($_GET['batchid']) ? trim((string)$_GET['batchid']) : lesson_timetable_default_batch_id($con));
$filterAcademicYear = $isStudentView
    ? ($selectedStudentContext ? lesson_timetable_normalize_year($selectedStudentContext['academicyear']) : '')
    : (isset($_GET['academicyear']) ? lesson_timetable_normalize_year($_GET['academicyear']) : lesson_timetable_default_academic_year($con));
$filterTermName = $isStudentView
    ? ($selectedStudentContext ? (string)((int)$selectedStudentContext['termname']) : '')
    : (isset($_GET['termname']) && trim((string)$_GET['termname']) !== '' ? (string)((int)$_GET['termname']) : "1");
$filterClassId = $isStudentView
    ? ($selectedStudentContext ? (string)$selectedStudentContext['classid'] : '')
    : (isset($_GET['classid']) ? trim((string)$_GET['classid']) : "");
$filterTeacherId = $isTeacherView ? $teacherId : ($isStudentView ? "" : (isset($_GET['teacherid']) ? trim((string)$_GET['teacherid']) : ""));

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

$teacherRows = array();
$teacherResult = mysqli_query($con, "SELECT userid, firstname, othernames, surname FROM tblsystemuser WHERE systemtype='Teacher' AND status='active' ORDER BY firstname ASC, surname ASC");
if($teacherResult){
    while($row = mysqli_fetch_array($teacherResult, MYSQLI_ASSOC)){
        $teacherRows[] = $row;
    }
}

$rows = lesson_timetable_fetch_rows($con, array(
    'batchid' => $filterBatchId,
    'academicyear' => $filterAcademicYear,
    'termname' => $filterTermName,
    'classid' => $filterClassId,
    'teacherid' => $filterTeacherId
));
$groupedRows = lesson_timetable_group_rows_by_day($rows);
$todayRows = lesson_timetable_group_rows_by_day(lesson_timetable_fetch_rows($con, array(
    'batchid' => $filterBatchId,
    'academicyear' => $filterAcademicYear,
    'termname' => $filterTermName,
    'classid' => $filterClassId,
    'teacherid' => $filterTeacherId,
    'weekday' => lesson_timetable_today_name()
)));

$teacherCount = array();
$classCount = array();
$subjectCount = array();
foreach($rows as $row){
    $teacherCount[$row['teacherid']] = true;
    $classCount[$row['classid']] = true;
    $subjectCount[$row['subjectid']] = true;
}

$batchNames = array();
foreach($batchRows as $batchRow){
    $batchNames[$batchRow['batchid']] = $batchRow['batch'];
}
$classNames = array();
foreach($classRows as $classRow){
    $classNames[$classRow['class_entryid']] = $classRow['class_name'];
}
$teacherNames = array();
foreach($teacherRows as $teacherRow){
    $teacherNames[$teacherRow['userid']] = trim($teacherRow['firstname'].' '.$teacherRow['othernames'].' '.$teacherRow['surname']);
}
$weekdays = lesson_timetable_weekdays();
$timeSlots = lesson_timetable_extract_time_slots($rows);
$matrixRows = lesson_timetable_group_rows_by_day_and_slot($rows);
$dayCounts = array();
foreach($weekdays as $day){
    $dayCounts[$day] = isset($groupedRows[$day]) ? count($groupedRows[$day]) : 0;
}
$activeBatchLabel = ($filterBatchId !== '' && isset($batchNames[$filterBatchId])) ? $batchNames[$filterBatchId] : 'All Batches';
$activeClassLabel = ($filterClassId !== '' && isset($classNames[$filterClassId])) ? $classNames[$filterClassId] : 'All Classes';
$activeTeacherLabel = ($filterTeacherId !== '' && isset($teacherNames[$filterTeacherId])) ? $teacherNames[$filterTeacherId] : ($isTeacherView ? 'My Timetable' : ($isStudentView ? 'My Class Schedule' : 'All Teachers'));
$activeYearLabel = $filterAcademicYear !== '' ? $filterAcademicYear : 'All Years';
$activeTermLabel = $filterTermName !== '' ? 'Semester '.$filterTermName : 'All Semesters';
$timeWindowLabel = 'No lesson periods yet';
if(count($timeSlots) > 0){
    $firstSlot = $timeSlots[0];
    $lastSlot = $timeSlots[count($timeSlots) - 1];
    $timeWindowLabel = lesson_timetable_format_time($firstSlot['starttime']).' - '.lesson_timetable_format_time($lastSlot['endtime']);
}
$todayName = lesson_timetable_today_name();
$todayList = isset($todayRows[$todayName]) ? $todayRows[$todayName] : array();
$currentLessonRow = lesson_timetable_find_current_row($todayList, $todayName);
$nextLessonRow = lesson_timetable_find_next_row($todayList, $todayName);
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
            <span class="lesson-eyebrow"><i class="fa fa-calendar-o"></i> Lesson Timetable Report</span>
            <h1><?php echo $isTeacherView ? 'My weekly teaching planner' : ($isStudentView ? 'My class lesson timetable' : 'Lesson timetable overview'); ?></h1>
                <p><?php echo $isTeacherView ? 'See your teaching week on your phone or computer.' : ($isStudentView ? 'Follow your class timetable, see the lesson you should be in right now, and check what comes next.' : 'Review the school timetable on desktop or mobile.'); ?></p>
            <div class="lesson-hero__chips">
                <span class="lesson-hero-chip"><i class="fa fa-clone"></i> <?php echo lesson_timetable_escape($activeBatchLabel); ?></span>
                <span class="lesson-hero-chip"><i class="fa fa-calendar-check-o"></i> <?php echo lesson_timetable_escape($activeYearLabel); ?></span>
                <span class="lesson-hero-chip"><i class="fa fa-graduation-cap"></i> <?php echo lesson_timetable_escape($activeClassLabel); ?></span>
                <span class="lesson-hero-chip"><i class="fa fa-user"></i> <?php echo lesson_timetable_escape($activeTeacherLabel); ?></span>
            </div>
        </div>
        <div class="lesson-stat-grid">
            <article class="lesson-stat-card"><span><?php echo $isStudentView ? 'This Week' : 'Visible Lessons'; ?></span><strong><?php echo count($rows); ?></strong></article>
            <article class="lesson-stat-card"><span><?php echo $isStudentView ? 'Today' : 'Classes'; ?></span><strong><?php echo $isStudentView ? count($todayList) : count($classCount); ?></strong></article>
            <article class="lesson-stat-card"><span><?php echo $isStudentView ? 'Now' : 'Daily Window'; ?></span><strong><?php echo lesson_timetable_escape($isStudentView ? ($currentLessonRow ? $currentLessonRow['subject'] : 'Free Period') : $timeWindowLabel); ?></strong></article>
        </div>
    </section>

    <section class="lesson-toolbar lesson-print-hide">
        <div class="lesson-toolbar__group">
            <span class="lesson-pill"><?php echo count($subjectCount); ?> Subject<?php echo count($subjectCount) === 1 ? '' : 's'; ?></span>
            <span class="lesson-pill"><?php echo count($timeSlots); ?> Time Slot<?php echo count($timeSlots) === 1 ? '' : 's'; ?></span>
            <span class="lesson-pill"><?php echo lesson_timetable_escape($activeYearLabel.' | '.$activeTermLabel); ?></span>
        </div>
        <button class="lesson-btn lesson-btn--secondary" type="button" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
    </section>

    <section class="lesson-card lesson-print-hide" style="margin-bottom:16px;">
        <div class="lesson-card__header">
            <div>
                <h2>Filter Timetable</h2>
                <p><?php echo $isStudentView ? 'Select the registered class session you want to view.' : 'Select the class, academic year, semester, and teacher you want to view.'; ?></p>
            </div>
            <span class="lesson-pill"><?php echo lesson_timetable_escape($activeTeacherLabel); ?></span>
        </div>
        <div class="lesson-card__body">
            <?php if($isStudentView && count($studentContexts) === 0){ ?>
            <div class="lesson-empty">Your class registration is not available yet, so your lesson timetable cannot be shown right now.</div>
            <?php } else { ?>
            <form method="get" action="lesson-timetable-report.php" class="lesson-form">
                <div class="lesson-filter-grid">
                    <?php if($isStudentView){ ?>
                    <div class="lesson-form-field lesson-form-field--full">
                        <label for="context">My Registered Class Session</label>
                        <select id="context" name="context">
                            <?php foreach($studentContexts as $contextRow){ ?>
                            <option value="<?php echo lesson_timetable_escape($contextRow['context_key']); ?>"<?php echo $selectedStudentContextKey === $contextRow['context_key'] ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($contextRow['context_label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } else { ?>
                    <div class="lesson-form-field">
                        <label for="batchid">Batch</label>
                        <select id="batchid" name="batchid">
                            <option value="">All Batches</option>
                            <?php foreach($batchRows as $batch){ ?>
                            <option value="<?php echo lesson_timetable_escape($batch['batchid']); ?>"<?php echo $filterBatchId === $batch['batchid'] ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($batch['batch']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="lesson-form-field">
                        <label for="academicyear">Academic Year</label>
                        <select id="academicyear" name="academicyear">
                            <?php foreach($academicYearOptions as $yearOption){ ?>
                            <option value="<?php echo lesson_timetable_escape($yearOption); ?>"<?php echo $filterAcademicYear === $yearOption ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($yearOption); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="lesson-form-field">
                        <label for="termname">Semester</label>
                        <select id="termname" name="termname">
                            <option value="">All</option>
                            <option value="1"<?php echo $filterTermName === '1' ? ' selected' : ''; ?>>1</option>
                            <option value="2"<?php echo $filterTermName === '2' ? ' selected' : ''; ?>>2</option>
                        </select>
                    </div>
                    <div class="lesson-form-field">
                        <label for="classid">Class</label>
                        <select id="classid" name="classid">
                            <option value="">All Classes</option>
                            <?php foreach($classRows as $classRow){ ?>
                            <option value="<?php echo lesson_timetable_escape($classRow['class_entryid']); ?>"<?php echo $filterClassId === $classRow['class_entryid'] ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($classRow['class_name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="lesson-form-field">
                        <label for="teacherid">Teacher</label>
                        <?php if($isTeacherView){ ?>
                        <input type="text" value="My timetable only" readonly>
                        <?php } else { ?>
                        <select id="teacherid" name="teacherid">
                            <option value="">All Teachers</option>
                            <?php foreach($teacherRows as $teacherRow){ $teacherName = trim($teacherRow['firstname'].' '.$teacherRow['othernames'].' '.$teacherRow['surname']); ?>
                            <option value="<?php echo lesson_timetable_escape($teacherRow['userid']); ?>"<?php echo $filterTeacherId === $teacherRow['userid'] ? ' selected' : ''; ?>><?php echo lesson_timetable_escape($teacherName.' ('.$teacherRow['userid'].')'); ?></option>
                            <?php } ?>
                        </select>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
                <div class="lesson-form-actions">
                    <button class="lesson-btn lesson-btn--secondary" type="submit"><i class="fa fa-filter"></i> Apply Filter</button>
                    <a class="lesson-btn lesson-btn--secondary" href="lesson-timetable-report.php"><i class="fa fa-refresh"></i> Reset</a>
                    <?php if(!$isStudentView && lesson_timetable_can_manage($con)){ ?>
                    <a class="lesson-btn" href="lesson-timetable.php?batchid=<?php echo urlencode($filterBatchId); ?>&academicyear=<?php echo urlencode($filterAcademicYear); ?>&termname=<?php echo urlencode($filterTermName); ?>&classid=<?php echo urlencode($filterClassId); ?>"><i class="fa fa-plus"></i> Manage Lessons</a>
                    <?php } ?>
                </div>
            </form>
            <?php } ?>
        </div>
    </section>

    <section class="lesson-card" style="margin-bottom:16px;">
        <div class="lesson-card__header">
            <div>
                <h2><?php echo $isStudentView ? 'Current Lesson' : 'Today'; ?></h2>
                <p><?php echo $isStudentView ? 'See what you should be attending right now and what comes next for today.' : lesson_timetable_escape(lesson_timetable_today_name()).' lesson summary for the current filter.'; ?></p>
            </div>
        </div>
        <div class="lesson-card__body">
            <?php if($isStudentView){ ?>
                <div class="lesson-now-grid">
                    <article class="lesson-helper-item lesson-helper-item--tinted">
                        <strong><?php echo $currentLessonRow ? 'In progress now' : 'No lesson right now'; ?></strong>
                        <?php if($currentLessonRow){ ?>
                        <span><?php echo lesson_timetable_escape($currentLessonRow['subject'].' | '.lesson_timetable_format_time($currentLessonRow['starttime']).' - '.lesson_timetable_format_time($currentLessonRow['endtime'])); ?></span>
                        <span><?php echo lesson_timetable_escape($currentLessonRow['teacher_name'].(trim((string)$currentLessonRow['location']) !== '' ? ' | '.$currentLessonRow['location'] : '')); ?></span>
                        <?php } else { ?>
                        <span><?php echo count($todayList) > 0 ? 'You do not have a lesson at this exact time.' : 'No lesson has been scheduled for today in this class session.'; ?></span>
                        <?php } ?>
                    </article>
                    <article class="lesson-helper-item">
                        <strong><?php echo $nextLessonRow ? 'Next lesson today' : 'No more lessons today'; ?></strong>
                        <?php if($nextLessonRow){ ?>
                        <span><?php echo lesson_timetable_escape($nextLessonRow['subject'].' | '.lesson_timetable_format_time($nextLessonRow['starttime']).' - '.lesson_timetable_format_time($nextLessonRow['endtime'])); ?></span>
                        <span><?php echo lesson_timetable_escape($nextLessonRow['teacher_name'].(trim((string)$nextLessonRow['location']) !== '' ? ' | '.$nextLessonRow['location'] : '')); ?></span>
                        <?php } else { ?>
                        <span>Once the next school day starts, your next lesson will appear here automatically.</span>
                        <?php } ?>
                    </article>
                </div>
            <?php } ?>
            <?php if(count($todayList) > 0){ ?>
            <div class="lesson-helper-list">
                <?php foreach($todayList as $row){ ?>
                <div class="lesson-helper-item<?php echo ($currentLessonRow && (string)$currentLessonRow['lessonid'] === (string)$row['lessonid']) ? ' lesson-helper-item--tinted' : ''; ?>">
                    <strong><?php echo lesson_timetable_escape(lesson_timetable_format_time($row['starttime']).' - '.lesson_timetable_format_time($row['endtime']).' | '.$row['subject']); ?></strong>
                    <span><?php echo lesson_timetable_escape($row['class_name'].' | '.$row['teacher_name'].(trim((string)$row['location']) !== '' ? ' | '.$row['location'] : '')); ?></span>
                </div>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="lesson-empty"><?php echo $isStudentView ? 'No lesson is scheduled for you today in the selected class session.' : 'No lesson is scheduled for today in the current filter.'; ?></div>
            <?php } ?>
        </div>
    </section>

    <section class="lesson-card">
        <div class="lesson-card__header">
            <div>
                <h2>Weekly Planner</h2>
                <p><?php echo $isStudentView ? 'Your full class timetable for the selected session, with today and the current lesson highlighted where relevant.' : ($filterTeacherId !== '' ? 'Showing the selected teacher view in a full-week planner.' : 'Showing all timetable entries in the current weekly planner.'); ?></p>
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

            <?php if(count($rows) > 0 && count($timeSlots) > 0){ ?>
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
                            <?php foreach($slotRows as $row){ $tokens = lesson_timetable_visual_tokens($row['subjectid'].'-'.$row['classid'].'-'.$row['teacherid']); $isCurrentLessonCard = ($currentLessonRow && (string)$currentLessonRow['lessonid'] === (string)$row['lessonid']); ?>
                            <article class="lesson-entry-card<?php echo $isCurrentLessonCard ? ' lesson-entry-card--current' : ''; ?>" style="--lesson-accent: <?php echo lesson_timetable_escape($tokens['accent']); ?>; --lesson-soft: <?php echo lesson_timetable_escape($tokens['soft']); ?>; --lesson-surface: <?php echo lesson_timetable_escape($tokens['surface']); ?>; --lesson-ink: <?php echo lesson_timetable_escape($tokens['ink']); ?>;">
                                <div class="lesson-entry-card__timeband"><?php echo lesson_timetable_escape(lesson_timetable_format_time($row['starttime']).' - '.lesson_timetable_format_time($row['endtime'])); ?></div>
                                <div class="lesson-entry-card__topline">
                                    <span class="lesson-entry-card__subject"><?php echo lesson_timetable_escape($row['subject']); ?></span>
                                    <span class="lesson-entry-card__tag"><?php echo lesson_timetable_escape($row['class_name']); ?></span>
                                </div>
                                <?php if($isCurrentLessonCard){ ?><div class="lesson-entry-card__live">Current lesson</div><?php } ?>
                                <div class="lesson-entry-card__title"><?php echo lesson_timetable_escape($row['teacher_name']); ?></div>
                                <div class="lesson-entry-card__meta">
                                    <span><i class="fa fa-calendar"></i> <?php echo lesson_timetable_escape($row['academicyear']); ?></span>
                                    <span><i class="fa fa-users"></i> <?php echo lesson_timetable_escape($row['batch']); ?></span>
                                    <span><i class="fa fa-calendar-check-o"></i> Semester <?php echo lesson_timetable_escape($row['termname']); ?></span>
                                    <?php if(trim((string)$row['location']) !== ''){ ?><span><i class="fa fa-map-marker"></i> <?php echo lesson_timetable_escape($row['location']); ?></span><?php } ?>
                                </div>
                                <?php if(trim((string)$row['note']) !== ''){ ?><p class="lesson-entry-card__note"><?php echo lesson_timetable_escape($row['note']); ?></p><?php } ?>
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
                <?php foreach($groupedRows as $day => $dayRows){ ?>
                <section class="lesson-agenda-day">
                    <div class="lesson-agenda-day__head">
                        <h3><?php echo lesson_timetable_escape($day); ?></h3>
                        <span><?php echo count($dayRows); ?> lesson<?php echo count($dayRows) === 1 ? '' : 's'; ?></span>
                    </div>
                    <div class="lesson-agenda-day__body">
                        <?php if(count($dayRows) > 0){ ?>
                            <?php foreach($dayRows as $row){ $tokens = lesson_timetable_visual_tokens($row['subjectid'].'-'.$row['classid'].'-'.$row['teacherid']); $isCurrentLessonCard = ($currentLessonRow && (string)$currentLessonRow['lessonid'] === (string)$row['lessonid']); ?>
                            <article class="lesson-entry-card lesson-entry-card--mobile<?php echo $isCurrentLessonCard ? ' lesson-entry-card--current' : ''; ?>" style="--lesson-accent: <?php echo lesson_timetable_escape($tokens['accent']); ?>; --lesson-soft: <?php echo lesson_timetable_escape($tokens['soft']); ?>; --lesson-surface: <?php echo lesson_timetable_escape($tokens['surface']); ?>; --lesson-ink: <?php echo lesson_timetable_escape($tokens['ink']); ?>;">
                                <div class="lesson-entry-card__timeband"><?php echo lesson_timetable_escape(lesson_timetable_format_time($row['starttime']).' - '.lesson_timetable_format_time($row['endtime'])); ?></div>
                                <div class="lesson-entry-card__topline">
                                    <span class="lesson-entry-card__subject"><?php echo lesson_timetable_escape($row['subject']); ?></span>
                                    <span class="lesson-entry-card__tag"><?php echo lesson_timetable_escape($row['class_name']); ?></span>
                                </div>
                                <?php if($isCurrentLessonCard){ ?><div class="lesson-entry-card__live">Current lesson</div><?php } ?>
                                <div class="lesson-entry-card__title"><?php echo lesson_timetable_escape($row['teacher_name']); ?></div>
                                <div class="lesson-entry-card__meta">
                                    <span><i class="fa fa-calendar"></i> <?php echo lesson_timetable_escape($row['academicyear']); ?></span>
                                    <span><i class="fa fa-users"></i> <?php echo lesson_timetable_escape($row['batch']); ?></span>
                                    <span><i class="fa fa-calendar-check-o"></i> Semester <?php echo lesson_timetable_escape($row['termname']); ?></span>
                                    <?php if(trim((string)$row['location']) !== ''){ ?><span><i class="fa fa-map-marker"></i> <?php echo lesson_timetable_escape($row['location']); ?></span><?php } ?>
                                </div>
                                <?php if(trim((string)$row['note']) !== ''){ ?><p class="lesson-entry-card__note"><?php echo lesson_timetable_escape($row['note']); ?></p><?php } ?>
                            </article>
                            <?php } ?>
                        <?php } else { ?>
                        <div class="lesson-empty">No lesson is scheduled for <?php echo lesson_timetable_escape($day); ?> in the current filter.</div>
                        <?php } ?>
                    </div>
                </section>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="lesson-empty lesson-empty--large"><?php echo $isStudentView ? 'No lesson timetable has been published yet for the selected class session.' : 'No timetable entries are available in the current filter yet.'; ?></div>
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

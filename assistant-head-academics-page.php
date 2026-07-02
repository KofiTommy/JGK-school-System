<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("company.php");
include_once("user-management-utils.php");
include_once("student-attendance-utils.php");
include_once("semester-registry-utils.php");
include_once("report-approval-utils.php");
include_once("course-registration-utils.php");

ensure_student_attendance_tables($con);
semester_registry_ensure_academic_year_column($con);
report_approval_ensure_table($con);
course_registration_ensure_tables($con);

if(!(function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user())){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

if(!function_exists('aha_esc')){
function aha_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('aha_first_name')){
function aha_first_name($fullName){
    $fullName = trim((string)$fullName);
    if($fullName === ''){
        return 'Academic Lead';
    }
    $parts = preg_split('/\s+/', $fullName);
    return isset($parts[0]) && trim((string)$parts[0]) !== '' ? trim((string)$parts[0]) : $fullName;
}
}

if(!function_exists('aha_fetch_scalar')){
function aha_fetch_scalar($con, $sql, $field, $default = 0){
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return isset($row[$field]) ? $row[$field] : $default;
    }
    return $default;
}
}

if(!function_exists('aha_can_module')){
function aha_can_module($con, $moduleKey){
    $moduleKey = trim((string)$moduleKey);
    if($moduleKey === ''){
        return true;
    }
    return function_exists('um_current_user_can_access_module') ? um_current_user_can_access_module($con, $moduleKey) : true;
}
}

$currentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$currentBranchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : '';
$currentBranchIdEsc = mysqli_real_escape_string($con, $currentBranchId);
$branchUserFilter = $currentBranchId !== '' ? " AND su.branchid='$currentBranchIdEsc' " : '';

$branchName = 'Whole School';
if($currentBranchId !== ''){
    $branchSql = mysqli_query($con, "SELECT location FROM tblbranch WHERE branchid='$currentBranchIdEsc' LIMIT 1");
    if($branchSql && ($branchRow = mysqli_fetch_array($branchSql, MYSQLI_ASSOC))){
        $branchName = trim((string)$branchRow['location']) !== '' ? trim((string)$branchRow['location']) : $branchName;
    }
}

$schoolName = isset($_CompanyName) && trim((string)$_CompanyName) !== '' ? trim((string)$_CompanyName) : 'School';
$assistantName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : 'Assistant Head Academics';
$assistantShortName = aha_first_name($assistantName);

$activeBatches = array();
$activeBatchSql = mysqli_query($con, "SELECT batch FROM tblbatch WHERE status='active' ORDER BY datetimeentry DESC");
if($activeBatchSql){
    while($batchRow = mysqli_fetch_array($activeBatchSql, MYSQLI_ASSOC)){
        $batchLabel = trim((string)$batchRow['batch']);
        if($batchLabel !== ''){
            $activeBatches[] = $batchLabel;
        }
    }
}
$activeBatchLabel = !empty($activeBatches) ? implode(', ', array_slice($activeBatches, 0, 3)) : 'No active semester';

$studentTotal = (int)aha_fetch_scalar($con, "SELECT COUNT(*) AS total_students FROM tblsystemuser su WHERE su.systemtype='Student' AND su.status='active' $branchUserFilter", 'total_students', 0);
$teacherTotal = (int)aha_fetch_scalar($con, "SELECT COUNT(*) AS total_teachers FROM tblsystemuser su WHERE su.systemtype='Teacher' AND su.status='active' $branchUserFilter", 'total_teachers', 0);
$classTotal = (int)aha_fetch_scalar($con, "SELECT COUNT(*) AS total_classes FROM tblclassentry", 'total_classes', 0);

$normalizedResidenceSql = "
  CASE
    WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('DAY','D') THEN 'Day'
    WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('BOARDING','BOARDER','B') THEN 'Boarding'
    ELSE ''
  END
";
$studentBreakdownSql = "
  SELECT
    CASE
      WHEN UPPER(TRIM(COALESCE(su.gender, ''))) IN ('M','MALE','BOY','B') THEN 'Male'
      WHEN UPPER(TRIM(COALESCE(su.gender, ''))) IN ('F','FEMALE','GIRL','G') THEN 'Female'
      ELSE 'Other'
    END AS gnorm,
    ".$normalizedResidenceSql." AS residence_group,
    COUNT(DISTINCT su.userid) AS cnt
  FROM tblsystemuser su
  INNER JOIN tblclass cl ON cl.userid=su.userid
  WHERE su.systemtype='Student'
    AND su.status='active'
    AND cl.status='active'
    ".$branchUserFilter."
  GROUP BY gnorm, residence_group
";
$studentStatsSql = "
  SELECT
    COUNT(DISTINCT su.userid) AS total_students,
    COUNT(DISTINCT CASE WHEN ".$normalizedResidenceSql." = '' THEN su.userid END) AS no_status_students
  FROM tblsystemuser su
  INNER JOIN tblclass cl ON cl.userid=su.userid
  WHERE su.systemtype='Student'
    AND su.status='active'
    AND cl.status='active'
    ".$branchUserFilter."
";

$residenceCounts = array(
    'Male' => array('Day' => 0, 'Boarding' => 0),
    'Female' => array('Day' => 0, 'Boarding' => 0),
);
$studentBreakdownRes = mysqli_query($con, $studentBreakdownSql);
if($studentBreakdownRes){
    while($breakdownRow = mysqli_fetch_array($studentBreakdownRes, MYSQLI_ASSOC)){
        $genderKey = isset($breakdownRow['gnorm']) ? trim((string)$breakdownRow['gnorm']) : '';
        $residenceKey = isset($breakdownRow['residence_group']) ? trim((string)$breakdownRow['residence_group']) : '';
        if(isset($residenceCounts[$genderKey][$residenceKey])){
            $residenceCounts[$genderKey][$residenceKey] = (int)$breakdownRow['cnt'];
        }
    }
}

$studentsNoStatus = 0;
$studentStatsRes = mysqli_query($con, $studentStatsSql);
if($studentStatsRes && ($studentStatsRow = mysqli_fetch_array($studentStatsRes, MYSQLI_ASSOC))){
    $studentsNoStatus = (int)$studentStatsRow['no_status_students'];
}

$boys_day = $residenceCounts['Male']['Day'];
$boys_boarding = $residenceCounts['Male']['Boarding'];
$girls_day = $residenceCounts['Female']['Day'];
$girls_boarding = $residenceCounts['Female']['Boarding'];

$attendanceSessionsToday = (int)aha_fetch_scalar($con, "SELECT COUNT(*) AS total_sessions FROM tblstudentattendancesession WHERE attendancedate=CURDATE()", 'total_sessions', 0);
$attendanceAssignments = (int)aha_fetch_scalar($con, "SELECT COUNT(*) AS total_assignments FROM tblclassteacher WHERE status='active'", 'total_assignments', 0);
$attendanceAwaiting = max(0, $attendanceAssignments - $attendanceSessionsToday);
$attendanceStatusSql = mysqli_query($con, "SELECT
        SUM(CASE WHEN ate.attendancestatus='present' THEN 1 ELSE 0 END) AS present_total,
        SUM(CASE WHEN ate.attendancestatus='late' THEN 1 ELSE 0 END) AS late_total,
        SUM(CASE WHEN ate.attendancestatus='absent' THEN 1 ELSE 0 END) AS absent_total,
        SUM(CASE WHEN ate.attendancestatus='excused' THEN 1 ELSE 0 END) AS excused_total
    FROM tblstudentattendanceentry ate
    INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
    WHERE ats.attendancedate=CURDATE()");
$attendanceStatus = array('present_total' => 0, 'late_total' => 0, 'absent_total' => 0, 'excused_total' => 0);
if($attendanceStatusSql && ($attendanceRow = mysqli_fetch_array($attendanceStatusSql, MYSQLI_ASSOC))){
    $attendanceStatus = array_merge($attendanceStatus, $attendanceRow);
}
$attendanceMarkedToday = (int)$attendanceStatus['present_total'] + (int)$attendanceStatus['late_total'] + (int)$attendanceStatus['absent_total'] + (int)$attendanceStatus['excused_total'];
$attendancePositiveToday = (int)$attendanceStatus['present_total'] + (int)$attendanceStatus['late_total'];
$attendanceRateToday = $attendanceMarkedToday > 0 ? round(($attendancePositiveToday / $attendanceMarkedToday) * 100, 1) : 0;
$attendanceCoverageRate = $attendanceAssignments > 0 ? round(($attendanceSessionsToday / $attendanceAssignments) * 100, 1) : 0;

$totalAssignedSubjects = (int)aha_fetch_scalar($con, "SELECT COUNT(DISTINCT assignmentid) AS total_assigned FROM tblsubjectassignment", 'total_assigned', 0);
$submittedSubjects = (int)aha_fetch_scalar($con, "SELECT COUNT(DISTINCT sa.assignmentid) AS submitted_total
    FROM tblsubjectassignment sa
    WHERE EXISTS (
        SELECT 1 FROM tblmark mk
        WHERE mk.assignmentid=sa.assignmentid
          AND mk.status='active'
    )", 'submitted_total', 0);
$pendingScoreAssignments = max(0, $totalAssignedSubjects - $submittedSubjects);
$scoreEntryRate = $totalAssignedSubjects > 0 ? round(($submittedSubjects / $totalAssignedSubjects) * 100, 1) : 0;

$yearSql = semester_registry_resolved_year_sql("tr");
$releaseWhere = "(CAST($yearSql AS UNSIGNED) > 2026 OR (CAST($yearSql AS UNSIGNED) = 2026 AND tr.termname >= 2))";
$approvalSummarySql = mysqli_query($con, "SELECT
        COUNT(*) AS total_scopes,
        SUM(CASE WHEN ra.status='approved' THEN 1 ELSE 0 END) AS approved_scopes
    FROM (
        SELECT DISTINCT tr.batchid, $yearSql AS academic_year, tr.termname, tr.class_entryid AS classid
        FROM tbltermregistry tr
        WHERE $releaseWhere
    ) sc
    LEFT JOIN tblclassreportapproval ra
        ON ra.batchid=sc.batchid
       AND ra.academicyear=sc.academic_year
       AND ra.termname=sc.termname
       AND ra.classid=sc.classid
       AND ra.status='approved'");
$reportApprovalTotal = 0;
$reportApprovedTotal = 0;
if($approvalSummarySql && ($approvalRow = mysqli_fetch_array($approvalSummarySql, MYSQLI_ASSOC))){
    $reportApprovalTotal = (int)$approvalRow['total_scopes'];
    $reportApprovedTotal = (int)$approvalRow['approved_scopes'];
}
$reportPendingTotal = max(0, $reportApprovalTotal - $reportApprovedTotal);
$reportReleaseRate = $reportApprovalTotal > 0 ? round(($reportApprovedTotal / $reportApprovalTotal) * 100, 1) : 0;

$openCourseWindows = (int)aha_fetch_scalar(
    $con,
    "SELECT COUNT(*) AS open_windows
     FROM tblcourseregwindow
     WHERE status='open'".($currentBranchId !== '' ? " AND branchid='$currentBranchIdEsc'" : ''),
    'open_windows',
    0
);

$riskStudents = (int)aha_fetch_scalar($con, "SELECT COUNT(*) AS total_risk FROM (
        SELECT
            ate.userid,
            SUM(CASE WHEN ate.attendancestatus='present' THEN 1 ELSE 0 END) AS present_total,
            SUM(CASE WHEN ate.attendancestatus='late' THEN 1 ELSE 0 END) AS late_total,
            SUM(CASE WHEN ate.attendancestatus='absent' THEN 1 ELSE 0 END) AS absent_total,
            COUNT(*) AS marked_total
        FROM tblstudentattendanceentry ate
        INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
        WHERE ats.attendancedate BETWEEN (CURDATE() - INTERVAL 30 DAY) AND CURDATE()
        GROUP BY ate.userid
        HAVING absent_total >= 3 OR ((present_total + late_total) / marked_total) < 0.75
    ) risk_scope", 'total_risk', 0);

$quickLinks = array(
    array('module' => 'student_progression', 'href' => 'student-history.php', 'icon' => 'fa-history', 'label' => 'Student Transcript'),
    array('module' => 'student_progression', 'href' => 'continuing-students.php', 'icon' => 'fa-users', 'label' => 'Continuing Students'),
    array('module' => 'student_progression', 'href' => 'promotion-center.php', 'icon' => 'fa-level-up', 'label' => 'Promotion Center'),
    array('module' => 'class_semester_registry', 'href' => 'view-class-registry.php', 'icon' => 'fa-folder-open', 'label' => 'View Class Registry'),
    array('module' => 'class_semester_registry', 'href' => 'term-registry.php', 'icon' => 'fa-plus', 'label' => 'Semester Registry'),
    array('module' => 'subject_management', 'href' => 'subject-classification.php', 'icon' => 'fa-book', 'label' => 'Subject Classification'),
    array('module' => 'subject_management', 'href' => 'subject-assignment.php', 'icon' => 'fa-plus', 'label' => 'Subject Assignment'),
    array('module' => 'subject_management', 'href' => 'view-all-subject-assigned.php', 'icon' => 'fa-book', 'label' => 'Assigned Subjects'),
    array('module' => 'class_teacher_assignment', 'href' => 'class-teacher-assignment.php', 'icon' => 'fa-users', 'label' => 'Class Teacher Assignment'),
    array('module' => 'student_attendance', 'href' => 'student-attendance-report.php', 'icon' => 'fa-bar-chart', 'label' => 'Attendance Summary'),
    array('module' => 'reports', 'href' => 'terminal-report.php', 'icon' => 'fa-file-text-o', 'label' => 'Examination Report'),
    array('module' => 'reports', 'href' => 'report-approval-board.php', 'icon' => 'fa-check-circle', 'label' => 'Report Approval'),
    array('module' => 'reports', 'href' => 'internal-exam-analysis.php', 'icon' => 'fa-bar-chart', 'label' => 'Internal Exams Analysis'),
    array('module' => 'reports', 'href' => 'waec-analysis.php', 'icon' => 'fa-line-chart', 'label' => 'WAEC Analysis'),
    array('module' => 'lesson_timetable', 'href' => 'lesson-timetable-report.php', 'icon' => 'fa-calendar', 'label' => 'Lesson Timetable'),
    array('module' => 'examination_timetable', 'href' => 'examinationtimetablereport.php', 'icon' => 'fa-book', 'label' => 'Exam Time Table'),
    array('module' => 'course_registration', 'href' => 'course-registration-admin.php', 'icon' => 'fa-list-alt', 'label' => 'Course Registration'),
    array('module' => 'notice_communication', 'href' => 'messages.php', 'icon' => 'fa-comments', 'label' => 'Messages'),
    array('module' => 'notice_communication', 'href' => 'notification.php', 'icon' => 'fa-bullhorn', 'label' => 'Send Notice')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
include("title.php");
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/headmaster-dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
</head>
<body class="hm-page">
<div class="header">
    <?php include("menu.php"); ?>
</div>

<main class="hm-shell">
    <aside class="hm-sidebar">
        <div class="hm-sidebar__inner">
            <?php
            include("welcome.php");
            include("menuboard.php");
            ?>
        </div>
    </aside>

    <section class="hm-main">
        <section class="hm-hero hm-hero--single">
            <div class="hm-hero__copy">
                <span class="hm-kicker">Assistant Head Academics</span>
                <h1><?php echo aha_esc($schoolName); ?></h1>
                <p>Academic leadership overview for <?php echo aha_esc($assistantShortName); ?>. Follow attendance, score entry, report release, and core academic structure from one place.</p>
                <div class="hm-hero__footer">
                    <div class="hm-context">
                        <span><?php echo aha_esc($branchName); ?></span>
                        <span><?php echo aha_esc($activeBatchLabel); ?></span>
                        <span><?php echo aha_esc(date("d M Y")); ?></span>
                    </div>
                    <div class="hm-live-clock-wrap">
                        <div class="xschool-live-clock hm-live-clock" data-live-clock>
                            <div class="xschool-live-clock__top">
                                <span class="xschool-live-clock__eyebrow">Live Date &amp; Time</span>
                                <span class="xschool-live-clock__status"><i class="fa fa-circle"></i> Live</span>
                            </div>
                            <div class="xschool-live-clock__time" data-live-clock-time>--:--:--</div>
                            <div class="xschool-live-clock__date" data-live-clock-date>Loading current date</div>
                            <div class="xschool-live-clock__zone" data-live-clock-zone>Local time</div>
                        </div>
                    </div>
                </div>
                <?php if(aha_can_module($con, 'student_search')){ ?>
                <div class="hm-desktop-search" data-aha-desktop-search>
                    <form class="hm-desktop-search-form" id="aha-desktop-search-form" autocomplete="off">
                        <label class="hm-desktop-search-label" for="aha-desktop-search-input">Academic Search</label>
                        <div class="hm-desktop-search-field">
                            <i class="fa fa-search"></i>
                            <input
                                class="hm-desktop-search-input"
                                type="search"
                                id="aha-desktop-search-input"
                                name="aha_desktop_search"
                                placeholder="Search students, teachers, classes, batches, or academic tools"
                                aria-label="Search students, teachers, classes, batches, or academic tools"
                            >
                            <button type="submit" class="hm-desktop-search-submit">Search</button>
                        </div>
                        <p class="hm-desktop-search-hint">Search academic records and key teaching tools from one place.</p>
                    </form>
                    <div class="hm-desktop-search-results" id="aha-desktop-search-results" hidden></div>
                </div>
                <?php } ?>
            </div>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Dashboard Summary</span>
                    <h2>Academic student breakdown and school totals</h2>
                </div>
            </div>
            <div class="dashboard-flex" role="region" aria-label="Assistant head academics summary dashboard">
                <div class="chart-side">
                    <div class="chart-container">
                        <div class="chart-canvas-wrap">
                            <canvas id="academicStudentChart" aria-label="Student distribution by gender and residence"></canvas>
                        </div>
                        <p class="chart-note">This graph shows student groups by gender and residence, while the tiles beside it keep the main academic totals close at hand.</p>
                    </div>
                </div>
                <div class="cards-side">
                    <div class="card total" role="article" aria-label="Total Active Students">
                        <h4><i class="fa fa-users" style="color:#fff; margin-right:4px;"></i>Total Active Students</h4>
                        <p><?php echo number_format($studentTotal); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Active Teachers">
                        <h4><i class="fa fa-users" style="color:#0f766e; margin-right:4px;"></i>Teachers</h4>
                        <p><?php echo number_format($teacherTotal); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Registered Classes">
                        <h4><i class="fa fa-building-o" style="color:#2563eb; margin-right:4px;"></i>Classes</h4>
                        <p><?php echo number_format($classTotal); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Boys Day Students">
                        <h4><i class="fa fa-male" style="color:#2563eb; margin-right:4px;"></i>Boys - Day</h4>
                        <p><?php echo number_format($boys_day); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Boys Boarding Students">
                        <h4><i class="fa fa-male" style="color:#38bdf8; margin-right:4px;"></i>Boys - Boarding</h4>
                        <p><?php echo number_format($boys_boarding); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Girls Day Students">
                        <h4><i class="fa fa-female" style="color:#db2777; margin-right:4px;"></i>Girls - Day</h4>
                        <p><?php echo number_format($girls_day); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Girls Boarding Students">
                        <h4><i class="fa fa-female" style="color:#f472b6; margin-right:4px;"></i>Girls - Boarding</h4>
                        <p><?php echo number_format($girls_boarding); ?></p>
                    </div>
                    <div class="card" role="article" aria-label="Students With No Residence Status">
                        <h4><i class="fa fa-question-circle" style="color:#b45309; margin-right:4px;"></i>No Residence Status</h4>
                        <p><?php echo number_format($studentsNoStatus); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Academic Watch</span>
                    <h2>Today at a glance</h2>
                </div>
            </div>
            <div class="hm-panel-grid hm-panel-grid--three">
                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Attendance</span>
                            <h2>Class attendance watch</h2>
                        </div>
                    </div>
                    <div class="hm-progress">
                        <div class="hm-progress__label">
                            <span>Attendance coverage</span>
                            <strong><?php echo aha_esc(number_format($attendanceCoverageRate, 1)); ?>%</strong>
                        </div>
                        <div class="hm-progress__track"><span style="width: <?php echo aha_esc(max(0, min(100, $attendanceCoverageRate))); ?>%;"></span></div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--three hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Registers Marked</span>
                            <strong><?php echo number_format($attendanceSessionsToday); ?></strong>
                            <small>Class attendance sessions marked today.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--blue">
                            <span>Awaiting Marking</span>
                            <strong><?php echo number_format($attendanceAwaiting); ?></strong>
                            <small>Class registers still waiting to be marked.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Attendance Risk</span>
                            <strong><?php echo number_format($riskStudents); ?></strong>
                            <small>Students flagged by the 30-day attendance watch.</small>
                        </article>
                    </div>
                    <p class="hm-panel__note">Student attendance rate today is <?php echo aha_esc(number_format($attendanceRateToday, 1)); ?>%, with <?php echo number_format($attendanceMarkedToday); ?> attendance record(s) already captured.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span><?php echo number_format($attendanceAssignments); ?> class teacher assignment(s) are currently active.</span>
                        <a href="student-attendance-report.php">Open attendance summary</a>
                    </div>
                </section>

                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Results</span>
                            <h2>Result release status</h2>
                        </div>
                    </div>
                    <div class="hm-progress hm-progress--gold">
                        <div class="hm-progress__label">
                            <span>Report release progress</span>
                            <strong><?php echo aha_esc(number_format($reportReleaseRate, 1)); ?>%</strong>
                        </div>
                        <div class="hm-progress__track"><span style="width: <?php echo aha_esc(max(0, min(100, $reportReleaseRate))); ?>%;"></span></div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--three hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Reports Released</span>
                            <strong><?php echo number_format($reportApprovedTotal); ?></strong>
                            <small>Class report scopes already released.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Awaiting Release</span>
                            <strong><?php echo number_format($reportPendingTotal); ?></strong>
                            <small>Report scopes still waiting to be released.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--gold">
                            <span>Pending Score Entry</span>
                            <strong><?php echo number_format($pendingScoreAssignments); ?></strong>
                            <small>Assigned subject records still without scores.</small>
                        </article>
                    </div>
                    <p class="hm-panel__note">Report release stands at <?php echo aha_esc(number_format($reportReleaseRate, 1)); ?>%, while score entry completion is <?php echo aha_esc(number_format($scoreEntryRate, 1)); ?>%.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span><?php echo number_format($reportApprovalTotal); ?> report scope(s) are currently being tracked.</span>
                        <a href="terminal-report.php">Open examination report</a>
                    </div>
                </section>

                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Structure</span>
                            <h2>Academic structure watch</h2>
                        </div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--three hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Subject Assignments</span>
                            <strong><?php echo number_format($totalAssignedSubjects); ?></strong>
                            <small>Teacher subject assignment records on file.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--blue">
                            <span>Class Teacher Roles</span>
                            <strong><?php echo number_format($attendanceAssignments); ?></strong>
                            <small>Active class teacher assignment records.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--gold">
                            <span>Open Course Windows</span>
                            <strong><?php echo number_format($openCourseWindows); ?></strong>
                            <small>Course registration windows currently open.</small>
                        </article>
                    </div>
                    <p class="hm-panel__note">Keep subject allocation, class teacher assignments, and course registration windows aligned with the current academic timetable.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span><?php echo number_format($classTotal); ?> class record(s) are currently active in the academic structure.</span>
                        <a href="<?php echo aha_can_module($con, 'course_registration') ? 'course-registration-admin.php' : 'subject-assignment.php'; ?>">Open academic setup</a>
                    </div>
                </section>
            </div>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Quick Links</span>
                    <h2>Open important academic tools</h2>
                </div>
            </div>
            <div class="hm-quick-grid">
                <?php foreach($quickLinks as $quickLink){ ?>
                    <?php if(aha_can_module($con, $quickLink['module'])){ ?>
                    <a class="hm-quick-card" href="<?php echo aha_esc($quickLink['href']); ?>">
                        <span class="hm-quick-card__icon"><i class="fa <?php echo aha_esc($quickLink['icon']); ?>"></i></span>
                        <span><?php echo aha_esc($quickLink['label']); ?></span>
                    </a>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>
    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var desktopSearchWrap = document.querySelector('[data-aha-desktop-search]');
    if (desktopSearchWrap) {
        var searchForm = document.getElementById('aha-desktop-search-form');
        var searchInput = document.getElementById('aha-desktop-search-input');
        var searchResults = document.getElementById('aha-desktop-search-results');
        var searchTimer = null;
        var searchRequestIndex = 0;

        function setSearchResults(html) {
            if (!searchResults) {
                return;
            }
            searchResults.innerHTML = html;
            searchResults.removeAttribute('hidden');
        }

        function closeSearchResults() {
            if (!searchResults) {
                return;
            }
            searchResults.setAttribute('hidden', 'hidden');
            searchResults.innerHTML = '';
        }

        function runDesktopSearch(forceSearch) {
            if (!searchInput || !searchResults) {
                return;
            }
            var query = searchInput.value.trim();
            if (query === '') {
                closeSearchResults();
                return;
            }
            if (query.length < 2 && !forceSearch) {
                setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-search'></i><div><strong>Keep typing</strong><span>Use at least 2 characters to search the dashboard.</span></div></div>");
                return;
            }

            setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-spinner fa-spin'></i><div><strong>Searching</strong><span>Checking students, teachers, classes, batches, and academic tools.</span></div></div>");
            var requestId = ++searchRequestIndex;
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4 || requestId !== searchRequestIndex) {
                    return;
                }
                if (xhr.status === 200) {
                    setSearchResults(xhr.responseText);
                } else if (xhr.status === 403) {
                    setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-lock'></i><div><strong>Access denied</strong><span>You do not have access to academic dashboard search.</span></div></div>");
                } else {
                    setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-exclamation-circle'></i><div><strong>Search failed</strong><span>Try again in a moment.</span></div></div>");
                }
            };
            xhr.open('GET', 'assistant-head-academics-global-search.php?q=' + encodeURIComponent(query), true);
            xhr.send();
        }

        if (searchForm) {
            searchForm.addEventListener('submit', function (event) {
                event.preventDefault();
                runDesktopSearch(true);
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }
                searchTimer = setTimeout(function () {
                    runDesktopSearch(false);
                }, 220);
            });

            searchInput.addEventListener('focus', function () {
                if (searchInput.value.trim() !== '') {
                    runDesktopSearch(false);
                }
            });
        }

        document.addEventListener('click', function (event) {
            if (!desktopSearchWrap.contains(event.target)) {
                closeSearchResults();
            }
        });
    }

    if (typeof Chart !== 'undefined') {
        var studentCanvas = document.getElementById('academicStudentChart');
        if (studentCanvas) {
            var chartContext = studentCanvas.getContext('2d');
            var existingChart = typeof Chart.getChart === 'function' ? Chart.getChart(studentCanvas) : null;
            if (existingChart) {
                existingChart.destroy();
            }

            studentCanvas.style.width = '100%';
            studentCanvas.style.height = '100%';

            window.academicStudentChartInstance = new Chart(chartContext, {
                type: 'bar',
                data: {
                    labels: [
                        ['Boys', 'Day'],
                        ['Boys', 'Boarding'],
                        ['Girls', 'Day'],
                        ['Girls', 'Boarding'],
                        ['No Residence', 'Status']
                    ],
                    datasets: [{
                        label: 'Students',
                        data: [<?php echo $boys_day; ?>, <?php echo $boys_boarding; ?>, <?php echo $girls_day; ?>, <?php echo $girls_boarding; ?>, <?php echo $studentsNoStatus; ?>],
                        backgroundColor: ['#2563eb', '#38bdf8', '#db2777', '#f472b6', '#d59b2d'],
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 46
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    resizeDelay: 150,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Student Population by Group',
                            font: { size: 15, weight: '600' },
                            color: '#111827',
                            padding: { top: 8, bottom: 16 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var value = context.parsed.y || 0;
                                    var total = <?php echo $studentTotal; ?>;
                                    var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return value.toLocaleString() + ' student(s) (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: '#475569',
                                font: {
                                    size: 11,
                                    weight: '600'
                                },
                                padding: 8,
                                maxRotation: 0,
                                autoSkip: false
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#475569',
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.2)'
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
</body>
</html>

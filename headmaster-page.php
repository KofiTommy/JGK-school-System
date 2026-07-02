<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("company.php");
include_once("user-management-utils.php");
include_once("student-attendance-utils.php");
include_once("semester-registry-utils.php");
include_once("report-approval-utils.php");
include_once("online-admission-utils.php");
include_once("counselling-utils.php");
include_once("audit_notifications.php");
include_once("duty-roster-utils.php");
include_once("house-master-utils.php");

ensure_student_attendance_tables($con);
semester_registry_ensure_academic_year_column($con);
report_approval_ensure_table($con);
ensure_online_admission_tables($con);
ensure_counselling_tables($con);
ensureSystemChangeLogTable($con);
ensure_duty_roster_tables($con);
ensure_house_tables($con);

if(!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Headmaster')){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

if(!function_exists('hm_esc')){
function hm_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('hm_first_name')){
function hm_first_name($fullName){
    $fullName = trim((string)$fullName);
    if($fullName === ''){
        return 'Headmaster';
    }
    $parts = preg_split('/\s+/', $fullName);
    return isset($parts[0]) && trim((string)$parts[0]) !== '' ? trim((string)$parts[0]) : $fullName;
}
}

if(!function_exists('hm_money')){
function hm_money($amount){
    $symbol = isset($_SESSION['SYMBOL']) && trim((string)$_SESSION['SYMBOL']) !== '' ? trim((string)$_SESSION['SYMBOL']) : 'GHS';
    return $symbol.' '.number_format((float)$amount, 2);
}
}

if(!function_exists('hm_fetch_scalar')){
function hm_fetch_scalar($con, $sql, $field, $default = 0){
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return isset($row[$field]) ? $row[$field] : $default;
    }
    return $default;
}
}

if(!function_exists('hm_can_module')){
function hm_can_module($con, $moduleKey){
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
$branchAdmissionFilter = $currentBranchId !== '' ? " WHERE branchid='$currentBranchIdEsc' " : '';
$branchHelpFilter = $currentBranchId !== '' ? " WHERE branchid='$currentBranchIdEsc' " : '';

$branchName = 'Whole School';
if($currentBranchId !== ''){
    $branchSql = mysqli_query($con, "SELECT location FROM tblbranch WHERE branchid='$currentBranchIdEsc' LIMIT 1");
    if($branchSql && ($branchRow = mysqli_fetch_array($branchSql, MYSQLI_ASSOC))){
        $branchName = trim((string)$branchRow['location']) !== '' ? trim((string)$branchRow['location']) : $branchName;
    }
}

$schoolName = isset($_CompanyName) && trim((string)$_CompanyName) !== '' ? trim((string)$_CompanyName) : 'School';
$headmasterName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : 'Headmaster';
$headmasterShortName = hm_first_name($headmasterName);
$todayDate = date("Y-m-d");
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

$studentTotal = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_students FROM tblsystemuser su WHERE su.systemtype='Student' AND su.status='active' $branchUserFilter", 'total_students', 0);
$teacherTotal = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_teachers FROM tblsystemuser su WHERE su.systemtype='Teacher' AND su.status='active' $branchUserFilter", 'total_teachers', 0);
$officeTotal = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_users FROM tblsystemuser su WHERE su.accesslevel='user' AND su.status='active' $branchUserFilter", 'total_users', 0);
$classTotal = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_classes FROM tblclassentry", 'total_classes', 0);

$normalizedResidenceSql = "
  CASE
    WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('DAY','D') THEN 'Day'
    WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('BOARDING','BOARDER','B') THEN 'Boarding'
    ELSE ''
  END
";
$headmasterStudentBreakdownSql = "
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
$headmasterStudentStatsSql = "
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
$headmasterBreakdownRes = mysqli_query($con, $headmasterStudentBreakdownSql);
if($headmasterBreakdownRes){
    while($breakdownRow = mysqli_fetch_array($headmasterBreakdownRes, MYSQLI_ASSOC)){
        $genderKey = isset($breakdownRow['gnorm']) ? trim((string)$breakdownRow['gnorm']) : '';
        $residenceKey = isset($breakdownRow['residence_group']) ? trim((string)$breakdownRow['residence_group']) : '';
        if(isset($residenceCounts[$genderKey][$residenceKey])){
            $residenceCounts[$genderKey][$residenceKey] = (int)$breakdownRow['cnt'];
        }
    }
}

$studentsNoStatus = 0;
$headmasterStudentStatsRes = mysqli_query($con, $headmasterStudentStatsSql);
if($headmasterStudentStatsRes && ($studentStatsRow = mysqli_fetch_array($headmasterStudentStatsRes, MYSQLI_ASSOC))){
    $studentsNoStatus = (int)$studentStatsRow['no_status_students'];
}

$boys_day = $residenceCounts['Male']['Day'];
$boys_boarding = $residenceCounts['Male']['Boarding'];
$girls_day = $residenceCounts['Female']['Day'];
$girls_boarding = $residenceCounts['Female']['Boarding'];
$boys_total = $boys_day + $boys_boarding;
$girls_total = $girls_day + $girls_boarding;
$day_total = $boys_day + $girls_day;
$boarding_total = $boys_boarding + $girls_boarding;
$studentsWithStatusTotal = $boys_total + $girls_total;
$attendanceSessionsToday = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_sessions FROM tblstudentattendancesession WHERE attendancedate=CURDATE()", 'total_sessions', 0);
$attendanceAssignments = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_assignments FROM tblclassteacher WHERE status='active'", 'total_assignments', 0);
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

$totalAssignedSubjects = (int)hm_fetch_scalar($con, "SELECT COUNT(DISTINCT assignmentid) AS total_assigned FROM tblsubjectassignment", 'total_assigned', 0);
$submittedSubjects = (int)hm_fetch_scalar($con, "SELECT COUNT(DISTINCT sa.assignmentid) AS submitted_total
    FROM tblsubjectassignment sa
    WHERE EXISTS (
        SELECT 1 FROM tblmark mk
        WHERE mk.assignmentid=sa.assignmentid
          AND mk.status='active'
    )", 'submitted_total', 0);
$pendingScoreAssignments = max(0, $totalAssignedSubjects - $submittedSubjects);

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

$billingTotal = (float)hm_fetch_scalar($con, "SELECT COALESCE(SUM(cost),0) AS billed_total FROM tblbilling WHERE status='active'", 'billed_total', 0);
$paymentTotal = (float)hm_fetch_scalar($con, "SELECT COALESCE(SUM(payment),0) AS paid_total FROM tblpayment WHERE status='active'", 'paid_total', 0);
$outstandingTotal = max(0, $billingTotal - $paymentTotal);
$paymentsToday = (float)hm_fetch_scalar($con, "SELECT COALESCE(SUM(payment),0) AS paid_today FROM tblpayment WHERE status='active' AND DATE(datetimepayment)=CURDATE()", 'paid_today', 0);

$dutyBranchFilter = $currentBranchId !== '' ? " AND dr.userid IN (SELECT userid FROM tblsystemuser WHERE branchid='$currentBranchIdEsc' AND systemtype='Teacher') " : '';
$dutyTodayCount = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_duty
    FROM tbldutyroster dr
    WHERE dr.status='active'
      AND '$todayDate' BETWEEN dr.startdate AND dr.enddate
      $dutyBranchFilter", 'total_duty', 0);
$dutyTodayNames = array();
$dutyTodayRes = mysqli_query($con, "SELECT DISTINCT CONCAT_WS(' ', su.firstname, su.othernames, su.surname) AS teacher_name
    FROM tbldutyroster dr
    INNER JOIN tblsystemuser su ON su.userid=dr.userid
    WHERE dr.status='active'
      AND '$todayDate' BETWEEN dr.startdate AND dr.enddate
      $branchUserFilter
    ORDER BY su.firstname ASC, su.surname ASC");
if($dutyTodayRes){
    while($dutyTodayRow = mysqli_fetch_array($dutyTodayRes, MYSQLI_ASSOC)){
        $teacherName = trim((string)$dutyTodayRow['teacher_name']);
        if($teacherName !== ''){
            $dutyTodayNames[] = $teacherName;
        }
    }
}
$dutyTodaySummary = count($dutyTodayNames) > 0 ? duty_roster_team_summary_from_names($dutyTodayNames, 3) : 'No teacher currently on duty.';
$dutyTodayTeacherCount = count($dutyTodayNames);

$seniorMasterName = '--';
$seniorMistressName = '--';
$seniorLeadershipRes = mysqli_query($con, "SELECT
    sha.designation,
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), sha.userid) AS teacher_name
    FROM tblseniorhouseauthority sha
    LEFT JOIN tblsystemuser su ON su.userid=sha.userid
    WHERE sha.status='active'
    ORDER BY sha.datetimeentry DESC");
if($seniorLeadershipRes){
    while($seniorLeadershipRow = mysqli_fetch_array($seniorLeadershipRes, MYSQLI_ASSOC)){
        $designation = trim((string)$seniorLeadershipRow['designation']);
        $teacherName = trim((string)$seniorLeadershipRow['teacher_name']);
        if($designation === 'Senior House Master' && $seniorMasterName === '--'){
            $seniorMasterName = $teacherName !== '' ? $teacherName : '--';
        }
        if($designation === 'Senior House Mistress' && $seniorMistressName === '--'){
            $seniorMistressName = $teacherName !== '' ? $teacherName : '--';
        }
    }
}

$seniorHouseOverview = array(
    'active_houses' => 0,
    'active_supervisors' => 0,
    'assigned_students' => 0,
    'pending_exeat' => 0,
    'active_out' => 0,
    'overdue_returns' => 0,
    'returned_today' => 0,
    'external_pending' => 0,
    'internal_pending' => 0
);
$seniorBranchStudentFilter = $currentBranchId !== '' ? " AND su.branchid='$currentBranchIdEsc' " : '';
$seniorOverviewSql = "SELECT
    (SELECT COUNT(*) FROM tblhouse WHERE status='active') AS active_houses,
    (SELECT COUNT(*) FROM tblhousemaster WHERE status='active') AS active_supervisors,
    (SELECT COUNT(*)
        FROM tblstudenthouse sh
        INNER JOIN tblsystemuser su ON su.userid=sh.userid
        WHERE sh.status='active'
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS assigned_students,
    (SELECT COUNT(*)
        FROM tblexeatrequest er
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.status='pending'
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS pending_exeat,
    (SELECT COUNT(*)
        FROM tblexeatrequest er
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.status='approved'
          AND er.actualreturndatetime IS NULL
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS active_out,
    (SELECT COUNT(*)
        FROM tblexeatrequest er
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE ".house_master_exeat_overdue_sql('er')."
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS overdue_returns,
    (SELECT COUNT(*)
        FROM tblexeatrequest er
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.actualreturndatetime IS NOT NULL
          AND DATE(er.actualreturndatetime)=CURDATE()
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS returned_today,
    (SELECT COUNT(*)
        FROM tblexeatrequest er
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.status='pending'
          AND LOWER(COALESCE(er.exeattype,'external'))='external'
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS external_pending,
    (SELECT COUNT(*)
        FROM tblexeatrequest er
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.status='pending'
          AND LOWER(COALESCE(er.exeattype,'external'))='internal'
          AND su.systemtype='Student'
          AND su.status='active'
          $seniorBranchStudentFilter
    ) AS internal_pending";
$seniorOverviewRes = mysqli_query($con, $seniorOverviewSql);
if($seniorOverviewRes && ($seniorOverviewRow = mysqli_fetch_array($seniorOverviewRes, MYSQLI_ASSOC))){
    $seniorHouseOverview = array_merge($seniorHouseOverview, $seniorOverviewRow);
}

$admissionSummarySql = mysqli_query($con, "SELECT
        SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) AS submitted_total,
        SUM(CASE WHEN status='needs_attention' THEN 1 ELSE 0 END) AS needs_attention_total,
        SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END) AS reviewed_total,
        SUM(CASE WHEN DATE(submittedat)=CURDATE() AND status IN('submitted','needs_attention','reviewed') THEN 1 ELSE 0 END) AS submitted_today
    FROM tblonlineadmissionapplication".$branchAdmissionFilter);
$admissionSubmittedTotal = 0;
$admissionNeedsAttentionTotal = 0;
$admissionReviewedTotal = 0;
$admissionSubmittedToday = 0;
if($admissionSummarySql && ($admissionRow = mysqli_fetch_array($admissionSummarySql, MYSQLI_ASSOC))){
    $admissionSubmittedTotal = (int)$admissionRow['submitted_total'];
    $admissionNeedsAttentionTotal = (int)$admissionRow['needs_attention_total'];
    $admissionReviewedTotal = (int)$admissionRow['reviewed_total'];
    $admissionSubmittedToday = (int)$admissionRow['submitted_today'];
}
$admissionPendingTotal = $admissionSubmittedTotal + $admissionNeedsAttentionTotal;

$helpRequestTotal = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_help FROM tblonlineadmissionhelprequest".$branchHelpFilter, 'total_help', 0);
$unreadMessages = (int)um_message_unread_count($con, $currentUserId, 'Headmaster');
$schoolCounsellorRow = function_exists('counselling_school_assignment_row') ? counselling_school_assignment_row($con) : null;
$schoolCounsellorName = $schoolCounsellorRow ? counselling_person_name($schoolCounsellorRow) : 'Not assigned';
$counsellingSummary = array(
    'active_cases' => 0,
    'pending_cases' => 0,
    'urgent_cases' => 0,
    'sessions_today' => 0
);
$counsellingSummarySql = mysqli_query($con, "SELECT
        SUM(CASE WHEN cr.status IN('pending','accepted','rescheduled') THEN 1 ELSE 0 END) AS active_cases,
        SUM(CASE WHEN cr.status='pending' THEN 1 ELSE 0 END) AS pending_cases,
        SUM(CASE WHEN cr.status IN('pending','accepted','rescheduled') AND LOWER(COALESCE(cr.urgency,'')) IN('high','urgent') THEN 1 ELSE 0 END) AS urgent_cases,
        SUM(CASE WHEN cr.status IN('pending','accepted','rescheduled') AND cr.scheduled_date=CURDATE() THEN 1 ELSE 0 END) AS sessions_today
    FROM tblcounsellingrequest cr
    INNER JOIN tblsystemuser su ON su.userid=cr.studentid
    WHERE su.systemtype='Student'
      AND su.status='active'
      $branchUserFilter");
if($counsellingSummarySql && ($counsellingSummaryRow = mysqli_fetch_array($counsellingSummarySql, MYSQLI_ASSOC))){
    $counsellingSummary = array_merge($counsellingSummary, $counsellingSummaryRow);
}

$riskStudents = (int)hm_fetch_scalar($con, "SELECT COUNT(*) AS total_risk FROM (
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
$attendanceCoverageRate = $attendanceAssignments > 0 ? round(($attendanceSessionsToday / $attendanceAssignments) * 100, 1) : 0;
$reportReleaseRate = $reportApprovalTotal > 0 ? round(($reportApprovedTotal / $reportApprovalTotal) * 100, 1) : 0;
$scoreEntryRate = $totalAssignedSubjects > 0 ? round(($submittedSubjects / $totalAssignedSubjects) * 100, 1) : 0;

$attentionItems = array();
if($reportPendingTotal > 0){
    $attentionItems[] = array(
        'title' => 'Some class reports are still awaiting release.',
        'detail' => number_format($reportPendingTotal).' class report scope'.($reportPendingTotal === 1 ? ' is' : 's are').' not yet released.',
        'href' => 'terminal-report.php',
        'label' => 'Open Examination Report'
    );
}
if($pendingScoreAssignments > 0){
    $attentionItems[] = array(
        'title' => 'Score entry is still outstanding.',
        'detail' => number_format($pendingScoreAssignments).' assigned subject record'.($pendingScoreAssignments === 1 ? ' has' : 's have').' no score entry yet.',
        'href' => 'terminal-report.php',
        'label' => 'Open Examination Report'
    );
}
if($admissionPendingTotal > 0){
    $attentionItems[] = array(
        'title' => 'Admissions are waiting for review.',
        'detail' => number_format($admissionPendingTotal).' submitted admission form'.($admissionPendingTotal === 1 ? ' is' : 's are').' still open.',
        'href' => 'online-admission-admin.php',
        'label' => 'Open Admission Desk'
    );
}
if($unreadMessages > 0){
    $attentionItems[] = array(
        'title' => 'You have unread school messages.',
        'detail' => number_format($unreadMessages).' message'.($unreadMessages === 1 ? '' : 's').' need your attention.',
        'href' => 'messages.php',
        'label' => 'Open Messages'
    );
}
if($riskStudents > 0){
    $attentionItems[] = array(
        'title' => 'Attendance risk is rising.',
        'detail' => number_format($riskStudents).' student'.($riskStudents === 1 ? ' has' : 's have').' weak attendance in the last 30 days.',
        'href' => 'student-attendance-report.php',
        'label' => 'Review Attendance'
    );
}
if($dutyTodayCount > 0){
    $attentionItems[] = array(
        'title' => 'Teachers are currently on duty.',
        'detail' => number_format($dutyTodayCount).' duty assignment'.($dutyTodayCount === 1 ? ' is' : 's are').' active today. '.$dutyTodaySummary,
        'href' => 'duty-roster.php',
        'label' => 'Open Duty Roster'
    );
}
if((int)$seniorHouseOverview['pending_exeat'] > 0 || (int)$seniorHouseOverview['overdue_returns'] > 0){
    $attentionItems[] = array(
        'title' => 'Senior house welfare needs attention.',
        'detail' => number_format((int)$seniorHouseOverview['pending_exeat']).' pending exeat request(s) and '.number_format((int)$seniorHouseOverview['overdue_returns']).' overdue return(s) are currently on record.',
        'href' => 'senior-house-dashboard.php',
        'label' => 'Open Senior House Overview'
    );
}
if(empty($attentionItems)){
    $attentionItems[] = array(
        'title' => 'No urgent school alerts right now.',
        'detail' => 'Reports, admissions, attendance, and message queues are currently under control.',
        'href' => '',
        'label' => ''
    );
}

$quickLinks = array(
    array('module' => '', 'href' => 'viewstudents.php', 'icon' => 'fa-graduation-cap', 'label' => 'View Students'),
    array('module' => '', 'href' => 'viewusers.php', 'icon' => 'fa-users', 'label' => 'Teachers List'),
    array('module' => '', 'href' => 'duty-roster.php', 'icon' => 'fa-calendar-check-o', 'label' => 'Teacher On Duty'),
    array('module' => '', 'href' => 'senior-house-dashboard.php', 'icon' => 'fa-shield', 'label' => 'Senior House Overview'),
    array('module' => 'student_progression', 'href' => 'student-history.php', 'icon' => 'fa-history', 'label' => 'Student Transcript'),
    array('module' => 'student_attendance', 'href' => 'student-attendance-report.php', 'icon' => 'fa-bar-chart', 'label' => 'Attendance Summary'),
    array('module' => '', 'href' => 'terminal-report.php', 'icon' => 'fa-file-text-o', 'label' => 'Examination Report'),
    array('module' => '', 'href' => 'internal-exam-analysis.php', 'icon' => 'fa-bar-chart', 'label' => 'Internal Exams Analysis'),
    array('module' => '', 'href' => 'waec-analysis.php', 'icon' => 'fa-line-chart', 'label' => 'WAEC Analysis'),
    array('module' => 'accounts_finance', 'href' => 'payment-analysis.php', 'icon' => 'fa-line-chart', 'label' => 'Payment Report'),
    array('module' => 'online_admission', 'href' => 'online-admission-admin.php', 'icon' => 'fa-globe', 'label' => 'Online Admission'),
    array('module' => '', 'href' => 'messages.php', 'icon' => 'fa-comments', 'label' => 'Messages'),
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
                <span class="hm-kicker">Headmaster Dashboard</span>
                <h1><?php echo hm_esc($schoolName); ?></h1>
                <p>School overview for <?php echo hm_esc($headmasterShortName); ?>. Monitor attendance, results, admissions, finance, and recent school activity from one place.</p>
                <div class="hm-hero__footer">
                    <div class="hm-context">
                        <span><?php echo hm_esc($branchName); ?></span>
                        <span><?php echo hm_esc($activeBatchLabel); ?></span>
                        <span><?php echo hm_esc(date("d M Y")); ?></span>
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
                <div class="hm-desktop-search" data-hm-desktop-search>
                    <form class="hm-desktop-search-form" id="hm-desktop-search-form" autocomplete="off">
                        <label class="hm-desktop-search-label" for="hm-desktop-search-input">School Search</label>
                        <div class="hm-desktop-search-field">
                            <i class="fa fa-search"></i>
                            <input
                                class="hm-desktop-search-input"
                                type="search"
                                id="hm-desktop-search-input"
                                name="hm_desktop_search"
                                placeholder="Search students, teachers, classes, batches, or tools"
                                aria-label="Search students, teachers, classes, batches, or tools"
                            >
                            <button type="submit" class="hm-desktop-search-submit">Search</button>
                        </div>
                        <p class="hm-desktop-search-hint">Search the school records and key headmaster tools from one place.</p>
                    </form>
                    <div class="hm-desktop-search-results" id="hm-desktop-search-results" hidden></div>
                </div>
            </div>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Dashboard Summary</span>
                    <h2>School totals and student breakdown</h2>
                </div>
            </div>
            <div class="dashboard-flex" role="region" aria-label="Headmaster summary dashboard">
                <div class="chart-side">
                    <div class="chart-container">
                        <div class="chart-canvas-wrap">
                            <canvas id="headmasterStudentChart" aria-label="Student distribution by gender and residence"></canvas>
                        </div>
                        <p class="chart-note">The bar chart compares student groups by gender and residence, while the tiles beside it show the main school totals.</p>
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
                    <span class="hm-section__eyebrow">Daily School Watch</span>
                    <h2>Today at a glance</h2>
                </div>
            </div>
            <div class="hm-panel-grid hm-panel-grid--three">
                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Today</span>
                            <h2>Staff and attendance summary</h2>
                        </div>
                    </div>
                    <div class="hm-progress">
                        <div class="hm-progress__label">
                            <span>Class attendance coverage</span>
                            <strong><?php echo hm_esc(number_format($attendanceCoverageRate, 1)); ?>%</strong>
                        </div>
                        <div class="hm-progress__track"><span style="width: <?php echo hm_esc(max(0, min(100, $attendanceCoverageRate))); ?>%;"></span></div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--three hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Active Teachers</span>
                            <strong><?php echo number_format($teacherTotal); ?></strong>
                            <small>Teachers currently active in this branch.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--blue">
                            <span>Teachers On Duty</span>
                            <strong><?php echo number_format($dutyTodayTeacherCount); ?></strong>
                            <small>Teachers listed on today’s duty roster.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Awaiting Marking</span>
                            <strong><?php echo number_format($attendanceAwaiting); ?></strong>
                            <small>Class registers still waiting to be marked.</small>
                        </article>
                    </div>
                    <p class="hm-panel__note">Student attendance rate today is <?php echo hm_esc(number_format($attendanceRateToday, 1)); ?>%, with <?php echo number_format($attendanceSessionsToday); ?> class register(s) already marked.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span><?php echo number_format($attendanceMarkedToday); ?> attendance record(s) captured today.</span>
                        <a href="student-attendance-report.php">Open attendance summary</a>
                    </div>
                </section>

                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Academics</span>
                            <h2>Result release status</h2>
                        </div>
                    </div>
                    <div class="hm-progress hm-progress--gold">
                        <div class="hm-progress__label">
                            <span>Report release progress</span>
                            <strong><?php echo hm_esc(number_format($reportReleaseRate, 1)); ?>%</strong>
                        </div>
                        <div class="hm-progress__track"><span style="width: <?php echo hm_esc(max(0, min(100, $reportReleaseRate))); ?>%;"></span></div>
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
                    <p class="hm-panel__note">Report release stands at <?php echo hm_esc(number_format($reportReleaseRate, 1)); ?>%, while score entry completion is <?php echo hm_esc(number_format($scoreEntryRate, 1)); ?>%.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span><?php echo number_format($reportApprovalTotal); ?> report scope(s) are currently being tracked.</span>
                        <a href="terminal-report.php">Open examination report</a>
                    </div>
                </section>

                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Welfare</span>
                            <h2>Student welfare alerts</h2>
                        </div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--three hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Attendance Risk</span>
                            <strong><?php echo number_format($riskStudents); ?></strong>
                            <small>Students flagged by the 30-day attendance watch.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Active Counselling</span>
                            <strong><?php echo number_format((int)$counsellingSummary['active_cases']); ?></strong>
                            <small>Open counselling cases still in progress.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--blue">
                            <span>Exeat Watch</span>
                            <strong><?php echo number_format((int)$seniorHouseOverview['pending_exeat'] + (int)$seniorHouseOverview['overdue_returns']); ?></strong>
                            <small>Pending exeat plus overdue return cases.</small>
                        </article>
                    </div>
                    <p class="hm-panel__note">School counsellor: <?php echo hm_esc($schoolCounsellorName); ?>. Sessions today: <?php echo number_format((int)$counsellingSummary['sessions_today']); ?>. Urgent counselling cases: <?php echo number_format((int)$counsellingSummary['urgent_cases']); ?>.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span><?php echo number_format((int)$seniorHouseOverview['active_out']); ?> student(s) currently out on exeat.</span>
                        <a href="senior-house-dashboard.php">Open senior house overview</a>
                    </div>
                </section>
            </div>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Quick Links</span>
                    <h2>Open important school tools</h2>
                </div>
            </div>
            <div class="hm-quick-grid">
                <?php foreach($quickLinks as $quickLink){ ?>
                    <?php if(hm_can_module($con, $quickLink['module'])){ ?>
                    <a class="hm-quick-card" href="<?php echo hm_esc($quickLink['href']); ?>">
                        <span class="hm-quick-card__icon"><i class="fa <?php echo hm_esc($quickLink['icon']); ?>"></i></span>
                        <span><?php echo hm_esc($quickLink['label']); ?></span>
                    </a>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>

        <section class="hm-section">
            <section class="hm-panel">
                <div class="hm-panel__head">
                    <div>
                        <span class="hm-section__eyebrow">Senior House</span>
                        <h2>Welfare and exeat overview</h2>
                    </div>
                </div>
                <div class="hm-senior-leadership">
                    <article class="hm-senior-person">
                        <span>Senior House Master</span>
                        <strong><?php echo hm_esc($seniorMasterName); ?></strong>
                        <small>School-wide senior house lead.</small>
                    </article>
                    <article class="hm-senior-person">
                        <span>Senior House Mistress</span>
                        <strong><?php echo hm_esc($seniorMistressName); ?></strong>
                        <small>School-wide senior house lead.</small>
                    </article>
                </div>
                <div class="hm-senior-summary-grid">
                    <article class="hm-mini-card">
                        <span>Students In Houses</span>
                        <strong><?php echo number_format((int)$seniorHouseOverview['assigned_students']); ?></strong>
                        <small>Students currently placed into active houses.</small>
                    </article>
                    <article class="hm-mini-card">
                        <span>Pending Exeat</span>
                        <strong><?php echo number_format((int)$seniorHouseOverview['pending_exeat']); ?></strong>
                        <small>Requests still waiting for a decision.</small>
                    </article>
                    <article class="hm-mini-card">
                        <span>Students Out</span>
                        <strong><?php echo number_format((int)$seniorHouseOverview['active_out']); ?></strong>
                        <small>Approved exeat students not yet checked back in.</small>
                    </article>
                    <article class="hm-mini-card">
                        <span>Overdue Returns</span>
                        <strong><?php echo number_format((int)$seniorHouseOverview['overdue_returns']); ?></strong>
                        <small>Students whose expected return time has passed.</small>
                    </article>
                    <article class="hm-mini-card">
                        <span>Returned Today</span>
                        <strong><?php echo number_format((int)$seniorHouseOverview['returned_today']); ?></strong>
                        <small>Students checked back in today.</small>
                    </article>
                    <article class="hm-mini-card">
                        <span>House Supervisors</span>
                        <strong><?php echo number_format((int)$seniorHouseOverview['active_supervisors']); ?></strong>
                        <small><?php echo number_format((int)$seniorHouseOverview['active_houses']); ?> active house record(s) currently on file.</small>
                    </article>
                </div>
                <div class="hm-panel__footer hm-panel__footer--split">
                    <span>Pending external: <?php echo number_format((int)$seniorHouseOverview['external_pending']); ?> | Pending internal: <?php echo number_format((int)$seniorHouseOverview['internal_pending']); ?></span>
                    <a href="senior-house-dashboard.php">Open senior house overview</a>
                </div>
            </section>
        </section>

    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var desktopSearchWrap = document.querySelector('[data-hm-desktop-search]');
    if (desktopSearchWrap) {
        var searchForm = document.getElementById('hm-desktop-search-form');
        var searchInput = document.getElementById('hm-desktop-search-input');
        var searchResults = document.getElementById('hm-desktop-search-results');
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

            setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-spinner fa-spin'></i><div><strong>Searching</strong><span>Checking students, teachers, classes, batches, and tools.</span></div></div>");
            var requestId = ++searchRequestIndex;
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4 || requestId !== searchRequestIndex) {
                    return;
                }
                if (xhr.status === 200) {
                    setSearchResults(xhr.responseText);
                } else if (xhr.status === 403) {
                    setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-lock'></i><div><strong>Access denied</strong><span>You do not have access to dashboard search.</span></div></div>");
                } else {
                    setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-exclamation-circle'></i><div><strong>Search failed</strong><span>Try again in a moment.</span></div></div>");
                }
            };
            xhr.open('GET', 'headmaster-global-search.php?q=' + encodeURIComponent(query), true);
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

            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeSearchResults();
                    searchInput.blur();
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
        var studentCanvas = document.getElementById('headmasterStudentChart');
        if (studentCanvas) {
            var chartContext = studentCanvas.getContext('2d');
            var existingChart = typeof Chart.getChart === 'function' ? Chart.getChart(studentCanvas) : null;
            if (existingChart) {
                existingChart.destroy();
            }

            if (window.headmasterStudentChartInstance && typeof window.headmasterStudentChartInstance.destroy === 'function') {
                window.headmasterStudentChartInstance.destroy();
            }

            studentCanvas.style.width = '100%';
            studentCanvas.style.height = '100%';

            window.headmasterStudentChartInstance = new Chart(chartContext, {
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
                        backgroundColor: ['#2563eb', '#0ea5e9', '#db2777', '#f472b6', '#d59b2d'],
                        borderColor: ['#1d4ed8', '#0284c7', '#be185d', '#db2777', '#b45309'],
                        borderWidth: 1,
                        borderRadius: 8,
                        maxBarThickness: 28
                    }]
                },
                options: {
                    indexAxis: 'y',
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
                            text: 'Student Population Comparison',
                            font: { size: 15, weight: '600' },
                            color: '#111827',
                            padding: { top: 8, bottom: 16 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var label = context.label || '';
                                    var value = context.parsed && typeof context.parsed.x !== 'undefined' ? context.parsed.x : 0;
                                    var total = <?php echo $studentTotal; ?>;
                                    var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                color: '#475569',
                                font: {
                                    size: 11,
                                    weight: '600'
                                },
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.18)'
                            }
                        },
                        y: {
                            ticks: {
                                color: '#475569',
                                font: {
                                    size: 11,
                                    weight: '600'
                                },
                                padding: 8
                            },
                            grid: {
                                display: false
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

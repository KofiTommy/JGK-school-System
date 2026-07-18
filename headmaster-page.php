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
include_once("storekeeper-utils.php");
include_once("matron-utils.php");

ensure_student_attendance_tables($con);
semester_registry_ensure_academic_year_column($con);
report_approval_ensure_table($con);
ensure_online_admission_tables($con);
ensure_counselling_tables($con);
ensureSystemChangeLogTable($con);
ensure_duty_roster_tables($con);
ensure_house_tables($con);
ensure_storekeeper_tables($con);
ensure_matron_tables($con);

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

if(!function_exists('hm_status_tone')){
function hm_status_tone($status){
    $status = strtolower(trim((string)$status));
    if(in_array($status, array('pending', 'awaiting_headmaster', 'awaiting_return', 'low'), true)){
        return 'warning';
    }
    if(in_array($status, array('rejected', 'cancelled', 'overdue', 'lost', 'out_of_stock'), true)){
        return 'danger';
    }
    if(in_array($status, array('approved', 'issued', 'active', 'filled'), true)){
        return 'success';
    }
    return 'info';
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

if(!function_exists('hm_flash_html')){
function hm_flash_html($tone, $message){
    $tone = strtolower(trim((string)$tone));
    if(!in_array($tone, array('success', 'error', 'warning', 'info'), true)){
        $tone = 'info';
    }
    return "<div class='hm-inline-flash hm-inline-flash--".hm_esc($tone)."'>".hm_esc($message)."</div>";
}
}

if(!function_exists('hm_dashboard_date')){
function hm_dashboard_date($value){
    $value = trim((string)$value);
    if($value === '' || $value === '0000-00-00'){
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

if(!function_exists('hm_requisition_item_options_html')){
function hm_requisition_item_options_html($items, $selectedId, $fallbackLabel = ''){
    $selectedId = trim((string)$selectedId);
    $fallbackLabel = trim((string)$fallbackLabel);
    $html = '';
    $found = false;
    foreach((array)$items as $itemRow){
        $itemId = isset($itemRow['storeitemid']) ? trim((string)$itemRow['storeitemid']) : '';
        if($itemId === ''){
            continue;
        }
        $isSelected = $selectedId !== '' && $itemId === $selectedId;
        if($isSelected){
            $found = true;
        }
        $label = function_exists('storekeeper_item_picker_label')
            ? storekeeper_item_picker_label($itemRow)
            : (isset($itemRow['itemname']) ? trim((string)$itemRow['itemname']) : $itemId);
        $html .= "<option value='".hm_esc($itemId)."'".($isSelected ? " selected" : "").">".hm_esc($label)."</option>";
    }
    if(!$found && $selectedId !== ''){
        $label = $fallbackLabel !== '' ? $fallbackLabel : $selectedId;
        $html .= "<option value='".hm_esc($selectedId)."' selected>".hm_esc($label)."</option>";
    }
    return $html;
}
}

$currentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$currentBranchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : '';
$currentBranchIdEsc = mysqli_real_escape_string($con, $currentBranchId);
$branchUserFilter = $currentBranchId !== '' ? " AND su.branchid='$currentBranchIdEsc' " : '';
$branchAdmissionFilter = $currentBranchId !== '' ? " WHERE branchid='$currentBranchIdEsc' " : '';
$branchHelpFilter = $currentBranchId !== '' ? " WHERE branchid='$currentBranchIdEsc' " : '';

if(isset($_POST['headmaster_requisition_action']) && matron_can_final_approve_requisition($con)){
    $requisitionId = trim((string)(isset($_POST['requisitionid']) ? $_POST['requisitionid'] : ''));
    $action = trim((string)$_POST['headmaster_requisition_action']);
    $requisitionRow = $requisitionId !== '' ? matron_get_requisition_row($con, $requisitionId) : null;

    if(!$requisitionRow || (string)$requisitionRow['status'] !== 'awaiting_headmaster'){
        $_SESSION['Message'] = hm_flash_html('warning', 'That requisition is no longer waiting for final approval.');
        header("location:headmaster-page.php#hm-matron-approval");
        exit();
    }

    if($action === 'approve'){
        $approvedStoreItemId = trim((string)(isset($_POST['approvedstoreitemid']) ? $_POST['approvedstoreitemid'] : (isset($requisitionRow['effective_storeitemid']) ? $requisitionRow['effective_storeitemid'] : '')));
        $approvedQuantity = trim((string)(isset($_POST['approvedquantity']) ? $_POST['approvedquantity'] : (isset($requisitionRow['quantity']) ? $requisitionRow['quantity'] : '')));
        $approvedNeedByDate = trim((string)(isset($_POST['approvedneedbydate']) ? $_POST['approvedneedbydate'] : (isset($requisitionRow['needbydate']) ? $requisitionRow['needbydate'] : '')));
        $approvedWeekStartDate = matron_week_start_date(isset($_POST['approvedweekstartdate']) ? (string)$_POST['approvedweekstartdate'] : (isset($requisitionRow['weekstartdate']) ? (string)$requisitionRow['weekstartdate'] : date('Y-m-d')));
        $approvedDayName = matron_normalize_day_name(isset($_POST['approveddayname']) ? (string)$_POST['approveddayname'] : (isset($requisitionRow['dayname']) ? (string)$requisitionRow['dayname'] : 'Monday'), isset($requisitionRow['dayname']) ? (string)$requisitionRow['dayname'] : 'Monday');
        $approvedMealTime = matron_normalize_meal_name(isset($_POST['approvedmealtime']) ? (string)$_POST['approvedmealtime'] : (isset($requisitionRow['mealtime']) ? (string)$requisitionRow['mealtime'] : 'Breakfast'), isset($requisitionRow['mealtime']) ? (string)$requisitionRow['mealtime'] : 'Breakfast');
        $approvedPurpose = trim((string)(isset($_POST['approvedpurpose']) ? $_POST['approvedpurpose'] : (isset($requisitionRow['purpose']) ? $requisitionRow['purpose'] : '')));
        $approvedNotes = trim((string)(isset($_POST['approvednotes']) ? $_POST['approvednotes'] : (isset($requisitionRow['notes']) ? $requisitionRow['notes'] : '')));
        $headDecisionNote = trim((string)(isset($_POST['headdecisionnote']) ? $_POST['headdecisionnote'] : ''));
        $approvedItemRow = storekeeper_get_item_row($con, $approvedStoreItemId);
        $itemAllowed = $approvedItemRow && (
            matron_can_request_store_item($con, $approvedItemRow, isset($requisitionRow['requestorigin']) ? (string)$requisitionRow['requestorigin'] : 'matron') ||
            $approvedStoreItemId === (string)$requisitionRow['requested_storeitemid'] ||
            $approvedStoreItemId === (string)$requisitionRow['effective_storeitemid']
        );

        if(!$itemAllowed){
            $_SESSION['Message'] = hm_flash_html('error', 'Choose a valid store item before giving final approval.');
        } elseif($approvedQuantity === '' || !is_numeric($approvedQuantity) || (float)$approvedQuantity <= 0){
            $_SESSION['Message'] = hm_flash_html('error', 'Approved quantity must be a valid number greater than zero.');
        } elseif($approvedPurpose === ''){
            $_SESSION['Message'] = hm_flash_html('error', 'Enter the approved purpose before saving.');
        } elseif(!in_array($approvedDayName, matron_menu_day_options(), true) || !in_array($approvedMealTime, matron_meal_options(), true)){
            $_SESSION['Message'] = hm_flash_html('error', 'Choose a valid day and meal slot for the final approval.');
        } else {
            $requisitionIdEsc = mysqli_real_escape_string($con, $requisitionId);
            $headUserEsc = mysqli_real_escape_string($con, $currentUserId);
            $approvedStoreItemIdEsc = mysqli_real_escape_string($con, $approvedStoreItemId);
            $approvedNeedByDateSql = $approvedNeedByDate !== '' ? "'" . mysqli_real_escape_string($con, $approvedNeedByDate) . "'" : "NULL";
            $approvedWeekStartEsc = mysqli_real_escape_string($con, $approvedWeekStartDate);
            $approvedDayEsc = mysqli_real_escape_string($con, $approvedDayName);
            $approvedMealEsc = mysqli_real_escape_string($con, $approvedMealTime);
            $approvedQuantitySql = number_format((float)$approvedQuantity, 2, '.', '');
            $approvedPurposeEsc = mysqli_real_escape_string($con, $approvedPurpose);
            $approvedNotesEsc = mysqli_real_escape_string($con, $approvedNotes);
            $headDecisionNote = $headDecisionNote !== '' ? $headDecisionNote : 'Approved by the headmaster.';
            $headDecisionNoteEsc = mysqli_real_escape_string($con, $headDecisionNote);
            @mysqli_query($con, "UPDATE tblmatronrequisition
                SET status='approved',
                    headdecisionstatus='approved',
                    headdecisionnote='$headDecisionNoteEsc',
                    headdecisionby='$headUserEsc',
                    headdecisiondatetime=NOW(),
                    approvedstoreitemid='$approvedStoreItemIdEsc',
                    approvedneedbydate=$approvedNeedByDateSql,
                    approvedweekstartdate='$approvedWeekStartEsc',
                    approveddayname='$approvedDayEsc',
                    approvedmealtime='$approvedMealEsc',
                    approvedquantity='$approvedQuantitySql',
                    approvedpurpose='$approvedPurposeEsc',
                    approvednotes='$approvedNotesEsc',
                    decisionnote='$headDecisionNoteEsc',
                    decisionby='$headUserEsc',
                    decisiondatetime=NOW()
                WHERE requisitionid='$requisitionIdEsc'
                  AND status='awaiting_headmaster'
                LIMIT 1");
            $_SESSION['Message'] = mysqli_affected_rows($con) > 0
                ? hm_flash_html('success', 'Final approval saved successfully.')
                : hm_flash_html('warning', 'That requisition could not be updated. Please refresh and try again.');
        }
    } elseif($action === 'reject'){
        $headDecisionNote = trim((string)(isset($_POST['headdecisionnote']) ? $_POST['headdecisionnote'] : ''));
        $headDecisionNote = $headDecisionNote !== '' ? $headDecisionNote : 'Rejected by the headmaster.';
        $requisitionIdEsc = mysqli_real_escape_string($con, $requisitionId);
        $headUserEsc = mysqli_real_escape_string($con, $currentUserId);
        $headDecisionNoteEsc = mysqli_real_escape_string($con, $headDecisionNote);
        @mysqli_query($con, "UPDATE tblmatronrequisition
            SET status='rejected',
                headdecisionstatus='rejected',
                headdecisionnote='$headDecisionNoteEsc',
                headdecisionby='$headUserEsc',
                headdecisiondatetime=NOW(),
                decisionnote='$headDecisionNoteEsc',
                decisionby='$headUserEsc',
                decisiondatetime=NOW()
            WHERE requisitionid='$requisitionIdEsc'
              AND status='awaiting_headmaster'
            LIMIT 1");
        $_SESSION['Message'] = mysqli_affected_rows($con) > 0
            ? hm_flash_html('warning', 'The requisition was rejected.')
            : hm_flash_html('warning', 'That requisition could not be updated. Please refresh and try again.');
    } else {
        $_SESSION['Message'] = hm_flash_html('error', 'That headmaster action was not recognised.');
    }

    header("location:headmaster-page.php#hm-matron-approval");
    exit();
}

$headmasterMessage = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : '';
unset($_SESSION['Message']);

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

$_HeadmasterStudentBatchSummary = dashboard_student_population_summary($con, array(
    'branchid' => $currentBranchId,
    'require_active_class' => true
));

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

$storekeeperSummary = storekeeper_dashboard_summary($con);
$matronSummary = matron_dashboard_summary($con);
$matronCurrentWeekMenu = matron_current_week_menu_context($con, $todayDate);
$matronRecentRequisitions = matron_recent_requisitions($con, 6);
$matronHeadApprovalQueue = matron_fetch_requisition_rows($con, array(
    'status' => 'awaiting_headmaster',
    'limit' => 12
));
$headmasterRequisitionCatalog = matron_request_catalog_context($con, 'teacher', 500);
$headmasterRequisitionItems = isset($headmasterRequisitionCatalog['rows']) && is_array($headmasterRequisitionCatalog['rows'])
    ? $headmasterRequisitionCatalog['rows']
    : array();
$headmasterRequisitionNoticeCount = count($matronHeadApprovalQueue);
$headmasterHistoryStatus = isset($_GET['requisition_history_status']) ? trim((string)$_GET['requisition_history_status']) : '';
$headmasterHistoryOrigin = isset($_GET['requisition_history_origin']) ? trim((string)$_GET['requisition_history_origin']) : '';
$headmasterHistorySearch = isset($_GET['requisition_history_search']) ? trim((string)$_GET['requisition_history_search']) : '';
$headmasterHistoryPanelOpen = isset($_GET['show_requisition_history']) && trim((string)$_GET['show_requisition_history']) === '1';
$headmasterHistoryFilterValues = array(
    'status' => $headmasterHistoryStatus,
    'origin' => $headmasterHistoryOrigin,
    'search' => $headmasterHistorySearch
);
if(!$headmasterHistoryPanelOpen){
    foreach($headmasterHistoryFilterValues as $headmasterHistoryFilterValue){
        if(trim((string)$headmasterHistoryFilterValue) !== ''){
            $headmasterHistoryPanelOpen = true;
            break;
        }
    }
}
$headmasterHistoryPrintParams = array('autoprint' => '1');
if ($headmasterHistoryStatus !== '' && in_array($headmasterHistoryStatus, array('approved', 'issued', 'rejected', 'cancelled'), true)) {
    $headmasterHistoryPrintParams['status'] = $headmasterHistoryStatus;
}
if ($headmasterHistoryOrigin !== '' && in_array($headmasterHistoryOrigin, array_keys(matron_requisition_origin_options()), true)) {
    $headmasterHistoryPrintParams['origin'] = $headmasterHistoryOrigin;
}
if ($headmasterHistorySearch !== '') {
    $headmasterHistoryPrintParams['search'] = $headmasterHistorySearch;
}
$headmasterHistoryPrintQuery = http_build_query($headmasterHistoryPrintParams);
$headmasterHistoryFilters = array('limit' => 220);
if (in_array($headmasterHistoryStatus, array('approved', 'issued', 'rejected', 'cancelled'), true)) {
    $headmasterHistoryFilters['status'] = $headmasterHistoryStatus;
}
if ($headmasterHistoryOrigin !== '' && in_array($headmasterHistoryOrigin, array_keys(matron_requisition_origin_options()), true)) {
    $headmasterHistoryFilters['requestorigin'] = $headmasterHistoryOrigin;
}
if ($headmasterHistorySearch !== '') {
    $headmasterHistoryFilters['search'] = $headmasterHistorySearch;
}
$headmasterRequisitionHistoryRows = array();
foreach (matron_fetch_requisition_rows($con, $headmasterHistoryFilters) as $historyRow) {
    if (in_array((string)$historyRow['status'], array('approved', 'issued', 'rejected', 'cancelled'), true)) {
        $headmasterRequisitionHistoryRows[] = $historyRow;
    }
}
$headmasterLatestRequisition = !empty($matronRecentRequisitions) ? $matronRecentRequisitions[0] : null;
$headmasterHistorySummaryText = number_format(count($headmasterRequisitionHistoryRows)).' record(s) ready';
if($headmasterHistoryStatus !== '' || $headmasterHistoryOrigin !== '' || $headmasterHistorySearch !== ''){
    $headmasterHistorySummaryText .= ' for this filtered view';
}
$storeWatchRows = array();
foreach(storekeeper_fetch_balance_rows($con) as $storeRow){
    $storeBalance = isset($storeRow['current_balance']) ? (float)$storeRow['current_balance'] : 0;
    $storeReorder = isset($storeRow['reorderlevel']) ? (float)$storeRow['reorderlevel'] : 0;
    if($storeBalance <= 0 || ($storeReorder > 0 && $storeBalance <= $storeReorder)){
        $storeRow['_watch_status'] = $storeBalance <= 0 ? 'out_of_stock' : 'low';
        $storeWatchRows[] = $storeRow;
    }
}
usort($storeWatchRows, function($left, $right){
    $leftSeverity = (isset($left['_watch_status']) && $left['_watch_status'] === 'out_of_stock') ? 0 : 1;
    $rightSeverity = (isset($right['_watch_status']) && $right['_watch_status'] === 'out_of_stock') ? 0 : 1;
    if($leftSeverity !== $rightSeverity){
        return $leftSeverity - $rightSeverity;
    }
    $leftBalance = isset($left['current_balance']) ? (float)$left['current_balance'] : 0;
    $rightBalance = isset($right['current_balance']) ? (float)$right['current_balance'] : 0;
    if($leftBalance === $rightBalance){
        return strcmp((string)(isset($left['itemname']) ? $left['itemname'] : ''), (string)(isset($right['itemname']) ? $right['itemname'] : ''));
    }
    return ($leftBalance < $rightBalance) ? -1 : 1;
});
$storeWatchRows = array_slice($storeWatchRows, 0, 4);

$todayMenuSlots = array();
$todayDayName = date('l');
foreach(matron_meal_options() as $mealName){
    $slotRow = isset($matronCurrentWeekMenu['grouped'][$todayDayName][$mealName]) ? $matronCurrentWeekMenu['grouped'][$todayDayName][$mealName] : null;
    $todayMenuSlots[$mealName] = $slotRow ? matron_menu_display_text($slotRow) : 'Not set';
}

$storeDashboardHref = hm_can_module($con, 'stores_management') ? 'storekeeper-dashboard.php' : '';
$matronDashboardHref = hm_can_module($con, 'matron_management') ? 'matron-dashboard.php' : '';

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
        'title' => 'There are unread messages.',
        'detail' => number_format($unreadMessages).' message'.($unreadMessages === 1 ? '' : 's').' need your attention.',
        'href' => 'messages.php',
        'label' => 'View messages'
    );
}
if($riskStudents > 0){
    $attentionItems[] = array(
        'title' => 'Attendance needs follow-up.',
        'detail' => number_format($riskStudents).' student'.($riskStudents === 1 ? ' has' : 's have').' low attendance in the last 30 days.',
        'href' => 'student-attendance-report.php',
        'label' => 'View attendance'
    );
}
if($dutyTodayCount > 0){
    $attentionItems[] = array(
        'title' => 'The duty roster is active today.',
        'detail' => number_format($dutyTodayCount).' duty assignment'.($dutyTodayCount === 1 ? ' is' : 's are').' active today. '.$dutyTodaySummary,
        'href' => 'duty-roster.php',
        'label' => 'View duty roster'
    );
}
if((int)$seniorHouseOverview['pending_exeat'] > 0 || (int)$seniorHouseOverview['overdue_returns'] > 0){
    $attentionItems[] = array(
        'title' => 'There are exeat cases to review.',
        'detail' => number_format((int)$seniorHouseOverview['pending_exeat']).' pending exeat request(s) and '.number_format((int)$seniorHouseOverview['overdue_returns']).' overdue return(s) are currently on record.',
        'href' => 'senior-house-dashboard.php',
        'label' => 'View exeat overview'
    );
}
if((int)$storekeeperSummary['low_stock_items'] > 0 || (int)$storekeeperSummary['out_of_stock_items'] > 0){
    $attentionItems[] = array(
        'title' => 'Store balances need checking.',
        'detail' => number_format((int)$storekeeperSummary['low_stock_items']).' item(s) are running low and '.number_format((int)$storekeeperSummary['out_of_stock_items']).' item(s) are finished.',
        'href' => $storeDashboardHref,
        'label' => $storeDashboardHref !== '' ? 'Open store page' : ''
    );
}
if((int)$storekeeperSummary['student_items_overdue'] > 0){
    $attentionItems[] = array(
        'title' => 'Some student-issued items are overdue.',
        'detail' => number_format((int)$storekeeperSummary['student_items_overdue']).' book(s) or store item(s) are past the expected return date.',
        'href' => $storeDashboardHref !== '' ? 'store-student-issue.php' : '',
        'label' => $storeDashboardHref !== '' ? 'View item register' : ''
    );
}
if((int)$matronSummary['requisition_pending'] > 0 || (int)$matronSummary['requisition_waiting_headmaster'] > 0 || (int)$matronSummary['food_low_stock'] > 0){
    $attentionItems[] = array(
        'title' => 'Store requests need follow-up.',
        'detail' => number_format((int)$matronSummary['requisition_pending']).' requisition(s) are still at the store, '.number_format((int)$matronSummary['requisition_waiting_headmaster']).' are waiting for final approval, and '.number_format((int)$matronSummary['food_low_stock']).' food or kitchen item(s) are running low.',
        'href' => 'headmaster-page.php#hm-matron-approval',
        'label' => 'Open approval queue'
    );
}
if((int)$matronSummary['menu_slot_open'] > 0){
    $attentionItems[] = array(
        'title' => 'This week menu still has gaps.',
        'detail' => number_format((int)$matronSummary['menu_slot_open']).' meal slot(s) are still empty on the weekly menu.',
        'href' => $matronDashboardHref,
        'label' => $matronDashboardHref !== '' ? 'Check the menu' : ''
    );
}
if(empty($attentionItems)){
    $attentionItems[] = array(
        'title' => 'Nothing urgent right now.',
        'detail' => 'The main school alerts look settled for now.',
        'href' => '',
        'label' => ''
    );
}

$quickLinks = array(
    array('module' => '', 'href' => 'viewstudents.php', 'icon' => 'fa-graduation-cap', 'label' => 'View Students'),
    array('module' => '', 'href' => 'viewusers.php', 'icon' => 'fa-users', 'label' => 'Teachers List'),
    array('module' => '', 'href' => 'duty-roster.php', 'icon' => 'fa-calendar-check-o', 'label' => 'Teacher On Duty'),
    array('module' => '', 'href' => 'senior-house-dashboard.php', 'icon' => 'fa-shield', 'label' => 'Senior House Overview'),
    array('module' => 'stores_management', 'href' => 'storekeeper-dashboard.php', 'icon' => 'fa-archive', 'label' => 'Storekeeper Dashboard'),
    array('module' => 'matron_management', 'href' => 'matron-dashboard.php', 'icon' => 'fa-cutlery', 'label' => 'Matron Dashboard'),
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
                    <div class="hm-hero__utility">
                        <a class="hm-notice-bell<?php echo $headmasterRequisitionNoticeCount > 0 ? ' hm-notice-bell--active' : ''; ?>" href="#hm-matron-approval">
                            <span class="hm-notice-bell__icon"><i class="fa fa-bell"></i></span>
                            <span class="hm-notice-bell__body">
                                <strong>Approval queue</strong>
                                <small><?php echo $headmasterRequisitionNoticeCount > 0 ? number_format((int)$headmasterRequisitionNoticeCount) . ' request(s) waiting' : 'No request waiting'; ?></small>
                            </span>
                            <span class="hm-notice-bell__count"><?php echo number_format((int)$headmasterRequisitionNoticeCount); ?></span>
                        </a>
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

        <?php if($headmasterMessage !== ''){ ?>
        <?php echo $headmasterMessage; ?>
        <?php } ?>

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
                        <?php echo dashboard_student_batch_breakdown_html($_HeadmasterStudentBatchSummary, 'day_boys', 'Batches', 'No batch yet.'); ?>
                    </div>
                    <div class="card" role="article" aria-label="Boys Boarding Students">
                        <h4><i class="fa fa-male" style="color:#38bdf8; margin-right:4px;"></i>Boys - Boarding</h4>
                        <p><?php echo number_format($boys_boarding); ?></p>
                        <?php echo dashboard_student_batch_breakdown_html($_HeadmasterStudentBatchSummary, 'boarding_boys', 'Batches', 'No batch yet.'); ?>
                    </div>
                    <div class="card" role="article" aria-label="Girls Day Students">
                        <h4><i class="fa fa-female" style="color:#db2777; margin-right:4px;"></i>Girls - Day</h4>
                        <p><?php echo number_format($girls_day); ?></p>
                        <?php echo dashboard_student_batch_breakdown_html($_HeadmasterStudentBatchSummary, 'day_girls', 'Batches', 'No batch yet.'); ?>
                    </div>
                    <div class="card" role="article" aria-label="Girls Boarding Students">
                        <h4><i class="fa fa-female" style="color:#f472b6; margin-right:4px;"></i>Girls - Boarding</h4>
                        <p><?php echo number_format($girls_boarding); ?></p>
                        <?php echo dashboard_student_batch_breakdown_html($_HeadmasterStudentBatchSummary, 'boarding_girls', 'Batches', 'No batch yet.'); ?>
                    </div>
                    <div class="card" role="article" aria-label="Students With No Residence Status">
                        <h4><i class="fa fa-question-circle" style="color:#b45309; margin-right:4px;"></i>No Residence Status</h4>
                        <p><?php echo number_format($studentsNoStatus); ?></p>
                        <?php echo dashboard_student_batch_breakdown_html($_HeadmasterStudentBatchSummary, 'students_no_status', 'Batches', 'All set.'); ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="hm-section">
            <details class="hm-section-disclosure">
                <summary class="hm-section-disclosure__summary">
                    <div>
                        <span class="hm-section__eyebrow">Daily School Watch</span>
                        <strong>Today at a glance</strong>
                        <small>Attendance coverage: <?php echo hm_esc(number_format($attendanceCoverageRate, 1)); ?>% | Reports waiting: <?php echo number_format($reportPendingTotal); ?> | Welfare watch: <?php echo number_format($riskStudents); ?></small>
                    </div>
                </summary>
                <div class="hm-section-disclosure__body">
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
                </div>
            </details>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Today</span>
                    <h2>What needs your attention</h2>
                </div>
            </div>
            <section class="hm-panel">
                <div class="hm-alert-list">
                    <?php foreach($attentionItems as $attentionItem){ ?>
                    <article class="hm-alert-item">
                        <div>
                            <strong><?php echo hm_esc($attentionItem['title']); ?></strong>
                            <p><?php echo hm_esc($attentionItem['detail']); ?></p>
                        </div>
                        <?php if(isset($attentionItem['href'], $attentionItem['label']) && trim((string)$attentionItem['href']) !== '' && trim((string)$attentionItem['label']) !== ''){ ?>
                        <a class="hm-alert-item__link" href="<?php echo hm_esc($attentionItem['href']); ?>"><?php echo hm_esc($attentionItem['label']); ?></a>
                        <?php } ?>
                    </article>
                    <?php } ?>
                </div>
            </section>
        </section>

        <section class="hm-section">
            <details class="hm-section-disclosure">
                <summary class="hm-section-disclosure__summary">
                    <div>
                        <span class="hm-section__eyebrow">Stores And Feeding</span>
                        <strong>Store and kitchen at a glance</strong>
                        <small>Low stock: <?php echo number_format((int)$storekeeperSummary['low_stock_items']); ?> | Waiting for head: <?php echo number_format((int)$matronSummary['requisition_waiting_headmaster']); ?> | Menu slots open: <?php echo number_format((int)$matronSummary['menu_slot_open']); ?></small>
                    </div>
                </summary>
                <div class="hm-section-disclosure__body">
            <div class="hm-panel-grid hm-panel-grid--three">
                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Storekeeper</span>
                            <h2>Store position</h2>
                        </div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--gold">
                            <span>Low Stock</span>
                            <strong><?php echo number_format((int)$storekeeperSummary['low_stock_items']); ?></strong>
                            <small>Items getting low.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Out Of Stock</span>
                            <strong><?php echo number_format((int)$storekeeperSummary['out_of_stock_items']); ?></strong>
                            <small>Items already finished.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--blue">
                            <span>Student Items Out</span>
                            <strong><?php echo number_format((int)$storekeeperSummary['student_items_out']); ?></strong>
                            <small>Items currently with students.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Overdue Returns</span>
                            <strong><?php echo number_format((int)$storekeeperSummary['student_items_overdue']); ?></strong>
                            <small>Items due back from students.</small>
                        </article>
                    </div>
                    <?php if(empty($storeWatchRows)){ ?>
                    <div class="hm-empty-state" style="margin-top:14px;">
                        <h3>No store issue right now.</h3>
                        <p>No item is below the reorder level at the moment.</p>
                    </div>
                    <?php } else { ?>
                    <div class="hm-activity-list" style="margin-top:14px;">
                        <?php foreach($storeWatchRows as $_StoreRow){ ?>
                        <article class="hm-activity-item">
                            <div>
                                <strong><?php echo hm_esc($_StoreRow['itemname']); ?></strong>
                                <p>
                                    Balance: <?php echo hm_esc(storekeeper_format_quantity($_StoreRow['current_balance'])); ?> <?php echo hm_esc($_StoreRow['unitname']); ?>
                                    <?php if((float)$_StoreRow['reorderlevel'] > 0){ ?>
                                     | Reorder: <?php echo hm_esc(storekeeper_format_quantity($_StoreRow['reorderlevel'])); ?>
                                    <?php } ?>
                                </p>
                            </div>
                            <span class="hm-status-pill hm-status-pill--<?php echo hm_esc(hm_status_tone($_StoreRow['_watch_status'])); ?>">
                                <?php echo (isset($_StoreRow['_watch_status']) && $_StoreRow['_watch_status'] === 'out_of_stock') ? 'Out of Stock' : 'Low Stock'; ?>
                            </span>
                        </article>
                        <?php } ?>
                    </div>
                    <?php } ?>
                    <p class="hm-panel__note">This is the current picture from the school store.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span>Receipts this week: <?php echo number_format((int)$storekeeperSummary['receipt_count_week']); ?> | Issues this week: <?php echo number_format((int)$storekeeperSummary['issue_count_week']); ?> | Student issues this week: <?php echo number_format((int)$storekeeperSummary['student_issue_count_week']); ?></span>
                        <?php if($storeDashboardHref !== ''){ ?>
                        <a href="<?php echo hm_esc($storeDashboardHref); ?>">Open storekeeper dashboard</a>
                        <?php } ?>
                    </div>
                </section>

                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Store Requests</span>
                            <h2>Request summary</h2>
                        </div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--gold">
                            <span>At Store</span>
                            <strong><?php echo number_format((int)$matronSummary['requisition_pending']); ?></strong>
                            <small>Requests still waiting at the store.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--blue">
                            <span>Waiting for Head</span>
                            <strong><?php echo number_format((int)$matronSummary['requisition_waiting_headmaster']); ?></strong>
                            <small>Requests now waiting for your final approval.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Final Approved</span>
                            <strong><?php echo number_format((int)$matronSummary['requisition_approved']); ?></strong>
                            <small>Fully approved requests not yet supplied.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--rose">
                            <span>Food Low Stock</span>
                            <strong><?php echo number_format((int)$matronSummary['food_low_stock']); ?></strong>
                            <small>Food or kitchen items getting low.</small>
                        </article>
                    </div>
                    <?php if($headmasterLatestRequisition){ ?>
                    <div class="hm-compact-callout">
                        <span class="hm-compact-callout__eyebrow">Latest Request</span>
                        <strong><?php echo hm_esc($headmasterLatestRequisition['requested_by_name']); ?> asked for <?php echo hm_esc($headmasterLatestRequisition['itemname']); ?></strong>
                        <p><?php echo hm_esc(storekeeper_format_quantity($headmasterLatestRequisition['quantity'])); ?> <?php echo hm_esc($headmasterLatestRequisition['unitname']); ?> for <?php echo hm_esc(matron_requisition_slot_label($headmasterLatestRequisition['dayname'], $headmasterLatestRequisition['mealtime'])); ?>.</p>
                        <div class="hm-compact-callout__meta">
                            <span><?php echo hm_esc($headmasterLatestRequisition['requestorigin_label']); ?> request</span>
                            <span><?php echo hm_esc(matron_requisition_status_label($headmasterLatestRequisition['status'])); ?></span>
                        </div>
                    </div>
                    <?php } else { ?>
                    <div class="hm-empty-state hm-empty-state--compact" style="margin-top:14px;">
                        <h3>No request has come in yet.</h3>
                        <p>When staff or the matron send one, the latest request will show here.</p>
                    </div>
                    <?php } ?>
                    <p class="hm-panel__note">Boarding student items overdue: <?php echo number_format((int)$matronSummary['boarding_student_items_overdue']); ?>. Boarders currently out on exeat: <?php echo number_format((int)$matronSummary['active_out']); ?>. Boarders without house: <?php echo number_format((int)$matronSummary['boarders_without_house']); ?>.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span>Issued requisitions: <?php echo number_format((int)$matronSummary['requisition_issued']); ?> | Rejected: <?php echo number_format((int)$matronSummary['requisition_rejected']); ?> | Cancelled: <?php echo number_format((int)$matronSummary['requisition_cancelled']); ?></span>
                        <span class="hm-panel__links">
                            <a href="#hm-matron-approval">Open approval queue</a>
                            <?php if($matronDashboardHref !== ''){ ?>
                            <a href="<?php echo hm_esc($matronDashboardHref); ?>">Open matron dashboard</a>
                            <?php } ?>
                        </span>
                    </div>
                </section>

                <section class="hm-panel">
                    <div class="hm-panel__head">
                        <div>
                            <span class="hm-section__eyebrow">Current Menu</span>
                            <h2>Menu for today</h2>
                        </div>
                    </div>
                    <div class="hm-mini-grid hm-mini-grid--tight">
                        <article class="hm-mini-card hm-mini-card--teal">
                            <span>Filled Slots</span>
                            <strong><?php echo number_format((int)$matronSummary['menu_slot_filled']); ?></strong>
                            <small>Meals already entered for the week.</small>
                        </article>
                        <article class="hm-mini-card hm-mini-card--gold">
                            <span>Open Slots</span>
                            <strong><?php echo number_format((int)$matronSummary['menu_slot_open']); ?></strong>
                            <small>Meals still to be added this week.</small>
                        </article>
                    </div>
                    <div class="hm-data-list" style="margin-top:14px;">
                        <?php foreach($todayMenuSlots as $mealName => $mealText){ ?>
                        <div>
                            <span><?php echo hm_esc($mealName); ?></span>
                            <strong><?php echo hm_esc($mealText); ?></strong>
                        </div>
                        <?php } ?>
                    </div>
                    <p class="hm-panel__note"><?php echo hm_esc($matronCurrentWeekMenu['week_label']); ?> is the menu now showing on the student and teacher dashboards.</p>
                    <div class="hm-panel__footer hm-panel__footer--split">
                        <span>Today: <?php echo hm_esc($todayDayName); ?> | Food items tracked by matron: <?php echo number_format((int)$matronSummary['food_items_total']); ?></span>
                        <?php if($matronDashboardHref !== ''){ ?>
                        <a href="<?php echo hm_esc($matronDashboardHref); ?>">Open menu page</a>
                        <?php } ?>
                    </div>
                </section>
            </div>
                </div>
            </details>
        </section>

        <section class="hm-section" id="hm-matron-approval">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Final Approval</span>
                    <h2>Requests waiting for your approval</h2>
                </div>
            </div>
            <section class="hm-panel">
                <div class="hm-approval-overview">
                    <article class="hm-approval-overview__item">
                        <span>Waiting Now</span>
                        <strong><?php echo number_format((int)$headmasterRequisitionNoticeCount); ?></strong>
                    </article>
                    <article class="hm-approval-overview__item">
                        <span>Sent To Store</span>
                        <strong><?php echo number_format((int)$matronSummary['requisition_pending']); ?></strong>
                    </article>
                    <article class="hm-approval-overview__item">
                        <span>Approved Not Issued</span>
                        <strong><?php echo number_format((int)$matronSummary['requisition_approved']); ?></strong>
                    </article>
                </div>
                <?php if(empty($matronHeadApprovalQueue)){ ?>
                <div class="hm-empty-state">
                    <h3>No requisition is waiting for final approval.</h3>
                    <p>The storekeeper has not sent any staff or kitchen request to the headmaster queue yet.</p>
                </div>
                <?php } else { ?>
                <div class="hm-approval-list">
                    <?php foreach($matronHeadApprovalQueue as $_ApprovalReq){ ?>
                    <details class="hm-approval-card">
                        <summary class="hm-approval-summary">
                            <div class="hm-approval-summary__main">
                                <span class="hm-section__eyebrow"><?php echo hm_esc($_ApprovalReq['requestorigin_label']); ?> Request <?php echo hm_esc($_ApprovalReq['requisitionid']); ?></span>
                                <strong><?php echo hm_esc($_ApprovalReq['requested_by_name']); ?> needs <?php echo hm_esc($_ApprovalReq['itemname']); ?></strong>
                                <small><?php echo hm_esc(storekeeper_format_quantity($_ApprovalReq['quantity'])); ?> <?php echo hm_esc($_ApprovalReq['unitname']); ?> | Need by <?php echo hm_esc(hm_dashboard_date($_ApprovalReq['needbydate'])); ?> | <?php echo hm_esc(matron_requisition_slot_label($_ApprovalReq['dayname'], $_ApprovalReq['mealtime'])); ?></small>
                            </div>
                            <div class="hm-approval-summary__meta">
                                <span><?php echo trim((string)$_ApprovalReq['store_decision_by_name']) !== '' ? hm_esc($_ApprovalReq['store_decision_by_name']) : 'Storekeeper'; ?></span>
                                <span><?php echo hm_esc(hm_dashboard_date($_ApprovalReq['requestdate'])); ?></span>
                            </div>
                            <span class="hm-status-pill hm-status-pill--<?php echo hm_esc(hm_status_tone($_ApprovalReq['status'])); ?>"><?php echo hm_esc($_ApprovalReq['status_label']); ?></span>
                        </summary>

                        <div class="hm-approval-card__body">
                            <div class="hm-approval-card__summary">
                                <div>
                                    <span>Request Date</span>
                                    <strong><?php echo hm_esc(hm_dashboard_date($_ApprovalReq['requestdate'])); ?></strong>
                                </div>
                                <div>
                                    <span>Need By</span>
                                    <strong><?php echo hm_esc(hm_dashboard_date($_ApprovalReq['needbydate'])); ?></strong>
                                </div>
                                <div>
                                    <span>Meal Slot</span>
                                    <strong><?php echo hm_esc(matron_requisition_slot_label($_ApprovalReq['dayname'], $_ApprovalReq['mealtime'])); ?></strong>
                                </div>
                                <div>
                                    <span>Store Check</span>
                                    <strong><?php echo trim((string)$_ApprovalReq['store_decision_by_name']) !== '' ? hm_esc($_ApprovalReq['store_decision_by_name']) : 'Storekeeper'; ?></strong>
                                </div>
                            </div>

                            <div class="hm-data-list">
                                <div>
                                    <span>Purpose</span>
                                    <strong><?php echo hm_esc($_ApprovalReq['purpose']); ?></strong>
                                </div>
                                <div>
                                    <span>Store note</span>
                                    <strong><?php echo hm_esc(trim((string)$_ApprovalReq['stage_note']) !== '' ? $_ApprovalReq['stage_note'] : 'Waiting for your final decision.'); ?></strong>
                                </div>
                            </div>

                            <form method="post" action="headmaster-page.php#hm-matron-approval" class="hm-approval-form">
                                <input type="hidden" name="requisitionid" value="<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">
                                <div class="hm-approval-form__grid">
                                    <div class="hm-field">
                                        <label for="hm_item_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Final Item</label>
                                        <select id="hm_item_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" name="approvedstoreitemid">
                                            <?php echo hm_requisition_item_options_html($headmasterRequisitionItems, (string)$_ApprovalReq['effective_storeitemid'], (string)$_ApprovalReq['itemname']); ?>
                                        </select>
                                    </div>
                                    <div class="hm-field">
                                        <label for="hm_qty_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Final Quantity</label>
                                        <input id="hm_qty_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" type="number" step="0.01" min="0.01" name="approvedquantity" value="<?php echo hm_esc((string)$_ApprovalReq['quantity']); ?>">
                                    </div>
                                    <div class="hm-field">
                                        <label for="hm_need_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Need By</label>
                                        <input id="hm_need_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" type="date" name="approvedneedbydate" value="<?php echo hm_esc((string)$_ApprovalReq['needbydate']); ?>">
                                    </div>
                                    <div class="hm-field">
                                        <label for="hm_week_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Menu Week</label>
                                        <input id="hm_week_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" type="date" name="approvedweekstartdate" value="<?php echo hm_esc((string)$_ApprovalReq['weekstartdate']); ?>">
                                    </div>
                                    <div class="hm-field">
                                        <label for="hm_day_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Day</label>
                                        <select id="hm_day_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" name="approveddayname">
                                            <?php foreach(matron_menu_day_options() as $_DayOption){ ?>
                                            <option value="<?php echo hm_esc($_DayOption); ?>"<?php echo (string)$_ApprovalReq['dayname'] === (string)$_DayOption ? ' selected' : ''; ?>><?php echo hm_esc($_DayOption); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="hm-field">
                                        <label for="hm_meal_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Meal Time</label>
                                        <select id="hm_meal_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" name="approvedmealtime">
                                            <?php foreach(matron_meal_options() as $_MealOption){ ?>
                                            <option value="<?php echo hm_esc($_MealOption); ?>"<?php echo (string)$_ApprovalReq['mealtime'] === (string)$_MealOption ? ' selected' : ''; ?>><?php echo hm_esc($_MealOption); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="hm-field hm-field--wide">
                                        <label for="hm_purpose_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Final Purpose</label>
                                        <input id="hm_purpose_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" type="text" name="approvedpurpose" value="<?php echo hm_esc((string)$_ApprovalReq['purpose']); ?>">
                                    </div>
                                    <div class="hm-field hm-field--wide">
                                        <label for="hm_notes_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Final Notes</label>
                                        <textarea id="hm_notes_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" name="approvednotes"><?php echo hm_esc((string)$_ApprovalReq['notes']); ?></textarea>
                                    </div>
                                    <div class="hm-field hm-field--wide">
                                        <label for="hm_decision_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>">Headmaster Comment</label>
                                        <textarea id="hm_decision_<?php echo hm_esc($_ApprovalReq['requisitionid']); ?>" name="headdecisionnote" placeholder="Optional note about what you changed or why you approved it."></textarea>
                                    </div>
                                </div>
                                <div class="hm-approval-form__actions">
                                    <button type="submit" name="headmaster_requisition_action" value="approve" class="hm-action-button hm-action-button--success">Approve Final</button>
                                    <button type="submit" name="headmaster_requisition_action" value="reject" class="hm-action-button hm-action-button--danger" onclick="return confirm('Reject this requisition?');">Reject</button>
                                </div>
                            </form>
                        </div>
                    </details>
                    <?php } ?>
                </div>
                <?php } ?>
            </section>
        </section>

        <section class="hm-section" id="hm-requisition-history">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">History</span>
                    <h2>Past requests</h2>
                </div>
            </div>
            <section class="hm-panel">
                <details class="hm-history-disclosure"<?php echo $headmasterHistoryPanelOpen ? ' open' : ''; ?>>
                    <summary class="hm-history-disclosure__summary">
                        <div>
                            <span class="hm-section__eyebrow">Open History</span>
                            <strong>View old requests and print past approvals</strong>
                            <small><?php echo hm_esc($headmasterHistorySummaryText); ?></small>
                        </div>
                    </summary>
                    <div class="hm-history-disclosure__body">
                        <form method="get" action="headmaster-page.php#hm-requisition-history" class="hm-filter-toolbar">
                            <input type="hidden" name="show_requisition_history" value="1">
                            <div class="hm-field">
                                <label for="requisition_history_status">Status</label>
                                <select id="requisition_history_status" name="requisition_history_status">
                                    <option value="">All Past Requests</option>
                                    <option value="approved"<?php echo $headmasterHistoryStatus === 'approved' ? ' selected' : ''; ?>>Approved</option>
                                    <option value="issued"<?php echo $headmasterHistoryStatus === 'issued' ? ' selected' : ''; ?>>Issued</option>
                                    <option value="rejected"<?php echo $headmasterHistoryStatus === 'rejected' ? ' selected' : ''; ?>>Rejected</option>
                                    <option value="cancelled"<?php echo $headmasterHistoryStatus === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="hm-field">
                                <label for="requisition_history_origin">Request Type</label>
                                <select id="requisition_history_origin" name="requisition_history_origin">
                                    <option value="">All Types</option>
                                    <?php foreach(matron_requisition_origin_options() as $_OriginKey => $_OriginLabel){ ?>
                                    <option value="<?php echo hm_esc($_OriginKey); ?>"<?php echo $headmasterHistoryOrigin === $_OriginKey ? ' selected' : ''; ?>><?php echo hm_esc($_OriginLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="hm-field hm-field--wide">
                                <label for="requisition_history_search">Search</label>
                                <input id="requisition_history_search" type="text" name="requisition_history_search" value="<?php echo hm_esc($headmasterHistorySearch); ?>" placeholder="Search requester, item, purpose, slot, or requisition id">
                            </div>
                            <div class="hm-filter-toolbar__actions">
                                <button type="submit" class="hm-action-button hm-action-button--success">View History</button>
                                <a class="hm-action-button hm-action-button--neutral" href="headmaster-requisition-print.php?<?php echo hm_esc($headmasterHistoryPrintQuery); ?>" target="_blank" rel="noopener">Print History</a>
                            </div>
                        </form>

                        <?php if(empty($headmasterRequisitionHistoryRows)){ ?>
                        <div class="hm-empty-state">
                            <h3>No past requisition matched this view.</h3>
                            <p>Try a different status, request type, or search word.</p>
                        </div>
                        <?php } else { ?>
                        <div class="hm-history-table-wrap">
                            <table class="hm-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Requester</th>
                                        <th>Item</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Print</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($headmasterRequisitionHistoryRows as $_HistoryReq){ ?>
                                    <tr>
                                        <td><?php echo hm_esc(hm_dashboard_date($_HistoryReq['requestdate'])); ?></td>
                                        <td>
                                            <?php echo hm_esc($_HistoryReq['requested_by_name']); ?>
                                            <small><?php echo hm_esc($_HistoryReq['requestorigin_label']); ?> request</small>
                                        </td>
                                        <td>
                                            <?php echo hm_esc($_HistoryReq['itemname']); ?>
                                            <small><?php echo hm_esc(storekeeper_format_quantity($_HistoryReq['quantity'])); ?> <?php echo hm_esc($_HistoryReq['unitname']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo hm_esc($_HistoryReq['purpose']); ?>
                                            <?php if(trim((string)$_HistoryReq['stage_note']) !== ''){ ?>
                                            <small><?php echo hm_esc($_HistoryReq['stage_note']); ?></small>
                                            <?php } ?>
                                        </td>
                                        <td><span class="hm-status-pill hm-status-pill--<?php echo hm_esc(hm_status_tone($_HistoryReq['status'])); ?>"><?php echo hm_esc($_HistoryReq['status_label']); ?></span></td>
                                        <td><a class="hm-inline-print" href="headmaster-requisition-print.php?requisitionid=<?php echo rawurlencode((string)$_HistoryReq['requisitionid']); ?>&autoprint=1" target="_blank" rel="noopener"><i class="fa fa-print"></i> Print</a></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                    </div>
                </details>
            </section>
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
            <details class="hm-section-disclosure">
                <summary class="hm-section-disclosure__summary">
                    <div>
                        <span class="hm-section__eyebrow">Senior House</span>
                        <strong>Welfare and exeat overview</strong>
                        <small>Pending exeat: <?php echo number_format((int)$seniorHouseOverview['pending_exeat']); ?> | Students out: <?php echo number_format((int)$seniorHouseOverview['active_out']); ?> | Overdue returns: <?php echo number_format((int)$seniorHouseOverview['overdue_returns']); ?></small>
                    </div>
                </summary>
                <div class="hm-section-disclosure__body">
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
                </div>
            </details>
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

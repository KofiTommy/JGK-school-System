<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include("house-master-utils.php");
include("company.php");
include_once("semester-registry-utils.php");
include_once("voting-utils.php");
include_once("report-approval-utils.php");
include_once("counselling-utils.php");
include_once("student-chat-utils.php");
ensure_class_teacher_table($con);
ensure_house_tables($con);
ensure_voting_tables($con);
ensure_counselling_tables($con);
student_chat_ensure_tables($con);
counselling_process_due_reminders($con);

if(!house_master_is_student()){
    header("location:".house_master_landing_page());
    exit();
}

function sd_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function sd_alert($type, $message){
    $class = "student-inline-alert student-inline-alert--info";
    if($type === "success"){
        $class = "student-inline-alert student-inline-alert--success";
    }elseif($type === "error"){
        $class = "student-inline-alert student-inline-alert--error";
    }elseif($type === "warning"){
        $class = "student-inline-alert student-inline-alert--warning";
    }
    return "<div class=\"$class\">".sd_esc($message)."</div>";
}

function sd_term($term){
    $term = trim((string)$term);
    return $term === "" ? "Semester" : "Semester ".$term;
}

function sd_date($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y, H:i", $time) : (string)$value;
}

function sd_perf_delta_class($delta){
    if($delta === null){
        return "student-perf-delta student-perf-delta--neutral";
    }
    if((float)$delta > 0){
        return "student-perf-delta student-perf-delta--up";
    }
    if((float)$delta < 0){
        return "student-perf-delta student-perf-delta--down";
    }
    return "student-perf-delta student-perf-delta--neutral";
}

function sd_perf_delta_text($delta){
    if($delta === null){
        return "No previous year";
    }
    $delta = round((float)$delta, 2);
    if($delta > 0){
        return "+".number_format($delta, 2)."%";
    }
    if($delta < 0){
        return number_format($delta, 2)."%";
    }
    return "0.00%";
}

function sd_money($amount){
    $symbol = (isset($_SESSION['SYMBOL']) && trim((string)$_SESSION['SYMBOL']) !== "") ? trim((string)$_SESSION['SYMBOL']) : "GHC";
    return $symbol." ".number_format((float)$amount, 2);
}

function sd_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "approved" || $status === "returned"){
        return "student-status-pill student-status-pill--success";
    }
    if($status === "pending"){
        return "student-status-pill student-status-pill--warning";
    }
    if($status === "rejected" || $status === "declined" || $status === "denied"){
        return "student-status-pill student-status-pill--danger";
    }
    return "student-status-pill student-status-pill--neutral";
}

function sd_person_name($row){
    $parts = array();
    foreach(array('firstname', 'othernames', 'surname') as $field){
        if(isset($row[$field])){
            $value = trim((string)$row[$field]);
            if($value !== ''){
                $parts[] = $value;
            }
        }
    }
    return trim(implode(' ', $parts));
}

function sd_message_target_key($type, $group, $value){
    return trim((string)$type).'|'.trim((string)$group).'|'.trim((string)$value);
}

function sd_message_add_target(&$options, $type, $group, $value, $label){
    $key = sd_message_target_key($type, $group, $value);
    if(isset($options[$key])){
        return;
    }
    $options[$key] = array(
        'recipient_type' => trim((string)$type),
        'recipient_group' => trim((string)$group),
        'recipient_value' => trim((string)$value),
        'recipient_label' => trim((string)$label)
    );
}

function sd_student_class_teacher_targets($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, (string)$studentId);
    $options = array();
    $sql = "SELECT
                ct.userid AS recipient_userid,
                su.firstname,
                su.othernames,
                su.surname,
                ce.class_name,
                bh.batch,
                tr.termname
            FROM tbltermregistry tr
            INNER JOIN tblclassteacher ct
                ON ct.classid=tr.class_entryid
               AND ct.batchid=tr.batchid
               AND ct.termname=tr.termname
               AND ct.status='active'
            INNER JOIN tblsystemuser su
                ON su.userid=ct.userid
               AND su.status='active'
            LEFT JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
            LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
            WHERE tr.userid='$studentIdEsc'
            ORDER BY bh.datetimeentry DESC, tr.termname DESC, ct.datetimeentry DESC
            LIMIT 6";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $teacherId = trim((string)$row['recipient_userid']);
            if($teacherId === ''){
                continue;
            }
            $teacherName = sd_person_name($row);
            $classLabel = trim((string)$row['class_name']);
            $batchLabel = trim((string)$row['batch']);
            $termLabel = trim((string)$row['termname']);
            $label = "My Class Teacher";
            if($teacherName !== ''){
                $label .= " - ".$teacherName;
            }
            if($classLabel !== '' || $batchLabel !== '' || $termLabel !== ''){
                $label .= " (".trim($classLabel.($batchLabel !== '' ? " · ".$batchLabel : '').($termLabel !== '' ? " · Semester ".$termLabel : '')).")";
            }
            sd_message_add_target($options, 'user', 'teachers', $teacherId, $label);
        }
    }
    return $options;
}

function sd_student_house_teacher_targets($con, $studentId){
    $studentIdEsc = mysqli_real_escape_string($con, (string)$studentId);
    $options = array();
    $sql = "SELECT
                hm.userid AS recipient_userid,
                su.firstname,
                su.othernames,
                su.surname,
                h.housename
            FROM tblstudenthouse sh
            INNER JOIN tblhousemaster hm
                ON hm.houseid=sh.houseid
               AND hm.status='active'
            INNER JOIN tblsystemuser su
                ON su.userid=hm.userid
               AND su.status='active'
            INNER JOIN tblhouse h
                ON h.houseid=sh.houseid
               AND h.status='active'
            WHERE sh.userid='$studentIdEsc'
              AND sh.status='active'
            ORDER BY hm.datetimeentry DESC
            LIMIT 6";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $teacherId = trim((string)$row['recipient_userid']);
            if($teacherId === ''){
                continue;
            }
            $teacherName = sd_person_name($row);
            $houseLabel = trim((string)$row['housename']);
            $label = "My House Master / Mistress";
            if($teacherName !== ''){
                $label .= " - ".$teacherName;
            }
            if($houseLabel !== ''){
                $label .= " (".$houseLabel.")";
            }
            sd_message_add_target($options, 'user', 'teachers', $teacherId, $label);
        }
    }
    return $options;
}

function sd_student_message_targets($con, $studentId){
    $options = array();
    sd_message_add_target($options, 'group', 'admins', '', 'Admin Only');
    foreach(sd_student_class_teacher_targets($con, $studentId) as $key => $meta){
        $options[$key] = $meta;
    }
    foreach(sd_student_house_teacher_targets($con, $studentId) as $key => $meta){
        $options[$key] = $meta;
    }
    return $options;
}

$studentId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : "";
$studentIdEsc = mysqli_real_escape_string($con, $studentId);
$studentMessageTargets = sd_student_message_targets($con, $studentId);
$studentDefaultMessageTarget = key($studentMessageTargets);

if(isset($_POST['send_message'])){
    $message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ""));
    if($message === ""){
        $_SESSION['Message'] = sd_alert("warning", "Please type a message before sending.");
    }else{
        include("code.php");
        $messageId = mysqli_real_escape_string($con, (string)$code);
        $messageEsc = mysqli_real_escape_string($con, $message);
        $targetKey = isset($_POST['message_target']) ? trim((string)$_POST['message_target']) : (string)$studentDefaultMessageTarget;
        if($targetKey === '' || !isset($studentMessageTargets[$targetKey])){
            $targetKey = (string)$studentDefaultMessageTarget;
        }
        $targetMeta = isset($studentMessageTargets[$targetKey]) ? $studentMessageTargets[$targetKey] : array(
            'recipient_type' => 'group',
            'recipient_group' => 'admins',
            'recipient_value' => '',
            'recipient_label' => 'Admin Only'
        );
        $messageAudienceEsc = mysqli_real_escape_string($con, $targetMeta['recipient_group']);
        $messageTypeEsc = mysqli_real_escape_string($con, $targetMeta['recipient_type']);
        $messageValueEsc = mysqli_real_escape_string($con, $targetMeta['recipient_value']);
        $messageLabelEsc = mysqli_real_escape_string($con, $targetMeta['recipient_label']);
        $_SQL = mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group,recipient_type,recipient_value,recipient_label)
            VALUES('$messageId','$messageEsc',NOW(),'active','$studentIdEsc','$messageAudienceEsc','$messageTypeEsc','$messageValueEsc','$messageLabelEsc')");
        if($_SQL){
            engagement_track_daily_action($con, 'student_message_sent_daily', $studentId);
        }
        $_SESSION['Message'] = $_SQL ? sd_alert("success", "Message successfully submitted.") : sd_alert("error", "Message failed to submit.");
    }
    header("location:student-page.php#student-messages");
    exit();
}

if(isset($_POST['delete_message'])){
    $messageId = trim((string)(isset($_POST['messageid']) ? $_POST['messageid'] : ""));
    if($messageId !== ""){
        $messageIdEsc = mysqli_real_escape_string($con, $messageId);
        $_SQL_D = mysqli_query($con, "DELETE FROM tblmessages WHERE messageid='$messageIdEsc' AND sentby='$studentIdEsc' LIMIT 1");
        $_SESSION['Message'] = ($_SQL_D && mysqli_affected_rows($con) > 0) ? sd_alert("success", "Message successfully deleted.") : sd_alert("error", "Message could not be deleted.");
    }
    header("location:student-page.php#student-messages");
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
$_SESSION['Message'] = "";
$portalTitle = trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName)." Student Portal" : "Student Portal";
$studentName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : "";
$studentShortName = $studentName !== "" ? explode(" ", $studentName)[0] : "Student";
$studentBranch = "";
$studentFilename = "";

$profileRes = mysqli_query($con, "SELECT su.firstname,su.surname,su.othernames,su.filename,br.location
    FROM tblsystemuser su
    LEFT JOIN tblbranch br ON su.branchid=br.branchid
    WHERE su.userid='$studentIdEsc' LIMIT 1");
if($profileRes && $row = mysqli_fetch_array($profileRes, MYSQLI_ASSOC)){
    $full = trim($row['firstname']." ".$row['othernames']." ".$row['surname']);
    if($full !== ""){
        $studentName = $full;
        $studentShortName = explode(" ", $full)[0];
    }
    $studentBranch = trim((string)$row['location']);
    $studentFilename = trim((string)$row['filename']);
}

$studentImage = "uploads/comm.gif";
if($studentFilename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$studentFilename)){
    $studentImage = "uploads/".rawurlencode($studentFilename);
}

$houseInfo = get_student_active_house($con, $studentId);
$houseName = ($houseInfo && !empty($houseInfo['housename'])) ? trim((string)$houseInfo['housename']) : "Not Assigned";

$classLookup = array();
$classGroups = array();
$latestClassRow = null;
$classRes = mysqli_query($con, "SELECT cl.class_entryid,cl.batchid,cl.datetimeentry,ce.class_name,bh.batch
    FROM tblclass cl
    INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
    WHERE cl.userid='$studentIdEsc'
      AND cl.status='active'
    ORDER BY cl.datetimeentry DESC, bh.datetimeentry DESC, ce.class_name ASC");
if($classRes){
    while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
        if($latestClassRow === null){
            $latestClassRow = $row;
        }
        $key = $row['batchid']."|".$row['class_entryid'];
        if(isset($classLookup[$key])){
            continue;
        }
        $classLookup[$key] = true;
        $classGroups[] = $row;
    }
}
$classCount = count($classGroups);

$reportOptionLookup = array();
$semesterLookup = array();
$reportOptions = array();
$reportApprovalPendingCount = 0;
$termRes = mysqli_query($con, "SELECT tr.class_entryid,tr.batchid,tr.termname,ce.class_name,bh.batch,
    ".semester_registry_resolved_year_sql("tr")." AS academic_year
    FROM tbltermregistry tr
    INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
    WHERE tr.userid='$studentIdEsc'
    ORDER BY ".semester_registry_resolved_year_sql("tr")." DESC, bh.datetimeentry DESC, tr.termname DESC, ce.class_name ASC");
if($termRes){
    while($row = mysqli_fetch_array($termRes, MYSQLI_ASSOC)){
        $key = $row['batchid']."|".$row['class_entryid']."|".$row['termname']."|".trim((string)$row['academic_year']);
        if(isset($reportOptionLookup[$key])){
            continue;
        }
        $reportOptionLookup[$key] = true;
        $semesterLookup[$row['batchid']."|".$row['termname']."|".trim((string)$row['academic_year'])] = true;
        $reportOptions[] = $row;
    }
}
foreach($reportOptions as $reportIndex => $reportRow){
    $approvalMeta = report_approval_scope_meta(
        $con,
        isset($reportRow['batchid']) ? $reportRow['batchid'] : '',
        isset($reportRow['academic_year']) ? $reportRow['academic_year'] : '',
        isset($reportRow['termname']) ? $reportRow['termname'] : '',
        isset($reportRow['class_entryid']) ? $reportRow['class_entryid'] : ''
    );
    $reportOptions[$reportIndex]['report_required'] = $approvalMeta['required'] ? '1' : '0';
    $reportOptions[$reportIndex]['report_allowed'] = $approvalMeta['allowed'] ? '1' : '0';
    $reportOptions[$reportIndex]['report_status'] = $approvalMeta['status'];
    $reportOptions[$reportIndex]['report_status_label'] = $approvalMeta['status_label'];
    if($approvalMeta['required'] && !$approvalMeta['allowed']){
        $reportApprovalPendingCount++;
    }
}
$availableReportCount = count($reportOptions);
$semesterCount = count($semesterLookup);
$latestReportOption = $availableReportCount > 0 ? $reportOptions[0] : null;
$currentClassLabel = $latestClassRow ? trim((string)$latestClassRow['class_name']) : "";
$currentBatchLabel = $latestClassRow ? trim((string)$latestClassRow['batch']) : "";
if(($currentClassLabel === "" || $currentBatchLabel === "") && $latestReportOption){
    if($currentClassLabel === ""){
        $currentClassLabel = trim((string)$latestReportOption['class_name']);
    }
    if($currentBatchLabel === ""){
        $currentBatchLabel = trim((string)$latestReportOption['batch']);
    }
}
$currentSemesterLabel = $latestReportOption ? sd_term($latestReportOption['termname']) : "No semester selected";
$studentCounsellingSoonRows = counselling_dashboard_notification_rows($con, $studentId, 'student', 15);
$studentCounsellingSoonCount = count($studentCounsellingSoonRows);
$studentCounsellingSoonMessage = "";
if($studentCounsellingSoonCount > 0){
    $studentNextCounselling = $studentCounsellingSoonRows[0];
    $studentCounsellorName = counselling_person_name($studentNextCounselling);
    $studentCounsellingTime = counselling_format_time(isset($studentNextCounselling['scheduled_time']) ? $studentNextCounselling['scheduled_time'] : '');
    $studentCounsellingDate = counselling_format_date(isset($studentNextCounselling['scheduled_date']) ? $studentNextCounselling['scheduled_date'] : '');
    $studentCounsellingSoonMessage = $studentCounsellingSoonCount > 1
        ? "Counselling reminder: You have ".number_format((int)$studentCounsellingSoonCount)." sessions starting within the next 15 minutes. Next is with ".$studentCounsellorName." at ".$studentCounsellingTime." on ".$studentCounsellingDate.". Open Guidance & Counselling."
        : "Counselling reminder: Your session with ".$studentCounsellorName." starts at ".$studentCounsellingTime." on ".$studentCounsellingDate.". Open Guidance & Counselling.";
}

$financeBilled = 0.0;
$financePaid = 0.0;
$latestPaymentDate = "";
$billedRes = mysqli_query($con, "SELECT COALESCE(SUM(ip.price),0) AS total_billed
    FROM tblbilling bi
    INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
    WHERE bi.userid='$studentIdEsc'");
if($billedRes && $row = mysqli_fetch_array($billedRes, MYSQLI_ASSOC)){
    $financeBilled = (float)$row['total_billed'];
}
$paidRes = mysqli_query($con, "SELECT COALESCE(SUM(pm.payment),0) AS total_paid, MAX(pm.datetimepayment) AS latest_payment
    FROM tblpayment pm
    INNER JOIN tblbilling bi ON bi.billid=pm.billid
    WHERE bi.userid='$studentIdEsc'");
if($paidRes && $row = mysqli_fetch_array($paidRes, MYSQLI_ASSOC)){
    $financePaid = (float)$row['total_paid'];
    $latestPaymentDate = trim((string)$row['latest_payment']);
}
$financeBalance = $financeBilled - $financePaid;
$messageUnreadCount = um_message_unread_count($con, $studentId, 'Student');
$studentPrivateChatEnabled = student_chat_is_enabled($con);
$studentPrivateChatPendingCount = $studentPrivateChatEnabled ? student_chat_pending_inbound_count($con, $studentId) : 0;
$studentVotingSnapshot = voting_dashboard_snapshot($con, voting_default_branch_id($con), 'Student');

$exeatTotal = 0;
$exeatPending = 0;
$exeatApproved = 0;
$recentExeat = array();
$exeatStatsRes = mysqli_query($con, "SELECT COUNT(*) AS total_requests,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_requests,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_requests
    FROM tblexeatrequest
    WHERE userid='$studentIdEsc'");
if($exeatStatsRes && $row = mysqli_fetch_array($exeatStatsRes, MYSQLI_ASSOC)){
    $exeatTotal = (int)$row['total_requests'];
    $exeatPending = (int)$row['pending_requests'];
    $exeatApproved = (int)$row['approved_requests'];
}
$exeatRes = mysqli_query($con, "SELECT er.exeattype,er.status,er.reason,er.dateout,er.timeout,er.datereturn,er.timereturn,er.decisionnote,er.requestedatetime,h.housename
    FROM tblexeatrequest er
    LEFT JOIN tblhouse h ON h.houseid=er.houseid
    WHERE er.userid='$studentIdEsc'
    ORDER BY er.requestedatetime DESC
    LIMIT 4");
if($exeatRes){
    while($row = mysqli_fetch_array($exeatRes, MYSQLI_ASSOC)){
        $recentExeat[] = $row;
    }
}

$myMessages = array();
$messageRes = mysqli_query($con, "SELECT messageid,messages,datetimeentry,recipient_label
    FROM tblmessages
    WHERE sentby='$studentIdEsc' AND status='active'
    ORDER BY datetimeentry DESC
    LIMIT 6");
if($messageRes){
    while($row = mysqli_fetch_array($messageRes, MYSQLI_ASSOC)){
        $myMessages[] = $row;
    }
}
$engagementSummary = engagement_get_summary($con, $studentId);
$engagementRecent = engagement_get_recent_activity($con, $studentId, 5);

$studentPerformanceYearOptions = array();
$studentPerformanceYear = "";
$studentPerformancePreviousYear = null;
$studentPerfRows = array();
$studentPerfLabels = array();
$studentPerfAvg = array();
$studentPerfPass = array();
$studentPerfTrendLabels = array();
$studentPerfTrendAvg = array();
$studentPerfTrendPass = array();
$studentPerfComparisonRows = array();
$studentPerfOverallByYear = array();
$studentPerfCurrentAvg = 0;
$studentPerfCurrentPass = 0;
$studentPerfSubjectCount = 0;
$studentPerfBestSubject = "N/A";
$studentPerfBestScore = 0;
$studentPerfYearDelta = null;
$studentPerfYearSql = semester_registry_assignment_year_sql("sa");

$studentYearOptionsSql = "SELECT DISTINCT academic_year FROM (
        SELECT $studentPerfYearSql AS academic_year
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
        WHERE mk.userid='$studentIdEsc' AND mk.status='active'
        UNION
        SELECT COALESCE(NULLIF(tr.academicyear,''), DATE_FORMAT(tr.datetimeentry, '%Y')) AS academic_year
        FROM tbltermregistry tr
        WHERE tr.userid='$studentIdEsc'
    ) perf_years
    WHERE academic_year IS NOT NULL AND academic_year<>''
    ORDER BY academic_year DESC";
$studentYearOptionsRes = mysqli_query($con, $studentYearOptionsSql);
if($studentYearOptionsRes){
    while($studentYearRow = mysqli_fetch_array($studentYearOptionsRes, MYSQLI_ASSOC)){
        $yearValue = trim((string)$studentYearRow['academic_year']);
        if($yearValue !== ""){
            $studentPerformanceYearOptions[] = $yearValue;
        }
    }
}
if(count($studentPerformanceYearOptions) === 0){
    $studentPerformanceYearOptions[] = date("Y");
}
$studentRequestedYear = isset($_GET['student_perf_year']) ? semester_registry_normalize_year($_GET['student_perf_year']) : "";
$studentPerformanceYear = in_array($studentRequestedYear, $studentPerformanceYearOptions, true) ? $studentRequestedYear : $studentPerformanceYearOptions[0];
foreach($studentPerformanceYearOptions as $studentYearOption){
    if((int)$studentYearOption < (int)$studentPerformanceYear){
        $studentPerformancePreviousYear = $studentYearOption;
        break;
    }
}

$studentPerfWhere = "WHERE mk.userid='$studentIdEsc' AND mk.status='active' AND $studentPerfYearSql='".mysqli_real_escape_string($con, $studentPerformanceYear)."'";
$studentPerfSql = "SELECT
        sub.subjectid,
        sub.subject,
        COUNT(*) AS entries_count,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
    $studentPerfWhere
    GROUP BY sub.subjectid, sub.subject
    ORDER BY avg_percent DESC, sub.subject ASC";
$studentPerfRes = mysqli_query($con, $studentPerfSql);
if($studentPerfRes){
    while($studentPerfRow = mysqli_fetch_array($studentPerfRes, MYSQLI_ASSOC)){
        $studentPerfRows[] = $studentPerfRow;
        $studentPerfLabels[] = $studentPerfRow['subject'];
        $studentPerfAvg[] = (float)$studentPerfRow['avg_percent'];
        $studentPerfPass[] = (float)$studentPerfRow['pass_rate'];
        $studentPerfCurrentAvg += (float)$studentPerfRow['avg_percent'];
        $studentPerfCurrentPass += (float)$studentPerfRow['pass_rate'];
        $studentPerfSubjectCount++;
        if((float)$studentPerfRow['avg_percent'] >= $studentPerfBestScore){
            $studentPerfBestScore = (float)$studentPerfRow['avg_percent'];
            $studentPerfBestSubject = (string)$studentPerfRow['subject'];
        }
    }
}
if($studentPerfSubjectCount > 0){
    $studentPerfCurrentAvg = round($studentPerfCurrentAvg / $studentPerfSubjectCount, 2);
    $studentPerfCurrentPass = round($studentPerfCurrentPass / $studentPerfSubjectCount, 2);
}

$studentTrendSql = "SELECT
        $studentPerfYearSql AS academic_year,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
    WHERE mk.userid='$studentIdEsc' AND mk.status='active'
    GROUP BY academic_year
    ORDER BY academic_year ASC";
$studentTrendRes = mysqli_query($con, $studentTrendSql);
if($studentTrendRes){
    while($studentTrendRow = mysqli_fetch_array($studentTrendRes, MYSQLI_ASSOC)){
        $trendYear = trim((string)$studentTrendRow['academic_year']);
        if($trendYear === ""){
            continue;
        }
        $studentPerfTrendLabels[] = $trendYear;
        $studentPerfTrendAvg[] = (float)$studentTrendRow['avg_percent'];
        $studentPerfTrendPass[] = (float)$studentTrendRow['pass_rate'];
        $studentPerfOverallByYear[$trendYear] = (float)$studentTrendRow['avg_percent'];
    }
}
if($studentPerformancePreviousYear !== null && isset($studentPerfOverallByYear[$studentPerformancePreviousYear])){
    $studentPerfYearDelta = $studentPerfCurrentAvg - (float)$studentPerfOverallByYear[$studentPerformancePreviousYear];
}

$studentSubjectHistory = array();
$studentHistorySql = "SELECT
        sub.subject,
        $studentPerfYearSql AS academic_year,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
    WHERE mk.userid='$studentIdEsc' AND mk.status='active'
    GROUP BY sub.subject, academic_year
    ORDER BY sub.subject ASC, academic_year DESC";
$studentHistoryRes = mysqli_query($con, $studentHistorySql);
if($studentHistoryRes){
    while($studentHistoryRow = mysqli_fetch_array($studentHistoryRes, MYSQLI_ASSOC)){
        $historySubject = trim((string)$studentHistoryRow['subject']);
        $historyYear = trim((string)$studentHistoryRow['academic_year']);
        if($historySubject === "" || $historyYear === ""){
            continue;
        }
        if(!isset($studentSubjectHistory[$historySubject])){
            $studentSubjectHistory[$historySubject] = array();
        }
        $studentSubjectHistory[$historySubject][$historyYear] = (float)$studentHistoryRow['avg_percent'];
    }
}
foreach($studentPerfRows as $studentPerfRow){
    $subjectLabel = (string)$studentPerfRow['subject'];
    $previousYear = null;
    $previousAvg = null;
    if(isset($studentSubjectHistory[$subjectLabel])){
        $subjectYears = array_keys($studentSubjectHistory[$subjectLabel]);
        rsort($subjectYears, SORT_NUMERIC);
        foreach($subjectYears as $subjectYear){
            if((int)$subjectYear < (int)$studentPerformanceYear){
                $previousYear = (string)$subjectYear;
                $previousAvg = (float)$studentSubjectHistory[$subjectLabel][$subjectYear];
                break;
            }
        }
    }
    $studentPerfComparisonRows[] = array(
        'subject' => $subjectLabel,
        'current_avg' => (float)$studentPerfRow['avg_percent'],
        'previous_year' => $previousYear,
        'previous_avg' => $previousAvg,
        'delta' => ($previousAvg !== null ? ((float)$studentPerfRow['avg_percent'] - (float)$previousAvg) : null)
    );
}

$reportPreview = array_slice($reportOptions, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="student-dashboard-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="student-shell">
<?php if($flashMessage !== ""){ ?><div class="student-flash"><?php echo $flashMessage; ?></div><?php } ?>
<?php if($studentCounsellingSoonMessage !== ""){ ?><div class="student-flash"><?php echo sd_alert("warning", $studentCounsellingSoonMessage); ?></div><?php } ?>

<section class="student-hero">
    <div class="student-hero__copy">
        <span class="student-kicker"><?php echo sd_esc($portalTitle); ?></span>
        <h1>Welcome back, <?php echo sd_esc($studentShortName); ?>.</h1>
        <div class="student-hero__utility">
            <div class="student-live-clock">
                <div class="xschool-live-clock" data-live-clock>
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
        <div class="student-stat-grid">
            <article class="student-stat-card student-stat-card--classes"><span>Registered Classes</span><strong><?php echo (int)$classCount; ?></strong></article>
            <article class="student-stat-card student-stat-card--reports"><span>Report Options</span><strong><?php echo (int)$availableReportCount; ?></strong></article>
            <article class="student-stat-card student-stat-card--exeat"><span>Pending Exeat</span><strong><?php echo (int)$exeatPending; ?></strong></article>
            <article class="student-stat-card student-stat-card--finance"><span>Balance</span><strong><?php echo sd_esc(sd_money($financeBalance)); ?></strong></article>
        </div>
    </div>
    <aside class="student-profile-card">
        <div class="student-profile-card__top">
            <img src="<?php echo sd_esc($studentImage); ?>" alt="<?php echo sd_esc($studentName !== "" ? $studentName : $studentId); ?>">
            <div>
                <span class="student-profile-card__eyebrow">Student Profile</span>
                <h2><?php echo sd_esc($studentName !== "" ? $studentName : $studentId); ?></h2>
                <p><?php echo sd_esc($studentBranch !== "" ? $studentBranch : "Branch not set"); ?></p>
            </div>
        </div>
        <div class="student-profile-meta">
            <div class="student-profile-meta__item"><span>Current Class</span><strong><?php echo sd_esc($currentClassLabel !== "" ? $currentClassLabel : "Not Yet Registered"); ?></strong></div>
            <div class="student-profile-meta__item"><span>Academic Year</span><strong><?php echo sd_esc($currentBatchLabel !== "" ? $currentBatchLabel : "Not Yet Registered"); ?></strong></div>
            <div class="student-profile-meta__item"><span>House</span><strong><?php echo sd_esc($houseName); ?></strong></div>
        </div>
        <div class="student-profile-actions">
            <a class="student-secondary-link" href="edit-account.php"><i class="fa fa-user"></i> Edit Profile</a>
            <a class="student-secondary-link" href="uploaduser-image.php"><i class="fa fa-arrow-circle-up"></i> Upload Image</a>
            <a class="student-secondary-link" href="change-password.php"><i class="fa fa-key"></i> Change Password</a>
        </div>
    </aside>
</section>

<section class="student-section">
    <div class="student-section__heading">
        <div><span class="student-section__eyebrow">Student Tools</span><h2>Open your student tools</h2></div>
    </div>
    <div class="student-quick-grid">
        <a class="student-action-card student-action-card--report" href="individual-terminal-report.php"><span class="student-action-card__icon"><i class="fa fa-book"></i></span><h3>Terminal Report</h3></a>
        <a class="student-action-card student-action-card--finance" href="account-statements.php"><span class="student-action-card__icon"><i class="fa fa-money"></i></span><h3>Account Statement</h3></a>
        <a class="student-action-card student-action-card--exeat" href="student-exeat-request.php"><span class="student-action-card__icon"><i class="fa fa-file"></i></span><h3>Request Exeat</h3></a>
        <a class="student-action-card student-action-card--counselling" href="guidance-counselling.php"><span class="student-action-card__icon"><i class="fa fa-heartbeat"></i></span><h3>Guidance &amp; Counselling<?php if($studentCounsellingSoonCount > 0){ ?><span class="student-action-card__badge"><?php echo (int)$studentCounsellingSoonCount; ?> Soon</span><?php } ?></h3><p><?php echo $studentCounsellingSoonCount > 0 ? "Your counselling session starts soon. Open the case to review the meeting details." : "Book a private session with your dedicated counsellor and track the case here."; ?></p></a>
        <a class="student-action-card student-action-card--exam" href="examinationtimetablereport.php"><span class="student-action-card__icon"><i class="fa fa-calendar"></i></span><h3>Exam Timetable</h3></a>
        <a class="student-action-card student-action-card--timetable" href="lesson-timetable-report.php"><span class="student-action-card__icon"><i class="fa fa-clock-o"></i></span><h3>Lesson Timetable</h3></a>
        <a class="student-action-card student-action-card--online" href="online-class.php"><span class="student-action-card__icon"><i class="fa fa-video-camera"></i></span><h3>Join Class</h3><p>Open live class links shared by your teachers for your class.</p></a>
        <a class="student-action-card student-action-card--course" href="student-course-registration.php"><span class="student-action-card__icon"><i class="fa fa-list-alt"></i></span><h3>Course Registration</h3><p>Choose your semester courses from the class list when the school opens registration.</p></a>
        <a class="student-action-card student-action-card--attendance" href="student-attendance-report.php"><span class="student-action-card__icon"><i class="fa fa-bar-chart"></i></span><h3>My Attendance</h3></a>
        <a class="student-action-card student-action-card--voting" href="online-voting.php"><?php if($studentVotingSnapshot && !empty($studentVotingSnapshot["contest"])){ ?><span class="student-action-card__icon"><i class="fa fa-trophy"></i></span><h3>Online Voting</h3><p><?php echo sd_esc($studentVotingSnapshot["contest"]["title"]); ?> is <?php echo sd_esc(strtolower(voting_status_label($studentVotingSnapshot["contest"]["resolved_status"]))); ?>.</p><?php }else{ ?><span class="student-action-card__icon"><i class="fa fa-trophy"></i></span><h3>Online Voting</h3><p>Voting details will appear when a contest is available.</p><?php } ?></a>
        <a class="student-action-card student-action-card--messages" href="messages.php"><span class="student-action-card__icon"><i class="fa fa-comments"></i></span><h3>Message Board<?php if($messageUnreadCount > 0){ ?><span class="student-action-card__badge"><?php echo (int)$messageUnreadCount; ?> New</span><?php } ?></h3><p><?php echo $messageUnreadCount > 0 ? number_format((int)$messageUnreadCount)." unread message".((int)$messageUnreadCount === 1 ? "" : "s")." waiting for you." : "Open the message board to read or send messages."; ?></p></a>
        <?php if($studentPrivateChatEnabled){ ?>
        <a class="student-action-card student-action-card--private-chat" href="student-chat.php"><span class="student-action-card__icon"><i class="fa fa-user-plus"></i></span><h3>Student Chat<?php if($studentPrivateChatPendingCount > 0){ ?><span class="student-action-card__badge"><?php echo (int)$studentPrivateChatPendingCount; ?> Request<?php echo (int)$studentPrivateChatPendingCount === 1 ? "" : "s"; ?></span><?php } ?></h3><p><?php echo $studentPrivateChatPendingCount > 0 ? "You have private chat request".((int)$studentPrivateChatPendingCount === 1 ? "" : "s")." waiting for your approval." : "Find fellow students and chat privately after approval."; ?></p></a>
        <?php } ?>
        <a class="student-action-card student-action-card--profile" href="edit-account.php"><span class="student-action-card__icon"><i class="fa fa-id-card"></i></span><h3>Profile Settings</h3></a>
        <a class="student-action-card student-action-card--profile-image" href="uploaduser-image.php"><span class="student-action-card__icon"><i class="fa fa-image"></i></span><h3>Profile Image</h3></a>
        <a class="student-action-card student-action-card--logout" href="logout.php"><span class="student-action-card__icon"><i class="fa fa-power-off"></i></span><h3>Sign Out</h3></a>
    </div>
</section>

<?php if($studentVotingSnapshot && !empty($studentVotingSnapshot["contest"])){ ?>
<section class="student-section student-voting-section">
    <div class="student-section__heading">
        <div><span class="student-section__eyebrow">Voting</span><h2>Current voting update</h2></div>
        <a class="student-panel__link" href="online-voting.php">View Voting</a>
    </div>
    <div class="student-voting-card">
        <div class="student-voting-card__summary">
            <article><span>Contest</span><strong><?php echo sd_esc($studentVotingSnapshot["contest"]["title"]); ?></strong></article>
            <article><span>Status</span><strong><?php echo sd_esc(voting_status_label($studentVotingSnapshot["contest"]["resolved_status"])); ?></strong></article>
            <article><span>Total Votes</span><strong><?php echo number_format((int)$studentVotingSnapshot["summary"]["total_votes"]); ?></strong></article>
            <article><span>Leader</span><strong><?php echo sd_esc($studentVotingSnapshot["summary"]["leader_name"]); ?></strong></article>
        </div>
        <div class="student-voting-card__leaders">
            <?php foreach($studentVotingSnapshot["top_candidates"] as $voteCandidate){ ?>
            <a class="student-voting-leader" href="online-voting.php?contest=<?php echo rawurlencode((string)$studentVotingSnapshot["contest"]["contestid"]); ?>&candidate=<?php echo rawurlencode((string)$voteCandidate["candidateid"]); ?>">
                <img src="<?php echo sd_esc(voting_candidate_photo($voteCandidate)); ?>" alt="<?php echo sd_esc($voteCandidate["candidatename"]); ?>">
                <div>
                    <strong><?php echo sd_esc($voteCandidate["candidatename"]); ?></strong>
                    <span><?php echo number_format((int)$voteCandidate["totalvotes"]); ?> votes</span>
                </div>
            </a>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<div class="student-layout">
    <div class="student-panel-stack student-panel-stack--main">
    <section class="student-panel student-panel--wide">
        <div class="student-panel__header">
            <div><span class="student-panel__eyebrow">Academic Record</span><h2>Available reports and current class record</h2></div>
            <a class="student-panel__link" href="individual-terminal-report.php">View Reports</a>
        </div>
        <div class="student-summary-grid">
            <article class="student-summary-card"><span>Current Class</span><strong><?php echo sd_esc($currentClassLabel !== "" ? $currentClassLabel : "Not Yet Registered"); ?></strong></article>
            <article class="student-summary-card"><span>Current Year</span><strong><?php echo sd_esc($currentBatchLabel !== "" ? $currentBatchLabel : "Not Yet Registered"); ?></strong></article>
            <article class="student-summary-card"><span>Latest Semester</span><strong><?php echo sd_esc($currentSemesterLabel); ?></strong></article>
            <article class="student-summary-card"><span>Available Semesters</span><strong><?php echo (int)$semesterCount; ?></strong></article>
        </div>

        <?php if($reportApprovalPendingCount > 0){ ?>
        <div class="student-inline-note"><?php echo sd_esc($reportApprovalPendingCount); ?> semester report(s) are awaiting approval.</div>
        <?php } ?>

        <?php if(count($reportPreview) > 0){ ?>
        <div class="student-report-grid">
            <?php foreach($reportPreview as $report){ ?>
            <article class="student-report-card">
                <div class="student-report-card__meta">
                    <span class="<?php echo sd_esc(sd_status_class(isset($report['report_status']) ? $report['report_status'] : 'neutral')); ?>"><?php echo sd_esc(isset($report['report_status_label']) ? $report['report_status_label'] : sd_term($report['termname'])); ?></span>
                    <span class="student-report-card__year"><?php echo sd_esc(trim((string)(isset($report['academic_year']) && trim((string)$report['academic_year']) !== '' ? $report['academic_year'] : $report['batch']))); ?></span>
                </div>
                <h3><?php echo sd_esc($report['class_name']); ?></h3>
                <p><?php echo sd_esc(sd_term($report['termname']).' · '.trim((string)$report['batch'])); ?></p>
                <form method="post" action="individual-terminal-report.php" class="student-inline-form">
                    <input type="hidden" name="batchid" value="<?php echo sd_esc((string)$report['batchid']); ?>">
                    <input type="hidden" name="academicyear" value="<?php echo sd_esc((string)(isset($report['academic_year']) ? $report['academic_year'] : '')); ?>">
                    <input type="hidden" name="termid" value="<?php echo sd_esc((string)$report['termname']); ?>">
                    <input type="hidden" name="classid" value="<?php echo sd_esc((string)$report['class_entryid']); ?>">
                    <button class="student-inline-btn<?php echo (!empty($report['report_allowed']) && $report['report_allowed'] === '1') ? '' : ' is-disabled'; ?>" type="submit" name="print_terminal_report" <?php echo (!empty($report['report_allowed']) && $report['report_allowed'] === '1') ? '' : 'disabled'; ?>><i class="fa fa-print"></i> <?php echo (!empty($report['report_allowed']) && $report['report_allowed'] === '1') ? 'Print Report' : 'Awaiting Approval'; ?></button>
                </form>
            </article>
            <?php } ?>
        </div>
        <?php } else { ?>
        <div class="student-empty-state"><h3>No reports available yet</h3></div>
        <?php } ?>
    </section>

    <section class="student-panel student-panel--wide">
        <div class="student-panel__header">
            <div><span class="student-panel__eyebrow">Subject Performance</span><h2>See your subject performance</h2></div>
            <a class="student-panel__link" href="individual-terminal-report.php">View Report</a>
        </div>
        <form method="get" action="student-page.php" class="student-performance-toolbar">
            <div class="student-performance-toolbar__field">
                <label for="student_perf_year">Academic Year</label>
                <select id="student_perf_year" name="student_perf_year">
                    <?php foreach($studentPerformanceYearOptions as $yearOption){ ?>
                    <option value="<?php echo sd_esc($yearOption); ?>"<?php echo ($studentPerformanceYear === $yearOption ? " selected" : ""); ?>><?php echo sd_esc($yearOption); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="student-performance-toolbar__actions">
                <button type="submit" class="student-primary-btn"><i class="fa fa-filter"></i> View Year</button>
            </div>
        </form>

        <div class="student-performance-scope">
            <strong>Selected Year:</strong> <?php echo sd_esc($studentPerformanceYear); ?> Academic Year
            <?php if($studentPerformancePreviousYear !== null){ ?>
            <span>Compared with <?php echo sd_esc($studentPerformancePreviousYear); ?></span>
            <?php } ?>
        </div>

        <div class="student-performance-kpis">
            <article class="student-performance-kpi">
                <span>Subjects With Scores</span>
                <strong><?php echo number_format((int)$studentPerfSubjectCount); ?></strong>
            </article>
            <article class="student-performance-kpi">
                <span>Average Score</span>
                <strong><?php echo number_format((float)$studentPerfCurrentAvg, 2); ?>%</strong>
            </article>
            <article class="student-performance-kpi">
                <span>Pass Rate</span>
                <strong><?php echo number_format((float)$studentPerfCurrentPass, 2); ?>%</strong>
            </article>
            <article class="student-performance-kpi">
                <span>Strongest Subject</span>
                <strong><?php echo sd_esc($studentPerfBestSubject); ?></strong>
                <small><?php echo number_format((float)$studentPerfBestScore, 2); ?>%</small>
            </article>
            <article class="student-performance-kpi">
                <span>Year Comparison</span>
                <strong><?php echo ($studentPerformancePreviousYear !== null ? sd_esc($studentPerformancePreviousYear) : "No prior year"); ?></strong>
                <small class="<?php echo sd_esc(sd_perf_delta_class($studentPerfYearDelta)); ?>"><?php echo sd_esc(sd_perf_delta_text($studentPerfYearDelta)); ?></small>
            </article>
        </div>

        <?php if(count($studentPerfRows) > 0 || count($studentPerfTrendLabels) > 0){ ?>
        <div class="student-performance-grid">
            <article class="student-performance-card">
                <div class="student-performance-card__head">
                    <h3><?php echo sd_esc($studentPerformanceYear); ?> Subject Snapshot</h3>
                    <span>See your average score and pass rate by subject.</span>
                </div>
                <div class="student-performance-chart-wrap">
                    <canvas id="studentSubjectPerformanceChart" height="280" aria-label="Student subject performance chart"></canvas>
                </div>
            </article>
            <article class="student-performance-card">
                <div class="student-performance-card__head">
                    <h3>Previous Years Trend</h3>
                    <span>Compare your overall performance across academic years.</span>
                </div>
                <div class="student-performance-chart-wrap">
                    <canvas id="studentSubjectTrendChart" height="280" aria-label="Student subject trend chart"></canvas>
                </div>
            </article>
        </div>

        <div class="student-performance-table-wrap">
            <div class="student-performance-card__head">
                <h3>Subject-by-Subject Comparison</h3>
                <span>See how each subject compares with your most recent earlier year.</span>
            </div>
            <div class="student-performance-table-scroll">
                <table class="student-performance-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th><?php echo sd_esc($studentPerformanceYear); ?> Avg %</th>
                            <th>Previous Year</th>
                            <th>Previous Avg %</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($studentPerfComparisonRows as $comparisonRow){ ?>
                        <tr>
                            <td><?php echo sd_esc($comparisonRow['subject']); ?></td>
                            <td><?php echo number_format((float)$comparisonRow['current_avg'], 2); ?></td>
                            <td><?php echo sd_esc($comparisonRow['previous_year'] !== null ? $comparisonRow['previous_year'] : 'N/A'); ?></td>
                            <td><?php echo ($comparisonRow['previous_avg'] !== null ? number_format((float)$comparisonRow['previous_avg'], 2) : 'N/A'); ?></td>
                            <td><span class="<?php echo sd_esc(sd_perf_delta_class($comparisonRow['delta'])); ?>"><?php echo sd_esc(sd_perf_delta_text($comparisonRow['delta'])); ?></span></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } else { ?>
        <div class="student-empty-state student-empty-state--compact">
            <p>No score data was found for the selected academic year yet.</p>
        </div>
        <?php } ?>
    </section>
    </div>

    <div class="student-panel-stack">
        <section class="student-panel student-panel--accent student-panel--engagement">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">Engagement</span><h2>Stay active on your student portal</h2></div>
            </div>
            <div class="student-engagement-hero student-engagement-hero--<?php echo sd_esc($engagementSummary["badge"]["tone"]); ?>">
                <div class="student-engagement-hero__copy">
                    <span class="student-engagement-hero__eyebrow">Current Level</span>
                    <h3><?php echo sd_esc($engagementSummary["badge"]["label"]); ?></h3>
                    <div class="student-engagement-stars" aria-label="<?php echo (int)$engagementSummary["stars"]; ?> stars">
                        <?php for($starIndex = 1; $starIndex <= 5; $starIndex++){ ?>
                        <i class="fa fa-star<?php echo ($starIndex <= (int)$engagementSummary["stars"]) ? " is-active" : ""; ?>"></i>
                        <?php } ?>
                    </div>
                    <div class="student-engagement-total"><?php echo number_format((int)$engagementSummary["total_points"]); ?> points</div>
                    <div class="student-engagement-meter" aria-hidden="true">
                        <span class="student-engagement-meter__fill" style="width: <?php echo (int)$engagementSummary["progress_percent"]; ?>%;"></span>
                    </div>
                    <p class="student-engagement-progress-copy">
                        <?php if(!empty($engagementSummary["next_badge"])){ ?>
                            <?php echo number_format((int)$engagementSummary["points_to_next"]); ?> more point<?php echo ((int)$engagementSummary["points_to_next"] === 1 ? "" : "s"); ?> to reach <?php echo sd_esc((string)$engagementSummary["next_badge"]["label"]); ?>.
                        <?php } else { ?>
                            Top level reached. Keep showing up well.
                        <?php } ?>
                    </p>
                </div>
                <div class="student-engagement-side">
                    <article class="student-engagement-stat">
                        <span>This Week</span>
                        <strong><?php echo number_format((int)$engagementSummary["week_points"]); ?></strong>
                    </article>
                    <article class="student-engagement-stat">
                        <span>Active Streak</span>
                        <strong><?php echo number_format((int)$engagementSummary["streak_days"]); ?> Day<?php echo ((int)$engagementSummary["streak_days"] === 1 ? "" : "s"); ?></strong>
                    </article>
                    <article class="student-engagement-stat">
                        <span>Progress</span>
                        <strong><?php echo (int)$engagementSummary["progress_percent"]; ?>%</strong>
                    </article>
                </div>
            </div>
            <div class="student-engagement-list">
                <?php if(count($engagementRecent) > 0){ ?>
                    <?php foreach($engagementRecent as $activity){ ?>
                    <article class="student-engagement-item">
                        <div class="student-engagement-item__meta">
                            <strong><?php echo sd_esc((string)$activity["actionlabel"]); ?></strong>
                            <span><?php echo sd_esc(sd_date((string)$activity["datetimeentry"])); ?></span>
                        </div>
                        <div class="student-engagement-points">+<?php echo number_format((int)$activity["pointvalue"]); ?></div>
                    </article>
                    <?php } ?>
                <?php } else { ?>
                <div class="student-empty-state student-empty-state--compact"><p>No activity yet.</p></div>
                <?php } ?>
            </div>
        </section>

        <section class="student-panel student-panel--accent student-panel--registrations">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">Registrations</span><h2>My classes and years</h2></div>
            </div>
            <?php if($classCount > 0){ ?>
            <div class="student-list-grid">
                <?php foreach($classGroups as $classRow){ ?>
                <article class="student-list-card">
                    <h3><?php echo sd_esc($classRow['class_name']); ?></h3>
                    <p><?php echo sd_esc($classRow['batch']); ?></p>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="student-empty-state student-empty-state--compact"><p>No classes yet.</p></div>
            <?php } ?>
        </section>

        <section class="student-panel student-panel--accent student-panel--house">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">House And Exeat</span><h2>Current house and latest requests</h2></div>
                <a class="student-panel__link" href="student-exeat-request.php">View Exeat</a>
            </div>
            <div class="student-house-card">
                <div class="student-house-card__row"><span>House</span><strong><?php echo sd_esc($houseName); ?></strong></div>
                <div class="student-house-card__row"><span>Total Requests</span><strong><?php echo (int)$exeatTotal; ?></strong></div>
                <div class="student-house-card__row"><span>Approved</span><strong><?php echo (int)$exeatApproved; ?></strong></div>
                <div class="student-house-card__row"><span>Pending</span><strong><?php echo (int)$exeatPending; ?></strong></div>
            </div>
            <?php if(count($recentExeat) > 0){ ?>
            <div class="student-list-grid student-list-grid--tight">
                <?php foreach($recentExeat as $exeat){ ?>
                <article class="student-list-card">
                    <div class="student-report-card__meta">
                        <span class="<?php echo sd_esc(sd_status_class($exeat['status'])); ?>"><?php echo sd_esc(ucfirst((string)$exeat['status'])); ?></span>
                        <span class="student-report-card__year"><?php echo sd_esc(ucfirst((string)$exeat['exeattype'])); ?></span>
                    </div>
                    <h3><?php echo sd_esc($exeat['housename'] !== "" ? $exeat['housename'] : $houseName); ?></h3>
                    <p><?php echo sd_esc($exeat['reason']); ?></p>
                    <small><?php echo sd_esc(trim((string)$exeat['dateout']." ".(string)$exeat['timeout'])); ?> to <?php echo sd_esc(trim((string)$exeat['datereturn']." ".(string)$exeat['timereturn'])); ?></small>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="student-empty-state student-empty-state--compact"><p>No exeat request yet.</p></div>
            <?php } ?>
        </section>

        <section class="student-panel student-panel--accent student-panel--finance">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">Finance</span><h2>Account balance</h2></div>
                <a class="student-panel__link" href="account-statements.php">View Statements</a>
            </div>
            <div class="student-finance-grid">
                <div class="student-finance-card"><span>Total Billed</span><strong><?php echo sd_esc(sd_money($financeBilled)); ?></strong></div>
                <div class="student-finance-card"><span>Total Paid</span><strong><?php echo sd_esc(sd_money($financePaid)); ?></strong></div>
                <div class="student-finance-card"><span>Outstanding</span><strong><?php echo sd_esc(sd_money($financeBalance)); ?></strong></div>
            </div>
            <div class="student-finance-note">
                <span>Latest Payment</span>
                <strong><?php echo sd_esc($latestPaymentDate !== "" ? sd_date($latestPaymentDate) : "No payment record yet"); ?></strong>
            </div>
        </section>
    </div>
</div>

<div class="student-layout student-layout--messages">
    <section class="student-panel student-panel--accent student-panel--messages" id="student-messages">
        <div class="student-panel__header">
            <div><span class="student-panel__eyebrow">Message Center</span><h2>Send and manage your messages</h2></div>
            <a class="student-panel__link" href="messages.php">View Messages<?php if($messageUnreadCount > 0){ ?><span class="student-panel__badge"><?php echo (int)$messageUnreadCount; ?> New</span><?php } ?></a>
        </div>
        <form method="post" action="student-page.php#student-messages" class="student-message-form">
            <label for="message">Write a message</label>
            <textarea id="message" name="message" placeholder="Share a concern, ask for support, or leave an update for the school team." required></textarea>
            <?php if(count($studentMessageTargets) > 1){ ?>
            <label for="message_target">Send To</label>
            <select id="message_target" name="message_target">
                <?php foreach($studentMessageTargets as $targetKey => $targetMeta){ ?>
                <option value="<?php echo sd_esc($targetKey); ?>"<?php echo ((string)$targetKey === (string)$studentDefaultMessageTarget ? " selected" : ""); ?>><?php echo sd_esc($targetMeta['recipient_label']); ?></option>
                <?php } ?>
            </select>
            <div class="student-message-target-preview" data-student-target-preview>
                Sending to: <strong><?php echo sd_esc($studentMessageTargets[$studentDefaultMessageTarget]['recipient_label']); ?></strong>
            </div>
            <?php } ?>
            <div class="student-message-form__actions">
                <span><?php echo (count($studentMessageTargets) > 1) ? "Choose admin, your class teacher, or your house master or mistress before sending." : "Your message will go to admin only."; ?></span>
                <button class="student-primary-btn" type="submit" name="send_message"><i class="fa fa-send"></i> Send Message</button>
            </div>
        </form>

        <div class="student-message-list">
            <?php if(count($myMessages) > 0){ ?>
                <?php foreach($myMessages as $message){ ?>
                <article class="student-message-card">
                    <div class="student-message-card__meta">
                        <span><?php echo sd_esc(sd_date($message['datetimeentry'])); ?><?php echo (trim((string)$message['recipient_label']) !== '' ? ' · To: '.sd_esc((string)$message['recipient_label']) : ''); ?></span>
                        <form method="post" action="student-page.php#student-messages">
                            <input type="hidden" name="messageid" value="<?php echo sd_esc((string)$message['messageid']); ?>">
                            <button type="submit" name="delete_message" class="student-message-delete" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <p><?php echo nl2br(sd_esc($message['messages'])); ?></p>
                </article>
                <?php } ?>
            <?php } else { ?>
            <div class="student-empty-state student-empty-state--compact"><p>No messages yet.</p></div>
            <?php } ?>
        </div>
    </section>
</div>
</main>
<script>
(function(){
    if(typeof Chart !== 'function'){
        return;
    }
    var subjectCanvas = document.getElementById('studentSubjectPerformanceChart');
    var trendCanvas = document.getElementById('studentSubjectTrendChart');
    var subjectLabels = <?php echo json_encode(array_values($studentPerfLabels)); ?>;
    var subjectAverageScores = <?php echo json_encode(array_values($studentPerfAvg)); ?>;
    var subjectPassRates = <?php echo json_encode(array_values($studentPerfPass)); ?>;
    var trendLabels = <?php echo json_encode(array_values($studentPerfTrendLabels)); ?>;
    var trendAverageScores = <?php echo json_encode(array_values($studentPerfTrendAvg)); ?>;
    var trendPassRates = <?php echo json_encode(array_values($studentPerfTrendPass)); ?>;

    if(subjectCanvas && subjectLabels.length > 0){
        new Chart(subjectCanvas, {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [
                    {
                        label: 'Average Score',
                        data: subjectAverageScores,
                        backgroundColor: 'rgba(28, 126, 214, 0.72)',
                        borderColor: '#1c7ed6',
                        borderWidth: 1.2,
                        borderRadius: 10,
                        maxBarThickness: 42
                    },
                    {
                        label: 'Pass Rate',
                        data: subjectPassRates,
                        backgroundColor: 'rgba(14, 163, 140, 0.68)',
                        borderColor: '#0ea38c',
                        borderWidth: 1.2,
                        borderRadius: 10,
                        maxBarThickness: 42
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                return context.dataset.label + ': ' + Number(context.raw).toFixed(2) + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#17324d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value){
                                return value + '%';
                            },
                            color: '#5d748d'
                        },
                        grid: {
                            color: 'rgba(17, 43, 69, 0.08)'
                        }
                    }
                }
            }
        });
    }

    if(trendCanvas && trendLabels.length > 0){
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Average Score',
                        data: trendAverageScores,
                        borderColor: '#1c7ed6',
                        backgroundColor: 'rgba(28, 126, 214, 0.14)',
                        fill: true,
                        tension: 0.28,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Pass Rate',
                        data: trendPassRates,
                        borderColor: '#0ea38c',
                        backgroundColor: 'rgba(14, 163, 140, 0.1)',
                        fill: true,
                        tension: 0.28,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                return context.dataset.label + ': ' + Number(context.raw).toFixed(2) + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#17324d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value){
                                return value + '%';
                            },
                            color: '#5d748d'
                        },
                        grid: {
                            color: 'rgba(17, 43, 69, 0.08)'
                        }
                    }
                }
            }
        });
    }
})();

(function(){
    var select = document.getElementById('message_target');
    var preview = document.querySelector('[data-student-target-preview]');
    if(!select || !preview){
        return;
    }
    var strong = preview.querySelector('strong');
    var syncPreview = function(){
        var text = '';
        if(select.selectedIndex >= 0 && select.options[select.selectedIndex]){
            text = select.options[select.selectedIndex].text;
        }
        if(strong){
            strong.textContent = text;
        }else{
            preview.textContent = text === '' ? '' : 'Sending to: ' + text;
        }
    };
    select.addEventListener('change', syncPreview);
    syncPreview();
})();
</script>
</body>
</html>

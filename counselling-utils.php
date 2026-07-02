<?php
include_once(__DIR__.DIRECTORY_SEPARATOR."house-master-utils.php");
include_once(__DIR__.DIRECTORY_SEPARATOR."semester-registry-utils.php");
include_once(__DIR__.DIRECTORY_SEPARATOR."gradingsystem.php");

if(!function_exists('counselling_is_admin')){
function counselling_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('counselling_is_teacher')){
function counselling_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Teacher';
}
}

if(!function_exists('counselling_is_student')){
function counselling_is_student(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Student';
}
}

if(!function_exists('counselling_landing_page')){
function counselling_landing_page(){
    if(function_exists('house_master_landing_page')){
        return house_master_landing_page();
    }
    return 'index.php';
}
}

if(!function_exists('counselling_esc')){
function counselling_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('counselling_status_meta')){
function counselling_status_meta($status){
    $status = strtolower(trim((string)$status));
    $map = array(
        'pending' => array('label' => 'Pending', 'class' => 'pending'),
        'accepted' => array('label' => 'Accepted', 'class' => 'accepted'),
        'rescheduled' => array('label' => 'Rescheduled', 'class' => 'rescheduled'),
        'completed' => array('label' => 'Completed', 'class' => 'completed'),
        'declined' => array('label' => 'Declined', 'class' => 'declined'),
        'cancelled' => array('label' => 'Cancelled', 'class' => 'cancelled')
    );
    return isset($map[$status]) ? $map[$status] : array('label' => 'Open', 'class' => 'neutral');
}
}

if(!function_exists('counselling_request_active')){
function counselling_request_active($status){
    $status = strtolower(trim((string)$status));
    return in_array($status, array('pending', 'accepted', 'rescheduled'), true);
}
}

if(!function_exists('counselling_term_label')){
function counselling_term_label($termValue){
    $termValue = trim((string)$termValue);
    return $termValue === '' ? 'Semester' : 'Semester '.$termValue;
}
}

if(!function_exists('counselling_person_name')){
function counselling_person_name($row){
    $full = trim(
        (isset($row['firstname']) ? (string)$row['firstname'] : '').' '.
        (isset($row['othernames']) ? (string)$row['othernames'] : '').' '.
        (isset($row['surname']) ? (string)$row['surname'] : '')
    );
    if($full !== ''){
        return $full;
    }
    return isset($row['userid']) ? trim((string)$row['userid']) : '';
}
}

if(!function_exists('counselling_format_datetime')){
function counselling_format_datetime($value){
    $value = trim((string)$value);
    if($value === ''){
        return 'Not set';
    }
    $time = strtotime($value);
    return $time ? date('d M Y, H:i', $time) : $value;
}
}

if(!function_exists('counselling_format_date')){
function counselling_format_date($value){
    $value = trim((string)$value);
    if($value === ''){
        return 'Not set';
    }
    $time = strtotime($value);
    return $time ? date('d M Y', $time) : $value;
}
}

if(!function_exists('counselling_format_time')){
function counselling_format_time($value){
    $value = trim((string)$value);
    if($value === ''){
        return 'Not set';
    }
    $time = strtotime($value);
    return $time ? date('H:i', $time) : $value;
}
}

if(!function_exists('counselling_scope_label')){
function counselling_scope_label($row){
    $type = strtolower(trim((string)(isset($row['assignmenttype']) ? $row['assignmenttype'] : 'class')));
    if($type === 'school'){
        return 'Entire School';
    }
    if($type === 'student'){
        $studentName = isset($row['student_name']) ? trim((string)$row['student_name']) : '';
        $studentId = isset($row['studentid']) ? trim((string)$row['studentid']) : '';
        if($studentName !== ''){
            return $studentName.($studentId !== '' ? ' ('.$studentId.')' : '');
        }
        return $studentId !== '' ? $studentId : 'Student override';
    }
    $className = isset($row['class_name']) ? trim((string)$row['class_name']) : '';
    $batchLabel = isset($row['batch']) ? trim((string)$row['batch']) : '';
    return trim($className.($batchLabel !== '' ? ' · '.$batchLabel : ''));
}
}

if(!function_exists('ensure_counselling_tables')){
function ensure_counselling_tables($con){
    if(!$con){
        return;
    }
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_counselling_v2', 43200)){
        return;
    }

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblcounsellorassignment (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        counsellorid VARCHAR(30) NOT NULL,
        assignmenttype VARCHAR(20) NOT NULL DEFAULT 'class',
        studentid VARCHAR(30) NULL,
        classid VARCHAR(40) NULL,
        batchid VARCHAR(40) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_counsellorassignment_counsellor (counsellorid),
        INDEX idx_counsellorassignment_student (studentid),
        INDEX idx_counsellorassignment_scope (classid, batchid),
        INDEX idx_counsellorassignment_status (status, assignmenttype)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblcounsellingrequest (
        requestid VARCHAR(40) NOT NULL PRIMARY KEY,
        studentid VARCHAR(30) NOT NULL,
        counsellorid VARCHAR(30) NOT NULL,
        assignmentid VARCHAR(40) NULL,
        category VARCHAR(40) NOT NULL DEFAULT 'general',
        sessionmode VARCHAR(20) NOT NULL DEFAULT 'in_person',
        urgency VARCHAR(20) NOT NULL DEFAULT 'normal',
        subjectline VARCHAR(120) NULL,
        concern TEXT NOT NULL,
        preferred_date DATE NULL,
        preferred_time TIME NULL,
        scheduled_date DATE NULL,
        scheduled_time TIME NULL,
        scheduled_endtime TIME NULL,
        meetinglink VARCHAR(255) NULL,
        venue VARCHAR(150) NULL,
        statusnote TEXT NULL,
        counsellorremindersentat DATETIME NULL,
        counsellorreminderstatus VARCHAR(60) NULL,
        counsellorreminderattemptat DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        createdat DATETIME NOT NULL,
        updatedat DATETIME NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_counsellingrequest_student (studentid, status),
        INDEX idx_counsellingrequest_counsellor (counsellorid, status),
        INDEX idx_counsellingrequest_created (createdat),
        INDEX idx_counsellingrequest_schedule (scheduled_date, scheduled_time)
    )");

    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblcounsellingrequest LIKE 'counsellorremindersentat'");
    if(!$columnRes || mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblcounsellingrequest ADD COLUMN counsellorremindersentat DATETIME NULL AFTER statusnote");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblcounsellingrequest LIKE 'counsellorreminderstatus'");
    if(!$columnRes || mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblcounsellingrequest ADD COLUMN counsellorreminderstatus VARCHAR(60) NULL AFTER counsellorremindersentat");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblcounsellingrequest LIKE 'counsellorreminderattemptat'");
    if(!$columnRes || mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblcounsellingrequest ADD COLUMN counsellorreminderattemptat DATETIME NULL AFTER counsellorreminderstatus");
    }

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblcounsellingmessage (
        messageid VARCHAR(40) NOT NULL PRIMARY KEY,
        requestid VARCHAR(40) NOT NULL,
        senderid VARCHAR(30) NOT NULL,
        senderrole VARCHAR(20) NOT NULL,
        messagetext TEXT NOT NULL,
        datetimeentry DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        INDEX idx_counsellingmessage_request (requestid, datetimeentry),
        INDEX idx_counsellingmessage_sender (senderid)
    )");

    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_counselling_v2');
    }
}
}

if(!function_exists('counselling_normalize_phone')){
function counselling_normalize_phone($value){
    $value = trim((string)$value);
    if($value === ''){
        return '';
    }
    return preg_replace('/\s+/', '', $value);
}
}

if(!function_exists('counselling_schedule_datetime')){
function counselling_schedule_datetime($row){
    $dateValue = trim((string)(isset($row['scheduled_date']) ? $row['scheduled_date'] : ''));
    $timeValue = trim((string)(isset($row['scheduled_time']) ? $row['scheduled_time'] : ''));
    if($dateValue === '' || $timeValue === ''){
        return '';
    }
    return $dateValue.' '.$timeValue;
}
}

if(!function_exists('counselling_due_counsellor_sms_rows')){
function counselling_due_counsellor_sms_rows($con, $minutesAhead = 15){
    $rows = array();
    if(!$con){
        return $rows;
    }
    $minutesAhead = max(1, (int)$minutesAhead);
    $windowStart = date('Y-m-d H:i:s', strtotime('-3 minutes'));
    $windowEnd = date('Y-m-d H:i:s', strtotime('+'.$minutesAhead.' minutes'));
    $retryAt = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $windowStartEsc = mysqli_real_escape_string($con, $windowStart);
    $windowEndEsc = mysqli_real_escape_string($con, $windowEnd);
    $retryAtEsc = mysqli_real_escape_string($con, $retryAt);

    $sql = mysqli_query($con, "SELECT
            cr.*,
            student.firstname AS student_firstname,
            student.surname AS student_surname,
            student.othernames AS student_othernames,
            counsellor.firstname AS counsellor_firstname,
            counsellor.surname AS counsellor_surname,
            counsellor.othernames AS counsellor_othernames,
            counsellor.mobile AS counsellor_mobile
        FROM tblcounsellingrequest cr
        INNER JOIN tblsystemuser student ON student.userid=cr.studentid
        INNER JOIN tblsystemuser counsellor ON counsellor.userid=cr.counsellorid
        WHERE cr.status IN ('accepted', 'rescheduled')
          AND cr.scheduled_date IS NOT NULL
          AND cr.scheduled_time IS NOT NULL
          AND cr.counsellorremindersentat IS NULL
          AND (cr.counsellorreminderattemptat IS NULL OR cr.counsellorreminderattemptat <= '$retryAtEsc')
          AND TIMESTAMP(cr.scheduled_date, cr.scheduled_time) BETWEEN '$windowStartEsc' AND '$windowEndEsc'
        ORDER BY TIMESTAMP(cr.scheduled_date, cr.scheduled_time) ASC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $row['student_name'] = trim((string)$row['student_firstname'].' '.(string)$row['student_othernames'].' '.(string)$row['student_surname']);
            $row['counsellor_name'] = trim((string)$row['counsellor_firstname'].' '.(string)$row['counsellor_othernames'].' '.(string)$row['counsellor_surname']);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_mark_counsellor_sms_attempt')){
function counselling_mark_counsellor_sms_attempt($con, $requestId, $status, $sent = false){
    $requestId = trim((string)$requestId);
    if(!$con || $requestId === ''){
        return false;
    }
    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $statusEsc = mysqli_real_escape_string($con, trim((string)$status));
    $updates = array(
        "counsellorreminderattemptat=NOW()",
        "counsellorreminderstatus='$statusEsc'"
    );
    if($sent){
        $updates[] = "counsellorremindersentat=NOW()";
    }
    return mysqli_query($con, "UPDATE tblcounsellingrequest SET ".implode(',', $updates)." WHERE requestid='$requestIdEsc' LIMIT 1") ? true : false;
}
}

if(!function_exists('counselling_counsellor_sms_message')){
function counselling_counsellor_sms_message($requestRow){
    $studentName = trim((string)(isset($requestRow['student_name']) ? $requestRow['student_name'] : 'Student'));
    if($studentName === ''){
        $studentName = 'Student';
    }
    $timeText = counselling_format_time(isset($requestRow['scheduled_time']) ? $requestRow['scheduled_time'] : '');
    $dateText = counselling_format_date(isset($requestRow['scheduled_date']) ? $requestRow['scheduled_date'] : '');
    $modeText = trim((string)(isset($requestRow['sessionmode']) ? $requestRow['sessionmode'] : ''));
    if($modeText === 'in_person'){
        $modeText = 'In-person';
    }elseif($modeText === 'online'){
        $modeText = 'Online';
    }elseif($modeText === 'phone'){
        $modeText = 'Phone';
    }else{
        $modeText = 'Counselling';
    }
    return "Counselling reminder: Your session with ".$studentName." starts at ".$timeText." on ".$dateText.". Mode: ".$modeText.". Please open the counselling dashboard.";
}
}

if(!function_exists('counselling_process_due_reminders')){
function counselling_process_due_reminders($con, $minutesAhead = 15){
    static $done = false;
    $summary = array('processed' => 0, 'sent' => 0, 'failed' => 0, 'no_phone' => 0);
    if($done || !$con){
        return $summary;
    }
    $done = true;

    $rows = counselling_due_counsellor_sms_rows($con, $minutesAhead);
    foreach($rows as $row){
        $summary['processed']++;
        $requestId = trim((string)(isset($row['requestid']) ? $row['requestid'] : ''));
        if($requestId === ''){
            continue;
        }
        $phone = counselling_normalize_phone(isset($row['counsellor_mobile']) ? $row['counsellor_mobile'] : '');
        if($phone === ''){
            counselling_mark_counsellor_sms_attempt($con, $requestId, 'NO_PHONE', true);
            $summary['no_phone']++;
            continue;
        }
        $message = counselling_counsellor_sms_message($row);
        $resultCode = '';
        $sent = function_exists('send_bulk_sms_message') ? send_bulk_sms_message($phone, $message, $resultCode) : false;
        if($sent){
            counselling_mark_counsellor_sms_attempt($con, $requestId, trim((string)$resultCode) !== '' ? trim((string)$resultCode) : 'SENT', true);
            $summary['sent']++;
        }else{
            counselling_mark_counsellor_sms_attempt($con, $requestId, trim((string)$resultCode) !== '' ? trim((string)$resultCode) : 'FAILED', false);
            $summary['failed']++;
        }
    }

    return $summary;
}
}

if(!function_exists('counselling_class_scope_context')){
function counselling_class_scope_context($con, $studentId){
    $studentId = trim((string)$studentId);
    if(!$con || $studentId === ''){
        return null;
    }

    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $scopeSql = mysqli_query($con, "SELECT
            cl.class_entryid AS classid,
            cl.batchid,
            cl.datetimeentry,
            ce.class_name,
            bh.batch
        FROM tblclass cl
        LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
        WHERE cl.userid='$studentIdEsc'
          AND cl.status='active'
        ORDER BY cl.datetimeentry DESC
        LIMIT 1");
    if($scopeSql && ($row = mysqli_fetch_array($scopeSql, MYSQLI_ASSOC))){
        return $row;
    }

    $fallbackSql = mysqli_query($con, "SELECT
            tr.class_entryid AS classid,
            tr.batchid,
            tr.datetimeentry,
            ce.class_name,
            bh.batch
        FROM tbltermregistry tr
        LEFT JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
        WHERE tr.userid='$studentIdEsc'
        ORDER BY tr.datetimeentry DESC, tr.termname DESC
        LIMIT 1");
    if($fallbackSql && ($row = mysqli_fetch_array($fallbackSql, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('counselling_school_assignment_row')){
function counselling_school_assignment_row($con){
    if(!$con){
        return null;
    }
    $sql = mysqli_query($con, "SELECT
            ca.*,
            su.userid,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblcounsellorassignment ca
        INNER JOIN tblsystemuser su ON su.userid=ca.counsellorid
        WHERE ca.assignmenttype='school'
          AND ca.status='active'
          AND su.status='active'
        ORDER BY ca.datetimeentry DESC
        LIMIT 1");
    if($sql && ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('counselling_resolve_student_assignment')){
function counselling_resolve_student_assignment($con, $studentId){
    $studentId = trim((string)$studentId);
    if(!$con || $studentId === ''){
        return null;
    }

    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $directSql = mysqli_query($con, "SELECT
            ca.*,
            su.userid,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblcounsellorassignment ca
        INNER JOIN tblsystemuser su ON su.userid=ca.counsellorid
        WHERE ca.assignmenttype='student'
          AND ca.studentid='$studentIdEsc'
          AND ca.status='active'
          AND su.status='active'
        ORDER BY ca.datetimeentry DESC
        LIMIT 1");
    if($directSql && ($row = mysqli_fetch_array($directSql, MYSQLI_ASSOC))){
        $row['student_scope'] = counselling_class_scope_context($con, $studentId);
        return $row;
    }

    $scope = counselling_class_scope_context($con, $studentId);
    if($scope){
        $classIdEsc = mysqli_real_escape_string($con, trim((string)$scope['classid']));
        $batchIdEsc = mysqli_real_escape_string($con, trim((string)$scope['batchid']));
        $classSql = mysqli_query($con, "SELECT
                ca.*,
                su.userid,
                su.firstname,
                su.surname,
                su.othernames
            FROM tblcounsellorassignment ca
            INNER JOIN tblsystemuser su ON su.userid=ca.counsellorid
            WHERE ca.assignmenttype='class'
              AND ca.classid='$classIdEsc'
              AND ca.batchid='$batchIdEsc'
              AND ca.status='active'
              AND su.status='active'
            ORDER BY ca.datetimeentry DESC
            LIMIT 1");
        if($classSql && ($row = mysqli_fetch_array($classSql, MYSQLI_ASSOC))){
            $row['student_scope'] = $scope;
            return $row;
        }
    }

    $schoolRow = counselling_school_assignment_row($con);
    if($schoolRow){
        $schoolRow['student_scope'] = $scope;
        return $schoolRow;
    }
    return null;
}
}

if(!function_exists('counselling_teacher_has_assignment')){
function counselling_teacher_has_assignment($con, $teacherId){
    $teacherId = trim((string)$teacherId);
    if(!$con || $teacherId === ''){
        return false;
    }
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $sql = mysqli_query($con, "SELECT assignmentid
        FROM tblcounsellorassignment
        WHERE counsellorid='$teacherIdEsc'
          AND status='active'
        LIMIT 1");
    return ($sql && mysqli_num_rows($sql) > 0);
}
}

if(!function_exists('counselling_teacher_can_manage_student')){
function counselling_teacher_can_manage_student($con, $teacherId, $studentId){
    $teacherId = trim((string)$teacherId);
    $studentId = trim((string)$studentId);
    if(!$con || $teacherId === '' || $studentId === ''){
        return false;
    }
    $assignment = counselling_resolve_student_assignment($con, $studentId);
    return $assignment && trim((string)(isset($assignment['counsellorid']) ? $assignment['counsellorid'] : '')) === $teacherId;
}
}

if(!function_exists('counselling_counsellor_student_rows')){
function counselling_counsellor_student_rows($con, $teacherId){
    $rows = array();
    $teacherId = trim((string)$teacherId);
    if(!$con || $teacherId === ''){
        return $rows;
    }

    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $schoolAssigned = false;
    $schoolSql = mysqli_query($con, "SELECT assignmentid
        FROM tblcounsellorassignment
        WHERE counsellorid='$teacherIdEsc'
          AND assignmenttype='school'
          AND status='active'
        LIMIT 1");
    if($schoolSql && mysqli_num_rows($schoolSql) > 0){
        $schoolAssigned = true;
    }

    if($schoolAssigned){
        $sql = mysqli_query($con, "SELECT
                su.userid,
                su.firstname,
                su.surname,
                su.othernames,
                (
                    SELECT ce.class_name
                    FROM tblclass cl
                    LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
                    WHERE cl.userid=su.userid
                      AND cl.status='active'
                    ORDER BY cl.datetimeentry DESC
                    LIMIT 1
                ) AS class_name,
                (
                    SELECT bh.batch
                    FROM tblclass cl
                    LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
                    WHERE cl.userid=su.userid
                      AND cl.status='active'
                    ORDER BY cl.datetimeentry DESC
                    LIMIT 1
                ) AS batch
            FROM tblsystemuser su
            WHERE su.systemtype='Student'
              AND su.status='active'
            ORDER BY su.surname ASC, su.firstname ASC, su.othernames ASC, su.userid ASC");
    }else{
        $sql = mysqli_query($con, "SELECT DISTINCT
                su.userid,
                su.firstname,
                su.surname,
                su.othernames,
                (
                    SELECT ce.class_name
                    FROM tblclass cl
                    LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
                    WHERE cl.userid=su.userid
                      AND cl.status='active'
                    ORDER BY cl.datetimeentry DESC
                    LIMIT 1
                ) AS class_name,
                (
                    SELECT bh.batch
                    FROM tblclass cl
                    LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
                    WHERE cl.userid=su.userid
                      AND cl.status='active'
                    ORDER BY cl.datetimeentry DESC
                    LIMIT 1
                ) AS batch
            FROM tblsystemuser su
            WHERE su.systemtype='Student'
              AND su.status='active'
              AND (
                    su.userid IN (
                        SELECT ca.studentid
                        FROM tblcounsellorassignment ca
                        WHERE ca.counsellorid='$teacherIdEsc'
                          AND ca.assignmenttype='student'
                          AND ca.status='active'
                          AND ca.studentid IS NOT NULL
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM tblclass cl
                        INNER JOIN tblcounsellorassignment ca2
                            ON ca2.classid=cl.class_entryid
                           AND ca2.batchid=cl.batchid
                           AND ca2.assignmenttype='class'
                           AND ca2.counsellorid='$teacherIdEsc'
                           AND ca2.status='active'
                        WHERE cl.userid=su.userid
                          AND cl.status='active'
                    )
              )
            ORDER BY su.surname ASC, su.firstname ASC, su.othernames ASC, su.userid ASC");
    }

    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_teacher_assignment_summary')){
function counselling_teacher_assignment_summary($con, $teacherId){
    $summary = array(
        'school_scope_count' => 0,
        'class_scope_count' => 0,
        'student_override_count' => 0,
        'total_scope_count' => 0,
        'pending_count' => 0
    );
    $teacherId = trim((string)$teacherId);
    if(!$con || $teacherId === ''){
        return $summary;
    }
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $sql = mysqli_query($con, "SELECT
            SUM(CASE WHEN assignmenttype='school' AND status='active' THEN 1 ELSE 0 END) AS school_scope_count,
            SUM(CASE WHEN assignmenttype='class' AND status='active' THEN 1 ELSE 0 END) AS class_scope_count,
            SUM(CASE WHEN assignmenttype='student' AND status='active' THEN 1 ELSE 0 END) AS student_override_count
        FROM tblcounsellorassignment
        WHERE counsellorid='$teacherIdEsc'");
    if($sql && ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))){
        $summary['school_scope_count'] = (int)$row['school_scope_count'];
        $summary['class_scope_count'] = (int)$row['class_scope_count'];
        $summary['student_override_count'] = (int)$row['student_override_count'];
        $summary['total_scope_count'] = $summary['school_scope_count'] + $summary['class_scope_count'] + $summary['student_override_count'];
    }
    $pendingSql = mysqli_query($con, "SELECT COUNT(*) AS total_pending
        FROM tblcounsellingrequest
        WHERE counsellorid='$teacherIdEsc'
          AND status='pending'");
    if($pendingSql && ($row = mysqli_fetch_array($pendingSql, MYSQLI_ASSOC))){
        $summary['pending_count'] = (int)$row['total_pending'];
    }
    return $summary;
}
}

if(!function_exists('counselling_assignment_rows')){
function counselling_assignment_rows($con){
    $rows = array();
    if(!$con){
        return $rows;
    }
    $sql = mysqli_query($con, "SELECT
            ca.*,
            counsellor.firstname AS counsellor_firstname,
            counsellor.surname AS counsellor_surname,
            counsellor.othernames AS counsellor_othernames,
            student.firstname AS student_firstname,
            student.surname AS student_surname,
            student.othernames AS student_othernames,
            ce.class_name,
            bh.batch
        FROM tblcounsellorassignment ca
        INNER JOIN tblsystemuser counsellor ON counsellor.userid=ca.counsellorid
        LEFT JOIN tblsystemuser student ON student.userid=ca.studentid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=ca.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ca.batchid
        ORDER BY ca.datetimeentry DESC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $row['counsellor_name'] = trim($row['counsellor_firstname'].' '.$row['counsellor_othernames'].' '.$row['counsellor_surname']);
            $row['student_name'] = trim($row['student_firstname'].' '.$row['student_othernames'].' '.$row['student_surname']);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_student_request_rows')){
function counselling_student_request_rows($con, $studentId){
    $rows = array();
    $studentId = trim((string)$studentId);
    if(!$con || $studentId === ''){
        return $rows;
    }
    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $sql = mysqli_query($con, "SELECT
            cr.*,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblcounsellingrequest cr
        INNER JOIN tblsystemuser su ON su.userid=cr.counsellorid
        WHERE cr.studentid='$studentIdEsc'
        ORDER BY cr.createdat DESC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_counsellor_request_rows')){
function counselling_counsellor_request_rows($con, $teacherId){
    $rows = array();
    $teacherId = trim((string)$teacherId);
    if(!$con || $teacherId === ''){
        return $rows;
    }
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $sql = mysqli_query($con, "SELECT
            cr.*,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblcounsellingrequest cr
        INNER JOIN tblsystemuser su ON su.userid=cr.studentid
        WHERE cr.counsellorid='$teacherIdEsc'
        ORDER BY
            CASE cr.status
                WHEN 'pending' THEN 1
                WHEN 'accepted' THEN 2
                WHEN 'rescheduled' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'declined' THEN 5
                ELSE 6
            END ASC,
            cr.createdat DESC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_counsellor_timetable_rows')){
function counselling_counsellor_timetable_rows($con, $teacherId, $fromDate, $toDate = ''){
    $rows = array();
    $teacherId = trim((string)$teacherId);
    $fromDate = trim((string)$fromDate);
    $toDate = trim((string)$toDate);
    if(!$con || $teacherId === '' || $fromDate === ''){
        return $rows;
    }
    if($toDate === ''){
        $toDate = $fromDate;
    }

    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $fromDateEsc = mysqli_real_escape_string($con, $fromDate);
    $toDateEsc = mysqli_real_escape_string($con, $toDate);

    $sql = mysqli_query($con, "SELECT
            cr.*,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblcounsellingrequest cr
        INNER JOIN tblsystemuser su ON su.userid=cr.studentid
        WHERE cr.counsellorid='$teacherIdEsc'
          AND cr.scheduled_date IS NOT NULL
          AND cr.scheduled_date BETWEEN '$fromDateEsc' AND '$toDateEsc'
          AND cr.status NOT IN ('declined', 'cancelled')
        ORDER BY
            cr.scheduled_date ASC,
            COALESCE(cr.scheduled_time, '23:59:59') ASC,
            CASE cr.status
                WHEN 'accepted' THEN 1
                WHEN 'rescheduled' THEN 2
                WHEN 'pending' THEN 3
                WHEN 'completed' THEN 4
                ELSE 5
            END ASC,
            cr.createdat ASC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_dashboard_notification_rows')){
function counselling_dashboard_notification_rows($con, $userId, $role, $minutesAhead = 15){
    $rows = array();
    $userId = trim((string)$userId);
    $role = strtolower(trim((string)$role));
    if(!$con || $userId === '' || !in_array($role, array('teacher', 'student'), true)){
        return $rows;
    }

    $minutesAhead = max(1, (int)$minutesAhead);
    $windowStart = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $windowEnd = date('Y-m-d H:i:s', strtotime('+'.$minutesAhead.' minutes'));
    $windowStartEsc = mysqli_real_escape_string($con, $windowStart);
    $windowEndEsc = mysqli_real_escape_string($con, $windowEnd);
    $userIdEsc = mysqli_real_escape_string($con, $userId);

    if($role === 'teacher'){
        $sql = mysqli_query($con, "SELECT
                cr.*,
                su.firstname,
                su.surname,
                su.othernames
            FROM tblcounsellingrequest cr
            INNER JOIN tblsystemuser su ON su.userid=cr.studentid
            WHERE cr.counsellorid='$userIdEsc'
              AND cr.status IN ('accepted', 'rescheduled')
              AND cr.scheduled_date IS NOT NULL
              AND cr.scheduled_time IS NOT NULL
              AND TIMESTAMP(cr.scheduled_date, cr.scheduled_time) BETWEEN '$windowStartEsc' AND '$windowEndEsc'
            ORDER BY TIMESTAMP(cr.scheduled_date, cr.scheduled_time) ASC");
    }else{
        $sql = mysqli_query($con, "SELECT
                cr.*,
                su.firstname,
                su.surname,
                su.othernames
            FROM tblcounsellingrequest cr
            INNER JOIN tblsystemuser su ON su.userid=cr.counsellorid
            WHERE cr.studentid='$userIdEsc'
              AND cr.status IN ('accepted', 'rescheduled')
              AND cr.scheduled_date IS NOT NULL
              AND cr.scheduled_time IS NOT NULL
              AND TIMESTAMP(cr.scheduled_date, cr.scheduled_time) BETWEEN '$windowStartEsc' AND '$windowEndEsc'
            ORDER BY TIMESTAMP(cr.scheduled_date, cr.scheduled_time) ASC");
    }

    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_request_row')){
function counselling_request_row($con, $requestId){
    $requestId = trim((string)$requestId);
    if(!$con || $requestId === ''){
        return null;
    }
    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $sql = mysqli_query($con, "SELECT
            cr.*,
            student.firstname AS student_firstname,
            student.surname AS student_surname,
            student.othernames AS student_othernames,
            counsellor.firstname AS counsellor_firstname,
            counsellor.surname AS counsellor_surname,
            counsellor.othernames AS counsellor_othernames
        FROM tblcounsellingrequest cr
        INNER JOIN tblsystemuser student ON student.userid=cr.studentid
        INNER JOIN tblsystemuser counsellor ON counsellor.userid=cr.counsellorid
        WHERE cr.requestid='$requestIdEsc'
        LIMIT 1");
    if($sql && ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))){
        $row['student_name'] = trim($row['student_firstname'].' '.$row['student_othernames'].' '.$row['student_surname']);
        $row['counsellor_name'] = trim($row['counsellor_firstname'].' '.$row['counsellor_othernames'].' '.$row['counsellor_surname']);
        return $row;
    }
    return null;
}
}

if(!function_exists('counselling_user_can_view_request')){
function counselling_user_can_view_request($con, $requestId, $userId, $role){
    $request = counselling_request_row($con, $requestId);
    if(!$request){
        return false;
    }
    $role = trim((string)$role);
    $userId = trim((string)$userId);
    if($role === 'student'){
        return trim((string)$request['studentid']) === $userId;
    }
    if($role === 'teacher'){
        return trim((string)$request['counsellorid']) === $userId;
    }
    return false;
}
}

if(!function_exists('counselling_thread_rows')){
function counselling_thread_rows($con, $requestId){
    $rows = array();
    $requestId = trim((string)$requestId);
    if(!$con || $requestId === ''){
        return $rows;
    }
    $requestIdEsc = mysqli_real_escape_string($con, $requestId);
    $sql = mysqli_query($con, "SELECT *
        FROM tblcounsellingmessage
        WHERE requestid='$requestIdEsc'
          AND status='active'
        ORDER BY datetimeentry ASC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('counselling_case_counts')){
function counselling_case_counts($rows){
    $counts = array(
        'pending' => 0,
        'accepted' => 0,
        'rescheduled' => 0,
        'completed' => 0,
        'declined' => 0,
        'cancelled' => 0
    );
    foreach($rows as $row){
        $status = strtolower(trim((string)(isset($row['status']) ? $row['status'] : 'pending')));
        if(isset($counts[$status])){
            $counts[$status]++;
        }
    }
    return $counts;
}
}

if(!function_exists('counselling_number')){
function counselling_number($value){
    return is_numeric($value) ? (float)$value : 0.0;
}
}

if(!function_exists('counselling_grade_for_total')){
function counselling_grade_for_total($value){
    static $grading = null;
    if($grading === null){
        $grading = new GradingSystem();
    }
    $grading->setMark((float)$value);
    $grade = trim((string)$grading->getMark());
    return $grade !== '' ? $grade : 'N/A';
}
}

if(!function_exists('counselling_year_label')){
function counselling_year_label($value, $fallbackDate = ''){
    $value = trim((string)$value);
    $normalized = function_exists('semester_registry_normalize_year')
        ? semester_registry_normalize_year($value)
        : $value;
    if($normalized !== ''){
        return $normalized;
    }
    if($fallbackDate !== ''){
        $time = strtotime($fallbackDate);
        if($time){
            return date('Y', $time);
        }
    }
    return $value !== '' ? $value : 'Unknown';
}
}

if(!function_exists('counselling_academic_session_key')){
function counselling_academic_session_key($academicYear, $batchId, $termName, $classId){
    return trim((string)$academicYear).'|'.trim((string)$batchId).'|'.trim((string)$termName).'|'.trim((string)$classId);
}
}

if(!function_exists('counselling_academic_find_session_by_scope')){
function counselling_academic_find_session_by_scope($sessions, $batchId, $termName, $classId = '', $className = '', $academicYear = ''){
    $batchId = trim((string)$batchId);
    $termName = trim((string)$termName);
    $classId = trim((string)$classId);
    $className = trim((string)$className);
    $academicYear = trim((string)$academicYear);

    foreach($sessions as $key => $session){
        if(trim((string)$session['batchid']) !== $batchId){
            continue;
        }
        if(trim((string)$session['termname']) !== $termName){
            continue;
        }
        if($academicYear !== '' && trim((string)$session['academic_year']) !== $academicYear){
            continue;
        }
        if($classId !== '' && trim((string)$session['class_entryid']) === $classId){
            return $key;
        }
        if($className !== '' && trim((string)$session['class_name']) === $className){
            return $key;
        }
        if($classId === '' && $className === ''){
            return $key;
        }
    }
    return '';
}
}

if(!function_exists('counselling_academic_subject_index')){
function counselling_academic_subject_index($subjects, $subjectId, $subjectName){
    $subjectId = trim((string)$subjectId);
    $subjectName = trim((string)$subjectName);
    foreach($subjects as $index => $subjectRow){
        $existingId = trim((string)(isset($subjectRow['subjectid']) ? $subjectRow['subjectid'] : ''));
        $existingName = trim((string)(isset($subjectRow['subject']) ? $subjectRow['subject'] : ''));
        if($subjectId !== '' && $existingId === $subjectId){
            return $index;
        }
        if($subjectId === '' && $subjectName !== '' && $existingName === $subjectName){
            return $index;
        }
    }
    return -1;
}
}

if(!function_exists('counselling_academic_ensure_session')){
function counselling_academic_ensure_session(&$sessions, $academicYear, $batchId, $termName, $classId, $seed = array()){
    $academicYear = trim((string)$academicYear);
    $batchId = trim((string)$batchId);
    $termName = trim((string)$termName);
    $classId = trim((string)$classId);
    $key = counselling_academic_session_key($academicYear, $batchId, $termName, $classId);

    if(!isset($sessions[$key])){
        $sessions[$key] = array(
            'session_key' => $key,
            'academic_year' => $academicYear,
            'batchid' => $batchId,
            'batch' => '',
            'termname' => $termName,
            'class_entryid' => $classId,
            'class_name' => '',
            'registered_on' => '',
            'last_updated' => '',
            'subjects' => array(),
            'remarks' => null,
            'average_score' => null,
            'pass_count' => 0,
            'subject_count' => 0
        );
    }
    foreach($seed as $field => $value){
        if(!array_key_exists($field, $sessions[$key])){
            continue;
        }
        if($sessions[$key][$field] === '' || $sessions[$key][$field] === null){
            $sessions[$key][$field] = $value;
        }
    }
    return $key;
}
}

if(!function_exists('counselling_sort_academic_sessions')){
function counselling_sort_academic_sessions($sessions){
    usort($sessions, function($left, $right){
        $leftYear = trim((string)(isset($left['academic_year']) ? $left['academic_year'] : ''));
        $rightYear = trim((string)(isset($right['academic_year']) ? $right['academic_year'] : ''));
        if($leftYear !== $rightYear){
            return strcmp($rightYear, $leftYear);
        }
        $leftTerm = (int)(isset($left['termname']) ? $left['termname'] : 0);
        $rightTerm = (int)(isset($right['termname']) ? $right['termname'] : 0);
        if($leftTerm !== $rightTerm){
            return ($rightTerm <=> $leftTerm);
        }
        $leftUpdated = strtotime((string)(isset($left['last_updated']) ? $left['last_updated'] : '')) ?: 0;
        $rightUpdated = strtotime((string)(isset($right['last_updated']) ? $right['last_updated'] : '')) ?: 0;
        return ($rightUpdated <=> $leftUpdated);
    });
    return $sessions;
}
}

if(!function_exists('counselling_student_academic_profile')){
function counselling_student_academic_profile($con, $studentId){
    $profile = array(
        'summary' => array(
            'session_count' => 0,
            'subject_count' => 0,
            'pass_count' => 0,
            'average_score' => null,
            'pass_rate' => null,
            'latest_session' => 'Not available'
        ),
        'sessions' => array()
    );

    $studentId = trim((string)$studentId);
    if(!$con || $studentId === ''){
        return $profile;
    }

    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $sessions = array();

    $termYearSql = semester_registry_resolved_year_sql('tr');
    $termSql = mysqli_query($con, "SELECT
            tr.class_entryid,
            tr.termname,
            tr.batchid,
            tr.datetimeentry AS registered_on,
            $termYearSql AS academic_year,
            ce.class_name,
            bh.batch
        FROM tbltermregistry tr
        LEFT JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
        WHERE tr.userid='$studentIdEsc'
        ORDER BY academic_year ASC, tr.termname ASC, tr.datetimeentry ASC");
    if($termSql){
        while($row = mysqli_fetch_array($termSql, MYSQLI_ASSOC)){
            $academicYear = counselling_year_label(isset($row['academic_year']) ? $row['academic_year'] : '', isset($row['registered_on']) ? $row['registered_on'] : '');
            counselling_academic_ensure_session($sessions, $academicYear, $row['batchid'], $row['termname'], $row['class_entryid'], array(
                'academic_year' => $academicYear,
                'batch' => isset($row['batch']) ? $row['batch'] : '',
                'class_name' => isset($row['class_name']) ? $row['class_name'] : '',
                'registered_on' => isset($row['registered_on']) ? $row['registered_on'] : ''
            ));
        }
    }

    $historySemesterRes = @mysqli_query($con, "SELECT * FROM vw_student_semester_history WHERE userid='$studentIdEsc' ORDER BY batch, semester, semester_registered_on");
    if($historySemesterRes){
        while($row = mysqli_fetch_array($historySemesterRes, MYSQLI_ASSOC)){
            $registeredOn = '';
            if(isset($row['semester_registered_on']) && trim((string)$row['semester_registered_on']) !== ''){
                $registeredOn = $row['semester_registered_on'];
            }elseif(isset($row['class_registered_on']) && trim((string)$row['class_registered_on']) !== ''){
                $registeredOn = $row['class_registered_on'];
            }

            $sessionKey = counselling_academic_find_session_by_scope(
                $sessions,
                isset($row['batchid']) ? $row['batchid'] : '',
                isset($row['semester']) ? $row['semester'] : '',
                '',
                isset($row['class_name']) ? $row['class_name'] : ''
            );
            if($sessionKey === ''){
                $academicYear = counselling_year_label('', $registeredOn);
                counselling_academic_ensure_session($sessions, $academicYear, $row['batchid'], $row['semester'], '', array(
                    'academic_year' => $academicYear,
                    'batch' => isset($row['batch']) ? $row['batch'] : '',
                    'class_name' => isset($row['class_name']) ? $row['class_name'] : '',
                    'registered_on' => $registeredOn
                ));
            }
        }
    }

    $marksSql = mysqli_query($con, "SELECT
            sa.batchid,
            bh.batch,
            sa.termname,
            sa.classid AS class_entryid,
            ce.class_name,
            sub.subjectid,
            sub.subject,
            DATE_FORMAT(MAX(sa.datetimeentry), '%Y') AS assignment_year,
            ROUND(SUM(CASE WHEN mk.testtype='Class Score' THEN mk.mark ELSE 0 END), 2) AS class_score,
            ROUND(SUM(CASE WHEN mk.testtype='Exam Score' THEN mk.mark ELSE 0 END), 2) AS exam_score,
            ROUND(SUM(CASE WHEN mk.testtype IN ('Class Score','Exam Score') THEN mk.mark ELSE 0 END), 2) AS total_score,
            ROUND(SUM(CASE WHEN mk.testtype='Class Score' THEN mk.totalmark ELSE 0 END), 2) AS class_total_mark,
            ROUND(SUM(CASE WHEN mk.testtype='Exam Score' THEN mk.totalmark ELSE 0 END), 2) AS exam_total_mark,
            MAX(CASE WHEN mk.testtype='Class Score' THEN 1 ELSE 0 END) AS has_class_score,
            MAX(CASE WHEN mk.testtype='Exam Score' THEN 1 ELSE 0 END) AS has_exam_score,
            MAX(mk.datetimeentry) AS last_updated
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
        INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        LEFT JOIN tblbatch bh ON bh.batchid=sa.batchid
        WHERE mk.userid='$studentIdEsc'
          AND mk.status='active'
        GROUP BY sa.batchid, bh.batch, sa.termname, sa.classid, ce.class_name, sub.subjectid, sub.subject
        ORDER BY assignment_year ASC, sa.termname ASC, sub.subject ASC");
    if($marksSql){
        while($row = mysqli_fetch_array($marksSql, MYSQLI_ASSOC)){
            $sessionKey = counselling_academic_find_session_by_scope(
                $sessions,
                isset($row['batchid']) ? $row['batchid'] : '',
                isset($row['termname']) ? $row['termname'] : '',
                isset($row['class_entryid']) ? $row['class_entryid'] : '',
                isset($row['class_name']) ? $row['class_name'] : ''
            );
            if($sessionKey === ''){
                $academicYear = counselling_year_label(isset($row['assignment_year']) ? $row['assignment_year'] : '', isset($row['last_updated']) ? $row['last_updated'] : '');
                $sessionKey = counselling_academic_ensure_session($sessions, $academicYear, $row['batchid'], $row['termname'], $row['class_entryid'], array(
                    'academic_year' => $academicYear,
                    'batch' => isset($row['batch']) ? $row['batch'] : '',
                    'class_name' => isset($row['class_name']) ? $row['class_name'] : '',
                    'last_updated' => isset($row['last_updated']) ? $row['last_updated'] : ''
                ));
            }

            $subjectIndex = counselling_academic_subject_index(
                $sessions[$sessionKey]['subjects'],
                isset($row['subjectid']) ? $row['subjectid'] : '',
                isset($row['subject']) ? $row['subject'] : ''
            );
            $totalScore = counselling_number($row['total_score']);
            $payload = array(
                'subject' => isset($row['subject']) ? $row['subject'] : '',
                'subjectid' => isset($row['subjectid']) ? $row['subjectid'] : '',
                'class_score' => (int)$row['has_class_score'] === 1 ? counselling_number($row['class_score']) : null,
                'exam_score' => (int)$row['has_exam_score'] === 1 ? counselling_number($row['exam_score']) : null,
                'total_score' => $totalScore,
                'class_total_mark' => counselling_number($row['class_total_mark']),
                'exam_total_mark' => counselling_number($row['exam_total_mark']),
                'grade' => counselling_grade_for_total($totalScore),
                'passed' => ($totalScore >= 50)
            );
            if($subjectIndex >= 0){
                $sessions[$sessionKey]['subjects'][$subjectIndex] = $payload;
            }else{
                $sessions[$sessionKey]['subjects'][] = $payload;
            }
            if(trim((string)$sessions[$sessionKey]['last_updated']) === '' && trim((string)$row['last_updated']) !== ''){
                $sessions[$sessionKey]['last_updated'] = $row['last_updated'];
            }
        }
    }

    $historyResultsSql = @mysqli_query($con, "SELECT
            batchid,
            batch,
            semester,
            class_name,
            subjectid,
            subject,
            ROUND(SUM(CASE WHEN testtype='Class Score' THEN mark ELSE 0 END), 2) AS class_score,
            ROUND(SUM(CASE WHEN testtype='Exam Score' THEN mark ELSE 0 END), 2) AS exam_score,
            ROUND(SUM(CASE WHEN testtype IN ('Class Score','Exam Score') THEN mark ELSE 0 END), 2) AS total_score,
            ROUND(SUM(CASE WHEN testtype='Class Score' THEN totalmark ELSE 0 END), 2) AS class_total_mark,
            ROUND(SUM(CASE WHEN testtype='Exam Score' THEN totalmark ELSE 0 END), 2) AS exam_total_mark,
            MAX(CASE WHEN testtype='Class Score' THEN 1 ELSE 0 END) AS has_class_score,
            MAX(CASE WHEN testtype='Exam Score' THEN 1 ELSE 0 END) AS has_exam_score,
            MAX(datetimeentry) AS last_updated
        FROM vw_student_results_history
        WHERE userid='$studentIdEsc'
        GROUP BY batchid, batch, semester, class_name, subjectid, subject
        ORDER BY batch, semester, subject");
    if($historyResultsSql){
        while($row = mysqli_fetch_array($historyResultsSql, MYSQLI_ASSOC)){
            $sessionKey = counselling_academic_find_session_by_scope(
                $sessions,
                isset($row['batchid']) ? $row['batchid'] : '',
                isset($row['semester']) ? $row['semester'] : '',
                '',
                isset($row['class_name']) ? $row['class_name'] : ''
            );
            if($sessionKey === ''){
                $academicYear = counselling_year_label('', isset($row['last_updated']) ? $row['last_updated'] : '');
                $sessionKey = counselling_academic_ensure_session($sessions, $academicYear, $row['batchid'], $row['semester'], '', array(
                    'academic_year' => $academicYear,
                    'batch' => isset($row['batch']) ? $row['batch'] : '',
                    'class_name' => isset($row['class_name']) ? $row['class_name'] : '',
                    'last_updated' => isset($row['last_updated']) ? $row['last_updated'] : ''
                ));
            }
            $subjectIndex = counselling_academic_subject_index(
                $sessions[$sessionKey]['subjects'],
                isset($row['subjectid']) ? $row['subjectid'] : '',
                isset($row['subject']) ? $row['subject'] : ''
            );
            $totalScore = counselling_number($row['total_score']);
            $payload = array(
                'subject' => isset($row['subject']) ? $row['subject'] : '',
                'subjectid' => isset($row['subjectid']) ? $row['subjectid'] : '',
                'class_score' => (int)$row['has_class_score'] === 1 ? counselling_number($row['class_score']) : null,
                'exam_score' => (int)$row['has_exam_score'] === 1 ? counselling_number($row['exam_score']) : null,
                'total_score' => $totalScore,
                'class_total_mark' => counselling_number($row['class_total_mark']),
                'exam_total_mark' => counselling_number($row['exam_total_mark']),
                'grade' => counselling_grade_for_total($totalScore),
                'passed' => ($totalScore >= 50)
            );
            if($subjectIndex >= 0){
                $existing = $sessions[$sessionKey]['subjects'][$subjectIndex];
                if($existing['class_score'] === null && $payload['class_score'] !== null){
                    $existing['class_score'] = $payload['class_score'];
                }
                if($existing['exam_score'] === null && $payload['exam_score'] !== null){
                    $existing['exam_score'] = $payload['exam_score'];
                }
                if(counselling_number($existing['total_score']) <= 0 && counselling_number($payload['total_score']) > 0){
                    $existing['total_score'] = $payload['total_score'];
                    $existing['grade'] = $payload['grade'];
                    $existing['passed'] = $payload['passed'];
                }
                $sessions[$sessionKey]['subjects'][$subjectIndex] = $existing;
            }else{
                $sessions[$sessionKey]['subjects'][] = $payload;
            }
        }
    }

    $terminalYearSql = semester_registry_resolved_year_sql('tr2');
    $remarksSql = mysqli_query($con, "SELECT
            str.batchid,
            str.termname,
            str.roll,
            str.attendance,
            str.totalattendance,
            str.promotedto,
            str.conduct,
            str.interest,
            str.class_teacher_remark,
            str.head_teacher_remark,
            str.status,
            str.datetimeentry,
            bh.batch,
            nextce.class_name AS promoted_class_name,
            (
                SELECT $terminalYearSql
                FROM tbltermregistry tr2
                WHERE tr2.userid=str.userid
                  AND tr2.batchid=str.batchid
                  AND tr2.termname=str.termname
                ORDER BY tr2.datetimeentry DESC
                LIMIT 1
            ) AS academic_year,
            (
                SELECT tr2.class_entryid
                FROM tbltermregistry tr2
                WHERE tr2.userid=str.userid
                  AND tr2.batchid=str.batchid
                  AND tr2.termname=str.termname
                ORDER BY tr2.datetimeentry DESC
                LIMIT 1
            ) AS class_entryid,
            (
                SELECT ce2.class_name
                FROM tbltermregistry tr2
                INNER JOIN tblclassentry ce2 ON ce2.class_entryid=tr2.class_entryid
                WHERE tr2.userid=str.userid
                  AND tr2.batchid=str.batchid
                  AND tr2.termname=str.termname
                ORDER BY tr2.datetimeentry DESC
                LIMIT 1
            ) AS class_name
        FROM tblstudentterminalreport str
        LEFT JOIN tblbatch bh ON bh.batchid=str.batchid
        LEFT JOIN tblclassentry nextce ON nextce.class_entryid=str.promotedto
        WHERE str.userid='$studentIdEsc'
        ORDER BY str.datetimeentry DESC");
    if($remarksSql){
        while($row = mysqli_fetch_array($remarksSql, MYSQLI_ASSOC)){
            $academicYear = counselling_year_label(isset($row['academic_year']) ? $row['academic_year'] : '', isset($row['datetimeentry']) ? $row['datetimeentry'] : '');
            $sessionKey = counselling_academic_find_session_by_scope(
                $sessions,
                isset($row['batchid']) ? $row['batchid'] : '',
                isset($row['termname']) ? $row['termname'] : '',
                isset($row['class_entryid']) ? $row['class_entryid'] : '',
                isset($row['class_name']) ? $row['class_name'] : '',
                $academicYear
            );
            if($sessionKey === ''){
                $sessionKey = counselling_academic_ensure_session($sessions, $academicYear, $row['batchid'], $row['termname'], isset($row['class_entryid']) ? $row['class_entryid'] : '', array(
                    'academic_year' => $academicYear,
                    'batch' => isset($row['batch']) ? $row['batch'] : '',
                    'class_name' => isset($row['class_name']) ? $row['class_name'] : '',
                    'last_updated' => isset($row['datetimeentry']) ? $row['datetimeentry'] : ''
                ));
            }
            if($sessions[$sessionKey]['remarks'] === null){
                $sessions[$sessionKey]['remarks'] = $row;
            }
        }
    }

    $sessions = array_values($sessions);
    foreach($sessions as $index => $session){
        $subjectCount = count($session['subjects']);
        $passCount = 0;
        $totalSum = 0.0;
        usort($session['subjects'], function($left, $right){
            return strcmp(trim((string)$left['subject']), trim((string)$right['subject']));
        });
        foreach($session['subjects'] as $subjectRow){
            $totalSum += counselling_number(isset($subjectRow['total_score']) ? $subjectRow['total_score'] : 0);
            if(!empty($subjectRow['passed'])){
                $passCount++;
            }
        }
        $session['subject_count'] = $subjectCount;
        $session['pass_count'] = $passCount;
        $session['average_score'] = $subjectCount > 0 ? round($totalSum / $subjectCount, 2) : null;
        $sessions[$index] = $session;

        $profile['summary']['subject_count'] += $subjectCount;
        $profile['summary']['pass_count'] += $passCount;
        if($profile['summary']['average_score'] === null){
            $profile['summary']['average_score'] = 0.0;
        }
        $profile['summary']['average_score'] += $totalSum;
    }

    $sessions = counselling_sort_academic_sessions($sessions);
    $profile['sessions'] = $sessions;
    $profile['summary']['session_count'] = count($sessions);
    if($profile['summary']['subject_count'] > 0){
        $profile['summary']['average_score'] = round($profile['summary']['average_score'] / $profile['summary']['subject_count'], 2);
        $profile['summary']['pass_rate'] = round(($profile['summary']['pass_count'] / $profile['summary']['subject_count']) * 100, 2);
    }else{
        $profile['summary']['average_score'] = null;
        $profile['summary']['pass_rate'] = null;
    }
    if(!empty($sessions)){
        $profile['summary']['latest_session'] = trim((string)$sessions[0]['academic_year']).' · '.counselling_term_label(isset($sessions[0]['termname']) ? $sessions[0]['termname'] : '').' · '.trim((string)$sessions[0]['class_name']);
    }

    return $profile;
}
}

if(!function_exists('counselling_student_context')){
function counselling_student_context($con, $studentId){
    $studentId = trim((string)$studentId);
    $context = array(
        'student' => null,
        'class_scope' => counselling_class_scope_context($con, $studentId),
        'house' => function_exists('get_student_active_house') ? get_student_active_house($con, $studentId) : null,
        'academic_profile' => counselling_student_academic_profile($con, $studentId)
    );
    if(!$con || $studentId === ''){
        return $context;
    }
    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $sql = mysqli_query($con, "SELECT userid, firstname, surname, othernames, gender, mobile
        FROM tblsystemuser
        WHERE userid='$studentIdEsc'
        LIMIT 1");
    if($sql && ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))){
        $context['student'] = $row;
    }
    return $context;
}
}

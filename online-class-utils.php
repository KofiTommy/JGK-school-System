<?php
include_once(__DIR__.DIRECTORY_SEPARATOR."house-master-utils.php");

if(!function_exists('online_class_is_teacher')){
function online_class_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Teacher';
}
}

if(!function_exists('online_class_is_student')){
function online_class_is_student(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Student';
}
}

if(!function_exists('online_class_landing_page')){
function online_class_landing_page(){
    if(function_exists('house_master_landing_page')){
        return house_master_landing_page();
    }
    return 'index.php';
}
}

if(!function_exists('online_class_esc')){
function online_class_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('online_class_term_label')){
function online_class_term_label($termValue){
    $termValue = trim((string)$termValue);
    return $termValue === '' ? 'Semester' : 'Semester '.$termValue;
}
}

if(!function_exists('online_class_scope_key')){
function online_class_scope_key($classId, $batchId, $termName){
    return trim((string)$classId).'|'.trim((string)$batchId).'|'.trim((string)$termName);
}
}

if(!function_exists('online_class_parse_scope_key')){
function online_class_parse_scope_key($scopeKey){
    $parts = explode('|', trim((string)$scopeKey));
    return array(
        'classid' => isset($parts[0]) ? trim((string)$parts[0]) : '',
        'batchid' => isset($parts[1]) ? trim((string)$parts[1]) : '',
        'termname' => isset($parts[2]) ? trim((string)$parts[2]) : ''
    );
}
}

if(!function_exists('online_class_full_name')){
function online_class_full_name($row){
    $parts = array(
        isset($row['firstname']) ? trim((string)$row['firstname']) : '',
        isset($row['othernames']) ? trim((string)$row['othernames']) : '',
        isset($row['surname']) ? trim((string)$row['surname']) : ''
    );
    $parts = array_values(array_filter($parts, function($value){
        return $value !== '';
    }));
    return trim(implode(' ', $parts));
}
}

if(!function_exists('online_class_subject_label')){
function online_class_subject_label($row){
    $subject = isset($row['subjectline']) ? trim((string)$row['subjectline']) : '';
    return $subject !== '' ? $subject : 'General Class Session';
}
}

if(!function_exists('online_class_schedule_label')){
function online_class_schedule_label($row){
    $dateValue = isset($row['sessiondate']) ? trim((string)$row['sessiondate']) : '';
    $startValue = isset($row['starttime']) ? trim((string)$row['starttime']) : '';
    $endValue = isset($row['endtime']) ? trim((string)$row['endtime']) : '';
    $dateText = $dateValue !== '' && strtotime($dateValue) ? date('D, d M Y', strtotime($dateValue)) : $dateValue;
    $startText = $startValue !== '' && strtotime($startValue) ? date('H:i', strtotime($startValue)) : $startValue;
    $endText = $endValue !== '' && strtotime($endValue) ? date('H:i', strtotime($endValue)) : $endValue;
    if($dateText === ''){
        return trim($startText.($endText !== '' ? ' - '.$endText : ''));
    }
    if($startText === ''){
        return $dateText;
    }
    return $dateText.' · '.$startText.($endText !== '' ? ' - '.$endText : '');
}
}

if(!function_exists('online_class_session_state')){
function online_class_session_state($row, $nowTimestamp = null){
    $statusValue = strtolower(trim((string)(isset($row['status']) ? $row['status'] : 'active')));
    if($statusValue === 'cancelled'){
        return array(
            'code' => 'cancelled',
            'label' => 'Cancelled',
            'badge_class' => 'online-class-badge online-class-badge--cancelled',
            'joinable' => false
        );
    }

    if($nowTimestamp === null){
        $nowTimestamp = time();
    }

    $dateValue = isset($row['sessiondate']) ? trim((string)$row['sessiondate']) : '';
    $startValue = isset($row['starttime']) ? trim((string)$row['starttime']) : '';
    $endValue = isset($row['endtime']) ? trim((string)$row['endtime']) : '';

    $startTimestamp = ($dateValue !== '' && $startValue !== '') ? strtotime($dateValue.' '.$startValue) : false;
    $endTimestamp = ($dateValue !== '' && $endValue !== '') ? strtotime($dateValue.' '.$endValue) : false;
    if($startTimestamp && (!$endTimestamp || $endTimestamp <= $startTimestamp)){
        $endTimestamp = $startTimestamp + 3600;
    }

    if(!$startTimestamp){
        return array(
            'code' => 'scheduled',
            'label' => 'Scheduled',
            'badge_class' => 'online-class-badge online-class-badge--upcoming',
            'joinable' => true
        );
    }

    if($nowTimestamp < $startTimestamp){
        return array(
            'code' => 'upcoming',
            'label' => 'Upcoming',
            'badge_class' => 'online-class-badge online-class-badge--upcoming',
            'joinable' => true
        );
    }

    if($endTimestamp && $nowTimestamp <= $endTimestamp){
        return array(
            'code' => 'live',
            'label' => 'Live Now',
            'badge_class' => 'online-class-badge online-class-badge--live',
            'joinable' => true
        );
    }

    return array(
        'code' => 'completed',
        'label' => 'Completed',
        'badge_class' => 'online-class-badge online-class-badge--completed',
        'joinable' => false
    );
}
}

if(!function_exists('ensure_online_class_tables')){
function ensure_online_class_tables($con){
    if(!$con){
        return;
    }
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_online_class_v1', 43200)){
        return;
    }

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineclasssession (
        sessionid VARCHAR(40) NOT NULL PRIMARY KEY,
        teacherid VARCHAR(30) NOT NULL,
        classid VARCHAR(40) NOT NULL,
        batchid VARCHAR(40) NOT NULL,
        termname VARCHAR(20) NOT NULL,
        subjectline VARCHAR(120) NULL,
        topic VARCHAR(150) NOT NULL,
        meetinglink VARCHAR(255) NOT NULL,
        sessiondate DATE NOT NULL,
        starttime TIME NOT NULL,
        endtime TIME NULL,
        instructions TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        updatedat DATETIME NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_onlineclass_teacher (teacherid),
        INDEX idx_onlineclass_scope (classid, batchid, termname),
        INDEX idx_onlineclass_schedule (sessiondate, starttime),
        INDEX idx_onlineclass_status (status)
    )");

    if(function_exists('xschool_schema_ensure_index')){
        xschool_schema_ensure_index(
            $con,
            'tblonlineclasssession',
            'idx_onlineclass_student_lookup',
            "ALTER TABLE tblonlineclasssession ADD INDEX idx_onlineclass_student_lookup (status, classid, batchid, sessiondate)"
        );
    }

    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_online_class_v1');
    }
}
}

if(!function_exists('online_class_teacher_scopes')){
function online_class_teacher_scopes($con, $teacherId){
    $teacherId = trim((string)$teacherId);
    if(!$con || $teacherId === ''){
        return array();
    }

    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $scopeMap = array();

    $subjectSql = mysqli_query($con, "SELECT
            sa.classid,
            sa.batchid,
            sa.termname,
            ce.class_name,
            bh.batch,
            MAX(sa.datetimeentry) AS scope_datetimeentry,
            GROUP_CONCAT(DISTINCT sub.subject ORDER BY sub.subject ASC SEPARATOR ', ') AS subject_summary
        FROM tblsubjectassignment sa
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        LEFT JOIN tblbatch bh ON bh.batchid=sa.batchid
        WHERE sa.userid='$teacherIdEsc'
          AND sa.status='active'
        GROUP BY sa.classid, sa.batchid, sa.termname, ce.class_name, bh.batch
        ORDER BY scope_datetimeentry DESC, sa.termname DESC, ce.class_name ASC");
    if($subjectSql){
        while($row = mysqli_fetch_array($subjectSql, MYSQLI_ASSOC)){
            $key = online_class_scope_key($row['classid'], $row['batchid'], $row['termname']);
            $row['scope_key'] = $key;
            $row['is_class_teacher'] = false;
            $row['sort_timestamp'] = strtotime((string)$row['scope_datetimeentry']) ?: 0;
            $scopeMap[$key] = $row;
        }
    }

    $classTeacherSql = mysqli_query($con, "SELECT
            ct.classid,
            ct.batchid,
            ct.termname,
            ct.datetimeentry AS scope_datetimeentry,
            ce.class_name,
            bh.batch
        FROM tblclassteacher ct
        INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ct.batchid
        WHERE ct.userid='$teacherIdEsc'
          AND ct.status='active'
        ORDER BY ct.datetimeentry DESC, ct.termname DESC, ce.class_name ASC");
    if($classTeacherSql){
        while($row = mysqli_fetch_array($classTeacherSql, MYSQLI_ASSOC)){
            $key = online_class_scope_key($row['classid'], $row['batchid'], $row['termname']);
            if(isset($scopeMap[$key])){
                $scopeMap[$key]['is_class_teacher'] = true;
                continue;
            }
            $row['scope_key'] = $key;
            $row['subject_summary'] = 'Class meeting';
            $row['is_class_teacher'] = true;
            $row['sort_timestamp'] = strtotime((string)$row['scope_datetimeentry']) ?: 0;
            $scopeMap[$key] = $row;
        }
    }

    $scopeRows = array_values($scopeMap);
    usort($scopeRows, function($left, $right){
        $leftSort = isset($left['sort_timestamp']) ? (int)$left['sort_timestamp'] : 0;
        $rightSort = isset($right['sort_timestamp']) ? (int)$right['sort_timestamp'] : 0;
        if($leftSort === $rightSort){
            return strcmp(
                trim((string)(isset($left['class_name']) ? $left['class_name'] : '')),
                trim((string)(isset($right['class_name']) ? $right['class_name'] : ''))
            );
        }
        return ($rightSort <=> $leftSort);
    });
    return $scopeRows;
}
}

if(!function_exists('online_class_student_scopes')){
function online_class_student_scopes($con, $studentId){
    $studentId = trim((string)$studentId);
    if(!$con || $studentId === ''){
        return array();
    }

    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $scopeRows = array();
    $scopeLookup = array();

    $classSql = mysqli_query($con, "SELECT
            cl.class_entryid AS classid,
            cl.batchid,
            cl.datetimeentry,
            ce.class_name,
            bh.batch
        FROM tblclass cl
        INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
        WHERE cl.userid='$studentIdEsc'
          AND cl.status='active'
        ORDER BY cl.datetimeentry DESC, ce.class_name ASC");
    if($classSql){
        while($row = mysqli_fetch_array($classSql, MYSQLI_ASSOC)){
            $key = trim((string)$row['classid']).'|'.trim((string)$row['batchid']);
            if(isset($scopeLookup[$key])){
                continue;
            }
            $scopeLookup[$key] = true;
            $scopeRows[] = $row;
        }
    }

    if(!empty($scopeRows)){
        return $scopeRows;
    }

    $fallbackSql = mysqli_query($con, "SELECT
            tr.class_entryid AS classid,
            tr.batchid,
            tr.datetimeentry,
            ce.class_name,
            bh.batch
        FROM tbltermregistry tr
        INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
        WHERE tr.userid='$studentIdEsc'
        ORDER BY tr.datetimeentry DESC, tr.termname DESC, ce.class_name ASC");
    if($fallbackSql){
        while($row = mysqli_fetch_array($fallbackSql, MYSQLI_ASSOC)){
            $key = trim((string)$row['classid']).'|'.trim((string)$row['batchid']);
            if(isset($scopeLookup[$key])){
                continue;
            }
            $scopeLookup[$key] = true;
            $scopeRows[] = $row;
        }
    }
    return $scopeRows;
}
}

if(!function_exists('online_class_fetch_session_row')){
function online_class_fetch_session_row($con, $sessionId){
    $sessionId = trim((string)$sessionId);
    if(!$con || $sessionId === ''){
        return null;
    }

    $sessionIdEsc = mysqli_real_escape_string($con, $sessionId);
    $sql = mysqli_query($con, "SELECT
            ocs.*,
            ce.class_name,
            bh.batch,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblonlineclasssession ocs
        INNER JOIN tblclassentry ce ON ce.class_entryid=ocs.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ocs.batchid
        LEFT JOIN tblsystemuser su ON su.userid=ocs.teacherid
        WHERE ocs.sessionid='$sessionIdEsc'
        LIMIT 1");
    if($sql && ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_class_teacher_sessions')){
function online_class_teacher_sessions($con, $teacherId){
    $teacherId = trim((string)$teacherId);
    if(!$con || $teacherId === ''){
        return array();
    }

    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $sessionRows = array();
    $sql = mysqli_query($con, "SELECT
            ocs.*,
            ce.class_name,
            bh.batch,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblonlineclasssession ocs
        INNER JOIN tblclassentry ce ON ce.class_entryid=ocs.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ocs.batchid
        LEFT JOIN tblsystemuser su ON su.userid=ocs.teacherid
        WHERE ocs.teacherid='$teacherIdEsc'
        ORDER BY ocs.sessiondate DESC, ocs.starttime DESC, ocs.datetimeentry DESC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $sessionRows[] = $row;
        }
    }
    return $sessionRows;
}
}

if(!function_exists('online_class_student_sessions')){
function online_class_student_sessions($con, $studentId){
    $scopeRows = online_class_student_scopes($con, $studentId);
    if(empty($scopeRows)){
        return array();
    }

    $conditions = array();
    foreach($scopeRows as $scopeRow){
        $classId = isset($scopeRow['classid']) ? trim((string)$scopeRow['classid']) : '';
        $batchId = isset($scopeRow['batchid']) ? trim((string)$scopeRow['batchid']) : '';
        if($classId === '' || $batchId === ''){
            continue;
        }
        $conditions[] = "(ocs.classid='".mysqli_real_escape_string($con, $classId)."' AND ocs.batchid='".mysqli_real_escape_string($con, $batchId)."')";
    }
    if(empty($conditions)){
        return array();
    }

    $sessionRows = array();
    $sql = mysqli_query($con, "SELECT
            ocs.*,
            ce.class_name,
            bh.batch,
            su.firstname,
            su.surname,
            su.othernames
        FROM tblonlineclasssession ocs
        INNER JOIN tblclassentry ce ON ce.class_entryid=ocs.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ocs.batchid
        LEFT JOIN tblsystemuser su ON su.userid=ocs.teacherid
        WHERE ocs.status='active'
          AND (".implode(' OR ', $conditions).")
          AND ocs.sessiondate >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        ORDER BY ocs.sessiondate ASC, ocs.starttime ASC, ocs.datetimeentry DESC");
    if($sql){
        while($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)){
            $sessionRows[] = $row;
        }
    }
    return $sessionRows;
}
}

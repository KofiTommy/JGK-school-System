<?php
include_once("semester-registry-utils.php");

if(!function_exists('course_registration_column_exists')){
function course_registration_column_exists($con, $tableName, $columnName){
    static $cache = array();
    $cacheKey = strtolower(trim((string)$tableName)).'.'.strtolower(trim((string)$columnName));
    if(isset($cache[$cacheKey])){
        return $cache[$cacheKey];
    }

    $tableSafe = mysqli_real_escape_string($con, trim((string)$tableName));
    $columnSafe = mysqli_real_escape_string($con, trim((string)$columnName));
    $result = @mysqli_query($con, "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'");
    $cache[$cacheKey] = ($result && mysqli_num_rows($result) > 0);
    return $cache[$cacheKey];
}
}

if(!function_exists('course_registration_ensure_tables')){
function course_registration_ensure_tables($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblcourseregwindow (
        windowid VARCHAR(40) NOT NULL,
        classid VARCHAR(60) NOT NULL,
        batchid VARCHAR(60) NOT NULL,
        academicyear VARCHAR(10) NOT NULL DEFAULT '',
        termname VARCHAR(10) NOT NULL DEFAULT '',
        branchid VARCHAR(60) NOT NULL DEFAULT '',
        windowtitle VARCHAR(160) NOT NULL DEFAULT '',
        minimum_electives INT NOT NULL DEFAULT 0,
        maximum_electives INT NOT NULL DEFAULT 0,
        openfrom DATETIME NULL DEFAULT NULL,
        closeat DATETIME NULL DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'closed',
        notes TEXT NULL,
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        lastupdatedat DATETIME NULL DEFAULT NULL,
        recordedby VARCHAR(60) NOT NULL DEFAULT '',
        updatedby VARCHAR(60) NOT NULL DEFAULT '',
        PRIMARY KEY (windowid),
        UNIQUE KEY uq_scope (classid,batchid,academicyear,termname),
        KEY idx_batch_scope (batchid,academicyear,termname),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblcourseregoffering (
        offeringid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        windowid VARCHAR(40) NOT NULL,
        assignmentid VARCHAR(60) NOT NULL,
        teacherid VARCHAR(60) NOT NULL DEFAULT '',
        classid VARCHAR(60) NOT NULL DEFAULT '',
        classificationid VARCHAR(60) NOT NULL DEFAULT '',
        subjectid VARCHAR(60) NOT NULL DEFAULT '',
        subjectlabel VARCHAR(200) NOT NULL DEFAULT '',
        teacherlabel VARCHAR(200) NOT NULL DEFAULT '',
        selectiontype VARCHAR(20) NOT NULL DEFAULT 'compulsory',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        recordedby VARCHAR(60) NOT NULL DEFAULT '',
        PRIMARY KEY (offeringid),
        UNIQUE KEY uq_window_assignment (windowid,assignmentid),
        KEY idx_windowid (windowid),
        KEY idx_teacherid (teacherid),
        KEY idx_classid (classid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentcourseregistration (
        registrationid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        windowid VARCHAR(40) NOT NULL,
        userid VARCHAR(60) NOT NULL,
        assignmentid VARCHAR(60) NOT NULL,
        teacherid VARCHAR(60) NOT NULL DEFAULT '',
        classid VARCHAR(60) NOT NULL DEFAULT '',
        batchid VARCHAR(60) NOT NULL DEFAULT '',
        academicyear VARCHAR(10) NOT NULL DEFAULT '',
        termname VARCHAR(10) NOT NULL DEFAULT '',
        classificationid VARCHAR(60) NOT NULL DEFAULT '',
        subjectid VARCHAR(60) NOT NULL DEFAULT '',
        subjectlabel VARCHAR(200) NOT NULL DEFAULT '',
        selectiontype VARCHAR(20) NOT NULL DEFAULT 'elective',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        submittedby VARCHAR(60) NOT NULL DEFAULT '',
        PRIMARY KEY (registrationid),
        UNIQUE KEY uq_window_user_assignment (windowid,userid,assignmentid),
        KEY idx_windowid (windowid),
        KEY idx_userid (userid),
        KEY idx_teacherid (teacherid),
        KEY idx_scope (classid,batchid,academicyear,termname)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentcourseregstatus (
        statusid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        windowid VARCHAR(40) NOT NULL,
        userid VARCHAR(60) NOT NULL,
        classid VARCHAR(60) NOT NULL DEFAULT '',
        batchid VARCHAR(60) NOT NULL DEFAULT '',
        academicyear VARCHAR(10) NOT NULL DEFAULT '',
        termname VARCHAR(10) NOT NULL DEFAULT '',
        selectedcount INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'submitted',
        submittedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        lastupdatedat DATETIME NULL DEFAULT NULL,
        submittedby VARCHAR(60) NOT NULL DEFAULT '',
        PRIMARY KEY (statusid),
        UNIQUE KEY uq_window_user (windowid,userid),
        KEY idx_userid (userid),
        KEY idx_scope (classid,batchid,academicyear,termname)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if(!course_registration_column_exists($con, 'tblcourseregwindow', 'minimum_electives')){
        @mysqli_query($con, "ALTER TABLE tblcourseregwindow ADD COLUMN minimum_electives INT NOT NULL DEFAULT 0 AFTER windowtitle");
    }
    if(!course_registration_column_exists($con, 'tblcourseregwindow', 'maximum_electives')){
        @mysqli_query($con, "ALTER TABLE tblcourseregwindow ADD COLUMN maximum_electives INT NOT NULL DEFAULT 0 AFTER minimum_electives");
    }
    if(!course_registration_column_exists($con, 'tblcourseregwindow', 'openfrom')){
        @mysqli_query($con, "ALTER TABLE tblcourseregwindow ADD COLUMN openfrom DATETIME NULL DEFAULT NULL AFTER maximum_electives");
    }
    if(!course_registration_column_exists($con, 'tblcourseregwindow', 'closeat')){
        @mysqli_query($con, "ALTER TABLE tblcourseregwindow ADD COLUMN closeat DATETIME NULL DEFAULT NULL AFTER openfrom");
    }
    if(!course_registration_column_exists($con, 'tblcourseregwindow', 'lastupdatedat')){
        @mysqli_query($con, "ALTER TABLE tblcourseregwindow ADD COLUMN lastupdatedat DATETIME NULL DEFAULT NULL AFTER datetimeentry");
    }
    if(!course_registration_column_exists($con, 'tblcourseregoffering', 'teacherlabel')){
        @mysqli_query($con, "ALTER TABLE tblcourseregoffering ADD COLUMN teacherlabel VARCHAR(200) NOT NULL DEFAULT '' AFTER subjectlabel");
    }
    if(!course_registration_column_exists($con, 'tblsubjectclassification', 'termname')){
        @mysqli_query($con, "ALTER TABLE tblsubjectclassification ADD COLUMN termname VARCHAR(10) NOT NULL DEFAULT '' AFTER subjectid");
    }
    if(course_registration_column_exists($con, 'tblsubjectclassification', 'term')){
        @mysqli_query($con, "UPDATE tblsubjectclassification SET termname=term WHERE TRIM(COALESCE(termname,''))='' AND TRIM(COALESCE(term,''))<>''");
    }
    if(!course_registration_column_exists($con, 'tblsubjectclassification', 'academicyear')){
        @mysqli_query($con, "ALTER TABLE tblsubjectclassification ADD COLUMN academicyear VARCHAR(10) NOT NULL DEFAULT '' AFTER termname");
    }
    @mysqli_query($con, "UPDATE tblsubjectclassification SET academicyear='' WHERE TRIM(COALESCE(termname,''))=''");
}
}

if(!function_exists('course_registration_normalize_year')){
function course_registration_normalize_year($value){
    if(function_exists('semester_registry_normalize_year')){
        return semester_registry_normalize_year($value);
    }
    $value = trim((string)$value);
    if($value === '' || preg_match('/^\d{4}$/', $value) !== 1){
        return '';
    }
    return $value;
}
}

if(!function_exists('course_registration_year_options')){
function course_registration_year_options(){
    if(function_exists('semester_registry_year_options')){
        return semester_registry_year_options();
    }
    $currentYear = (int)date('Y');
    $years = array();
    for($year = max(2030, $currentYear + 3); $year >= min(2025, $currentYear - 1); $year--){
        $years[] = (string)$year;
    }
    return $years;
}
}

if(!function_exists('course_registration_term_label')){
function course_registration_term_label($termValue){
    $termValue = trim((string)$termValue);
    return $termValue === '' ? 'Semester' : 'Semester '.$termValue;
}
}

if(!function_exists('course_registration_session_label')){
function course_registration_session_label($academicYear, $batchLabel, $termValue){
    if(function_exists('semester_registry_session_label')){
        return semester_registry_session_label($academicYear, $batchLabel, $termValue);
    }
    return trim(course_registration_normalize_year($academicYear).' Batch '.trim((string)$batchLabel).' '.course_registration_term_label($termValue));
}
}

if(!function_exists('course_registration_selection_type_label')){
function course_registration_selection_type_label($selectionType){
    $selectionType = strtolower(trim((string)$selectionType));
    return $selectionType === 'elective' ? 'Elective' : 'Compulsory';
}
}

if(!function_exists('course_registration_generate_window_id')){
function course_registration_generate_window_id(){
    return 'CRW'.date('ymdHis').mt_rand(100, 999);
}
}

if(!function_exists('course_registration_branch_id')){
function course_registration_branch_id(){
    return isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : '';
}
}

if(!function_exists('course_registration_scope_query')){
function course_registration_scope_query($params){
    $clean = array();
    foreach($params as $key => $value){
        $value = trim((string)$value);
        if($value !== ''){
            $clean[$key] = $value;
        }
    }
    $query = http_build_query($clean);
    return $query === '' ? '' : '?'.$query;
}
}

if(!function_exists('course_registration_person_name')){
function course_registration_person_name($row){
    $parts = array();
    foreach(array('firstname','othernames','surname') as $field){
        if(isset($row[$field])){
            $value = trim((string)$row[$field]);
            if($value !== ''){
                $parts[] = $value;
            }
        }
    }
    return trim(implode(' ', $parts));
}
}

if(!function_exists('course_registration_teacher_label')){
function course_registration_teacher_label($row){
    $name = course_registration_person_name($row);
    $userId = isset($row['userid']) ? trim((string)$row['userid']) : '';
    if($name === ''){
        return $userId;
    }
    return $userId === '' ? $name : $name.' ('.$userId.')';
}
}

if(!function_exists('course_registration_window_is_open')){
function course_registration_window_is_open($windowRow){
    $status = 'closed';
    if(isset($windowRow['status'])){
        $status = strtolower(trim((string)$windowRow['status']));
    }elseif(isset($windowRow['window_status'])){
        $status = strtolower(trim((string)$windowRow['window_status']));
    }
    if($status !== 'open'){
        return false;
    }
    $now = time();
    $openFrom = trim((string)(isset($windowRow['openfrom']) ? $windowRow['openfrom'] : ''));
    $closeAt = trim((string)(isset($windowRow['closeat']) ? $windowRow['closeat'] : ''));
    if($openFrom !== ''){
        $openTime = strtotime($openFrom);
        if($openTime && $now < $openTime){
            return false;
        }
    }
    if($closeAt !== ''){
        $closeTime = strtotime($closeAt);
        if($closeTime && $now > $closeTime){
            return false;
        }
    }
    return true;
}
}

if(!function_exists('course_registration_window_status_label')){
function course_registration_window_status_label($windowRow){
    if(course_registration_window_is_open($windowRow)){
        return 'Open';
    }
    $status = strtolower(trim((string)(isset($windowRow['status']) ? $windowRow['status'] : 'closed')));
    if($status === 'open'){
        $openFrom = trim((string)(isset($windowRow['openfrom']) ? $windowRow['openfrom'] : ''));
        if($openFrom !== ''){
            return 'Scheduled';
        }
    }
    return 'Closed';
}
}

if(!function_exists('course_registration_scope_student_count')){
function course_registration_scope_student_count($con, $classId, $batchId, $academicYear, $termName){
    static $cache = array();
    $cacheKey = trim((string)$classId).'|'.trim((string)$batchId).'|'.trim((string)$academicYear).'|'.trim((string)$termName);
    if(isset($cache[$cacheKey])){
        return $cache[$cacheKey];
    }

    $resolvedYearSql = function_exists('semester_registry_resolved_year_sql') ? semester_registry_resolved_year_sql('tr') : "DATE_FORMAT(tr.datetimeentry, '%Y')";
    $classEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$academicYear));
    $termEsc = mysqli_real_escape_string($con, trim((string)$termName));
    $sql = "SELECT COUNT(DISTINCT tr.userid) AS total_students
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid
        WHERE tr.status='active'
          AND su.systemtype='Student'
          AND su.status='active'
          AND tr.class_entryid='$classEsc'
          AND tr.batchid='$batchEsc'
          AND ".$resolvedYearSql."='$yearEsc'
          AND tr.termname='$termEsc'";
    $result = mysqli_query($con, $sql);
    $cache[$cacheKey] = ($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))) ? (int)$row['total_students'] : 0;
    return $cache[$cacheKey];
}
}

if(!function_exists('course_registration_fetch_scope_assignments')){
function course_registration_fetch_scope_assignments($con, $classId, $batchId, $academicYear, $termName){
    course_registration_ensure_tables($con);
    $rows = array();
    $classEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$academicYear));
    $termEsc = mysqli_real_escape_string($con, trim((string)$termName));
    $sql = "SELECT
            sc.classificationid AS assignmentid,
            '' AS teacherid,
            sc.classid,
            sc.classificationid,
            sc.subjectid,
            sc.termname,
            sc.academicyear,
            ce.class_name,
            sub.subject,
            sc.datetimeentry
        FROM tblsubjectclassification sc
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=sc.classid
        WHERE sc.status='active'
          AND sc.classid='$classEsc'
        ORDER BY
          CASE
            WHEN TRIM(COALESCE(sc.termname,''))='$termEsc' AND TRIM(COALESCE(sc.academicyear,''))='$yearEsc' THEN 0
            WHEN TRIM(COALESCE(sc.termname,''))='$termEsc' AND TRIM(COALESCE(sc.academicyear,''))='' THEN 1
            WHEN TRIM(COALESCE(sc.termname,''))='' AND TRIM(COALESCE(sc.academicyear,''))='' THEN 2
            WHEN TRIM(COALESCE(sc.termname,''))='' AND TRIM(COALESCE(sc.academicyear,''))='$yearEsc' THEN 3
            ELSE 4
          END ASC,
          sub.subject ASC,
          sc.datetimeentry DESC";
    $result = mysqli_query($con, $sql);
    $seenSubjects = array();
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $subjectKey = trim((string)$row['subjectid']);
            if($subjectKey === '' || isset($seenSubjects[$subjectKey])){
                continue;
            }
            $seenSubjects[$subjectKey] = true;
            $row['teacherlabel'] = 'Teacher not assigned yet';
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('course_registration_fetch_recent_windows')){
function course_registration_fetch_recent_windows($con, $limit = 12){
    $rows = array();
    $limit = max(1, (int)$limit);
    $sql = "SELECT
            w.*,
            ce.class_name,
            COALESCE(b.batch, 'Batch not set') AS batch,
            (SELECT COUNT(*) FROM tblcourseregoffering o WHERE o.windowid=w.windowid AND o.status='active') AS course_count,
            (SELECT COUNT(*) FROM tblstudentcourseregstatus s WHERE s.windowid=w.windowid AND s.status='submitted') AS submitted_count
        FROM tblcourseregwindow w
        LEFT JOIN tblclassentry ce ON ce.class_entryid=w.classid
        LEFT JOIN tblbatch b ON b.batchid=w.batchid
        ORDER BY w.datetimeentry DESC
        LIMIT ".$limit;
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row['session_label'] = course_registration_session_label($row['academicyear'], $row['batch'], $row['termname']);
            $row['eligible_count'] = course_registration_scope_student_count($con, $row['classid'], $row['batchid'], $row['academicyear'], $row['termname']);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('course_registration_fetch_window_by_scope')){
function course_registration_fetch_window_by_scope($con, $classId, $batchId, $academicYear, $termName){
    $classEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$academicYear));
    $termEsc = mysqli_real_escape_string($con, trim((string)$termName));
    $sql = "SELECT w.*, ce.class_name, COALESCE(b.batch, 'Batch not set') AS batch
        FROM tblcourseregwindow w
        LEFT JOIN tblclassentry ce ON ce.class_entryid=w.classid
        LEFT JOIN tblbatch b ON b.batchid=w.batchid
        WHERE w.classid='$classEsc'
          AND w.batchid='$batchEsc'
          AND w.academicyear='$yearEsc'
          AND w.termname='$termEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        $row['session_label'] = course_registration_session_label($row['academicyear'], $row['batch'], $row['termname']);
        return $row;
    }
    return null;
}
}

if(!function_exists('course_registration_fetch_window_by_id')){
function course_registration_fetch_window_by_id($con, $windowId){
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $sql = "SELECT w.*, ce.class_name, COALESCE(b.batch, 'Batch not set') AS batch
        FROM tblcourseregwindow w
        LEFT JOIN tblclassentry ce ON ce.class_entryid=w.classid
        LEFT JOIN tblbatch b ON b.batchid=w.batchid
        WHERE w.windowid='$windowEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        $row['session_label'] = course_registration_session_label($row['academicyear'], $row['batch'], $row['termname']);
        return $row;
    }
    return null;
}
}

if(!function_exists('course_registration_fetch_offerings')){
function course_registration_fetch_offerings($con, $windowId){
    $rows = array();
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $sql = "SELECT *
        FROM tblcourseregoffering
        WHERE windowid='$windowEsc' AND status='active'
        ORDER BY FIELD(selectiontype,'compulsory','elective'), subjectlabel ASC, teacherlabel ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('course_registration_window_has_submissions')){
function course_registration_window_has_submissions($con, $windowId){
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $result = mysqli_query($con, "SELECT COUNT(*) AS total_submitted FROM tblstudentcourseregstatus WHERE windowid='$windowEsc' AND status='submitted' LIMIT 1");
    return ($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) && (int)$row['total_submitted'] > 0);
}
}

if(!function_exists('course_registration_save_window')){
function course_registration_save_window($con, $data, $selectedOfferings, $recordedBy){
    course_registration_ensure_tables($con);

    $classId = trim((string)(isset($data['classid']) ? $data['classid'] : ''));
    $batchId = trim((string)(isset($data['batchid']) ? $data['batchid'] : ''));
    $academicYear = course_registration_normalize_year(isset($data['academicyear']) ? $data['academicyear'] : '');
    $termName = trim((string)(isset($data['termname']) ? $data['termname'] : ''));
    $windowStatus = strtolower(trim((string)(isset($data['status']) ? $data['status'] : 'closed')));
    $windowTitle = trim((string)(isset($data['windowtitle']) ? $data['windowtitle'] : ''));
    $notes = trim((string)(isset($data['notes']) ? $data['notes'] : ''));
    $minimumElectives = max(0, (int)(isset($data['minimum_electives']) ? $data['minimum_electives'] : 0));
    $maximumElectives = max(0, (int)(isset($data['maximum_electives']) ? $data['maximum_electives'] : 0));
    $openFrom = trim((string)(isset($data['openfrom']) ? $data['openfrom'] : ''));
    $closeAt = trim((string)(isset($data['closeat']) ? $data['closeat'] : ''));
    $branchId = trim((string)(isset($data['branchid']) ? $data['branchid'] : course_registration_branch_id()));
    $recordedBy = trim((string)$recordedBy);

    if($classId === '' || $batchId === '' || $academicYear === '' || $termName === ''){
        return array('ok' => false, 'message' => 'Select class, batch, academic year, and semester before saving the registration window.');
    }
    if(!in_array($windowStatus, array('open','closed'), true)){
        $windowStatus = 'closed';
    }
    if($maximumElectives > 0 && $maximumElectives < $minimumElectives){
        return array('ok' => false, 'message' => 'Maximum elective count cannot be lower than the minimum elective count.');
    }

    $assignmentRows = course_registration_fetch_scope_assignments($con, $classId, $batchId, $academicYear, $termName);
    $assignmentMap = array();
    foreach($assignmentRows as $assignmentRow){
        $assignmentMap[trim((string)$assignmentRow['assignmentid'])] = $assignmentRow;
    }

    $existingWindow = course_registration_fetch_window_by_scope($con, $classId, $batchId, $academicYear, $termName);
    $windowId = $existingWindow ? trim((string)$existingWindow['windowid']) : course_registration_generate_window_id();
    $hasSubmissions = $existingWindow ? course_registration_window_has_submissions($con, $windowId) : false;

    $offeringsToSave = array();
    $newOfferingsToInsert = array();
    $preserveExistingOfferings = ($existingWindow && count($assignmentMap) === 0);
    if($hasSubmissions || $preserveExistingOfferings){
        $existingOfferingMap = array();
        foreach(course_registration_fetch_offerings($con, $windowId) as $offeringRow){
            $offeringsToSave[] = $offeringRow;
            $existingOfferingMap[trim((string)$offeringRow['assignmentid'])] = true;
        }
        if(count($offeringsToSave) === 0){
            return array('ok' => false, 'message' => 'This registration window no longer has any saved classified subjects to preserve. Reload the subject classification for the selected scope first.');
        }
        if($hasSubmissions && count($assignmentMap) > 0){
            foreach($assignmentRows as $assignmentRow){
                $assignmentId = trim((string)$assignmentRow['assignmentid']);
                if($assignmentId === '' || isset($existingOfferingMap[$assignmentId])){
                    continue;
                }
                $newOfferingsToInsert[] = array(
                    'assignmentid' => $assignmentId,
                    'teacherid' => trim((string)$assignmentRow['teacherid']),
                    'classid' => trim((string)$assignmentRow['classid']),
                    'classificationid' => trim((string)$assignmentRow['classificationid']),
                    'subjectid' => trim((string)$assignmentRow['subjectid']),
                    'subjectlabel' => trim((string)$assignmentRow['subject']),
                    'teacherlabel' => trim((string)$assignmentRow['teacherlabel']),
                    'selectiontype' => 'elective'
                );
            }
        }
    }else{
        if(count($assignmentMap) === 0){
            return array('ok' => false, 'message' => 'No valid classified subjects were found for the selected scope. Confirm the subject classification first.');
        }

        if(is_array($selectedOfferings) && count($selectedOfferings) > 0){
            foreach($selectedOfferings as $assignmentId => $selectionType){
                $assignmentId = trim((string)$assignmentId);
                if($assignmentId === '' || !isset($assignmentMap[$assignmentId])){
                    continue;
                }
                $selectionType = strtolower(trim((string)$selectionType));
                if($selectionType !== 'elective'){
                    $selectionType = 'compulsory';
                }
                $assignmentRow = $assignmentMap[$assignmentId];
                $offeringsToSave[] = array(
                    'assignmentid' => $assignmentId,
                    'teacherid' => trim((string)$assignmentRow['teacherid']),
                    'classid' => trim((string)$assignmentRow['classid']),
                    'classificationid' => trim((string)$assignmentRow['classificationid']),
                    'subjectid' => trim((string)$assignmentRow['subjectid']),
                    'subjectlabel' => trim((string)$assignmentRow['subject']),
                    'teacherlabel' => trim((string)$assignmentRow['teacherlabel']),
                    'selectiontype' => $selectionType
                );
            }
        }

        if(count($offeringsToSave) === 0){
            foreach($assignmentRows as $assignmentRow){
                $offeringsToSave[] = array(
                    'assignmentid' => trim((string)$assignmentRow['assignmentid']),
                    'teacherid' => trim((string)$assignmentRow['teacherid']),
                    'classid' => trim((string)$assignmentRow['classid']),
                    'classificationid' => trim((string)$assignmentRow['classificationid']),
                    'subjectid' => trim((string)$assignmentRow['subjectid']),
                    'subjectlabel' => trim((string)$assignmentRow['subject']),
                    'teacherlabel' => trim((string)$assignmentRow['teacherlabel']),
                    'selectiontype' => 'elective'
                );
            }
        }
    }

    $windowTitleEsc = mysqli_real_escape_string($con, $windowTitle);
    $notesEsc = mysqli_real_escape_string($con, $notes);
    $classEsc = mysqli_real_escape_string($con, $classId);
    $batchEsc = mysqli_real_escape_string($con, $batchId);
    $yearEsc = mysqli_real_escape_string($con, $academicYear);
    $termEsc = mysqli_real_escape_string($con, $termName);
    $statusEsc = mysqli_real_escape_string($con, $windowStatus);
    $branchEsc = mysqli_real_escape_string($con, $branchId);
    $recordedByEsc = mysqli_real_escape_string($con, $recordedBy);
    $openFromSql = $openFrom !== '' ? "'".mysqli_real_escape_string($con, $openFrom)."'" : "NULL";
    $closeAtSql = $closeAt !== '' ? "'".mysqli_real_escape_string($con, $closeAt)."'" : "NULL";

    @mysqli_query($con, "START TRANSACTION");
    $windowOk = false;
    if($existingWindow){
        $windowOk = @mysqli_query($con, "UPDATE tblcourseregwindow
            SET windowtitle='$windowTitleEsc',
                minimum_electives='$minimumElectives',
                maximum_electives='$maximumElectives',
                openfrom=$openFromSql,
                closeat=$closeAtSql,
                status='$statusEsc',
                notes='$notesEsc',
                branchid='$branchEsc',
                lastupdatedat=NOW(),
                updatedby='$recordedByEsc'
            WHERE windowid='".mysqli_real_escape_string($con, $windowId)."'
            LIMIT 1");
    }else{
        $windowOk = @mysqli_query($con, "INSERT INTO tblcourseregwindow(windowid,classid,batchid,academicyear,termname,branchid,windowtitle,minimum_electives,maximum_electives,openfrom,closeat,status,notes,datetimeentry,lastupdatedat,recordedby,updatedby)
            VALUES('".mysqli_real_escape_string($con, $windowId)."','$classEsc','$batchEsc','$yearEsc','$termEsc','$branchEsc','$windowTitleEsc','$minimumElectives','$maximumElectives',$openFromSql,$closeAtSql,'$statusEsc','$notesEsc',NOW(),NOW(),'$recordedByEsc','$recordedByEsc')");
    }

    if(!$windowOk){
        @mysqli_query($con, "ROLLBACK");
        return array('ok' => false, 'message' => 'The registration window could not be saved: '.mysqli_error($con));
    }

    if(!$hasSubmissions && !$preserveExistingOfferings){
        $deleteOk = @mysqli_query($con, "DELETE FROM tblcourseregoffering WHERE windowid='".mysqli_real_escape_string($con, $windowId)."'");
        if(!$deleteOk){
            @mysqli_query($con, "ROLLBACK");
            return array('ok' => false, 'message' => 'Existing course offerings could not be refreshed: '.mysqli_error($con));
        }

        foreach($offeringsToSave as $offeringRow){
            $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['assignmentid']));
            $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['teacherid']));
            $classIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['classid']));
            $classificationIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['classificationid']));
            $subjectIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['subjectid']));
            $subjectLabelEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['subjectlabel']));
            $teacherLabelEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['teacherlabel']));
            $selectionTypeEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['selectiontype']));
            $offeringOk = @mysqli_query($con, "INSERT INTO tblcourseregoffering(windowid,assignmentid,teacherid,classid,classificationid,subjectid,subjectlabel,teacherlabel,selectiontype,status,datetimeentry,recordedby)
                VALUES('".mysqli_real_escape_string($con, $windowId)."','$assignmentIdEsc','$teacherIdEsc','$classIdEsc','$classificationIdEsc','$subjectIdEsc','$subjectLabelEsc','$teacherLabelEsc','$selectionTypeEsc','active',NOW(),'$recordedByEsc')");
            if(!$offeringOk){
                @mysqli_query($con, "ROLLBACK");
                return array('ok' => false, 'message' => 'A course offering could not be saved: '.mysqli_error($con));
            }
        }
    }elseif(count($newOfferingsToInsert) > 0){
        foreach($newOfferingsToInsert as $offeringRow){
            $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['assignmentid']));
            $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['teacherid']));
            $classIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['classid']));
            $classificationIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['classificationid']));
            $subjectIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['subjectid']));
            $subjectLabelEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['subjectlabel']));
            $teacherLabelEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['teacherlabel']));
            $selectionTypeEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['selectiontype']));
            $offeringOk = @mysqli_query($con, "INSERT INTO tblcourseregoffering(windowid,assignmentid,teacherid,classid,classificationid,subjectid,subjectlabel,teacherlabel,selectiontype,status,datetimeentry,recordedby)
                VALUES('".mysqli_real_escape_string($con, $windowId)."','$assignmentIdEsc','$teacherIdEsc','$classIdEsc','$classificationIdEsc','$subjectIdEsc','$subjectLabelEsc','$teacherLabelEsc','$selectionTypeEsc','active',NOW(),'$recordedByEsc')");
            if(!$offeringOk){
                @mysqli_query($con, "ROLLBACK");
                return array('ok' => false, 'message' => 'A newly detected classified subject could not be added: '.mysqli_error($con));
            }
        }
    }

    @mysqli_query($con, "COMMIT");
    return array(
        'ok' => true,
        'message' => ($hasSubmissions || $preserveExistingOfferings) ? 'Registration window updated. Existing course offerings were preserved.' : 'Registration window saved successfully. Classified subjects were loaded automatically for student selection.',
        'windowid' => $windowId
    );
}
}

if(!function_exists('course_registration_student_is_eligible_for_window')){
function course_registration_student_is_eligible_for_window($con, $studentId, $windowRow){
    if(!$windowRow){
        return false;
    }
    $resolvedYearSql = function_exists('semester_registry_resolved_year_sql') ? semester_registry_resolved_year_sql('tr') : "DATE_FORMAT(tr.datetimeentry, '%Y')";
    $studentEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $classEsc = mysqli_real_escape_string($con, trim((string)$windowRow['classid']));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$windowRow['batchid']));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$windowRow['academicyear']));
    $termEsc = mysqli_real_escape_string($con, trim((string)$windowRow['termname']));
    $sql = "SELECT tr.userid
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid
        WHERE tr.status='active'
          AND su.systemtype='Student'
          AND su.status='active'
          AND tr.userid='$studentEsc'
          AND tr.class_entryid='$classEsc'
          AND tr.batchid='$batchEsc'
          AND ".$resolvedYearSql."='$yearEsc'
          AND tr.termname='$termEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('course_registration_fetch_student_windows')){
function course_registration_fetch_student_windows($con, $studentId){
    $rows = array();
    $studentEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $resolvedYearSql = function_exists('semester_registry_resolved_year_sql') ? semester_registry_resolved_year_sql('tr') : "DATE_FORMAT(tr.datetimeentry, '%Y')";
    $sql = "SELECT DISTINCT
            w.*,
            ce.class_name,
            COALESCE(b.batch, 'Batch not set') AS batch,
            ss.submittedat,
            ss.selectedcount,
            ss.status AS submission_status
        FROM tblcourseregwindow w
        INNER JOIN tbltermregistry tr
            ON tr.class_entryid=w.classid
           AND tr.batchid=w.batchid
           AND ".$resolvedYearSql."=w.academicyear
           AND tr.termname=w.termname
           AND tr.status='active'
        LEFT JOIN tblstudentcourseregstatus ss
            ON ss.windowid=w.windowid
           AND ss.userid='$studentEsc'
        LEFT JOIN tblclassentry ce ON ce.class_entryid=w.classid
        LEFT JOIN tblbatch b ON b.batchid=w.batchid
        WHERE tr.userid='$studentEsc'
        ORDER BY w.status='open' DESC, w.academicyear DESC, CAST(w.termname AS UNSIGNED) DESC, w.datetimeentry DESC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row['session_label'] = course_registration_session_label($row['academicyear'], $row['batch'], $row['termname']);
            $row['is_open_now'] = course_registration_window_is_open($row) ? 1 : 0;
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('course_registration_fetch_student_selection_map')){
function course_registration_fetch_student_selection_map($con, $windowId, $studentId){
    $map = array();
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $studentEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $result = mysqli_query($con, "SELECT assignmentid FROM tblstudentcourseregistration WHERE windowid='$windowEsc' AND userid='$studentEsc' AND status='active'");
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $assignmentId = trim((string)$row['assignmentid']);
            if($assignmentId !== ''){
                $map[$assignmentId] = true;
            }
        }
    }
    return $map;
}
}

if(!function_exists('course_registration_fetch_student_submission_row')){
function course_registration_fetch_student_submission_row($con, $windowId, $studentId){
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $studentEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $result = mysqli_query($con, "SELECT * FROM tblstudentcourseregstatus WHERE windowid='$windowEsc' AND userid='$studentEsc' LIMIT 1");
    return ($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))) ? $row : null;
}
}

if(!function_exists('course_registration_save_student_selection_core')){
function course_registration_save_student_selection_core($con, $windowId, $studentId, $selectedAssignmentIds, $submittedBy, $requireWindowOpen = true, $successMessage = 'Your semester course registration has been saved.'){
    course_registration_ensure_tables($con);
    $windowRow = course_registration_fetch_window_by_id($con, $windowId);
    if(!$windowRow){
        return array('ok' => false, 'message' => 'The selected registration window could not be found.');
    }
    if(!course_registration_student_is_eligible_for_window($con, $studentId, $windowRow)){
        return array('ok' => false, 'message' => 'This semester course window is not linked to your registered class for that period.');
    }
    if($requireWindowOpen && !course_registration_window_is_open($windowRow)){
        return array('ok' => false, 'message' => 'Course registration is currently closed for this semester.');
    }

    $offerings = course_registration_fetch_offerings($con, $windowId);
    if(count($offerings) === 0){
        return array('ok' => false, 'message' => 'No courses are available in this registration window yet.');
    }

    $existingSubmissionRow = course_registration_fetch_student_submission_row($con, $windowId, $studentId);

    $selectedMap = array();
    if(is_array($selectedAssignmentIds)){
        foreach($selectedAssignmentIds as $assignmentId){
            $assignmentId = trim((string)$assignmentId);
            if($assignmentId !== ''){
                $selectedMap[$assignmentId] = true;
            }
        }
    }

    $compulsoryIds = array();
    $electiveIds = array();
    $offeringMap = array();
    foreach($offerings as $offeringRow){
        $assignmentId = trim((string)$offeringRow['assignmentid']);
        $offeringMap[$assignmentId] = $offeringRow;
        if(strtolower(trim((string)$offeringRow['selectiontype'])) === 'elective'){
            $electiveIds[$assignmentId] = true;
        }else{
            $compulsoryIds[$assignmentId] = true;
        }
    }

    $cleanElectiveIds = array();
    foreach($selectedMap as $assignmentId => $flag){
        if(isset($electiveIds[$assignmentId])){
            $cleanElectiveIds[$assignmentId] = true;
        }
    }
    $electiveCount = count($cleanElectiveIds);
    $minimumElectives = max(0, (int)$windowRow['minimum_electives']);
    $maximumElectives = max(0, (int)$windowRow['maximum_electives']);
    if($electiveCount < $minimumElectives){
        return array('ok' => false, 'message' => 'Select at least '.$minimumElectives.' elective course'.($minimumElectives === 1 ? '' : 's').' before submitting.');
    }
    if($maximumElectives > 0 && $electiveCount > $maximumElectives){
        return array('ok' => false, 'message' => 'You can select only '.$maximumElectives.' elective course'.($maximumElectives === 1 ? '' : 's').' for this registration window.');
    }

    $finalAssignmentIds = array_keys($compulsoryIds + $cleanElectiveIds);
    if(count($finalAssignmentIds) === 0){
        return array('ok' => false, 'message' => 'There are no valid courses selected for submission.');
    }

    $studentEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $submittedByEsc = mysqli_real_escape_string($con, trim((string)$submittedBy));
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $classEsc = mysqli_real_escape_string($con, trim((string)$windowRow['classid']));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$windowRow['batchid']));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$windowRow['academicyear']));
    $termEsc = mysqli_real_escape_string($con, trim((string)$windowRow['termname']));

    @mysqli_query($con, "START TRANSACTION");
    $deleteRegistrationsOk = @mysqli_query($con, "DELETE FROM tblstudentcourseregistration WHERE windowid='$windowEsc' AND userid='$studentEsc'");
    if(!$deleteRegistrationsOk){
        @mysqli_query($con, "ROLLBACK");
        return array('ok' => false, 'message' => 'Previous course selections could not be refreshed: '.mysqli_error($con));
    }

    foreach($finalAssignmentIds as $assignmentId){
        if(!isset($offeringMap[$assignmentId])){
            continue;
        }
        $offeringRow = $offeringMap[$assignmentId];
        $assignmentIdEsc = mysqli_real_escape_string($con, $assignmentId);
        $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['teacherid']));
        $classificationIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['classificationid']));
        $subjectIdEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['subjectid']));
        $subjectLabelEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['subjectlabel']));
        $selectionTypeEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['selectiontype']));
        $insertOk = @mysqli_query($con, "INSERT INTO tblstudentcourseregistration(windowid,userid,assignmentid,teacherid,classid,batchid,academicyear,termname,classificationid,subjectid,subjectlabel,selectiontype,status,datetimeentry,submittedby)
            VALUES('$windowEsc','$studentEsc','$assignmentIdEsc','$teacherIdEsc','$classEsc','$batchEsc','$yearEsc','$termEsc','$classificationIdEsc','$subjectIdEsc','$subjectLabelEsc','$selectionTypeEsc','active',NOW(),'$submittedByEsc')");
        if(!$insertOk){
            @mysqli_query($con, "ROLLBACK");
            return array('ok' => false, 'message' => 'One of the selected courses could not be saved: '.mysqli_error($con));
        }
    }

    $selectedCount = count($finalAssignmentIds);
    $statusOk = @mysqli_query($con, "INSERT INTO tblstudentcourseregstatus(windowid,userid,classid,batchid,academicyear,termname,selectedcount,status,submittedat,lastupdatedat,submittedby)
        VALUES('$windowEsc','$studentEsc','$classEsc','$batchEsc','$yearEsc','$termEsc','$selectedCount','submitted',NOW(),NOW(),'$submittedByEsc')
        ON DUPLICATE KEY UPDATE
            classid=VALUES(classid),
            batchid=VALUES(batchid),
            academicyear=VALUES(academicyear),
            termname=VALUES(termname),
            selectedcount=VALUES(selectedcount),
            status='submitted',
            submittedat=NOW(),
            lastupdatedat=NOW(),
            submittedby=VALUES(submittedby)");
    if(!$statusOk){
        @mysqli_query($con, "ROLLBACK");
        return array('ok' => false, 'message' => 'The course registration submission could not be finalized: '.mysqli_error($con));
    }

    @mysqli_query($con, "COMMIT");
    if($successMessage === 'Your semester course registration has been saved.' && $existingSubmissionRow){
        $successMessage = 'Your semester course registration has been updated.';
    }

    return array('ok' => true, 'message' => $successMessage, 'selectedcount' => $selectedCount);
}
}

if(!function_exists('course_registration_submit_student_selection')){
function course_registration_submit_student_selection($con, $windowId, $studentId, $selectedAssignmentIds, $submittedBy){
    return course_registration_save_student_selection_core(
        $con,
        $windowId,
        $studentId,
        $selectedAssignmentIds,
        $submittedBy,
        true,
        'Your semester course registration has been saved.'
    );
}
}

if(!function_exists('course_registration_admin_update_student_selection')){
function course_registration_admin_update_student_selection($con, $windowId, $studentId, $selectedAssignmentIds, $submittedBy){
    return course_registration_save_student_selection_core(
        $con,
        $windowId,
        $studentId,
        $selectedAssignmentIds,
        $submittedBy,
        false,
        'Student semester course registration updated successfully.'
    );
}
}

if(!function_exists('course_registration_window_summary')){
function course_registration_window_summary($con, $windowId){
    $windowRow = course_registration_fetch_window_by_id($con, $windowId);
    if(!$windowRow){
        return array('eligible_count' => 0, 'submitted_count' => 0, 'outstanding_count' => 0, 'course_count' => 0, 'selection_count' => 0);
    }

    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $submittedResult = mysqli_query($con, "SELECT COUNT(*) AS total_submitted FROM tblstudentcourseregstatus WHERE windowid='$windowEsc' AND status='submitted'");
    $courseResult = mysqli_query($con, "SELECT COUNT(*) AS total_courses FROM tblcourseregoffering WHERE windowid='$windowEsc' AND status='active'");
    $selectionResult = mysqli_query($con, "SELECT COUNT(*) AS total_selected FROM tblstudentcourseregistration WHERE windowid='$windowEsc' AND status='active'");

    $eligibleCount = course_registration_scope_student_count($con, $windowRow['classid'], $windowRow['batchid'], $windowRow['academicyear'], $windowRow['termname']);
    $submittedCount = ($submittedResult && ($row = mysqli_fetch_array($submittedResult, MYSQLI_ASSOC))) ? (int)$row['total_submitted'] : 0;
    $courseCount = ($courseResult && ($row = mysqli_fetch_array($courseResult, MYSQLI_ASSOC))) ? (int)$row['total_courses'] : 0;
    $selectionCount = ($selectionResult && ($row = mysqli_fetch_array($selectionResult, MYSQLI_ASSOC))) ? (int)$row['total_selected'] : 0;
    return array(
        'eligible_count' => $eligibleCount,
        'submitted_count' => $submittedCount,
        'outstanding_count' => max(0, $eligibleCount - $submittedCount),
        'course_count' => $courseCount,
        'selection_count' => $selectionCount
    );
}
}

if(!function_exists('course_registration_resolve_assignment_for_offering')){
function course_registration_resolve_assignment_for_offering($con, $windowRow, $offeringRow, $teacherIdFilter = ''){
    if(!$windowRow || !$offeringRow){
        return null;
    }

    $classEsc = mysqli_real_escape_string($con, trim((string)$windowRow['classid']));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$windowRow['batchid']));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$windowRow['academicyear']));
    $termEsc = mysqli_real_escape_string($con, trim((string)$windowRow['termname']));
    $classificationEsc = mysqli_real_escape_string($con, trim((string)(isset($offeringRow['classificationid']) ? $offeringRow['classificationid'] : '')));
    $subjectEsc = mysqli_real_escape_string($con, trim((string)(isset($offeringRow['subjectid']) ? $offeringRow['subjectid'] : '')));
    $teacherSql = '';
    if(trim((string)$teacherIdFilter) !== ''){
        $teacherSql = " AND sa.userid='".mysqli_real_escape_string($con, trim((string)$teacherIdFilter))."'";
    }
    $assignmentYearSql = function_exists('semester_registry_assignment_year_sql') ? semester_registry_assignment_year_sql('sa') : "DATE_FORMAT(sa.datetimeentry, '%Y')";

    $sql = "SELECT
            sa.assignmentid,
            sa.userid AS teacherid,
            sc.subjectid,
            tu.firstname,
            tu.othernames,
            tu.surname
        FROM tblsubjectassignment sa
        INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        LEFT JOIN tblsystemuser tu ON tu.userid=sa.userid
        WHERE sa.status='active'
          AND sa.classid='$classEsc'
          AND sa.batchid='$batchEsc'
          AND sa.termname='$termEsc'
          AND ".$assignmentYearSql."='$yearEsc'
          AND (sa.classificationid='$classificationEsc' OR sc.subjectid='$subjectEsc')
          $teacherSql
        ORDER BY (sa.classificationid='$classificationEsc') DESC, sa.datetimeentry DESC
        LIMIT 1";

    $result = mysqli_query($con, $sql);
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        $row['teacherlabel'] = course_registration_teacher_label($row);
        return $row;
    }
    return null;
}
}

if(!function_exists('course_registration_fetch_admin_course_rows')){
function course_registration_fetch_admin_course_rows($con, $windowId){
    $rows = array();
    $windowRow = course_registration_fetch_window_by_id($con, $windowId);
    if(!$windowRow){
        return $rows;
    }
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $sql = "SELECT
            o.windowid,
            o.assignmentid,
            o.teacherid,
            o.classid,
            o.classificationid,
            o.subjectid,
            o.subjectlabel,
            o.teacherlabel,
            o.selectiontype,
            COUNT(DISTINCT r.userid) AS registered_students,
            GROUP_CONCAT(DISTINCT CONCAT(
                TRIM(CONCAT(COALESCE(su.firstname,''),' ',COALESCE(su.othernames,''),' ',COALESCE(su.surname,''))),
                ' (',su.userid,')'
            ) ORDER BY su.firstname ASC SEPARATOR '||') AS student_labels
        FROM tblcourseregoffering o
        LEFT JOIN tblstudentcourseregistration r
            ON r.windowid=o.windowid
           AND r.assignmentid=o.assignmentid
           AND r.status='active'
        LEFT JOIN tblsystemuser su
            ON su.userid=r.userid
        WHERE o.windowid='$windowEsc'
          AND o.status='active'
        GROUP BY o.windowid,o.assignmentid,o.teacherid,o.classid,o.classificationid,o.subjectid,o.subjectlabel,o.teacherlabel,o.selectiontype
        ORDER BY o.subjectlabel ASC, o.teacherlabel ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row['student_list'] = array();
            $labels = trim((string)$row['student_labels']);
            if($labels !== ''){
                $row['student_list'] = explode('||', $labels);
            }
            $resolvedAssignment = course_registration_resolve_assignment_for_offering($con, $windowRow, $row);
            if($resolvedAssignment){
                $row['teacherid'] = trim((string)$resolvedAssignment['teacherid']);
                $row['teacherlabel'] = trim((string)$resolvedAssignment['teacherlabel']);
                $row['assignmentid'] = trim((string)$resolvedAssignment['assignmentid']);
            }elseif(trim((string)$row['teacherlabel']) === ''){
                $row['teacherlabel'] = 'Teacher not assigned yet';
            }
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('course_registration_fetch_admin_student_rows')){
function course_registration_fetch_admin_student_rows($con, $windowId){
    $rows = array();
    $windowRow = course_registration_fetch_window_by_id($con, $windowId);
    if(!$windowRow){
        return $rows;
    }

    $resolvedYearSql = function_exists('semester_registry_resolved_year_sql') ? semester_registry_resolved_year_sql('tr') : "DATE_FORMAT(tr.datetimeentry, '%Y')";
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $classEsc = mysqli_real_escape_string($con, trim((string)$windowRow['classid']));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$windowRow['batchid']));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$windowRow['academicyear']));
    $termEsc = mysqli_real_escape_string($con, trim((string)$windowRow['termname']));

    $sql = "SELECT
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            ss.submittedat,
            ss.selectedcount,
            GROUP_CONCAT(DISTINCT r.subjectlabel ORDER BY r.subjectlabel ASC SEPARATOR ' | ') AS course_labels
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su
            ON su.userid=tr.userid
           AND su.systemtype='Student'
           AND su.status='active'
        LEFT JOIN tblstudentcourseregstatus ss
            ON ss.windowid='$windowEsc'
           AND ss.userid=su.userid
        LEFT JOIN tblstudentcourseregistration r
            ON r.windowid='$windowEsc'
           AND r.userid=su.userid
           AND r.status='active'
        WHERE tr.status='active'
          AND tr.class_entryid='$classEsc'
          AND tr.batchid='$batchEsc'
          AND ".$resolvedYearSql."='$yearEsc'
          AND tr.termname='$termEsc'
        GROUP BY su.userid,su.firstname,su.othernames,su.surname,ss.submittedat,ss.selectedcount
        ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row['student_name'] = course_registration_person_name($row);
            $row['is_submitted'] = trim((string)$row['submittedat']) !== '' ? 1 : 0;
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('course_registration_fetch_teacher_rows')){
function course_registration_fetch_teacher_rows($con, $teacherId, $academicYear = '', $termName = ''){
    $rows = array();
    $teacherId = trim((string)$teacherId);
    if($teacherId === ''){
        return $rows;
    }

    $windowFilters = array("1=1");
    if(trim((string)$academicYear) !== ''){
        $windowFilters[] = "w.academicyear='".mysqli_real_escape_string($con, trim((string)$academicYear))."'";
    }
    if(trim((string)$termName) !== ''){
        $windowFilters[] = "w.termname='".mysqli_real_escape_string($con, trim((string)$termName))."'";
    }

    $windowSql = "SELECT w.*, ce.class_name, COALESCE(b.batch, 'Batch not set') AS batch
        FROM tblcourseregwindow w
        LEFT JOIN tblclassentry ce ON ce.class_entryid=w.classid
        LEFT JOIN tblbatch b ON b.batchid=w.batchid
        WHERE ".implode(' AND ', $windowFilters)."
        ORDER BY w.academicyear DESC, CAST(w.termname AS UNSIGNED) DESC, w.datetimeentry DESC";
    $windowResult = mysqli_query($con, $windowSql);
    if(!$windowResult){
        return $rows;
    }

    while($windowRow = mysqli_fetch_array($windowResult, MYSQLI_ASSOC)){
        $windowRow['session_label'] = course_registration_session_label($windowRow['academicyear'], $windowRow['batch'], $windowRow['termname']);
        $windowRow['eligible_students'] = course_registration_scope_student_count($con, $windowRow['classid'], $windowRow['batchid'], $windowRow['academicyear'], $windowRow['termname']);
        $offerings = course_registration_fetch_offerings($con, $windowRow['windowid']);
        foreach($offerings as $offeringRow){
            $resolvedAssignment = course_registration_resolve_assignment_for_offering($con, $windowRow, $offeringRow, $teacherId);
            if(!$resolvedAssignment){
                continue;
            }

            $offeringEsc = mysqli_real_escape_string($con, trim((string)$offeringRow['assignmentid']));
            $windowEsc = mysqli_real_escape_string($con, trim((string)$windowRow['windowid']));
            $registrationSql = "SELECT
                    COUNT(DISTINCT r.userid) AS registered_students,
                    GROUP_CONCAT(DISTINCT CONCAT(
                        TRIM(CONCAT(COALESCE(su.firstname,''),' ',COALESCE(su.othernames,''),' ',COALESCE(su.surname,''))),
                        ' (',su.userid,')'
                    ) ORDER BY su.firstname ASC SEPARATOR '||') AS student_labels
                FROM tblstudentcourseregistration r
                LEFT JOIN tblsystemuser su ON su.userid=r.userid
                WHERE r.windowid='$windowEsc'
                  AND r.status='active'
                  AND (r.assignmentid='$offeringEsc'
                       OR r.classificationid='".mysqli_real_escape_string($con, trim((string)$offeringRow['classificationid']))."'
                       OR (r.subjectid='".mysqli_real_escape_string($con, trim((string)$offeringRow['subjectid']))."'
                           AND r.classid='".mysqli_real_escape_string($con, trim((string)$windowRow['classid']))."'
                           AND r.batchid='".mysqli_real_escape_string($con, trim((string)$windowRow['batchid']))."'
                           AND r.academicyear='".mysqli_real_escape_string($con, trim((string)$windowRow['academicyear']))."'
                           AND r.termname='".mysqli_real_escape_string($con, trim((string)$windowRow['termname']))."'))";
            $registrationResult = mysqli_query($con, $registrationSql);
            $registeredStudents = 0;
            $studentList = array();
            if($registrationResult && ($registrationRow = mysqli_fetch_array($registrationResult, MYSQLI_ASSOC))){
                $registeredStudents = (int)$registrationRow['registered_students'];
                $labels = trim((string)$registrationRow['student_labels']);
                if($labels !== ''){
                    $studentList = explode('||', $labels);
                }
            }

            $rows[] = array(
                'windowid' => $windowRow['windowid'],
                'window_status' => $windowRow['status'],
                'openfrom' => $windowRow['openfrom'],
                'closeat' => $windowRow['closeat'],
                'academicyear' => $windowRow['academicyear'],
                'termname' => $windowRow['termname'],
                'classid' => $windowRow['classid'],
                'batchid' => $windowRow['batchid'],
                'class_name' => $windowRow['class_name'],
                'batch' => $windowRow['batch'],
                'assignmentid' => $resolvedAssignment['assignmentid'],
                'subjectlabel' => $offeringRow['subjectlabel'],
                'teacherlabel' => $resolvedAssignment['teacherlabel'],
                'selectiontype' => $offeringRow['selectiontype'],
                'registered_students' => $registeredStudents,
                'session_label' => $windowRow['session_label'],
                'eligible_students' => $windowRow['eligible_students'],
                'student_list' => $studentList
            );
        }
    }

    return $rows;
}
}
?>

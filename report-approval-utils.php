<?php
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    $key = trim((string)$key);
    if($key === ''){
        return false;
    }
    $ttlSeconds = (int)$ttlSeconds;
    if($ttlSeconds <= 0){
        $ttlSeconds = 900;
    }
    $cacheBag = isset($_SESSION['_xschool_schema_cache']) && is_array($_SESSION['_xschool_schema_cache'])
        ? $_SESSION['_xschool_schema_cache']
        : array();
    if(!isset($cacheBag[$key])){
        return false;
    }
    return ((time() - (int)$cacheBag[$key]) < $ttlSeconds);
}
}

if(!function_exists('xschool_schema_cache_mark')){
function xschool_schema_cache_mark($key){
    $key = trim((string)$key);
    if($key === ''){
        return;
    }
    if(!isset($_SESSION['_xschool_schema_cache']) || !is_array($_SESSION['_xschool_schema_cache'])){
        $_SESSION['_xschool_schema_cache'] = array();
    }
    $_SESSION['_xschool_schema_cache'][$key] = time();
}
}

if(!function_exists('report_approval_normalize_year')){
function report_approval_normalize_year($academicYear){
    $academicYear = trim((string)$academicYear);
    if($academicYear === ''){
        return '';
    }
    if(is_numeric($academicYear)){
        return (string)((int)$academicYear);
    }
    return $academicYear;
}
}

if(!function_exists('report_approval_scope_requires_release')){
function report_approval_scope_requires_release($academicYear, $termName){
    $year = (int)report_approval_normalize_year($academicYear);
    $term = (int)trim((string)$termName);
    if($year <= 0 || $term <= 0){
        return false;
    }
    if($year > 2026){
        return true;
    }
    return ($year === 2026 && $term >= 2);
}
}

if(!function_exists('report_approval_is_admin_user')){
function report_approval_is_admin_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])
        && $_SESSION['ACCESSLEVEL'] === 'administrator'
        && in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('report_approval_is_student_user')){
function report_approval_is_student_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])
        && $_SESSION['ACCESSLEVEL'] === 'user'
        && $_SESSION['SYSTEMTYPE'] === 'Student';
}
}

if(!function_exists('report_approval_scope_cache_key')){
function report_approval_scope_cache_key($batchId, $academicYear, $termName, $classId){
    return trim((string)$batchId).'|'.report_approval_normalize_year($academicYear).'|'.(int)trim((string)$termName).'|'.trim((string)$classId);
}
}

if(!function_exists('report_approval_scope_cache_forget')){
function report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId){
    $cacheKey = report_approval_scope_cache_key($batchId, $academicYear, $termName, $classId);
    if(isset($GLOBALS['_report_approval_scope_meta_cache'][$cacheKey])){
        unset($GLOBALS['_report_approval_scope_meta_cache'][$cacheKey]);
    }
}
}

if(!function_exists('report_approval_column_exists')){
function report_approval_column_exists($con, $tableName, $columnName){
    if(!$con){
        return false;
    }
    $tableSafe = mysqli_real_escape_string($con, trim((string)$tableName));
    $columnSafe = mysqli_real_escape_string($con, trim((string)$columnName));
    $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('report_approval_ensure_table')){
function report_approval_ensure_table($con){
    if(!$con){
        return;
    }
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_tblclassreportapproval_v2')){
        return;
    }
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblclassreportapproval (
        approvalid BIGINT NOT NULL AUTO_INCREMENT,
        batchid VARCHAR(100) NOT NULL,
        academicyear VARCHAR(10) NOT NULL DEFAULT '',
        termname INT NOT NULL DEFAULT 0,
        classid VARCHAR(100) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'approved',
        approvedby VARCHAR(100) NOT NULL DEFAULT '',
        approveddatetime DATETIME NULL,
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updateddatetime DATETIME NULL,
        PRIMARY KEY (approvalid),
        UNIQUE KEY uq_report_scope (batchid, academicyear, termname, classid),
        KEY idx_report_scope_status (batchid, academicyear, termname, classid, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'scoreeditoverride')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN scoreeditoverride TINYINT(1) NOT NULL DEFAULT 0 AFTER approveddatetime");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'scoreeditoverrideby')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN scoreeditoverrideby VARCHAR(100) NOT NULL DEFAULT '' AFTER scoreeditoverride");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'scoreeditoverridedatetime')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN scoreeditoverridedatetime DATETIME NULL AFTER scoreeditoverrideby");
    }
    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_tblclassreportapproval_v2');
    }
}
}

if(!function_exists('report_approval_scope_meta')){
function report_approval_scope_meta($con, $batchId, $academicYear, $termName, $classId){
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $cacheKey = report_approval_scope_cache_key($batchId, $academicYear, $termName, $classId);
    if(isset($GLOBALS['_report_approval_scope_meta_cache'][$cacheKey])){
        return $GLOBALS['_report_approval_scope_meta_cache'][$cacheKey];
    }

    $required = report_approval_scope_requires_release($academicYear, $termName);
    $meta = array(
        'required' => $required,
        'approved' => false,
        'allowed' => !$required,
        'status' => $required ? 'pending' : 'not_required',
        'status_label' => $required ? 'Awaiting Admin Approval' : 'No Approval Needed',
        'approvedby' => '',
        'approveddatetime' => '',
        'score_edit_locked' => false,
        'score_edit_allowed' => true,
        'score_edit_override_enabled' => false,
        'score_edit_status' => 'open',
        'score_edit_status_label' => $required ? 'Open Until Approval' : 'Open for Score Entry',
        'score_edit_override_by' => '',
        'score_edit_override_datetime' => ''
    );

    if(!$required || !$con || $batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        $GLOBALS['_report_approval_scope_meta_cache'][$cacheKey] = $meta;
        return $meta;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $sql = "SELECT
                status,
                approvedby,
                approveddatetime,
                COALESCE(scoreeditoverride, 0) AS scoreeditoverride,
                COALESCE(scoreeditoverrideby, '') AS scoreeditoverrideby,
                scoreeditoverridedatetime
            FROM tblclassreportapproval
            WHERE batchid='$batchIdEsc'
              AND academicyear='$academicYearEsc'
              AND termname='$termName'
              AND classid='$classIdEsc'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $status = strtolower(trim((string)$row['status']));
        if($status === 'approved'){
            $meta['approved'] = true;
            $meta['allowed'] = true;
            $meta['status'] = 'approved';
            $meta['status_label'] = 'Approved for Students';
            $overrideEnabled = ((int)$row['scoreeditoverride'] === 1);
            if($overrideEnabled){
                $meta['score_edit_locked'] = false;
                $meta['score_edit_allowed'] = true;
                $meta['score_edit_override_enabled'] = true;
                $meta['score_edit_status'] = 'temporary_override';
                $meta['score_edit_status_label'] = 'Temporary Correction Window';
            }else{
                $meta['score_edit_locked'] = true;
                $meta['score_edit_allowed'] = false;
                $meta['score_edit_override_enabled'] = false;
                $meta['score_edit_status'] = 'locked_after_approval';
                $meta['score_edit_status_label'] = 'Locked After Approval';
            }
        }else{
            $meta['status'] = 'pending';
            $meta['status_label'] = 'Awaiting Admin Approval';
            $meta['score_edit_status'] = 'open';
            $meta['score_edit_status_label'] = 'Open Until Approval';
        }
        $meta['approvedby'] = trim((string)$row['approvedby']);
        $meta['approveddatetime'] = trim((string)$row['approveddatetime']);
        $meta['score_edit_override_by'] = trim((string)$row['scoreeditoverrideby']);
        $meta['score_edit_override_datetime'] = trim((string)$row['scoreeditoverridedatetime']);
    }

    $GLOBALS['_report_approval_scope_meta_cache'][$cacheKey] = $meta;
    return $meta;
}
}

if(!function_exists('report_approval_set_scope_status')){
function report_approval_set_scope_status($con, $batchId, $academicYear, $termName, $classId, $status, $approvedBy){
    if(!$con){
        return false;
    }
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $status = strtolower(trim((string)$status)) === 'approved' ? 'approved' : 'pending';
    $approvedBy = trim((string)$approvedBy);

    if($batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        return false;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $statusEsc = mysqli_real_escape_string($con, $status);
    $approvedByEsc = mysqli_real_escape_string($con, $approvedBy);
    $approvalTimeSql = ($status === 'approved') ? 'NOW()' : 'NULL';
    $result = @mysqli_query($con, "INSERT INTO tblclassreportapproval(batchid, academicyear, termname, classid, status, approvedby, approveddatetime, datetimeentry, updateddatetime)
        VALUES('$batchIdEsc', '$academicYearEsc', '$termName', '$classIdEsc', '$statusEsc', '$approvedByEsc', $approvalTimeSql, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            approvedby=VALUES(approvedby),
            approveddatetime=$approvalTimeSql,
            scoreeditoverride=0,
            scoreeditoverrideby='',
            scoreeditoverridedatetime=NULL,
            updateddatetime=NOW()");
    if($result){
        report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId);
    }
    return (bool)$result;
}
}

if(!function_exists('report_approval_set_score_edit_override')){
function report_approval_set_score_edit_override($con, $batchId, $academicYear, $termName, $classId, $enabled, $updatedBy){
    if(!$con){
        return false;
    }
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $updatedBy = trim((string)$updatedBy);
    $enabled = ($enabled ? 1 : 0);

    if($batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        return false;
    }

    $currentMeta = report_approval_scope_meta($con, $batchId, $academicYear, $termName, $classId);
    if(!$currentMeta['required'] || !$currentMeta['approved']){
        return false;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $updatedByEsc = mysqli_real_escape_string($con, $updatedBy);
    $overrideTimeSql = $enabled ? 'NOW()' : 'NULL';
    $overrideBySql = $enabled ? "'".$updatedByEsc."'" : "''";
    $result = @mysqli_query($con, "UPDATE tblclassreportapproval
        SET scoreeditoverride='$enabled',
            scoreeditoverrideby=$overrideBySql,
            scoreeditoverridedatetime=$overrideTimeSql,
            updateddatetime=NOW()
        WHERE batchid='$batchIdEsc'
          AND academicyear='$academicYearEsc'
          AND termname='$termName'
          AND classid='$classIdEsc'
          AND status='approved'
        LIMIT 1");
    if($result){
        report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId);
    }
    return (bool)$result;
}
}

if(!function_exists('report_approval_assignment_scope')){
function report_approval_assignment_scope($con, $assignmentId){
    if(!$con){
        return null;
    }
    $assignmentId = trim((string)$assignmentId);
    if($assignmentId === ''){
        return null;
    }
    $assignmentIdEsc = mysqli_real_escape_string($con, $assignmentId);
    $sql = "SELECT
            sa.assignmentid,
            sa.classid,
            sa.batchid,
            sa.termname,
            DATE_FORMAT(sa.datetimeentry, '%Y') AS assignment_year
        FROM tblsubjectassignment sa
        WHERE sa.assignmentid='$assignmentIdEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('report_approval_assignment_scope_meta')){
function report_approval_assignment_scope_meta($con, $assignmentId){
    $scope = report_approval_assignment_scope($con, $assignmentId);
    if(!$scope){
        return null;
    }
    $meta = report_approval_scope_meta($con, $scope['batchid'], $scope['assignment_year'], $scope['termname'], $scope['classid']);
    $meta['scope'] = $scope;
    return $meta;
}
}

if(!function_exists('report_approval_mark_scope_meta')){
function report_approval_mark_scope_meta($con, $markId){
    if(!$con){
        return null;
    }
    $markId = trim((string)$markId);
    if($markId === ''){
        return null;
    }
    $markIdEsc = mysqli_real_escape_string($con, $markId);
    $sql = "SELECT
            mk.markid,
            mk.assignmentid,
            sa.classid,
            sa.batchid,
            sa.termname,
            DATE_FORMAT(sa.datetimeentry, '%Y') AS assignment_year
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
        WHERE mk.markid='$markIdEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if(!$result || !($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return null;
    }
    $meta = report_approval_scope_meta($con, $row['batchid'], $row['assignment_year'], $row['termname'], $row['classid']);
    $meta['scope'] = $row;
    return $meta;
}
}

if(!function_exists('report_approval_score_edit_locked_message')){
function report_approval_score_edit_locked_message(){
    return "This score sheet is locked because the class result has already been approved. Ask the administrator to reopen score editing for this class and semester.";
}
}

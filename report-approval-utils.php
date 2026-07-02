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

if(!function_exists('report_approval_ensure_table')){
function report_approval_ensure_table($con){
    if(!$con){
        return;
    }
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_tblclassreportapproval_v1')){
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
    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_tblclassreportapproval_v1');
    }
}
}

if(!function_exists('report_approval_scope_meta')){
function report_approval_scope_meta($con, $batchId, $academicYear, $termName, $classId){
    static $memoryCache = array();

    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $cacheKey = $batchId.'|'.$academicYear.'|'.$termName.'|'.$classId;
    if(isset($memoryCache[$cacheKey])){
        return $memoryCache[$cacheKey];
    }

    $required = report_approval_scope_requires_release($academicYear, $termName);
    $meta = array(
        'required' => $required,
        'approved' => false,
        'allowed' => !$required,
        'status' => $required ? 'pending' : 'not_required',
        'status_label' => $required ? 'Awaiting Admin Approval' : 'No Approval Needed',
        'approvedby' => '',
        'approveddatetime' => ''
    );

    if(!$required || !$con || $batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        $memoryCache[$cacheKey] = $meta;
        return $meta;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $sql = "SELECT status, approvedby, approveddatetime
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
        }else{
            $meta['status'] = 'pending';
            $meta['status_label'] = 'Awaiting Admin Approval';
        }
        $meta['approvedby'] = trim((string)$row['approvedby']);
        $meta['approveddatetime'] = trim((string)$row['approveddatetime']);
    }

    $memoryCache[$cacheKey] = $meta;
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
            updateddatetime=NOW()");
    return (bool)$result;
}
}

<?php
if(file_exists(__DIR__.DIRECTORY_SEPARATOR."course-registration-utils.php")){
    include_once("course-registration-utils.php");
}

if(!function_exists('semester_registry_column_exists')){
function semester_registry_column_exists($con, $tableName, $columnName){
    $tableSafe = mysqli_real_escape_string($con, (string)$tableName);
    $columnSafe = mysqli_real_escape_string($con, (string)$columnName);
    $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('score_entry_assignment_window')){
function score_entry_assignment_window($con, $classId, $batchId, $assignmentYear, $termName){
    static $cache = array();
    $cacheKey = trim((string)$classId).'|'.trim((string)$batchId).'|'.trim((string)$assignmentYear).'|'.trim((string)$termName);
    if(isset($cache[$cacheKey])){
        return $cache[$cacheKey];
    }
    if(function_exists('course_registration_fetch_window_by_scope')){
        $cache[$cacheKey] = course_registration_fetch_window_by_scope($con, $classId, $batchId, $assignmentYear, $termName);
        return $cache[$cacheKey];
    }
    $cache[$cacheKey] = null;
    return null;
}
}

if(!function_exists('score_entry_registered_student_ids_from_window')){
function score_entry_registered_student_ids_from_window($con, $windowId, $assignmentId){
    $userIds = array();
    $windowEsc = mysqli_real_escape_string($con, trim((string)$windowId));
    $assignmentEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    $classificationEsc = '';
    $subjectEsc = '';
    $classEsc = '';
    $batchEsc = '';
    $yearEsc = '';
    $termEsc = '';

    $assignmentScopeSql = "SELECT
            sa.classid,
            sa.batchid,
            sa.termname,
            ".semester_registry_assignment_year_sql("sa")." AS assignment_year,
            sa.classificationid,
            sc.subjectid
        FROM tblsubjectassignment sa
        LEFT JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        WHERE sa.assignmentid='$assignmentEsc'
        LIMIT 1";
    $assignmentScopeResult = mysqli_query($con, $assignmentScopeSql);
    if($assignmentScopeResult && ($assignmentScopeRow = mysqli_fetch_array($assignmentScopeResult, MYSQLI_ASSOC))){
        $classificationEsc = mysqli_real_escape_string($con, trim((string)$assignmentScopeRow['classificationid']));
        $subjectEsc = mysqli_real_escape_string($con, trim((string)$assignmentScopeRow['subjectid']));
        $classEsc = mysqli_real_escape_string($con, trim((string)$assignmentScopeRow['classid']));
        $batchEsc = mysqli_real_escape_string($con, trim((string)$assignmentScopeRow['batchid']));
        $yearEsc = mysqli_real_escape_string($con, trim((string)$assignmentScopeRow['assignment_year']));
        $termEsc = mysqli_real_escape_string($con, trim((string)$assignmentScopeRow['termname']));
    }

    $conditions = array("assignmentid='$assignmentEsc'");
    if($classificationEsc !== ''){
        $conditions[] = "classificationid='$classificationEsc'";
    }
    if($subjectEsc !== '' && $classEsc !== '' && $batchEsc !== '' && $yearEsc !== '' && $termEsc !== ''){
        $conditions[] = "(subjectid='$subjectEsc' AND classid='$classEsc' AND batchid='$batchEsc' AND academicyear='$yearEsc' AND termname='$termEsc')";
    }

    $sql = "SELECT DISTINCT userid
        FROM tblstudentcourseregistration
        WHERE windowid='$windowEsc'
          AND status='active'
          AND (".implode(' OR ', $conditions).")
        ORDER BY userid ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $userId = trim((string)$row['userid']);
            if($userId !== ''){
                $userIds[$userId] = $userId;
            }
        }
    }
    return array_values($userIds);
}
}

if(!function_exists('score_entry_term_registry_student_ids')){
function score_entry_term_registry_student_ids($con, $classId, $batchId, $assignmentYear, $termName){
    $userIds = array();
    $classEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $yearEsc = mysqli_real_escape_string($con, trim((string)$assignmentYear));
    $termEsc = mysqli_real_escape_string($con, trim((string)$termName));
    $sql = "SELECT DISTINCT tr.userid
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su
            ON su.userid=tr.userid
           AND su.systemtype='Student'
           AND su.status='active'
        WHERE tr.status='active'
          AND tr.class_entryid='$classEsc'
          AND tr.batchid='$batchEsc'
          AND ".semester_registry_resolved_year_sql("tr")."='$yearEsc'
          AND tr.termname='$termEsc'
        ORDER BY tr.userid ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $userId = trim((string)$row['userid']);
            if($userId !== ''){
                $userIds[$userId] = $userId;
            }
        }
    }
    return array_values($userIds);
}
}

if(!function_exists('score_entry_assignment_student_context')){
function score_entry_assignment_student_context($con, $assignmentId, $classId, $batchId, $assignmentYear, $termName){
    $windowRow = score_entry_assignment_window($con, $classId, $batchId, $assignmentYear, $termName);
    if($windowRow){
        return array(
            'uses_course_registration' => true,
            'window' => $windowRow,
            'userids' => score_entry_registered_student_ids_from_window($con, $windowRow['windowid'], $assignmentId)
        );
    }
    return array(
        'uses_course_registration' => false,
        'window' => null,
        'userids' => score_entry_term_registry_student_ids($con, $classId, $batchId, $assignmentYear, $termName)
    );
}
}

if(!function_exists('score_entry_saved_mark_count')){
function score_entry_saved_mark_count($con, $assignmentId, $scoreType, $userIds){
    $userIds = is_array($userIds) ? array_values(array_filter($userIds, function($value){
        return trim((string)$value) !== '';
    })) : array();
    if(count($userIds) === 0){
        return 0;
    }
    $assignmentEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    $scoreTypeEsc = mysqli_real_escape_string($con, trim((string)$scoreType));
    $userIdParts = array();
    foreach($userIds as $userId){
        $userIdParts[] = "'".mysqli_real_escape_string($con, trim((string)$userId))."'";
    }
    $sql = "SELECT COUNT(DISTINCT userid) AS total_saved
        FROM tblmark
        WHERE assignmentid='$assignmentEsc'
          AND testtype='$scoreTypeEsc'
          AND userid IN (".implode(',', $userIdParts).")";
    $result = mysqli_query($con, $sql);
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return (int)$row['total_saved'];
    }
    return 0;
}
}

if(!function_exists('semester_registry_ensure_academic_year_column')){
function semester_registry_ensure_academic_year_column($con){
    if(!semester_registry_column_exists($con, 'tbltermregistry', 'academicyear')){
        @mysqli_query($con, "ALTER TABLE tbltermregistry ADD COLUMN academicyear VARCHAR(10) NOT NULL DEFAULT '' AFTER batchid");
        @mysqli_query($con, "UPDATE tbltermregistry SET academicyear=CAST(YEAR(datetimeentry) AS CHAR) WHERE TRIM(COALESCE(academicyear,''))=''");
    }
}
}

if(!function_exists('semester_registry_normalize_year')){
function semester_registry_normalize_year($value){
    $value = trim((string)$value);
    if($value === ''){
        return '';
    }
    if(preg_match('/^\d{4}$/', $value) !== 1){
        return '';
    }
    $yearValue = (int)$value;
    if($yearValue < 2000 || $yearValue > 2100){
        return '';
    }
    return (string)$yearValue;
}
}

if(!function_exists('semester_registry_resolved_year_sql')){
function semester_registry_resolved_year_sql($alias = 'tr'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'tr';
    }
    return "COALESCE(NULLIF(TRIM(CONVERT(".$alias.".academicyear USING utf8mb4)),''), DATE_FORMAT(".$alias.".datetimeentry, '%Y'))";
}
}

if(!function_exists('semester_registry_assignment_year_sql')){
function semester_registry_assignment_year_sql($alias = 'sa'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'sa';
    }
    return "DATE_FORMAT(".$alias.".datetimeentry, '%Y')";
}
}

if(!function_exists('score_entry_esc')){
function score_entry_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('score_entry_alert')){
function score_entry_alert($type, $message){
    $class = "score-entry-alert score-entry-alert--info";
    if($type === "success"){
        $class = "score-entry-alert score-entry-alert--success";
    }elseif($type === "error"){
        $class = "score-entry-alert score-entry-alert--error";
    }elseif($type === "warning"){
        $class = "score-entry-alert score-entry-alert--warning";
    }
    return "<div class=\"$class\">".score_entry_esc($message)."</div>";
}
}

if(!function_exists('score_entry_build_url')){
function score_entry_build_url($page, $classId, $termId, $batchId, $subjectId, $prefillTotal = "", $yearId = ""){
    $params = array();
    if(trim((string)$classId) !== ""){
        $params['class_ID'] = trim((string)$classId);
    }
    if(trim((string)$termId) !== ""){
        $params['term_ID'] = trim((string)$termId);
    }
    if(trim((string)$batchId) !== ""){
        $params['batch_ID'] = trim((string)$batchId);
    }
    if(trim((string)$subjectId) !== ""){
        $params['subject_ID'] = trim((string)$subjectId);
    }
    if(trim((string)$yearId) !== ""){
        $params['year_ID'] = trim((string)$yearId);
    }
    if(trim((string)$prefillTotal) !== ""){
        $params['prefill_total'] = trim((string)$prefillTotal);
    }
    if(empty($params)){
        return $page;
    }
    return $page."?".http_build_query($params);
}
}

if(!function_exists('score_entry_status_meta')){
function score_entry_status_meta($totalStudents, $savedStudents){
    $totalStudents = (int)$totalStudents;
    $savedStudents = (int)$savedStudents;
    $pendingStudents = max($totalStudents - $savedStudents, 0);

    if($totalStudents === 0){
        return array(
            "label" => "No Students",
            "class" => "score-entry-status score-entry-status--empty",
            "pending" => 0
        );
    }

    if($pendingStudents === 0){
        return array(
            "label" => "Completed",
            "class" => "score-entry-status score-entry-status--done",
            "pending" => 0
        );
    }

    if($savedStudents > 0){
        return array(
            "label" => "Continue",
            "class" => "score-entry-status score-entry-status--progress",
            "pending" => $pendingStudents
        );
    }

    return array(
        "label" => "Start",
        "class" => "score-entry-status score-entry-status--start",
        "pending" => $pendingStudents
    );
}
}

if(!function_exists('score_entry_session_label')){
function score_entry_session_label($dateTimeValue, $batchLabel, $termValue, $yearOverride = ""){
    $yearValue = trim((string)$yearOverride);
    if($yearValue === ""){
        if(trim((string)$dateTimeValue) !== ""){
            $time = strtotime((string)$dateTimeValue);
            if($time){
                $yearValue = date("Y", $time);
            }
        }
    }
    if($yearValue === ""){
        $yearValue = date("Y");
    }

    $batchText = trim((string)$batchLabel);
    if($batchText === ""){
        $batchText = "Not Set";
    }

    $termText = trim((string)$termValue);
    if($termText === ""){
        $termText = "Not Set";
    }

    return trim($yearValue." Batch ".$batchText." Semester ".$termText);
}
}

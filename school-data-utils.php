<?php
if(!function_exists('school_data_column_exists')){
function school_data_column_exists($con, $tableName, $columnName){
    $tableSafe = mysqli_real_escape_string($con, (string)$tableName);
    $columnSafe = mysqli_real_escape_string($con, (string)$columnName);
    $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('school_data_ensure_academic_year_column')){
function school_data_ensure_academic_year_column($con){
    if(!$con){
        return;
    }
    if(!school_data_column_exists($con, 'tblschoolinfo', 'academicyear')){
        @mysqli_query($con, "ALTER TABLE tblschoolinfo ADD COLUMN academicyear VARCHAR(10) NOT NULL DEFAULT '' AFTER termname");
        @mysqli_query($con, "UPDATE tblschoolinfo SET academicyear=CAST(YEAR(datetimeentry) AS CHAR) WHERE TRIM(COALESCE(academicyear,''))=''");
    }
}
}

if(!function_exists('school_data_resolved_year_sql')){
function school_data_resolved_year_sql($alias = 'si'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'si';
    }
    return "COALESCE(NULLIF(TRIM(CONVERT(".$alias.".academicyear USING utf8mb4)),''), DATE_FORMAT(".$alias.".datetimeentry, '%Y'))";
}
}

if(!function_exists('school_data_fetch_scope')){
function school_data_fetch_scope($con, $batchId, $academicYear = '', $termName = ''){
    if(!$con){
        return null;
    }

    school_data_ensure_academic_year_column($con);

    $batchId = trim((string)$batchId);
    $academicYear = trim((string)$academicYear);
    $termName = trim((string)$termName);
    if($batchId === ''){
        return null;
    }

    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $yearEsc = mysqli_real_escape_string($con, $academicYear);
    $termEsc = mysqli_real_escape_string($con, $termName);
    $yearSql = school_data_resolved_year_sql('si');

    $queries = array();
    if($academicYear !== '' && $termName !== ''){
        $queries[] = "SELECT * FROM tblschoolinfo si
            WHERE si.batchid='$batchIdEsc'
              AND si.termname='$termEsc'
              AND $yearSql='$yearEsc'
            ORDER BY si.datetimeentry DESC
            LIMIT 1";
    }
    if($termName !== ''){
        $queries[] = "SELECT * FROM tblschoolinfo si
            WHERE si.batchid='$batchIdEsc'
              AND si.termname='$termEsc'
            ORDER BY $yearSql DESC, si.datetimeentry DESC
            LIMIT 1";
    }
    $queries[] = "SELECT * FROM tblschoolinfo si
        WHERE si.batchid='$batchIdEsc'
        ORDER BY $yearSql DESC, si.termname DESC, si.datetimeentry DESC
        LIMIT 1";

    foreach($queries as $sql){
        $res = mysqli_query($con, $sql);
        if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
            return $row;
        }
    }
    return null;
}
}

if(!function_exists('school_data_display_date')){
function school_data_display_date($value){
    $value = trim((string)$value);
    if($value === '' || $value === '0000-00-00'){
        return '';
    }
    $time = strtotime($value);
    if($time === false){
        return $value;
    }
    return date('d/m/Y', $time);
}
}

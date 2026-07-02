<?php
if(!function_exists('semester_registry_column_exists')){
function semester_registry_column_exists($con, $tableName, $columnName){
    $tableSafe = mysqli_real_escape_string($con, (string)$tableName);
    $columnSafe = mysqli_real_escape_string($con, (string)$columnName);
    $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
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

if(!function_exists('semester_registry_year_options')){
function semester_registry_year_options($startYear = null, $endYear = null){
    $currentYear = (int)date('Y');
    $startYear = $startYear === null ? min(2025, $currentYear - 1) : (int)$startYear;
    $endYear = $endYear === null ? max(2030, $currentYear + 4) : (int)$endYear;
    if($endYear < $startYear){
        $endYear = $startYear;
    }

    $years = array();
    for($year = $endYear; $year >= $startYear; $year--){
        $years[] = (string)$year;
    }
    return $years;
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

if(!function_exists('semester_registry_session_label')){
function semester_registry_session_label($academicYear, $batchLabel, $termValue){
    $academicYear = semester_registry_normalize_year($academicYear);
    if($academicYear === ''){
        $academicYear = date('Y');
    }

    $batchLabel = trim((string)$batchLabel);
    if($batchLabel === ''){
        $batchLabel = 'Not Set';
    }

    $termValue = trim((string)$termValue);
    if($termValue === ''){
        $termValue = 'Not Set';
    }

    return trim($academicYear.' Batch '.$batchLabel.' Semester '.$termValue);
}
}
?>

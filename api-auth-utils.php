<?php

if(!function_exists('api_auth_column_exists')){
function api_auth_column_exists($con, $table, $column){
    static $cache = array();
    $key = strtolower(trim((string)$table)).'.'.strtolower(trim((string)$column));
    if(isset($cache[$key])){
        return $cache[$key];
    }

    $tableEsc = mysqli_real_escape_string($con, trim((string)$table));
    $columnEsc = mysqli_real_escape_string($con, trim((string)$column));
    $res = @mysqli_query($con, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}
}

if(!function_exists('api_auth_expected_key')){
function api_auth_expected_key(){
    static $expectedKey = null;
    if($expectedKey === null){
        $expectedKey = md5("hnWZab3Fjs9IwEcABz47-B2Hdp9OIluKLfbRhvPaC-UNrk7ESwZz8H01afbI4B-kZUbfhQJ1OtGrSYI7c0u01-01-2020");
    }
    return $expectedKey;
}
}

if(!function_exists('ensure_api_auth_table')){
function ensure_api_auth_table($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblapi (
        valueid VARCHAR(10) NOT NULL DEFAULT '',
        apikey VARCHAR(80) DEFAULT NULL,
        startdate DATE DEFAULT NULL,
        enddate DATE DEFAULT NULL,
        status VARCHAR(20) DEFAULT NULL,
        apikeyvalid VARCHAR(200) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");

    if(!api_auth_column_exists($con, 'tblapi', 'valueid')){
        @mysqli_query($con, "ALTER TABLE tblapi ADD COLUMN valueid VARCHAR(10) NOT NULL DEFAULT '' FIRST");
    }
    if(!api_auth_column_exists($con, 'tblapi', 'apikey')){
        @mysqli_query($con, "ALTER TABLE tblapi ADD COLUMN apikey VARCHAR(80) DEFAULT NULL AFTER valueid");
    }
    if(!api_auth_column_exists($con, 'tblapi', 'startdate')){
        @mysqli_query($con, "ALTER TABLE tblapi ADD COLUMN startdate DATE DEFAULT NULL AFTER apikey");
    }
    if(!api_auth_column_exists($con, 'tblapi', 'enddate')){
        @mysqli_query($con, "ALTER TABLE tblapi ADD COLUMN enddate DATE DEFAULT NULL AFTER startdate");
    }
    if(!api_auth_column_exists($con, 'tblapi', 'status')){
        @mysqli_query($con, "ALTER TABLE tblapi ADD COLUMN status VARCHAR(20) DEFAULT NULL AFTER enddate");
    }
    if(!api_auth_column_exists($con, 'tblapi', 'apikeyvalid')){
        @mysqli_query($con, "ALTER TABLE tblapi ADD COLUMN apikeyvalid VARCHAR(200) NOT NULL DEFAULT '' AFTER status");
    }
}
}

if(!function_exists('api_auth_has_expected_key')){
function api_auth_has_expected_key($con, $requireInUse = false){
    if(!$con){
        return false;
    }

    $expectedKey = api_auth_expected_key();
    $sql = "SELECT apikey FROM tblapi WHERE apikey=?".($requireInUse ? " AND status='inuse'" : "")." LIMIT 1";
    $stmt = @mysqli_prepare($con, $sql);
    if(!$stmt){
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $expectedKey);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}
}

if(!function_exists('ensure_api_auth_record')){
function ensure_api_auth_record($con){
    static $ready = null;
    if($ready !== null){
        return $ready;
    }
    if(!$con){
        $ready = false;
        return $ready;
    }

    ensure_api_auth_table($con);

    if(api_auth_has_expected_key($con, true)){
        $ready = true;
        return $ready;
    }

    $expectedKey = api_auth_expected_key();
    $expectedKeyEsc = mysqli_real_escape_string($con, $expectedKey);

    @mysqli_query(
        $con,
        "UPDATE tblapi
         SET apikeyvalid='$expectedKeyEsc', status='inuse'
         WHERE apikey='$expectedKeyEsc'"
    );

    if(api_auth_has_expected_key($con, true)){
        $ready = true;
        return $ready;
    }

    $valueId = '1230eaPcN';
    $stmtInsert = @mysqli_prepare(
        $con,
        "INSERT INTO tblapi (valueid, apikey, startdate, enddate, status, apikeyvalid)
         VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 YEAR), 'inuse', ?)"
    );
    if($stmtInsert){
        mysqli_stmt_bind_param($stmtInsert, "sss", $valueId, $expectedKey, $expectedKey);
        @mysqli_stmt_execute($stmtInsert);
        mysqli_stmt_close($stmtInsert);
    }

    $ready = api_auth_has_expected_key($con, true);
    return $ready;
}
}

if(!function_exists('api_auth_is_ready')){
function api_auth_is_ready($con){
    if(!ensure_api_auth_record($con)){
        return false;
    }
    return api_auth_has_expected_key($con, true);
}
}
?>

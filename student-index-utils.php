<?php
if(!function_exists('student_index_column_exists')){
function student_index_column_exists($con, $table, $column){
    $tableEsc = mysqli_real_escape_string($con, (string)$table);
    $columnEsc = mysqli_real_escape_string($con, (string)$column);
    $res = mysqli_query($con, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
    return $res && mysqli_num_rows($res) > 0;
}
}

if(!function_exists('student_index_index_exists')){
function student_index_index_exists($con, $table, $indexName){
    $tableEsc = mysqli_real_escape_string($con, (string)$table);
    $indexEsc = mysqli_real_escape_string($con, (string)$indexName);
    $res = mysqli_query($con, "SHOW INDEX FROM `$tableEsc` WHERE Key_name='$indexEsc'");
    return $res && mysqli_num_rows($res) > 0;
}
}

if(!function_exists('student_index_ensure_schema')){
function student_index_ensure_schema($con){
    if(!student_index_column_exists($con, "tblsystemuser", "schoolindexnumber")){
        $added = mysqli_query($con, "ALTER TABLE tblsystemuser ADD COLUMN schoolindexnumber VARCHAR(40) NULL AFTER BECEIndexNumber");
        if(!$added){
            return false;
        }
    }
    if(!student_index_index_exists($con, "tblsystemuser", "idx_systemuser_schoolindexnumber")){
        @mysqli_query($con, "CREATE INDEX idx_systemuser_schoolindexnumber ON tblsystemuser(schoolindexnumber)");
    }
    return true;
}
}

if(!function_exists('student_index_batch_year_code')){
function student_index_batch_year_code($batchName){
    $batchName = trim((string)$batchName);
    if(preg_match('/(19|20)([0-9]{2})/', $batchName, $match)){
        return $match[2];
    }
    if(preg_match('/\b([0-9]{2})\b/', $batchName, $match)){
        return $match[1];
    }
    return date("y");
}
}

if(!function_exists('student_index_class_code')){
function student_index_class_code($className){
    $className = strtoupper(trim((string)$className));
    $className = preg_replace('/\s+/', ' ', str_replace('.', '', $className));
    if($className === ""){
        return "";
    }
    if(strpos($className, "AGRIC") !== false){
        return "AG";
    }
    if(strpos($className, "BUSINESS") !== false){
        return "BUS";
    }
    if(strpos($className, "HOME") !== false){
        return "HE";
    }
    if(strpos($className, "VISUAL") !== false){
        return "VA";
    }
    if(preg_match('/ARTS\s*([0-9]+[A-Z]?)/', $className, $match)){
        return "GA".$match[1];
    }
    if(strpos($className, "ARTS") !== false){
        return "GA";
    }

    $className = preg_replace('/^[0-9]+\s*/', '', $className);
    $words = preg_split('/[^A-Z0-9]+/', $className, -1, PREG_SPLIT_NO_EMPTY);
    $code = "";
    foreach($words as $word){
        if($word !== ""){
            $code .= substr($word, 0, 1);
        }
    }
    return substr($code, 0, 8);
}
}

if(!function_exists('student_index_next_value')){
function student_index_next_value($con, $yearCode, $classCode){
    $yearCode = preg_replace('/[^0-9]/', '', (string)$yearCode);
    $classCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$classCode));
    if($yearCode === "" || $classCode === ""){
        return "";
    }
    $prefix = "AY".$yearCode."/".$classCode;
    $likeEsc = mysqli_real_escape_string($con, $prefix."/%");
    $maxNumber = 0;
    $res = mysqli_query($con, "SELECT schoolindexnumber AS index_no FROM tblsystemuser WHERE schoolindexnumber LIKE '$likeEsc'
        UNION ALL
        SELECT userid AS index_no FROM tblsystemuser WHERE userid LIKE '$likeEsc'");
    if($res){
        $pattern = '/^'.preg_quote($prefix, '/').'\/([0-9]+)$/';
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $value = trim((string)$row["index_no"]);
            if(preg_match($pattern, $value, $match)){
                $maxNumber = max($maxNumber, (int)$match[1]);
            }
        }
    }

    $next = $maxNumber + 1;
    while($next < 10000){
        $candidate = $prefix."/".str_pad((string)$next, 3, "0", STR_PAD_LEFT);
        $candidateEsc = mysqli_real_escape_string($con, $candidate);
        $check = mysqli_query($con, "SELECT userid FROM tblsystemuser WHERE userid='$candidateEsc' OR schoolindexnumber='$candidateEsc' LIMIT 1");
        if(!$check || mysqli_num_rows($check) === 0){
            return $candidate;
        }
        $next++;
    }
    return "";
}
}

if(!function_exists('student_index_assign_for_class')){
function student_index_assign_for_class($con, $userId, $classEntryId, $batchId, $recordedBy = ""){
    $result = array(
        "success" => false,
        "created" => false,
        "index" => "",
        "message" => ""
    );
    if(!student_index_ensure_schema($con)){
        $result["message"] = "The school index field could not be prepared.";
        return $result;
    }

    $userEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $classEsc = mysqli_real_escape_string($con, trim((string)$classEntryId));
    $batchEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    if($userEsc === "" || $classEsc === "" || $batchEsc === ""){
        $result["message"] = "The student, class or batch is missing.";
        return $result;
    }

    $studentRes = mysqli_query($con, "SELECT userid, schoolindexnumber FROM tblsystemuser WHERE userid='$userEsc' AND systemtype='Student' LIMIT 1");
    if(!$studentRes || !($student = mysqli_fetch_array($studentRes, MYSQLI_ASSOC))){
        $result["message"] = "The selected student could not be found.";
        return $result;
    }
    $existingIndex = trim((string)$student["schoolindexnumber"]);
    if($existingIndex !== ""){
        $result["success"] = true;
        $result["index"] = $existingIndex;
        $result["message"] = "School index already exists.";
        return $result;
    }

    $userIdValue = trim((string)$student["userid"]);
    if(preg_match('/^AY[0-9]{2}\/[A-Z0-9]+\/[0-9]+$/', $userIdValue)){
        $candidate = $userIdValue;
    }else{
        $classRes = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$classEsc' LIMIT 1");
        $batchRes = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$batchEsc' LIMIT 1");
        $classRow = $classRes ? mysqli_fetch_array($classRes, MYSQLI_ASSOC) : null;
        $batchRow = $batchRes ? mysqli_fetch_array($batchRes, MYSQLI_ASSOC) : null;
        if(!$classRow || !$batchRow){
            $result["message"] = "The class or batch could not be found.";
            return $result;
        }
        $classCode = student_index_class_code($classRow["class_name"]);
        $yearCode = student_index_batch_year_code($batchRow["batch"]);
        $candidate = student_index_next_value($con, $yearCode, $classCode);
    }

    if($candidate === ""){
        $result["message"] = "The school index number could not be generated.";
        return $result;
    }

    $candidateEsc = mysqli_real_escape_string($con, $candidate);
    $updated = mysqli_query($con, "UPDATE tblsystemuser
        SET schoolindexnumber='$candidateEsc'
        WHERE userid='$userEsc'
          AND (schoolindexnumber IS NULL OR schoolindexnumber='')
        LIMIT 1");
    if(!$updated){
        $result["message"] = "The school index number could not be saved.";
        return $result;
    }

    $result["success"] = true;
    $result["created"] = mysqli_affected_rows($con) > 0;
    $result["index"] = $candidate;
    $result["message"] = $result["created"] ? "School index generated." : "School index already exists.";
    return $result;
}
}
?>

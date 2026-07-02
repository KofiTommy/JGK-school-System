<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("semester-registry-utils.php");
include_once("class-teacher-utils.php");
include_once("gradingsystem.php");
include_once("company.php");

semester_registry_ensure_academic_year_column($con);
ensure_student_terminal_term_column($con);

$isAcademicLeadViewer = function_exists('um_is_academic_lead_user') && um_is_academic_lead_user();

if (!isset($_SESSION['ACCESSLEVEL']) || ($_SESSION['ACCESSLEVEL'] != "administrator" && !$isAcademicLeadViewer)) {
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}
if (!isset($_SESSION['SYSTEMTYPE']) || (!in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true) && !$isAcademicLeadViewer)) {
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}

function sh_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function sh_format_datetime($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date("d M Y, g:i a", $timestamp);
}

function sh_format_date($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date("d M Y", $timestamp);
}

function sh_year_label($value, $fallbackDate = ""){
    $value = trim((string)$value);
    $normalized = semester_registry_normalize_year($value);
    if($normalized !== ""){
        return $normalized;
    }
    if($fallbackDate !== ""){
        $timestamp = strtotime($fallbackDate);
        if($timestamp !== false){
            return date("Y", $timestamp);
        }
    }
    return ($value !== "") ? $value : "Unknown";
}

function sh_term_label($value){
    $value = trim((string)$value);
    if($value === "" || $value === "0"){
        return "Semester Not Set";
    }
    return "Semester ".$value;
}

function sh_number($value){
    return is_numeric($value) ? (float)$value : 0.0;
}

function sh_format_score($value){
    if($value === null || $value === ""){
        return "-";
    }
    $number = (float)$value;
    if(abs($number - round($number)) < 0.0001){
        return (string)((int)round($number));
    }
    return rtrim(rtrim(number_format($number, 2, ".", ""), "0"), ".");
}

function sh_grade_for_total($value){
    static $grading = null;
    if($grading === null){
        $grading = new GradingSystem();
    }
    $grading->setMark((float)$value);
    $grade = trim((string)$grading->getMark());
    return $grade !== "" ? $grade : "N/A";
}

function sh_session_key($academicYear, $batchId, $termName, $classId){
    return trim((string)$academicYear)."|".trim((string)$batchId)."|".trim((string)$termName)."|".trim((string)$classId);
}

function sh_ensure_session(&$sessions, $academicYear, $batchId, $termName, $classId, $seed = array()){
    $academicYear = trim((string)$academicYear);
    $batchId = trim((string)$batchId);
    $termName = trim((string)$termName);
    $classId = trim((string)$classId);
    $key = sh_session_key($academicYear, $batchId, $termName, $classId);

    if(!isset($sessions[$key])){
        $sessions[$key] = array(
            "session_key" => $key,
            "academic_year" => $academicYear !== "" ? $academicYear : "Unknown",
            "batchid" => $batchId,
            "batch" => "",
            "termname" => $termName,
            "class_entryid" => $classId,
            "class_name" => "",
            "session_status" => "",
            "registered_on" => "",
            "last_updated" => "",
            "subjects" => array(),
            "remarks" => null
        );
    }

    foreach($seed as $seedKey => $seedValue){
        if(!array_key_exists($seedKey, $sessions[$key])){
            $sessions[$key][$seedKey] = $seedValue;
            continue;
        }

        $currentValue = $sessions[$key][$seedKey];
        if(is_array($currentValue)){
            continue;
        }

        if(trim((string)$currentValue) === "" && trim((string)$seedValue) !== ""){
            $sessions[$key][$seedKey] = $seedValue;
        }
    }

    return $key;
}

function sh_compare_sessions($left, $right){
    $leftYear = (int)preg_replace("/[^0-9]/", "", (string)$left["academic_year"]);
    $rightYear = (int)preg_replace("/[^0-9]/", "", (string)$right["academic_year"]);
    if($leftYear !== $rightYear){
        return ($leftYear < $rightYear) ? -1 : 1;
    }

    $leftTerm = (int)trim((string)$left["termname"]);
    $rightTerm = (int)trim((string)$right["termname"]);
    if($leftTerm !== $rightTerm){
        return ($leftTerm < $rightTerm) ? -1 : 1;
    }

    $leftClass = strtolower(trim((string)$left["class_name"]));
    $rightClass = strtolower(trim((string)$right["class_name"]));
    if($leftClass !== $rightClass){
        return strcmp($leftClass, $rightClass);
    }

    $leftBatch = strtolower(trim((string)$left["batch"]));
    $rightBatch = strtolower(trim((string)$right["batch"]));
    if($leftBatch !== $rightBatch){
        return strcmp($leftBatch, $rightBatch);
    }

    return strcmp((string)$left["session_key"], (string)$right["session_key"]);
}

function sh_compare_subjects($left, $right){
    return strcmp(
        strtolower(trim((string)$left["subject"])),
        strtolower(trim((string)$right["subject"]))
    );
}

function sh_find_session_by_scope($sessions, $batchId, $termName, $classId = "", $className = "", $academicYear = ""){
    $batchId = trim((string)$batchId);
    $termName = trim((string)$termName);
    $classId = trim((string)$classId);
    $className = trim((string)$className);
    $academicYear = trim((string)$academicYear);
    $matches = array();

    foreach($sessions as $sessionKey => $session){
        if(trim((string)$session["batchid"]) !== $batchId){
            continue;
        }
        if(trim((string)$session["termname"]) !== $termName){
            continue;
        }
        if($academicYear !== "" && trim((string)$session["academic_year"]) !== $academicYear){
            continue;
        }
        if($classId !== "" && trim((string)$session["class_entryid"]) !== $classId){
            continue;
        }
        if($classId === "" && $className !== ""){
            $sessionClassName = trim((string)$session["class_name"]);
            if($sessionClassName !== "" && strcasecmp($sessionClassName, $className) !== 0){
                continue;
            }
        }
        $matches[] = $sessionKey;
    }

    if(count($matches) === 1){
        return $matches[0];
    }
    if(count($matches) > 1){
        return $matches[count($matches) - 1];
    }

    foreach($sessions as $sessionKey => $session){
        if(trim((string)$session["batchid"]) === $batchId && trim((string)$session["termname"]) === $termName){
            return $sessionKey;
        }
    }

    return "";
}

function sh_find_subject_index($subjects, $subjectId, $subjectName){
    $subjectId = trim((string)$subjectId);
    $subjectName = strtolower(trim((string)$subjectName));

    foreach($subjects as $index => $subjectRow){
        $rowSubjectId = trim((string)(isset($subjectRow["subjectid"]) ? $subjectRow["subjectid"] : ""));
        $rowSubjectName = strtolower(trim((string)(isset($subjectRow["subject"]) ? $subjectRow["subject"] : "")));
        if($subjectId !== "" && $rowSubjectId !== "" && $rowSubjectId === $subjectId){
            return $index;
        }
        if($subjectName !== "" && $rowSubjectName === $subjectName){
            return $index;
        }
    }

    return -1;
}

function sh_promoted_label($remarks){
    if(!is_array($remarks)){
        return "Not recorded";
    }
    $promotedClass = trim((string)(isset($remarks["promoted_class_name"]) ? $remarks["promoted_class_name"] : ""));
    if($promotedClass !== ""){
        return $promotedClass;
    }
    $promotedRaw = trim((string)(isset($remarks["promotedto"]) ? $remarks["promotedto"] : ""));
    return ($promotedRaw !== "") ? $promotedRaw : "Not recorded";
}

function sh_resolve_school_logo($logoValue){
    $logoValue = trim((string)$logoValue);
    $candidates = array();

    if($logoValue !== ""){
        $candidates[] = "images/logo/".$logoValue;
        $candidates[] = "images/".$logoValue;
        $candidates[] = "logo/".$logoValue;
        $candidates[] = $logoValue;
    }

    $candidates[] = "images/logo.png";
    $candidates[] = "images/logo.jpeg";
    $candidates[] = "logo/logo.png";
    $candidates[] = "logo/logo.jpeg";

    foreach($candidates as $candidate){
        $candidate = str_replace("\\", "/", trim((string)$candidate));
        if($candidate === ""){
            continue;
        }
        $fullPath = __DIR__.DIRECTORY_SEPARATOR.str_replace("/", DIRECTORY_SEPARATOR, $candidate);
        if(file_exists($fullPath)){
            return $candidate;
        }
    }

    return "";
}

$_StudentId = trim((string)(isset($_GET["studentid"]) ? $_GET["studentid"] : ""));
if($_StudentId === "" && isset($_GET["userid"])){
    $_StudentId = trim((string)$_GET["userid"]);
}
$_StudentIdSafe = mysqli_real_escape_string($con, $_StudentId);

    $selectedStudent = null;
    $transcriptSessions = array();
    $transcriptYears = array();
    $termRegistryError = "";
    $marksQueryError = "";
    $remarksQueryError = "";
    $studentSummary = array(
        "session_count" => 0,
        "subject_count" => 0,
        "pass_count" => 0,
    "average_score" => null,
    "pass_rate" => null,
    "latest_session" => "Not available"
);

if($_StudentId !== ""){
    $studentRes = mysqli_query($con, "SELECT userid, firstname, surname, othernames FROM tblsystemuser WHERE userid='$_StudentIdSafe' LIMIT 1");
    if($studentRes && ($studentRow = mysqli_fetch_array($studentRes, MYSQLI_ASSOC))){
        $selectedStudent = $studentRow;
    }

    $termYearSql = semester_registry_resolved_year_sql("tr");
    $termRegistrySql = "SELECT
            tr.termid,
            tr.class_entryid,
            tr.termname,
            tr.batchid,
            tr.status,
            tr.datetimeentry AS registered_on,
            $termYearSql AS academic_year,
            ce.class_name,
            b.batch
        FROM tbltermregistry tr
        LEFT JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
        LEFT JOIN tblbatch b ON b.batchid=tr.batchid
        WHERE tr.userid='$_StudentIdSafe'
        ORDER BY academic_year ASC, tr.termname ASC, tr.datetimeentry ASC";
    $termRegistryRes = mysqli_query($con, $termRegistrySql);
    if($termRegistryRes){
        while($row = mysqli_fetch_array($termRegistryRes, MYSQLI_ASSOC)){
            $academicYear = sh_year_label(isset($row["academic_year"]) ? $row["academic_year"] : "", isset($row["registered_on"]) ? $row["registered_on"] : "");
            sh_ensure_session($transcriptSessions, $academicYear, $row["batchid"], $row["termname"], $row["class_entryid"], array(
                "academic_year" => $academicYear,
                "batch" => isset($row["batch"]) ? $row["batch"] : "",
                "class_name" => isset($row["class_name"]) ? $row["class_name"] : "",
                "session_status" => isset($row["status"]) ? $row["status"] : "",
                "registered_on" => isset($row["registered_on"]) ? $row["registered_on"] : ""
            ));
        }
    }else{
        $termRegistryError = "Semester registration history could not be loaded.";
    }

    $historySemesterRes = @mysqli_query($con, "SELECT * FROM vw_student_semester_history WHERE userid='$_StudentIdSafe' ORDER BY batch, semester, semester_registered_on");
    if($historySemesterRes){
        while($row = mysqli_fetch_array($historySemesterRes, MYSQLI_ASSOC)){
            $registeredOn = "";
            if(isset($row["semester_registered_on"]) && trim((string)$row["semester_registered_on"]) !== ""){
                $registeredOn = $row["semester_registered_on"];
            }elseif(isset($row["class_registered_on"]) && trim((string)$row["class_registered_on"]) !== ""){
                $registeredOn = $row["class_registered_on"];
            }

            $sessionKey = sh_find_session_by_scope(
                $transcriptSessions,
                isset($row["batchid"]) ? $row["batchid"] : "",
                isset($row["semester"]) ? $row["semester"] : "",
                "",
                isset($row["class_name"]) ? $row["class_name"] : ""
            );

            if($sessionKey === ""){
                $academicYear = sh_year_label("", $registeredOn);
                sh_ensure_session($transcriptSessions, $academicYear, $row["batchid"], $row["semester"], "", array(
                    "academic_year" => $academicYear,
                    "batch" => isset($row["batch"]) ? $row["batch"] : "",
                    "class_name" => isset($row["class_name"]) ? $row["class_name"] : "",
                    "session_status" => isset($row["semester_status"]) ? $row["semester_status"] : "",
                    "registered_on" => $registeredOn
                ));
            }
        }
        if($termRegistryError !== "" && !empty($transcriptSessions)){
            $termRegistryError = "";
        }
    }

    $marksSql = "SELECT
            sa.batchid,
            b.batch,
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
        LEFT JOIN tblbatch b ON b.batchid=sa.batchid
        WHERE mk.userid='$_StudentIdSafe' AND mk.status='active'
        GROUP BY sa.batchid, b.batch, sa.termname, sa.classid, ce.class_name, sub.subjectid, sub.subject
        ORDER BY assignment_year ASC, sa.termname ASC, sub.subject ASC";
    $marksRes = mysqli_query($con, $marksSql);
    if($marksRes){
        while($row = mysqli_fetch_array($marksRes, MYSQLI_ASSOC)){
            $sessionKey = sh_find_session_by_scope(
                $transcriptSessions,
                isset($row["batchid"]) ? $row["batchid"] : "",
                isset($row["termname"]) ? $row["termname"] : "",
                isset($row["class_entryid"]) ? $row["class_entryid"] : "",
                isset($row["class_name"]) ? $row["class_name"] : ""
            );

            if($sessionKey === ""){
                $academicYear = sh_year_label(isset($row["assignment_year"]) ? $row["assignment_year"] : "", isset($row["last_updated"]) ? $row["last_updated"] : "");
                $sessionKey = sh_ensure_session($transcriptSessions, $academicYear, $row["batchid"], $row["termname"], $row["class_entryid"], array(
                    "academic_year" => $academicYear,
                    "batch" => isset($row["batch"]) ? $row["batch"] : "",
                    "class_name" => isset($row["class_name"]) ? $row["class_name"] : "",
                    "last_updated" => isset($row["last_updated"]) ? $row["last_updated"] : ""
                ));
            }

            $totalScore = sh_number($row["total_score"]);
            $subjectIndex = sh_find_subject_index(
                $transcriptSessions[$sessionKey]["subjects"],
                isset($row["subjectid"]) ? $row["subjectid"] : "",
                isset($row["subject"]) ? $row["subject"] : ""
            );
            $subjectPayload = array(
                "subject" => isset($row["subject"]) ? $row["subject"] : "",
                "subjectid" => isset($row["subjectid"]) ? $row["subjectid"] : "",
                "class_score" => (int)$row["has_class_score"] === 1 ? sh_number($row["class_score"]) : null,
                "exam_score" => (int)$row["has_exam_score"] === 1 ? sh_number($row["exam_score"]) : null,
                "total_score" => $totalScore,
                "class_total_mark" => sh_number($row["class_total_mark"]),
                "exam_total_mark" => sh_number($row["exam_total_mark"]),
                "grade" => sh_grade_for_total($totalScore),
                "passed" => ($totalScore >= 50)
            );
            if($subjectIndex >= 0){
                $transcriptSessions[$sessionKey]["subjects"][$subjectIndex] = $subjectPayload;
            }else{
                $transcriptSessions[$sessionKey]["subjects"][] = $subjectPayload;
            }

            if(trim((string)$transcriptSessions[$sessionKey]["last_updated"]) === "" && trim((string)$row["last_updated"]) !== ""){
                $transcriptSessions[$sessionKey]["last_updated"] = $row["last_updated"];
            }
        }
    }else{
        $marksQueryError = "Assessment summary could not be loaded for this student.";
    }

    $historyResultsSql = "SELECT
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
        WHERE userid='$_StudentIdSafe'
        GROUP BY batchid, batch, semester, class_name, subjectid, subject
        ORDER BY batch, semester, subject";
    $historyResultsRes = @mysqli_query($con, $historyResultsSql);
    if($historyResultsRes){
        while($row = mysqli_fetch_array($historyResultsRes, MYSQLI_ASSOC)){
            $sessionKey = sh_find_session_by_scope(
                $transcriptSessions,
                isset($row["batchid"]) ? $row["batchid"] : "",
                isset($row["semester"]) ? $row["semester"] : "",
                "",
                isset($row["class_name"]) ? $row["class_name"] : ""
            );

            if($sessionKey === ""){
                $academicYear = sh_year_label("", isset($row["last_updated"]) ? $row["last_updated"] : "");
                $sessionKey = sh_ensure_session($transcriptSessions, $academicYear, $row["batchid"], $row["semester"], "", array(
                    "academic_year" => $academicYear,
                    "batch" => isset($row["batch"]) ? $row["batch"] : "",
                    "class_name" => isset($row["class_name"]) ? $row["class_name"] : "",
                    "last_updated" => isset($row["last_updated"]) ? $row["last_updated"] : ""
                ));
            }

            $subjectIndex = sh_find_subject_index(
                $transcriptSessions[$sessionKey]["subjects"],
                isset($row["subjectid"]) ? $row["subjectid"] : "",
                isset($row["subject"]) ? $row["subject"] : ""
            );
            $totalScore = sh_number($row["total_score"]);
            $subjectPayload = array(
                "subject" => isset($row["subject"]) ? $row["subject"] : "",
                "subjectid" => isset($row["subjectid"]) ? $row["subjectid"] : "",
                "class_score" => (int)$row["has_class_score"] === 1 ? sh_number($row["class_score"]) : null,
                "exam_score" => (int)$row["has_exam_score"] === 1 ? sh_number($row["exam_score"]) : null,
                "total_score" => $totalScore,
                "class_total_mark" => sh_number($row["class_total_mark"]),
                "exam_total_mark" => sh_number($row["exam_total_mark"]),
                "grade" => sh_grade_for_total($totalScore),
                "passed" => ($totalScore >= 50)
            );

            if($subjectIndex >= 0){
                $existingSubject = $transcriptSessions[$sessionKey]["subjects"][$subjectIndex];
                if($existingSubject["class_score"] === null && $subjectPayload["class_score"] !== null){
                    $existingSubject["class_score"] = $subjectPayload["class_score"];
                }
                if($existingSubject["exam_score"] === null && $subjectPayload["exam_score"] !== null){
                    $existingSubject["exam_score"] = $subjectPayload["exam_score"];
                }
                if(
                    sh_number($existingSubject["total_score"]) <= 0 &&
                    sh_number($subjectPayload["total_score"]) > 0
                ){
                    $existingSubject["total_score"] = $subjectPayload["total_score"];
                    $existingSubject["grade"] = $subjectPayload["grade"];
                    $existingSubject["passed"] = $subjectPayload["passed"];
                }
                $transcriptSessions[$sessionKey]["subjects"][$subjectIndex] = $existingSubject;
            }else{
                $transcriptSessions[$sessionKey]["subjects"][] = $subjectPayload;
            }

            if(trim((string)$transcriptSessions[$sessionKey]["last_updated"]) === "" && trim((string)$row["last_updated"]) !== ""){
                $transcriptSessions[$sessionKey]["last_updated"] = $row["last_updated"];
            }
        }
        if($marksQueryError !== ""){
            $hasSubjects = false;
            foreach($transcriptSessions as $sessionRow){
                if(!empty($sessionRow["subjects"])){
                    $hasSubjects = true;
                    break;
                }
            }
            if($hasSubjects){
                $marksQueryError = "";
            }
        }
    }

    $terminalYearSql = semester_registry_resolved_year_sql("tr2");
    $remarksSql = "SELECT
            str.terminalid,
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
            b.batch,
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
        LEFT JOIN tblbatch b ON b.batchid=str.batchid
        LEFT JOIN tblclassentry nextce ON nextce.class_entryid=str.promotedto
        WHERE str.userid='$_StudentIdSafe'
        ORDER BY str.datetimeentry DESC";
    $remarksRes = mysqli_query($con, $remarksSql);
    if($remarksRes){
        while($row = mysqli_fetch_array($remarksRes, MYSQLI_ASSOC)){
            $academicYear = sh_year_label(isset($row["academic_year"]) ? $row["academic_year"] : "", isset($row["datetimeentry"]) ? $row["datetimeentry"] : "");
            $sessionKey = sh_find_session_by_scope(
                $transcriptSessions,
                isset($row["batchid"]) ? $row["batchid"] : "",
                isset($row["termname"]) ? $row["termname"] : "",
                isset($row["class_entryid"]) ? $row["class_entryid"] : "",
                isset($row["class_name"]) ? $row["class_name"] : "",
                $academicYear
            );

            if($sessionKey === ""){
                $sessionKey = sh_ensure_session($transcriptSessions, $academicYear, $row["batchid"], $row["termname"], isset($row["class_entryid"]) ? $row["class_entryid"] : "", array(
                    "academic_year" => $academicYear,
                    "batch" => isset($row["batch"]) ? $row["batch"] : "",
                    "class_name" => isset($row["class_name"]) ? $row["class_name"] : "",
                    "last_updated" => isset($row["datetimeentry"]) ? $row["datetimeentry"] : ""
                ));
            }

            if($transcriptSessions[$sessionKey]["remarks"] === null){
                $transcriptSessions[$sessionKey]["remarks"] = $row;
            }else{
                $existingTime = strtotime((string)$transcriptSessions[$sessionKey]["remarks"]["datetimeentry"]);
                $newTime = strtotime((string)$row["datetimeentry"]);
                if($newTime !== false && ($existingTime === false || $newTime > $existingTime)){
                    $transcriptSessions[$sessionKey]["remarks"] = $row;
                }
            }

            if(trim((string)$transcriptSessions[$sessionKey]["class_name"]) === "" && trim((string)$row["class_name"]) !== ""){
                $transcriptSessions[$sessionKey]["class_name"] = $row["class_name"];
            }
            if(trim((string)$transcriptSessions[$sessionKey]["batch"]) === "" && trim((string)$row["batch"]) !== ""){
                $transcriptSessions[$sessionKey]["batch"] = $row["batch"];
            }
            if(trim((string)$transcriptSessions[$sessionKey]["last_updated"]) === "" && trim((string)$row["datetimeentry"]) !== ""){
                $transcriptSessions[$sessionKey]["last_updated"] = $row["datetimeentry"];
            }
        }
    }else{
        $remarksQueryError = "Semester remarks could not be loaded for this student.";
    }

    $transcriptSessions = array_values($transcriptSessions);
    if(!empty($transcriptSessions)){
        usort($transcriptSessions, "sh_compare_sessions");
    }

    $overallTotalScore = 0;
    $overallSubjectCount = 0;
    $overallPassCount = 0;
    foreach($transcriptSessions as $sessionIndex => $sessionRow){
        if(!empty($sessionRow["subjects"])){
            usort($sessionRow["subjects"], "sh_compare_subjects");
        }

        $sessionSubjectCount = count($sessionRow["subjects"]);
        $sessionTotalScore = 0;
        $sessionPassCount = 0;

        foreach($sessionRow["subjects"] as $subjectRow){
            $sessionTotalScore += sh_number($subjectRow["total_score"]);
            if(!empty($subjectRow["passed"])){
                $sessionPassCount++;
            }
        }

        $sessionAverage = ($sessionSubjectCount > 0) ? round($sessionTotalScore / $sessionSubjectCount, 2) : null;
        $transcriptSessions[$sessionIndex]["subject_count"] = $sessionSubjectCount;
        $transcriptSessions[$sessionIndex]["session_average"] = $sessionAverage;
        $transcriptSessions[$sessionIndex]["pass_count"] = $sessionPassCount;

        $yearKey = trim((string)$sessionRow["academic_year"]);
        if(!isset($transcriptYears[$yearKey])){
            $transcriptYears[$yearKey] = array(
                "academic_year" => $yearKey,
                "sessions" => array(),
                "subject_count" => 0
            );
        }
        $transcriptYears[$yearKey]["sessions"][] = $transcriptSessions[$sessionIndex];
        $transcriptYears[$yearKey]["subject_count"] += $sessionSubjectCount;

        $overallTotalScore += $sessionTotalScore;
        $overallSubjectCount += $sessionSubjectCount;
        $overallPassCount += $sessionPassCount;
    }

    if(!empty($transcriptYears)){
        ksort($transcriptYears, SORT_NATURAL);
    }

    $studentSummary["session_count"] = count($transcriptSessions);
    $studentSummary["subject_count"] = $overallSubjectCount;
    $studentSummary["pass_count"] = $overallPassCount;
    $studentSummary["average_score"] = ($overallSubjectCount > 0) ? round($overallTotalScore / $overallSubjectCount, 2) : null;
    $studentSummary["pass_rate"] = ($overallSubjectCount > 0) ? round(($overallPassCount / $overallSubjectCount) * 100, 1) : null;
    if(!empty($transcriptSessions)){
        $latestSession = $transcriptSessions[count($transcriptSessions) - 1];
        $studentSummary["latest_session"] = trim($latestSession["academic_year"]." ".sh_term_label($latestSession["termname"]));
    }
}

$schoolLogoPath = sh_resolve_school_logo(isset($_Logo) ? $_Logo : "");
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-history.css">
</head>
<body class="student-history-page">
<div class="header print-hide">
<?php include("menu.php"); ?>
</div>

<div class="main-platform student-history-shell">
    <section class="student-history-search form-entry print-hide" align="left">
        <div class="student-history-search__head">
            <div>
                <span class="student-history-kicker">Academic Record</span>
                <h2>Student Transcript</h2>
                <p>Search for a student and print a multi-year transcript with semester summaries, subject totals, grades, and official remarks.</p>
            </div>
        </div>

        <form method="get" action="student-history.php" class="student-history-search__form">
            <div class="student-history-field">
                <label for="studentid">Select Student</label>
                <?php
                $_SQL_ST = mysqli_query($con, "SELECT userid, firstname, surname, othernames FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC, surname ASC");
                echo "<select name='studentid' id='studentid'>";
                echo "<option value=''>Select Student</option>";
                if($_SQL_ST){
                    while($row = mysqli_fetch_array($_SQL_ST, MYSQLI_ASSOC)){
                        $_Sel = ($_StudentId === trim((string)$row["userid"])) ? "selected" : "";
                        echo "<option value='".sh_esc($row["userid"])."' $_Sel>".sh_esc(trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]))." (".sh_esc($row["userid"]).")</option>";
                    }
                }
                echo "</select>";
                ?>
            </div>

            <div class="student-history-field">
                <label for="userid">Or Enter Student ID Manually</label>
                <input type="text" name="userid" id="userid" value="<?php echo sh_esc($_StudentId); ?>" placeholder="Optional manual Student ID">
            </div>

            <div class="student-history-search__actions">
                <button class="button-show" type="submit"><i class="fa fa-search"></i> Show Transcript</button>
                <a href="student-history.php" class="button-print student-history-reset"><i class="fa fa-refresh"></i> Reset</a>
                <?php if($_StudentId !== ""){ ?>
                <button type="button" class="button-print" onclick="window.print();"><i class="fa fa-print"></i> Print Transcript</button>
                <?php } ?>
            </div>
        </form>
    </section>

    <?php if($_StudentId !== ""){ ?>
    <section class="student-history-report" align="left">
        <div class="student-history-report__hero student-transcript-hero">
            <div class="student-transcript-hero__identity">
                <?php if($schoolLogoPath !== ""){ ?>
                <div class="student-transcript-crest">
                    <img src="<?php echo sh_esc($schoolLogoPath); ?>" alt="School crest">
                </div>
                <?php } ?>
                <div class="student-transcript-hero__school">
                    <span class="student-history-kicker">Official Transcript</span>
                    <h2><?php echo sh_esc($_CompanyName !== "" ? $_CompanyName : "School Academic Record"); ?></h2>
                    <p>
                        <?php
                        $schoolLine = trim($_Address);
                        if(trim($_Location) !== ""){
                            $schoolLine = trim($schoolLine.($schoolLine !== "" ? ", " : "").$_Location);
                        }
                        if(trim($_Telephone1) !== "" || trim($_Telephone2) !== ""){
                            $schoolLine = trim($schoolLine.($schoolLine !== "" ? " | " : "").trim($_Telephone1." ".$_Telephone2));
                        }
                        echo sh_esc($schoolLine !== "" ? $schoolLine : "Generated from the school academic records.");
                        ?>
                    </p>
                    <div class="student-transcript-hero__rule"></div>
                    <p class="student-transcript-hero__statement">This transcript is an official summary of the student's academic record as captured by the school.</p>
                </div>
            </div>
            <div class="student-transcript-hero__document">
                <span class="student-transcript-doc-label">Document</span>
                <strong>Multi-Year Student Transcript</strong>
                <small>Generated on <?php echo sh_esc(date("d M Y")); ?></small>
            </div>
        </div>

        <section class="student-history-card">
            <div class="student-history-card__head">
                <div>
                    <span class="student-history-kicker">Student Profile</span>
                    <h3><?php echo $selectedStudent ? sh_esc(trim($selectedStudent["firstname"]." ".$selectedStudent["othernames"]." ".$selectedStudent["surname"])) : "Student Record"; ?></h3>
                </div>
                <span class="student-history-chip"><?php echo sh_esc($_StudentId); ?></span>
            </div>

            <div class="student-transcript-profile">
                <article>
                    <span>Student ID</span>
                    <strong><?php echo sh_esc($_StudentId); ?></strong>
                </article>
                <article>
                    <span>Latest Session</span>
                    <strong><?php echo sh_esc($studentSummary["latest_session"]); ?></strong>
                </article>
                <article>
                    <span>Print Date</span>
                    <strong><?php echo sh_esc(date("d M Y")); ?></strong>
                </article>
                <article>
                    <span>Record Source</span>
                    <strong><?php echo $selectedStudent ? "Registered student profile" : "Transcript tables only"; ?></strong>
                </article>
            </div>
        </section>

        <div class="student-history-metrics">
            <article>
                <span>Academic Sessions</span>
                <strong><?php echo number_format((int)$studentSummary["session_count"]); ?></strong>
            </article>
            <article>
                <span>Subjects Recorded</span>
                <strong><?php echo number_format((int)$studentSummary["subject_count"]); ?></strong>
            </article>
            <article>
                <span>Cumulative Average</span>
                <strong><?php echo $studentSummary["average_score"] !== null ? sh_esc(sh_format_score($studentSummary["average_score"])) : "N/A"; ?></strong>
            </article>
            <article>
                <span>Pass Rate</span>
                <strong><?php echo $studentSummary["pass_rate"] !== null ? sh_esc(sh_format_score($studentSummary["pass_rate"]))."%" : "N/A"; ?></strong>
            </article>
        </div>

        <section class="student-history-card student-transcript-scale-card">
            <div class="student-history-card__head">
                <div>
                    <span class="student-history-kicker">Grading Scale</span>
                    <h3>Transcript Legend</h3>
                </div>
            </div>
            <div class="student-transcript-scale">
                <span>A1: 80-100</span>
                <span>B2: 70-79</span>
                <span>B3: 65-69</span>
                <span>C4: 60-64</span>
                <span>C5: 55-59</span>
                <span>C6: 50-54</span>
                <span>D7: 45-49</span>
                <span>E8: 40-44</span>
                <span>F9: 0-39</span>
            </div>
        </section>

        <?php if($termRegistryError !== "" || $marksQueryError !== "" || $remarksQueryError !== ""){ ?>
        <section class="student-history-card">
            <div class="student-history-card__head">
                <div>
                    <span class="student-history-kicker">System Alerts</span>
                    <h3>Transcript Data Notes</h3>
                </div>
            </div>
            <?php if($termRegistryError !== ""){ ?>
            <div class="student-history-alert student-history-alert--error"><?php echo sh_esc($termRegistryError); ?></div>
            <?php } ?>
            <?php if($marksQueryError !== ""){ ?>
            <div class="student-history-alert student-history-alert--error"><?php echo sh_esc($marksQueryError); ?></div>
            <?php } ?>
            <?php if($remarksQueryError !== ""){ ?>
            <div class="student-history-alert student-history-alert--error"><?php echo sh_esc($remarksQueryError); ?></div>
            <?php } ?>
        </section>
        <?php } ?>

        <?php if(empty($transcriptSessions)){ ?>
        <section class="student-history-card">
            <div class="student-history-empty">No transcript data was found for this student.</div>
        </section>
        <?php }else{ ?>
            <?php foreach($transcriptYears as $yearGroup){ ?>
            <section class="student-history-card student-transcript-year">
                <div class="student-history-card__head">
                    <div>
                        <span class="student-history-kicker">Academic Year</span>
                        <h3><?php echo sh_esc($yearGroup["academic_year"]); ?></h3>
                    </div>
                    <span class="student-history-chip"><?php echo number_format(count($yearGroup["sessions"])); ?> session(s)</span>
                </div>

                <div class="student-transcript-session-list">
                    <?php foreach($yearGroup["sessions"] as $session){ ?>
                    <article class="student-transcript-session">
                        <div class="student-transcript-session__head">
                            <div>
                                <h4><?php echo sh_esc(sh_term_label($session["termname"])); ?></h4>
                                <p><?php echo sh_esc(trim(($session["class_name"] !== "" ? $session["class_name"] : "Class not set")." | ".($session["batch"] !== "" ? $session["batch"] : "Batch not set"))); ?></p>
                            </div>
                            <div class="student-transcript-session__chips">
                                <span class="student-history-pill"><?php echo number_format((int)$session["subject_count"]); ?> subject(s)</span>
                                <span class="student-history-chip"><?php echo $session["session_average"] !== null ? sh_esc(sh_format_score($session["session_average"]))." avg" : "No scores"; ?></span>
                            </div>
                        </div>

                        <div class="student-transcript-session__meta">
                            <span><strong>Class:</strong> <?php echo sh_esc($session["class_name"] !== "" ? $session["class_name"] : "Not recorded"); ?></span>
                            <span><strong>Batch:</strong> <?php echo sh_esc($session["batch"] !== "" ? $session["batch"] : "Not recorded"); ?></span>
                            <span><strong>Status:</strong> <?php echo sh_esc($session["session_status"] !== "" ? $session["session_status"] : "Not recorded"); ?></span>
                            <span><strong>Registered:</strong> <?php echo sh_esc($session["registered_on"] !== "" ? sh_format_datetime($session["registered_on"]) : "Not recorded"); ?></span>
                        </div>

                        <?php if(empty($session["subjects"])){ ?>
                        <div class="student-history-empty">No subject scores were captured for this session.</div>
                        <?php }else{ ?>
                        <div class="student-history-table-wrap">
                            <table class="student-history-table student-transcript-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Class Score</th>
                                        <th>Exam Score</th>
                                        <th>Total Score</th>
                                        <th>Grade</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($session["subjects"] as $subjectRow){ ?>
                                    <tr>
                                        <td data-label="Subject"><?php echo sh_esc($subjectRow["subject"]); ?></td>
                                        <td data-label="Class Score"><?php echo sh_esc(sh_format_score($subjectRow["class_score"])); ?></td>
                                        <td data-label="Exam Score"><?php echo sh_esc(sh_format_score($subjectRow["exam_score"])); ?></td>
                                        <td data-label="Total Score"><?php echo sh_esc(sh_format_score($subjectRow["total_score"])); ?></td>
                                        <td data-label="Grade"><span class="student-transcript-grade"><?php echo sh_esc($subjectRow["grade"]); ?></span></td>
                                        <td data-label="Result">
                                            <span class="student-transcript-result <?php echo !empty($subjectRow["passed"]) ? "is-pass" : "is-fail"; ?>">
                                                <?php echo !empty($subjectRow["passed"]) ? "Pass" : "Fail"; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>

                        <?php $sessionRemarks = $session["remarks"]; ?>
                        <div class="student-transcript-remarks">
                            <article class="student-transcript-note-card">
                                <span class="student-history-kicker">Semester Summary</span>
                                <ul class="student-transcript-note-list">
                                    <li><strong>Roll Number:</strong> <?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["roll"]) !== "" ? $sessionRemarks["roll"] : "Not recorded"); ?></li>
                                    <li><strong>Attendance:</strong> <?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["attendance"]) !== "" ? $sessionRemarks["attendance"] : "Not recorded"); ?></li>
                                    <li><strong>Total Attendance:</strong> <?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["totalattendance"]) !== "" ? $sessionRemarks["totalattendance"] : "Not recorded"); ?></li>
                                    <li><strong>Promoted To:</strong> <?php echo sh_esc(sh_promoted_label($sessionRemarks)); ?></li>
                                    <li><strong>Conduct:</strong> <?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["conduct"]) !== "" ? $sessionRemarks["conduct"] : "Not recorded"); ?></li>
                                    <li><strong>Interest:</strong> <?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["interest"]) !== "" ? $sessionRemarks["interest"] : "Not recorded"); ?></li>
                                </ul>
                            </article>

                            <article class="student-transcript-note-card">
                                <span class="student-history-kicker">Remarks</span>
                                <div class="student-transcript-remark-block">
                                    <strong>Class Teacher</strong>
                                    <p><?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["class_teacher_remark"]) !== "" ? $sessionRemarks["class_teacher_remark"] : "No class teacher remark recorded."); ?></p>
                                </div>
                                <div class="student-transcript-remark-block">
                                    <strong>Head Teacher</strong>
                                    <p><?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["head_teacher_remark"]) !== "" ? $sessionRemarks["head_teacher_remark"] : "No head teacher remark recorded."); ?></p>
                                </div>
                                <div class="student-transcript-note-meta">
                                    <span><strong>Remark Date:</strong> <?php echo sh_esc(is_array($sessionRemarks) && trim((string)$sessionRemarks["datetimeentry"]) !== "" ? sh_format_datetime($sessionRemarks["datetimeentry"]) : "Not recorded"); ?></span>
                                </div>
                            </article>
                        </div>
                    </article>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>

            <section class="student-history-card student-transcript-certification">
                <div class="student-history-card__head">
                    <div>
                        <span class="student-history-kicker">Certification</span>
                        <h3>Official Approval</h3>
                    </div>
                </div>
                <p class="student-transcript-certification__text">This transcript is certified as a true and official extract from the academic records maintained by the school.</p>
                <div class="student-transcript-signatures">
                    <article class="student-transcript-signature">
                        <span class="student-transcript-signature__label">Headmaster / Headmistress</span>
                        <div class="student-transcript-signature__line"></div>
                        <strong>Signature</strong>
                    </article>
                    <article class="student-transcript-signature">
                        <span class="student-transcript-signature__label">Date Of Approval</span>
                        <div class="student-transcript-signature__line"></div>
                        <strong>Date</strong>
                    </article>
                    <article class="student-transcript-signature">
                        <span class="student-transcript-signature__label">School Seal</span>
                        <div class="student-transcript-signature__line student-transcript-signature__line--seal"></div>
                        <strong>Stamp / Seal</strong>
                    </article>
                </div>
            </section>
        <?php } ?>
    </section>
    <?php } ?>
</div>
</body>
</html>

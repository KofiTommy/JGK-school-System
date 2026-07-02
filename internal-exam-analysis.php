<?php
session_start();
include("check-login.php");
include("dbstring.php");
include_once("audit_notifications.php");

$isTeacher = (
    isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    $_SESSION['ACCESSLEVEL'] === "user" &&
    $_SESSION['SYSTEMTYPE'] === "Teacher"
);

$isAllowed = (
    isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    (
        ($_SESSION['ACCESSLEVEL'] === "administrator" && in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true)) ||
        (function_exists('um_is_academic_lead_user') && um_is_academic_lead_user())
    )
);

if(!$isAllowed){
    if(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] === "user" && isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === "Teacher"){
        header("location:teacher-page.php");
    } elseif(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] === "user" && isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === "Student"){
        header("location:student-page.php");
    } else {
        header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    }
    exit();
}

function examdash_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function examdash_grade_list(){
    return array("A1","B2","B3","C4","C5","C6","D7","E8","F9");
}

function examdash_normalize_gender($value){
    $gender = strtoupper(trim((string)$value));
    if(in_array($gender, array("M", "MALE", "BOY", "B"), true)){
        return "Male";
    }
    if(in_array($gender, array("F", "FEMALE", "GIRL", "G"), true)){
        return "Female";
    }
    return "Not Set";
}

function examdash_grade_from_percent($percent){
    $score = round((float)$percent);
    if($score > 100){
        $score = 100;
    }
    if($score < 0){
        $score = 0;
    }
    if($score >= 80){
        return "A1";
    }
    if($score >= 70){
        return "B2";
    }
    if($score >= 65){
        return "B3";
    }
    if($score >= 60){
        return "C4";
    }
    if($score >= 55){
        return "C5";
    }
    if($score >= 50){
        return "C6";
    }
    if($score >= 45){
        return "D7";
    }
    if($score >= 40){
        return "E8";
    }
    return "F9";
}

function examdash_grade_point($grade){
    $map = array("A1"=>1,"B2"=>2,"B3"=>3,"C4"=>4,"C5"=>5,"C6"=>6,"D7"=>7,"E8"=>8,"F9"=>9);
    return isset($map[$grade]) ? $map[$grade] : 0;
}

function examdash_is_pass($grade){
    return in_array($grade, array("A1","B2","B3","C4","C5","C6"), true);
}

function examdash_is_strong($grade){
    return in_array($grade, array("A1","B2","B3"), true);
}

function examdash_position_text($position){
    if($position % 100 >= 11 && $position % 100 <= 13){
        return $position."th";
    }
    switch($position % 10){
        case 1: return $position."st";
        case 2: return $position."nd";
        case 3: return $position."rd";
        default: return $position."th";
    }
}

function examdash_unique_count($rows, $key){
    $seen = array();
    foreach($rows as $row){
        if(isset($row[$key])){
            $seen[$row[$key]] = true;
        }
    }
    return count($seen);
}

function examdash_filter_rows($rows, $subjectId = "", $gender = "", $grade = ""){
    $filtered = array();
    foreach($rows as $row){
        if($subjectId !== "" && $row["subjectid"] !== $subjectId){
            continue;
        }
        if($gender !== "" && $row["gender"] !== $gender){
            continue;
        }
        if($grade !== "" && $row["grade"] !== $grade){
            continue;
        }
        $filtered[] = $row;
    }
    return $filtered;
}

function examdash_normalize_year($value){
    $value = trim((string)$value);
    return preg_match('/^\d{4}$/', $value) ? $value : "";
}

function examdash_column_exists($con, $table, $column){
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

function examdash_assignment_year_sql($alias = "sa"){
    $alias = trim((string)$alias);
    if($alias === ""){
        $alias = "sa";
    }
    return "DATE_FORMAT(".$alias.".datetimeentry, '%Y')";
}

function examdash_termregistry_year_sql($con, $alias = "tr"){
    $alias = trim((string)$alias);
    if($alias === ""){
        $alias = "tr";
    }
    if(examdash_column_exists($con, 'tbltermregistry', 'academicyear')){
        return "COALESCE(NULLIF(TRIM(CONVERT(".$alias.".academicyear USING utf8mb4)),''), DATE_FORMAT(".$alias.".datetimeentry, '%Y'))";
    }
    return "DATE_FORMAT(".$alias.".datetimeentry, '%Y')";
}

function examdash_build_summary($rows){
    $summary = array(
        "total_results" => 0,
        "total_students" => 0,
        "total_subjects" => 0,
        "pass_count" => 0,
        "fail_count" => 0,
        "strong_count" => 0,
        "avg_percent" => 0,
        "avg_point" => 0,
        "pass_pct" => 0,
        "strong_pct" => 0,
        "avg_components" => 0
    );

    if(count($rows) === 0){
        return $summary;
    }

    $studentIds = array();
    $subjectIds = array();
    $totalMarks = 0;
    $totalPossible = 0;
    $totalPoints = 0;
    $totalComponents = 0;

    foreach($rows as $row){
        $studentIds[$row["userid"]] = true;
        $subjectIds[$row["subjectid"]] = true;
        $totalMarks += (float)$row["total_mark"];
        $totalPossible += (float)$row["total_possible"];
        $totalPoints += examdash_grade_point($row["grade"]);
        $totalComponents += (int)$row["component_count"];
        if(examdash_is_pass($row["grade"])){
            $summary["pass_count"]++;
        } else {
            $summary["fail_count"]++;
        }
        if(examdash_is_strong($row["grade"])){
            $summary["strong_count"]++;
        }
    }

    $summary["total_results"] = count($rows);
    $summary["total_students"] = count($studentIds);
    $summary["total_subjects"] = count($subjectIds);
    $summary["avg_percent"] = $totalPossible > 0 ? round(($totalMarks * 100) / $totalPossible, 2) : 0;
    $summary["avg_point"] = $summary["total_results"] > 0 ? round($totalPoints / $summary["total_results"], 2) : 0;
    $summary["pass_pct"] = $summary["total_results"] > 0 ? round(($summary["pass_count"] * 100) / $summary["total_results"], 2) : 0;
    $summary["strong_pct"] = $summary["total_results"] > 0 ? round(($summary["strong_count"] * 100) / $summary["total_results"], 2) : 0;
    $summary["avg_components"] = $summary["total_results"] > 0 ? round($totalComponents / $summary["total_results"], 2) : 0;
    return $summary;
}

function examdash_build_student_summaries($rows){
    $map = array();

    foreach($rows as $row){
        $studentId = $row["userid"];
        if(!isset($map[$studentId])){
            $map[$studentId] = array(
                "userid" => $studentId,
                "student_name" => $row["student_name"],
                "gender" => $row["gender"],
                "results" => array(),
                "total_marks" => 0,
                "total_possible" => 0,
                "pass_count" => 0,
                "strong_count" => 0,
                "grade_points" => 0,
                "subjects_count" => 0,
                "avg_percent" => 0,
                "avg_point" => 0,
                "pass_pct" => 0,
                "strong_pct" => 0,
                "position" => ""
            );
        }

        $map[$studentId]["results"][] = $row;
        $map[$studentId]["total_marks"] += (float)$row["total_mark"];
        $map[$studentId]["total_possible"] += (float)$row["total_possible"];
        $map[$studentId]["grade_points"] += examdash_grade_point($row["grade"]);
        if(examdash_is_pass($row["grade"])){
            $map[$studentId]["pass_count"]++;
        }
        if(examdash_is_strong($row["grade"])){
            $map[$studentId]["strong_count"]++;
        }
    }

    $students = array_values($map);
    foreach($students as $idx => $student){
        usort($student["results"], function($a, $b){
            return strcmp($a["subject"], $b["subject"]);
        });
        $student["subjects_count"] = count($student["results"]);
        $student["avg_percent"] = $student["total_possible"] > 0 ? round(($student["total_marks"] * 100) / $student["total_possible"], 2) : 0;
        $student["avg_point"] = $student["subjects_count"] > 0 ? round($student["grade_points"] / $student["subjects_count"], 2) : 0;
        $student["pass_pct"] = $student["subjects_count"] > 0 ? round(($student["pass_count"] * 100) / $student["subjects_count"], 2) : 0;
        $student["strong_pct"] = $student["subjects_count"] > 0 ? round(($student["strong_count"] * 100) / $student["subjects_count"], 2) : 0;
        $student["search_blob"] = strtolower($student["student_name"]." ".$student["userid"]);
        $students[$idx] = $student;
    }

    usort($students, function($a, $b){
        if((float)$a["avg_percent"] === (float)$b["avg_percent"]){
            if((float)$a["total_marks"] === (float)$b["total_marks"]){
                return strcmp($a["student_name"], $b["student_name"]);
            }
            return ((float)$a["total_marks"] < (float)$b["total_marks"]) ? 1 : -1;
        }
        return ((float)$a["avg_percent"] < (float)$b["avg_percent"]) ? 1 : -1;
    });

    $positions = array();
    foreach($students as $idx => $student){
        $scoreKey = number_format((float)$student["avg_percent"], 2, ".", "");
        if(!isset($positions[$scoreKey])){
            $positions[$scoreKey] = $idx + 1;
        }
        $students[$idx]["position"] = examdash_position_text($positions[$scoreKey]);
    }

    return $students;
}

function examdash_build_results_listing($registeredStudents, $rows){
    $listingMap = array();

    foreach($registeredStudents as $student){
        $listingMap[$student["userid"]] = array(
            "userid" => $student["userid"],
            "student_name" => strtoupper($student["student_name"]),
            "gender" => $student["gender"],
            "results" => array(),
            "results_count" => 0,
            "results_text" => "No scores entered yet."
        );
    }

    foreach($rows as $row){
        $studentId = $row["userid"];
        if(!isset($listingMap[$studentId])){
            $listingMap[$studentId] = array(
                "userid" => $studentId,
                "student_name" => strtoupper($row["student_name"]),
                "gender" => $row["gender"],
                "results" => array(),
                "results_count" => 0,
                "results_text" => "No scores entered yet."
            );
        }
        $listingMap[$studentId]["results"][] = array(
            "subject" => $row["subject"],
            "grade" => $row["grade"]
        );
    }

    $listingRows = array_values($listingMap);
    foreach($listingRows as $idx => $student){
        usort($student["results"], function($a, $b){
            return strcmp($a["subject"], $b["subject"]);
        });
        $resultParts = array();
        foreach($student["results"] as $resultRow){
            $resultParts[] = $resultRow["subject"]." - ".$resultRow["grade"];
        }
        $student["results_count"] = count($resultParts);
        if(count($resultParts) > 0){
            $student["results_text"] = implode(", ", $resultParts);
        }
        $listingRows[$idx] = $student;
    }

    usort($listingRows, function($a, $b){
        $nameCompare = strcmp($a["student_name"], $b["student_name"]);
        if($nameCompare !== 0){
            return $nameCompare;
        }
        return strcmp($a["userid"], $b["userid"]);
    });

    return $listingRows;
}

function examdash_build_board_publish_rows($registeredStudents, $rows){
    $publishedRows = array();
    $publishedMap = array();
    $rankedStudents = examdash_build_student_summaries($rows);

    foreach($rankedStudents as $student){
        $resultParts = array();
        foreach($student["results"] as $resultRow){
            $resultParts[] = $resultRow["subject"]." - ".$resultRow["grade"];
        }
        $publishedRows[] = array(
            "userid" => $student["userid"],
            "student_name" => strtoupper($student["student_name"]),
            "position" => $student["position"],
            "avg_percent" => $student["avg_percent"],
            "overall_grade" => examdash_grade_from_percent($student["avg_percent"]),
            "subjects_count" => $student["subjects_count"],
            "pass_count" => $student["pass_count"],
            "results_text" => count($resultParts) > 0 ? implode(", ", $resultParts) : "No scores entered yet."
        );
        $publishedMap[$student["userid"]] = true;
    }

    $pendingRows = array();
    foreach($registeredStudents as $student){
        if(isset($publishedMap[$student["userid"]])){
            continue;
        }
        $pendingRows[] = array(
            "userid" => $student["userid"],
            "student_name" => strtoupper($student["student_name"]),
            "position" => "-",
            "avg_percent" => "",
            "overall_grade" => "Pending",
            "subjects_count" => 0,
            "pass_count" => 0,
            "results_text" => "No scores entered yet."
        );
    }

    usort($pendingRows, function($a, $b){
        $nameCompare = strcmp($a["student_name"], $b["student_name"]);
        if($nameCompare !== 0){
            return $nameCompare;
        }
        return strcmp($a["userid"], $b["userid"]);
    });

    return array_merge($publishedRows, $pendingRows);
}

function examdash_completeness_issue_label($classCount, $examCount){
    $issues = array();
    $classCount = (int)$classCount;
    $examCount = (int)$examCount;

    if($classCount === 0 && $examCount === 0){
        $issues[] = "Missing Class + Exam";
    } else {
        if($classCount === 0){
            $issues[] = "Missing Class Score";
        }
        if($examCount === 0){
            $issues[] = "Missing Exam Score";
        }
    }

    if($classCount > 1){
        $issues[] = "Duplicate Class Scores";
    }
    if($examCount > 1){
        $issues[] = "Duplicate Exam Scores";
    }

    return count($issues) > 0 ? implode("; ", $issues) : "Complete";
}

function examdash_build_completeness_analysis($rows){
    $summary = array(
        "expected_rows" => 0,
        "complete_rows" => 0,
        "missing_class_rows" => 0,
        "missing_exam_rows" => 0,
        "missing_both_rows" => 0,
        "duplicate_rows" => 0,
        "issue_rows" => 0,
        "completion_pct" => 0
    );
    $subjectMap = array();
    $issueRows = array();

    foreach($rows as $row){
        $summary["expected_rows"]++;

        $subjectId = $row["subjectid"];
        if(!isset($subjectMap[$subjectId])){
            $subjectMap[$subjectId] = array(
                "subjectid" => $subjectId,
                "subject" => $row["subject"],
                "expected_rows" => 0,
                "complete_rows" => 0,
                "missing_class_rows" => 0,
                "missing_exam_rows" => 0,
                "missing_both_rows" => 0,
                "duplicate_rows" => 0,
                "completion_pct" => 0
            );
        }

        $classCount = (int)$row["class_score_rows"];
        $examCount = (int)$row["exam_score_rows"];
        $missingBoth = ($classCount === 0 && $examCount === 0);
        $missingClass = ($classCount === 0 && $examCount > 0);
        $missingExam = ($classCount > 0 && $examCount === 0);
        $hasDuplicate = ($classCount > 1 || $examCount > 1);
        $isComplete = ($classCount === 1 && $examCount === 1);

        $subjectMap[$subjectId]["expected_rows"]++;

        if($isComplete){
            $summary["complete_rows"]++;
            $subjectMap[$subjectId]["complete_rows"]++;
        } else {
            $summary["issue_rows"]++;
        }

        if($missingBoth){
            $summary["missing_both_rows"]++;
            $subjectMap[$subjectId]["missing_both_rows"]++;
        }
        if($missingClass){
            $summary["missing_class_rows"]++;
            $subjectMap[$subjectId]["missing_class_rows"]++;
        }
        if($missingExam){
            $summary["missing_exam_rows"]++;
            $subjectMap[$subjectId]["missing_exam_rows"]++;
        }
        if($hasDuplicate){
            $summary["duplicate_rows"]++;
            $subjectMap[$subjectId]["duplicate_rows"]++;
        }

        if(!$isComplete || $hasDuplicate){
            $issueRows[] = array(
                "userid" => $row["userid"],
                "student_name" => $row["student_name"],
                "gender" => $row["gender"],
                "subjectid" => $row["subjectid"],
                "subject" => $row["subject"],
                "class_score_rows" => $classCount,
                "exam_score_rows" => $examCount,
                "issue_label" => examdash_completeness_issue_label($classCount, $examCount)
            );
        }
    }

    $subjectRows = array_values($subjectMap);
    foreach($subjectRows as $idx => $subjectRow){
        $subjectRows[$idx]["completion_pct"] = $subjectRow["expected_rows"] > 0
            ? round(($subjectRow["complete_rows"] * 100) / $subjectRow["expected_rows"], 2)
            : 0;
    }
    usort($subjectRows, function($a, $b){
        if((float)$a["completion_pct"] === (float)$b["completion_pct"]){
            return strcmp($a["subject"], $b["subject"]);
        }
        return ((float)$a["completion_pct"] < (float)$b["completion_pct"]) ? -1 : 1;
    });

    usort($issueRows, function($a, $b){
        $subjectCompare = strcmp($a["subject"], $b["subject"]);
        if($subjectCompare !== 0){
            return $subjectCompare;
        }
        return strcmp($a["student_name"], $b["student_name"]);
    });

    $summary["completion_pct"] = $summary["expected_rows"] > 0
        ? round(($summary["complete_rows"] * 100) / $summary["expected_rows"], 2)
        : 0;

    return array(
        "summary" => $summary,
        "subject_rows" => $subjectRows,
        "issue_rows" => $issueRows
    );
}

function examdash_delta_text($value, $suffix = "%"){
    if($value === "" || $value === null){
        return "N/A";
    }
    $number = round((float)$value, 2);
    if(abs($number) < 0.01){
        $number = 0;
    }
    $prefix = $number > 0 ? "+" : "";
    return $prefix.$number.$suffix;
}

function examdash_build_term_trend_rows($rows, $registeredCounts){
    $termMap = array();

    foreach($registeredCounts as $termName => $registeredCount){
        $termMap[(string)$termName] = array(
            "termname" => (string)$termName,
            "registered_students" => (int)$registeredCount,
            "results_count" => 0,
            "students" => array(),
            "total_marks" => 0,
            "total_possible" => 0,
            "pass_count" => 0,
            "strong_count" => 0,
            "students_scored" => 0,
            "avg_percent" => "",
            "pass_pct" => "",
            "strong_pct" => "",
            "coverage_pct" => "",
            "avg_change" => "",
            "pass_change" => ""
        );
    }

    foreach($rows as $row){
        $termName = (string)$row["termname"];
        if(!isset($termMap[$termName])){
            $termMap[$termName] = array(
                "termname" => $termName,
                "registered_students" => 0,
                "results_count" => 0,
                "students" => array(),
                "total_marks" => 0,
                "total_possible" => 0,
                "pass_count" => 0,
                "strong_count" => 0,
                "students_scored" => 0,
                "avg_percent" => "",
                "pass_pct" => "",
                "strong_pct" => "",
                "coverage_pct" => "",
                "avg_change" => "",
                "pass_change" => ""
            );
        }

        $termMap[$termName]["results_count"]++;
        $termMap[$termName]["students"][$row["userid"]] = true;
        $termMap[$termName]["total_marks"] += (float)$row["total_mark"];
        $termMap[$termName]["total_possible"] += (float)$row["total_possible"];
        if(examdash_is_pass($row["grade"])){
            $termMap[$termName]["pass_count"]++;
        }
        if(examdash_is_strong($row["grade"])){
            $termMap[$termName]["strong_count"]++;
        }
    }

    $termRows = array_values($termMap);
    usort($termRows, function($a, $b){
        if((int)$a["termname"] === (int)$b["termname"]){
            return 0;
        }
        return ((int)$a["termname"] < (int)$b["termname"]) ? -1 : 1;
    });

    $previousWithScores = null;
    foreach($termRows as $idx => $termRow){
        $termRow["students_scored"] = count($termRow["students"]);
        unset($termRow["students"]);

        if($termRow["results_count"] > 0 && (float)$termRow["total_possible"] > 0){
            $termRow["avg_percent"] = round(($termRow["total_marks"] * 100) / $termRow["total_possible"], 2);
            $termRow["pass_pct"] = round(($termRow["pass_count"] * 100) / $termRow["results_count"], 2);
            $termRow["strong_pct"] = round(($termRow["strong_count"] * 100) / $termRow["results_count"], 2);
        }

        if((int)$termRow["registered_students"] > 0){
            $termRow["coverage_pct"] = round(($termRow["students_scored"] * 100) / $termRow["registered_students"], 2);
        }

        if($previousWithScores !== null && $termRow["avg_percent"] !== "" && $previousWithScores["avg_percent"] !== ""){
            $termRow["avg_change"] = round((float)$termRow["avg_percent"] - (float)$previousWithScores["avg_percent"], 2);
            $termRow["pass_change"] = round((float)$termRow["pass_pct"] - (float)$previousWithScores["pass_pct"], 2);
        }

        if($termRow["results_count"] > 0){
            $previousWithScores = $termRow;
        }
        $termRows[$idx] = $termRow;
    }

    return $termRows;
}

function examdash_find_term_trend_row($termRows, $termName){
    foreach($termRows as $termRow){
        if((string)$termRow["termname"] === (string)$termName){
            return $termRow;
        }
    }
    return null;
}

function examdash_find_previous_term_name($termRows, $selectedTerm){
    $previousTerm = "";
    foreach($termRows as $termRow){
        if((int)$termRow["termname"] < (int)$selectedTerm && (int)$termRow["results_count"] > 0){
            $previousTerm = (string)$termRow["termname"];
        }
    }
    return $previousTerm;
}

function examdash_build_student_term_history($rows){
    $studentMap = array();

    foreach($rows as $row){
        $studentId = $row["userid"];
        $termName = (string)$row["termname"];

        if(!isset($studentMap[$studentId])){
            $studentMap[$studentId] = array(
                "userid" => $studentId,
                "student_name" => $row["student_name"],
                "gender" => $row["gender"],
                "term_map" => array()
            );
        }

        if(!isset($studentMap[$studentId]["term_map"][$termName])){
            $studentMap[$studentId]["term_map"][$termName] = array(
                "termname" => $termName,
                "total_marks" => 0,
                "total_possible" => 0,
                "pass_count" => 0,
                "strong_count" => 0,
                "subjects_count" => 0,
                "avg_percent" => "",
                "pass_pct" => "",
                "strong_pct" => "",
                "overall_grade" => "Pending"
            );
        }

        $studentMap[$studentId]["term_map"][$termName]["total_marks"] += (float)$row["total_mark"];
        $studentMap[$studentId]["term_map"][$termName]["total_possible"] += (float)$row["total_possible"];
        $studentMap[$studentId]["term_map"][$termName]["subjects_count"]++;
        if(examdash_is_pass($row["grade"])){
            $studentMap[$studentId]["term_map"][$termName]["pass_count"]++;
        }
        if(examdash_is_strong($row["grade"])){
            $studentMap[$studentId]["term_map"][$termName]["strong_count"]++;
        }
    }

    foreach($studentMap as $studentId => $studentRow){
        $termRows = array_values($studentRow["term_map"]);
        usort($termRows, function($a, $b){
            if((int)$a["termname"] === (int)$b["termname"]){
                return 0;
            }
            return ((int)$a["termname"] < (int)$b["termname"]) ? -1 : 1;
        });

        $studentRow["term_lookup"] = array();
        foreach($termRows as $idx => $termRow){
            if((float)$termRow["total_possible"] > 0){
                $termRow["avg_percent"] = round(($termRow["total_marks"] * 100) / $termRow["total_possible"], 2);
                $termRow["pass_pct"] = $termRow["subjects_count"] > 0 ? round(($termRow["pass_count"] * 100) / $termRow["subjects_count"], 2) : "";
                $termRow["strong_pct"] = $termRow["subjects_count"] > 0 ? round(($termRow["strong_count"] * 100) / $termRow["subjects_count"], 2) : "";
                $termRow["overall_grade"] = examdash_grade_from_percent($termRow["avg_percent"]);
            }
            $studentRow["term_lookup"][(string)$termRow["termname"]] = $termRow;
            $termRows[$idx] = $termRow;
        }

        $studentRow["term_rows"] = $termRows;
        unset($studentRow["term_map"]);
        $studentMap[$studentId] = $studentRow;
    }

    return $studentMap;
}

function examdash_build_term_movement_rows($studentHistoryMap, $selectedTerm, $previousTerm){
    $movementRows = array();

    foreach($studentHistoryMap as $studentRow){
        if(!isset($studentRow["term_lookup"][(string)$selectedTerm])){
            continue;
        }

        $currentTermRow = $studentRow["term_lookup"][(string)$selectedTerm];
        $previousTermRow = ($previousTerm !== "" && isset($studentRow["term_lookup"][(string)$previousTerm]))
            ? $studentRow["term_lookup"][(string)$previousTerm]
            : null;

        $avgChange = "";
        $passChange = "";
        $movement = "New";
        if($previousTermRow !== null && $previousTermRow["avg_percent"] !== "" && $currentTermRow["avg_percent"] !== ""){
            $avgChange = round((float)$currentTermRow["avg_percent"] - (float)$previousTermRow["avg_percent"], 2);
            $passChange = round((float)$currentTermRow["pass_pct"] - (float)$previousTermRow["pass_pct"], 2);
            if($avgChange > 0.01){
                $movement = "Improved";
            } elseif($avgChange < -0.01){
                $movement = "Declined";
            } else {
                $movement = "Flat";
            }
        }

        $movementRows[] = array(
            "userid" => $studentRow["userid"],
            "student_name" => $studentRow["student_name"],
            "gender" => $studentRow["gender"],
            "previous_avg_percent" => $previousTermRow !== null ? $previousTermRow["avg_percent"] : "",
            "current_avg_percent" => $currentTermRow["avg_percent"],
            "avg_change" => $avgChange,
            "previous_pass_pct" => $previousTermRow !== null ? $previousTermRow["pass_pct"] : "",
            "current_pass_pct" => $currentTermRow["pass_pct"],
            "pass_change" => $passChange,
            "current_grade" => $currentTermRow["overall_grade"],
            "movement" => $movement
        );
    }

    usort($movementRows, function($a, $b){
        $aHasDelta = ($a["avg_change"] !== "");
        $bHasDelta = ($b["avg_change"] !== "");
        if($aHasDelta && $bHasDelta){
            if((float)$a["avg_change"] === (float)$b["avg_change"]){
                return strcmp($a["student_name"], $b["student_name"]);
            }
            return ((float)$a["avg_change"] < (float)$b["avg_change"]) ? 1 : -1;
        }
        if($aHasDelta !== $bHasDelta){
            return $aHasDelta ? -1 : 1;
        }
        return strcmp($a["student_name"], $b["student_name"]);
    });

    return $movementRows;
}

function examdash_build_subject_term_tracking($rows, $registeredCounts){
    $subjectMap = array();

    foreach($rows as $row){
        $subjectId = $row["subjectid"];
        $termName = (string)$row["termname"];

        if(!isset($subjectMap[$subjectId])){
            $subjectMap[$subjectId] = array(
                "subjectid" => $subjectId,
                "subject" => $row["subject"],
                "term_map" => array()
            );
        }

        if(!isset($subjectMap[$subjectId]["term_map"][$termName])){
            $subjectMap[$subjectId]["term_map"][$termName] = array(
                "termname" => $termName,
                "registered_students" => isset($registeredCounts[$termName]) ? (int)$registeredCounts[$termName] : 0,
                "results_count" => 0,
                "students" => array(),
                "total_marks" => 0,
                "total_possible" => 0,
                "pass_count" => 0,
                "strong_count" => 0,
                "students_scored" => 0,
                "avg_percent" => "",
                "pass_pct" => "",
                "strong_pct" => "",
                "coverage_pct" => "",
                "missing_students" => 0
            );
        }

        $subjectMap[$subjectId]["term_map"][$termName]["results_count"]++;
        $subjectMap[$subjectId]["term_map"][$termName]["students"][$row["userid"]] = true;
        $subjectMap[$subjectId]["term_map"][$termName]["total_marks"] += (float)$row["total_mark"];
        $subjectMap[$subjectId]["term_map"][$termName]["total_possible"] += (float)$row["total_possible"];
        if(examdash_is_pass($row["grade"])){
            $subjectMap[$subjectId]["term_map"][$termName]["pass_count"]++;
        }
        if(examdash_is_strong($row["grade"])){
            $subjectMap[$subjectId]["term_map"][$termName]["strong_count"]++;
        }
    }

    foreach($subjectMap as $subjectId => $subjectRow){
        $termRows = array_values($subjectRow["term_map"]);
        usort($termRows, function($a, $b){
            if((int)$a["termname"] === (int)$b["termname"]){
                return 0;
            }
            return ((int)$a["termname"] < (int)$b["termname"]) ? -1 : 1;
        });
        $subjectRow["term_lookup"] = array();
        foreach($termRows as $idx => $termRow){
            $termRow["students_scored"] = count($termRow["students"]);
            unset($termRow["students"]);
            if($termRow["results_count"] > 0 && (float)$termRow["total_possible"] > 0){
                $termRow["avg_percent"] = round(($termRow["total_marks"] * 100) / $termRow["total_possible"], 2);
                $termRow["pass_pct"] = round(($termRow["pass_count"] * 100) / $termRow["results_count"], 2);
                $termRow["strong_pct"] = round(($termRow["strong_count"] * 100) / $termRow["results_count"], 2);
            }
            if((int)$termRow["registered_students"] > 0){
                $termRow["coverage_pct"] = round(($termRow["students_scored"] * 100) / $termRow["registered_students"], 2);
                $termRow["missing_students"] = max(0, (int)$termRow["registered_students"] - (int)$termRow["students_scored"]);
            }
            $subjectRow["term_lookup"][(string)$termRow["termname"]] = $termRow;
            $termRows[$idx] = $termRow;
        }
        $subjectRow["term_rows"] = $termRows;
        unset($subjectRow["term_map"]);
        $subjectMap[$subjectId] = $subjectRow;
    }

    return $subjectMap;
}

function examdash_build_subject_term_watch_rows($subjectTrendMap, $selectedTerm, $previousTerm){
    $watchRows = array();

    foreach($subjectTrendMap as $subjectRow){
        if(!isset($subjectRow["term_lookup"][(string)$selectedTerm])){
            continue;
        }

        $currentTermRow = $subjectRow["term_lookup"][(string)$selectedTerm];
        $previousTermRow = ($previousTerm !== "" && isset($subjectRow["term_lookup"][(string)$previousTerm]))
            ? $subjectRow["term_lookup"][(string)$previousTerm]
            : null;

        $watchRows[] = array(
            "subjectid" => $subjectRow["subjectid"],
            "subject" => $subjectRow["subject"],
            "current_avg_percent" => $currentTermRow["avg_percent"],
            "previous_avg_percent" => $previousTermRow !== null ? $previousTermRow["avg_percent"] : "",
            "avg_change" => ($previousTermRow !== null && $previousTermRow["avg_percent"] !== "" && $currentTermRow["avg_percent"] !== "")
                ? round((float)$currentTermRow["avg_percent"] - (float)$previousTermRow["avg_percent"], 2)
                : "",
            "current_pass_pct" => $currentTermRow["pass_pct"],
            "previous_pass_pct" => $previousTermRow !== null ? $previousTermRow["pass_pct"] : "",
            "pass_change" => ($previousTermRow !== null && $previousTermRow["pass_pct"] !== "" && $currentTermRow["pass_pct"] !== "")
                ? round((float)$currentTermRow["pass_pct"] - (float)$previousTermRow["pass_pct"], 2)
                : "",
            "current_coverage_pct" => $currentTermRow["coverage_pct"],
            "current_missing_students" => $currentTermRow["missing_students"],
            "registered_students" => $currentTermRow["registered_students"]
        );
    }

    usort($watchRows, function($a, $b){
        $aSeverity = ($a["pass_change"] !== "") ? (float)$a["pass_change"] : 9999;
        $bSeverity = ($b["pass_change"] !== "") ? (float)$b["pass_change"] : 9999;
        if($aSeverity === $bSeverity){
            $aAvg = ($a["avg_change"] !== "") ? (float)$a["avg_change"] : 9999;
            $bAvg = ($b["avg_change"] !== "") ? (float)$b["avg_change"] : 9999;
            if($aAvg === $bAvg){
                return strcmp($a["subject"], $b["subject"]);
            }
            return ($aAvg < $bAvg) ? -1 : 1;
        }
        return ($aSeverity < $bSeverity) ? -1 : 1;
    });

    return $watchRows;
}

function examdash_alert_weight($severity){
    switch($severity){
        case "Critical":
            return 1;
        case "Warning":
            return 2;
        case "Info":
            return 3;
        case "Success":
            return 4;
        default:
            return 5;
    }
}

function examdash_add_alert(&$alerts, &$counts, $severity, $area, $title, $detail, $action){
    $alerts[] = array(
        "severity" => $severity,
        "area" => $area,
        "title" => $title,
        "detail" => $detail,
        "action" => $action
    );
    if(!isset($counts[$severity])){
        $counts[$severity] = 0;
    }
    $counts[$severity]++;
}

function examdash_build_analytics_alerts($subjectWatchRows, $subjectPerformanceRows, $completenessSummary, $completenessSubjectRows, $termTrendOverview, $termMovementRows, $termPreviousName){
    $alerts = array();
    $counts = array("Critical" => 0, "Warning" => 0, "Info" => 0, "Success" => 0);

    if((int)$completenessSummary["missing_both_rows"] > 0){
        examdash_add_alert(
            $alerts,
            $counts,
            "Critical",
            "Score Entry",
            $completenessSummary["missing_both_rows"]." expected entries have no scores at all",
            "Some student-subject rows are missing both Class Score and Exam Score for this semester.",
            "Open the completeness checker and finish the missing entries before publishing or printing results."
        );
    }
    if((int)$completenessSummary["missing_class_rows"] > 0){
        examdash_add_alert(
            $alerts,
            $counts,
            "Warning",
            "Score Entry",
            $completenessSummary["missing_class_rows"]." entries are missing class scores",
            "These rows already have exam scores but their class scores are still absent.",
            "Review the issue list and complete the missing Class Score rows."
        );
    }
    if((int)$completenessSummary["missing_exam_rows"] > 0){
        examdash_add_alert(
            $alerts,
            $counts,
            "Warning",
            "Score Entry",
            $completenessSummary["missing_exam_rows"]." entries are missing exam scores",
            "These rows already have class scores but their exam scores are still absent.",
            "Review the issue list and complete the missing Exam Score rows."
        );
    }
    if((int)$completenessSummary["duplicate_rows"] > 0){
        examdash_add_alert(
            $alerts,
            $counts,
            "Warning",
            "Data Quality",
            $completenessSummary["duplicate_rows"]." rows have duplicate score components",
            "At least one student-subject row has more than one Class Score or more than one Exam Score.",
            "Clean the duplicate entries so each row has only one Class Score and one Exam Score."
        );
    }

    $subjectIssueCount = 0;
    foreach($completenessSubjectRows as $subjectRow){
        $incompleteCount = (int)$subjectRow["expected_rows"] - (int)$subjectRow["complete_rows"];
        if($incompleteCount <= 0){
            continue;
        }
        $subjectIssueCount++;
        examdash_add_alert(
            $alerts,
            $counts,
            ((int)$subjectRow["completion_pct"] < 70) ? "Critical" : "Warning",
            "Subject Coverage",
            $subjectRow["subject"]." has ".$incompleteCount." incomplete score row(s)",
            "Completion for ".$subjectRow["subject"]." is ".$subjectRow["completion_pct"]."% in the current semester.",
            "Use the completeness checker to fill or clean the missing ".$subjectRow["subject"]." entries."
        );
        if($subjectIssueCount >= 3){
            break;
        }
    }

    if($termPreviousName !== ""){
        if($termTrendOverview["avg_change"] !== "" && (float)$termTrendOverview["avg_change"] <= -5){
            examdash_add_alert(
                $alerts,
                $counts,
                "Critical",
                "Class Trend",
                "Class average dropped by ".abs((float)$termTrendOverview["avg_change"])." points",
                "The current semester average is lower than semester ".$termPreviousName." for this same batch and class.",
                "Review subject watchlists and the declining-student list to identify where the drop is coming from."
            );
        } elseif($termTrendOverview["avg_change"] !== "" && (float)$termTrendOverview["avg_change"] <= -2.5){
            examdash_add_alert(
                $alerts,
                $counts,
                "Warning",
                "Class Trend",
                "Class average is trending downward",
                "The class average fell by ".abs((float)$termTrendOverview["avg_change"])." points compared with semester ".$termPreviousName.".",
                "Review subjects with the biggest drops before the next reporting cycle."
            );
        }

        if($termTrendOverview["pass_change"] !== "" && (float)$termTrendOverview["pass_change"] <= -10){
            examdash_add_alert(
                $alerts,
                $counts,
                "Critical",
                "Pass Rate",
                "Overall pass rate dropped by ".abs((float)$termTrendOverview["pass_change"])." percentage points",
                "Compared with semester ".$termPreviousName.", fewer results are now landing in the pass range A1-C6.",
                "Check subjects with the sharpest pass-rate drops and address any missing score rows."
            );
        } elseif($termTrendOverview["pass_change"] !== "" && (float)$termTrendOverview["pass_change"] <= -5){
            examdash_add_alert(
                $alerts,
                $counts,
                "Warning",
                "Pass Rate",
                "Overall pass rate is lower than the previous semester",
                "Pass rate moved down by ".abs((float)$termTrendOverview["pass_change"])." percentage points against semester ".$termPreviousName.".",
                "Review the subject watchlist and the students needing attention."
            );
        }
    }

    $subjectDropCount = 0;
    foreach($subjectWatchRows as $watchRow){
        $isPassDrop = ($watchRow["pass_change"] !== "" && (float)$watchRow["pass_change"] <= -10);
        $isAvgDrop = ($watchRow["avg_change"] !== "" && (float)$watchRow["avg_change"] <= -5);
        if(!$isPassDrop && !$isAvgDrop){
            continue;
        }
        $subjectDropCount++;
        $severity = ($watchRow["pass_change"] !== "" && (float)$watchRow["pass_change"] <= -20) ? "Critical" : "Warning";
        $detailParts = array();
        if($watchRow["pass_change"] !== ""){
            $detailParts[] = "pass rate ".examdash_delta_text($watchRow["pass_change"]);
        }
        if($watchRow["avg_change"] !== ""){
            $detailParts[] = "average ".examdash_delta_text($watchRow["avg_change"]);
        }
        examdash_add_alert(
            $alerts,
            $counts,
            $severity,
            "Subject Trend",
            $watchRow["subject"]." is slipping compared with the previous semester",
            "Current movement: ".implode(", ", $detailParts).".",
            "Review ".$watchRow["subject"]." teaching coverage, score completion, and the students now underperforming in this subject."
        );
        if($subjectDropCount >= 4){
            break;
        }
    }

    $coverageAlertCount = 0;
    foreach($subjectPerformanceRows as $subjectRow){
        if((int)$subjectRow["missing_students"] <= 0){
            continue;
        }
        $coverageAlertCount++;
        $severity = ((int)$subjectRow["missing_students"] >= 5 || (float)$subjectRow["coverage_pct"] < 85) ? "Warning" : "Info";
        examdash_add_alert(
            $alerts,
            $counts,
            $severity,
            "Missing Results",
            $subjectRow["missing_students"]." student(s) have no ".$subjectRow["subject"]." result yet",
            $subjectRow["subject"]." coverage is ".$subjectRow["coverage_pct"]."% for the current semester.",
            "Check whether those students are absent, not yet marked, or missing from the expected score rows."
        );
        if($coverageAlertCount >= 3){
            break;
        }
    }

    if(count($termMovementRows) > 0){
        $declinedCount = 0;
        $improvedCount = 0;
        foreach($termMovementRows as $movementRow){
            if($movementRow["movement"] === "Declined"){
                $declinedCount++;
            } elseif($movementRow["movement"] === "Improved"){
                $improvedCount++;
            }
        }
        if($declinedCount >= max(5, (int)ceil(count($termMovementRows) * 0.3))){
            examdash_add_alert(
                $alerts,
                $counts,
                "Warning",
                "Student Movement",
                $declinedCount." students declined against the previous semester",
                "A sizable share of the tracked students are now scoring below their earlier semester average.",
                "Use the cross-term student movement table to identify who needs intervention first."
            );
        }
        if($improvedCount > 0){
            examdash_add_alert(
                $alerts,
                $counts,
                "Success",
                "Student Movement",
                $improvedCount." students improved this semester",
                "Some students are moving upward compared with the previous scored semester.",
                "Review the improved group as a positive signal and see what support can be repeated elsewhere."
            );
        }
    }

    usort($alerts, function($a, $b){
        $weightCompare = examdash_alert_weight($a["severity"]) - examdash_alert_weight($b["severity"]);
        if($weightCompare !== 0){
            return $weightCompare;
        }
        $areaCompare = strcmp($a["area"], $b["area"]);
        if($areaCompare !== 0){
            return $areaCompare;
        }
        return strcmp($a["title"], $b["title"]);
    });

    return array(
        "alerts" => $alerts,
        "counts" => $counts
    );
}

function examdash_build_subject_performance($rows, $expectedStudents){
    $subjectMap = array();

    foreach($rows as $row){
        $subjectId = $row["subjectid"];
        if(!isset($subjectMap[$subjectId])){
            $subjectMap[$subjectId] = array(
                "subjectid" => $subjectId,
                "subject" => $row["subject"],
                "results_count" => 0,
                "students" => array(),
                "total_marks" => 0,
                "total_possible" => 0,
                "pass_count" => 0,
                "strong_count" => 0,
                "top_percent" => -1,
                "top_student" => "N/A"
            );
        }
        $subjectMap[$subjectId]["results_count"]++;
        $subjectMap[$subjectId]["students"][$row["userid"]] = true;
        $subjectMap[$subjectId]["total_marks"] += (float)$row["total_mark"];
        $subjectMap[$subjectId]["total_possible"] += (float)$row["total_possible"];
        if(examdash_is_pass($row["grade"])){
            $subjectMap[$subjectId]["pass_count"]++;
        }
        if(examdash_is_strong($row["grade"])){
            $subjectMap[$subjectId]["strong_count"]++;
        }
        if((float)$row["percent"] > (float)$subjectMap[$subjectId]["top_percent"]){
            $subjectMap[$subjectId]["top_percent"] = (float)$row["percent"];
            $subjectMap[$subjectId]["top_student"] = $row["student_name"];
        }
    }

    $subjects = array_values($subjectMap);
    foreach($subjects as $idx => $subject){
        $studentCount = count($subject["students"]);
        $subject["students_count"] = $studentCount;
        $subject["avg_percent"] = $subject["total_possible"] > 0 ? round(($subject["total_marks"] * 100) / $subject["total_possible"], 2) : 0;
        $subject["pass_pct"] = $subject["results_count"] > 0 ? round(($subject["pass_count"] * 100) / $subject["results_count"], 2) : 0;
        $subject["strong_pct"] = $subject["results_count"] > 0 ? round(($subject["strong_count"] * 100) / $subject["results_count"], 2) : 0;
        $subject["coverage_pct"] = $expectedStudents > 0 ? round(($studentCount * 100) / $expectedStudents, 2) : 0;
        $subject["missing_students"] = $expectedStudents > 0 ? max(0, $expectedStudents - $studentCount) : 0;
        unset($subject["students"]);
        $subjects[$idx] = $subject;
    }

    usort($subjects, function($a, $b){
        if((float)$a["avg_percent"] === (float)$b["avg_percent"]){
            return strcmp($a["subject"], $b["subject"]);
        }
        return ((float)$a["avg_percent"] < (float)$b["avg_percent"]) ? 1 : -1;
    });

    return $subjects;
}

function examdash_build_grade_distribution($rows){
    $distribution = array();
    foreach(examdash_grade_list() as $grade){
        $distribution[$grade] = 0;
    }
    foreach($rows as $row){
        if(isset($distribution[$row["grade"]])){
            $distribution[$row["grade"]]++;
        }
    }
    return $distribution;
}

function examdash_build_gender_subject_comparison($rows){
    $map = array();
    foreach($rows as $row){
        if(!in_array($row["gender"], array("Male", "Female"), true)){
            continue;
        }
        $subjectId = $row["subjectid"];
        if(!isset($map[$subjectId])){
            $map[$subjectId] = array(
                "subjectid" => $subjectId,
                "subject" => $row["subject"],
                "Male_results" => 0,
                "Male_pass" => 0,
                "Female_results" => 0,
                "Female_pass" => 0
            );
        }
        $genderKey = $row["gender"];
        $map[$subjectId][$genderKey."_results"]++;
        if(examdash_is_pass($row["grade"])){
            $map[$subjectId][$genderKey."_pass"]++;
        }
    }

    $rowsOut = array_values($map);
    foreach($rowsOut as $idx => $row){
        $row["Male_pass_pct"] = $row["Male_results"] > 0 ? round(($row["Male_pass"] * 100) / $row["Male_results"], 2) : "";
        $row["Female_pass_pct"] = $row["Female_results"] > 0 ? round(($row["Female_pass"] * 100) / $row["Female_results"], 2) : "";
        $row["gap"] = ($row["Male_pass_pct"] !== "" && $row["Female_pass_pct"] !== "") ? round((float)$row["Male_pass_pct"] - (float)$row["Female_pass_pct"], 2) : "";
        $rowsOut[$idx] = $row;
    }

    usort($rowsOut, function($a, $b){
        return strcmp($a["subject"], $b["subject"]);
    });
    return $rowsOut;
}

function examdash_url($page, $defaults, $overrides = array()){
    $params = $defaults;
    foreach($overrides as $key => $value){
        if($value === null){
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    foreach($params as $key => $value){
        if($value === ""){
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return $query === "" ? $page : $page."?".$query;
}

// __EXAMDASH_HELPERS__
$currentPage = "internal-exam-analysis.php";
$gradeList = examdash_grade_list();
$teacherWhere = "";
$teacherIdEsc = "";
if($isTeacher){
    $teacherIdEsc = mysqli_real_escape_string($con, $_SESSION['USERID']);
    $teacherWhere = " AND sa.userid='".$teacherIdEsc."' ";
}

$filterBatch = isset($_GET["batchid"]) ? trim((string)$_GET["batchid"]) : "";
$filterAcademicYear = isset($_GET["academic_year"]) ? examdash_normalize_year($_GET["academic_year"]) : "";
$filterClass = isset($_GET["class_id"]) ? trim((string)$_GET["class_id"]) : "";
$filterTerm = isset($_GET["term_id"]) ? trim((string)$_GET["term_id"]) : "";
$focusSubject = isset($_GET["focus_subject"]) ? trim((string)$_GET["focus_subject"]) : "";
$focusGrade = isset($_GET["focus_grade"]) ? strtoupper(trim((string)$_GET["focus_grade"])) : "";
$focusGender = isset($_GET["focus_gender"]) ? trim((string)$_GET["focus_gender"]) : "";
$studentQuery = isset($_GET["student_query"]) ? trim((string)$_GET["student_query"]) : "";
$selectedStudentKey = isset($_GET["student_key"]) ? trim((string)$_GET["student_key"]) : "";

if(!in_array($focusGrade, $gradeList, true)){
    $focusGrade = "";
}
if(!in_array($focusGender, array("Male", "Female"), true)){
    $focusGender = "";
}

$queryDefaults = array(
    "batchid" => $filterBatch,
    "academic_year" => $filterAcademicYear,
    "class_id" => $filterClass,
    "term_id" => $filterTerm,
    "focus_subject" => $focusSubject,
    "focus_grade" => $focusGrade,
    "focus_gender" => $focusGender,
    "student_query" => $studentQuery,
    "student_key" => $selectedStudentKey
);

$batchOptions = array();
$yearOptions = array();
$classOptions = array();
$termOptions = array();
$subjectOptions = array();

$batchRes = mysqli_query($con, "SELECT DISTINCT bch.batchid,bch.batch
    FROM tblsubjectassignment sa
    INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
    WHERE 1=1 ".$teacherWhere."
    ORDER BY bch.batch DESC");
if($batchRes){
    while($row = mysqli_fetch_array($batchRes, MYSQLI_ASSOC)){
        $batchOptions[] = $row;
    }
}

$yearSql = "SELECT DISTINCT ".examdash_assignment_year_sql("sa")." AS academicyear
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    WHERE 1=1 ".$teacherWhere;
if($filterBatch !== ""){
    $yearSql .= " AND sa.batchid='".mysqli_real_escape_string($con, $filterBatch)."'";
}
if($filterClass !== ""){
    $yearSql .= " AND sc.classid='".mysqli_real_escape_string($con, $filterClass)."'";
}
$yearSql .= " ORDER BY academicyear DESC";
$yearRes = mysqli_query($con, $yearSql);
if($yearRes){
    while($row = mysqli_fetch_array($yearRes, MYSQLI_ASSOC)){
        $yearValue = examdash_normalize_year(isset($row["academicyear"]) ? $row["academicyear"] : "");
        if($yearValue !== ""){
            $yearOptions[] = array("academicyear" => $yearValue);
        }
    }
}

$classSql = "SELECT DISTINCT ce.class_entryid,ce.class_name
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblclassentry ce ON ce.class_entryid=sc.classid
    WHERE 1=1 ".$teacherWhere;
if($filterBatch !== ""){
    $classSql .= " AND sa.batchid='".mysqli_real_escape_string($con, $filterBatch)."'";
}
if($filterAcademicYear !== ""){
    $classSql .= " AND ".examdash_assignment_year_sql("sa")."='".mysqli_real_escape_string($con, $filterAcademicYear)."'";
}
$classSql .= " ORDER BY ce.class_name ASC";
$classRes = mysqli_query($con, $classSql);
if($classRes){
    while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
        $classOptions[] = $row;
    }
}

$termSql = "SELECT DISTINCT sa.termname
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    WHERE 1=1 ".$teacherWhere;
if($filterBatch !== ""){
    $termSql .= " AND sa.batchid='".mysqli_real_escape_string($con, $filterBatch)."'";
}
if($filterAcademicYear !== ""){
    $termSql .= " AND ".examdash_assignment_year_sql("sa")."='".mysqli_real_escape_string($con, $filterAcademicYear)."'";
}
if($filterClass !== ""){
    $termSql .= " AND sc.classid='".mysqli_real_escape_string($con, $filterClass)."'";
}
$termSql .= " ORDER BY sa.termname ASC";
$termRes = mysqli_query($con, $termSql);
if($termRes){
    while($row = mysqli_fetch_array($termRes, MYSQLI_ASSOC)){
        $termOptions[] = $row;
    }
}

$subjectSql = "SELECT DISTINCT sub.subjectid,sub.subject
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
    WHERE 1=1 ".$teacherWhere;
if($filterBatch !== ""){
    $subjectSql .= " AND sa.batchid='".mysqli_real_escape_string($con, $filterBatch)."'";
}
if($filterAcademicYear !== ""){
    $subjectSql .= " AND ".examdash_assignment_year_sql("sa")."='".mysqli_real_escape_string($con, $filterAcademicYear)."'";
}
if($filterClass !== ""){
    $subjectSql .= " AND sc.classid='".mysqli_real_escape_string($con, $filterClass)."'";
}
if($filterTerm !== ""){
    $subjectSql .= " AND sa.termname='".mysqli_real_escape_string($con, $filterTerm)."'";
}
$subjectSql .= " ORDER BY sub.subject ASC";
$subjectRes = mysqli_query($con, $subjectSql);
if($subjectRes){
    while($row = mysqli_fetch_array($subjectRes, MYSQLI_ASSOC)){
        $subjectOptions[] = $row;
    }
}

$scopeReady = ($filterBatch !== "" && $filterAcademicYear !== "" && $filterClass !== "" && $filterTerm !== "");
$scopeTitle = "";
$academicYearName = "";
$batchName = "";
$className = "";
$scopeMessage = "";

$registeredStudents = array();
$registeredGenderCounts = array("Male" => 0, "Female" => 0, "Not Set" => 0);
$allResultRows = array();
$comparisonRows = array();
$dashboardRows = array();
$gradeDistribution = examdash_build_grade_distribution(array());
$summary = examdash_build_summary(array());
$subjectPerformanceRows = array();
$studentSummaries = array();
$studentSummaryMap = array();
$studentMatches = array();
$selectedStudent = null;
$genderAnalysisRows = array();
$genderPerformance = array("Male" => examdash_build_summary(array()), "Female" => examdash_build_summary(array()));
$scoredStudentCount = 0;
$expectedStudentCount = 0;
$topSubjects = array();
$bottomSubjects = array();
$resultsListingRows = array();
$boardPublishRows = array();
$completenessRows = array();
$completenessSubjectRows = array();
$completenessIssueRows = array();
$termRegisteredCounts = array();
$termScopeRows = array();
$termTrendRows = array();
$termMovementRows = array();
$termImprovedRows = array();
$termDeclinedRows = array();
$termStudentHistoryMap = array();
$subjectTrendMap = array();
$subjectWatchRows = array();
$analyticsAlerts = array();
$analyticsAlertCounts = array("Critical" => 0, "Warning" => 0, "Info" => 0, "Success" => 0);
$selectedStudentTrendRows = array();
$termPreviousName = "";
$termTrendOverview = array(
    "tracked_terms" => 0,
    "current_avg_percent" => "",
    "previous_avg_percent" => "",
    "avg_change" => "",
    "current_pass_pct" => "",
    "previous_pass_pct" => "",
    "pass_change" => "",
    "current_students" => 0,
    "previous_students" => 0,
    "improved_count" => 0,
    "declined_count" => 0,
    "flat_count" => 0,
    "new_count" => 0
);
$completenessSummary = array(
    "expected_rows" => 0,
    "complete_rows" => 0,
    "missing_class_rows" => 0,
    "missing_exam_rows" => 0,
    "missing_both_rows" => 0,
    "duplicate_rows" => 0,
    "issue_rows" => 0,
    "completion_pct" => 0
);

if($scopeReady){
    $batchEsc = mysqli_real_escape_string($con, $filterBatch);
    $yearEsc = mysqli_real_escape_string($con, $filterAcademicYear);
    $classEsc = mysqli_real_escape_string($con, $filterClass);
    $termEsc = mysqli_real_escape_string($con, $filterTerm);
    $academicYearName = $filterAcademicYear;

    $batchNameRes = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$batchEsc' LIMIT 1");
    if($batchNameRes && $row = mysqli_fetch_array($batchNameRes, MYSQLI_ASSOC)){
        $batchName = $row["batch"];
    }
    $classNameRes = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$classEsc' LIMIT 1");
    if($classNameRes && $row = mysqli_fetch_array($classNameRes, MYSQLI_ASSOC)){
        $className = $row["class_name"];
    }
    $scopeTitle = trim($academicYearName." / ".$batchName." / ".$className." / Semester ".$filterTerm, " /");

    $registeredSql = "SELECT DISTINCT su.userid,su.firstname,su.othernames,su.surname,su.gender
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid";
    if($isTeacher){
        $registeredSql .= " INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND sa.userid='".$teacherIdEsc."'";
    }
    $registeredSql .= " WHERE tr.batchid='$batchEsc'
        AND tr.class_entryid='$classEsc'
        AND tr.termname='$termEsc'
        AND ".examdash_termregistry_year_sql($con, "tr")."='$yearEsc'";
    if($isTeacher){
        $registeredSql .= " AND ".examdash_assignment_year_sql("sa")."='$yearEsc'";
    }
    $registeredSql .= "
        AND su.systemtype='Student'
        ORDER BY su.firstname ASC,su.othernames ASC,su.surname ASC";
    $registeredRes = mysqli_query($con, $registeredSql);
    if($registeredRes){
        while($row = mysqli_fetch_array($registeredRes, MYSQLI_ASSOC)){
            $gender = examdash_normalize_gender($row["gender"]);
            $registeredStudents[] = array(
                "userid" => $row["userid"],
                "student_name" => trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]),
                "gender" => $gender
            );
            if(!isset($registeredGenderCounts[$gender])){
                $registeredGenderCounts[$gender] = 0;
            }
            $registeredGenderCounts[$gender]++;
        }
    }

    $trendRegisteredSql = "SELECT tr.termname,su.gender,COUNT(DISTINCT tr.userid) AS registered_count
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid
        WHERE tr.batchid='$batchEsc'
            AND tr.class_entryid='$classEsc'
            AND ".examdash_termregistry_year_sql($con, "tr")."='$yearEsc'
            AND su.systemtype='Student'
        GROUP BY tr.termname,su.gender
        ORDER BY tr.termname ASC";
    $trendRegisteredRes = mysqli_query($con, $trendRegisteredSql);
    if($trendRegisteredRes){
        while($row = mysqli_fetch_array($trendRegisteredRes, MYSQLI_ASSOC)){
            $termName = (string)$row["termname"];
            $gender = examdash_normalize_gender($row["gender"]);
            if(!isset($termRegisteredCounts[$termName])){
                $termRegisteredCounts[$termName] = 0;
            }
            if($focusGender !== "" && $gender !== $focusGender){
                continue;
            }
            $termRegisteredCounts[$termName] += (int)$row["registered_count"];
        }
    }

    $resultSql = "SELECT
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            su.gender,
            sc.subjectid,
            sub.subject,
            SUM(mk.mark) AS total_mark,
            SUM(CASE WHEN mk.totalmark > 0 THEN mk.totalmark ELSE 0 END) AS total_possible,
            COUNT(mk.markid) AS component_count
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        INNER JOIN tblsystemuser su ON su.userid=mk.userid
        INNER JOIN tbltermregistry tr ON tr.userid=su.userid AND tr.batchid=sa.batchid AND tr.termname=sa.termname AND tr.class_entryid=sc.classid
        WHERE mk.status='active'
            AND su.systemtype='Student'
            AND sa.batchid='$batchEsc'
            AND sc.classid='$classEsc'
            AND sa.termname='$termEsc'
            AND ".examdash_assignment_year_sql("sa")."='$yearEsc'
            AND ".examdash_termregistry_year_sql($con, "tr")."='$yearEsc'".$teacherWhere."
        GROUP BY su.userid,su.firstname,su.othernames,su.surname,su.gender,sc.subjectid,sub.subject
        HAVING total_possible > 0
        ORDER BY sub.subject ASC,su.firstname ASC,su.othernames ASC,su.surname ASC";
    $resultRes = mysqli_query($con, $resultSql);
    if($resultRes){
        while($row = mysqli_fetch_array($resultRes, MYSQLI_ASSOC)){
            $studentName = trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]);
            $totalMark = (float)$row["total_mark"];
            $totalPossible = (float)$row["total_possible"];
            $percent = $totalPossible > 0 ? round(($totalMark * 100) / $totalPossible, 2) : 0;
            $allResultRows[] = array(
                "userid" => $row["userid"],
                "student_name" => strtoupper($studentName),
                "gender" => examdash_normalize_gender($row["gender"]),
                "subjectid" => $row["subjectid"],
                "subject" => $row["subject"],
                "total_mark" => round($totalMark, 2),
                "total_possible" => round($totalPossible, 2),
                "percent" => $percent,
                "grade" => examdash_grade_from_percent($percent),
                "component_count" => (int)$row["component_count"]
            );
        }
    }

    $trendResultSql = "SELECT
            sa.termname,
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            su.gender,
            sc.subjectid,
            sub.subject,
            SUM(mk.mark) AS total_mark,
            SUM(CASE WHEN mk.totalmark > 0 THEN mk.totalmark ELSE 0 END) AS total_possible,
            COUNT(mk.markid) AS component_count
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        INNER JOIN tblsystemuser su ON su.userid=mk.userid
        INNER JOIN tbltermregistry tr ON tr.userid=su.userid AND tr.batchid=sa.batchid AND tr.termname=sa.termname AND tr.class_entryid=sc.classid
        WHERE mk.status='active'
            AND su.systemtype='Student'
            AND sa.batchid='$batchEsc'
            AND sc.classid='$classEsc'
            AND ".examdash_assignment_year_sql("sa")."='$yearEsc'
            AND ".examdash_termregistry_year_sql($con, "tr")."='$yearEsc'".$teacherWhere."
            ".($focusSubject !== "" ? " AND sc.subjectid='".mysqli_real_escape_string($con, $focusSubject)."'" : "")."
        GROUP BY sa.termname,su.userid,su.firstname,su.othernames,su.surname,su.gender,sc.subjectid,sub.subject
        HAVING total_possible > 0
        ORDER BY sa.termname ASC,sub.subject ASC,su.firstname ASC,su.othernames ASC,su.surname ASC";
    $trendResultRes = mysqli_query($con, $trendResultSql);
    if($trendResultRes){
        while($row = mysqli_fetch_array($trendResultRes, MYSQLI_ASSOC)){
            $gender = examdash_normalize_gender($row["gender"]);
            if($focusGender !== "" && $gender !== $focusGender){
                continue;
            }
            $studentName = trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]);
            $totalMark = (float)$row["total_mark"];
            $totalPossible = (float)$row["total_possible"];
            $percent = $totalPossible > 0 ? round(($totalMark * 100) / $totalPossible, 2) : 0;
            $termScopeRows[] = array(
                "termname" => (string)$row["termname"],
                "userid" => $row["userid"],
                "student_name" => strtoupper($studentName),
                "gender" => $gender,
                "subjectid" => $row["subjectid"],
                "subject" => $row["subject"],
                "total_mark" => round($totalMark, 2),
                "total_possible" => round($totalPossible, 2),
                "percent" => $percent,
                "grade" => examdash_grade_from_percent($percent),
                "component_count" => (int)$row["component_count"]
            );
        }
    }
    $termTrendRows = examdash_build_term_trend_rows($termScopeRows, $termRegisteredCounts);
    $termStudentHistoryMap = examdash_build_student_term_history($termScopeRows);
    $subjectTrendMap = examdash_build_subject_term_tracking($termScopeRows, $termRegisteredCounts);
    $termPreviousName = examdash_find_previous_term_name($termTrendRows, $filterTerm);
    $termMovementRows = examdash_build_term_movement_rows($termStudentHistoryMap, $filterTerm, $termPreviousName);
    $subjectWatchRows = examdash_build_subject_term_watch_rows($subjectTrendMap, $filterTerm, $termPreviousName);
    foreach($termMovementRows as $movementRow){
        if($movementRow["movement"] === "Improved"){
            $termImprovedRows[] = $movementRow;
            $termTrendOverview["improved_count"]++;
        } elseif($movementRow["movement"] === "Declined"){
            $termDeclinedRows[] = $movementRow;
            $termTrendOverview["declined_count"]++;
        } elseif($movementRow["movement"] === "Flat"){
            $termTrendOverview["flat_count"]++;
        } elseif($movementRow["movement"] === "New"){
            $termTrendOverview["new_count"]++;
        }
    }
    usort($termDeclinedRows, function($a, $b){
        if((float)$a["avg_change"] === (float)$b["avg_change"]){
            return strcmp($a["student_name"], $b["student_name"]);
        }
        return ((float)$a["avg_change"] < (float)$b["avg_change"]) ? -1 : 1;
    });
    $currentTrendRow = examdash_find_term_trend_row($termTrendRows, $filterTerm);
    $previousTrendRow = ($termPreviousName !== "") ? examdash_find_term_trend_row($termTrendRows, $termPreviousName) : null;
    $termTrendOverview["tracked_terms"] = count($termTrendRows);
    if($currentTrendRow !== null){
        $termTrendOverview["current_avg_percent"] = $currentTrendRow["avg_percent"];
        $termTrendOverview["current_pass_pct"] = $currentTrendRow["pass_pct"];
        $termTrendOverview["current_students"] = (int)$currentTrendRow["students_scored"];
    }
    if($previousTrendRow !== null){
        $termTrendOverview["previous_avg_percent"] = $previousTrendRow["avg_percent"];
        $termTrendOverview["previous_pass_pct"] = $previousTrendRow["pass_pct"];
        $termTrendOverview["previous_students"] = (int)$previousTrendRow["students_scored"];
        if($currentTrendRow !== null && $currentTrendRow["avg_percent"] !== "" && $previousTrendRow["avg_percent"] !== ""){
            $termTrendOverview["avg_change"] = round((float)$currentTrendRow["avg_percent"] - (float)$previousTrendRow["avg_percent"], 2);
        }
        if($currentTrendRow !== null && $currentTrendRow["pass_pct"] !== "" && $previousTrendRow["pass_pct"] !== ""){
            $termTrendOverview["pass_change"] = round((float)$currentTrendRow["pass_pct"] - (float)$previousTrendRow["pass_pct"], 2);
        }
    }

    $focusSubjectSql = "";
    if($focusSubject !== ""){
        $focusSubjectSql = " AND sc.subjectid='".mysqli_real_escape_string($con, $focusSubject)."'";
    }
    $completenessSql = "SELECT
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            su.gender,
            sc.subjectid,
            sub.subject,
            SUM(CASE WHEN mk.status='active' AND mk.testtype='Class Score' THEN 1 ELSE 0 END) AS class_score_rows,
            SUM(CASE WHEN mk.status='active' AND mk.testtype='Exam Score' THEN 1 ELSE 0 END) AS exam_score_rows
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid
        INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        LEFT JOIN tblmark mk ON mk.assignmentid=sa.assignmentid AND mk.userid=su.userid
        WHERE tr.batchid='$batchEsc'
            AND tr.class_entryid='$classEsc'
            AND tr.termname='$termEsc'
            AND ".examdash_termregistry_year_sql($con, "tr")."='$yearEsc'
            AND ".examdash_assignment_year_sql("sa")."='$yearEsc'
            AND su.systemtype='Student'".$teacherWhere.$focusSubjectSql."
        GROUP BY su.userid,su.firstname,su.othernames,su.surname,su.gender,sc.subjectid,sub.subject
        ORDER BY sub.subject ASC,su.firstname ASC,su.othernames ASC,su.surname ASC";
    $completenessRes = mysqli_query($con, $completenessSql);
    if($completenessRes){
        while($row = mysqli_fetch_array($completenessRes, MYSQLI_ASSOC)){
            $gender = examdash_normalize_gender($row["gender"]);
            if($focusGender !== "" && $gender !== $focusGender){
                continue;
            }
            $completenessRows[] = array(
                "userid" => $row["userid"],
                "student_name" => strtoupper(trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"])),
                "gender" => $gender,
                "subjectid" => $row["subjectid"],
                "subject" => $row["subject"],
                "class_score_rows" => (int)$row["class_score_rows"],
                "exam_score_rows" => (int)$row["exam_score_rows"]
            );
        }
    }
    $completenessPack = examdash_build_completeness_analysis($completenessRows);
    $completenessSummary = $completenessPack["summary"];
    $completenessSubjectRows = $completenessPack["subject_rows"];
    $completenessIssueRows = $completenessPack["issue_rows"];

    $comparisonRows = examdash_filter_rows($allResultRows, $focusSubject, $focusGender, "");
    $dashboardRows = examdash_filter_rows($allResultRows, $focusSubject, $focusGender, $focusGrade);
    $gradeDistribution = examdash_build_grade_distribution($comparisonRows);
    $summary = examdash_build_summary($dashboardRows);
    $expectedStudentCount = ($focusGender !== "" && isset($registeredGenderCounts[$focusGender])) ? (int)$registeredGenderCounts[$focusGender] : count($registeredStudents);
    $subjectPerformanceRows = examdash_build_subject_performance($comparisonRows, $expectedStudentCount);
    $studentSummaries = examdash_build_student_summaries($comparisonRows);
    foreach($studentSummaries as $student){
        $studentSummaryMap[$student["userid"]] = $student;
    }
    $resultsListingRows = examdash_build_results_listing($registeredStudents, $allResultRows);
    $boardPublishRows = examdash_build_board_publish_rows($registeredStudents, $allResultRows);
    $scoredStudentCount = examdash_unique_count($dashboardRows, "userid");
    $topSubjects = array_slice($subjectPerformanceRows, 0, 5);
    $bottomSubjects = array_slice(array_reverse($subjectPerformanceRows), 0, 5);
    $analyticsPack = examdash_build_analytics_alerts(
        $subjectWatchRows,
        $subjectPerformanceRows,
        $completenessSummary,
        $completenessSubjectRows,
        $termTrendOverview,
        $termMovementRows,
        $termPreviousName
    );
    $analyticsAlerts = $analyticsPack["alerts"];
    $analyticsAlertCounts = $analyticsPack["counts"];

    $genderScopeRows = examdash_filter_rows($allResultRows, $focusSubject, "", "");
    $genderPerformance["Male"] = examdash_build_summary(examdash_filter_rows($genderScopeRows, "", "Male", ""));
    $genderPerformance["Female"] = examdash_build_summary(examdash_filter_rows($genderScopeRows, "", "Female", ""));
    $genderAnalysisRows = examdash_build_gender_subject_comparison($genderScopeRows);

    if($selectedStudentKey !== "" && isset($studentSummaryMap[$selectedStudentKey])){
        $selectedStudent = $studentSummaryMap[$selectedStudentKey];
    }
    if($studentQuery !== ""){
        $needle = strtolower($studentQuery);
        foreach($studentSummaries as $student){
            if(strpos($student["search_blob"], $needle) !== false){
                $studentMatches[] = $student;
            }
        }
        if($selectedStudent === null && count($studentMatches) > 0){
            $selectedStudent = $studentMatches[0];
        }
    }
    if($selectedStudent !== null && isset($termStudentHistoryMap[$selectedStudent["userid"]])){
        $selectedStudentTrendRows = $termStudentHistoryMap[$selectedStudent["userid"]]["term_rows"];
    }

    if(count($allResultRows) === 0){
        $scopeMessage = "No active internal exam scores were found for this scope yet.";
    } elseif(count($dashboardRows) === 0){
        $scopeMessage = "Scores exist for this scope, but nothing matched the current subject, gender, or grade filter.";
    }

    if(isset($_GET["export"]) && $_GET["export"] === "csv"){
        $filenameParts = array("internal-exam-analysis");
        if($academicYearName !== ""){
            $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($academicYearName));
        }
        if($batchName !== ""){
            $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($batchName));
        }
        if($className !== ""){
            $filenameParts[] = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($className));
        }
        $filenameParts[] = "sem-".$filterTerm;
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"".implode("-", $filenameParts).".csv\"");
        $out = fopen("php://output", "w");
        fputcsv($out, array("Student ID", "Student Name", "Gender", "Subject", "Score Obtained", "Total Possible", "Percent", "Grade", "Components"));
        foreach($dashboardRows as $row){
            fputcsv($out, array($row["userid"], $row["student_name"], $row["gender"], $row["subject"], $row["total_mark"], $row["total_possible"], $row["percent"], $row["grade"], $row["component_count"]));
        }
        fclose($out);
        exit();
    }
}

// __EXAMDASH_DATA__
?>
<html>
<head>
<?php
include("links.php");
?>
<style>
:root{
    --examdash-bg:#f3f7fb;
    --examdash-card:#ffffff;
    --examdash-line:#d7e2ee;
    --examdash-text:#19324d;
    --examdash-muted:#5f7388;
    --examdash-accent:#0b66c3;
    --examdash-accent-2:#0f766e;
    --examdash-soft:#eef5fb;
}
body{
    background:linear-gradient(180deg, #f8fbfe 0%, #edf4fb 100%);
}
.examdash-wrap{
    max-width:1240px;
    margin:18px auto 28px;
    padding:0 14px;
}
.examdash-card{
    background:var(--examdash-card);
    border:1px solid var(--examdash-line);
    border-radius:18px;
    padding:18px;
    box-shadow:0 16px 40px rgba(15,23,42,0.05);
    margin-bottom:16px;
}
.examdash-title{
    margin:0 0 6px;
    color:var(--examdash-text);
    font-size:30px;
    line-height:1.15;
}
.examdash-note{
    margin:0;
    color:var(--examdash-muted);
}
.examdash-badges{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:12px;
}
.examdash-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    border-radius:999px;
    background:var(--examdash-soft);
    color:var(--examdash-text);
    font-size:13px;
    border:1px solid #dbe7f3;
}
.examdash-toolbar{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:center;
}
.examdash-toolbar select,
.examdash-toolbar input[type="text"]{
    min-height:44px;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid var(--examdash-line);
    background:#fff;
    color:var(--examdash-text);
    flex:1 1 170px;
}
.examdash-btn,
.examdash-btn-muted{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:44px;
    padding:10px 14px;
    border-radius:12px;
    border:1px solid transparent;
    text-decoration:none;
    cursor:pointer;
    font-weight:600;
    touch-action:manipulation;
}
.examdash-btn{
    background:linear-gradient(90deg, var(--examdash-accent-2), var(--examdash-accent));
    color:#fff;
}
.examdash-btn-muted{
    background:#fff;
    color:var(--examdash-text);
    border-color:var(--examdash-line);
}
.examdash-stats{
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:12px;
}
.examdash-stat{
    padding:14px;
    border-radius:14px;
    background:linear-gradient(180deg, #ffffff 0%, #f8fbfe 100%);
    border:1px solid var(--examdash-line);
}
.examdash-stat h4{
    margin:0 0 8px;
    padding:0;
    background:transparent;
    border-bottom:0;
    color:var(--examdash-muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.examdash-stat strong{
    display:block;
    font-size:28px;
    color:var(--examdash-text);
}
.examdash-grid{
    display:grid;
    grid-template-columns:1.15fr .85fr;
    gap:16px;
}
.examdash-grid-even{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.examdash-table{
    width:100%;
    border-collapse:collapse;
}
.examdash-table th,
.examdash-table td{
    padding:10px 12px;
    border-bottom:1px solid #e5edf5;
    text-align:left;
    vertical-align:top;
}
.examdash-table th{
    color:var(--examdash-muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.examdash-table tr:last-child td{
    border-bottom:0;
}
.examdash-small{
    color:var(--examdash-muted);
    font-size:12px;
}
.examdash-alert{
    margin-top:12px;
    padding:12px 14px;
    border-radius:14px;
    background:#f0fdf4;
    border:1px solid #cce7d4;
    color:#14532d;
}
.examdash-empty{
    padding:18px;
    border-radius:14px;
    background:#f8fafc;
    border:1px dashed #c9d7e5;
    color:var(--examdash-muted);
}
.examdash-distribution{
    display:flex;
    flex-direction:column;
    gap:10px;
}
.examdash-distribution-row{
    display:grid;
    grid-template-columns:74px 1fr 56px 70px;
    gap:10px;
    align-items:center;
}
.examdash-progress{
    height:12px;
    border-radius:999px;
    background:#e5edf5;
    overflow:hidden;
}
.examdash-fill{
    height:100%;
    background:linear-gradient(90deg, var(--examdash-accent-2), var(--examdash-accent));
}
.examdash-link{
    color:var(--examdash-accent);
    text-decoration:none;
}
.examdash-collapsible{
    transition:border-color .2s ease, box-shadow .2s ease;
}
.examdash-collapsible-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    cursor:pointer;
}
.examdash-collapsible-title{
    flex:1 1 auto;
    min-width:0;
}
.examdash-collapsible-title h3{
    margin:0;
}
.examdash-collapsible-toggle{
    flex:0 0 auto;
    min-width:104px;
}
.examdash-collapsible:not(.is-open) .examdash-collapsible-body{
    display:none;
}
.examdash-collapsible-note{
    margin-top:6px;
}
.examdash-pill{
    display:inline-flex;
    align-items:center;
    padding:4px 8px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.03em;
    text-transform:uppercase;
}
.examdash-pill-critical{
    background:#fee2e2;
    color:#991b1b;
}
.examdash-pill-warning{
    background:#fef3c7;
    color:#92400e;
}
.examdash-pill-info{
    background:#dbeafe;
    color:#1d4ed8;
}
.examdash-pill-success{
    background:#dcfce7;
    color:#166534;
}
.examdash-trend-up{
    color:#0f766e;
    font-weight:700;
}
.examdash-trend-down{
    color:#b91c1c;
    font-weight:700;
}
.examdash-trend-flat{
    color:var(--examdash-muted);
    font-weight:700;
}
@media (max-width:1100px){
    .examdash-stats{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }
}
@media (max-width:900px){
    .examdash-grid,
    .examdash-grid-even{
        grid-template-columns:1fr;
    }
    .examdash-stats{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}
@media (max-width:640px){
    .examdash-wrap{
        padding:0 10px;
    }
    .examdash-card{
        padding:14px;
        border-radius:14px;
    }
    .examdash-title{
        font-size:24px;
    }
    .examdash-stats{
        grid-template-columns:1fr;
    }
}
</style>
<script type="text/javascript">
function examdashPrintSection(sectionId, titleText){
    var section = document.getElementById(sectionId);
    if(!section){
        return;
    }
    var printWindow = window.open("", "_blank");
    if(!printWindow){
        return;
    }
    var html = ""
        + "<!DOCTYPE html><html><head><title>" + (titleText || "Print") + "</title>"
        + "<style>"
        + "body{font-family:Arial,sans-serif;padding:24px;color:#19324d;background:#fff;}"
        + "h2,h3,h4{margin:0 0 12px;}"
        + "table{width:100%;border-collapse:collapse;margin-top:10px;}"
        + "th,td{border:1px solid #d7e2ee;padding:8px 10px;text-align:left;vertical-align:top;}"
        + "th{font-size:12px;text-transform:uppercase;color:#5f7388;letter-spacing:.04em;}"
        + ".examdash-card{border:1px solid #d7e2ee;border-radius:12px;padding:14px;margin-bottom:14px;}"
        + ".examdash-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}"
        + ".examdash-stat{border:1px solid #d7e2ee;border-radius:12px;padding:12px;}"
        + ".examdash-stat h4{margin:0 0 8px;font-size:12px;text-transform:uppercase;color:#5f7388;}"
        + ".examdash-stat strong{font-size:22px;}"
        + ".examdash-small{font-size:12px;color:#5f7388;}"
        + ".examdash-collapsible-body{display:block !important;}"
        + ".examdash-collapsible-toggle{display:none !important;}"
        + ".examdash-board-safe-search{display:none !important;}"
        + ".examdash-distribution-row{display:grid;grid-template-columns:74px 1fr 56px 70px;gap:10px;align-items:center;margin-bottom:8px;}"
        + ".examdash-progress{height:12px;border-radius:999px;background:#e5edf5;overflow:hidden;}"
        + ".examdash-fill{height:100%;background:linear-gradient(90deg,#0f766e,#0b66c3);}"
        + "</style></head><body><h2>" + (titleText || "Print") + "</h2>" + section.innerHTML + "</body></html>";
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(function(){ printWindow.print(); }, 250);
}

function examdashSetCollapsibleState(section, shouldOpen){
    if(!section){
        return;
    }
    section.classList.toggle("is-open", shouldOpen);
    var toggle = section.querySelector(".examdash-collapsible-toggle");
    if(toggle){
        toggle.innerHTML = shouldOpen
            ? "<i class='fa fa-chevron-up'></i> Hide"
            : "<i class='fa fa-chevron-down'></i> Open";
        toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    }
}

function examdashMakeCollapsible(sectionId){
    var section = document.getElementById(sectionId);
    if(!section || section.getAttribute("data-collapsible-ready") === "1"){
        return;
    }
    var title = section.querySelector("h3");
    if(!title){
        return;
    }

    section.setAttribute("data-collapsible-ready", "1");
    section.classList.add("examdash-collapsible");

    var header = document.createElement("div");
    header.className = "examdash-collapsible-head";

    var titleWrap = document.createElement("div");
    titleWrap.className = "examdash-collapsible-title";
    title.parentNode.removeChild(title);
    titleWrap.appendChild(title);

    var toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "examdash-btn-muted examdash-collapsible-toggle";

    header.appendChild(titleWrap);
    header.appendChild(toggle);
    section.insertBefore(header, section.firstChild);

    var body = document.createElement("div");
    body.className = "examdash-collapsible-body";
    while(header.nextSibling){
        body.appendChild(header.nextSibling);
    }
    section.appendChild(body);

    var setOpen = function(shouldOpen){
        var group = section.getAttribute("data-collapsible-group");
        if(shouldOpen && group){
            var groupSections = document.querySelectorAll("[data-collapsible-group='" + group + "']");
            for(var i = 0; i < groupSections.length; i++){
                if(groupSections[i] !== section){
                    examdashSetCollapsibleState(groupSections[i], false);
                }
            }
        }
        examdashSetCollapsibleState(section, shouldOpen);
    };

    header.addEventListener("click", function(e){
        if(e.target.closest("button")){
            return;
        }
        setOpen(!section.classList.contains("is-open"));
    });
    toggle.addEventListener("click", function(e){
        e.stopPropagation();
        setOpen(!section.classList.contains("is-open"));
    });

    examdashSetCollapsibleState(section, false);
}

function examdashFilterBoardSafeRows(){
    var input = document.getElementById("examdash-board-safe-search");
    var tableBody = document.getElementById("examdash-board-safe-body");
    var emptyRow = document.getElementById("examdash-board-safe-empty");
    var countNode = document.getElementById("examdash-board-safe-count");
    if(!tableBody){
        return;
    }

    var query = input ? String(input.value || "").toLowerCase().trim() : "";
    var rows = tableBody.querySelectorAll("tr[data-board-safe-label]");
    var visibleCount = 0;

    for(var i = 0; i < rows.length; i++){
        var label = String(rows[i].getAttribute("data-board-safe-label") || "").toLowerCase();
        var shouldShow = (query === "" || label.indexOf(query) !== -1);
        rows[i].style.display = shouldShow ? "" : "none";
        if(shouldShow){
            visibleCount++;
        }
    }

    if(emptyRow){
        emptyRow.style.display = visibleCount === 0 ? "" : "none";
    }
    if(countNode){
        countNode.textContent = visibleCount + " student(s)";
    }
}

function examdashClearBoardSafeRows(){
    var input = document.getElementById("examdash-board-safe-search");
    if(input){
        input.value = "";
    }
    examdashFilterBoardSafeRows();
    if(input){
        input.focus();
    }
}

window.addEventListener("DOMContentLoaded", function(){
    var collapsibleIds = [
        "examdash-results-listing-print",
        "examdash-board-safe-print",
        "examdash-completeness-print",
        "examdash-analytics-alerts-print",
        "examdash-term-trend-print"
    ];
    for(var i = 0; i < collapsibleIds.length; i++){
        var section = document.getElementById(collapsibleIds[i]);
        if(section){
            section.setAttribute("data-collapsible-group", "secondary-analysis");
        }
        examdashMakeCollapsible(collapsibleIds[i]);
    }

    var boardSafeSearch = document.getElementById("examdash-board-safe-search");
    if(boardSafeSearch){
        boardSafeSearch.addEventListener("input", examdashFilterBoardSafeRows);
        boardSafeSearch.addEventListener("keydown", function(e){
            if(e.key === "Enter"){
                e.preventDefault();
                examdashFilterBoardSafeRows();
            }
        });
        examdashFilterBoardSafeRows();
    }
});
</script>
</head>
<body>
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="examdash-wrap">
    <div class="examdash-card">
        <h2 class="examdash-title"><i class="fa fa-line-chart"></i> Internal Exams Analysis</h2>
        <p class="examdash-note">Analyze class score and exam score performance with a WAEC-style dashboard for internal results.</p>
        <div class="examdash-badges">
            <span class="examdash-badge"><i class="fa fa-filter"></i> Filter by Batch, Academic Year, Class, Semester, Subject, Gender, and Grade</span>
            <span class="examdash-badge"><i class="fa fa-search"></i> Search an individual student</span>
            <span class="examdash-badge"><i class="fa fa-table"></i> Compare subjects, students, and grade bands</span>
            <?php if($isTeacher){ ?>
            <span class="examdash-badge"><i class="fa fa-user"></i> Teacher view: only subjects assigned to you are shown</span>
            <?php } ?>
        </div>
        <?php
        if(isset($_SESSION['Message']) && trim((string)$_SESSION['Message']) !== ""){
            echo "<div style='margin-top:12px;'>".$_SESSION['Message']."</div>";
            $_SESSION['Message'] = "";
        }
        ?>
    </div>

    <div class="examdash-card">
        <form method="get" action="<?php echo examdash_esc($currentPage); ?>" class="examdash-toolbar">
            <select name="batchid">
                <option value="">Select Batch</option>
                <?php foreach($batchOptions as $opt){ $selected = ($filterBatch === $opt["batchid"]) ? "selected" : ""; echo "<option value='".examdash_esc($opt["batchid"])."' $selected>".examdash_esc($opt["batch"])."</option>"; } ?>
            </select>
            <select name="academic_year">
                <option value="">Select Academic Year</option>
                <?php foreach($yearOptions as $opt){ $selected = ($filterAcademicYear === $opt["academicyear"]) ? "selected" : ""; echo "<option value='".examdash_esc($opt["academicyear"])."' $selected>".examdash_esc($opt["academicyear"])."</option>"; } ?>
            </select>
            <select name="class_id">
                <option value="">Select Class</option>
                <?php foreach($classOptions as $opt){ $selected = ($filterClass === $opt["class_entryid"]) ? "selected" : ""; echo "<option value='".examdash_esc($opt["class_entryid"])."' $selected>".examdash_esc($opt["class_name"])."</option>"; } ?>
            </select>
            <select name="term_id">
                <option value="">Select Semester</option>
                <?php foreach($termOptions as $opt){ $selected = ($filterTerm === $opt["termname"]) ? "selected" : ""; echo "<option value='".examdash_esc($opt["termname"])."' $selected>Semester ".examdash_esc($opt["termname"])."</option>"; } ?>
            </select>
            <select name="focus_subject">
                <option value="">All Subjects</option>
                <?php foreach($subjectOptions as $opt){ $selected = ($focusSubject === $opt["subjectid"]) ? "selected" : ""; echo "<option value='".examdash_esc($opt["subjectid"])."' $selected>".examdash_esc($opt["subject"])."</option>"; } ?>
            </select>
            <select name="focus_gender">
                <option value="">All Genders</option>
                <option value="Male" <?php echo ($focusGender === "Male") ? "selected" : ""; ?>>Male</option>
                <option value="Female" <?php echo ($focusGender === "Female") ? "selected" : ""; ?>>Female</option>
            </select>
            <select name="focus_grade">
                <option value="">All Grades</option>
                <?php foreach($gradeList as $grade){ $selected = ($focusGrade === $grade) ? "selected" : ""; echo "<option value='".examdash_esc($grade)."' $selected>".examdash_esc($grade)."</option>"; } ?>
            </select>
            <input type="text" name="student_query" value="<?php echo examdash_esc($studentQuery); ?>" placeholder="Search student by name or ID">
            <button class="examdash-btn" type="submit"><i class="fa fa-search"></i> Apply</button>
            <a class="examdash-btn-muted" href="<?php echo examdash_esc($currentPage); ?>"><i class="fa fa-refresh"></i> Reset</a>
        </form>
        <div class="examdash-badges">
            <a class="examdash-btn-muted" href="examanalysis-subject.php"><i class="fa fa-book"></i> Subject Report</a>
            <a class="examdash-btn-muted" href="examanalysis-rank.php"><i class="fa fa-trophy"></i> Rank Report</a>
            <?php if($scopeReady){ ?>
            <a class="examdash-btn-muted" href="<?php echo examdash_esc(examdash_url($currentPage, $queryDefaults, array("export" => "csv"))); ?>"><i class="fa fa-file-excel-o"></i> Download CSV</a>
            <button type="button" class="examdash-btn-muted" onclick="examdashPrintSection('examdash-print-area', 'Internal Exams Analysis')"><i class="fa fa-print"></i> Print Dashboard</button>
            <button type="button" class="examdash-btn-muted" onclick="examdashPrintSection('examdash-results-listing-print', 'Internal Results Listing')"><i class="fa fa-list"></i> Print Results Listing</button>
            <button type="button" class="examdash-btn-muted" onclick="examdashPrintSection('examdash-board-safe-print', 'Board-Safe Results Sheet')"><i class="fa fa-bullhorn"></i> Print Board-Safe Sheet</button>
            <button type="button" class="examdash-btn-muted" onclick="examdashPrintSection('examdash-completeness-print', 'Score Completeness Checker')"><i class="fa fa-check-square-o"></i> Print Completeness Checker</button>
            <button type="button" class="examdash-btn-muted" onclick="examdashPrintSection('examdash-analytics-alerts-print', 'Analytics Alerts')"><i class="fa fa-bell-o"></i> Print Analytics Alerts</button>
            <button type="button" class="examdash-btn-muted" onclick="examdashPrintSection('examdash-term-trend-print', 'Cross-Term Performance Tracking')"><i class="fa fa-line-chart"></i> Print Cross-Term Tracking</button>
            <?php } ?>
        </div>
    </div>

    <?php if(!$scopeReady){ ?>
    <div class="examdash-card">
        <div class="examdash-empty">Select a batch, academic year, class, and semester to load internal exam analysis.</div>
        <p class="examdash-small" style="margin-top:12px;">Use the filters above to open the batch, academic year, class, and semester you want to review.</p>
    </div>
    <?php } else { ?>
    <div id="examdash-print-area">
        <div class="examdash-card">
            <div class="examdash-badges" style="margin-top:0;">
                <span class="examdash-badge"><i class="fa fa-folder-open"></i> <?php echo examdash_esc($scopeTitle); ?></span>
                <?php
                $focusSubjectName = "";
                foreach($subjectOptions as $subjectOpt){
                    if($subjectOpt["subjectid"] === $focusSubject){
                        $focusSubjectName = $subjectOpt["subject"];
                        break;
                    }
                }
                ?>
                <?php if($focusSubject !== ""){ ?><span class="examdash-badge"><i class="fa fa-book"></i> <?php echo examdash_esc($focusSubjectName !== "" ? $focusSubjectName : $focusSubject); ?></span><?php } ?>
                <?php if($focusGender !== ""){ ?><span class="examdash-badge"><i class="fa fa-users"></i> <?php echo examdash_esc($focusGender); ?></span><?php } ?>
                <?php if($focusGrade !== ""){ ?><span class="examdash-badge"><i class="fa fa-tag"></i> Grade <?php echo examdash_esc($focusGrade); ?></span><?php } ?>
            </div>

            <?php if($scopeMessage !== ""){ ?>
            <div class="examdash-alert"><?php echo examdash_esc($scopeMessage); ?></div>
            <?php } else { ?>
            <div class="examdash-stats" style="margin-top:14px;">
                <div class="examdash-stat"><h4>Results In Scope</h4><strong><?php echo (int)$summary["total_results"]; ?></strong></div>
                <div class="examdash-stat"><h4>Registered Students</h4><strong><?php echo (int)$expectedStudentCount; ?></strong></div>
                <div class="examdash-stat"><h4>Students With Scores</h4><strong><?php echo (int)$scoredStudentCount; ?></strong></div>
                <div class="examdash-stat"><h4>Subjects In Scope</h4><strong><?php echo (int)$summary["total_subjects"]; ?></strong></div>
                <div class="examdash-stat"><h4>Average Score %</h4><strong><?php echo examdash_esc($summary["avg_percent"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Pass Rate</h4><strong><?php echo examdash_esc($summary["pass_pct"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Strong Grades A1-B3</h4><strong><?php echo examdash_esc($summary["strong_pct"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Average Point</h4><strong><?php echo examdash_esc($summary["avg_point"]); ?></strong></div>
                <div class="examdash-stat"><h4>Pass Count</h4><strong><?php echo (int)$summary["pass_count"]; ?></strong></div>
                <div class="examdash-stat"><h4>Avg Components / Result</h4><strong><?php echo examdash_esc($summary["avg_components"]); ?></strong></div>
            </div>
            <?php if(count($subjectPerformanceRows) > 0){ ?>
            <div class="examdash-alert">
                Best subject in this scope: <strong><?php echo examdash_esc($subjectPerformanceRows[0]["subject"]); ?></strong>
                with an average of <strong><?php echo examdash_esc($subjectPerformanceRows[0]["avg_percent"]); ?>%</strong>.
            </div>
            <?php } ?>
            <p class="examdash-small" style="margin-top:12px;">Subject performance, gender comparison, and ranking use the current batch, academic year, class, semester, and optional subject or gender scope. The grade filter is applied to the summary cards.</p>
            <?php } ?>
        </div>

        <div class="examdash-card" id="examdash-results-listing-print">
            <h3 style="margin-top:0;">Internal Results Listing</h3>
            <p class="examdash-small">This mirrors the WAEC Results Listing format using the full selected batch, academic year, class, and semester scope. It uses your internal <strong>Student ID</strong> in place of WAEC index number and ignores the optional subject, gender, and grade filters so the listing stays complete.</p>
            <div class="examdash-alert">
                Total candidates in listing: <strong><?php echo count($resultsListingRows); ?></strong>.
                Students with at least one scored subject: <strong><?php echo examdash_unique_count($allResultRows, "userid"); ?></strong>.
            </div>
            <table class="examdash-table" style="margin-top:14px;">
                <thead><tr><th>Student ID</th><th>Name</th><th>Gender</th><th>Results</th></tr></thead>
                <tbody>
                    <?php
                    if(count($resultsListingRows) > 0){
                        foreach($resultsListingRows as $listingRow){
                            echo "<tr>";
                            echo "<td>".examdash_esc($listingRow["userid"])."</td>";
                            echo "<td>".examdash_esc($listingRow["student_name"])."</td>";
                            echo "<td>".examdash_esc($listingRow["gender"])."</td>";
                            echo "<td>".examdash_esc($listingRow["results_text"])."<br><span class='examdash-small'>".(int)$listingRow["results_count"]." subject(s)</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No internal results listing is available for this scope yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="examdash-card" id="examdash-board-safe-print">
            <h3 style="margin-top:0;">Board-Safe Results Sheet</h3>
            <p class="examdash-small">This sheet is designed for notice-board publishing. It hides gender, raw score breakdowns, and the student drill-down details, while keeping only position, student ID, student name, overall average, overall grade, and subject-grade summary.</p>
            <div class="examdash-alert">
                Board-safe scope always uses the full selected batch, academic year, class, and semester.
                Optional dashboard filters are ignored so you do not accidentally publish a partial list.
            </div>
            <div class="examdash-board-safe-search" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:14px;">
                <input type="text" id="examdash-board-safe-search" placeholder="Search by student name, ID, grade, result, or position" style="flex:1 1 280px;min-height:44px;padding:10px 14px;border-radius:12px;border:1px solid #d7e2ee;">
                <button type="button" class="examdash-btn" onclick="examdashFilterBoardSafeRows()"><i class="fa fa-search"></i> Search</button>
                <button type="button" class="examdash-btn-muted" onclick="examdashClearBoardSafeRows()"><i class="fa fa-refresh"></i> Reset</button>
                <span class="examdash-small" id="examdash-board-safe-count"><?php echo count($boardPublishRows); ?> student(s)</span>
            </div>
            <table class="examdash-table" style="margin-top:14px;">
                <thead><tr><th>Pos</th><th>Student ID</th><th>Name</th><th>Average %</th><th>Overall Grade</th><th>Results</th></tr></thead>
                <tbody id="examdash-board-safe-body">
                    <?php
                    if(count($boardPublishRows) > 0){
                        foreach($boardPublishRows as $boardRow){
                            $averageText = ($boardRow["avg_percent"] === "") ? "-" : examdash_esc($boardRow["avg_percent"])."%";
                            $boardSafeLabel = trim(
                                $boardRow["position"]." ".
                                $boardRow["userid"]." ".
                                $boardRow["student_name"]." ".
                                $boardRow["overall_grade"]." ".
                                $boardRow["results_text"]." ".
                                $boardRow["avg_percent"]
                            );
                            echo "<tr data-board-safe-label='".examdash_esc($boardSafeLabel)."'>";
                            echo "<td>".examdash_esc($boardRow["position"])."</td>";
                            echo "<td>".examdash_esc($boardRow["userid"])."</td>";
                            echo "<td>".examdash_esc($boardRow["student_name"])."</td>";
                            echo "<td>".$averageText."</td>";
                            echo "<td>".examdash_esc($boardRow["overall_grade"])."</td>";
                            echo "<td>".examdash_esc($boardRow["results_text"])."<br><span class='examdash-small'>".(int)$boardRow["pass_count"]." pass(es) out of ".(int)$boardRow["subjects_count"]." subject(s)</span></td>";
                            echo "</tr>";
                        }
                        echo "<tr id='examdash-board-safe-empty' style='display:none;'><td colspan='6'>No student matched your board-safe search.</td></tr>";
                    } else {
                        echo "<tr><td colspan='6'>No board-safe results are available for this scope yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="examdash-card" id="examdash-completeness-print">
            <h3 style="margin-top:0;">Score Completeness Checker</h3>
            <p class="examdash-small">This audit checks whether each expected student-subject entry has both required mark components: <strong>Class Score</strong> and <strong>Exam Score</strong>. It respects the current batch, academic year, class, semester, and optional subject/gender scope, but it ignores the grade filter.</p>
            <div class="examdash-stats" style="margin-top:14px;">
                <div class="examdash-stat"><h4>Expected Entries</h4><strong><?php echo (int)$completenessSummary["expected_rows"]; ?></strong></div>
                <div class="examdash-stat"><h4>Complete</h4><strong><?php echo (int)$completenessSummary["complete_rows"]; ?></strong></div>
                <div class="examdash-stat"><h4>Missing Class</h4><strong><?php echo (int)$completenessSummary["missing_class_rows"]; ?></strong></div>
                <div class="examdash-stat"><h4>Missing Exam</h4><strong><?php echo (int)$completenessSummary["missing_exam_rows"]; ?></strong></div>
                <div class="examdash-stat"><h4>Missing Both</h4><strong><?php echo (int)$completenessSummary["missing_both_rows"]; ?></strong></div>
                <div class="examdash-stat"><h4>Duplicate Rows</h4><strong><?php echo (int)$completenessSummary["duplicate_rows"]; ?></strong></div>
                <div class="examdash-stat"><h4>Completion %</h4><strong><?php echo examdash_esc($completenessSummary["completion_pct"]); ?>%</strong></div>
            </div>
            <?php if((int)$completenessSummary["issue_rows"] > 0 || (int)$completenessSummary["duplicate_rows"] > 0){ ?>
            <div class="examdash-alert">
                Incomplete score entries were detected. Use the subject summary and issue list below to see whether the gap is a missing class score, missing exam score, no score at all, or a duplicate entry.
            </div>
            <?php } else { ?>
            <div class="examdash-alert">
                All expected entries in this scope currently have one class score and one exam score.
            </div>
            <?php } ?>
            <div class="examdash-grid-even" style="margin-top:16px;">
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Subject Completeness</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Subject</th><th>Expected</th><th>Complete</th><th>Missing Class</th><th>Missing Exam</th><th>Missing Both</th><th>Duplicates</th><th>Completion %</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($completenessSubjectRows) > 0){
                                foreach($completenessSubjectRows as $subjectRow){
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($subjectRow["subject"])."</td>";
                                    echo "<td>".(int)$subjectRow["expected_rows"]."</td>";
                                    echo "<td>".(int)$subjectRow["complete_rows"]."</td>";
                                    echo "<td>".(int)$subjectRow["missing_class_rows"]."</td>";
                                    echo "<td>".(int)$subjectRow["missing_exam_rows"]."</td>";
                                    echo "<td>".(int)$subjectRow["missing_both_rows"]."</td>";
                                    echo "<td>".(int)$subjectRow["duplicate_rows"]."</td>";
                                    echo "<td>".examdash_esc($subjectRow["completion_pct"])."%</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8'>No completeness data found for this scope.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Issue List</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Student</th><th>Gender</th><th>Subject</th><th>Class Rows</th><th>Exam Rows</th><th>Issue</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($completenessIssueRows) > 0){
                                foreach(array_slice($completenessIssueRows, 0, 120) as $issueRow){
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($issueRow["student_name"])."<br><span class='examdash-small'>".examdash_esc($issueRow["userid"])."</span></td>";
                                    echo "<td>".examdash_esc($issueRow["gender"])."</td>";
                                    echo "<td>".examdash_esc($issueRow["subject"])."</td>";
                                    echo "<td>".(int)$issueRow["class_score_rows"]."</td>";
                                    echo "<td>".(int)$issueRow["exam_score_rows"]."</td>";
                                    echo "<td>".examdash_esc($issueRow["issue_label"])."</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No completeness issues found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if(count($completenessIssueRows) > 120){ ?>
                    <p class="examdash-small" style="margin-top:10px;">Showing the first 120 issue rows in the dashboard view.</p>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="examdash-card" id="examdash-analytics-alerts-print">
            <h3 style="margin-top:0;">Analytics Alerts</h3>
            <p class="examdash-small">These alerts combine current score completeness, subject coverage, and previous-semester trend checks so you can spot urgent issues quickly without changing the underlying scoring workflow.</p>
            <div class="examdash-stats" style="margin-top:14px;">
                <div class="examdash-stat"><h4>Critical</h4><strong><?php echo (int)$analyticsAlertCounts["Critical"]; ?></strong></div>
                <div class="examdash-stat"><h4>Warnings</h4><strong><?php echo (int)$analyticsAlertCounts["Warning"]; ?></strong></div>
                <div class="examdash-stat"><h4>Info</h4><strong><?php echo (int)$analyticsAlertCounts["Info"]; ?></strong></div>
                <div class="examdash-stat"><h4>Positive</h4><strong><?php echo (int)$analyticsAlertCounts["Success"]; ?></strong></div>
                <div class="examdash-stat"><h4>Total Alerts</h4><strong><?php echo count($analyticsAlerts); ?></strong></div>
            </div>
            <?php if(count($analyticsAlerts) > 0){ ?>
            <div class="examdash-alert">
                Alerts are generated from the current selected scope. Subject and gender filters are respected, while grade filter is ignored for trend-based alerts so comparisons stay reliable.
            </div>
            <div class="examdash-grid-even" style="margin-top:16px;">
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Alert Feed</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Severity</th><th>Area</th><th>Alert</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php
                            foreach(array_slice($analyticsAlerts, 0, 14) as $alertRow){
                                $severityClass = "examdash-pill-info";
                                if($alertRow["severity"] === "Critical"){
                                    $severityClass = "examdash-pill-critical";
                                } elseif($alertRow["severity"] === "Warning"){
                                    $severityClass = "examdash-pill-warning";
                                } elseif($alertRow["severity"] === "Success"){
                                    $severityClass = "examdash-pill-success";
                                }
                                echo "<tr>";
                                echo "<td><span class='examdash-pill ".$severityClass."'>".examdash_esc($alertRow["severity"])."</span></td>";
                                echo "<td>".examdash_esc($alertRow["area"])."</td>";
                                echo "<td><strong>".examdash_esc($alertRow["title"])."</strong><br><span class='examdash-small'>".examdash_esc($alertRow["detail"])."</span></td>";
                                echo "<td>".examdash_esc($alertRow["action"])."</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if(count($analyticsAlerts) > 14){ ?>
                    <p class="examdash-small" style="margin-top:10px;">Showing the first 14 alerts in the dashboard view.</p>
                    <?php } ?>
                </div>
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Subject Watchlist</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Subject</th><th>Current Pass %</th><th>Pass Change</th><th>Current Avg %</th><th>Avg Change</th><th>Missing Students</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($subjectWatchRows) > 0){
                                foreach(array_slice($subjectWatchRows, 0, 10) as $watchRow){
                                    $passClass = "examdash-trend-flat";
                                    if($watchRow["pass_change"] !== ""){
                                        if((float)$watchRow["pass_change"] > 0){
                                            $passClass = "examdash-trend-up";
                                        } elseif((float)$watchRow["pass_change"] < 0){
                                            $passClass = "examdash-trend-down";
                                        }
                                    }
                                    $avgClass = "examdash-trend-flat";
                                    if($watchRow["avg_change"] !== ""){
                                        if((float)$watchRow["avg_change"] > 0){
                                            $avgClass = "examdash-trend-up";
                                        } elseif((float)$watchRow["avg_change"] < 0){
                                            $avgClass = "examdash-trend-down";
                                        }
                                    }
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($watchRow["subject"])."</td>";
                                    echo "<td>".($watchRow["current_pass_pct"] !== "" ? examdash_esc($watchRow["current_pass_pct"])."%" : "N/A")."</td>";
                                    echo "<td><span class='".$passClass."'>".examdash_esc(examdash_delta_text($watchRow["pass_change"]))."</span></td>";
                                    echo "<td>".($watchRow["current_avg_percent"] !== "" ? examdash_esc($watchRow["current_avg_percent"])."%" : "N/A")."</td>";
                                    echo "<td><span class='".$avgClass."'>".examdash_esc(examdash_delta_text($watchRow["avg_change"]))."</span></td>";
                                    echo "<td>".(int)$watchRow["current_missing_students"]."</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No subject watchlist data found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } else { ?>
            <div class="examdash-alert">
                No alerts were triggered in this scope right now. That usually means current score coverage and recent trends look stable.
            </div>
            <?php } ?>
        </div>

        <div class="examdash-card" id="examdash-term-trend-print">
            <h3 style="margin-top:0;">Cross-Term Performance Tracking</h3>
            <p class="examdash-small">This view follows the same batch, academic year, and class across all available semesters. It respects the optional subject and gender filters, but ignores the grade filter so semester-to-semester comparisons stay stable.</p>
            <?php if(count($termTrendRows) > 0){ ?>
            <div class="examdash-stats" style="margin-top:14px;">
                <div class="examdash-stat"><h4>Semesters Tracked</h4><strong><?php echo (int)$termTrendOverview["tracked_terms"]; ?></strong></div>
                <div class="examdash-stat"><h4>Current Avg %</h4><strong><?php echo $termTrendOverview["current_avg_percent"] !== "" ? examdash_esc($termTrendOverview["current_avg_percent"])."%" : "N/A"; ?></strong></div>
                <div class="examdash-stat"><h4>Previous Avg %</h4><strong><?php echo $termTrendOverview["previous_avg_percent"] !== "" ? examdash_esc($termTrendOverview["previous_avg_percent"])."%" : "N/A"; ?></strong></div>
                <div class="examdash-stat"><h4>Avg Change</h4><strong><?php echo examdash_esc(examdash_delta_text($termTrendOverview["avg_change"])); ?></strong></div>
                <div class="examdash-stat"><h4>Current Pass %</h4><strong><?php echo $termTrendOverview["current_pass_pct"] !== "" ? examdash_esc($termTrendOverview["current_pass_pct"])."%" : "N/A"; ?></strong></div>
                <div class="examdash-stat"><h4>Pass Change</h4><strong><?php echo examdash_esc(examdash_delta_text($termTrendOverview["pass_change"])); ?></strong></div>
                <div class="examdash-stat"><h4>Students With Scores</h4><strong><?php echo (int)$termTrendOverview["current_students"]; ?></strong></div>
                <div class="examdash-stat"><h4>Improved</h4><strong><?php echo (int)$termTrendOverview["improved_count"]; ?></strong></div>
                <div class="examdash-stat"><h4>Declined</h4><strong><?php echo (int)$termTrendOverview["declined_count"]; ?></strong></div>
                <div class="examdash-stat"><h4>New This Semester</h4><strong><?php echo (int)$termTrendOverview["new_count"]; ?></strong></div>
            </div>
            <?php if($termPreviousName !== ""){ ?>
            <div class="examdash-alert">
                Current semester <strong><?php echo examdash_esc($filterTerm); ?></strong> is being compared with semester
                <strong><?php echo examdash_esc($termPreviousName); ?></strong>.
            </div>
            <?php } else { ?>
            <div class="examdash-alert">
                There is no earlier semester with scores in this batch/class scope yet, so the page is showing the semester trail only.
            </div>
            <?php } ?>
            <table class="examdash-table" style="margin-top:14px;">
                <thead><tr><th>Semester</th><th>Registered</th><th>Students With Scores</th><th>Results</th><th>Avg %</th><th>Pass %</th><th>Strong %</th><th>Coverage</th><th>Avg Change</th><th>Pass Change</th></tr></thead>
                <tbody>
                    <?php
                    foreach($termTrendRows as $termRow){
                        $isSelectedTerm = ((string)$termRow["termname"] === (string)$filterTerm);
                        $avgChangeClass = "examdash-trend-flat";
                        if($termRow["avg_change"] !== ""){
                            if((float)$termRow["avg_change"] > 0){
                                $avgChangeClass = "examdash-trend-up";
                            } elseif((float)$termRow["avg_change"] < 0){
                                $avgChangeClass = "examdash-trend-down";
                            }
                        }
                        $passChangeClass = "examdash-trend-flat";
                        if($termRow["pass_change"] !== ""){
                            if((float)$termRow["pass_change"] > 0){
                                $passChangeClass = "examdash-trend-up";
                            } elseif((float)$termRow["pass_change"] < 0){
                                $passChangeClass = "examdash-trend-down";
                            }
                        }
                        echo "<tr>";
                        echo "<td>".($isSelectedTerm ? "<strong>Semester ".examdash_esc($termRow["termname"])."</strong>" : "Semester ".examdash_esc($termRow["termname"]))."</td>";
                        echo "<td>".(int)$termRow["registered_students"]."</td>";
                        echo "<td>".(int)$termRow["students_scored"]."</td>";
                        echo "<td>".(int)$termRow["results_count"]."</td>";
                        echo "<td>".($termRow["avg_percent"] !== "" ? examdash_esc($termRow["avg_percent"])."%" : "N/A")."</td>";
                        echo "<td>".($termRow["pass_pct"] !== "" ? examdash_esc($termRow["pass_pct"])."%" : "N/A")."</td>";
                        echo "<td>".($termRow["strong_pct"] !== "" ? examdash_esc($termRow["strong_pct"])."%" : "N/A")."</td>";
                        echo "<td>".($termRow["coverage_pct"] !== "" ? examdash_esc($termRow["coverage_pct"])."%" : "N/A")."</td>";
                        echo "<td><span class='".$avgChangeClass."'>".examdash_esc(examdash_delta_text($termRow["avg_change"]))."</span></td>";
                        echo "<td><span class='".$passChangeClass."'>".examdash_esc(examdash_delta_text($termRow["pass_change"]))."</span></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <?php if($termPreviousName !== "" && count($termMovementRows) > 0){ ?>
            <div class="examdash-grid-even" style="margin-top:16px;">
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Most Improved Students</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Student</th><th>Previous Avg</th><th>Current Avg</th><th>Change</th><th>Current Grade</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($termImprovedRows) > 0){
                                foreach(array_slice($termImprovedRows, 0, 10) as $movementRow){
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($movementRow["student_name"])."<br><span class='examdash-small'>".examdash_esc($movementRow["userid"])."</span></td>";
                                    echo "<td>".($movementRow["previous_avg_percent"] !== "" ? examdash_esc($movementRow["previous_avg_percent"])."%" : "N/A")."</td>";
                                    echo "<td>".examdash_esc($movementRow["current_avg_percent"])."%</td>";
                                    echo "<td><span class='examdash-trend-up'>".examdash_esc(examdash_delta_text($movementRow["avg_change"]))."</span></td>";
                                    echo "<td>".examdash_esc($movementRow["current_grade"])."</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No student improvement records yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Students Needing Attention</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Student</th><th>Previous Avg</th><th>Current Avg</th><th>Change</th><th>Current Grade</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($termDeclinedRows) > 0){
                                foreach(array_slice($termDeclinedRows, 0, 10) as $movementRow){
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($movementRow["student_name"])."<br><span class='examdash-small'>".examdash_esc($movementRow["userid"])."</span></td>";
                                    echo "<td>".($movementRow["previous_avg_percent"] !== "" ? examdash_esc($movementRow["previous_avg_percent"])."%" : "N/A")."</td>";
                                    echo "<td>".examdash_esc($movementRow["current_avg_percent"])."%</td>";
                                    echo "<td><span class='examdash-trend-down'>".examdash_esc(examdash_delta_text($movementRow["avg_change"]))."</span></td>";
                                    echo "<td>".examdash_esc($movementRow["current_grade"])."</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No declining student records for this comparison.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>

            <?php if($selectedStudent !== null && count($selectedStudentTrendRows) > 0){ ?>
            <div class="examdash-card" style="margin-top:16px;margin-bottom:0;">
                <h4 style="margin-top:0;">Selected Student Semester Trail</h4>
                <p class="examdash-small"><?php echo examdash_esc($selectedStudent["student_name"]); ?> (<?php echo examdash_esc($selectedStudent["userid"]); ?>)</p>
                <table class="examdash-table">
                    <thead><tr><th>Semester</th><th>Subjects</th><th>Average %</th><th>Pass %</th><th>Strong %</th><th>Overall Grade</th></tr></thead>
                    <tbody>
                        <?php
                        foreach($selectedStudentTrendRows as $trendRow){
                            echo "<tr>";
                            echo "<td>Semester ".examdash_esc($trendRow["termname"])."</td>";
                            echo "<td>".(int)$trendRow["subjects_count"]."</td>";
                            echo "<td>".($trendRow["avg_percent"] !== "" ? examdash_esc($trendRow["avg_percent"])."%" : "N/A")."</td>";
                            echo "<td>".($trendRow["pass_pct"] !== "" ? examdash_esc($trendRow["pass_pct"])."%" : "N/A")."</td>";
                            echo "<td>".($trendRow["strong_pct"] !== "" ? examdash_esc($trendRow["strong_pct"])."%" : "N/A")."</td>";
                            echo "<td>".examdash_esc($trendRow["overall_grade"])."</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
            <?php } else { ?>
            <div class="examdash-empty" style="margin-top:14px;">No semester trend data was found for this batch and class yet.</div>
            <?php } ?>
        </div>

        <?php if($scopeMessage === ""){ ?>
        <div class="examdash-grid">
            <div class="examdash-card">
                <h3 style="margin-top:0;">Grade Distribution</h3>
                <div class="examdash-distribution">
                    <?php
                    $distributionTotal = array_sum($gradeDistribution);
                    foreach($gradeList as $grade){
                        $count = isset($gradeDistribution[$grade]) ? (int)$gradeDistribution[$grade] : 0;
                        $pct = $distributionTotal > 0 ? round(($count * 100) / $distributionTotal, 2) : 0;
                        $gradeUrl = examdash_url($currentPage, $queryDefaults, array("focus_grade" => $grade));
                    ?>
                    <div class="examdash-distribution-row">
                        <div><a class="examdash-link" href="<?php echo examdash_esc($gradeUrl); ?>"><?php echo examdash_esc($grade); ?></a></div>
                        <div class="examdash-progress"><div class="examdash-fill" style="width:<?php echo max(0, min(100, $pct)); ?>%;"></div></div>
                        <div><?php echo $count; ?></div>
                        <div class="examdash-small"><?php echo examdash_esc($pct); ?>%</div>
                    </div>
                    <?php } ?>
                </div>
                <?php if($focusGrade !== ""){ ?>
                <div style="margin-top:12px;">
                    <a class="examdash-btn-muted" href="<?php echo examdash_esc(examdash_url($currentPage, $queryDefaults, array("focus_grade" => null))); ?>"><i class="fa fa-refresh"></i> Reset Grade Filter</a>
                </div>
                <?php } ?>
            </div>

            <div class="examdash-card">
                <h3 style="margin-top:0;">Subject League</h3>
                <table class="examdash-table">
                    <thead><tr><th>Subject</th><th>Avg %</th><th>Pass %</th><th>Coverage</th></tr></thead>
                    <tbody>
                        <?php
                        if(count($subjectPerformanceRows) > 0){
                            foreach(array_slice($subjectPerformanceRows, 0, 8) as $subjectRow){
                                $subjectUrl = examdash_url($currentPage, $queryDefaults, array("focus_subject" => $subjectRow["subjectid"], "focus_grade" => null));
                                echo "<tr>";
                                echo "<td><a class='examdash-link' href='".examdash_esc($subjectUrl)."'>".examdash_esc($subjectRow["subject"])."</a></td>";
                                echo "<td>".examdash_esc($subjectRow["avg_percent"])."%</td>";
                                echo "<td>".examdash_esc($subjectRow["pass_pct"])."%</td>";
                                echo "<td>".(int)$subjectRow["students_count"]."/".(int)$expectedStudentCount."</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4'>No subject performance data found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="examdash-card">
            <h3 style="margin-top:0;">Subject Performance</h3>
            <table class="examdash-table">
                <thead><tr><th>Subject</th><th>Results</th><th>Students</th><th>Avg %</th><th>Pass %</th><th>Strong %</th><th>Top Student</th><th>Coverage</th></tr></thead>
                <tbody>
                    <?php
                    if(count($subjectPerformanceRows) > 0){
                        foreach($subjectPerformanceRows as $subjectRow){
                            echo "<tr>";
                            echo "<td>".examdash_esc($subjectRow["subject"])."</td>";
                            echo "<td>".(int)$subjectRow["results_count"]."</td>";
                            echo "<td>".(int)$subjectRow["students_count"]."</td>";
                            echo "<td>".examdash_esc($subjectRow["avg_percent"])."%</td>";
                            echo "<td>".examdash_esc($subjectRow["pass_pct"])."%</td>";
                            echo "<td>".examdash_esc($subjectRow["strong_pct"])."%</td>";
                            echo "<td>".examdash_esc($subjectRow["top_student"])."<br><span class='examdash-small'>".examdash_esc($subjectRow["top_percent"])."%</span></td>";
                            echo "<td>".examdash_esc($subjectRow["coverage_pct"])."% <span class='examdash-small'>(".(int)$subjectRow["missing_students"]." missing)</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No subject performance data found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="examdash-card">
            <h3 style="margin-top:0;">Gender Analysis</h3>
            <?php
            $genderGap = round((float)$genderPerformance["Male"]["pass_pct"] - (float)$genderPerformance["Female"]["pass_pct"], 2);
            $pointGap = round((float)$genderPerformance["Female"]["avg_point"] - (float)$genderPerformance["Male"]["avg_point"], 2);
            ?>
            <div class="examdash-stats">
                <div class="examdash-stat"><h4>Male Students</h4><strong><?php echo isset($registeredGenderCounts["Male"]) ? (int)$registeredGenderCounts["Male"] : 0; ?></strong></div>
                <div class="examdash-stat"><h4>Female Students</h4><strong><?php echo isset($registeredGenderCounts["Female"]) ? (int)$registeredGenderCounts["Female"] : 0; ?></strong></div>
                <div class="examdash-stat"><h4>Male Avg %</h4><strong><?php echo examdash_esc($genderPerformance["Male"]["avg_percent"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Female Avg %</h4><strong><?php echo examdash_esc($genderPerformance["Female"]["avg_percent"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Male Pass %</h4><strong><?php echo examdash_esc($genderPerformance["Male"]["pass_pct"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Female Pass %</h4><strong><?php echo examdash_esc($genderPerformance["Female"]["pass_pct"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Male Results</h4><strong><?php echo (int)$genderPerformance["Male"]["total_results"]; ?></strong></div>
                <div class="examdash-stat"><h4>Female Results</h4><strong><?php echo (int)$genderPerformance["Female"]["total_results"]; ?></strong></div>
                <div class="examdash-stat"><h4>Pass Gap (M-F)</h4><strong><?php echo examdash_esc($genderGap); ?>%</strong></div>
                <div class="examdash-stat"><h4>Avg Point Gap (M-F)</h4><strong><?php echo examdash_esc($pointGap); ?></strong></div>
            </div>
            <div class="examdash-grid-even" style="margin-top:16px;">
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Subject Pass Rate By Gender</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Subject</th><th>Male %</th><th>Female %</th><th>Gap</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($genderAnalysisRows) > 0){
                                foreach($genderAnalysisRows as $genderRow){
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($genderRow["subject"])."</td>";
                                    echo "<td>".($genderRow["Male_pass_pct"] !== "" ? examdash_esc($genderRow["Male_pass_pct"])."% <span class='examdash-small'>(".$genderRow["Male_results"].")</span>" : "N/A")."</td>";
                                    echo "<td>".($genderRow["Female_pass_pct"] !== "" ? examdash_esc($genderRow["Female_pass_pct"])."% <span class='examdash-small'>(".$genderRow["Female_results"].")</span>" : "N/A")."</td>";
                                    echo "<td>".($genderRow["gap"] !== "" ? examdash_esc($genderRow["gap"])."%" : "N/A")."</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4'>No gender comparison data yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="examdash-card" style="margin-bottom:0;">
                    <h4 style="margin-top:0;">Top Students</h4>
                    <table class="examdash-table">
                        <thead><tr><th>Pos</th><th>Student</th><th>Avg %</th><th>Pass %</th></tr></thead>
                        <tbody>
                            <?php
                            if(count($studentSummaries) > 0){
                                foreach(array_slice($studentSummaries, 0, 10) as $studentRow){
                                    $studentUrl = examdash_url($currentPage, $queryDefaults, array("student_query" => $studentRow["student_name"], "student_key" => $studentRow["userid"]));
                                    echo "<tr>";
                                    echo "<td>".examdash_esc($studentRow["position"])."</td>";
                                    echo "<td><a class='examdash-link' href='".examdash_esc($studentUrl)."'>".examdash_esc($studentRow["student_name"])."</a><br><span class='examdash-small'>".examdash_esc($studentRow["userid"])."</span></td>";
                                    echo "<td>".examdash_esc($studentRow["avg_percent"])."%</td>";
                                    echo "<td>".examdash_esc($studentRow["pass_pct"])."%</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4'>No ranked students found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="examdash-grid-even">
            <div class="examdash-card">
                <h3 style="margin-top:0;">Best Subjects</h3>
                <table class="examdash-table">
                    <thead><tr><th>Subject</th><th>Avg %</th><th>Pass %</th></tr></thead>
                    <tbody>
                        <?php
                        if(count($topSubjects) > 0){
                            foreach($topSubjects as $subjectRow){
                                echo "<tr>";
                                echo "<td>".examdash_esc($subjectRow["subject"])."</td>";
                                echo "<td>".examdash_esc($subjectRow["avg_percent"])."%</td>";
                                echo "<td>".examdash_esc($subjectRow["pass_pct"])."%</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3'>No subject data found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="examdash-card">
                <h3 style="margin-top:0;">Subjects Needing Attention</h3>
                <table class="examdash-table">
                    <thead><tr><th>Subject</th><th>Avg %</th><th>Missing Students</th></tr></thead>
                    <tbody>
                        <?php
                        if(count($bottomSubjects) > 0){
                            foreach($bottomSubjects as $subjectRow){
                                echo "<tr>";
                                echo "<td>".examdash_esc($subjectRow["subject"])."</td>";
                                echo "<td>".examdash_esc($subjectRow["avg_percent"])."%</td>";
                                echo "<td>".(int)$subjectRow["missing_students"]."</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3'>No subject data found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if($studentQuery !== "" || $selectedStudent !== null){ ?>
        <div class="examdash-card">
            <h3 style="margin-top:0;">Student Result Analysis</h3>
            <?php if(count($studentMatches) > 0){ ?>
            <table class="examdash-table">
                <thead><tr><th>Student</th><th>Gender</th><th>Avg %</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    foreach(array_slice($studentMatches, 0, 8) as $match){
                        $selectUrl = examdash_url($currentPage, $queryDefaults, array("student_key" => $match["userid"]));
                        echo "<tr>";
                        echo "<td>".examdash_esc($match["student_name"])."<br><span class='examdash-small'>".examdash_esc($match["userid"])."</span></td>";
                        echo "<td>".examdash_esc($match["gender"])."</td>";
                        echo "<td>".examdash_esc($match["avg_percent"])."%</td>";
                        echo "<td><a class='examdash-link' href='".examdash_esc($selectUrl)."'>Select</a></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php } elseif($studentQuery !== "") { ?>
            <div class="examdash-empty">No student matched "<?php echo examdash_esc($studentQuery); ?>".</div>
            <?php } ?>

            <?php if($selectedStudent !== null){ ?>
            <div class="examdash-stats" style="margin-top:16px;">
                <div class="examdash-stat"><h4>Student</h4><strong style="font-size:18px;"><?php echo examdash_esc($selectedStudent["student_name"]); ?></strong><span class="examdash-small"><?php echo examdash_esc($selectedStudent["userid"]); ?></span></div>
                <div class="examdash-stat"><h4>Gender</h4><strong><?php echo examdash_esc($selectedStudent["gender"]); ?></strong></div>
                <div class="examdash-stat"><h4>Total Subjects</h4><strong><?php echo (int)$selectedStudent["subjects_count"]; ?></strong></div>
                <div class="examdash-stat"><h4>Average %</h4><strong><?php echo examdash_esc($selectedStudent["avg_percent"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Average Point</h4><strong><?php echo examdash_esc($selectedStudent["avg_point"]); ?></strong></div>
                <div class="examdash-stat"><h4>Pass %</h4><strong><?php echo examdash_esc($selectedStudent["pass_pct"]); ?>%</strong></div>
                <div class="examdash-stat"><h4>Strong Grades</h4><strong><?php echo (int)$selectedStudent["strong_count"]; ?></strong></div>
                <div class="examdash-stat"><h4>Position</h4><strong><?php echo examdash_esc($selectedStudent["position"]); ?></strong></div>
            </div>
            <table class="examdash-table" style="margin-top:14px;">
                <thead><tr><th>Subject</th><th>Score</th><th>Total</th><th>%</th><th>Grade</th><th>Components</th></tr></thead>
                <tbody>
                    <?php
                    foreach($selectedStudent["results"] as $studentResult){
                        echo "<tr>";
                        echo "<td>".examdash_esc($studentResult["subject"])."</td>";
                        echo "<td>".examdash_esc($studentResult["total_mark"])."</td>";
                        echo "<td>".examdash_esc($studentResult["total_possible"])."</td>";
                        echo "<td>".examdash_esc($studentResult["percent"])."%</td>";
                        echo "<td>".examdash_esc($studentResult["grade"])."</td>";
                        echo "<td>".(int)$studentResult["component_count"]."</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php } ?>
        </div>
        <?php } ?>

        <?php } ?>
    </div>
    <?php } ?>
</div>
</body>
</html>

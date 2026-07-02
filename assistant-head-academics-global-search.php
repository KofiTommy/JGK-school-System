<?php
session_start();
include("dbstring.php");
include_once("user-management-utils.php");

header("Content-Type: text/html; charset=UTF-8");

function ags_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function ags_trim_name($row){
    return trim((string)($row['firstname'] ?? '')." ".(string)($row['othernames'] ?? '')." ".(string)($row['surname'] ?? ''));
}

function ags_feedback($title, $message, $icon = "fa-search"){
    return "<div class='desktop-search-feedback'><i class='fa ".$icon."'></i><div><strong>".ags_esc($title)."</strong><span>".ags_esc($message)."</span></div></div>";
}

function ags_normalize_search_text($value){
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function ags_search_tokens($query){
    $normalized = ags_normalize_search_text($query);
    if($normalized === ''){
        return array();
    }
    $parts = explode(' ', $normalized);
    $tokens = array();
    foreach($parts as $part){
        $part = trim((string)$part);
        if(strlen($part) < 2){
            continue;
        }
        $tokens[$part] = $part;
    }
    return array_values($tokens);
}

function ags_tool_search_score($tool, $normalizedQuery, $tokens){
    $label = ags_normalize_search_text($tool['label'] ?? '');
    $description = ags_normalize_search_text($tool['description'] ?? '');
    $keywords = ags_normalize_search_text($tool['keywords'] ?? '');
    $href = ags_normalize_search_text(str_replace(array('.php', '-', '_', '/'), ' ', (string)($tool['href'] ?? '')));
    $haystack = trim($label.' '.$description.' '.$keywords.' '.$href);

    if($normalizedQuery === '' || $haystack === ''){
        return 0;
    }

    $score = 0;
    if($label === $normalizedQuery){
        $score += 160;
    }
    if(strpos($label, $normalizedQuery) !== false){
        $score += 110;
    }
    if(strpos($keywords, $normalizedQuery) !== false){
        $score += 90;
    }
    if(strpos($haystack, $normalizedQuery) !== false){
        $score += 55;
    }

    $matchedTokens = 0;
    foreach($tokens as $token){
        if(strpos($label, $token) !== false){
            $score += 22;
            $matchedTokens++;
            continue;
        }
        if(strpos($keywords, $token) !== false){
            $score += 18;
            $matchedTokens++;
            continue;
        }
        if(strpos($description, $token) !== false || strpos($href, $token) !== false){
            $score += 12;
            $matchedTokens++;
            continue;
        }
        if(count($tokens) > 1){
            return 0;
        }
    }

    if(!empty($tokens) && $matchedTokens === count($tokens)){
        $score += count($tokens) > 1 ? 35 : 12;
    }

    return $score;
}

function ags_tool_catalog(){
    return array(
        array('module' => 'student_progression', 'href' => 'student-history.php', 'label' => 'Student Transcript', 'icon' => 'fa-history', 'description' => 'Open a student transcript view.', 'keywords' => 'student transcript results history'),
        array('module' => 'student_progression', 'href' => 'continuing-students.php', 'label' => 'Continuing Students', 'icon' => 'fa-users', 'description' => 'Review continuing student records by class and batch.', 'keywords' => 'continuing students promotion'),
        array('module' => 'student_progression', 'href' => 'promotion-center.php', 'label' => 'Promotion Center', 'icon' => 'fa-level-up', 'description' => 'Review student promotion and progression.', 'keywords' => 'promotion center progression'),
        array('module' => 'class_semester_registry', 'href' => 'view-class-registry.php', 'label' => 'View Class Registry', 'icon' => 'fa-folder-open', 'description' => 'Open active class registry records.', 'keywords' => 'class registry students classes'),
        array('module' => 'class_semester_registry', 'href' => 'term-registry.php', 'label' => 'Semester Registry', 'icon' => 'fa-plus', 'description' => 'Open semester registry tools.', 'keywords' => 'semester registry term registry'),
        array('module' => 'class_semester_registry', 'href' => 'group-term-registry.php', 'label' => 'Group Semester Registry', 'icon' => 'fa-users', 'description' => 'Register semester records for a group of students.', 'keywords' => 'group semester registry batch'),
        array('module' => 'subject_management', 'href' => 'subject-classification.php', 'label' => 'Subject Classification', 'icon' => 'fa-book', 'description' => 'Review subject classifications.', 'keywords' => 'subject classification'),
        array('module' => 'subject_management', 'href' => 'subject-assignment.php', 'label' => 'Subject Assignment', 'icon' => 'fa-plus', 'description' => 'Review subject assignment tools.', 'keywords' => 'subject assignment teachers'),
        array('module' => 'subject_management', 'href' => 'view-all-subject-assigned.php', 'label' => 'Assigned Subjects', 'icon' => 'fa-book', 'description' => 'Review assigned subjects.', 'keywords' => 'assigned subjects view'),
        array('module' => 'class_teacher_assignment', 'href' => 'class-teacher-assignment.php', 'label' => 'Class Teacher Assignment', 'icon' => 'fa-users', 'description' => 'Review class teacher assignments.', 'keywords' => 'class teacher assignment'),
        array('module' => 'student_attendance', 'href' => 'student-attendance-report.php', 'label' => 'Attendance Summary', 'icon' => 'fa-bar-chart', 'description' => 'Review attendance summary for students.', 'keywords' => 'attendance students summary'),
        array('module' => 'reports', 'href' => 'terminal-report.php', 'label' => 'Examination Report', 'icon' => 'fa-file-text-o', 'description' => 'Open examination reports.', 'keywords' => 'exam report terminal report results'),
        array('module' => 'reports', 'href' => 'report-approval-board.php', 'label' => 'Report Approval', 'icon' => 'fa-check-circle', 'description' => 'Review report approval status.', 'keywords' => 'report approval release'),
        array('module' => 'reports', 'href' => 'internal-exam-analysis.php', 'label' => 'Internal Exams Analysis', 'icon' => 'fa-bar-chart', 'description' => 'Review internal exams analysis.', 'keywords' => 'internal exams analysis performance'),
        array('module' => 'reports', 'href' => 'waec-analysis.php', 'label' => 'WAEC Analysis', 'icon' => 'fa-line-chart', 'description' => 'Review WAEC analysis and exam trends.', 'keywords' => 'waec analysis external exam performance'),
        array('module' => 'lesson_timetable', 'href' => 'lesson-timetable-report.php', 'label' => 'Lesson Timetable', 'icon' => 'fa-calendar', 'description' => 'Open the lesson timetable report.', 'keywords' => 'lesson timetable schedule'),
        array('module' => 'examination_timetable', 'href' => 'examinationtimetablereport.php', 'label' => 'Exam Time Table Report', 'icon' => 'fa-book', 'description' => 'Review the examination timetable.', 'keywords' => 'exam timetable schedule'),
        array('module' => 'course_registration', 'href' => 'course-registration-admin.php', 'label' => 'Course Registration', 'icon' => 'fa-list-alt', 'description' => 'Review semester course registration.', 'keywords' => 'course registration subjects students'),
        array('module' => 'notice_communication', 'href' => 'notification.php', 'label' => 'Send Notification', 'icon' => 'fa-bullhorn', 'description' => 'Send academic notices and alerts.', 'keywords' => 'send notification notice announcement'),
        array('module' => 'notice_communication', 'href' => 'messages.php', 'label' => 'Messages', 'icon' => 'fa-comments', 'description' => 'Open the internal message board.', 'keywords' => 'messages inbox communication')
    );
}

if(!(function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user())){
    http_response_code(403);
    echo ags_feedback("Access denied", "This search is available only to the assistant head academics dashboard.", "fa-lock");
    exit();
}

if(function_exists('um_current_user_can_access_module') && !um_current_user_can_access_module($con, 'student_search')){
    http_response_code(403);
    echo ags_feedback("Access denied", "Student search is not enabled for this account.", "fa-lock");
    exit();
}

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if($query === ''){
    echo ags_feedback("Start typing", "Search students, teachers, classes, batches, and academic tools from here.");
    exit();
}
if(strlen($query) < 2){
    echo ags_feedback("Keep typing", "Use at least 2 characters to search the dashboard.");
    exit();
}

$like = "%".$query."%";
$prefix = $query."%";
$upper = strtoupper($query);
$normalizedQuery = ags_normalize_search_text($query);
$queryTokens = ags_search_tokens($query);

$currentBranchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : '';
$currentBranchIdEsc = mysqli_real_escape_string($con, $currentBranchId);
$branchUserFilter = $currentBranchId !== '' ? " AND su.branchid='".$currentBranchIdEsc."' " : '';

$studentRows = array();
$studentSql = "SELECT
        su.userid,
        su.firstname,
        su.othernames,
        su.surname,
        su.mobile,
        ce.class_name,
        bh.batch
    FROM tblsystemuser su
    LEFT JOIN (
        SELECT cl.userid, cl.class_entryid, cl.batchid
        FROM tblclass cl
        INNER JOIN (
            SELECT userid, MAX(datetimeentry) AS max_datetime
            FROM tblclass
            WHERE status='active'
            GROUP BY userid
        ) latest
            ON latest.userid = cl.userid
           AND latest.max_datetime = cl.datetimeentry
        WHERE cl.status='active'
    ) current_class ON current_class.userid = su.userid
    LEFT JOIN tblclassentry ce ON ce.class_entryid = current_class.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid = current_class.batchid
    WHERE su.systemtype='Student'
      AND su.status='active'
      ".$branchUserFilter."
      AND (
          su.userid LIKE ?
          OR su.firstname LIKE ?
          OR su.othernames LIKE ?
          OR su.surname LIKE ?
          OR CONCAT_WS(' ', su.firstname, su.othernames, su.surname) LIKE ?
      )
    ORDER BY
      CASE
        WHEN su.userid = ? THEN 0
        WHEN UPPER(CONCAT_WS(' ', su.firstname, su.othernames, su.surname)) = ? THEN 1
        WHEN su.userid LIKE ? THEN 2
        ELSE 3
      END,
      su.firstname ASC,
      su.othernames ASC,
      su.surname ASC
    LIMIT 6";
$studentStmt = mysqli_prepare($con, $studentSql);
if($studentStmt){
    mysqli_stmt_bind_param($studentStmt, "ssssssss", $like, $like, $like, $like, $like, $query, $upper, $prefix);
    mysqli_stmt_execute($studentStmt);
    $studentRes = mysqli_stmt_get_result($studentStmt);
    if($studentRes){
        while($row = mysqli_fetch_array($studentRes, MYSQLI_ASSOC)){
            $studentRows[] = $row;
        }
    }
    mysqli_stmt_close($studentStmt);
}

$teacherRows = array();
$teacherSql = "SELECT
        su.userid,
        su.firstname,
        su.othernames,
        su.surname,
        su.mobile,
        su.systemtype
    FROM tblsystemuser su
    WHERE su.systemtype='Teacher'
      AND su.status='active'
      ".$branchUserFilter."
      AND (
          su.userid LIKE ?
          OR su.firstname LIKE ?
          OR su.othernames LIKE ?
          OR su.surname LIKE ?
          OR CONCAT_WS(' ', su.firstname, su.othernames, su.surname) LIKE ?
      )
    ORDER BY
      CASE
        WHEN su.userid = ? THEN 0
        WHEN UPPER(CONCAT_WS(' ', su.firstname, su.othernames, su.surname)) = ? THEN 1
        WHEN su.userid LIKE ? THEN 2
        ELSE 3
      END,
      su.firstname ASC,
      su.othernames ASC,
      su.surname ASC
    LIMIT 6";
$teacherStmt = mysqli_prepare($con, $teacherSql);
if($teacherStmt){
    mysqli_stmt_bind_param($teacherStmt, "ssssssss", $like, $like, $like, $like, $like, $query, $upper, $prefix);
    mysqli_stmt_execute($teacherStmt);
    $teacherRes = mysqli_stmt_get_result($teacherStmt);
    if($teacherRes){
        while($row = mysqli_fetch_array($teacherRes, MYSQLI_ASSOC)){
            $teacherRows[] = $row;
        }
    }
    mysqli_stmt_close($teacherStmt);
}

$classRows = array();
$classSql = "SELECT
        ce.class_entryid,
        ce.class_name,
        COUNT(DISTINCT cl.userid) AS active_students
    FROM tblclassentry ce
    LEFT JOIN tblclass cl
        ON cl.class_entryid = ce.class_entryid
       AND cl.status='active'
    LEFT JOIN tblsystemuser su
        ON su.userid = cl.userid
       AND su.systemtype='Student'
       AND su.status='active'
    WHERE ce.class_name LIKE ?
      ".($currentBranchId !== '' ? " AND su.branchid='".$currentBranchIdEsc."' " : '')."
    GROUP BY ce.class_entryid, ce.class_name
    ORDER BY
      CASE
        WHEN ce.class_name = ? THEN 0
        WHEN ce.class_name LIKE ? THEN 1
        ELSE 2
      END,
      ce.class_name ASC
    LIMIT 6";
$classStmt = mysqli_prepare($con, $classSql);
if($classStmt){
    mysqli_stmt_bind_param($classStmt, "sss", $like, $query, $prefix);
    mysqli_stmt_execute($classStmt);
    $classRes = mysqli_stmt_get_result($classStmt);
    if($classRes){
        while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
            $classRows[] = $row;
        }
    }
    mysqli_stmt_close($classStmt);
}

$batchRows = array();
$batchSql = "SELECT batchid, batch
    FROM tblbatch
    WHERE batch LIKE ?
    ORDER BY
      CASE
        WHEN batch = ? THEN 0
        WHEN batch LIKE ? THEN 1
        ELSE 2
      END,
      datetimeentry DESC
    LIMIT 6";
$batchStmt = mysqli_prepare($con, $batchSql);
if($batchStmt){
    mysqli_stmt_bind_param($batchStmt, "sss", $like, $query, $prefix);
    mysqli_stmt_execute($batchStmt);
    $batchRes = mysqli_stmt_get_result($batchStmt);
    if($batchRes){
        while($row = mysqli_fetch_array($batchRes, MYSQLI_ASSOC)){
            $batchRows[] = $row;
        }
    }
    mysqli_stmt_close($batchStmt);
}

$toolRows = array();
foreach(ags_tool_catalog() as $tool){
    $moduleKey = isset($tool['module']) ? trim((string)$tool['module']) : '';
    if($moduleKey !== '' && function_exists('um_current_user_can_access_module') && !um_current_user_can_access_module($con, $moduleKey)){
        continue;
    }
    $score = ags_tool_search_score($tool, $normalizedQuery, $queryTokens);
    if($score > 0){
        $tool['score'] = $score;
        $toolRows[] = $tool;
    }
}
if(count($toolRows) > 1){
    usort($toolRows, function($left, $right){
        $leftScore = isset($left['score']) ? (int)$left['score'] : 0;
        $rightScore = isset($right['score']) ? (int)$right['score'] : 0;
        if($leftScore === $rightScore){
            return strcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        }
        return $rightScore <=> $leftScore;
    });
}
$toolRows = array_slice($toolRows, 0, 10);

$totalShown = count($studentRows) + count($teacherRows) + count($classRows) + count($batchRows) + count($toolRows);
if($totalShown === 0){
    echo ags_feedback("No matches found", "Try a student ID, teacher name, class name, batch name, or academic tool.", "fa-search-minus");
    exit();
}

echo "<div class='desktop-search-summary'>";
echo "<div><strong>".number_format($totalShown)." matches shown</strong><span>Search results for &ldquo;".ags_esc($query)."&rdquo;</span></div>";
echo "<span>Students, teachers, classes, batches, and academic tools</span>";
echo "</div>";

if(!empty($studentRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-graduation-cap'></i> Students</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($studentRows as $row){
        $fullName = ags_trim_name($row);
        $classBits = array();
        if(trim((string)($row['class_name'] ?? '')) !== ''){
            $classBits[] = trim((string)$row['class_name']);
        }
        if(trim((string)($row['batch'] ?? '')) !== ''){
            $classBits[] = trim((string)$row['batch']);
        }
        $meta = trim((string)$row['userid']);
        if(!empty($classBits)){
            $meta .= " | ".implode(" | ", $classBits);
        }
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-user-circle-o'></i> Student</div>";
        echo "<a class='desktop-search-card__title' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'>".ags_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".ags_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'><i class='fa fa-eye'></i> View Details</a>";
        echo "<a class='desktop-search-card__action' href='student-history.php?userid=".urlencode((string)$row['userid'])."'><i class='fa fa-history'></i> Transcript</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($teacherRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-users'></i> Teachers</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($teacherRows as $row){
        $fullName = ags_trim_name($row);
        $meta = trim((string)$row['userid'])." | Teacher";
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-id-badge'></i> Teacher</div>";
        echo "<a class='desktop-search-card__title' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'>".ags_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".ags_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'><i class='fa fa-eye'></i> View Details</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($classRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-building-o'></i> Classes</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($classRows as $row){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-building'></i> Class</div>";
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".ags_esc((string)$row['class_name'])."</a>";
        echo "<div class='desktop-search-card__meta'>Active students: ".number_format((int)$row['active_students'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='view-class-registry.php'><i class='fa fa-folder-open'></i> View Registry</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($batchRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-calendar'></i> Batches</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($batchRows as $row){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-calendar-o'></i> Batch</div>";
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".ags_esc((string)$row['batch'])."</a>";
        echo "<div class='desktop-search-card__meta'>Batch ID: ".ags_esc((string)$row['batchid'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='view-class-registry.php'><i class='fa fa-folder-open'></i> View Registry</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($toolRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-compass'></i> Academic Tools</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($toolRows as $tool){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa ".ags_esc((string)$tool['icon'])."'></i> Tool</div>";
        echo "<a class='desktop-search-card__title' href='".ags_esc((string)$tool['href'])."'>".ags_esc((string)$tool['label'])."</a>";
        echo "<div class='desktop-search-card__desc'>".ags_esc((string)$tool['description'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='".ags_esc((string)$tool['href'])."'><i class='fa fa-arrow-right'></i> Open</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}
?>

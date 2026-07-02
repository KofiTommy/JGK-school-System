<?php
session_start();
include("dbstring.php");
include_once("user-management-utils.php");

header("Content-Type: text/html; charset=UTF-8");

function hgs_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function hgs_trim_name($row){
    return trim((string)($row['firstname'] ?? '')." ".(string)($row['othernames'] ?? '')." ".(string)($row['surname'] ?? ''));
}

function hgs_feedback($title, $message, $icon = "fa-search"){
    return "<div class='desktop-search-feedback'><i class='fa ".$icon."'></i><div><strong>".hgs_esc($title)."</strong><span>".hgs_esc($message)."</span></div></div>";
}

function hgs_normalize_search_text($value){
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function hgs_search_tokens($query){
    $normalized = hgs_normalize_search_text($query);
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

function hgs_tool_search_score($tool, $normalizedQuery, $tokens){
    $label = hgs_normalize_search_text($tool['label'] ?? '');
    $description = hgs_normalize_search_text($tool['description'] ?? '');
    $keywords = hgs_normalize_search_text($tool['keywords'] ?? '');
    $href = hgs_normalize_search_text(str_replace(array('.php', '-', '_', '/'), ' ', (string)($tool['href'] ?? '')));
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

function hgs_tool_catalog(){
    return array(
        array('module' => 'student_progression', 'href' => 'student-history.php', 'label' => 'Student Transcript', 'icon' => 'fa-history', 'description' => 'Open a student transcript view.', 'keywords' => 'student transcript results history'),
        array('module' => '', 'href' => 'viewstudents.php', 'label' => 'View Students', 'icon' => 'fa-graduation-cap', 'description' => 'Review student records in bulk.', 'keywords' => 'students list records'),
        array('module' => '', 'href' => 'continuing-students.php', 'label' => 'Continuing Students', 'icon' => 'fa-users', 'description' => 'Review continuing student records by class and batch.', 'keywords' => 'continuing students promotion'),
        array('module' => '', 'href' => 'viewusers.php', 'label' => 'Teachers List', 'icon' => 'fa-users', 'description' => 'Review teacher records and assignments.', 'keywords' => 'teachers staff list'),
        array('module' => '', 'href' => 'duty-roster.php', 'label' => 'Teacher On Duty', 'icon' => 'fa-calendar-check-o', 'description' => 'Open the duty roster summary.', 'keywords' => 'teacher on duty roster'),
        array('module' => '', 'href' => 'senior-house-dashboard.php', 'label' => 'Senior House Overview', 'icon' => 'fa-shield', 'description' => 'Review house welfare and exeat summary.', 'keywords' => 'senior house welfare exeat'),
        array('module' => '', 'href' => 'view-class-registry.php', 'label' => 'View Class Registry', 'icon' => 'fa-folder-open', 'description' => 'Open active class registry records.', 'keywords' => 'class registry students classes'),
        array('module' => 'student_attendance', 'href' => 'student-attendance-report.php', 'label' => 'Attendance Summary', 'icon' => 'fa-bar-chart', 'description' => 'Review attendance summary for students.', 'keywords' => 'attendance students summary'),
        array('module' => '', 'href' => 'terminal-report.php', 'label' => 'Examination Report', 'icon' => 'fa-file-text-o', 'description' => 'Open examination reports.', 'keywords' => 'exam report terminal report results'),
        array('module' => '', 'href' => 'internal-exam-analysis.php', 'label' => 'Internal Exams Analysis', 'icon' => 'fa-bar-chart', 'description' => 'Review internal exams analysis.', 'keywords' => 'internal exams analysis performance'),
        array('module' => '', 'href' => 'waec-analysis.php', 'label' => 'WAEC Analysis', 'icon' => 'fa-line-chart', 'description' => 'Review WAEC analysis and exam trends.', 'keywords' => 'waec analysis external exam performance'),
        array('module' => '', 'href' => 'lesson-timetable-report.php', 'label' => 'Lesson Timetable', 'icon' => 'fa-calendar', 'description' => 'Open the lesson timetable report.', 'keywords' => 'lesson timetable schedule'),
        array('module' => '', 'href' => 'examinationtimetablereport.php', 'label' => 'Exam Time Table Report', 'icon' => 'fa-book', 'description' => 'Review the examination timetable.', 'keywords' => 'exam timetable schedule'),
        array('module' => 'accounts_finance', 'href' => 'payment-analysis.php', 'label' => 'Payment Report', 'icon' => 'fa-line-chart', 'description' => 'Open the payment report summary.', 'keywords' => 'payment finance report fees'),
        array('module' => '', 'href' => 'bills-report.php', 'label' => 'Bills Report', 'icon' => 'fa-files-o', 'description' => 'Review billed items and balances.', 'keywords' => 'bills fees report'),
        array('module' => '', 'href' => 'item-bill-report.php', 'label' => 'Item Bill Report', 'icon' => 'fa-list', 'description' => 'Review billed items by item type.', 'keywords' => 'item bill report fees'),
        array('module' => 'online_admission', 'href' => 'online-admission-admin.php', 'label' => 'Online Admission', 'icon' => 'fa-globe', 'description' => 'Review online admission activity.', 'keywords' => 'online admission applications'),
        array('module' => 'notice_communication', 'href' => 'notification.php', 'label' => 'Send Notification', 'icon' => 'fa-bullhorn', 'description' => 'Send school notices and alerts.', 'keywords' => 'send notification notice announcement'),
        array('module' => '', 'href' => 'messages.php', 'label' => 'Message Box', 'icon' => 'fa-comments', 'description' => 'Open the internal message board.', 'keywords' => 'messages inbox communication')
    );
}

if(!function_exists('um_is_headmaster_user') || !um_is_headmaster_user()){
    http_response_code(403);
    echo hgs_feedback("Access denied", "This search is available only to the headmaster dashboard.", "fa-lock");
    exit();
}

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if($query === ''){
    echo hgs_feedback("Start typing", "Search students, teachers, classes, batches, and school tools from here.");
    exit();
}
if(strlen($query) < 2){
    echo hgs_feedback("Keep typing", "Use at least 2 characters to search the dashboard.");
    exit();
}

$like = "%".$query."%";
$prefix = $query."%";
$upper = strtoupper($query);
$normalizedQuery = hgs_normalize_search_text($query);
$queryTokens = hgs_search_tokens($query);

$currentBranchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : '';
$currentBranchIdEsc = mysqli_real_escape_string($con, $currentBranchId);
$branchUserFilter = $currentBranchId !== '' ? " AND su.branchid='".$currentBranchIdEsc."' " : '';
$branchClassFilter = $currentBranchId !== '' ? " AND su.branchid='".$currentBranchIdEsc."' " : '';

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

$staffRows = array();
$staffSql = "SELECT
        su.userid,
        su.firstname,
        su.othernames,
        su.surname,
        su.mobile,
        su.systemtype,
        su.accesslevel
    FROM tblsystemuser su
    WHERE su.systemtype <> 'Student'
      AND su.status='active'
      ".$branchUserFilter."
      AND (
          su.userid LIKE ?
          OR su.firstname LIKE ?
          OR su.othernames LIKE ?
          OR su.surname LIKE ?
          OR CONCAT_WS(' ', su.firstname, su.othernames, su.surname) LIKE ?
          OR su.systemtype LIKE ?
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
$staffStmt = mysqli_prepare($con, $staffSql);
if($staffStmt){
    mysqli_stmt_bind_param($staffStmt, "sssssssss", $like, $like, $like, $like, $like, $like, $query, $upper, $prefix);
    mysqli_stmt_execute($staffStmt);
    $staffRes = mysqli_stmt_get_result($staffStmt);
    if($staffRes){
        while($row = mysqli_fetch_array($staffRes, MYSQLI_ASSOC)){
            $staffRows[] = $row;
        }
    }
    mysqli_stmt_close($staffStmt);
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
foreach(hgs_tool_catalog() as $tool){
    $moduleKey = isset($tool['module']) ? trim((string)$tool['module']) : '';
    if($moduleKey !== '' && function_exists('um_current_user_can_access_module') && !um_current_user_can_access_module($con, $moduleKey)){
        continue;
    }
    $score = hgs_tool_search_score($tool, $normalizedQuery, $queryTokens);
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

$totalShown = count($studentRows) + count($staffRows) + count($classRows) + count($batchRows) + count($toolRows);
if($totalShown === 0){
    echo hgs_feedback("No matches found", "Try a student ID, teacher name, class name, batch name, or school tool.", "fa-search-minus");
    exit();
}

echo "<div class='desktop-search-summary'>";
echo "<div><strong>".number_format($totalShown)." matches shown</strong><span>Search results for &ldquo;".hgs_esc($query)."&rdquo;</span></div>";
echo "<span>Students, teachers, classes, batches, and tools</span>";
echo "</div>";

if(!empty($studentRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-graduation-cap'></i> Students</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($studentRows as $row){
        $fullName = hgs_trim_name($row);
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
        echo "<a class='desktop-search-card__title' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'>".hgs_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".hgs_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'><i class='fa fa-eye'></i> View Details</a>";
        echo "<a class='desktop-search-card__action' href='student-history.php?userid=".urlencode((string)$row['userid'])."'><i class='fa fa-history'></i> Transcript</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($staffRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-users'></i> Teachers And Staff</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($staffRows as $row){
        $fullName = hgs_trim_name($row);
        $role = function_exists('um_role_label_from_user') ? um_role_label_from_user($row) : trim((string)$row['systemtype']);
        $meta = trim((string)$row['userid'])." | ".trim((string)$role);
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-id-badge'></i> ".hgs_esc($role)."</div>";
        echo "<a class='desktop-search-card__title' href='user-profile.php?view_user=".urlencode((string)$row['userid'])."'>".hgs_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".hgs_esc($meta)."</div>";
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
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".hgs_esc((string)$row['class_name'])."</a>";
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
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-calendar-check-o'></i> Batches</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($batchRows as $row){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-calendar'></i> Batch</div>";
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".hgs_esc((string)$row['batch'])."</a>";
        echo "<div class='desktop-search-card__meta'>Batch ID: ".hgs_esc((string)$row['batchid'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='view-class-registry.php'><i class='fa fa-list-alt'></i> View Registry</a>";
        echo "<a class='desktop-search-card__action' href='continuing-students.php'><i class='fa fa-users'></i> Continuing</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($toolRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-compass'></i> Tools</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($toolRows as $tool){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa ".hgs_esc((string)$tool['icon'])."'></i> Tool</div>";
        echo "<a class='desktop-search-card__title' href='".hgs_esc((string)$tool['href'])."'>".hgs_esc((string)$tool['label'])."</a>";
        echo "<div class='desktop-search-card__desc'>".hgs_esc((string)$tool['description'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='".hgs_esc((string)$tool['href'])."'><i class='fa ".hgs_esc((string)$tool['icon'])."'></i> Open</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

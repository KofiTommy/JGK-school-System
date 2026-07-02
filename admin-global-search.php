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

function ags_default_tool_icon($href){
    $href = strtolower(trim((string)$href));
    if($href === ''){
        return 'fa-compass';
    }
    $map = array(
        'online-voting' => 'fa-trophy',
        'online-admission' => 'fa-globe',
        'student-history' => 'fa-history',
        'terminal-report' => 'fa-file-text-o',
        'scores-report' => 'fa-bar-chart',
        'class-score' => 'fa-pencil-square-o',
        'exam-score' => 'fa-pencil',
        'lesson-timetable' => 'fa-calendar',
        'examinationtimetable' => 'fa-calendar-check-o',
        'course-registration' => 'fa-list-alt',
        'notification' => 'fa-bullhorn',
        'messages' => 'fa-envelope-o',
        'payments' => 'fa-money',
        'billing' => 'fa-credit-card',
        'house' => 'fa-home',
        'counselling' => 'fa-comments-o',
        'duty-roster' => 'fa-calendar-check-o',
        'viewstudents' => 'fa-graduation-cap',
        'viewusers' => 'fa-users',
        'search' => 'fa-search'
    );
    foreach($map as $needle => $icon){
        if(strpos($href, $needle) !== false){
            return $icon;
        }
    }
    return 'fa-compass';
}

function ags_tool_overrides(){
    return array(
        'student_search' => array('href' => 'search.php', 'icon' => 'fa-search', 'keywords' => 'search student finder records'),
        'student_progression' => array('href' => 'student-history.php', 'icon' => 'fa-history', 'keywords' => 'student transcript promotion continuing students'),
        'class_semester_registry' => array('href' => 'view-class-registry.php', 'icon' => 'fa-folder-open', 'keywords' => 'class registry semester registry group term registry'),
        'billing' => array('href' => 'student-billing.php', 'icon' => 'fa-credit-card', 'keywords' => 'student billing fees invoicing finance'),
        'accounts_finance' => array('href' => 'payment-analysis.php', 'icon' => 'fa-money', 'keywords' => 'payments finance accounts reports'),
        'reports' => array('href' => 'terminal-report.php', 'icon' => 'fa-file-text-o', 'keywords' => 'reports result release terminal exams analysis'),
        'examination_scores' => array('href' => 'class-score-entry.php', 'icon' => 'fa-pencil-square-o', 'keywords' => 'score entry class score exam score assessment'),
        'examination_timetable' => array('href' => 'examinationtimetablereport.php', 'icon' => 'fa-calendar-check-o', 'keywords' => 'exam timetable examination timetable report'),
        'lesson_timetable' => array('href' => 'lesson-timetable-report.php', 'icon' => 'fa-calendar', 'keywords' => 'lesson timetable class schedule'),
        'course_registration' => array('href' => 'course-registration-admin.php', 'icon' => 'fa-list-alt', 'keywords' => 'course registration subjects elective semester choices'),
        'online_admission' => array('href' => 'online-admission-admin.php', 'icon' => 'fa-globe', 'keywords' => 'online admission applications admissions'),
        'online_voting' => array('href' => 'online-voting-admin.php', 'icon' => 'fa-trophy', 'keywords' => 'online voting contest election poll pageantry'),
        'notice_communication' => array('href' => 'notification.php', 'icon' => 'fa-bullhorn', 'keywords' => 'notice communication messages announcements'),
        'sms_management' => array('href' => 'smsreport.php', 'icon' => 'fa-commenting', 'keywords' => 'sms texting alerts results sms'),
        'house_management' => array('href' => 'student-house-assignment.php', 'icon' => 'fa-home', 'keywords' => 'house assignment senior house exeat dormitory'),
        'duty_roster' => array('href' => 'duty-roster.php', 'icon' => 'fa-calendar-check-o', 'keywords' => 'teacher on duty roster staff duty'),
        'student_attendance' => array('href' => 'student-attendance-report.php', 'icon' => 'fa-check-square-o', 'keywords' => 'attendance register student attendance summary'),
        'student_teacher_registration' => array('href' => 'viewstudents.php', 'icon' => 'fa-graduation-cap', 'keywords' => 'register student register teacher view students view teachers'),
        'subject_management' => array('href' => 'subject-assignment.php', 'icon' => 'fa-book', 'keywords' => 'subject classification assignment subjects'),
        'class_teacher_assignment' => array('href' => 'class-teacher-assignment.php', 'icon' => 'fa-users', 'keywords' => 'class teacher assignment'),
        'backup_tools' => array('href' => 'backup_db.php', 'icon' => 'fa-database', 'keywords' => 'backup system tools api key')
    );
}

function ags_build_tool_catalog(){
    $tools = array(
        array("href" => "search.php", "label" => "Search Students", "icon" => "fa-search", "description" => "Open the full student search page.", "keywords" => "student search finder records"),
        array("href" => "student-history.php", "label" => "Student Transcript", "icon" => "fa-history", "description" => "View a student's full transcript.", "keywords" => "transcript history results"),
        array("href" => "promotion-center.php", "label" => "Promote Students", "icon" => "fa-level-up", "description" => "Run student promotion for the next class.", "keywords" => "promotion continuing students"),
        array("href" => "register-student.php", "label" => "Register Student", "icon" => "fa-user", "description" => "Add a new student record.", "keywords" => "student admission register"),
        array("href" => "register-teacher.php", "label" => "Register Teacher", "icon" => "fa-user-plus", "description" => "Add a new teacher record.", "keywords" => "teacher staff register"),
        array("href" => "viewstudents.php", "label" => "View Students", "icon" => "fa-graduation-cap", "description" => "Review student records in bulk.", "keywords" => "students list"),
        array("href" => "viewusers.php", "label" => "View Teachers", "icon" => "fa-users", "description" => "Review teacher and staff records.", "keywords" => "teachers staff list"),
        array("href" => "class-registry.php", "label" => "Class Registry", "icon" => "fa-folder-open", "description" => "Assign students to classes.", "keywords" => "class registry assign"),
        array("href" => "group-term-registry.php", "label" => "Group Semester Registry", "icon" => "fa-calendar", "description" => "Register a class into a semester in bulk.", "keywords" => "group term semester registry"),
        array("href" => "term-registry.php", "label" => "Semester Registry", "icon" => "fa-calendar-o", "description" => "Register one student into a semester.", "keywords" => "term semester registry"),
        array("href" => "view-class-registry.php", "label" => "View Class Registry", "icon" => "fa-list-alt", "description" => "Review class registry records.", "keywords" => "view class registry"),
        array("href" => "student-billing.php", "label" => "Student Billing", "icon" => "fa-credit-card", "description" => "Bill students by class and batch.", "keywords" => "billing fees finance"),
        array("href" => "print-student-bills.php", "label" => "Print Student Bills", "icon" => "fa-print", "description" => "Print student bill sheets.", "keywords" => "print bills statements"),
        array("href" => "online-admission-admin.php", "label" => "Online Admission", "icon" => "fa-globe", "description" => "Manage online admission applications.", "keywords" => "online admission applications"),
        array("href" => "online-voting-admin.php", "label" => "Online Voting", "icon" => "fa-trophy", "description" => "Run and monitor online voting activities.", "keywords" => "online voting election contest pageantry poll"),
        array("href" => "guidance-counselling.php", "label" => "Guidance And Counselling", "icon" => "fa-comments-o", "description" => "Open the counselling session board.", "keywords" => "counselling counselor welfare"),
        array("href" => "online-class.php", "label" => "Online Class", "icon" => "fa-video-camera", "description" => "Manage online class sessions and meeting links.", "keywords" => "online class virtual class meeting"),
        array("href" => "messages.php", "label" => "Messages", "icon" => "fa-envelope-o", "description" => "Open the school message board.", "keywords" => "messages communication inbox"),
        array("href" => "notification.php", "label" => "Send Notification", "icon" => "fa-bullhorn", "description" => "Send school-wide notices and alerts.", "keywords" => "notification notice announcement communication"),
        array("href" => "admin-password-reset.php", "label" => "Admin Password Reset", "icon" => "fa-key", "description" => "Reset and send user login details.", "keywords" => "password reset sms credentials"),
        array("href" => "smsreport.php", "label" => "Results SMS", "icon" => "fa-commenting", "description" => "Send result summaries by SMS.", "keywords" => "sms results report"),
        array("href" => "enablesmsalert.php", "label" => "SMS Alerts", "icon" => "fa-bell", "description" => "Manage student SMS alert subscriptions.", "keywords" => "sms alerts notification"),
        array("href" => "scores-report.php", "label" => "Scores Report", "icon" => "fa-bar-chart", "description" => "Review entered scores and reports.", "keywords" => "scores report results"),
        array("href" => "terminal-report.php", "label" => "Terminal Reports", "icon" => "fa-file-text-o", "description" => "Prepare and print terminal reports.", "keywords" => "terminal report semester results"),
        array("href" => "internal-exam-analysis.php", "label" => "Internal Exams Analysis", "icon" => "fa-line-chart", "description" => "Review internal exam performance analysis.", "keywords" => "internal exams analysis results performance"),
        array("href" => "waec-analysis.php", "label" => "WAEC Analysis", "icon" => "fa-line-chart", "description" => "Review WAEC analysis and exam performance trends.", "keywords" => "waec analysis external exams performance"),
        array("href" => "lesson-timetable-report.php", "label" => "Lesson Timetable", "icon" => "fa-calendar", "description" => "Open the lesson timetable report.", "keywords" => "lesson timetable class schedule"),
        array("href" => "course-registration-admin.php", "label" => "Course Registration", "icon" => "fa-list-alt", "description" => "Manage semester course registration windows.", "keywords" => "course registration subjects electives"),
        array("href" => "student-house-assignment.php", "label" => "Student House Assignment", "icon" => "fa-home", "description" => "Assign students to houses and print house lists.", "keywords" => "student house assignment dormitory")
    );

    if(function_exists('um_module_catalog')){
        $moduleCatalog = um_module_catalog();
        $moduleOverrides = ags_tool_overrides();
        foreach($moduleCatalog as $moduleKey => $module){
            $scripts = isset($module['scripts']) && is_array($module['scripts']) ? $module['scripts'] : array();
            if(empty($scripts) && empty($moduleOverrides[$moduleKey]['href'])){
                continue;
            }
            $override = isset($moduleOverrides[$moduleKey]) ? $moduleOverrides[$moduleKey] : array();
            $href = isset($override['href']) ? trim((string)$override['href']) : trim((string)$scripts[0]);
            if($href === ''){
                continue;
            }
            $label = trim((string)($module['label'] ?? $moduleKey));
            $group = trim((string)($module['group'] ?? ''));
            $description = trim((string)($module['description'] ?? ''));
            $scriptKeywords = array();
            foreach($scripts as $script){
                $scriptKeywords[] = str_replace(array('.php', '-', '_'), ' ', trim((string)$script));
            }
            $tools[] = array(
                'href' => $href,
                'label' => $label,
                'icon' => isset($override['icon']) ? $override['icon'] : ags_default_tool_icon($href),
                'description' => $description !== '' ? $description : 'Open the '.$label.' module.',
                'keywords' => trim($moduleKey.' '.$group.' '.implode(' ', $scriptKeywords).' '.(isset($override['keywords']) ? $override['keywords'] : ''))
            );
        }
    }

    $deduped = array();
    foreach($tools as $tool){
        $href = trim((string)($tool['href'] ?? ''));
        $label = trim((string)($tool['label'] ?? ''));
        if($href === '' || $label === ''){
            continue;
        }
        $key = strtolower($href);
        if(!isset($deduped[$key])){
            $deduped[$key] = $tool;
            continue;
        }
        $deduped[$key]['keywords'] = trim((string)($deduped[$key]['keywords'] ?? '').' '.(string)($tool['keywords'] ?? ''));
        if(trim((string)($deduped[$key]['description'] ?? '')) === '' && trim((string)($tool['description'] ?? '')) !== ''){
            $deduped[$key]['description'] = $tool['description'];
        }
    }

    return array_values($deduped);
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

if(!function_exists('um_is_admin_manager') || !um_is_admin_manager()){
    http_response_code(403);
    echo ags_feedback("Access denied", "Desktop search is available only to administrators.", "fa-lock");
    exit();
}

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if($query === ''){
    echo ags_feedback("Start typing", "Search students, staff, classes, batches, and tools from here.");
    exit();
}
if(strlen($query) < 2){
    echo ags_feedback("Keep typing", "Use at least 2 characters to search the desktop.");
    exit();
}

$like = "%".$query."%";
$prefix = $query."%";
$upper = strtoupper($query);
$queryLower = strtolower($query);
$normalizedQuery = ags_normalize_search_text($query);
$queryTokens = ags_search_tokens($query);
$totalShown = 0;

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
    WHERE ce.class_name LIKE ?
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

$toolCatalog = ags_build_tool_catalog();
$toolRows = array();
foreach($toolCatalog as $tool){
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

$totalShown = count($studentRows) + count($staffRows) + count($classRows) + count($batchRows) + count($toolRows);
if($totalShown === 0){
    echo ags_feedback("No matches found", "Try a student ID, staff name, class name, batch name, module, or tool title.", "fa-search-minus");
    exit();
}

echo "<div class='desktop-search-summary'>";
echo "<div><strong>".number_format($totalShown)." matches shown</strong><span>Search results for &ldquo;".ags_esc($query)."&rdquo;</span></div>";
echo "<span>Students, staff, classes, batches, tools, and modules</span>";
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
        echo "<a class='desktop-search-card__title' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'>".ags_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".ags_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'><i class='fa fa-edit'></i> Profile</a>";
        echo "<a class='desktop-search-card__action' href='student-history.php?userid=".urlencode((string)$row['userid'])."'><i class='fa fa-history'></i> Transcript</a>";
        echo "<a class='desktop-search-card__action' href='payments.php?userid=".urlencode((string)$row['userid'])."'><i class='fa fa-money'></i> Payments</a>";
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
        $fullName = ags_trim_name($row);
        $role = function_exists('um_role_label_from_user') ? um_role_label_from_user($row) : trim((string)$row['systemtype']);
        $meta = trim((string)$row['userid'])." | ".trim((string)$role);
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-id-badge'></i> ".ags_esc($role)."</div>";
        echo "<a class='desktop-search-card__title' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'>".ags_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".ags_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'><i class='fa fa-edit'></i> Profile</a>";
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
        echo "<a class='desktop-search-card__action' href='class-registry.php'><i class='fa fa-folder-open'></i> Registry</a>";
        echo "<a class='desktop-search-card__action' href='group-term-registry.php'><i class='fa fa-calendar'></i> Semester</a>";
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
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".ags_esc((string)$row['batch'])."</a>";
        echo "<div class='desktop-search-card__meta'>Batch ID: ".ags_esc((string)$row['batchid'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='view-class-registry.php'><i class='fa fa-list-alt'></i> View Registry</a>";
        echo "<a class='desktop-search-card__action' href='group-term-registry.php'><i class='fa fa-users'></i> Group Registry</a>";
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
        echo "<div class='desktop-search-card__eyebrow'><i class='fa ".ags_esc((string)$tool['icon'])."'></i> Tool</div>";
        echo "<a class='desktop-search-card__title' href='".ags_esc((string)$tool['href'])."'>".ags_esc((string)$tool['label'])."</a>";
        echo "<div class='desktop-search-card__desc'>".ags_esc((string)$tool['description'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='".ags_esc((string)$tool['href'])."'><i class='fa ".ags_esc((string)$tool['icon'])."'></i> Open</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

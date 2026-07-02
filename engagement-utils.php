<?php
if(!function_exists('engagement_schema_cache_is_fresh')){
function engagement_schema_cache_is_fresh($key, $ttlSeconds = 900){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ''){
        return false;
    }
    if(isset($memoryCache[$key])){
        return $memoryCache[$key];
    }
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        $memoryCache[$key] = false;
        return false;
    }
    $cacheBag = isset($_SESSION['_engagement_schema_cache']) && is_array($_SESSION['_engagement_schema_cache'])
        ? $_SESSION['_engagement_schema_cache']
        : array();
    $isFresh = isset($cacheBag[$key]) && ((int)$cacheBag[$key] + (int)$ttlSeconds) > time();
    $memoryCache[$key] = $isFresh;
    return $isFresh;
}
}

if(!function_exists('engagement_schema_cache_mark')){
function engagement_schema_cache_mark($key){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ''){
        return;
    }
    $memoryCache[$key] = true;
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        return;
    }
    if(!isset($_SESSION['_engagement_schema_cache']) || !is_array($_SESSION['_engagement_schema_cache'])){
        $_SESSION['_engagement_schema_cache'] = array();
    }
    $_SESSION['_engagement_schema_cache'][$key] = time();
}
}

if(!function_exists('engagement_current_role_key')){
function engagement_current_role_key(){
    $systemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
    if($systemType === 'Teacher'){
        return 'teacher';
    }
    if($systemType === 'Student'){
        return 'student';
    }
    return '';
}
}

if(!function_exists('engagement_current_user_id')){
function engagement_current_user_id(){
    return isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
}
}

if(!function_exists('engagement_action_catalog')){
function engagement_action_catalog(){
    static $catalog = null;
    if($catalog !== null){
        return $catalog;
    }
    $catalog = array(
        'teacher_dashboard_daily' => array('label' => 'Opened teacher dashboard', 'points' => 1, 'role' => 'teacher'),
        'teacher_class_score_daily' => array('label' => 'Opened class score entry', 'points' => 3, 'role' => 'teacher'),
        'teacher_exam_score_daily' => array('label' => 'Opened exam score entry', 'points' => 3, 'role' => 'teacher'),
        'teacher_lesson_timetable_daily' => array('label' => 'Checked lesson timetable', 'points' => 2, 'role' => 'teacher'),
        'teacher_attendance_summary_daily' => array('label' => 'Reviewed attendance summary', 'points' => 2, 'role' => 'teacher'),
        'teacher_message_board_daily' => array('label' => 'Opened message board', 'points' => 1, 'role' => 'teacher'),
        'teacher_exam_timetable_daily' => array('label' => 'Checked exam timetable', 'points' => 1, 'role' => 'teacher'),
        'teacher_message_sent_daily' => array('label' => 'Sent school message', 'points' => 2, 'role' => 'teacher'),
        'teacher_attendance_saved' => array('label' => 'Saved daily attendance', 'points' => 4, 'role' => 'teacher'),
        'student_dashboard_daily' => array('label' => 'Opened student dashboard', 'points' => 1, 'role' => 'student'),
        'student_terminal_report_daily' => array('label' => 'Checked terminal report', 'points' => 2, 'role' => 'student'),
        'student_account_statement_daily' => array('label' => 'Checked account statement', 'points' => 2, 'role' => 'student'),
        'student_lesson_timetable_daily' => array('label' => 'Checked lesson timetable', 'points' => 2, 'role' => 'student'),
        'student_attendance_daily' => array('label' => 'Checked attendance summary', 'points' => 2, 'role' => 'student'),
        'student_message_board_daily' => array('label' => 'Opened message board', 'points' => 1, 'role' => 'student'),
        'student_exam_timetable_daily' => array('label' => 'Checked exam timetable', 'points' => 1, 'role' => 'student'),
        'student_message_sent_daily' => array('label' => 'Sent school message', 'points' => 2, 'role' => 'student')
    );
    return $catalog;
}
}

if(!function_exists('ensure_engagement_tables')){
function ensure_engagement_tables($con){
    if(!$con || engagement_schema_cache_is_fresh('schema_engagement_v1')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblengagementpointlog (
        logid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        userid VARCHAR(60) NOT NULL,
        rolekey VARCHAR(20) NOT NULL,
        actionkey VARCHAR(80) NOT NULL,
        actionlabel VARCHAR(120) NOT NULL,
        pointvalue INT NOT NULL DEFAULT 0,
        eventkey VARCHAR(100) NOT NULL,
        actiondate DATE NOT NULL,
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_engagement_user_event (userid, eventkey),
        KEY idx_engagement_user_date (userid, actiondate),
        KEY idx_engagement_role_date (rolekey, actiondate)
    )");
    engagement_schema_cache_mark('schema_engagement_v1');
}
}

if(!function_exists('engagement_award_points')){
function engagement_award_points($con, $actionKey, $eventKey, $userId = ''){
    if(!$con){
        return false;
    }
    $catalog = engagement_action_catalog();
    if(!isset($catalog[$actionKey])){
        return false;
    }
    $roleKey = engagement_current_role_key();
    if($roleKey === '' || $catalog[$actionKey]['role'] !== $roleKey){
        return false;
    }
    $userId = trim((string)$userId);
    if($userId === ''){
        $userId = engagement_current_user_id();
    }
    if($userId === ''){
        return false;
    }
    ensure_engagement_tables($con);
    $eventKey = substr(trim((string)$eventKey), 0, 100);
    if($eventKey === ''){
        return false;
    }
    $label = (string)$catalog[$actionKey]['label'];
    $points = (int)$catalog[$actionKey]['points'];
    $actionDate = date('Y-m-d');
    $stmt = @mysqli_prepare($con, "INSERT IGNORE INTO tblengagementpointlog
        (userid, rolekey, actionkey, actionlabel, pointvalue, eventkey, actiondate, datetimeentry)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if(!$stmt){
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ssssiss', $userId, $roleKey, $actionKey, $label, $points, $eventKey, $actionDate);
    $ok = mysqli_stmt_execute($stmt);
    $affected = $ok ? (int)mysqli_stmt_affected_rows($stmt) : 0;
    mysqli_stmt_close($stmt);
    return $ok && $affected > 0;
}
}

if(!function_exists('engagement_track_daily_action')){
function engagement_track_daily_action($con, $actionKey, $userId = ''){
    $eventKey = trim((string)$actionKey).'|'.date('Y-m-d');
    return engagement_award_points($con, $actionKey, $eventKey, $userId);
}
}

if(!function_exists('engagement_track_event_action')){
function engagement_track_event_action($con, $actionKey, $eventSuffix, $userId = ''){
    $eventSuffix = trim((string)$eventSuffix);
    if($eventSuffix === ''){
        return false;
    }
    return engagement_award_points($con, $actionKey, trim((string)$actionKey).'|'.$eventSuffix, $userId);
}
}

if(!function_exists('engagement_track_current_script')){
function engagement_track_current_script($con){
    $roleKey = engagement_current_role_key();
    if($roleKey === ''){
        return false;
    }
    $script = basename((string)(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''));
    $map = array(
        'teacher-page.php' => array('teacher' => 'teacher_dashboard_daily'),
        'student-page.php' => array('student' => 'student_dashboard_daily'),
        'class-score-entry.php' => array('teacher' => 'teacher_class_score_daily'),
        'exam-score-entry.php' => array('teacher' => 'teacher_exam_score_daily'),
        'lesson-timetable-report.php' => array('teacher' => 'teacher_lesson_timetable_daily', 'student' => 'student_lesson_timetable_daily'),
        'student-attendance-report.php' => array('teacher' => 'teacher_attendance_summary_daily', 'student' => 'student_attendance_daily'),
        'messages.php' => array('teacher' => 'teacher_message_board_daily', 'student' => 'student_message_board_daily'),
        'examinationtimetablereport.php' => array('teacher' => 'teacher_exam_timetable_daily', 'student' => 'student_exam_timetable_daily'),
        'individual-terminal-report.php' => array('student' => 'student_terminal_report_daily'),
        'account-statements.php' => array('student' => 'student_account_statement_daily')
    );
    if(!isset($map[$script][$roleKey])){
        return false;
    }
    return engagement_track_daily_action($con, $map[$script][$roleKey]);
}
}

if(!function_exists('engagement_badge_for_points')){
function engagement_badge_for_points($points){
    $points = (int)$points;
    if($points >= 300){
        return array('label' => 'Champion', 'tone' => 'champion');
    }
    if($points >= 150){
        return array('label' => 'Consistent', 'tone' => 'consistent');
    }
    if($points >= 75){
        return array('label' => 'Engaged', 'tone' => 'engaged');
    }
    if($points >= 25){
        return array('label' => 'Active', 'tone' => 'active');
    }
    return array('label' => 'Starter', 'tone' => 'starter');
}
}

if(!function_exists('engagement_badge_ladder')){
function engagement_badge_ladder(){
    return array(
        array('label' => 'Starter', 'tone' => 'starter', 'min_points' => 0, 'stars' => 1),
        array('label' => 'Active', 'tone' => 'active', 'min_points' => 25, 'stars' => 2),
        array('label' => 'Engaged', 'tone' => 'engaged', 'min_points' => 75, 'stars' => 3),
        array('label' => 'Consistent', 'tone' => 'consistent', 'min_points' => 150, 'stars' => 4),
        array('label' => 'Champion', 'tone' => 'champion', 'min_points' => 300, 'stars' => 5)
    );
}
}

if(!function_exists('engagement_progress_meta')){
function engagement_progress_meta($points){
    $points = (int)$points;
    $ladder = engagement_badge_ladder();
    $currentIndex = 0;
    $ladderCount = count($ladder);
    for($i = 0; $i < $ladderCount; $i++){
        if($points >= (int)$ladder[$i]['min_points']){
            $currentIndex = $i;
        }
    }
    $current = $ladder[$currentIndex];
    $next = ($currentIndex < ($ladderCount - 1)) ? $ladder[$currentIndex + 1] : null;
    $currentMin = (int)$current['min_points'];
    $nextMin = $next ? (int)$next['min_points'] : $currentMin;
    $range = max(1, $nextMin - $currentMin);
    $progressPercent = $next ? (int)round((min($points, $nextMin) - $currentMin) / $range * 100) : 100;
    if($progressPercent < 0){
        $progressPercent = 0;
    }
    if($progressPercent > 100){
        $progressPercent = 100;
    }
    return array(
        'current' => $current,
        'next' => $next,
        'progress_percent' => $progressPercent,
        'points_to_next' => $next ? max(0, $nextMin - $points) : 0,
        'current_min_points' => $currentMin,
        'next_min_points' => $nextMin
    );
}
}

if(!function_exists('engagement_get_summary')){
function engagement_get_summary($con, $userId = ''){
    $summary = array(
        'total_points' => 0,
        'week_points' => 0,
        'streak_days' => 0,
        'badge' => engagement_badge_for_points(0),
        'stars' => 1,
        'progress_percent' => 0,
        'points_to_next' => 25,
        'next_badge' => array('label' => 'Active', 'tone' => 'active', 'min_points' => 25, 'stars' => 2),
        'current_min_points' => 0,
        'next_min_points' => 25
    );
    if(!$con){
        return $summary;
    }
    $userId = trim((string)$userId);
    if($userId === ''){
        $userId = engagement_current_user_id();
    }
    if($userId === ''){
        return $summary;
    }
    ensure_engagement_tables($con);
    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $totalsRes = mysqli_query($con, "SELECT
        COALESCE(SUM(pointvalue), 0) AS total_points,
        COALESCE(SUM(CASE WHEN actiondate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN pointvalue ELSE 0 END), 0) AS week_points
        FROM tblengagementpointlog
        WHERE userid='$userIdEsc'");
    if($totalsRes && ($row = mysqli_fetch_array($totalsRes, MYSQLI_ASSOC))){
        $summary['total_points'] = (int)$row['total_points'];
        $summary['week_points'] = (int)$row['week_points'];
    }
    $dateRows = array();
    $datesRes = mysqli_query($con, "SELECT DISTINCT actiondate
        FROM tblengagementpointlog
        WHERE userid='$userIdEsc'
        ORDER BY actiondate DESC
        LIMIT 60");
    if($datesRes){
        while($row = mysqli_fetch_array($datesRes, MYSQLI_ASSOC)){
            $dateRows[] = (string)$row['actiondate'];
        }
    }
    if(!empty($dateRows)){
        $expectedTimestamp = strtotime(date('Y-m-d'));
        $firstTimestamp = strtotime($dateRows[0]);
        if($firstTimestamp !== false){
            if($firstTimestamp === strtotime(date('Y-m-d', $expectedTimestamp - 86400))){
                $expectedTimestamp = $firstTimestamp;
            }
            if($firstTimestamp === $expectedTimestamp){
                $streak = 0;
                foreach($dateRows as $dateValue){
                    $currentTimestamp = strtotime($dateValue);
                    if($currentTimestamp === false){
                        break;
                    }
                    if(date('Y-m-d', $currentTimestamp) !== date('Y-m-d', $expectedTimestamp)){
                        break;
                    }
                    $streak++;
                    $expectedTimestamp -= 86400;
                }
                $summary['streak_days'] = $streak;
            }
        }
    }
    $progressMeta = engagement_progress_meta($summary['total_points']);
    $summary['badge'] = $progressMeta['current'];
    $summary['stars'] = (int)$progressMeta['current']['stars'];
    $summary['progress_percent'] = (int)$progressMeta['progress_percent'];
    $summary['points_to_next'] = (int)$progressMeta['points_to_next'];
    $summary['next_badge'] = $progressMeta['next'];
    $summary['current_min_points'] = (int)$progressMeta['current_min_points'];
    $summary['next_min_points'] = (int)$progressMeta['next_min_points'];
    return $summary;
}
}

if(!function_exists('engagement_get_recent_activity')){
function engagement_get_recent_activity($con, $userId = '', $limit = 5){
    $items = array();
    if(!$con){
        return $items;
    }
    $userId = trim((string)$userId);
    if($userId === ''){
        $userId = engagement_current_user_id();
    }
    if($userId === ''){
        return $items;
    }
    ensure_engagement_tables($con);
    $limit = max(1, min(12, (int)$limit));
    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $res = mysqli_query($con, "SELECT actionlabel, pointvalue, datetimeentry
        FROM tblengagementpointlog
        WHERE userid='$userIdEsc'
        ORDER BY datetimeentry DESC
        LIMIT ".$limit);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $items[] = $row;
        }
    }
    return $items;
}
}

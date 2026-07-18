<?php
include_once("user-management-utils.php");
$storekeeperDashboardStudentUtilsPath = __DIR__ . DIRECTORY_SEPARATOR . "dashboard-student-utils.php";
if (is_file($storekeeperDashboardStudentUtilsPath)) {
    include_once($storekeeperDashboardStudentUtilsPath);
}

if (!function_exists('storekeeper_dashboard_table_has_column')) {
function storekeeper_dashboard_table_has_column($con, $tableName, $columnName)
{
    if (!$con) {
        return false;
    }
    $tableName = trim((string)$tableName);
    $columnName = trim((string)$columnName);
    if ($tableName === '' || $columnName === '') {
        return false;
    }

    $tableEsc = mysqli_real_escape_string($con, $tableName);
    $columnEsc = mysqli_real_escape_string($con, $columnName);
    $res = @mysqli_query($con, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
    return $res && mysqli_num_rows($res) > 0;
}
}

if (!function_exists('storekeeper_dashboard_normalize_gender')) {
function storekeeper_dashboard_normalize_gender($value)
{
    $value = strtoupper(trim((string)$value));
    if (in_array($value, array('M', 'MALE', 'BOY', 'MAN'), true)) {
        return 'Male';
    }
    if (in_array($value, array('F', 'FEMALE', 'GIRL', 'WOMAN'), true)) {
        return 'Female';
    }
    return '';
}
}

if (!function_exists('storekeeper_dashboard_normalize_residence')) {
function storekeeper_dashboard_normalize_residence($value)
{
    $value = strtoupper(trim((string)$value));
    if (in_array($value, array('BOARDING', 'BOARDER', 'B', 'HOSTEL', 'RESIDENT'), true)) {
        return 'Boarding';
    }
    if (in_array($value, array('DAY', 'D', 'DAY STUDENT', 'DAY STUDENTS', 'DAYSCHOLAR'), true)) {
        return 'Day';
    }
    return '';
}
}

if (!function_exists('dashboard_student_population_summary')) {
function dashboard_student_population_summary($con, $options = array())
{
    $summary = array(
        'student_total' => 0,
        'day_students_total' => 0,
        'boarding_students_total' => 0,
        'day_boys' => 0,
        'day_girls' => 0,
        'boarding_boys' => 0,
        'boarding_girls' => 0,
        'other_students_total' => 0,
        'students_no_status' => 0,
        'batch_breakdown' => array(
            'student_total' => array(),
            'day_students_total' => array(),
            'boarding_students_total' => array(),
            'day_boys' => array(),
            'day_girls' => array(),
            'boarding_boys' => array(),
            'boarding_girls' => array(),
            'other_students_total' => array(),
            'students_no_status' => array()
        )
    );
    if (!$con) {
        return $summary;
    }

    $where = array("su.systemtype='Student'");
    if (storekeeper_dashboard_table_has_column($con, 'tblsystemuser', 'status')) {
        $where[] = "COALESCE(su.status,'')='active'";
    }

    $genderSql = storekeeper_dashboard_table_has_column($con, 'tblsystemuser', 'gender')
        ? "COALESCE(su.gender, '') AS gender"
        : "'' AS gender";
    $residenceSql = storekeeper_dashboard_table_has_column($con, 'tblsystemuser', 'residencetype')
        ? "COALESCE(su.residencetype, '') AS residencetype"
        : "'' AS residencetype";

    $sql = "SELECT $genderSql, $residenceSql
        FROM tblsystemuser su
        WHERE " . implode(' AND ', $where);
    $res = @mysqli_query($con, $sql);
    if (!$res) {
        return $summary;
    }

    while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
        $summary['student_total']++;

        $gender = storekeeper_dashboard_normalize_gender(isset($row['gender']) ? $row['gender'] : '');
        $residence = storekeeper_dashboard_normalize_residence(isset($row['residencetype']) ? $row['residencetype'] : '');

        if ($residence === 'Day' && $gender === 'Male') {
            $summary['day_boys']++;
            $summary['day_students_total']++;
        } elseif ($residence === 'Day' && $gender === 'Female') {
            $summary['day_girls']++;
            $summary['day_students_total']++;
        } elseif ($residence === 'Boarding' && $gender === 'Male') {
            $summary['boarding_boys']++;
            $summary['boarding_students_total']++;
        } elseif ($residence === 'Boarding' && $gender === 'Female') {
            $summary['boarding_girls']++;
            $summary['boarding_students_total']++;
        } elseif ($residence === 'Day') {
            $summary['day_students_total']++;
            $summary['other_students_total']++;
        } elseif ($residence === 'Boarding') {
            $summary['boarding_students_total']++;
            $summary['other_students_total']++;
        } else {
            $summary['students_no_status']++;
            $summary['other_students_total']++;
        }
    }

    return $summary;
}
}

if (!function_exists('dashboard_student_batch_breakdown_rows')) {
function dashboard_student_batch_breakdown_rows($summary, $key)
{
    return array();
}
}

if (!function_exists('dashboard_student_batch_label_short')) {
function dashboard_student_batch_label_short($label)
{
    $label = trim((string)$label);
    return $label === '' ? 'No batch' : $label;
}
}

if (!function_exists('dashboard_student_batch_breakdown_text')) {
function dashboard_student_batch_breakdown_text($summary, $key, $limit = 2, $emptyText = 'No batch summary yet.')
{
    return (string)$emptyText;
}
}

if (!function_exists('dashboard_student_batch_breakdown_html')) {
function dashboard_student_batch_breakdown_html($summary, $key, $summaryLabel = 'Batches', $emptyText = 'No batch yet.')
{
    return '<span class="student-batch-toggle__empty">' . htmlspecialchars((string)$emptyText, ENT_QUOTES, 'UTF-8') . '</span>';
}
}

if (!function_exists('storekeeper_is_admin')) {
function storekeeper_is_admin()
{
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true);
}
}

if (!function_exists('storekeeper_landing_page')) {
function storekeeper_landing_page()
{
    if (storekeeper_is_admin()) {
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if (isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])) {
        if ($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "User") {
            return "user.php";
        }
        if ($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Teacher") {
            return "teacher-page.php";
        }
        if ($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Student") {
            return "student-page.php";
        }
        if ($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Headmaster") {
            return "headmaster-page.php";
        }
        if ($_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "AssistantHeadAcademic") {
            return "assistant-head-academics-page.php";
        }
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php";
}
}

if (!function_exists('storekeeper_can_manage_module')) {
function storekeeper_can_manage_module($con = null, $moduleKey = 'stores_management')
{
    if (storekeeper_is_admin()) {
        return true;
    }
    if (!$con || !function_exists('um_current_user_can_access_module')) {
        return false;
    }
    return um_current_user_can_access_module($con, trim((string)$moduleKey));
}
}

if (!function_exists('ensure_storekeeper_tables')) {
function ensure_storekeeper_tables($con)
{
    static $done = false;
    if ($done || !$con) {
        return;
    }
    $done = true;

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstoreitem (
        storeitemid VARCHAR(40) NOT NULL PRIMARY KEY,
        itemname VARCHAR(120) NOT NULL,
        itemcategory VARCHAR(60) NOT NULL DEFAULT 'Food Item',
        unitname VARCHAR(30) NOT NULL,
        reorderlevel DECIMAL(12,2) NOT NULL DEFAULT 0,
        description VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_storeitem_name (itemname),
        KEY idx_storeitem_category (itemcategory),
        KEY idx_storeitem_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstorereceipt (
        receiptid VARCHAR(40) NOT NULL PRIMARY KEY,
        storeitemid VARCHAR(40) NOT NULL,
        receiptdate DATE NOT NULL,
        source_name VARCHAR(120) NOT NULL DEFAULT '',
        quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
        unitcost DECIMAL(12,2) NOT NULL DEFAULT 0,
        batchnumber VARCHAR(60) NOT NULL DEFAULT '',
        expirydate DATE NULL DEFAULT NULL,
        notes VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'posted',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        KEY idx_storereceipt_item (storeitemid),
        KEY idx_storereceipt_date (receiptdate),
        KEY idx_storereceipt_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstoreissue (
        issueid VARCHAR(40) NOT NULL PRIMARY KEY,
        storeitemid VARCHAR(40) NOT NULL,
        issuedate DATE NOT NULL,
        issuedto VARCHAR(120) NOT NULL DEFAULT '',
        purpose VARCHAR(120) NOT NULL DEFAULT '',
        quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
        notes VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'posted',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        KEY idx_storeissue_item (storeitemid),
        KEY idx_storeissue_date (issuedate),
        KEY idx_storeissue_status (status),
        KEY idx_storeissue_issuedto (issuedto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstorestudentissue (
        studentissueid VARCHAR(40) NOT NULL PRIMARY KEY,
        storeitemid VARCHAR(40) NOT NULL,
        studentid VARCHAR(30) NOT NULL,
        issuedate DATE NOT NULL,
        quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
        returnrequired TINYINT(1) NOT NULL DEFAULT 0,
        expectedreturndate DATE NULL DEFAULT NULL,
        actualreturndate DATE NULL DEFAULT NULL,
        purpose VARCHAR(120) NOT NULL DEFAULT '',
        issuecondition VARCHAR(80) NOT NULL DEFAULT '',
        returncondition VARCHAR(80) NOT NULL DEFAULT '',
        notes VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'issued',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        KEY idx_storestudentissue_item (storeitemid),
        KEY idx_storestudentissue_student (studentid),
        KEY idx_storestudentissue_status (status),
        KEY idx_storestudentissue_issue_date (issuedate),
        KEY idx_storestudentissue_expected_return (expectedreturndate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}

if (!function_exists('storekeeper_esc')) {
function storekeeper_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('storekeeper_flash_html')) {
function storekeeper_flash_html($tone, $message)
{
    $tone = strtolower(trim((string)$tone));
    $allowed = array('success', 'error', 'warning', 'info');
    if (!in_array($tone, $allowed, true)) {
        $tone = 'info';
    }

    $icon = 'fa-info-circle';
    if ($tone === 'success') {
        $icon = 'fa-check-circle';
    } elseif ($tone === 'error') {
        $icon = 'fa-exclamation-circle';
    } elseif ($tone === 'warning') {
        $icon = 'fa-exclamation-triangle';
    }

    return "<div class='sk-flash sk-flash--".$tone."'><span class='sk-flash__icon'><i class='fa ".$icon."'></i></span><div class='sk-flash__body'>".$message."</div></div>";
}
}

if (!function_exists('storekeeper_status_badge_html')) {
function storekeeper_status_badge_html($status)
{
    $status = strtolower(trim((string)$status));
    $className = 'neutral';
    $label = ucwords($status);

    if ($status === 'active' || $status === 'posted' || $status === 'returned') {
        $className = 'active';
    } elseif ($status === 'inactive' || $status === 'void') {
        $className = 'inactive';
    } elseif ($status === 'warning' || $status === 'low' || $status === 'awaiting_return') {
        $className = 'warning';
    } elseif ($status === 'issued') {
        $className = 'neutral';
    } elseif ($status === 'out' || $status === 'danger' || $status === 'lost' || $status === 'overdue') {
        $className = 'danger';
    }

    if ($status === 'posted') {
        $label = 'Posted';
    } elseif ($status === 'void') {
        $label = 'Voided';
    } elseif ($status === 'active') {
        $label = 'Active';
    } elseif ($status === 'inactive') {
        $label = 'Inactive';
    } elseif ($status === 'out') {
        $label = 'Out Of Stock';
    } elseif ($status === 'low') {
        $label = 'Low Stock';
    } elseif ($status === 'issued') {
        $label = 'Issued Out';
    } elseif ($status === 'awaiting_return') {
        $label = 'Awaiting Return';
    } elseif ($status === 'overdue') {
        $label = 'Overdue Return';
    } elseif ($status === 'returned') {
        $label = 'Returned';
    } elseif ($status === 'lost') {
        $label = 'Lost / Missing';
    }

    return "<span class='sk-badge sk-badge--".$className."'>".$label."</span>";
}
}

if (!function_exists('storekeeper_stock_badge_html')) {
function storekeeper_stock_badge_html($balance, $reorderLevel)
{
    $balance = (float)$balance;
    $reorderLevel = (float)$reorderLevel;
    if ($balance <= 0) {
        return storekeeper_status_badge_html('out');
    }
    if ($reorderLevel > 0 && $balance <= $reorderLevel) {
        return storekeeper_status_badge_html('low');
    }
    return storekeeper_status_badge_html('active');
}
}

if (!function_exists('storekeeper_categories')) {
function storekeeper_categories()
{
    return array(
        'Food Item',
        'Dry Goods',
        'Protein',
        'Vegetable',
        'Beverage',
        'Condiment',
        'Textbook',
        'Stationery',
        'Uniform',
        'Bedding',
        'Cleaning Supply',
        'Kitchen Supply',
        'Other'
    );
}
}

if (!function_exists('storekeeper_units')) {
function storekeeper_units()
{
    return array(
        'bag',
        'bottle',
        'box',
        'carton',
        'copy',
        'crate',
        'gallon',
        'gram',
        'kilo',
        'litre',
        'pack',
        'pair',
        'piece',
        'sachet',
        'set',
        'tin',
        'tub',
        'unit'
    );
}
}

if (!function_exists('storekeeper_format_quantity')) {
function storekeeper_format_quantity($value)
{
    $formatted = number_format((float)$value, 2, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}
}

if (!function_exists('storekeeper_distinct_categories')) {
function storekeeper_distinct_categories($con)
{
    ensure_storekeeper_tables($con);
    $categories = array();
    $res = @mysqli_query($con, "SELECT DISTINCT itemcategory FROM tblstoreitem WHERE TRIM(COALESCE(itemcategory,''))<>'' ORDER BY itemcategory ASC");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $label = trim((string)$row['itemcategory']);
            if ($label !== '') {
                $categories[$label] = $label;
            }
        }
    }
    foreach (storekeeper_categories() as $label) {
        $categories[$label] = $label;
    }
    ksort($categories);
    return array_values($categories);
}
}

if (!function_exists('storekeeper_date_is_past')) {
function storekeeper_date_is_past($dateValue)
{
    $dateValue = trim((string)$dateValue);
    if ($dateValue === '' || $dateValue === '0000-00-00') {
        return false;
    }
    return strtotime($dateValue) < strtotime(date('Y-m-d'));
}
}

if (!function_exists('storekeeper_normalize_gender_label')) {
function storekeeper_normalize_gender_label($gender)
{
    $gender = strtoupper(trim((string)$gender));
    if (in_array($gender, array('M', 'MALE', 'BOY', 'B'), true)) {
        return 'Boy';
    }
    if (in_array($gender, array('F', 'FEMALE', 'GIRL', 'G'), true)) {
        return 'Girl';
    }
    return '';
}
}

if (!function_exists('storekeeper_normalize_residence_label')) {
function storekeeper_normalize_residence_label($residence)
{
    $residence = strtoupper(trim((string)$residence));
    if (in_array($residence, array('DAY', 'D'), true)) {
        return 'Day';
    }
    if (in_array($residence, array('BOARDING', 'BOARDER', 'B'), true)) {
        return 'Boarding';
    }
    return '';
}
}

if (!function_exists('storekeeper_student_scope_options')) {
function storekeeper_student_scope_options()
{
    return array(
        'all' => 'All Students',
        'day' => 'All Day Students',
        'day_boys' => 'Day Boys',
        'day_girls' => 'Day Girls',
        'boarding' => 'All Boarders',
        'boarding_boys' => 'Boarder Boys',
        'boarding_girls' => 'Boarder Girls'
    );
}
}

if (!function_exists('storekeeper_student_population_bucket')) {
function storekeeper_student_population_bucket($row)
{
    $gender = storekeeper_normalize_gender_label(isset($row['gender']) ? $row['gender'] : '');
    $residence = storekeeper_normalize_residence_label(isset($row['residencetype']) ? $row['residencetype'] : '');

    if ($residence === 'Day' && $gender === 'Boy') {
        return 'day_boys';
    }
    if ($residence === 'Day' && $gender === 'Girl') {
        return 'day_girls';
    }
    if ($residence === 'Boarding' && $gender === 'Boy') {
        return 'boarding_boys';
    }
    if ($residence === 'Boarding' && $gender === 'Girl') {
        return 'boarding_girls';
    }
    if ($residence === 'Day') {
        return 'day';
    }
    if ($residence === 'Boarding') {
        return 'boarding';
    }
    return 'other';
}
}

if (!function_exists('storekeeper_student_scope_label')) {
function storekeeper_student_scope_label($scope)
{
    $options = storekeeper_student_scope_options();
    $scope = trim((string)$scope);
    if (isset($options[$scope])) {
        return $options[$scope];
    }
    return $options['all'];
}
}

if (!function_exists('storekeeper_find_student_scope_key')) {
function storekeeper_find_student_scope_key($value, $defaultValue = 'all')
{
    $options = storekeeper_student_scope_options();
    $value = trim((string)$value);
    if ($value === '') {
        return $defaultValue;
    }
    if (isset($options[$value])) {
        return $value;
    }

    foreach ($options as $scopeKey => $scopeLabel) {
        if (strcasecmp($scopeLabel, $value) === 0) {
            return $scopeKey;
        }
    }

    return $defaultValue;
}
}

if (!function_exists('storekeeper_student_issue_status_options')) {
function storekeeper_student_issue_status_options()
{
    return array(
        'issued' => 'Issued Out',
        'awaiting_return' => 'Awaiting Return',
        'overdue' => 'Overdue',
        'returned' => 'Returned',
        'lost' => 'Lost / Missing',
        'void' => 'Voided'
    );
}
}

if (!function_exists('storekeeper_student_issue_status_label')) {
function storekeeper_student_issue_status_label($status)
{
    $options = storekeeper_student_issue_status_options();
    $status = trim((string)$status);
    if (isset($options[$status])) {
        return $options[$status];
    }
    return '';
}
}

if (!function_exists('storekeeper_find_student_issue_status_key')) {
function storekeeper_find_student_issue_status_key($value)
{
    $options = storekeeper_student_issue_status_options();
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (isset($options[$value])) {
        return $value;
    }

    foreach ($options as $statusKey => $statusLabel) {
        if (strcasecmp($statusLabel, $value) === 0) {
            return $statusKey;
        }
    }

    return '';
}
}

if (!function_exists('storekeeper_enrich_student_row')) {
function storekeeper_enrich_student_row($row)
{
    if (!is_array($row)) {
        return $row;
    }
    $row['_gender_label'] = storekeeper_normalize_gender_label(isset($row['gender']) ? $row['gender'] : '');
    $row['_residence_label'] = storekeeper_normalize_residence_label(isset($row['residencetype']) ? $row['residencetype'] : '');
    $row['_population_bucket'] = storekeeper_student_population_bucket($row);
    $row['_population_label'] = storekeeper_student_scope_label($row['_population_bucket']);
    if (!isset($row['student_name'])) {
        $row['student_name'] = storekeeper_student_display_name($row);
    }
    return $row;
}
}

if (!function_exists('storekeeper_get_item_row')) {
function storekeeper_get_item_row($con, $itemId)
{
    ensure_storekeeper_tables($con);
    $itemId = trim((string)$itemId);
    if ($itemId === '') {
        return null;
    }
    $itemIdEsc = mysqli_real_escape_string($con, $itemId);
    $res = @mysqli_query($con, "SELECT * FROM tblstoreitem WHERE storeitemid='$itemIdEsc' LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return $row;
    }
    return null;
}
}

if (!function_exists('storekeeper_get_student_row')) {
function storekeeper_get_student_row($con, $studentId)
{
    ensure_storekeeper_tables($con);
    $studentId = trim((string)$studentId);
    if ($studentId === '') {
        return null;
    }
    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $res = @mysqli_query($con, "SELECT userid,firstname,othernames,surname,gender,residencetype,systemtype,status
        FROM tblsystemuser
        WHERE userid='$studentIdEsc'
          AND systemtype='Student'
        LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return storekeeper_enrich_student_row($row);
    }
    return null;
}
}

if (!function_exists('storekeeper_student_display_name')) {
function storekeeper_student_display_name($row)
{
    $parts = array();
    foreach (array('firstname', 'othernames', 'surname') as $field) {
        if (isset($row[$field])) {
            $value = trim((string)$row[$field]);
            if ($value !== '') {
                $parts[] = $value;
            }
        }
    }
    $fullName = trim(implode(' ', $parts));
    if ($fullName === '') {
        $fullName = isset($row['userid']) ? trim((string)$row['userid']) : 'Student';
    }
    return $fullName;
}
}

if (!function_exists('storekeeper_picker_normalize')) {
function storekeeper_picker_normalize($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/\s+/', ' ', $value);
    return strtolower($value);
}
}

if (!function_exists('storekeeper_selected_item_name')) {
function storekeeper_selected_item_name($con, $itemId, $rows = array())
{
    $itemId = trim((string)$itemId);
    if ($itemId === '') {
        return 'No item selected yet';
    }

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (isset($row['storeitemid']) && (string)$row['storeitemid'] === $itemId) {
                $itemName = isset($row['itemname']) ? trim((string)$row['itemname']) : '';
                if ($itemName !== '') {
                    return $itemName;
                }
            }
        }
    }

    $itemRow = storekeeper_get_item_row($con, $itemId);
    if ($itemRow && trim((string)$itemRow['itemname']) !== '') {
        return trim((string)$itemRow['itemname']);
    }

    return 'Selected item';
}
}

if (!function_exists('storekeeper_item_picker_label')) {
function storekeeper_item_picker_label($row)
{
    if (!is_array($row)) {
        return '';
    }
    $itemName = isset($row['itemname']) ? trim((string)$row['itemname']) : '';
    $unitName = isset($row['unitname']) ? trim((string)$row['unitname']) : '';
    if ($itemName === '') {
        return '';
    }
    return $unitName !== '' ? ($itemName . ' (' . $unitName . ')') : $itemName;
}
}

if (!function_exists('storekeeper_find_item_by_picker_label')) {
function storekeeper_find_item_by_picker_label($con, $label, $rows = array())
{
    $label = trim((string)$label);
    if ($label === '') {
        return null;
    }
    $normalizedLabel = storekeeper_picker_normalize($label);
    $searchLabel = $label;
    if (stripos($searchLabel, ' - Balance:') !== false) {
        $searchLabel = trim((string)substr($searchLabel, 0, stripos($searchLabel, ' - Balance:')));
    }
    $normalizedSearchLabel = storekeeper_picker_normalize($searchLabel);

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (storekeeper_picker_normalize(storekeeper_item_picker_label($row)) === $normalizedLabel ||
                storekeeper_picker_normalize(storekeeper_item_picker_label($row)) === $normalizedSearchLabel) {
                return $row;
            }
            if (isset($row['itemname']) && (
                storekeeper_picker_normalize($row['itemname']) === $normalizedLabel ||
                storekeeper_picker_normalize($row['itemname']) === $normalizedSearchLabel
            )) {
                return $row;
            }
        }
    }

    $itemName = $searchLabel;
    if (preg_match('/^(.*?)\s*\([^)]+\)$/', $searchLabel, $matches)) {
        $itemName = trim((string)$matches[1]);
    }
    $itemNameEsc = mysqli_real_escape_string($con, $itemName);
    $res = @mysqli_query($con, "SELECT * FROM tblstoreitem
        WHERE itemname='$itemNameEsc'
        LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return $row;
    }
    return null;
}
}

if (!function_exists('storekeeper_selected_student_name')) {
function storekeeper_selected_student_name($con, $studentId, $rows = array())
{
    $studentId = trim((string)$studentId);
    if ($studentId === '') {
        return 'No student selected yet';
    }

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (isset($row['userid']) && (string)$row['userid'] === $studentId) {
                return storekeeper_student_display_name($row);
            }
        }
    }

    $studentRow = storekeeper_get_student_row($con, $studentId);
    if ($studentRow) {
        return storekeeper_student_display_name($studentRow);
    }

    return 'Selected student';
}
}

if (!function_exists('storekeeper_student_picker_label')) {
function storekeeper_student_picker_label($row)
{
    if (!is_array($row)) {
        return '';
    }
    $userId = isset($row['userid']) ? trim((string)$row['userid']) : '';
    if ($userId === '') {
        return '';
    }
    $displayName = storekeeper_student_display_name($row);
    $populationLabel = isset($row['_population_label']) ? trim((string)$row['_population_label']) : '';
    $parts = array($userId, $displayName);
    if ($populationLabel !== '') {
        $parts[] = $populationLabel;
    }
    return implode(' - ', $parts);
}
}

if (!function_exists('storekeeper_find_student_by_picker_label')) {
function storekeeper_find_student_by_picker_label($con, $label, $rows = array())
{
    $label = trim((string)$label);
    if ($label === '') {
        return null;
    }
    $normalizedLabel = storekeeper_picker_normalize($label);

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (storekeeper_picker_normalize(storekeeper_student_picker_label($row)) === $normalizedLabel) {
                return $row;
            }
            if (storekeeper_picker_normalize(storekeeper_student_display_name($row)) === $normalizedLabel) {
                return $row;
            }
        }
    }

    $studentId = $label;
    if (strpos($label, ' - ') !== false) {
        $studentId = trim((string)substr($label, 0, strpos($label, ' - ')));
    }
    $studentRow = storekeeper_get_student_row($con, $studentId);
    if ($studentRow) {
        return $studentRow;
    }

    $studentNameEsc = mysqli_real_escape_string($con, $label);
    $res = @mysqli_query($con, "SELECT userid,firstname,othernames,surname,gender,residencetype,systemtype,status
        FROM tblsystemuser
        WHERE systemtype='Student'
          AND TRIM(CONCAT_WS(' ', firstname, othernames, surname))='$studentNameEsc'
        LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return storekeeper_enrich_student_row($row);
    }
    return null;
}
}

if (!function_exists('storekeeper_active_students')) {
function storekeeper_active_students($con, $limit = 5000, $filters = array())
{
    ensure_storekeeper_tables($con);
    $limit = max(50, min(10000, (int)$limit));
    $filters = is_array($filters) ? $filters : array();
    $scope = isset($filters['scope']) ? trim((string)$filters['scope']) : 'all';
    $search = isset($filters['search']) ? trim((string)$filters['search']) : '';
    $rows = array();
    $res = @mysqli_query($con, "SELECT userid,firstname,othernames,surname,gender,residencetype
        FROM tblsystemuser
        WHERE systemtype='Student'
          AND status='active'
        ORDER BY firstname ASC, othernames ASC, surname ASC
        LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $row = storekeeper_enrich_student_row($row);
            if ($scope !== '' && $scope !== 'all') {
                if ((string)$row['_population_bucket'] !== $scope) {
                    if ($scope === 'day' && strpos((string)$row['_population_bucket'], 'day_') !== 0) {
                        continue;
                    }
                    if ($scope === 'boarding' && strpos((string)$row['_population_bucket'], 'boarding_') !== 0) {
                        continue;
                    }
                    if (!in_array($scope, array('day', 'boarding'), true)) {
                        continue;
                    }
                }
            }
            if ($search !== '') {
                $haystack = strtolower(trim(
                    (string)$row['userid'].' '.
                    (string)$row['firstname'].' '.
                    (string)$row['othernames'].' '.
                    (string)$row['surname'].' '.
                    (string)$row['_population_label'].' '.
                    (string)$row['_residence_label'].' '.
                    (string)$row['_gender_label']
                ));
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('storekeeper_student_population_summary')) {
function storekeeper_student_population_summary($con)
{
    $summary = dashboard_student_population_summary($con, array(
        'require_active_class' => false
    ));

    return array(
        'student_total' => isset($summary['student_total']) ? (int)$summary['student_total'] : 0,
        'day_students_total' => isset($summary['day_students_total']) ? (int)$summary['day_students_total'] : 0,
        'boarding_students_total' => isset($summary['boarding_students_total']) ? (int)$summary['boarding_students_total'] : 0,
        'day_boys' => isset($summary['day_boys']) ? (int)$summary['day_boys'] : 0,
        'day_girls' => isset($summary['day_girls']) ? (int)$summary['day_girls'] : 0,
        'boarding_boys' => isset($summary['boarding_boys']) ? (int)$summary['boarding_boys'] : 0,
        'boarding_girls' => isset($summary['boarding_girls']) ? (int)$summary['boarding_girls'] : 0,
        'other_students_total' => isset($summary['other_students_total']) ? (int)$summary['other_students_total'] : 0,
        'students_no_status' => isset($summary['students_no_status']) ? (int)$summary['students_no_status'] : 0,
        'batch_breakdown' => (isset($summary['batch_breakdown']) && is_array($summary['batch_breakdown'])) ? $summary['batch_breakdown'] : array()
    );
}
}

if (!function_exists('storekeeper_student_issue_status')) {
function storekeeper_student_issue_status($row)
{
    $status = strtolower(trim((string)(isset($row['status']) ? $row['status'] : '')));
    if ($status === 'returned' || $status === 'lost' || $status === 'void') {
        return $status;
    }
    $returnRequired = !empty($row['returnrequired']);
    $expectedReturnDate = isset($row['expectedreturndate']) ? trim((string)$row['expectedreturndate']) : '';
    if ($returnRequired && $expectedReturnDate !== '' && storekeeper_date_is_past($expectedReturnDate)) {
        return 'overdue';
    }
    if ($returnRequired) {
        return 'awaiting_return';
    }
    return 'issued';
}
}

if (!function_exists('storekeeper_student_issue_status_badge_html')) {
function storekeeper_student_issue_status_badge_html($row)
{
    return storekeeper_status_badge_html(storekeeper_student_issue_status($row));
}
}

if (!function_exists('storekeeper_active_items')) {
function storekeeper_active_items($con)
{
    ensure_storekeeper_tables($con);
    $rows = array();
    $res = @mysqli_query($con, "SELECT storeitemid,itemname,itemcategory,unitname,reorderlevel,description,status
        FROM tblstoreitem
        WHERE status='active'
        ORDER BY itemname ASC");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('storekeeper_item_balance')) {
function storekeeper_item_balance($con, $itemId)
{
    ensure_storekeeper_tables($con);
    $itemId = trim((string)$itemId);
    if ($itemId === '') {
        return 0.0;
    }
    $itemIdEsc = mysqli_real_escape_string($con, $itemId);
    $totalReceived = 0.0;
    $totalIssued = 0.0;
    $totalStudentIssued = 0.0;

    $receiptRes = @mysqli_query($con, "SELECT COALESCE(SUM(quantity),0) AS total_received
        FROM tblstorereceipt
        WHERE storeitemid='$itemIdEsc' AND status='posted'");
    if ($receiptRes && ($receiptRow = mysqli_fetch_array($receiptRes, MYSQLI_ASSOC))) {
        $totalReceived = (float)$receiptRow['total_received'];
    }

    $issueRes = @mysqli_query($con, "SELECT COALESCE(SUM(quantity),0) AS total_issued
        FROM tblstoreissue
        WHERE storeitemid='$itemIdEsc' AND status='posted'");
    if ($issueRes && ($issueRow = mysqli_fetch_array($issueRes, MYSQLI_ASSOC))) {
        $totalIssued = (float)$issueRow['total_issued'];
    }

    $studentIssueRes = @mysqli_query($con, "SELECT COALESCE(SUM(quantity),0) AS total_student_issued
        FROM tblstorestudentissue
        WHERE storeitemid='$itemIdEsc'
          AND status IN ('issued','lost')");
    if ($studentIssueRes && ($studentIssueRow = mysqli_fetch_array($studentIssueRes, MYSQLI_ASSOC))) {
        $totalStudentIssued = (float)$studentIssueRow['total_student_issued'];
    }

    return $totalReceived - $totalIssued - $totalStudentIssued;
}
}

if (!function_exists('storekeeper_fetch_balance_rows')) {
function storekeeper_fetch_balance_rows($con, $search = '', $category = '')
{
    ensure_storekeeper_tables($con);
    $where = array("1=1");
    $search = trim((string)$search);
    $category = trim((string)$category);

    if ($search !== '') {
        $searchEsc = mysqli_real_escape_string($con, $search);
        $searchLike = "%".$searchEsc."%";
        $where[] = "(si.storeitemid LIKE '$searchLike'
            OR si.itemname LIKE '$searchLike'
            OR COALESCE(si.itemcategory,'') LIKE '$searchLike'
            OR COALESCE(si.unitname,'') LIKE '$searchLike'
            OR COALESCE(si.description,'') LIKE '$searchLike')";
    }

    if ($category !== '') {
        $categoryEsc = mysqli_real_escape_string($con, $category);
        $where[] = "si.itemcategory='$categoryEsc'";
    }

    $rows = array();
    $sql = "SELECT
            si.*,
            COALESCE((
                SELECT SUM(sr.quantity)
                FROM tblstorereceipt sr
                WHERE sr.storeitemid=si.storeitemid
                  AND sr.status='posted'
            ),0) AS total_received,
            COALESCE((
                SELECT SUM(so.quantity)
                FROM tblstoreissue so
                WHERE so.storeitemid=si.storeitemid
                  AND so.status='posted'
            ),0) AS total_issued,
            COALESCE((
                SELECT SUM(ssi.quantity)
                FROM tblstorestudentissue ssi
                WHERE ssi.storeitemid=si.storeitemid
                  AND ssi.status IN ('issued','lost')
            ),0) AS total_student_issued
        FROM tblstoreitem si
        WHERE ".implode(" AND ", $where)."
        ORDER BY
            CASE WHEN si.status='active' THEN 0 ELSE 1 END,
            si.itemname ASC";
    $res = @mysqli_query($con, $sql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $row['current_balance'] = (float)$row['total_received'] - (float)$row['total_issued'] - (float)$row['total_student_issued'];
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('storekeeper_dashboard_summary')) {
function storekeeper_dashboard_summary($con)
{
    ensure_storekeeper_tables($con);
    $rows = storekeeper_fetch_balance_rows($con);
    $population = storekeeper_student_population_summary($con);
    $summary = array(
        'total_items' => 0,
        'active_items' => 0,
        'inactive_items' => 0,
        'low_stock_items' => 0,
        'out_of_stock_items' => 0,
        'receipt_count_week' => 0,
        'issue_count_week' => 0,
        'student_issue_count_week' => 0,
        'student_items_out' => 0,
        'student_items_overdue' => 0,
        'student_total' => (int)$population['student_total'],
        'day_students_total' => (int)$population['day_students_total'],
        'boarding_students_total' => (int)$population['boarding_students_total'],
        'day_boys' => (int)$population['day_boys'],
        'day_girls' => (int)$population['day_girls'],
        'boarding_boys' => (int)$population['boarding_boys'],
        'boarding_girls' => (int)$population['boarding_girls'],
        'other_students_total' => (int)$population['other_students_total'],
        'students_no_status' => isset($population['students_no_status']) ? (int)$population['students_no_status'] : 0,
        'batch_breakdown' => (isset($population['batch_breakdown']) && is_array($population['batch_breakdown'])) ? $population['batch_breakdown'] : array()
    );

    foreach ($rows as $row) {
        $summary['total_items']++;
        if ((string)$row['status'] === 'active') {
            $summary['active_items']++;
        } else {
            $summary['inactive_items']++;
        }

        $balance = (float)$row['current_balance'];
        $reorderLevel = (float)$row['reorderlevel'];
        if ($balance <= 0) {
            $summary['out_of_stock_items']++;
        } elseif ($reorderLevel > 0 && $balance <= $reorderLevel) {
            $summary['low_stock_items']++;
        }
    }

    $receiptRes = @mysqli_query($con, "SELECT COUNT(*) AS total_receipts
        FROM tblstorereceipt
        WHERE status='posted'
          AND receiptdate >= (CURDATE() - INTERVAL 7 DAY)");
    if ($receiptRes && ($receiptRow = mysqli_fetch_array($receiptRes, MYSQLI_ASSOC))) {
        $summary['receipt_count_week'] = (int)$receiptRow['total_receipts'];
    }

    $issueRes = @mysqli_query($con, "SELECT COUNT(*) AS total_issues
        FROM tblstoreissue
        WHERE status='posted'
          AND issuedate >= (CURDATE() - INTERVAL 7 DAY)");
    if ($issueRes && ($issueRow = mysqli_fetch_array($issueRes, MYSQLI_ASSOC))) {
        $summary['issue_count_week'] = (int)$issueRow['total_issues'];
    }

    $studentIssueRes = @mysqli_query($con, "SELECT
            COUNT(*) AS total_student_issues,
            SUM(CASE
                WHEN status='issued'
                 AND returnrequired=1
                 AND expectedreturndate IS NOT NULL
                 AND expectedreturndate <> '0000-00-00'
                 AND expectedreturndate < CURDATE()
                THEN 1 ELSE 0
            END) AS overdue_total
        FROM tblstorestudentissue
        WHERE status IN ('issued','lost')");
    if ($studentIssueRes && ($studentIssueRow = mysqli_fetch_array($studentIssueRes, MYSQLI_ASSOC))) {
        $summary['student_items_out'] = (int)$studentIssueRow['total_student_issues'];
        $summary['student_items_overdue'] = (int)$studentIssueRow['overdue_total'];
    }

    $studentIssueWeekRes = @mysqli_query($con, "SELECT COUNT(*) AS total_student_issues_week
        FROM tblstorestudentissue
        WHERE status<>'void'
          AND issuedate >= (CURDATE() - INTERVAL 7 DAY)");
    if ($studentIssueWeekRes && ($studentIssueWeekRow = mysqli_fetch_array($studentIssueWeekRes, MYSQLI_ASSOC))) {
        $summary['student_issue_count_week'] = (int)$studentIssueWeekRow['total_student_issues_week'];
    }

    return $summary;
}
}

if (!function_exists('storekeeper_recent_receipts')) {
function storekeeper_recent_receipts($con, $limit = 10)
{
    ensure_storekeeper_tables($con);
    $limit = max(1, min(50, (int)$limit));
    $rows = array();
    $res = @mysqli_query($con, "SELECT
            sr.*,
            COALESCE(si.itemname, sr.storeitemid) AS itemname,
            COALESCE(si.unitname, '') AS unitname
        FROM tblstorereceipt sr
        LEFT JOIN tblstoreitem si ON si.storeitemid=sr.storeitemid
        ORDER BY sr.receiptdate DESC, sr.datetimeentry DESC
        LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('storekeeper_recent_issues')) {
function storekeeper_recent_issues($con, $limit = 10)
{
    ensure_storekeeper_tables($con);
    $limit = max(1, min(50, (int)$limit));
    $rows = array();
    $res = @mysqli_query($con, "SELECT
            siu.*,
            COALESCE(sti.itemname, siu.storeitemid) AS itemname,
            COALESCE(sti.unitname, '') AS unitname
        FROM tblstoreissue siu
        LEFT JOIN tblstoreitem sti ON sti.storeitemid=siu.storeitemid
        ORDER BY siu.issuedate DESC, siu.datetimeentry DESC
        LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('storekeeper_get_student_issue_row')) {
function storekeeper_get_student_issue_row($con, $studentIssueId)
{
    ensure_storekeeper_tables($con);
    $studentIssueId = trim((string)$studentIssueId);
    if ($studentIssueId === '') {
        return null;
    }
    $studentIssueIdEsc = mysqli_real_escape_string($con, $studentIssueId);
    $res = @mysqli_query($con, "SELECT
            ssi.*,
            COALESCE(si.itemname, ssi.storeitemid) AS itemname,
            COALESCE(si.unitname, '') AS unitname,
            su.firstname,
            su.othernames,
            su.surname
        FROM tblstorestudentissue ssi
        LEFT JOIN tblstoreitem si ON si.storeitemid=ssi.storeitemid
        LEFT JOIN tblsystemuser su ON su.userid=ssi.studentid
        WHERE ssi.studentissueid='$studentIssueIdEsc'
        LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return storekeeper_enrich_student_row($row);
    }
    return null;
}
}

if (!function_exists('storekeeper_recent_student_issues')) {
function storekeeper_recent_student_issues($con, $limit = 10)
{
    ensure_storekeeper_tables($con);
    $limit = max(1, min(50, (int)$limit));
    $rows = array();
    $res = @mysqli_query($con, "SELECT
            ssi.*,
            COALESCE(si.itemname, ssi.storeitemid) AS itemname,
            COALESCE(si.unitname, '') AS unitname,
            su.firstname,
            su.othernames,
            su.surname,
            su.gender,
            su.residencetype
        FROM tblstorestudentissue ssi
        LEFT JOIN tblstoreitem si ON si.storeitemid=ssi.storeitemid
        LEFT JOIN tblsystemuser su ON su.userid=ssi.studentid
        ORDER BY ssi.issuedate DESC, ssi.datetimeentry DESC
        LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = storekeeper_enrich_student_row($row);
        }
    }
    return $rows;
}
}

if (!function_exists('storekeeper_fetch_student_issue_rows')) {
function storekeeper_fetch_student_issue_rows($con, $filters = array())
{
    ensure_storekeeper_tables($con);
    $filters = is_array($filters) ? $filters : array();
    $search = isset($filters['search']) ? trim((string)$filters['search']) : '';
    $studentId = isset($filters['studentid']) ? trim((string)$filters['studentid']) : '';
    $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
    $populationScope = isset($filters['population_scope']) ? trim((string)$filters['population_scope']) : '';
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : 250;
    $limit = max(20, min(2000, $limit));

    $where = array("1=1");
    if ($studentId !== '') {
        $studentIdEsc = mysqli_real_escape_string($con, $studentId);
        $where[] = "ssi.studentid='$studentIdEsc'";
    }
    if ($status !== '') {
        $statusEsc = mysqli_real_escape_string($con, $status);
        if ($status === 'overdue') {
            $where[] = "ssi.status='issued'
                AND ssi.returnrequired=1
                AND ssi.expectedreturndate IS NOT NULL
                AND ssi.expectedreturndate <> '0000-00-00'
                AND ssi.expectedreturndate < CURDATE()";
        } elseif ($status === 'awaiting_return') {
            $where[] = "ssi.status='issued'
                AND ssi.returnrequired=1
                AND (ssi.expectedreturndate IS NULL OR ssi.expectedreturndate='0000-00-00' OR ssi.expectedreturndate >= CURDATE())";
        } elseif ($status === 'issued') {
            $where[] = "ssi.status='issued' AND ssi.returnrequired=0";
        } else {
            $where[] = "ssi.status='$statusEsc'";
        }
    }
    if ($populationScope !== '') {
        if ($populationScope === 'day') {
            $where[] = "UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('DAY','D')";
        } elseif ($populationScope === 'boarding') {
            $where[] = "UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('BOARDING','BOARDER','B')";
        } elseif ($populationScope === 'day_boys') {
            $where[] = "UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('DAY','D')
                AND UPPER(TRIM(COALESCE(su.gender,''))) IN ('M','MALE','BOY','B')";
        } elseif ($populationScope === 'day_girls') {
            $where[] = "UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('DAY','D')
                AND UPPER(TRIM(COALESCE(su.gender,''))) IN ('F','FEMALE','GIRL','G')";
        } elseif ($populationScope === 'boarding_boys') {
            $where[] = "UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('BOARDING','BOARDER','B')
                AND UPPER(TRIM(COALESCE(su.gender,''))) IN ('M','MALE','BOY','B')";
        } elseif ($populationScope === 'boarding_girls') {
            $where[] = "UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('BOARDING','BOARDER','B')
                AND UPPER(TRIM(COALESCE(su.gender,''))) IN ('F','FEMALE','GIRL','G')";
        }
    }
    if ($search !== '') {
        $searchEsc = mysqli_real_escape_string($con, $search);
        $searchLike = "%".$searchEsc."%";
        $where[] = "(ssi.studentissueid LIKE '$searchLike'
            OR ssi.studentid LIKE '$searchLike'
            OR COALESCE(ssi.purpose,'') LIKE '$searchLike'
            OR COALESCE(ssi.notes,'') LIKE '$searchLike'
            OR COALESCE(si.itemname,'') LIKE '$searchLike'
            OR CONCAT_WS(' ', COALESCE(su.firstname,''), COALESCE(su.othernames,''), COALESCE(su.surname,'')) LIKE '$searchLike')";
    }

    $rows = array();
    $sql = "SELECT
            ssi.*,
            COALESCE(si.itemname, ssi.storeitemid) AS itemname,
            COALESCE(si.unitname, '') AS unitname,
            su.firstname,
            su.othernames,
            su.surname,
            su.gender,
            su.residencetype
        FROM tblstorestudentissue ssi
        LEFT JOIN tblstoreitem si ON si.storeitemid=ssi.storeitemid
        LEFT JOIN tblsystemuser su ON su.userid=ssi.studentid
        WHERE ".implode(" AND ", $where)."
        ORDER BY
            CASE
                WHEN ssi.status='issued'
                 AND ssi.returnrequired=1
                 AND ssi.expectedreturndate IS NOT NULL
                 AND ssi.expectedreturndate <> '0000-00-00'
                 AND ssi.expectedreturndate < CURDATE() THEN 0
                WHEN ssi.status='issued' THEN 1
                WHEN ssi.status='lost' THEN 2
                WHEN ssi.status='returned' THEN 3
                ELSE 4
            END ASC,
            ssi.issuedate DESC,
            ssi.datetimeentry DESC
        LIMIT $limit";
    $res = @mysqli_query($con, $sql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = storekeeper_enrich_student_row($row);
        }
    }
    return $rows;
}
}

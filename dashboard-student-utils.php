<?php

if (!function_exists('dashboard_student_population_breakdown_keys')) {
function dashboard_student_population_breakdown_keys()
{
    return array(
        'student_total',
        'day_students_total',
        'boarding_students_total',
        'day_boys',
        'day_girls',
        'boarding_boys',
        'boarding_girls',
        'other_students_total',
        'students_no_status'
    );
}
}

if (!function_exists('dashboard_student_population_normalize_gender')) {
function dashboard_student_population_normalize_gender($value)
{
    $value = strtoupper(trim((string)$value));
    if (in_array($value, array('M', 'MALE', 'BOY', 'BOYS', 'B'), true)) {
        return 'Male';
    }
    if (in_array($value, array('F', 'FEMALE', 'GIRL', 'GIRLS', 'G'), true)) {
        return 'Female';
    }
    return 'Other';
}
}

if (!function_exists('dashboard_student_population_normalize_residence')) {
function dashboard_student_population_normalize_residence($value)
{
    $value = strtoupper(trim((string)$value));
    if (in_array($value, array('DAY', 'D'), true)) {
        return 'Day';
    }
    if (in_array($value, array('BOARDING', 'BOARDER', 'B'), true)) {
        return 'Boarding';
    }
    return '';
}
}

if (!function_exists('dashboard_student_population_bucket')) {
function dashboard_student_population_bucket($gender, $residence)
{
    if ($residence === 'Day') {
        if ($gender === 'Male') {
            return 'day_boys';
        }
        if ($gender === 'Female') {
            return 'day_girls';
        }
        return 'day_students_other';
    }
    if ($residence === 'Boarding') {
        if ($gender === 'Male') {
            return 'boarding_boys';
        }
        if ($gender === 'Female') {
            return 'boarding_girls';
        }
        return 'boarding_students_other';
    }
    return 'students_no_status';
}
}

if (!function_exists('dashboard_student_population_breakdown_compare')) {
function dashboard_student_population_breakdown_compare($left, $right)
{
    $leftSort = isset($left['sort']) ? trim((string)$left['sort']) : '';
    $rightSort = isset($right['sort']) ? trim((string)$right['sort']) : '';
    if ($leftSort !== $rightSort) {
        return strcmp($rightSort, $leftSort);
    }
    $leftLabel = isset($left['label']) ? (string)$left['label'] : '';
    $rightLabel = isset($right['label']) ? (string)$right['label'] : '';
    return strnatcasecmp($rightLabel, $leftLabel);
}
}

if (!function_exists('dashboard_student_population_increment_breakdown')) {
function dashboard_student_population_increment_breakdown(&$map, $label, $sortValue)
{
    $label = trim((string)$label);
    if ($label === '') {
        $label = 'No Batch';
    }
    $sortValue = trim((string)$sortValue);
    if (!isset($map[$label])) {
        $map[$label] = array(
            'label' => $label,
            'count' => 0,
            'sort' => $sortValue
        );
    }
    $map[$label]['count']++;
    if ($sortValue !== '' && ((string)$map[$label]['sort'] === '' || strcmp($sortValue, (string)$map[$label]['sort']) > 0)) {
        $map[$label]['sort'] = $sortValue;
    }
}
}

if (!function_exists('dashboard_student_population_rows')) {
function dashboard_student_population_rows($con, $options = array())
{
    $options = is_array($options) ? $options : array();
    $branchId = isset($options['branchid']) ? trim((string)$options['branchid']) : '';
    $requireActiveClass = !array_key_exists('require_active_class', $options) || !empty($options['require_active_class']);
    $rows = array();

    $where = array(
        "su.systemtype='Student'",
        "su.status='active'"
    );
    if ($branchId !== '') {
        $where[] = "su.branchid='" . mysqli_real_escape_string($con, $branchId) . "'";
    }
    if ($requireActiveClass) {
        $where[] = "cl.classid IS NOT NULL";
    }

    $sql = "SELECT
            su.userid,
            COALESCE(su.gender, '') AS gender,
            COALESCE(su.residencetype, '') AS residencetype,
            COALESCE(NULLIF(TRIM(b.batch), ''), 'No Batch') AS batch_label,
            COALESCE(b.datetimeentry, cl.datetimeentry, '') AS batch_sort
        FROM tblsystemuser su
        LEFT JOIN tblclass cl ON cl.classid = (
            SELECT cl2.classid
            FROM tblclass cl2
            WHERE cl2.userid=su.userid
              AND cl2.status='active'
            ORDER BY COALESCE(cl2.datetimeentry, '0000-00-00 00:00:00') DESC, cl2.classid DESC
            LIMIT 1
        )
        LEFT JOIN tblbatch b ON b.batchid=cl.batchid
        WHERE " . implode(" AND ", $where) . "
        ORDER BY COALESCE(b.datetimeentry, cl.datetimeentry, '') DESC, batch_label DESC, su.userid ASC";
    $res = @mysqli_query($con, $sql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $row['_gender_group'] = dashboard_student_population_normalize_gender(isset($row['gender']) ? $row['gender'] : '');
            $row['_residence_group'] = dashboard_student_population_normalize_residence(isset($row['residencetype']) ? $row['residencetype'] : '');
            $row['_population_bucket'] = dashboard_student_population_bucket($row['_gender_group'], $row['_residence_group']);
            $rows[] = $row;
        }
    }

    return $rows;
}
}

if (!function_exists('dashboard_student_population_summary')) {
function dashboard_student_population_summary($con, $options = array())
{
    $rows = dashboard_student_population_rows($con, $options);
    $breakdownKeys = dashboard_student_population_breakdown_keys();
    $breakdownMaps = array();
    foreach ($breakdownKeys as $breakdownKey) {
        $breakdownMaps[$breakdownKey] = array();
    }

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
        'batch_breakdown' => array()
    );

    foreach ($rows as $row) {
        $bucket = isset($row['_population_bucket']) ? (string)$row['_population_bucket'] : 'students_no_status';
        $batchLabel = isset($row['batch_label']) ? (string)$row['batch_label'] : 'No Batch';
        $batchSort = isset($row['batch_sort']) ? (string)$row['batch_sort'] : '';

        $summary['student_total']++;
        dashboard_student_population_increment_breakdown($breakdownMaps['student_total'], $batchLabel, $batchSort);

        if ($bucket === 'day_boys') {
            $summary['day_boys']++;
            $summary['day_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['day_boys'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['day_students_total'], $batchLabel, $batchSort);
        } elseif ($bucket === 'day_girls') {
            $summary['day_girls']++;
            $summary['day_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['day_girls'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['day_students_total'], $batchLabel, $batchSort);
        } elseif ($bucket === 'boarding_boys') {
            $summary['boarding_boys']++;
            $summary['boarding_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['boarding_boys'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['boarding_students_total'], $batchLabel, $batchSort);
        } elseif ($bucket === 'boarding_girls') {
            $summary['boarding_girls']++;
            $summary['boarding_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['boarding_girls'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['boarding_students_total'], $batchLabel, $batchSort);
        } elseif ($bucket === 'day_students_other') {
            $summary['day_students_total']++;
            $summary['other_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['day_students_total'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['other_students_total'], $batchLabel, $batchSort);
        } elseif ($bucket === 'boarding_students_other') {
            $summary['boarding_students_total']++;
            $summary['other_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['boarding_students_total'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['other_students_total'], $batchLabel, $batchSort);
        } else {
            $summary['students_no_status']++;
            $summary['other_students_total']++;
            dashboard_student_population_increment_breakdown($breakdownMaps['students_no_status'], $batchLabel, $batchSort);
            dashboard_student_population_increment_breakdown($breakdownMaps['other_students_total'], $batchLabel, $batchSort);
        }
    }

    foreach ($breakdownMaps as $breakdownKey => $breakdownMap) {
        $rowsForKey = array_values($breakdownMap);
        if (!empty($rowsForKey)) {
            usort($rowsForKey, 'dashboard_student_population_breakdown_compare');
        }
        foreach ($rowsForKey as $index => $row) {
            unset($rowsForKey[$index]['sort']);
        }
        $summary['batch_breakdown'][$breakdownKey] = array_values($rowsForKey);
    }

    return $summary;
}
}

if (!function_exists('dashboard_student_batch_breakdown_rows')) {
function dashboard_student_batch_breakdown_rows($summary, $key)
{
    if (!is_array($summary)
        || !isset($summary['batch_breakdown'])
        || !is_array($summary['batch_breakdown'])
        || !isset($summary['batch_breakdown'][$key])
        || !is_array($summary['batch_breakdown'][$key])) {
        return array();
    }
    return $summary['batch_breakdown'][$key];
}
}

if (!function_exists('dashboard_student_batch_label_short')) {
function dashboard_student_batch_label_short($label)
{
    $label = trim((string)$label);
    if ($label === '') {
        return 'No batch';
    }
    if (strcasecmp($label, 'No Batch') === 0) {
        return 'No batch';
    }

    $normalized = preg_replace('/[\-_]+/', ' ', $label);
    $normalized = preg_replace('/\s+/', ' ', (string)$normalized);
    $normalized = trim((string)$normalized);

    if (preg_match('/((?:19|20)\d{2})$/', $normalized, $matches)) {
        return (string)$matches[1];
    }

    return $label;
}
}

if (!function_exists('dashboard_student_batch_breakdown_text')) {
function dashboard_student_batch_breakdown_text($summary, $key, $limit = 2, $emptyText = 'No batch summary yet.')
{
    $rows = dashboard_student_batch_breakdown_rows($summary, $key);
    if (empty($rows)) {
        return $emptyText;
    }

    $parts = array();

    foreach ($rows as $row) {
        $label = isset($row['label']) ? trim((string)$row['label']) : 'No Batch';
        if ($label === '') {
            $label = 'No Batch';
        }
        $label = dashboard_student_batch_label_short($label);
        $count = isset($row['count']) ? (int)$row['count'] : 0;
        $parts[] = $label . ': ' . number_format($count);
    }

    return implode("\n", $parts);
}
}

if (!function_exists('dashboard_student_batch_breakdown_html')) {
function dashboard_student_batch_breakdown_html($summary, $key, $summaryLabel = 'Batches', $emptyText = 'No batch yet.')
{
    $rows = dashboard_student_batch_breakdown_rows($summary, $key);
    if (empty($rows)) {
        return '<span class="student-batch-toggle__empty">' . htmlspecialchars((string)$emptyText, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $items = array();
    foreach ($rows as $row) {
        $label = isset($row['label']) ? trim((string)$row['label']) : 'No Batch';
        if ($label === '') {
            $label = 'No Batch';
        }
        $label = dashboard_student_batch_label_short($label);
        $count = isset($row['count']) ? (int)$row['count'] : 0;
        $items[] = '<span>' . htmlspecialchars($label . ': ' . number_format($count), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return '<details class="student-batch-toggle">'
        . '<summary>' . htmlspecialchars((string)$summaryLabel, ENT_QUOTES, 'UTF-8') . '</summary>'
        . '<div class="student-batch-toggle__list">' . implode('', $items) . '</div>'
        . '</details>';
}
}

<?php
if (!function_exists('tr_terminal_report_pdf_text')) {
function tr_terminal_report_pdf_text($value)
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $value);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $value;
}
}

if (!function_exists('tr_terminal_report_filename_part')) {
function tr_terminal_report_filename_part($value, $fallback = 'report')
{
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== '' ? $value : $fallback;
}
}

if (!function_exists('tr_terminal_report_fetch_batch_label')) {
function tr_terminal_report_fetch_batch_label($con, $batchId)
{
    $batchId = trim((string)$batchId);
    if (!$con || $batchId === '') {
        return '';
    }
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $res = @mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$batchIdEsc' LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return trim((string)$row['batch']);
    }
    return '';
}
}

if (!function_exists('tr_terminal_report_fetch_class_label')) {
function tr_terminal_report_fetch_class_label($con, $classId)
{
    $classId = trim((string)$classId);
    if (!$con || $classId === '') {
        return '';
    }
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $res = @mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$classIdEsc' LIMIT 1");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return trim((string)$row['class_name']);
    }
    return '';
}
}

if (!function_exists('tr_terminal_report_fetch_company_meta')) {
function tr_terminal_report_fetch_company_meta($con)
{
    $meta = array(
        'company_name' => '',
        'address' => '',
        'location' => '',
        'telephone1' => '',
        'telephone2' => '',
        'logo' => ''
    );
    if (!$con) {
        return $meta;
    }

    $sql = "SELECT cm.fullname, br.address, br.location, br.telephone1, br.telephone2,
            cm.logo AS company_logo, br.logo AS branch_logo
        FROM tblbranch br
        INNER JOIN tblcompany cm ON br.companyid=cm.companyid
        WHERE br.status='active'
        LIMIT 1";
    $res = @mysqli_query($con, $sql);
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $meta['company_name'] = isset($row['fullname']) ? trim((string)$row['fullname']) : '';
        $meta['address'] = isset($row['address']) ? trim((string)$row['address']) : '';
        $meta['location'] = isset($row['location']) ? trim((string)$row['location']) : '';
        $meta['telephone1'] = isset($row['telephone1']) ? trim((string)$row['telephone1']) : '';
        $meta['telephone2'] = isset($row['telephone2']) ? trim((string)$row['telephone2']) : '';
        $meta['logo'] = isset($row['company_logo']) ? trim((string)$row['company_logo']) : '';
        if ($meta['logo'] === '' && !empty($row['branch_logo'])) {
            $meta['logo'] = trim((string)$row['branch_logo']);
        }
    }

    return $meta;
}
}

if (!function_exists('tr_terminal_report_prepare_dependencies')) {
function tr_terminal_report_prepare_dependencies($con)
{
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'class-teacher-utils.php');
    if (function_exists('ensure_student_terminal_term_column')) {
        ensure_student_terminal_term_column($con);
    }

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'house-master-utils.php');
    if (function_exists('ensure_house_tables')) {
        ensure_house_tables($con);
    }

    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'config.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'gradingsystem.php');

    $fpdfPath = __DIR__ . DIRECTORY_SEPARATOR . 'fpdf181' . DIRECTORY_SEPARATOR . 'fpdf.php';
    if (!file_exists($fpdfPath)) {
        return array(
            'success' => false,
            'message' => 'Print setup error: PDF library file not found.'
        );
    }
    if (!class_exists('FPDF')) {
        require($fpdfPath);
    }

    ini_set('log_errors', '1');
    ini_set('display_errors', '0');
    ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'print-error.log');
    error_reporting(E_ALL);
    @set_time_limit(180);

    return array('success' => true);
}
}

if (!function_exists('tr_terminal_report_fetch_scope_meta')) {
function tr_terminal_report_fetch_scope_meta($con, $batchId, $academicYear, $termId)
{
    $termFilter = (trim((string)$termId) !== '') ? (int)$termId : 0;
    $meta = array(
        'school_closes' => '',
        'next_term_begins' => '',
        'academic_year_label' => trim((string)$academicYear),
        'semester_label' => ($termFilter > 0 ? (string)$termFilter : ''),
        'batch_label' => tr_terminal_report_fetch_batch_label($con, $batchId)
    );

    if (function_exists('school_data_fetch_scope')) {
        $schoolInfoRow = school_data_fetch_scope($con, $batchId, trim((string)$academicYear), $termFilter);
        if (is_array($schoolInfoRow)) {
            $meta['school_closes'] = function_exists('school_data_display_date')
                ? school_data_display_date(isset($schoolInfoRow['schoolcloses']) ? $schoolInfoRow['schoolcloses'] : '')
                : trim((string)(isset($schoolInfoRow['schoolcloses']) ? $schoolInfoRow['schoolcloses'] : ''));
            $meta['next_term_begins'] = function_exists('school_data_display_date')
                ? school_data_display_date(isset($schoolInfoRow['schoolresumes']) ? $schoolInfoRow['schoolresumes'] : '')
                : trim((string)(isset($schoolInfoRow['schoolresumes']) ? $schoolInfoRow['schoolresumes'] : ''));

            $academicYearLabel = trim((string)(isset($schoolInfoRow['academicyear']) ? $schoolInfoRow['academicyear'] : ''));
            if ($academicYearLabel === '' && trim((string)(isset($schoolInfoRow['datetimeentry']) ? $schoolInfoRow['datetimeentry'] : '')) !== '') {
                $academicYearLabel = date('Y', strtotime((string)$schoolInfoRow['datetimeentry']));
            }
            if ($academicYearLabel !== '') {
                $meta['academic_year_label'] = $academicYearLabel;
            }

            if ($meta['semester_label'] === '' && isset($schoolInfoRow['termname'])) {
                $semesterCandidate = trim((string)$schoolInfoRow['termname']);
                if ($semesterCandidate !== '' && $semesterCandidate !== '0') {
                    $meta['semester_label'] = $semesterCandidate;
                }
            }
        }
    }

    return $meta;
}
}

if (!function_exists('tr_terminal_report_fetch_terminal_summary_row')) {
function tr_terminal_report_fetch_terminal_summary_row($con, $userId, $batchId, $termId)
{
    $userId = trim((string)$userId);
    $batchId = trim((string)$batchId);
    if (!$con || $userId === '' || $batchId === '') {
        return null;
    }

    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $termFilter = trim((string)$termId);
    if ($termFilter !== '') {
        $termFilter = (string)((int)$termFilter);
        $sql = "SELECT * FROM tblstudentterminalreport
            WHERE userid='$userIdEsc'
              AND batchid='$batchIdEsc'
              AND (termname='$termFilter' OR termname='0')
            ORDER BY termname DESC, datetimeentry DESC
            LIMIT 1";
    } else {
        $sql = "SELECT * FROM tblstudentterminalreport
            WHERE userid='$userIdEsc'
              AND batchid='$batchIdEsc'
            ORDER BY datetimeentry DESC
            LIMIT 1";
    }
    $res = @mysqli_query($con, $sql);
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return $row;
    }
    return null;
}
}

if (!function_exists('tr_terminal_report_fetch_student_house_name')) {
function tr_terminal_report_fetch_student_house_name($con, $userId)
{
    if (function_exists('get_student_active_house')) {
        $row = get_student_active_house($con, $userId);
        if (is_array($row) && !empty($row['housename'])) {
            return trim((string)$row['housename']);
        }
    }
    return 'Not Assigned';
}
}

if (!function_exists('tr_terminal_report_build_assignment_sql')) {
function tr_terminal_report_build_assignment_sql($con, $userId, $batchId, $termId, $classId, $academicYear)
{
    $userIdEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termId = trim((string)$termId);
    $classId = trim((string)$classId);
    $academicYear = trim((string)$academicYear);

    $filters = array(
        "su.userid='$userIdEsc'",
        "sa.batchid='$batchIdEsc'"
    );
    if ($termId !== '') {
        $filters[] = "sa.termname='" . ((int)$termId) . "'";
    }
    if ($classId !== '') {
        $filters[] = "sa.classid='" . mysqli_real_escape_string($con, $classId) . "'";
    }
    if ($academicYear !== '') {
        $filters[] = semester_registry_assignment_year_sql('sa') . "='" . mysqli_real_escape_string($con, $academicYear) . "'";
    }

    return "SELECT DISTINCT
            mk.assignmentid,
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            sa.termname,
            ce.class_name,
            sub.subject,
            sub.subjectid,
            bh.batch
        FROM tblmark mk
        INNER JOIN tblsystemuser su ON mk.userid=su.userid
        INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
        INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
        INNER JOIN tblbatch bh ON sa.batchid=bh.batchid
        WHERE " . implode(' AND ', $filters) . "
        ORDER BY sub.subject ASC";
}
}

if (!function_exists('tr_terminal_report_fetch_assignment_rows')) {
function tr_terminal_report_fetch_assignment_rows($con, $userId, $batchId, $termId, $classId, $academicYear)
{
    $rows = array();
    $res = @mysqli_query($con, tr_terminal_report_build_assignment_sql($con, $userId, $batchId, $termId, $classId, $academicYear));
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('tr_terminal_report_fetch_assignment_mark_map')) {
function tr_terminal_report_fetch_assignment_mark_map($con, $assignmentRows, $userId)
{
    $assignmentIds = array();
    foreach ((array)$assignmentRows as $row) {
        if (!empty($row['assignmentid'])) {
            $assignmentIds[] = "'" . mysqli_real_escape_string($con, (string)$row['assignmentid']) . "'";
        }
    }
    $assignmentIds = array_values(array_unique($assignmentIds));
    if (empty($assignmentIds)) {
        return array();
    }

    $userIdEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $markMap = array();
    $res = @mysqli_query($con, "SELECT assignmentid,testtype,mark
        FROM tblmark
        WHERE userid='$userIdEsc'
          AND assignmentid IN (" . implode(',', $assignmentIds) . ")");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $assignmentId = isset($row['assignmentid']) ? (string)$row['assignmentid'] : '';
            $testType = strtolower(trim((string)(isset($row['testtype']) ? $row['testtype'] : '')));
            if ($assignmentId === '' || $testType === '') {
                continue;
            }
            $markMap[$assignmentId][$testType] = (float)$row['mark'];
        }
    }

    return $markMap;
}
}

if (!function_exists('tr_terminal_report_fetch_student_context_row')) {
function tr_terminal_report_fetch_student_context_row($con, $userId, $batchId, $classId)
{
    $userId = trim((string)$userId);
    if (!$con || $userId === '') {
        return array();
    }

    $userIdEsc = mysqli_real_escape_string($con, $userId);
    $res = @mysqli_query($con, "SELECT userid,firstname,othernames,surname
        FROM tblsystemuser
        WHERE userid='$userIdEsc'
        LIMIT 1");
    $row = array(
        'userid' => $userId,
        'firstname' => '',
        'othernames' => '',
        'surname' => '',
        'class_name' => tr_terminal_report_fetch_class_label($con, $classId),
        'batch' => tr_terminal_report_fetch_batch_label($con, $batchId)
    );
    if ($res && ($_UserRow = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $row['firstname'] = isset($_UserRow['firstname']) ? $_UserRow['firstname'] : '';
        $row['othernames'] = isset($_UserRow['othernames']) ? $_UserRow['othernames'] : '';
        $row['surname'] = isset($_UserRow['surname']) ? $_UserRow['surname'] : '';
    }
    return $row;
}
}

if (!function_exists('tr_terminal_report_fetch_overall_score')) {
function tr_terminal_report_fetch_overall_score($con, $userId, $batchId, $termId, $classId, $academicYear)
{
    $userIdEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $filters = array(
        "sa.batchid='$batchIdEsc'",
        "mk.userid='$userIdEsc'"
    );
    if (trim((string)$termId) !== '') {
        $filters[] = "sa.termname='" . ((int)$termId) . "'";
    }
    if (trim((string)$classId) !== '') {
        $filters[] = "sa.classid='" . mysqli_real_escape_string($con, trim((string)$classId)) . "'";
    }
    if (trim((string)$academicYear) !== '') {
        $filters[] = semester_registry_assignment_year_sql('sa') . "='" . mysqli_real_escape_string($con, trim((string)$academicYear)) . "'";
    }
    $sql = "SELECT SUM(mk.mark) AS overall_score
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
        WHERE " . implode(' AND ', $filters);
    $res = @mysqli_query($con, $sql);
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        return (float)$row['overall_score'];
    }
    return 0.0;
}
}

if (!function_exists('tr_terminal_report_resolve_semester_label')) {
function tr_terminal_report_resolve_semester_label($scopeMeta, $terminalRow, $assignmentRows)
{
    $semesterLabel = isset($scopeMeta['semester_label']) ? trim((string)$scopeMeta['semester_label']) : '';
    if ($semesterLabel === '' && is_array($terminalRow) && isset($terminalRow['termname'])) {
        $candidate = trim((string)$terminalRow['termname']);
        if ($candidate !== '' && $candidate !== '0') {
            $semesterLabel = $candidate;
        }
    }
    if ($semesterLabel === '' && !empty($assignmentRows) && isset($assignmentRows[0]['termname'])) {
        $candidate = trim((string)$assignmentRows[0]['termname']);
        if ($candidate !== '' && $candidate !== '0') {
            $semesterLabel = $candidate;
        }
    }
    return $semesterLabel;
}
}

if (!function_exists('tr_terminal_report_fetch_scope_students')) {
function tr_terminal_report_fetch_scope_students($con, $batchId, $academicYear, $termId, $classId)
{
    $rows = array();
    if (!$con || trim((string)$batchId) === '') {
        return $rows;
    }

    $filters = array(
        "su.systemtype='Student'",
        "tr.batchid='" . mysqli_real_escape_string($con, trim((string)$batchId)) . "'"
    );
    if (trim((string)$academicYear) !== '') {
        $filters[] = semester_registry_resolved_year_sql('tr') . "='" . mysqli_real_escape_string($con, trim((string)$academicYear)) . "'";
    }
    if (trim((string)$termId) !== '') {
        $filters[] = "tr.termname='" . ((int)$termId) . "'";
    }
    if (trim((string)$classId) !== '') {
        $filters[] = "tr.class_entryid='" . mysqli_real_escape_string($con, trim((string)$classId)) . "'";
    }

    $sql = "SELECT DISTINCT
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            COALESCE(ce.class_name, '') AS class_name,
            COALESCE(bh.batch, '') AS batch
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON tr.userid=su.userid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
        WHERE " . implode(' AND ', $filters) . "
        ORDER BY ce.class_name ASC, su.surname ASC, su.firstname ASC, su.othernames ASC, su.userid ASC";
    $res = @mysqli_query($con, $sql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }

    if (!empty($rows)) {
        return $rows;
    }

    $fallbackFilters = array(
        "su.systemtype='Student'",
        "sa.batchid='" . mysqli_real_escape_string($con, trim((string)$batchId)) . "'"
    );
    if (trim((string)$academicYear) !== '') {
        $fallbackFilters[] = semester_registry_assignment_year_sql('sa') . "='" . mysqli_real_escape_string($con, trim((string)$academicYear)) . "'";
    }
    if (trim((string)$termId) !== '') {
        $fallbackFilters[] = "sa.termname='" . ((int)$termId) . "'";
    }
    if (trim((string)$classId) !== '') {
        $fallbackFilters[] = "sa.classid='" . mysqli_real_escape_string($con, trim((string)$classId)) . "'";
    }

    $fallbackSql = "SELECT DISTINCT
            su.userid,
            su.firstname,
            su.othernames,
            su.surname,
            COALESCE(ce.class_name, '') AS class_name,
            COALESCE(bh.batch, '') AS batch
        FROM tblmark mk
        INNER JOIN tblsystemuser su ON mk.userid=su.userid
        INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
        INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        INNER JOIN tblbatch bh ON bh.batchid=sa.batchid
        WHERE " . implode(' AND ', $fallbackFilters) . "
        ORDER BY ce.class_name ASC, su.surname ASC, su.firstname ASC, su.othernames ASC, su.userid ASC";
    $res = @mysqli_query($con, $fallbackSql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }

    return $rows;
}
}

if (!function_exists('tr_terminal_report_render_student_page')) {
function tr_terminal_report_render_student_page($pdf, $con, $userId, $batchId, $academicYear, $termId, $classId, $options = array())
{
    $scopeMeta = isset($options['scope_meta']) && is_array($options['scope_meta'])
        ? $options['scope_meta']
        : tr_terminal_report_fetch_scope_meta($con, $batchId, $academicYear, $termId);
    $positionObj = isset($options['position_obj']) ? $options['position_obj'] : new Position();
    $classPositionObj = isset($options['class_position_obj']) ? $options['class_position_obj'] : new ClassPosition();
    $gradeObj = isset($options['grade_obj']) ? $options['grade_obj'] : new GradingSystem();
    $companyMeta = isset($options['company_meta']) && is_array($options['company_meta'])
        ? $options['company_meta']
        : tr_terminal_report_fetch_company_meta($con);

    $assignmentRows = tr_terminal_report_fetch_assignment_rows($con, $userId, $batchId, $termId, $classId, $academicYear);
    $markMap = tr_terminal_report_fetch_assignment_mark_map($con, $assignmentRows, $userId);
    $terminalRow = tr_terminal_report_fetch_terminal_summary_row($con, $userId, $batchId, $termId);
    $contextRow = !empty($assignmentRows)
        ? $assignmentRows[0]
        : tr_terminal_report_fetch_student_context_row($con, $userId, $batchId, $classId);

    $studentName = trim(
        (string)(isset($contextRow['firstname']) ? $contextRow['firstname'] : '') . ' ' .
        (string)(isset($contextRow['othernames']) ? $contextRow['othernames'] : '') . ' ' .
        (string)(isset($contextRow['surname']) ? $contextRow['surname'] : '')
    );
    if ($studentName === '') {
        $studentName = trim((string)$userId);
    }
    $studentName .= ' (' . trim((string)$userId) . ')';

    $className = trim((string)(isset($contextRow['class_name']) ? $contextRow['class_name'] : ''));
    if ($className === '') {
        $className = tr_terminal_report_fetch_class_label($con, $classId);
    }
    if ($className === '') {
        $className = 'Not Set';
    }

    $batchLabel = trim((string)(isset($scopeMeta['batch_label']) ? $scopeMeta['batch_label'] : ''));
    if ($batchLabel === '') {
        $batchLabel = trim((string)(isset($contextRow['batch']) ? $contextRow['batch'] : ''));
    }
    if ($batchLabel === '') {
        $batchLabel = trim((string)$batchId);
    }

    $semesterLabel = tr_terminal_report_resolve_semester_label($scopeMeta, $terminalRow, $assignmentRows);
    $houseName = tr_terminal_report_fetch_student_house_name($con, $userId);

    $roll = is_array($terminalRow) && isset($terminalRow['roll']) ? $terminalRow['roll'] : 0;
    $attendance = is_array($terminalRow) && isset($terminalRow['attendance']) ? $terminalRow['attendance'] : 0;
    $totalAttendance = is_array($terminalRow) && isset($terminalRow['totalattendance']) ? $terminalRow['totalattendance'] : 0;
    $promotedTo = is_array($terminalRow) && isset($terminalRow['promotedto']) ? $terminalRow['promotedto'] : '';
    $conduct = is_array($terminalRow) && isset($terminalRow['conduct']) ? $terminalRow['conduct'] : '';
    $interest = is_array($terminalRow) && isset($terminalRow['interest']) ? $terminalRow['interest'] : '';
    $classTeacherRemark = is_array($terminalRow) && isset($terminalRow['class_teacher_remark']) ? $terminalRow['class_teacher_remark'] : '';
    $headTeacherRemark = is_array($terminalRow) && isset($terminalRow['head_teacher_remark']) ? $terminalRow['head_teacher_remark'] : '';

    $groupYearPosition = 'Not Ready';
    $classPositionLabel = 'Not Ready';
    $classCount = 0;
    if (!empty($assignmentRows)) {
        $overallScore = tr_terminal_report_fetch_overall_score($con, $userId, $batchId, $termId, $classId, $academicYear);
        $classPositionObj->setClassPosition($batchId, $overallScore, $termId, '', $academicYear, $userId);
        $groupYearPosition = $classPositionObj->getClassPosition();
        $classPositionObj->setClassPosition($batchId, $overallScore, $termId, $classId, $academicYear, $userId);
        $classPositionLabel = $classPositionObj->getClassPosition();
        $classCount = $classPositionObj->getClassCount();
    }

    $logoPath = '';
    if (!empty($companyMeta['logo'])) {
        $candidatePaths = array(
            __DIR__ . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $companyMeta['logo'],
            __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $companyMeta['logo']
        );
        foreach ($candidatePaths as $candidatePath) {
            if (file_exists($candidatePath)) {
                $logoPath = $candidatePath;
                break;
            }
        }
    }
    if ($logoPath === '' && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.png')) {
        $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.png';
    }
    if ($logoPath === '' && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.jpeg')) {
        $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.jpeg';
    }
    if ($logoPath === '' && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.png')) {
        $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.png';
    }
    if ($logoPath === '' && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.jpeg')) {
        $logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.jpeg';
    }

    $widthCell = array(45, 30, 25, 30, 25, 35);
    $lineGap = 7;

    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 18);
    if ($logoPath !== '') {
        $pdf->Image($logoPath, $widthCell[0] + $widthCell[1] + $widthCell[2], 3, 22);
    }
    $pdf->Ln(20);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text(strtoupper((string)$companyMeta['company_name']) . ' - GES'), 0, 0, 'C', true);
    $pdf->Ln($lineGap);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text((string)$companyMeta['address'] . ', ' . (string)$companyMeta['location']), 0, 0, 'C', true);
    $pdf->Ln($lineGap);
    $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text('Tel:' . (string)$companyMeta['telephone1'] . ' ' . (string)$companyMeta['telephone2']), 0, 0, 'C', true);
    $pdf->Ln($lineGap);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text('Group Year Position: ' . $groupYearPosition), 0, 0, 'R', true);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Ln($lineGap);
    $classPositionText = $classPositionLabel;
    if ($classCount > 0 && $classPositionLabel !== 'Not Ready') {
        $classPositionText .= ' / ' . $classCount;
    }
    $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text('Class Position: ' . $classPositionText), 0, 0, 'R', true);
    $pdf->Ln($lineGap);

    $pdf->Cell(70, 5, tr_terminal_report_pdf_text('Name: ' . $studentName), 0, 0, 'L', true);
    $pdf->Ln($lineGap);
    $pdf->Cell($widthCell[0] + $widthCell[1] + $widthCell[2], 10, tr_terminal_report_pdf_text('Class/Form: ' . $className), 0, 0, 'L', true);
    $pdf->Cell($widthCell[3] + $widthCell[4] + $widthCell[5], 10, tr_terminal_report_pdf_text('House: ' . $houseName), 0, 0, 'L', true);
    $pdf->Ln($lineGap);

    $pdf->Cell($widthCell[0] + $widthCell[1] + $widthCell[2], 10, tr_terminal_report_pdf_text('No. On Roll: ' . $roll), 0, 0, 'L', true);
    $pdf->Cell($widthCell[3] + $widthCell[4] + $widthCell[5], 10, tr_terminal_report_pdf_text('Batch: ' . $batchLabel), 0, 0, 'L', true);
    $pdf->Ln($lineGap);

    $pdf->Cell($widthCell[0] + $widthCell[1] + $widthCell[2], 10, tr_terminal_report_pdf_text('School Closes: ' . (string)$scopeMeta['school_closes']), 0, 0, 'L', true);
    $academicYearLabel = trim((string)(isset($scopeMeta['academic_year_label']) ? $scopeMeta['academic_year_label'] : ''));
    $academicYearText = ($academicYearLabel !== '' ? $academicYearLabel : $batchLabel);
    $semesterText = ($semesterLabel !== '' ? $semesterLabel : 'N/A');
    $pdf->Cell($widthCell[3] + $widthCell[4] + $widthCell[5], 10, tr_terminal_report_pdf_text('Academic Year: ' . $academicYearText . ' | Semester: ' . $semesterText), 0, 0, 'L', true);
    $pdf->Ln($lineGap);

    $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text('Next Semester Begins: ' . (string)$scopeMeta['next_term_begins']), 0, 0, 'L', true);
    $pdf->Ln($lineGap);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($widthCell[0], 10, 'SUBJECT', 1, 0, 'C', true);
    $pdf->Cell($widthCell[1], 10, 'CLASS SCORE', 1, 0, 'C', true);
    $pdf->Cell($widthCell[2], 10, 'EXAM SCORE', 1, 0, 'C', true);
    $pdf->Cell($widthCell[3], 10, 'TOTAL SCORE', 1, 0, 'C', true);
    $pdf->Cell($widthCell[4], 10, 'POS IN SUB', 1, 0, 'C', true);
    $pdf->Cell($widthCell[5], 10, 'GRADE', 1, 0, 'C', true);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Ln(10);

    if (empty($assignmentRows)) {
        $pdf->Cell(array_sum($widthCell), 10, tr_terminal_report_pdf_text('No marks entered for this student in the selected scope.'), 1, 0, 'C', true);
        $pdf->Ln(10);
    } else {
        foreach ($assignmentRows as $row) {
            $assignmentId = isset($row['assignmentid']) ? (string)$row['assignmentid'] : '';
            $classScore = isset($markMap[$assignmentId]['class score']) ? (float)$markMap[$assignmentId]['class score'] : 0;
            $examScore = isset($markMap[$assignmentId]['exam score']) ? (float)$markMap[$assignmentId]['exam score'] : 0;
            $totalScore = $classScore + $examScore;
            $positionLabel = '-';
            if ($assignmentId !== '') {
                $positionObj->setPosition($assignmentId, $totalScore);
                $positionLabel = $positionObj->getPosition();
            }
            $gradeObj->setMark($totalScore);
            $gradeLabel = $gradeObj->getMark($totalScore);

            $pdf->Cell($widthCell[0], 10, tr_terminal_report_pdf_text((string)$row['subject']), 1, 0, 'L', true);
            $pdf->Cell($widthCell[1], 10, tr_terminal_report_pdf_text(number_format($classScore, 0)), 1, 0, 'C', true);
            $pdf->Cell($widthCell[2], 10, tr_terminal_report_pdf_text(number_format($examScore, 0)), 1, 0, 'C', true);
            $pdf->Cell($widthCell[3], 10, tr_terminal_report_pdf_text(number_format($totalScore, 0)), 1, 0, 'C', true);
            $pdf->Cell($widthCell[4], 10, tr_terminal_report_pdf_text($positionLabel), 1, 0, 'C', true);
            $pdf->Cell($widthCell[5], 10, tr_terminal_report_pdf_text($gradeLabel), 1, 0, 'C', true);
            $pdf->Ln(10);
        }
    }

    $pdf->Ln(1);
    $pdf->Cell(0, 10, tr_terminal_report_pdf_text('Attendance:........................' . $attendance . '...........................Out of............................ ' . $totalAttendance . '.............................   Promoted to:..................' . $promotedTo), 0, 0, 'L', true);
    $pdf->Ln(7);
    $pdf->Cell(0, 10, tr_terminal_report_pdf_text('Conduct:  ' . $conduct), 0, 0, 'L', true);
    $pdf->Ln(7);
    $pdf->Cell(0, 10, tr_terminal_report_pdf_text('Interest(Special Aptitude):  ' . $interest), 0, 0, 'L', true);
    $pdf->Ln(7);
    $pdf->Cell(0, 10, tr_terminal_report_pdf_text("Class Teacher's Remarks:  " . $classTeacherRemark), 0, 0, 'L', true);
    $pdf->Ln(7);
    $pdf->Cell(0, 10, tr_terminal_report_pdf_text("Head Teacher's Remarks:  " . $headTeacherRemark), 0, 0, 'L', true);
    $pdf->Ln(7);
    $pdf->Cell(0, 10, tr_terminal_report_pdf_text('Signature:................................................'), 0, 0, 'R', true);

    $pdf->Ln(7);
    $pdf->SetFont('Arial', 'U', 8);
    $pdf->Cell(0, 10, 'GRADING(S):', 0, 0, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Ln(6);
    $pdf->Cell($widthCell[0], 10, '1. A1 (80%-100%)', 0, 0, 'L', true);
    $pdf->Cell($widthCell[1], 10, '3. B3 (65%-69%) ', 0, 0, 'L', true);
    $pdf->Cell($widthCell[2] + $widthCell[3], 10, '5. C5 (55%-59%)', 0, 0, 'C', true);
    $pdf->Cell($widthCell[4] + $widthCell[5], 10, '7. D7 (45%-49%)', 0, 0, 'C', true);
    $pdf->Ln(6);
    $pdf->Cell($widthCell[0], 10, '2. B2 (70%-79%)', 0, 0, 'L', true);
    $pdf->Cell($widthCell[1], 10, '4. C4 (60%-64%) ', 0, 0, 'L', true);
    $pdf->Cell($widthCell[2] + $widthCell[3], 10, '6 C6 (50%-54%) ', 0, 0, 'C', true);
    $pdf->Cell($widthCell[4] + $widthCell[5], 10, '8 E8 (40%-44%)', 0, 0, 'C', true);
    $pdf->Ln(6);
    $pdf->Cell($widthCell[1] + $widthCell[2] + $widthCell[3] + $widthCell[4] + $widthCell[5], 10, '9. F9 (0%-39%)', 0, 0, 'L', true);
    $pdf->Ln(6);
}
}

if (!function_exists('tr_terminal_report_output_pdf')) {
function tr_terminal_report_output_pdf($pdf, $fileName)
{
    $fileName = trim((string)$fileName);
    if ($fileName === '') {
        $fileName = 'terminal-report.pdf';
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    $pdf->Output('I', $fileName);
    exit();
}
}

if (!function_exists('tr_terminal_report_print_single_pdf')) {
function tr_terminal_report_print_single_pdf($con, $userId, $batchId, $academicYear, $termId, $classId)
{
    $prepared = tr_terminal_report_prepare_dependencies($con);
    if (empty($prepared['success'])) {
        return $prepared;
    }
    if (trim((string)$userId) === '' || trim((string)$batchId) === '') {
        return array(
            'success' => false,
            'message' => 'Choose a student and the report scope before printing.'
        );
    }

    $pdf = new FPDF();
    $scopeMeta = tr_terminal_report_fetch_scope_meta($con, $batchId, $academicYear, $termId);
    $companyMeta = tr_terminal_report_fetch_company_meta($con);
    tr_terminal_report_render_student_page($pdf, $con, $userId, $batchId, $academicYear, $termId, $classId, array(
        'scope_meta' => $scopeMeta,
        'company_meta' => $companyMeta,
        'position_obj' => new Position(),
        'class_position_obj' => new ClassPosition(),
        'grade_obj' => new GradingSystem()
    ));
    tr_terminal_report_output_pdf($pdf, 'terminal-report.pdf');
    return array('success' => true);
}
}

if (!function_exists('tr_terminal_report_print_scope_pack_pdf')) {
function tr_terminal_report_print_scope_pack_pdf($con, $batchId, $academicYear, $termId, $classId)
{
    $prepared = tr_terminal_report_prepare_dependencies($con);
    if (empty($prepared['success'])) {
        return $prepared;
    }
    if (trim((string)$batchId) === '' || trim((string)$academicYear) === '' || trim((string)$termId) === '' || trim((string)$classId) === '') {
        return array(
            'success' => false,
            'message' => 'Select the batch, academic year, semester, and class before printing the report pack.'
        );
    }

    $students = tr_terminal_report_fetch_scope_students($con, $batchId, $academicYear, $termId, $classId);
    if (empty($students)) {
        return array(
            'success' => false,
            'message' => 'No students were found for the selected batch, academic year, semester, and class.'
        );
    }

    $pdf = new FPDF();
    $scopeMeta = tr_terminal_report_fetch_scope_meta($con, $batchId, $academicYear, $termId);
    $companyMeta = tr_terminal_report_fetch_company_meta($con);
    $sharedOptions = array(
        'scope_meta' => $scopeMeta,
        'company_meta' => $companyMeta,
        'position_obj' => new Position(),
        'class_position_obj' => new ClassPosition(),
        'grade_obj' => new GradingSystem()
    );

    foreach ($students as $studentRow) {
        if (empty($studentRow['userid'])) {
            continue;
        }
        tr_terminal_report_render_student_page(
            $pdf,
            $con,
            (string)$studentRow['userid'],
            $batchId,
            $academicYear,
            $termId,
            $classId,
            $sharedOptions
        );
    }

    $batchLabel = tr_terminal_report_fetch_batch_label($con, $batchId);
    $classLabel = tr_terminal_report_fetch_class_label($con, $classId);
    $fileName = 'terminal-report-pack-'
        . tr_terminal_report_filename_part($batchLabel !== '' ? $batchLabel : $batchId, 'batch')
        . '-'
        . tr_terminal_report_filename_part($academicYear, 'year')
        . '-semester-'
        . tr_terminal_report_filename_part($termId, 'term')
        . '-'
        . tr_terminal_report_filename_part($classLabel !== '' ? $classLabel : $classId, 'class')
        . '.pdf';

    tr_terminal_report_output_pdf($pdf, $fileName);
    return array('success' => true);
}
}
?>

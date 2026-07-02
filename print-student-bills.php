<?php
session_start();
if (!isset($_SESSION['Message'])) {
    $_SESSION['Message'] = "";
}
include("check-login.php");
include("dbstring.php");
include("teacher-billing-utils.php");
ensure_teacher_billing_table($con);
ensure_teacher_billing_item_table($con);
teacher_billing_enforce_page_access($con);

$__teacherBillingUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$__teacherBillingIsAdmin = teacher_billing_is_admin();
$__teacherBillingScopeClasses = teacher_billing_class_options($con);
$__teacherBillingScopeBatches = teacher_billing_batch_options($con);
$__teacherBillingHasScope = (count($__teacherBillingScopeClasses) > 0 && count($__teacherBillingScopeBatches) > 0);

if (!function_exists('psb_esc')) {
    function psb_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('psb_flash_html')) {
    function psb_flash_html($tone, $message)
    {
        $tone = trim((string)$tone);
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

        return "<div class='psb-flash psb-flash--" . $tone . "'><span class='psb-flash__icon'><i class='fa " . $icon . "'></i></span><div class='psb-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists('psb_money')) {
    function psb_money($amount)
    {
        $symbol = isset($_SESSION['SYMBOL']) ? (string)$_SESSION['SYMBOL'] : '';
        return trim($symbol . ' ' . number_format((float)$amount, 2));
    }
}

if (!function_exists('psb_scope_url')) {
    function psb_scope_url($classId = '', $batchId = '')
    {
        $params = array();
        $classId = trim((string)$classId);
        $batchId = trim((string)$batchId);
        if ($classId !== '') {
            $params['class_entryid'] = $classId;
        }
        if ($batchId !== '') {
            $params['batchid'] = $batchId;
        }
        return 'print-student-bills.php' . (!empty($params) ? '?' . http_build_query($params) : '');
    }
}

if (!function_exists('psb_status_badge_html')) {
    function psb_status_badge_html($tone, $label)
    {
        return "<span class='psb-badge psb-badge--" . psb_esc($tone) . "'>" . psb_esc($label) . "</span>";
    }
}

if (!function_exists('psb_fetch_student_row')) {
    function psb_fetch_student_row($con, $studentId)
    {
        $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
        if ($studentIdEsc === '') {
            return null;
        }
        $sql = "SELECT userid, firstname, othernames, surname
            FROM tblsystemuser
            WHERE userid='$studentIdEsc'
            LIMIT 1";
        $res = mysqli_query($con, $sql);
        if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
            return $row;
        }
        return null;
    }
}

if (!function_exists('psb_current_actor_label')) {
    function psb_current_actor_label($con)
    {
        $userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
        if ($userId === '') {
            return '';
        }
        $row = psb_fetch_student_row($con, $userId);
        if (!$row) {
            $userIdEsc = mysqli_real_escape_string($con, $userId);
            $sql = "SELECT userid, firstname, othernames, surname
                FROM tblsystemuser
                WHERE userid='$userIdEsc'
                LIMIT 1";
            $res = mysqli_query($con, $sql);
            if ($res && ($userRow = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
                $row = $userRow;
            }
        }
        if (!$row) {
            return $userId;
        }
        $fullName = trim((string)$row['firstname'] . " " . (string)$row['othernames'] . " " . (string)$row['surname']);
        return trim($fullName . ($fullName !== '' ? ' ' : '') . '(' . (string)$row['userid'] . ')');
    }
}

if (!function_exists('psb_logo_path')) {
    function psb_logo_path($logoName)
    {
        $logoName = trim((string)$logoName);
        $candidates = array();
        if ($logoName !== '') {
            $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $logoName;
            $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $logoName;
        }
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'nexgen-logo.png';
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png';
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return '';
    }
}

if (!function_exists('psb_render_pdf_header')) {
    function psb_render_pdf_header($pdf, $branding, $studentRow, $classLabel, $batchLabel, $termName)
    {
        $logoPath = isset($branding['logo_path']) ? (string)$branding['logo_path'] : '';
        $schoolName = isset($branding['school_name']) ? (string)$branding['school_name'] : '';
        $addressLine = isset($branding['address']) ? (string)$branding['address'] : '';
        $telephoneLine = isset($branding['telephone']) ? (string)$branding['telephone'] : '';

        if ($logoPath !== '') {
            $pdf->Image($logoPath, 14, 10, 24);
        }

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 8, $schoolName, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $addressLine, 0, 1, 'C');
        $pdf->Cell(0, 6, $telephoneLine, 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, 'Student Bills Statement', 0, 1, 'C');
        $pdf->Ln(2);

        $fullName = trim((string)$studentRow['firstname'] . " " . (string)$studentRow['othernames'] . " " . (string)$studentRow['surname']);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 7, 'Student:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(85, 7, $fullName . ' (' . (string)$studentRow['userid'] . ')', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(20, 7, 'Class:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, $classLabel, 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 7, 'Batch:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(85, 7, $batchLabel, 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(20, 7, 'Semester:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, (string)$termName, 0, 1, 'L');
        $pdf->Ln(3);
    }
}

if (!function_exists('psb_render_pdf_table')) {
    function psb_render_pdf_table($pdf, $items, $totalAmount)
    {
        $widths = array(12, 100, 32, 42);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(236, 243, 249);
        $pdf->Cell($widths[0], 9, '#', 1, 0, 'C', true);
        $pdf->Cell($widths[1], 9, 'Item', 1, 0, 'L', true);
        $pdf->Cell($widths[2], 9, 'Amount', 1, 0, 'C', true);
        $pdf->Cell($widths[3], 9, 'Billed On', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $fill = false;
        $rowNumber = 0;
        foreach ($items as $item) {
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 251 : 255, $fill ? 253 : 255);
            $pdf->Cell($widths[0], 8, (string)(++$rowNumber), 1, 0, 'C', true);
            $pdf->Cell($widths[1], 8, (string)$item['itemname'], 1, 0, 'L', true);
            $pdf->Cell($widths[2], 8, psb_money($item['price']), 1, 0, 'C', true);
            $pdf->Cell($widths[3], 8, (string)$item['billed_at'], 1, 1, 'C', true);
            $fill = !$fill;
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($widths[0] + $widths[1], 9, 'Total', 1, 0, 'R', true);
        $pdf->Cell($widths[2], 9, psb_money($totalAmount), 1, 0, 'C', true);
        $pdf->Cell($widths[3], 9, '', 1, 1, 'C', true);
    }
}

if (!function_exists('psb_output_bills_pdf')) {
    function psb_output_bills_pdf($con, $studentIds, $classId, $batchId, $termName, $teacherId)
    {
        include("config.php");
        include("company.php");
        require('fpdf181/fpdf.php');

        $classLabel = '';
        $batchLabel = '';
        $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
        $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
        $termName = (int)$termName;

        $classRes = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$classIdEsc' LIMIT 1");
        if ($classRes && ($classRow = mysqli_fetch_array($classRes, MYSQLI_ASSOC))) {
            $classLabel = (string)$classRow['class_name'];
        }
        $batchRes = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$batchIdEsc' LIMIT 1");
        if ($batchRes && ($batchRow = mysqli_fetch_array($batchRes, MYSQLI_ASSOC))) {
            $batchLabel = (string)$batchRow['batch'];
        }

        $branding = array(
            'logo_path' => psb_logo_path(isset($_Logo) ? $_Logo : ''),
            'school_name' => isset($_CompanyName) ? (string)$_CompanyName : (isset($_SESSION['COMPANYNAME']) ? (string)$_SESSION['COMPANYNAME'] : 'School'),
            'address' => trim((string)(isset($_Address) ? $_Address : '') . ' ' . (string)(isset($_Location) ? $_Location : '')),
            'telephone' => 'TEL: ' . trim((string)(isset($_Telephone1) ? $_Telephone1 : '') . ' / ' . (string)(isset($_Telephone2) ? $_Telephone2 : '')),
        );

        $pdf = new FPDF();
        $pdf->SetAutoPageBreak(true, 18);
        $printActor = psb_current_actor_label($con);
        $printedDate = date('d/m/Y H:i');
        $renderedCount = 0;

        foreach ((array)$studentIds as $studentId) {
            $studentId = trim((string)$studentId);
            if ($studentId === '') {
                continue;
            }
            if (!teacher_billing_student_registered_for_scope($con, $studentId, $classId, $batchId, $termName)) {
                continue;
            }

            $studentRow = psb_fetch_student_row($con, $studentId);
            if (!$studentRow) {
                continue;
            }

            $summary = teacher_billing_student_scope_summary($con, $studentId, $classId, $batchId, $termName, $teacherId);
            if ((int)$summary['billed_items'] <= 0) {
                continue;
            }

            $billedItems = array();
            foreach ($summary['items'] as $itemRow) {
                if (!empty($itemRow['is_billed'])) {
                    $billedItems[] = array(
                        'itemname' => (string)$itemRow['itemname'],
                        'price' => (float)$itemRow['price'],
                        'billed_at' => (string)$itemRow['billed_at'],
                    );
                }
            }
            if (empty($billedItems)) {
                continue;
            }

            $pdf->AddPage();
            psb_render_pdf_header($pdf, $branding, $studentRow, $classLabel, $batchLabel, $termName);
            psb_render_pdf_table($pdf, $billedItems, $summary['billed_amount']);
            $pdf->Ln(8);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 6, 'Printed on: ' . $printedDate, 0, 1, 'L');
            if ($printActor !== '') {
                $pdf->Cell(0, 6, 'Printed by: ' . $printActor, 0, 1, 'L');
            }
            $pdf->Cell(0, 6, 'Only billed items in this class, batch, and semester are included.', 0, 1, 'L');
            $renderedCount++;
        }

        if ($renderedCount <= 0) {
            return false;
        }

        $fileName = 'student-bills-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', trim($classLabel . '-' . $batchLabel . '-term-' . $termName)) . '.pdf';
        $pdf->Output('I', $fileName);
        return true;
    }
}

$classOptionsById = array();
foreach ($__teacherBillingScopeClasses as $classRow) {
    $classOptionsById[(string)$classRow['class_entryid']] = $classRow;
}
$batchOptionsById = array();
foreach ($__teacherBillingScopeBatches as $batchRow) {
    $batchOptionsById[(string)$batchRow['batchid']] = $batchRow;
}

$selectedClassId = isset($_REQUEST['class_entryid']) ? trim((string)$_REQUEST['class_entryid']) : '';
$selectedBatchId = isset($_REQUEST['batchid']) ? trim((string)$_REQUEST['batchid']) : '';
$selectedClassLabel = isset($classOptionsById[$selectedClassId]) ? (string)$classOptionsById[$selectedClassId]['class_name'] : '';
$selectedBatchLabel = isset($batchOptionsById[$selectedBatchId]) ? (string)$batchOptionsById[$selectedBatchId]['batch'] : '';
$scopeIsSelected = ($selectedClassId !== '' && $selectedBatchId !== '');
$scopeIsAllowed = true;
$pageMessage = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
$_SESSION['Message'] = "";

if (isset($_GET['user_id']) && isset($_GET['term_id'])) {
    $printStudentId = trim((string)$_GET['user_id']);
    $printClassId = trim((string)(isset($_GET['class_entryid']) ? $_GET['class_entryid'] : ''));
    $printBatchId = trim((string)(isset($_GET['batch_id']) ? $_GET['batch_id'] : ''));
    $printTermId = (int)(isset($_GET['term_id']) ? $_GET['term_id'] : 0);

    if ($printStudentId === '' || $printClassId === '' || $printBatchId === '' || $printTermId <= 0) {
        $_SESSION['Message'] = psb_flash_html('error', 'The requested print scope is incomplete.');
        header('location:' . psb_scope_url($printClassId, $printBatchId));
        exit();
    }

    teacher_billing_enforce_scope_or_redirect($con, $printClassId, $printBatchId, $printTermId);
    if (!psb_output_bills_pdf($con, array($printStudentId), $printClassId, $printBatchId, $printTermId, $__teacherBillingUserId)) {
        $_SESSION['Message'] = psb_flash_html('warning', 'No billed items were found for that student scope.');
        header('location:' . psb_scope_url($printClassId, $printBatchId));
        exit();
    }
    exit();
}

if (isset($_POST['print_selected_bills'])) {
    $selectedClassId = isset($_POST['class_entryid']) ? trim((string)$_POST['class_entryid']) : $selectedClassId;
    $selectedBatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : $selectedBatchId;
    $selectedTermId = isset($_POST['termname']) ? (int)$_POST['termname'] : 0;
    $selectedStudentIds = isset($_POST['student_userids']) && is_array($_POST['student_userids']) ? $_POST['student_userids'] : array();

    if ($selectedClassId === '' || $selectedBatchId === '' || $selectedTermId <= 0) {
        $_SESSION['Message'] = psb_flash_html('error', 'Choose the class, batch, and semester before printing.');
        header('location:' . psb_scope_url($selectedClassId, $selectedBatchId) . '#print-student-bills-results');
        exit();
    }

    teacher_billing_enforce_scope_or_redirect($con, $selectedClassId, $selectedBatchId, $selectedTermId);

    $filteredStudentIds = array();
    foreach ($selectedStudentIds as $studentId) {
        $studentId = trim((string)$studentId);
        if ($studentId !== '') {
            $filteredStudentIds[$studentId] = $studentId;
        }
    }
    $filteredStudentIds = array_values($filteredStudentIds);

    if (empty($filteredStudentIds)) {
        $_SESSION['Message'] = psb_flash_html('warning', 'Select at least one student before printing.');
        header('location:' . psb_scope_url($selectedClassId, $selectedBatchId) . '#print-student-bills-results');
        exit();
    }

    if (!psb_output_bills_pdf($con, $filteredStudentIds, $selectedClassId, $selectedBatchId, $selectedTermId, $__teacherBillingUserId)) {
        $_SESSION['Message'] = psb_flash_html('warning', 'No billed items were found for the selected students in that semester.');
        header('location:' . psb_scope_url($selectedClassId, $selectedBatchId) . '#print-student-bills-results');
        exit();
    }
    exit();
}

if ($scopeIsSelected && !$__teacherBillingIsAdmin && !teacher_billing_is_assigned_pair($con, $__teacherBillingUserId, $selectedClassId, $selectedBatchId)) {
    $scopeIsAllowed = false;
    $pageMessage = psb_flash_html('error', 'You are not assigned billing access for that class and batch.');
}

$studentCards = array();
$printableTermOptions = array();
$visibleStudentCount = 0;
$printableStudentCount = 0;
$printableScopeCount = 0;
$totalPrintedAmount = 0.0;
$scopeBillableAmount = 0.0;

if ($scopeIsSelected && $scopeIsAllowed) {
    $allowedTerms = $__teacherBillingIsAdmin
        ? array()
        : teacher_billing_terms_for_pair($con, $__teacherBillingUserId, $selectedClassId, $selectedBatchId);

    $studentRows = array();
    $studentIds = array();
    $selectedClassIdEsc = mysqli_real_escape_string($con, $selectedClassId);
    $selectedBatchIdEsc = mysqli_real_escape_string($con, $selectedBatchId);

    $studentSql = "SELECT DISTINCT su.userid, su.firstname, su.othernames, su.surname
        FROM tblsystemuser su
        INNER JOIN tblclass cl ON su.userid=cl.userid
        WHERE cl.class_entryid='$selectedClassIdEsc'
          AND cl.batchid='$selectedBatchIdEsc'
          AND cl.status='active'
          AND su.systemtype='Student'
        ORDER BY su.firstname ASC, su.surname ASC, su.othernames ASC";
    $studentRes = mysqli_query($con, $studentSql);
    if ($studentRes) {
        while ($studentRow = mysqli_fetch_array($studentRes, MYSQLI_ASSOC)) {
            $studentRows[] = $studentRow;
            $studentIds[] = (string)$studentRow['userid'];
        }
    }

    $studentTermsById = array();
    if (!empty($studentIds)) {
        $studentIdsSql = array();
        foreach ($studentIds as $studentId) {
            $studentIdsSql[] = "'" . mysqli_real_escape_string($con, $studentId) . "'";
        }
        $termSql = "SELECT DISTINCT userid, termname
            FROM tbltermregistry
            WHERE class_entryid='$selectedClassIdEsc'
              AND batchid='$selectedBatchIdEsc'
              AND userid IN (" . implode(",", $studentIdsSql) . ")
            ORDER BY termname ASC";
        $termRes = mysqli_query($con, $termSql);
        if ($termRes) {
            while ($termRow = mysqli_fetch_array($termRes, MYSQLI_ASSOC)) {
                $studentId = (string)$termRow['userid'];
                $termName = (int)$termRow['termname'];
                if ($termName <= 0) {
                    continue;
                }
                if (!$__teacherBillingIsAdmin && !in_array($termName, $allowedTerms, true)) {
                    continue;
                }
                if (!isset($studentTermsById[$studentId])) {
                    $studentTermsById[$studentId] = array();
                }
                $studentTermsById[$studentId][$termName] = $termName;
            }
        }
    }

    foreach ($studentRows as $studentRow) {
        $studentId = (string)$studentRow['userid'];
        $studentFullName = trim((string)$studentRow['firstname'] . " " . (string)$studentRow['othernames'] . " " . (string)$studentRow['surname']);
        $studentScopes = array();
        $studentPrintableTerms = array();
        $studentPrintedAmount = 0.0;
        $studentPendingAmount = 0.0;

        if (isset($studentTermsById[$studentId])) {
            $studentTerms = array_values($studentTermsById[$studentId]);
            sort($studentTerms);
            foreach ($studentTerms as $termName) {
                $summary = teacher_billing_student_scope_summary($con, $studentId, $selectedClassId, $selectedBatchId, $termName, $__teacherBillingUserId);
                if (!$summary['has_billable_items']) {
                    continue;
                }

                $studentPrintedAmount += (float)$summary['billed_amount'];
                $studentPendingAmount += (float)$summary['pending_amount'];
                $scopeBillableAmount += (float)$summary['total_amount'];

                if ((int)$summary['billed_items'] > 0) {
                    $studentPrintableTerms[$termName] = $termName;
                    $printableTermOptions[$termName] = $termName;
                    $printableScopeCount++;
                    $totalPrintedAmount += (float)$summary['billed_amount'];
                }

                $studentScopes[] = array(
                    'termname' => $termName,
                    'total_items' => (int)$summary['total_items'],
                    'billed_items' => (int)$summary['billed_items'],
                    'pending_items' => (int)$summary['pending_items'],
                    'billed_amount' => (float)$summary['billed_amount'],
                    'pending_amount' => (float)$summary['pending_amount'],
                    'latest_billed_at' => (string)$summary['latest_billed_at'],
                );
            }
        }

        if (!empty($studentScopes)) {
            $visibleStudentCount++;
            if (!empty($studentPrintableTerms)) {
                $printableStudentCount++;
            }
            $studentSearch = strtolower(trim($studentFullName . " " . $studentId . " " . $selectedClassLabel . " " . $selectedBatchLabel));
            $studentCards[] = array(
                'userid' => $studentId,
                'fullname' => $studentFullName !== '' ? $studentFullName : $studentId,
                'search' => $studentSearch,
                'scopes' => $studentScopes,
                'printable_terms' => array_values($studentPrintableTerms),
                'printed_amount' => $studentPrintedAmount,
                'pending_amount' => $studentPendingAmount,
            );
        }
    }

    ksort($printableTermOptions);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/print-student-bills.css">
<script src="scripts/print-student-bills.js" defer></script>
</head>
<body class="print-student-bills-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="psb-shell">
        <section class="psb-hero">
            <div class="psb-hero__copy">
                <span class="psb-kicker"><i class="fa fa-print"></i> Print Student Bills</span>
                <h1>Print Student Bill Statements</h1>
                <p>Select a class and batch, review billed semesters, and print statements for students who already have billed items.</p>
                <div class="psb-hero__chips">
                    <span class="psb-chip"><i class="fa fa-building"></i> Scope: <?php echo $scopeIsSelected ? psb_esc($selectedClassLabel . ' / ' . $selectedBatchLabel) : 'Select a class and batch'; ?></span>
                    <span class="psb-chip"><i class="fa fa-filter"></i> Teacher item filters respected</span>
                    <span class="psb-chip"><i class="fa fa-file-pdf-o"></i> Single and bulk print</span>
                </div>
            </div>
            <div class="psb-stats">
                <article class="psb-stat">
                    <span>Students Visible</span>
                    <strong><?php echo number_format((int)$visibleStudentCount); ?></strong>
                    <small>Students with billable semester scopes in the selected class and batch.</small>
                </article>
                <article class="psb-stat">
                    <span>Printable Students</span>
                    <strong><?php echo number_format((int)$printableStudentCount); ?></strong>
                    <small>Students with at least one semester that already has billed items.</small>
                </article>
                <article class="psb-stat">
                    <span>Printable Scopes</span>
                    <strong><?php echo number_format((int)$printableScopeCount); ?></strong>
                    <small>Student-semester scopes that can be printed right now.</small>
                </article>
                <article class="psb-stat psb-stat--accent">
                    <span>Printed Amount</span>
                    <strong><?php echo psb_esc(psb_money($totalPrintedAmount)); ?></strong>
                    <small>Total billed amount across the printable semester scopes currently visible.</small>
                </article>
            </div>
        </section>

        <div class="psb-layout">
            <aside class="psb-sidebar">
                <section class="psb-surface">
                    <div class="psb-panel-head">
                        <div>
                            <span class="psb-panel-kicker">Scope Filters</span>
                            <h2>Choose Class and Batch</h2>
                            <p>Select the class and batch you want to review. Only billed semesters will be available for printing.</p>
                        </div>
                    </div>

                    <?php if ($pageMessage !== '') { ?>
                    <div class="psb-message-stack"><?php echo $pageMessage; ?></div>
                    <?php } ?>

                    <?php if (!$__teacherBillingIsAdmin && !$__teacherBillingHasScope) { ?>
                    <div class="psb-inline-note psb-inline-note--warning">
                        <i class="fa fa-info-circle"></i>
                        <span>No billing class has been assigned yet. Ask admin to open <strong>Teacher Billing Assignment</strong> and assign your class, batch, and semester.</span>
                    </div>
                    <?php } ?>

                    <form method="get" action="print-student-bills.php" class="psb-form">
                        <div class="psb-form-grid">
                            <label class="psb-field">
                                <span>Class</span>
                                <select id="class_entryid" name="class_entryid">
                                    <option value="">Select Class</option>
                                    <?php foreach ($__teacherBillingScopeClasses as $classOption) { ?>
                                    <option value="<?php echo psb_esc($classOption['class_entryid']); ?>"<?php echo ($selectedClassId === (string)$classOption['class_entryid'] ? ' selected' : ''); ?>><?php echo psb_esc($classOption['class_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="psb-field">
                                <span>Batch</span>
                                <select id="batchid" name="batchid">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($__teacherBillingScopeBatches as $batchOption) { ?>
                                    <option value="<?php echo psb_esc($batchOption['batchid']); ?>"<?php echo ($selectedBatchId === (string)$batchOption['batchid'] ? ' selected' : ''); ?>><?php echo psb_esc($batchOption['batch']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>

                        <div class="psb-actions">
                            <button class="psb-btn psb-btn--primary" type="submit"><i class="fa fa-search"></i> Show Bills</button>
                            <a class="psb-btn psb-btn--secondary" href="print-student-bills.php"><i class="fa fa-undo"></i> Clear</a>
                        </div>
                    </form>
                </section>
            </aside>

            <section class="psb-main" id="print-student-bills-results">
                <section class="psb-surface">
                    <div class="psb-panel-head">
                        <div>
                            <span class="psb-panel-kicker">Print List</span>
                            <h2>Printable Student Bills</h2>
                            <p>Review each student's billed and pending amount by semester, then print single or bulk statements for billed semesters.</p>
                        </div>
                        <span class="psb-panel-tag"><i class="fa fa-users"></i> <?php echo number_format((int)$printableStudentCount); ?> printable student<?php echo ($printableStudentCount === 1 ? '' : 's'); ?></span>
                    </div>

                    <?php if ($scopeIsSelected && $scopeIsAllowed && !empty($studentCards)) { ?>
                    <form method="post" action="print-student-bills.php?class_entryid=<?php echo urlencode($selectedClassId); ?>&batchid=<?php echo urlencode($selectedBatchId); ?>#print-student-bills-results">
                        <input type="hidden" name="class_entryid" value="<?php echo psb_esc($selectedClassId); ?>">
                        <input type="hidden" name="batchid" value="<?php echo psb_esc($selectedBatchId); ?>">

                        <div class="psb-toolbar">
                            <label class="psb-search">
                                <i class="fa fa-search"></i>
                                <input type="search" placeholder="Search student name or ID" data-student-search>
                            </label>
                            <label class="psb-term-select">
                                <span>Bulk print semester</span>
                                <select name="termname" data-bulk-term>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($printableTermOptions as $termOption) { ?>
                                    <option value="<?php echo (int)$termOption; ?>">Semester <?php echo (int)$termOption; ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>

                        <div class="psb-bulk-bar">
                            <div class="psb-bulk-bar__copy">
                                <strong data-selected-count>0 student selected</strong>
                                <span>Bulk print uses the chosen semester above. Students without billed items in that semester are disabled automatically.</span>
                            </div>
                            <div class="psb-actions">
                                <button class="psb-btn psb-btn--secondary" type="button" data-select-visible><i class="fa fa-check-square-o"></i> Select Visible</button>
                                <button class="psb-btn psb-btn--secondary" type="button" data-clear-visible><i class="fa fa-square-o"></i> Clear</button>
                                <button class="psb-btn psb-btn--primary" type="submit" name="print_selected_bills"><i class="fa fa-print"></i> Print Selected Bills</button>
                            </div>
                        </div>

                        <div class="psb-student-grid">
                            <?php foreach ($studentCards as $studentCard) {
                                $printableTermsAttr = implode(',', $studentCard['printable_terms']);
                                $isPrintableStudent = !empty($studentCard['printable_terms']);
                            ?>
                            <article class="psb-student-card" data-student-card data-search="<?php echo psb_esc($studentCard['search']); ?>" data-printable-terms="<?php echo psb_esc($printableTermsAttr); ?>">
                                <div class="psb-student-card__head">
                                    <div class="psb-student-card__select">
                                        <input type="checkbox" name="student_userids[]" value="<?php echo psb_esc($studentCard['userid']); ?>" data-student-checkbox<?php echo $isPrintableStudent ? '' : ' disabled'; ?>>
                                    </div>
                                    <div class="psb-student-card__identity">
                                        <h3><?php echo psb_esc($studentCard['fullname']); ?></h3>
                                        <p><?php echo psb_esc($studentCard['userid']); ?></p>
                                    </div>
                                    <div class="psb-student-card__meta">
                                        <?php echo $isPrintableStudent ? psb_status_badge_html('success', number_format(count($studentCard['printable_terms'])) . ' printable term' . (count($studentCard['printable_terms']) === 1 ? '' : 's')) : psb_status_badge_html('warning', 'No printed bills yet'); ?>
                                        <span class="psb-student-card__amount"><?php echo psb_esc(psb_money($studentCard['printed_amount'])); ?> billed</span>
                                    </div>
                                </div>

                                <div class="psb-scope-list">
                                    <?php foreach ($studentCard['scopes'] as $scope) { ?>
                                    <section class="psb-scope-card">
                                        <div class="psb-scope-card__head">
                                            <div>
                                                <h4>Semester <?php echo (int)$scope['termname']; ?></h4>
                                                <p><?php echo number_format((int)$scope['billed_items']); ?> billed item<?php echo ($scope['billed_items'] === 1 ? '' : 's'); ?>, <?php echo number_format((int)$scope['pending_items']); ?> pending</p>
                                            </div>
                                            <div class="psb-scope-card__status">
                                                <?php
                                                if ((int)$scope['billed_items'] > 0) {
                                                    echo psb_status_badge_html('success', 'Ready to print');
                                                } else {
                                                    echo psb_status_badge_html('warning', 'Nothing printed yet');
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <div class="psb-scope-stats">
                                            <div class="psb-scope-stat">
                                                <span>Billed</span>
                                                <strong><?php echo psb_esc(psb_money($scope['billed_amount'])); ?></strong>
                                            </div>
                                            <div class="psb-scope-stat">
                                                <span>Pending</span>
                                                <strong><?php echo psb_esc(psb_money($scope['pending_amount'])); ?></strong>
                                            </div>
                                            <div class="psb-scope-stat">
                                                <span>Items</span>
                                                <strong><?php echo number_format((int)$scope['billed_items']); ?> / <?php echo number_format((int)$scope['total_items']); ?></strong>
                                            </div>
                                            <div class="psb-scope-stat">
                                                <span>Last Billed</span>
                                                <strong><?php echo $scope['latest_billed_at'] !== '' ? psb_esc($scope['latest_billed_at']) : 'Not yet'; ?></strong>
                                            </div>
                                        </div>

                                        <div class="psb-scope-actions">
                                            <?php if ((int)$scope['billed_items'] > 0) { ?>
                                            <a class="psb-btn psb-btn--primary" target="_blank" href="print-student-bills.php?user_id=<?php echo urlencode($studentCard['userid']); ?>&class_entryid=<?php echo urlencode($selectedClassId); ?>&batch_id=<?php echo urlencode($selectedBatchId); ?>&term_id=<?php echo (int)$scope['termname']; ?>">
                                                <i class="fa fa-print"></i> Print Semester Bill
                                            </a>
                                            <?php } else { ?>
                                            <button class="psb-btn psb-btn--secondary" type="button" disabled><i class="fa fa-ban"></i> Nothing To Print</button>
                                            <?php } ?>
                                        </div>
                                    </section>
                                    <?php } ?>
                                </div>
                            </article>
                            <?php } ?>
                        </div>
                    </form>

                    <div class="psb-empty-state psb-empty-state--inline" data-student-empty hidden>
                        <h3>No students match this search</h3>
                        <p>Try a different student name or clear the search box.</p>
                    </div>
                    <?php } elseif ($scopeIsSelected && $scopeIsAllowed) { ?>
                    <div class="psb-empty-state">
                        <h3>No billable student scopes were found</h3>
                        <p>The selected class and batch do not currently have student billing scopes available for printing.</p>
                    </div>
                    <?php } else { ?>
                    <div class="psb-empty-state">
                        <h3>Select a class and batch to begin</h3>
                        <p>Once you choose a scope, this page will show each student's billed semesters and print-ready totals.</p>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>

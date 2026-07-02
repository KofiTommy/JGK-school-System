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

if (!function_exists('sb_esc')) {
    function sb_esc($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sb_flash_html')) {
    function sb_flash_html($tone, $message)
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

        return "<div class='sb-flash sb-flash--" . $tone . "'><span class='sb-flash__icon'><i class='fa " . $icon . "'></i></span><div class='sb-flash__body'>" . $message . "</div></div>";
    }
}

if (!function_exists('sb_money')) {
    function sb_money($amount)
    {
        $symbol = isset($_SESSION['SYMBOL']) ? (string)$_SESSION['SYMBOL'] : '';
        return trim($symbol . ' ' . number_format((float)$amount, 2));
    }
}

if (!function_exists('sb_status_badge_html')) {
    function sb_status_badge_html($tone, $label)
    {
        $tone = trim((string)$tone);
        return "<span class='sb-badge sb-badge--" . sb_esc($tone) . "'>" . sb_esc($label) . "</span>";
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

if (isset($_POST['bill_scope'])) {
    $selectedClassId = isset($_POST['class_entryid']) ? trim((string)$_POST['class_entryid']) : $selectedClassId;
    $selectedBatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : $selectedBatchId;
    $billStudentId = isset($_POST['student_userid']) ? trim((string)$_POST['student_userid']) : '';
    $billTermName = isset($_POST['termname']) ? (int)$_POST['termname'] : 0;

    if (!$__teacherBillingIsAdmin && !teacher_billing_is_assigned($con, $__teacherBillingUserId, $selectedClassId, $selectedBatchId, $billTermName)) {
        $_SESSION['Message'] = sb_flash_html('error', 'You are not assigned billing access for that class, batch, and semester.');
    } else {
        $billResult = teacher_billing_bill_student_scope(
            $con,
            $billStudentId,
            $selectedClassId,
            $selectedBatchId,
            $billTermName,
            $__teacherBillingUserId,
            $__teacherBillingUserId
        );

        $studentLabel = $billStudentId !== '' ? "<strong>" . sb_esc($billStudentId) . "</strong>" : 'this student';
        if ($billResult['tone'] === 'success') {
            $_SESSION['Message'] = sb_flash_html(
                'success',
                "Billing completed for " . $studentLabel . ". Added <strong>" . number_format((int)$billResult['inserted_count']) . "</strong> item(s) worth <strong>" . sb_esc(sb_money($billResult['inserted_amount'])) . "</strong>." .
                ($billResult['transactionid'] !== '' ? " Transaction: <strong>" . sb_esc($billResult['transactionid']) . "</strong>." : '')
            );
        } elseif ($billResult['tone'] === 'warning') {
            $_SESSION['Message'] = sb_flash_html(
                'warning',
                $billResult['message'] .
                ($billResult['inserted_count'] > 0 ? " Added <strong>" . number_format((int)$billResult['inserted_count']) . "</strong> item(s)." : "")
            );
        } elseif ($billResult['tone'] === 'info') {
            $_SESSION['Message'] = sb_flash_html('info', $billResult['message']);
        } else {
            $_SESSION['Message'] = sb_flash_html('error', $billResult['message']);
        }
    }

    $redirectParams = array();
    if ($selectedClassId !== '') {
        $redirectParams['class_entryid'] = $selectedClassId;
    }
    if ($selectedBatchId !== '') {
        $redirectParams['batchid'] = $selectedBatchId;
    }
    header("location:student-billing.php" . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '') . '#student-billing-results');
    exit();
}

if ($scopeIsSelected && !$__teacherBillingIsAdmin && !teacher_billing_is_assigned_pair($con, $__teacherBillingUserId, $selectedClassId, $selectedBatchId)) {
    $scopeIsAllowed = false;
    $pageMessage = sb_flash_html('error', 'You are not assigned billing access for that class and batch.');
}

$studentCards = array();
$termItemRowsByTerm = array();
$visibleScopeCount = 0;
$visibleStudentCount = 0;
$totalBillableAmount = 0.0;
$totalBilledAmount = 0.0;
$totalPendingAmount = 0.0;
$studentsWithPendingScopes = 0;

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

    $allTerms = array();
    foreach ($studentTermsById as $studentTermSet) {
        foreach ($studentTermSet as $termName) {
            $allTerms[$termName] = $termName;
        }
    }
    ksort($allTerms);

    $allItemPriceIds = array();
    foreach ($allTerms as $termName) {
        $termItemRows = teacher_billing_scope_itemprice_rows_for_user(
            $con,
            $__teacherBillingUserId,
            $selectedClassId,
            $selectedBatchId,
            $termName
        );
        if (!empty($termItemRows)) {
            $termItemRowsByTerm[$termName] = $termItemRows;
            foreach ($termItemRows as $termItemRow) {
                $itemPriceId = trim((string)($termItemRow['itempriceid'] ?? ''));
                if ($itemPriceId !== '') {
                    $allItemPriceIds[$itemPriceId] = $itemPriceId;
                }
            }
        }
    }

    $billedByStudent = array();
    if (!empty($studentIds) && !empty($allItemPriceIds)) {
        $studentIdsSql = array();
        foreach ($studentIds as $studentId) {
            $studentIdsSql[] = "'" . mysqli_real_escape_string($con, $studentId) . "'";
        }
        $itemIdsSql = array();
        foreach ($allItemPriceIds as $itemPriceId) {
            $itemIdsSql[] = "'" . mysqli_real_escape_string($con, $itemPriceId) . "'";
        }
        $billedSql = "SELECT userid, itempriceid, datetimebilled
            FROM tblbilling
            WHERE userid IN (" . implode(",", $studentIdsSql) . ")
              AND itempriceid IN (" . implode(",", $itemIdsSql) . ")";
        $billedRes = mysqli_query($con, $billedSql);
        if ($billedRes) {
            while ($billedRow = mysqli_fetch_array($billedRes, MYSQLI_ASSOC)) {
                $studentId = (string)$billedRow['userid'];
                $itemPriceId = (string)$billedRow['itempriceid'];
                if (!isset($billedByStudent[$studentId])) {
                    $billedByStudent[$studentId] = array();
                }
                $billedByStudent[$studentId][$itemPriceId] = $billedRow;
            }
        }
    }

    foreach ($studentRows as $studentRow) {
        $studentId = (string)$studentRow['userid'];
        $studentFullName = trim((string)$studentRow['firstname'] . " " . (string)$studentRow['othernames'] . " " . (string)$studentRow['surname']);
        $studentScopes = array();
        $studentPendingAmount = 0.0;
        $studentPendingScopes = 0;
        $studentBillableAmount = 0.0;
        $studentBilledAmount = 0.0;

        if (isset($studentTermsById[$studentId])) {
            $studentTerms = array_values($studentTermsById[$studentId]);
            sort($studentTerms);
            foreach ($studentTerms as $termName) {
                if (!isset($termItemRowsByTerm[$termName])) {
                    continue;
                }

                $scopeItems = array();
                $totalItems = 0;
                $billedItems = 0;
                $pendingItems = 0;
                $scopeTotalAmount = 0.0;
                $scopeBilledAmount = 0.0;
                $scopePendingAmount = 0.0;
                $latestBilledAt = '';

                foreach ($termItemRowsByTerm[$termName] as $itemRow) {
                    $itemPriceId = trim((string)($itemRow['itempriceid'] ?? ''));
                    $price = isset($itemRow['price']) ? (float)$itemRow['price'] : 0.0;
                    $billRow = isset($billedByStudent[$studentId][$itemPriceId]) ? $billedByStudent[$studentId][$itemPriceId] : null;
                    $isBilled = is_array($billRow);
                    $billedAt = $isBilled ? trim((string)($billRow['datetimebilled'] ?? '')) : '';

                    $totalItems++;
                    $scopeTotalAmount += $price;
                    if ($isBilled) {
                        $billedItems++;
                        $scopeBilledAmount += $price;
                        if ($billedAt !== '' && ($latestBilledAt === '' || strcmp($billedAt, $latestBilledAt) > 0)) {
                            $latestBilledAt = $billedAt;
                        }
                    } else {
                        $pendingItems++;
                        $scopePendingAmount += $price;
                    }

                    $scopeItems[] = array(
                        'itemname' => (string)$itemRow['itemname'],
                        'price' => $price,
                        'is_billed' => $isBilled,
                        'billed_at' => $billedAt,
                    );
                }

                if ($totalItems <= 0) {
                    continue;
                }

                if ($pendingItems > 0) {
                    $studentPendingScopes++;
                }
                $studentPendingAmount += $scopePendingAmount;
                $studentBillableAmount += $scopeTotalAmount;
                $studentBilledAmount += $scopeBilledAmount;

                $studentScopes[] = array(
                    'termname' => $termName,
                    'items' => $scopeItems,
                    'total_items' => $totalItems,
                    'billed_items' => $billedItems,
                    'pending_items' => $pendingItems,
                    'total_amount' => $scopeTotalAmount,
                    'billed_amount' => $scopeBilledAmount,
                    'pending_amount' => $scopePendingAmount,
                    'latest_billed_at' => $latestBilledAt,
                );
            }
        }

        if (!empty($studentScopes)) {
            $studentSearch = strtolower(trim($studentFullName . " " . $studentId . " " . $selectedClassLabel . " " . $selectedBatchLabel));
            $studentCards[] = array(
                'userid' => $studentId,
                'fullname' => $studentFullName !== '' ? $studentFullName : $studentId,
                'search' => $studentSearch,
                'scopes' => $studentScopes,
                'pending_amount' => $studentPendingAmount,
                'pending_scope_count' => $studentPendingScopes,
                'total_amount' => $studentBillableAmount,
                'billed_amount' => $studentBilledAmount,
            );

            $visibleStudentCount++;
            $visibleScopeCount += count($studentScopes);
            $totalBillableAmount += $studentBillableAmount;
            $totalBilledAmount += $studentBilledAmount;
            $totalPendingAmount += $studentPendingAmount;
            if ($studentPendingScopes > 0) {
                $studentsWithPendingScopes++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/student-billing.css">
<script src="scripts/student-billing.js" defer></script>
</head>
<body class="student-billing-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <main class="sb-shell">
        <section class="sb-hero">
            <div class="sb-hero__copy">
                <span class="sb-kicker"><i class="fa fa-credit-card"></i> Student Billing</span>
                <h1>Bill Students by Class and Batch</h1>
                <p>Select a class and batch, review each student's semester billing summary, and bill only the pending items.</p>
                <div class="sb-hero__chips">
                    <span class="sb-chip"><i class="fa fa-building"></i> Scope: <?php echo $scopeIsSelected ? sb_esc($selectedClassLabel . ' / ' . $selectedBatchLabel) : 'Select a class and batch'; ?></span>
                    <span class="sb-chip"><i class="fa fa-lock"></i> Assigned scope enforcement</span>
                    <span class="sb-chip"><i class="fa fa-print"></i> Per-scope bill print</span>
                </div>
            </div>
            <div class="sb-stats">
                <article class="sb-stat">
                    <span>Students Visible</span>
                    <strong><?php echo number_format((int)$visibleStudentCount); ?></strong>
                    <small>Students with at least one billable semester in the selected scope.</small>
                </article>
                <article class="sb-stat">
                    <span>Billing Scopes</span>
                    <strong><?php echo number_format((int)$visibleScopeCount); ?></strong>
                    <small>Student-semester billing summaries currently on the page.</small>
                </article>
                <article class="sb-stat">
                    <span>Billed Amount</span>
                    <strong><?php echo sb_esc(sb_money($totalBilledAmount)); ?></strong>
                    <small>Total billed amount across the visible student scopes.</small>
                </article>
                <article class="sb-stat sb-stat--accent">
                    <span>Pending Amount</span>
                    <strong><?php echo sb_esc(sb_money($totalPendingAmount)); ?></strong>
                    <small><?php echo number_format((int)$studentsWithPendingScopes); ?> student<?php echo ($studentsWithPendingScopes === 1 ? '' : 's'); ?> still have pending bill items.</small>
                </article>
            </div>
        </section>

        <div class="sb-layout">
            <aside class="sb-sidebar">
                <section class="sb-surface">
                    <div class="sb-panel-head">
                        <div>
                            <span class="sb-panel-kicker">Scope Filters</span>
                            <h2>Choose Class and Batch</h2>
                            <p>Select the class and batch you want to bill. The results will show each student's semester billing summary for that scope.</p>
                        </div>
                    </div>

                    <?php if ($pageMessage !== '') { ?>
                    <div class="sb-message-stack"><?php echo $pageMessage; ?></div>
                    <?php } ?>

                    <?php if (!$__teacherBillingIsAdmin && !$__teacherBillingHasScope) { ?>
                    <div class="sb-inline-note sb-inline-note--warning">
                        <i class="fa fa-info-circle"></i>
                        <span>No billing class has been assigned yet. Ask admin to open <strong>Teacher Billing Assignment</strong> and assign your class, batch, and semester.</span>
                    </div>
                    <?php } ?>

                    <form method="get" action="student-billing.php" class="sb-form">
                        <div class="sb-form-grid">
                            <label class="sb-field">
                                <span>Class</span>
                                <select id="class_entryid" name="class_entryid">
                                    <option value="">Select Class</option>
                                    <?php foreach ($__teacherBillingScopeClasses as $classOption) { ?>
                                    <option value="<?php echo sb_esc($classOption['class_entryid']); ?>"<?php echo ($selectedClassId === (string)$classOption['class_entryid'] ? ' selected' : ''); ?>><?php echo sb_esc($classOption['class_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="sb-field">
                                <span>Batch</span>
                                <select id="batchid" name="batchid">
                                    <option value="">Select Batch</option>
                                    <?php foreach ($__teacherBillingScopeBatches as $batchOption) { ?>
                                    <option value="<?php echo sb_esc($batchOption['batchid']); ?>"<?php echo ($selectedBatchId === (string)$batchOption['batchid'] ? ' selected' : ''); ?>><?php echo sb_esc($batchOption['batch']); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </div>

                        <div class="sb-actions">
                            <button class="sb-btn sb-btn--primary" type="submit"><i class="fa fa-search"></i> Show Students</button>
                            <a class="sb-btn sb-btn--secondary" href="student-billing.php"><i class="fa fa-undo"></i> Clear</a>
                        </div>
                    </form>
                </section>
            </aside>

            <section class="sb-main" id="student-billing-results">
                <section class="sb-surface">
                    <div class="sb-panel-head">
                        <div>
                            <span class="sb-panel-kicker">Billing List</span>
                            <h2>Student Billing Summary</h2>
                            <p>Review each student's semester billing summary, bill the pending items, and print existing bills where available.</p>
                        </div>
                        <span class="sb-panel-tag"><i class="fa fa-users"></i> <?php echo number_format((int)$visibleStudentCount); ?> student<?php echo ($visibleStudentCount === 1 ? '' : 's'); ?></span>
                    </div>

                    <?php if ($scopeIsSelected && $scopeIsAllowed && !empty($studentCards)) { ?>
                    <div class="sb-toolbar">
                        <label class="sb-search">
                            <i class="fa fa-search"></i>
                            <input type="search" placeholder="Search student name or ID" data-student-search>
                        </label>
                        <div class="sb-toolbar__summary">
                            <strong><?php echo sb_esc($selectedClassLabel . ' / ' . $selectedBatchLabel); ?></strong>
                            <span><?php echo number_format((int)$visibleScopeCount); ?> term scope<?php echo ($visibleScopeCount === 1 ? '' : 's'); ?> ready for review.</span>
                        </div>
                    </div>

                    <div class="sb-student-grid">
                        <?php foreach ($studentCards as $studentCard) { ?>
                        <article class="sb-student-card" data-student-card data-search="<?php echo sb_esc($studentCard['search']); ?>">
                            <div class="sb-student-card__head">
                                <div class="sb-student-card__identity">
                                    <h3><?php echo sb_esc($studentCard['fullname']); ?></h3>
                                    <p><?php echo sb_esc($studentCard['userid']); ?></p>
                                </div>
                                <div class="sb-student-card__meta">
                                    <?php echo $studentCard['pending_scope_count'] > 0 ? sb_status_badge_html('warning', number_format((int)$studentCard['pending_scope_count']) . ' pending scope' . ($studentCard['pending_scope_count'] === 1 ? '' : 's')) : sb_status_badge_html('success', 'Fully billed'); ?>
                                    <span class="sb-student-card__amount"><?php echo sb_esc(sb_money($studentCard['pending_amount'])); ?> pending</span>
                                </div>
                            </div>

                            <div class="sb-scope-list">
                                <?php foreach ($studentCard['scopes'] as $scope) { ?>
                                <section class="sb-scope-card">
                                    <div class="sb-scope-card__head">
                                        <div>
                                            <h4>Semester <?php echo sb_esc($scope['termname']); ?></h4>
                                            <p><?php echo number_format((int)$scope['total_items']); ?> item<?php echo ($scope['total_items'] === 1 ? '' : 's'); ?>, <?php echo number_format((int)$scope['pending_items']); ?> pending</p>
                                        </div>
                                        <div class="sb-scope-card__status">
                                            <?php
                                            if ($scope['pending_items'] > 0) {
                                                echo sb_status_badge_html('warning', 'Pending ' . number_format((int)$scope['pending_items']));
                                            } else {
                                                echo sb_status_badge_html('success', 'Fully billed');
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="sb-scope-stats">
                                        <div class="sb-scope-stat">
                                            <span>Total</span>
                                            <strong><?php echo sb_esc(sb_money($scope['total_amount'])); ?></strong>
                                        </div>
                                        <div class="sb-scope-stat">
                                            <span>Billed</span>
                                            <strong><?php echo sb_esc(sb_money($scope['billed_amount'])); ?></strong>
                                        </div>
                                        <div class="sb-scope-stat">
                                            <span>Pending</span>
                                            <strong><?php echo sb_esc(sb_money($scope['pending_amount'])); ?></strong>
                                        </div>
                                        <div class="sb-scope-stat">
                                            <span>Last Billed</span>
                                            <strong><?php echo $scope['latest_billed_at'] !== '' ? sb_esc($scope['latest_billed_at']) : 'Not yet'; ?></strong>
                                        </div>
                                    </div>

                                    <details class="sb-scope-details">
                                        <summary>View billed items</summary>
                                        <div class="sb-item-list">
                                            <?php foreach ($scope['items'] as $scopeItem) { ?>
                                            <div class="sb-item-row">
                                                <div class="sb-item-row__identity">
                                                    <strong><?php echo sb_esc($scopeItem['itemname']); ?></strong>
                                                    <small><?php echo sb_esc(sb_money($scopeItem['price'])); ?></small>
                                                </div>
                                                <div class="sb-item-row__status">
                                                    <?php
                                                    if (!empty($scopeItem['is_billed'])) {
                                                        echo sb_status_badge_html('success', 'Billed');
                                                        if ($scopeItem['billed_at'] !== '') {
                                                            echo "<small>" . sb_esc($scopeItem['billed_at']) . "</small>";
                                                        }
                                                    } else {
                                                        echo sb_status_badge_html('danger', 'Pending');
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </details>

                                    <div class="sb-scope-actions">
                                        <form method="post" action="student-billing.php?class_entryid=<?php echo urlencode($selectedClassId); ?>&batchid=<?php echo urlencode($selectedBatchId); ?>#student-billing-results">
                                            <input type="hidden" name="class_entryid" value="<?php echo sb_esc($selectedClassId); ?>">
                                            <input type="hidden" name="batchid" value="<?php echo sb_esc($selectedBatchId); ?>">
                                            <input type="hidden" name="student_userid" value="<?php echo sb_esc($studentCard['userid']); ?>">
                                            <input type="hidden" name="termname" value="<?php echo (int)$scope['termname']; ?>">
                                            <button class="sb-btn sb-btn--primary" type="submit" name="bill_scope" <?php echo ($scope['pending_items'] > 0 ? '' : 'disabled'); ?> onclick="return confirm('Bill the pending items for <?php echo sb_esc($studentCard['fullname']); ?> in semester <?php echo (int)$scope['termname']; ?>?');">
                                                <i class="fa fa-plus"></i> Bill Pending Items
                                            </button>
                                        </form>

                                        <?php if ($scope['billed_items'] > 0) { ?>
                                        <a class="sb-btn sb-btn--secondary" target="_blank" href="print-student-bills.php?user_id=<?php echo urlencode($studentCard['userid']); ?>&class_entryid=<?php echo urlencode($selectedClassId); ?>&batch_id=<?php echo urlencode($selectedBatchId); ?>&term_id=<?php echo (int)$scope['termname']; ?>">
                                            <i class="fa fa-print"></i> Print Bills
                                        </a>
                                        <?php } ?>
                                    </div>
                                </section>
                                <?php } ?>
                            </div>
                        </article>
                        <?php } ?>
                    </div>

                    <div class="sb-empty-state sb-empty-state--inline" data-student-empty hidden>
                        <h3>No students match this search</h3>
                        <p>Try a different student name or clear the search box.</p>
                    </div>
                    <?php } elseif ($scopeIsSelected && $scopeIsAllowed) { ?>
                    <div class="sb-empty-state">
                        <h3>No billable student scopes were found</h3>
                        <p>The selected class and batch do not currently have students with active term billing items that match your billing scope.</p>
                    </div>
                    <?php } else { ?>
                    <div class="sb-empty-state">
                        <h3>Select a class and batch to begin</h3>
                        <p>Once you choose a billing scope, this page will show each student's semester billing summary and pending amount.</p>
                    </div>
                    <?php } ?>
                </section>
            </section>
        </div>
    </main>
</div>
</body>
</html>

<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("storekeeper-utils.php");
include("matron-utils.php");
ensure_storekeeper_tables($con);
ensure_matron_tables($con);

if (!storekeeper_can_manage_module($con, 'stores_management')) {
    $_SESSION['Message'] = function_exists('storekeeper_flash_html')
        ? storekeeper_flash_html('error', 'You do not have access to the storekeeper dashboard.')
        : "<div style='color:red;text-align:center;padding:8px;'>You do not have access to the storekeeper dashboard.</div>";
    header("location:" . storekeeper_landing_page());
    exit();
}

if (!function_exists('sk_dashboard_date')) {
function sk_dashboard_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

$_Message = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
unset($_SESSION['Message']);

if (isset($_POST['update_matron_requisition']) && function_exists('matron_can_review_requisition') && matron_can_review_requisition($con)) {
    $_RequisitionId = trim((string)(isset($_POST['requisitionid']) ? $_POST['requisitionid'] : ''));
    $_DecisionStatus = trim((string)(isset($_POST['decision_status']) ? $_POST['decision_status'] : ''));
    $_AllowedStatuses = array('awaiting_headmaster', 'issued', 'rejected');
    if ($_RequisitionId === '' || !in_array($_DecisionStatus, $_AllowedStatuses, true)) {
        $_SESSION['Message'] = storekeeper_flash_html('error', 'That matron requisition update was not valid.');
    } else {
        $_RequisitionIdEsc = mysqli_real_escape_string($con, $_RequisitionId);
        $_DecisionByEsc = mysqli_real_escape_string($con, isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '');

        if ($_DecisionStatus === 'awaiting_headmaster') {
            $_StoreDecisionStatusEsc = mysqli_real_escape_string($con, 'approved');
            $_DecisionStatusEsc = mysqli_real_escape_string($con, 'awaiting_headmaster');
            $_DecisionNoteEsc = mysqli_real_escape_string($con, 'Checked by the storekeeper and sent to the headmaster.');
            @mysqli_query($con, "UPDATE tblmatronrequisition
                SET status='$_DecisionStatusEsc',
                    storedecisionstatus='$_StoreDecisionStatusEsc',
                    storedecisionnote='$_DecisionNoteEsc',
                    storedecisionby='$_DecisionByEsc',
                    storedecisiondatetime=NOW(),
                    decisionnote='$_DecisionNoteEsc',
                    decisionby='$_DecisionByEsc',
                    decisiondatetime=NOW()
                WHERE requisitionid='$_RequisitionIdEsc'
                  AND status='pending'
                LIMIT 1");
        } elseif ($_DecisionStatus === 'rejected') {
            $_DecisionStatusEsc = mysqli_real_escape_string($con, 'rejected');
            $_StoreDecisionStatusEsc = mysqli_real_escape_string($con, 'rejected');
            $_DecisionNoteEsc = mysqli_real_escape_string($con, 'Rejected by the storekeeper.');
            @mysqli_query($con, "UPDATE tblmatronrequisition
                SET status='$_DecisionStatusEsc',
                    storedecisionstatus='$_StoreDecisionStatusEsc',
                    storedecisionnote='$_DecisionNoteEsc',
                    storedecisionby='$_DecisionByEsc',
                    storedecisiondatetime=NOW(),
                    decisionnote='$_DecisionNoteEsc',
                    decisionby='$_DecisionByEsc',
                    decisiondatetime=NOW()
                WHERE requisitionid='$_RequisitionIdEsc'
                  AND status='pending'
                LIMIT 1");
        } else {
            $_DecisionStatusEsc = mysqli_real_escape_string($con, 'issued');
            $_DecisionNoteEsc = mysqli_real_escape_string($con, 'Issued from the store after final approval.');
            @mysqli_query($con, "UPDATE tblmatronrequisition
                SET status='$_DecisionStatusEsc',
                    decisionnote='$_DecisionNoteEsc',
                    decisionby='$_DecisionByEsc',
                    decisiondatetime=NOW()
                WHERE requisitionid='$_RequisitionIdEsc'
                  AND status='approved'
                LIMIT 1");
        }
        $_SESSION['Message'] = mysqli_affected_rows($con) > 0
            ? storekeeper_flash_html('success', 'Matron requisition updated successfully.')
            : storekeeper_flash_html('warning', 'That requisition could not be updated. It may already be closed.');
    }
    header("location:storekeeper-dashboard.php#matron-requisitions");
    exit();
}

$_Summary = storekeeper_dashboard_summary($con);
$_MatronRequisitionSummary = matron_requisition_summary($con);
$_BalanceRows = storekeeper_fetch_balance_rows($con);
$_LowStockRows = array();
foreach ($_BalanceRows as $_Row) {
    if ((float)$_Row['current_balance'] <= 0 || ((float)$_Row['reorderlevel'] > 0 && (float)$_Row['current_balance'] <= (float)$_Row['reorderlevel'])) {
        $_LowStockRows[] = $_Row;
    }
    if (count($_LowStockRows) >= 8) {
        break;
    }
}
$_RecentReceipts = storekeeper_recent_receipts($con, 8);
$_RecentIssues = storekeeper_recent_issues($con, 8);
$_RecentStudentIssues = storekeeper_recent_student_issues($con, 8);
$_RecentMatronRequisitions = matron_recent_requisitions($con, 8);
$_DashboardPrintLimit = 8;
$_RecentReceiptCount = count($_RecentReceipts);
$_RecentIssueCount = count($_RecentIssues);
$_RecentStudentIssueCount = count($_RecentStudentIssues);
$_RecentRequisitionCount = count($_RecentMatronRequisitions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/storekeeper.css">
</head>
<body class="storekeeper-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<main class="sk-main">
    <div class="sk-shell">
        <section class="sk-hero">
            <div>
                <span class="sk-kicker"><i class="fa fa-archive"></i> School Store</span>
                <h1>Keep store work and student item records in one place.</h1>
                <p>Use this page to check balances, record stock movement, follow student-issued items, and respond to store requests from the kitchen or teachers.</p>
                <div class="sk-hero__chips">
                    <span class="sk-chip"><i class="fa fa-cubes"></i> Total Items: <?php echo number_format((int)$_Summary['total_items']); ?></span>
                    <span class="sk-chip"><i class="fa fa-arrow-down"></i> Receipts This Week: <?php echo number_format((int)$_Summary['receipt_count_week']); ?></span>
                    <span class="sk-chip"><i class="fa fa-arrow-up"></i> Issues This Week: <?php echo number_format((int)$_Summary['issue_count_week']); ?></span>
                    <span class="sk-chip"><i class="fa fa-book"></i> Student Issues This Week: <?php echo number_format((int)$_Summary['student_issue_count_week']); ?></span>
                    <span class="sk-chip"><i class="fa fa-cutlery"></i> Pending Store Requests: <?php echo number_format((int)$_MatronRequisitionSummary['pending']); ?></span>
                    <span class="sk-chip"><i class="fa fa-user-circle-o"></i> Waiting for Head: <?php echo number_format((int)$_MatronRequisitionSummary['awaiting_headmaster']); ?></span>
                </div>
                <div class="sk-link-grid" style="margin-top:16px;">
                    <a class="sk-link-chip" href="store-item-entry.php"><i class="fa fa-tags"></i> Item Master</a>
                    <a class="sk-link-chip" href="store-stock-receipt.php"><i class="fa fa-download"></i> Stock Receipt</a>
                    <a class="sk-link-chip" href="store-stock-issue.php"><i class="fa fa-upload"></i> Stock Issue</a>
                    <a class="sk-link-chip" href="store-student-issue.php"><i class="fa fa-book"></i> Student Items</a>
                    <a class="sk-link-chip" href="store-balance-report.php"><i class="fa fa-line-chart"></i> Balance Report</a>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Active Items</span>
                    <strong><?php echo number_format((int)$_Summary['active_items']); ?></strong>
                    <small>Items currently active in the store records.</small>
                </article>
                <article class="sk-stat">
                    <span>Low Stock</span>
                    <strong><?php echo number_format((int)$_Summary['low_stock_items']); ?></strong>
                    <small>Items at or below the reorder level.</small>
                </article>
                <article class="sk-stat">
                    <span>Out Of Stock</span>
                    <strong><?php echo number_format((int)$_Summary['out_of_stock_items']); ?></strong>
                    <small>Items with no balance left in the store.</small>
                </article>
                <article class="sk-stat">
                    <span>Overdue Student Items</span>
                    <strong><?php echo number_format((int)$_Summary['student_items_overdue']); ?></strong>
                    <small>Student items that should have been returned by now.</small>
                </article>
            </div>
        </section>

        <?php if ($_Message !== "") { ?>
        <?php echo $_Message; ?>
        <?php } ?>

        <section class="sk-summary-grid">
            <article class="sk-summary-card">
                <span>Item Master</span>
                <strong><?php echo number_format((int)$_Summary['total_items']); ?></strong>
                <small>All store items entered so far, including inactive ones.</small>
            </article>
            <article class="sk-summary-card">
                <span>Low Stock Watch</span>
                <strong><?php echo number_format((int)$_Summary['low_stock_items']); ?></strong>
                <small>Items that may need restocking soon.</small>
            </article>
            <article class="sk-summary-card">
                <span>Receipts</span>
                <strong><?php echo number_format((int)$_Summary['receipt_count_week']); ?></strong>
                <small>Receipt entries posted in the last seven days.</small>
            </article>
            <article class="sk-summary-card">
                <span>Issues</span>
                <strong><?php echo number_format((int)$_Summary['issue_count_week']); ?></strong>
                <small>Issue entries posted in the last seven days.</small>
            </article>
            <article class="sk-summary-card">
                <span>Student Items Out</span>
                <strong><?php echo number_format((int)$_Summary['student_items_out']); ?></strong>
                <small>Items currently recorded under student names.</small>
            </article>
            <article class="sk-summary-card">
                <span>Overdue Student Items</span>
                <strong><?php echo number_format((int)$_Summary['student_items_overdue']); ?></strong>
                <small>Student items now past the return date.</small>
            </article>
            <article class="sk-summary-card">
                <span>Store Requests Pending</span>
                <strong><?php echo number_format((int)$_MatronRequisitionSummary['pending']); ?></strong>
                <small>Requests from staff or the kitchen still waiting for store review.</small>
            </article>
            <article class="sk-summary-card">
                <span>Waiting for Head</span>
                <strong><?php echo number_format((int)$_MatronRequisitionSummary['awaiting_headmaster']); ?></strong>
                <small>Requests already checked by the store and now waiting for final approval.</small>
            </article>
        </section>

        <section class="sk-panel">
            <div class="sk-panel__header">
                <div>
                    <h2>Student Statistics</h2>
                    <p>A quick view of the student population available to the store.</p>
                </div>
                <a class="sk-button--ghost" href="store-student-issue.php"><i class="fa fa-book"></i> Open student item register</a>
            </div>
            <div class="sk-panel__body">
                <div class="sk-summary-grid">
                    <article class="sk-summary-card">
                        <span>All Students</span>
                        <strong><?php echo number_format((int)$_Summary['student_total']); ?></strong>
                        <small>Active students.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'student_total', 'Batches', 'No batch yet.'); ?>
                    </article>
                    <article class="sk-summary-card">
                        <span>Day Students</span>
                        <strong><?php echo number_format((int)$_Summary['day_students_total']); ?></strong>
                        <small>Day students.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'day_students_total', 'Batches', 'No batch yet.'); ?>
                    </article>
                    <article class="sk-summary-card">
                        <span>Boarders</span>
                        <strong><?php echo number_format((int)$_Summary['boarding_students_total']); ?></strong>
                        <small>Boarders.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'boarding_students_total', 'Batches', 'No batch yet.'); ?>
                    </article>
                    <article class="sk-summary-card">
                        <span>Day Boys</span>
                        <strong><?php echo number_format((int)$_Summary['day_boys']); ?></strong>
                        <small>Day boys.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'day_boys', 'Batches', 'No batch yet.'); ?>
                    </article>
                    <article class="sk-summary-card">
                        <span>Day Girls</span>
                        <strong><?php echo number_format((int)$_Summary['day_girls']); ?></strong>
                        <small>Day girls.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'day_girls', 'Batches', 'No batch yet.'); ?>
                    </article>
                    <article class="sk-summary-card">
                        <span>Boarder Boys</span>
                        <strong><?php echo number_format((int)$_Summary['boarding_boys']); ?></strong>
                        <small>Boarding boys.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'boarding_boys', 'Batches', 'No batch yet.'); ?>
                    </article>
                    <article class="sk-summary-card">
                        <span>Boarder Girls</span>
                        <strong><?php echo number_format((int)$_Summary['boarding_girls']); ?></strong>
                        <small>Boarding girls.</small>
                        <?php echo dashboard_student_batch_breakdown_html($_Summary, 'boarding_girls', 'Batches', 'No batch yet.'); ?>
                    </article>
                </div>
            </div>
        </section>

        <div class="sk-layout">
            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Stock to check</h2>
                        <p>These items need attention based on the current balance and reorder level.</p>
                    </div>
                    <a class="sk-button--ghost" href="store-balance-report.php"><i class="fa fa-eye"></i> Open full report</a>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_LowStockRows)) { ?>
                    <div class="sk-empty">No stock issue to follow up right now.</div>
                    <?php } else { ?>
                    <div class="sk-list">
                        <?php foreach ($_LowStockRows as $_Row) { ?>
                        <div class="sk-list-item">
                            <strong><?php echo storekeeper_esc($_Row['itemname']); ?></strong>
                            <div class="sk-inline-meta">
                                <span><i class="fa fa-folder-open-o"></i> <?php echo storekeeper_esc($_Row['itemcategory']); ?></span>
                                <span><i class="fa fa-balance-scale"></i> Balance: <?php echo storekeeper_format_quantity($_Row['current_balance']); ?> <?php echo storekeeper_esc($_Row['unitname']); ?></span>
                                <span><i class="fa fa-bell-o"></i> Reorder: <?php echo storekeeper_format_quantity($_Row['reorderlevel']); ?></span>
                            </div>
                            <div style="margin-top:10px;"><?php echo storekeeper_stock_badge_html($_Row['current_balance'], $_Row['reorderlevel']); ?></div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </section>

            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Quick Actions</h2>
                        <p>Open the main store tasks from one place.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <div class="sk-list">
                        <div class="sk-list-item">
                            <strong>Set up store items</strong>
                            <small>Add store items, units, categories, and reorder levels before posting stock movement.</small>
                            <div class="sk-actions" style="margin-top:12px;">
                                <a class="sk-button" href="store-item-entry.php"><i class="fa fa-plus-circle"></i> Open item master</a>
                            </div>
                        </div>
                        <div class="sk-list-item">
                            <strong>Record goods received</strong>
                            <small>Record stock received from purchases, donations, or transfers into the main store.</small>
                            <div class="sk-actions" style="margin-top:12px;">
                                <a class="sk-button" href="store-stock-receipt.php"><i class="fa fa-download"></i> Record receipt</a>
                            </div>
                        </div>
                        <div class="sk-list-item">
                            <strong>Issue stock out</strong>
                            <small>Issue items to the kitchen, matron, or another user and keep balances up to date.</small>
                            <div class="sk-actions" style="margin-top:12px;">
                                <a class="sk-button" href="store-stock-issue.php"><i class="fa fa-upload"></i> Record issue</a>
                            </div>
                        </div>
                        <div class="sk-list-item">
                            <strong>Issue items to students</strong>
                            <small>Record books, uniforms, bedding, and other items given to students with return dates.</small>
                            <div class="sk-actions" style="margin-top:12px;">
                                <a class="sk-button" href="store-student-issue.php"><i class="fa fa-book"></i> Open student register</a>
                            </div>
                        </div>
                        <div class="sk-list-item">
                            <strong>Review store requisitions</strong>
                            <small>Check requests from teachers or the kitchen, send the cleared ones to the headmaster, and issue only after final approval.</small>
                            <div class="sk-actions" style="margin-top:12px;">
                                <a class="sk-button" href="#matron-requisitions"><i class="fa fa-cutlery"></i> Open store requests</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <details class="sk-panel sk-disclosure" id="matron-requisitions">
            <summary class="sk-disclosure__summary">
                <div>
                    <span class="sk-disclosure__eyebrow">Store Requisitions</span>
                    <strong>Review requests from teachers and the kitchen</strong>
                    <small>Pending: <?php echo number_format((int)$_MatronRequisitionSummary['pending']); ?> | Waiting for head: <?php echo number_format((int)$_MatronRequisitionSummary['awaiting_headmaster']); ?> | Showing latest <?php echo number_format((int)$_RecentRequisitionCount); ?></small>
                </div>
            </summary>
            <div class="sk-panel__header">
                    <div>
                        <h2>Store Requisitions</h2>
                        <p>Requests from the kitchen or teachers and the stage each one has reached.</p>
                    </div>
                <div class="sk-actions">
                    <a class="sk-button--ghost" href="storekeeper-dashboard-print.php?report=requisitions&amp;limit=<?php echo (int)$_DashboardPrintLimit; ?>&amp;autoprint=1" target="_blank" rel="noopener"><i class="fa fa-print"></i> Print</a>
                    <a class="sk-button--ghost" href="store-stock-issue.php"><i class="fa fa-upload"></i> Open stock issue page</a>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_RecentMatronRequisitions)) { ?>
                <div class="sk-empty">No matron request has been recorded yet.</div>
                <?php } else { ?>
                <div class="sk-table-wrap">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Actions</th>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Need By</th>
                                <th>Meal Slot</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_RecentMatronRequisitions as $_Req) { ?>
                            <tr>
                                <td>
                                    <div class="sk-actions">
                                        <?php if ((string)$_Req['status'] === 'pending') { ?>
                                        <form method="post" action="storekeeper-dashboard.php#matron-requisitions" class="matron-inline-form">
                                            <input type="hidden" name="requisitionid" value="<?php echo matron_esc($_Req['requisitionid']); ?>">
                                            <input type="hidden" name="decision_status" value="awaiting_headmaster">
                                            <button type="submit" name="update_matron_requisition" class="matron-inline-button"><i class="fa fa-share"></i> Send to Head</button>
                                        </form>
                                        <form method="post" action="storekeeper-dashboard.php#matron-requisitions" class="matron-inline-form">
                                            <input type="hidden" name="requisitionid" value="<?php echo matron_esc($_Req['requisitionid']); ?>">
                                            <input type="hidden" name="decision_status" value="rejected">
                                            <button type="submit" name="update_matron_requisition" class="matron-inline-button" onclick="return confirm('Reject this matron requisition?');"><i class="fa fa-times"></i> Reject</button>
                                        </form>
                                        <?php } elseif ((string)$_Req['status'] === 'awaiting_headmaster') { ?>
                                        <span class="sk-muted">Waiting for headmaster</span>
                                        <?php } elseif ((string)$_Req['status'] === 'approved') { ?>
                                        <form method="post" action="storekeeper-dashboard.php#matron-requisitions" class="matron-inline-form">
                                            <input type="hidden" name="requisitionid" value="<?php echo matron_esc($_Req['requisitionid']); ?>">
                                            <input type="hidden" name="decision_status" value="issued">
                                            <button type="submit" name="update_matron_requisition" class="matron-inline-button"><i class="fa fa-upload"></i> Mark Issued</button>
                                        </form>
                                        <?php } else { ?>
                                        <span class="sk-muted">Closed</span>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td><?php echo sk_dashboard_date($_Req['requestdate']); ?></td>
                                <td>
                                    <?php echo matron_esc($_Req['itemname']); ?>
                                    <small><?php echo matron_esc($_Req['requested_by_name']); ?> | <?php echo matron_esc($_Req['requestorigin_label']); ?> request</small>
                                </td>
                                <td><?php echo storekeeper_format_quantity($_Req['quantity']); ?> <?php echo matron_esc($_Req['unitname']); ?></td>
                                <td><?php echo sk_dashboard_date($_Req['needbydate']); ?></td>
                                <td>
                                    <strong class="matron-cell-title"><?php echo matron_esc(matron_requisition_slot_label($_Req['dayname'], $_Req['mealtime'])); ?></strong>
                                    <small><?php echo matron_esc($_Req['dayname']); ?> schedule</small>
                                </td>
                                <td>
                                    <strong class="matron-cell-title"><?php echo matron_esc($_Req['purpose']); ?></strong>
                                    <?php if (trim((string)$_Req['notes']) !== '') { ?>
                                    <small><?php echo matron_esc($_Req['notes']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php echo matron_requisition_badge_html($_Req['status']); ?>
                                    <?php if (trim((string)$_Req['stage_note']) !== '') { ?>
                                    <small><?php echo matron_esc($_Req['stage_note']); ?></small>
                                    <?php } ?>
                                    <?php if (trim((string)$_Req['store_decision_by_name']) !== '') { ?>
                                    <small>Store: <?php echo matron_esc($_Req['store_decision_by_name']); ?></small>
                                    <?php } ?>
                                    <?php if (trim((string)$_Req['head_decision_by_name']) !== '') { ?>
                                    <small>Head: <?php echo matron_esc($_Req['head_decision_by_name']); ?></small>
                                    <?php } ?>
                                    <?php if (!empty($_Req['is_headmaster_adjusted'])) { ?>
                                    <small>Headmaster changed the final details before approving.</small>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </details>

        <div class="sk-layout">
            <details class="sk-panel sk-disclosure" id="recent-receipts">
                <summary class="sk-disclosure__summary">
                    <div>
                        <span class="sk-disclosure__eyebrow">Recent Receipts</span>
                        <strong>Latest stock received into the store</strong>
                        <small>This week: <?php echo number_format((int)$_Summary['receipt_count_week']); ?> | Showing latest <?php echo number_format((int)$_RecentReceiptCount); ?> receipt record(s)</small>
                    </div>
                </summary>
                <div class="sk-panel__header">
                    <div>
                        <h2>Recent Receipts</h2>
                        <p>Latest stock received into the store.</p>
                    </div>
                    <a class="sk-button--ghost" href="store-stock-receipt.php"><i class="fa fa-list"></i> Open receipts page</a>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_RecentReceipts)) { ?>
                    <div class="sk-empty">No receipt has been posted yet.</div>
                    <?php } else { ?>
                    <div class="sk-table-wrap">
                        <table class="sk-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Source</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_RecentReceipts as $_Receipt) { ?>
                                <tr>
                                    <td><?php echo sk_dashboard_date($_Receipt['receiptdate']); ?></td>
                                    <td>
                                        <?php echo storekeeper_esc($_Receipt['itemname']); ?>
                                        <small><?php echo storekeeper_esc($_Receipt['receiptid']); ?></small>
                                    </td>
                                    <td><?php echo storekeeper_esc($_Receipt['source_name']); ?></td>
                                    <td><?php echo storekeeper_format_quantity($_Receipt['quantity']); ?> <?php echo storekeeper_esc($_Receipt['unitname']); ?></td>
                                    <td><?php echo storekeeper_status_badge_html($_Receipt['status']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </details>

            <details class="sk-panel sk-disclosure" id="recent-issues">
                <summary class="sk-disclosure__summary">
                    <div>
                        <span class="sk-disclosure__eyebrow">Recent Issues</span>
                        <strong>Latest stock issued from the store</strong>
                        <small>This week: <?php echo number_format((int)$_Summary['issue_count_week']); ?> | Showing latest <?php echo number_format((int)$_RecentIssueCount); ?> issue record(s)</small>
                    </div>
                </summary>
                <div class="sk-panel__header">
                    <div>
                        <h2>Recent Issues</h2>
                        <p>Latest stock issued from the store.</p>
                    </div>
                    <div class="sk-actions">
                        <a class="sk-button--ghost" href="storekeeper-dashboard-print.php?report=issues&amp;limit=<?php echo (int)$_DashboardPrintLimit; ?>&amp;autoprint=1" target="_blank" rel="noopener"><i class="fa fa-print"></i> Print</a>
                        <a class="sk-button--ghost" href="store-stock-issue.php"><i class="fa fa-list"></i> Open issues page</a>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_RecentIssues)) { ?>
                    <div class="sk-empty">No issue has been posted yet.</div>
                    <?php } else { ?>
                    <div class="sk-table-wrap">
                        <table class="sk-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Issued To</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_RecentIssues as $_Issue) { ?>
                                <tr>
                                    <td><?php echo sk_dashboard_date($_Issue['issuedate']); ?></td>
                                    <td>
                                        <?php echo storekeeper_esc($_Issue['itemname']); ?>
                                        <small><?php echo storekeeper_esc($_Issue['issueid']); ?></small>
                                    </td>
                                    <td><?php echo storekeeper_esc($_Issue['issuedto']); ?></td>
                                    <td><?php echo storekeeper_format_quantity($_Issue['quantity']); ?> <?php echo storekeeper_esc($_Issue['unitname']); ?></td>
                                    <td><?php echo storekeeper_status_badge_html($_Issue['status']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </details>
        </div>

        <details class="sk-panel sk-disclosure" id="recent-student-items">
            <summary class="sk-disclosure__summary">
                <div>
                    <span class="sk-disclosure__eyebrow">Student Item Records</span>
                    <strong>Latest items issued out to students</strong>
                    <small>Items out now: <?php echo number_format((int)$_Summary['student_items_out']); ?> | Overdue: <?php echo number_format((int)$_Summary['student_items_overdue']); ?> | Showing latest <?php echo number_format((int)$_RecentStudentIssueCount); ?></small>
                </div>
            </summary>
            <div class="sk-panel__header">
                <div>
                    <h2>Recent Student Item Records</h2>
                    <p>Latest items given to students, including return status and due dates.</p>
                </div>
                <div class="sk-actions">
                    <a class="sk-button--ghost" href="storekeeper-dashboard-print.php?report=student_items&amp;limit=<?php echo (int)$_DashboardPrintLimit; ?>&amp;autoprint=1" target="_blank" rel="noopener"><i class="fa fa-print"></i> Print</a>
                    <a class="sk-button--ghost" href="store-student-issue.php"><i class="fa fa-list"></i> Open student items page</a>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_RecentStudentIssues)) { ?>
                <div class="sk-empty">No student item has been recorded yet.</div>
                <?php } else { ?>
                <div class="sk-table-wrap">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Group</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_RecentStudentIssues as $_StudentIssue) { ?>
                            <tr>
                                <td><?php echo sk_dashboard_date($_StudentIssue['issuedate']); ?></td>
                                <td>
                                    <?php echo storekeeper_esc($_StudentIssue['student_name']); ?>
                                    <small><?php echo storekeeper_esc($_StudentIssue['studentid']); ?></small>
                                </td>
                                <td><?php echo storekeeper_esc($_StudentIssue['_population_label']); ?></td>
                                <td>
                                    <?php echo storekeeper_esc($_StudentIssue['itemname']); ?>
                                    <small><?php echo storekeeper_esc($_StudentIssue['studentissueid']); ?></small>
                                </td>
                                <td><?php echo storekeeper_format_quantity($_StudentIssue['quantity']); ?> <?php echo storekeeper_esc($_StudentIssue['unitname']); ?></td>
                                <td><?php echo sk_dashboard_date($_StudentIssue['expectedreturndate']); ?></td>
                                <td><?php echo storekeeper_student_issue_status_badge_html($_StudentIssue); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </details>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var hash = window.location.hash || '';
    if (!hash) {
        return;
    }

    var target = document.querySelector(hash);
    if (target && target.tagName && target.tagName.toLowerCase() === 'details') {
        target.open = true;
    }
});
</script>
</body>
</html>

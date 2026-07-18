<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("company.php");
include_once("storekeeper-utils.php");
include_once("matron-utils.php");

ensure_storekeeper_tables($con);
ensure_matron_tables($con);

if (!storekeeper_can_manage_module($con, 'stores_management')) {
    header("location:" . storekeeper_landing_page());
    exit();
}

if (!function_exists('skpr_esc')) {
function skpr_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if (!function_exists('skpr_date')) {
function skpr_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

if (!function_exists('skpr_logo_path')) {
function skpr_logo_path($rawLogo)
{
    $rawLogo = trim((string)$rawLogo);
    if ($rawLogo === '') {
        return '';
    }

    $uploadPath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $rawLogo;
    if (file_exists($uploadPath)) {
        return 'uploads/' . rawurlencode($rawLogo);
    }

    $directPath = __DIR__ . DIRECTORY_SEPARATOR . $rawLogo;
    if (file_exists($directPath)) {
        return $rawLogo;
    }

    return '';
}
}

if (!function_exists('skpr_store_status_label')) {
function skpr_store_status_label($status)
{
    $status = strtolower(trim((string)$status));
    if ($status === 'posted') {
        return 'Posted';
    }
    if ($status === 'void') {
        return 'Voided';
    }
    if ($status === 'active') {
        return 'Active';
    }
    if ($status === 'inactive') {
        return 'Inactive';
    }
    if ($status === 'out') {
        return 'Out Of Stock';
    }
    if ($status === 'low') {
        return 'Low Stock';
    }
    if ($status === 'issued') {
        return 'Issued Out';
    }
    if ($status === 'awaiting_return') {
        return 'Awaiting Return';
    }
    if ($status === 'overdue') {
        return 'Overdue Return';
    }
    if ($status === 'returned') {
        return 'Returned';
    }
    if ($status === 'lost') {
        return 'Lost / Missing';
    }
    return ucwords($status);
}
}

$report = isset($_GET['report']) ? strtolower(trim((string)$_GET['report'])) : 'requisitions';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
$limit = max(1, min(50, $limit));
$autoPrint = isset($_GET['autoprint']) && trim((string)$_GET['autoprint']) === '1';

$allowedReports = array('requisitions', 'issues', 'student_items');
if (!in_array($report, $allowedReports, true)) {
    $report = 'requisitions';
}

$rows = array();
$reportTitle = 'Store Requisitions';
$reportSubtitle = 'Latest store requests from teachers and the kitchen.';
$backHref = 'storekeeper-dashboard.php#matron-requisitions';

if ($report === 'issues') {
    $rows = storekeeper_recent_issues($con, $limit);
    $reportTitle = 'Recent Issues';
    $reportSubtitle = 'Latest stock issued from the store.';
    $backHref = 'storekeeper-dashboard.php#recent-issues';
} elseif ($report === 'student_items') {
    $rows = storekeeper_recent_student_issues($con, $limit);
    $reportTitle = 'Recent Student Item Records';
    $reportSubtitle = 'Latest items issued to students and their return position.';
    $backHref = 'storekeeper-dashboard.php#recent-student-items';
} else {
    $rows = matron_recent_requisitions($con, $limit);
}

$schoolName = isset($_CompanyName) && trim((string)$_CompanyName) !== '' ? trim((string)$_CompanyName) : 'LiveCampus';
$schoolAddress = trim((string)(isset($_Address) ? $_Address : ''));
$schoolLocation = trim((string)(isset($_Location) ? $_Location : ''));
$schoolPhone = trim(implode(' / ', array_filter(array(
    trim((string)(isset($_Telephone1) ? $_Telephone1 : '')),
    trim((string)(isset($_Telephone2) ? $_Telephone2 : ''))
))));
$logoPath = skpr_logo_path(isset($_Logo) ? $_Logo : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo skpr_esc($reportTitle); ?> - LiveCampus</title>
<style>
body {
    margin: 0;
    background: #f4f7fb;
    color: #1f2937;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}
.print-shell {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px;
}
.print-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}
.print-toolbar a,
.print-toolbar button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 16px;
    border: 0;
    border-radius: 12px;
    background: #0f766e;
    color: #ffffff;
    font: inherit;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}
.print-toolbar a {
    background: #e5edf5;
    color: #24435f;
}
.print-card {
    padding: 28px;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 22px 42px rgba(15, 23, 42, 0.08);
}
.print-header {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 18px;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 2px solid #dbe3ea;
}
.print-header img {
    width: 86px;
    height: 86px;
    object-fit: contain;
}
.print-header h1 {
    margin: 0;
    font-size: 1.8rem;
}
.print-header p {
    margin: 6px 0 0;
    color: #5f6f84;
    line-height: 1.6;
}
.print-meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 18px;
    color: #5f6f84;
}
.print-section-title {
    margin: 24px 0 6px;
    font-size: 1.05rem;
    color: #112033;
}
.print-section-note {
    margin: 0 0 14px;
    color: #64748b;
    line-height: 1.55;
}
.print-table {
    width: 100%;
    border-collapse: collapse;
}
.print-table th,
.print-table td {
    padding: 12px 10px;
    border: 1px solid #dbe3ea;
    text-align: left;
    vertical-align: top;
}
.print-table thead th {
    background: #f8fbff;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #4b5563;
}
.print-table td small {
    display: block;
    margin-top: 4px;
    color: #64748b;
    line-height: 1.45;
}
.print-empty {
    margin-top: 20px;
    padding: 18px;
    border-radius: 18px;
    background: #f8fbff;
    border: 1px dashed #cbd8e6;
    color: #64748b;
}
@media print {
    body {
        background: #ffffff;
    }
    .print-shell {
        max-width: none;
        padding: 0;
    }
    .print-toolbar {
        display: none;
    }
    .print-card {
        box-shadow: none;
        border-radius: 0;
        padding: 0;
    }
}
@media (max-width: 720px) {
    .print-header {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="print-shell">
    <div class="print-toolbar">
        <a href="<?php echo skpr_esc($backHref); ?>">Back to Store Dashboard</a>
        <button type="button" onclick="window.print()">Print Now</button>
    </div>

    <div class="print-card">
        <div class="print-header">
            <?php if ($logoPath !== '') { ?>
            <img src="<?php echo skpr_esc($logoPath); ?>" alt="<?php echo skpr_esc($schoolName); ?>">
            <?php } ?>
            <div>
                <h1><?php echo skpr_esc($schoolName); ?></h1>
                <p><?php echo skpr_esc($schoolAddress); ?><?php echo $schoolAddress !== '' && $schoolLocation !== '' ? ' | ' : ''; ?><?php echo skpr_esc($schoolLocation); ?></p>
                <?php if ($schoolPhone !== '') { ?>
                <p><?php echo skpr_esc($schoolPhone); ?></p>
                <?php } ?>
            </div>
        </div>

        <div class="print-meta">
            <span><?php echo skpr_esc($reportTitle); ?></span>
            <span>Generated on <?php echo skpr_esc(date("d M Y, H:i")); ?></span>
        </div>

        <?php if (empty($rows)) { ?>
        <div class="print-empty">No record matched this print view.</div>
        <?php } elseif ($report === 'issues') { ?>
        <h2 class="print-section-title"><?php echo skpr_esc($reportTitle); ?></h2>
        <p class="print-section-note"><?php echo skpr_esc($reportSubtitle); ?> Showing latest <?php echo number_format(count($rows)); ?> row(s).</p>
        <table class="print-table">
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
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo skpr_esc(skpr_date($row['issuedate'])); ?></td>
                    <td>
                        <?php echo skpr_esc($row['itemname']); ?>
                        <small><?php echo skpr_esc($row['issueid']); ?></small>
                    </td>
                    <td><?php echo skpr_esc($row['issuedto']); ?></td>
                    <td><?php echo skpr_esc(storekeeper_format_quantity($row['quantity'])); ?> <?php echo skpr_esc($row['unitname']); ?></td>
                    <td><?php echo skpr_esc(skpr_store_status_label($row['status'])); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } elseif ($report === 'student_items') { ?>
        <h2 class="print-section-title"><?php echo skpr_esc($reportTitle); ?></h2>
        <p class="print-section-note"><?php echo skpr_esc($reportSubtitle); ?> Showing latest <?php echo number_format(count($rows)); ?> row(s).</p>
        <table class="print-table">
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
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo skpr_esc(skpr_date($row['issuedate'])); ?></td>
                    <td>
                        <?php echo skpr_esc($row['student_name']); ?>
                        <small><?php echo skpr_esc($row['studentid']); ?></small>
                    </td>
                    <td><?php echo skpr_esc($row['_population_label']); ?></td>
                    <td>
                        <?php echo skpr_esc($row['itemname']); ?>
                        <small><?php echo skpr_esc($row['studentissueid']); ?></small>
                    </td>
                    <td><?php echo skpr_esc(storekeeper_format_quantity($row['quantity'])); ?> <?php echo skpr_esc($row['unitname']); ?></td>
                    <td><?php echo skpr_esc(skpr_date($row['expectedreturndate'])); ?></td>
                    <td><?php echo skpr_esc(storekeeper_student_issue_status_label(storekeeper_student_issue_status($row))); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { ?>
        <h2 class="print-section-title"><?php echo skpr_esc($reportTitle); ?></h2>
        <p class="print-section-note"><?php echo skpr_esc($reportSubtitle); ?> Showing latest <?php echo number_format(count($rows)); ?> row(s).</p>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Requester</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Need By</th>
                    <th>Slot</th>
                    <th>Purpose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo skpr_esc(skpr_date($row['requestdate'])); ?></td>
                    <td>
                        <?php echo skpr_esc($row['requested_by_name']); ?>
                        <small><?php echo skpr_esc($row['requestorigin_label']); ?> request</small>
                    </td>
                    <td><?php echo skpr_esc($row['itemname']); ?></td>
                    <td><?php echo skpr_esc(storekeeper_format_quantity($row['quantity'])); ?> <?php echo skpr_esc($row['unitname']); ?></td>
                    <td><?php echo skpr_esc(skpr_date($row['needbydate'])); ?></td>
                    <td><?php echo skpr_esc(matron_requisition_slot_label($row['dayname'], $row['mealtime'])); ?></td>
                    <td>
                        <?php echo skpr_esc($row['purpose']); ?>
                        <?php if (trim((string)$row['stage_note']) !== '') { ?>
                        <small><?php echo skpr_esc($row['stage_note']); ?></small>
                        <?php } ?>
                    </td>
                    <td><?php echo skpr_esc(isset($row['status_label']) ? $row['status_label'] : matron_requisition_status_label($row['status'])); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } ?>
    </div>
</div>
<?php if ($autoPrint) { ?>
<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
<?php } ?>
</body>
</html>

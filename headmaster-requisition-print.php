<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("company.php");
include_once("storekeeper-utils.php");
include_once("matron-utils.php");

ensure_storekeeper_tables($con);
ensure_matron_tables($con);

if (!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Headmaster')) {
    header("location:" . (function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

if (!function_exists('hmpr_esc')) {
function hmpr_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if (!function_exists('hmpr_date')) {
function hmpr_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y", $timestamp) : $value;
}
}

if (!function_exists('hmpr_logo_path')) {
function hmpr_logo_path($rawLogo)
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

$printRequisitionId = isset($_GET['requisitionid']) ? trim((string)$_GET['requisitionid']) : '';
$printStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$printOrigin = isset($_GET['origin']) ? trim((string)$_GET['origin']) : '';
$printSearch = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$autoPrint = isset($_GET['autoprint']) && trim((string)$_GET['autoprint']) === '1';

$rows = array();
if ($printRequisitionId !== '') {
    $singleRow = matron_get_requisition_row($con, $printRequisitionId);
    if ($singleRow) {
        $rows[] = $singleRow;
    }
} else {
    $filters = array('limit' => 300);
    if (in_array($printStatus, array('approved', 'issued', 'rejected', 'cancelled'), true)) {
        $filters['status'] = $printStatus;
    }
    if ($printOrigin !== '' && in_array($printOrigin, array_keys(matron_requisition_origin_options()), true)) {
        $filters['requestorigin'] = $printOrigin;
    }
    if ($printSearch !== '') {
        $filters['search'] = $printSearch;
    }
    foreach (matron_fetch_requisition_rows($con, $filters) as $historyRow) {
        if (in_array((string)$historyRow['status'], array('approved', 'issued', 'rejected', 'cancelled'), true)) {
            $rows[] = $historyRow;
        }
    }
}

$schoolName = isset($_CompanyName) && trim((string)$_CompanyName) !== '' ? trim((string)$_CompanyName) : 'LiveCampus';
$schoolAddress = trim((string)(isset($_Address) ? $_Address : ''));
$schoolLocation = trim((string)(isset($_Location) ? $_Location : ''));
$schoolPhone = trim(implode(' / ', array_filter(array(trim((string)(isset($_Telephone1) ? $_Telephone1 : '')), trim((string)(isset($_Telephone2) ? $_Telephone2 : ''))))));
$logoPath = hmpr_logo_path(isset($_Logo) ? $_Logo : '');
$printTitle = $printRequisitionId !== '' ? 'Requisition Printout' : 'Past Requisitions Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo hmpr_esc($printTitle); ?> - LiveCampus</title>
<style>
body {
    margin: 0;
    background: #f4f7fb;
    color: #1f2937;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}
.print-shell {
    max-width: 1080px;
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
    margin: 24px 0 12px;
    font-size: 1.05rem;
    color: #112033;
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
.print-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}
.print-detail {
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid #dbe3ea;
    background: #f8fbff;
}
.print-detail span {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.print-detail strong {
    display: block;
    margin-top: 8px;
    line-height: 1.55;
}
.print-note {
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 16px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.6;
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
    .print-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="print-shell">
    <div class="print-toolbar">
        <a href="headmaster-page.php#hm-requisition-history">Back to Headmaster Dashboard</a>
        <button type="button" onclick="window.print()">Print Now</button>
    </div>

    <div class="print-card">
        <div class="print-header">
            <?php if ($logoPath !== '') { ?>
            <img src="<?php echo hmpr_esc($logoPath); ?>" alt="<?php echo hmpr_esc($schoolName); ?>">
            <?php } ?>
            <div>
                <h1><?php echo hmpr_esc($schoolName); ?></h1>
                <p><?php echo hmpr_esc($schoolAddress); ?><?php echo $schoolAddress !== '' && $schoolLocation !== '' ? ' | ' : ''; ?><?php echo hmpr_esc($schoolLocation); ?></p>
                <?php if ($schoolPhone !== '') { ?>
                <p><?php echo hmpr_esc($schoolPhone); ?></p>
                <?php } ?>
            </div>
        </div>

        <div class="print-meta">
            <span><?php echo hmpr_esc($printTitle); ?></span>
            <span>Generated on <?php echo hmpr_esc(date("d M Y, H:i")); ?></span>
        </div>

        <?php if (empty($rows)) { ?>
        <div class="print-empty">No requisition matched this print view.</div>
        <?php } elseif ($printRequisitionId !== '' && count($rows) === 1) { ?>
        <?php $row = $rows[0]; ?>
        <h2 class="print-section-title">Requisition Details</h2>
        <div class="print-detail-grid">
            <div class="print-detail"><span>Requisition ID</span><strong><?php echo hmpr_esc($row['requisitionid']); ?></strong></div>
            <div class="print-detail"><span>Status</span><strong><?php echo hmpr_esc($row['status_label']); ?></strong></div>
            <div class="print-detail"><span>Requester</span><strong><?php echo hmpr_esc($row['requested_by_name']); ?> | <?php echo hmpr_esc($row['requestorigin_label']); ?> request</strong></div>
            <div class="print-detail"><span>Request Date</span><strong><?php echo hmpr_esc(hmpr_date($row['requestdate'])); ?></strong></div>
            <div class="print-detail"><span>Item</span><strong><?php echo hmpr_esc($row['itemname']); ?></strong></div>
            <div class="print-detail"><span>Quantity</span><strong><?php echo hmpr_esc(storekeeper_format_quantity($row['quantity'])); ?> <?php echo hmpr_esc($row['unitname']); ?></strong></div>
            <div class="print-detail"><span>Need By</span><strong><?php echo hmpr_esc(hmpr_date($row['needbydate'])); ?></strong></div>
            <div class="print-detail"><span>Requested Slot</span><strong><?php echo hmpr_esc(matron_requisition_slot_label($row['dayname'], $row['mealtime'])); ?></strong></div>
            <div class="print-detail"><span>Purpose</span><strong><?php echo hmpr_esc($row['purpose']); ?></strong></div>
            <div class="print-detail"><span>Store Review</span><strong><?php echo hmpr_esc(trim((string)$row['store_decision_by_name']) !== '' ? $row['store_decision_by_name'] : 'Storekeeper'); ?></strong></div>
            <div class="print-detail"><span>Headmaster Decision</span><strong><?php echo hmpr_esc(trim((string)$row['head_decision_by_name']) !== '' ? $row['head_decision_by_name'] : 'Not recorded'); ?></strong></div>
            <div class="print-detail"><span>Final Notes</span><strong><?php echo hmpr_esc(trim((string)$row['notes']) !== '' ? $row['notes'] : 'No additional note.'); ?></strong></div>
        </div>
        <?php if (trim((string)$row['stage_note']) !== '') { ?>
        <div class="print-note"><?php echo hmpr_esc($row['stage_note']); ?></div>
        <?php } ?>
        <?php if (!empty($row['is_headmaster_adjusted'])) { ?>
        <div class="print-note">The headmaster adjusted the final requisition details before approval.</div>
        <?php } ?>
        <?php } else { ?>
        <h2 class="print-section-title">Past Requisitions</h2>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Requester</th>
                    <th>Item</th>
                    <th>Purpose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo hmpr_esc(hmpr_date($row['requestdate'])); ?></td>
                    <td>
                        <?php echo hmpr_esc($row['requested_by_name']); ?>
                        <small><?php echo hmpr_esc($row['requestorigin_label']); ?> request</small>
                    </td>
                    <td>
                        <?php echo hmpr_esc($row['itemname']); ?>
                        <small><?php echo hmpr_esc(storekeeper_format_quantity($row['quantity'])); ?> <?php echo hmpr_esc($row['unitname']); ?></small>
                    </td>
                    <td>
                        <?php echo hmpr_esc($row['purpose']); ?>
                        <?php if (trim((string)$row['stage_note']) !== '') { ?>
                        <small><?php echo hmpr_esc($row['stage_note']); ?></small>
                        <?php } ?>
                    </td>
                    <td><?php echo hmpr_esc($row['status_label']); ?></td>
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

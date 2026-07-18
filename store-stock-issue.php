<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("storekeeper-utils.php");
ensure_storekeeper_tables($con);

if (!storekeeper_can_manage_module($con, 'stores_management')) {
    header("location:" . storekeeper_landing_page());
    exit();
}

if (!function_exists('sk_issue_date')) {
function sk_issue_date($value)
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

$_Form = array(
    'issuedate' => date('Y-m-d'),
    'storeitemid' => '',
    'storeitemlabel' => '',
    'issuedto' => '',
    'purpose' => '',
    'quantity' => '',
    'notes' => ''
);
$_Items = storekeeper_active_items($con);

if (isset($_POST['save_issue'])) {
    $_Form['issuedate'] = trim((string)(isset($_POST['issuedate']) ? $_POST['issuedate'] : date('Y-m-d')));
    $_Form['storeitemid'] = trim((string)(isset($_POST['storeitemid']) ? $_POST['storeitemid'] : ''));
    $_Form['storeitemlabel'] = trim((string)(isset($_POST['storeitemlabel']) ? $_POST['storeitemlabel'] : ''));
    $_Form['issuedto'] = trim((string)(isset($_POST['issuedto']) ? $_POST['issuedto'] : ''));
    $_Form['purpose'] = trim((string)(isset($_POST['purpose']) ? $_POST['purpose'] : ''));
    $_Form['quantity'] = trim((string)(isset($_POST['quantity']) ? $_POST['quantity'] : ''));
    $_Form['notes'] = trim((string)(isset($_POST['notes']) ? $_POST['notes'] : ''));

    if ($_Form['storeitemid'] === '' && $_Form['storeitemlabel'] !== '') {
        $_ResolvedItem = storekeeper_find_item_by_picker_label($con, $_Form['storeitemlabel'], $_Items);
        if ($_ResolvedItem) {
            $_Form['storeitemid'] = (string)$_ResolvedItem['storeitemid'];
        }
    }
    $_ItemRow = storekeeper_get_item_row($con, $_Form['storeitemid']);
    if (!$_ItemRow && $_Form['storeitemlabel'] !== '') {
        $_ItemRow = storekeeper_find_item_by_picker_label($con, $_Form['storeitemlabel'], $_Items);
        if ($_ItemRow) {
            $_Form['storeitemid'] = (string)$_ItemRow['storeitemid'];
        }
    }

    if (!$_ItemRow || (string)$_ItemRow['status'] !== 'active') {
        $_Message = storekeeper_flash_html('error', 'Please select a valid active item.');
    } elseif ($_Form['issuedate'] === '') {
        $_Message = storekeeper_flash_html('error', 'Issue date is required.');
    } elseif ($_Form['issuedto'] === '') {
        $_Message = storekeeper_flash_html('error', 'Issued-to name is required.');
    } elseif ($_Form['quantity'] === '' || !is_numeric($_Form['quantity']) || (float)$_Form['quantity'] <= 0) {
        $_Message = storekeeper_flash_html('error', 'Quantity must be a valid number greater than zero.');
    } else {
        $_AvailableBalance = storekeeper_item_balance($con, $_Form['storeitemid']);
        if ((float)$_Form['quantity'] > $_AvailableBalance) {
            $_Message = storekeeper_flash_html('error', 'Issue quantity cannot be more than the available balance of ' . storekeeper_format_quantity($_AvailableBalance) . ' ' . storekeeper_esc($_ItemRow['unitname']) . '.');
        } else {
            include("code.php");
            $_IssueIdEsc = mysqli_real_escape_string($con, trim((string)$code));
            $_ItemIdEsc = mysqli_real_escape_string($con, $_Form['storeitemid']);
            $_IssueDateEsc = mysqli_real_escape_string($con, $_Form['issuedate']);
            $_IssuedToEsc = mysqli_real_escape_string($con, $_Form['issuedto']);
            $_PurposeEsc = mysqli_real_escape_string($con, $_Form['purpose']);
            $_Quantity = number_format((float)$_Form['quantity'], 2, '.', '');
            $_NotesEsc = mysqli_real_escape_string($con, $_Form['notes']);
            $_RecordedByEsc = mysqli_real_escape_string($con, isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '');

            $_SQL = mysqli_query($con, "INSERT INTO tblstoreissue
                (issueid,storeitemid,issuedate,issuedto,purpose,quantity,notes,status,datetimeentry,recordedby)
                VALUES
                ('$_IssueIdEsc','$_ItemIdEsc','$_IssueDateEsc','$_IssuedToEsc','$_PurposeEsc','$_Quantity','$_NotesEsc','posted',NOW(),'$_RecordedByEsc')");
            if ($_SQL) {
                $_SESSION['Message'] = storekeeper_flash_html('success', 'Stock issue saved successfully.');
                header("location:store-stock-issue.php");
                exit();
            }
            $_Message = storekeeper_flash_html('error', 'Failed to save stock issue: ' . storekeeper_esc(mysqli_error($con)));
        }
    }
}

if (isset($_GET['void_issue'])) {
    $_IssueId = trim((string)$_GET['void_issue']);
    if ($_IssueId !== '') {
        $_IssueIdEsc = mysqli_real_escape_string($con, $_IssueId);
        $_IssueRes = mysqli_query($con, "SELECT issueid,status FROM tblstoreissue WHERE issueid='$_IssueIdEsc' LIMIT 1");
        if ($_IssueRes && ($_IssueRow = mysqli_fetch_array($_IssueRes, MYSQLI_ASSOC))) {
            if ((string)$_IssueRow['status'] !== 'posted') {
                $_SESSION['Message'] = storekeeper_flash_html('warning', 'This issue entry is already voided.');
            } else {
                $_SQL = mysqli_query($con, "UPDATE tblstoreissue SET status='void' WHERE issueid='$_IssueIdEsc' LIMIT 1");
                if ($_SQL) {
                    $_SESSION['Message'] = storekeeper_flash_html('warning', 'Issue entry voided successfully.');
                } else {
                    $_SESSION['Message'] = storekeeper_flash_html('error', 'Failed to void issue entry: ' . storekeeper_esc(mysqli_error($con)));
                }
            }
        }
    }
    header("location:store-stock-issue.php");
    exit();
}

$_RecentIssues = storekeeper_recent_issues($con, 18);
$_Summary = storekeeper_dashboard_summary($con);
$_LowStockRows = array();
foreach (storekeeper_fetch_balance_rows($con) as $_Row) {
    if ((float)$_Row['current_balance'] <= 0 || ((float)$_Row['reorderlevel'] > 0 && (float)$_Row['current_balance'] <= (float)$_Row['reorderlevel'])) {
        $_LowStockRows[] = $_Row;
    }
    if (count($_LowStockRows) >= 6) {
        break;
    }
}
$_SelectedIssueItemName = storekeeper_selected_item_name($con, $_Form['storeitemid'], $_Items);
$_SelectedIssueItemLabel = '';
foreach ($_Items as $_Item) {
    if ($_Form['storeitemid'] !== '' && $_Form['storeitemid'] === (string)$_Item['storeitemid']) {
        $_SelectedIssueItemLabel = storekeeper_item_picker_label($_Item);
        break;
    }
}
if ($_SelectedIssueItemLabel === '' && $_Form['storeitemlabel'] !== '') {
    $_SelectedIssueItemLabel = $_Form['storeitemlabel'];
}
$_SelectedIssueRecipient = trim((string)$_Form['issuedto']) !== '' ? trim((string)$_Form['issuedto']) : 'selected recipient';
$_SelectedIssueSummary = $_SelectedIssueItemName . ' to ' . $_SelectedIssueRecipient;
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
                <span class="sk-kicker"><i class="fa fa-upload"></i> Stock Issue</span>
                <h1>Release stock out of store with quantity control.</h1>
                <p>Every issue entry reduces the live balance for the selected item, so this page checks available stock before posting the movement.</p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="storekeeper-dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                    <a class="sk-link-chip" href="store-item-entry.php"><i class="fa fa-tags"></i> Item Master</a>
                    <a class="sk-link-chip" href="store-stock-receipt.php"><i class="fa fa-download"></i> Stock Receipt</a>
                    <a class="sk-link-chip" href="store-balance-report.php"><i class="fa fa-line-chart"></i> Balance Report</a>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Issues This Week</span>
                    <strong><?php echo number_format((int)$_Summary['issue_count_week']); ?></strong>
                    <small>Posted issue entries during the last seven days.</small>
                </article>
                <article class="sk-stat">
                    <span>Low Stock</span>
                    <strong><?php echo number_format((int)$_Summary['low_stock_items']); ?></strong>
                    <small>Items that need caution before further issuing.</small>
                </article>
                <article class="sk-stat">
                    <span>Out Of Stock</span>
                    <strong><?php echo number_format((int)$_Summary['out_of_stock_items']); ?></strong>
                    <small>Items that can no longer be issued until restocked.</small>
                </article>
                <article class="sk-stat">
                    <span>Active Items</span>
                    <strong><?php echo number_format((int)$_Summary['active_items']); ?></strong>
                    <small>Active item lines currently available in the module.</small>
                </article>
            </div>
        </section>

        <?php if ($_Message !== "") { ?>
        <?php echo $_Message; ?>
        <?php } ?>

        <div class="sk-layout">
            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>New Issue Entry</h2>
                        <p>Use a clear issued-to name so stock handover remains easy to audit later.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_Items)) { ?>
                    <div class="sk-empty">Add store items first before recording stock issues.</div>
                    <?php } else { ?>
                    <form method="post" class="sk-form" action="store-stock-issue.php">
                        <div class="sk-form-grid">
                            <div class="sk-field">
                                <label for="issuedate">Issue Date</label>
                                <input type="date" id="issuedate" name="issuedate" value="<?php echo storekeeper_esc($_Form['issuedate']); ?>" required>
                            </div>
                            <div class="sk-field">
                                <label for="storeitempicker">Item</label>
                                <input type="hidden" id="storeitemid" name="storeitemid" value="<?php echo storekeeper_esc($_Form['storeitemid']); ?>">
                                <input type="text" id="storeitempicker" name="storeitemlabel" value="<?php echo storekeeper_esc($_SelectedIssueItemLabel); ?>" list="storeitempickerlist" placeholder="Select or type item name" autocomplete="off" oninput="if (window.storekeeperSyncIssueSummary) { window.storekeeperSyncIssueSummary(); }" required>
                                <datalist id="storeitempickerlist">
                                    <?php foreach ($_Items as $_Item) { ?>
                                    <option value="<?php echo storekeeper_esc(storekeeper_item_picker_label($_Item)); ?>" data-itemid="<?php echo storekeeper_esc($_Item['storeitemid']); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="issuedto">Issued To</label>
                                <input type="text" id="issuedto" name="issuedto" value="<?php echo storekeeper_esc($_Form['issuedto']); ?>" placeholder="e.g. Matron, Kitchen, Dining Hall" oninput="if (window.storekeeperSyncIssueSummary) { window.storekeeperSyncIssueSummary(); }" required>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="selectedissuesummary">Selected Issue</label>
                                <div id="selectedissuesummary" class="sk-readonly-field"><?php echo storekeeper_esc($_SelectedIssueSummary); ?></div>
                                <small class="sk-field-help">This shows the item and recipient currently selected for the issue entry.</small>
                            </div>
                            <div class="sk-field">
                                <label for="purpose">Purpose</label>
                                <input type="text" id="purpose" name="purpose" value="<?php echo storekeeper_esc($_Form['purpose']); ?>" placeholder="Optional">
                            </div>
                            <div class="sk-field">
                                <label for="quantity">Quantity Issued</label>
                                <input type="number" id="quantity" name="quantity" value="<?php echo storekeeper_esc($_Form['quantity']); ?>" min="0.01" step="0.01" required>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" placeholder="Optional remarks about collection, purpose, or handover details."><?php echo storekeeper_esc($_Form['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_issue" class="sk-button"><i class="fa fa-save"></i> Save Issue</button>
                        </div>
                    </form>
                    <?php } ?>
                </div>
            </section>

            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Low Stock Reminder</h2>
                        <p>These items are already tight, so double-check before issuing more.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_LowStockRows)) { ?>
                    <div class="sk-empty">No low-stock alerts right now.</div>
                    <?php } else { ?>
                    <div class="sk-list">
                        <?php foreach ($_LowStockRows as $_Row) { ?>
                        <div class="sk-list-item">
                            <strong><?php echo storekeeper_esc($_Row['itemname']); ?></strong>
                            <div class="sk-inline-meta">
                                <span>Balance: <?php echo storekeeper_format_quantity($_Row['current_balance']); ?> <?php echo storekeeper_esc($_Row['unitname']); ?></span>
                                <span>Reorder: <?php echo storekeeper_format_quantity($_Row['reorderlevel']); ?></span>
                            </div>
                            <div style="margin-top:10px;"><?php echo storekeeper_stock_badge_html($_Row['current_balance'], $_Row['reorderlevel']); ?></div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </div>

        <section class="sk-panel">
            <div class="sk-panel__header">
                <div>
                    <h2>Recent Issue Entries</h2>
                    <p>Voiding an issue restores that quantity back into the live balance.</p>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_RecentIssues)) { ?>
                <div class="sk-empty">No stock issue entries have been recorded yet.</div>
                <?php } else { ?>
                <div class="sk-table-wrap">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Actions</th>
                                <th>Date</th>
                                <th>Issue</th>
                                <th>Item</th>
                                <th>Issued To</th>
                                <th>Purpose</th>
                                <th>Quantity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_RecentIssues as $_Issue) { ?>
                            <tr>
                                <td>
                                    <?php if ((string)$_Issue['status'] === 'posted') { ?>
                                    <a class="sk-inline-action" onclick="return confirm('Void this issue entry?');" href="store-stock-issue.php?void_issue=<?php echo urlencode($_Issue['issueid']); ?>"><i class="fa fa-ban"></i> Void</a>
                                    <?php } else { ?>
                                    <?php echo storekeeper_status_badge_html($_Issue['status']); ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo sk_issue_date($_Issue['issuedate']); ?></td>
                                <td><?php echo storekeeper_esc($_Issue['issueid']); ?></td>
                                <td>
                                    <?php echo storekeeper_esc($_Issue['itemname']); ?>
                                    <small><?php echo storekeeper_esc($_Issue['unitname']); ?></small>
                                </td>
                                <td><?php echo storekeeper_esc($_Issue['issuedto']); ?></td>
                                <td><?php echo storekeeper_esc($_Issue['purpose']); ?></td>
                                <td><?php echo storekeeper_format_quantity($_Issue['quantity']); ?> <?php echo storekeeper_esc($_Issue['unitname']); ?></td>
                                <td><?php echo storekeeper_status_badge_html($_Issue['status']); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </section>
    </div>
</main>
<script>
(function () {
    var form = document.querySelector('form[action="store-stock-issue.php"]');
    if (!form) {
        return;
    }

    var itemIdField = form.querySelector('#storeitemid');
    var itemField = form.querySelector('#storeitempicker');
    var issuedToField = form.querySelector('#issuedto');
    var summaryField = form.querySelector('#selectedissuesummary');
    var itemList = document.getElementById('storeitempickerlist');

    function safeTrim(value) {
        return (value || '').replace(/^\s+|\s+$/g, '');
    }

    function setNodeText(node, value) {
        if (!node) {
            return;
        }
        if (typeof node.textContent !== 'undefined') {
            node.textContent = value;
        } else {
            node.innerText = value;
        }
    }

    function findListOptionByValue(list, value) {
        if (!list) {
            return null;
        }
        var normalizedValue = safeTrim(value).toLowerCase();
        if (normalizedValue === '') {
            return null;
        }
        for (var optionIndex = 0; optionIndex < list.options.length; optionIndex++) {
            var option = list.options[optionIndex];
            if (safeTrim(option.value).toLowerCase() === normalizedValue) {
                return option;
            }
        }
        return null;
    }

    function syncHiddenItemId() {
        if (!itemIdField) {
            return;
        }
        var matchedOption = findListOptionByValue(itemList, itemField ? itemField.value : '');
        itemIdField.value = matchedOption ? (matchedOption.getAttribute('data-itemid') || '') : '';
    }

    function currentItemLabel() {
        if (!itemField) {
            return 'No item selected yet';
        }
        var itemValue = safeTrim(itemField.value);
        if (itemValue === '') {
            return 'No item selected yet';
        }
        if (itemValue.indexOf(' (') > -1) {
            itemValue = itemValue.split(' (')[0];
        }
        itemValue = safeTrim(itemValue);
        return itemValue !== '' ? itemValue : 'Selected item';
    }

    function syncIssueSummary() {
        syncHiddenItemId();
        if (!summaryField) {
            return;
        }
        var recipient = issuedToField && safeTrim(issuedToField.value) !== '' ? safeTrim(issuedToField.value) : 'selected recipient';
        setNodeText(summaryField, currentItemLabel() + ' to ' + recipient);
    }

    window.storekeeperSyncIssueSummary = syncIssueSummary;
    if (itemField) {
        itemField.addEventListener('change', syncIssueSummary);
        itemField.addEventListener('blur', syncIssueSummary);
    }
    form.addEventListener('submit', syncIssueSummary);
    syncIssueSummary();
})();
</script>
</body>
</html>

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

if (!function_exists('sk_receipt_date')) {
function sk_receipt_date($value)
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
    'receiptdate' => date('Y-m-d'),
    'storeitemid' => '',
    'storeitemlabel' => '',
    'source_name' => '',
    'quantity' => '',
    'unitcost' => '',
    'batchnumber' => '',
    'expirydate' => '',
    'notes' => ''
);
$_Items = storekeeper_active_items($con);

if (isset($_POST['save_receipt'])) {
    $_Form['receiptdate'] = trim((string)(isset($_POST['receiptdate']) ? $_POST['receiptdate'] : date('Y-m-d')));
    $_Form['storeitemid'] = trim((string)(isset($_POST['storeitemid']) ? $_POST['storeitemid'] : ''));
    $_Form['storeitemlabel'] = trim((string)(isset($_POST['storeitemlabel']) ? $_POST['storeitemlabel'] : ''));
    $_Form['source_name'] = trim((string)(isset($_POST['source_name']) ? $_POST['source_name'] : ''));
    $_Form['quantity'] = trim((string)(isset($_POST['quantity']) ? $_POST['quantity'] : ''));
    $_Form['unitcost'] = trim((string)(isset($_POST['unitcost']) ? $_POST['unitcost'] : ''));
    $_Form['batchnumber'] = trim((string)(isset($_POST['batchnumber']) ? $_POST['batchnumber'] : ''));
    $_Form['expirydate'] = trim((string)(isset($_POST['expirydate']) ? $_POST['expirydate'] : ''));
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
    } elseif ($_Form['receiptdate'] === '') {
        $_Message = storekeeper_flash_html('error', 'Receipt date is required.');
    } elseif ($_Form['source_name'] === '') {
        $_Message = storekeeper_flash_html('error', 'Source or supplier name is required.');
    } elseif ($_Form['quantity'] === '' || !is_numeric($_Form['quantity']) || (float)$_Form['quantity'] <= 0) {
        $_Message = storekeeper_flash_html('error', 'Quantity must be a valid number greater than zero.');
    } elseif ($_Form['unitcost'] !== '' && (!is_numeric($_Form['unitcost']) || (float)$_Form['unitcost'] < 0)) {
        $_Message = storekeeper_flash_html('error', 'Unit cost must be a valid number greater than or equal to zero.');
    } else {
        include("code.php");
        $_ReceiptIdEsc = mysqli_real_escape_string($con, trim((string)$code));
        $_ItemIdEsc = mysqli_real_escape_string($con, $_Form['storeitemid']);
        $_ReceiptDateEsc = mysqli_real_escape_string($con, $_Form['receiptdate']);
        $_SourceEsc = mysqli_real_escape_string($con, $_Form['source_name']);
        $_Quantity = number_format((float)$_Form['quantity'], 2, '.', '');
        $_UnitCost = number_format((float)($_Form['unitcost'] === '' ? 0 : $_Form['unitcost']), 2, '.', '');
        $_BatchEsc = mysqli_real_escape_string($con, $_Form['batchnumber']);
        $_NotesEsc = mysqli_real_escape_string($con, $_Form['notes']);
        $_RecordedByEsc = mysqli_real_escape_string($con, isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '');
        $_ExpirySql = "NULL";
        if ($_Form['expirydate'] !== '') {
            $_ExpirySql = "'" . mysqli_real_escape_string($con, $_Form['expirydate']) . "'";
        }

        $_SQL = mysqli_query($con, "INSERT INTO tblstorereceipt
            (receiptid,storeitemid,receiptdate,source_name,quantity,unitcost,batchnumber,expirydate,notes,status,datetimeentry,recordedby)
            VALUES
            ('$_ReceiptIdEsc','$_ItemIdEsc','$_ReceiptDateEsc','$_SourceEsc','$_Quantity','$_UnitCost','$_BatchEsc',$_ExpirySql,'$_NotesEsc','posted',NOW(),'$_RecordedByEsc')");
        if ($_SQL) {
            $_SESSION['Message'] = storekeeper_flash_html('success', 'Stock receipt saved successfully.');
            header("location:store-stock-receipt.php");
            exit();
        }
        $_Message = storekeeper_flash_html('error', 'Failed to save stock receipt: ' . storekeeper_esc(mysqli_error($con)));
    }
}

if (isset($_GET['void_receipt'])) {
    $_ReceiptId = trim((string)$_GET['void_receipt']);
    if ($_ReceiptId !== '') {
        $_ReceiptIdEsc = mysqli_real_escape_string($con, $_ReceiptId);
        $_ReceiptRes = mysqli_query($con, "SELECT receiptid,storeitemid,quantity,status
            FROM tblstorereceipt
            WHERE receiptid='$_ReceiptIdEsc'
            LIMIT 1");
        if ($_ReceiptRes && ($_ReceiptRow = mysqli_fetch_array($_ReceiptRes, MYSQLI_ASSOC))) {
            if ((string)$_ReceiptRow['status'] !== 'posted') {
                $_SESSION['Message'] = storekeeper_flash_html('warning', 'This receipt is already voided.');
            } else {
                $_CurrentBalance = storekeeper_item_balance($con, $_ReceiptRow['storeitemid']);
                $_NewBalance = $_CurrentBalance - (float)$_ReceiptRow['quantity'];
                if ($_NewBalance < 0) {
                    $_SESSION['Message'] = storekeeper_flash_html('error', 'This receipt cannot be voided because later issues already depend on the stock received.');
                } else {
                    $_SQL = mysqli_query($con, "UPDATE tblstorereceipt SET status='void' WHERE receiptid='$_ReceiptIdEsc' LIMIT 1");
                    if ($_SQL) {
                        $_SESSION['Message'] = storekeeper_flash_html('warning', 'Receipt entry voided successfully.');
                    } else {
                        $_SESSION['Message'] = storekeeper_flash_html('error', 'Failed to void receipt entry: ' . storekeeper_esc(mysqli_error($con)));
                    }
                }
            }
        }
    }
    header("location:store-stock-receipt.php");
    exit();
}

$_RecentReceipts = storekeeper_recent_receipts($con, 18);
$_Summary = storekeeper_dashboard_summary($con);
$_SelectedReceiptItemName = storekeeper_selected_item_name($con, $_Form['storeitemid'], $_Items);
$_SelectedReceiptItemLabel = '';
foreach ($_Items as $_Item) {
    if ($_Form['storeitemid'] !== '' && $_Form['storeitemid'] === (string)$_Item['storeitemid']) {
        $_SelectedReceiptItemLabel = storekeeper_item_picker_label($_Item);
        break;
    }
}
if ($_SelectedReceiptItemLabel === '' && $_Form['storeitemlabel'] !== '') {
    $_SelectedReceiptItemLabel = $_Form['storeitemlabel'];
}
$_SelectedReceiptSource = trim((string)$_Form['source_name']) !== '' ? trim((string)$_Form['source_name']) : 'selected source';
$_SelectedReceiptSummary = $_SelectedReceiptItemName . ' from ' . $_SelectedReceiptSource;
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
                <span class="sk-kicker"><i class="fa fa-download"></i> Stock Receipt</span>
                <h1>Record goods received into the main store.</h1>
                <p>Capture every food item or domestic supply that comes into the school store so the running stock balance stays accurate from day one.</p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="storekeeper-dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                    <a class="sk-link-chip" href="store-item-entry.php"><i class="fa fa-tags"></i> Item Master</a>
                    <a class="sk-link-chip" href="store-stock-issue.php"><i class="fa fa-upload"></i> Stock Issue</a>
                    <a class="sk-link-chip" href="store-balance-report.php"><i class="fa fa-line-chart"></i> Balance Report</a>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Items Available</span>
                    <strong><?php echo number_format(count($_Items)); ?></strong>
                    <small>Active item lines ready to receive new stock.</small>
                </article>
                <article class="sk-stat">
                    <span>Receipts This Week</span>
                    <strong><?php echo number_format((int)$_Summary['receipt_count_week']); ?></strong>
                    <small>Posted receipt entries within the last seven days.</small>
                </article>
                <article class="sk-stat">
                    <span>Low Stock</span>
                    <strong><?php echo number_format((int)$_Summary['low_stock_items']); ?></strong>
                    <small>Useful reminder of items that still need replenishment.</small>
                </article>
                <article class="sk-stat">
                    <span>Out Of Stock</span>
                    <strong><?php echo number_format((int)$_Summary['out_of_stock_items']); ?></strong>
                    <small>Items whose balance is fully exhausted right now.</small>
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
                        <h2>New Receipt Entry</h2>
                        <p>Each receipt entry adds stock to the selected store item.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_Items)) { ?>
                    <div class="sk-empty">Add store items first before recording stock receipts.</div>
                    <?php } else { ?>
                    <form method="post" class="sk-form" action="store-stock-receipt.php">
                        <div class="sk-form-grid">
                            <div class="sk-field">
                                <label for="receiptdate">Receipt Date</label>
                                <input type="date" id="receiptdate" name="receiptdate" value="<?php echo storekeeper_esc($_Form['receiptdate']); ?>" required>
                            </div>
                            <div class="sk-field">
                                <label for="storeitempicker">Item</label>
                                <input type="hidden" id="storeitemid" name="storeitemid" value="<?php echo storekeeper_esc($_Form['storeitemid']); ?>">
                                <input type="text" id="storeitempicker" name="storeitemlabel" value="<?php echo storekeeper_esc($_SelectedReceiptItemLabel); ?>" list="storeitempickerlist" placeholder="Select or type item name" autocomplete="off" oninput="if (window.storekeeperSyncReceiptSummary) { window.storekeeperSyncReceiptSummary(); }" required>
                                <datalist id="storeitempickerlist">
                                    <?php foreach ($_Items as $_Item) { ?>
                                    <option value="<?php echo storekeeper_esc(storekeeper_item_picker_label($_Item)); ?>" data-itemid="<?php echo storekeeper_esc($_Item['storeitemid']); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="source_name">Source / Supplier</label>
                                <input type="text" id="source_name" name="source_name" value="<?php echo storekeeper_esc($_Form['source_name']); ?>" placeholder="e.g. School purchase, Donation, Main supplier" oninput="if (window.storekeeperSyncReceiptSummary) { window.storekeeperSyncReceiptSummary(); }" required>
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="selectedreceiptsummary">Selected Receipt</label>
                                <div id="selectedreceiptsummary" class="sk-readonly-field"><?php echo storekeeper_esc($_SelectedReceiptSummary); ?></div>
                                <small class="sk-field-help">This shows the item and source currently selected for the receipt entry.</small>
                            </div>
                            <div class="sk-field">
                                <label for="quantity">Quantity Received</label>
                                <input type="number" id="quantity" name="quantity" value="<?php echo storekeeper_esc($_Form['quantity']); ?>" min="0.01" step="0.01" required>
                            </div>
                            <div class="sk-field">
                                <label for="unitcost">Unit Cost</label>
                                <input type="number" id="unitcost" name="unitcost" value="<?php echo storekeeper_esc($_Form['unitcost']); ?>" min="0" step="0.01" placeholder="Optional">
                            </div>
                            <div class="sk-field">
                                <label for="batchnumber">Batch / Lot</label>
                                <input type="text" id="batchnumber" name="batchnumber" value="<?php echo storekeeper_esc($_Form['batchnumber']); ?>" placeholder="Optional">
                            </div>
                            <div class="sk-field">
                                <label for="expirydate">Expiry Date</label>
                                <input type="date" id="expirydate" name="expirydate" value="<?php echo storekeeper_esc($_Form['expirydate']); ?>">
                            </div>
                            <div class="sk-field sk-field--full">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" placeholder="Optional remarks about condition, pack size, or receipt note."><?php echo storekeeper_esc($_Form['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_receipt" class="sk-button"><i class="fa fa-save"></i> Save Receipt</button>
                        </div>
                    </form>
                    <?php } ?>
                </div>
            </section>

            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Recent Receipt Entries</h2>
                        <p>Voiding a receipt will remove it from stock balances.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_RecentReceipts)) { ?>
                    <div class="sk-empty">No stock receipt entries have been recorded yet.</div>
                    <?php } else { ?>
                    <div class="sk-table-wrap">
                        <table class="sk-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Date</th>
                                    <th>Receipt</th>
                                    <th>Item</th>
                                    <th>Source</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_RecentReceipts as $_Receipt) { ?>
                                <tr>
                                    <td>
                                        <?php if ((string)$_Receipt['status'] === 'posted') { ?>
                                        <a class="sk-inline-action" onclick="return confirm('Void this receipt entry?');" href="store-stock-receipt.php?void_receipt=<?php echo urlencode($_Receipt['receiptid']); ?>"><i class="fa fa-ban"></i> Void</a>
                                        <?php } else { ?>
                                        <?php echo storekeeper_status_badge_html($_Receipt['status']); ?>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo sk_receipt_date($_Receipt['receiptdate']); ?></td>
                                    <td>
                                        <?php echo storekeeper_esc($_Receipt['receiptid']); ?>
                                        <?php if (trim((string)$_Receipt['batchnumber']) !== '') { ?>
                                        <small>Batch: <?php echo storekeeper_esc($_Receipt['batchnumber']); ?></small>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php echo storekeeper_esc($_Receipt['itemname']); ?>
                                        <small><?php echo storekeeper_esc($_Receipt['unitname']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo storekeeper_esc($_Receipt['source_name']); ?>
                                        <?php if (trim((string)$_Receipt['expirydate']) !== '' && $_Receipt['expirydate'] !== '0000-00-00') { ?>
                                        <small>Expiry: <?php echo sk_receipt_date($_Receipt['expirydate']); ?></small>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php echo storekeeper_format_quantity($_Receipt['quantity']); ?> <?php echo storekeeper_esc($_Receipt['unitname']); ?>
                                        <?php if ((float)$_Receipt['unitcost'] > 0) { ?>
                                        <small>Unit cost: <?php echo number_format((float)$_Receipt['unitcost'], 2); ?></small>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo storekeeper_status_badge_html($_Receipt['status']); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </div>
    </div>
</main>
<script>
(function () {
    var form = document.querySelector('form[action="store-stock-receipt.php"]');
    if (!form) {
        return;
    }

    var itemIdField = form.querySelector('#storeitemid');
    var itemField = form.querySelector('#storeitempicker');
    var sourceField = form.querySelector('#source_name');
    var summaryField = form.querySelector('#selectedreceiptsummary');
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

    function syncReceiptSummary() {
        syncHiddenItemId();
        if (!summaryField) {
            return;
        }
        var sourceName = sourceField && safeTrim(sourceField.value) !== '' ? safeTrim(sourceField.value) : 'selected source';
        setNodeText(summaryField, currentItemLabel() + ' from ' + sourceName);
    }

    window.storekeeperSyncReceiptSummary = syncReceiptSummary;
    if (itemField) {
        itemField.addEventListener('change', syncReceiptSummary);
        itemField.addEventListener('blur', syncReceiptSummary);
    }
    form.addEventListener('submit', syncReceiptSummary);
    syncReceiptSummary();
})();
</script>
</body>
</html>

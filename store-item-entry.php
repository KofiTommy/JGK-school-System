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

$_Message = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
unset($_SESSION['Message']);

$_Form = array(
    'storeitemid' => '',
    'itemname' => '',
    'itemcategory' => 'Food Item',
    'unitname' => 'bag',
    'reorderlevel' => '0',
    'description' => '',
    'status' => 'active'
);
$_EditingItem = false;

if (isset($_GET['edit_item'])) {
    $_EditItemId = trim((string)$_GET['edit_item']);
    $_EditRow = storekeeper_get_item_row($con, $_EditItemId);
    if ($_EditRow) {
        $_Form = array(
            'storeitemid' => (string)$_EditRow['storeitemid'],
            'itemname' => (string)$_EditRow['itemname'],
            'itemcategory' => (string)$_EditRow['itemcategory'],
            'unitname' => (string)$_EditRow['unitname'],
            'reorderlevel' => storekeeper_format_quantity($_EditRow['reorderlevel']),
            'description' => (string)$_EditRow['description'],
            'status' => (string)$_EditRow['status']
        );
        $_EditingItem = true;
    } else {
        $_Message = storekeeper_flash_html('error', 'The selected item could not be found.');
    }
}

if (isset($_POST['save_item'])) {
    $_Form['storeitemid'] = trim((string)(isset($_POST['storeitemid']) ? $_POST['storeitemid'] : ''));
    $_Form['itemname'] = trim((string)(isset($_POST['itemname']) ? $_POST['itemname'] : ''));
    $_Form['itemcategory'] = trim((string)(isset($_POST['itemcategory']) ? $_POST['itemcategory'] : 'Food Item'));
    $_Form['unitname'] = trim((string)(isset($_POST['unitname']) ? $_POST['unitname'] : ''));
    $_Form['reorderlevel'] = trim((string)(isset($_POST['reorderlevel']) ? $_POST['reorderlevel'] : '0'));
    $_Form['description'] = trim((string)(isset($_POST['description']) ? $_POST['description'] : ''));
    $_Form['status'] = strtolower(trim((string)(isset($_POST['status']) ? $_POST['status'] : 'active')));
    $_EditingItem = $_Form['storeitemid'] !== '';

    if ($_Form['itemname'] === '') {
        $_Message = storekeeper_flash_html('error', 'Item name is required.');
    } elseif ($_Form['unitname'] === '') {
        $_Message = storekeeper_flash_html('error', 'Unit is required.');
    } elseif ($_Form['reorderlevel'] === '' || !is_numeric($_Form['reorderlevel']) || (float)$_Form['reorderlevel'] < 0) {
        $_Message = storekeeper_flash_html('error', 'Reorder level must be a valid number greater than or equal to zero.');
    } else {
        $_ItemIdEsc = mysqli_real_escape_string($con, $_Form['storeitemid']);
        $_ItemNameEsc = mysqli_real_escape_string($con, $_Form['itemname']);
        $_CategoryEsc = mysqli_real_escape_string($con, $_Form['itemcategory']);
        $_UnitEsc = mysqli_real_escape_string($con, $_Form['unitname']);
        $_ReorderLevel = number_format((float)$_Form['reorderlevel'], 2, '.', '');
        $_DescriptionEsc = mysqli_real_escape_string($con, $_Form['description']);
        $_StatusEsc = mysqli_real_escape_string($con, in_array($_Form['status'], array('active', 'inactive'), true) ? $_Form['status'] : 'active');
        $_RecordedByEsc = mysqli_real_escape_string($con, isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '');

        $_CHK_SQL = "SELECT storeitemid FROM tblstoreitem WHERE itemname='$_ItemNameEsc'";
        if ($_EditingItem) {
            $_CHK_SQL .= " AND storeitemid<>'$_ItemIdEsc'";
        }
        $_CHK_SQL .= " LIMIT 1";
        $_CHK = mysqli_query($con, $_CHK_SQL);

        if ($_CHK && mysqli_num_rows($_CHK) > 0) {
            $_Message = storekeeper_flash_html('error', 'An item with this name already exists.');
        } else {
            if ($_EditingItem) {
                $_SQL = mysqli_query($con, "UPDATE tblstoreitem
                    SET itemname='$_ItemNameEsc',
                        itemcategory='$_CategoryEsc',
                        unitname='$_UnitEsc',
                        reorderlevel='$_ReorderLevel',
                        description='$_DescriptionEsc',
                        status='$_StatusEsc'
                    WHERE storeitemid='$_ItemIdEsc'
                    LIMIT 1");
                if ($_SQL) {
                    $_SESSION['Message'] = storekeeper_flash_html('success', 'Store item updated successfully.');
                    header("location:store-item-entry.php");
                    exit();
                }
                $_Message = storekeeper_flash_html('error', 'Failed to update item: ' . storekeeper_esc(mysqli_error($con)));
            } else {
                include("code.php");
                $_NewItemIdEsc = mysqli_real_escape_string($con, trim((string)$code));
                $_SQL = mysqli_query($con, "INSERT INTO tblstoreitem
                    (storeitemid,itemname,itemcategory,unitname,reorderlevel,description,status,datetimeentry,recordedby)
                    VALUES
                    ('$_NewItemIdEsc','$_ItemNameEsc','$_CategoryEsc','$_UnitEsc','$_ReorderLevel','$_DescriptionEsc','active',NOW(),'$_RecordedByEsc')");
                if ($_SQL) {
                    $_SESSION['Message'] = storekeeper_flash_html('success', 'Store item saved successfully.');
                    header("location:store-item-entry.php");
                    exit();
                }
                $_Message = storekeeper_flash_html('error', 'Failed to save item: ' . storekeeper_esc(mysqli_error($con)));
            }
        }
    }
}

if (isset($_GET['activate_item']) || isset($_GET['deactivate_item'])) {
    $_ItemId = trim((string)(isset($_GET['activate_item']) ? $_GET['activate_item'] : $_GET['deactivate_item']));
    $_NewStatus = isset($_GET['activate_item']) ? 'active' : 'inactive';
    if ($_ItemId !== '') {
        $_ItemIdEsc = mysqli_real_escape_string($con, $_ItemId);
        $_NewStatusEsc = mysqli_real_escape_string($con, $_NewStatus);
        $_SQL = mysqli_query($con, "UPDATE tblstoreitem SET status='$_NewStatusEsc' WHERE storeitemid='$_ItemIdEsc' LIMIT 1");
        if ($_SQL) {
            $_SESSION['Message'] = storekeeper_flash_html('success', 'Item status updated.');
        } else {
            $_SESSION['Message'] = storekeeper_flash_html('error', 'Failed to update item status: ' . storekeeper_esc(mysqli_error($con)));
        }
    }
    header("location:store-item-entry.php");
    exit();
}

if (isset($_GET['delete_item'])) {
    $_ItemId = trim((string)$_GET['delete_item']);
    if ($_ItemId !== '') {
        $_ItemIdEsc = mysqli_real_escape_string($con, $_ItemId);
        $_ReceiptChk = mysqli_query($con, "SELECT receiptid FROM tblstorereceipt WHERE storeitemid='$_ItemIdEsc' LIMIT 1");
        $_IssueChk = mysqli_query($con, "SELECT issueid FROM tblstoreissue WHERE storeitemid='$_ItemIdEsc' LIMIT 1");
        if (($_ReceiptChk && mysqli_num_rows($_ReceiptChk) > 0) || ($_IssueChk && mysqli_num_rows($_IssueChk) > 0)) {
            $_SESSION['Message'] = storekeeper_flash_html('error', 'This item already has stock history, so it cannot be deleted.');
        } else {
            $_SQL = mysqli_query($con, "DELETE FROM tblstoreitem WHERE storeitemid='$_ItemIdEsc' LIMIT 1");
            if ($_SQL) {
                $_SESSION['Message'] = storekeeper_flash_html('warning', 'Store item deleted.');
            } else {
                $_SESSION['Message'] = storekeeper_flash_html('error', 'Failed to delete item: ' . storekeeper_esc(mysqli_error($con)));
            }
        }
    }
    header("location:store-item-entry.php");
    exit();
}

$_BalanceRows = storekeeper_fetch_balance_rows($con);
$_Summary = storekeeper_dashboard_summary($con);
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
                <span class="sk-kicker"><i class="fa fa-tags"></i> Item Master</span>
                <h1>Register and maintain store items.</h1>
                <p>Use this page to build the school store register for food items, kitchen supplies, and domestic consumables before recording receipts and issues.</p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="storekeeper-dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                    <a class="sk-link-chip" href="store-stock-receipt.php"><i class="fa fa-download"></i> Stock Receipt</a>
                    <a class="sk-link-chip" href="store-stock-issue.php"><i class="fa fa-upload"></i> Stock Issue</a>
                    <a class="sk-link-chip" href="store-balance-report.php"><i class="fa fa-line-chart"></i> Balance Report</a>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Total Items</span>
                    <strong><?php echo number_format((int)$_Summary['total_items']); ?></strong>
                    <small>Every registered line in the store item master.</small>
                </article>
                <article class="sk-stat">
                    <span>Active Items</span>
                    <strong><?php echo number_format((int)$_Summary['active_items']); ?></strong>
                    <small>Items available for fresh stock transactions.</small>
                </article>
                <article class="sk-stat">
                    <span>Low Stock</span>
                    <strong><?php echo number_format((int)$_Summary['low_stock_items']); ?></strong>
                    <small>Items already at or below their reorder levels.</small>
                </article>
                <article class="sk-stat">
                    <span>Inactive Items</span>
                    <strong><?php echo number_format((int)$_Summary['inactive_items']); ?></strong>
                    <small>Items kept in history but not open for new entries.</small>
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
                        <h2><?php echo $_EditingItem ? 'Update Item' : 'Add New Item'; ?></h2>
                        <p>Keep names clear and use the unit you normally count in store.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <form method="post" class="sk-form" action="store-item-entry.php">
                        <input type="hidden" name="storeitemid" value="<?php echo storekeeper_esc($_Form['storeitemid']); ?>">
                        <div class="sk-form-grid">
                            <div class="sk-field sk-field--full">
                                <label for="itemname">Item Name</label>
                                <input type="text" id="itemname" name="itemname" value="<?php echo storekeeper_esc($_Form['itemname']); ?>" placeholder="e.g. Rice, Cooking Oil, Sugar" required>
                            </div>
                            <div class="sk-field">
                                <label for="itemcategory">Category</label>
                                <input type="text" id="itemcategory" name="itemcategory" value="<?php echo storekeeper_esc($_Form['itemcategory']); ?>" list="store-category-list" placeholder="Select or type category" autocomplete="off">
                                <datalist id="store-category-list">
                                    <?php foreach (storekeeper_distinct_categories($con) as $_CategoryOption) { ?>
                                    <option value="<?php echo storekeeper_esc($_CategoryOption); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field">
                                <label for="unitname">Unit</label>
                                <input type="text" id="unitname" name="unitname" value="<?php echo storekeeper_esc($_Form['unitname']); ?>" list="store-unit-list" placeholder="Select or type unit" autocomplete="off" required>
                                <datalist id="store-unit-list">
                                    <?php foreach (storekeeper_units() as $_UnitOption) { ?>
                                    <option value="<?php echo storekeeper_esc($_UnitOption); ?>">
                                    <?php } ?>
                                </datalist>
                            </div>
                            <div class="sk-field">
                                <label for="reorderlevel">Reorder Level</label>
                                <input type="number" id="reorderlevel" name="reorderlevel" value="<?php echo storekeeper_esc($_Form['reorderlevel']); ?>" min="0" step="0.01">
                            </div>
                            <?php if ($_EditingItem) { ?>
                            <div class="sk-field">
                                <label for="status">Status</label>
                                <input type="text" id="status" name="status" value="<?php echo storekeeper_esc($_Form['status']); ?>" list="store-status-list" placeholder="Select status" autocomplete="off">
                                <datalist id="store-status-list">
                                    <option value="active">
                                    <option value="inactive">
                                </datalist>
                            </div>
                            <?php } ?>
                            <div class="sk-field sk-field--full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" placeholder="Optional notes about pack size, brand, or handling details."><?php echo storekeeper_esc($_Form['description']); ?></textarea>
                            </div>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" name="save_item" class="sk-button"><i class="fa fa-save"></i> <?php echo $_EditingItem ? 'Update Item' : 'Save Item'; ?></button>
                            <?php if ($_EditingItem) { ?>
                            <a href="store-item-entry.php" class="sk-button--ghost"><i class="fa fa-times-circle"></i> Cancel Edit</a>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </section>

            <section class="sk-panel">
                <div class="sk-panel__header">
                    <div>
                        <h2>Registered Items</h2>
                        <p>Current balance is calculated from posted receipts minus posted issues.</p>
                    </div>
                </div>
                <div class="sk-panel__body">
                    <?php if (empty($_BalanceRows)) { ?>
                    <div class="sk-empty">No store items have been saved yet.</div>
                    <?php } else { ?>
                    <div class="sk-table-wrap">
                        <table class="sk-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Unit</th>
                                    <th>Reorder</th>
                                    <th>Balance</th>
                                    <th>Stock Level</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_BalanceRows as $_Row) { ?>
                                <tr>
                                    <td>
                                        <div class="sk-actions">
                                            <a class="sk-inline-action" href="store-item-entry.php?edit_item=<?php echo urlencode($_Row['storeitemid']); ?>"><i class="fa fa-edit"></i> Edit</a>
                                            <?php if ((string)$_Row['status'] === 'active') { ?>
                                            <a class="sk-inline-action" onclick="return confirm('Deactivate this item?');" href="store-item-entry.php?deactivate_item=<?php echo urlencode($_Row['storeitemid']); ?>"><i class="fa fa-pause-circle"></i> Deactivate</a>
                                            <?php } else { ?>
                                            <a class="sk-inline-action" onclick="return confirm('Activate this item?');" href="store-item-entry.php?activate_item=<?php echo urlencode($_Row['storeitemid']); ?>"><i class="fa fa-play-circle"></i> Activate</a>
                                            <?php } ?>
                                            <a class="sk-inline-action" onclick="return confirm('Delete this item? This only works if there is no stock history.');" href="store-item-entry.php?delete_item=<?php echo urlencode($_Row['storeitemid']); ?>"><i class="fa fa-trash"></i> Delete</a>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo storekeeper_esc($_Row['itemname']); ?>
                                        <?php if (trim((string)$_Row['description']) !== '') { ?>
                                        <small><?php echo storekeeper_esc($_Row['description']); ?></small>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo storekeeper_esc($_Row['itemcategory']); ?></td>
                                    <td><?php echo storekeeper_esc($_Row['unitname']); ?></td>
                                    <td><?php echo storekeeper_format_quantity($_Row['reorderlevel']); ?></td>
                                    <td><?php echo storekeeper_format_quantity($_Row['current_balance']); ?></td>
                                    <td><?php echo storekeeper_stock_badge_html($_Row['current_balance'], $_Row['reorderlevel']); ?></td>
                                    <td><?php echo storekeeper_status_badge_html($_Row['status']); ?></td>
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
</body>
</html>

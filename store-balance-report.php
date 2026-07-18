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

$_Search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$_Categories = storekeeper_distinct_categories($con);
$_CategoryInput = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$_Category = $_CategoryInput;
if ($_CategoryInput !== '') {
    foreach ($_Categories as $_CategoryOption) {
        if (strcasecmp(trim((string)$_CategoryOption), $_CategoryInput) === 0) {
            $_Category = (string)$_CategoryOption;
            break;
        }
    }
}
$_Rows = storekeeper_fetch_balance_rows($con, $_Search, $_Category);

$_VisibleItems = count($_Rows);
$_LowStock = 0;
$_OutOfStock = 0;
foreach ($_Rows as $_Row) {
    if ((float)$_Row['current_balance'] <= 0) {
        $_OutOfStock++;
    } elseif ((float)$_Row['reorderlevel'] > 0 && (float)$_Row['current_balance'] <= (float)$_Row['reorderlevel']) {
        $_LowStock++;
    }
}
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
                <span class="sk-kicker"><i class="fa fa-line-chart"></i> Balance Report</span>
                <h1>Review live stock balances across all store items.</h1>
                <p>This report combines posted receipts, internal store issues, and student-issued items so the storekeeper can see what is left, what is low, and what is currently out with students.</p>
                <div class="sk-link-grid">
                    <a class="sk-link-chip" href="storekeeper-dashboard.php"><i class="fa fa-dashboard"></i> Dashboard</a>
                    <a class="sk-link-chip" href="store-item-entry.php"><i class="fa fa-tags"></i> Item Master</a>
                    <a class="sk-link-chip" href="store-stock-receipt.php"><i class="fa fa-download"></i> Stock Receipt</a>
                    <a class="sk-link-chip" href="store-stock-issue.php"><i class="fa fa-upload"></i> Stock Issue</a>
                    <a class="sk-link-chip" href="store-student-issue.php"><i class="fa fa-book"></i> Student Items</a>
                </div>
            </div>
            <div class="sk-stats">
                <article class="sk-stat">
                    <span>Visible Items</span>
                    <strong><?php echo number_format((int)$_VisibleItems); ?></strong>
                    <small>Items returned by the current search and category filter.</small>
                </article>
                <article class="sk-stat">
                    <span>Low Stock</span>
                    <strong><?php echo number_format((int)$_LowStock); ?></strong>
                    <small>Items still available but already at reorder level.</small>
                </article>
                <article class="sk-stat">
                    <span>Out Of Stock</span>
                    <strong><?php echo number_format((int)$_OutOfStock); ?></strong>
                    <small>Items whose running balance is fully exhausted.</small>
                </article>
                <article class="sk-stat">
                    <span>Category Filter</span>
                    <strong><?php echo $_Category !== '' ? storekeeper_esc($_Category) : 'All'; ?></strong>
                    <small>Current category scope for this report view.</small>
                </article>
            </div>
        </section>

        <section class="sk-panel">
            <div class="sk-panel__header">
                <div>
                    <h2>Filter Report</h2>
                    <p>Search by item name, id, category, or unit and optionally narrow the view by category.</p>
                </div>
            </div>
            <div class="sk-panel__body">
                <form method="get" class="sk-form" action="store-balance-report.php">
                    <div class="sk-filter-bar">
                        <div class="sk-field">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo storekeeper_esc($_Search); ?>" placeholder="Item name, category, unit, or id">
                        </div>
                        <div class="sk-field">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" value="<?php echo storekeeper_esc($_Category); ?>" list="report-category-list" placeholder="All categories" autocomplete="off">
                            <datalist id="report-category-list">
                                <?php foreach ($_Categories as $_CategoryOption) { ?>
                                <option value="<?php echo storekeeper_esc($_CategoryOption); ?>">
                                <?php } ?>
                            </datalist>
                        </div>
                        <div class="sk-actions">
                            <button type="submit" class="sk-button"><i class="fa fa-filter"></i> Apply Filter</button>
                            <a href="store-balance-report.php" class="sk-button--ghost"><i class="fa fa-refresh"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="sk-panel">
            <div class="sk-panel__header">
                <div>
                    <h2>Stock Balance Table</h2>
                    <p>Balance = total posted receipts minus internal issues minus student items still out.</p>
                </div>
            </div>
            <div class="sk-panel__body">
                <?php if (empty($_Rows)) { ?>
                <div class="sk-empty">No items matched the current report filters.</div>
                <?php } else { ?>
                <div class="sk-table-wrap">
                    <table class="sk-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Total Received</th>
                                <th>Internal Issues</th>
                                <th>Student Items Out</th>
                                <th>Balance</th>
                                <th>Reorder Level</th>
                                <th>Stock Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_Rows as $_Row) { ?>
                            <tr>
                                <td>
                                    <?php echo storekeeper_esc($_Row['itemname']); ?>
                                    <small><?php echo storekeeper_esc($_Row['storeitemid']); ?></small>
                                </td>
                                <td><?php echo storekeeper_esc($_Row['itemcategory']); ?></td>
                                <td><?php echo storekeeper_esc($_Row['unitname']); ?></td>
                                <td><?php echo storekeeper_format_quantity($_Row['total_received']); ?></td>
                                <td><?php echo storekeeper_format_quantity($_Row['total_issued']); ?></td>
                                <td><?php echo storekeeper_format_quantity($_Row['total_student_issued']); ?></td>
                                <td><?php echo storekeeper_format_quantity($_Row['current_balance']); ?></td>
                                <td><?php echo storekeeper_format_quantity($_Row['reorderlevel']); ?></td>
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
</main>
</body>
</html>

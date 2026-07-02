<?php
session_start();
include("check-login.php");
include("dbstring.php");

$_SESSION['Message'] = $_SESSION['Message'] ?? "";

function gd_is_allowed_admin() {
    $access = strtolower(trim((string)($_SESSION['ACCESSLEVEL'] ?? '')));
    $type = strtolower(trim((string)($_SESSION['SYSTEMTYPE'] ?? '')));
    return $access === 'administrator' && in_array($type, array('normal_user', 'super_user', 'user'), true);
}

function gd_table_exists($con, $table) {
    $tableEsc = mysqli_real_escape_string($con, $table);
    $res = mysqli_query($con, "SHOW TABLES LIKE '$tableEsc'");
    return $res && mysqli_num_rows($res) > 0;
}

function gd_count_table($con, $table) {
    $res = @mysqli_query($con, "SELECT COUNT(*) AS total FROM `$table`");
    if ($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
        return (int)$row['total'];
    }
    return 0;
}

function gd_get_base_tables($con) {
    $tables = array();
    $res = mysqli_query($con, "SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_NUM)) {
            if (!empty($row[0])) {
                $tables[] = $row[0];
            }
        }
    }
    sort($tables);
    return $tables;
}

function gd_reset_operational_data($con) {
    $currentUser = mysqli_real_escape_string($con, (string)($_SESSION['USERID'] ?? ''));
    $tables = gd_get_base_tables($con);

    // These tables keep the system usable after reset. Student/teacher users are deleted separately.
    $protectedTables = array(
        'tblcompany',
        'tblbranch',
        'tblcurrency',
        'tblmodule',
        'tblaccounttype',
        'tblapi',
        'tblsystemuser'
    );

    $deleted = array();
    $failed = array();

    @mysqli_query($con, "SET FOREIGN_KEY_CHECKS=0");

    foreach ($tables as $table) {
        if (in_array($table, $protectedTables, true)) {
            continue;
        }

        $before = gd_count_table($con, $table);
        $ok = @mysqli_query($con, "DELETE FROM `$table`");
        if ($ok) {
            @mysqli_query($con, "ALTER TABLE `$table` AUTO_INCREMENT=1");
            $deleted[] = array('table' => $table, 'rows' => $before);
        } else {
            $failed[] = array('table' => $table, 'error' => mysqli_error($con));
        }
    }

    if (gd_table_exists($con, 'tblsystemuser')) {
        $beforeUsers = gd_count_table($con, 'tblsystemuser');
        $where = "WHERE NOT (LOWER(accesslevel)='administrator' OR userid='$currentUser')";
        $okUsers = @mysqli_query($con, "DELETE FROM tblsystemuser $where");
        if ($okUsers) {
            $afterUsers = gd_count_table($con, 'tblsystemuser');
            $deleted[] = array('table' => 'tblsystemuser (students/teachers only)', 'rows' => max(0, $beforeUsers - $afterUsers));
        } else {
            $failed[] = array('table' => 'tblsystemuser', 'error' => mysqli_error($con));
        }
    }

    @mysqli_query($con, "SET FOREIGN_KEY_CHECKS=1");

    return array('deleted' => $deleted, 'failed' => $failed, 'protected' => $protectedTables);
}

$isAllowed = gd_is_allowed_admin();
$resetResult = null;

if (!$isAllowed) {
    http_response_code(403);
}

if ($isAllowed && isset($_POST['confirm_reset'])) {
    $phrase = trim((string)($_POST['confirmation_phrase'] ?? ''));
    if ($phrase !== 'RESET SCHOOL DATA') {
        $_SESSION['Message'] = "<div class='gd-alert gd-alert-danger'>Reset cancelled. You must type RESET SCHOOL DATA exactly.</div>";
    } else {
        $resetResult = gd_reset_operational_data($con);
        $deletedCount = count($resetResult['deleted']);
        $failedCount = count($resetResult['failed']);
        $_SESSION['Message'] = "<div class='gd-alert gd-alert-success'>Database reset completed. Tables processed: $deletedCount. Failed: $failedCount.</div>";
    }
}

$previewTables = $isAllowed ? gd_get_base_tables($con) : array();
$protectedPreview = array('tblcompany', 'tblbranch', 'tblcurrency', 'tblmodule', 'tblaccounttype', 'tblapi', 'tblsystemuser');
?>
<html>
<head>
<?php include("links.php"); ?>
<style>
.gd-page {
    min-height: 100vh;
    padding: 24px;
    background:
        radial-gradient(circle at top left, rgba(220, 38, 38, 0.1), transparent 28%),
        linear-gradient(135deg, #fff7ed 0%, #eef2f7 100%);
    color: #1f2937;
}
.gd-shell {
    max-width: 1050px;
    margin: 0 auto;
}
.gd-hero,
.gd-panel {
    border: 1px solid #fecaca;
    border-radius: 22px;
    background: rgba(255,255,255,0.96);
    box-shadow: 0 18px 42px rgba(127, 29, 29, 0.12);
}
.gd-hero {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    padding: 24px;
    margin-bottom: 18px;
}
.gd-kicker {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 999px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.gd-hero h1 {
    margin: 10px 0 8px;
    color: #7f1d1d;
    font-size: clamp(28px, 4vw, 42px);
}
.gd-hero p,
.gd-panel p,
.gd-panel li {
    color: #64748b;
}
.gd-hero-card {
    min-width: 230px;
    padding: 18px;
    border-radius: 18px;
    background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 100%);
    color: #fff;
}
.gd-hero-card i {
    font-size: 32px;
    color: #fecaca;
}
.gd-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(300px, 0.95fr);
    gap: 18px;
}
.gd-panel {
    padding: 20px;
}
.gd-panel h2 {
    margin: 0 0 10px;
    color: #7f1d1d;
}
.gd-alert {
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 14px;
    font-weight: 800;
}
.gd-alert-danger {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #991b1b;
}
.gd-alert-success {
    border: 1px solid #bbf7d0;
    background: #ecfdf5;
    color: #166534;
}
.gd-form {
    display: grid;
    gap: 12px;
}
.gd-form label {
    color: #7f1d1d;
    font-weight: 900;
}
.gd-form input {
    min-height: 46px;
    padding: 10px 12px;
    border: 1px solid #fecaca;
    border-radius: 13px;
    font-size: 15px;
}
.gd-button {
    min-height: 46px;
    border: 0;
    border-radius: 999px;
    background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%);
    color: #fff;
    font-weight: 900;
    cursor: pointer;
}
.gd-table-list {
    max-height: 330px;
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
}
.gd-table-list table {
    width: 100%;
    border-collapse: collapse;
}
.gd-table-list th,
.gd-table-list td {
    padding: 10px;
    border-bottom: 1px solid #f1f5f9;
    text-align: left;
    font-size: 13px;
}
.gd-table-list th {
    position: sticky;
    top: 0;
    background: #7f1d1d;
    color: #fff;
}
.gd-pill {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
}
.gd-pill-delete {
    background: #fee2e2;
    color: #991b1b;
}
.gd-pill-protect {
    background: #ecfdf5;
    color: #166534;
}
@media (max-width: 820px) {
    .gd-page {
        padding: 12px;
    }
    .gd-hero,
    .gd-grid {
        grid-template-columns: 1fr;
    }
    .gd-hero {
        flex-direction: column;
    }
    .gd-hero-card {
        min-width: 0;
    }
}
</style>
</head>
<body class="body-style">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform gd-page">
<div class="gd-shell">
    <section class="gd-hero">
        <div>
            <span class="gd-kicker">Hidden danger zone</span>
            <h1>Database Reset</h1>
            <p>This page is intentionally removed from the main menu. Use it only after taking a backup and only when you want to clear school operational data.</p>
        </div>
        <div class="gd-hero-card">
            <i class="fa fa-warning"></i>
            <h3>High Risk Action</h3>
            <p style="color:#fee2e2;">This cannot be undone from inside the system.</p>
        </div>
    </section>

    <?php echo $_SESSION['Message']; ?>

    <?php if (!$isAllowed) { ?>
        <div class="gd-panel">
            <h2>Access Denied</h2>
            <p>Only administrator accounts can access this reset area.</p>
        </div>
    <?php } else { ?>
    <div class="gd-grid">
        <section class="gd-panel">
            <h2>What This Will Do</h2>
            <p>The old button did not clear everything. It only deleted a fixed list of older tables. This safer version scans the database tables dynamically.</p>
            <ul>
                <li>Deletes operational records from all database tables it can safely process.</li>
                <li>Deletes student and teacher accounts from `tblsystemuser`.</li>
                <li>Preserves administrator accounts so you do not lock yourself out.</li>
                <li>Preserves basic setup tables like company, branch, currency, modules, account types and API authentication.</li>
            </ul>
            <form method="post" action="global_deletes.php" class="gd-form" onsubmit="return confirm('This will clear school operational data. Have you taken a database backup?');">
                <label for="confirmation_phrase">Type RESET SCHOOL DATA to continue</label>
                <input type="text" id="confirmation_phrase" name="confirmation_phrase" autocomplete="off" required>
                <button type="submit" name="confirm_reset" class="gd-button"><i class="fa fa-trash"></i> Reset School Data</button>
            </form>
        </section>

        <section class="gd-panel">
            <h2>Table Preview</h2>
            <p>Protected tables stay usable. All other listed tables are cleared when you confirm.</p>
            <div class="gd-table-list">
                <table>
                    <thead><tr><th>Table</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php
                    foreach ($previewTables as $table) {
                        $protected = in_array($table, $protectedPreview, true);
                        echo "<tr>";
                        echo "<td>".htmlspecialchars($table)."</td>";
                        echo "<td><span class='gd-pill ".($protected ? 'gd-pill-protect' : 'gd-pill-delete')."'>".($protected ? 'Protected' : 'Clear')."</span></td>";
                        echo "</tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <?php if (is_array($resetResult)) { ?>
        <section class="gd-panel" style="margin-top:18px;">
            <h2>Reset Summary</h2>
            <div class="gd-table-list">
                <table>
                    <thead><tr><th>Table</th><th>Rows Deleted</th></tr></thead>
                    <tbody>
                    <?php
                    foreach ($resetResult['deleted'] as $item) {
                        echo "<tr><td>".htmlspecialchars($item['table'])."</td><td>".(int)$item['rows']."</td></tr>";
                    }
                    if (count($resetResult['failed']) > 0) {
                        foreach ($resetResult['failed'] as $item) {
                            echo "<tr><td>".htmlspecialchars($item['table'])."</td><td>Failed: ".htmlspecialchars($item['error'])."</td></tr>";
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php } ?>
    <?php } ?>
</div>
</div>
</body>
</html>

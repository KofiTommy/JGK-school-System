<?php
session_start();
include("dbstring.php");
include_once("student-index-utils.php");
$schoolIndexReady = student_index_ensure_schema($con);

if (!function_exists("view_class_registry_esc")) {
function view_class_registry_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if (!function_exists("view_class_registry_lower")) {
function view_class_registry_lower($value)
{
    $value = (string)$value;
    return function_exists("mb_strtolower") ? mb_strtolower($value, "UTF-8") : strtolower($value);
}
}

if (!function_exists("view_class_registry_name")) {
function view_class_registry_name($row)
{
    $parts = array(
        isset($row["firstname"]) ? trim((string)$row["firstname"]) : "",
        isset($row["othernames"]) ? trim((string)$row["othernames"]) : "",
        isset($row["surname"]) ? trim((string)$row["surname"]) : "",
    );
    $name = trim(implode(" ", array_filter($parts, "strlen")));
    if ($name !== "") {
        return $name;
    }
    return isset($row["userid"]) ? trim((string)$row["userid"]) : "Student";
}
}

if (!function_exists("view_class_registry_format_datetime")) {
function view_class_registry_format_datetime($value)
{
    $value = trim((string)$value);
    if ($value === "") {
        return "Not recorded";
    }
    $time = strtotime($value);
    return $time ? date("d M Y, H:i", $time) : $value;
}
}

$flashHtml = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

$selectedBatchId = isset($_POST["batchid"]) ? trim((string)$_POST["batchid"]) : "";
$shouldLoadRecords = isset($_POST["show_class"]) && $selectedBatchId !== "";

$batchOptions = array();
$batchQuery = mysqli_query($con, "SELECT batchid, batch, status FROM tblbatch ORDER BY batch DESC");
if ($batchQuery) {
    while ($row = mysqli_fetch_array($batchQuery, MYSQLI_ASSOC)) {
        $batchOptions[] = $row;
    }
}

$selectedBatchLabel = "Batch not selected";
foreach ($batchOptions as $batchOption) {
    if ($selectedBatchId === trim((string)$batchOption["batchid"])) {
        $selectedBatchLabel = trim((string)$batchOption["batch"]);
        break;
    }
}

$registryRows = array();
$studentMap = array();
$classMap = array();
$latestRecordedAt = "";

if ($shouldLoadRecords) {
    $selectedBatchIdEsc = mysqli_real_escape_string($con, $selectedBatchId);
    $schoolIndexSelect = $schoolIndexReady ? "su.schoolindexnumber" : "'' AS schoolindexnumber";
    $sql = "SELECT
                cl.classid,
                cl.userid,
                cl.datetimeentry,
                su.firstname,
                su.surname,
                su.othernames,
                $schoolIndexSelect,
                ce.class_name,
                bh.batch,
                bh.batchid
            FROM tblclass cl
            INNER JOIN tblsystemuser su ON su.userid=cl.userid
            LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
            LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
            WHERE cl.batchid='$selectedBatchIdEsc'
              AND cl.status='active'
              AND su.systemtype='Student'
            ORDER BY ce.class_name ASC, su.firstname ASC, su.othernames ASC, su.surname ASC, cl.datetimeentry DESC";
    $registryQuery = mysqli_query($con, $sql);
    if ($registryQuery) {
        while ($row = mysqli_fetch_array($registryQuery, MYSQLI_ASSOC)) {
            $registryRows[] = $row;
            $studentId = trim((string)(isset($row["userid"]) ? $row["userid"] : ""));
            $className = trim((string)(isset($row["class_name"]) ? $row["class_name"] : ""));
            if ($studentId !== "") {
                $studentMap[$studentId] = true;
            }
            if ($className !== "") {
                $classMap[$className] = true;
            }
            $recordedAt = trim((string)(isset($row["datetimeentry"]) ? $row["datetimeentry"] : ""));
            if ($recordedAt !== "" && ($latestRecordedAt === "" || strtotime($recordedAt) > strtotime($latestRecordedAt))) {
                $latestRecordedAt = $recordedAt;
            }
        }
    }
}

$totalRecords = count($registryRows);
$totalStudents = count($studentMap);
$classCount = count($classMap);
$latestRecordedLabel = $latestRecordedAt !== "" ? view_class_registry_format_datetime($latestRecordedAt) : "No records yet";
$resultSummaryText = !$shouldLoadRecords
    ? "Choose a batch to review active student class placements."
    : ($totalRecords > 0
        ? "Showing active class registrations for the selected batch."
        : "No active class registrations were found for the selected batch.");
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/view-class-registry.css">
<script type="text/javascript" src="scripts/view-class-registry.js" defer></script>
</head>

<body class="body-style view-class-registry-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="main-platform registry-view-shell">
    <section class="registry-view-hero">
        <div class="registry-view-hero__copy">
            <span class="registry-view-kicker"><i class="fa fa-folder-open-o"></i> Student Records</span>
            <h1>View Class Registry</h1>
            <p>Review active class registration records by batch, search through the student list quickly, and open or remove entries from one place.</p>
            <div class="registry-view-hero__chips">
                <span class="registry-view-chip"><i class="fa fa-calendar"></i> <?php echo view_class_registry_esc($selectedBatchLabel); ?></span>
                <span class="registry-view-chip"><i class="fa fa-check-circle"></i> Active records only</span>
            </div>
        </div>

        <div class="registry-view-hero__actions">
            <a href="class-registry.php" class="registry-view-btn registry-view-btn--ghost"><i class="fa fa-users"></i> Class Registry</a>
            <a href="upload-class-registry.php" class="registry-view-btn registry-view-btn--light"><i class="fa fa-arrow-circle-up"></i> Upload Registry</a>
            <a href="view-class-registry.php" class="registry-view-btn registry-view-btn--light"><i class="fa fa-refresh"></i> Reset View</a>
        </div>

        <div class="registry-view-stats">
            <article class="registry-view-stat">
                <span>Students</span>
                <strong><?php echo (int)$totalStudents; ?></strong>
                <small>Unique students in the current result set.</small>
            </article>
            <article class="registry-view-stat">
                <span>Class Records</span>
                <strong><?php echo (int)$totalRecords; ?></strong>
                <small>Active registry entries returned for the selected batch.</small>
            </article>
            <article class="registry-view-stat">
                <span>Classes Covered</span>
                <strong><?php echo (int)$classCount; ?></strong>
                <small>Distinct classes represented in the active list.</small>
            </article>
            <article class="registry-view-stat">
                <span>Latest Recorded</span>
                <strong><?php echo view_class_registry_esc($shouldLoadRecords ? $latestRecordedLabel : "Pending"); ?></strong>
                <small><?php echo $shouldLoadRecords ? "Most recent active registry timestamp." : "Select a batch to load records."; ?></small>
            </article>
        </div>
    </section>

    <?php if ($flashHtml !== "") { ?>
    <div class="registry-view-message-stack"><?php echo $flashHtml; ?></div>
    <?php } ?>

    <section class="registry-view-panel registry-view-filter-panel">
        <div class="registry-view-panel__head">
            <div>
                <h2>Filter Registry</h2>
                <p>Select the batch you want to inspect, then load the active class records for that period.</p>
            </div>
            <div class="registry-view-panel__tag">
                <i class="fa fa-filter"></i> Batch Filter
            </div>
        </div>

        <form method="post" action="view-class-registry.php" class="registry-view-filter-grid">
            <label for="batchid">
                <span>Batch</span>
                <select id="batchid" name="batchid" required>
                    <option value="">Select Batch</option>
                    <?php foreach ($batchOptions as $batchOption) { ?>
                    <option value="<?php echo view_class_registry_esc($batchOption["batchid"]); ?>"<?php echo $selectedBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                        <?php echo view_class_registry_esc($batchOption["batch"]); ?><?php echo trim((string)$batchOption["status"]) !== "" ? " (".view_class_registry_esc($batchOption["status"]).")" : ""; ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <div class="registry-view-filter-actions">
                <button type="submit" class="registry-view-btn registry-view-btn--primary" name="show_class"><i class="fa fa-search"></i> Load Records</button>
                <a href="view-class-registry.php" class="registry-view-btn registry-view-btn--secondary"><i class="fa fa-times"></i> Clear</a>
            </div>
        </form>
    </section>

    <section class="registry-view-panel registry-view-results-panel">
        <div class="registry-view-panel__head registry-view-panel__head--results">
            <div>
                <h2>Class Registry Records</h2>
                <p><?php echo view_class_registry_esc($resultSummaryText); ?></p>
            </div>

            <div class="registry-view-search-block">
                <label for="registry-view-search">Quick Search</label>
                <div class="registry-view-search-field">
                    <i class="fa fa-search"></i>
                    <input
                        type="search"
                        id="registry-view-search"
                        placeholder="Search by student name, school index, ID, class, batch or date"
                        <?php echo $totalRecords === 0 ? "disabled" : ""; ?>
                    >
                </div>
            </div>
        </div>

        <?php if ($shouldLoadRecords) { ?>
        <div class="registry-view-active-filters">
            <span class="registry-view-filter-pill"><i class="fa fa-calendar"></i> <?php echo view_class_registry_esc($selectedBatchLabel); ?></span>
            <span class="registry-view-filter-pill"><i class="fa fa-clock-o"></i> Latest record: <?php echo view_class_registry_esc($latestRecordedLabel); ?></span>
            <span class="registry-view-filter-pill"><i class="fa fa-folder-open-o"></i> Active registry only</span>
        </div>
        <?php } ?>

        <?php if (!$shouldLoadRecords) { ?>
        <div class="registry-view-empty-state">
            <i class="fa fa-filter"></i>
            <h3>Choose a batch to view class registry records.</h3>
            <p>Once you select a batch and load it, the active student class records will appear here with quick search and action buttons.</p>
        </div>
        <?php } elseif ($totalRecords > 0) { ?>
        <div class="registry-view-results-meta">
            <p><strong id="registry-view-visible-count"><?php echo (int)$totalRecords; ?></strong> of <?php echo (int)$totalRecords; ?> records visible</p>
            <p>Open a student record or remove a class entry from the active registry when needed.</p>
        </div>

        <div class="registry-view-table-wrap">
            <table class="registry-view-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Recorded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="registry-view-body">
                    <?php foreach ($registryRows as $index => $row) {
                        $studentId = trim((string)(isset($row["userid"]) ? $row["userid"] : ""));
                        $schoolIndex = trim((string)(isset($row["schoolindexnumber"]) ? $row["schoolindexnumber"] : ""));
                        $studentName = view_class_registry_name($row);
                        $className = trim((string)(isset($row["class_name"]) ? $row["class_name"] : ""));
                        $batchName = trim((string)(isset($row["batch"]) ? $row["batch"] : ""));
                        $recordedAt = view_class_registry_format_datetime(isset($row["datetimeentry"]) ? $row["datetimeentry"] : "");
                        $searchIndex = view_class_registry_lower($studentName." ".$schoolIndex." ".$studentId." ".$className." ".$batchName." ".$recordedAt);
                    ?>
                    <tr class="registry-view-row" data-search="<?php echo view_class_registry_esc($searchIndex); ?>">
                        <td data-label="#"><?php echo (int)($index + 1); ?></td>
                        <td data-label="Student">
                            <div class="registry-view-student-cell">
                                <strong><?php echo view_class_registry_esc($studentName); ?></strong>
                                <small><?php echo $schoolIndex !== "" ? view_class_registry_esc($schoolIndex) : ($studentId !== "" ? view_class_registry_esc($studentId) : "No ID"); ?></small>
                                <?php if ($schoolIndex !== "" && $studentId !== "" && $schoolIndex !== $studentId) { ?>
                                <small>Login ID: <?php echo view_class_registry_esc($studentId); ?></small>
                                <?php } ?>
                            </div>
                        </td>
                        <td data-label="Class">
                            <span class="registry-view-table-pill"><?php echo $className !== "" ? view_class_registry_esc($className) : "N/A"; ?></span>
                        </td>
                        <td data-label="Batch"><?php echo $batchName !== "" ? view_class_registry_esc($batchName) : "N/A"; ?></td>
                        <td data-label="Recorded"><?php echo view_class_registry_esc($recordedAt); ?></td>
                        <td data-label="Actions">
                            <div class="registry-view-actions">
                                <?php if ($studentId !== "") { ?>
                                <a href="class-registry.php?view_user=<?php echo urlencode($studentId); ?>" class="registry-view-link-btn" title="Open student registry">
                                    <i class="fa fa-folder-open-o"></i>
                                    <span>Open Student</span>
                                </a>
                                <?php } ?>
                                <?php if (trim((string)(isset($row["classid"]) ? $row["classid"] : "")) !== "") { ?>
                                <a
                                    href="upload-class-registry.php?delete_class=<?php echo urlencode((string)$row["classid"]); ?>"
                                    class="registry-view-link-btn registry-view-link-btn--danger"
                                    title="Remove class record"
                                    onclick="return confirm('Do you want to remove this class record?');"
                                >
                                    <i class="fa fa-trash-o"></i>
                                    <span>Remove</span>
                                </a>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div id="registry-view-search-empty" class="registry-view-empty-state registry-view-empty-state--search" hidden>
            <i class="fa fa-search"></i>
            <h3>No registry records match this search.</h3>
            <p>Try a broader student name, class, ID, batch, or recorded date and the list will update immediately.</p>
        </div>
        <?php } else { ?>
        <div class="registry-view-empty-state">
            <i class="fa fa-folder-open-o"></i>
            <h3>No active class registry records were found.</h3>
            <p>Check the selected batch again, or confirm that students have been registered into active classes for that batch.</p>
        </div>
        <?php } ?>
    </section>
</main>
</body>
</html>

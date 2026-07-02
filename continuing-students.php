<?php
session_start();
include("dbstring.php");
include("check-login.php");

if (!isset($_SESSION['ACCESSLEVEL']) || ($_SESSION['ACCESSLEVEL'] != "administrator" && !(function_exists('um_is_academic_lead_user') && um_is_academic_lead_user()))) {
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}

function continuing_students_esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function continuing_students_lower($value) {
    $value = (string)$value;
    if (function_exists("mb_strtolower")) {
        return mb_strtolower($value, "UTF-8");
    }
    return strtolower($value);
}

function continuing_students_name($row) {
    $parts = array();
    $fields = array("firstname", "othernames", "surname");
    foreach ($fields as $field) {
        $part = trim((string)(isset($row[$field]) ? $row[$field] : ""));
        if ($part !== "") {
            $parts[] = $part;
        }
    }
    $name = trim(implode(" ", $parts));
    if ($name !== "") {
        return $name;
    }
    return trim((string)(isset($row["userid"]) ? $row["userid"] : "Unknown Student"));
}

function continuing_students_format_datetime($value) {
    $value = trim((string)$value);
    if ($value === "") {
        return "N/A";
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date("d M Y, g:i a", $timestamp);
}

$selectedBatchId = isset($_GET["batchid"]) ? trim((string)$_GET["batchid"]) : "";
$selectedClassId = isset($_GET["classid"]) ? trim((string)$_GET["classid"]) : "";
$selectedBatchIdSafe = mysqli_real_escape_string($con, $selectedBatchId);
$selectedClassIdSafe = mysqli_real_escape_string($con, $selectedClassId);

$batchOptions = array();
$classOptions = array();
$selectedBatchLabel = "";
$selectedClassLabel = "";

$batchQuery = mysqli_query($con, "SELECT batchid, batch, status FROM tblbatch ORDER BY datetimeentry DESC");
if ($batchQuery) {
    while ($row = mysqli_fetch_array($batchQuery, MYSQLI_ASSOC)) {
        $batchOptions[] = $row;
        if ($selectedBatchId !== "" && $selectedBatchId === trim((string)$row["batchid"])) {
            $selectedBatchLabel = trim((string)$row["batch"]);
            $status = trim((string)$row["status"]);
            if ($status !== "") {
                $selectedBatchLabel .= " (".$status.")";
            }
        }
    }
}

$classQuery = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
if ($classQuery) {
    while ($row = mysqli_fetch_array($classQuery, MYSQLI_ASSOC)) {
        $classOptions[] = $row;
        if ($selectedClassId !== "" && $selectedClassId === trim((string)$row["class_entryid"])) {
            $selectedClassLabel = trim((string)$row["class_name"]);
        }
    }
}

if ($selectedBatchLabel === "") {
    $selectedBatchLabel = "All batches / semesters";
}
if ($selectedClassLabel === "") {
    $selectedClassLabel = "All classes";
}

$sql = "SELECT * FROM vw_continuing_students WHERE 1=1";
if ($selectedBatchId !== "") {
    $sql .= " AND batchid='".$selectedBatchIdSafe."'";
}
if ($selectedClassId !== "") {
    $sql .= " AND class_entryid='".$selectedClassIdSafe."'";
}
$sql .= " ORDER BY class_name ASC, firstname ASC, othernames ASC, surname ASC, userid ASC";

$studentRows = array();
$classCoverage = array();
$maleCount = 0;
$femaleCount = 0;
$otherGenderCount = 0;
$boardingCount = 0;
$dayCount = 0;
$latestRecordedAt = "";

$studentQuery = mysqli_query($con, $sql);
if ($studentQuery) {
    while ($row = mysqli_fetch_array($studentQuery, MYSQLI_ASSOC)) {
        $studentRows[] = $row;

        $className = trim((string)(isset($row["class_name"]) ? $row["class_name"] : ""));
        if ($className !== "") {
            $classCoverage[$className] = true;
        }

        $gender = continuing_students_lower(trim((string)(isset($row["gender"]) ? $row["gender"] : "")));
        if ($gender === "male") {
            $maleCount++;
        } elseif ($gender === "female") {
            $femaleCount++;
        } elseif ($gender !== "") {
            $otherGenderCount++;
        }

        $residence = continuing_students_lower(trim((string)(isset($row["residencetype"]) ? $row["residencetype"] : "")));
        if (in_array($residence, array("boarding", "boarder", "resident"), true)) {
            $boardingCount++;
        } elseif (in_array($residence, array("day", "day student", "non resident", "non-resident"), true)) {
            $dayCount++;
        }

        $recordedAt = trim((string)(isset($row["datetimeentry"]) ? $row["datetimeentry"] : ""));
        if ($recordedAt !== "" && ($latestRecordedAt === "" || strtotime($recordedAt) > strtotime($latestRecordedAt))) {
            $latestRecordedAt = $recordedAt;
        }
    }
}

$totalStudents = count($studentRows);
$classCount = count($classCoverage);
$latestRecordedLabel = continuing_students_format_datetime($latestRecordedAt);
$resultSummaryText = $selectedBatchId !== "" || $selectedClassId !== ""
    ? "Filtered continuing student records for the selected scope."
    : "Showing the full continuing student list across available semesters and classes.";
?>
<!DOCTYPE html>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/continuing-students.css">
<script type="text/javascript" src="scripts/continuing-students.js" defer></script>
</head>
<body class="continuing-students-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="main-platform continuing-shell">
    <section class="continuing-hero">
        <div class="continuing-hero__copy">
            <span class="continuing-kicker"><i class="fa fa-users"></i> Continuing Students</span>
            <h1>Continuing Students</h1>
            <div class="continuing-hero__chips">
                <span class="continuing-chip"><i class="fa fa-calendar-check-o"></i> <?php echo continuing_students_esc($selectedBatchLabel); ?></span>
                <span class="continuing-chip"><i class="fa fa-graduation-cap"></i> <?php echo continuing_students_esc($selectedClassLabel); ?></span>
            </div>
        </div>

        <div class="continuing-hero__actions">
            <a href="promotion-center.php" class="continuing-btn continuing-btn--ghost"><i class="fa fa-level-up"></i> Promotion Center</a>
            <a href="continuing-students.php" class="continuing-btn continuing-btn--light"><i class="fa fa-refresh"></i> Reset View</a>
        </div>

        <div class="continuing-stats">
            <article class="continuing-stat">
                <span>Total Students</span>
                <strong><?php echo (int)$totalStudents; ?></strong>
                <small>Continuing learners in the current result set.</small>
            </article>
            <article class="continuing-stat">
                <span>Classes Covered</span>
                <strong><?php echo (int)$classCount; ?></strong>
                <small>Distinct class groups represented after filtering.</small>
            </article>
            <article class="continuing-stat">
                <span>Male Students</span>
                <strong><?php echo (int)$maleCount; ?></strong>
                <small>Boarding: <?php echo (int)$boardingCount; ?></small>
            </article>
            <article class="continuing-stat">
                <span>Female Students</span>
                <strong><?php echo (int)$femaleCount; ?></strong>
                <small>Day: <?php echo (int)$dayCount; ?><?php echo $otherGenderCount > 0 ? " | Other/blank: ".(int)$otherGenderCount : ""; ?></small>
            </article>
        </div>
    </section>

    <section class="continuing-panel continuing-filter-panel">
        <div class="continuing-panel__head">
            <div>
                <h2>Filter Students</h2>
                <p>Pick the semester batch you want to review, then narrow down to one class if needed.</p>
            </div>
            <div class="continuing-panel__tag">
                <i class="fa fa-filter"></i> Scoped View
            </div>
        </div>

        <form method="get" action="continuing-students.php" class="continuing-filter-grid">
            <label for="batchid">
                <span>Batch / Semester</span>
                <select name="batchid" id="batchid">
                    <option value="">All Batches</option>
                    <?php foreach ($batchOptions as $batchOption) { ?>
                    <option value="<?php echo continuing_students_esc($batchOption["batchid"]); ?>"<?php echo $selectedBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                        <?php echo continuing_students_esc($batchOption["batch"]); ?><?php echo trim((string)$batchOption["status"]) !== "" ? " (".continuing_students_esc($batchOption["status"]).")" : ""; ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <label for="classid">
                <span>Class</span>
                <select name="classid" id="classid">
                    <option value="">All Classes</option>
                    <?php foreach ($classOptions as $classOption) { ?>
                    <option value="<?php echo continuing_students_esc($classOption["class_entryid"]); ?>"<?php echo $selectedClassId === trim((string)$classOption["class_entryid"]) ? " selected" : ""; ?>>
                        <?php echo continuing_students_esc($classOption["class_name"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <div class="continuing-filter-actions">
                <button type="submit" class="continuing-btn continuing-btn--primary"><i class="fa fa-search"></i> Apply Filter</button>
                <a href="continuing-students.php" class="continuing-btn continuing-btn--secondary"><i class="fa fa-times"></i> Clear</a>
            </div>
        </form>
    </section>

    <section class="continuing-panel continuing-results-panel">
        <div class="continuing-panel__head continuing-panel__head--results">
            <div>
                <h2>Continuing Student Records</h2>
                <p><?php echo continuing_students_esc($resultSummaryText); ?></p>
            </div>

            <div class="continuing-search-block">
                <label for="continuing-search">Quick Search</label>
                <div class="continuing-search-field">
                    <i class="fa fa-search"></i>
                    <input
                        type="search"
                        id="continuing-search"
                        placeholder="Search by name, ID, class, batch, gender or residence"
                        <?php echo $totalStudents === 0 ? "disabled" : ""; ?>
                    >
                </div>
            </div>
        </div>

        <div class="continuing-active-filters">
            <span class="continuing-filter-pill"><i class="fa fa-calendar"></i> <?php echo continuing_students_esc($selectedBatchLabel); ?></span>
            <span class="continuing-filter-pill"><i class="fa fa-university"></i> <?php echo continuing_students_esc($selectedClassLabel); ?></span>
            <span class="continuing-filter-pill"><i class="fa fa-clock-o"></i> Latest record: <?php echo continuing_students_esc($latestRecordedLabel); ?></span>
        </div>

        <?php if ($totalStudents > 0) { ?>
        <div class="continuing-results-meta">
            <p><strong id="continuing-visible-count"><?php echo (int)$totalStudents; ?></strong> of <?php echo (int)$totalStudents; ?> students visible</p>
            <p>Use the action button to open any student's transcript.</p>
        </div>

        <div class="continuing-table-wrap">
            <table class="continuing-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Gender</th>
                        <th>Residence</th>
                        <th>Transcript</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody id="continuing-students-body">
                    <?php foreach ($studentRows as $index => $row) {
                        $studentId = trim((string)(isset($row["userid"]) ? $row["userid"] : ""));
                        $studentName = continuing_students_name($row);
                        $className = trim((string)(isset($row["class_name"]) ? $row["class_name"] : ""));
                        $batchName = trim((string)(isset($row["batch"]) ? $row["batch"] : ""));
                        $gender = trim((string)(isset($row["gender"]) ? $row["gender"] : ""));
                        $residence = trim((string)(isset($row["residencetype"]) ? $row["residencetype"] : ""));
                        $recordedAt = continuing_students_format_datetime(isset($row["datetimeentry"]) ? $row["datetimeentry"] : "");
                        $searchIndex = continuing_students_lower($studentName." ".$studentId." ".$className." ".$batchName." ".$gender." ".$residence);
                    ?>
                    <tr class="continuing-row" data-search="<?php echo continuing_students_esc($searchIndex); ?>">
                        <td data-label="#"><?php echo (int)($index + 1); ?></td>
                        <td data-label="Student">
                            <div class="continuing-student-cell">
                                <strong><?php echo continuing_students_esc($studentName); ?></strong>
                                <small><?php echo $studentId !== "" ? continuing_students_esc($studentId) : "No ID"; ?></small>
                            </div>
                        </td>
                        <td data-label="Class"><span class="continuing-table-pill"><?php echo $className !== "" ? continuing_students_esc($className) : "N/A"; ?></span></td>
                        <td data-label="Batch"><?php echo $batchName !== "" ? continuing_students_esc($batchName) : "N/A"; ?></td>
                        <td data-label="Gender"><?php echo $gender !== "" ? continuing_students_esc($gender) : "N/A"; ?></td>
                        <td data-label="Residence"><?php echo $residence !== "" ? continuing_students_esc($residence) : "N/A"; ?></td>
                        <td data-label="Transcript">
                            <?php if ($studentId !== "") { ?>
                            <a href="student-history.php?studentid=<?php echo urlencode($studentId); ?>" class="continuing-link-btn" title="View Transcript">
                                <i class="fa fa-history"></i>
                                <span>Open Transcript</span>
                            </a>
                            <?php } else { ?>
                            <span class="continuing-link-btn continuing-link-btn--disabled">
                                <i class="fa fa-ban"></i>
                                <span>Unavailable</span>
                            </span>
                            <?php } ?>
                        </td>
                        <td data-label="Recorded"><?php echo continuing_students_esc($recordedAt); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div id="continuing-search-empty" class="continuing-empty-state continuing-empty-state--search" hidden>
            <i class="fa fa-search"></i>
            <h3>No students match this search.</h3>
            <p>Try a broader name, class, ID, or batch keyword and the list will update instantly.</p>
        </div>
        <?php } else { ?>
        <div class="continuing-empty-state">
            <i class="fa fa-folder-open-o"></i>
            <h3>No continuing student records were found.</h3>
            <p>Adjust the semester or class filter, or confirm that the selected batch has registered continuing students.</p>
        </div>
        <?php } ?>
    </section>
</main>
</body>
</html>

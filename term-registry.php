<?php
session_start();
include("dbstring.php");
include_once("semester-registry-utils.php");

semester_registry_ensure_academic_year_column($con);

if (!function_exists("trr_esc")) {
function trr_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if (!function_exists("trr_lower")) {
function trr_lower($value)
{
    $value = (string)$value;
    return function_exists("mb_strtolower") ? mb_strtolower($value, "UTF-8") : strtolower($value);
}
}

if (!function_exists("trr_student_name")) {
function trr_student_name($row)
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

if (!function_exists("trr_format_datetime")) {
function trr_format_datetime($value)
{
    $value = trim((string)$value);
    if ($value === "") {
        return "Not recorded";
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y, H:i", $timestamp) : $value;
}
}

if (!function_exists("trr_alert")) {
function trr_alert($tone, $title, $message)
{
    $allowed = array("success", "danger", "warning", "info");
    $tone = in_array($tone, $allowed, true) ? $tone : "info";
    return '<div class="trr-alert trr-alert--' . trr_esc($tone) . '"><strong>' . trr_esc($title) . '</strong><span>' . trr_esc($message) . "</span></div>";
}
}

if (!function_exists("trr_generate_shortcode")) {
function trr_generate_shortcode()
{
    include("shortcode.php");
    return isset($shortcode) ? (string)$shortcode : "";
}
}

if (!function_exists("trr_build_query")) {
function trr_build_query($params)
{
    $clean = array();
    foreach ((array)$params as $key => $value) {
        $value = trim((string)$value);
        if ($value !== "") {
            $clean[$key] = $value;
        }
    }
    return http_build_query($clean);
}
}

$flashHtml = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

$selectedStudentId = trim((string)($_POST["userid"] ?? $_GET["view_user"] ?? ""));
$selectedClassId = trim((string)($_POST["classid"] ?? $_GET["class_id"] ?? ""));
$selectedTermId = trim((string)($_POST["termid"] ?? ""));
$selectedTerm = trim((string)($_POST["term"] ?? ""));
$searchClassEntryId = trim((string)($_POST["class_entryid"] ?? $_GET["class_entryid"] ?? ""));
$academicYearFilter = semester_registry_normalize_year($_POST["academicyear_filter"] ?? $_GET["academicyear_filter"] ?? "");
$searchBatchId = trim((string)($_POST["batchid_filter"] ?? $_GET["batchid_filter"] ?? ""));
$selectedBatchId = trim((string)($_POST["batchid"] ?? $searchBatchId));
$selectedAcademicYear = semester_registry_normalize_year($_POST["academicyear"] ?? $academicYearFilter);
$recordedBy = trim((string)($_SESSION["USERID"] ?? ""));

if (isset($_GET["delete_term"]) && trim((string)$_GET["delete_term"]) !== "") {
    $deleteTermId = mysqli_real_escape_string($con, trim((string)$_GET["delete_term"]));
    $deleteQuery = mysqli_query($con, "DELETE FROM tbltermregistry WHERE termid='$deleteTermId'");
    if ($deleteQuery) {
        $flashHtml = trr_alert("warning", "Semester Deleted", "The selected semester registration has been removed.") . $flashHtml;
    } else {
        $flashHtml = trr_alert("danger", "Delete Failed", "The semester registration could not be removed.") . $flashHtml;
    }
}

if (isset($_POST["register_term"])) {
    if ($selectedStudentId === "" || $selectedClassId === "" || $selectedTerm === "" || $selectedBatchId === "" || $selectedAcademicYear === "") {
        $flashHtml = trr_alert("danger", "Missing Details", "Select the student, class, semester, batch, and academic year before saving.") . $flashHtml;
    } else {
        $selectedStudentIdEsc = mysqli_real_escape_string($con, $selectedStudentId);
        $selectedClassIdEsc = mysqli_real_escape_string($con, $selectedClassId);
        $selectedTermEsc = mysqli_real_escape_string($con, $selectedTerm);
        $selectedBatchIdEsc = mysqli_real_escape_string($con, $selectedBatchId);
        $selectedAcademicYearEsc = mysqli_real_escape_string($con, $selectedAcademicYear);
        $termIdToSave = $selectedTermId !== "" ? $selectedTermId : trr_generate_shortcode();
        $termIdToSaveEsc = mysqli_real_escape_string($con, $termIdToSave);
        $recordedByEsc = mysqli_real_escape_string($con, $recordedBy);

        $classExistsQuery = mysqli_query($con, "SELECT class_entryid FROM tblclassentry WHERE class_entryid='$selectedClassIdEsc' LIMIT 1");
        if (!$classExistsQuery || mysqli_num_rows($classExistsQuery) === 0) {
            $flashHtml = trr_alert("danger", "Invalid Class", "The selected class is no longer available. Reload the student from the list and try again.") . $flashHtml;
        } else {
            $classMatchQuery = mysqli_query($con, "SELECT classid FROM tblclass WHERE userid='$selectedStudentIdEsc' AND class_entryid='$selectedClassIdEsc' AND batchid='$selectedBatchIdEsc' AND status='active' LIMIT 1");
            if (!$classMatchQuery || mysqli_num_rows($classMatchQuery) === 0) {
                $flashHtml = trr_alert("danger", "Class Record Not Found", "This student is not in an active class for the selected batch. Open the student from the current list and try again.") . $flashHtml;
            } else {
                $resolvedYearSql = semester_registry_resolved_year_sql("tbltermregistry");
                $duplicateQuery = mysqli_query($con, "SELECT termid FROM tbltermregistry WHERE userid='$selectedStudentIdEsc' AND class_entryid='$selectedClassIdEsc' AND termname='$selectedTermEsc' AND batchid='$selectedBatchIdEsc' AND $resolvedYearSql='$selectedAcademicYearEsc' LIMIT 1");
                if ($duplicateQuery && mysqli_num_rows($duplicateQuery) > 0) {
                    $batchName = isset($_POST["batch_name"]) ? trim((string)$_POST["batch_name"]) : "";
                    if ($batchName === "") {
                        $batchQuery = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$selectedBatchIdEsc' LIMIT 1");
                        if ($batchQuery && ($batchRow = mysqli_fetch_array($batchQuery, MYSQLI_ASSOC))) {
                            $batchName = trim((string)$batchRow["batch"]);
                        }
                    }
                    $flashHtml = trr_alert("warning", "Already Registered", "This student already has a semester record for academic year " . $selectedAcademicYear . ", semester " . $selectedTerm . ($batchName !== "" ? ", batch " . $batchName : "") . ".") . $flashHtml;
                } else {
                    $insertSql = "INSERT INTO tbltermregistry(termid, userid, class_entryid, termname, batchid, academicyear, status, datetimeentry, recordedby)
                        VALUES('$termIdToSaveEsc', '$selectedStudentIdEsc', '$selectedClassIdEsc', '$selectedTermEsc', '$selectedBatchIdEsc', '$selectedAcademicYearEsc', 'active', NOW(), '$recordedByEsc')";
                    $insertQuery = mysqli_query($con, $insertSql);
                    if ($insertQuery) {
                        $flashHtml = trr_alert("success", "Semester Registered", "The semester record was saved successfully for the selected student.") . $flashHtml;
                        $selectedTermId = "";
                    } else {
                        $flashHtml = trr_alert("danger", "Save Failed", "The semester record could not be saved. " . mysqli_error($con)) . $flashHtml;
                    }
                }
            }
        }
    }
}

$classOptions = array();
$classQuery = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
if ($classQuery) {
    while ($row = mysqli_fetch_array($classQuery, MYSQLI_ASSOC)) {
        $classOptions[] = $row;
    }
}

$batchOptions = array();
$batchQuery = mysqli_query($con, "SELECT batchid, batch, status FROM tblbatch ORDER BY batch DESC");
if ($batchQuery) {
    while ($row = mysqli_fetch_array($batchQuery, MYSQLI_ASSOC)) {
        $batchOptions[] = $row;
    }
}

$classLabelMap = array();
foreach ($classOptions as $classOption) {
    $classLabelMap[trim((string)$classOption["class_entryid"])] = trim((string)$classOption["class_name"]);
}

$batchLabelMap = array();
foreach ($batchOptions as $batchOption) {
    $batchLabelMap[trim((string)$batchOption["batchid"])] = trim((string)$batchOption["batch"]);
}

$searchClassLabel = $searchClassEntryId !== "" && isset($classLabelMap[$searchClassEntryId]) ? $classLabelMap[$searchClassEntryId] : "Class not selected";
$searchBatchLabel = $searchBatchId !== "" && isset($batchLabelMap[$searchBatchId]) ? $batchLabelMap[$searchBatchId] : "Batch not selected";
$selectedClassLabel = $selectedClassId !== "" && isset($classLabelMap[$selectedClassId]) ? $classLabelMap[$selectedClassId] : "Class not selected";
$selectedBatchLabel = $selectedBatchId !== "" && isset($batchLabelMap[$selectedBatchId]) ? $batchLabelMap[$selectedBatchId] : "Batch not selected";

$selectedStudent = null;
if ($selectedStudentId !== "") {
    $selectedStudentIdEsc = mysqli_real_escape_string($con, $selectedStudentId);
    $selectedStudentQuery = mysqli_query($con, "SELECT userid, firstname, othernames, surname FROM tblsystemuser WHERE userid='$selectedStudentIdEsc' LIMIT 1");
    if ($selectedStudentQuery && ($selectedStudentRow = mysqli_fetch_array($selectedStudentQuery, MYSQLI_ASSOC))) {
        $selectedStudent = $selectedStudentRow;
    }
}

$shouldLoadRecords = $searchClassEntryId !== "" && $searchBatchId !== "" && (
    isset($_POST["show_semester"]) ||
    isset($_POST["register_term"]) ||
    isset($_GET["class_entryid"]) ||
    isset($_GET["batchid_filter"]) ||
    isset($_GET["view_user"]) ||
    isset($_GET["delete_term"])
);

$studentRows = array();
$termRowsByUser = array();
$totalTermRows = 0;
$studentsWithTerms = 0;
$latestRecordAt = "";

if ($shouldLoadRecords) {
    $searchClassEntryIdEsc = mysqli_real_escape_string($con, $searchClassEntryId);
    $searchBatchIdEsc = mysqli_real_escape_string($con, $searchBatchId);
    $studentSql = "SELECT
            su.userid,
            su.firstname,
            su.surname,
            su.othernames,
            cl.class_entryid,
            cl.batchid,
            cl.datetimeentry,
            ce.class_name,
            bh.batch
        FROM tblsystemuser su
        INNER JOIN tblclass cl ON su.userid=cl.userid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
        WHERE cl.batchid='$searchBatchIdEsc'
          AND cl.class_entryid='$searchClassEntryIdEsc'
          AND cl.status='active'
          AND su.systemtype='Student'
        ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC, su.userid ASC";
    $studentListQuery = mysqli_query($con, $studentSql);
    if ($studentListQuery) {
        while ($row = mysqli_fetch_array($studentListQuery, MYSQLI_ASSOC)) {
            $studentRows[] = $row;
            $recordedAt = trim((string)($row["datetimeentry"] ?? ""));
            if ($recordedAt !== "" && ($latestRecordAt === "" || strtotime($recordedAt) > strtotime($latestRecordAt))) {
                $latestRecordAt = $recordedAt;
            }
        }
    }

    if (count($studentRows) > 0) {
        $userIdList = array();
        foreach ($studentRows as $studentRow) {
            $userIdList[] = "'" . mysqli_real_escape_string($con, trim((string)$studentRow["userid"])) . "'";
        }
        $resolvedYearSql = semester_registry_resolved_year_sql("tr");
        $academicYearWhere = $academicYearFilter !== "" ? " AND $resolvedYearSql='" . mysqli_real_escape_string($con, $academicYearFilter) . "'" : "";
        $termSql = "SELECT tr.termid, tr.userid, tr.class_entryid, tr.termname, tr.batchid, tr.datetimeentry, b.batch, $resolvedYearSql AS resolved_academic_year
            FROM tbltermregistry tr
            INNER JOIN tblbatch b ON tr.batchid=b.batchid
            WHERE tr.userid IN (" . implode(",", $userIdList) . ")
              AND tr.class_entryid='$searchClassEntryIdEsc'
              AND tr.batchid='$searchBatchIdEsc'
              $academicYearWhere
            ORDER BY resolved_academic_year DESC, tr.termname ASC, tr.datetimeentry DESC";
        $termQuery = mysqli_query($con, $termSql);
        if ($termQuery) {
            while ($termRow = mysqli_fetch_array($termQuery, MYSQLI_ASSOC)) {
                $userId = trim((string)$termRow["userid"]);
                if (!isset($termRowsByUser[$userId])) {
                    $termRowsByUser[$userId] = array();
                }
                $termRowsByUser[$userId][] = $termRow;
                $totalTermRows++;
            }
        }
    }

    foreach ($termRowsByUser as $rows) {
        if (count($rows) > 0) {
            $studentsWithTerms++;
        }
    }
}

$loadedStudentCount = count($studentRows);
$latestRecordLabel = $latestRecordAt !== "" ? trr_format_datetime($latestRecordAt) : "No records loaded";
$selectedStudentName = $selectedStudent ? trr_student_name($selectedStudent) : "No student selected";
$resultSummaryText = !$shouldLoadRecords
    ? "Choose a class and batch to load the active students and their semester history."
    : ($loadedStudentCount > 0
        ? "Showing active students for the selected class. Open any student to register a new semester record or remove an old one."
        : "No active students were found for the selected class and batch.");

$newTermId = trr_generate_shortcode();
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/term-registry.css">
<script type="text/javascript" src="scripts/term-registry.js" defer></script>
</head>
<body class="body-style term-registry-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="main-platform trr-shell">
    <section class="trr-hero">
        <div class="trr-hero__copy">
            <span class="trr-kicker"><i class="fa fa-book"></i> Semester Registry</span>
            <h1>Single Student Semester Registration</h1>
            <p>Open a class list, choose the student you want to work on, and keep the semester history in view while you register or remove records. The page stays clean and readable across desktop and mobile screens.</p>
            <div class="trr-hero__chips">
                <span class="trr-chip"><i class="fa fa-user"></i> One student at a time</span>
                <span class="trr-chip"><i class="fa fa-search"></i> Search class history</span>
                <span class="trr-chip"><i class="fa fa-mobile"></i> Mobile ready</span>
            </div>
        </div>

        <div class="trr-hero__actions">
            <a href="group-term-registry.php" class="trr-btn trr-btn--ghost"><i class="fa fa-clone"></i> Group Entry</a>
            <a href="term-registry.php" class="trr-btn trr-btn--light"><i class="fa fa-refresh"></i> Reset Page</a>
        </div>

        <div class="trr-stats">
            <article class="trr-stat">
                <span>Students Loaded</span>
                <strong><?php echo (int)$loadedStudentCount; ?></strong>
                <small><?php echo $shouldLoadRecords ? "Active students available in the current class filter." : "Load a class list to begin."; ?></small>
            </article>
            <article class="trr-stat">
                <span>Semester Records</span>
                <strong><?php echo (int)$totalTermRows; ?></strong>
                <small><?php echo $shouldLoadRecords ? "Semester records currently shown in the selected class scope." : "Filter by class and batch to view history."; ?></small>
            </article>
            <article class="trr-stat">
                <span>Selected Student</span>
                <strong><?php echo trr_esc($selectedStudent ? $selectedStudentName : "Pending"); ?></strong>
                <small><?php echo $selectedStudent ? "Ready for semester registration." : "Choose a student from the class list."; ?></small>
            </article>
            <article class="trr-stat">
                <span>Latest Class Record</span>
                <strong><?php echo trr_esc($shouldLoadRecords ? $latestRecordLabel : "Pending"); ?></strong>
                <small><?php echo $shouldLoadRecords ? "Most recent active class entry in the selected scope." : "No student list loaded yet."; ?></small>
            </article>
        </div>
    </section>

    <?php if ($flashHtml !== "") { ?>
    <div class="trr-message-stack"><?php echo $flashHtml; ?></div>
    <?php } ?>

    <div class="trr-workspace">
        <aside class="trr-panel trr-register-panel">
            <div class="trr-panel__head">
                <div>
                    <h2>Register Semester</h2>
                    <p>Review the student, confirm the semester details, and save the record for the selected class and batch.</p>
                </div>
                <div class="trr-panel__tag"><i class="fa fa-pencil-square-o"></i> Single Entry</div>
            </div>

            <?php if ($selectedStudent) { ?>
            <div class="trr-student-summary">
                <span class="trr-student-summary__serial"><i class="fa fa-user"></i></span>
                <div class="trr-student-summary__content">
                    <strong><?php echo trr_esc($selectedStudentName); ?></strong>
                    <span><i class="fa fa-id-card-o"></i> <?php echo trr_esc($selectedStudent["userid"]); ?></span>
                    <span><i class="fa fa-graduation-cap"></i> <?php echo trr_esc($selectedClassLabel); ?></span>
                </div>
            </div>
            <?php } else { ?>
            <div class="trr-empty-state trr-empty-state--compact">
                <i class="fa fa-user-o"></i>
                <h3>Select a student from the class list.</h3>
                <p>The registration form will stay ready here once you open a student from the results panel.</p>
            </div>
            <?php } ?>

            <form method="post" action="term-registry.php" class="trr-form-grid">
                <input type="hidden" name="userid" value="<?php echo trr_esc($selectedStudentId); ?>">
                <input type="hidden" name="classid" value="<?php echo trr_esc($selectedClassId); ?>">
                <input type="hidden" name="class_entryid" value="<?php echo trr_esc($searchClassEntryId); ?>">
                <input type="hidden" name="batchid_filter" value="<?php echo trr_esc($searchBatchId); ?>">
                <input type="hidden" name="academicyear_filter" value="<?php echo trr_esc($academicYearFilter); ?>">
                <input type="hidden" name="batch_name" value="<?php echo trr_esc($selectedBatchLabel); ?>">

                <label for="termid">
                    <span>Semester ID</span>
                    <input type="text" id="termid" name="termid" value="<?php echo trr_esc($newTermId); ?>" readonly>
                </label>

                <label for="student_name">
                    <span>Student</span>
                    <input type="text" id="student_name" value="<?php echo trr_esc($selectedStudent ? $selectedStudentName . " (" . $selectedStudent["userid"] . ")" : "No student selected"); ?>" readonly>
                </label>

                <label for="class_name_display">
                    <span>Class</span>
                    <input type="text" id="class_name_display" value="<?php echo trr_esc($selectedClassLabel); ?>" readonly>
                </label>

                <label for="term">
                    <span>Semester</span>
                    <select id="term" name="term" required>
                        <option value="">Select Semester</option>
                        <option value="1"<?php echo $selectedTerm === "1" ? " selected" : ""; ?>>1</option>
                        <option value="2"<?php echo $selectedTerm === "2" ? " selected" : ""; ?>>2</option>
                    </select>
                </label>

                <label for="academicyear">
                    <span>Academic Year</span>
                    <select id="academicyear" name="academicyear" required>
                        <option value="">Select Academic Year</option>
                        <?php foreach (semester_registry_year_options() as $yearOption) { ?>
                        <option value="<?php echo trr_esc($yearOption); ?>"<?php echo $selectedAcademicYear === $yearOption ? " selected" : ""; ?>>
                            <?php echo trr_esc($yearOption); ?>
                        </option>
                        <?php } ?>
                    </select>
                </label>

                <label for="batchid">
                    <span>Batch</span>
                    <select id="batchid" name="batchid" required>
                        <option value="">Select Batch</option>
                        <?php foreach ($batchOptions as $batchOption) { ?>
                        <option value="<?php echo trr_esc($batchOption["batchid"]); ?>"<?php echo $selectedBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                            <?php echo trr_esc($batchOption["batch"]); ?>
                        </option>
                        <?php } ?>
                    </select>
                </label>

                <div class="trr-note">
                    <i class="fa fa-info-circle"></i>
                    <p>Open the student from the current class list so the class and batch stay aligned with the active registry entry.</p>
                </div>

                <div class="trr-submit-bar">
                    <button type="submit" class="trr-btn trr-btn--primary" name="register_term" <?php echo $selectedStudent ? "" : "disabled"; ?>><i class="fa fa-save"></i> Save Semester</button>
                    <a href="term-registry.php?<?php echo trr_esc(trr_build_query(array("class_entryid" => $searchClassEntryId, "batchid_filter" => $searchBatchId, "academicyear_filter" => $academicYearFilter))); ?>" class="trr-btn trr-btn--secondary"><i class="fa fa-times"></i> Clear Student</a>
                </div>
            </form>
        </aside>

        <div class="trr-results-stack">
            <section class="trr-panel">
                <div class="trr-panel__head">
                    <div>
                        <h2>Find Students</h2>
                        <p>Choose the class and batch you want to review, then load the active students and their semester history.</p>
                    </div>
                    <div class="trr-panel__tag"><i class="fa fa-filter"></i> Class Filter</div>
                </div>

                <form method="post" action="term-registry.php" class="trr-filter-grid">
                    <label for="class_entryid">
                        <span>Class</span>
                        <select id="class_entryid" name="class_entryid" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classOptions as $classOption) { ?>
                            <option value="<?php echo trr_esc($classOption["class_entryid"]); ?>"<?php echo $searchClassEntryId === trim((string)$classOption["class_entryid"]) ? " selected" : ""; ?>>
                                <?php echo trr_esc($classOption["class_name"]); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                    <label for="academicyear_filter">
                        <span>Academic Year</span>
                        <select id="academicyear_filter" name="academicyear_filter">
                            <option value="">All Academic Years</option>
                            <?php foreach (semester_registry_year_options() as $yearOption) { ?>
                            <option value="<?php echo trr_esc($yearOption); ?>"<?php echo $academicYearFilter === $yearOption ? " selected" : ""; ?>>
                                <?php echo trr_esc($yearOption); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                    <label for="batchid_filter">
                        <span>Batch</span>
                        <select id="batchid_filter" name="batchid_filter" required>
                            <option value="">Select Batch</option>
                            <?php foreach ($batchOptions as $batchOption) { ?>
                            <option value="<?php echo trr_esc($batchOption["batchid"]); ?>"<?php echo $searchBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                                <?php echo trr_esc($batchOption["batch"]); ?><?php echo trim((string)$batchOption["status"]) !== "" ? " (" . trr_esc($batchOption["status"]) . ")" : ""; ?>
                            </option>
                            <?php } ?>
                        </select>
                    </label>

                    <div class="trr-filter-actions">
                        <button type="submit" name="show_semester" class="trr-btn trr-btn--primary"><i class="fa fa-search"></i> Load Students</button>
                        <a href="term-registry.php" class="trr-btn trr-btn--secondary"><i class="fa fa-times"></i> Clear</a>
                    </div>
                </form>
            </section>

            <section class="trr-panel trr-results-panel">
                <div class="trr-panel__head trr-panel__head--results">
                    <div>
                        <h2>Semester History</h2>
                        <p><?php echo trr_esc($resultSummaryText); ?></p>
                    </div>

                    <div class="trr-search-block">
                        <label for="trr-student-search">Quick Search</label>
                        <div class="trr-search-field">
                            <i class="fa fa-search"></i>
                            <input type="search" id="trr-student-search" placeholder="Search by student name, ID, class or batch" <?php echo $loadedStudentCount === 0 ? "disabled" : ""; ?>>
                        </div>
                    </div>
                </div>

                <?php if ($shouldLoadRecords) { ?>
                <div class="trr-active-filters">
                    <span class="trr-filter-pill"><i class="fa fa-graduation-cap"></i> <?php echo trr_esc($searchClassLabel); ?></span>
                    <span class="trr-filter-pill"><i class="fa fa-calendar"></i> <?php echo trr_esc($searchBatchLabel); ?></span>
                    <span class="trr-filter-pill"><i class="fa fa-folder-open-o"></i> <?php echo trr_esc($academicYearFilter !== "" ? "Academic Year " . $academicYearFilter : "All academic years"); ?></span>
                </div>
                <?php } ?>

                <?php if (!$shouldLoadRecords) { ?>
                <div class="trr-empty-state">
                    <i class="fa fa-filter"></i>
                    <h3>Select a class and batch to view students.</h3>
                    <p>The student list and semester history will appear here after you load a class.</p>
                </div>
                <?php } elseif ($loadedStudentCount === 0) { ?>
                <div class="trr-empty-state">
                    <i class="fa fa-users"></i>
                    <h3>No active students were found.</h3>
                    <p>Try a different class or batch, or confirm that the students already have active class records.</p>
                </div>
                <?php } else { ?>
                <div class="trr-results-meta">
                    <p><strong id="trr-visible-count"><?php echo (int)$loadedStudentCount; ?></strong> of <?php echo (int)$loadedStudentCount; ?> students visible</p>
                    <p><?php echo (int)$studentsWithTerms; ?> student<?php echo $studentsWithTerms === 1 ? "" : "s"; ?> currently have semester records in this view.</p>
                </div>

                <div class="trr-student-grid" id="trr-student-list">
                    <?php foreach ($studentRows as $studentRow) { ?>
                    <?php
                        $studentId = trim((string)$studentRow["userid"]);
                        $studentName = trr_student_name($studentRow);
                        $studentTerms = isset($termRowsByUser[$studentId]) ? $termRowsByUser[$studentId] : array();
                        $searchText = trr_lower($studentName . " " . $studentId . " " . ($studentRow["class_name"] ?? "") . " " . ($studentRow["batch"] ?? ""));
                        $studentLinkQuery = trr_build_query(array(
                            "view_user" => $studentId,
                            "class_id" => trim((string)$studentRow["class_entryid"]),
                            "class_entryid" => $searchClassEntryId,
                            "batchid_filter" => $searchBatchId,
                            "academicyear_filter" => $academicYearFilter,
                        ));
                    ?>
                    <article class="trr-student-card<?php echo $selectedStudentId === $studentId ? " is-selected" : ""; ?>" data-student-card data-search-text="<?php echo trr_esc($searchText); ?>">
                        <div class="trr-student-card__head">
                            <div class="trr-student-card__identity">
                                <span class="trr-student-card__avatar"><?php echo trr_esc(substr($studentName, 0, 1)); ?></span>
                                <div>
                                    <h3><?php echo trr_esc($studentName); ?></h3>
                                    <p><i class="fa fa-id-card-o"></i> <?php echo trr_esc($studentId); ?></p>
                                </div>
                            </div>
                            <a href="term-registry.php?<?php echo trr_esc($studentLinkQuery); ?>" class="trr-btn trr-btn--secondary"><i class="fa fa-plus"></i> Register</a>
                        </div>

                        <div class="trr-student-card__chips">
                            <span class="trr-student-chip"><i class="fa fa-graduation-cap"></i> <?php echo trr_esc(trim((string)($studentRow["class_name"] ?? $searchClassLabel))); ?></span>
                            <span class="trr-student-chip"><i class="fa fa-calendar"></i> <?php echo trr_esc(trim((string)($studentRow["batch"] ?? $searchBatchLabel))); ?></span>
                            <span class="trr-student-chip"><i class="fa fa-book"></i> <?php echo count($studentTerms); ?> record<?php echo count($studentTerms) === 1 ? "" : "s"; ?></span>
                        </div>

                        <?php if (count($studentTerms) > 0) { ?>
                        <div class="trr-term-list">
                            <?php foreach ($studentTerms as $termRow) { ?>
                            <?php
                                $deleteLinkQuery = trr_build_query(array(
                                    "delete_term" => trim((string)$termRow["termid"]),
                                    "view_user" => $selectedStudentId !== "" ? $selectedStudentId : $studentId,
                                    "class_id" => $selectedClassId !== "" ? $selectedClassId : trim((string)$studentRow["class_entryid"]),
                                    "class_entryid" => $searchClassEntryId,
                                    "batchid_filter" => $searchBatchId,
                                    "academicyear_filter" => $academicYearFilter,
                                ));
                            ?>
                            <div class="trr-term-row">
                                <div class="trr-term-row__meta">
                                    <span class="trr-term-pill"><i class="fa fa-folder-open-o"></i> <?php echo trr_esc($termRow["resolved_academic_year"]); ?></span>
                                    <span class="trr-term-pill"><i class="fa fa-bookmark"></i> Semester <?php echo trr_esc($termRow["termname"]); ?></span>
                                    <span class="trr-term-pill"><i class="fa fa-calendar-check-o"></i> <?php echo trr_esc($termRow["batch"]); ?></span>
                                </div>
                                <div class="trr-term-row__foot">
                                    <small><?php echo trr_esc(trr_format_datetime($termRow["datetimeentry"])); ?></small>
                                    <a href="term-registry.php?<?php echo trr_esc($deleteLinkQuery); ?>" class="trr-delete-link" onclick="return confirm('Do you want to remove this semester record?');"><i class="fa fa-trash-o"></i> Remove</a>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } else { ?>
                        <div class="trr-mini-empty">
                            <i class="fa fa-book"></i>
                            <span>No semester records found for this student in the current filter.</span>
                        </div>
                        <?php } ?>
                    </article>
                    <?php } ?>
                </div>

                <div class="trr-empty-state trr-empty-state--compact" id="trr-no-results" hidden>
                    <i class="fa fa-search"></i>
                    <h3>No students match this search.</h3>
                    <p>Clear the search text or try a different class filter to see more students.</p>
                </div>
                <?php } ?>
            </section>
        </div>
    </div>
</main>
</body>
</html>

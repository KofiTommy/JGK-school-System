<?php
session_start();
include("dbstring.php");
include_once("semester-registry-utils.php");

semester_registry_ensure_academic_year_column($con);

if (!function_exists("gtr_esc")) {
function gtr_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if (!function_exists("gtr_lower")) {
function gtr_lower($value)
{
    $value = (string)$value;
    return function_exists("mb_strtolower") ? mb_strtolower($value, "UTF-8") : strtolower($value);
}
}

if (!function_exists("gtr_student_name")) {
function gtr_student_name($row)
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

if (!function_exists("gtr_format_datetime")) {
function gtr_format_datetime($value)
{
    $value = trim((string)$value);
    if ($value === "") {
        return "Not recorded";
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y, H:i", $timestamp) : $value;
}
}

if (!function_exists("gtr_alert")) {
function gtr_alert($tone, $title, $message)
{
    $allowed = array("success", "danger", "warning", "info");
    $tone = in_array($tone, $allowed, true) ? $tone : "info";
    return '<div class="gtr-alert gtr-alert--' . gtr_esc($tone) . '"><strong>' . gtr_esc($title) . '</strong><span>' . gtr_esc($message) . "</span></div>";
}
}

$flashHtml = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

if (isset($_GET["delete_term"]) && trim((string)$_GET["delete_term"]) !== "") {
    $deleteTermId = mysqli_real_escape_string($con, trim((string)$_GET["delete_term"]));
    $deleteQuery = mysqli_query($con, "DELETE FROM tbltermregistry WHERE termid='$deleteTermId'");
    if ($deleteQuery) {
        $flashHtml = gtr_alert("warning", "Semester Deleted", "The selected semester registration has been removed.") . $flashHtml;
    } else {
        $flashHtml = gtr_alert("danger", "Delete Failed", "The semester registration could not be removed.") . $flashHtml;
    }
}

$searchClassId = trim((string)($_POST["search_classentryid"] ?? $_POST["classentryid"] ?? $_GET["classentryid"] ?? ""));
$searchBatchId = trim((string)($_POST["search_batchid2"] ?? $_POST["batchid2"] ?? $_GET["batchid2"] ?? ""));
$selectedClassId = trim((string)($_POST["classid"] ?? $searchClassId));
$selectedTerm = trim((string)($_POST["term"] ?? ""));
$selectedBatchId = trim((string)($_POST["batchid"] ?? $searchBatchId));
$selectedAcademicYear = semester_registry_normalize_year($_POST["academicyear"] ?? "");
$selectedUserIds = array_values(array_filter(array_map("trim", (array)($_POST["userid"] ?? array())), "strlen"));
$recordedBy = trim((string)($_SESSION["USERID"] ?? ""));

$searchRequested = isset($_POST["searchstudent"]);
$registerRequested = isset($_POST["register_term"]);
$shouldLoadStudents = $searchClassId !== "" && $searchBatchId !== "" && ($searchRequested || $registerRequested || isset($_POST["search_classentryid"]) || isset($_POST["search_batchid2"]) || isset($_POST["keep_student_results"]));

if ($registerRequested) {
    if (count($selectedUserIds) === 0) {
        $flashHtml = gtr_alert("danger", "No Students Selected", "Select one or more students before saving the semester registration.") . $flashHtml;
    } elseif ($selectedClassId === "" || $selectedTerm === "" || $selectedBatchId === "" || $selectedAcademicYear === "") {
        $flashHtml = gtr_alert("danger", "Missing Registration Details", "Select the class, semester, batch, and academic year before saving.") . $flashHtml;
    } else {
        $successCount = 0;
        $duplicateNames = array();
        $failedEntries = array();

        $selectedClassIdEsc = mysqli_real_escape_string($con, $selectedClassId);
        $selectedTermEsc = mysqli_real_escape_string($con, $selectedTerm);
        $selectedBatchIdEsc = mysqli_real_escape_string($con, $selectedBatchId);
        $selectedAcademicYearEsc = mysqli_real_escape_string($con, $selectedAcademicYear);
        $resolvedYearSql = semester_registry_resolved_year_sql("tbltermregistry");

        foreach ($selectedUserIds as $studentId) {
            include("code.php");
            $termId = $code;
            $studentIdEsc = mysqli_real_escape_string($con, $studentId);

            $studentName = $studentId;
            $studentQuery = mysqli_query($con, "SELECT firstname, othernames, surname, userid FROM tblsystemuser WHERE userid='$studentIdEsc' LIMIT 1");
            if ($studentQuery && mysqli_num_rows($studentQuery) > 0) {
                $studentRow = mysqli_fetch_array($studentQuery, MYSQLI_ASSOC);
                $studentName = gtr_student_name($studentRow);
            }

            $checkSql = "SELECT termid FROM tbltermregistry
                WHERE userid='$studentIdEsc'
                  AND class_entryid='$selectedClassIdEsc'
                  AND termname='$selectedTermEsc'
                  AND batchid='$selectedBatchIdEsc'
                  AND $resolvedYearSql='$selectedAcademicYearEsc'
                LIMIT 1";
            $checkQuery = mysqli_query($con, $checkSql);

            if ($checkQuery && mysqli_num_rows($checkQuery) > 0) {
                $duplicateNames[] = $studentName;
                continue;
            }

            $termIdEsc = mysqli_real_escape_string($con, $termId);
            $recordedByEsc = mysqli_real_escape_string($con, $recordedBy);
            $insertSql = "INSERT INTO tbltermregistry(termid, userid, class_entryid, termname, batchid, academicyear, status, datetimeentry, recordedby)
                VALUES('$termIdEsc', '$studentIdEsc', '$selectedClassIdEsc', '$selectedTermEsc', '$selectedBatchIdEsc', '$selectedAcademicYearEsc', 'active', NOW(), '$recordedByEsc')";
            $insertQuery = mysqli_query($con, $insertSql);

            if ($insertQuery) {
                $successCount++;
            } else {
                $failedEntries[] = $studentName . ": " . mysqli_error($con);
            }
        }

        $messageParts = array();
        if ($successCount > 0) {
            $messageParts[] = gtr_alert("success", "Semester Saved", $successCount . " student" . ($successCount === 1 ? "" : "s") . " registered successfully.");
        }
        if (count($duplicateNames) > 0) {
            $duplicatePreview = implode(", ", array_slice($duplicateNames, 0, 4));
            if (count($duplicateNames) > 4) {
                $duplicatePreview .= " and more";
            }
            $messageParts[] = gtr_alert("warning", "Already Registered", count($duplicateNames) . " student" . (count($duplicateNames) === 1 ? "" : "s") . " already had this semester record. " . $duplicatePreview . ".");
        }
        if (count($failedEntries) > 0) {
            $messageParts[] = gtr_alert("danger", "Save Incomplete", "Some records could not be saved. " . $failedEntries[0]);
        }
        if (count($messageParts) > 0) {
            $flashHtml = implode("", $messageParts) . $flashHtml;
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

$searchClassLabel = $searchClassId !== "" && isset($classLabelMap[$searchClassId]) ? $classLabelMap[$searchClassId] : "Class not selected";
$searchBatchLabel = $searchBatchId !== "" && isset($batchLabelMap[$searchBatchId]) ? $batchLabelMap[$searchBatchId] : "Batch not selected";
$selectedClassLabel = $selectedClassId !== "" && isset($classLabelMap[$selectedClassId]) ? $classLabelMap[$selectedClassId] : "Class not selected";
$selectedBatchLabel = $selectedBatchId !== "" && isset($batchLabelMap[$selectedBatchId]) ? $batchLabelMap[$selectedBatchId] : "Batch not selected";

$studentRows = array();
$studentIdsLoaded = array();
$latestClassEntry = "";

if ($shouldLoadStudents) {
    $searchClassIdEsc = mysqli_real_escape_string($con, $searchClassId);
    $searchBatchIdEsc = mysqli_real_escape_string($con, $searchBatchId);
    $studentSql = "SELECT
            su.userid,
            su.firstname,
            su.surname,
            su.othernames,
            cl.classid,
            cl.class_entryid,
            cl.batchid,
            cl.datetimeentry,
            ce.class_name,
            bh.batch
        FROM tblsystemuser su
        INNER JOIN tblclass cl ON su.userid=cl.userid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
        LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
        WHERE cl.class_entryid='$searchClassIdEsc'
          AND cl.batchid='$searchBatchIdEsc'
          AND cl.status='active'
          AND su.systemtype='Student'
        ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC, su.userid ASC";
    $studentQuery = mysqli_query($con, $studentSql);
    if ($studentQuery) {
        while ($row = mysqli_fetch_array($studentQuery, MYSQLI_ASSOC)) {
            $studentRows[] = $row;
            $studentIdsLoaded[trim((string)$row["userid"])] = true;
            $recordedAt = trim((string)($row["datetimeentry"] ?? ""));
            if ($recordedAt !== "" && ($latestClassEntry === "" || strtotime($recordedAt) > strtotime($latestClassEntry))) {
                $latestClassEntry = $recordedAt;
            }
        }
    }
}

$loadedStudentCount = count($studentRows);
$initialSelectedCount = 0;
foreach ($selectedUserIds as $selectedUserId) {
    if (isset($studentIdsLoaded[$selectedUserId])) {
        $initialSelectedCount++;
    }
}

$existingRegistrationCount = 0;
if ($selectedClassId !== "" && $selectedBatchId !== "" && $selectedTerm !== "" && $selectedAcademicYear !== "") {
    $selectedClassIdEsc = mysqli_real_escape_string($con, $selectedClassId);
    $selectedBatchIdEsc = mysqli_real_escape_string($con, $selectedBatchId);
    $selectedTermEsc = mysqli_real_escape_string($con, $selectedTerm);
    $selectedAcademicYearEsc = mysqli_real_escape_string($con, $selectedAcademicYear);
    $resolvedYearSql = semester_registry_resolved_year_sql("tr");
    $countSql = "SELECT COUNT(*) AS total
        FROM tbltermregistry tr
        WHERE tr.class_entryid='$selectedClassIdEsc'
          AND tr.batchid='$selectedBatchIdEsc'
          AND tr.termname='$selectedTermEsc'
          AND $resolvedYearSql='$selectedAcademicYearEsc'";
    $countQuery = mysqli_query($con, $countSql);
    if ($countQuery && ($countRow = mysqli_fetch_array($countQuery, MYSQLI_ASSOC))) {
        $existingRegistrationCount = (int)($countRow["total"] ?? 0);
    }
}

$latestClassEntryLabel = $latestClassEntry !== "" ? gtr_format_datetime($latestClassEntry) : "No records loaded";
$resultSummary = !$shouldLoadStudents
    ? "Choose a class and batch to load active students for semester registration."
    : ($loadedStudentCount > 0
        ? "Showing active students in the selected class and batch. Choose the students you want to register for the semester."
        : "No active students were found in the selected class and batch.");
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/group-term-registry.css">
<script type="text/javascript" src="scripts/group-term-registry.js" defer></script>
</head>

<body class="body-style group-term-registry-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<main class="main-platform gtr-shell">
    <section class="gtr-hero">
        <div class="gtr-hero__copy">
            <span class="gtr-kicker"><i class="fa fa-clone"></i> Semester Registry</span>
            <h1>Group Semester Registration</h1>
            <p>Load a class list, select the students you want, and register their semester records from one page. The layout stays simple on mobile so you can work comfortably on smaller screens too.</p>
            <div class="gtr-hero__chips">
                <span class="gtr-chip"><i class="fa fa-users"></i> Active students only</span>
                <span class="gtr-chip"><i class="fa fa-calendar"></i> Academic year required</span>
                <span class="gtr-chip"><i class="fa fa-check-circle"></i> Bulk semester entry</span>
            </div>
        </div>

        <div class="gtr-hero__actions">
            <a href="term-registry.php" class="gtr-btn gtr-btn--ghost"><i class="fa fa-user-plus"></i> Single Student Entry</a>
            <a href="group-term-registry.php" class="gtr-btn gtr-btn--light"><i class="fa fa-refresh"></i> Reset Page</a>
        </div>

        <div class="gtr-stats">
            <article class="gtr-stat">
                <span>Students Loaded</span>
                <strong><?php echo (int)$loadedStudentCount; ?></strong>
                <small><?php echo $shouldLoadStudents ? "Students available in the current class and batch." : "Load a class list to begin."; ?></small>
            </article>
            <article class="gtr-stat">
                <span>Selected</span>
                <strong id="gtr-selected-count"><?php echo (int)$initialSelectedCount; ?></strong>
                <small>Students currently marked for semester registration.</small>
            </article>
            <article class="gtr-stat">
                <span>Current Scope</span>
                <strong><?php echo gtr_esc($shouldLoadStudents ? $searchClassLabel : "Pending"); ?></strong>
                <small><?php echo gtr_esc($shouldLoadStudents ? $searchBatchLabel : "Select class and batch."); ?></small>
            </article>
            <article class="gtr-stat">
                <span>Latest Class Entry</span>
                <strong><?php echo gtr_esc($shouldLoadStudents ? $latestClassEntryLabel : "Pending"); ?></strong>
                <small><?php echo $shouldLoadStudents ? "Most recent active class record loaded on this page." : "No student list loaded yet."; ?></small>
            </article>
        </div>
    </section>

    <?php if ($flashHtml !== "") { ?>
    <div class="gtr-message-stack"><?php echo $flashHtml; ?></div>
    <?php } ?>

    <section class="gtr-panel">
        <div class="gtr-panel__head">
            <div>
                <h2>Find Students</h2>
                <p>Choose the class and batch you want to work with, then load the active student list for that group.</p>
            </div>
            <div class="gtr-panel__tag"><i class="fa fa-filter"></i> Search Scope</div>
        </div>

        <form method="post" action="group-term-registry.php" class="gtr-filter-grid">
            <label for="classentryid">
                <span>Class</span>
                <select id="classentryid" name="classentryid" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classOptions as $classOption) { ?>
                    <option value="<?php echo gtr_esc($classOption["class_entryid"]); ?>"<?php echo $searchClassId === trim((string)$classOption["class_entryid"]) ? " selected" : ""; ?>>
                        <?php echo gtr_esc($classOption["class_name"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <label for="batchid2">
                <span>Batch</span>
                <select id="batchid2" name="batchid2" required>
                    <option value="">Select Batch</option>
                    <?php foreach ($batchOptions as $batchOption) { ?>
                    <option value="<?php echo gtr_esc($batchOption["batchid"]); ?>"<?php echo $searchBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                        <?php echo gtr_esc($batchOption["batch"]); ?><?php echo trim((string)$batchOption["status"]) !== "" ? " (" . gtr_esc($batchOption["status"]) . ")" : ""; ?>
                    </option>
                    <?php } ?>
                </select>
            </label>

            <div class="gtr-filter-actions">
                <button type="submit" name="searchstudent" class="gtr-btn gtr-btn--primary"><i class="fa fa-search"></i> Load Students</button>
                <a href="group-term-registry.php" class="gtr-btn gtr-btn--secondary"><i class="fa fa-times"></i> Clear</a>
            </div>
        </form>
    </section>

    <form method="post" action="group-term-registry.php" class="gtr-workspace">
        <input type="hidden" name="search_classentryid" value="<?php echo gtr_esc($searchClassId); ?>">
        <input type="hidden" name="search_batchid2" value="<?php echo gtr_esc($searchBatchId); ?>">
        <input type="hidden" name="keep_student_results" value="1">

        <section class="gtr-panel gtr-students-panel">
            <div class="gtr-panel__head gtr-panel__head--results">
                <div>
                    <h2>Student List</h2>
                    <p><?php echo gtr_esc($resultSummary); ?></p>
                </div>

                <div class="gtr-search-block">
                    <label for="gtr-student-search">Quick Search</label>
                    <div class="gtr-search-field">
                        <i class="fa fa-search"></i>
                        <input type="search" id="gtr-student-search" placeholder="Search by student name, ID, class or batch" <?php echo $loadedStudentCount === 0 ? "disabled" : ""; ?>>
                    </div>
                </div>
            </div>

            <?php if ($shouldLoadStudents) { ?>
            <div class="gtr-active-filters">
                <span class="gtr-filter-pill"><i class="fa fa-graduation-cap"></i> <?php echo gtr_esc($searchClassLabel); ?></span>
                <span class="gtr-filter-pill"><i class="fa fa-calendar"></i> <?php echo gtr_esc($searchBatchLabel); ?></span>
                <span class="gtr-filter-pill"><i class="fa fa-check-circle"></i> Active students only</span>
            </div>
            <?php } ?>

            <?php if (!$shouldLoadStudents) { ?>
            <div class="gtr-empty-state">
                <i class="fa fa-filter"></i>
                <h3>Select a class and batch to load students.</h3>
                <p>The student list will appear here once you search for an active class group.</p>
            </div>
            <?php } elseif ($loadedStudentCount === 0) { ?>
            <div class="gtr-empty-state">
                <i class="fa fa-users"></i>
                <h3>No active students were found for this selection.</h3>
                <p>Try another class or batch, or confirm that the students already have active class records.</p>
            </div>
            <?php } else { ?>
            <div class="gtr-student-toolbar">
                <div class="gtr-student-toolbar__meta">
                    <p><strong id="gtr-visible-count"><?php echo (int)$loadedStudentCount; ?></strong> of <?php echo (int)$loadedStudentCount; ?> students visible</p>
                    <p>Tap any student card to select or clear it.</p>
                </div>

                <div class="gtr-student-toolbar__actions">
                    <button type="button" class="gtr-btn gtr-btn--secondary" id="gtr-select-visible"><i class="fa fa-check-square-o"></i> Select Visible</button>
                    <button type="button" class="gtr-btn gtr-btn--secondary" id="gtr-clear-visible"><i class="fa fa-square-o"></i> Clear Visible</button>
                </div>
            </div>

            <div class="gtr-student-list" id="gtr-student-list">
                <?php foreach ($studentRows as $index => $studentRow) { ?>
                <?php
                    $studentId = trim((string)$studentRow["userid"]);
                    $studentName = gtr_student_name($studentRow);
                    $searchText = gtr_lower($studentName . " " . $studentId . " " . ($studentRow["class_name"] ?? "") . " " . ($studentRow["batch"] ?? ""));
                    $isChecked = in_array($studentId, $selectedUserIds, true);
                ?>
                <label class="gtr-student-card" data-student-card data-search-text="<?php echo gtr_esc($searchText); ?>">
                    <span class="gtr-student-card__check">
                        <input type="checkbox" name="userid[]" value="<?php echo gtr_esc($studentId); ?>"<?php echo $isChecked ? " checked" : ""; ?>>
                    </span>
                    <span class="gtr-student-card__content">
                        <span class="gtr-student-card__serial"><?php echo (int)($index + 1); ?></span>
                        <span class="gtr-student-card__name"><?php echo gtr_esc($studentName); ?></span>
                        <span class="gtr-student-card__meta"><i class="fa fa-id-card-o"></i> <?php echo gtr_esc($studentId); ?></span>
                        <span class="gtr-student-card__meta"><i class="fa fa-graduation-cap"></i> <?php echo gtr_esc(trim((string)($studentRow["class_name"] ?? $searchClassLabel))); ?></span>
                        <span class="gtr-student-card__meta"><i class="fa fa-calendar"></i> <?php echo gtr_esc(trim((string)($studentRow["batch"] ?? $searchBatchLabel))); ?></span>
                    </span>
                </label>
                <?php } ?>
            </div>

            <div class="gtr-empty-state gtr-empty-state--compact" id="gtr-no-results" hidden>
                <i class="fa fa-search"></i>
                <h3>No students match this search.</h3>
                <p>Clear the search box or change the filter text to see more students.</p>
            </div>
            <?php } ?>
        </section>

        <aside class="gtr-panel gtr-settings-panel">
            <div class="gtr-panel__head">
                <div>
                    <h2>Registration Details</h2>
                    <p>Set the semester information for the students you selected from the loaded list.</p>
                </div>
                <div class="gtr-panel__tag"><i class="fa fa-pencil-square-o"></i> Save Setup</div>
            </div>

            <div class="gtr-summary-card">
                <h3>Current Selection</h3>
                <ul class="gtr-summary-list">
                    <li><span>Loaded Class</span><strong><?php echo gtr_esc($shouldLoadStudents ? $searchClassLabel : "Pending"); ?></strong></li>
                    <li><span>Loaded Batch</span><strong><?php echo gtr_esc($shouldLoadStudents ? $searchBatchLabel : "Pending"); ?></strong></li>
                    <li><span>Students Loaded</span><strong><?php echo (int)$loadedStudentCount; ?></strong></li>
                    <li><span>Already Registered</span><strong><?php echo (int)$existingRegistrationCount; ?></strong></li>
                </ul>
            </div>

            <div class="gtr-form-grid">
                <label for="classid">
                    <span>Class</span>
                    <select id="classid" name="classid" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classOptions as $classOption) { ?>
                        <option value="<?php echo gtr_esc($classOption["class_entryid"]); ?>"<?php echo $selectedClassId === trim((string)$classOption["class_entryid"]) ? " selected" : ""; ?>>
                            <?php echo gtr_esc($classOption["class_name"]); ?>
                        </option>
                        <?php } ?>
                    </select>
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
                        <option value="<?php echo gtr_esc($yearOption); ?>"<?php echo $selectedAcademicYear === $yearOption ? " selected" : ""; ?>>
                            <?php echo gtr_esc($yearOption); ?>
                        </option>
                        <?php } ?>
                    </select>
                </label>

                <label for="batchid">
                    <span>Batch</span>
                    <select id="batchid" name="batchid" required>
                        <option value="">Select Batch</option>
                        <?php foreach ($batchOptions as $batchOption) { ?>
                        <option value="<?php echo gtr_esc($batchOption["batchid"]); ?>"<?php echo $selectedBatchId === trim((string)$batchOption["batchid"]) ? " selected" : ""; ?>>
                            <?php echo gtr_esc($batchOption["batch"]); ?>
                        </option>
                        <?php } ?>
                    </select>
                </label>
            </div>

            <div class="gtr-note">
                <i class="fa fa-info-circle"></i>
                <p>The class and batch default to the loaded student list so you can save without repeating the same setup.</p>
            </div>

            <div class="gtr-submit-bar">
                <button type="submit" name="register_term" class="gtr-btn gtr-btn--primary" <?php echo $loadedStudentCount === 0 ? "disabled" : ""; ?>><i class="fa fa-save"></i> Save Semester</button>
                <a href="term-registry.php" class="gtr-btn gtr-btn--secondary"><i class="fa fa-external-link"></i> Open Single Entry</a>
            </div>
        </aside>
    </form>
</main>
</body>
</html>

<?php
session_start();
include("dbstring.php");

function vasa_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function vasa_alert($type, $message){
    $class = "vasa-alert vasa-alert--info";
    if($type === "success"){
        $class = "vasa-alert vasa-alert--success";
    }elseif($type === "error"){
        $class = "vasa-alert vasa-alert--error";
    }elseif($type === "warning"){
        $class = "vasa-alert vasa-alert--warning";
    }
    return "<div class=\"$class\">".vasa_safe($message)."</div>";
}

function vasa_assignment_year_value($dateTimeValue){
    $dateTimeValue = trim((string)$dateTimeValue);
    if($dateTimeValue === ""){
        return date("Y");
    }
    $time = strtotime($dateTimeValue);
    return $time ? date("Y", $time) : date("Y");
}

function vasa_session_label($dateTimeValue, $batchLabel, $termValue){
    $yearValue = vasa_assignment_year_value($dateTimeValue);
    return trim($yearValue." Batch ".trim((string)$batchLabel)." Semester ".trim((string)$termValue));
}

function vasa_teacher_name($row){
    $parts = array();
    foreach(array("firstname", "othernames", "surname") as $field){
        $value = trim((string)(isset($row[$field]) ? $row[$field] : ""));
        if($value !== ""){
            $parts[] = $value;
        }
    }
    $label = trim(implode(" ", $parts));
    $teacherId = trim((string)(isset($row["teacherid"]) ? $row["teacherid"] : (isset($row["userid"]) ? $row["userid"] : "")));
    if($teacherId !== ""){
        $label .= ($label !== "" ? " " : "")."(".$teacherId.")";
    }
    return trim($label);
}

function vasa_filter_query($teacherId, $year, $term){
    $params = array();
    if(trim((string)$teacherId) !== ""){
        $params["teacherid"] = trim((string)$teacherId);
    }
    if(trim((string)$year) !== ""){
        $params["academicyear"] = trim((string)$year);
    }
    if(trim((string)$term) !== ""){
        $params["termname"] = trim((string)$term);
    }
    $query = http_build_query($params);
    return $query === "" ? "" : "?".$query;
}

function vasa_fetch_teacher_options($con){
    $options = array();
    $sql = "SELECT userid,firstname,othernames,surname
        FROM tblsystemuser
        WHERE systemtype='Teacher'
        ORDER BY firstname ASC, othernames ASC, surname ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row["teacherid"] = $row["userid"];
            $options[] = array(
                "userid" => $row["userid"],
                "label" => vasa_teacher_name($row)
            );
        }
    }
    return $options;
}

function vasa_fetch_year_options($con){
    $years = array();
    $sql = "SELECT DISTINCT DATE_FORMAT(datetimeentry, '%Y') AS academicyear
        FROM tblsubjectassignment
        WHERE datetimeentry IS NOT NULL
        ORDER BY academicyear DESC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $year = trim((string)$row["academicyear"]);
            if($year !== ""){
                $years[] = $year;
            }
        }
    }
    if(count($years) === 0){
        $years[] = date("Y");
    }
    return $years;
}

function vasa_fetch_term_options($con){
    $terms = array();
    $sql = "SELECT DISTINCT termname
        FROM tblsubjectassignment
        WHERE TRIM(COALESCE(termname,''))<>''
        ORDER BY CAST(termname AS UNSIGNED) ASC, termname ASC";
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $term = trim((string)$row["termname"]);
            if($term !== ""){
                $terms[] = $term;
            }
        }
    }
    return $terms;
}

function vasa_fetch_assignment_rows($con, $teacherId = "", $year = "", $term = ""){
    $where = array("su.systemtype='Teacher'");
    if(trim((string)$teacherId) !== ""){
        $teacherSafe = mysqli_real_escape_string($con, trim((string)$teacherId));
        $where[] = "sa.userid='$teacherSafe'";
    }
    if(trim((string)$year) !== ""){
        $yearSafe = mysqli_real_escape_string($con, trim((string)$year));
        $where[] = "DATE_FORMAT(sa.datetimeentry, '%Y')='$yearSafe'";
    }
    if(trim((string)$term) !== ""){
        $termSafe = mysqli_real_escape_string($con, trim((string)$term));
        $where[] = "sa.termname='$termSafe'";
    }
    $sql = "SELECT
            sa.assignmentid,
            sa.userid AS teacherid,
            sa.classid,
            sa.classificationid,
            sa.batchid,
            sa.termname,
            sa.datetimeentry,
            sa.status,
            DATE_FORMAT(sa.datetimeentry, '%Y') AS academicyear,
            su.firstname,
            su.othernames,
            su.surname,
            ce.class_name,
            sub.subjectid,
            sub.subject,
            COALESCE(b.batch, 'Batch not set') AS batch
        FROM tblsubjectassignment sa
        INNER JOIN tblsystemuser su ON su.userid=sa.userid
        INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        LEFT JOIN tblbatch b ON b.batchid=sa.batchid
        WHERE ".implode(" AND ", $where)."
        ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC, academicyear DESC, ce.class_name ASC, sa.termname ASC, sub.subject ASC";
    $rows = array();
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}

function vasa_group_assignment_rows($rows){
    $grouped = array();
    foreach($rows as $row){
        $teacherId = (string)$row["teacherid"];
        $classKey = trim((string)$row["classid"])."|".trim((string)$row["academicyear"])."|".trim((string)$row["termname"]);
        if(!isset($grouped[$teacherId])){
            $grouped[$teacherId] = array(
                "teacherid" => $teacherId,
                "teachername" => vasa_teacher_name($row),
                "rows_count" => 0,
                "subjects_count" => 0,
                "classes_count" => 0,
                "subject_keys" => array(),
                "class_keys" => array(),
                "classes" => array()
            );
        }
        $grouped[$teacherId]["rows_count"]++;
        $subjectKey = trim((string)$row["subjectid"])."|".trim((string)$row["classificationid"])."|".trim((string)$row["academicyear"])."|".trim((string)$row["termname"]);
        if(!isset($grouped[$teacherId]["subject_keys"][$subjectKey])){
            $grouped[$teacherId]["subject_keys"][$subjectKey] = true;
            $grouped[$teacherId]["subjects_count"]++;
        }
        if(!isset($grouped[$teacherId]["class_keys"][$classKey])){
            $grouped[$teacherId]["class_keys"][$classKey] = true;
            $grouped[$teacherId]["classes_count"]++;
            $grouped[$teacherId]["classes"][$classKey] = array(
                "class_name" => trim((string)$row["class_name"]) !== "" ? trim((string)$row["class_name"]) : "Class not set",
                "session_label" => vasa_session_label($row["datetimeentry"], $row["batch"], $row["termname"]),
                "items" => array()
            );
        }
        $grouped[$teacherId]["classes"][$classKey]["items"][] = $row;
    }
    return $grouped;
}

function vasa_output_pdf($con, $teacherId, $year, $term, $singleTeacherOnly){
    include("config.php");
    include("company.php");
    require('fpdf181/fpdf.php');

    $rows = vasa_fetch_assignment_rows($con, $teacherId, $year, $term);
    $grouped = vasa_group_assignment_rows($rows);
    $teacherLabel = "All Teachers";
    if($singleTeacherOnly && trim((string)$teacherId) !== ""){
        foreach(vasa_fetch_teacher_options($con) as $teacherOption){
            if((string)$teacherOption["userid"] === (string)$teacherId){
                $teacherLabel = $teacherOption["label"];
                break;
            }
        }
    }
    $scopeYear = trim((string)$year) !== "" ? trim((string)$year) : "All Years";
    $scopeTerm = trim((string)$term) !== "" ? "Semester ".trim((string)$term) : "All Semesters";

    $pdf = new FPDF();
    $pdf->AddPage();
    $widthCell = array(12, 24, 54, 84);
    $reportWidth = array_sum($widthCell);
    $pdf->SetFont('Arial','B',18);
    $pdf->Image('images/logo.png', 95, 3, 22);
    $pdf->Ln(20);
    $lineGap = 7;
    $pdf->SetFillColor(255,255,255);
    $pdf->Cell($reportWidth,10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
    $pdf->Ln($lineGap);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell($reportWidth,10,$_Address.", ".$_Location,0,0,'C',true);
    $pdf->Ln($lineGap);
    $pdf->Cell($reportWidth,10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
    $pdf->Ln($lineGap + 3);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell($reportWidth,10,"LIST OF SUBJECTS ASSIGNED",0,0,'C',true);
    $pdf->Ln($lineGap);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell($reportWidth,8,"Teacher Filter: ".$teacherLabel." | Academic Year: ".$scopeYear." | ".$scopeTerm,0,0,'C',true);
    $pdf->Ln(12);

    if(count($grouped) === 0){
        $pdf->SetFont('Arial','I',10);
        $pdf->Cell($reportWidth,8,"No subject assignments matched the selected filters.",0,0,'L',true);
        $pdf->Output();
        return;
    }

    foreach($grouped as $teacherGroup){
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell($reportWidth,9,$teacherGroup["teachername"],1,0,'L',true);
        $pdf->Ln(9);
        foreach($teacherGroup["classes"] as $classGroup){
            $pdf->SetFont('Arial','B',9);
            $pdf->Cell($reportWidth,8,$classGroup["class_name"]." - ".$classGroup["session_label"],1,0,'L',true);
            $pdf->Ln(8);
            $pdf->Cell($widthCell[0],8,'#',1,0,'C',true);
            $pdf->Cell($widthCell[1],8,'SUBJECT ID',1,0,'C',true);
            $pdf->Cell($widthCell[2],8,'SUBJECT',1,0,'C',true);
            $pdf->Cell($widthCell[3],8,'ENTRY / STATUS',1,0,'C',true);
            $pdf->Ln(8);
            $pdf->SetFont('Arial','',9);
            $serial = 0;
            foreach($classGroup["items"] as $item){
                $serial++;
                $entryText = date("d M Y", strtotime((string)$item["datetimeentry"]))." | ".strtoupper((string)$item["status"]);
                $pdf->Cell($widthCell[0],8,$serial,1,0,'C',true);
                $pdf->Cell($widthCell[1],8,(string)$item["subjectid"],1,0,'L',true);
                $pdf->Cell($widthCell[2],8,substr((string)$item["subject"], 0, 32),1,0,'L',true);
                $pdf->Cell($widthCell[3],8,$entryText,1,0,'L',true);
                $pdf->Ln(8);
            }
            $pdf->Ln(2);
        }
        $pdf->Ln(2);
    }
    $pdf->Output();
}

$flashMessage = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

$selectedTeacherId = "";
if(isset($_POST["teacherid"])){
    $selectedTeacherId = trim((string)$_POST["teacherid"]);
}elseif(isset($_GET["teacherid"])){
    $selectedTeacherId = trim((string)$_GET["teacherid"]);
}
$selectedYear = "";
if(isset($_POST["academicyear"])){
    $selectedYear = trim((string)$_POST["academicyear"]);
}elseif(isset($_GET["academicyear"])){
    $selectedYear = trim((string)$_GET["academicyear"]);
}
$selectedTerm = "";
if(isset($_POST["termname"])){
    $selectedTerm = trim((string)$_POST["termname"]);
}elseif(isset($_GET["termname"])){
    $selectedTerm = trim((string)$_GET["termname"]);
}

if(isset($_GET["delete_subject"])){
    $assignmentId = trim((string)$_GET["delete_subject"]);
    if($assignmentId !== ""){
        $assignmentEsc = mysqli_real_escape_string($con, $assignmentId);
        $delete = mysqli_query($con, "DELETE FROM tblsubjectassignment WHERE assignmentid='$assignmentEsc' LIMIT 1");
        if($delete){
            $_SESSION["Message"] = vasa_alert("success", "Subject assignment deleted successfully.");
        }else{
            $_SESSION["Message"] = vasa_alert("error", "Subject assignment failed to delete. Error: ".mysqli_error($con));
        }
    }
    header("location:view-all-subject-assigned.php".vasa_filter_query($selectedTeacherId, $selectedYear, $selectedTerm));
    exit();
}

if(isset($_POST["print_report"])){
    if($selectedTeacherId === ""){
        $_SESSION["Message"] = vasa_alert("warning", "Select a teacher first before printing a teacher-specific report.");
        header("location:view-all-subject-assigned.php".vasa_filter_query($selectedTeacherId, $selectedYear, $selectedTerm));
        exit();
    }
    vasa_output_pdf($con, $selectedTeacherId, $selectedYear, $selectedTerm, true);
    exit();
}

if(isset($_POST["print_all_reports"])){
    vasa_output_pdf($con, $selectedTeacherId, $selectedYear, $selectedTerm, false);
    exit();
}

$teacherOptions = vasa_fetch_teacher_options($con);
$yearOptions = vasa_fetch_year_options($con);
$termOptions = vasa_fetch_term_options($con);
$assignmentRows = vasa_fetch_assignment_rows($con, $selectedTeacherId, $selectedYear, $selectedTerm);
$groupedAssignments = vasa_group_assignment_rows($assignmentRows);

$subjectCount = 0;
$classCount = 0;
$classKeys = array();
$subjectKeys = array();
foreach($assignmentRows as $row){
    $classKey = trim((string)$row["classid"])."|".trim((string)$row["academicyear"])."|".trim((string)$row["termname"]);
    if(!isset($classKeys[$classKey])){
        $classKeys[$classKey] = true;
        $classCount++;
    }
    $subjectKey = trim((string)$row["subjectid"])."|".trim((string)$row["classificationid"])."|".trim((string)$row["academicyear"])."|".trim((string)$row["termname"])."|".trim((string)$row["teacherid"]);
    if(!isset($subjectKeys[$subjectKey])){
        $subjectKeys[$subjectKey] = true;
        $subjectCount++;
    }
}

$selectedTeacherLabel = "All Teachers";
foreach($teacherOptions as $teacherOption){
    if((string)$teacherOption["userid"] === (string)$selectedTeacherId){
        $selectedTeacherLabel = $teacherOption["label"];
        break;
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/view-subject-assigned.css">
</head>
<body class="vasa-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="vasa-shell">
    <?php if($flashMessage !== ""){ ?><div class="vasa-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="vasa-hero">
        <div>
            <span class="vasa-kicker"><i class="fa fa-book"></i> Subject Assignment Directory</span>
            <h1>Filter assigned subjects by teacher and academic year.</h1>
            <p>The page now keeps the teacher view, the year filter, and the print actions aligned, and the list reads much better on phones as well as larger screens.</p>
            <div class="vasa-pills">
                <span>Teacher filter</span>
                <span>Academic year filter</span>
                <span>Mobile friendly cards</span>
            </div>
        </div>
        <aside class="vasa-hero-card">
            <h2><?php echo vasa_safe($selectedTeacherLabel); ?></h2>
            <p><?php echo vasa_safe(($selectedYear !== "" ? $selectedYear." Academic Year" : "All Academic Years")." | ".($selectedTerm !== "" ? "Semester ".$selectedTerm : "All Semesters")); ?></p>
            <div class="vasa-metrics">
                <article><span>Teachers Shown</span><strong><?php echo number_format(count($groupedAssignments)); ?></strong></article>
                <article><span>Subjects</span><strong><?php echo number_format($subjectCount); ?></strong></article>
                <article><span>Class Sessions</span><strong><?php echo number_format($classCount); ?></strong></article>
                <article><span>Total Rows</span><strong><?php echo number_format(count($assignmentRows)); ?></strong></article>
            </div>
            <small>Choose a teacher to focus on that one person’s assigned subjects instantly.</small>
        </aside>
    </section>

    <section class="vasa-panel">
        <div class="vasa-panel-head">
            <div>
                <span class="vasa-kicker vasa-kicker--dark">Filters</span>
                <h2>Teacher and Year Scope</h2>
                <p>Pick one teacher, one year, or leave them open to see the broader subject assignment picture.</p>
            </div>
        </div>

        <form method="get" action="view-all-subject-assigned.php" class="vasa-filter-form">
            <div class="vasa-field">
                <label for="teacherid">Teacher</label>
                <select id="teacherid" name="teacherid">
                    <option value="">All Teachers</option>
                    <?php foreach($teacherOptions as $teacherOption){ ?>
                    <option value="<?php echo vasa_safe($teacherOption["userid"]); ?>"<?php echo ((string)$selectedTeacherId === (string)$teacherOption["userid"] ? " selected" : ""); ?>><?php echo vasa_safe($teacherOption["label"]); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="vasa-field">
                <label for="academicyear">Academic Year</label>
                <select id="academicyear" name="academicyear">
                    <option value="">All Years</option>
                    <?php foreach($yearOptions as $yearOption){ ?>
                    <option value="<?php echo vasa_safe($yearOption); ?>"<?php echo ((string)$selectedYear === (string)$yearOption ? " selected" : ""); ?>><?php echo vasa_safe($yearOption); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="vasa-field">
                <label for="termname">Semester</label>
                <select id="termname" name="termname">
                    <option value="">All Semesters</option>
                    <?php foreach($termOptions as $termOption){ ?>
                    <option value="<?php echo vasa_safe($termOption); ?>"<?php echo ((string)$selectedTerm === (string)$termOption ? " selected" : ""); ?>><?php echo vasa_safe("Semester ".$termOption); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="vasa-filter-actions">
                <button type="submit" class="vasa-btn vasa-btn--primary"><i class="fa fa-filter"></i> Apply Filter</button>
                <a href="view-all-subject-assigned.php" class="vasa-btn vasa-btn--ghost"><i class="fa fa-refresh"></i> Reset</a>
            </div>
        </form>

        <div class="vasa-print-row">
            <form method="post" action="view-all-subject-assigned.php">
                <input type="hidden" name="teacherid" value="<?php echo vasa_safe($selectedTeacherId); ?>">
                <input type="hidden" name="academicyear" value="<?php echo vasa_safe($selectedYear); ?>">
                <input type="hidden" name="termname" value="<?php echo vasa_safe($selectedTerm); ?>">
                <button type="submit" name="print_report" class="vasa-btn vasa-btn--dark"><i class="fa fa-print"></i> Print Selected Teacher</button>
            </form>
            <form method="post" action="view-all-subject-assigned.php">
                <input type="hidden" name="teacherid" value="<?php echo vasa_safe($selectedTeacherId); ?>">
                <input type="hidden" name="academicyear" value="<?php echo vasa_safe($selectedYear); ?>">
                <input type="hidden" name="termname" value="<?php echo vasa_safe($selectedTerm); ?>">
                <button type="submit" name="print_all_reports" class="vasa-btn vasa-btn--ghost"><i class="fa fa-print"></i> Print Visible List</button>
            </form>
        </div>
    </section>

    <?php if(count($groupedAssignments) === 0){ ?>
    <section class="vasa-panel">
        <div class="vasa-empty">
            <h2>No subject assignments matched these filters</h2>
            <p>Try another teacher or academic year, or reset the filters to view the full list again.</p>
        </div>
    </section>
    <?php } else { ?>
    <div class="vasa-groups">
        <?php foreach($groupedAssignments as $teacherGroup){ ?>
        <section class="vasa-panel">
            <div class="vasa-teacher-head">
                <div>
                    <span class="vasa-kicker vasa-kicker--dark">Teacher</span>
                    <h2><?php echo vasa_safe($teacherGroup["teachername"]); ?></h2>
                </div>
                <div class="vasa-teacher-metrics">
                    <span><?php echo number_format((int)$teacherGroup["subjects_count"]); ?> subjects</span>
                    <span><?php echo number_format((int)$teacherGroup["classes_count"]); ?> class sessions</span>
                    <span><?php echo number_format((int)$teacherGroup["rows_count"]); ?> records</span>
                </div>
            </div>

            <div class="vasa-class-list">
                <?php foreach($teacherGroup["classes"] as $classGroup){ ?>
                <article class="vasa-class-card">
                    <div class="vasa-class-card__head">
                        <div>
                            <h3><?php echo vasa_safe($classGroup["class_name"]); ?></h3>
                            <p><?php echo vasa_safe($classGroup["session_label"]); ?></p>
                        </div>
                        <span><?php echo number_format(count($classGroup["items"])); ?> subject<?php echo (count($classGroup["items"]) === 1 ? "" : "s"); ?></span>
                    </div>

                    <div class="vasa-assignment-grid">
                        <?php foreach($classGroup["items"] as $item){ ?>
                        <article class="vasa-assignment-card">
                            <div class="vasa-assignment-card__meta">
                                <span class="vasa-chip"><?php echo vasa_safe((string)$item["subjectid"]); ?></span>
                                <span class="vasa-chip vasa-chip--status"><?php echo vasa_safe(ucfirst((string)$item["status"])); ?></span>
                            </div>
                            <h4><?php echo vasa_safe((string)$item["subject"]); ?></h4>
                            <div class="vasa-assignment-card__info">
                                <span><strong>Batch:</strong> <?php echo vasa_safe((string)$item["batch"]); ?></span>
                                <span><strong>Semester:</strong> <?php echo vasa_safe("Semester ".(string)$item["termname"]); ?></span>
                                <span><strong>Entry Date:</strong> <?php echo vasa_safe(date("d M Y, g:i a", strtotime((string)$item["datetimeentry"]))); ?></span>
                            </div>
                            <div class="vasa-assignment-card__actions">
                                <a href="view-all-subject-assigned.php?delete_subject=<?php echo urlencode((string)$item["assignmentid"]); ?>&teacherid=<?php echo urlencode($selectedTeacherId); ?>&academicyear=<?php echo urlencode($selectedYear); ?>&termname=<?php echo urlencode($selectedTerm); ?>" onclick="return confirm('Do you want to delete <?php echo vasa_safe(addslashes((string)$item["subject"])); ?>?');" class="vasa-delete-link"><i class="fa fa-trash-o"></i> Delete</a>
                            </div>
                        </article>
                        <?php } ?>
                    </div>
                </article>
                <?php } ?>
            </div>
        </section>
        <?php } ?>
    </div>
    <?php } ?>
</main>
</body>
</html>

<?php
session_start();
include("dbstring.php");

if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "administrator" && $_SESSION['SYSTEMTYPE'] === "super_user"){
    header("location:view-all-subject-assigned.php");
    exit();
}

if(!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'], $_SESSION['USERID']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Teacher")){
    header("location:index.php");
    exit();
}

function vts_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function vts_alert($type, $message){
    $class = "vasa-alert vasa-alert--info";
    if($type === "success"){
        $class = "vasa-alert vasa-alert--success";
    }elseif($type === "error"){
        $class = "vasa-alert vasa-alert--error";
    }elseif($type === "warning"){
        $class = "vasa-alert vasa-alert--warning";
    }
    return "<div class=\"$class\">".vts_safe($message)."</div>";
}

function vts_assignment_year_value($dateTimeValue){
    $dateTimeValue = trim((string)$dateTimeValue);
    if($dateTimeValue === ""){
        return date("Y");
    }
    $time = strtotime($dateTimeValue);
    return $time ? date("Y", $time) : date("Y");
}

function vts_session_label($dateTimeValue, $batchLabel, $termValue){
    $yearValue = vts_assignment_year_value($dateTimeValue);
    return trim($yearValue." Batch ".trim((string)$batchLabel)." Semester ".trim((string)$termValue));
}

function vts_teacher_name($con, $teacherId){
    $teacherEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $result = mysqli_query($con, "SELECT firstname,othernames,surname,userid FROM tblsystemuser WHERE userid='$teacherEsc' LIMIT 1");
    if($result && $row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
        $parts = array();
        foreach(array("firstname", "othernames", "surname") as $field){
            $value = trim((string)$row[$field]);
            if($value !== ""){
                $parts[] = $value;
            }
        }
        $label = trim(implode(" ", $parts));
        return trim($label." (".$row["userid"].")");
    }
    return trim((string)$teacherId);
}

function vts_filter_query($year, $term){
    $params = array();
    if(trim((string)$year) !== ""){
        $params["academicyear"] = trim((string)$year);
    }
    if(trim((string)$term) !== ""){
        $params["termname"] = trim((string)$term);
    }
    $query = http_build_query($params);
    return $query === "" ? "" : "?".$query;
}

function vts_fetch_year_options($con, $teacherId){
    $years = array();
    $teacherEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $sql = "SELECT DISTINCT DATE_FORMAT(datetimeentry, '%Y') AS academicyear
        FROM tblsubjectassignment
        WHERE userid='$teacherEsc' AND status='active' AND datetimeentry IS NOT NULL
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

function vts_fetch_term_options($con, $teacherId){
    $terms = array();
    $teacherEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $sql = "SELECT DISTINCT termname
        FROM tblsubjectassignment
        WHERE userid='$teacherEsc' AND status='active' AND TRIM(COALESCE(termname,''))<>''
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

function vts_fetch_assignment_rows($con, $teacherId, $year = "", $term = ""){
    $teacherEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $where = array("sa.userid='$teacherEsc'", "sa.status='active'");
    if(trim((string)$year) !== ""){
        $yearEsc = mysqli_real_escape_string($con, trim((string)$year));
        $where[] = "DATE_FORMAT(sa.datetimeentry, '%Y')='$yearEsc'";
    }
    if(trim((string)$term) !== ""){
        $termEsc = mysqli_real_escape_string($con, trim((string)$term));
        $where[] = "sa.termname='$termEsc'";
    }
    $sql = "SELECT
            sa.assignmentid,
            sa.classid,
            sa.classificationid,
            sa.batchid,
            sa.termname,
            sa.datetimeentry,
            sa.status,
            DATE_FORMAT(sa.datetimeentry, '%Y') AS academicyear,
            ce.class_name,
            sub.subjectid,
            sub.subject,
            COALESCE(b.batch, 'Batch not set') AS batch
        FROM tblsubjectassignment sa
        INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        LEFT JOIN tblbatch b ON b.batchid=sa.batchid
        WHERE ".implode(" AND ", $where)."
        ORDER BY academicyear DESC, ce.class_name ASC, sa.termname ASC, sub.subject ASC";
    $rows = array();
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}

function vts_group_assignment_rows($rows){
    $grouped = array();
    foreach($rows as $row){
        $classKey = trim((string)$row["classid"])."|".trim((string)$row["academicyear"])."|".trim((string)$row["termname"]);
        if(!isset($grouped[$classKey])){
            $grouped[$classKey] = array(
                "class_name" => trim((string)$row["class_name"]) !== "" ? trim((string)$row["class_name"]) : "Class not set",
                "session_label" => vts_session_label($row["datetimeentry"], $row["batch"], $row["termname"]),
                "items" => array()
            );
        }
        $grouped[$classKey]["items"][] = $row;
    }
    return $grouped;
}

function vts_output_pdf($con, $teacherId, $year, $term){
    include("config.php");
    include("company.php");
    require('fpdf181/fpdf.php');

    $rows = vts_fetch_assignment_rows($con, $teacherId, $year, $term);
    $grouped = vts_group_assignment_rows($rows);
    $teacherLabel = vts_teacher_name($con, $teacherId);
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
    $pdf->Cell($reportWidth,8,"Teacher: ".$teacherLabel." | Academic Year: ".$scopeYear." | ".$scopeTerm,0,0,'C',true);
    $pdf->Ln(12);

    if(count($grouped) === 0){
        $pdf->SetFont('Arial','I',10);
        $pdf->Cell($reportWidth,8,"No subject assignments matched the selected filters.",0,0,'L',true);
        $pdf->Output();
        return;
    }

    foreach($grouped as $classGroup){
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell($reportWidth,9,$classGroup["class_name"]." - ".$classGroup["session_label"],1,0,'L',true);
        $pdf->Ln(9);
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
    $pdf->Output();
}

$teacherId = trim((string)$_SESSION['USERID']);
$teacherName = vts_teacher_name($con, $teacherId);
$flashMessage = isset($_SESSION["Message"]) ? (string)$_SESSION["Message"] : "";
$_SESSION["Message"] = "";

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

if(isset($_POST["print_report"])){
    vts_output_pdf($con, $teacherId, $selectedYear, $selectedTerm);
    exit();
}

$yearOptions = vts_fetch_year_options($con, $teacherId);
$termOptions = vts_fetch_term_options($con, $teacherId);
$assignmentRows = vts_fetch_assignment_rows($con, $teacherId, $selectedYear, $selectedTerm);
$groupedAssignments = vts_group_assignment_rows($assignmentRows);

$subjectCount = 0;
$classCount = count($groupedAssignments);
$subjectKeys = array();
foreach($assignmentRows as $row){
    $subjectKey = trim((string)$row["subjectid"])."|".trim((string)$row["classificationid"])."|".trim((string)$row["academicyear"])."|".trim((string)$row["termname"]);
    if(!isset($subjectKeys[$subjectKey])){
        $subjectKeys[$subjectKey] = true;
        $subjectCount++;
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
            <span class="vasa-kicker"><i class="fa fa-search"></i> Teacher Subject View</span>
            <h1>Review your assigned subjects by year and semester.</h1>
            <p>Your full subject list now stays much easier to scan. You can narrow it by academic year and semester, then print exactly that filtered view when you need it.</p>
            <div class="vasa-pills">
                <span>Academic year filter</span>
                <span>Semester filter</span>
                <span>Mobile friendly cards</span>
            </div>
        </div>
        <aside class="vasa-hero-card">
            <h2><?php echo vts_safe($teacherName); ?></h2>
            <p><?php echo vts_safe(($selectedYear !== "" ? $selectedYear." Academic Year" : "All Academic Years")." | ".($selectedTerm !== "" ? "Semester ".$selectedTerm : "All Semesters")); ?></p>
            <div class="vasa-metrics">
                <article><span>Subjects</span><strong><?php echo number_format($subjectCount); ?></strong></article>
                <article><span>Class Sessions</span><strong><?php echo number_format($classCount); ?></strong></article>
                <article><span>Total Rows</span><strong><?php echo number_format(count($assignmentRows)); ?></strong></article>
                <article><span>Status</span><strong>Active</strong></article>
            </div>
            <small>The list below belongs only to your teacher account.</small>
        </aside>
    </section>

    <section class="vasa-panel">
        <div class="vasa-panel-head">
            <div>
                <span class="vasa-kicker vasa-kicker--dark">Filters</span>
                <h2>Year and Semester Scope</h2>
                <p>Focus on the exact set of subjects you need for the current or a previous session.</p>
            </div>
        </div>

        <form method="get" action="view-teacher-subject.php" class="vasa-filter-form" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
            <div class="vasa-field">
                <label for="academicyear">Academic Year</label>
                <select id="academicyear" name="academicyear">
                    <option value="">All Years</option>
                    <?php foreach($yearOptions as $yearOption){ ?>
                    <option value="<?php echo vts_safe($yearOption); ?>"<?php echo ((string)$selectedYear === (string)$yearOption ? " selected" : ""); ?>><?php echo vts_safe($yearOption); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="vasa-field">
                <label for="termname">Semester</label>
                <select id="termname" name="termname">
                    <option value="">All Semesters</option>
                    <?php foreach($termOptions as $termOption){ ?>
                    <option value="<?php echo vts_safe($termOption); ?>"<?php echo ((string)$selectedTerm === (string)$termOption ? " selected" : ""); ?>><?php echo vts_safe("Semester ".$termOption); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="vasa-filter-actions">
                <button type="submit" class="vasa-btn vasa-btn--primary"><i class="fa fa-filter"></i> Apply Filter</button>
                <a href="view-teacher-subject.php" class="vasa-btn vasa-btn--ghost"><i class="fa fa-refresh"></i> Reset</a>
            </div>
        </form>

        <div class="vasa-print-row">
            <form method="post" action="view-teacher-subject.php">
                <input type="hidden" name="academicyear" value="<?php echo vts_safe($selectedYear); ?>">
                <input type="hidden" name="termname" value="<?php echo vts_safe($selectedTerm); ?>">
                <button type="submit" name="print_report" class="vasa-btn vasa-btn--dark"><i class="fa fa-print"></i> Print Visible Subject List</button>
            </form>
        </div>
    </section>

    <?php if(count($groupedAssignments) === 0){ ?>
    <section class="vasa-panel">
        <div class="vasa-empty">
            <h2>No subjects matched these filters</h2>
            <p>Try another academic year or semester, or reset the filters to view your full active list again.</p>
        </div>
    </section>
    <?php } else { ?>
    <div class="vasa-groups">
        <section class="vasa-panel">
            <div class="vasa-teacher-head">
                <div>
                    <span class="vasa-kicker vasa-kicker--dark">Assigned Subjects</span>
                    <h2><?php echo vts_safe($teacherName); ?></h2>
                </div>
                <div class="vasa-teacher-metrics">
                    <span><?php echo number_format($subjectCount); ?> subjects</span>
                    <span><?php echo number_format($classCount); ?> class sessions</span>
                </div>
            </div>

            <div class="vasa-class-list">
                <?php foreach($groupedAssignments as $classGroup){ ?>
                <article class="vasa-class-card">
                    <div class="vasa-class-card__head">
                        <div>
                            <h3><?php echo vts_safe($classGroup["class_name"]); ?></h3>
                            <p><?php echo vts_safe($classGroup["session_label"]); ?></p>
                        </div>
                        <span><?php echo number_format(count($classGroup["items"])); ?> subject<?php echo (count($classGroup["items"]) === 1 ? "" : "s"); ?></span>
                    </div>

                    <div class="vasa-assignment-grid">
                        <?php foreach($classGroup["items"] as $item){ ?>
                        <article class="vasa-assignment-card">
                            <div class="vasa-assignment-card__meta">
                                <span class="vasa-chip"><?php echo vts_safe((string)$item["subjectid"]); ?></span>
                                <span class="vasa-chip vasa-chip--status"><?php echo vts_safe(ucfirst((string)$item["status"])); ?></span>
                            </div>
                            <h4><?php echo vts_safe((string)$item["subject"]); ?></h4>
                            <div class="vasa-assignment-card__info">
                                <span><strong>Batch:</strong> <?php echo vts_safe((string)$item["batch"]); ?></span>
                                <span><strong>Semester:</strong> <?php echo vts_safe("Semester ".(string)$item["termname"]); ?></span>
                                <span><strong>Entry Date:</strong> <?php echo vts_safe(date("d M Y, g:i a", strtotime((string)$item["datetimeentry"]))); ?></span>
                            </div>
                        </article>
                        <?php } ?>
                    </div>
                </article>
                <?php } ?>
            </div>
        </section>
    </div>
    <?php } ?>
</main>
</body>
</html>

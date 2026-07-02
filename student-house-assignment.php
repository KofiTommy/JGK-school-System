<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_can_manage_module($con, 'house_management')){
    header("location:".house_master_landing_page());
    exit();
}

if(!function_exists('sha_esc')){
function sha_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('sha_alert')){
function sha_alert($type, $message){
    $class = "sha-alert";
    if($type === "success"){
        $class .= " sha-alert--success";
    }elseif($type === "warning"){
        $class .= " sha-alert--warning";
    }else{
        $class .= " sha-alert--error";
    }
    return "<div class=\"".$class."\">".sha_esc($message)."</div>";
}
}

if(!function_exists('sha_flash_set')){
function sha_flash_set($message){
    $_SESSION["STUDENT_HOUSE_ASSIGNMENT_MESSAGE"] = (string)$message;
}
}

if(!function_exists('sha_flash_take')){
function sha_flash_take(){
    if(!isset($_SESSION["STUDENT_HOUSE_ASSIGNMENT_MESSAGE"])){
        return "";
    }
    $message = (string)$_SESSION["STUDENT_HOUSE_ASSIGNMENT_MESSAGE"];
    unset($_SESSION["STUDENT_HOUSE_ASSIGNMENT_MESSAGE"]);
    return $message;
}
}

if(!function_exists('sha_request_value')){
function sha_request_value($key){
    if(isset($_POST[$key])){
        return trim((string)$_POST[$key]);
    }
    if(isset($_GET[$key])){
        return trim((string)$_GET[$key]);
    }
    return "";
}
}

if(!function_exists('sha_url')){
function sha_url($params = array(), $hash = ""){
    $clean = array();
    foreach((array)$params as $key => $value){
        $value = trim((string)$value);
        if($value !== ""){
            $clean[$key] = $value;
        }
    }
    $query = http_build_query($clean);
    return "student-house-assignment.php".($query !== "" ? "?".$query : "").$hash;
}
}

@$_FilterClassId = sha_request_value('filter_classid');
@$_FilterBatchId = sha_request_value('filter_batchid');
@$_FilterSearch = sha_request_value('filter_search');
$_RedirectContext = array(
    "filter_classid" => $_FilterClassId,
    "filter_batchid" => $_FilterBatchId,
    "filter_search" => $_FilterSearch
);

if(isset($_POST['save_student_house'])){
    @$_UserId = trim((string)($_POST['userid'] ?? ''));
    @$_HouseId = trim((string)($_POST['houseid'] ?? ''));
    if(!$_UserId || !$_HouseId){
        sha_flash_set(sha_alert("error", "Please select both student and house."));
    }else{
        if(assign_student_to_house($con, $_UserId, $_HouseId, $_SESSION['USERID'])){
            sha_flash_set(sha_alert("success", "Student assigned to house successfully."));
        }else{
            sha_flash_set(sha_alert("error", "Failed to assign student: ".mysqli_error($con)));
        }
    }
    header("location:".sha_url($_RedirectContext, "#single-assignment"));
    exit();
}

if(isset($_POST['bulk_assign_students'])){
    @$_BulkHouseId = trim((string)($_POST['bulk_houseid'] ?? ''));
    @$_StudentIds = isset($_POST['studentids']) && is_array($_POST['studentids']) ? $_POST['studentids'] : array();
    if(!$_BulkHouseId){
        sha_flash_set(sha_alert("error", "Please select a house for bulk assignment."));
    }elseif(!is_array($_StudentIds) || count($_StudentIds) === 0){
        sha_flash_set(sha_alert("error", "Select at least one student for bulk assignment."));
    }else{
        $_Success = 0;
        $_Failed = 0;
        $_Processed = array();
        foreach($_StudentIds as $_Sid){
            $_Sid = trim((string)$_Sid);
            if($_Sid === "" || isset($_Processed[$_Sid])){
                continue;
            }
            $_Processed[$_Sid] = 1;
            if(assign_student_to_house($con, $_Sid, $_BulkHouseId, $_SESSION['USERID'])){
                $_Success++;
            }else{
                $_Failed++;
            }
        }
        if($_Success > 0 && $_Failed === 0){
            sha_flash_set(sha_alert("success", "Bulk assignment complete. Success: $_Success, Failed: $_Failed."));
        }elseif($_Success > 0){
            sha_flash_set(sha_alert("warning", "Bulk assignment completed with some issues. Success: $_Success, Failed: $_Failed."));
        }else{
            sha_flash_set(sha_alert("error", "Bulk assignment failed. No selected student could be assigned."));
        }
    }
    header("location:".sha_url($_RedirectContext, "#bulk-assignment"));
    exit();
}

if(isset($_POST['remove_student_house'])){
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)($_POST['assignmentid'] ?? '')));
    $_SQL_R = mysqli_query($con, "UPDATE tblstudenthouse SET status='inactive' WHERE assignmentid='$_AssignmentId' AND status='active'");
    if($_AssignmentId === ""){
        sha_flash_set(sha_alert("error", "Select a valid assignment to remove."));
    }elseif($_SQL_R){
        if(mysqli_affected_rows($con) > 0){
            sha_flash_set(sha_alert("success", "Student removed from house successfully."));
        }else{
            sha_flash_set(sha_alert("warning", "No active student-house assignment found to remove."));
        }
    }else{
        sha_flash_set(sha_alert("error", "Failed to remove student from house: ".mysqli_error($con)));
    }
    header("location:".sha_url($_RedirectContext, "#active-student-houses"));
    exit();
}
$_FlashMessage = sha_flash_take();

$_Where = " WHERE su.systemtype='Student' AND su.status='active' ";
if($_FilterClassId !== ''){
    $_FilterClassIdEsc = mysqli_real_escape_string($con, $_FilterClassId);
    $_Where .= " AND EXISTS (SELECT 1 FROM tblclass cl WHERE cl.userid=su.userid AND cl.class_entryid='$_FilterClassIdEsc' AND cl.status='active') ";
}
if($_FilterBatchId !== ''){
    $_FilterBatchIdEsc = mysqli_real_escape_string($con, $_FilterBatchId);
    $_Where .= " AND EXISTS (SELECT 1 FROM tbltermregistry tr WHERE tr.userid=su.userid AND tr.batchid='$_FilterBatchIdEsc' AND tr.status='active') ";
}
if($_FilterSearch !== ''){
    $_FilterSearchEsc = mysqli_real_escape_string($con, $_FilterSearch);
    $_Where .= " AND (su.userid LIKE '%$_FilterSearchEsc%' OR su.firstname LIKE '%$_FilterSearchEsc%' OR su.surname LIKE '%$_FilterSearchEsc%' OR su.othernames LIKE '%$_FilterSearchEsc%') ";
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/student-house-assignment.css">
<style>
.sha-print-header { display: none; }

@media print {
    body * { visibility: hidden !important; }
    .print-area, .print-area * { visibility: visible !important; }
    .print-area {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: #ffffff;
    }
    .header, .print-hide, .sha-page-header, .sha-card__title, .sha-empty-note { display: none !important; }
    #single-assignment form,
    .sha-filter-form,
    .sha-form-actions,
    .sha-inline-form,
    .sha-inline-tools,
    .sha-select-all { display: none !important; }
    .main-platform, .form-entry, .sha-card { margin: 0 !important; padding: 0 !important; border: 0 !important; box-shadow: none !important; background: transparent !important; }
    table { width: 100% !important; }
    .selection-col { display: none !important; }
    input[type="checkbox"] { display: none !important; }
    .sha-table-wrap { overflow: visible !important; }
    .sha-print-header { display: block !important; margin-bottom: 14px; }
}
</style>
<script>
function toggleAllStudents(source){
    var checkboxes = document.querySelectorAll("input[name='studentids[]']");
    for(var i=0;i<checkboxes.length;i++){
        checkboxes[i].checked = source.checked;
    }
}
</script>
</head>
<body class="student-house-assignment-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
<div class="sha-page-header">
<p class="sha-page-header__eyebrow">House Management</p>
<h3>Student House Assignment</h3>
<p class="sha-page-header__copy">Assign students to houses, load students by class or batch, and review active house placements.</p>
</div>
<?php if($_FlashMessage !== ""){ echo $_FlashMessage; } ?>

<div class="sha-layout">
<div class="sha-column sha-column--side">
<div class="form-entry sha-card" align="left" id="single-assignment">
<div class="sha-card__title">
<h4>Single Assignment</h4>
<p>Assign one student quickly when you already know the house.</p>
</div>

<form method="post" action="student-house-assignment.php" id="formID" name="formID">
<input type="hidden" name="filter_classid" value="<?php echo sha_esc($_FilterClassId); ?>">
<input type="hidden" name="filter_batchid" value="<?php echo sha_esc($_FilterBatchId); ?>">
<input type="hidden" name="filter_search" value="<?php echo sha_esc($_FilterSearch); ?>">
<?php
$_SQL_S = mysqli_query($con,"SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' AND status='active' ORDER BY firstname ASC");
echo "<label>Student</label><br/>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Student</option>";
while($row_s=mysqli_fetch_array($_SQL_S,MYSQLI_ASSOC)){
    echo "<option value='$row_s[userid]'>$row_s[firstname] $row_s[othernames] $row_s[surname] ($row_s[userid])</option>";
}
echo "</select><br/><br/>";

$_SQL_H = mysqli_query($con,"SELECT houseid,housename FROM tblhouse WHERE status='active' ORDER BY housename ASC");
echo "<label>House</label><br/>";
echo "<select id='houseid' name='houseid' class='validate[required]'>";
echo "<option value=''>Select House</option>";
while($row_h=mysqli_fetch_array($_SQL_H,MYSQLI_ASSOC)){
    echo "<option value='$row_h[houseid]'>$row_h[housename]</option>";
}
echo "</select><br/><br/>";
?>
<div align="center"><button class="button-save" id="save_student_house" name="save_student_house"><i class="fa fa-save"></i> Save Assignment</button></div>
</form>
</div>

<div class="form-entry sha-card sha-card--note">
<div class="sha-card__title">
<h4>Helpful Tip</h4>
<p>Use the batch filter when you want to load a whole intake quickly. Use class and search when you want a smaller list on mobile.</p>
</div>
</div>
</div>

<div class="sha-column sha-column--main">
<div class="form-entry sha-card" id="bulk-assignment">
<div class="sha-card__title">
<h4>Bulk Assignment</h4>
<p>Load a filtered student list, select the students you want, and assign them to one house in a single action.</p>
</div>

<form method="get" action="student-house-assignment.php" class="sha-filter-form print-hide">
<?php
$_SQL_CLS = mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
echo "<label>Filter by Class</label><br/>";
echo "<select name='filter_classid'>";
echo "<option value=''>All Classes</option>";
while($row_cls=mysqli_fetch_array($_SQL_CLS,MYSQLI_ASSOC)){
    $_Selected = ($_FilterClassId === $row_cls['class_entryid']) ? "selected" : "";
    echo "<option value='$row_cls[class_entryid]' $_Selected>$row_cls[class_name]</option>";
}
echo "</select><br/><br/>";

$_SQL_BH = mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
echo "<label>Filter by Batch</label><br/>";
echo "<select name='filter_batchid'>";
echo "<option value=''>All Batches</option>";
while($row_bh=mysqli_fetch_array($_SQL_BH,MYSQLI_ASSOC)){
    $_Selected = ($_FilterBatchId === $row_bh['batchid']) ? "selected" : "";
    echo "<option value='$row_bh[batchid]' $_Selected>$row_bh[batch]</option>";
}
echo "</select><br/><br/>";
?>
<label>Search (Name or ID)</label><br/>
<input type="text" name="filter_search" value="<?php echo htmlspecialchars($_FilterSearch); ?>" />
<div class="sha-filter-actions">
<button type="submit" name="load_mode" value="all" title="Load using current filters"><i class="fa fa-filter"></i> Load Students</button>
<button type="submit" name="load_mode" value="batch" title="Load all students in selected batch" onclick="return loadByBatchOnly();"><i class="fa fa-users"></i> Load By Batch</button>
<button type="button" title="Clear filters" onclick="clearFilters();"><i class="fa fa-eraser"></i> Clear Filters</button>
</div>
</form>

<form method="post" action="student-house-assignment.php">
<input type="hidden" name="filter_classid" value="<?php echo sha_esc($_FilterClassId); ?>">
<input type="hidden" name="filter_batchid" value="<?php echo sha_esc($_FilterBatchId); ?>">
<input type="hidden" name="filter_search" value="<?php echo sha_esc($_FilterSearch); ?>">
<?php
$_SQL_H2 = mysqli_query($con,"SELECT houseid,housename FROM tblhouse WHERE status='active' ORDER BY housename ASC");
echo "<label>Assign Selected To House</label><br/>";
echo "<select id='bulk_houseid' name='bulk_houseid' required>";
echo "<option value=''>Select House</option>";
$_HousePrintOptions = array();
while($row_h2=mysqli_fetch_array($_SQL_H2,MYSQLI_ASSOC)){
    $_HousePrintOptions[$row_h2['houseid']] = $row_h2['housename'];
    echo "<option value='$row_h2[houseid]'>$row_h2[housename]</option>";
}
echo "</select><br/><br/>";

$_SQL_BULK = mysqli_query($con,"SELECT su.userid,su.firstname,su.surname,su.othernames,COALESCE(h.housename,'Not Assigned') AS currenthouse, COALESCE(h.houseid,'') AS currenthouseid
FROM tblsystemuser su
LEFT JOIN (
    SELECT sh1.userid,sh1.houseid
    FROM tblstudenthouse sh1
    INNER JOIN (
        SELECT userid,MAX(datetimeentry) AS latestdt
        FROM tblstudenthouse
        WHERE status='active'
        GROUP BY userid
    ) sh2 ON sh2.userid=sh1.userid AND sh2.latestdt=sh1.datetimeentry
    WHERE sh1.status='active'
) sh ON sh.userid=su.userid
LEFT JOIN tblhouse h ON h.houseid=sh.houseid
$_Where
ORDER BY su.firstname ASC,su.surname ASC");

echo "<div class='sha-inline-tools print-hide'>";
echo "<label for='print_houseid' style='margin:0;'>Print House</label>";
echo "<select id='print_houseid'>";
echo "<option value=''>All Loaded Students</option>";
foreach($_HousePrintOptions as $_PHouseId => $_PHouseName){
    echo "<option value='".htmlspecialchars($_PHouseId)."'>".htmlspecialchars($_PHouseName)."</option>";
}
echo "<option value='__unassigned__'>Not Assigned</option>";
echo "</select>";
echo "<button type='button' onclick='loadStudentsBySelectedHouse();'><i class='fa fa-refresh'></i> Load House</button>";
echo "<button type='button' onclick='printStudentListByHouse();'><i class='fa fa-print'></i> Print Student List</button>";
echo "</div>";

echo "<div class='print-area'>";
echo "<div class='sha-print-header' id='sha-print-header'>";
echo "<h4 class='sha-subheading'>Students List</h4>";
echo "<div class='sha-count-note' id='sha-print-scope'>All loaded students</div>";
echo "</div>";
echo "<div class='sha-select-all'><input type='checkbox' onclick='toggleAllStudents(this)' /> Select All Loaded Students</div>";
echo "<div class='sha-table-wrap'>";
echo "<table id='loaded-students-table' width='100%' style='background-color:white'>";
echo "<thead><th class='sha-number-col'>#</th><th class='selection-col'></th><th>Student</th><th>Current House</th></thead>";
echo "<tbody>";
$_CountLoaded = 0;
while($row_bulk=mysqli_fetch_array($_SQL_BULK,MYSQLI_ASSOC)){
    $_CountLoaded++;
    $_RowHouseId = ($row_bulk['currenthouseid'] !== '') ? $row_bulk['currenthouseid'] : '__unassigned__';
    echo "<tr data-houseid='".htmlspecialchars($_RowHouseId)."'>";
    echo "<td align='center' class='sha-row-number'>".$_CountLoaded."</td>";
    echo "<td class='selection-col' align='center'><input type='checkbox' name='studentids[]' value='".htmlspecialchars($row_bulk['userid'])."' /></td>";
    echo "<td>".htmlspecialchars($row_bulk['firstname']." ".$row_bulk['othernames']." ".$row_bulk['surname'])." (".htmlspecialchars($row_bulk['userid']).")</td>";
    echo "<td align='center'>".htmlspecialchars($row_bulk['currenthouse'])."</td>";
    echo "</tr>";
}
if($_CountLoaded === 0){
    echo "<tr><td colspan='4' align='center'>No students matched your filters.</td></tr>";
}
echo "</tbody>";
echo "</table>";
echo "</div>";
echo "<div class='sha-count-note' id='loaded-students-summary'>Loaded: ".(int)$_CountLoaded." student(s)</div>";
echo "</div>";
?>
<div class="sha-form-actions" align="right">
<button class="button-save" type="submit" name="bulk_assign_students"><i class="fa fa-save"></i> Assign Selected Students</button>
</div>
</form>
</div>

<div class="form-entry sha-card" style="margin-top:10px;" id="active-student-houses">
<div class="sha-card__title">
<h4>Active Student House Assignments</h4>
<p>Review current house placements and remove an assignment safely when a student needs to be moved.</p>
</div>
<?php
$_SQL_A = mysqli_query($con,"SELECT sh.*,h.housename,su.firstname,su.surname,su.othernames
FROM tblstudenthouse sh
INNER JOIN tblhouse h ON h.houseid=sh.houseid
INNER JOIN tblsystemuser su ON su.userid=sh.userid
WHERE sh.status='active'
ORDER BY sh.datetimeentry DESC");
echo "<div class='sha-table-wrap'>";
echo "<table width='100%' style='background-color:white'>";
echo "<thead><th>Task</th><th>Student</th><th>House</th><th>Date/Time</th></thead>";
echo "<tbody>";
$_AssignmentCount = 0;
while($row=mysqli_fetch_array($_SQL_A,MYSQLI_ASSOC)){
    $_AssignmentCount++;
    echo "<tr>";
    echo "<td align='center'>";
    echo "<form method='post' action='student-house-assignment.php' class='sha-inline-form' onsubmit=\"return confirm('Remove this student from house assignment?');\">";
    echo "<input type='hidden' name='assignmentid' value='".sha_esc($row['assignmentid'])."'>";
    echo "<input type='hidden' name='filter_classid' value='".sha_esc($_FilterClassId)."'>";
    echo "<input type='hidden' name='filter_batchid' value='".sha_esc($_FilterBatchId)."'>";
    echo "<input type='hidden' name='filter_search' value='".sha_esc($_FilterSearch)."'>";
    echo "<button type='submit' name='remove_student_house' class='sha-icon-button' title='Remove student from house'><i class='fa fa-trash'></i></button>";
    echo "</form>";
    echo "</td>";
    echo "<td>".htmlspecialchars($row['firstname']." ".$row['othernames']." ".$row['surname'])." (".htmlspecialchars($row['userid']).")</td>";
    echo "<td align='center'>".htmlspecialchars($row['housename'])."</td>";
    echo "<td align='center'>".htmlspecialchars($row['datetimeentry'])."</td>";
    echo "</tr>";
}
if($_AssignmentCount === 0){
    echo "<tr><td colspan='4' align='center'>No active student-house assignments found yet.</td></tr>";
}
echo "</tbody>";
echo "</table>";
echo "</div>";
echo "<div class='sha-count-note sha-empty-note'>Active assignments: ".(int)$_AssignmentCount."</div>";
?>
</div>
</div>
</div>
</div>
<script>
function loadByBatchOnly(){
    var batch = document.querySelector("select[name='filter_batchid']");
    if(!batch || !batch.value){
        alert("Select a batch first.");
        return false;
    }
    var classSel = document.querySelector("select[name='filter_classid']");
    var search = document.querySelector("input[name='filter_search']");
    if(classSel){ classSel.value = ""; }
    if(search){ search.value = ""; }
    return true;
}

function clearFilters(){
    window.location = "student-house-assignment.php";
}

function printStudentListByHouse(){
    var selectedHouse = "";
    var houseSel = document.getElementById("print_houseid");
    if(houseSel){
        selectedHouse = houseSel.value || "";
    }
    updatePrintHeader(selectedHouse);
    filterLoadedStudentsByHouse(selectedHouse, true);
    window.setTimeout(function(){
        window.print();
    }, 120);
}

function loadStudentsBySelectedHouse(){
    var houseSel = document.getElementById("print_houseid");
    var selectedHouse = houseSel ? (houseSel.value || "") : "";
    updatePrintHeader(selectedHouse);
    filterLoadedStudentsByHouse(selectedHouse, false);
}

function updatePrintHeader(selectedHouse){
    var scope = document.getElementById("sha-print-scope");
    if(!scope){
        return;
    }
    if(selectedHouse === ""){
        scope.textContent = "All loaded students";
        return;
    }
    var houseSel = document.getElementById("print_houseid");
    if(!houseSel){
        scope.textContent = "Selected house";
        return;
    }
    var selectedOption = houseSel.options[houseSel.selectedIndex];
    var selectedLabel = selectedOption ? (selectedOption.text || "Selected house") : "Selected house";
    scope.textContent = selectedLabel;
}

function filterLoadedStudentsByHouse(selectedHouse, forPrint){
    var table = document.getElementById("loaded-students-table");
    if(!table){
        return 0;
    }
    var rows = table.querySelectorAll("tbody tr");
    var actualRowCount = 0;
    var visibleCount = 0;
    var emptyRow = document.getElementById("loaded-students-empty-row");

    if(!emptyRow){
        emptyRow = document.createElement("tr");
        emptyRow.id = "loaded-students-empty-row";
        emptyRow.style.display = "none";
        emptyRow.innerHTML = "<td colspan='4' align='center'>No loaded students are currently in the selected house.</td>";
        table.querySelector("tbody").appendChild(emptyRow);
    }

    var numbering = 0;
    for(var i=0;i<rows.length;i++){
        if(rows[i].id === "loaded-students-empty-row"){
            continue;
        }
        var houseId = rows[i].getAttribute("data-houseid") || "";
        var numberCell = rows[i].querySelector(".sha-row-number");
        if(rows[i].hasAttribute("data-houseid")){
            actualRowCount++;
        }
        if(!rows[i].hasAttribute("data-houseid")){
            rows[i].style.display = (actualRowCount === 0) ? "" : "none";
            if(numberCell){
                numberCell.textContent = "";
            }
        }else if(selectedHouse === "" || houseId === selectedHouse){
            rows[i].style.display = "";
            numbering++;
            if(numberCell){
                numberCell.textContent = numbering;
            }
            visibleCount++;
        }else{
            rows[i].style.display = "none";
            if(numberCell){
                numberCell.textContent = "";
            }
        }
    }

    if(actualRowCount > 0 && visibleCount === 0){
        emptyRow.style.display = "";
    }else{
        emptyRow.style.display = "none";
    }

    if(!forPrint){
        var summary = document.getElementById("loaded-students-summary");
        if(summary){
            if(selectedHouse === ""){
                summary.textContent = "Loaded: " + actualRowCount + " student(s)";
            }else{
                summary.textContent = "Showing: " + visibleCount + " of " + actualRowCount + " loaded student(s)";
            }
        }
    }

    return visibleCount;
}

document.addEventListener("DOMContentLoaded", function(){
    updatePrintHeader("");
});
</script>
</body>
</html>

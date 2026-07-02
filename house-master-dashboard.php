<?php
session_start();
$_FlashMessageText = trim(strip_tags((string)($_SESSION['Message'] ?? "")));
$_SESSION['Message'] = "";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
include_once("online-admission-utils.php");
ensure_house_tables($con);
ensure_online_admission_tables($con);

if(!house_master_is_teacher()){
    header("location:".house_master_landing_page());
    exit();
}

function house_master_dash_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function house_master_dash_datetime($value){
    $value = trim((string)$value);
    if($value === "" || $value === "0000-00-00 00:00:00"){
        return "-";
    }
    $timestamp = strtotime($value);
    return $timestamp ? date("d M Y, H:i", $timestamp) : $value;
}

$_TeacherIdRaw = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : "";
$_TeacherId = mysqli_real_escape_string($con, $_TeacherIdRaw);
$_TeacherDisplayName = $_TeacherIdRaw !== "" ? $_TeacherIdRaw : "Teacher";
$_DashboardTitle = house_master_dashboard_label($con, $_TeacherIdRaw);
$_HomeUrl = house_master_landing_page();
$_HasAssignment = house_master_has_assignment($con, $_TeacherIdRaw);
$_StudentSearch = isset($_GET['student_search']) ? trim((string)$_GET['student_search']) : "";
$_StudentHouseId = isset($_GET['student_houseid']) ? trim((string)$_GET['student_houseid']) : "";
$_PostedSearch = isset($_GET['posted_search']) ? trim((string)$_GET['posted_search']) : "";
$_PostedHouseId = isset($_GET['posted_houseid']) ? trim((string)$_GET['posted_houseid']) : "";
$_TeacherHouseScope = get_teacher_house_filter_sql($con, $_TeacherIdRaw);
$_ExpectedReturnSql = house_master_exeat_expected_return_sql('er');
$_OverdueSql = house_master_exeat_overdue_sql('er');

$_Overview = array(
    "houses" => 0,
    "students" => 0,
    "pending_exeat" => 0,
    "active_out" => 0,
    "overdue_returns" => 0,
    "returned_today" => 0
);
$_HouseAssignments = array();
$_AssignedHouseMap = array();
$_StudentRows = array();
$_PostedRows = array();
$_OverdueRows = array();
$_ReturnedRows = array();
$_Alerts = array();

if($_TeacherIdRaw !== ""){
    $_TeacherRes = mysqli_query($con, "SELECT
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(firstname,''), ' ', COALESCE(othernames,''), ' ', COALESCE(surname,''))), ''), userid) AS teacher_name
        FROM tblsystemuser
        WHERE userid='$_TeacherId'
        LIMIT 1");
    if($_TeacherRes && $_TeacherRow = mysqli_fetch_array($_TeacherRes, MYSQLI_ASSOC)){
        $_TeacherDisplayName = (string)$_TeacherRow['teacher_name'];
    }
}

if($_HasAssignment){
    $_HouseRes = mysqli_query($con, "SELECT
        hm.houseid,
        h.housename,
        h.description,
        hm.datetimeentry AS assigned_on,
        (SELECT COUNT(*)
            FROM tblstudenthouse sh
            INNER JOIN tblsystemuser su ON su.userid=sh.userid
            WHERE sh.houseid=hm.houseid
              AND sh.status='active'
              AND su.systemtype='Student'
              AND su.status='active'
        ) AS student_count,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=hm.houseid AND er.status='pending') AS pending_exeat,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=hm.houseid AND er.status='approved' AND er.actualreturndatetime IS NULL) AS active_out,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=hm.houseid AND ".$_OverdueSql.") AS overdue_returns,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=hm.houseid AND er.actualreturndatetime IS NOT NULL AND DATE(er.actualreturndatetime)=CURDATE()) AS returned_today
        FROM tblhousemaster hm
        INNER JOIN tblhouse h ON h.houseid=hm.houseid
        WHERE hm.userid='$_TeacherId'
          AND hm.status='active'
        ORDER BY overdue_returns DESC, pending_exeat DESC, h.housename ASC");
    if($_HouseRes){
        while($_HouseRow = mysqli_fetch_array($_HouseRes, MYSQLI_ASSOC)){
            $_HouseAssignments[] = $_HouseRow;
            $_AssignedHouseMap[$_HouseRow['houseid']] = $_HouseRow['housename'];
            $_Overview["houses"]++;
            $_Overview["students"] += (int)$_HouseRow["student_count"];
            $_Overview["pending_exeat"] += (int)$_HouseRow["pending_exeat"];
            $_Overview["active_out"] += (int)$_HouseRow["active_out"];
            $_Overview["overdue_returns"] += (int)$_HouseRow["overdue_returns"];
            $_Overview["returned_today"] += (int)$_HouseRow["returned_today"];
        }
    }

    if($_StudentHouseId !== "" && !isset($_AssignedHouseMap[$_StudentHouseId])){
        $_StudentHouseId = "";
    }
    if($_PostedHouseId !== "" && !isset($_AssignedHouseMap[$_PostedHouseId])){
        $_PostedHouseId = "";
    }

    $_StudentSearchSql = "";
    if($_StudentSearch !== ""){
        $_StudentSearchEsc = mysqli_real_escape_string($con, $_StudentSearch);
        $_StudentSearchLike = "%".$_StudentSearchEsc."%";
        $_StudentSearchSql = " AND (
            su.userid LIKE '$_StudentSearchLike'
            OR CONCAT_WS(' ', COALESCE(su.firstname,''), COALESCE(su.othernames,''), COALESCE(su.surname,'')) LIKE '$_StudentSearchLike'
            OR h.housename LIKE '$_StudentSearchLike'
            OR COALESCE(su.mobile,'') LIKE '$_StudentSearchLike'
            OR COALESCE(su.nextofkin_contact,'') LIKE '$_StudentSearchLike'
        )";
    }

    $_StudentHouseSql = "";
    if($_StudentHouseId !== ""){
        $_StudentHouseEsc = mysqli_real_escape_string($con, $_StudentHouseId);
        $_StudentHouseSql = " AND sh.houseid='$_StudentHouseEsc'";
    }

    $_PostedSearchSql = "";
    if($_PostedSearch !== ""){
        $_PostedSearchEsc = mysqli_real_escape_string($con, $_PostedSearch);
        $_PostedSearchLike = "%".$_PostedSearchEsc."%";
        $_PostedSearchSql = " AND (
            post.beceindexnumber LIKE '$_PostedSearchLike'
            OR post.firstname LIKE '$_PostedSearchLike'
            OR post.surname LIKE '$_PostedSearchLike'
            OR post.othernames LIKE '$_PostedSearchLike'
            OR CONCAT_WS(' ', COALESCE(post.firstname,''), COALESCE(post.othernames,''), COALESCE(post.surname,'')) LIKE '$_PostedSearchLike'
            OR CONCAT_WS(' ', COALESCE(post.surname,''), COALESCE(post.firstname,''), COALESCE(post.othernames,'')) LIKE '$_PostedSearchLike'
            OR COALESCE(post.gender,'') LIKE '$_PostedSearchLike'
            OR COALESCE(post.admissionyear,'') LIKE '$_PostedSearchLike'
            OR COALESCE(post.offeredprogram,'') LIKE '$_PostedSearchLike'
            OR COALESCE(post.offeredclass,'') LIKE '$_PostedSearchLike'
            OR COALESCE(post.residentialstatus,'') LIKE '$_PostedSearchLike'
            OR COALESCE(post.mobile,'') LIKE '$_PostedSearchLike'
            OR COALESCE(h.housename,'') LIKE '$_PostedSearchLike'
            OR COALESCE(app.status,'') LIKE '$_PostedSearchLike'
        )";
    }

    $_PostedHouseSql = "";
    if($_PostedHouseId !== ""){
        $_PostedHouseEsc = mysqli_real_escape_string($con, $_PostedHouseId);
        $_PostedHouseSql = " AND app.assignedhouseid='$_PostedHouseEsc'";
    }

    $_StudentRes = mysqli_query($con, "SELECT
        su.userid,
        su.firstname,
        su.surname,
        su.othernames,
        su.mobile,
        su.nextofkin_contact,
        h.housename,
        sh.datetimeentry
        FROM tblstudenthouse sh
        INNER JOIN tblsystemuser su ON su.userid=sh.userid
        INNER JOIN tblhouse h ON h.houseid=sh.houseid
        WHERE sh.houseid IN (".$_TeacherHouseScope.")
          AND sh.status='active'
          AND su.systemtype='Student'
          AND su.status='active'
          $_StudentHouseSql
          $_StudentSearchSql
        ORDER BY h.housename ASC, su.firstname ASC, su.surname ASC");
    if($_StudentRes){
        while($_StudentRow = mysqli_fetch_array($_StudentRes, MYSQLI_ASSOC)){
            $_StudentRows[] = $_StudentRow;
        }
    }

    $_PostedRes = mysqli_query($con, "SELECT
        post.postingid,
        post.beceindexnumber,
        post.firstname,
        post.surname,
        post.othernames,
        post.gender,
        post.admissionyear,
        post.offeredprogram,
        post.offeredclass,
        post.residentialstatus,
        post.mobile,
        post.datetimeentry AS posted_on,
        h.housename,
        app.applicationid,
        app.status AS application_status,
        app.updatedat AS application_updatedat,
        app.assignedhouseat,
        pay.status AS payment_status,
        pay.paidat
        FROM tblonlineadmissionapplication app
        INNER JOIN tbladmissionpostedstudent post ON post.postingid=app.postingid
        LEFT JOIN tblhouse h ON h.houseid=app.assignedhouseid
        LEFT JOIN tblonlineadmissionpayment pay ON pay.paymentid = (
            SELECT p2.paymentid
            FROM tblonlineadmissionpayment p2
            WHERE p2.applicationid=app.applicationid
            ORDER BY
                CASE
                    WHEN p2.status='success' THEN 0
                    WHEN p2.status='pending' THEN 1
                    WHEN p2.status='initialized' THEN 2
                    ELSE 3
                END ASC,
                COALESCE(p2.paidat, p2.createdat) DESC
            LIMIT 1
        )
        WHERE app.assignedhouseid IN (".$_TeacherHouseScope.")
          AND post.status='active'
          $_PostedHouseSql
          $_PostedSearchSql
        ORDER BY h.housename ASC, app.updatedat DESC, post.datetimeentry DESC");
    if($_PostedRes){
        while($_PostedRow = mysqli_fetch_array($_PostedRes, MYSQLI_ASSOC)){
            $_PostedRows[] = $_PostedRow;
        }
    }

    $_OverdueRes = mysqli_query($con, "SELECT
        er.exeatid,
        h.housename,
        su.userid,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name,
        ".$_ExpectedReturnSql." AS expected_returndatetime,
        TIMESTAMPDIFF(HOUR, ".$_ExpectedReturnSql.", NOW()) AS hours_overdue
        FROM tblexeatrequest er
        INNER JOIN tblhouse h ON h.houseid=er.houseid
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.houseid IN (".$_TeacherHouseScope.")
          AND ".$_OverdueSql."
        ORDER BY ".$_ExpectedReturnSql." ASC
        LIMIT 8");
    if($_OverdueRes){
        while($_OverdueRow = mysqli_fetch_array($_OverdueRes, MYSQLI_ASSOC)){
            $_OverdueRows[] = $_OverdueRow;
        }
    }

    $_ReturnedRes = mysqli_query($con, "SELECT
        er.exeatid,
        er.actualreturndatetime,
        er.returnedby,
        er.returnnote,
        h.housename,
        su.userid,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name
        FROM tblexeatrequest er
        INNER JOIN tblhouse h ON h.houseid=er.houseid
        INNER JOIN tblsystemuser su ON su.userid=er.userid
        WHERE er.houseid IN (".$_TeacherHouseScope.")
          AND er.actualreturndatetime IS NOT NULL
          AND DATE(er.actualreturndatetime)=CURDATE()
        ORDER BY er.actualreturndatetime DESC
        LIMIT 8");
    if($_ReturnedRes){
        while($_ReturnedRow = mysqli_fetch_array($_ReturnedRes, MYSQLI_ASSOC)){
            $_ReturnedRows[] = $_ReturnedRow;
        }
    }

    if($_Overview["overdue_returns"] > 0){
        $_Alerts[] = $_Overview["overdue_returns"]." student(s) are overdue from exeat return in your assigned houses.";
    }
    if($_Overview["pending_exeat"] > 0){
        $_Alerts[] = $_Overview["pending_exeat"]." exeat request(s) are waiting for your attention.";
    }
}

$_FilterParts = array();
if($_StudentHouseId !== "" && isset($_AssignedHouseMap[$_StudentHouseId])){
    $_FilterParts[] = "House: ".$_AssignedHouseMap[$_StudentHouseId];
}
if($_StudentSearch !== ""){
    $_FilterParts[] = "Search: ".$_StudentSearch;
}
$_FilterSummary = implode(" | ", $_FilterParts);
$_StudentResultCount = count($_StudentRows);
$_FilterActive = ($_FilterSummary !== "");
$_StudentPrintTitle = ($_StudentHouseId !== "" && isset($_AssignedHouseMap[$_StudentHouseId]))
    ? ($_AssignedHouseMap[$_StudentHouseId]." Student List")
    : "Assigned House Student List";
$_StudentPrintSummary = $_FilterActive
    ? $_FilterSummary
    : "All students across your assigned houses.";
$_PostedFilterParts = array();
if($_PostedHouseId !== "" && isset($_AssignedHouseMap[$_PostedHouseId])){
    $_PostedFilterParts[] = "House: ".$_AssignedHouseMap[$_PostedHouseId];
}
if($_PostedSearch !== ""){
    $_PostedFilterParts[] = "Search: ".$_PostedSearch;
}
$_PostedFilterSummary = implode(" | ", $_PostedFilterParts);
$_PostedResultCount = count($_PostedRows);
$_PostedFilterActive = ($_PostedFilterSummary !== "");
$_FlashAlertClass = "hm-alert-success";
if($_FlashMessageText !== ""){
    $_FlashLower = strtolower($_FlashMessageText);
    if(strpos($_FlashLower, "fail") !== false || strpos($_FlashLower, "error") !== false || strpos($_FlashLower, "invalid") !== false){
        $_FlashAlertClass = "hm-alert-warning";
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
<style>
:root{
    --hm-ink:#132238;
    --hm-muted:#60748a;
    --hm-border:#d9e3ec;
    --hm-panel:#ffffff;
    --hm-bg:#f4f7fb;
    --hm-brand:#0f766e;
    --hm-brand-deep:#155e75;
    --hm-info:#ecfeff;
    --hm-success:#f0fdf4;
    --hm-warn:#fff7ed;
    --hm-danger:#fff1f2;
}
body{
    background:
        radial-gradient(circle at top left, rgba(21,94,117,0.12), transparent 26%),
        linear-gradient(180deg, #f9fbff 0%, var(--hm-bg) 100%);
}
.hm-wrap{
    max-width:1240px;
    margin:0 auto;
}
.hm-shell{
    background:rgba(255,255,255,0.9);
    border:1px solid rgba(217,227,236,0.95);
    border-radius:24px;
    padding:20px;
    box-shadow:0 22px 48px rgba(15,23,42,0.08);
}
.hm-hero{
    display:flex;
    justify-content:space-between;
    gap:22px;
    align-items:flex-start;
    margin-bottom:12px;
}
.hm-copy{
    flex:1 1 auto;
}
.hm-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(15,118,110,0.1);
    color:var(--hm-brand);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.08em;
    margin-bottom:12px;
}
.hm-title{
    margin:0;
    color:var(--hm-ink);
    font-size:32px;
    line-height:1.05;
}
.hm-subtitle{
    margin:10px 0 0 0;
    color:var(--hm-muted);
    max-width:780px;
    line-height:1.6;
    font-size:14px;
}
.hm-chip-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:14px;
}
.hm-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 12px;
    border-radius:999px;
    border:1px solid transparent;
    font-size:12px;
    font-weight:700;
    line-height:1;
}
.hm-chip-neutral{
    background:#f8fbfd;
    border-color:#d7e4ef;
    color:var(--hm-ink);
}
.hm-chip-info{
    background:var(--hm-info);
    border-color:#bae6fd;
    color:#0f4c81;
}
.hm-chip-success{
    background:var(--hm-success);
    border-color:#bbf7d0;
    color:#166534;
}
.hm-chip-warning{
    background:#fffbeb;
    border-color:#fde68a;
    color:#92400e;
}
.hm-chip-danger{
    background:var(--hm-danger);
    border-color:#fecdd3;
    color:#b91c1c;
}
.hm-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    justify-content:flex-end;
}
.hm-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid var(--hm-border);
    background:#ffffff;
    color:var(--hm-ink);
    text-decoration:none;
    padding:10px 15px;
    border-radius:999px;
    font-weight:700;
    cursor:pointer;
    font:inherit;
    box-shadow:0 8px 18px rgba(15,23,42,0.04);
    transition:transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}
.hm-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 20px rgba(15,23,42,0.08);
    border-color:#bfd0df;
}
.hm-btn-primary{
    background:linear-gradient(135deg, var(--hm-brand) 0%, var(--hm-brand-deep) 100%);
    border-color:var(--hm-brand);
    color:#ffffff;
}
.hm-nav{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin:14px 0 18px 0;
}
.hm-nav-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:14px;
    text-decoration:none;
    background:#ffffff;
    border:1px solid var(--hm-border);
    color:var(--hm-ink);
    font-weight:700;
    font-size:13px;
    transition:transform 0.16s ease, border-color 0.16s ease, background 0.16s ease;
}
.hm-nav-link:hover{
    transform:translateY(-1px);
    border-color:#b9ccdb;
    background:#f8fbfd;
}
.hm-alerts{
    display:grid;
    gap:10px;
    margin-bottom:18px;
}
.hm-alert{
    border-radius:14px;
    padding:12px 14px;
    box-shadow:0 8px 16px rgba(15,23,42,0.04);
}
.hm-alert-warning{
    border:1px solid #fed7aa;
    background:var(--hm-warn);
    color:#9a3412;
}
.hm-alert-success{
    border:1px solid #bbf7d0;
    background:var(--hm-success);
    color:#166534;
}
.hm-stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
    gap:14px;
    margin-bottom:18px;
}
.hm-stat{
    background:var(--hm-panel);
    border:1px solid var(--hm-border);
    border-radius:18px;
    padding:16px;
    position:relative;
    overflow:hidden;
}
.hm-stat::before{
    content:"";
    position:absolute;
    inset:0 auto 0 0;
    width:5px;
    background:#cbd5e1;
}
.hm-stat-neutral::before{
    background:#94a3b8;
}
.hm-stat-info::before{
    background:#0891b2;
}
.hm-stat-success::before{
    background:#16a34a;
}
.hm-stat-warning::before{
    background:#d97706;
}
.hm-stat-danger::before{
    background:#dc2626;
}
.hm-stat h4{
    margin:0 0 8px 0;
    font-size:12px;
    color:var(--hm-muted);
    text-transform:uppercase;
    letter-spacing:0.08em;
}
.hm-stat strong{
    display:block;
    color:var(--hm-ink);
    font-size:30px;
    line-height:1;
}
.hm-stat span{
    display:block;
    margin-top:8px;
    color:var(--hm-muted);
    font-size:12px;
}
.hm-section{
    background:var(--hm-panel);
    border:1px solid var(--hm-border);
    border-radius:20px;
    padding:16px;
    box-shadow:0 14px 28px rgba(15,23,42,0.04);
}
.hm-section + .hm-section{
    margin-top:14px;
}
.hm-section h3{
    margin:0 0 8px 0;
    color:var(--hm-ink);
    font-size:19px;
    display:flex;
    align-items:center;
    gap:10px;
}
.hm-section p{
    margin:0 0 12px 0;
    color:var(--hm-muted);
    font-size:13px;
    line-height:1.5;
}
.hm-heading-icon{
    color:var(--hm-brand);
}
.hm-grid{
    display:grid;
    grid-template-columns:repeat(12, minmax(0, 1fr));
    gap:14px;
    margin-top:14px;
}
.hm-half{
    grid-column:span 6;
}
.hm-note{
    border:1px dashed #c9d7e3;
    border-radius:16px;
    padding:16px;
    color:var(--hm-muted);
    background:#fbfdff;
    font-size:13px;
}
.hm-table-wrap{
    overflow:auto;
    border-radius:16px;
    border:1px solid #e7edf4;
    background:#ffffff;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.7);
}
.hm-table{
    width:100%;
    border-collapse:collapse;
    background:#ffffff;
}
.hm-table th,
.hm-table td{
    border-bottom:1px solid #e9eef5;
    padding:10px 8px;
    vertical-align:top;
    text-align:left;
    font-size:13px;
}
.hm-table th{
    color:var(--hm-muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.04em;
    background:#f8fbfd;
    position:sticky;
    top:0;
    z-index:1;
}
.hm-table tbody tr:nth-child(even){
    background:#fbfdff;
}
.hm-table tbody tr:hover{
    background:#f2f8fc;
}
.hm-row-watch{
    background:linear-gradient(90deg, rgba(245,158,11,0.08), transparent 45%);
}
.hm-row-critical{
    background:linear-gradient(90deg, rgba(220,38,38,0.08), transparent 45%);
}
.hm-row-calm{
    background:linear-gradient(90deg, rgba(22,163,74,0.06), transparent 45%);
}
.hm-table tr:last-child td{
    border-bottom:none;
}
.hm-name{
    font-weight:700;
    color:var(--hm-ink);
}
.hm-muted{
    color:var(--hm-muted);
    font-size:12px;
}
.hm-filter-form{
    display:grid;
    grid-template-columns:minmax(240px, 2fr) minmax(270px, 1.1fr) auto;
    gap:12px;
    align-items:end;
    margin-bottom:14px;
}
.hm-filter-field label{
    display:block;
    margin-bottom:6px;
    color:var(--hm-ink);
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.05em;
}
.hm-filter-field input,
.hm-filter-field select{
    width:100%;
    min-height:46px;
    border:1px solid #cfdce8;
    border-radius:14px;
    padding:10px 12px;
    background:#ffffff;
    color:var(--hm-ink);
    font:inherit;
}
.hm-filter-field input:focus,
.hm-filter-field select:focus{
    outline:none;
    border-color:#74b8c5;
    box-shadow:0 0 0 3px rgba(15,118,110,0.12);
}
.hm-filter-field-house select{
    min-width:270px;
}
.hm-filter-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}
.hm-filter-summary{
    margin:0 0 12px 0;
    color:var(--hm-muted);
    font-size:13px;
}
.hm-print-header{
    display:none;
    margin-bottom:14px;
    padding-bottom:10px;
    border-bottom:1px solid #d7e4ef;
}
.hm-print-header h2{
    margin:0 0 6px 0;
    font-size:22px;
    color:var(--hm-ink);
}
.hm-print-header p{
    margin:0;
    color:var(--hm-muted);
    font-size:13px;
    line-height:1.5;
}
.hm-empty{
    padding:16px;
    color:var(--hm-muted);
    text-align:center;
    font-size:13px;
}
@media (max-width: 980px){
    .hm-hero{
        flex-direction:column;
    }
    .hm-actions{
        width:100%;
        justify-content:flex-start;
    }
    .hm-half{
        grid-column:span 12;
    }
    .hm-filter-form{
        grid-template-columns:1fr;
    }
    .hm-filter-field-house select{
        min-width:0;
    }
}
@media (max-width: 720px){
    .hm-shell{
        padding:16px;
        border-radius:20px;
    }
    .hm-title{
        font-size:28px;
    }
    .hm-chip-row{
        gap:8px;
    }
    .hm-chip{
        width:100%;
        justify-content:flex-start;
    }
    .hm-nav{
        gap:8px;
    }
    .hm-nav-link{
        width:100%;
        justify-content:center;
    }
}
@media print{
    body{
        background:#ffffff;
    }
    body.hm-print-students .hm-hero,
    body.hm-print-students .hm-nav,
    body.hm-print-students .hm-alerts,
    body.hm-print-students .hm-stats,
    body.hm-print-students .hm-grid,
    body.hm-print-students .hm-section:not(#hm-students){
        display:none !important;
    }
    body.hm-print-students .hm-shell{
        box-shadow:none;
        border:none;
        padding:0;
        background:#ffffff;
    }
    body.hm-print-students #hm-students{
        display:block !important;
        border:none;
        box-shadow:none;
        padding:0;
        margin:0;
        background:#ffffff;
    }
    body.hm-print-students #hm-students .hm-filter-form,
    body.hm-print-students #hm-students .hm-filter-actions,
    body.hm-print-students #hm-students > h3,
    body.hm-print-students #hm-students > p{
        display:none !important;
    }
    body.hm-print-students #hm-students .hm-print-header{
        display:block !important;
    }
    body.hm-print-students #hm-students .hm-table-wrap{
        border:none;
        box-shadow:none;
    }
    body.hm-print-students #hm-students .hm-table{
        min-width:0;
    }
    .hm-shell{
        box-shadow:none;
        border:none;
        padding:0;
    }
    .hm-actions,
    .hm-nav,
    .hm-filter-actions{
        display:none !important;
    }
    .hm-section,
    .hm-stat{
        box-shadow:none;
        break-inside:avoid;
    }
}
</style>
</head>
<body>
<div class="hm-wrap" style="padding:24px 12px 32px;">
    <div class="hm-shell">
        <div class="hm-hero">
            <div class="hm-copy">
                <div class="hm-kicker"><i class="fa fa-home"></i> House Operations</div>
                <h1 class="hm-title"><?php echo house_master_dash_esc($_DashboardTitle); ?></h1>
                <p class="hm-subtitle">Stay on top of your assigned houses, keep a close watch on exeat movement, and reach student contacts quickly when action is needed.</p>
                <div class="hm-chip-row">
                    <span class="hm-chip hm-chip-neutral"><i class="fa fa-user-circle"></i> <?php echo house_master_dash_esc($_TeacherDisplayName); ?></span>
                    <span class="hm-chip hm-chip-info"><i class="fa fa-building"></i> <?php echo (int)$_Overview["houses"]; ?> Active House(s)</span>
                    <span class="hm-chip hm-chip-neutral"><i class="fa fa-users"></i> <?php echo (int)$_Overview["students"]; ?> Assigned Student(s)</span>
                    <?php if($_FilterActive){ ?>
                    <span class="hm-chip hm-chip-warning"><i class="fa fa-filter"></i> <?php echo house_master_dash_esc($_FilterSummary); ?></span>
                    <?php } ?>
                    <?php if((int)$_Overview["overdue_returns"] > 0){ ?>
                    <span class="hm-chip hm-chip-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo (int)$_Overview["overdue_returns"]; ?> Overdue Return(s)</span>
                    <?php }elseif((int)$_Overview["returned_today"] > 0){ ?>
                    <span class="hm-chip hm-chip-success"><i class="fa fa-check-circle"></i> <?php echo (int)$_Overview["returned_today"]; ?> Returned Today</span>
                    <?php }else{ ?>
                    <span class="hm-chip hm-chip-success"><i class="fa fa-shield"></i> Daily House View Ready</span>
                    <?php } ?>
                </div>
            </div>
            <div class="hm-actions">
                <a href="<?php echo house_master_dash_esc($_HomeUrl); ?>" class="hm-btn"><i class="fa fa-home"></i> Home</a>
                <?php if($_HasAssignment){ ?>
                <a href="house-master-exeat.php" class="hm-btn hm-btn-primary"><i class="fa fa-random"></i> Exeat Desk</a>
                <?php } ?>
                <button type="button" class="hm-btn" onclick="window.print();"><i class="fa fa-print"></i> Print Dashboard</button>
            </div>
        </div>

        <?php if($_HasAssignment){ ?>
        <div class="hm-nav">
            <a href="#hm-watchlist" class="hm-nav-link"><i class="fa fa-eye"></i> Return Watch</a>
            <a href="#hm-students" class="hm-nav-link"><i class="fa fa-address-book"></i> Student Directory</a>
        </div>
        <?php } ?>

        <div class="hm-alerts">
            <?php if($_FlashMessageText !== ""){ ?>
            <div class="hm-alert <?php echo house_master_dash_esc($_FlashAlertClass); ?>">
                <?php echo house_master_dash_esc($_FlashMessageText); ?>
            </div>
            <?php } ?>
            <?php if(!$_HasAssignment){ ?>
            <div class="hm-alert hm-alert-warning">No active house assignment was found for your account yet. Once admin assigns your house, this dashboard will automatically fill with your students and exeat activity.</div>
            <?php }elseif(count($_Alerts) > 0){ ?>
                <?php foreach($_Alerts as $_AlertText){ ?>
                <div class="hm-alert hm-alert-warning"><?php echo house_master_dash_esc($_AlertText); ?></div>
                <?php } ?>
            <?php }else{ ?>
            <div class="hm-alert hm-alert-success">Your assigned houses are clear right now. There are no overdue return issues waiting on this screen.</div>
            <?php } ?>
        </div>

        <div class="hm-stats">
            <div class="hm-stat hm-stat-info">
                <h4>Assigned Houses</h4>
                <strong><?php echo (int)$_Overview["houses"]; ?></strong>
                <span>Current active house coverage under your supervision.</span>
            </div>
            <div class="hm-stat hm-stat-neutral">
                <h4>Assigned Students</h4>
                <strong><?php echo (int)$_Overview["students"]; ?></strong>
                <span>Students currently placed in your active house scope.</span>
            </div>
            <div class="hm-stat hm-stat-warning">
                <h4>Pending Exeat</h4>
                <strong><?php echo (int)$_Overview["pending_exeat"]; ?></strong>
                <span>Requests still waiting for your review and decision.</span>
            </div>
            <div class="hm-stat hm-stat-info">
                <h4>Out On Exeat</h4>
                <strong><?php echo (int)$_Overview["active_out"]; ?></strong>
                <span>Approved students who are still out and not checked back in.</span>
            </div>
            <div class="hm-stat hm-stat-danger">
                <h4>Overdue Returns</h4>
                <strong><?php echo (int)$_Overview["overdue_returns"]; ?></strong>
                <span>Return times already passed without a recorded check-in.</span>
            </div>
            <div class="hm-stat hm-stat-success">
                <h4>Returned Today</h4>
                <strong><?php echo (int)$_Overview["returned_today"]; ?></strong>
                <span>Students whose return from exeat has been confirmed today.</span>
            </div>
        </div>

        <div class="hm-grid" id="hm-watchlist">
            <div class="hm-section hm-half">
                <h3><i class="fa fa-exclamation-triangle hm-heading-icon"></i> Overdue Return Watchlist</h3>
                <p>Students whose approved exeat return time has passed and who still need to be checked back in.</p>
                <div class="hm-table-wrap">
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>House</th>
                                <th>Expected Return</th>
                                <th>Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($_OverdueRows) > 0){ ?>
                                <?php foreach($_OverdueRows as $_Overdue){ ?>
                                <tr class="hm-row-critical">
                                    <td>
                                        <span class="hm-name"><?php echo house_master_dash_esc($_Overdue["student_name"]); ?></span>
                                        <div class="hm-muted"><?php echo house_master_dash_esc($_Overdue["userid"]); ?></div>
                                    </td>
                                    <td><?php echo house_master_dash_esc($_Overdue["housename"]); ?></td>
                                    <td><?php echo house_master_dash_esc(house_master_dash_datetime($_Overdue["expected_returndatetime"])); ?></td>
                                    <td><?php echo house_master_dash_esc(max(0, (int)$_Overdue["hours_overdue"])." hr(s)"); ?></td>
                                </tr>
                                <?php } ?>
                            <?php }else{ ?>
                            <tr><td colspan="4" class="hm-empty">No overdue exeat returns in your current house scope.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="hm-section hm-half">
                <h3><i class="fa fa-check-circle hm-heading-icon"></i> Checked In Today</h3>
                <p>Students whose return from exeat has been confirmed today, including who checked them back in.</p>
                <div class="hm-table-wrap">
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>House</th>
                                <th>Returned</th>
                                <th>Checked In By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($_ReturnedRows) > 0){ ?>
                                <?php foreach($_ReturnedRows as $_Returned){ ?>
                                <tr class="hm-row-calm">
                                    <td>
                                        <span class="hm-name"><?php echo house_master_dash_esc($_Returned["student_name"]); ?></span>
                                        <div class="hm-muted"><?php echo house_master_dash_esc($_Returned["userid"]); ?></div>
                                    </td>
                                    <td><?php echo house_master_dash_esc($_Returned["housename"]); ?></td>
                                    <td><?php echo house_master_dash_esc(house_master_dash_datetime($_Returned["actualreturndatetime"])); ?></td>
                                    <td>
                                        <?php echo house_master_dash_esc(trim((string)$_Returned["returnedby"]) !== "" ? $_Returned["returnedby"] : "-"); ?>
                                        <?php if(trim((string)$_Returned["returnnote"]) !== ""){ ?>
                                        <div class="hm-muted"><?php echo house_master_dash_esc($_Returned["returnnote"]); ?></div>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            <?php }else{ ?>
                            <tr><td colspan="4" class="hm-empty">No students have been checked back in today in your current house scope.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="hm-section" id="hm-students">
            <h3><i class="fa fa-address-book hm-heading-icon"></i> Student Directory</h3>
            <p>Search active students assigned to your house scope by student ID, name, house, or saved contact details.</p>
            <?php if($_HasAssignment){ ?>
            <form method="get" action="house-master-dashboard.php" class="hm-filter-form">
                <div class="hm-filter-field">
                    <label for="student_search">Search Students</label>
                    <input
                        type="text"
                        id="student_search"
                        name="student_search"
                        value="<?php echo house_master_dash_esc($_StudentSearch); ?>"
                        placeholder="Search name, ID or contact"
                    />
                </div>
                <div class="hm-filter-field hm-filter-field-house">
                    <label for="student_houseid">House</label>
                    <select id="student_houseid" name="student_houseid">
                        <option value="">All Assigned Houses</option>
                        <?php foreach($_AssignedHouseMap as $_HouseId => $_HouseName){ ?>
                        <option value="<?php echo house_master_dash_esc($_HouseId); ?>" <?php echo ($_StudentHouseId === (string)$_HouseId) ? "selected" : ""; ?>>
                            <?php echo house_master_dash_esc($_HouseName); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="hm-filter-actions">
                    <button type="submit" class="hm-btn hm-btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <button type="button" class="hm-btn" onclick="printHouseMasterStudentList();"><i class="fa fa-print"></i> Print House List</button>
                    <?php if($_FilterActive){ ?>
                    <a href="house-master-dashboard.php#hm-students" class="hm-btn"><i class="fa fa-times"></i> Clear</a>
                    <?php } ?>
                </div>
            </form>

            <div class="hm-filter-summary">
                <?php
                if($_FilterActive){
                    echo house_master_dash_esc($_StudentResultCount." student(s) found. ".$_FilterSummary);
                }else{
                    echo house_master_dash_esc("Showing ".$_StudentResultCount." student(s) across your assigned houses.");
                }
                ?>
            </div>

            <div class="hm-print-header">
                <h2><?php echo house_master_dash_esc($_StudentPrintTitle); ?></h2>
                <p><?php echo house_master_dash_esc($_StudentPrintSummary." | Student count: ".$_StudentResultCount); ?></p>
            </div>

            <div class="hm-table-wrap">
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>House</th>
                            <th>Student Contact</th>
                            <th>Parent / Guardian Contact</th>
                            <th>Assigned On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($_StudentResultCount > 0){ ?>
                            <?php foreach($_StudentRows as $_Student){ ?>
                            <?php
                            $_StudentName = trim((string)$_Student["firstname"]." ".(string)$_Student["othernames"]." ".(string)$_Student["surname"]);
                            if($_StudentName === ""){
                                $_StudentName = (string)$_Student["userid"];
                            }
                            ?>
                            <tr class="hm-row-calm">
                                <td>
                                    <span class="hm-name"><?php echo house_master_dash_esc($_StudentName); ?></span>
                                    <div class="hm-muted"><?php echo house_master_dash_esc($_Student["userid"]); ?></div>
                                </td>
                                <td><?php echo house_master_dash_esc($_Student["housename"]); ?></td>
                                <td><?php echo house_master_dash_esc(trim((string)$_Student["mobile"]) !== "" ? $_Student["mobile"] : "-"); ?></td>
                                <td><?php echo house_master_dash_esc(trim((string)$_Student["nextofkin_contact"]) !== "" ? $_Student["nextofkin_contact"] : "-"); ?></td>
                                <td><?php echo house_master_dash_esc(house_master_dash_datetime($_Student["datetimeentry"])); ?></td>
                            </tr>
                            <?php } ?>
                        <?php }else{ ?>
                        <tr><td colspan="5" class="hm-empty">No students matched the current search in your assigned houses.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php }else{ ?>
            <div class="hm-note">Student contacts and search will appear here as soon as your house assignment becomes active.</div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
function printHouseMasterStudentList(){
    document.body.classList.add('hm-print-students');
    var cleanup = function(){
        document.body.classList.remove('hm-print-students');
        window.removeEventListener('afterprint', cleanup);
    };
    window.addEventListener('afterprint', cleanup);
    window.print();
    setTimeout(cleanup, 1200);
}
</script>
</body>
</html>

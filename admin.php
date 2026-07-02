<?php
session_start();

if(isset($_POST['mark_changes_read'])){
    include("dbstring.php");
    include("audit_notifications.php");
    ensureSystemChangeLogTable($con);
    mysqli_query($con, "UPDATE tblsystemchangelog SET status='read' WHERE status='unread' AND actor_type IN ('Teacher','Student')");
    header("Location: admin.php#system-change-notifications");
    exit();
}

if(!function_exists('admin_dashboard_safe')){
function admin_dashboard_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('admin_dashboard_relative_time')){
function admin_dashboard_relative_time($value){
    $timestamp = strtotime((string)$value);
    if($timestamp === false){
        return trim((string)$value);
    }
    $diff = time() - $timestamp;
    if($diff < 0){
        $diff = 0;
    }
    if($diff < 60){
        return "Just now";
    }
    if($diff < 3600){
        return floor($diff / 60)." min ago";
    }
    if($diff < 86400){
        return floor($diff / 3600)." hr ago";
    }
    if($diff < 604800){
        return floor($diff / 86400)." day".(floor($diff / 86400) === 1 ? "" : "s")." ago";
    }
    return date("d M Y, g:i a", $timestamp);
}
}

if(!function_exists('admin_dashboard_action_label')){
function admin_dashboard_action_label($actionType){
    $actionType = strtoupper(trim((string)$actionType));
    $map = array(
        "PASSWORD_CHANGE" => "Password Changed",
        "ADMIN_PASSWORD_RESET" => "Password Reset",
        "CLASS_SCORE_UPLOAD" => "Class Scores Uploaded",
        "EXAM_SCORE_UPLOAD" => "Exam Scores Uploaded",
        "CLASS_EXAM_SCORE_UPLOAD" => "Scores Uploaded",
        "SCORE_UPLOAD" => "Scores Uploaded",
        "RESULT_PUBLISH" => "Results Published",
        "RESULT_APPROVAL" => "Results Approved",
        "RESULT_REOPEN" => "Results Reopened",
        "MARK_DELETE" => "Mark Deleted",
        "ONLINE_ADMISSION_SUBMITTED" => "Online Admission Submitted",
        "ONLINE_ADMISSION_HELP_REQUEST" => "Online Admission Help Request"
    );
    if(isset($map[$actionType])){
        return $map[$actionType];
    }
    if($actionType === ""){
        return "System Activity";
    }
    return ucwords(strtolower(str_replace("_", " ", $actionType)));
}
}

if(!function_exists('admin_dashboard_activity_icon')){
function admin_dashboard_activity_icon($actionType, $source = "log"){
    $actionType = strtoupper(trim((string)$actionType));
    if($source === "registration"){
        return ($actionType === "TEACHER") ? "fa-user-plus" : "fa-graduation-cap";
    }
    if(strpos($actionType, "PASSWORD") !== false){
        return "fa-key";
    }
    if(strpos($actionType, "UPLOAD") !== false || strpos($actionType, "SCORE") !== false){
        return "fa-line-chart";
    }
    if(strpos($actionType, "RESULT") !== false){
        return "fa-file-text-o";
    }
    if(strpos($actionType, "ADMISSION") !== false){
        return "fa-file-text";
    }
    if(strpos($actionType, "DELETE") !== false){
        return "fa-trash-o";
    }
    return "fa-history";
}
}

if(!function_exists('admin_dashboard_activity_tone')){
function admin_dashboard_activity_tone($actionType, $source = "log"){
    $actionType = strtoupper(trim((string)$actionType));
    if($source === "registration"){
        return ($actionType === "TEACHER") ? "accent" : "success";
    }
    if(strpos($actionType, "PASSWORD") !== false){
        return "warning";
    }
    if(strpos($actionType, "UPLOAD") !== false || strpos($actionType, "SCORE") !== false){
        return "info";
    }
    if(strpos($actionType, "RESULT") !== false){
        return "success";
    }
    if(strpos($actionType, "ADMISSION") !== false){
        return "accent";
    }
    if(strpos($actionType, "DELETE") !== false){
        return "danger";
    }
    return "neutral";
}
}

if(!function_exists('admin_dashboard_excerpt')){
function admin_dashboard_excerpt($text, $limit = 150){
    $text = trim((string)$text);
    if($text === ""){
        return "";
    }
    if(function_exists('mb_strlen') && function_exists('mb_substr')){
        if(mb_strlen($text, "UTF-8") <= $limit){
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit - 1, "UTF-8"))."...";
    }
    if(strlen($text) <= $limit){
        return $text;
    }
    return rtrim(substr($text, 0, $limit - 1))."...";
}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<style>
:root {
    --bg-1: #eef2f7;
    --bg-2: #fff7ed;
    --ink: #1f2937;
    --muted: #64748b;
    --panel: #ffffff;
    --line: #e5e7eb;
    --brand: #0f766e;
    --accent: #b45309;
    --executive-navy: #0f2742;
    --executive-gold: #d59b2d;
    --executive-teal: #0f766e;
    --executive-sky: #0ea5e9;
    --success: #16a34a;
    --warning: #f59e0b;
    --danger: #dc2626;
}

.body-style {
    margin: 0;
    color: var(--ink);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: radial-gradient(circle at 0% 0%, rgba(213, 155, 45, 0.22) 0%, transparent 24%),
                radial-gradient(circle at 100% 0%, rgba(14, 165, 233, 0.18) 0%, transparent 28%),
                linear-gradient(180deg, var(--bg-2), var(--bg-1));
    position: relative;
    min-height: 100vh;
    isolation: isolate;
    overflow-x: hidden;
}

.body-style::before,
.body-style::after {
    content: "";
    position: fixed;
    pointer-events: none;
    z-index: 0;
    border-radius: 999px;
    filter: blur(18px);
    opacity: 0.6;
}

.body-style::before {
    top: 84px;
    left: -8vw;
    width: 32vw;
    height: 32vw;
    min-width: 260px;
    min-height: 260px;
    background: radial-gradient(circle at 35% 35%, rgba(213, 155, 45, 0.3) 0%, rgba(213, 155, 45, 0.14) 36%, transparent 72%);
    animation: dashboardBlobFloat 18s ease-in-out infinite alternate;
}

.body-style::after {
    right: -10vw;
    bottom: 4vh;
    width: 36vw;
    height: 36vw;
    min-width: 300px;
    min-height: 300px;
    background: radial-gradient(circle at 45% 45%, rgba(14, 165, 233, 0.24) 0%, rgba(15, 118, 110, 0.14) 40%, transparent 76%);
    animation: dashboardBlobDrift 24s ease-in-out infinite alternate;
}

.header {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid var(--line);
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    padding: 12px 20px;
    position: relative;
    z-index: 1;
}

.main-platform {
    max-width: 1500px;
    margin: 0 auto;
    padding: 6px 18px 8px;
    box-sizing: border-box;
    overflow-x: clip;
    position: relative;
    z-index: 1;
}

.main-platform > h2 {
    margin: 0 0 14px;
    padding: 16px 18px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background:
        linear-gradient(135deg, rgba(255,255,255,0.12), transparent 34%),
        linear-gradient(135deg, var(--executive-navy) 0%, #155e75 48%, var(--executive-teal) 100%);
    color: #ecfeff;
    box-shadow: 0 16px 38px rgba(8, 47, 73, 0.26);
    text-align: left;
    animation: executiveFadeUp 0.55s ease both;
}

.admin-layout {
    width: 100%;
    border-collapse: separate;
    border-spacing: 12px 0;
    table-layout: fixed;
}

.admin-layout td {
    vertical-align: top;
    min-width: 0;
}

.admin-sidebar-col {
    width: 320px;
}

.admin-sidebar-scroll {
    position: sticky;
    top: 12px;
    max-height: calc(100vh - 118px);
    overflow-y: auto;
    padding: 10px;
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 16px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(248, 250, 252, 0.96) 100%),
        linear-gradient(135deg, rgba(213, 155, 45, 0.1), rgba(14, 165, 233, 0.08));
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
    animation: executiveFadeUp 0.62s ease both;
}

.admin-sidebar-scroll::-webkit-scrollbar {
    width: 8px;
}

.admin-sidebar-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
}

.admin-dashboard-col {
    width: auto;
}

.admin-dashboard-panel {
    min-height: calc(100vh - 190px);
    border-radius: 16px;
    border: 1px solid rgba(15, 39, 66, 0.1);
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.08), transparent 30%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
    min-width: 0;
    animation: executiveFadeUp 0.7s ease both;
}

.form-entry {
    border-radius: 14px;
    border: 1px solid var(--line);
    background: var(--panel);
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    padding: 14px;
    min-width: 0;
}

.dashboard-flex {
    display: grid;
    grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
    gap: 14px;
    margin: 0 0 16px;
    min-width: 0;
}

.chart-container {
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 12px;
    background:
        linear-gradient(135deg, rgba(236, 254, 255, 0.72), transparent 36%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 14px;
    height: 360px;
    min-width: 0;
    overflow: hidden;
}

.chart-container #studentChart {
    display: block;
    width: 100% !important;
    height: 282px !important;
}

.chart-note {
    margin: 10px 0 0;
    color: var(--muted);
    font-size: 0.8rem;
    line-height: 1.45;
    text-align: center;
}

.cards-side {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    align-items: stretch;
    grid-auto-rows: minmax(82px, 1fr);
}

.card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 11px;
    background:
        linear-gradient(135deg, rgba(14, 165, 233, 0.06), transparent 42%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 12px;
    min-height: 82px;
    transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
}

.card::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 5px;
    background: var(--executive-sky);
}

.card:nth-child(3n+1)::before { background: var(--executive-teal); }
.card:nth-child(3n+2)::before { background: var(--executive-sky); }
.card:nth-child(3n+3)::before { background: var(--executive-gold); }

.card:hover {
    transform: translateY(-4px);
    border-color: rgba(14, 165, 233, 0.35);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.13);
}

.card h4 {
    margin: 0 0 7px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--executive-navy);
}

.card p {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
}

.card.total {
    background:
        radial-gradient(circle at top right, rgba(245, 158, 11, 0.28), transparent 34%),
        linear-gradient(135deg, var(--executive-navy), #0e7490 55%, var(--brand));
    border-color: transparent;
    box-shadow: 0 16px 34px rgba(8, 47, 73, 0.18);
}

.card.total h4,
.card.total p {
    color: #ecfeff;
}

.recent-activity-shell {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.9fr);
    gap: 14px;
    margin: 0 0 16px;
    align-items: start;
}

.recent-activity-panel,
.recent-activity-aside {
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 14px;
    background:
        linear-gradient(135deg, rgba(236, 254, 255, 0.5), transparent 38%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}

.recent-activity-panel {
    padding: 14px;
}

.recent-activity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.recent-activity-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #0f766e;
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.recent-activity-header h3 {
    margin: 0;
    color: #0f172a;
    font-size: 1.02rem;
}

.recent-activity-header p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 0.86rem;
    line-height: 1.5;
    max-width: 580px;
}

.recent-activity-open {
    white-space: nowrap;
}

.recent-activity-list {
    display: grid;
    gap: 10px;
}

.recent-activity-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.92);
}

.recent-activity-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    border: 1px solid transparent;
    flex: 0 0 auto;
}

.recent-activity-icon--info {
    background: #e0f2fe;
    color: #0369a1;
    border-color: #bae6fd;
}

.recent-activity-icon--success {
    background: #dcfce7;
    color: #166534;
    border-color: #86efac;
}

.recent-activity-icon--warning {
    background: #fef3c7;
    color: #92400e;
    border-color: #fcd34d;
}

.recent-activity-icon--danger {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fca5a5;
}

.recent-activity-icon--accent {
    background: #ede9fe;
    color: #6d28d9;
    border-color: #c4b5fd;
}

.recent-activity-icon--neutral {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}

.recent-activity-body {
    min-width: 0;
}

.recent-activity-topline {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.recent-activity-topline strong {
    color: #0f172a;
    font-size: 0.95rem;
}

.recent-activity-topline time {
    color: #64748b;
    font-size: 0.78rem;
    white-space: nowrap;
}

.recent-activity-body p {
    margin: 0;
    color: #475569;
    font-size: 0.84rem;
    line-height: 1.55;
}

.recent-activity-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 9px;
}

.recent-activity-meta span {
    display: inline-flex;
    align-items: center;
    padding: 5px 8px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 600;
}

.recent-activity-link {
    align-self: center;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15, 39, 66, 0.1);
    background: #ffffff;
    color: #0f2742;
    text-decoration: none;
    font-size: 0.77rem;
    font-weight: 700;
}

.recent-activity-link:hover {
    border-color: rgba(15, 118, 110, 0.3);
    background: #ecfeff;
}

.recent-activity-empty {
    padding: 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    background: #f8fafc;
    color: #475569;
    font-size: 0.86rem;
    text-align: center;
}

.recent-activity-empty i {
    display: block;
    margin-bottom: 8px;
    color: #0f766e;
    font-size: 1.1rem;
}

.recent-activity-aside {
    padding: 14px;
}

.recent-activity-aside h4 {
    margin: 0 0 6px;
    color: #0f172a;
    font-size: 0.96rem;
}

.recent-activity-aside p {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.5;
}

.recent-activity-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.recent-activity-summary-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.94);
    padding: 12px;
}

.recent-activity-summary-card span {
    display: block;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: 7px;
}

.recent-activity-summary-card strong {
    display: block;
    color: #0f172a;
    font-size: 1.45rem;
    line-height: 1.1;
}

.recent-activity-summary-card small {
    display: block;
    margin-top: 6px;
    color: #475569;
    font-size: 0.8rem;
    line-height: 1.45;
}

.readiness-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.readiness-copy {
    flex: 1 1 320px;
    min-width: 0;
}

.readiness-card p {
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.45;
    color: #334155;
}

.readiness-meta {
    margin-top: 8px;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 600;
}

.readiness-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.readiness-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.readiness-pill-ready {
    background: #dcfce7;
    border-color: #86efac;
    color: #166534;
}

.readiness-pill-not-ready {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}

.readiness-pill-neutral {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #334155;
}

.readiness-pill-warning {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}

.readiness-score {
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 700;
}

.quick-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0;
}

.dashboard-global-search {
    flex: 1 1 430px;
    min-width: min(100%, 360px);
    position: relative;
}

.dashboard-global-search-form {
    margin: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.dashboard-global-search-label {
    display: block;
    margin: 0 0 6px;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    width: 100%;
    text-align: center;
}

.dashboard-global-search-field {
    position: relative;
    width: 100%;
}

.dashboard-global-search-field > i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--executive-teal);
    font-size: 0.96rem;
    pointer-events: none;
}

.dashboard-global-search-input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid rgba(15, 39, 66, 0.14);
    border-radius: 999px;
    padding: 12px 102px 12px 40px;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.92rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.dashboard-global-search-input:focus {
    outline: none;
    border-color: rgba(14, 165, 233, 0.65);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
    background: #f8fafc;
}

.dashboard-global-search-submit {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    border-radius: 999px;
    padding: 9px 16px;
    background: linear-gradient(135deg, var(--executive-navy) 0%, var(--executive-teal) 100%);
    color: #ecfeff;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.dashboard-global-search-submit:hover {
    transform: translateY(-50%) translateY(-1px);
    box-shadow: 0 12px 20px rgba(15, 118, 110, 0.22);
}

.dashboard-global-search-hint {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 0.78rem;
    width: 100%;
    text-align: center;
}

.dashboard-global-search-results {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    right: 0;
    z-index: 30;
    padding: 14px;
    border: 1px solid rgba(15, 39, 66, 0.12);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 24px 40px rgba(15, 23, 42, 0.14);
    backdrop-filter: blur(10px);
    max-height: 520px;
    overflow-y: auto;
}

.dashboard-global-search-results[hidden] {
    display: none !important;
}

.desktop-search-summary {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    padding-bottom: 12px;
    margin-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
}

.desktop-search-summary strong {
    display: block;
    color: #0f172a;
    font-size: 0.98rem;
}

.desktop-search-summary span {
    color: #64748b;
    font-size: 0.8rem;
}

.desktop-search-group + .desktop-search-group {
    margin-top: 14px;
}

.desktop-search-group-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    color: #1e293b;
    font-size: 0.9rem;
    font-weight: 700;
}

.desktop-search-group-title i {
    color: var(--executive-teal);
}

.desktop-search-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.desktop-search-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.desktop-search-card__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #0f766e;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.desktop-search-card__title {
    display: inline-block;
    color: #0f172a;
    font-size: 0.98rem;
    font-weight: 700;
    text-decoration: none;
    line-height: 1.35;
}

.desktop-search-card__title:hover {
    color: var(--executive-teal);
}

.desktop-search-card__meta,
.desktop-search-card__desc {
    color: #475569;
    font-size: 0.83rem;
    line-height: 1.5;
}

.desktop-search-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.desktop-search-card__action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15, 39, 66, 0.08);
    background: #ffffff;
    color: #0f2742;
    font-size: 0.77rem;
    font-weight: 600;
    text-decoration: none;
}

.desktop-search-card__action:hover {
    border-color: rgba(15, 118, 110, 0.24);
    background: #ecfeff;
}

.desktop-search-feedback {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    background: #f8fafc;
    color: #334155;
}

.desktop-search-feedback i {
    margin-top: 2px;
    color: var(--executive-teal);
}

.desktop-search-feedback strong {
    display: block;
    color: #0f172a;
    margin-bottom: 3px;
}

.quick-action-btn {
    text-decoration: none;
    border: 1px solid rgba(15, 39, 66, 0.1);
    background:
        linear-gradient(135deg, rgba(236, 254, 255, 0.55), transparent 42%),
        #fff;
    color: var(--executive-navy);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.84rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
}

.dashboard-status-strip {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0 0 14px;
    padding: 10px 236px 10px 14px;
    min-height: 74px;
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 14px;
    background:
        linear-gradient(135deg, rgba(236, 254, 255, 0.72), transparent 42%),
        #ffffff;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}

.dashboard-status-strip strong {
    color: var(--executive-navy);
}

.dashboard-status-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #374151;
    font-size: 0.92rem;
}

.dashboard-status-label i {
    color: var(--executive-teal);
}

.dashboard-live-clock {
    position: absolute;
    top: 50%;
    right: 14px;
    width: 206px;
    max-width: calc(100% - 28px);
    transform: translateY(-50%);
}

.dashboard-live-clock .xschool-live-clock {
    --xclock-bg:
        linear-gradient(135deg, rgba(236, 254, 255, 0.9), rgba(255, 247, 237, 0.92)),
        #ffffff;
    --xclock-border: rgba(15, 118, 110, 0.16);
    --xclock-ink: #0f172a;
    --xclock-muted: #64748b;
    --xclock-accent: var(--executive-teal);
    --xclock-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
    min-width: 0;
    padding: 10px 12px;
    gap: 4px;
    border-radius: 16px;
}

.dashboard-live-clock .xschool-live-clock__eyebrow {
    font-size: 0.58rem;
    letter-spacing: 0.14em;
}

.dashboard-live-clock .xschool-live-clock__status {
    padding: 3px 7px;
    font-size: 0.6rem;
}

.dashboard-live-clock .xschool-live-clock__time {
    font-size: 1.32rem;
    line-height: 1.05;
}

.dashboard-live-clock .xschool-live-clock__date {
    font-size: 0.76rem;
}

.dashboard-live-clock .xschool-live-clock__zone {
    display: none;
}

@media (max-width: 1260px) {
    .dashboard-status-strip {
        padding-right: 14px;
        padding-top: 84px;
        min-height: 0;
        align-items: flex-start;
    }

    .dashboard-live-clock {
        top: 10px;
        right: 14px;
        transform: none;
    }
}

.quick-action-btn:hover {
    border-color: rgba(15, 118, 110, 0.35);
    background: linear-gradient(135deg, #ecfeff 0%, #fff7ed 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 22px rgba(15, 118, 110, 0.12);
}

.dashboard-shell {
    display: grid;
    grid-template-columns: 210px minmax(0, 1fr);
    gap: 12px;
    margin-bottom: 14px;
    min-width: 0;
}

.dashboard-side-menu {
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
    padding: 10px;
    height: fit-content;
    min-width: 0;
}

.dash-side-btn {
    width: 100%;
    margin-bottom: 8px;
    text-align: left;
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 10px;
    padding: 10px 11px;
    background: #fff;
    color: #0f172a;
    cursor: pointer;
    font-weight: 600;
    transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.dash-side-btn.active {
    background: linear-gradient(135deg, #ecfeff 0%, #fff7ed 100%);
    border-color: #67e8f9;
    color: var(--executive-navy);
    box-shadow: inset 4px 0 0 var(--executive-gold);
}

.dash-side-btn:hover {
    transform: translateX(2px);
    border-color: #67e8f9;
}

.dashboard-main {
    min-width: 0;
}

.dashboard-top-menu {
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 10px;
    margin-bottom: 10px;
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    min-width: 0;
}

.dash-top-btn {
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 10px;
    padding: 9px 12px;
    background: #fff;
    color: #0f172a;
    cursor: pointer;
    font-weight: 700;
    transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
}

.dash-top-btn.active {
    background: linear-gradient(135deg, #fef3c7 0%, #ecfeff 100%);
    border-color: #f59e0b;
}

.dash-top-btn:hover {
    transform: translateY(-2px);
}

.dashboard-section {
    display: none;
}

.dashboard-section.active {
    display: block;
    animation: executiveFadeUp 0.42s ease both;
}

#section-overview.dashboard-section.active {
    animation: none;
}

.perf-panel {
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 12px;
    background:
        linear-gradient(135deg, rgba(213, 155, 45, 0.08), transparent 35%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 12px;
    margin-bottom: 14px;
    min-width: 0;
}

.perf-toolbar {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    align-items: end;
    margin-bottom: 12px;
}

.perf-toolbar label {
    display: block;
    margin-bottom: 5px;
    color: #334155;
    font-weight: 600;
    font-size: 0.82rem;
}

.perf-toolbar select {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--line);
    border-radius: 9px;
    background: #fff;
}

.perf-grid {
    display: grid;
    grid-template-columns: minmax(320px, 1fr) minmax(0, 1.25fr);
    gap: 12px;
    min-width: 0;
}

.perf-chart-wrap {
    border: 1px solid rgba(14, 165, 233, 0.18);
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    min-height: 290px;
    min-width: 0;
}

.perf-table-wrap {
    border: 1px solid rgba(15, 118, 110, 0.16);
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    min-width: 0;
    overflow-x: visible;
}

.pending-list-wrap {
    border: 1px solid rgba(245, 158, 11, 0.26);
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    margin-bottom: 12px;
}

.pending-list {
    margin: 0;
    padding-left: 18px;
    max-height: 170px;
    overflow-y: auto;
}

.pending-list li {
    margin: 0 0 7px;
    color: #334155;
    font-size: 0.85rem;
}

.table-wrap {
    min-width: 0;
    overflow-x: visible;
    border: 1px solid rgba(15, 39, 66, 0.1);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 12px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
    table-layout: fixed;
}

.table caption {
    text-align: left;
    font-weight: 700;
    margin-bottom: 9px;
    color: #0f172a;
}

.table thead th {
    text-align: left;
    font-weight: 700;
    color: #ecfeff;
    border-bottom: 1px solid rgba(15, 39, 66, 0.12);
    padding: 9px 10px;
    background: linear-gradient(135deg, var(--executive-navy) 0%, #155e75 100%);
}

.table td {
    border-bottom: 1px solid #f1f5f9;
    padding: 10px;
    vertical-align: top;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.notification-scroll {
    max-height: 340px;
    overflow-y: auto;
    overflow-x: visible;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    min-width: 0;
}

.notification-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}

.system-change-panel {
    position: relative;
    overflow: hidden;
    padding: 0;
    border: 1px solid #dbeafe;
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.14), transparent 32%),
        linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.system-change-header {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.system-change-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 240px;
}

.system-change-icon {
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    border-radius: 15px;
    background: #ecfeff;
    color: #0e7490;
    box-shadow: inset 0 0 0 1px #a5f3fc;
}

.system-change-title h3 {
    margin: 0;
    color: #0f172a;
    font-size: 1.1rem;
}

.system-change-title p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 0.84rem;
}

.system-change-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.system-change-count {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border: 1px solid #fecaca;
    border-radius: 999px;
    background: #fff1f2;
    color: #991b1b;
    font-size: 0.82rem;
    font-weight: 800;
}

.system-change-count strong {
    min-width: 24px;
    padding: 2px 7px;
    border-radius: 999px;
    background: #b91c1c;
    color: #ffffff;
    text-align: center;
}

.system-change-count.has-unread strong {
    animation: executivePulse 1.9s ease-in-out infinite;
}

.system-change-mark-form {
    margin: 0;
}

.system-change-mark-btn {
    min-height: 40px;
    padding: 9px 13px;
    border-radius: 999px;
    border-color: #bae6fd;
    background: #f0f9ff;
    color: #075985;
    font-weight: 800;
}

.system-change-mark-btn:hover {
    border-color: #38bdf8;
    background: #e0f2fe;
}

.system-change-table-wrap {
    margin: 14px;
    max-height: 390px;
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #ffffff;
}

.system-change-table {
    min-width: 900px;
}

.system-change-table caption {
    padding: 14px 14px 4px;
    margin: 0;
    color: #0f172a;
    font-size: 1rem;
}

.system-change-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #0f766e;
    color: #ecfeff;
}

.system-change-row-unread td {
    background: #fffbeb;
}

.system-change-row-read td {
    background: #ffffff;
}

.system-change-actor {
    font-weight: 800;
    color: #0f172a;
}

.system-change-actor small {
    display: block;
    margin-top: 3px;
    color: #64748b;
    font-weight: 700;
}

.system-change-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 74px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 900;
    text-transform: uppercase;
}

.system-change-pill-read {
    background: #ecfdf5;
    color: #047857;
}

.system-change-pill-unread {
    background: #fef3c7;
    color: #92400e;
}

.system-change-role {
    background: #eef2ff;
    color: #3730a3;
}

.system-change-action-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 8px;
    padding: 7px 11px;
    border-radius: 999px;
    background: #ecfeff;
    color: #0f766e;
    font-weight: 900;
    text-decoration: none;
}

.system-change-action-link:hover {
    background: #ccfbf1;
    color: #115e59;
}

.system-change-empty {
    padding: 28px !important;
    color: #64748b !important;
    text-align: center;
}

.admin-danger-zone {
    margin-top: 14px;
    border: 1px solid #fecaca;
    border-radius: 16px;
    background:
        linear-gradient(135deg, rgba(254, 226, 226, 0.78), transparent 45%),
        #ffffff;
    overflow: hidden;
}

.admin-danger-zone summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 13px 15px;
    color: #7f1d1d;
    font-weight: 900;
    cursor: pointer;
    list-style: none;
}

.admin-danger-zone summary::-webkit-details-marker {
    display: none;
}

.admin-danger-zone summary::after {
    content: "Open";
    padding: 4px 9px;
    border-radius: 999px;
    background: #fee2e2;
    color: #991b1b;
    font-size: 0.72rem;
    text-transform: uppercase;
}

.admin-danger-zone[open] summary::after {
    content: "Close";
}

.admin-danger-zone-body {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    padding: 0 15px 15px;
}

.admin-danger-zone-body p {
    flex: 1 1 320px;
    margin: 0;
    color: #64748b;
    font-size: 0.86rem;
    line-height: 1.45;
}

.admin-danger-btn {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 14px;
    border-radius: 999px;
    background: linear-gradient(135deg, #991b1b 0%, #dc2626 100%);
    color: #ffffff !important;
    font-size: 0.82rem;
    font-weight: 900;
    text-decoration: none;
    box-shadow: 0 10px 22px rgba(185, 28, 28, 0.18);
}

@keyframes executiveFadeUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes executivePulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(185, 28, 28, 0.22);
    }
    50% {
        transform: scale(1.04);
        box-shadow: 0 0 0 7px rgba(185, 28, 28, 0);
    }
}

@keyframes dashboardBlobFloat {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        transform: translate3d(5vw, 3vh, 0) scale(1.08);
    }
}

@keyframes dashboardBlobDrift {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        transform: translate3d(-6vw, -4vh, 0) scale(1.1);
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.001ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: 0.001ms !important;
    }
}

#myBtn {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    border-radius: 999px;
    border: 0;
    background: var(--accent);
    color: #fff;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(120, 53, 15, 0.35);
}

@media (max-width: 1200px) {
    .main-platform {
        padding-left: 14px;
        padding-right: 14px;
    }

    .cards-side {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .perf-grid {
        grid-template-columns: 1fr;
    }
    .perf-toolbar {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .recent-activity-shell {
        grid-template-columns: 1fr;
    }
    .dashboard-shell {
        grid-template-columns: 1fr;
    }
    .dashboard-top-menu {
        justify-content: flex-start;
    }
    .desktop-search-grid {
        grid-template-columns: 1fr;
    }

    .table thead th,
    .table td {
        padding: 9px 8px;
    }
}

@media (max-width: 980px) {
    .admin-layout,
    .admin-layout tbody,
    .admin-layout tr,
    .admin-layout td {
        display: block;
        width: 100%;
    }
    .admin-layout {
        border-spacing: 0;
    }
    .admin-sidebar-col {
        width: 100%;
    }
    .admin-sidebar-scroll {
        position: static;
        max-height: none;
        margin-bottom: 14px;
    }
    .admin-dashboard-panel {
        min-height: 0;
    }
    .dashboard-flex {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .cards-side {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 620px) {
    .main-platform {
        padding-left: 10px;
        padding-right: 10px;
    }

    .dashboard-global-search {
        min-width: 100%;
    }
    .dashboard-global-search-input {
        padding-right: 94px;
    }
    .dashboard-global-search-results {
        left: -2px;
        right: -2px;
        padding: 12px;
    }

    .cards-side {
        grid-template-columns: 1fr;
    }
    .recent-activity-item {
        grid-template-columns: 1fr;
    }
    .recent-activity-link {
        align-self: flex-start;
    }
    .recent-activity-summary-grid {
        grid-template-columns: 1fr;
    }
    .perf-toolbar {
        grid-template-columns: 1fr;
    }
    .system-change-header {
        padding: 14px;
    }
    .system-change-title,
    .system-change-actions,
    .system-change-count,
    .system-change-mark-form,
    .system-change-mark-btn {
        width: 100%;
    }
    .system-change-actions {
        justify-content: stretch;
    }
    .system-change-count,
    .system-change-mark-btn {
        justify-content: center;
    }
    .system-change-table-wrap {
        margin: 10px;
    }
    .system-change-table {
        min-width: 760px;
    }
}
</style>
</head>

<body class="body-style">
    <div class="header">
        <?php
        include("menu.php");
        ?>
    </div>

    <div class="main-platform" align="center">
        <h2>Administrator Dashboard</h2>
        <table border="0" width="100%" class="admin-layout">
            <tr>
                <td width="25%" valign="top" class="admin-sidebar-col">
                    <div class="admin-sidebar-scroll">
                        <?php
                        include("welcome.php");
                        include("menuboard.php");
                        ?>
                    </div>
                </td>
                <td width="75%" valign="top" class="admin-dashboard-col">
                    <div class="form-entry admin-dashboard-panel">
                        <?php
                        include("dbstring.php");
                        include("audit_notifications.php");
                        ensureSystemChangeLogTable($con);
                        mysqli_query($con, "DELETE FROM tblsystemchangelog WHERE status='read' AND datetimeentry < (NOW() - INTERVAL 48 HOUR)");

                        $normalizedResidenceSql = "
                          CASE
                            WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('DAY','D') THEN 'Day'
                            WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('BOARDING','BOARDER','B') THEN 'Boarding'
                            ELSE ''
                          END
                        ";

                        /* 1) Query: counts by (gender x residence) */
                        $sql = "
                          SELECT
                            CASE
                              WHEN UPPER(su.gender) IN ('M','MALE','BOY','B') THEN 'Male'
                              WHEN UPPER(su.gender) IN ('F','FEMALE','GIRL','G') THEN 'Female'
                              ELSE 'Other'
                            END AS gnorm,
                            ".$normalizedResidenceSql." AS residence_group,
                            COUNT(DISTINCT su.userid) AS cnt
                          FROM tblsystemuser su
                          INNER JOIN tblclass cl ON cl.userid=su.userid
                          WHERE su.systemtype='Student'
                            AND su.status='active'
                            AND cl.status='active'
                          GROUP BY gnorm, residence_group
                        ";

                        $res = mysqli_query($con, $sql);

                        $statsSql = "
                          SELECT
                            COUNT(DISTINCT su.userid) AS total_students,
                            COUNT(DISTINCT CASE WHEN ".$normalizedResidenceSql." = '' THEN su.userid END) AS no_status_students
                          FROM tblsystemuser su
                          INNER JOIN tblclass cl ON cl.userid=su.userid
                          WHERE su.systemtype='Student'
                            AND su.status='active'
                            AND cl.status='active'
                        ";
                        $statsRes = mysqli_query($con, $statsSql);

                        /* 2) Seed defaults so missing combos show 0 */
                        $counts = [
                          'Male'   => ['Day' => 0, 'Boarding' => 0],
                          'Female' => ['Day' => 0, 'Boarding' => 0],
                        ];

                        if ($res) {
                          while ($row = mysqli_fetch_assoc($res)) {
                            $g = $row['gnorm'];
                            $r = $row['residence_group'];
                            if (isset($counts[$g][$r])) {
                              $counts[$g][$r] = (int)$row['cnt'];
                            }
                          }
                        }

                        $students_total = 0;
                        $students_no_status = 0;
                        if ($statsRes && ($statsRow = mysqli_fetch_assoc($statsRes))) {
                            $students_total = (int)$statsRow['total_students'];
                            $students_no_status = (int)$statsRow['no_status_students'];
                        }

                        /* 3) Convenient vars + totals */
                        $boys_day        = $counts['Male']['Day'];
                        $boys_boarding   = $counts['Male']['Boarding'];
                        $girls_day       = $counts['Female']['Day'];
                        $girls_boarding  = $counts['Female']['Boarding'];

                        $boys_total      = $boys_day + $boys_boarding;
                        $girls_total     = $girls_day + $girls_boarding;
                        $day_total       = $boys_day + $girls_day;
                        $boarding_total  = $boys_boarding + $girls_boarding;
                        $students_with_status_total = $boys_total + $girls_total;
                        $grand_total     = $students_total;

                        $activeBatchNames = array();
                        $_SQL_ACTIVE_BATCH = mysqli_query($con, "SELECT batch FROM tblbatch WHERE status='active' ORDER BY datetimeentry DESC");
                        if ($_SQL_ACTIVE_BATCH && mysqli_num_rows($_SQL_ACTIVE_BATCH) > 0) {
                            while ($row_ab = mysqli_fetch_array($_SQL_ACTIVE_BATCH, MYSQLI_ASSOC)) {
                                $activeBatchNames[] = $row_ab['batch'];
                            }
                        }
                        $activeBatchLabel = count($activeBatchNames) > 0 ? implode(", ", $activeBatchNames) : "No active semester";

                        $_ClassFilter = isset($_GET['perf_class']) ? trim($_GET['perf_class']) : '';
                        $_BatchFilter = isset($_GET['perf_batch']) ? trim($_GET['perf_batch']) : '';
                        $_YearFilter = isset($_GET['perf_year']) ? trim($_GET['perf_year']) : '';
                        $_TermFilter = isset($_GET['perf_term']) ? trim($_GET['perf_term']) : '';
                        $_ClassFilterSafe = mysqli_real_escape_string($con, $_ClassFilter);
                        $_BatchFilterSafe = mysqli_real_escape_string($con, $_BatchFilter);
                        $_YearFilterSafe = mysqli_real_escape_string($con, $_YearFilter);
                        $_TermFilterSafe = mysqli_real_escape_string($con, $_TermFilter);

                        $_PerfClassName = "";
                        $_PerfBatchName = "";
                        $_PerfScopeParts = array();
                        if($_ClassFilterSafe !== ''){
                            $_SQL_SCOPE_CLASS = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassFilterSafe' LIMIT 1");
                            if($_SQL_SCOPE_CLASS && $row_scope_class = mysqli_fetch_array($_SQL_SCOPE_CLASS, MYSQLI_ASSOC)){
                                $_PerfClassName = trim((string)$row_scope_class['class_name']);
                                if($_PerfClassName !== ''){
                                    $_PerfScopeParts[] = $_PerfClassName;
                                }
                            }
                        }
                        if($_BatchFilterSafe !== ''){
                            $_SQL_SCOPE_BATCH = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$_BatchFilterSafe' LIMIT 1");
                            if($_SQL_SCOPE_BATCH && $row_scope_batch = mysqli_fetch_array($_SQL_SCOPE_BATCH, MYSQLI_ASSOC)){
                                $_PerfBatchName = trim((string)$row_scope_batch['batch']);
                                if($_PerfBatchName !== ''){
                                    $_PerfScopeParts[] = $_PerfBatchName;
                                }
                            }
                        }
                        if($_YearFilter !== ''){
                            $_PerfScopeParts[] = "AY ".$_YearFilter;
                        }
                        if($_TermFilter !== ''){
                            $_PerfScopeParts[] = "Semester ".$_TermFilter;
                        }
                        $_PerfScopeLabel = count($_PerfScopeParts) > 0 ? implode(" | ", $_PerfScopeParts) : "All classes, batches, academic years, and semesters";
                        $_PerfChartTitle = "Average vs Pass Rate by Subject";
                        if(count($_PerfScopeParts) > 0){
                            $_PerfChartTitle .= " - ".$_PerfScopeLabel;
                        }

                        $_ClassOptions = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
                        $_BatchOptions = mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
                        $_YearOptions = mysqli_query($con, "SELECT DISTINCT YEAR(datetimeentry) AS assignment_year FROM tblsubjectassignment WHERE datetimeentry IS NOT NULL ORDER BY assignment_year DESC");
                        $_TermOptions = mysqli_query($con, "SELECT DISTINCT termname FROM tblsubjectassignment ORDER BY termname ASC");

                        $_PerfWhere = " WHERE mk.status='active' ";
                        if($_ClassFilterSafe !== ''){
                            $_PerfWhere .= " AND sa.classid='$_ClassFilterSafe' ";
                        }
                        if($_BatchFilterSafe !== ''){
                            $_PerfWhere .= " AND sa.batchid='$_BatchFilterSafe' ";
                        }
                        if($_YearFilterSafe !== ''){
                            $_PerfWhere .= " AND YEAR(sa.datetimeentry)='$_YearFilterSafe' ";
                        }
                        if($_TermFilterSafe !== ''){
                            $_PerfWhere .= " AND sa.termname='$_TermFilterSafe' ";
                        }

                        $_AssignWhere = " WHERE 1=1 ";
                        if($_ClassFilterSafe !== ''){
                            $_AssignWhere .= " AND sa.classid='$_ClassFilterSafe' ";
                        }
                        if($_BatchFilterSafe !== ''){
                            $_AssignWhere .= " AND sa.batchid='$_BatchFilterSafe' ";
                        }
                        if($_YearFilterSafe !== ''){
                            $_AssignWhere .= " AND YEAR(sa.datetimeentry)='$_YearFilterSafe' ";
                        }
                        if($_TermFilterSafe !== ''){
                            $_AssignWhere .= " AND sa.termname='$_TermFilterSafe' ";
                        }

                        $_TotalAssignedSubjects = 0;
                        $_SubmittedSubjects = 0;
                        $_PendingSubjects = 0;
                        $_SQL_TOTAL_ASSIGNED = mysqli_query($con, "SELECT COUNT(DISTINCT sa.assignmentid) AS total_assigned
                            FROM tblsubjectassignment sa
                            $_AssignWhere");
                        if($_SQL_TOTAL_ASSIGNED && $row_total_assigned = mysqli_fetch_array($_SQL_TOTAL_ASSIGNED, MYSQLI_ASSOC)){
                            $_TotalAssignedSubjects = (int)$row_total_assigned['total_assigned'];
                        }

                        $_SQL_SUBMITTED = mysqli_query($con, "SELECT COUNT(DISTINCT sa.assignmentid) AS submitted_total
                            FROM tblsubjectassignment sa
                            $_AssignWhere
                            AND EXISTS (
                                SELECT 1 FROM tblmark mk
                                WHERE mk.assignmentid=sa.assignmentid
                                  AND mk.status='active'
                            )");
                        if($_SQL_SUBMITTED && $row_submitted = mysqli_fetch_array($_SQL_SUBMITTED, MYSQLI_ASSOC)){
                            $_SubmittedSubjects = (int)$row_submitted['submitted_total'];
                        }
                        $_PendingSubjects = max(0, $_TotalAssignedSubjects - $_SubmittedSubjects);

                        $_PendingRows = array();
                            $_SQL_PENDING_LIST = mysqli_query($con, "SELECT
                                sa.assignmentid,
                                sa.termname,
                                YEAR(sa.datetimeentry) AS assignment_year,
                                ce.class_name,
                                bch.batch,
                                sub.subject,
                                CONCAT(su.firstname,' ',su.othernames,' ',su.surname,' (',su.userid,')') AS teacher_name
                            FROM tblsubjectassignment sa
                            INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
                            INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
                            INNER JOIN tblclassentry ce ON sa.classid = ce.class_entryid
                            INNER JOIN tblbatch bch ON sa.batchid = bch.batchid
                            INNER JOIN tblsystemuser su ON sa.userid = su.userid
                            $_AssignWhere
                            AND NOT EXISTS (
                                SELECT 1 FROM tblmark mk
                                WHERE mk.assignmentid=sa.assignmentid
                                  AND mk.status='active'
                            )
                            ORDER BY ce.class_name ASC, sa.termname ASC, sub.subject ASC
                            LIMIT 100");
                        if($_SQL_PENDING_LIST && mysqli_num_rows($_SQL_PENDING_LIST)>0){
                            while($row_pending = mysqli_fetch_array($_SQL_PENDING_LIST, MYSQLI_ASSOC)){
                                $_PendingRows[] = $row_pending;
                            }
                        }

                            $_HasReadinessScope = ($_ClassFilterSafe !== '' && $_BatchFilterSafe !== '' && $_TermFilterSafe !== '');
                            $_ReadinessStatusLabel = "Select Scope";
                            $_ReadinessPillClass = "readiness-pill-neutral";
                            $_ReadinessDetail = "Select class, batch, academic year, and semester to evaluate whether the full class result set is ready.";
                            $_ReadinessMeta = "This badge uses complete student-subject coverage, not just whether a subject has started receiving scores.";
                            $_ReadinessScore = "";
                        $_ReadinessCounts = array(
                            'expected_rows' => 0,
                            'complete_rows' => 0,
                            'missing_class_rows' => 0,
                            'missing_exam_rows' => 0,
                            'missing_both_rows' => 0,
                            'duplicate_rows' => 0
                        );

                        if($_HasReadinessScope){
                            $_ReadyClassName = $_ClassFilter;
                            $_ReadyBatchName = $_BatchFilter;

                            $_SQL_READY_CLASS = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassFilterSafe' LIMIT 1");
                            if($_SQL_READY_CLASS && $row_ready_class = mysqli_fetch_array($_SQL_READY_CLASS, MYSQLI_ASSOC)){
                                $_ReadyClassName = $row_ready_class['class_name'];
                            }

                            $_SQL_READY_BATCH = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$_BatchFilterSafe' LIMIT 1");
                            if($_SQL_READY_BATCH && $row_ready_batch = mysqli_fetch_array($_SQL_READY_BATCH, MYSQLI_ASSOC)){
                                $_ReadyBatchName = $row_ready_batch['batch'];
                            }

                            $_ReadinessMeta = trim($_ReadyClassName." / ".$_ReadyBatchName." / ".($_YearFilter !== '' ? $_YearFilter." / " : "")."Semester ".$_TermFilter, " /");

                            $_SQL_CLASS_READY = mysqli_query($con, "SELECT
                                    COUNT(*) AS expected_rows,
                                    SUM(CASE WHEN ready.class_score_rows = 1 AND ready.exam_score_rows = 1 THEN 1 ELSE 0 END) AS complete_rows,
                                    SUM(CASE WHEN ready.class_score_rows = 0 AND ready.exam_score_rows > 0 THEN 1 ELSE 0 END) AS missing_class_rows,
                                    SUM(CASE WHEN ready.class_score_rows > 0 AND ready.exam_score_rows = 0 THEN 1 ELSE 0 END) AS missing_exam_rows,
                                    SUM(CASE WHEN ready.class_score_rows = 0 AND ready.exam_score_rows = 0 THEN 1 ELSE 0 END) AS missing_both_rows,
                                    SUM(CASE WHEN ready.class_score_rows > 1 OR ready.exam_score_rows > 1 THEN 1 ELSE 0 END) AS duplicate_rows
                                FROM (
                                    SELECT
                                        tr.userid,
                                        sc.subjectid,
                                        SUM(CASE WHEN mk.status='active' AND mk.testtype='Class Score' THEN 1 ELSE 0 END) AS class_score_rows,
                                        SUM(CASE WHEN mk.status='active' AND mk.testtype='Exam Score' THEN 1 ELSE 0 END) AS exam_score_rows
                                    FROM tbltermregistry tr
                                    INNER JOIN tblsystemuser stu ON stu.userid = tr.userid
                                    INNER JOIN tblsubjectassignment sa ON sa.classid = tr.class_entryid AND sa.batchid = tr.batchid AND sa.termname = tr.termname
                                    INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
                                    LEFT JOIN tblmark mk ON mk.assignmentid = sa.assignmentid AND mk.userid = tr.userid
                                    WHERE tr.class_entryid='$_ClassFilterSafe'
                                      AND tr.batchid='$_BatchFilterSafe'
                                      AND tr.termname='$_TermFilterSafe'
                                      ".($_YearFilterSafe !== '' ? " AND YEAR(sa.datetimeentry)='$_YearFilterSafe' " : "")."
                                      AND stu.systemtype='Student'
                                    GROUP BY tr.userid, sc.subjectid
                                ) ready");

                            if($_SQL_CLASS_READY && $row_ready = mysqli_fetch_array($_SQL_CLASS_READY, MYSQLI_ASSOC)){
                                $_ReadinessCounts['expected_rows'] = (int)$row_ready['expected_rows'];
                                $_ReadinessCounts['complete_rows'] = (int)$row_ready['complete_rows'];
                                $_ReadinessCounts['missing_class_rows'] = (int)$row_ready['missing_class_rows'];
                                $_ReadinessCounts['missing_exam_rows'] = (int)$row_ready['missing_exam_rows'];
                                $_ReadinessCounts['missing_both_rows'] = (int)$row_ready['missing_both_rows'];
                                $_ReadinessCounts['duplicate_rows'] = (int)$row_ready['duplicate_rows'];
                            }

                            $_ExpectedReadinessRows = $_ReadinessCounts['expected_rows'];
                            $_CompleteReadinessRows = $_ReadinessCounts['complete_rows'];
                            $_MissingClassRows = $_ReadinessCounts['missing_class_rows'];
                            $_MissingExamRows = $_ReadinessCounts['missing_exam_rows'];
                            $_MissingBothRows = $_ReadinessCounts['missing_both_rows'];
                            $_DuplicateReadinessRows = $_ReadinessCounts['duplicate_rows'];

                            if($_ExpectedReadinessRows <= 0){
                                $_ReadinessStatusLabel = "No Data";
                                $_ReadinessPillClass = "readiness-pill-warning";
                                $_ReadinessDetail = "No registered student-subject rows were found for this class scope yet, so readiness cannot be confirmed.";
                            } else {
                                $_ReadinessCompletionPct = round(($_CompleteReadinessRows * 100) / $_ExpectedReadinessRows, 2);
                                $_ReadinessScore = number_format($_CompleteReadinessRows)."/".number_format($_ExpectedReadinessRows)." Complete (".number_format($_ReadinessCompletionPct, 2)."%)";

                                if($_CompleteReadinessRows === $_ExpectedReadinessRows &&
                                   $_MissingClassRows === 0 &&
                                   $_MissingExamRows === 0 &&
                                   $_MissingBothRows === 0 &&
                                   $_DuplicateReadinessRows === 0){
                                    $_ReadinessStatusLabel = "Ready";
                                    $_ReadinessPillClass = "readiness-pill-ready";
                                    $_ReadinessDetail = "All expected entries for this class currently have one class score and one exam score.";
                                } else {
                                    $_ReadinessStatusLabel = "Not Ready";
                                    $_ReadinessPillClass = "readiness-pill-not-ready";
                                    $_ReadinessDetail = number_format($_CompleteReadinessRows)." of ".number_format($_ExpectedReadinessRows)." expected entries are complete. Missing Class: ".number_format($_MissingClassRows)." | Missing Exam: ".number_format($_MissingExamRows)." | Missing Both: ".number_format($_MissingBothRows)." | Duplicates: ".number_format($_DuplicateReadinessRows).".";
                                }
                            }
                        }

                        $_SQL_SUBJECT_PERF = mysqli_query($con, "SELECT
                                sub.subjectid,
                                sub.subject,
                                COUNT(*) AS entries_count,
                                ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
                                ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate,
                                ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 80 THEN 100 ELSE 0 END),2) AS excellence_rate
                            FROM tblmark mk
                            INNER JOIN tblsubjectassignment sa ON sa.assignmentid = mk.assignmentid
                            INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
                            INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
                            $_PerfWhere
                            GROUP BY sub.subjectid, sub.subject
                            ORDER BY avg_percent DESC, sub.subject ASC");

                        $_PerfLabels = array();
                        $_PerfAvg = array();
                        $_PerfPass = array();
                        $_PerfRows = array();
                        $_OverallAvg = 0;
                        $_OverallPass = 0;
                        $_TotalSubjects = 0;
                        $_BestSubject = "N/A";
                        $_BestSubjectScore = 0;

                        if($_SQL_SUBJECT_PERF && mysqli_num_rows($_SQL_SUBJECT_PERF)>0){
                            while($row_perf = mysqli_fetch_array($_SQL_SUBJECT_PERF, MYSQLI_ASSOC)){
                                $_PerfRows[] = $row_perf;
                                $_PerfLabels[] = $row_perf['subject'];
                                $_PerfAvg[] = (float)$row_perf['avg_percent'];
                                $_PerfPass[] = (float)$row_perf['pass_rate'];
                                $_OverallAvg += (float)$row_perf['avg_percent'];
                                $_OverallPass += (float)$row_perf['pass_rate'];
                            }
                            $_TotalSubjects = count($_PerfRows);
                            if($_TotalSubjects>0){
                                $_OverallAvg = round($_OverallAvg / $_TotalSubjects, 2);
                                $_OverallPass = round($_OverallPass / $_TotalSubjects, 2);
                                $_BestSubject = $_PerfRows[0]['subject'];
                                $_BestSubjectScore = (float)$_PerfRows[0]['avg_percent'];
                            }
                        }

                        $_UnreadChangeCount = 0;
                        $_SQL_UNREAD = mysqli_query($con, "SELECT COUNT(*) AS total_unread
                            FROM tblsystemchangelog
                            WHERE status='unread'
                              AND actor_type IN ('Teacher','Student')");
                        if($_SQL_UNREAD && $row_unread = mysqli_fetch_array($_SQL_UNREAD, MYSQLI_ASSOC)){
                            $_UnreadChangeCount = (int)$row_unread['total_unread'];
                        }

                        $_RecentActivities = array();
                        $_RecentActivityFeedCount = 0;
                        $_TodayStudentRegistrations = 0;
                        $_TodayTeacherRegistrations = 0;
                        $_WeekRegistrations = 0;

                        $_SQL_RECENT_REG_COUNTS = mysqli_query($con, "SELECT
                            SUM(CASE WHEN systemtype='Student' AND DATE(registereddatetime)=CURDATE() THEN 1 ELSE 0 END) AS today_students,
                            SUM(CASE WHEN systemtype='Teacher' AND DATE(registereddatetime)=CURDATE() THEN 1 ELSE 0 END) AS today_teachers,
                            SUM(CASE WHEN systemtype IN ('Student','Teacher') AND YEARWEEK(registereddatetime,1)=YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END) AS week_total
                            FROM tblsystemuser
                            WHERE status='active'");
                        if($_SQL_RECENT_REG_COUNTS && $row_reg_counts = mysqli_fetch_array($_SQL_RECENT_REG_COUNTS, MYSQLI_ASSOC)){
                            $_TodayStudentRegistrations = (int)$row_reg_counts['today_students'];
                            $_TodayTeacherRegistrations = (int)$row_reg_counts['today_teachers'];
                            $_WeekRegistrations = (int)$row_reg_counts['week_total'];
                        }

                        $_SQL_RECENT_LOGS = mysqli_query($con, "SELECT
                                logid,
                                actor_userid,
                                actor_name,
                                actor_type,
                                action_type,
                                target_userid,
                                details,
                                page_name,
                                datetimeentry,
                                status
                            FROM tblsystemchangelog
                            ORDER BY datetimeentry DESC
                            LIMIT 12");
                        if($_SQL_RECENT_LOGS && mysqli_num_rows($_SQL_RECENT_LOGS) > 0){
                            while($row_log_activity = mysqli_fetch_array($_SQL_RECENT_LOGS, MYSQLI_ASSOC)){
                                $_When = trim((string)$row_log_activity['datetimeentry']);
                                $_Stamp = strtotime($_When);
                                if($_Stamp === false){
                                    continue;
                                }
                                $_TargetUserId = trim((string)$row_log_activity['target_userid']);
                                $_PageName = trim((string)$row_log_activity['page_name']);
                                $_ActionType = trim((string)$row_log_activity['action_type']);
                                $_Link = '';
                                $_LinkLabel = '';
                                if(strtoupper($_ActionType) === 'ONLINE_ADMISSION_SUBMITTED' && $_TargetUserId !== ''){
                                    $_Link = 'online-admission-admin.php?edit_application='.urlencode($_TargetUserId).'#edit-application';
                                    $_LinkLabel = 'Review Admission';
                                } elseif(strtoupper($_ActionType) === 'ONLINE_ADMISSION_HELP_REQUEST'){
                                    $_Link = 'online-admission-admin.php#help-requests';
                                    $_LinkLabel = 'Open Help Requests';
                                } elseif($_TargetUserId !== ''){
                                    $_Link = 'register_edit.php?edit_user='.urlencode($_TargetUserId);
                                    $_LinkLabel = 'Open Record';
                                } elseif($_PageName !== '' && preg_match('/^[A-Za-z0-9._-]+\.php$/', $_PageName) && is_file(__DIR__.DIRECTORY_SEPARATOR.$_PageName)){
                                    $_Link = $_PageName;
                                    $_LinkLabel = 'Open Page';
                                }

                                $_ActorName = trim((string)$row_log_activity['actor_name']);
                                $_ActorUserId = trim((string)$row_log_activity['actor_userid']);
                                $_ActorLabel = $_ActorName !== '' ? $_ActorName : ($_ActorUserId !== '' ? $_ActorUserId : 'System');
                                $_RecentActivities[] = array(
                                    'timestamp' => $_Stamp,
                                    'datetime' => $_When,
                                    'title' => admin_dashboard_action_label($_ActionType),
                                    'description' => admin_dashboard_excerpt(trim((string)$row_log_activity['details']) !== '' ? trim((string)$row_log_activity['details']) : admin_dashboard_action_label($_ActionType).' was recorded.'),
                                    'meta' => array_filter(array(
                                        trim((string)$row_log_activity['actor_type']) !== '' ? trim((string)$row_log_activity['actor_type']) : 'System',
                                        $_ActorLabel,
                                        $_PageName !== '' ? $_PageName : '',
                                        trim((string)$row_log_activity['status']) !== '' ? ucfirst(trim((string)$row_log_activity['status'])) : ''
                                    )),
                                    'icon' => admin_dashboard_activity_icon($_ActionType, 'log'),
                                    'tone' => admin_dashboard_activity_tone($_ActionType, 'log'),
                                    'link' => $_Link,
                                    'link_label' => $_LinkLabel
                                );
                            }
                        }

                        $_SQL_RECENT_REG = mysqli_query($con, "SELECT
                                userid,
                                firstname,
                                othernames,
                                surname,
                                systemtype,
                                registereddatetime
                            FROM tblsystemuser
                            WHERE status='active'
                              AND systemtype IN ('Student','Teacher')
                              AND registereddatetime IS NOT NULL
                            ORDER BY registereddatetime DESC
                            LIMIT 10");
                        if($_SQL_RECENT_REG && mysqli_num_rows($_SQL_RECENT_REG) > 0){
                            while($row_reg_activity = mysqli_fetch_array($_SQL_RECENT_REG, MYSQLI_ASSOC)){
                                $_When = trim((string)$row_reg_activity['registereddatetime']);
                                $_Stamp = strtotime($_When);
                                if($_Stamp === false){
                                    continue;
                                }
                                $_Role = trim((string)$row_reg_activity['systemtype']) === 'Teacher' ? 'Teacher' : 'Student';
                                $_FullName = trim((string)$row_reg_activity['firstname'].' '.$row_reg_activity['othernames'].' '.$row_reg_activity['surname']);
                                $_RecentActivities[] = array(
                                    'timestamp' => $_Stamp,
                                    'datetime' => $_When,
                                    'title' => 'New '.$_Role.' Registered',
                                    'description' => admin_dashboard_excerpt(($_FullName !== '' ? $_FullName : (string)$row_reg_activity['userid']).' ('.trim((string)$row_reg_activity['userid']).')'),
                                    'meta' => array($_Role, 'Registration'),
                                    'icon' => admin_dashboard_activity_icon($_Role, 'registration'),
                                    'tone' => admin_dashboard_activity_tone($_Role, 'registration'),
                                    'link' => 'register_edit.php?edit_user='.urlencode(trim((string)$row_reg_activity['userid'])),
                                    'link_label' => 'Open Record'
                                );
                            }
                        }

                        if(count($_RecentActivities) > 1){
                            usort($_RecentActivities, function($left, $right){
                                return ((int)$right['timestamp']) <=> ((int)$left['timestamp']);
                            });
                        }
                        if(count($_RecentActivities) > 10){
                            $_RecentActivities = array_slice($_RecentActivities, 0, 10);
                        }
                        $_RecentActivityFeedCount = count($_RecentActivities);
                        ?>

                        <div class="dashboard-status-strip">
                            <div class="dashboard-status-label">
                                <i class="fa fa-calendar-check-o"></i>
                                <span>Active Semesters: <strong><?php echo $activeBatchLabel; ?></strong></span>
                            </div>
                            <div class="dashboard-live-clock">
                                <div class="xschool-live-clock" data-live-clock>
                                    <div class="xschool-live-clock__top">
                                        <span class="xschool-live-clock__eyebrow">Live Date &amp; Time</span>
                                        <span class="xschool-live-clock__status"><i class="fa fa-circle"></i> Live</span>
                                    </div>
                                    <div class="xschool-live-clock__time" data-live-clock-time>--:--:--</div>
                                    <div class="xschool-live-clock__date" data-live-clock-date>Loading current date</div>
                                    <div class="xschool-live-clock__zone" data-live-clock-zone>Local time</div>
                                </div>
                            </div>
                            <div class="dashboard-global-search" data-desktop-search>
                                <form class="dashboard-global-search-form" id="dashboard-global-search-form" autocomplete="off">
                                    <label class="dashboard-global-search-label" for="dashboard-global-search-input">Desktop Search</label>
                                    <div class="dashboard-global-search-field">
                                        <i class="fa fa-search"></i>
                                        <input
                                            class="dashboard-global-search-input"
                                            type="search"
                                            id="dashboard-global-search-input"
                                            name="dashboard_global_search"
                                            placeholder="Search students, staff, classes, batches, or tools"
                                            aria-label="Search students, staff, classes, batches, or tools"
                                        >
                                        <button type="submit" class="dashboard-global-search-submit">Search</button>
                                    </div>
                                    <p class="dashboard-global-search-hint">Search students, staff, classes, batches, tools, and modules from one place.</p>
                                </form>
                                <div class="dashboard-global-search-results" id="dashboard-global-search-results" hidden></div>
                            </div>
                            <div class="quick-actions" role="region" aria-label="Academic actions">
                                <a class="quick-action-btn" href="promotion-center.php"><i class="fa fa-level-up"></i> Promote Students</a>
                                <a class="quick-action-btn" href="student-history.php"><i class="fa fa-history"></i> Student Transcript</a>
                            </div>
                        </div>

                        <div class="dashboard-shell">
                            <div class="dashboard-side-menu" aria-label="Dashboard Sections">
                                <button type="button" class="dash-side-btn active" data-target="section-overview"><i class="fa fa-dashboard"></i> Overview</button>
                                <button type="button" class="dash-side-btn" data-target="section-activity"><i class="fa fa-history"></i> Recent Activity</button>
                                <button type="button" class="dash-side-btn" data-target="section-notifications"><i class="fa fa-bell"></i> Notifications</button>
                            </div>
                            <div class="dashboard-main">
                                <div class="dashboard-top-menu" aria-label="Performance Menu">
                                    <button type="button" class="dash-top-btn" data-target="section-performance"><i class="fa fa-line-chart"></i> Subject Performance</button>
                                </div>

                        <?php
                        $_SQL_CHANGE_LOG = mysqli_query($con, "SELECT *
                            FROM tblsystemchangelog
                            WHERE actor_type IN ('Teacher','Student')
                            ORDER BY (CASE WHEN status='unread' THEN 0 ELSE 1 END), datetimeentry DESC
                            LIMIT 120");
                        ?>

                        <div class="dashboard-section" id="section-notifications">
                        <div class="table-wrap system-change-panel" role="region" aria-label="System Change Notifications" id="system-change-notifications">
                            <div class="system-change-header">
                                <div class="system-change-title">
                                    <span class="system-change-icon"><i class="fa fa-bell"></i></span>
                                    <div>
                                        <h3>System Change Notifications</h3>
                                        <p>Track teacher/student changes and online admission submissions in the audit view.</p>
                                    </div>
                                </div>
                                <div class="system-change-actions">
                                    <div class="system-change-count <?php echo ((int)$_UnreadChangeCount > 0) ? 'has-unread' : ''; ?>">
                                        <span>Unread Changes</span>
                                        <strong><?php echo (int)$_UnreadChangeCount; ?></strong>
                                    </div>
                                    <form method="post" action="admin.php#system-change-notifications" class="system-change-mark-form">
                                        <button type="submit" name="mark_changes_read" class="quick-action-btn system-change-mark-btn">
                                            <i class="fa fa-check"></i> Mark All As Read
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="system-change-table-wrap">
                                <table class="table system-change-table">
                                    <caption>System Change Notifications (Teachers, Students, and Admissions)</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Date/Time</th>
                                            <th scope="col">Actor</th>
                                            <th scope="col">Role</th>
                                            <th scope="col">Action</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($_SQL_CHANGE_LOG && mysqli_num_rows($_SQL_CHANGE_LOG) > 0) {
                                            while ($row_log = mysqli_fetch_array($_SQL_CHANGE_LOG, MYSQLI_ASSOC)) {
                                                $_ChangeStatus = strtolower(trim((string)($row_log['status'] ?? 'read')));
                                                $_RowClass = ($_ChangeStatus === 'unread') ? 'system-change-row-unread' : 'system-change-row-read';
                                                $_StatusClass = ($_ChangeStatus === 'unread') ? 'system-change-pill-unread' : 'system-change-pill-read';
                                                $_NotificationAction = '';
                                                if(strtoupper(trim((string)$row_log['action_type'])) === 'ONLINE_ADMISSION_SUBMITTED' && trim((string)$row_log['target_userid']) !== ''){
                                                    $_AdmissionReviewLink = 'online-admission-admin.php?edit_application='.urlencode(trim((string)$row_log['target_userid'])).'#edit-application';
                                                    $_NotificationAction = "<br><a class='system-change-action-link' href='".admin_dashboard_safe($_AdmissionReviewLink)."'><i class='fa fa-arrow-right'></i> Review Admission</a>";
                                                }elseif(strtoupper(trim((string)$row_log['action_type'])) === 'ONLINE_ADMISSION_HELP_REQUEST'){
                                                    $_NotificationAction = "<br><a class='system-change-action-link' href='online-admission-admin.php#help-requests'><i class='fa fa-arrow-right'></i> Open Help Requests</a>";
                                                }
                                                echo "<tr class='".$_RowClass."'>";
                                                echo "<td>".htmlspecialchars($row_log['datetimeentry'])."</td>";
                                                echo "<td><span class='system-change-actor'>".htmlspecialchars($row_log['actor_name'])."<small>".htmlspecialchars($row_log['actor_userid'])."</small></span></td>";
                                                echo "<td><span class='system-change-pill system-change-role'>".htmlspecialchars($row_log['actor_type'])."</span></td>";
                                                echo "<td>".htmlspecialchars($row_log['action_type'])."</td>";
                                                echo "<td><span class='system-change-pill ".$_StatusClass."'>".htmlspecialchars($row_log['status'])."</span></td>";
                                                echo "<td>".htmlspecialchars($row_log['details']).$_NotificationAction."</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='6' class='system-change-empty'>No change notifications yet.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <details class="admin-danger-zone">
                            <summary><span><i class="fa fa-shield"></i> Admin Danger Zone</span></summary>
                            <div class="admin-danger-zone-body">
                                <p>The database reset button is hidden here for safety. Open it only after taking a backup and when you intentionally want to clear school operational data.</p>
                                <a class="admin-danger-btn" href="global_deletes.php"><i class="fa fa-trash"></i> Open Database Reset</a>
                            </div>
                        </details>
                        </div>

                        <!-- Chart.js CDN -->
                        <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

                        <!-- Dashboard Section -->
                        <div class="dashboard-section active" id="section-overview">
                        <div class="dashboard-flex" role="region" aria-label="Student Distribution Dashboard">
                            <div class="chart-side">
                                <div class="chart-container">
                                    <canvas id="studentChart" width="280" height="280" aria-label="Student distribution by gender and residence"></canvas>
                                    <p class="chart-note">Chart and summary tiles below show students with recognized Day or Boarding residence. Missing residence records are shown separately.</p>
                                </div>
                            </div>
                            <div class="cards-side">
                                <div class="card" role="article" aria-label="Boys Day Students">
                                    <h4><i class="fa fa-male" style="color:#2563eb; margin-right:4px;"></i>Boys - Day</h4>
                                    <p><?php echo number_format($boys_day); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Boys Boarding Students">
                                    <h4><i class="fa fa-male" style="color:#38bdf8; margin-right:4px;"></i>Boys - Boarding</h4>
                                    <p><?php echo number_format($boys_boarding); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Girls Day Students">
                                    <h4><i class="fa fa-female" style="color:#db2777; margin-right:4px;"></i>Girls - Day</h4>
                                    <p><?php echo number_format($girls_day); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Girls Boarding Students">
                                    <h4><i class="fa fa-female" style="color:#f472b6; margin-right:4px;"></i>Girls - Boarding</h4>
                                    <p><?php echo number_format($girls_boarding); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Students With No Residence Status">
                                    <h4><i class="fa fa-question-circle" style="color:#b45309; margin-right:4px;"></i>No Residence Status</h4>
                                    <p><?php echo number_format($students_no_status); ?></p>
                                </div>
                                <div class="card total" role="article" aria-label="Total Active Students">
                                    <h4><i class="fa fa-users" style="color:#fff; margin-right:4px;"></i>Total Active Students</h4>
                                    <p><?php echo number_format($grand_total); ?></p>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="dashboard-section" id="section-activity">
                        <div class="recent-activity-shell">
                            <section class="recent-activity-panel" role="region" aria-label="Recent Activity">
                                <div class="recent-activity-header">
                                    <div>
                                        <span class="recent-activity-eyebrow"><i class="fa fa-history"></i> Desktop Feed</span>
                                        <h3>Recent Activity</h3>
                                        <p>Latest registrations, score work, account changes, and other recent school activity.</p>
                                    </div>
                                    <a href="admin.php#system-change-notifications" class="quick-action-btn recent-activity-open"><i class="fa fa-bell"></i> Open Audit</a>
                                </div>

                                <div class="recent-activity-list">
                                    <?php
                                    if($_RecentActivityFeedCount > 0){
                                        foreach($_RecentActivities as $_Activity){
                                            $_Tone = trim((string)(isset($_Activity['tone']) ? $_Activity['tone'] : 'neutral'));
                                            $_Icon = trim((string)(isset($_Activity['icon']) ? $_Activity['icon'] : 'fa-history'));
                                            $_Title = trim((string)(isset($_Activity['title']) ? $_Activity['title'] : 'Activity'));
                                            $_Description = trim((string)(isset($_Activity['description']) ? $_Activity['description'] : ''));
                                            $_DateTime = trim((string)(isset($_Activity['datetime']) ? $_Activity['datetime'] : ''));
                                            $_Link = trim((string)(isset($_Activity['link']) ? $_Activity['link'] : ''));
                                            $_LinkLabel = trim((string)(isset($_Activity['link_label']) ? $_Activity['link_label'] : 'Open'));
                                            $_MetaItems = isset($_Activity['meta']) && is_array($_Activity['meta']) ? $_Activity['meta'] : array();

                                            echo "<article class='recent-activity-item'>";
                                            echo "<span class='recent-activity-icon recent-activity-icon--".admin_dashboard_safe($_Tone)."'><i class='fa ".admin_dashboard_safe($_Icon)."'></i></span>";
                                            echo "<div class='recent-activity-body'>";
                                            echo "<div class='recent-activity-topline'>";
                                            echo "<strong>".admin_dashboard_safe($_Title)."</strong>";
                                            echo "<time datetime='".admin_dashboard_safe($_DateTime)."'>".admin_dashboard_safe(admin_dashboard_relative_time($_DateTime))."</time>";
                                            echo "</div>";
                                            echo "<p>".admin_dashboard_safe($_Description !== '' ? $_Description : $_Title)."</p>";
                                            if(count($_MetaItems) > 0){
                                                echo "<div class='recent-activity-meta'>";
                                                foreach($_MetaItems as $_MetaItem){
                                                    echo "<span>".admin_dashboard_safe($_MetaItem)."</span>";
                                                }
                                                echo "</div>";
                                            }
                                            echo "</div>";
                                            if($_Link !== ''){
                                                echo "<a class='recent-activity-link' href='".admin_dashboard_safe($_Link)."'><i class='fa fa-arrow-right'></i> ".admin_dashboard_safe($_LinkLabel)."</a>";
                                            }
                                            echo "</article>";
                                        }
                                    } else {
                                        echo "<div class='recent-activity-empty'><i class='fa fa-clock-o'></i>No recent activity is available on the dashboard yet.</div>";
                                    }
                                    ?>
                                </div>
                            </section>

                            <aside class="recent-activity-aside" aria-label="Recent Activity Summary">
                                <h4>Activity Summary</h4>
                                <p>Quick desktop totals for the latest feed and today's registrations.</p>
                                <div class="recent-activity-summary-grid">
                                    <div class="recent-activity-summary-card">
                                        <span>Feed Items</span>
                                        <strong><?php echo number_format($_RecentActivityFeedCount); ?></strong>
                                        <small>Latest activity items currently shown on the dashboard.</small>
                                    </div>
                                    <div class="recent-activity-summary-card">
                                        <span>Unread Changes</span>
                                        <strong><?php echo number_format($_UnreadChangeCount); ?></strong>
                                        <small>Audit items still waiting for review.</small>
                                    </div>
                                    <div class="recent-activity-summary-card">
                                        <span>Students Today</span>
                                        <strong><?php echo number_format($_TodayStudentRegistrations); ?></strong>
                                        <small>Student records added today.</small>
                                    </div>
                                    <div class="recent-activity-summary-card">
                                        <span>Teachers Today</span>
                                        <strong><?php echo number_format($_TodayTeacherRegistrations); ?></strong>
                                        <small>Teacher records added today.</small>
                                    </div>
                                    <div class="recent-activity-summary-card">
                                        <span>This Week</span>
                                        <strong><?php echo number_format($_WeekRegistrations); ?></strong>
                                        <small>Student and teacher registrations this week.</small>
                                    </div>
                                    <div class="recent-activity-summary-card">
                                        <span>Active Semesters</span>
                                        <strong><?php echo number_format(count($activeBatchNames)); ?></strong>
                                        <small>Semester batches currently marked active.</small>
                                    </div>
                                </div>
                            </aside>
                        </div>
                        </div>

                        <div class="dashboard-section" id="section-performance">
                        <div class="perf-panel" role="region" aria-label="Subject Performance" id="subject-performance-section">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                <h3 style="margin:0;color:#0f172a;font-size:1rem;">Subject Performance Metrics</h3>
                                <div style="color:#475569;font-size:0.85rem;">Filter by class, batch, academic year, and semester</div>
                            </div>

                            <form method="get" action="admin.php" class="perf-toolbar">
                                <div>
                                    <label for="perf_class">Class</label>
                                    <select id="perf_class" name="perf_class">
                                        <option value="">All Classes</option>
                                        <?php
                                        if($_ClassOptions){
                                            while($row_c = mysqli_fetch_array($_ClassOptions, MYSQLI_ASSOC)){
                                                $selected = ($_ClassFilter === $row_c['class_entryid']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_c['class_entryid'])."' $selected>".htmlspecialchars($row_c['class_name'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="perf_batch">Batch</label>
                                    <select id="perf_batch" name="perf_batch">
                                        <option value="">All Batches</option>
                                        <?php
                                        if($_BatchOptions){
                                            while($row_b = mysqli_fetch_array($_BatchOptions, MYSQLI_ASSOC)){
                                                $selected = ($_BatchFilter === $row_b['batchid']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_b['batchid'])."' $selected>".htmlspecialchars($row_b['batch'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="perf_year">Academic Year</label>
                                    <select id="perf_year" name="perf_year">
                                        <option value="">All Years</option>
                                        <?php
                                        if($_YearOptions){
                                            while($row_y = mysqli_fetch_array($_YearOptions, MYSQLI_ASSOC)){
                                                if(trim((string)$row_y['assignment_year']) === ''){
                                                    continue;
                                                }
                                                $selected = ($_YearFilter === (string)$row_y['assignment_year']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_y['assignment_year'])."' $selected>".htmlspecialchars($row_y['assignment_year'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="perf_term">Semester</label>
                                    <select id="perf_term" name="perf_term">
                                        <option value="">All Semesters</option>
                                        <?php
                                        if($_TermOptions){
                                            while($row_t = mysqli_fetch_array($_TermOptions, MYSQLI_ASSOC)){
                                                $selected = ($_TermFilter === $row_t['termname']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_t['termname'])."' $selected>".htmlspecialchars($row_t['termname'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label>&nbsp;</label>
                                    <button type="submit" class="quick-action-btn" style="width:100%;"><i class="fa fa-filter"></i> Apply Filter</button>
                                </div>
                                <div>
                                    <label>&nbsp;</label>
                                    <a href="admin.php" class="quick-action-btn" style="width:100%;"><i class="fa fa-undo"></i> Reset</a>
                                </div>
                            </form>

                            <div style="margin-bottom:12px;padding:10px 12px;border:1px solid #dbeafe;background:#eff6ff;border-radius:14px;color:#1e3a8a;font-size:0.92rem;">
                                <strong>Selected Year:</strong> <?php echo htmlspecialchars($_PerfScopeLabel); ?>
                            </div>

                            <div class="cards-side" style="margin-bottom:12px;">
                                <div class="card">
                                    <h4>Assigned Subjects</h4>
                                    <p><?php echo number_format($_TotalAssignedSubjects); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Submitted Entries</h4>
                                    <p><?php echo number_format($_SubmittedSubjects); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Pending Entries</h4>
                                    <p><?php echo number_format($_PendingSubjects); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Average Score (%)</h4>
                                    <p><?php echo number_format($_OverallAvg, 2); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Average Pass Rate (%)</h4>
                                    <p><?php echo number_format($_OverallPass, 2); ?></p>
                                </div>
                                <div class="card readiness-card" style="grid-column: span 3;">
                                    <div class="readiness-copy">
                                        <h4>Class Ready</h4>
                                        <p><?php echo htmlspecialchars($_ReadinessDetail); ?></p>
                                        <div class="readiness-meta"><?php echo htmlspecialchars($_ReadinessMeta); ?></div>
                                    </div>
                                    <div class="readiness-side">
                                        <span class="readiness-pill <?php echo $_ReadinessPillClass; ?>"><?php echo htmlspecialchars($_ReadinessStatusLabel); ?></span>
                                        <?php if($_ReadinessScore !== ''){ ?>
                                            <div class="readiness-score"><?php echo htmlspecialchars($_ReadinessScore); ?></div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="card total" style="grid-column: span 3;">
                                    <h4>Top Subject</h4>
                                    <p style="font-size:1.1rem;"><?php echo htmlspecialchars($_BestSubject); ?> (<?php echo number_format($_BestSubjectScore,2); ?>%)</p>
                                    <div style="font-size:0.82rem;color:#cbd5e1;margin-top:6px;"><?php echo htmlspecialchars($_PerfScopeLabel); ?></div>
                                </div>
                            </div>

                            <div class="pending-list-wrap">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                    <strong style="color:#0f172a;">Pending Subject List (No Score Entry Yet)</strong>
                                    <span style="font-size:0.82rem;color:#64748b;">Showing up to 100 items</span>
                                </div>
                                <?php if(count($_PendingRows)>0){ ?>
                                    <ol class="pending-list">
                                        <?php
                                        foreach($_PendingRows as $pendingRow){
                                            echo "<li>";
                                            echo "<strong>".htmlspecialchars($pendingRow['subject'])."</strong>";
                                            echo " | Class: ".htmlspecialchars($pendingRow['class_name']);
                                            echo " | Academic Year: ".htmlspecialchars($pendingRow['assignment_year']);
                                            echo " | Semester: ".htmlspecialchars($pendingRow['termname']);
                                            echo " | Batch: ".htmlspecialchars($pendingRow['batch']);
                                            echo " | Teacher: ".htmlspecialchars($pendingRow['teacher_name']);
                                            echo "</li>";
                                        }
                                        ?>
                                    </ol>
                                <?php } else { ?>
                                    <div style="color:#0f766e;font-size:0.9rem;">All assigned subjects have submitted score entries for this filter.</div>
                                <?php } ?>
                            </div>

                            <div class="perf-grid">
                                <div class="perf-chart-wrap">
                                    <canvas id="subjectPerformanceChart" height="260" aria-label="Subject performance chart"></canvas>
                                </div>
                                <div class="perf-table-wrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Entries</th>
                                                <th>Avg %</th>
                                                <th>Pass %</th>
                                                <th>Excellent %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if($_TotalSubjects>0){
                                                foreach($_PerfRows as $prow){
                                                    echo "<tr>";
                                                    echo "<td>".htmlspecialchars($prow['subject'])."</td>";
                                                    echo "<td align='center'>".number_format($prow['entries_count'])."</td>";
                                                    echo "<td align='center'>".number_format((float)$prow['avg_percent'],2)."</td>";
                                                    echo "<td align='center'>".number_format((float)$prow['pass_rate'],2)."</td>";
                                                    echo "<td align='center'>".number_format((float)$prow['excellence_rate'],2)."</td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='5' style='text-align:center;color:#64748b'>No score data found for selected filter.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            function showDashboardSection(sectionId) {
                                const sections = document.querySelectorAll('.dashboard-section');
                                const sideBtns = document.querySelectorAll('.dash-side-btn');
                                const topBtns = document.querySelectorAll('.dash-top-btn');

                                sections.forEach(sec => sec.classList.remove('active'));
                                sideBtns.forEach(btn => btn.classList.remove('active'));
                                topBtns.forEach(btn => btn.classList.remove('active'));

                                const selectedSection = document.getElementById(sectionId);
                                if (selectedSection) {
                                    selectedSection.classList.add('active');
                                }

                                const selectedSide = document.querySelector('.dash-side-btn[data-target="' + sectionId + '"]');
                                const selectedTop = document.querySelector('.dash-top-btn[data-target="' + sectionId + '"]');
                                if (selectedSide) selectedSide.classList.add('active');
                                if (selectedTop) selectedTop.classList.add('active');
                            }

                            function syncDashboardSectionFromLocation() {
                                const urlParams = new URLSearchParams(window.location.search);
                                if (window.location.hash === '#system-change-notifications') {
                                    showDashboardSection('section-notifications');
                                    return;
                                }
                                if (
                                    urlParams.get('perf_class') ||
                                    urlParams.get('perf_batch') ||
                                    urlParams.get('perf_year') ||
                                    urlParams.get('perf_term')
                                ) {
                                    showDashboardSection('section-performance');
                                    return;
                                }
                                showDashboardSection('section-overview');
                            }

                            document.querySelectorAll('.dash-side-btn, .dash-top-btn').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const target = this.getAttribute('data-target');
                                    showDashboardSection(target);
                                });
                            });

                            window.addEventListener('hashchange', syncDashboardSectionFromLocation);
                            syncDashboardSectionFromLocation();

                            const desktopSearchWrap = document.querySelector('[data-desktop-search]');
                            if (desktopSearchWrap) {
                                const searchForm = document.getElementById('dashboard-global-search-form');
                                const searchInput = document.getElementById('dashboard-global-search-input');
                                const searchResults = document.getElementById('dashboard-global-search-results');
                                let searchTimer = null;
                                let searchRequestIndex = 0;

                                function setSearchResults(html) {
                                    if (!searchResults) {
                                        return;
                                    }
                                    searchResults.innerHTML = html;
                                    searchResults.removeAttribute('hidden');
                                }

                                function closeSearchResults() {
                                    if (!searchResults) {
                                        return;
                                    }
                                    searchResults.setAttribute('hidden', 'hidden');
                                    searchResults.innerHTML = '';
                                }

                                function runDesktopSearch(forceSearch) {
                                    if (!searchInput || !searchResults) {
                                        return;
                                    }
                                    const query = searchInput.value.trim();
                                    if (!query) {
                                        closeSearchResults();
                                        return;
                                    }
                                    if (query.length < 2 && !forceSearch) {
                                        setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-search'></i><div><strong>Keep typing</strong><span>Use at least 2 characters to search the desktop.</span></div></div>");
                                        return;
                                    }

                                    setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-spinner fa-spin'></i><div><strong>Searching</strong><span>Checking students, staff, classes, batches, tools, and modules.</span></div></div>");
                                    const requestId = ++searchRequestIndex;
                                    const xhr = new XMLHttpRequest();
                                    xhr.onreadystatechange = function () {
                                        if (xhr.readyState !== 4 || requestId !== searchRequestIndex) {
                                            return;
                                        }
                                        if (xhr.status === 200) {
                                            setSearchResults(xhr.responseText);
                                        } else if (xhr.status === 403) {
                                            setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-lock'></i><div><strong>Access denied</strong><span>You do not have access to desktop search.</span></div></div>");
                                        } else {
                                            setSearchResults("<div class='desktop-search-feedback'><i class='fa fa-exclamation-circle'></i><div><strong>Search failed</strong><span>Try again in a moment.</span></div></div>");
                                        }
                                    };
                                    xhr.open('GET', 'admin-global-search.php?q=' + encodeURIComponent(query), true);
                                    xhr.send();
                                }

                                if (searchForm) {
                                    searchForm.addEventListener('submit', function (event) {
                                        event.preventDefault();
                                        runDesktopSearch(true);
                                    });
                                }

                                if (searchInput) {
                                    searchInput.addEventListener('input', function () {
                                        if (searchTimer) {
                                            clearTimeout(searchTimer);
                                        }
                                        searchTimer = setTimeout(function () {
                                            runDesktopSearch(false);
                                        }, 220);
                                    });

                                    searchInput.addEventListener('focus', function () {
                                        if (searchInput.value.trim() !== '') {
                                            runDesktopSearch(false);
                                        }
                                    });

                                    searchInput.addEventListener('keydown', function (event) {
                                        if (event.key === 'Escape') {
                                            closeSearchResults();
                                            searchInput.blur();
                                        }
                                    });
                                }

                                document.addEventListener('click', function (event) {
                                    if (!desktopSearchWrap.contains(event.target)) {
                                        closeSearchResults();
                                    }
                                });
                            }

                            if (typeof Chart !== 'function') {
                                return;
                            }

                            const studentCanvas = document.getElementById('studentChart');
                            if (studentCanvas) {
                                const ctx = studentCanvas.getContext('2d');
                                new Chart(ctx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Boys Day', 'Boys Boarding', 'Girls Day', 'Girls Boarding', 'No Residence Status'],
                                        datasets: [{
                                            label: 'Student Count',
                                            data: [<?php echo $boys_day; ?>, <?php echo $boys_boarding; ?>, <?php echo $girls_day; ?>, <?php echo $girls_boarding; ?>, <?php echo $students_no_status; ?>],
                                            backgroundColor: ['#2563eb', '#38bdf8', '#db2777', '#f472b6', '#d59b2d'],
                                            borderColor: '#fff',
                                            borderWidth: 2,
                                            hoverOffset: 16
                                        }]
                                    },
                                    options: {
                                        responsive: true, maintainAspectRatio: false,
                                        animation: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    font: { size: 14 },
                                                    color: '#374151',
                                                    padding: 20,
                                                    boxWidth: 20
                                                }
                                            },
                                            title: {
                                                display: true,
                                                text: 'Student Distribution by Gender & Residence Status',
                                                font: { size: 16, weight: '600' },
                                                color: '#111827',
                                                padding: { top: 10, bottom: 20 }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.label || '';
                                                        let value = context.parsed || 0;
                                                        let total = <?php echo $grand_total; ?>;
                                                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                                        return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            }

                            const perfCanvas = document.getElementById('subjectPerformanceChart');
                            if (perfCanvas) {
                                const perfLabels = <?php echo json_encode($_PerfLabels); ?>;
                                const perfAvg = <?php echo json_encode($_PerfAvg); ?>;
                                const perfPass = <?php echo json_encode($_PerfPass); ?>;
                                const perfTitle = <?php echo json_encode($_PerfChartTitle); ?>;
                                const perfCtx = perfCanvas.getContext('2d');

                                if (perfLabels.length > 0) {
                                    new Chart(perfCtx, {
                                        type: 'bar',
                                        data: {
                                            labels: perfLabels,
                                            datasets: [
                                                {
                                                    label: 'Average %',
                                                    data: perfAvg,
                                                    backgroundColor: 'rgba(14, 116, 144, 0.75)',
                                                    borderColor: '#0e7490',
                                                    borderWidth: 1
                                                },
                                                {
                                                    label: 'Pass %',
                                                    data: perfPass,
                                                    backgroundColor: 'rgba(180, 83, 9, 0.65)',
                                                    borderColor: '#b45309',
                                                    borderWidth: 1
                                                }
                                            ]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: { position: 'top' },
                                                title: {
                                                    display: true,
                                                    text: perfTitle
                                                }
                                            },
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    suggestedMax: 100
                                                }
                                            }
                                        }
                                    });
                                } else {
                                    perfCtx.font = '14px Segoe UI';
                                    perfCtx.fillStyle = '#64748b';
                                    perfCtx.fillText('No performance data for selected filter.', 16, 40);
                                }
                            }
                        });
                        </script>

                    </div>
                </td>
            </tr>
        </table>

        <button onclick="topFunction()" id="myBtn" title="Go to top" aria-label="Scroll to top"><i class="fa fa-arrow-up"></i></button>
    </div>

    <script>
    // Go to Top Button
    const mybutton = document.getElementById('myBtn');
    window.onscroll = function() {
        if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
            mybutton.style.display = 'block';
        } else {
            mybutton.style.display = 'none';
        }
    };

    function topFunction() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    </script>
</body>
</html>

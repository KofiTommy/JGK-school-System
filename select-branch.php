<?php
session_start();
include("dbstring.php");
if(!isset($_SESSION['Message'])){
    $_SESSION['Message'] = "";
}

function branch_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function branch_image_src($filename){
    $filename = trim((string)$filename);
    if($filename === ""){
        return "images/nexgen-logo.png";
    }
    return "images/logo/".rawurlencode($filename);
}

$_SubBranchId = isset($_POST['subbranchid']) ? $_POST['subbranchid'] : "";
if(isset($_POST['button_update_sublogo'])){
    $_Filename = isset($_FILES["logo"]["name"]) ? $_FILES["logo"]["name"] : "";
    $_Image = isset($_FILES["logo"]["tmp_name"]) ? $_FILES["logo"]["tmp_name"] : "";
    $_Destination = "images/logo";

    if($_Filename !== "" && !file_exists($_Destination."/".$_Filename)){
        move_uploaded_file($_Image, $_Destination."/".$_Filename);
    }

    if($_Filename !== "" && file_exists($_Destination."/".$_Filename)){
        $_SQL_2 = mysqli_query($con, "UPDATE tblsubbranch SET logo='$_Filename' WHERE subbranchid='$_SubBranchId'");
        if($_SQL_2){
            header("location:select-branch.php");
            exit();
        }
    }
}

$_BranchId = isset($_POST['branchid']) ? $_POST['branchid'] : "";
$_Initials = isset($_POST['initials']) ? $_POST['initials'] : "";
if(isset($_POST['button_update_logo'])){
    $_Filename = isset($_FILES["logo"]["name"]) ? $_FILES["logo"]["name"] : "";
    $_Image = isset($_FILES["logo"]["tmp_name"]) ? $_FILES["logo"]["tmp_name"] : "";
    $_Destination = "images/logo";

    if($_Filename !== "" && !file_exists($_Destination."/".$_Filename)){
        move_uploaded_file($_Image, $_Destination."/".$_Filename);
    }

    if($_Filename !== "" && file_exists($_Destination."/".$_Filename)){
        $_SQL_2 = mysqli_query($con, "UPDATE tblbranch SET logo='$_Filename' WHERE branchid='$_BranchId'");
        if($_SQL_2){
            header("location:select-branch.php");
            exit();
        }
    }
}

$_Update_CompanyId = isset($_POST['update_companyid']) ? $_POST['update_companyid'] : "";
if(isset($_POST["button_update"])){
    include("code.php");
    $_Filename = isset($_FILES["backgroundphoto"]["name"]) ? $_FILES["backgroundphoto"]["name"] : "";
    $_Image = isset($_FILES["backgroundphoto"]["tmp_name"]) ? $_FILES["backgroundphoto"]["tmp_name"] : "";
    $_Destination = "images/logo";

    if($_Filename !== "" && !file_exists($_Destination."/".$_Filename)){
        move_uploaded_file($_Image, $_Destination."/".$_Filename);
    }

    if($_Filename !== "" && file_exists($_Destination."/".$_Filename)){
        $sql2 = "UPDATE tblcompany SET backgroundphoto='$_Filename' WHERE companyid='$_Update_CompanyId'";
        if(!mysqli_query($con, $sql2)){
            die('Error:' . mysqli_error($con));
        } else {
            header("location:select-branch.php");
            exit();
        }
    }
}

if(isset($_GET['select_branch_id'])){
    $_SESSION['BRANCHID'] = $_GET['select_branch_id'];
    if($_SESSION["BRANCHID"] != ""){
        header("location:admin.php");
        exit();
    }
}

include("check-login.php");
if(!$_SESSION["AUDITDATE"]){
    header("location:auditdatealert.php");
    exit();
}

$currentUserName = "";
if(isset($_SESSION['FULLNAME']) && trim((string)$_SESSION['FULLNAME']) !== ""){
    $currentUserName = trim((string)$_SESSION['FULLNAME']);
} elseif(isset($_SESSION['USERNAME']) && trim((string)$_SESSION['USERNAME']) !== ""){
    $currentUserName = trim((string)$_SESSION['USERNAME']);
}

$companies = array();
$branchTotal = 0;
if(isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === "normal_user" && isset($_SESSION['COMPANY'])){
    $companyIdEsc = mysqli_real_escape_string($con, (string)$_SESSION['COMPANY']);
    $_SQL = mysqli_query($con, "SELECT * FROM tblcompany WHERE companyid='$companyIdEsc'");
    if($_SQL){
        while($row_c = mysqli_fetch_array($_SQL, MYSQLI_ASSOC)){
            $row_c['branches'] = array();
            $companyIdRowEsc = mysqli_real_escape_string($con, (string)$row_c['companyid']);
            $sql1 = "SELECT br.initials, br.branchid, br.companyid, cp.fullname, br.address, br.location, br.telephone1, br.telephone2, br.status, br.logo
                FROM tblbranch br
                INNER JOIN tblcompany cp ON br.companyid=cp.companyid
                WHERE cp.companyid='$companyIdRowEsc'
                ORDER BY cp.fullname ASC, br.location ASC";
            $result = mysqli_query($con, $sql1);
            if($result){
                while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                    $row_c['branches'][] = $row;
                    $branchTotal++;
                }
            }
            $row_c['branch_count'] = count($row_c['branches']);
            $companies[] = $row_c;
        }
    }
}
$companyCount = count($companies);

include("validation/header.php");
?>
<html>
<head>
<?php include("title.php"); ?>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/select-branch.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
var rnd;
function getStaffId()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("staff-id").value=rnd;
}
</script>
</head>
<body class="branch-page" onload="idleLogout()">
<div class="branch-audit-bar">
    <div class="branch-audit-bar__inner">
        <span><i class="fa fa-calendar"></i> Audit Date: <?php echo branch_esc($_SESSION["AUDITDATE"]); ?></span>
        <?php if($currentUserName !== ""){ ?>
        <span><i class="fa fa-user-circle"></i> <?php echo branch_esc($currentUserName); ?></span>
        <?php } ?>
    </div>
</div>

<main class="branch-shell">
    <section class="branch-hero">
        <div class="branch-hero__copy">
            <div class="branch-brand">
                <div class="branch-brand__mark">
                    <img src="images/nexgen-logo.png" alt="NexGen">
                </div>
                <div>
                    <span class="branch-kicker">Branch Workspace</span>
                    <h1>Select the branch you want to open.</h1>
                    <p>Choose a branch, update logos, and manage background branding.</p>
                </div>
            </div>

            <div class="branch-stat-grid">
                <article class="branch-stat-card">
                    <span>Companies</span>
                    <strong><?php echo (int)$companyCount; ?></strong>
                </article>
                <article class="branch-stat-card">
                    <span>Branches</span>
                    <strong><?php echo (int)$branchTotal; ?></strong>
                </article>
                <article class="branch-stat-card">
                    <span>Current Company</span>
                    <strong><?php echo branch_esc(isset($_SESSION['COMPANY']) ? $_SESSION['COMPANY'] : "-"); ?></strong>
                </article>
            </div>
        </div>

        <div class="branch-hero__actions">
            <div class="branch-chip"><i class="fa fa-mobile"></i> Mobile Responsive</div>
            <div class="branch-chip branch-chip--accent"><i class="fa fa-building"></i> Branch Selection</div>
            <form method="post" action="logout.php" class="branch-logout-form">
                <button class="branch-secondary-btn branch-secondary-btn--dark" type="submit">
                    <i class="fa fa-power-off"></i> Log Out
                </button>
            </form>
        </div>
    </section>

    <?php if(trim((string)$_SESSION['Message']) !== ""){ ?>
    <div class="branch-alert"><?php echo $_SESSION['Message']; ?></div>
    <?php $_SESSION['Message'] = ""; ?>
    <?php } ?>

    <?php if(isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === "normal_user"){ ?>
        <?php if($companyCount > 0){ ?>
            <?php foreach($companies as $company){ ?>
            <section class="branch-company-card">
                <div class="branch-company-card__header">
                    <div>
                        <span class="branch-company-card__eyebrow">Company Workspace</span>
                        <h2><?php echo branch_esc($company['fullname']); ?></h2>
                        <p>Company ID: <?php echo branch_esc($company['companyid']); ?> | Branches: <?php echo (int)$company['branch_count']; ?></p>
                    </div>
                    <div class="branch-company-card__preview">
                        <img src="<?php echo branch_esc(branch_image_src(isset($company['backgroundphoto']) ? $company['backgroundphoto'] : "")); ?>" alt="Background preview for <?php echo branch_esc($company['fullname']); ?>">
                    </div>
                </div>

                <form method="post" action="select-branch.php" enctype="multipart/form-data" class="branch-upload-form">
                    <input type="hidden" name="update_companyid" value="<?php echo branch_esc($company['companyid']); ?>">
                    <div class="branch-upload-form__copy">
                        <span class="branch-upload-form__eyebrow">Branding</span>
                        <h3>Update background image</h3>
                        <p>Upload a background photo for this company.</p>
                    </div>
                    <div class="branch-upload-row">
                        <input class="branch-file-input" type="file" name="backgroundphoto" accept="image/*" required>
                        <button class="branch-primary-btn" type="submit" name="button_update">
                            <i class="fa fa-upload"></i> Upload Background
                        </button>
                    </div>
                </form>

                <?php if($company['branch_count'] > 0){ ?>
                <div class="branch-grid">
                    <?php foreach($company['branches'] as $index => $branch){ ?>
                    <?php
                    $status = strtolower(trim((string)$branch['status']));
                    $statusClass = ($status === "active") ? "branch-status--active" : "branch-status--inactive";
                    $branchName = trim((string)$branch['location']) !== "" ? $branch['location'] : "Unnamed Branch";
                    ?>
                    <article class="branch-card">
                        <div class="branch-card__top">
                            <span class="branch-card__index"><?php echo str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT); ?></span>
                            <span class="branch-status <?php echo $statusClass; ?>">
                                <?php echo branch_esc($status === "" ? "Unknown" : ucfirst($status)); ?>
                            </span>
                        </div>

                        <h3><?php echo branch_esc($branchName); ?></h3>
                        <p class="branch-card__id">Branch ID: <?php echo branch_esc($branch['branchid']); ?></p>

                        <div class="branch-details">
                            <div class="branch-detail">
                                <span>Address</span>
                                <strong><?php echo branch_esc($branch['address']); ?></strong>
                            </div>
                            <div class="branch-detail">
                                <span>Telephone 1</span>
                                <strong><?php echo branch_esc($branch['telephone1']); ?></strong>
                            </div>
                            <div class="branch-detail">
                                <span>Telephone 2</span>
                                <strong><?php echo branch_esc($branch['telephone2']); ?></strong>
                            </div>
                        </div>

                        <div class="branch-card__media">
                            <div class="branch-logo-preview">
                                <img src="<?php echo branch_esc(branch_image_src(isset($branch['logo']) ? $branch['logo'] : "")); ?>" alt="Logo for <?php echo branch_esc($branchName); ?>">
                            </div>
                            <a class="branch-open-btn" href="select-branch.php?select_branch_id=<?php echo urlencode((string)$branch['branchid']); ?>" onclick="return confirm('Do you want to open this branch?');">
                                <i class="fa fa-home"></i> Open Branch
                            </a>
                        </div>

                        <form method="post" action="select-branch.php" enctype="multipart/form-data" class="branch-logo-form">
                            <input type="hidden" name="branchid" value="<?php echo branch_esc($branch['branchid']); ?>">
                            <input type="hidden" name="initials" value="<?php echo branch_esc($branch['initials']); ?>">
                            <label for="branch-logo-file-<?php echo (int)$index; ?>-<?php echo branch_esc($branch['branchid']); ?>">Branch Logo</label>
                            <div class="branch-upload-row">
                                <input class="branch-file-input" id="branch-logo-file-<?php echo (int)$index; ?>-<?php echo branch_esc($branch['branchid']); ?>" type="file" name="logo" accept="image/*" required>
                                <button class="branch-secondary-btn" type="submit" name="button_update_logo">
                                    <i class="fa fa-upload"></i> Upload Logo
                                </button>
                            </div>
                        </form>
                    </article>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <div class="branch-empty">No branches are available for this company yet.</div>
                <?php } ?>
            </section>
            <?php } ?>
        <?php } else { ?>
        <section class="branch-empty branch-empty--large">
            <h2>No branches available</h2>
            <p>There are no company branches to display right now.</p>
        </section>
        <?php } ?>
    <?php } else { ?>
    <section class="branch-empty branch-empty--large">
        <h2>Access restricted</h2>
        <p>This page is available only for admin branch selection.</p>
    </section>
    <?php } ?>
</main>
</body>
</html>

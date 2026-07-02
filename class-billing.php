<?php
session_start();
if(!isset($_SESSION['Message'])){
    $_SESSION['Message'] = "";
}
include("check-login.php");
include("dbstring.php");
include("teacher-billing-utils.php");
ensure_teacher_billing_table($con);

function cb_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function cb_flash($type, $message){
    $class = "billing-manager-flash billing-manager-flash--info";
    if($type === "success"){
        $class = "billing-manager-flash billing-manager-flash--success";
    }elseif($type === "error"){
        $class = "billing-manager-flash billing-manager-flash--error";
    }elseif($type === "warning"){
        $class = "billing-manager-flash billing-manager-flash--warning";
    }
    return "<div class=\"$class\">".cb_safe($message)."</div>";
}

function cb_redirect($params = array()){
    $url = "class-billing.php";
    if(!empty($params)){
        $url .= "?".http_build_query($params);
    }
    header("Location: ".$url);
    exit();
}

function cb_generate_item_id(){
    include(__DIR__.DIRECTORY_SEPARATOR."shortcode.php");
    return isset($shortcode) ? (string)$shortcode : ("ITM".date("YmdHis"));
}

function cb_generate_price_id(){
    include(__DIR__.DIRECTORY_SEPARATOR."code.php");
    return isset($code) ? (string)$code : ("PRICE_".date("YmdHis"));
}

function cb_fetch_one($con, $sql){
    $result = mysqli_query($con, $sql);
    if($result && mysqli_num_rows($result) > 0){
        return mysqli_fetch_array($result, MYSQLI_ASSOC);
    }
    return null;
}

function cb_count_value($con, $sql, $field){
    $row = cb_fetch_one($con, $sql);
    if($row && isset($row[$field])){
        return (int)$row[$field];
    }
    return 0;
}

function cb_price_has_bills($con, $itemPriceId){
    $itemPriceEsc = mysqli_real_escape_string($con, (string)$itemPriceId);
    return cb_count_value($con, "SELECT COUNT(*) AS total_count FROM tblbilling WHERE itempriceid='$itemPriceEsc'", "total_count") > 0;
}

function cb_item_has_price_rows($con, $itemId){
    $itemEsc = mysqli_real_escape_string($con, (string)$itemId);
    return cb_count_value($con, "SELECT COUNT(*) AS total_count FROM tblitemprice WHERE itemid='$itemEsc'", "total_count") > 0;
}

function cb_format_money($value){
    if($value === "" || $value === null){
        return "--";
    }
    return number_format((float)$value, 2);
}

function cb_format_date($value, $format = "d M Y, g:i a"){
    $value = trim((string)$value);
    if($value === ""){
        return "--";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date($format, $timestamp);
}

$cbIsAdminBillingManager = teacher_billing_is_admin();
if(!$cbIsAdminBillingManager){
    $_SESSION['Message'] = cb_flash("error", "You do not have access to the billing manager.");
    header("location:".teacher_billing_landing_page());
    exit();
}
$cbCanManageBillingCatalog = $cbIsAdminBillingManager;
$cbScopeClassRows = array();
$cbScopeBatchRows = array();
$cbScopeTeacherId = '';

$flashMessage = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
$_SESSION['Message'] = "";

$editItemId = isset($_GET["edit_item"]) ? trim((string)$_GET["edit_item"]) : "";
$editPriceId = isset($_GET["price_item"]) ? trim((string)$_GET["price_item"]) : "";

if($cbCanManageBillingCatalog && isset($_POST["save_item_entry"])){
    $itemName = strtoupper(trim((string)$_POST["item_name"]));
    if($itemName === ""){
        $_SESSION['Message'] = cb_flash("error", "Enter an item name before saving.");
        cb_redirect();
    }

    $itemNameEsc = mysqli_real_escape_string($con, $itemName);
    $existingItem = cb_fetch_one($con, "SELECT * FROM tblitem WHERE UPPER(itemname)='$itemNameEsc' LIMIT 1");
    if($existingItem){
        $existingItemIdEsc = mysqli_real_escape_string($con, (string)$existingItem["itemid"]);
        if(strtolower((string)$existingItem["status"]) === "active"){
            $_SESSION['Message'] = cb_flash("warning", $itemName." already exists.");
        }else{
            mysqli_query($con, "UPDATE tblitem SET itemname='$itemNameEsc', status='active', recordedby='".mysqli_real_escape_string($con, (string)$_SESSION['USERID'])."' WHERE itemid='$existingItemIdEsc' LIMIT 1");
            $_SESSION['Message'] = cb_flash("success", $itemName." was restored to active billing items.");
        }
        cb_redirect();
    }

    $itemId = cb_generate_item_id();
    $itemIdEsc = mysqli_real_escape_string($con, $itemId);
    $recordedByEsc = mysqli_real_escape_string($con, (string)$_SESSION['USERID']);
    $branchEsc = mysqli_real_escape_string($con, (string)$_SESSION['BRANCHID']);
    $insertItem = mysqli_query($con, "INSERT INTO tblitem(itemid,itemname,datetimeentry,recordedby,status,branchid)
        VALUES('$itemIdEsc','$itemNameEsc',NOW(),'$recordedByEsc','active','$branchEsc')");
    if($insertItem){
        $_SESSION['Message'] = cb_flash("success", $itemName." added to billing items.");
    }else{
        $_SESSION['Message'] = cb_flash("error", "Billing item could not be saved. ".mysqli_error($con));
    }
    cb_redirect();
}

if($cbCanManageBillingCatalog && isset($_POST["update_item_entry"])){
    $itemId = trim((string)$_POST["update_itemid"]);
    $itemName = strtoupper(trim((string)$_POST["update_item"]));
    if($itemId === "" || $itemName === ""){
        $_SESSION['Message'] = cb_flash("error", "The billing item update is incomplete.");
        cb_redirect();
    }

    $itemIdEsc = mysqli_real_escape_string($con, $itemId);
    $itemNameEsc = mysqli_real_escape_string($con, $itemName);
    $duplicate = cb_fetch_one($con, "SELECT * FROM tblitem WHERE UPPER(itemname)='$itemNameEsc' AND itemid<>'$itemIdEsc' LIMIT 1");
    if($duplicate && strtolower((string)$duplicate["status"]) === "active"){
        $_SESSION['Message'] = cb_flash("warning", $itemName." already exists.");
        cb_redirect(array("edit_item" => $itemId));
    }

    $updateItem = mysqli_query($con, "UPDATE tblitem SET itemname='$itemNameEsc' WHERE itemid='$itemIdEsc' LIMIT 1");
    if($updateItem){
        $_SESSION['Message'] = cb_flash("success", $itemName." updated successfully.");
    }else{
        $_SESSION['Message'] = cb_flash("error", "Billing item could not be updated. ".mysqli_error($con));
    }
    cb_redirect();
}

if($cbCanManageBillingCatalog && isset($_GET["delete_item"])){
    $itemId = trim((string)$_GET["delete_item"]);
    if($itemId !== ""){
        $itemIdEsc = mysqli_real_escape_string($con, $itemId);
        $itemRow = cb_fetch_one($con, "SELECT * FROM tblitem WHERE itemid='$itemIdEsc' LIMIT 1");
        if($itemRow){
            if(cb_item_has_price_rows($con, $itemId)){
                mysqli_query($con, "UPDATE tblitem SET status='inactive' WHERE itemid='$itemIdEsc' LIMIT 1");
                $_SESSION['Message'] = cb_flash("warning", $itemRow["itemname"]." has billing history, so it was archived from future billing instead of being deleted.");
            }else{
                mysqli_query($con, "DELETE FROM tblitem WHERE itemid='$itemIdEsc' LIMIT 1");
                $_SESSION['Message'] = cb_flash("success", $itemRow["itemname"]." deleted successfully.");
            }
        }
    }
    cb_redirect();
}

if($cbCanManageBillingCatalog && isset($_POST["save_price_item"])){
    $classId = trim((string)$_POST["class_entryid"]);
    $batchId = trim((string)$_POST["batchid"]);
    $term = trim((string)$_POST["term"]);
    $itemId = trim((string)$_POST["itemid"]);
    $priceRaw = trim((string)$_POST["price"]);
    $price = is_numeric($priceRaw) ? (float)$priceRaw : -1;

    if($classId === "" || $batchId === "" || $term === "" || $itemId === "" || $price < 0){
        $_SESSION['Message'] = cb_flash("error", "Select class, batch, semester, item, and a valid price.");
        cb_redirect();
    }

    $classEsc = mysqli_real_escape_string($con, $classId);
    $batchEsc = mysqli_real_escape_string($con, $batchId);
    $termEsc = mysqli_real_escape_string($con, $term);
    $itemEsc = mysqli_real_escape_string($con, $itemId);
    $priceEsc = mysqli_real_escape_string($con, (string)$price);
    $recordedByEsc = mysqli_real_escape_string($con, (string)$_SESSION['USERID']);
    $branchEsc = mysqli_real_escape_string($con, (string)$_SESSION['BRANCHID']);

    $activeDuplicate = cb_fetch_one($con, "SELECT * FROM tblitemprice WHERE class_entryid='$classEsc' AND batch='$batchEsc' AND term='$termEsc' AND itemid='$itemEsc' AND status='active' LIMIT 1");
    if($activeDuplicate){
        $_SESSION['Message'] = cb_flash("warning", "That item is already active for the selected class, batch, and semester.");
        cb_redirect();
    }

    $inactiveMatch = cb_fetch_one($con, "SELECT * FROM tblitemprice WHERE class_entryid='$classEsc' AND batch='$batchEsc' AND term='$termEsc' AND itemid='$itemEsc' ORDER BY datetimeprice DESC LIMIT 1");
    if($inactiveMatch && strtolower((string)$inactiveMatch["status"]) !== "active" && !cb_price_has_bills($con, $inactiveMatch["itempriceid"])){
        $itemPriceEsc = mysqli_real_escape_string($con, (string)$inactiveMatch["itempriceid"]);
        $reactivate = mysqli_query($con, "UPDATE tblitemprice SET price='$priceEsc', status='active', datetimeprice=NOW(), recordedby='$recordedByEsc', branchid='$branchEsc' WHERE itempriceid='$itemPriceEsc' LIMIT 1");
        if($reactivate){
            $_SESSION['Message'] = cb_flash("success", "Billing price restored and updated.");
        }else{
            $_SESSION['Message'] = cb_flash("error", "Billing price could not be restored. ".mysqli_error($con));
        }
        cb_redirect();
    }

    $itemPriceId = cb_generate_price_id();
    $itemPriceEsc = mysqli_real_escape_string($con, $itemPriceId);
    $insertPrice = mysqli_query($con, "INSERT INTO tblitemprice(itempriceid,class_entryid,term,batch,itemid,price,datetimeprice,status,recordedby,branchid)
        VALUES('$itemPriceEsc','$classEsc','$termEsc','$batchEsc','$itemEsc','$priceEsc',NOW(),'active','$recordedByEsc','$branchEsc')");
    if($insertPrice){
        $_SESSION['Message'] = cb_flash("success", "Class billing item saved successfully.");
    }else{
        $_SESSION['Message'] = cb_flash("error", "Class billing item could not be saved. ".mysqli_error($con));
    }
    cb_redirect();
}

if($cbCanManageBillingCatalog && isset($_POST["price_item_update"])){
    $itemPriceId = trim((string)$_POST["itempriceid"]);
    $priceRaw = trim((string)$_POST["price"]);
    $price = is_numeric($priceRaw) ? (float)$priceRaw : -1;

    if($itemPriceId === "" || $price < 0){
        $_SESSION['Message'] = cb_flash("error", "Enter a valid price before updating.");
        cb_redirect();
    }

    $itemPriceIdEsc = mysqli_real_escape_string($con, $itemPriceId);
    $priceEsc = mysqli_real_escape_string($con, (string)$price);
    $recordedByEsc = mysqli_real_escape_string($con, (string)$_SESSION['USERID']);
    $branchEsc = mysqli_real_escape_string($con, (string)$_SESSION['BRANCHID']);
    $priceRow = cb_fetch_one($con, "SELECT * FROM tblitemprice WHERE itempriceid='$itemPriceIdEsc' LIMIT 1");

    if(!$priceRow){
        $_SESSION['Message'] = cb_flash("error", "The selected class billing record could not be found.");
        cb_redirect();
    }

    if(cb_price_has_bills($con, $itemPriceId)){
        mysqli_query($con, "UPDATE tblitemprice SET status='inactive' WHERE itempriceid='$itemPriceIdEsc' LIMIT 1");
        $newItemPriceId = cb_generate_price_id();
        $newItemPriceIdEsc = mysqli_real_escape_string($con, $newItemPriceId);
        $classEsc = mysqli_real_escape_string($con, (string)$priceRow["class_entryid"]);
        $batchEsc = mysqli_real_escape_string($con, (string)$priceRow["batch"]);
        $termEsc = mysqli_real_escape_string($con, (string)$priceRow["term"]);
        $itemEsc = mysqli_real_escape_string($con, (string)$priceRow["itemid"]);
        $insertUpdated = mysqli_query($con, "INSERT INTO tblitemprice(itempriceid,class_entryid,term,batch,itemid,price,datetimeprice,status,recordedby,branchid)
            VALUES('$newItemPriceIdEsc','$classEsc','$termEsc','$batchEsc','$itemEsc','$priceEsc',NOW(),'active','$recordedByEsc','$branchEsc')");
        if($insertUpdated){
            $_SESSION['Message'] = cb_flash("warning", "The old billing price already had billed students, so it was archived and a new active price was created.");
        }else{
            $_SESSION['Message'] = cb_flash("error", "The new billing price could not be created. ".mysqli_error($con));
        }
    }else{
        $updatePrice = mysqli_query($con, "UPDATE tblitemprice SET price='$priceEsc', status='active', recordedby='$recordedByEsc' WHERE itempriceid='$itemPriceIdEsc' LIMIT 1");
        if($updatePrice){
            $_SESSION['Message'] = cb_flash("success", "Class billing price updated successfully.");
        }else{
            $_SESSION['Message'] = cb_flash("error", "Class billing price could not be updated. ".mysqli_error($con));
        }
    }
    cb_redirect();
}

if($cbCanManageBillingCatalog && isset($_GET["delete_price_item"])){
    $itemPriceId = trim((string)$_GET["delete_price_item"]);
    if($itemPriceId !== ""){
        $itemPriceIdEsc = mysqli_real_escape_string($con, $itemPriceId);
        $priceRow = cb_fetch_one($con, "SELECT ip.*, itm.itemname FROM tblitemprice ip INNER JOIN tblitem itm ON ip.itemid=itm.itemid WHERE ip.itempriceid='$itemPriceIdEsc' LIMIT 1");
        if($priceRow){
            if(cb_price_has_bills($con, $itemPriceId)){
                mysqli_query($con, "UPDATE tblitemprice SET status='inactive' WHERE itempriceid='$itemPriceIdEsc' LIMIT 1");
                $_SESSION['Message'] = cb_flash("warning", $priceRow["itemname"]." already has billed students, so it was archived from future billing.");
            }else{
                mysqli_query($con, "DELETE FROM tblitemprice WHERE itempriceid='$itemPriceIdEsc' LIMIT 1");
                $_SESSION['Message'] = cb_flash("success", $priceRow["itemname"]." removed from class billing.");
            }
        }
    }
    cb_redirect();
}

$itemEditRow = null;
if($editItemId !== ""){
    $itemEditRow = cb_fetch_one($con, "SELECT * FROM tblitem WHERE itemid='".mysqli_real_escape_string($con, $editItemId)."' LIMIT 1");
}

$priceEditRow = null;
if($editPriceId !== ""){
    $priceEditRow = cb_fetch_one($con, "SELECT ip.*, itm.itemname, ce.class_name, b.batch AS batch_name
        FROM tblitemprice ip
        INNER JOIN tblitem itm ON ip.itemid=itm.itemid
        INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
        INNER JOIN tblbatch b ON ip.batch=b.batchid
        WHERE ip.itempriceid='".mysqli_real_escape_string($con, $editPriceId)."'
        LIMIT 1");
}

$classOptions = array();
$batchOptions = array();
$activeItems = array();
$allItems = array();
$allPriceRows = array();

$classOptions = $cbCanManageBillingCatalog ? array() : $cbScopeClassRows;
$batchOptions = $cbCanManageBillingCatalog ? array() : $cbScopeBatchRows;
if($cbCanManageBillingCatalog){
    $classResult = mysqli_query($con, "SELECT * FROM tblclassentry ORDER BY class_name ASC");
    if($classResult){
        while($row = mysqli_fetch_array($classResult, MYSQLI_ASSOC)){
            $classOptions[] = $row;
        }
    }

    $batchResult = mysqli_query($con, "SELECT * FROM tblbatch ORDER BY batch ASC");
    if($batchResult){
        while($row = mysqli_fetch_array($batchResult, MYSQLI_ASSOC)){
            $batchOptions[] = $row;
        }
    }
}

$activeItemSql = $cbCanManageBillingCatalog
    ? "SELECT * FROM tblitem WHERE status='active' ORDER BY itemname ASC"
    : "SELECT * FROM tblitem WHERE status='active' ORDER BY itemname ASC";
$activeItemResult = mysqli_query($con, $activeItemSql);
if($activeItemResult){
    while($row = mysqli_fetch_array($activeItemResult, MYSQLI_ASSOC)){
        $activeItems[] = $row;
    }
}

$allItemSql = $cbCanManageBillingCatalog
    ? "SELECT * FROM tblitem ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END, itemname ASC"
    : "SELECT * FROM tblitem WHERE status='active' ORDER BY itemname ASC";
$allItemResult = mysqli_query($con, $allItemSql);
if($allItemResult){
    while($row = mysqli_fetch_array($allItemResult, MYSQLI_ASSOC)){
        $allItems[] = $row;
    }
}

$priceScopeFilter = "";
if(!$cbCanManageBillingCatalog){
    $priceScopeFilter = " WHERE ".teacher_billing_allowed_scope_sql($con, $cbScopeTeacherId, "ip.class_entryid", "ip.batch", "ip.term");
}
$priceResult = mysqli_query($con, "SELECT ip.*, itm.itemname, itm.status AS item_status, ce.class_name, b.batch AS batch_name
    FROM tblitemprice ip
    INNER JOIN tblitem itm ON ip.itemid=itm.itemid
    INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
    INNER JOIN tblbatch b ON ip.batch=b.batchid
    $priceScopeFilter
    ORDER BY ce.class_name ASC, ip.term ASC, b.batch ASC, itm.itemname ASC");
if($priceResult){
    while($row = mysqli_fetch_array($priceResult, MYSQLI_ASSOC)){
        $allPriceRows[] = $row;
    }
}

$activeItemCount = cb_count_value($con, "SELECT COUNT(*) AS total_count FROM tblitem WHERE status='active'", "total_count");
$inactiveItemCount = $cbCanManageBillingCatalog ? cb_count_value($con, "SELECT COUNT(*) AS total_count FROM tblitem WHERE status<>'active' OR status IS NULL", "total_count") : 0;
$activePriceCount = $cbCanManageBillingCatalog
    ? cb_count_value($con, "SELECT COUNT(*) AS total_count FROM tblitemprice WHERE status='active'", "total_count")
    : count(array_filter($allPriceRows, function($row){ return strtolower((string)$row["status"]) === "active"; }));
$inactivePriceCount = $cbCanManageBillingCatalog
    ? cb_count_value($con, "SELECT COUNT(*) AS total_count FROM tblitemprice WHERE status<>'active' OR status IS NULL", "total_count")
    : count(array_filter($allPriceRows, function($row){ return strtolower((string)$row["status"]) !== "active"; }));
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/class-billing.css?v=20260514">
</head>
<body class="billing-manager-page">
    <div class="header">
    <?php include("menu.php"); ?>
    </div>

    <main class="billing-manager-shell">
        <?php if($flashMessage !== ""){ ?>
        <div class="billing-manager-message"><?php echo $flashMessage; ?></div>
        <?php } ?>

        <section class="billing-manager-hero">
            <div class="billing-manager-hero__copy">
                <span class="billing-manager-eyebrow">Billing Manager</span>
                <h1><?php echo $cbCanManageBillingCatalog ? "Billing setup in one place" : "Billing items for your assigned classes"; ?></h1>
                <p><?php echo $cbCanManageBillingCatalog ? "Manage billing items, assign class prices by batch and semester, and retire items safely without breaking old billing history." : "Review the active billing items and class prices available for the classes, batches, and semesters assigned to you."; ?></p>
            </div>
            <div class="billing-manager-hero__meta">
                <span class="billing-manager-chip"><i class="fa fa-list"></i> One item dropdown for class billing</span>
                <?php if($cbCanManageBillingCatalog){ ?>
                <span class="billing-manager-chip"><i class="fa fa-archive"></i> Safe archive when history already exists</span>
                <a href="teacher-billing-assignment.php" class="billing-manager-chip" style="text-decoration:none;"><i class="fa fa-users"></i> Teacher Billing Assignment</a>
                <?php }else{ ?>
                <span class="billing-manager-chip"><i class="fa fa-lock"></i> Read-only teacher view</span>
                <?php } ?>
            </div>
        </section>

        <?php if($cbCanManageBillingCatalog){ ?>
        <section class="billing-manager-panel" style="margin-bottom:24px;">
            <div class="billing-manager-panel__head">
                <div>
                    <span class="billing-manager-eyebrow">Teacher Billing Access</span>
                    <h2>Assign teachers to bill specific classes</h2>
                </div>
                <div class="billing-manager-panel__note">Use exact class, batch, and semester scope so teachers bill only the students they are responsible for.</div>
            </div>
            <div class="billing-manager-form__actions">
                <a href="teacher-billing-assignment.php" class="billing-manager-primary-btn" style="text-decoration:none;"><i class="fa fa-users"></i> Open Teacher Billing Assignment</a>
            </div>
        </section>
        <?php }else{ ?>
        <section class="billing-manager-panel" style="margin-bottom:24px;">
            <div class="billing-manager-panel__head">
                <div>
                    <span class="billing-manager-eyebrow">Teacher Access</span>
                    <h2>Read-only billing catalogue</h2>
                </div>
                <div class="billing-manager-panel__note">Use this page to confirm the billing items and prices already prepared for your assigned class scopes. Billing itself still happens from the billing screens.</div>
            </div>
        </section>
        <?php } ?>

        <section class="billing-manager-stats" aria-label="Billing Summary">
            <article class="billing-manager-stat">
                <span>Active Items</span>
                <strong><?php echo number_format($activeItemCount); ?></strong>
            </article>
            <article class="billing-manager-stat">
                <span>Archived Items</span>
                <strong><?php echo number_format($inactiveItemCount); ?></strong>
            </article>
            <article class="billing-manager-stat">
                <span>Active Class Prices</span>
                <strong><?php echo number_format($activePriceCount); ?></strong>
            </article>
            <article class="billing-manager-stat">
                <span>Archived Class Prices</span>
                <strong><?php echo number_format($inactivePriceCount); ?></strong>
            </article>
        </section>

        <?php if($cbCanManageBillingCatalog){ ?>
        <div class="billing-manager-form-grid">
            <section class="billing-manager-panel">
                <div class="billing-manager-panel__head">
                    <div>
                        <span class="billing-manager-eyebrow">Billing Items</span>
                        <h2><?php echo $itemEditRow ? "Update Billing Item" : "Add Billing Item"; ?></h2>
                    </div>
                </div>
                <form method="post" class="billing-manager-form">
                    <?php if($itemEditRow){ ?>
                    <input type="hidden" name="update_itemid" value="<?php echo cb_safe($itemEditRow["itemid"]); ?>">
                    <?php } ?>
                    <label class="billing-manager-field">
                        <span>Item Name</span>
                        <input type="text" name="<?php echo $itemEditRow ? "update_item" : "item_name"; ?>" value="<?php echo cb_safe($itemEditRow ? $itemEditRow["itemname"] : ""); ?>" placeholder="Enter billing item name" required>
                    </label>
                    <div class="billing-manager-form__actions">
                        <?php if($itemEditRow){ ?>
                        <button type="submit" name="update_item_entry" class="billing-manager-primary-btn"><i class="fa fa-edit"></i> Update Item</button>
                        <a href="class-billing.php" class="billing-manager-secondary-link"><i class="fa fa-times"></i> Cancel</a>
                        <?php }else{ ?>
                        <button type="submit" name="save_item_entry" class="billing-manager-primary-btn"><i class="fa fa-save"></i> Save Item</button>
                        <?php } ?>
                    </div>
                </form>
            </section>

            <section class="billing-manager-panel">
                <div class="billing-manager-panel__head">
                    <div>
                        <span class="billing-manager-eyebrow">Class Billing</span>
                        <h2><?php echo $priceEditRow ? "Update Class Billing Price" : "Assign Class Billing Price"; ?></h2>
                    </div>
                </div>
                <form method="post" class="billing-manager-form">
                    <?php if($priceEditRow){ ?>
                    <input type="hidden" name="itempriceid" value="<?php echo cb_safe($priceEditRow["itempriceid"]); ?>">
                    <label class="billing-manager-field">
                        <span>Class</span>
                        <input type="text" value="<?php echo cb_safe($priceEditRow["class_name"]); ?>" readonly>
                    </label>
                    <label class="billing-manager-field">
                        <span>Batch</span>
                        <input type="text" value="<?php echo cb_safe($priceEditRow["batch_name"]); ?>" readonly>
                    </label>
                    <label class="billing-manager-field">
                        <span>Semester</span>
                        <input type="text" value="<?php echo cb_safe($priceEditRow["term"]); ?>" readonly>
                    </label>
                    <label class="billing-manager-field">
                        <span>Item</span>
                        <input type="text" value="<?php echo cb_safe($priceEditRow["itemname"]); ?>" readonly>
                    </label>
                    <label class="billing-manager-field">
                        <span>Price</span>
                        <input type="number" step="0.01" min="0" name="price" value="<?php echo cb_safe($priceEditRow["price"]); ?>" required>
                    </label>
                    <div class="billing-manager-form__actions">
                        <button type="submit" name="price_item_update" class="billing-manager-primary-btn"><i class="fa fa-edit"></i> Update Price</button>
                        <a href="class-billing.php" class="billing-manager-secondary-link"><i class="fa fa-times"></i> Cancel</a>
                    </div>
                    <?php }else{ ?>
                    <label class="billing-manager-field">
                        <span>Class</span>
                        <select name="class_entryid" required>
                            <option value="">Select Class</option>
                            <?php foreach($classOptions as $row){ ?>
                            <option value="<?php echo cb_safe($row["class_entryid"]); ?>"><?php echo cb_safe($row["class_name"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label class="billing-manager-field">
                        <span>Batch</span>
                        <select name="batchid" required>
                            <option value="">Select Batch</option>
                            <?php foreach($batchOptions as $row){ ?>
                            <option value="<?php echo cb_safe($row["batchid"]); ?>"><?php echo cb_safe($row["batch"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label class="billing-manager-field">
                        <span>Semester</span>
                        <select name="term" required>
                            <option value="">Select Semester</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                    </label>
                    <label class="billing-manager-field">
                        <span>Billing Item</span>
                        <select name="itemid" required>
                            <option value="">Select Billing Item</option>
                            <?php foreach($activeItems as $row){ ?>
                            <option value="<?php echo cb_safe($row["itemid"]); ?>"><?php echo cb_safe($row["itemname"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <label class="billing-manager-field">
                        <span>Price</span>
                        <input type="number" step="0.01" min="0" name="price" placeholder="Enter price" required>
                    </label>
                    <div class="billing-manager-form__actions">
                        <button type="submit" name="save_price_item" class="billing-manager-primary-btn"><i class="fa fa-save"></i> Save Class Billing</button>
                    </div>
                    <?php } ?>
                </form>

            </section>
        </div>
        <?php } ?>

        <section class="billing-manager-panel">
            <div class="billing-manager-panel__head">
                <div>
                    <span class="billing-manager-eyebrow">Item Catalogue</span>
                    <h2>Billing Items</h2>
                </div>
                <div class="billing-manager-panel__note"><?php echo $cbCanManageBillingCatalog ? "Deleting an item with existing pricing history archives it instead of breaking old records." : "Teachers can view active billing items here."; ?></div>
            </div>
            <?php if(count($allItems) > 0){ ?>
            <div class="billing-manager-table-wrap">
                <table class="billing-manager-table">
                    <thead>
                        <tr>
                            <th>Item ID</th>
                            <th>Item</th>
                            <th>Entry Date</th>
                            <th>Status</th>
                            <?php if($cbCanManageBillingCatalog){ ?><th class="billing-manager-actions-col">Task</th><?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allItems as $row){ ?>
                        <tr>
                            <td data-label="Item ID"><?php echo cb_safe($row["itemid"]); ?></td>
                            <td data-label="Item"><?php echo cb_safe($row["itemname"]); ?></td>
                            <td data-label="Entry Date"><?php echo cb_safe(cb_format_date($row["datetimeentry"])); ?></td>
                            <td data-label="Status">
                                <span class="billing-manager-badge billing-manager-badge--<?php echo (strtolower((string)$row["status"]) === "active" ? "active" : "inactive"); ?>">
                                    <?php echo cb_safe((strtolower((string)$row["status"]) === "active" ? "Active" : "Archived")); ?>
                                </span>
                            </td>
                            <?php if($cbCanManageBillingCatalog){ ?>
                            <td data-label="Task" class="billing-manager-actions-cell">
                                <div class="billing-manager-actions">
                                    <a href="class-billing.php?edit_item=<?php echo urlencode((string)$row["itemid"]); ?>" class="billing-manager-action billing-manager-action--edit"><i class="fa fa-edit"></i> Edit</a>
                                    <a href="class-billing.php?delete_item=<?php echo urlencode((string)$row["itemid"]); ?>" class="billing-manager-action billing-manager-action--delete" onclick="return confirm('Remove this billing item?');"><i class="fa fa-trash-o"></i> Delete</a>
                                </div>
                            </td>
                            <?php } ?>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php }else{ ?>
            <div class="billing-manager-empty"><i class="fa fa-inbox"></i><p>No billing items have been created yet.</p></div>
            <?php } ?>
        </section>

        <section class="billing-manager-panel">
            <div class="billing-manager-panel__head">
                <div>
                    <span class="billing-manager-eyebrow">Class Pricing</span>
                    <h2>Class Billing Records</h2>
                </div>
                <div class="billing-manager-panel__note"><?php echo $cbCanManageBillingCatalog ? "Archived class-price rows stay out of future billing but remain available for old bill history." : "Only price rows within your assigned billing scope are shown here."; ?></div>
            </div>
            <?php if(count($allPriceRows) > 0){ ?>
            <div class="billing-manager-table-wrap">
                <table class="billing-manager-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Semester</th>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Entry Date</th>
                            <th>Status</th>
                            <?php if($cbCanManageBillingCatalog){ ?><th class="billing-manager-actions-col">Task</th><?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allPriceRows as $row){ ?>
                        <tr>
                            <td data-label="Class"><?php echo cb_safe($row["class_name"]); ?></td>
                            <td data-label="Batch"><?php echo cb_safe($row["batch_name"]); ?></td>
                            <td data-label="Semester"><?php echo cb_safe($row["term"]); ?></td>
                            <td data-label="Item"><?php echo cb_safe($row["itemname"]); ?></td>
                            <td data-label="Price">GHS <?php echo cb_safe(cb_format_money($row["price"])); ?></td>
                            <td data-label="Entry Date"><?php echo cb_safe(cb_format_date($row["datetimeprice"])); ?></td>
                            <td data-label="Status">
                                <span class="billing-manager-badge billing-manager-badge--<?php echo (strtolower((string)$row["status"]) === "active" ? "active" : "inactive"); ?>">
                                    <?php echo cb_safe((strtolower((string)$row["status"]) === "active" ? "Active" : "Archived")); ?>
                                </span>
                            </td>
                            <?php if($cbCanManageBillingCatalog){ ?>
                            <td data-label="Task" class="billing-manager-actions-cell">
                                <div class="billing-manager-actions">
                                    <a href="class-billing.php?price_item=<?php echo urlencode((string)$row["itempriceid"]); ?>" class="billing-manager-action billing-manager-action--edit"><i class="fa fa-edit"></i> Edit</a>
                                    <a href="class-billing.php?delete_price_item=<?php echo urlencode((string)$row["itempriceid"]); ?>" class="billing-manager-action billing-manager-action--delete" onclick="return confirm('Remove this class billing item?');"><i class="fa fa-trash-o"></i> Delete</a>
                                </div>
                            </td>
                            <?php } ?>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php }else{ ?>
            <div class="billing-manager-empty"><i class="fa fa-credit-card"></i><p>No class billing prices have been created yet.</p></div>
            <?php } ?>
        </section>
    </main>
</body>
</html>

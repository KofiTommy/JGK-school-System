<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("audit_notifications.php");
include_once("user-management-utils.php");

ensure_user_management_columns($con);

if(!um_is_admin_manager()){
    header("location:index.php");
    exit();
}

function umh($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function um_flash($type, $message){
    $class = "um-alert um-alert--info";
    if($type === "success"){ $class = "um-alert um-alert--success"; }
    elseif($type === "warning"){ $class = "um-alert um-alert--warning"; }
    elseif($type === "error"){ $class = "um-alert um-alert--error"; }
    return "<div class=\"".$class."\">".umh($message)."</div>";
}

function um_url($params = array(), $anchor = ''){
    $query = $_GET;
    foreach($params as $key => $value){
        if($value === null || $value === ''){
            unset($query[$key]);
        }else{
            $query[$key] = $value;
        }
    }
    $url = "user-management.php";
    $qs = http_build_query($query);
    if($qs !== ''){
        $url .= "?".$qs;
    }
    if($anchor !== ''){
        $url .= $anchor;
    }
    return $url;
}

function um_redirect($params = array(), $anchor = '#user-directory'){
    header("location:".um_url($params, $anchor));
    exit();
}

function um_user_image_src($userRow){
    $defaultImage = "uploads/comm.gif";
    $filename = trim((string)(isset($userRow["filename"]) ? $userRow["filename"] : ""));
    if($filename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename)){
        return "uploads/".rawurlencode($filename);
    }
    return $defaultImage;
}

function um_directory_should_open($isSuperAdmin, $search, $roleFilter, $statusFilter, $branchFilter){
    if(isset($_GET["directory"]) && trim((string)$_GET["directory"]) === "1"){
        return true;
    }
    if($search !== "" || $roleFilter !== "all" || $statusFilter !== "all"){
        return true;
    }
    if($isSuperAdmin && $branchFilter !== ""){
        return true;
    }
    return false;
}

function um_form_defaults($branchId){
    return array(
        "userid" => um_generate_userid("office_user"),
        "firstname" => "",
        "surname" => "",
        "othernames" => "",
        "gender" => "",
        "birthday" => "",
        "email" => "",
        "mobile" => "",
        "username" => "",
        "status" => "active",
        "role_key" => "office_user",
        "branchid" => $branchId,
        "temp_password" => um_generate_temp_password(8),
        "module_keys" => array()
    );
}

$isSuperAdmin = um_is_super_admin_manager();
$currentBranchId = isset($_SESSION["BRANCHID"]) ? trim((string)$_SESSION["BRANCHID"]) : "";
$availableRoles = um_available_creation_roles();
$allRoleProfiles = um_role_profiles();
$moduleCatalog = um_module_catalog();
$moduleGroups = um_module_groups();

$branches = array();
$branchRes = @mysqli_query($con, "SELECT branchid,location FROM tblbranch ORDER BY location ASC");
if($branchRes){
    while($branchRow = mysqli_fetch_array($branchRes, MYSQLI_ASSOC)){
        $branches[] = $branchRow;
    }
}

$search = isset($_GET["q"]) ? trim((string)$_GET["q"]) : "";
$roleFilter = isset($_GET["role"]) ? trim((string)$_GET["role"]) : "all";
$statusFilter = isset($_GET["status"]) ? trim((string)$_GET["status"]) : "all";
$branchFilter = $isSuperAdmin ? trim((string)(isset($_GET["branch"]) ? $_GET["branch"] : "")) : $currentBranchId;
$editUserId = isset($_GET["edit_user"]) ? trim((string)$_GET["edit_user"]) : "";

$formMode = "create";
$formData = um_form_defaults($branchFilter !== "" ? $branchFilter : $currentBranchId);
$editingUser = null;
$formPermissionOnly = false;

$persist = array(
    "q" => $search,
    "role" => $roleFilter,
    "status" => $statusFilter,
    "branch" => $isSuperAdmin ? $branchFilter : null
);
$directoryRequested = um_directory_should_open($isSuperAdmin, $search, $roleFilter, $statusFilter, $branchFilter);

if(isset($_POST["save_user_account"])){
    $formMode = trim((string)(isset($_POST["form_mode"]) ? $_POST["form_mode"] : "create")) === "update" ? "update" : "create";
    $userid = trim((string)(isset($_POST["userid"]) ? $_POST["userid"] : ""));
    $firstname = trim((string)(isset($_POST["firstname"]) ? $_POST["firstname"] : ""));
    $surname = trim((string)(isset($_POST["surname"]) ? $_POST["surname"] : ""));
    $othernames = trim((string)(isset($_POST["othernames"]) ? $_POST["othernames"] : ""));
    $gender = trim((string)(isset($_POST["gender"]) ? $_POST["gender"] : ""));
    $birthday = trim((string)(isset($_POST["birthday"]) ? $_POST["birthday"] : ""));
    $email = trim((string)(isset($_POST["email"]) ? $_POST["email"] : ""));
    $mobile = trim((string)(isset($_POST["mobile"]) ? $_POST["mobile"] : ""));
    $username = trim((string)(isset($_POST["username"]) ? $_POST["username"] : ""));
    $status = trim((string)(isset($_POST["status"]) ? $_POST["status"] : "active"));
    $roleKey = trim((string)(isset($_POST["role_key"]) ? $_POST["role_key"] : "office_user"));
    $branchId = $isSuperAdmin ? trim((string)(isset($_POST["branchid"]) ? $_POST["branchid"] : "")) : $currentBranchId;
    $tempPassword = trim((string)(isset($_POST["temp_password"]) ? $_POST["temp_password"] : ""));
    $moduleKeys = um_normalize_module_keys(isset($_POST["module_keys"]) ? $_POST["module_keys"] : array());
    $resolvedRoleKey = $roleKey;
    $targetForUpdate = null;

    $formData = array(
        "userid" => $userid,
        "firstname" => $firstname,
        "surname" => $surname,
        "othernames" => $othernames,
        "gender" => $gender,
        "birthday" => $birthday,
        "email" => $email,
        "mobile" => $mobile,
        "username" => $username,
        "status" => ($status === "block" ? "block" : "active"),
        "role_key" => $roleKey,
        "branchid" => $branchId,
        "temp_password" => $tempPassword,
        "module_keys" => $moduleKeys
    );

    if($formMode === "update" && $userid !== ""){
        $targetForUpdate = um_fetch_user_row($con, $userid);
        if($targetForUpdate){
            $resolvedRoleKey = um_role_key_from_user($targetForUpdate);
            $roleKey = $resolvedRoleKey;
            $formData["role_key"] = $resolvedRoleKey;
        }
    }

    $errors = array();
    if($firstname === "" || $surname === "" || $username === ""){
        $errors[] = "First name, surname, and username are required.";
    }
    if($gender === "" || $birthday === ""){
        $errors[] = "Gender and birthday are required.";
    }
    if($formMode === "create"){
        if(!isset($allRoleProfiles[$roleKey])){
            $errors[] = "Choose a valid role.";
        }elseif(!$isSuperAdmin && !empty($allRoleProfiles[$roleKey]["super_only"])){
            $errors[] = "Only a system administrator can assign that role.";
        }
    }elseif($formMode === "update"){
        if(!$targetForUpdate){
            $errors[] = "That account could not be found.";
        }elseif(!um_can_manage_row($targetForUpdate)){
            $errors[] = "You cannot edit that account.";
        }elseif($resolvedRoleKey !== "teacher" && !isset($allRoleProfiles[$resolvedRoleKey])){
            $errors[] = "This editor only updates office, administrator, and teacher accounts.";
        }
    }
    if($branchId === ""){
        $errors[] = "Branch is required.";
    }
    if($formMode === "create" && strlen($tempPassword) < 6){
        $errors[] = "Temporary password must be at least 6 characters.";
    }
    $allowedModuleKeys = um_assignable_module_keys_for_role($resolvedRoleKey);
    if($formMode === "create" && empty($moduleKeys)){
        $defaultRoleModules = function_exists('um_default_module_keys_for_role') ? um_default_module_keys_for_role($resolvedRoleKey) : array();
        if(!empty($defaultRoleModules)){
            $moduleKeys = um_normalize_module_keys($defaultRoleModules);
            $formData["module_keys"] = $moduleKeys;
        }
    }
    if(!empty(array_diff($moduleKeys, $allowedModuleKeys))){
        $errors[] = "Choose only the allowed modules for that account type.";
    }

    $age = um_age_from_birthdate($birthday);
    $nextOfKinName = trim($firstname." ".$othernames." ".$surname);
    if($nextOfKinName === ""){
        $nextOfKinName = "N/A";
    }
    $nextOfKinContact = $mobile !== "" ? $mobile : "0000000000";

    if(empty($errors) && $formMode === "create"){
        $userid = um_generate_userid($roleKey);
        $formData["userid"] = $userid;
        $checkStmt = @mysqli_prepare($con, "SELECT userid FROM tblsystemuser WHERE userid=? OR username=? LIMIT 1");
        if($checkStmt){
            mysqli_stmt_bind_param($checkStmt, "ss", $userid, $username);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            if($checkResult && mysqli_num_rows($checkResult) > 0){
                $errors[] = "That user ID or username already exists.";
            }
            mysqli_stmt_close($checkStmt);
        }

        if(empty($errors)){
            $passwordHash = md5($tempPassword);
            $passwordResetRequired = 1;
            $resetAt = date("Y-m-d H:i:s");
            $insertStmt = @mysqli_prepare($con, "INSERT INTO tblsystemuser
                (userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,email,mobile,registereddatetime,status,username,password,password_reset_required,password_last_reset_at,module_permission_mode,accesslevel,systemtype,branchid)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?,?)");
            if($insertStmt){
                $blank = "";
                $relationship = "Other";
                $permissionMode = "custom";
                mysqli_stmt_bind_param($insertStmt, "ssssssissssssssssssisssss",
                    $userid, $firstname, $surname, $othernames, $gender, $birthday, $age,
                    $blank, $blank, $blank, $blank, $relationship, $nextOfKinName, $nextOfKinContact,
                    $email, $mobile, $status, $username, $passwordHash, $passwordResetRequired, $resetAt, $permissionMode,
                    $allRoleProfiles[$roleKey]["accesslevel"], $allRoleProfiles[$roleKey]["systemtype"], $branchId
                );
                $saved = mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
                if($saved){
                    um_save_user_module_permissions($con, $userid, $moduleKeys, isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "");
                    logSystemChange($con, "USER_ACCOUNT_CREATED", "Created account ".$userid." as ".$allRoleProfiles[$roleKey]["label"].".", $userid);
                    $_SESSION["Message"] = um_flash("success", "Account created. Temporary password: ".$tempPassword.". The user must change it on next login.");
                    um_redirect(array_merge($persist, array("edit_user" => null)), "#account-form");
                }
                $errors[] = "The account could not be created.";
            }else{
                $errors[] = "The account insert query could not be prepared.";
            }
        }
    }

    if(empty($errors) && $formMode === "update"){
        $target = $targetForUpdate ? $targetForUpdate : um_fetch_user_row($con, $userid);
        if(!$target){
            $errors[] = "That account could not be found.";
        }elseif(!um_can_manage_row($target)){
            $errors[] = "You cannot edit that account.";
        }else{
            $currentRoleKey = um_role_key_from_user($target);
            if($currentRoleKey === "teacher"){
                $modeStmt = @mysqli_prepare($con, "UPDATE tblsystemuser SET module_permission_mode='custom' WHERE userid=? LIMIT 1");
                if($modeStmt){
                    mysqli_stmt_bind_param($modeStmt, "s", $userid);
                    $savedMode = mysqli_stmt_execute($modeStmt);
                    mysqli_stmt_close($modeStmt);
                    if($savedMode){
                        um_save_user_module_permissions($con, $userid, $moduleKeys, isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "");
                        logSystemChange($con, "TEACHER_MODULE_ACCESS_UPDATED", "Updated module access for teacher ".$userid.".", $userid);
                        $_SESSION["Message"] = um_flash("success", "Teacher privileges updated successfully.");
                        um_redirect(array_merge($persist, array("edit_user" => $userid)), "#account-form");
                    }
                }
                $errors[] = "Teacher privileges could not be updated.";
            }elseif(!isset($allRoleProfiles[$currentRoleKey])){
                $errors[] = "This editor only updates office, administrator, and teacher accounts.";
            }else{
                if(isset($_SESSION["USERID"]) && $_SESSION["USERID"] === $userid){
                if($roleKey !== $currentRoleKey){
                    $errors[] = "You cannot change the role on your own active account.";
                }
                if($status !== "active"){
                    $errors[] = "You cannot block your own active account.";
                }
                }
                if($currentRoleKey === "system_admin" && ($roleKey !== "system_admin" || $status !== "active")){
                $activeSuperResult = @mysqli_query($con, "SELECT COUNT(*) AS total_active FROM tblsystemuser WHERE systemtype='super_user' AND status='active'");
                $activeSuperCount = 0;
                if($activeSuperResult && ($activeSuperRow = mysqli_fetch_array($activeSuperResult, MYSQLI_ASSOC))){
                    $activeSuperCount = (int)$activeSuperRow["total_active"];
                }
                if($activeSuperCount <= 1){
                    $errors[] = "The last active system administrator cannot be downgraded or blocked.";
                }
                }

                $checkUserStmt = @mysqli_prepare($con, "SELECT userid FROM tblsystemuser WHERE username=? AND userid<>? LIMIT 1");
                if($checkUserStmt){
                mysqli_stmt_bind_param($checkUserStmt, "ss", $username, $userid);
                mysqli_stmt_execute($checkUserStmt);
                $dupResult = mysqli_stmt_get_result($checkUserStmt);
                if($dupResult && mysqli_num_rows($dupResult) > 0){
                    $errors[] = "That username is already used by another account.";
                }
                mysqli_stmt_close($checkUserStmt);
                }

                if(empty($errors)){
                $updateStmt = @mysqli_prepare($con, "UPDATE tblsystemuser
                    SET firstname=?,surname=?,othernames=?,gender=?,birthday=?,age=?,email=?,mobile=?,username=?,status=?,module_permission_mode='custom',accesslevel=?,systemtype=?,branchid=?,nextofkin_fullname=?,nextofkin_contact=?
                    WHERE userid=? LIMIT 1");
                if($updateStmt){
                    mysqli_stmt_bind_param($updateStmt, "sssssissssssssss",
                        $firstname, $surname, $othernames, $gender, $birthday, $age, $email, $mobile, $username, $status,
                        $allRoleProfiles[$roleKey]["accesslevel"], $allRoleProfiles[$roleKey]["systemtype"], $branchId,
                        $nextOfKinName, $nextOfKinContact, $userid
                    );
                    $saved = mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);
                    if($saved){
                        um_save_user_module_permissions($con, $userid, $moduleKeys, isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "");
                        logSystemChange($con, "USER_ACCOUNT_UPDATED", "Updated account ".$userid.".", $userid);
                        $_SESSION["Message"] = um_flash("success", "Account updated successfully.");
                        um_redirect(array_merge($persist, array("edit_user" => $userid)), "#account-form");
                    }
                }
                $errors[] = "The account could not be updated.";
                }
            }
        }
    }

    if(!empty($errors)){
        $_SESSION["Message"] = um_flash("error", implode(" ", $errors));
    }
}

if(isset($_POST["block_user_account"]) || isset($_POST["unblock_user_account"])){
    $targetUserId = trim((string)(isset($_POST["target_userid"]) ? $_POST["target_userid"] : ""));
    $target = um_fetch_user_row($con, $targetUserId);
    $newStatus = isset($_POST["block_user_account"]) ? "block" : "active";
    if(!$target){
        $_SESSION["Message"] = um_flash("error", "That account could not be found.");
    }elseif(!um_can_manage_row($target)){
        $_SESSION["Message"] = um_flash("error", "You cannot manage that account.");
    }elseif(isset($_SESSION["USERID"]) && $_SESSION["USERID"] === $targetUserId && $newStatus === "block"){
        $_SESSION["Message"] = um_flash("error", "You cannot block your own account.");
    }elseif(trim((string)$target["systemtype"]) === "super_user" && trim((string)$target["status"]) === "active" && $newStatus === "block"){
        $activeSuperResult = @mysqli_query($con, "SELECT COUNT(*) AS total_active FROM tblsystemuser WHERE systemtype='super_user' AND status='active'");
        $activeSuperCount = 0;
        if($activeSuperResult && ($activeSuperRow = mysqli_fetch_array($activeSuperResult, MYSQLI_ASSOC))){
            $activeSuperCount = (int)$activeSuperRow["total_active"];
        }
        if($activeSuperCount <= 1){
            $_SESSION["Message"] = um_flash("error", "The last active system administrator cannot be blocked.");
            um_redirect(array_merge($persist, array("directory" => "1")), "#user-directory");
        }
    }else{
        $statusStmt = @mysqli_prepare($con, "UPDATE tblsystemuser SET status=? WHERE userid=? LIMIT 1");
        if($statusStmt){
            mysqli_stmt_bind_param($statusStmt, "ss", $newStatus, $targetUserId);
            $saved = mysqli_stmt_execute($statusStmt);
            mysqli_stmt_close($statusStmt);
            if($saved){
                logSystemChange($con, strtoupper($newStatus)."_USER_ACCOUNT", "Changed status for ".$targetUserId." to ".$newStatus.".", $targetUserId);
                $_SESSION["Message"] = um_flash("success", $newStatus === "active" ? "Account restored." : "Account blocked.");
            }else{
                $_SESSION["Message"] = um_flash("error", "The account status could not be updated.");
            }
        }
    }
    um_redirect(array_merge($persist, array("directory" => "1")), "#user-directory");
}

if(isset($_POST["reset_user_password"])){
    $targetUserId = trim((string)(isset($_POST["target_userid"]) ? $_POST["target_userid"] : ""));
    $target = um_fetch_user_row($con, $targetUserId);
    if(!$target){
        $_SESSION["Message"] = um_flash("error", "That account could not be found.");
    }elseif(!um_can_manage_row($target)){
        $_SESSION["Message"] = um_flash("error", "You cannot reset that account.");
    }else{
        $tempPassword = um_generate_temp_password(8);
        $passwordHash = md5($tempPassword);
        $passwordResetRequired = 1;
        $resetAt = date("Y-m-d H:i:s");
        $resetStmt = @mysqli_prepare($con, "UPDATE tblsystemuser SET password=?,password_reset_required=?,password_last_reset_at=? WHERE userid=? LIMIT 1");
        if($resetStmt){
            mysqli_stmt_bind_param($resetStmt, "siss", $passwordHash, $passwordResetRequired, $resetAt, $targetUserId);
            $saved = mysqli_stmt_execute($resetStmt);
            mysqli_stmt_close($resetStmt);
            if($saved){
                logSystemChange($con, "ADMIN_PASSWORD_RESET", "Reset password for ".$targetUserId.".", $targetUserId);
                $_SESSION["Message"] = um_flash("success", "Temporary password for ".$targetUserId.": ".$tempPassword.". The user must change it on next login.");
            }else{
                $_SESSION["Message"] = um_flash("error", "The password could not be reset.");
            }
        }
    }
    um_redirect(array_merge($persist, array("directory" => "1")), "#user-directory");
}

if(isset($_POST["delete_user_account"])){
    $targetUserId = trim((string)(isset($_POST["target_userid"]) ? $_POST["target_userid"] : ""));
    $result = um_delete_or_block_user($con, $targetUserId);
    if($result["status"] === "deleted"){
        logSystemChange($con, "DELETE_USER_ACCOUNT", "Deleted account ".$targetUserId.".", $targetUserId);
        $_SESSION["Message"] = um_flash("success", $result["message"]);
    }elseif($result["status"] === "blocked"){
        logSystemChange($con, "BLOCK_USER_ACCOUNT", "Blocked account ".$targetUserId." after delete fallback.", $targetUserId);
        $_SESSION["Message"] = um_flash("warning", $result["message"]);
    }else{
        $_SESSION["Message"] = um_flash("error", $result["message"]);
    }
    um_redirect(array_merge($persist, array("edit_user" => null, "directory" => "1")), "#user-directory");
}

if($editUserId !== ""){
    $editingUser = um_fetch_user_row($con, $editUserId);
    if(!$editingUser){
        $_SESSION["Message"] = um_flash("error", "That account could not be found.");
        $editUserId = "";
    }elseif(!um_can_manage_row($editingUser)){
        $_SESSION["Message"] = um_flash("error", "You cannot manage that account.");
        $editUserId = "";
    }else{
        $editingRoleKey = um_role_key_from_user($editingUser);
        if(isset($allRoleProfiles[$editingRoleKey]) || $editingRoleKey === "teacher"){
            $formMode = "update";
            $formPermissionOnly = ($editingRoleKey === "teacher");
            $formData = array(
                "userid" => trim((string)$editingUser["userid"]),
                "firstname" => trim((string)$editingUser["firstname"]),
                "surname" => trim((string)$editingUser["surname"]),
                "othernames" => trim((string)$editingUser["othernames"]),
                "gender" => trim((string)$editingUser["gender"]),
                "birthday" => trim((string)$editingUser["birthday"]),
                "email" => trim((string)$editingUser["email"]),
                "mobile" => trim((string)$editingUser["mobile"]),
                "username" => trim((string)$editingUser["username"]),
                "status" => trim((string)$editingUser["status"]) === "block" ? "block" : "active",
                "role_key" => $editingRoleKey,
                "branchid" => trim((string)$editingUser["branchid"]),
                "temp_password" => "",
                "module_keys" => um_get_user_module_permissions($con, trim((string)$editingUser["userid"]))
            );
        }else{
            $_SESSION["Message"] = um_flash("warning", "That account can be blocked, deleted, or reset here, but module assignment is only available for office, administrator, and teacher accounts right now.");
            $editUserId = "";
            $editingUser = null;
        }
    }
}

if($formMode === "create" && trim((string)$formData["branchid"]) === "" && !empty($branches)){
    if(!$isSuperAdmin){
        $formData["branchid"] = $currentBranchId;
    }elseif(isset($branches[0]["branchid"])){
        $formData["branchid"] = trim((string)$branches[0]["branchid"]);
    }
}

$searchEsc = mysqli_real_escape_string($con, $search);
$branchFilterEsc = mysqli_real_escape_string($con, $branchFilter);
$whereParts = array("1=1");
if(!$isSuperAdmin && $currentBranchId !== ""){
    $whereParts[] = "su.branchid='".mysqli_real_escape_string($con, $currentBranchId)."'";
}elseif($isSuperAdmin && $branchFilter !== ""){
    $whereParts[] = "su.branchid='".$branchFilterEsc."'";
}

if($statusFilter === "active" || $statusFilter === "block"){
    $whereParts[] = "su.status='".mysqli_real_escape_string($con, $statusFilter)."'";
}

if($roleFilter !== "" && $roleFilter !== "all"){
    if($roleFilter === "teacher"){
        $whereParts[] = "su.systemtype='Teacher'";
    }elseif($roleFilter === "student"){
        $whereParts[] = "su.systemtype='Student'";
    }elseif(isset($allRoleProfiles[$roleFilter])){
        $profile = $allRoleProfiles[$roleFilter];
        $whereParts[] = "su.accesslevel='".mysqli_real_escape_string($con, $profile["accesslevel"])."'";
        $whereParts[] = "su.systemtype='".mysqli_real_escape_string($con, $profile["systemtype"])."'";
    }
}

if($search !== ""){
    $like = "%".$searchEsc."%";
    $whereParts[] = "(su.userid LIKE '".$like."' OR su.username LIKE '".$like."' OR su.firstname LIKE '".$like."' OR su.surname LIKE '".$like."' OR su.othernames LIKE '".$like."' OR su.mobile LIKE '".$like."' OR su.email LIKE '".$like."')";
}

$visibleUsers = array();
$totalUsers = 0;
$activeUsers = 0;
$blockedUsers = 0;
$resetRequiredUsers = 0;
$adminUsers = 0;

$summarySql = "SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN su.status='active' THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN su.status='block' THEN 1 ELSE 0 END) AS blocked_users,
        SUM(CASE WHEN IFNULL(su.password_reset_required,0)=1 THEN 1 ELSE 0 END) AS reset_required_users,
        SUM(CASE WHEN su.accesslevel='administrator' THEN 1 ELSE 0 END) AS admin_users
    FROM tblsystemuser su
    WHERE ".implode(" AND ", $whereParts);
$summaryResult = @mysqli_query($con, $summarySql);
if($summaryResult && ($summaryRow = mysqli_fetch_array($summaryResult, MYSQLI_ASSOC))){
    $totalUsers = (int)$summaryRow["total_users"];
    $activeUsers = (int)$summaryRow["active_users"];
    $blockedUsers = (int)$summaryRow["blocked_users"];
    $resetRequiredUsers = (int)$summaryRow["reset_required_users"];
    $adminUsers = (int)$summaryRow["admin_users"];
}

if($directoryRequested){
    $userDirectorySql = "SELECT su.*, br.location AS branchname
        FROM tblsystemuser su
        LEFT JOIN tblbranch br ON su.branchid=br.branchid
        WHERE ".implode(" AND ", $whereParts)."
        ORDER BY FIELD(su.status,'active','block'), su.registereddatetime DESC, su.firstname ASC, su.surname ASC";
    $userDirectoryResult = @mysqli_query($con, $userDirectorySql);
    if($userDirectoryResult){
        while($userRow = mysqli_fetch_array($userDirectoryResult, MYSQLI_ASSOC)){
            $visibleUsers[] = $userRow;
        }
    }
}

$branchLabels = array();
foreach($branches as $branchRow){
    $branchLabels[trim((string)$branchRow["branchid"])] = trim((string)$branchRow["location"]);
}

$activeFilterChips = array();
if($search !== ""){
    $activeFilterChips[] = "Search: ".$search;
}
if($roleFilter !== "" && $roleFilter !== "all"){
    if($roleFilter === "teacher"){
        $activeFilterChips[] = "Role: Teachers";
    }elseif($roleFilter === "student"){
        $activeFilterChips[] = "Role: Students";
    }elseif(isset($allRoleProfiles[$roleFilter])){
        $activeFilterChips[] = "Role: ".$allRoleProfiles[$roleFilter]["label"];
    }
}
if($statusFilter === "active"){
    $activeFilterChips[] = "Status: Active";
}elseif($statusFilter === "block"){
    $activeFilterChips[] = "Status: Blocked";
}
if($isSuperAdmin && $branchFilter !== ""){
    $activeFilterChips[] = "Branch: ".(isset($branchLabels[$branchFilter]) ? $branchLabels[$branchFilter] : $branchFilter);
}

ob_start();
?>
                <div class="um-directory-tools">
                    <div class="um-directory-tools__copy">
                        <span class="um-section__eyebrow">Account Directory</span>
                        <h3>Find and manage visible accounts</h3>
                        <p>Search by login details, filter by role or status, and open only the accounts you need to work on.</p>
                    </div>
                    <div class="um-directory-tools__summary">
                        <span class="um-pill"><?php echo number_format(count($visibleUsers)); ?> Result<?php echo count($visibleUsers) === 1 ? "" : "s"; ?></span>
                        <?php if(!empty($activeFilterChips)){ ?>
                        <span class="um-pill"><?php echo number_format(count($activeFilterChips)); ?> Filter<?php echo count($activeFilterChips) === 1 ? "" : "s"; ?> Active</span>
                        <?php } ?>
                    </div>
                </div>

                <?php if(!empty($activeFilterChips)){ ?>
                <div class="um-active-filters">
                    <?php foreach($activeFilterChips as $filterChip){ ?>
                    <span class="um-active-filter"><?php echo umh($filterChip); ?></span>
                    <?php } ?>
                    <a class="um-active-filters__clear" href="user-management.php?directory=1#user-directory">Clear Filters</a>
                </div>
                <?php } ?>

                <form method="get" action="user-management.php" class="um-filter-bar">
                    <input type="hidden" name="directory" value="1">
                    <label class="um-filter-field um-filter-field--search">
                        <span>Search Accounts</span>
                        <div class="um-filter-input-wrap">
                            <i class="fa fa-search"></i>
                            <input type="text" name="q" value="<?php echo umh($search); ?>" placeholder="Search by ID, username, name, email, or mobile">
                        </div>
                    </label>
                    <label class="um-filter-field">
                        <span>Role</span>
                        <select name="role">
                            <option value="all" <?php echo $roleFilter === "all" ? "selected" : ""; ?>>All Roles</option>
                            <option value="office_user" <?php echo $roleFilter === "office_user" ? "selected" : ""; ?>>Office Users</option>
                            <option value="headmaster" <?php echo $roleFilter === "headmaster" ? "selected" : ""; ?>>Headmasters</option>
                            <option value="assistant_head_academics" <?php echo $roleFilter === "assistant_head_academics" ? "selected" : ""; ?>>Assistant Head Academics</option>
                            <?php if($isSuperAdmin){ ?>
                            <option value="branch_admin" <?php echo $roleFilter === "branch_admin" ? "selected" : ""; ?>>Branch Administrators</option>
                            <option value="system_admin" <?php echo $roleFilter === "system_admin" ? "selected" : ""; ?>>System Administrators</option>
                            <?php } ?>
                            <option value="teacher" <?php echo $roleFilter === "teacher" ? "selected" : ""; ?>>Teachers</option>
                            <option value="student" <?php echo $roleFilter === "student" ? "selected" : ""; ?>>Students</option>
                        </select>
                    </label>
                    <label class="um-filter-field">
                        <span>Status</span>
                        <select name="status">
                            <option value="all" <?php echo $statusFilter === "all" ? "selected" : ""; ?>>All Statuses</option>
                            <option value="active" <?php echo $statusFilter === "active" ? "selected" : ""; ?>>Active</option>
                            <option value="block" <?php echo $statusFilter === "block" ? "selected" : ""; ?>>Blocked</option>
                        </select>
                    </label>
                    <?php if($isSuperAdmin){ ?>
                    <label class="um-filter-field">
                        <span>Branch</span>
                        <select name="branch">
                            <option value="" <?php echo $branchFilter === "" ? "selected" : ""; ?>>All Branches</option>
                            <?php foreach($branches as $branchRow){ ?>
                            <option value="<?php echo umh($branchRow["branchid"]); ?>" <?php echo $branchFilter === trim((string)$branchRow["branchid"]) ? "selected" : ""; ?>><?php echo umh($branchRow["location"]); ?></option>
                            <?php } ?>
                        </select>
                    </label>
                    <?php } ?>
                    <div class="um-filter-actions">
                        <button class="button-edit" type="submit"><i class="fa fa-search"></i> Apply</button>
                        <a class="um-link-button" href="user-management.php?directory=1#user-directory">Reset</a>
                    </div>
                </form>

                <?php if(empty($visibleUsers)){ ?>
                <div class="um-empty">
                    <h3>No accounts match this view yet.</h3>
                    <p>Try a different filter or create a new office account above.</p>
                </div>
                <?php }else{ ?>
                <div class="um-card-grid">
                    <?php foreach($visibleUsers as $userRow){ ?>
                    <?php
                    $rowRoleKey = um_role_key_from_user($userRow);
                    $canEdit = um_can_manage_row($userRow) && isset($allRoleProfiles[$rowRoleKey]);
                    $canManage = um_can_manage_row($userRow);
                    $canAccessEdit = $canManage && $rowRoleKey === "teacher";
                    $assignedModules = um_get_user_module_permissions($con, trim((string)$userRow["userid"]));
                    $moduleMode = isset($userRow["module_permission_mode"]) ? trim((string)$userRow["module_permission_mode"]) : "legacy";
                    $branchLabel = trim((string)(isset($userRow["branchname"]) ? $userRow["branchname"] : ""));
                    if($branchLabel === "" && isset($branchLabels[$userRow["branchid"]])){
                        $branchLabel = $branchLabels[$userRow["branchid"]];
                    }
                    $fullName = trim($userRow["firstname"]." ".$userRow["othernames"]." ".$userRow["surname"]);
                    $userImage = um_user_image_src($userRow);
                    ?>
                    <article class="um-user-card">
                        <div class="um-user-card__head">
                            <div class="um-user-card__identity">
                                <div class="um-user-card__avatar">
                                    <img src="<?php echo umh($userImage); ?>" alt="<?php echo umh($fullName !== "" ? $fullName : $userRow["userid"]); ?>" loading="lazy">
                                </div>
                                <div class="um-user-card__title">
                                    <h3><?php echo umh($fullName !== "" ? $fullName : $userRow["userid"]); ?></h3>
                                    <p><?php echo umh($userRow["userid"]); ?></p>
                                </div>
                            </div>
                            <span class="um-status <?php echo trim((string)$userRow["status"]) === "active" ? "is-active" : "is-blocked"; ?>">
                                <?php echo trim((string)$userRow["status"]) === "active" ? "Active" : "Blocked"; ?>
                            </span>
                        </div>

                        <div class="um-user-meta">
                            <span><?php echo umh(um_role_label_from_user($userRow)); ?></span>
                            <span><?php echo umh($branchLabel !== "" ? $branchLabel : "No Branch"); ?></span>
                            <span><?php echo umh(trim((string)$userRow["username"])); ?></span>
                        </div>

                        <dl class="um-user-facts">
                            <div>
                                <dt>Mobile</dt>
                                <dd><?php echo umh(trim((string)$userRow["mobile"]) !== "" ? $userRow["mobile"] : "Not set"); ?></dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd><?php echo umh(trim((string)$userRow["email"]) !== "" ? $userRow["email"] : "Not set"); ?></dd>
                            </div>
                            <div>
                                <dt>Registered</dt>
                                <dd><?php echo umh(trim((string)$userRow["registereddatetime"]) !== "" ? date("d M Y", strtotime($userRow["registereddatetime"])) : "Unknown"); ?></dd>
                            </div>
                            <div>
                                <dt>Password</dt>
                                <dd><?php echo um_requires_password_change($userRow) ? "Must change on next login" : "Up to date"; ?></dd>
                            </div>
                            <div>
                                <dt>Module Access</dt>
                                <dd>
                                    <?php
                                    if($moduleMode === "custom"){
                                        echo count($assignedModules) > 0 ? number_format(count($assignedModules))." selected" : "No modules selected";
                                    }else{
                                        echo "Legacy full access";
                                    }
                                    ?>
                                </dd>
                            </div>
                        </dl>

                        <?php if($moduleMode === "custom" && !empty($assignedModules)){ ?>
                        <div class="um-user-meta">
                            <?php foreach($assignedModules as $assignedModuleKey){
                                $moduleLabel = isset($moduleCatalog[$assignedModuleKey]['label']) ? $moduleCatalog[$assignedModuleKey]['label'] : $assignedModuleKey;
                            ?>
                            <span><?php echo umh($moduleLabel); ?></span>
                            <?php } ?>
                        </div>
                        <?php } ?>

                        <?php if($canManage){ ?>
                        <div class="um-user-actions">
                            <?php if($canEdit || $canAccessEdit){ ?>
                            <a class="um-chip-link" href="<?php echo umh(um_url(array_merge($persist, array("edit_user" => $userRow["userid"])), "#account-form")); ?>"><i class="fa fa-edit"></i> <?php echo $canAccessEdit ? "Assign Access" : "Edit"; ?></a>
                            <?php } ?>

                            <form method="post" action="<?php echo umh(um_url(array("edit_user" => null, "directory" => "1"), "#user-directory")); ?>">
                                <input type="hidden" name="target_userid" value="<?php echo umh($userRow["userid"]); ?>">
                                <button type="submit" name="reset_user_password"><i class="fa fa-key"></i> Reset Password</button>
                            </form>

                            <form method="post" action="<?php echo umh(um_url(array("edit_user" => null, "directory" => "1"), "#user-directory")); ?>">
                                <input type="hidden" name="target_userid" value="<?php echo umh($userRow["userid"]); ?>">
                                <?php if(trim((string)$userRow["status"]) === "active"){ ?>
                                <button type="submit" name="block_user_account"><i class="fa fa-lock"></i> Block</button>
                                <?php }else{ ?>
                                <button type="submit" name="unblock_user_account"><i class="fa fa-unlock"></i> Unblock</button>
                                <?php } ?>
                            </form>

                            <form method="post" action="<?php echo umh(um_url(array("edit_user" => null, "directory" => "1"), "#user-directory")); ?>" onsubmit="return confirm('Delete this account? If it is already used in records, the system will block it instead of deleting it.');">
                                <input type="hidden" name="target_userid" value="<?php echo umh($userRow["userid"]); ?>">
                                <button type="submit" name="delete_user_account"><i class="fa fa-trash"></i> Delete</button>
                            </form>
                        </div>
                        <?php } ?>
                    </article>
                    <?php } ?>
                </div>
                <?php } ?>
<?php
$directoryBodyHtml = ob_get_clean();

if(isset($_GET["directory_fragment"]) && trim((string)$_GET["directory_fragment"]) === "1"){
    echo $directoryBodyHtml;
    exit();
}

$flashMessage = "";
if(isset($_SESSION["Message"]) && $_SESSION["Message"] !== ""){
    $flashMessage = $_SESSION["Message"];
    $_SESSION["Message"] = "";
}

$formPreviewUserId = $formMode === "create" ? um_generate_userid($formData["role_key"]) : $formData["userid"];
$modulePickerGroups = um_module_groups_for_role($formData["role_key"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
include("title.php");
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/user-management.css">
</head>
<body class="um-page">
<div class="header">
<?php include("menu.php"); ?>
</div>

<div class="um-wrap">
    <div class="um-layout">
        <aside class="um-sidebar">
            <?php include("menuboard.php"); ?>
        </aside>

        <main class="um-main">
            <section class="um-hero">
                <div class="um-hero__copy">
                    <span class="um-kicker">Administration</span>
                    <h1>User Management</h1>
                    <p>Create internal accounts, reset passwords, restrict access, and keep account support in one place.</p>
                </div>
                <div class="um-hero__meta">
                    <span class="um-pill"><?php echo $isSuperAdmin ? "System Administrator" : "Branch Administrator"; ?></span>
                    <span class="um-pill"><?php echo umh(isset($branchLabels[$currentBranchId]) ? $branchLabels[$currentBranchId] : "Current Branch"); ?></span>
                </div>
            </section>

            <nav class="um-jump-links" aria-label="User Management Sections">
                <a class="um-jump-link" href="#account-form"><i class="fa fa-user-plus"></i> Account Form</a>
                <a class="um-jump-link" href="#user-directory"><i class="fa fa-users"></i> Account Directory</a>
            </nav>

            <?php echo $flashMessage; ?>

            <section class="um-stats">
                <article class="um-stat">
                    <span class="um-stat__label">Visible Accounts</span>
                    <strong><?php echo number_format($totalUsers); ?></strong>
                </article>
                <article class="um-stat">
                    <span class="um-stat__label">Active</span>
                    <strong><?php echo number_format($activeUsers); ?></strong>
                </article>
                <article class="um-stat">
                    <span class="um-stat__label">Blocked</span>
                    <strong><?php echo number_format($blockedUsers); ?></strong>
                </article>
                <article class="um-stat">
                    <span class="um-stat__label">Password Reset Required</span>
                    <strong><?php echo number_format($resetRequiredUsers); ?></strong>
                </article>
                <article class="um-stat">
                    <span class="um-stat__label">Administrators</span>
                    <strong><?php echo number_format($adminUsers); ?></strong>
                </article>
            </section>

            <section class="um-section" id="account-form">
                <div class="um-section__head">
                    <div>
                        <span class="um-section__eyebrow"><?php echo $formPermissionOnly ? "Teacher Access" : "Internal Accounts"; ?></span>
                        <h2><?php echo $formPermissionOnly ? "Assign Teacher Privileges" : ($formMode === "update" ? "Update Account" : "Create Account"); ?></h2>
                        <p class="um-section__hint"><?php echo $formPermissionOnly ? "Review the teacher details below and switch on only the extra modules this teacher should be allowed to open." : "Create internal and leadership accounts here, or update an existing account without leaving the page."; ?></p>
                    </div>
                    <?php if($formMode === "update"){ ?>
                    <a class="um-link-button" href="<?php echo umh(um_url(array("edit_user" => null), "#account-form")); ?>">Cancel Edit</a>
                    <?php } ?>
                </div>

                <?php if($formPermissionOnly){ ?>
                <div class="um-alert um-alert--info">
                    Profile details are locked here. This screen is only for giving extra module access to the selected teacher.
                </div>
                <?php } ?>

                <form method="post" action="<?php echo umh(um_url(array("edit_user" => $formMode === "update" ? $formData["userid"] : null), "#account-form")); ?>" class="um-form-grid">
                    <input type="hidden" name="form_mode" value="<?php echo umh($formMode); ?>">

                    <label class="um-field">
                        <span>User ID</span>
                        <input type="text" value="<?php echo umh($formPreviewUserId); ?>" readonly>
                        <input type="hidden" name="userid" value="<?php echo umh($formMode === "update" ? $formData["userid"] : $formPreviewUserId); ?>">
                    </label>

                    <label class="um-field">
                        <span>Role</span>
                        <?php if($formPermissionOnly){ ?>
                        <input type="text" value="<?php echo umh(um_role_label_from_user($editingUser)); ?>" readonly>
                        <input type="hidden" name="role_key" value="<?php echo umh($formData["role_key"]); ?>">
                        <?php }else{ ?>
                        <select name="role_key" <?php echo ($formMode === "update" && isset($_SESSION["USERID"]) && $_SESSION["USERID"] === $formData["userid"]) ? "disabled" : ""; ?>>
                            <?php foreach($availableRoles as $roleKey => $profile){ ?>
                            <option value="<?php echo umh($roleKey); ?>" <?php echo $formData["role_key"] === $roleKey ? "selected" : ""; ?>><?php echo umh($profile["label"]); ?></option>
                            <?php } ?>
                        </select>
                        <?php if($formMode === "update" && isset($_SESSION["USERID"]) && $_SESSION["USERID"] === $formData["userid"]){ ?>
                        <input type="hidden" name="role_key" value="<?php echo umh($formData["role_key"]); ?>">
                        <?php } ?>
                        <?php } ?>
                    </label>

                    <label class="um-field">
                        <span>Branch</span>
                        <?php if($formPermissionOnly){ ?>
                        <input type="text" value="<?php echo umh(isset($branchLabels[$formData["branchid"]]) ? $branchLabels[$formData["branchid"]] : "Current Branch"); ?>" readonly>
                        <input type="hidden" name="branchid" value="<?php echo umh($formData["branchid"]); ?>">
                        <?php }elseif($isSuperAdmin){ ?>
                        <select name="branchid">
                            <?php foreach($branches as $branchRow){ ?>
                            <option value="<?php echo umh($branchRow["branchid"]); ?>" <?php echo trim((string)$formData["branchid"]) === trim((string)$branchRow["branchid"]) ? "selected" : ""; ?>><?php echo umh($branchRow["location"]); ?></option>
                            <?php } ?>
                        </select>
                        <?php }else{ ?>
                        <input type="text" value="<?php echo umh(isset($branchLabels[$formData["branchid"]]) ? $branchLabels[$formData["branchid"]] : "Current Branch"); ?>" readonly>
                        <input type="hidden" name="branchid" value="<?php echo umh($formData["branchid"]); ?>">
                        <?php } ?>
                    </label>

                    <label class="um-field">
                        <span>Status</span>
                        <?php if($formPermissionOnly){ ?>
                        <input type="text" value="<?php echo $formData["status"] === "active" ? "Active" : "Blocked"; ?>" readonly>
                        <input type="hidden" name="status" value="<?php echo umh($formData["status"]); ?>">
                        <?php }else{ ?>
                        <select name="status" <?php echo ($formMode === "update" && isset($_SESSION["USERID"]) && $_SESSION["USERID"] === $formData["userid"]) ? "disabled" : ""; ?>>
                            <option value="active" <?php echo $formData["status"] === "active" ? "selected" : ""; ?>>Active</option>
                            <option value="block" <?php echo $formData["status"] === "block" ? "selected" : ""; ?>>Blocked</option>
                        </select>
                        <?php if($formMode === "update" && isset($_SESSION["USERID"]) && $_SESSION["USERID"] === $formData["userid"]){ ?>
                        <input type="hidden" name="status" value="<?php echo umh($formData["status"]); ?>">
                        <?php } ?>
                        <?php } ?>
                    </label>

                    <label class="um-field">
                        <span>First Name</span>
                        <input type="text" name="firstname" value="<?php echo umh($formData["firstname"]); ?>" <?php echo $formPermissionOnly ? "readonly" : "required"; ?>>
                    </label>

                    <label class="um-field">
                        <span>Surname</span>
                        <input type="text" name="surname" value="<?php echo umh($formData["surname"]); ?>" <?php echo $formPermissionOnly ? "readonly" : "required"; ?>>
                    </label>

                    <label class="um-field">
                        <span>Other Names</span>
                        <input type="text" name="othernames" value="<?php echo umh($formData["othernames"]); ?>" <?php echo $formPermissionOnly ? "readonly" : ""; ?>>
                    </label>

                    <label class="um-field">
                        <span>Gender</span>
                        <?php if($formPermissionOnly){ ?>
                        <input type="text" value="<?php echo umh(ucfirst((string)$formData["gender"])); ?>" readonly>
                        <input type="hidden" name="gender" value="<?php echo umh($formData["gender"]); ?>">
                        <?php }else{ ?>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo strtolower((string)$formData["gender"]) === "male" ? "selected" : ""; ?>>Male</option>
                            <option value="female" <?php echo strtolower((string)$formData["gender"]) === "female" ? "selected" : ""; ?>>Female</option>
                        </select>
                        <?php } ?>
                    </label>

                    <label class="um-field">
                        <span>Birthday</span>
                        <input type="<?php echo $formPermissionOnly ? "text" : "date"; ?>" name="<?php echo $formPermissionOnly ? "birthday_display" : "birthday"; ?>" value="<?php echo umh($formData["birthday"]); ?>" <?php echo $formPermissionOnly ? "readonly" : "required"; ?>>
                        <?php if($formPermissionOnly){ ?><input type="hidden" name="birthday" value="<?php echo umh($formData["birthday"]); ?>"><?php } ?>
                    </label>

                    <label class="um-field">
                        <span>Mobile</span>
                        <input type="text" name="mobile" value="<?php echo umh($formData["mobile"]); ?>" placeholder="Optional mobile number" <?php echo $formPermissionOnly ? "readonly" : ""; ?>>
                    </label>

                    <label class="um-field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?php echo umh($formData["email"]); ?>" placeholder="Optional email address" <?php echo $formPermissionOnly ? "readonly" : ""; ?>>
                    </label>

                    <label class="um-field">
                        <span>Username</span>
                        <input type="text" name="username" value="<?php echo umh($formData["username"]); ?>" <?php echo $formPermissionOnly ? "readonly" : "required"; ?>>
                    </label>

                    <?php if($formMode === "create"){ ?>
                    <label class="um-field um-field--wide">
                        <span>Temporary Password</span>
                        <input type="text" name="temp_password" value="<?php echo umh($formData["temp_password"]); ?>" required>
                        <small>The user will sign in with this password once and then be forced to change it.</small>
                    </label>
                    <?php } ?>

                    <div class="um-field um-field--wide">
                        <span>Module Access</span>
                        <details class="um-module-picker" open>
                            <summary>Choose the modules this account can open</summary>
                            <div class="um-module-picker__body">
                                <p class="um-module-picker__note">Leave a module unchecked if you want to hide and block that area for this user. Core pages like home, profile, and password change stay available.</p>
                                <?php foreach($modulePickerGroups as $groupLabel => $groupModules){ ?>
                                <section class="um-module-group">
                                    <h3><?php echo umh($groupLabel); ?></h3>
                                    <div class="um-module-options">
                                        <?php foreach($groupModules as $moduleKey => $module){ ?>
                                        <label class="um-module-option">
                                            <input type="checkbox" name="module_keys[]" value="<?php echo umh($moduleKey); ?>" <?php echo in_array($moduleKey, $formData["module_keys"], true) ? "checked" : ""; ?>>
                                            <span>
                                                <strong><?php echo umh($module["label"]); ?></strong>
                                                <small><?php echo umh($module["description"]); ?></small>
                                            </span>
                                        </label>
                                        <?php } ?>
                                    </div>
                                </section>
                                <?php } ?>
                            </div>
                        </details>
                    </div>

                    <div class="um-form-actions um-field--wide">
                        <button class="button-edit" type="submit" name="save_user_account">
                            <i class="fa fa-save"></i> <?php echo $formPermissionOnly ? "Save Teacher Access" : ($formMode === "update" ? "Update Account" : "Create Account"); ?>
                        </button>
                        <?php if($formMode === "create"){ ?>
                        <a class="um-link-button" href="<?php echo umh(um_url(array("edit_user" => null), "#account-form")); ?>">Clear Form</a>
                        <?php } ?>
                    </div>
                </form>
            </section>

            <section class="um-section" id="user-directory">
                <div class="um-section__head">
                    <div>
                        <span class="um-section__eyebrow">Directory</span>
                        <h2>Manage Accounts</h2>
                        <p class="um-section__hint">Open this section only when you need to search, review, reset, block, or delete accounts.</p>
                    </div>
                    <button
                        class="um-directory-toggle"
                        type="button"
                        data-directory-toggle
                        data-open-url="<?php echo umh(um_url(array_merge($persist, array("directory" => "1", "directory_fragment" => "1")))); ?>"
                        aria-expanded="<?php echo $directoryRequested ? "true" : "false"; ?>"
                    >
                        <i class="fa <?php echo $directoryRequested ? "fa-chevron-up" : "fa-users"; ?>"></i>
                        <?php echo $directoryRequested ? "Hide Accounts" : "Open Manage Accounts"; ?>
                    </button>
                </div>

                <?php if(!$directoryRequested){ ?>
                <div class="um-directory-placeholder" data-directory-placeholder>
                    <div>
                        <strong>Manage accounts stays tucked away until you need it.</strong>
                        <p>Open this section to load search, filters, and the account cards. That keeps the page lighter, especially on phones.</p>
                    </div>
                    <span class="um-pill"><?php echo number_format($totalUsers); ?> Accounts Ready</span>
                </div>
                <?php } ?>

                <div
                    class="um-directory-shell<?php echo $directoryRequested ? " is-open is-loaded" : ""; ?>"
                    data-directory-shell
                    <?php echo $directoryRequested ? "" : "hidden"; ?>
                >
                    <?php if($directoryRequested){ echo $directoryBodyHtml; } ?>
                </div>
            </section>
        </main>
    </div>
</div>
<script>
(function(){
    var toggle = document.querySelector('[data-directory-toggle]');
    var shell = document.querySelector('[data-directory-shell]');
    var placeholder = document.querySelector('[data-directory-placeholder]');
    if(!toggle || !shell){
        return;
    }

    function setOpenState(isOpen){
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.innerHTML = isOpen
            ? '<i class="fa fa-chevron-up"></i> Hide Accounts'
            : '<i class="fa fa-users"></i> Open Manage Accounts';
        if(isOpen){
            shell.hidden = false;
            shell.classList.add('is-open');
            if(placeholder){ placeholder.hidden = true; }
        }else{
            shell.hidden = true;
            shell.classList.remove('is-open');
            if(placeholder){ placeholder.hidden = false; }
        }
    }

    async function loadDirectory(){
        if(shell.classList.contains('is-loaded')){
            return true;
        }
        shell.hidden = false;
        shell.classList.add('is-open');
        shell.innerHTML = '<div class="um-directory-loading"><i class="fa fa-refresh fa-spin"></i> Loading accounts...</div>';
        try{
            var response = await fetch(toggle.getAttribute('data-open-url'), { credentials: 'same-origin' });
            if(!response.ok){
                throw new Error('load failed');
            }
            var html = await response.text();
            shell.innerHTML = html;
            shell.classList.add('is-loaded');
            if(placeholder){ placeholder.hidden = true; }
            return true;
        }catch(error){
            shell.innerHTML = '<div class="um-empty"><h3>Accounts could not load right now.</h3><p>Please try opening the section again.</p></div>';
            return false;
        }
    }

    toggle.addEventListener('click', async function(){
        var isOpen = toggle.getAttribute('aria-expanded') === 'true';
        if(isOpen){
            setOpenState(false);
            return;
        }
        var loaded = await loadDirectory();
        if(loaded){
            setOpenState(true);
        }
    });
})();
</script>
</body>
</html>

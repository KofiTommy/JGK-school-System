<?php
session_start();

function normalizeExcelBirthday($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    // Excel serial date (e.g. 45231) -> YYYY-MM-DD
    if (is_numeric($value)) {
        $serial = (int)$value;
        if ($serial > 0) {
            $unix = ($serial - 25569) * 86400;
            if ($unix > 0) {
                return gmdate('Y-m-d', $unix);
            }
        }
    }

    // Try common date formats from sheet text cells
    $formats = array(
        'Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'm-d-Y',
        'd.m.Y', 'Y/m/d', 'd M Y', 'd F Y'
    );
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt && $dt->format($format) === $value) {
            return $dt->format('Y-m-d');
        }
    }

    // Last fallback using strtotime
    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return '';
}

function normalizeGender($value) {
    $g = strtoupper(trim((string)$value));
    if (in_array($g, array('M', 'MALE', 'BOY', 'B'))) {
        return 'male';
    }
    if (in_array($g, array('F', 'FEMALE', 'GIRL', 'G'))) {
        return 'female';
    }
    return trim((string)$value);
}

function normalizeResidenceType($value) {
    $r = strtoupper(trim((string)$value));
    if ($r === 'D' || $r === 'DAY') {
        return 'Day';
    }
    if ($r === 'B' || $r === 'BOARDING' || $r === 'BOARDER') {
        return 'Boarding';
    }
    return trim((string)$value);
}

function canonicalHeader($header) {
    $h = strtolower(trim((string)$header));
    $h = preg_replace('/[^a-z0-9]+/', '', $h);
    return $h;
}

function buildHeaderMap($row) {
    $map = array();
    foreach ($row as $i => $cell) {
        $key = canonicalHeader($cell);
        if ($key !== '') {
            $map[$key] = $i;
        }
    }
    return $map;
}

function valueByMap($row, $map, $headerKeys, $defaultIndex) {
    if (is_array($map)) {
        foreach ($headerKeys as $key) {
            if (isset($map[$key]) && array_key_exists($map[$key], $row)) {
                return trim((string)$row[$map[$key]]);
            }
        }
    }
    return isset($row[$defaultIndex]) ? trim((string)$row[$defaultIndex]) : '';
}

function insertRegisterData(
    $_UserId, $_Firstname, $_Surname, $_Othernames, $_Gender, $_ResidenceType, $_Birthday, $_Age,
    $_PostalAddress, $_HomeAddress, $_HomeTown, $_Religion, $_Relationship, $_BECEIndexNumber,
    $_Nextofkin_fullname, $_Nextofkin_contact, $_Email, $_Mobile, $_Username,
    $_UserPassword, $_AccessLevel, $_SystemType, $_Recipient
) {
    // Skip header row or invalid user id
    $userIdCheck = canonicalHeader($_UserId);
    $firstCheck = canonicalHeader($_Firstname);
    $surnameCheck = canonicalHeader($_Surname);
    if ($userIdCheck === '' || $userIdCheck === 'userid' || $firstCheck === 'firstname' || $surnameCheck === 'surname') {
        return false;
    }

    include("dbstring.php"); // Make sure $con is defined here

    // Check if user already exists
    $sql = "SELECT * FROM tblsystemuser WHERE userid='" . mysqli_real_escape_string($con, $_UserId) . "'";
    $result = mysqli_query($con, $sql);
    $count = mysqli_num_rows($result);

    if ($count > 0) {
        echo "<div style='background-color:white;color:red;' align='center'>User Information already created for UserID: " . htmlspecialchars($_UserId, ENT_QUOTES, 'UTF-8') . "</div><br>";
        return false;
    } else {
        $_UserPassword = md5($_UserPassword);

        // Prepare insert query with proper escaping
        $sqlInsert = "INSERT INTO tblsystemuser (
            userid, firstname, surname, othernames, gender, residencetype, birthday, age,
            postaladdress, homeaddress, hometown, religion, relationship, beceindexnumber,
            nextofkin_fullname, nextofkin_contact, email, mobile,
            registereddatetime, status, username, password, accesslevel,
            systemtype, staffstatus, branchid
        ) VALUES (
            '" . mysqli_real_escape_string($con, $_UserId) . "',
            '" . mysqli_real_escape_string($con, $_Firstname) . "',
            '" . mysqli_real_escape_string($con, $_Surname) . "',
            '" . mysqli_real_escape_string($con, $_Othernames) . "',
            '" . mysqli_real_escape_string($con, $_Gender) . "',
            '" . mysqli_real_escape_string($con, $_ResidenceType) . "',
            '" . mysqli_real_escape_string($con, $_Birthday) . "',
            '" . mysqli_real_escape_string($con, $_Age) . "',
            '" . mysqli_real_escape_string($con, $_PostalAddress) . "',
            '" . mysqli_real_escape_string($con, $_HomeAddress) . "',
            '" . mysqli_real_escape_string($con, $_HomeTown) . "',
            '" . mysqli_real_escape_string($con, $_Religion) . "',
            '" . mysqli_real_escape_string($con, $_Relationship) . "',
            '" . mysqli_real_escape_string($con, $_BECEIndexNumber) . "',
            '" . mysqli_real_escape_string($con, $_Nextofkin_fullname) . "',
            '" . mysqli_real_escape_string($con, $_Nextofkin_contact) . "',
            '" . mysqli_real_escape_string($con, $_Email) . "',
            '" . mysqli_real_escape_string($con, $_Mobile) . "',
            NOW(),
            'active',
            '" . mysqli_real_escape_string($con, $_Username) . "',
            '" . mysqli_real_escape_string($con, $_UserPassword) . "',
            '" . mysqli_real_escape_string($con, $_AccessLevel) . "',
            '" . mysqli_real_escape_string($con, $_SystemType) . "',
            '" . mysqli_real_escape_string($con, $_Recipient) . "',
            '" . mysqli_real_escape_string($con, $_SESSION['BRANCHID']) . "'
        )";

        $_SQL_EXECUTE = mysqli_query($con, $sqlInsert);

        if ($_SQL_EXECUTE) {
            return true;
        } else {
            $_Error = mysqli_error($con);
            echo "<div style='background-color:white;color:red;' align='center'>User Information failed to save for UserID: " . htmlspecialchars($_UserId, ENT_QUOTES, 'UTF-8') . ", Error: " . htmlspecialchars($_Error, ENT_QUOTES, 'UTF-8') . "</div><br>";
            return false;
        }
    }
}
?>

<?php
require_once 'simplexlsx.class.php';

$counter = 0;
$message = "";
$headerMap = null;

if (isset($_POST['submit_group_data'])) {
    $file = $_FILES['file1']['tmp_name'] ?? '';
    $fileName = $_FILES['file1']['name'] ?? '';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($ext !== 'xlsx') {
        $message = "<div style='background-color:white;color:red;' align='center'>Invalid file format. Please upload a .xlsx file (not .xls).</div><br>";
    } else {
        $xlsx = new SimpleXLSX($file);
        if (!$xlsx->success()) {
            $parseError = htmlspecialchars($xlsx->error(), ENT_QUOTES, 'UTF-8');
            $message = "<div style='background-color:white;color:red;' align='center'>Unable to read Excel file: $parseError</div><br>";
        } else {
            $rows = $xlsx->rows();
            if (!is_array($rows)) {
                $message = "<div style='background-color:white;color:red;' align='center'>Unable to read rows from uploaded Excel file.</div><br>";
            } else {
                foreach ($rows as $field) {
                    if (!is_array($field) || count($field) === 0) {
                        continue;
                    }

                    if ($headerMap === null) {
                        $candidateMap = buildHeaderMap($field);
                        if (isset($candidateMap['userid']) && (isset($candidateMap['firstname']) || isset($candidateMap['surname']))) {
                            $headerMap = $candidateMap;
                            continue;
                        }
                        $headerMap = array();
                    }

                    $_UserId = valueByMap($field, $headerMap, array('userid', 'useridno', 'studentid'), 0);
                    $_Firstname = valueByMap($field, $headerMap, array('firstname', 'fname'), 1);
                    $_Surname = valueByMap($field, $headerMap, array('surname', 'lastname', 'lname'), 2);
                    $_Othernames = valueByMap($field, $headerMap, array('othernames', 'middlename'), 3);
                    $_Gender = normalizeGender(valueByMap($field, $headerMap, array('gender', 'sex'), 4));
                    $_ResidenceType = normalizeResidenceType(valueByMap($field, $headerMap, array('residencetype', 'residence', 'boardingstatus'), 5));
                    $_Birthday = normalizeExcelBirthday(valueByMap($field, $headerMap, array('birthday', 'dateofbirth', 'dob'), 6));
                    $_Age = valueByMap($field, $headerMap, array('age'), 7);
                    $_PostalAddress = valueByMap($field, $headerMap, array('postaladdress', 'postal'), 8);
                    $_HomeAddress = valueByMap($field, $headerMap, array('homeaddress', 'address'), 9);
                    $_HomeTown = valueByMap($field, $headerMap, array('hometown', 'town'), 10);
                    $_Religion = valueByMap($field, $headerMap, array('religion'), 11);
                    $_Relationship = valueByMap($field, $headerMap, array('relationship'), 12);
                    $_BECEIndexNumber = valueByMap($field, $headerMap, array('beceindexnumber', 'beceindexno', 'beceindex'), 13);
                    $_Nextofkin_fullname = valueByMap($field, $headerMap, array('nextofkinfullname', 'nextofkin', 'nextkinfullname'), 14);
                    $_Nextofkin_contact = valueByMap($field, $headerMap, array('nextofkincontact', 'nextkincontact'), 15);
                    $_Email = valueByMap($field, $headerMap, array('email', 'emailaddress'), 16);
                    $_Mobile = valueByMap($field, $headerMap, array('mobile', 'mobileno', 'phone', 'contact'), 17);
                    $_Username = valueByMap($field, $headerMap, array('username', 'username'), 18);
                    $_Password = valueByMap($field, $headerMap, array('password', 'passcode'), 19);
                    $_AccessLevel = "user";
                    $_SystemType = valueByMap($field, $headerMap, array('systemtype', 'usertype'), 20);
                    $_Recipient = valueByMap($field, $headerMap, array('staffstatus', 'recipient'), 21);

                    $success = insertRegisterData(
                        $_UserId, $_Firstname, $_Surname, $_Othernames, $_Gender, $_ResidenceType, $_Birthday, $_Age,
                        $_PostalAddress, $_HomeAddress, $_HomeTown, $_Religion, $_Relationship, $_BECEIndexNumber,
                        $_Nextofkin_fullname, $_Nextofkin_contact, $_Email, $_Mobile, $_Username,
                        $_Password, $_AccessLevel, $_SystemType, $_Recipient
                    );

                    if ($success) {
                        $counter++;
                    }
                }
            }
        }
    }

    if ($message === "") {
        if ($counter > 0) {
            $message = "<div style='background-color:white;color:green;' align='center'>Register Data Successfully Uploaded. Total records inserted: $counter</div><br>";
        } else {
            $message = "<div style='background-color:white;color:red;' align='center'>Register Data Failed To Upload</div><br>";
        }
    }
}
?>

<?php
echo $message;
?>

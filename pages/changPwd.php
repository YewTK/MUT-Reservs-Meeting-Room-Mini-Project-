<?php
session_start();
require_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession("../index.php");

$_SESSION['pageName'] = "changPwd";
include_once "menus/navbar.php";

if (isset($_POST['btn-changPwd'])) {
    $oldPwd = $_POST['oldPwd'];
    $newPwd = $_POST['newPwd'];
    $cfPwd = $_POST['cfPwd'];

    if (empty($oldPwd) || empty($newPwd) || empty($cfPwd)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบ!!!";
    } else {
        if ($newPwd !== $cfPwd) {
            $_SESSION['error'] = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
        } else {
            $query = "SELECT Password FROM Employee WHERE EmployeeID = ?";
            $stmt = $db->Query($query, [$_SESSION['EmployeeID']]);

            if ($stmt) {
                $currentPassword = $newPwd;
                $curentCheck = false;

                if ($useHashPassword) {
                    $curentCheck = password_verify($oldPwd, $stmt['PASSWORD']);
                    $currentPassword = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 10]);
                } else {
                    $curentCheck = $stmt['PASSWORD'] == $oldPwd;
                }

                if ($curentCheck) {
                    $updateQuery = "UPDATE Employee SET Password = ? WHERE EmployeeID = ?";
                    $isUpdated = $db->nonQuery($updateQuery, [$currentPassword, $_SESSION['EmployeeID']]);

                    $selectLogs = "SELECT COALESCE(MAX(CHANGEID), 0) as MAXID FROM CHANGEPASSWORD";
                    $maxID = $db->Query($selectLogs, false, false, true);
                    $maxID = $maxID['MAXID'] + 1;

                    $currentOldPassword = $oldPwd;
                    if ($useHashPassword) {
                        $currentOldPassword = password_hash($oldPwd, PASSWORD_BCRYPT, ['cost' => 10]);
                    }

                    $insertLogs = "INSERT INTO CHANGEPASSWORD (CHANGEID, EMPLOYEEID, OLD_PWD, NEW_PWD) VALUES (?, ?, ?, ?)";
                    $resultInsert = $db->nonQuery($insertLogs, [$maxID, $_SESSION['EmployeeID'], $currentOldPassword, $currentPassword]);

                    if ($isUpdated) {
                        $_SESSION['success'] = "เปลี่ยนรหัสผ่านสำเร็จ";
                        setcookie("user_password", $newPwd, time() + (60 * 60 * 24 * 365),'/');
                    } else {
                        $_SESSION['error'] = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน กรุณาลองใหม่";
                    }
                } else {
                    $_SESSION['error'] = "รหัสผ่านเดิมของท่านไม่ถูกต้อง";
                }
            } else {
                $_SESSION['error'] = "ตรวจสอบรหัสผ่านล้มเหลว!!!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $Title . " - Change Password"; ?></title>
    <?php include_once "menus/header.php"; ?>
</head>
<body>
    <div class="f-1">
        <div class="box-title">
            <span>CHANGE PASSWORD</span>
        </div>
        <div class="container c-p mt-3">
            <form action="" method="POST">
                <div>
                    <label for="oldPwd">Old Password:</label>
                    <input type="password" id="oldPwd" name="oldPwd" required>
                </div>
                <div>
                    <label for="newPwd">New Password:</label>
                    <input type="password" id="newPwd" name="newPwd" required>
                </div>
                <div>
                    <label for="cfPwd">Confirm Password:</label>
                    <input type="password" id="cfPwd" name="cfPwd" required>
                </div>
                <button name="btn-changPwd"> <i class="fas fa-rotate"></i> Change Password</button>
            </form>
        </div>
    </div>
    <?php include_once "menus/footer.php"; ?>
</body>
</html>

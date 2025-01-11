<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "manageEmp";
include_once "menus/navbar.php";

if ($permissions[3] != '1') {
    header('Location: home.php');
    exit();
}

// Query ดึงข้อมูลแผนก
$deptQuery = "SELECT DepartmentID, DepartmentName FROM Department";
$departments = $db->Query($deptQuery);

// Query ดึงข้อมูลตำแหน่งงาน
$positionQuery = "SELECT PositionID, PositionName FROM Position";
$positions = $db->Query($positionQuery);

if (isset($_POST['AddEmployee_Click'])) {
    $firstName = htmlspecialchars($_POST['fname']);
    $lastName = htmlspecialchars($_POST['lname']);
    $tellNumber = htmlspecialchars($_POST['PhoneNumber']);
    $departmentID = htmlspecialchars($_POST['Department']);
    $positionID = htmlspecialchars($_POST['JobPosition']);
    $StatusID = 1;
    $countLock = 0;
    $userProfile = '..\assets\img\uploads\users\noimg.png';
    $password = htmlspecialchars($_POST['password']);
    $employeeID = $db->autoID($departmentID);
    if ($employeeID === false) {
        $_SESSION['error'] = "พนักงานเต็มแล้ว";
    } else {
        $query = "SELECT 1 FROM Employee WHERE EmployeeID = ?";
        $result = $db->Query($query, [$employeeID]);
        if (isset($result['1'])) {
            $_SESSION['error'] = 'รหัสผู้ใช้นี้มีอยู่แล้ว';
        } else {
            $query = "SELECT 1 FROM Employee WHERE FName = ? AND LName = ?";
            $param = [$firstName, $lastName];
            $result = $db->Query($query, $param);
            if (isset($result['1'])) {
                $_SESSION['error'] = 'ชื่อและนามสกุลผู้ใช้นี้มีอยู่แล้ว';
            } else {
                $query = "SELECT 1 FROM Employee WHERE TellNumber = ?";
                $param = [$tellNumber];
                $result = $db->Query($query, $param);
                if (isset($result['1'])) {
                    $_SESSION['error'] = 'เบอร์ผู้ใช้นี้มีอยู่แล้ว';
                } else {

                    $curentPassword = $password;

                    if ($useHashPassword) {
                        /*กำหนด cost 10 เพื่อให้การเข้ารหัสรวดเร็วยิ่งขึ้น *ตัวเลขยิ่งเยอะ ยิ่งทำงานช้า 
                        ซึ่งขึ้นอยู่กับความเร็วของคอมที่เราใช้ครับ 
                        เพราะฉะนั้น 10 ก็พอครับ หรือจะลองเพิ่มตัวเลขแล้วรันดูครับ ว่าจะดีเลเยอะไหม!!*/
                        $options = [
                            'cost' => 10,
                        ];
                        $curentPassword = password_hash($password, PASSWORD_BCRYPT, $options);
                    }

                    $query = "INSERT INTO Employee VALUES (:employeeID, :firstName, :lastName, :tellNumber, :departmentID, :positionID, :statusID, :countLock, :userProfile, :password)";
                    $params = [
                        ':employeeID' => $employeeID,
                        ':firstName' => $firstName,
                        ':lastName' => $lastName,
                        ':tellNumber' => $tellNumber,
                        ':departmentID' => $departmentID,
                        ':positionID' => $positionID,
                        ':statusID' => $StatusID,
                        ':countLock' => $countLock,
                        ':password' => $curentPassword,
                        ':userProfile' => $userProfile
                    ];

                    $result = $db->nonQuery($query, $params);
                    if ($result) {
                        $_SESSION['success'] = "เพิ่มพนักงานสำเร็จ";
                        $_SESSION['action_status'] = "success";
                    } else {
                        $_SESSION['error'] = "ไม่สามารถเพิ่มพนักงานได้ กรุณาลองใหม่";
                    }
                }
            }
        }
    }

}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo $Title ?> - Add Employee</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">ADD EMPLOYEE</span>
        </div>

        <div class="container profile-container mt-3">
            <div class="profile-header">
                <label for="fileToUpload">
                    <i class="far fa-circle-user" style="font-size: 124px;"></i>
                </label>
                <div>
                    <span id="fullName">NAME</span>
                </div>
            </div>

            <!-- ฟอร์มการเพิ่มพนักงาน -->
            <form method="POST" action="">
                <div class="profile-details">
                    <div class="box">
                        <label for="fname">First Name:</label>
                        <input type="text" id="fname" name="fname" maxlength="40" oninput="updateFullName()" required>
                    </div>
                    <div class="box">
                        <label for="lname">Last Name:</label>
                        <input type="text" id="lname" name="lname" maxlength="40" oninput="updateFullName()" required>
                    </div>
                    <div class="box">
                        <label for="PhoneNumber">Phone Number:</label>
                        <input type="text" id="PhoneNumber" name="PhoneNumber" maxlength="10" required>
                    </div>
                    <div class="d-flex justify-content-center align-items-center box">
                        <label for="Department">Department:</label>
                        <select class="form-select" id="Department" name="Department" required>
                            <?php
                            foreach ($departments as $department) {
                                echo "<option value='{$department['DEPARTMENTID']}'>{$department['DEPARTMENTNAME']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="d-flex justify-content-center align-items-center box">
                        <label for="JobPosition">Job Position:</label>
                        <select class="form-select" id="JobPosition" name="JobPosition" required>
                            <?php
                            foreach ($positions as $position) {
                                echo "<option value='{$position['POSITIONID']}'>{$position['POSITIONNAME']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="box">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" maxlength="255" required>
                    </div>
                </div>


                <!-- ปุ่มสำหรับ submit ฟอร์ม -->
                <div class="d-flex justify-content-center mt-5">
                    <button class="btn btn-primary" type="submit" name="AddEmployee_Click">
                        <i class="fas fa-user-plus"></i> Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once "menus/footer.php"; ?>
</body>

</html>
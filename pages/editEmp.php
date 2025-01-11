<?php
ob_start(); // เริ่มต้น output buffering
session_start();
include_once "../config/connection.php";
include_once "../config/config.php";
include_once "../config/function.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "manageEmp";
include_once "menus/navbar.php";

if ($permissions[3] != '1') {
    header('Location: home.php');
    exit();
}

if (!empty($_POST['empID'])) {
    $_SESSION['Update_EmployeeID'] = $_POST['empID'];
}

if (empty($_SESSION['Update_EmployeeID'])) {
    header('Location: report.php');
    exit();
}

$emp = $db->getEmployee($_SESSION['Update_EmployeeID']);

// ดึงข้อมูล Departments และ Positions สำหรับ dropdown
$departments = $db->Query("SELECT DepartmentID, DepartmentName FROM Department");
$positions = $db->Query("SELECT PositionID, PositionName FROM Position");

$targetDir = "/home/u6511130055/public_html/cbt/assets/img/uploads/users/";
$targetImg = "../assets/img/uploads/users/";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['is_submitting']) && !isset($_POST['btn-save'])) {
    $_SESSION['is_submitting'] = true;
    if (!empty($_FILES["fileToUpload"]["name"])) {
        $fileName = basename($_FILES["fileToUpload"]["name"]);
        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $fileName); // กรองชื่อไฟล์
        $targetFilePath = $targetDir . $fileName;
        $targetImgPath = $targetImg . $fileName;
        $_SESSION['RoomIMG'] = $targetImgPath;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $isAdmin = true;
        $allowTypes = $isAdmin ? array('jpg', 'png', 'jpeg', 'gif') : array('jpg', 'png', 'jpeg');

        // ตรวจสอบประเภทไฟล์
        if (in_array($fileType, $allowTypes)) {
            if ($_FILES["fileToUpload"]["size"] > $maxFileSize) {
                $_SESSION['error'] = "File size exceeds the " . formatSizeUnits($maxFileSize) . " limit.";
            } else {
                // ย้ายไฟล์ไปยังปลายทาง
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFilePath)) {
                    // อัปเดตฐานข้อมูล
                    $insert = $db->nonQuery(
                        "UPDATE Employee SET UserProfile = ? WHERE EmployeeID = ?",
                        [$targetImgPath, $emp['EMPLOYEEID']]
                    );
                    if ($insert) {
                        $_SESSION['success'] = "The file " . htmlspecialchars($fileName) . " has been uploaded successfully.";
                        $emp = $db->getEmployee($_SESSION['Update_EmployeeID']);
                        unset($_SESSION['RoomIMG']);
                    } else {
                        $_SESSION['error'] = "Failed to update the database. Please try again.";
                    }
                } else {
                    $_SESSION['error'] = "There was an error moving the uploaded file.";
                }
            }
        } else {
            $_SESSION['error'] = "Only JPG, JPEG, and PNG files are allowed.";
        }
    }

    unset($_SESSION['is_submitting']);
}

if (isset($_POST['btn-save'])) {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $password = $_POST['pwd'];
    $departmentID = $_POST['Department'];
    $positionID = $_POST['JobPosition'];
    $tellNumber = $_POST['PhoneNumber'];
    $empID = $_POST['empID'];
    $_SESSION['Update_EmployeeID'] = $empID;

    // ตรวจสอบว่ามีการกรอกข้อมูลครบหรือไม่
    if (empty($fname) || empty($lname) || empty($departmentID) || empty($positionID) || empty($empID)) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        if (!empty($password)) {

            $currentPassword = $password;

            if ($useHashPassword) {
                $options = [
                    'cost' => 10,
                ];
                $currentPassword = password_hash($password, PASSWORD_BCRYPT, $options);
            }

            $query = "UPDATE Employee SET FName = ?, LName = ?, DepartmentID = ?, PositionID = ? , TellNumber = ? , Password = ? WHERE EmployeeID = ?";
            $param = [$fname, $lname, $departmentID, $positionID, $tellNumber, $currentPassword, $empID];
            $stmt = $db->nonQuery($query, $param);
            // ดำเนินการอัปเดต
            if ($stmt) {
                $_SESSION['success'] = "แก้ไขข้อมูลสำเร็จ";
            } else {
                $_SESSION['error'] = 'ไม่สามารถแก้ไขข้อมูลได้';
            }
        } else {
            // คำสั่ง SQL ใช้ named placeholders
            $query = "UPDATE Employee SET FName = ?, LName = ?, DepartmentID = ?, PositionID = ? , TellNumber = ? WHERE EmployeeID = ?";
            $param = [$fname, $lname, $departmentID, $positionID, $tellNumber, $empID];
            $stmt = $db->nonQuery($query, $param);

            // ดำเนินการอัปเดต
            if ($stmt) {
                $_SESSION['success'] = "แก้ไขข้อมูลสำเร็จ";
            } else {
                $_SESSION['error'] = 'ไม่สามารถแก้ไขข้อมูลได้';
            }
        }
    }

    // รีเฟรชหน้าเพื่อหลีกเลี่ยงการส่งฟอร์มซ้ำ
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

?>
<!DOCTYPE html>
<html>

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Edit Employee</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">EDIT EMPLOYEE</span>
        </div>
        <div class="container profile-container mt-3">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="profile-header">
                    <label for="fileToUpload">
                        <?php if (!empty($emp['USERPROFILE'])) { ?>
                            <img src="<?php echo htmlspecialchars($emp['USERPROFILE']); ?>" alt="User Profile"
                                style="cursor: pointer; width: 124px; height: 124px; border-radius: 50%;">
                        <?php } else { ?>
                            <i class="far fa-circle-user"
                                style="font-size: 124px; cursor: pointer; border-radius: 50%;"></i>
                        <?php } ?>
                    </label>
                    <input type="file" name="fileToUpload" id="fileToUpload" style="display: none;" accept="image/*">
                    <div>
                        <span id="fullName"><?php echo htmlspecialchars($emp['FNAME'] . " " . $emp['LNAME']); ?></span>
                    </div>
                </div>
            </form>
            <form action="" method="POST">
                <div class="profile-details">
                    <div class="box">
                        <label for="empID">EmployeeID:</label>
                        <input type="hidden" name="empID" value="<?php echo htmlspecialchars($emp['EMPLOYEEID']); ?>">
                        <input type="text" placeholder="<?php echo htmlspecialchars($emp['EMPLOYEEID']); ?>" disabled />
                    </div>
                    <div class="box">
                        <label for="fname">First Name:</label>
                        <input type='text' id="fname" value="<?php echo htmlspecialchars($emp['FNAME']); ?>"
                            name="fname" maxlength="40" oninput="updateFullName()">
                    </div>
                    <div class="box">
                        <label for="lname">Last Name:</label>
                        <input type='text' id="lname" value="<?php echo htmlspecialchars($emp['LNAME']); ?>"
                            name="lname" oninput="updateFullName()">
                    </div>
                    <div class="box">
                        <label for="PhoneNumber">Phone Number:</label>
                        <input type='text' id="PhoneNumber" value="<?php echo htmlspecialchars($emp['TELLNUMBER']); ?>"
                            name="PhoneNumber" maxlength="10">
                    </div>
                    <div class="d-flex justify-content-center align-items-center box">
                        <label for="Department">Department:</label>
                        <select class="form-select" id="Department" name="Department" required>
                            <?php foreach ($departments as $department) {
                                $selected = ($department['DEPARTMENTID'] == $emp['DEPARTMENTID']) ? 'selected' : '';
                                echo "<option value='{$department['DEPARTMENTID']}' {$selected}>{$department['DEPARTMENTNAME']}</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="d-flex justify-content-center align-items-center box">
                        <label for="JobPosition">Job Position:</label>
                        <select class="form-select" id="JobPosition" name="JobPosition" required>
                            <?php foreach ($positions as $position) {
                                $selected = ($position['POSITIONID'] == $emp['POSITIONID']) ? 'selected' : '';
                                echo "<option value='{$position['POSITIONID']}' {$selected}>{$position['POSITIONNAME']}</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="box">
                        <label for="pwd">Password:</label>
                        <input type='text' id="pwd" value="<?php //echo htmlspecialchars($emp['PASSWORD']); ?>"
                            name="pwd">
                    </div>
                </div>
                <!-- ปุ่มสำหรับ submit ฟอร์ม -->
                <div class="d-flex justify-content-center mt-5">
                    <button class="btn btn-primary" type="submit" name="btn-save">
                        <i class="fas fa-pen-to-square"></i> Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once "menus/footer.php"; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ตัวแปรควบคุมการ submit
            let isSubmitting = false;

            // จัดการ event การเลือกไฟล์
            const fileInput = document.getElementById('fileToUpload');
            if (fileInput) {
                fileInput.addEventListener('change', function (e) {
                    e.preventDefault(); // ป้องกันการ submit ซ้ำ

                    if (this.files.length > 0 && !isSubmitting) {
                        isSubmitting = true;

                        // ตรวจสอบประเภทไฟล์
                        const file = this.files[0];
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];

                        if (allowedTypes.includes(file.type)) {
                            document.getElementById('uploadForm').submit();
                        } else {
                            alert('Only JPG, JPEG, and PNG files are allowed.');
                            isSubmitting = false;
                        }
                    }
                });
            }

            // Reset isSubmitting เมื่อโหลดหน้าใหม่
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    isSubmitting = false;
                }
            });

            // Reset isSubmitting เมื่อออกจากหน้า
            window.addEventListener('beforeunload', function () {
                isSubmitting = false;
            });
        });
    </script>
</body>

</html>

<?php
ob_end_flush(); // ส่ง output ที่เก็บไว้ใน buffer ไปที่เบราว์เซอร์
?>
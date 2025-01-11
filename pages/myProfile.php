<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
include_once "../config/function.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "myProfile";
include_once "menus/navbar.php";

$isAdmin = $db->Query("SELECT p.PositionName 
FROM employee e
JOIN POSITION p ON p.POSITIONID = e.POSITIONID 
WHERE e.EmployeeID = ?", [$_SESSION['EmployeeID']]) ?? false;

// เส้นทางอัปโหลด
$targetDir = "/home/u6511130055/public_html/cbt/assets/img/uploads/users/";
$targetImg = "../assets/img/uploads/users/";

// ตรวจสอบการ submit ซ้ำด้วย session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['is_submitting'])) {
    $_SESSION['is_submitting'] = true;

    // ตรวจสอบว่ามีไฟล์ถูกอัปโหลดหรือไม่
    if (!empty($_FILES["fileToUpload"]["name"])) {
        $fileName = basename($_FILES["fileToUpload"]["name"]);
        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $fileName); // กรองชื่อไฟล์
        $targetFilePath = $targetDir . $fileName;
        $targetImgPath = $targetImg . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $allowTypes = strtoupper($isAdmin['POSITIONNAME']) == "ADMIN" ? array('jpg', 'png', 'jpeg', 'gif') : array('jpg', 'png', 'jpeg');

        // ตรวจสอบประเภทไฟล์
        if (in_array($fileType, $allowTypes)) {
            if ($_FILES["fileToUpload"]["size"] > $maxFileSize) {
                $_SESSION['error'] = "File size exceeds the ".formatSizeUnits($maxFileSize)." limit.";
            } else {
                // ย้ายไฟล์ไปยังปลายทาง
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFilePath)) {
                    // อัปเดตฐานข้อมูล
                    $insert = $db->nonQuery(
                        "UPDATE Employee SET UserProfile = ? WHERE EmployeeID = ?",
                        [$targetImgPath, $_SESSION['EmployeeID']]
                    );
                    if ($insert) {
                        $_SESSION['success'] = "The file " . htmlspecialchars($fileName) . " has been uploaded successfully.";
                        $emp = $db->getEmployee(); // ดึงข้อมูลพนักงานอีกครั้งหลังอัปเดต
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
    } else {
        $_SESSION['error'] = "Please select a file to upload.";
    }

    unset($_SESSION['is_submitting']); // ลบ session หลังจากอัปโหลดเสร็จ
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - My Profile</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">PERSONAL DETAILS</span>
        </div>
        <div class="container profile-container mt-3">
            <div class="profile-header">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <label for="fileToUpload">
                        <?php if (!empty($emp['USERPROFILE'])): ?>
                            <img src="<?php echo htmlspecialchars($emp['USERPROFILE']); ?>" alt="User Profile"
                                style="cursor: pointer; width: 124px; height: 124px; border-radius: 50%;">
                        <?php else: ?>
                            <i class="far fa-circle-user" style="font-size: 124px; cursor: pointer;"></i>
                        <?php endif; ?>
                    </label>
                    <input type="file" name="fileToUpload" id="fileToUpload" style="display: none;" accept="image/*">
                    <div>
                        <span><?php echo htmlspecialchars($emp['FNAME'] . " " . $emp['LNAME']); ?></span>
                    </div>
                </form>
            </div>

            <div class="profile-details p-d">
                <div class="box">
                    <label for="EmployeeID">Employee ID:</label>
                    <span id="EmployeeID"><?php echo htmlspecialchars($emp['EMPLOYEEID']); ?></span>
                </div>

                <div class="box">
                    <label for="Department">Department:</label>
                    <span id="Department">
                        <?php
                        $query = "SELECT DepartmentName FROM Department WHERE DepartmentID = :DepartmentID";
                        $param = [':DepartmentID' => $emp['DEPARTMENTID']];
                        $department = $db->Query($query, $param);
                        echo htmlspecialchars($department['DEPARTMENTNAME']);
                        ?>
                    </span>
                </div>

                <div class="box">
                    <label for="JobPosition">Job Position:</label>
                    <span id="JobPosition">
                        <?php
                        $query = "SELECT PositionName FROM Position WHERE PositionID = :PositionID";
                        $param = [':PositionID' => $emp['POSITIONID']];
                        $position = $db->Query($query, $param);
                        echo htmlspecialchars($position['POSITIONNAME']);
                        ?>
                    </span>
                </div>

                <div class="box">
                    <label for="PhoneNumber">Phone Number:</label>
                    <span id="PhoneNumber"><?php echo htmlspecialchars($emp['TELLNUMBER']); ?></span>
                </div>

                <div class="box">
                    <label for="FirstName">First Name:</label>
                    <span id="FirstName"><?php echo htmlspecialchars($emp['FNAME']); ?></span>
                </div>

                <div class="box">
                    <label for="LastName">Last Name:</label>
                    <span id="LastName"><?php echo htmlspecialchars($emp['LNAME']); ?></span>
                </div>
            </div>
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
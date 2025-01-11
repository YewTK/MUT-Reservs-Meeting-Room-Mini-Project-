<?php
session_start();
require_once "../config/connection.php";
require_once "../config/config.php";
include_once "../config/function.php";

$db = new connectDB();
$db->InitSession("../index.php");
$_SESSION['pageName'] = "manageMtgRm";
include_once "menus/navbar.php";

// Permissions check
if (!isset($permissions) || $permissions[4] != '1') {
    header('Location: home.php');
    exit();
}

// Post form manageMtgRm.php
if (isset($_POST['btn-edit-room'])) {
    $_SESSION['RoomID'] = $_POST['roomID'];
}

if (isset($_POST['buildingID']) && isset($_POST['selectedFloorID'])) {
    $buildingID = $_POST['buildingID'];
    $selectedFloorID = $_POST['selectedFloorID'];  // รับค่า Floor ID ที่ต้องการตั้งให้เป็น selected

    // Fetch floors based on selected building
    $floors = $db->Query("SELECT * FROM Floor WHERE BUILDINGID = ?", [$buildingID], true);

    if ($floors) {
        foreach ($floors as $floor) {
            // ตรวจสอบว่าชั้นนี้ตรงกับชั้นปัจจุบันที่ต้องการตั้งเป็น selected หรือไม่
            $selected = ($floor['FLOORID'] == $selectedFloorID) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($floor['FLOORID']) . '" ' . $selected . '>' . htmlspecialchars($floor['FLOORNUMBER']) . '</option>';
        }
    } else {
        echo '<option value="">No floors found</option>';
    }
    exit();
}

// Fetch required data for dropdowns
$buildings = $db->Query("SELECT * FROM Building");
$floors = $db->Query("SELECT * FROM Floor");
$typerooms = $db->Query("SELECT * FROM TypeRoom");
$roomStates = $db->Query("SELECT * FROM MeetingRoomState");

$query = "SELECT * FROM Detail";
$detail = $db->Query($query);

// Fetch the selected room's data
$queryRoom = "SELECT mr.ROOMID, mr.ROOMNAME, mr.ROOMIMG, mr.CAPACITY, mr.STATEID, 
          b.BUILDINGID, b.BUILDINGNAME, f.FLOORID, f.FLOORNUMBER, tr.TYPEID, tr.TYPENAME
          FROM MeetingRoom mr
          JOIN Floor f ON mr.FLOORID = f.FLOORID
          JOIN Building b ON f.BUILDINGID = b.BUILDINGID
          JOIN TypeRoom tr ON mr.TYPEID = tr.TYPEID
          WHERE mr.ROOMID = ?";
$roomID = $_SESSION['RoomID'];
$editRoom = $db->Query($queryRoom, [$roomID]);

$targetDir = "/home/u6511130055/public_html/cbt/assets/img/uploads/meetingrooms/";
$targetImg = "../assets/img/uploads/meetingrooms/";

if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['is_submitting']) && !isset($_POST['btn-update-room'])){
    $_SESSION['is_submitting'] = true;
    if (!empty($_FILES["fileToUpload"]["name"])) {
        $fileName = basename($_FILES["fileToUpload"]["name"]);
        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $fileName); // กรองชื่อไฟล์
        $targetFilePath = $targetDir .   $fileName;
        $targetImgPath = $targetImg . $fileName;
        $_SESSION['RoomIMG'] = $targetImgPath;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $isAdmin = true; 
        $allowTypes = $isAdmin ? array('jpg', 'png', 'jpeg', 'gif') : array('jpg', 'png', 'jpeg');

        // ตรวจสอบประเภทไฟล์
        if (in_array($fileType, $allowTypes)) {
            if ($_FILES["fileToUpload"]["size"] > $maxFileSize) {
                $_SESSION['error'] = "File size exceeds the ".formatSizeUnits($maxFileSize)." limit.";
            } else {
                // ย้ายไฟล์ไปยังปลายทาง
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFilePath)) {
                    // อัปเดตฐานข้อมูล
                    $insert = $db->nonQuery("UPDATE MeetingRoom SET ROOMIMG = ? WHERE ROOMID = ?", 
                        [$targetImgPath, $roomID]);
                    if ($insert) {
                        $_SESSION['success'] = "The file " . htmlspecialchars($fileName) . " has been uploaded successfully.";
                        $editRoom = $db->Query($queryRoom, [$roomID]);
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

// Update room information
if (isset($_POST['btn-update-room'])) {
    $roomName = $_POST['room-name'];
    $roomBuilding = $_POST['building'];
    $roomFloor = $_POST['floor'];
    $roomSize = $_POST['room-size'];
    $roomType = $_POST['typeroom'];
    $roomState = $_POST['room-state'];

    if (
        !empty($roomName) && !empty($roomBuilding) &&
        !empty($roomFloor) && !empty($roomSize) &&
        !empty($roomType) && !empty($roomState)
    ) {
        // Check if the room name exists, excluding the current room
        $stmt = $db->Query("SELECT 1 FROM MeetingRoom WHERE ROOMNAME = ? AND ROOMID != ?", [$roomName, $roomID], true);
        if (!$stmt) {
            // Proceed to update room details
            $query = "UPDATE MeetingRoom SET ROOMNAME = ?, STATEID = ?, CAPACITY = ?, FLOORID = ?, TYPEID = ? WHERE ROOMID = ?";
            $params = [$roomName, $roomState, $roomSize, $roomFloor, $roomType, $roomID];
            $stmt = $db->nonQuery($query, $params);

            // Update equipment information
            $state = true;
            if (!empty($_POST['equipment'])) {
                // Clear current equipment data
                $db->nonQuery("DELETE FROM DETAILROOM WHERE ROOMID = ?", [$roomID]);

                $equipmentSelected = $_POST['equipment'];
                $quantities = $_POST['quantity'];

                foreach ($equipmentSelected as $detailID => $value) {
                    if (isset($quantities[$detailID])) {
                        $query = "INSERT INTO DETAILROOM (ROOMID, DETAILID, NUMOFSTUFF) VALUES (?, ?, ?)";
                        $param = [$roomID, $detailID, $quantities[$detailID]];
                        $state = $db->nonQuery($query, $param);
                    }
                }
            }

            if ($stmt && $state) {
                $_SESSION['success'] = 'อัพเดทข้อมูลห้องสำเร็จ!!!';
                $editRoom = $db->Query($queryRoom, [$roomID]);
            } else {
                $_SESSION['error'] = 'เกิดข้อผิดพลาดไม่สามารถอัพเดทข้อมูลได้!!!';
            }
        } else {
            $_SESSION['error'] = 'ชื่อห้องนี้ถูกใช้แล้ว กรุณาเลือกชื่ออื่น!';
        }
    } else {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลห้องให้ครบถ้วนก่อนอัพเดทข้อมูล!!!';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo html_entity_decode($Title); ?> - Edit Rooms</title>
    <style>
        .container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 30px;
            margin-top: 20px;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        input[type="text"]::placeholder {
            color: #888;
            font-size: 14px;
        }

        select {
            color: #333;
        }

        .add-room-wrapper {
            display: flex;
            gap: 30px;
            justify-content: center;
            align-items: flex-start;
        }

        .add-room-wrapper .left-section {
            flex: 1;
            max-width: 1000px;
            text-align: center;
        }

        .add-room-wrapper .right-section {
            flex: 1;
            max-width: 600px;
        }

        #room-name,
        #room-size {
            width: 100%;
        }

        select,
        input {
            margin-top: 10px;
        }

        .add-room-wrapper .right-section .form-select,
        .add-room-wrapper .right-section .form-control {
            margin-bottom: 15px;
        }

        img {
            width: 750px;
            height: auto;
            object-fit: cover;
        }
    </style>
    <?php require_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">EDIT ROOM</span>
        </div>
        <div class="container">
            <div class="add-room-wrapper">
                <div class="left-section">
                    <!-- Dynamic room image -->
                    <!--img src="<?php //echo htmlspecialchars($editRoom['ROOMIMG']); ?>" alt="Room Image" /-->
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <label for="fileToUpload">
                        <?php if (!empty($editRoom['ROOMIMG'])): ?>
                            <img src="<?php echo htmlspecialchars($editRoom['ROOMIMG']); ?>" alt="RoomImg">
                        <?php else: ?>
                            <img src="../assets/img/uploads/meetingrooms/noimg.png" alt="RoomImg">
                        <?php endif; ?>
                    </label>
                    <input type="file" name="fileToUpload" id="fileToUpload" style="display: none;" accept="image/*">
                </form>
                </div>
                <form method="POST">
                    <div class="right-section">
                        <!-- Room Name -->
                        <input type="text" class="form-control" name="room-name" placeholder="ROOM NAME" id="room-name"
                            value="<?php echo htmlspecialchars($editRoom["ROOMNAME"]); ?>" maxlength="255">

                        <!-- Building Dropdown -->
                        <select class="form-select" id="Building" name="building" required
                            onchange="fetchFloors(this.value)">
                            <option value="">Select Building</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo htmlspecialchars($building['BUILDINGID']); ?>" <?php echo ($building['BUILDINGID'] == $editRoom['BUILDINGID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($building['BUILDINGNAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Floor Dropdown -->
                        <select class="form-select" id="Floor" name="floor" <?php if (!$editRoom['BUILDINGID'])
                            echo 'disabled'; ?>>
                            <option value="">Select Building First</option>
                        </select>

                        <!-- Room Capacity -->
                        <input type="text" class="form-control" name="room-size" placeholder="CAPACITY" id="room-size"
                            value="<?php echo htmlspecialchars($editRoom['CAPACITY']); ?>" maxlength="5">

                        <!-- Room Type Dropdown -->
                        <select class="form-select" id="Typeroom" name="typeroom">
                            <option value="">Select Room Type</option>
                            <?php foreach ($typerooms as $typeroom): ?>
                                <option value="<?php echo htmlspecialchars($typeroom['TYPEID']); ?>" <?php echo ($typeroom['TYPEID'] == $editRoom['TYPEID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($typeroom['TYPENAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Room State Dropdown -->
                        <select class="form-select" id="room-state" name="room-state">
                            <option value="">Select Room State</option>
                            <?php foreach ($roomStates as $state): ?>
                                <option value="<?php echo htmlspecialchars($state['STATEID']); ?>" <?php echo ($state['STATEID'] == $editRoom['STATEID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['STATEDESCRIPTION']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Room Equipment -->
                        <div>
                            <?php
                            if (!empty($detail)) {
                                foreach ($detail as $dt) {
                                    // ดึงข้อมูลจำนวนอุปกรณ์ปัจจุบันสำหรับห้องที่กำลังแก้ไข
                                    $currentQty = $db->Query("SELECT NUMOFSTUFF FROM DETAILROOM WHERE ROOMID = ? AND DETAILID = ?", [$roomID, $dt['DETAILID']]);
                                    $currentQty = $currentQty ? $currentQty['NUMOFSTUFF'] : 0; // ถ้าไม่มีอุปกรณ์ในห้อง ให้แสดงเป็น 0
                            
                                    echo '<label>';
                                    echo htmlspecialchars($dt['NAMESTUFF']) . ': ';
                                    ?>
                                    <input type="checkbox" name="equipment[<?php echo htmlspecialchars($dt['DETAILID']); ?>]"
                                        value="1"
                                        onchange="toggleInput(this, 'quantity-<?php echo htmlspecialchars($dt['DETAILID']); ?>')"
                                        <?php if ($currentQty > 0)
                                            echo 'checked'; ?>>
                                    จำนวน:
                                    <input type="number" id="quantity-<?php echo htmlspecialchars($dt['DETAILID']); ?>"
                                        name="quantity[<?php echo htmlspecialchars($dt['DETAILID']); ?>]"
                                        value="<?php echo $currentQty; ?>" min="1" style="width: 60px;" <?php if ($currentQty == 0)
                                                echo 'disabled'; ?>>
                                    <?php
                                    echo '</label><br>';
                                }
                            } else {
                                echo "<p>No equipment available for this room.</p>";
                            }
                            ?>
                        </div>

                        <div class="mt-5">
                            <button class="btn btn-primary" type="submit" name="btn-update-room">
                                <i class="fas fa-save"></i> Update Room
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>
    <?php include_once "menus/footer.php"; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: '<?php echo htmlspecialchars($_SESSION['warning']); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true
        });
    </script>
    <?php unset($_SESSION['warning']); ?>
<?php endif; ?>

    <script>
        function fetchFloors(buildingID, selectedFloorID = null) {
            if (buildingID) {
                $.ajax({
                    url: '',  // URL ของไฟล์ PHP ที่ดึงข้อมูล
                    type: 'POST',
                    data: {
                        buildingID: buildingID,
                        selectedFloorID: selectedFloorID // ส่งค่า selectedFloorID ไปด้วย
                    },
                    success: function (response) {
                        $('#Floor').html(response);
                        $('#Floor').prop('disabled', false); // เปิดใช้งาน dropdown ของชั้น
                    }
                });
            } else {
                $('#Floor').html('<option value="">Select Building First</option>');
                $('#Floor').prop('disabled', true);
            }
        }
        $(document).ready(function () {
            var selectedBuilding = "<?php echo $editRoom['BUILDINGID']; ?>";
            var selectedFloor = "<?php echo $editRoom['FLOORID']; ?>";
            if (selectedBuilding) {
                fetchFloors(selectedBuilding, selectedFloor); // ส่งค่า selectedFloor ไปด้วย
            }
        });

        function toggleInput(checkbox, inputID) {
            var input = document.getElementById(inputID);
            if (checkbox.checked) {
                input.disabled = false;  // เปิดให้แก้ไขจำนวนได้
            } else {
                input.disabled = true;  // ปิดไม่ให้แก้ไข
            }
        }




        document.addEventListener('DOMContentLoaded', function() {
        // ตัวแปรควบคุมการ submit
        let isSubmitting = false;

        // จัดการ event การเลือกไฟล์
        const fileInput = document.getElementById('fileToUpload');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
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
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                isSubmitting = false;
            }
        });

        // Reset isSubmitting เมื่อออกจากหน้า
        window.addEventListener('beforeunload', function() {
            isSubmitting = false;
        });
    });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>

<?php
session_start();
require_once "../config/connection.php";
require_once "../config/config.php";
include_once "../config/function.php";

$db = new connectDB();
$db->InitSession("../index.php");
$_SESSION['pageName'] = "manageMtgRm";
include_once "menus/navbar.php";

if (!isset($permissions) || $permissions[4] != '1') {
    header('Location: home.php');
    exit();
}

// Fetch necessary data for dropdowns
$buildings = $db->Query("SELECT * FROM Building");
$typerooms = $db->Query("SELECT * FROM TypeRoom");
$mrtState = $db->Query("SELECT * FROM MeetingRoomState");

$query = "SELECT * FROM Detail";
$detail = $db->Query($query);

// Handle floor selection based on building
if (isset($_POST['buildingID'])) {
    $buildingID = $_POST['buildingID'];
    $floors = $db->Query("SELECT * FROM Floor WHERE BUILDINGID = ?", [$buildingID], true);

    if ($floors) {
        foreach ($floors as $floor) {
            echo '<option value="' . htmlspecialchars($floor['FLOORID']) . '">' . htmlspecialchars($floor['FLOORNUMBER']) . '</option>';
        }
    } else {
        echo '<option value="">No floors found</option>';
    }
    exit();
}

$targetDir = "/home/u6511130055/public_html/cbt/assets/img/uploads/meetingrooms/";
$targetImg = "../assets/img/uploads/meetingrooms/";
$defImg = "../assets/img/uploads/meetingrooms/noimg.png";

//def cookie time 
$defDate = (60 * 60) * 24; // 1 day

if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['btn-add-rooms'])){
    setcookie("add_img_rooms", '', time() + $defDate, "/");
    //refesh page when new post and reset cookie
    header("Location: addRoom.php");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['is_submitting']) && !isset($_POST['btn-add-rooms'])) {
    $_SESSION['is_submitting'] = true;
    if (!empty($_FILES["fileToUpload"]["name"])) {
        $fileName = basename($_FILES["fileToUpload"]["name"]);
        $fileName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $fileName); // กรองชื่อไฟล์
        $targetFilePath = $targetDir . $fileName;
        $targetImgPath = $targetImg . $fileName;
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
                        setcookie("add_img_rooms", $targetImgPath, time() + $defDate, "/");
                        $_SESSION['success'] = "The file " . htmlspecialchars($fileName) . " has been uploaded successfully.";
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

// Handle form submission
if (isset($_POST['btn-add-rooms'])) {
    $nameRoom = $_POST['room-name'];
    $buildingRoom = $_POST['building'];
    $floorRoom = $_POST['floor'];
    $sizeRoom = $_POST['room-size'];
    $typeRoom = $_POST['typeroom'];
    $stateRoom = $_POST['room-state'];
    $roomImg = '../assets/img/uploads/meetingrooms/noimg.png';

    // Validate that all fields are filled in
    if (!empty($nameRoom) && !empty($buildingRoom) && !empty($floorRoom) && !empty($sizeRoom) && !empty($typeRoom) && !empty($stateRoom)) {
        // Check if the room name already exists
        $query = "SELECT 1 FROM MeetingRoom WHERE ROOMNAME = ?";
        $stmt = $db->query($query, [$nameRoom]);

        if ($stmt) {
            $_SESSION['error'] = 'มีห้องนี้อยู่แล้ว!';
        } else {
            // Generate a new Room ID
            $query = "SELECT MAX(ROOMID) AS MaxRoomID FROM MeetingRoom";
            $stmt = $db->Query($query, false, false, true);
            $maxID = $stmt['MAXROOMID'] + 1;

            // Insert new room data
            $query = "INSERT INTO MeetingRoom (ROOMID, STATEID, TYPEID, ROOMNAME, CAPACITY, ROOMIMG, FLOORID) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $param = [$maxID, $stateRoom, $typeRoom, $nameRoom, $sizeRoom, $_COOKIE['add_img_rooms'] ?? $defImg, $floorRoom];
            $stmt = $db->nonQuery($query, $param);
            $state = true;

            // Handle equipment
            if (!empty($_POST['equipment'])) {
                $equipmentSelected = $_POST['equipment']; // Get selected equipment checkboxes
                $quantities = $_POST['quantity']; // Get quantities for each selected equipment

                foreach ($equipmentSelected as $detailID => $value) {
                    if (isset($quantities[$detailID])) {
                        $query = "INSERT INTO DETAILROOM (ROOMID, DETAILID, NUMOFSTUFF) VALUES (?, ?, ?)";
                        $param = [$maxID, $detailID, $quantities[$detailID]];
                        $state = $db->nonQuery($query, $param);
                    }
                }
            }

            if ($stmt && $state) {
                setcookie("add_img_rooms", '', time() - $defDate, "/");
                ob_start();
                $_SESSION['success'] = "เพิ่มห้องสำเร็จ!";
                header("Location: manageMtgRm.php");
                ob_end_flush();
                exit();
            } else {
                $_SESSION['error'] = 'ไม่สามารถเพิ่มห้องได้!';
            }
        }
    } else {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบก่อนเพิ่มห้อง';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo html_entity_decode($Title); ?> - Add Rooms</title>
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
            <span class="">ADD ROOM</span>
        </div>
        <div class="container">
            <div class="add-room-wrapper">
                <div class="left-section">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <label for="fileToUpload">
                                <img src=" <?php echo isset($_COOKIE['add_img_rooms']) ? $_COOKIE['add_img_rooms'] : $defImg?>" alt="RoomImg">
                        </label>
                        <input type="file" name="fileToUpload" id="fileToUpload" style="display: none;" accept="image/*">
                    </form>
                </div>
                <form method="POST">
                    <div class="right-section">
                        <!-- Room Name -->
                        <input type="text" class="form-control" name="room-name" placeholder="ROOM NAME" id="room-name"
                            maxlength="255">

                        <!-- Building Dropdown -->
                        <select class="form-select" id="Building" name="building" required
                            onchange="fetchFloors(this.value)">
                            <option value="">Select Building</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo htmlspecialchars($building['BUILDINGID']); ?>">
                                    <?php echo htmlspecialchars($building['BUILDINGNAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Floor Dropdown -->
                        <select class="form-select" id="Floor" name="floor" disabled>
                            <option value="">Select Building First</option>
                        </select>

                        <!-- Room Capacity -->
                        <input type="text" class="form-control" name="room-size" placeholder="CAPACITY" id="room-size"
                            maxlength="5">

                        <!-- Room Type Dropdown -->
                        <select class="form-select" id="Typeroom" name="typeroom">
                            <option value="">Select Room Type</option>
                            <?php foreach ($typerooms as $typeroom): ?>
                                <option value="<?php echo htmlspecialchars($typeroom['TYPEID']); ?>">
                                    <?php echo htmlspecialchars($typeroom['TYPENAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Room State Dropdown -->
                        <select class="form-select" id="room-state" name="room-state">
                            <option value="">Select Room State</option>
                            <?php foreach ($mrtState as $meetingRoomsState): ?>
                                <option value="<?php echo htmlspecialchars($meetingRoomsState['STATEID']); ?>">
                                    <?php echo htmlspecialchars($meetingRoomsState['STATEDESCRIPTION']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Room Equipment -->
                        <div class="room-equipment">
                            <?php
                            if (!empty($detail)) {
                                foreach ($detail as $dt) {
                                    echo '<label>';
                                    echo htmlspecialchars($dt['NAMESTUFF']) . ': ';
                                    ?>
                                    <input type="checkbox" name="equipment[<?php echo htmlspecialchars($dt['DETAILID']); ?>]"
                                        value="1"
                                        onchange="toggleInput(this, 'quantity-<?php echo htmlspecialchars($dt['DETAILID']); ?>')">
                                    จำนวน:
                                    <input type="number" id="quantity-<?php echo htmlspecialchars($dt['DETAILID']); ?>"
                                        name="quantity[<?php echo htmlspecialchars($dt['DETAILID']); ?>]" value="1" min="1"
                                        style="width: 60px;" disabled>
                                    <?php
                                    echo '</label><br>';
                                }
                            } else {
                                echo "<p>No equipment available for this room.</p>";
                            }
                            ?>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="mt-4">
                            <button class="btn btn-primary" type="submit" name="btn-add-rooms">
                                <i class="fas fa-plus-circle"></i> Add Room
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Function to enable or disable the quantity input based on checkbox state
        function toggleInput(checkbox, inputID) {
            var input = document.getElementById(inputID);
            if (checkbox.checked) {
                input.disabled = false;
            } else {
                input.disabled = true;
                input.value = 1; // Reset value to 1 if unchecked
            }
        }

        function fetchFloors(buildingID) {
            if (buildingID) {
                $.ajax({
                    url: '', // Change to the appropriate file handling this request
                    type: 'POST',
                    data: {
                        buildingID: buildingID
                    },
                    success: function (response) {
                        $('#Floor').html(response); // Update the floor dropdown with the fetched data
                        $('#Floor').prop('disabled', false); // Enable the floor dropdown
                    }
                });
            } else {
                $('#Floor').html('<option value="">Select Building First</option>'); // Reset floor dropdown
                $('#Floor').prop('disabled', true); // Disable the floor dropdown
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
    <?php include_once "menus/footer.php"; ?>
</body>

</html>
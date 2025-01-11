<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
include_once "../config/function.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = 'booking';
include_once "menus/navbar.php";


if ($permissions[1] != '1') {
    $_SESSION['error'] = "ท่านไม่มีสิทธิ์ในการเข้าถึง " . strtoupper($_SESSION['pageName']);
    header('Location: home.php');
    exit();
}


$queryRes = "SELECT bd.BUILDINGNAME, mrt.ROOMNAME, f.FLOORNUMBER, t.TYPENAME, mrt.CAPACITY, b.DATEBEGIN, b.DATEEND,
        r.RESERVATIONID, r.RESERVATIONDATE, b.BOOKINGID, t.APPROVALREQUIRED, rbs.STATUSID, mrt.ROOMID
FROM BOOKING b
JOIN MEETINGROOM mrt ON mrt.ROOMID = b.ROOMID
JOIN FLOOR f ON f.FLOORID = mrt.FLOORID
JOIN BUILDING bd ON bd.BUILDINGID = f.BUILDINGID
JOIN RESERVATION r ON r.RESERVATIONID = b.RESERVATIONID
JOIN TYPEROOM t ON t.TYPEID = mrt.TYPEID
LEFT JOIN ROOMBOOKINGSTATUS rbs ON rbs.BOOKINGID = b.BOOKINGID 
WHERE r.EmployeeID = ? AND rbs.STATUSID IS NULL";
$stmtRes = $db->Query($queryRes, [$_SESSION['EmployeeID']], true) ?? [];


//สำหรับการดูว่าห้องนี้ถูกจองแล้วหรือยัง
$querytime = "SELECT b.DATEBEGIN, b.DATEEND, b.ROOMID
            FROM ROOMBOOKINGSTATUS rbk
            JOIN BOOKING b ON b.BOOKINGID = rbk.BOOKINGID
            WHERE b.ROOMID = ? AND (
      (rbk.DATEBEGIN <= TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS') AND rbk.DATEEND >= TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS')) OR
      (rbk.DATEBEGIN BETWEEN TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS') AND TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS')) OR
      (rbk.DATEEND BETWEEN TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS') AND TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))
)
              AND rbk.STATUSID != 3";


//กำหนดค่าเริ่มต้น
$stateID = '1';
$desc = 'อะไรน้อง hack ไร';

//POS มาจาก home
if (isset($_POST['btn-booking'])) {
    $_SESSION['meetingroomID'] = $_POST['room-id'];
    $stateID = $_POST['state-id'];
    $desc = $_POST['state-desc'];
}

//ตรวจสอบสถานะห้องก่อนจอง
if (($stateID != '1') || (!isset($_SESSION['meetingroomID']))) {
    unset($_SESSION['meetingroomID']);
    $_SESSION['error'] = $desc;
    header('Location: home.php');
    exit();
}
if (isset($_POST['btn-addbooking'])) {
    $RoomID = $_POST['room-id'];
    $beginDate = $_POST['begin-date']; // รูปแบบ: MM/dd/yyyy
    $beginTime = $_POST['begin-time']; // รูปแบบ: HH:MM AM/PM
    $endDate = $_POST['end-date'];     // รูปแบบ: MM/dd/yyyy
    $endTime = $_POST['end-time'];     // รูปแบบ: HH:MM AM/PM

    // Create DateTime objects
    $beginDateTime = $beginDate . ' ' . $beginTime;
    $endDateTime = $endDate . ' ' . $endTime;

    if ($beginDateTime && $endDateTime) {
        // Convert to the required format
        $formattedBeginDateTime = $beginDateTime;
        $formattedEndDateTime = $endDateTime;

        $stmlQueryTime = $db->Query($querytime, [
            $RoomID,
            $beginDateTime,
            $endDateTime,
            $beginDateTime,
            $endDateTime,
            $beginDateTime,
            $endDateTime
        ]);

        if ($stmlQueryTime == null) {
            // Query to get APPROVALREQUIRED for the selected room
            $queryApproval = "
                SELECT t.APPROVALREQUIRED 
                FROM MEETINGROOM m 
                JOIN TYPEROOM t ON t.TYPEID = m.TYPEID 
                WHERE m.ROOMID = ?
            ";
            $approvalResult = $db->Query($queryApproval, [$RoomID], false, true);
            $approvalRequired = $approvalResult['APPROVALREQUIRED'];

            // Convert date and time to the format Oracle requires with TO_DATE
            $formattedBeginDateTime = $beginDate . ' ' . $beginTime;
            $formattedEndDateTime = $endDate . ' ' . $endTime;

            // Get max booking ID
            $maxIDQuery = "SELECT COALESCE(MAX(BOOK_ID), 0) as MAXID FROM ROOMBOOKING";
            $stmtBookingMaxID = $db->Query($maxIDQuery, false, false, true);
            $maxbookID = $stmtBookingMaxID['MAXID'] + 1;

            // Insert into ROOMBOOKING
            $insertBookingQuery = "INSERT INTO ROOMBOOKING (BOOK_ID, ROOMID) VALUES (?, ?)";
            $paramRoombooking = [$maxbookID, $RoomID];
            $insertRoomBooking = $db->nonQuery($insertBookingQuery, $paramRoombooking);

            // Global query for booking
            $queryBooking = "
                INSERT INTO BOOKING (BOOKINGID, ROOMID, RESERVATIONID, DATEBEGIN, DATEEND) 
                VALUES (?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'), TO_DATE(?, 'YYYY-MM-DD HH24:MI'))
            ";

            // Check if EMPLOYEEID exists in RESERVATION
            $query = "SELECT RESERVATIONID FROM RESERVATION WHERE EMPLOYEEID = ?";
            $stmt = $db->Query($query, [$_SESSION['EmployeeID']]);

            if ($stmt && !empty($stmt)) {
                // If there is already a reservation, add a new booking
                $QueryMaxID = "SELECT COALESCE(MAX(BOOKINGID), 0) as MAXID FROM BOOKING";
                $getMaxid = $db->Query($QueryMaxID, false, false, true);
                $MaxIDBooking = $getMaxid['MAXID'] + 1;

                $param = [$MaxIDBooking, $RoomID, $stmt['RESERVATIONID'], $formattedBeginDateTime, $formattedEndDateTime];
                $insertBooking = $db->nonQuery($queryBooking, $param);

                if ($insertBooking) {
                    // Set status based on APPROVALREQUIRED
                    $statusID = ($approvalRequired == '1') ? '1' : '2'; // Default is '1' for pending approval

                    // Get max status ID
                    $queryConfirm = "SELECT COALESCE(MAX(BOOKINGSTATUS_NO), 0) as MAXID FROM ROOMBOOKINGSTATUS";
                    $stmtMaxID = $db->Query($queryConfirm, false, false, true);
                    $maxID = $stmtMaxID['MAXID'] + 1; // Increment MAXID

                    $queryStatus = "INSERT INTO ROOMBOOKINGSTATUS (BOOKINGSTATUS_NO, BOOKINGID, STATUSID,DATEBEGIN,DATEEND) 
                    VALUES (?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'), TO_DATE(?, 'YYYY-MM-DD HH24:MI'))";
                    $insertStatus = $db->nonQuery($queryStatus, [$maxID, $MaxIDBooking, $statusID, $formattedBeginDateTime, $formattedEndDateTime]);

                    // Generate QR code
                    $qrCodeInfo = generateUniqueCode($db);


                    $queryQr = "
                        INSERT INTO QRCODE (QRCODEID, CODE, BOOKINGID, ISUSE, QRCODE_GENERATED_DATE) 
                        VALUES (?, ?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'))
                    ";
                    $paramQr = [$qrCodeInfo['MAXID'], $qrCodeInfo['CODE'], $MaxIDBooking, 0, $formattedBeginDateTime];
                    $insertQr = $db->nonQuery($queryQr, $paramQr);

                    if ($insertStatus && $insertQr) {
                        $_SESSION['success'] = ($approvalRequired == '1') ?
                            "เพิ่มการจองเรียบร้อยแล้ว รอการอนุมัติ!" :
                            "เพิ่มการจองเรียบร้อยแล้ว อนุมัติอัตโนมัติ!";
                        header("Location: home.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "ไม่สามารถอัปเดตสถานะการจองได้!";
                    }
                } else {
                    $_SESSION['error'] = "ไม่สามารถเพิ่มไปยังจองได้!! error code: 1";
                }
            } else {
                // If there is no reservation for this employee, add a RESERVATION and a new booking
                $QueryMaxID = "SELECT COALESCE(MAX(RESERVATIONID), 0) as MAXID FROM RESERVATION";
                $getMaxid = $db->Query($QueryMaxID, false, false, true);
                $MaxIDRes = $getMaxid['MAXID'] + 1;

                // Insert data into RESERVATION
                $query = "INSERT INTO RESERVATION (RESERVATIONID, EMPLOYEEID) VALUES (?, ?)";
                $insertRes = $db->nonQuery($query, [$MaxIDRes, $_SESSION['EmployeeID']]);

                // Insert a new booking
                $QueryMaxID = "SELECT COALESCE(MAX(BOOKINGID), 0) as MAXID FROM BOOKING";
                $getMaxid = $db->Query($QueryMaxID, false, false, true);
                $MaxIDBooking = $getMaxid['MAXID'] + 1;

                $param = [$MaxIDBooking, $RoomID, $MaxIDRes, $formattedBeginDateTime, $formattedEndDateTime];
                $insertBooking = $db->nonQuery($queryBooking, $param);

                if ($insertRes && $insertBooking) {
                    // Get BOOKINGSTATUS_NO
                    $QueryMaxStatusNo = "SELECT COALESCE(MAX(BOOKINGSTATUS_NO), 0) as MAXNO FROM ROOMBOOKINGSTATUS";
                    $getMaxStatusNo = $db->Query($QueryMaxStatusNo, false, false, true);
                    $MaxStatusNo = $getMaxStatusNo['MAXNO'] + 1;

                    // Set status based on APPROVALREQUIRED
                    $statusID = ($approvalRequired == '1') ? '1' : '2'; // Default is '1' for pending approval

                    $queryStatus = "INSERT INTO ROOMBOOKINGSTATUS (BOOKINGSTATUS_NO, BOOKINGID, STATUSID,DATEBEGIN,DATEEND) 
                    VALUES (?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'), TO_DATE(?, 'YYYY-MM-DD HH24:MI'))";
                    $insertStatus = $db->nonQuery($queryStatus, [$MaxStatusNo, $MaxIDBooking, $statusID, $formattedBeginDateTime, $formattedEndDateTime]);

                    // Generate QR code
                    $qrCodeInfo = generateUniqueCode($db);
                    $queryQr = "
                        INSERT INTO QRCODE (QRCODEID, CODE, BOOKINGID, ISUSE, QRCODE_GENERATED_DATE) 
                        VALUES (?, ?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'))
                    ";
                    $paramQr = [$qrCodeInfo['MAXID'], $qrCodeInfo['CODE'], $MaxIDBooking, 0, $formattedBeginDateTime];
                    $insertQr = $db->nonQuery($queryQr, $paramQr);

                    if ($insertStatus && $insertQr) {
                        $_SESSION['success'] = ($approvalRequired == '1') ?
                            "เพิ่มการจองเรียบร้อยแล้ว รอการอนุมัติ!" :
                            "เพิ่มการจองเรียบร้อยแล้ว อนุมัติอัตโนมัติ!";
                        header("Location: home.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "ไม่สามารถอัปเดตสถานะการจองได้!";
                    }
                } else {
                    $_SESSION['error'] = "ไม่สามารถเพิ่มไปยัง List ได้!! error code: 2";
                }
            }
        } else {
            $_SESSION['error'] = "ห้องนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว!";
        }
    } else {
        $_SESSION['error'] = "รูปแบบวันที่หรือเวลาไม่ถูกต้อง";
    }
}


if (isset($_POST['btn-addList'])) {
    $RoomID = $_POST['room-id'];
    $beginDate = $_POST['begin-date']; // รูปแบบ: MM/dd/yyyy
    $beginTime = $_POST['begin-time']; // รูปแบบ: HH:MM AM/PM
    $endDate = $_POST['end-date'];     // รูปแบบ: MM/dd/yyyy
    $endTime = $_POST['end-time'];     // รูปแบบ: HH:MM AM/PM

    // Create DateTime objects
    $beginDateTime = $beginDate . ' ' . $beginTime;
    $endDateTime = $endDate . ' ' . $endTime;

    if ($beginDateTime && $endDateTime) {
        // Convert to the required format
        $formattedBeginDateTime = $beginDateTime;
        $formattedEndDateTime = $endDateTime;
        $stmlQueryTime = $db->Query($querytime, [
            $RoomID,
            $beginDateTime,
            $endDateTime,
            $beginDateTime,
            $endDateTime,
            $beginDateTime,
            $endDateTime
        ]);
        if ($stmlQueryTime == null) {
            // Proceed with booking
            $queryBooking = "INSERT INTO BOOKING (BOOKINGID, ROOMID, RESERVATIONID, DATEBEGIN, DATEEND) 
                             VALUES (?, ?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'), TO_DATE(?, 'YYYY-MM-DD HH24:MI'))";

            // Check for existing reservation
            $query = "SELECT RESERVATIONID FROM RESERVATION WHERE EMPLOYEEID = ?";
            $stmt = $db->Query($query, [$_SESSION['EmployeeID']]);

            if ($stmt && !empty($stmt)) {
                // ถ้ามีการใบจองอยู่แล้ว ให้เพิ่มการจองใหม่ใน BOOKING
                $QueryMaxID = "SELECT COALESCE(MAX(BOOKINGID), 0) as MAXID FROM BOOKING";
                $getMaxid = $db->Query($QueryMaxID, false, false, true);
                $MaxIDBooking = $getMaxid['MAXID'] + 1;

                $param = [$MaxIDBooking, $RoomID, $stmt['RESERVATIONID'], $formattedBeginDateTime, $formattedEndDateTime];
                $insertBooking = $db->nonQuery($queryBooking, $param);

                if ($insertBooking) {
                    $_SESSION['success'] = "เพิ่มไปที่ List เรียบร้อยแล้ว!!";
                    header("Location: home.php");
                    exit();
                } else {
                    $_SESSION['error'] = "ไม่สามารถเพิ่มไปยัง List ได้!! error code: 1";
                }
            } else {
                // ถ้ายังไม่มีการจองของพนักงานนี้ ให้เพิ่ม RESERVATION และการจองใหม่ใน BOOKING
                $QueryMaxID = "SELECT COALESCE(MAX(RESERVATIONID), 0) as MAXID FROM RESERVATION";
                $getMaxid = $db->Query($QueryMaxID, false, false, true);
                $MaxIDRes = $getMaxid['MAXID'] + 1;

                // เพิ่มข้อมูลใน RESERVATION
                $query = "INSERT INTO RESERVATION (RESERVATIONID, EMPLOYEEID) VALUES (?, ?)";
                $insertRes = $db->nonQuery($query, [$MaxIDRes, $_SESSION['EmployeeID']]);

                $QueryMaxID = "SELECT COALESCE(MAX(BOOKINGID), 0) as MAXID FROM BOOKING";
                $getMaxid = $db->Query($QueryMaxID, false, false, true);
                $MaxIDBooking = $getMaxid['MAXID'] + 1;

                $param = [$MaxIDBooking, $RoomID, $stmt['RESERVATIONID'], $formattedBeginDateTime, $formattedEndDateTime];
                $insertBooking = $db->nonQuery($queryBooking, $param);


                if ($insertBooking) {
                    $_SESSION['success'] = "เพิ่มไปที่ List เรียบร้อยแล้ว!!";
                    header("Location: home.php");
                    exit();
                } else {
                    $_SESSION['error'] = "ไม่สามารถเพิ่มไปยัง List ได้!! error code: 1";
                }
            }
        } else {
            $_SESSION['error'] = "ห้องนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว!";
        }
    } else {
        $_SESSION['error'] = "รูปแบบวันที่หรือเวลาไม่ถูกต้อง";
    }
}

if (isset($_COOKIE['roomId'])) {
    $GETRoomID = $_COOKIE['roomId']; // Get ROOMID from cookie

    $currenttimeBooking = "SELECT rbk.DATEBEGIN, rbk.DATEEND 
                               FROM ROOMBOOKINGSTATUS rbk 
                               JOIN BOOKING b ON b.BOOKINGID = rbk.BOOKINGID
                               JOIN MEETINGROOM mrt ON mrt.ROOMID = b.ROOMID 
                               WHERE b.ROOMID = ? AND rbk.STATUSID != 3";
    $stmlcurrentTime = $db->Query($currenttimeBooking, [$GETRoomID], true);

}


$roomID = $_SESSION['meetingroomID'] ?? '';
if ($roomID) {
    // ดึงข้อมูลห้อง
    $query = "SELECT mr.ROOMID, mr.ROOMNAME, mr.ROOMIMG, mr.CAPACITY,
               b.BUILDINGNAME, f.FLOORNUMBER, tr.TYPENAME , mrs.STATEDESCRIPTION
        FROM MeetingRoom mr
        JOIN Floor f ON mr.FLOORID = f.FLOORID
        JOIN Building b ON f.BUILDINGID = b.BUILDINGID
        JOIN TypeRoom tr ON mr.TYPEID = tr.TYPEID
        JOIN MeetingRoomState mrs ON mrs.STATEID = mr.STATEID
        WHERE mr.ROOMID = ?";
    $room = $db->Query($query, [$roomID]);

    // ดึงข้อมูลรายละเอียดห้อง
    $query = "SELECT dr.ROOMID, dr.DETAILID, d.NAMESTUFF, dr.NUMOFSTUFF
              FROM DETAILROOM dr
              JOIN Detail d ON dr.DETAILID = d.DETAILID 
              WHERE dr.ROOMID = ?";
    $detail = $db->Query($query, [$roomID], true);
} else {
    $room = [];
    $detail = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Room Booking</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.0/mdb.min.css" rel="stylesheet" />
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="container my-4">
        <div class="row">
            <!-- Image Section -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="bg-image hover-overlay ripple" data-mdb-ripple-color="light" onclick="showImage()">
                        <img src="<?php echo htmlspecialchars($room['ROOMIMG'] ?? ''); ?>" class="img-fluid"
                            alt="Room Image">
                        <a>
                            <div class="mask" style="background-color: rgba(0, 0, 0, 0.5);"></div>
                        </a>
                    </div>
                </div>
                <!-- Booking Information Section -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Current Bookings:</h5>
                        <?php if (!empty($stmlcurrentTime)): ?>
                            <p class="text-muted">จำนวนการจอง: <?php echo count($stmlcurrentTime); ?> รายการ</p>
                            <ul class="list-group">
                                <?php
                                $index = 1;
                                foreach ($stmlcurrentTime as $bookingTime):
                                    $beginDate = DateTime::createFromFormat('d-M-y h.i.s.u A', $bookingTime['DATEBEGIN'])->format('d/m/Y H:i');
                                    $endDate = DateTime::createFromFormat('d-M-y h.i.s.u A', $bookingTime['DATEEND'])->format('d/m/Y H:i');
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>รายการที่ <?php echo $index; ?>:</strong><br>
                                            <strong>จาก:</strong> <?php echo htmlspecialchars($beginDate); ?><br>
                                            <strong>ถึง:</strong> <?php echo htmlspecialchars($endDate); ?>
                                        </div>
                                    </li>
                                    <?php $index++; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center text-muted">ไม่มีการจองห้องประชุมในขณะนี้</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Information Section -->
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($room['ROOMNAME'] ?? ''); ?></h2>
                        <p class="card-text"><strong>Building:</strong>
                            <?php echo htmlspecialchars($room['BUILDINGNAME'] ?? ''); ?></p>
                        <p class="card-text"><strong>Floor:</strong>
                            <?php echo htmlspecialchars($room['FLOORNUMBER'] ?? ''); ?></p>
                        <p class="card-text"><strong>Capacity:</strong>
                            <?php echo htmlspecialchars($room['CAPACITY'] ?? ''); ?></p>
                        <p class="card-text"><strong>Type:</strong>
                            <?php echo htmlspecialchars($room['TYPENAME'] ?? ''); ?></p>
                        <p class="card-text"><strong>Status:</strong>
                            <?php echo htmlspecialchars($room['STATEDESCRIPTION'] ?? ''); ?></p>

                        <form method="POST">
                            <input type="hidden" name="room-id" value="<?php echo htmlspecialchars($roomID); ?>">
                            <div class="form-outline mb-4">
                                <input type="date" id="beginDate" name="begin-date" class="form-control" required>
                                <label class="form-label" for="beginDate">Begin Date</label>
                            </div>
                            <div class="form-outline mb-4">
                                <input type="time" id="beginTime" name="begin-time" class="form-control" required>
                                <label class="form-label" for="beginTime">Begin Time</label>
                            </div>
                            <div class="form-outline mb-4">
                                <input type="date" id="endDate" name="end-date" class="form-control" required>
                                <label class="form-label" for="endDate">End Date</label>
                            </div>
                            <div class="form-outline mb-4">
                                <input type="time" id="endTime" name="end-time" class="form-control" required>
                                <label class="form-label" for="endTime">End Time</label>
                            </div>
                            <h5>Room Equipment</h5>
                            <ul class="list-group mb-4">
                                <?php if ($detail): ?>
                                    <?php foreach ($detail as $dt): ?>
                                        <li class="list-group-item"><?php echo htmlspecialchars($dt['NAMESTUFF']); ?>
                                            (<?php echo htmlspecialchars($dt['NUMOFSTUFF']); ?>)</li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item">This room has no equipment.</li>
                                <?php endif; ?>
                            </ul>

                            <button type="submit" name="btn-addList" class="btn btn-primary btn-block mb-2">
                                <i class="far fa-calendar-plus"></i> ADD TO LIST
                            </button>
                            <button type="submit" name="btn-addbooking" class="btn btn-success btn-block">
                                <i class="fas fa-square-plus"></i> BOOKING
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/time.js"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <?php include_once "menus/footer.php"; ?>
    <script>
        function showImage() {
            Swal.fire({
                imageUrl: "<?php echo htmlspecialchars($room['ROOMIMG'] ?? '..\assets\img\uploads\meetingrooms\noimg.png'); ?>",
                title: "<?php echo htmlspecialchars($room['ROOMNAME'] ?? '') ?>",
                //text: "Modal with a custom image.",
                imageHeight: 400,
                imageWidth: 600,
                imageAlt: "Room Image"
            });
        }
    </script>
</body>

</html>
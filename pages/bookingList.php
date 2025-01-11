<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
include_once "../config/function.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "bookingList";
include_once "menus/navbar.php";

// Modified query to explicitly cast or convert numeric fields
$queryRes = "SELECT bd.BUILDINGNAME, mrt.ROOMNAME, mrt.ROOMID, f.FLOORNUMBER, t.TYPENAME, 
        TO_NUMBER(mrt.CAPACITY) as CAPACITY, b.DATEBEGIN, b.DATEEND,
        r.RESERVATIONID, r.RESERVATIONDATE, b.BOOKINGID, t.APPROVALREQUIRED, rbs.STATUSID
FROM BOOKING b
JOIN MEETINGROOM mrt ON mrt.ROOMID = b.ROOMID
JOIN FLOOR f ON f.FLOORID = mrt.FLOORID
JOIN BUILDING bd ON bd.BUILDINGID = f.BUILDINGID
JOIN RESERVATION r ON r.RESERVATIONID = b.RESERVATIONID
JOIN TYPEROOM t ON t.TYPEID = mrt.TYPEID
LEFT JOIN ROOMBOOKINGSTATUS rbs ON rbs.BOOKINGID = b.BOOKINGID 
WHERE r.EmployeeID = ? AND rbs.STATUSID IS NULL";
$stmtRes = $db->Query($queryRes, [$_SESSION['EmployeeID']], true) ?? [];

$queryCount = "SELECT COUNT(*) as COUNTROOMS
    FROM BOOKING b
    JOIN MEETINGROOM mrt ON mrt.ROOMID = b.ROOMID
    JOIN FLOOR f ON f.FLOORID = mrt.FLOORID
    JOIN BUILDING bd ON bd.BUILDINGID = f.BUILDINGID
    JOIN RESERVATION r ON r.RESERVATIONID = b.RESERVATIONID
    JOIN TYPEROOM t ON t.TYPEID = mrt.TYPEID
    LEFT JOIN ROOMBOOKINGSTATUS rbs ON rbs.BOOKINGID = b.BOOKINGID 
    WHERE r.EmployeeID = ? AND rbs.STATUSID IS NULL";
$CountRes = $db->Query($queryCount, [$_SESSION['EmployeeID']]);

$timestamp = date('Y-m-d H:i:s');

$Timequery = "SELECT b.DATEBEGIN, b.DATEEND, b.ROOMID
FROM ROOMBOOKINGSTATUS rbk
JOIN BOOKING b ON b.BOOKINGID = rbk.BOOKINGID
WHERE b.ROOMID = ? AND (
      (rbk.DATEBEGIN <= TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS') AND rbk.DATEEND >= TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS')) OR
      (rbk.DATEBEGIN BETWEEN TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS') AND TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS')) OR
      (rbk.DATEEND BETWEEN TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS') AND TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))
)";


if (isset($_POST['btn-cancel'])) {
    $resID = (int) $_POST['reserID'];
    $bookingID = (int) $_POST['bookingID'];

    $queryDelBooking = "DELETE FROM BOOKING WHERE BOOKINGID = ?";
    $stmt = $db->nonQuery($queryDelBooking, [$bookingID]);

    if ($stmt) {
        $_SESSION['success'] = "กดยกเลิกเรียบร้อยแล้ว";
        if ($CountRes['COUNTROOMS'] > 1) {
            $stmtRes = $db->Query($queryRes, [$_SESSION['EmployeeID']], true);
        } else {
            $stmtRes = [];
        }
    } else {
        $_SESSION['error'] = "กดยกเลิกไม่สำเร็จ";
    }
}

if (isset($_POST['btn-confirm'])) {
    $approvRq = (int) $_POST['approvRequired'];
    $bookingID = (int) $_POST['bookingID'];
    $RoomID = (int) $_POST['roomID'];
    $ROOMNAME = $_POST['roomname'];
    $statusID = 1; // 1.Pending, 2.Confirm, 3.Cancel

    $queryTime = "SELECT 
    TO_CHAR(b.DATEBEGIN, 'YYYY-MM-DD HH24:MI:SS') as DATEBEGIN,
    TO_CHAR(b.DATEEND, 'YYYY-MM-DD HH24:MI:SS') as DATEEND
FROM BOOKING b
WHERE b.ROOMID = ? AND b.BOOKINGID = ?";

    $stmlQueryTime = $db->Query($queryTime, [$RoomID, $bookingID], false);

    $formattedBeginDateTime = $stmlQueryTime['DATEBEGIN'] ?? '';
    $formattedEndDateTime = $stmlQueryTime['DATEEND'] ?? '';

    if ($formattedBeginDateTime && $formattedEndDateTime) {
        $stmlTime = $db->Query($Timequery, [
            $RoomID,
            $formattedBeginDateTime,
            $formattedEndDateTime,
            $formattedBeginDateTime,
            $formattedEndDateTime,
            $formattedBeginDateTime,
            $formattedEndDateTime
        ]);
    }
    if ($stmlTime == null) {
        // Get next BOOKINGSTATUS_NO
        $queryConfirm = "SELECT NVL(MAX(TO_NUMBER(BOOKINGSTATUS_NO)), 0) as MAXID FROM ROOMBOOKINGSTATUS";
        $stmtMaxID = $db->Query($queryConfirm, [], false, true);
        $maxID = (int) $stmtMaxID['MAXID'] + 1;

        // Get next BOOK_ID
        $maxIDQuery = "SELECT NVL(MAX(TO_NUMBER(BOOK_ID)), 0) as MAXID FROM ROOMBOOKING";
        $stmtBookingMaxID = $db->Query($maxIDQuery, [], false, true);
        $maxbookID = (int) $stmtBookingMaxID['MAXID'] + 1;

        // Insert into ROOMBOOKING
        $insertBookingQuery = "INSERT INTO ROOMBOOKING (BOOK_ID, ROOMID) VALUES (?, ?)";
        $paramRoombooking = [$maxbookID, $RoomID];
        $insertRoomBooking = $db->nonQuery($insertBookingQuery, $paramRoombooking);

        if ($approvRq == 1) {
            $queryStatus = "INSERT INTO ROOMBOOKINGSTATUS 
                (BOOKINGSTATUS_NO, BOOKINGID, STATUSID, DATEBEGIN, DATEEND) 
                VALUES (?, ?, ?, 
                    TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'),
                    TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))";

            $insertStatus = $db->nonQuery($queryStatus, [
                $maxID,
                $bookingID,
                $statusID,
                $formattedBeginDateTime,
                $formattedEndDateTime
            ]);

            if ($insertStatus) {
                $_SESSION['success'] = "จองห้องประชุมสำเร็จ กรุณารอแอดมินอนุมัติ!!!";
            } else {
                $_SESSION['error'] = "ไม่สามารถจองห้องประชุมได้!!";
            }
        } else {
            // Normal room handling (auto-confirm)
            $statusID = 2;
            $queryStatus = "INSERT INTO ROOMBOOKINGSTATUS 
                (BOOKINGSTATUS_NO, BOOKINGID, STATUSID, DATEBEGIN, DATEEND) 
                VALUES (?, ?, ?, 
                    TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'),
                    TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))";

            $insertStatus = $db->nonQuery($queryStatus, [
                $maxID,
                $bookingID,
                $statusID,
                $formattedBeginDateTime,
                $formattedEndDateTime
            ]);

            if ($insertStatus) {
                $_SESSION['success'] = "จองห้องประชุมสำเร็จ";
            } else {
                $_SESSION['error'] = "ไม่สามารถจองห้องประชุมได้";
                return;
            }
        }

        // Generate and insert QR code
        $qrCodeInfo = generateUniqueCode($db);
        $queryQr = "INSERT INTO QRCODE 
                   (QRCODEID, CODE, BOOKINGID, ISUSE, QRCODE_GENERATED_DATE) 
                   VALUES (?, ?, ?, ?, TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))";

        $paramQr = [
            $qrCodeInfo['MAXID'],
            $qrCodeInfo['CODE'],
            $bookingID,
            0,
            $timestamp
        ];

        $insertQr = $db->nonQuery($queryQr, $paramQr);

        // Refresh the reservation list
        $stmtRes = $db->Query($queryRes, [$_SESSION['EmployeeID']], true);
    } else {
        $_SESSION['error'] = "ห้อง [$ROOMNAME] ถูกจองในช่วงเวลาดังกล่าวแล้ว โปรดยกเลิกการจองหรือระบบจะลบการจองโดยอัตโนมัติ";
    }
}
if (isset($_POST['btn-confirm-all'])) {
    if (isset($stmtRes)) {
        $statusID = '1'; // เริ่มที่สถานะ Pending
        $queryConfirm = "SELECT COALESCE(MAX(BOOKINGSTATUS_NO),0) as MAXID FROM ROOMBOOKINGSTATUS";
        $stmtMaxID = $db->Query($queryConfirm, false, false, true);
        $maxID = $stmtMaxID['MAXID'];

        // เก็บรายชื่อห้องที่มีการจองซ้ำซ้อน
        $conflictRooms = [];
        // เก็บรายชื่อห้องที่จองสำเร็จ
        $successRooms = [];

        foreach ($stmtRes as $RES) {
            $maxID++;
            $approvRq = $RES['APPROVALREQUIRED'];
            $bookingID = $RES['BOOKINGID'];
            $RoomID = $RES['ROOMID'];
            $ROOMNAME = $RES['ROOMNAME'];

            $queryTime = "SELECT 
                TO_CHAR(b.DATEBEGIN, 'YYYY-MM-DD HH24:MI:SS') as DATEBEGIN,
                TO_CHAR(b.DATEEND, 'YYYY-MM-DD HH24:MI:SS') as DATEEND
            FROM BOOKING b
            WHERE b.ROOMID = ? AND b.BOOKINGID = ?";

            $stmlQueryTime = $db->Query($queryTime, [$RoomID, $bookingID], false);
            $formattedBeginDateTime = $stmlQueryTime['DATEBEGIN'] ?? '';
            $formattedEndDateTime = $stmlQueryTime['DATEEND'] ?? '';

            if ($formattedBeginDateTime && $formattedEndDateTime) {
                $stmlTime = $db->Query($Timequery, [
                    $RoomID,
                    $formattedBeginDateTime,
                    $formattedEndDateTime,
                    $formattedBeginDateTime,
                    $formattedEndDateTime,
                    $formattedBeginDateTime,
                    $formattedEndDateTime
                ]);
            }

            if ($stmlTime == null) {
                // Get next BOOK_ID
                $maxIDQuery = "SELECT COALESCE(MAX(TO_NUMBER(BOOK_ID)), 0) as MAXID FROM ROOMBOOKING";
                $stmtBookingMaxID = $db->Query($maxIDQuery, [], false, true);
                $maxbookID = (int) $stmtBookingMaxID['MAXID'] + 1;

                // Insert into ROOMBOOKING
                $insertBookingQuery = "INSERT INTO ROOMBOOKING (BOOK_ID, ROOMID) VALUES (?, ?)";
                $paramRoombooking = [$maxbookID, $RoomID];
                $insertRoomBooking = $db->nonQuery($insertBookingQuery, $paramRoombooking);

                if (!$insertRoomBooking) {
                    throw new Exception("ไม่สามารถบันทึกข้อมูลการจองสำหรับห้อง $ROOMNAME");
                }

                // กำหนดสถานะตามประเภทห้อง
                $currentStatusID = ($approvRq == '1') ? '1' : '2';

                // บันทึกสถานะการจอง
                $queryStatus = "INSERT INTO ROOMBOOKINGSTATUS 
                    (BOOKINGSTATUS_NO, BOOKINGID, STATUSID, DATEBEGIN, DATEEND) 
                    VALUES (?, ?, ?, TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'), 
                            TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))";

                $insertStatus = $db->nonQuery(
                    $queryStatus,
                    [$maxID, $bookingID, $currentStatusID, $formattedBeginDateTime, $formattedEndDateTime]
                );

                // สร้างและบันทึก QR Code
                $qrCodeInfo = generateUniqueCode($db);
                $queryQr = "INSERT INTO QRCODE 
                    (QRCODEID, CODE, BOOKINGID, ISUSE, QRCODE_GENERATED_DATE) 
                    VALUES (?, ?, ?, ?, TO_TIMESTAMP(?, 'YYYY-MM-DD HH24:MI:SS'))";

                $paramQr = [
                    $qrCodeInfo['MAXID'],
                    $qrCodeInfo['CODE'],
                    $bookingID,
                    0,
                    $timestamp
                ];

                $insertQr = $db->nonQuery($queryQr, $paramQr);

                if ($insertQr) {
                    $successRooms[] = $ROOMNAME; // เก็บชื่อห้องที่จองสำเร็จ
                } else {
                    $_SESSION['error'] = "มีห้องที่ไม่สามารถสร้าง QR Code ได้";
                }
            } else {
                // หากมีการจองซ้ำซ้อน
                $conflictRooms[] = $ROOMNAME; // เก็บชื่อห้องที่มีการจองอยู่
            }
        }

        // สรุปผลการจอง
        if (!empty($conflictRooms)) {
            $_SESSION['error'] = "ห้องต่อไปนี้ถูกจองอยู่แล้ว: " . implode(', ', $conflictRooms) . " โปรดยกเลิกการจองหรือระบบจะลบการจองโดยอัตโนมัติ";
        } elseif (!empty($successRooms)) {
            $_SESSION['success'] = "การจองทั้งหมดเสร็จสมบูรณ์สำหรับห้อง: " . implode(', ', $successRooms);
        }

        // Refresh the reservation list
        $stmtRes = $db->Query($queryRes, [$_SESSION['EmployeeID']], true);
    } else {
        $_SESSION['warning'] = "ไม่พบรายการห้องที่ต้องการจอง";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Booking List</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">RESERVATION</span>
        </div>
        <div class="container mt-5">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <!-- <th scope="col">ID RESERVATION</th> -->
                                    <th scope="col">ROOMS</th>
                                    <th scope="col">BOOKING TIME</th>
                                    <th scope="col">DETAIL</th>
                                    <th scope="col">CANCEL</th>
                                    <th scope="col">ACCEPT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stmtRes && !empty($stmtRes)): ?>
                                    <?php foreach ($stmtRes as $RES): ?>

                                        <tr>
                                        <tr>
                                            <form method="POST">
                                                <input type="hidden" name="reserID"
                                                    value="<?php echo htmlspecialchars($RES['RESERVATIONID']); ?>">
                                                <input type="hidden" name="bookingID"
                                                    value="<?php echo htmlspecialchars($RES['BOOKINGID']); ?>">
                                                <input type="hidden" name="roomID"
                                                    value="<?php echo htmlspecialchars($RES['ROOMID']); ?>">
                                                <input type="hidden" name="approvRequired"
                                                    value="<?php echo htmlspecialchars($RES['APPROVALREQUIRED']); ?>">
                                                <input type="hidden" name="roomname"
                                                    value="<?php echo htmlspecialchars($RES['ROOMNAME']); ?>">
                                                <!-- <td><?php //echo htmlspecialchars($RES['RESERVATIONID']); ?></td> -->
                                                <td><?php echo htmlspecialchars($RES['ROOMNAME']) ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($RES['DATEBEGIN']) . '<br> ถึง <br>' . htmlspecialchars($RES['DATEEND']); ?>
                                                <td>
                                                    BUILDING : <?php echo htmlspecialchars($RES['BUILDINGNAME']); ?><br>
                                                    FLOOR : <?php echo htmlspecialchars($RES['FLOORNUMBER']); ?> <br>
                                                    TYPE : <?php echo htmlspecialchars($RES['TYPENAME']); ?> <br>
                                                    CAPACITY : <?php echo htmlspecialchars($RES['CAPACITY']); ?>
                                                </td>
                                                <td>
                                                    <?php if (empty($RES['STATUSID'])): ?>
                                                        <button class="btn btn-danger" name="btn-cancel" id="btn-Cancel">
                                                            <i class="fas fa-xmark"></i> ยกเลิกการจอง
                                                        </button>
                                                    <?php else: ?>
                                                        <i>
                                                            <span>-</span>
                                                        </i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (empty($RES['STATUSID'])): ?>
                                                        <button class="btn btn-primary" name="btn-confirm" id="btn-Confirm">
                                                            <i class="fas fa-right-to-bracket"></i> ยืนยันการจอง
                                                        </button>
                                                    <?php elseif ($RES['STATUSID'] == '1'): ?>
                                                        <i>
                                                            <span>Panding</span>
                                                        </i>
                                                    <?php elseif ($RES['STATUSID'] == '2'): ?>
                                                        <i>
                                                            <span class="btn-complete">Complete</span>
                                                        </i>
                                                    <?php endif; ?>
                                                </td>
                                            </form>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">ไม่พบข้อมูลการจอง</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="contrainer">
                        <form method="POST">
                            <?php if (!empty($stmtRes)): ?>
                                <button class="btn btn-success" name="btn-confirm-all"><i class="far fa-calendar-check"></i>
                                    CONFIRM</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once "menus/footer.php"; ?>
</body>

</html>
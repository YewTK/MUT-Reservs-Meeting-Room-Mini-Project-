<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "myBooking";
include_once "menus/navbar.php";


$currentDateTime = date('Y-m-d H:i:s');
$queryAll = "SELECT 
    b.BOOKINGID,
    b.ROOMID,
    mrt.ROOMNAME,
    r.RESERVATIONID,
    b.DATEBEGIN,
    b.DATEEND,
    bd.BUILDINGNAME,
    f.FLOORNUMBER,
    t.TYPENAME,
    mrt.CAPACITY,
    rbs.BOOKINGSTATUS_NO,
    rbs.STATUSID,
    q.QRCODEID,
    q.CODE,
    d.DENYID,
    d.REASON_DENY,
    d.DENYDATE,
    q.ISUSE,
    qrc.SCANID,
    d.Reason_DENY
FROM BOOKING b
JOIN MEETINGROOM mrt ON mrt.ROOMID = b.ROOMID
JOIN FLOOR f ON f.FLOORID = mrt.FLOORID
JOIN BUILDING bd ON bd.BUILDINGID = f.BUILDINGID
JOIN RESERVATION r ON r.RESERVATIONID = b.RESERVATIONID
JOIN TYPEROOM t ON t.TYPEID = mrt.TYPEID
JOIN ROOMBOOKINGSTATUS rbs ON rbs.BOOKINGID = b.BOOKINGID 
JOIN QRCODE q ON b.BOOKINGID = q.BOOKINGID
LEFT JOIN DENYPERMIT d ON d.BOOKINGSTATUS_NO = rbs.BOOKINGSTATUS_NO
LEFT JOIN QRCODESCAN qrc ON q.QRCODEID = qrc.QRCODEID
WHERE r.EmployeeID = ? AND TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS') <= b.DATEEND
ORDER BY b.DATEBEGIN DESC";
$params = [
    $_SESSION['EmployeeID'],
    $currentDateTime
];
$stmtRes = $db->Query($queryAll, $params, true) ?? [];


if (isset($_POST['btn-cancel'])) {
    $bookingID = $_POST['booking_id'];
    $reservationID = $_POST['reservation_id'];
    $bookingstatusNO = $_POST['bookingstatus_no'];
    $qrcodeNo = $_POST['qrcode_id'];
    $roomID = $_POST['room_id'];
    $denyID = $_POST['deny_id'];
    $StatusID = $_POST['Status_id'];

    $queryDelQRcode = "DELETE FROM QRCODE WHERE QRCODEID = ?";
    $stmtDelQRcode = $db->nonQuery($queryDelQRcode, [$qrcodeNo]);
    if ($denyID != null) {
        $queryDelDeny = "DELETE FROM DENYPERMIT WHERE DENYID = ?";
        $stmlDElDeny = $db->nonQuery($queryDelDeny, [$denyID]);
    }
    $queryDelBookingStatus = "DELETE FROM ROOMBOOKINGSTATUS WHERE BOOKINGSTATUS_NO = ?";
    $stmtDelBookingStatus = $db->nonQuery($queryDelBookingStatus, [$bookingstatusNO]);

    $queryDelBooking = "DELETE FROM BOOKING WHERE BOOKINGID = ?";
    $stmtDelBooking = $db->nonQuery($queryDelBooking, [$bookingID]);

    // Check if all deletions were successfuls
    if ($stmtDelBookingStatus && $stmtDelBooking && $stmtDelQRcode) {

        // Refresh the booking list
        $stmtRes = $db->Query($queryAll, $params, true) ?? [];

        if ($StatusID != '3') {
            // Get MAXID for CANCELBOOKING
            $queryInsert = "SELECT COALESCE(MAX(CANCELID), 0) as MAXID FROM CANCELBOOKING";
            $stmtMaxID = $db->Query($queryInsert, false, false, true);
            $maxID = $stmtMaxID['MAXID'] + 1; // Increment MAXID

            // Use $reservationID for CANCELBOOKING
            $query = "INSERT INTO CANCELBOOKING (CANCELID, CANCELDATE, ROOMID) VALUES (?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'), ?)";
            $cancelDate = date('Y-m-d H:i'); // หรือใช้วันที่และเวลาปัจจุบัน
            $param = [$maxID, $cancelDate, $roomID];
            $insertCancel = $db->nonQuery($query, $param);
            if ($insertCancel) {
                $_SESSION['success'] = "กดยกเลิกเรียบร้อยแล้ว";
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการกดยกเลิก!!!";
            }
        } else {
            $_SESSION['success'] = "กดยกเลิกเรียบร้อยแล้ว";
        }

    } else {
        $_SESSION['error'] = "กดยกเลิกไม่สำเร็จ";
    }
}

if (isset($_POST['btn-premature'])) {
    $bookingID = $_POST['booking_id'];
    $bookingstatusNO = $_POST['bookingstatus_no'];
    $qrcodeID = $_POST['qrcode_id'];
    $scanID = $_POST['qrcode_scanid'];

    // 1. Delete QRCODESCAN first
    $queryDelQRcodeScan = "DELETE FROM QRCODESCAN WHERE SCANID = ?";
    $stmtDelQRcodeScan = $db->nonQuery($queryDelQRcodeScan, [$scanID]);

    // 2. Delete QRCODE
    $queryDelQRcode = "DELETE FROM QRCODE WHERE QRCODEID = ?";
    $stmtDelQRcode = $db->nonQuery($queryDelQRcode, [$qrcodeID]);

    // 3. Delete ROOMBOOKINGSTATUS
    $queryDelBookingStatus = "DELETE FROM ROOMBOOKINGSTATUS WHERE BOOKINGSTATUS_NO = ?";
    $stmtDelBookingStatus = $db->nonQuery($queryDelBookingStatus, [$bookingstatusNO]);

    // 4. Delete BOOKING
    $queryDelBooking = "DELETE FROM BOOKING WHERE BOOKINGID = ?";
    $stmtDelBooking = $db->nonQuery($queryDelBooking, [$bookingID]);

    if ($stmtDelBooking) {
        $stmtRes = $db->Query($queryAll, $params, true) ?? [];
        $_SESSION['success'] = "กดยกเลิกก่อนเวลาเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการกดยกเลิก";
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - My Booking</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.0/mdb.min.css" rel="stylesheet">
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">My Booking</span>
        </div>
        <div class="container mt-5">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <?php if ($stmtRes): ?>
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Room Name</th>
                                        <th scope="col">Booking Time</th>
                                        <th scope="col">Room Details</th>
                                        <th scope="col">Cancel</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">QR Code</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stmtRes as $RES): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($RES['ROOMNAME']); ?></td>
                                            <td><?php echo htmlspecialchars($RES['DATEBEGIN']) . '<br> ถึง <br>' . htmlspecialchars($RES['DATEEND']); ?>
                                            </td>
                                            <td>
                                                BUILDING : <?php echo htmlspecialchars($RES['BUILDINGNAME']); ?><br>
                                                FLOOR : <?php echo htmlspecialchars($RES['FLOORNUMBER']); ?><br>
                                                TYPE : <?php echo htmlspecialchars($RES['TYPENAME']); ?><br>
                                                CAPACITY : <?php echo htmlspecialchars($RES['CAPACITY']); ?>
                                            </td>
                                            <td>
                                                <form action="" method="post" style="display:inline;">
                                                    <input type="hidden" name="booking_id"
                                                        value="<?php echo htmlspecialchars($RES['BOOKINGID']); ?>">
                                                    <input type="hidden" name="reservation_id"
                                                        value="<?php echo htmlspecialchars($RES['RESERVATIONID']); ?>">
                                                    <input type="hidden" name="bookingstatus_no"
                                                        value="<?php echo htmlspecialchars($RES['BOOKINGSTATUS_NO']); ?>">
                                                    <input type="hidden" name="room_id"
                                                        value="<?php echo htmlspecialchars($RES['ROOMID']); ?>">
                                                    <input type="hidden" name="qrcode_id"
                                                        value="<?php echo htmlspecialchars($RES['QRCODEID']); ?>">
                                                    <input type="hidden" name="deny_id"
                                                        value="<?php echo htmlspecialchars($RES['DENYID']); ?>">
                                                    <input type="hidden" name="qrcode_scanid"
                                                        value="<?php echo htmlspecialchars($RES['SCANID']); ?>">
                                                    <input type="hidden" name="Status_id"
                                                        value="<?php echo htmlspecialchars($RES['STATUSID']); ?>">

                                                    <?php if ($RES['ISUSE'] == 0): ?>
                                                        <button class="btn btn-danger" name="btn-cancel" id="btn-Cancel">
                                                            <i class="fas fa-xmark"></i> ยกเลิกการจอง
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-info" name="btn-premature" id="btn-Premature"
                                                            data-mdb-ripple-init>
                                                            <i class="fas fa-door-open"></i> กำหนดออกห้องก่อนเวลา
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($RES['STATUSID'] == '2'): ?>
                                                    <button type="button" class="btn btn-success" data-mdb-ripple-init disabled>
                                                        <i class="fas fa-check"></i> Success
                                                    </button>
                                                <?php elseif ($RES['STATUSID'] == '1'): ?>
                                                    <button type="button" class="btn btn-secondary" data-mdb-ripple-init disabled>
                                                        <i class="fas fa-spinner"></i> Pending
                                                    </button>
                                                <?php else: ?>
                                                    <div class="d-flex flex-column gap-2">
                                                        <button type="button" class="btn btn-danger" data-mdb-ripple-init disabled>
                                                            <i class="fas fa-ban"></i> Deny

                                                        </button>
                                                        <?php if (!is_null($RES['REASON_DENY'])): ?>
                                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                                data-mdb-ripple-init
                                                                onclick="showDenyReason('<?php echo htmlspecialchars($RES['REASON_DENY']); ?>')">
                                                                <i class="fas fa-info-circle"></i> View Reason
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($RES['STATUSID'] == '2'): ?>
                                                    <div class="qr-code" data-code="<?php echo htmlspecialchars($RES['CODE']); ?>"
                                                        style="display: block;"></div>
                                                <?php else: ?>
                                                    <div class="qr-code">
                                                        <img src="../assets/img/uploads/df-qr/pending-qr.png" alt="Qr-Code">
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div colspan="6" class="text-center">ไม่พบข้อมูลการจอง</div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Modal for showing deny reason -->
                <div class="modal fade" id="denyReasonModal" tabindex="-1" aria-labelledby="denyReasonModalLabel"
                    aria-hidden="true" data-mdb-backdrop="static">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="denyReasonModalLabel">
                                    <i class="fas fa-exclamation-circle"></i> Denial Reason
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p id="denyReasonText"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal"
                                    data-mdb-ripple-init>Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once "menus/footer.php"; ?>
    <script src="../assets/js/qrcode.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

</body>

<script>
    function showDenyReason(reason) {
        document.getElementById('denyReasonText').textContent = reason;
        const myModal = document.getElementById('denyReasonModal');
        const modal = new mdb.Modal(myModal);
        modal.show();
    }
</script>

</html>
<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = 'useCode';
include_once "menus/navbar.php";


$currentDateTime = date('Y-m-d H:i:s');
$query = "SELECT * 
FROM QRCODE q
JOIN BOOKING b ON b.BOOKINGID = q.BOOKINGID
JOIN MEETINGROOM mtr ON b.ROOMID = mtr.ROOMID
JOIN RESERVATION r ON r.RESERVATIONID = b.RESERVATIONID
JOIN EMPLOYEE e ON e.EMPLOYEEID = r.EMPLOYEEID
JOIN ROOMBOOKINGSTATUS rbk ON rbk.BOOKINGID = b.BOOKINGID
WHERE e.EMPLOYEEID = ?
AND TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS') <= rbk.DATEEND 
AND TO_DATE(?, 'YYYY-MM-DD HH24:MI:SS') >= rbk.DATEBEGIN
AND rbk.STATUSID = 2";
$params = [
    $_SESSION['EmployeeID'],
    $currentDateTime,
    $currentDateTime
];
$stmlquery = $db->Query($query, $params, true);



if (isset($_POST['use_code'])) {
    $qrcodeId = $_POST['qrcode_id'];
    $submittedCode = $_POST['message']; // Get the submitted code
    $roomId = $_POST['room_id'];

    // Check if the QR code exists and matches
    $queryCheckqr = "SELECT QRCODEID, CODE FROM QRCODE WHERE QRCODEID = ? AND CODE = ?";
    $params = [$qrcodeId, $submittedCode];
    $Checkqr = $db->Query($queryCheckqr, $params);

    if ($Checkqr && !empty($Checkqr)) {
        // Check if code is already used
        $queryCheckUsed = "SELECT ISUSE FROM QRCODE WHERE QRCODEID = ?";
        $checkUsed = $db->Query($queryCheckUsed, [$qrcodeId]);

        // Update ISUSE status
        $updateisusse = "UPDATE QRCODE SET ISUSE = 1 WHERE QRCODEID = ?";
        $stmlupdateIsuse = $db->nonQuery($updateisusse, [$qrcodeId]);

        if ($stmlupdateIsuse) {
            // Get next SCANID
            $queryInsert = "SELECT COALESCE(MAX(SCANID), 0) as MAXID FROM QRCODESCAN";
            $stmtMaxID = $db->Query($queryInsert);
            $maxID = $stmtMaxID[0]['MAXID'] + 1;

            // Insert scan record
            $insertQrcodeScan = "INSERT INTO QRCODESCAN (SCANID, QRCODEID, SCANDATE) 
                                   VALUES (?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'))";
            $TimeCurrentDate = date('Y-m-d H:i');
            $param = [$maxID, $qrcodeId, $TimeCurrentDate];
            $insertStatus = $db->nonQuery($insertQrcodeScan, $param);

            $queryInsertUsingroom = "SELECT COALESCE(MAX(USINGID), 0) as MAXID FROM USINGROOM";
            $stmtMaxIDusing = $db->Query($queryInsertUsingroom);
            $maxIDusingroom = $stmtMaxIDusing[0]['MAXID'] + 1;

            // Insert scan record
            $insertusingRoom = "INSERT INTO UsingRoom (UsingID, ROOMID, USINGDATE) 
                                   VALUES (?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'))";
            $TimeCurrentDate = date('Y-m-d H:i');
            $paramusingroom = [$maxIDusingroom, $roomId, $TimeCurrentDate];
            $insertUsing = $db->nonQuery($insertusingRoom, $paramusingroom);

            if ($insertStatus && $insertUsing) {
                $_SESSION['success'] = "ใช้งาน Code สำเร็จ";
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }

        } else {
            $_SESSION['error'] = "Code นี้ถูกใช้งานไปแล้ว";
        }
    } else {
        $_SESSION['error'] = "Code ไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>USE CODE</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">USE CODE</span>
        </div>
        <div class="container mt-5">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>QRCODE ID</th>
                                    <th>Booking Info</th>
                                    <th>Date Begin</th>
                                    <th>Date End</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stmlquery && is_array($stmlquery)): ?>
                                    <?php foreach ($stmlquery as $qr): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($qr['QRCODEID']); ?></td>
                                            <td>
                                                BOOKING ID: <?php echo htmlspecialchars($qr['BOOKINGID']); ?><br>
                                                ROOM ID: <?php echo htmlspecialchars($qr['ROOMID']); ?><br>
                                                ROOM name: <?php echo htmlspecialchars($qr['ROOMNAME']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($qr['DATEBEGIN']); ?></td>
                                            <td><?php echo htmlspecialchars($qr['DATEEND']); ?></td>
                                            <td>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="qrcode_id"
                                                        value="<?php echo htmlspecialchars($qr['QRCODEID']); ?>">
                                                    <input type="hidden" name="room_id"
                                                        value="<?php echo htmlspecialchars($qr['ROOMID']); ?>">
                                                    <div class="form-group">
                                                        <textarea name="message" class="form-control mb-2"
                                                            placeholder="Enter Code..." required></textarea>
                                                        <button type="submit" name="use_code" class="btn btn-primary">Use
                                                            Code</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once "menus/footer.php"; ?>
</body>

</html>
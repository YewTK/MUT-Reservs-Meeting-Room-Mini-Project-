<?php
ob_start();
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "roomsPermission";
include_once "menus/navbar.php";

if (!isset($permissions) || $permissions[5] != '1') {
    header('Location: home.php');
    exit();
}
$query = "SELECT DISTINCT 
    b.BOOKINGID,
    mr.ROOMID,
    rbs.bookingstatus_NO,
    e.EMPLOYEEID,
    e.FNAME,
    e.LNAME,
    rt.TYPENAME,
    mr.ROOMNAME
FROM BOOKING b
JOIN MEETINGROOM mr ON mr.ROOMID = b.ROOMID
JOIN FLOOR f ON f.FLOORID = mr.FLOORID
JOIN BUILDING bld ON bld.BUILDINGID = f.BUILDINGID
JOIN RESERVATION res ON res.RESERVATIONID = b.RESERVATIONID
JOIN EMPLOYEE e ON res.EMPLOYEEID = e.EMPLOYEEID
JOIN TYPEROOM rt ON rt.TYPEID = mr.TYPEID
JOIN ROOMBOOKINGSTATUS rbs ON rbs.BOOKINGID = b.BOOKINGID 
WHERE rbs.STATUSID = 1";
$stmtRes = $db->Query($query);


if (isset($_POST['deny-button'])) {
    $bookingID = $_POST['booking_id'] ?? '';
    $bookID = $_POST['book_id'] ?? '';
    $roomID = $_POST['room_id'] ?? '';
    $reason = $_POST['message'] ?? '';
    $statusNo = $_POST['bookingstatus_NO'] ?? '';

    if (!empty($bookingID) && !empty($bookID) && !empty($reason) && !empty($statusNo)) {
        $maxIDQuery = "SELECT COALESCE(MAX(DENYID), 0) as MAXID FROM DENYPERMIT";
        $stmtMaxID = $db->Query($maxIDQuery, false, false, true);
        $maxID = $stmtMaxID['MAXID'] + 1;

        $insertDenyQuery = "INSERT INTO DENYPERMIT (DENYID, REASON_DENY, DENYDATE, BOOKINGSTATUS_NO) VALUES (?, ?, TO_DATE(?, 'YYYY-MM-DD HH24:MI'), ?)";
        $DenyDate = date('Y-m-d H:i');
        $param = [$maxID, $reason, $DenyDate, $statusNo];
        $insertDeny = $db->nonQuery($insertDenyQuery, $param);

        if ($insertDeny) {
            $statusChange = "UPDATE ROOMBOOKINGSTATUS SET STATUSID = 3 WHERE BOOKINGID = ?";
            $db->nonQuery($statusChange, [$bookingID]);
            $_SESSION['success'] = "Booking denied successfully";
            $stmtRes = $db->Query($query);
        } else {
            $_SESSION['error'] = "ไม่สามารถปฏิเสธการจองได้";
        }
    } else {
        $_SESSION['error'] = "Missing required information for denial";
    }
}

if (isset($_POST['btn-success'])) {
    $bookingID = $_POST['booking_id'] ?? '';

    if (!empty($bookingID)) {
        $statusChange = "UPDATE ROOMBOOKINGSTATUS SET STATUSID = 2 WHERE BOOKINGID = ?";
        $updated = $db->nonQuery($statusChange, [$bookingID]);

        if ($updated) {
            $_SESSION['success'] = "Booking Permit successfully";
            $stmtRes = $db->Query($query);
        } else {
            $_SESSION['error'] = "Can't permit this booking";
        }
    } else {
        $_SESSION['error'] = "Missing booking ID for permit";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - My Booking</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">Permission</span>
        </div>
        <div class="container mt-5">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">EmployeeID</th>
                                    <th scope="col">EMPLOYEENAME</th>
                                    <th scope="col">Room Details</th>
                                    <th scope="col">Deny</th>
                                    <th scope="col">Permit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($stmtRes)): ?>
                                    <?php foreach ($stmtRes as $RES): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($RES['EMPLOYEEID']); ?></td>
                                            <td><?php echo htmlspecialchars($RES['FNAME']) . ' ' . htmlspecialchars($RES['LNAME']); ?>
                                            </td>
                                            <td>
                                                ROOMID: <?php echo htmlspecialchars($RES['ROOMID']); ?><br>
                                                ROOMNAME: <?php echo htmlspecialchars($RES['ROOMNAME']); ?><br>
                                                TYPEROOM: <?php echo htmlspecialchars($RES['TYPENAME']); ?>
                                            </td>
                                            <td>
                                                <form action="" method="post" class="deny-form">
                                                    <input type="hidden" name="room_id"
                                                        value="<?php echo htmlspecialchars($RES['ROOMID']); ?>">
                                                    <input type="hidden" name="bookingstatus_NO"
                                                        value="<?php echo htmlspecialchars($RES['BOOKINGSTATUS_NO']); ?>">
                                                    <input type="hidden" name="book_id"
                                                        value="<?php echo htmlspecialchars($RES['BOOK_ID']); ?>">
                                                    <input type="hidden" name="booking_id"
                                                        value="<?php echo htmlspecialchars($RES['BOOKINGID']); ?>">
                                                    <textarea name="message" placeholder="Reason for denial..." required></textarea>
                                                    <button class="btn btn-danger" name="deny-button">
                                                        <i class="fas fa-xmark"></i> Deny
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <form action="" method="post" class="permit-form">
                                                    <input type="hidden" name="booking_id"
                                                        value="<?php echo htmlspecialchars($RES['BOOKINGID']); ?>">
                                                    <button class="btn btn-success" name="btn-success">
                                                        <i class="fas fa-check"></i> Permit
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">ไม่พบข้อมูลที่รอการอนุมัติ</td>
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
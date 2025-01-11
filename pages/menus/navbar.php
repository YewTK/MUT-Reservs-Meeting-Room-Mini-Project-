<?php
ob_start();
include_once "../config/connection.php";
$db = new connectDB();
$db->InitSession("../index.php");
$emp = $db->getEmployee();
$query = "SELECT PageAccess FROM Position WHERE PositionID = ?";
$stmt = $db->Query($query, [$emp['POSITIONID']]);
$empAccessPage = $stmt['PAGEACCESS'];
$permissions = str_split($empAccessPage);

$query = "SELECT COUNT(*) as COUNTROOMS
FROM BOOKING b
JOIN MEETINGROOM mrt ON mrt.ROOMID = b.ROOMID
JOIN FLOOR f ON f.FLOORID = mrt.FLOORID
JOIN BUILDING bd ON bd.BUILDINGID = f.BUILDINGID
JOIN RESERVATION r ON r.RESERVATIONID = b.RESERVATIONID
JOIN TYPEROOM t ON t.TYPEID = mrt.TYPEID
LEFT JOIN ROOMBOOKINGSTATUS rbs ON rbs.BOOKINGID = b.BOOKINGID 
WHERE r.EmployeeID = ? AND rbs.STATUSID IS NULL";
$stmtNewRes = $db->Query($query, [$_SESSION['EmployeeID']]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light"
        style="background-color: #A31D25; padding: 0 0 0 10px; height: 55px; width: 100%; position: sticky; top: 0;"
        data-mdb-theme="light">
        <a class="navbar-brand" href="#">
            <img src="../assets/img/LOGO-Mut.png" class="d-inline-block align-text-top mut" alt="MUT Logo">
        </a>

        <button data-mdb-collapse-init class="navbar-toggler" type="button" data-mdb-target="#navbarText"
            aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse all-menu" id="navbarText">
            <!-- Navbar Links -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (isset($permissions[0]) && $permissions[0] == '1'): ?>
                <li class="nav-item">
                    <a class="nav-link section-a" href="home.php#homepage">HOME</a>
                </li>
                <?php endif; ?>
                <?php if (isset($permissions[1]) && $permissions[1] == '1'): ?>
                <li class="nav-item">
                    <a class="nav-link section-a" href="home.php#meetingroom">MEETINGROOM</a>
                </li>
                <?php endif; ?>
                <?php if (isset($permissions[2]) && $permissions[2] == '1'): ?>
                <li class="nav-item">
                    <a class="nav-link section-a" href="home.php#contact">CONTACT</a>                        
                </li>
                <?php endif; ?>
                <?php if (isset($permissions[3]) && $permissions[3] == '1'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                        data-mdb-toggle="dropdown" aria-expanded="false">
                        REPORT
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'reportLockEmp' ? 'dropdown-item-active' : '' ?>"
                                href="report_Locked_Employee.php">REPORT LOCKEMPLOYEE</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'reportMeetingUsing' ? 'dropdown-item-active' : '' ?>"
                                href="report_Meeting_Using.php">REPORT MEETING USING</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'reportUsingCancel' ? 'dropdown-item-active' : '' ?>"
                                href="report_Using_Cancel.php">REPORT USING CANCEL</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (isset($permissions[4]) && $permissions[4] == '1'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                        data-mdb-toggle="dropdown" aria-expanded="false">
                        MANAGEMENT
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'manageEmp' ? 'dropdown-item-active' : '' ?>"
                                href="manageEmp.php">EMPLOYEE</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'manageMtgRm' ? 'dropdown-item-active' : '' ?>"
                                href="manageMtgRm.php">MEETING ROOM</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'manageLockEmp' ? 'dropdown-item-active' : '' ?>"
                                href="manageLockEmp.php">LOCK EMPLOYEE</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'managePosition' ? 'dropdown-item-active' : '' ?>"
                                href="managePosition.php">POSITION</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'manageBuilding' ? 'dropdown-item-active' : '' ?>"
                                href="manageBuilding.php">BUILDING</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (isset($permissions[5]) && $permissions[5] == '1'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $_SESSION['pageName'] === 'roomsPermission' ? 'dropdown-item-active' : '' ?>"
                        href="roomsPermission.php">ROOMPERMISSION</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a  class="nav-link-cart <?= $_SESSION['pageName'] === 'bookingList' ? 'dropdown-item-active' : '' ?>"
                        href="bookingList.php" onmouseover="this.style.backgroundColor='#FF9F14'"
                        onmouseout="this.style.backgroundColor='#6A040F'">
                        <i class="fas fa-bell" style="font-size:22px"></i>
                        <?php if ($stmtNewRes['COUNTROOMS'] !== '0'): ?>
                        <span class="badge rounded-pill badge-notification bg-danger"
                            style="padding: 0 auto; margin-left: 15px;"><?php echo $stmtNewRes['COUNTROOMS']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button"
                        data-mdb-toggle="dropdown" aria-expanded="false">
                        <?php echo htmlspecialchars($emp['FNAME']); ?>
                        <?php if (!empty($emp['USERPROFILE'])): ?>
                        <img class="profile-navbar mx-1" src="<?php echo htmlspecialchars($emp['USERPROFILE']); ?>"
                            alt="User Profile" width="64" height="64">
                        <?php else: ?>
                        <i class="far fa-circle-user mx-1" style="font-size: 38px; cursor: pointer;"></i>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownUser">
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'myProfile' ? 'dropdown-item-active' : '' ?>"
                                href="myProfile.php">MY PROFILE</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'changPwd' ? 'dropdown-item-active' : '' ?>"
                                href="changPwd.php">CHANG PASSWORD</a></li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'myBooking' ? 'dropdown-item-active' : '' ?>"
                                href="myBooking.php">MY BOOKING</a></li>
                        <li>
                        <li><a class="dropdown-item <?= $_SESSION['pageName'] === 'useCode' ? 'dropdown-item-active' : '' ?>"
                                href="useCode.php">USE CODE</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><button class="dropdown-item" id="LogoutButton">LOGOUT</button></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoutButton = document.getElementById('LogoutButton');

        if (logoutButton) {
            logoutButton.addEventListener('click', function(event) {
                event.preventDefault(); // ป้องกันไม่ให้เปลี่ยนเส้นทางทันที

                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: "คุณต้องการออกจากระบบ!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ใช่, ออกจากระบบ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'ออกจากระบบสำเร็จ!',
                            text: 'คุณจะถูกเปลี่ยนเส้นทาง...',
                            icon: 'success',
                            timer: 1200,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '../pages/logout.php';
                        });
                    }
                });
            });
        }
    });
    </script>
</body>

</html>